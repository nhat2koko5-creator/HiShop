<?php
// FILE: product_detail.php (ĐÃ NÂNG CẤP LÊN BIẾN THỂ ĐỘNG)
require_once 'client/layouts/header.php';

// 1. Lấy ID sản phẩm
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($product_id <= 0) {
    echo "<div class='container'><p>Sản phẩm không tồn tại.</p></div>";
    require_once 'client/layouts/footer.php';
    exit;
}

// 2. (SỬA LẠI) Truy vấn thông tin SẢN PHẨM GỐC
// (Bỏ 'gia' và 'so_luong' vì giờ chúng nằm trong biến thể)
$stmt = $pdo->prepare("SELECT id, ten, hinh_anh, trang_thai, danh_muc_id, mo_ta, mo_ta_chi_tiet FROM san_pham WHERE id = ?");
$stmt->execute([$product_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    echo "<div class='container'><p>Không tìm thấy sản phẩm.</p></div>";
    require_once 'client/layouts/footer.php';
    exit;
}

// 3. (MỚI) LẤY TẤT CẢ BIẾN THỂ
$stmt_variants = $pdo->prepare("
    SELECT * FROM bien_the_san_pham 
    WHERE san_pham_id = ? 
    ORDER BY gia ASC
");
$stmt_variants->execute([$product_id]);
$variants = $stmt_variants->fetchAll(PDO::FETCH_ASSOC);

// 4. (MỚI) XỬ LÝ DỮ LIỆU BIẾN THỂ CHO PHP VÀ JS
$available_colors = [];
$available_ssds = [];
$variants_js_data = []; // Dữ liệu để truyền cho JavaScript
$base_price = 0;
$total_stock = 0;

if (!empty($variants)) {
    // Lấy giá thấp nhất làm giá "khởi điểm"
    $base_price = $variants[0]['gia']; 
    
    foreach ($variants as $variant) {
        // Lấy danh sách tùy chọn duy nhất
        $available_colors[$variant['mau_sac']] = $variant['mau_sac'];
        $available_ssds[$variant['dung_luong_ssd']] = $variant['dung_luong_ssd'];
        
        // Tính tổng tồn kho
        $total_stock += $variant['so_luong_ton'];

        // (MỚI) Tạo một "key" để JS có thể tra cứu
        // Ví dụ: "Đen|512GB" -> { id: 5, gia: 26000000, ... }
        $key = $variant['mau_sac'] . '|' . $variant['dung_luong_ssd'];
        $variants_js_data[$key] = [
            'id' => $variant['id'], // Đây là ID của biến thể
            'gia' => $variant['gia'],
            'so_luong_ton' => $variant['so_luong_ton'],
            'hinh_anh' => $variant['hinh_anh'] // (Tùy chọn)
        ];
    }
} else {
    // (Dự phòng nếu sản phẩm chưa có biến thể)
    $base_price = $product['gia'] ?? 0; // Lấy giá cũ nếu có
}

// 5. (GIỮ NGUYÊN) Lấy tên danh mục
$category = 'Không xác định';
if (!empty($product['danh_muc_id'])) {
    $stmt_cat = $pdo->prepare("SELECT ten FROM danh_muc WHERE id = ?");
    $stmt_cat->execute([$product['danh_muc_id']]);
    $cat_name = $stmt_cat->fetchColumn();
    if ($cat_name) $category = $cat_name;
}

// 6. (GIỮ NGUYÊN) Lấy thông số kỹ thuật
$stmt_specs = $pdo->prepare("
    SELECT ts.man_hinh, ts.o_cung, ts.cpu, ts.gpu, ts.ram
    FROM san_pham_thong_so spts
    JOIN thong_so ts ON spts.thong_so_id = ts.id
    WHERE spts.san_pham_id = ?
");
$stmt_specs->execute([$product_id]);
$specs = $stmt_specs->fetch(PDO::FETCH_ASSOC);

// 7. (GIỮ NGUYÊN) Lấy sản phẩm liên quan
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

// 8. (GIỮ NGUYÊN) Hàm và đường dẫn ảnh
function price_format($n) {
    return number_format($n, 0, ',', '.') . '₫';
}
$img_folder = 'assets/img/products';
$default_img = 'assets/img/no-image.png';
$img_path = (!empty($product['hinh_anh'])) ? $img_folder . '/' . $product['hinh_anh'] : $default_img;
if (!file_exists($img_path)) $img_path = $default_img;
?>

<div class="container">
  <div class="static-page-header">
  <div class="breadcrumb">
    <a href="index.php?page=home">Trang chủ</a> ›
    <a href="index.php?page=product_list&cat=<?= htmlspecialchars($product['danh_muc_id'] ?? '') ?>"><?= htmlspecialchars($category) ?></a> ›
    <span class="current"><?= htmlspecialchars($product['ten']) ?></span>
  </div>
</div>
  <div class="grid">
    <div class="image-panel">
      <div class="image-container">
        <img src="<?= htmlspecialchars($img_path) ?>" alt="<?= htmlspecialchars($product['ten']) ?>" id="main-product-image">
      </div>
      <div class="stock-info" id="stock-status">
        <?= ($total_stock > 0) ? 'Vui lòng chọn tùy chọn' : '<span class="out">Hết hàng</span>' ?>
      </div>
    </div>

    <div class="info">
      <h1><?= htmlspecialchars($product['ten']) ?></h1>
      
     <div class="price-product">
        <div class="current" id="product-price">
            <?= ($base_price > 0) ? price_format($base_price) : 'Liên hệ' ?>
        </div>
        
        <?php if (!empty($variants) && count($variants) > 1): ?>
          <span class="price-note" id="price-note-label"></span>
        <?php endif; ?>
      </div>

      <?php if (!empty($product['mo_ta'])): ?>
        <div class="short-desc"><?= nl2br(htmlspecialchars($product['mo_ta'])) ?></div>
      <?php endif; ?>

      <?php if (!empty($variants)): ?>
        
        <div class="option-group">
          <h4>Màu sắc</h4>
          <div class="option-box" id="colorOptions">
            <?php foreach ($available_colors as $color): ?>
              <div class="option" data-group="color" data-value="<?= htmlspecialchars($color) ?>"><?= htmlspecialchars($color) ?></div>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="option-group">
          <h4>SSD</h4>
          <div class="option-box" id="ssdOptions">
             <?php foreach ($available_ssds as $ssd): ?>
              <div class="option" data-group="ssd" data-value="<?= htmlspecialchars($ssd) ?>"><?= htmlspecialchars($ssd) ?></div>
            <?php endforeach; ?>
          </div>
        </div>

      <?php else: ?>
        <p><em>Sản phẩm này hiện chưa có tùy chọn cụ thể.</em></p>
      <?php endif; ?>

      <div class="actions">
        <button class="btn btn-primary" id="addCartBtn" data-id="<?= $product['id'] ?>" disabled>🛒 Thêm vào giỏ</button>
        <button class="btn btn-ghost" id="buyNowBtn" data-id="<?= $product['id'] ?>" disabled>🛍️ Mua ngay</button>
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
// (MỚI) Pass dữ liệu biến thể từ PHP sang JS
const variantsData = <?= json_encode($variants_js_data) ?>;
const productId = <?= $product['id'] ?>;
const defaultImg = "<?= htmlspecialchars($img_path) ?>";
const imgFolder = "<?= $img_folder ?>";
const priceNoteEl = document.getElementById("price-note-label");

// (GIỮ NGUYÊN) Logic cho Tabs
document.querySelectorAll(".tab-btn").forEach(btn => {
  btn.addEventListener("click", function(){
    document.querySelectorAll(".tab-btn").forEach(b=>b.classList.remove("active"));
    document.querySelectorAll(".tab-content").forEach(c=>c.classList.remove("active"));
    this.classList.add("active");
    document.getElementById(this.dataset.tab).classList.add("active");
  });
});

// (MỚI) Logic chọn biến thể
let selectedColor = null;
let selectedSSD = null;
let currentSelectedVariant = null; // Sẽ lưu trữ {id, gia, ...}

const buyNowBtn = document.getElementById("buyNowBtn");
const addCartBtn = document.getElementById("addCartBtn");
const priceEl = document.getElementById("product-price");
const stockEl = document.getElementById("stock-status");
const mainImageEl = document.getElementById("main-product-image");

// HÀM MỚI (An toàn hơn)
function formatPrice(n) {
    // 1. Chuyển đổi n (có thể là chuỗi) thành SỐ
    const number = Number(n); 
    
    // 2. Kiểm tra nếu không phải là số
    if (isNaN(number)) return 'Liên hệ';

    // 3. Sử dụng toLocaleString() trên SỐ
    return number.toLocaleString('vi-VN') + '₫';
}

// (MỚI) Hàm kiểm tra và cập nhật giao diện
// (MỚI) Hàm kiểm tra và cập nhật giao diện
function checkSelections() {
  // 1. Reset nút và thông tin
  buyNowBtn.disabled = true;
  addCartBtn.disabled = true;
  currentSelectedVariant = null;

  // (MỚI) Hiển thị lại "Giá từ" nếu nó tồn tại
  if (priceNoteEl) priceNoteEl.style.display = 'inline';

  // 2. Chỉ tiếp tục nếu đã chọn đủ
  if (!selectedColor || !selectedSSD) {
    return;
  }

  // 3. Tạo key và tìm biến thể
  const variantKey = selectedColor + '|' + selectedSSD;
  const variant = variantsData[variantKey];

  if (variant) {
    // 4. TÌM THẤY -> Cập nhật giao diện
    priceEl.textContent = formatPrice(variant.gia);

    // (MỚI) Ẩn "Giá từ" đi
    if (priceNoteEl) priceNoteEl.style.display = 'none';

    if (variant.so_luong_ton > 0) {
      stockEl.textContent = "Còn " + variant.so_luong_ton + " sản phẩm";
      stockEl.className = 'stock-info';
      buyNowBtn.disabled = false;
      addCartBtn.disabled = false;
      currentSelectedVariant = variant; // Lưu lại biến thể hợp lệ
    } else {
      stockEl.textContent = "Hết hàng";
      stockEl.className = 'stock-info out';
    }

    // Cập nhật ảnh (nếu có)
    if (variant.hinh_anh) {
        mainImageEl.src = imgFolder + '/' + variant.hinh_anh;
    } else {
        mainImageEl.src = defaultImg;
    }

  } else {
    // 5. KHÔNG TÌM THẤY (ví dụ: Màu Đen + 1TB không có)
    stockEl.textContent = "Tùy chọn không có sẵn";
    stockEl.className = 'stock-info out';

    // (MỚI) Ẩn "Giá từ" đi
    if (priceNoteEl) priceNoteEl.style.display = 'none';
  }
}

// (MỚI) Gán sự kiện cho các .option
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

// (SỬA LẠI) Nút Mua ngay
buyNowBtn.addEventListener("click", function(){
  if (!currentSelectedVariant) return;
  
  // (MỚI) Lấy ID của BIẾN THỂ đã chọn
  const variantId = currentSelectedVariant.id;
  
  // (MỚI) Chuyển hướng đến trang checkout với luồng "Mua ngay"
  // Chúng ta truyền `action=buy_now` và `variant_id`
  window.location.href = `index.php?page=checkout&action=buy_now&variant_id=${variantId}`;
});

// (SỬA LẠI) Thêm vào giỏ
addCartBtn.addEventListener("click", function(){
  if (!currentSelectedVariant) return;

  const body = new URLSearchParams();
  body.append('action', 'add');
  
  // (QUAN TRỌNG) Gửi 2 ID
  body.append('id', productId); // ID sản phẩm gốc
  body.append('variant_id', currentSelectedVariant.id); // ID biến thể
  body.append('quantity', 1);

  fetch('cart-handler.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: body.toString() // Gửi dữ liệu mới
  })
  .then(res => res.json())
  .then(data => {
    if (data.status === "success") {
      showPopup('🛒 Sản phẩm đã được thêm vào giỏ hàng!');
      if (typeof updateCartIconCount === "function") {
        updateCartIconCount(data.totalItems);
      }
    } else {
      showPopup("Lỗi: " + data.message, true);
    }
  })
  .catch(() => showPopup('Lỗi kết nối. Vui lòng thử lại.', true));
});

// (GIỮ NGUYÊN) Popup nhỏ
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