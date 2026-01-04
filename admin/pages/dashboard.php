<?php
// FILE: admin/pages/dashboard.php

require_once '../src/config.php';
require_once '../src/functions.php';

// =================================================================================
// 1. TRUY VẤN DỮ LIỆU
// =================================================================================

// A. Các thẻ KPI
$pending_count = $pdo->query("SELECT COUNT(*) FROM don_hang WHERE trang_thai_don_hang = 'Chờ xử lý'")->fetchColumn();

$sql_low = "SELECT COUNT(*) FROM bien_the_san_pham bt 
            WHERE COALESCE((SELECT SUM(CASE WHEN kh.trang_thai = 1 THEN ckt.so_luong_ton ELSE 0 END) 
                          FROM chi_tiet_kho_hang ckt 
                          JOIN kho_hang kh ON ckt.kho_hang_id = kh.id 
                          WHERE ckt.bien_the_id = bt.id), 0) < 5";
$low_stock = $pdo->query($sql_low)->fetchColumn() + $pdo->query("SELECT COUNT(*) FROM san_pham WHERE id NOT IN (SELECT DISTINCT san_pham_id FROM bien_the_san_pham)")->fetchColumn();

$today_revenue = $pdo->query("SELECT COALESCE(SUM(tong_tien), 0) FROM don_hang WHERE trang_thai_thanh_toan = 'Đã thanh toán' AND DATE(ngay_dat) = CURDATE()")->fetchColumn();
$total_users = $pdo->query("SELECT COUNT(*) FROM nguoi_dung WHERE vai_tro_id = 2")->fetchColumn();

// B. Top 5 Sản phẩm bán chạy (Lấy dữ liệu cho Biểu đồ Tròn)
$sql_top = "
    SELECT sp.ten, SUM(ct.so_luong) as da_ban
    FROM chi_tiet_don_hang ct
    JOIN don_hang dh ON ct.don_hang_id = dh.id
    JOIN san_pham sp ON ct.san_pham_id = sp.id
    WHERE dh.trang_thai_don_hang IN ('Đã giao hàng', 'Hoàn tất') 
    GROUP BY sp.id, sp.ten
    ORDER BY da_ban DESC LIMIT 5
";
$top_products = $pdo->query($sql_top)->fetchAll(PDO::FETCH_ASSOC);

// Chuẩn bị dữ liệu JSON cho biểu đồ tròn
$top_labels = []; 
$top_values = [];
foreach($top_products as $p) {
    // Cắt tên sản phẩm cho ngắn gọn nếu dài quá
    $shortName = strlen($p['ten']) > 20 ? substr($p['ten'], 0, 20) . '...' : $p['ten'];
    $top_labels[] = $shortName;
    $top_values[] = (int)$p['da_ban'];
}

// C. Biểu đồ xu hướng 7 ngày (Biểu đồ đường)
$sql_chart = "SELECT DATE_FORMAT(ngay_dat, '%d/%m') as ngay, SUM(tong_tien) as doanh_thu FROM don_hang WHERE trang_thai_thanh_toan = 'Đã thanh toán' AND ngay_dat >= DATE(NOW()) - INTERVAL 6 DAY GROUP BY DATE(ngay_dat) ORDER BY ngay_dat ASC";
$chart_data = $pdo->query($sql_chart)->fetchAll(PDO::FETCH_ASSOC);
$labels = []; $values = [];
foreach($chart_data as $d) { $labels[] = $d['ngay']; $values[] = (int)$d['doanh_thu']; }
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link rel="stylesheet" href="../assets/css/admin/dashboard.css">

<style>
    .grid-charts {
        display: grid;
        grid-template-columns: 2fr 1fr !important; /* Trái to (Line Chart) - Phải nhỏ (Pie Chart) */
        gap: 24px;
    }
    @media (max-width: 992px) { .grid-charts { grid-template-columns: 1fr !important; } }
</style>

<div class="admin-page-content dashboard-container">
    <div class="section-heading"><i class="fa-solid fa-bolt" style="color:#ffc700"></i> Tiêu điểm hôm nay</div>

    <div class="grid-4">
        <div class="white-card border-top-danger action-card">
            <div class="card-icon" style="background: #fff5f8; color: #f1416c;"><i class="fa-solid fa-bell"></i></div>
            <div>
                <div class="card-label">Đơn chờ xử lý</div>
                <div class="card-value text-danger"><?= $pending_count ?></div>
            </div>
            <?php if($pending_count > 0): ?><a href="index.php?page=orders_list&status=pending" class="btn-sm-action text-danger">Xử lý ngay &rarr;</a><?php endif; ?>
        </div>

        <div class="white-card border-top-warning action-card">
            <div class="card-icon" style="background: #fff8dd; color: #ffc700;"><i class="fa-solid fa-boxes-stacked"></i></div>
            <div>
                <div class="card-label">Hàng sắp hết</div>
                <div class="card-value text-warning"><?= $low_stock ?></div>
            </div>
            <?php if($low_stock > 0): ?>
            <a href="index.php?page=products_list&stock=low" class="btn-sm-action text-warning">Xem &rarr;</a>
            <?php endif; ?>
        </div>

        <div class="white-card border-top-green">
            <div class="card-icon" style="background: #e8fff3; color: #50cd89;"><i class="fa-solid fa-money-bill-wave"></i></div>
            <div>
                <div class="card-label">Doanh thu</div>
                <div class="card-value text-success"><?= number_format($today_revenue) ?>đ</div>
            </div>
        </div>

        <div class="white-card border-top-blue">
            <div class="card-icon" style="background: #f1faff; color: #009ef7;"><i class="fa-solid fa-users"></i></div>
            <div>
                <div class="card-label">Khách hàng</div>
                <div class="card-value text-primary"><?= $total_users ?></div>
            </div>
        </div>
    </div>

    <div class="grid-charts">
        
        <div class="chart-wrapper">
            <div class="chart-header">
                <div style="display:flex; align-items:center; gap:10px;">
                    <i class="fa-solid fa-chart-line" style="color:#7239ea"></i>
                    <span class="chart-title">Xu hướng doanh thu (7 ngày)</span>
                </div>
            </div>
            <div style="height: 300px;"><canvas id="weeklyChart"></canvas></div>
        </div>

        <div class="chart-wrapper">
            <div class="chart-header">
                <div style="display:flex; align-items:center; gap:10px;">
                    <i class="fa-solid fa-chart-pie" style="color:#f59e0b"></i>
                    <span class="chart-title">Top Bán Chạy</span>
                </div>
            </div>
            <div style="height: 300px; position: relative;">
                <?php if(empty($top_products)): ?>
                    <div style="text-align:center; padding-top:100px; color:#999;">Chưa có dữ liệu</div>
                <?php else: ?>
                    <canvas id="topProductChart"></canvas>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<script>
// 1. Biểu đồ Doanh thu (Line Chart)
const ctxLine = document.getElementById('weeklyChart').getContext('2d');
let gradient = ctxLine.createLinearGradient(0, 0, 0, 400);
gradient.addColorStop(0, 'rgba(114, 57, 234, 0.3)');
gradient.addColorStop(1, 'rgba(114, 57, 234, 0.0)');

new Chart(ctxLine, {
    type: 'line',
    data: {
        labels: <?= json_encode($labels) ?>,
        datasets: [{
            label: 'Doanh thu',
            data: <?= json_encode($values) ?>,
            borderColor: '#7239ea',
            backgroundColor: gradient,
            borderWidth: 3,
            tension: 0.4,
            fill: true,
            pointRadius: 5,
            pointBackgroundColor: '#fff',
            pointBorderColor: '#7239ea'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, grid: { borderDash: [5, 5] }, ticks: { callback: v => v.toLocaleString('vi') + 'đ' } },
            x: { grid: { display: false } }
        }
    }
});

// 2. Biểu đồ Top Sản phẩm (Doughnut Chart)
<?php if(!empty($top_products)): ?>
const ctxPie = document.getElementById('topProductChart').getContext('2d');
new Chart(ctxPie, {
    type: 'doughnut', // Dạng bánh donut (tròn rỗng giữa)
    data: {
        labels: <?= json_encode($top_labels) ?>,
        datasets: [{
            data: <?= json_encode($top_values) ?>,
            backgroundColor: [
                '#4e73df', // Xanh dương
                '#1cc88a', // Xanh lá
                '#36b9cc', // Xanh ngọc
                '#f6c23e', // Vàng
                '#e74a3b'  // Đỏ
            ],
            hoverOffset: 4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom', // Chú thích nằm dưới
                labels: {
                    usePointStyle: true,
                    boxWidth: 8,
                    font: { size: 11 }
                }
            }
        },
        cutout: '65%', // Độ rỗng ở giữa
    }
});
<?php endif; ?>
</script>