<?php
// FILE: client/auth/reset_password.php
// (Lưu ý: $pdo đã có sẵn từ file index.php)

$errors = [];

// 1. (BACK-END) BẢO VỆ TRANG
// Kiểm tra xem người dùng đã xác thực OTP thành công ở bước trước chưa
if (!isset($_SESSION['email_verified_for_reset'])) {
    // Nếu chưa, đá về trang nhập email ban đầu
    header('Location: index.php?page=forgot_password');
    exit;
}

// Lấy email đã được xác thực từ Session
$email_to_update = $_SESSION['email_verified_for_reset'];


// 2. (BACK-END) XỬ LÝ KHI NGƯỜI DÙNG SUBMIT MẬT KHẨU MỚI
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $mat_khau = $_POST['mat_khau'] ?? '';
    $mat_khau_nhap_lai = $_POST['mat_khau_nhap_lai'] ?? '';

    // Validate dữ liệu
    if (empty($mat_khau)) {
        $errors[] = 'Vui lòng nhập mật khẩu mới.';
    } elseif (strlen($mat_khau) < 6) {
        $errors[] = 'Mật khẩu phải có ít nhất 6 ký tự.';
    }
    
    if ($mat_khau !== $mat_khau_nhap_lai) {
        $errors[] = 'Mật khẩu nhập lại không khớp.';
    }

    if (empty($errors)) {
        try {
            // A. Mã hóa mật khẩu
            $hashed_password = password_hash($mat_khau, PASSWORD_DEFAULT);

            // B. Cập nhật mật khẩu mới vào bảng nguoi_dung
            $stmt = $pdo->prepare("UPDATE nguoi_dung SET mat_khau = ? WHERE email = ?");
            $stmt->execute([$hashed_password, $email_to_update]);

            // C. Xóa mã OTP cũ trong bảng dat_lai_mat_khau để không dùng lại được
            $pdo->prepare("DELETE FROM dat_lai_mat_khau WHERE email = ?")->execute([$email_to_update]);

            // D. Xóa session xác thực
            unset($_SESSION['email_verified_for_reset']);

            // E. Chuyển hướng về trang Login với thông báo thành công
            header("Location: index.php?page=login&reset=success");
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
    <title>Đặt Lại Mật Khẩu - HIShop</title>
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
                    <h2>TẠO MẬT KHẨU MỚI</h2>
                    <p style="font-size: 14px; color: #666; margin-top: 5px;">
                        Tài khoản: <strong style="color: #6A0DAD;"><?php echo htmlspecialchars($email_to_update); ?></strong>
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

                <form class="login-form" method="POST" action="index.php?page=reset_password">
                    
                    <div class="input-group">
                        <span class="material-icons">lock_outline</span>
                        <input type="password" name="mat_khau" placeholder="Mật khẩu mới (Min 6 ký tự)" required autofocus>
                    </div>

                    <div class="input-group">
                        <span class="material-icons">lock</span>
                        <input type="password" name="mat_khau_nhap_lai" placeholder="Nhập lại mật khẩu mới" required>
                    </div>
                    
                    <button type="submit" class="login-now-btn">Đổi Mật Khẩu</button>
                </form>

                <div class="signup-link">
                    <a href="index.php?page=login">Quay lại Đăng nhập</a>
                </div>
            </div>
        </div>
    </div>

</body>
</html>