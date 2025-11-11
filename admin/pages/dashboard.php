<?php
// FILE: admin/pages/dashboard.php
// (Biến $pdo đã có sẵn từ index.php)

// 1. (BACK-END) Gọi hàm lấy dữ liệu
$stats = getAdminDashboardStats($pdo);
$recent_orders = getRecentOrders($pdo, 5); // Lấy 5 đơn hàng mới nhất

?>

<div class="admin-page-content">
    
    <div class="stats-grid">
        <div class="stat-card">
            <h3>Tổng Doanh Thu</h3>
            <p><?php echo number_format($stats['total_revenue'] ?? 0); ?>₫</p>
        </div>
        <div class="stat-card">
            <h3>Tổng Đơn Hàng</h3>
            <p><?php echo $stats['total_orders'] ?? 0; ?></p>
        </div>
        <div class="stat-card">
            <h3>Khách Hàng</h3>
            <p><?php echo $stats['total_users'] ?? 0; ?></p>
        </div>
        <div class="stat-card">
            <h3>Sản Phẩm</h3>
            <p><?php echo $stats['total_products'] ?? 0; ?></p>
        </div>
    </div>

    <div class="content-card" style="margin-top: 24px;">
        <div class="card-header">
            <h2>Đơn Hàng Mới Nhất</h2>
        </div>
        <div class="card-body">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Mã ĐH</th>
                        <th>Khách Hàng</th>
                        <th>Ngày Đặt</th>
                        <th>Trạng Thái</th>
                        <th>Tổng Tiền</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recent_orders)): ?>
                        <tr>
                            <td colspan="5">Chưa có đơn hàng nào.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recent_orders as $order): ?>
                            <tr>
                                <td>#<?php echo $order['id']; ?></td>
                                <td><?php echo htmlspecialchars($order['ho_ten']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($order['ngay_dat'])); ?></td>
                                <td>
                                    <?php 
                                    $status_class = 'processing';
                                    if ($order['trang_thai'] == 'paid' || $order['trang_thai'] == 'Đã giao hàng') $status_class = 'success';
                                    if ($order['trang_thai'] == 'cancelled' || $order['trang_thai'] == 'Đã hủy') $status_class = 'cancelled';
                                    ?>
                                    <span class="status-badge <?php echo $status_class; ?>">
                                        <?php echo htmlspecialchars($order['trang_thai']); ?>
                                    </span>
                                </td>
                                <td><?php echo number_format($order['tong_tien']); ?>₫</td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>