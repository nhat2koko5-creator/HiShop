<?php
require_once 'client/layouts/header.php';

// Lấy category_id từ URL
$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;

// Tên danh mục (mặc định)
$category_name = "Tất cả sản phẩm";

// Nếu có category_id hợp lệ → lọc sản phẩm theo danh mục
if ($category_id > 0) {
    // Lấy tên danh mục
    $stmt = $pdo->prepare("SELECT ten FROM danh_muc WHERE id = ?");
    $stmt->execute([$category_id]);
    $cat_name = $stmt->fetchColumn();

    if ($cat_name) {
        $category_name = $cat_name;
        $stmt = $pdo->prepare("SELECT * FROM san_pham WHERE danh_muc_id = ?");
        $stmt->execute([$category_id]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Nếu không tìm thấy danh mục → hiển thị tất cả
        $products = $pdo->query("SELECT * FROM san_pham")->fetchAll(PDO::FETCH_ASSOC);
    }
} else {
    // Không có category_id → hiển thị tất cả
    $products = $pdo->query("SELECT * FROM san_pham")->fetchAll(PDO::FETCH_ASSOC);
}

// Xử lý ảnh sản phẩm
$img_folder = "assets/img/products";
$default_img = "assets/img/no-image.png";

function format_price($price) {
    return number_format($price, 0, ',', '.') . "₫";
}
?>

<style>
</style>

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
                    <div class="price"><?= format_price($p['gia']) ?></div>
                    <div class="btn-group">
                        <a href="index.php?page=product_detail&id=<?= $p['id'] ?>" class="btn btn-detail">🔍 Xem chi tiết</a>
                        <a href="index.php?page=checkout&id=<?= $p['id'] ?>" class="btn btn-buy">🛍️ Mua ngay</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="no-products">Không có sản phẩm nào trong danh mục này.</p>
    <?php endif; ?>
</div>

<?php require_once 'client/layouts/footer.php'; ?>
