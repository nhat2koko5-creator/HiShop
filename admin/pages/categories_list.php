<?php
// FILE: admin/pages/categories_list.php

require_once '../src/config.php';
require_once '../src/functions.php';

// --- 1. XỬ LÝ LOGIC (Action) ---

// A. THÊM DANH MỤC
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

// B. SỬA DANH MỤC
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

// C. ẨN / HIỆN DANH MỤC
if (isset($_GET['toggle'])) {
    $id = intval($_GET['toggle']);
    $stmt = $pdo->prepare("SELECT trang_thai FROM danh_muc WHERE id = ?");
    $stmt->execute([$id]);
    $current = $stmt->fetchColumn();

    $newStatus = ($current == 1) ? 0 : 1;
    $update = $pdo->prepare("UPDATE danh_muc SET trang_thai = ? WHERE id = ?");
    $update->execute([$newStatus, $id]);

    $msg = ($newStatus == 1) ? 'Đã hiển thị danh mục.' : 'Đã ẩn danh mục.';
    $_SESSION['toast'] = ['type' => 'success', 'message' => $msg];
    
    echo "<script>window.location.href='index.php?page=categories_list';</script>";
    exit;
}

// --- 2. LẤY DỮ LIỆU & PHÂN TRANG ---

// Config
$limit = 10;
$page = isset($_GET['p']) ? max(1, intval($_GET['p'])) : 1;
$offset = ($page - 1) * $limit;
$keyword = $_GET['keyword'] ?? "";

// Query đếm tổng (cho phân trang)
$sql_count = "SELECT COUNT(*) FROM danh_muc WHERE ten LIKE :keyword";
$stmt_count = $pdo->prepare($sql_count);
$stmt_count->execute(['keyword' => "%$keyword%"]);
$total_records = $stmt_count->fetchColumn();
$total_pages = ceil($total_records / $limit);

// Query lấy dữ liệu hiển thị
$sql = "SELECT c.*, (SELECT COUNT(*) FROM san_pham sp WHERE sp.danh_muc_id = c.id) AS so_san_pham
        FROM danh_muc c
        WHERE c.ten LIKE :keyword
        ORDER BY c.id DESC
        LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute(['keyword' => "%$keyword%"]);
$categories = $stmt->fetchAll();

// --- 3. THỐNG KÊ (Query toàn bộ để hiển thị Stats đúng) ---
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

<div class="cat-page-container">
    
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon-wrapper stat-blue"><i class="fa-solid fa-layer-group"></i></div>
            <div class="stat-content">
                <h3><?= $stat_total ?></h3>
                <p>Tổng danh mục</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon-wrapper stat-green"><i class="fa-solid fa-eye"></i></div>
            <div class="stat-content">
                <h3><?= $stat_active ?></h3>
                <p>Đang hiển thị</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon-wrapper stat-gray"><i class="fa-solid fa-eye-slash"></i></div>
            <div class="stat-content">
                <h3><?= $stat_hidden ?></h3>
                <p>Đang ẩn</p>
            </div>
        </div>
    </div>

    <div class="page-toolbar">
        <form method="get" style="margin:0; flex-grow: 1; display: flex; justify-content: flex-end;">
            <input type="hidden" name="page" value="categories_list">
            <div class="modern-search">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" name="keyword" placeholder="Tìm kiếm danh mục..." value="<?= htmlspecialchars($keyword) ?>">
            </div>
        </form>
        
        <button class="btn-add-primary" onclick="openAddModal()">
            <i class="fa-solid fa-plus"></i> Thêm mới
        </button>
    </div>

<?php if (!empty($_GET['updated'])): ?>
    <div class="alert alert-success">Đã cập nhật danh mục thành công.</div>
<?php endif; ?>

<?php if (!empty($_GET['toggled'])): ?>
    <div class="alert alert-success">Đã thay đổi trạng thái danh mục.</div>
<?php endif; ?>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light" style="background-color: #0676e5ff; color: white;">
                <tr>
                    <th width="80" style="text-align: center;">ID</th>
                    <th width="35%">Tên danh mục</th>
                    <th width="20%">Số sản phẩm</th>
                    <th width="20%">Trạng thái</th>
                    <th width="25%" style="text-align: right;">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($categories)): ?>
                    <tr><td colspan="5" style="text-align: center; padding: 40px; color: #94a3b8;">Không tìm thấy danh mục nào.</td></tr>
                <?php else: ?>
                    <?php foreach ($categories as $cat): ?>
                    <tr>
                        <td style="text-align: center; color: #64748b;">#<?= $cat['id'] ?></td>
                        <td>
                            <div style="font-weight: 600; color: #1e293b;"><?= htmlspecialchars($cat['ten']) ?></div>
                        </td>
                        <td>
                            <div style="display: inline-flex; align-items: center; gap: 5px; background: #f8fafc; padding: 4px 10px; border-radius: 6px; font-weight: 500;">
                                <i class="fa-solid fa-box" style="font-size: 12px; color: #64748b;"></i>
                                <?= $cat['so_san_pham'] ?>
                            </div>
                        </td>
                        <td>
                            <?php if ($cat['trang_thai'] == 1): ?>
                                <span class="status-badge badge-active">
                                    <span class="badge-dot"></span> Hiển thị
                                </span>
                            <?php else: ?>
                                <span class="status-badge badge-hidden">
                                    <span class="badge-dot"></span> Đang ẩn
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="action-btn-group">
                                <button class="btn-action-pill btn-edit" title="Chỉnh sửa"
                                        onclick="openEditModal(<?= $cat['id'] ?>, '<?= htmlspecialchars($cat['ten']) ?>')">
                                    <i class="fa-solid fa-pen"></i>
                                    <span>Sửa</span>
                                </button>

                                <a href="index.php?page=categories_list&toggle=<?= $cat['id'] ?>" 
                                   class="btn-action-pill <?= ($cat['trang_thai'] == 1) ? 'btn-toggle-hide' : 'btn-toggle-show' ?>"
                                   title="<?= ($cat['trang_thai'] == 1) ? 'Ẩn danh mục' : 'Hiện danh mục' ?>">
                                    <?php if ($cat['trang_thai'] == 1): ?>
                                        <i class="fa-solid fa-eye-slash"></i>
                                        <span>Ẩn</span>
                                    <?php else: ?>
                                        <i class="fa-solid fa-eye"></i>
                                        <span>Hiện</span>
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
        <div class="pagination-container">
            <div class="pagination-info">
                Hiển thị <strong><?= count($categories) ?></strong> trên tổng số <strong><?= $total_records ?></strong> danh mục
            </div>
            <div class="pagination-links">
                <?php 
                    $queryParams = $_GET;
                    unset($queryParams['page']);
                ?>
                
                <?php if ($page > 1): ?>
                    <a href="index.php?page=categories_list&<?= http_build_query(array_merge($queryParams, ['p' => $page - 1])) ?>" class="page-link normal">
                        <i class="fa-solid fa-chevron-left"></i>
                    </a>
                <?php else: ?>
                    <span class="page-link disabled"><i class="fa-solid fa-chevron-left"></i></span>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="page-link active"><?= $i ?></span>
                    <?php else: ?>
                        <a href="index.php?page=categories_list&<?= http_build_query(array_merge($queryParams, ['p' => $i])) ?>" class="page-link normal"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="index.php?page=categories_list&<?= http_build_query(array_merge($queryParams, ['p' => $page + 1])) ?>" class="page-link normal">
                        <i class="fa-solid fa-chevron-right"></i>
                    </a>
                <?php else: ?>
                    <span class="page-link disabled"><i class="fa-solid fa-chevron-right"></i></span>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<div id="modalAdd" class="admin-modal">
    <div class="admin-modal-content">
        <h3 style="margin-top:0; color:#1e293b;">Thêm Danh Mục Mới</h3>
        <form method="post">
            <input type="hidden" name="action" value="add">
            <div class="form-group">
                <label>Tên danh mục <span style="color:red">*</span></label>
                <input type="text" name="ten" class="form-input" placeholder="Ví dụ: Laptop Gaming..." required>
            </div>
            <div style="display:flex; gap:10px; margin-top:20px;">
                <button type="button" onclick="closeModal('modalAdd')" style="flex:1; padding:10px; border:1px solid #e2e8f0; background:#fff; border-radius:8px; cursor:pointer; font-weight:600; color:#64748b;">Hủy</button>
                <button type="submit" style="flex:1; padding:10px; border:none; background:var(--primary-color); color:#fff; border-radius:8px; cursor:pointer; font-weight:600;">Thêm mới</button>
            </div>
        </form>
    </div>
</div>

<div id="modalEdit" class="admin-modal">
    <div class="admin-modal-content">
        <h3 style="margin-top:0; color:#1e293b;">Cập Nhật Danh Mục</h3>
        <form method="post">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="edit_id" id="edit_id">
            
            <div class="form-group">
                <label>Tên danh mục <span style="color:red">*</span></label>
                <input type="text" name="edit_ten" id="edit_ten" class="form-input" required>
            </div>
            
            <div style="display:flex; gap:10px; margin-top:20px;">
                <button type="button" onclick="closeModal('modalEdit')" style="flex:1; padding:10px; border:1px solid #e2e8f0; background:#fff; border-radius:8px; cursor:pointer; font-weight:600; color:#64748b;">Hủy</button>
                <button type="submit" style="flex:1; padding:10px; border:none; background:var(--primary-color); color:#fff; border-radius:8px; cursor:pointer; font-weight:600;">Lưu thay đổi</button>
            </div>
        </form>
    </div>
</div>

<div id="adminToast" class="admin-toast">
    <div style="font-size:24px;" class="toast-icon"><i class="fa-solid fa-circle-check"></i></div>
    <div>
        <h4 style="margin:0 0 4px; font-size:15px; color:#1e293b;">Thông báo</h4>
        <p id="toastMsg" style="margin:0; font-size:13px; color:#64748b;">...</p>
    </div>
</div>

<script>
    // TOAST LOGIC
    function showToast(type, msg) {
        const toast = document.getElementById('adminToast');
        const icon = toast.querySelector('.toast-icon i');
        const text = document.getElementById('toastMsg');
        toast.className = 'admin-toast show';
        
        if(type === 'success') {
            toast.style.borderLeftColor = '#10b981';
            icon.className = 'fa-solid fa-circle-check'; icon.style.color = '#10b981';
        } else {
            toast.style.borderLeftColor = '#ef4444';
            icon.className = 'fa-solid fa-circle-xmark'; icon.style.color = '#ef4444';
        }
        text.innerHTML = msg;
        setTimeout(() => { toast.classList.remove('show'); }, 3000);
    }

    document.addEventListener('DOMContentLoaded', function() {
        <?php if (isset($_SESSION['toast'])): ?>
            showToast('<?= $_SESSION['toast']['type'] ?>', '<?= $_SESSION['toast']['message'] ?>');
            <?php unset($_SESSION['toast']); ?>
        <?php endif; ?>
    });

    // MODAL LOGIC
    function openAddModal() {
        document.getElementById('modalAdd').classList.add('show');
    }
    
    function openEditModal(id, ten) {
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_ten').value = ten;
        document.getElementById('modalEdit').classList.add('show');
    }

    function closeModal(id) {
        document.getElementById(id).classList.remove('show');
    }

    // Close on click outside
    window.onclick = function(event) {
        if (event.target.classList.contains('admin-modal')) {
            event.target.classList.remove('show');
        }
    }
</script>