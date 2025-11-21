<?php
// FILE: cart-handler.php
// ==============================================================
// Sửa lại để dùng gio_hang.id làm key, tính giá đúng biến thể + giảm giá
// ==============================================================

ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/src/config.php';

header('Content-Type: application/json; charset=utf-8');

$response = ['status' => 'error', 'message' => 'Lỗi không xác định.'];

if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.';
    sendResponse($response);
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

// Unified inputs
$product_id = (int)($_POST['id'] ?? 0);
$quantity = (int)($_POST['quantity'] ?? 1);
$cart_key_raw = $_POST['key'] ?? ''; // used as gio_hang.id for update/delete

try {

    /**
     * Helper: compute unit price for given product id + optional variant, applying product-level discount if any.
     * Returns float price (per unit after discount).
     */
    function compute_unit_price(PDO $pdo, $san_pham_id, $bien_the_id = null) {
        // Get base price: variant price if exists else product price
        if ($bien_the_id) {
            $stmt = $pdo->prepare("SELECT gia FROM bien_the_san_pham WHERE id = ? AND san_pham_id = ? LIMIT 1");
            $stmt->execute([$bien_the_id, $san_pham_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && $row['gia'] !== null) {
                $base = (float)$row['gia'];
            } else {
                // fallback to product price
                $stmt2 = $pdo->prepare("SELECT gia FROM san_pham WHERE id = ? LIMIT 1");
                $stmt2->execute([$san_pham_id]);
                $base = (float)$stmt2->fetchColumn();
            }
        } else {
            $stmt = $pdo->prepare("SELECT gia FROM san_pham WHERE id = ? LIMIT 1");
            $stmt->execute([$san_pham_id]);
            $base = (float)$stmt->fetchColumn();
        }

        // Check active discount for this product
        $stmtD = $pdo->prepare("
            SELECT g.loai_giam_gia, g.gia_tri
            FROM san_pham_giam_gia spg
            JOIN giam_gia g ON g.id = spg.giam_gia_id
            WHERE spg.san_pham_id = ?
              AND (g.ngay_bat_dau IS NULL OR g.ngay_bat_dau <= NOW())
              AND (g.ngay_ket_thuc IS NULL OR g.ngay_ket_thuc >= NOW())
            LIMIT 1
        ");
        $stmtD->execute([$san_pham_id]);
        $disc = $stmtD->fetch(PDO::FETCH_ASSOC);

        if ($disc) {
            if ($disc['loai_giam_gia'] === 'percent') {
                $base = $base - ($base * ((float)$disc['gia_tri'] / 100));
            } elseif ($disc['loai_giam_gia'] === 'amount') {
                $base = $base - (float)$disc['gia_tri'];
            }
        }

        if ($base < 0) $base = 0;
        return $base;
    }

    switch ($action) {

        // Apply coupon handled earlier in your file; keep it if needed.
        case 'apply_coupon':
            // (kept in original file) forward to existing logic
            $code = trim($_POST['code'] ?? '');

            if (empty($code)) {
                unset($_SESSION['promo']);
                $response['status'] = 'success';
                $response['message'] = 'Đã hủy mã giảm giá. Giá đơn hàng sẽ về mặc định.';
            } else {
                $sql = "SELECT * FROM ma_khuyen_mai 
                        WHERE ten = ? 
                        AND (ngay_bat_dau IS NULL OR ngay_bat_dau <= NOW())
                        AND (ngay_ket_thuc IS NULL OR ngay_ket_chuc >= NOW())";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$code]);
                $coupon = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($coupon) {
                    if (isset($coupon['so_luong']) && $coupon['so_luong'] <= 0) {
                        $response['status'] = 'error';
                        $response['message'] = 'Mã này đã hết lượt sử dụng.';
                    } else {
                        $_SESSION['promo'] = [
                            'id' => $coupon['id'],
                            'code' => $coupon['ten'],
                            'type' => $coupon['loai_khuyen_mai'],
                            'value' => (float)$coupon['gia_tri']
                        ];
                        $response['status'] = 'success';
                        $response['message'] = 'Áp dụng mã "' . htmlspecialchars($coupon['ten']) . '" thành công!';
                    }
                } else {
                    unset($_SESSION['promo']);
                    $response['status'] = 'error';
                    $response['message'] = 'Mã giảm giá không tồn tại hoặc đã hết hạn!';
                }
            }
            break;

        case 'add':
            // variant_id may be passed (frontend should send variant_id if user selected a variant)
            $variant_id = isset($_POST['variant_id']) && !empty($_POST['variant_id']) ? (int)$_POST['variant_id'] : null;
            $product_id_in = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            $qty = max(1, (int)($_POST['quantity'] ?? 1));

            // Validate product/variant
            if ($variant_id) {
                $stmtV = $pdo->prepare("SELECT id, san_pham_id, gia, so_luong_ton FROM bien_the_san_pham WHERE id = ? LIMIT 1");
                $stmtV->execute([$variant_id]);
                $variantRow = $stmtV->fetch(PDO::FETCH_ASSOC);
                if (!$variantRow) {
                    $response['message'] = 'Biến thể không tồn tại.';
                    break;
                }
                // ensure variant belongs to product if product id provided
                if ($product_id_in > 0 && (int)$variantRow['san_pham_id'] !== $product_id_in) {
                    $response['message'] = 'Biến thể không thuộc sản phẩm được chọn.';
                    break;
                }
                $parent_product_id = (int)$variantRow['san_pham_id'];
            } else {
                $parent_product_id = $product_id_in;
            }

            if ($parent_product_id <= 0) {
                $response['message'] = 'Sản phẩm không hợp lệ.';
                break;
            }

            // Check if a row already exists for this user/product/variant
            if ($variant_id) {
                $stmtExist = $pdo->prepare("SELECT id, so_luong FROM gio_hang WHERE nguoi_dung_id = ? AND san_pham_id = ? AND bien_the_id = ? LIMIT 1");
                $stmtExist->execute([$user_id, $parent_product_id, $variant_id]);
                $exist = $stmtExist->fetch(PDO::FETCH_ASSOC);
            } else {
                $stmtExist = $pdo->prepare("SELECT id, so_luong FROM gio_hang WHERE nguoi_dung_id = ? AND san_pham_id = ? AND (bien_the_id IS NULL OR bien_the_id = 0) LIMIT 1");
                $stmtExist->execute([$user_id, $parent_product_id]);
                $exist = $stmtExist->fetch(PDO::FETCH_ASSOC);
            }

            if ($exist) {
                // Update quantity
                $newQty = (int)$exist['so_luong'] + $qty;
                $stmtUp = $pdo->prepare("UPDATE gio_hang SET so_luong = ? WHERE id = ?");
                $stmtUp->execute([$newQty, $exist['id']]);
            } else {
                // Insert new
                $stmtIns = $pdo->prepare("INSERT INTO gio_hang (nguoi_dung_id, san_pham_id, bien_the_id, so_luong) VALUES (?, ?, ?, ?)");
                // if $variant_id is null, insert NULL
                $bv = $variant_id ? $variant_id : null;
                $stmtIns->execute([$user_id, $parent_product_id, $bv, $qty]);
            }

            $stmt_count = $pdo->prepare("SELECT COALESCE(SUM(so_luong),0) FROM gio_hang WHERE nguoi_dung_id = ?");
            $stmt_count->execute([$user_id]);
            $totalItems = (int)$stmt_count->fetchColumn();

            $response['status'] = 'success';
            $response['message'] = 'Đã thêm vào giỏ hàng thành công!';
            $response['totalItems'] = $totalItems;
            break;

        case 'update':
            // Here frontend should send key = gio_hang.id
            $cart_id = (int)$cart_key_raw;
            $qty = max(1, $quantity);

            if ($cart_id <= 0) {
                $response['message'] = 'ID giỏ hàng không hợp lệ.';
                break;
            }

            // Ensure the cart row belongs to this user
            $stmtRow = $pdo->prepare("SELECT id, san_pham_id, bien_the_id FROM gio_hang WHERE id = ? AND nguoi_dung_id = ? LIMIT 1");
            $stmtRow->execute([$cart_id, $user_id]);
            $cartRow = $stmtRow->fetch(PDO::FETCH_ASSOC);

            if (!$cartRow) {
                $response['message'] = 'Mục giỏ hàng không tồn tại.';
                break;
            }

            // Update quantity
            $stmtUp = $pdo->prepare("UPDATE gio_hang SET so_luong = ? WHERE id = ? AND nguoi_dung_id = ?");
            $stmtUp->execute([$qty, $cart_id, $user_id]);

            // Recompute unit price (with discount) and item total
            $san_pham_id = (int)$cartRow['san_pham_id'];
            $bien_the_id = $cartRow['bien_the_id'] ? (int)$cartRow['bien_the_id'] : null;

            $unit_price = compute_unit_price($pdo, $san_pham_id, $bien_the_id);
            $item_total_raw = $unit_price * $qty;

            // formatted
            $response['status'] = 'success';
            $response['item_total'] = number_format($item_total_raw, 0, ',', '.') . '₫';
            $response['unit_price'] = number_format($unit_price, 0, ',', '.') . '₫';
            $response['quantity'] = $qty;
            break;

        case 'delete':
            // frontend sends key = gio_hang.id
            $cart_id = (int)$cart_key_raw;
            if ($cart_id <= 0) {
                $response['message'] = 'ID giỏ hàng không hợp lệ.';
                break;
            }

            // ensure belongs to user
            $stmtRow = $pdo->prepare("SELECT id FROM gio_hang WHERE id = ? AND nguoi_dung_id = ? LIMIT 1");
            $stmtRow->execute([$cart_id, $user_id]);
            if (!$stmtRow->fetch()) {
                $response['message'] = 'Mục giỏ hàng không tồn tại.';
                break;
            }

            $stmtDel = $pdo->prepare("DELETE FROM gio_hang WHERE id = ? AND nguoi_dung_id = ?");
            $stmtDel->execute([$cart_id, $user_id]);

            $stmt_count = $pdo->prepare("SELECT COUNT(id) FROM gio_hang WHERE nguoi_dung_id = ?");
            $stmt_count->execute([$user_id]);
            $response['status'] = 'success';
            $response['cart_empty'] = $stmt_count->fetchColumn() == 0;
            break;

        default:
            $response['message'] = 'Hành động không hợp lệ.';
            break;
    }

} catch (Exception $e) {
    $response['message'] = 'Lỗi Server: ' . $e->getMessage();
}

sendResponse($response);

function sendResponse($data) {
    // Clean output buffer to avoid broken JSON
    while (ob_get_level() > 0) ob_end_clean();
    echo json_encode($data);
    exit;
}
?>
