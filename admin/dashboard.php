<?php
/**
 * admin/dashboard.php
 * Dashboard Nhà hàng GỘP - thay thế hoàn toàn admin/menu.php và admin/analytics.php
 * (2 file đó đã bị XÓA - toàn bộ chức năng giờ nằm trong 4 tab của trang này).
 *
 * Tab 1: Đơn hàng      (Module 2 - REQ-2.x)
 * Tab 2: Tài xế        (Module 3 - bản đồ đa-tài-xế + bảng theo dõi)
 * Tab 3: Menu          (Module 4 - CRUD danh mục/món/ảnh)
 * Tab 4: Analytics     (Module 5 - Chart.js)
 */

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
    <title>TaoLao Food - Dashboard Quản lý</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="dashboard.css">
    <link rel="stylesheet" href="menu.css">
    <link rel="stylesheet" href="analytics.css">
    <!-- Chart.js self-host (KHÔNG dùng CDN - xem lý do ở lịch sử fix trước đó) -->
    <script src="../js/vendor/chart.umd.js"></script>
</head>
<body class="dashboard-body">

    <header class="dashboard-header">
        <h1>🍜 Dashboard Nhà hàng</h1>

        <nav class="tab-nav">
            <button class="tab-btn active" data-tab="orders">📦 Đơn hàng</button>
            <button class="tab-btn" data-tab="drivers">🛵 Tài xế</button>
            <button class="tab-btn" data-tab="menu">🍽️ Menu</button>
            <button class="tab-btn" data-tab="analytics">📊 Analytics</button>
        </nav>

        <div class="header-actions">
            <span id="last-updated" class="last-updated-text"></span>
            <button id="refresh-btn" class="refresh-btn">🔄 Làm mới</button>
            <a href="logout.php" class="logout-link">Đăng xuất (<?= htmlspecialchars($currentAdmin['username'], ENT_QUOTES, 'UTF-8') ?>)</a>
        </div>
    </header>

    <main class="dashboard-container">

        <!-- ================= TAB 1: ĐƠN HÀNG ================= -->
        <section id="tab-orders" class="tab-panel active">
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
        </section>

        <!-- ================= TAB 2: TÀI XẾ ================= -->
        <section id="tab-drivers" class="tab-panel">
            <div class="driver-tab-layout">
                <div class="chart-card">
                    <h2>Bản đồ mô phỏng - Tất cả tài xế đang hoạt động</h2>
                    <canvas id="driverMapCanvas" width="480" height="480"></canvas>
                </div>

                <div class="chart-card">
                    <h2>Theo dõi trạng thái tài xế</h2>
                    <table class="orders-table">
                        <thead>
                            <tr>
                                <th>Tài xế</th>
                                <th>Trạng thái giao hàng</th>
                                <th>Đơn đang hoạt động</th>
                            </tr>
                        </thead>
                        <tbody id="driver-tbody">
                            <tr><td colspan="3" class="loading-row">Đang tải...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- ================= TAB 3: MENU ================= -->
        <section id="tab-menu" class="tab-panel">
            <section class="category-section">
                <h2>Danh mục món ăn</h2>
                <div id="category-list" class="category-list">
                    <p class="loading-row">Đang tải danh mục...</p>
                </div>
                <form id="category-form" class="inline-form">
                    <input type="text" id="cat-name" placeholder="Tên danh mục mới" required maxlength="100">
                    <input type="number" id="cat-order" placeholder="Thứ tự" value="0" style="width:80px">
                    <button type="submit" class="action-btn">+ Thêm danh mục</button>
                </form>
            </section>

            <section class="menu-section">
                <div class="menu-toolbar">
                    <select id="filter-category">
                        <option value="">Tất cả danh mục</option>
                    </select>
                    <label class="checkbox-label">
                        <input type="checkbox" id="show-deleted">
                        Hiện món đã ẩn
                    </label>
                    <button id="add-item-btn" class="action-btn">+ Thêm món mới</button>
                </div>

                <table class="orders-table menu-table">
                    <thead>
                        <tr>
                            <th>Ảnh</th>
                            <th>Tên món</th>
                            <th>Danh mục</th>
                            <th>Giá</th>
                            <th>Trạng thái</th>
                            <th>Hành động</th>
                        </tr>
                    </thead>
                    <tbody id="menu-tbody">
                        <tr><td colspan="6" class="loading-row">Đang tải menu...</td></tr>
                    </tbody>
                </table>
            </section>
        </section>

        <!-- ================= TAB 4: ANALYTICS ================= -->
        <section id="tab-analytics" class="tab-panel">
            <section class="kpi-grid">
                <div class="kpi-card">
                    <span class="kpi-label">Tổng doanh thu (đơn đã giao)</span>
                    <span class="kpi-value" id="kpi-total-revenue">—</span>
                </div>
                <div class="kpi-card">
                    <span class="kpi-label">Tổng số đơn đã giao</span>
                    <span class="kpi-value" id="kpi-total-orders">—</span>
                </div>
                <div class="kpi-card">
                    <span class="kpi-label">Giá trị đơn hàng trung bình</span>
                    <span class="kpi-value" id="kpi-aov">—</span>
                </div>
            </section>

            <section class="chart-grid">
                <div class="chart-card chart-card-wide">
                    <h2>Doanh thu 14 ngày gần nhất</h2>
                    <canvas id="revenueChart"></canvas>
                </div>
                <div class="chart-card">
                    <h2>Top 5 món bán chạy</h2>
                    <canvas id="topItemsChart"></canvas>
                </div>
                <div class="chart-card">
                    <h2>Tỷ lệ trạng thái đơn hàng</h2>
                    <canvas id="statusChart"></canvas>
                </div>
            </section>

            <p id="analytics-error" class="error-row hidden"></p>
        </section>

    </main>

    <!-- ---------- FORM THÊM/SỬA MÓN (dùng chung cho tab Menu, ẩn mặc định) ---------- -->
    <div id="item-form-overlay" class="form-overlay hidden">
        <form id="item-form" class="item-form">
            <h2 id="item-form-title">Thêm món mới</h2>
            <input type="hidden" id="item-id" value="">

            <label>Tên món
                <input type="text" id="item-name" required maxlength="100">
            </label>
            <label>Mô tả
                <textarea id="item-description" rows="3" maxlength="500"></textarea>
            </label>
            <label>Giá (đ)
                <input type="number" id="item-price" min="0" step="1000" required>
            </label>
            <label>Danh mục
                <select id="item-category">
                    <option value="">-- Không thuộc danh mục --</option>
                </select>
            </label>
            <label>Ảnh món (JPG/PNG/WEBP, tối đa 2MB)
                <input type="file" id="item-image" accept="image/jpeg,image/png,image/webp">
            </label>
            <div id="item-image-preview" class="image-preview"></div>

            <div class="form-actions">
                <button type="submit" class="action-btn">Lưu</button>
                <button type="button" id="cancel-item-btn" class="secondary-btn">Hủy</button>
            </div>
        </form>
    </div>

    <script src="../js/dashboard.js"></script>
    <script src="../js/driver.js"></script>
    <script src="../js/menu.js"></script>
    <script src="../js/analytics.js"></script>
    <script src="../js/tabs.js"></script>
</body>
</html>