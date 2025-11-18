<?php
// FILE: vnpay_ipn.php (ĐÃ SỬA THEO TÀI LIỆU)
require_once __DIR__ . '/src/config.php';

// (FIX 1) LẤY KEY THẬT
$vnp_HashSecret = "8WOZ0DZ5QFV8800P2ZQYFYCU8D51EBL6"; 
$inputData = array();
$returnData = array();
$data = $_REQUEST;

foreach ($data as $key => $value) {
    if (substr($key, 0, 4) == "vnp_") {
        $inputData[$key] = $value;
    }
}

$vnp_SecureHash = $inputData['vnp_SecureHash'];
unset($inputData['vnp_SecureHash']);
ksort($inputData);
$i = 0;
$hashData = "";

foreach ($inputData as $key => $value) {
    if ($i == 1) {
        $hashData = $hashData . '&' . urlencode($key) . "=" . urlencode($value);
    } else {
        $hashData = $hashData . urlencode($key) . "=" . urlencode($value);
        $i = 1;
    }
}

$secureHash = hash_hmac('sha512', $hashData, $vnp_HashSecret);
$vnp_Amount = $inputData['vnp_Amount']/100; // Số tiền
$real_order_id = explode("_", $inputData['vnp_TxnRef'])[0];

try {
    //Check Orderid    
    if ($secureHash == $vnp_SecureHash) {
        // Lấy thông tin đơn hàng từ CSDL
        $stmt_check = $pdo->prepare("SELECT trang_thai, tong_tien FROM don_hang WHERE id = ?");
        $stmt_check->execute([$real_order_id]);
        $order = $stmt_check->fetch(PDO::FETCH_ASSOC);

        if ($order) {
            // Kiểm tra số tiền
            if($order["tong_tien"] == $vnp_Amount) {
                // Kiểm tra trạng thái
                if ($order["trang_thai"] == 'pending') {
                    if ($inputData['vnp_ResponseCode'] == '00' && $inputData['vnp_TransactionStatus'] == '00') {
                        // Thanh toán THÀNH CÔNG
                        $sql_update_order = "UPDATE don_hang SET trang_thai = 'paid' WHERE id = ?";
                        $pdo->prepare($sql_update_order)->execute([$real_order_id]);

                        $sql_insert_payment = "INSERT INTO thanh_toan (don_hang_id, so_tien, phuong_thuc, trang_thai, ngay_thanh_toan, ma_giao_dich)
                                               VALUES (?, ?, 'VNPAY', 'success', NOW(), ?)";
                        $pdo->prepare($sql_insert_payment)->execute([
                            $real_order_id, 
                            $vnp_Amount,
                            $inputData['vnp_TransactionNo']
                        ]);
                    } else {
                        // Thanh toán THẤT BẠI
                        $sql_update_order = "UPDATE don_hang SET trang_thai = 'failed' WHERE id = ?";
                        $pdo->prepare($sql_update_order)->execute([$real_order_id]);
                    }
                    $returnData['RspCode'] = '00';
                    $returnData['Message'] = 'Confirm Success';
                } else {
                    $returnData['RspCode'] = '02';
                    $returnData['Message'] = 'Order already confirmed';
                }
            }
            else {
                $returnData['RspCode'] = '04';
                $returnData['Message'] = 'Invalid amount';
            }
        } else {
            $returnData['RspCode'] = '01';
            $returnData['Message'] = 'Order not found';
        }
    } else {
        $returnData['RspCode'] = '97';
        $returnData['Message'] = 'Invalid signature';
    }
} catch (Exception $e) {
    error_log("Lỗi VNPAY IPN: " . $e->getMessage());
    $returnData['RspCode'] = '99';
    $returnData['Message'] = 'Unknown error';
}

echo json_encode($returnData);
?>