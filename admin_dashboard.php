<?php
/**
 * admin/admin_dashboard.php
 * Module 2 - Restaurant Management Dashboard
 * Hiển thị danh sách đơn hàng, trạng thái, và thông tin driver (nếu đã dispatch).
 * Yêu cầu: đã đăng nhập với role 'admin' (xem includes/auth_guard.php).
 *
 * Phụ thuộc:
 *   - ../config.php            (biến $pdo)
 *   - ../includes/auth_guard.php (require_role)
 *   - update_status.php        (cùng thư mục admin/, xử lý mọi transition FSM)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth_guard.php';

// Chặn truy cập nếu chưa đăng nhập / không phải admin -> redirect về trang login.
// LƯU Ý: đổi '../login.php' thành đúng đường dẫn login thật của em nếu khác.
require_role('admin', '../login.php');

// Lấy danh sách đơn hàng, JOIN với bảng driver/tracking để biết ai đang giao đơn nào
$stmt = $pdo->query("
    SELECT o.order_id, o.customer_name, o.total_price, o.status,
           d.driver_name, dt.status AS tracking_status
    FROM orders o
    LEFT JOIN delivery_tracking dt ON o.order_id = dt.order_id
    LEFT JOIN drivers d ON dt.driver_id = d.driver_id
    ORDER BY o.created_at DESC
");
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>TaoLao Food - Restaurant Dashboard</title>
<style>
    body { font-family: Arial, sans-serif; margin: 40px; background: #f7f7f7; }
    h1 { color: #2c3e50; }
    table { width: 100%; border-collapse: collapse; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
    th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #eee; }
    th { background: #2c3e50; color: #fff; }
    .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 12px; color: #fff; background: #3498db; margin-left: 6px; }
    .text-muted { color: #999; font-style: italic; }
    button.action-btn { border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; color: #fff; }
    button.dispatch-btn { background: #27ae60; }
    button.dispatch-btn:hover { background: #219150; }
    button.deliver-btn { background: #2980b9; }
    button.deliver-btn:hover { background: #21618c; }
    button.action-btn:disabled { background: #bdc3c7; cursor: not-allowed; }
</style>
</head>
<body>

<h1>TaoLao Food — Restaurant Dashboard</h1>

<table>
    <thead>
        <tr>
            <th>Order ID</th>
            <th>Khách hàng</th>
            <th>Tổng tiền</th>
            <th>Trạng thái đơn</th>
            <th>Tài xế</th>
            <th>Hành động</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($orders as $order): ?>
        <tr id="order-row-<?= (int)$order['order_id'] ?>">
            <td><?= htmlspecialchars((string)$order['order_id']) ?></td>
            <td><?= htmlspecialchars($order['customer_name']) ?></td>
            <td><?= number_format((float)$order['total_price'], 0, ',', '.') ?>đ</td>
            <td class="order-status"><?= htmlspecialchars($order['status']) ?></td>
            <td>
                <?php if ($order['driver_name']): ?>
                    <?= htmlspecialchars($order['driver_name']) ?>
                    <span class="badge"><?= htmlspecialchars($order['tracking_status']) ?></span>
                <?php else: ?>
                    <span class="text-muted">Chưa gán tài xế</span>
                <?php endif; ?>
            </td>
            <td>
                <?php if ($order['status'] === 'Preparing' && !$order['driver_name']): ?>
                    <button class="action-btn dispatch-btn"
                            onclick="updateOrderStatus(<?= (int)$order['order_id'] ?>, 'Out for Delivery', this)">
                        Gán tài xế &amp; Giao hàng
                    </button>
                <?php elseif ($order['status'] === 'Out for Delivery'): ?>
                    <button class="action-btn deliver-btn"
                            onclick="updateOrderStatus(<?= (int)$order['order_id'] ?>, 'Delivered', this)">
                        Xác nhận đã giao
                    </button>
                <?php else: ?>
                    <span class="text-muted">—</span>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<script>
// Gọi update_status.php (cùng thư mục admin/) cho MỌI transition FSM, bao gồm cả
// việc gán driver (Preparing -> Out for Delivery) và xác nhận giao xong
// (Out for Delivery -> Delivered). Không còn dispatch_order.php riêng nữa -
// tất cả đi qua 1 endpoint duy nhất để đảm bảo luôn được kiểm tra theo bảng FSM.
function updateOrderStatus(orderId, newStatus, btnEl) {
    btnEl.disabled = true;
    const originalText = btnEl.textContent;
    btnEl.textContent = 'Đang xử lý...';

    fetch('update_status.php', {
        method: 'POST',
        credentials: 'same-origin', // gửi kèm session cookie để qua được require_role_api('admin')
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ order_id: orderId, new_status: newStatus })
    })
    .then(res => res.json())
    .then(data => {
        if (!data.success) {
            alert(data.message); // ví dụ: "Không còn tài xế rảnh" hoặc "Unauthorized"
            btnEl.disabled = false;
            btnEl.textContent = originalText;
            return;
        }
        // Thành công -> load lại trang để cập nhật bảng (đơn giản, phù hợp phạm vi prototype)
        location.reload();
    })
    .catch(() => {
        alert('Lỗi kết nối server, vui lòng thử lại.');
        btnEl.disabled = false;
        btnEl.textContent = originalText;
    });
}
</script>

</body>
</html>