<?php
// config.php
$host = '127.0.0.1';
$db   = 'taolao_food_db';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false, // bắt buộc dùng prepared statement thật (NFR-1)
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    // Không lộ thông tin nhạy cảm ra ngoài production
    error_log($e->getMessage());
    die("Database connection failed. Please check config or contact admin.");
}