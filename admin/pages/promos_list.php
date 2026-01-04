<?php
// FILE: admin/pages/promos_list.php
require_once '../src/config.php';
require_once '../src/functions.php';

// =================================================================
// 0. HÀM HỖ TRỢ (REDIRECT & URL)
// =================================================================
function js_redirect($url) {
    echo "<script>window.location.href='" . $url . "';</script>";
    exit;
}

function getStatusUrl($status, $keyword) {
    $url = "index.php?page=discount_list&status=$status";
    if (!empty($keyword)) {
        $url .= "&keyword=" . urlencode($keyword);
    }
    return $url;
}

// =================================================================
// 1. XỬ LÝ FORM SUBMIT (POST)
// =================================================================

// A. THÊM KHUYẾN MÃI MỚI
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_promo') {
    
    $ten = trim($_POST['ten']);
    $ma_code = strtoupper(trim($_POST['ma_code']));
    $mo_ta = $_POST['mo_ta'];
    $loai_khuyen_mai = $_POST['loai_khuyen_mai'];
    
    // Xử lý số tiền (Xóa dấu chấm/phẩy nếu có)
    $gia_tri = (float)str_replace([',','.'], '', $_POST['gia_tri']);
    $dieu_kien = (float)str_replace([',','.'], '', $_POST['dieu_kien']); 
    $so_luong = (int)str_replace([',','.'], '', $_POST['so_luong']); // [MỚI] Số lượng giới hạn
    
    $ngay_bat_dau = $_POST['ngay_bat_dau'];
    $ngay_ket_thuc = $_POST['ngay_ket_thuc'];
    
    // --- VALIDATE DỮ LIỆU ---
    if ($gia_tri < 0) {
        js_redirect("index.php?page=discount_list&error=" . urlencode("Giá trị giảm giá không được là số âm!"));
    }
    if ($dieu_kien < 0) {
        js_redirect("index.php?page=discount_list&error=" . urlencode("Đơn hàng tối thiểu không được âm!"));
    }
    if ($so_luong < 0) {
        js_redirect("index.php?page=discount_list&error=" . urlencode("Số lượng giới hạn không hợp lệ!"));
    }
    if ($loai_khuyen_mai == 'phan_tram' && $gia_tri > 100) {
        js_redirect("index.php?page=discount_list&error=" . urlencode("Giảm giá phần trăm không được quá 100%!"));
    }
    if (strtotime($ngay_bat_dau) > strtotime($ngay_ket_thuc)) {
        js_redirect("index.php?page=discount_list&error=" . urlencode("Ngày bắt đầu không được lớn hơn ngày kết thúc!"));
    }

    try {
        // Thêm cột so_luong và da_dung (mặc định 0)
        $stmt = $pdo->prepare("
            INSERT INTO ma_khuyen_mai (ten, ma_code, mo_ta, loai_khuyen_mai, gia_tri, ngay_bat_dau, ngay_ket_thuc, dieu_kien, so_luong, da_dung, trang_thai)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 1)
        ");
        $stmt->execute([$ten, $ma_code, $mo_ta, $loai_khuyen_mai, $gia_tri, $ngay_bat_dau, $ngay_ket_thuc, $dieu_kien, $so_luong]);
        
        js_redirect("index.php?page=discount_list&added=1");
    } catch (PDOException $e) {
        // Lỗi thường gặp là trùng mã code (Duplicate entry)
        js_redirect("index.php?page=discount_list&error=" . urlencode("Mã code '$ma_code' đã tồn tại!"));
    }
}

// B. SỬA KHUYẾN MÃI
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'edit_promo') {

    $id = intval($_POST['edit_id']);
    $ten = trim($_POST['edit_ten']);
    $ma_code = strtoupper(trim($_POST['edit_ma_code']));
    $mo_ta = $_POST['edit_mo_ta'];
    $loai_khuyen_mai = $_POST['edit_loai_khuyen_mai'];
    
    $gia_tri = (float)str_replace([',','.'], '', $_POST['edit_gia_tri']);
    $dieu_kien = (float)str_replace([',','.'], '', $_POST['edit_dieu_kien']);
    $so_luong = (int)str_replace([',','.'], '', $_POST['edit_so_luong']); // [MỚI]
    
    $ngay_bat_dau = $_POST['edit_ngay_bat_dau'];
    $ngay_ket_thuc = $_POST['edit_ngay_ket_thuc'];
    
    // --- VALIDATE ---
    if ($gia_tri < 0) js_redirect("index.php?page=discount_list&error=" . urlencode("Giá trị giảm giá lỗi!"));
    if ($dieu_kien < 0) js_redirect("index.php?page=discount_list&error=" . urlencode("Đơn tối thiểu lỗi!"));
    if ($so_luong < 0) js_redirect("index.php?page=discount_list&error=" . urlencode("Số lượng lỗi!"));
    if ($loai_khuyen_mai == 'phan_tram' && $gia_tri > 100) js_redirect("index.php?page=discount_list&error=" . urlencode("Giảm giá quá 100%!"));
    if (strtotime($ngay_bat_dau) > strtotime($ngay_ket_thuc)) js_redirect("index.php?page=discount_list&error=" . urlencode("Ngày kết thúc không hợp lệ!"));
    
    try {
        // Cập nhật thông tin, bao gồm số lượng
        $stmt = $pdo->prepare("
            UPDATE ma_khuyen_mai
            SET ten=?, ma_code=?, mo_ta=?, loai_khuyen_mai=?, gia_tri=?, ngay_bat_dau=?, ngay_ket_thuc=?, dieu_kien=?, so_luong=?
            WHERE id=?
        ");
        $stmt->execute([$ten, $ma_code, $mo_ta, $loai_khuyen_mai, $gia_tri, $ngay_bat_dau, $ngay_ket_thuc, $dieu_kien, $so_luong, $id]);

        // Giữ nguyên tab và từ khóa tìm kiếm sau khi sửa
        $current_tab = $_POST['current_tab'] ?? 'all';
        $keyword = $_POST['current_keyword'] ?? '';
        
        $url = "index.php?page=discount_list&updated=1&status=$current_tab";
        if($keyword) $url .= "&keyword=".urlencode($keyword);
        
        js_redirect($url);

    } catch (PDOException $e) {
        js_redirect("index.php?page=discount_list&error=" . urlencode("Lỗi cập nhật: " . $e->getMessage()));
    }
}

// =================================================================
// 2. XỬ LÝ ẨN/HIỆN (GET)
// =================================================================
if (isset($_GET['toggle'])) {
    $id = intval($_GET['toggle']);
    
    $stmt = $pdo->prepare("SELECT trang_thai FROM ma_khuyen_mai WHERE id = ?");
    $stmt->execute([$id]);
    $current = $stmt->fetchColumn();

    $newStatus = ($current == 1) ? 0 : 1;
    $update = $pdo->prepare("UPDATE ma_khuyen_mai SET trang_thai = ? WHERE id = ?");
    $update->execute([$newStatus, $id]);

    $current_tab = $_GET['status'] ?? 'all';
    $current_keyword = $_GET['keyword'] ?? '';
    
    $url = "index.php?page=discount_list&toggled=1&status_changed=$newStatus";
    if ($current_tab !== 'all') $url .= "&status=$current_tab";
    if (!empty($current_keyword)) $url .= "&keyword=" . urlencode($current_keyword);

    js_redirect($url);
}

// =================================================================
// 3. LẤY DỮ LIỆU HIỂN THỊ
// =================================================================
$current_tab = $_GET['status'] ?? 'all';
$keyword = $_GET['keyword'] ?? '';
$current_time = date('Y-m-d H:i:s');

// Xây dựng câu truy vấn lọc
$sql_base = "FROM ma_khuyen_mai WHERE 1=1";
$params = [];

if (!empty($keyword)) {
    $sql_base .= " AND (ten LIKE ? OR ma_code LIKE ?)";
    $params[] = "%$keyword%";
    $params[] = "%$keyword%";
}

if ($current_tab === 'active') {
    $sql_base .= " AND trang_thai = 1 AND ngay_bat_dau <= ? AND ngay_ket_thuc >= ?";
    $params[] = $current_time; $params[] = $current_time;
} elseif ($current_tab === 'expired') {
    $sql_base .= " AND ngay_ket_thuc < ?";
    $params[] = $current_time;
} elseif ($current_tab === 'upcoming') {
    $sql_base .= " AND trang_thai = 1 AND ngay_bat_dau > ?";
    $params[] = $current_time;
} elseif ($current_tab === 'inactive') {
    $sql_base .= " AND trang_thai = 0";
}

$sql_final = "SELECT * $sql_base ORDER BY id DESC";
$stmt = $pdo->prepare($sql_final);
$stmt->execute($params);
$discounts = $stmt->fetchAll();

// Thống kê nhanh
$stats = $pdo->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN trang_thai = 1 THEN 1 ELSE 0 END) as active,
    SUM(CASE WHEN trang_thai = 0 THEN 1 ELSE 0 END) as hidden
FROM ma_khuyen_mai")->fetch();

// Hàm hiển thị trạng thái badge
function getStatusLabel($d) {
    date_default_timezone_set('Asia/Ho_Chi_Minh'); 
    $now = date('Y-m-d H:i:s');
    
    // Ưu tiên kiểm tra hết lượt dùng
    if ($d['so_luong'] > 0 && $d['da_dung'] >= $d['so_luong']) {
        return ['text' => 'Hết lượt', 'class' => 'badge-dark']; 
    }
    
    if ($d['trang_thai'] == 0) return ['text' => 'Đang ẩn', 'class' => 'badge-secondary'];
    if ($now < $d['ngay_bat_dau']) return ['text' => 'Sắp diễn ra', 'class' => 'badge-info'];
    if ($now > $d['ngay_ket_thuc']) return ['text' => 'Đã hết hạn', 'class' => 'badge-danger'];
    
    return ['text' => 'Đang hoạt động', 'class' => 'badge-success'];
}
?>

<?php require_once 'layouts/header.php'; ?>

<link rel="stylesheet" href="../assets/css/admin/promos_list.css">

<div class="admin-page discount-list-page">
    <div class="page-header-title">
      <i class="fas fa-box-open"></i> Quản lý Khuyến Mãi
    </div>
    
    <div id="toast-container" class="toast-container"></div>

    <div class="stats-grid-container">
        <div class="stat-card stat-blue">
            <div class="stat-icon-wrapper"><i class="fa-solid fa-tags"></i></div>
            <div class="stat-content"><h3><?= $stats['total'] ?></h3><p>Tổng Mã</p></div>
        </div>
        <div class="stat-card stat-green">
            <div class="stat-icon-wrapper"><i class="fa-solid fa-eye"></i></div>
            <div class="stat-content"><h3><?= $stats['active'] ?></h3><p>Đang Hiện</p></div>
        </div>
        <div class="stat-card stat-gray">
            <div class="stat-icon-wrapper"><i class="fa-solid fa-eye-slash"></i></div>
            <div class="stat-content"><h3><?= $stats['hidden'] ?></h3><p>Đang Ẩn</p></div>
        </div>
    </div>
    
    <div class="filter-toolbar">
        <button class="btn-add" onclick="openModal()">+ Thêm mã khuyến mãi</button>

        <div class="status-tabs">
            <a href="<?= getStatusUrl('all', $keyword) ?>" class="tab-btn <?= $current_tab=='all'?'active':'' ?>">Tất cả</a>
            <a href="<?= getStatusUrl('active', $keyword) ?>" class="tab-btn <?= $current_tab=='active'?'active':'' ?>">Đang chạy</a>
            <a href="<?= getStatusUrl('upcoming', $keyword) ?>" class="tab-btn <?= $current_tab=='upcoming'?'active':'' ?>">Sắp tới</a>
            <a href="<?= getStatusUrl('expired', $keyword) ?>" class="tab-btn <?= $current_tab=='expired'?'active':'' ?>">Hết hạn</a>
            <a href="<?= getStatusUrl('inactive', $keyword) ?>" class="tab-btn <?= $current_tab=='inactive'?'active':'' ?>">Đã ẩn</a>
        </div>

        <div class="search-wrapper">
            <form method="GET" action="index.php">
                <input type="hidden" name="page" value="discount_list">
                <input type="hidden" name="status" value="<?= $current_tab ?>">
                <input type="text" class="search-input" name="keyword" placeholder="Tìm tên/mã code..." value="<?= htmlspecialchars($keyword) ?>">
                <button class="search-btn"><i class="fa-solid fa-search"></i></button>
            </form>
        </div>
    </div>

    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th width="50">ID</th>
                    <th>Tên</th>
                    <th>Mã Code</th>
                    <th>Giá trị</th>
                    <th>Lượt dùng</th> <th>Thời gian</th>
                    <th>Điều kiện (Min)</th>
                    <th>Trạng thái</th>
                    <th class="text-end">Hành động</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($discounts) == 0): ?>
                    <tr><td colspan="9" class="text-center p-4 text-muted">Không tìm thấy dữ liệu phù hợp.</td></tr>
                <?php endif; ?>

                <?php foreach ($discounts as $d): 
                    $val_display = number_format($d['gia_tri']) . ($d['loai_khuyen_mai']=='phan_tram' ? '%' : 'đ');
                    $st = getStatusLabel($d);
                    
                    // Hiển thị điều kiện
                    $min_order = (float)$d['dieu_kien'];
                    $cond_display = ($min_order > 0) ? "Đơn > " . number_format($min_order) . "đ" : "Không giới hạn";
                    
                    // Hiển thị lượt dùng [Logic Mới]
                    $limit_num = isset($d['so_luong']) ? (int)$d['so_luong'] : 0;
                    $used_num = isset($d['da_dung']) ? (int)$d['da_dung'] : 0;
                    
                    $limit_display = ($limit_num > 0) ? number_format($limit_num) : "∞";
                    $usage_text = number_format($used_num) . " / " . $limit_display;
                    
                    // Tô màu đỏ nếu sắp hết hoặc hết
                    $usage_color = ($limit_num > 0 && $used_num >= $limit_num) ? '#d70018' : '#333';
                    
                    $toggleUrl = "index.php?page=discount_list&toggle=" . $d['id'];
                    if (!empty($keyword)) $toggleUrl .= "&keyword=" . urlencode($keyword);
                    if ($current_tab != 'all') $toggleUrl .= "&status=" . $current_tab;
                ?>
                <tr>
                    <td class="text-center">#<?= $d['id'] ?></td>
                    <td><?= htmlspecialchars($d['ten']) ?></td>
                    <td><b style="color:#4e73df;"><?= htmlspecialchars($d['ma_code']) ?></b></td>
                    <td style="color:#d70018; font-weight:700;"><?= $val_display ?></td>
                    
                    <td style="color:<?= $usage_color ?>; font-weight:600;"><?= $usage_text ?></td>

                    <td>
                        <small>Từ: <?= date('d/m/Y H:i', strtotime($d['ngay_bat_dau'])) ?></small><br>
                        <small>Đến: <?= date('d/m/Y H:i', strtotime($d['ngay_ket_thuc'])) ?></small>
                    </td>
                    <td><small style="color:#64748b; font-weight:600;"><?= $cond_display ?></small></td>
                    <td><span class="badge <?= $st['class'] ?>"><?= $st['text'] ?></span></td>
                    <td class="text-end">
                        <button class="action-btn edit-btn-new" onclick="openEditModal(
                            <?= $d['id'] ?>,
                            '<?= htmlspecialchars($d['ten']) ?>',
                            '<?= htmlspecialchars($d['ma_code']) ?>',
                            '<?= htmlspecialchars($d['mo_ta']) ?>',
                            '<?= $d['loai_khuyen_mai'] ?>',
                            '<?= $d['gia_tri'] ?>',
                            '<?= date('Y-m-d\TH:i', strtotime($d['ngay_bat_dau'])) ?>',
                            '<?= date('Y-m-d\TH:i', strtotime($d['ngay_ket_thuc'])) ?>',
                            '<?= $min_order ?>',
                            '<?= $limit_num ?>' 
                        )"><i class="fa-solid fa-pencil"></i> Sửa</button>

                        <?php if ($d['trang_thai'] == 1): ?>
                            <a href="<?= $toggleUrl ?>" class="action-btn btn-warning-custom" title="Đang hiện, bấm để ẩn"><i class="fa-solid fa-eye-slash"></i> Ẩn</a>
                        <?php else: ?>
                            <a href="<?= $toggleUrl ?>" class="action-btn btn-dark-custom" title="Đang ẩn, bấm để hiện"><i class="fa-solid fa-eye"></i> Hiện</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="modalAdd" class="modal-overlay" style="display:none;">
    <div class="modal-box">
        <h3>Thêm mã khuyến mãi</h3>
        <form method="post">
            <input type="hidden" name="action" value="add_promo">
            <div style="display:flex; gap:15px;">
                <div style="flex:1;">
                    <label>Tên chương trình:</label>
                    <input type="text" name="ten" required>
                    
                    <label>Mã Code (Coupon):</label>
                    <input type="text" name="ma_code" required placeholder="VD: TET2025" style="text-transform:uppercase;">
                    
                    <label>Loại giảm giá:</label>
                    <select name="loai_khuyen_mai" required>
                        <option value="phan_tram">Phần trăm (%)</option>
                        <option value="tien_mat">Tiền mặt (VNĐ)</option>
                    </select>
                    
                    <label>Giá trị giảm:</label>
                    <input type="number" name="gia_tri" min="0" required placeholder="VD: 10 hoặc 50000">
                </div>
                
                <div style="flex:1;">
                    <label>Thời gian bắt đầu:</label>
                    <input type="datetime-local" name="ngay_bat_dau" required>
                    
                    <label>Thời gian kết thúc:</label>
                    <input type="datetime-local" name="ngay_ket_thuc" required>
                    
                    <label>Số lượng giới hạn (0 = Vô hạn):</label>
                    <input type="number" name="so_luong" min="0" value="0" placeholder="VD: 50">

                    <label>Đơn tối thiểu (VNĐ):</label>
                    <input type="number" name="dieu_kien" min="0" value="0" required placeholder="Nhập 0 nếu không giới hạn">
                    
                    <label>Mô tả ngắn:</label>
                    <textarea name="mo_ta" rows="1"></textarea>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="document.getElementById('modalAdd').style.display='none'">Hủy</button>
                <button type="submit" class="btn-save">Lưu lại</button>
            </div>
        </form>
    </div>
</div>

<div id="modalEdit" class="modal-overlay" style="display:none;">
    <div class="modal-box">
        <h3>Sửa mã khuyến mãi</h3>
        <form method="post">
            <input type="hidden" name="action" value="edit_promo">
            <input type="hidden" name="edit_id" id="edit_id">
            <input type="hidden" name="current_tab" value="<?= $current_tab ?>">
            <input type="hidden" name="current_keyword" value="<?= htmlspecialchars($keyword) ?>">

            <div style="display:flex; gap:15px;">
                <div style="flex:1;">
                    <label>Tên chương trình:</label>
                    <input type="text" name="edit_ten" id="edit_ten" required>
                    
                    <label>Mã Code:</label>
                    <input type="text" name="edit_ma_code" id="edit_ma_code" required style="text-transform:uppercase;">
                    
                    <label>Loại giảm giá:</label>
                    <select name="edit_loai_khuyen_mai" id="edit_loai_khuyen_mai" required>
                        <option value="phan_tram">Phần trăm (%)</option>
                        <option value="tien_mat">Tiền mặt (VNĐ)</option>
                    </select>
                    
                    <label>Giá trị giảm:</label>
                    <input type="number" name="edit_gia_tri" id="edit_gia_tri" min="0" required>
                </div>
                
                <div style="flex:1;">
                    <label>Bắt đầu:</label>
                    <input type="datetime-local" name="edit_ngay_bat_dau" id="edit_ngay_bat_dau" required>
                    
                    <label>Kết thúc:</label>
                    <input type="datetime-local" name="edit_ngay_ket_thuc" id="edit_ngay_ket_thuc" required>
                    
                    <label>Số lượng giới hạn (0 = Vô hạn):</label>
                    <input type="number" name="edit_so_luong" id="edit_so_luong" min="0">

                    <label>Đơn tối thiểu (VNĐ):</label>
                    <input type="number" name="edit_dieu_kien" id="edit_dieu_kien" min="0" required>
                    
                    <label>Mô tả:</label>
                    <textarea name="edit_mo_ta" id="edit_mo_ta" rows="1"></textarea>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="document.getElementById('modalEdit').style.display='none'">Hủy</button>
                <button type="submit" class="btn-save">Cập nhật</button>
            </div>
        </form>
    </div>
</div>

<script>
// Mở modal thêm mới
function openModal(){ 
    document.getElementById('modalAdd').style.display = 'flex'; 
}

// Mở modal chỉnh sửa & điền dữ liệu
function openEditModal(id, ten, code, desc, type, val, start, end, cond, qty){
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_ten').value = ten;
    document.getElementById('edit_ma_code').value = code;
    document.getElementById('edit_mo_ta').value = desc;
    document.getElementById('edit_loai_khuyen_mai').value = type;
    document.getElementById('edit_gia_tri').value = val;
    document.getElementById('edit_ngay_bat_dau').value = start;
    document.getElementById('edit_ngay_ket_thuc').value = end;
    
    // Điền số tiền tối thiểu & số lượng
    document.getElementById('edit_dieu_kien').value = cond; 
    document.getElementById('edit_so_luong').value = qty; 
    
    document.getElementById('modalEdit').style.display = 'flex';
}

// Đóng modal khi click ra ngoài
window.onclick = function(e){
    if(e.target.className === 'modal-overlay') {
        document.getElementById('modalAdd').style.display = 'none';
        document.getElementById('modalEdit').style.display = 'none';
    }
}

// Hiển thị thông báo (Toast)
function showToast(message, type = 'success') {
    const container = document.getElementById('toast-container');
    const toast = document.createElement('div');
    const icon = type === 'success' ? '<i class="fa-solid fa-circle-check"></i>' : '<i class="fa-solid fa-circle-exclamation"></i>';
    toast.className = `toast ${type}`;
    toast.innerHTML = `<div class="toast-content"><span class="icon">${icon}</span><span class="toast-message">${message}</span></div><span class="toast-close" onclick="this.parentElement.remove()">&times;</span>`;
    container.appendChild(toast);
    setTimeout(() => { toast.style.animation = 'fadeOut 0.3s ease forwards'; setTimeout(() => toast.remove(), 300); }, 3000);
}

// Xử lý thông báo từ URL sau khi redirect
document.addEventListener("DOMContentLoaded", function() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('added')) showToast('Thêm mã khuyến mãi thành công!', 'success');
    if (urlParams.has('updated')) showToast('Cập nhật thành công!', 'success');
    if (urlParams.has('toggled')) {
        const status = urlParams.get('status_changed');
        const action = (status == 1) ? 'Hiển thị' : 'Ẩn';
        showToast(`Đã ${action} mã khuyến mãi!`, 'success');
    }
    if (urlParams.has('error')) {
        let errorMsg = urlParams.get('error').replace(/\+/g, ' '); 
        try { errorMsg = decodeURIComponent(errorMsg); } catch(e) {}
        showToast(errorMsg, 'error');
    }
    // Xóa param trên URL để không hiện lại khi reload
    const newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + "?page=discount_list";
    window.history.replaceState({path: newUrl}, '', newUrl);
});
</script>