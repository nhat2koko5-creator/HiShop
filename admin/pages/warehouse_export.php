<?php
// FILE: admin/pages/warehouse_export.php

/* ===========================================================
   PHẦN 1: XỬ LÝ LOGIC BACKEND
   =========================================================== */
$msg = '';
$msg_type = '';
$redirect_url = ''; // Biến để lưu link chuyển hướng nếu thành công

// Kiểm tra nếu truy cập trực tiếp với id kho bị khóa
$kho_id_from_url = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($kho_id_from_url > 0) {
    $stmtCheckLockedKho = $pdo->prepare("SELECT id, trang_thai, ten_kho FROM kho_hang WHERE id = ?");
    $stmtCheckLockedKho->execute([$kho_id_from_url]);
    $locked_kho = $stmtCheckLockedKho->fetch(PDO::FETCH_ASSOC);
    
    if ($locked_kho && $locked_kho['trang_thai'] == 0) {
        echo "<script>alert('Kho hàng \"" . htmlspecialchars($locked_kho['ten_kho']) . "\" đang bị tạm khóa. Không thể xuất hàng từ kho bị khóa!'); window.location.href='index.php?page=warehouse_list';</script>";
        exit;
    }
}

// 1. Lấy danh sách kho (chỉ kho hoạt động)
$stmt = $pdo->query("SELECT * FROM kho_hang WHERE trang_thai = 1 ORDER BY id DESC");
$ds_kho = $stmt->fetchAll();

// 2. Lấy danh sách sản phẩm
$sqlProducts = "
    SELECT bt.id as bien_the_id, sp.ten, bt.mau_sac, bt.dung_luong_ssd, sp.hinh_anh
    FROM bien_the_san_pham bt
    JOIN san_pham sp ON bt.san_pham_id = sp.id
    ORDER BY sp.ten ASC
";
$stmtProd = $pdo->query($sqlProducts);
$ds_san_pham = $stmtProd->fetchAll();

// 3. Lấy chi tiết tồn kho
$stock_map = [];
$stmtStockDetail = $pdo->query("SELECT kho_hang_id, bien_the_id, so_luong_ton FROM chi_tiet_kho_hang");
while ($row = $stmtStockDetail->fetch(PDO::FETCH_ASSOC)) {
    $stock_map[$row['kho_hang_id']][$row['bien_the_id']] = (int)$row['so_luong_ton'];
}

// 4. XỬ LÝ FORM SUBMIT
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kho_id = isset($_POST['kho_id']) ? (int)$_POST['kho_id'] : 0;
    $ghi_chu = isset($_POST['ghi_chu']) ? trim($_POST['ghi_chu']) : '';
    $nguoi_xuat_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : (isset($_SESSION['user']['id']) ? $_SESSION['user']['id'] : 10); 

    $product_ids = isset($_POST['product_variant_id']) ? $_POST['product_variant_id'] : [];
    $quantities = isset($_POST['quantity']) ? $_POST['quantity'] : [];

    $errors = [];

    // Check kho - kiểm tra cả trạng thái kho
    $stmtCheckKho = $pdo->prepare("SELECT ten_kho, trang_thai FROM kho_hang WHERE id = ?");
    $stmtCheckKho->execute([$kho_id]);
    $kho_data = $stmtCheckKho->fetch(PDO::FETCH_ASSOC);
    
    if (!$kho_data) {
        $errors[] = "Kho xuất không tồn tại.";
    } else {
        $ten_kho = $kho_data['ten_kho'];
        if ($kho_data['trang_thai'] == 0) {
            $errors[] = "Kho hàng này đang bị tạm khóa. Không thể xuất hàng từ kho bị khóa!";
        }
    }
    
    if (empty($product_ids)) $errors[] = "Chưa chọn sản phẩm nào.";

    // Check chi tiết
    if (empty($errors)) {
        $temp_check = [];
        foreach ($product_ids as $key => $pid) {
            if (empty($pid)) continue;
            $qty = (int)$quantities[$key];

            if (in_array($pid, $temp_check)) {
                $errors[] = "Dòng " . ($key + 1) . ": Sản phẩm bị trùng.";
            }
            $temp_check[] = $pid;

            if ($qty <= 0) {
                $errors[] = "Dòng " . ($key + 1) . ": Số lượng phải lớn hơn 0.";
                continue;
            }

            $stmtStock = $pdo->prepare("SELECT so_luong_ton FROM chi_tiet_kho_hang WHERE kho_hang_id = ? AND bien_the_id = ?");
            $stmtStock->execute([$kho_id, $pid]);
            $current_stock = $stmtStock->fetchColumn();

            if ($current_stock === false || $current_stock < $qty) {
                $stock_show = ($current_stock === false) ? 0 : $current_stock;
                $errors[] = "Dòng " . ($key + 1) . ": <b>Không đủ hàng!</b> Kho <b>$ten_kho</b> chỉ còn <b>$stock_show</b> sản phẩm (Yêu cầu xuất: $qty).";
            }
        }
    }

    if (!empty($errors)) {
        $msg = implode("<br>", $errors);
        $msg_type = "error"; // Dùng 'error' thay vì 'danger' cho JS dễ xử lý
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

                $sqlChiTiet = "INSERT INTO chi_tiet_phieu_kho (phieu_kho_id, san_pham_id, bien_the_id, so_luong, don_gia) VALUES (?, ?, ?, ?, 0)";
                $pdo->prepare($sqlChiTiet)->execute([$phieu_id, $sp_id, $bt_id, $so_luong]);

                $sqlKhoChiTiet = "UPDATE chi_tiet_kho_hang SET so_luong_ton = so_luong_ton - ? WHERE kho_hang_id = ? AND bien_the_id = ?";
                $pdo->prepare($sqlKhoChiTiet)->execute([$so_luong, $kho_id, $bt_id]);

                $pdo->prepare("UPDATE bien_the_san_pham SET so_luong_ton = so_luong_ton - ? WHERE id = ?")->execute([$so_luong, $bt_id]);
            }

            $pdo->commit();
            
            // [THAY ĐỔI] Không echo script alert nữa, mà set biến để hiện Toast
            $msg = "Xuất kho thành công! Mã phiếu: $ma_phieu. Đang chuyển hướng...";
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
<link rel="stylesheet" href="../assets/css/admin/warehouse_export.css">

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
    
    .toast-icon {
        font-size: 20px;
        margin-right: 12px;
    }
    .toast.success .toast-icon { color: #10b981; }
    .toast.error .toast-icon { color: #ef4444; }
    
    .toast-content { flex: 1; }
    .toast-title { font-weight: 700; font-size: 14px; margin-bottom: 4px; color: #1e293b; }
    .toast-msg { font-size: 13px; color: #64748b; line-height: 1.4; }
    
    .toast-close {
        cursor: pointer;
        font-size: 18px;
        color: #94a3b8;
        margin-left: 12px;
    }
    
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    @keyframes fadeOut {
        to { opacity: 0; transform: translateX(20px); }
    }
</style>

<div class="export-container">
    <div id="toast-container"></div>

    <div class="page-header-title">
        <div style="display:flex; align-items:center; gap:12px;">
            <a href="index.php?page=warehouse_list" class="btn-back"><i class="fa-solid fa-arrow-left"></i></a>
            <span>Tạo Phiếu Xuất Kho</span>
        </div>
    </div>

    <form action="" method="POST" id="exportForm" onsubmit="return validateForm()">
        <div class="export-grid">
            
            <div class="card-info">
                <div class="card-header">
                    <i class="fa-solid fa-truck-ramp-box"></i> Thông tin xuất
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">Xuất từ Kho <span class="text-red">*</span></label>
                        <select name="kho_id" id="warehouse_select" class="form-select" required onchange="onWarehouseChange()">
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
                                        <option value="">-- Tìm kiếm & chọn sản phẩm --</option>
                                        <?php foreach ($ds_san_pham as $sp): ?>
                                            <option value="<?php echo $sp['bien_the_id']; ?>">
                                                <?php echo htmlspecialchars($sp['ten'] . ' (' . $sp['mau_sac'] . ' - ' . $sp['dung_luong_ssd'] . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="stock-hint">Tồn kho tại kho này: <span class="stock-val">0</span></div>
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
    const stockMap = <?php echo json_encode($stock_map); ?>;

    $(document).ready(function() {
        initSelect2();
        onWarehouseChange();

        // [MỚI] KIỂM TRA BIẾN PHP ĐỂ HIỆN TOAST
        <?php if ($msg): ?>
            showToast("<?php echo $msg_type == 'success' ? 'Thành công' : 'Lỗi xuất kho'; ?>", 
                      "<?php echo addslashes($msg); ?>", 
                      "<?php echo $msg_type; ?>");
            
            <?php if ($redirect_url): ?>
                // Nếu thành công, đợi 1.5s rồi chuyển trang
                setTimeout(function() {
                    window.location.href = "<?php echo $redirect_url; ?>";
                }, 1500);
            <?php endif; ?>
        <?php endif; ?>
    });

    // [MỚI] HÀM HIỂN THỊ TOAST
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
        
        // Tự động ẩn sau 5s (nếu không chuyển trang)
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
            updateStockHint(this);
        });
    }

    function enforcePositive(el) {
        if (el.value === '') return;
        if (parseInt(el.value) < 0) el.value = 1;
        
        let row = el.closest('tr');
        let stockSpan = row.querySelector('.stock-val');
        let maxStock = parseInt(stockSpan.innerText.replace(/[^0-9]/g, '')) || 0;
        
        if(parseInt(el.value) > maxStock) {
            // [CẬP NHẬT] Dùng Toast thay vì alert
            showToast("Cảnh báo số lượng", "Số lượng xuất không được vượt quá tồn kho (" + maxStock + ")", "error");
            el.value = maxStock;
        }
    }

    function getStock(khoId, productId) {
        if (stockMap[khoId] && stockMap[khoId][productId]) {
            return parseInt(stockMap[khoId][productId]);
        }
        return 0;
    }

    function onWarehouseChange() {
        let productSelects = document.querySelectorAll('.product-select');
        productSelects.forEach(function(select) {
            updateStockHint(select);
        });
    }

    function updateStockHint(select) {
        let khoId = document.getElementById('warehouse_select').value;
        let productId = select.value;
        let hintSpan = select.parentNode.querySelector('.stock-val');
        let inputQty = select.closest('tr').querySelector('.qty');

        if (!productId) {
            hintSpan.innerText = 0;
            hintSpan.style.color = '#666';
            return;
        }

        let currentStock = getStock(khoId, productId);
        
        hintSpan.innerText = currentStock;
        inputQty.max = currentStock;

        if (currentStock <= 0) {
            hintSpan.style.color = 'red';
            hintSpan.innerText = "0 (Hết hàng tại kho này)";
            inputQty.value = 0;
            inputQty.disabled = true;
        } else {
            hintSpan.style.color = '#0f62fe';
            inputQty.disabled = false;
            if(inputQty.value == 0 || parseInt(inputQty.value) > currentStock) {
                inputQty.value = 1;
            }
        }
    }

    function addRow() {
        var table = document.getElementById("productTable").getElementsByTagName('tbody')[0];
        
        var optionsHtml = `
            <option value="">-- Tìm kiếm & chọn sản phẩm --</option>
            <?php foreach ($ds_san_pham as $sp): ?>
                <option value="<?php echo $sp['bien_the_id']; ?>">
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
                    <div class="stock-hint">Tồn kho tại kho này: <span class="stock-val">0</span></div>
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
        
        let newSelect = table.lastElementChild.querySelector('select');
        updateStockHint(newSelect);
    }

    function removeRow(btn) {
        var row = btn.closest('tr');
        var tbody = row.parentNode;
        if (tbody.querySelectorAll('tr').length > 1) row.remove();
        else showToast("Lỗi thao tác", "Phải có ít nhất 1 dòng sản phẩm!", "error");
    }

    function validateForm() {
        let isValid = true;
        let khoId = document.getElementById('warehouse_select').value;

        if(document.querySelectorAll('.item-row').length === 0) {
            showToast("Lỗi nhập liệu", "Vui lòng chọn sản phẩm cần xuất.", "error");
            return false;
        }

        document.querySelectorAll('.item-row').forEach(row => {
            let pid = row.querySelector('.product-select').value;
            let qty = parseInt(row.querySelector('.qty').value);
            let stock = getStock(khoId, pid);

            if(pid && qty > stock) {
                isValid = false;
                showToast("Lỗi tồn kho", 'Sản phẩm vượt quá số lượng tồn kho (' + stock + ')', "error");
                row.querySelector('.qty').style.border = "1px solid red";
            }
        });

        if(!isValid) return false;

        return confirm('CẢNH BÁO: Bạn đang thực hiện XUẤT KHO. Số lượng tồn kho sẽ bị trừ ngay lập tức. Bạn có chắc chắn không?');
    }
</script>