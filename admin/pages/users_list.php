<?php
// FILE: admin/pages/users_list.php

require_once '../src/config.php';
require_once '../src/functions.php';

// --- XỬ LÝ LOGIC (Giữ nguyên) ---
if (isset($_GET['action']) && $_GET['action'] === "update_role" && isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = $pdo->prepare("SELECT vai_tro_id FROM nguoi_dung WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    if ($user) {
        $newRole = ($user['vai_tro_id'] == 1) ? 2 : 1;
        $pdo->prepare("UPDATE nguoi_dung SET vai_tro_id = ? WHERE id = ?")->execute([$newRole, $id]);
        $_SESSION['toast'] = ['type' => 'success', 'message' => 'Đã cập nhật quyền hạn!'];
    }
    echo "<script>window.location.href='index.php?page=users_list';</script>";
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === "unlock_user" && isset($_GET['id'])) {
    $id = $_GET['id'];
    $pdo->prepare("UPDATE nguoi_dung SET trang_thai = 1, ly_do_khoa = NULL WHERE id = ?")->execute([$id]);
    $_SESSION['toast'] = ['type' => 'success', 'message' => 'Đã mở khóa tài khoản!'];
    echo "<script>window.location.href='index.php?page=users_list';</script>";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lock_user_id'])) {
    $id = $_POST['lock_user_id'];
    $reason = trim($_POST['lock_reason'] ?? 'Vi phạm chính sách');
    $pdo->prepare("UPDATE nguoi_dung SET trang_thai = 0, ly_do_khoa = ? WHERE id = ?")->execute([$reason, $id]);
    $_SESSION['toast'] = ['type' => 'success', 'message' => 'Đã khóa tài khoản!'];
    echo "<script>window.location.href='index.php?page=users_list';</script>";
    exit;
}

// --- LẤY DỮ LIỆU ---
$keyword = $_GET['keyword'] ?? '';
$role_filter = $_GET['role'] ?? '';
$status_filter = $_GET['status'] ?? '';
$limit = 10;
$page = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$offset = ($page - 1) * $limit;

$sql_base = "FROM nguoi_dung nd WHERE 1=1";
$params = [];

if (!empty($keyword)) {
    $sql_base .= " AND (nd.ho_ten LIKE ? OR nd.email LIKE ?)";
    $params[] = "%$keyword%";
    $params[] = "%$keyword%";
}
if ($role_filter !== '') {
    $sql_base .= " AND nd.vai_tro_id = ?";
    $params[] = $role_filter;
}
if ($status_filter !== '') {
    $sql_base .= " AND nd.trang_thai = ?";
    $params[] = $status_filter;
}

$stmt_count = $pdo->prepare("SELECT COUNT(*) " . $sql_base);
$stmt_count->execute($params);
$total_records = $stmt_count->fetchColumn();
$total_pages = ceil($total_records / $limit);

$sql_data = "SELECT nd.*, 
            (SELECT COALESCE(SUM(tong_tien), 0) FROM don_hang dh WHERE dh.nguoi_dung_id = nd.id AND dh.trang_thai_thanh_toan = 'Đã thanh toán') as tong_chi_tieu
            " . $sql_base . " ORDER BY nd.id DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql_data);
$stmt->execute($params);
$users = $stmt->fetchAll();

$stats = $pdo->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN vai_tro_id = 1 THEN 1 ELSE 0 END) as admin_count,
    SUM(CASE WHEN trang_thai = 0 THEN 1 ELSE 0 END) as locked_count
FROM nguoi_dung")->fetch();
?>

<link rel="stylesheet" href="../assets/css/admin/user-list.css">

<div class="admin-page-container">
    <div class="page-header-title">
        <i class="fas fa-users"></i> Quản lý Người Dùng
    </div>
    
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon bg-blue"><i class="fa-solid fa-users"></i></div>
            <div class="stat-info">
                <span class="stat-label">Tổng thành viên</span>
                <span class="stat-number"><?= number_format($stats['total']) ?></span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon bg-purple"><i class="fa-solid fa-user-shield"></i></div>
            <div class="stat-info">
                <span class="stat-label">Quản trị viên</span>
                <span class="stat-number"><?= number_format($stats['admin_count']) ?></span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon bg-red"><i class="fa-solid fa-user-lock"></i></div>
            <div class="stat-info">
                <span class="stat-label">Tài khoản bị khóa</span>
                <span class="stat-number"><?= number_format($stats['locked_count']) ?></span>
            </div>
        </div>
    </div>

    <div class="main-card-box">
        <div class="toolbar-section">
            <form method="get" class="search-form-wrapper">
                <input type="hidden" name="page" value="users_list">
                
                <div class="filter-wrapper">
                    <select name="role" class="form-select-custom" onchange="this.form.submit()">
                        <option value="">-- Tất cả vai trò --</option>
                        <option value="1" <?= $role_filter === '1' ? 'selected' : '' ?>>Admin</option>
                        <option value="2" <?= $role_filter === '2' ? 'selected' : '' ?>>User</option>
                    </select>

                    <select name="status" class="form-select-custom" onchange="this.form.submit()">
                        <option value="">-- Tất cả trạng thái --</option>
                        <option value="1" <?= $status_filter === '1' ? 'selected' : '' ?>>Hoạt động</option>
                        <option value="0" <?= $status_filter === '0' ? 'selected' : '' ?>>Đã khóa</option>
                    </select>
                </div>

                <div class="search-wrapper">
                    <input type="text" name="keyword" class="search-input" placeholder="Tìm tên, email..." value="<?= htmlspecialchars($keyword) ?>">
                    <button type="submit" class="btn-search-icon"><i class="fa-solid fa-magnifying-glass"></i></button>
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table-custom">
                <thead>
                    <tr>
                        <th width="60" class="text-center">ID</th>
                        <th>Thành viên</th>
                        <th>Liên lạc</th>
                        <th width="100">Vai trò</th>
                        <th width="120">Chi tiêu</th>
                        <th width="120">Trạng thái</th>
                        <th width="250" class="text-end" style="text-align: center;" >Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr><td colspan="7" class="text-center" style="padding: 30px; color: #888;">Không tìm thấy kết quả.</td></tr>
                    <?php else: ?>
                        <?php foreach ($users as $u): ?>
                        <tr>
                            <td class="text-center"><span class="id-badge">#<?= $u['id'] ?></span></td>
                            <td>
                                <div class="user-cell">
                                    <?php if (!empty($u['avatar']) && file_exists('../assets/img/avatars/' . $u['avatar'])): ?>
                                        <img src="../assets/img/avatars/<?= htmlspecialchars($u['avatar']) ?>" class="avatar-circle">
                                    <?php else: ?>
                                        <div class="avatar-circle default"><?= strtoupper(substr($u['ho_ten'], 0, 1)) ?></div>
                                    <?php endif; ?>
                                    <div>
                                        <div class="user-name"><?= htmlspecialchars($u['ho_ten']) ?></div>
                                        <div class="user-email"><?= htmlspecialchars($u['email']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div style="font-weight: 500; font-size: 13px;"><?= htmlspecialchars($u['so_dien_thoai'] ?? '---') ?></div>
                                <div style="font-size: 12px; color: #999; text-transform: capitalize;"><?= $u['gioi_tinh'] ?? '---' ?></div>
                            </td>
                            <td>
                                <?php if ($u['vai_tro_id'] == 1): ?>
                                    <span class="badge-role admin"><i class="fa-solid fa-shield-halved"></i> Admin</span>
                                <?php else: ?>
                                    <span class="badge-role user">User</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-weight: 700; color: #4e73df;"><?= number_format($u['tong_chi_tieu']) ?>đ</td>
                            <td>
                                <?php if ($u['trang_thai'] == 1): ?>
                                    <span class="badge-status active">Hoạt động</span>
                                <?php else: ?>
                                    <span class="badge-status inactive">Đã khóa</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <div class="action-group">
                                    <a href="index.php?page=users_list&action=update_role&id=<?= $u['id'] ?>" 
                                       class="btn-action blue" onclick="return confirm('Đổi quyền hạn?')">
                                       <i class="fa-solid fa-user-gear"></i> Phân quyền
                                    </a>
                                    <?php if ($u['trang_thai'] == 1): ?>
                                        <button class="btn-action red" onclick="openLockModal(<?= $u['id'] ?>, '<?= htmlspecialchars($u['ho_ten']) ?>')">
                                            <i class="fa-solid fa-lock"></i> Khóa
                                        </button>
                                    <?php else: ?>
                                        <a href="index.php?page=users_list&action=unlock_user&id=<?= $u['id'] ?>" 
                                           class="btn-action green" onclick="return confirm('Mở khóa?')">
                                           <i class="fa-solid fa-lock-open"></i> Mở
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1): ?>
        <div class="pagination-wrapper">
            <?php $qs = $_GET; unset($qs['page']); ?>
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="index.php?page=users_list&<?= http_build_query(array_merge($qs, ['p' => $i])) ?>" 
                   class="page-link <?= ($i == $page) ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<div id="lockUserModal" style="display:none;" class="modal-overlay">
    <div class="modal-box">
        <h3>Khóa tài khoản</h3>
        <p>Thành viên: <strong id="modalUserName" style="color:#e74a3b"></strong></p>
        <form method="POST">
            <input type="hidden" name="lock_user_id" id="lockUserId">
            <textarea name="lock_reason" placeholder="Nhập lý do..." required></textarea>
            <div style="text-align:right; margin-top:10px;">
                <button type="button" onclick="document.getElementById('lockUserModal').style.display='none'" class="btn-cancel">Hủy</button>
                <button type="submit" class="btn-confirm">Khóa</button>
            </div>
        </form>
    </div>
</div>
<?php if (isset($_SESSION['toast'])): ?>
<div id="adminToast" class="toast show">
    <i class="fa-solid fa-circle-check"></i> <span><?= $_SESSION['toast']['message'] ?></span>
</div>
<?php unset($_SESSION['toast']); endif; ?>

<script>
    function openLockModal(id, name) {
        document.getElementById('lockUserModal').style.display = 'flex';
        document.getElementById('lockUserId').value = id;
        document.getElementById('modalUserName').innerText = name;
    }
    setTimeout(() => { 
        let t = document.getElementById('adminToast'); 
        if(t) t.classList.remove('show'); 
    }, 3000);
</script>