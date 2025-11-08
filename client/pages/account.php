<?php
// FILE: client/pages/account.php
// (Biến $pdo, $categories đã có sẵn từ index.php)

// 1. (BACK-END) BẢO VỆ TRANG
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?page=login');
    exit;
}
$user_id = $_SESSION['user_id'];

// 2. (BACK-END) ROUTER CON
$section = $_GET['section'] ?? 'profile'; 

// 3. (BACK-END) XỬ LÝ FORM CẬP NHẬT PROFILE
$update_success = null;
$update_error = null; 

if ($section == 'profile' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $ho_ten = trim($_POST['ho_ten'] ?? '');
    $so_dien_thoai = trim($_POST['so_dien_thoai'] ?? '');
    $ngay_sinh = $_POST['ngay_sinh'] ?? null;
    $gioi_tinh = $_POST['gioi_tinh'] ?? 'other';

    if (empty($ho_ten)) {
        $update_error = "Họ và tên không được để trống.";
    } else {
        $result = updateUserProfile($pdo, $user_id, $ho_ten, $so_dien_thoai, $ngay_sinh, $gioi_tinh);
        if ($result) {
            $update_success = "Cập nhật thông tin thành công!";
            $_SESSION['user_name'] = $ho_ten;
        } else {
            $update_error = "Cập nhật thất bại. Vui lòng thử lại.";
        }
    }
}

// 4. (BACK-END) LẤY DỮ LIỆU 
$user_profile_data = getUserProfile($pdo, $user_id); // Luôn lấy profile

// 5. (SỬA LỖI) KIỂM TRA DỮ LIỆU NGAY LẬP TỨC
// Nếu hàm fetch() trả về false (không tìm thấy user), hãy đăng xuất
if (!$user_profile_data) {
    // Có thể session cũ nhưng user đã bị xóa
    header('Location: index.php?page=logout');
    exit;
}

// Lấy dữ liệu cho các tab khác
switch ($section) {
    case 'orders':
        $data = getUserOrders($pdo, $user_id);
        break;
    case 'addresses':
        $data = getUserAddresses($pdo, $user_id);
        break;
    case 'profile':
    default:
        $data = $user_profile_data; // Gán $data
        break;
}

?>

<div class="container">
    <h1 class="page-title">Tài Khoản (Chào, <?php echo htmlspecialchars($user_profile_data['ho_ten']); ?>)</h1>

    <div class="account-layout">

        <aside class="account-nav">
            <ul>
                <li>
                    <a href="index.php?page=account&section=profile" 
                       class="<?php echo ($section == 'profile') ? 'active' : ''; ?>">
                       Thông tin tài khoản
                    </a>
                </li>
                <li>
                    <a href="index.php?page=account&section=orders" 
                       class="<?php echo ($section == 'orders') ? 'active' : ''; ?>">
                       Lịch sử đơn hàng
                    </a>
                </li>
                <li>
                    <a href="index.php?page=account&section=addresses" 
                       class="<?php echo ($section == 'addresses') ? 'active' : ''; ?>">
                       Sổ địa chỉ
                    </a>
                </li>
                <li>
                    <a href="index.php?page=logout">Đăng xuất</a>
                </li>
            </ul>
        </aside>

        <section class="account-content">

            <?php
            // (ĐÃ SỬA LỖI) DÙNG CÚ PHÁP CHUẨN (DẤU NGOẶC NHỌN)
            switch ($section) {

                // --- TRƯỜNG HỢP 1: THÔNG TIN TÀI KHOẢN ---
                case 'profile':
            ?>
                    <div class="account-content-header">
                        <h2>Thông tin tài khoản</h2>
                    </div>
                    <div class="account-content-body">
                        
                        <?php if ($update_success): ?>
                            <div class="error-message" style="background-color: #D1FAE5; color: #065F46; border-color: #6EE7B7;">
                                <p>✅ <?php echo $update_success; ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($update_error): ?>
                            <div class="error-message">
                                <p>❌ <?php echo $update_error; ?></p>
                            </div>
                        <?php endif; ?>

                        <form class="profile-form" method="POST" action="index.php?page=account&section=profile">
                            <div class="form-group">
                                <label for="email" class="form-label">Email (Không thể thay đổi)</label>
                                <input type="email" id="email" class="form-input" value="<?php echo htmlspecialchars($data['email']); ?>" disabled>
                            </div>
                            <div class="form-group">
                                <label for="ho_ten" class="form-label">Họ và tên</label>
                                <input type="text" id="ho_ten" name="ho_ten" class="form-input" value="<?php echo htmlspecialchars($data['ho_ten']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="so_dien_thoai" class="form-label">Số điện thoại</label>
                                <input type="tel" id="so_dien_thoai" name="so_dien_thoai" class="form-input" value="<?php echo htmlspecialchars($data['so_dien_thoai'] ?? ''); ?>">
                            </div>
                            <div class="form-grid-2">
                                <div class="form-group">
                                    <label for="ngay_sinh" class="form-label">Ngày sinh</label>
                                    <input type="date" id="ngay_sinh" name="ngay_sinh" class="form-input" value="<?php echo htmlspecialchars($data['ngay_sinh'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="gioi_tinh" class="form-label">Giới tính</label>
                                    <select id="gioi_tinh" name="gioi_tinh" class="form-input">
                                        <option value="other" <?php echo ($data['gioi_tinh'] == 'other') ? 'selected' : ''; ?>>Khác</option>
                                        <option value="male" <?php echo ($data['gioi_tinh'] == 'male') ? 'selected' : ''; ?>>Nam</option>
                                        <option value="female" <?php echo ($data['gioi_tinh'] == 'female') ? 'selected' : ''; ?>>Nữ</option>
                                    </select>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">Lưu thay đổi</button>
                        </form>
                    </div>
            <?php 
                    break; 

                // --- TRƯỜNG HỢP 2: LỊCH SỬ ĐƠN HÀNG ---
                case 'orders':
            ?>
                    <div class="account-content-header">
                        <h2>Lịch sử đơn hàng</h2>
                    </div>
                    <div class="account-content-body">
                        <div class="order-history-list">
                            <?php if (empty($data)): ?>
                                <p>Bạn chưa có đơn hàng nào.</p>
                            <?php else: ?>
                                <?php foreach ($data as $order): ?>
                                    <div class="order-item">
                                        <div>
                                            <span>Mã đơn hàng</span>
                                            #<?php echo htmlspecialchars($order['id']); ?>
                                        </div>
                                        <div>
                                            <span>Ngày đặt</span>
                                            <?php echo date('d/m/Y', strtotime($order['ngay_dat'])); ?>
                                        </div>
                                        <div>
                                            <span>Tổng tiền</span>
                                            <?php echo number_format($order['tong_tien']); ?>₫
                                        </div>
                                        <div>
                                            <span>Trạng thái</span>
                                            <?php if ($order['trang_thai'] == 'paid' || $order['trang_thai'] == 'Đã giao hàng'): ?>
                                                <div class="order-status delivered">Đã giao hàng</div>
                                            <?php else: ?>
                                                <div class="order-status processing"><?php echo htmlspecialchars($order['trang_thai']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
            <?php 
                    break;

                // --- TRƯỜNG HỢP 3: SỔ ĐỊA CHỈ ---
                case 'addresses':
            ?>
                    <div class="account-content-header">
                        <h2>Sổ địa chỉ</h2>
                    </div>
                    <div class="account-content-body">
                        <a href="#" class="btn btn-primary add-address-btn">(+) Thêm địa chỉ mới</a>
                        <div class="address-book">
                            <?php if (empty($data)): ?>
                                <p>Bạn chưa lưu địa chỉ nào.</p>
                            <?php else: ?>
                                <?php foreach ($data as $address): ?>
                                    <div class="address-card">
                                        <h3>Địa chỉ</h3>
                                        <p>
                                            <?php echo htmlspecialchars($address['dia_chi_cu_the']); ?>
                                        </p>
                                        <div class="address-card-actions">
                                            <a href="#">Chỉnh sửa</a>
                                            <a href="#">Xóa</a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
            <?php 
                    break;

            } // <<< KẾT THÚC SWITCH
            ?>
            
        </section> </div> </div> ```