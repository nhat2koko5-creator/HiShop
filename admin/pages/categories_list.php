<?php
// FILE: admin/pages/categories_list.php

require_once '../src/config.php';
require_once '../src/functions.php';

// --- 1. XỬ LÝ LOGIC (Action) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
    $ten = trim($_POST['ten']);
    if ($ten != "") {
        $stmt = $pdo->prepare("INSERT INTO danh_muc (ten, trang_thai) VALUES (?, 1)");
        $stmt->execute([$ten]);
        $_SESSION['toast'] = ['type' => 'success', 'message' => 'Thêm danh mục thành công!'];
    }
    echo "<script>window.location.href='index.php?page=categories_list';</script>";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'edit') {
    $id = intval($_POST['edit_id']);
    $ten = trim($_POST['edit_ten']);
    if ($ten != "") {
        $stmt = $pdo->prepare("UPDATE danh_muc SET ten = ? WHERE id = ?");
        $stmt->execute([$ten, $id]);
        $_SESSION['toast'] = ['type' => 'success', 'message' => 'Cập nhật danh mục thành công!'];
    }
    echo "<script>window.location.href='index.php?page=categories_list';</script>";
    exit;
}

if (isset($_GET['toggle'])) {
    $id = intval($_GET['toggle']);
    $stmt = $pdo->prepare("SELECT trang_thai FROM danh_muc WHERE id = ?");
    $stmt->execute([$id]);
    $current = $stmt->fetchColumn();

    $newStatus = ($current == 1) ? 0 : 1;
    
    // Cập nhật trạng thái danh mục
    $update = $pdo->prepare("UPDATE danh_muc SET trang_thai = ? WHERE id = ?");
    $update->execute([$newStatus, $id]);
    
    // Cập nhật trạng thái tất cả sản phẩm trong danh mục
    $updateProducts = $pdo->prepare("UPDATE san_pham SET trang_thai = ? WHERE danh_muc_id = ?");
    $updateProducts->execute([$newStatus, $id]);

    $msg = ($newStatus == 1) ? 'Đã hiển thị danh mục và sản phẩm.' : 'Đã ẩn danh mục và sản phẩm.';
    $_SESSION['toast'] = ['type' => 'success', 'message' => $msg];
    
    echo "<script>window.location.href='index.php?page=categories_list';</script>";
    exit;
}

// --- 2. LẤY DỮ LIỆU & PHÂN TRANG ---
$limit = 10;
$page = isset($_GET['p']) ? max(1, intval($_GET['p'])) : 1;
$offset = ($page - 1) * $limit;
$keyword = $_GET['keyword'] ?? "";

$sql_count = "SELECT COUNT(*) FROM danh_muc WHERE ten LIKE :keyword";
$stmt_count = $pdo->prepare($sql_count);
$stmt_count->execute(['keyword' => "%$keyword%"]);
$total_records = $stmt_count->fetchColumn();
$total_pages = ceil($total_records / $limit);

$sql = "SELECT c.*, (SELECT COUNT(*) FROM san_pham sp WHERE sp.danh_muc_id = c.id) AS so_san_pham
        FROM danh_muc c
        WHERE c.ten LIKE :keyword
        ORDER BY c.id DESC
        LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute(['keyword' => "%$keyword%"]);
$categories = $stmt->fetchAll();

// --- 3. THỐNG KÊ ---
$stats = $pdo->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN trang_thai = 1 THEN 1 ELSE 0 END) as active,
    SUM(CASE WHEN trang_thai = 0 THEN 1 ELSE 0 END) as hidden
FROM danh_muc")->fetch();

$stat_total = $stats['total'] ?? 0;
$stat_active = $stats['active'] ?? 0;
$stat_hidden = $stats['hidden'] ?? 0;
?>

<link rel="stylesheet" href="../assets/css/admin/categories_list.css">

<div class="admin-page-content category-container">
    
    <div class="page-header-title">
        <i class="fa-solid fa-layer-group"></i> Quản lý Danh Mục
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon-box bg-blue">
                <i class="fa-solid fa-layer-group"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Tổng danh mục</div>
                <div class="stat-value"><?= $stat_total ?></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon-box bg-green">
                <i class="fa-solid fa-check-circle"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Đang hoạt động</div>
                <div class="stat-value"><?= $stat_active ?></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon-box bg-gray">
                <i class="fa-solid fa-eye-slash"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Danh mục ẩn</div>
                <div class="stat-value"><?= $stat_hidden ?></div>
            </div>
        </div>
    </div>

    <div class="main-card-wrapper">
        
        <div class="toolbar-wrapper">
            <form method="get" class="search-form">
                <input type="hidden" name="page" value="categories_list">
                <div class="search-box">
                    <input type="text" name="keyword" placeholder="Tìm kiếm danh mục..." value="<?= htmlspecialchars($keyword) ?>">
                    <button type="submit"><i class="fa-solid fa-magnifying-glass"></i></button>
                </div>
            </form>
            
            <button class="btn-add" onclick="openAddModal()">
                <i class="fa-solid fa-plus"></i> Thêm mới
            </button>
        </div>

        <table class="table-list">
            <thead>
                <tr>
                    <th width="80" class="text-center">ID</th>
                    <th>Tên danh mục</th>
                    <th>Số sản phẩm</th>
                    <th>Trạng thái</th>
                    <th class="text-right">Hành động</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($categories)): ?>
                    <tr><td colspan="5" class="text-center text-muted" style="padding: 40px;">Không tìm thấy dữ liệu.</td></tr>
                <?php else: ?>
                    <?php foreach ($categories as $cat): ?>
                    <tr>
                        <td class="text-center text-muted">#<?= $cat['id'] ?></td>
                        <td>
                            <div class="fw-bold"><?= htmlspecialchars($cat['ten']) ?></div>
                        </td>
                        <td>
                            <span class="count-badge">
                                <i class="fa-solid fa-box"></i> <?= $cat['so_san_pham'] ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($cat['trang_thai'] == 1): ?>
                                <span class="status-badge active">Hoạt động</span>
                            <?php else: ?>
                                <span class="status-badge inactive">Đang ẩn</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-right">
                            <div class="action-buttons">
                                <button class="btn-action btn-edit" 
                                        onclick="openEditModal(<?= $cat['id'] ?>, '<?= htmlspecialchars($cat['ten']) ?>')">
                                    <i class="fa-regular fa-pen-to-square"></i> Sửa
                                </button>

                                <a href="index.php?page=categories_list&toggle=<?= $cat['id'] ?>" 
                                   class="btn-action <?= ($cat['trang_thai'] == 1) ? 'btn-hide' : 'btn-show' ?>">
                                    <?php if ($cat['trang_thai'] == 1): ?>
                                        <i class="fa-regular fa-eye-slash"></i> Ẩn
                                    <?php else: ?>
                                        <i class="fa-regular fa-eye"></i> Hiện
                                    <?php endif; ?>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <?php if ($total_pages > 1): ?>
        <div class="pagination-area">
            <span class="page-info">Trang <strong><?= $page ?></strong> / <?= $total_pages ?></span>
            <div class="page-list">
                <?php 
                    $queryParams = $_GET; unset($queryParams['page']);
                ?>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="index.php?page=categories_list&<?= http_build_query(array_merge($queryParams, ['p' => $i])) ?>" 
                       class="page-number <?= ($i == $page) ? 'active' : '' ?>">
                       <?= $i ?>
                    </a>
                <?php endfor; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<div id="modalAdd" class="admin-modal">
    <div class="admin-modal-content">
        <h3 class="modal-title">Thêm Danh Mục Mới</h3>
        <form method="post">
            <input type="hidden" name="action" value="add">
            <div style="margin-bottom: 20px;">
                <label class="form-label">Tên danh mục <span style="color:red">*</span></label>
                <input type="text" name="ten" class="form-control" placeholder="Nhập tên danh mục..." required>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="closeModal('modalAdd')" class="btn-modal btn-cancel">Hủy</button>
                <button type="submit" class="btn-modal btn-submit">Thêm mới</button>
            </div>
        </form>
    </div>
</div>

<div id="modalEdit" class="admin-modal">
    <div class="admin-modal-content">
        <h3 class="modal-title">Cập Nhật Danh Mục</h3>
        <form method="post">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="edit_id" id="edit_id">
            <div style="margin-bottom: 20px;">
                <label class="form-label">Tên danh mục <span style="color:red">*</span></label>
                <input type="text" name="edit_ten" id="edit_ten" class="form-control" required>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="closeModal('modalEdit')" class="btn-modal btn-cancel">Hủy</button>
                <button type="submit" class="btn-modal btn-submit">Lưu thay đổi</button>
            </div>
        </form>
    </div>
</div>

<div id="adminToast" class="admin-toast" style="display:none;"></div>

<script>
    // JS Logic
    function showToast(type, msg) { /* Logic Toast đã có */ }
    document.addEventListener('DOMContentLoaded', function() {
        <?php if (isset($_SESSION['toast'])): ?>
            // Giả lập hiện toast nếu bạn có hàm showToast
            <?php unset($_SESSION['toast']); ?>
        <?php endif; ?>
    });
    function openAddModal() { document.getElementById('modalAdd').classList.add('show'); }
    function openEditModal(id, ten) {
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_ten').value = ten;
        document.getElementById('modalEdit').classList.add('show');
    }
    function closeModal(id) { document.getElementById(id).classList.remove('show'); }
    window.onclick = function(event) {
        if (event.target.classList.contains('admin-modal')) event.target.classList.remove('show');
    }
</script>