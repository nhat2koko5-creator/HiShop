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
            <div class="feature-icon">
                ✔ </div>
            <h3>Sản phẩm uy tín</h3>
            <p>Cam kết 100% hàng chính hãng.</p>
        </div>
        
        <div class="feature-card">
            <div class="feature-icon">
                💻</div>
            <h3>Đa dạng Laptop</h3>
            <p>Đầy đủ các dòng máy mới nhất.</p>
        </div>
        
        <div class="feature-card">
            <div class="feature-icon">
                💰 </div>
            <h3>Giá cả cạnh tranh</h3>
            <p>Luôn có ưu đãi tốt nhất thị trường.</p>
        </div>
        
        <div class="feature-card">
            <div class="feature-icon">
                💬 </div>
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
                                        -<?php echo (int)$sp['gia_tri_giam']; ?>%
                                    <?php else: ?>
                                        SALE
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <div class="product-image">
                               <img src="assets/img/products/<?php echo htmlspecialchars($sp['hinh_anh']); ?>" 
                                    alt="<?php echo htmlspecialchars($sp['ten']); ?>">
                            </div>
                            
                            <div class="card-content">
                                <h3 class="card-title"><?php echo htmlspecialchars($sp['ten']); ?></h3>
                                <div class="card-price">
                                    <?php if (isset($sp['gia_moi'])): ?>
                                        <span class="card-price-old"><?php echo number_format($sp['gia_goc']); ?>₫</span>
                                        <span class="card-price-new"><?php echo number_format($sp['gia_moi']); ?>₫</span>
                                    <?php else: ?>
                                        <span class="card-price-new"><?php echo number_format($sp['gia_goc']); ?>₫</span>
                                    <?php endif; ?>
                                </div>
                                <a href="index.php?page=product_detail&id=<?php echo $sp['id']; ?>" class="btn btn-green card-btn-green">Xem chi tiết</a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>Không tìm thấy sản phẩm nổi bật nào.</p>
                <?php endif; ?>

            </div> </div> <div class="swiper-button-prev"></div>
        <div class="swiper-button-next"></div>
    </div> </section>
