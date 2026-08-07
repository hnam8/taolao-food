// js/driver.js
// Tab "Tài xế": bản đồ mô phỏng nhiều tài xế cùng lúc + bảng theo dõi trạng thái
// Gọi admin/driver_status_api.php (không dùng get_driver_position.php - file đó
// chỉ trả về 1 driver theo 1 order_id, còn tab này cần TOÀN BỘ driver cùng lúc)

const DRIVER_POLL_INTERVAL_MS = 3000;
const DRIVER_COLOR_PALETTE = ['#3E8368', '#B23A3A', '#2D5F8A', '#D9A441', '#7A4FA3', '#C2694E'];

let driverPollTimer = null;
let driverCanvasCtx = null;

function driverColorFor(driverId) {
    return DRIVER_COLOR_PALETTE[driverId % DRIVER_COLOR_PALETTE.length];
}

async function loadDriverStatus() {
    try {
        const res = await fetch('driver_status_api.php');
        const result = await res.json();
        if (!result.success) throw new Error(result.message);

        renderDriverMap(result.data);
        renderDriverTable(result.data);
    } catch (err) {
        console.error('Lỗi tải trạng thái tài xế:', err);
        document.getElementById('driver-tbody').innerHTML =
            '<tr><td colspan="3" class="error-row">Lỗi kết nối đến server.</td></tr>';
    }
}

// ---------- BẢN ĐỒ (canvas) ----------
function renderDriverMap(drivers) {
    const canvas = document.getElementById('driverMapCanvas');
    if (!driverCanvasCtx) driverCanvasCtx = canvas.getContext('2d');
    const ctx = driverCanvasCtx;

    ctx.clearRect(0, 0, canvas.width, canvas.height);

    const activeDrivers = drivers.filter(d => d.order_id !== null && d.x !== null);

    if (activeDrivers.length === 0) {
        ctx.fillStyle = '#8a7d6a';
        ctx.font = '14px sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText('Không có tài xế nào đang giao hàng.', canvas.width / 2, canvas.height / 2);
        return;
    }

    activeDrivers.forEach(driver => {
        const color = driverColorFor(driver.driver_id);

        // Đường đi (nét đứt) origin -> destination
        ctx.setLineDash([4, 4]);
        ctx.strokeStyle = color;
        ctx.globalAlpha = 0.35;
        ctx.beginPath();
        ctx.moveTo(driver.origin_x / 100 * canvas.width, driver.origin_y / 100 * canvas.height);
        ctx.lineTo(driver.dest_x / 100 * canvas.width, driver.dest_y / 100 * canvas.height);
        ctx.stroke();
        ctx.setLineDash([]);
        ctx.globalAlpha = 1;

        // Điểm đích (khách hàng)
        drawDot(ctx, driver.dest_x, driver.dest_y, canvas, '#999', 5);

        // Vị trí driver hiện tại + nhãn tên
        const px = driver.x / 100 * canvas.width;
        const py = driver.y / 100 * canvas.height;
        ctx.fillStyle = color;
        ctx.beginPath();
        ctx.arc(px, py, 8, 0, Math.PI * 2);
        ctx.fill();

        ctx.fillStyle = '#2b241d';
        ctx.font = '11px sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText(`${driver.driver_name} (${driver.progress}%)`, px, py - 12);
    });
}

function drawDot(ctx, xPercent, yPercent, canvas, color, radius) {
    const x = xPercent / 100 * canvas.width;
    const y = yPercent / 100 * canvas.height;
    ctx.fillStyle = color;
    ctx.beginPath();
    ctx.arc(x, y, radius, 0, Math.PI * 2);
    ctx.fill();
}

// ---------- BẢNG THEO DÕI ----------
function renderDriverTable(drivers) {
    const tbody = document.getElementById('driver-tbody');

    if (drivers.length === 0) {
        tbody.innerHTML = '<tr><td colspan="3" class="empty-row">Chưa có tài xế nào trong hệ thống.</td></tr>';
        return;
    }

    tbody.innerHTML = drivers.map(d => {
        const statusText = d.order_id !== null
            ? `<span class="status-badge status-outfordelivery">${escapeHtmlDriver(d.tracking_status)} (${d.progress}%)</span>`
            : `<span class="status-badge status-delivered">Đang rảnh</span>`;

        const orderCell = d.order_id !== null
            ? `#${d.order_id}`
            : `<span class="text-muted">Không có</span>`;

        return `
            <tr>
                <td>${escapeHtmlDriver(d.driver_name)}</td>
                <td>${statusText}</td>
                <td>${orderCell}</td>
            </tr>
        `;
    }).join('');
}

function escapeHtmlDriver(str) {
    const div = document.createElement('div');
    div.textContent = str ?? '';
    return div.innerHTML;
}

// ---------- START/STOP POLLING (chỉ chạy khi tab "Tài xế" đang mở, tránh
// tốn tài nguyên gọi API liên tục khi admin đang xem tab khác) ----------
function startDriverPolling() {
    loadDriverStatus();
    if (driverPollTimer) clearInterval(driverPollTimer);
    driverPollTimer = setInterval(loadDriverStatus, DRIVER_POLL_INTERVAL_MS);
}

function stopDriverPolling() {
    if (driverPollTimer) {
        clearInterval(driverPollTimer);
        driverPollTimer = null;
    }
}