<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
requireMethod('GET');

try {
    $pdo = database();
    $store = $pdo->query(
        'SELECT name, whatsapp_phone, delivery_fee, free_delivery_minimum, estimated_delivery_minutes, is_open
         FROM store_config WHERE id = 1'
    )->fetch();

    if (!$store) {
        respond(['error' => 'Configure as informações da loja no banco de dados.'], 503);
    }

    $products = $pdo->query(
        'SELECT id, name, description, category, price, image_url, is_featured AS featured
         FROM products WHERE is_available = 1 ORDER BY category, sort_order, name'
    )->fetchAll();

    $addons = $pdo->query(
        'SELECT id, name, price FROM product_addons
         WHERE is_available = 1 ORDER BY sort_order, name'
    )->fetchAll();

    $categories = array_values(array_unique(array_column($products, 'category')));
    foreach ($products as &$product) {
        $product['id'] = (int) $product['id'];
        $product['price'] = (float) $product['price'];
        $product['featured'] = (bool) $product['featured'];
    }
    unset($product);

    foreach ($addons as &$addon) {
        $addon['id'] = (int) $addon['id'];
        $addon['price'] = (float) $addon['price'];
    }
    unset($addon);

    $store['delivery_fee'] = (float) $store['delivery_fee'];
    $store['free_delivery_minimum'] = $store['free_delivery_minimum'] === null
        ? 0
        : (float) $store['free_delivery_minimum'];
    $store['estimated_delivery_minutes'] = (int) $store['estimated_delivery_minutes'];
    $store['is_open'] = (bool) $store['is_open'];

    respond([
        'store' => $store,
        'categories' => $categories,
        'products' => $products,
        'addons' => $addons
    ]);
} catch (PDOException $exception) {
    error_log('Falha ao carregar cardápio: ' . $exception->getMessage());
    respond(['error' => 'Não foi possível carregar o cardápio agora. Tente novamente em instantes.'], 503);
}
