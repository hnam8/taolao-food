<?php
// api/get_menu.php
// Endpoint: GET - Trả về danh sách món ăn đang available dưới dạng JSON
// Đáp ứng REQ-1.1: hiển thị menu động từ database bằng PHP

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config.php';

try {
    // Chỉ lấy món còn available (is_available = 1) — hỗ trợ soft delete
    $stmt = $pdo->prepare(
        "SELECT item_id, item_name, description, price, image_url 
         FROM menu_items 
         WHERE is_available = 1 
         ORDER BY item_id ASC"
    );
    $stmt->execute();
    $items = $stmt->fetchAll();

    // Ép kiểu price về float để JS xử lý số học chính xác (tránh string "45000.00")
    foreach ($items as &$item) {
        $item['price'] = (float) $item['price'];
    }

    echo json_encode([
        'success' => true,
        'data'    => $items
    ]);
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to fetch menu items.'
    ]);
}