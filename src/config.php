<?php
// FILE: src/config.php

// 1. Thông tin kết nối CSDL
$db_host = 'localhost';
$db_name = 'hishop_db'; // Tên CSDL của bạn
$db_user = 'root';
$db_pass = ''; // Mật khẩu mặc định của XAMPP là rỗng

// 2. Tùy chọn cho PDO
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Bật báo lỗi
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Trả về dữ liệu dạng mảng
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4'     // Hỗ trợ tiếng Việt
];

// 3. Khởi tạo kết nối PDO
// Biến $pdo sẽ là "cầu nối" đến CSDL của bạn
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass, $options);
} catch (\PDOException $e) {
    // Nếu kết nối thất bại, dừng chương trình
    die("Lỗi kết nối CSDL: " . $e->getMessage());
}
//  THÊM CẤU HÌNH EMAIL
define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_USERNAME', 'nhat2koko5@gmail.com'); // <<< THAY BẰNG EMAIL CỦA BẠN
define('MAIL_PASSWORD', 'ueib vhxq ohat aevj'); // <<< THAY BẰNG MẬT KHẨU 16 CHỮ CÁI
define('MAIL_FROM_NAME', 'HIShop');

define('VNP_TMN_CODE', 'NJJ0R8FS'); 
define('VNP_HASH_SECRET', 'BYKJBHPPZKQMKBIBGGXIYKWYFAYSJXCW');
define('VNP_URL', 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html');
define('VNP_RETURN_URL', 'http://localhost/HISHOP/index.php?page=vnpay_return');
?>