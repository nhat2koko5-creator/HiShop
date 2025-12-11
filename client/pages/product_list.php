<?php
// FILE: product_list.php (ĐÃ SỬA HOÀN CHỈNH - LIÊN KẾT THƯƠNG HIỆU QUA BẢNG san_pham_thuong_hieu)
require_once 'client/layouts/header.php';

/* ------------ HÀM HỖ TRỢ: LẤY GIẢM GIÁ CHO 1 SẢN PHẨM ------------- */
/* (Code hàm discount không đổi, giữ nguyên) */

/* ---------------- PHÂN TRANG ---------------- */
$products_per_page = 11;
$current_page = isset($_GET['p']) ? (int)$_GET['p'] : 1;
if ($current_page < 1) $current_page = 1;
$offset = ($current_page - 1) * $products_per_page;

/* ---------------- LẤY DANH MỤC (nếu có) ---------------- */
$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$category_name = "Tất cả sản phẩm";

/* Base WHERE (Dùng sp.trang_thai = 1 cho sản phẩm - giữ nguyên) */
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

/* ---------------- LẤY DANH SÁCH THƯƠNG HIỆU ---------------- */
$sql_brands = "SELECT id, ten FROM thuong_hieu ORDER BY ten ASC";
$stmt_brands = $pdo->prepare($sql_brands);
$stmt_brands->execute();
$brands = $stmt_brands->fetchAll(PDO::FETCH_ASSOC);


/* ---------------- ĐẾM TỔNG SẢN PHẨM ---------------- */
// CHÚ Ý: Nếu có filter brand, cần join bảng trung gian khi đếm tổng
$where_clauses_count = $where_clauses;
$params_count = $params;
$join_count = "";

/* ----- FILTER THƯƠNG HIỆU cho Count ----- */
if (!empty($_GET['brand_id']) && (int)$_GET['brand_id'] > 0) {
    // SỬ DỤNG JOIN với bảng trung gian
    $join_count .= ' JOIN san_pham_thuong_hieu spth ON spth.san_pham_id = sp.id';
    $where_clauses_count[] = 'spth.thuong_hieu_id = ?';
    $params_count[] = (int)$_GET['brand_id'];
}
$where_sql_count = implode(' AND ', $where_clauses_count);


$sql_count = "SELECT COUNT(DISTINCT sp.id) FROM san_pham sp $join_count WHERE $where_sql_count";
$stmt_count = $pdo->prepare($sql_count);
if (!empty($params_count)) {
    $stmt_count->execute($params_count);
} else {
    $stmt_count->execute();
}
$total_products = (int)$stmt_count->fetchColumn();
$total_pages = $total_products > 0 ? (int)ceil($total_products / $products_per_page) : 1;
if ($current_page > $total_pages) {
    $current_page = $total_pages;
    $offset = ($current_page - 1) * $products_per_page;
}

/* ---------------- LẤY SẢN PHẨM (có thông số kỹ thuật và lọc) ---------------- */

// Rebuild WHERE condition with named parameter for category and filters
$where_clauses2 = ['sp.trang_thai = 1'];
$join_sql = '';
$useRam = false;
$useCpu = false;
$useBrand = false;
$useCategory = false;


/* ----- FILTER GIÁ ----- */
if (!empty($_GET['price'])) {
    switch ($_GET['price']) {
        case "1":
            $where_clauses2[] = 'sp.gia < 10000000';
            break;
        case "2":
            $where_clauses2[] = 'sp.gia BETWEEN 10000000 AND 20000000';
            break;
        case "3":
            $where_clauses2[] = 'sp.gia > 20000000';
            break;
    }
}

/* ----- FILTER RAM/CPU - Cần JOIN thong_so ----- */
if (!empty($_GET['ram'])) {
    $where_clauses2[] = 'ts.ram LIKE :ram';
    $useRam = true; // FIX: Định nghĩa biến cờ
}
if (!empty($_GET['cpu'])) {
    $where_clauses2[] = 'ts.cpu LIKE :cpu';
    $useCpu = true; // FIX: Định nghĩa biến cờ
}

/* ----- FILTER THƯƠNG HIỆU (Sử dụng bảng trung gian san_pham_thuong_hieu) ----- */
if (!empty($_GET['brand_id']) && (int)$_GET['brand_id'] > 0) {
    // Lọc qua bảng trung gian
    $where_clauses2[] = 'spth.thuong_hieu_id = :brand_id';
    $join_sql .= ' LEFT JOIN san_pham_thuong_hieu spth ON spth.san_pham_id = sp.id';
    $useBrand = true;
}

if ($category_id > 0) {
    $where_clauses2[] = 'sp.danh_muc_id = :category_id';
    $useCategory = true;
} else {
    $useCategory = false;
}

$where_sql2 = implode(' AND ', $where_clauses2);

// Query: lấy sp + thông số (dùng GROUP BY để tránh lặp)
$sql_products = "
    SELECT 
        sp.*, 
        MAX(ts.cpu) AS cpu, 
        MAX(ts.ram) AS ram
    FROM san_pham sp
    LEFT JOIN san_pham_thong_so spts ON spts.san_pham_id = sp.id
    LEFT JOIN thong_so ts ON ts.id = spts.thong_so_id
    $join_sql /* Thêm JOIN cho Brand nếu cần */
    WHERE $where_sql2
    /* THAY ĐỔI: Bỏ sp.thuong_hieu_id khỏi GROUP BY */
    GROUP BY sp.id, sp.ten, sp.hinh_anh, sp.gia, sp.danh_muc_id, sp.mo_ta, sp.trang_thai 
    ORDER BY sp.id DESC
    LIMIT :limit OFFSET :offset
";

// Prepare statement and check
$stmt_products = $pdo->prepare($sql_products);
if (!$stmt_products) {
    $err = $pdo->errorInfo();
    die("Prepare products failed: " . htmlspecialchars($err[2] ?? 'Unknown error') . "<br>SQL: " . htmlspecialchars($sql_products));
}

// Bind dynamic filters safely
if ($useCategory) {
    $stmt_products->bindValue(':category_id', (int)$category_id, PDO::PARAM_INT);
}
if ($useRam) {
    $stmt_products->bindValue(':ram', '%' . trim($_GET['ram']) . '%', PDO::PARAM_STR);
}
if ($useCpu) {
    $stmt_products->bindValue(':cpu', '%' . trim($_GET['cpu']) . '%', PDO::PARAM_STR);
}
/* ----- BIND THƯƠNG HIỆU ----- */
if ($useBrand) {
    $stmt_products->bindValue(':brand_id', (int)$_GET['brand_id'], PDO::PARAM_INT);
}

// Bind limit/offset as integers (PDO::PARAM_INT)
$stmt_products->bindValue(':limit', (int)$products_per_page, PDO::PARAM_INT);
$stmt_products->bindValue(':offset', (int)$offset, PDO::PARAM_INT);

// Execute and fetch with try/catch 
try {
    $stmt_products->execute();
    $products = $stmt_products->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $ex) {
    die("Execute products failed: " . htmlspecialchars($ex->getMessage()));
}

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
<div class="main-content-layout">

    <div class="sidebar-filters">
        <form method="GET" class="filter-bar">
            <input type="hidden" name="page" value="product_list">
            <?php if ($category_id > 0): ?>
                <input type="hidden" name="category_id" value="<?= $category_id ?>">
            <?php endif; ?>

            <div class="filter-box">
                <h4 class="filter-title">Thương hiệu</h4>
                <?php $current_brand_id = $_GET['brand_id'] ?? 0; ?>
                
                <div class="filter-option">
                    <input type="radio" 
                           id="brand_all" 
                           name="brand_id" 
                           value="0" 
                           onchange="this.form.submit()" 
                           <?= (int)$current_brand_id === 0 ? 'checked' : '' ?>>
                    <label for="brand_all">Tất cả</label>
                </div>

                <?php foreach ($brands as $brand): ?>
                    <div class="filter-option">
                        <input type="radio" 
                               id="brand_<?= $brand['id'] ?>" 
                               name="brand_id" 
                               value="<?= $brand['id'] ?>" 
                               onchange="this.form.submit()" 
                               <?= (int)$current_brand_id === (int)$brand['id'] ? 'checked' : '' ?>>
                        <label for="brand_<?= $brand['id'] ?>"><?= htmlspecialchars($brand['ten']) ?></label>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="filter-box">
                <h4 class="filter-title">Lọc theo Giá</h4>
                <?php $current_price = $_GET['price'] ?? ''; ?>
                <div class="filter-option">
                    <input type="radio" id="price_all" name="price" value="" onchange="this.form.submit()" <?= $current_price==''?'checked':'' ?>>
                    <label for="price_all">Tất cả</label>
                </div>
                <div class="filter-option">
                    <input type="radio" id="price_1" name="price" value="1" onchange="this.form.submit()" <?= $current_price=='1'?'checked':'' ?>>
                    <label for="price_1">Dưới 10 triệu</label>
                </div>
                <div class="filter-option">
                    <input type="radio" id="price_2" name="price" value="2" onchange="this.form.submit()" <?= $current_price=='2'?'checked':'' ?>>
                    <label for="price_2">10 – 20 triệu</label>
                </div>
                <div class="filter-option">
                    <input type="radio" id="price_3" name="price" value="3" onchange="this.form.submit()" <?= $current_price=='3'?'checked':'' ?>>
                    <label for="price_3">Trên 20 triệu</label>
                </div>
            </div>
            
            <div class="filter-box">
                <h4 class="filter-title">Lọc theo RAM</h4>
                <?php $current_ram = $_GET['ram'] ?? ''; ?>
                <div class="filter-option">
                    <input type="radio" id="ram_all" name="ram" value="" onchange="this.form.submit()" <?= $current_ram==''?'checked':'' ?>>
                    <label for="ram_all">Tất cả</label>
                </div>
                <div class="filter-option">
                    <input type="radio" id="ram_8" name="ram" value="8GB" onchange="this.form.submit()" <?= $current_ram=='8GB'?'checked':'' ?>>
                    <label for="ram_8">8GB</label>
                </div>
                <div class="filter-option">
                    <input type="radio" id="ram_16" name="ram" value="16GB" onchange="this.form.submit()" <?= $current_ram=='16GB'?'checked':'' ?>>
                    <label for="ram_16">16GB</label>
                </div>
                <div class="filter-option">
                    <input type="radio" id="ram_32" name="ram" value="32GB" onchange="this.form.submit()" <?= $current_ram=='32GB'?'checked':'' ?>>
                    <label for="ram_32">32GB</label>
                </div>
            </div>

            <div class="filter-box">
                <h4 class="filter-title">Lọc theo CPU</h4>
                <?php $current_cpu = $_GET['cpu'] ?? ''; ?>
                <div class="filter-option">
                    <input type="radio" id="cpu_all" name="cpu" value="" onchange="this.form.submit()" <?= $current_cpu==''?'checked':'' ?>>
                    <label for="cpu_all">Tất cả</label>
                </div>
                <div class="filter-option">
                    <input type="radio" id="cpu_i5" name="cpu" value="i5" onchange="this.form.submit()" <?= $current_cpu=='i5'?'checked':'' ?>>
                    <label for="cpu_i5">Intel Core i5</label>
                </div>
                <div class="filter-option">
                    <input type="radio" id="cpu_i7" name="cpu" value="i7" onchange="this.form.submit()" <?= $current_cpu=='i7'?'checked':'' ?>>
                    <label for="cpu_i7">Intel Core i7</label>
                </div>
                <div class="filter-option">
                    <input type="radio" id="cpu_r5" name="cpu" value="Ryzen 5" onchange="this.form.submit()" <?= $current_cpu=='Ryzen 5'?'checked':'' ?>>
                    <label for="cpu_r5">Ryzen 5</label>
                </div>
                <div class="filter-option">
                    <input type="radio" id="cpu_r7" name="cpu" value="Ryzen 7" onchange="this.form.submit()" <?= $current_cpu=='Ryzen 7'?'checked':'' ?>>
                    <label for="cpu_r7">Ryzen 7</label>
                </div>
            </div>
        </form>
    </div>
    
    <div class="product-list-content">
        <?php if (!empty($products)): ?>
            <div class="product-grid">
    <?php foreach ($products as $p): 
        // 1. Xử lý ảnh
        $img_path = (!empty($p['hinh_anh']) && file_exists($img_folder . '/' . $p['hinh_anh'])) 
                    ? $img_folder . '/' . $p['hinh_anh'] 
                    : $default_img;

        // 2. Lấy giá hiển thị 
        $display_price = isset($p['gia_from']) && $p['gia_from'] > 0 ? (int)$p['gia_from'] : (int)$p['gia'];

        // 3. Tính toán giảm giá
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
                $cpu_display = !empty($p['cpu']) ? "" . htmlspecialchars($p['cpu']) : '';
                $ram_display = !empty($p['ram']) ? "" . htmlspecialchars($p['ram']) : '';
                
                $spec_line = '';
                if ($cpu_display && $ram_display) {
                    $spec_line = $cpu_display . ' |' . $ram_display; 
                } elseif ($cpu_display) {
                    $spec_line = $cpu_display;
                } elseif ($ram_display) {
                    $spec_line = $ram_display;
                }
                ?>

                <?php if ($spec_line): ?>
                <div class="product-specs" style="font-size: 13px; color: #666; margin-bottom: 10px; font-weight: 500; text-align:center;">
                    <?= $spec_line ?>
                </div>
                <?php endif; ?>
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
                        
                        <a href="index.php?page=cart&action=add&id=<?= $p['id'] ?>" class="btn-view" style="width: 100%;">
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

</div> <?php if ($total_pages > 1): ?>
        <nav class="pagination-nav">
            <ul class="pagination">
                <?php 
                // Xây dựng chuỗi query string hiện tại (giữ lại các bộ lọc khác)
                $query_params = $_GET;
                unset($query_params['p']); // Loại bỏ tham số trang hiện tại

                function build_pagination_url($page_num, $params) {
                    $params['p'] = $page_num;
                    return 'index.php?page=product_list&' . http_build_query($params);
                }
                ?>

                <?php if ($current_page > 1): ?>
                    <li><a href="<?= build_pagination_url($current_page - 1, $query_params) ?>">«</a></li>
                <?php endif; ?>

                <?php
                // Show a sliding window of pages
                $start = max(1, $current_page - 3);
                $end = min($total_pages, $current_page + 3);
                if ($start > 1) {
                    echo '<li><a href="'.build_pagination_url(1, $query_params).'">1</a></li>';
                    if ($start > 2) echo '<li><span>…</span></li>';
                }
                for ($i = $start; $i <= $end; $i++) {
                    echo '<li><a class="'.($i==$current_page?'active':'').'" href="'.build_pagination_url($i, $query_params).'">'.$i.'</a></li>';
                }
                if ($end < $total_pages) {
                    if ($end < $total_pages - 1) echo '<li><span>…</span></li>';
                    echo '<li><a href="'.build_pagination_url($total_pages, $query_params).'">'.$total_pages.'</a></li>';
                }
                ?>

                <?php if ($current_page < $total_pages): ?>
                    <li><a href="<?= build_pagination_url($current_page + 1, $query_params) ?>">»</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    <?php endif; ?>
    
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
    let currentDiscount = null; // Thêm biến currentDiscount

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