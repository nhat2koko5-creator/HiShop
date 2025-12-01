<?php
// FILE: client/pages/confirmation.php
require_once 'client/layouts/header.php';

// Lấy kết quả MoMo trả về qua URL
$resultCode = $_GET['resultCode'] ?? 'unknown';
$orderId_momo = $_GET['orderId'] ?? '';

// Lấy ID đơn hàng thật
$real_order_id = explode("_", $orderId_momo)[0];

// Truy vấn CSDL để lấy trạng thái MỚI NHẤT (do IPN cập nhật)
$status = 'pending';
if (!empty($real_order_id)) {
    $stmt = $pdo->prepare("SELECT trang_thai FROM don_hang WHERE id = ?");
    $stmt->execute([$real_order_id]);
    $status = $stmt->fetchColumn();
}

// Sử dụng style của trang 404/static để hiển thị
?>
<link rel="stylesheet" href="assets/css/confirmation.css">
<div class="container" style="padding: var(--spacing-64) 0; text-align: center;">
    
    <?php if ($status === 'paid'): // Thành công ?>
        
        <div class="confirmation-icon" style="background-color: var(--color-green);">
            <i class="fa-solid fa-check" style="color: white; font-size: 40px;"></i>
        </div>
        <h1 style="font: var(--font-h2); margin-top: var(--spacing-24);">Thanh toán thành công!</h1>
        <p style="font-size: 18px; color: var(--color-fg-muted); margin-top: var(--spacing-16);">
            Cảm ơn bạn đã mua hàng tại HIShop. Đơn hàng #<?= htmlspecialchars($real_order_id) ?> đang được xử lý.
        </p>
        <a href="index.php?page=account" class="btn btn-primary" style="margin-top: var(--spacing-32);">Xem lịch sử đơn hàng</a>

    <?php else: // Thất bại hoặc đang chờ ?>

        <div class="confirmation-icon" style="background-color: var(--color-red);">
            <i class="fa-solid fa-xmark" style="color: white; font-size: 40px;"></i>
        </div>
        <h1 style="font: var(--font-h2); margin-top: var(--spacing-24);">Thanh toán thất bại</h1>
        <p style="font-size: 18px; color: var(--color-fg-muted); margin-top: var(--spacing-16);">
            Đã xảy ra lỗi trong quá trình thanh toán.
            <?php if ($status === 'pending'): ?>
                Đơn hàng #<?= htmlspecialchars($real_order_id) ?> của bạn chưa được thanh toán.
            <?php endif; ?>
        </p>
        <a href="index.php?page=cart" class="btn btn-outline" style="margin-top: var(--spacing-32);">Quay lại giỏ hàng</a>

    <?php endif; ?>

</div>

<?php 
require_once 'client/layouts/footer.php'; 
?>