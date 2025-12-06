<?php
// FILE: admin/pages/product_form.php
require_once '../src/config.php';
require_once '../src/functions.php';

// 1. DATA LOADING CHO DROPDOWN GỢI Ý
$categories = $pdo->query("SELECT * FROM danh_muc ORDER BY ten ASC")->fetchAll();
// Lấy danh sách duy nhất để gợi ý (Autocomplete)
$man_hinhs = $pdo->query("SELECT DISTINCT man_hinh AS ten FROM thong_so WHERE man_hinh IS NOT NULL AND man_hinh != '' ORDER BY man_hinh ASC")->fetchAll();
$o_cungs   = $pdo->query("SELECT DISTINCT o_cung AS ten FROM thong_so WHERE o_cung IS NOT NULL AND o_cung != '' ORDER BY o_cung ASC")->fetchAll();
$cpus      = $pdo->query("SELECT DISTINCT cpu AS ten FROM thong_so WHERE cpu IS NOT NULL AND cpu != '' ORDER BY cpu ASC")->fetchAll();
$gpus      = $pdo->query("SELECT DISTINCT gpu AS ten FROM thong_so WHERE gpu IS NOT NULL AND gpu != '' ORDER BY gpu ASC")->fetchAll();
$rams      = $pdo->query("SELECT DISTINCT ram AS ten FROM thong_so WHERE ram IS NOT NULL AND ram != '' ORDER BY ram ASC")->fetchAll();

$product = null;
$variants = [];
$current_specs = []; // Mảng chứa thông số hiện tại của SP
$isEdit = false;

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    // Lấy thông tin cơ bản
    $stmt = $pdo->prepare("SELECT * FROM san_pham WHERE id=?");
    $stmt->execute([$id]);
    $product = $stmt->fetch();
    
    if($product) {
        $isEdit = true;
        // Lấy biến thể
        $stmt2 = $pdo->prepare("SELECT * FROM bien_the_san_pham WHERE san_pham_id=?");
        $stmt2->execute([$id]);
        $variants = $stmt2->fetchAll();

        // [MỚI] Lấy thông số kỹ thuật hiện tại của sản phẩm
        // Join bảng san_pham_thong_so với thong_so
        $stmt3 = $pdo->prepare("
            SELECT ts.* FROM san_pham_thong_so spts 
            JOIN thong_so ts ON spts.thong_so_id = ts.id 
            WHERE spts.san_pham_id = ? 
            LIMIT 1
        ");
        $stmt3->execute([$id]);
        $current_specs = $stmt3->fetch(PDO::FETCH_ASSOC);
    }
}
?>

<link rel="stylesheet" href="../assets/css/admin/product_form.css">

<form action="index.php?page=products_list" method="post" enctype="multipart/form-data" id="productForm">
    <div class="admin-page">
        <div class="page-header">
            <h1 class="page-title"><?= $isEdit ? 'Cập Nhật Sản Phẩm' : 'Thêm Sản Phẩm Mới' ?></h1>
            <div class="btn-group">
                <a href="index.php?page=products_list" class="btn btn-secondary">Hủy bỏ</a>
                <button type="submit" class="btn btn-save">
                    <i class="fa-solid fa-floppy-disk"></i> Lưu Sản Phẩm
                </button>
            </div>
        </div>

        <div class="form-grid">
            
            <div class="col-left">
                <div class="card">
                    <div class="card-header">Thông tin chung</div>
                    <div class="form-group">
                        <label class="form-label">Tên sản phẩm <span class="text-danger">*</span></label>
                        <input type="text" name="ten" class="form-control" value="<?= htmlspecialchars($product['ten'] ?? '') ?>" required placeholder="VD: MacBook Air M2 2023">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Mô tả chi tiết</label>
                        <textarea name="mo_ta" class="form-control"><?= htmlspecialchars($product['mo_ta'] ?? '') ?></textarea>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">Biến thể sản phẩm</div>
                    <div id="variantList" class="variant-list"></div>
                    <button type="button" class="btn-add-variant" onclick="addVariant()">
                        <i class="fa-solid fa-plus"></i> Thêm biến thể mới
                    </button>
                </div>
            </div>

            <div class="col-right">
                <div class="card">
                    <div class="card-header">Danh mục</div>
                    <div class="form-group">
                        <label class="form-label">Các danh mục</label>
                        <select name="danh_muc" class="form-control" required>
                            <?php foreach ($categories as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= isset($product['danh_muc_id']) && $product['danh_muc_id']==$c['id'] ? 'selected' : '' ?>>
                                    <?= $c['ten'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">Ảnh đại diện</div>
                    <div class="main-img-preview" onclick="document.getElementById('main_img_input').click()">
                        <?php $mainImg = !empty($product['hinh_anh']) ? $product['hinh_anh'] : ''; ?>
                        <img id="main_preview" src="<?= $mainImg ? '/HiShop/assets/img/products/'.$mainImg : '/HiShop/assets/img/upload-placeholder.png' ?>">
                        <input type="file" name="hinh_anh" id="main_img_input" style="display:none;" onchange="previewMainImg(this)">
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">Thông số kỹ thuật</div>
                    
                    <div class="form-group">
                        <label class="form-label">Màn hình</label>
                        <input type="text" name="man_hinh" list="list_man_hinh" class="form-control" 
                               value="<?= htmlspecialchars($current_specs['man_hinh'] ?? '') ?>" placeholder="Nhập hoặc chọn...">
                        <datalist id="list_man_hinh">
                            <?php foreach ($man_hinhs as $item): ?><option value="<?= $item['ten'] ?>"><?php endforeach; ?>
                        </datalist>
                    </div>

                    <div class="form-group">
                        <label class="form-label">CPU</label>
                        <input type="text" name="cpu" list="list_cpu" class="form-control" 
                               value="<?= htmlspecialchars($current_specs['cpu'] ?? '') ?>" placeholder="Nhập hoặc chọn...">
                        <datalist id="list_cpu">
                            <?php foreach ($cpus as $item): ?><option value="<?= $item['ten'] ?>"><?php endforeach; ?>
                        </datalist>
                    </div>

                    <div class="form-group">
                        <label class="form-label">RAM</label>
                        <input type="text" name="ram" list="list_ram" class="form-control" 
                               value="<?= htmlspecialchars($current_specs['ram'] ?? '') ?>" placeholder="Nhập hoặc chọn...">
                        <datalist id="list_ram">
                            <?php foreach ($rams as $item): ?><option value="<?= $item['ten'] ?>"><?php endforeach; ?>
                        </datalist>
                    </div>

                    <div class="form-group">
                        <label class="form-label">GPU (Card đồ họa)</label>
                        <input type="text" name="gpu" list="list_gpu" class="form-control" 
                               value="<?= htmlspecialchars($current_specs['gpu'] ?? '') ?>" placeholder="Nhập hoặc chọn...">
                        <datalist id="list_gpu">
                            <?php foreach ($gpus as $item): ?><option value="<?= $item['ten'] ?>"><?php endforeach; ?>
                        </datalist>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Ổ cứng</label>
                        <input type="text" name="o_cung" list="list_o_cung" class="form-control" 
                               value="<?= htmlspecialchars($current_specs['o_cung'] ?? '') ?>" placeholder="Nhập hoặc chọn...">
                        <datalist id="list_o_cung">
                            <?php foreach ($o_cungs as $item): ?><option value="<?= $item['ten'] ?>"><?php endforeach; ?>
                        </datalist>
                    </div>

                </div>
            </div>
        </div>

        <input type="hidden" name="action" value="<?= $isEdit ? 'update_product' : 'add_product' ?>">
        <input type="hidden" name="variants_json" id="variants_json">
        <?php if($isEdit): ?>
            <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
        <?php endif; ?>

    </div>
</form>

<script>
// (Giữ nguyên Javascript cũ của file trước)
const existingVariants = <?= json_encode($variants) ?>;
let variantIndex = 0;

document.addEventListener('DOMContentLoaded', () => {
    if (existingVariants.length > 0) {
        existingVariants.forEach(v => addVariant(v));
    } else {
        addVariant();
    }
});

function addVariant(data = null) {
    const id = variantIndex++;
    const container = document.getElementById('variantList');
    
    const mau = data ? data.mau_sac : '';
    const ssd = data ? data.dung_luong_ssd : '';
    const gia = data ? data.gia : '';
    const ton = data ? data.so_luong_ton : '';
    const imgName = data ? data.hinh_anh : ''; 
    const imgUrl = imgName ? '/HiShop/assets/img/products/' + imgName : '';

    const html = `
    <div class="variant-item" id="v_${id}">
        <button type="button" class="btn-remove" onclick="removeVariant(${id})"><i class="fa-solid fa-xmark"></i></button>
        <div class="v-col-img">
            <div class="img-upload-box" onclick="document.getElementById('file_${id}').click()">
                <img id="preview_${id}" src="${imgUrl}" style="display: ${imgUrl ? 'block' : 'none'}">
                <i class="fa-solid fa-image icon-upload" style="display: ${imgUrl ? 'none' : 'block'}"></i>
                <input type="file" id="file_${id}" name="variant_imgs[]" accept="image/*" onchange="previewVariantImg(this, ${id})">
                <input type="hidden" class="v_img_old" value="${imgName}">
            </div>
            <div style="font-size:11px; color:#666; margin-top:4px;">Ảnh</div>
        </div>
        <div class="v-row">
            <div class="v-col"><label class="form-label">Màu sắc</label><input type="text" class="form-control v_mau" value="${mau}" required></div>
            <div class="v-col"><label class="form-label">SSD</label><input type="text" class="form-control v_ssd" value="${ssd}" required></div>
            <div class="v-col"><label class="form-label">Giá</label><input type="number" class="form-control v_gia" value="${gia}" required></div>
            <div class="v-col"><label class="form-label">Kho</label><input type="number" class="form-control v_ton" value="${ton}" required></div>
        </div>
    </div>`;
    
    container.insertAdjacentHTML('beforeend', html);
}

function removeVariant(id) {
    const item = document.getElementById('v_' + id);
    if(item) item.remove();
}

function previewMainImg(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) { document.getElementById('main_preview').src = e.target.result; }
        reader.readAsDataURL(input.files[0]);
    }
}

function previewVariantImg(input, id) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const img = document.getElementById('preview_' + id);
            img.src = e.target.result;
            img.style.display = 'block';
            input.parentElement.querySelector('.icon-upload').style.display = 'none';
        }
        reader.readAsDataURL(input.files[0]);
    }
}

document.getElementById('productForm').onsubmit = function() {
    let arr = [];
    let domVariants = document.querySelectorAll('.variant-item');
    domVariants.forEach((v, index) => {
        arr.push({
            mau: v.querySelector(".v_mau").value,
            ssd: v.querySelector(".v_ssd").value,
            gia: v.querySelector(".v_gia").value,
            ton: v.querySelector(".v_ton").value,
            old_img: v.querySelector(".v_img_old").value, 
            img_index: index
        });
    });
    document.getElementById("variants_json").value = JSON.stringify(arr);
    return true;
};
</script>