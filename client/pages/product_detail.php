<?php
require_once 'client/layouts/header.php';

// Lấy id sản phẩm
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($product_id <= 0) {
    echo "<div class='container'><p>Sản phẩm không tồn tại.</p></div>";
    require_once 'client/layouts/footer.php';
    exit;
}

// Truy vấn thông tin sản phẩm (có cả mô tả ngắn và mô tả chi tiết)
$stmt = $pdo->prepare("SELECT id, ten, gia, so_luong, hinh_anh, trang_thai, danh_muc_id, mo_ta, mo_ta_chi_tiet FROM san_pham WHERE id = ?");
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

// Lấy thông số kỹ thuật
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
$img_path = (!empty($product['hinh_anh'])) ? $img_folder . '/' . $product['hinh_anh'] : $default_img;
if (!file_exists($img_path)) $img_path = $default_img;
?>

<div class="container">
  <div class="breadcrumb">
    <a href="index.php?page=home">Trang chủ</a> ›
    <a href="index.php?page=product_list&cat=<?= htmlspecialchars($product['danh_muc_id'] ?? '') ?>"><?= htmlspecialchars($category) ?></a> ›
    <span class="current"><?= htmlspecialchars($product['ten']) ?></span>
  </div>

  <div class="grid">
    <div class="image-panel">
      <div class="image-container">
        <img src="<?= htmlspecialchars($img_path) ?>" alt="<?= htmlspecialchars($product['ten']) ?>">
      </div>
      <div class="stock-info">
        Số lượng còn: 
        <?= (int)$product['so_luong'] > 0 ? $product['so_luong'] : '<span class="out">Hết hàng</span>' ?>
      </div>
    </div>

    <div class="info">
      <h1><?= htmlspecialchars($product['ten']) ?></h1>
      <div class="price"><div class="current"><?= price_format($product['gia']) ?></div></div>

      <!-- Mô tả ngắn -->
      <?php if (!empty($product['mo_ta'])): ?>
        <div class="short-desc"><?= nl2br(htmlspecialchars($product['mo_ta'])) ?></div>
      <?php endif; ?>

      <!-- Chọn màu sắc -->
      <div class="option-group">
        <h4>Màu sắc</h4>
        <div class="option-box" id="colorOptions">
          <div class="option" data-value="Đen">Đen</div>
          <div class="option" data-value="Trắng">Trắng</div>
          <div class="option" data-value="Bạc">Bạc</div>
        </div>
      </div>

      <!-- Chọn SSD -->
      <div class="option-group">
        <h4>SSD</h4>
        <div class="option-box" id="ssdOptions">
          <div class="option" data-value="256GB">256GB</div>
          <div class="option" data-value="512GB">512GB</div>
          <div class="option" data-value="1TB">1TB</div>
        </div>
      </div>

      <div class="actions">
        <button class="btn btn-primary" id="addCartBtn" data-id="<?= $product['id'] ?>" disabled>🛒 Thêm vào giỏ</button>
        <button class="btn btn-ghost" id="buyNowBtn" data-id="<?= $product['id'] ?>" disabled>🛍️ Mua ngay</button>
      </div>
    </div>
  </div>

  <!-- Tabs -->
  <div class="tab-container">
    <div class="tab-buttons">
      <button class="tab-btn active" data-tab="specs">Thông số kỹ thuật</button>
      <button class="tab-btn" data-tab="desc">Mô tả chi tiết</button>
    </div>
    <div id="specs" class="tab-content active">
      <?php if ($specs): ?>
        <ul>
          <li><strong>Màn hình:</strong> <?= htmlspecialchars($specs['man_hinh']) ?></li>
          <li><strong>Ổ cứng:</strong> <?= htmlspecialchars($specs['o_cung']) ?></li>
          <li><strong>CPU:</strong> <?= htmlspecialchars($specs['cpu']) ?></li>
          <li><strong>GPU:</strong> <?= htmlspecialchars($specs['gpu']) ?></li>
          <li><strong>RAM:</strong> <?= htmlspecialchars($specs['ram']) ?></li>
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

  <!-- Related -->
  <div class="section">
    <h2>Sản phẩm liên quan</h2>
    <div class="related-grid">
      <?php foreach ($related_products as $r): 
        $r_img = (!empty($r['hinh_anh']) && file_exists($img_folder . '/' . $r['hinh_anh'])) ? $img_folder . '/' . $r['hinh_anh'] : $default_img;
      ?>
      <div class="card">
        <img src="<?= htmlspecialchars($r_img) ?>" alt="<?= htmlspecialchars($r['ten']) ?>">
        <div class="name"><?= htmlspecialchars($r['ten']) ?></div>
        <div class="price"><?= price_format($r['gia']) ?></div>
        <div class="spec"><?= htmlspecialchars($r['cpu']) ?> | <?= htmlspecialchars($r['ram']) ?></div>
        <div class="card-actions">
          <button class="btn-ghost" onclick="window.location.href='index.php?page=product_detail&id=<?= $r['id'] ?>'">👁️ Xem chi tiết</button>
          <button class="btn-primary" onclick="window.location.href='index.php?page=checkout&id=<?= $r['id'] ?>'">🛍️ Mua ngay</button>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<script>
// Tabs
document.querySelectorAll(".tab-btn").forEach(btn => {
  btn.addEventListener("click", function(){
    document.querySelectorAll(".tab-btn").forEach(b=>b.classList.remove("active"));
    document.querySelectorAll(".tab-content").forEach(c=>c.classList.remove("active"));
    this.classList.add("active");
    document.getElementById(this.dataset.tab).classList.add("active");
  });
});

// Chọn màu & SSD
let selectedColor = null;
let selectedSSD = null;
const buyNowBtn = document.getElementById("buyNowBtn");
const addCartBtn = document.getElementById("addCartBtn");

function checkSelections() {
  const ready = selectedColor && selectedSSD;
  buyNowBtn.disabled = !ready;
  addCartBtn.disabled = !ready;
}

document.querySelectorAll("#colorOptions .option").forEach(opt => {
  opt.addEventListener("click", () => {
    document.querySelectorAll("#colorOptions .option").forEach(o => o.classList.remove("active"));
    opt.classList.add("active");
    selectedColor = opt.dataset.value;
    checkSelections();
  });
});

document.querySelectorAll("#ssdOptions .option").forEach(opt => {
  opt.addEventListener("click", () => {
    document.querySelectorAll("#ssdOptions .option").forEach(o => o.classList.remove("active"));
    opt.classList.add("active");
    selectedSSD = opt.dataset.value;
    checkSelections();
  });
});

// Nút Mua ngay
buyNowBtn.addEventListener("click", function(){
  if (!selectedColor || !selectedSSD) return;
  const id = this.dataset.id;
  const color = encodeURIComponent(selectedColor);
  const ssd = encodeURIComponent(selectedSSD);
  window.location.href = `index.php?page=checkout&id=${id}&color=${color}&ssd=${ssd}`;
});

// Thêm vào giỏ
addCartBtn.addEventListener("click", function(){
  if (!selectedColor || !selectedSSD) return;
  const id = this.dataset.id;

  fetch('cart-handler.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `action=add&id=${id}&quantity=1&color=${encodeURIComponent(selectedColor)}&ssd=${encodeURIComponent(selectedSSD)}`
  })
  .then(res => res.json())
  .then(data => {
    if (data.status === "success") {
      showPopup('🛒 Sản phẩm đã được thêm vào giỏ hàng!');

      // Nếu icon ở header có hàm cập nhật
      if (typeof updateCartIconCount === "function") {
        updateCartIconCount(data.totalItems);
      }

    } else {
      showPopup("Lỗi: " + data.message, true);
    }
  })
  .catch(() => showPopup('Lỗi kết nối. Vui lòng thử lại.', true));
});

// Popup nhỏ
function showPopup(msg) {
  const el = document.createElement('div');
  el.textContent = msg;
  Object.assign(el.style, {
    position:'fixed', bottom:'30px', right:'30px',
    background:'#0f62fe', color:'#fff', padding:'12px 20px',
    borderRadius:'12px', boxShadow:'0 4px 10px rgba(0,0,0,0.2)',
    zIndex:'9999', transition:'opacity 0.5s'
  });
  document.body.appendChild(el);
  setTimeout(()=>el.style.opacity='0',2000);
  setTimeout(()=>el.remove(),2500);
}
</script>

<?php require_once 'client/layouts/footer.php'; ?>
