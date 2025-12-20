<?php
// FILE: client/auth/forgot_password.php
// (Biến $pdo đã có sẵn từ file index.php)

$errors = [];
$success_message = null; 

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email'] ?? '');

    // 1. Validate Email
    if (empty($email)) {
        $errors[] = "Vui lòng nhập email của bạn.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Email không hợp lệ.";
    }

    if (empty($errors)) {
        try {
            // 2. Kiểm tra Email có tồn tại trong hệ thống không
            $stmt = $pdo->prepare("SELECT id, ho_ten FROM nguoi_dung WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                // 3. Tạo mã OTP
                $otp = (string) rand(100000, 999999);
                
                // 4. Xóa mã OTP cũ (nếu có) để tránh rác DB
                $pdo->prepare("DELETE FROM dat_lai_mat_khau WHERE email = ?")->execute([$email]);
                
                // 5. Lưu mã OTP mới vào CSDL (Hết hạn sau 10 phút)
                $stmt_insert = $pdo->prepare("INSERT INTO dat_lai_mat_khau (email, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))");
                $stmt_insert->execute([$email, $otp]);

                // --- [ĐOẠN MỚI THÊM] GỬI EMAIL OTP ---
                $subject = "[HIShop] Mã xác thực lấy lại mật khẩu";
                $body = "Xin chào <b>" . htmlspecialchars($user['ho_ten']) . "</b>,<br>Bạn vừa yêu cầu lấy lại mật khẩu.<br>Mã xác thực (OTP) của bạn là: <b style='font-size:20px;color:red'>$otp</b>.<br>Mã này có hiệu lực trong 10 phút.";
                
                if (sendMail($email, $subject, $body)) {
                    // Gửi thành công -> Chuyển hướng sang trang nhập OTP
                    header("Location: index.php?page=verify_otp&email=" . urlencode($email));
                    exit;
                } else {
                    $errors[] = "Lỗi: Không thể gửi email. Vui lòng kiểm tra lại cấu hình server.";
                }
                // -------------------------------------

            } else {
                // Để bảo mật, có thể thông báo chung chung hoặc báo lỗi email không tồn tại
                $errors[] = "Email này chưa được đăng ký trong hệ thống.";
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
    <title>Quên Mật Khẩu - HIShop</title>
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
                    <h2>QUÊN MẬT KHẨU</h2>
                    <p style="font-size: 14px; color: #666; margin-top: 5px;">
                        Nhập email đã đăng ký để nhận mã xác thực.
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

                <form class="login-form" method="POST" action="index.php?page=forgot_password">
                    
                    <div class="input-group">
                        <span class="material-icons">mail_outline</span>
                        <input type="email" id="email" name="email" class="form-input" placeholder="Nhập email của bạn" required
                               value="<?= htmlspecialchars($email ?? '') ?>">
                    </div>

                    <button type="submit" class="login-now-btn">Gửi Mã OTP</button>
                </form>

                <div class="signup-link">
                    Nhớ mật khẩu? <a href="index.php?page=login">Đăng nhập ngay</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>