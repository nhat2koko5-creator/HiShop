<?php
// FILE: client/auth/verify_register.php

// Kiểm tra xem có dữ liệu đăng ký tạm không, nếu không thì đá về trang đăng ký
if (!isset($_SESSION['reg_temp_data'])) {
    header('Location: index.php?page=register');
    exit;
}

$userData = $_SESSION['reg_temp_data'];
$errors = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $otp_input = trim($_POST['otp'] ?? '');

    // 1. Kiểm tra OTP
    if (empty($otp_input)) {
        $errors[] = 'Vui lòng nhập mã OTP.';
    } elseif ($otp_input !== $userData['otp']) {
        $errors[] = 'Mã OTP không chính xác.';
    } elseif (time() > $userData['otp_time']) {
        $errors[] = 'Mã OTP đã hết hạn. Vui lòng đăng ký lại.';
    }

    // 2. NẾU OTP ĐÚNG -> LƯU VÀO CSDL (INSERT)
    if (empty($errors)) {
        try {
            $sql = "INSERT INTO nguoi_dung (ho_ten, email, so_dien_thoai, ngay_sinh, gioi_tinh, mat_khau, vai_tro_id, trang_thai) 
                    VALUES (?, ?, ?, ?, ?, ?, 2, 1)"; // 2: Khách hàng, 1: Hoạt động
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $userData['ho_ten'],
                $userData['email'],
                $userData['so_dien_thoai'],
                $userData['ngay_sinh'],
                $userData['gioi_tinh'],
                $userData['mat_khau'] // Đã hash ở bước trước
            ]);

            // Xóa session tạm
            unset($_SESSION['reg_temp_data']);

            // Chuyển hướng thành công (có thông báo)
            header("Location: index.php?page=login&register=success");
            exit;

        } catch (PDOException $e) {
            $errors[] = "Lỗi hệ thống: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xác thực OTP - HIShop</title>
    <link rel="stylesheet" href="assets/css/style-auth.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
</head>
<body>
    
    <header class="navbar">
        <div class="logo"><img src="assets/img/logo.png" alt="Hishop" class="brand-logo"></div>
        <nav class="nav-links">
            <a href="index.php?page=home">Trang chủ</a>
            <a href="index.php?page=product_list">Sản Phẩm</a>
        </nav>
    </header>

    <div class="background-container">
        <div class="login-modal-container">
            <div class="login-modal" style="max-width: 450px;">
                
                <div class="modal-header">
                    <h2>XÁC THỰC EMAIL</h2>
                    <p style="font-size: 14px; color: #666; margin-top: 5px; line-height: 1.5;">
                        Mã OTP 6 số đã được gửi đến:<br>
                        <strong style="color: #6A0DAD;"><?php echo htmlspecialchars($userData['email']); ?></strong>
                    </p>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="error-message">
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form class="login-form" method="POST" action="index.php?page=verify_register">
                    
                    <div class="input-group">
                        <span class="material-icons">vpn_key</span>
                        <input type="text" name="otp" placeholder="Nhập mã OTP (6 số)" maxlength="6" required autofocus 
                               style="letter-spacing: 2px; font-weight: bold; font-size: 16px;">
                    </div>
                    
                    <button type="submit" class="login-now-btn">Xác nhận</button>
                </form>

                <div class="signup-link">
                    Sai email? <a href="index.php?page=register">Đăng ký lại</a>
                </div>
            </div>
        </div>
    </div>

</body>
</html>