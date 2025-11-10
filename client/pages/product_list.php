<?php
// client/pages/product_list.php
require_once 'client/layouts/header.php';

// Thư mục ảnh (chỉnh nếu cần)
$img_folder = 'assets/img/products';
$default_img = 'assets/img/no-image.png';

// Lấy param cat (có thể là id hoặc tên)
$cat_param = $_GET['cat'] ?? '';

// Nếu không có cat => hiển thị tất cả (hoặc redirect)
if ($cat_param === '' || $cat_param === '0') {
    // Hiển thị tất cả sản phẩm
    $cat_name = 'Tất cả sản phẩm';
    $stmt = $pdo->prepare("SELECT id, ten, gia, hinh_anh, danh_muc_id FROM san_pham ORDER BY id DESC");
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Nếu cat_param là số (id) -> lọc theo id
    if (ctype_digit((string)$cat_param)) {
        $cat_id = (int)$cat_param;
        // Kiểm tra danh mục tồn tại
        $stmt_cat = $pdo->prepare("SELECT id, ten FROM danh_muc WHERE id = ? LIMIT 1");
        $stmt_cat->execute([$cat_id]);
        $cat = $stmt_cat->fetch(PDO::FETCH_ASSOC);
        if (!$cat) {
            // Danh mục không tồn tại
            $cat_name = "Danh mục không tồn tại";
            $products = [];
        } else {
            $cat_name = $cat['ten'];
            // Lấy sản phẩm thuộc danh mục (lọc chính xác bằng danh_muc_id)
            $stmt = $pdo->prepare("SELECT id, ten, gia, hinh_anh, danh_muc_id FROM san_pham WHERE danh_muc_id = ? ORDER BY id DESC");
            $stmt->execute([$cat_id]);
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } else {
        // Nếu cat_param là chuỗi -> coi như tên danh mục (ten) -> tìm id trước
        $cat_slug = trim($cat_param);
        $stmt_cat = $pdo->prepare("SELECT id, ten FROM danh_muc WHERE ten = ? LIMIT 1");
        $stmt_cat->execute([$cat_slug]);
        $cat = $stmt_cat->fetch(PDO::FETCH_ASSOC);
        if (!$cat) {
            $cat_name = "Danh mục không tồn tại";
            $products = [];
        } else {
            $cat_name = $cat['ten'];
            $cat_id = $cat['id'];
            $stmt = $pdo->prepare("SELECT id, ten, gia, hinh_anh, danh_muc_id FROM san_pham WHERE danh_muc_id = ? ORDER BY id DESC");
            $stmt->execute([$cat_id]);
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
}

// Hàm format giá
function price_format($n) {
    return number_format($n, 0, ',', '.') . '₫';
}
?>

<style>
.container{max-width:1200px;margin:40px auto;padding:0 20px;font-family:Inter,Arial,Helvetica,sans-serif;}
h1{font-size:28px;margin-bottom:18px;color:#111;}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:18px;}
.card{background:#fff;border-radius:12px;padding:14px;text-align:center;box-shadow:0 6px 18px rgba(15,23,42,0.04);display:flex;flex-direction:column;justify-content:space-between;height:100%;}
.card img{width:100%;height:170px;object-fit:contain;margin-bottom:10px;}
.name{font-size:15px;font-weight:600;color:#111;min-height:42px;margin-bottom:8px;}
.price{color:#ef4444;font-weight:700;margin-bottom:10px;}
.btn-primary{background:#0f62fe;color:#fff;border:0;padding:10px 14px;border-radius:8px;cursor:pointer;font-weight:600;text-decoration:none;display:inline-block;}
.empty{padding:30px;text-align:center;color:#666;}
.meta{font-size:13px;color:#555;margin-bottom:8px;}
</style>

<div class="container">
  <h1><?= htmlspecialchars($cat_name) ?></h1>

  <?php if (empty($products)): ?>
    <div class="empty">Không tìm thấy sản phẩm trong danh mục này.</div>
  <?php else: ?>
    <div class="grid">
      <?php foreach ($products as $p):
        $img = (!empty($p['hinh_anh']) && file_exists($img_folder . '/' . $p['hinh_anh'])) ? $img_folder . '/' . $p['hinh_anh'] : $default_img;
      ?>
        <div class="card">
          <div>
            <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($p['ten']) ?>">
            <div class="name"><?= htmlspecialchars($p['ten']) ?></div>
            <div class="meta">Mã SP: <?= (int)$p['id'] ?></div>
            <div class="price"><?= price_format($p['gia']) ?></div>
          </div>
          <div style="margin-top:10px">
            <a class="btn-primary" href="index.php?page=product_detail&id=<?= $p['id'] ?>">Xem chi tiết</a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php require_once 'client/layouts/footer.php'; ?>
