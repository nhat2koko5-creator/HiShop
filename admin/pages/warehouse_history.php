<?php
// FILE: admin/pages/warehouse_history.php

require_once '../src/config.php';
require_once '../src/functions.php';

// 1. XỬ LÝ BỘ LỌC & PHÂN TRANG
$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
$filter_kho = isset($_GET['kho_id']) ? $_GET['kho_id'] : '';
$filter_loai = isset($_GET['loai_phieu']) ? $_GET['loai_phieu'] : '';

$limit = 10; // Giới hạn 10 dòng/trang
$page = isset($_GET['p']) ? max(1, intval($_GET['p'])) : 1;
$offset = ($page - 1) * $limit;

// 2. XÂY DỰNG QUERY
$base_sql = "
    FROM phieu_kho pk
    LEFT JOIN kho_hang k ON pk.kho_hang_id = k.id
    LEFT JOIN nguoi_dung nd ON pk.nguoi_dung_id = nd.id
    WHERE 1=1
";
$params = [];

if ($keyword) {
    $base_sql .= " AND (pk.ma_phieu LIKE ? OR pk.ghi_chu LIKE ?)";
    $params[] = "%$keyword%";
    $params[] = "%$keyword%";
}
if ($filter_kho) {
    $base_sql .= " AND pk.kho_hang_id = ?";
    $params[] = $filter_kho;
}
if ($filter_loai) {
    $base_sql .= " AND pk.loai_phieu = ?";
    $params[] = $filter_loai;
}

// 3. ĐẾM TỔNG SỐ DÒNG (Để phân trang)
$sql_count = "SELECT COUNT(*) " . $base_sql;
$stmt_count = $pdo->prepare($sql_count);
$stmt_count->execute($params);
$total_records = $stmt_count->fetchColumn();
$total_pages = ceil($total_records / $limit);

// 4. LẤY DỮ LIỆU HIỂN THỊ
$sql_data = "
    SELECT 
        pk.*,
        k.ten_kho,
        nd.ho_ten AS nguoi_thuc_hien,
        (SELECT COUNT(*) FROM chi_tiet_phieu_kho WHERE phieu_kho_id = pk.id) as tong_sp
    " . $base_sql . "
    ORDER BY pk.ngay_tao DESC 
    LIMIT $limit OFFSET $offset
";

$stmt = $pdo->prepare($sql_data);
$stmt->execute($params);
$history = $stmt->fetchAll();

// Lấy danh sách kho để nạp vào Select Box
$all_warehouses = $pdo->query("SELECT id, ten_kho FROM kho_hang")->fetchAll();
?>

<link rel="stylesheet" href="../assets/css/admin/warehouse_history.css">

<div class="history-container">
    
    <div class="page-header-title">
        <i class="fa-solid fa-clock-rotate-left"></i> Lịch sử Nhập / Xuất Kho
    </div>

    <div class="main-card-wrapper">
        
        <div class="filter-toolbar">
            <form action="" method="GET" class="filter-form">
                <input type="hidden" name="page" value="warehouse_history">
                
                <div class="filter-item search-box">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" name="keyword" placeholder="Tìm mã phiếu, ghi chú..." value="<?php echo htmlspecialchars($keyword); ?>">
                </div>
                
                <div class="filter-item">
                    <select name="kho_id" class="form-select">
                        <option value="">-- Tất cả kho --</option>
                        <?php foreach ($all_warehouses as $w): ?>
                            <option value="<?php echo $w['id']; ?>" <?php echo $filter_kho == $w['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($w['ten_kho']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-item">
                    <select name="loai_phieu" class="form-select">
                        <option value="">-- Loại phiếu --</option>
                        <option value="nhap" <?php echo $filter_loai == 'nhap' ? 'selected' : ''; ?>>Nhập kho</option>
                        <option value="xuat" <?php echo $filter_loai == 'xuat' ? 'selected' : ''; ?>>Xuất kho</option>
                    </select>
                </div>

                <button type="submit" class="btn-filter primary"><i class="fa-solid fa-filter"></i> Lọc</button>
                <a href="index.php?page=warehouse_history" class="btn-filter secondary"><i class="fa-solid fa-rotate-right"></i> Reset</a>
            </form>
        </div>

        <table class="table-list">
            <thead>
                <tr>
                    <th>Mã phiếu</th>
                    <th>Loại phiếu</th>
                    <th>Kho hàng</th>
                    <th>Người thực hiện</th>
                    <th>Thời gian</th>
                    <th class="text-center">Số lượng SP</th>
                    <th>Ghi chú</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($history) > 0): ?>
                    <?php foreach ($history as $h): ?>
                    <tr>
                        <td style="font-weight: 700; color: #1e293b;">
                            <?php echo htmlspecialchars($h['ma_phieu']); ?>
                        </td>
                        
                        <td>
                            <?php if ($h['loai_phieu'] == 'nhap'): ?>
                                <span class="badge-type import"><i class="fa-solid fa-arrow-down"></i> Nhập kho</span>
                            <?php else: ?>
                                <span class="badge-type export"><i class="fa-solid fa-arrow-up"></i> Xuất kho</span>
                            <?php endif; ?>
                        </td>

                        <td style="color: #475569;">
                            <?php echo htmlspecialchars($h['ten_kho']); ?>
                        </td>

                        <td>
                            <div class="user-cell">
                                <div class="avatar-circle"><?php echo strtoupper(substr($h['nguoi_thuc_hien'], 0, 1)); ?></div>
                                <span><?php echo htmlspecialchars($h['nguoi_thuc_hien']); ?></span>
                            </div>
                        </td>

                        <td class="text-muted" style="font-size: 13px;">
                            <?php echo date('d/m/Y - H:i', strtotime($h['ngay_tao'])); ?>
                        </td>

                        <td class="text-center">
                            <span class="count-pill"><?php echo $h['tong_sp']; ?></span>
                        </td>

                        <td class="text-muted" style="font-style: italic; max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                            <?php echo htmlspecialchars($h['ghi_chu']); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted" style="padding: 40px;">
                            Không tìm thấy dữ liệu phù hợp.
                        </td>
                    </tr>
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
                    <a href="index.php?page=warehouse_history&<?= http_build_query(array_merge($queryParams, ['p' => $i])) ?>" 
                       class="page-number <?= ($i == $page) ? 'active' : '' ?>">
                       <?= $i ?>
                    </a>
                <?php endfor; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>