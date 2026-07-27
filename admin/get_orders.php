<?php
// admin/get_orders.php
// Endpoint: GET - Trả về danh sách đơn hàng kèm chi tiết món ăn + thông tin thanh toán
// Đáp ứng REQ-2.1 (Order List) + REQ-2.2 (Order Detail View) + Payment Integration

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_role_api('admin');

try {
    // Bước 1: Lấy danh sách đơn hàng (bảng cha), kèm thông tin giao hàng & thanh toán,
    // mới nhất lên đầu
    // Thêm LEFT JOIN drivers/delivery_tracking (Module 3) để dashboard biết đơn nào
    // đã có tài xế và tài xế đang ở trạng thái nào. Dùng LEFT JOIN vì phần lớn đơn
    // (Pending/Preparing) sẽ chưa có dòng delivery_tracking tương ứng.
    $stmtOrders = $pdo->prepare(
        "SELECT o.order_id, o.customer_name, o.phone, o.delivery_address, o.total_price, o.status, 
                o.payment_method, o.payment_status, o.created_at,
                dr.driver_name, dt.status AS tracking_status
         FROM orders o
         LEFT JOIN delivery_tracking dt ON o.order_id = dt.order_id
         LEFT JOIN drivers dr ON dt.driver_id = dr.driver_id
         ORDER BY o.created_at DESC"
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