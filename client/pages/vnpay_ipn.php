<?php
// FILE: vnpay_ipn.php
require_once __DIR__ . '/src/config.php';

$vnp_HashSecret = "8WOZ0DZ5QFV8800P2ZQYFYCU8D51EBL6"; // Lấy từ Bước 1
$inputData = array();
$returnData = array();
$data = $_REQUEST;

foreach ($inputData as $key => $value) {
    // (FIX) Chỉ hash các trường có giá trị
    if ($value !== null && $value !== '') {
        if ($i == 1) {
            $hashData = $hashData . '&' . $key . "=" . $value;
        } else {
            $hashData = $hashData . $key . "=" . $value;
            $i = 1;
        }
    }
}

$vnp_SecureHash = $inputData['vnp_SecureHash'];
unset($inputData['vnp_SecureHash']);
ksort($inputData);
$i = 0;
$hashData = "";
foreach ($inputData as $key => $value) {
    if ($i == 1) $hashData = $hashData . '&' . urlencode($key) . "=" . urlencode($value);
    else $hashData = $hashData . urlencode($key) . "=" . urlencode($value);
    $i = 1;
}

$secureHash = hash_hmac('sha512', $hashData, $vnp_HashSecret);

try {
    // Lấy ID đơn hàng thật
    $real_order_id = explode("_", $inputData['vnp_TxnRef'])[0];

    // 1. Kiểm tra chữ ký
    if ($secureHash == $vnp_SecureHash) {
        // 2. Kiểm tra trạng thái đơn hàng trong CSDL
        $stmt_check = $pdo->prepare("SELECT trang_thai FROM don_hang WHERE id = ?");
        $stmt_check->execute([$real_order_id]);
        $order_status = $stmt_check->fetchColumn();

        if ($order_status != NULL && $order_status == 'pending') {
            // 3. Kiểm tra mã giao dịch (vnp_ResponseCode)
            if ($inputData['vnp_ResponseCode'] == '00' && $inputData['vnp_TransactionStatus'] == '00') {
                // Thanh toán THÀNH CÔNG
                $sql_update_order = "UPDATE don_hang SET trang_thai = 'paid' WHERE id = ?";
                $pdo->prepare($sql_update_order)->execute([$real_order_id]);

                $sql_insert_payment = "INSERT INTO thanh_toan (don_hang_id, so_tien, phuong_thuc, trang_thai, ngay_thanh_toan, ma_giao_dich)
                                       VALUES (?, ?, 'VNPAY', 'success', NOW(), ?)";
                $pdo->prepare($sql_insert_payment)->execute([
                    $real_order_id, 
                    $inputData['vnp_Amount'] / 100, // Chia 100 để về VND
                    $inputData['vnp_TransactionNo']
                ]);
                
                $returnData['RspCode'] = '00';
                $returnData['Message'] = 'Confirm Success';
            } else {
                // Thanh toán THẤT BẠI
                $sql_update_order = "UPDATE don_hang SET trang_thai = 'failed' WHERE id = ?";
                $pdo->prepare($sql_update_order)->execute([$real_order_id]);
                
                $returnData['RspCode'] = '00'; // Vẫn trả 00 vì đã xử lý
                $returnData['Message'] = 'Confirm Success (Failed Order)';
            }
        } else {
            // Đơn hàng đã được cập nhật rồi
            $returnData['RspCode'] = '02';
            $returnData['Message'] = 'Order already confirmed';
        }
    } else {
        // Sai chữ ký
        $returnData['RspCode'] = '97';
        $returnData['Message'] = 'Invalid Checksum';
    }
} catch (Exception $e) {
    error_log("Lỗi VNPAY IPN: " . $e->getMessage());
    $returnData['RspCode'] = '99';
    $returnData['Message'] = 'Unknown error';
}

echo json_encode($returnData);
?>