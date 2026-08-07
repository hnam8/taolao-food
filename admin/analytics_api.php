<?php
/**
 * admin/analytics_api.php
 * Đáp ứng REQ-5.1 -> REQ-5.4.
 * GET -> trả về toàn bộ dữ liệu tổng hợp cho 4 biểu đồ trong 1 lần gọi
 * (không chia action như CRUD, vì đây là read-only, gộp lại giảm số round-trip).
 *
 * QUY ƯỚC: doanh thu (revenue_by_day, average_order_value, total_revenue) chỉ
 * tính trên đơn có status = 'Delivered' - đơn Pending/Preparing/Out for Delivery
 * chưa thực sự "thu tiền xong" nên không nên tính vào doanh thu đã ghi nhận.
 * Riêng status_distribution vẫn đếm TẤT CẢ đơn, vì mục đích là cho biết bức
 * tranh vận hành hiện tại (bao nhiêu đơn đang ở mỗi trạng thái).
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_role_api('admin');

const REVENUE_DAYS_RANGE = 14; // hiển thị 14 ngày gần nhất

try {
    // ---------- 1. Doanh thu theo ngày (REQ-5.1) ----------
    $stmt = $pdo->prepare("
        SELECT DATE(created_at) AS day, SUM(total_price) AS revenue, COUNT(*) AS order_count
        FROM orders
        WHERE status = 'Delivered' AND created_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
        GROUP BY DATE(created_at)
        ORDER BY day ASC
    ");
    $stmt->bindValue(':days', REVENUE_DAYS_RANGE - 1, PDO::PARAM_INT);
    $stmt->execute();
    $revenueRows = $stmt->fetchAll();

    // Gộp dữ liệu SQL vào 1 dải ngày liên tục, đảm bảo ngày không có đơn vẫn
    // hiện là 0đ thay vì bị thiếu hẳn trên trục X của biểu đồ (tránh biểu đồ
    // gây hiểu lầm là các ngày liền kề nhau trong khi thực ra bị nhảy cóc).
    $revenueByDate = [];
    foreach ($revenueRows as $row) {
        $revenueByDate[$row['day']] = (float)$row['revenue'];
    }
    $revenueByDay = [];
    for ($i = REVENUE_DAYS_RANGE - 1; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-{$i} days"));
        $revenueByDay[] = ['date' => $date, 'revenue' => $revenueByDate[$date] ?? 0.0];
    }

    // ---------- 2. Top món bán chạy (REQ-5.2) ----------
    // Dùng item_name_snapshot (không JOIN menu_items) để không bị lệch nếu món
    // đã bị đổi tên/xóa sau này - đúng nguyên tắc snapshot pattern đã áp dụng.
    $stmt = $pdo->query("
        SELECT od.item_name_snapshot AS item_name, SUM(od.quantity) AS total_qty
        FROM order_details od
        JOIN orders o ON od.order_id = o.order_id
        WHERE o.status = 'Delivered'
        GROUP BY od.item_name_snapshot
        ORDER BY total_qty DESC
        LIMIT 5
    ");
    $topItems = array_map(function ($row) {
        return ['item_name' => $row['item_name'], 'total_qty' => (int)$row['total_qty']];
    }, $stmt->fetchAll());

    // ---------- 3. Tỷ lệ trạng thái đơn hàng (REQ-5.3) ----------
    $stmt = $pdo->query("SELECT status, COUNT(*) AS count FROM orders GROUP BY status");
    $statusDistribution = array_map(function ($row) {
        return ['status' => $row['status'], 'count' => (int)$row['count']];
    }, $stmt->fetchAll());

    // ---------- 4. Giá trị đơn hàng trung bình + tổng quan (REQ-5.4) ----------
    $stmt = $pdo->query("
        SELECT COALESCE(AVG(total_price), 0) AS aov,
               COALESCE(SUM(total_price), 0) AS total_revenue,
               COUNT(*) AS total_orders
        FROM orders
        WHERE status = 'Delivered'
    ");
    $summary = $stmt->fetch();

    echo json_encode([
        'success' => true,
        'data' => [
            'revenue_by_day'     => $revenueByDay,
            'top_items'          => $topItems,
            'status_distribution' => $statusDistribution,
            'average_order_value' => round((float)$summary['aov']),
            'total_revenue'       => round((float)$summary['total_revenue']),
            'total_orders'        => (int)$summary['total_orders'],
        ],
    ]);
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Lỗi khi tổng hợp dữ liệu.']);
}