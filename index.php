<?php
// FILE: index.php (Thư mục gốc)
session_start();
// 1. GỌI CONFIG (Tạo kết nối $pdo)
require_once 'src/config.php'; 

// 2. GỌI FUNCTIONS (Định nghĩa các hàm)
require_once 'src/functions.php'; 

// 3. (FIX LỖI) GỌI HÀM ĐỂ LẤY DỮ LIỆU CHUNG (HEADER)
// Chúng ta phải lấy $categories TRƯỚC KHI gọi header.php
$categories = getActiveCategories($pdo);

// --- PHẦN ĐIỀU HƯỚNG (ROUTER) ---

// 4. Lấy trang người dùng muốn xem
$page = $_GET['page'] ?? 'home';
if ($page === 'logout') {
    session_destroy(); // Hủy toàn bộ session
    header('Location: index.php?page=home'); // Chuyển về trang chủ
    exit;
}
// 5. Danh sách các trang "auth" (không dùng header/footer chung)
$auth_pages = ['login', 'register', 'forgot_password', 'reset_password'];

// 6. Xử lý trang "auth"
if (in_array($page, $auth_pages)) {
    $auth_file = "client/auth/{$page}.php";
    if (file_exists($auth_file)) {
        require_once $auth_file;
    } else {
        $page_file = 'client/pages/404.php';
        require_once 'client/layouts/header.php';
        require_once $page_file;
        require_once 'client/layouts/footer.php';
    }
    exit;
}
// 7. Xử lý các trang người dùng bình thường
switch ($page) {
    case 'home':
        $page_title = 'HIShop - Trang Chủ';
        $page_file = 'client/pages/home.php';
        break;
    case 'product_list':
        $page_title = 'Danh Sách Sản Phẩm';
        $page_file = 'client/pages/product_list.php';
        break;
    case 'product_detail':
        $page_title = 'Chi Tiết Sản Phẩm';
        $page_file = 'client/pages/product_detail.php';
        break;
    case 'cart':
        $page_title = 'Giỏ Hàng';
        $page_file = 'client/pages/cart.php';
        break;
    case 'checkout':
        $page_title = 'Thanh Toán';
        $page_file = 'client/pages/checkout.php';
        break;
    case 'confirmation':
        $page_title = 'Xác Nhận Đơn Hàng';
        $page_file = 'client/pages/confirmation.php';
        break;
    case 'contact':
        $page_title = 'Liên Hệ';
        $page_file = 'client/pages/contact.php';
        break;
    case 'static_about':
        $page_title = 'Về Chúng Tôi';
        $page_file = 'client/pages/static_about.php';
        break;
    case 'static_policy':
        $page_title = 'Chính Sách';
        $page_file = 'client/pages/static_policy.php';
        break;
    case 'search_results':
        $page_title = 'Kết Quả Tìm Kiếm';
        $page_file = 'client/pages/search_results.php';
        break;
   case 'account':
        $page_title = 'Tài Khoản Của Tôi';
        $page_file = 'client/pages/account.php';
        break;
    default:
        $page_title = '404 - Không Tìm Thấy';
        $page_file = 'client/pages/404.php';
        break;
}

// 8. "In" trang web ra
// 8.1. Tải Header (File này giờ đã có thể dùng biến $categories)
require_once 'client/layouts/header.php';

// 8.2. Tải Nội Dung Trang
if (file_exists($page_file)) {
    require_once $page_file;
} else {
    // Nếu file nội dung không tồn tại (lỗi code), hiển thị 404
    require_once 'client/pages/404.php';
}

// 8.3. Tải Footer
require_once 'client/layouts/footer.php';

?>