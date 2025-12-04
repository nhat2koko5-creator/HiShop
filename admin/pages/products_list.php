<?php
require_once '../src/config.php';
require_once '../src/functions.php';

// ================================
// 1. XỬ LÝ HÀNH ĐỘNG (toggle)
// ================================
if (isset($_GET['toggle'])) {
    $id = intval($_GET['toggle']);

    $stmt = $pdo->prepare("SELECT trang_thai FROM san_pham WHERE id = ?");
    $stmt->execute([$id]);
    $status = $stmt->fetchColumn();

    $newStatus = ($status == 1 ? 0 : 1);

    $pdo->prepare("UPDATE san_pham SET trang_thai = ? WHERE id = ?")
        ->execute([$newStatus, $id]);
    header("Location: index.php?page=products_list&toggled=1");
    exit;
}
// ================================
// 2. CHỈ INCLUDE HEADER SAU KHI
//    TOÀN BỘ LOGIC PHP ĐÃ XONG
// ================================
require_once 'layouts/header.php';
?>
<?php
/* ==========================
    THÊM SẢN PHẨM (CÓ BIẾN THỂ)
========================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ten'])) {

    $ten = trim($_POST['ten']);
    $mo_ta = $_POST['mo_ta'];
    $danh_muc = $_POST['danh_muc'];
    $variants = json_decode($_POST['variants_json'], true);

    /* ---- TÍNH TỔNG VÀ GIÁ THẤP NHẤT ---- */
    $tong_sl = array_sum(array_column($variants, 'ton'));
    $gia_min = min(array_column($variants, 'gia'));

    /* ---- UPLOAD HÌNH CHÍNH ---- */
    $hinh_anh = "";
    if (!empty($_FILES['hinh_anh']['name'])) {
        $fileName = time() . "_" . basename($_FILES["hinh_anh"]["name"]);
        $targetPath = "../assets/img/products/" . $fileName;
        move_uploaded_file($_FILES["hinh_anh"]["tmp_name"], $targetPath);
        $hinh_anh = $fileName;
    }

    /* ---- LƯU SẢN PHẨM ---- */
    $stmt = $pdo->prepare("
        INSERT INTO san_pham (ten, gia, so_luong, hinh_anh, mo_ta, trang_thai, danh_muc_id)
        VALUES (?, ?, ?, ?, ?, 1, ?)
    ");
    $stmt->execute([$ten, $gia_min, $tong_sl, $hinh_anh, $mo_ta, $danh_muc]);

    $product_id = $pdo->lastInsertId();

    /* ---- LƯU BIẾN THỂ ---- */
foreach ($variants as $v) {

    $imgName = "";
    $idx = $v['img_index']; // lấy index đã lưu trong JS

    if (!empty($_FILES['variant_imgs']['name'][$idx])) {

        $imgName = time() . "_" . basename($_FILES['variant_imgs']['name'][$idx]);
        move_uploaded_file(
            $_FILES['variant_imgs']['tmp_name'][$idx],
            "../assets/img/products/" . $imgName
        );
    }

    $stmt2 = $pdo->prepare("
        INSERT INTO bien_the_san_pham 
        (san_pham_id, mau_sac, dung_luong_ssd, gia, so_luong_ton, hinh_anh)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $stmt2->execute([
        $product_id,
        $v['mau'],
        $v['ssd'],
        $v['gia'],
        $v['ton'],
        $imgName
    ]);
}
    header("Location: index.php?page=products_list&added=1");
    exit;
}


/* ==========================
    SỬA SẢN PHẨM
========================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['variant_id'])) {

    $id = intval($_POST['variant_id']);
    $mau = $_POST['variant_mau'];
    $ssd = $_POST['variant_ssd'];
    $gia = $_POST['variant_gia'];
    $ton = $_POST['variant_ton'];
    $hinh = $_POST['variant_hinh'];

    // UPDATE BIẾN THỂ
    $stmt = $pdo->prepare("
        UPDATE bien_the_san_pham
        SET mau_sac=?, dung_luong_ssd=?, gia=?, so_luong_ton=?, hinh_anh=?
        WHERE id=?
    ");
    $stmt->execute([$mau, $ssd, $gia, $ton, $hinh, $id]);

    // UPDATE TỔNG SỐ LƯỢNG SẢN PHẨM
    $stmt2 = $pdo->prepare("
        UPDATE san_pham
        SET so_luong = (
            SELECT COALESCE(SUM(so_luong_ton), 0)
            FROM bien_the_san_pham
            WHERE san_pham_id = (SELECT san_pham_id FROM bien_the_san_pham WHERE id=?)
        )
        WHERE id = (SELECT san_pham_id FROM bien_the_san_pham WHERE id=?)
    ");
    $stmt2->execute([$id, $id]);
    // KHÔNG ĐƯỢC OUTPUT TRƯỚC HEADER
    header("Location: index.php?page=products_list&variant_updated=1");
    exit;
}
/* ==========================
    LOAD SẢN PHẨM
========================== */
$keyword = $_GET['keyword'] ?? '';

$sql = "SELECT sp.*, dm.ten AS ten_danh_muc,
        (SELECT SUM(so_luong_ton) 
         FROM bien_the_san_pham 
         WHERE san_pham_id = sp.id) AS tong_bien_the
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
            <thead class="table-light"style="background-color: #0676e5ff; color: white;">
                <tr>
                    <th width="60">ID</th>
                    <th>Hình ảnh</th>
                    <th>Tên sản phẩm</th>
                    <th>Giá</th> <!-- vẫn giữ cột, nhưng giá sẽ để dấu — -->
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

    <!-- HÀNG SẢN PHẨM CHÍNH -->
    <tr>
        <td class="text-center">
    <button class="toggle-arrow" onclick="toggleVariants(<?= $p['id'] ?>)">
        ▶
    </button>
    <?= $p['id'] ?>
</td>

        <td><img src="/HiShop/assets/img/products/<?= $p['hinh_anh'] ?>" width="60"></td>
        <td><?= htmlspecialchars($p['ten']) ?></td>
        <!-- <td><?= number_format($p['gia']) ?>₫</td> -->
        <td>—</td> <!-- Hiển thị dấu gạch cho đẹp -->
        <td><?= ($p['tong_bien_the'] !== null ? $p['tong_bien_the'] : 0) ?></td>
        <td><?= $p['ten_danh_muc'] ?? "Không có" ?></td>

<td class="text-end">

    <!-- Nút SỬA -->
    <a href="#"
       class="action-btn edit-btn"
       onclick="openEditModal(
                <?= $p['id'] ?>,
                '<?= htmlspecialchars($p['ten'], ENT_QUOTES) ?>',
                '<?= $p['gia'] ?>',
                '<?= $p['so_luong'] ?>',
                '<?= htmlspecialchars($p['hinh_anh'], ENT_QUOTES) ?>',
                '<?= htmlspecialchars($p['mo_ta'], ENT_QUOTES) ?>',
                '<?= $p['danh_muc_id'] ?>'
            )">
        <i class="fa-solid fa-pen"></i> Sửa
    </a>

    <!-- Nút ẨN / HIỆN -->
    <a href="index.php?page=products_list&toggle=<?= $p['id'] ?>"
       class="action-btn status-btn <?= ($p['trang_thai'] == 1 ? 'show' : 'hide') ?>">
        <?php if ($p['trang_thai'] == 1): ?>
            <i class="fa-solid fa-eye"></i> Hiện
        <?php else: ?>
            <i class="fa-solid fa-eye-slash"></i> Ẩn
        <?php endif; ?>
    </a>

</td>


    <!-- LẤY BIẾN THỂ -->
    <?php  
        $variants = $pdo->prepare("SELECT * FROM bien_the_san_pham WHERE san_pham_id = ?");
        $variants->execute([$p['id']]);
        $variants = $variants->fetchAll();
    ?>

    <!-- HIỂN THỊ BIẾN THỂ -->
    <?php foreach ($variants as $v): ?>
        <tr class="variant-row variant-of-<?= $p['id'] ?>" style="display:none;">
            <td></td>
            <td><img src="/HiShop/assets/img/products/<?= $v['hinh_anh'] ?>" width="45"></td>
            <td>
                <b>Màu:</b> <?= $v['mau_sac'] ?> <br>
                <b>SSD:</b> <?= $v['dung_luong_ssd'] ?>
            </td>
            <td><?= number_format($v['gia']) ?>₫</td>
            <td><?= $v['so_luong_ton'] ?></td>
            <td colspan="2" class="text-end">
               <button 
    class="btn btn-sm btn-variant"
    onclick="openVariantModal(
        '<?= $v['id'] ?>',
        '<?= addslashes($v['mau_sac']) ?>',
        '<?= addslashes($v['dung_luong_ssd']) ?>',
        '<?= $v['gia'] ?>',
        '<?= $v['so_luong_ton'] ?>',
        '<?= addslashes($v['hinh_anh']) ?>'
    )"
>
    Sửa biến thể
</button>

            </td>
        </tr>
    <?php endforeach; ?>

<?php endforeach; ?>

            </tbody>

        </table>
    </div>
</div>

<div id="modalVariant" class="modal-overlay" style="display:none;">
    <div class="modal-box">
        <h3>Sửa biến thể</h3>

        <form method="post">
            <input type="hidden" name="variant_id" id="variant_id">

            <label>Màu sắc:</label>
            <input type="text" name="variant_mau" id="variant_mau" required>

            <label>Dung lượng SSD:</label>
            <input type="text" name="variant_ssd" id="variant_ssd" required>

            <label>Giá:</label>
            <input type="number" name="variant_gia" id="variant_gia" required>

            <label>Số lượng tồn:</label>
            <input type="number" name="variant_ton" id="variant_ton" required>

            <label>Hình ảnh:</label>
            <input type="text" name="variant_hinh" id="variant_hinh" required>

            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeVariantModal()">Hủy</button>
                <button type="submit" class="btn-save">Lưu</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL THÊM -->
<div id="modalAdd" class="modal-overlay" style="display:none;">
    <div class="modal-box">
        <h3>Thêm sản phẩm</h3>

        <form method="post" enctype="multipart/form-data">

            <!-- THÔNG TIN SẢN PHẨM -->
            <label>Tên sản phẩm:</label>
            <input type="text" name="ten" required>

            <label>Hình ảnh chính:</label>
            <input type="file" name="hinh_anh" accept="image/*" required>

            <label>Mô tả:</label>
            <input type="text" name="mo_ta">

            <label>Danh mục:</label>
            <select name="danh_muc" required>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= $c['ten'] ?></option>
                <?php endforeach; ?>
            </select>

            <hr>
            <br>
            <!-- BIẾN THỂ -->
            <h3>Biến thể sản phẩm</h3>

            <div id="variantList"></div>

            <button type="button" class="btn-add" onclick="addVariant()">+ Thêm biến thể</button>

            <br><br>

            <!-- AUTO SUM -->
            <label>Tổng số lượng:</label>
            <input type="number" id="tong_sl" readonly style="background:#eee">

            <label>Giá hiển thị (giá thấp nhất trong biến thể):</label>
            <input type="number" id="gia_min" readonly style="background:#eee">

            <input type="hidden" name="variants_json" id="variants_json">

            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeModal()">Hủy</button>
                <button type="submit" class="btn-save">Lưu sản phẩm</button>
            </div>
        </form>
    </div>
</div>
<script>
let variantIndex = 0;

function addVariant() {
    let id = variantIndex++;

    let html = `
    <div class="variant-item" id="v_${id}" data-vid="${id}"
        style="border:1px solid #ccc;padding:10px;margin-bottom:10px;">
        
        <label>Màu:</label>
        <input type="text" class="v_mau" required>

        <label>SSD:</label>
        <input type="text" class="v_ssd" required>

        <label>Giá:</label>
        <input type="number" class="v_gia" required>

        <label>Số lượng:</label>
        <input type="number" class="v_ton" required>

        <label>Hình ảnh:</label>
        <input type="file" class="v_img" name="variant_imgs[]" accept="image/*" required>

        <button type="button" onclick="removeVariant(${id})" class="btn-delete">
            Xóa biến thể
        </button>
    </div>`;
    
    document.getElementById("variantList").insertAdjacentHTML("beforeend", html);
    attachListeners();
}

function removeVariant(id) {
    document.getElementById("v_" + id).remove();
    calculateTotals();
}

function attachListeners() {
    document.querySelectorAll(".v_gia, .v_ton").forEach(el => {
        el.oninput = calculateTotals;
    });
}

function calculateTotals() {
    let totalQty = 0;
    let prices = [];

    document.querySelectorAll(".variant-item").forEach(v => {
        let gia = parseInt(v.querySelector(".v_gia").value) || 0;
        let sl  = parseInt(v.querySelector(".v_ton").value) || 0;

        if (gia > 0) prices.push(gia);
        totalQty += sl;
    });

    document.getElementById("tong_sl").value = totalQty;
    if (prices.length > 0) {
        document.getElementById("gia_min").value = Math.min(...prices);
    }

    saveVariantsJSON();
}

function saveVariantsJSON() {
    let arr = [];
    let index = 0;

    document.querySelectorAll(".variant-item").forEach(v => {
        arr.push({
            mau: v.querySelector(".v_mau").value,
            ssd: v.querySelector(".v_ssd").value,
            gia: v.querySelector(".v_gia").value,
            ton: v.querySelector(".v_ton").value,
            img_index: index // ẢNH THUỘC BIẾN THỂ NÀY
        });
        index++;
    });

    document.getElementById("variants_json").value = JSON.stringify(arr);
}

</script>

<!-- MODAL SỬA -->
<div id="modalEdit" class="modal-overlay" style="display:none;">
    <div class="modal-box">
        <h3>Sửa sản phẩm</h3>

        <form method="post">
            <input type="hidden" name="edit_id" id="edit_id">

            <label>Tên sản phẩm:</label>
            <input type="text" name="edit_ten" id="edit_ten" required>

            <label>Hình ảnh mới (nếu muốn đổi):</label>
            <input type="file" name="edit_hinh_anh" accept="image/*">
            <input type="hidden" name="old_hinh_anh" id="edit_hinh_anh">


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
    document.getElementById('edit_hinh_anh').value = hinh_anh;
    document.getElementById('edit_mo_ta').value = mo_ta;
    document.getElementById('edit_danh_muc').value = danh_muc;

    document.getElementById('modalEdit').style.display = 'flex';
}

function closeEditModal(){
    document.getElementById('modalEdit').style.display = 'none';
}
</script>
<script>
function openVariantModal(id, mau, ssd, gia, ton, hinh) {
    document.getElementById('variant_id').value = id;
    document.getElementById('variant_mau').value = mau;
    document.getElementById('variant_ssd').value = ssd;
    document.getElementById('variant_gia').value = gia;
    document.getElementById('variant_ton').value = ton;
    document.getElementById('variant_hinh').value = hinh;

    document.getElementById('modalVariant').style.display = 'flex';
}

function closeVariantModal() {
    document.getElementById('modalVariant').style.display = 'none';
}
</script>
<script>
function toggleVariants(id) {
    let rows = document.querySelectorAll(".variant-of-" + id);
    let arrow = document.querySelector(
        ".toggle-arrow[onclick='toggleVariants(" + id + ")']"
    );

    let isHidden = rows[0].style.display === "none";

    rows.forEach(r => r.style.display = isHidden ? "table-row" : "none");
    arrow.textContent = isHidden ? "▼" : "▶";
}
</script>

<link rel="stylesheet" href="/HiShop/assets/css/admin/product_list.css">