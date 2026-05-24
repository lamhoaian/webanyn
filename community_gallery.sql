-- Community gallery tables (also auto-created on first page visit)
CREATE TABLE IF NOT EXISTS community_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    title VARCHAR(120) NULL,
    image_url VARCHAR(255) NOT NULL,
    rating ENUM('sfw','nsfw') NOT NULL DEFAULT 'sfw',
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    admin_note VARCHAR(255) NULL,
    reviewed_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_community_status (status),
    INDEX idx_community_rating (rating),
    INDEX idx_community_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS community_reactions (
    user_id INT(11) NOT NULL,
    post_id INT(11) NOT NULL,
    reaction_type ENUM('like','love','fire') NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, post_id),
    INDEX idx_community_react_post (post_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
