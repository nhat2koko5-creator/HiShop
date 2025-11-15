<?php 
// FILE: client/pages/checkout.php (PHIÊN BẢN SẠCH)
// (index.php đã xử lý session_start, kiểm tra đăng nhập, và tải header.php)

// Lấy thông tin giỏ hàng từ CSDL
// (Chúng ta an toàn vì index.php đã đảm bảo $_SESSION['user_id'] tồn tại)
$cart = getCartItemsAndTotal($pdo, $_SESSION['user_id']);
$cart_items = $cart['items'];
$cart_total = $cart['total'];

// Nếu giỏ hàng rỗng (vẫn dùng JavaScript)
if (empty($cart_items)) {
    echo "<script>
        alert('Giỏ hàng của bạn đang rỗng. Đang chuyển về trang giỏ hàng...');
        window.location.href='index.php?page=cart';
    </script>";
    exit; 
}

// Lấy thông tin người dùng để điền sẵn vào form
$user_profile = getUserProfile($pdo, $_SESSION['user_id']);
?>

<div class="container">
    <h1 class="page-title">Thanh Toán</h1>

    <div class="checkout-layout">
        
        <form class="checkout-form" method="POST" action="index.php?page=process_payment">
            
            <div class="form-section">
                <div class="form-section-header">
                    <h2>Thông tin người nhận</h2>
                </div>
                <div class="form-section-body">
                    <div class="form-group">
                        <label for="ho_ten">Họ và tên *</label>
                        <input type="text" id="ho_ten" name="ho_ten" class="form-input" 
                               value="<?= htmlspecialchars($user_profile['ho_ten'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="so_dien_thoai">Số điện thoại *</label>
                        <input type="tel" id="so_dien_thoai" name="so_dien_thoai" class="form-input" 
                               value="<?= htmlspecialchars($user_profile['so_dien_thoai'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="dia_chi">Địa chỉ nhận hàng *</label>
                        <input type="text" id="dia_chi" name="dia_chi" class="form-input" 
                               placeholder="Số nhà, tên đường, phường/xã, quận/huyện, tỉnh/thành" required>
                    </div>
                    <div class="form-group">
                        <label for="ghi_chu">Ghi chú (Tùy chọn)</label>
                        <textarea id="ghi_chu" name="ghi_chu" class="form-textarea" rows="3"></textarea>
                    </div>
                </div>
            </div>

            <div class="order-summary">
                <h2>Tóm tắt đơn hàng</h2>

                <div class="summary-item-list">
                    <?php foreach ($cart_items as $item): ?>
                    <div class="summary-item">
                        <div class="summary-item-image">
                            <img src="assets/img/products/<?= htmlspecialchars($item['hinh_anh']) ?>" alt="<?= htmlspecialchars($item['ten']) ?>">
                        </div>
                        <div class="summary-item-details">
                            <p><?= htmlspecialchars($item['ten']) ?></p>
                            <span>Số lượng: <?= $item['so_luong'] ?></span>
                        </div>
                        <div class="summary-item-price">
                            <?= number_format($item['gia'] * $item['so_luong']) ?>₫
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <hr class="summary-divider">

                <div class="summary-row">
                    <span>Tạm tính</span>
                    <span><?= number_format($cart_total) ?>₫</span>
                </div>
                <div class="summary-row">
                    <span>Phí vận chuyển</span>
                    <span>Miễn phí</span>
                </div>
                
                <hr class="summary-divider">
                
                <div class="summary-row total">
                    <span>Tổng cộng</span>
                    <span><?= number_format($cart_total) ?>₫</span>
                </div>

                <div class="checkout-btn">
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        Thanh toán qua MoMo
                    </button>
                </div>
            </div>
        
        </form> 
    </div> 
</div>