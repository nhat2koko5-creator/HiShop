<?php
// FILE: vnpay_ipn.php
require_once __DIR__ . '/src/config.php';

// LẤY KEY TỪ CONFIG (Key đúng ELA...)
$vnp_HashSecret = VNP_HASH_SECRET; 

$inputData = array();
$returnData = array();

// Lấy dữ liệu từ VNPAY
foreach ($_GET as $key => $value) {
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

// Tạo mã hash chuẩn để so sánh
$secureHash = hash_hmac('sha512', $hashData, $vnp_HashSecret);

$vnp_Amount = $inputData['vnp_Amount'] / 100; 
$order_id = $inputData['vnp_TxnRef'];

try {
    // Kiểm tra chữ ký
    if ($secureHash === $vnp_SecureHash) {
        
        // Lấy đơn hàng
        $stmt = $pdo->prepare("SELECT id, tong_tien, trang_thai FROM don_hang WHERE id = ?");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($order) {
            // Kiểm tra số tiền
            if ((int)$order["tong_tien"] == (int)$vnp_Amount) {
                
                if ($order["trang_thai"] == 'pending') {
                    if ($inputData['vnp_ResponseCode'] == '00' && $inputData['vnp_TransactionStatus'] == '00') {
                        // Cập nhật thành công
                        $stmt_update = $pdo->prepare("UPDATE don_hang SET trang_thai = 'confirmed', trang_thai_thanh_toan = 'paid' WHERE id = ?");
                        $stmt_update->execute([$order_id]);
                        
                        // Lưu log thanh toán
                        $stmt_log = $pdo->prepare("INSERT INTO thanh_toan (don_hang_id, so_tien, phuong_thuc, trang_thai, ngay_thanh_toan, ma_giao_dich) VALUES (?, ?, 'VNPAY', 'success', NOW(), ?)");
                        $stmt_log->execute([$order_id, $vnp_Amount, $inputData['vnp_TransactionNo']]);

                        $returnData['RspCode'] = '00';
                        $returnData['Message'] = 'Confirm Success';
                    } else {
                        // Thanh toán lỗi
                        $stmt_update = $pdo->prepare("UPDATE don_hang SET trang_thai = 'failed' WHERE id = ?");
                        $stmt_update->execute([$order_id]);
                        
                        $returnData['RspCode'] = '00'; 
                        $returnData['Message'] = 'Confirm Success (Payment Failed)';
                    }
                } else {
                    $returnData['RspCode'] = '02';
                    $returnData['Message'] = 'Order already confirmed';
                }
            } else {
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
    $returnData['RspCode'] = '99';
    $returnData['Message'] = 'Unknow error';
}

echo json_encode($returnData);
?>