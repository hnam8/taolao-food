<?php
// api/wallet_topup.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth_guard.php';
init_session();
$user = current_user();
if (!$user || $user['role'] !== 'customer') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$amount = filter_var($input['amount'] ?? null, FILTER_VALIDATE_FLOAT);

if ($amount === false || $amount < 10000) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Số tiền nạp tối thiểu là 10.000đ.']);
    exit;
}

try {
    $pdo->beginTransaction();

    $stmtUpdate = $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance + :amount WHERE user_id = :id");
    $stmtUpdate->execute(['amount' => $amount, 'id' => $user['user_id']]);

    $stmtLog = $pdo->prepare(
        "INSERT INTO wallet_transactions (user_id, order_id, amount, type) VALUES (:user_id, NULL, :amount, 'topup')"
    );
    $stmtLog->execute(['user_id' => $user['user_id'], 'amount' => $amount]);

    $pdo->commit();

    $stmtBalance = $pdo->prepare("SELECT wallet_balance FROM users WHERE user_id = :id");
    $stmtBalance->execute(['id' => $user['user_id']]);
    $newBalance = (float) $stmtBalance->fetchColumn();

    echo json_encode(['success' => true, 'new_balance' => $newBalance]);
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Lỗi khi nạp tiền.']);
}