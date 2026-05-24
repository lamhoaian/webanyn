<?php
session_start();
// ==========================================
// FILE 1: config/database.php
// Nhiệm vụ: Kết nối đến MySQL bằng PDO
// ==========================================
$host = '127.0.0.1';
$db   = 'anyn'; // Đổi tên DB ở đây
$user = 'root'; // User database
$pass = ''; // Password database
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}
?>