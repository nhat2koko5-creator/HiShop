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
body {
    font-family: "Inter", Arial, sans-serif;
    background: #f6f8fb;
    color: #1f2937;
}
.container {
    max-width: 1200px;
    margin: auto;
    padding: 32px 16px;
}
.page-title {
    font-size: 28px;
    font-weight: 700;
    color: #111827;
    margin-bottom: 24px;
    text-align: center;
}
.product-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}
.card {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 8px 20px rgba(18,38,63,.05);
    padding: 16px;
    text-align: center;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.card:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 25px rgba(18,38,63,.08);
}
.image-wrapper {
    width: 100%;
    height: 200px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    margin-bottom: 12px;
}
.image-wrapper img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
    transition: transform 0.3s ease;
}
.image-wrapper img:hover {
    transform: scale(1.05);
}
.card .name {
    font-size: 16px;
    font-weight: 600;
    color: #111827;
    margin-bottom: 8px;
    line-height: 1.3;
    min-height: 40px;
}
.card .price {
    color: #ef4444;
    font-weight: 700;
    margin-bottom: 10px;
}
.card .btn {
    display: inline-block;
    width: 100%;
    padding: 10px 0;
    border-radius: 8px;
    background: #0f62fe;
    color: #fff;
    font-weight: 600;
    text-decoration: none;
    transition: background 0.2s ease;
}
.card .btn:hover {
    background: #0043ce;
}
.no-products {
    text-align: center;
    color: #6b7280;
    font-size: 16px;
    margin-top: 40px;
}
</style>

<div class="container">
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
                    <a href="index.php?page=product_detail&id=<?= $p['id'] ?>" class="btn">Xem chi tiết</a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="no-products">Không có sản phẩm nào trong danh mục này.</p>
    <?php endif; ?>
</div>

<?php require_once 'client/layouts/footer.php'; ?>
