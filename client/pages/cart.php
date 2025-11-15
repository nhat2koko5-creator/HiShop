<?php
require_once 'client/layouts/header.php';

// --- HÀM HELPER (Đã SỬA LỖI) ---
if (!function_exists('calculateCartTotals')) {
    function calculateCartTotals() {
        $subtotal = 0; $shipping = 0; $discount = 0;
        if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
            return ['subtotal' => 0, 'total' => 0, 'shipping' => 0, 'discount' => 0];
        }
        foreach ($_SESSION['cart'] as $item) {
            $subtotal += $item['price'] * $item['quantity'];
        }
        
        $promo_key = isset($_SESSION['promo']) ? 'promo' : 'coupon';
        if (isset($_SESSION[$promo_key])) { 
            $coupon = $_SESSION[$promo_key];
            $couponType = $coupon['type'] ?? 'fixed'; 
            
            if ($couponType == 'percent' || $couponType == 'percentage' || $couponType == 'loai_khuyen_mai' && strpos($couponType, 'percent') !== false) {
                 $discount = ($subtotal * $coupon['value']) / 100;
            } else {
                 $discount = $coupon['value'];
            }
            
            if ($discount > $subtotal) $discount = $subtotal;
        }
        
        $total = $subtotal + $shipping - $discount;
        return ['subtotal' => $subtotal, 'total' => $total, 'shipping' => $shipping, 'discount' => $discount];
    }
}
if (!function_exists('price_format')) {
    function price_format($n) {
        return number_format($n, 0, ',', '.') . '₫';
    }
}
// --- KẾT THÚC HÀM HELPER ---

$cart = $_SESSION['cart'] ?? [];
$totals = calculateCartTotals();
$img_folder = 'assets/img/products';
$default_img = 'assets/img/no-image.png';

$is_logged_in = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
$promo_key = isset($_SESSION['promo']) ? 'promo' : 'coupon';

?>

<style>
    /* (CSS Cũ CỦA BẠN - GIỮ NGUYÊN) */
    .cart-page { max-width: 1200px; margin: 40px auto; padding: 0 20px; }
    .cart-summary { flex-basis: 320px; background-color: #f9f9f9; padding: 24px; border-radius: 8px; height: fit-content; }
    .cart-item { display: flex; gap: 15px; padding-bottom: 20px; margin-bottom: 20px; border-bottom: 1px solid #eee; align-items: center; }
    .cart-item-select { width: 20px; height: 20px; flex-shrink: 0; }
    .cart-item-img { width: 100px; height: 100px; object-fit: cover; border-radius: 4px; }
    .cart-item-info { flex: 1; }
    .cart-item-name { font-size: 18px; font-weight: 600; }
    .cart-item-desc { font-size: 14px; color: #666; margin: 4px 0; }
    .cart-item-remove { font-size: 14px; color: #d90000; text-decoration: none; cursor: pointer; }
    .quantity-control { display: flex; align-items: center; border: 1px solid #ccc; border-radius: 4px; }
    .quantity-control button { background: #f5f5f5; border: none; padding: 0 10px; cursor: pointer; font-size: 18px; height: 34px; }
    .quantity-control input { width: 40px; text-align: center; border: none; height: 32px; padding: 0; }
    .quantity-control input[type=number] { -moz-appearance: textfield; }
    .btn-checkout:disabled { background-color: #ccc; cursor: not-allowed; }

    /* === CSS CHO POPUP DÙNG CHUNG (GIỮ NGUYÊN) === */
    #modal-prompt-overlay {
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0,0,0,0.6); display: none; align-items: center; justify-content: center;
        z-index: 9998; opacity: 0; transition: opacity 0.2s ease;
    }
    #modal-prompt-overlay.show { display: flex; opacity: 1; }
    
    /* ID NÀY PHẢI KHỚP VỚI HTML */
    #modal-prompt-box {
        background: #fff; padding: 30px; border-radius: 12px; text-align: center;
        width: 90%; max-width: 400px; transform: scale(0.9); transition: transform 0.2s ease;
    }
    #modal-prompt-overlay.show #modal-prompt-box { transform: scale(1); }
    #modal-prompt-message { font-size: 18px; color: #333; margin-bottom: 25px; }
    .prompt-buttons { display: flex; gap: 15px; }
    .prompt-buttons button {
        flex: 1; padding: 12px; border: none; border-radius: 8px;
        font-size: 16px; font-weight: 600; cursor: pointer;
        transition: background-color 0.2s;
    }
    #btn-prompt-secondary { 
        background: #f1f1f1; 
        color: #333; 
    }
    #btn-prompt-primary { 
        background: #0f62fe;
        color: white; 
    }
    #btn-prompt-primary.danger {
        background: #dc2626;
    }
    .prompt-buttons .hide {
        display: none;
    }

    /* ============================================
    === CSS MỚI CHO BỐ CỤC GIỎ HÀNG (SỬA Ở ĐÂY) ===
    ============================================
    */
    .cart-container {
        display: flex;
        flex-wrap: wrap; /* Cho phép xuống dòng trên mobile */
        gap: 30px;       /* Khoảng cách giữa cột trái (items) và cột phải (summary) */
    }

    .cart-items {
        flex: 1; /* Cho phép cột item co giãn, chiếm hết không gian còn lại */
        min-width: 450px; /* Chiều rộng tối thiểu trước khi cột summary bị đẩy xuống */
    }

    .cart-summary {
        /* flex-basis: 320px; (Đã có sẵn ở trên) */
        width: 100%; /* Đảm bảo chiếm 100% width khi xuống dòng trên mobile */
        position: sticky;
        top: 20px; /* Dính vào lề trên 20px khi cuộn */
    }
    
    .cart-item-price {
        font-size: 18px;
        font-weight: 600;
        min-width: 120px; /* Giữ chiều rộng cố định 1 chút */
        text-align: right;
    }

    /* ============================================
    === CSS CẢI THIỆN GIAO DIỆN SUMMARY BOX ===
    ============================================
    */
    .cart-summary h2 {
        margin-top: 0;
        margin-bottom: 20px;
    }
    .summary-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 12px;
        font-size: 16px;
    }
    .summary-row span:last-child {
        font-weight: 600;
        color: #333;
    }
    .summary-row.total {
        font-size: 20px;
        font-weight: 700;
        border-top: 1px solid #ddd;
        padding-top: 15px;
        margin-top: 15px;
    }
    .summary-row.total span:last-child {
        color: #d90000; /* Màu đỏ cho tổng tiền */
    }
    
    .coupon-form {
        display: flex;
        margin: 20px 0;
    }
    .coupon-form input[type="text"] {
        flex: 1;
        border: 1px solid #ccc;
        padding: 10px 12px;
        border-radius: 4px 0 0 4px;
        outline: none;
        font-size: 15px;
        min-width: 100px; /* Tránh bị co lại quá mức */
    }
    .coupon-form button {
        border: none;
        background: #0f62fe; /* Màu xanh dương (giống popup) */
        color: white;
        padding: 0 15px;
        cursor: pointer;
        border-radius: 0 4px 4px 0;
        font-weight: 600;
        font-size: 15px;
        transition: background-color 0.2s;
    }
    .coupon-form button:hover {
        background: #004ecc;
    }
    
    #coupon-message {
        font-size: 14px;
        margin-top: -10px; /* Nằm ngay dưới form */
        margin-bottom: 15px;
        text-align: left;
    }
    #coupon-message.success { 
        color: #15803d; /* Xanh lá */
        font-weight: 500;
    }
    #coupon-message.error { 
        color: #dc2626; /* Đỏ */
        font-weight: 500;
    }
    
    #discount-row {
        color: #15803d; /* Màu xanh cho giảm giá */
    }

    .btn-checkout {
        width: 100%;
        padding: 14px;
        font-size: 18px;
        font-weight: 700;
        background: #0f62fe; /* Đồng bộ màu nút chính */
        color: white;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        transition: background-color 0.2s;
        text-align: center;
    }
    .btn-checkout:hover:not(:disabled) {
        background: #004ecc;
    }

</style>

<div class="cart-page">
    <h1 style ="margin-bottom: 30px">Giỏ Hàng Của Bạn</h1>
    <div class="cart-container" id="cart-wrapper">
        <?php if (empty($cart)): ?>
            <div class="cart-empty-msg" style="width: 100%; text-align: center; padding: 50px; background: #f9f9f9; border-radius: 8px;">
                <h2>Giỏ hàng của bạn đang trống</h2>
                <p>Hãy quay lại trang sản phẩm để lựa chọn nhé.</p>
                
                <a href="index.php?page=product_list" class="btn-checkout" style="text-decoration: none; display: inline-block; width: auto; padding: 10px 20px; margin-top: 20px;">
                    Xem sản phẩm
                </a>
            </div>
        <?php else: ?>
            <div class="cart-items">
                <?php foreach ($cart as $key => $item): ?>
                    <?php $img_path = (!empty($item['image']) && file_exists($img_folder . '/' . $item['image'])) ? $img_folder . '/' . $item['image'] : $default_img; ?>
                    <div class="cart-item" id="item-<?php echo $key; ?>">
                        <input type="checkbox" class="cart-item-select" data-key="<?php echo $key; ?>" data-price="<?php echo $item['price']; ?>">
                        <img src="<?php echo htmlspecialchars($img_path); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" class="cart-item-img">
                        <div class="cart-item-info">
                            <span class="cart-item-name"><?php echo htmlspecialchars($item['name']); ?></span>
                            <span class="cart-item-desc"><?php echo htmlspecialchars($item['color'] ?? ''); ?> / <?php echo htmlspecialchars($item['ssd'] ?? ''); ?></span>
                            <br>
                            <a class="cart-item-remove" data-key="<?php echo $key; ?>">Xóa</a>
                        </div>
                        <div class="quantity-control">
                            <button class="btn-quantity" data-key="<?php echo $key; ?>" data-change="-1">-</button>
                            <input type="number" class="input-quantity" id="quantity-<?php echo $key; ?>" value="<?php echo $item['quantity']; ?>" min="1" data-key="<?php echo $key; ?>">
                            <button class="btn-quantity" data-key="<?php echo $key; ?>" data-change="1">+</button>
                        </div>
                        <span class="cart-item-price" id="item-total-<?php echo $key; ?>">
                            <?php echo price_format($item['price'] * $item['quantity']); ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="cart-summary">
                <h2>Tóm tắt đơn hàng</h2>
                <div class="summary-row">
                    <span>Tổng (<span id="summary-item-count">...</span> sản phẩm)</span>
                    <span id="summary-subtotal"><?php echo price_format($totals['subtotal']); ?></span>
                </div>
                <div class="summary-row">
                    <span>Phí vận chuyển</span>
                    <span id="summary-shipping">Miễn phí</span>
                </div>
                <div class="summary-row" id="discount-row" style="<?php echo ($totals['discount'] == 0) ? 'display: none;' : ''; ?>">
                    <span id="discount-label">Giảm giá (<?php echo $_SESSION['promo']['code'] ?? ''; ?>)</span>
                    <span id="summary-discount">-<?php echo price_format($totals['discount']); ?></span>
                </div>
                <div class="coupon-form">
                    <input type="text" id="coupon-input" placeholder="Mã khuyến mãi" value="<?php echo $_SESSION['promo']['code'] ?? ''; ?>">
                    <button id="btn-apply-coupon">Áp dụng</button>
                </div>
                <div id="coupon-message"></div> 
                
                <div class="summary-row total">
                    <span>Tổng cộng</span>
                    <span id="summary-total"><?php echo price_format($totals['total']); ?></span>
                </div>
                <button class="btn-checkout" id="btn-checkout">Mua ngay</button>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="modal-prompt-overlay" id="modal-prompt-overlay">
    <div class="modal-prompt-box" id="modal-prompt-box">
        <p class="modal-prompt-message" id="modal-prompt-message">Nội dung thông báo</p>
        <div class="prompt-buttons">
            <button class="btn-prompt-secondary" id="btn-prompt-secondary">Nút Phụ</button>
            <button class="btn-prompt-primary" id="btn-prompt-primary">Nút Chính</button>
        </div>
    </div>
</div>


<script>
document.addEventListener('DOMContentLoaded', function() {
    
    let currentCoupon = <?php echo isset($_SESSION['promo']) ? json_encode($_SESSION['promo']) : 'null'; ?>;
    const isLoggedIn = <?php echo $is_logged_in ? 'true' : 'false'; ?>;
    const btnCheckout = document.getElementById('btn-checkout');

    const modalOverlay = document.getElementById('modal-prompt-overlay');
    const modalMessage = document.getElementById('modal-prompt-message');
    const btnPromptPrimary = document.getElementById('btn-prompt-primary');
    const btnPromptSecondary = document.getElementById('btn-prompt-secondary');

    let itemKeyToDelete = null; 
    let modalState = 'none'; // 'login', 'delete', 'alert'

    // --- HÀM 1: GỬI AJAX ---
    async function sendCartRequest(action, data) {
        const formData = new URLSearchParams();
        formData.append('action', action);
        for (const key in data) formData.append(key, data[key]);
        try {
            const response = await fetch('cart-handler.php', { method: 'POST', body: formData });
            if (!response.ok) {
                const errorText = await response.text();
                try {
                    return JSON.parse(errorText);
                } catch (e) {
                    return { status: 'error', message: `Lỗi Server (${response.status}): ${errorText}` };
                }
            }
            return await response.json();
        } catch (err) {
            console.error('Fetch Error:', err);
            return { status: 'error', message: 'Lỗi kết nối. Kiểm tra (F12) tab Network.' };
        }
    }

    // --- HÀM 2: TÍNH TOÁN TỔNG TIỀN ---
    function recalculateAndDisplaySummary() {
        let subtotal = 0, selectedItemCount = 0;
        document.querySelectorAll('.cart-item-select').forEach(checkbox => {
            if (checkbox.checked) {
                const key = checkbox.dataset.key, price = parseFloat(checkbox.dataset.price);
                const quantityEl = document.getElementById('quantity-' + key);
                if(quantityEl) {
                    const quantity = parseInt(quantityEl.value);
                    if (!isNaN(price) && !isNaN(quantity)) {
                        subtotal += price * quantity;
                        selectedItemCount++;
                    }
                }
            }
        });
        let discount = 0;
        if (currentCoupon) {
            let couponType = currentCoupon.type || (currentCoupon.loai_khuyen_mai || 'fixed');
            if (couponType.includes('percent')) discount = (subtotal * parseFloat(currentCoupon.value || currentCoupon.gia_tri)) / 100;
            else discount = parseFloat(currentCoupon.value || currentCoupon.gia_tri);
            if (discount > subtotal) discount = subtotal;
        }
        const total = subtotal - discount;
        
        // Cập nhật DOM (Chỉ cập nhật nếu element tồn tại)
        const countEl = document.getElementById('summary-item-count');
        const subtotalEl = document.getElementById('summary-subtotal');
        const totalEl = document.getElementById('summary-total');
        const discountRow = document.getElementById('discount-row');
        
        if (countEl) countEl.textContent = selectedItemCount;
        if (subtotalEl) subtotalEl.textContent = formatPrice(subtotal);
        if (totalEl) totalEl.textContent = formatPrice(total);
        
        if (discountRow) {
            if (discount > 0) {
                document.getElementById('summary-discount').textContent = '-' + formatPrice(discount);
                document.getElementById('discount-label').textContent = `Giảm giá (${currentCoupon.code || currentCoupon.ten})`;
                discountRow.style.display = 'flex';
            } else {
                discountRow.style.display = 'none';
            }
        }
        if (btnCheckout) btnCheckout.disabled = (selectedItemCount === 0);
    }
    function formatPrice(n) { return n.toLocaleString('vi-VN', { style: 'currency', currency: 'VND' }); }

    // --- HÀM 3: CẬP NHẬT SỐ LƯỢNG ---
    async function handleUpdateQuantity(key, quantity) {
        if (quantity < 1) {
            quantity = 1;
            document.getElementById('quantity-' + key).value = 1;
        }
        const data = await sendCartRequest('update', { key: key, quantity: quantity });
        if (data.status === 'success') {
            document.getElementById('item-total-' + key).textContent = data.item_total;
            recalculateAndDisplaySummary();
            // Hàm updateCartIconCount này giả định bạn có ở file header.php
            if (typeof updateCartIconCount === 'function') {
                updateCartIconCount(data.totalItems);
            }
        } else {
            showModalAlert(data.message || 'Lỗi cập nhật.');
            if(data.totalItems && typeof updateCartIconCount === 'function') {
                updateCartIconCount(data.totalItems);
            }
        }
    }

    // --- HÀM 4: LOGIC XÓA SẢN PHẨM ---
    async function handleDeleteItem(key) {
        const data = await sendCartRequest('delete', { key: key });
        if (data.status === 'success') {
            document.getElementById('item-' + key).remove();
            recalculateAndDisplaySummary();
             if (typeof updateCartIconCount === 'function') {
                updateCartIconCount(data.totalItems); 
             }
            if (data.cart_empty) {
                // === SỬA LỖI 2/2 TẠI ĐÂY ===
                // Sửa: 'product' -> 'product_list'
                document.getElementById('cart-wrapper').innerHTML = `<div class="cart-empty-msg" style="width: 100%; text-align: center; padding: 50px; background: #f9f9f9; border-radius: 8px;"><h2>Giỏ hàng của bạn đang trống</h2>
                <a href="index.php?page=product_list" class="btn-checkout" style="text-decoration: none; display: inline-block; width: auto; padding: 10px 20px; margin-top: 20px;">
                    Xem sản phẩm
                </a>
                </div>`;
            }
        } else { 
            showModalAlert(data.message || 'Lỗi khi xóa.'); 
        }
        hideModalPrompt();
    }

    // --- HÀM 5: Quản lý Popup ---
    function showLoginPrompt() {
        modalState = 'login';
        modalMessage.textContent = 'Bạn cần đăng nhập để tiếp tục!';
        btnPromptSecondary.textContent = 'Quay lại';
        btnPromptPrimary.textContent = 'Đăng nhập';
        
        btnPromptPrimary.classList.remove('danger', 'hide');
        btnPromptSecondary.classList.remove('hide');

        if (modalOverlay) modalOverlay.classList.add('show');
    }
    
    function showDeletePrompt(key) {
        modalState = 'delete';
        itemKeyToDelete = key;
        
        modalMessage.textContent = 'Bạn có xác nhận xóa không?'; 
        btnPromptSecondary.textContent = 'Quay lại';
        btnPromptPrimary.textContent = 'Xác nhận';
        
        btnPromptPrimary.classList.add('danger');
        btnPromptPrimary.classList.remove('hide');
        btnPromptSecondary.classList.remove('hide');

        if (modalOverlay) modalOverlay.classList.add('show');
    }

    function showModalAlert(message) {
        modalState = 'alert';
        modalMessage.textContent = message;
        btnPromptSecondary.textContent = 'OK';
        
        btnPromptPrimary.classList.add('hide');
        btnPromptSecondary.classList.remove('hide');

        if (modalOverlay) modalOverlay.classList.add('show');
    }

    function hideModalPrompt() {
        itemKeyToDelete = null;
        modalState = 'none';
        if (modalOverlay) modalOverlay.classList.remove('show');
    }

    // --- GÁN SỰ KIỆN ---
    document.querySelectorAll('.cart-item-select').forEach(cb => cb.addEventListener('change', recalculateAndDisplaySummary));
    document.querySelectorAll('.input-quantity').forEach(inp => inp.addEventListener('change', e => handleUpdateQuantity(e.target.dataset.key, parseInt(e.target.value))));
    document.querySelectorAll('.btn-quantity').forEach(btn => {
        btn.addEventListener('click', e => {
            const key = e.target.dataset.key, change = parseInt(e.target.dataset.change);
            const input = document.getElementById('quantity-' + key);
            let newQty = parseInt(input.value) + change;
            if (newQty < 1) newQty = 1;
            input.value = newQty;
            handleUpdateQuantity(key, newQty);
        });
    });

    document.querySelectorAll('.cart-item-remove').forEach(btn => {
        btn.addEventListener('click', e => {
            e.preventDefault();
            showDeletePrompt(e.target.dataset.key);
        });
    });
    
    if(modalOverlay) {
        btnPromptSecondary.addEventListener('click', () => {
            hideModalPrompt();
        });
        modalOverlay.addEventListener('click', e => { 
            if (e.target === modalOverlay) hideModalPrompt(); 
        });
        btnPromptPrimary.addEventListener('click', () => {
            if (modalState === 'delete' && itemKeyToDelete) {
                handleDeleteItem(itemKeyToDelete);
            } else if (modalState === 'login') {
                window.location.href = 'index.php?page=login&redirect=cart';
            }
        });
    }

    const couponBtn = document.getElementById('btn-apply-coupon');
    if (couponBtn) {
        couponBtn.addEventListener('click', async () => {
            if (!isLoggedIn) {
                showLoginPrompt();
                return;
            }
            const code = document.getElementById('coupon-input').value;
            const msgEl = document.getElementById('coupon-message');
            if (!code) { msgEl.textContent = 'Vui lòng nhập mã.'; msgEl.className = 'error'; return; }
            
            const data = await sendCartRequest('apply_coupon', { code: code });
            
            if (data.status === 'success') {
                msgEl.textContent = data.message; msgEl.className = 'success';
                currentCoupon = data.coupon;
            } else {
                msgEl.textContent = data.message; msgEl.className = 'error';
                currentCoupon = null;
            }
            recalculateAndDisplaySummary();
        });
    }
    
    if (btnCheckout) {
        btnCheckout.addEventListener('click', async e => {
            e.preventDefault();
            if (!isLoggedIn) { showLoginPrompt(); return; } 
            
            const selectedKeys = [];
            document.querySelectorAll('.cart-item-select:checked').forEach(cb => selectedKeys.push(cb.dataset.key));
            
            if (selectedKeys.length === 0) { 
                showModalAlert('Bạn chưa chọn sản phẩm nào để mua.');
                return; 
            }
            
            const data = await sendCartRequest('set_selected', { keys: JSON.stringify(selectedKeys) });
            
            if (data.status === 'success') {
                window.location.href = 'index.php?page=checkout';
            } else {
                showModalAlert(data.message || 'Có lỗi xảy ra, vui lòng thử lại.');
            }
        });
    }
    
    // Khởi chạy lần đầu
    if (!document.querySelector('.cart-empty-msg')) {
        recalculateAndDisplaySummary();
    }
});
</script>

<?php
require_once 'client/layouts/footer.php';
?>