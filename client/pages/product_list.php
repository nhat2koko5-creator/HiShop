<?php
// FILE: product_list.php (ĐÃ NÂNG CẤP PHÂN TRANG)
require_once 'client/layouts/header.php';

// (MỚI) 1. THIẾT LẬP PHÂN TRANG
$products_per_page = 11; // Hiển thị 9 sản phẩm mỗi trang
$current_page = isset($_GET['p']) ? (int)$_GET['p'] : 1;
if ($current_page < 1) $current_page = 1;
$offset = ($current_page - 1) * $products_per_page;

// 2. LẤY DANH SÁCH SẢN PHẨM (DỰA TRÊN CATEGORY)
$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$category_name = "Tất cả sản phẩm";
$products = [];
$total_products = 0;
$total_pages = 1;

// (MỚI) Chuẩn bị câu lệnh SQL
$sql_base = " FROM san_pham WHERE trang_thai = 1";
$params = [];

if ($category_id > 0) {
    // Lấy tên danh mục
    $stmt_cat = $pdo->prepare("SELECT ten FROM danh_muc WHERE id = ?");
    $stmt_cat->execute([$category_id]);
    $cat_name = $stmt_cat->fetchColumn();

    if ($cat_name) {
        $category_name = $cat_name;
        $sql_base .= " AND danh_muc_id = ?";
        $params[] = $category_id;
    }
}

try {
    // (MỚI) 3. TRUY VẤN ĐẾM TỔNG SẢN PHẨM (ĐỂ PHÂN TRANG)
    $stmt_count = $pdo->prepare("SELECT COUNT(id)" . $sql_base);
    $stmt_count->execute($params);
    $total_products = (int)$stmt_count->fetchColumn();
    $total_pages = ceil($total_products / $products_per_page);

    if ($current_page > $total_pages && $total_products > 0) {
        // Nếu người dùng cố vào trang không có, chuyển về trang cuối
        $current_page = $total_pages;
        $offset = ($current_page - 1) * $products_per_page;
    }

    // (MỚI) 4. TRUY VẤN LẤY SẢN PHẨM (VỚI LIMIT VÀ OFFSET)
    $sql_products = "SELECT *" . $sql_base . " ORDER BY id DESC LIMIT ? OFFSET ?";
    $params[] = $products_per_page;
    $params[] = $offset;

    $stmt_products = $pdo->prepare($sql_products);
    // (MỚI) Gán kiểu dữ liệu cho LIMIT và OFFSET
    $stmt_products->bindParam(count($params) - 1, $products_per_page, PDO::PARAM_INT);
    $stmt_products->bindParam(count($params), $offset, PDO::PARAM_INT);
    // Gán các tham số khác (nếu có, như category_id)
    for ($i = 0; $i < count($params) - 2; $i++) {
        $stmt_products->bindParam($i + 1, $params[$i]);
    }
    
    $stmt_products->execute();
    $products = $stmt_products->fetchAll(PDO::FETCH_ASSOC);

    // (MỚI) 5. LẤY BIẾN THỂ (TỐI ƯU HÓA, giống home.php)
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
        $all_variants = $stmt_variants->fetchAll(PDO::FETCH_ASSOC);

        $variants_map = [];
        foreach ($all_variants as $variant) {
            $variants_map[$variant['san_pham_id']][] = $variant;
        }

        foreach ($products as $i => $product) {
            $product_id = $product['id'];
            if (isset($variants_map[$product_id])) {
                $products[$i]['variants'] = $variants_map[$product_id];
                $prices = array_column($variants_map[$product_id], 'gia');
                $products[$i]['gia'] = min($prices); // Cập nhật giá "từ"
            } else {
                $products[$i]['variants'] = [];
            }
        }
    }

} catch (PDOException $e) {
    error_log("Lỗi Product List: " . $e.getMessage());
    $products = [];
}

// 6. HÀM HỖ TRỢ VÀ ĐƯỜNG DẪN ẢNH (Giữ nguyên)
$img_folder = "assets/img/products";
$default_img = "assets/img/no-image.png";

function format_price($price) {
    return number_format($price, 0, ',', '.') . "₫";
}
?>
<div class="container">

<nav class="breadcrumb">
    <a href="index.php">Trang chủ</a>
    <span class="divider">›</span>

    <?php if ($category_id > 0): ?>
        <a href="index.php?page=product_list">Danh mục</a>
        <span class="divider">›</span>
        <span class="current"><?= htmlspecialchars($category_name) ?></span>
    <?php else: ?>
        <span class="current">Danh sách sản phẩm</span>
    <?php endif; ?>
</nav>

    <h1 class="page-title">
        <?= htmlspecialchars($category_name) ?>
    </h1>

    <?php if (!empty($products)): ?>
        <div class="product-grid">
            <?php foreach ($products as $p): 
                $img_path = (!empty($p['hinh_anh']) && file_exists($img_folder . '/' . $p['hinh_anh']))
                    ? $img_folder . '/' . $p['hinh_anh']
                    : $default_img;
            ?>
                <div class="card">
                    <div class="image-wrapper">
                        <img src="<?= htmlspecialchars($img_path) ?>" alt="<?= htmlspecialchars($p['ten']) ?>">
                    </div>
                    <div class="name"><?= htmlspecialchars($p['ten']) ?></div>
                    <div class="price">
                        <?= format_price($p['gia']) ?>
                        <?php if (count($p['variants']) > 1): ?>
                            <span style="font-size: 14px; color: #6B7280;"></span>
                        <?php endif; ?>
                    </div>
                    <div class="btn-group">
                        <a href="index.php?page=product_detail&id=<?= $p['id'] ?>" class="btn btn-detail">🔍 Xem chi tiết</a>
                        
                        <?php if (!empty($p['variants'])): ?>
                            <a href="javascript:void(0);" 
                               class="btn btn-buy btn-quick-buy" 
                               data-product-id="<?= $p['id']; ?>"
                               data-product-name="<?= htmlspecialchars($p['ten']); ?>"
                               data-product-image="<?= htmlspecialchars($p['hinh_anh']); ?>"
                               data-variants='<?= htmlspecialchars(json_encode($p['variants']), ENT_QUOTES, 'UTF-8'); ?>'
                            >
                                🛍️ Mua ngay
                            </a>
                        <?php else: ?>
                             <a href="#" class="btn btn-buy" disabled>🛍️ Tạm hết hàng</a>
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
            <?php
            // Nút "Trang trước"
            if ($current_page > 1): ?>
                <li>
                    <a href="index.php?page=product_list&category_id=<?= $category_id ?>&p=<?= $current_page - 1 ?>">
                        «
                    </a>
                </li>
            <?php endif; ?>

            <?php
            // Hiển thị các trang
            for ($i = 1; $i <= $total_pages; $i++): ?>
                <li>
                    <a href="index.php?page=product_list&category_id=<?= $category_id ?>&p=<?= $i ?>"
                       class="<?= ($i == $current_page) ? 'active' : '' ?>">
                        <?= $i ?>
                    </a>
                </li>
            <?php endfor; ?>

            <?php
            // Nút "Trang sau"
            if ($current_page < $total_pages): ?>
                <li>
                    <a href="index.php?page=product_list&category_id=<?= $category_id ?>&p=<?= $current_page + 1 ?>">
                        »
                    </a>
                </li>
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
                        <div class="price" style="margin-bottom: 20px;">
                            Giá: 
                            <span class="current" id="modal-product-price" style="margin-left: 8px; font-size: 20px; font-weight: 700;">--</span>
                        </div>
                        <div class="stock-info" id="modal-stock-status">Vui lòng chọn tùy chọn</div>
                    </div>
                </div>

                <hr style="margin: 20px 0;">

                <div class="option-group" id="modal-color-group">
                    <h4>Màu sắc</h4>
                    <div class="option-box" id="modal-color-options">
                        </div>
                </div>
                <div class="option-group" id="modal-ssd-group">
                    <h4>SSD</h4>
                    <div class="option-box" id="modal-ssd-options">
                        </div>
                </div>
            </div>
            <div class="variant-modal-footer">
                <button class="btn btn-outline" id="modal-cancel-btn">Hủy</button>
                <button class="btn btn-primary" id="modal-action-btn" disabled>Mua ngay</button>
            </div>
        </div>
    </div>
    </div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // --- Biến DOM (cho modal) ---
    const modal = document.getElementById('quick-add-modal');
    const modalProductName = document.getElementById('modal-product-name');
    const modalPrice = document.getElementById('modal-product-price');
    const modalStock = document.getElementById('modal-stock-status');
    const modalColorBox = document.getElementById('modal-color-options');
    const modalSsdBox = document.getElementById('modal-ssd-options');
    const modalActionBtn = document.getElementById('modal-action-btn'); // Sửa tên nút
    const modalMainImage = document.getElementById('modal-product-main-image');
    
    // --- Biến Trạng Thái (sẽ được reset) ---
    let currentVariants = []; 
    let currentProductId = null;
    let selectedColor = null;
    let selectedSSD = null;
    let currentSelectedVariant = null; 
    let defaultProductImage = 'assets/img/no-image.png'; 

    // === HÀM 1: MỞ VÀ ĐIỀN DỮ LIỆU VÀO MODAL ===
    function openQuickModal(e) {
        e.preventDefault();
        const btn = e.currentTarget;

        currentProductId = btn.dataset.productId;
        modalProductName.textContent = btn.dataset.productName;
        
        const productImage = btn.dataset.productImage;
        defaultProductImage = productImage ? `assets/img/products/${productImage}` : 'assets/img/no-image.png';
        modalMainImage.src = defaultProductImage;
        
        try {
            currentVariants = JSON.parse(btn.dataset.variants);
        } catch(e) {
            alert('Lỗi dữ liệu biến thể. Vui lòng thử lại.');
            return;
        }

        const colors = [...new Set(currentVariants.map(v => v.mau_sac))];
        const ssds = [...new Set(currentVariants.map(v => v.dung_luong_ssd))];

        modalColorBox.innerHTML = ''; 
        colors.forEach(color => {
            const opt = document.createElement('div');
            opt.className = 'option';
            opt.dataset.group = 'color';
            opt.dataset.value = color;
            opt.textContent = color;
            modalColorBox.appendChild(opt);
        });

        modalSsdBox.innerHTML = ''; 
        ssds.forEach(ssd => {
            const opt = document.createElement('div');
            opt.className = 'option';
            opt.dataset.group = 'ssd';
            opt.dataset.value = ssd;
            opt.textContent = ssd;
            modalSsdBox.appendChild(opt);
        });
        
        modalPrice.textContent = '--';
        modalStock.textContent = 'Vui lòng chọn tùy chọn';
        modalStock.className = 'stock-info';
        modalActionBtn.disabled = true; // Sửa tên nút
        modalActionBtn.textContent = 'Mua ngay'; // Sửa text nút
        modal.style.display = 'flex';
    }

    // === HÀM 2: ĐÓNG VÀ RESET MODAL ===
    function closeQuickModal() {
        modal.style.display = 'none';
        currentVariants = [];
        currentProductId = null;
        selectedColor = null;
        selectedSSD = null;
        currentSelectedVariant = null;
        modalActionBtn.disabled = true; // Sửa tên nút
    }

    // === HÀM 3: KIỂM TRA LỰA CHỌN (Logic chính) ===
    function checkModalSelections() {
        modalActionBtn.disabled = true; // Sửa tên nút
        currentSelectedVariant = null;

        if (!selectedColor || !selectedSSD) {
            return;
        }

        const variant = currentVariants.find(v => (v.mau_sac === selectedColor && v.dung_luong_ssd === selectedSSD));

        if (variant) {
            modalPrice.textContent = formatPrice(variant.gia);
            
            if (variant.so_luong_ton > 0) {
                modalStock.textContent = "Còn " + variant.so_luong_ton + " sản phẩm";
                modalStock.className = 'stock-info';
                modalActionBtn.disabled = false; // Sửa tên nút
                currentSelectedVariant = variant; 
            } else {
                modalStock.textContent = "Hết hàng";
                modalStock.className = 'stock-info out';
            }
            
            if (variant.hinh_anh) {
                modalMainImage.src = `assets/img/products/${variant.hinh_anh}`;
            } else {
                modalMainImage.src = defaultProductImage; 
            }
            
        } else {
            modalPrice.textContent = '--';
            modalStock.textContent = "Tùy chọn không có sẵn";
            modalStock.className = 'stock-info out';
            modalMainImage.src = defaultProductImage; 
        }
    }

    // === HÀM 4: HÀM HỖ TRỢ ===
    function formatPrice(n) {
        const number = Number(n); 
        if (isNaN(number)) return 'Liên hệ';
        return number.toLocaleString('vi-VN') + '₫';
    }

    // === GÁN SỰ KIỆN ===

    // 1. Gán sự kiện cho tất cả nút "Mua ngay"
    document.querySelectorAll('.btn-quick-buy').forEach(button => {
        button.addEventListener('click', openQuickModal);
    });

    // 2. Gán sự kiện cho các nút đóng modal
    document.getElementById('modal-close-btn').addEventListener('click', closeQuickModal);
    document.getElementById('modal-cancel-btn').addEventListener('click', closeQuickModal);
    modal.addEventListener('click', e => {
        if (e.target === modal) closeQuickModal();
    });

    // 3. (MỚI) Dùng "Event Delegation" để xử lý các nút .option
    modal.addEventListener('click', function(e) {
        if (!e.target.classList.contains('option') || e.target.classList.contains('disabled')) {
            return;
        }
        const group = e.target.dataset.group;
        const value = e.target.dataset.value;

        if (group === 'color') {
            selectedColor = value;
            modalColorBox.querySelectorAll('.option').forEach(o => o.classList.remove('active'));
        } else if (group === 'ssd') {
            selectedSSD = value;
            modalSsdBox.querySelectorAll('.option').forEach(o => o.classList.remove('active'));
        }
        
        e.target.classList.add('active');
        checkModalSelections();
    });

    // 4. (MỚI) Gán sự kiện cho nút "Mua ngay" TRONG MODAL
    modalActionBtn.addEventListener('click', async function() {
        if (!currentSelectedVariant) return;

        // Lấy ID biến thể
        const variantId = currentSelectedVariant.id;
        
        // Chuyển hướng đến trang checkout (giống hệt product_detail.php)
        window.location.href = `index.php?page=checkout&action=buy_now&variant_id=${variantId}`;
    });
});
</script>

<?php require_once 'client/layouts/footer.php'; ?>