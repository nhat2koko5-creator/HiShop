<?php
// admin/layouts/sidebar.php

// 1. Lấy trang hiện tại
$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

// 2. Hàm kiểm tra active đơn giản
function isActive($name, $currentPage) {
    return $name === $currentPage ? 'active' : '';
}

// 3. Xử lý Logic mở rộng Menu con
$product_pages = ['products_list', 'product_form', 'product_sale'];
$is_product_group = in_array($page, $product_pages);

$warehouse_pages = ['warehouse_list', 'warehouse_detail', 'warehouse_import', 'warehouse_export', 'warehouse_history'];
$is_warehouse_group = in_array($page, $warehouse_pages);

$system_pages = ['users_list', 'reports'];
$is_system_group = in_array($page, $system_pages);
?>

<aside class="admin-sidebar">
    <div class="sidebar-brand">
        <img src="../assets/img/logo.png" alt="Hishop" class="brand-logo" style="filter: brightness(0) invert(1);">
        <span class="brand-text">HiShop Admin</span>
    </div>

    <div class="sidebar-menu">
        
        <div class="nav-group-title">Tổng quan</div>
        <ul class="nav-group">
            <li>
                <a href="index.php?page=dashboard" class="nav-link <?php echo isActive('dashboard', $page); ?>">
                    <div class="nav-icon"><i class="fas fa-th-large"></i></div>
                    <span class="nav-text">Trang Chủ</span>
                </a>
            </li>
        </ul>

        <div class="nav-group-title">Quản lý bán hàng</div>
        <ul class="nav-group">
            
            <li>
                <a href="index.php?page=orders_list" class="nav-link <?php echo isActive('orders_list', $page) . isActive('order_detail', $page); ?>">
                    <div class="nav-icon"><i class="fas fa-shopping-cart"></i></div>
                    <span class="nav-text">Đơn hàng</span>
                </a>
            </li>

            <li class="has-submenu <?php echo $is_product_group ? 'open' : ''; ?>">
                <a href="javascript:void(0)" class="nav-link toggle-submenu <?php echo $is_product_group ? 'active-parent' : ''; ?>">
                    <div class="nav-icon"><i class="fas fa-box-open"></i></div>
                    <span class="nav-text">Sản phẩm</span>
                    <i class="fas fa-chevron-right arrow-icon"></i>
                </a>
                <ul class="submenu">
                    <li>
                        <a href="index.php?page=products_list" class="<?php echo isActive('products_list', $page) . isActive('product_form', $page); ?>">
                            Danh sách sản phẩm
                        </a>
                    </li>
                    <li>
                        <a href="index.php?page=product_sale" class="<?php echo isActive('product_sale', $page); ?>">
                            Sản phẩm giảm giá
                        </a>
                    </li>
                </ul>
            </li>

            <li>
                <a href="index.php?page=categories_list" class="nav-link <?php echo isActive('categories_list', $page); ?>">
                    <div class="nav-icon"><i class="fas fa-layer-group"></i></div>
                    <span class="nav-text">Danh mục</span>
                </a>
            </li>
            
            <li>
                <a href="index.php?page=promos_list" class="nav-link <?php echo isActive('promos_list', $page); ?>">
                    <div class="nav-icon"><i class="fas fa-ticket-alt"></i></div>
                    <span class="nav-text">Mã khuyến mãi</span>
                </a>
            </li>
        </ul>

        <div class="nav-group-title">Quản lý Kho</div>
        <ul class="nav-group">
            <li class="has-submenu <?php echo $is_warehouse_group ? 'open' : ''; ?>">
                <a href="javascript:void(0)" class="nav-link toggle-submenu <?php echo $is_warehouse_group ? 'active-parent' : ''; ?>">
                    <div class="nav-icon"><i class="fas fa-warehouse"></i></div>
                    <span class="nav-text">Kho hàng</span>
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
                            Tạo phiếu nhập
                        </a>
                    </li>
                    <li>
                        <a href="index.php?page=warehouse_export" class="<?php echo isActive('warehouse_export', $page); ?>">
                            Tạo phiếu xuất
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
            <div class="nav-icon"><i class="fas fa-users"></i></div>
            <span class="nav-text">Người Dùng</span>
        </a>
    </li>

    <li class="has-submenu <?php echo $is_report_group ? 'open' : ''; ?>">
        <a href="javascript:void(0)" class="nav-link toggle-submenu <?php echo $is_report_group ? 'active-parent' : ''; ?>">
            <div class="nav-icon"><i class="fas fa-chart-line"></i></div>
            <span class="nav-text">Báo cáo</span>
            <i class="fas fa-chevron-right arrow-icon"></i>
        </a>
        <ul class="submenu">
            <li>
                <a href="index.php?page=reports" class="<?php echo isActive('reports', $page); ?>">
                    Doanh thu
                </a>
            </li>
            <li>
                <a href="index.php?page=repost_revenue" class="<?php echo isActive('reports_revenue', $page); ?>">
                    Kho hàng
                </a>
            </li>
            <li>
                <a href="index.php?page=inventory" class="<?php echo isActive('inventory', $page); ?>">
                    Tồn kho
                </a>
            </li>
        </ul>
    </li>
</ul>
    </div>

    <div class="admin-user">
        <a href="#" onclick="confirmLogout(event)" class="btn-view-web"">
            <i class="fas fa-sign-out-alt" style="margin-right: 8px;"></i> Đăng xuất
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
                parentLi.classList.toggle('open');
            });
        });
    });
</script>