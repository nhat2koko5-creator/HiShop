<?php
// FILE: client/pages/process_vnpay.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'src/config.php'; 
// require_once 'src/functions.php'; // (Nếu cần dùng hàm hỗ trợ)

// 1. KIỂM TRA ĐĂNG NHẬP
if (!isset($_SESSION['user_id'])) {
    die("Vui lòng đăng nhập để thanh toán.");
}
$user_id = $_SESSION['user_id'];

// 2. LẤY DỮ LIỆU TỪ FORM CHECKOUT
// Lưu ý: Các biến này lấy từ name="" trong thẻ input của checkout.php
$ho_ten = $_POST['ho_ten'] ?? '';
$so_dien_thoai = $_POST['so_dien_thoai'] ?? '';
$dia_chi = $_POST['dia_chi'] ?? '';
$ghi_chu = $_POST['ghi_chu'] ?? '';
$order_type = $_POST['order_type'] ?? 'cart'; // 'cart' hoặc 'buy_now'

// 3. XÁC ĐỊNH DANH SÁCH SẢN PHẨM MUA
$order_items = [];
$subtotal = 0;

if ($order_type === 'buy_now') {
    // --- TRƯỜNG HỢP A: MUA NGAY ---
    if (isset($_SESSION['buy_now_item']) && !empty($_SESSION['buy_now_item'])) {
        $item = $_SESSION['buy_now_item'];
        // Giả lập cấu trúc giống giỏ hàng để xử lý chung
        $order_items[] = [
            'san_pham_id' => $item['san_pham_id'], // Lưu ý: Đây là ID biến thể hoặc ID sản phẩm tùy logic
            'ten' => $item['ten'],
            'gia' => $item['gia'],
            'so_luong' => $item['so_luong']
        ];
        $subtotal += $item['gia'] * $item['so_luong'];
    } else {
        die("Lỗi: Không tìm thấy thông tin sản phẩm mua ngay. Vui lòng thử lại.");
    }
} else {
    // --- TRƯỜNG HỢP B: MUA TỪ GIỎ HÀNG (Mặc định) ---
    // Lấy từ CSDL (Hàm này bạn đã có trong functions.php)
    $cartData = getCartItemsAndTotal($pdo, $user_id);
    $order_items = $cartData['items'];
    $subtotal = $cartData['total'];

    if (empty($order_items)) {
        die("Giỏ hàng của bạn đang trống.");
    }
}

// 4. TÍNH TOÁN MÃ GIẢM GIÁ (Tính lại ở Server để bảo mật)
$discount = 0;
$coupon_id = null; // Để lưu vào DB cột ma_khuyen_mai_id

if (isset($_SESSION['promo'])) {
    $coupon = $_SESSION['promo'];
    if ($coupon['type'] == 'percent') {
        $discount = ($subtotal * $coupon['value']) / 100;
    } else {
        $discount = $coupon['value'];
    }
    // Không giảm quá tổng tiền
    if ($discount > $subtotal) $discount = $subtotal;
    $coupon_id = $coupon['id'];
}

// TỔNG TIỀN CUỐI CÙNG
$final_total = $subtotal - $discount;
if ($final_total < 0) $final_total = 0;

// 5. TẠO ĐƠN HÀNG TRONG DATABASE (Trạng thái: PENDING)
try {
    $pdo->beginTransaction();

    // 5.1 Insert bảng don_hang
    // [SỬA LỖI]: Đổi tên cột cho khớp với Database: ho_ten -> ho_ten_nguoi_nhan, so_dien_thoai -> sdt_nguoi_nhan
    $sql_order = "INSERT INTO don_hang (nguoi_dung_id, ho_ten_nguoi_nhan, sdt_nguoi_nhan, dia_chi_giao_hang, ghi_chu, tong_tien, trang_thai, ma_khuyen_mai_id, ngay_dat) 
                  VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, NOW())";
    
    $stmt = $pdo->prepare($sql_order);
    $stmt->execute([
        $user_id, 
        $ho_ten, 
        $so_dien_thoai, 
        $dia_chi, 
        $ghi_chu, 
        $final_total,
        $coupon_id // Có thể là null
    ]);
    
    $order_id = $pdo->lastInsertId(); // Lấy ID đơn vừa tạo

    // 5.2 Insert bảng chi_tiet_don_hang
    // [SỬA LỖI]: Đổi tên cột gia_don_vi -> don_gia
    $sql_detail = "INSERT INTO chi_tiet_don_hang (don_hang_id, san_pham_id, so_luong, don_gia) VALUES (?, ?, ?, ?)";
    $stmt_detail = $pdo->prepare($sql_detail);

    foreach ($order_items as $item) {
        $stmt_detail->execute([
            $order_id, 
            $item['san_pham_id'], 
            $item['so_luong'], 
            $item['gia']
        ]);
    }

    // 5.3 Nếu là mua từ giỏ hàng -> Xóa giỏ hàng
    if ($order_type === 'cart') {
        $stmt_del = $pdo->prepare("DELETE FROM gio_hang WHERE nguoi_dung_id = ?");
        $stmt_del->execute([$user_id]);
    }
    // Nếu mua ngay -> Xóa session buy_now
    if ($order_type === 'buy_now') {
        unset($_SESSION['buy_now_item']);
    }

    // Xóa mã giảm giá sau khi dùng
    unset($_SESSION['promo']);

    $pdo->commit();

} catch (Exception $e) {
    $pdo->rollBack();
    die("Lỗi tạo đơn hàng: " . $e->getMessage());
}

// 6. CẤU HÌNH VNPAY & CHUYỂN HƯỚNG
// Thay các thông số này bằng Key thật của bạn
$vnp_TmnCode = "CODE_CUA_BAN"; // <-- ĐIỀN LẠI MÃ CỦA BẠN
$vnp_HashSecret = "SECRET_KEY_CUA_BAN"; // <-- ĐIỀN LẠI SECRET KEY CỦA BẠN
$vnp_Url = "https://sandbox.vnpayment.vn/paymentv2/vpcpay.html";
$vnp_Returnurl = "http://localhost/HISHOP/index.php?page=vnpay_return";

$vnp_TxnRef = $order_id; // Mã đơn hàng
$vnp_OrderInfo = "Thanh toan don hang #" . $order_id;
$vnp_OrderType = "billpayment";
$vnp_Amount = $final_total * 100; // VNPay tính đơn vị đồng (x100)
$vnp_Locale = "vn";
$vnp_BankCode = ""; 
$vnp_IpAddr = $_SERVER['REMOTE_ADDR'];

$inputData = array(
    "vnp_Version" => "2.1.0",
    "vnp_TmnCode" => $vnp_TmnCode,
    "vnp_Amount" => $vnp_Amount,
    "vnp_Command" => "pay",
    "vnp_CreateDate" => date('YmdHis'),
    "vnp_CurrCode" => "VND",
    "vnp_IpAddr" => $vnp_IpAddr,
    "vnp_Locale" => $vnp_Locale,
    "vnp_OrderInfo" => $vnp_OrderInfo,
    "vnp_OrderType" => $vnp_OrderType,
    "vnp_ReturnUrl" => $vnp_Returnurl,
    "vnp_TxnRef" => $vnp_TxnRef
);

if (isset($vnp_BankCode) && $vnp_BankCode != "") {
    $inputData['vnp_BankCode'] = $vnp_BankCode;
}

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

$vnp_Url = $vnp_Url . "?" . $query;
if (isset($vnp_HashSecret)) {
    $vnpSecureHash =   hash_hmac('sha512', $hashdata, $vnp_HashSecret);
    $vnp_Url .= 'vnp_SecureHash=' . $vnpSecureHash;
}

// Chuyển hướng sang VNPAY
header('Location: ' . $vnp_Url);
die();
?>