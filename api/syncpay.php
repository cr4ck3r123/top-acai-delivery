<?php
declare(strict_types=1);

const SYNCPAY_API_URL = 'https://api.syncpayments.com.br/api/partner/v1';

final class SyncPayApiException extends RuntimeException
{
}

function syncPayConfig(): array
{
    $clientId = trim((string) getenv('SYNCPAY_CLIENT_ID'));
    $clientSecret = trim((string) getenv('SYNCPAY_CLIENT_SECRET'));
    $webhookSecret = trim((string) getenv('SYNCPAY_WEBHOOK_SECRET'));
    $baseUrl = rtrim(trim((string) getenv('APP_BASE_URL')), '/');
    $urlParts = parse_url($baseUrl);
    $missingVariables = [];
    foreach ([
        'SYNCPAY_CLIENT_ID' => $clientId,
        'SYNCPAY_CLIENT_SECRET' => $clientSecret,
        'SYNCPAY_WEBHOOK_SECRET' => $webhookSecret
    ] as $name => $value) {
        if ($value === '') {
            $missingVariables[] = $name;
        }
    }
    if ($missingVariables !== []) {
        throw new RuntimeException(
            'Preencha no arquivo .env as variáveis obrigatórias: ' . implode(', ', $missingVariables) . '.'
        );
    }
    if (preg_match('#^https?://#i', $webhookSecret)) {
        throw new RuntimeException(
            'SYNCPAY_WEBHOOK_SECRET contém uma URL. Informe o segredo de assinatura configurado no webhook da SyncPay.'
        );
    }

    if ($urlParts === false || ($urlParts['scheme'] ?? null) !== 'https'
        || !isset($urlParts['host']) || isset($urlParts['user']) || isset($urlParts['pass'])
        || isset($urlParts['query']) || isset($urlParts['fragment'])) {
        throw new RuntimeException('Configure APP_BASE_URL no .env com o endereço público HTTPS da loja.');
    }
    if (!filter_var((string) ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) {
        throw new RuntimeException('Ative allow_url_fopen no PHP para conectar à API SyncPay.');
    }
    return [
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'webhook_secret' => $webhookSecret,
        'base_url' => $baseUrl
    ];
}

function syncPayHttpRequest(string $url, string $method, array $headers, ?array $payload = null): array
{
    $httpOptions = [
        'method' => $method,
        'header' => implode("\r\n", $headers),
        'timeout' => 20,
        'ignore_errors' => true,
        'follow_location' => 0,
        'max_redirects' => 0
    ];
    if ($payload !== null) {
        try {
            $httpOptions['content'] = json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            );
        } catch (JsonException $exception) {
            throw new RuntimeException('Não foi possível preparar os dados do pagamento.', 0, $exception);
        }
    }

    $context = stream_context_create([
        'http' => $httpOptions,
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true]
    ]);
    set_error_handler(static function (int $severity, string $message): never {
        throw new RuntimeException('Não foi possível se comunicar com a SyncPay.');
    });
    try {
        $body = file_get_contents($url, false, $context);
    } finally {
        restore_error_handler();
    }
    if (!is_string($body)) {
        throw new RuntimeException('Não foi possível se comunicar com a SyncPay.');
    }

    $httpStatus = 0;
    foreach ($http_response_header ?? [] as $header) {
        if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $header, $matches)) {
            $httpStatus = (int) $matches[1];
        }
    }
    $response = json_decode($body, true);
    if (!is_array($response)) {
        throw new RuntimeException('A SyncPay retornou uma resposta inválida.');
    }
    if ($httpStatus < 200 || $httpStatus >= 300) {
        $endpoint = (string) parse_url($url, PHP_URL_PATH);
        throw new SyncPayApiException(
            'A SyncPay recusou a solicitação para ' . $endpoint . ' (HTTP ' . $httpStatus . ').',
            $httpStatus
        );
    }

    return $response;
}

function syncPayAccessToken(array $config): string
{
    $cacheDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'web-pedidos-syncpay-cache';
    if (!is_dir($cacheDirectory) && !mkdir($cacheDirectory, 0700) && !is_dir($cacheDirectory)) {
        throw new RuntimeException('Não foi possível preparar o cache de autenticação SyncPay.');
    }
    if (is_link($cacheDirectory)) {
        throw new RuntimeException('O cache de autenticação SyncPay não é seguro.');
    }
    if (!chmod($cacheDirectory, 0700)) {
        throw new RuntimeException('Não foi possível proteger o cache de autenticação SyncPay.');
    }

    $cacheKey = hash(
        'sha256',
        $config['client_id'] . "\0" . hash('sha256', $config['client_secret'])
    );
    $cachePath = $cacheDirectory . DIRECTORY_SEPARATOR . $cacheKey . '.json';
    if (is_link($cachePath)) {
        throw new RuntimeException('O cache de autenticação SyncPay não é seguro.');
    }
    $cacheFile = fopen($cachePath, 'c+');
    if ($cacheFile === false) {
        throw new RuntimeException('Não foi possível abrir o cache de autenticação SyncPay.');
    }

    $locked = false;
    try {
        if (!chmod($cachePath, 0600)) {
            throw new RuntimeException('Não foi possível proteger o cache de autenticação SyncPay.');
        }
        if (!flock($cacheFile, LOCK_EX)) {
            throw new RuntimeException('Não foi possível bloquear o cache de autenticação SyncPay.');
        }
        $locked = true;
        rewind($cacheFile);
        $cached = json_decode((string) stream_get_contents($cacheFile), true);
        if (is_array($cached) && is_string($cached['access_token'] ?? null)
            && (int) ($cached['expires_at'] ?? 0) > time() + 30) {
            return $cached['access_token'];
        }

        $response = syncPayHttpRequest(
            SYNCPAY_API_URL . '/auth-token',
            'POST',
            ['Accept: application/json', 'Content-Type: application/json'],
            [
                'client_id' => $config['client_id'],
                'client_secret' => $config['client_secret']
            ]
        );
        $accessToken = $response['access_token'] ?? null;
        $expiresIn = filter_var($response['expires_in'] ?? 3600, FILTER_VALIDATE_INT);
        if (!is_string($accessToken) || $accessToken === '' || $expiresIn === false || $expiresIn < 60) {
            throw new RuntimeException('A SyncPay não retornou um token de acesso válido.');
        }

        $cacheValue = json_encode([
            'access_token' => $accessToken,
            'expires_at' => time() + max(30, $expiresIn - 60)
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        rewind($cacheFile);
        if (!ftruncate($cacheFile, 0) || fwrite($cacheFile, $cacheValue) !== strlen($cacheValue)
            || !fflush($cacheFile)) {
            throw new RuntimeException('Não foi possível salvar o cache de autenticação SyncPay.');
        }

        return $accessToken;
    } finally {
        if ($locked) {
            flock($cacheFile, LOCK_UN);
        }
        fclose($cacheFile);
    }
}

function syncPayRequest(array $config, string $accessToken, string $method, string $path, array $payload): array
{
    if (!preg_match('#^/[A-Za-z0-9/_-]+$#', $path)) {
        throw new RuntimeException('A rota SyncPay solicitada é inválida.');
    }

    return syncPayHttpRequest(
        SYNCPAY_API_URL . $path,
        $method,
        [
            'Authorization: Bearer ' . $accessToken,
            'Accept: application/json',
            'Content-Type: application/json'
        ],
        $payload
    );
}

function syncPayClientIp(): string|false
{
    $remoteAddress = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    if (filter_var($remoteAddress, FILTER_VALIDATE_IP) === false) {
        return false;
    }

    $trustedProxies = array_filter(array_map(
        'trim',
        explode(',', (string) getenv('SYNCPAY_TRUSTED_PROXY_IPS'))
    ));
    if (!in_array($remoteAddress, $trustedProxies, true)) {
        return $remoteAddress;
    }

    $forwardedAddress = trim((string) ($_SERVER['HTTP_X_REAL_IP'] ?? ''));
    if (filter_var($forwardedAddress, FILTER_VALIDATE_IP) === false) {
        $forwardedFor = (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
        $forwardedAddress = trim(explode(',', $forwardedFor)[0]);
    }

    return filter_var($forwardedAddress, FILTER_VALIDATE_IP) === false
        ? false
        : $forwardedAddress;
}

function syncPayIsValidCpf(string $cpf): bool
{
    if (!preg_match('/^\d{11}$/', $cpf) || preg_match('/^(\d)\1{10}$/', $cpf)) {
        return false;
    }

    for ($digit = 9; $digit <= 10; $digit++) {
        $sum = 0;
        for ($index = 0; $index < $digit; $index++) {
            $sum += (int) $cpf[$index] * (($digit + 1) - $index);
        }
        $checkDigit = ($sum * 10) % 11;
        if ($checkDigit === 10) {
            $checkDigit = 0;
        }
        if ((int) $cpf[$digit] !== $checkDigit) {
            return false;
        }
    }

    return true;
}
