<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'src/config.php'; 
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

// 3. CHUẨN HÓA DỮ LIỆU SẢN PHẨM
$subtotal = 0;
$order_items = [];

if ($order_type === 'buy_now') {
    // --- TRƯỜNG HỢP 1: MUA NGAY ---
    if (!isset($_SESSION['buy_now_item'])) {
        die("Lỗi session: Không tìm thấy sản phẩm mua ngay. Vui lòng thử lại.");
    }
    $item = $_SESSION['buy_now_item'];
    
    // Fix lỗi: Đảm bảo lấy đúng ID sản phẩm (Parent ID)
    $sp_id = null;
    if (!empty($item['san_pham_id'])) {
        $sp_id = $item['san_pham_id'];
    } elseif (!empty($item['id'])) {
        // Trong trường hợp session lưu 'id' là id sản phẩm
        $sp_id = $item['id']; 
    }

    if (!$sp_id) die("Lỗi dữ liệu: Không xác định được ID sản phẩm (Mua ngay).");

    $order_items[] = [
        'san_pham_id' => $sp_id,
        'so_luong'    => $item['so_luong'],
        'gia'         => $item['gia']
    ];
    $subtotal = $item['gia'] * $item['so_luong'];

} else {
    // Thay vì dùng hàm getCartItemsAndTotal, ta query trực tiếp để lấy ID chuẩn xác từ DB
    // Logic: Lấy san_pham_id từ bảng gio_hang, lấy giá từ bien_the_san_pham (nếu có) hoặc san_pham
    try {
        $sql_cart = "
            SELECT 
                gh.san_pham_id, 
                gh.so_luong,
                -- Ưu tiên giá biến thể, nếu không có lấy giá gốc
                COALESCE(v.gia, p.gia) as gia_chuan
            FROM gio_hang gh
            JOIN san_pham p ON gh.san_pham_id = p.id
            LEFT JOIN bien_the_san_pham v ON gh.bien_the_id = v.id
            WHERE gh.nguoi_dung_id = ?
        ";
        
        $stmt_cart = $pdo->prepare($sql_cart);
        $stmt_cart->execute([$user_id]);
        $raw_items = $stmt_cart->fetchAll(PDO::FETCH_ASSOC);

        foreach ($raw_items as $item) {
            $order_items[] = [
                'san_pham_id' => $item['san_pham_id'], // Cột này chắc chắn tồn tại và đúng chuẩn DB
                'so_luong'    => $item['so_luong'],
                'gia'         => $item['gia_chuan']
            ];
            $subtotal += $item['gia_chuan'] * $item['so_luong'];
        }

    } catch (Exception $e) {
        die("Lỗi lấy dữ liệu giỏ hàng: " . $e->getMessage());
    }
}

// Kiểm tra lần cuối
if (empty($order_items)) {
    die("Giỏ hàng trống hoặc dữ liệu sản phẩm không hợp lệ.");
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

// 4. LƯU ĐƠN HÀNG VÀO DB
try {
    $pdo->beginTransaction();
    
    // A. Insert đơn hàng cha
    $sql = "INSERT INTO don_hang (nguoi_dung_id, ho_ten_nguoi_nhan, sdt_nguoi_nhan, dia_chi_giao_hang, ghi_chu, tong_tien, trang_thai, trang_thai_thanh_toan, ma_khuyen_mai_id, ngay_dat) 
            VALUES (?, ?, ?, ?, ?, ?, 'pending', 'unpaid', ?, NOW())";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id, $ho_ten, $so_dien_thoai, $dia_chi, $ghi_chu, $final_total, $coupon_id]);
    $order_id = $pdo->lastInsertId();

    // B. Insert chi tiết đơn hàng
    $stmt_dt = $pdo->prepare("INSERT INTO chi_tiet_don_hang (don_hang_id, san_pham_id, so_luong, don_gia) VALUES (?, ?, ?, ?)");
    
    foreach ($order_items as $it) {
        // Kiểm tra kỹ ID sản phẩm trước khi insert để tránh lỗi 1452
        if (empty($it['san_pham_id'])) {
            throw new Exception("Dữ liệu sản phẩm bị lỗi (Thiếu ID).");
        }
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

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // Xử lý lỗi cụ thể để báo người dùng
    if ($e->getCode() == '23000' && strpos($e->getMessage(), '1452') !== false) {
        // Lỗi này nghĩa là san_pham_id trong giỏ hàng không khớp với id trong bảng san_pham
        die("Lỗi hệ thống: Một trong các sản phẩm bạn chọn không còn tồn tại trong kho (ID không khớp). Vui lòng xóa giỏ hàng và chọn lại.");
    }
    die("Lỗi Database: " . $e->getMessage());
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("Lỗi không xác định: " . $e->getMessage());
}

// 5. CẤU HÌNH THANH TOÁN VNPAY
$vnp_TxnRef = $order_id; 
$vnp_OrderInfo = "Thanh toan don hang " . $order_id;
$vnp_OrderType = "other";
$vnp_Amount = (int)$final_total * 100; 
$vnp_Locale = "vn";
$vnp_IpAddr = "127.0.0.1"; 
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