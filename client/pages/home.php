<?php
// FILE: client/pages/home.php

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
                                <div class="sale-tag">
                                    <?php if ($sp['loai_giam_gia'] == 'percent'): ?>
                                        -<?= (int)$sp['gia_tri_giam']; ?>%
                                    <?php else: ?>
                                        SALE
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <div class="product-image">
                                <img src="assets/img/products/<?= htmlspecialchars($sp['hinh_anh']); ?>" 
                            alt="<?= htmlspecialchars($sp['ten']); ?>">
                            </div>

                            <div class="card-content">
                                <h3 class="card-title"><?= htmlspecialchars($sp['ten']); ?></h3>
                                <div class="card-price">
                                    <?php if (isset($sp['gia_moi'])): ?>
                                        <span class="card-price-old"><?= number_format($sp['gia_goc']); ?>₫</span>
                                        <span class="card-price-new"><?= number_format($sp['gia_moi']); ?>₫</span>
                                    <?php else: ?>
                                        <span class="card-price-new"><?= number_format($sp['gia_goc']); ?>₫</span>
                                    <?php endif; ?>
                                </div>

                            <div class="btn-group-vertical">
                                <a href="index.php?page=product_detail&id=<?= $sp['id']; ?>" class="btn-view">🔍 Xem chi tiết</a>
                               <a href="javascript:void(0);" 
                                class="btn-cart btn-add-ajax" 
                                data-id="<?= $sp['id']; ?>">
                                🛒 Thêm vào giỏ hàng
                            </a>
                            </div>

                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>Không tìm thấy sản phẩm nổi bật nào.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="swiper-button-prev"></div>
        <div class="swiper-button-next"></div>
    </div>
    <script>
document.addEventListener('DOMContentLoaded', function() {
    
    // Hàm gửi AJAX (tương tự như ở các trang khác)
    async function sendCartRequest(action, data) {
        const formData = new URLSearchParams();
        formData.append('action', action);
        for (const key in data) {
            formData.append(key, data[key]);
        }
        
        try {
            const response = await fetch('cart-handler.php', { // Gọi thẳng đến cart-handler
                method: 'POST',
                body: formData
            });
            return await response.json();
        } catch (err) {
            return { status: 'error', message: 'Lỗi kết nối.' };
        }
    }

    // Hàm xử lý khi nhấn nút
    async function handleAjaxAddToCart(e) {
        e.preventDefault(); // Ngăn hành vi mặc định của thẻ <a>
        
        const productId = e.currentTarget.dataset.id;
        if (!productId) return;

        // Gọi API
        const data = await sendCartRequest('add', { 
            id: productId, 
            quantity: 1 
        });

        if (data.status === 'success') {
            // Hiển thị thông báo (tạm dùng alert, vì modal-thông-báo không có ở trang này)
            alert('Đã thêm sản phẩm vào giỏ hàng!');
            
            // Cập nhật icon giỏ hàng (nếu hàm này tồn tại)
            if (typeof updateCartIconCount === 'function' && data.totalItems) {
                updateCartIconCount(data.totalItems);
            }
        } else {
            // Nếu người dùng chưa đăng nhập, cart-handler.php sẽ trả về lỗi
            // Chuyển họ đến trang đăng nhập
            if (data.message.includes('Bạn cần đăng nhập')) {
                alert('Bạn cần đăng nhập để thêm vào giỏ hàng.');
                window.location.href = 'index.php?page=login';
            } else {
                alert('Lỗi: ' + data.message);
            }
        }
    }

    // Gán sự kiện cho tất cả các nút có class .btn-add-ajax
    document.querySelectorAll('.btn-add-ajax').forEach(button => {
        button.addEventListener('click', handleAjaxAddToCart);
    });
});
</script>
</section>