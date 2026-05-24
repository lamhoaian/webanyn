<?php
require_once '../config/database.php';

// 1. KIỂM TRA QUYỀN ADMIN (Phải làm TRƯỚC khi in bất cứ thứ gì)
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != 1) {
    die("<h3 style='color: #ef4444; text-align: center; margin-top: 50px;'>Access Denied! Admin privileges required.</h3>");
}

$message = '';

// 2. XỬ LÝ LOGIC (POST/GET)
// A. TẠO NHÓM MỚI
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_group'])) {
    $group_name = trim($_POST['group_name']);
    if (!empty($group_name)) {
        try {
            $pdo->prepare("INSERT INTO bot_groups (name) VALUES (?)")->execute([$group_name]);
            header("Location: admin_groups.php?msg=Group created successfully!");
            exit;
        } catch (Exception $e) { 
            header("Location: admin_groups.php?err=Group name already exists!");
            exit;
        }
    }
}

// B. XÓA NHÓM (Nếu m cần tính năng này sau này)
if (isset($_GET['delete_group_id'])) {
    $pdo->prepare("DELETE FROM bot_groups WHERE id=?")->execute([$_GET['delete_group_id']]);
    header("Location: admin_groups.php?msg=Group deleted!");
    exit;
}

// C. LƯU THÀNH VIÊN VÀO NHÓM
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_members'])) {
    $group_id = $_POST['group_id'];
    $selected_bots = $_POST['bots'] ?? []; 
    $pdo->prepare("DELETE FROM bot_group_members WHERE group_id = ?")->execute([$group_id]);
    $stmt = $pdo->prepare("INSERT INTO bot_group_members (group_id, bot_id) VALUES (?, ?)");
    foreach ($selected_bots as $bot_id) { 
        $stmt->execute([$group_id, $bot_id]); 
    }
    header("Location: admin_groups.php?edit_group_id=" . $group_id . "&msg=Group members updated successfully!");
    exit;
}

// 3. LẤY DỮ LIỆU HIỂN THỊ
if (isset($_GET['msg'])) $message = $_GET['msg'];
if (isset($_GET['err'])) $message = $_GET['err']; // Xử lý thông báo lỗi (VD: trùng tên)

$groups = $pdo->query("SELECT * FROM bot_groups ORDER BY name ASC")->fetchAll();
$all_bots = $pdo->query("SELECT id, name, image_url FROM bots ORDER BY created_at DESC")->fetchAll();
$edit_group_id = $_GET['edit_group_id'] ?? ($groups[0]['id'] ?? null);
$current_members = [];

if ($edit_group_id) {
    $stmt = $pdo->prepare("SELECT bot_id FROM bot_group_members WHERE group_id = ?");
    $stmt->execute([$edit_group_id]);
    $current_members = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// 4. MỌI LOGIC XONG, GIỜ MỚI GỌI HEADER ĐỂ IN GIAO DIỆN
require_once '../includes/header.php';
?>

<style>
    .admin-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 2px solid var(--pink-soft); }
    .admin-title { margin: 0; color: var(--pink-dark); font-size: 28px; }
    
    .btn-action { background: var(--pink); color: white; padding: 10px 20px; border-radius: 12px; text-decoration: none; font-weight: bold; font-size: 14px; transition: 0.3s; box-shadow: 0 4px 10px rgba(255, 183, 197, 0.4); border: none; cursor: pointer; display: inline-block; }
    .btn-action:hover { background: var(--pink-dark); transform: translateY(-2px); }
    
    .admin-panel { background: var(--surface); padding: 25px; border-radius: 15px; box-shadow: var(--shadow-md); border: 1px solid #f9f9f9; margin-bottom: 30px; }
    .panel-title { margin: 0 0 20px 0; font-size: 18px; color: var(--text); border-left: 4px solid var(--pink); padding-left: 10px; }

    .form-group input[type="text"] { width: 100%; padding: 10px 15px; border: 1.5px solid var(--border); border-radius: 8px; box-sizing: border-box; font-family: inherit; font-size: 14px; outline: none; transition: 0.3s; background: #fdfdfd; }
    .form-group input:focus { border-color: var(--pink); box-shadow: 0 0 0 3px rgba(255, 183, 197, 0.2); }
    
    .group-list { list-style: none; padding: 0; margin: 0; }
    .group-item { display: flex; justify-content: space-between; align-items: center; padding: 12px 15px; background: #fdfdfd; border: 1px solid var(--border); border-radius: 10px; margin-bottom: 10px; transition: 0.2s; }
    .group-item:hover { border-color: var(--pink); background: var(--pink-soft); }
    .group-item.active { background: #fff5f7; border-color: var(--pink-dark); border-left: 4px solid var(--pink-dark); }
    .group-name { font-weight: 600; color: var(--text-2); }
    .group-item.active .group-name { color: var(--pink-dark); }
    
    .bot-checkbox-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px; }
    .bot-label { display: flex; align-items: center; gap: 12px; background: #fdfdfd; padding: 12px; border-radius: 10px; border: 1.5px solid var(--border); cursor: pointer; transition: 0.2s; }
    .bot-label:hover { border-color: var(--pink); background: var(--pink-soft); }
    .bot-label input[type="checkbox"] { width: 18px; height: 18px; accent-color: var(--pink); cursor: pointer; }
    .bot-label img { width: 30px; height: 30px; border-radius: 6px; object-fit: cover; }
    .bot-label span { font-size: 14px; font-weight: 600; color: #444; }

    .alert { padding: 10px 20px; border-radius: 10px; font-weight: bold; font-size: 13px; margin-bottom: 20px; text-align: center; }
    .alert-success { background: #d1fae5; color: #059669; border: 1px solid #10b981; }
    .alert-error { background: #fee2e2; color: #dc2626; border: 1px solid #ef4444; }
</style>

<div class="admin-header">
    <div style="display: flex; align-items: center; gap: 20px;">
        <h2 class="admin-title">👑 Group Management</h2>
        <a href="admin_bots.php" class="btn-action" style="background: #8b5cf6; box-shadow: 0 4px 10px rgba(139, 92, 246, 0.3);">⬅️ Back to Bots</a>
    </div>
</div>

<?php if($message): ?> 
    <?php $is_error = strpos($message, 'exists') !== false || strpos($message, 'Failed') !== false; ?>
    <div class="alert <?= $is_error ? 'alert-error' : 'alert-success' ?>">
        <?= htmlspecialchars($message) ?>
    </div> 
<?php endif; ?>

<div style="display: grid; grid-template-columns: 1fr 2fr; gap: 30px;">
    
    <!-- CỘT TRÁI: TẠO VÀ CHỌN NHÓM -->
    <div class="admin-panel" style="height: fit-content;">
        <h3 class="panel-title"><i class="fa-solid fa-folder-plus"></i> 1. Create New Group</h3>
        <form method="POST" action="admin_groups.php" style="display: flex; gap: 10px; margin-bottom: 30px;" class="form-group">
            <input type="text" name="group_name" required placeholder="e.g., The Honkai Universe">
            <button type="submit" name="create_group" class="btn-action" style="padding: 10px 15px;"><i class="fa-solid fa-plus"></i></button>
        </form>
        
        <h3 class="panel-title"><i class="fa-solid fa-list-ul"></i> 2. Select a Group</h3>
        <?php if (count($groups) > 0): ?>
            <ul class="group-list">
                <?php foreach($groups as $g): ?>
                    <?php $isActive = ($g['id'] == $edit_group_id); ?>
                    <li class="group-item <?= $isActive ? 'active' : '' ?>">
                        <div class="group-name">📁 <?= htmlspecialchars($g['name']) ?></div>
                        <a href="admin_groups.php?edit_group_id=<?= $g['id'] ?>" style="color: <?= $isActive ? 'var(--pink-dark)' : 'var(--pink)' ?>; font-weight: bold; text-decoration: none; font-size: 13px; padding: 4px 8px;">SELECT</a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p style="color: var(--text-3); font-size: 14px; text-align: center; padding: 20px 0; background: #f9f9f9; border-radius: 8px;">No groups created yet.</p>
        <?php endif; ?>
    </div>

    <!-- CỘT PHẢI: GÁN BOT VÀO NHÓM -->
    <div class="admin-panel">
        <?php if($edit_group_id): ?>
            <?php 
                $selected_group_name = '';
                foreach ($groups as $g) {
                    if ($g['id'] == $edit_group_id) {
                        $selected_group_name = $g['name'];
                        break;
                    }
                }
            ?>
            <h3 class="panel-title" style="color: #f39c12; border-left-color: #f39c12;">
                <i class="fa-solid fa-users-viewfinder"></i> 3. Assign Bots to: <?= htmlspecialchars($selected_group_name) ?>
            </h3>
            
            <form method="POST" action="admin_groups.php?edit_group_id=<?= $edit_group_id ?>">
                <input type="hidden" name="group_id" value="<?= $edit_group_id ?>">
                
                <div class="bot-checkbox-grid" style="margin-bottom: 25px;">
                    <?php foreach($all_bots as $b): ?>
                        <label class="bot-label">
                            <input type="checkbox" name="bots[]" value="<?= $b['id'] ?>" <?= in_array($b['id'], $current_members) ? 'checked' : '' ?>>
                            <?php if ($b['image_url']): ?>
                                <img src="<?= htmlspecialchars($b['image_url']) ?>" alt="Bot">
                            <?php else: ?>
                                <div style="width: 30px; height: 30px; border-radius: 6px; background: #eee;"></div>
                            <?php endif; ?>
                            <span><?= htmlspecialchars($b['name']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                
                <button type="submit" name="save_members" class="btn-action" style="width: 100%; font-size: 16px; padding: 12px; background: #10b981; box-shadow: 0 4px 10px rgba(16, 185, 129, 0.3);">Save Group Members</button>
            </form>
        <?php else: ?>
            <div style="text-align: center; padding: 50px; color: #aaa;">
                <i class="fa-solid fa-folder-open" style="font-size: 50px; margin-bottom: 15px; color: #ddd;"></i>
                <p style="margin: 0; font-size: 15px;">Please select a group from the left panel to assign bots.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>