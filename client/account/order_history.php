<?php
// FILE: client/account/order_history.php
// Biến $data (danh sách TOÀN BỘ đơn hàng) được truyền từ account.php

// 1. ĐỊNH NGHĨA CÁC TRẠNG THÁI (Tabs)
// Bạn cần đảm bảo các key (pending, confirmed...) khớp với giá trị trong CSDL của bạn
$order_tabs = [
    'all'       => 'Tất cả',
    'pending'   => 'Chờ xác nhận',
    'confirmed' => 'Đã xác nhận',
    'shipping'  => 'Đang vận chuyển',
    'delivered' => 'Đã giao hàng', // Hoặc 'paid' tùy database
    'cancelled' => 'Đã hủy'
];

// 2. LẤY TRẠNG THÁI HIỆN TẠI
$current_status = $_GET['status'] ?? 'all';

// 3. LỌC ĐƠN HÀNG THEO TRẠNG THÁI
$filtered_orders = [];
if (!empty($data)) {
    foreach ($data as $order) {
        $db_status = $order['trang_thai']; // Ví dụ: 'pending', 'paid', 'cancelled'
        
        // Logic mapping trạng thái (Tùy chỉnh theo CSDL của bạn)
        // Ví dụ: Database lưu là 'paid' thì coi như là 'delivered'
        $mapped_status = $db_status;
        if ($db_status == 'paid') $mapped_status = 'delivered';
        
        if ($current_status == 'all') {
            $filtered_orders[] = $order;
        } elseif ($current_status == $mapped_status) {
            $filtered_orders[] = $order;
        }
    }
}
?>

<div class="cps-card full-width">
    
    <div class="order-tabs-container">
        <div class="order-tabs">
            <?php foreach ($order_tabs as $key => $label): ?>
                <a href="index.php?page=account&section=orders&status=<?php echo $key; ?>" 
                   class="order-tab-item <?php echo ($current_status == $key) ? 'active' : ''; ?>">
                   <?php echo $label; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="cps-card-body">
        
        <?php if (empty($filtered_orders)): ?>
            <div class="empty-state">
                <p>Không có đơn hàng nào trong mục này.</p>
                <a href="index.php?page=product_list" class="btn btn-primary">Mua sắm ngay</a>
            </div>
        <?php else: ?>
            
            <div class="order-list-container">
                <?php foreach ($filtered_orders as $order): ?>
                    <div class="order-card-item">
                        <div class="oci-header">
                            <div class="oci-id">
                                <span class="icon">📦</span>
                                <strong>Đơn hàng #<?php echo htmlspecialchars($order['id']); ?></strong>
                            </div>
                            
                            <?php 
                                // Xử lý hiển thị badge trạng thái
                                $statusClass = 'processing'; 
                                $statusText = htmlspecialchars($order['trang_thai']);
                                
                                if ($order['trang_thai'] == 'paid' || $order['trang_thai'] == 'delivered') {
                                    $statusClass = 'success';
                                    $statusText = 'Đã giao hàng';
                                } else if ($order['trang_thai'] == 'cancelled') {
                                    $statusClass = 'cancelled';
                                    $statusText = 'Đã hủy';
                                } else if ($order['trang_thai'] == 'confirmed') {
                                    $statusClass = 'info'; // Màu xanh dương
                                    $statusText = 'Đã xác nhận';
                                } else {
                                    $statusClass = 'pending';
                                    $statusText = 'Chờ xác nhận';
                                }
                            ?>
                            <span class="status-tag <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                        </div>
                        
                        <div class="oci-body">
                            <div class="oci-row">
                                <span class="label">Ngày đặt hàng:</span>
                                <span class="value"><?php echo date('H:i - d/m/Y', strtotime($order['ngay_dat'])); ?></span>
                            </div>
                            <div class="oci-row">
                                <span class="label">Tổng tiền:</span>
                                <span class="value price"><?php echo number_format($order['tong_tien']); ?>đ</span>
                            </div>
                        </div>
                        
                        <div class="oci-footer">
                            <a href="index.php?page=order_detail&id=<?php echo $order['id']; ?>" class="btn-link">Xem chi tiết</a>
                            <?php if ($order['trang_thai'] == 'paid' || $order['trang_thai'] == 'delivered'): ?>
                                <a href="index.php?page=product_list" class="btn-sm btn-outline">Mua lại</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php endif; ?>
    </div>
</div>