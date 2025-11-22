<?php
// FILE: client/pages/process_vnpay.php (BẢN FIX LỖI SAI CHỮ KÝ)

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'src/config.php'; 
date_default_timezone_set('Asia/Ho_Chi_Minh');

// 1. AUTH
if (!isset($_SESSION['user_id'])) die("Vui lòng đăng nhập.");
$user_id = $_SESSION['user_id'];

// 2. LẤY DỮ LIỆU
$ho_ten = $_POST['ho_ten'] ?? 'Khach';
$so_dien_thoai = $_POST['so_dien_thoai'] ?? '0987654321';
$dia_chi = $_POST['dia_chi'] ?? 'Hanoi';
$ghi_chu = $_POST['ghi_chu'] ?? '';
$order_type = $_POST['order_type'] ?? 'cart';

// 3. TÍNH TOÁN TIỀN
$subtotal = 0;
$order_items = [];

if ($order_type === 'buy_now') {
    if (!isset($_SESSION['buy_now_item'])) die("Lỗi session.");
    $item = $_SESSION['buy_now_item'];
    $order_items[] = [
        'san_pham_id' => $item['san_pham_id'],
        'so_luong' => $item['so_luong'],
        'gia' => $item['gia']
    ];
    $subtotal = $item['gia'] * $item['so_luong'];
} else {
    $cartData = getCartItemsAndTotal($pdo, $user_id);
    $order_items = $cartData['items'];
    $subtotal = $cartData['total'];
}

// Giảm giá
$discount = 0;
$coupon_id = null;
if (isset($_SESSION['promo'])) {
    $coupon = $_SESSION['promo'];
    if ($coupon['type'] == 'percent') $discount = ($subtotal * $coupon['value']) / 100;
    else $discount = $coupon['value'];
    if ($discount > $subtotal) $discount = $subtotal;
    $coupon_id = $coupon['id'];
}
$final_total = $subtotal - $discount;

// 4. LƯU ĐƠN HÀNG VÀO DB (TRẠNG THÁI PENDING)
try {
    $pdo->beginTransaction();
    $sql = "INSERT INTO don_hang (nguoi_dung_id, ho_ten_nguoi_nhan, sdt_nguoi_nhan, dia_chi_giao_hang, ghi_chu, tong_tien, trang_thai, ma_khuyen_mai_id, ngay_dat) VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, NOW())";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id, $ho_ten, $so_dien_thoai, $dia_chi, $ghi_chu, $final_total, $coupon_id]);
    $order_id = $pdo->lastInsertId();

    $stmt_dt = $pdo->prepare("INSERT INTO chi_tiet_don_hang (don_hang_id, san_pham_id, so_luong, don_gia) VALUES (?, ?, ?, ?)");
    foreach ($order_items as $it) {
        $stmt_dt->execute([$order_id, $it['san_pham_id'], $it['so_luong'], $it['gia']]);
    }

    // Xóa giỏ hàng nếu mua từ giỏ
    if ($order_type === 'cart') {
        $pdo->prepare("DELETE FROM gio_hang WHERE nguoi_dung_id = ?")->execute([$user_id]);
    } else {
        unset($_SESSION['buy_now_item']);
    }
    unset($_SESSION['promo']);
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    die("Lỗi DB: " . $e->getMessage());
}

// 5. CẤU HÌNH THANH TOÁN VNPAY
// Tham số bắt buộc
$vnp_TxnRef = $order_id; // Mã đơn hàng
$vnp_OrderInfo = "Thanh toan don hang " . $order_id;
$vnp_OrderType = "other";
$vnp_Amount = (int)$final_total * 100; // Nhân 100 để bỏ số thập phân
$vnp_Locale = "vn";
$vnp_IpAddr = "127.0.0.1"; // (FIX LỖI) Cứng IP IPv4 để tránh lỗi ::1 trên localhost
$vnp_CreateDate = date('YmdHis');
$vnp_ExpireDate = date('YmdHis', strtotime('+15 minutes'));

// Mảng dữ liệu gửi sang VNPAY
$inputData = array(
    "vnp_Version" => "2.1.0",
    "vnp_TmnCode" => VNP_TMN_CODE,
    "vnp_Amount" => $vnp_Amount,
    "vnp_Command" => "pay",
    "vnp_CreateDate" => $vnp_CreateDate,
    "vnp_CurrCode" => "VND",
    "vnp_IpAddr" => $vnp_IpAddr,
    "vnp_Locale" => $vnp_Locale,
    "vnp_OrderInfo" => $vnp_OrderInfo,
    "vnp_OrderType" => $vnp_OrderType,
    "vnp_ReturnUrl" => VNP_RETURN_URL,
    "vnp_TxnRef" => $vnp_TxnRef,
    "vnp_ExpireDate" => $vnp_ExpireDate
);

// Sắp xếp mảng theo alphabet (BẮT BUỘC ĐỂ TẠO HASH ĐÚNG)
ksort($inputData);

$query = "";
$i = 0;
$hashdata = "";
foreach ($inputData as $key => $value) {
    if ($i == 1) {
        $hashdata .= '&' . urlencode($key) . "=" . urlencode($value);
    } else {
        $hashdata .= urlencode($key) . "=" . urlencode($value);
        $i = 1;
    }
    $query .= urlencode($key) . "=" . urlencode($value) . '&';
}

// Tạo URL thanh toán
$vnp_Url = VNP_URL . "?" . $query;
if (defined('VNP_HASH_SECRET')) {
    $vnpSecureHash = hash_hmac('sha512', $hashdata, VNP_HASH_SECRET);
    $vnp_Url .= 'vnp_SecureHash=' . $vnpSecureHash;
}

// Chuyển hướng
header('Location: ' . $vnp_Url);
die();
?>