<?php
// includes/auth_guard.php
// Các hàm kiểm tra session, dùng chung cho cả customer & admin pages

declare(strict_types=1);

function init_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start([
            'cookie_httponly' => true, // JS không đọc được cookie session -> chống XSS đánh cắp session
            'cookie_samesite' => 'Lax',
        ]);
    }
}

function current_user() {
    init_session();
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool {
    return current_user() !== null;
}

// Chặn truy cập nếu không đúng role, redirect về trang login tương ứng
function require_role(string $requiredRole, string $redirectTo): void {
    init_session();
    $user = current_user();
    if (!$user || $user['role'] !== $requiredRole) {
        header('Location: ' . $redirectTo);
        exit;
    }
}

// Dùng cho API endpoints (JSON) thay vì redirect - trả lỗi 401
function require_role_api(string $requiredRole): void {
    init_session();
    $user = current_user();
    if (!$user || $user['role'] !== $requiredRole) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Unauthorized. Vui lòng đăng nhập.']);
        exit;
    }
}