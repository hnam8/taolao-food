<?php
/**
 * admin/driver_status_api.php
 * Trả về TOÀN BỘ driver (Idle lẫn đang giao) kèm vị trí hiện tại (nếu đang giao)
 * để render bảng theo dõi + bản đồ nhiều tài xế cùng lúc trong tab "Tài xế".
 *
 * Tự tính progress/x/y giống hệt get_driver_position.php, và cũng tự động
 * hoàn tất đơn khi tới đích - giúp đơn không bị "kẹt" ở Out for Delivery
 * ngay cả khi không có khách hàng nào đang mở tracking.php để trigger việc đó.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_role_api('admin');

try {
    $stmt = $pdo->query("
        SELECT d.driver_id, d.driver_name, d.status AS driver_status,
               dt.tracking_id, dt.order_id, dt.status AS tracking_status,
               dt.origin_x, dt.origin_y, dt.destination_x, dt.destination_y,
               dt.start_time, dt.estimated_duration_seconds
        FROM drivers d
        LEFT JOIN delivery_tracking dt
            ON dt.driver_id = d.driver_id AND dt.status IN ('Assigned', 'En Route')
        ORDER BY d.driver_name ASC
    ");
    $rows = $stmt->fetchAll();

    $result = [];
    foreach ($rows as $row) {
        $entry = [
            'driver_id'     => (int)$row['driver_id'],
            'driver_name'   => $row['driver_name'],
            'driver_status' => $row['driver_status'],
            'order_id'      => $row['order_id'] !== null ? (int)$row['order_id'] : null,
            'tracking_status' => $row['tracking_status'],
            'x' => null,
            'y' => null,
            'origin_x' => null, 'origin_y' => null,
            'dest_x' => null, 'dest_y' => null,
            'progress' => null,
        ];

        if ($row['order_id'] !== null) {
            $elapsed = time() - strtotime($row['start_time']);
            $progress = max(0, min(1, $elapsed / (int)$row['estimated_duration_seconds']));

            $currentX = $row['origin_x'] + ($row['destination_x'] - $row['origin_x']) * $progress;
            $currentY = $row['origin_y'] + ($row['destination_y'] - $row['origin_y']) * $progress;

            // Tự động hoàn tất nếu đã tới đích (đồng bộ logic với get_driver_position.php)
            if ($progress >= 1 && $row['tracking_status'] !== 'Arrived') {
                $pdo->beginTransaction();
                $pdo->prepare("UPDATE delivery_tracking SET status = 'Arrived' WHERE tracking_id = :id")
                    ->execute(['id' => $row['tracking_id']]);
                $pdo->prepare("UPDATE orders SET status = 'Delivered' WHERE order_id = :id")
                    ->execute(['id' => $row['order_id']]);
                $pdo->prepare("UPDATE drivers SET status = 'Idle' WHERE driver_id = :id")
                    ->execute(['id' => $row['driver_id']]);
                $pdo->commit();

                // Đơn này vừa hoàn tất -> không còn "đang hoạt động" nữa, bỏ khỏi danh sách active
                $entry['driver_status'] = 'Idle';
                $entry['order_id'] = null;
                $entry['tracking_status'] = null;
            } else {
                $entry['x'] = round($currentX, 2);
                $entry['y'] = round($currentY, 2);
                $entry['origin_x'] = (float)$row['origin_x'];
                $entry['origin_y'] = (float)$row['origin_y'];
                $entry['dest_x'] = (float)$row['destination_x'];
                $entry['dest_y'] = (float)$row['destination_y'];
                $entry['progress'] = round($progress * 100);
            }
        }

        $result[] = $entry;
    }

    echo json_encode(['success' => true, 'data' => $result]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Lỗi khi tải trạng thái tài xế.']);
}