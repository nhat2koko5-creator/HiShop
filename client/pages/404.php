<?php
// FILE: client/pages/404.php
require_once 'client/layouts/header.php';
?>
<link rel="stylesheet" href="assets/css/client/404.css">
<div class="error-container">
    <div class="error-code">404</div>
    <div class="error-title">Không tìm thấy trang</div>
    <p class="error-message">
        Xin lỗi, trang bạn đang tìm kiếm có thể đã bị xóa, đổi tên hoặc tạm thời không khả dụng.
    </p>

    <div class="error-actions">
        <a href="index.php?page=home" class="btn btn-primary">🏠 Về trang chủ</a>
        <a href="index.php?page=product_list" class="btn btn-outline">🛍️ Xem sản phẩm</a>
    </div>
</div>

<?php
require_once 'client/layouts/footer.php';
?>
