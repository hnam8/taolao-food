<?php
// index.php
// Module 1: Customer Order Placement - Trang chính
declare(strict_types=1);
require_once __DIR__ . '/includes/auth_guard.php';
init_session();
$loggedInUser = current_user();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TaoLao Food - Đặt món trực tuyến</title>
    <link rel="stylesheet" href="css/style.css?v=1.1">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
</head>
<body>

    <header class="site-header">
        <h1>🍜 TaoLao Food</h1>
        <p>Đặt món ngon, giao tận nơi</p>
        <div class="auth-status-bar">
            <?php if ($loggedInUser): ?>
                Xin chào, <strong><?= htmlspecialchars($loggedInUser['username'], ENT_QUOTES, 'UTF-8') ?></strong> |
                <a href="logout.php">Đăng xuất</a>
            <?php else: ?>
                <a href="login.php">Đăng nhập</a> | <a href="register.php">Đăng ký</a>
            <?php endif; ?>
</div>
    </header>

    <main class="container">
        <!-- Danh sách món ăn sẽ được JS render vào đây sau khi fetch từ get_menu.php -->
        <section id="menu-section">
            <h2>Thực đơn hôm nay</h2>
            <div id="menu-list" class="menu-grid">
                <p class="loading-text">Đang tải thực đơn...</p>
            </div>
        </section>

        <!-- Giỏ hàng - quản lý bằng JS client-side (REQ-1.2) -->
        <aside id="cart-section" class="cart-box">
            <h2>Giỏ hàng của bạn</h2>
            <div id="cart-items">
                <p class="empty-cart-text">Giỏ hàng đang trống.</p>
            </div>
            <div class="cart-total">
                <strong>Tổng cộng: <span id="cart-total-amount">0</span> đ</strong>
            </div>

            <!-- Form nhập tên khách (guest checkout) -->
            <div class="checkout-form">
                <label for="customer-name">Tên khách hàng:</label>
                <input type="text" id="customer-name" placeholder="Nhập tên của bạn" maxlength="100"
                    value="<?= $loggedInUser ? htmlspecialchars($loggedInUser['username'], ENT_QUOTES, 'UTF-8') : '' ?>"
                    <?= $loggedInUser ? 'readonly' : '' ?>>

                <label for="customer-phone">Số điện thoại:</label>
                <input type="tel" id="customer-phone" placeholder="09xxxxxxxx" maxlength="15"
                    pattern="^(0|\+84)[0-9]{9,10}$" required>

                <label for="customer-address">Địa chỉ giao hàng:</label>
                <textarea id="customer-address" placeholder="Số nhà, đường, phường/xã, quận/huyện" maxlength="255" rows="2" required></textarea>

                <label>Phương thức thanh toán:</label>
                <div class="payment-methods">
                    <label class="payment-option">
                        <input type="radio" name="payment_method" value="cash" checked>
                        <span> Tiền mặt khi nhận hàng</span>
                    </label>
                    <label class="payment-option">
                        <input type="radio" name="payment_method" value="wallet" <?= !$loggedInUser ? 'disabled' : '' ?>>
                        <span> Ví TaoLao <?php if ($loggedInUser): ?>(<a href="wallet.php">nạp tiền</a>)<?php else: ?>(cần đăng nhập)<?php endif; ?></span>
                    </label>
                    <label class="payment-option">
                        <input type="radio" name="payment_method" value="qr">
                        <span> Quét mã QR chuyển khoản</span>
                    </label>
                </div>

                <button id="checkout-btn" disabled>Đặt hàng</button>
            </div>
            <p id="order-message"></p>
            <div id="qr-payment-box" class="qr-payment-box hidden">
                <p>Quét mã QR để thanh toán đơn <strong id="qr-order-id"></strong>:</p>
                <div id="qrcode-canvas"></div>
                <p class="mock-note">⚠️ Mã QR mô phỏng cho mục đích học tập, không kết nối ngân hàng thật.</p>
                <button id="confirm-qr-btn">Tôi đã thanh toán</button>
            </div>
        </aside>
    </main>

    <footer class="site-footer">
    <p>&copy; 2026 TaoLao Food - BTEC Unit 7 Prototype</p>
    <p><a href="admin/dashboard.php" style="color: var(--color-amber);">→ Trang quản lý nhà hàng (Admin)</a></p>
</footer>

    <script src="js/cart.js"></script>
</body>
</html>