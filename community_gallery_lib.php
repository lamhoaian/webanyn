<?php

function ensureCommunityGallerySchema(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS community_posts (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS community_reactions (
        user_id INT(11) NOT NULL,
        post_id INT(11) NOT NULL,
        reaction_type ENUM('like','love','fire') NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id, post_id),
        INDEX idx_community_react_post (post_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function isCommunityAdmin(): bool
{
    return isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === 1;
}

function uploadCommunityImage(array $file, string $target_dir): ?string
{
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
        return null;
    }
    $name = time() . '_comm_' . uniqid() . '.' . $ext;
    $path = $target_dir . $name;
    if (move_uploaded_file($file['tmp_name'], $path)) {
        return '/anyn/uploads/community/' . $name;
    }
    return null;
}

function communityGuidelinesHtml(): string
{
    return '
    <ul class="guidelines-list">
        <li><strong>Fictional / AI art only</strong> — no real people, celebrities, or photos of real humans.</li>
        <li><strong>No minors</strong> — no underage characters or “aged-down” depictions.</li>
        <li><strong>No extreme violence</strong> — no gore, torture, or graphic harm.</li>
        <li><strong>No hate or harassment</strong> — no slurs, targeted abuse, or discrimination.</li>
        <li><strong>No sensitive religion or politics</strong> — no propaganda or inflammatory religious content.</li>
        <li><strong>NSFW still follows all rules above</strong> — suggestive art only; nothing illegal or non-consensual.</li>
        <li>All uploads are <strong>reviewed by admins</strong> before they appear publicly.</li>
    </ul>';
}

function handleCommunityReaction(PDO $pdo): void
{
    if (!isset($_SESSION['user_id'])) {
        header('Location: community_gallery.php?error=login');
        exit;
    }
    $post_id = (int)$_POST['post_id'];
    $r_type  = $_POST['reaction_type'];
    $u_id    = (int)$_SESSION['user_id'];

    if (!in_array($r_type, ['like', 'love', 'fire'], true)) {
        header('Location: community_gallery.php');
        exit;
    }

    $check = $pdo->prepare('SELECT reaction_type FROM community_reactions WHERE user_id = ? AND post_id = ?');
    $check->execute([$u_id, $post_id]);
    $existing = $check->fetch();

    if ($existing) {
        if ($existing['reaction_type'] === $r_type) {
            $pdo->prepare('DELETE FROM community_reactions WHERE user_id = ? AND post_id = ?')->execute([$u_id, $post_id]);
        } else {
            $pdo->prepare('UPDATE community_reactions SET reaction_type = ? WHERE user_id = ? AND post_id = ?')
                ->execute([$r_type, $u_id, $post_id]);
        }
    } else {
        $pdo->prepare('INSERT INTO community_reactions (user_id, post_id, reaction_type) VALUES (?, ?, ?)')
            ->execute([$u_id, $post_id, $r_type]);
        require_once __DIR__ . '/gems_lib.php';
        tryAwardMission($pdo, $u_id, 'react_community');
    }

    $rating = $_POST['return_rating'] ?? 'sfw';
    header('Location: community_gallery.php?tab=' . urlencode($rating));
    exit;
}

function loadCommunityReactionMaps(PDO $pdo): array
{
    $react_counts = [];
    foreach ($pdo->query('SELECT post_id, reaction_type, COUNT(*) AS cnt FROM community_reactions GROUP BY post_id, reaction_type') as $row) {
        $react_counts[$row['post_id']][$row['reaction_type']] = (int)$row['cnt'];
    }

    $user_reactions = [];
    if (isset($_SESSION['user_id'])) {
        $stmt = $pdo->prepare('SELECT post_id, reaction_type FROM community_reactions WHERE user_id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        foreach ($stmt->fetchAll() as $row) {
            $user_reactions[$row['post_id']] = $row['reaction_type'];
        }
    }

    return [$react_counts, $user_reactions];
}
