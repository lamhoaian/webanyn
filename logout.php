<?php
session_start();
require_once '../config/database.php';

// Nếu user đang đăng nhập
if (isset($_SESSION['user_id'])) {
    // 1. Xóa token trong Database để cái vé ở trình duyệt bị vô hiệu hóa
    $pdo->prepare("UPDATE users SET remember_token = NULL WHERE id = ?")->execute([$_SESSION['user_id']]);
}

// 2. Xóa Cookie ở trình duyệt (Set thời gian về quá khứ)
if (isset($_COOKIE['remember_me'])) {
    setcookie('remember_me', '', time() - 3600, "/");
}

// 3. Phá hủy toàn bộ Session
$_SESSION = array();
session_destroy();

// 4. Đá về trang chủ
header("Location: ../index.php");
exit();
?>