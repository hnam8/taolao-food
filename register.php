<?php
// register.php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth_guard.php';
init_session();

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if (strlen($username) < 3) {
        $errors[] = 'Tên đăng nhập phải có ít nhất 3 ký tự.';
    }
    if (strlen($password) < 6) {
        $errors[] = 'Mật khẩu phải có ít nhất 6 ký tự.';
    }
    if ($password !== $confirmPassword) {
        $errors[] = 'Mật khẩu xác nhận không khớp.';
    }

    if (empty($errors)) {
        // Kiểm tra username đã tồn tại chưa
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE username = :username");
        $stmt->execute(['username' => $username]);
        if ($stmt->fetch()) {
            $errors[] = 'Tên đăng nhập đã được sử dụng.';
        } else {
            // Hash mật khẩu - KHÔNG BAO GIỜ lưu plain text (NFR-1)
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

            $stmt = $pdo->prepare(
                "INSERT INTO users (username, password, role) VALUES (:username, :password, 'customer')"
            );
            $stmt->execute(['username' => $username, 'password' => $hashedPassword]);
            $success = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng ký - TaoLao Food</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/auth.css">
</head>
<body class="auth-body">
    <div class="auth-box">
        <h1>Đăng ký tài khoản</h1>

        <?php if ($success): ?>
            <p class="success-text">Đăng ký thành công! <a href="login.php">Đăng nhập ngay</a>.</p>
        <?php else: ?>
            <?php if (!empty($errors)): ?>
                <ul class="error-list">
                    <?php foreach ($errors as $err): ?>
                        <li><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <form method="POST" class="auth-form">
                <label for="username">Tên đăng nhập</label>
                <input type="text" id="username" name="username" required minlength="3"
                       value="<?= htmlspecialchars($username ?? '', ENT_QUOTES, 'UTF-8') ?>">

                <label for="password">Mật khẩu</label>
                <input type="password" id="password" name="password" required minlength="6">

                <label for="confirm_password">Xác nhận mật khẩu</label>
                <input type="password" id="confirm_password" name="confirm_password" required minlength="6">

                <button type="submit">Đăng ký</button>
            </form>
            <p class="auth-switch">Đã có tài khoản? <a href="login.php">Đăng nhập</a></p>
        <?php endif; ?>
    </div>
</body>
</html>