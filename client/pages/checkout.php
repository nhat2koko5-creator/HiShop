<?php 
// FILE: client/pages/checkout.php (ĐÃ NÂNG CẤP "MUA NGAY")

// 1. (MỚI) KIỂM TRA LUỒNG "MUA NGAY"
$is_buy_now = isset($_GET['action']) 
              && $_GET['action'] == 'buy_now' 
              && isset($_GET['variant_id'])
              && isset($_SESSION['user_id']); // Phải đăng nhập

if ($is_buy_now) {
    // --- LUỒNG MUA NGAY ---
    $variant_id = (int)$_GET['variant_id'];
    
    // Truy vấn thông tin của 1 biến thể
    $stmt = $pdo->prepare("
        SELECT 
            sp.ten, sp.hinh_anh, 
            bv.id AS bien_the_id, bv.gia, bv.mau_sac, bv.dung_luong_ssd
        FROM bien_the_san_pham AS bv
        JOIN san_pham AS sp ON bv.san_pham_id = sp.id
        WHERE bv.id = ? AND bv.so_luong_ton > 0
    ");
    $stmt->execute([$variant_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        // Biến thể không hợp lệ hoặc hết hàng
        echo "<script>
            alert('Sản phẩm không hợp lệ hoặc đã hết hàng.');
            window.location.href='index.php?page=home';
        </script>";
        exit;
    }
    
    // (MỚI) Tạo dữ liệu giỏ hàng "giả" chỉ chứa 1 sản phẩm
    $cart_items = [
        [
            'san_pham_id' => $item['bien_the_id'], // Dùng ID biến thể
            'ten' => $item['ten'] . " ({$item['mau_sac']} - {$item['dung_luong_ssd']})",
            'hinh_anh' => $item['hinh_anh'],
            'gia' => $item['gia'],
            'so_luong' => 1
        ]
    ];
    $subtotal = $item['gia'];
    
    // (MỚI) Lưu thông tin "Mua ngay" vào Session để trang VNPAY biết
    $_SESSION['buy_now_item'] = $cart_items[0]; 

} else {
    // --- LUỒNG GIỎ HÀNG BÌNH THƯỜNG ---
    
    // Xóa session "Mua ngay" cũ (nếu có)
    unset($_SESSION['buy_now_item']); 

    // 1. LẤY GIỎ HÀNG (Như cũ)
    $cart = getCartItemsAndTotal($pdo, $_SESSION['user_id']);
    $cart_items = $cart['items'];
    $subtotal = $cart['total'];

    // 2. KIỂM TRA GIỎ HÀNG RỖNG (Như cũ)
    if (empty($cart_items)) {
        echo "<script>
            alert('Giỏ hàng của bạn đang rỗng. Đang chuyển về trang giỏ hàng...');
            window.location.href='index.php?page=cart';
        </script>";
        exit; 
    }
}

// 3. LẤY THÔNG TIN (Phần này chạy chung cho cả 2 luồng)
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

<div class="alert-modal-overlay" id="alert-modal-overlay" style="display: none;">
    <div class="alert-modal-box" id="alert-modal-box">
        <div class="alert-modal-header">
            <h3 class="alert-modal-title" id="alert-modal-title">Tiêu đề</h3>
        </div>
        <div class="alert-modal-body">
            <p id="alert-modal-message">Nội dung thông báo.</p>
        </div>
        <div class="alert-modal-footer">
            <button class="btn-alert-close" id="btn-alert-close">Đóng</button>
        </div>
    </div>
</div>

<div class="coupon-modal-overlay" id="coupon-modal-overlay">
    <div class="coupon-modal">
        <div class="coupon-modal-header">
            <h3>✨ Chọn mã giảm giá</h3>
            <button class="close-modal-btn" id="close-coupon-modal">&times;</button>
        </div>
        <div class="coupon-modal-body">
            <?php if (empty($available_coupons)): ?>
                <p style="text-align: center; color: var(--color-fg-muted);">Không có mã giảm giá nào.</p>
            <?php else: ?>
                <?php foreach ($available_coupons as $coupon): ?>
                    <div class="coupon-item">
                        <div class="coupon-info">
                            <strong><?= htmlspecialchars($coupon['ten']) ?></strong>
                            <p>Giảm <?= ($coupon['loai_khuyen_mai'] == 'percent') ? $coupon['gia_tri'] . '%' : number_format($coupon['gia_tri']) . '₫' ?></p>
                        </div>
                        <button class="btn-apply-from-modal" data-code="<?= htmlspecialchars($coupon['ten']) ?>">
                            Chọn
                        </button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // === CÁC BIẾN DOM ===
    const couponModalOverlay = document.getElementById('coupon-modal-overlay');
    const openModalBtn = document.getElementById('open-coupon-modal');
    const closeModalBtn = document.getElementById('close-coupon-modal');
    
    const alertModalOverlay = document.getElementById('alert-modal-overlay');
    const alertModalBox = document.getElementById('alert-modal-box');
    const alertModalTitle = document.getElementById('alert-modal-title');
    const alertModalMessage = document.getElementById('alert-modal-message');
    const btnCloseAlertModal = document.getElementById('btn-alert-close');

    const couponInput = document.getElementById('coupon-input');
    const applyCouponBtn = document.getElementById('btn-apply-coupon');
    const couponMsg = document.getElementById('coupon-message');
    
    // Cờ để tải lại trang sau khi đóng modal
    let reloadOnClose = false; 

    // === HÀM 1: QUẢN LÝ MODAL CHỌN MÃ ===
    if (openModalBtn) {
        openModalBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if (couponModalOverlay) couponModalOverlay.style.display = 'flex';
        });
    }
    if (closeModalBtn) {
        closeModalBtn.addEventListener('click', () => {
            if (couponModalOverlay) couponModalOverlay.style.display = 'none';
        });
    }
    if (couponModalOverlay) {
        couponModalOverlay.addEventListener('click', function(e) {
            if (e.target === couponModalOverlay) {
                couponModalOverlay.style.display = 'none';
            }
        });
    }

    // === HÀM 2: QUẢN LÝ MODAL THÔNG BÁO ===
    function showNotificationModal(title, message, isSuccess = true) {
        alertModalTitle.textContent = title;
        alertModalMessage.textContent = message;
        
        if (isSuccess) {
            alertModalBox.className = 'alert-modal-box success';
        } else {
            alertModalBox.className = 'alert-modal-box error';
        }
        
        if (alertModalOverlay) alertModalOverlay.style.display = 'flex';
    }

    function hideNotificationModal() {
        if (alertModalOverlay) alertModalOverlay.style.display = 'none';
        // (SỬA LỖI) Chỉ tải lại trang nếu cờ reloadOnClose là true
        if (reloadOnClose) {
            window.location.reload();
        }
    }
    
    if (btnCloseAlertModal) {
        btnCloseAlertModal.addEventListener('click', hideNotificationModal);
    }

    // === HÀM 3: GỌI API ÁP DỤNG MÃ (ĐÃ CẬP NHẬT) ===
    async function applyCoupon(code) {
        if (!code) {
            couponMsg.textContent = 'Vui lòng nhập mã.';
            couponMsg.className = 'error';
            return;
        }

        // (SỬA LỖI) Đặt cờ TẢI LẠI TRANG thành true
        // Bất kể thành công hay thất bại, chúng ta đều cần reload
        // để đồng bộ hóa Session PHP.
        reloadOnClose = true; 

        const formData = new URLSearchParams();
        formData.append('action', 'apply_coupon');
        formData.append('code', code);

        try {
            const response = await fetch('cart-handler.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();

            if (data.status === 'success') {
                showNotificationModal('Thành công!', 'Áp dụng mã thành công!', true); 
            } else {
                showNotificationModal('Lỗi', data.message || 'Lỗi không xác định.', false);
            }
        } catch (err) {
            // NGOẠI LỆ: Nếu lỗi kết nối, không cần tải lại trang
            reloadOnClose = false; 
            showNotificationModal('Lỗi', 'Lỗi kết nối. Vui lòng thử lại.', false); 
        }
    }

    // === HÀM 4: GÁN SỰ KIỆN CHO CÁC NÚT ===
    if (applyCouponBtn) {
        applyCouponBtn.addEventListener('click', function() {
            applyCoupon(couponInput.value);
        });
    }

    document.querySelectorAll('.btn-apply-from-modal').forEach(button => {
        button.addEventListener('click', function() {
            const code = this.dataset.code;
            couponInput.value = code; 
            if (couponModalOverlay) couponModalOverlay.style.display = 'none';
            applyCoupon(code);
        });
    });
});
</script>
<?php 
require_once 'client/layouts/footer.php'; 
?>