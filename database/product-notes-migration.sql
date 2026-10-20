USE acaidelivery_db;

SET @add_item_notes = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'order_items' AND COLUMN_NAME = 'item_notes') = 0,
    "ALTER TABLE order_items ADD COLUMN item_notes VARCHAR(300) NOT NULL DEFAULT ''",
    'SELECT 1'
);
PREPARE add_item_notes_stmt FROM @add_item_notes;
EXECUTE add_item_notes_stmt;
DEALLOCATE PREPARE add_item_notes_stmt;
