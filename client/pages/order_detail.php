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
                    
                    <div style="background: #fff; padding: 12px; border-radius: 8px; border: 1px solid <?= $pay_info['color'] ?>40; display:flex; align-items:center; gap:10px;">
                        <span style="font-size: 20px;"><?= $pay_info['icon'] ?></span>
                        <div>
                            <span style="font-size: 12px; color: #888; display:block;">Trạng thái tiền:</span>
                            <strong style="color: <?= $pay_info['color'] ?>; font-size:14px;"><?= $pay_info['text'] ?></strong>
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
/* (Giữ nguyên CSS cũ của order_detail) */
.od-header { display: flex; justify-content: space-between; align-items: flex-start; background: #fff; padding: 24px; border-radius: 12px; border: 1px solid #eee; margin-bottom: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.02); }
.od-title h1 { font-size: 22px; margin: 0 0 8px 0; font-weight: 700; color: #1f2937; }
.od-date { font-size: 14px; color: #6b7280; }
.od-status-badge { padding: 8px 16px; border-radius: 6px; font-size: 14px; font-weight: 600; text-transform: capitalize; }

.od-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
.od-info-card { background: #fff; border-radius: 12px; padding: 24px; border: 1px solid #eee; }
.od-info-card h3 { font-size: 16px; font-weight: 600; margin: 0 0 16px 0; border-bottom: 1px solid #f3f4f6; padding-bottom: 12px; }
.od-info-content p { margin-bottom: 8px; font-size: 14px; color: #4b5563; }

.od-products-card { background: #fff; border-radius: 12px; border: 1px solid #eee; overflow: hidden; }
.od-products-header { background: #f9fafb; padding: 16px 24px; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb; }
.od-item { display: flex; padding: 20px 24px; border-bottom: 1px solid #f3f4f6; align-items: center; }
.od-item:last-child { border-bottom: none; }
.od-item-img { width: 70px; height: 70px; border: 1px solid #f3f4f6; border-radius: 8px; padding: 4px; margin-right: 20px; flex-shrink: 0; }
.od-item-img img { width: 100%; height: 100%; object-fit: contain; }
.od-item-info { flex: 1; }
.od-item-name { font-weight: 600; font-size: 15px; margin-bottom: 6px; color: #111; }
.od-item-meta { font-size: 13px; color: #6b7280; }
.od-item-price { font-weight: 700; font-size: 15px; color: #1f2937; }

.od-footer { padding: 24px; background: #fcfcfc; border-top: 1px solid #eee; }
.od-total-row { display: flex; justify-content: flex-end; margin-bottom: 10px; font-size: 14px; }
.od-total-row span:first-child { margin-right: 30px; color: #6b7280; }
.od-total-row span:last-child { min-width: 120px; text-align: right; font-weight: 500; color: #111; }
.od-total-row.final { margin-top: 16px; border-top: 1px dashed #e5e7eb; padding-top: 16px; align-items: center; }
.od-total-row.final span:first-child { font-size: 16px; font-weight: 600; color: #111; }
.od-total-row.final span:last-child { color: #ef4444; font-size: 22px; font-weight: 700; }

@media (max-width: 768px) {
    .od-grid { grid-template-columns: 1fr; }
    .od-header { flex-direction: column; gap: 12px; }
    .order-progress-track { display: none; } 
}
</style>