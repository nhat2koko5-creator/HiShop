<?php
require_once '../src/config.php';
require_once '../src/functions.php';

/* ==========================
    XỬ LÝ ĐỔI VAI TRÒ
========================== */
if (isset($_GET['action']) && $_GET['action'] === "update_role" && isset($_GET['id'])) {
    $id = $_GET['id'];

    $stmt = $pdo->prepare("SELECT vai_tro_id FROM nguoi_dung WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch();

    if ($user) {
        $newRole = ($user['vai_tro_id'] == 1) ? 2 : 1;

        $update = $pdo->prepare("UPDATE nguoi_dung SET vai_tro_id = ? WHERE id = ?");
        $update->execute([$newRole, $id]);
    }

    header("Location: index.php?page=users_list");
    exit;
}

/* ==========================
    XỬ LÝ VÔ HIỆU / KÍCH HOẠT
========================== */
if (isset($_GET['action']) && $_GET['action'] === "toggle_user" && isset($_GET['id'])) {
    $id = $_GET['id'];

    $stmt = $pdo->prepare("SELECT trang_thai FROM nguoi_dung WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch();

    if ($user) {
        $newStatus = ($user['trang_thai'] == 1) ? 0 : 1;

        $update = $pdo->prepare("UPDATE nguoi_dung SET trang_thai = ? WHERE id = ?");
        $update->execute([$newStatus, $id]);
    }

    header("Location: index.php?page=users_list");
    exit;
}

/* ==========================
    LOAD DANH SÁCH NGƯỜI DÙNG
========================== */

$keyword = $_GET['keyword'] ?? '';

$sql = "SELECT 
            nd.*, 
            (SELECT COUNT(*) FROM don_hang dh WHERE dh.nguoi_dung_id = nd.id) AS so_don_hang
        FROM nguoi_dung nd
        WHERE nd.ho_ten LIKE :keyword1 OR nd.email LIKE :keyword2
        ORDER BY nd.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    'keyword1' => "%$keyword%",
    'keyword2' => "%$keyword%"
]);
$users = $stmt->fetchAll();

?>
<div class="admin-page">
    <h1 class="title">Quản Lý Người Dùng</h1>

    <div class="actions mb-3">
    <form method="get">
        <input type="hidden" name="page" value="users_list">

        <div class="search-box">
            <input type="text" 
                   name="keyword" 
                   placeholder="Tìm kiếm theo tên hoặc email..."
                   value="<?= htmlspecialchars($keyword) ?>">

            <div class="divider"></div>

            <button type="submit">
                <i class="fa-solid fa-search"></i>
            </button>
        </div>

    </form>
</div>

    <div class="card">
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="text-center">ID</th>
                        <th class="text-center">Họ tên</th>
                        <th class="text-center">Email</th>
                        <th class="text-center">SĐT</th>
                        <th class="text-center">Giới tính</th>
                        <th class="text-center">Ngày sinh</th>
                        <th class="text-center">Vai trò</th>
                        <th class="text-center">Trạng thái</th>
                        <th class="text-center">Số đơn</th>
                        <th class="text-center">Thao tác</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (count($users) == 0): ?>
                    <tr>
                        <td colspan="10" class="text-center p-4 text-muted">Không có người dùng nào.</td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($users as $u): ?>
                    <tr>
                        <td class="text-center"><?= $u['id'] ?></td>
                        <td class="text-center"><?= htmlspecialchars($u['ho_ten']) ?></td>
                        <td class="text-center"><?= htmlspecialchars($u['email']) ?></td>
                        <td class="text-center"><?= htmlspecialchars($u['so_dien_thoai']) ?></td>
                        <td class="text-center"><?= $u['gioi_tinh'] ?></td>
                        <td class="text-center"><?= $u['ngay_sinh'] ?></td>
                        <td class="text-center"><?= ($u['vai_tro_id'] == 1) ? "User" : "Admin" ?></td>

                        <td class="text-center">
                            <?= ($u['trang_thai'] == 1)
                                ? "<span class='badge bg-success'>Hoạt động</span>"
                                : "<span class='badge bg-danger'>Vô hiệu</span>" ?>
                        </td>

                        <td class="text-center"><?= $u['so_don_hang'] ?></td>

                        <td class="text-center">

                            <!-- Đổi vai trò (icon bút) -->
                            <a href="index.php?page=users_list&action=update_role&id=<?= $u['id'] ?>"
                               class="btn btn-sm btn-warning" title="Đổi vai trò">
                                <i class="fa-solid fa-pen"></i>
                            </a>

                            <!-- Vô hiệu / kích hoạt -->
                            <?php if ($u['trang_thai'] == 1): ?>
                                <a href="index.php?page=users_list&action=toggle_user&id=<?= $u['id'] ?>"
                                   class="btn btn-sm btn-danger"
                                   title="Vô hiệu hóa"
                                   onclick="return confirm('Vô hiệu hóa tài khoản này?')">
                                    <i class="fa-solid fa-eye-slash"></i>
                                </a>
                            <?php else: ?>
                                <a href="index.php?page=users_list&action=toggle_user&id=<?= $u['id'] ?>"
                                   class="btn btn-sm btn-success"
                                   title="Kích hoạt"
                                   onclick="return confirm('Kích hoạt lại tài khoản?')">
                                    <i class="fa-solid fa-eye"></i>
                                </a>
                            <?php endif; ?>

                        </td>
                    </tr>
                <?php endforeach; ?>

                </tbody>
            </table>
        </div>
    </div>
</div>
