<?php
// FILE: client/pages/order_detail.php

if (!isset($_SESSION['user_id'])) {
    echo "<script>window.location.href='index.php?page=login';</script>";
    exit;
}

$order_id = $_GET['id'] ?? 0;
$user_id = $_SESSION['user_id'];

// Lấy thông tin đơn hàng
$stmt = $pdo->prepare("SELECT * FROM don_hang WHERE id = ? AND nguoi_dung_id = ?");
$stmt->execute([$order_id, $user_id]);
$order = $stmt->fetch();

if (!$order) {
    echo "<div class='container' style='margin-top:40px;'><p>Đơn hàng không tồn tại.</p></div>";
    return;
}
$order_items = getOrderItems($pdo, $order_id);

// CẤU HÌNH HIỂN THỊ
$order_status = $order['trang_thai_don_hang']; 
$pay_status   = $order['trang_thai_thanh_toan'];
// [MỚI] Lấy phương thức thanh toán
$payment_method = $order['phuong_thuc_thanh_toan'] ?? 'COD'; 

// Mảng màu sắc trạng thái đơn hàng
$st_labels = [
    'Chờ xử lý'      => ['text' => 'Chờ xử lý',       'color' => '#f59e0b', 'bg' => '#fffbeb'], 
    'Đã xác nhận'    => ['text' => 'Đã xác nhận',     'color' => '#3b82f6', 'bg' => '#eff6ff'], 
    'Đang giao hàng' => ['text' => 'Đang vận chuyển', 'color' => '#3b82f6', 'bg' => '#eff6ff'], 
    'Đã giao hàng'   => ['text' => 'Giao thành công', 'color' => '#10b981', 'bg' => '#ecfdf5'], 
    'Đã hủy'         => ['text' => 'Đã hủy',          'color' => '#ef4444', 'bg' => '#fef2f2'], 
    'Trả hàng'       => ['text' => 'Trả hàng',        'color' => '#ef4444', 'bg' => '#fef2f2']
];
$status_info = $st_labels[$order_status] ?? ['text' => $order_status, 'color' => '#333', 'bg' => '#f3f4f6'];

// Mảng trạng thái thanh toán
$pay_labels = [
    'Chưa thanh toán' => ['text' => 'Chưa thanh toán', 'color' => '#f59e0b', 'icon' => '⏳'],
    'Đã thanh toán'   => ['text' => 'Đã thanh toán',   'color' => '#10b981', 'icon' => '✅'],
    'Đã hoàn tiền'    => ['text' => 'Đã hoàn tiền',    'color' => '#6b7280', 'icon' => '↩️']
];
$pay_info = $pay_labels[$pay_status] ?? ['text' => $pay_status, 'color' => '#333', 'icon' => '❓'];
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
        <?php if ($order['trang_thai_don_hang'] == 'Đã hủy'): ?>
            <div class="cancellation-alert" style="
                background-color: #fef2f2; 
                border: 1px solid #fecaca; 
                border-left: 4px solid #ef4444; 
                border-radius: 6px; 
                padding: 16px; 
                margin-bottom: 24px; 
                display: flex; 
                gap: 12px;
                align-items: flex-start;">
                
                <div style="color: #ef4444; font-size: 20px;">
                    <i class="fa-solid fa-circle-exclamation"></i>
                </div>
                
                <div>
                    <h4 style="margin: 0 0 6px 0; color: #991b1b; font-size: 16px; font-weight: 700;">Đơn hàng đã bị hủy</h4>
                    <p style="margin: 0; color: #7f1d1d; font-size: 14px; line-height: 1.5;">
                        <strong>Lý do:</strong> 
                        <?= !empty($order['ly_do_huy']) ? htmlspecialchars($order['ly_do_huy']) : 'Quyết định từ hệ thống/quản trị viên (Vui lòng liên hệ CSKH để biết thêm chi tiết).' ?>
                    </p>
                    <div style="margin-top: 8px; font-size: 13px; color: #991b1b;">
                        Nếu bạn đã thanh toán online, tiền sẽ được hoàn về tài khoản của bạn trong vòng 3-5 ngày làm việc.
                    </div>
                </div>
            </div>
        <?php endif; ?>
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
                    <p style="margin-bottom: 12px;">Hình thức: 
                        <strong>
                            <?php 
                                if ($payment_method == 'COD') echo "Thanh toán khi nhận hàng (COD)";
                                else if ($payment_method == 'VNPAY') echo "Thanh toán qua VNPAY";
                                else echo htmlspecialchars($payment_method);
                            ?>
                        </strong>
                    </p>
                    
                    <div class="payment-status-box">
                        <span><?= $pay_info['icon'] ?></span> 
                        <div>
                            <span style="font-size: 13px; color: #6b7280; display:block; font-weight: 500;">Trạng thái tiền:</span>
                            <strong style="color: <?= $pay_info['color'] ?>; font-size:15px;"><?= $pay_info['text'] ?></strong>
                        </div>
                    </div>

                    <?php 
                    if ($pay_status == 'Chưa thanh toán' 
                        && $order_status != 'Đã hủy' 
                        && $order_status != 'Trả hàng'
                        && $payment_method == 'VNPAY'): 
                    ?>
                        <div style="margin-top: 15px;">
                            <a href="client/pages/process_vnpay.php?repay_order_id=<?= $order['id'] ?>" class="btn btn-primary" style="width: 100%; text-align: center; display: block; background-color: #007bff; color: white; padding: 10px; border-radius: 6px; text-decoration: none; font-weight: 600;">
                                💳 Thanh toán ngay
                            </a>
                            <p style="font-size: 12px; color: #dc3545; margin-top: 8px; text-align: center;">
                                <i class="fa-solid fa-triangle-exclamation"></i> Đơn hàng chưa được thanh toán.
                            </p>
                        </div>
                    <?php endif; ?>
                    
                    <?php if(!empty($order['ghi_chu'])): ?>
                        <div style="margin-top: 15px; padding-top: 10px; border-top: 1px dashed #eee;">
                            <p style="color:#666; font-size: 13px; margin-bottom: 4px;">Ghi chú:</p>
                            <p style="font-style: italic;">"<?= htmlspecialchars($order['ghi_chu']) ?>"</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div> <div class="od-products-card">
            <div class="od-products-header">Sản phẩm</div>
            <div class="od-products-list">
                <?php foreach ($order_items as $item): 
                    $imgName = !empty($item['hinh_bien_the']) ? $item['hinh_bien_the'] : $item['hinh_anh'];
                    $img = !empty($imgName) ? "assets/img/products/".$imgName : "assets/img/no-image.png";
                ?>
                <div class="od-item">
                    <div class="od-item-img">
                        <img src="<?= $img ?>" alt="Product">
                    </div>
                    <div class="od-item-info">
                        <div class="od-item-name"><?= htmlspecialchars($item['ten_san_pham']) ?></div>
                        
                        <?php if (!empty($item['mau_sac']) || !empty($item['dung_luong_ssd'])): ?>
                            <div class="od-item-meta" style="margin-bottom: 6px; display: block;">
                                Phân loại: <?= htmlspecialchars($item['mau_sac'] ?? '') ?> 
                                <?= (!empty($item['mau_sac']) && !empty($item['dung_luong_ssd'])) ? '-' : '' ?> 
                                <?= htmlspecialchars($item['dung_luong_ssd'] ?? '') ?>
                            </div>
                        <?php endif; ?>

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