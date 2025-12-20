<?php
$featuredProducts = getFeaturedProducts($pdo);
$discountProducts = getDiscountProducts($pdo);
?>
<link rel="stylesheet" href="assets/css/client/home.css">
<link rel="stylesheet" href="assets/css/client/product-list.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
<section class="hero-slider-section">
    <div class="swiper hero-swiper">
        <div class="swiper-wrapper">
            
            <div class="swiper-slide">
                <a href="index.php?page=product_list&category_id=1" class="banner-link">
                    <img src="assets/img/banner/banner1.png" alt="Gaming">
                    
                    <div class="simple-content content-center">
                        <h2 class="simple-title">GIẢI PHÁP CÔNG NGHỆ<br>HIỆN ĐẠI & ĐÁNG TIN CẬY</h2>
                        <p class="simple-desc">Hỗ trợ doanh nghiệp và cá nhân tiếp cận sản phẩm tốt nhất</p>
                        <span class="btn-basic primary">MUA NGAY</span>
                    </div>
                </a>
            </div>

            <div class="swiper-slide">
                <a href="index.php?page=product_list" class="banner-link">
                    <img src="assets/img/banner/banner2.png" alt="Công nghệ">
                    
                    <div class="simple-content content-right">
                        <h2 class="simple-title">CÔNG NGHỆ<br>DẪN ĐẦU</h2>
                        <p class="simple-desc">Trải nghiệm sức mạnh vượt trội</p>
                        <div class="btn-group">
                            <span class="btn-basic primary">Xem sản phẩm</span>
                            <span class="btn-basic outline">Liên hệ tư vấn</span>
                        </div>
                    </div>
                </a>
            </div>

            <div class="swiper-slide">
                <a href="index.php?page=product_list&category_id=2" class="banner-link">
                    <img src="assets/img/banner/banner3.png" alt="Đồ họa">
                    
                    <div class="simple-content content-left">
                        <h2 class="simple-title text-yellow">ĐỒ HỌA<br>CHUYÊN NGHIỆP</h2>
                        <p class="simple-desc">Màu sắc chuẩn xác - Sáng tạo không giới hạn</p>
                        <span class="btn-basic primary">Xem Chi Tiết</span>
                    </div>
                </a>
            </div>

            <div class="swiper-slide">
                <a href="index.php?page=product_list&category_id=4" class="banner-link">
                    <img src="assets/img/banner/banner4.png" alt="Học tập">
                    
                    <div class="simple-content content-right">
                        <h2 class="simple-title">BACK TO SCHOOL<br>ƯU ĐÃI SINH VIÊN</h2>
                        <p class="simple-desc">Giảm thêm 5% - Tặng Balo xịn</p>
                        <span class="btn-basic primary">Săn Deal Ngay</span>
                    </div>
                </a>
            </div>

            <div class="swiper-slide">
                <a href="index.php?page=product_list&category_id=5" class="banner-link">
                    <img src="assets/img/banner/banner5.png" alt="Doanh nhân">
                    
                    <div class="simple-content content-right">
                        <h2 class="simple-title">KHẲNG ĐỊNH ĐẲNG CẤP<br>DOANH NHÂN</h2>
                        <p class="simple-desc">Thiết kế tinh xảo hiệu năng vượt trội</p>
                        <span class="btn-basic primary">Xem Ngay</span>
                    </div>
                </a>
            </div>

        </div>
        <div class="swiper-pagination"></div>
        <div class="swiper-button-next"></div>
        <div class="swiper-button-prev"></div>
    </div>
</section>

<section class="why-us-section container">
    <h2 class="section-title">Tại sao chọn chúng tôi?</h2>
    <div class="feature-grid">
        <div class="feature-card">
            <div class="feature-icon">✔</div>
            <h3>Sản phẩm uy tín</h3>
            <p>Cam kết 100% hàng chính hãng.</p>
        </div>
        <div class="feature-card">
            <div class="feature-icon">💻</div>
            <h3>Đa dạng Laptop</h3>
            <p>Đầy đủ các dòng máy mới nhất.</p>
        </div>
        <div class="feature-card">
            <div class="feature-icon">💰</div>
            <h3>Giá cả cạnh tranh</h3>
            <p>Luôn có ưu đãi tốt nhất thị trường.</p>
        </div>
        <div class="feature-card">
            <div class="feature-icon">💬</div>
            <h3>Hỗ trợ 24/7</h3>
            <p>Giải đáp mọi thắc mắc của bạn.</p>
        </div>
    </div>
</section>
<section class="category-section" style="background-color: #f9fafb;">
    <div class="container">
        <span class="section-subtitle">DANH MỤC SẢN PHẨM</span>
        <h2 class="section-title">Khám phá theo nhu cầu</h2>

        <div class="category-pills">
            <a href="index.php?page=product_list&category_id=5" class="modern-cat-card">
                <div class="cat-icon-placeholder"><i class="fa-solid fa-briefcase"></i></div>
                <span>Doanh Nhân</span>
            </a>
            
            <a href="index.php?page=product_list&category_id=2" class="modern-cat-card">
                <div class="cat-icon-placeholder"><i class="fa-solid fa-pen-nib"></i></div>
                <span>Đồ Họa & Kỹ Thuật</span>
            </a>

            <a href="index.php?page=product_list&category_id=1" class="modern-cat-card">
                <div class="cat-icon-placeholder" style="color: #ec4899;"><i class="fa-solid fa-gamepad"></i></div>
                <span>Gaming Gear</span>
            </a>

            <a href="index.php?page=product_list&category_id=4" class="modern-cat-card">
                <div class="cat-icon-placeholder"><i class="fa-solid fa-graduation-cap"></i></div>
                <span>Học Tập, Sinh Viên</span>
            </a>

            <a href="index.php?page=product_list&category_id=3" class="modern-cat-card">
                <div class="cat-icon-placeholder"><i class="fa-solid fa-building"></i></div>
                <span>Văn Phòng</span>
            </a>
        </div>
    </div>
</section>
<section class="product-section container">
    <span class="section-subtitle">SẢN PHẨM NỔI BẬT</span>
    <h2 class="section-title">Laptop được yêu thích nhất</h2>

    <div class="product-carousel-wrapper">
        <div class="swiper featured-carousel">
            <div class="swiper-wrapper">

                <?php if (!empty($featuredProducts)): ?>
                    <?php foreach ($featuredProducts as $sp): ?>
                        <?php
                        // 1. Xử lý giảm giá
                        $is_discount = isset($sp['gia_da_giam']) && $sp['gia_da_giam'] < $sp['gia'];
                        $percent = 0;
                        if ($is_discount && $sp['gia'] > 0) {
                            $percent = round((($sp['gia'] - $sp['gia_da_giam']) / $sp['gia']) * 100);
                        }

                        // 2. Xử lý ảnh
                        $img_path = 'assets/img/products/' . htmlspecialchars($sp['hinh_anh']);
                        if (empty($sp['hinh_anh']) || !file_exists($img_path)) {
                            $img_path = 'assets/img/no-image.png';
                        }
                        ?>

                        <div class="swiper-slide">
                            <div class="product-card">
                                <?php if ($percent > 0): ?>
                                    <div class="product-sale-tag">-<?= $percent ?>%</div>
                                <?php endif; ?>

                                <div class="product-image">
                                    <a href="index.php?page=product_detail&id=<?= $sp['id']; ?>">
                                        <img src="<?= $img_path ?>" alt="<?= htmlspecialchars($sp['ten']); ?>">
                                    </a>
                                </div>

                                <div class="card-content">
                                    <h3 class="card-title"><?= htmlspecialchars($sp['ten']); ?></h3>

                                    <?php 
                                        $cpu_show = $sp['cpu'] ?? null;
                                        $ram_show = $sp['ram'] ?? null;

                                        if (empty($cpu_show) || empty($ram_show)) {
                                            $stmt_specs = $pdo->prepare("SELECT ts.ten, ts.gia_tri FROM thong_so ts JOIN san_pham_thong_so spts ON spts.thong_so_id = ts.id WHERE spts.san_pham_id = ?");
                                            $stmt_specs->execute([$sp['id']]);
                                            $all_specs = $stmt_specs->fetchAll(PDO::FETCH_ASSOC);

                                            foreach ($all_specs as $s) {
                                                $ten = mb_strtolower($s['ten'], 'UTF-8');
                                                if (empty($cpu_show) && preg_match('/cpu|vi xử lý|chip/u', $ten)) $cpu_show = $s['gia_tri'];
                                                if (empty($ram_show) && preg_match('/ram|bộ nhớ/u', $ten)) $ram_show = $s['gia_tri'];
                                            }
                                            // Fallback
                                            if (empty($cpu_show) && isset($all_specs[0])) $cpu_show = $all_specs[0]['gia_tri'];
                                            if (empty($ram_show) && isset($all_specs[1])) $ram_show = $all_specs[1]['gia_tri'];
                                        }
                                    ?>
                                    
                                    <div class="product-specs">
                                        <?php if ($cpu_show): ?>
                                            <span class="spec-pill"><?= htmlspecialchars($cpu_show) ?></span>
                                        <?php endif; ?>
                                        <?php if ($cpu_show && $ram_show): ?><span style="color:#ccc">|</span><?php endif; ?>
                                        <?php if ($ram_show): ?>
                                            <span class="spec-pill"><?= htmlspecialchars($ram_show) ?></span>
                                        <?php endif; ?>
                                        <?php if (!$cpu_show && !$ram_show): ?><span style="height:24px; display:block"></span><?php endif; ?>
                                    </div>

                                    <div class="card-price">
                                        <?php if ($is_discount): ?>
                                            <span class="card-price-old"><?= number_format($sp['gia']); ?>₫</span>
                                            <span class="card-price-new"><?= number_format($sp['gia_da_giam']); ?>₫</span>
                                        <?php else: ?>
                                            <span class="card-price-new"><?= number_format($sp['gia']); ?>₫</span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="btn-group-vertical">
                                        <?php if (!empty($sp['variants'])): ?>
                                            <a href="javascript:void(0);" class="btn-view btn-buy-now btn-quick-add"
                                               data-product-id="<?= $sp['id']; ?>"
                                               data-product-name="<?= htmlspecialchars($sp['ten']); ?>"
                                               data-product-image="assets/img/products/<?= htmlspecialchars($sp['hinh_anh']); ?>"
                                               data-variants='<?= htmlspecialchars(json_encode($sp['variants']), ENT_QUOTES, "UTF-8"); ?>'
                                               data-action="buy">
                                                🔥 Mua ngay
                                            </a>
                                            <a href="javascript:void(0);" class="btn-cart btn-quick-add"
                                               title="Thêm vào giỏ"
                                               data-product-id="<?= $sp['id']; ?>"
                                               data-product-name="<?= htmlspecialchars($sp['ten']); ?>"
                                               data-product-image="assets/img/products/<?= htmlspecialchars($sp['hinh_anh']); ?>"
                                               data-variants='<?= htmlspecialchars(json_encode($sp['variants']), ENT_QUOTES, "UTF-8"); ?>'
                                               data-action="add">
                                                🛒
                                            </a>
                                        <?php else: ?>
                                            <a href="index.php?page=checkout&action=buy_now&variant_id=<?= $sp['id']; ?>&quantity=1" class="btn-view btn-buy-now">
                                                🔥 Mua ngay
                                            </a>
                                            <a href="index.php?page=cart&action=add&id=<?= $sp['id']; ?>" class="btn-cart" title="Thêm vào giỏ">
                                                🛒
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="text-align:center; width:100%;">Không có sản phẩm nổi bật nào.</p>
                <?php endif; ?>

            </div>
        </div>

        <div class="swiper-button-prev featured-prev"></div>
        <div class="swiper-button-next featured-next"></div>
    </div>
</section>
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

            <div class="option-group" id="modal-variant-group">
                <h4>Tùy chọn biến thể</h4>
                <div class="option-box variant-combo-box" id="modal-variant-options">
                    </div>
            </div>
            <div class="option-group" id="modal-qty-group">
    <h4>Số lượng</h4>
    <div class="qty-box">
        <button class="qty-btn" id="qty-minus">−</button>
        <input type="number" id="qty-input" value="1" min="1">
        <button class="qty-btn" id="qty-plus">+</button>
    </div>
</div>

        </div>
            <div class="variant-modal-footer">
                <button class="btn btn-outline" id="modal-cancel-btn">Hủy</button>
                <button class="btn btn-primary" id="modal-add-btn" disabled>Thêm vào giỏ</button>
            </div>
        </div>
    </div>
    </section>

<section class="product-section container" id="discount-products">

    <span class="section-subtitle">SẢN PHẨM GIẢM GIÁ</span>
    <h2 class="section-title">Ưu đãi hot trong tuần</h2>

    <div class="product-carousel-wrapper">
        <div class="swiper discount-carousel">
<div class="swiper-wrapper">
    <?php if (!empty($discountProducts)): ?>
        <?php foreach ($discountProducts as $sp): ?>
            <div class="swiper-slide"> <?php
                // Xử lý giá
                $percent   = isset($sp['giam_phan_tram']) ? (int)$sp['giam_phan_tram'] : 0;
                $newPrice  = $sp['gia_da_giam'] ?? $sp['gia'];
                $origPrice = $sp['gia'] ?? 0;
                $is_discount = ($newPrice < $origPrice);

                // Xử lý ảnh
                $img_path = 'assets/img/products/' . htmlspecialchars($sp['hinh_anh']);
                if (empty($sp['hinh_anh']) || !file_exists($img_path)) {
                    $img_path = 'assets/img/no-image.png';
                }
                ?>

                <div class="product-card">
                    <?php if ($percent > 0): ?>
                        <div class="product-sale-tag">-<?= $percent ?>%</div>
                    <?php endif; ?>

                    <div class="product-image">
                        <a href="index.php?page=product_detail&id=<?= $sp['id']; ?>">
                            <img src="<?= $img_path ?>" alt="<?= htmlspecialchars($sp['ten']); ?>">
                        </a>
                    </div>

                    <div class="card-content">
                        <h3 class="card-title"><?= htmlspecialchars($sp['ten']); ?></h3>

                        <?php 
                            $cpu_show = $sp['cpu'] ?? null;
                            $ram_show = $sp['ram'] ?? null;
                            if (empty($cpu_show) || empty($ram_show)) {
                                $stmt_specs = $pdo->prepare("SELECT ts.ten, ts.gia_tri FROM thong_so ts JOIN san_pham_thong_so spts ON spts.thong_so_id = ts.id WHERE spts.san_pham_id = ?");
                                $stmt_specs->execute([$sp['id']]);
                                $all_specs = $stmt_specs->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($all_specs as $s) {
                                    $ten = mb_strtolower($s['ten'], 'UTF-8');
                                    if (empty($cpu_show) && preg_match('/cpu|vi xử lý|chip/u', $ten)) $cpu_show = $s['gia_tri'];
                                    if (empty($ram_show) && preg_match('/ram|bộ nhớ/u', $ten)) $ram_show = $s['gia_tri'];
                                }
                                if (empty($cpu_show) && isset($all_specs[0])) $cpu_show = $all_specs[0]['gia_tri'];
                                if (empty($ram_show) && isset($all_specs[1])) $ram_show = $all_specs[1]['gia_tri'];
                            }
                        ?>
                        <div class="product-specs">
                            <?php if ($cpu_show): ?>
                                <span class="spec-pill"><?= htmlspecialchars($cpu_show) ?></span>
                            <?php endif; ?>
                            <?php if ($cpu_show && $ram_show): ?><span style="color:#ccc">|</span><?php endif; ?>
                            <?php if ($ram_show): ?>
                                <span class="spec-pill"><?= htmlspecialchars($ram_show) ?></span>
                            <?php endif; ?>
                            <?php if (!$cpu_show && !$ram_show): ?><span style="height:24px; display:block"></span><?php endif; ?>
                        </div>

                        <div class="card-price">
                            <?php if ($is_discount): ?>
                                <span class="card-price-old"><?= number_format($origPrice) ?>₫</span>
                                <span class="card-price-new"><?= number_format($newPrice) ?>₫</span>
                            <?php else: ?>
                                <span class="card-price-new"><?= number_format($origPrice) ?>₫</span>
                            <?php endif; ?>
                        </div>

                        <div class="btn-group-vertical">
                            <?php if (!empty($sp['variants'])): ?>
                                <a href="javascript:void(0);" class="btn-view btn-buy-now btn-quick-add"
                                   data-product-id="<?= $sp['id']; ?>"
                                   data-product-name="<?= htmlspecialchars($sp['ten']); ?>"
                                   data-product-image="assets/img/products/<?= htmlspecialchars($sp['hinh_anh']); ?>"
                                   data-variants='<?= htmlspecialchars(json_encode($sp['variants']), ENT_QUOTES, "UTF-8"); ?>'
                                   data-action="buy">
                                    🔥 Mua ngay
                                </a>
                            <?php else: ?>
                                <a href="index.php?page=checkout&action=buy_now&variant_id=<?= $sp['id']; ?>&quantity=1" class="btn-view btn-buy-now">
                                    🔥 Mua ngay
                                </a>
                            <?php endif; ?>

                            <?php if (!empty($sp['variants'])): ?>
                                <a href="javascript:void(0);" class="btn-cart btn-quick-add"
                                   title="Thêm vào giỏ"
                                   data-product-id="<?= $sp['id']; ?>"
                                   data-product-name="<?= htmlspecialchars($sp['ten']); ?>"
                                   data-product-image="assets/img/products/<?= htmlspecialchars($sp['hinh_anh']); ?>"
                                   data-variants='<?= htmlspecialchars(json_encode($sp['variants']), ENT_QUOTES, "UTF-8"); ?>'
                                   data-action="add">
                                    🛒
                                </a>
                            <?php else: ?>
                                <a href="index.php?page=cart&action=add&id=<?= $sp['id']; ?>" class="btn-cart" title="Thêm vào giỏ">
                                    🛒
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div> </div> <?php endforeach; ?>
    <?php else: ?>
        <p style="text-align:center;width:100%;">Không có sản phẩm giảm giá.</p>
<?php endif; ?>
</div> </div> <div class="swiper-button-prev discount-prev"></div>
        <div class="swiper-button-next discount-next"></div>
    </div> </section>
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
<script>
    var swiperDiscount = new Swiper(".discount-carousel", {
        slidesPerView: 4,      // Mặc định hiện 4 sản phẩm
        spaceBetween: 20,      // Khoảng cách giữa các sản phẩm
        loop: true,            // <--- QUAN TRỌNG: Bật chế độ lặp vòng
        grabCursor: true,      // Hiện con trỏ bàn tay khi kéo
        
        // Cấu hình nút bấm
        navigation: {
            nextEl: ".discount-next",
            prevEl: ".discount-prev"
        },
        breakpoints: {
            0: {
                slidesPerView: 1,
            },
            640: {
                slidesPerView: 2,
            },
            768: {
                slidesPerView: 3,
            },
            1024: {
                slidesPerView: 4,
            },
        }
    });
    var swiperFeatured = new Swiper(".featured-carousel", {
        slidesPerView: 4,
        spaceBetween: 20,
        loop: true, // Cuộn vô tận
        grabCursor: true,
        navigation: {
            nextEl: ".featured-next",
            prevEl: ".featured-prev"
        },
        breakpoints: {
            0: { slidesPerView: 1 },
            640: { slidesPerView: 2 },
            768: { slidesPerView: 3 },
            1024: { slidesPerView: 4 },
        }
    });
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const isLoggedIn = <?php echo isset($_SESSION['user_id']) ? 'true' : 'false'; ?>;
    // --- Biến DOM (cho modal) ---
    const modal = document.getElementById('quick-add-modal');
    const modalProductName = document.getElementById('modal-product-name');
    const modalPrice = document.getElementById('modal-product-price');
    const modalStock = document.getElementById('modal-stock-status');
    const modalVariantBox = document.getElementById('modal-variant-options'); // (ĐÃ SỬA) Biến cho combo options
    const modalAddBtn = document.getElementById('modal-add-btn');
    const modalMainImage = document.getElementById('modal-product-main-image');
    
    // --- Biến Trạng Thái (sẽ được reset) ---
    let currentVariants = []; // Dữ liệu JSON từ data-variants
    let currentProductId = null;
    let selectedVariantKey = null; // (MỚI) Lưu key gộp: "Màu|SSD"
    let currentSelectedVariant = null; // {id, gia, ...}
    let selectedQty = 1;
    let currentAction = 'add';
    let originalProductImage = ''; // Lưu ảnh gốc khi mở modal


    // === HÀM 1: MỞ VÀ ĐIỀN DỮ LIỆU VÀO MODAL ===
function openQuickAddModal(btn) {

    selectedQty = 1;
    document.getElementById("qty-input").value = 1;
    selectedVariantKey = null;

    currentAction = btn.dataset.action || 'add';

    if (currentAction === 'buy') {
        modalAddBtn.textContent = "Mua ngay";
    } else {
        modalAddBtn.textContent = "Thêm vào giỏ";
    }

    currentProductId = btn.dataset.productId;
    modalProductName.textContent = btn.dataset.productName;

    originalProductImage = btn.dataset.productImage;
    modalMainImage.src = originalProductImage || 'assets/img/products/no-image.jpg';

    try{
        currentVariants = JSON.parse(btn.dataset.variants);
    }catch(e){
        alert('Lỗi biến thể');
        return;
    }

    modalVariantBox.innerHTML = '';

    currentVariants.forEach(v=>{
        const opt = document.createElement('div');
        opt.className = 'option-combo';

        opt.dataset.key = v.mau_sac + "|" + v.dung_luong_ssd;
        opt.textContent = `${v.mau_sac} (${v.dung_luong_ssd})`;

        if (v.so_luong_ton <= 0) {
            opt.classList.add('disabled');
        }

        modalVariantBox.appendChild(opt);
    });

    modalPrice.textContent = '--';
    modalStock.textContent = 'Vui lòng chọn tùy chọn';
    modal.style.display = 'flex';
}


    // === HÀM 2: ĐÓNG VÀ RESET MODAL ===
    function closeQuickAddModal() {
        modal.style.display = 'none';
        // Reset tất cả
        currentVariants = [];
        currentProductId = null;
        selectedVariantKey = null; // (ĐÃ SỬA)
        currentSelectedVariant = null;
        modalAddBtn.disabled = true;
    }

    // === HÀM 3: KIỂM TRA LỰA CHỌN (Logic chính) ===
    function checkModalSelections() {
        // 1. Reset
        modalAddBtn.disabled = true;
        currentSelectedVariant = null;
        modalMainImage.src = originalProductImage; // Reset ảnh về ảnh gốc

        // 2. Chỉ tiếp tục nếu đã chọn biến thể
        if (!selectedVariantKey) {
            modalPrice.textContent = '--';
            modalStock.textContent = 'Vui lòng chọn tùy chọn';
            modalStock.className = 'stock-info';
            return;
        }

        // 3. Tìm biến thể (dùng key gộp)
        const variant = currentVariants.find(v => {
            const variantKey = v.mau_sac + '|' + v.dung_luong_ssd;
            return variantKey === selectedVariantKey;
        });


        if (variant) {
            // 4. TÌM THẤY -> Cập nhật giao diện
            if (variant.gia_giam && variant.gia_giam < variant.gia) {
                modalPrice.innerHTML =
                    `<span class="old-price" style="text-decoration:line-through;color:#999;margin-right:6px;">
                        ${formatPrice(variant.gia)}
                    </span>
                    <span class="new-price" style="color:#e60000;font-weight:bold;">
                        ${formatPrice(variant.gia_giam)}
                    </span>`;
            } else {
                modalPrice.innerHTML = `<span class="new-price">${formatPrice(variant.gia)}</span>`;
            }

            
            if (variant.so_luong_ton > 0) {
                modalStock.textContent = "Còn " + variant.so_luong_ton + " sản phẩm";
                modalStock.className = 'stock-info';
                modalAddBtn.disabled = false;
                currentSelectedVariant = variant; // Lưu lại
            } else {
                modalStock.textContent = "Hết hàng";
                modalStock.className = 'stock-info out';
            }
            // Cập nhật ảnh (nếu có)
            if (variant.hinh_anh && variant.hinh_anh !== "") {
                modalMainImage.src = `assets/img/products/${variant.hinh_anh}`;
            } else {
                modalMainImage.src = originalProductImage;
            }
             // Cập nhật số lượng tối đa
            document.getElementById('qty-input').max = variant.so_luong_ton;


        } else {
            // 5. KHÔNG TÌM THẤY
            modalPrice.textContent = '--';
            modalStock.textContent = "Tùy chọn không có sẵn";
            modalStock.className = 'stock-info out';
        }
    }

    // === HÀM 4 & 5: GỌI AJAX VÀ HIỂN THỊ POPUP ===
    async function sendCartRequest(action, data) {
        const formData = new URLSearchParams();
        formData.append('action', action);
        for (const key in data) formData.append(key, data[key]);
        try {
            const response = await fetch('cart-handler.php', { method: 'POST', body: formData });
            return await response.json();
        } catch (err) {
            return { status: 'error', message: 'Lỗi kết nối.' };
        }
    }

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
    
    function formatPrice(n) {
        const number = Number(n); 
        if (isNaN(number)) return 'Liên hệ';
        return number.toLocaleString('vi-VN') + '₫';
    }


    // === GÁN SỰ KIỆN ===

    // 1. Gán sự kiện cho tất cả nút "Thêm vào giỏ"
// BẮT SỰ KIỆN CHÍNH XÁC - ĐỘC LẬP SWIPER
document.addEventListener("click", function(e) {
    const btn = e.target.closest(".btn-quick-add");
    if (!btn || !btn.dataset.variants) return; // nếu không có variants → bỏ qua
    e.preventDefault();
    e.stopPropagation();
    openQuickAddModal(btn);
});

    // 2. Gán sự kiện cho các nút đóng modal
    document.getElementById('modal-close-btn').addEventListener('click', closeQuickAddModal);
    document.getElementById('modal-cancel-btn').addEventListener('click', closeQuickAddModal);
    modal.addEventListener('click', e => {
        if (e.target === modal) closeQuickAddModal();
    });

    // 3. (MỚI) Xử lý các nút .option-combo
    modal.addEventListener('click', function(e) {
        // Chỉ xử lý nếu nhấn vào .option-combo và không bị disabled
        if (!e.target.classList.contains('option-combo') || e.target.classList.contains('disabled')) {
            return;
        }

        const key = e.target.dataset.key; // Lấy key gộp

        // Xóa active cho tất cả các nút trong modal variant box
        document.querySelectorAll('.option-combo').forEach(o => o.classList.remove('active'));

        // Lưu key và thêm active mới
        selectedVariantKey = key;
        e.target.classList.add('active');
        
        // Kiểm tra và cập nhật giao diện
        checkModalSelections();
        
        // Đảm bảo số lượng không vượt quá tồn kho
        const qtyInput = document.getElementById('qty-input');
        let currentVal = parseInt(qtyInput.value);
        if (currentSelectedVariant && currentVal > currentSelectedVariant.so_luong_ton) {
             qtyInput.value = currentSelectedVariant.so_luong_ton;
             selectedQty = currentSelectedVariant.so_luong_ton;
        }
    });

    // 4. Gán sự kiện cho nút "Thêm vào giỏ" TRONG MODAL
// 4. Gán sự kiện cho nút "Thêm vào giỏ" / "Mua ngay" TRONG MODAL
modalAddBtn.addEventListener('click', async function () {
    // 1. Kiểm tra đã chọn biến thể chưa
    if (!currentSelectedVariant) {
        showPopup("Vui lòng chọn phiên bản sản phẩm!");
        return;
    }

    // [LOGIC MỚI] XỬ LÝ CHUYỂN HƯỚNG KHI MUA NGAY + CHƯA ĐĂNG NHẬP
    if (currentAction === 'buy' && !isLoggedIn) {
        // Tạo đường dẫn đến trang Checkout (nơi khách muốn đến)
        const vid = currentSelectedVariant.id;
        const qty = selectedQty;
        const checkoutUrl = `index.php?page=checkout&action=buy_now&variant_id=${vid}&quantity=${qty}`;

        // Chuyển hướng sang trang Login, kèm theo tham số redirect
        // encodeURIComponent để đảm bảo đường dẫn không bị lỗi ký tự đặc biệt
        window.location.href = `index.php?page=login&redirect=${encodeURIComponent(checkoutUrl)}`;
        
        return; // Dừng lại, không chạy code phía dưới nữa
    }

    // --- NẾU ĐÃ ĐĂNG NHẬP HOẶC CHỈ LÀ "THÊM VÀO GIỎ" THÌ CHẠY TIẾP ---

    const bodyData = {
        id: currentProductId,
        variant_id: currentSelectedVariant.id,
        quantity: selectedQty
    };

    // Gọi AJAX xử lý
    const data = await sendCartRequest(currentAction, bodyData);

    // Xử lý MUA NGAY (Khi đã đăng nhập)
    if (currentAction === "buy") {
        if (data && data.status === "success") {
            const vid = currentSelectedVariant.id;
            window.location.href = `index.php?page=checkout&action=buy_now&variant_id=${vid}&quantity=${selectedQty}`;
        } else {
            // Trường hợp lỗi (ví dụ: hết session, lỗi server) -> Hiện popup hoặc reload
            if (data?.message === 'Bạn cần đăng nhập trước.') {
                 // Fallback: Nếu backend báo chưa đăng nhập, cũng chuyển hướng luôn
                 const checkoutUrl = `index.php?page=checkout&action=buy_now&variant_id=${currentSelectedVariant.id}&quantity=${selectedQty}`;
                 window.location.href = `index.php?page=login&redirect=${encodeURIComponent(checkoutUrl)}`;
            } else {
                 showPopup(data?.message || "Không thể mua ngay");
            }
        }
        return;
    }

    // Xử lý THÊM VÀO GIỎ (Giữ nguyên)
    closeQuickAddModal();   
    if (data.status === "success") {
        showPopup("🛒 Sản phẩm đã được thêm vào giỏ hàng!");
        if (typeof updateCartIconCount === "function") {
            updateCartIconCount(data.cart_count);
        }
    } else {
        showPopup("Lỗi: " + data.message);
    }
});
    
    // Logic nút tăng/giảm số lượng
    const qtyInput = document.getElementById('qty-input');
    document.getElementById('qty-minus').addEventListener('click', function() {
        let currentVal = parseInt(qtyInput.value);
        if (currentVal > 1) {
            qtyInput.value = currentVal - 1;
            selectedQty = currentVal - 1;
        }
    });

    document.getElementById('qty-plus').addEventListener('click', function() {
        let currentVal = parseInt(qtyInput.value);
        let maxQty = currentSelectedVariant ? currentSelectedVariant.so_luong_ton : 999;
        
        if (currentSelectedVariant && currentVal < maxQty) {
            qtyInput.value = currentVal + 1;
            selectedQty = currentVal + 1;
        } else if (!currentSelectedVariant) {
            // Nếu chưa chọn biến thể, giới hạn tạm thời
            qtyInput.value = currentVal + 1;
            selectedQty = currentVal + 1;
        }
    });

    qtyInput.addEventListener('change', function() {
        let currentVal = parseInt(qtyInput.value);
        let maxQty = currentSelectedVariant ? currentSelectedVariant.so_luong_ton : 999;
        
        if (isNaN(currentVal) || currentVal < 1) {
            currentVal = 1;
        } else if (currentSelectedVariant && currentVal > maxQty) {
            currentVal = maxQty;
            showPopup(`Chỉ còn tối đa ${maxQty} sản phẩm`);
        }
        
        qtyInput.value = currentVal;
        selectedQty = currentVal;
    });
    /* Thêm vào cuối file home.php, trong thẻ <script> */

    var swiperHero = new Swiper(".hero-swiper", {
        slidesPerView: 1,
        loop: true, // Lặp vô tận
        effect: "fade", // Hiệu ứng mờ dần (sang hơn trượt ngang cho banner lớn)
        fadeEffect: {
            crossFade: true
        },
        autoplay: {
            delay: 5000, // Tự chuyển sau 5 giây
            disableOnInteraction: false, // Vẫn tiếp tục auto sau khi người dùng chạm vào
        },
        pagination: {
            el: ".swiper-pagination",
            clickable: true, // Cho phép bấm vào dấu chấm
        },
        navigation: {
            nextEl: ".swiper-button-next",
            prevEl: ".swiper-button-prev",
        },
    });
});
</script>
