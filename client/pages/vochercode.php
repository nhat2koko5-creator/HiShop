<?php
// Giả định: $pdo đã được khởi tạo và kết nối CSDL
// Giả định: Hàm getAvailableCoupons($pdo) đã được định nghĩa trong functions.php
// Đảm bảo file functions.php đã được include/require ở đâu đó trước khi chạy hàm này
$coupons = getAvailableCoupons($pdo); 
?>

<style>
    /* CSS Tùy chỉnh cho Trang Mã Khuyến Mãi */
    .coupon-section {
        padding: 40px 20px;
        max-width: 1200px;
        margin: 0 auto;
    }
    .coupon-title {
        text-align: center;
        margin-bottom: 30px;
        font-size: 2em;
        color: #333;
    }
    .coupon-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        gap: 25px;
    }
    .coupon-card {
        border: 2px solid #ddd;
        border-left: 8px solid #ff6347; /* Đổi màu cam tươi */
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        transition: transform 0.3s ease;
        background-color: #fff;
    }
    .coupon-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 20px rgba(0,0,0,0.1);
    }
    .coupon-header {
        background-color: #ff6347;
        color: white;
        padding: 15px 20px;
    }
    .coupon-code {
        font-size: 1.6em;
        font-weight: 700;
        letter-spacing: 1px;
        margin: 0;
    }
    .coupon-body {
        padding: 20px;
    }
    .coupon-value {
        font-size: 1.3em;
        color: #ff6347;
        font-weight: bold;
        margin-bottom: 10px;
        border-bottom: 1px dashed #eee;
        padding-bottom: 10px;
    }
    .coupon-detail {
        margin-bottom: 8px;
        color: #555;
        font-size: 0.95em;
    }
    .copy-area {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 15px;
    }
    .copy-btn {
        background-color: #4CAF50; /* Xanh lá */
        color: white;
        border: none;
        padding: 10px 15px;
        border-radius: 5px;
        cursor: pointer;
        font-weight: bold;
        transition: background-color 0.3s;
    }
    .copy-btn:hover {
        background-color: #45a049;
    }
    .coupon-footer {
        background-color: #f7f7f7;
        padding: 10px 20px;
        font-size: 0.85em;
        color: #777;
        border-top: 1px solid #eee;
    }
</style>

<main class="coupon-section">
    <h2 class="coupon-title">🎉 Kho Mã Giảm Giá Đặc Biệt Dành Cho Khách Hàng</h2>

    <div class="coupon-grid">
        <?php if (!empty($coupons)): ?>
            <?php foreach ($coupons as $coupon): 
                
                // Định dạng giá trị hiển thị
                $value_display = '';
                if ($coupon['loai_khuyen_mai'] === 'percent') {
                    $value_display = number_format($coupon['gia_tri'], 0) . '%';
                    $description_prefix = 'Giảm thêm';
                } elseif ($coupon['loai_khuyen_mai'] === 'amount') {
                    $value_display = number_format($coupon['gia_tri'], 0) . 'đ';
                    $description_prefix = 'Giảm trực tiếp';
                }
            ?>
            <div class="coupon-card">
                <div class="coupon-header">
                    <p class="coupon-code" id="code-<?= $coupon['id'] ?>"><?= htmlspecialchars($coupon['ten']) ?></p>
                </div>
                
                <div class="coupon-body">
                    <p class="coupon-value">
                        <?= $description_prefix ?> **<?= $value_display ?>**
                    </p>
                    <p class="coupon-detail">
                        **Mô tả:** <?= htmlspecialchars($coupon['mo_ta'] ?? 'Không có mô tả chi tiết.') ?>
                    </p>
                    <p class="coupon-detail">
                        **Điều kiện áp dụng:** <?= htmlspecialchars($coupon['dieu_kien'] ?? 'Áp dụng cho mọi đơn hàng.') ?>
                    </p>
                    
                    <div class="copy-area">
                        <small>Nhấn vào để sao chép mã</small>
                        <button class="copy-btn" data-coupon-id="<?= $coupon['id'] ?>">
                            Sao Chép Mã
                        </button>
                    </div>
                </div>

                <div class="coupon-footer">
                    Hạn dùng: 
                    <?php 
                        if ($coupon['ngay_ket_thuc']) {
                            echo 'Đến hết ' . date('H:i, d/m/Y', strtotime($coupon['ngay_ket_thuc']));
                        } else {
                            echo 'Không giới hạn thời gian';
                        }
                    ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="text-align: center; grid-column: 1 / -1;">
                Hiện tại không có mã khuyến mãi nào đang hoạt động. Vui lòng theo dõi các chương trình sắp tới!
            </p>
        <?php endif; ?>
    </div>
</main>

<script>
    // Chức năng JavaScript để Sao Chép Mã
    document.querySelectorAll('.copy-btn').forEach(button => {
        button.addEventListener('click', function() {
            const couponId = this.dataset.couponId;
            const couponCodeElement = document.getElementById(`code-${couponId}`);
            // Lấy mã code, loại bỏ khoảng trắng thừa
            const couponCode = (couponCodeElement.textContent || couponCodeElement.innerText).trim();
            
            // Sao chép vào clipboard
            navigator.clipboard.writeText(couponCode)
                .then(() => {
                    const originalText = this.textContent;
                    const originalColor = this.style.backgroundColor;
                    
                    this.textContent = 'Đã Sao Chép!';
                    this.style.backgroundColor = '#1e88e5'; // Màu xanh dương
                    
                    // Đặt lại sau 2.5 giây
                    setTimeout(() => {
                        this.textContent = originalText;
                        this.style.backgroundColor = originalColor;
                    }, 2500);
                })
                .catch(err => {
                    console.error('Lỗi sao chép: ', err);
                    alert('Sao chép thất bại. Vui lòng sao chép thủ công: ' + couponCode);
                });
        });
    });
</script>