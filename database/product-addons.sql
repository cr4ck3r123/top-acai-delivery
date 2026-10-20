-- Catálogo de complementos do Açaí Premium Especial.
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
