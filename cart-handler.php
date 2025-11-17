<?php
// FILE: cart-handler.php (ĐÃ SỬA LỖI - DÙNG CSDL)

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/src/config.php';

header('Content-Type: application/json');
$response = ['status' => 'error', 'message' => 'Lỗi không xác định.'];

// --- BẢO MẬT: Phải đăng nhập ---
if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'Bạn cần đăng nhập để thêm vào giỏ hàng.';
    // 401 = Unauthorized
    http_response_code(401); 
    echo json_encode($response);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? 'view';
$product_id = (int)($_POST['id'] ?? 0);
$quantity = (int)($_POST['quantity'] ?? 1);
$cart_key = $_POST['key'] ?? ''; // 'key' này giờ là product_id

// --- XỬ LÝ DỮ LIỆU ---
try {
    switch ($action) {
        case 'add':
            if ($product_id <= 0 || $quantity <= 0) {
                $response['message'] = 'Dữ liệu không hợp lệ.';
                break;
            }

            // (Kiểm tra tồn kho trước khi thêm)
            $stmt_stock = $pdo->prepare("SELECT so_luong FROM san_pham WHERE id = ?");
            $stmt_stock->execute([$product_id]);
            $stock = $stmt_stock->fetchColumn();

            // Sử dụng ON DUPLICATE KEY UPDATE
            $sql = "INSERT INTO gio_hang (nguoi_dung_id, san_pham_id, so_luong)
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE so_luong = so_luong + VALUES(so_luong)";
            
            $stmt_insert = $pdo->prepare($sql);
            $stmt_insert->execute([$user_id, $product_id, $quantity]);

            $response['status'] = 'success';
            $response['message'] = 'Đã thêm vào giỏ hàng!';
            
            // Lấy tổng số lượng mới
            $stmt_count = $pdo->prepare("SELECT SUM(so_luong) FROM gio_hang WHERE nguoi_dung_id = ?");
            $stmt_count->execute([$user_id]);
            $response['totalItems'] = (int)$stmt_count->fetchColumn();
            break;

        case 'update':
            $product_id = (int)$cart_key; // key chính là product_id
            if ($product_id <= 0 || $quantity < 1) {
                $response['message'] = 'Lỗi cập nhật.';
                break;
            }
            
            $sql = "UPDATE gio_hang SET so_luong = ? WHERE nguoi_dung_id = ? AND san_pham_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$quantity, $user_id, $product_id]);

            // Lấy giá sản phẩm để tính tổng
            $stmt_price = $pdo->prepare("SELECT gia FROM san_pham WHERE id = ?");
            $stmt_price->execute([$product_id]);
            $price = $stmt_price->fetchColumn();
            
            $response['status'] = 'success';
            $response['item_total'] = number_format($price * $quantity, 0, ',', '.') . '₫';
            
            // Lấy tổng số lượng mới
            $stmt_count = $pdo->prepare("SELECT SUM(so_luong) FROM gio_hang WHERE nguoi_dung_id = ?");
            $stmt_count->execute([$user_id]);
            $response['totalItems'] = (int)$stmt_count->fetchColumn();
            break;

        case 'delete':
            $product_id = (int)$cart_key; // key chính là product_id
            if ($product_id <= 0) {
                $response['message'] = 'Lỗi khi xóa.';
                break;
            }
            
            $sql = "DELETE FROM gio_hang WHERE nguoi_dung_id = ? AND san_pham_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$user_id, $product_id]);

            $stmt_count = $pdo->prepare("SELECT COUNT(id) FROM gio_hang WHERE nguoi_dung_id = ?");
            $stmt_count->execute([$user_id]);
            
            $response['status'] = 'success';
            $response['cart_empty'] = $stmt_count->fetchColumn() == 0;
            break;
            
       case 'apply_coupon':
            $code = trim($_POST['code'] ?? '');
            if (empty($code)) {
                $response['message'] = 'Vui lòng nhập mã.';
                break;
            }

            // Lấy mã từ bảng 'ma_khuyen_mai' (Đây là bảng đúng cho coupon)
            $stmt = $pdo->prepare("SELECT * FROM ma_khuyen_mai 
                                   WHERE ten = ? 
                                     AND (ngay_bat_dau IS NULL OR ngay_bat_dau <= NOW())
                                     AND (ngay_ket_thuc IS NULL OR ngay_ket_thuc >= NOW())");
            $stmt->execute([$code]);
            $coupon = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($coupon) {
                // Nếu tìm thấy, lưu vào session
                $coupon_data = [
                    'id' => $coupon['id'],
                    'code' => $coupon['ten'],
                    'type' => $coupon['loai_khuyen_mai'], // 'percent' or 'amount'
                    'value' => (float)$coupon['gia_tri']
                ];
                $_SESSION['promo'] = $coupon_data;
                
                $response['status'] = 'success';
                $response['message'] = 'Áp dụng mã thành công!';
                $response['coupon'] = $coupon_data; // Gửi coupon về cho JS
            } else {
                // Nếu không, xóa session cũ và báo lỗi
                $_SESSION['promo'] = null;
                $response['message'] = 'Mã không hợp lệ hoặc đã hết hạn.';
            }
            break;

        default:
            $response['message'] = 'Hành động không hợp lệ.';
            break;
    }
} catch (PDOException $e) {
    error_log("Lỗi cart-handler (DB): " . $e->getMessage());
    $response['message'] = 'Lỗi CSDL: ' . $e->getMessage();
}

echo json_encode($response);
exit;