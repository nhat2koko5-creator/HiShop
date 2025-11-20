<?php
$featuredProducts = getFeaturedProducts($pdo);
$discountProducts = getDiscountProducts($pdo);

// Load biến thể cho sản phẩm nổi bật
foreach ($featuredProducts as &$sp) {
    $sp['variants'] = getProductVariants($pdo, $sp['id']);
}

// Load biến thể cho sản phẩm giảm giá (BẠN BỎ QUÊN ĐOẠN NÀY)
foreach ($discountProducts as &$sp) {
    $sp['variants'] = getProductVariants($pdo, $sp['id']);
}
?>
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
<style>
</style>
<section class="product-section container">
    <span class="section-subtitle">SẢN PHẨM NỔI BẬT</span>
    <h2 class="section-title">Laptop được yêu thích nhất</h2>

    <div class="product-carousel-wrapper">

        <!-- SWIPER CONTAINER -->
        <div class="swiper product-carousel">
            <div class="swiper-wrapper">

                <?php if (!empty($featuredProducts)): ?>
                    <?php foreach ($featuredProducts as $sp): ?>

                        <div class="swiper-slide">
                            <div class="product-card">

                                <!-- Hình ảnh sản phẩm -->
                                <div class="product-image">
                                    <?php
                                    $img_path = 'assets/img/products/' . htmlspecialchars($sp['hinh_anh']);
                                    if (empty($sp['hinh_anh']) || !file_exists($img_path)) {
                                        $img_path = 'assets/img/no-image.png';
                                    }
                                    ?>
                                    <img src="<?= $img_path ?>" alt="<?= htmlspecialchars($sp['ten']); ?>">
                                </div>

                                <div class="card-content">
                                    <h3 class="card-title"><?= htmlspecialchars($sp['ten']); ?></h3>

                                    <!-- Giá sản phẩm -->
                                    <div class="card-price">
                                        <span class="card-price-new">
                                            <?= number_format($sp['gia']); ?>₫
                                        </span>
                                    </div>

                                    <!-- Nút -->
                                    <div class="btn-group-vertical">
                                        <a href="index.php?page=product_detail&id=<?= $sp['id']; ?>" class="btn-view">
                                            🔍 Xem chi tiết
                                        </a>

                                        <?php if (!empty($sp['variants']) && count($sp['variants']) > 0): ?>

                                            <!-- Có biến thể → Quick Add -->
                                            <a href="javascript:void(0);"
                                               class="btn-cart btn-quick-add"
                                               data-product-id="<?= $sp['id']; ?>"
                                               data-product-name="<?= htmlspecialchars($sp['ten']); ?>"
                                               data-product-image="assets/img/products/<?= htmlspecialchars($sp['hinh_anh']); ?>"
                                               data-variants='<?= htmlspecialchars(json_encode($sp['variants']), ENT_QUOTES, "UTF-8"); ?>'>
                                                🛒 Thêm vào giỏ
                                            </a>

                                        <?php else: ?>

                                            <!-- Không có biến thể → Add trực tiếp -->
                                            <a href="index.php?page=cart&action=add&id=<?= $sp['id']; ?>" class="btn-cart">
                                                🛒 Thêm vào giỏ
                                            </a>

                                        <?php endif; ?>
                                    </div>

                                </div>
                            </div>
                        </div>

                    <?php endforeach; ?>

                <?php else: ?>

                    <p style="text-align:center; width:100%;">Không tìm thấy sản phẩm nổi bật nào.</p>

                <?php endif; ?>

            </div>
        </div>

        <!-- MŨI TÊN SWIPER (ĐÚNG VỊ TRÍ) -->
        <div class="swiper-button-prev"></div>
        <div class="swiper-button-next"></div>

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

            <div class="option-group" id="modal-color-group">
                <h4>Màu sắc</h4>
                <div class="option-box" id="modal-color-options">
                    </div>
            </div>
            <div class="option-group" id="modal-ssd-group">
                <h4>SSD</h4>
                <div class="option-box" id="modal-ssd-options">
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

<section class="product-section container">
    <span class="section-subtitle">SẢN PHẨM GIẢM GIÁ</span>
    <h2 class="section-title">Ưu đãi hot trong tuần</h2>

    <div class="product-carousel-wrapper">
        <div class="swiper product-carousel">
            <div class="swiper-wrapper">
<?php if (!empty($discountProducts)): ?>
    <?php foreach ($discountProducts as $sp): ?>
    <div class="swiper-slide">
        <div class="product-card">

<?php 
// an toàn: nếu không có key thì lấy giá trị mặc định
$percent = isset($sp['giam_phan_tram']) ? (int)$sp['giam_phan_tram'] : 0;
$newPrice = isset($sp['gia_da_giam']) ? $sp['gia_da_giam'] : null;
$origPrice = isset($sp['gia']) ? $sp['gia'] : null;
?>

<!-- SALE TAG (BONG BÓNG GIẢM GIÁ) -->
<?php if ($percent > 0): ?>
    <div class="product-sale-tag">-<?= htmlspecialchars($percent); ?>%</div>
<?php endif; ?>

<div class="product-image">
    <?php
    $img_path = 'assets/img/products/' . htmlspecialchars($sp['hinh_anh']);
    if (empty($sp['hinh_anh']) || !file_exists($img_path)) {
        $img_path = 'assets/img/no-image.png';
    }
    ?>
    <img src="<?= $img_path ?>" alt="<?= htmlspecialchars($sp['ten']); ?>">
</div>

<div class="card-content">
    <h3 class="card-title"><?= htmlspecialchars($sp['ten']); ?></h3>

    <div class="card-price">
        <?php if ($newPrice !== null): ?>
            <span class="card-price-old"><?= number_format($origPrice); ?>₫</span>
            <span class="card-price-new"><?= number_format($newPrice); ?>₫</span>
        <?php else: ?>
            <span class="card-price-new"><?= number_format($origPrice); ?>₫</span>
        <?php endif; ?>
    </div>

<div class="btn-group-vertical">
    <a href="index.php?page=product_detail&id=<?= $sp['id']; ?>" class="btn-view">
        🔍 Xem chi tiết
    </a>

    <?php if (!empty($sp['variants']) && count($sp['variants']) > 0): ?>
        <!-- Giảm giá nhưng có biến thể → Quick Add -->
        <a href="javascript:void(0);"
           class="btn-cart btn-quick-add"
           data-product-id="<?= $sp['id']; ?>"
           data-product-name="<?= htmlspecialchars($sp['ten']); ?>"
           data-product-image="assets/img/products/<?= htmlspecialchars($sp['hinh_anh']); ?>"
           data-variants='<?= htmlspecialchars(json_encode($sp['variants']), ENT_QUOTES, "UTF-8"); ?>'
        >
            🛒 Thêm vào giỏ
        </a>

    <?php else: ?>
        <!-- Không có biến thể → add thẳng -->
        <a href="index.php?page=cart&action=add&id=<?= $sp['id']; ?>" class="btn-cart">
            🛒 Thêm vào giỏ
        </a>
    <?php endif; ?>
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

        <div class="swiper-button-prev"></div>
        <div class="swiper-button-next"></div>
    </div>
</section>
<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // --- Biến DOM (cho modal) ---
    const modal = document.getElementById('quick-add-modal');
    const modalProductName = document.getElementById('modal-product-name');
    const modalPrice = document.getElementById('modal-product-price');
    const modalStock = document.getElementById('modal-stock-status');
    const modalColorBox = document.getElementById('modal-color-options');
    const modalSsdBox = document.getElementById('modal-ssd-options');
    const modalAddBtn = document.getElementById('modal-add-btn');
    const modalMainImage = document.getElementById('modal-product-main-image');
    
    // --- Biến Trạng Thái (sẽ được reset) ---
    let currentVariants = []; // Dữ liệu JSON từ data-variants
    let currentProductId = null;
    let selectedColor = null;
    let selectedSSD = null;
    let currentSelectedVariant = null; // {id, gia, ...}

    // === HÀM 1: MỞ VÀ ĐIỀN DỮ LIỆU VÀO MODAL ===
    function openQuickAddModal(e) {
        e.preventDefault();
        const btn = e.currentTarget;

        // Lấy dữ liệu từ nút
        currentProductId = btn.dataset.productId;
        modalProductName.textContent = btn.dataset.productName;
        const productImage = btn.dataset.productImage;
modalMainImage.src = productImage || 'assets/img/no-image.png';

        
        try {
            currentVariants = JSON.parse(btn.dataset.variants);
        } catch(e) {
            alert('Lỗi dữ liệu biến thể. Vui lòng thử lại.');
            return;
        }

        // --- (MỚI) Tự động xây dựng các tùy chọn ---
        const colors = [...new Set(currentVariants.map(v => v.mau_sac))];
        const ssds = [...new Set(currentVariants.map(v => v.dung_luong_ssd))];

        // Tạo HTML cho Màu
        modalColorBox.innerHTML = ''; // Xóa sạch
        colors.forEach(color => {
            const opt = document.createElement('div');
            opt.className = 'option';
            opt.dataset.group = 'color';
            opt.dataset.value = color;
            opt.textContent = color;
            modalColorBox.appendChild(opt);
        });

        // Tạo HTML cho SSD
        modalSsdBox.innerHTML = ''; // Xóa sạch
        ssds.forEach(ssd => {
            const opt = document.createElement('div');
            opt.className = 'option';
            opt.dataset.group = 'ssd';
            opt.dataset.value = ssd;
            opt.textContent = ssd;
            modalSsdBox.appendChild(opt);
        });
        
        // Reset giá và hiển thị modal
        modalPrice.textContent = '--';
        modalStock.textContent = 'Vui lòng chọn tùy chọn';
        modalStock.className = 'stock-info';
        modal.style.display = 'flex';
    }

    // === HÀM 2: ĐÓNG VÀ RESET MODAL ===
    function closeQuickAddModal() {
        modal.style.display = 'none';
        // Reset tất cả
        currentVariants = [];
        currentProductId = null;
        selectedColor = null;
        selectedSSD = null;
        currentSelectedVariant = null;
        modalAddBtn.disabled = true;
    }

    // === HÀM 3: KIỂM TRA LỰA CHỌN (Logic chính) ===
    function checkModalSelections() {
        // 1. Reset
        modalAddBtn.disabled = true;
        currentSelectedVariant = null;

        // 2. Chỉ tiếp tục nếu đã chọn đủ
        if (!selectedColor || !selectedSSD) {
            return;
        }

        // 3. Tìm biến thể
        // (MỚI) Dùng hàm find() để tìm trong mảng JSON
        const variant = currentVariants.find(v => (v.mau_sac === selectedColor && v.dung_luong_ssd === selectedSSD));

        if (variant) {
            // 4. TÌM THẤY
            modalPrice.textContent = formatPrice(variant.gia);
            
            if (variant.so_luong_ton > 0) {
                modalStock.textContent = "Còn " + variant.so_luong_ton + " sản phẩm";
                modalStock.className = 'stock-info';
                modalAddBtn.disabled = false;
                currentSelectedVariant = variant; // Lưu lại
            } else {
                modalStock.textContent = "Hết hàng";
                modalStock.className = 'stock-info out';
            }
if (variant.hinh_anh && variant.hinh_anh !== "") {
    modalMainImage.src = `assets/img/products/${variant.hinh_anh}`;
} else {
    modalMainImage.src = originalProductImage;
}

        } else {
            // 5. KHÔNG TÌM THẤY (Kết hợp không tồn tại)
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
    document.querySelectorAll('.btn-quick-add').forEach(button => {
        button.addEventListener('click', openQuickAddModal);
    });

    // 2. Gán sự kiện cho các nút đóng modal
    document.getElementById('modal-close-btn').addEventListener('click', closeQuickAddModal);
    document.getElementById('modal-cancel-btn').addEventListener('click', closeQuickAddModal);
    modal.addEventListener('click', e => {
        if (e.target === modal) closeQuickAddModal();
    });

    // 3. (MỚI) Dùng "Event Delegation" để xử lý các nút .option được tạo động
    modal.addEventListener('click', function(e) {
        // Chỉ xử lý nếu nhấn vào .option
        if (!e.target.classList.contains('option') || e.target.classList.contains('disabled')) {
            return;
        }

        const group = e.target.dataset.group;
        const value = e.target.dataset.value;

        if (group === 'color') {
            selectedColor = value;
            // Xóa active cũ
            modalColorBox.querySelectorAll('.option').forEach(o => o.classList.remove('active'));
        } else if (group === 'ssd') {
            selectedSSD = value;
            // Xóa active cũ
            modalSsdBox.querySelectorAll('.option').forEach(o => o.classList.remove('active'));
        }
        
        // Thêm active mới
        e.target.classList.add('active');
        
        // Kiểm tra
        checkModalSelections();
    });

    // 4. Gán sự kiện cho nút "Thêm vào giỏ" TRONG MODAL
    modalAddBtn.addEventListener('click', async function() {
        if (!currentSelectedVariant) return;

        const bodyData = {
            action: 'add',
            id: currentProductId, // ID sản phẩm gốc
            variant_id: currentSelectedVariant.id, // ID biến thể
            quantity: 1
        };
        
        const data = await sendCartRequest('add', bodyData);
        closeQuickAddModal(); // Đóng modal ngay

        if (data.status === "success") {
            showPopup('🛒 Sản phẩm đã được thêm vào giỏ hàng!');
            if (typeof updateCartIconCount === "function") {
                updateCartIconCount(data.totalItems);
            }
        } else {
            // Xử lý lỗi (ví dụ: chưa đăng nhập)
            if (data.message.includes('Bạn cần đăng nhập')) {
                // (ĐÃ SỬA LỖI CÚ PHÁP TẠI ĐÂY)
                showPopup("Lỗi: " + data.message);
                setTimeout(() => { window.location.href = 'index.php?page=login'; }, 1500);
            } else {
                showPopup("Lỗi: " + data.message);
            }
        }
    });
});
</script>
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
