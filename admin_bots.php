<?php
require_once '../config/database.php';

// 1. KIỂM TRÊN QUYỀN ADMIN 
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != 1) {
    die("<h3 style='color: #ef4444; text-align: center; margin-top: 50px;'>Access Denied! Admin privileges required.</h3>");
}

$message = '';
$action = $_GET['action'] ?? 'add';
$edit_bot = null;
$current_theme_ids = [];

// --- LOGIC CẬP NHẬT COMMISSION (Trạng thái & Lời nhắn) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_commission'])) {
    $c_id = $_POST['com_id'];
    $new_status = $_POST['status'];
    $admin_note = trim($_POST['admin_note']);
    $pdo->prepare("UPDATE commissions SET status = ?, admin_note = ? WHERE id = ?")->execute([$new_status, $admin_note, $c_id]);
    header("Location: admin_bots.php?msg=Commission updated!"); 
    exit;
}

// --- LOGIC QUẢN LÝ THỂ LOẠI (THEMES) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_theme'])) {
    $new_theme = trim($_POST['new_theme_name']);
    if (!empty($new_theme)) {
        try {
            $pdo->prepare("INSERT INTO themes (name) VALUES (?)")->execute([$new_theme]);
            header("Location: admin_bots.php?msg=Theme added successfully!");
            exit;
        } catch (PDOException $e) {
            $message = "Theme already exists!";
        }
    }
}
if (isset($_GET['delete_theme_id'])) {
    $pdo->prepare("DELETE FROM themes WHERE id=?")->execute([$_GET['delete_theme_id']]);
    header("Location: admin_bots.php"); 
    exit;
}

// --- LOGIC QUẢN LÝ BOT ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_bot'])) {
    $name = $_POST['name'];
    $description = $_POST['description'];
    $rp_platform_url = $_POST['rp_platform_url'];
    $selected_themes = $_POST['themes'] ?? []; 
    $is_pinned = isset($_POST['is_pinned']) ? 1 : 0;
    $bot_id = $_POST['bot_id'] ?? null;
    $final_image_url = $_POST['image_url'];

    if (isset($_FILES['bot_image_file']) && $_FILES['bot_image_file']['error'] == 0) {
        $target_dir = "../uploads/";
        if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);
        $file_extension = strtolower(pathinfo($_FILES["bot_image_file"]["name"], PATHINFO_EXTENSION));
        $new_file_name = time() . "_" . uniqid() . "." . $file_extension;
        $target_file = $target_dir . $new_file_name;
        if (move_uploaded_file($_FILES["bot_image_file"]["tmp_name"], $target_file)) {
            $final_image_url = "/anyn/uploads/" . $new_file_name;
        }
    }

    if ($bot_id) {
        $stmt = $pdo->prepare("UPDATE bots SET name=?, description=?, image_url=?, rp_platform_url=?, is_pinned=? WHERE id=?");
        $stmt->execute([$name, $description, $final_image_url, $rp_platform_url, $is_pinned, $bot_id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO bots (name, description, image_url, rp_platform_url, is_pinned) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $description, $final_image_url, $rp_platform_url, $is_pinned]);
        $bot_id = $pdo->lastInsertId(); 
    }

    $pdo->prepare("DELETE FROM bot_themes WHERE bot_id = ?")->execute([$bot_id]); 
    $insert_theme_stmt = $pdo->prepare("INSERT INTO bot_themes (bot_id, theme_id) VALUES (?, ?)");
    foreach ($selected_themes as $tid) {
        $insert_theme_stmt->execute([$bot_id, $tid]); 
    }
    header("Location: admin_bots.php?msg=Operation successful!");
    exit;
}

if (isset($_GET['delete_id'])) {
    $pdo->prepare("DELETE FROM bots WHERE id=?")->execute([$_GET['delete_id']]);
    header("Location: admin_bots.php?msg=Bot deleted!");
    exit;
}

// 2. LẤY DỮ LIỆU ĐỂ HIỂN THỊ
if (isset($_GET['msg'])) $message = $_GET['msg'];

if ($action == 'edit' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM bots WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $edit_bot = $stmt->fetch();
    $stmt_th = $pdo->prepare("SELECT theme_id FROM bot_themes WHERE bot_id = ?");
    $stmt_th->execute([$edit_bot['id']]);
    $current_theme_ids = $stmt_th->fetchAll(PDO::FETCH_COLUMN);
}

$db_themes = $pdo->query("SELECT * FROM themes ORDER BY name ASC")->fetchAll();
$bots = $pdo->query("SELECT b.*, GROUP_CONCAT(t.name SEPARATOR ', ') as theme_names FROM bots b LEFT JOIN bot_themes bt ON b.id = bt.bot_id LEFT JOIN themes t ON bt.theme_id = t.id GROUP BY b.id ORDER BY b.created_at DESC")->fetchAll();
$commissions = $pdo->query("SELECT c.*, u.username FROM commissions c JOIN users u ON c.user_id = u.id ORDER BY FIELD(c.status, 'Pending', 'In Progress', 'Completed'), c.created_at DESC")->fetchAll();

require_once '../includes/header.php';
?>

<style>
    .admin-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 2px solid var(--pink-soft); }
    .admin-title { margin: 0; color: var(--pink-dark); font-size: 28px; }
    .btn-action { background: var(--pink); color: white; padding: 10px 20px; border-radius: 12px; text-decoration: none; font-weight: bold; font-size: 14px; transition: 0.3s; box-shadow: 0 4px 10px rgba(255, 183, 197, 0.4); border: none; cursor: pointer; display: inline-block; }
    .btn-action:hover { background: var(--pink-dark); transform: translateY(-2px); }
    .admin-panel { background: var(--surface); padding: 25px; border-radius: 15px; box-shadow: var(--shadow-md); border: 1px solid #f9f9f9; margin-bottom: 30px; }
    .panel-title { margin: 0 0 20px 0; font-size: 18px; color: var(--text); border-left: 4px solid var(--pink); padding-left: 10px; }
    .form-group { margin-bottom: 15px; }
    .form-group label { display: block; font-size: 13px; font-weight: 600; color: #666; margin-bottom: 5px; }
    .form-group input[type="text"], .form-group textarea { width: 100%; padding: 10px 15px; border: 1.5px solid var(--border); border-radius: 8px; box-sizing: border-box; font-family: inherit; font-size: 14px; background: #fdfdfd; }
    .checkbox-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 10px; background: #fdfdfd; padding: 15px; border-radius: 8px; border: 1.5px solid var(--border); }
    .theme-label { background: var(--surface); padding: 6px 12px; border-radius: 20px; font-size: 12px; cursor: pointer; border: 1px solid var(--border); display: flex; align-items: center; gap: 8px; }
    .admin-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .admin-table th { background: var(--pink-soft); padding: 12px; text-align: left; color: var(--pink-dark); border-bottom: 2px solid var(--pink); }
    .admin-table td { padding: 12px; border-bottom: 1px solid #eee; }
    .com-card { background: #fafafa; border: 1px solid var(--border); padding: 15px; border-radius: 12px; margin-bottom: 15px; transition: 0.2s; }
    .com-card:hover { border-color: var(--pink); }
</style>

<div class="admin-header">
    <div style="display: flex; align-items: center; gap: 20px;">
        <h2 class="admin-title">👑 Admin Control Panel</h2>
        <a href="admin_groups.php" class="btn-action" style="background: #10b981;">📁 Manage Groups</a>
    </div>
    <?php if($message): ?> 
        <span style="background: #10b981; color: white; padding: 8px 15px; border-radius: 20px; font-size: 13px; font-weight: bold;"><?= $message ?></span> 
    <?php endif; ?>
</div>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px;">
    <div>
        <div class="admin-panel">
            <h3 class="panel-title"><i class="fa-solid fa-robot"></i> <?= $action == 'edit' ? 'Edit Bot' : 'New Bot' ?></h3>
            <form method="POST" action="admin_bots.php" enctype="multipart/form-data">
                <input type="hidden" name="bot_id" value="<?= $edit_bot['id'] ?? '' ?>">
                <div class="form-group"><label>Bot Name</label><input type="text" name="name" required value="<?= htmlspecialchars($edit_bot['name'] ?? '') ?>"></div>
                <div class="form-group"><label>Themes</label>
                    <div class="checkbox-grid">
                        <?php foreach($db_themes as $t): ?>
                            <label class="theme-label"><input type="checkbox" name="themes[]" value="<?= $t['id'] ?>" <?= in_array($t['id'], $current_theme_ids) ? 'checked' : '' ?>> <?= htmlspecialchars($t['name']) ?></label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="form-group"><label>Description</label><textarea name="description" rows="3" required><?= htmlspecialchars($edit_bot['description'] ?? '') ?></textarea></div>
                <div class="form-group" style="background: #fcfcfc; padding: 15px; border: 1px dashed var(--pink); border-radius: 8px;">
                    <label>Avatar</label><input type="file" name="bot_image_file" accept="image/*"><input type="text" name="image_url" value="<?= htmlspecialchars($edit_bot['image_url'] ?? '') ?>" placeholder="URL...">
                </div>
                <div class="form-group"><label>RP Link</label><input type="text" name="rp_platform_url" required value="<?= htmlspecialchars($edit_bot['rp_platform_url'] ?? '') ?>"></div>
                <div class="form-group"><label style="cursor:pointer; color:#f39c12; font-weight:bold;"><input type="checkbox" name="is_pinned" value="1" <?= (isset($edit_bot['is_pinned']) && $edit_bot['is_pinned']) ? 'checked' : '' ?>> 📌 Pin to Top</label></div>
                <button type="submit" name="save_bot" class="btn-action" style="width: 100%;"><?= $action == 'edit' ? 'Save Changes' : 'Publish Bot' ?></button>
            </form>
        </div>

        <div class="admin-panel" style="padding: 0; overflow: hidden;">
            <table class="admin-table">
                <thead><tr><th>Bot</th><th>Themes</th><th style="text-align:right;">Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($bots as $bot): ?>
                    <tr>
                        <td style="display:flex; gap:10px; align-items:center;">
                            <?php if ($bot['image_url']): ?>
                                <img src="<?= htmlspecialchars($bot['image_url']) ?>" style="width:35px; height:35px; object-fit:cover; border-radius:8px;">
                            <?php else: ?>
                                <div style="width: 35px; height: 35px; border-radius: 8px; background: #eee;"></div>
                            <?php endif; ?>
                            <strong><?= htmlspecialchars($bot['name']) ?></strong>
                        </td>
                        <td><span style="font-size:11px; color:#888;"><?= htmlspecialchars($bot['theme_names'] ?? 'None') ?></span></td>
                        <td style="text-align:right;">
                            <a href="admin_bots.php?action=edit&id=<?= $bot['id'] ?>" style="color:var(--pink); font-weight:bold; margin-right:10px;">EDIT</a>
                            <a href="admin_bots.php?delete_id=<?= $bot['id'] ?>" style="color:#ef4444; font-weight:bold;" onclick="return confirm('Delete?');">DEL</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div>
        <div class="admin-panel">
            <h3 class="panel-title"><i class="fa-solid fa-file-invoice-dollar"></i> Commissions</h3>
            <div style="max-height: 600px; overflow-y: auto; padding-right: 5px;">
                <?php foreach($commissions as $com): ?>
                    <div class="com-card">
                        <div style="display:flex; justify-content:space-between; font-weight:bold; font-size:14px; margin-bottom: 5px;">
                            <span>#<?= $com['id'] ?> <?= htmlspecialchars($com['username']) ?></span>
                            <span style="color:#10b981;">$<?= $com['amount_paid'] ?></span>
                        </div>
                        <div style="font-size:11px; margin-bottom: 10px;"><?= $com['is_private'] ? '🔒 Private Bot' : '🌍 Public Bot' ?></div>
                        
                        <!-- CÁC NÚT TƯƠNG TÁC (Xem chi tiết + Ảnh) -->
                        <div style="display: flex; gap: 8px; margin-bottom: 10px;">
                            <button type="button" onclick="toggleDetails(<?= $com['id'] ?>)" style="background: var(--pink-soft); border: 1px solid var(--pink); color: var(--pink-dark); padding: 4px 8px; border-radius: 6px; font-size: 11px; font-weight: bold; cursor: pointer;">
                                👁️ View Details
                            </button>
                            <?php if(!empty($com['image_url'])): ?>
                                <a href="<?= htmlspecialchars($com['image_url']) ?>" target="_blank" style="display: inline-block; font-size: 11px; background: #eee; border: 1.5px solid var(--border); padding: 4px 8px; border-radius: 6px; color: var(--text-2); text-decoration: none; font-weight: bold;">🖼️ Ref Image</a>
                            <?php endif; ?>
                        </div>

                        <!-- KHUNG ẨN CHỨA THÔNG TIN CHI TIẾT ĐỂ LÀM BOT -->
                        <div id="com_detail_<?= $com['id'] ?>" style="display: none; background: var(--surface); padding: 12px; border-radius: 8px; border: 1px dashed #ccc; margin-bottom: 10px; font-size: 12px; color: var(--text-2);">
                            <div style="margin-bottom: 8px;"><strong style="color:#333;">Title:</strong> <?= htmlspecialchars($com['title']) ?></div>
                            <div style="margin-bottom: 8px;"><strong style="color:#333;">Appearance:</strong><br><?= nl2br(htmlspecialchars($com['appearance'])) ?></div>
                            <div><strong style="color:#333;">Context:</strong><br><?= nl2br(htmlspecialchars($com['context'])) ?></div>
                        </div>

                        <!-- FORM CẬP NHẬT TRẠNG THÁI VÀ GỬI LỜI NHẮN -->
                        <form method="POST" action="admin_bots.php" style="border-top: 1px solid #eee; padding-top: 10px;">
                            <input type="hidden" name="com_id" value="<?= $com['id'] ?>">
                            
                            <label style="font-size: 11px; color: var(--text-3); display: block; margin-bottom: 5px;">Admin Note (Discord link, etc.):</label>
                            <textarea name="admin_note" rows="2" style="width: 100%; padding: 6px; border-radius: 6px; border: 1.5px solid var(--border); font-size: 12px; margin-bottom: 8px; font-family: inherit; resize: vertical;" placeholder="Paste Discord link here..."><?= htmlspecialchars($com['admin_note'] ?? '') ?></textarea>
                            
                            <div style="display: flex; gap: 8px; align-items: center;">
                                <select name="status" style="padding: 6px; font-size: 12px; border-radius: 6px; border: 1.5px solid var(--border); outline: none; flex-grow: 1;">
                                    <option value="Pending" <?= $com['status']=='Pending' ? 'selected' : '' ?>>🟡 Pending</option>
                                    <option value="In Progress" <?= $com['status']=='In Progress' ? 'selected' : '' ?>>🔵 In Progress</option>
                                    <option value="Completed" <?= $com['status']=='Completed' ? 'selected' : '' ?>>🟢 Completed</option>
                                </select>
                                <button type="submit" name="update_commission" class="btn-action" style="padding: 6px 15px; font-size: 12px; box-shadow: none;">Save</button>
                            </div>
                        </form>

                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="admin-panel">
            <h3 class="panel-title"><i class="fa-solid fa-tags"></i> Themes</h3>
            <form method="POST" action="admin_bots.php" style="display:flex; gap:5px; margin-bottom:15px;">
                <input type="text" name="new_theme_name" required placeholder="New tag..." style="flex-grow:1; padding:8px; border-radius:8px; border:1px solid #ddd;">
                <button type="submit" name="add_theme" class="btn-action" style="padding:8px 12px;">+</button>
            </form>
            <div style="max-height: 250px; overflow-y: auto;">
                <?php foreach($db_themes as $t): ?>
                    <div style="display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px dashed #eee; font-size:13px;">
                        <span>🏷️ <?= htmlspecialchars($t['name']) ?></span>
                        <a href="admin_bots.php?delete_theme_id=<?= $t['id'] ?>" style="color:#ef4444; font-weight:bold;" onclick="return confirm('Delete?');">DEL</a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script>
    // JS Xử lý bật tắt nút View Details
    function toggleDetails(id) {
        var el = document.getElementById('com_detail_' + id);
        if (el.style.display === 'none') {
            el.style.display = 'block';
        } else {
            el.style.display = 'none';
        }
    }
</script>

<?php require_once '../includes/footer.php'; ?>