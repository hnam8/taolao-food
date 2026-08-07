<?php
/**
 * admin/upload_image.php
 * Đáp ứng REQ-4.5 + NFR-5 (Upload Security).
 * Nhận multipart/form-data: item_id (int), image (file).
 * Validate MIME type THẬT (không tin đuôi file client gửi), giới hạn dung lượng,
 * đổi tên file ngẫu nhiên khi lưu, xóa ảnh cũ nếu có để tránh rác file.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_role_api('admin');

const MAX_FILE_SIZE = 2 * 1024 * 1024; // 2MB
const ALLOWED_MIME_TO_EXT = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
];
const UPLOAD_DIR_RELATIVE = '/../uploads/menu_images/'; // so với thư mục admin/
const UPLOAD_DIR_PUBLIC_PATH = 'uploads/menu_images/';  // dùng để lưu vào DB / render <img>

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$itemId = filter_var($_POST['item_id'] ?? null, FILTER_VALIDATE_INT);
if (!$itemId) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'item_id không hợp lệ.']);
    exit;
}

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Không nhận được file ảnh hợp lệ.']);
    exit;
}

$file = $_FILES['image'];

// 1. Giới hạn dung lượng
if ($file['size'] > MAX_FILE_SIZE) {
    http_response_code(413);
    echo json_encode(['success' => false, 'message' => 'File vượt quá 2MB.']);
    exit;
}

// 2. Kiểm tra MIME type THẬT bằng finfo (đọc nội dung file, KHÔNG tin đuôi file
// hay Content-Type mà client tự khai báo - đây là điểm bảo mật quan trọng nhất
// của endpoint này, chống upload file .php giả dạng .jpg)
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$realMime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!array_key_exists($realMime, ALLOWED_MIME_TO_EXT)) {
    http_response_code(415);
    echo json_encode(['success' => false, 'message' => 'Chỉ chấp nhận ảnh JPG, PNG hoặc WEBP.']);
    exit;
}

// 3. Tên file luôn do SERVER sinh ra ngẫu nhiên, không dùng lại tên gốc của client
$extension = ALLOWED_MIME_TO_EXT[$realMime];
$newFileName = bin2hex(random_bytes(16)) . '.' . $extension;

$uploadDirAbsolute = __DIR__ . UPLOAD_DIR_RELATIVE;
if (!is_dir($uploadDirAbsolute)) {
    mkdir($uploadDirAbsolute, 0755, true);
}

$destination = $uploadDirAbsolute . $newFileName;

if (!move_uploaded_file($file['tmp_name'], $destination)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Lưu file thất bại.']);
    exit;
}

try {
    // Xóa ảnh cũ (nếu có) để tránh rác file tích lũy theo thời gian
    $stmt = $pdo->prepare("SELECT image_url FROM menu_items WHERE item_id = :id");
    $stmt->execute(['id' => $itemId]);
    $old = $stmt->fetchColumn();

    if ($old && str_starts_with($old, UPLOAD_DIR_PUBLIC_PATH)) {
        $oldAbsolute = __DIR__ . '/../' . $old;
        if (is_file($oldAbsolute)) {
            unlink($oldAbsolute);
        }
    }

    $newImageUrl = UPLOAD_DIR_PUBLIC_PATH . $newFileName;
    $pdo->prepare("UPDATE menu_items SET image_url = :url WHERE item_id = :id")
        ->execute(['url' => $newImageUrl, 'id' => $itemId]);

    echo json_encode(['success' => true, 'image_url' => $newImageUrl]);
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Lỗi cơ sở dữ liệu.']);
}