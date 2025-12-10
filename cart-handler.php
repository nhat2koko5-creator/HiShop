<?php
// FILE: cart-handler.php (ĐÃ FIX SẠCH SẼ)
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/src/config.php';
// [KHUYẾN NGHỊ] Nên bỏ comment dòng dưới để dùng chung hàm với hệ thống
// require_once __DIR__ . '/src/functions.php'; 

header('Content-Type: application/json; charset=utf-8');

$response = ['status' => 'error', 'message' => 'Lỗi không xác định.'];

// GIẢ ĐỊNH HÀM getCouponByCode ĐÃ ĐƯỢC LOAD
if (!function_exists('getCouponByCode')) {
    function getCouponByCode(PDO $pdo, $code) {
        try {
            // [ĐÃ SỬA] Thêm so_luong, da_dung vào SELECT để logic check số lượng hoạt động
            $sql = "SELECT ten, loai_khuyen_mai, gia_tri, dieu_kien, so_luong, da_dung 
                    FROM ma_khuyen_mai 
                    WHERE ten = ? 
                    AND (ngay_bat_dau IS NULL OR ngay_bat_dau <= NOW())
                    AND (ngay_ket_thuc IS NULL OR ngay_ket_thuc >= NOW())
                    LIMIT 1";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([strtoupper(trim($code))]); 
            return $stmt->fetch(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("Lỗi truy vấn coupon: " . $e->getMessage());
            return null;
        }
    }
}

if (isset($_POST['action']) && $_POST['action'] === 'buy') {
    $_POST['action'] = 'add';
}

if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'Bạn cần đăng nhập trước.';
    sendResponse($response);
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';
$product_id = (int)($_POST['id'] ?? 0);
$quantity = (int)($_POST['quantity'] ?? 1);
$cart_id_raw = $_POST['key'] ?? null;


/* =======================================================
    HÀM TÍNH GIÁ (CÓ GIẢM GIÁ + GIÁ BIẾN THỂ)
======================================================= */
function compute_unit_price(PDO $pdo, $product_id, $variant_id = null)
{
    // ... (Giữ nguyên logic cũ của bạn) ...
    if ($variant_id) {
        $stmt = $pdo->prepare("SELECT gia FROM bien_the_san_pham WHERE id = ? AND san_pham_id = ?");
        $stmt->execute([$variant_id, $product_id]);
        $gia = $stmt->fetchColumn();
        $base_price = ($gia !== false) ? (float)$gia : 0;
        
        if ($gia === false) {
             $stmt2 = $pdo->prepare("SELECT gia FROM san_pham WHERE id = ?");
             $stmt2->execute([$product_id]);
             $base_price = (float)$stmt2->fetchColumn();
        }
    } else {
        $stmt = $pdo->prepare("SELECT gia FROM san_pham WHERE id = ?");
        $stmt->execute([$product_id]);
        $base_price = (float)$stmt->fetchColumn();
    }

    $stmtD = $pdo->prepare("
        SELECT g.loai_giam_gia, g.gia_tri
        FROM san_pham_giam_gia spg
        JOIN giam_gia g ON g.id = spg.giam_gia_id
        WHERE spg.san_pham_id = ?
          AND (g.ngay_bat_dau IS NULL OR g.ngay_bat_dau <= NOW())
          AND (g.ngay_ket_thuc IS NULL OR g.ngay_ket_thuc >= NOW())
        LIMIT 1
    ");
    $stmtD->execute([$product_id]);
    $discount = $stmtD->fetch(PDO::FETCH_ASSOC);

    if ($discount) {
        if ($discount['loai_giam_gia'] === 'percent') {
            $base_price -= ($base_price * ((float)$discount['gia_tri'] / 100));
        } else {
            $base_price -= (float)$discount['gia_tri'];
        }
    }

    return max($base_price, 0);
}

/* =======================================================
    HÀM TRẢ VỀ TỔNG SỐ SẢN PHẨM TRONG GIỎ
======================================================= */
function getCartCount(PDO $pdo, $user_id)
{
    $stmt = $pdo->prepare("SELECT SUM(so_luong) FROM gio_hang WHERE nguoi_dung_id = ?");
    $stmt->execute([$user_id]);
    return (int)$stmt->fetchColumn();
}


/* =======================================================
    XỬ LÝ ACTION
======================================================= */
switch ($action) {

    /* -----------------------
        ADD TO CART
    ----------------------- */
    case 'add':
        $variant_id = isset($_POST['variant_id']) && $_POST['variant_id'] !== "" 
                         ? (int)$_POST['variant_id'] : null;

        if ($product_id <= 0) {
            $response['message'] = 'Sản phẩm không hợp lệ.';
            break;
        }

        if ($variant_id) {
            $stmt = $pdo->prepare("SELECT id, so_luong FROM gio_hang WHERE nguoi_dung_id = ? AND san_pham_id = ? AND bien_the_id = ? LIMIT 1");
            $stmt->execute([$user_id, $product_id, $variant_id]);
        } else {
            $stmt = $pdo->prepare("SELECT id, so_luong FROM gio_hang WHERE nguoi_dung_id = ? AND san_pham_id = ? AND bien_the_id IS NULL LIMIT 1");
            $stmt->execute([$user_id, $product_id]);
        }
        $exist = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($exist) {
            $newQty = $exist['so_luong'] + $quantity;
            $stmtUp = $pdo->prepare("UPDATE gio_hang SET so_luong = ? WHERE id = ?");
            $stmtUp->execute([$newQty, $exist['id']]);
        } else {
            $stmtIns = $pdo->prepare("INSERT INTO gio_hang (nguoi_dung_id, san_pham_id, bien_the_id, so_luong) VALUES (?, ?, ?, ?)");
            $stmtIns->execute([$user_id, $product_id, $variant_id ?: null, $quantity]);
        }

        $currentCount = getCartCount($pdo, $user_id);
        $_SESSION['global_cart_count'] = $currentCount; 
        
        $response['status'] = 'success';
        $response['message'] = 'Đã thêm vào giỏ hàng';
        $response['cart_count'] = $currentCount;
        break;


    /* -----------------------
        UPDATE QUANTITY
    ----------------------- */
    case 'update':
        $cart_id = (int)$cart_id_raw;
        $stmt = $pdo->prepare("SELECT san_pham_id, bien_the_id FROM gio_hang WHERE id = ? AND nguoi_dung_id = ?");
        $stmt->execute([$cart_id, $user_id]);
        $cartRow = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$cartRow) {
            $response['message'] = 'Mục giỏ hàng không tồn tại.';
            break;
        }

        $pdo->prepare("UPDATE gio_hang SET so_luong = ? WHERE id = ?")->execute([$quantity, $cart_id]);
        $price = compute_unit_price($pdo, $cartRow['san_pham_id'], $cartRow['bien_the_id']);

        $currentCount = getCartCount($pdo, $user_id);
        $_SESSION['global_cart_count'] = $currentCount; 

        $response = [
            'status' => 'success',
            'quantity' => $quantity,
            'unit_price' => number_format($price, 0, ',', '.') . '₫',
            'item_total' => number_format($price * $quantity, 0, ',', '.') . '₫',
            'cart_count' => $currentCount
        ];
        break;


    /* -----------------------
        DELETE
    ----------------------- */
    case 'delete':
        $cart_id = (int)$cart_id_raw;
        $pdo->prepare("DELETE FROM gio_hang WHERE id = ? AND nguoi_dung_id = ?")->execute([$cart_id, $user_id]);

        $currentCount = getCartCount($pdo, $user_id);
        $_SESSION['global_cart_count'] = $currentCount; 

        $response['status'] = 'success';
        $response['cart_count'] = $currentCount;
        break;

    
    /* -----------------------
        APPLY COUPON (ĐÃ FIX CHECK LIMIT)
    ----------------------- */
   case 'apply_coupon':
        $coupon_code_input = $_POST['code'] ?? '';
        
        if (empty($coupon_code_input)) {
            $response['message'] = 'Vui lòng nhập mã giảm giá.';
            unset($_SESSION['promo']); 
            break;
        }

        $coupon = getCouponByCode($pdo, $coupon_code_input);

        if (!$coupon) {
            $response['message'] = 'Mã **' . htmlspecialchars($coupon_code_input) . '** không hợp lệ, hết hạn hoặc không tồn tại.';
            unset($_SESSION['promo']);
            break;
        }
        
        // --- 1. TÍNH TỔNG TIỀN HIỆN TẠI ---
        $current_total = 0;

        if (isset($_SESSION['buy_now_item']) && !empty($_SESSION['buy_now_item'])) {
            $item = $_SESSION['buy_now_item'];
            $current_total = (float)$item['gia'] * (int)$item['so_luong'];
        } else {
            $stmtC = $pdo->prepare("SELECT san_pham_id, bien_the_id, so_luong FROM gio_hang WHERE nguoi_dung_id = ?");
            $stmtC->execute([$user_id]);
            $cart_items = $stmtC->fetchAll(PDO::FETCH_ASSOC);

            foreach ($cart_items as $item) {
                $price = compute_unit_price($pdo, $item['san_pham_id'], $item['bien_the_id']);
                $current_total += $price * $item['so_luong'];
            }
        }

        // --- 2. CHECK ĐIỀU KIỆN TIỀN TỐI THIỂU ---
        $min_condition = (float)$coupon['dieu_kien'];
        if ($current_total < $min_condition) {
            $response['message'] = 'Mã này chỉ áp dụng cho đơn hàng từ ' . number_format($min_condition, 0, ',', '.') . '₫ trở lên.';
            unset($_SESSION['promo']);
            break;
        }

        // --- 3. CHECK SỐ LƯỢNG GIỚI HẠN (FIX LOGIC) ---
        $limit = isset($coupon['so_luong']) ? (int)$coupon['so_luong'] : 0;
        $used  = isset($coupon['da_dung']) ? (int)$coupon['da_dung'] : 0;

        if ($limit > 0 && $used >= $limit) {
            $response['message'] = 'Rất tiếc, mã khuyến mãi này đã hết lượt sử dụng.';
            unset($_SESSION['promo']);
            break;
        }

        // --- 4. THÀNH CÔNG: LƯU SESSION ---
        $_SESSION['promo'] = [
            'code' => $coupon['ten'],
            'type' => $coupon['loai_khuyen_mai'],
            'value' => (float)$coupon['gia_tri'],
            'min_order' => $min_condition
        ];
        
        $response['status'] = 'success'; 
        $response['message'] = 'Áp dụng mã ' . htmlspecialchars($coupon['ten']) . ' thành công!';
        break;
    
    
    /* -----------------------
        REMOVE COUPON
    ----------------------- */
    case 'remove_coupon':
        unset($_SESSION['promo']);
        $response['status'] = 'success';
        $response['message'] = 'Mã giảm giá đã được gỡ bỏ.';
        break;


    default:
        $response['message'] = 'Hành động không hợp lệ.';
}


/* =======================================================
    TRẢ JSON
======================================================= */
sendResponse($response);


function sendResponse($data)
{
    while (ob_get_level() > 0) ob_end_clean();
    echo json_encode($data);
    exit;
}