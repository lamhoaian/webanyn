<?php
require_once '../config/database.php';
require_once '../includes/community_gallery_lib.php';
require_once '../includes/gems_lib.php';

ensureCommunityGallerySchema($pdo);
ensureGemsSchema($pdo);

if (!isset($_SESSION['user_id'])) {
    require_once '../includes/header.php';
    echo "<div style='text-align:center;padding:60px 20px;background:var(--surface);border-radius:var(--radius);box-shadow:var(--shadow-md);'>";
    echo "<h3 style='color:var(--text-2);margin-bottom:12px;'>Sign in required</h3>";
    echo "<p style='color:var(--text-3);'>You must <a href='login.php' style='color:var(--pink-dark);font-weight:700;'>log in</a> to upload community art.</p>";
    echo "</div>";
    require_once '../includes/footer.php';
    exit;
}

$message = '';
$message_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_community'])) {
    $rating = ($_POST['rating'] ?? '') === 'nsfw' ? 'nsfw' : 'sfw';
    $title  = trim($_POST['title'] ?? '') ?: null;
    $agreed = isset($_POST['agree_guidelines']);

    if (!$agreed) {
        $message = 'You must accept the Community Guidelines to upload.';
        $message_type = 'error';
    } elseif (!isset($_FILES['image_file']) || $_FILES['image_file']['error'] !== UPLOAD_ERR_OK) {
        $message = 'Please select an image to upload.';
        $message_type = 'error';
    } else {
        $url = uploadCommunityImage($_FILES['image_file'], '../uploads/community/');
        if (!$url) {
            $message = 'Invalid file or upload failed. Use JPG, PNG, GIF, or WEBP.';
            $message_type = 'error';
        } else {
            $pdo->prepare(
                'INSERT INTO community_posts (user_id, title, image_url, rating, status) VALUES (?, ?, ?, ?, ?)'
            )->execute([(int)$_SESSION['user_id'], $title, $url, $rating, 'pending']);
            tryAwardMission($pdo, (int)$_SESSION['user_id'], 'upload_community');
            header('Location: community_upload.php?msg=submitted');
            exit;
        }
    }
}

if (isset($_GET['msg']) && $_GET['msg'] === 'submitted') {
    $message = 'Your artwork was submitted! It will appear after admin approval.';
}

require_once '../includes/header.php';
?>

<style>
    .comm-upload-wrap { max-width: 720px; margin: 0 auto; }
    .comm-panel {
        background: var(--surface); border-radius: var(--radius);
        padding: 28px; box-shadow: var(--shadow-md); border: 1px solid var(--border);
    }
    .comm-page-title { font-size: 26px; color: var(--pink-dark); margin: 0 0 8px; }
    .comm-page-sub { color: var(--text-3); font-size: 14px; margin-bottom: 24px; }
    .guidelines-box {
        background: var(--pink-soft); border: 1px dashed var(--pink);
        border-radius: 12px; padding: 18px 20px; margin-bottom: 22px;
    }
    .guidelines-box h3 { margin: 0 0 12px; font-size: 15px; color: var(--pink-dark); }
    .guidelines-list { margin: 0; padding-left: 18px; color: var(--text-2); font-size: 13px; line-height: 1.65; }
    .guidelines-list li { margin-bottom: 6px; }
    .form-group { margin-bottom: 18px; }
    .form-group label { display: block; font-size: 12px; font-weight: 700; color: var(--text-2); margin-bottom: 8px; text-transform: uppercase; letter-spacing: .4px; }
    .rating-row { display: flex; gap: 12px; flex-wrap: wrap; }
    .rating-option {
        flex: 1; min-width: 140px; padding: 12px 14px; border-radius: 10px;
        border: 1.5px solid var(--border); cursor: pointer; text-align: center;
        font-size: 13px; font-weight: 700; color: var(--text-2); transition: .2s;
    }
    .rating-option input { display: none; }
    .rating-option:has(input:checked) { border-color: var(--pink); background: var(--pink-soft); color: var(--pink-dark); }
    .rating-option.nsfw-opt:has(input:checked) { border-color: #c026d3; background: rgba(192,38,211,.1); color: #a21caf; }
    .agree-row { display: flex; align-items: flex-start; gap: 10px; font-size: 13px; color: var(--text-2); margin-bottom: 20px; }
    .agree-row input { margin-top: 3px; accent-color: var(--pink-dark); }
    .alert { padding: 12px 16px; border-radius: 10px; font-size: 13px; font-weight: 600; margin-bottom: 18px; }
    .alert-success { background: #f0fdf4; color: #16a34a; }
    .alert-error { background: #fef2f2; color: #ef4444; }
    .back-row { margin-bottom: 20px; }
    .back-row a { color: var(--text-3); font-size: 13px; font-weight: 600; }
    .back-row a:hover { color: var(--pink-dark); }
</style>

<div class="comm-upload-wrap">
    <div class="back-row">
        <a href="community_gallery.php"><i class="fa-solid fa-arrow-left"></i> Back to Community Gallery</a>
    </div>

    <div class="comm-panel animate-in">
        <h2 class="comm-page-title"><i class="fa-solid fa-cloud-arrow-up"></i> Upload Community Art</h2>
        <p class="comm-page-sub">Share fan art with the community. All posts are moderated before going live.</p>

        <?php if ($message): ?>
            <div class="alert alert-<?= $message_type === 'error' ? 'error' : 'success' ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <div class="guidelines-box">
            <h3><i class="fa-solid fa-shield-heart"></i> Community Guidelines</h3>
            <?= communityGuidelinesHtml() ?>
        </div>

        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label>Content rating</label>
                <div class="rating-row">
                    <label class="rating-option">
                        <input type="radio" name="rating" value="sfw" checked> SFW — Safe for all ages
                    </label>
                    <label class="rating-option nsfw-opt">
                        <input type="radio" name="rating" value="nsfw"> NSFW — Mature themes
                    </label>
                </div>
            </div>

            <div class="form-group">
                <label>Title (optional)</label>
                <input type="text" name="title" class="form-input" placeholder="You can add a title later" maxlength="120"
                       value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label>Image</label>
                <input type="file" name="image_file" accept="image/*" required style="font-size:13px;color:var(--text-2);">
            </div>

            <label class="agree-row">
                <input type="checkbox" name="agree_guidelines" value="1" required>
                <span>I confirm this artwork follows the Community Guidelines and I have the right to share it.</span>
            </label>

            <button type="submit" name="submit_community" class="btn-pink" style="width:100%;justify-content:center;">
                <i class="fa-solid fa-paper-plane"></i> Submit for review
            </button>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
