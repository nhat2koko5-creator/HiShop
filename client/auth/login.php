<?php
// FILE: client/auth/login.php
// (Lưu ý: $pdo đã có sẵn từ file index.php)
// (Lưu ý: session_start() đã được gọi ở index.php)

// (ĐÃ SỬA) Thống nhất dùng tên $errors
$errors = []; 

// 1. KIỂM TRA NẾU NGƯỜI DÙNG NHẤN NÚT "ĐĂNG NHẬP"
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // 2. LẤY DỮ LIỆU TỪ FORM
    $email = trim($_POST['email'] ?? '');
    $mat_khau = $_POST['mat_khau'] ?? '';

    // 3. KIỂM TRA (VALIDATE) DỮ LIỆU
    if (empty($email)) {
        $errors[] = 'Vui lòng nhập email.';
    }
    if (empty($mat_khau)) {
        $errors[] = 'Vui lòng nhập mật khẩu.';
    }

    // 4. NẾU KHÔNG CÓ LỖI VALIDATE, BẮT ĐẦU KIỂM TRA CSDL
    if (empty($errors)) {
        try {
            // 5. Tìm người dùng bằng email
            $stmt = $pdo->prepare("SELECT * FROM nguoi_dung WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            // 6. KIỂM TRA NGƯỜI DÙNG VÀ MẬT KHẨU
            if ($user && password_verify($mat_khau, $user['mat_khau'])) {
                
                // 7. ĐĂNG NHẬP THÀNH CÔNG!
                session_regenerate_id(true); 
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['ho_ten'];
                $_SESSION['user_role_id'] = $user['vai_tro_id'];
                
                // 8. Chuyển hướng
                header("Location: index.php?page=home");
                exit;

            } else {
                // 9. ĐĂNG NHẬP THẤT BẠI
                // (ĐÃ SỬA) Thêm lỗi vào mảng $errors
                $errors[] = 'Email hoặc mật khẩu không chính xác.';
            }
        } catch (PDOException $e) {
            $errors[] = "Lỗi CSDL: " . $e->getMessage();
        }
    }
} // Kết thúc xử lý POST
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng Nhập - HIShop</title>
    <link rel="stylesheet" href="assets/css/style-auth.css">
</head>
<body>

    <div class="auth-card">
        <div class="auth-header">
            <a href="index.php?page=home" class="logo">HIShop</a>
            <h1>Đăng Nhập</h1>
            <p>Chào mừng bạn quay trở lại.</p>
        </div>

        <?php if (isset($_GET['register']) && $_GET['register'] == 'success'): ?>
            <div class="error-message" style="background-color: #D1FAE5; color: #065F46; border-color: #6EE7B7;">
                <p>✅ Đăng ký tài khoản thành công! Vui lòng đăng nhập.</p>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['reset']) && $_GET['reset'] == 'success'): ?>
            <div class="error-message" style="background-color: #D1FAE5; color: #065F46; border-color: #6EE7B7;">
                <p>✅ Đặt lại mật khẩu thành công! Vui lòng đăng nhập.</p>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="error-message">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form class="auth-form" method="POST" action="index.php?page=login">
            
            <div class="form-group">
                <label for="email" class="form-label">Email</label>
                <input type="email" id="email" name="email" class="form-input" placeholder="ban@email.com" required>
            </div>
            
            <div class="form-group">
                <label for="mat_khau" class="form-label">Mật khẩu</label>
                <input type="password" id="mat_khau" name="mat_khau" class="form-input" placeholder="Nhập mật khẩu của bạn" required>
            </div>
            
            <a href="index.php?page=forgot_password" class="form-link" style="text-align: right;">Quên mật khẩu?</a>

            <button type="submit" class="btn btn-primary">Đăng Nhập</button>
        </form>

        <div class="auth-footer">
            Chưa có tài khoản? <a href="index.php?page=register" class="form-link">Tạo tài khoản ngay</a>
        </div>
    </div>
    
</body>
</html>