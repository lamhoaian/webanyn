<?php

function ensureGemsSchema(PDO $pdo): void
{
    if (!$pdo->query("SHOW COLUMNS FROM users LIKE 'gems'")->fetch()) {
        $pdo->exec('ALTER TABLE users ADD COLUMN gems INT(11) NOT NULL DEFAULT 0 AFTER total_spent');
    }
    if (!$pdo->query("SHOW COLUMNS FROM users LIKE 'active_chat_frame'")->fetch()) {
        $pdo->exec('ALTER TABLE users ADD COLUMN active_chat_frame VARCHAR(50) NULL');
    }
    if (!$pdo->query("SHOW COLUMNS FROM users LIKE 'active_avatar_frame'")->fetch()) {
        $pdo->exec('ALTER TABLE users ADD COLUMN active_avatar_frame VARCHAR(50) NULL');
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS shop_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        item_key VARCHAR(80) NOT NULL UNIQUE,
        name VARCHAR(120) NOT NULL,
        category VARCHAR(40) NOT NULL,
        cost_gems INT(11) NOT NULL,
        description VARCHAR(255) NULL,
        meta_json TEXT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS user_inventory (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT(11) NOT NULL,
        item_key VARCHAR(80) NOT NULL,
        item_type VARCHAR(40) NOT NULL,
        meta_json TEXT NULL,
        used_at TIMESTAMP NULL DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_inv_user (user_id),
        INDEX idx_inv_type (user_id, item_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS gem_mission_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT(11) NOT NULL,
        mission_key VARCHAR(50) NOT NULL,
        log_date DATE NOT NULL,
        count INT(11) NOT NULL DEFAULT 1,
        UNIQUE KEY uq_mission_day (user_id, mission_key, log_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    seedShopItems($pdo);
}

function gemsMissionDefinitions(): array
{
    return [
        'daily_checkin'      => ['name' => 'Daily check-in',           'gems' => 5,  'daily_max' => 1],
        'react_gallery'      => ['name' => 'Like gallery art',         'gems' => 2,  'daily_max' => 10],
        'react_community'    => ['name' => 'React to community art',   'gems' => 2,  'daily_max' => 10],
        'upload_community'   => ['name' => 'Upload community art',     'gems' => 15, 'daily_max' => 2],
        'submit_idea'        => ['name' => 'Submit an idea',           'gems' => 10, 'daily_max' => 3],
        'upvote_idea'        => ['name' => 'Upvote an idea',           'gems' => 1,  'daily_max' => 20],
        'submit_commission'  => ['name' => 'Submit a commission',      'gems' => 25, 'daily_max' => 2],
    ];
}

function seedShopItems(PDO $pdo): void
{
    $items = [
        ['chat_sakura', 'Sakura Chat Frame', 'chat_frame', 30, 'Pink blossom border on your chat bubbles', '{}', 10],
        ['chat_neon', 'Neon Chat Frame', 'chat_frame', 50, 'Glowing cyan/pink chat style', '{}', 20],
        ['chat_royal', 'Royal Chat Frame', 'chat_frame', 80, 'Gold-trimmed premium chat look', '{}', 30],
        ['chat_stardust', 'Stardust Chat Frame', 'chat_frame', 100, 'Sparkle gradient chat frame', '{}', 40],
        ['avt_gold', 'Gold Avatar Ring', 'avatar_frame', 25, 'Golden ring around your avatar', '{}', 50],
        ['avt_crystal', 'Crystal Avatar Ring', 'avatar_frame', 45, 'Icy crystal avatar border', '{}', 60],
        ['avt_bloom', 'Bloom Avatar Ring', 'avatar_frame', 60, 'Floral glow avatar frame', '{}', 70],
        ['coupon_commission_publish', 'Commission — Published', 'commission_publish', 120, 'Voucher for a published store commission (use when ordering)', '{}', 100],
        ['coupon_commission_unlisted', 'Commission — Unlisted', 'commission_unlisted', 100, 'Voucher for an unlisted commission (use when ordering)', '{}', 110],
        ['imageset_3', 'Character Image Set ×3', 'image_set', 3, '3 custom images for your character', '{"images":3}', 200],
        ['imageset_5', 'Character Image Set ×5', 'image_set', 5, '5 custom images', '{"images":5}', 210],
        ['imageset_10', 'Character Image Set ×10', 'image_set', 10, '10 custom images', '{"images":10}', 220],
        ['imageset_15', 'Character Image Set ×15', 'image_set', 15, '15 custom images', '{"images":15}', 230],
        ['imageset_20', 'Character Image Set ×20', 'image_set', 20, '20 custom images', '{"images":20}', 240],
        ['imageset_30', 'Character Image Set ×30', 'image_set', 30, '30 custom images (~$3 value)', '{"images":30,"usd":3}', 250],
    ];

    $stmt = $pdo->prepare('INSERT IGNORE INTO shop_items (item_key, name, category, cost_gems, description, meta_json, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)');
    foreach ($items as $row) {
        $stmt->execute($row);
    }
}

function getUserGems(PDO $pdo, int $userId): int
{
    $s = $pdo->prepare('SELECT gems FROM users WHERE id = ?');
    $s->execute([$userId]);
    return (int)($s->fetchColumn() ?: 0);
}

function addGems(PDO $pdo, int $userId, int $amount): void
{
    if ($amount <= 0) {
        return;
    }
    $pdo->prepare('UPDATE users SET gems = gems + ? WHERE id = ?')->execute([$amount, $userId]);
}

function spendGems(PDO $pdo, int $userId, int $amount): bool
{
    if ($amount <= 0 || getUserGems($pdo, $userId) < $amount) {
        return false;
    }
    $pdo->prepare('UPDATE users SET gems = gems - ? WHERE id = ?')->execute([$amount, $userId]);
    return true;
}

function tryAwardMission(PDO $pdo, int $userId, string $missionKey): array
{
    $defs = gemsMissionDefinitions();
    if (!isset($defs[$missionKey])) {
        return ['ok' => false, 'gems' => 0, 'message' => 'Unknown mission.'];
    }
    $def = $defs[$missionKey];
    $today = date('Y-m-d');

    $log = $pdo->prepare('SELECT count FROM gem_mission_log WHERE user_id = ? AND mission_key = ? AND log_date = ?');
    $log->execute([$userId, $missionKey, $today]);
    $row = $log->fetch();
    $current = $row ? (int)$row['count'] : 0;

    if ($current >= $def['daily_max']) {
        return ['ok' => false, 'gems' => 0, 'message' => 'Daily limit reached for this mission.'];
    }

    if ($row) {
        $pdo->prepare('UPDATE gem_mission_log SET count = count + 1 WHERE user_id = ? AND mission_key = ? AND log_date = ?')
            ->execute([$userId, $missionKey, $today]);
    } else {
        $pdo->prepare('INSERT INTO gem_mission_log (user_id, mission_key, log_date, count) VALUES (?, ?, ?, 1)')
            ->execute([$userId, $missionKey, $today]);
    }

    addGems($pdo, $userId, $def['gems']);
    return [
        'ok'      => true,
        'gems'    => $def['gems'],
        'message' => '+' . $def['gems'] . ' gems — ' . $def['name'],
    ];
}

function getMissionProgress(PDO $pdo, int $userId): array
{
    $today = date('Y-m-d');
    $out = [];
    foreach (gemsMissionDefinitions() as $key => $def) {
        $log = $pdo->prepare('SELECT count FROM gem_mission_log WHERE user_id = ? AND mission_key = ? AND log_date = ?');
        $log->execute([$userId, $key, $today]);
        $done = (int)($log->fetchColumn() ?: 0);
        $out[$key] = [
            'name'      => $def['name'],
            'gems'      => $def['gems'],
            'daily_max' => $def['daily_max'],
            'done'      => $done,
        ];
    }
    return $out;
}

function getShopItems(PDO $pdo, ?string $category = null): array
{
    if ($category) {
        $s = $pdo->prepare('SELECT * FROM shop_items WHERE is_active = 1 AND category = ? ORDER BY sort_order, cost_gems');
        $s->execute([$category]);
        return $s->fetchAll();
    }
    return $pdo->query('SELECT * FROM shop_items WHERE is_active = 1 ORDER BY sort_order, cost_gems')->fetchAll();
}

function getShopItem(PDO $pdo, string $itemKey): ?array
{
    $s = $pdo->prepare('SELECT * FROM shop_items WHERE item_key = ? AND is_active = 1');
    $s->execute([$itemKey]);
    $row = $s->fetch();
    return $row ?: null;
}

function userOwnsItem(PDO $pdo, int $userId, string $itemKey): bool
{
    $s = $pdo->prepare('SELECT id FROM user_inventory WHERE user_id = ? AND item_key = ? AND used_at IS NULL LIMIT 1');
    $s->execute([$userId, $itemKey]);
    return (bool)$s->fetch();
}

function countUnusedInventory(PDO $pdo, int $userId, string $itemType): int
{
    $s = $pdo->prepare('SELECT COUNT(*) FROM user_inventory WHERE user_id = ? AND item_type = ? AND used_at IS NULL');
    $s->execute([$userId, $itemType]);
    return (int)$s->fetchColumn();
}

function purchaseShopItem(PDO $pdo, int $userId, string $itemKey): array
{
    $item = getShopItem($pdo, $itemKey);
    if (!$item) {
        return ['ok' => false, 'message' => 'Item not found.'];
    }

    $cosmeticTypes = ['chat_frame', 'avatar_frame'];
    if (in_array($item['category'], $cosmeticTypes, true) && userOwnsItem($pdo, $userId, $itemKey)) {
        return ['ok' => false, 'message' => 'You already own this cosmetic.'];
    }

    if (!spendGems($pdo, $userId, (int)$item['cost_gems'])) {
        return ['ok' => false, 'message' => 'Not enough gems.'];
    }

    $pdo->prepare('INSERT INTO user_inventory (user_id, item_key, item_type, meta_json) VALUES (?, ?, ?, ?)')
        ->execute([$userId, $itemKey, $item['category'], $item['meta_json']]);

    if ($item['category'] === 'chat_frame') {
        $pdo->prepare('UPDATE users SET active_chat_frame = ? WHERE id = ?')->execute([$itemKey, $userId]);
    } elseif ($item['category'] === 'avatar_frame') {
        $pdo->prepare('UPDATE users SET active_avatar_frame = ? WHERE id = ?')->execute([$itemKey, $userId]);
    }

    return ['ok' => true, 'message' => 'Purchased: ' . $item['name']];
}

function equipCosmetic(PDO $pdo, int $userId, string $type, ?string $itemKey): array
{
    if ($type === 'chat_frame') {
        if ($itemKey && !userOwnsItem($pdo, $userId, $itemKey)) {
            return ['ok' => false, 'message' => 'You do not own this frame.'];
        }
        $pdo->prepare('UPDATE users SET active_chat_frame = ? WHERE id = ?')->execute([$itemKey, $userId]);
        return ['ok' => true, 'message' => $itemKey ? 'Chat frame equipped.' : 'Chat frame removed.'];
    }
    if ($type === 'avatar_frame') {
        if ($itemKey && !userOwnsItem($pdo, $userId, $itemKey)) {
            return ['ok' => false, 'message' => 'You do not own this frame.'];
        }
        $pdo->prepare('UPDATE users SET active_avatar_frame = ? WHERE id = ?')->execute([$itemKey, $userId]);
        return ['ok' => true, 'message' => $itemKey ? 'Avatar frame equipped.' : 'Avatar frame removed.'];
    }
    return ['ok' => false, 'message' => 'Invalid type.'];
}

function consumeInventoryCoupon(PDO $pdo, int $userId, string $itemType): ?int
{
    $s = $pdo->prepare('SELECT id FROM user_inventory WHERE user_id = ? AND item_type = ? AND used_at IS NULL ORDER BY id ASC LIMIT 1');
    $s->execute([$userId, $itemType]);
    $row = $s->fetch();
    if (!$row) {
        return null;
    }
    $pdo->prepare('UPDATE user_inventory SET used_at = NOW() WHERE id = ?')->execute([$row['id']]);
    return (int)$row['id'];
}

function getUserInventory(PDO $pdo, int $userId, ?string $type = null): array
{
    if ($type) {
        $s = $pdo->prepare('SELECT ui.*, si.name FROM user_inventory ui LEFT JOIN shop_items si ON ui.item_key = si.item_key WHERE ui.user_id = ? AND ui.item_type = ? ORDER BY ui.created_at DESC');
        $s->execute([$userId, $type]);
        return $s->fetchAll();
    }
    $s = $pdo->prepare('SELECT ui.*, si.name FROM user_inventory ui LEFT JOIN shop_items si ON ui.item_key = si.item_key WHERE ui.user_id = ? ORDER BY ui.created_at DESC');
    $s->execute([$userId]);
    return $s->fetchAll();
}

function shopCategoryLabel(string $cat): string
{
    return match ($cat) {
        'chat_frame'           => 'Chat frames',
        'avatar_frame'         => 'Avatar frames',
        'commission_publish'   => 'Commission vouchers',
        'commission_unlisted'  => 'Commission vouchers',
        'image_set'            => 'Character image sets',
        default                => ucfirst(str_replace('_', ' ', $cat)),
    };
}
