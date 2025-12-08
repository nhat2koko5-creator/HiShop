<?php
// FILE: client/auth/register.php
$errors = [];

// 1. KIỂM TRA NẾU NGƯỜI DÙNG NHẤN NÚT "ĐĂNG KÝ"
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // 2. LẤY DỮ LIỆU TỪ FORM (và làm sạch)
    $ho_ten = trim($_POST['ho_ten'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $sdt = trim($_POST['so_dien_thoai'] ?? '');
    $ngay_sinh = $_POST['ngay_sinh'] ?? null;
    $gioi_tinh = $_POST['gioi_tinh'] ?? null;
    $mat_khau = $_POST['mat_khau'] ?? '';
    $mat_khau_nhap_lai = $_POST['mat_khau_nhap_lai'] ?? '';

    // 3. KIỂM TRA (VALIDATE) DỮ LIỆU
    
    // Kiểm tra các trường bắt buộc
    if (empty($ho_ten)) {
        $errors[] = 'Họ và tên là bắt buộc.';
    }
    if (empty($email)) {
        $errors[] = 'Email là bắt buộc.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email không hợp lệ.';
    }
    if (empty($mat_khau)) {
        $errors[] = 'Mật khẩu là bắt buộc.';
    } elseif (strlen($mat_khau) < 6) {
        $errors[] = 'Mật khẩu phải có ít nhất 6 ký tự.';
    }
    if ($mat_khau !== $mat_khau_nhap_lai) {
        $errors[] = 'Mật khẩu nhập lại không khớp.';
    }
    
    // --- [MỚI] XỬ LÝ & VALIDATE NGÀY SINH (16+) ---
    if (empty($ngay_sinh)) {
        $ngay_sinh = null;
        // Nếu bạn muốn bắt buộc nhập ngày sinh để kiểm tra tuổi, hãy bỏ comment dòng dưới:
        // $errors[] = 'Vui lòng nhập ngày sinh để xác minh độ tuổi.';
    } else {
        // Tính toán độ tuổi
        $dateOfBirth = new DateTime($ngay_sinh);
        $today = new DateTime();
        
        // Kiểm tra xem người dùng có nhập ngày tương lai không
        if ($dateOfBirth > $today) {
            $errors[] = 'Bạn phải từ 16 tuổi trở lên mới được đăng ký tài khoản.';
        } else {
            // Tính khoảng cách năm
            $age = $today->diff($dateOfBirth)->y;
            
            // Kiểm tra đủ 16 tuổi
            if ($age < 16) {
                $errors[] = 'Bạn phải từ 16 tuổi trở lên mới được đăng ký tài khoản.';
            }
        }
    }
    
    // Xử lý giới tính
    if (!in_array($gioi_tinh, ['male', 'female', 'other'])) {
        $gioi_tinh = 'other'; // Mặc định
    }

    // 4. KIỂM TRA TRÙNG LẶP (Email/SĐT) - NẾU KHÔNG CÓ LỖI VALIDATE
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM nguoi_dung WHERE email = ? OR so_dien_thoai = ?");
            $stmt->execute([$email, $sdt]);
            $existingUser = $stmt->fetch();

            if ($existingUser) {
                if ($existingUser['email'] === $email) {
                    $errors[] = 'Email này đã được sử dụng.';
                }
                if ($existingUser['so_dien_thoai'] === $sdt && !empty($sdt)) {
                    $errors[] = 'Số điện thoại này đã được sử dụng.';
                }
            }
        } catch (PDOException $e) {
            $errors[] = "Lỗi truy vấn CSDL: " . $e->getMessage();
        }
    }

    // 5. TẠO TÀI KHOẢN (NẾU TẤT CẢ ĐỀU ỔN)
    if (empty($errors)) {
        try {
            // Băm mật khẩu
            $hashed_password = password_hash($mat_khau, PASSWORD_DEFAULT);
            
            // Vai trò Khách hàng = 2
            $vai_tro_id = 2; 

            $sql = "INSERT INTO nguoi_dung (ho_ten, email, so_dien_thoai, ngay_sinh, gioi_tinh, mat_khau, vai_tro_id, trang_thai) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 1)";
            
            $stmt = $pdo->prepare($sql);
            
            $stmt->execute([
                $ho_ten,
                $email,
                $sdt,
                $ngay_sinh,
                $gioi_tinh,
                $hashed_password,
                $vai_tro_id
            ]);

            // 6. CHUYỂN HƯỚNG
            header("Location: index.php?page=login&register=success");
            exit;

        } catch (PDOException $e) {
            $errors[] = "Lỗi khi tạo tài khoản: " . $e->getMessage();
        }
    }
} 
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng Ký - HIShop</title>
    <link rel="stylesheet" href="./assets/css/style-auth.css">
</head>
<body>

    <div class="auth-card">
        <div class="auth-header">
            <a href="index.php?page=home" class="logo">HIShop</a>
            <h1>Tạo Tài Khoản</h1>
            <p>Tham gia cùng HIShop ngay hôm nay.</p>
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
            
            <div class="form-group">
                <label for="ho_ten" class="form-label">Họ và tên</label>
                <input type="text" id="ho_ten" name="ho_ten" class="form-input" placeholder="Nguyễn Văn A" value="<?= htmlspecialchars($_POST['ho_ten'] ?? '') ?>" required>
            </div>
            
            <div class="form-group">
                <label for="email" class="form-label">Email</label>
                <input type="email" id="email" name="email" class="form-input" placeholder="ban@email.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
            </div>
            
            <div class="form-group">
                <label for="so_dien_thoai" maxlength="10" class="form-label">Số điện thoại (Tùy chọn)</label>
                <input type="tel" id="so_dien_thoai" name="so_dien_thoai" class="form-input" placeholder="0901234567" value="<?= htmlspecialchars($_POST['so_dien_thoai'] ?? '') ?>">
            </div>
            
            <div class="form-grid-2">
                <div class="form-group">
                    <label for="ngay_sinh" class="form-label">Ngày sinh</label>
                    <input type="date" id="ngay_sinh" name="ngay_sinh" class="form-input" value="<?= htmlspecialchars($_POST['ngay_sinh'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="gioi_tinh" class="form-label">Giới tính</label>
                    <select id="gioi_tinh" name="gioi_tinh" class="form-select">
                        <option value="other" <?= (isset($_POST['gioi_tinh']) && $_POST['gioi_tinh'] == 'other') ? 'selected' : '' ?>>Khác</option>
                        <option value="male" <?= (isset($_POST['gioi_tinh']) && $_POST['gioi_tinh'] == 'male') ? 'selected' : '' ?>>Nam</option>
                        <option value="female" <?= (isset($_POST['gioi_tinh']) && $_POST['gioi_tinh'] == 'female') ? 'selected' : '' ?>>Nữ</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="mat_khau" class="form-label">Mật khẩu</label>
                <input type="password" id="mat_khau" name="mat_khau" class="form-input" placeholder="Tạo mật khẩu (ít nhất 6 ký tự)" required>
            </div>

            <div class="form-group">
                <label for="mat_khau_nhap_lai" class="form-label">Nhập lại mật khẩu</label>
                <input type="password" id="mat_khau_nhap_lai" name="mat_khau_nhap_lai" class="form-input" placeholder="Nhập lại mật khẩu của bạn" required>
            </div>
            
            <button type="submit" class="btn btn-primary">Đăng Ký</button>
        </form>

        <div class="auth-footer">
            Đã có tài khoản? <a href="index.php?page=login" class="form-link">Đăng nhập ngay</a>
        </div>
    </div>
    
</body>
</html>