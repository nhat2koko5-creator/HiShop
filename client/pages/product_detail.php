<?php
// FILE: client/pages/product_detail.php
require_once 'client/layouts/header.php';

// --- LOGIC PHP CƠ BẢN (GIỮ NGUYÊN) ---
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($product_id <= 0) { echo "<div class='container' style='padding:50px 0; text-align:center;'><h3>Sản phẩm không tồn tại.</h3></div>"; require_once 'client/layouts/footer.php'; exit; }

// [THAY ĐỔI 1.1] TRUY VẤN SẢN PHẨM CHÍNH (KHÔNG JOIN THƯƠNG HIỆU 1:N)
$stmt = $pdo->prepare("
    SELECT
        id, ten, hinh_anh, trang_thai, danh_muc_id, mo_ta, mo_ta_chi_tiet
    FROM
        san_pham
    WHERE
        id = ?
");
$stmt->execute([$product_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$product) { echo "<div class='container' style='padding:50px 0; text-align:center;'><h3>Không tìm thấy sản phẩm.</h3></div>"; require_once 'client/layouts/footer.php'; exit; }

// [THAY ĐỔI 1.2] TRUY VẤN TẤT CẢ THƯƠNG HIỆU CỦA SẢN PHẨM HIỆN TẠI (M:N)
$stmt_brands = $pdo->prepare("
    SELECT 
        th.id AS thuong_hieu_id, th.ten AS ten_thuong_hieu
    FROM 
        san_pham_thuong_hieu spth
    JOIN 
        thuong_hieu th ON spth.thuong_hieu_id = th.id
    WHERE 
        spth.san_pham_id = ?
");
$stmt_brands->execute([$product_id]);
$product_brands = $stmt_brands->fetchAll(PDO::FETCH_ASSOC);

// Thiết lập các biến cho logic liên quan và tiêu đề
$current_brand_id = !empty($product_brands) ? $product_brands[0]['thuong_hieu_id'] : 0;
$brand_name = !empty($product_brands) ? $product_brands[0]['ten_thuong_hieu'] : 'Khác';
$product_brand_ids = array_column($product_brands, 'thuong_hieu_id'); // Mảng chứa tất cả ID thương hiệu liên kết

// --- LẤY BIẾN THỂ SẢN PHẨM CHÍNH (FIX: LẤY SỐ LƯỢNG TỪ KHO HOẠT ĐỘNG) ---
$stmt_variants = $pdo->prepare("
    SELECT 
        bt.id,
        bt.san_pham_id,
        bt.gia,
        COALESCE(SUM(CASE WHEN k.trang_thai = 1 THEN ckt.so_luong_ton ELSE 0 END), 0) as so_luong_ton,
        bt.mau_sac,
        bt.dung_luong_ssd,
        bt.hinh_anh
    FROM bien_the_san_pham bt
    LEFT JOIN chi_tiet_kho_hang ckt ON bt.id = ckt.bien_the_id
    LEFT JOIN kho_hang k ON ckt.kho_hang_id = k.id
    WHERE bt.san_pham_id = ?
    GROUP BY bt.id, bt.san_pham_id, bt.gia, bt.mau_sac, bt.dung_luong_ssd, bt.hinh_anh
    ORDER BY bt.gia ASC
");
$stmt_variants->execute([$product_id]);
$variants = $stmt_variants->fetchAll(PDO::FETCH_ASSOC);

$variants_js_data = [];
$base_price = 0;
$today = date('Y-m-d H:i:s');

if (!empty($variants)) {
    foreach ($variants as &$variant) {
        // [MỚI] LƯU GIÁ GỐC
        $variant['gia_goc'] = $variant['gia']; // Lưu giá gốc trước khi tính giảm giá
        $variant['gia_hien_tai'] = $variant['gia']; 
        $stmt_discount = $pdo->prepare("SELECT gg.loai_giam_gia, gg.gia_tri
    FROM san_pham_giam_gia spgg 
    JOIN giam_gia gg ON spgg.giam_gia_id = gg.id 
    WHERE spgg.san_pham_id = ? 
    AND gg.ngay_bat_dau <= ? 
    AND (gg.ngay_ket_thuc IS NULL OR gg.ngay_ket_thuc >= ?) 
    ORDER BY gg.id DESC LIMIT 1");

    $stmt_discount->execute([$product_id, $today, $today]);

            $stmt_discount->execute([$product_id, $today, $today]);
           $discount = $stmt_discount->fetch(PDO::FETCH_ASSOC);
        if ($discount) {
            if ($discount['loai_giam_gia'] === 'percent') {
                $variant['gia_hien_tai'] = $variant['gia'] * (1 - $discount['gia_tri']/100);
            } elseif ($discount['loai_giam_gia'] === 'amount') {
                $variant['gia_hien_tai'] = max(0, $variant['gia'] - $discount['gia_tri']);
            }
        }
        
        $key = $variant['mau_sac'] . '|' . $variant['dung_luong_ssd'];
        $variants_js_data[$key] = [
            'id' => $variant['id'],
            'gia' => $variant['gia_hien_tai'], 
            // [MỚI] THÊM GIÁ GỐC VÀO JS DATA
            'gia_goc' => $variant['gia_goc'], 
            'co_giam_gia' => ($variant['gia_hien_tai'] < $variant['gia_goc']), // Cờ kiểm tra
            'so_luong_ton' => $variant['so_luong_ton'],
            'hinh_anh' => $variant['hinh_anh']
        ];
    }
    unset($variant);
    $base_price = min(array_column($variants, 'gia_hien_tai'));
} else {
        $base_price = $product['gia'] ?? 0;
    }
// --- CHÈN SAU KHI LẤY ĐƯỢC $product ---
$user_id = $_SESSION['user_id'] ?? 0;
$is_liked = false;

// Nếu đã đăng nhập, kiểm tra xem ID sản phẩm này có trong danh sách đã like không
if ($user_id > 0) {
    // Hàm này bạn đã thêm vào functions.php ở bước trước
    $liked_ids = getUserLikedProductIds($pdo, $user_id);
    $is_liked = in_array($product_id, $liked_ids);
}
    // --- LẤY THÔNG TIN DANH MỤC & THÔNG SỐ (GIỮ NGUYÊN) ---
    $category = 'Không xác định';
    if (!empty($product['danh_muc_id'])) {
        $stmt_cat = $pdo->prepare("SELECT ten FROM danh_muc WHERE id = ?");
        $stmt_cat->execute([$product['danh_muc_id']]);
        $cat_name = $stmt_cat->fetchColumn();
        if ($cat_name) $category = $cat_name;
    }

    $stmt_specs = $pdo->prepare("SELECT ts.man_hinh, ts.o_cung, ts.cpu, ts.gpu, ts.ram FROM san_pham_thong_so spts JOIN thong_so ts ON spts.thong_so_id = ts.id WHERE spts.san_pham_id = ?");
    $stmt_specs->execute([$product_id]);
    $specs = $stmt_specs->fetch(PDO::FETCH_ASSOC);

// [THAY ĐỔI 2] LOGIC LẤY SẢN PHẨM LIÊN QUAN (M:N - CÙNG THƯƠNG HIỆU)
$related_products = [];
// $current_brand_id và $product_brand_ids đã được xác định ở trên

if (!empty($product_brand_ids)) {
    // Tạo placeholders cho IN clause
    $placeholders = implode(',', array_fill(0, count($product_brand_ids), '?'));
    
    $sql_related = "
        SELECT 
            sp.id, sp.ten, sp.gia, sp.hinh_anh, ts.cpu, ts.ram 
        FROM 
            san_pham sp 
        JOIN 
            san_pham_thuong_hieu spth ON sp.id = spth.san_pham_id -- Join qua bảng trung gian
        LEFT JOIN 
            san_pham_thong_so spts ON sp.id = spts.san_pham_id 
        LEFT JOIN 
            thong_so ts ON spts.thong_so_id = ts.id 
        WHERE 
            spth.thuong_hieu_id IN ({$placeholders}) -- Kiểm tra xem sản phẩm có bất kỳ TH ID nào trong danh sách không
            AND sp.id != ? 
            AND sp.trang_thai = 1
        GROUP BY sp.id -- Đảm bảo mỗi sản phẩm chỉ xuất hiện 1 lần nếu nó có nhiều TH trùng
        ORDER BY RAND() 
        LIMIT 4
    ";

    // Chuẩn bị mảng tham số: ID Thương hiệu + ID Sản phẩm hiện tại
    $params = array_merge($product_brand_ids, [$product_id]);
    
    $stmt_related = $pdo->prepare($sql_related);
    $stmt_related->execute($params);
    $related_products = $stmt_related->fetchAll(PDO::FETCH_ASSOC);
}

    // [MỚI] LẤY BIẾN THỂ CHO SẢN PHẨM LIÊN QUAN (Để phục vụ Modal Quick Add) - FIX LẤY SỐ LƯỢNG TỪ KHO HOẠT ĐỘNG
    if (!empty($related_products)) {
        $r_ids = array_column($related_products, 'id');
        $placeholders = implode(',', array_fill(0, count($r_ids), '?'));
        
        $sql_r_variants = "
            SELECT 
                bt.id, 
                bt.san_pham_id, 
                bt.gia, 
                COALESCE(SUM(CASE WHEN k.trang_thai = 1 THEN ckt.so_luong_ton ELSE 0 END), 0) as so_luong_ton,
                bt.mau_sac, 
                bt.dung_luong_ssd, 
                bt.hinh_anh 
            FROM bien_the_san_pham bt
            LEFT JOIN chi_tiet_kho_hang ckt ON bt.id = ckt.bien_the_id
            LEFT JOIN kho_hang k ON ckt.kho_hang_id = k.id
            WHERE bt.san_pham_id IN ($placeholders)
            GROUP BY bt.id, bt.san_pham_id, bt.gia, bt.mau_sac, bt.dung_luong_ssd, bt.hinh_anh
        ";
        $stmt_r_variants = $pdo->prepare($sql_r_variants);
        $stmt_r_variants->execute($r_ids);
        $all_r_variants = $stmt_r_variants->fetchAll(PDO::FETCH_ASSOC);

        $r_variants_map = [];
        foreach ($all_r_variants as $v) {
            $r_variants_map[$v['san_pham_id']][] = $v;
        }

        foreach ($related_products as $key => $r_prod) {
            if (isset($r_variants_map[$r_prod['id']])) {
                $related_products[$key]['variants'] = $r_variants_map[$r_prod['id']];
            } else {
                $related_products[$key]['variants'] = [];
            }
        }
    }

    function price_format($n) { return number_format($n, 0, ',', '.') . '₫'; }
    $img_folder = 'assets/img/products';
    $default_img = 'assets/img/no-image.png';
    $img_path = (!empty($product['hinh_anh'])) ? $img_folder . '/' . $product['hinh_anh'] : $default_img;
    if (!file_exists($img_path)) $img_path = $default_img;
    ?>

    <link rel="stylesheet" href="assets/css/client/product_detail.css">

    <div class="container">
        <div class="static-page-header">
            <div class="breadcrumb">
                <a href="index.php?page=home">Trang chủ</a> ›
                <a href="index.php?page=product_list&cat=<?= htmlspecialchars($product['danh_muc_id'] ?? '') ?>"><?= htmlspecialchars($category) ?></a> ›
                <span class="current"><?= htmlspecialchars($product['ten']) ?></span>
            </div>
        </div>
        <div class="grid-container">
        <div class="pd-grid-layout">
            <div class="pd-image-box">
                <div class="image-container">
                    <img src="<?= htmlspecialchars($img_path) ?>" alt="<?= htmlspecialchars($product['ten']) ?>" id="main-product-image">
                </div>
            </div>

            <div class="pd-info-box">
                <h1 class="pd-title"><?= htmlspecialchars($product['ten']) ?></h1>
                
                <div class="pd-meta">
                    <span>Mã SP: #<?= $product['id'] ?></span> | 
                    <span class="stock-label" id="stock-text">Vui lòng chọn phiên bản</span>
                </div>

<?php
if (!empty($variants)) {
    $current_prices = array_column($variants, 'gia_hien_tai');
    $min_price = min($current_prices);
    $max_price = max($current_prices);

    if ($min_price == $max_price) {
        $price_display = price_format($min_price);
    } else {
        $price_display = price_format($min_price) . ' - ' . price_format($max_price);
    }
} else {
    $price_display = ($base_price > 0) ? price_format($base_price) : 'Liên hệ';
}
?>
<div class="pd-price" id="product-price">
    <?= $price_display ?>
</div>
                <?php if (!empty($variants)): ?>
                    <div class="pd-options">
                        <h4>Chọn phiên bản:</h4>
                        <div class="pd-option-list" id="variantOptions">
                            <?php foreach ($variants as $variant): 
                                $variant_key = htmlspecialchars($variant['mau_sac'] . '|' . $variant['dung_luong_ssd']);
                                $full_price_text = price_format($variant['gia_hien_tai']);
                                $is_discounted = $variant['gia_hien_tai'] < $variant['gia_goc'];
                                $is_out_of_stock = $variant['so_luong_ton'] <= 0;
                            ?>
                            <div class="pd-option-item <?= $is_out_of_stock ? 'out-of-stock' : '' ?>" data-key="<?= $variant_key ?>" <?= $is_out_of_stock ? 'style="opacity:0.5; cursor:not-allowed;"' : '' ?>>
                                <span class="pd-opt-name">
                                    <?= htmlspecialchars($variant['mau_sac']) ?> - <?= htmlspecialchars($variant['dung_luong_ssd']) ?>
                                    <?php if ($is_out_of_stock): ?>
                                        <span class="badge-out-of-stock" style="color:#dc2626; font-weight:bold; margin-left:8px;">[Hết hàng]</span>
                                    <?php endif; ?>
                                </span>
                                <span class="pd-opt-price">
                                    <?php if ($is_discounted): ?>
                                        <del style="color:#999; font-size: 0.8em; margin-right: 8px;"><?= price_format($variant['gia_goc']) ?></del>
                                    <?php endif; ?>
                                    <?= $full_price_text ?>
                                </span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="pd-quantity-section">
                    <label>Số lượng:</label>
                    <div class="quantity-control">
                        <button type="button" class="qty-btn" id="btnMinus">-</button>
                        <input type="number" id="qtyInput" value="1" min="1" max="1" readonly>
                        <button type="button" class="qty-btn" id="btnPlus">+</button>
                    </div>
                    <span id="max-stock-hint" style="font-size: 13px; color: #999; margin-left: 10px;"></span>
                </div>

<div class="pd-actions" style="display: flex; gap: 10px;">
                <button type="button" class="pd-btn btn-wishlist-detail" 
                        style="width: auto; background: #fff; border: 1px solid #ddd; color: #333; display: flex; align-items: center; justify-content: center; padding: 0 20px;" 
                        onclick="toggleWishlistDetail(this, <?= $product_id ?>)">
                    <?php if ($is_liked): ?>
                        <i class="fa-solid fa-heart text-danger"></i>
                    <?php else: ?>
                        <i class="fa-regular fa-heart"></i>
                    <?php endif; ?>
                </button>

                <button class="pd-btn pd-btn-cart" id="addCartBtn" disabled style="flex: 1;">
                    <i class="fa-solid fa-cart-plus"></i> Thêm Giỏ Hàng
                </button>
                
                <button class="pd-btn pd-btn-buy" id="buyNowBtn" disabled style="flex: 1;">
                    Mua Ngay
                </button>
            </div>
                
                <div class="about-gird">
                    <div><i class="fa-solid fa-shield-halved" style="color:#0f62fe;"></i> Hàng chính hãng 100%</div>
                    <div><i class="fa-solid fa-truck" style="color:#0f62fe;"></i> Miễn phí vận chuyển toàn quốc</div>
                    <div><i class="fa-solid fa-rotate-left" style="color:#0f62fe;"></i> Đổi trả trong 7 ngày</div>
                </div>
            </div>
        </div>
    </div>

        <div class="tab-container">
            <div class="tab-buttons">
                <button class="tab-btn active" data-tab="specs">Thông số kỹ thuật</button>
                <button class="tab-btn" data-tab="desc">Mô tả chi tiết</button>
            </div>
            <div id="specs" class="tab-content active">
                <?php if ($specs): ?>
                    <ul>
                        <li><strong>Màn hình:</strong> <?= htmlspecialchars($specs['man_hinh'] ?? 'Đang cập nhật') ?></li>
                        <li><strong>Ổ cứng:</strong> <?= htmlspecialchars($specs['o_cung'] ?? 'Đang cập nhật') ?></li>
                        <li><strong>CPU:</strong> <?= htmlspecialchars($specs['cpu'] ?? 'Đang cập nhật') ?></li>
                        <li><strong>GPU:</strong> <?= htmlspecialchars($specs['gpu'] ?? 'Đang cập nhật') ?></li>
                        <li><strong>RAM:</strong> <?= htmlspecialchars($specs['ram'] ?? 'Đang cập nhật') ?></li>
                    </ul>
                <?php else: ?>
                    <p>Thông số đang được cập nhật...</p>
                <?php endif; ?>
            </div>
            <div id="desc" class="tab-content">
                <?php if (!empty($product['mo_ta_chi_tiet'])): ?>
                    <?= nl2br(htmlspecialchars($product['mo_ta_chi_tiet'])) ?>
                <?php else: ?>
                    <p>Đang cập nhật mô tả chi tiết...</p>
                <?php endif; ?>
            </div>
        </div>

<div class="section">
    <h2 class="section-title" style="margin: 40px 0 20px 0; font-size: 24px; color:#333;">Sản phẩm khác từ <?= htmlspecialchars($brand_name) ?></h2>
    <div class="related-grid">
                <?php foreach ($related_products as $r): 
                    $r_img = (!empty($r['hinh_anh']) && file_exists($img_folder . '/' . $r['hinh_anh'])) ? $img_folder . '/' . $r['hinh_anh'] : $default_img;
                    $r_link = "index.php?page=product_detail&id=" . $r['id'];
                    
                    // Chuẩn bị dữ liệu cho Modal Quick Add
                    $has_variants = !empty($r['variants']);
                    $variants_json = $has_variants ? htmlspecialchars(json_encode($r['variants']), ENT_QUOTES, 'UTF-8') : '';
                ?>
                <div class="product-card">
                    <a href="<?= $r_link ?>" class="product-clickable-area">
                        <div class="product-image">
                            <img src="<?= htmlspecialchars($r_img) ?>" alt="<?= htmlspecialchars($r['ten']) ?>">
                        </div>
                        <div class="card-content">
                            <div class="card-title"><?= htmlspecialchars($r['ten']) ?></div>
                            <div class="card-price">
                                <span class="card-price-new"><?= price_format($r['gia']) ?></span>
                            </div>
                            <?php if (!empty($r['cpu']) || !empty($r['ram'])): ?>
                                <div class="card-specs">
                                    <?= htmlspecialchars($r['cpu']) ?> <?= !empty($r['ram']) ? ' | ' . htmlspecialchars($r['ram']) : '' ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </a>

                    <div class="card-actions-row">
                        <?php if ($has_variants): ?>
                            <a href="javascript:void(0);" 
                            class="btn-card-action btn-card-cart btn-quick-add" 
                            title="Thêm vào giỏ"
                            data-product-id="<?= $r['id'] ?>"
                            data-product-name="<?= htmlspecialchars($r['ten']) ?>"
                            data-product-image="<?= htmlspecialchars($r['hinh_anh']) ?>"
                            data-variants='<?= $variants_json ?>'>
                                <i class="fa-solid fa-cart-plus"></i>
                            </a>
                            
                            <a href="javascript:void(0);" 
                            class="btn-card-action btn-card-buy btn-quick-buy"
                            data-product-id="<?= $r['id'] ?>"
                            data-product-name="<?= htmlspecialchars($r['ten']) ?>"
                            data-product-image="<?= htmlspecialchars($r['hinh_anh']) ?>"
                            data-variants='<?= $variants_json ?>'>
                                Mua ngay
                            </a>
                        <?php else: ?>
                            <a href="javascript:void(0);" class="btn-card-action btn-card-cart" onclick="quickAddSimple(<?= $r['id'] ?>)">
                                <i class="fa-solid fa-cart-plus"></i>
                            </a>
                            <a href="index.php?page=checkout&action=buy_now&id=<?= $r['id'] ?>" class="btn-card-action btn-card-buy">
                                Mua ngay
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
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
                        <button id="qty-minus-modal" style="padding: 5px 10px; border: none; background: none; cursor: pointer; font-size: 16px;">-</button>
                        <input type="number" id="qty-input-modal" value="1" min="1" readonly style="width: 40px; text-align: center; border: none; padding: 5px 0; -moz-appearance: textfield;">
                        <button id="qty-plus-modal" style="padding: 5px 10px; border: none; background: none; cursor: pointer; font-size: 16px;">+</button>
                    </div>
                </div>
            </div>
            <div class="variant-modal-footer" style="display: flex; justify-content: flex-end; padding-top: 20px;">
                <button class="btn btn-outline" id="modal-cancel-btn">Hủy</button>
                <div class="action-buttons-group">
                                    <button type="button" class="btn btn-outline btn-wishlist-detail" onclick="toggleWishlistDetail(this, <?= $product_id ?>)">
                        <?php if ($is_liked): ?>
                            <i class="fa-solid fa-heart text-danger"></i> <span>Đã thích</span>
                        <?php else: ?>
                            <i class="fa-regular fa-heart"></i> <span>Yêu thích</span>
                        <?php endif; ?>
                    </button>
                    <button class="btn btn-outline" id="modal-add-to-cart-btn" style="display:none;" disabled>🛒 Thêm vào giỏ</button>
                    <button class="btn btn-primary" id="modal-buy-now-btn" style="display:none;" disabled>🔥 Mua ngay</button>
                </div>
            </div>
        </div>
    </div>

    <script>
    // ============================================
    // 1. LOGIC CHO SẢN PHẨM CHÍNH (MAIN PRODUCT)
    // ============================================
    const variantsData = <?= json_encode($variants_js_data) ?>;
    const productId = <?= $product['id'] ?>;
    const defaultImg = "<?= htmlspecialchars($img_path) ?>";
    const imgFolder = "<?= $img_folder ?>";

    let selectedVariantKey = null;
    let currentSelectedVariant = null;

    const priceEl = document.getElementById("product-price");
    const stockTextEl = document.getElementById("stock-text");
    const mainImageEl = document.getElementById("main-product-image");
    const buyNowBtn = document.getElementById("buyNowBtn");
    const addCartBtn = document.getElementById("addCartBtn");

    const qtyInput = document.getElementById("qtyInput");
    const btnMinus = document.getElementById("btnMinus");
    const btnPlus = document.getElementById("btnPlus");
    const maxStockHint = document.getElementById("max-stock-hint");

    function formatPrice(n) { return Number(n).toLocaleString('vi-VN') + '₫'; }

    // [CẬP NHẬT] Hàm kiểm tra lựa chọn và Bật/Tắt nút
function checkSelections() {
        // Mặc định disable
        buyNowBtn.disabled = true;
        addCartBtn.disabled = true;

        if (!selectedVariantKey) return;

        const variant = variantsData[selectedVariantKey];
        if (variant) {
            // [MỚI] HIỂN THỊ GIÁ GỐC VÀ GIÁ HIỆN TẠI
            let priceHtml = '';
            if (variant.co_giam_gia) {
                // Hiển thị giá gốc bị gạch ngang và giá mới
                priceHtml += `<del style="color:#999; font-size: 0.7em; margin-right: 15px;">${formatPrice(variant.gia_goc)}</del>`;
            }
            priceHtml += formatPrice(variant.gia);
            priceEl.innerHTML = priceHtml; // Dùng innerHTML để chèn thẻ HTML

            if (variant.so_luong_ton > 0) {
                stockTextEl.textContent = "Còn hàng";
                stockTextEl.className = "stock-label";
                currentSelectedVariant = variant;
                
                qtyInput.max = variant.so_luong_ton;
                qtyInput.value = 1; 
                qtyInput.disabled = false;
                btnMinus.disabled = false;
                btnPlus.disabled = false;
                maxStockHint.textContent = "(Có sẵn " + variant.so_luong_ton + " sản phẩm)";

                // [QUAN TRỌNG] MỞ KHÓA NÚT VÌ CÓ HÀNG
                buyNowBtn.disabled = false;
                addCartBtn.disabled = false;
            } else {
                stockTextEl.textContent = "Tạm hết hàng";
                stockTextEl.className = "stock-label out";
                currentSelectedVariant = null;
                
                qtyInput.value = 1;
                qtyInput.disabled = true;
                btnMinus.disabled = true;
                btnPlus.disabled = true;
                maxStockHint.textContent = "";

                // [QUAN TRỌNG] KHÓA NÚT VÌ HẾT HÀNG
                buyNowBtn.disabled = true;
                addCartBtn.disabled = true;
            }

            if (variant.hinh_anh) {
                mainImageEl.src = imgFolder + '/' + variant.hinh_anh;
            } else {
                mainImageEl.src = defaultImg;
            }
        }
    }

    document.querySelectorAll("#variantOptions .pd-option-item").forEach(opt => {
        // Ngăn click vào item hết hàng
        if (opt.classList.contains("out-of-stock")) {
            opt.style.pointerEvents = "none";
            return;
        }
        
        opt.addEventListener("click", () => {
            document.querySelectorAll("#variantOptions .pd-option-item").forEach(o => o.classList.remove("active"));
            opt.classList.add("active");
            selectedVariantKey = opt.dataset.key;
            checkSelections();
        });
    });

    btnMinus.addEventListener("click", () => {
        let current = parseInt(qtyInput.value) || 1;
        if (current > 1) qtyInput.value = current - 1;
    });

    btnPlus.addEventListener("click", () => {
        let current = parseInt(qtyInput.value) || 1;
        let max = parseInt(qtyInput.max) || 1;
        if (current < max) qtyInput.value = current + 1;
    });

    qtyInput.addEventListener("change", () => {
        let current = parseInt(qtyInput.value) || 1;
        let max = parseInt(qtyInput.max) || 1;
        if (current < 1) qtyInput.value = 1;
        if (current > max) qtyInput.value = max;
    });

    qtyInput.disabled = true;
    btnMinus.disabled = true;
    btnPlus.disabled = true;

    // Mua ngay (Sản phẩm chính)
    buyNowBtn.addEventListener("click", function(){
        if (currentSelectedVariant && currentSelectedVariant.so_luong_ton > 0) {
            let qty = qtyInput.value;
            window.location.href = `index.php?page=checkout&action=buy_now&variant_id=${currentSelectedVariant.id}&quantity=${qty}`;
        }
    });

    // Thêm giỏ (Sản phẩm chính)
    addCartBtn.addEventListener("click", function(){
        if (currentSelectedVariant && currentSelectedVariant.so_luong_ton > 0) {
            const body = new URLSearchParams();
            body.append('action', 'add');
            body.append('id', productId);
            body.append('variant_id', currentSelectedVariant.id);
            body.append('quantity', qtyInput.value);

            fetch('cart-handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString()
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === "success") {
                    showPopup('🛒 Đã thêm vào giỏ hàng thành công!');
                    if (typeof updateCartIconCount === "function") updateCartIconCount(data.cart_count);
                } else {
                    showPopup("Lỗi: " + data.message, true);
                }
            });
        }
    });

    function showPopup(msg, isError = false) {
        const el = document.createElement('div');
        el.innerHTML = msg;
        Object.assign(el.style, {
            position: 'fixed', bottom: '30px', right: '30px',
            background: isError ? '#dc2626' : '#2ecc71', 
            color: '#fff', padding: '12px 24px',
            borderRadius: '8px', boxShadow: '0 4px 15px rgba(0,0,0,0.2)',
            zIndex: '9999', transition: 'opacity 0.5s, transform 0.5s',
            fontSize: '14px', fontWeight: '600', opacity: '0', transform: 'translateY(20px)'
        });
        document.body.appendChild(el);
        requestAnimationFrame(() => { el.style.opacity = '1'; el.style.transform = 'translateY(0)'; });
        setTimeout(() => {
            el.style.opacity = '0'; el.style.transform = 'translateY(20px)';
            setTimeout(() => el.remove(), 500);
        }, 3000);
    }

    document.querySelectorAll(".tab-btn").forEach(btn => {
        btn.addEventListener("click", function(){
            document.querySelectorAll(".tab-btn").forEach(b => b.classList.remove("active"));
            document.querySelectorAll(".tab-content").forEach(c => c.classList.remove("active"));
            this.classList.add("active");
            document.getElementById(this.dataset.tab).classList.add("active");
        });
    });

    // ============================================
    // 2. LOGIC MODAL (CHO SẢN PHẨM LIÊN QUAN)
    // ============================================
    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('quick-add-modal');
        const modalProductName = document.getElementById('modal-product-name');
        const modalPrice = document.getElementById('modal-product-price');
        const modalStock = document.getElementById('modal-stock-status');
        const modalOptionBox = document.getElementById('modal-option-box');
        const modalBuyNowBtn = document.getElementById('modal-buy-now-btn');
        const modalAddToCartBtn = document.getElementById('modal-add-to-cart-btn');
        const modalMainImage = document.getElementById('modal-product-main-image');
        
        // Đổi ID để không trùng với main product
        const qtyInputModal = document.getElementById('qty-input-modal');
        const qtyMinusBtnModal = document.getElementById('qty-minus-modal');
        const qtyPlusBtnModal = document.getElementById('qty-plus-modal');

        let m_currentVariants = [];
        let m_currentSelectedVariant = null;
        let m_defaultProductImage = 'assets/img/no-image.png';
        let m_maxQuantity = 0;
        let m_currentProductId = 0;

        function openQuickModal(e) {
            e.preventDefault();
            const btn = e.currentTarget;
            modalProductName.textContent = btn.dataset.productName;
            const productImage = btn.dataset.productImage;
            m_defaultProductImage = productImage ? `assets/img/products/${productImage}` : 'assets/img/no-image.png';
            modalMainImage.src = m_defaultProductImage;

            try { m_currentVariants = JSON.parse(btn.dataset.variants); } 
            catch(e) { alert('Lỗi dữ liệu biến thể.'); return; }
            
            m_currentProductId = btn.dataset.productId;
            m_currentSelectedVariant = null;
            m_maxQuantity = 0;
            qtyInputModal.value = 1;
            modalPrice.textContent = '--';
            modalStock.textContent = 'Vui lòng chọn tùy chọn';
            modalStock.className = 'stock-info';
            qtyMinusBtnModal.disabled = true;
            qtyPlusBtnModal.disabled = true;

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
            m_currentVariants.forEach(v => {
                let displayText = (v.dung_luong_ssd ? v.dung_luong_ssd+' ' : '') + (v.mau_sac ? v.mau_sac : '');
                const opt = document.createElement('div');
                opt.className = 'option';
                opt.dataset.variantId = v.id;
                opt.textContent = displayText || 'Tùy chọn';
                if (parseInt(v.so_luong_ton) === 0) { opt.classList.add('disabled'); opt.title='Hết hàng'; }
                modalOptionBox.appendChild(opt);
            });

            modal.style.display = 'flex';
        }

        function closeQuickModal() {
            modal.style.display = 'none';
        }

        function updateModalQty() {
            let currentQty = parseInt(qtyInputModal.value);
            if (currentQty < 1) currentQty = 1;
            if (currentQty > m_maxQuantity) currentQty = m_maxQuantity;
            qtyInputModal.value = currentQty;
            
            qtyMinusBtnModal.disabled = currentQty <= 1 || m_maxQuantity === 0;
            qtyPlusBtnModal.disabled = currentQty >= m_maxQuantity || m_maxQuantity === 0;

            if (m_currentSelectedVariant && currentQty > 0 && currentQty <= m_maxQuantity) {
                modalAddToCartBtn.disabled = false;
                modalBuyNowBtn.disabled = false;
            }
        }

        function selectVariant(variantId) {
            m_currentSelectedVariant = m_currentVariants.find(v => String(v.id) === String(variantId));
            if (!m_currentSelectedVariant) return;

            m_maxQuantity = parseInt(m_currentSelectedVariant.so_luong_ton);
            modalPrice.textContent = new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(m_currentSelectedVariant.gia);
            
            if (m_maxQuantity > 0) {
                modalStock.textContent = `Còn hàng (${m_maxQuantity})`;
                modalStock.className = 'stock-info';
            } else {
                modalStock.textContent = 'Hết hàng';
                modalStock.className = 'stock-info out';
            }

            qtyInputModal.value = 1;
            updateModalQty();
            if(m_currentSelectedVariant.hinh_anh) {
                modalMainImage.src = `assets/img/products/${m_currentSelectedVariant.hinh_anh}`;
            }
        }

        // Gắn sự kiện cho các nút trong list
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

        qtyMinusBtnModal.addEventListener('click', () => { qtyInputModal.value = parseInt(qtyInputModal.value)-1; updateModalQty(); });
        qtyPlusBtnModal.addEventListener('click', () => { qtyInputModal.value = parseInt(qtyInputModal.value)+1; updateModalQty(); });

        modalAddToCartBtn.addEventListener('click', function() {
            if (!m_currentSelectedVariant) return;
            const body = new URLSearchParams();
            body.append('action', 'add');
            body.append('id', m_currentProductId);
            body.append('variant_id', m_currentSelectedVariant.id);
            body.append('quantity', qtyInputModal.value);

            fetch('cart-handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString()
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === "success") {
                    showPopup('🛒 Đã thêm vào giỏ hàng!');
                    closeQuickModal();
                    if (typeof updateCartIconCount === "function") updateCartIconCount(data.cart_count);
                } else {
                    showPopup("Lỗi: " + data.message, true);
                }
            });
        });

        modalBuyNowBtn.addEventListener('click', () => {
            if (!m_currentSelectedVariant) return;
            window.location.href = `index.php?page=checkout&action=buy_now&variant_id=${m_currentSelectedVariant.id}&qty=${parseInt(qtyInputModal.value)}`;
        });
    });
    // Hàm hỗ trợ thêm nhanh sản phẩm không biến thể
    function quickAddSimple(id) {
        const body = new URLSearchParams();
        body.append('action', 'add');
        body.append('id', id);
        body.append('quantity', 1);

        fetch('cart-handler.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === "success") {
                showPopup('🛒 Đã thêm vào giỏ hàng!');
                if (typeof updateCartIconCount === "function") updateCartIconCount(data.cart_count);
            } else {
                showPopup("Lỗi: " + data.message, true);
            }
        });
    }
    </script>
    <script>
async function toggleWishlistDetail(btn, productId) {
    const userId = <?= $user_id ?>;
    
    // 1. Kiểm tra đăng nhập
    if (userId === 0) {
        if(confirm('Bạn cần đăng nhập để lưu sản phẩm yêu thích. Đăng nhập ngay?')) {
            window.location.href = 'index.php?page=login';
        }
        return;
    }

    // 2. Khóa nút tạm thời
    btn.disabled = true;
    const icon = btn.querySelector('i');
    const textSpan = btn.querySelector('span');

    try {
        // 3. Gửi Ajax
        const formData = new FormData();
        formData.append('product_id', productId);
        formData.append('action', 'toggle');

        const response = await fetch('wishlist_handler.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();

        // 4. Cập nhật giao diện (Icon + Chữ)
        if (data.status === 'success') {
            if (data.state === 'liked') {
                // Trạng thái: ĐÃ THÍCH
                icon.className = 'fa-solid fa-heart text-danger';
                textSpan.textContent = 'Đã thích';
                btn.classList.add('active'); // Thêm class để CSS nếu cần
            } else {
                // Trạng thái: CHƯA THÍCH
                icon.className = 'fa-regular fa-heart';
                textSpan.textContent = 'Yêu thích';
                btn.classList.remove('active');
            }
        } else {
            alert(data.message);
        }
    } catch (error) {
        console.error(error);
        alert('Có lỗi xảy ra!');
    } finally {
        btn.disabled = false;
    }
}
</script>

    <?php require_once 'client/layouts/footer.php'; ?>