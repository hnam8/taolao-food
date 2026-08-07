// js/cart.js
// Module 1: quản lý state giỏ hàng phía client + gọi API

// State giỏ hàng lưu tạm ở client (theo REQ-1.2)
// Cấu trúc: { item_id: { item_name, price, quantity } }
let cart = {};

// ---------- 1. TẢI MENU TỪ SERVER ----------
// Nhận thêm filter (search, category_id) - truyền lên get_menu.php qua query string.
// Gọi lại mỗi khi người dùng gõ tìm kiếm hoặc đổi danh mục (xem phần INIT bên dưới).
async function loadMenu(filters = {}) {
    const menuListEl = document.getElementById('menu-list');
    menuListEl.innerHTML = '<p class="loading-text">Đang tải thực đơn...</p>';

    const params = new URLSearchParams();
    if (filters.search) params.set('search', filters.search);
    if (filters.categoryId) params.set('category_id', filters.categoryId);

    try {
        const response = await fetch(`api/get_menu.php?${params.toString()}`);
        const result = await response.json();

        if (!result.success) {
            menuListEl.innerHTML = '<p class="error-text">Không thể tải thực đơn.</p>';
            return;
        }

        renderMenu(result.data);
    } catch (err) {
        console.error('Lỗi khi tải menu:', err);
        menuListEl.innerHTML = '<p class="error-text">Lỗi kết nối đến server.</p>';
    }
}

// Tải danh sách danh mục cho dropdown filter (gọi 1 lần khi trang load)
async function loadCategoriesForFilter() {
    const select = document.getElementById('menu-category-filter');
    try {
        const response = await fetch('api/get_categories.php');
        const result = await response.json();
        if (!result.success) return;

        const options = result.data
            .map(cat => `<option value="${cat.category_id}">${escapeHtml(cat.category_name)} (${cat.item_count})</option>`)
            .join('');
        select.innerHTML = '<option value="">Tất cả danh mục</option>' + options;
    } catch (err) {
        console.error('Lỗi khi tải danh mục:', err);
        // Không chặn trang nếu lỗi - dropdown chỉ còn mỗi "Tất cả danh mục", vẫn dùng được
    }
}

// Đọc giá trị filter hiện tại từ 2 ô input/select rồi gọi lại loadMenu()
function applyMenuFilters() {
    const search = document.getElementById('menu-search').value.trim();
    const categoryId = document.getElementById('menu-category-filter').value;
    loadMenu({ search, categoryId });
}

// ---------- 2. RENDER MENU RA DOM ----------
function renderMenu(items) {
    const menuListEl = document.getElementById('menu-list');

    if (items.length === 0) {
        menuListEl.innerHTML = '<p>Hiện chưa có món ăn nào.</p>';
        return;
    }

    // Dùng template string nhưng data đã được PHP xử lý sạch từ DB (server-side).
    // Escape thêm ở đây để phòng vệ (defense in depth) tránh DOM-based XSS.
    menuListEl.innerHTML = items.map(item => `
        <div class="menu-item-card" data-id="${item.item_id}">
            <img src="${escapeHtml(item.image_url || 'assets/images/placeholder.jpg')}" alt="${escapeHtml(item.item_name)}">
            <h3>${escapeHtml(item.item_name)}</h3>
            <p class="item-desc">${escapeHtml(item.description || '')}</p>
            <p class="item-price">${formatCurrency(item.price)} đ</p>
            <button class="add-to-cart-btn" 
                    data-id="${item.item_id}" 
                    data-name="${escapeHtml(item.item_name)}" 
                    data-price="${item.price}">
                Thêm vào giỏ
            </button>
        </div>
    `).join('');

    // Gắn sự kiện cho từng nút "Thêm vào giỏ"
    document.querySelectorAll('.add-to-cart-btn').forEach(btn => {
        btn.addEventListener('click', handleAddToCart);
    });
}

// ---------- 3. THÊM VÀO GIỎ HÀNG ----------
function handleAddToCart(e) {
    const btn = e.currentTarget;
    const id = btn.dataset.id;
    const name = btn.dataset.name;
    const price = parseFloat(btn.dataset.price);

    if (cart[id]) {
        cart[id].quantity += 1;
    } else {
        cart[id] = { item_id: id, item_name: name, price: price, quantity: 1 };
    }

    renderCart();
}

// ---------- 4. CẬP NHẬT SỐ LƯỢNG / XOÁ MÓN ----------
function updateQuantity(id, delta) {
    if (!cart[id]) return;
    cart[id].quantity += delta;

    if (cart[id].quantity <= 0) {
        delete cart[id];
    }
    renderCart();
}

// ---------- 5. RENDER GIỎ HÀNG ----------
function renderCart() {
    const cartItemsEl = document.getElementById('cart-items');
    const cartTotalEl = document.getElementById('cart-total-amount');
    const checkoutBtn = document.getElementById('checkout-btn');
    const cartArray = Object.values(cart);

    if (cartArray.length === 0) {
        cartItemsEl.innerHTML = '<p class="empty-cart-text">Giỏ hàng đang trống.</p>';
        cartTotalEl.textContent = '0';
        checkoutBtn.disabled = true;
        return;
    }

    cartItemsEl.innerHTML = cartArray.map(item => `
        <div class="cart-row" data-id="${item.item_id}">
            <span class="cart-item-name">${escapeHtml(item.item_name)}</span>
            <div class="cart-qty-controls">
                <button class="qty-btn" data-action="minus" data-id="${item.item_id}">-</button>
                <span>${item.quantity}</span>
                <button class="qty-btn" data-action="plus" data-id="${item.item_id}">+</button>
            </div>
            <span class="cart-item-subtotal">${formatCurrency(item.price * item.quantity)} đ</span>
        </div>
    `).join('');

    // Gắn sự kiện cho nút +/-
    document.querySelectorAll('.qty-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const id = btn.dataset.id;
            const delta = btn.dataset.action === 'plus' ? 1 : -1;
            updateQuantity(id, delta);
        });
    });

    const total = cartArray.reduce((sum, item) => sum + item.price * item.quantity, 0);
    cartTotalEl.textContent = formatCurrency(total);
    checkoutBtn.disabled = false;
}

// ---------- 6. GỬI ĐƠN HÀNG (checkout) ----------
async function handleCheckout() {
    const nameInput = document.getElementById('customer-name');
    const phoneInput = document.getElementById('customer-phone');
    const addressInput = document.getElementById('customer-address');
    const messageEl = document.getElementById('order-message');
    const paymentMethod = document.querySelector('input[name="payment_method"]:checked').value;

    const customerName = nameInput.value.trim();
    const phone = phoneInput.value.trim();
    const address = addressInput.value.trim();

    if (!customerName) {
        messageEl.textContent = 'Vui lòng nhập tên của bạn.';
        messageEl.className = 'error-text';
        return;
    }
    if (!/^(0|\+84)[0-9]{9,10}$/.test(phone)) {
        messageEl.textContent = 'Số điện thoại không hợp lệ.';
        messageEl.className = 'error-text';
        return;
    }
    if (address.length < 5) {
        messageEl.textContent = 'Vui lòng nhập địa chỉ giao hàng đầy đủ.';
        messageEl.className = 'error-text';
        return;
    }

    const cartArray = Object.values(cart);
    if (cartArray.length === 0) return;

    const payload = {
        customer_name: customerName,
        phone,
        delivery_address: address,
        payment_method: paymentMethod,
        items: cartArray.map(item => ({ item_id: item.item_id, quantity: item.quantity })),
    };

    try {
        const response = await fetch('api/place_order.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const result = await response.json();

        if (!result.success) {
            messageEl.textContent = result.message || 'Đặt hàng thất bại.';
            messageEl.className = 'error-text';
            return;
        }

        messageEl.textContent = `Đặt hàng thành công! Mã đơn: #${result.order_id}`;
        messageEl.className = 'success-text';
        saveOrderIdToLocalStorage(result.order_id);

        // Cập nhật ngay section "Đơn hàng của tôi" (track-order.js) nếu có trên trang,
        // thay vì đợi chu kỳ polling 8s tiếp theo mới thấy đơn vừa đặt.
        if (typeof refreshTrackedOrders === 'function') {
            refreshTrackedOrders();
        }

        cart = {};
        renderCart();
        phoneInput.value = '';
        addressInput.value = '';

        if (paymentMethod === 'qr') {
            showQrPayment(result.order_id, result.total_price);
        }
    } catch (err) {
        console.error('Lỗi khi đặt hàng:', err);
        messageEl.textContent = 'Lỗi kết nối đến server.';
        messageEl.className = 'error-text';
    }
}

// Lưu order_id vào localStorage để trang track-order.php đọc lại được sau này
// (key STORAGE_KEY phải khớp với hằng số dùng trong js/track-order.js)
const GUEST_ORDERS_STORAGE_KEY = 'taolao_guest_orders';
function saveOrderIdToLocalStorage(orderId) {
    try {
        const raw = localStorage.getItem(GUEST_ORDERS_STORAGE_KEY);
        const savedIds = raw ? JSON.parse(raw) : [];
        savedIds.push(orderId);
        // Giới hạn tối đa 30 đơn gần nhất để localStorage không phình to
        const trimmed = [...new Set(savedIds)].slice(-30);
        localStorage.setItem(GUEST_ORDERS_STORAGE_KEY, JSON.stringify(trimmed));
    } catch (err) {
        console.error('Lỗi khi lưu order_id vào localStorage:', err);
        // Không chặn luồng đặt hàng nếu localStorage lỗi (vd: private browsing)
    }
}

// Hiển thị QR mock cho đơn hàng vừa tạo
function showQrPayment(orderId, totalPrice) {
    const box = document.getElementById('qr-payment-box');
    const canvasEl = document.getElementById('qrcode-canvas');
    document.getElementById('qr-order-id').textContent = `#${orderId}`;
    canvasEl.innerHTML = '';

    // Payload QR mang tính minh hoạ (KHÔNG phải chuẩn VietQR ngân hàng thật)
    const qrPayload = `TAOLAO-PAY|order=${orderId}|amount=${totalPrice}`;
    new QRCode(canvasEl, { text: qrPayload, width: 180, height: 180 });

    box.classList.remove('hidden');

    document.getElementById('confirm-qr-btn').onclick = async () => {
        try {
            const res = await fetch('api/confirm_qr_payment.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ order_id: orderId }),
            });
            const result = await res.json();
            if (result.success) {
                box.innerHTML = '<p class="success-text">✅ Đã xác nhận thanh toán!</p>';
            } else {
                alert(result.message || 'Không thể xác nhận thanh toán.');
            }
        } catch (err) {
            alert('Lỗi kết nối đến server.');
        }
    };
}

// ---------- HELPER FUNCTIONS ----------
function formatCurrency(num) {
    return new Intl.NumberFormat('vi-VN').format(num);
}

// Escape HTML để chống DOM-based XSS khi render dữ liệu vào innerHTML
function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// ---------- INIT ----------
document.addEventListener('DOMContentLoaded', () => {
    loadMenu();
    loadCategoriesForFilter();
    document.getElementById('checkout-btn').addEventListener('click', handleCheckout);

    // Debounce ô tìm kiếm 300ms - tránh gọi API mỗi lần gõ 1 ký tự (mỗi lần gõ
    // xong 1 từ mới gọi, không phải mỗi phím bấm), giảm tải server đáng kể.
    let searchDebounceTimer = null;
    document.getElementById('menu-search').addEventListener('input', () => {
        clearTimeout(searchDebounceTimer);
        searchDebounceTimer = setTimeout(applyMenuFilters, 300);
    });

    // Đổi danh mục thì lọc lại ngay, không cần debounce (chỉ đổi khi click, không gõ liên tục)
    document.getElementById('menu-category-filter').addEventListener('change', applyMenuFilters);
});