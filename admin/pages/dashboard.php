<?php
// FILE: admin/pages/dashboard.php

// 1. LẤY DỮ LIỆU
$total_products = $pdo->query("SELECT COUNT(*) FROM san_pham")->fetchColumn();
$total_orders   = $pdo->query("SELECT COUNT(*) FROM don_hang")->fetchColumn();
$total_users    = $pdo->query("SELECT COUNT(*) FROM nguoi_dung WHERE vai_tro_id = 2")->fetchColumn();
$total_revenue  = $pdo->query("SELECT SUM(tong_tien) FROM don_hang WHERE trang_thai_thanh_toan = 'paid'")->fetchColumn();

$total_cats = $pdo->query("SELECT COUNT(*) FROM danh_muc")->fetchColumn();
$orders_this_month = $pdo->query("SELECT COUNT(*) FROM don_hang WHERE MONTH(ngay_dat) = MONTH(CURRENT_DATE()) AND YEAR(ngay_dat) = YEAR(CURRENT_DATE())")->fetchColumn();
$revenue_this_month = $pdo->query("SELECT SUM(tong_tien) FROM don_hang WHERE trang_thai_thanh_toan = 'paid' AND MONTH(ngay_dat) = MONTH(CURRENT_DATE()) AND YEAR(ngay_dat) = YEAR(CURRENT_DATE())")->fetchColumn();

$st_pending   = $pdo->query("SELECT COUNT(*) FROM don_hang WHERE trang_thai = 'pending'")->fetchColumn();
$st_shipping  = $pdo->query("SELECT COUNT(*) FROM don_hang WHERE trang_thai = 'shipping'")->fetchColumn();
$st_delivered = $pdo->query("SELECT COUNT(*) FROM don_hang WHERE trang_thai = 'delivered'")->fetchColumn();
$st_cancelled = $pdo->query("SELECT COUNT(*) FROM don_hang WHERE trang_thai = 'cancelled'")->fetchColumn();

// Biểu đồ
$sql_chart_revenue = "SELECT DATE_FORMAT(ngay_dat, '%m/%Y') as thang, SUM(tong_tien) as doanh_thu FROM don_hang WHERE trang_thai_thanh_toan = 'paid' GROUP BY DATE_FORMAT(ngay_dat, '%Y-%m') ORDER BY ngay_dat DESC LIMIT 6";
$stmt_rev = $pdo->prepare($sql_chart_revenue);
$stmt_rev->execute();
$revenue_data = array_reverse($stmt_rev->fetchAll(PDO::FETCH_ASSOC));
$chart_labels = []; $chart_values = [];
foreach ($revenue_data as $d) { $chart_labels[] = $d['thang']; $chart_values[] = (int)$d['doanh_thu']; }

$sql_top_products = "SELECT sp.ten, SUM(ct.so_luong) as da_ban FROM chi_tiet_don_hang ct JOIN san_pham sp ON ct.san_pham_id = sp.id JOIN don_hang dh ON ct.don_hang_id = dh.id WHERE dh.trang_thai_thanh_toan = 'paid' GROUP BY sp.id ORDER BY da_ban DESC LIMIT 5";
$stmt_top = $pdo->prepare($sql_top_products);
$stmt_top->execute();
$top_products = $stmt_top->fetchAll(PDO::FETCH_ASSOC);
$top_labels = []; $top_values = [];
foreach ($top_products as $p) { $top_labels[] = mb_strimwidth($p['ten'], 0, 25, "..."); $top_values[] = (int)$p['da_ban']; }
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link rel="stylesheet" href="assets/css/admin/dashboard.css">

<style>
    /* === FIX LỖI GIAO DIỆN === */
    
    /* 1. Cưỡng chế màu nền XÁM cho toàn bộ vùng nội dung Admin */
    .admin-main-content, body {
        background-color: #f3f6f9 !important;
    }

    .dashboard-container {
        font-family: 'Inter', sans-serif;
        padding: 10px 20px 50px 20px;
        color: #3f4254;
    }

    /* Tiêu đề Section */
    .section-heading {
        font-size: 14px;
        font-weight: 700;
        color: #009ef7;
        margin-bottom: 20px;
        margin-top: 30px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .section-heading i { font-size: 16px; }
    .section-heading:first-child { margin-top: 0; }

    /* CARD STYLE */
    .white-card {
        background: #ffffff;
        border-radius: 8px;
        padding: 24px 28px;
        /* Đổ bóng đậm hơn để nổi lên trên nền xám */
        box-shadow: 0 4px 20px 0 rgba(0, 0, 0, 0.05); 
        border: 1px solid #ffffff;
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: center;
        transition: all 0.3s ease;
        position: relative;
    }
    
    .white-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 30px 0 rgba(0, 0, 0, 0.1);
    }

    /* Viền trên màu sắc */
    .border-top-blue   { border-top: 4px solid #009ef7; }
    .border-top-green  { border-top: 4px solid #50cd89; }
    .border-top-warning{ border-top: 4px solid #ffc700; }
    .border-top-danger { border-top: 4px solid #f1416c; }
    .border-top-purple { border-top: 4px solid #7239ea; }
    .border-top-cyan   { border-top: 4px solid #00b2ff; }
    .border-top-pink   { border-top: 4px solid #d946ef; }

    /* Nội dung Card */
    .card-label {
        font-size: 13px;
        color: #b5b5c3;
        font-weight: 600;
        text-transform: uppercase;
        margin-bottom: 10px;
    }
    .card-value {
        font-size: 26px;
        font-weight: 800;
        color: #181c32;
        display: flex;
        align-items: center;
        gap: 15px;
    }
    
    /* ICON ĐƯỢC TÔ MÀU (NEW) */
    .card-icon {
        font-size: 24px;
        width: 48px;
        height: 48px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        float: left;
        margin-right: 15px;
    }

    /* Màu icon theo từng loại thẻ */
    .border-top-blue .card-icon   { background: #f1faff; color: #009ef7; }
    .border-top-green .card-icon  { background: #e8fff3; color: #50cd89; }
    .border-top-warning .card-icon{ background: #fff8dd; color: #ffc700; }
    .border-top-danger .card-icon { background: #fff5f8; color: #f1416c; }
    .border-top-purple .card-icon { background: #f8f5ff; color: #7239ea; }
    .border-top-cyan .card-icon   { background: #f0fdff; color: #00b2ff; }
    .border-top-pink .card-icon   { background: #fdf2ff; color: #d946ef; }

    /* GRIDS */
    .grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 24px; margin-bottom: 24px; }
    .grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; margin-bottom: 24px; }
    .grid-charts { display: grid; grid-template-columns: 2fr 1fr; gap: 24px; margin-top: 30px; }

    /* STATUS WIDGETS */
    .status-card {
        background: #fff;
        border-radius: 8px;
        padding: 20px;
        text-align: center;
        box-shadow: 0 2px 10px rgba(0,0,0,0.02);
        border: 1px dashed #e4e6ef;
        transition: 0.2s;
    }
    .status-card:hover { border-color: #009ef7; border-style: solid; transform: translateY(-3px); }
    
    .status-label { font-size: 13px; color: #7e8299; margin-bottom: 5px; font-weight: 600; }
    .status-val { font-size: 24px; font-weight: 700; }
    
    .text-warning { color: #ffc700; }
    .text-primary { color: #009ef7; }
    .text-success { color: #50cd89; }
    .text-danger  { color: #f1416c; }

    /* CHART BOX */
    .chart-wrapper {
        background: #fff;
        padding: 24px;
        border-radius: 8px;
        box-shadow: 0 4px 20px 0 rgba(0, 0, 0, 0.05);
    }
    .chart-header {
        border-bottom: 1px solid #eff2f5;
        padding-bottom: 15px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .chart-title { font-size: 16px; font-weight: 700; color: #181c32; }

    /* RESPONSIVE */
    @media (max-width: 1200px) {
        .grid-4, .grid-3 { grid-template-columns: repeat(2, 1fr); }
        .grid-charts { grid-template-columns: 1fr; }
    }
    @media (max-width: 600px) {
        .grid-4, .grid-3 { grid-template-columns: 1fr; }
    }
</style>

<div class="admin-page-content dashboard-container">
    
    <div class="section-heading">
        <i class="fa-solid fa-chart-line"></i> Thống kê tổng quan
    </div>

    <div class="grid-4">
        <div class="white-card border-top-blue">
            <div class="card-icon"><i class="fa-solid fa-laptop"></i></div>
            <div>
                <div class="card-label">Sản phẩm</div>
                <div class="card-value"><?= $total_products ?></div>
            </div>
        </div>
        <div class="white-card border-top-green">
            <div class="card-icon"><i class="fa-solid fa-cart-shopping"></i></div>
            <div>
                <div class="card-label">Đơn hàng</div>
                <div class="card-value"><?= $total_orders ?></div>
            </div>
        </div>
        <div class="white-card border-top-warning">
            <div class="card-icon"><i class="fa-solid fa-users"></i></div>
            <div>
                <div class="card-label">Người dùng</div>
                <div class="card-value"><?= $total_users ?></div>
            </div>
        </div>
        <div class="white-card border-top-danger">
            <div class="card-icon"><i class="fa-solid fa-sack-dollar"></i></div>
            <div>
                <div class="card-label">Tổng doanh thu</div>
                <div class="card-value" style="font-size: 22px; color: #f1416c;">
                    <?= number_format($total_revenue ?? 0, 0, ',', '.') ?>đ
                </div>
            </div>
        </div>
    </div>

    <div class="grid-3">
        <div class="white-card border-top-purple">
            <div class="card-icon"><i class="fa-solid fa-folder-tree"></i></div>
            <div>
                <div class="card-label">Danh mục</div>
                <div class="card-value"><?= $total_cats ?></div>
            </div>
        </div>
        <div class="white-card border-top-cyan">
            <div class="card-icon"><i class="fa-regular fa-calendar-check"></i></div>
            <div>
                <div class="card-label">Đơn hàng tháng này</div>
                <div class="card-value"><?= $orders_this_month ?></div>
            </div>
        </div>
        <div class="white-card border-top-pink">
            <div class="card-icon"><i class="fa-solid fa-chart-line"></i></div>
            <div>
                <div class="card-label">Doanh thu tháng này</div>
                <div class="card-value" style="color: #d946ef; font-size: 22px;">
                    <?= number_format($revenue_this_month ?? 0, 0, ',', '.') ?>đ
                </div>
            </div>
        </div>
    </div>

    <div class="section-heading">
        <i class="fa-solid fa-clipboard-list"></i> Thống kê đơn hàng
    </div>

    <div class="grid-4">
        <div class="status-card">
            <div class="status-label">Chờ xác nhận</div>
            <div class="status-val text-warning"><?= $st_pending ?></div>
        </div>
        <div class="status-card">
            <div class="status-label">Đang giao</div>
            <div class="status-val text-primary"><?= $st_shipping ?></div>
        </div>
        <div class="status-card">
            <div class="status-label">Đã giao thành công</div>
            <div class="status-val text-success"><?= $st_delivered ?></div>
        </div>
        <div class="status-card">
            <div class="status-label">Đã hủy</div>
            <div class="status-val text-danger"><?= $st_cancelled ?></div>
        </div>
    </div>

    <div class="grid-charts">
        <div class="chart-wrapper">
            <div class="chart-header">
                <i class="fa-solid fa-chart-column" style="color:#009ef7"></i>
                <span class="chart-title">Doanh thu theo tháng</span>
            </div>
            <div style="height: 300px;">
                <canvas id="revenueChart"></canvas>
            </div>
        </div>

        <div class="chart-wrapper">
            <div class="chart-header">
                <i class="fa-solid fa-chart-pie" style="color:#ffc700"></i>
                <span class="chart-title">Top sản phẩm bán chạy</span>
            </div>
            <div style="height: 300px; display:flex; justify-content:center;">
                <canvas id="productChart"></canvas>
            </div>
        </div>
    </div>

</div>

<script>
    Chart.defaults.font.family = "'Inter', sans-serif";
    Chart.defaults.color = '#7e8299';

    const ctxRev = document.getElementById('revenueChart').getContext('2d');
    new Chart(ctxRev, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($chart_labels); ?>,
            datasets: [{
                label: 'Doanh thu',
                data: <?php echo json_encode($chart_values); ?>,
                backgroundColor: '#009ef7',
                borderRadius: 4,
                barThickness: 30
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { borderDash: [4, 4], color: '#eff2f5' }, ticks: { callback: function(value) { return value.toLocaleString('vi-VN') + 'đ'; } } },
                x: { grid: { display: false } }
            }
        }
    });

    const ctxProd = document.getElementById('productChart').getContext('2d');
    new Chart(ctxProd, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($top_labels); ?>,
            datasets: [{
                data: <?php echo json_encode($top_values); ?>,
                backgroundColor: ['#009ef7', '#50cd89', '#ffc700', '#f1416c', '#7239ea'],
                borderWidth: 0,
                hoverOffset: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: { legend: { position: 'right', labels: { usePointStyle: true, padding: 15, font: {size: 11} } } }
        }
    });
</script>