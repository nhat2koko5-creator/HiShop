<?php

// 1. Xác định trang người dùng muốn xem
// Nếu không có ?page=... trên URL, mặc định là trang 'home'
$page = $_GET['page'] ?? 'home';

// 2. Danh sách các trang "auth" (Đăng nhập/Ký)
// Các trang này không dùng header/footer chung
$auth_pages = ['login', 'register', 'forgot_password', 'reset_password'];

// 3. Kiểm tra xem có phải trang "auth" không
if (in_array($page, $auth_pages)) {

    // (Code để xử lý trang auth sẽ nằm ở đây)
    // Ví dụ: gọi file auth tương ứng
    $auth_file = "client/auth/{$page}.php";
    if (file_exists($auth_file)) {
        require_once $auth_file;
    } else {
        // Nếu file không tồn tại, báo lỗi 404
        require_once 'client/pages/404.php';
    }
    
    // Dừng kịch bản tại đây, không tải header/footer chung
    exit;
}

// 4. NẾU LÀ TRANG BÌNH THƯỜNG (dùng header/footer chung)

// Đặt tiêu đề (title) cho mỗi trang
switch ($page) {
    case 'cart':
        $page_title = 'Giỏ Hàng Của Bạn';
        $page_file = 'client/pages/cart.php';
        break;
    case 'product_detail':
        $page_title = 'Chi Tiết Sản Phẩm';
        $page_file = 'client/pages/product_detail.php';
        break;
    case 'checkout':
        $page_title = 'Thanh Toán';
        $page_file = 'client/pages/checkout.php';
        break;
    case 'contact':
        $page_title = 'Liên Hệ';
        $page_file = 'client/pages/contact.php';
        break;
    case 'product_list':
        $page_title = 'Danh Sách Sản Phẩm';
        $page_file = 'client/pages/product_list.php';
        break;
    // (Bạn tự thêm các case khác cho static_about, static_policy...)
    
    case 'home': // Trang chủ mặc định
    default:
        $page_title = 'HIShop - Trang Chủ';
        $page_file = 'client/pages/home.php';
        break;
}

// 5. Bắt đầu "in" ra trang web
// 5.1. Gọi Header
require_once 'client/layouts/header.php';

// 5.2. Gọi Nội Dung Trang (đã xác định ở trên)
if (file_exists($page_file)) {
    require_once $page_file;
} else {
    // Nếu file nội dung không tồn tại, hiển thị trang 404
    require_once 'client/pages/404.php';
}

// 5.3. Gọi Footer
require_once 'client/layouts/footer.php';

?>