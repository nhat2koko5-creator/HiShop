<?php 
// FILE: client/pages/checkout.php (ĐÃ FIX HIỂN THỊ BIẾN THỂ)

// 1. KIỂM TRA LUỒNG "MUA NGAY"
$is_buy_now = isset($_GET['action']) 
              && $_GET['action'] == 'buy_now' 
              && isset($_GET['variant_id'])
              && isset($_SESSION['user_id']);

if ($is_buy_now) {
    // --- LUỒNG MUA NGAY ---
    $variant_id = (int)$_GET['variant_id'];
    
    // Lấy thông tin sản phẩm cha + biến thể
    $stmt = $pdo->prepare("
        SELECT 
            sp.id AS parent_id,
            sp.ten, 
            sp.hinh_anh, 
            bv.id AS bien_the_id, 
            bv.gia, 
            bv.mau_sac, 
            bv.dung_luong_ssd
        FROM bien_the_san_pham AS bv
        JOIN san_pham AS sp ON bv.san_pham_id = sp.id
        WHERE bv.id = ? AND bv.so_luong_ton > 0
    ");
    $stmt->execute([$variant_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        echo "<script>alert('Sản phẩm không hợp lệ hoặc đã hết hàng.'); window.location.href='index.php?page=home';</script>";
        exit;
    }
    
    // [CẬP NHẬT]: Tách riêng mau_sac và dung_luong_ssd để hiển thị đẹp hơn
    $cart_items = [
        [
            'san_pham_id' => $item['parent_id'], 
            'variant_id'  => $item['bien_the_id'], 
            'ten'         => $item['ten'],
            'mau_sac'     => $item['mau_sac'],          // Lưu riêng
            'dung_luong_ssd' => $item['dung_luong_ssd'], // Lưu riêng
            'hinh_anh'    => $item['hinh_anh'],
            'gia'         => $item['gia'],
            'so_luong'    => 1
        ]
    ];
    $subtotal = $item['gia'];
    $_SESSION['buy_now_item'] = $cart_items[0]; 

} else {
    // --- LUỒNG GIỎ HÀNG BÌNH THƯỜNG ---
    unset($_SESSION['buy_now_item']); 

    // Hàm này đã được sửa ở functions.php để lấy kèm mau_sac, dung_luong_ssd
    $cartData = getCartItemsAndTotal($pdo, $_SESSION['user_id']);
    $cart_items = $cartData['items'];
    $subtotal = $cartData['total'];

    // (Tùy chọn) Nếu bạn muốn lọc chỉ thanh toán các món được chọn từ trang Cart
    // Bạn có thể thêm logic lọc $cart_items dựa trên $_GET['selected_ids'] ở đây.
    // Hiện tại ta cứ hiển thị hết như mặc định.

    if (empty($cart_items)) {
        echo "<script>alert('Giỏ hàng trống.'); window.location.href='index.php?page=cart';</script>";
        exit; 
    }
}

// 3. LẤY THÔNG TIN USER & TÍNH TOÁN MÃ GIẢM GIÁ
$user_profile = getUserProfile($pdo, $_SESSION['user_id']);
$available_coupons = getAvailableCoupons($pdo); 

$discount = 0;
$promo_code = '';
$promo_message = '';
$promo_status_class = '';

if (isset($_SESSION['promo']) && is_array($_SESSION['promo'])) { 
    $coupon = $_SESSION['promo'];
    $promo_code = $coupon['code'];
    $promo_message = "Đã áp dụng mã: " . htmlspecialchars($promo_code);
    $promo_status_class = 'text-success'; 

    if ($coupon['type'] == 'percent') {
         $discount = ($subtotal * $coupon['value']) / 100;
    } else {
         $discount = $coupon['value'];
    }
    if ($discount > $subtotal) $discount = $subtotal;
}

$shipping = 0; // Mặc định miễn phí
$total = $subtotal + $shipping - $discount;
?>

<div class="container">
    <h1 class="page-title">Thanh toán</h1>

    <form method="POST" action="index.php?page=process_vnpay" id="checkout-form">
        <input type="hidden" name="order_type" value="<?= $is_buy_now ? 'buy_now' : 'cart' ?>">
        
        <div class="checkout-layout">
            
            <div class="checkout-form"> 
                <div class="form-section">
                    <div class="form-section-header"><h2>Thông tin người nhận</h2></div>
                    <div class="form-section-body">
                        <div class="form-group">
                            <label>Họ và tên *</label>
                            <input type="text" name="ho_ten" class="form-input" value="<?= htmlspecialchars($user_profile['ho_ten'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Số điện thoại *</label>
                            <input type="tel" name="so_dien_thoai" class="form-input" value="<?= htmlspecialchars($user_profile['so_dien_thoai'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Địa chỉ nhận hàng *</label>
                            <input type="text" name="dia_chi" class="form-input" placeholder="Số nhà, tên đường..." required>
                        </div>
                        <div class="form-group">
                            <label>Ghi chú</label>
                            <textarea name="ghi_chu" class="form-textarea" rows="3"></textarea>
                        </div>
                    </div>
                </div>
            </div> 

            <div class="checkout-summary-stack">
                <div class="order-summary">
                    <h2>Tóm tắt đơn hàng</h2>
                    <div class="summary-item-list">
                        <?php foreach ($cart_items as $item): 
                             $img_path = (!empty($item['hinh_anh'])) ? "assets/img/products/" . $item['hinh_anh'] : "assets/img/no-image.png";
                        ?>
                        <div class="summary-item">
                            <div class="summary-item-image">
                                <img src="<?= htmlspecialchars($img_path) ?>" alt="">
                            </div>
                            <div class="summary-item-details">
                                <p style="margin-bottom: 4px;"><?= htmlspecialchars($item['ten']) ?></p>
                                
                                <?php if (!empty($item['mau_sac']) || !empty($item['dung_luong_ssd'])): ?>
                                    <small style="color: #666; display: block; font-weight: 500; margin-bottom: 4px;">
                                        <?= htmlspecialchars($item['mau_sac'] ?? '') ?> 
                                        <?= (!empty($item['mau_sac']) && !empty($item['dung_luong_ssd'])) ? ' / ' : '' ?> 
                                        <?= htmlspecialchars($item['dung_luong_ssd'] ?? '') ?>
                                    </small>
                                <?php endif; ?>

                                <span style="color: #888; font-size: 13px;">x<?= $item['so_luong'] ?></span>
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
                        <a href="#" id="open-coupon-modal" style="font-size:14px; color:var(--color-primary);">✨ Chọn mã giảm giá</a>
                    </div>
                    <div class="coupon-form">
                        <input type="text" id="coupon-input" placeholder="Nhập mã" value="<?= htmlspecialchars($promo_code); ?>">
                        <button type="button" id="btn-apply-coupon">Áp dụng</button>
                    </div>
                    <div id="coupon-status-msg" style="font-size: 13px; font-weight: 600; margin-bottom: 10px;" class="<?= $promo_status_class ?>">
                        <?= $promo_message ?>
                    </div> 

                    <div class="summary-row">
                        <span>Tạm tính</span>
                        <span><?= number_format($subtotal) ?>₫</span>
                    </div>
                    <div class="summary-row">
                        <span>Vận chuyển</span>
                        <span>Miễn phí</span>
                    </div>
                    
                    <?php if ($discount > 0): ?>
                    <div class="summary-row" id="discount-row">
                        <span>Giảm giá</span>
                        <span style="color: var(--color-red);">-<?= number_format($discount) ?>₫</span>
                    </div>
                    <?php endif; ?>

                    <hr class="summary-divider">
                    <div class="summary-row total">
                        <span>Tổng cộng</span>
                        <span style="color: var(--color-red); font-size: 20px;"><?= number_format($total) ?>₫</span>
                    </div>
                </div> 

                <div class="payment-summary">
                    <h3>Phương thức thanh toán</h3>
                    <div class="payment-methods-list">
                        <div class="payment-method-box active">
                            <input type="radio" id="payment_vnpay" name="payment_method" value="vnpay" checked>
                            <img src="assets/img/vnpay-logo.png" alt="VNPAY" style="height: 24px;"> 
                            <label for="payment_vnpay" style="margin-left: 8px;">Thanh toán qua VNPAY</label>
                        </div>
                    </div>

                    <div class="terms-checkbox">
                        <input type="checkbox" id="terms" name="terms" required>
                        <label for="terms">Tôi đồng ý với <a href="index.php?page=static_policy" target="_blank">điều khoản và chính sách</a>.</label>
                    </div>
                    <div class="checkout-btn">
                        <button type="submit" class="btn btn-primary" style="width: 100%;">Đặt hàng</button>
                    </div>
                </div> 
            </div> 
        </div> 
    </form> 
</div> 

<div class="coupon-modal-overlay" id="coupon-modal-overlay">
    <div class="coupon-modal">
        <div class="coupon-modal-header">
            <h3>✨ Chọn mã ưu đãi</h3>
            <button class="close-modal-btn" id="close-coupon-modal">&times;</button>
        </div>
        <div class="coupon-modal-body">
            <?php if (empty($available_coupons)): ?>
                <p style="text-align: center; padding: 20px; color: #666;">Không có mã giảm giá nào khả dụng.</p>
            <?php else: ?>
                <?php foreach ($available_coupons as $cp): ?>
                    <div class="coupon-item">
                        <div class="coupon-info">
                            <strong style="color: var(--color-primary); font-size: 16px;"><?= htmlspecialchars($cp['ten']) ?></strong>
                            <p style="margin: 4px 0; font-size: 13px; color: #555;">
                                Giảm: <?= ($cp['loai_khuyen_mai'] == 'percent') ? $cp['gia_tri'] . '%' : number_format($cp['gia_tri']) . '₫' ?>
                            </p>
                        </div>
                        <button type="button" class="btn-apply-from-modal" data-code="<?= htmlspecialchars($cp['ten']) ?>">
                            Chọn
                        </button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="alert-modal-overlay" id="alert-modal-overlay">
    <div class="alert-modal-box" id="alert-modal-box">
        <div class="alert-modal-header">
            <h3 id="alert-modal-title" style="margin:0;">Thông báo</h3>
        </div>
        <div class="alert-modal-body" style="padding: 20px 0;">
            <p id="alert-modal-message" style="font-size: 16px; text-align: center;">Nội dung</p>
        </div>
        <div class="alert-modal-footer">
            <button id="btn-alert-close" class="btn btn-primary" style="width: 100%;">OK</button>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Khai báo Element
    const couponOverlay = document.getElementById('coupon-modal-overlay');
    const alertOverlay = document.getElementById('alert-modal-overlay');
    const btnOpenCoupon = document.getElementById('open-coupon-modal');
    const btnCloseCoupon = document.getElementById('close-coupon-modal');
    const alertBox = document.getElementById('alert-modal-box');
    const alertTitle = document.getElementById('alert-modal-title');
    const alertMsg = document.getElementById('alert-modal-message');
    const btnAlertClose = document.getElementById('btn-alert-close');
    const couponInput = document.getElementById('coupon-input');
    const btnApplyManual = document.getElementById('btn-apply-coupon');
    let needReload = false;

    function showPopup(title, message, isSuccess) {
        alertTitle.textContent = title;
        alertMsg.textContent = message;
        if (isSuccess) {
            alertBox.classList.remove('error');
            alertBox.classList.add('success');
        } else {
            alertBox.classList.remove('success');
            alertBox.classList.add('error');
        }
        alertOverlay.style.display = 'flex';
        needReload = true; 
    }

    if (btnAlertClose) {
        btnAlertClose.addEventListener('click', function() {
            alertOverlay.style.display = 'none';
            if (needReload) {
                this.textContent = "Đang tải lại...";
                window.location.reload(); 
            }
        });
    }

    async function applyCoupon(code) {
        couponOverlay.style.display = 'none';
        if (!code) { alert("Vui lòng nhập mã."); return; }
        const formData = new URLSearchParams();
        formData.append('action', 'apply_coupon');
        formData.append('code', code);

        try {
            const response = await fetch('cart-handler.php', { method: 'POST', body: formData });
            const textResponse = await response.text();
            try {
                const data = JSON.parse(textResponse);
                if (data.status === 'success') {
                    showPopup('Thành công!', data.message, true);
                } else {
                    showPopup('Thất bại', data.message, false);
                }
            } catch (e) { showPopup('Lỗi hệ thống', 'Server trả về dữ liệu lỗi.', false); }
        } catch (err) { showPopup('Lỗi kết nối', 'Không thể kết nối đến server.', false); }
    }

    if (btnOpenCoupon) {
        btnOpenCoupon.addEventListener('click', (e) => { e.preventDefault(); couponOverlay.style.display = 'flex'; });
    }
    if (btnCloseCoupon) {
        btnCloseCoupon.addEventListener('click', () => { couponOverlay.style.display = 'none'; });
    }
    if (btnApplyManual) {
        btnApplyManual.addEventListener('click', (e) => { e.preventDefault(); applyCoupon(couponInput.value); });
    }
    document.querySelectorAll('.btn-apply-from-modal').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const code = this.getAttribute('data-code');
            if (couponInput) couponInput.value = code;
            applyCoupon(code);
        });
    });
    window.addEventListener('click', (e) => {
        if (e.target === couponOverlay) couponOverlay.style.display = 'none';
    });
});
</script>

<?php require_once 'client/layouts/footer.php'; ?>