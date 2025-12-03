<?php
// FILE: admin/pages/orders_list.php

// --- CẤU HÌNH ---
$limit = 10; // Số đơn hàng mỗi trang (Chỉnh thành 10 cho gọn)
$current_page = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$offset = ($current_page - 1) * $limit;

$status_map = [
    'pending'   => 'Chờ xử lý',
    'confirmed' => 'Đã xác nhận',
    'shipping'  => 'Đang giao hàng',
    'delivered' => 'Đã giao hàng',
    'cancelled' => 'Đã hủy',
    'returned'  => 'Trả hàng'
];

// --- QUERY DỮ LIỆU ---
$current_tab = $_GET['status'] ?? 'all';
$search_query = trim($_GET['q'] ?? '');

// 1. Xây dựng câu Query cơ bản
$sql_base = "FROM don_hang d JOIN nguoi_dung u ON d.nguoi_dung_id = u.id WHERE 1=1";
$params = [];

if ($current_tab !== 'all' && isset($status_map[$current_tab])) {
    $sql_base .= " AND d.trang_thai_don_hang = ?";
    $params[] = $status_map[$current_tab]; 
}

if (!empty($search_query)) {
    $sql_base .= " AND (d.id LIKE ? OR u.ho_ten LIKE ?)";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
}

// 2. Đếm tổng số bản ghi (Để làm phân trang)
$stmt_count = $pdo->prepare("SELECT COUNT(*) $sql_base");
$stmt_count->execute($params);
$total_records = $stmt_count->fetchColumn();
$total_pages = ceil($total_records / $limit);

// 3. Lấy dữ liệu phân trang
$sql_final = "SELECT d.*, u.ho_ten $sql_base ORDER BY d.ngay_dat DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql_final);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// --- THỐNG KÊ (Giữ nguyên) ---
$count_pending = $pdo->query("SELECT COUNT(*) FROM don_hang WHERE trang_thai_don_hang = 'Chờ xử lý'")->fetchColumn();
$count_shipping = $pdo->query("SELECT COUNT(*) FROM don_hang WHERE trang_thai_don_hang = 'Đang giao hàng'")->fetchColumn();
$total_revenue_today = $pdo->query("SELECT SUM(tong_tien) FROM don_hang WHERE trang_thai_thanh_toan = 'Đã thanh toán' AND DATE(ngay_dat) = CURDATE()")->fetchColumn();

// --- HELPER FUNCTIONS ---
function getStatusBadge($status) {
    switch ($status) {
        case 'Chờ xử lý':       return ['class' => 'badge-warning'];
        case 'Đã xác nhận':     return ['class' => 'badge-info'];
        case 'Đang giao hàng':  return ['class' => 'badge-primary'];
        case 'Đã giao hàng':    return ['class' => 'badge-success'];
        case 'Đã hủy':          return ['class' => 'badge-danger'];
        default:                return ['class' => 'badge-secondary'];
    }
}

function getPaymentBadge($status) {
    switch ($status) {
        case 'Chưa thanh toán': return ['class' => 'text-warning', 'label' => 'Chưa TT'];
        case 'Đã thanh toán':   return ['class' => 'text-success', 'label' => 'Đã TT'];
        case 'Đã hoàn tiền':    return ['class' => 'text-danger',  'label' => 'Hoàn tiền'];
        default:                return ['class' => 'text-secondary','label' => $status];
    }
}

// Hàm tạo link phân trang giữ nguyên các tham số lọc
function getPageUrl($page) {
    $params = $_GET;
    $params['p'] = $page;
    return 'index.php?' . http_build_query($params);
}
?>

<link rel="stylesheet" href="../assets/css/admin/orders-list.css">

<div class="admin-page-content">
    
    <div class="stats-bar">
        <div class="stat-box">
            <div class="stat-icon icon-orange"><i class="fa-solid fa-clock"></i></div>
            <div class="stat-info">
                <h4><?= $count_pending ?></h4>
                <p>Đơn chờ xử lý</p>
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

    <div class="filter-toolbar">
        <div class="status-tabs">
            <a href="index.php?page=orders_list&status=all" class="tab-btn <?= $current_tab=='all'?'active':'' ?>">Tất cả</a>
            <a href="index.php?page=orders_list&status=pending" class="tab-btn <?= $current_tab=='pending'?'active':'' ?>">Chờ xử lý</a>
            <a href="index.php?page=orders_list&status=confirmed" class="tab-btn <?= $current_tab=='confirmed'?'active':'' ?>">Đã xác nhận</a>
            <a href="index.php?page=orders_list&status=shipping" class="tab-btn <?= $current_tab=='shipping'?'active':'' ?>">Đang giao</a>
            <a href="index.php?page=orders_list&status=delivered" class="tab-btn <?= $current_tab=='delivered'?'active':'' ?>">Hoàn tất</a>
            <a href="index.php?page=orders_list&status=cancelled" class="tab-btn <?= $current_tab=='cancelled'?'active':'' ?>">Đã hủy</a>
        </div>

        <div class="search-wrapper">
            <form method="GET" class="search-form-flex">
                <input type="hidden" name="page" value="orders_list">
                <input type="hidden" name="status" value="<?= $current_tab ?>">
                <input type="text" name="q" class="search-input" placeholder="Mã đơn, tên khách..." value="<?= htmlspecialchars($search_query) ?>">
                <button class="search-btn"><i class="fa-solid fa-magnifying-glass"></i></button>
            </form>
        </div>
    </div>

    <div class="order-table-container">
        <table class="order-table table-compact">
            <thead>
                <tr>
                    <th width="8%">Mã</th>
                    <th width="22%">Khách Hàng</th>
                    <th width="15%">Ngày Đặt</th>
                    <th width="10%">PTTT</th>
                    <th width="12%">Thanh Toán</th>
                    <th width="13%">Tổng Tiền</th>
                    <th width="12%">Trạng Thái</th>
                    <th width="8%" style="text-align: right;">Hành động</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 40px; color: #6b7280;">
                            <p>Không tìm thấy đơn hàng nào.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($orders as $order): 
                        $status_badge = getStatusBadge($order['trang_thai_don_hang']);
                        $payment_badge = getPaymentBadge($order['trang_thai_thanh_toan']);
                        $pttt = $order['phuong_thuc_thanh_toan'] ?? 'COD';
                    ?>
                    <tr>
                        <td style="font-weight: 700; color: #334155;">#<?= $order['id'] ?></td>
                        
                        <td>
                            <div style="font-weight: 600; color: #1e293b; font-size: 13px;"><?= htmlspecialchars($order['ho_ten']) ?></div>
                        </td>
                        
                        <td style="color: #64748b; font-size: 13px;">
                            <?= date('d/m/Y H:i', strtotime($order['ngay_dat'])) ?>
                        </td>

                        <td>
                            <span class="badge-pill"><?= htmlspecialchars($pttt) ?></span>
                        </td>
                        
                        <td>
                            <span class="<?= $payment_badge['class'] ?>" style="font-size: 12px; font-weight: 600;">
                                <?= $payment_badge['label'] ?>
                            </span>
                        </td>
                        
                        <td class="col-price">
                            <?= number_format($order['tong_tien'], 0, ',', '.') ?>đ
                        </td>
                        
                        <td>
                            <span class="badge <?= $status_badge['class'] ?>">
                                <?= $order['trang_thai_don_hang'] ?>
                            </span>
                        </td>
                        
                        <td style="text-align: right;">
                            <a href="index.php?page=order_detail&id=<?= $order['id'] ?>" class="btn-detail-sm">
                               <i class="fa-solid fa-eye"></i> Xem chi tiết
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($total_pages > 1): ?>
    <div class="pagination-container">
        <div class="pagination-info">
            Hiển thị <strong><?= count($orders) ?></strong> trên tổng <strong><?= $total_records ?></strong> đơn hàng
        </div>
        <div class="pagination-links">
            <?php if ($current_page > 1): ?>
                <a href="<?= getPageUrl($current_page - 1) ?>" class="page-link"><i class="fa-solid fa-chevron-left"></i></a>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="<?= getPageUrl($i) ?>" class="page-link <?= $i == $current_page ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>

            <?php if ($current_page < $total_pages): ?>
                <a href="<?= getPageUrl($current_page + 1) ?>" class="page-link"><i class="fa-solid fa-chevron-right"></i></a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

</div>