<?php
/**
 * admin/menu_api.php
 * CRUD cho bảng menu_items. Đáp ứng REQ-4.2, REQ-4.3, REQ-4.4.
 * GET  -> danh sách món (mặc định chỉ món chưa bị soft-delete)
 * POST -> {action: 'create'|'update'|'delete'|'restore'|'toggle_availability', ...}
 *
 * LƯU Ý: endpoint này KHÔNG xử lý upload ảnh (xem admin/upload_image.php riêng),
 * vì multipart/form-data cần cách đọc request khác hẳn JSON body.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

/** @var \PDO $pdo */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_role_api('admin');

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // ?include_deleted=1 -> admin xem cả món đã bị ẩn để có thể "khôi phục"
        $includeDeleted = isset($_GET['include_deleted']) && $_GET['include_deleted'] === '1';

        $sql = "
            SELECT m.item_id, m.item_name, m.description, m.price, m.image_url,
                   m.category_id, c.category_name, m.is_available, m.deleted_at
            FROM menu_items m
            LEFT JOIN categories c ON m.category_id = c.category_id
        ";
        if (!$includeDeleted) {
            $sql .= " WHERE m.deleted_at IS NULL";
        }
        $sql .= " ORDER BY m.item_name ASC";

        $stmt = $pdo->query($sql);
        $items = $stmt->fetchAll();

        // Ép kiểu số/bool cho đúng type khi trả JSON (tránh JS phải tự parse string)
        foreach ($items as &$item) {
            $item['price'] = (float)$item['price'];
            $item['is_available'] = (bool)$item['is_available'];
            $item['is_deleted'] = $item['deleted_at'] !== null;
        }

        echo json_encode(['success' => true, 'data' => $items]);
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
        case 'create': {
            $name = trim((string)($input['item_name'] ?? ''));
            $description = trim((string)($input['description'] ?? ''));
            $price = filter_var($input['price'] ?? null, FILTER_VALIDATE_FLOAT);
            $categoryId = filter_var($input['category_id'] ?? null, FILTER_VALIDATE_INT) ?: null;

            if ($name === '' || $price === false || $price === null || $price < 0) {
                http_response_code(422);
                echo json_encode(['success' => false, 'message' => 'Tên món và giá phải hợp lệ (giá >= 0).']);
                exit;
            }

            $stmt = $pdo->prepare("
                INSERT INTO menu_items (item_name, description, price, category_id, is_available)
                VALUES (:name, :desc, :price, :cat, 1)
            ");
            $stmt->execute([
                'name' => $name, 'desc' => $description, 'price' => $price, 'cat' => $categoryId,
            ]);

            // Trả về item_id mới để client dùng ngay cho bước upload ảnh tiếp theo (nếu có)
            echo json_encode(['success' => true, 'item_id' => (int)$pdo->lastInsertId()]);
            break;
        }

        case 'update': {
            $id = filter_var($input['item_id'] ?? null, FILTER_VALIDATE_INT);
            $name = trim((string)($input['item_name'] ?? ''));
            $description = trim((string)($input['description'] ?? ''));
            $price = filter_var($input['price'] ?? null, FILTER_VALIDATE_FLOAT);
            $categoryId = filter_var($input['category_id'] ?? null, FILTER_VALIDATE_INT) ?: null;

            if (!$id || $name === '' || $price === false || $price === null || $price < 0) {
                http_response_code(422);
                echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ.']);
                exit;
            }

            $stmt = $pdo->prepare("
                UPDATE menu_items
                SET item_name = :name, description = :desc, price = :price, category_id = :cat
                WHERE item_id = :id AND deleted_at IS NULL
            ");
            $stmt->execute([
                'name' => $name, 'desc' => $description, 'price' => $price,
                'cat' => $categoryId, 'id' => $id,
            ]);
            echo json_encode(['success' => true]);
            break;
        }

        case 'toggle_availability': {
            $id = filter_var($input['item_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$id) {
                http_response_code(422);
                echo json_encode(['success' => false, 'message' => 'item_id không hợp lệ.']);
                exit;
            }
            // Toggle trực tiếp trong SQL (is_available = NOT is_available), tránh race
            // condition so với việc đọc giá trị cũ ở PHP rồi ghi giá trị ngược lại.
            $stmt = $pdo->prepare("
                UPDATE menu_items SET is_available = NOT is_available
                WHERE item_id = :id AND deleted_at IS NULL
            ");
            $stmt->execute(['id' => $id]);
            echo json_encode(['success' => true]);
            break;
        }

        case 'delete': {
            // SOFT DELETE - không bao giờ DELETE cứng (NFR-6), giữ nguyên dữ liệu
            // lịch sử cho các đơn hàng cũ đã snapshot tên/giá món này.
            $id = filter_var($input['item_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$id) {
                http_response_code(422);
                echo json_encode(['success' => false, 'message' => 'item_id không hợp lệ.']);
                exit;
            }
            $stmt = $pdo->prepare("UPDATE menu_items SET deleted_at = NOW() WHERE item_id = :id");
            $stmt->execute(['id' => $id]);
            echo json_encode(['success' => true]);
            break;
        }

        case 'restore': {
            $id = filter_var($input['item_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$id) {
                http_response_code(422);
                echo json_encode(['success' => false, 'message' => 'item_id không hợp lệ.']);
                exit;
            }
            $stmt = $pdo->prepare("UPDATE menu_items SET deleted_at = NULL WHERE item_id = :id");
            $stmt->execute(['id' => $id]);
            echo json_encode(['success' => true]);
            break;
        }

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Action không hợp lệ.']);
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Lỗi cơ sở dữ liệu.']);
}