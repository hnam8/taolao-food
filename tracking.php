<?php
/**
 * tracking.php
 * Module 3 - Delivery Tracking Simulation (Customer-facing)
 * Hiển thị canvas mô phỏng: nhà hàng (cố định) -> driver (di chuyển) -> khách hàng (cố định)
 * Truy cập: tracking.php?order_id=1
 */

require 'config.php';

$orderId = filter_input(INPUT_GET, 'order_id', FILTER_VALIDATE_INT);

if (!$orderId) {
    die('Thiếu order_id. Truy cập dạng: tracking.php?order_id=1');
}

$stmt = $pdo->prepare("SELECT order_id, customer_name, status FROM orders WHERE order_id = :id");
$stmt->execute(['id' => $orderId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    die('Không tìm thấy đơn hàng #' . htmlspecialchars($orderId));
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>TaoLao Food - Theo dõi đơn hàng #<?= htmlspecialchars($order['order_id']) ?></title>
<style>
    body { font-family: Arial, sans-serif; margin: 40px; background: #f7f7f7; text-align: center; }
    h1 { color: #2c3e50; }
    #trackingCanvas { background: #eafaf1; border: 2px solid #27ae60; border-radius: 8px; }
    #progressLabel { font-size: 18px; font-weight: bold; margin-top: 15px; color: #2c3e50; }
    .legend { margin-top: 10px; font-size: 14px; }
    .legend span { display: inline-block; margin: 0 12px; }
    .dot { display: inline-block; width: 10px; height: 10px; border-radius: 50%; margin-right: 5px; vertical-align: middle; }
</style>
</head>
<body>

<h1>Đơn hàng #<?= htmlspecialchars($order['order_id']) ?> - <?= htmlspecialchars($order['customer_name']) ?></h1>

<canvas id="trackingCanvas" width="400" height="400"></canvas>

<div class="legend">
    <span><i class="dot" style="background:#e74c3c;"></i>Nhà hàng</span>
    <span><i class="dot" style="background:#2980b9;"></i>Khách hàng</span>
    <span><i class="dot" style="background:#27ae60;"></i>Tài xế</span>
</div>

<div id="progressLabel">Đang tải dữ liệu...</div>

<script>
const orderId = <?= (int)$order['order_id'] ?>;
const canvas = document.getElementById('trackingCanvas');
const ctx = canvas.getContext('2d');

// Vẽ toàn bộ canvas: 2 điểm cố định (nhà hàng, khách hàng) + 1 điểm động (driver)
function drawMap(originX, originY, destX, destY, driverX, driverY, hasDriver) {
    ctx.clearRect(0, 0, canvas.width, canvas.height);

    // Nhà hàng (cố định, màu đỏ)
    drawPoint(originX, originY, '#e74c3c', 8);

    // Khách hàng (cố định, màu xanh dương)
    drawPoint(destX, destY, '#2980b9', 8);

    // Đường đi giả lập (nét đứt) giữa 2 điểm
    ctx.setLineDash([5, 5]);
    ctx.strokeStyle = '#bbb';
    ctx.beginPath();
    ctx.moveTo(originX / 100 * canvas.width, originY / 100 * canvas.height);
    ctx.lineTo(destX / 100 * canvas.width, destY / 100 * canvas.height);
    ctx.stroke();
    ctx.setLineDash([]);

    // Driver (di chuyển, màu xanh lá) - chỉ vẽ nếu đã dispatch
    if (hasDriver) {
        drawPoint(driverX, driverY, '#27ae60', 10);
    }
}

function drawPoint(xPercent, yPercent, color, radius) {
    const x = xPercent / 100 * canvas.width;
    const y = yPercent / 100 * canvas.height;
    ctx.fillStyle = color;
    ctx.beginPath();
    ctx.arc(x, y, radius, 0, Math.PI * 2);
    ctx.fill();
}

function pollDriverPosition() {
    fetch(`get_driver_position.php?order_id=${orderId}`)
        .then(res => res.json())
        .then(data => {
            const label = document.getElementById('progressLabel');

            if (data.error) {
                label.textContent = 'Không tìm thấy dữ liệu theo dõi.';
                return; // dừng polling
            }

            if (data.status === 'not_dispatched') {
                label.textContent = 'Đơn hàng đang được chuẩn bị, chưa có tài xế...';
                // vẫn chưa có tọa độ nên chỉ hiện canvas trống, tiếp tục hỏi lại
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                setTimeout(pollDriverPosition, 3000);
                return;
            }

            drawMap(data.origin_x, data.origin_y, data.dest_x, data.dest_y, data.x, data.y, true);

            if (data.status === 'Arrived') {
                label.textContent = 'Đơn hàng đã được giao thành công! 🎉';
                // dừng polling, không gọi lại nữa
            } else {
                label.textContent = `Đang giao... ${data.progress}%`;
                setTimeout(pollDriverPosition, 2500);
            }
        })
        .catch(() => {
            // Mất mạng / lỗi server tạm thời -> thử lại thay vì crash UI
            document.getElementById('progressLabel').textContent = 'Đang kết nối lại...';
            setTimeout(pollDriverPosition, 4000);
        });
}

pollDriverPosition();
</script>

</body>
</html>