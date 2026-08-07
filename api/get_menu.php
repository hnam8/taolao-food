<?php
// api/get_menu.php
// Endpoint: GET - Trả về danh sách món ăn đang available dưới dạng JSON
// Đáp ứng REQ-1.1: hiển thị menu động từ database bằng PHP

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config.php';

try {
    // BUG FIX: query cũ chỉ lọc is_available = 1, THIẾU deleted_at IS NULL -
    // nghĩa là món đã bị admin "xóa" (soft delete, Module 4) vẫn hiện ra cho
    // khách hàng vì cột deleted_at chưa từng được đưa vào điều kiện WHERE.

    // Filter mới: ?search=... (tìm theo tên hoặc mô tả) và ?category_id=... (lọc danh mục)
    $search = trim((string)($_GET['search'] ?? ''));
    $categoryId = filter_input(INPUT_GET, 'category_id', FILTER_VALIDATE_INT);

    $sql = "
        SELECT m.item_id, m.item_name, m.description, m.price, m.image_url,
               m.category_id, c.category_name
        FROM menu_items m
        LEFT JOIN categories c ON m.category_id = c.category_id
        WHERE m.is_available = 1 AND m.deleted_at IS NULL
    ";
    $params = [];

   if ($search !== '') {
    $sql .= " AND (m.item_name LIKE :search1 OR m.description LIKE :search2)";
    $params['search1'] = '%' . $search . '%';
    $params['search2'] = '%' . $search . '%';
    }
    if ($categoryId) {
        $sql .= " AND m.category_id = :category_id";
        $params['category_id'] = $categoryId;
    }
    $sql .= " ORDER BY m.item_id ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
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