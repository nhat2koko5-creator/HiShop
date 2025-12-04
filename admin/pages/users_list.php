<?php
// FILE: admin/pages/users_list.php

require_once '../src/config.php';
require_once '../src/functions.php';

// --- 1. XỬ LÝ LOGIC HÀNH ĐỘNG (Dùng JS Redirect để tránh lỗi Header) ---

// A. Đổi vai trò
if (isset($_GET['action']) && $_GET['action'] === "update_role" && isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = $pdo->prepare("SELECT vai_tro_id FROM nguoi_dung WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch();

    if ($user) {
        $newRole = ($user['vai_tro_id'] == 1) ? 2 : 1;
        $update = $pdo->prepare("UPDATE nguoi_dung SET vai_tro_id = ? WHERE id = ?");
        $update->execute([$newRole, $id]);
        $_SESSION['toast'] = ['type' => 'success', 'message' => 'Đã cập nhật quyền hạn thành công!'];
    }
    // SỬA LỖI: Dùng JS để chuyển trang thay vì header()
    echo "<script>window.location.href='index.php?page=users_list';</script>";
    exit;
}

// B. Mở khóa
if (isset($_GET['action']) && $_GET['action'] === "unlock_user" && isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = $pdo->prepare("UPDATE nguoi_dung SET trang_thai = 1, ly_do_khoa = NULL WHERE id = ?");
    if ($stmt->execute([$id])) {
        $_SESSION['toast'] = ['type' => 'success', 'message' => 'Đã mở khóa tài khoản thành công!'];
    }
    // SỬA LỖI: Dùng JS để chuyển trang
    echo "<script>window.location.href='index.php?page=users_list';</script>";
    exit;
}

// C. Khóa tài khoản
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lock_user_id'])) {
    $id = $_POST['lock_user_id'];
    $reason = trim($_POST['lock_reason'] ?? 'Vi phạm chính sách');
    $stmt = $pdo->prepare("UPDATE nguoi_dung SET trang_thai = 0, ly_do_khoa = ? WHERE id = ?");
    if ($stmt->execute([$reason, $id])) {
        $_SESSION['toast'] = ['type' => 'success', 'message' => 'Đã khóa tài khoản thành công!'];
    }
    // SỬA LỖI: Dùng JS để chuyển trang
    echo "<script>window.location.href='index.php?page=users_list';</script>";
    exit;
}

// --- 2. XỬ LÝ LỌC & PHÂN TRANG (Query Builder) ---

// Lấy tham số filter
$keyword = $_GET['keyword'] ?? '';
$role_filter = $_GET['role'] ?? '';     // 1: Admin, 2: User
$status_filter = $_GET['status'] ?? ''; // 1: Active, 0: Locked

// Cấu hình phân trang
$limit = 10; // Số lượng bản ghi trên 1 trang
$page = isset($_GET['p']) ? (int)$_GET['p'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Xây dựng câu Query động
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

// Đếm tổng số bản ghi (để tính số trang)
$sql_count = "SELECT COUNT(*) " . $sql_base;
$stmt_count = $pdo->prepare($sql_count);
$stmt_count->execute($params);
$total_records = $stmt_count->fetchColumn();
$total_pages = ceil($total_records / $limit);

// Lấy dữ liệu trang hiện tại
$sql_data = "SELECT nd.*, 
            (SELECT COUNT(*) FROM don_hang dh WHERE dh.nguoi_dung_id = nd.id) AS so_don_hang,
            (SELECT COALESCE(SUM(tong_tien), 0) FROM don_hang dh WHERE dh.nguoi_dung_id = nd.id AND dh.trang_thai_thanh_toan = 'Đã thanh toán') as tong_chi_tieu
            " . $sql_base . " ORDER BY nd.id DESC LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($sql_data);
$stmt->execute($params);
$users = $stmt->fetchAll();

// --- 3. LẤY THỐNG KÊ TỔNG QUAN (Không ảnh hưởng bởi filter) ---
$stats = $pdo->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN vai_tro_id = 1 THEN 1 ELSE 0 END) as admin_count,
    SUM(CASE WHEN trang_thai = 0 THEN 1 ELSE 0 END) as locked_count
FROM nguoi_dung")->fetch();

$stat_total = $stats['total'] ?? 0;
$stat_admin = $stats['admin_count'] ?? 0;
$stat_banned = $stats['locked_count'] ?? 0;
?>

<link rel="stylesheet" href="../assets/css/admin/user-list.css">

<div class="user-page-container">
    
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon-wrapper stat-blue"><i class="fa-solid fa-users"></i></div>
            <div class="stat-content"><h3><?= number_format($stat_total) ?></h3><p>Tổng thành viên</p></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon-wrapper stat-purple"><i class="fa-solid fa-user-shield"></i></div>
            <div class="stat-content"><h3><?= number_format($stat_admin) ?></h3><p>Quản trị viên</p></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon-wrapper stat-red"><i class="fa-solid fa-user-slash"></i></div>
            <div class="stat-content"><h3><?= number_format($stat_banned) ?></h3><p>Tài khoản bị khóa</p></div>
        </div>
    </div>

    <div class="page-toolbar">
        <form method="get" class="filter-group">
            <input type="hidden" name="page" value="users_list">
            
            <select name="role" class="modern-select">
                <option value="">Tất cả vai trò</option>
                <option value="1" <?= $role_filter === '1' ? 'selected' : '' ?>>Quản trị viên (Admin)</option>
                <option value="2" <?= $role_filter === '2' ? 'selected' : '' ?>>Người dùng (User)</option>
            </select>

            <select name="status" class="modern-select">
                <option value="">Tất cả trạng thái</option>
                <option value="1" <?= $status_filter === '1' ? 'selected' : '' ?>>Hoạt động</option>
                <option value="0" <?= $status_filter === '0' ? 'selected' : '' ?>>Đã khóa</option>
            </select>

            <div class="modern-search">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" name="keyword" placeholder="Tìm tên, email..." value="<?= htmlspecialchars($keyword) ?>">
            </div>

            <button type="submit" class="btn-filter">Lọc</button>
        </form>
    </div>

    <div class="table-card">
        <table class="custom-table">
            <thead>
                <tr>
                    <th width="30%">Thành viên</th>
                    <th width="15%">Liên lạc</th>
                    <th width="10%">Vai trò</th>
                    <th width="12%">Trạng thái</th>
                    <th width="10%">Chi tiêu</th>
                    <th width="23%" style="text-align: center;">Hành động</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                    <tr><td colspan="6" style="text-align: center; padding: 40px; color: #94a3b8;">Không tìm thấy kết quả.</td></tr>
                <?php else: ?>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td>
                            <div class="user-info-cell">
                                <?php if (!empty($u['avatar']) && file_exists('../assets/img/avatars/' . $u['avatar'])): ?>
                                    <img src="../assets/img/avatars/<?= htmlspecialchars($u['avatar']) ?>" class="avatar-circle">
                                <?php else: ?>
                                    <div class="avatar-circle"><?= strtoupper(substr($u['ho_ten'], 0, 1)) ?></div>
                                <?php endif; ?>
                                <div>
                                    <div style="font-weight: 600; color: #1e293b;"><?= htmlspecialchars($u['ho_ten']) ?></div>
                                    <div style="font-size: 13px; color: #64748b;"><?= htmlspecialchars($u['email']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div style="font-size:13px; font-weight:500;"><?= htmlspecialchars($u['so_dien_thoai'] ?? '---') ?></div>
                            <div style="font-size:12px; color:#94a3b8; text-transform: capitalize;"><?= $u['gioi_tinh'] ?? '---' ?></div>
                        </td>
                        <td>
                            <?php if ($u['vai_tro_id'] == 1): ?>
                                <span class="status-badge badge-admin"><i class="fa-solid fa-shield-cat"></i> Admin</span>
                            <?php else: ?>
                                <span class="status-badge badge-user">User</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($u['trang_thai'] == 1): ?>
                                <span class="status-badge badge-active"><span class="badge-dot"></span> Hoạt động</span>
                            <?php else: ?>
                                <span class="status-badge badge-banned" title="<?= htmlspecialchars($u['ly_do_khoa'] ?? '') ?>">
                                    <span class="badge-dot"></span> Đã khóa
                                </span>
                            <?php endif; ?>
                        </td>
                        <td style="font-weight:700; color:#4f46e5;"><?= number_format($u['tong_chi_tieu']) ?>đ</td>
                        <td>
                            <div class="action-btn-group">
                                <a href="index.php?page=users_list&action=update_role&id=<?= $u['id'] ?>" 
                                   class="btn-action-pill btn-role"
                                   onclick="return confirm('Bạn có chắc chắn muốn thay đổi quyền hạn?');">
                                    <i class="fa-solid fa-user-gear"></i>
                                    <span>Phân quyền</span>
                                </a>

                                <?php if ($u['trang_thai'] == 1): ?>
                                    <button type="button" class="btn-action-pill btn-lock" 
                                            onclick="openLockModal(<?= $u['id'] ?>, '<?= htmlspecialchars($u['ho_ten']) ?>')">
                                        <i class="fa-solid fa-lock"></i>
                                        <span>Khóa</span>
                                    </button>
                                <?php else: ?>
                                    <a href="index.php?page=users_list&action=unlock_user&id=<?= $u['id'] ?>" 
                                       class="btn-action-pill btn-unlock"
                                       onclick="return confirm('Mở khóa tài khoản này?');">
                                        <i class="fa-solid fa-lock-open"></i>
                                        <span>Mở khóa</span>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <?php if ($total_pages > 1): ?>
        <div class="pagination-container">
            <div class="pagination-info">
                Hiển thị <strong><?= count($users) ?></strong> trên tổng số <strong><?= $total_records ?></strong> người dùng
            </div>
            <div class="pagination-links">
                <?php 
                    $queryParams = $_GET;
                    unset($queryParams['page']); // bỏ tham số 'page' của router
                ?>
                
                <?php if ($page > 1): ?>
                    <a href="index.php?page=users_list&<?= http_build_query(array_merge($queryParams, ['p' => $page - 1])) ?>" class="page-link normal">
                        <i class="fa-solid fa-chevron-left"></i>
                    </a>
                <?php else: ?>
                    <span class="page-link disabled"><i class="fa-solid fa-chevron-left"></i></span>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="page-link active"><?= $i ?></span>
                    <?php else: ?>
                        <a href="index.php?page=users_list&<?= http_build_query(array_merge($queryParams, ['p' => $i])) ?>" class="page-link normal"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="index.php?page=users_list&<?= http_build_query(array_merge($queryParams, ['p' => $page + 1])) ?>" class="page-link normal">
                        <i class="fa-solid fa-chevron-right"></i>
                    </a>
                <?php else: ?>
                    <span class="page-link disabled"><i class="fa-solid fa-chevron-right"></i></span>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<div id="lockUserModal" class="admin-modal">
    <div style="background:#fff; width:100%; max-width:420px; padding:30px; border-radius:20px; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25);">
        <div style="width:60px; height:60px; background:#fee2e2; color:#ef4444; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:24px; margin:0 auto 20px;">
            <i class="fa-solid fa-user-lock"></i>
        </div>
        <h3 style="text-align:center; margin:0 0 5px; color:#1e293b;">Khóa Tài Khoản</h3>
        <p style="text-align:center; color:#64748b; font-size:14px; margin-bottom:20px;">
            Bạn đang khóa người dùng: <strong id="modalUserName" style="color:#ef4444;">...</strong>
        </p>
        
        <form method="POST" id="lockForm">
            <input type="hidden" name="lock_user_id" id="lockUserId">
            
            <div style="margin-bottom: 8px; font-size: 12px; font-weight: 600; color: #64748b;">Chọn lý do nhanh:</div>
            <div class="chip-group">
                <div class="reason-chip" onclick="setReason('Vi phạm chính sách cộng đồng')">Vi phạm chính sách</div>
                <div class="reason-chip" onclick="setReason('Spam/Quảng cáo trái phép')">Spam</div>
                <div class="reason-chip" onclick="setReason('Bùng hàng nhiều lần')">Boom hàng</div>
                <div class="reason-chip" onclick="setReason('Yêu cầu từ chủ sở hữu')">Yêu cầu từ User</div>
                <div class="reason-chip" onclick="setReason('Nghi vấn gian lận')">Gian lận</div>
            </div>

            <textarea name="lock_reason" id="lockReasonInput" 
                      style="width:100%; padding:12px; border:1px solid #cbd5e1; border-radius:8px; outline:none; min-height:80px; font-family:inherit; font-size:14px; margin-bottom: 20px;"
                      placeholder="Nhập lý do chi tiết... (Bắt buộc)"></textarea>
            
            <div style="display:flex; gap:10px;">
                <button type="button" onclick="closeLockModal()" 
                        style="flex:1; padding:10px; border:1px solid #cbd5e1; background:#fff; border-radius:8px; font-weight:600; cursor:pointer; color:#64748b;">Hủy bỏ</button>
                <button type="submit" 
                        style="flex:1; padding:10px; border:none; background:#ef4444; color:#fff; border-radius:8px; font-weight:600; cursor:pointer;">Xác nhận Khóa</button>
            </div>
        </form>
    </div>
</div>

<div id="adminToast" class="admin-toast">
    <div style="font-size:24px;" class="toast-icon"><i class="fa-solid fa-circle-check"></i></div>
    <div>
        <h4 style="margin:0 0 4px; font-size:15px; color:#1e293b;">Thông báo</h4>
        <p id="toastMsg" style="margin:0; font-size:13px; color:#64748b;">...</p>
    </div>
</div>

<script>
    // TOAST LOGIC
    function showToast(type, msg) {
        const toast = document.getElementById('adminToast');
        const icon = toast.querySelector('.toast-icon i');
        const text = document.getElementById('toastMsg');
        toast.className = 'admin-toast show';
        if(type === 'success') {
            toast.style.borderLeftColor = '#10b981';
            icon.className = 'fa-solid fa-circle-check';
            icon.style.color = '#10b981';
        } else {
            toast.style.borderLeftColor = '#ef4444';
            icon.className = 'fa-solid fa-circle-xmark';
            icon.style.color = '#ef4444';
        }
        text.innerHTML = msg;
        setTimeout(() => { toast.classList.remove('show'); }, 3000);
    }
    document.addEventListener('DOMContentLoaded', function() {
        <?php if (isset($_SESSION['toast'])): ?>
            showToast('<?= $_SESSION['toast']['type'] ?>', '<?= $_SESSION['toast']['message'] ?>');
            <?php unset($_SESSION['toast']); ?>
        <?php endif; ?>
    });

    // MODAL LOGIC
    function openLockModal(id, name) {
        document.getElementById('lockUserId').value = id;
        document.getElementById('modalUserName').innerText = name;
        document.getElementById('lockUserModal').classList.add('show');
        document.getElementById('lockReasonInput').value = '';
    }
    function closeLockModal() {
        document.getElementById('lockUserModal').classList.remove('show');
    }
    
    // Quick Reason Function
    function setReason(text) {
        document.getElementById('lockReasonInput').value = text;
    }

    document.getElementById('lockForm').onsubmit = function() {
        var reason = document.getElementById('lockReasonInput').value.trim();
        if (reason === "") {
            alert("Vui lòng nhập lý do khóa!");
            return false;
        }
        return true;
    };
    window.onclick = function(event) {
        if (event.target == document.getElementById('lockUserModal')) closeLockModal();
    }
</script>