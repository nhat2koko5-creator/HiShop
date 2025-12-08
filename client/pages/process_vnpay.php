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

// 2. LẤY DỮ LIỆU
$ho_ten = $_POST['ho_ten'] ?? 'Khach';
$so_dien_thoai = $_POST['so_dien_thoai'] ?? '0987654321';
$dia_chi = $_POST['dia_chi'] ?? 'Hanoi';
$ghi_chu = $_POST['ghi_chu'] ?? '';
$order_type = $_POST['order_type'] ?? 'cart';
$payment_method = $_POST['payment_method'] ?? 'cod';

// --- [QUAN TRỌNG] LOGIC THANH TOÁN LẠI (REPAY) ---
if (isset($_GET['repay_order_id'])) {
    $repay_id = (int)$_GET['repay_order_id'];
    
    // Kiểm tra kỹ lưỡng:
    // 1. Đúng chủ đơn hàng (user_id)
    // 2. Trạng thái thanh toán phải là 'Chưa thanh toán'
    // 3. Trạng thái đơn KHÔNG được là 'Đã hủy'
    $stmt = $pdo->prepare("
        SELECT * FROM don_hang 
        WHERE id = ? 
        AND nguoi_dung_id = ? 
        AND trang_thai_thanh_toan = 'Chưa thanh toán'
        AND trang_thai_don_hang != 'Đã hủy'
    ");
    $stmt->execute([$repay_id, $user_id]);
    $order = $stmt->fetch();

    if (!$order) {
        // Nếu không tìm thấy đơn hợp lệ -> Chuyển hướng về trang lỗi hoặc thông báo
        echo "<script>
            alert('Không thể thanh toán lại đơn hàng này (Đơn không tồn tại, đã thanh toán, hoặc đã bị hủy).');
            window.location.href = 'index.php?page=account&section=orders';
        </script>";
        exit;
    }

    // Lấy số tiền cần thanh toán
    $final_total = $order['tong_tien'];
    $order_id = $order['id'];
    
    // Chuyển hướng sang tạo URL VNPay
    goto vnpay_config; 
}
// -----------------------------------------------------

// ... (Các phần xử lý Mua mới / Giỏ hàng giữ nguyên) ...
// ... (Đoạn code từ dòng 39 đến 119 của file cũ - Logic tạo đơn mới) ...

// (Tôi copy lại đoạn tạo đơn mới ở đây để file hoàn chỉnh)
$subtotal = 0;
$order_items = [];

if ($order_type === 'buy_now') {
    if (!isset($_SESSION['buy_now_item'])) die("Lỗi session: Không tìm thấy sản phẩm.");
    $item = $_SESSION['buy_now_item'];
    $sp_id = $item['san_pham_id'] ?? $item['id'];
    $variant_id = $item['variant_id'] ?? $item['bien_the_id'] ?? null;
    $order_items[] = ['san_pham_id' => $sp_id, 'bien_the_id' => $variant_id, 'so_luong' => $item['so_luong'], 'gia' => $item['gia']];
    $subtotal = $item['gia'] * $item['so_luong'];
} else {
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


// ==============================================================================
// [MỚI] BẮT BUỘC: KIỂM TRA TỒN KHO LẦN CUỐI TRƯỚC KHI TẠO ĐƠN
// Lý do: Tránh trường hợp khách hàng treo trang checkout quá lâu, sản phẩm đã bị người khác mua hết.
// ==============================================================================
foreach ($order_items as $check_item) {
    $id_bien_the_check = $check_item['bien_the_id'];
    $sl_mua = $check_item['so_luong'];

    // Lấy số lượng thực tế trong database
    $stmtCheckStock = $pdo->prepare("SELECT so_luong_ton, san_pham_id FROM bien_the_san_pham WHERE id = ?");
    $stmtCheckStock->execute([$id_bien_the_check]);
    $stock_data = $stmtCheckStock->fetch(PDO::FETCH_ASSOC);

    // Lấy tên sản phẩm để báo lỗi cho thân thiện
    $stmtName = $pdo->prepare("SELECT ten FROM san_pham WHERE id = ?");
    $stmtName->execute([$stock_data['san_pham_id']]);
    $prod_name = $stmtName->fetchColumn();

    // 1. Kiểm tra sản phẩm có tồn tại không
    if (!$stock_data) {
        echo "<script>
            alert('Lỗi: Sản phẩm \"$prod_name\" có thể đã bị xóa hoặc ngừng kinh doanh.');
            window.location.href = 'index.php?page=cart';
        </script>";
        exit; // Dừng ngay lập tức
    }

    // 2. Kiểm tra số lượng tồn
    if ($stock_data['so_luong_ton'] < $sl_mua) {
        $sl_con_lai = $stock_data['so_luong_ton'];
        echo "<script>
            alert('Rất tiếc! Sản phẩm \"$prod_name\" vừa hết hàng hoặc không đủ số lượng.\\n(Hiện chỉ còn: $sl_con_lai, Bạn đặt: $sl_mua).\\nVui lòng cập nhật lại giỏ hàng.');
            window.location.href = 'index.php?page=cart';
        </script>";
        exit; // Dừng ngay lập tức
    }
}
// ==============================================================================
try {
    $pdo->beginTransaction();
    $sql = "INSERT INTO don_hang (nguoi_dung_id, ho_ten_nguoi_nhan, sdt_nguoi_nhan, dia_chi_giao_hang, ghi_chu, tong_tien, trang_thai_don_hang, trang_thai_thanh_toan, ma_khuyen_mai_id, phuong_thuc_thanh_toan, ngay_dat) VALUES (?, ?, ?, ?, ?, ?, 'Chờ xử lý', 'Chưa thanh toán', ?, ?, NOW())";
    $payment_method_text = ($payment_method == 'vnpay') ? 'VNPAY' : 'COD';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id, $ho_ten, $so_dien_thoai, $dia_chi, $ghi_chu, $final_total, $coupon_id, $payment_method_text]);
    $order_id = $pdo->lastInsertId();

    $stmt_dt = $pdo->prepare("INSERT INTO chi_tiet_don_hang (don_hang_id, san_pham_id, bien_the_id, so_luong, don_gia) VALUES (?, ?, ?, ?, ?)");
    foreach ($order_items as $it) {
        $stmt_dt->execute([$order_id, $it['san_pham_id'], $it['bien_the_id'], $it['so_luong'], $it['gia']]);
    }

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

if ($payment_method === 'cod') {
    header('Location: index.php?page=confirmation&id=' . $order_id);
    exit;
}

// --- CẤU HÌNH VNPAY (DÙNG CHUNG CHO CẢ MUA MỚI VÀ REPAY) ---
vnpay_config: 

$vnp_TxnRef = $order_id . "_" . time(); // [QUAN TRỌNG] Thêm time() để mã không trùng nếu thanh toán lại nhiều lần
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