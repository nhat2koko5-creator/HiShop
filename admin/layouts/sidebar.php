<?php
// (Biến $page (tên trang) sẽ được tạo từ file admin/index.php)
$currentPage = $page ?? 'dashboard';
?>
<aside class="admin-sidebar">
    <div class="sidebar-logo">
        HIShop <span>Admin</span>
    </div>
    
    <nav>
        <div class="nav-group">
            <p class="nav-group-title">Tổng quan</p>
            <a href="index.php?page=dashboard" 
               class="nav-link <?php echo ($currentPage == 'dashboard') ? 'active' : ''; ?>">
                <span>(Icon)</span> Dashboard
            </a>
        </div>

        <div class="nav-group">
            <p class="nav-group-title">Quản lý</p>
            <a href="index.php?page=orders_list" 
               class="nav-link <?php echo ($currentPage == 'orders_list') ? 'active' : ''; ?>">
                <span>(Icon)</span> Đơn hàng
            </a>
            <a href="index.php?page=products_list" 
               class="nav-link <?php echo ($currentPage == 'products_list') ? 'active' : ''; ?>">
                <span>(Icon)</span> Sản phẩm
            </a>
            <a href="index.php?page=categories_list" 
               class="nav-link <?php echo ($currentPage == 'categories_list') ? 'active' : ''; ?>">
                <span>(Icon)</span> Danh mục
            </a>
            <a href="index.php?page=promos_list" 
               class="nav-link <?php echo ($currentPage == 'promos_list') ? 'active' : ''; ?>">
                <span>(Icon)</span> Khuyến mãi
            </a>
            <a href="index.php?page=users_list" 
               class="nav-link <?php echo ($currentPage == 'users_list') ? 'active' : ''; ?>">
                <span>(Icon)</span> Người dùng
            </a>
        </div>
        
        <div class="nav-group">
            <p class="nav-group-title">Hệ thống</p>
            <a href="index.php?page=warehouse_list" 
               class="nav-link <?php echo ($currentPage == 'warehouse_list') ? 'active' : ''; ?>">
                <span>(Icon)</span> Kho hàng
            </a>
            <a href="index.php?page=settings" 
               class="nav-link <?php echo ($currentPage == 'settings') ? 'active' : ''; ?>">
                <span>(Icon)</span> Cài đặt
            </a>
        </div>
    </nav>

    <div class="admin-user">
        <a href="../index.php?page=logout">(Icon) Đăng xuất</a>
    </div>
</aside>