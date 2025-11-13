</main> <footer class="footer">
        <div class="container">
            <div class="footer-main">
                <div class="footer-col">
                    <a href="index.php?page=home" class="logo">HIShop</a>
                    <p>Giải pháp công nghệ hiện đại & đáng tin cậy.</p>
                    
                    <div class="social-icons">
                        <a href="#" aria-label="Facebook"><i class="fa-brands fa-facebook-f"></i></a>
                        <a href="#" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a>
                        <a href="#" aria-label="TikTok"><i class="fa-brands fa-tiktok"></i></a>
                        <a href="#" aria-label="YouTube"><i class="fa-brands fa-youtube"></i></a>
                    </div>
                </div>

                <div class="footer-col">
                    <h5>Sản Phẩm</h5>
                    <ul>
                        <?php if (isset($categories) && !empty($categories)): ?>
                            <?php foreach ($categories as $category): ?>
                                <li>
                                    <a href="index.php?page=product_list&category_id=<?php echo $category['id']; ?>">
                                        <?php echo htmlspecialchars($category['ten']); ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li><a href="index.php?page=product_list">Tất cả sản phẩm</a></li>
                        <?php endif; ?>
                    </ul>
                </div>

                <div class="footer-col">
                    <h5>Hỗ Trợ</h5>
                    <ul>
                        <li><a href="index.php?page=static_about">Về Chúng Tôi</a></li> <li><a href="index.php?page=static_policy">Chính sách bảo hành</a></li>
                        <li><a href="index.php?page=static_policy">Chính sách đổi trả</a></li> <li><a href="index.php?page=contact">Liên hệ</a></li>
                    </ul>
                </div>

                <div class="footer-col contact-info"> <h5>Liên Hệ</h5>
                    <ul>
                        <li>
                            <i class="fa-solid fa-location-dot"></i>
                            <span>128A, Hồ Tùng Mậu, Cầu Giấy, Hà Nội</span>
                        </li>
                        <li>
                            <i class="fa-solid fa-envelope"></i>
                            <a href="mailto:contact@hishop.com">contact@hishop.com</a>
                        </li>
                        <li>
                            <i class="fa-solid fa-phone"></i>
                            <a href="tel:18001234">1800 1234</a>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>© 2025 HIShop. Đã đăng ký bản quyền.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    
    <script src="https://kit.fontawesome.com/your-kit-code.js" crossorigin="anonymous"></script> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />


    <script>
        // Khởi tạo Swiper cho carousel sản phẩm
        const productSwiper = new Swiper('.product-carousel', {
            slidesPerView: 4,      
            spaceBetween: 24,      
            loop: true,            
            navigation: {
                nextEl: '.swiper-button-next',
                prevEl: '.swiper-button-prev',
            },
        });
    </script>
</body>
</html>