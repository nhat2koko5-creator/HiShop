<?php
// FILE: admin/pages/orders_list.php

// 1. XỬ LÝ CẬP NHẬT TRẠNG THÁI
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_status') {
    $order_id = (int)$_POST['order_id'];
    $new_status = $_POST['status'];
    $allowed_status = ['pending', 'confirmed', 'shipping', 'delivered', 'cancelled', 'returned'];
    
    if (in_array($new_status, $allowed_status)) {
        $stmt = $pdo->prepare("UPDATE don_hang SET trang_thai = ? WHERE id = ?");
        $stmt->execute([$new_status, $order_id]);
        if ($new_status == 'delivered') {
            $pdo->prepare("UPDATE don_hang SET trang_thai_thanh_toan = 'paid' WHERE id = ?")->execute([$order_id]);
        }
        $msg_success = "Đã cập nhật trạng thái đơn hàng #$order_id";
    }
}

// 2. QUERY DỮ LIỆU
$status_filter = $_GET['status'] ?? 'all';
$search_query = trim($_GET['q'] ?? '');

$sql = "SELECT d.*, u.ho_ten 
        FROM don_hang d 
        JOIN nguoi_dung u ON d.nguoi_dung_id = u.id 
        WHERE 1=1";
$params = [];

if ($status_filter !== 'all') {
    $sql .= " AND d.trang_thai = ?";
    $params[] = $status_filter;
}
if (!empty($search_query)) {
    $sql .= " AND (d.id LIKE ? OR u.ho_ten LIKE ?)";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
}
$sql .= " ORDER BY d.ngay_dat DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// 3. THỐNG KÊ
$count_pending = $pdo->query("SELECT COUNT(*) FROM don_hang WHERE trang_thai = 'pending'")->fetchColumn();
$count_shipping = $pdo->query("SELECT COUNT(*) FROM don_hang WHERE trang_thai = 'shipping'")->fetchColumn();
$total_revenue_today = $pdo->query("SELECT SUM(tong_tien) FROM don_hang WHERE trang_thai = 'paid' AND DATE(ngay_dat) = CURDATE()")->fetchColumn();

// HELPER FUNCTIONS
function getStatusBadge($status) {
    $map = [
        'pending'   => ['label' => 'Chờ xác nhận', 'class' => 'badge-warning'],
        'confirmed' => ['label' => 'Đã xác nhận',  'class' => 'badge-info'],
        'shipping'  => ['label' => 'Đang giao',    'class' => 'badge-primary'],
        'delivered' => ['label' => 'Hoàn tất',     'class' => 'badge-success'],
        'cancelled' => ['label' => 'Đã hủy',       'class' => 'badge-danger'],
        'returned'  => ['label' => 'Trả hàng',     'class' => 'badge-dark']
    ];
    return $map[$status] ?? ['label' => $status, 'class' => 'badge-secondary'];
}
function getPaymentBadge($status) {
    $map = [
        'unpaid'   => ['label' => 'Chưa TT', 'class' => 'text-warning'],
        'paid'     => ['label' => 'Đã TT',   'class' => 'text-success'],
        'refunded' => ['label' => 'Hoàn tiền', 'class' => 'text-danger']
    ];
    return $map[$status] ?? ['label' => $status, 'class' => 'text-secondary'];
}
?>

<link rel="stylesheet" href="../assets/css/admin/orders-list.css">

<div class="admin-page-content">
    
    <div class="stats-bar">
        <div class="stat-box">
            <div class="stat-icon icon-orange"><i class="fa-solid fa-clock"></i></div>
            <div class="stat-info">
                <h4><?= $count_pending ?></h4>
                <p>Đơn chờ xác nhận</p>
            </div>
        </div>
        <div class="stat-box">
            <div class="stat-icon icon-blue"><i class="fa-solid fa-truck-fast"></i></div>
            <div class="stat-info">
                <h4><?= $count_shipping ?></h4>
                <p>Đơn đang giao</p>
            </div>
        </div>
        <div class="stat-box">
            <div class="stat-icon icon-green"><i class="fa-solid fa-money-bill-wave"></i></div>
            <div class="stat-info">
                <h4><?= number_format($total_revenue_today ?? 0, 0, ',', '.') ?> <small>đ</small></h4>
                <p>Doanh thu hôm nay</p>
            </div>
        </div>
    </div>

    <?php if (isset($msg_success)): ?>
        <div class="alert alert-success" style="margin-bottom: 20px;"><?= $msg_success ?></div>
    <?php endif; ?>

    <div class="filter-toolbar">
        <div class="status-tabs">
            <a href="index.php?page=orders_list&status=all" class="tab-btn <?= $status_filter=='all'?'active':'' ?>">Tất cả</a>
            <a href="index.php?page=orders_list&status=pending" class="tab-btn <?= $status_filter=='pending'?'active':'' ?>">Chờ xác nhận</a>
            <a href="index.php?page=orders_list&status=confirmed" class="tab-btn <?= $status_filter=='confirmed'?'active':'' ?>">Đã xác nhận</a>
            <a href="index.php?page=orders_list&status=shipping" class="tab-btn <?= $status_filter=='shipping'?'active':'' ?>">Đang giao</a>
            <a href="index.php?page=orders_list&status=delivered" class="tab-btn <?= $status_filter=='delivered'?'active':'' ?>">Hoàn tất</a>
            <a href="index.php?page=orders_list&status=cancelled" class="tab-btn <?= $status_filter=='cancelled'?'active':'' ?>">Đã hủy</a>
        </div>

        <div class="search-wrapper">
            <form method="GET" class="search-form-flex">
                <input type="hidden" name="page" value="orders_list">
                <input type="hidden" name="status" value="<?= $status_filter ?>">
                <input type="text" name="q" class="search-input" placeholder="Mã đơn hoặc tên khách..." value="<?= htmlspecialchars($search_query) ?>">
                <button class="search-btn"><i class="fa-solid fa-magnifying-glass"></i></button>
            </form>
        </div>
    </div>

    <div class="order-table-container">
        <table class="order-table">
            <thead>
                <tr>
                    <th width="10%">Mã Đơn</th>
                    <th width="20%">Khách Hàng</th>
                    <th width="15%">Ngày Đặt</th>
                    <th width="15%">Thanh Toán</th>
                    <th width="15%">Tổng Tiền</th>
                    <th width="15%">Trạng Thái</th>
                    <th width="10%" style="text-align: right;">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 60px; color: #6b7280;">
                            <img src="../assets/img/empty-box.png" style="width: 64px; opacity: 0.5; margin-bottom: 10px;" alt="Empty">
                            <p>Không tìm thấy đơn hàng nào.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($orders as $order): 
                        $status_badge = getStatusBadge($order['trang_thai']);
                        $payment_badge = getPaymentBadge($order['trang_thai_thanh_toan']);
                    ?>
                    <tr>
                        <td style="font-weight: 700; color: var(--color-text-primary);">#<?= $order['id'] ?></td>
                        
                        <td>
                            <div style="font-weight: 600; color: var(--color-text-primary);"><?= htmlspecialchars($order['ho_ten']) ?></div>
                            <div style="font-size: 13px; color: #6b7280; margin-top: 2px;"><?= htmlspecialchars($order['sdt_nguoi_nhan']) ?></div>
                        </td>
                        
                        <td style="color: #4b5563;"><?= date('d/m/Y H:i', strtotime($order['ngay_dat'])) ?></td>
                        
                        <td>
                            <span class="<?= $payment_badge['class'] ?>">
                                <?= $payment_badge['label'] ?>
                            </span>
                        </td>
                        
                        <td class="col-price">
                            <?= number_format($order['tong_tien'], 0, ',', '.') ?>đ
                        </td>
                        
                        <td>
                            <span class="badge <?= $status_badge['class'] ?>">
                                <?= $status_badge['label'] ?>
                            </span>
                        </td>
                        
                        <td style="text-align: right;">
                            <div class="action-group">
                                <a href="#" class="btn-icon btn-view" title="Xem chi tiết">
                                    <i class="fa-regular fa-eye"></i>
                                </a>
                                
                                <form method="POST" class="form-update-status">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                    <select name="status" class="status-select" onchange="if(confirm('Xác nhận đổi trạng thái?')) this.form.submit()">
                                        <option value="" disabled selected>Cập nhật...</option>
                                        <option value="confirmed">Xác nhận</option>
                                        <option value="shipping">Giao hàng</option>
                                        <option value="delivered">Hoàn tất</option>
                                        <option value="cancelled">Hủy đơn</option>
                                    </select>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>