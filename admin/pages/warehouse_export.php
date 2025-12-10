<?php
// FILE: admin/pages/warehouse_export.php

/* ===========================================================
   PHẦN 1: XỬ LÝ LOGIC BACKEND
   =========================================================== */
$msg = '';
$msg_type = '';

// 1. Lấy danh sách kho đang hoạt động
$stmt = $pdo->query("SELECT * FROM kho_hang WHERE trang_thai = 1 ORDER BY id DESC");
$ds_kho = $stmt->fetchAll();

// 2. Lấy danh sách sản phẩm (Kèm tồn kho tổng để hiển thị gợi ý)
$sqlProducts = "
    SELECT bt.id as bien_the_id, sp.ten, bt.mau_sac, bt.dung_luong_ssd, bt.so_luong_ton, sp.hinh_anh
    FROM bien_the_san_pham bt
    JOIN san_pham sp ON bt.san_pham_id = sp.id
    ORDER BY sp.ten ASC
";
$stmtProd = $pdo->query($sqlProducts);
$ds_san_pham = $stmtProd->fetchAll();

// 3. XỬ LÝ FORM SUBMIT
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kho_id = isset($_POST['kho_id']) ? (int)$_POST['kho_id'] : 0;
    $ghi_chu = isset($_POST['ghi_chu']) ? trim($_POST['ghi_chu']) : '';
    // Lấy ID người dùng an toàn
    $nguoi_xuat_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : (isset($_SESSION['user']['id']) ? $_SESSION['user']['id'] : 10); 

    $product_ids = isset($_POST['product_variant_id']) ? $_POST['product_variant_id'] : [];
    $quantities = isset($_POST['quantity']) ? $_POST['quantity'] : [];

    $errors = [];

    // Check kho
    $stmtCheckKho = $pdo->prepare("SELECT ten_kho FROM kho_hang WHERE id = ? AND trang_thai = 1");
    $stmtCheckKho->execute([$kho_id]);
    $ten_kho = $stmtCheckKho->fetchColumn();
    
    if (!$ten_kho) $errors[] = "Kho xuất không hợp lệ.";
    if (empty($product_ids)) $errors[] = "Chưa chọn sản phẩm nào.";

    // Check chi tiết từng dòng
    if (empty($errors)) {
        $temp_check = [];
        foreach ($product_ids as $key => $pid) {
            if (empty($pid)) continue;

            $qty = (int)$quantities[$key];

            // 1. Check trùng sản phẩm trong phiếu
            if (in_array($pid, $temp_check)) {
                $errors[] = "Dòng " . ($key + 1) . ": Sản phẩm bị trùng.";
            }
            $temp_check[] = $pid;

            // 2. Check số lượng âm
            if ($qty <= 0) {
                $errors[] = "Dòng " . ($key + 1) . ": Số lượng phải lớn hơn 0.";
                continue;
            }

            // 3. [QUAN TRỌNG] Check tồn kho thực tế TẠI KHO ĐÓ
            // Phải kiểm tra bảng chi_tiet_kho_hang, chứ không phải bảng tổng
            $stmtStock = $pdo->prepare("SELECT so_luong_ton FROM chi_tiet_kho_hang WHERE kho_hang_id = ? AND bien_the_id = ?");
            $stmtStock->execute([$kho_id, $pid]);
            $current_stock = $stmtStock->fetchColumn();

            // Nếu không tìm thấy dòng nào hoặc số lượng không đủ
            if ($current_stock === false || $current_stock < $qty) {
                $stock_show = ($current_stock === false) ? 0 : $current_stock;
                $errors[] = "Dòng " . ($key + 1) . ": <b>Không đủ hàng!</b> Kho <b>$ten_kho</b> chỉ còn <b>$stock_show</b> sản phẩm (Yêu cầu xuất: $qty).";
            }
        }
    }

    if (!empty($errors)) {
        $msg = implode("<br>", $errors);
        $msg_type = "danger";
    } else {
        try {
            $pdo->beginTransaction();

            $ma_phieu = 'PX' . date('YmdHis') . rand(10, 99);
            
            // Insert Header
            $sqlPhieu = "INSERT INTO phieu_kho (ma_phieu, loai_phieu, kho_hang_id, nguoi_dung_id, ghi_chu, trang_thai, ngay_tao) 
                         VALUES (?, 'xuat', ?, ?, ?, 1, NOW())";
            $stmtPhieu = $pdo->prepare($sqlPhieu);
            $stmtPhieu->execute([$ma_phieu, $kho_id, $nguoi_xuat_id, $ghi_chu]);
            $phieu_id = $pdo->lastInsertId();

            // Insert Details & Update Stock
            for ($i = 0; $i < count($product_ids); $i++) {
                $bt_id = $product_ids[$i];
                $so_luong = $quantities[$i];

                if(empty($bt_id)) continue;

                $stmtGetParent = $pdo->prepare("SELECT san_pham_id FROM bien_the_san_pham WHERE id = ?");
                $stmtGetParent->execute([$bt_id]);
                $sp_id = $stmtGetParent->fetchColumn();

                // Chi tiết phiếu (Giá xuất để 0 hoặc có thể phát triển thêm giá vốn)
                $sqlChiTiet = "INSERT INTO chi_tiet_phieu_kho (phieu_kho_id, san_pham_id, bien_the_id, so_luong, don_gia) VALUES (?, ?, ?, ?, 0)";
                $pdo->prepare($sqlChiTiet)->execute([$phieu_id, $sp_id, $bt_id, $so_luong]);

                // Trừ kho chi tiết (chi_tiet_kho_hang)
                $sqlKhoChiTiet = "UPDATE chi_tiet_kho_hang SET so_luong_ton = so_luong_ton - ? WHERE kho_hang_id = ? AND bien_the_id = ?";
                $pdo->prepare($sqlKhoChiTiet)->execute([$so_luong, $kho_id, $bt_id]);

                // Trừ kho tổng (bien_the_san_pham)
                $pdo->prepare("UPDATE bien_the_san_pham SET so_luong_ton = so_luong_ton - ? WHERE id = ?")->execute([$so_luong, $bt_id]);
            }

            $pdo->commit();
            echo "<script>alert('Xuất kho thành công! Mã phiếu: $ma_phieu'); window.location.href='index.php?page=warehouse_history';</script>";
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $msg = "Lỗi hệ thống: " . $e->getMessage();
            $msg_type = "danger";
        }
    }
}
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="../assets/css/admin/warehouse_export.css">


<div class="export-container">
    
    <div class="page-header-title">
        <div style="display:flex; align-items:center; gap:12px;">
            <a href="index.php?page=warehouse_list" class="btn-back"><i class="fa-solid fa-arrow-left"></i></a>
            <span>Tạo Phiếu Xuất Kho</span>
        </div>
    </div>

    <?php if ($msg): ?>
        <div class="alert alert-<?php echo $msg_type; ?>">
            <i class="fa-solid fa-triangle-exclamation"></i> <?php echo $msg; ?>
        </div>
    <?php endif; ?>

    <form action="" method="POST" id="exportForm" onsubmit="return validateForm()">
        <div class="export-grid">
            
            <div class="card-info">
                <div class="card-header">
                    <i class="fa-solid fa-truck-ramp-box"></i> Thông tin xuất
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">Xuất từ Kho <span class="text-red">*</span></label>
                        <select name="kho_id" class="form-select" required>
                            <?php foreach ($ds_kho as $kho): ?>
                                <option value="<?php echo $kho['id']; ?>"><?php echo htmlspecialchars($kho['ten_kho']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Người lập phiếu</label>
                        <input type="text" class="form-input readonly" value="<?php echo isset($_SESSION['user']) ? htmlspecialchars($_SESSION['user']['ho_ten']) : 'Administrator'; ?>" readonly>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Lý do xuất</label>
                        <textarea name="ghi_chu" class="form-textarea" placeholder="Ví dụ: Xuất hủy hàng hỏng, điều chuyển..."></textarea>
                    </div>
                </div>
            </div>

            <div class="card-products">
                <div class="card-header flex-between">
                    <div><i class="fa-solid fa-clipboard-list"></i> Danh sách hàng xuất</div>
                    <button type="button" class="btn-add-row" onclick="addRow()">
                        <i class="fa-solid fa-plus"></i> Thêm dòng
                    </button>
                </div>
                
                <div class="table-wrapper">
                    <table class="table-export" id="productTable">
                        <thead>
                            <tr>
                                <th width="60%">Sản phẩm</th>
                                <th width="20%">Số lượng xuất</th>
                                <th width="10%"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="item-row">
                                <td>
                                    <select name="product_variant_id[]" class="form-select product-select select2-init" required onchange="updateStockHint(this)">
                                        <option value="" data-stock="0">-- Tìm kiếm & chọn sản phẩm --</option>
                                        <?php foreach ($ds_san_pham as $sp): ?>
                                            <option value="<?php echo $sp['bien_the_id']; ?>" data-stock="<?php echo $sp['so_luong_ton']; ?>">
                                                <?php echo htmlspecialchars($sp['ten'] . ' (' . $sp['mau_sac'] . ' - ' . $sp['dung_luong_ssd'] . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="stock-hint">Tổng tồn kho hệ thống: <span>0</span></div>
                                </td>
                                <td>
                                    <input type="number" name="quantity[]" class="form-input qty text-center" min="1" value="1" required
                                           oninput="enforcePositive(this)">
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn-remove" onclick="removeRow(this)"><i class="fa-solid fa-xmark"></i></button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="footer-actions">
                    <a href="index.php?page=warehouse_list" class="btn-cancel">Hủy bỏ</a>
                    <button type="submit" class="btn-submit">Xác nhận Xuất kho</button>
                </div>
            </div>
        </div>
    </form>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    // 1. Khởi tạo Select2
    $(document).ready(function() {
        initSelect2();
    });

    function initSelect2() {
        $('.select2-init').select2({
            width: '100%',
            placeholder: "-- Tìm kiếm sản phẩm --",
            allowClear: true
        });
    }

    // 2. Chặn số âm
    function enforcePositive(el) {
        if (el.value === '') return;
        if (parseInt(el.value) < 0) {
            el.value = 1;
        }
    }

    // 3. Hiển thị tồn kho (Tổng)
    function updateStockHint(select) {
        var stock = select.options[select.selectedIndex].getAttribute('data-stock') || 0;
        var hint = select.parentNode.querySelector('.stock-hint span');
        if(hint) hint.innerText = stock;
    }

    // 4. Thêm dòng mới
    function addRow() {
        var table = document.getElementById("productTable").getElementsByTagName('tbody')[0];
        
        // Chuỗi Options cho select
        var optionsHtml = `
            <option value="" data-stock="0">-- Tìm kiếm & chọn sản phẩm --</option>
            <?php foreach ($ds_san_pham as $sp): ?>
                <option value="<?php echo $sp['bien_the_id']; ?>" data-stock="<?php echo $sp['so_luong_ton']; ?>">
                    <?php echo htmlspecialchars($sp['ten'] . ' (' . $sp['mau_sac'] . ' - ' . $sp['dung_luong_ssd'] . ')'); ?>
                </option>
            <?php endforeach; ?>
        `;

        var newRowHtml = `
            <tr class="item-row">
                <td>
                    <select name="product_variant_id[]" class="form-select product-select select2-init" required onchange="updateStockHint(this)">
                        ${optionsHtml}
                    </select>
                    <div class="stock-hint">Tổng tồn kho hệ thống: <span>0</span></div>
                </td>
                <td>
                    <input type="number" name="quantity[]" class="form-input qty text-center" min="1" value="1" required oninput="enforcePositive(this)">
                </td>
                <td class="text-center">
                    <button type="button" class="btn-remove" onclick="removeRow(this)"><i class="fa-solid fa-xmark"></i></button>
                </td>
            </tr>
        `;
        
        $(table).append(newRowHtml);
        initSelect2();
    }

    function removeRow(btn) {
        var row = btn.closest('tr');
        var tbody = row.parentNode;
        if (tbody.querySelectorAll('tr').length > 1) row.remove();
        else alert("Phải có ít nhất 1 dòng sản phẩm!");
    }

    function validateForm() {
        if(document.querySelectorAll('.item-row').length === 0) {
            alert("Vui lòng chọn sản phẩm cần xuất.");
            return false;
        }
        return confirm('CẢNH BÁO: Bạn đang thực hiện XUẤT KHO. Số lượng tồn kho sẽ bị trừ ngay lập tức. Bạn có chắc chắn không?');
    }
</script>