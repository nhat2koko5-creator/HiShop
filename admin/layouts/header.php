<?php
// FILE: admin/layouts/header.php

// 1. Xác định tiêu đề
$title = $page_title ?? 'Dashboard';

// 2. Lấy thông tin Admin
$admin_name = $_SESSION['user_name'] ?? 'Admin';
// Avatar tạo tự động theo tên
$admin_avatar = 'https://ui-avatars.com/api/?name=' . urlencode($admin_name) . '&background=0f62fe&color=fff&size=128';
?>
    <header class="admin-topbar">
    <div class="topbar-left">
        <nav class="breadcrumb">
            <a href="index.php" class="breadcrumb-item"><i class="fa-solid fa-house"></i></a>
            <span class="breadcrumb-separator">/</span>
            <span class="breadcrumb-item active"><?php echo htmlspecialchars($title); ?></span>
        </nav>
        <h1 class="page-title"><?php echo htmlspecialchars($title); ?></h1>
    </div>
        <div class="topbar-right">
        <form action="index.php" method="GET" class="header-search">
            <input type="hidden" name="page" value="orders_list">
            <i class="fa-solid fa-magnifying-glass search-icon"></i>
            <input type="text" name="q" placeholder="Tìm nhanh đơn hàng..." class="search-input" value="<?= isset($_GET['q']) ? htmlspecialchars($_GET['q']) : '' ?>">
        </form>
            <div class="header-actions">
            <div class="action-item">
                <i class="fa-regular fa-bell"></i>
                <span class="badge-dot"></span>
            </div>
        </div>
            <div class="header-user">
            <div class="user-info">
                <span class="user-name"><?php echo htmlspecialchars($admin_name); ?></span>
                <span class="user-role">Administrator</span>
            </div>
            <img src="<?php echo $admin_avatar; ?>" alt="Admin" class="user-avatar">
                <div class="user-dropdown">
                <a href="#" class="dropdown-item"><i class="fa-regular fa-user"></i> Hồ sơ cá nhân</a>
                <div class="dropdown-divider"></div>
                <a href="#" onclick="confirmLogout(event)" class="dropdown-item text-danger">
                    <i class="fa-solid fa-right-from-bracket"></i> Đăng xuất
                </a>
            </div>
        </div>
        </div>
</header>

<div id="logoutModal" class="logout-modal-overlay">
    <div class="logout-modal-box">
        <div class="logout-icon">
            <i class="fa-solid fa-right-from-bracket"></i>
        </div>
        <h3>Đăng xuất?</h3>
        <p>Bạn có chắc chắn muốn đăng xuất khỏi hệ thống không?</p>
        <div class="logout-actions">
            <button class="btn-cancel" onclick="closeLogoutModal()">Hủy</button>
            <a href="../index.php?page=logout" class="btn-confirm">Đồng ý</a>
        </div>
    </div>
</div>

<script>
    function confirmLogout(e) {
        if(e) e.preventDefault();
        document.getElementById('logoutModal').classList.add('show');
    }
    function closeLogoutModal() {
        document.getElementById('logoutModal').classList.remove('show');
    }
    window.onclick = function(event) {
        const modal = document.getElementById('logoutModal');
        if (event.target == modal) closeLogoutModal();
    }
</script>