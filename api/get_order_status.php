<?php
// api/get_order_status.php
// Endpoint: GET - Trả trạng thái của MỘT HOẶC NHIỀU đơn hàng cụ thể (theo order_id)
// Dùng cho trang Guest "Đơn hàng của tôi" (track-order.php) - KHÔNG cần đăng nhập.
//
// Cách gọi: api/get_order_status.php?ids=12,15,20
//
// LƯU Ý BẢO MẬT (đáng đưa vào phần NFR-1 / D3 của báo cáo):
// Đây là dạng "biết mã đơn thì tra được trạng thái" (không cần mật khẩu).
// Vì order_id là số nguyên tự tăng, về lý thuyết có rủi ro IDOR (đoán được
// mã đơn của người khác). Với phạm vi prototype BTEC, mình chấp nhận rủi ro
// này (đã ghi chú), và chỉ trả về các trường KHÔNG nhạy cảm (không trả
// địa chỉ giao hàng, không trả SĐT đầy đủ) để giảm mức độ rủi ro.
// Hướng nâng cấp thật (có thể nêu trong phần "Recommendations" D3):
// yêu cầu thêm SĐT khớp với đơn hàng trước khi trả kết quả.

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config.php';

try {
    $idsParam = trim((string)($_GET['ids'] ?? ''));

    if ($idsParam === '') {
        echo json_encode(['success' => true, 'data' => []]);
        exit;
    }

    // Parse "12,15,20" -> [12, 15, 20], lọc bỏ giá trị không phải số nguyên dương
    $ids = array_filter(
        array_map('intval', explode(',', $idsParam)),
        fn($id) => $id > 0
    );
    $ids = array_values(array_unique($ids));

    if (empty($ids)) {
        echo json_encode(['success' => true, 'data' => []]);
        exit;
    }

    // Giới hạn số lượng id tra cứu 1 lần (chống lạm dụng endpoint)
    $ids = array_slice($ids, 0, 20);

    // Build placeholder động cho IN (:id0, :id1, ...) - an toàn với PDO prepared statement
    $placeholders = [];
    $params = [];
    foreach ($ids as $i => $id) {
        $key = ":id{$i}";
        $placeholders[] = $key;
        $params[$key] = $id;
    }
    $inClause = implode(', ', $placeholders);

    // Chỉ SELECT các trường không nhạy cảm - không trả địa chỉ/SĐT đầy đủ
    $sql = "
        SELECT order_id, customer_name, total_price, status, created_at
        FROM orders
        WHERE order_id IN ({$inClause})
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll();

    foreach ($orders as &$order) {
        $order['total_price'] = (float) $order['total_price'];
    }

    echo json_encode([
        'success' => true,
        'data'    => $orders,
    ]);
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to fetch order status.',
    ]);
}