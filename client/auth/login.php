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
            $stmt = $pdo->prepare("SELECT * FROM nguoi_dung WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            // 6. KIỂM TRA NGƯỜI DÙNG VÀ MẬT KHẨU
            if ($user && password_verify($mat_khau, $user['mat_khau'])) {
                
                // [MỚI] KIỂM TRA TRẠNG THÁI TÀI KHOẢN
                if ($user['trang_thai'] == 0) {
                    // Nếu bị khóa -> Báo lỗi và không cho Login
                    $reason = $user['ly_do_khoa'] ?? 'Vi phạm chính sách cộng đồng.';
                    $errors[] = "Tài khoản của bạn đã bị vô hiệu hóa.<br><strong>Lý do:</strong> " . htmlspecialchars($reason);
                } 
                else {
                    // [NẾU KHÔNG BỊ KHÓA -> ĐĂNG NHẬP BÌNH THƯỜNG]
                    
                    session_regenerate_id(true); 
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['ho_ten'];
                    $_SESSION['user_role_id'] = $user['vai_tro_id'];
                    
                    if ($user['vai_tro_id'] == 1) {
                        header("Location: admin/index.php");
                    } else {
                        header("Location: index.php?page=home");
                    }
                    exit; 
                }

            } else {
                $errors[] = 'Email hoặc mật khẩu không chính xác.';
            }
        } catch (PDOException $e) {
            $errors[] = "Lỗi CSDL: " . $e->getMessage();
        }
    }if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM nguoi_dung WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            // 6. KIỂM TRA NGƯỜI DÙNG VÀ MẬT KHẨU
            if ($user && password_verify($mat_khau, $user['mat_khau'])) {
                
                // [MỚI] KIỂM TRA TRẠNG THÁI TÀI KHOẢN
                if ($user['trang_thai'] == 0) {
                    // Nếu bị khóa -> Báo lỗi và không cho Login
                    $reason = $user['ly_do_khoa'] ?? 'Vi phạm chính sách cộng đồng.';
                    $errors[] = "Tài khoản của bạn đã bị vô hiệu hóa.<br><strong>Lý do:</strong> " . htmlspecialchars($reason);
                } 
                else {
                    // [NẾU KHÔNG BỊ KHÓA -> ĐĂNG NHẬP BÌNH THƯỜNG]
                    
                    session_regenerate_id(true); 
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['ho_ten'];
                    $_SESSION['user_role_id'] = $user['vai_tro_id'];
                    
                    if ($user['vai_tro_id'] == 1) {
                        header("Location: admin/index.php");
                    } else {
                        header("Location: index.php?page=home");
                    }
                    exit; 
                }

            } else {
                $errors[] = 'Email hoặc mật khẩu không chính xác.';
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
    <title>Đăng Nhập - HISHOP</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style-auth.css">
</head>
<body>
    
    <header class="navbar">
        <div class="logo"><img src="assets/img/logo.png" alt="Hishop" class="brand-logo"></div>
        <nav class="nav-links">
            <a href="index.php?page=home">Trang chủ</a>
            <a href="index.php?page=product_list">Sản Phẩm</a>
            <a href="#"></a>
            <a href="#">Về chúng tôi</a>
            <a href="#">Liên hệ</a>
        </nav>
        <a href="index.php?page=register"><button class="login-btn">Đăng ký</button></a>
    </header>

    <div class="background-container">
        <div class="login-modal-container">
            <div class="login-modal">
                <div class="modal-header">
                    <h2>ĐĂNG NHẬP</h2>
                </div>

                <?php if (isset($_GET['register']) && $_GET['register'] == 'success'): ?>
                    <div class="success-message">
                        <p>✅ Đăng ký tài khoản thành công! Vui lòng đăng nhập.</p>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_GET['reset']) && $_GET['reset'] == 'success'): ?>
                    <div class="success-message">
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

                <form class="login-form" method="POST" action="index.php?page=login">
                    
                    <div class="input-group">
                        <span class="material-icons">mail_outline</span>
                        <input type="email" id="email" name="email" placeholder="Enter your email" required
                               value="<?php echo htmlspecialchars($email ?? ''); ?>">
                    </div>
                    
                    <div class="input-group">
                        <span class="material-icons">lock_open</span>
                        <input type="password" id="mat_khau" name="mat_khau" placeholder="Enter you password" required>
                    </div>
                    
                    <div class="options">
                        <label>
                            <input type="checkbox"> Nhớ tài khoản
                        </label>
                        <a href="index.php?page=forgot_password" class="forgot-password">Quên mật khẩu?</a>
                    </div>

                    <button type="submit" class="login-now-btn">Đăng nhập</button>
                </form>

                <div class="signup-link">
                    Chưa có tài khoản? <a href="index.php?page=register">Đăng ký</a>
                </div>
                
                <div class="auth-header-hidden" style="display: none;">
                    <a href="index.php?page=home" class="logo-hidden">HIShop</a>
                    <h1>Đăng Nhập</h1>
                    <p>Chào mừng bạn quay trở lại.</p>
                </div>
                <div class="auth-footer-hidden" style="display: none;">
                    Chưa có tài khoản? <a href="index.php?page=register" class="form-link">Tạo tài khoản ngay</a>
                </div>
                </div>
        </div>
    </div>
    <style>

    </style>
</body>
</html>