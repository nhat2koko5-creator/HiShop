<?php 
// FILE: client/pages/checkout.php

// 1. KIỂM TRA LUỒNG "MUA NGAY"
$is_buy_now = isset($_GET['action']) 
             && $_GET['action'] == 'buy_now' 
             && isset($_GET['variant_id'])
             && isset($_SESSION['user_id']);

if ($is_buy_now) {
    // --- LUỒNG MUA NGAY (ĐÃ FIX LẤY GIÁ GIẢM) ---
    $variant_id = (int)$_GET['variant_id'];
    $qty_buy_now = isset($_GET['quantity']) ? max(1, intval($_GET['quantity'])) : 1;
    
    // [CẬP NHẬT SQL] Join thêm bảng giảm giá để lấy thông tin khuyến mãi
    $stmt = $pdo->prepare("
        SELECT 
            sp.id AS parent_id, sp.ten, sp.hinh_anh, 
            bv.id AS bien_the_id, bv.gia, bv.mau_sac, bv.dung_luong_ssd, bv.so_luong_ton,
            gg.loai_giam_gia, gg.gia_tri
        FROM bien_the_san_pham AS bv
        JOIN san_pham AS sp ON bv.san_pham_id = sp.id
        LEFT JOIN san_pham_giam_gia spgg ON sp.id = spgg.san_pham_id
        LEFT JOIN giam_gia gg ON spgg.giam_gia_id = gg.id
            AND (gg.ngay_bat_dau IS NULL OR gg.ngay_bat_dau <= NOW())
            AND (gg.ngay_ket_thuc IS NULL OR gg.ngay_ket_thuc >= NOW())
        WHERE bv.id = ? AND bv.so_luong_ton > 0
    ");
    $stmt->execute([$variant_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        echo "<script>alert('Sản phẩm không hợp lệ hoặc đã hết hàng.'); window.location.href='index.php?page=home';</script>";
        exit;
    }
    
    if ($qty_buy_now > $item['so_luong_ton']) $qty_buy_now = $item['so_luong_ton'];

    // [THÊM LOGIC] Tính giá sau giảm (nếu có)
    $final_price = (float)$item['gia'];
    if (!empty($item['loai_giam_gia']) && !empty($item['gia_tri'])) {
        if ($item['loai_giam_gia'] === 'percent') {
            $final_price -= ($final_price * ($item['gia_tri'] / 100));
        } elseif ($item['loai_giam_gia'] === 'amount') {
            $final_price -= $item['gia_tri'];
        }
    }
    if ($final_price < 0) $final_price = 0;

    // Lưu vào session với giá ĐÃ GIẢM
    $cart_items = [
        [
            'san_pham_id' => $item['parent_id'], 
            'variant_id'  => $item['bien_the_id'], 
            'ten'         => $item['ten'],
            'mau_sac'     => $item['mau_sac'],     
            'dung_luong_ssd' => $item['dung_luong_ssd'], 
            'hinh_anh'    => $item['hinh_anh'],
            'gia'         => $final_price, // Dùng giá đã tính toán
            'gia_goc'     => $item['gia'], // Lưu thêm giá gốc để hiển thị nếu cần
            'so_luong'    => $qty_buy_now
        ]
    ];
    
    $subtotal = $final_price * $qty_buy_now;
    $_SESSION['buy_now_item'] = $cart_items[0]; 

}else {
    // --- LUỒNG GIỎ HÀNG ---
    unset($_SESSION['buy_now_item']); 
    
    $selected_ids_input = $_GET['selected_ids'] ?? '';
    $selected_ids_arr = [];

    if (!empty($selected_ids_input)) {
        $parts = explode(',', $selected_ids_input);
        $selected_ids_arr = array_filter($parts, 'is_numeric');
    }

    if (empty($selected_ids_arr)) {
        echo "<script>alert('Vui lòng chọn ít nhất 1 sản phẩm để thanh toán.'); window.location.href='index.php?page=cart';</script>";
        exit;
    }

    $cartData = getCartItemsAndTotal($pdo, $_SESSION['user_id'], $selected_ids_arr); 
    
    $cart_items = $cartData['items'];
    $subtotal = $cartData['total'];

    if (empty($cart_items)) {
        echo "<script>alert('Giỏ hàng không hợp lệ.'); window.location.href='index.php?page=cart';</script>";
        exit; 
    }
}

// 3. LẤY DATA NGƯỜI DÙNG & MÃ KM
$user_profile = getUserProfile($pdo, $_SESSION['user_id']);
$available_coupons = getAvailableCoupons($pdo); 
$saved_addresses = getUserAddresses($pdo, $_SESSION['user_id']);

// 4. TÍNH TOÁN MÃ GIẢM GIÁ (Có Validate lại điều kiện)
$discount = 0;
$promo_code = '';
$promo_message = '';
$promo_status_class = '';

if (isset($_SESSION['promo']) && is_array($_SESSION['promo'])) { 
    $coupon = $_SESSION['promo'];
    
    // [QUAN TRỌNG] Kiểm tra lại: Tổng tiền hiện tại có đủ điều kiện không?
    $min_condition = isset($coupon['min_order']) ? (float)$coupon['min_order'] : 0;

    if ($subtotal < $min_condition) {
        // Nếu mua ngay số lượng ít -> không đủ điều kiện -> Tự động gỡ mã
        unset($_SESSION['promo']);
        $promo_code = '';
        $promo_message = '';
    } else {
        // Đủ điều kiện -> Tính tiền
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
}

$shipping = 0; 
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
        
        <?php if(!empty($promo_code)): ?>
            <input type="hidden" name="coupon_code" value="<?= htmlspecialchars($promo_code) ?>">
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
            <button class="close-modal-btn" id="close-coupon-modal" type="button">&times;</button>
        </div>
        <div class="coupon-modal-body">
            <?php if (empty($available_coupons)): ?>
                <p style="text-align: center; padding: 20px; color: #666;">Không có mã giảm giá nào khả dụng.</p>
            <?php else: ?>
                <?php foreach ($available_coupons as $cp): ?>
                    <?php 
                        $limit = (int)$cp['so_luong'];
                        $used  = (int)$cp['da_dung'];
                        $is_out_of_stock = ($limit > 0 && $used >= $limit);
                        $remaining = $limit - $used;
                        $remaining_text = ($limit > 0) ? "Còn lại: $remaining" : "Không giới hạn";
                    ?>
                    <div class="coupon-item <?= $is_out_of_stock ? 'disabled-coupon' : '' ?>" 
                         style="<?= $is_out_of_stock ? 'opacity: 0.6; background: #f9f9f9;' : '' ?>">
                        <div class="coupon-info">
                            <strong style="color: var(--color-primary); font-size: 16px;">
                                <?= htmlspecialchars($cp['ten']) ?>
                            </strong>
                            <p style="margin: 4px 0; font-size: 13px; color: #333; font-weight: 600;">
                                Giảm: <?= ($cp['loai_khuyen_mai'] == 'phan_tram') ? number_format($cp['gia_tri']) . '%' : number_format($cp['gia_tri']) . '₫' ?>
                            </p>
                            <div style="font-size: 12px; color: #666;">
                                <?php if ((float)$cp['dieu_kien'] > 0): ?>
                                    <span><i class="fa-solid fa-circle-exclamation" style="font-size: 10px; color: #f59e0b;"></i> Đơn tối thiểu: <?= number_format($cp['dieu_kien']) ?>₫</span><br>
                                <?php endif; ?>
                                <?php if ($is_out_of_stock): ?>
                                    <span style="color: #ef4444; font-weight: bold;">Đã hết lượt sử dụng</span>
                                <?php else: ?>
                                    <span style="color: #10b981;"><?= $remaining_text ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($is_out_of_stock): ?>
                            <button type="button" class="btn-apply-from-modal" disabled style="background:#ccc; cursor:not-allowed;">Hết lượt</button>
                        <?php else: ?>
                            <button type="button" class="btn-apply-from-modal" data-code="<?= htmlspecialchars($cp['ten']) ?>">Chọn</button>
                        <?php endif; ?>
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
        <div class="alert-modal-footer">
            <button id="btn-alert-close" type="button" class="btn btn-primary" style="width: 100%;">OK</button>
        </div>
    </div>
</div>

<script>
// --- 1. TỰ ĐỘNG RELOAD TRANG KHI VÀO TỪ CACHE (Back/Forward) ---
window.addEventListener('pageshow', (event) => {
    if (event.persisted || (window.performance && window.performance.navigation.type === 2)) {
        console.log("Reloading from cache...");
        window.location.reload();
    }
});

function selectAddress(addressText) {
    const inputAddr = document.getElementById('input-shipping-address');
    const modal = document.getElementById('address-modal-overlay');
    if(inputAddr) {
        inputAddr.value = addressText;
        inputAddr.style.borderColor = '#0f62fe';
        setTimeout(() => inputAddr.style.borderColor = '#e5e7eb', 500);
    }
    if(modal) modal.style.display = 'none';
}

document.addEventListener('DOMContentLoaded', function() {
    
    // --- KHỞI TẠO BIẾN ---
    const couponOverlay = document.getElementById('coupon-modal-overlay');
    const alertOverlay = document.getElementById('alert-modal-overlay');
    const btnAlertClose = document.getElementById('btn-alert-close');
    const addressOverlay = document.getElementById('address-modal-overlay');

    function bindClick(id, callback) {
        const el = document.getElementById(id);
        if(el) el.addEventListener('click', callback);
    }

    // --- XỬ LÝ CLICK ---
    bindClick('open-address-modal', (e) => { e.preventDefault(); addressOverlay.style.display = 'flex'; });
    bindClick('close-address-modal', () => { addressOverlay.style.display = 'none'; });
    bindClick('open-coupon-modal', (e) => { e.preventDefault(); couponOverlay.style.display = 'flex'; });
    bindClick('close-coupon-modal', () => { couponOverlay.style.display = 'none'; });

    window.addEventListener('click', (e) => {
        if (e.target === addressOverlay) addressOverlay.style.display = 'none';
        if (e.target === couponOverlay) couponOverlay.style.display = 'none';
        if (e.target === alertOverlay && btnAlertClose) btnAlertClose.click(); 
    });

    // --- XỬ LÝ AJAX COUPON (QUAN TRỌNG) ---
    let successReload = false;

    if(btnAlertClose) {
        btnAlertClose.addEventListener('click', function() {
            alertOverlay.style.display = 'none';
            if (successReload) window.location.reload(); 
        });
    }

    async function handleCoupon(code, action) {
        if(couponOverlay) couponOverlay.style.display = 'none';

        const formData = new URLSearchParams();
        formData.append('action', action);
        if (code) formData.append('code', code);

        try {
            const response = await fetch('cart-handler.php', { method: 'POST', body: formData });
            const text = await response.text();
            let data;
            
            try { 
                data = JSON.parse(text); 
            } catch (e) {
                console.error("JSON Error:", text);
                alert("Lỗi dữ liệu từ hệ thống.");
                return;
            }

            const titleEl = document.getElementById('alert-modal-title');
            const msgEl = document.getElementById('alert-modal-message');
            const boxEl = document.getElementById('alert-modal-box');

            if(titleEl) titleEl.textContent = data.status === 'success' ? 'Thành công' : 'Thông báo';
            if(msgEl) msgEl.textContent = data.message;
            if(boxEl) boxEl.className = 'alert-modal-box ' + (data.status === 'success' ? 'success' : 'error');
            
            successReload = (data.status === 'success');
            if(alertOverlay) alertOverlay.style.display = 'flex';

        } catch (err) { 
            console.error(err);
            alert('Lỗi kết nối đến máy chủ!');
        }
    }

    const btnApply = document.getElementById('btn-apply-coupon');
    const btnRemove = document.getElementById('btn-remove-coupon');
    const inputC = document.getElementById('coupon-input');

    if(btnApply) btnApply.addEventListener('click', (e) => { 
        e.preventDefault(); handleCoupon(inputC.value, 'apply_coupon'); 
    });
    
    if(btnRemove) btnRemove.addEventListener('click', (e) => { 
        e.preventDefault(); handleCoupon(null, 'remove_coupon'); 
    });

    document.querySelectorAll('.btn-apply-from-modal').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            if(!this.hasAttribute('disabled')) {
                handleCoupon(this.getAttribute('data-code'), 'apply_coupon');
            }
        });
    });
    
    // --- VALIDATE FORM ---
    const checkoutForm = document.getElementById('checkout-form');
    if (checkoutForm) {
        checkoutForm.addEventListener('submit', function(e) {
            const name = document.querySelector('input[name="ho_ten"]').value.trim();
            const phone = document.querySelector('input[name="so_dien_thoai"]').value.trim();
            const address = document.querySelector('input[name="dia_chi"]').value.trim();
            
            let errors = [];
            if (name.length < 2) errors.push("Họ tên quá ngắn.");
            if (address.length < 5) errors.push("Địa chỉ quá ngắn.");
            if (!/^(03|05|07|08|09)+([0-9]{8})$/.test(phone)) errors.push("Số điện thoại không đúng định dạng.");

            if (errors.length > 0) {
                e.preventDefault();
                alert("Vui lòng kiểm tra lại:\n- " + errors.join("\n- "));
                return;
            }
            if(!confirm("Xác nhận đặt hàng?")) e.preventDefault();
        });
    }
});
</script>

<?php require_once 'client/layouts/footer.php'; ?>