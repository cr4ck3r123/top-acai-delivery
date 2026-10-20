<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function loadEnvironmentFile(): void
{
    $environmentPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
    if (!is_file($environmentPath)) {
        return;
    }
    if (!is_readable($environmentPath)) {
        error_log('O usuário do PHP não tem permissão para ler o arquivo .env.');
        throw new RuntimeException(
            'O PHP não consegue ler .env. Confira as permissões para o usuário do Apache/PHP.'
        );
    }

    $lines = file($environmentPath, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        throw new RuntimeException('Não foi possível ler o arquivo .env.');
    }

    foreach ($lines as $index => $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (!preg_match('/^([A-Z_][A-Z0-9_]*)\s*=\s*(.*)$/', $line, $matches)) {
            throw new RuntimeException('Formato inválido no arquivo .env, linha ' . ($index + 1) . '.');
        }

        $name = $matches[1];
        $value = trim($matches[2]);
        if (strlen($value) >= 2
            && (($value[0] === '"' && str_ends_with($value, '"'))
                || ($value[0] === "'" && str_ends_with($value, "'")))) {
            $value = substr($value, 1, -1);
        }

        if (getenv($name) === false) {
            putenv($name . '=' . $value);
            $_ENV[$name] = $value;
        }
    }
}

loadEnvironmentFile();

function respond(array $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function database(): PDO
{
    static $connection = null;
    if ($connection instanceof PDO) {
        return $connection;
    }

    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $port = getenv('DB_PORT') ?: '3306';
    $name = getenv('DB_NAME') ?: 'acaidelivery_db';
    $user = getenv('DB_USER') ?: 'pedidos_app';
    $password = getenv('DB_PASSWORD');
    if ($password === false) {
        throw new RuntimeException('Configure DB_PASSWORD no ambiente ou no arquivo .env.');
    }

    $connection = new PDO(
        "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );

    return $connection;
}

function requireMethod(string $method): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== $method) {
        header('Allow: ' . $method);
        respond(['error' => 'Método não permitido.'], 405);
    }
}

function requiredText(mixed $value, string $field, int $maxLength): string
{
    if (!is_string($value)) {
        respond(['error' => "O campo {$field} é obrigatório."], 422);
    }

    $value = trim($value);
    if ($value === '' || textLength($value) > $maxLength) {
        respond(['error' => "Confira o campo {$field} e tente novamente."], 422);
    }

    return $value;
}

function textLength(string $value): int
{
    $matches = [];
    return preg_match_all('/./us', $value, $matches);
}

function moneyCents(string|int|float $amount): int
{
    return (int) round((float) $amount * 100);
}
