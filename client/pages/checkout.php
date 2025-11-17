<?php 
// FILE: client/pages/checkout.php (ĐÃ CẬP NHẬT CHO VNPAY)

// 1. LẤY GIỎ HÀNG
$cart = getCartItemsAndTotal($pdo, $_SESSION['user_id']);
$cart_items = $cart['items'];
$subtotal = $cart['total'];

// 2. KIỂM TRA GIỎ HÀNG RỖNG
if (empty($cart_items)) {
    echo "<script>
        alert('Giỏ hàng của bạn đang rỗng. Đang chuyển về trang giỏ hàng...');
        window.location.href='index.php?page=cart';
    </script>";
    exit; 
}

// 3. LẤY THÔNG TIN
$user_profile = getUserProfile($pdo, $_SESSION['user_id']);
$available_coupons = getAvailableCoupons($pdo); 
$discount = 0;
$promo_code = '';
$promo_message = '';
if (isset($_SESSION['promo']) && is_array($_SESSION['promo'])) { 
    $coupon = $_SESSION['promo'];
    $promo_code = $coupon['code'];
    $promo_message = "Đã áp dụng mã: " . htmlspecialchars($promo_code);
    if ($coupon['type'] == 'percent') {
         $discount = ($subtotal * $coupon['value']) / 100;
    } else {
         $discount = $coupon['value'];
    }
    if ($discount > $subtotal) $discount = $subtotal;
}
$shipping = 0;
$total = $subtotal + $shipping - $discount;
?>

<div class="container">
    <h1 class="page-title">Thanh toán</h1>

    <form method="POST" action="index.php?page=process_vnpay" id="checkout-form">
    
        <div class="checkout-layout">
            
            <div class="checkout-form"> 
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
            </div> 

            <div class="checkout-summary-stack">
            
                <div class="order-summary">
                    <h2>Tóm tắt giỏ hàng</h2>
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

                    <div class="coupon-header">
                        <span>Mã giảm giá</span>
                        <a href="#" id="open-coupon-modal">✨ Chọn mã giảm giá</a>
                    </div>
                    <div class="coupon-form">
                        <input type="text" id="coupon-input" placeholder="Nhập mã của bạn" value="<?= htmlspecialchars($promo_code); ?>">
                        <button type="button" id="btn-apply-coupon">Áp dụng</button>
                    </div>
                    <div id="coupon-message" class="<?= $discount > 0 ? 'success' : '' ?>"><?= $promo_message ?></div> 

                    <div class="summary-row">
                        <span>Tạm tính</span>
                        <span id="summary-subtotal"><?= number_format($subtotal) ?>₫</span>
                    </div>
                    <div class="summary-row">
                        <span>Phí vận chuyển</span>
                        <span>Miễn phí</span>
                    </div>
                    <div class="summary-row" id="discount-row" style="<?= $discount == 0 ? 'display: none;' : '' ?>">
                        <span>Giảm giá</span>
                        <span id="summary-discount">-<?= number_format($discount) ?>₫</span>
                    </div>
                    <hr class="summary-divider">
                    <div class="summary-row total">
                        <span>Tổng cộng</span>
                        <span id="summary-total"><?= number_format($total) ?>₫</span>
                    </div>
                </div> 

                <div class="payment-summary">
                    <h3>Phương thức thanh toán</h3>
                    <div class="payment-methods-list">
                        <div class="payment-method-box active">
                            <input type="radio" id="payment_vnpay" name="payment_method" value="vnpay" checked>
                            <img src="assets/img/vnpay-logo.png" alt="VNPAY"> 
                            <label for="payment_vnpay">Thanh toán qua VNPAY</label>
                        </div>
                    </div>

                    <div class="terms-checkbox">
                        <input type="checkbox" id="terms" name="terms" required>
                        <label for="terms">Tôi đồng ý với <a href="index.php?page=static_policy" target="_blank">điều khoản và chính sách</a>.</label>
                    </div>

                    <div class="checkout-btn">
                        <button type="submit" class="btn btn-primary" style="width: 100%;">
                            Đặt hàng
                        </button>
                    </div>
                </div> 
            </div> 
        </div> 
    </form> 
</div> 

<div class="coupon-modal-overlay" id="coupon-modal-overlay">...</div>
<script>...</script>
<?php 
require_once 'client/layouts/footer.php'; 
?>