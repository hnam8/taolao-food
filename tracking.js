function pollDriverPosition(orderId) {
    fetch(`get_driver_position.php?order_id=${orderId}`)
        .then(res => res.json())
        .then(data => {
            const label = document.getElementById('progressLabel');

            if (data.error) {
                label.textContent = 'Không tìm thấy đơn hàng.';
                return; // dừng polling
            }
            if (data.status === 'not_dispatched') {
                label.textContent = 'Đơn hàng đang được chuẩn bị, chưa có tài xế...';
                setTimeout(() => pollDriverPosition(orderId), 3000); // vẫn tiếp tục hỏi lại
                return;
            }

            drawMap(data.x, data.y);
            label.textContent = `${data.progress}% - ${data.status}`;

            if (data.status !== 'Arrived') {
                setTimeout(() => pollDriverPosition(orderId), 2500);
            } else {
                label.textContent = 'Đơn hàng đã được giao thành công!';
            }
        })
        .catch(() => {
            // Edge case (c): mất mạng / server lỗi tạm thời -> thử lại thay vì crash UI
            document.getElementById('progressLabel').textContent = 'Đang kết nối lại...';
            setTimeout(() => pollDriverPosition(orderId), 4000);
        });
}