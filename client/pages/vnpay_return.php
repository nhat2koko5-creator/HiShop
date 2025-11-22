<?php
// FILE: client/pages/vnpay_return.php (ĐÃ SỬA LỖI HIỂN THỊ)
require_once 'src/config.php';

// 1. Lấy dữ liệu trả về từ VNPAY
$vnp_SecureHash = $_GET['vnp_SecureHash'] ?? '';
$inputData = array();
foreach ($_GET as $key => $value) {
    if (substr($key, 0, 4) == "vnp_") {
        $inputData[$key] = $value;
    }
}

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
    $query .= urlencode($key) . "=" . urlencode($value) . '&';
}

$secureHash = hash_hmac('sha512', $hashData, VNP_HASH_SECRET);

// 2. Gán giá trị cho các biến (FIX LỖI UNDEFINED VARIABLE)
$order_id = $_GET['vnp_TxnRef'] ?? 'Không xác định';
$amount = isset($_GET['vnp_Amount']) ? $_GET['vnp_Amount'] / 100 : 0;
$vnp_BankCode = $_GET['vnp_BankCode'] ?? 'Không xác định';
$vnp_TransactionNo = $_GET['vnp_TransactionNo'] ?? 'Không xác định';
$order_desc = $_GET['vnp_OrderInfo'] ?? 'Không có nội dung';
$vnp_ResponseCode = $_GET['vnp_ResponseCode'] ?? '';

// 3. Xác định trạng thái
$status_msg = "";
$status_class = "";
$status_icon = "";

if ($secureHash == $vnp_SecureHash) {
    if ($vnp_ResponseCode == '00') {
        $status_msg = "Giao dịch thành công!";
        $status_class = "success";
        $status_icon = "✅";
    } else {
        $status_msg = "Giao dịch thất bại!";
        $status_class = "error";
        $status_icon = "❌";
    }
} else {
    $status_msg = "Chữ ký không hợp lệ!";
    $status_class = "error";
    $status_icon = "⚠️";
}
?>

<?php require_once 'client/layouts/header.php'; ?>
<div class="container">
    <div class="result-box">
        <div class="result-icon <?= $status_class ?>"><?= $status_icon ?></div>
        <h2 class="result-title"><?= $status_msg ?></h2>
        
        <?php if ($secureHash == $vnp_SecureHash): ?>
        <div class="result-details">
            <div class="detail-row">
                <span class="detail-label">Mã đơn hàng:</span>
                <span class="detail-value">#<?= htmlspecialchars($order_id) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Số tiền:</span>
                <span class="detail-value"><?= number_format($amount, 0, ',', '.') ?>₫</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Ngân hàng:</span>
                <span class="detail-value"><?= htmlspecialchars($vnp_BankCode) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Mã giao dịch:</span>
                <span class="detail-value"><?= htmlspecialchars($vnp_TransactionNo) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Nội dung:</span>
                <span class="detail-value"><?= htmlspecialchars($order_desc) ?></span>
            </div>
            <?php if ($vnp_ResponseCode != '00'): ?>
            <div class="detail-row">
                <span class="detail-label" style="color:red">Lỗi:</span>
                <span class="detail-value" style="color:red">Mã lỗi <?= htmlspecialchars($vnp_ResponseCode) ?></span>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="action-buttons">
            <a href="index.php?page=home" class="btn btn-outline">Về trang chủ</a>
            <a href="index.php?page=product_list" class="btn btn-primary">Tiếp tục mua sắm</a>
        </div>
    </div>
</div>

<?php require_once 'client/layouts/footer.php'; ?>