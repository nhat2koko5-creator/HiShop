<?php
// FILE: client/auth/verify_otp.php
// (Lưu ý: $pdo đã có sẵn từ file index.php)

$errors = [];
$email = $_GET['email'] ?? null; // Lấy email từ URL

// Nếu không có email trên URL, quay về trang 1 (Quên mật khẩu)
if (!$email) {
    header('Location: index.php?page=forgot_password');
    exit;
}

// 1. (BACK-END) XỬ LÝ KHI NGƯỜI DÙNG NHẬP OTP
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $otp = trim($_POST['otp'] ?? '');
    $email_from_form = trim($_POST['email'] ?? '');

    if (empty($otp)) {
        $errors[] = 'Vui lòng nhập mã OTP.';
    } elseif (!is_numeric($otp) || strlen($otp) != 6) {
        $errors[] = 'Mã OTP phải là 6 chữ số.';
    }
    
    // Kiểm tra bảo mật: Email form gửi lên phải khớp với email trên URL
    if ($email !== $email_from_form) {
        $errors[] = 'Lỗi bảo mật: Email không khớp.';
    }

    if (empty($errors)) {
        try {
            // 2. Kiểm tra OTP trong CSDL (Bảng dat_lai_mat_khau)
            $sql = "SELECT * FROM dat_lai_mat_khau WHERE email = ? AND token = ? AND expires_at > NOW()";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$email_from_form, $otp]);
            $token_data = $stmt->fetch();

            if ($token_data) {
                // 3. OTP đúng -> Lưu xác nhận vào Session để sang bước Đổi mật khẩu
                $_SESSION['email_verified_for_reset'] = $email_from_form;
                
                // Chuyển hướng sang trang Đặt lại mật khẩu
                header("Location: index.php?page=reset_password");
                exit;
            } else {
                $errors[] = "Mã OTP không chính xác hoặc đã hết hạn.";
            }
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
    <title>Nhập mã OTP - HIShop</title>
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
                    <h2>NHẬP MÃ OTP</h2>
                    <p style="font-size: 14px; color: #666; margin-top: 5px; line-height: 1.5;">
                        Chúng tôi đã gửi mã 6 số đến:<br>
                        <strong style="color: #6A0DAD;"><?php echo htmlspecialchars($email); ?></strong>
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

                <form class="login-form" method="POST" action="index.php?page=verify_otp&email=<?php echo htmlspecialchars($email); ?>">
                    
                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">

                    <div class="input-group">
                        <span class="material-icons">vpn_key</span>
                        <input type="text" id="otp" name="otp" placeholder="Nhập mã OTP (6 số)" maxlength="6" required autofocus 
                               style="letter-spacing: 2px; font-weight: bold; font-size: 16px;">
                    </div>
                    
                    <button type="submit" class="login-now-btn">Xác Nhận</button>
                </form>

                <div class="signup-link">
                    Không nhận được mã? <a href="index.php?page=forgot_password">Gửi lại</a>
                </div>
            </div>
        </div>
    </div>

</body>
</html>