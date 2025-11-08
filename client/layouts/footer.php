</main>

    <footer class="footer">
        <div class="container">
            <div class="footer-main">
                <div class="footer-col"><a href="index.php?page=home" class="logo">HIShop</a><p>Giải pháp công nghệ hiện đại & đáng tin cậy.</p></div>
                <div class="footer-col">
                    <h5>Sản Phẩm</h5>
                    <ul>
                        <li><a href="index.php?page=product_list&category=gaming">Laptop Gaming</a></li>
                        <li><a href="index.php?page=product_list&category=office">Laptop Văn Phòng</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h5>Hỗ Trợ</h5>
                    <ul>
                        <li><a href="index.php?page=static_policy">Chính sách bảo hành</a></li>
                        <li><a href="index.php?page=contact">Liên hệ</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h5>Liên Hệ</h5>
                    <ul>
                        <li>Email: contact@hishop.com</li>
                        <li>Hotline: 1800 1234</li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>© 2025 HIShop. Đã đăng ký bản quyền.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>

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