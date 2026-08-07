<?php
/**
 * admin/categories_api.php
 * CRUD cho bảng categories. Đáp ứng REQ-4.1.
 * GET  -> danh sách category (kèm số món đang thuộc category đó)
 * POST -> {action: 'create'|'update'|'delete', ...}
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_role_api('admin');

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $pdo->query("
            SELECT c.category_id, c.category_name, c.display_order,
                   COUNT(m.item_id) AS item_count
            FROM categories c
            LEFT JOIN menu_items m ON m.category_id = c.category_id AND m.deleted_at IS NULL
            GROUP BY c.category_id, c.category_name, c.display_order
            ORDER BY c.display_order ASC, c.category_name ASC
        ");
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input) || !isset($input['action'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ.']);
        exit;
    }

    switch ($input['action']) {
        case 'create':
            $name = trim((string)($input['category_name'] ?? ''));
            $order = filter_var($input['display_order'] ?? 0, FILTER_VALIDATE_INT) ?: 0;

            if ($name === '') {
                http_response_code(422);
                echo json_encode(['success' => false, 'message' => 'Tên danh mục không được để trống.']);
                exit;
            }

            $stmt = $pdo->prepare("INSERT INTO categories (category_name, display_order) VALUES (:name, :order)");
            $stmt->execute(['name' => $name, 'order' => $order]);
            echo json_encode(['success' => true, 'category_id' => (int)$pdo->lastInsertId()]);
            break;

        case 'update':
            $id = filter_var($input['category_id'] ?? null, FILTER_VALIDATE_INT);
            $name = trim((string)($input['category_name'] ?? ''));
            $order = filter_var($input['display_order'] ?? 0, FILTER_VALIDATE_INT) ?: 0;

            if (!$id || $name === '') {
                http_response_code(422);
                echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ.']);
                exit;
            }

            $stmt = $pdo->prepare("UPDATE categories SET category_name = :name, display_order = :order WHERE category_id = :id");
            $stmt->execute(['name' => $name, 'order' => $order, 'id' => $id]);
            echo json_encode(['success' => true]);
            break;

        case 'delete':
            $id = filter_var($input['category_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$id) {
                http_response_code(422);
                echo json_encode(['success' => false, 'message' => 'category_id không hợp lệ.']);
                exit;
            }
            $stmt = $pdo->prepare("DELETE FROM categories WHERE category_id = :id");
            $stmt->execute(['id' => $id]);
            echo json_encode(['success' => true]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Action không hợp lệ.']);
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Lỗi cơ sở dữ liệu.']);
}