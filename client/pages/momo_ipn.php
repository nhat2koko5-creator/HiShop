<?php
// FILE: momo_ipn.php
require_once __DIR__ . '/src/config.php';

// (GIẢ LẬP) Lấy Secret Key (bạn phải lưu key này an toàn)
$momo_secretKey = "YOUR_SECRET_KEY";

// Lấy dữ liệu MoMo gửi về
$body = file_get_contents('php://input');
$data = json_decode($body, true);

if (!$data) {
    http_response_code(400);
    echo "Invalid data";
    exit;
}

// Tách các trường dữ liệu
$partnerCode = $data['partnerCode'];
$orderId = $data['orderId'];
$requestId = $data['requestId'];
$amount = $data['amount'];
$orderInfo = $data['orderInfo'];
$orderType = $data['orderType'];
$transId = $data['transId'];
$resultCode = $data['resultCode'];
$message = $data['message'];
$payType = $data['payType'];
$responseTime = $data['responseTime'];
$extraData = $data['extraData'];
$signature = $data['signature'];

// Tạo chuỗi rawHash để kiểm tra chữ ký
$rawHash = "accessKey=" . $data['accessKey'] .
           "&amount=" . $amount .
           "&extraData=" . $extraData .
           "&message=" . $message .
           "&orderId=" . $orderId .
           "&orderInfo=" . $orderInfo .
           "&orderType=" . $orderType .
           "&partnerCode=" . $partnerCode .
           "&payType=" . $payType .
           "&requestId=" . $requestId .
           "&responseTime=" . $responseTime .
           "&resultCode=" . $resultCode .
           "&transId=" . $transId;

$localSignature = hash_hmac("sha256", $rawHash, $momo_secretKey);

// --- KIỂM TRA CHỮ KÝ VÀ CẬP NHẬT CSDL ---
if ($localSignature === $signature) {
    try {
        // Lấy ID đơn hàng thật (bỏ phần timestamp)
        $real_order_id = explode("_", $orderId)[0];
        
        if ($resultCode == 0) {
            // Thanh toán THÀNH CÔNG
            
            // 1. Cập nhật `don_hang`
            $sql_update_order = "UPDATE don_hang SET trang_thai = 'paid' 
                                 WHERE id = ? AND trang_thai = 'pending'";
            $stmt_order = $pdo->prepare($sql_update_order);
            $stmt_order->execute([$real_order_id]);

            // 2. Thêm vào bảng `thanh_toan`
            $sql_insert_payment = "INSERT INTO thanh_toan (don_hang_id, so_tien, phuong_thuc, trang_thai, ngay_thanh_toan, ma_giao_dich)
                                   VALUES (?, ?, 'MoMo', 'success', NOW(), ?)";
            $stmt_payment = $pdo->prepare($sql_insert_payment);
            $stmt_payment->execute([$real_order_id, $amount, (string) $transId]);

        } else {
            // Thanh toán THẤT BẠI
            $sql_update_order = "UPDATE don_hang SET trang_thai = 'failed' 
                                 WHERE id = ? AND trang_thai = 'pending'";
            $stmt_order = $pdo->prepare($sql_update_order);
            $stmt_order->execute([$real_order_id]);
        }

        // Phản hồi cho MoMo server
        http_response_code(204);

    } catch (Exception $e) {
        // Lỗi CSDL
        error_log("Lỗi IPN: " . $e->getMessage());
        http_response_code(500);
    }
} else {
    // Sai chữ ký
    error_log("Lỗi IPN: Sai chữ ký");
    http_response_code(400);
    echo "Invalid signature";
}
?>