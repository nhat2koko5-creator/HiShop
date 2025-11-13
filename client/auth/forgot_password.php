
<?php
// FILE: client/auth/forgot_password.php
// (Biến $pdo đã có sẵn từ file index.php)

$errors = [];
$success_message = null; 

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        $errors[] = "Vui lòng nhập email của bạn.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Email không hợp lệ.";
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT id, ho_ten FROM nguoi_dung WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                $otp = (string) rand(100000, 999999);
                
                // (XÓA DÒNG NÀY) $expires_at = date('Y-m-d H:i:s', time() + 600); 

                // 4. (ĐÃ SỬA LỖI) Lưu OTP vào CSDL
                $pdo->prepare("DELETE FROM dat_lai_mat_khau WHERE email = ?")->execute([$email]);
                
                // (ĐÃ SỬA LỖI) Dùng hàm DATE_ADD(NOW(), INTERVAL 10 MINUTE) của MySQL
                $sql = "INSERT INTO dat_lai_mat_khau (email, token, expires_at) 
                        VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))";
                
                $stmt = $pdo->prepare($sql);
                // (Chỉ cần truyền $email và $otp)
                $stmt->execute([$email, $otp]); 

                // 5. GỬI EMAIL CHỨA OTP
                $subject = "HIShop - Mã Xác Nhận Đặt Lại Mật Khẩu";
                $body = "
                    <p>Chào bạn " . htmlspecialchars($user['ho_ten']) . ",</p>
                    <p>Mã OTP để đặt lại mật khẩu của bạn là:</p>
                    <h1 style='font-size: 32px; letter-spacing: 5px; background-color: #f4f7fc; padding: 10px 20px; display: inline-block; border-radius: 8px;'>
                        " . $otp . "
                    </h1>
                    <p>Mã này sẽ hết hạn sau 10 phút. Vui lòng không chia sẻ mã này.</p>
                ";

                if (!sendEmail($email, $user['ho_ten'], $subject, $body)) {
                    $errors[] = "Không thể gửi email. Vui lòng thử lại sau.";
                }
            }

            if (empty($errors)) {
                header("Location: index.php?page=verify_otp&email=" . urlencode($email));
                exit;
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
</head>
<body>
    <div class="auth-card">
        <div class="auth-header">
            <a href="index.php?page=home" class="logo">HIShop</a>
            <h1>Quên Mật Khẩu</h1>
            <p>Nhập email của bạn và chúng tôi sẽ gửi mã OTP gồm 6 số.</p>
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

        <form class="auth-form" method="POST" action="index.php?page=forgot_password">
            <div class="form-group">
                <label for="email" class="form-label">Email</label>
                <input type="email" id="email" name="email" class="form-input" placeholder="ban@email.com" required>
            </div>
            <button type="submit" class="btn btn-primary">Gửi Mã OTP</button>
        </form>

        <div class="auth-footer">
            Nhớ mật khẩu? <a href="index.php?page=login" class="form-link">Đăng nhập</a>
        </div>
    </div>
</body>
</html>