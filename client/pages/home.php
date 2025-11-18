<?php
// FILE: client/pages/home.php
// (Code này giả định bạn đã cập nhật hàm getFeaturedProducts() trong functions.php)

// (PHẦN BACK-END)
// Lấy sản phẩm nổi bật. Biến $pdo và $categories đã có sẵn từ index.php
$featuredProducts = getFeaturedProducts($pdo);
?>

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
            <?php if (!empty($categories)): ?>
                <?php foreach ($categories as $index => $category): ?>
                    <?php
                        $colors = ['purple', 'cyan', 'pink'];
                        $colorClass = $colors[$index % count($colors)];
                    ?>
                    <a href="index.php?page=product_list&category_id=<?= $category['id']; ?>" 
                       class="btn pill-btn <?= $colorClass; ?>">
                        <?= htmlspecialchars($category['ten']); ?>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="product-section container">
    <span class="section-subtitle">SẢN PHẨM NỔI BẬT</span>
    <h2 class="section-title">Laptop được yêu thích nhất</h2>
    
    <div class="product-carousel-wrapper">
        <div class="swiper product-carousel">
            <div class="swiper-wrapper">
                <?php if (!empty($featuredProducts)): ?>
                    <?php foreach ($featuredProducts as $sp): ?>
                    <div class="swiper-slide">
                        <div class="product-card">
                            
                            <?php if (isset($sp['gia_moi'])): ?>
                                <div class="sale-tag">SALE <?= round((1 - $sp['gia_moi'] / $sp['gia_goc']) * 100) ?>%</div>
                            <?php endif; ?>
                            
                            <div class="product-image">
                                <?php
                                $img_path = 'assets/img/products/' . htmlspecialchars($sp['hinh_anh']);
                                if (empty($sp['hinh_anh']) || !file_exists($img_path)) {
                                    $img_path = 'assets/img/no-image.png'; // Ảnh mặc định
                                }
                                ?>
                                <img src="<?= $img_path ?>" alt="<?= htmlspecialchars($sp['ten']); ?>">
                            </div>

                            <div class="card-content">
                                <h3 class="card-title"><?= htmlspecialchars($sp['ten']); ?></h3>
                                
                                <div class="card-price">
                                    <?php if (isset($sp['gia_moi'])): ?>
                                        <span class="card-price-old"><?= number_format($sp['gia_goc']); ?>₫</span>
                                        <span class="card-price-new"><?= number_format($sp['gia_moi']); ?>₫</span>
                                    <?php else: ?>
                                        <span class="card-price-new"><?= number_format($sp['gia_goc'] ?? 0); ?>₫</span>
                                    <?php endif; ?>
                                    
                                    <?php if (isset($sp['variants']) && count($sp['variants']) > 1): ?>
                                        <span style="font-size: 14px; color: #6B7280;"></span>
                                    <?php endif; ?>
                                </div>

                                <div class="btn-group-vertical">
                                    <a href="index.php?page=product_detail&id=<?= $sp['id']; ?>" class="btn-view">🔍 Xem chi tiết</a>
                                    
                                    <?php if (!empty($sp['variants'])): ?>
                                        <a href="javascript:void(0);" 
                                           class="btn-cart btn-quick-add" 
                                           data-product-id="<?= $sp['id']; ?>"
                                           data-product-name="<?= htmlspecialchars($sp['ten']); ?>"
                                           data-product-image="<?= htmlspecialchars($sp['hinh_anh']); ?>"
                                           data-variants='<?= htmlspecialchars(json_encode($sp['variants']), ENT_QUOTES, 'UTF-8'); ?>'
                                        >
                                            🛒 Thêm vào giỏ
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="text-align: center; width: 100%;">Không tìm thấy sản phẩm nổi bật nào.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="swiper-button-prev"></div>
        <div class="swiper-button-next"></div>
    </div>

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
        modalMainImage.src = productImage ? `assets/img/products/${productImage}` : 'assets/img/no-image.png';
        
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
            if (variant.hinh_anh) {
              modalMainImage.src = `assets/img/products/${variant.hinh_anh}`;
          } else {
              // Nếu biến thể không có ảnh riêng, revert về ảnh sản phẩm gốc
              // (Chúng ta cần lưu lại ảnh sản phẩm gốc khi mở modal)
              // Để đơn giản, hiện tại sẽ giữ nguyên ảnh mặc định
              // Để làm đúng, bạn cần truyền cả ảnh gốc vào data- thuộc tính của nút btn-quick-add
              // Ví dụ: modalMainImage.src = `assets/img/products/${btn.dataset.productImage}`;
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
<!-- ============= KHỐI SẢN PHẨM NỔI BẬT _THỨ 2_ ============= -->
<?php
// LẤY SẢN PHẨM GIẢM GIÁ TỪ  san_pham_giam_gia  +  giam_gia  +  san_pham
try {
    $sql = "
        SELECT 
            sp.id,
            sp.ten,
            sp.hinh_anh,
            sp.gia AS gia_goc,
            gg.loai_giam_gia,
            gg.gia_tri,
            (
                CASE 
                    WHEN gg.loai_giam_gia = 'percent' 
                        THEN sp.gia - (sp.gia * gg.gia_tri / 100)
                    WHEN gg.loai_giam_gia = 'amount' 
                        THEN sp.gia - gg.gia_tri
                    ELSE sp.gia
                END
            ) AS gia_moi
        FROM san_pham_giam_gia spgg
        JOIN san_pham sp ON sp.id = spgg.san_pham_id
        JOIN giam_gia gg ON gg.id = spgg.giam_gia_id
        WHERE (gg.ngay_bat_dau IS NULL OR gg.ngay_bat_dau <= NOW())
          AND (gg.ngay_ket_thuc IS NULL OR gg.ngay_ket_thuc >= NOW())
        ORDER BY gg.id DESC, sp.id ASC
        LIMIT 20
    ";
    $stmt = $pdo->query($sql);
    $discountProducts = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (PDOException $e) {
    // Nếu có lỗi, không chết giao diện — để trống danh sách và log lỗi nếu cần
    $discountProducts = [];
    error_log('Error fetching discount products: ' . $e->getMessage());
}
?>

<section class="product-section container">
    <span class="section-subtitle">GỢI Ý HÔM NAY</span>
    <h2 class="section-title">Các Sản phẩm giảm giá</h2>
    
    <div class="product-carousel-wrapper">
        <div class="swiper product-carousel-2">
            <div class="swiper-wrapper">
                <?php if (!empty($discountProducts)): ?>
                    <?php foreach ($discountProducts as $sp): ?>
                    <div class="swiper-slide">
                        <div class="product-card">
                            
<?php
// TÍNH % GIẢM GIÁ CHUẨN
$discountPercent = 0;
if (!empty($sp['loai_giam_gia']) && !empty($sp['gia_tri'])) {
    if ($sp['loai_giam_gia'] == 'percent') {
        $discountPercent = (int)$sp['gia_tri'];
    } else { 
        // amount → đổi sang %
        if ($sp['gia_goc'] > 0) {
            $discountPercent = round(($sp['gia_tri'] / $sp['gia_goc']) * 100);
        }
    }
}
?>

<?php if ($discountPercent > 0): ?>
    <div class="sale-tag">-<?= $discountPercent ?>%</div>
<?php endif; ?>
<style>
.sale-tag {
    position: absolute;
    top: 10px;
    left: 10px;
    background: #ff3b30;
    color: #fff;
    padding: 6px 12px;
    font-size: 14px;
    font-weight: 700;
    border-radius: 20px;
    z-index: 10;
}
.product-card {
    position: relative;
}
</style>
                            
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
                                    <span class="card-price-old"><?= number_format($sp['gia_goc']); ?>₫</span>
                                    <span class="card-price-new"><?= number_format($sp['gia_moi']); ?>₫</span>
                                </div>

                                <div class="btn-group-vertical">
                                    <a href="index.php?page=product_detail&id=<?= $sp['id']; ?>" class="btn-view">🔍 Xem chi tiết</a>
                                    <!-- Nếu bạn có chức năng thêm nhanh (variants), giữ nguyên logic cũ -->
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="text-align:center; width:100%;">Không có sản phẩm giảm giá.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="swiper-button-prev swiper-button-prev-2"></div>
        <div class="swiper-button-next swiper-button-next-2"></div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof Swiper !== 'undefined') {
        new Swiper('.product-carousel-2', {
            slidesPerView: 4,
            spaceBetween: 20,
            navigation: {
                nextEl: '.swiper-button-next-2',
                prevEl: '.swiper-button-prev-2'
            },
            breakpoints: {
                0:   { slidesPerView: 1.2 },
                576: { slidesPerView: 2 },
                768: { slidesPerView: 3 },
                1200:{ slidesPerView: 4 }
            }
        });
    }
});
</script>
