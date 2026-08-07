// js/tabs.js
// Điều khiển việc chuyển tab trong dashboard.php gộp (Đơn hàng / Tài xế / Menu / Analytics).
// Mỗi tab (trừ "orders" - mặc định mở sẵn) chỉ load dữ liệu LẦN ĐẦU khi được mở
// (lazy load), tránh gọi cả 4 API cùng lúc ngay khi vào trang - đặc biệt tab
// "Tài xế" cần dừng polling khi rời tab để không tốn tài nguyên/băng thông.

const tabInitialized = { orders: true, drivers: false, menu: false, analytics: false };

function switchTab(tabName) {
    // Đổi nút active
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.tab === tabName);
    });
    // Đổi panel hiển thị
    document.querySelectorAll('.tab-panel').forEach(panel => {
        panel.classList.toggle('active', panel.id === `tab-${tabName}`);
    });

    // Dừng polling tài xế nếu rời khỏi tab đó (tránh gọi API ngầm khi không cần)
    if (tabName !== 'drivers' && typeof stopDriverPolling === 'function') {
        stopDriverPolling();
    }

    // Lazy load lần đầu tiên mở mỗi tab
    if (!tabInitialized[tabName]) {
        tabInitialized[tabName] = true;
        if (tabName === 'drivers' && typeof loadDriverStatus === 'function') {
            // dùng startDriverPolling (không chỉ loadDriverStatus) để tự động cập nhật liên tục
        }
        if (tabName === 'menu' && typeof initMenuTab === 'function') initMenuTab();
        if (tabName === 'analytics' && typeof initAnalyticsTab === 'function') initAnalyticsTab();
    }

    // Bật lại polling mỗi lần vào tab "Tài xế" (không chỉ lần đầu - vì dữ liệu
    // vị trí driver thay đổi liên tục, cần polling mỗi lần quay lại tab)
    if (tabName === 'drivers' && typeof startDriverPolling === 'function') {
        startDriverPolling();
    }
}

// Nút "Làm mới" dùng chung cho cả 4 tab - phải biết tab nào đang active
// để gọi đúng hàm load, thay vì gọi cứng loadOrders() như code cũ trước khi gộp
// (lỗi đó khiến nút "vô dụng" khi đang xem tab Tài xế/Menu/Analytics).
function refreshCurrentTab() {
    const activeBtn = document.querySelector('.tab-btn.active');
    const tabName = activeBtn ? activeBtn.dataset.tab : 'orders';

    if (tabName === 'orders' && typeof loadOrders === 'function') {
        loadOrders();
    } else if (tabName === 'drivers' && typeof loadDriverStatus === 'function') {
        loadDriverStatus();
    } else if (tabName === 'menu' && typeof loadMenuItems === 'function') {
        loadCategories();
        loadMenuItems();
    } else if (tabName === 'analytics' && typeof loadAnalytics === 'function') {
        loadAnalytics();
    }
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', () => switchTab(btn.dataset.tab));
    });

    document.getElementById('refresh-btn').addEventListener('click', refreshCurrentTab);
});