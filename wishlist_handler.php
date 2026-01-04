<?php
// FILE: wishlist_handler.php
session_start();
require_once 'src/config.php';

header('Content-Type: application/json');

// --- 1. VALIDATE ĐĂNG NHẬP ---
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    http_response_code(401); // Lỗi chưa xác thực
    echo json_encode([
        'status' => 'error',
        'message' => 'Bạn cần đăng nhập để thực hiện chức năng này.'
    ]);
    exit;
}

// --- 2. VALIDATE INPUT DỮ LIỆU ---
// Chỉ chấp nhận POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['status' => 'error', 'message' => 'Phương thức không hợp lệ.']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$action = isset($_POST['action']) ? $_POST['action'] : '';

// Kiểm tra ID sản phẩm có hợp lệ không
if ($product_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Sản phẩm không hợp lệ.']);
    exit;
}

// Kiểm tra Action có nằm trong danh sách cho phép không
$allowed_actions = ['toggle', 'add', 'remove'];
if (!in_array($action, $allowed_actions)) {
    echo json_encode(['status' => 'error', 'message' => 'Hành động không hợp lệ.']);
    exit;
}

try {
    // --- 3. XỬ LÝ LOGIC ---
    
    // Kiểm tra xem đã like chưa
    $stmt_check = $pdo->prepare("SELECT id FROM san_pham_yeu_thich WHERE nguoi_dung_id = ? AND san_pham_id = ?");
    $stmt_check->execute([$user_id, $product_id]);
    $exists = $stmt_check->fetch();

    // -- CASE 1: TOGGLE (Bật/Tắt - Dùng cho nút tim) --
    if ($action === 'toggle') {
        if ($exists) {
            // Đang like -> Xóa
            $stmt = $pdo->prepare("DELETE FROM san_pham_yeu_thich WHERE nguoi_dung_id = ? AND san_pham_id = ?");
            $stmt->execute([$user_id, $product_id]);
            echo json_encode(['status' => 'success', 'state' => 'unliked', 'message' => 'Đã xóa khỏi yêu thích 💔']);
        } else {
            // Chưa like -> Thêm
            $stmt = $pdo->prepare("INSERT INTO san_pham_yeu_thich (nguoi_dung_id, san_pham_id, ngay_tao) VALUES (?, ?, NOW())");
            $stmt->execute([$user_id, $product_id]);
            echo json_encode(['status' => 'success', 'state' => 'liked', 'message' => 'Đã thêm vào yêu thích ❤️']);
        }
    } 
    
    // -- CASE 2: REMOVE (Xóa hẳn - Dùng cho trang wishlist) --
    elseif ($action === 'remove') {
        if ($exists) {
            $stmt = $pdo->prepare("DELETE FROM san_pham_yeu_thich WHERE nguoi_dung_id = ? AND san_pham_id = ?");
            $stmt->execute([$user_id, $product_id]);
            echo json_encode(['status' => 'success', 'message' => 'Đã xóa sản phẩm thành công!']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Sản phẩm không tồn tại trong danh sách.']);
        }
    }

} catch (PDOException $e) {
    // Ghi log lỗi server (không show cho user)
    error_log("Wishlist Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Lỗi hệ thống, vui lòng thử lại sau.']);
}