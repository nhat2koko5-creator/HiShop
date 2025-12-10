<?php
// FILE: admin/pages/dashboard.php

require_once '../src/config.php';
require_once '../src/functions.php';

// =================================================================================
// 1. TRUY VẤN DỮ LIỆU "NÓNG"
// =================================================================================

// A. Đơn hàng cần xử lý ngay
$sql_pending = "SELECT COUNT(*) FROM don_hang WHERE trang_thai_don_hang = 'Chờ xử lý'";
$pending_count = $pdo->query($sql_pending)->fetchColumn();

// B. [ĐÃ SỬA] Sắp hết hàng (Đếm Biến thể < 5 hoặc Sản phẩm chưa có biến thể)
// Logic: Đếm tất cả các dòng trong bảng bien_the_san_pham có số lượng < 5
$sql_low_stock = "SELECT COUNT(*) FROM bien_the_san_pham WHERE so_luong_ton < 5";
$low_stock_variant_count = $pdo->query($sql_low_stock)->fetchColumn();

// Cộng thêm các sản phẩm chưa có biến thể (coi như tồn kho = 0)
$sql_no_variant = "SELECT COUNT(*) FROM san_pham WHERE id NOT IN (SELECT DISTINCT san_pham_id FROM bien_the_san_pham)";
$no_variant_count = $pdo->query($sql_no_variant)->fetchColumn();

$total_low_stock = $low_stock_variant_count + $no_variant_count;

// C. Doanh thu HÔM NAY
$sql_today_rev = "SELECT COALESCE(SUM(tong_tien), 0) FROM don_hang WHERE trang_thai_thanh_toan = 'Đã thanh toán' AND DATE(ngay_dat) = CURDATE()";
$today_revenue = $pdo->query($sql_today_rev)->fetchColumn();

// D. Tổng Khách Hàng
$sql_total_users = "SELECT COUNT(*) FROM nguoi_dung WHERE vai_tro_id = 2";
$total_users = $pdo->query($sql_total_users)->fetchColumn();

// E. Đơn hàng mới nhất
$sql_latest = "SELECT dh.*, nd.ho_ten FROM don_hang dh LEFT JOIN nguoi_dung nd ON dh.nguoi_dung_id = nd.id ORDER BY dh.ngay_dat DESC LIMIT 5";
$latest_orders = $pdo->query($sql_latest)->fetchAll(PDO::FETCH_ASSOC);

// F. Biểu đồ
$sql_chart = "SELECT DATE_FORMAT(ngay_dat, '%d/%m') as ngay, SUM(tong_tien) as doanh_thu FROM don_hang WHERE trang_thai_thanh_toan = 'Đã thanh toán' AND ngay_dat >= DATE(NOW()) - INTERVAL 6 DAY GROUP BY DATE(ngay_dat) ORDER BY ngay_dat ASC";
$chart_data = $pdo->query($sql_chart)->fetchAll(PDO::FETCH_ASSOC);
$labels = []; $values = [];
foreach($chart_data as $d) { $labels[] = $d['ngay']; $values[] = (int)$d['doanh_thu']; }
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link rel="stylesheet" href="../assets/css/admin/dashboard.css">

<div class="admin-page-content dashboard-container">
    <div class="section-heading"><i class="fa-solid fa-bolt" style="color:#ffc700"></i> Tiêu điểm hôm nay</div>

    <div class="grid-4">
        <div class="white-card border-top-danger action-card">
            <div class="card-icon" style="background: #fff5f8; color: #f1416c;"><i class="fa-solid fa-bell"></i></div>
            <div>
                <div class="card-label">Đơn chờ xử lý</div>
                <div class="card-value text-danger"><?= $pending_count ?> <span
                        style="font-size:13px; font-weight:500; color:#b5b5c3;">đơn</span></div>
            </div>
            <?php if($pending_count > 0): ?><a href="index.php?page=orders_list&status=pending"
                class="btn-sm-action text-danger">Xử lý ngay &rarr;</a><?php endif; ?>
        </div>

        <div class="white-card border-top-warning action-card">
            <div class="card-icon" style="background: #fff8dd; color: #ffc700;"><i
                    class="fa-solid fa-boxes-stacked"></i></div>
            <div>
                <div class="card-label">Mẫu sắp hết (< 5)</div>
                        <div class="card-value text-warning"><?= $total_low_stock ?> <span
                                style="font-size:13px; font-weight:500; color:#b5b5c3;">mục</span></div>
                </div>
                <?php if($total_low_stock > 0): ?>
                <a href="index.php?page=products_list&stock=low" class="btn-sm-action text-warning">Xem chi tiết
                    &rarr;</a>
                <?php endif; ?>
            </div>

            <div class="white-card border-top-green">
                <div class="card-icon" style="background: #e8fff3; color: #50cd89;"><i
                        class="fa-solid fa-money-bill-wave"></i></div>
                <div>
                    <div class="card-label">Doanh thu hôm nay</div>
                    <div class="card-value text-success"><?= number_format($today_revenue) ?>đ</div>
                </div>
            </div>

            <div class="white-card border-top-blue">
                <div class="card-icon" style="background: #f1faff; color: #009ef7;"><i class="fa-solid fa-users"></i>
                </div>
                <div>
                    <div class="card-label">Tổng khách hàng</div>
                    <div class="card-value text-primary"><?= $total_users ?></div>
                </div>
            </div>
        </div>

        <div class="grid-charts">
            <div class="chart-wrapper">
                <div class="chart-header" style="justify-content: space-between;">
                    <div style="display:flex; align-items:center; gap:10px;"><i class="fa-solid fa-clock-rotate-left"
                            style="color:#009ef7"></i><span class="chart-title">Đơn hàng vừa đặt</span></div>
                    <a href="index.php?page=orders_list"
                        style="font-size:13px; color:#009ef7; font-weight:600; text-decoration:none;">Xem tất cả</a>
                </div>
                <table class="dashboard-table">
                    <thead>
                        <tr>
                            <th>Mã đơn</th>
                            <th>Khách hàng</th>
                            <th>Tổng tiền</th>
                            <th>Trạng thái</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($latest_orders)): ?><tr>
                            <td colspan="4" style="text-align:center; padding:20px; color:#999;">Chưa có đơn hàng nào.
                            </td>
                        </tr><?php else: ?>
                        <?php foreach($latest_orders as $order): ?>
                        <tr>
                            <td><a href="index.php?page=order_detail&id=<?= $order['id'] ?>"
                                    style="color:#333; font-weight:700;">#<?= $order['id'] ?></a></td>
                            <td>
                                <div style="font-weight:600; font-size:13px; color:#3f4254;">
                                    <?= htmlspecialchars($order['ho_ten'] ?? 'Khách lẻ') ?></div>
                                <div style="font-size:11px; color:#aaa;">
                                    <?= date('H:i - d/m', strtotime($order['ngay_dat'])) ?></div>
                            </td>
                            <td style="color:#009ef7; font-weight:700;"><?= number_format($order['tong_tien']) ?>đ</td>
                            <td>
                                <?php $stt = $order['trang_thai_don_hang']; $badge = 'badge-primary';
                            if($stt == 'Chờ xử lý') $badge = 'badge-warning'; elseif($stt == 'Đã giao hàng') $badge = 'badge-success'; elseif($stt == 'Đã hủy') $badge = 'badge-danger'; ?>
                                <span class="status-badge-sm <?= $badge ?>"><?= $stt ?></span>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="chart-wrapper">
                <div class="chart-header">
                    <div style="display:flex; align-items:center; gap:10px;"><i class="fa-solid fa-chart-area"
                            style="color:#7239ea"></i><span class="chart-title">Xu hướng 7 ngày qua</span></div>
                </div>
                <div style="height: 250px;"><canvas id="weeklyChart"></canvas></div>
            </div>
        </div>
    </div>
    <script>
    const ctx = document.getElementById('weeklyChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode($labels) ?>,
            datasets: [{
                label: 'Doanh thu',
                data: <?= json_encode($values) ?>,
                borderColor: '#7239ea',
                backgroundColor: 'rgba(114, 57, 234, 0.1)',
                borderWidth: 3,
                tension: 0.4,
                fill: true,
                pointRadius: 4,
                pointBackgroundColor: '#fff',
                pointBorderColor: '#7239ea'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        borderDash: [4, 4],
                        color: '#f1f1f1'
                    },
                    ticks: {
                        display: false
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
    </script>