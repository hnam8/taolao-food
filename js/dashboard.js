// js/dashboard.js
// Module 2: Restaurant Management Dashboard - logic hiển thị & tương tác

// Mapping FSM: trạng thái hiện tại -> trạng thái kế tiếp (dùng để render đúng nút hành động)
// Đồng bộ với bảng VALID_TRANSITIONS bên admin/update_status.php
const STATUS_FLOW = {
    'Pending':   { next: 'Preparing', label: 'Xác nhận & Chuẩn bị', badgeClass: 'status-pending' },
    'Preparing': { next: 'Done',      label: 'Hoàn tất đơn',        badgeClass: 'status-preparing' },
    'Done':      { next: null,        label: null,                  badgeClass: 'status-done' },
};

const REFRESH_INTERVAL_MS = 15000; // tự động refresh mỗi 15s (đáp ứng "real-time or refreshed table")
let expandedOrderId = null; // theo dõi đơn nào đang mở rộng chi tiết

// ---------- 1. TẢI DANH SÁCH ĐƠN HÀNG ----------
async function loadOrders() {
    try {
        const response = await fetch('get_orders.php');
        const result = await response.json();

        if (!result.success) {
            renderError();
            return;
        }

        renderOrders(result.data);
        document.getElementById('last-updated').textContent =
            'Cập nhật lúc: ' + new Date().toLocaleTimeString('vi-VN');
    } catch (err) {
        console.error('Lỗi khi tải đơn hàng:', err);
        renderError();
    }
}

function renderError() {
    document.getElementById('orders-tbody').innerHTML =
        '<tr><td colspan="6" class="error-row">Lỗi kết nối đến server.</td></tr>';
}

// ---------- 2. RENDER BẢNG ĐƠN HÀNG ----------
function renderOrders(orders) {
    const tbody = document.getElementById('orders-tbody');

    if (orders.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="empty-row">Chưa có đơn hàng nào.</td></tr>';
        return;
    }

    tbody.innerHTML = orders.map(order => buildOrderRows(order)).join('');

    // Gắn sự kiện: click vào hàng đơn hàng -> mở rộng chi tiết (REQ-2.2)
    document.querySelectorAll('.order-row').forEach(row => {
        row.addEventListener('click', (e) => {
            if (e.target.closest('.action-btn')) return; // không mở rộng khi bấm nút hành động
            const orderId = row.dataset.orderId;
            expandedOrderId = (expandedOrderId === orderId) ? null : orderId;
            renderOrders(orders); // re-render để cập nhật trạng thái mở rộng
        });
    });

    // Gắn sự kiện cho nút chuyển trạng thái (REQ-2.3)
    document.querySelectorAll('.action-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            handleStatusUpdate(btn.dataset.orderId, btn.dataset.nextStatus);
        });
    });
}

// Xây dựng HTML cho 1 dòng đơn hàng + dòng chi tiết (nếu đang mở rộng)
function buildOrderRows(order) {
    const flow = STATUS_FLOW[order.status];
    const isExpanded = expandedOrderId === String(order.order_id);

    const actionButton = flow.next
        ? `<button class="action-btn" data-order-id="${order.order_id}" data-next-status="${flow.next}">${flow.label}</button>`
        : `<span class="done-label">Đã hoàn tất</span>`;

    const mainRow = `
        <tr class="order-row ${isExpanded ? 'row-expanded' : ''}" data-order-id="${order.order_id}">
            <td>#${order.order_id}</td>
            <td>${escapeHtml(order.customer_name)}</td>
            <td class="price-cell">${formatCurrency(order.total_price)} đ</td>
            <td><span class="status-badge ${flow.badgeClass}">${escapeHtml(order.status)}</span></td>
            <td class="time-cell">${formatDateTime(order.created_at)}</td>
            <td>${actionButton}</td>
        </tr>
    `;

    // Dòng chi tiết món ăn - chỉ render khi đơn hàng đang được mở rộng (REQ-2.2)
    const detailRow = isExpanded ? `
        <tr class="detail-row">
            <td colspan="6">
                <div class="order-detail-box">
                    <strong>Chi tiết đơn #${order.order_id}:</strong>

                    <div class="delivery-info">
                        <p><strong>SĐT:</strong> ${escapeHtml(order.phone || 'Chưa cung cấp')}</p>
                        <p><strong>Địa chỉ giao hàng:</strong> ${escapeHtml(order.delivery_address || 'Chưa cung cấp')}</p>
                        <p><strong>Thanh toán:</strong> ${formatPaymentMethod(order.payment_method)} — ${formatPaymentStatus(order.payment_status)}</p>
                    </div>

                    <ul class="detail-item-list">
                        ${order.items.map(item => `
                            <li>
                                <span>${escapeHtml(item.item_name)} × ${item.quantity}</span>
                                <span>${formatCurrency(item.unit_price * item.quantity)} đ</span>
                            </li>
                        `).join('')}
                    </ul>
                </div>
            </td>
        </tr>
    ` : '';

    return mainRow + detailRow;
}

// ---------- 3. CẬP NHẬT TRẠNG THÁI ----------
async function handleStatusUpdate(orderId, nextStatus) {
    try {
        const response = await fetch('update_status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ order_id: parseInt(orderId, 10), new_status: nextStatus }),
        });
        const result = await response.json();

        if (!result.success) {
            alert(result.message || 'Không thể cập nhật trạng thái.');
            return;
        }

        loadOrders(); // reload lại bảng để phản ánh trạng thái mới
    } catch (err) {
        console.error('Lỗi khi cập nhật trạng thái:', err);
        alert('Lỗi kết nối đến server.');
    }
}

// ---------- HELPERS ----------
function formatCurrency(num) {
    return new Intl.NumberFormat('vi-VN').format(num);
}

function formatPaymentMethod(method) {
    const map = { cash: 'Tiền mặt', wallet: 'Ví TaoLao', qr: 'Chuyển khoản QR' };
    return map[method] || method;
}

function formatPaymentStatus(status) {
    return status === 'paid' ? '✅ Đã thanh toán' : '⏳ Chưa thanh toán';
}

function formatDateTime(dateStr) {
    const d = new Date(dateStr.replace(' ', 'T'));
    return d.toLocaleString('vi-VN', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// ---------- INIT ----------
document.addEventListener('DOMContentLoaded', () => {
    loadOrders();
    document.getElementById('refresh-btn').addEventListener('click', loadOrders);
    setInterval(loadOrders, REFRESH_INTERVAL_MS);
});