<?php
// FILE: client/auth/verify_otp.php
// (Lưu ý: $pdo đã có sẵn từ file index.php)

$errors = [];
$email = $_GET['email'] ?? null; // Lấy email từ URL

// Nếu không có email trên URL, quay về trang 1
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
    if ($email !== $email_from_form) {
        $errors[] = 'Lỗi bảo mật (Email không khớp).';
    }

    if (empty($errors)) {
        try {
            // 2. Kiểm tra OTP trong CSDL
            // (Chúng ta lưu OTP trong cột 'token')
            $sql = "SELECT * FROM dat_lai_mat_khau WHERE email = ? AND token = ? AND expires_at > NOW()";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$email, $otp]);
            $request = $stmt->fetch();

            if ($request) {
                // 3. XÁC THỰC THÀNH CÔNG!
                
                // (MỚI) Lưu trạng thái đã xác thực vào SESSION
                $_SESSION['email_verified_for_reset'] = $email;
                
                // (Xóa OTP đã dùng)
                $pdo->prepare("DELETE FROM dat_lai_mat_khau WHERE email = ?")->execute([$email]);

                // 4. Chuyển đến trang 3 (Đặt Lại Mật Khẩu)
                header("Location: index.php?page=reset_password");
                exit;
            } else {
                // 5. THẤT BẠI
                $errors[] = "Mã OTP không đúng hoặc đã hết hạn.";
            }
        } catch (PDOException $e) {
            $errors[] = "Lỗi CSDL: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xác Nhận OTP - HIShop</title>
    <link rel="stylesheet" href="assets/css/style-auth.css">
</head>
<body>
    <div class="auth-card">
        <div class="auth-header">
            <a href="index.php?page=home" class="logo">HIShop</a>
            <h1>Nhập Mã OTP</h1>
            <p>Chúng tôi đã gửi mã 6 số đến <strong><?php echo htmlspecialchars($email); ?></strong>. Vui lòng kiểm tra email.</p>
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

        <form class="auth-form" method="POST" action="index.php?page=verify_otp&email=<?php echo htmlspecialchars($email); ?>">
            
            <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">

            <div class="form-group">
                <label for="otp" class="form-label">Mã OTP (6 số)</label>
                <input type="text" id="otp" name="otp" class="form-input" placeholder="123456" maxlength="6" required>
            </div>
            
            <button type="submit" class="btn btn-primary">Xác Nhận</button>
        </form>

        <div class="auth-footer">
            Không nhận được mã? <a href="index.php?page=forgot_password" class="form-link">Gửi lại</a>
        </div>
    </div>
</body>
</html>