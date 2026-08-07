<?php
// api/get_categories.php
// Endpoint: GET - Trả về danh sách danh mục cho dropdown filter ở trang order.
// KHÁC với admin/categories_api.php: không yêu cầu đăng nhập (khách hàng dùng),
// và chỉ trả category CÒN món available để không hiện danh mục rỗng trong filter.

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config.php';

try {
    $stmt = $pdo->query("
        SELECT c.category_id, c.category_name, COUNT(m.item_id) AS item_count
        FROM categories c
        INNER JOIN menu_items m
            ON m.category_id = c.category_id
            AND m.deleted_at IS NULL
            AND m.is_available = 1
        GROUP BY c.category_id, c.category_name
        ORDER BY c.display_order ASC, c.category_name ASC
    ");
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to fetch categories.']);
}