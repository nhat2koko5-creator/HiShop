<?php
require_once '../src/config.php';
require_once '../src/functions.php';

/* ==========================
    THÊM KHUYẾN MÃI
========================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ten'])) {
    
    $ten = trim($_POST['ten']);
    $ma_code = strtoupper(trim($_POST['ma_code']));
    $mo_ta = $_POST['mo_ta'];
    $loai_khuyen_mai = $_POST['loai_khuyen_mai'];
    $gia_tri = floatval($_POST['gia_tri']);
    $ngay_bat_dau = $_POST['ngay_bat_dau'];
    $ngay_ket_thuc = $_POST['ngay_ket_thuc'];
    $dieu_kien = $_POST['dieu_kien'];

    try {
        /* ---- LƯU KHUYẾN MÃI ---- */
        $stmt = $pdo->prepare("
            INSERT INTO ma_khuyen_mai (ten, ma_code, mo_ta, loai_khuyen_mai, gia_tri, ngay_bat_dau, ngay_ket_thuc, dieu_kien, trang_thai)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
        ");
        $stmt->execute([$ten, $ma_code, $mo_ta, $loai_khuyen_mai, $gia_tri, $ngay_bat_dau, $ngay_ket_thuc, $dieu_kien]);

        header("Location: index.php?page=discount_list&added=1");
        exit;
    } catch (PDOException $e) {
        header("Location: index.php?page=discount_list&error=" . urlencode("Lỗi: Mã code có thể đã bị trùng."));
        exit;
    }
}


/* ==========================
    SỬA KHUYẾN MÃI
========================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'])) {

    $id = intval($_POST['edit_id']);
    $ten = trim($_POST['edit_ten']);
    $ma_code = strtoupper(trim($_POST['edit_ma_code']));
    $mo_ta = $_POST['edit_mo_ta'];
    $loai_khuyen_mai = $_POST['edit_loai_khuyen_mai'];
    $gia_tri = floatval($_POST['edit_gia_tri']);
    $ngay_bat_dau = $_POST['edit_ngay_bat_dau'];
    $ngay_ket_thuc = $_POST['edit_ngay_ket_thuc'];
    $dieu_kien = $_POST['edit_dieu_kien'];
    
    try {
        // UPDATE KHUYẾN MÃI
        $stmt = $pdo->prepare("
            UPDATE ma_khuyen_mai
            SET ten=?, ma_code=?, mo_ta=?, loai_khuyen_mai=?, gia_tri=?, ngay_bat_dau=?, ngay_ket_thuc=?, dieu_kien=?
            WHERE id=?
        ");
        $stmt->execute([$ten, $ma_code, $mo_ta, $loai_khuyen_mai, $gia_tri, $ngay_bat_dau, $ngay_ket_thuc, $dieu_kien, $id]);

        ob_clean(); 
        header("Location: index.php?page=discount_list&updated=1");
        exit;

    } catch (PDOException $e) {
        header("Location: index.php?page=discount_list&error=" . urlencode("Lỗi: Mã code có thể đã bị trùng."));
        exit;
    }
}


/* ==========================
    ẨN / HIỆN KHUYẾN MÃI
========================== */
if (isset($_GET['toggle'])) {
    $id = intval($_GET['toggle']);

    $stmt = $pdo->prepare("SELECT trang_thai FROM ma_khuyen_mai WHERE id = ?");
    $stmt->execute([$id]);
    $status = $stmt->fetchColumn();

    $newStatus = ($status == 1 ? 0 : 1);

    $update = $pdo->prepare("UPDATE ma_khuyen_mai SET trang_thai = ? WHERE id = ?");
    $update->execute([$newStatus, $id]);

    header("Location: index.php?page=discount_list&toggled=1");
    exit;
}

/* ==========================
    LOAD KHUYẾN MÃI (ĐÃ CHỈNH SỬA LỌC TRẠNG THÁI)
========================== */
// --- CẤU HÌNH ---
$current_tab = $_GET['status'] ?? 'all';
$keyword = $_GET['keyword'] ?? '';
$current_time = date('Y-m-d H:i:s'); // Lấy thời gian hiện tại

// 1. Xây dựng câu Query cơ bản
$sql_base = "FROM ma_khuyen_mai WHERE 1=1";
$params = [];

// Thêm điều kiện tìm kiếm
if (!empty($keyword)) {
    $sql_base .= " AND (ten LIKE ? OR ma_code LIKE ?)";
    $params[] = "%$keyword%";
    $params[] = "%$keyword%";
}

// Thêm điều kiện lọc trạng thái theo ngày/trạng thái
if ($current_tab === 'active') {
    // Đang hoạt động: trang_thai = 1 VÀ ngày hiện tại nằm giữa ngày bắt đầu và ngày kết thúc
    $sql_base .= " AND trang_thai = 1 AND ngay_bat_dau <= ? AND ngay_ket_thuc >= ?";
    $params[] = $current_time;
    $params[] = $current_time;
} elseif ($current_tab === 'expired') {
    // Đã hết hạn: ngày kết thúc < ngày hiện tại
    $sql_base .= " AND ngay_ket_thuc < ?";
    $params[] = $current_time;
} elseif ($current_tab === 'upcoming') {
    // Sắp hoạt động: trang_thai = 1 VÀ ngày bắt đầu > ngày hiện tại
    $sql_base .= " AND trang_thai = 1 AND ngay_bat_dau > ?";
    $params[] = $current_time;
} elseif ($current_tab === 'inactive') {
    // Chỉ lọc các mã đã bị admin ẩn (trang_thai = 0)
    $sql_base .= " AND trang_thai = 0";
}


$sql_final = "SELECT * $sql_base ORDER BY id DESC";
$stmt = $pdo->prepare($sql_final);
$stmt->execute($params);
$discounts = $stmt->fetchAll();

// Helper function để hiển thị trạng thái theo thời gian
function getDiscountStatus($discount) {
    $current_time = date('Y-m-d H:i:s');
    $start = $discount['ngay_bat_dau'];
    $end = $discount['ngay_ket_thuc'];
    $is_active = $discount['trang_thai'] == 1;

    // Trạng thái Admin (Đang ẩn) được ưu tiên
    if (!$is_active) {
        // Dùng badge-secondary cho Đang ẩn
        return ['text' => 'Đang ẩn', 'class' => 'badge-secondary']; 
    }

    // Trạng thái theo thời gian
    if ($current_time < $start) {
        // Dùng badge-info cho Sắp hoạt động (Sẽ được CSS là màu xám)
        return ['text' => 'Sắp hoạt động', 'class' => 'badge-info'];
    } elseif ($current_time >= $start && $current_time <= $end) {
        // Dùng badge-success cho Đang hoạt động (Sẽ được CSS là màu xanh lá)
        return ['text' => 'Đang hoạt động', 'class' => 'badge-success'];
    } elseif ($current_time > $end) {
        // Dùng badge-danger cho Đã hết hạn (Sẽ được CSS là màu đỏ)
        return ['text' => 'Đã hết hạn', 'class' => 'badge-danger'];
    }
    return ['text' => 'Lỗi trạng thái', 'class' => 'badge-secondary'];
}

// Hàm trợ giúp để tạo URL trạng thái khuyến mãi và giữ lại từ khóa tìm kiếm
function getStatusUrl($status, $keyword) {
    $url = "index.php?page=discount_list&status=$status";
    if (!empty($keyword)) {
        // Sử dụng urlencode để đảm bảo keyword không gây lỗi URL
        $url .= "&keyword=" . urlencode($keyword);
    }
    return $url;
}


// Hàm tạo link phân trang (giữ nguyên để đảm bảo cấu trúc nhất quán)
function getPageUrl($page) {
    $params = $_GET;
    $params['p'] = $page;
    return 'index.php?' . http_build_query($params);
}
?>
<?php require_once 'layouts/header.php'; ?>

<div class="admin-page discount-list-page">
<h1 class="title">QUẢN LÝ KHUYẾN MÃI</h1>

<div class="filter-toolbar">
    <div class="add-action-wrapper">
        <button class="btn-add" onclick="openModal()">+ Thêm mã khuyến mãi</button>
    </div>

    <div class="status-tabs-wrapper">
        <div class="status-tabs">
            <a href="<?= getStatusUrl('all', $keyword) ?>" class="tab-btn <?= $current_tab=='all'?'active':'' ?>">Tất cả</a>
            <a href="<?= getStatusUrl('active', $keyword) ?>" class="tab-btn <?= $current_tab=='active'?'active':'' ?>">Đang hoạt động</a>
            <a href="<?= getStatusUrl('upcoming', $keyword) ?>" class="tab-btn <?= $current_tab=='upcoming'?'active':'' ?>">Sắp hoạt động</a>
            <a href="<?= getStatusUrl('expired', $keyword) ?>" class="tab-btn <?= $current_tab=='expired'?'active':'' ?>">Đã hết hạn</a>
            <a href="<?= getStatusUrl('inactive', $keyword) ?>" class="tab-btn <?= $current_tab=='inactive'?'active':'' ?>">Đã ẩn (Admin)</a>
        </div>
    </div>

    <div class="search-wrapper">
        <form method="GET" class="search-form-flex" action="index.php">
            <input type="hidden" name="page" value="discount_list">
            <input type="hidden" name="status" value="<?= $current_tab ?>">
            <input type="text" 
                   class="search-input" 
                   name="keyword"
                   placeholder="Tìm kiếm theo Tên hoặc Mã code..."
                   value="<?= htmlspecialchars($keyword) ?>">
            <button class="search-btn">
                <i class="fa-solid fa-search"></i>
            </button>
        </form>
    </div>
</div>
<?php if (!empty($_GET['added'])): ?>
    <div class="alert alert-success">Đã thêm mã khuyến mãi thành công.</div>
<?php endif; ?>

<?php if (!empty($_GET['updated'])): ?>
    <div class="alert alert-success">Đã cập nhật mã khuyến mãi thành công.</div>
<?php endif; ?>

<?php if (!empty($_GET['toggled'])): ?>
    <div class="alert alert-success">Đã thay đổi trạng thái mã khuyến mãi.</div>
<?php endif; ?>

<?php if (!empty($_GET['error'])): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($_GET['error']) ?></div>
<?php endif; ?>

<div class="card responsive-table-container">
    <div class="card-body p-0">
        <table class="table table-hover mb-0 table-list">
            <thead class="table-header">
                <tr>
                    <th width="4%">ID</th>
                    <th width="15%">Tên</th>
                    <th width="7%">Mã Code</th>
                    <th width="10%">Giá trị</th>
                    <th width="10%">Ngày Bắt Đầu</th> 
                    <th width="10%">Ngày Kết Thúc</th>
                    <th width="15%">Điều kiện</th>
                    <th width="10%">Trạng thái</th>
                    <th width="12%" class="text-end">Thao tác</th>
                </tr>
            </thead>

            <tbody>
                <?php if (count($discounts) == 0): ?>
                    <tr>
                        <td colspan="9" class="text-center p-4 text-muted">Không có mã khuyến mãi nào.</td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($discounts as $d): 
                    // Định dạng giá trị
                    $value_display = number_format($d['gia_tri']);
                    if (strpos(strtolower($d['loai_khuyen_mai']), 'phan_tram') !== false) {
                        $value_display .= '%';
                    } else {
                         $value_display .= '₫';
                    }

                    // LẤY TRẠNG THÁI MỚI (theo logic thời gian thực)
                    $status_info = getDiscountStatus($d);
                    
                    // Lô-gic hiển thị Trạng thái (quay lại hiển thị theo thời gian như yêu cầu)
                    $display_status_text = $status_info['text'];
                    $display_status_class = $status_info['class']; 
                ?>

    <tr class="table-row-data">
        <td data-label="ID" class="text-center"><?= $d['id'] ?></td>
        <td data-label="Tên khuyến mãi" class="col-name"><?= htmlspecialchars($d['ten']) ?></td>
        <td data-label="Mã Code"><b><?= htmlspecialchars($d['ma_code']) ?></b></td>
        <td data-label="Giá trị" class="col-value"><?= $value_display ?></td>
        
        <td data-label="Ngày Bắt Đầu" class="col-date">
            <?= date('d/m/Y H:i', strtotime($d['ngay_bat_dau'])) ?>
        </td>
        
        <td data-label="Ngày Kết Thúc" class="col-date">
            <?= date('d/m/Y H:i', strtotime($d['ngay_ket_thuc'])) ?>
        </td>
        
        <td data-label="Điều kiện" class="col-condition"><?= htmlspecialchars($d['dieu_kien'] ?? 'Không') ?></td>
        
        <td data-label="Trạng thái">
            <span class="badge status-badge <?= $display_status_class ?>">
                <?= $display_status_text ?>
            </span>
        </td>

        <td class="text-end col-actions" data-label="Thao tác">
            <a href="#"
               class="action-btn edit-btn-new"
               onclick="openEditModal(
                                <?= $d['id'] ?>,
                                '<?= htmlspecialchars($d['ten'], ENT_QUOTES) ?>',
                                '<?= htmlspecialchars($d['ma_code'], ENT_QUOTES) ?>',
                                '<?= htmlspecialchars($d['mo_ta'], ENT_QUOTES) ?>',
                                '<?= $d['loai_khuyen_mai'] ?>',
                                '<?= $d['gia_tri'] ?>',
                                '<?= date('Y-m-d\TH:i', strtotime($d['ngay_bat_dau'])) ?>',
                                '<?= date('Y-m-d\TH:i', strtotime($d['ngay_ket_thuc'])) ?>',
                                '<?= htmlspecialchars($d['dieu_kien'], ENT_QUOTES) ?>'
                            )">
                <i class="fa-solid fa-pencil"></i> Sửa
            </a>

            <a href="index.php?page=discount_list&toggle=<?= $d['id'] ?>" class="action-btn toggle-btn-new <?= $d['trang_thai'] == 1 ? 'btn-hide' : 'btn-show' ?>">
                <?php if ($d['trang_thai'] == 1): ?>
                    <i class="fa-solid fa-eye-slash"></i> Ẩn
                <?php else: ?>
                    <i class="fa-solid fa-eye"></i> Hiện
                <?php endif; ?>
            </a>

        </td>
    </tr>

<?php endforeach; ?>

            </tbody>

        </table>
    </div>
</div>

<div id="modalAdd" class="modal-overlay" style="display:none;">
    <div class="modal-box">
        <h3>Thêm mã khuyến mãi mới</h3>

        <form method="post">

            <label>Tên khuyến mãi:</label>
            <input type="text" name="ten" required>
            
            <label>Mã giảm giá (Code):</label>
            <input type="text" name="ma_code" required placeholder="VD: TET2025">
            <small class="form-text text-muted">Mã code phải là duy nhất.</small>

            <label>Mô tả:</label>
            <textarea name="mo_ta"></textarea>

            <label>Loại khuyến mãi:</label>
            <select name="loai_khuyen_mai" required>
                <option value="phan_tram">Phần trăm (%)</option>
                <option value="tien_mat">Tiền mặt (VNĐ)</option>
                <option value="mien_phi_ship">Miễn phí Ship</option>
            </select>

            <label>Giá trị giảm (số):</label>
            <input type="number" name="gia_tri" min="0" step="0.01" required>

            <label>Ngày bắt đầu:</label>
            <input type="datetime-local" name="ngay_bat_dau" required>

            <label>Ngày kết thúc:</label>
            <input type="datetime-local" name="ngay_ket_thuc" required>

            <label>Điều kiện áp dụng:</label>
            <input type="text" name="dieu_kien" placeholder="VD: Đơn hàng từ 500.000đ">

            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeModal()">Hủy</button>
                <button type="submit" class="btn-save">Lưu khuyến mãi</button>
            </div>
        </form>
    </div>
</div>

<div id="modalEdit" class="modal-overlay" style="display:none;">
    <div class="modal-box">
        <h3>Sửa mã khuyến mãi</h3>

        <form method="post">
            <input type="hidden" name="edit_id" id="edit_id">

            <label>Tên khuyến mãi:</label>
            <input type="text" name="edit_ten" id="edit_ten" required>
            
            <label>Mã giảm giá (Code):</label>
            <input type="text" name="edit_ma_code" id="edit_ma_code" required>

            <label>Mô tả:</label>
            <textarea name="edit_mo_ta" id="edit_mo_ta"></textarea>

            <label>Loại khuyến mãi:</label>
            <select name="edit_loai_khuyen_mai" id="edit_loai_khuyen_mai" required>
                <option value="phan_tram">Phần trăm (%)</option>
                <option value="tien_mat">Tiền mặt (VNĐ)</option>
                <option value="mien_phi_ship">Miễn phí Ship</option>
            </select>

            <label>Giá trị giảm (số):</label>
            <input type="number" name="edit_gia_tri" id="edit_gia_tri" min="0" step="0.01" required>

            <label>Ngày bắt đầu:</label>
            <input type="datetime-local" name="edit_ngay_bat_dau" id="edit_ngay_bat_dau" required>

            <label>Ngày kết thúc:</label>
            <input type="datetime-local" name="edit_ngay_ket_thuc" id="edit_ngay_ket_thuc" required>

            <label>Điều kiện áp dụng:</label>
            <input type="text" name="edit_dieu_kien" id="edit_dieu_kien" placeholder="VD: Đơn hàng từ 500.000đ">


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

function openEditModal(id, ten, ma_code, mo_ta, loai_khuyen_mai, gia_tri, start, end, dieu_kien){
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_ten').value = ten;
    document.getElementById('edit_ma_code').value = ma_code;
    document.getElementById('edit_mo_ta').value = mo_ta;
    document.getElementById('edit_loai_khuyen_mai').value = loai_khuyen_mai;
    document.getElementById('edit_gia_tri').value = gia_tri;
    document.getElementById('edit_ngay_bat_dau').value = start;
    document.getElementById('edit_ngay_ket_thuc').value = end;
    document.getElementById('edit_dieu_kien').value = dieu_kien;

    document.getElementById('modalEdit').style.display = 'flex';
}

function closeEditModal(){
    document.getElementById('modalEdit').style.display = 'none';
}
</script>
<link rel="stylesheet" href="/HiShop/assets/css/admin/promos_list.css">