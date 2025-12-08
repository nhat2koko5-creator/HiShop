<?php
// FILE: admin/pages/warehouse_export.php

/* ===========================================================
   PHẦN 1: XỬ LÝ LOGIC BACKEND (GIỮ NGUYÊN)
   =========================================================== */
$msg = '';
$msg_type = '';

// 1. Lấy danh sách kho đang hoạt động
$stmt = $pdo->query("SELECT * FROM kho_hang WHERE trang_thai = 1 ORDER BY id DESC");
$ds_kho = $stmt->fetchAll();

// 2. Lấy danh sách sản phẩm (Kèm tồn kho tổng)
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
    $nguoi_xuat_id = isset($_SESSION['user']) ? $_SESSION['user']['id'] : 1; 

    $product_ids = isset($_POST['product_variant_id']) ? $_POST['product_variant_id'] : [];
    $quantities = isset($_POST['quantity']) ? $_POST['quantity'] : [];

    $errors = [];

    // Check kho
    $stmtCheckKho = $pdo->prepare("SELECT id FROM kho_hang WHERE id = ? AND trang_thai = 1");
    $stmtCheckKho->execute([$kho_id]);
    if ($stmtCheckKho->rowCount() == 0) $errors[] = "Kho xuất không hợp lệ.";

    if (empty($product_ids)) $errors[] = "Chưa chọn sản phẩm nào.";

    // Check tồn kho
    if (empty($errors)) {
        $temp_check = [];
        for ($i = 0; $i < count($product_ids); $i++) {
            $pid = (int)$product_ids[$i];
            $qty = (int)$quantities[$i];

            if (in_array($pid, $temp_check)) {
                $errors[] = "Dòng " . ($i + 1) . ": Sản phẩm bị trùng.";
            }
            $temp_check[] = $pid;

            if ($qty <= 0) {
                $errors[] = "Dòng " . ($i + 1) . ": Số lượng phải > 0.";
                continue;
            }

            // Check tồn kho thực tế
            $stmtStock = $pdo->prepare("SELECT so_luong_ton FROM chi_tiet_kho_hang WHERE kho_hang_id = ? AND bien_the_id = ?");
            $stmtStock->execute([$kho_id, $pid]);
            $current_stock = $stmtStock->fetchColumn();

            if ($current_stock === false || $current_stock < $qty) {
                $stock_show = ($current_stock === false) ? 0 : $current_stock;
                $errors[] = "Dòng " . ($i + 1) . ": <b>Lỗi tồn kho!</b> Kho này chỉ còn <b>$stock_show</b> sản phẩm (Cần xuất: $qty).";
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

                $stmtGetParent = $pdo->prepare("SELECT san_pham_id FROM bien_the_san_pham WHERE id = ?");
                $stmtGetParent->execute([$bt_id]);
                $sp_id = $stmtGetParent->fetchColumn();

                // Chi tiết phiếu
                $sqlChiTiet = "INSERT INTO chi_tiet_phieu_kho (phieu_kho_id, san_pham_id, bien_the_id, so_luong, don_gia) VALUES (?, ?, ?, ?, 0)";
                $pdo->prepare($sqlChiTiet)->execute([$phieu_id, $sp_id, $bt_id, $so_luong]);

                // Trừ kho chi tiết
                $sqlKhoChiTiet = "UPDATE chi_tiet_kho_hang SET so_luong_ton = so_luong_ton - ? WHERE kho_hang_id = ? AND bien_the_id = ?";
                $pdo->prepare($sqlKhoChiTiet)->execute([$so_luong, $kho_id, $bt_id]);

                // Trừ kho tổng
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
                                    <select name="product_variant_id[]" class="form-select product-select" required onchange="updateStockHint(this)">
                                        <option value="" data-stock="0">-- Chọn sản phẩm --</option>
                                        <?php foreach ($ds_san_pham as $sp): ?>
                                            <option value="<?php echo $sp['bien_the_id']; ?>" data-stock="<?php echo $sp['so_luong_ton']; ?>">
                                                <?php echo htmlspecialchars($sp['ten'] . ' (' . $sp['mau_sac'] . ' - ' . $sp['dung_luong_ssd'] . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="stock-hint">Tổng tồn hệ thống: <span>0</span></div>
                                </td>
                                <td>
                                    <input type="number" name="quantity[]" class="form-input qty text-center" min="1" value="1" required>
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

<script>
    function updateStockHint(select) {
        var stock = select.options[select.selectedIndex].getAttribute('data-stock') || 0;
        var hint = select.parentNode.querySelector('.stock-hint span');
        if(hint) hint.innerText = stock;
    }

    function addRow() {
        var table = document.getElementById("productTable").getElementsByTagName('tbody')[0];
        var newRow = table.rows[0].cloneNode(true);
        
        newRow.querySelector('select').value = '';
        newRow.querySelector('.stock-hint span').innerText = '0';
        newRow.querySelector('.qty').value = 1;
        
        table.appendChild(newRow);
    }

    function removeRow(btn) {
        var row = btn.closest('tr');
        var tbody = row.parentNode;
        if (tbody.rows.length > 1) row.remove();
        else alert("Phải có ít nhất 1 dòng sản phẩm!");
    }

    function validateForm() {
        return confirm('CẢNH BÁO: Bạn đang thực hiện XUẤT KHO. Số lượng tồn kho sẽ bị trừ ngay lập tức. Bạn có chắc chắn không?');
    }
</script>