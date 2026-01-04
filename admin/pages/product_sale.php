<?php
// FILE: admin/pages/product_sale.php

require_once '../src/config.php';
require_once '../src/functions.php';

// =================================================================
// 1. XỬ LÝ LƯU DỮ LIỆU (THÊM / SỬA)
// =================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // [QUAN TRỌNG] Bắt đầu transaction ngay lập tức
        $pdo->beginTransaction();

        $id = $_POST['id'] ?? '';
        $ten = trim($_POST['ten']);
        $loai = $_POST['loai_giam_gia'];
        
        if ($loai == 'percent') {   
            $gia_tri = str_replace(',', '.', $_POST['gia_tri']);
        } else {
            $gia_tri = str_replace(['.', ','], '', $_POST['gia_tri']);
        }
        $start = $_POST['ngay_bat_dau'];
        $end = $_POST['ngay_ket_thuc'];
        $product_ids = isset($_POST['products']) ? $_POST['products'] : [];

        // --- VALIDATE DỮ LIỆU ---
        if (empty($ten)) throw new Exception("Tên chương trình không được để trống.");
        if ($gia_tri <= 0) throw new Exception("Giá trị giảm phải lớn hơn 0.");
        if ($loai == 'amount' && !empty($product_ids)) {
            foreach ($product_ids as $pid) {
                // Lấy giá hiện tại của sản phẩm
                $stmtCheck = $pdo->prepare("SELECT ten, gia FROM san_pham WHERE id = ?");
                $stmtCheck->execute([$pid]);
                $prod = $stmtCheck->fetch();

                if ($prod) {
                    $max_discount = $prod['gia'] * 0.5; // Giới hạn 50%
                    if ($gia_tri > $max_discount) {
                        throw new Exception("Mức giảm ".number_format($gia_tri)."đ quá lớn so với sản phẩm '{$prod['ten']}' (Giá: ".number_format($prod['gia'])."đ). Không được giảm quá 50%!");
                    }
                }
            }
        }
        // -----------------------

        if (!empty($id)) {
            // UPDATE
            $stmt = $pdo->prepare("UPDATE giam_gia SET ten=?, loai_giam_gia=?, gia_tri=?, ngay_bat_dau=?, ngay_ket_thuc=? WHERE id=?");
            $stmt->execute([$ten, $loai, $gia_tri, $start, $end, $id]);
            $promo_id = $id;
        } else {
            // INSERT
            $stmt = $pdo->prepare("INSERT INTO giam_gia (ten, loai_giam_gia, gia_tri, ngay_bat_dau, ngay_ket_thuc) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$ten, $loai, $gia_tri, $start, $end]);
            $promo_id = $pdo->lastInsertId();
        }

        // Cập nhật sản phẩm áp dụng
        $pdo->prepare("DELETE FROM san_pham_giam_gia WHERE giam_gia_id = ?")->execute([$promo_id]);
        
        if (!empty($product_ids)) {
            $sqlLink = "INSERT INTO san_pham_giam_gia (giam_gia_id, san_pham_id) VALUES (?, ?)";
            $stmtLink = $pdo->prepare($sqlLink);
            foreach ($product_ids as $pid) {
                $stmtLink->execute([$promo_id, $pid]);
            }
        }

        $pdo->commit();
        
        // [MỚI] Sử dụng Session để thông báo thay vì alert()
        $_SESSION['notification'] = [
            'type' => 'success',
            'message' => 'Lưu chương trình thành công!'
        ];
        echo "<script>window.location.href='index.php?page=product_sale';</script>";
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        // [MỚI] Thông báo lỗi qua Session
        $_SESSION['notification'] = [
            'type' => 'error',
            'message' => $e->getMessage()
        ];
        echo "<script>window.location.href='index.php?page=product_sale';</script>";
        exit;
    }
}

// 2. XỬ LÝ XÓA
if (isset($_GET['delete'])) {
    try {
        $del_id = $_GET['delete'];
        $pdo->prepare("DELETE FROM giam_gia WHERE id = ?")->execute([$del_id]);
        
        $_SESSION['notification'] = [
            'type' => 'success',
            'message' => 'Đã xóa chương trình!'
        ];
    } catch (Exception $e) {
        $_SESSION['notification'] = [
            'type' => 'error',
            'message' => 'Lỗi xóa: ' . $e->getMessage()
        ];
    }
    echo "<script>window.location.href='index.php?page=product_sale';</script>";
    exit;
}

// =================================================================
// 3. LẤY DỮ LIỆU & BỘ LỌC
// =================================================================
$current_tab = $_GET['tab'] ?? 'all';
$keyword = $_GET['q'] ?? '';

$sqlBase = "SELECT g.*, (SELECT COUNT(*) FROM san_pham_giam_gia WHERE giam_gia_id = g.id) as so_luong_sp FROM giam_gia g WHERE 1=1";
$params = [];

if (!empty($keyword)) {
    $sqlBase .= " AND g.ten LIKE ?";
    $params[] = "%$keyword%";
}

$now = date('Y-m-d H:i:s');
if ($current_tab == 'running') {
    $sqlBase .= " AND g.ngay_bat_dau <= '$now' AND g.ngay_ket_thuc >= '$now'";
} elseif ($current_tab == 'upcoming') {
    $sqlBase .= " AND g.ngay_bat_dau > '$now'";
} elseif ($current_tab == 'expired') {
    $sqlBase .= " AND g.ngay_ket_thuc < '$now'";
}

$sqlBase .= " ORDER BY g.id DESC";

$stmt = $pdo->prepare($sqlBase);
$stmt->execute($params);
$promos = $stmt->fetchAll();

$stat_total = $pdo->query("SELECT COUNT(*) FROM giam_gia")->fetchColumn();
$stat_running = $pdo->query("SELECT COUNT(*) FROM giam_gia WHERE ngay_bat_dau <= '$now' AND ngay_ket_thuc >= '$now'")->fetchColumn();
$stat_expired = $pdo->query("SELECT COUNT(*) FROM giam_gia WHERE ngay_ket_thuc < '$now'")->fetchColumn();

$all_products = $pdo->query("SELECT id, ten, gia FROM san_pham WHERE trang_thai = 1 ORDER BY ten ASC")->fetchAll();
$map_applied = [];
$rows = $pdo->query("SELECT * FROM san_pham_giam_gia")->fetchAll();
foreach ($rows as $r) { $map_applied[$r['giam_gia_id']][] = $r['san_pham_id']; }
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<link rel="stylesheet" href="../assets/css/admin/product_sale.css">

<div class="ps-container">
    
    <div class="page-header-title">
      <i class="fas fa-box-open"></i></i> Quản lý Sản Phẩm Giảm Giá
    </div>

    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon bg-blue"><i class="fa-solid fa-tags"></i></div>
            <div class="stat-info"><h4><?= $stat_total ?></h4><p>Tổng chương trình</p></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon bg-green"><i class="fa-solid fa-bolt"></i></div>
            <div class="stat-info"><h4><?= $stat_running ?></h4><p>Đang hoạt động</p></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon bg-gray"><i class="fa-solid fa-clock-rotate-left"></i></div>
            <div class="stat-info"><h4><?= $stat_expired ?></h4><p>Đã kết thúc</p></div>
        </div>
    </div>

    <div class="main-box">
        
        <div class="toolbar">
            <button class="btn-create" onclick="openModal()">
                <i class="fa-solid fa-plus"></i> Tạo chương trình mới
            </button>

            <div class="filter-tabs">
                <a href="index.php?page=product_sale&tab=all" class="tab-link <?= $current_tab=='all'?'active':'' ?>">Tất cả</a>
                <a href="index.php?page=product_sale&tab=running" class="tab-link <?= $current_tab=='running'?'active':'' ?>">Đang chạy</a>
                <a href="index.php?page=product_sale&tab=upcoming" class="tab-link <?= $current_tab=='upcoming'?'active':'' ?>">Sắp tới</a>
                <a href="index.php?page=product_sale&tab=expired" class="tab-link <?= $current_tab=='expired'?'active':'' ?>">Đã kết thúc</a>
            </div>

            <form class="search-box" method="GET">
                <input type="hidden" name="page" value="product_sale">
                <input type="hidden" name="tab" value="<?= $current_tab ?>">
                <i class="fa-solid fa-magnifying-glass search-icon-i"></i>
                <input type="text" name="q" class="search-input" placeholder="Tìm tên chương trình..." value="<?= htmlspecialchars($keyword) ?>">
            </form>
        </div>

        <table class="table-custom">
            <thead>
                <tr>
                    <th width="50">ID</th>
                    <th>Tên Chương Trình</th>
                    <th>Mức Giảm</th>
                    <th>Thời Gian</th>
                    <th class="text-center">Phạm Vi</th>
                    <th class="text-center">Trạng Thái</th>
                    <th class="text-right">Hành Động</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($promos)): ?>
                    <tr><td colspan="7" class="text-center" style="padding: 40px; color: #94a3b8;">Không tìm thấy dữ liệu.</td></tr>
                <?php else: ?>
                    <?php foreach ($promos as $p): 
                    date_default_timezone_set('Asia/Ho_Chi_Minh');
                        $now = date('Y-m-d H:i:s');
                        $sttClass = 'st-running'; $sttText = 'Đang chạy';
                        if ($p['ngay_ket_thuc'] < $now) { $sttClass = 'st-expired'; $sttText = 'Đã kết thúc'; }
                        elseif ($p['ngay_bat_dau'] > $now) { $sttClass = 'st-upcoming'; $sttText = 'Sắp diễn ra'; }
                    ?>
                    <tr>
                        <td>#<?= $p['id'] ?></td>
                        <td>
                            <div style="font-weight: 700; color: #1e293b;"><?= htmlspecialchars($p['ten']) ?></div>
                        </td>
                        <td>
                            <span style="font-weight: 700; color: #e11d48;">

                            <?php if($p['loai_giam_gia'] == 'percent'): ?>
                                -<?= (float)$p['gia_tri'] ?>%
                            <?php else: ?>
                                -<?= number_format($p['gia_tri'], 0, ',', '.') ?>đ
                            <?php endif; ?>
 
                            </span>
                        </td>
                        <td style="font-size: 13px; color: #64748b;">
                            <div>Từ: <?= date('d/m/Y H:i', strtotime($p['ngay_bat_dau'])) ?></div>
                            <div>Đến: <?= date('d/m/Y H:i', strtotime($p['ngay_ket_thuc'])) ?></div>
                        </td>
                        <td class="text-center">
                            <span style="background: #f1f5f9; padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 600; color: #475569;">
                                <?= $p['so_luong_sp'] ?> sản phẩm
                            </span>
                        </td>
                        <td class="text-center">
                            <span class="status-badge <?= $sttClass ?>"><?= $sttText ?></span>
                        </td>
                        <td class="text-right">
                            <div style="display:flex; justify-content:flex-end;">
                                <button class="btn-action" onclick='editPromo(<?= json_encode($p) ?>)'>
                                    <i class="fa-solid fa-pen-to-square"></i> Sửa
                                </button>
                                
                                <a href="#" class="btn-action delete" onclick="confirmDelete(<?= $p['id'] ?>)">
                                    <i class="fa-solid fa-trash"></i> Xóa
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="promoModal" class="admin-modal" style="display: none; position: fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; justify-content:center; align-items:center;">
    <div class="admin-modal-content" style="background:#fff; width: 600px; max-width:90%; padding:28px; border-radius:16px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); max-height: 90vh; overflow-y: auto;">
        <h3 id="modalTitle" style="margin-top:0; margin-bottom: 24px; font-size: 20px; font-weight: 800; color: #1e293b;">Thêm Chương Trình Mới</h3>
        
        <form method="POST" id="promoForm" onsubmit="return validateForm()">
            <input type="hidden" name="id" id="promoId">
            
            <div class="form-group" style="margin-bottom: 20px;">
                <label style="display:block; font-weight:600; margin-bottom:8px; font-size:13px; color:#475569;">Tên chương trình <span style="color:red">*</span></label>
                <input type="text" name="ten" id="promoName" class="form-control" required placeholder="VD: Sale Giáng Sinh 2025" style="width:100%; padding:12px; border:1px solid #cbd5e1; border-radius:8px; outline:none;">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                <div>
                    <label style="display:block; font-weight:600; margin-bottom:8px; font-size:13px; color:#475569;">Loại giảm giá</label>
                    <select name="loai_giam_gia" id="promoType" class="form-control" style="width:100%; padding:12px; border:1px solid #cbd5e1; border-radius:8px;">
                        <option value="percent">Giảm theo %</option>
                        <option value="amount">Giảm tiền mặt (VNĐ)</option>
                    </select>
                </div>
                <div>
                    <label style="display:block; font-weight:600; margin-bottom:8px; font-size:13px; color:#475569;">Giá trị <span style="color:red">*</span></label>
                    <input type="number" name="gia_tri" id="promoValue" class="form-control" required placeholder="VD: 20" style="width:100%; padding:12px; border:1px solid #cbd5e1; border-radius:8px;">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                <div>
                    <label style="display:block; font-weight:600; margin-bottom:8px; font-size:13px; color:#475569;">Ngày bắt đầu <span style="color:red">*</span></label>
                    <input type="datetime-local" name="ngay_bat_dau" id="promoStart" required style="width:100%; padding:12px; border:1px solid #cbd5e1; border-radius:8px;">
                </div>
                <div>
                    <label style="display:block; font-weight:600; margin-bottom:8px; font-size:13px; color:#475569;">Ngày kết thúc <span style="color:red">*</span></label>
                    <input type="datetime-local" name="ngay_ket_thuc" id="promoEnd" required style="width:100%; padding:12px; border:1px solid #cbd5e1; border-radius:8px;">
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 30px;">
                <label style="display:block; font-weight:600; margin-bottom:8px; font-size:13px; color:#475569;">Sản phẩm áp dụng</label>
                <select name="products[]" id="promoProducts" class="form-control select2-multi" multiple="multiple" style="width: 100%;">
                    <?php foreach ($all_products as $prod): ?>
                        <option value="<?= $prod['id'] ?>" data-price="<?= $prod['gia'] ?>">
                            <?= htmlspecialchars($prod['ten']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="text-align: right; display: flex; justify-content: flex-end; gap: 12px;">
                <button type="button" onclick="closeModal()" style="padding: 12px 24px; border: 1px solid #cbd5e1; background: #fff; border-radius: 8px; font-weight: 600; cursor: pointer; color: #475569;">Hủy</button>
                <button type="submit" style="padding: 12px 24px; border: none; background: #4f46e5; color: #fff; border-radius: 8px; cursor: pointer; font-weight: 600;">Lưu chương trình</button>
            </div>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    const mapApplied = <?= json_encode($map_applied) ?>;

    $(document).ready(function() {
        $('.select2-multi').select2({
            placeholder: "Tìm kiếm sản phẩm...",
            allowClear: true,
            dropdownParent: $('#promoModal')
        });
    });

function validateForm() {
        const val = parseFloat($('#promoValue').val());
        const type = $('#promoType').val();
        const start = new Date($('#promoStart').val());
        const end = new Date($('#promoEnd').val());
        const products = $('#promoProducts').val(); // Mảng ID các sp đã chọn

        if (val <= 0) { 
            Swal.fire('Lỗi', 'Giá trị giảm phải lớn hơn 0!', 'error'); 
            return false; 
        }
        
        // Check %
        if (type === 'percent' && val > 99) { 
            Swal.fire('Lỗi', 'Giảm giá % không được quá 99%!', 'error'); 
            return false; 
        }

        // [MỚI] Check Tiền mặt > 50% giá sản phẩm
        if (type === 'amount' && products.length > 0) {
            // Lặp qua các option đã chọn để check giá
            let hasError = false;
            let errorMsg = "";

            $('#promoProducts option:selected').each(function() {
                let price = parseFloat($(this).attr('data-price')) || 0;
                let maxAllowed = price * 0.5; // 50%

                if (val > maxAllowed) {
                    hasError = true;
                    // Format tiền tệ cho dễ đọc
                    let moneyVal = new Intl.NumberFormat('vi-VN').format(val);
                    let moneyPrice = new Intl.NumberFormat('vi-VN').format(price);
                    errorMsg = `Mức giảm ${moneyVal}đ vượt quá 50% giá của sản phẩm "${$(this).text()}" (Giá: ${moneyPrice}đ)`;
                    return false; // Break vòng lặp
                }
            });

            if (hasError) {
                Swal.fire('Lỗi', errorMsg, 'error');
                return false;
            }
        }

        if (end <= start) { 
            Swal.fire('Lỗi', 'Ngày kết thúc phải sau ngày bắt đầu!', 'error'); 
            return false; 
        }
        if (!products || products.length === 0) { 
            Swal.fire('Lỗi', 'Vui lòng chọn ít nhất 1 sản phẩm!', 'error'); 
            return false; 
        }

        return true;
    }

    // Modal logic
    function openModal() {
        $('#promoId').val('');
        $('#promoName').val('');
        $('#promoValue').val('');
        $('#promoStart').val('');
        $('#promoEnd').val('');
        $('#promoProducts').val(null).trigger('change');
        $('#modalTitle').text('Thêm Chương Trình Mới');
        document.getElementById('promoModal').style.display = 'flex';
    }

    function editPromo(data) {
        $('#promoId').val(data.id);
        $('#promoName').val(data.ten);
        $('#promoType').val(data.loai_giam_gia);
        $('#promoValue').val(data.gia_tri);
        $('#promoStart').val(data.ngay_bat_dau.replace(' ', 'T'));
        $('#promoEnd').val(data.ngay_ket_thuc.replace(' ', 'T'));
        
        const selectedProducts = mapApplied[data.id] || [];
        $('#promoProducts').val(selectedProducts).trigger('change');

        $('#modalTitle').text('Cập Nhật Chương Trình');
        document.getElementById('promoModal').style.display = 'flex';
    }

    function closeModal() {
        document.getElementById('promoModal').style.display = 'none';
    }

    function confirmDelete(id) {
        Swal.fire({
            title: 'Bạn chắc chắn chứ?',
            text: "Chương trình sẽ bị xóa vĩnh viễn và giá sản phẩm sẽ quay về mức cũ!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Vâng, xóa nó!',
            cancelButtonText: 'Hủy'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'index.php?page=product_sale&delete=' + id;
            }
        })
    }
    
    window.onclick = function(event) {
        if (event.target == document.getElementById('promoModal')) { closeModal(); }
    }
</script>

<?php
// HIỂN THỊ THÔNG BÁO TỪ SESSION (SWEETALERT2)
if (isset($_SESSION['notification'])) {
    $type = $_SESSION['notification']['type'];
    $msg = $_SESSION['notification']['message'];
    unset($_SESSION['notification']); // Xóa ngay sau khi hiện
    echo "<script>
        Swal.fire({
            icon: '$type',
            title: '$msg',
            showConfirmButton: false,
            timer: 1500,
            toast: true,
            position: 'top-end'
        });
    </script>";
}
?>