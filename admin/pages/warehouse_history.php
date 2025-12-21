<?php
// FILE: admin/pages/warehouse_history.php

require_once '../src/config.php';
require_once '../src/functions.php';

// =================================================================
// 1. LẤY THỐNG KÊ (STATS) - ĐỂ HIỂN THỊ ICON PHÍA TRÊN
// =================================================================
$stats = [
    'nhap' => $pdo->query("SELECT COUNT(*) FROM phieu_kho WHERE loai_phieu = 'nhap'")->fetchColumn(),
    'xuat' => $pdo->query("SELECT COUNT(*) FROM phieu_kho WHERE loai_phieu = 'xuat'")->fetchColumn(),
    'today' => $pdo->query("SELECT COUNT(*) FROM phieu_kho WHERE DATE(ngay_tao) = CURDATE()")->fetchColumn()
];

// =================================================================
// 2. XỬ LÝ BỘ LỌC & PHÂN TRANG CHO BẢNG DỮ LIỆU
// =================================================================
$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
$filter_kho = isset($_GET['kho_id']) ? $_GET['kho_id'] : '';
$filter_loai = isset($_GET['loai_phieu']) ? $_GET['loai_phieu'] : '';

$limit = 10;
$page = isset($_GET['p']) ? max(1, intval($_GET['p'])) : 1;
$offset = ($page - 1) * $limit;

// Query cơ sở
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

// Đếm tổng số bản ghi
$stmtCount = $pdo->prepare("SELECT COUNT(*) " . $base_sql);
$stmtCount->execute($params);
$total_records = $stmtCount->fetchColumn();
$total_pages = ceil($total_records / $limit);

// Lấy dữ liệu chi tiết
$sql = "
    SELECT pk.*, k.ten_kho, nd.ho_ten as nguoi_tao,
    (SELECT SUM(so_luong) FROM chi_tiet_phieu_kho WHERE phieu_kho_id = pk.id) as tong_sl_hang
    " . $base_sql . "
    ORDER BY pk.ngay_tao DESC
    LIMIT $limit OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$history = $stmt->fetchAll();

// Lấy danh sách kho để đổ vào dropdown
$ds_kho = $pdo->query("SELECT id, ten_kho FROM kho_hang")->fetchAll();
?>
<link rel="stylesheet" href="../assets/css/admin/warehouse_history.css">
<div class="admin-page-container">
    <div class="page-header-title">
        <i class="fa-solid fa-clock-rotate-left"></i> Lịch sử Nhập / Xuất kho
    </div>

    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon" style="background: #e0e7ff; color: #4338ca;">
                <i class="fa-solid fa-download"></i>
            </div>
            <div>
                <div style="font-size: 13px; color: #888;">Tổng phiếu Nhập</div>
                <div style="font-size: 20px; font-weight: 700; color: #333;"><?= number_format($stats['nhap']) ?></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background: #ffedd5; color: #c2410c;">
                <i class="fa-solid fa-upload"></i>
            </div>
            <div>
                <div style="font-size: 13px; color: #888;">Tổng phiếu Xuất</div>
                <div style="font-size: 20px; font-weight: 700; color: #333;"><?= number_format($stats['xuat']) ?></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background: #dcfce7; color: #15803d;">
                <i class="fa-regular fa-calendar-check"></i>
            </div>
            <div>
                <div style="font-size: 13px; color: #888;">Hoạt động hôm nay</div>
                <div style="font-size: 20px; font-weight: 700; color: #333;"><?= number_format($stats['today']) ?></div>
            </div>
        </div>
    </div>

    <div class="filter-bar">
        <form method="GET" action="index.php" class="d-flex flex-wrap gap-2 w-100" style="display: flex; gap: 10px; width: 100%;">
            <input type="hidden" name="page" value="warehouse_history">
            
            <div class="search-box">
                <input type="text" name="keyword" placeholder="Tìm mã phiếu, ghi chú..." value="<?= htmlspecialchars($keyword) ?>">
                <button type="submit"><i class="fa-solid fa-magnifying-glass"></i></button>
            </div>

            <select name="kho_id" class="form-select-custom" onchange="this.form.submit()">
                <option value="">-- Tất cả kho --</option>
                <?php foreach ($ds_kho as $k): ?>
                    <option value="<?= $k['id'] ?>" <?= $filter_kho == $k['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($k['ten_kho']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="loai_phieu" class="form-select-custom" onchange="this.form.submit()">
                <option value="">-- Loại phiếu --</option>
                <option value="nhap" <?= $filter_loai == 'nhap' ? 'selected' : '' ?>>Nhập kho</option>
                <option value="xuat" <?= $filter_loai == 'xuat' ? 'selected' : '' ?>>Xuất kho</option>
            </select>

            <?php if($keyword || $filter_kho || $filter_loai): ?>
                <a href="index.php?page=warehouse_history" class="btn-reset-filter" title="Xóa bộ lọc">
                    <i class="fa-solid fa-rotate-right"></i>
                </a>
            <?php endif; ?>
        </form>
    </div>

    <div class="main-card-box">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th width="50" class="text-center">STT</th>
                        <th>Mã phiếu</th>
                        <th>Kho hàng</th>
                        <th>Loại phiếu</th>
                        <th>Người tạo</th> <th>Ngày tạo</th>
                        <th class="text-center">Tổng SL</th>
                        <th>Ghi chú</th>
                        <th class="text-center" width="120">Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($history) > 0): ?>
                        <?php 
                        $i = $offset + 1;
                        foreach ($history as $h): 
                        ?>
                        <tr>
                            <td class="text-center text-muted"><?= $i++ ?></td>
                            
                            <td>
                                <span style="font-weight: 700; color: #4e73df;">
                                    <?= htmlspecialchars($h['ma_phieu']) ?>
                                </span>
                            </td>

                            <td>
                                <div style="font-weight: 600; font-size: 13px;"><?= htmlspecialchars($h['ten_kho']) ?></div>
                            </td>

                            <td>
                                <?php if ($h['loai_phieu'] == 'nhap'): ?>
                                    <span class="badge-type import"><i class="fa-solid fa-arrow-down"></i> Nhập kho</span>
                                <?php else: ?>
                                    <span class="badge-type export"><i class="fa-solid fa-arrow-up"></i> Xuất kho</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <i class="fa-solid fa-user-shield" style="color: #4e73df; font-size: 14px;"></i>
                                    <span style="font-weight: 600; font-size: 13px; color: #333;">
                                     <?= htmlspecialchars($h['nguoi_tao']) ?>
                                    </span>
                                </div>
                            </td>

                            <td>
                                <div style="font-weight: 600; font-size: 13px; color: #333;">
                                    <?= date('d/m/Y', strtotime($h['ngay_tao'])) ?>
                                </div>
                                <div style="font-size: 11px; color: #888;">
                                    <?= date('H:i', strtotime($h['ngay_tao'])) ?>
                                </div>
                            </td>

                            <td class="text-center">
                                <span class="qty-pill"><?= number_format($h['tong_sl_hang']) ?></span>
                            </td>

                            <td style="color: #94a3b8; font-style: italic; font-size: 13px; max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                <?= htmlspecialchars($h['ghi_chu']) ?>
                            </td>

                            <td class="text-center">
                                <a href="index.php?page=warehouse_receipt_detail&id=<?= $h['id'] ?>" class="btn-detail-view" title="Xem chi tiết phiếu">
                                    <i class="fa-solid fa-eye"></i> Chi tiết
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="text-center" style="padding: 40px; color: #888;">
                                <i class="fa-solid fa-box-open" style="font-size: 40px; margin-bottom: 10px; display: block; color: #d1d3e2;"></i>
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