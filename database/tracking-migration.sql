USE acaidelivery_db;

ALTER TABLE orders
    ADD COLUMN tracking_token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL,
    ADD UNIQUE INDEX idx_orders_tracking_token_hash (tracking_token_hash);
