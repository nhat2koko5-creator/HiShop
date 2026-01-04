<?php
// FILE: client/auth/login.php

$errors = []; 

// 1. LẤY URL CHUYỂN HƯỚNG (NẾU CÓ)
// Ưu tiên lấy từ POST (khi vừa submit form), nếu không có thì lấy từ GET (khi vừa được redirect tới)
$redirect_to = $_POST['redirect'] ?? $_GET['redirect'] ?? '';

// KIỂM TRA NẾU NGƯỜI DÙNG NHẤN NÚT "ĐĂNG NHẬP"
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // 2. LẤY DỮ LIỆU TỪ FORM
    $email = trim($_POST['email'] ?? '');
    $mat_khau = $_POST['mat_khau'] ?? '';

    // 3. VALIDATE
    if (empty($email)) $errors[] = 'Vui lòng nhập email.';
    if (empty($mat_khau)) $errors[] = 'Vui lòng nhập mật khẩu.';

    // 4. XỬ LÝ ĐĂNG NHẬP
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM nguoi_dung WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($mat_khau, $user['mat_khau'])) {
                
                // CHECK TRẠNG THÁI
                if ($user['trang_thai'] == 0) {
                    $reason = $user['ly_do_khoa'] ?? 'Vi phạm chính sách cộng đồng.';
                    $errors[] = "Tài khoản bị vô hiệu hóa.<br>Lý do: " . htmlspecialchars($reason);
                } 
                else {
                    // --- ĐĂNG NHẬP THÀNH CÔNG ---
                    session_regenerate_id(true); 
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['ho_ten'];
                    $_SESSION['user_role_id'] = $user['vai_tro_id'];
                    
                    // --- [LOGIC MỚI] ĐIỀU HƯỚNG ---
                    if ($user['vai_tro_id'] == 1) {
                        // Admin luôn về trang admin
                        header("Location: admin/index.php");
                    } else {
                        // Khách hàng: Nếu có link redirect hợp lệ thì quay lại đó, còn không thì về Home
                        if (!empty($redirect_to)) {
                            // Decode lại URL trước khi chuyển hướng
                            header("Location: " . urldecode($redirect_to));
                        } else {
                            header("Location: index.php?page=home");
                        }
                    }
                    exit; 
                }

            } else {
                $errors[] = 'Email hoặc mật khẩu không chính xác.';
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
        </nav>
        <a href="index.php?page=register"><button class="login-btn">Đăng ký</button></a>
    </header>

    <div class="background-container">
        <div class="login-modal-container">
            <div class="login-modal">
                <div class="modal-header">
                    <h2>ĐĂNG NHẬP</h2>
                    <?php if(!empty($redirect_to)): ?>
                        <p style="font-size: 13px; color: #0f62fe; margin-top: 5px;">
                            Vui lòng đăng nhập để tiếp tục thanh toán
                        </p>
                    <?php endif; ?>
                </div>

                <?php if (isset($_GET['register']) && $_GET['register'] == 'success'): ?>
                    <div class="success-message"><p>✅ Đăng ký thành công! Vui lòng đăng nhập.</p></div>
                <?php endif; ?>
                
                <?php if (isset($_GET['reset']) && $_GET['reset'] == 'success'): ?>
                    <div class="success-message"><p>✅ Đặt lại mật khẩu thành công!</p></div>
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
                    
                    <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect_to); ?>">

                    <div class="input-group">
                        <span class="material-icons">mail_outline</span>
                        <input type="email" id="email" name="email" placeholder="Email của bạn" required
                               value="<?php echo htmlspecialchars($email ?? ''); ?>">
                    </div>
                    
                    <div class="input-group">
                        <span class="material-icons">lock_open</span>
                        <input type="password" id="mat_khau" name="mat_khau" placeholder="Mật khẩu" required>
                    </div>
                    
                    <div class="options">
                        <label><input type="checkbox"> Nhớ tài khoản</label>
                        <a href="index.php?page=forgot_password" class="forgot-password">Quên mật khẩu?</a>
                    </div>

                    <button type="submit" class="login-now-btn">Đăng nhập</button>
                </form>

                <div class="signup-link">
                    Chưa có tài khoản? <a href="index.php?page=register">Đăng ký ngay</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>