-- Gallery groups: run once in phpMyAdmin if auto-migration does not apply
CREATE TABLE IF NOT EXISTS gallery_groups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100) NULL,
    bot_id INT(11) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_gallery_groups_bot (bot_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE gallery ADD COLUMN group_id INT(11) NULL AFTER title;
ALTER TABLE gallery ADD INDEX idx_gallery_group (group_id);
