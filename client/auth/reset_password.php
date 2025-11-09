<?php
// FILE: client/auth/reset_password.php
// (Lưu ý: $pdo đã có sẵn từ file index.php)

$errors = [];

// 1. (BACK-END) BẢO VỆ TRANG
// Kiểm tra xem người dùng đã xác thực OTP chưa
if (!isset($_SESSION['email_verified_for_reset'])) {
    // Nếu chưa, đá về trang 1
    header('Location: index.php?page=forgot_password');
    exit;
}

// Lấy email đã được xác thực từ Session
$email_to_update = $_SESSION['email_verified_for_reset'];


// 2. (BACK-END) XỬ LÝ KHI NGƯỜI DÙNG NHẬP MẬT KHẨU MỚI
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $mat_khau = $_POST['mat_khau'] ?? '';
    $mat_khau_nhap_lai = $_POST['mat_khau_nhap_lai'] ?? '';

    // Validate mật khẩu
    if (empty($mat_khau)) {
        $errors[] = 'Mật khẩu là bắt buộc.';
    } elseif (strlen($mat_khau) < 6) {
        $errors[] = 'Mật khẩu phải có ít nhất 6 ký tự.';
    }
    if ($mat_khau !== $mat_khau_nhap_lai) {
        $errors[] = 'Mật khẩu nhập lại không khớp.';
    }

    // 3. NẾU MỌI THỨ OK, CẬP NHẬT MẬT KHẨU
    if (empty($errors)) {
        try {
            // Băm mật khẩu mới
            $hashed_password = password_hash($mat_khau, PASSWORD_DEFAULT);

            // Cập nhật bảng nguoi_dung
            $stmt_update = $pdo->prepare("UPDATE nguoi_dung SET mat_khau = ? WHERE email = ?");
            $stmt_update->execute([$hashed_password, $email_to_update]);

            // (Xóa session xác thực)
            unset($_SESSION['email_verified_for_reset']);

            // Chuyển hướng về trang Login với thông báo thành công
            header("Location: index.php?page=login&reset=success");
            exit;

        } catch (PDOException $e) {
            $errors[] = "Lỗi khi cập nhật mật khẩu: " . $e->getMessage();
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
</head>
<body>
    <div class="auth-card">
        <div class="auth-header">
            <a href="index.php?page=home" class="logo">HIShop</a>
            <h1>Tạo Mật Khẩu Mới</h1>
            <p>Tài khoản: <strong><?php echo htmlspecialchars($email_to_update); ?></strong></p>
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
        
        <form class="auth-form" method="POST" action="index.php?page=reset_password">
            <div class="form-group">
                <label for="mat_khau" class="form-label">Mật khẩu mới</label>
                <input type="password" id="mat_khau" name="mat_khau" class="form-input" placeholder="Tạo mật khẩu mới" required>
            </div>

            <div class="form-group">
                <label for="mat_khau_nhap_lai" class="form-label">Nhập lại mật khẩu mới</label>
                <input type="password" id="mat_khau_nhap_lai" name="mat_khau_nhap_lai" class="form-input" placeholder="Nhập lại mật khẩu mới" required>
            </div>
            
            <button type="submit" class="btn btn-primary">Lưu Mật Khẩu Mới</button>
        </form>

    </div>
</body>
</html>