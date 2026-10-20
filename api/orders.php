<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/syncpay.php';
requireMethod('POST');

$contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($contentLength > 65536) {
    respond(['error' => 'O pedido excede o tamanho permitido.'], 413);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    respond(['error' => 'Não foi possível ler os dados do pedido.'], 400);
}

$customerName = requiredText($input['customer_name'] ?? null, 'nome', 100);
$customerPhone = requiredText($input['customer_phone'] ?? null, 'WhatsApp', 20);
$phoneDigits = preg_replace('/\D+/', '', $customerPhone);
if (strlen($phoneDigits) < 10 || strlen($phoneDigits) > 13) {
    respond(['error' => 'Informe um número de WhatsApp válido com DDD.'], 422);
}

$fulfillment = $input['fulfillment'] ?? '';
if (!in_array($fulfillment, ['delivery', 'pickup'], true)) {
    respond(['error' => 'Escolha entrega ou retirada no local.'], 422);
}

$paymentMethod = $input['payment_method'] ?? '';
$paymentLabels = [
    'pix' => 'Pix',
    'credit_card' => 'Cartão de crédito',
    'debit_card' => 'Cartão de débito',
    'cash' => 'Dinheiro'
];
if (!is_string($paymentMethod) || !isset($paymentLabels[$paymentMethod])) {
    respond(['error' => 'Selecione uma forma de pagamento válida.'], 422);
}

$onlinePayment = in_array($paymentMethod, ['pix_online', 'credit_card_online'], true);
$syncPayConfig = null;
if ($onlinePayment) {
    try {
        $syncPayConfig = syncPayConfig();
    } catch (RuntimeException $exception) {
        error_log('Configuração de pagamentos online inválida: ' . $exception->getMessage());
        respond(['error' => 'O pagamento online está indisponível. Escolha uma forma de pagamento na entrega.'], 503);
    }
}

$customerEmail = null;
$customerCpf = null;
if ($onlinePayment) {
    $customerEmail = requiredText($input['customer_email'] ?? null, 'e-mail', 254);
    if (!filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
        respond(['error' => 'Informe um endereço de e-mail válido para pagar online.'], 422);
    }
    $customerCpf = preg_replace('/\D+/', '', requiredText($input['customer_cpf'] ?? null, 'CPF', 20));
    if (!syncPayIsValidCpf($customerCpf)) {
        respond(['error' => 'Informe um CPF válido para pagar online.'], 422);
    }
    if ($paymentMethod === 'credit_card_online') {
        respond(['error' => 'Pagamento online com cartão está pausado por enquanto. Selecione Pix ou pague na entrega.'], 503);
    }
}

$address = '';
$complement = '';
$neighborhood = '';
if ($fulfillment === 'delivery') {
    $address = requiredText($input['address'] ?? null, 'endereço', 180);
    $neighborhood = requiredText($input['neighborhood'] ?? null, 'bairro', 100);
    if (isset($input['complement']) && !is_string($input['complement'])) {
        respond(['error' => 'Confira o campo complemento.'], 422);
    }
    $complement = trim($input['complement'] ?? '');
    if (textLength($complement) > 100) {
        respond(['error' => 'O complemento excede o tamanho permitido.'], 422);
    }
}

$notes = $input['notes'] ?? '';
if (!is_string($notes) || textLength($notes) > 500) {
    respond(['error' => 'Confira as observações do pedido.'], 422);
}
$notes = trim($notes);

$cashChange = null;
if ($paymentMethod === 'cash' && isset($input['cash_change']) && $input['cash_change'] !== '') {
    if (!is_string($input['cash_change']) || !preg_match('/^\d{1,7}(?:[,.]\d{1,2})?$/', $input['cash_change'])) {
        respond(['error' => 'Informe um valor válido para o troco.'], 422);
    }
    $cashChange = number_format((float) str_replace(',', '.', $input['cash_change']), 2, '.', '');
}

$items = $input['items'] ?? null;
if (!is_array($items) || count($items) < 1 || count($items) > 50) {
    respond(['error' => 'Adicione produtos ao pedido para continuar.'], 422);
}

$syncPayRequestAttempted = false;
$syncPayPaymentAccepted = false;
$quantities = [];
$requestedItems = [];
foreach ($items as $item) {
    if (!is_array($item) || filter_var($item['id'] ?? null, FILTER_VALIDATE_INT) === false
        || filter_var($item['quantity'] ?? null, FILTER_VALIDATE_INT) === false) {
        respond(['error' => 'Um dos produtos do pedido é inválido. Atualize o cardápio e tente novamente.'], 422);
    }
    $id = (int) $item['id'];
    $quantity = (int) $item['quantity'];
    if ($id < 1 || $quantity < 1 || $quantity > 20) {
        respond(['error' => 'A quantidade de um dos produtos excede o limite permitido.'], 422);
    }
    $itemNotes = $item['notes'] ?? '';
    if (!is_string($itemNotes) || textLength($itemNotes) > 300) {
        respond(['error' => 'Confira as observações de um dos produtos.'], 422);
    }
    $itemNotes = trim($itemNotes);
    $addonIds = $item['addon_ids'] ?? [];
    if (!is_array($addonIds) || count($addonIds) > 4) {
        respond(['error' => 'Escolha no máximo 4 complementos por produto.'], 422);
    }
    $normalizedAddonIds = [];
    foreach ($addonIds as $addonId) {
        if (filter_var($addonId, FILTER_VALIDATE_INT) === false || (int) $addonId < 1) {
            respond(['error' => 'Um dos complementos selecionados é inválido.'], 422);
        }
        $normalizedAddonIds[] = (int) $addonId;
    }
    if (count(array_unique($normalizedAddonIds)) !== count($normalizedAddonIds)) {
        respond(['error' => 'Remova complementos duplicados do produto.'], 422);
    }
    $quantities[$id] = ($quantities[$id] ?? 0) + $quantity;
    if ($quantities[$id] > 20) {
        respond(['error' => 'O limite é de 20 unidades por produto.'], 422);
    }
    $requestedItems[] = ['id' => $id, 'quantity' => $quantity, 'notes' => $itemNotes, 'addon_ids' => $normalizedAddonIds];
}

try {
    $pdo = database();
    $pdo->beginTransaction();

    $store = $pdo->query(
        'SELECT name, whatsapp_phone, delivery_fee, free_delivery_minimum, is_open
         FROM store_config WHERE id = 1 FOR UPDATE'
    )->fetch();
    if (!$store) {
        $pdo->rollBack();
        respond(['error' => 'As informações da loja ainda não foram configuradas.'], 503);
    }
    if (!(bool) $store['is_open']) {
        $pdo->rollBack();
        respond(['error' => 'A loja está fechada no momento. Tente novamente mais tarde.'], 409);
    }

    $whatsappPhone = preg_replace('/\D+/', '', (string) $store['whatsapp_phone']);
    if (strlen($whatsappPhone) < 10 || strlen($whatsappPhone) > 15) {
        $pdo->rollBack();
        respond(['error' => 'A loja está temporariamente sem um número de WhatsApp válido. Avise o estabelecimento.'], 503);
    }

    $ids = array_keys($quantities);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $productQuery = $pdo->prepare(
        "SELECT id, name, category, price FROM products WHERE is_available = 1 AND id IN ({$placeholders})"
    );
    $productQuery->execute($ids);
    $availableProducts = [];
    foreach ($productQuery->fetchAll() as $product) {
        $availableProducts[(int) $product['id']] = $product;
    }
    if (count($availableProducts) !== count($quantities)) {
        $pdo->rollBack();
        respond(['error' => 'Um ou mais produtos não estão mais disponíveis. Atualize o cardápio e tente novamente.'], 409);
    }

    $selectedAddonIds = array_values(array_unique(array_merge(...array_map(
        static fn (array $item): array => $item['addon_ids'],
        $requestedItems
    ))));
    $availableAddons = [];
    if ($selectedAddonIds !== []) {
        $addonPlaceholders = implode(',', array_fill(0, count($selectedAddonIds), '?'));
        $addonQuery = $pdo->prepare(
            "SELECT id, name, price FROM product_addons WHERE is_available = 1 AND id IN ({$addonPlaceholders})"
        );
        $addonQuery->execute($selectedAddonIds);
        foreach ($addonQuery->fetchAll() as $addon) {
            $availableAddons[(int) $addon['id']] = $addon;
        }
        if (count($availableAddons) !== count($selectedAddonIds)) {
            $pdo->rollBack();
            respond(['error' => 'Um ou mais complementos não estão mais disponíveis. Atualize o cardápio.'], 409);
        }
    }

    $subtotalCents = 0;
    $orderLines = [];
    foreach ($requestedItems as $requestedItem) {
        $id = $requestedItem['id'];
        $quantity = $requestedItem['quantity'];
        $product = $availableProducts[$id];
        $selectedNames = [];
        $priceCents = moneyCents($product['price']);
        if ($requestedItem['addon_ids'] !== [] && $product['category'] !== 'Açaí Premium Especial') {
            $pdo->rollBack();
            respond(['error' => 'Os complementos estão disponíveis apenas no Açaí Premium Especial.'], 422);
        }
        foreach ($requestedItem['addon_ids'] as $addonId) {
            $addon = $availableAddons[$addonId];
            $selectedNames[] = $addon['name'];
            $priceCents += moneyCents($addon['price']);
        }
        $lineNotes = [];
        if ($selectedNames !== []) $lineNotes[] = 'Complementos: ' . implode(', ', $selectedNames);
        if ($requestedItem['notes'] !== '') $lineNotes[] = 'Observações: ' . $requestedItem['notes'];
        $combinedNotes = implode(' | ', $lineNotes);
        if (textLength($combinedNotes) > 300) {
            $pdo->rollBack();
            respond(['error' => 'As observações e os complementos excedem o limite. Reduza as observações do produto.'], 422);
        }
        $subtotalCents += $priceCents * $quantity;
        $orderLines[] = [
            'id' => $id,
            'name' => $product['name'],
            'quantity' => $quantity,
            'price_cents' => $priceCents,
            'notes' => $combinedNotes
        ];
    }

    $deliveryFeeCents = $fulfillment === 'delivery' ? moneyCents($store['delivery_fee']) : 0;
    if ($fulfillment === 'delivery' && $store['free_delivery_minimum'] !== null
        && $subtotalCents >= moneyCents($store['free_delivery_minimum'])) {
        $deliveryFeeCents = 0;
    }
    $totalCents = $subtotalCents + $deliveryFeeCents;

    if ($onlinePayment && $totalCents < 100) {
        $pdo->rollBack();
        respond(['error' => 'O valor mínimo para pagamento online é R$ 1,00.'], 422);
    }

    if ($cashChange !== null && moneyCents($cashChange) < $totalCents) {
        $pdo->rollBack();
        respond(['error' => 'O valor informado para o troco deve cobrir o total do pedido.'], 422);
    }

    $trackingToken = bin2hex(random_bytes(32));
    $insertOrder = $pdo->prepare(
        'INSERT INTO orders
         (customer_name, customer_phone, fulfillment, address, complement, neighborhood,
          payment_method, payment_status, cash_change, notes, subtotal, delivery_fee, total, status, tracking_token_hash)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $insertOrder->execute([
        $customerName,
        $phoneDigits,
        $fulfillment,
        $address,
        $complement,
        $neighborhood,
        $paymentMethod,
        $onlinePayment ? 'pending' : 'not_required',
        $cashChange,
        $notes,
        number_format($subtotalCents / 100, 2, '.', ''),
        number_format($deliveryFeeCents / 100, 2, '.', ''),
        number_format($totalCents / 100, 2, '.', ''),
        'pending',
        hash('sha256', $trackingToken)
    ]);

    $orderId = (int) $pdo->lastInsertId();
    $insertItem = $pdo->prepare(
        'INSERT INTO order_items (order_id, product_id, product_name, quantity, unit_price, item_notes)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    foreach ($orderLines as $line) {
        $insertItem->execute([
            $orderId,
            $line['id'],
            $line['name'],
            $line['quantity'],
            number_format($line['price_cents'] / 100, 2, '.', ''),
            $line['notes']
        ]);
    }
    $pdo->commit();

    $pixCode = null;
    if ($onlinePayment) {
        $accessToken = syncPayAccessToken($syncPayConfig);
        if ($paymentMethod === 'pix_online') {
            $localPhone = $phoneDigits;
            if (str_starts_with($localPhone, '55') && in_array(strlen($localPhone), [12, 13], true)) {
                $localPhone = substr($localPhone, 2);
            }
            if (!preg_match('/^\d{10,11}$/', $localPhone)) {
                throw new RuntimeException('Informe um WhatsApp com DDD para gerar o Pix.');
            }
            $syncPayRequestAttempted = true;
            $paymentResponse = syncPayRequest($syncPayConfig, $accessToken, 'POST', '/cash-in', [
                'amount' => round($totalCents / 100, 2),
                'description' => 'Pedido #' . $orderId,
                'client' => [
                    'name' => $customerName,
                    'cpf' => $customerCpf,
                    'email' => $customerEmail,
                    'phone' => $localPhone
                ]
            ]);
            $syncPayPaymentAccepted = true;
            $transactionId = $paymentResponse['identifier'] ?? null;
            $pixCode = $paymentResponse['pix_code'] ?? null;
        }

        if (!is_string($transactionId) || !preg_match(
            '/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i',
            $transactionId
        )
            || ($paymentMethod === 'pix_online'
                && (!is_string($pixCode) || $pixCode === '' || strlen($pixCode) > 5000))) {
            throw new RuntimeException('A SyncPay retornou dados de pagamento incompletos.');
        }
        $saveTransaction = $pdo->prepare(
            'UPDATE orders SET syncpay_transaction_id = ?, syncpay_pix_code = ? WHERE id = ?'
        );
        $saveTransaction->execute([$transactionId, $pixCode, $orderId]);
    }

    $messageLines = [
        "Olá! Quero confirmar o pedido #{$orderId} da {$store['name']}.",
        '',
        '*Meu pedido:*'
    ];
    foreach ($orderLines as $line) {
        $lineTotal = number_format($line['price_cents'] * $line['quantity'] / 100, 2, ',', '.');
        $messageLines[] = "{$line['quantity']}x {$line['name']} — R$ {$lineTotal}";
        if ($line['notes'] !== '') {
            $messageLines[] = '  Observação: ' . $line['notes'];
        }
    }
    $messageLines[] = '';
    $messageLines[] = '*Subtotal:* R$ ' . number_format($subtotalCents / 100, 2, ',', '.');
    $messageLines[] = '*Entrega:* ' . ($fulfillment === 'pickup' ? 'Retirada no local' : 'R$ ' . number_format($deliveryFeeCents / 100, 2, ',', '.'));
    $messageLines[] = '*Total:* R$ ' . number_format($totalCents / 100, 2, ',', '.');
    $messageLines[] = '*Pagamento:* ' . $paymentLabels[$paymentMethod];
    if ($cashChange !== null) {
        $messageLines[] = '*Troco para:* R$ ' . number_format((float) $cashChange, 2, ',', '.');
    }
    $messageLines[] = '*Cliente:* ' . $customerName . ' — WhatsApp ' . $phoneDigits;
    if ($fulfillment === 'delivery') {
        $messageLines[] = '*Endereço:* ' . $address . ($complement !== '' ? ", {$complement}" : '') . " — {$neighborhood}";
    }
    if ($notes !== '') {
        $messageLines[] = '*Observações:* ' . $notes;
    }

    respond([
        'order_number' => str_pad((string) $orderId, 5, '0', STR_PAD_LEFT),
        'tracking_token' => $trackingToken,
        'whatsapp_url' => 'https://wa.me/' . $whatsappPhone . '?text=' . rawurlencode(implode("\n", $messageLines)),
        'pix_code' => $pixCode
    ], 201);
} catch (PDOException | RuntimeException $exception) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $safeToDeleteOrder = !$syncPayRequestAttempted
        || ($exception instanceof SyncPayApiException
            && $exception->getCode() >= 400 && $exception->getCode() < 500);
    if (($onlinePayment ?? false) && $safeToDeleteOrder && !$syncPayPaymentAccepted
        && isset($orderId) && isset($pdo) && !$pdo->inTransaction()) {
        try {
            $cleanup = $pdo->prepare(
                "DELETE FROM orders WHERE id = ? AND payment_method IN ('pix_online', 'credit_card_online')
                 AND syncpay_transaction_id IS NULL"
            );
            $cleanup->execute([$orderId]);
        } catch (PDOException $cleanupException) {
            error_log('Falha ao limpar pedido sem checkout: ' . $cleanupException->getMessage());
        }
    }
    error_log('Falha ao registrar pedido: ' . $exception->getMessage());
    $paymentOutcomeUncertain = ($onlinePayment ?? false) && !$safeToDeleteOrder;
    $errorMessage = $paymentOutcomeUncertain && isset($orderId)
        ? 'A SyncPay ainda não confirmou o resultado do pagamento do pedido #' . $orderId
            . '. Não envie o pedido novamente agora; entre em contato com a loja informando esse número.'
        : (($onlinePayment ?? false)
            ? 'Não foi possível iniciar o pagamento agora. Tente novamente ou escolha pagar na entrega.'
            : 'Não foi possível registrar seu pedido agora. Tente novamente em instantes.');
    respond(['error' => $errorMessage], 503);
} catch (Random\RandomException $exception) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Falha ao gerar link de acompanhamento: ' . $exception->getMessage());
    respond(['error' => 'Não foi possível registrar seu pedido agora. Tente novamente em instantes.'], 503);
}
