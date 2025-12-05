<?php
$featuredProducts = getFeaturedProducts($pdo);
$discountProducts = getDiscountProducts($pdo);
?>
<link rel="stylesheet" href="assets/css/client/home.css">
<link rel="stylesheet" href="assets/css/client/product-list.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
<section class="hero">
    <div class="container">
        <div class="hero-content">
            <h1>Giải pháp công nghệ<br>hiện đại & đáng tin cậy</h1>
            <p>Hỗ trợ doanh nghiệp và cá nhân tiếp cận sản phẩm tốt nhất.</p>
            <div class="btn-group">
                <a href="index.php?page=product_list" class="btn btn-primary">Xem sản phẩm</a>
                <a href="index.php?page=contact" class="btn btn-outline">Liên hệ tư vấn</a>
            </div>
        </div>
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
<section class="category-section">
    <div class="container">
        <span class="section-subtitle">DANH MỤC SẢN PHẨM</span>
        <h2 class="section-title">Khám phá theo danh mục</h2>

        <div class="category-pills">
            <a href="index.php?page=product_list&category_id=5" class="pill-btn blue">
                Laptop Doanh Nhân
            </a>
            <a href="index.php?page=product_list&category_id=2" class="pill-btn purple">
                Laptop Đồ Họa
            </a>
            <a href="index.php?page=product_list&category_id=1" class="pill-btn pink">
                Laptop Gaming
            </a>
            <a href="index.php?page=product_list&category_id=4" class="pill-btn cyan">
                Laptop Học Sinh, Sinh Viên
            </a>
            <a href="index.php?page=product_list&category_id=3" class="pill-btn blue">
                Laptop Văn Phòng
            </a>
        </div>
    </div>
</section>
<section class="product-section container">
    <span class="section-subtitle">SẢN PHẨM NỔI BẬT</span>
    <h2 class="section-title">Laptop được yêu thích nhất</h2>

    <div class="product-grid">

        <?php if (!empty($featuredProducts)): ?>
            <?php foreach ($featuredProducts as $sp): ?>

<?php
// trong vòng lặp foreach ($featuredProducts as $sp):
// đảm bảo $is_discount được khai báo trước khi dùng
$is_discount = isset($sp['gia_da_giam']) && $sp['gia_da_giam'] !== $sp['gia'];
?>
<div class="product-card">

    <div class="product-image">
            <?php
    $img_path = 'assets/img/products/' . htmlspecialchars($sp['hinh_anh']);
    if (empty($sp['hinh_anh']) || !file_exists($img_path)) {
        $img_path = 'assets/img/no-image.png';
    }
    ?>
        <!-- Click ảnh -> sang trang chi tiết -->
        <a href="index.php?page=product_detail&id=<?= $sp['id']; ?>">
            <img src="<?= $img_path ?>" alt="<?= htmlspecialchars($sp['ten']); ?>">
        </a>
    </div>

    <div class="card-content">
        <h3 class="card-title"><?= htmlspecialchars($sp['ten']); ?></h3>

        <div class="product-specs single-line">
            <span class="spec-data">
                <span class="spec-label-sm">CPU:</span> <?= htmlspecialchars($sp['cpu'] ?? 'N/A'); ?>
            </span>
            <span class="spec-separator">|</span>
            <span class="spec-data">
                <span class="spec-label-sm">RAM:</span> <?= htmlspecialchars($sp['ram'] ?? 'N/A'); ?>
            </span>
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

            <!-- MUA NGAY: nếu có variants -> mở modal (data-action='buy'); nếu không -> chuyển thẳng checkout -->
            <?php if (!empty($sp['variants']) && count($sp['variants']) > 0): ?>
                <a href="javascript:void(0);"
                   class="btn-view btn-quick-add btn-buy-now"
                   data-product-id="<?= $sp['id']; ?>"
                   data-product-name="<?= htmlspecialchars($sp['ten']); ?>"
                   data-product-image="assets/img/products/<?= htmlspecialchars($sp['hinh_anh']); ?>"
                   data-variants='<?= htmlspecialchars(json_encode($sp['variants']), ENT_QUOTES, "UTF-8"); ?>'
                   data-action="buy">
                    ⚡ Mua ngay
                </a>
            <?php else: ?>
                <a href="index.php?page=checkout&action=buy_now&variant_id=<?= $sp['id']; ?>&quantity=1" class="btn-view">
                    ⚡ Mua ngay
                </a>
            <?php endif; ?>

            <!-- Thêm vào giỏ: nếu có variants -> mở modal add; nếu không -> link add trực tiếp -->
            <?php if (!empty($sp['variants']) && count($sp['variants']) > 0): ?>
                <a href="javascript:void(0);"
                   class="btn-cart btn-quick-add"
                   data-product-id="<?= $sp['id']; ?>"
                   data-product-name="<?= htmlspecialchars($sp['ten']); ?>"
                   data-product-image="assets/img/products/<?= htmlspecialchars($sp['hinh_anh']); ?>"
                   data-variants='<?= htmlspecialchars(json_encode($sp['variants']), ENT_QUOTES, "UTF-8"); ?>'
                   data-action="add">
                    🛒 Thêm vào giỏ
                </a>
            <?php else: ?>
                <a href="index.php?page=cart&action=add&id=<?= $sp['id']; ?>" class="btn-cart">
                    🛒 Thêm vào giỏ
                </a>
            <?php endif; ?>

        </div>
    </div>
</div>


            <?php endforeach; ?>
        <?php else: ?>
            <p style="text-align:center; width:100%;">Không tìm thấy sản phẩm nổi bật nào.</p>
        <?php endif; ?>

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
        <div class="swiper product-carousel discount-carousel">
            <div class="swiper-wrapper">

                <?php if (!empty($discountProducts)): ?>
                    <?php foreach ($discountProducts as $sp): ?>

                        <?php
                        $percent   = isset($sp['giam_phan_tram']) ? (int)$sp['giam_phan_tram'] : 0;
                        $newPrice  = $sp['gia_da_giam'] ?? null;
                        $origPrice = $sp['gia'] ?? null;

                        $img_path = 'assets/img/products/' . htmlspecialchars($sp['hinh_anh']);
                        if (empty($sp['hinh_anh']) || !file_exists($img_path)) {
                            $img_path = 'assets/img/no-image.png';
                        }
                        ?>

                        <div class="swiper-slide">
                            <div class="product-card">

                                <?php if ($percent > 0): ?>
                                    <div class="product-sale-tag">-<?= htmlspecialchars($percent) ?>%</div>
                                <?php endif; ?>

                                <a href="index.php?page=product_detail&id=<?= $sp['id'] ?>" class="product-image">
                                    <img src="<?= $img_path ?>" alt="<?= htmlspecialchars($sp['ten']) ?>">
                                </a>

                                <div class="card-content">
                                    <h3 class="card-title"><?= htmlspecialchars($sp['ten']) ?></h3>

                                    <div class="product-specs single-line">
                                        <span class="spec-data">
                                            <span class="spec-label-sm">CPU:</span>
                                            <?= htmlspecialchars($sp['cpu'] ?? 'N/A') ?>
                                        </span>

                                        <span class="spec-separator">|</span>

                                        <span class="spec-data">
                                            <span class="spec-label-sm">RAM:</span>
                                            <?= htmlspecialchars($sp['ram'] ?? 'N/A') ?>
                                        </span>
                                    </div>

                                    <div class="card-price">
                                        <?php if ($newPrice !== null): ?>
                                            <span class="card-price-old"><?= number_format($origPrice) ?>₫</span>
                                            <span class="card-price-new"><?= number_format($newPrice) ?>₫</span>
                                        <?php else: ?>
                                            <span class="card-price-new"><?= number_format($origPrice) ?>₫</span>
                                            
                                        <?php endif; ?>
                                    </div>
                                    <div class="btn-group-vertical">
    <!-- Nút Mua ngay -->
<?php if (!empty($sp['variants']) && count($sp['variants']) > 0): ?>
    <a href="javascript:void(0);"
       class="btn-view btn-quick-add btn-buy-now"
       data-product-id="<?= $sp['id']; ?>"
       data-product-name="<?= htmlspecialchars($sp['ten']); ?>"
       data-product-image="assets/img/products/<?= htmlspecialchars($sp['hinh_anh']); ?>"
       data-variants='<?= htmlspecialchars(json_encode($sp['variants']), ENT_QUOTES, "UTF-8"); ?>'
       data-action="buy">
        ⚡ Mua ngay
    </a>
<?php else: ?>
    <!-- Không có biến thể → chuyển thẳng checkout -->
    <a href="index.php?page=checkout&action=buy_now&variant_id=<?= $sp['id']; ?>&quantity=1"
       class="btn-view btn-buy-now">
        ⚡ Mua ngay
    </a>
<?php endif; ?>

    <!-- Nút Thêm vào giỏ -->
    <a href="javascript:void(0);"
       class="btn-cart btn-quick-add"
       data-product-id="<?= $sp['id']; ?>"
       data-product-name="<?= htmlspecialchars($sp['ten']); ?>"
       data-product-image="assets/img/products/<?= htmlspecialchars($sp['hinh_anh']); ?>"
       data-variants='<?= htmlspecialchars(json_encode($sp['variants']), ENT_QUOTES, "UTF-8"); ?>'
       data-action="add">
        🛒 Thêm vào giỏ
    </a>
</div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="text-align:center;width:100%;">Không có sản phẩm giảm giá.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="swiper-button-prev discount-prev"></div>
        <div class="swiper-button-next discount-next"></div>
    </div>
</section>
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
<script>
var swiperDiscount = new Swiper(".discount-carousel", {
    slidesPerView: 4,
    spaceBetween: 20,
    loop:false,
    navigation: {
        nextEl: ".discount-next",
        prevEl: ".discount-prev"
    },
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    
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
    modalMainImage.src = originalProductImage || 'assets/img/no-image.png';

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
modalAddBtn.addEventListener('click', async function () {
    if (!currentSelectedVariant) return;

    const bodyData = {
        id: currentProductId,
        variant_id: currentSelectedVariant.id,
        quantity: selectedQty
    };

    const data = await sendCartRequest(currentAction, bodyData);

    // -------------------
    // Xử lý MUA NGAY
    // -------------------
if (currentAction === "buy") {
    if (data && data.status === "success") {

        // Lấy ID biến thể được chọn
        const vid = currentSelectedVariant.id;

        // CHUYỂN HƯỚNG ĐÚNG LINK BẠN CẦN
        window.location.href = `index.php?page=checkout&action=buy_now&variant_id=${vid}&quantity=${selectedQty}`;

    } else {
        showPopup(data?.message || "Không thể mua ngay");
    }
    return;
}
    // -------------------
    // Xử lý THÊM VÀO GIỎ
    // -------------------
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

});
</script>
