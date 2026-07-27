<?php
/**
 * get_driver_position.php
 * Được tracking.php gọi định kỳ (AJAX polling) để lấy vị trí driver hiện tại.
 * Vị trí được TÍNH Ở SERVER (không phải client) để tránh gian lận và tránh lệch
 * khi khách F5 trang - tiến trình luôn được tính lại đúng từ start_time trong DB.
 */

require 'config.php';
header('Content-Type: application/json');

$orderId = filter_input(INPUT_GET, 'order_id', FILTER_VALIDATE_INT);

// Edge case: order_id không hợp lệ
if (!$orderId) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_order_id']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM delivery_tracking WHERE order_id = :id");
$stmt->execute(['id' => $orderId]);
$track = $stmt->fetch(PDO::FETCH_ASSOC);

// Edge case: đơn chưa được dispatch (còn đang Pending/Preparing)
if (!$track) {
    echo json_encode(['status' => 'not_dispatched']);
    exit;
}

$elapsed = time() - strtotime($track['start_time']);
// clamp về khoảng [0,1] để tránh giá trị âm hoặc vượt 100% nếu server lệch giờ
$progress = max(0, min(1, $elapsed / $track['estimated_duration_seconds']));

$currentX = $track['origin_x'] + ($track['destination_x'] - $track['origin_x']) * $progress;
$currentY = $track['origin_y'] + ($track['destination_y'] - $track['origin_y']) * $progress;

// Khi tới đích: tự động cập nhật order + tracking + trả driver về Idle
if ($progress >= 1 && $track['status'] !== 'Arrived') {
    $pdo->beginTransaction();
    $pdo->prepare("UPDATE delivery_tracking SET status = 'Arrived' WHERE order_id = :id")
        ->execute(['id' => $orderId]);
    $pdo->prepare("UPDATE orders SET status = 'Delivered' WHERE order_id = :id")
        ->execute(['id' => $orderId]);
    $pdo->prepare("UPDATE drivers SET status = 'Idle' WHERE driver_id = :id")
        ->execute(['id' => $track['driver_id']]);
    $pdo->commit();
}

echo json_encode([
    'status'   => $progress >= 1 ? 'Arrived' : 'En Route',
    'x'        => round($currentX, 2),
    'y'        => round($currentY, 2),
    'origin_x' => (float)$track['origin_x'],
    'origin_y' => (float)$track['origin_y'],
    'dest_x'   => (float)$track['destination_x'],
    'dest_y'   => (float)$track['destination_y'],
    'progress' => round($progress * 100)
]);