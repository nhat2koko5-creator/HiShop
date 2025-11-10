<?php
require_once 'client/layouts/header.php';

// Lấy id sản phẩm
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($product_id <= 0) {
    echo "<div class='container'><p>Sản phẩm không tồn tại.</p></div>";
    require_once 'client/layouts/footer.php';
    exit;
}

// Lấy thông tin sản phẩm
$stmt = $pdo->prepare("SELECT id, ten, gia, so_luong, hinh_anh, danh_muc_id FROM san_pham WHERE id = ?");
$stmt->execute([$product_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    echo "<div class='container'><p>Không tìm thấy sản phẩm.</p></div>";
    require_once 'client/layouts/footer.php';
    exit;
}

// Lấy tên danh mục
$category = 'Không xác định';
if (!empty($product['danh_muc_id'])) {
    $stmt_cat = $pdo->prepare("SELECT ten FROM danh_muc WHERE id = ?");
    $stmt_cat->execute([$product['danh_muc_id']]);
    $cat_name = $stmt_cat->fetchColumn();
    if ($cat_name) $category = $cat_name;
}

// Lấy thông số sản phẩm
$stmt_specs = $pdo->prepare("
    SELECT ts.man_hinh, ts.o_cung, ts.cpu, ts.gpu, ts.ram
    FROM san_pham_thong_so spts
    JOIN thong_so ts ON spts.thong_so_id = ts.id
    WHERE spts.san_pham_id = ?
");
$stmt_specs->execute([$product_id]);
$specs = $stmt_specs->fetch(PDO::FETCH_ASSOC);

// Lấy sản phẩm liên quan
$stmt_related = $pdo->prepare("
    SELECT sp.id, sp.ten, sp.gia, sp.hinh_anh, ts.cpu, ts.ram 
    FROM san_pham sp
    LEFT JOIN san_pham_thong_so spts ON sp.id = spts.san_pham_id
    LEFT JOIN thong_so ts ON spts.thong_so_id = ts.id
    WHERE sp.danh_muc_id = ? AND sp.id != ?
    LIMIT 4
");
$stmt_related->execute([$product['danh_muc_id'], $product_id]);
$related_products = $stmt_related->fetchAll(PDO::FETCH_ASSOC);

function price_format($n) {
    return number_format($n, 0, ',', '.') . '₫';
}

$img_folder = 'assets/img/products';
$default_img = 'assets/img/no-image.png';
$img_path = (!empty($product['hinh_anh']) && file_exists($img_folder . '/' . $product['hinh_anh'])) ? $img_folder . '/' . $product['hinh_anh'] : $default_img;
?>

<style>
body {font-family: Inter, sans-serif; background: #f6f8fb; color:#1f2937;}
.container {max-width: 1200px; margin: auto; padding: 30px;}
img {max-width: 100%; display:block;}
.grid {display: grid; gap: 30px; grid-template-columns: 1fr;}
@media(min-width: 900px){ .grid { grid-template-columns: 1fr 1fr; } }

.image-panel {
  background: #fff;
  border-radius: 14px;
  padding: 24px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  box-shadow: 0 6px 18px rgba(18,38,63,0.06);
}
.image-container {
  width: 100%;
  max-width: 320px;
  display: flex;
  justify-content: center;
  align-items: center;
}
.image-container img {
  max-width: 100%;
  max-height: 340px;
  object-fit: contain;
}
.stock-info {
  margin-top: 10px;
  font-weight: 600;
  font-size: 16px;
  color: #111827;
}
.stock-info .out { color: #dc2626; font-weight: bold; }

.info h1 {font-size: 28px; margin-bottom: 10px;}
.price {display:flex; align-items:baseline; gap:12px; margin-bottom:12px;}
.price .current {color:#ef4444;font-weight:700;font-size:22px;}
.actions {display:flex; gap:10px; flex-wrap:wrap;}
.btn {cursor:pointer;border:0;padding:12px 18px;border-radius:12px;font-weight:600;font-size:15px;}
.btn-primary{background:#0f62fe;color:#fff;}
.btn-ghost{background:#f3f4f6;color:#111827;}

/* Tabs */
.tab-container {margin-top: 30px; background:#fff; border-radius:12px; padding:20px; box-shadow:0 8px 20px rgba(0,0,0,0.05);}
.tab-buttons {display:flex; gap:10px; margin-bottom:15px;}
.tab-buttons button {flex:1; padding:12px; border:0; border-radius:10px; background:#f3f4f6; cursor:pointer; font-weight:600;}
.tab-buttons button.active {background:#0f62fe; color:#fff;}
.tab-content {display:none;}
.tab-content.active {display:block;}

/* Related */
.section {margin-top:40px;}
.related-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: 18px;
}
.card {
  background:#fff;
  border-radius:12px;
  padding:16px;
  text-align:center;
  box-shadow:0 8px 20px rgba(18,38,63,0.05);
}
.card img {
  width:100%;
  height:160px;
  object-fit:contain;
  margin-bottom:8px;
}
.card .name {font-weight:600; font-size:15px; min-height:40px;}
.card .price {color:#ef4444;font-weight:600;margin-bottom:6px;}
.card .spec {font-size:13px; color:#6b7280; margin-bottom:10px;}
.card .buttons {display:flex; gap:6px; flex-direction:column;}
.card button {border:0; padding:10px; border-radius:8px; font-weight:600; cursor:pointer;}
.card .btn-view {background:#
