<?php
// FILE: admin/pages/inventory_manager.php
require_once '../src/config.php';
require_once '../src/functions.php';

$inventory = []; // Khởi tạo mảng rỗng để tránh lỗi foreach

try {
    // 1. Cập nhật SQL để lọc sản phẩm có số lượng > 20
$sql = "
    SELECT 
        sp.ten,
        bt.mau_sac,
        bt.dung_luong_ssd,
        sptk.so_luong,
        sptk.ngay_cap_nhat, -- Cột mới thêm
        bt.gia
    FROM san_pham_ton_kho sptk
    JOIN san_pham sp ON sptk.san_pham_id = sp.id
    JOIN bien_the_san_pham bt ON sptk.bien_the_id = bt.id
    WHERE sptk.so_luong > 20
    ORDER BY sptk.so_luong DESC
";

    $stmt = $pdo->query($sql);
    $inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Thông báo lỗi chuyên nghiệp hơn
    echo "<div style='background:#fee2e2; color:#991b1b; padding:15px; border-radius:8px; margin:20px; border:1px solid #f87171;'>
            <strong>Lỗi truy vấn:</strong> " . htmlspecialchars($e->getMessage()) . "
            <br><small>Gợi ý: Hãy kiểm tra xem bạn đã chạy lệnh SQL đồng bộ dữ liệu chưa.</small>
        </div>";
}

// Khởi tạo các biến thống kê
$total_qty = 0; $total_val = 0; $low_stock = 0;
$chart_labels = []; $chart_data = [];

// Chỉ tính toán nếu có dữ liệu
if (!empty($inventory)) {
    foreach ($inventory as $item) {
        $total_qty += $item['so_luong'];
        $total_val += ($item['so_luong'] * $item['gia']);
        if ($item['so_luong'] <= 5) $low_stock++;

        if (count($chart_labels) < 10) {
            $chart_labels[] = $item['ten'] . " (" . $item['mau_sac'] . ")";
            $chart_data[] = $item['so_luong'];
        }
    }
}
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link rel="stylesheet" href="../assets/css/admin/reports.css">

<div class="admin-page-container">
    <div class="page-header-title">
        <i class="fa-solid fa-boxes-stacked"></i> Báo Cáo Quản Trị Tồn Kho Thực Tế
    </div>

    <div class="stats-grid-3">
        <div class="stat-card">
            <div class="stat-icon bg-purple"><i class="fa-solid fa-warehouse"></i></div>
            <div class="stat-info">
                <span class="stat-label">Tổng tồn thực tế</span>
                <span class="stat-number" style="color:#7e22ce"><?= number_format($total_qty) ?> cái</span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon bg-green"><i class="fa-solid fa-money-bill-trend-up"></i></div>
            <div class="stat-info">
                <span class="stat-label">Giá trị ước tính</span>
                <span class="stat-number text-success"><?= number_format($total_val) ?>đ</span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon bg-orange"><i class="fa-solid fa-triangle-exclamation"></i></div>
            <div class="stat-info">
                <span class="stat-label">Cảnh báo hết hàng</span>
                <span class="stat-number" style="color:#c2410c"><?= $low_stock ?> mẫu</span>
            </div>
        </div>
    </div>

    <div class="main-card-box" style="margin-bottom: 25px;">
        <div class="chart-header">
            <h3 class="box-title">Phân bổ tồn kho theo sản phẩm (Top 10)</h3>
        </div>
        <div style="height: 350px; padding: 15px;">
            <canvas id="inventoryChart"></canvas>
        </div>
    </div>

    <div class="main-card-box">
        <div class="chart-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h3 class="box-title">Bảng kê chi tiết vị trí kệ hàng</h3>
            <button onclick="window.print()" class="btn-action no-print">
                <i class="fa-solid fa-print"></i> Xuất báo cáo (PDF)
            </button>
        </div>
       <div class="table-responsive">
<table class="table-custom">
    <thead>
        <tr>
            <th style="text-align: left; padding-left: 20px;">Thông tin sản phẩm</th>
            <th style="text-align: center;">Số lượng</th>
            <th style="text-align: center;">Ngày nhập dự kiến (+2th)</th>
            <th style="text-align: right; padding-right: 20px;">Đơn giá</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($inventory)): ?>
            <tr><td colspan="4" class="text-center">Không có sản phẩm nào có số lượng trên 20.</td></tr>
        <?php else: ?>
            <?php foreach ($inventory as $item): 
                // Xử lý ngày: Nếu không có ngày cập nhật thì lấy ngày hiện tại
                $ngay_goc = !empty($item['ngay_cap_nhat']) ? $item['ngay_cap_nhat'] : date('Y-m-d');
                $date = new DateTime($ngay_goc);
                $ngay_du_kien = $date->modify('+2 months')->format('d/m/Y');
            ?>
                <tr>
                    <td style="text-align: left; padding-left: 20px;">
                        <div style="font-weight: 600; color: #1e293b;">
                            <?= htmlspecialchars($item['ten']) ?>
                        </div>
                        <div style="font-size: 12px; color: #64748b;">
                            <?= htmlspecialchars($item['mau_sac']) ?> | <?= htmlspecialchars($item['dung_luong_ssd']) ?>
                        </div>
                    </td>

                    <td style="text-align: center;">
                        <span style="background: #dbeafe; color: #1e40af; padding: 4px 12px; border-radius: 12px; font-weight: bold; font-size: 13px;">
                            <?= number_format($item['so_luong']) ?>
                        </span>
                    </td>

                    <td style="text-align: center; font-weight: bold; color: #7e22ce;">
                        <?= $ngay_du_kien ?>
                    </td>

                    <td style="text-align: right; padding-right: 20px; font-weight: 500; color: #0f172a;">
                        <?= number_format($item['gia'], 0, ',', '.') ?>đ
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>
</div>
    </div>
</div>

<script>
    // Cấu hình Biểu đồ Chart.js
    const ctx = document.getElementById('inventoryChart').getContext('2d');
    
    // Tạo hiệu ứng đổ màu (Gradient) cho đường biểu đồ
    let gradient = ctx.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, 'rgba(126, 34, 206, 0.4)');
    gradient.addColorStop(1, 'rgba(126, 34, 206, 0)');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode($chart_labels) ?>,
            datasets: [{
                label: 'Số lượng tồn',
                data: <?= json_encode($chart_data) ?>,
                borderColor: '#7e22ce',
                backgroundColor: gradient,
                fill: true,
                tension: 0.4,
                pointRadius: 6,
                pointBackgroundColor: '#7e22ce',
                borderWidth: 3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { 
                legend: { display: false } 
            },
            scales: {
                y: { 
                    beginAtZero: true,
                    grid: { color: '#f1f5f9' }
                },
                x: { 
                    grid: { display: false } 
                }
            }
        }
    });
</script>