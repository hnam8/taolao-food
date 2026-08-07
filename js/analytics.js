// js/analytics.js
// Module 5 - Analytics: gọi analytics_api.php, vẽ 4 biểu đồ bằng Chart.js

// Bảng màu đồng bộ với theme lacquerware đang dùng ở dashboard.css
const CHART_COLORS = {
    lacquer: '#B23A3A',
    amber: '#D9A441',
    jade: '#3E8368',
    blue: '#2D5F8A',
    grayMuted: '#8a7d6a',
};

const STATUS_COLOR_MAP = {
    'Pending':          '#F4E0C4',
    'Preparing':        '#F2D9A8',
    'Out for Delivery': '#CFE3F2',
    'Delivered':        '#C7E8D5',
};

async function loadAnalytics() {
    try {
        const res = await fetch('analytics_api.php');
        const result = await res.json();

        if (!result.success) {
            showError(result.message || 'Không thể tải dữ liệu analytics.');
            return;
        }

        renderKPIs(result.data);
        renderRevenueChart(result.data.revenue_by_day);
        renderTopItemsChart(result.data.top_items);
        renderStatusChart(result.data.status_distribution);
    } catch (err) {
        console.error('Lỗi tải analytics:', err);
        showError('Lỗi kết nối đến server.');
    }
}

function showError(message) {
    const el = document.getElementById('analytics-error');
    el.textContent = message;
    el.classList.remove('hidden');
}

// ---------- KPI CARDS ----------
function renderKPIs(data) {
    document.getElementById('kpi-total-revenue').textContent = formatCurrency(data.total_revenue) + ' đ';
    document.getElementById('kpi-total-orders').textContent = data.total_orders;
    document.getElementById('kpi-aov').textContent = formatCurrency(data.average_order_value) + ' đ';
}

// ---------- BIỂU ĐỒ 1: DOANH THU THEO NGÀY (line chart) ----------
function renderRevenueChart(revenueByDay) {
    const ctx = document.getElementById('revenueChart');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: revenueByDay.map(d => formatShortDate(d.date)),
            datasets: [{
                label: 'Doanh thu (đ)',
                data: revenueByDay.map(d => d.revenue),
                borderColor: CHART_COLORS.lacquer,
                backgroundColor: 'rgba(178, 58, 58, 0.12)',
                fill: true,
                tension: 0.25,
                pointRadius: 3,
            }],
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { callback: (value) => formatCurrency(value) },
                },
            },
        },
    });
}

// ---------- BIỂU ĐỒ 2: TOP MÓN BÁN CHẠY (horizontal bar chart) ----------
function renderTopItemsChart(topItems) {
    const ctx = document.getElementById('topItemsChart');

    if (topItems.length === 0) {
        ctx.parentElement.innerHTML += '<p class="text-muted">Chưa có đơn hàng đã giao để thống kê.</p>';
        return;
    }

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: topItems.map(i => i.item_name),
            datasets: [{
                label: 'Số lượng đã bán',
                data: topItems.map(i => i.total_qty),
                backgroundColor: CHART_COLORS.amber,
                borderRadius: 4,
            }],
        },
        options: {
            indexAxis: 'y', // bar ngang - dễ đọc tên món dài
            responsive: true,
            plugins: { legend: { display: false } },
            scales: { x: { beginAtZero: true, ticks: { stepSize: 1 } } },
        },
    });
}

// ---------- BIỂU ĐỒ 3: TỶ LỆ TRẠNG THÁI ĐƠN (doughnut chart) ----------
function renderStatusChart(statusDistribution) {
    const ctx = document.getElementById('statusChart');

    if (statusDistribution.length === 0) {
        ctx.parentElement.innerHTML += '<p class="text-muted">Chưa có đơn hàng nào.</p>';
        return;
    }

    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: statusDistribution.map(s => s.status),
            datasets: [{
                data: statusDistribution.map(s => s.count),
                backgroundColor: statusDistribution.map(s => STATUS_COLOR_MAP[s.status] || CHART_COLORS.grayMuted),
                borderWidth: 1,
            }],
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'bottom' } },
        },
    });
}

// ---------- HELPERS ----------
function formatCurrency(num) {
    return new Intl.NumberFormat('vi-VN').format(num);
}

function formatShortDate(dateStr) {
    const d = new Date(dateStr + 'T00:00:00');
    return d.toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit' });
}

// ---------- INIT ----------
// Không tự chạy khi trang vừa load - chỉ chạy khi admin mở tab Analytics
// lần đầu (xem js/tabs.js), tránh tính toán SQL nặng (SUM/GROUP BY) mỗi lần
// vào dashboard trong khi admin có thể chỉ cần xem tab Đơn hàng.
async function initAnalyticsTab() {
    await loadAnalytics();
}