<?php
// admin/login.php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth_guard.php';
init_session();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    $stmt = $pdo->prepare("SELECT user_id, username, password, role FROM users WHERE username = :username AND role = 'admin'");
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user'] = [
            'user_id'  => $user['user_id'],
            'username' => $user['username'],
            'role'     => $user['role'],
        ];
        header('Location: dashboard.php');
        exit;
    }

    $error = 'Tên đăng nhập hoặc mật khẩu không đúng, hoặc tài khoản không có quyền admin.';
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập Admin - TaoLao Food</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/auth.css">
</head>
<body class="auth-body auth-body-admin">
    <div class="auth-box">
        <h1>🔐 Đăng nhập Quản trị</h1>
        <?php if ($error): ?>
            <p class="error-text-block"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>

        <form method="POST" class="auth-form">
            <label for="username">Tên đăng nhập</label>
            <input type="text" id="username" name="username" required>

            <label for="password">Mật khẩu</label>
            <input type="password" id="password" name="password" required>

            <button type="submit">Đăng nhập</button>
        </form>
    </div>
</body>
</html>