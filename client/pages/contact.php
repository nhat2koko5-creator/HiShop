<?php 
// FILE: client/pages/contact.php (ĐÃ THIẾT KẾ LẠI HOÀN HẢO)
require_once 'client/layouts/header.php'; 
?>

<div class="static-page-header">
    <h1>Liên Hệ Với Chúng Tôi</h1>
</div>

<div class="container">

    <div class="contact-layout-new">

        <div class="contact-card contact-form-card">
            <h2>Gửi tin nhắn cho HIShop</h2>
            <p>Nếu bạn có bất kỳ câu hỏi nào, đừng ngần ngại gửi tin nhắn cho chúng tôi. Đội ngũ HIShop sẽ phản hồi bạn
                trong thời gian sớm nhất.</p>

            <form id="contactForm" method="POST">
                <div class="form-group">
                    <label for="contact_name">Họ và tên *</label>
                    <input type="text" id="contact_name" name="contact_name" class="form-input" required>
                </div>

                <div class="form-group">
                    <label for="contact_email">Email *</label>
                    <input type="email" id="contact_email" name="contact_email" class="form-input" required>
                </div>

                <div class="form-group">
                    <label for="contact_phone">Số điện thoại</label>
                    <input type="tel" id="contact_phone" name="contact_phone" class="form-input">
                </div>

                <div class="form-group">
                    <label for="contact_message">Nội dung tin nhắn *</label>
                    <textarea id="contact_message" name="contact_message" class="form-textarea" rows="5"
                        required></textarea>
                </div>

                <button type="submit" class="btn btn-primary">Gửi Tin Nhắn</button>
            </form>
        </div>

        <div class="contact-right-stack">

            <div class="contact-card contact-info-card">
                <h2>Thông tin liên hệ</h2>
                <p>Bạn cũng có thể liên hệ trực tiếp với chúng tôi qua các kênh dưới đây:</p>

                <div class="info-block">
                    <div class="info-block-icon">
                        <i class="fa-solid fa-location-dot"></i>
                    </div>
                    <div class="info-block-content">
                        <h3>Địa chỉ cửa hàng</h3>
                        <p>123A, Hồ Tùng Mậu, Cầu Giấy, Hà Nội</p>
                    </div>
                </div>

                <div class="info-block">
                    <div class="info-block-icon">
                        <i class="fa-solid fa-phone"></i>
                    </div>
                    <div class="info-block-content">
                        <h3>Hotline Hỗ Trợ</h3>
                        <p>1900 1000+ (Hỗ trợ Kỹ thuật)<br>1800 1234+ (Tư vấn Bán hàng)</p>
                    </div>
                </div>

                <div class="info-block">
                    <div class="info-block-icon">
                        <i class="fa-solid fa-envelope"></i>
                    </div>
                    <div class="info-block-content">
                        <h3>Email</h3>
                        <p>contact@hishop.com</p>
                    </div>
                </div>
            </div>

            <div class="contact-card contact-map-card">
                <iframe
                    src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3724.0323067931396!2d105.7828060759086!3d21.03152508736746!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3135ab0000000001%3A0x11162a0c63c03a7c!2s123A%20H%E1%BB%93%20T%C3%B9ng%20M%E1%BA%ADu%2C%20D%E1%BB%8Bch%20V%E1%BB%8Dng%20H%E1%BA%ADu%2C%20C%E1%BA%A7u%20Gi%E1%BA%A5y%2C%20H%C3%A0%20N%E1%BB%99i%2C%20Vi%E1%BB%87t%20Nam!5e0!3m2!1svi!2s!4v1731498160088!5m2!1svi!2s"
                    width="100%" height="300" style="border:0; border-radius: var(--radius-lg);" allowfullscreen=""
                    loading="lazy">
                </iframe>
            </div>

        </div>
    </div>
</div>
<script>
    // 1. SAO CHÉP HÀM POPUP TỪ FILE product_detail.php
    function showPopup(msg) {
        const el = document.createElement('div');
        el.textContent = msg;
        // (Tôi đã cập nhật màu nền cho đồng bộ với nút)
        Object.assign(el.style, {
            position: 'fixed',
            bottom: '30px',
            right: '30px',
            background: '#4F46E5',
            /* Dùng màu --color-primary */
            color: '#fff',
            padding: '12px 20px',
            borderRadius: 'var(--radius-lg)',
            /* Dùng --radius-lg cho đẹp */
            boxShadow: 'var(--shadow)',
            /* Dùng --shadow */
            zIndex: '9999',
            transition: 'opacity 0.5s',
            opacity: '0' /* Bắt đầu trong suốt */
        });
        document.body.appendChild(el);
        // Hiệu ứng fade-in
        setTimeout(() => el.style.opacity = '1', 10);
        // Tự động ẩn sau 3 giây
        setTimeout(() => el.style.opacity = '0', 3000);
        setTimeout(() => el.remove(), 3500);
    }
    // 2. GẮN SỰ KIỆN VÀO FORM
    document.addEventListener("DOMContentLoaded", function() {
        const form = document.getElementById("contactForm");
        const submitButton = form.querySelector("button[type='submit']");
        if (form) {
            form.addEventListener("submit", function(event) {
                // A. Ngăn form tải lại trang (để gửi bằng AJAX)
                event.preventDefault();
                const originalBtnText = submitButton.textContent;
                submitButton.textContent = "Đang gửi...";
                submitButton.disabled = true;
                // B. (GIẢ LẬP) Gửi tin nhắn thành công
                // (Trong dự án thật, bạn sẽ thay thế hàm setTimeout này
                // bằng một lệnh 'fetch' để gửi data đi)
                setTimeout(() => {
                    // C. HIỂN THỊ POPUP
                    showPopup("✔️ Tin nhắn đã được gửi thành công!\n Cảm ơn bạn đã đóng góp ý kiến ");
                    // D. Reset form và nút bấm
                    submitButton.textContent = originalBtnText;
                    submitButton.disabled = false;
                    form.reset();
                }, 1200); // Giả lập mất 1.2 giây để gửi
            });
        }
    });
</script>

<?php 
require_once 'client/layouts/footer.php'; 
?>