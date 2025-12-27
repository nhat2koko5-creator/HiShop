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
                            <a href="mailto:contact@hishop.com">hishopNSHB@gmail.com</a>
                        </li>
                        <li>
                            <i class="fa-solid fa-phone"></i>
                            <a href="tel:18001234">1900 10009
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
    <script>
async function toggleWishlist(btn, productId) {
    // 1. Hiệu ứng UX tức thời (Optimistic UI) - Bấm cái đổi màu ngay cho sướng tay
    const icon = btn.querySelector('i');
    const isDetailBtn = btn.classList.contains('btn-wishlist-detail');
    const spanText = btn.querySelector('span'); // Cho trang chi tiết

    // Chặn click liên tục
    if(btn.disabled) return;
    btn.disabled = true;

    try {
        // 2. Gửi request lên server
        const formData = new FormData();
        formData.append('product_id', productId);
        formData.append('action', 'toggle');

        const response = await fetch('wishlist_handler.php', {
            method: 'POST',
            body: formData
        });

        // 3. Xử lý kết quả
        if (response.status === 401) {
            // Nếu chưa đăng nhập -> Chuyển hướng login
            if(confirm('Bạn cần đăng nhập để lưu sản phẩm yêu thích. Đi đến trang đăng nhập?')) {
                window.location.href = 'index.php?page=login';
            }
            btn.disabled = false;
            return;
        }

        const data = await response.json();

        if (data.status === 'success') {
            // Cập nhật giao diện dựa trên kết quả thật từ Server
            if (data.state === 'liked') {
                // Đổi thành tim đặc (đỏ)
                icon.classList.remove('fa-regular');
                icon.classList.add('fa-solid', 'text-danger');
                if(isDetailBtn && spanText) spanText.textContent = "Đã thích";
                
                // (Tùy chọn) Hiện thông báo nhỏ
                // showToast('Đã thêm vào yêu thích ❤️'); 
            } else {
                // Đổi thành tim rỗng
                icon.classList.remove('fa-solid', 'text-danger');
                icon.classList.add('fa-regular');
                if(isDetailBtn && spanText) spanText.textContent = "Yêu thích";
            }
        } else {
            alert('Lỗi: ' + data.message);
        }

    } catch (error) {
        console.error('Error:', error);
        alert('Có lỗi xảy ra, vui lòng thử lại.');
    } finally {
        btn.disabled = false;
    }
}
</script>
</body>
</html>