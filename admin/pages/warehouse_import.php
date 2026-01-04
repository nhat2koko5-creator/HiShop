<?php
// FILE: admin/pages/warehouse_import.php

/* ===========================================================
   PHẦN 1: XỬ LÝ LOGIC BACKEND
   =========================================================== */
$msg = '';
$msg_type = '';
$redirect_url = ''; // Biến lưu link chuyển hướng

// Kiểm tra nếu truy cập trực tiếp với id kho bị khóa
$kho_id_from_url = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($kho_id_from_url > 0) {
    $stmtCheckLockedKho = $pdo->prepare("SELECT id, trang_thai, ten_kho FROM kho_hang WHERE id = ?");
    $stmtCheckLockedKho->execute([$kho_id_from_url]);
    $locked_kho = $stmtCheckLockedKho->fetch(PDO::FETCH_ASSOC);
    
    if ($locked_kho && $locked_kho['trang_thai'] == 0) {
        echo "<script>alert('Kho hàng \"" . htmlspecialchars($locked_kho['ten_kho']) . "\" đang bị tạm khóa. Không thể nhập hàng vào kho bị khóa!'); window.location.href='index.php?page=warehouse_list';</script>";
        exit;
    }
}

// 1. Lấy danh sách kho (chỉ kho hoạt động)
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

    // Kiểm tra kho có bị khóa không
    $stmtCheckWarehouse = $pdo->prepare("SELECT trang_thai FROM kho_hang WHERE id = ?");
    $stmtCheckWarehouse->execute([$kho_id]);
    $warehouse_status = $stmtCheckWarehouse->fetchColumn();
    
    if ($warehouse_status === false) {
        $errors[] = "Kho hàng không tồn tại.";
    } elseif ($warehouse_status == 0) {
        $errors[] = "Kho hàng này đang bị tạm khóa. Không thể nhập hàng vào kho bị khóa!";
    }

    // Validation cơ bản
    if (empty($product_ids)) $errors[] = "Chưa chọn sản phẩm nào.";
    
    // Tạo bản đồ giá bán để so sánh
    $price_map = [];
    foreach ($ds_san_pham as $sp) {
        $price_map[$sp['bien_the_id']] = $sp['gia'];
    }

    // Check trùng lặp & Logic chi tiết
    $temp_check = [];
    foreach ($product_ids as $key => $pid) {
        if (empty($pid)) continue;

        if (in_array($pid, $temp_check)) {
            $errors[] = "Dòng " . ($key + 1) . ": Sản phẩm bị trùng.";
        }
        $temp_check[] = $pid;
        
        if ($quantities[$key] <= 0) $errors[] = "Dòng " . ($key + 1) . ": Số lượng phải lớn hơn 0.";
        if ($import_prices[$key] < 0) $errors[] = "Dòng " . ($key + 1) . ": Giá nhập không được âm.";

        if (isset($price_map[$pid])) {
            $gia_ban_hien_tai = $price_map[$pid];
            if ($import_prices[$key] > $gia_ban_hien_tai) {
                $errors[] = "Dòng " . ($key + 1) . ": <b>LỖI NGHIÊM TRỌNG!</b> Giá nhập (" . number_format($import_prices[$key]) . "đ) cao hơn giá bán (" . number_format($gia_ban_hien_tai) . "đ).";
            }
        }
    } 

    if (!empty($errors)) {
        $msg = implode("<br>", $errors);
        $msg_type = "error"; // Đổi thành 'error' cho đồng bộ JS
    } else {
        try {
            $pdo->beginTransaction();

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

                if(empty($bt_id)) continue;

                $stmtGetParent = $pdo->prepare("SELECT san_pham_id FROM bien_the_san_pham WHERE id = ?");
                $stmtGetParent->execute([$bt_id]);
                $sp_id = $stmtGetParent->fetchColumn();

                $sqlChiTiet = "INSERT INTO chi_tiet_phieu_kho (phieu_kho_id, san_pham_id, bien_the_id, so_luong, don_gia) VALUES (?, ?, ?, ?, ?)";
                $pdo->prepare($sqlChiTiet)->execute([$phieu_id, $sp_id, $bt_id, $sl, $gia]);

                $sqlKho = "INSERT INTO chi_tiet_kho_hang (kho_hang_id, san_pham_id, bien_the_id, so_luong_ton) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE so_luong_ton = so_luong_ton + VALUES(so_luong_ton)";
                $pdo->prepare($sqlKho)->execute([$kho_id, $sp_id, $bt_id, $sl]);

                $pdo->prepare("UPDATE bien_the_san_pham SET so_luong_ton = so_luong_ton + ? WHERE id = ?")->execute([$sl, $bt_id]);
            }

            $pdo->commit();
            
            // [THAY ĐỔI] Set biến để hiện popup
            $msg = "Nhập kho thành công! Mã phiếu: $ma_phieu. Đang chuyển hướng...";
            $msg_type = "success";
            $redirect_url = "index.php?page=warehouse_history";

        } catch (Exception $e) {
            $pdo->rollBack();
            $msg = "Lỗi hệ thống: " . $e->getMessage();
            $msg_type = "error";
        }
    }
}
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="../assets/css/admin/warehouse_import.css">

<style>
    #toast-container {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
    }
    .toast {
        display: flex;
        align-items: center;
        background: #fff;
        padding: 16px 20px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        margin-bottom: 12px;
        min-width: 300px;
        max-width: 450px;
        animation: slideIn 0.3s ease forwards;
        border-left: 5px solid #ccc;
    }
    .toast.success { border-left-color: #10b981; }
    .toast.error { border-left-color: #ef4444; }
    .toast-icon { font-size: 20px; margin-right: 12px; }
    .toast.success .toast-icon { color: #10b981; }
    .toast.error .toast-icon { color: #ef4444; }
    .toast-content { flex: 1; }
    .toast-title { font-weight: 700; font-size: 14px; margin-bottom: 4px; color: #1e293b; }
    .toast-msg { font-size: 13px; color: #64748b; line-height: 1.4; }
    .toast-close { cursor: pointer; font-size: 18px; color: #94a3b8; margin-left: 12px; }
    
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    @keyframes fadeOut {
        to { opacity: 0; transform: translateX(20px); }
    }
</style>

<div class="import-container">
    <div id="toast-container"></div>
    
    <div class="page-header-title">
        <div style="display:flex; align-items:center; gap:12px;">
            <a href="index.php?page=warehouse_list" class="btn-back"><i class="fa-solid fa-arrow-left"></i></a>
            <span>Tạo Phiếu Nhập Kho</span>
        </div>
    </div>

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
                                    <select name="product_variant_id[]" class="form-select product-select select2-init" required onchange="updatePriceHint(this)">
                                        <option value="" data-price="0">-- Tìm kiếm & chọn sản phẩm --</option>
                                        <?php foreach ($ds_san_pham as $sp): ?>
                                            <option value="<?php echo $sp['bien_the_id']; ?>" data-price="<?php echo $sp['gia']; ?>">
                                                <?php echo htmlspecialchars($sp['ten'] . ' (' . $sp['mau_sac'] . ' - ' . $sp['dung_luong_ssd'] . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="price-hint">Giá bán hiện tại: <span>0</span>đ</div>
                                </td>
                                <td>
                                    <input type="number" name="quantity[]" class="form-input qty text-center" min="1" value="1" required 
                                           oninput="enforcePositive(this); calcTotal()">
                                </td>
                                <td>
                                    <input type="number" name="import_price[]" class="form-input price text-end" min="0" value="0" required 
                                           oninput="enforcePositive(this); calcTotal(); checkProfit(this)">
                                    <div class="profit-warning" style="display:none; color:red; font-size:11px; margin-top:4px;">
                                        <i class="fa-solid fa-triangle-exclamation"></i> Giá nhập > Giá bán!
                                    </div>
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

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    $(document).ready(function() {
        initSelect2();

        // [MỚI] HIỂN THỊ TOAST TỪ PHP
        <?php if ($msg): ?>
            showToast("<?php echo $msg_type == 'success' ? 'Thành công' : 'Lỗi nhập kho'; ?>", 
                      "<?php echo addslashes($msg); ?>", 
                      "<?php echo $msg_type; ?>");
            
            <?php if ($redirect_url): ?>
                setTimeout(function() {
                    window.location.href = "<?php echo $redirect_url; ?>";
                }, 1500);
            <?php endif; ?>
        <?php endif; ?>
    });

    // [MỚI] HÀM SHOW TOAST
    function showToast(title, message, type) {
        const icons = {
            success: '<i class="fa-solid fa-circle-check"></i>',
            error: '<i class="fa-solid fa-triangle-exclamation"></i>',
            info: '<i class="fa-solid fa-circle-info"></i>'
        };
        const icon = icons[type] || icons.info;
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.innerHTML = `
            <div class="toast-icon">${icon}</div>
            <div class="toast-content">
                <div class="toast-title">${title}</div>
                <div class="toast-msg">${message}</div>
            </div>
            <div class="toast-close" onclick="this.parentElement.remove()">&times;</div>
        `;
        document.getElementById('toast-container').appendChild(toast);
        setTimeout(() => {
            toast.style.animation = 'fadeOut 0.3s ease forwards';
            setTimeout(() => toast.remove(), 300);
        }, 5000);
    }

    function initSelect2() {
        $('.select2-init').select2({
            width: '100%',
            placeholder: "-- Tìm kiếm sản phẩm --",
            allowClear: true
        });
        $('.select2-init').on('select2:select', function (e) {
            this.dispatchEvent(new Event('change'));
        });
    }

    function enforcePositive(el) {
        if (el.value === '') return;
        if (parseInt(el.value) < 0) {
            el.value = 0; 
            if(el.classList.contains('qty')) el.value = 1;
        }
    }

    function updatePriceHint(select) {
        var price = select.options[select.selectedIndex].getAttribute('data-price') || 0;
        var hint = select.parentNode.querySelector('.price-hint span');
        if(hint) hint.innerText = new Intl.NumberFormat('vi-VN').format(price);
        
        var row = select.closest('tr');
        var priceInput = row.querySelector('.price');
        checkProfit(priceInput);
    }

    function addRow() {
        var table = document.getElementById("productTable").getElementsByTagName('tbody')[0];
        
        var optionsHtml = `
            <option value="" data-price="0">-- Tìm kiếm & chọn sản phẩm --</option>
            <?php foreach ($ds_san_pham as $sp): ?>
                <option value="<?php echo $sp['bien_the_id']; ?>" data-price="<?php echo $sp['gia']; ?>">
                    <?php echo htmlspecialchars($sp['ten'] . ' (' . $sp['mau_sac'] . ' - ' . $sp['dung_luong_ssd'] . ')'); ?>
                </option>
            <?php endforeach; ?>
        `;

        var newRowHtml = `
            <tr class="item-row">
                <td>
                    <select name="product_variant_id[]" class="form-select product-select select2-init" required onchange="updatePriceHint(this)">
                        ${optionsHtml}
                    </select>
                    <div class="price-hint">Giá bán hiện tại: <span>0</span>đ</div>
                </td>
                <td>
                    <input type="number" name="quantity[]" class="form-input qty text-center" min="1" value="1" required oninput="enforcePositive(this); calcTotal()">
                </td>
                <td>
                    <input type="number" name="import_price[]" class="form-input price text-end" min="0" value="0" required oninput="enforcePositive(this); calcTotal(); checkProfit(this)">
                    <div class="profit-warning" style="display:none; color:red; font-size:11px; margin-top:4px;">
                        <i class="fa-solid fa-triangle-exclamation"></i> Giá nhập > Giá bán!
                    </div>
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
        if (tbody.querySelectorAll('tr').length > 1) {
            row.remove();
            calcTotal();
        } else {
            showToast("Cảnh báo", "Phải nhập ít nhất 1 sản phẩm!", "error");
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
        if(document.querySelectorAll('.item-row').length === 0) {
            showToast("Lỗi nhập liệu", "Vui lòng thêm ít nhất 1 sản phẩm.", "error");
            return false;
        }

        var hasError = false;
        var rows = document.querySelectorAll('.item-row');
        
        rows.forEach(function(row) {
            var select = row.querySelector('.product-select');
            var priceInput = row.querySelector('.price');
            
            var sellingPrice = parseFloat(select.options[select.selectedIndex].getAttribute('data-price')) || 0;
            var importPrice = parseFloat(priceInput.value) || 0;

            if (sellingPrice > 0 && importPrice > sellingPrice) {
                hasError = true;
                priceInput.style.backgroundColor = '#ffe6e6';
                priceInput.focus();
            }
        });

        if (hasError) {
            showToast("Rủi ro kinh doanh", "Có sản phẩm giá nhập cao hơn giá bán! Vui lòng kiểm tra lại.", "error");
            return false;
        }

        return confirm('Xác nhận nhập kho? Kho sẽ được cập nhật ngay lập tức.');
    }

    function checkProfit(input) {
        var row = input.closest('tr');
        var select = row.querySelector('.product-select');
        var sellingPrice = parseFloat(select.options[select.selectedIndex].getAttribute('data-price')) || 0;
        var importPrice = parseFloat(input.value) || 0;
        var warningMsg = row.querySelector('.profit-warning');

        if (importPrice > sellingPrice && sellingPrice > 0) {
            input.style.borderColor = 'red';
            input.style.backgroundColor = '#fff0f0';
            if(warningMsg) warningMsg.style.display = 'block';
        } else {
            input.style.borderColor = '';
            input.style.backgroundColor = '';
            if(warningMsg) warningMsg.style.display = 'none';
        }
    }
</script>