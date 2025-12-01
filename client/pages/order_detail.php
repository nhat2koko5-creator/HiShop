<?php
// FILE: client/pages/order_detail.php

// 1. KIỂM TRA ĐĂNG NHẬP
if (!isset($_SESSION['user_id'])) {
    echo "<script>window.location.href='index.php?page=login';</script>";
    exit;
}

$order_id = $_GET['id'] ?? 0;
$user_id = $_SESSION['user_id'];

// 2. LẤY THÔNG TIN ĐƠN HÀNG (Không cần join bảng thanh_toan nữa vì đã có cột riêng)
$stmt = $pdo->prepare("SELECT * FROM don_hang WHERE id = ? AND nguoi_dung_id = ?");
$stmt->execute([$order_id, $user_id]);
$order = $stmt->fetch();

if (!$order) {
    echo "<div class='container' style='margin-top:40px;'><p>Đơn hàng không tồn tại.</p></div>";
    return;
}
$order_items = getOrderItems($pdo, $order_id);

// 3. CẤU HÌNH HIỂN THỊ (MAPPING)
$order_status = $order['trang_thai'];           // pending, confirmed, shipping, delivered, cancelled
$pay_status   = $order['trang_thai_thanh_toan']; // unpaid, paid, refunded

// A. Trạng thái đơn hàng
$st_labels = [
    'pending'   => ['text' => 'Chờ xác nhận',    'color' => '#f59e0b', 'bg' => '#fffbeb'], 
    'confirmed' => ['text' => 'Đã xác nhận',     'color' => '#3b82f6', 'bg' => '#eff6ff'], 
    'shipping'  => ['text' => 'Đang vận chuyển', 'color' => '#3b82f6', 'bg' => '#eff6ff'], 
    'delivered' => ['text' => 'Giao thành công', 'color' => '#10b981', 'bg' => '#ecfdf5'], 
    'cancelled' => ['text' => 'Đã hủy',          'color' => '#ef4444', 'bg' => '#fef2f2'], 
    'returned'  => ['text' => 'Trả hàng',        'color' => '#ef4444', 'bg' => '#fef2f2']
];
$status_info = $st_labels[$order_status] ?? ['text' => $order_status, 'color' => '#333', 'bg' => '#f3f4f6'];

// B. Trạng thái thanh toán
$pay_labels = [
    'unpaid'   => ['text' => 'Chưa thanh toán', 'color' => '#f59e0b', 'icon' => '⏳'],
    'paid'     => ['text' => 'Đã thanh toán',   'color' => '#10b981', 'icon' => '✅'],
    'refunded' => ['text' => 'Đã hoàn tiền',    'color' => '#6b7280', 'icon' => '↩️']
];
$pay_info = $pay_labels[$pay_status] ?? ['text' => 'Không rõ', 'color' => '#333', 'icon' => '❓'];


// 4. LOGIC THANH TIẾN TRÌNH (4 BƯỚC CHUẨN)
$current_step = 1;
if ($order_status == 'confirmed') $current_step = 2;
if ($order_status == 'shipping')  $current_step = 3;
if ($order_status == 'delivered') $current_step = 4;
if ($order_status == 'cancelled' || $order_status == 'returned') $current_step = 0;
?>
<link rel="stylesheet" href="assets/css/client/account.css">
<div class="container" style="margin-top: 30px; margin-bottom: 50px;">
    
    <div class="mb-4" style="margin-bottom: 20px;">
        <a href="index.php?page=account&section=orders" style="color: #666; text-decoration: none; font-size: 14px;">
            &larr; Quay lại danh sách đơn hàng
        </a>
    </div>

    <div class="order-detail-container">
        
        <div class="od-header">
            <div class="od-title">
                <h1>Chi tiết đơn hàng #<?= htmlspecialchars($order['id']) ?></h1>
                <span class="od-date">Ngày đặt: <?= date('d/m/Y H:i', strtotime($order['ngay_dat'])) ?></span>
            </div>
            <div class="od-status-badge" style="color: <?= $status_info['color'] ?>; background-color: <?= $status_info['bg'] ?>; border: 1px solid <?= $status_info['color'] ?>20;">
                <?= $status_info['text'] ?>
            </div>
        </div>
        <div class="od-grid">
            <div class="od-info-card">
                <h3>Địa chỉ người nhận</h3>
                <div class="od-info-content">
                    <p style="font-size: 16px; font-weight: 600; margin-bottom: 8px;"><?= htmlspecialchars($order['ho_ten_nguoi_nhan']) ?></p>
                    <p>SĐT: <?= htmlspecialchars($order['sdt_nguoi_nhan']) ?></p>
                    <p class="address" style="color:#666;">Địa chỉ: <?= htmlspecialchars($order['dia_chi_giao_hang']) ?></p>
                </div>
            </div>

        <div class="od-info-card">
                <h3>Thông tin thanh toán</h3>
                <div class="od-info-content">
                    <p style="margin-bottom: 12px;">Hình thức: <strong>Thanh toán qua VNPAY</strong></p>
                    
                    <div class="payment-status-box" style="background: #fff; padding: 12px; border-radius: 8px; border: 1px solid <?= $pay_info['color'] ?>40;">
                        <span style="font-size: 24px;"><?= $pay_info['icon'] ?></span> <div>
                            <span style="font-size: 13px; color: #6b7280; display:block; font-weight: 500;">Trạng thái tiền:</span>
                            <strong style="color: <?= $pay_info['color'] ?>; font-size:15px;"><?= $pay_info['text'] ?></strong>
                        </div>
                    </div>

                    <?php if(!empty($order['ghi_chu'])): ?>
                        <div style="margin-top: 15px;">
                            <p style="color:#666; font-size: 13px; margin-bottom: 4px;">Ghi chú:</p>
                            <p style="font-style: italic;">"<?= htmlspecialchars($order['ghi_chu']) ?>"</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="od-products-card">
            <div class="od-products-header">Sản phẩm</div>
            <div class="od-products-list">
                <?php foreach ($order_items as $item): 
                    $img = !empty($item['hinh_anh']) ? "assets/img/products/".$item['hinh_anh'] : "assets/img/no-image.png";
                ?>
                <div class="od-item">
                    <div class="od-item-img">
                        <img src="<?= $img ?>" alt="Product">
                    </div>
                    <div class="od-item-info">
                        <div class="od-item-name"><?= htmlspecialchars($item['ten_san_pham']) ?></div>
                        <div class="od-item-meta">Số lượng: x<?= $item['so_luong'] ?></div>
                    </div>
                    <div class="od-item-price">
                        <?= number_format($item['don_gia']) ?>₫
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="od-footer">
                <div class="od-total-row">
                    <span>Tổng tiền hàng:</span>
                    <span><?= number_format($order['tong_tien']) ?>₫</span>
                </div>
                <div class="od-total-row">
                    <span>Phí vận chuyển:</span>
                    <span>Miễn phí</span>
                </div>
                <div class="od-total-row final">
                    <span>Thành tiền:</span>
                    <span><?= number_format($order['tong_tien']) ?>₫</span>
                </div>
            </div>
        </div>

    </div>
</div>

<style>

</style>