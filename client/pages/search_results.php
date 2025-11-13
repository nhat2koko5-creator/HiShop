<?php
// Tải header
require_once 'client/layouts/header.php';

// 1. Lấy từ khóa tìm kiếm từ URL (thường là 'q')
$search_query = trim($_GET['query'] ?? '');

// 2. Chuẩn bị các biến
$products = [];
$page_title = "Kết quả tìm kiếm"; // Tiêu đề mặc định
$no_product_message = "Không tìm thấy sản phẩm nào phù hợp.";

// 3. Định nghĩa hàm format giá (giống hệt file product_list.php)
function format_price($price) {
    return number_format($price, 0, ',', '.') . "₫";
}

// 4. Định nghĩa đường dẫn ảnh (giống hệt file product_list.php)
$img_folder = "assets/img/products";
$default_img = "assets/img/no-image.png";

// 5. Thực hiện truy vấn CSDL NẾU có từ khóa
if (!empty($search_query)) {
    // Cập nhật tiêu đề trang
    $page_title = "Kết quả cho: \"" . htmlspecialchars($search_query) . "\"";
    
    // Chuẩn bị tham số cho LIKE
    $search_param = "%" . $search_query . "%";

    try {
        // Tìm kiếm ở Tên, Mô tả ngắn, và Mô tả chi tiết
        // Chúng ta cũng chỉ tìm các sản phẩm đang hoạt động (trang_thai = 1)
        $stmt = $pdo->prepare("SELECT * FROM san_pham 
                             WHERE trang_thai = 1 
                             AND (ten LIKE ? OR mo_ta LIKE ? OR mo_ta_chi_tiet LIKE ?)");
        
        $stmt->execute([$search_param, $search_param, $search_param]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log($e->getMessage()); // Ghi lại lỗi
        $no_product_message = "Đã xảy ra lỗi khi tìm kiếm. Vui lòng thử lại.";
    }

} else {
    // Nếu người dùng truy cập trang mà không có từ khóa
    $no_product_message = "Vui lòng nhập từ khóa để tìm kiếm.";
}
?>

<div class="container">
    <h1 class="page-title">
        <?= htmlspecialchars($page_title) ?>
    </h1>

    <?php if (!empty($products)): ?>
        <div class="product-grid">
            
            <?php foreach ($products as $p): 
                // Xử lý đường dẫn ảnh (y hệt product_list.php)
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
        <p class="no-products"><?= htmlspecialchars($no_product_message) ?></p>
    <?php endif; ?>
</div>

<?php
// Tải footer
require_once 'client/layouts/footer.php';
?>