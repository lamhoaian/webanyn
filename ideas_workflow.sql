-- Idea workflow columns (auto-applied on first visit to ideas.php)
ALTER TABLE ideas ADD COLUMN work_status ENUM('open','in_progress','completed') NOT NULL DEFAULT 'open' AFTER upvotes;
ALTER TABLE ideas ADD COLUMN bot_id INT(11) NULL AFTER work_status;
ALTER TABLE ideas ADD COLUMN bot_visibility ENUM('published','unlisted') NULL AFTER bot_id;
ALTER TABLE ideas ADD COLUMN completed_at TIMESTAMP NULL DEFAULT NULL AFTER bot_visibility;
ALTER TABLE ideas ADD COLUMN unlisted_link VARCHAR(500) NULL AFTER bot_visibility;
