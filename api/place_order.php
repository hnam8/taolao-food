<?php
// api/place_order.php
// Endpoint: POST - Nhận đơn hàng từ Fetch API và ghi vào database
// Đáp ứng REQ-1.3 (Checkout Process) + NFR-1 (Security) + NFR-4 (Data Integrity)
// Đã tích hợp: thông tin giao hàng (phone/address) + Payment Integration (cash/wallet/qr)

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth_guard.php';
init_session();
$loggedInUser = current_user();
$userId = $loggedInUser['user_id'] ?? null;

// Chỉ chấp nhận method POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// ---------- 1. ĐỌC & VALIDATE INPUT ----------
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

if (json_last_error() !== JSON_ERROR_NONE || !is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dữ liệu gửi lên không hợp lệ.']);
    exit;
}

// --- Sanitize customer_name (NFR-1: chống XSS) ---
$customerName = isset($input['customer_name']) ? trim((string) $input['customer_name']) : '';
$customerName = htmlspecialchars($customerName, ENT_QUOTES, 'UTF-8');

if ($customerName === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Tên khách hàng không được để trống.']);
    exit;
}

// --- Sanitize & validate phone (Order Placement Form) ---
$phone = isset($input['phone']) ? trim((string) $input['phone']) : '';

if (!preg_match('/^(0|\+84)[0-9]{9,10}$/', $phone)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Số điện thoại không hợp lệ.']);
    exit;
}

// --- Sanitize & validate delivery_address (Order Placement Form) ---
$deliveryAddress = isset($input['delivery_address']) ? trim((string) $input['delivery_address']) : '';
$deliveryAddress = htmlspecialchars($deliveryAddress, ENT_QUOTES, 'UTF-8');

if (mb_strlen($deliveryAddress) < 5) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Địa chỉ giao hàng không hợp lệ.']);
    exit;
}

// --- Validate payment_method (Payment Integration) ---
$paymentMethod = isset($input['payment_method']) ? trim((string) $input['payment_method']) : 'cash';

if (!in_array($paymentMethod, ['cash', 'wallet', 'qr'], true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Phương thức thanh toán không hợp lệ.']);
    exit;
}

// Thanh toán bằng ví BẮT BUỘC phải đăng nhập
if ($paymentMethod === 'wallet' && !$userId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập để thanh toán bằng Ví TaoLao.']);
    exit;
}

// --- Validate giỏ hàng ---
$items = $input['items'] ?? null;

if (!is_array($items) || count($items) === 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Giỏ hàng đang trống.']);
    exit;
}

// Validate cấu trúc từng item trong giỏ hàng
$cleanItems = [];
foreach ($items as $item) {
    if (!isset($item['item_id'], $item['quantity'])) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Dữ liệu món ăn không hợp lệ.']);
        exit;
    }

    $itemId = filter_var($item['item_id'], FILTER_VALIDATE_INT);
    $quantity = filter_var($item['quantity'], FILTER_VALIDATE_INT);

    if ($itemId === false || $quantity === false || $quantity <= 0) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Số lượng hoặc mã món ăn không hợp lệ.']);
        exit;
    }

    $cleanItems[] = ['item_id' => $itemId, 'quantity' => $quantity];
}

// ---------- 2. XỬ LÝ TRONG TRANSACTION ----------
// Lý do dùng transaction: phải đảm bảo "orders", "order_details", và (nếu có) trừ ví
// được ghi ĐỒNG THỜI. Nếu 1 bước lỗi giữa chừng, phải rollback toàn bộ, tránh:
// (a) order "mồ côi" không có món ăn bên trong, hoặc
// (b) trừ tiền ví nhưng đơn hàng không được tạo.
try {
    $pdo->beginTransaction();

    // Bước A: Lấy giá THẬT từ DB cho từng item (KHÔNG tin giá client gửi lên)
    // Đây là điểm bảo mật quan trọng: client có thể sửa giá trong Fetch payload,
    // nên server luôn phải tự truy vấn giá gốc, không bao giờ nhận price từ front-end.
    $stmtGetItem = $pdo->prepare(
        "SELECT item_id, item_name, price 
         FROM menu_items 
         WHERE item_id = :item_id AND is_available = 1"
    );

    $orderItemsToInsert = [];
    $totalPrice = 0.0;

    foreach ($cleanItems as $item) {
        $stmtGetItem->execute(['item_id' => $item['item_id']]);
        $menuItem = $stmtGetItem->fetch();

        if (!$menuItem) {
            // Món ăn không tồn tại hoặc đã ngừng bán (is_available = 0)
            throw new RuntimeException("Món ăn ID {$item['item_id']} không tồn tại hoặc đã ngừng bán.");
        }

        $subtotal = $menuItem['price'] * $item['quantity'];
        $totalPrice += $subtotal;

        // Snapshot pattern: lưu lại tên món + giá TẠI THỜI ĐIỂM đặt hàng
        $orderItemsToInsert[] = [
            'item_id'            => $menuItem['item_id'],
            'item_name_snapshot' => $menuItem['item_name'],
            'quantity'           => $item['quantity'],
            'unit_price'         => $menuItem['price'],
        ];
    }

    // Bước B: Nếu thanh toán bằng ví -> kiểm tra & trừ tiền TRƯỚC khi tạo đơn,
    // trong CÙNG 1 transaction với việc tạo đơn.
    $paymentStatus = 'unpaid';

    if ($paymentMethod === 'wallet') {
        // FOR UPDATE: khoá dòng user lại trong lúc transaction chạy, tránh race condition
        // (VD: user bấm đặt hàng 2 lần liên tiếp rất nhanh, cả 2 request cùng đọc
        // một số dư ban đầu và cùng pass qua điều kiện đủ tiền, dẫn tới trừ ví 2 lần).
        $stmtBalance = $pdo->prepare("SELECT wallet_balance FROM users WHERE user_id = :id FOR UPDATE");
        $stmtBalance->execute(['id' => $userId]);
        $currentBalance = (float) $stmtBalance->fetchColumn();

        if ($currentBalance < $totalPrice) {
            throw new RuntimeException('Số dư ví không đủ. Vui lòng nạp thêm tiền hoặc chọn phương thức khác.');
        }

        $stmtDeduct = $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance - :amount WHERE user_id = :id");
        $stmtDeduct->execute(['amount' => $totalPrice, 'id' => $userId]);

        $paymentStatus = 'paid'; // trừ ví thành công ngay lập tức
    }

    // Bước C: Insert vào bảng orders (bảng cha)
    $stmtOrder = $pdo->prepare(
        "INSERT INTO orders (customer_name, phone, delivery_address, user_id, total_price, status, payment_method, payment_status) 
         VALUES (:customer_name, :phone, :delivery_address, :user_id, :total_price, 'Pending', :payment_method, :payment_status)"
    );
    $stmtOrder->execute([
        'customer_name'    => $customerName,
        'phone'            => $phone,
        'delivery_address' => $deliveryAddress,
        'user_id'          => $userId,   // NULL nếu là guest checkout - vẫn hợp lệ vì cột cho phép NULL
        'total_price'      => $totalPrice,
        'payment_method'   => $paymentMethod,
        'payment_status'   => $paymentStatus,
    ]);

    $orderId = (int) $pdo->lastInsertId();

    // Bước D: Ghi log giao dịch ví (nếu thanh toán bằng ví) - audit trail
    if ($paymentMethod === 'wallet') {
        $stmtLog = $pdo->prepare(
            "INSERT INTO wallet_transactions (user_id, order_id, amount, type) 
             VALUES (:user_id, :order_id, :amount, 'payment')"
        );
        $stmtLog->execute([
            'user_id'  => $userId,
            'order_id' => $orderId,
            'amount'   => -$totalPrice, // âm = tiền bị trừ khỏi ví
        ]);
    }

    // Bước E: Insert từng dòng vào order_details (bảng con)
    $stmtDetail = $pdo->prepare(
        "INSERT INTO order_details (order_id, item_id, item_name_snapshot, quantity, unit_price) 
         VALUES (:order_id, :item_id, :item_name_snapshot, :quantity, :unit_price)"
    );

    foreach ($orderItemsToInsert as $detail) {
        $stmtDetail->execute([
            'order_id'           => $orderId,
            'item_id'            => $detail['item_id'],
            'item_name_snapshot' => $detail['item_name_snapshot'],
            'quantity'           => $detail['quantity'],
            'unit_price'         => $detail['unit_price'],
        ]);
    }

    // Tất cả thành công -> commit transaction
    $pdo->commit();

    echo json_encode([
        'success'        => true,
        'order_id'       => $orderId,
        'total_price'    => $totalPrice,
        'payment_status' => $paymentStatus,
    ]);

} catch (RuntimeException $e) {
    // Lỗi nghiệp vụ (VD: món ăn không tồn tại, số dư ví không đủ) -> rollback, trả lỗi rõ ràng cho client
    $pdo->rollBack();
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);

} catch (PDOException $e) {
    // Lỗi hệ thống/DB -> rollback, KHÔNG lộ chi tiết lỗi ra client (bảo mật)
    $pdo->rollBack();
    error_log('place_order.php DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Có lỗi xảy ra khi xử lý đơn hàng. Vui lòng thử lại.']);
}