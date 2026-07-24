<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

// Bạn có thể tùy chỉnh Tên đăng nhập và Mật khẩu ở đây
$admin_username = 'admin'; 
$admin_password = '123456'; 

// Băm mật khẩu để đảm bảo tiêu chuẩn bảo mật NFR-1
$hashedPassword = password_hash($admin_password, PASSWORD_BCRYPT);

try {
    // 1. Dọn dẹp tài khoản admin cũ hoặc username trùng lặp nếu có
    $stmtDelete = $pdo->prepare("DELETE FROM users WHERE role = 'admin' OR username = :username");
    $stmtDelete->execute(['username' => $admin_username]);

    // 2. Tạo mới tài khoản Admin chuẩn
    $stmtInsert = $pdo->prepare(
        "INSERT INTO users (username, password, role) VALUES (:username, :password, 'admin')"
    );
    $stmtInsert->execute([
        'username' => $admin_username,
        'password' => $hashedPassword
    ]);

    echo "<div style='font-family: sans-serif; padding: 20px; border: 1px solid #4CAF50; background-color: #e8f5e9; border-radius: 8px;'>";
    echo "<h3 style='color: #2e7d32; margin-top: 0;'>Đã khởi tạo THÀNH CÔNG tài khoản Admin mới!</h3>";
    echo "<p>Tên đăng nhập: <b>" . htmlspecialchars($admin_username) . "</b></p>";
    echo "<p>Mật khẩu: <b>" . htmlspecialchars($admin_password) . "</b></p>";
    echo "<p style='color: #c62828;'><i>Lưu ý: Hãy xóa file <b>reset_admin.php</b> sau khi đăng nhập thành công.</i></p>";
    echo "</div>";

} catch (PDOException $e) {
    echo "<h3 style='color: red;'>Lỗi truy vấn Database:</h3> " . $e->getMessage();
}
?>