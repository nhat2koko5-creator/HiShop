<?php
// FILE: client/auth/register.php

$errors = [];

// Khởi tạo biến rỗng để tránh lỗi nếu form chưa submit
$ho_ten = '';
$email = '';
$sdt = '';
$ngay_sinh = '';
$gioi_tinh = '';

// 1. XỬ LÝ KHI NGƯỜI DÙNG BẤM ĐĂNG KÝ
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Lấy dữ liệu từ form
    $ho_ten = trim($_POST['ho_ten'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $sdt = trim($_POST['so_dien_thoai'] ?? '');
    $ngay_sinh = $_POST['ngay_sinh'] ?? null;
    $gioi_tinh = $_POST['gioi_tinh'] ?? 'other';
    $mat_khau = $_POST['mat_khau'] ?? '';
    $mat_khau_nhap_lai = $_POST['mat_khau_nhap_lai'] ?? '';

    // --- VALIDATE DỮ LIỆU ---
    if (empty($ho_ten)) $errors[] = 'Họ và tên là bắt buộc.';
    if (empty($email)) $errors[] = 'Email là bắt buộc.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email không hợp lệ.';
    
    if (empty($mat_khau)) $errors[] = 'Mật khẩu là bắt buộc.';
    elseif (strlen($mat_khau) < 6) $errors[] = 'Mật khẩu phải có ít nhất 6 ký tự.';
    
    if ($mat_khau !== $mat_khau_nhap_lai) $errors[] = 'Mật khẩu nhập lại không khớp.';

    // --- KIỂM TRA EMAIL ĐÃ TỒN TẠI CHƯA ---
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM nguoi_dung WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = 'Email này đã được đăng ký.';
        }
    }

    // --- [ĐÂY LÀ CHỖ THAY ĐỔI CHÍNH] ---
    // Thay vì INSERT vào DB ngay, ta lưu vào Session và gửi OTP
    if (empty($errors)) {
        // 1. Tạo mã OTP
        $otp = (string) rand(100000, 999999);

        // 2. Gửi Email
        $subject = "[HIShop] Xác thực đăng ký";
        $body = "Xin chào <b>$ho_ten</b>,<br>Mã xác thực (OTP) của bạn là: <b style='font-size:20px;color:blue'>$otp</b>.<br>Mã này có hiệu lực trong 10 phút.";
        
        if (sendMail($email, $subject, $body)) {
            // 3. Lưu toàn bộ thông tin đăng ký vào Session (tạm thời)
            $_SESSION['reg_temp_data'] = [
                'ho_ten' => $ho_ten,
                'email' => $email,
                'so_dien_thoai' => $sdt,
                'ngay_sinh' => $ngay_sinh,
                'gioi_tinh' => $gioi_tinh,
                'mat_khau' => password_hash($mat_khau, PASSWORD_DEFAULT), // Mã hóa luôn
                'otp' => $otp,
                'otp_time' => time() + 600 // Hết hạn sau 10 phút
            ];

            // 4. Chuyển hướng sang trang nhập OTP
            header("Location: index.php?page=verify_register");
            exit;
        } else {
            $errors[] = "Không thể gửi email. Vui lòng kiểm tra lại kết nối mạng hoặc email.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng Ký - HISHOP</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style-auth.css">
</head>
<body>
    
    <<header class="navbar">
        <div class="logo"><img src="assets/img/logo.png" alt="Hishop" class="brand-logo"></div>
        <nav class="nav-links">
            <a href="index.php?page=home">Trang chủ</a>
            <a href="index.php?page=product_list">Sản Phẩm</a>
        </nav>
        <a href="index.php?page=login"><button class="login-btn">Đăng nhập</button></a>
    </header>

    <div class="background-container">
        <div class="register-modal-container">
            <div class="auth-card">
                <div class="modal-header">
                    <h2>Tạo Tài Khoản</h2>
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
                
                <form class="auth-form" method="POST" action="index.php?page=register">
                    
                    <div class="input-group">
                        <span class="material-icons">person_outline</span>
                        <input type="text" id="ho_ten" name="ho_ten" class="form-input" placeholder="Họ và tên của bạn" value="<?= htmlspecialchars($_POST['ho_ten'] ?? '') ?>" required>
                    </div>
                    
                    <div class="input-group">
                        <span class="material-icons">mail_outline</span>
                        <input type="email" id="email" name="email" class="form-input" placeholder="Enter your email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                    </div>
                    
                    <div class="input-group">
                        <span class="material-icons">phone_iphone</span>
                        <input type="tel" id="so_dien_thoai" name="so_dien_thoai" maxlength="10" class="form-input" placeholder="Số điện thoại (Tùy chọn)" value="<?= htmlspecialchars($_POST['so_dien_thoai'] ?? '') ?>">
                    </div>
                    
                    <div class="form-grid-2">
                        <div class="input-group input-group-date">
                            <span class="material-icons">calendar_today</span>
                            <input type="date" id="ngay_sinh" name="ngay_sinh" class="form-input" value="<?= htmlspecialchars($_POST['ngay_sinh'] ?? '') ?>">
                        </div>
                        <div class="input-group input-group-select">
                             <span class="material-icons">wc</span>
                            <select id="gioi_tinh" name="gioi_tinh" class="form-select">
                                <option value="other" <?= (isset($_POST['gioi_tinh']) && $_POST['gioi_tinh'] == 'other') ? 'selected' : '' ?>>Khác</option>
                                <option value="male" <?= (isset($_POST['gioi_tinh']) && $_POST['gioi_tinh'] == 'male') ? 'selected' : '' ?>>Nam</option>
                                <option value="female" <?= (isset($_POST['gioi_tinh']) && $_POST['gioi_tinh'] == 'female') ? 'selected' : '' ?>>Nữ</option>
                            </select>
                        </div>
                    </div>

                    <div class="input-group">
                        <span class="material-icons">lock_open</span>
                        <input type="password" id="mat_khau" name="mat_khau" class="form-input" placeholder="Tạo mật khẩu (ít nhất 6 ký tự)" required>
                    </div>

                    <div class="input-group">
                        <span class="material-icons">lock_open</span>
                        <input type="password" id="mat_khau_nhap_lai" name="mat_khau_nhap_lai" class="form-input" placeholder="Nhập lại mật khẩu" required>
                    </div>
                    
                    <button type="submit" class="login-now-btn">Đăng Ký</button>
                </form>

                <div class="signup-link">
                    Đã có tài khoản? <a href="index.php?page=login">Đăng nhập</a>
                </div>
                
                <div class="auth-header-hidden" style="display: none;">
                    <a href="index.php?page=home" class="logo-hidden">HIShop</a>
                    <h1>Tạo Tài Khoản</h1>
                    <p>Tham gia cùng HIShop ngay hôm nay.</p>
                </div>
                <div class="auth-footer-hidden" style="display: none;">
                    Đã có tài khoản? <a href="index.php?page=login" class="form-link">Đăng nhập ngay</a>
                </div>
                </div>
        </div>
    </div>
    
</body>
</html>