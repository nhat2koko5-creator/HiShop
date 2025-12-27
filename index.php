<?php
session_start();

// Autoload
$autoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
} else {
    require_once __DIR__ . '/libs/PHPMailer/src/Exception.php';
    require_once __DIR__ . '/libs/PHPMailer/src/PHPMailer.php';
    require_once __DIR__ . '/libs/PHPMailer/src/SMTP.php';
}

// Core includes
require_once __DIR__ . '/src/config.php';
require_once __DIR__ . '/src/functions.php';

// Lấy danh mục cho header
$categories = getActiveCategories($pdo);

// ========== ROUTER ==========

// Lấy page
$page = $_GET['page'] ?? 'home';

// API giỏ hàng — chạy độc lập, không load header/footer
if ($page === 'cart_api') {
    require_once 'cart-handler.php';
    exit;
}

// 🔥🔥🔥 ROUTE CHO API GIỎ HÀNG — KHÔNG LOAD HEADER FOOTER 🔥🔥🔥
if ($page === 'cart_api') {
    require_once __DIR__ . '/cart-handler.php';
    exit;
}

// Logout
if ($page === 'logout') {
    session_destroy();
    header('Location: index.php?page=home');
    exit;
}

// Auth pages
$auth_pages = ['login', 'register', 'forgot_password', 'reset_password', 'verify_otp','verify_register'];

if (in_array($page, $auth_pages)) {
    $auth_file = "client/auth/{$page}.php";

    if (file_exists($auth_file)) {
        require_once $auth_file;
    } else {
        $page_title = "404 - Không Tìm Thấy";
        require_once "client/layouts/header.php";
        require_once "client/pages/404.php";
        require_once "client/layouts/footer.php";
    }
    exit;
}

// Trang chính
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
    case 'wishlist':
        $page_title = 'Sản phẩm yêu thích';
        $page_file = 'client/pages/wishlist.php';
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
    case 'vnpay_return':
    $page_title = 'Kết quả thanh toán';
    $page_file = 'client/pages/vnpay_return.php';
    break;
    case 'order_detail':
        $page_title = 'Chi Tiết Đơn Hàng';
        $page_file = 'client/pages/order_detail.php';
        break;

    default:
        $page_title = '404 - Không Tìm Thấy';
        $page_file = 'client/pages/404.php';
        break;
}
// (MỚI) BƯỚC 8.5: KIỂM TRA BẢO MẬT (TRƯỚC KHI TẢI HEADER)
$pages_that_require_login = ['checkout', 'account', 'process_payment', 'order_detail'];

if (in_array($page, $pages_that_require_login) && !isset($_SESSION['user_id'])) {
    // 1. Lấy toàn bộ query string hiện tại (Ví dụ: page=checkout&action=buy_now&variant_id=1...)
    $current_query = $_SERVER['QUERY_STRING'];
    
    // 2. Mã hóa URL đích để truyền an toàn qua URL
    $redirect_url = urlencode("index.php?" . $current_query);
    
    // 3. Chuyển hướng đến trang login kèm theo tham số 'redirect'
    header("Location: index.php?page=login&redirect=" . $redirect_url);
    exit; // Dừng lại ngay
}
// (MỚI) XỬ LÝ THANH TOÁN (PHẢI CHẠY TRƯỚC KHI IN HTML)
if ($page === 'process_vnpay') {
    $page_file = 'client/pages/process_vnpay.php';
    if (file_exists($page_file)) {
        // Chạy file xử lý thanh toán
        require_once $page_file; 
    }
    // File process_vnpay.php sẽ tự chứa header('Location:...') và exit;
    // nên chúng ta cũng exit ở đây để đảm bảo
    exit; 
}
// 9. "In" trang web ra
// 9.1. Tải Header (File này giờ đã có thể dùng biến $categories)
// Load giao diện
require_once 'client/layouts/header.php';

if (file_exists($page_file)) {
    require_once $page_file;
} else {
    require_once 'client/pages/404.php';
}

require_once 'client/layouts/footer.php';
?>
