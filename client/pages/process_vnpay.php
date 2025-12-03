<?php
// FILE: client/pages/process_vnpay.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../src/config.php'; 

date_default_timezone_set('Asia/Ho_Chi_Minh');

// 1. AUTH
if (!isset($_SESSION['user_id'])) die("Vui lòng đăng nhập.");
$user_id = $_SESSION['user_id'];

// 2. LẤY DỮ LIỆU TỪ FORM
$ho_ten = $_POST['ho_ten'] ?? 'Khach';
$so_dien_thoai = $_POST['so_dien_thoai'] ?? '0987654321';
$dia_chi = $_POST['dia_chi'] ?? 'Hanoi';
$ghi_chu = $_POST['ghi_chu'] ?? '';
$order_type = $_POST['order_type'] ?? 'cart';

// [MỚI] Lấy phương thức thanh toán
$payment_method = $_POST['payment_method'] ?? 'cod'; // Mặc định là COD

// 3. CHUẨN HÓA DỮ LIỆU SẢN PHẨM (GIỮ NGUYÊN CODE CŨ CỦA BẠN TỪ DÒNG 23-107)
$subtotal = 0;
$order_items = [];

// --- LOGIC THANH TOÁN LẠI (REPAY) ---
if (isset($_GET['repay_order_id'])) {
    $repay_id = (int)$_GET['repay_order_id'];
    $stmt = $pdo->prepare("SELECT * FROM don_hang WHERE id = ? AND nguoi_dung_id = ? AND trang_thai_thanh_toan = 'Chưa thanh toán'");
    $stmt->execute([$repay_id, $user_id]);
    $order = $stmt->fetch();

    if (!$order) {
        die("Đơn hàng không hợp lệ hoặc đã thanh toán.");
    }
    $final_total = $order['tong_tien'];
    $order_id = $order['id'];
    
    // Nếu repay mà chọn COD thì redirect luôn (trường hợp hiếm, thường repay là để thanh toán online)
    if ($payment_method == 'cod') {
         header('Location: index.php?page=confirmation&id=' . $order_id);
         exit;
    }
    
    goto vnpay_config; 
}
// ------------------------------------

// ... (ĐOẠN CODE LẤY GIỎ HÀNG / MUA NGAY / COUPON GIỮ NGUYÊN KHÔNG ĐỔI) ...
if ($order_type === 'buy_now') {
    if (!isset($_SESSION['buy_now_item'])) die("Lỗi session: Không tìm thấy sản phẩm.");
    $item = $_SESSION['buy_now_item'];
    $sp_id = $item['san_pham_id'] ?? $item['id'];
    $variant_id = $item['variant_id'] ?? $item['bien_the_id'] ?? null;
    $order_items[] = ['san_pham_id' => $sp_id, 'bien_the_id' => $variant_id, 'so_luong' => $item['so_luong'], 'gia' => $item['gia']];
    $subtotal = $item['gia'] * $item['so_luong'];
} else {
    // Logic lấy giỏ hàng (Giữ nguyên)
    $sql_cart = "SELECT gh.san_pham_id, gh.bien_the_id, gh.so_luong, COALESCE(v.gia, p.gia) as gia_chuan FROM gio_hang gh JOIN san_pham p ON gh.san_pham_id = p.id LEFT JOIN bien_the_san_pham v ON gh.bien_the_id = v.id WHERE gh.nguoi_dung_id = ?";
    $stmt_cart = $pdo->prepare($sql_cart);
    $stmt_cart->execute([$user_id]);
    $raw_items = $stmt_cart->fetchAll(PDO::FETCH_ASSOC);
    foreach ($raw_items as $item) {
        $order_items[] = ['san_pham_id' => $item['san_pham_id'], 'bien_the_id' => $item['bien_the_id'], 'so_luong' => $item['so_luong'], 'gia' => $item['gia_chuan']];
        $subtotal += $item['gia_chuan'] * $item['so_luong'];
    }
}

if (empty($order_items)) die("Giỏ hàng trống.");

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


// 4. LƯU ĐƠN HÀNG VÀO DB
try {
    $pdo->beginTransaction();
    
    // [MỚI] Thêm cột phuong_thuc_thanh_toan vào câu lệnh INSERT
    $sql = "INSERT INTO don_hang (nguoi_dung_id, ho_ten_nguoi_nhan, sdt_nguoi_nhan, dia_chi_giao_hang, ghi_chu, tong_tien, trang_thai_don_hang, trang_thai_thanh_toan, ma_khuyen_mai_id, phuong_thuc_thanh_toan, ngay_dat) 
            VALUES (?, ?, ?, ?, ?, ?, 'Chờ xử lý', 'Chưa thanh toán', ?, ?, NOW())";
    
    // Convert payment method sang tên hiển thị đẹp hơn
    $payment_method_text = ($payment_method == 'vnpay') ? 'VNPAY' : 'COD';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id, $ho_ten, $so_dien_thoai, $dia_chi, $ghi_chu, $final_total, $coupon_id, $payment_method_text]);
    $order_id = $pdo->lastInsertId();

    // Insert chi tiết đơn hàng (Giữ nguyên)
    $stmt_dt = $pdo->prepare("INSERT INTO chi_tiet_don_hang (don_hang_id, san_pham_id, bien_the_id, so_luong, don_gia) VALUES (?, ?, ?, ?, ?)");
    foreach ($order_items as $it) {
        $stmt_dt->execute([$order_id, $it['san_pham_id'], $it['bien_the_id'], $it['so_luong'], $it['gia']]);
    }

    // Xóa giỏ hàng
    if ($order_type === 'cart') {
        $pdo->prepare("DELETE FROM gio_hang WHERE nguoi_dung_id = ?")->execute([$user_id]);
    } else {
        unset($_SESSION['buy_now_item']);
    }
    unset($_SESSION['promo']);
    
    $pdo->commit();

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    die("Lỗi xử lý đơn hàng: " . $e->getMessage());
}

// 5. PHÂN LUỒNG XỬ LÝ THANH TOÁN
if ($payment_method === 'cod') {
    // --- NẾU LÀ COD: CHUYỂN HƯỚNG ĐẾN TRANG CẢM ƠN ---
    header('Location: index.php?page=confirmation&id=' . $order_id);
    exit;
}

// --- NẾU LÀ VNPAY: TIẾP TỤC CẤU HÌNH DƯỚI ĐÂY ---
vnpay_config: 

$vnp_TxnRef = $order_id; 
$vnp_OrderInfo = "Thanh toan don hang " . $order_id;
$vnp_OrderType = "other";
$vnp_Amount = (int)$final_total * 100; 
$vnp_Locale = "vn";
$vnp_IpAddr = $_SERVER['REMOTE_ADDR']; 
$vnp_CreateDate = date('YmdHis');
$vnp_ExpireDate = date('YmdHis', strtotime('+15 minutes'));

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

$vnp_Url = VNP_URL . "?" . $query;
if (defined('VNP_HASH_SECRET')) {
    $vnpSecureHash = hash_hmac('sha512', $hashdata, VNP_HASH_SECRET);
    $vnp_Url .= 'vnp_SecureHash=' . $vnpSecureHash;
}

header('Location: ' . $vnp_Url);
die();
?>