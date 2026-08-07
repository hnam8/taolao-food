// js/track-order.js
// Module 1 (mở rộng): Guest tra cứu trạng thái đơn hàng
// Đọc danh sách order_id đã lưu trong localStorage (do cart.js ghi vào lúc checkout),
// gọi api/get_order_status.php để lấy trạng thái mới nhất, và AJAX polling định kỳ.

const STORAGE_KEY = 'taolao_guest_orders'; // phải khớp với key dùng trong cart.js

// Thứ tự các trạng thái theo FSM của hệ thống (khớp update_status.php phía Admin)
const STATUS_FLOW = ['Pending', 'Preparing', 'Out for Delivery', 'Delivered'];

const STATUS_LABELS = {
    'Pending': 'Chờ xác nhận',
    'Preparing': 'Đang chuẩn bị',
    'Out for Delivery': 'Đang giao hàng',
    'Delivered': 'Đã giao thành công',
};

let pollTimer = null;

// ---------- 1. ĐỌC DANH SÁCH ORDER_ID TỪ LOCALSTORAGE ----------
function getSavedOrderIds() {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        if (!raw) return [];
        const parsed = JSON.parse(raw);
        // Chỉ giữ lại các số nguyên dương hợp lệ, loại trùng
        return [...new Set(parsed.filter(id => Number.isInteger(id) && id > 0))];
    } catch (err) {
        console.error('Lỗi đọc localStorage:', err);
        return [];
    }
}

// ---------- 2. GỌI API LẤY TRẠNG THÁI ----------
async function fetchOrderStatuses(ids) {
    const params = new URLSearchParams({ ids: ids.join(',') });
    const response = await fetch(`api/get_order_status.php?${params.toString()}`);
    const result = await response.json();
    if (!result.success) throw new Error(result.message || 'Fetch failed');
    return result.data;
}

// ---------- 3. RENDER PROGRESS BAR THEO FSM ----------
function renderProgressBar(currentStatus) {
    const currentIndex = STATUS_FLOW.indexOf(currentStatus);

    return `
        <div class="status-progress-bar">
            ${STATUS_FLOW.map((step, i) => {
                let stateClass = 'pending';
                if (i < currentIndex) stateClass = 'done';
                if (i === currentIndex) stateClass = 'active';
                return `
                    <div class="progress-step ${stateClass}">
                        <span class="progress-dot"></span>
                        <span class="progress-label">${STATUS_LABELS[step] || step}</span>
                    </div>
                `;
            }).join('')}
        </div>
    `;
}

// ---------- 4. RENDER DANH SÁCH ĐƠN ----------
function renderOrders(orders) {
    const listEl = document.getElementById('tracked-orders-list');
    const emptyEl = document.getElementById('no-orders-message');

    if (orders.length === 0) {
        listEl.innerHTML = '';
        emptyEl.classList.remove('hidden');
        return;
    }
    emptyEl.classList.add('hidden');

    // Đơn mới nhất hiển thị trước
    const sorted = [...orders].sort((a, b) => b.order_id - a.order_id);

    listEl.innerHTML = sorted.map(order => `
        <div class="tracked-order-card">
            <div class="tracked-order-header">
                <strong>Đơn #${order.order_id}</strong>
                <span class="tracked-order-total">${formatCurrency(order.total_price)} đ</span>
            </div>
            <p class="tracked-order-time">Đặt lúc: ${escapeHtml(order.created_at)}</p>
            ${order.status === 'Delivered'
                ? '<p class="status-delivered-text">✅ Đơn hàng đã được giao thành công.</p>'
                : renderProgressBar(order.status)}
        </div>
    `).join('');
}

// ---------- 5. VÒNG LẶP POLLING ----------
async function refreshTrackedOrders() {
    const ids = getSavedOrderIds();

    if (ids.length === 0) {
        renderOrders([]);
        return;
    }

    try {
        const orders = await fetchOrderStatuses(ids);
        renderOrders(orders);

        // Nếu TẤT CẢ đơn đã "Delivered" thì dừng polling để đỡ tốn tài nguyên
        const allDelivered = orders.length > 0 && orders.every(o => o.status === 'Delivered');
        if (allDelivered && pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    } catch (err) {
        console.error('Lỗi khi tải trạng thái đơn hàng:', err);
        document.getElementById('tracked-orders-list').innerHTML =
            '<p class="error-text">Không thể tải trạng thái đơn hàng. Vui lòng thử lại sau.</p>';
    }
}

// ---------- HELPER (dùng chung style với cart.js) ----------
function formatCurrency(num) {
    return new Intl.NumberFormat('vi-VN').format(num);
}
function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// ---------- INIT ----------
document.addEventListener('DOMContentLoaded', () => {
    refreshTrackedOrders();
    // AJAX polling mỗi 8 giây - cùng kiến trúc với dashboard.js bên Admin
    pollTimer = setInterval(refreshTrackedOrders, 8000);
});