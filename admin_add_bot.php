<?php
require_once '../config/database.php';

// Chỉ cho Admin (ID=1) vào, không là user nó phá nát web
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != 1) {
    header("Location: ../index.php");
    exit();
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];
    $desc = $_POST['description'];
    $juicy = $_POST['juicy_url'];
    $crushon = $_POST['crushon_url'];
    $is_pinned = isset($_POST['is_pinned']) ? 1 : 0;
    
    $final_image_url = '';

    // XỬ LÝ UPLOAD ẢNH TRỰC TIẾP TỪ MÁY
    if (isset($_FILES['bot_image']) && $_FILES['bot_image']['error'] == 0) {
        $target_dir = "../uploads/";
        $file_name = time() . "_" . basename($_FILES["bot_image"]["name"]);
        $target_file = $target_dir . $file_name;

        if (move_uploaded_file($_FILES["bot_image"]["tmp_name"], $target_file)) {
            // Lưu đường dẫn chuẩn để hiển thị (bỏ dấu ..)
            $final_image_url = "/uploads/" . $file_name;
        } else {
            $message = "Lỗi upload ảnh rồi m ơi!";
        }
    }

    if ($final_image_url) {
        try {
            $sql = "INSERT INTO bots (name, description, image_url, juicy_url, crushon_url, is_pinned) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$name, $desc, $final_image_url, $juicy, $crushon, $is_pinned]);
            $message = "Thêm Bot thành công! Quá nhanh quá nguy hiểm.";
        } catch (PDOException $e) {
            $message = "Lỗi Database: " . $e->getMessage();
        }
    }
}

require_once '../includes/header.php';
?>

<div style="max-width: 800px; margin: 0 auto; background: var(--surface); padding: 40px; border-radius: 20px; box-shadow: var(--shadow-md);">
    <h2 style="color: var(--pink-dark); margin-top: 0;"><i class="fa-solid fa-plus"></i> Thêm Bot Mới</h2>
    
    <?php if($message): ?>
        <div style="padding: 15px; background: #f0fdf4; color: #16a34a; border-radius: 10px; margin-bottom: 20px; border: 1px solid #bbf7d0;">
            <?= $message ?>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <div style="margin-bottom: 20px;">
            <label style="display:block; font-weight:bold; margin-bottom:8px;">Tên Bot:</label>
            <input type="text" name="name" required style="width:100%; padding:12px; border:1px solid #ddd; border-radius:10px;">
        </div>

        <div style="margin-bottom: 20px;">
            <label style="display:block; font-weight:bold; margin-bottom:8px;">Mô tả:</label>
            <textarea name="description" required style="width:100%; height:100px; padding:12px; border:1px solid #ddd; border-radius:10px;"></textarea>
        </div>

        <!-- KHÚC NÀY LÀ ĐỂ ÚP ẢNH TỪ MÁY NÈ -->
        <div style="margin-bottom: 20px;">
            <label style="display:block; font-weight:bold; margin-bottom:8px;">Ảnh đại diện (Chọn từ máy m):</label>
            <input type="file" name="bot_image" accept="image/*" required>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
            <div>
                <label style="display:block; font-weight:bold; margin-bottom:8px;">Link JuicyChat:</label>
                <input type="text" name="juicy_url" placeholder="https://..." style="width:100%; padding:12px; border:1px solid #ddd; border-radius:10px;">
            </div>
            <div>
                <label style="display:block; font-weight:bold; margin-bottom:8px;">Link CrushOn.AI:</label>
                <input type="text" name="crushon_url" placeholder="https://..." style="width:100%; padding:12px; border:1px solid #ddd; border-radius:10px;">
            </div>
        </div>

        <div style="margin-bottom: 30px;">
            <label><input type="checkbox" name="is_pinned"> Ghim lên đầu trang chủ</label>
        </div>

        <button type="submit" class="btn-pink" style="width: 100%; padding: 15px; font-size: 16px;">XÁC NHẬN THÊM BOT</button>
    </form>
</div>

<?php require_once '../includes/footer.php'; ?>