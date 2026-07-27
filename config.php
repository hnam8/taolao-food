<?php
// config.php
// File kết nối Database dùng chung cho toàn bộ dự án TaoLao Food.
// Mọi file PHP khác (dispatch_order.php, get_driver_position.php, admin_dashboard.php,
// tracking.php, ...) đều require file này để lấy biến $pdo.

// Đồng bộ timezone giữa PHP và MySQL - quan trọng cho Module 3 (Delivery Tracking),
// vì get_driver_position.php dùng time() (PHP) so sánh với start_time được MySQL NOW() ghi vào.
// Nếu bỏ dòng này, progress% có thể tính sai nếu server lệch múi giờ.
date_default_timezone_set('Asia/Ho_Chi_Minh');

$host    = '127.0.0.1';
$db      = 'taolao_food_db';
$user    = 'root';
$pass    = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false, // bắt buộc dùng prepared statement thật (NFR-1: chống SQL Injection)
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    // Không lộ thông tin nhạy cảm ra ngoài production
    error_log($e->getMessage());
    die("Database connection failed. Please check config or contact admin.");
}