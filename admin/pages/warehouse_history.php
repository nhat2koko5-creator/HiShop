<?php
// FILE: admin/pages/warehouse_history.php

require_once '../src/config.php';
require_once '../src/functions.php';

// =================================================================
// 1. XỬ LÝ BỘ LỌC & PHÂN TRANG
// =================================================================
$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
$filter_kho = isset($_GET['kho_id']) ? $_GET['kho_id'] : '';
$filter_loai = isset($_GET['loai_phieu']) ? $_GET['loai_phieu'] : '';

$limit = 10;
$page = isset($_GET['p']) ? max(1, intval($_GET['p'])) : 1;
$offset = ($page - 1) * $limit;

// Xây dựng Query cơ sở
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

// Đếm tổng dòng
$sql_count = "SELECT COUNT(*) " . $base_sql;
$stmt_count = $pdo->prepare($sql_count);
$stmt_count->execute($params);
$total_records = $stmt_count->fetchColumn();
$total_pages = ceil($total_records / $limit);

// Lấy dữ liệu hiển thị (ĐÃ SỬA: Tính tổng số lượng sản phẩm thực tế)
$sql_data = "
    SELECT 
        pk.*,
        k.ten_kho,
        nd.ho_ten AS nguoi_thuc_hien,
        -- Tính tổng số lượng hàng hóa trong phiếu (SUM thay vì COUNT)
        (SELECT COALESCE(SUM(so_luong), 0) FROM chi_tiet_phieu_kho WHERE phieu_kho_id = pk.id) as tong_sl_hang
    " . $base_sql . "
    ORDER BY pk.ngay_tao DESC 
    LIMIT $limit OFFSET $offset
";

$stmt = $pdo->prepare($sql_data);
$stmt->execute($params);
$history = $stmt->fetchAll();

// Lấy danh sách kho cho Select box
$all_warehouses = $pdo->query("SELECT id, ten_kho FROM kho_hang")->fetchAll();

// Thống kê nhanh cho Stats Row
$stats = $pdo->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN loai_phieu = 'nhap' THEN 1 ELSE 0 END) as total_nhap,
    SUM(CASE WHEN loai_phieu = 'xuat' THEN 1 ELSE 0 END) as total_xuat
FROM phieu_kho")->fetch();
?>

<link rel="stylesheet" href="../assets/css/admin/warehouse_history.css">

<div class="admin-page-container">
    
    <div class="page-header-title">
        <i class="fa-solid fa-clock-rotate-left"></i> Lịch sử Nhập / Xuất Kho
    </div>

    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon bg-blue"><i class="fa-solid fa-file-invoice"></i></div>
            <div class="stat-info">
                <span class="stat-label">Tổng phiếu</span>
                <span class="stat-number"><?= number_format($stats['total']) ?></span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon bg-green"><i class="fa-solid fa-dolly"></i></div>
            <div class="stat-info">
                <span class="stat-label">Phiếu Nhập</span>
                <span class="stat-number"><?= number_format($stats['total_nhap']) ?></span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon bg-orange"><i class="fa-solid fa-truck-ramp-box"></i></div>
            <div class="stat-info">
                <span class="stat-label">Phiếu Xuất</span>
                <span class="stat-number"><?= number_format($stats['total_xuat']) ?></span>
            </div>
        </div>
    </div>

    <div class="main-card-box">
        
        <div class="toolbar-section">
            <form action="" method="GET" class="search-form-wrapper">
                <input type="hidden" name="page" value="warehouse_history">
                
                <div class="filter-wrapper">
                    <select name="kho_id" class="form-select-custom" onchange="this.form.submit()">
                        <option value="">-- Tất cả kho --</option>
                        <?php foreach ($all_warehouses as $w): ?>
                            <option value="<?= $w['id'] ?>" <?= $filter_kho == $w['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($w['ten_kho']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select name="loai_phieu" class="form-select-custom" onchange="this.form.submit()">
                        <option value="">-- Loại phiếu --</option>
                        <option value="nhap" <?= $filter_loai == 'nhap' ? 'selected' : '' ?>>Phiếu Nhập</option>
                        <option value="xuat" <?= $filter_loai == 'xuat' ? 'selected' : '' ?>>Phiếu Xuất</option>
                    </select>
                </div>

                <div class="search-wrapper">
                    <input type="text" name="keyword" class="search-input" placeholder="Tìm mã phiếu, ghi chú..." value="<?= htmlspecialchars($keyword) ?>">
                    <button type="submit" class="btn-search-icon"><i class="fa-solid fa-magnifying-glass"></i></button>
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table-custom">
                <thead>
                    <tr>
                        <th width="15%">Mã phiếu</th>
                        <th width="12%">Loại</th>
                        <th width="15%">Kho hàng</th>
                        <th width="18%">Người thực hiện</th>
                        <th width="15%">Thời gian</th>
                        <th width="10%" class="text-center">Số lượng SP</th>
                        <th>Ghi chú</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($history) > 0): ?>
                        <?php foreach ($history as $h): ?>
                        <tr>
                            <td>
                                <span style="font-weight: 700; color: #4e73df;">
                                    <?= htmlspecialchars($h['ma_phieu']) ?>
                                </span>
                            </td>
                            
                            <td>
                                <?php if ($h['loai_phieu'] == 'nhap'): ?>
                                    <span class="badge-type import"><i class="fa-solid fa-arrow-down"></i> Nhập</span>
                                <?php else: ?>
                                    <span class="badge-type export"><i class="fa-solid fa-arrow-up"></i> Xuất</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <div style="font-weight: 500; color: #333;"><?= htmlspecialchars($h['ten_kho']) ?></div>
                            </td>

                            <td>
                                <div class="user-cell">
                                    <div class="avatar-circle-sm"><?= strtoupper(substr($h['nguoi_thuc_hien'], 0, 1)) ?></div>
                                    <span style="font-size: 13px;"><?= htmlspecialchars($h['nguoi_thuc_hien']) ?></span>
                                </div>
                            </td>

                            <td style="color: #64748b; font-size: 13px;">
                                <?= date('d/m/Y - H:i', strtotime($h['ngay_tao'])) ?>
                            </td>

                            <td class="text-center">
                                <span class="qty-pill"><?= number_format($h['tong_sl_hang']) ?></span>
                            </td>

                            <td style="color: #94a3b8; font-style: italic; font-size: 13px;">
                                <?= htmlspecialchars($h['ghi_chu']) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center" style="padding: 40px; color: #888;">
                                Không tìm thấy dữ liệu phù hợp.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1): ?>
        <div class="pagination-wrapper">
            <?php 
                $queryParams = $_GET; unset($queryParams['page']);
            ?>
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="index.php?page=warehouse_history&<?= http_build_query(array_merge($queryParams, ['p' => $i])) ?>" 
                   class="page-link <?= ($i == $page) ? 'active' : '' ?>">
                   <?= $i ?>
                </a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>
</div>