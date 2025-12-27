<?php
// FILE: admin/pages/warehouse_reports.php

require_once '../src/config.php';
require_once '../src/functions.php';

// --- 1. XỬ LÝ LỌC NGÀY ---
$filter_type = $_GET['filter_type'] ?? 'this_month';
$start_date = $_GET['start'] ?? '';
$end_date   = $_GET['end']   ?? '';

switch ($filter_type) {
    case 'today':
        $start_date = date('Y-m-d');
        $end_date   = date('Y-m-d');
        break;
    case 'last_month':
        $start_date = date('Y-m-01', strtotime('first day of last month'));
        $end_date   = date('Y-m-t', strtotime('last day of last month'));
        break;
    case 'this_month':
        $start_date = $start_date ?: date('Y-m-01');
        $end_date   = $end_date   ?: date('Y-m-d');
        break;
    case 'custom':
        // Giữ nguyên giá trị từ input date
        break;
}

// Kiểm tra nếu ngày bắt đầu lớn hơn ngày kết thúc thì đảo lại
if (strtotime($start_date) > strtotime($end_date)) {
    list($start_date, $end_date) = [$end_date, $start_date];
}

// --- 2. TRUY VẤN DỮ LIỆU KHO ---
// Lấy tổng số lượng nhập và xuất theo ngày
$sql = "
    SELECT 
        DATE(pk.ngay_tao) as ngay,
        SUM(CASE WHEN pk.loai_phieu = 'nhap' THEN ctpk.so_luong ELSE 0 END) as sl_nhap,
        SUM(CASE WHEN pk.loai_phieu = 'xuat' THEN ctpk.so_luong ELSE 0 END) as sl_xuat,
        COUNT(DISTINCT pk.id) as tong_phieu
    FROM phieu_kho pk
    JOIN chi_tiet_phieu_kho ctpk ON pk.id = ctpk.phieu_kho_id
    WHERE DATE(pk.ngay_tao) BETWEEN ? AND ?
    GROUP BY DATE(pk.ngay_tao)
    ORDER BY ngay ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$start_date, $end_date]);
$warehouse_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- 3. TỔNG HỢP SỐ LIỆU ---
$total_import = 0;
$total_export = 0;
$chart_labels = [];
$chart_import = [];
$chart_export = [];

foreach ($warehouse_data as $row) {
    $total_import += $row['sl_nhap'];
    $total_export += $row['sl_xuat'];

    $chart_labels[] = date('d/m', strtotime($row['ngay']));
    $chart_import[] = $row['sl_nhap'];
    $chart_export[] = $row['sl_xuat'];
}

// Lấy tổng tồn kho hiện tại (chỉ từ kho hoạt động)
$sql_stock = "SELECT COALESCE(SUM(CASE WHEN kh.trang_thai = 1 THEN ckt.so_luong_ton ELSE 0 END), 0) as ton_tong 
              FROM chi_tiet_kho_hang ckt 
              JOIN kho_hang kh ON ckt.kho_hang_id = kh.id";
$total_stock = $pdo->query($sql_stock)->fetchColumn() ?: 0;
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link rel="stylesheet" href="../assets/css/admin/reports.css">

<div class="admin-page-container">
    <div class="page-header-title">
        <i class="fa-solid fa-warehouse"></i> Báo Cáo Kho Hàng
    </div>

<div class="main-card-box no-print" style="margin-bottom: 25px; padding: 20px;">
    <form method="GET" action="index.php" class="filter-toolbar" id="filterForm">
        <input type="hidden" name="page" value="repost_revenue">
        <input type="hidden" name="filter_type" id="filter_type" value="<?= $filter_type ?>">

        <div class="filter-group" style="display: flex; flex-wrap: wrap; gap: 15px; align-items: center;">
            <div class="quick-filters" style="display: flex; gap: 5px;">
                <button type="button" onclick="setFilter('this_month')" 
                    class="btn-action <?= $filter_type == 'this_month' ? 'active' : '' ?>" 
                    style="background: <?= $filter_type == 'this_month' ? '#3b82f6' : '#f1f5f9' ?>; color: <?= $filter_type == 'this_month' ? '#fff' : '#64748b' ?>; border: none; padding: 8px 15px; border-radius: 5px; cursor: pointer;">
                    Tháng này
                </button>
                <button type="button" onclick="setFilter('last_month')" 
                    class="btn-action <?= $filter_type == 'last_month' ? 'active' : '' ?>" 
                    style="background: <?= $filter_type == 'last_month' ? '#3b82f6' : '#f1f5f9' ?>; color: <?= $filter_type == 'last_month' ? '#fff' : '#64748b' ?>; border: none; padding: 8px 15px; border-radius: 5px; cursor: pointer;">
                    Tháng trước
                </button>
            </div>

            <div style="height: 25px; width: 1px; background: #e2e8f0; margin: 0 10px;"></div>

            <div class="date-input-group">
                <span class="label-date">Từ:</span>
                <input type="date" name="start" onchange="document.getElementById('filter_type').value='custom'" class="form-control-date" value="<?= $start_date ?>" style="padding: 7px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            <div class="date-input-group">
                <span class="label-date">Đến:</span>
                <input type="date" name="end" onchange="document.getElementById('filter_type').value='custom'" class="form-control-date" value="<?= $end_date ?>" style="padding: 7px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            
            <button type="submit" class="btn-action btn-filter" style="background: #1e293b; color: #fff; padding: 8px 20px; border-radius: 5px; border: none; cursor: pointer;">
                <i class="fa-solid fa-magnifying-glass"></i> Xem báo cáo
            </button>
        </div>
    </form>
</div>

<script>
function setFilter(type) {
    // Gán loại lọc vào input hidden
    document.getElementById('filter_type').value = type;
    // Thực hiện submit form
    document.getElementById('filterForm').submit();
}
</script>

    <div class="stats-grid-3">
        <div class="stat-card">
            <div class="stat-icon bg-blue" style="background: #3b82f6;"><i class="fa-solid fa-file-import"></i></div>
            <div class="stat-info">
                <span class="stat-label">Tổng nhập kho</span>
                <span class="stat-number" style="color:#3b82f6"><?= number_format($total_import) ?> <small>SP</small></span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon bg-orange"><i class="fa-solid fa-file-export"></i></div>
            <div class="stat-info">
                <span class="stat-label">Tổng xuất kho</span>
                <span class="stat-number" style="color:#c2410c"><?= number_format($total_export) ?> <small>SP</small></span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon bg-green"><i class="fa-solid fa-boxes-stacked"></i></div>
            <div class="stat-info">
                <span class="stat-label">Tồn kho hiện tại</span>
                <span class="stat-number text-success"><?= number_format($total_stock) ?> <small>SP</small></span>
            </div>
        </div>
    </div>

    <div class="main-card-box" style="margin-bottom: 25px;">
        <div class="chart-header">
            <h3 class="box-title">Biến động Nhập - Xuất hàng hóa</h3>
        </div>
        <div style="height: 400px; width: 100%; padding: 10px;">
            <canvas id="warehouseChart"></canvas>
        </div>
    </div>

    <div class="main-card-box">
        <div class="chart-header">
            <h3 class="box-title">Chi tiết giao dịch theo ngày</h3>
        </div>
<div style="width: 100%; overflow-x: auto; background: #fff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
    <table style="width: 100%; border-collapse: collapse; table-layout: fixed; margin: 0;">
        <thead>
            <tr style="background-color: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                <th style="width: 25%; text-align: center; padding: 15px; color: #64748b; font-size: 13px; text-transform: uppercase;">Ngày thực hiện</th>
                <th style="width: 15%; text-align: center; padding: 15px; color: #64748b; font-size: 13px; text-transform: uppercase;">Số phiếu</th>
                <th style="width: 20%; text-align: center; padding: 15px; color: #64748b; font-size: 13px; text-transform: uppercase;">Số lượng nhập</th>
                <th style="width: 20%; text-align: center; padding: 15px; color: #64748b; font-size: 13px; text-transform: uppercase;">Số lượng xuất</th>
                <th style="width: 20%; text-align: center; padding: 15px; color: #64748b; font-size: 13px; text-transform: uppercase;">Chênh lệch</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($warehouse_data)): ?>
                <tr>
                    <td colspan="5" style="text-align: center; padding: 50px; color: #94a3b8;">Không có hoạt động kho nào trong thời gian này.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($warehouse_data as $row): 
                    $diff = (int)$row['sl_nhap'] - (int)$row['sl_xuat'];
                    $diff_color = ($diff > 0) ? '#10b981' : (($diff < 0) ? '#ef4444' : '#64748b');
                ?>
                <tr style="border-bottom: 1px solid #f1f5f9;">
                    <td style="padding: 15px; text-align: center; white-space: nowrap;">
                        <strong style="color: #1e293b;"><?= date('d/m/Y', strtotime($row['ngay'])) ?></strong>
                    </td>
                    <td style="padding: 15px; text-align: center; color: #64748b;">
                        <?= number_format($row['tong_phieu']) ?>
                    </td>
                    <td style="padding: 15px; text-align: center; color: #3b82f6; font-weight: 600;">
                        +<?= number_format($row['sl_nhap']) ?>
                    </td>
                    <td style="padding: 15px; text-align: center; color: #ef4444; font-weight: 600;">
                        -<?= number_format($row['sl_xuat']) ?>
                    </td>
                    <td style="padding: 15px; text-align: center; font-weight: 700; color: <?= $diff_color ?>;">
                        <?= ($diff > 0 ? '+' : '') . number_format($diff) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
        <?php if (!empty($warehouse_data)): ?>
        <tfoot>
            <tr style="background-color: #f8fafc; font-weight: bold; border-top: 2px solid #e2e8f0;">
                <td colspan="2" style="padding: 15px; text-align: left; color: #1e293b;">TỔNG CỘNG TRONG KỲ</td>
                <td style="padding: 15px; text-align: center; color: #3b82f6;">+<?= number_format($total_import) ?></td>
                <td style="padding: 15px; text-align: center; color: #ef4444;">-<?= number_format($total_export) ?></td>
                <td style="padding: 15px; text-align: center; color: #1e293b; background: #f1f5f9;">
                    <?= ($total_import - $total_export > 0 ? '+' : '') . number_format($total_import - $total_export) ?>
                </td>
            </tr>
        </tfoot>
        <?php endif; ?>
    </table>
</div>
    </div>
</div>

<script>
    const ctx = document.getElementById('warehouseChart').getContext('2d');
    
    new Chart(ctx, {
        type: 'bar', // Dùng biểu đồ cột cho kho hàng để dễ nhìn sự chênh lệch
        data: {
            labels: <?= json_encode($chart_labels) ?>,
            datasets: [
                {
                    label: 'Số lượng nhập',
                    data: <?= json_encode($chart_import) ?>,
                    backgroundColor: '#3b82f6',
                    borderRadius: 5,
                },
                {
                    label: 'Số lượng xuất',
                    data: <?= json_encode($chart_export) ?>,
                    backgroundColor: '#ef4444',
                    borderRadius: 5,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'top', labels: { usePointStyle: true } }
            },
            scales: {
                y: { 
                    beginAtZero: true,
                    grid: { borderDash: [4, 4], color: '#f1f5f9' }
                },
                x: { grid: { display: false } }
            }
        }
    });
</script>