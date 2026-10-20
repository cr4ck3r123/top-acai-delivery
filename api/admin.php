<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/admin-session.php';
startAdminSession();

$method = $_SERVER['REQUEST_METHOD'] ?? '';
if ($method === 'GET') {
    $pdo = adminDatabase();
    $admin = requireAdmin($pdo);

    try {
        $products = $pdo->query(
            'SELECT id, name, description, category, price, image_url,
                    is_featured AS featured, is_available AS available, sort_order
             FROM products ORDER BY category, sort_order, name'
        )->fetchAll();
        foreach ($products as &$product) {
            $product['id'] = (int) $product['id'];
            if (str_starts_with((string) $product['image_url'], 'uploads/products/')) {
                $product['image_url'] = '/' . $product['image_url'];
            }
            $product['price'] = (float) $product['price'];
            $product['featured'] = (bool) $product['featured'];
            $product['available'] = (bool) $product['available'];
            $product['sort_order'] = (int) $product['sort_order'];
        }
        unset($product);

        
        $addons = $pdo->query('SELECT id, name, price, is_available AS available FROM product_addons ORDER BY sort_order, name')->fetchAll();
        foreach ($addons as &$addon) {
            $addon['id'] = (int) $addon['id'];
            $addon['price'] = (float) $addon['price'];
            $addon['available'] = (bool) $addon['available'];
        }
        unset($addon);
        $settings = $pdo->query(
            'SELECT name, whatsapp_phone, delivery_fee, free_delivery_minimum,
                    estimated_delivery_minutes, is_open
             FROM store_config WHERE id = 1'
        )->fetch();
        if (!$settings) {
            respond(['error' => 'Configure as informações da loja no banco de dados.'], 503);
        }
        $settings['delivery_fee'] = (float) $settings['delivery_fee'];
        $settings['free_delivery_minimum'] = $settings['free_delivery_minimum'] === null
            ? ''
            : (float) $settings['free_delivery_minimum'];
        $settings['estimated_delivery_minutes'] = (int) $settings['estimated_delivery_minutes'];
        $settings['is_open'] = (bool) $settings['is_open'];

        $orderRows = $pdo->query(
            'SELECT id, customer_name, customer_phone, fulfillment, address, complement,
                    neighborhood, payment_method, payment_status, cash_change, notes, subtotal, delivery_fee,
                    total, status, created_at
             FROM orders ORDER BY created_at DESC, id DESC LIMIT 100'
        )->fetchAll();
        $orders = [];
        $orderIds = [];
        foreach ($orderRows as &$order) {
            $order['id'] = (int) $order['id'];
            $order['subtotal'] = (float) $order['subtotal'];
            $order['delivery_fee'] = (float) $order['delivery_fee'];
            $order['total'] = (float) $order['total'];
            $order['items'] = [];
            $orders[$order['id']] = &$order;
            $orderIds[] = $order['id'];
        }
        unset($order);
        if ($orderIds) {
            $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
            $itemsQuery = $pdo->prepare(
                "SELECT order_id, product_name, quantity, unit_price, item_notes
                 FROM order_items WHERE order_id IN ({$placeholders}) ORDER BY id"
            );
            $itemsQuery->execute($orderIds);
            foreach ($itemsQuery->fetchAll() as $item) {
                $orders[(int) $item['order_id']]['items'][] = [
                    'name' => $item['product_name'],
                    'quantity' => (int) $item['quantity'],
                    'unit_price' => (float) $item['unit_price'],
                    'notes' => $item['item_notes']
                ];
            }
        }

        $admins = $pdo->query(
            'SELECT id, username, is_active AS active, created_at
             FROM admin_users ORDER BY username'
        )->fetchAll();
        foreach ($admins as &$account) {
            $account['id'] = (int) $account['id'];
            $account['active'] = (bool) $account['active'];
        }
        unset($account);

        respond([
            'admin' => $admin,
            'products' => $products,
            'addons' => $addons,
            'settings' => $settings,
            'orders' => array_values($orders),
            'admins' => $admins
        ]);
    } catch (PDOException $exception) {
        error_log('Falha ao carregar painel administrativo: ' . $exception->getMessage());
        respond(['error' => 'Não foi possível carregar o painel. Tente novamente em instantes.'], 503);
    }
}

if ($method !== 'POST') {
    header('Allow: GET, POST');
    respond(['error' => 'Método não permitido.'], 405);
}

verifyAdminCsrf();
$contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($contentLength > 32768) {
    respond(['error' => 'A solicitação excede o tamanho permitido.'], 413);
}
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input) || !is_string($input['action'] ?? null)) {
    respond(['error' => 'Não foi possível ler a solicitação.'], 400);
}

$action = $input['action'];
$pdo = adminDatabase();
if ($action === 'login') {
    $username = $input['username'] ?? null;
    $password = $input['password'] ?? null;
    if (!is_string($username) || !preg_match('/^[a-zA-Z0-9._-]{3,50}$/', $username)
        || !is_string($password) || strlen($password) < 1 || strlen($password) > 200) {
        respond(['error' => 'Usuário ou senha inválidos.'], 422);
    }
    $username = strtolower($username);
    $ipHash = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'unknown');

    try {
        $pdo->beginTransaction();
        $attemptInsert = $pdo->prepare(
            'INSERT IGNORE INTO admin_login_attempts (username, ip_hash) VALUES (?, ?)'
        );
        $attemptInsert->execute([$username, $ipHash]);
        $attemptQuery = $pdo->prepare(
            'SELECT failed_attempts, window_started_at, locked_until
             FROM admin_login_attempts WHERE username = ? AND ip_hash = ? FOR UPDATE'
        );
        $attemptQuery->execute([$username, $ipHash]);
        $attempt = $attemptQuery->fetch();
        $now = time();
        $windowStarted = strtotime($attempt['window_started_at']);
        if ($attempt['locked_until'] !== null && strtotime($attempt['locked_until']) > $now) {
            $pdo->commit();
            respond(['error' => 'Muitas tentativas. Aguarde 15 minutos e tente novamente.'], 429);
        }
        $failedAttempts = (int) $attempt['failed_attempts'];
        if ($now - $windowStarted >= 900) {
            $failedAttempts = 0;
        }

        $accountQuery = $pdo->prepare(
            'SELECT id, username, password_hash, session_version
             FROM admin_users WHERE username = ? AND is_active = 1'
        );
        $accountQuery->execute([$username]);
        $account = $accountQuery->fetch();
        $verified = password_verify(
            $password,
            $account['password_hash'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.'
        );

        if (!$account || !$verified) {
            $failedAttempts++;
            $lockedUntil = $failedAttempts >= 5 ? date('Y-m-d H:i:s', $now + 900) : null;
            $attemptUpdate = $pdo->prepare(
                'UPDATE admin_login_attempts SET failed_attempts = ?, window_started_at = ?,
                 locked_until = ? WHERE username = ? AND ip_hash = ?'
            );
            $attemptUpdate->execute([
                $failedAttempts,
                date('Y-m-d H:i:s', $failedAttempts === 1 ? $now : $windowStarted),
                $lockedUntil,
                $username,
                $ipHash
            ]);
            $pdo->commit();
            respond([
                'error' => $failedAttempts >= 5
                    ? 'Muitas tentativas. Aguarde 15 minutos antes de tentar novamente.'
                    : 'Usuário ou senha inválidos.'
            ], $failedAttempts >= 5 ? 429 : 401);
        }

        $attemptDelete = $pdo->prepare(
            'DELETE FROM admin_login_attempts WHERE username = ? AND ip_hash = ?'
        );
        $attemptDelete->execute([$username, $ipHash]);
        $pdo->commit();
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int) $account['id'];
        $_SESSION['admin_session_version'] = (int) $account['session_version'];
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        respond(['username' => $account['username'], 'csrf_token' => $_SESSION['csrf_token']]);
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Falha na autenticação administrativa: ' . $exception->getMessage());
        respond(['error' => 'Não foi possível autenticar agora. Tente novamente em instantes.'], 503);
    }
}

if ($action === 'logout') {
    requireAdmin($pdo);
    $_SESSION = [];
    session_regenerate_id(true);
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    respond(['logged_out' => true, 'csrf_token' => $_SESSION['csrf_token']]);
}

$admin = requireAdmin($pdo);

try {
    switch ($action) {
        case 'save_addon_prices':
            $prices = $input['addons'] ?? null;
            if (!is_array($prices) || count($prices) < 1 || count($prices) > 100) {
                respond(['error' => 'A lista de preços dos complementos é inválida.'], 422);
            }
            $normalizedPrices = [];
            foreach ($prices as $entry) {
                if (!is_array($entry)) {
                    respond(['error' => 'Confira os valores dos complementos.'], 422);
                }
                $id = filter_var($entry['id'] ?? null, FILTER_VALIDATE_INT);
                $price = $entry['price'] ?? null;
                if ($id === false || $id < 1
                    || (!is_string($price) && !is_int($price) && !is_float($price))
                    || !preg_match('/^(?:0|[1-9]\d{0,5})(?:\.\d{1,2})?$/', (string) $price)) {
                    respond(['error' => 'Informe preços válidos de até R$ 999.999,99.'], 422);
                }
                $normalizedPrices[$id] = number_format((float) $price, 2, '.', '');
            }
            if (count($normalizedPrices) !== count($prices)) {
                respond(['error' => 'Há complementos repetidos na lista.'], 422);
            }
            $pdo->beginTransaction();
            $updateAddon = $pdo->prepare('UPDATE product_addons SET price = ? WHERE id = ?');
            $findAddon = $pdo->prepare('SELECT id FROM product_addons WHERE id = ?');
            foreach ($normalizedPrices as $id => $price) {
                $updateAddon->execute([$price, $id]);
                if ($updateAddon->rowCount() === 0) {
                    $findAddon->execute([$id]);
                    if (!$findAddon->fetch()) {
                        $pdo->rollBack();
                        respond(['error' => 'Um complemento não existe mais. Atualize o painel.'], 409);
                    }
                }
            }
            $pdo->commit();
            respond(['message' => 'Preços dos complementos atualizados.']);
        case 'save_product':
            $id = $input['id'] ?? null;
            if ($id !== null && (filter_var($id, FILTER_VALIDATE_INT) === false || (int) $id < 1)) {
                respond(['error' => 'O identificador do produto é inválido.'], 422);
            }
            $name = requiredText($input['name'] ?? null, 'nome', 120);
            $description = $input['description'] ?? '';
            if (!is_string($description) || textLength($description) > 300) {
                respond(['error' => 'A descrição deve ter até 300 caracteres.'], 422);
            }
            $category = requiredText($input['category'] ?? null, 'categoria', 60);
            $price = $input['price'] ?? null;
            if ((!is_string($price) && !is_int($price) && !is_float($price))
                || !preg_match('/^(?:0|[1-9]\d{0,7})(?:\.\d{1,2})?$/', (string) $price)) {
                respond(['error' => 'Informe um preço válido, de até R$ 99.999.999,99.'], 422);
            }
            $imageUrl = requiredText($input['image_url'] ?? null, 'URL da imagem', 500);
            if (str_starts_with($imageUrl, '/uploads/products/')) {
                $imageUrl = substr($imageUrl, 1);
            }
            $urlParts = parse_url($imageUrl);
            $isUploadedImage = preg_match('#^/?uploads/products/[a-f0-9]{32}\.(?:jpg|png|webp)$#', $imageUrl) === 1;
            $isLocalAcaiImage = in_array($imageUrl, ['/top-acai-hero.svg', 'top-acai-hero.svg'], true);
            if (!$isUploadedImage && !$isLocalAcaiImage && (filter_var($imageUrl, FILTER_VALIDATE_URL) === false
                || !isset($urlParts['scheme'], $urlParts['host'])
                || strtolower($urlParts['scheme']) !== 'https')) {
                respond(['error' => 'A imagem deve ser enviada pelo painel ou ter uma URL válida começando com https://.'], 422);
            }
            $featured = $input['featured'] ?? false;
            $available = $input['available'] ?? true;
            $sortOrder = $input['sort_order'] ?? 0;
            if (!is_bool($featured) || !is_bool($available)
                || filter_var($sortOrder, FILTER_VALIDATE_INT) === false
                || (int) $sortOrder < 0 || (int) $sortOrder > 65535) {
                respond(['error' => 'Confira a disponibilidade, o destaque e a ordem do produto.'], 422);
            }

            $values = [
                $name,
                trim($description),
                $category,
                number_format((float) $price, 2, '.', ''),
                $imageUrl,
                $featured ? 1 : 0,
                $available ? 1 : 0,
                (int) $sortOrder
            ];
            if ($id === null) {
                $statement = $pdo->prepare(
                    'INSERT INTO products
                     (name, description, category, price, image_url, is_featured, is_available, sort_order)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $statement->execute($values);
                respond(['id' => (int) $pdo->lastInsertId(), 'message' => 'Produto cadastrado com sucesso.'], 201);
            }
            $values[] = (int) $id;
            $statement = $pdo->prepare(
                'UPDATE products SET name = ?, description = ?, category = ?, price = ?,
                 image_url = ?, is_featured = ?, is_available = ?, sort_order = ?
                 WHERE id = ?'
            );
            $statement->execute($values);
            if ($statement->rowCount() === 0) {
                $exists = $pdo->prepare('SELECT id FROM products WHERE id = ?');
                $exists->execute([(int) $id]);
                if (!$exists->fetch()) {
                    respond(['error' => 'Esse produto não existe mais. Atualize o painel e tente novamente.'], 404);
                }
            }
            respond(['message' => 'Produto atualizado com sucesso.']);

        case 'toggle_product':
            $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
            $available = $input['available'] ?? null;
            if ($id === false || $id < 1 || !is_bool($available)) {
                respond(['error' => 'A ação sobre o produto é inválida.'], 422);
            }
            $statement = $pdo->prepare('UPDATE products SET is_available = ? WHERE id = ?');
            $statement->execute([$available ? 1 : 0, $id]);
            if ($statement->rowCount() === 0) {
                $exists = $pdo->prepare('SELECT id FROM products WHERE id = ?');
                $exists->execute([$id]);
                if (!$exists->fetch()) {
                    respond(['error' => 'Esse produto não existe mais.'], 404);
                }
            }
            respond(['message' => $available ? 'Produto disponível no cardápio.' : 'Produto ocultado do cardápio.']);

        case 'delete_product':
            $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
            if ($id === false || $id < 1) {
                respond(['error' => 'O identificador do produto é inválido.'], 422);
            }
            $statement = $pdo->prepare('DELETE FROM products WHERE id = ?');
            $statement->execute([$id]);
            if ($statement->rowCount() !== 1) {
                respond(['error' => 'Esse produto não existe mais. Atualize o painel e tente novamente.'], 404);
            }
            respond(['message' => 'Produto removido. Os pedidos antigos continuam preservados.']);

        case 'save_settings':
            $name = requiredText($input['name'] ?? null, 'nome da loja', 100);
            $phone = requiredText($input['whatsapp_phone'] ?? null, 'WhatsApp', 20);
            $phone = preg_replace('/\D+/', '', $phone);
            if (strlen($phone) < 10 || strlen($phone) > 15) {
                respond(['error' => 'Informe um WhatsApp válido com código do país e DDD.'], 422);
            }
            $deliveryFee = $input['delivery_fee'] ?? null;
            $freeMinimum = $input['free_delivery_minimum'] ?? '';
            $minutes = $input['estimated_delivery_minutes'] ?? null;
            $isOpen = $input['is_open'] ?? null;
            $validMoney = static fn (mixed $value): bool =>
                (is_string($value) || is_int($value) || is_float($value))
                && preg_match('/^(?:0|[1-9]\d{0,7})(?:\.\d{1,2})?$/', (string) $value) === 1;
            if (!$validMoney($deliveryFee) || ($freeMinimum !== '' && !$validMoney($freeMinimum))
                || filter_var($minutes, FILTER_VALIDATE_INT) === false
                || (int) $minutes < 1 || (int) $minutes > 240
                || !is_bool($isOpen)) {
                respond(['error' => 'Confira as taxas, o tempo estimado e o status de funcionamento.'], 422);
            }
            $statement = $pdo->prepare(
                'UPDATE store_config SET name = ?, whatsapp_phone = ?, delivery_fee = ?,
                 free_delivery_minimum = ?, estimated_delivery_minutes = ?, is_open = ? WHERE id = 1'
            );
            $statement->execute([
                $name,
                $phone,
                number_format((float) $deliveryFee, 2, '.', ''),
                $freeMinimum === '' ? null : number_format((float) $freeMinimum, 2, '.', ''),
                (int) $minutes,
                $isOpen ? 1 : 0
            ]);
            respond(['message' => 'Configurações da loja atualizadas.']);

        case 'update_order_status':
            $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
            $status = $input['status'] ?? '';
            $transitions = [
                'pending' => ['confirmed', 'cancelled'],
                'confirmed' => ['preparing', 'cancelled'],
                'preparing' => ['ready', 'cancelled'],
                'ready' => ['delivered', 'cancelled'],
                'delivered' => [],
                'cancelled' => []
            ];
            if ($id === false || $id < 1 || !is_string($status) || !isset($transitions[$status])) {
                respond(['error' => 'O status do pedido é inválido.'], 422);
            }
            $pdo->beginTransaction();
            $orderQuery = $pdo->prepare(
                'SELECT status, payment_method, payment_status FROM orders WHERE id = ? FOR UPDATE'
            );
            $orderQuery->execute([$id]);
            $order = $orderQuery->fetch();
            if (!$order) {
                $pdo->rollBack();
                respond(['error' => 'Esse pedido não existe mais. Atualize o painel e tente novamente.'], 404);
            }
            if (!in_array($status, $transitions[$order['status']], true)) {
                $pdo->rollBack();
                respond(['error' => 'Não é possível alterar esse pedido para o status selecionado.'], 409);
            }
            if ($status === 'confirmed' && str_ends_with($order['payment_method'], '_online')
                && $order['payment_status'] !== 'approved') {
                $pdo->rollBack();
                respond(['error' => 'O pedido só pode ser confirmado após o pagamento ser aprovado.'], 409);
            }
            $statement = $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?');
            $statement->execute([$status, $id]);
            $pdo->commit();
            respond(['message' => 'Status do pedido atualizado.']);

        case 'create_admin':
            $username = requiredText($input['username'] ?? null, 'usuário', 50);
            if (!preg_match('/^[a-zA-Z0-9._-]{3,50}$/', $username)) {
                respond(['error' => 'Use de 3 a 50 letras, números, ponto, hífen ou sublinhado no usuário.'], 422);
            }
            $password = $input['password'] ?? null;
            if (!is_string($password) || strlen($password) < 12 || strlen($password) > 200) {
                respond(['error' => 'A senha deve ter entre 12 e 200 caracteres.'], 422);
            }
            $statement = $pdo->prepare(
                'INSERT INTO admin_users (username, password_hash) VALUES (?, ?)'
            );
            $statement->execute([strtolower($username), password_hash($password, PASSWORD_DEFAULT)]);
            respond(['message' => 'Acesso administrativo criado. Compartilhe a senha inicial com segurança.'], 201);

        case 'toggle_admin':
            $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
            $active = $input['active'] ?? null;
            if ($id === false || $id < 1 || !is_bool($active)) {
                respond(['error' => 'A ação sobre o acesso administrativo é inválida.'], 422);
            }
            if (!$active && $id === $admin['id']) {
                respond(['error' => 'Você não pode desativar a sua própria conta.'], 422);
            }
            $pdo->beginTransaction();
            $lockedAdmins = $pdo->query(
                'SELECT id, is_active FROM admin_users FOR UPDATE'
            )->fetchAll();
            $targetExists = false;
            $activeCount = 0;
            foreach ($lockedAdmins as $account) {
                if ((int) $account['id'] === $id) {
                    $targetExists = true;
                }
                if ((bool) $account['is_active']) {
                    $activeCount++;
                }
            }
            if (!$targetExists) {
                $pdo->rollBack();
                respond(['error' => 'Essa conta administrativa não existe.'], 404);
            }
            if (!$active && $activeCount <= 1) {
                $pdo->rollBack();
                respond(['error' => 'Mantenha pelo menos uma conta administrativa ativa.'], 422);
            }
            $statement = $pdo->prepare(
                'UPDATE admin_users SET is_active = ?, session_version = session_version + 1 WHERE id = ?'
            );
            $statement->execute([$active ? 1 : 0, $id]);
            $pdo->commit();
            respond(['message' => $active ? 'Acesso administrativo reativado.' : 'Acesso administrativo desativado.']);

        case 'change_password':
            $currentPassword = $input['current_password'] ?? null;
            $newPassword = $input['new_password'] ?? null;
            if (!is_string($currentPassword) || !is_string($newPassword)
                || strlen($newPassword) < 12 || strlen($newPassword) > 200) {
                respond(['error' => 'Informe a senha atual e uma nova senha com pelo menos 12 caracteres.'], 422);
            }
            $query = $pdo->prepare('SELECT password_hash FROM admin_users WHERE id = ? AND is_active = 1');
            $query->execute([$admin['id']]);
            $hash = $query->fetchColumn();
            if (!is_string($hash) || !password_verify($currentPassword, $hash)) {
                respond(['error' => 'A senha atual está incorreta.'], 401);
            }
            $statement = $pdo->prepare(
                'UPDATE admin_users SET password_hash = ?, session_version = session_version + 1 WHERE id = ?'
            );
            $statement->execute([password_hash($newPassword, PASSWORD_DEFAULT), $admin['id']]);
            $_SESSION['admin_session_version']++;
            respond(['message' => 'Senha alterada com sucesso.']);

        default:
            respond(['error' => 'A operação solicitada não existe.'], 404);
    }
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($exception->getCode() === '23000' && $action === 'save_product') {
        respond(['error' => 'Já existe um produto com esse nome.'], 409);
    }
    if ($exception->getCode() === '23000' && $action === 'create_admin') {
        respond(['error' => 'Já existe um usuário com esse nome.'], 409);
    }
    error_log('Falha na operação administrativa (' . $action . '): ' . $exception->getMessage());
    respond(['error' => 'Não foi possível concluir essa operação. Tente novamente em instantes.'], 503);
}
