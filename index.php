<?php
session_start();

$autoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
} else {
    require_once __DIR__ . '/libs/PHPMailer/src/Exception.php';
    require_once __DIR__ . '/libs/PHPMailer/src/PHPMailer.php';
    require_once __DIR__ . '/libs/PHPMailer/src/SMTP.php';
}

require_once __DIR__ . '/src/config.php';
require_once __DIR__ . '/src/functions.php';
// 3. GỌI HÀM ĐỂ LẤY DỮ LIỆU CHUNG (HEADER)
$categories = getActiveCategories($pdo);

// --- PHẦN ĐIỀU HƯỚNG (ROUTER) ---

// 4. Lấy trang người dùng muốn xem
$page = $_GET['page'] ?? 'home';

// 5. Xử lý đăng xuất
if ($page === 'logout') {
    session_destroy();
    header('Location: index.php?page=home');
    exit;
}

// 6. Danh sách các trang "auth" (không dùng header/footer chung)
$auth_pages = ['login', 'register', 'forgot_password', 'reset_password', 'verify_otp'];

// 7. Xử lý trang "auth"
if (in_array($page, $auth_pages)) {
    $auth_file = "client/auth/{$page}.php";
    if (file_exists($auth_file)) {
        // (Biến $pdo đã có sẵn cho các file auth)
        require_once $auth_file;
    } else {
        // Tạm thời chuyển về trang 404 nếu file auth không tồn tại
        $page_title = '404 - Không Tìm Thấy';
        $page_file = 'client/pages/404.php';
        require_once 'client/layouts/header.php'; // Vẫn cần layout
        require_once $page_file;
        require_once 'client/layouts/footer.php';
    }
    exit; // Dừng lại, không chạy code bên dưới
}

// 8. Xử lý các trang người dùng bình thường
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
    case 'process_payment':
        $page_file = 'client/pages/process_payment.php';
    break;
    default:
        $page_title = '404 - Không Tìm Thấy';
        $page_file = 'client/pages/404.php';
        break;
}
// (MỚI) BƯỚC 8.5: KIỂM TRA BẢO MẬT (TRƯỚC KHI TẢI HEADER)
$pages_that_require_login = ['checkout', 'account', 'process_payment'];

if (in_array($page, $pages_that_require_login) && !isset($_SESSION['user_id'])) {
    // Người dùng chưa đăng nhập VÀ đang cố vào trang bảo mật
    header('Location: index.php?page=login');
    exit; // Dừng lại ngay
}
// 9. "In" trang web ra
// 9.1. Tải Header (File này giờ đã có thể dùng biến $categories)
require_once 'client/layouts/header.php';

// 9.2. Tải Nội Dung Trang
if (file_exists($page_file)) {
    require_once $page_file;
} else {
    // Nếu file nội dung không tồn tại (lỗi code), hiển thị 404
    require_once 'client/pages/404.php';
}

// 9.3. Tải Footer
require_once 'client/layouts/footer.php';

?>