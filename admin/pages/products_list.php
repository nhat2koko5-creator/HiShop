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

// A. THÊM SẢN PHẨM MỚI
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_product') {
    $ten = trim($_POST['ten']);
    $mo_ta = $_POST['mo_ta'];
    $danh_muc = $_POST['danh_muc'];
    $variants = json_decode($_POST['variants_json'], true);

    $tong_sl = 0; $gia_min = 0;
    if (!empty($variants)) {
        $tong_sl = array_sum(array_column($variants, 'ton'));
        $gia_min = min(array_column($variants, 'gia'));
    }

    $hinh_anh = "";
    if (!empty($_FILES['hinh_anh']['name'])) {
        $fileName = time() . "_" . basename($_FILES["hinh_anh"]["name"]);
        move_uploaded_file($_FILES["hinh_anh"]["tmp_name"], "../assets/img/products/" . $fileName);
        $hinh_anh = $fileName;
    }

    $stmt = $pdo->prepare("INSERT INTO san_pham (ten, gia, so_luong, hinh_anh, mo_ta, trang_thai, danh_muc_id) VALUES (?, ?, ?, ?, ?, 1, ?)");
    $stmt->execute([$ten, $gia_min, $tong_sl, $hinh_anh, $mo_ta, $danh_muc]);
    $product_id = $pdo->lastInsertId();

    // [MỚI] LƯU THÔNG SỐ KỸ THUẬT
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
            $stmt2 = $pdo->prepare("INSERT INTO bien_the_san_pham (san_pham_id, mau_sac, dung_luong_ssd, gia, so_luong_ton, hinh_anh) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt2->execute([$product_id, $v['mau'], $v['ssd'], $v['gia'], $v['ton'], $imgName]);
        }
    }
    js_redirect("index.php?page=products_list&added=1");
}

// B. CẬP NHẬT SẢN PHẨM
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_product') {
    $id = intval($_POST['product_id']);
    $ten = trim($_POST['ten']);
    $mo_ta = $_POST['mo_ta'];
    $danh_muc = $_POST['danh_muc'];
    $variants = json_decode($_POST['variants_json'], true);

    $tong_sl = 0; $gia_min = 0;
    if (!empty($variants)) {
        $tong_sl = array_sum(array_column($variants, 'ton'));
        $gia_min = min(array_column($variants, 'gia'));
    }

    $sql_update = "UPDATE san_pham SET ten=?, gia=?, so_luong=?, mo_ta=?, danh_muc_id=?";
    $params = [$ten, $gia_min, $tong_sl, $mo_ta, $danh_muc];

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

    // [MỚI] CẬP NHẬT THÔNG SỐ KỸ THUẬT
    $specs = [
        'man_hinh' => $_POST['man_hinh'] ?? '',
        'cpu'      => $_POST['cpu'] ?? '',
        'ram'      => $_POST['ram'] ?? '',
        'gpu'      => $_POST['gpu'] ?? '',
        'o_cung'   => $_POST['o_cung'] ?? ''
    ];
    saveProductSpecs($pdo, $id, $specs);

    // CẬP NHẬT BIẾN THỂ (Xóa hết thêm lại)
    $pdo->prepare("DELETE FROM bien_the_san_pham WHERE san_pham_id=?")->execute([$id]);

    if (!empty($variants)) {
        foreach ($variants as $v) {
            $imgName = $v['old_img']; 
            $idx = $v['img_index']; 
            if (!empty($_FILES['variant_imgs']['name'][$idx])) {
                $newImgName = time() . "_" . basename($_FILES['variant_imgs']['name'][$idx]);
                move_uploaded_file($_FILES['variant_imgs']['tmp_name'][$idx], "../assets/img/products/" . $newImgName);
                $imgName = $newImgName; 
            }
            $stmt2 = $pdo->prepare("INSERT INTO bien_the_san_pham (san_pham_id, mau_sac, dung_luong_ssd, gia, so_luong_ton, hinh_anh) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt2->execute([$id, $v['mau'], $v['ssd'], $v['gia'], $v['ton'], $imgName]);
        }
    }
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
// 3. LẤY DỮ LIỆU (GET)
// =================================================================
$stats = [
    'total' => $pdo->query("SELECT COUNT(*) FROM san_pham")->fetchColumn(),
    'active' => $pdo->query("SELECT COUNT(*) FROM san_pham WHERE trang_thai = 1")->fetchColumn(),
    'hidden' => $pdo->query("SELECT COUNT(*) FROM san_pham WHERE trang_thai = 0")->fetchColumn()
];

$keyword = $_GET['keyword'] ?? '';
$limit = 10;
$page = isset($_GET['p']) ? max(1, intval($_GET['p'])) : 1;
$offset = ($page - 1) * $limit;

$sqlCount = "SELECT COUNT(*) FROM san_pham WHERE ten LIKE :kw";
$stmtCount = $pdo->prepare($sqlCount);
$stmtCount->execute(['kw' => "%$keyword%"]);
$totalRecords = $stmtCount->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

$sql = "SELECT sp.*, dm.ten AS ten_danh_muc,
        (SELECT SUM(so_luong_ton) FROM bien_the_san_pham WHERE san_pham_id = sp.id) AS tong_bien_the
        FROM san_pham sp 
        LEFT JOIN danh_muc dm ON sp.danh_muc_id = dm.id
        WHERE sp.ten LIKE :kw
        ORDER BY sp.id DESC
        LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);
$stmt->bindValue(':kw', "%$keyword%", PDO::PARAM_STR);
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
    </div>

    <div class="main-card-box">
        <div class="toolbar-section">
            <form method="GET" action="index.php" class="search-form">
                <input type="hidden" name="page" value="products_list">
                <input type="text" name="keyword" class="search-input" placeholder="Tìm kiếm sản phẩm..." value="<?= htmlspecialchars($keyword) ?>">
                <button type="submit" class="btn-search-icon"><i class="fa-solid fa-magnifying-glass"></i></button>
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
                        <th>Tên sản phẩm</th> <th width="140">Giá (Min)</th>
                        <th width="100">Kho</th>
                        <th>Danh mục</th>
                        <th width="120">Trạng thái</th>
                        <th width="180" class="text-end">Hành động</th>
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
                        <td>
                            <span style="font-weight:600; color: <?= ($p['tong_bien_the'] < 10) ? '#e74a3b' : '#333' ?>">
                                <?= number_format($p['tong_bien_the']) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($p['ten_danh_muc']) ?></td>
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
        <div style="margin-top: 25px; display: flex; justify-content: flex-end;">
            <div style="display: flex; gap: 5px;">
                <?php if ($page > 1): ?>
                    <a href="index.php?page=products_list&p=<?= $page - 1 ?>&keyword=<?= htmlspecialchars($keyword) ?>" 
                       style="padding: 6px 12px; border: 1px solid #ddd; border-radius: 6px; color: #4e73df; text-decoration: none; background: white;">&laquo;</a>
                <?php endif; ?>
                
                <?php 
                $start = max(1, $page - 2); $end = min($totalPages, $page + 2);
                for ($i = $start; $i <= $end; $i++): 
                ?>
                    <a href="index.php?page=products_list&p=<?= $i ?>&keyword=<?= htmlspecialchars($keyword) ?>" 
                       style="padding: 6px 12px; border: 1px solid <?= ($i == $page) ? '#4e73df' : '#ddd' ?>; border-radius: 6px; text-decoration: none; 
                              background: <?= ($i == $page) ? '#4e73df' : 'white' ?>; color: <?= ($i == $page) ? 'white' : '#4e73df' ?>;">
                       <?= $i ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($page < $totalPages): ?>
                    <a href="index.php?page=products_list&p=<?= $page + 1 ?>&keyword=<?= htmlspecialchars($keyword) ?>" 
                       style="padding: 6px 12px; border: 1px solid #ddd; border-radius: 6px; color: #4e73df; text-decoration: none; background: white;">&raquo;</a>
                <?php endif; ?>
            </div>
        </div>
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