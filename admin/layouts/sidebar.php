<?php
// admin/layouts/sidebar.php

// Lấy trang hiện tại để xử lý Active Class
$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

// Hàm kiểm tra active (Trả về class 'active')
function isActive($name, $currentPage) {
    return $name === $currentPage ? 'active' : '';
}

// Kiểm tra nhóm Warehouse để mở rộng menu con
$warehouse_pages = ['warehouse_list', 'warehouse_detail', 'warehouse_import', 'warehouse_export', 'warehouse_history'];
$is_warehouse_group = in_array($page, $warehouse_pages);
?>
<link rel="stylesheet" href="../assets/css/admin/style-admin.css">
<aside class="admin-sidebar">
    <div class="sidebar-brand">
        <img src="../assets/img/logo.png" alt="Hishop" class="brand-logo">
        <span class="brand-text">HiShop Admin</span>
    </div>

    <div class="sidebar-menu">
        <div class="nav-group-title">Tổng quan</div>
        <ul class="nav-group">
            <li>
                <a href="index.php?page=dashboard" class="nav-link <?php echo isActive('dashboard', $page); ?>">
                    <i class="fas fa-th-large"></i>
                    <span>Dashboard</span>
                </a>
            </li>
        </ul>

        <div class="nav-group-title">Kinh doanh</div>
        <ul class="nav-group">
            <li>
                <a href="index.php?page=orders_list" class="nav-link <?php echo isActive('orders_list', $page) . isActive('order_detail', $page); ?>">
                    <i class="fas fa-shopping-cart"></i>
                    <span>Đơn hàng</span>
                </a>
            </li>
            <li>
                <a href="index.php?page=products_list" class="nav-link <?php echo isActive('products_list', $page) . isActive('product_form', $page); ?>">
                    <i class="fas fa-box-open"></i>
                    <span>Sản phẩm</span>
                </a>
            </li>
            <li>
                <a href="index.php?page=categories_list" class="nav-link <?php echo isActive('categories_list', $page); ?>">
                    <i class="fas fa-layer-group"></i> <span>Danh mục</span>
                </a>
            </li>
            <li>
                <a href="index.php?page=promos_list" class="nav-link <?php echo isActive('promos_list', $page); ?>">
                    <i class="fas fa-ticket-alt"></i>
                    <span>Khuyến mãi</span>
                </a>
            </li>
        </ul>

        <div class="nav-group-title">Quản lý Kho</div>
        <ul class="nav-group">
            <li class="has-submenu <?php echo $is_warehouse_group ? 'open' : ''; ?>">
                <a href="javascript:void(0)" class="nav-link toggle-submenu">
                    <i class="fas fa-warehouse"></i>
                    <span>Kho hàng</span>
                    <i class="fas fa-chevron-right arrow-icon"></i>
                </a>
                
                <ul class="submenu">
                    <li>
                        <a href="index.php?page=warehouse_list" class="<?php echo isActive('warehouse_list', $page); ?>">
                            Danh sách kho
                        </a>
                    </li>
                    <li>
                        <a href="index.php?page=warehouse_import" class="<?php echo isActive('warehouse_import', $page); ?>">
                            Nhập kho
                        </a>
                    </li>
                    <li>
                        <a href="index.php?page=warehouse_export" class="<?php echo isActive('warehouse_export', $page); ?>">
                            Xuất kho
                        </a>
                    </li>
                    <li>
                        <a href="index.php?page=warehouse_history" class="<?php echo isActive('warehouse_history', $page); ?>">
                            Lịch sử X/N
                        </a>
                    </li>
                </ul>
            </li>
        </ul>

        <div class="nav-group-title">Hệ thống</div>
        <ul class="nav-group">
            <li>
                <a href="index.php?page=users_list" class="nav-link <?php echo isActive('users_list', $page); ?>">
                    <i class="fas fa-users"></i>
                    <span>Khách hàng</span>
                </a>
            </li>
            <li>
                <a href="index.php?page=settings" class="nav-link <?php echo isActive('settings', $page); ?>">
                    <i class="fas fa-cog"></i>
                    <span>Cài đặt</span>
                </a>
            </li>
        </ul>
    </div>

    <div class="admin-user">
        <a href="../client/index.php" target="_blank" style="color: #94a3b8; font-size: 13px; font-weight: 500; display: block;">
            <i class="fas fa-external-link-alt" style="margin-right: 5px;"></i> Xem Website
        </a>
    </div>
</aside>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        const toggles = document.querySelectorAll('.toggle-submenu');
        
        toggles.forEach(toggle => {
            toggle.addEventListener('click', function(e) {
                e.preventDefault();
                const parentLi = this.parentElement;
                
                // Toggle class 'open' để CSS xử lý animation
                parentLi.classList.toggle('open');
            });
        });
    });
</script>