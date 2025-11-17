<?php
// FILE: client/pages/process_vnpay.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/functions.php';

// 1. KIỂM TRA BẢO MẬT
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user_id'])) {
    die("Truy cập không hợp lệ.");
}

$user_id = $_SESSION['user_id'];

// 2. LẤY GIỎ HÀNG VÀ TÍNH TOÁN (Giống MoMo)
$cart = getCartItemsAndTotal($pdo, $user_id);
if (empty($cart['items'])) {
    die("Giỏ hàng rỗng.");
}
$subtotal = $cart['total'];
$discount = 0;
if (isset($_SESSION['promo']) && is_array($_SESSION['promo'])) { 
    $coupon = $_SESSION['promo'];
    if ($coupon['type'] == 'percent') $discount = ($subtotal * $coupon['value']) / 100;
    else $discount = $coupon['value'];
    if ($discount > $subtotal) $discount = $subtotal;
}
$total_amount = $subtotal - $discount; // Đây là số tiền cuối cùng

// 3. LẤY THÔNG TIN FORM
$ho_ten = trim($_POST['ho_ten'] ?? '');
$so_dien_thoai = trim($_POST['so_dien_thoai'] ?? '');
$dia_chi = trim($_POST['dia_chi'] ?? '');
$ghi_chu = trim($_POST['ghi_chu'] ?? '');

// 4. LƯU ĐƠN HÀNG VÀO CSDL (Giống MoMo)
try {
    $pdo->beginTransaction();

    // 4.1. Tạo đơn hàng MỚI (status: 'pending')
    $sql_don_hang = "INSERT INTO don_hang (nguoi_dung_id, ngay_dat, tong_tien, trang_thai, ho_ten_nguoi_nhan, sdt_nguoi_nhan, dia_chi_giao_hang, ghi_chu)
                     VALUES (?, NOW(), ?, 'pending', ?, ?, ?, ?)";
    $stmt_don_hang = $pdo->prepare($sql_don_hang);
    $stmt_don_hang->execute([$user_id, $total_amount, $ho_ten, $so_dien_thoai, $dia_chi, $ghi_chu]);
    $order_id = $pdo->lastInsertId();

    // 4.2. Thêm chi tiết đơn hàng
    $sql_chi_tiet = "INSERT INTO chi_tiet_don_hang (don_hang_id, san_pham_id, so_luong, don_gia) VALUES (?, ?, ?, ?)";
    $stmt_chi_tiet = $pdo->prepare($sql_chi_tiet);
    foreach ($cart['items'] as $item) {
        $stmt_chi_tiet->execute([$order_id, $item['san_pham_id'], $item['so_luong'], $item['gia']]);
    }

    // 4.3. Xóa giỏ hàng
    $pdo->prepare("DELETE FROM gio_hang WHERE nguoi_dung_id = ?")->execute([$user_id]);
    
    // 4.4. Xóa mã giảm giá đã dùng (nếu có)
    unset($_SESSION['promo']);
    
    $pdo->commit();

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Lỗi khi tạo đơn hàng VNPAY: " . $e->getMessage());
    die("Đã xảy ra lỗi khi tạo đơn hàng. Vui lòng thử lại.");
}

// 5. CHUẨN BỊ DỮ LIỆU GỬI SANG VNPAY
$vnp_Url = "https://sandbox.vnpayment.vn/paymentv2/vpcpay.html";
$vnp_Returnurl = "http://localhost/HiShop/index.php?page=vnpay_return"; // Trang trả về
$vnp_TmnCode = "IG5F92AF"; // Lấy từ Bước 1
$vnp_HashSecret = "8WOZ0DZ5QFV8800P2ZQYFYCU8D51EBL6"; // Lấy từ Bước 1
$vnp_TxnRef = $order_id . '_' . time(); // Mã đơn hàng (duy nhất)
$vnp_OrderInfo = "ThanhToan_HIShop_" . $order_id;
$vnp_OrderType = 'other';
$vnp_Amount = $total_amount * 100; // VNPAY yêu cầu * 100 (đơn vị xu/cent)
$vnp_Locale = 'vn';
$vnp_BankCode = ''; // Để trống để VNPAY hiển thị cổng chọn ngân hàng
$vnp_IpAddr = '127.0.0.1';
date_default_timezone_set('Asia/Ho_Chi_Minh');
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
    "vnp_TxnRef" => $vnp_TxnRef,
    "vnp_SecureHashType" => "SHA512"
);

if (isset($vnp_BankCode) && $vnp_BankCode != "") {
    $inputData['vnp_BankCode'] = $vnp_BankCode;
}

ksort($inputData);
$query = "";
$hashdata = "";
$i = 0;
foreach ($inputData as $key => $value) {
    
    // (FIX) CHỈ XỬ LÝ NHỮNG TRƯỜNG CÓ GIÁ TRỊ (KHÁC RỖNG VÀ KHÔNG NULL)
    if ($value !== null && $value !== '') {
        // Tạo chuỗi query cho URL (CẦN urlencode)
        $query .= urlencode($key) . "=" . urlencode($value) . '&';
        
        // Tạo chuỗi hashdata để tạo chữ ký (KHÔNG urlencode)
        if ($i == 1) {
            $hashdata .= '&' . $key . "=" . $value;
        } else {
            $hashdata .= $key . "=" . $value;
            $i = 1;
        }
    }
}

$vnp_Url = $vnp_Url . "?" . $query;
$vnp_SecureHash = hash_hmac('sha512', $hashdata, $vnp_HashSecret);
$vnp_Url .= 'vnp_SecureHash=' . $vnp_SecureHash;

header('Location: ' . $vnp_Url);
die();
?>