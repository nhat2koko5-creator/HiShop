<?php
// FILE: client/pages/account.php

// 1. BẢO VỆ TRANG
if (!isset($_SESSION['user_id'])) {
    echo "<script>window.location.href='index.php?page=login';</script>";
    exit;
}
$user_id = $_SESSION['user_id'];

// 2. ROUTER CON (Mặc định vào profile thay vì dashboard)
$section = $_GET['section'] ?? 'profile'; 

// 3. XỬ LÝ FORM (PROFILE & ADDRESS)
$update_success = null;
$update_error = null; 

// FILE: client/pages/account.php

// A. Xử lý cập nhật thông tin cá nhân (BAO GỒM AVATAR)
if ($section == 'profile' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $ho_ten = trim($_POST['ho_ten'] ?? '');
    $so_dien_thoai = trim($_POST['so_dien_thoai'] ?? '');
    $ngay_sinh = $_POST['ngay_sinh'] ?? null;
    $gioi_tinh = $_POST['gioi_tinh'] ?? 'other';
    
    // Lấy avatar hiện tại từ DB (nếu có)
    $avatar_new_name = $user_profile_data['avatar'] ?? null; 
    $upload_error = null;

    // 1. Xử lý Upload Avatar
    if (isset($_FILES['avatar_file']) && $_FILES['avatar_file']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['avatar_file']['tmp_name'];
        $file_name = $_FILES['avatar_file']['name'];
        $file_size = $_FILES['avatar_file']['size'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        // Các định dạng cho phép
        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (!in_array($file_ext, $allowed_ext)) {
            $upload_error = "Chỉ chấp nhận file ảnh (JPG, JPEG, PNG, GIF).";
        } elseif ($file_size > 2 * 1024 * 1024) { // Giới hạn 2MB
             $upload_error = "Kích thước ảnh không được vượt quá 2MB.";
        } else {
            // Tạo tên file mới để tránh trùng lặp: avatar_ID_Timestamp.ext
            $avatar_new_name = 'avatar_' . $user_id . '_' . time() . '.' . $file_ext;
            $upload_dir = 'assets/img/avatars/';
            
            // Tạo thư mục nếu chưa có
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

            if (move_uploaded_file($file_tmp, $upload_dir . $avatar_new_name)) {
                // Upload thành công. (Tùy chọn: Có thể xóa ảnh cũ ở đây nếu muốn tiết kiệm bộ nhớ)
            } else {
                $upload_error = "Có lỗi xảy ra khi lưu ảnh.";
                $avatar_new_name = $user_profile_data['avatar']; // Giữ lại ảnh cũ nếu lỗi
            }
        }
    }

    // 2. Kiểm tra lỗi và Cập nhật DB
    if (empty($ho_ten)) {
        $update_error = "Họ và tên không được để trống.";
    } elseif ($upload_error) {
        $update_error = $upload_error; // Hiển thị lỗi upload
    } else {
        // Cần cập nhật hàm updateUserProfile trong src/user_functions.php để nhận thêm tham số avatar
        // Ví dụ: updateUserProfile($pdo, $user_id, $ho_ten, $so_dien_thoai, $ngay_sinh, $gioi_tinh, $avatar_new_name);
        
        // GIẢ ĐỊNH: Bạn đã sửa hàm updateUserProfile để nhận tham số thứ 6 là $avatar_new_name
        // Nếu chưa sửa hàm trong model, bạn cần vào đó thêm cột avatar = ? vào câu lệnh UPDATE.
        
        // Code tạm thời giả định hàm đã được sửa:
        $result = updateUserProfile($pdo, $user_id, $ho_ten, $so_dien_thoai, $ngay_sinh, $gioi_tinh, $avatar_new_name);

        if ($result) {
            $update_success = "Cập nhật thông tin thành công!";
            $_SESSION['user_name'] = $ho_ten;
            // Refresh lại dữ liệu mới nhất để hiển thị
            $user_profile_data = getUserProfile($pdo, $user_id); 
        } else {
            $update_error = "Cập nhật thất bại. Vui lòng thử lại.";
        }
    }
}

// B. Xử lý Địa chỉ (Thêm - Sửa - Xóa)
if ($section == 'addresses' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    // 1. Thêm mới
    if ($action == 'add_address') {
        $dia_chi_moi = trim($_POST['dia_chi_moi'] ?? '');
        if (!empty($dia_chi_moi)) {
            if (addUserAddress($pdo, $user_id, $dia_chi_moi)) {
                echo "<script>window.location.href='index.php?page=account&section=addresses';</script>";
                exit;
            }
        }
    }
    
    // 2. Xóa
    if ($action == 'delete_address') {
        $address_id = $_POST['address_id'] ?? 0;
        if (deleteUserAddress($pdo, $user_id, $address_id)) {
            echo "<script>window.location.href='index.php?page=account&section=addresses';</script>";
            exit;
        }
    }

    // 3. Sửa (Cập nhật)
    if ($action == 'edit_address') {
        $address_id = $_POST['address_id'] ?? 0;
        $dia_chi_sua = trim($_POST['dia_chi_moi'] ?? '');
        if (!empty($dia_chi_sua)) {
            updateUserAddress($pdo, $user_id, $address_id, $dia_chi_sua);
            echo "<script>window.location.href='index.php?page=account&section=addresses';</script>";
            exit;
        }
    }
}

// 4. LẤY DỮ LIỆU
$user_profile_data = getUserProfile($pdo, $user_id); 
if (!$user_profile_data) {
    echo "<script>window.location.href='index.php?page=logout';</script>";
    exit;
}

// Lấy dữ liệu cho section hiện tại
switch ($section) {
    case 'orders': $data = getUserOrders($pdo, $user_id); break;
    case 'addresses': $data = getUserAddresses($pdo, $user_id); break;
    case 'profile': 
    default: $data = $user_profile_data; break;
}
?>
<link rel="stylesheet" href="assets/css/account.css">
<div class="container" style="margin-top: 20px; margin-bottom: 40px;">
    
    <div style="margin-bottom: 20px;">
        <nav class="breadcrumb" style="font-size: 14px; color: #666; margin-bottom: 10px;">
            <a href="index.php" style="color: #666; text-decoration: none;">Trang chủ</a>
            <span style="margin: 0 8px;">&gt;</span>
            <span style="color: #333; font-weight: 500;">Tài khoản</span>
        </nav>
    </div>

    <div class="cps-account-layout">
        
        <aside class="cps-sidebar">
            <div class="sidebar-user-info">
              <?php 
                // Xác định avatar cho sidebar
                $sidebar_avatar = 'https://ui-avatars.com/api/?name=' . urlencode($user_profile_data['ho_ten']) . '&background=ffebd0&color=fd7e14&size=64';
                if (!empty($user_profile_data['avatar']) && file_exists('assets/img/avatars/' . $user_profile_data['avatar'])) {
                    $sidebar_avatar = 'assets/img/avatars/' . $user_profile_data['avatar'];
                }
                ?>
                <img src="<?php echo $sidebar_avatar; ?>" alt="Avatar" style="object-fit: cover;">
                <div class="info-text">
                    <strong><?php echo htmlspecialchars($user_profile_data['ho_ten']); ?></strong>
                    <span>Thành viên</span>
                </div>
            </div>

            <ul class="cps-menu">
                <li>
                    <a href="index.php?page=account&section=profile" class="<?php echo ($section == 'profile') ? 'active' : ''; ?>">
                        <span class="icon">👤</span> Thông tin tài khoản
                    </a>
                </li>
                <li>
                    <a href="index.php?page=account&section=orders" class="<?php echo ($section == 'orders') ? 'active' : ''; ?>">
                        <span class="icon">📦</span> Quản lý đơn hàng
                    </a>
                </li>
                <li>
                    <a href="index.php?page=account&section=addresses" class="<?php echo ($section == 'addresses') ? 'active' : ''; ?>">
                        <span class="icon">📍</span> Sổ địa chỉ
                    </a>
                </li>
                <li class="menu-spacer"></li>
                <li>
                    <a href="#" class="logout-item" id="btn-logout-trigger">
                        <span class="icon">🚪</span> Đăng xuất
                    </a>
                </li>
            </ul>
        </aside>

        <section class="cps-content">
            <?php
            switch ($section) {
                case 'profile':
                    if (file_exists('client/account/profile.php')) require_once 'client/account/profile.php'; 
                    break;

                case 'orders':
                    if (file_exists('client/account/order_history.php')) require_once 'client/account/order_history.php'; 
                    break;

                case 'addresses':
                    if (file_exists('client/account/address_book.php')) require_once 'client/account/address_book.php'; 
                    break;

                default:
                    echo "<p>Mục không tồn tại.</p>";
                    break;
            } 
            ?>
        </section>
    </div>
</div>

<div class="cps-modal-overlay" id="logoutModal">
    <div class="cps-modal">
        <div class="cps-modal-header">
            <h3>Xác nhận đăng xuất</h3>
            <button class="cps-modal-close" id="closeLogout">&times;</button>
        </div>
        <div class="cps-modal-body">
            <p>Bạn có chắc chắn muốn đăng xuất khỏi hệ thống?</p>
        </div>
        <div class="cps-modal-footer">
            <button class="btn btn-outline" id="cancelLogout">Ở lại</button>
            <a href="index.php?page=logout" class="btn btn-primary">Đăng xuất</a>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const logoutBtn = document.getElementById('btn-logout-trigger');
    const modal = document.getElementById('logoutModal');
    const closeBtn = document.getElementById('closeLogout');
    const cancelBtn = document.getElementById('cancelLogout');

    if (logoutBtn && modal) {
        logoutBtn.addEventListener('click', function(e) {
            e.preventDefault();
            modal.classList.add('show');
        });

        function closeModal() {
            modal.classList.remove('show');
        }

        closeBtn.addEventListener('click', closeModal);
        cancelBtn.addEventListener('click', closeModal);
        
        modal.addEventListener('click', function(e) {
            if (e.target === modal) closeModal();
        });
    }
});
</script>