<?php
// (Biến $page_title sẽ được tạo từ file admin/index.php)
$title = $page_title ?? 'Dashboard';
?>
<header class="admin-topbar">
    <h1 class="topbar-title"><?php echo htmlspecialchars($title); ?></h1>
    <div class="topbar-user">
        Chào, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?>
    </div>
</header>