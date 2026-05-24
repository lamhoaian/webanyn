<?php
// Gọi kết nối database mới của m
require_once 'config/database.php';

$q = isset($_GET['q']) ? trim($_GET['q']) : '';

if (strlen($q) > 0) {
    // Tìm trong bảng bots, lấy tối đa 5 bot có tên chứa từ khóa
    $stmt = $pdo->prepare("SELECT id, name, image_url FROM bots WHERE name LIKE ? LIMIT 5");
    $stmt->execute(["%$q%"]);
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC); 
    // Trả về dữ liệu dạng JSON cho JavaScript đọc
    echo json_encode($results);
} else {
    echo json_encode([]);
}
?>