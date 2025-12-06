<?php
// FILE: product_list.php (ĐÃ SỬA - HIỂN THỊ GIẢM GIÁ + GIỮ NGUYÊN PHÂN TRANG & MODAL)
require_once 'client/layouts/header.php';

/* ------------ HÀM HỖ TRỢ: LẤY GIẢM GIÁ CHO 1 SẢN PHẨM ------------- */
/* Sử dụng đúng cấu trúc DB trong hishop_db.sql:
   - san_pham_giam_gia(giam_gia_id, san_pham_id)
   - giam_gia(id, loai_giam_gia enum('percent','amount'), gia_tri, ngay_bat_dau, ngay_ket_thuc)
*/

/* ---------------- PHÂN TRANG ---------------- */
$products_per_page = 11;
$current_page = isset($_GET['p']) ? (int)$_GET['p'] : 1;
if ($current_page < 1) $current_page = 1;
$offset = ($current_page - 1) * $products_per_page;

/* ---------------- LẤY DANH MỤC (nếu có) ---------------- */
$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$category_name = "Tất cả sản phẩm";

/* Base WHERE */
$where_clauses = ['sp.trang_thai = 1'];
$params = [];

if ($category_id > 0) {
    // lấy tên danh mục
    $stmt_cat = $pdo->prepare("SELECT ten FROM danh_muc WHERE id = ?");
    $stmt_cat->execute([$category_id]);
    $cat_name = $stmt_cat->fetchColumn();
    if ($cat_name) {
        $category_name = $cat_name;
        $where_clauses[] = 'sp.danh_muc_id = ?';
        $params[] = $category_id;
    }
}

$where_sql = implode(' AND ', $where_clauses);

/* ---------------- ĐẾM TỔNG SẢN PHẨM ---------------- */
$sql_count = "SELECT COUNT(sp.id) FROM san_pham sp WHERE $where_sql";
$stmt_count = $pdo->prepare($sql_count);
if (!empty($params)) {
    $stmt_count->execute($params);
} else {
    $stmt_count->execute();
}
$total_products = (int)$stmt_count->fetchColumn();
$total_pages = $total_products > 0 ? (int)ceil($total_products / $products_per_page) : 1;
if ($current_page > $total_pages) {
    $current_page = $total_pages;
    $offset = ($current_page - 1) * $products_per_page;
}

/* ---------------- LẤY SẢN PHẨM (không lọc chỉ giảm giá) ---------------- */
/* Chú ý: dùng placeholder cho LIMIT/OFFSET bằng bindValue (PDO::PARAM_INT) */


// bind category params (positional: they were added with ? in $where_sql)
$bindIndex = 1;
if (!empty($params)) {
    foreach ($params as $p) {
        // bindParam is 1-indexed for positional ? — but we used ? placeholders earlier.
        // Because we used ? in $where_sql, it's simpler to re-prepare with positional placeholders.
        // To avoid mismatch, we'll build execute array in same order:
        // We'll call execute later with array_merge($params, [limit, offset])
    }
}

// Execute with merged array: first the positional params (if any), then limit and offset as integers
$exec_params = $params;
$exec_params[] = $products_per_page;
$exec_params[] = $offset;

// However PDO does not accept passing :limit/:offset as values in execute when prepared with named params.
// So change approach: bind named params for :limit and :offset, and execute with positional params if present.
// We'll re-prepare properly:

// Rebuild statement: if there are category params, replace the '?' placeholders with named ones to avoid confusion.
// Simpler: prepare statement dynamically with named param for category if exists.

/* ---------------- LẤY SẢN PHẨM (có thông số kỹ thuật) ---------------- */

// Rebuild WHERE condition with named parameter for category
$where_clauses2 = ['sp.trang_thai = 1'];
if ($category_id > 0) {
    $where_clauses2[] = 'sp.danh_muc_id = :category_id';
}
$where_sql2 = implode(' AND ', $where_clauses2);

// SỬA ĐỔI: Thêm JOIN với thong_so và sử dụng GROUP BY để tránh lặp hàng
$sql_products = "
    SELECT 
        sp.*, 
        MAX(ts.cpu) AS cpu, 
        MAX(ts.ram) AS ram
    FROM san_pham sp
    -- LEFT JOIN để lấy thông số kỹ thuật
    LEFT JOIN san_pham_thong_so spts ON spts.san_pham_id = sp.id
    LEFT JOIN thong_so ts ON ts.id = spts.thong_so_id
    
    WHERE $where_sql2
    
    GROUP BY sp.id, sp.ten, sp.hinh_anh, sp.gia, sp.danh_muc_id, sp.mo_ta, sp.trang_thai -- Group tất cả cột sp.*
    
    ORDER BY sp.id DESC 
    LIMIT :limit OFFSET :offset
";

$stmt_products = $pdo->prepare($sql_products);

// Bind category param nếu có
if ($category_id > 0) {
    $stmt_products->bindValue(':category_id', $category_id, PDO::PARAM_INT);
}

// bind limit/offset as integers
$stmt_products->bindValue(':limit', (int)$products_per_page, PDO::PARAM_INT);
$stmt_products->bindValue(':offset', (int)$offset, PDO::PARAM_INT);

// bind limit/offset as integers
$stmt_products->bindValue(':limit', (int)$products_per_page, PDO::PARAM_INT);
$stmt_products->bindValue(':offset', (int)$offset, PDO::PARAM_INT);

// execute
$stmt_products->execute();
$products = $stmt_products->fetchAll(PDO::FETCH_ASSOC);

/* ---------------- LẤY BIẾN THỂ TỐI ƯU ---------------- */
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

    $variants_map = [];
    foreach ($variants_all as $v) {
        $variants_map[$v['san_pham_id']][] = $v;
    }

    foreach ($products as $i => $prod) {
        $pid = $prod['id'];
        if (isset($variants_map[$pid])) {
            $products[$i]['variants'] = $variants_map[$pid];
            $prices = array_column($variants_map[$pid], 'gia');
            $products[$i]['gia_from'] = (int)min($prices);
        } else {
            $products[$i]['variants'] = [];
            $products[$i]['gia_from'] = isset($prod['gia']) ? (int)$prod['gia'] : 0;
        }
    }
}

/* ---------------- Helpers ---------------- */
$img_folder = "assets/img/products";
$default_img = "assets/img/no-image.png";
function format_price($p) {
    return number_format((int)$p, 0, ',', '.') . "₫";
}

?>
<link rel="stylesheet" href="assets/css/client/product_list.css">
<div class="container">
    <div class="static-page-header" style="font-size: 14px;">
    <nav class="breadcrumb">
        <a href="index.php">Trang chủ</a> <span class="divider">›</span>
        <?php if ($category_id > 0): ?>
            <a href="index.php?page=product_list">Danh mục</a>
            <span class="divider">›</span>
            <span class="current"><?= htmlspecialchars($category_name) ?></span>
        <?php else: ?>
            <span class="current">Danh sách sản phẩm</span>
        <?php endif; ?>
    </nav>    
    <h1 class="page-title"><?= htmlspecialchars($category_name) ?></h1>
        </div>


    <?php if (!empty($products)): ?>
<div class="product-grid">
    <?php foreach ($products as $p): 
        // 1. Xử lý ảnh
        $img_path = (!empty($p['hinh_anh']) && file_exists($img_folder . '/' . $p['hinh_anh'])) 
                    ? $img_folder . '/' . $p['hinh_anh'] 
                    : $default_img;

        // 2. Lấy giá hiển thị (Ưu tiên giá biến thể thấp nhất nếu có, không thì lấy giá gốc)
        $display_price = isset($p['gia_from']) && $p['gia_from'] > 0 ? (int)$p['gia_from'] : (int)$p['gia'];

        // 3. Tính toán giảm giá (Query này nên tối ưu bằng JOIN ở câu SQL chính, nhưng tạm thời để đây cũng được)
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
<div class="card-title"><?= htmlspecialchars($p['ten']) ?></div>

<div class="card-price" style="justify-content: center; margin-bottom: 8px;">
<?php if ($discount_percent > 0): ?>
<span class="card-price-old"><?= format_price($display_price) ?></span>
<span class="card-price-new"><?= format_price($price_after) ?></span>
<?php else: ?>
<span class="card-price-new"><?= format_price($display_price) ?></span>
<?php endif; ?>
</div>

                <?php 
                $cpu_display = !empty($p['cpu']) ? "CPU: " . htmlspecialchars($p['cpu']) : '';
                $ram_display = !empty($p['ram']) ? "RAM: " . htmlspecialchars($p['ram']) : '';
                
                $spec_line = '';
                if ($cpu_display && $ram_display) {
                    $spec_line = $cpu_display . ' || ' . $ram_display; 
                } elseif ($cpu_display) {
                    $spec_line = $cpu_display;
                } elseif ($ram_display) {
                    $spec_line = $ram_display;
                }
                ?>

                <?php if ($spec_line): ?>
                <div class="product-specs" style="font-size: 13px; color: #666; margin-bottom: 10px; font-weight: 500;">
                    <?= $spec_line ?>
                </div>
                <?php endif; ?>
<div class="btn-group-vertical">
    <?php if (!empty($p['variants'])): ?>
        <!-- Nút Thêm vào giỏ (mở modal) -->
        <a href="javascript:void(0);" 
   class="btn-cart btn-quick-add"
   data-product-id="<?= $p['id'] ?>"
   data-product-name="<?= htmlspecialchars($p['ten']) ?>"
   data-product-image="<?= htmlspecialchars($p['hinh_anh']) ?>"
   data-discount='<?= json_encode($discount) ?>'
   data-variants='<?= htmlspecialchars(json_encode($p['variants']), ENT_QUOTES) ?>'>
            🛒 Thêm vào giỏ
        </a>

        <!-- Nút Mua ngay -->
        <a href="javascript:void(0);"
   class="btn-cart btn-quick-buy"
   data-product-id="<?= $p['id'] ?>"
   data-product-name="<?= htmlspecialchars($p['ten']) ?>"
   data-product-image="<?= htmlspecialchars($p['hinh_anh']) ?>"
   data-discount='<?= json_encode($discount) ?>'
   data-variants='<?= htmlspecialchars(json_encode($p['variants']), ENT_QUOTES) ?>'>
            🔥 Mua ngay
        </a>
    <?php else: ?>
        <!-- Sản phẩm không có biến thể: Thêm trực tiếp vào giỏ -->
        <a href="index.php?page=cart&action=add&id=<?= $p['id'] ?>" class="btn-cart">
            🛒 Thêm vào giỏ
        </a>
    <?php endif; ?>
</div>


            </div>
        </div>
    <?php endforeach; ?>
</div>
</div>
    <?php else: ?>
        <p class="no-products">Không có sản phẩm nào trong danh mục này.</p>
    <?php endif; ?>

    <?php if ($total_pages > 1): ?>
        <nav class="pagination-nav">
            <ul class="pagination">
                <?php if ($current_page > 1): ?>
                    <li><a href="index.php?page=product_list&category_id=<?= $category_id ?>&p=<?= $current_page - 1 ?>">«</a></li>
                <?php endif; ?>

                <?php
                // Show a sliding window of pages
                $start = max(1, $current_page - 3);
                $end = min($total_pages, $current_page + 3);
                if ($start > 1) {
                    echo '<li><a href="index.php?page=product_list&category_id='.$category_id.'&p=1">1</a></li>';
                    if ($start > 2) echo '<li><span>…</span></li>';
                }
                for ($i = $start; $i <= $end; $i++) {
                    echo '<li><a class="'.($i==$current_page?'active':'').'" href="index.php?page=product_list&category_id='.$category_id.'&p='.$i.'">'.$i.'</a></li>';
                }
                if ($end < $total_pages) {
                    if ($end < $total_pages - 1) echo '<li><span>…</span></li>';
                    echo '<li><a href="index.php?page=product_list&category_id='.$category_id.'&p='.$total_pages.'">'.$total_pages.'</a></li>';
                }
                ?>

                <?php if ($current_page < $total_pages): ?>
                    <li><a href="index.php?page=product_list&category_id=<?= $category_id ?>&p=<?= $current_page + 1 ?>">»</a></li>
                <?php endif; ?>
            </ul>
        </nav>
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

function openQuickModal(e) {
    e.preventDefault();
    const btn = e.currentTarget;
    modalProductName.textContent = btn.dataset.productName;
    const productImage = btn.dataset.productImage;
    defaultProductImage = productImage ? `assets/img/products/${productImage}` : 'assets/img/no-image.png';
    modalMainImage.src = defaultProductImage;

    try { currentVariants = JSON.parse(btn.dataset.variants); } 
    catch(e) { alert('Lỗi dữ liệu biến thể. Vui lòng thử lại.'); return; }
    // Lấy giảm giá
try { 
    currentDiscount = JSON.parse(btn.dataset.discount ?? "null"); 
} catch(e) { 
    currentDiscount = null; 
}

    currentProductId = btn.dataset.productId;
    currentSelectedVariant = null;
    maxQuantity = 0;
    qtyInput.value = 1;
    modalPrice.textContent = '--';
    modalStock.textContent = 'Vui lòng chọn tùy chọn';
    modalStock.className = 'stock-info';
    qtyMinusBtn.disabled = true;
    qtyPlusBtn.disabled = true;

    // Kiểm tra loại nút: Thêm vào giỏ hay Mua ngay
    if (btn.classList.contains('btn-quick-add')) {
        modalAddToCartBtn.style.display = 'inline-block';
        modalAddToCartBtn.disabled = true;
        modalBuyNowBtn.style.display = 'none';
    } else if (btn.classList.contains('btn-quick-buy')) {
        modalBuyNowBtn.style.display = 'inline-block';
        modalBuyNowBtn.disabled = true;
        modalAddToCartBtn.style.display = 'none';
    }

    // Hiển thị tùy chọn gộp
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
        modalOptionBox.querySelectorAll('.option').forEach(o => o.classList.remove('active'));
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
        if (!currentSelectedVariant) {
            modalPrice.textContent = '--';
            modalStock.textContent = 'Tùy chọn không hợp lệ';
            modalStock.className = 'stock-info out';
            modalMainImage.src = defaultProductImage;
            return;
        }

        maxQuantity = parseInt(currentSelectedVariant.so_luong_ton);
        // --- TÍNH GIÁ SAU GIẢM ---
let price = parseFloat(currentSelectedVariant.gia);

if (currentDiscount) {
    if (currentDiscount.loai_giam_gia === "percent") {
        price = price * (1 - currentDiscount.gia_tri / 100);
    } else {
        price = Math.max(0, price - currentDiscount.gia_tri);
    }
}

modalPrice.textContent = new Intl.NumberFormat('vi-VN', { 
    style: 'currency', 
    currency: 'VND' 
}).format(price);

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

function showPopup(msg) {
    const el = document.createElement('div');
    el.textContent = msg;
    Object.assign(el.style, {
        position:'fixed',
        bottom:'30px',
        right:'30px',
        background:'#0f62fe',
        color:'#fff',
        padding:'12px 20px',
        borderRadius:'12px',
        boxShadow:'0 4px 10px rgba(0,0,0,0.2)',
        zIndex:'9999',
        transition:'opacity 0.5s'
    });
    document.body.appendChild(el);
    setTimeout(()=>el.style.opacity='0', 2000);
    setTimeout(()=>el.remove(), 2500);
}
modalBuyNowBtn.addEventListener('click', () => {
    if (!currentSelectedVariant) return;
    window.location.href = `index.php?page=checkout&action=buy_now&variant_id=${currentSelectedVariant.id}&qty=${parseInt(qtyInput.value)}`;
});

});

</script>
<?php require_once 'client/layouts/footer.php'; ?>
