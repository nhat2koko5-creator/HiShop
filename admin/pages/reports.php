<?php
// FILE: admin/pages/reports.php

require_once '../src/config.php';
require_once '../src/functions.php';

// --- 1. XỬ LÝ LỌC NGÀY ---
$start_date = $_GET['start'] ?? date('Y-m-01');
$end_date   = $_GET['end']   ?? date('Y-m-d');

if (strtotime($start_date) > strtotime($end_date)) {
    list($start_date, $end_date) = [$end_date, $start_date];
}

// --- 2. TRUY VẤN DỮ LIỆU CHUẨN XÁC ---
// [QUAN TRỌNG] Chỉ lấy những đơn có ngày hoàn thành (ngay_hoan_thanh) nằm trong khoảng lọc
$sql = "
    SELECT 
        DATE(dh.ngay_hoan_thanh) as ngay, 
        COUNT(DISTINCT dh.id) as tong_don,
        
        SUM(ct.so_luong * ct.don_gia) as doanh_thu,
        
        -- Tính giá vốn (Lấy giá nhập gần nhất)
        SUM(ct.so_luong * COALESCE(
            (
                SELECT don_gia 
                FROM chi_tiet_phieu_kho ctpk
                JOIN phieu_kho pk ON ctpk.phieu_kho_id = pk.id
                WHERE pk.loai_phieu = 'nhap' 
                  AND ctpk.san_pham_id = ct.san_pham_id 
                  AND (ctpk.bien_the_id = ct.bien_the_id OR (ctpk.bien_the_id IS NULL AND ct.bien_the_id IS NULL))
                ORDER BY pk.ngay_tao DESC 
                LIMIT 1
            ), 0
        )) as gia_von

    FROM don_hang dh
    JOIN chi_tiet_don_hang ct ON dh.id = ct.don_hang_id
    WHERE dh.trang_thai_thanh_toan = 'Đã thanh toán' 
      AND dh.trang_thai_don_hang = 'Đã giao hàng'
      AND dh.ngay_hoan_thanh IS NOT NULL -- Bắt buộc phải có ngày hoàn thành
      AND DATE(dh.ngay_hoan_thanh) BETWEEN ? AND ?
    GROUP BY DATE(dh.ngay_hoan_thanh)
    ORDER BY ngay ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$start_date, $end_date]);
$report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- 3. TỔNG HỢP ---
$total_revenue = 0;
$total_profit  = 0;
$chart_labels  = [];
$chart_revenue = [];
$chart_profit  = [];

foreach ($report_data as $row) {
    $rev    = $row['doanh_thu'];
    $profit = $rev - $row['gia_von'];

    $total_revenue += $rev;
    $total_profit  += $profit;

    $chart_labels[]  = date('d/m', strtotime($row['ngay']));
    $chart_revenue[] = $rev;
    $chart_profit[]  = $profit;
}

$profit_margin = ($total_revenue > 0) ? round(($total_profit / $total_revenue) * 100, 1) : 0;
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link rel="stylesheet" href="../assets/css/admin/reports.css">

<div class="admin-page-container">
    <div class="page-header-title">
        <i class="fa-solid fa-chart-pie"></i> Báo Cáo Doanh Thu 
    </div>

    <div class="main-card-box no-print" style="margin-bottom: 25px; padding: 20px;">
        <form method="GET" class="filter-toolbar">
            <input type="hidden" name="page" value="reports">
            <div class="filter-group">
                <div class="date-input-group">
                    <span class="label-date">Từ:</span>
                    <input type="date" name="start" class="form-control-date" value="<?= $start_date ?>">
                </div>
                <div class="date-input-group">
                    <span class="label-date">Đến:</span>
                    <input type="date" name="end" class="form-control-date" value="<?= $end_date ?>">
                </div>
                <button type="submit" class="btn-action btn-filter">
                    <i class="fa-solid fa-filter"></i> Phân tích
                </button>
            </div>
            <button type="button" onclick="window.print()" class="btn-action btn-print">
                <i class="fa-solid fa-print"></i> In
            </button>
        </form>
    </div>

    <div class="stats-grid-3">
        <div class="stat-card">
            <div class="stat-icon bg-green"><i class="fa-solid fa-money-bill-trend-up"></i></div>
            <div class="stat-info">
                <span class="stat-label">Tổng doanh thu</span>
                <span class="stat-number text-success"><?= number_format($total_revenue) ?>đ</span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon bg-purple"><i class="fa-solid fa-sack-dollar"></i></div>
            <div class="stat-info">
                <span class="stat-label">Lợi nhuận ròng</span>
                <span class="stat-number" style="color:#7e22ce"><?= number_format($total_profit) ?>đ</span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon bg-orange"><i class="fa-solid fa-percent"></i></div>
            <div class="stat-info">
                <span class="stat-label">Tỷ suất lãi</span>
                <span class="stat-number" style="color:#c2410c"><?= $profit_margin ?>%</span>
            </div>
        </div>
    </div>

    <div class="main-card-box" style="margin-bottom: 25px;">
        <div class="chart-header">
            <h3 class="box-title">Xu hướng Tài chính</h3>
        </div>
        <div style="height: 400px; width: 100%; padding: 10px;">
            <canvas id="financeChart"></canvas>
        </div>
    </div>

    <div class="main-card-box">
        <div class="chart-header">
            <h3 class="box-title">Chi tiết theo ngày</h3>
        </div>
        <div class="table-responsive">
            <table class="table-custom">
                <thead>
                    <tr>
                        <th width="20%">Thời gian (Hoàn thành)</th>
                        <th width="15%" class="text-center">Số đơn</th>
                        <th width="20%" class="text-end">Doanh thu</th>
                        <th width="20%" class="text-end">Giá vốn</th>
                        <th width="25%" class="text-end">Lợi nhuận</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($report_data)): ?>
                        <tr><td colspan="5" class="text-center text-muted" style="padding: 40px;">Chưa có dữ liệu hoàn thành trong khoảng này.</td></tr>
                    <?php else: ?>
                        <?php foreach ($report_data as $row): 
                            $dt = $row['doanh_thu'];
                            $gv = $row['gia_von'];
                            $ln = $dt - $gv;
                            $color = ($ln >= 0) ? '#7e22ce' : '#dc2626';
                        ?>
                        <tr>
                            <td><strong><?= date('d/m/Y', strtotime($row['ngay'])) ?></strong></td>
                            <td class="text-center"><?= number_format($row['tong_don']) ?></td>
                            <td class="text-end text-success fw-bold"><?= number_format($dt) ?>đ</td>
                            <td class="text-end text-muted"><?= number_format($gv) ?>đ</td>
                            <td class="text-end" style="color:<?= $color ?>; font-weight:700;"><?= number_format($ln) ?>đ</td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <?php if (!empty($report_data)): ?>
                <tfoot>
                    <tr class="table-footer-row">
                        <td colspan="2">TỔNG CỘNG</td>
                        <td class="text-end text-success"><?= number_format($total_revenue) ?>đ</td>
                        <td class="text-end"><?= number_format($total_revenue - $total_profit) ?>đ</td>
                        <td class="text-end" style="color:#7e22ce;"><?= number_format($total_profit) ?>đ</td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>

<script>
    const ctx = document.getElementById('financeChart').getContext('2d');
    let gradientRevenue = ctx.createLinearGradient(0, 0, 0, 400);
    gradientRevenue.addColorStop(0, 'rgba(16, 185, 129, 0.2)'); 
    gradientRevenue.addColorStop(1, 'rgba(16, 185, 129, 0.0)');

    let gradientProfit = ctx.createLinearGradient(0, 0, 0, 400);
    gradientProfit.addColorStop(0, 'rgba(126, 34, 206, 0.4)'); 
    gradientProfit.addColorStop(1, 'rgba(126, 34, 206, 0.0)');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode($chart_labels) ?>,
            datasets: [
                {
                    label: 'Doanh thu',
                    data: <?= json_encode($chart_revenue) ?>,
                    borderColor: '#10b981',
                    backgroundColor: gradientRevenue,
                    borderWidth: 2,
                    tension: 0.4,
                    fill: true,
                    pointRadius: 4,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: '#10b981'
                },
                {
                    label: 'Lợi nhuận',
                    data: <?= json_encode($chart_profit) ?>,
                    borderColor: '#7e22ce',
                    backgroundColor: gradientProfit,
                    borderWidth: 2,
                    tension: 0.4,
                    fill: true,
                    pointRadius: 4,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: '#7e22ce'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { position: 'top', labels: { usePointStyle: true, font: { size: 12 } } },
                tooltip: {
                    backgroundColor: 'rgba(255, 255, 255, 0.9)',
                    titleColor: '#333',
                    bodyColor: '#333',
                    borderColor: '#e2e8f0',
                    borderWidth: 1,
                    callbacks: {
                        label: function(c) {
                            return c.dataset.label + ': ' + new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(c.parsed.y);
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { borderDash: [4, 4], color: '#f1f5f9' },
                    ticks: { callback: function(v) { return v/1000000 + 'M'; }, color: '#64748b' }
                },
                x: { grid: { display: false }, ticks: { color: '#64748b' } }
            }
        }
    });
</script>