<?php
// FILE: cart.php (ĐÃ SỬA LỖI - DÙNG CSDL)
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

// (MỚI) Dùng hàm getCartItemsAndTotal (giống hệt checkout.php)
$cartData = getCartItemsAndTotal($pdo, $user_id);
$cart = $cartData['items'];
$totals = ['subtotal' => $cartData['total'], 'total' => $cartData['total'], 'discount' => 0]; // Giả lập

// (Hàm này giờ chỉ dùng để định dạng)
if (!function_exists('price_format')) {
    function price_format($n) {
        return number_format($n, 0, ',', '.') . '₫';
    }
}
$img_folder = 'assets/img/products';
$default_img = 'assets/img/no-image.png';
$is_logged_in = true; // Đã check ở trên
?>
<div class="cart-page">
    <h1 style="margin-bottom: 30px">Giỏ Hàng Của Bạn</h1>
    <div class="cart-container" id="cart-wrapper">
        <?php if (empty($cart)): ?>
        <div class="cart-empty-msg"
            style="width: 100%; text-align: center; padding: 50px; background: #f9f9f9; border-radius: 8px;">
            <h2>Giỏ hàng của bạn đang trống</h2>
            <a href="index.php?page=product_list" class="btn-checkout"
                style="text-decoration: none; display: inline-block; width: auto; padding: 10px 20px; margin-top: 20px;">
                Xem sản phẩm
            </a>
        </div>
        <?php else: ?>
        <div class="cart-items">
            <?php foreach ($cart as $item): // $key giờ là san_pham_id ?>
            <?php 
                        $key = $item['san_pham_id']; // Dùng ID sản phẩm làm key
                        $img_path = (!empty($item['hinh_anh']) && file_exists($img_folder . '/' . $item['hinh_anh'])) ? $img_folder . '/' . $item['hinh_anh'] : $default_img; 
                    ?>
            <div class="cart-item" id="item-<?php echo $key; ?>">
                <img src="<?php echo htmlspecialchars($img_path); ?>"
                    alt="<?php echo htmlspecialchars($item['ten']); ?>" class="cart-item-img">
                <div class="cart-item-info">
                    <span class="cart-item-name"><?php echo htmlspecialchars($item['ten']); ?></span>
                    <span class="cart-item-desc"></span>
                    <br>
                    <a class="cart-item-remove" data-key="<?php echo $key; ?>">Xóa</a>
                </div>
                <div class="quantity-control">
                    <button class="btn-quantity" data-key="<?php echo $key; ?>" data-change="-1">-</button>
                    <input type="number" class="input-quantity" id="quantity-<?php echo $key; ?>"
                        value="<?php echo $item['so_luong']; ?>" min="1" data-key="<?php echo $key; ?>">
                    <button class="btn-quantity" data-key="<?php echo $key; ?>" data-change="1">+</button>
                </div>
                <span class="cart-item-price" id="item-total-<?php echo $key; ?>">
                    <?php echo price_format($item['gia'] * $item['so_luong']); ?>
                </span>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="cart-summary">
            <h2>Tóm tắt đơn hàng</h2>
            <div class="summary-row">
                <span>Tổng tạm tính</span>
                <span id="summary-subtotal"><?php echo price_format($totals['subtotal']); ?></span>
            </div>
            <div class="summary-row total">
                <span>Tổng cộng</span>
                <span id="summary-total"><?php echo price_format($totals['total']); ?></span>
            </div>
            <a href="index.php?page=checkout" class="btn-checkout" id="btn-checkout" style="text-decoration: none;">
                Tiến hành Thanh Toán
            </a>
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
    // SCRIPT MỚI CHO LOGIC CSDL
    document.addEventListener('DOMContentLoaded', function() {
        // (Bỏ logic coupon và checkbox)
        const modalOverlay = document.getElementById('modal-prompt-overlay');
        const modalMessage = document.getElementById('modal-prompt-message');
        const btnPromptPrimary = document.getElementById('btn-prompt-primary');
        const btnPromptSecondary = document.getElementById('btn-prompt-secondary');
        let itemKeyToDelete = null;
        // --- HÀM 1: GỬI AJAX (Giữ nguyên) ---
        async function sendCartRequest(action, data) {
            const formData = new URLSearchParams();
            formData.append('action', action);
            for (const key in data) formData.append(key, data[key]);
            try {
                const response = await fetch('cart-handler.php', {
                    method: 'POST',
                    body: formData
                });
                return await response.json();
            } catch (err) {
                return {
                    status: 'error',
                    message: 'Lỗi kết nối.'
                };
            }
        }
        // --- HÀM 2: TÍNH TOÁN LẠI TỔNG (Đơn giản hóa) ---
        function recalculateAndDisplaySummary() {
            let subtotal = 0;
            document.querySelectorAll('.cart-item').forEach(item => {
                const key = item.id.split('-')[1];
                const priceEl = document.getElementById('item-total-' + key);
                if (priceEl) {
                    // Lấy giá trị từ text, loại bỏ '₫' và '.'
                    let priceText = priceEl.textContent.replace(/[^0-9]/g, '');
                    subtotal += parseFloat(priceText);
                }
            });
            const subtotalEl = document.getElementById('summary-subtotal');
            const totalEl = document.getElementById('summary-total');
            if (subtotalEl) subtotalEl.textContent = formatPrice(subtotal);
            if (totalEl) totalEl.textContent = formatPrice(subtotal);
        }

        function formatPrice(n) {
            return n.toLocaleString('vi-VN', {
                style: 'currency',
                currency: 'VND'
            });
        }
        // --- HÀM 3: CẬP NHẬT SỐ LƯỢNG (Giữ nguyên) ---
        async function handleUpdateQuantity(key, quantity) {
            if (quantity < 1) {
                quantity = 1;
                document.getElementById('quantity-' + key).value = 1;
            }
            // key bây giờ là product_id
            const data = await sendCartRequest('update', {
                key: key,
                quantity: quantity
            });
            if (data.status === 'success') {
                document.getElementById('item-total-' + key).textContent = data.item_total;
                recalculateAndDisplaySummary();
                if (typeof updateCartIconCount === 'function') {
                    updateCartIconCount(data.totalItems);
                }
            } else {
                showModalAlert(data.message || 'Lỗi cập nhật.');
            }
        }
        // --- HÀM 4: LOGIC XÓA (Giữ nguyên) ---
        async function handleDeleteItem(key) {
            // key bây giờ là product_id
            const data = await sendCartRequest('delete', {
                key: key
            });
            if (data.status === 'success') {
                document.getElementById('item-' + key).remove();
                recalculateAndDisplaySummary();
                if (typeof updateCartIconCount === 'function') {
                    // (Cần cập nhật hàm cart-handler để trả về totalItems)
                }
                if (data.cart_empty) {
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
        // --- HÀM 5: Quản lý Popup (Sửa lại) ---
        function showDeletePrompt(key) {
            itemKeyToDelete = key;
            modalMessage.textContent = 'Bạn có muốn xóa sản phẩm này?';
            btnPromptSecondary.textContent = 'Hủy';
            btnPromptPrimary.textContent = 'Xóa';
            btnPromptPrimary.classList.add('danger');
            btnPromptSecondary.classList.remove('hide');
            btnPromptPrimary.classList.remove('hide');
            if (modalOverlay) modalOverlay.classList.add('show');
        }

        function showModalAlert(message) {
            modalMessage.textContent = message;
            btnPromptSecondary.textContent = 'OK';
            btnPromptPrimary.classList.add('hide');
            btnPromptSecondary.classList.remove('hide');
            if (modalOverlay) modalOverlay.classList.add('show');
        }

        function hideModalPrompt() {
            itemKeyToDelete = null;
            if (modalOverlay) modalOverlay.classList.remove('show');
        }
        // --- GÁN SỰ KIỆN (Giữ nguyên) ---
        document.querySelectorAll('.input-quantity').forEach(inp => inp.addEventListener('change', e =>
            handleUpdateQuantity(e.target.dataset.key, parseInt(e.target.value))));
        document.querySelectorAll('.btn-quantity').forEach(btn => {
            btn.addEventListener('click', e => {
                const key = e.target.dataset.key,
                    change = parseInt(e.target.dataset.change);
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
        if (modalOverlay) { 
            btnPromptSecondary.addEventListener('click', () => hideModalPrompt());
            modalOverlay.addEventListener('click', e => {
                if (e.target === modalOverlay) hideModalPrompt();
            });
            btnPromptPrimary.addEventListener('click', () => {
                if (itemKeyToDelete) {
                    handleDeleteItem(itemKeyToDelete);
                }
            });
        }
        // (Bỏ logic coupon và checkout)
    });
</script>
<link rel="stylesheet" href="../assets/css/style-client.css">

<?php
require_once 'client/layouts/footer.php';
?>