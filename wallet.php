<?php
// wallet.php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth_guard.php';
// Ensure $pdo is available. If config.php didn't provide it, try to create one from known constants.
if (!isset($pdo) || !$pdo instanceof PDO) {
    try {
        if (defined('DB_DSN')) {
            $user = defined('DB_USER') ? DB_USER : null;
            $pass = defined('DB_PASS') ? DB_PASS : null;
            $opts = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
            $pdo = new PDO(DB_DSN, $user, $pass, $opts);
        } elseif (defined('DB_HOST') && defined('DB_NAME')) {
            $dbuser = defined('DB_USER') ? DB_USER : '';
            $dbpass = defined('DB_PASS') ? DB_PASS : '';
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            $pdo = new PDO($dsn, $dbuser, $dbpass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        }
    } catch (Exception $e) {
        // If PDO cannot be established, stop to avoid undefined variable usage.
        http_response_code(500);
        echo 'Database connection error.';
        exit;
    }
}
// Ensure a session helper exists (some environments may not load init_session)
if (!function_exists('init_session')) {
    function init_session(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
    }
}

init_session();
// Some environments may not load require_role from auth_guard.php correctly.
// Define a lightweight fallback to avoid "Undefined function 'require_role'".
if (!function_exists('require_role')) {
    function require_role(string $role, string $redirect): void
    {
        // Ensure session is started
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        // Try to use current_user() if available
        if (function_exists('current_user')) {
            $user = current_user();
        } else {
            $user = $_SESSION['user'] ?? null;
        }

        if (empty($user) || (isset($user['role']) && $user['role'] !== $role) || (isset($user['role']) === false && $role !== '')) {
            header('Location: ' . $redirect);
            exit;
        }
    }
}

// Provide a simple current_user() fallback if not defined by auth_guard.php
if (!function_exists('current_user')) {
    function current_user()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        return $_SESSION['user'] ?? null;
    }
}

require_role('customer', 'login.php');
$user = current_user();

// Lấy số dư mới nhất trực tiếp từ DB (không dùng số cũ trong session, tránh hiển thị sai)
if (!isset($pdo) || !$pdo instanceof PDO) {
    http_response_code(500);
    echo 'Database connection error.';
    exit;
}
$stmt = $pdo->prepare("SELECT wallet_balance FROM users WHERE user_id = :id");
$stmt->execute(['id' => $user['user_id']]);
$balance = (float) $stmt->fetchColumn();

// Lấy lịch sử giao dịch gần nhất
$stmtHistory = $pdo->prepare(
    "SELECT amount, type, created_at FROM wallet_transactions 
     WHERE user_id = :id ORDER BY created_at DESC LIMIT 10"
);
$stmtHistory->execute(['id' => $user['user_id']]);
$history = $stmtHistory->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ví TaoLao - TaoLao Food</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/auth.css">
    <link rel="stylesheet" href="css/payment.css">
</head>
<body class="auth-body">
    <div class="auth-box wallet-box">
        <h1> Ví TaoLao</h1>
        <p class="wallet-balance">Số dư: <strong><?= number_format($balance, 0, ',', '.') ?> đ</strong></p>

        <form id="topup-form" class="auth-form">
            <label for="topup-amount">Nạp tiền vào ví</label>
            <input type="number" id="topup-amount" min="10000" step="10000" placeholder="VD: 100000" required>
            <p class="mock-note">⚠️ Đây là ví mô phỏng cho mục đích học tập — số tiền được cộng trực tiếp, không qua cổng thanh toán thật.</p>
            <button type="submit">Nạp tiền (mock)</button>
        </form>
        <p id="topup-message"></p>

        <h2 class="wallet-history-title">Lịch sử giao dịch</h2>
        <ul class="wallet-history-list">
            <?php if (empty($history)): ?>
                <li class="empty-history">Chưa có giao dịch nào.</li>
            <?php endif; ?>
            <?php foreach ($history as $tx): ?>
                <li>
                    <span><?= $tx['type'] === 'topup' ? 'Nạp tiền' : ($tx['type'] === 'payment' ? 'Thanh toán đơn' : 'Hoàn tiền') ?></span>
                    <span class="<?= $tx['amount'] >= 0 ? 'amount-plus' : 'amount-minus' ?>">
                        <?= ($tx['amount'] >= 0 ? '+' : '') . number_format((float)$tx['amount'], 0, ',', '.') ?> đ
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
        <p class="auth-switch"><a href="index.php">← Quay lại đặt món</a></p>
    </div>
    <script src="js/wallet.js"></script>
</body>
</html>