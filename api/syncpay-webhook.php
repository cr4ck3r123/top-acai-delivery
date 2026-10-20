<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/syncpay.php';
requireMethod('POST');

try {
    $config = syncPayConfig();
} catch (RuntimeException $exception) {
    error_log('Configuração do webhook SyncPay inválida: ' . $exception->getMessage());
    respond(['error' => 'O recebimento de pagamentos está temporariamente indisponível.'], 503);
}

$contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($contentLength > 32768) {
    respond(['error' => 'A notificação excede o tamanho permitido.'], 413);
}

$rawBody = file_get_contents('php://input');
if (!is_string($rawBody) || strlen($rawBody) > 32768) {
    respond(['error' => 'A notificação excede o tamanho permitido.'], 413);
}
$signatureHeader = (string) ($_SERVER['HTTP_X_SYNCPAY_SIGNATURE'] ?? '');
$signatureParts = [];
foreach (explode(',', $signatureHeader) as $part) {
    $pair = explode('=', trim($part), 2);
    if (count($pair) === 2) {
        $signatureParts[$pair[0]] = $pair[1];
    }
}
$timestamp = $signatureParts['t'] ?? '';
$providedSignature = $signatureParts['v1'] ?? '';
if ($timestamp === '' || !ctype_digit($timestamp)
    || $providedSignature === '' || abs(time() - (int) $timestamp) > 300) {
    respond(['error' => 'Assinatura da notificação ausente ou expirada.'], 401);
}

$expectedSignature = hash_hmac(
    'sha256',
    $timestamp . '.' . $rawBody,
    $config['webhook_secret']
);
if (!preg_match('/^[a-f0-9]{64}$/i', $providedSignature)
    || !hash_equals($expectedSignature, strtolower($providedSignature))) {
    respond(['error' => 'Assinatura da notificação inválida.'], 401);
}

$input = json_decode($rawBody, true);
if (!is_array($input)) {
    respond(['error' => 'Notificação de pagamento inválida.'], 400);
}
if (($input['event'] ?? null) !== 'transaction.updated') {
    respond(['received' => true]);
}

$eventId = $input['event_id'] ?? null;
$transaction = $input['transaction'] ?? null;
if (!is_string($eventId) || !preg_match(
    '/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i',
    $eventId
) || !is_array($transaction)) {
    respond(['error' => 'Dados da notificação inválidos.'], 400);
}

$referenceId = $transaction['reference_id'] ?? null;
$providerStatus = $transaction['status'] ?? null;
$providerMethod = $transaction['payment_method'] ?? null;
$amountCents = isset($transaction['amount']) && is_numeric($transaction['amount'])
    ? (int) round((float) $transaction['amount'] * 100)
    : -1;
if (!is_string($referenceId) || $referenceId === ''
    || !is_string($providerStatus)
    || !in_array($providerMethod, ['pix', 'credit_card'], true)
    || ($transaction['currency'] ?? null) !== 'BRL' || $amountCents < 0) {
    respond(['error' => 'Dados de pagamento incompatíveis.'], 422);
}

$paymentStatus = match ($providerStatus) {
    'completed' => 'approved',
    'refused', 'failed' => 'rejected',
    'cancelled', 'canceled', 'expired' => 'cancelled',
    'refunded', 'charged_back' => 'refunded',
    default => 'pending'
};

try {
    $pdo = database();
    $pdo->beginTransaction();
    $orderQuery = $pdo->prepare(
        'SELECT total, payment_method, payment_status, syncpay_transaction_id
         FROM orders WHERE syncpay_transaction_id = ? FOR UPDATE'
    );
    $orderQuery->execute([$referenceId]);
    $order = $orderQuery->fetch();
    $expectedMethod = $providerMethod === 'pix' ? 'pix_online' : 'credit_card_online';
    if (!$order || $order['payment_method'] !== $expectedMethod
        || $order['syncpay_transaction_id'] !== $referenceId
        || moneyCents($order['total']) !== $amountCents) {
        $pdo->rollBack();
        respond(['error' => 'O pedido correspondente não foi encontrado.'], 404);
    }

    $recordEvent = $pdo->prepare('INSERT IGNORE INTO syncpay_webhook_events (event_id) VALUES (?)');
    $recordEvent->execute([$eventId]);
    if ($recordEvent->rowCount() === 0) {
        $pdo->commit();
        respond(['received' => true]);
    }

    if ($order['payment_status'] === 'refunded') {
        $paymentStatus = 'refunded';
    } elseif ($order['payment_status'] === 'approved' && $paymentStatus !== 'refunded') {
        $paymentStatus = 'approved';
    }
    if ($paymentStatus !== $order['payment_status']) {
        $update = $pdo->prepare('UPDATE orders SET payment_status = ? WHERE syncpay_transaction_id = ?');
        $update->execute([$paymentStatus, $referenceId]);
    }

    $pdo->commit();
    respond(['received' => true]);
} catch (PDOException | RuntimeException $exception) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Falha ao processar webhook SyncPay: ' . $exception->getMessage());
    respond(['error' => 'Não foi possível processar a notificação agora.'], 503);
}
