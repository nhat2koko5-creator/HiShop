<?php
// FILE: admin/pages/warehouse_detail.php

// 1. Kiểm tra ID kho
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "<script>alert('Không tìm thấy mã kho!'); window.location.href='index.php?page=warehouse_list';</script>";
    exit;
}
$kho_id = $_GET['id'];

// 2. Lấy thông tin kho hàng
$stmt = $pdo->prepare("SELECT * FROM kho_hang WHERE id = ?");
$stmt->execute([$kho_id]);
$kho = $stmt->fetch();

if (!$kho) {
    echo "<script>alert('Kho hàng không tồn tại!'); window.location.href='index.php?page=warehouse_list';</script>";
    exit;
}

// 3. Kiểm tra kho có bị khóa không
$is_warehouse_locked = $kho['trang_thai'] == 0;
if ($is_warehouse_locked) {
    $warning_msg = "⚠️ Cảnh báo: Kho hàng này đang bị tạm khóa. Bạn chỉ có thể xem thông tin, không thể nhập/xuất hàng!";
}

// 4. XỬ LÝ TÌM KIẾM & PHÂN TRANG
$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
$limit = 10; // Giới hạn 10 sản phẩm/trang
$page = isset($_GET['p']) ? max(1, intval($_GET['p'])) : 1;
$offset = ($page - 1) * $limit;

// Base SQL Query (Dùng chung cho cả Stats và Table)
$base_sql = "
    FROM chi_tiet_kho_hang ct
    JOIN san_pham sp ON ct.san_pham_id = sp.id
    JOIN bien_the_san_pham bt ON ct.bien_the_id = bt.id
    WHERE ct.kho_hang_id = ?
";
$params = [$kho_id];

if ($keyword) {
    $base_sql .= " AND (sp.ten LIKE ? OR bt.mau_sac LIKE ?)";
    $params[] = "%$keyword%";
    $params[] = "%$keyword%";
}

// --- QUERY 1: LẤY TẤT CẢ ĐỂ TÍNH STATS (VỐN TỒN KHO) ---
// (Phải chạy query này để tính tổng tiền chính xác cho toàn bộ kho)
$sql_stats = "
    SELECT 
        ct.so_luong_ton,
        COALESCE((
            SELECT don_gia 
            FROM chi_tiet_phieu_kho ctpk
            JOIN phieu_kho pk ON ctpk.phieu_kho_id = pk.id
            WHERE ctpk.bien_the_id = bt.id 
              AND pk.loai_phieu = 'nhap'
            ORDER BY pk.ngay_tao DESC 
            LIMIT 1
        ), 0) AS gia_nhap_gan_nhat
    " . $base_sql;

$stmt_stats = $pdo->prepare($sql_stats);
$stmt_stats->execute($params);
$all_items = $stmt_stats->fetchAll();

// Tính toán thống kê
$total_items_count = count($all_items); // Tổng số mã SP (SKU)
$total_quantity = 0;
$total_value_cost = 0;

foreach ($all_items as $item) {
    $qty = $item['so_luong_ton'];
    $cost = $item['gia_nhap_gan_nhat'];
    $total_quantity += $qty;
    $total_value_cost += $qty * $cost;
}

// Tính tổng số trang
$total_pages = ceil($total_items_count / $limit);

// --- QUERY 2: LẤY DỮ LIỆU ĐỂ HIỂN THỊ BẢNG (CÓ LIMIT) ---
$sql_table = "
    SELECT 
        ct.so_luong_ton,
        sp.ten AS ten_san_pham,
        sp.hinh_anh AS hinh_cha,
        bt.mau_sac,
        bt.dung_luong_ssd,
        bt.hinh_anh AS hinh_bien_the,
        bt.gia AS gia_ban,
        
        -- Subquery giá nhập (Copy lại logic cũ)
        COALESCE((
            SELECT don_gia 
            FROM chi_tiet_phieu_kho ctpk
            JOIN phieu_kho pk ON ctpk.phieu_kho_id = pk.id
            WHERE ctpk.bien_the_id = bt.id 
              AND pk.loai_phieu = 'nhap'
            ORDER BY pk.ngay_tao DESC 
            LIMIT 1
        ), 0) AS gia_nhap_gan_nhat
    " . $base_sql . " 
    ORDER BY sp.ten ASC, ct.so_luong_ton DESC 
    LIMIT $limit OFFSET $offset
";

$stmt_table = $pdo->prepare($sql_table);
$stmt_table->execute($params);
$inventory_page = $stmt_table->fetchAll();
?>

<link rel="stylesheet" href="../assets/css/admin/warehouse_list.css">
<link rel="stylesheet" href="../assets/css/admin/warehouse_detail.css">

<div class="warehouse-container">
    
    <div class="page-header-title" style="justify-content: space-between; margin-bottom: 24px;">
        <div style="display:flex; align-items:center; gap:10px;">
            <a href="index.php?page=warehouse_list" class="btn-back"><i class="fa-solid fa-arrow-left"></i></a>
            <span style="font-size: 20px;">Chi Tiết Kho: <span style="color:#4f46e5;"><?php echo htmlspecialchars($kho['ten_kho']); ?></span></span>
        </div>
        
        <div style="display: flex; gap: 10px;">
            <a href="index.php?page=warehouse_export&id=<?= $kho_id ?>" class="btn-action-top btn-out" <?php echo $is_warehouse_locked ? 'style="opacity:0.5; pointer-events:none;" disabled' : ''; ?> title="<?php echo $is_warehouse_locked ? 'Kho bị khóa - không thể xuất hàng' : ''; ?>">
                <i class="fa-solid fa-boxes-packing"></i> Xuất kho
            </a>
            <a href="index.php?page=warehouse_import&id=<?= $kho_id ?>" class="btn-action-top btn-in" <?php echo $is_warehouse_locked ? 'style="opacity:0.5; pointer-events:none;" disabled' : ''; ?> title="<?php echo $is_warehouse_locked ? 'Kho bị khóa - không thể nhập hàng' : ''; ?>">
                <i class="fa-solid fa-dolly"></i> Nhập hàng
            </a>
        </div>
    </div>

    <?php if ($is_warehouse_locked): ?>
    <div style="background-color: #fef2f2; border: 2px solid #fca5a5; border-radius: 8px; padding: 16px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px;">
        <i class="fa-solid fa-triangle-exclamation" style="color: #dc2626; font-size: 24px;"></i>
        <div>
            <strong style="color: #991b1b; font-size: 16px;">Cảnh báo: Kho hàng bị tạm khóa</strong>
            <p style="color: #7f1d1d; margin: 4px 0 0 0; font-size: 14px;">Kho hàng này đang tạm khóa. Bạn chỉ có thể xem thông tin, không thể thực hiện các thao tác nhập/xuất hàng.</p>
        </div>
    </div>
    <?php endif; ?>

    <div class="detail-grid">
        <div class="info-card">
            <div class="info-header">
                <div class="info-icon"><i class="fa-solid fa-store"></i></div>
                <div>
                    <div class="info-name"><?php echo htmlspecialchars($kho['ten_kho']); ?></div>
                    <div class="info-status <?php echo $kho['trang_thai'] == 1 ? 'active' : 'inactive'; ?>">
                        <?php echo $kho['trang_thai'] == 1 ? 'Đang hoạt động' : 'Tạm khóa'; ?>
                    </div>
                </div>
            </div>
            <div class="info-body">
                <div class="info-row">
                    <i class="fa-solid fa-location-dot"></i> <span><?php echo htmlspecialchars($kho['dia_chi']); ?></span>
                </div>
                <div class="info-row">
                    <i class="fa-solid fa-phone"></i> <span><?php echo htmlspecialchars($kho['so_dien_thoai']); ?></span>
                </div>
            </div>
        </div>

        <div class="stats-mini-grid">
            <div class="stat-card">
                <div class="stat-icon-box bg-blue"><i class="fa-solid fa-barcode"></i></div>
                <div class="stat-content">
                    <div class="stat-label">Tổng mã SP</div>
                    <div class="stat-value"><?php echo $total_items_count; ?></div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon-box bg-orange"><i class="fa-solid fa-cubes"></i></div>
                <div class="stat-content">
                    <div class="stat-label">Tổng số lượng</div>
                    <div class="stat-value"><?php echo number_format($total_quantity); ?></div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon-box bg-green"><i class="fa-solid fa-sack-dollar"></i></div>
                <div class="stat-content">
                    <div class="stat-label">Vốn tồn kho</div>
                    <div class="stat-value text-success">
                        <?php 
                            if($total_value_cost > 1000000000) echo number_format($total_value_cost / 1000000000, 2) . ' tỷ';
                            else echo number_format($total_value_cost); 
                        ?> <small>đ</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="main-card-wrapper">
        <div class="toolbar-wrapper">
            <div style="font-weight: 700; color: #334155; font-size: 16px;">Danh sách tồn kho</div>
            <form action="" method="GET" class="search-form">
                <input type="hidden" name="page" value="warehouse_detail">
                <input type="hidden" name="id" value="<?php echo $kho_id; ?>">
                <div class="search-box">
                    <i class="fa-solid fa-magnifying-glass search-icon"></i>
                    <input type="text" name="keyword" class="search-input" placeholder="Tìm sản phẩm..." value="<?php echo htmlspecialchars($keyword); ?>">
                </div>
            </form>
        </div>

        <table class="table-list">
            <thead>
                <tr>
                    <th width="50" class="text-center">STT</th>
                    <th>Sản phẩm</th>
                    <th>Phân loại</th>
                    <th>Giá nhập (Gần nhất)</th>
                    <th class="text-center">Tồn kho</th>
                    <th class="text-right">Trạng thái</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($inventory_page) > 0): ?>
                    <?php 
                        $i = $offset + 1; 
                        foreach ($inventory_page as $item): 
                            $img_src = !empty($item['hinh_bien_the']) ? $item['hinh_bien_the'] : $item['hinh_cha'];
                            $img_path = "../assets/img/products/" . $img_src; 
                    ?>
                    <tr>
                        <td class="text-center text-muted"><?php echo $i++; ?></td>
                        <td>
                            <div class="product-cell">
                                <img src="<?php echo htmlspecialchars($img_path); ?>" class="thumb-img" onerror="this.src='../assets/img/no-image.png'">
                                <div>
                                    <div class="prod-name"><?php echo htmlspecialchars($item['ten_san_pham']); ?></div>
                                    <div class="prod-price">Giá bán: <?php echo number_format($item['gia_ban']); ?>đ</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="variant-badge">
                                <?php echo htmlspecialchars($item['mau_sac']); ?> - <?php echo htmlspecialchars($item['dung_luong_ssd']); ?>
                            </span>
                        </td>
                        <td style="font-weight: 600; color: #d97706;">
                            <?php echo ($item['gia_nhap_gan_nhat'] > 0) ? number_format($item['gia_nhap_gan_nhat']) . 'đ' : '<span class="text-muted text-sm">--</span>'; ?>
                        </td>
                        <td class="text-center">
                            <?php if ($item['so_luong_ton'] > 10): ?>
                                <span class="qty-badge high"><?php echo $item['so_luong_ton']; ?></span>
                            <?php elseif ($item['so_luong_ton'] > 0): ?>
                                <span class="qty-badge low"><?php echo $item['so_luong_ton']; ?></span>
                            <?php else: ?>
                                <span class="qty-badge out">0</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-right">
                            <?php if ($item['so_luong_ton'] > 0): ?>
                                <span class="status-text ready"><i class="fa-solid fa-check"></i> Sẵn sàng</span>
                            <?php else: ?>
                                <span class="status-text out-stock"><i class="fa-solid fa-triangle-exclamation"></i> Hết hàng</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="text-center text-muted" style="padding: 40px;">Không tìm thấy sản phẩm nào.</td></tr>
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
                <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                    <a href="index.php?page=warehouse_detail&<?= http_build_query(array_merge($queryParams, ['p' => $p])) ?>" 
                       class="page-number <?= ($p == $page) ? 'active' : '' ?>">
                       <?= $p ?>
                    </a>
                <?php endfor; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>