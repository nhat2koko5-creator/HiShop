<?php
// FILE: client/pages/vnpay_return.php (ĐÃ SỬA THEO TÀI LIỆU)
require_once 'client/layouts/header.php';

// (FIX 1) LẤY KEY THẬT
$vnp_HashSecret = "8WOZ0DZ5QFV8800P2ZQYFYCU8D51EBL6"; 
$vnp_SecureHash = $_GET['vnp_SecureHash'];
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
}

$secureHash = hash_hmac('sha512', $hashData, $vnp_HashSecret);
$real_order_id = explode("_", $_GET['vnp_TxnRef'])[0];
$status = 'failed'; 

if ($secureHash == $vnp_SecureHash) {
    if ($_GET['vnp_ResponseCode'] == '00') {
        $status = 'paid';
    }
}
?>

<div class="container" style="padding: var(--spacing-64) 0; text-align: center;">
    <?php if ($status === 'paid'): // Thành công ?>
        <div class="confirmation-icon" style="background-color: var(--color-green);">
            <i class="fa-solid fa-check" style="color: white; font-size: 40px;"></i>
        </div>
        <h1 style="font: var(--font-h2); margin-top: var(--spacing-24);">Thanh toán thành công!</h1>
        <p style="font-size: 18px; color: var(--color-fg-muted); margin-top: var(--spacing-16);">
            Cảm ơn bạn đã mua hàng tại HIShop. Đơn hàng #<?= htmlspecialchars($real_order_id) ?> đã được thanh toán.
        </p>
        <a href="index.php?page=account" class="btn btn-primary" style="margin-top: var(--spacing-32);">Xem lịch sử đơn hàng</a>
    <?php else: // Thất bại ?>
        <div class="confirmation-icon" style="background-color: var(--color-red);">
            <i class="fa-solid fa-xmark" style="color: white; font-size: 40px;"></i>
        </div>
        <h1 style="font: var(--font-h2); margin-top: var(--spacing-24);">Thanh toán thất bại</h1>
        <p style="font-size: 18px; color: var(--color-fg-muted); margin-top: var(--spacing-16);">
            <?php if ($secureHash != $vnp_SecureHash): ?>
                Lỗi: Sai chữ ký. (Thông tin trả về không hợp lệ).
            <?php else: ?>
                Đã xảy ra lỗi trong quá trình thanh toán với VNPAY.
                (Mã lỗi: <?= htmlspecialchars($_GET['vnp_ResponseCode'] ?? 'unknown') ?>)
            <?php endif; ?>
        </p>
        <a href="index.php?page=cart" class="btn btn-outline" style="margin-top: var(--spacing-32);">Quay lại giỏ hàng</a>
    <?php endif; ?>
</div>

<?php 
require_once 'client/layouts/footer.php'; 
?>