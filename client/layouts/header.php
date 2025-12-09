<?php
// FILE: client/layouts/header.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Logic đếm giỏ hàng
$total_cart_items = 0;

if (isset($_SESSION['user_id'])) {
    // Nếu biến $pdo tồn tại (được load từ config.php ở index), ta query trực tiếp
    if (isset($pdo)) {
        try {
            $stmtCnt = $pdo->prepare("SELECT SUM(so_luong) FROM gio_hang WHERE nguoi_dung_id = ?");
            $stmtCnt->execute([$_SESSION['user_id']]);
            $total_cart_items = (int)$stmtCnt->fetchColumn(); // Ép kiểu int để null thành 0
            
            // Cập nhật ngược lại session cho các trang khác dùng
            $_SESSION['global_cart_count'] = $total_cart_items;
        } catch (Exception $e) {
            $total_cart_items = 0;
        }
    } else {
        // Fallback: Nếu không có $pdo thì mới dùng Session
        $total_cart_items = isset($_SESSION['global_cart_count']) ? (int)$_SESSION['global_cart_count'] : 0;
    }
}

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
    <link rel="stylesheet" href="/HiShop/assets/css/style-client.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
</head>

<body>
    <header class="site-header-sticky"> 
        <div class="container">
            <nav class="header-nav">
                
                <a href="index.php?page=home" class="logo"><img src="assets/img/logo.png" alt="Hishop" style="width:70px; height:50px;margin-top:10px;"></a>
                
                <div class="nav-center-links" style="text-decoration: double;">
                    <a href="index.php?page=home">Trang Chủ</a>
                    
                    <div class="nav-item has-dropdown" id="categoryDropdown">
                        <a href="javascript:void(0)" id="toggleCategory">Danh Mục</a>
                        <div class="dropdown-menu" id="categoryMenu">
                            <?php if (isset($categories) && !empty($categories)): ?>
                                <?php foreach ($categories as $category): ?>
                                    <a href="index.php?page=product_list&category_id=<?= $category['id'] ?>">
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
                    
                    <form action="index.php" method="GET" class="nav-search-form">
                        <input type="hidden" name="page" value="search_results">
                        <input type="text" name="query" class="nav-search-input" placeholder="Tìm kiếm sản phẩm...">
                        <button type="submit" class="icon-btn nav-search-btn">🔍</button>
                    </form>
                    
                   <a href="index.php?page=cart" class="icon-btn cart-icon-wrapper">
                        🛒 <span id="cart-item-count" style="display: <?php echo ($total_cart_items > 0) ? 'flex !important' : 'none !important'; ?>;">
                            <?php echo $total_cart_items; ?>
                        </span>
                    </a>
                    
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <div class="nav-item has-dropdown user-dropdown-wrapper" id="userDropdown">
                            
                            <a href="javascript:void(0);" id="toggleUser" class="icon-btn" style="display: flex; align-items: center; gap: 5px; text-decoration: none;">
                                👤 <span style="font-size: 13px; font-weight: 600; max-width: 100px; overflow: hidden; white-space: nowrap; text-overflow: ellipsis;">
                                    <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Tài khoản'); ?>
                                </span>
                            </a>

                            <div class="dropdown-menu user-menu">
                                <div class="user-menu-info">
                                    Xin chào, <strong><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Bạn'); ?></strong>
                                </div>
                                <ul class="user-menu-list">
                                    <li><a href="index.php?page=account&section=profile">⚙️ Thông tin cá nhân</a></li>
                                    <li><a href="index.php?page=account&section=orders">📦 Đơn hàng của tôi</a></li>
                                    <li><a href="index.php?page=account&section=addresses">📍 Sổ địa chỉ</a></li>
                                    <li style="border-top: 1px solid #eee; margin: 5px 0;"></li>
                                    <li><a href="index.php?page=logout" style="color: #dc2626;">🚪 Đăng xuất</a></li>
                                </ul>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="auth-buttons" >
                            <a href="index.php?page=register" class="btn btn-primary" >Đăng Ký</a>
                            <a href="index.php?page=login" class="btn btn-primary">Đăng Nhập</a>
                        </div>
                    <?php endif; ?>
                </div>
            </nav>
        </div> 
    </header>

    <main>
    
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        
        // --- 1. Logic Dropdown USER (Hover + Click + Auto Close) ---
        const userWrapper = document.getElementById("userDropdown");
        const userToggle = document.getElementById("toggleUser");

        if(userWrapper && userToggle) {
            // Sự kiện CLICK (Toggle)
            userToggle.addEventListener("click", function(e) {
                e.preventDefault();
                // Đóng các menu khác
                const catDropdown = document.getElementById("categoryDropdown");
                if (catDropdown) catDropdown.classList.remove("open");
                
                userWrapper.classList.toggle("open");
            });

            // Sự kiện MOUSE LEAVE (Rê chuột ra ngoài -> Đóng ngay)
            userWrapper.addEventListener("mouseleave", function() {
                userWrapper.classList.remove("open");
            });
        }

        // --- 2. Logic Dropdown DANH MỤC ---
        const catWrapper = document.getElementById("categoryDropdown");
        const catToggle = document.getElementById("toggleCategory");

        if(catWrapper && catToggle) {
            catToggle.addEventListener("click", function(e) {
                e.preventDefault();
                // Đóng User menu
                if (userWrapper) userWrapper.classList.remove("open");
                
                catWrapper.classList.toggle("open");
            });

            // Tự đóng khi rê chuột ra ngoài (cho trải nghiệm đồng nhất)
            catWrapper.addEventListener("mouseleave", function() {
                catWrapper.classList.remove("open");
            });
        }

        // --- 3. Click ra ngoài để đóng tất cả (Backup) ---
        document.addEventListener("click", function(e) {
            if (catWrapper && !catWrapper.contains(e.target)) {
                catWrapper.classList.remove("open");
            }
            if (userWrapper && !userWrapper.contains(e.target)) {
                userWrapper.classList.remove("open");
            }
        });

        // --- 4. Logic Popup (Giữ nguyên) ---
        const modalOverlay = document.getElementById('modal-prompt-overlay');
        const btnPromptPrimary = document.getElementById('btn-prompt-primary');
        const btnPromptSecondary = document.getElementById('btn-prompt-secondary');

        if(modalOverlay && btnPromptPrimary && btnPromptSecondary) {
            btnPromptSecondary.addEventListener('click', () => hideModalPrompt());
            modalOverlay.addEventListener('click', e => { 
                if (e.target === modalOverlay) hideModalPrompt(); 
            });
            btnPromptPrimary.addEventListener('click', () => {
                const state = modalOverlay.dataset.modalState || 'none';
                if (state === 'login') {
                    window.location.href = 'index.php?page=login&redirect=cart';
                }
                hideModalPrompt();
            });
        }
    });

    // --- CÁC HÀM HỖ TRỢ ---
    function updateCartIconCount(count) {
        const countElement = document.getElementById('cart-item-count');
        if (countElement) {
            const finalCount = parseInt(count) || 0;
            if (finalCount > 0) {
                countElement.textContent = finalCount;
                countElement.style.display = 'flex';
            } else {
                countElement.textContent = '0';
                countElement.style.display = 'none';
            }
        }
    }

    function hideModalPrompt() {
        const modalOverlay = document.getElementById('modal-prompt-overlay');
        if (modalOverlay) {
            modalOverlay.classList.remove('show');
            modalOverlay.dataset.modalState = 'none';
        }
    }

    function showModalAlert(message) {
        const modalOverlay = document.getElementById('modal-prompt-overlay');
        const modalMessage = document.getElementById('modal-prompt-message');
        const btnPromptPrimary = document.getElementById('btn-prompt-primary');
        const btnPromptSecondary = document.getElementById('btn-prompt-secondary');

        if (!modalOverlay || !modalMessage || !btnPromptPrimary || !btnPromptSecondary) return;
        
        modalOverlay.dataset.modalState = 'alert';
        modalMessage.textContent = message;
        
        btnPromptSecondary.textContent = 'OK'; 
        btnPromptSecondary.classList.remove('hide');
        btnPromptPrimary.classList.add('hide'); 
        btnPromptPrimary.classList.remove('danger');

        modalOverlay.classList.add('show');
    }
    
    function showLoginPrompt() {
        const modalOverlay = document.getElementById('modal-prompt-overlay');
        const modalMessage = document.getElementById('modal-prompt-message');
        const btnPromptPrimary = document.getElementById('btn-prompt-primary');
        const btnPromptSecondary = document.getElementById('btn-prompt-secondary');

        if (!modalOverlay || !modalMessage || !btnPromptPrimary || !btnPromptSecondary) return;
        
        modalOverlay.dataset.modalState = 'login';
        modalMessage.textContent = 'Bạn cần đăng nhập để tiếp tục!';
        
        btnPromptSecondary.textContent = 'Quay lại';
        btnPromptSecondary.classList.remove('hide');
        
        btnPromptPrimary.textContent = 'Đăng nhập';
        btnPromptPrimary.classList.remove('danger', 'hide');

        modalOverlay.classList.add('show');
    }
    </script>
</body>
</html>