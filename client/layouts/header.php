<?php
    // Biến $page_title và $categories đã được tạo từ file index.php
    if (!isset($page_title)) {
        $page_title = 'HIShop - Giải Pháp Công Nghệ';
    }
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    
    <link rel="stylesheet" href="assets/css/style-client.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />

    <style>
        /* --- Dropdown click toggle --- */
        .nav-item {
            position: relative;
        }

        .dropdown-menu {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            background: #fff;
            border: 1px solid #e5e7eb;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            min-width: 180px;
            z-index: 999;
        }

        .dropdown-menu a {
            display: block;
            padding: 10px 14px;
            color: #1f2937;
            text-decoration: none;
            transition: background 0.2s;
        }

        .dropdown-menu a:hover {
            background: #f3f4f6;
        }

        .nav-item.open .dropdown-menu {
            display: block;
            animation: dropdownFade 0.2s ease;
        }

        @keyframes dropdownFade {
            from {
                opacity: 0;
                transform: translateY(-5px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>

<body>

    <header class="container">
        <nav class="header-nav">
            
            <a href="index.php?page=home" class="logo">HIShop</a>
            
            <div class="nav-center-links">
                <a href="index.php?page=home">Trang Chủ</a>
                
                <!-- Dropdown Danh Mục -->
                <div class="nav-item has-dropdown" id="categoryDropdown">
                    <a href="javascript:void(0)" id="toggleCategory">Danh Mục</a>
                    <div class="dropdown-menu" id="categoryMenu">
                        <?php if (isset($categories) && !empty($categories)): ?>
                            <?php foreach ($categories as $category): ?>
                                <!-- ✅ Sửa đúng URL để lọc sản phẩm -->
                                <a href="index.php?page=product_list&cat=<?= $category['id'] ?>">
                                    <?= htmlspecialchars($category['ten']) ?>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <a href="#">Không có danh mục</a>
                        <?php endif; ?>
                    </div>
                </div>

                <a href="index.php?page=product_list">Sản Phẩm</a>
                <a href="index.php?page=static_about">Về Chúng Tôi</a>
                <a href="index.php?page=contact">Liên Hệ</a>
            </div>

            <div class="nav-right-actions">
                
                <!-- Thanh tìm kiếm -->
                <form action="index.php" method="GET" class="nav-search-form">
                    <input type="hidden" name="page" value="search_results">
                    <input type="text" name="query" class="nav-search-input" placeholder="Tìm kiếm sản phẩm...">
                    <button type="submit" class="icon-btn nav-search-btn">🔍</button>
                </form>
                
                <!-- Giỏ hàng -->
                <a href="index.php?page=cart" class="icon-btn">🛒</a>
                
                <!-- Đăng nhập / tài khoản -->
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="index.php?page=account" class="icon-btn">👤</a>
                <?php else: ?>
                    <div class="auth-buttons">
                        <a href="index.php?page=register" class="btn btn-primary">Đăng Ký</a>
                        <a href="index.php?page=login" class="btn btn-primary">Đăng Nhập</a>
                    </div>
                <?php endif; ?>
            </div>
        </nav>
    </header>

    <main>

    <!-- JavaScript xử lý click toggle -->
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        const toggle = document.getElementById("toggleCategory");
        const dropdown = document.getElementById("categoryDropdown");

        // Khi click vào chữ Danh Mục
        toggle.addEventListener("click", function(e) {
            e.preventDefault();
            dropdown.classList.toggle("open");
        });

        // Khi click ra ngoài menu thì ẩn menu
        document.addEventListener("click", function(e) {
            if (!dropdown.contains(e.target)) {
                dropdown.classList.remove("open");
            }
        });
    });
    </script>

</body>
</html>
