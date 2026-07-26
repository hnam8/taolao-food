<?php
// api/confirm_qr_payment.php
// MOCK: Trong hệ thống thật, endpoint này KHÔNG được gọi trực tiếp từ client.
// Nó phải là 1 webhook được cổng thanh toán (VNPay/Momo...) gọi tới sau khi
// xác thực giao dịch bằng chữ ký số (signature) riêng của ngân hàng.
// Ở đây mô phỏng bằng cách để khách hàng tự bấm "Tôi đã thanh toán" sau khi quét QR.

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config.php';

$input = json_decode(file_get_contents('php://input'), true);
$orderId = filter_var($input['order_id'] ?? null, FILTER_VALIDATE_INT);

if (!$orderId) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Mã đơn hàng không hợp lệ.']);
    exit;
}

try {
    $stmt = $pdo->prepare(
        "UPDATE orders SET payment_status = 'paid' 
         WHERE order_id = :id AND payment_method = 'qr' AND payment_status = 'unpaid'"
    );
    $stmt->execute(['id' => $orderId]);

    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Đơn hàng không tồn tại hoặc đã được thanh toán trước đó.']);
        exit;
    }

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Lỗi khi xác nhận thanh toán.']);
    exit;
}