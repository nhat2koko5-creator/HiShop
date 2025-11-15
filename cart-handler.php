<?php
// cart-handler.php (API) — PHẢI CHẠY ĐỘC LẬP (KHÔNG include header.php)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load config / pdo
require_once __DIR__ . '/src/config.php';

// Khởi tạo giỏ hàng
if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
if (!isset($_SESSION['promo'])) $_SESSION['promo'] = null; 

// Helper
function getCartTotalItems() {
    $total_cart_items = 0;
    if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $item) {
            $total_cart_items += (int)$item['quantity'];
        }
    }
    return $total_cart_items;
}
if (!function_exists('price_format')) {
    function price_format($n) {
        return number_format($n, 0, ',', '.') . '₫';
    }
}

$action = $_POST['action'] ?? $_GET['action'] ?? 'view';

try {
    switch ($action) {

        case 'add':
            header('Content-Type: application/json');
            $product_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
            $color = isset($_POST['color']) ? trim($_POST['color']) : '';
            $ssd = isset($_POST['ssd']) ? trim($_POST['ssd']) : '';

            if ($product_id <= 0 || $quantity <= 0 || empty($color) || empty($ssd)) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Dữ liệu không hợp lệ.']);
                exit;
            }

            $stmt = $pdo->prepare("SELECT ten, gia, hinh_anh, so_luong FROM san_pham WHERE id = ? AND trang_thai = 1");
            $stmt->execute([$product_id]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$product) {
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => 'Sản phẩm không tồn tại.']);
                exit;
            }

            $cart_key = $product_id . '_' . md5($color . $ssd);

            if (isset($_SESSION['cart'][$cart_key])) {
                $new_quantity = $_SESSION['cart'][$cart_key]['quantity'] + $quantity;
                if ($product['so_luong'] < $new_quantity) {
                    http_response_code(409);
                    echo json_encode(['status' => 'error', 'message' => "Số lượng tồn kho không đủ (chỉ còn {$product['so_luong']})."]);
                    exit;
                }
                $_SESSION['cart'][$cart_key]['quantity'] = $new_quantity;
            } else {
                if ($product['so_luong'] < $quantity) {
                    http_response_code(409);
                    echo json_encode(['status' => 'error', 'message' => "Số lượng tồn kho không đủ (chỉ còn {$product['so_luong']})."]);
                    exit;
                }
                $_SESSION['cart'][$cart_key] = [
                    'product_id' => $product_id,
                    'name'       => $product['ten'],
                    'price'      => (float)$product['gia'],
                    'quantity'   => (int)$quantity,
                    'image'      => $product['hinh_anh'],
                    'color'      => $color,
                    'ssd'        => $ssd
                ];
            }

            echo json_encode(['status' => 'success', 'totalItems' => getCartTotalItems()]);
            exit;

        case 'update':
            header('Content-Type: application/json');
            $cart_key = $_POST['key'] ?? '';
            $quantity = (int)($_POST['quantity'] ?? 0);

            if (!empty($cart_key) && $quantity > 0 && isset($_SESSION['cart'][$cart_key])) {
                $product_id = $_SESSION['cart'][$cart_key]['product_id'];
                $stmt = $pdo->prepare("SELECT so_luong FROM san_pham WHERE id = ?");
                $stmt->execute([$product_id]);
                $stock = $stmt->fetchColumn();

                if ($stock === false) { 
                    unset($_SESSION['cart'][$cart_key]);
                    echo json_encode(['status' => 'error', 'message' => 'Sản phẩm không còn tồn tại.', 'totalItems' => getCartTotalItems()]);
                    exit;
                }
                if ($quantity > $stock) $quantity = $stock;

                $_SESSION['cart'][$cart_key]['quantity'] = $quantity;
                $item_total = $_SESSION['cart'][$cart_key]['price'] * $quantity;

                echo json_encode(['status' => 'success','item_total' => price_format($item_total),'totalItems' => getCartTotalItems()]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Lỗi cập nhật.']);
            }
            exit;

        case 'delete':
            header('Content-Type: application/json');
            $cart_key = $_POST['key'] ?? '';
            if (!empty($cart_key) && isset($_SESSION['cart'][$cart_key])) {
                unset($_SESSION['cart'][$cart_key]);
                echo json_encode(['status' => 'success','cart_empty' => empty($_SESSION['cart']),'totalItems' => getCartTotalItems()]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Lỗi khi xóa.']);
            }
            exit;

        case 'apply_coupon':
            header('Content-Type: application/json');
            $code = trim($_POST['code'] ?? '');
            $stmt = $pdo->prepare("SELECT * FROM ma_khuyen_mai WHERE ten = ? AND ngay_ket_thuc >= CURDATE()");
            $stmt->execute([$code]);
            $coupon = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($coupon) {
                $coupon_data = ['code'=>$coupon['ten'],'type'=>$coupon['loai_khuyen_mai'],'value'=>(float)$coupon['gia_tri']];
                $_SESSION['promo'] = $coupon_data;
                echo json_encode(['status'=>'success','message'=>'Áp dụng mã thành công!','coupon'=>$coupon_data]);
            } else {
                $_SESSION['promo'] = null;
                echo json_encode(['status'=>'error','message'=>'Mã không hợp lệ hoặc đã hết hạn.','coupon'=>null]);
            }
            exit;

        case 'set_selected':
            header('Content-Type: application/json');
            $keys_json = $_POST['keys'] ?? '[]';
            $selected_keys = json_decode($keys_json, true);
            if (is_array($selected_keys)) {
                $_SESSION['selected_cart_keys'] = $selected_keys;
                echo json_encode(['status'=>'success']);
            } else {
                echo json_encode(['status'=>'error','message'=>'Dữ liệu không hợp lệ.']);
            }
            exit;

        default:
            header('Content-Type: application/json');
            echo json_encode(['status'=>'error','message'=>'Hành động không hợp lệ.']);
            exit;
    }
} catch (PDOException $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Lỗi CSDL: ' . $e->getMessage()]);
    exit();
}
