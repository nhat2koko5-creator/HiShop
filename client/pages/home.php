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
        <span class="section-subtitle">& DANH MỤC SẢN PHẨM</span>
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
    <a href="index.php?page=cart&action=add&id=<?= $sp['id']; ?>" class="btn-cart">🛒 Thêm vào giỏ hàng</a>
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
</section>
<style>
/* ======================
   STYLE CHO NÚT SẢN PHẨM
====================== */

/* Nút xem chi tiết – có màu nền xanh + viền */
.btn-view {
    background-color: #007bff;
    color: #fff !important;
    border: 2px solid #007bff;
    border-radius: 8px;
    padding: 10px 18px;
    font-weight: 500;
    font-size: 15px;
    width: 100%;
    transition: all 0.3s ease;
}

.btn-view:hover {
    background-color: #0056b3;
    border-color: #0056b3;
    transform: translateY(-2px);
}

/* Nút thêm vào giỏ hàng – nền trắng, viền xanh */
.btn-cart {
    background-color: #fff;
    color: #007bff !important;
    border: 2px solid #007bff;
    border-radius: 8px;
    padding: 10px 18px;
    font-weight: 500;
    font-size: 15px;
    width: 100%;
    transition: all 0.3s ease;
}

.btn-cart:hover {
    background-color: #007bff;
    color: #fff !important;
    transform: translateY(-2px);
}

/* Hai nút đặt dọc, cách nhau nhẹ */
.btn-group-vertical {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-top: auto;
}

/* CARD SẢN PHẨM */
.product-card {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    height: 100%;
    padding: 16px;
    border-radius: 12px;
    background: #fff;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.product-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 4px 16px rgba(0,0,0,0.12);
}

/* Hình ảnh sản phẩm */
.product-image {
    display: flex;
    justify-content: center;
    align-items: center;
    height: 180px;
    margin-bottom: 10px;
}

.product-image img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
}

/* Nội dung card */
.card-content {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    flex-grow: 1;
    min-height: 180px;
}

/* Tên sản phẩm – luôn gọn 2 dòng */
.card-title {
    font-size: 16px;
    font-weight: 600;
    color: #333;
    text-align: center;
    margin: 8px 0;
    line-height: 1.4em;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
    min-height: 44px;
}

/* Giá sản phẩm */
.card-price {
    text-align: center;
    margin-bottom: 8px;
}

.card-price-old {
    text-decoration: line-through;
    color: #888;
    margin-right: 6px;
}

/* 👉 Giá mới màu đỏ để nổi bật */
.card-price-new {
    color: #e60000; /* đỏ tươi */
    font-weight: 700;
    font-size: 16px;
}
</style>
