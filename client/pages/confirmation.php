<?php
// FILE: client/pages/confirmation.php

// Kiểm tra ID đơn hàng trên URL
$order_id = $_GET['id'] ?? 0;

if (empty($order_id)) {
    // Nếu không có ID, quay về trang chủ
    echo "<script>window.location.href='index.php';</script>";
    exit;
}

// Truy vấn thông tin đơn hàng mới nhất từ CSDL
$stmt = $pdo->prepare("SELECT * FROM don_hang WHERE id = ?");
$stmt->execute([$order_id]);
$order = $stmt->fetch();

if (!$order) {
    echo "<div class='container' style='padding: 50px;'><p>Đơn hàng không tồn tại.</p></div>";
    require_once 'client/layouts/footer.php'; 
    exit;
}

// Lấy thông tin trạng thái để xử lý hiển thị
$status_don = $order['trang_thai_don_hang'];       // Ví dụ: 'Chờ xử lý', 'Đã hủy'
$status_tien = $order['trang_thai_thanh_toan'];    // Ví dụ: 'Chưa thanh toán', 'Đã thanh toán'
$method = $order['phuong_thuc_thanh_toan'];        // 'COD' hoặc 'VNPAY'

// Xác định trạng thái hiển thị (Success / Pending / Failed)
$is_success = false;
$message_title = "";
$message_desc = "";
$status_mode = ""; // success-mode | error-mode | warning-mode

if ($status_don == 'Đã hủy') {
    // 1. TRƯỜNG HỢP ĐƠN BỊ HỦY
    $is_success = false;
    $status_mode = "error-mode";
    $message_title = "Đơn hàng đã bị hủy";
    $message_desc = "Rất tiếc, đơn hàng này đã bị hủy. Vui lòng đặt lại hoặc liên hệ CSKH.";

} elseif ($method == 'COD') {
    // 2. TRƯỜNG HỢP COD (Luôn báo thành công nếu chưa hủy)
    $is_success = true;
    $status_mode = "success-mode";
    $message_title = "Đặt hàng thành công!";
    $message_desc = "Cảm ơn bạn đã mua hàng. Chúng tôi sẽ sớm liên hệ để xác nhận đơn hàng của bạn.";

} elseif ($method == 'VNPAY') {
    // 3. TRƯỜNG HỢP VNPAY
    if ($status_tien == 'Đã thanh toán') {
        $is_success = true;
        $status_mode = "success-mode";
        $message_title = "Thanh toán thành công!";
        $message_desc = "Giao dịch đã được xác nhận. Đơn hàng của bạn đang được chuẩn bị.";
    } else {
        // Chưa thanh toán
        $is_success = false;
        $status_mode = "warning-mode"; // Cần thêm CSS cho warning-mode nếu muốn màu vàng
        $message_title = "Chưa hoàn tất thanh toán";
        $message_desc = "Đơn hàng đã được tạo nhưng chưa thanh toán. Vui lòng thực hiện thanh toán lại.";
    }
} else {
    // Trường hợp khác
    $is_success = true;
    $status_mode = "success-mode";
    $message_title = "Đã tiếp nhận đơn hàng";
    $message_desc = "Đơn hàng #$order_id đang được hệ thống xử lý.";
}
?>

<link rel="stylesheet" href="assets/css/client/checkout.css">

<style>
    .warning-mode .pr-svg-icon { color: #f59e0b; }
    .warning-mode .pr-title { color: #f59e0b; }
</style>

<div class="payment-result-container">
    <div class="payment-result-card <?= $status_mode ?>">
        
        <div class="pr-header">
            <div class="pr-icon-wrapper">
                <?php if ($status_mode == 'success-mode'): ?>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="pr-svg-icon">
                        <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12zm13.36-1.814a.75.75 0 10-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.14-.094l3.75-5.25z" clip-rule="evenodd" />
                    </svg>
                <?php elseif ($status_mode == 'error-mode'): ?>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="pr-svg-icon">
                        <path fill-rule="evenodd" d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25zm-1.72 6.97a.75.75 0 10-1.06 1.06L10.94 12l-1.72 1.72a.75.75 0 101.06 1.06L12 13.06l1.72 1.72a.75.75 0 101.06-1.06L13.06 12l1.72-1.72a.75.75 0 10-1.06-1.06L12 10.94l-1.72-1.72z" clip-rule="evenodd" />
                    </svg>
                <?php else: ?>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="pr-svg-icon">
                        <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12zM12 8.25a.75.75 0 01.75.75v3.75a.75.75 0 01-1.5 0V9a.75.75 0 01.75-.75zm0 8.25a.75.75 0 100-1.5.75.75 0 000 1.5z" clip-rule="evenodd" />
                    </svg>
                <?php endif; ?>
            </div>
            <h2 class="pr-title"><?= htmlspecialchars($message_title) ?></h2>
            <p class="pr-desc"><?= htmlspecialchars($message_desc) ?></p>
        </div>

        <div class="pr-key-info">
            <div class="pr-info-item">
                <span class="pr-label">Mã đơn hàng</span>
                <span class="pr-value highlight">#<?= htmlspecialchars($order['id']) ?></span>
            </div>
            <div class="pr-info-item">
                <span class="pr-label">Tổng thanh toán</span>
                <span class="pr-value total-amount"><?= number_format($order['tong_tien'], 0, ',', '.') ?>₫</span>
            </div>
            <div class="pr-info-item">
                <span class="pr-label">Phương thức</span>
                <span class="pr-value">
                    <?php if($method == 'VNPAY'): ?>
                        VNPAY <img src="assets/img/vnpay.jpg" alt="VNPAY" style="height: 20px; vertical-align: middle; margin-left: 5px;">
                    <?php else: ?>
                        <?= htmlspecialchars($method) ?>
                    <?php endif; ?>
                </span>
            </div>
            <div class="pr-info-item">
                <span class="pr-label">Trạng thái</span>
                <span class="pr-value" style="color: #2563eb;"><?= htmlspecialchars($status_don) ?></span>
            </div>
        </div>

        <div class="pr-actions">
            <?php if (!$is_success && $status_don != 'Đã hủy' && $method == 'VNPAY'): ?>
                <a href="client/pages/process_vnpay.php?repay_order_id=<?= $order['id'] ?>" class="btn btn-primary btn-block">
                    Thanh toán ngay
                </a>
            <?php else: ?>
                <a href="index.php?page=order_detail&id=<?= $order['id'] ?>" class="btn btn-primary btn-block">
                    Xem chi tiết đơn hàng
                </a>
            <?php endif; ?>
            
            <a href="index.php?page=home" class="btn btn-outline btn-block">
                Tiếp tục mua sắm
            </a>
        </div>

    </div>
</div>