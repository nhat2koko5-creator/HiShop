<?php
// FILE: cart-handler.php
// ==============================================================
// PHIÊN BẢN ĐÃ SỬA LỖI HOÀN TOÀN CHO LOGIC MÃ GIẢM GIÁ & JSON
// ==============================================================

// 1. Bắt đầu bộ đệm đầu ra để ngăn chặn bất kỳ ký tự lạ nào (khoảng trắng, lỗi) làm hỏng JSON
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Đường dẫn này phải chính xác với cấu trúc thư mục của bạn
require_once __DIR__ . '/src/config.php';

// 2. Đặt header JSON chuẩn
header('Content-Type: application/json; charset=utf-8');

$response = ['status' => 'error', 'message' => 'Lỗi không xác định.'];

// 3. Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.';
    sendResponse($response);
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

// Các biến dùng chung cho add/update/delete
$product_id = (int)($_POST['id'] ?? 0);
$quantity = (int)($_POST['quantity'] ?? 1);
$cart_key = $_POST['key'] ?? ''; // Trong context này key chính là product_id

try {
    switch ($action) {
        // --- XỬ LÝ ÁP DỤNG MÃ GIẢM GIÁ (TRỌNG TÂM) ---
        case 'apply_coupon':
            $code = trim($_POST['code'] ?? '');

            // Nếu mã rỗng -> Người dùng muốn hủy mã
            if (empty($code)) {
                unset($_SESSION['promo']);
                $response['status'] = 'success';
                $response['message'] = 'Đã hủy mã giảm giá. Giá đơn hàng sẽ về mặc định.';
            } else {
                // Kiểm tra mã trong CSDL
                // Logic: Mã phải tồn tại + Ngày bắt đầu <= Hiện tại + (Chưa hết hạn HOẶC không có hạn)
                $sql = "SELECT * FROM ma_khuyen_mai 
                        WHERE ten = ? 
                        AND (ngay_bat_dau IS NULL OR ngay_bat_dau <= NOW())
                        AND (ngay_ket_thuc IS NULL OR ngay_ket_thuc >= NOW())";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$code]);
                $coupon = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($coupon) {
                    // Kiểm tra số lượng (nếu dự án của bạn có quản lý số lượng mã)
                    if (isset($coupon['so_luong']) && $coupon['so_luong'] <= 0) {
                         $response['status'] = 'error';
                         $response['message'] = 'Mã này đã hết lượt sử dụng.';
                    } else {
                        // --- THÀNH CÔNG ---
                        $_SESSION['promo'] = [
                            'id' => $coupon['id'],
                            'code' => $coupon['ten'],
                            'type' => $coupon['loai_khuyen_mai'], // 'percent' hoặc 'amount'
                            'value' => (float)$coupon['gia_tri']
                        ];
                        $response['status'] = 'success';
                        $response['message'] = 'Áp dụng mã "' . htmlspecialchars($coupon['ten']) . '" thành công!';
                    }
                } else {
                    // --- THẤT BẠI ---
                    unset($_SESSION['promo']); // Xóa session cũ đi cho an toàn
                    $response['status'] = 'error';
                    $response['message'] = 'Mã giảm giá không tồn tại hoặc đã hết hạn!';
                }
            }
            break;

        // --- CÁC CHỨC NĂNG GIỎ HÀNG KHÁC (GIỮ NGUYÊN ĐỂ KHÔNG LỖI CART) ---
       case 'add':
            if (isset($_POST['variant_id']) && !empty($_POST['variant_id'])) {
                $id_to_add = (int)$_POST['variant_id'];
            } else {
                $id_to_add = (int)$_POST['id'];
            }

            // Kiểm tra dữ liệu đầu vào
            if ($id_to_add <= 0 || $quantity <= 0) { 
                $response['message'] = 'Dữ liệu sản phẩm không hợp lệ.'; 
                break; 
            }

            // Câu lệnh SQL Insert/Update
            $sql = "INSERT INTO gio_hang (nguoi_dung_id, san_pham_id, so_luong) 
                    VALUES (?, ?, ?) 
                    ON DUPLICATE KEY UPDATE so_luong = so_luong + VALUES(so_luong)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$user_id, $id_to_add, $quantity]);
             
            // Đếm lại tổng số lượng để cập nhật icon giỏ hàng
            $stmt_count = $pdo->prepare("SELECT SUM(so_luong) FROM gio_hang WHERE nguoi_dung_id = ?");
            $stmt_count->execute([$user_id]);
            
            $response['status'] = 'success';
            $response['message'] = 'Đã thêm vào giỏ hàng thành công!';
            $response['totalItems'] = (int)$stmt_count->fetchColumn();
            break;

        case 'update':
            $product_id = (int)$cart_key;
            if ($product_id <= 0 || $quantity < 1) { $response['message'] = 'Lỗi cập nhật.'; break; }
            
            $sql = "UPDATE gio_hang SET so_luong = ? WHERE nguoi_dung_id = ? AND san_pham_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$quantity, $user_id, $product_id]);

            // Lấy giá để tính lại tổng item
            $stmt_price = $pdo->prepare("SELECT gia FROM san_pham WHERE id = ?");
            $stmt_price->execute([$product_id]);
            $price = $stmt_price->fetchColumn();
            
            $response['status'] = 'success';
            $response['item_total'] = number_format($price * $quantity, 0, ',', '.') . '₫';
            break;

        case 'delete':
            $product_id = (int)$cart_key;
            if ($product_id <= 0) { $response['message'] = 'Lỗi khi xóa.'; break; }
            
            $sql = "DELETE FROM gio_hang WHERE nguoi_dung_id = ? AND san_pham_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$user_id, $product_id]);

            $stmt_count = $pdo->prepare("SELECT COUNT(id) FROM gio_hang WHERE nguoi_dung_id = ?");
            $stmt_count->execute([$user_id]);
            $response['status'] = 'success';
            $response['cart_empty'] = $stmt_count->fetchColumn() == 0;
            break;

        default:
            $response['message'] = 'Hành động không hợp lệ.';
            break;
    }
} catch (Exception $e) {
    $response['message'] = 'Lỗi Server: ' . $e->getMessage();
}

// Gửi phản hồi và kết thúc script
sendResponse($response);

function sendResponse($data) {
    ob_end_clean(); // Quan trọng: Xóa sạch bộ đệm trước khi echo JSON
    echo json_encode($data);
    exit;
}
?>