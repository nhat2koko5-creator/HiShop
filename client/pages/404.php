<?php
// FILE: client/pages/404.php
require_once 'client/layouts/header.php';
?>

<style>
body {
    font-family: "Inter", Arial, sans-serif;
    background-color: #f9fafb;
    color: #1f2937;
}

.error-container {
    max-width: 600px;
    margin: 100px auto;
    text-align: center;
    padding: 40px 20px;
}

.error-code {
    font-size: 96px;
    font-weight: 800;
    color: #0f62fe;
    line-height: 1;
    margin-bottom: 10px;
}

.error-title {
    font-size: 28px;
    font-weight: 700;
    color: #111827;
    margin-bottom: 10px;
}

.error-message {
    color: #6b7280;
    font-size: 16px;
    margin-bottom: 30px;
}

.error-actions {
    display: flex;
    justify-content: center;
    gap: 16px;
    flex-wrap: wrap;
}

.error-actions .btn {
    display: inline-block;
    padding: 12px 24px;
    border-radius: 8px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.2s ease;
}

.btn-primary {
    background-color: #0f62fe;
    color: #fff;
}

.btn-primary:hover {
    background-color: #0043ce;
}

.btn-outline {
    border: 2px solid #0f62fe;
    color: #0f62fe;
    background-color: transparent;
}

.btn-outline:hover {
    background-color: #0f62fe;
    color: #fff;
}

@media (max-width: 600px) {
    .error-code {
        font-size: 72px;
    }
    .error-title {
        font-size: 22px;
    }
}
</style>

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
