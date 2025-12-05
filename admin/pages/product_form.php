<?php
require_once '../src/config.php';
require_once '../src/functions.php';

// Lấy danh mục
$categories = $pdo->query("SELECT * FROM danh_muc ORDER BY ten ASC")->fetchAll();

// Lấy các thông số kỹ thuật
$man_hinhs = $pdo->query("SELECT DISTINCT man_hinh AS ten FROM thong_so WHERE man_hinh IS NOT NULL ORDER BY man_hinh ASC")->fetchAll();
$o_cungs   = $pdo->query("SELECT DISTINCT o_cung AS ten FROM thong_so WHERE o_cung IS NOT NULL ORDER BY o_cung ASC")->fetchAll();
$cpus      = $pdo->query("SELECT DISTINCT cpu AS ten FROM thong_so WHERE cpu IS NOT NULL ORDER BY cpu ASC")->fetchAll();
$gpus      = $pdo->query("SELECT DISTINCT gpu AS ten FROM thong_so WHERE gpu IS NOT NULL ORDER BY gpu ASC")->fetchAll();
$rams      = $pdo->query("SELECT DISTINCT ram AS ten FROM thong_so WHERE ram IS NOT NULL ORDER BY ram ASC")->fetchAll();

// Nếu có id, load sản phẩm và biến thể
$product = null;
$variants = [];
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);

    $stmt = $pdo->prepare("SELECT * FROM san_pham WHERE id=?");
    $stmt->execute([$id]);
    $product = $stmt->fetch();

    $stmt2 = $pdo->prepare("SELECT * FROM bien_the_san_pham WHERE san_pham_id=?");
    $stmt2->execute([$id]);
    $variants = $stmt2->fetchAll();
}
?>
<?php
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $ten = $_POST['ten'];
    $mo_ta = $_POST['mo_ta'];
    $danh_muc = $_POST['danh_muc'];
    $variants = json_decode($_POST['variants_json'], true);

    if (!empty($_POST['product_id'])) {
        // UPDATE sản phẩm
        $id = $_POST['product_id'];
        $stmt = $pdo->prepare("UPDATE san_pham SET ten=?, mo_ta=?, danh_muc_id=? WHERE id=?");
        $stmt->execute([$ten, $mo_ta, $danh_muc, $id]);

        // Cập nhật biến thể tương tự
    } else {
        // Thêm mới
        // Giữ nguyên logic INSERT cũ
    }
}
?>

<link rel="stylesheet" href="/HiShop/assets/css/admin/product_form.css">

<div class="admin-page">
    <div class="form-container">
        <h1 class="title">THÊM SẢN PHẨM MỚI</h1>

        <form action="index.php?page=products_list" method="post" enctype="multipart/form-data">

            <!-- THÔNG TIN SẢN PHẨM -->
            <div class="form-block">
                <h3>Thông tin sản phẩm</h3>
                <div class="form-group">
                    <label for="ten">Tên sản phẩm:</label>
<input type="text" id="ten" name="ten" class="form-control"
       value="<?= htmlspecialchars($product['ten'] ?? '') ?>" required>

<textarea id="mo_ta" name="mo_ta" class="form-control" rows="3"><?= htmlspecialchars($product['mo_ta'] ?? '') ?></textarea>

<select id="danh_muc" name="danh_muc" class="form-control" required>
    <?php foreach ($categories as $c): ?>
        <option value="<?= $c['id'] ?>"
            <?= isset($product['danh_muc_id']) && $product['danh_muc_id']==$c['id'] ? 'selected' : '' ?>>
            <?= $c['ten'] ?>
        </option>
    <?php endforeach; ?>
</select>

                </div>
                <div class="form-group">
                    <label for="mo_ta">Mô tả:</label>
                    <textarea id="mo_ta" name="mo_ta" class="form-control" rows="3"></textarea>
                </div>
            </div>

            <!-- THÔNG SỐ KỸ THUẬT -->
            <div class="form-block">
                <h3>Thông số kỹ thuật</h3>

                <div class="form-group">
                    <label for="man_hinh">Màn hình:</label>
                    <select id="man_hinh" name="man_hinh" class="form-control">
                        <option value="">-- Chọn màn hình --</option>
                        <?php foreach ($man_hinhs as $m): ?>
                            <option value="<?= $m['ten'] ?>"><?= $m['ten'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="o_cung">Ổ cứng:</label>
                    <select id="o_cung" name="o_cung" class="form-control">
                        <option value="">-- Chọn ổ cứng --</option>
                        <?php foreach ($o_cungs as $o): ?>
                            <option value="<?= $o['ten'] ?>"><?= $o['ten'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="cpu">CPU:</label>
                    <select id="cpu" name="cpu" class="form-control">
                        <option value="">-- Chọn CPU --</option>
                        <?php foreach ($cpus as $c): ?>
                            <option value="<?= $c['ten'] ?>"><?= $c['ten'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="gpu">GPU:</label>
                    <select id="gpu" name="gpu" class="form-control">
                        <option value="">-- Chọn GPU --</option>
                        <?php foreach ($gpus as $g): ?>
                            <option value="<?= $g['ten'] ?>"><?= $g['ten'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="ram">RAM:</label>
                    <select id="ram" name="ram" class="form-control">
                        <option value="">-- Chọn RAM --</option>
                        <?php foreach ($rams as $r): ?>
                            <option value="<?= $r['ten'] ?>"><?= $r['ten'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- BIẾN THỂ -->
            <div class="form-block">
                <h3>Biến thể sản phẩm</h3>
                <div id="variantList">
<?php foreach ($variants as $v): ?>
<div class="variant-item">
    <label>Màu:</label>
    <input type="text" class="v_mau form-control" value="<?= $v['mau_sac'] ?>" required>

    <label>SSD:</label>
    <input type="text" class="v_ssd form-control" value="<?= $v['dung_luong_ssd'] ?>" required>

    <label>Giá:</label>
    <input type="number" class="v_gia form-control" value="<?= $v['gia'] ?>" required>

    <label>Số lượng:</label>
    <input type="number" class="v_ton form-control" value="<?= $v['so_luong_ton'] ?>" required>

    <label>Hình ảnh:</label>
    <input type="file" class="v_img form-control" name="variant_imgs[]" accept="image/*">
    <input type="hidden" class="v_img_old" value="<?= $v['hinh_anh'] ?>">
</div>
<?php endforeach; ?>
</div>

                <button type="button" class="btn btn-add" onclick="addVariant()">+ Thêm biến thể</button>
            </div>

            <!-- TỔNG SỐ LƯỢNG & GIÁ -->
            <div class="form-block small-block">
                <div class="form-group">
                    <label>Tổng số lượng:</label>
                    <input type="number" id="tong_sl" class="form-control" readonly>
                </div>
                <div class="form-group">
                    <label>Giá hiển thị (thấp nhất):</label>
                    <input type="number" id="gia_min" class="form-control" readonly>
                </div>
            </div>

            <input type="hidden" name="variants_json" id="variants_json">

            <div class="modal-actions">
                <a href="index.php?page=products_list" class="btn btn-secondary">Hủy</a>
                <button type="submit" class="btn btn-save">Lưu sản phẩm</button>
            </div>
        </form>
    </div>
</div>

<script>
let variantIndex = 0;

function addVariant() {
    let id = variantIndex++;
    let html = `
    <div class="variant-item" id="v_${id}">
        <button type="button" onclick="removeVariant(${id})" class="btn-delete">Xóa</button>
        <div class="form-group">
            <label>Màu:</label>
            <input type="text" class="v_mau form-control" required>
        </div>
        <div class="form-group">
            <label>SSD:</label>
            <input type="text" class="v_ssd form-control" required>
        </div>
        <div class="form-group">
            <label>Giá:</label>
            <input type="number" class="v_gia form-control" required min="0">
        </div>
        <div class="form-group">
            <label>Số lượng:</label>
            <input type="number" class="v_ton form-control" required min="0">
        </div>
        <div class="form-group">
            <label>Hình ảnh:</label>
            <input type="file" class="v_img form-control" name="variant_imgs[]" accept="image/*" required>
        </div>
    </div>`;
    document.getElementById("variantList").insertAdjacentHTML("beforeend", html);
    attachListeners();
}

// Thêm 1 biến thể mặc định
addVariant();

function removeVariant(id) {
    document.getElementById("v_" + id).remove();
    calculateTotals();
}

function attachListeners() {
    document.querySelectorAll(".v_gia, .v_ton").forEach(el => el.oninput = calculateTotals);
    document.querySelector('form').onsubmit = saveVariantsJSON; 
}

function calculateTotals() {
    let totalQty = 0;
    let prices = [];
    document.querySelectorAll(".variant-item").forEach(v => {
        let gia = parseInt(v.querySelector(".v_gia").value) || 0;
        let sl = parseInt(v.querySelector(".v_ton").value) || 0;
        if(gia > 0) prices.push(gia);
        totalQty += sl;
    });
    document.getElementById("tong_sl").value = totalQty;
    document.getElementById("gia_min").value = prices.length ? Math.min(...prices) : 0;
}

function saveVariantsJSON() {
    let arr = [];
    let index = 0;
    document.querySelectorAll(".variant-item").forEach(v=>{
        arr.push({
            mau: v.querySelector(".v_mau").value,
            ssd: v.querySelector(".v_ssd").value,
            gia: v.querySelector(".v_gia").value,
            ton: v.querySelector(".v_ton").value,
            img_index: index
        });
        index++;
    });
    document.getElementById("variants_json").value = JSON.stringify(arr);
    return true;
}
</script>
