CREATE DATABASE IF NOT EXISTS pedidos
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

INSERT INTO store_config (id, name, whatsapp_phone, delivery_fee, free_delivery_minimum, estimated_delivery_minutes, is_open)
VALUES (1, 'Top Açaí Delivery', '5545988392692', 6.00, 80.00, 30, TRUE)
ON DUPLICATE KEY UPDATE id = id;

INSERT INTO products (name, description, category, price, image_url, is_featured, sort_order) VALUES
('Clássico da Brasa', 'Blend artesanal 180g, queijo prato, alface, tomate e molho da casa.', 'Hambúrgueres', 32.90, 'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?auto=format&fit=crop&w=900&q=80', TRUE, 1),
('Cheddar & Bacon', 'Blend 180g, cheddar cremoso, bacon crocante e cebola caramelizada.', 'Hambúrgueres', 36.90, 'https://images.unsplash.com/photo-1553979459-d2229ba7433a?auto=format&fit=crop&w=900&q=80', TRUE, 2),
('Duplo Smash', 'Dois smash burgers, queijo americano, picles e molho especial.', 'Hambúrgueres', 34.90, 'https://images.unsplash.com/photo-1550547660-d9450f859349?auto=format&fit=crop&w=900&q=80', FALSE, 3),
('Frango Crocante', 'Frango empanado artesanal, queijo, alface fresca e maionese de ervas.', 'Hambúrgueres', 29.90, 'https://images.unsplash.com/photo-1606755962773-d324e0a13086?auto=format&fit=crop&w=900&q=80', FALSE, 4),
('Batata da Casa', 'Batatas douradas com páprica e alecrim, acompanhadas de molho da casa.', 'Acompanhamentos', 18.90, 'https://images.unsplash.com/photo-1573080496219-bb080dd4f877?auto=format&fit=crop&w=900&q=80', TRUE, 1),
('Anéis de Cebola', 'Porção de anéis crocantes com maionese defumada.', 'Acompanhamentos', 19.90, 'https://images.unsplash.com/photo-1639024471283-03518883512d?auto=format&fit=crop&w=900&q=80', FALSE, 2),
('Milk-shake de Chocolate', 'Sorvete artesanal de chocolate batido com leite e calda.', 'Bebidas', 16.90, 'https://images.unsplash.com/photo-1572490122747-3968b75cc699?auto=format&fit=crop&w=900&q=80', FALSE, 1),
('Limonada da Casa', 'Limão-taiti espremido na hora, gelo e um toque de hortelã.', 'Bebidas', 9.90, 'https://images.unsplash.com/photo-1513558161293-cdaf765edfd7?auto=format&fit=crop&w=900&q=80', FALSE, 2)
ON DUPLICATE KEY UPDATE name = VALUES(name);
