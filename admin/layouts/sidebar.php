<?php
$currentPage = $page ?? 'dashboard';
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<aside class="admin-sidebar">
    <div class="sidebar-logo">
        HIShop <span>Admin</span>
    </div>
    
    <nav>
        <div class="nav-group">
            <p class="nav-group-title">Tổng quan</p>
            <a href="index.php?page=dashboard" 
               class="nav-link <?php echo ($currentPage == 'dashboard') ? 'active' : ''; ?>">
                <i class="fa-solid fa-gauge"></i>
                Dashboard
            </a>
        </div>

        <div class="nav-group">
            <p class="nav-group-title">Quản lý</p>

            <a href="index.php?page=orders_list" 
               class="nav-link <?php echo ($currentPage == 'orders_list') ? 'active' : ''; ?>">
                <i class="fa-solid fa-receipt"></i>
                Đơn hàng
            </a>

            <a href="index.php?page=products_list" 
               class="nav-link <?php echo ($currentPage == 'products_list') ? 'active' : ''; ?>">
                <i class="fa-solid fa-box"></i>
                Sản phẩm
            </a>

            <a href="index.php?page=categories_list" 
               class="nav-link <?php echo ($currentPage == 'categories_list') ? 'active' : ''; ?>">
                <i class="fa-solid fa-folder-tree"></i>
                Danh mục
            </a>

            <a href="index.php?page=promos_list" 
               class="nav-link <?php echo ($currentPage == 'promos_list') ? 'active' : ''; ?>">
                <i class="fa-solid fa-gift"></i>
                Khuyến mãi
            </a>

            <a href="index.php?page=users_list" 
               class="nav-link <?php echo ($currentPage == 'users_list') ? 'active' : ''; ?>">
                <i class="fa-solid fa-user"></i>
                Người dùng
            </a>
        </div>
        
        <div class="nav-group">
            <p class="nav-group-title">Hệ thống</p>

            <a href="index.php?page=warehouse_list" 
               class="nav-link <?php echo ($currentPage == 'warehouse_list') ? 'active' : ''; ?>">
                <i class="fa-solid fa-warehouse"></i>
                Kho hàng
            </a>

            <a href="index.php?page=settings" 
               class="nav-link <?php echo ($currentPage == 'settings') ? 'active' : ''; ?>">
                <i class="fa-solid fa-gear"></i>
                Cài đặt
            </a>
        </div>
    </nav>

    <div class="admin-user">
        <a href="../index.php?page=logout">
            <i class="fa-solid fa-right-from-bracket"></i>
            Đăng xuất
        </a>
    </div>
</aside>
