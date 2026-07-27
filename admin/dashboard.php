<?php
// admin/dashboard.php
// Module 2: Restaurant Management Dashboard
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_role('admin', 'login.php');
$currentAdmin = current_user();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TaoLao Food - Dashboard Quản lý Đơn hàng</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="dashboard.css">
</head>
<body class="dashboard-body">

    <header class="dashboard-header">
        <h1> Dashboard Nhà hàng</h1>
        <div class="header-actions">
            <span id="last-updated" class="last-updated-text"></span>
            <button id="refresh-btn" class="refresh-btn">🔄 Làm mới</button>
            <a href="logout.php" class="logout-link">Đăng xuất (<?= htmlspecialchars($currentAdmin['username'], ENT_QUOTES, 'UTF-8') ?>)</a>
        </div>
    </header>

    <main class="dashboard-container">
        <table class="orders-table">
            <thead>
                <tr>
                    <th>Mã đơn</th>
                    <th>Khách hàng</th>
                    <th>Tổng tiền</th>
                    <th>Trạng thái</th>
                    <th>Thời gian</th>
                    <th>Tài xế</th>
                    <th>Hành động</th>
                </tr>
            </thead>
            <tbody id="orders-tbody">
                <tr><td colspan="7" class="loading-row">Đang tải đơn hàng...</td></tr>
            </tbody>
        </table>
    </main>

    <script src="../js/dashboard.js"></script>
</body>
</html>