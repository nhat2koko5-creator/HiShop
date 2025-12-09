<?php
// FILE: client/pages/process_vnpay.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/functions.php'; 

date_default_timezone_set('Asia/Ho_Chi_Minh');

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?page=login');
    exit;
}

$user_id = $_SESSION['user_id'];

// --- 1. LẤY DỮ LIỆU TỪ FORM ---
$ho_ten = $_POST['ho_ten'] ?? 'Khach';
$so_dien_thoai = $_POST['so_dien_thoai'] ?? '';
$dia_chi = $_POST['dia_chi'] ?? '';
$ghi_chu = $_POST['ghi_chu'] ?? '';
$order_type = $_POST['order_type'] ?? 'cart';
$payment_method = $_POST['payment_method'] ?? 'cod';

$customer_info = [
    'ho_ten' => $ho_ten,
    'sdt' => $so_dien_thoai,
    'dia_chi' => $dia_chi,
    'ghi_chu' => $ghi_chu
];

// --- 2. CHUẨN BỊ SẢN PHẨM MUA & TÍNH TỔNG TIỀN TẠM TÍNH ---
$order_items = [];
$temp_subtotal = 0; // Biến tính tổng tiền hàng trước khi giảm giá

if ($order_type === 'buy_now') {
    if (!isset($_SESSION['buy_now_item'])) die("Lỗi: Không tìm thấy sản phẩm mua ngay.");
    $item = $_SESSION['buy_now_item'];
    
    $order_items[] = [
        'san_pham_id' => $item['san_pham_id'] ?? $item['id'],
        'bien_the_id' => $item['variant_id'] ?? $item['bien_the_id'] ?? null,
        'so_luong'    => $item['so_luong'],
        'gia'         => $item['gia'],
        'ten_san_pham'=> $item['ten']
    ];
    $temp_subtotal += $item['gia'] * $item['so_luong'];

} else {
    $selected_ids_input = $_POST['selected_ids'] ?? '';
    $selected_ids = !empty($selected_ids_input) ? explode(',', $selected_ids_input) : null;

    $cartData = getCartItemsAndTotal($pdo, $user_id, $selected_ids);
    $raw_items = $cartData['items'];

    foreach ($raw_items as $item) {
        $order_items[] = [
            'san_pham_id' => $item['id'] ?? $item['san_pham_id'],
            'bien_the_id' => $item['bien_the_id'],
            'so_luong'    => $item['so_luong'],
            'gia'         => $item['gia'],
            'ten_san_pham'=> $item['ten_san_pham']
        ];
        $temp_subtotal += $item['gia'] * $item['so_luong'];
    }
}

if (empty($order_items)) {
    echo "<script>alert('Giỏ hàng trống!'); window.location.href='index.php?page=cart';</script>";
    exit;
}

// --- 3. [QUAN TRỌNG] TÍNH TOÁN GIẢM GIÁ TỪ SESSION ---
$discount_amount = 0;
$coupon_code = null;

if (isset($_SESSION['promo']) && is_array($_SESSION['promo'])) {
    $coupon = $_SESSION['promo'];
    $coupon_code = $coupon['code']; // Lấy mã code (ví dụ: WELCOME100K)

    // Tính số tiền được giảm dựa trên tổng tiền hàng (temp_subtotal)
    if ($coupon['type'] == 'phan_tram') {
        $discount_amount = ($temp_subtotal * $coupon['value']) / 100;
    } elseif ($coupon['type'] == 'tien_mat') {
        $discount_amount = $coupon['value'];
    }

    // Không cho phép giảm giá vượt quá tổng tiền
    if ($discount_amount > $temp_subtotal) {
        $discount_amount = $temp_subtotal;
    }
}

// --- 4. GỌI HÀM TẠO ĐƠN & TRỪ KHO ---
// Truyền thêm $discount_amount và $coupon_code vào hàm
$result = processCheckout($pdo, $user_id, $order_items, $customer_info, $payment_method, $discount_amount, $coupon_code);

if (!$result['success']) {
    echo "<script>alert('Lỗi đặt hàng: " . $result['message'] . "'); window.location.href='index.php?page=cart';</script>";
    exit;
}

$order_id = $result['order_id'];
$final_total = $result['total']; // Đây là giá cuối cùng (đã trừ giảm giá)

// Xóa session
if ($order_type === 'buy_now') unset($_SESSION['buy_now_item']);
unset($_SESSION['promo']); // Xóa mã giảm giá sau khi dùng xong

// --- 5. ĐIỀU HƯỚNG THANH TOÁN ---

// A. COD
if ($payment_method === 'cod') {
    header("Location: index.php?page=confirmation&id=$order_id");
    exit;
}

// B. VNPAY
if ($payment_method === 'vnpay') {
    $vnp_TxnRef = $order_id;
    $vnp_OrderInfo = "Thanh toan don hang #" . $order_id;
    $vnp_OrderType = "other";
    $vnp_Amount = (int)$final_total * 100; // Sử dụng giá đã giảm
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
}
?>