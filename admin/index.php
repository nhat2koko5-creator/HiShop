<?php
// FILE: admin/index.php

session_start(); // Luôn bắt đầu session

// 1. GỌI CONFIG & FUNCTIONS (Dùng ../ để lùi 1 cấp)
require_once '../src/config.php'; 
require_once '../src/functions.php'; 

// 2. (BACK-END) BẢO VỆ TRANG ADMIN
// Kiểm tra xem user đã đăng nhập chưa VÀ có phải là Admin không
// (Giả sử 1 = Admin trong bảng vai_tro)
if (!isset($_SESSION['user_id']) || $_SESSION['user_role_id'] != 1) {
    // Nếu không phải Admin, đá về trang chủ
    header('Location: ../index.php');
    exit;
}

// 3. Lấy trang admin muốn xem
$page = $_GET['page'] ?? 'dashboard';

// 4. Xác định tiêu đề và file nội dung (cho switch)
switch ($page) {
    case 'product_form':
    $page_title = 'Quản lý sản phẩm';
    $page_file = 'pages/product_form.php';
    break;

    case 'products_list':
        $page_title = 'Quản lý Sản Phẩm';
        $page_file = 'pages/products_list.php';
        break;
    case 'product_sale': 
        $page_title = 'Quản lý Sản Phẩm Giảm Giá';
        $page_file = 'pages/product_sale.php';
        break;


    case 'orders_list':
        $page_title = 'Quản lý Đơn Hàng';
        $page_file = 'pages/orders_list.php';
        break;
    case 'reports':
        $page_title = 'Báo cáo';
        $page_file = 'pages/reports.php';
        break;

    case 'order_detail':
        $page_title = 'Chi tiết Đơn Hàng';
        $page_file = 'pages/order_detail.php';
        break;

    case 'categories_list':
        $page_title = 'Quản lý Danh Mục';
        $page_file = 'pages/categories_list.php';
        break;

    case 'promos_list':
        $page_title = 'Quản lý Khuyến Mãi';
        $page_file = 'pages/promos_list.php';
        break;
    case 'discount_list':  // Tên này phải trùng với tham số ?page=discount_list ở URL
        $page_title = 'Quản lý Khuyến Mãi';
        $page_file = 'pages/promos_list.php'; // Trỏ đúng đến file code vừa tạo
        break;

    case 'users_list':
        $page_title = 'Quản lý Người Dùng';
        $page_file = 'pages/users_list.php';
        break;

   case 'warehouse_list':
        $page_title = 'Quản lý Kho Hàng';
        $page_file = 'pages/warehouse_list.php';
        break;

    case 'warehouse_detail':
        $page_title = 'Chi tiết Kho Hàng';
        $page_file = 'pages/warehouse_detail.php';
        break;

    case 'warehouse_import':
        $page_title = 'Tạo Phiếu Nhập Kho';
        $page_file = 'pages/warehouse_import.php';
        break;

    case 'warehouse_export':
        $page_title = 'Tạo Phiếu Xuất Kho';
        $page_file = 'pages/warehouse_export.php';
        break;
    case 'warehouse_history':
        $page_title = 'Lịch Sử Nhập/Xuất Kho';
        $page_file = 'pages/warehouse_history.php'; // Đúng: Gán đường dẫn vào biến để index xử lý layout sau
        break;

    case 'repost_revenue':
        $page_title = 'Báo Cáo Doanh Thu';
        $page_file = 'pages/repost_revenue.php'; // Đúng: Gán đường dẫn vào biến để index xử lý layout sau
        break;
    case 'dashboard':
    default:
        $page_title = 'Trang chủ';
        $page_file = 'pages/dashboard.php';
        break;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - <?php echo htmlspecialchars($page_title); ?></title>
    <link rel="stylesheet" href="../assets/css/style-admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

    <div class="admin-layout">
        
        <?php
        // 1. Tải Sidebar (Menu trái)
        require_once 'layouts/sidebar.php'; 
        ?>

        <main class="admin-main-content">
            
            <?php
            // 2.1. Tải Header (Topbar)
            require_once 'layouts/header.php';
            
            // 2.2. Tải Nội Dung Trang (Dashboard, Products, v.v.)
            if (file_exists($page_file)) {
                require_once $page_file;
            } else {
                echo '<div class="admin-page-content"><p>Lỗi: Không tìm thấy file nội dung!</p></div>';
            }
            ?>

        </main> </div> </body>
</html>