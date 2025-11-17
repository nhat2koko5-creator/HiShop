<?php
// BẮT BUỘC: Đặt session_start() ở đây
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// === MỚI: Logic đếm tổng số lượng sản phẩm ===
$total_cart_items = 0;
if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $total_cart_items += (int)$item['quantity'];
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
    
    <link rel="stylesheet" href="assets/css/style-client.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />

    <style>
        /* --- Dropdown (CSS cũ của bạn) --- */
        .nav-item { position: relative; }
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
        .dropdown-menu a { display: block; padding: 10px 14px; color: #1f2937; text-decoration: none; transition: background 0.2s; }
        .dropdown-menu a:hover { background: #f3f4f6; }
        .nav-item.open .dropdown-menu { display: block; animation: dropdownFade 0.2s ease; }
        @keyframes dropdownFade {
            from { opacity: 0; transform: translateY(-5px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* === CSS cho số lượng trên giỏ hàng === */
        .cart-icon-wrapper {
            position: relative;
            display: inline-block;
        }
        #cart-item-count {
            position: absolute;
            top: -8px;
            right: -10px;
            background-color: #ef4444; /* Màu đỏ */
            color: white;
            font-size: 12px;
            font-weight: bold;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
            padding-bottom: 1px;
            /* Cập nhật logic hiển thị bằng PHP */
            display: <?php echo ($total_cart_items > 0) ? 'flex' : 'none'; ?>;
        }

        /* =======================================
        === MỚI: CSS CHO POPUP DÙNG CHUNG ===
        ======================================= */
        #modal-prompt-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.6); display: none; align-items: center; justify-content: center;
            z-index: 9998; opacity: 0; transition: opacity 0.2s ease;
        }
        #modal-prompt-overlay.show { display: flex; opacity: 1; }
        #modal-prompt-box {
            background: #fff; padding: 30px; border-radius: 12px; text-align: center;
            width: 90%; max-width: 400px; transform: scale(0.9); transition: transform 0.2s ease;
        }
        #modal-prompt-overlay.show #modal-prompt-box { transform: scale(1); }
        #modal-prompt-message { font-size: 18px; color: #333; margin-bottom: 25px; }
        .prompt-buttons { display: flex; gap: 15px; }
        .prompt-buttons button {
            flex: 1; padding: 12px; border: none; border-radius: 8px;
            font-size: 16px; font-weight: 600; cursor: pointer;
            transition: background-color 0.2s;
        }
        #btn-prompt-secondary { 
            background: #f1f1f1; 
            color: #333; 
        }
        #btn-prompt-primary { 
            background: #0f62fe;
            color: white; 
        }
        #btn-prompt-primary.danger {
            background: #dc2626;
        }
        .prompt-buttons .hide {
            display: none;
        }
    </style>
</head>

<body>
    <header class="container">
        <nav class="header-nav">
            
            <a href="index.php?page=home" class="logo">HIShop</a>
            
            <div class="nav-center-links">
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
                    🛒 <span id="cart-item-count"><?php echo $total_cart_items; ?></span>
                </a>
                
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
    
    <div class="modal-prompt-overlay" id="modal-prompt-overlay">
        <div class="modal-prompt-box" id="modal-prompt-box">
            <p class="modal-prompt-message" id="modal-prompt-message">Nội dung thông báo</p>
            <div class="prompt-buttons">
                <button class="btn-prompt-secondary" id="btn-prompt-secondary">Nút Phụ</button>
                <button class="btn-prompt-primary" id="btn-prompt-primary">Nút Chính</button>
            </div>
        </div>
    </div>
    
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        // --- Logic cho Dropdown (Cũ) ---
        const toggle = document.getElementById("toggleCategory");
        const dropdown = document.getElementById("categoryDropdown");

        if(toggle && dropdown) {
            toggle.addEventListener("click", function(e) {
                e.preventDefault();
                dropdown.classList.toggle("open");
            });
            document.addEventListener("click", function(e) {
                if (!dropdown.contains(e.target)) {
                    dropdown.classList.remove("open");
                }
            });
        }

        // --- MỚI: Logic gán sự kiện cho Popup ---
        // Lấy các element của popup (chỉ lấy 1 lần)
        const modalOverlay = document.getElementById('modal-prompt-overlay');
        const btnPromptPrimary = document.getElementById('btn-prompt-primary');
        const btnPromptSecondary = document.getElementById('btn-prompt-secondary');

        if(modalOverlay && btnPromptPrimary && btnPromptSecondary) {
            // Nút phụ (OK, Quay lại) -> luôn là nút đóng
            btnPromptSecondary.addEventListener('click', () => {
                hideModalPrompt();
            });

            // Bấm ra ngoài nền đen -> đóng
            modalOverlay.addEventListener('click', e => { 
                if (e.target === modalOverlay) hideModalPrompt(); 
            });

            // Nút chính (Đăng nhập, Xác nhận...)
            btnPromptPrimary.addEventListener('click', () => {
                // Lấy trạng thái hiện tại của modal (sẽ được set trong hàm show)
                const state = modalOverlay.dataset.modalState || 'none';
                
                if (state === 'login') {
                    // Nếu là popup login -> chuyển trang login
                    window.location.href = 'index.php?page=login&redirect=cart';
                }
                // Bạn có thể thêm các state khác (như 'delete') nếu cần
                
                // Mặc định, nút chính cũng đóng popup
                hideModalPrompt();
            });
        }
    });
    </script>

    <script>
        /**
         * Cập nhật số lượng trên icon giỏ hàng
         * @param {number} count - Tổng số lượng sản phẩm
         */
        function updateCartIconCount(count) {
            const countElement = document.getElementById('cart-item-count');
            if (countElement) {
                if (count > 0) {
                    countElement.textContent = count;
                    countElement.style.display = 'flex';
                } else {
                    countElement.textContent = '0';
                    countElement.style.display = 'none';
                }
            }
        }

        /**
         * MỚI: Ẩn popup
         */
        function hideModalPrompt() {
            const modalOverlay = document.getElementById('modal-prompt-overlay');
            if (modalOverlay) {
                modalOverlay.classList.remove('show');
                modalOverlay.dataset.modalState = 'none';
            }
        }

        /**
         * MỚI: Hiển thị popup dạng Thông báo (chỉ có nút OK)
         * @param {string} message - Nội dung thông báo
         */
        function showModalAlert(message) {
            const modalOverlay = document.getElementById('modal-prompt-overlay');
            const modalMessage = document.getElementById('modal-prompt-message');
            const btnPromptPrimary = document.getElementById('btn-prompt-primary');
            const btnPromptSecondary = document.getElementById('btn-prompt-secondary');

            if (!modalOverlay || !modalMessage || !btnPromptPrimary || !btnPromptSecondary) return;
            
            modalOverlay.dataset.modalState = 'alert';
            modalMessage.textContent = message;
            
            btnPromptSecondary.textContent = 'OK'; // Nút phụ làm nút OK
            btnPromptSecondary.classList.remove('hide');

            btnPromptPrimary.classList.add('hide'); // Ẩn nút chính
            btnPromptPrimary.classList.remove('danger');

            modalOverlay.classList.add('show');
        }
        
        /**
         * MỚI: Hiển thị popup dạng Yêu cầu Đăng nhập
         */
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