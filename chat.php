<?php
require_once '../config/database.php';
require_once '../includes/gems_lib.php';
ensureGemsSchema($pdo);

// 1. KIỂM TRA ĐĂNG NHẬP (Làm trước)
if (!isset($_SESSION['user_id'])) {
    die("<div style='text-align:center; padding: 50px; background: var(--surface); border-radius: 15px; box-shadow: var(--shadow-md); margin-top: 30px;'><h3 style='color: var(--text-2);'>Access Denied</h3><p style='color: var(--text-3);'>You need to <a href='login.php' style='color: var(--pink); font-weight: bold;'>log in</a> to use Global Chat!</p></div>");
}

$user_id = $_SESSION['user_id'];
$is_admin = ($_SESSION['user_id'] == 1); // Xác định quyền Admin để cấp tính năng xóa

// 2. XỬ LÝ POST TIN NHẮN
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_message'])) {
    $content = trim($_POST['content']);
    if (!empty($content)) {
        $stmt = $pdo->prepare("INSERT INTO global_chat (user_id, content) VALUES (?, ?)");
        $stmt->execute([$user_id, $content]);
        
        // Gửi xong thì load lại trang để tránh resubmit form khi F5
        header("Location: chat.php");
        exit;
    }
}

// 3. ADMIN XÓA TIN NHẮN
if ($is_admin && isset($_GET['delete_id'])) {
    $del_id = $_GET['delete_id'];
    $stmt = $pdo->prepare("DELETE FROM global_chat WHERE id = ?");
    $stmt->execute([$del_id]);
    
    // Xóa xong load lại trang
    header("Location: chat.php");
    exit;
}

// 4. LẤY DANH SÁCH TIN NHẮN (Limit 50 tin nhắn gần nhất)
$stmt = $pdo->query("
    SELECT c.*, u.username, u.avatar_url, u.total_spent, u.active_chat_frame, u.active_avatar_frame
    FROM global_chat c 
    JOIN users u ON c.user_id = u.id 
    ORDER BY c.created_at DESC 
    LIMIT 50
");
$messages = $stmt->fetchAll();
$messages = array_reverse($messages); // Đảo ngược mảng để tin mới nhất nằm ở dưới cùng

// 5. GỌI HEADER ĐỂ IN GIAO DIỆN
require_once '../includes/header.php';
?>

<style>
    .chat-container { max-width: 800px; margin: 0 auto; background: var(--surface); border-radius: 15px; box-shadow: var(--shadow-md); overflow: hidden; display: flex; flex-direction: column; height: 75vh; border: 1px solid #f9f9f9; }
    
    .chat-header { background: var(--pink-soft); padding: 15px 20px; border-bottom: 2px solid var(--pink); display: flex; align-items: center; justify-content: space-between; }
    .chat-title { margin: 0; color: var(--pink-dark); font-size: 20px; font-weight: bold; }
    
    .chat-box { flex-grow: 1; padding: 20px; overflow-y: auto; background: #fdfdfd; display: flex; flex-direction: column; gap: 15px; }
    
    .msg-wrapper { display: flex; gap: 12px; align-items: flex-start; max-width: 85%; }
    .msg-wrapper.my-msg { align-self: flex-end; flex-direction: row-reverse; }
    
    .msg-avatar { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid #eee; background: var(--surface); flex-shrink: 0; }
    .avatar-premium { border-color: #ff8fa3; box-shadow: 0 0 8px rgba(255, 143, 163, 0.4); }
    
    .msg-content-box { display: flex; flex-direction: column; }
    .msg-wrapper.my-msg .msg-content-box { align-items: flex-end; }
    
    .msg-info { font-size: 11px; color: var(--text-3); margin-bottom: 4px; display: flex; gap: 8px; align-items: center; }
    .msg-author { font-weight: bold; color: var(--text-2); }
    .msg-author.premium-name { color: #db2777; }
    .msg-author.admin-name { color: #ef4444; }
    
    .msg-bubble { background: #f1f1f1; padding: 10px 15px; border-radius: 15px; font-size: 14px; color: var(--text); line-height: 1.5; position: relative; word-break: break-word; }
    
    /* bong bóng cho mình gửi */
    .msg-wrapper.my-msg .msg-bubble { background: var(--pink); color: white; border-bottom-right-radius: 5px; }
    
    /* bong bóng premium (ai nạp > 10$) */
    .msg-bubble.premium-bubble { background: #1a1518; color: #eee; border: 1px solid #ff8fa3; box-shadow: 0 0 10px rgba(255, 143, 163, 0.2); }
    .msg-wrapper.my-msg .msg-bubble.premium-bubble { border-bottom-right-radius: 5px; border-bottom-left-radius: 15px; }
    
    .chat-input-area { padding: 15px 20px; background: var(--surface); border-top: 1px solid #eee; display: flex; gap: 10px; }
    .chat-input { flex-grow: 1; padding: 12px 15px; border: 1.5px solid var(--border); border-radius: 20px; outline: none; font-family: inherit; font-size: 14px; transition: 0.2s; }
    .chat-input:focus { border-color: var(--pink); }
    .btn-send { background: var(--pink); color: white; border: none; width: 45px; height: 45px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 16px; transition: 0.2s; }
    .btn-send:hover { background: var(--pink-dark); transform: scale(1.05); }

    .btn-delete-msg { color: #ef4444; font-size: 10px; text-decoration: none; font-weight: bold; padding: 2px 5px; background: #fee2e2; border-radius: 4px; }
</style>

<div class="chat-container">
    <div class="chat-header">
        <h2 class="chat-title">💬 Global Chat Room</h2>
        <div style="font-size: 12px; color: var(--text-3); font-weight: 600;">Welcome, <?= htmlspecialchars($_SESSION['username']) ?>!</div>
    </div>

    <div class="chat-box" id="chatBox">
        <?php if (count($messages) > 0): ?>
            <?php foreach ($messages as $msg): ?>
                <?php 
                    $is_mine = ($msg['user_id'] == $user_id);
                    $is_premium = ($msg['total_spent'] >= 10);
                    $is_admin_msg = ($msg['user_id'] == 1);
                    
                    $wrapper_class = $is_mine ? 'msg-wrapper my-msg' : 'msg-wrapper';
                    
                    $avatar_img = !empty($msg['avatar_url']) ? htmlspecialchars($msg['avatar_url']) : 'https://i.postimg.cc/mZh4H8hC/default-avatar.png';
                    $avatar_class = $is_premium ? 'msg-avatar avatar-premium' : 'msg-avatar';
                    if (!empty($msg['active_avatar_frame'])) {
                        $avatar_class .= ' gem-avt-' . preg_replace('/[^a-z0-9_]/', '', $msg['active_avatar_frame']);
                    }
                    
                    // Style tên
                    $author_class = 'msg-author';
                    if ($is_admin_msg) $author_class .= ' admin-name';
                    elseif ($is_premium) $author_class .= ' premium-name';
                    
                    // Tên hiển thị kèm icon
                    $display_name = htmlspecialchars($msg['username']);
                    if ($is_admin_msg) $display_name .= ' 👑';
                    elseif ($is_premium) $display_name .= ' 🌸';

                    // Style bong bóng chat
                    $bubble_class = 'msg-bubble';
                    if ($is_premium && !$is_mine) $bubble_class .= ' premium-bubble';
                    if (!empty($msg['active_chat_frame'])) {
                        $bubble_class .= ' gem-chat-' . preg_replace('/[^a-z0-9_]/', '', $msg['active_chat_frame']);
                    }
                ?>
                
                <div class="<?= $wrapper_class ?>">
                    <img src="<?= $avatar_img ?>" class="<?= $avatar_class ?>" alt="Avatar">
                    
                    <div class="msg-content-box">
                        <div class="msg-info">
                            <span class="<?= $author_class ?>"><?= $display_name ?></span>
                            <span style="font-size: 10px;"><?= date('H:i', strtotime($msg['created_at'])) ?></span>
                            
                            <!-- Hiện nút xóa nếu mình là admin -->
                            <?php if ($is_admin): ?>
                                <a href="chat.php?delete_id=<?= $msg['id'] ?>" class="btn-delete-msg" onclick="return confirm('Delete this message?');">DEL</a>
                            <?php endif; ?>
                        </div>
                        <div class="<?= $bubble_class ?>">
                            <?= nl2br(htmlspecialchars($msg['content'])) ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="text-align: center; color: #aaa; margin-top: 50px; font-style: italic;">
                No messages yet. Be the first to say hi! 👋
            </div>
        <?php endif; ?>
    </div>

    <form method="POST" action="" class="chat-input-area">
        <input type="text" name="content" class="chat-input" placeholder="Type a message..." required autocomplete="off" autofocus>
        <button type="submit" name="send_message" class="btn-send"><i class="fa-solid fa-paper-plane"></i></button>
    </form>
</div>

<!-- Script tự động cuộn xuống cuối cùng của khung chat -->
<script>
    document.addEventListener("DOMContentLoaded", function() {
        var chatBox = document.getElementById("chatBox");
        chatBox.scrollTop = chatBox.scrollHeight;
    });
</script>

<?php require_once '../includes/footer.php'; ?>