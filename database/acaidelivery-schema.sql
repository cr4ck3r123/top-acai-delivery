-- Inicialização do banco da Top Açaí Delivery. Requer permissão CREATE DATABASE.
CREATE DATABASE IF NOT EXISTS acaidelivery_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
USE acaidelivery_db;

CREATE TABLE IF NOT EXISTS store_config (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    whatsapp_phone VARCHAR(20) NOT NULL,
    delivery_fee DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    free_delivery_minimum DECIMAL(10, 2) NULL DEFAULT NULL,
    estimated_delivery_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 35,
    is_open BOOLEAN NOT NULL DEFAULT TRUE,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_single_store CHECK (id = 1),
    CONSTRAINT chk_delivery_fee CHECK (delivery_fee >= 0),
    CONSTRAINT chk_delivery_minimum CHECK (free_delivery_minimum IS NULL OR free_delivery_minimum >= 0)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS admin_users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    session_version INT UNSIGNED NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_admin_users_username (username)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS admin_login_attempts (
    username VARCHAR(50) NOT NULL,
    ip_hash CHAR(64) NOT NULL,
    failed_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    window_started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    locked_until DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (username, ip_hash)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS products (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(300) NOT NULL DEFAULT '',
    category VARCHAR(60) NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    image_url VARCHAR(500) NOT NULL,
    is_featured BOOLEAN NOT NULL DEFAULT FALSE,
    is_available BOOLEAN NOT NULL DEFAULT TRUE,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_product_price CHECK (price >= 0),
    UNIQUE KEY uq_products_name (name),
    INDEX idx_products_menu (is_available, category, sort_order)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS product_addons (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL,
    price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    is_available BOOLEAN NOT NULL DEFAULT TRUE,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_product_addons_name (name),
    CONSTRAINT chk_product_addon_price CHECK (price >= 0)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS orders (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(100) NOT NULL,
    customer_phone VARCHAR(20) NOT NULL,
    fulfillment ENUM('delivery', 'pickup') NOT NULL,
    address VARCHAR(180) NOT NULL DEFAULT '',
    complement VARCHAR(100) NOT NULL DEFAULT '',
    neighborhood VARCHAR(100) NOT NULL DEFAULT '',
    payment_method ENUM('pix', 'credit_card', 'debit_card', 'cash', 'pix_online', 'credit_card_online') NOT NULL,
    payment_status ENUM('not_required', 'pending', 'approved', 'rejected', 'cancelled', 'refunded')
        NOT NULL DEFAULT 'not_required',
    syncpay_transaction_id VARCHAR(100) NULL DEFAULT NULL,
    syncpay_pix_code TEXT NULL DEFAULT NULL,
    cash_change DECIMAL(10, 2) NULL DEFAULT NULL,
    notes VARCHAR(500) NOT NULL DEFAULT '',
    subtotal DECIMAL(10, 2) NOT NULL,
    delivery_fee DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    total DECIMAL(10, 2) NOT NULL,
    status ENUM('pending', 'confirmed', 'preparing', 'ready', 'delivered', 'cancelled')
        NOT NULL DEFAULT 'pending',
    tracking_token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_orders_created (created_at),
    INDEX idx_orders_status (status, created_at),
    UNIQUE INDEX uq_orders_syncpay_transaction_id (syncpay_transaction_id),
    UNIQUE INDEX idx_orders_tracking_token_hash (tracking_token_hash),
    CONSTRAINT chk_order_amounts CHECK (subtotal >= 0 AND delivery_fee >= 0 AND total >= 0)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS syncpay_webhook_events (
    event_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
    received_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_syncpay_webhook_events_received_at (received_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS order_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NULL,
    product_name VARCHAR(120) NOT NULL,
    quantity SMALLINT UNSIGNED NOT NULL,
    unit_price DECIMAL(10, 2) NOT NULL,
    item_notes VARCHAR(300) NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE,
    CONSTRAINT fk_order_items_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE SET NULL,
    CONSTRAINT chk_order_item_quantity CHECK (quantity > 0),
    INDEX idx_order_items_order (order_id)
) ENGINE=InnoDB;


INSERT INTO store_config (id, name, whatsapp_phone, delivery_fee, free_delivery_minimum, estimated_delivery_minutes, is_open)
VALUES (1, 'Top Açaí Delivery', '5545988392692', 0.00, NULL, 35, TRUE)
ON DUPLICATE KEY UPDATE id = id;

-- Produtos transcritos do cardápio Top Açaí Delivery.
-- A operação é idempotente: nomes já cadastrados permanecem sem alteração.
INSERT INTO product_addons (name, price, is_available, sort_order) VALUES
    ('Porção de banana', 0.00, 1, 10),
    ('Porção de morango', 4.00, 1, 20),
    ('Creme de cookies', 0.00, 1, 30),
    ('Cobertura de chocolate', 0.00, 1, 40),
    ('Cobertura de morango', 0.00, 1, 50),
    ('Cobertura de pistache', 0.00, 1, 60),
    ('Creme de amendoim', 0.00, 1, 70),
    ('Creme de avelã', 0.00, 1, 80),
    ('Cobertura de mel', 0.00, 1, 90),
    ('Creme de brigadeiro', 0.00, 1, 100),
    ('Creme de leite Ninho', 0.00, 1, 110),
    ('Farofa de amendoim', 0.00, 1, 120),
    ('Flocos de arroz', 0.00, 1, 130),
    ('Disquetes', 0.00, 1, 140),
    ('Chocoball', 0.00, 1, 150),
    ('Granola', 0.00, 1, 160),
    ('Leite condensado', 0.00, 1, 170),
    ('Leite em pó', 0.00, 1, 180),
    ('Ovomaltine', 0.00, 1, 190),
    ('Paçoca', 0.00, 1, 200),
    ('Sucrilhos com açúcar', 0.00, 1, 210)
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO products
    (name, description, category, price, image_url, is_featured, is_available, sort_order)
VALUES
    ('Açaí Premium Puro 250 ml', 'Açaí puro e cremoso. Sabor original que você ama.', 'Açaí Premium Puro', 11.60, '/top-acai-hero.svg', 0, 1, 10),
    ('Açaí Premium Puro 300 ml', 'Açaí puro e cremoso. Sabor original que você ama.', 'Açaí Premium Puro', 13.60, '/top-acai-hero.svg', 0, 1, 20),
    ('Açaí Premium Puro 400 ml', 'Açaí puro e cremoso. Sabor original que você ama.', 'Açaí Premium Puro', 15.00, '/top-acai-hero.svg', 0, 1, 30),
    ('Açaí Premium Puro 500 ml', 'Açaí puro e cremoso. Sabor original que você ama.', 'Açaí Premium Puro', 18.70, '/top-acai-hero.svg', 0, 1, 40),
    ('Açaí Premium Camadinha 250 ml', 'Açaí com camadas de leite condensado e leite em pó.', 'Açaí Premium Camadinha', 14.86, '/top-acai-hero.svg', 0, 1, 110),
    ('Açaí Premium Camadinha 300 ml', 'Açaí com camadas de leite condensado e leite em pó.', 'Açaí Premium Camadinha', 16.60, '/top-acai-hero.svg', 0, 1, 120),
    ('Açaí Premium Camadinha 400 ml', 'Açaí com camadas de leite condensado e leite em pó.', 'Açaí Premium Camadinha', 19.60, '/top-acai-hero.svg', 0, 1, 130),
    ('Açaí Premium Camadinha 500 ml', 'Açaí com camadas de leite condensado e leite em pó.', 'Açaí Premium Camadinha', 22.68, '/top-acai-hero.svg', 0, 1, 140),
    ('Açaí Premium Camadinha 700 ml', 'Açaí com camadas de leite condensado e leite em pó.', 'Açaí Premium Camadinha', 28.60, '/top-acai-hero.svg', 0, 1, 150),
    ('Açaí Premium Camadinha 1000 ml', 'Açaí com camadas de leite condensado e leite em pó.', 'Açaí Premium Camadinha', 38.98, '/top-acai-hero.svg', 0, 1, 160),
    ('Açaí Premium Especial 250 ml', 'Monte seu copo com 4 complementos à sua escolha.', 'Açaí Premium Especial', 17.80, '/top-acai-hero.svg', 0, 1, 210),
    ('Açaí Premium Especial 300 ml', 'Monte seu copo com 4 complementos à sua escolha.', 'Açaí Premium Especial', 22.64, '/top-acai-hero.svg', 0, 1, 220),
    ('Açaí Premium Especial 400 ml', 'Monte seu copo com 4 complementos à sua escolha.', 'Açaí Premium Especial', 24.76, '/top-acai-hero.svg', 0, 1, 230),
    ('Açaí Premium Especial 500 ml', 'Monte seu copo com 4 complementos à sua escolha.', 'Açaí Premium Especial', 28.89, '/top-acai-hero.svg', 0, 1, 240),
    ('Açaí Premium Especial 700 ml', 'Monte seu copo com 4 complementos à sua escolha.', 'Açaí Premium Especial', 35.90, '/top-acai-hero.svg', 0, 1, 250),
    ('Açaí Premium Especial 1000 ml', 'Monte seu copo com 4 complementos à sua escolha.', 'Açaí Premium Especial', 48.89, '/top-acai-hero.svg', 0, 1, 260),
    ('Batidinha de Açaí Premium 300 ml', 'Cremosa, geladinha e irresistível.', 'Batidinha de Açaí Premium', 15.89, '/top-acai-hero.svg', 0, 1, 310),
    ('Batidinha de Açaí Premium 400 ml', 'Cremosa, geladinha e irresistível.', 'Batidinha de Açaí Premium', 18.89, '/top-acai-hero.svg', 0, 1, 320),
    ('Batidinha de Açaí Premium 500 ml', 'Cremosa, geladinha e irresistível.', 'Batidinha de Açaí Premium', 20.89, '/top-acai-hero.svg', 0, 1, 330),
    ('Batidinha de Açaí Premium 1000 ml', 'Cremosa, geladinha e irresistível.', 'Batidinha de Açaí Premium', 30.89, '/top-acai-hero.svg', 0, 1, 340)
ON DUPLICATE KEY UPDATE name = VALUES(name);
