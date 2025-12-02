<?php
require_once '../src/config.php';
require_once '../src/functions.php';

/* ==========================
    THÊM SẢN PHẨM
========================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ten'])) {
    $ten = trim($_POST['ten']);
    $gia = $_POST['gia'];
    $so_luong = $_POST['so_luong'];
    $hinh_anh = $_POST['hinh_anh'];
    $mo_ta = $_POST['mo_ta'];
    $danh_muc = $_POST['danh_muc'];

    if ($ten != "") {
        $stmt = $pdo->prepare("INSERT INTO san_pham (ten, gia, so_luong, hinh_anh, mo_ta, trang_thai, danh_muc_id)
                               VALUES (?, ?, ?, ?, ?, 1, ?)");
        $stmt->execute([$ten, $gia, $so_luong, $hinh_anh, $mo_ta, $danh_muc]);

        header("Location: index.php?page=products_list&added=1");
        exit;
    }
}

/* ==========================
    SỬA SẢN PHẨM
========================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'])) {
    $id = intval($_POST['edit_id']);
    $ten = trim($_POST['edit_ten']);
    $gia = $_POST['edit_gia'];
    $so_luong = $_POST['edit_so_luong'];
    $hinh_anh = $_POST['edit_hinh_anh'];
    $mo_ta = $_POST['edit_mo_ta'];
    $danh_muc = $_POST['edit_danh_muc'];

    if ($ten != "") {
        $stmt = $pdo->prepare("UPDATE san_pham 
                               SET ten=?, gia=?, so_luong=?, hinh_anh=?, mo_ta=?, danh_muc_id=?
                               WHERE id=?");
        $stmt->execute([$ten, $gia, $so_luong, $hinh_anh, $mo_ta, $danh_muc, $id]);

        header("Location: index.php?page=products_list&updated=1");
        exit;
    }
}

/* ==========================
    ẨN / HIỆN SẢN PHẨM
========================== */
if (isset($_GET['toggle'])) {
    $id = intval($_GET['toggle']);

    $stmt = $pdo->prepare("SELECT trang_thai FROM san_pham WHERE id = ?");
    $stmt->execute([$id]);
    $status = $stmt->fetchColumn();

    $newStatus = ($status == 1 ? 0 : 1);

    $update = $pdo->prepare("UPDATE san_pham SET trang_thai = ? WHERE id = ?");
    $update->execute([$newStatus, $id]);

    header("Location: index.php?page=products_list&toggled=1");
    exit;
}

/* ==========================
    LOAD SẢN PHẨM
========================== */
$keyword = $_GET['keyword'] ?? '';

$sql = "SELECT sp.*, dm.ten AS ten_danh_muc 
        FROM san_pham sp 
        LEFT JOIN danh_muc dm ON sp.danh_muc_id = dm.id
        WHERE sp.ten LIKE :keyword
        ORDER BY sp.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute(['keyword' => "%$keyword%"]);
$products = $stmt->fetchAll();

/* ==========================
    LOAD DANH MỤC CHO SELECT
========================== */
$categories = $pdo->query("SELECT * FROM danh_muc ORDER BY ten ASC")->fetchAll();
?>
<?php require_once 'layouts/header.php'; ?>

<style>

</style>
<div class="admin-page">
<h1 class="title">QUẢN LÝ SẢN PHẨM</h1>
<div class="action-bar">
    <button class="btn-add" onclick="openModal()">+ Thêm sản phẩm</button>
<form method="GET" class="search-wrapper" action="index.php">
    <input type="hidden" name="page" value="products_list">

    <input type="text" 
           class="search-input" 
           name="keyword"
           placeholder="Tìm kiếm sản phẩm..."
           value="<?= htmlspecialchars($keyword) ?>">

    <button class="search-btn">
        <i class="fa-solid fa-search"></i>
    </button>
</form>

</div>


<?php if (!empty($_GET['added'])): ?>
    <div class="alert alert-success">Đã thêm sản phẩm thành công.</div>
<?php endif; ?>

<?php if (!empty($_GET['updated'])): ?>
    <div class="alert alert-success">Đã cập nhật sản phẩm thành công.</div>
<?php endif; ?>

<?php if (!empty($_GET['toggled'])): ?>
    <div class="alert alert-success">Đã thay đổi trạng thái sản phẩm.</div>
<?php endif; ?>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th width="60">ID</th>
                    <th>Hình ảnh</th>
                    <th>Tên sản phẩm</th>
                    <th>Giá</th>
                    <th>Số lượng</th>
                    <th>Danh mục</th>
                    <th width="140" class="text-end">Thao tác</th>
                </tr>
            </thead>

            <tbody>
                <?php if (count($products) == 0): ?>
                    <tr>
                        <td colspan="7" class="text-center p-4 text-muted">Không có sản phẩm nào.</td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($products as $p): ?>
                    <tr>
                        <td class="text-center"><?= $p['id'] ?></td>
                        <td><img src="/HiShop/assets/img/products/<?= $p['hinh_anh'] ?>" width="60"></td>
                        <td><?= htmlspecialchars($p['ten']) ?></td>
                        <td><?= number_format($p['gia']) ?>₫</td>
                        <td><?= $p['so_luong'] ?></td>
                        <td><?= $p['ten_danh_muc'] ?? "Không có" ?></td>

                        <td class="text-end">
                            <!-- SỬA -->
                            <a href="#"
                               class="btn btn-sm btn-warning"
                               onclick="openEditModal(
                                            <?= $p['id'] ?>,
                                            '<?= htmlspecialchars($p['ten'], ENT_QUOTES) ?>',
                                            '<?= $p['gia'] ?>',
                                            '<?= $p['so_luong'] ?>',
                                            '<?= htmlspecialchars($p['hinh_anh'], ENT_QUOTES) ?>',
                                            '<?= htmlspecialchars($p['mo_ta'], ENT_QUOTES) ?>',
                                            '<?= $p['danh_muc_id'] ?>'
                                        )">
                                <i class="fa-solid fa-pen"></i>
                            </a>

                            <!-- ẨN / HIỆN -->
                            <a href="index.php?page=products_list&toggle=<?= $p['id'] ?>" class="btn btn-sm btn-info">
                                <?php if ($p['trang_thai'] == 1): ?>
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
        <h3>Thêm sản phẩm</h3>

        <form method="post">
            <label>Tên sản phẩm:</label>
            <input type="text" name="ten" required>

            <label>Giá:</label>
            <input type="number" name="gia" required>

            <label>Số lượng:</label>
            <input type="number" name="so_luong" required>

            <label>Hình ảnh (URL):</label>
            <input type="text" name="hinh_anh" required>

            <label>Mô tả:</label>
            <input type="text" name="mo_ta">

            <label>Danh mục:</label>
            <select name="danh_muc" required>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= $c['ten'] ?></option>
                <?php endforeach; ?>
            </select>

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
        <h3>Sửa sản phẩm</h3>

        <form method="post">
            <input type="hidden" name="edit_id" id="edit_id">

            <label>Tên sản phẩm:</label>
            <input type="text" name="edit_ten" id="edit_ten" required>

            <label>Giá:</label>
            <input type="number" name="edit_gia" id="edit_gia" required>

            <label>Số lượng:</label>
            <input type="number" name="edit_so_luong" id="edit_so_luong" required>

            <label>Hình ảnh (URL):</label>
            <input type="text" name="edit_hinh_anh" id="edit_hinh_anh" required>

            <label>Mô tả:</label>
            <input type="text" name="edit_mo_ta" id="edit_mo_ta">

            <label>Danh mục:</label>
            <select name="edit_danh_muc" id="edit_danh_muc" required>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= $c['ten'] ?></option>
                <?php endforeach; ?>
            </select>

            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeEditModal()">Hủy</button>
                <button type="submit" class="btn-save">Lưu</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(){
    document.getElementById('modalAdd').style.display = 'flex';
}
function closeModal(){
    document.getElementById('modalAdd').style.display = 'none';
}

function openEditModal(id, ten, gia, so_luong, hinh_anh, mo_ta, danh_muc){
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_ten').value = ten;
    document.getElementById('edit_gia').value = gia;
    document.getElementById('edit_so_luong').value = so_luong;
    document.getElementById('edit_hinh_anh').value = hinh_anh;
    document.getElementById('edit_mo_ta').value = mo_ta;
    document.getElementById('edit_danh_muc').value = danh_muc;

    document.getElementById('modalEdit').style.display = 'flex';
}

function closeEditModal(){
    document.getElementById('modalEdit').style.display = 'none';
}
</script>
