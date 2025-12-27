<?php
// FILE: client/pages/wishlist.php

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?page=login');
    exit;
}

// Lấy danh sách cơ bản từ DB
$wishlistProducts = getUserWishlistProducts($pdo, $_SESSION['user_id']);
?>

<link rel="stylesheet" href="/HiShop/assets/css/client/product_list.css">

<style>
    /* Grid riêng cho wishlist */
    .wishlist-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 20px;
        margin-top: 20px;
    }
    
    /* Nút xóa (Thùng rác) - Nằm đè lên góc phải ảnh */
    .btn-remove-wishlist {
        position: absolute; 
        top: 10px; 
        right: 10px; 
        z-index: 50; 
        width: 32px; height: 32px;
        background: #ffffff; 
        border: 1px solid #fee2e2; 
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        cursor: pointer; 
        transition: all 0.2s ease;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    .btn-remove-wishlist:hover {
        background: #ef4444; 
        border-color: #ef4444; 
    }
    .btn-remove-wishlist:hover i { color: #fff; }
    .btn-remove-wishlist i { color: #ef4444; font-size: 14px; transition: color 0.2s; }

    /* Responsive */
    @media (max-width: 1024px) { .wishlist-grid { grid-template-columns: repeat(3, 1fr); } }
    @media (max-width: 768px) { .wishlist-grid { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 480px) { .wishlist-grid { grid-template-columns: 1fr; } }
</style>

<div class="static-page-header">
    <div class="container">
        <nav class="breadcrumb">
            <a href="index.php">Trang chủ</a> 
            <span class="divider">/</span>
            <span class="current">Sản phẩm yêu thích</span>
        </nav>
        <h1 class="page-title" style="margin-top: 5px;">
            Sản phẩm yêu thích <span style="font-weight: 400; color: #6b7280; font-size: 18px;">(<?= count($wishlistProducts) ?>)</span>
        </h1>
    </div>
</div>

<div class="container" style="margin-bottom: 50px;">
    <?php if (empty($wishlistProducts)): ?>
        <div style="text-align: center; padding: 60px 20px; background: #fff; border: 1px dashed #e5e7eb; border-radius: 12px; margin-top: 20px;">
            <div style="width: 80px; height: 80px; background: #f9fafb; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                <i class="fa-regular fa-heart" style="font-size: 32px; color: #9ca3af;"></i>
            </div>
            <h3 style="color: #374151; font-size: 18px; margin-bottom: 10px;">Danh sách yêu thích trống</h3>
            <p style="color: #6b7280; margin-bottom: 25px;">Hãy thêm những sản phẩm bạn quan tâm vào đây để xem lại sau.</p>
            <a href="index.php?page=product_list" class="btn-basic primary" style="display: inline-block; padding: 10px 24px; background: #0f62fe; color: #fff; border-radius: 6px; text-decoration: none; font-weight: 600;">Khám phá ngay</a>
        </div>
    <?php else: ?>
        <div class="wishlist-grid">
            <?php foreach ($wishlistProducts as $p): 
                // --- A. CHUẨN BỊ DỮ LIỆU ---
                
                // 1. Lấy dữ liệu biến thể
                $variants = getProductVariants($pdo, $p['id']); 
                
                // 2. Lấy thông tin giảm giá
                $stmt_disc = $pdo->prepare("
                    SELECT g.loai_giam_gia, g.gia_tri
                    FROM san_pham_giam_gia spg
                    JOIN giam_gia g ON g.id = spg.giam_gia_id
                    WHERE spg.san_pham_id = ?
                      AND (g.ngay_bat_dau IS NULL OR g.ngay_bat_dau <= NOW())
                      AND (g.ngay_ket_thuc IS NULL OR g.ngay_ket_thuc >= NOW())
                    LIMIT 1
                ");
                $stmt_disc->execute([$p['id']]);
                $discount = $stmt_disc->fetch(PDO::FETCH_ASSOC);

                // 3. Tính toán giá hiển thị
                $minPrice = !empty($variants) ? min(array_column($variants, 'gia')) : $p['gia'];
                $price_after = $minPrice;
                $discount_percent = 0;

                if ($discount) {
                    if ($discount['loai_giam_gia'] === 'percent') {
                        $discount_percent = (int)$discount['gia_tri'];
                        $price_after = $minPrice * (1 - $discount_percent / 100);
                    } else {
                        $amount = (int)$discount['gia_tri'];
                        $price_after = max(0, $minPrice - $amount);
                        $discount_percent = $minPrice > 0 ? round(($amount / $minPrice) * 100) : 0;
                    }
                }

                // 4. [SỬA LỖI SQL] Lấy Thông số kỹ thuật (Gọi đúng tên cột: cpu, ram)
                $cpu_show = null;
                $ram_show = null;
                
                // Truy vấn trực tiếp các cột có thật trong DB
                $stmt_specs = $pdo->prepare("
                    SELECT ts.cpu, ts.ram 
                    FROM thong_so ts 
                    JOIN san_pham_thong_so spts ON spts.thong_so_id = ts.id 
                    WHERE spts.san_pham_id = ? 
                    LIMIT 1
                ");
                $stmt_specs->execute([$p['id']]);
                $specs = $stmt_specs->fetch(PDO::FETCH_ASSOC);

                if ($specs) {
                    $cpu_show = $specs['cpu'];
                    $ram_show = $specs['ram'];
                }

                // 5. Xử lý ảnh
                $img = !empty($p['hinh_anh']) ? 'assets/img/products/'.$p['hinh_anh'] : 'assets/img/no-image.png';
            ?>
            
            <div class="product-card" id="wishlist-item-<?= $p['id'] ?>" style="margin-bottom: 0;">
                
                <?php if ($discount_percent > 0): ?>
                    <div class="product-sale-tag">-<?= $discount_percent ?>%</div>
                <?php endif; ?>

                <div class="product-image">
                    <button class="btn-remove-wishlist" onclick="toggleWishlist(this, <?= $p['id'] ?>)" title="Bỏ thích">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                    
                    <a href="index.php?page=product_detail&id=<?= $p['id'] ?>">
                        <img src="<?= $img ?>" alt="<?= htmlspecialchars($p['ten']) ?>">
                    </a>
                </div>
                
                <div class="card-content">
                    <h3 class="card-title">
                        <a href="index.php?page=product_detail&id=<?= $p['id'] ?>"><?= htmlspecialchars($p['ten']) ?></a>
                    </h3>
                    
                    <div class="product-specs" style="font-size: 12px; color: #666; margin-bottom: 8px; font-weight: 500; text-align: center; min-height: 18px;">
                        <?php 
                            $spec_text = [];
                            if ($cpu_show) $spec_text[] = $cpu_show;
                            if ($ram_show) $spec_text[] = $ram_show;
                            echo implode(' | ', $spec_text);
                        ?>
                    </div>

                    <div class="card-price" style="justify-content: center; margin-bottom: 12px;">
                        <?php if ($discount_percent > 0): ?>
                            <span class="card-price-old"><?= number_format($minPrice) ?>₫</span>
                            <span class="card-price-new"><?= number_format($price_after) ?>₫</span>
                        <?php else: ?>
                            <span class="card-price-new"><?= number_format($minPrice) ?>₫</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="btn-group-vertical">
                        <?php if (!empty($variants)): ?>
                            <a href="javascript:void(0);"
                               class="btn-view btn-buy-now btn-quick-buy"
                               data-product-id="<?= $p['id'] ?>"
                               data-product-name="<?= htmlspecialchars($p['ten']) ?>"
                               data-product-image="<?= htmlspecialchars($p['hinh_anh']) ?>"
                               data-discount='<?= json_encode($discount) ?>'
                               data-variants='<?= htmlspecialchars(json_encode($variants), ENT_QUOTES) ?>'
                               data-action="buy">
                                🔥 Mua ngay
                            </a>

                            <a href="javascript:void(0);" 
                               class="btn-cart btn-quick-add"
                               title="Thêm vào giỏ"
                               data-product-id="<?= $p['id'] ?>"
                               data-product-name="<?= htmlspecialchars($p['ten']) ?>"
                               data-product-image="<?= htmlspecialchars($p['hinh_anh']) ?>"
                               data-discount='<?= json_encode($discount) ?>'
                               data-variants='<?= htmlspecialchars(json_encode($variants), ENT_QUOTES) ?>'
                               data-action="add">
                                🛒
                            </a>
                        <?php else: ?>
                            <a href="index.php?page=cart&action=add&id=<?= $p['id'] ?>" class="btn-view" style="width: 100%;">
                                🛒 Thêm vào giỏ
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
async function toggleWishlist(btn, productId) {
    // 1. Xóa ngay trên giao diện cho mượt (Optimistic UI)
    const item = document.getElementById('wishlist-item-' + productId);
    if (item) {
        item.style.transition = "all 0.3s";
        item.style.opacity = "0";
        item.style.transform = "scale(0.8)";
        setTimeout(() => item.remove(), 300);
    }

    // 2. Cập nhật số lượng trên Header (nếu có badge)
    const wishlistBadge = document.querySelector('a[href*="page=wishlist"] .badge-count'); 
    // Tìm badge trong header (dựa vào href hoặc class icon-btn bạn đã đặt ở header)
    if(wishlistBadge) {
        let count = parseInt(wishlistBadge.innerText);
        if(count > 1) {
            wishlistBadge.innerText = count - 1;
        } else {
            wishlistBadge.style.display = 'none'; // Ẩn nếu về 0
        }
    }

    // 3. Gửi yêu cầu xuống Server để xóa trong CSDL
    try {
        const formData = new FormData();
        formData.append('action', 'toggle'); // Hành động toggle sẽ tự xóa nếu đã tồn tại
        formData.append('product_id', productId);

        const response = await fetch('wishlist_handler.php', {
            method: 'POST',
            body: formData
        });
        // Không cần làm gì thêm nếu thành công
    } catch (error) {
        console.error('Lỗi khi xóa wishlist:', error);
        alert('Có lỗi xảy ra, vui lòng tải lại trang.');
    }
}
</script>

<div class="variant-modal-overlay" id="quick-add-modal" style="display: none;">
    <div class="variant-modal-box">
        <div class="variant-modal-header">
            <h3 id="modal-product-name">[Tên sản phẩm]</h3>
            <button class="close-variant-modal" id="modal-close-btn">&times;</button>
        </div>
        <div class="variant-modal-body">
            <div class="modal-product-info">
                <div class="modal-product-image">
                    <img id="modal-product-main-image" src="assets/img/no-image.png" alt="Product Image">
                </div>
                <div class="modal-product-details">
                    <div class="price" style="margin-bottom: 10px;">
                        Giá: <span class="current" id="modal-product-price" style="margin-left: 8px; font-size: 18px; font-weight: 700; color: #d70018;">--</span>
                    </div>
                    <div class="stock-info" id="modal-stock-status">Vui lòng chọn tùy chọn</div>
                </div>
            </div>
            <hr style="margin: 15px 0; border: 0; border-top: 1px solid #eee;">
            <div class="option-group" id="modal-option-group">
                <h4 style="margin-bottom: 8px;">Tùy chọn</h4>
                <div class="option-box" id="modal-option-box"></div>
            </div>
            <hr style="margin: 15px 0; border: 0; border-top: 1px solid #eee;">
            <div class="quantity-group" style="margin-top: 15px;">
                <h4 style="margin-bottom: 8px;">Số lượng</h4>
                <div class="quantity-control" style="display: flex; align-items: center; width: 120px; border: 1px solid #ccc; border-radius: 4px;">
                    <button id="qty-minus" style="padding: 5px 10px; border: none; background: none; cursor: pointer; font-size: 16px;">-</button>
                    <input type="number" id="qty-input" value="1" min="1" readonly style="width: 40px; text-align: center; border: none; padding: 5px 0; -moz-appearance: textfield;">
                    <button id="qty-plus" style="padding: 5px 10px; border: none; background: none; cursor: pointer; font-size: 16px;">+</button>
                </div>
            </div>
        </div>
        <div class="variant-modal-footer" style="display: flex; justify-content: flex-end; padding-top: 20px;">
            <button class="btn btn-outline" id="modal-cancel-btn">Hủy</button>
            <div class="action-buttons-group">
                <button class="btn btn-outline" id="modal-add-to-cart-btn" style="display:none;" disabled>🛒 Thêm vào giỏ</button>
                <button class="btn btn-primary" id="modal-buy-now-btn" style="display:none;" disabled>🔥 Mua ngay</button>
            </div>
        </div>
    </div>
</div>

<script>
// Script xử lý Modal (Copy từ Product List)
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('quick-add-modal');
    const modalProductName = document.getElementById('modal-product-name');
    const modalPrice = document.getElementById('modal-product-price');
    const modalStock = document.getElementById('modal-stock-status');
    const modalOptionBox = document.getElementById('modal-option-box');
    const modalBuyNowBtn = document.getElementById('modal-buy-now-btn');
    const modalAddToCartBtn = document.getElementById('modal-add-to-cart-btn');
    const modalMainImage = document.getElementById('modal-product-main-image');
    const qtyInput = document.getElementById('qty-input');
    const qtyMinusBtn = document.getElementById('qty-minus');
    const qtyPlusBtn = document.getElementById('qty-plus');

    let currentVariants = [];
    let currentSelectedVariant = null;
    let defaultProductImage = 'assets/img/no-image.png';
    let maxQuantity = 0;
    let currentDiscount = null; 
    let currentProductId = null;

    function openQuickModal(e) {
        e.preventDefault();
        const btn = e.currentTarget;
        
        modalProductName.textContent = btn.dataset.productName;
        const productImage = btn.dataset.productImage;
        defaultProductImage = productImage ? `assets/img/products/${productImage}` : 'assets/img/no-image.png';
        modalMainImage.src = defaultProductImage;

        try { currentVariants = JSON.parse(btn.dataset.variants); } 
        catch(e) { alert('Lỗi dữ liệu biến thể.'); return; }
        
        try { currentDiscount = JSON.parse(btn.dataset.discount ?? "null"); } 
        catch(e) { currentDiscount = null; }

        currentProductId = btn.dataset.productId;
        currentSelectedVariant = null;
        maxQuantity = 0;
        qtyInput.value = 1;
        modalPrice.textContent = '--';
        modalStock.textContent = 'Vui lòng chọn tùy chọn';
        modalStock.className = 'stock-info';
        qtyMinusBtn.disabled = true;
        qtyPlusBtn.disabled = true;

        if (btn.classList.contains('btn-quick-add')) {
            modalAddToCartBtn.style.display = 'inline-block';
            modalAddToCartBtn.disabled = true;
            modalBuyNowBtn.style.display = 'none';
        } else if (btn.classList.contains('btn-quick-buy')) {
            modalBuyNowBtn.style.display = 'inline-block';
            modalBuyNowBtn.disabled = true;
            modalAddToCartBtn.style.display = 'none';
        }

        modalOptionBox.innerHTML = '';
        currentVariants.forEach(v => {
            let displayText = (v.dung_luong_ssd ? v.dung_luong_ssd+' ' : '') + (v.mau_sac ? v.mau_sac : '');
            const opt = document.createElement('div');
            opt.className = 'option';
            opt.dataset.variantId = v.id;
            opt.textContent = displayText || 'Mặc định';
            if (parseInt(v.so_luong_ton) === 0) { opt.classList.add('disabled'); opt.title='Hết hàng'; }
            modalOptionBox.appendChild(opt);
        });

        modal.style.display = 'flex';
    }

    function closeQuickModal() {
        modal.style.display = 'none';
        if(modalOptionBox) modalOptionBox.querySelectorAll('.option').forEach(o => o.classList.remove('active'));
    }

    function updateQuantityControls() {
        let currentQty = parseInt(qtyInput.value);
        if (currentQty < 1) currentQty = 1;
        if (currentQty > maxQuantity) currentQty = maxQuantity;
        qtyInput.value = currentQty;
        qtyMinusBtn.disabled = currentQty <= 1 || maxQuantity === 0;
        qtyPlusBtn.disabled = currentQty >= maxQuantity || maxQuantity === 0;

        const isValid = currentSelectedVariant && currentQty > 0 && currentQty <= maxQuantity;
        if (modalAddToCartBtn.style.display !== 'none') modalAddToCartBtn.disabled = !isValid;
        if (modalBuyNowBtn.style.display !== 'none') modalBuyNowBtn.disabled = !isValid;
    }

    function selectVariant(variantId) {
        currentSelectedVariant = currentVariants.find(v => String(v.id) === String(variantId));
        if (!currentSelectedVariant) return;

        maxQuantity = parseInt(currentSelectedVariant.so_luong_ton);
        
        let price = parseFloat(currentSelectedVariant.gia);
        if (currentDiscount) {
            if (currentDiscount.loai_giam_gia === "percent") {
                price = price * (1 - currentDiscount.gia_tri / 100);
            } else {
                price = Math.max(0, price - currentDiscount.gia_tri);
            }
        }

        modalPrice.textContent = new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(price);
        modalStock.textContent = maxQuantity > 0 ? `Còn hàng (${maxQuantity})` : 'Hết hàng';
        modalStock.className = maxQuantity > 0 ? 'stock-info' : 'stock-info out';
        
        qtyInput.value = 1;
        updateQuantityControls();
        
        if (currentSelectedVariant.hinh_anh) {
            modalMainImage.src = `assets/img/products/${currentSelectedVariant.hinh_anh}`;
        } else {
            modalMainImage.src = defaultProductImage;
        }
    }

    document.querySelectorAll('.btn-quick-buy').forEach(btn => btn.addEventListener('click', openQuickModal));
    document.querySelectorAll('.btn-quick-add').forEach(btn => btn.addEventListener('click', openQuickModal));
    
    const closeBtn = document.getElementById('modal-close-btn');
    if(closeBtn) closeBtn.addEventListener('click', closeQuickModal);
    
    const cancelBtn = document.getElementById('modal-cancel-btn');
    if(cancelBtn) cancelBtn.addEventListener('click', closeQuickModal);
    
    modal.addEventListener('click', e => { if (e.target === modal) closeQuickModal(); });
    
    modalOptionBox.addEventListener('click', e => {
        if (!e.target.classList.contains('option') || e.target.classList.contains('disabled')) return;
        modalOptionBox.querySelectorAll('.option').forEach(o => o.classList.remove('active'));
        e.target.classList.add('active');
        selectVariant(e.target.dataset.variantId);
    });

    async function sendCartRequest(action, data) {
        const formData = new URLSearchParams();
        formData.append('action', action);
        for (const key in data) formData.append(key, data[key]);
        try {
            const response = await fetch('cart-handler.php', { method: 'POST', body: formData });
            return await response.json();
        } catch (err) {
            return { status: 'error', message: 'Lỗi kết nối.' };
        }
    }

    qtyMinusBtn.addEventListener('click', () => { qtyInput.value = parseInt(qtyInput.value)-1; updateQuantityControls(); });
    qtyPlusBtn.addEventListener('click', () => { qtyInput.value = parseInt(qtyInput.value)+1; updateQuantityControls(); });

    modalAddToCartBtn.addEventListener('click', async () => {
        if (!currentSelectedVariant) return;
        const data = await sendCartRequest('add', {
            id: currentProductId,
            variant_id: currentSelectedVariant.id,
            quantity: parseInt(qtyInput.value)
        });
        if(data.status==='success') {
            showPopup('🛒 Sản phẩm đã được thêm vào giỏ!');
            closeQuickModal();
            if(typeof updateCartIconCount==='function') updateCartIconCount(data.cart_count);
        } else {
            showPopup('Lỗi: '+data.message);
        }
    });

    modalBuyNowBtn.addEventListener('click', () => {
        if (!currentSelectedVariant) return;
        window.location.href = `index.php?page=checkout&action=buy_now&variant_id=${currentSelectedVariant.id}&quantity=${parseInt(qtyInput.value)}`;
    });

    function showPopup(msg) {
        const el = document.createElement('div');
        el.textContent = msg;
        Object.assign(el.style, {
            position:'fixed', bottom:'30px', right:'30px',
            background:'#0f62fe', color:'#fff', padding:'12px 20px',
            borderRadius:'12px', boxShadow:'0 4px 10px rgba(0,0,0,0.2)',
            zIndex:'9999', transition:'opacity 0.5s'
        });
        document.body.appendChild(el);
        setTimeout(()=>el.style.opacity='0', 2000);
        setTimeout(()=>el.remove(), 2500);
    }
});
</script>