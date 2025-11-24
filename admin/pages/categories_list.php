<?php
require_once '../src/config.php';
require_once '../src/functions.php';

/* ==========================
    XỬ LÝ THÊM DANH MỤC
========================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ten'])) {
    $ten = trim($_POST['ten']);

    if ($ten != "") {
        $stmt = $pdo->prepare("INSERT INTO danh_muc (ten, trang_thai) VALUES (?, 1)");
        $stmt->execute([$ten]);
        header("Location: index.php?page=categories_list&added=1");
        exit;
    }
}

/* ==========================
    XỬ LÝ SỬA DANH MỤC
========================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'])) {
    $id = intval($_POST['edit_id']);
    $ten = trim($_POST['edit_ten']);

    if ($ten != "") {
        $stmt = $pdo->prepare("UPDATE danh_muc SET ten = ? WHERE id = ?");
        $stmt->execute([$ten, $id]);
        header("Location: index.php?page=categories_list&updated=1");
        exit;
    }
}

/* ==========================
    LOAD DANH SÁCH DANH MỤC
========================== */
$keyword = $_GET['keyword'] ?? '';
$sql = "SELECT c.*, (SELECT COUNT(*) FROM san_pham sp WHERE sp.danh_muc_id = c.id) AS so_san_pham
        FROM danh_muc c
        WHERE c.ten LIKE :keyword
        ORDER BY c.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute(['keyword' => "%$keyword%"]);
$categories = $stmt->fetchAll();

/* ==========================
    XÓA DANH MỤC
========================== */
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $check = $pdo->prepare("SELECT COUNT(*) FROM san_pham WHERE danh_muc_id = ?");
    $check->execute([$id]);

    if ($check->fetchColumn() > 0) {
        $error = "Không thể xoá vì danh mục đang có sản phẩm!";
    } else {
        $del = $pdo->prepare("DELETE FROM danh_muc WHERE id = ?");
        $del->execute([$id]);
        header("Location: index.php?page=categories_list&deleted=1");
        exit;
    }
}
?>

<div class="admin-page">
<h1 class="title">QUẢN LÝ DANH MỤC</h1>

<div class="actions">
    <button class="btn btn-primary add-btn" onclick="openModal()">
        <i class="fa-solid fa-plus"></i> Thêm danh mục
    </button>

    <form class="search-form" method="get">
        <input type="hidden" name="page" value="categories_list">
        <div class="search-box">
            <input type="text" name="keyword" placeholder="Tìm kiếm danh mục..." value="<?= htmlspecialchars($keyword) ?>">
            <button class="btn-search"><i class="fa-solid fa-search"></i></button>
        </div>
    </form>
</div>

<?php if (!empty($_GET['added'])): ?>
    <div class="alert alert-success">Đã thêm danh mục thành công.</div>
<?php endif; ?>

<?php if (!empty($_GET['updated'])): ?>
    <div class="alert alert-success">Đã cập nhật danh mục thành công.</div>
<?php endif; ?>

<?php if (!empty($_GET['deleted'])): ?>
    <div class="alert alert-success">Đã xoá danh mục thành công.</div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th width="60">ID</th>
                    <th class="text-center">Tên danh mục</th>
                    <th width="200" class="text-center">Số sản phẩm</th>
                    <th width="160" class="text-end">Thao tác</th>
                </tr>
            </thead>

            <tbody>
                <?php if (count($categories) == 0): ?>
                    <tr>
                        <td colspan="4" class="text-center p-4 text-muted">Không có danh mục nào.</td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($categories as $cat): ?>
                    <tr>
                        <td class="text-center"><?= $cat['id'] ?></td>
                        <td class="text-center"><?= htmlspecialchars($cat['ten']) ?></td>
                        <td class="text-center"><?= $cat['so_san_pham'] ?></td>

                        <td class="text-end">
                            <!-- NÚT SỬA DÙNG MODAL -->
                            <a href="#" 
                               class="btn btn-sm btn-warning"
                               onclick="openEditModal(<?= $cat['id'] ?>, '<?= htmlspecialchars($cat['ten'], ENT_QUOTES) ?>')">
                               <i class="fa-solid fa-pen"></i>
                            </a>

                            <a href="index.php?page=categories_list&delete=<?= $cat['id'] ?>"
                               onclick="return confirm('Bạn có chắc muốn xoá danh mục này?')"
                               class="btn btn-sm btn-danger">
                                <i class="fa-solid fa-trash"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>

        </table>
    </div>
</div>

<!-- MODAL THÊM -->
<div id="modalAdd" class="modal-overlay" style="display:none;">
    <div class="modal-box">
        <h3>Thêm danh mục</h3>
        <form method="post">
            <label>Tên danh mục:</label>
            <input type="text" name="ten" required placeholder="Nhập tên danh mục...">
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeModal()">Hủy</button>
                <button type="submit" class="btn-save">Lưu</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL SỬA -->
<div id="modalEdit" class="modal-overlay" style="display:none;">
    <div class="modal-box">
        <h3>Sửa danh mục</h3>

        <form method="post">
            <input type="hidden" name="edit_id" id="edit_id">

            <label>Tên danh mục:</label>
            <input type="text" name="edit_ten" id="edit_ten" required>

            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeEditModal()">Hủy</button>
                <button type="submit" class="btn-save">Lưu</button>
            </div>
        </form>
    </div>
</div>

<style>
.title { text-align: center; color: #1d7bff; font-size: 40px; margin: 20px 0 40px; font-weight: bold; } 
.admin-page { margin-top: 30px; } 
.actions { display: flex; justify-content: flex-end; align-items: center; gap: 10px; margin-bottom: 30px; } 
/* ===== BUTTON ===== */ 
.add-btn { padding: 10px 22px; background: #2563eb; color: #fff; border: none; border-radius: 10px; cursor: pointer; } 
.add-btn:hover { background: #1d4ed8; }
 /* ===== SEARCH BOX ===== */ 
 .search-box { display: flex; border: 1px solid #d1d5db; border-radius: 10px; overflow: hidden; } 
 .search-box input { border: none; padding: 8px 14px; outline: none; height: 40px; } 
 .btn-search { width: 45px; border: none; border-left: 1px solid #d1d5db; background: white; cursor: pointer; } 
 /* ===== TABLE ===== */ 
 .card { border-radius: 14px !important; border: 1px solid #e5e7eb; box-shadow: 0 4px 14px rgba(0,0,0,0.06); width: 100%; } 
 /* Bảng rộng và đẹp */ 
 .table { width: 100% !important; table-layout: auto; font-size: 16px; } 
 /* Giãn dòng bảng */ 
 .table th, .table td { padding: 18px 14px !important; vertical-align: middle; border-bottom: 1px solid #e5e7eb; } 
 /* Hover */ 
 .table tbody tr:hover { background: #f9fafb; } 
 /* Căn chỉnh từng cột */ 
 .table th:nth-child(1), 
 .table td:nth-child(1), 
 .table th:nth-child(2), 
 .table td:nth-child(2), 
 .table th:nth-child(3), 
 .table td:nth-child(3) { text-align: center; } 
 .table th:nth-child(4), 
 .table td:nth-child(4) { text-align: right; } 
 /* ===== MODAL ===== */ 
 .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.45); display: flex; justify-content: center; align-items: center; z-index: 9999; } 
 .modal-box { background: white; padding: 25px; width: 380px; border-radius: 12px; box-shadow: 0 6px 20px rgba(0,0,0,0.2); } 
 .modal-box h3 { margin-bottom: 15px; font-size: 20px; font-weight: 700; text-align: center; } 
 .modal-box input { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 8px; margin-bottom: 18px; font-size: 15px; } 
 .modal-actions { display: flex; justify-content: flex-end; gap: 10px; } /* Buttons trong modal */ .btn-cancel { padding: 8px 16px; background: #e5e7eb; border: none; border-radius: 8px; cursor: pointer; } 
 .btn-save { padding: 8px 18px; background: #2563eb; border: none; color: white; border-radius: 8px; cursor: pointer; } 
 .btn-save:hover { background: #1d4ed8; } .alert { padding: 12px 18px; border-radius: 8px; margin-bottom: 20px; font-size: 16px; } 
 .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; } 
 .alert-danger { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
</style>

<script>
function openModal(){ 
    document.getElementById('modalAdd').style.display = 'flex'; 
}
function closeModal(){ 
    document.getElementById('modalAdd').style.display = 'none'; 
}

function openEditModal(id, ten){
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_ten').value = ten;
    document.getElementById('modalEdit').style.display = 'flex';
}

function closeEditModal(){
    document.getElementById('modalEdit').style.display = 'none';
}
</script>
