<?php
// FILE: admin/pages/products_list.php
require_once '../src/config.php';
require_once '../src/functions.php';

function js_redirect($url) {
    echo "<script>window.location.href='" . $url . "';</script>";
    exit;
}

// [MỚI] HÀM LƯU THÔNG SỐ KỸ THUẬT (Dùng chung cho cả Add và Update)
function saveProductSpecs($pdo, $productId, $specs) {
    // 1. Kiểm tra xem bộ thông số này đã tồn tại trong bảng 'thong_so' chưa
    // Lưu ý: Nếu user để trống thì lưu là NULL hoặc chuỗi rỗng
    $sqlCheck = "SELECT id FROM thong_so WHERE man_hinh=? AND o_cung=? AND cpu=? AND gpu=? AND ram=? LIMIT 1";
    $stmt = $pdo->prepare($sqlCheck);
    $stmt->execute([$specs['man_hinh'], $specs['o_cung'], $specs['cpu'], $specs['gpu'], $specs['ram']]);
    $specId = $stmt->fetchColumn();

    if (!$specId) {
        // 2. Chưa có thì tạo mới bộ thông số này
        $sqlInsert = "INSERT INTO thong_so (man_hinh, o_cung, cpu, gpu, ram) VALUES (?, ?, ?, ?, ?)";
        $stmtInsert = $pdo->prepare($sqlInsert);
        $stmtInsert->execute([$specs['man_hinh'], $specs['o_cung'], $specs['cpu'], $specs['gpu'], $specs['ram']]);
        $specId = $pdo->lastInsertId();
    }

    // 3. Liên kết sản phẩm với bộ thông số (Xóa cũ -> Thêm mới để tránh trùng lặp)
    $pdo->prepare("DELETE FROM san_pham_thong_so WHERE san_pham_id = ?")->execute([$productId]);
    
    $sqlLink = "INSERT INTO san_pham_thong_so (san_pham_id, thong_so_id) VALUES (?, ?)";
    $pdo->prepare($sqlLink)->execute([$productId, $specId]);
}

// =================================================================
// 1. XỬ LÝ LOGIC
// =================================================================

// =================================================================
// 1. XỬ LÝ LOGIC (ĐÃ CẬP NHẬT LOGIC KHO)
// =================================================================

// A. THÊM SẢN PHẨM MỚI
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_product') {
    $ten = trim($_POST['ten']);
    $mo_ta = $_POST['mo_ta'];
    $mo_ta_chi_tiet = $_POST['mo_ta_chi_tiet'];
    $danh_muc = $_POST['danh_muc'];
    $variants = json_decode($_POST['variants_json'], true);

    // [THAY ĐỔI] Không tính tổng SL từ form nữa, mặc định là 0
    $tong_sl = 0; 
    $gia_min = 0;
    if (!empty($variants)) {
        // Lấy giá nhỏ nhất làm giá đại diện
        $gia_min = min(array_column($variants, 'gia'));
    }

    $hinh_anh = "";
    if (!empty($_FILES['hinh_anh']['name'])) {
        $fileName = time() . "_" . basename($_FILES["hinh_anh"]["name"]);
        move_uploaded_file($_FILES["hinh_anh"]["tmp_name"], "../assets/img/products/" . $fileName);
        $hinh_anh = $fileName;
    }

    // Insert SP (Số lượng = 0)
    $stmt = $pdo->prepare("INSERT INTO san_pham (ten, gia, so_luong, hinh_anh, mo_ta_chi_tiet, trang_thai, danh_muc_id) VALUES (?, ?, 0, ?, ?, 1, ?)");
    $stmt->execute([$ten, $gia_min, $hinh_anh, $mo_ta_chi_tiet, $danh_muc]);
    $product_id = $pdo->lastInsertId();

    // Lưu thông số kỹ thuật (Giữ nguyên code cũ)
    $specs = [
        'man_hinh' => $_POST['man_hinh'] ?? '',
        'cpu'      => $_POST['cpu'] ?? '',
        'ram'      => $_POST['ram'] ?? '',
        'gpu'      => $_POST['gpu'] ?? '',
        'o_cung'   => $_POST['o_cung'] ?? ''
    ];
    saveProductSpecs($pdo, $product_id, $specs);

    // LƯU BIẾN THỂ
    if (!empty($variants)) {
        foreach ($variants as $v) {
            $imgName = "";
            $idx = $v['img_index']; 
            if (!empty($_FILES['variant_imgs']['name'][$idx])) {
                $imgName = time() . "_" . basename($_FILES['variant_imgs']['name'][$idx]);
                move_uploaded_file($_FILES['variant_imgs']['tmp_name'][$idx], "../assets/img/products/" . $imgName);
            }
            // [THAY ĐỔI] so_luong_ton luôn là 0 khi tạo mới
            $stmt2 = $pdo->prepare("INSERT INTO bien_the_san_pham (san_pham_id, mau_sac, dung_luong_ssd, gia, so_luong_ton, hinh_anh) VALUES (?, ?, ?, ?, 0, ?)");
            $stmt2->execute([$product_id, $v['mau'], $v['ssd'], $v['gia'], $imgName]);
        }
    }
    js_redirect("index.php?page=products_list&added=1");
}

// B. CẬP NHẬT SẢN PHẨM
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_product') {
        $id = intval($_POST['product_id']);
        $ten = trim($_POST['ten']);
        $mo_ta_chi_tiet = $_POST['mo_ta_chi_tiet']; // [SỬA 1] Lấy đúng tên input
        $danh_muc = $_POST['danh_muc'];
        $variants = json_decode($_POST['variants_json'], true);

        $gia_min = 0;
        if (!empty($variants)) {
            $gia_min = min(array_column($variants, 'gia'));
        }

        // [SỬA 2] Update vào cột 'mo_ta_chi_tiet'
        $sql_update = "UPDATE san_pham SET ten=?, gia=?, mo_ta_chi_tiet=?, danh_muc_id=?";
        
        // [SỬA 3] Truyền đúng biến vào tham số
        $params = [$ten, $gia_min, $mo_ta_chi_tiet, $danh_muc];

    if (!empty($_FILES['hinh_anh']['name'])) {
        $fileName = time() . "_" . basename($_FILES["hinh_anh"]["name"]);
        move_uploaded_file($_FILES["hinh_anh"]["tmp_name"], "../assets/img/products/" . $fileName);
        $sql_update .= ", hinh_anh=?";
        $params[] = $fileName;
    }
    $sql_update .= " WHERE id=?";
    $params[] = $id;

    $stmt = $pdo->prepare($sql_update);
    $stmt->execute($params);

    // Lưu thông số kỹ thuật (Giữ nguyên)
    $specs = [
        'man_hinh' => $_POST['man_hinh'] ?? '',
        'cpu'      => $_POST['cpu'] ?? '',
        'ram'      => $_POST['ram'] ?? '',
        'gpu'      => $_POST['gpu'] ?? '',
        'o_cung'   => $_POST['o_cung'] ?? ''
    ];
    saveProductSpecs($pdo, $id, $specs);

    // CẬP NHẬT BIẾN THỂ
    // Logic: Xóa cũ thêm mới nhưng PHẢI GIỮ LẠI SỐ LƯỢNG TỒN CŨ
    // 1. Lấy map tồn kho cũ: [ 'Mau-SSD' => sl_ton ]
    $oldStockMap = [];
    $stmtOld = $pdo->prepare("SELECT mau_sac, dung_luong_ssd, so_luong_ton FROM bien_the_san_pham WHERE san_pham_id = ?");
    $stmtOld->execute([$id]);
    while($row = $stmtOld->fetch()){
        $key = $row['mau_sac'] . '-' . $row['dung_luong_ssd'];
        $oldStockMap[$key] = $row['so_luong_ton'];
    }

    // 2. Xóa hết
    $pdo->prepare("DELETE FROM bien_the_san_pham WHERE san_pham_id=?")->execute([$id]);

    // 3. Thêm lại (Khôi phục tồn kho nếu trùng màu/ssd)
    if (!empty($variants)) {
        foreach ($variants as $v) {
            $imgName = $v['old_img']; 
            $idx = $v['img_index']; 
            if (!empty($_FILES['variant_imgs']['name'][$idx])) {
                $newImgName = time() . "_" . basename($_FILES['variant_imgs']['name'][$idx]);
                move_uploaded_file($_FILES['variant_imgs']['tmp_name'][$idx], "../assets/img/products/" . $newImgName);
                $imgName = $newImgName; 
            }
            
            // Tìm lại tồn kho cũ
            $keyCheck = $v['mau'] . '-' . $v['ssd'];
            $currentStock = isset($oldStockMap[$keyCheck]) ? $oldStockMap[$keyCheck] : 0;

            $stmt2 = $pdo->prepare("INSERT INTO bien_the_san_pham (san_pham_id, mau_sac, dung_luong_ssd, gia, so_luong_ton, hinh_anh) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt2->execute([$id, $v['mau'], $v['ssd'], $v['gia'], $currentStock, $imgName]);
        }
    }
    
    // [QUAN TRỌNG] Tính lại tổng tồn kho cho sản phẩm cha sau khi update biến thể (chỉ từ kho hoạt động)
    $pdo->prepare("UPDATE san_pham SET so_luong = (SELECT COALESCE(SUM(CASE WHEN kh.trang_thai = 1 THEN ckt.so_luong_ton ELSE 0 END),0) FROM chi_tiet_kho_hang ckt JOIN kho_hang kh ON ckt.kho_hang_id = kh.id JOIN bien_the_san_pham bt ON ckt.bien_the_id = bt.id WHERE bt.san_pham_id = ?) WHERE id = ?")->execute([$id, $id]);

    js_redirect("index.php?page=products_list&updated=1");
}
// C. ẨN/HIỆN
if (isset($_GET['toggle'])) {
    $id = intval($_GET['toggle']);
    $stmt = $pdo->prepare("SELECT trang_thai FROM san_pham WHERE id = ?");
    $stmt->execute([$id]);
    $status = $stmt->fetchColumn();
    $newStatus = ($status == 1 ? 0 : 1);
    $pdo->prepare("UPDATE san_pham SET trang_thai = ? WHERE id = ?")->execute([$newStatus, $id]);
    js_redirect("index.php?page=products_list&toggled=1");
}

require_once 'layouts/header.php';
?>

<?php
// =================================================================
// 3. LẤY DỮ LIỆU (GET) - ĐÃ CẬP NHẬT BỘ LỌC
// =================================================================

// 1. Lấy danh sách danh mục để đổ vào dropdown
$categories = $pdo->query("SELECT * FROM danh_muc ORDER BY ten ASC")->fetchAll(PDO::FETCH_ASSOC);

$stats = [
    'total' => $pdo->query("SELECT COUNT(*) FROM san_pham")->fetchColumn(),
    'active' => $pdo->query("SELECT COUNT(*) FROM san_pham WHERE trang_thai = 1")->fetchColumn(),
    'hidden' => $pdo->query("SELECT COUNT(*) FROM san_pham WHERE trang_thai = 0")->fetchColumn(),
    'low_stock' => $pdo->query("SELECT COUNT(*) FROM bien_the_san_pham WHERE so_luong_ton <= 5")->fetchColumn(),

];

// 2. Lấy các tham số lọc từ URL
$keyword = $_GET['keyword'] ?? '';
$stock_filter = $_GET['stock'] ?? '';
$cat_filter = isset($_GET['cat_id']) && $_GET['cat_id'] !== '' ? intval($_GET['cat_id']) : '';
$status_filter = isset($_GET['status']) && $_GET['status'] !== '' ? intval($_GET['status']) : '';

$limit = 10;
$page = isset($_GET['p']) ? max(1, intval($_GET['p'])) : 1;
$offset = ($page - 1) * $limit;

// 3. Xây dựng câu query động
$sql_base = "FROM san_pham sp LEFT JOIN danh_muc dm ON sp.danh_muc_id = dm.id WHERE 1=1";

// Lọc theo từ khóa
if (!empty($keyword)) {
    $sql_base .= " AND sp.ten LIKE :kw";
}

// Lọc theo danh mục
if (!empty($cat_filter)) {
    $sql_base .= " AND sp.danh_muc_id = :cat_id";
}

// Lọc theo trạng thái
if ($status_filter !== '') {
    $sql_base .= " AND sp.trang_thai = :status";
}

// Lọc theo tồn kho (Logic cũ của bạn)
if ($stock_filter === 'low') {
    $sql_base .= " AND (
        (SELECT COUNT(*) FROM bien_the_san_pham bt WHERE bt.san_pham_id = sp.id AND COALESCE((SELECT SUM(CASE WHEN kh.trang_thai = 1 THEN ckt.so_luong_ton ELSE 0 END) FROM chi_tiet_kho_hang ckt JOIN kho_hang kh ON ckt.kho_hang_id = kh.id WHERE ckt.bien_the_id = bt.id), 0) < 5) > 0
        OR 
        (SELECT COUNT(*) FROM bien_the_san_pham bt WHERE bt.san_pham_id = sp.id) = 0
    )";
}

// 4. Đếm tổng để phân trang
$stmtCount = $pdo->prepare("SELECT COUNT(*) " . $sql_base);
if (!empty($keyword)) $stmtCount->bindValue(':kw', "%$keyword%");
if (!empty($cat_filter)) $stmtCount->bindValue(':cat_id', $cat_filter, PDO::PARAM_INT);
if ($status_filter !== '') $stmtCount->bindValue(':status', $status_filter, PDO::PARAM_INT);
$stmtCount->execute();

$totalRecords = $stmtCount->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

// 5. Lấy dữ liệu sản phẩm
// [ĐÃ SỬA] Tính tổng tồn kho chỉ từ kho hoạt động
$sql = "SELECT sp.*, dm.ten AS ten_danh_muc,
        COALESCE((SELECT SUM(CASE WHEN kh.trang_thai = 1 THEN ckt.so_luong_ton ELSE 0 END) 
                  FROM chi_tiet_kho_hang ckt 
                  JOIN bien_the_san_pham bt ON ckt.bien_the_id = bt.id
                  JOIN kho_hang kh ON ckt.kho_hang_id = kh.id
                  WHERE bt.san_pham_id = sp.id), 0) AS tong_ton
        " . $sql_base . "
        ORDER BY sp.id DESC
        LIMIT :limit OFFSET :offset";

// --- [THÊM DÒNG NÀY VÀO ĐÂY] ---
$stmt = $pdo->prepare($sql); 
// ------------------------------

// Bind các tham số
if (!empty($keyword)) $stmt->bindValue(':kw', "%$keyword%");
if (!empty($cat_filter)) $stmt->bindValue(':cat_id', $cat_filter, PDO::PARAM_INT);
if ($status_filter !== '') $stmt->bindValue(':status', $status_filter, PDO::PARAM_INT);

$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

$variantsGrouped = [];
if (!empty($products)) {
    $productIds = array_column($products, 'id');
    $placeholders = implode(',', array_fill(0, count($productIds), '?'));
    $sqlVar = "SELECT * FROM bien_the_san_pham WHERE san_pham_id IN ($placeholders)";
    $stmtVar = $pdo->prepare($sqlVar);
    $stmtVar->execute($productIds);
    while ($row = $stmtVar->fetch(PDO::FETCH_ASSOC)) {
        $variantsGrouped[$row['san_pham_id']][] = $row;
    }
}
?>
<link rel="stylesheet" href="../assets/css/admin/product_list.css">
<div class="admin-page-container">
     <div class="page-header-title">
      <i class="fas fa-box-open"></i></i> Quản lý Sản Phẩm
    </div>
    
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon bg-blue"><i class="fa-solid fa-box"></i></div>
            <div class="stat-info"><span class="stat-label">Tổng sản phẩm</span><span class="stat-number"><?= $stats['total'] ?></span></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon bg-green"><i class="fa-solid fa-check-circle"></i></div>
            <div class="stat-info"><span class="stat-label">Đang hoạt động</span><span class="stat-number"><?= $stats['active'] ?></span></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon bg-gray"><i class="fa-solid fa-eye-slash"></i></div>
            <div class="stat-info"><span class="stat-label">Sản phẩm bị ẩn</span><span class="stat-number"><?= $stats['hidden'] ?></span></div>
        </div>
        <div class="stat-card" style="border-left: 4px solid #e74a3b;">
            <div class="stat-icon" style="background: #ffebeb; color: #e74a3b;"><i class="fa-solid fa-triangle-exclamation"></i></div>
            <div class="stat-info">
                <span class="stat-label" style="color: #e74a3b; font-weight: 700;">Sắp hết hàng</span>
                <span class="stat-number" style="color: #e74a3b;"><?= $stats['low_stock'] ?></span>
            </div>
        </div>
    </div>

    <div class="main-card-box">
       <div class="toolbar-section">
            <form method="GET" action="index.php" class="search-form" style="flex: 1; display: flex; gap: 10px; flex-wrap: wrap;">
                <input type="hidden" name="page" value="products_list">
                
                <div style="position: relative;">
                    <input type="text" name="keyword" class="search-input" placeholder="Tìm tên sản phẩm..." value="<?= htmlspecialchars($keyword) ?>">
                    <button type="submit" class="btn-search-icon"><i class="fa-solid fa-magnifying-glass"></i></button>
                </div>

                <select name="cat_id" class="filter-select" onchange="this.form.submit()">
                    <option value="">-- Tất cả Danh mục --</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $cat_filter == $cat['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['ten']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="status" class="filter-select" onchange="this.form.submit()">
                    <option value="">-- Trạng thái --</option>
                    <option value="1" <?= $status_filter === 1 ? 'selected' : '' ?>>Đang hoạt động</option>
                    <option value="0" <?= $status_filter === 0 ? 'selected' : '' ?>>Đã ẩn</option>
                </select>

                <select name="stock" class="filter-select" onchange="this.form.submit()">
                    <option value="">-- Tồn kho --</option>
                    <option value="low" <?= $stock_filter === 'low' ? 'selected' : '' ?>>Sắp hết hàng (< 5)</option>
                    </select>
                <?php if(!empty($keyword) || !empty($cat_filter) || $status_filter !== '' || !empty($stock_filter)): ?>
                    <a href="index.php?page=products_list" class="btn-reset" title="Xóa bộ lọc"><i class="fa-solid fa-rotate-right"></i></a>
                <?php endif; ?>
            </form>
            
            <a href="index.php?page=product_form" class="btn-add-new">
                <i class="fa-solid fa-plus"></i> Thêm mới
            </a>
        </div>

       <div class="table-responsive">
            <table class="table-custom">
                <thead>
                    <tr>
                        <th width="60" class="text-center">ID</th>
                        <th width="80">Ảnh</th>
                        <th Width="200">Tên sản phẩm</th> 
                        <th width="140">Giá (Min)</th>
                        <th width="15%">Danh mục</th>
                        <th width="10%" class="text-center">Kho hàng</th> <th width="120">Trạng thái</th>
                        <th width="180" class="text-end" style="text-align: center;">Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($products) == 0): ?>
                        <tr><td colspan="8" class="text-center text-muted" style="padding: 30px;">Không tìm thấy sản phẩm nào phù hợp.</td></tr>
                    <?php endif; ?>

                    <?php foreach ($products as $p): 
                        $vars = $variantsGrouped[$p['id']] ?? [];
                        $minPrice = !empty($vars) ? min(array_column($vars, 'gia')) : $p['gia'];
                    ?>
                    <tr>
                        <td class="text-center">
                            <span style="font-weight:600; color:#888;">#<?= $p['id'] ?></span>
                        </td>

                        <td class="clickable-row" onclick="toggleVariants(<?= $p['id'] ?>)">
                            <?php $img = !empty($p['hinh_anh']) ? $p['hinh_anh'] : 'no-image.jpg'; ?>
                            <img src="/HiShop/assets/img/products/<?= $img ?>" class="img-thumb" onerror="this.onerror=null;this.src='/HiShop/assets/img/no-image.png';">
                        </td>

                        <td class="clickable-row" onclick="toggleVariants(<?= $p['id'] ?>)">
                            <div style="display: flex; align-items: center; justify-content: space-between;">
                                <div>
                                    <div class="product-name fw-bold" style="font-size:14px;"><?= htmlspecialchars($p['ten']) ?></div>
                                    <small class="text-muted" style="font-size:12px;"><?= count($vars) ?> biến thể</small>
                                </div>
                            </div>
                        </td>

                        <td style="font-weight:700; color:#4e73df;"><?= number_format($minPrice) ?>đ</td>
                        <td><?= htmlspecialchars($p['ten_danh_muc']) ?></td>

                        <td class="text-center">
                            <?php if ($p['tong_ton'] > 0): ?>
                                <span style="background: #1cc88a; color: white; padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: bold;">
                                    Còn <?= $p['tong_ton'] ?>
                                </span>
                            <?php else: ?>
                                <span style="background: #e74a3b; color: white; padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: bold;">
                                    Hết hàng
                                </span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?php if($p['trang_thai'] == 1): ?>
                                <span class="badge-status active">Hoạt động</span>
                            <?php else: ?>
                                <span class="badge-status inactive">Đã ẩn</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <div class="btn-action-group">
                                <a href="index.php?page=product_form&id=<?= $p['id'] ?>" class="btn-action btn-edit">
                                    <i class="fa-solid fa-pen-to-square"></i> Sửa
                                </a>
                                <a href="index.php?page=products_list&toggle=<?= $p['id'] ?>" class="btn-action btn-toggle <?= $p['trang_thai'] == 0 ? 'hidden' : '' ?>">
                                    <?= $p['trang_thai'] == 1 ? '<i class="fa-solid fa-eye-slash"></i> Ẩn' : '<i class="fa-solid fa-eye"></i> Hiện' ?>
                                </a>
                            </div>
                        </td>
                    </tr>
                    
                    <tr id="variant-row-<?= $p['id'] ?>" class="variant-row" style="display:none;">
                        <td colspan="8">
                            <div class="variant-list">
                                <?php if(empty($vars)): ?>
                                    <span class="text-muted fst-italic">Chưa có biến thể nào.</span>
                                <?php endif; ?>
                                
                                <?php foreach ($vars as $v): ?>
                                <div class="variant-item">
                                    <?php $vImg = !empty($v['hinh_anh']) ? $v['hinh_anh'] : 'no-image.png'; ?>
                                    <img src="/HiShop/assets/img/products/<?= $vImg ?>" class="v-thumb" onerror="this.onerror=null;this.src='/HiShop/assets/img/no-image.png';">
                                    <div class="v-details">
                                        <strong style="color:#333;"><?= htmlspecialchars($v['mau_sac']) ?> - <?= htmlspecialchars($v['dung_luong_ssd']) ?></strong>
                                        <div style="color:#666;">
                                            Giá: <span style="color:#4e73df; font-weight:700;"><?= number_format($v['gia']) ?>đ</span>
                                            <span style="margin:0 5px; color:#ddd;">|</span>
                                            Kho: <span style="color:#e74a3b; font-weight:700;"><?= $v['so_luong_ton'] ?></span>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <?php 
            // Tạo chuỗi tham số URL để giữ lại các lựa chọn lọc khi chuyển trang
            // Lưu ý: Các biến $cat_filter, $status_filter... phải được định nghĩa ở phần PHP đầu file như hướng dẫn trước
            $queryParams = "&keyword=" . urlencode($keyword) . 
                        "&cat_id=" . urlencode($cat_filter) . 
                        "&status=" . urlencode($status_filter);
            ?>

            <div style="margin-top: 25px; display: flex; justify-content: flex-end;">
                <div style="display: flex; gap: 5px;">
                    <?php if ($page > 1): ?>
                        <a href="index.php?page=products_list&p=<?= $page - 1 ?><?= $queryParams ?>" 
                        class="pagination-btn">&laquo;</a>
                    <?php endif; ?>
                    
                    <?php 
                    $start = max(1, $page - 2); $end = min($totalPages, $page + 2);
                    for ($i = $start; $i <= $end; $i++): 
                    ?>
                        <a href="index.php?page=products_list&p=<?= $i ?><?= $queryParams ?>" 
                        class="pagination-btn <?= ($i == $page) ? 'active' : '' ?>">
                        <?= $i ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="index.php?page=products_list&p=<?= $page + 1 ?><?= $queryParams ?>" 
                        class="pagination-btn">&raquo;</a>
                    <?php endif; ?>
                </div>
            </div>

            <style>
                .pagination-btn {
                    padding: 6px 12px;
                    border: 1px solid #ddd;
                    border-radius: 6px;
                    color: #4e73df;
                    text-decoration: none;
                    background: white;
                    transition: all 0.2s;
                }
                .pagination-btn:hover {
                    background-color: #f1f1f1;
                }
                .pagination-btn.active {
                    background: #4e73df;
                    color: white;
                    border-color: #4e73df;
                }
            </style>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleVariants(id) {
    let row = document.getElementById('variant-row-' + id);
    let icon = document.getElementById('icon-' + id);
    
    if (row.style.display === 'none') {
        row.style.display = 'table-row';
        // Thêm class rotate để mũi tên xoay xuống
        icon.classList.add('rotate');
    } else {
        row.style.display = 'none';
        // Bỏ class rotate để mũi tên xoay về
        icon.classList.remove('rotate');
    }
}
</script>