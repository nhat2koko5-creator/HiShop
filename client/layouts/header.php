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
</head>
<body>

    <header class="container">
        <nav class="header-nav">
            
            <a href="index.php?page=home" class="logo">HIShop</a>
            
            <div class="nav-center-links">
                <a href="index.php?page=home">Trang Chủ</a>
                
                <div class="nav-item has-dropdown">
                    <a href="index.php?page=product_list">Danh Mục</a>
                    
                    <div class="dropdown-menu">
                        
                        <?php if (isset($categories) && !empty($categories)): ?>
                            <?php foreach ($categories as $category): ?>
                                <a href="index.php?page=product_list&category_id=<?php echo $category['id']; ?>">
                                    <?php echo htmlspecialchars($category['ten']); ?>
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
                <form action="index.php" method="GET" class="nav-search-form">
                    <input type="hidden" name="page" value="search_results">
                    <input type="text" name="query" class="nav-search-input" placeholder="Tìm kiếm sản phẩm...">
                    <button type="submit" class="icon-btn nav-search-btn">
                        🔍
                    </button>
                </form>
                
                <a href="index.php?page=cart" class="icon-btn">🛒</a>
                <a href="index.php?page=login" class="icon-btn">👤</a>
            </div>
        </nav>
    </header>

    <main>