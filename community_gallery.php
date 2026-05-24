<?php
require_once '../config/database.php';
require_once '../includes/community_gallery_lib.php';
require_once '../includes/gems_lib.php';

ensureCommunityGallerySchema($pdo);
ensureGemsSchema($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_nsfw'])) {
    $_SESSION['community_nsfw_ok'] = true;
    header('Location: community_gallery.php?tab=nsfw');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['react'])) {
    handleCommunityReaction($pdo);
}

$tab = ($_GET['tab'] ?? 'sfw') === 'nsfw' ? 'nsfw' : 'sfw';
$nsfw_unlocked = !empty($_SESSION['community_nsfw_ok']);
$is_admin = isCommunityAdmin();

$stmt = $pdo->prepare("
    SELECT cp.*, u.username
    FROM community_posts cp
    JOIN users u ON cp.user_id = u.id
    WHERE cp.status = 'approved' AND cp.rating = ?
    ORDER BY cp.reviewed_at DESC, cp.created_at DESC
");
$stmt->execute([$tab]);
$posts = $stmt->fetchAll();

[$react_counts, $user_reactions] = loadCommunityReactionMaps($pdo);

$my_pending_count = 0;
$my_rejected = [];
if (isset($_SESSION['user_id'])) {
    $uid = (int)$_SESSION['user_id'];
    $pc = $pdo->prepare("SELECT COUNT(*) FROM community_posts WHERE user_id = ? AND status = 'pending'");
    $pc->execute([$uid]);
    $my_pending_count = (int)$pc->fetchColumn();

    $rj = $pdo->prepare("SELECT title, admin_note, created_at FROM community_posts WHERE user_id = ? AND status = 'rejected' ORDER BY reviewed_at DESC LIMIT 5");
    $rj->execute([$uid]);
    $my_rejected = $rj->fetchAll();
}

$pending_admin_count = 0;
if ($is_admin) {
    $pending_admin_count = (int)$pdo->query("SELECT COUNT(*) FROM community_posts WHERE status = 'pending'")->fetchColumn();
}

$flash_error = isset($_GET['error']) && $_GET['error'] === 'login'
    ? 'Please sign in to react to artwork.'
    : null;

require_once '../includes/header.php';
?>

<style>
    .comm-header { display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px; margin-bottom: 24px; }
    .comm-tabs { display: flex; gap: 8px; flex-wrap: wrap; }
    .comm-tab {
        padding: 10px 18px; border-radius: 99px; font-weight: 700; font-size: 13px;
        border: 1.5px solid var(--border); color: var(--text-2); text-decoration: none;
        transition: all var(--transition); background: var(--surface);
    }
    .comm-tab:hover { border-color: var(--pink); color: var(--pink-dark); }
    .comm-tab.active { background: var(--pink-soft); border-color: var(--pink); color: var(--pink-dark); }
    .comm-tab.nsfw.active { background: rgba(192,38,211,.12); border-color: #d946ef; color: #a21caf; }
    .comm-actions { display: flex; gap: 10px; flex-wrap: wrap; }
    .guidelines-inline {
        background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius);
        padding: 18px 20px; margin-bottom: 24px;
    }
    .guidelines-inline h3 { margin: 0 0 10px; font-size: 15px; color: var(--pink-dark); }
    .guidelines-list { margin: 0; padding-left: 18px; color: var(--text-2); font-size: 13px; line-height: 1.6; }
    .guidelines-list li { margin-bottom: 4px; }
    .comm-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px; }
    .comm-card {
        background: var(--surface); border-radius: var(--radius); overflow: hidden;
        border: 1px solid var(--border); box-shadow: var(--shadow-sm); transition: all var(--transition);
    }
    .comm-card:hover { border-color: var(--pink); box-shadow: var(--shadow-md); transform: translateY(-3px); }
    .comm-img { width: 100%; aspect-ratio: 1; object-fit: cover; display: block; }
    .comm-body { padding: 14px 16px 16px; }
    .comm-title { font-weight: 700; font-size: 14px; color: var(--text); margin-bottom: 4px; }
    .comm-title.untitled { color: var(--text-3); font-style: italic; font-weight: 500; }
    .comm-meta { font-size: 11px; color: var(--text-3); margin-bottom: 10px; }
    .reaction-bar { display: flex; gap: 6px; flex-wrap: wrap; padding-top: 10px; border-top: 1px solid var(--border); }
    .btn-react {
        padding: 4px 10px; border-radius: 99px; font-weight: 700; font-size: 11px;
        border: 1.5px solid var(--border); cursor: pointer; background: var(--surface-2);
        color: var(--text-2); font-family: inherit; transition: all var(--transition);
    }
    .btn-react:hover { transform: scale(1.05); }
    .react-like.active  { background: #e0f2fe; color: #0284c7; border-color: #bae6fd; }
    .react-love.active  { background: #fce7f3; color: #db2777; border-color: #fbcfe8; }
    .react-fire.active  { background: #ffedd5; color: #ea580c; border-color: #fed7aa; }
    .nsfw-gate {
        text-align: center; padding: 60px 24px; background: var(--surface);
        border: 2px solid #f0abfc; border-radius: var(--radius); max-width: 520px; margin: 0 auto 32px;
    }
    .nsfw-gate i { font-size: 48px; color: #c026d3; margin-bottom: 16px; }
    .nsfw-gate h3 { margin: 0 0 12px; color: var(--text); }
    .nsfw-gate p { color: var(--text-2); font-size: 14px; line-height: 1.65; margin-bottom: 20px; }
    .btn-nsfw-enter {
        background: linear-gradient(135deg, #d946ef, #a21caf); color: white; border: none;
        padding: 12px 28px; border-radius: 12px; font-weight: 700; font-size: 14px;
        cursor: pointer; font-family: inherit; box-shadow: 0 8px 20px rgba(192,38,211,.35);
    }
    .btn-nsfw-cancel { display: inline-block; margin-top: 14px; color: var(--text-3); font-size: 13px; font-weight: 600; }
    .status-banner {
        padding: 12px 16px; border-radius: 10px; font-size: 13px; margin-bottom: 16px;
        background: #fffbeb; color: #b45309; border: 1px solid #fde68a;
    }
    .reject-box {
        background: #fef2f2; border: 1px solid #fca5a5; border-radius: 10px;
        padding: 12px 16px; margin-bottom: 16px; font-size: 13px; color: #b91c1c;
    }
    .admin-pill {
        display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px;
        background: #fef3c7; color: #b45309; border-radius: 99px; font-size: 12px; font-weight: 700;
        text-decoration: none; border: 1px solid #fde68a;
    }
</style>

<div class="comm-header">
    <div>
        <h2 class="section-title" style="margin-bottom:6px;">Community Gallery</h2>
        <p style="color:var(--text-3);font-size:14px;margin:0;">Fan art by the community — moderated &amp; guideline-safe.</p>
    </div>
    <div class="comm-actions">
        <?php if (isset($_SESSION['user_id'])): ?>
            <a href="community_upload.php" class="btn-pink"><i class="fa-solid fa-cloud-arrow-up"></i> Upload</a>
        <?php else: ?>
            <a href="login.php" class="btn-pink"><i class="fa-regular fa-user"></i> Sign in to upload</a>
        <?php endif; ?>
        <?php if ($is_admin && $pending_admin_count > 0): ?>
            <a href="admin_community.php" class="admin-pill">
                <i class="fa-solid fa-gavel"></i> <?= $pending_admin_count ?> pending
            </a>
        <?php elseif ($is_admin): ?>
            <a href="admin_community.php" class="btn-pink" style="background:#8b5cf6;"><i class="fa-solid fa-gavel"></i> Moderate</a>
        <?php endif; ?>
    </div>
</div>

<div class="guidelines-inline">
    <h3><i class="fa-solid fa-shield-heart"></i> Community Standards</h3>
    <?= communityGuidelinesHtml() ?>
</div>

<?php if ($flash_error): ?>
    <div class="reject-box" style="background:#fef2f2;color:#b91c1c;"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($flash_error) ?></div>
<?php endif; ?>

<?php if ($my_pending_count > 0): ?>
    <div class="status-banner">
        <i class="fa-solid fa-hourglass-half"></i>
        You have <?= $my_pending_count ?> upload<?= $my_pending_count > 1 ? 's' : '' ?> waiting for admin approval.
    </div>
<?php endif; ?>

<?php foreach ($my_rejected as $rj): ?>
    <div class="reject-box">
        <strong>Upload declined</strong>
        <?php if (!empty($rj['title'])): ?> — <?= htmlspecialchars($rj['title']) ?><?php endif; ?>
        <?php if (!empty($rj['admin_note'])): ?><br><span style="color:#991b1b;">Reason: <?= htmlspecialchars($rj['admin_note']) ?></span><?php endif; ?>
    </div>
<?php endforeach; ?>

<div class="comm-tabs" style="margin-bottom:24px;">
    <a href="community_gallery.php?tab=sfw" class="comm-tab <?= $tab === 'sfw' ? 'active' : '' ?>">SFW</a>
    <a href="community_gallery.php?tab=nsfw" class="comm-tab nsfw <?= $tab === 'nsfw' ? 'active' : '' ?>">NSFW</a>
</div>

<?php if ($tab === 'nsfw' && !$nsfw_unlocked): ?>
    <div class="nsfw-gate animate-in">
        <i class="fa-solid fa-triangle-exclamation"></i>
        <h3>NSFW Content Warning</h3>
        <p>
            This section may contain mature or suggestive artwork intended for adults.
            By continuing, you confirm you are <strong>18+</strong> and understand this content is fictional fan art
            subject to the same community rules (no real people, no minors, no extreme violence, etc.).
        </p>
        <form method="POST">
            <button type="submit" name="confirm_nsfw" value="1" class="btn-nsfw-enter">I am 18+ — Enter NSFW</button>
        </form>
        <a href="community_gallery.php?tab=sfw" class="btn-nsfw-cancel">Go back to SFW</a>
    </div>
<?php else: ?>

    <?php if (empty($posts)): ?>
        <div style="text-align:center;padding:60px 20px;color:var(--text-3);background:var(--surface);border-radius:var(--radius);">
            <i class="fa-regular fa-images" style="font-size:40px;display:block;margin-bottom:12px;"></i>
            No <?= strtoupper($tab) ?> community art yet. Be the first to upload!
        </div>
    <?php else: ?>
        <div class="comm-grid">
            <?php foreach ($posts as $item):
                $pid = (int)$item['id'];
                $ur  = $user_reactions[$pid] ?? null;
                $has_title = !empty(trim($item['title'] ?? ''));
            ?>
            <article class="comm-card animate-in">
                <img src="<?= htmlspecialchars($item['image_url']) ?>" class="comm-img" alt="<?= htmlspecialchars($item['title'] ?: 'Community art') ?>" loading="lazy">
                <div class="comm-body">
                    <?php if ($has_title): ?>
                        <div class="comm-title"><?= htmlspecialchars($item['title']) ?></div>
                    <?php else: ?>
                        <div class="comm-title untitled">Untitled</div>
                    <?php endif; ?>
                    <div class="comm-meta">by <strong><?= htmlspecialchars($item['username']) ?></strong> · <?= date('M d, Y', strtotime($item['created_at'])) ?></div>
                    <div class="reaction-bar">
                        <?php foreach (['like' => '👍', 'love' => '❤️', 'fire' => '🔥'] as $rtype => $emoji):
                            $cnt    = $react_counts[$pid][$rtype] ?? 0;
                            $active = ($ur === $rtype) ? 'active' : '';
                        ?>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="post_id" value="<?= $pid ?>">
                            <input type="hidden" name="reaction_type" value="<?= $rtype ?>">
                            <input type="hidden" name="return_rating" value="<?= htmlspecialchars($tab) ?>">
                            <button type="submit" name="react" class="btn-react react-<?= $rtype ?> <?= $active ?>"><?= $emoji ?> <?= $cnt ?></button>
                        </form>
                        <?php endforeach; ?>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
