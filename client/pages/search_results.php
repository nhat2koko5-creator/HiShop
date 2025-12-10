<?php
// FILE: client/pages/search_results.php

// 1. Lấy từ khóa tìm kiếm
$search_query = trim($_GET['query'] ?? '');

// 2. Chuẩn bị biến
$products = [];
$page_title = "Kết quả tìm kiếm"; 
$no_product_message = "Không tìm thấy sản phẩm nào phù hợp.";

require_once 'client/layouts/header.php';

// Hàm hỗ trợ format tiền tệ
if (!function_exists('format_price')) {
    function format_price($p) {
        return number_format((int)$p, 0, ',', '.') . "₫";
    }
}

// 3. XỬ LÝ TÌM KIẾM
if (!empty($search_query)) {
    $page_title = "Kết quả cho: \"" . htmlspecialchars($search_query) . "\"";
    $search_param = "%" . $search_query . "%";

    try {
        // Tìm kiếm sản phẩm
        $stmt = $pdo->prepare("
            SELECT * FROM san_pham 
            WHERE trang_thai = 1 
            AND (ten LIKE ? OR mo_ta LIKE ? OR mo_ta_chi_tiet LIKE ?)
            ORDER BY id DESC
        ");
        $stmt->execute([$search_param, $search_param, $search_param]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // [MỚI] 4. LẤY BIẾN THỂ CHO SẢN PHẨM TÌM THẤY (Để dùng cho nút Mua hàng)
        if (!empty($products)) {
            $product_ids = array_column($products, 'id');
            $placeholders = implode(',', array_fill(0, count($product_ids), '?'));

            $sql_variants = "
                SELECT id, san_pham_id, gia, so_luong_ton, mau_sac, dung_luong_ssd, hinh_anh
                FROM bien_the_san_pham
                WHERE san_pham_id IN ($placeholders)
            ";
            $stmt_variants = $pdo->prepare($sql_variants);
            $stmt_variants->execute($product_ids);
            $variants_all = $stmt_variants->fetchAll(PDO::FETCH_ASSOC);

            // Gom nhóm biến thể theo ID sản phẩm
            $variants_map = [];
            foreach ($variants_all as $v) {
                $variants_map[$v['san_pham_id']][] = $v;
            }

            // Gán biến thể vào mảng sản phẩm
            foreach ($products as $i => $prod) {
                $pid = $prod['id'];
                if (isset($variants_map[$pid])) {
                    $products[$i]['variants'] = $variants_map[$pid];
                    // Lấy giá thấp nhất để hiển thị "Từ..."
                    $prices = array_column($variants_map[$pid], 'gia');
                    $products[$i]['gia_from'] = (int)min($prices);
                } else {
                    $products[$i]['variants'] = [];
                    $products[$i]['gia_from'] = (int)$prod['gia'];
                }
            }
        }

    } catch (PDOException $e) {
        $no_product_message = "Lỗi hệ thống khi tìm kiếm.";
    }
} else {
    $no_product_message = "Vui lòng nhập từ khóa để tìm kiếm.";
}
?>

<link rel="stylesheet" href="assets/css/client/product_list.css">

<style>
    .search-container { min-height: 60vh; padding-bottom: 60px; }
    .result-count { color: #6b7280; font-size: 16px; font-weight: 400; margin-left: 10px; }
</style>

<div class="container search-container">
    
    <div class="static-page-header">
        <nav class="breadcrumb">
            <a href="index.php">Trang chủ</a> 
            <span class="divider">›</span>
            <span class="current">Tìm kiếm</span>
        </nav>
        <h1 class="page-title">
            <?= htmlspecialchars($page_title) ?>
            <?php if (!empty($products)): ?>
                <span class="result-count">(<?= count($products) ?> sản phẩm)</span>
            <?php endif; ?>
        </h1>
    </div>

    <?php if (!empty($products)): ?>
        <div class="product-grid">
            <?php foreach ($products as $p): 
                $img_path = (!empty($p['hinh_anh']) && file_exists("assets/img/products/" . $p['hinh_anh'])) ? "assets/img/products/" . $p['hinh_anh'] : "assets/img/no-image.png";
                
                // Giá hiển thị
                $display_price = isset($p['gia_from']) && $p['gia_from'] > 0 ? (int)$p['gia_from'] : (int)$p['gia'];

                // Tính giảm giá
                $stmt_disc = $pdo->prepare("SELECT g.loai_giam_gia, g.gia_tri FROM san_pham_giam_gia spg JOIN giam_gia g ON g.id = spg.giam_gia_id WHERE spg.san_pham_id = ? AND (g.ngay_bat_dau IS NULL OR g.ngay_bat_dau <= NOW()) AND (g.ngay_ket_thuc IS NULL OR g.ngay_ket_thuc >= NOW()) LIMIT 1");
                $stmt_disc->execute([$p['id']]);
                $discount = $stmt_disc->fetch(PDO::FETCH_ASSOC);

                $price_after = $display_price;
                $discount_percent = 0;

                if ($discount) {
                    if ($discount['loai_giam_gia'] === 'percent') {
                        $discount_percent = (int)$discount['gia_tri'];
                        $price_after = $display_price * (1 - $discount_percent / 100);
                    } else {
                        $amount = (int)$discount['gia_tri'];
                        $price_after = max(0, $display_price - $amount);
                        $discount_percent = $display_price > 0 ? round(($amount / $display_price) * 100) : 0;
                    }
                }
            ?>
                <div class="product-card">
                    <?php if ($discount_percent > 0): ?>
                        <div class="product-sale-tag">-<?= $discount_percent ?>%</div>
                    <?php endif; ?>

                    <div class="product-image">
                        <a href="index.php?page=product_detail&id=<?= $p['id'] ?>">
                            <img src="<?= htmlspecialchars($img_path) ?>" alt="<?= htmlspecialchars($p['ten']) ?>">
                        </a>
                    </div>

                    <div class="card-content">
                        <a href="index.php?page=product_detail&id=<?= $p['id'] ?>" class="card-title" title="<?= htmlspecialchars($p['ten']) ?>">
                            <?= htmlspecialchars($p['ten']) ?>
                        </a>

                        <div class="card-price" style="justify-content: center; margin-bottom: 8px;">
                            <?php if ($discount_percent > 0): ?>
                                <span class="card-price-old"><?= format_price($display_price) ?></span>
                                <span class="card-price-new"><?= format_price($price_after) ?></span>
                            <?php else: ?>
                                <span class="card-price-new"><?= format_price($display_price) ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="btn-group-vertical">
                            <?php if (!empty($p['variants'])): ?>
                                <a href="javascript:void(0);"
                                   class="btn-view btn-buy-now btn-quick-buy"
                                   data-product-id="<?= $p['id'] ?>"
                                   data-product-name="<?= htmlspecialchars($p['ten']) ?>"
                                   data-product-image="<?= htmlspecialchars($p['hinh_anh']) ?>"
                                   data-discount='<?= json_encode($discount) ?>'
                                   data-variants='<?= htmlspecialchars(json_encode($p['variants']), ENT_QUOTES) ?>'>
                                    🔥 Mua ngay
                                </a>

                                <a href="javascript:void(0);" 
                                   class="btn-cart btn-quick-add"
                                   title="Thêm vào giỏ"
                                   data-product-id="<?= $p['id'] ?>"
                                   data-product-name="<?= htmlspecialchars($p['ten']) ?>"
                                   data-product-image="<?= htmlspecialchars($p['hinh_anh']) ?>"
                                   data-discount='<?= json_encode($discount) ?>'
                                   data-variants='<?= htmlspecialchars(json_encode($p['variants']), ENT_QUOTES) ?>'>
                                    🛒
                                </a>
                            <?php else: ?>
                                <a href="index.php?page=cart&action=add&id=<?= $p['id'] ?>" class="btn-view btn-buy-now">
                                    🛒 Thêm vào giỏ
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="no-products">
            <img src="assets/img/empty-box.png" alt="" style="width: 60px; opacity: 0.5; margin-bottom: 15px;">
            <p><?= htmlspecialchars($no_product_message) ?></p>
            <a href="index.php" style="color:#4f46e5; font-weight:600; margin-top:10px; display:inline-block; text-decoration:none;">
                Quay lại trang chủ
            </a>
        </div>
    <?php endif; ?>
</div>

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
                    <input type="number" id="qty-input" value="1" min="1" readonly 
                        style="width: 40px; text-align: center; border: none; padding: 5px 0; -moz-appearance: textfield;">
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
            opt.textContent = displayText || 'Không xác định';
            if (parseInt(v.so_luong_ton) === 0) { opt.classList.add('disabled'); opt.title='Hết hàng'; }
            modalOptionBox.appendChild(opt);
        });

        modal.style.display = 'flex';
    }

    function closeQuickModal() {
        modal.style.display = 'none';
    }

    function updateQuantityControls() {
        let currentQty = parseInt(qtyInput.value);
        if (currentQty < 1) currentQty = 1;
        if (currentQty > maxQuantity) currentQty = maxQuantity;
        qtyInput.value = currentQty;
        qtyMinusBtn.disabled = currentQty <= 1 || maxQuantity === 0;
        qtyPlusBtn.disabled = currentQty >= maxQuantity || maxQuantity === 0;

        if (currentSelectedVariant && currentQty > 0 && currentQty <= maxQuantity) {
            if (modalAddToCartBtn.style.display !== 'none') modalAddToCartBtn.disabled = false;
            if (modalBuyNowBtn.style.display !== 'none') modalBuyNowBtn.disabled = false;
        } else {
            if (modalAddToCartBtn.style.display !== 'none') modalAddToCartBtn.disabled = true;
            if (modalBuyNowBtn.style.display !== 'none') modalBuyNowBtn.disabled = true;
        }
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
        modalStock.textContent = maxQuantity > 0 ? `Còn hàng (${maxQuantity} sản phẩm)` : 'Hết hàng';
        modalStock.className = maxQuantity > 0 ? 'stock-info' : 'stock-info out';
        
        qtyInput.value = 1;
        updateQuantityControls();
        modalMainImage.src = currentSelectedVariant.hinh_anh ? `assets/img/products/${currentSelectedVariant.hinh_anh}` : defaultProductImage;
    }

    document.querySelectorAll('.btn-quick-buy').forEach(btn => btn.addEventListener('click', openQuickModal));
    document.querySelectorAll('.btn-quick-add').forEach(btn => btn.addEventListener('click', openQuickModal));
    document.getElementById('modal-close-btn').addEventListener('click', closeQuickModal);
    document.getElementById('modal-cancel-btn').addEventListener('click', closeQuickModal);
    modal.addEventListener('click', e => { if (e.target === modal) closeQuickModal(); });
    
    modalOptionBox.addEventListener('click', e => {
        if (!e.target.classList.contains('option') || e.target.classList.contains('disabled')) return;
        modalOptionBox.querySelectorAll('.option').forEach(o => o.classList.remove('active'));
        e.target.classList.add('active');
        selectVariant(e.target.dataset.variantId);
    });

    qtyMinusBtn.addEventListener('click', () => { qtyInput.value = parseInt(qtyInput.value)-1; updateQuantityControls(); });
    qtyPlusBtn.addEventListener('click', () => { qtyInput.value = parseInt(qtyInput.value)+1; updateQuantityControls(); });

    // Logic thêm giỏ hàng (AJAX)
    async function sendCartRequest(action, data) {
        const formData = new URLSearchParams();
        formData.append('action', action);
        for (const key in data) formData.append(key, data[key]);
        try {
            const response = await fetch('cart-handler.php', { method: 'POST', body: formData });
            return await response.json();
        } catch (err) { return { status: 'error', message: 'Lỗi kết nối.' }; }
    }

    modalAddToCartBtn.addEventListener('click', async () => {
        if (!currentSelectedVariant) return;
        const data = await sendCartRequest('add', {
            id: currentProductId,
            variant_id: currentSelectedVariant.id,
            quantity: parseInt(qtyInput.value)
        });
        if(data.status==='success') {
            showPopup('🛒 Đã thêm vào giỏ hàng!');
            closeQuickModal();
            if(typeof updateCartIconCount==='function') updateCartIconCount(data.cart_count);
        } else {
            alert('Lỗi: '+data.message);
        }
    });

    modalBuyNowBtn.addEventListener('click', () => {
        if (!currentSelectedVariant) return;
        window.location.href = `index.php?page=checkout&action=buy_now&variant_id=${currentSelectedVariant.id}&qty=${parseInt(qtyInput.value)}`;
    });

    function showPopup(msg) {
        const el = document.createElement('div');
        el.textContent = msg;
        Object.assign(el.style, {
            position:'fixed', bottom:'30px', right:'30px', background:'#0f62fe', color:'#fff',
            padding:'12px 20px', borderRadius:'12px', boxShadow:'0 4px 10px rgba(0,0,0,0.2)',
            zIndex:'9999', transition:'opacity 0.5s'
        });
        document.body.appendChild(el);
        setTimeout(()=>el.style.opacity='0', 2000);
        setTimeout(()=>el.remove(), 2500);
    }
});
</script>

<?php require_once 'client/layouts/footer.php'; ?>