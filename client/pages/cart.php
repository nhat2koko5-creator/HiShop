<?php
// FILE: client/pages/cart.php (ĐÃ UPDATE BIẾN THỂ & CHECKBOX)
require_once 'client/layouts/header.php';

// Bắt buộc đăng nhập
if (!isset($_SESSION['user_id'])) {
    echo "<script>
        alert('Bạn cần đăng nhập để xem giỏ hàng.');
        window.location.href='index.php?page=login&redirect=cart';
    </script>";
    exit; 
}
$user_id = $_SESSION['user_id'];

// Lấy dữ liệu giỏ hàng (Hàm này đã sửa ở Bước 1 để lấy màu/ssd)
$cartData = getCartItemsAndTotal($pdo, $user_id);
$cart = $cartData['items'];
$default_total = $cartData['total'];

// Hàm định dạng tiền
if (!function_exists('price_format')) {
    function price_format($n) {
        return number_format($n, 0, ',', '.') . '₫';
    }
}
$img_folder = 'assets/img/products';
$default_img = 'assets/img/no-image.png';
?>

<div class="cart-page">
    <h1>Giỏ hàng của bạn</h1>
    
    <div class="cart-container" id="cart-wrapper">
        <?php if (empty($cart)): ?>
            <div class="cart-empty-msg" style="width: 100%; text-align: center; padding: 60px 20px; background: #fff; border: 1px solid #e5e7eb; border-radius: 8px;">
                <img src="assets/img/empty-cart.png" alt="Empty Cart" style="width: 120px; margin-bottom: 20px; opacity: 0.5;">
                <h2 style="font-size: 18px; margin-bottom: 10px;">Giỏ hàng của bạn đang trống</h2>
                <a href="index.php?page=product_list" class="btn-checkout" style="width: auto; display: inline-block; padding: 12px 30px;">
                    Tiếp tục mua sắm
                </a>
            </div>
        <?php else: ?>
            
            <div class="cart-items">
                <div class="cart-list-header">
                    <label>
                        <input type="checkbox" id="check-all" class="cart-checkbox" checked>
                        <span>Chọn tất cả (<?php echo count($cart); ?> sản phẩm)</span>
                    </label>
                </div>

                <?php foreach ($cart as $item): 
                    // key là id của dòng trong bảng gio_hang (không phải san_pham_id hay bien_the_id)
                    $key = $item['id'];
                    // Giá đơn vị (đã được getCartItemsAndTotal tính sẵn nếu function đó làm việc đúng)
                    $unit_price = isset($item['gia']) ? (float)$item['gia'] : (float)($item['gia_bien_the'] ?? $item['gia_goc'] ?? 0);
                    $item_total = $unit_price * (int)$item['so_luong'];

                    // Ảnh: getCartItemsAndTotal đã chuẩn hóa trường hinh_anh (ưu tiên biến thể)
                    $img_name = $item['hinh_anh'] ?? $item['hinh_bien_the'] ?? $item['hinh_cha'] ?? '';
                    $img_path = (!empty($img_name) && file_exists($img_folder . '/' . $img_name)) ? $img_folder . '/' . $img_name : $default_img; 
                ?>
                <div class="cart-item" id="item-<?php echo $key; ?>">
                    
                    <input type="checkbox" 
                           class="cart-checkbox item-checkbox" 
                           data-key="<?php echo $key; ?>" 
                           data-price="<?php echo $unit_price; ?>" 
                           data-qty="<?php echo $item['so_luong']; ?>"
                           checked>

                    <img src="<?php echo htmlspecialchars($img_path); ?>" alt="<?php echo htmlspecialchars($item['ten_san_pham'] ?? 'Sản phẩm'); ?>" class="cart-item-img">
                    
                   <div class="cart-item-info">
                    <span class="cart-item-name"><?php echo htmlspecialchars($item['ten_san_pham'] ?? 'Sản phẩm'); ?></span>
                    
                    <?php if (!empty($item['mau_sac']) || !empty($item['dung_luong_ssd'])): ?>
                        <div class="cart-item-variant">
                            <?php 
                                echo htmlspecialchars($item['mau_sac'] ?? ''); 
                                if (!empty($item['mau_sac']) && !empty($item['dung_luong_ssd'])) echo ' / ';
                                echo htmlspecialchars($item['dung_luong_ssd'] ?? ''); 
                            ?>
                        </div>
                    <?php endif; ?>
                    
                    <span class="cart-item-price-single">
                        <?php echo price_format($unit_price); ?>
                    </span>

                    <a class="cart-item-remove" data-key="<?php echo $key; ?>">
                        <i class="fas fa-trash-alt"></i> Xóa
                    </a>
                </div>
                    <div class="quantity-control">
                        <button class="btn-quantity" data-key="<?php echo $key; ?>" data-change="-1">-</button>
                        <input type="number" class="input-quantity" id="quantity-<?php echo $key; ?>" value="<?php echo $item['so_luong']; ?>" min="1" data-key="<?php echo $key; ?>">
                        <button class="btn-quantity" data-key="<?php echo $key; ?>" data-change="1">+</button>
                    </div>

                    <span class="cart-item-price-total" id="item-total-text-<?php echo $key; ?>">
                        <?php echo price_format($item_total); ?>
                    </span>
                    <!-- Giá trị thô (nguyên số, VND, không định dạng) để JS parse dễ dàng -->
                    <input type="hidden" id="item-total-val-<?php echo $key; ?>" value="<?php echo $item_total; ?>">
                </div>
                <?php endforeach; ?>
            </div>

            <div class="cart-summary">
                <h2>Tóm tắt đơn hàng</h2>
                
                <div style="margin-bottom: 10px;">
                    <p style="font-size: 13px; font-weight: 600; margin-bottom: 8px;">
                        Sản phẩm đã chọn (<span id="selected-count"><?php echo count($cart); ?></span>)
                    </p>
                </div>

                <div class="summary-row">
                    <span>Tạm tính:</span>
                    <span id="summary-subtotal"><?php echo price_format($default_total); ?></span>
                </div>
                <div class="summary-row">
                    <span>Phí vận chuyển:</span>
                    <span>Miễn phí</span>
                </div>

                <div class="summary-row total">
                    <span>Tổng thanh toán:</span>
                    <span id="summary-total"><?php echo price_format($default_total); ?></span>
                </div>

                <div class="free-ship-note">
                    <i class="fas fa-truck"></i> Miễn phí vận chuyển cho đơn hàng này.
                </div>

                <form id="form-checkout" action="index.php" method="GET">
                    <input type="hidden" name="page" value="checkout">
                    <input type="hidden" name="selected_ids" id="input-selected-ids" value="">
                    
                    <button type="button" class="btn-checkout" id="btn-checkout">
                        THANH TOÁN NGAY
                    </button>
                </form>
                
                <a href="index.php?page=product_list" class="btn-continue">
                    ← Tiếp tục mua sắm
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="modal-prompt-overlay" id="modal-prompt-overlay">
    <div class="modal-prompt-box" id="modal-prompt-box">
        <p class="modal-prompt-message" id="modal-prompt-message">Nội dung thông báo</p>
        <div class="prompt-buttons">
            <button class="btn-prompt-secondary" id="btn-prompt-secondary">Hủy</button>
            <button class="btn-prompt-primary" id="btn-prompt-primary">Đồng ý</button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // --- 1. KHAI BÁO ---
    const checkAll = document.getElementById('check-all');
    const itemCheckboxes = document.querySelectorAll('.item-checkbox');
    const summarySubtotal = document.getElementById('summary-subtotal');
    const summaryTotal = document.getElementById('summary-total');
    const selectedCountEl = document.getElementById('selected-count');
    const btnCheckout = document.getElementById('btn-checkout');
    
    // Modal
    const modalOverlay = document.getElementById('modal-prompt-overlay');
    const modalMessage = document.getElementById('modal-prompt-message');
    const btnPromptPrimary = document.getElementById('btn-prompt-primary');
    const btnPromptSecondary = document.getElementById('btn-prompt-secondary');
    let itemKeyToDelete = null;

    // --- 2. LOGIC TÍNH TIỀN & CHECKBOX ---
    
    function formatPrice(n) {
        return n.toLocaleString('vi-VN', { style: 'currency', currency: 'VND' }).replace('₫', '') + '₫';
    }

    function updateSummary() {
        let total = 0;
        let count = 0;
        let selectedIds = [];

        itemCheckboxes.forEach(cb => {
            if (cb.checked) {
                const key = cb.dataset.key;
                // Lấy giá trị từ input ẩn (đã được update khi đổi số lượng)
                const itemTotal = parseFloat(document.getElementById('item-total-val-' + key).value) || 0;
                total += itemTotal;
                count++;
                selectedIds.push(key);
            }
        });

        // Cập nhật UI
        summarySubtotal.textContent = formatPrice(total);
        summaryTotal.textContent = formatPrice(total);
        selectedCountEl.textContent = count;
        btnCheckout.textContent = `THANH TOÁN NGAY (${count})`;
        
        // Cập nhật input ẩn để gửi sang checkout
        document.getElementById('input-selected-ids').value = selectedIds.join(',');

        // Cập nhật trạng thái Check All
        if (count === itemCheckboxes.length) {
            checkAll.checked = true;
            checkAll.indeterminate = false;
        } else if (count === 0) {
            checkAll.checked = false;
            checkAll.indeterminate = false;
        } else {
            checkAll.checked = false;
            checkAll.indeterminate = true; // Trạng thái gạch ngang
        }
    }

    // Sự kiện Checkbox con
    itemCheckboxes.forEach(cb => {
        cb.addEventListener('change', updateSummary);
    });

    // Sự kiện Check All
    if(checkAll) {
        checkAll.addEventListener('change', function() {
            const isChecked = this.checked;
            itemCheckboxes.forEach(cb => cb.checked = isChecked);
            updateSummary();
        });
    }
    
    // --- 3. LOGIC NÚT THANH TOÁN ---
    btnCheckout.addEventListener('click', function(e) {
        const ids = document.getElementById('input-selected-ids').value;
        if (!ids) {
            showModalAlert('Vui lòng chọn ít nhất 1 sản phẩm để thanh toán.');
            return;
        }
        // Submit form
        document.getElementById('form-checkout').submit();
    });

    // --- 4. LOGIC TĂNG GIẢM SỐ LƯỢNG (AJAX) ---
    async function sendCartRequest(action, data) {
        const formData = new URLSearchParams();
        formData.append('action', action);
        for (const key in data) formData.append(key, data[key]);
        try {
            const response = await fetch('cart-handler.php', { method: 'POST', body: formData });
            return await response.json();
        } catch (err) { return { status: 'error', message: 'Lỗi kết nối.' }; }
    }

    async function handleUpdateQuantity(key, quantity) {
        if (quantity < 1) {
            quantity = 1;
            document.getElementById('quantity-' + key).value = 1;
        }
        
        const data = await sendCartRequest('update', { key: key, quantity: quantity });
        
        if (data.status === 'success') {
            // Cập nhật Text hiển thị giá
            document.getElementById('item-total-text-' + key).textContent = data.item_total;
            
            // Cập nhật Value ẩn để hàm updateSummary dùng
            // data.item_total trả về dạng "1.000.000₫", cần parse lại
            const rawPrice = parseFloat(data.item_total.replace(/\./g, '').replace('₫', ''));
            document.getElementById('item-total-val-' + key).value = rawPrice;
            
            updateSummary(); // Tính lại tổng
        } else {
            showModalAlert(data.message || 'Lỗi cập nhật.');
        }
    }

    document.querySelectorAll('.btn-quantity').forEach(btn => {
        btn.addEventListener('click', e => {
            const key = e.target.dataset.key;
            const change = parseInt(e.target.dataset.change);
            const input = document.getElementById('quantity-' + key);
            let newQty = parseInt(input.value) + change;
            if (newQty < 1) newQty = 1;
            input.value = newQty;
            handleUpdateQuantity(key, newQty);
        });
    });

    document.querySelectorAll('.input-quantity').forEach(inp => {
        inp.addEventListener('change', e => 
            handleUpdateQuantity(e.target.dataset.key, parseInt(e.target.value))
        );
    });

    // --- 5. LOGIC XÓA ---
    document.querySelectorAll('.cart-item-remove').forEach(btn => {
        btn.addEventListener('click', e => {
            e.preventDefault();
            showDeletePrompt(e.target.dataset.key);
        });
    });

    async function handleDeleteItem(key) {
        const data = await sendCartRequest('delete', { key: key });
        if (data.status === 'success') {
            document.getElementById('item-' + key).remove();
            // Đơn giản reload để cập nhật lại danh sách & tổng
            location.reload(); 
        } else {
            showModalAlert(data.message || 'Lỗi khi xóa.');
        }
        hideModalPrompt();
    }

    // --- 6. MODAL FUNCTIONS ---
    function showDeletePrompt(key) {
        itemKeyToDelete = key;
        modalMessage.textContent = 'Bạn có muốn xóa sản phẩm này?';
        btnPromptSecondary.textContent = 'Hủy';
        btnPromptSecondary.classList.remove('hide');
        btnPromptPrimary.textContent = 'Xóa';
        btnPromptPrimary.classList.remove('hide');
        btnPromptPrimary.className = 'btn-prompt-primary danger';
        modalOverlay.classList.add('show');
    }

    function showModalAlert(msg) {
        modalMessage.textContent = msg;
        btnPromptSecondary.textContent = 'OK';
        btnPromptSecondary.classList.remove('hide');
        btnPromptPrimary.classList.add('hide');
        modalOverlay.classList.add('show');
    }

    function hideModalPrompt() {
        itemKeyToDelete = null;
        modalOverlay.classList.remove('show');
    }

    if (modalOverlay) {
        btnPromptSecondary.addEventListener('click', hideModalPrompt);
        btnPromptPrimary.addEventListener('click', () => {
            if (itemKeyToDelete) handleDeleteItem(itemKeyToDelete);
            else hideModalPrompt(); // Trường hợp Alert
        });
        modalOverlay.addEventListener('click', (e) => {
            if(e.target === modalOverlay) hideModalPrompt();
        });
    }

    // Khởi chạy lần đầu
    updateSummary();
});
</script>

<?php
require_once 'client/layouts/footer.php';
?>
