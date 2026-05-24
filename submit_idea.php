<?php
require_once '../config/database.php';
require_once '../includes/gems_lib.php';
ensureGemsSchema($pdo);
require_once '../includes/header.php';

// Require login
if (!isset($_SESSION['user_id'])) {
    echo "<div style='text-align:center; padding: 50px; background: var(--surface); border-radius: 15px; box-shadow: var(--shadow-md); margin-top: 30px;'>";
    echo "<h3 style='color: var(--text-2); margin-bottom: 15px;'>Access Denied</h3>";
    echo "<p style='color: var(--text-3);'>You must be <a href='login.php' style='color: var(--pink); font-weight: bold;'>logged in</a> to submit an idea!</p>";
    echo "</div>";
    require_once '../includes/footer.php';
    exit;
}

$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = $_POST['title'];
    $appearance = $_POST['appearance'];
    $context = $_POST['context'];
    $user_id = $_SESSION['user_id'];
    
    // Default to URL if provided
    $final_image_url = $_POST['image_url'];

    // Handle File Upload if user selected a file
    if (isset($_FILES['idea_image_file']) && $_FILES['idea_image_file']['error'] == 0) {
        $target_dir = "../uploads/";
        
        // Create uploads directory if it doesn't exist
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $file_extension = strtolower(pathinfo($_FILES["idea_image_file"]["name"], PATHINFO_EXTENSION));
        $new_file_name = time() . "_idea_" . uniqid() . "." . $file_extension;
        $target_file = $target_dir . $new_file_name;

        $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($file_extension, $allowed_types)) {
            if (move_uploaded_file($_FILES["idea_image_file"]["tmp_name"], $target_file)) {
                // IMPORTANT: Change '/anyn/' to your actual folder name if different
                $final_image_url = "/anyn/uploads/" . $new_file_name;
            } else {
                $error_msg = "Failed to upload image.";
            }
        } else {
            $error_msg = "Invalid image format. Allowed types: JPG, PNG, GIF, WEBP.";
        }
    }

    if ($error_msg == '') {
        $stmt = $pdo->prepare("INSERT INTO ideas (user_id, title, appearance, context, image_url) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $title, $appearance, $context, $final_image_url]);
        tryAwardMission($pdo, (int)$user_id, 'submit_idea');
        $success_msg = "Idea submitted successfully! Check the Idea Board to see it.";
    }
}
?>

<style>
    .submit-container { max-width: 700px; margin: 0 auto; background: var(--surface); padding: 40px; border-radius: 20px; box-shadow: var(--shadow-md); }
    .page-title { text-align: center; font-size: 28px; color: var(--pink-dark); margin: 0 0 10px 0; }
    .page-sub { text-align: center; color: var(--text-3); font-size: 14px; margin-bottom: 30px; }

    .form-group { margin-bottom: 25px; }
    .form-group label { display: block; font-weight: 600; font-size: 14px; margin-bottom: 10px; color: var(--text-2); }
    .form-group input[type="text"], .form-group textarea { width: 100%; padding: 12px 15px; border: 1.5px solid var(--border); border-radius: 12px; font-family: inherit; font-size: 14px; outline: none; transition: 0.3s; box-sizing: border-box; background: #fdfdfd; }
    .form-group input[type="text"]:focus, .form-group textarea:focus { border-color: var(--pink); box-shadow: 0 0 0 3px rgba(255, 183, 197, 0.2); }
    
    .upload-section { background: var(--pink-soft); padding: 20px; border-radius: 12px; border: 1px dashed var(--pink); margin-bottom: 25px; }
    .upload-title { color: var(--pink-dark); font-weight: bold; margin-bottom: 15px; font-size: 14px; display: flex; align-items: center; gap: 8px; }
    
    .btn-submit { width: 100%; background: var(--pink); color: white; border: none; padding: 15px; border-radius: 12px; font-size: 16px; font-weight: bold; cursor: pointer; transition: 0.3s; box-shadow: 0 5px 15px rgba(255, 183, 197, 0.4); }
    .btn-submit:hover { background: var(--pink-dark); transform: translateY(-2px); }

    .alert { padding: 15px; border-radius: 10px; margin-bottom: 25px; font-size: 14px; font-weight: 500; text-align: center; }
    .alert-error { background: #fef2f2; color: #ef4444; border: 1px solid #fca5a5; }
    .alert-success { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }
</style>

<div class="submit-container">
    <h2 class="page-title">Submit a Bot Idea 🌸</h2>
    <p class="page-sub">Describe the character you want to see. If the community loves it, we might bring it to life!</p>

    <?php if($success_msg): ?> <div class="alert alert-success"><?= $success_msg ?></div> <?php endif; ?>
    <?php if($error_msg): ?> <div class="alert alert-error"><?= $error_msg ?></div> <?php endif; ?>

    <form method="POST" action="" enctype="multipart/form-data">
        <div class="form-group">
            <label>Character Name / Title</label>
            <input type="text" name="title" required placeholder="e.g., Yukishiro Rin">
        </div>
        
        <div class="form-group">
            <label>Appearance</label>
            <textarea name="appearance" rows="3" required placeholder="e.g., Long black hair, wearing an oversized t-shirt..."></textarea>
        </div>
        
        <div class="form-group">
            <label>Context / Scenario</label>
            <textarea name="context" rows="4" required placeholder="e.g., A reserved kuudere who hides her true emotions during the initial meeting..."></textarea>
        </div>
        
        <div class="upload-section">
            <div class="upload-title"><i class="fa-regular fa-image"></i> Reference Image (Optional - Choose ONE method)</div>
            
            <div style="margin-bottom: 15px;">
                <div style="font-size: 13px; color: #666; margin-bottom: 5px;">Method 1: Upload from device</div>
                <input type="file" name="idea_image_file" accept="image/*" style="width: 100%; font-size: 13px; color: var(--text-2);">
            </div>
            
            <div style="text-align: center; color: #aaa; font-size: 12px; margin-bottom: 15px;">— OR —</div>

            <div>
                <div style="font-size: 13px; color: #666; margin-bottom: 5px;">Method 2: Paste an Image URL</div>
                <input type="text" name="image_url" placeholder="https://..." style="width: 100%; padding: 10px; border: 1.5px solid var(--border); border-radius: 8px; font-size: 13px; outline: none; box-sizing: border-box;">
            </div>
        </div>
        
        <button type="submit" class="btn-submit">Submit Idea</button>
    </form>
</div>

<?php require_once '../includes/footer.php'; ?>