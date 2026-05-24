<?php
require_once '../config/database.php';
require_once '../includes/ideas_lib.php';

ensureIdeasSchema($pdo);

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$idea_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($idea_id <= 0) {
    header('Location: ideas.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM ideas WHERE id = ?");
$stmt->execute([$idea_id]);
$idea = $stmt->fetch();

if (!$idea || (int)$idea['user_id'] !== (int)$_SESSION['user_id']) {
    header('Location: ideas.php?error=forbidden');
    exit;
}
if (($idea['work_status'] ?? 'open') !== 'open') {
    header('Location: ideas.php?error=forbidden');
    exit;
}

$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title      = trim($_POST['title'] ?? '');
    $appearance = trim($_POST['appearance'] ?? '');
    $context    = trim($_POST['context'] ?? '');

    if ($title === '' || $appearance === '' || $context === '') {
        $error_msg = 'Please fill in all required fields.';
    } else {
        $final_image_url = $idea['image_url'];

        if (!empty($_POST['remove_image'])) {
            $final_image_url = null;
        } elseif (isset($_FILES['idea_image_file']) && $_FILES['idea_image_file']['error'] === UPLOAD_ERR_OK) {
            $target_dir = '../uploads/';
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            $file_extension = strtolower(pathinfo($_FILES['idea_image_file']['name'], PATHINFO_EXTENSION));
            $allowed_types  = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (in_array($file_extension, $allowed_types, true)) {
                $new_file_name = time() . '_idea_' . uniqid() . '.' . $file_extension;
                $target_file   = $target_dir . $new_file_name;
                if (move_uploaded_file($_FILES['idea_image_file']['tmp_name'], $target_file)) {
                    $final_image_url = '/anyn/uploads/' . $new_file_name;
                } else {
                    $error_msg = 'Failed to upload image.';
                }
            } else {
                $error_msg = 'Invalid image format. Allowed types: JPG, PNG, GIF, WEBP.';
            }
        } elseif (!empty(trim($_POST['image_url'] ?? ''))) {
            $final_image_url = trim($_POST['image_url']);
        }

        if ($error_msg === '') {
            $pdo->prepare(
                'UPDATE ideas SET title = ?, appearance = ?, context = ?, image_url = ? WHERE id = ? AND user_id = ?'
            )->execute([$title, $appearance, $context, $final_image_url ?: null, $idea_id, $_SESSION['user_id']]);
            header('Location: ideas.php?msg=updated');
            exit;
        }

        $idea['title']      = $title;
        $idea['appearance'] = $appearance;
        $idea['context']    = $context;
    }
}

require_once '../includes/header.php';
?>

<style>
    .submit-container { max-width: 700px; margin: 0 auto; background: var(--surface); padding: 40px; border-radius: 20px; box-shadow: var(--shadow-md); }
    .page-title { text-align: center; font-size: 28px; color: var(--pink-dark); margin: 0 0 10px 0; }
    .page-sub { text-align: center; color: var(--text-3); font-size: 14px; margin-bottom: 30px; }
    .back-link { display: inline-flex; align-items: center; gap: 6px; color: var(--text-3); font-size: 13px; font-weight: 600; margin-bottom: 20px; transition: color .2s; }
    .back-link:hover { color: var(--pink-dark); }
    .form-group { margin-bottom: 25px; }
    .form-group label { display: block; font-weight: 600; font-size: 14px; margin-bottom: 10px; color: var(--text-2); }
    .form-group input[type="text"], .form-group textarea { width: 100%; padding: 12px 15px; border: 1.5px solid var(--border); border-radius: 12px; font-family: inherit; font-size: 14px; outline: none; transition: .3s; box-sizing: border-box; background: var(--surface-2); color: var(--text); }
    .form-group input[type="text"]:focus, .form-group textarea:focus { border-color: var(--pink); box-shadow: 0 0 0 3px rgba(255, 183, 197, 0.2); }
    .upload-section { background: var(--pink-soft); padding: 20px; border-radius: 12px; border: 1px dashed var(--pink); margin-bottom: 25px; }
    .upload-title { color: var(--pink-dark); font-weight: bold; margin-bottom: 15px; font-size: 14px; display: flex; align-items: center; gap: 8px; }
    .current-image { margin-bottom: 15px; }
    .current-image img { max-width: 200px; max-height: 200px; border-radius: 12px; border: 1px solid var(--border); object-fit: cover; }
    .remove-image-label { display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--text-2); margin-top: 10px; cursor: pointer; }
    .btn-submit { width: 100%; background: var(--pink); color: white; border: none; padding: 15px; border-radius: 12px; font-size: 16px; font-weight: bold; cursor: pointer; transition: .3s; box-shadow: 0 5px 15px rgba(255, 183, 197, 0.4); }
    .btn-submit:hover { background: var(--pink-dark); transform: translateY(-2px); }
    .alert { padding: 15px; border-radius: 10px; margin-bottom: 25px; font-size: 14px; font-weight: 500; text-align: center; }
    .alert-error { background: #fef2f2; color: #ef4444; border: 1px solid #fca5a5; }
</style>

<div class="submit-container">
    <a href="ideas.php" class="back-link"><i class="fa-solid fa-arrow-left"></i> Back to Idea Board</a>
    <h2 class="page-title">Edit Your Idea</h2>
    <p class="page-sub">Update your character proposal. Upvotes will be kept.</p>

    <?php if ($error_msg): ?><div class="alert alert-error"><?= htmlspecialchars($error_msg) ?></div><?php endif; ?>

    <form method="POST" action="" enctype="multipart/form-data">
        <div class="form-group">
            <label>Character Name / Title</label>
            <input type="text" name="title" required value="<?= htmlspecialchars($idea['title']) ?>">
        </div>

        <div class="form-group">
            <label>Appearance</label>
            <textarea name="appearance" rows="3" required><?= htmlspecialchars($idea['appearance']) ?></textarea>
        </div>

        <div class="form-group">
            <label>Context / Scenario</label>
            <textarea name="context" rows="4" required><?= htmlspecialchars($idea['context']) ?></textarea>
        </div>

        <div class="upload-section">
            <div class="upload-title"><i class="fa-regular fa-image"></i> Reference Image (Optional)</div>

            <?php if (!empty($idea['image_url'])): ?>
            <div class="current-image">
                <div style="font-size: 13px; color: var(--text-3); margin-bottom: 8px;">Current image:</div>
                <img src="<?= htmlspecialchars($idea['image_url']) ?>" alt="Current reference">
                <label class="remove-image-label">
                    <input type="checkbox" name="remove_image" value="1"> Remove current image
                </label>
            </div>
            <?php endif; ?>

            <div style="margin-bottom: 15px;">
                <div style="font-size: 13px; color: var(--text-3); margin-bottom: 5px;">Upload new image</div>
                <input type="file" name="idea_image_file" accept="image/*" style="width: 100%; font-size: 13px; color: var(--text-2);">
            </div>

            <div style="text-align: center; color: var(--text-3); font-size: 12px; margin-bottom: 15px;">— OR —</div>

            <div>
                <div style="font-size: 13px; color: var(--text-3); margin-bottom: 5px;">Paste an Image URL</div>
                <input type="text" name="image_url" placeholder="https://..." value="<?= htmlspecialchars($idea['image_url'] ?? '') ?>" style="width: 100%; padding: 10px; border: 1.5px solid var(--border); border-radius: 8px; font-size: 13px; outline: none; box-sizing: border-box; background: var(--surface); color: var(--text);">
            </div>
        </div>

        <button type="submit" class="btn-submit">Save Changes</button>
    </form>
</div>

<?php require_once '../includes/footer.php'; ?>
