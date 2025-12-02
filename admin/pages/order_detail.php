<?php
// FILE: admin/pages/order_detail.php

$order_id = $_GET['id'] ?? 0;

// 1. XỬ LÝ CẬP NHẬT TRẠNG THÁI (BACKEND)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // A. Cập nhật Trạng Thái Đơn Hàng
    if (isset($_POST['update_status'])) {
        $new_status = $_POST['order_status'];
        
        $cancel_reason = null;
        if ($new_status == 'Đã hủy') {
            $cancel_reason = trim($_POST['cancel_reason'] ?? 'Admin hủy đơn hàng');
        }

        $sql = "UPDATE don_hang SET trang_thai_don_hang = ?";
        $params = [$new_status];

        if ($new_status == 'Đã hủy') {
            $sql .= ", ly_do_huy = ?";
            $params[] = $cancel_reason;
        }

        $sql .= " WHERE id = ?";
        $params[] = $order_id;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        if ($new_status == 'Đã giao hàng') {
             $pdo->prepare("UPDATE don_hang SET trang_thai_thanh_toan = 'Đã thanh toán' WHERE id = ?")->execute([$order_id]);
        }
        
        if ($new_status == 'Đã hủy') {
            $check = $pdo->prepare("SELECT trang_thai_thanh_toan FROM don_hang WHERE id = ?");
            $check->execute([$order_id]);
            if ($check->fetchColumn() == 'Đã thanh toán') {
                $pdo->prepare("UPDATE don_hang SET trang_thai_thanh_toan = 'Đã hoàn tiền' WHERE id = ?")->execute([$order_id]);
            }
        }
        
        echo "<script>window.location.href='index.php?page=order_detail&id=$order_id';</script>";
    }

    // B. Cập nhật Trạng Thái Thanh Toán
    if (isset($_POST['update_payment'])) {
        $new_payment = $_POST['payment_status'];
        $stmt = $pdo->prepare("UPDATE don_hang SET trang_thai_thanh_toan = ? WHERE id = ?");
        $stmt->execute([$new_payment, $order_id]);
        echo "<script>window.location.href='index.php?page=order_detail&id=$order_id';</script>";
    }
}

// 2. LẤY DỮ LIỆU
$sql = "SELECT d.*, u.ho_ten as user_name, u.email as user_email, u.so_dien_thoai as user_phone, u.avatar
        FROM don_hang d
        LEFT JOIN nguoi_dung u ON d.nguoi_dung_id = u.id
        WHERE d.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$order_id]);
$order = $stmt->fetch();

if (!$order) { echo "<div class='alert alert-danger'>Không tìm thấy đơn hàng</div>"; return; }

$items = getOrderItems($pdo, $order_id);

$status_list = ['Chờ xử lý', 'Đã xác nhận', 'Đang giao hàng', 'Đã giao hàng', 'Đã hủy', 'Trả hàng'];
$payment_list = ['Chưa thanh toán', 'Đã thanh toán', 'Đã hoàn tiền'];

// Helper màu sắc
function getStatusColor($status) {
    switch($status) {
        case 'Chờ xử lý': return 'badge-warning';
        case 'Đã xác nhận': return 'badge-info';
        case 'Đang giao hàng': return 'badge-primary';
        case 'Đã giao hàng': return 'badge-success';
        case 'Đã hủy': return 'badge-danger';
        default: return 'badge-secondary';
    }
}
?>
<link rel="stylesheet" href="../assets/css/admin/orders-detail.css">
<div class="admin-page-content admin-detail-page">
    
    <div class="top-nav-bar no-print">
        <a href="index.php?page=orders_list" class="btn-back">
            <i class="fa-solid fa-arrow-left"></i> Quay lại danh sách đơn hàng
        </a>
        <button onclick="window.print()" class="btn-print">
            <i class="fa-solid fa-print"></i> In hóa đơn
        </button>
    </div>

    <div class="od-layout">
        
        <div class="od-main">
            
            <div class="od-card">
                <div class="od-card-header" style="height: auto; align-items: flex-start;">
                    <div class="order-title-wrapper">
                        <h2>
                            Đơn hàng #<?= $order['id'] ?>
                            <span class="badge <?= getStatusColor($order['trang_thai_don_hang']) ?>" style="font-size: 13px; padding: 5px 12px; margin-left: 10px;">
                                <?= $order['trang_thai_don_hang'] ?>
                            </span>
                        </h2>
                        <div class="order-date-meta">
                            Ngày đặt: <?= date('H:i - d/m/Y', strtotime($order['ngay_dat'])) ?> 
                            <span style="margin: 0 8px;">•</span> 
                            PTTT: <span class="tag <?= ($order['phuong_thuc_thanh_toan'] == 'VNPAY') ? 'tag-vnpay' : 'tag-cod' ?>"><?= $order['phuong_thuc_thanh_toan'] ?? 'COD' ?></span>
                        </div>
                    </div>
                    <span class="badge badge-secondary"><?= count($items) ?> sản phẩm</span>
                </div>

                <div class="od-card-body">
                    <table class="od-table">
                        <thead>
                            <tr>
                                <th width="50%">Sản phẩm</th>
                                <th width="15%" style="text-align: right;">Đơn giá</th>
                                <th width="10%" style="text-align: center;">SL</th>
                                <th width="20%" style="text-align: right;">Thành tiền</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): 
                                $img = !empty($item['hinh_bien_the']) ? "../assets/img/products/".$item['hinh_bien_the'] : (!empty($item['hinh_anh']) ? "../assets/img/products/".$item['hinh_anh'] : "../assets/img/no-image.png");
                            ?>
                            <tr>
                                <td>
                                    <div class="item-flex">
                                        <img src="<?= $img ?>" class="item-img" alt="">
                                        <div class="item-details">
                                            <h4><?= htmlspecialchars($item['ten_san_pham']) ?></h4>
                                            <?php if ($item['mau_sac'] || $item['dung_luong_ssd']): ?>
                                                <span class="item-meta">
                                                    <?= $item['mau_sac'] ?> <?= $item['dung_luong_ssd'] ? '• '.$item['dung_luong_ssd'] : '' ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td style="text-align: right;"><?= number_format($item['don_gia']) ?>đ</td>
                                <td style="text-align: center;">x<?= $item['so_luong'] ?></td>
                                <td style="text-align: right; font-weight: 600;"><?= number_format($item['don_gia'] * $item['so_luong']) ?>đ</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <div class="summary-box">
                        <div class="summary-row">
                            <span>Tạm tính</span>
                            <span><?= number_format($order['tong_tien']) ?>đ</span>
                        </div>
                        <div class="summary-row">
                            <span>Phí vận chuyển</span>
                            <span>0đ (Miễn phí)</span>
                        </div>
                        <div class="summary-row final">
                            <span>Tổng cộng</span>
                            <span class="text-brand"><?= number_format($order['tong_tien']) ?>đ</span>
                        </div>
                    </div>
                    
                    <?php if (!empty($order['ghi_chu'])): ?>
                        <div style="margin-top: 20px; padding: 15px; background: #fffbeb; border: 1px dashed #f59e0b; border-radius: 8px; color: #b45309; font-size: 14px;">
                            <strong><i class="fa-solid fa-note-sticky"></i> Ghi chú của khách:</strong> "<?= htmlspecialchars($order['ghi_chu']) ?>"
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="od-card">
                <div class="od-card-header"><span class="od-card-title"><i class="fa-solid fa-clock-rotate-left"></i> Lịch sử xử lý</span></div>
                <div class="od-card-body">
                    <ul class="timeline">
                        <li class="timeline-item active">
                            <div class="timeline-marker"></div>
                            <div class="timeline-content">
                                <h5 class="t-title">Đặt hàng thành công</h5>
                                <span class="t-time"><?= date('H:i - d/m/Y', strtotime($order['ngay_dat'])) ?></span>
                                <p class="t-desc">Đơn hàng được khởi tạo bởi khách hàng.</p>
                            </div>
                        </li>

                        <?php if(($order['phuong_thuc_thanh_toan'] ?? 'COD') == 'VNPAY' && $order['trang_thai_thanh_toan'] == 'Đã thanh toán'): ?>
                        <li class="timeline-item success">
                            <div class="timeline-marker" style="background:#10b981; border-color:#10b981"></div>
                            <div class="timeline-content">
                                <h5 class="t-title" style="color: #10b981;">Thanh toán thành công</h5>
                                <p class="t-desc">Hệ thống ghi nhận thanh toán qua VNPAY.</p>
                            </div>
                        </li>
                        <?php endif; ?>

                        <?php if($order['trang_thai_don_hang'] == 'Đã xác nhận' || $order['trang_thai_don_hang'] == 'Đang giao hàng' || $order['trang_thai_don_hang'] == 'Đã giao hàng'): ?>
                        <li class="timeline-item active">
                            <div class="timeline-marker"></div>
                            <div class="timeline-content">
                                <h5 class="t-title">Đã xác nhận đơn hàng</h5>
                                <p class="t-desc">Đơn hàng đã được Admin duyệt và chuyển kho.</p>
                            </div>
                        </li>
                        <?php endif; ?>

                        <?php if($order['trang_thai_don_hang'] == 'Đang giao hàng' || $order['trang_thai_don_hang'] == 'Đã giao hàng'): ?>
                        <li class="timeline-item active">
                            <div class="timeline-marker"></div>
                            <div class="timeline-content">
                                <h5 class="t-title">Đang vận chuyển</h5>
                                <p class="t-desc">Đơn hàng đã bàn giao cho đơn vị vận chuyển.</p>
                            </div>
                        </li>
                        <?php endif; ?>

                        <?php if($order['trang_thai_don_hang'] == 'Đã giao hàng'): ?>
                        <li class="timeline-item success">
                            <div class="timeline-marker" style="background: #10b981; border-color: #10b981;"></div>
                            <div class="timeline-content">
                                <h5 class="t-title" style="color: #10b981;">Giao hàng thành công</h5>
                                <p class="t-desc">Khách hàng đã nhận được sản phẩm.</p>
                            </div>
                        </li>
                        
                        <?php if(($order['phuong_thuc_thanh_toan'] ?? 'COD') != 'VNPAY' && $order['trang_thai_thanh_toan'] == 'Đã thanh toán'): ?>
                            <li class="timeline-item success">
                                <div class="timeline-marker" style="background: #10b981; border-color: #10b981;"></div>
                                <div class="timeline-content">
                                    <h5 class="t-title" style="color: #10b981;">Đã thu tiền (COD)</h5>
                                    <p class="t-desc">Shipper đã thu hộ tiền thành công.</p>
                                </div>
                            </li>
                        <?php endif; ?>
                        <?php endif; ?>

                        <?php if($order['trang_thai_don_hang'] == 'Đã hủy'): ?>
                        <li class="timeline-item danger">
                            <div class="timeline-marker" style="background: #ef4444; border-color: #ef4444;"></div>
                            <div class="timeline-content">
                                <h5 class="t-title" style="color: #ef4444;">Đã hủy đơn hàng</h5>
                                <p class="t-desc">Lý do: <strong><?= $order['ly_do_huy'] ?? 'Không có lý do cụ thể' ?></strong></p>
                            </div>
                        </li>
                        <?php if($order['trang_thai_thanh_toan'] == 'Đã hoàn tiền'): ?>
                            <li class="timeline-item danger">
                                <div class="timeline-marker" style="background: #ef4444; border-color: #ef4444;"></div>
                                <div class="timeline-content">
                                    <h5 class="t-title" style="color: #ef4444;">Đã hoàn tiền</h5>
                                    <p class="t-desc">Tiền đã được hoàn trả lại cho khách hàng.</p>
                                </div>
                            </li>
                        <?php endif; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-right no-print">
            
            <div class="od-card">
                <div class="od-card-header"><span class="od-card-title">Khách hàng</span></div>
                <div class="od-card-body">
                    <div class="customer-widget">
                        <div class="customer-avatar">
                            <?= strtoupper(substr($order['ho_ten_nguoi_nhan'], 0, 1)) ?>
                        </div>
                        <div class="customer-name"><?= htmlspecialchars($order['ho_ten_nguoi_nhan']) ?></div>
                        <div style="font-size: 12px; color: #64748b;">ID: #<?= $order['nguoi_dung_id'] ?></div>
                    </div>
                    <ul class="contact-list">
                        <li><i class="fa-solid fa-envelope"></i> <?= htmlspecialchars($order['user_email'] ?? 'Chưa cập nhật') ?></li>
                        <li><i class="fa-solid fa-phone"></i> <?= htmlspecialchars($order['sdt_nguoi_nhan']) ?></li>
                        <li><i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($order['dia_chi_giao_hang']) ?></li>
                    </ul>
                </div>
            </div>

            <div class="od-card" style="border-top: 4px solid #4f46e5;">
                <div class="od-card-header"><span class="od-card-title">Xử lý đơn hàng</span></div>
                <div class="od-card-body">
                    
                    <form method="POST" style="margin-bottom: 20px;" onsubmit="return validateCancel()">
                        <label style="font-size: 12px; font-weight: 600; color: #64748b; margin-bottom: 6px; display: block;">TRẠNG THÁI ĐƠN</label>
                        
                        <select name="order_status" class="status-select" id="statusSelect" onchange="toggleCancelReason()">
                            <?php foreach ($status_list as $st): ?>
                                <option value="<?= $st ?>" <?= $order['trang_thai_don_hang'] == $st ? 'selected' : '' ?>>
                                    <?= $st ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <div id="cancelReasonArea" class="cancel-reason-box">
                            <label>Lý do hủy đơn hàng:</label>
                            <input type="text" name="cancel_reason" class="cancel-reason-input" placeholder="VD: Hết hàng, Khách đổi ý...">
                        </div>

                        <button type="submit" name="update_status" class="btn-action btn-primary" style="margin-top: 10px;">
                            <i class="fa-solid fa-arrows-rotate"></i> Cập nhật
                        </button>
                    </form>

                    <hr style="border: 0; border-top: 1px dashed #e2e8f0; margin: 20px 0;">

                    <form method="POST">
                        <label style="font-size: 12px; font-weight: 600; color: #64748b; margin-bottom: 6px; display: block;">THANH TOÁN</label>
                        <select name="payment_status" class="status-select" id="paymentSelect">
                            <?php foreach ($payment_list as $pm): ?>
                                <option value="<?= $pm ?>" <?= $order['trang_thai_thanh_toan'] == $pm ? 'selected' : '' ?>>
                                    <?= $pm ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" name="update_payment" class="btn-action btn-success" style="margin-top: 10px;">
                            <i class="fa-solid fa-money-bill"></i> Xác nhận tiền
                        </button>
                    </form>

                </div>
            </div>

        </div>
    </div>
</div>

<script>
    // 1. Ẩn hiện ô lý do hủy
    function toggleCancelReason() {
        var select = document.getElementById('statusSelect');
        var reasonBox = document.getElementById('cancelReasonArea');
        if (select.value === 'Đã hủy') {
            reasonBox.style.display = 'block';
            reasonBox.querySelector('input').required = true;
        } else {
            reasonBox.style.display = 'none';
            reasonBox.querySelector('input').required = false;
        }
    }

    // 2. Bảo vệ: Không cho hủy nếu đã thanh toán mà chưa hoàn tiền
    function validateCancel() {
        var statusSelect = document.getElementById('statusSelect');
        var currentPaymentStatus = "<?= $order['trang_thai_thanh_toan'] ?>";

        if (statusSelect.value === 'Đã hủy' && currentPaymentStatus === 'Đã thanh toán') {
            var confirmRefund = confirm("CẢNH BÁO: Đơn hàng này ĐÃ THANH TOÁN.\n\nBạn có chắc chắn đã HOÀN TIỀN cho khách chưa?\nBấm OK để xác nhận hủy đơn (Hệ thống sẽ tự đổi sang 'Đã hoàn tiền').");
            return confirmRefund;
        }
        return true;
    }

    // Chạy khi load trang
    document.addEventListener('DOMContentLoaded', function() {
        toggleCancelReason();
    });
</script>