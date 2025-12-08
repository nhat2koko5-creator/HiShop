<?php 
// FILE: client/pages/checkout.php

// 1. KIỂM TRA LUỒNG "MUA NGAY"
$is_buy_now = isset($_GET['action']) 
             && $_GET['action'] == 'buy_now' 
             && isset($_GET['variant_id'])
             && isset($_SESSION['user_id']);

if ($is_buy_now) {
    // --- LUỒNG MUA NGAY ---
    $variant_id = (int)$_GET['variant_id'];
    // [MỚI] Hỗ trợ lấy số lượng từ URL (mặc định là 1)
    $qty_buy_now = isset($_GET['quantity']) ? max(1, intval($_GET['quantity'])) : 1;
    
    $stmt = $pdo->prepare("
        SELECT 
            sp.id AS parent_id, sp.ten, sp.hinh_anh, 
            bv.id AS bien_the_id, bv.gia, bv.mau_sac, bv.dung_luong_ssd, bv.so_luong_ton
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
    
    // Kiểm tra số lượng tồn kho
    if ($qty_buy_now > $item['so_luong_ton']) $qty_buy_now = $item['so_luong_ton'];

    $cart_items = [
        [
            'san_pham_id' => $item['parent_id'], 
            'variant_id'  => $item['bien_the_id'], 
            'ten'         => $item['ten'],
            'mau_sac'     => $item['mau_sac'],     
            'dung_luong_ssd' => $item['dung_luong_ssd'], 
            'hinh_anh'    => $item['hinh_anh'],
            'gia'         => $item['gia'],
            'so_luong'    => $qty_buy_now
        ]
    ];
    $subtotal = $item['gia'] * $qty_buy_now;
    $_SESSION['buy_now_item'] = $cart_items[0]; 

} else {
    // --- LUỒNG GIỎ HÀNG BÌNH THƯỜNG ---
    unset($_SESSION['buy_now_item']); 
    
    // [MỚI] 1. Lấy danh sách ID sản phẩm được chọn từ URL
    $selected_ids_input = $_GET['selected_ids'] ?? '';
    $selected_ids_arr = [];

    if (!empty($selected_ids_input)) {
        $parts = explode(',', $selected_ids_input);
        // Chỉ lấy số để bảo mật
        $selected_ids_arr = array_filter($parts, 'is_numeric');
    }

    // [MỚI] 2. Nếu không chọn gì thì đá về giỏ hàng
    if (empty($selected_ids_arr)) {
        echo "<script>alert('Vui lòng chọn ít nhất 1 sản phẩm để thanh toán.'); window.location.href='index.php?page=cart';</script>";
        exit;
    }

    // [MỚI] 3. Gọi hàm lấy giỏ hàng VỚI BỘ LỌC ID (truyền mảng ID vào tham số thứ 3)
    $cartData = getCartItemsAndTotal($pdo, $_SESSION['user_id'], $selected_ids_arr); 
    
    $cart_items = $cartData['items'];
    $subtotal = $cartData['total'];

    if (empty($cart_items)) {
        echo "<script>alert('Giỏ hàng không hợp lệ.'); window.location.href='index.php?page=cart';</script>";
        exit; 
    }
}

// 3. LẤY DATA CẦN THIẾT
$user_profile = getUserProfile($pdo, $_SESSION['user_id']);
$available_coupons = getAvailableCoupons($pdo); 

// [MỚI] Lấy danh sách địa chỉ đã lưu của khách
$saved_addresses = getUserAddresses($pdo, $_SESSION['user_id']);

$discount = 0;
$promo_code = '';
$promo_message = '';
$promo_status_class = '';

if (isset($_SESSION['promo']) && is_array($_SESSION['promo'])) { 
    $coupon = $_SESSION['promo'];
    $promo_code = $coupon['code'];
    $promo_message = "Đã áp dụng mã: " . htmlspecialchars($promo_code);
    $promo_status_class = 'text-success'; 

    if ($coupon['type'] == 'phan_tram') {
           $discount = ($subtotal * $coupon['value']) / 100;
    } elseif ($coupon['type'] == 'tien_mat') { 
           $discount = $coupon['value'];
    }
    if ($discount > $subtotal) $discount = $subtotal;
}

$shipping = 0; // Miễn phí vận chuyển
$shipping_display = 'Miễn phí';
$total = $subtotal + $shipping - $discount;
?>

<link rel="stylesheet" href="assets/css/client/checkout.css">

<div class="container">
    <h1 class="page-title">Đặt Hàng</h1>

    <form method="POST" action="index.php?page=process_vnpay" id="checkout-form">
        <input type="hidden" name="shipping_cost" value="0">
        <input type="hidden" name="order_type" value="<?= $is_buy_now ? 'buy_now' : 'cart' ?>">
        <?php if (!$is_buy_now && isset($selected_ids_input)): ?>
        <input type="hidden" name="selected_ids" value="<?= htmlspecialchars($selected_ids_input) ?>">
    <?php endif; ?>
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
                            <input type="tel" maxlength="10" name="so_dien_thoai" class="form-input" value="<?= htmlspecialchars($user_profile['so_dien_thoai'] ?? '') ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>
                                Địa chỉ nhận hàng *
                                <?php if(!empty($saved_addresses)): ?>
                                    <span class="address-select-link" id="open-address-modal">📍 Chọn từ sổ địa chỉ</span>
                                <?php endif; ?>
                            </label>
                            <input type="text" name="dia_chi" id="input-shipping-address" class="form-input" placeholder="Số nhà, tên đường, phường/xã, quận/huyện..." required>
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
                                <p style="margin-bottom: 4px; font-weight: 500;"><?= htmlspecialchars($item['ten'] ?? $item['ten_san_pham']) ?></p>
                                <?php if (!empty($item['mau_sac']) || !empty($item['dung_luong_ssd'])): ?>
                                    <small style="color: #666; display: block; margin-bottom: 4px;">
                                        <?= htmlspecialchars($item['mau_sac'] ?? '') ?> - <?= htmlspecialchars($item['dung_luong_ssd'] ?? '') ?>
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
                        <input type="text" id="coupon-input" placeholder="Nhập mã" value="<?= htmlspecialchars($promo_code); ?>" <?= !empty($promo_code) ? 'readonly' : '' ?>>
                        <?php if (!empty($promo_code)): ?>
                            <button type="button" id="btn-remove-coupon" class="btn-remove">Gỡ bỏ</button>
                        <?php else: ?>
                            <button type="button" id="btn-apply-coupon">Áp dụng</button>
                        <?php endif; ?>
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
                        <span><?= $shipping_display ?></span> 
                    </div>
                    <?php if ($discount > 0): ?>
                    <div class="summary-row">
                        <span>Giảm giá</span>
                        <span style="color: #d70018;">-<?= number_format($discount) ?>₫</span>
                    </div>
                    <?php endif; ?>

                    <hr class="summary-divider">
                    <div class="summary-row total">
                        <span>Tổng cộng</span>
                        <span style="color: #d70018; font-size: 20px;"><?= number_format($total) ?>₫</span>
                    </div>
                </div> 

                <div class="payment-summary">
                    <h3>Phương thức thanh toán</h3>
                    <div class="payment-methods-list">
                        <div class="payment-method-box active">
                            <input type="radio" id="payment_cod" name="payment_method" value="cod" checked>
                            <div style="display: flex; align-items: center; gap: 10px; margin-left: 8px;">
                                <i class="fa-solid fa-money-bill-wave" style="color: #10b981; font-size: 20px;"></i>
                                <label for="payment_cod" style="cursor: pointer;">Thanh toán khi nhận hàng (COD)</label>
                            </div>
                        </div>
                        <div class="payment-method-box">
                            <input type="radio" id="payment_vnpay" name="payment_method" value="vnpay">
                            <img src="assets/img/vnpay.jpg" alt="VNPAY" style="height: 24px; margin-left: 8px;"> 
                            <label for="payment_vnpay" style="margin-left: 8px; cursor: pointer;">Thanh toán qua VNPAY</label>
                        </div>
                    </div>

                    <div class="terms-checkbox">
                        <input type="checkbox" id="terms" name="terms" required checked> <label for="terms">Tôi đồng ý với điều khoản và chính sách mua hàng.</label>
                    </div>
                    <div class="checkout-btn">
                        <button type="submit" class="btn btn-primary" style="width: 100%;">Đặt hàng</button>
                    </div>
                </div> 
            </div> 
        </div>     
    </form> 
</div> 

<div class="coupon-modal-overlay" id="address-modal-overlay">
    <div class="coupon-modal">
        <div class="coupon-modal-header">
            <h3>📍 Sổ địa chỉ của bạn</h3>
            <button class="close-modal-btn" id="close-address-modal">&times;</button>
        </div>
        <div class="coupon-modal-body">
            <?php if (empty($saved_addresses)): ?>
                <div style="text-align:center; padding:30px;">
                    <p style="color:#666; margin-bottom:15px;">Bạn chưa lưu địa chỉ nào.</p>
                    <a href="index.php?page=account&section=addresses" class="btn btn-primary" style="padding:8px 15px; text-decoration:none;">+ Thêm địa chỉ mới</a>
                </div>
            <?php else: ?>
                <div class="address-list-modal">
                    <?php foreach ($saved_addresses as $addr): ?>
                    <div class="address-item-modal" onclick="selectAddress('<?= htmlspecialchars($addr['dia_chi_cu_the']) ?>')">
                        <div class="aim-icon">🏡</div>
                        <div class="aim-content">
                            <p class="aim-text"><?= htmlspecialchars($addr['dia_chi_cu_the']) ?></p>
                        </div>
                        <button type="button" class="btn-use-address">Dùng</button>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div style="margin-top:15px; text-align:center; border-top:1px dashed #eee; padding-top:10px;">
                    <a href="index.php?page=account&section=addresses" style="color:#0f62fe; font-size:13px; text-decoration:none;">Quản lý sổ địa chỉ</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
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
                                Giảm: <?= ($cp['loai_khuyen_mai'] == 'phan_tram') ? $cp['gia_tri'] . '%' : number_format($cp['gia_tri']) . '₫' ?>
                            </p>
                        </div>
                        <button type="button" class="btn-apply-from-modal" data-code="<?= htmlspecialchars($cp['ten']) ?>">Chọn</button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="alert-modal-overlay" id="alert-modal-overlay">
    <div class="alert-modal-box" id="alert-modal-box">
        <div class="alert-modal-header"><h3 id="alert-modal-title" style="margin:0;">Thông báo</h3></div>
        <div class="alert-modal-body" style="padding: 20px 0;">
            <p id="alert-modal-message" style="font-size: 16px; text-align: center;">Nội dung</p>
        </div>
        <div class="alert-modal-footer"><button id="btn-alert-close" class="btn btn-primary" style="width: 100%;">OK</button></div>
    </div>
</div>

<script>
// --- LOGIC CHỌN ĐỊA CHỈ (MỚI) ---
function selectAddress(addressText) {
    const inputAddr = document.getElementById('input-shipping-address');
    const modal = document.getElementById('address-modal-overlay');
    if(inputAddr) {
        inputAddr.value = addressText;
        // Hiệu ứng nháy nhẹ để biết đã điền
        inputAddr.style.borderColor = '#0f62fe';
        setTimeout(() => inputAddr.style.borderColor = '#e5e7eb', 500);
    }
    if(modal) modal.style.display = 'none';
}

document.addEventListener('DOMContentLoaded', function() {
    // 1. Xử lý Modal Địa chỉ
    const btnOpenAddr = document.getElementById('open-address-modal');
    const btnCloseAddr = document.getElementById('close-address-modal');
    const modalAddr = document.getElementById('address-modal-overlay');

    if(btnOpenAddr) {
        btnOpenAddr.addEventListener('click', (e) => {
            e.preventDefault();
            modalAddr.style.display = 'flex';
        });
    }
    if(btnCloseAddr) {
        btnCloseAddr.addEventListener('click', () => { modalAddr.style.display = 'none'; });
    }
    // Đóng khi click ngoài
    window.addEventListener('click', (e) => {
        if (e.target === modalAddr) modalAddr.style.display = 'none';
        if (e.target === document.getElementById('coupon-modal-overlay')) document.getElementById('coupon-modal-overlay').style.display = 'none';
    });

    // 2. Validate Form & Payment Method (Giữ nguyên logic cũ)
    const paymentRadios = document.querySelectorAll('input[name="payment_method"]');
    paymentRadios.forEach(radio => {
        radio.addEventListener('change', () => {
            paymentRadios.forEach(r => {
                if(r.checked) r.closest('.payment-method-box').classList.add('active');
                else r.closest('.payment-method-box').classList.remove('active');
            });
        });
    });

    const checkoutForm = document.getElementById('checkout-form');
    if (checkoutForm) {
        checkoutForm.addEventListener('submit', function(e) {
            let errors = [];
            const name = document.querySelector('input[name="ho_ten"]').value.trim();
            const phone = document.querySelector('input[name="so_dien_thoai"]').value.trim();
            const address = document.querySelector('input[name="dia_chi"]').value.trim();
            
            if (name.length < 2) errors.push("Họ tên quá ngắn.");
            const phoneRegex = /^(03|05|07|08|09)+([0-9]{8})$/;
            if (!phoneRegex.test(phone)) errors.push("Số điện thoại không hợp lệ.");
            if (address.length < 5) errors.push("Vui lòng nhập địa chỉ chi tiết.");

            if (errors.length > 0) {
                e.preventDefault();
                alert("Lỗi nhập liệu:\n- " + errors.join("\n- "));
            } else {
                if(!confirm("Xác nhận đặt hàng?")) e.preventDefault();
            }
        });
    }

    // 3. Logic Mã giảm giá (Giữ nguyên logic AJAX cũ của bạn)
    const btnOpenCoupon = document.getElementById('open-coupon-modal');
    const btnCloseCoupon = document.getElementById('close-coupon-modal');
    const couponOverlay = document.getElementById('coupon-modal-overlay');
    const alertOverlay = document.getElementById('alert-modal-overlay');
    const btnAlertClose = document.getElementById('btn-alert-close');
    const couponInput = document.getElementById('coupon-input');
    const btnApplyManual = document.getElementById('btn-apply-coupon'); 
    const btnRemoveManual = document.getElementById('btn-remove-coupon'); 
    let needReload = false;

    if (btnOpenCoupon) btnOpenCoupon.addEventListener('click', (e) => { e.preventDefault(); couponOverlay.style.display = 'flex'; });
    if (btnCloseCoupon) btnCloseCoupon.addEventListener('click', () => { couponOverlay.style.display = 'none'; });

    if (btnAlertClose) {
        btnAlertClose.addEventListener('click', function() {
            alertOverlay.style.display = 'none';
            if (needReload) window.location.reload(); 
        });
    }

    async function handleCoupon(code, action) {
        couponOverlay.style.display = 'none';
        const formData = new URLSearchParams();
        formData.append('action', action);
        if (code) formData.append('code', code);

        try {
            const response = await fetch('cart-handler.php', { method: 'POST', body: formData });
            const data = await response.json();
            
            document.getElementById('alert-modal-title').textContent = data.status === 'success' ? 'Thành công' : 'Thất bại';
            document.getElementById('alert-modal-message').textContent = data.message;
            document.getElementById('alert-modal-box').className = 'alert-modal-box ' + (data.status === 'success' ? 'success' : 'error');
            
            alertOverlay.style.display = 'flex';
            if(data.status === 'success') needReload = true;
        } catch (err) { console.error(err); }
    }

    if (btnApplyManual) btnApplyManual.addEventListener('click', (e) => { e.preventDefault(); handleCoupon(couponInput.value, 'apply_coupon'); });
    if (btnRemoveManual) btnRemoveManual.addEventListener('click', (e) => { e.preventDefault(); handleCoupon(null, 'remove_coupon'); });

    document.querySelectorAll('.btn-apply-from-modal').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            handleCoupon(this.getAttribute('data-code'), 'apply_coupon');
        });
    });
});
</script>

<?php require_once 'client/layouts/footer.php'; ?>