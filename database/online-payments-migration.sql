USE acaidelivery_db;

ALTER TABLE orders
    MODIFY COLUMN payment_method ENUM(
        'pix',
        'credit_card',
        'debit_card',
        'cash',
        'mercado_pago',
        'mercado_pago_pix',
        'mercado_pago_credit_card',
        'pix_online',
        'credit_card_online'
    ) NOT NULL;

SET @add_payment_status = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'payment_status') = 0,
    "ALTER TABLE orders ADD COLUMN payment_status ENUM('not_required', 'pending', 'approved', 'rejected', 'cancelled', 'refunded') NOT NULL DEFAULT 'not_required'",
    'SELECT 1'
);
PREPARE add_payment_status_stmt FROM @add_payment_status;
EXECUTE add_payment_status_stmt;
DEALLOCATE PREPARE add_payment_status_stmt;

SET @add_syncpay_transaction_id = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'syncpay_transaction_id') = 0,
    'ALTER TABLE orders ADD COLUMN syncpay_transaction_id VARCHAR(100) NULL DEFAULT NULL',
    'SELECT 1'
);
PREPARE add_syncpay_transaction_id_stmt FROM @add_syncpay_transaction_id;
EXECUTE add_syncpay_transaction_id_stmt;
DEALLOCATE PREPARE add_syncpay_transaction_id_stmt;

SET @add_syncpay_transaction_index = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders'
       AND INDEX_NAME = 'uq_orders_syncpay_transaction_id') = 0,
    'ALTER TABLE orders ADD UNIQUE INDEX uq_orders_syncpay_transaction_id (syncpay_transaction_id)',
    'SELECT 1'
);
PREPARE add_syncpay_transaction_index_stmt FROM @add_syncpay_transaction_index;
EXECUTE add_syncpay_transaction_index_stmt;
DEALLOCATE PREPARE add_syncpay_transaction_index_stmt;

SET @add_syncpay_pix_code = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'syncpay_pix_code') = 0,
    'ALTER TABLE orders ADD COLUMN syncpay_pix_code TEXT NULL DEFAULT NULL',
    'SELECT 1'
);
PREPARE add_syncpay_pix_code_stmt FROM @add_syncpay_pix_code;
EXECUTE add_syncpay_pix_code_stmt;
DEALLOCATE PREPARE add_syncpay_pix_code_stmt;

CREATE TABLE IF NOT EXISTS syncpay_webhook_events (
    event_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
    received_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_syncpay_webhook_events_received_at (received_at)
) ENGINE=InnoDB;
