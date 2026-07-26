<?php
// admin/get_orders.php
// Endpoint: GET - Trả về danh sách đơn hàng kèm chi tiết món ăn + thông tin thanh toán
// Đáp ứng REQ-2.1 (Order List) + REQ-2.2 (Order Detail View) + Payment Integration

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth_guard.php';

// Bảo vệ endpoint: chỉ admin đã đăng nhập mới gọi được (không chỉ chặn ở dashboard.php,
// vì API này có thể bị gọi thẳng qua Postman/curl nếu không guard riêng)
init_session();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    // Bước 1: Lấy danh sách đơn hàng (bảng cha), kèm thông tin giao hàng & thanh toán,
    // mới nhất lên đầu
    $stmtOrders = $pdo->prepare(
        "SELECT order_id, customer_name, phone, delivery_address, total_price, status, 
                payment_method, payment_status, created_at 
         FROM orders 
         ORDER BY created_at DESC"
    );
    $stmtOrders->execute();
    $orders = $stmtOrders->fetchAll();

    if (empty($orders)) {
        echo json_encode(['success' => true, 'data' => []]);
        exit;
    }

    // Bước 2: Lấy chi tiết món cho TẤT CẢ đơn hàng bằng 1 query duy nhất
    // (tránh N+1 query problem - lấy hết rồi group lại trong PHP thay vì query lặp trong vòng lặp)
    $orderIds = array_column($orders, 'order_id');
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));

    $stmtDetails = $pdo->prepare(
        "SELECT order_id, item_name_snapshot, quantity, unit_price 
         FROM order_details 
         WHERE order_id IN ($placeholders)"
    );
    $stmtDetails->execute($orderIds);
    $allDetails = $stmtDetails->fetchAll();

    // Group chi tiết món theo order_id
    $detailsByOrder = [];
    foreach ($allDetails as $detail) {
        $detailsByOrder[$detail['order_id']][] = [
            'item_name'  => $detail['item_name_snapshot'], // dùng snapshot, KHÔNG join lại menu_items
            'quantity'   => (int) $detail['quantity'],
            'unit_price' => (float) $detail['unit_price'],
        ];
    }

    // Gắn chi tiết + ép kiểu số cho đúng type khi trả JSON
    $result = array_map(function ($order) use ($detailsByOrder) {
        $order['total_price'] = (float) $order['total_price'];
        $order['items'] = $detailsByOrder[$order['order_id']] ?? [];
        return $order;
    }, $orders);

    echo json_encode(['success' => true, 'data' => $result]);

} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to fetch orders.']);
}