<?php
// ========================
// LOAD CONFIG TRƯỚC KHI GỬI HTML
// ========================
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
    CHUYỂN TRẠNG THÁI HIỆN / ẨN
========================== */
if (isset($_GET['toggle'])) {
    $id = intval($_GET['toggle']);

    $stmt = $pdo->prepare("SELECT trang_thai FROM danh_muc WHERE id = ?");
    $stmt->execute([$id]);
    $current = $stmt->fetchColumn();

    $newStatus = ($current == 1) ? 0 : 1;

    $update = $pdo->prepare("UPDATE danh_muc SET trang_thai = ? WHERE id = ?");
    $update->execute([$newStatus, $id]);

    header("Location: index.php?page=categories_list&toggled=1");
    exit;
}

/* ==========================
    LOAD DANH SÁCH DANH MỤC
========================== */
$keyword = $_GET['keyword'] ?? "";
$sql = "SELECT c.*, (SELECT COUNT(*) FROM san_pham sp WHERE sp.danh_muc_id = c.id) AS so_san_pham
        FROM danh_muc c
        WHERE c.ten LIKE :keyword
        ORDER BY c.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute(['keyword' => "%$keyword%"]);
$categories = $stmt->fetchAll();

// =============================
// CHỈ BAO GIỜ XUẤT HTML SAU ĐÂY
// =============================
?>

<?php require_once 'layouts/header.php'; ?>

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

<?php if (!empty($_GET['toggled'])): ?>
    <div class="alert alert-success">Đã thay đổi trạng thái danh mục.</div>
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
                            <a href="#" 
                            class="btn btn-sm btn-warning"
                            onclick="openEditModal(<?= $cat['id'] ?>, '<?= htmlspecialchars($cat['ten'], ENT_QUOTES) ?>')">
                                <i class="fa-solid fa-pen"></i>
                            </a>

                            <a href="index.php?page=categories_list&toggle=<?= $cat['id'] ?>"
                            class="btn btn-sm btn-info">

                                <?php if ($cat['trang_thai'] == 1): ?>
                                    <i class="fa-solid fa-eye"></i>
                                <?php else: ?>
                                    <i class="fa-solid fa-eye-slash"></i>
                                <?php endif; ?>
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
            <input type="text" name="ten" required>
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

<script>
function openModal(){ document.getElementById('modalAdd').style.display = 'flex'; }
function closeModal(){ document.getElementById('modalAdd').style.display = 'none'; }

function openEditModal(id, ten){
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_ten').value = ten;
    document.getElementById('modalEdit').style.display = 'flex';
}
function closeEditModal(){ document.getElementById('modalEdit').style.display = 'none'; }
</script>

