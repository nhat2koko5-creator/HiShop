<?php
// FILE: admin/pages/dashboard.php

// 1. LẤY DỮ LIỆU TỔNG QUAN
$total_products = $pdo->query("SELECT COUNT(*) FROM san_pham")->fetchColumn();
$total_orders   = $pdo->query("SELECT COUNT(*) FROM don_hang")->fetchColumn();
$total_users    = $pdo->query("SELECT COUNT(*) FROM nguoi_dung WHERE vai_tro_id = 2")->fetchColumn();

// Sửa: Check theo 'Đã thanh toán'
$total_revenue  = $pdo->query("SELECT SUM(tong_tien) FROM don_hang WHERE trang_thai_thanh_toan = 'Đã thanh toán'")->fetchColumn();

$total_cats = $pdo->query("SELECT COUNT(*) FROM danh_muc")->fetchColumn();
$orders_this_month = $pdo->query("SELECT COUNT(*) FROM don_hang WHERE MONTH(ngay_dat) = MONTH(CURRENT_DATE()) AND YEAR(ngay_dat) = YEAR(CURRENT_DATE())")->fetchColumn();

// Sửa: Check theo 'Đã thanh toán'
$revenue_this_month = $pdo->query("SELECT SUM(tong_tien) FROM don_hang WHERE trang_thai_thanh_toan = 'Đã thanh toán' AND MONTH(ngay_dat) = MONTH(CURRENT_DATE()) AND YEAR(ngay_dat) = YEAR(CURRENT_DATE())")->fetchColumn();

// 2. LẤY THỐNG KÊ TRẠNG THÁI (Sửa tên cột và giá trị tiếng Việt)
// Cột: trang_thai_don_hang
// Giá trị: Chờ xử lý, Đang giao hàng, Đã giao hàng, Đã hủy
$st_pending   = $pdo->query("SELECT COUNT(*) FROM don_hang WHERE trang_thai_don_hang = 'Chờ xử lý'")->fetchColumn();
$st_shipping  = $pdo->query("SELECT COUNT(*) FROM don_hang WHERE trang_thai_don_hang = 'Đang giao hàng'")->fetchColumn();
$st_delivered = $pdo->query("SELECT COUNT(*) FROM don_hang WHERE trang_thai_don_hang = 'Đã giao hàng'")->fetchColumn();
$st_cancelled = $pdo->query("SELECT COUNT(*) FROM don_hang WHERE trang_thai_don_hang = 'Đã hủy'")->fetchColumn();

// 3. DỮ LIỆU BIỂU ĐỒ DOANH THU
// Sửa: trang_thai_thanh_toan = 'Đã thanh toán'
$sql_chart_revenue = "SELECT DATE_FORMAT(ngay_dat, '%m/%Y') as thang, SUM(tong_tien) as doanh_thu FROM don_hang WHERE trang_thai_thanh_toan = 'Đã thanh toán' GROUP BY DATE_FORMAT(ngay_dat, '%Y-%m') ORDER BY ngay_dat DESC LIMIT 6";
$stmt_rev = $pdo->prepare($sql_chart_revenue);
$stmt_rev->execute();
$revenue_data = array_reverse($stmt_rev->fetchAll(PDO::FETCH_ASSOC));
$chart_labels = []; $chart_values = [];
foreach ($revenue_data as $d) { $chart_labels[] = $d['thang']; $chart_values[] = (int)$d['doanh_thu']; }

// 4. TOP SẢN PHẨM BÁN CHẠY
// Sửa: dh.trang_thai_thanh_toan = 'Đã thanh toán'
$sql_top_products = "SELECT sp.ten, SUM(ct.so_luong) as da_ban FROM chi_tiet_don_hang ct JOIN san_pham sp ON ct.san_pham_id = sp.id JOIN don_hang dh ON ct.don_hang_id = dh.id WHERE dh.trang_thai_thanh_toan = 'Đã thanh toán' GROUP BY sp.id ORDER BY da_ban DESC LIMIT 5";
$stmt_top = $pdo->prepare($sql_top_products);
$stmt_top->execute();
$top_products = $stmt_top->fetchAll(PDO::FETCH_ASSOC);
$top_labels = []; $top_values = [];
foreach ($top_products as $p) { $top_labels[] = mb_strimwidth($p['ten'], 0, 25, "..."); $top_values[] = (int)$p['da_ban']; }
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link rel="stylesheet" href="../assets/css/admin/dashboard.css">

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
            <div class="status-label">Chờ xử lý</div>
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