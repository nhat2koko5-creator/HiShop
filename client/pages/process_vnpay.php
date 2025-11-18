<?php
// FILE: client/pages/process_vnpay.php (ĐÃ SỬA 2 LỖI CUỐI CÙNG)

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/functions.php';

// --- BẢO MẬT: Kiểm tra điều kiện ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user_id'])) {
    die("Truy cập không hợp lệ.");
}

$user_id = $_SESSION['user_id'];

// --- LẤY GIỎ HÀNG VÀ TÍNH TOÁN TỔNG TIỀN ---
$cart = getCartItemsAndTotal($pdo, $user_id);
if (empty($cart['items'])) {
    die("Giỏ hàng rỗng.");
}
$subtotal = $cart['total'];
$discount = 0;
if (isset($_SESSION['promo']) && is_array($_SESSION['promo'])) { 
    $coupon = $_SESSION['promo'];
    if ($coupon['type'] == 'percent') {
         $discount = ($subtotal * $coupon['value']) / 100;
    } else {
         $discount = $coupon['value'];
    }
    if ($discount > $subtotal) $discount = $subtotal;
}
$total_amount = $subtotal - $discount;

// KIỂM TRA SỐ TIỀN TỐI THIỂU CỦA VNPAY
if ($total_amount < 5000) {
    die("Lỗi: Số tiền thanh toán (" . number_format($total_amount) . "đ) quá nhỏ. VNPAY yêu cầu tối thiểu 5,000đ.");
}

// --- LẤY THÔNG TIN FORM ---
$ho_ten = trim($_POST['ho_ten'] ?? '');
$so_dien_thoai = trim($_POST['so_dien_thoai'] ?? '');
$dia_chi = trim($_POST['dia_chi'] ?? '');
$ghi_chu = trim($_POST['ghi_chu'] ?? '');

// --- XỬ LÝ LƯU ĐƠN HÀNG VÀO CSDL ---
try {
    $pdo->beginTransaction();
    
    // 1. Tạo đơn hàng MỚI (status: 'pending')
    $sql_don_hang = "INSERT INTO don_hang (nguoi_dung_id, ngay_dat, tong_tien, trang_thai, ho_ten_nguoi_nhan, sdt_nguoi_nhan, dia_chi_giao_hang, ghi_chu)
                     VALUES (?, NOW(), ?, 'pending', ?, ?, ?, ?)";
    $stmt_don_hang = $pdo->prepare($sql_don_hang);
    $stmt_don_hang->execute([$user_id, $total_amount, $ho_ten, $so_dien_thoai, $dia_chi, $ghi_chu]);
    
    $order_id = $pdo->lastInsertId();

    // 2. Thêm các sản phẩm vào `chi_tiet_don_hang`
    $sql_chi_tiet = "INSERT INTO chi_tiet_don_hang (don_hang_id, san_pham_id, so_luong, don_gia)
                     VALUES (?, ?, ?, ?)";
    $stmt_chi_tiet = $pdo->prepare($sql_chi_tiet);
    
    foreach ($cart['items'] as $item) {
        $stmt_chi_tiet->execute([
            $order_id,
            $item['san_pham_id'],
            $item['so_luong'],
            $item['gia']
        ]);
    }

    // 3. Xóa giỏ hàng của người dùng
    $pdo->prepare("DELETE FROM gio_hang WHERE nguoi_dung_id = ?")->execute([$user_id]);

    // 4. Xóa mã giảm giá đã dùng (nếu có)
    unset($_SESSION['promo']);

    // 5. Hoàn tất giao dịch CSDL
    $pdo->commit();

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Lỗi khi tạo đơn hàng: " . $e->getMessage());
    die("Đã xảy ra lỗi khi tạo đơn hàng. Vui lòng thử lại.");
}

// --- CHUẨN BỊ DỮ LIỆU GỬI SANG VNPAY (FIXED) ---

date_default_timezone_set('Asia/Ho_Chi_Minh');
$startTime = date("YmdHis");
$expire = date('YmdHis',strtotime('+15 minutes',strtotime($startTime)));

$vnp_Url = "https://sandbox.vnpayment.vn/paymentv2/vpcpay.html";
$vnp_Returnurl = "http://localhost/HiShop/index.php?page=vnpay_return"; 
$vnp_TmnCode = "IG5F92AF"; 
$vnp_HashSecret = "8WOZ0DZ5QFV8800P2ZQYFYCU8D51EBL6"; 
$vnp_TxnRef = $order_id . '_' . time(); 
$vnp_OrderInfo = "ThanhToan_HIShop_" . $order_id;
$vnp_OrderType = 'other';
$vnp_Amount = $total_amount * 100; 
$vnp_Locale = 'vn';
$vnp_BankCode = ''; 
$vnp_IpAddr = '127.0.0.1'; // (FIX 1) Ép IP thành 127.0.0.1 (Vì $_SERVER['REMOTE_ADDR'] trên localhost là ::1)

$inputData = array(
    "vnp_Version" => "2.1.0",
    "vnp_TmnCode" => $vnp_TmnCode,
    "vnp_Amount" => $vnp_Amount,
    "vnp_Command" => "pay",
    "vnp_CreateDate" => $startTime,
    "vnp_CurrCode" => "VND",
    "vnp_IpAddr" => $vnp_IpAddr,
    "vnp_Locale" => $vnp_Locale,
    "vnp_OrderInfo" => $vnp_OrderInfo,
    "vnp_OrderType" => $vnp_OrderType,
    "vnp_ReturnUrl" => $vnp_Returnurl,
    "vnp_TxnRef" => $vnp_TxnRef,
    "vnp_ExpireDate" => $expire,
    "vnp_SecureHashType" => "SHA512" // <--- THÊM DÒNG NÀY
);

if (isset($vnp_BankCode) && $vnp_BankCode != "") {
    $inputData['vnp_BankCode'] = $vnp_BankCode;
}

ksort($inputData);

$query = "";
$hashdata = "";
$i = 0;
foreach ($inputData as $key => $value) {
    // (FIX) XÓA bỏ if ($value !== null && $value !== '')
    // Hash tất cả các trường, kể cả rỗng
    
    $query .= urlencode($key) . "=" . urlencode($value) . '&';
    
    if ($i == 1) {
        $hashdata .= '&' . urlencode($key) . "=" . urlencode($value);
    } else {
        $hashdata .= urlencode($key) . "=" . urlencode($value);
        $i = 1;
    }
}

$vnp_Url = $vnp_Url . "?" . $query;
if (isset($vnp_HashSecret)) {
    $vnpSecureHash =   hash_hmac('sha512', $hashdata, $vnp_HashSecret);
    $vnp_Url .= 'vnp_SecureHash=' . $vnpSecureHash;
}

header('Location: ' . $vnp_Url);
die();
?>