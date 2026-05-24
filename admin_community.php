<?php
require_once '../config/database.php';
require_once '../includes/community_gallery_lib.php';

if (!isCommunityAdmin()) {
    die("<h3 style='color:#ef4444;text-align:center;margin-top:50px;'>Access Denied! Admin privileges required.</h3>");
}

ensureCommunityGallerySchema($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['moderate_id'])) {
    $id     = (int)$_POST['moderate_id'];
    $action = $_POST['moderate_action'] ?? '';
    $note   = trim($_POST['admin_note'] ?? '') ?: null;

    if ($action === 'approve') {
        $pdo->prepare("UPDATE community_posts SET status = 'approved', admin_note = NULL, reviewed_at = NOW() WHERE id = ?")
            ->execute([$id]);
        header('Location: admin_community.php?msg=approved');
        exit;
    }
    if ($action === 'reject') {
        $pdo->prepare("UPDATE community_posts SET status = 'rejected', admin_note = ?, reviewed_at = NOW() WHERE id = ?")
            ->execute([$note, $id]);
        header('Location: admin_community.php?msg=rejected');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_post_id'])) {
    $id = (int)$_POST['delete_post_id'];
    $pdo->prepare('DELETE FROM community_reactions WHERE post_id = ?')->execute([$id]);
    $pdo->prepare('DELETE FROM community_posts WHERE id = ?')->execute([$id]);
    header('Location: admin_community.php?msg=deleted');
    exit;
}

$pending = $pdo->query("
    SELECT cp.*, u.username
    FROM community_posts cp
    JOIN users u ON cp.user_id = u.id
    WHERE cp.status = 'pending'
    ORDER BY cp.created_at ASC
")->fetchAll();

$approved_recent = $pdo->query("
    SELECT cp.*, u.username
    FROM community_posts cp
    JOIN users u ON cp.user_id = u.id
    WHERE cp.status = 'approved'
    ORDER BY cp.reviewed_at DESC, cp.created_at DESC
    LIMIT 24
")->fetchAll();

$flash = [
    'approved' => 'Post approved and is now public.',
    'rejected' => 'Post rejected.',
    'deleted'  => 'Post removed from gallery.',
];
$message = isset($_GET['msg'], $flash[$_GET['msg']]) ? $flash[$_GET['msg']] : '';

require_once '../includes/header.php';
?>

<style>
    .mod-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 24px; }
    .mod-title { margin: 0; color: var(--pink-dark); font-size: 26px; }
    .mod-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; }
    .mod-card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; box-shadow: var(--shadow-sm); }
    .mod-card img { width: 100%; aspect-ratio: 1; object-fit: cover; display: block; }
    .mod-card-body { padding: 14px; }
    .mod-badge { display: inline-block; padding: 3px 8px; border-radius: 6px; font-size: 10px; font-weight: 800; text-transform: uppercase; margin-bottom: 8px; }
    .mod-badge.sfw { background: #dbeafe; color: #1d4ed8; }
    .mod-badge.nsfw { background: #fce7f3; color: #be185d; }
    .mod-meta { font-size: 12px; color: var(--text-3); margin-bottom: 10px; }
    .mod-actions { display: flex; gap: 8px; flex-wrap: wrap; }
    .btn-approve { background: #10b981; color: white; border: none; padding: 8px 12px; border-radius: 8px; font-weight: 700; font-size: 12px; cursor: pointer; font-family: inherit; }
    .btn-reject { background: #fef2f2; color: #ef4444; border: 1px solid #fca5a5; padding: 8px 12px; border-radius: 8px; font-weight: 700; font-size: 12px; cursor: pointer; font-family: inherit; }
    .btn-del { background: var(--surface-2); color: var(--text-2); border: 1px solid var(--border); padding: 8px 12px; border-radius: 8px; font-size: 12px; cursor: pointer; font-family: inherit; }
    .reject-note { width: 100%; margin-top: 8px; padding: 8px; border-radius: 8px; border: 1px solid var(--border); font-size: 12px; font-family: inherit; resize: vertical; min-height: 50px; }
    .empty-mod { text-align: center; padding: 40px; color: var(--text-3); background: var(--surface); border-radius: var(--radius); }
</style>

<div class="mod-header">
    <h2 class="mod-title"><i class="fa-solid fa-gavel"></i> Community Moderation</h2>
    <a href="admin_bots.php" class="btn-pink" style="text-decoration:none;"><i class="fa-solid fa-arrow-left"></i> Admin Hub</a>
</div>

<?php if ($message): ?>
    <div style="padding:12px 16px;border-radius:10px;background:#f0fdf4;color:#16a34a;font-weight:600;margin-bottom:20px;"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<h3 style="color:var(--text);margin-bottom:16px;">Pending review (<?= count($pending) ?>)</h3>

<?php if (empty($pending)): ?>
    <div class="empty-mod"><i class="fa-regular fa-circle-check" style="font-size:32px;display:block;margin-bottom:10px;"></i>No pending uploads.</div>
<?php else: ?>
    <div class="mod-grid" style="margin-bottom:40px;">
        <?php foreach ($pending as $p): ?>
        <div class="mod-card">
            <img src="<?= htmlspecialchars($p['image_url']) ?>" alt="">
            <div class="mod-card-body">
                <span class="mod-badge <?= htmlspecialchars($p['rating']) ?>"><?= strtoupper($p['rating']) ?></span>
                <div class="mod-meta">
                    By <strong><?= htmlspecialchars($p['username']) ?></strong>
                    · <?= date('M d, Y H:i', strtotime($p['created_at'])) ?>
                </div>
                <?php if (!empty($p['title'])): ?>
                    <div style="font-weight:700;font-size:14px;margin-bottom:10px;"><?= htmlspecialchars($p['title']) ?></div>
                <?php endif; ?>
                <form method="POST">
                    <input type="hidden" name="moderate_id" value="<?= (int)$p['id'] ?>">
                    <div class="mod-actions">
                        <button type="submit" name="moderate_action" value="approve" class="btn-approve"><i class="fa-solid fa-check"></i> Approve</button>
                        <button type="submit" name="moderate_action" value="reject" class="btn-reject" onclick="return confirm('Reject this upload?');"><i class="fa-solid fa-xmark"></i> Reject</button>
                    </div>
                    <textarea name="admin_note" class="reject-note" placeholder="Rejection reason (optional, shown to user)"></textarea>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<h3 style="color:var(--text);margin-bottom:16px;">Recently approved</h3>
<?php if (empty($approved_recent)): ?>
    <div class="empty-mod">No approved community posts yet.</div>
<?php else: ?>
    <div class="mod-grid">
        <?php foreach ($approved_recent as $p): ?>
        <div class="mod-card">
            <img src="<?= htmlspecialchars($p['image_url']) ?>" alt="">
            <div class="mod-card-body">
                <span class="mod-badge <?= htmlspecialchars($p['rating']) ?>"><?= strtoupper($p['rating']) ?></span>
                <div class="mod-meta"><?= htmlspecialchars($p['username']) ?></div>
                <form method="POST" onsubmit="return confirm('Remove this post permanently?');">
                    <input type="hidden" name="delete_post_id" value="<?= (int)$p['id'] ?>">
                    <button type="submit" class="btn-del"><i class="fa-solid fa-trash"></i> Delete</button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
