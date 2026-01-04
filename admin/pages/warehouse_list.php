<?php
// FILE: admin/pages/warehouse_list.php

require_once '../src/config.php';
require_once '../src/functions.php';

/* ===========================================================
   PHẦN 1: XỬ LÝ LOGIC (THÊM / SỬA / ĐỔI TRẠNG THÁI)
   =========================================================== */

// 1. Xử lý Đổi trạng thái
if (isset($_GET['action']) && $_GET['action'] == 'toggle_status' && isset($_GET['id'])) {
    $id = $_GET['id'];
    try {
        $sql = "UPDATE kho_hang SET trang_thai = IF(trang_thai=1, 0, 1) WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        
        // Check trạng thái mới
        $stmt = $pdo->prepare("SELECT trang_thai FROM kho_hang WHERE id = ?");
        $stmt->execute([$id]);
        $newStatus = $stmt->fetchColumn();
        
        $msg = ($newStatus == 1) ? "Đã mở hoạt động kho hàng!" : "Đã tạm khóa kho hàng!";
        echo "<script>alert('$msg'); window.location.href='index.php?page=warehouse_list';</script>";
    } catch (PDOException $e) {
        echo "<script>alert('Lỗi: " . $e->getMessage() . "'); window.location.href='index.php?page=warehouse_list';</script>";
    }
    exit;
}

// 2. Xử lý Submit Form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? trim($_POST['id']) : '';
    $ten_kho = isset($_POST['ten_kho']) ? trim($_POST['ten_kho']) : '';
    $so_dien_thoai = isset($_POST['so_dien_thoai']) ? trim($_POST['so_dien_thoai']) : '';
    $dia_chi = isset($_POST['dia_chi']) ? trim($_POST['dia_chi']) : '';
    $trang_thai = isset($_POST['trang_thai']) ? $_POST['trang_thai'] : 1;

    if (!empty($ten_kho)) {
        try {
            if (!empty($id)) {
                // Update
                $sql = "UPDATE kho_hang SET ten_kho = ?, so_dien_thoai = ?, dia_chi = ?, trang_thai = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$ten_kho, $so_dien_thoai, $dia_chi, $trang_thai, $id]);
            } else {
                // Insert
                $sql = "INSERT INTO kho_hang (ten_kho, so_dien_thoai, dia_chi, trang_thai) VALUES (?, ?, ?, 1)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$ten_kho, $so_dien_thoai, $dia_chi]);
            }
            echo "<script>window.location.href='index.php?page=warehouse_list';</script>";
            exit;
        } catch (PDOException $e) {
            echo "<script>alert('Lỗi: " . $e->getMessage() . "');</script>";
        }
    }
}

// 3. Lấy dữ liệu & Phân trang
$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
$limit = 10;
$page = isset($_GET['p']) ? max(1, intval($_GET['p'])) : 1;
$offset = ($page - 1) * $limit;

// Đếm tổng
$sql_count = "SELECT COUNT(*) FROM kho_hang WHERE ten_kho LIKE ?";
$stmt_count = $pdo->prepare($sql_count);
$stmt_count->execute(["%$keyword%"]);
$total_records = $stmt_count->fetchColumn();
$total_pages = ceil($total_records / $limit);

// Lấy danh sách
$sql = "SELECT * FROM kho_hang WHERE ten_kho LIKE ? ORDER BY id DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute(["%$keyword%"]);
$warehouses = $stmt->fetchAll();

// Thống kê nhanh
$stats = $pdo->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN trang_thai = 1 THEN 1 ELSE 0 END) as active,
    SUM(CASE WHEN trang_thai = 0 THEN 1 ELSE 0 END) as inactive
FROM kho_hang")->fetch();

$totalWarehouses = $stats['total'] ?? 0;
$activeWarehouses = $stats['active'] ?? 0;
$inactiveWarehouses = $stats['inactive'] ?? 0;
?>

<link rel="stylesheet" href="../assets/css/admin/warehouse_list.css">

<div class="warehouse-container">
    
    <div class="page-header-title">
        <i class="fa-solid fa-warehouse"></i> Quản lý Kho Hàng
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon-box bg-orange">
                <i class="fa-solid fa-warehouse"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Tổng số kho</div>
                <div class="stat-value"><?= $totalWarehouses ?></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon-box bg-green">
                <i class="fa-solid fa-circle-check"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Đang hoạt động</div>
                <div class="stat-value text-success"><?= $activeWarehouses ?></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon-box bg-red">
                <i class="fa-solid fa-lock"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Đang tạm khóa</div>
                <div class="stat-value text-danger"><?= $inactiveWarehouses ?></div>
            </div>
        </div>
    </div>

    <div class="main-card-wrapper">
        
        <div class="toolbar-wrapper">
            <form method="get" class="search-form">
                <input type="hidden" name="page" value="warehouse_list">
                <div class="search-box">
                    <i class="fa-solid fa-magnifying-glass search-icon"></i>
                    <input type="text" name="keyword" class="search-input" placeholder="Tìm kiếm kho..." value="<?= htmlspecialchars($keyword) ?>">
                </div>
            </form>
            
            <button class="btn-add" onclick="openModal()">
                <i class="fa-solid fa-plus"></i> Thêm kho mới
            </button>
        </div>

        <table class="table-list">
            <thead>
                <tr>
                    <th width="80" class="text-center">ID</th>
                    <th>Tên Kho</th>
                    <th>Địa chỉ / Liên hệ</th>
                    <th>Trạng thái</th>
                    <th class="text-right">Hành động</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($warehouses) > 0): ?>
                    <?php foreach ($warehouses as $kho): ?>
                    <tr class="<?= $kho['trang_thai'] == 0 ? 'row-locked' : '' ?>">
                        <td class="text-center text-muted">#<?= $kho['id'] ?></td>
                        <td>
                            <div class="fw-bold text-dark"><?= htmlspecialchars($kho['ten_kho']) ?></div>
                        </td>
                        <td>
                            <div class="info-row"><i class="fa-solid fa-location-dot text-muted"></i> <?= htmlspecialchars($kho['dia_chi']) ?></div>
                            <div class="info-row"><i class="fa-solid fa-phone text-muted"></i> <?= htmlspecialchars($kho['so_dien_thoai']) ?></div>
                        </td>
                        <td>
                            <?php if ($kho['trang_thai'] == 1): ?>
                                <span class="status-badge active">Hoạt động</span>
                            <?php else: ?>
                                <span class="status-badge inactive">Tạm khóa</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-right">
                            <div class="action-buttons">
                                <a href="index.php?page=warehouse_detail&id=<?php echo $kho['id']; ?>" class="btn-action btn-view" title="Xem chi tiết">
                                    <i class="fa-regular fa-eye"></i> Xem
                                </a>
                                
                                <button class="btn-action btn-edit" onclick='editWarehouse(<?php echo json_encode($kho); ?>)'>
                                    <i class="fa-regular fa-pen-to-square"></i> Sửa
                                </button>

                                <?php if ($kho['trang_thai'] == 1): ?>
                                    <a href="index.php?page=warehouse_list&action=toggle_status&id=<?php echo $kho['id']; ?>" 
                                       class="btn-action btn-lock"
                                       onclick="return confirm('Khóa kho hàng này?');">
                                        <i class="fa-solid fa-lock"></i> Khóa
                                    </a>
                                <?php else: ?>
                                    <a href="index.php?page=warehouse_list&action=toggle_status&id=<?php echo $kho['id']; ?>" 
                                       class="btn-action btn-unlock"
                                       onclick="return confirm('Mở lại hoạt động kho hàng này?');">
                                        <i class="fa-solid fa-lock-open"></i> Mở
                                    </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="5" class="text-center text-muted" style="padding: 40px;">Chưa có dữ liệu kho hàng.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <?php if ($total_pages > 1): ?>
        <div class="pagination-area">
            <span class="page-info">Trang <strong><?= $page ?></strong> / <?= $total_pages ?></span>
            <div class="page-list">
                <?php 
                    $queryParams = $_GET; unset($queryParams['page']);
                ?>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="index.php?page=warehouse_list&<?= http_build_query(array_merge($queryParams, ['p' => $i])) ?>" 
                       class="page-number <?= ($i == $page) ? 'active' : '' ?>">
                       <?= $i ?>
                    </a>
                <?php endfor; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<div id="warehouseModal" class="admin-modal">
    <div class="admin-modal-content">
        <h3 class="modal-title" id="modalTitle">Thêm Kho Mới</h3>
        <form action="" method="POST">
            <input type="hidden" name="id" id="warehouseId">
            
            <div style="margin-bottom: 16px;">
                <label class="form-label">Tên kho hàng <span style="color:red">*</span></label>
                <input type="text" class="form-control" name="ten_kho" id="warehouseName" required placeholder="VD: Kho Hà Nội">
            </div>
            
            <div style="margin-bottom: 16px;">
                <label class="form-label">Số điện thoại</label>
                <input type="text" class="form-control" name="so_dien_thoai" id="warehousePhone" placeholder="098...">
            </div>

            <div style="margin-bottom: 16px;">
                <label class="form-label">Địa chỉ</label>
                <input type="text" class="form-control" name="dia_chi" id="warehouseAddress" placeholder="Địa chỉ chi tiết...">
            </div>

            <div style="margin-bottom: 20px;">
                <label class="form-label">Trạng thái</label>
                <select class="form-control" name="trang_thai" id="warehouseStatus">
                    <option value="1">Đang hoạt động</option>
                    <option value="0">Tạm khóa</option>
                </select>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-modal btn-cancel" onclick="closeModal()">Hủy</button>
                <button type="submit" class="btn-modal btn-submit">Lưu thông tin</button>
            </div>
        </form>
    </div>
</div>

<script>
    var modal = document.getElementById("warehouseModal");

    function openModal() {
        document.getElementById('modalTitle').innerText = 'Thêm Kho Mới';
        document.getElementById('warehouseId').value = '';
        document.getElementById('warehouseName').value = '';
        document.getElementById('warehousePhone').value = '';
        document.getElementById('warehouseAddress').value = '';
        document.getElementById('warehouseStatus').value = '1';
        modal.classList.add("show");
    }

    function editWarehouse(data) {
        document.getElementById('modalTitle').innerText = 'Cập nhật Kho Hàng';
        document.getElementById('warehouseId').value = data.id;
        document.getElementById('warehouseName').value = data.ten_kho;
        document.getElementById('warehousePhone').value = data.so_dien_thoai;
        document.getElementById('warehouseAddress').value = data.dia_chi;
        document.getElementById('warehouseStatus').value = data.trang_thai;
        modal.classList.add("show");
    }

    function closeModal() { modal.classList.remove("show"); }
    window.onclick = function(event) { if (event.target == modal) closeModal(); }
</script>