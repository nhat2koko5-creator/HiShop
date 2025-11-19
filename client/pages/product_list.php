<?php
// FILE: product_list.php (ĐÃ SỬA - HIỂN THỊ GIẢM GIÁ + GIỮ NGUYÊN PHÂN TRANG & MODAL)
require_once 'client/layouts/header.php';

/* ------------ HÀM HỖ TRỢ: LẤY GIẢM GIÁ CHO 1 SẢN PHẨM ------------- */
/* Sử dụng đúng cấu trúc DB trong hishop_db.sql:
   - san_pham_giam_gia(giam_gia_id, san_pham_id)
   - giam_gia(id, loai_giam_gia enum('percent','amount'), gia_tri, ngay_bat_dau, ngay_ket_thuc)
*/
function get_discount_for_product($pdo, $product_id) {
    $sql = "
        SELECT g.*
        FROM giam_gia g
        JOIN san_pham_giam_gia spg ON spg.giam_gia_id = g.id
        WHERE spg.san_pham_id = :pid
          AND (g.ngay_bat_dau IS NULL OR g.ngay_bat_dau <= NOW())
          AND (g.ngay_ket_thuc IS NULL OR g.ngay_ket_thuc >= NOW())
        ORDER BY g.id DESC
        LIMIT 1
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':pid' => $product_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

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
<div class="container">
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

    <?php if (!empty($products)): ?>
        <div class="product-grid">
            <?php foreach ($products as $p):
                $img_path = (!empty($p['hinh_anh']) && file_exists($img_folder . '/' . $p['hinh_anh'])) ? $img_folder . '/' . $p['hinh_anh'] : $default_img;
                // base display price: prefer gia_from (from variants), fallback to sp.gia
                $display_price = isset($p['gia_from']) && $p['gia_from'] > 0 ? (int)$p['gia_from'] : (isset($p['gia']) ? (int)$p['gia'] : 0);

                // get discount (if any)
                $discount = get_discount_for_product($pdo, $p['id']);
                $discount_percent = 0;
                $price_after = $display_price;
                if ($discount && isset($discount['loai_giam_gia']) && isset($discount['gia_tri'])) {
                    $type = strtolower($discount['loai_giam_gia']);
                    $val = (float)$discount['gia_tri'];
                    if ($type === 'percent') {
                        $discount_percent = (int)$val;
                        $price_after = (int) round($display_price * (100 - $discount_percent) / 100);
                    } else { // amount
                        $amount = (int)$val;
                        $price_after = max(0, $display_price - $amount);
                        if ($display_price > 0) $discount_percent = (int) round(($amount / $display_price) * 100);
                    }
                }
            ?>
                <div class="card">
                    <?php if ($discount_percent > 0): ?>
                        <div class="discount-badge">-<?= $discount_percent ?>%</div>
                    <?php endif; ?>

                    <div class="image-wrapper">
                        <img src="<?= htmlspecialchars($img_path) ?>" alt="<?= htmlspecialchars($p['ten']) ?>">
                    </div>

                    <div class="name"><?= htmlspecialchars($p['ten']) ?></div>

                    <div class="price">
                        <?php if ($discount_percent > 0 && $price_after < $display_price): ?>
                            <span class="old"><?= format_price($display_price) ?></span>
                            <span class="new"><?= format_price($price_after) ?></span>
                        <?php else: ?>
                            <span class="new"><?= format_price($display_price) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="btn-group">
                        <a href="index.php?page=product_detail&id=<?= $p['id'] ?>" class="btn btn-detail">🔍 Xem chi tiết</a>

                        <?php if (!empty($p['variants'])): ?>
                            <a href="javascript:void(0);" 
                               class="btn btn-buy btn-quick-buy"
                               data-product-id="<?= $p['id'] ?>"
                               data-product-name="<?= htmlspecialchars($p['ten']) ?>"
                               data-product-image="<?= htmlspecialchars($p['hinh_anh']) ?>"
                               data-variants='<?= htmlspecialchars(json_encode($p['variants']), ENT_QUOTES) ?>'>
                                🛍️ Mua ngay
                            </a>
                        <?php else: ?>
                            <a class="btn btn-buy" disabled>🛍️ Tạm hết hàng</a>
                        <?php endif; ?>
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

<!-- Keep your modal code (unchanged) -->
<!-- ... (modal HTML & JS) ... -->

<?php require_once 'client/layouts/footer.php'; ?>
