<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
requireMethod('POST');

$contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($contentLength > 1024) {
    respond(['error' => 'A solicitação excede o tamanho permitido.'], 413);
}

$input = json_decode(file_get_contents('php://input'), true);
$token = is_array($input) ? ($input['token'] ?? null) : null;
if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/', $token)) {
    respond(['error' => 'Link de acompanhamento inválido. Confira o link e tente novamente.'], 422);
}

try {
    $statement = database()->prepare(
        'SELECT id, status, fulfillment, payment_method, payment_status, syncpay_pix_code
         FROM orders WHERE tracking_token_hash = ?'
    );
    $statement->execute([hash('sha256', $token)]);
    $order = $statement->fetch();
    if (!$order) {
        respond(['error' => 'Não encontramos esse pedido. Confira se o link está correto.'], 404);
    }

    respond([
        'order_number' => str_pad((string) $order['id'], 5, '0', STR_PAD_LEFT),
        'status' => $order['status'],
        'fulfillment' => $order['fulfillment'],
        'payment_method' => $order['payment_method'],
        'payment_status' => $order['payment_status'],
        'pix_code' => $order['payment_method'] === 'pix_online'
            && $order['payment_status'] === 'pending'
                ? $order['syncpay_pix_code']
                : null
    ]);
} catch (PDOException $exception) {
    error_log('Falha ao consultar acompanhamento do pedido: ' . $exception->getMessage());
    respond(['error' => 'Não foi possível consultar o pedido agora. Tente novamente em instantes.'], 503);
}
