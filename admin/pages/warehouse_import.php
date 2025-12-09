<?php
// FILE: admin/pages/warehouse_import.php

/* ===========================================================
   PHẦN 1: XỬ LÝ LOGIC BACKEND (GIỮ NGUYÊN)
   =========================================================== */
$msg = '';
$msg_type = '';

// 1. Lấy danh sách kho
$stmt = $pdo->query("SELECT * FROM kho_hang WHERE trang_thai = 1 ORDER BY id DESC");
$ds_kho = $stmt->fetchAll();

// 2. Lấy danh sách sản phẩm
$sqlProducts = "
    SELECT bt.id as bien_the_id, sp.ten, bt.mau_sac, bt.dung_luong_ssd, bt.gia, sp.hinh_anh 
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
    $nguoi_nhap_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : (isset($_SESSION['user']['id']) ? $_SESSION['user']['id'] : 10);

    $product_ids = isset($_POST['product_variant_id']) ? $_POST['product_variant_id'] : [];
    $quantities = isset($_POST['quantity']) ? $_POST['quantity'] : [];
    $import_prices = isset($_POST['import_price']) ? $_POST['import_price'] : [];

    $errors = [];

    // Validation
    if (empty($product_ids)) $errors[] = "Chưa chọn sản phẩm nào.";
    
    // Check trùng lặp & Logic
    $temp_check = [];
// [MỚI] 1. Tạo bản đồ giá bán trước khi vào vòng lặp
    $price_map = [];
    foreach ($ds_san_pham as $sp) {
        $price_map[$sp['bien_the_id']] = $sp['gia'];
    }

    // Check trùng lặp & Logic
    $temp_check = [];
    foreach ($product_ids as $key => $pid) {
        if (in_array($pid, $temp_check)) {
            $errors[] = "Dòng " . ($key + 1) . ": Sản phẩm bị trùng.";
        }
        $temp_check[] = $pid;
        
        if ($quantities[$key] <= 0) $errors[] = "Dòng " . ($key + 1) . ": Số lượng phải > 0.";
        if ($import_prices[$key] < 0) $errors[] = "Dòng " . ($key + 1) . ": Giá nhập không được âm.";

        // [MỚI] 2. Logic kiểm tra nằm GỌN trong vòng lặp
        if (isset($price_map[$pid])) {
            $gia_ban_hien_tai = $price_map[$pid];
            if ($import_prices[$key] > $gia_ban_hien_tai) {
                $errors[] = "Dòng " . ($key + 1) . ": <b>LỖI NGHIÊM TRỌNG!</b> Giá nhập (" . number_format($import_prices[$key]) . "đ) cao hơn giá bán (" . number_format($gia_ban_hien_tai) . "đ).";
            }
        }
    } 
    if (!empty($errors)) {
        $msg = implode("<br>", $errors);
        $msg_type = "danger";
    } else {
        try {
            $pdo->beginTransaction();

            // Mã phiếu: PN + YmdHis
            $ma_phieu = 'PN' . date('YmdHis') . rand(10, 99);

            // Insert Header
            $sqlPhieu = "INSERT INTO phieu_kho (ma_phieu, loai_phieu, kho_hang_id, nguoi_dung_id, ghi_chu, trang_thai, ngay_tao) 
                         VALUES (?, 'nhap', ?, ?, ?, 1, NOW())";
            $stmtPhieu = $pdo->prepare($sqlPhieu);
            $stmtPhieu->execute([$ma_phieu, $kho_id, $nguoi_nhap_id, $ghi_chu]);
            $phieu_id = $pdo->lastInsertId();

            // Insert Details
            for ($i = 0; $i < count($product_ids); $i++) {
                $bt_id = $product_ids[$i];
                $sl = $quantities[$i];
                $gia = $import_prices[$i];

                $stmtGetParent = $pdo->prepare("SELECT san_pham_id FROM bien_the_san_pham WHERE id = ?");
                $stmtGetParent->execute([$bt_id]);
                $sp_id = $stmtGetParent->fetchColumn();

                // Lưu chi tiết phiếu
                $sqlChiTiet = "INSERT INTO chi_tiet_phieu_kho (phieu_kho_id, san_pham_id, bien_the_id, so_luong, don_gia) VALUES (?, ?, ?, ?, ?)";
                $pdo->prepare($sqlChiTiet)->execute([$phieu_id, $sp_id, $bt_id, $sl, $gia]);

                // Update Tồn kho chi tiết
                $sqlKho = "INSERT INTO chi_tiet_kho_hang (kho_hang_id, san_pham_id, bien_the_id, so_luong_ton) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE so_luong_ton = so_luong_ton + VALUES(so_luong_ton)";
                $pdo->prepare($sqlKho)->execute([$kho_id, $sp_id, $bt_id, $sl]);

                // Update Tổng tồn kho
                $pdo->prepare("UPDATE bien_the_san_pham SET so_luong_ton = so_luong_ton + ? WHERE id = ?")->execute([$sl, $bt_id]);
            }

            $pdo->commit();
            echo "<script>alert('Nhập kho thành công! Mã phiếu: $ma_phieu'); window.location.href='index.php?page=warehouse_history';</script>";
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $msg = "Lỗi hệ thống: " . $e->getMessage();
            $msg_type = "danger";
        }
    }
}
?>

<link rel="stylesheet" href="../assets/css/admin/warehouse_import.css">

<div class="import-container">
    
    <div class="page-header-title">
        <div style="display:flex; align-items:center; gap:12px;">
            <a href="index.php?page=warehouse_list" class="btn-back"><i class="fa-solid fa-arrow-left"></i></a>
            <span>Tạo Phiếu Nhập Kho</span>
        </div>
    </div>

    <?php if ($msg): ?>
        <div class="alert alert-<?php echo $msg_type; ?>">
            <i class="fa-solid fa-triangle-exclamation"></i> <?php echo $msg; ?>
        </div>
    <?php endif; ?>

    <form action="" method="POST" id="importForm" onsubmit="return validateForm()">
        <div class="import-grid">
            
            <div class="card-info">
                <div class="card-header">
                    <i class="fa-solid fa-circle-info"></i> Thông tin chung
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">Nhập vào Kho <span class="text-red">*</span></label>
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
                        <label class="form-label">Ghi chú</label>
                        <textarea name="ghi_chu" class="form-textarea" placeholder="Nhập lý do, nguồn hàng..."></textarea>
                    </div>
                </div>
            </div>

            <div class="card-products">
                <div class="card-header flex-between">
                    <div><i class="fa-solid fa-list-check"></i> Danh sách hàng nhập</div>
                    <button type="button" class="btn-add-row" onclick="addRow()">
                        <i class="fa-solid fa-plus"></i> Thêm dòng
                    </button>
                </div>
                
                <div class="table-wrapper">
                    <table class="table-import" id="productTable">
                        <thead>
                            <tr>
                                <th width="45%">Sản phẩm</th>
                                <th width="20%">Số lượng</th>
                                <th width="25%">Giá nhập</th>
                                <th width="10%"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="item-row">
                                <td>
                                    <select name="product_variant_id[]" class="form-select product-select" required onchange="updatePriceHint(this)">
                                        <option value="" data-price="0">-- Chọn sản phẩm --</option>
                                        <?php foreach ($ds_san_pham as $sp): ?>
                                            <option value="<?php echo $sp['bien_the_id']; ?>" data-price="<?php echo $sp['gia']; ?>">
                                                <?php echo htmlspecialchars($sp['ten'] . ' (' . $sp['mau_sac'] . ' - ' . $sp['dung_luong_ssd'] . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="price-hint">Giá bán hiện tại: <span>0</span>đ</div>
                                </td>
                                <td>
                                    <input type="number" name="quantity[]"  class="form-input qty text-center" min="1" value="1" required oninput="calcTotal()">
                                </td>
                                <td>
                                <input type="number" name="import_price[]" class="form-input price text-end" min="0" value="0" required oninput="calcTotal(); checkProfit(this)">
                                <div class="profit-warning" style="display:none; color:red; font-size:11px; margin-top:4px;"><i class="fa-solid fa-triangle-exclamation"></i> Giá nhập > Giá bán!</div>
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn-remove" onclick="removeRow(this)"><i class="fa-solid fa-xmark"></i></button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="footer-summary">
                    <div class="total-label">Tổng tiền dự kiến:</div>
                    <div class="total-value" id="grandTotal">0 đ</div>
                </div>

                <div class="footer-actions">
                    <a href="index.php?page=warehouse_list" class="btn-cancel">Hủy bỏ</a>
                    <button type="submit" class="btn-submit">Hoàn tất nhập kho</button>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
    // JS Logic
    function updatePriceHint(select) {
        var price = select.options[select.selectedIndex].getAttribute('data-price') || 0;
        var hint = select.parentNode.querySelector('.price-hint span');
        if(hint) hint.innerText = new Intl.NumberFormat('vi-VN').format(price);
    }

    function addRow() {
        var table = document.getElementById("productTable").getElementsByTagName('tbody')[0];
        var newRow = table.rows[0].cloneNode(true);
        
        // Reset values
        newRow.querySelector('select').value = '';
        newRow.querySelector('.price-hint span').innerText = '0';
        newRow.querySelector('.qty').value = 1;
        newRow.querySelector('.price').value = 0;
        
        table.appendChild(newRow);
    }

    function removeRow(btn) {
        var row = btn.closest('tr');
        var tbody = row.parentNode;
        if (tbody.rows.length > 1) {
            row.remove();
            calcTotal();
        } else {
            alert("Phải nhập ít nhất 1 sản phẩm!");
        }
    }

    function calcTotal() {
        var total = 0;
        document.querySelectorAll('.item-row').forEach(function(row) {
            var qty = parseFloat(row.querySelector('.qty').value) || 0;
            var price = parseFloat(row.querySelector('.price').value) || 0;
            total += qty * price;
        });
        document.getElementById('grandTotal').innerText = new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(total);
    }

        function validateForm() {
        // 1. Kiểm tra có dòng sản phẩm nào không
        if(document.querySelectorAll('.item-row').length === 0) {
            alert("Vui lòng thêm ít nhất 1 sản phẩm.");
            return false;
        }

        // 2. [MỚI] Quét xem có ô nào đang vi phạm giá bán không
        var hasError = false;
        var rows = document.querySelectorAll('.item-row');
        
        rows.forEach(function(row) {
            var select = row.querySelector('.product-select');
            var priceInput = row.querySelector('.price');
            
            // Lấy giá bán và giá nhập
            var sellingPrice = parseFloat(select.options[select.selectedIndex].getAttribute('data-price')) || 0;
            var importPrice = parseFloat(priceInput.value) || 0;

            if (sellingPrice > 0 && importPrice > sellingPrice) {
                hasError = true;
                priceInput.style.backgroundColor = '#ffe6e6'; // Highlight lại cho chắc
                priceInput.focus(); // Trỏ chuột vào ô lỗi
            }
        });

        if (hasError) {
            alert("CẢNH BÁO: Có sản phẩm giá nhập cao hơn giá bán!\nVui lòng kiểm tra lại các ô màu đỏ.");
            return false; // Chặn submit form
        }

        return confirm('Xác nhận nhập kho? Kho sẽ được cập nhật ngay lập tức.');
    }
    // [MỚI] Hàm cảnh báo lỗ vốn ngay lập tức
    function checkProfit(input) {
        var row = input.closest('tr');
        var select = row.querySelector('.product-select');
        
        // Lấy giá bán từ data-price của option đang chọn
        var selectedOption = select.options[select.selectedIndex];
        var sellingPrice = parseFloat(selectedOption.getAttribute('data-price')) || 0;
        var importPrice = parseFloat(input.value) || 0;
        
        // Tìm thẻ cảnh báo (div profit-warning vừa thêm ở trên)
        var warningMsg = row.querySelector('.profit-warning');

        if (importPrice > sellingPrice && sellingPrice > 0) {
            input.style.borderColor = 'red';
            input.style.backgroundColor = '#fff0f0';
            if(warningMsg) warningMsg.style.display = 'block';
        } else {
            input.style.borderColor = ''; // Trả về mặc định
            input.style.backgroundColor = '';
            if(warningMsg) warningMsg.style.display = 'none';
        }
    }

    // Cập nhật lại hàm updatePriceHint để reset cảnh báo khi đổi sản phẩm
    function updatePriceHint(select) {
        var price = select.options[select.selectedIndex].getAttribute('data-price') || 0;
        var hint = select.parentNode.querySelector('.price-hint span');
        if(hint) hint.innerText = new Intl.NumberFormat('vi-VN').format(price);
        
        // [MỚI] Kiểm tra lại giá khi đổi sản phẩm
        var row = select.closest('tr');
        var priceInput = row.querySelector('.price');
        checkProfit(priceInput);
    }
</script>