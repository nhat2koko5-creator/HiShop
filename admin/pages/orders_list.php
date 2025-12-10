<?php
// FILE: admin/pages/orders_list.php

require_once '../src/config.php';
require_once '../src/functions.php';

// --- 1. CẤU HÌNH & LOGIC ---
$limit = 10;
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

// --- 2. QUERY DỮ LIỆU ---
$current_tab = $_GET['status'] ?? 'all';
$search_query = trim($_GET['q'] ?? '');

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

// Đếm tổng
$stmt_count = $pdo->prepare("SELECT COUNT(*) $sql_base");
$stmt_count->execute($params);
$total_records = $stmt_count->fetchColumn();
$total_pages = ceil($total_records / $limit);

// Lấy dữ liệu
$sql_final = "SELECT d.*, u.ho_ten $sql_base ORDER BY d.ngay_dat DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql_final);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// --- 3. THỐNG KÊ NHANH ---
$count_pending = $pdo->query("SELECT COUNT(*) FROM don_hang WHERE trang_thai_don_hang = 'Chờ xử lý'")->fetchColumn();
$count_shipping = $pdo->query("SELECT COUNT(*) FROM don_hang WHERE trang_thai_don_hang = 'Đang giao hàng'")->fetchColumn();
// [SỬA] Tính theo ngày hoàn thành (ngay_hoan_thanh)
$total_revenue_today = $pdo->query("SELECT SUM(tong_tien) FROM don_hang WHERE trang_thai_thanh_toan = 'Đã thanh toán' AND DATE(ngay_hoan_thanh) = CURDATE()")->fetchColumn();

// --- HELPER CLASS ---
function getStatusClass($status) {
    switch ($status) {
        case 'Chờ xử lý':       return 'pending';   // Vàng
        case 'Đã xác nhận':     return 'confirmed'; // Xanh dương nhạt
        case 'Đang giao hàng':  return 'shipping';  // Xanh dương đậm
        case 'Đã giao hàng':    return 'success';   // Xanh lá
        case 'Đã hủy':          return 'danger';    // Đỏ
        default:                return 'secondary';
    }
}

function getPaymentClass($status) {
    switch ($status) {
        case 'Chưa thanh toán': return 'text-warning';
        case 'Đã thanh toán':   return 'text-success';
        case 'Đã hoàn tiền':    return 'text-danger';
        default:                return 'text-muted';
    }
}
?>

<link rel="stylesheet" href="../assets/css/admin/orders_list.css">

<div class="order-container">

    <div class="page-header-title">
        <i class="fa-solid fa-cart-shopping"></i> Quản lý Đơn Hàng
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon-box bg-yellow">
                <i class="fa-solid fa-clock"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Đơn chờ xử lý</div>
                <div class="stat-value text-yellow"><?= $count_pending ?></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon-box bg-blue">
                <i class="fa-solid fa-truck-fast"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Đang giao hàng</div>
                <div class="stat-value text-blue"><?= $count_shipping ?></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon-box bg-green">
                <i class="fa-solid fa-money-bill-wave"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Doanh thu hôm nay</div>
                <div class="stat-value text-green">
                    <?= number_format($total_revenue_today ?? 0, 0, ',', '.') ?> <small>đ</small>
                </div>
            </div>
        </div>
    </div>

    <div class="main-card-wrapper">

        <div class="toolbar-wrapper">
            <div class="status-tabs">
                <a href="index.php?page=orders_list&status=all"
                    class="tab-item <?= $current_tab=='all'?'active':'' ?>">Tất cả</a>
                <a href="index.php?page=orders_list&status=pending"
                    class="tab-item <?= $current_tab=='pending'?'active':'' ?>">Chờ xử lý</a>
                <a href="index.php?page=orders_list&status=shipping"
                    class="tab-item <?= $current_tab=='shipping'?'active':'' ?>">Đang giao</a>
                <a href="index.php?page=orders_list&status=delivered"
                    class="tab-item <?= $current_tab=='delivered'?'active':'' ?>">Hoàn tất</a>
                <a href="index.php?page=orders_list&status=cancelled"
                    class="tab-item <?= $current_tab=='cancelled'?'active':'' ?>">Đã hủy</a>
            </div>

            <form method="GET" class="search-form">
                <input type="hidden" name="page" value="orders_list">
                <input type="hidden" name="status" value="<?= $current_tab ?>">
                <div class="search-box">
                    <i class="fa-solid fa-magnifying-glass search-icon"></i>
                    <input type="text" name="q" class="search-input" placeholder="Mã đơn, khách hàng..."
                        value="<?= htmlspecialchars($search_query) ?>">
                </div>
            </form>
        </div>

        <table class="table-list">
            <thead>
                <tr>
                    <th width="10%">Mã Đơn</th>
                    <th width="10%">Khách Hàng</th>
                    <th width="15%">Ngày Đặt</th>
                    <th width="10%">PTTT</th>
                    <th width="15%">Thanh Toán</th>
                    <th width="15%">Tổng Tiền</th>
                    <th width="12%">Trạng Thái</th>
                    <th width="8%" class="text-right">Hành động</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                <tr>
                    <td colspan="8" class="text-center text-muted" style="padding: 40px;">
                        <img src="../assets/img/empty-box.png" alt=""
                            style="width: 50px; opacity: 0.5; margin-bottom: 10px;">
                        <p>Không tìm thấy đơn hàng nào.</p>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($orders as $order): 
                        $status_class = getStatusClass($order['trang_thai_don_hang']);
                        $payment_class = getPaymentClass($order['trang_thai_thanh_toan']);
                        $pttt = $order['phuong_thuc_thanh_toan'] ?? 'COD';
                    ?>
                <tr>
                    <td>
                        <div class="order-code">#<?= $order['id'] ?></div>
                    </td>

                    <td>
                        <div class="customer-name"><?= htmlspecialchars($order['ho_ten']) ?></div>
                    </td>

                    <td class="text-muted">
                        <?= date('d/m/Y H:i', strtotime($order['ngay_dat'])) ?>
                    </td>

                    <td>
                        <span class="payment-method"><?= htmlspecialchars($pttt) ?></span>
                    </td>

                    <td>
                        <span class="<?= $payment_class ?>" style="font-weight: 600; font-size: 13px;">
                            <?= $order['trang_thai_thanh_toan'] ?>
                        </span>
                    </td>

                    <td>
                        <div class="total-price">
                            <?= number_format($order['tong_tien'], 0, ',', '.') ?>đ
                        </div>
                    </td>

                    <td>
                        <span class="status-badge <?= $status_class ?>">
                            <?= $order['trang_thai_don_hang'] ?>
                        </span>
                    </td>

                    <td class="text-right">
                        <a href="index.php?page=order_detail&id=<?= $order['id'] ?>" class="btn-view-detail"
                            title="Xem">
                            <i class="fa-solid fa-eye"></i>Xem
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <?php if ($total_pages > 1): ?>
        <div class="pagination-area">
            <span class="page-info">Hiển thị <strong><?= count($orders) ?></strong> /
                <strong><?= $total_records ?></strong> đơn hàng</span>
            <div class="page-list">
                <?php 
                    $params = $_GET; 
                    unset($params['p']); 
                    $qs = http_build_query($params);
                ?>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="index.php?<?= $qs ?>&p=<?= $i ?>"
                    class="page-number <?= ($i == $current_page) ? 'active' : '' ?>">
                    <?= $i ?>
                </a>
                <?php endfor; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>