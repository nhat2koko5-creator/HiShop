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
$stmt = $pdo->prepare("SELECT id, ten, gia, so_luong, hinh_anh, trang_thai, danh_muc_id FROM san_pham WHERE id = ?");
$stmt->execute([$product_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$product) {
    echo "<div class='container'><p>Không tìm thấy sản phẩm.</p></div>";
    require_once 'client/layouts/footer.php';
    exit;
}

// Lấy thông số kỹ thuật qua bảng trung gian
$stmt_specs = $pdo->prepare("
    SELECT ts.man_hinh, ts.o_cung, ts.cpu, ts.gpu, ts.ram
    FROM san_pham_thong_so spts
    JOIN thong_so ts ON ts.id = spts.thong_so_id
    WHERE spts.san_pham_id = ?
    LIMIT 1
");
$stmt_specs->execute([$product_id]);
$specs = $stmt_specs->fetch(PDO::FETCH_ASSOC);

// Lấy sản phẩm liên quan
$stmt_related = $pdo->prepare("SELECT id, ten, gia, hinh_anh FROM san_pham WHERE danh_muc_id = ? AND id != ? LIMIT 4");
$stmt_related->execute([$product['danh_muc_id'], $product_id]);
$related_products = $stmt_related->fetchAll(PDO::FETCH_ASSOC);

function format_price($price) {
    return number_format($price, 0, ',', '.') . '₫';
}

$img_folder = 'assets/img/products';
$default_img = 'assets/img/no-image.png';
$img_path = (!empty($product['hinh_anh']) && file_exists($img_folder . '/' . $product['hinh_anh']))
    ? $img_folder . '/' . $product['hinh_anh']
    : $default_img;
?>

<style>
.container{max-width:1200px;margin:40px auto;padding:0 20px;}
.page-grid{display:grid;grid-template-columns:500px 1fr;gap:40px;}
@media(max-width:900px){.page-grid{grid-template-columns:1fr;gap:20px;}}
.product-img{background:#fff;border-radius:12px;padding:24px;box-shadow:0 6px 24px rgba(0,0,0,0.05);display:flex;align-items:center;justify-content:center;}
.product-img img{max-width:100%;max-height:450px;object-fit:contain;}
.product-info h1{font-size:26px;margin-bottom:12px;color:#111;}
.price{font-size:22px;font-weight:700;color:#007bff;margin-bottom:14px;}
.status{color:#555;margin-bottom:16px;}
.btn-primary{
  background:#0f62fe;
  color:#fff;
  border:none;
  padding:10px 18px;
  border-radius:8px;
  cursor:pointer;
  font-weight:600;
  transition:background 0.3s ease;
}
.btn-primary:hover{background:#0043ce;}
.tabs{margin-top:30px;}
.tab-buttons{display:flex;gap:20px;border-bottom:2px solid #eee;margin-bottom:20px;}
.tab-buttons button{
  background:none;
  border:none;
  font-size:16px;
  font-weight:600;
  padding:10px 0;
  cursor:pointer;
  color:#555;
  position:relative;
}
.tab-buttons button.active{
  color:#0f62fe;
}
.tab-buttons button.active::after{
  content:'';
  position:absolute;
  bottom:-2px;
  left:0;
  width:100%;
  height:2px;
  background:#0f62fe;
}
.tab-content{display:none;}
.tab-content.active{display:block;}
.spec-table table{width:100%;border-collapse:collapse;}
.spec-table td{padding:10px 8px;border-bottom:1px solid #f1f1f1;}
.spec-table td:first-child{font-weight:600;width:160px;color:#333;}
.related-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:20px;margin-top:30px;}
@media(max-width:900px){.related-grid{grid-template-columns:repeat(2,1fr);}}
.card{background:#fff;border-radius:10px;box-shadow:0 3px 10px rgba(0,0,0,0.05);padding:12px;text-align:center;}
.card img{height:110px;object-fit:contain;margin-bottom:8px;}
.card .name{font-size:14px;margin-bottom:6px;}
.card .price{font-size:14px;color:#007bff;font-weight:600;}
</style>

<div class="container">
  <div class="page-grid">
    <!-- Ảnh sản phẩm -->
    <div class="product-img">
      <img src="<?= htmlspecialchars($img_path) ?>" alt="<?= htmlspecialchars($product['ten']) ?>">
    </div>

    <!-- Thông tin -->
    <div class="product-info">
      <h1><?= htmlspecialchars($product['ten']) ?></h1>
      <div class="price"><?= format_price($product['gia']) ?></div>
      <div class="status">
        Tình trạng: <?= $product['trang_thai'] ? 'Còn hàng' : 'Hết hàng' ?><br>
        Kho: <?= (int)$product['so_luong'] ?>
      </div>

      <!-- Giữ nguyên button mẫu cũ -->
      <form method="post" action="index.php?page=cart&action=add">
        <input type="hidden" name="id" value="<?= $product['id'] ?>">
        <input type="number" name="qty" value="1" min="1" max="<?= $product['so_luong'] ?>" style="width:60px;padding:5px;margin-right:10px;">
        <button type="submit" class="btn-primary">Thêm vào giỏ hàng</button>
      </form>

      <!-- Tabs -->
      <div class="tabs">
        <div class="tab-buttons">
          <button class="active" onclick="showTab('spec')">Thông số kỹ thuật</button>
          <button onclick="showTab('desc')">Mô tả chi tiết</button>
        </div>

        <div id="tab-spec" class="tab-content active spec-table">
          <?php if ($specs): ?>
          <table>
            <tr><td>CPU</td><td><?= htmlspecialchars($specs['cpu'] ?? 'Đang cập nhật') ?></td></tr>
            <tr><td>GPU</td><td><?= htmlspecialchars($specs['gpu'] ?? 'Đang cập nhật') ?></td></tr>
            <tr><td>RAM</td><td><?= htmlspecialchars($specs['ram'] ?? 'Đang cập nhật') ?></td></tr>
            <tr><td>Màn hình</td><td><?= htmlspecialchars($specs['man_hinh'] ?? 'Đang cập nhật') ?></td></tr>
            <tr><td>Ổ cứng</td><td><?= htmlspecialchars($specs['o_cung'] ?? 'Đang cập nhật') ?></td></tr>
          </table>
          <?php else: ?>
            <p>Thông số kỹ thuật đang được cập nhật.</p>
          <?php endif; ?>
        </div>

        <div id="tab-desc" class="tab-content">
          <p>Đang cập nhật...</p>
        </div>
      </div>
    </div>
  </div>

  <!-- Sản phẩm liên quan -->
  <h3 style="margin-top:40px;">Sản phẩm liên quan</h3>
  <div class="related-grid">
    <?php foreach ($related_products as $r):
      $r_img = (!empty($r['hinh_anh']) && file_exists($img_folder . '/' . $r['hinh_anh']))
        ? $img_folder . '/' . $r['hinh_anh']
        : $default_img;
    ?>
    <div class="card">
      <img src="<?= htmlspecialchars($r_img) ?>" alt="<?= htmlspecialchars($r['ten']) ?>">
      <div class="name"><?= htmlspecialchars($r['ten']) ?></div>
      <div class="price"><?= format_price($r['gia']) ?></div>
      <a href="index.php?page=product_detail&id=<?= $r['id'] ?>">
        <button class="btn-primary">Xem chi tiết</button>
      </a>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<script>
function showTab(tab) {
  document.querySelectorAll('.tab-buttons button').forEach(btn => btn.classList.remove('active'));
  document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));

  document.querySelector(`[onclick="showTab('${tab}')"]`).classList.add('active');
  document.getElementById(`tab-${tab}`).classList.add('active');
}
</script>

<?php require_once 'client/layouts/footer.php'; ?>
