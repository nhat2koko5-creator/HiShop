<?php
// FILE: client/pages/process_payment.php
// (File này không cần header/footer vì nó chỉ xử lý logic)

// Bắt buộc phải start session để lấy user_id
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Tải config và functions
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/functions.php';

// --- BẢO MẬT: Kiểm tra điều kiện ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user_id'])) {
    die("Truy cập không hợp lệ.");
}

$user_id = $_SESSION['user_id'];

// --- LẤY DỮ LIỆU ---
$cart = getCartItemsAndTotal($pdo, $user_id);
if (empty($cart['items'])) {
    die("Giỏ hàng rỗng.");
}

$ho_ten = trim($_POST['ho_ten'] ?? '');
$so_dien_thoai = trim($_POST['so_dien_thoai'] ?? '');
$dia_chi = trim($_POST['dia_chi'] ?? '');
$ghi_chu = trim($_POST['ghi_chu'] ?? '');
$total_amount = $cart['total'];

// --- XỬ LÝ LƯU ĐƠN HÀNG VÀO CSDL ---
try {
    $pdo->beginTransaction();

    // (Tùy chọn) Lưu địa chỉ vào bảng `dia_chi` nếu bạn muốn user quản lý sổ địa chỉ
    // ...

    // 1. Tạo đơn hàng MỚI (status: 'pending')
    $sql_don_hang = "INSERT INTO don_hang (nguoi_dung_id, ngay_dat, tong_tien, trang_thai, ho_ten_nguoi_nhan, sdt_nguoi_nhan, dia_chi_giao_hang, ghi_chu)
                     VALUES (?, NOW(), ?, 'pending', ?, ?, ?, ?)";
    $stmt_don_hang = $pdo->prepare($sql_don_hang);
    $stmt_don_hang->execute([$user_id, $total_amount, $ho_ten, $so_dien_thoai, $dia_chi, $ghi_chu]);
    
    // Lấy ID của đơn hàng vừa tạo
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
        // (Nâng cao) Trừ tồn kho `san_pham` hoặc `chi_tiet_kho_hang` tại đây
    }

    // 3. Xóa giỏ hàng của người dùng
    $pdo->prepare("DELETE FROM gio_hang WHERE nguoi_dung_id = ?")->execute([$user_id]);

    // 4. Hoàn tất giao dịch CSDL
    $pdo->commit();

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Lỗi khi tạo đơn hàng: " . $e->getMessage());
    die("Đã xảy ra lỗi khi tạo đơn hàng. Vui lòng thử lại.");
}

// --- GỌI API MOMO (PHẦN QUAN TRỌNG) ---

// Đây là nơi bạn tích hợp MoMo SDK (tải qua Composer)
// require 'vendor/autoload.php';

// Các thông tin này bạn lấy từ Dashboard của MoMo
$momo_endpoint = "https://test-payment.momo.vn/v2/gateway/api/create";
$momo_partnerCode = "YOUR_PARTNER_CODE";
$momo_accessKey = "YOUR_ACCESS_KEY";
$momo_secretKey = "YOUR_SECRET_KEY";

$orderInfo = "Thanh toán đơn hàng HIShop #" . $order_id;
$amount = (string) $total_amount;
$orderId = (string) $order_id . "_" . time(); // Mã đơn hàng (phải là duy nhất)
$requestId = time() . "";
$requestType = "captureWallet";

// URL MoMo sẽ gọi về server của bạn (Bước 4)
$notifyUrl = "http://localhost/HiShop/momo_ipn.php"; 
// URL MoMo trả khách hàng về (Bước 5)
$returnUrl = "http://localhost/HiShop/index.php?page=confirmation"; 
$extraData = ""; // Dữ liệu bổ sung nếu cần

// Tạo chữ ký (Signature)
$rawHash = "accessKey=" . $momo_accessKey .
           "&amount=" . $amount .
           "&extraData=" . $extraData .
           "&ipnUrl=" . $notifyUrl .
           "&orderId=" . $orderId .
           "&orderInfo=" . $orderInfo .
           "&partnerCode=" . $momo_partnerCode .
           "&redirectUrl=" . $returnUrl .
           "&requestId=" . $requestId .
           "&requestType=" . $requestType;

$signature = hash_hmac("sha256", $rawHash, $momo_secretKey);

// Dữ liệu gửi đi
$data = [
    'partnerCode' => $momo_partnerCode,
    'accessKey' => $momo_accessKey,
    'requestId' => $requestId,
    'amount' => $amount,
    'orderId' => $orderId,
    'orderInfo' => $orderInfo,
    'redirectUrl' => $returnUrl,
    'ipnUrl' => $notifyUrl,
    'extraData' => $extraData,
    'requestType' => $requestType,
    'signature' => $signature,
    'lang' => 'vi'
];

// Gửi request bằng cURL
$ch = curl_init($momo_endpoint);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen(json_encode($data))
]);

$response = curl_exec($ch);
curl_close($ch);
$result = json_decode($response, true);

// --- CHUYỂN HƯỚNG NGƯỜI DÙNG ---
if (isset($result['payUrl'])) {
    // Chuyển hướng người dùng đến link thanh toán của MoMo
    header('Location: ' . $result['payUrl']);
    exit;
} else {
    // Xử lý lỗi
    error_log("Lỗi MoMo: " . ($result['message'] ?? 'Unknown error'));
    die("Không thể tạo yêu cầu thanh toán. Vui lòng thử lại.");
}
?>