<?php
// admin/update_status.php
// Endpoint: POST - Cập nhật trạng thái đơn hàng theo đúng luồng FSM
// Đáp ứng REQ-2.3 (Status Update)

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_role_api('admin');

// Ensure $pdo is available. If config.php didn't provide it, try to create using config constants.
if (!isset($pdo) || !($pdo instanceof PDO)) {
    if (defined('DB_DSN') && defined('DB_USER') && defined('DB_PASS')) {
        try {
            $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $e) {
            error_log($e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Lỗi kết nối CSDL.']);
            exit;
        }
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Cấu hình CSDL không tồn tại.']);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// ---------- ĐỊNH NGHĨA FSM: các trạng thái hợp lệ & transition được phép ----------
// Đã cập nhật theo Module 3 (Delivery Tracking): thêm 2 trạng thái
// 'Out for Delivery' và 'Delivered', thay cho 'Done' cũ.
// Key = trạng thái hiện tại, Value = danh sách trạng thái được phép chuyển tới.
const VALID_TRANSITIONS = [
    'Pending'          => ['Preparing'],
    'Preparing'        => ['Out for Delivery'],
    'Out for Delivery' => ['Delivered'],
    'Delivered'        => [], // trạng thái kết thúc (terminal state) - không có transition đi ra
];

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

if (json_last_error() !== JSON_ERROR_NONE || !is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ.']);
    exit;
}

$orderId = filter_var($input['order_id'] ?? null, FILTER_VALIDATE_INT);
$newStatus = isset($input['new_status']) ? trim((string) $input['new_status']) : '';

if ($orderId === false || $orderId === null) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Mã đơn hàng không hợp lệ.']);
    exit;
}

if (!array_key_exists($newStatus, VALID_TRANSITIONS)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Trạng thái không tồn tại trong hệ thống.']);
    exit;
}

try {
    // Bước 1: Lấy trạng thái HIỆN TẠI từ DB (không tin trạng thái cũ mà client tự gửi lên)
    $stmtGet = $pdo->prepare("SELECT status FROM orders WHERE order_id = :order_id");
    $stmtGet->execute(['order_id' => $orderId]);
    $order = $stmtGet->fetch();

    if (!$order) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy đơn hàng.']);
        exit;
    }

    $currentStatus = $order['status'];

    // Bước 2: Kiểm tra transition có hợp lệ theo FSM không
    // (Đây là "guard condition" đơn giản nhất của FSM: so sánh trạng thái hiện tại
    //  với bảng transition cho phép. Nếu làm eFSM, đây là chỗ thêm điều kiện phụ,
    //  VD: kiểm tra thêm biến payment_status trước khi cho phép Preparing -> Out for Delivery.)
    $allowedNextStates = VALID_TRANSITIONS[$currentStatus] ?? [];

    if (!in_array($newStatus, $allowedNextStates, true)) {
        http_response_code(409); // Conflict - trái với business rule
        echo json_encode([
            'success' => false,
            'message' => "Không thể chuyển từ '{$currentStatus}' sang '{$newStatus}'. " .
                         "Trạng thái hợp lệ tiếp theo: " . (empty($allowedNextStates) ? 'Không có (đã kết thúc)' : implode(', ', $allowedNextStates)),
        ]);
        exit;
    }

    // Bước 3: Thực hiện update
    // Từ đây trở đi là phần eFSM thật sự: một số transition có GUARD CONDITION
    // (điều kiện phụ ngoài bảng transition) và SIDE EFFECT (thay đổi thêm dữ liệu
    // liên quan) chứ không chỉ đơn thuần đổi 1 cột status.
    $pdo->beginTransaction();

    if ($newStatus === 'Out for Delivery') {
        // GUARD CONDITION: chỉ cho phép Preparing -> Out for Delivery nếu có
        // driver đang rảnh. Nếu không, transition bị CHẶN dù bảng FSM cho phép về mặt lý thuyết.
        // FOR UPDATE khóa dòng driver này lại, tránh 2 admin bấm cùng lúc gán trùng 1 driver
        // cho 2 đơn khác nhau (race condition).
        $driverStmt = $pdo->prepare("SELECT driver_id FROM drivers WHERE status = 'Idle' LIMIT 1 FOR UPDATE");
        $driverStmt->execute();
        $driver = $driverStmt->fetch();

        if (!$driver) {
            $pdo->rollBack();
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'message' => 'Không thể chuyển sang Out for Delivery: không còn tài xế rảnh.',
            ]);
            exit;
        }

        // SIDE EFFECT: tạo bản ghi tracking cho Module 3 (Delivery Tracking Simulation)
        $pdo->prepare("
            INSERT INTO delivery_tracking
                (order_id, driver_id, origin_x, origin_y, destination_x, destination_y,
                 start_time, estimated_duration_seconds, status)
            VALUES
                (:oid, :did, 10, 10, 90, 90, NOW(), 60, 'Assigned')
        ")->execute(['oid' => $orderId, 'did' => $driver['driver_id']]);

        $pdo->prepare("UPDATE drivers SET status = 'Assigned' WHERE driver_id = :id")
            ->execute(['id' => $driver['driver_id']]);
    }

    if ($newStatus === 'Delivered') {
        // SIDE EFFECT: cho phép admin override thủ công (VD: driver báo giao xong
        // ngoài đời nhưng canvas mô phỏng chưa kịp chạy hết 60 giây). Đồng bộ lại
        // delivery_tracking và trả driver về Idle để không bị "kẹt" ở Assigned.
        $trackStmt = $pdo->prepare("SELECT driver_id FROM delivery_tracking WHERE order_id = :oid");
        $trackStmt->execute(['oid' => $orderId]);
        $track = $trackStmt->fetch();

        if ($track) {
            $pdo->prepare("UPDATE delivery_tracking SET status = 'Arrived' WHERE order_id = :oid")
                ->execute(['oid' => $orderId]);
            $pdo->prepare("UPDATE drivers SET status = 'Idle' WHERE driver_id = :id")
                ->execute(['id' => $track['driver_id']]);
        }
    }

    $stmtUpdate = $pdo->prepare(
        "UPDATE orders SET status = :new_status WHERE order_id = :order_id"
    );
    $stmtUpdate->execute([
        'new_status' => $newStatus,
        'order_id'   => $orderId,
    ]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'order_id' => $orderId,
        'old_status' => $currentStatus,
        'new_status' => $newStatus,
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Lỗi khi cập nhật trạng thái.']);
}