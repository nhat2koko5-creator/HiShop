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

if (!empty($params)) {
    // We originally appended '?', so let's rebuild WHERE with a named category param
    // (Assume only one category filter as in this page)
    $where_clauses2 = ['sp.trang_thai = 1'];
    if ($category_id > 0) {
        $where_clauses2[] = 'sp.danh_muc_id = :category_id';
    }
    $where_sql2 = implode(' AND ', $where_clauses2);
    $sql_products = "SELECT sp.* FROM san_pham sp WHERE $where_sql2 ORDER BY sp.id DESC LIMIT :limit OFFSET :offset";
    $stmt_products = $pdo->prepare($sql_products);
    $stmt_products->bindValue(':category_id', $category_id, PDO::PARAM_INT);
} else {
    $sql_products = "SELECT sp.* FROM san_pham sp WHERE sp.trang_thai = 1 ORDER BY sp.id DESC LIMIT :limit OFFSET :offset";
    $stmt_products = $pdo->prepare($sql_products);
}

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
<link rel="stylesheet" href="assets/css/product_list.css">
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
                $img_path = (!empty($p['hinh_anh']) && file_exists($img_folder . '/' . $p['hinh_anh'])) ? $img_folder . '/' . $p['hinh_anh'] : $default_img;
                // base display price: prefer gia_from (from variants), fallback to sp.gia
               // --- Chuẩn hóa: lấy giá gốc từ SQL (san_pham.gia) — bắt buộc dùng giá trong bảng san_pham
$original_price = isset($p['gia']) ? (int)$p['gia'] : 0;

// Nếu bạn muốn hiển thị giá theo biến thể (nếu có) thay vì sp.gia, bật dòng bên dưới:
// $display_price = (isset($p['gia_from']) && (int)$p['gia_from'] > 0) ? (int)$p['gia_from'] : $original_price;

// Nếu bạn muốn HIỆN THỰC 100% GIÁ TỪ SQL thì dùng:
$display_price = $original_price;

// Nếu muốn debug nhanh (xóa/ comment khi không cần):
// echo 'DBG original_price='.$original_price.' display_price='.$display_price;

// --- Lấy giảm giá cho sản phẩm từ bảng san_pham_giam_gia (nếu có)
$discount_percent = 0;
$price_after = $display_price;

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

if ($discount) {
    if ($discount['loai_giam_gia'] === 'percent') {
        // giảm theo %
        $discount_percent = (int)$discount['gia_tri'];
        // tính % dựa trên giá gốc từ san_pham.gia (original_price)
        $price_after = (int) round($original_price * (100 - $discount_percent) / 100);
    } else {
        // giảm theo số tiền
        $amount = (int)$discount['gia_tri'];
        // áp trực tiếp tiền giảm trên display (còn có thể thay bằng original_price nếu bạn muốn)
        // Mình dùng original_price để giá sau giảm đúng với DB giá ban đầu
        $price_after = max(0, $original_price - $amount);
        // quy đổi ra %
        $discount_percent = $original_price > 0 ? (int) round(($amount / $original_price) * 100) : 0;
    }
}

                // get discount (if any)
// --- Lấy thông tin giảm giá ---
// === Lấy giảm giá cho sản phẩm từ bảng san_pham_giam_gia ===
$discount_percent = 0;
$price_after = $display_price;

// truy vấn discount theo sp.id
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

// Nếu có giảm giá
if ($discount) {
    if ($discount['loai_giam_gia'] === 'percent') {
        // giảm theo %
        $discount_percent = (int)$discount['gia_tri'];
        $price_after = (int) round($display_price * (100 - $discount_percent) / 100);
    } else {
        // giảm theo số tiền
        $amount = (int)$discount['gia_tri'];
        $price_after = max(0, $display_price - $amount);
        // quy đổi ra %
        $discount_percent = $display_price > 0 ? round(($amount / $display_price) * 100) : 0;
    }
}
            ?>
               <div class="product-card">
            
            <?php if ($discount_percent > 0): ?>
                <div class="product-sale-tag">-<?= $discount_percent ?>%</div>
            <?php endif; ?>

            <div class="product-image">
                <img src="<?= htmlspecialchars($img_path) ?>" alt="<?= htmlspecialchars($p['ten']) ?>">
            </div>

            <div class="card-content">
                <div class="card-title"><?= htmlspecialchars($p['ten']) ?></div>

                <div class="card-price">
                    <?php if ($discount_percent > 0 && $price_after < $display_price): ?>
                        <span class="card-price-old"><?= format_price($display_price) ?></span>
                        <span class="card-price-new"><?= format_price($price_after) ?></span>
                    <?php else: ?>
                        <span class="card-price-new"><?= format_price($display_price) ?></span>
                    <?php endif; ?>
                </div>

                <div class="btn-group-vertical">
                    <a href="index.php?page=product_detail&id=<?= $p['id'] ?>" class="btn-view">🔍 Xem chi tiết</a>

                    <?php if (!empty($p['variants'])): ?>
                        <a href="javascript:void(0);" 
                           class="btn-cart btn-quick-buy"
                           data-product-id="<?= $p['id'] ?>"
                           data-product-name="<?= htmlspecialchars($p['ten']) ?>"
                           data-product-image="<?= htmlspecialchars($p['hinh_anh']) ?>"
                           data-variants='<?= htmlspecialchars(json_encode($p['variants']), ENT_QUOTES) ?>'>
                            🛒 Mua ngay
                        </a>
                    <?php else: ?>
                        <a class="btn-cart" style="background:#ccc; cursor:not-allowed;">🛍️ Tạm hết hàng</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
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

            <div class="option-group" id="modal-color-group">
                <h4 style="margin-bottom: 8px;">Màu sắc</h4>
                <div class="option-box" id="modal-color-options"></div>
            </div>
            <div class="option-group" id="modal-ssd-group" style="margin-top: 15px;">
                <h4 style="margin-bottom: 8px;">SSD</h4>
                <div class="option-box" id="modal-ssd-options"></div>
            </div>
        </div>
        <div class="variant-modal-footer">
            <button class="btn btn-outline" id="modal-cancel-btn">Hủy</button>
            <button class="btn btn-primary" id="modal-action-btn" disabled>Mua ngay</button>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // --- Biến DOM ---
    const modal = document.getElementById('quick-add-modal');
    const modalProductName = document.getElementById('modal-product-name');
    const modalPrice = document.getElementById('modal-product-price');
    const modalStock = document.getElementById('modal-stock-status');
    const modalColorBox = document.getElementById('modal-color-options');
    const modalSsdBox = document.getElementById('modal-ssd-options');
    const modalActionBtn = document.getElementById('modal-action-btn'); 
    const modalMainImage = document.getElementById('modal-product-main-image');
    
    // --- Biến Trạng Thái ---
    let currentVariants = []; 
    let currentProductId = null;
    let selectedColor = null;
    let selectedSSD = null;
    let currentSelectedVariant = null; 
    let defaultProductImage = 'assets/img/no-image.png'; 

    // === 1. MỞ MODAL ===
    function openQuickModal(e) {
        e.preventDefault();
        const btn = e.currentTarget;

        currentProductId = btn.dataset.productId;
        modalProductName.textContent = btn.dataset.productName;
        
        const productImage = btn.dataset.productImage;
        defaultProductImage = productImage ? `assets/img/products/${productImage}` : 'assets/img/no-image.png';
        modalMainImage.src = defaultProductImage;
        
        try {
            // Parse dữ liệu biến thể từ nút bấm
            currentVariants = JSON.parse(btn.dataset.variants);
        } catch(e) {
            alert('Lỗi dữ liệu biến thể. Vui lòng thử lại.');
            return;
        }

        // Lấy danh sách màu và SSD duy nhất
        const colors = [...new Set(currentVariants.map(v => v.mau_sac))];
        const ssds = [...new Set(currentVariants.map(v => v.dung_luong_ssd))];

        // Vẽ nút chọn Màu
        modalColorBox.innerHTML = ''; 
        colors.forEach(color => {
            const opt = document.createElement('div');
            opt.className = 'option';
            opt.dataset.group = 'color';
            opt.dataset.value = color;
            opt.textContent = color;
            modalColorBox.appendChild(opt);
        });

        // Vẽ nút chọn SSD
        modalSsdBox.innerHTML = ''; 
        ssds.forEach(ssd => {
            const opt = document.createElement('div');
            opt.className = 'option';
            opt.dataset.group = 'ssd';
            opt.dataset.value = ssd;
            opt.textContent = ssd;
            modalSsdBox.appendChild(opt);
        });
        
        // Reset trạng thái
        selectedColor = null;
        selectedSSD = null;
        currentSelectedVariant = null;
        modalPrice.textContent = '--';
        modalStock.textContent = 'Vui lòng chọn tùy chọn';
        modalStock.className = 'stock-info';
        modalActionBtn.disabled = true; 
        
        // Hiển thị modal
        modal.style.display = 'flex';
    }

    // === 2. ĐÓNG MODAL ===
    function closeQuickModal() {
        modal.style.display = 'none';
    }

    // === 3. KIỂM TRA LỰA CHỌN ===
    function checkModalSelections() {
        modalActionBtn.disabled = true; 
        currentSelectedVariant = null;

        // Chỉ kiểm tra khi đã chọn cả 2
        if (!selectedColor || !selectedSSD) {
            return;
        }

        // Tìm biến thể khớp với lựa chọn
        const variant = currentVariants.find(v => (v.mau_sac === selectedColor && v.dung_luong_ssd === selectedSSD));

        if (variant) {
            // Tìm thấy biến thể
            modalPrice.textContent = new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(variant.gia);
            
            if (variant.so_luong_ton > 0) {
                modalStock.textContent = "Còn " + variant.so_luong_ton + " sản phẩm";
                modalStock.className = 'stock-info';
                modalActionBtn.disabled = false; // Bật nút Mua ngay
                currentSelectedVariant = variant; 
            } else {
                modalStock.textContent = "Hết hàng";
                modalStock.className = 'stock-info out';
            }
            
            // Đổi ảnh nếu biến thể có ảnh riêng
            if (variant.hinh_anh) {
                modalMainImage.src = `assets/img/products/${variant.hinh_anh}`;
            } else {
                modalMainImage.src = defaultProductImage; 
            }
            
        } else {
            // Không tìm thấy biến thể
            modalPrice.textContent = '--';
            modalStock.textContent = "Tùy chọn không có sẵn";
            modalStock.className = 'stock-info out';
            modalMainImage.src = defaultProductImage; 
        }
    }

    // === GÁN SỰ KIỆN ===

    // Sự kiện click nút "Mua ngay" ở danh sách sản phẩm
    document.querySelectorAll('.btn-quick-buy').forEach(button => {
        button.addEventListener('click', openQuickModal);
    });

    // Sự kiện đóng modal
    document.getElementById('modal-close-btn').addEventListener('click', closeQuickModal);
    document.getElementById('modal-cancel-btn').addEventListener('click', closeQuickModal);
    modal.addEventListener('click', e => {
        if (e.target === modal) closeQuickModal();
    });

    // Sự kiện chọn Option (Màu / SSD) - Dùng Event Delegation
    modal.addEventListener('click', function(e) {
        if (!e.target.classList.contains('option') || e.target.classList.contains('disabled')) {
            return;
        }
        const group = e.target.dataset.group;
        const value = e.target.dataset.value;

        // Xử lý active class
        if (group === 'color') {
            selectedColor = value;
            modalColorBox.querySelectorAll('.option').forEach(o => o.classList.remove('active'));
        } else if (group === 'ssd') {
            selectedSSD = value;
            modalSsdBox.querySelectorAll('.option').forEach(o => o.classList.remove('active'));
        }
        e.target.classList.add('active');
        
        // Kiểm tra kết quả
        checkModalSelections();
    });

    // === XỬ LÝ NÚT MUA NGAY TRONG MODAL ===
    modalActionBtn.addEventListener('click', function() {
        if (!currentSelectedVariant) return;

        // Lấy ID biến thể
        const variantId = currentSelectedVariant.id;
        
        // Chuyển hướng thẳng đến trang thanh toán với thông tin biến thể
        window.location.href = `index.php?page=checkout&action=buy_now&variant_id=${variantId}`;
    });
});
</script>
<?php require_once 'client/layouts/footer.php'; ?>
