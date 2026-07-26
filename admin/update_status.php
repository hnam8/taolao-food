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
// Đây là bảng chuyển trạng thái (transition table) của FSM đơn giản.
// Key = trạng thái hiện tại, Value = danh sách trạng thái được phép chuyển tới.
const VALID_TRANSITIONS = [
    'Pending'   => ['Preparing'],
    'Preparing' => ['Done'],
    'Done'      => [], // trạng thái kết thúc (terminal state) - không có transition đi ra
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
    //  VD: kiểm tra thêm biến payment_status trước khi cho phép Preparing -> Done.)
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
    $stmtUpdate = $pdo->prepare(
        "UPDATE orders SET status = :new_status WHERE order_id = :order_id"
    );
    $stmtUpdate->execute([
        'new_status' => $newStatus,
        'order_id'   => $orderId,
    ]);

    echo json_encode([
        'success' => true,
        'order_id' => $orderId,
        'old_status' => $currentStatus,
        'new_status' => $newStatus,
    ]);

} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Lỗi khi cập nhật trạng thái.']);
}