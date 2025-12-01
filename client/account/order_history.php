<?php
// FILE: client/account/order_history.php

// 1. XỬ LÝ TÌM KIẾM
$keyword = $_GET['q'] ?? '';

// 2. LẤY DỮ LIỆU (Gọi hàm vừa nâng cấp)
// Truyền từ khóa vào hàm getUserOrders
$all_orders = getUserOrders($pdo, $user_id, $keyword); 

// 3. TAB TRẠNG THÁI
$status_map = [
    'all'       => 'Tất cả',
    'pending'   => 'Đang xử lý',
    'shipping'  => 'Đang giao',
    'delivered' => 'Hoàn tất',
    'cancelled' => 'Đã hủy',
    'returned'  => 'Trả hàng'
];
$current_status = $_GET['status'] ?? 'all';

// 4. LỌC THEO TRẠNG THÁI (Client-side filtering)
$filtered_orders = [];
if (!empty($all_orders)) {
    foreach ($all_orders as $order) {
        $db_st = $order['trang_thai'];
        if ($current_status == 'all') {
            $filtered_orders[] = $order;
        } elseif ($current_status == 'delivered' && ($db_st == 'paid' || $db_st == 'delivered')) {
            $filtered_orders[] = $order;
        } elseif ($current_status == $db_st) {
            $filtered_orders[] = $order;
        }
    }
}
?>
<link rel="stylesheet" href="assets/css/account.css">
<div class="cps-card full-width" style="min-height: 500px;">
    
<div class="order-page-header">
        <h3 class="section-title-simple">Đơn hàng của tôi</h3>
        <form action="index.php" method="GET" class="order-search-box">
            <input type="hidden" name="page" value="account">
            <input type="hidden" name="section" value="orders">
            <input type="text" name="q" value="<?= htmlspecialchars($keyword) ?>" placeholder="Tìm đơn hàng...">
            <button type="submit"><i class="icon-search">🔍</i></button>
        </form>
    </div>

    <div class="fpt-tabs-wrapper">
        <div class="fpt-tabs">
            <?php foreach ($status_map as $key => $label): ?>
                <a href="index.php?page=account&section=orders&status=<?= $key ?>" 
                   class="fpt-tab-item <?= ($current_status == $key) ? 'active' : '' ?>">
                   <?= $label ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="cps-card-body" style="background-color: #f8f9fa; padding: 15px;">
        
        <?php if (empty($filtered_orders)): ?>
            <div class="empty-order-state">
                <img src="assets/img/empty-box.png" onerror="this.src='https://cdn-icons-png.flaticon.com/512/4076/4076432.png'" alt="Empty">
                <p>Bạn chưa có đơn hàng nào</p>
                <span class="sub-text">Cùng khám phá hàng ngàn sản phẩm tại HIShop nhé!</span>
                <a href="index.php?page=product_list" class="btn btn-primary-red">Khám phá ngay</a>
            </div>
        <?php else: ?>
            
            <div class="order-list-group">
                <?php foreach ($filtered_orders as $order): 
                    // Lấy chi tiết sản phẩm cho đơn hàng này
                    $items = getOrderItems($pdo, $order['id']);
                ?>
                    <div class="fpt-order-card">
                        <div class="foc-header">
                            <div class="foc-id">
                                <strong>#<?= htmlspecialchars($order['id']) ?></strong>
                                <span class="foc-date"><?= date('d/m/Y H:i', strtotime($order['ngay_dat'])) ?></span>
                            </div>
                            <div class="foc-status <?= $order['trang_thai'] ?>">
                                <?php 
                                    $st_labels = [
                                        'pending' => 'Đang xử lý',
                                        'paid' => 'Đã thanh toán',
                                        'delivered' => 'Giao hàng thành công',
                                        'cancelled' => 'Đã hủy'
                                    ];
                                    echo $st_labels[$order['trang_thai']] ?? $order['trang_thai'];
                                ?>
                            </div>
                        </div>

                        <div class="foc-body">
                            <?php foreach ($items as $item): 
                                $img = !empty($item['hinh_anh']) ? "assets/img/products/".$item['hinh_anh'] : "assets/img/no-image.png";
                            ?>
                            <div class="foc-product-item">
                                <div class="foc-img">
                                    <img src="<?= $img ?>" alt="Product">
                                </div>
                                <div class="foc-info">
                                    <div class="foc-name"><?= htmlspecialchars($item['ten_san_pham']) ?></div>
                                    <div class="foc-variant">Số lượng: x<?= $item['so_luong'] ?></div>
                                </div>
                                <div class="foc-price">
                                    <?= number_format($item['don_gia']) ?>₫
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="foc-footer">
                            <div class="foc-total">
                                <span>Thành tiền:</span>
                                <strong class="total-price"><?= number_format($order['tong_tien']) ?>₫</strong>
                            </div>
                            <div class="foc-actions">
                                <?php if ($order['trang_thai'] == 'paid' || $order['trang_thai'] == 'delivered'): ?>
                                    <a href="index.php?page=product_list" class="btn btn-outline-red">Mua lại</a>
                                <?php endif; ?>
                                <a href="index.php?page=order_detail&id=<?= $order['id'] ?>" class="btn btn-solid-red">Xem chi tiết</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php endif; ?>
    </div>
</div>