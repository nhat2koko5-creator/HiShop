<?php
// FILE: client/pages/account.php

// 1. BẢO VỆ TRANG (SỬA LỖI HEADERS SENT)
if (!isset($_SESSION['user_id'])) {
    echo "<script>window.location.href='index.php?page=login';</script>";
    exit;
}
$user_id = $_SESSION['user_id'];

// 2. ROUTER CON
$section = $_GET['section'] ?? 'dashboard'; 

// 3. XỬ LÝ FORM (PROFILE & ADDRESS)
$update_success = null;
$update_error = null; 

// A. Xử lý cập nhật thông tin cá nhân
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

// B. Xử lý thêm địa chỉ mới
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
    // (SỬA LỖI) Dùng JS để chuyển hướng
    echo "<script>window.location.href='index.php?page=logout';</script>";
    exit;
}

// TÍNH TOÁN THỐNG KÊ
$stats_orders_count = 0;
$stats_total_spent = 0;
$all_orders = getUserOrders($pdo, $user_id);
if (!empty($all_orders)) {
    $stats_orders_count = count($all_orders);
    foreach ($all_orders as $order) {
        if ($order['trang_thai'] !== 'cancelled') {
            $stats_total_spent += $order['tong_tien'];
        }
    }
}

// Lấy dữ liệu cho section hiện tại
switch ($section) {
    case 'orders': $data = $all_orders; break;
    case 'addresses': $data = getUserAddresses($pdo, $user_id); break;
    case 'profile':
    case 'dashboard': 
    default: $data = $user_profile_data; break;
}
?>

<div class="container" style="margin-top: 20px; margin-bottom: 40px;">
    
    <div style="margin-bottom: 20px;">
        <nav class="breadcrumb" style="font-size: 14px; color: #666; margin-bottom: 10px;">
            <a href="index.php" style="color: #666; text-decoration: none;">Trang chủ</a>
            <span style="margin: 0 8px;">&gt;</span>
            <span style="color: #333; font-weight: 500;">Trang cá nhân</span>
        </nav>
        <h1 style="font-size: 28px; font-weight: 700; color: #333; padding: 20px; text-align: center;">Trang Cá Nhân</h1>
    </div>


    <div class="cps-account-layout">
        
        <aside class="cps-sidebar">
            <ul class="cps-menu">
                <li>
                    <a href="index.php?page=account&section=dashboard" class="<?php echo ($section == 'dashboard') ? 'active' : ''; ?>">
                        <span class="icon">🏠</span> Tổng quan
                    </a>
                </li>
                <li>
                    <a href="index.php?page=account&section=orders" class="<?php echo ($section == 'orders') ? 'active' : ''; ?>">
                        <span class="icon">📦</span> Lịch sử mua hàng
                    </a>
                </li>
                <li>
                    <a href="index.php?page=account&section=addresses" class="<?php echo ($section == 'addresses') ? 'active' : ''; ?>">
                        <span class="icon">📍</span> Sổ địa chỉ
                    </a>
                </li>
                <li>
                    <a href="index.php?page=account&section=profile" class="<?php echo ($section == 'profile') ? 'active' : ''; ?>">
                        <span class="icon">⚙️</span> Thông tin tài khoản
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
                case 'dashboard':
            ?>
                <div class="cps-dashboard-grid">
                    <div class="cps-card full-width">
                        <div class="cps-card-header">
                            <h3>Đơn hàng gần đây</h3>
                            <?php if(!empty($all_orders)) : ?>
                                <a href="index.php?page=account&section=orders">Xem tất cả &gt;</a>
                            <?php endif; ?>
                        </div>
                        <div class="cps-card-body">
                            <?php if (empty($all_orders)): ?>
                                <div class="empty-state">
                                    <p>Bạn chưa mua đơn hàng nào.</p>
                                    <a href="index.php?page=product_list" class="btn btn-primary">Mua sắm ngay</a>
                                </div>
                            <?php else: 
                                $latest_order = $all_orders[0];
                            ?>
                                <div class="mini-order-item">
                                    <div class="moi-info">
                                        <strong>Đơn hàng #<?php echo $latest_order['id']; ?></strong>
                                        <span><?php echo date('d/m/Y', strtotime($latest_order['ngay_dat'])); ?></span>
                                        <span class="price"><?php echo number_format($latest_order['tong_tien']); ?>đ</span>
                                    </div>
                                    <div class="moi-status">
                                        <span class="status-tag <?php echo $latest_order['trang_thai'] == 'paid' ? 'success' : 'pending'; ?>">
                                            <?php echo htmlspecialchars($latest_order['trang_thai']); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="cps-card">
                        <div class="cps-card-header">
                            <h3>Thông tin cá nhân</h3>
                            <a href="index.php?page=account&section=profile">Sửa &gt;</a>
                        </div>
                        <div class="cps-card-body">
                            <div class="mini-profile-info">
                                <p><strong>Họ tên:</strong> <?php echo htmlspecialchars($user_profile_data['ho_ten']); ?></p>
                                <p><strong>SĐT:</strong> <?php echo htmlspecialchars($user_profile_data['so_dien_thoai'] ?? '--'); ?></p>
                                <p><strong>Email:</strong> <?php echo htmlspecialchars($user_profile_data['email']); ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="cps-card">
                        <div class="cps-card-header">
                            <h3>Sổ địa chỉ</h3>
                            <a href="index.php?page=account&section=addresses">Quản lý &gt;</a>
                        </div>
                        <div class="cps-card-body">
                             <?php 
                             $addresses = getUserAddresses($pdo, $user_id);
                             if (empty($addresses)): ?>
                                <p style="color: #888; font-size: 14px;">Chưa lưu địa chỉ nào.</p>
                             <?php else: ?>
                                <p style="font-size: 14px; line-height: 1.5;">
                                    <?php echo htmlspecialchars($addresses[0]['dia_chi_cu_the']); ?>
                                </p>
                             <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php
                    break;

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
            <div style="font-size: 40px; margin-top: 10px;">👋</div>
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