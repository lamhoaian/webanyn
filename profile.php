<?php
require_once '../config/database.php';

// Ép buộc phải đăng nhập mới được vào trang này
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Lấy thông tin user hiện tại
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// KIỂM TRA ĐIỀU KIỆN PREMIUM (Nạp >= 10$)
$is_premium = ($user['total_spent'] >= 10);
$spent_amount = floatval($user['total_spent']);
$progress_percent = min(100, ($spent_amount / 10) * 100);

// Danh sách 10 avatar mặc định m chuẩn bị sẵn (Ở đây t dùng tạm link DiceBear)
// Danh sách 10 avatar mặc định do admin (m) chuẩn bị sẵn
$premade_avatars = [
    '/anyn/uploads/avatars/avt_1.jpg',
    '/anyn/uploads/avatars/avt_2.jpg',
    '/anyn/uploads/avatars/avt_3.jpg',
    '/anyn/uploads/avatars/avt_4.jpg',
    '/anyn/uploads/avatars/avt_5.jpg',
    '/anyn/uploads/avatars/avt_6.jpg',
    '/anyn/uploads/avatars/avt_7.jpg',
    '/anyn/uploads/avatars/avt_8.jpg',
    '/anyn/uploads/avatars/avt_9.jpg',
    '/anyn/uploads/avatars/avt_10.jpg'
];

// XỬ LÝ LƯU FORM
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $new_username = trim($_POST['username']);
    $avatar_choice = $_POST['avatar_choice'] ?? '';
    
    // 1. Kiểm tra trùng tên (nếu user có đổi tên)
    if ($new_username !== $user['username']) {
        $check = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $check->execute([$new_username, $user_id]);
        if ($check->rowCount() > 0) {
            $error = "This username is already taken. Please choose another.";
        }
    }

    $new_avatar_url = $user['avatar_url']; // Giữ nguyên mặc định nếu không đổi

    if (empty($error)) {
        // 2. Xử lý Upload Avatar tùy chỉnh (CHỈ DÀNH CHO PREMIUM)
        if (isset($_FILES['custom_avatar']) && $_FILES['custom_avatar']['error'] == 0) {
            if ($is_premium) {
                $allowed = ['jpg', 'jpeg', 'png', 'gif'];
                $ext = strtolower(pathinfo($_FILES['custom_avatar']['name'], PATHINFO_EXTENSION));
                $max_size = 2 * 1024 * 1024; // 2MB
                
                if ($_FILES['custom_avatar']['size'] > $max_size) {
                    $error = "File too large! Maximum size is 2MB.";
                } elseif (in_array($ext, $allowed)) {
                    $filename = 'user_' . $user_id . '_' . time() . '.' . $ext;
                    $dest = '../uploads/avatars/' . $filename; 
                    
                    if (move_uploaded_file($_FILES['custom_avatar']['tmp_name'], $dest)) {
                        $new_avatar_url = '/anyn/uploads/avatars/' . $filename;
                    } else {
                        $error = "Failed to save uploaded image.";
                    }
                } else {
                    $error = "Invalid file type. Only JPG, PNG, GIF are allowed.";
                }
            } else {
                $error = "Nice try! You need to spend $10 to upload custom avatars.";
            }
        } 
        // 3. Xử lý nếu chọn 1 trong 10 avatar có sẵn
        elseif (!empty($avatar_choice) && in_array($avatar_choice, $premade_avatars)) {
            $new_avatar_url = $avatar_choice;
        }

        // Cập nhật Database nếu không có lỗi
        if (empty($error)) {
            $pdo->prepare("UPDATE users SET username = ?, avatar_url = ? WHERE id = ?")->execute([$new_username, $new_avatar_url, $user_id]);
            $_SESSION['username'] = $new_username; // Cập nhật lại session
            $user['username'] = $new_username;
            $user['avatar_url'] = $new_avatar_url;
            $success = "Profile updated successfully!";
        }
    }
}

// Gọi header sau khi xử lý xong logic
require_once '../includes/header.php';

// Xác định avatar hiển thị hiện tại
$display_avatar = !empty($user['avatar_url']) ? htmlspecialchars($user['avatar_url']) : 'https://i.postimg.cc/mZh4H8hC/default-avatar.png';
$avatar_class = $is_premium ? 'profile-avatar premium-glow' : 'profile-avatar';
if (!empty($user['active_avatar_frame'])) {
    $avatar_class .= ' gem-avt-' . preg_replace('/[^a-z0-9_]/', '', $user['active_avatar_frame']);
}
?>

<style>
    .profile-container { display: flex; gap: 40px; margin-bottom: 50px; }
    
    /* Cột Trái: Thông tin */
    .profile-card { background: var(--surface); padding: 40px 30px; border-radius: 20px; box-shadow: var(--shadow-md); text-align: center; flex: 0 0 320px; height: fit-content; }
    .profile-avatar { width: 150px; height: 150px; border-radius: 50%; object-fit: cover; margin: 0 auto 20px auto; border: 4px solid #fff; box-shadow: 0 5px 15px rgba(0,0,0,0.1); background: var(--pink-soft); }
    .premium-glow { border-color: #ff8fa3; box-shadow: 0 0 20px rgba(255, 143, 163, 0.6); }
    .stat-box { background: var(--bg); padding: 15px; border-radius: 12px; margin-top: 20px; text-align: left; }
    
    /* Cột Phải: Form chỉnh sửa */
    .edit-card { background: var(--surface); padding: 40px; border-radius: 20px; box-shadow: var(--shadow-md); flex-grow: 1; }
    
    /* Progress Bar */
    .progress-wrapper { background: #eee; border-radius: 20px; height: 10px; width: 100%; margin: 10px 0; overflow: hidden; }
    .progress-bar { background: linear-gradient(90deg, #ffb7c5, #ff8fa3); height: 100%; border-radius: 20px; transition: 0.5s; }
    
    /* Form Inputs */
    .form-group { margin-bottom: 25px; }
    .form-group label { display: block; font-weight: 600; font-size: 14px; margin-bottom: 10px; color: var(--text-2); }
    .form-group input[type="text"] { width: 100%; padding: 12px 20px; border: 1.5px solid var(--border); border-radius: 12px; font-family: inherit; font-size: 15px; outline: none; transition: 0.3s; box-sizing: border-box; }
    .form-group input[type="text"]:focus { border-color: var(--pink); box-shadow: 0 0 0 3px rgba(255, 183, 197, 0.2); }
    
    /* Grid 10 Avatar */
    .avatar-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 15px; margin-top: 10px; }
    .avatar-option { cursor: pointer; position: relative; }
    .avatar-option input[type="radio"] { display: none; }
    .avatar-img { width: 100%; aspect-ratio: 1; border-radius: 50%; object-fit: cover; border: 3px solid transparent; transition: 0.2s; background: var(--bg); }
    .avatar-option input[type="radio"]:checked + .avatar-img { border-color: var(--pink); transform: scale(1.05); box-shadow: 0 5px 15px rgba(255, 183, 197, 0.4); }
    
    /* Khung Upload Custom */
    .upload-box { border: 2px dashed #ccc; padding: 20px; border-radius: 12px; text-align: center; margin-top: 15px; transition: 0.3s; }
    .upload-box.locked { background: #f9f9f9; cursor: not-allowed; opacity: 0.7; }
    .upload-box.unlocked:hover { border-color: var(--pink); background: var(--pink-soft); }
    .file-input { display: none; }
    
    /* Alert */
    .alert { padding: 15px; border-radius: 10px; margin-bottom: 20px; font-size: 14px; font-weight: 500; }
    .alert-error { background: #fef2f2; color: #ef4444; border: 1px solid #fca5a5; }
    .alert-success { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }
</style>

<div class="profile-container">
    
    <!-- CỘT TRÁI: THÔNG TIN TỔNG QUAN -->
    <div class="profile-card">
        <img src="<?= $display_avatar ?>" class="<?= $avatar_class ?>" alt="User Avatar">
        <h2 style="margin: 0 0 5px 0; color: var(--text);"><?= htmlspecialchars($user['username']) ?></h2>
        <p style="margin: 0; color: var(--text-3); font-size: 14px;"><?= htmlspecialchars($user['email']) ?></p>
        
        <?php if($is_premium): ?>
            <div style="display: inline-block; background: #1a1518; color: #ffb7c5; padding: 5px 15px; border-radius: 20px; font-size: 12px; font-weight: bold; margin-top: 15px; border: 1px solid #ff8fa3; box-shadow: 0 0 10px rgba(255,143,163,0.3);">
                🌸 PREMIUM MEMBER
            </div>
        <?php else: ?>
            <div style="display: inline-block; background: #eee; color: #666; padding: 5px 15px; border-radius: 20px; font-size: 12px; font-weight: bold; margin-top: 15px;">
                MEMBER
            </div>
        <?php endif; ?>

        <div class="stat-box">
            <div style="display: flex; justify-content: space-between; font-size: 13px; font-weight: 600; color: var(--text-2);">
                <span>Total Spent</span>
                <span style="color: var(--pink-dark);">$<?= number_format($spent_amount, 2) ?></span>
            </div>
            
            <?php if(!$is_premium): ?>
                <div class="progress-wrapper">
                    <div class="progress-bar" style="width: <?= $progress_percent ?>%;"></div>
                </div>
                <div style="font-size: 11px; color: var(--text-3); text-align: center; margin-top: 5px;">
                    Spend $<?= number_format(10 - $spent_amount, 2) ?> more to unlock Custom Avatars!
                </div>
            <?php else: ?>
                <div style="font-size: 12px; color: #16a34a; font-weight: 600; margin-top: 10px; text-align: center;">
                    ✨ Custom Avatars Unlocked! ✨
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- CỘT PHẢI: FORM CHỈNH SỬA -->
    <div class="edit-card">
        <h2 style="margin-top: 0; margin-bottom: 25px; border-bottom: 2px solid var(--pink-soft); padding-bottom: 15px;">Edit Profile</h2>
        
        <?php if ($error): ?> <div class="alert alert-error"><?= $error ?></div> <?php endif; ?>
        <?php if ($success): ?> <div class="alert alert-success"><?= $success ?></div> <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            
            <div class="form-group">
                <label>Display Name</label>
                <input type="text" name="username" value="<?= htmlspecialchars($user['username']) ?>" required>
            </div>

            <div class="form-group">
                <label>Choose an Avatar</label>
                <div style="font-size: 12px; color: var(--text-3); margin-bottom: 10px;">Select from our free collection:</div>
                <div class="avatar-grid">
                    <?php foreach($premade_avatars as $avt): ?>
                        <label class="avatar-option">
                            <input type="radio" name="avatar_choice" value="<?= $avt ?>" <?= ($user['avatar_url'] == $avt) ? 'checked' : '' ?>>
                            <img src="<?= $avt ?>" class="avatar-img" alt="Option">
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-group">
                <label>Or Upload Custom Avatar 💎</label>
                
                <?php if($is_premium): ?>
                    <!-- NẾU LÀ PREMIUM -> MỞ KHÓA TẢI ẢNH -->
                    <label class="upload-box unlocked" style="display: block; cursor: pointer;">
                        <input type="file" name="custom_avatar" accept="image/jpeg, image/png, image/gif" class="file-input" id="customFile">
                        <i class="fa-solid fa-cloud-arrow-up" style="font-size: 24px; color: var(--pink); margin-bottom: 10px;"></i>
                        <div style="font-size: 14px; font-weight: 600; color: var(--text-2);" id="fileName">Click to browse your files</div>
                        <div style="font-size: 12px; color: #999; margin-top: 5px;">Max 2MB. JPG, PNG, GIF.</div>
                    </label>
                <?php else: ?>
                    <!-- NẾU CHƯA ĐỦ 10$ -> KHÓA LẠI -->
                    <div class="upload-box locked">
                        <i class="fa-solid fa-lock" style="font-size: 24px; color: #ccc; margin-bottom: 10px;"></i>
                        <div style="font-size: 14px; font-weight: 600; color: var(--text-3);">Custom Upload Locked</div>
                        <div style="font-size: 12px; color: #aaa; margin-top: 5px;">Unlock this feature by reaching $10 total spent.</div>
                    </div>
                <?php endif; ?>
            </div>

            <button type="submit" class="btn-pink" style="padding: 12px 30px; font-size: 16px;">Save Changes</button>
        </form>
    </div>
</div>

<script>
// Script nhỏ để hiển thị tên file khi user Premium chọn ảnh tải lên
document.addEventListener('DOMContentLoaded', function() {
    const fileInput = document.getElementById('customFile');
    const fileNameDisplay = document.getElementById('fileName');
    
    if(fileInput) {
        fileInput.addEventListener('change', function() {
            if(this.files && this.files.length > 0) {
                fileNameDisplay.textContent = 'Selected: ' + this.files[0].name;
                fileNameDisplay.style.color = '#f28b9d';
            }
        });
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>