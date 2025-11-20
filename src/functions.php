<?php
// FILE: src/functions.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Lấy tất cả danh mục đang hoạt động từ CSDL.
 * Khớp với bảng: `danh_muc`
 */

/* ---------------------------
    LẤY DANH MỤC ĐANG HOẠT ĐỘNG
----------------------------*/
function getActiveCategories(PDO $pdo) {
    $stmt = $pdo->query("SELECT id, ten FROM danh_muc WHERE trang_thai = 1 ORDER BY ten ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
/* =============================
   LẤY SẢN PHẨM NỔI BẬT
   ============================= */
function getFeaturedProducts($pdo) {
    $sql = "
        SELECT sp.*
        FROM san_pham sp
        INNER JOIN san_pham_noi_bat nb ON sp.id = nb.san_pham_id
        ORDER BY nb.noi_bat_id DESC
        LIMIT 8
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/* =============================
   LẤY SẢN PHẨM GIẢM GIÁ
   ============================= */

/* =============================
   TÍNH GIÁ SAU KHI GIẢM
   ============================= */
function calcDiscountPrice($gia, $loai, $gia_tri) {
    if ($loai === 'percent') {
        return $gia - ($gia * ($gia_tri / 100));
    }
    if ($loai === 'amount') {
        return max(0, $gia - $gia_tri);
    }
    return $gia;
}
function getProductVariants(PDO $pdo, $productId) {
    $sql = "SELECT * FROM bien_the_san_pham WHERE san_pham_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$productId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
function getDiscountProducts($pdo) {
    $sql = "
        SELECT 
            sp.id,
            sp.ten,
            sp.hinh_anh,
            sp.gia,

            gg.loai_giam_gia,
            gg.gia_tri,

            gg.ngay_bat_dau,
            gg.ngay_ket_thuc,

            CASE 
                WHEN gg.loai_giam_gia = 'percent' THEN gg.gia_tri
                WHEN gg.loai_giam_gia = 'amount' THEN ROUND(gg.gia_tri / sp.gia * 100)
                ELSE 0
            END AS giam_phan_tram,

            CASE 
                WHEN gg.loai_giam_gia = 'percent' THEN sp.gia - (sp.gia * gg.gia_tri / 100)
                WHEN gg.loai_giam_gia = 'amount' THEN sp.gia - gg.gia_tri
                ELSE sp.gia
            END AS gia_da_giam

        FROM san_pham sp
        JOIN san_pham_giam_gia spgg ON spgg.san_pham_id = sp.id
        JOIN giam_gia gg ON gg.id = spgg.giam_gia_id

        WHERE 
            gg.ngay_bat_dau <= NOW()
            AND gg.ngay_ket_thuc >= NOW();
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 🔥 LẤY BIẾN THỂ CHUẨN THEO BẢNG bien_the_san_pham
    foreach ($products as &$p) {
        $variantStmt = $pdo->prepare("
            SELECT 
                id,
                mau_sac,
                dung_luong_ssd,
                gia,
                so_luong_ton,
                hinh_anh
            FROM bien_the_san_pham
            WHERE san_pham_id = ?
        ");
        $variantStmt->execute([$p['id']]);
        $p['variants'] = $variantStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    return $products;
}

/* ================================================
    LẤY SẢN PHẨM MỚI NHẤT
=================================================*/
function getNewProducts(PDO $pdo){
    $stmt = $pdo->query("SELECT * FROM san_pham WHERE trang_thai = 1 ORDER BY id DESC LIMIT 8");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


/* =====================================================
    LẤY CHI TIẾT SẢN PHẨM (ĐÃ BỔ SUNG GIÁ GIẢM)
=====================================================*/
function getProductDetails(PDO $pdo, $id) {
    $sql = "
        SELECT 
            sp.*,
            sp.gia AS gia_goc,
            gg.loai_giam_gia,
            gg.gia_tri

        FROM san_pham sp
        LEFT JOIN san_pham_giam_gia spgg ON sp.id = spgg.san_pham_id
        LEFT JOIN giam_gia gg ON gg.id = spgg.giam_gia_id
            AND gg.ngay_bat_dau <= NOW()
            AND gg.ngay_ket_thuc >= NOW()

        WHERE sp.id = ?
        LIMIT 1
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $sp = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sp) return null;

    return array_merge($sp, apply_discount($sp));
}

/* ------ Các hàm user, cart, coupon giữ nguyên ------ */
/**
 * LẤY CÁC SẢN PHẨM LIÊN QUAN (cho trang PDP)
 */
function getRelatedProducts(PDO $pdo, $category_id, $current_product_id) {
    try {
        $sql = "
            SELECT 
                sp.id, sp.ten, sp.gia AS gia_goc, sp.hinh_anh,
                gg.loai_giam_gia, gg.gia_tri AS gia_tri_giam,
                CASE 
                    WHEN gg.loai_giam_gia = 'percent' THEN sp.gia * (1 - gg.gia_tri / 100)
                    WHEN gg.loai_giam_gia = 'amount' THEN sp.gia - gg.gia_tri
                    ELSE NULL 
                END AS gia_moi
            FROM san_pham AS sp
            LEFT JOIN san_pham_giam_gia AS spgg ON sp.id = spgg.san_pham_id
            LEFT JOIN giam_gia AS gg ON spgg.giam_gia_id = gg.id 
                 AND gg.ngay_bat_dau <= NOW() 
                 AND gg.ngay_ket_thuc >= NOW()
            WHERE 
                sp.trang_thai = 1 
                AND sp.danh_muc_id = ?
                AND sp.id != ?
            GROUP BY sp.id
            ORDER BY RAND() 
            LIMIT 4;
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$category_id, $current_product_id]);
        return $stmt->fetchAll();

    } catch (PDOException $e) {
        error_log($e->getMessage());
        return [];
    }
}

/**
 * Lấy thông tin chi tiết của một người dùng.
 */
function getUserProfile(PDO $pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("SELECT ho_ten, email, so_dien_thoai, ngay_sinh, gioi_tinh 
                              FROM nguoi_dung WHERE id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log($e->getMessage());
        return null;
    }
}

/**
 * Cập nhật thông tin cá nhân của người dùng.
 */
function updateUserProfile(PDO $pdo, $user_id, $ho_ten, $so_dien_thoai, $ngay_sinh, $gioi_tinh) {
    try {
        $sql = "UPDATE nguoi_dung 
                SET ho_ten = ?, so_dien_thoai = ?, ngay_sinh = ?, gioi_tinh = ?
                WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        
        $so_dien_thoai = empty($so_dien_thoai) ? null : $so_dien_thoai;
        $ngay_sinh = empty($ngay_sinh) ? null : $ngay_sinh;

        $stmt->execute([$ho_ten, $so_dien_thoai, $ngay_sinh, $gioi_tinh, $user_id]);
        return true; 
    } catch (PDOException $e) {
        error_log($e->getMessage());
        return false; 
    }
}

/**
 * Lấy lịch sử đơn hàng của người dùng.
 */
function getUserOrders(PDO $pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("SELECT id, ngay_dat, tong_tien, trang_thai 
                              FROM don_hang WHERE nguoi_dung_id = ? 
                              ORDER BY ngay_dat DESC");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log($e->getMessage());
        return [];
    }
}

/**
 * Lấy danh sách địa chỉ của người dùng.
 */
function getUserAddresses(PDO $pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("SELECT id, dia_chi_cu_the 
                              FROM dia_chi WHERE nguoi_dung_id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log($e->getMessage());
        return [];
    }
}

/**
 * (ĐÃ SỬA LỖI) Hàm gửi email chung cho dự án
 */
function sendEmail($to_email, $to_name, $subject, $body) {
    // Giờ đây chỉ cần gọi 'new PHPMailer' (không cần '\')
    $mail = new PHPMailer(true); 
    
    try {
        // Cấu hình Server (SMTP)
        // $mail->SMTPDebug = SMTP::DEBUG_SERVER; // Dùng hằng số 'SMTP' (không cần '\')
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        // Dùng hằng số 'PHPMailer' (không cần '\')
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; 
        $mail->Port       = 465;             
        $mail->CharSet    = 'UTF-8';
        // Người gửi
        $mail->setFrom(MAIL_USERNAME, MAIL_FROM_NAME);

        // Người nhận
        $mail->addAddress($to_email, $to_name);

        // Nội dung Email
        $mail->isHTML(true); 
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = strip_tags($body);

        $mail->send();
        return true; // Gửi thành công
    } catch (Exception $e) { // QUAN TRỌNG: 'Exception' này giờ đã được 'use' ở đầu file
        error_log("Lỗi gửi mail: {$mail->ErrorInfo}");
        return false; // Gửi thất bại
    }
}
/**
 * (MỚI) LẤY THỐNG KÊ TỔNG QUAN CHO ADMIN DASHBOARD
 */
function getAdminDashboardStats(PDO $pdo) {
    $stats = [];
    
    // 1. Tổng doanh thu (chỉ tính đơn đã thanh toán 'paid')
    $stmt1 = $pdo->query("SELECT SUM(tong_tien) as total_revenue FROM don_hang WHERE trang_thai = 'paid'");
    $stats['total_revenue'] = $stmt1->fetchColumn();

    // 2. Tổng đơn hàng
    $stmt2 = $pdo->query("SELECT COUNT(id) as total_orders FROM don_hang");
    $stats['total_orders'] = $stmt2->fetchColumn();

    // 3. Tổng khách hàng (vai_tro_id = 2)
    $stmt3 = $pdo->query("SELECT COUNT(id) as total_users FROM nguoi_dung WHERE vai_tro_id = 2");
    $stats['total_users'] = $stmt3->fetchColumn();
    
    // 4. Tổng sản phẩm
    $stmt4 = $pdo->query("SELECT COUNT(id) as total_products FROM san_pham");
    $stats['total_products'] = $stmt4->fetchColumn();

    return $stats;
}

/**
 * (MỚI) LẤY CÁC ĐƠN HÀNG MỚI NHẤT CHO ADMIN DASHBOARD
 */
function getRecentOrders(PDO $pdo, $limit = 5) {
    try {
        $sql = "
            SELECT d.id, d.ngay_dat, d.tong_tien, d.trang_thai, n.ho_ten
            FROM don_hang AS d
            JOIN nguoi_dung AS n ON d.nguoi_dung_id = n.id
            ORDER BY d.ngay_dat DESC
            LIMIT :limit
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log($e->getMessage());
        return [];
    }
}
/**
 * (MỚI) Lấy tất cả sản phẩm và tổng tiền trong giỏ hàng của người dùng
 * Dựa trên bảng: `gio_hang`, `san_pham`
 */
function getCartItemsAndTotal(PDO $pdo, $user_id) {
    $sql = "
        SELECT 
            sp.id AS san_pham_id,
            sp.ten,
            sp.hinh_anh,
            sp.gia,
            gh.so_luong
        FROM gio_hang AS gh
        JOIN san_pham AS sp ON gh.san_pham_id = sp.id
        WHERE gh.nguoi_dung_id = ?
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    $items = $stmt->fetchAll();
    
    $total_amount = 0;
    foreach ($items as $item) {
        // (Bạn có thể thêm logic kiểm tra giảm giá ở đây)
        $total_amount += $item['gia'] * $item['so_luong'];
    }
    
    return [
        'items' => $items,
        'total' => $total_amount
    ];
}
function getAvailableCoupons(PDO $pdo) {
    // Chỉ lấy mã còn hạn và chưa bắt đầu
    $sql = "SELECT * FROM ma_khuyen_mai 
            WHERE (ngay_bat_dau IS NULL OR ngay_bat_dau <= NOW())
              AND (ngay_ket_thuc IS NULL OR ngay_ket_thuc >= NOW())
            ORDER BY gia_tri DESC";
    
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll();
}
function addUserAddress(PDO $pdo, $user_id, $dia_chi) {
    try {
        $sql = "INSERT INTO dia_chi (nguoi_dung_id, dia_chi_cu_the) VALUES (?, ?)";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$user_id, $dia_chi]);
    } catch (PDOException $e) {
        error_log("Lỗi thêm địa chỉ: " . $e->getMessage());
        return false;
    }
}
/**
 * (MỚI) Xóa địa chỉ
 */
function deleteUserAddress(PDO $pdo, $user_id, $address_id) {
    try {
        $sql = "DELETE FROM dia_chi WHERE id = ? AND nguoi_dung_id = ?";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$address_id, $user_id]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * (MỚI) Cập nhật địa chỉ
 */
function updateUserAddress(PDO $pdo, $user_id, $address_id, $new_address) {
    try {
        $sql = "UPDATE dia_chi SET dia_chi_cu_the = ? WHERE id = ? AND nguoi_dung_id = ?";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$new_address, $address_id, $user_id]);
    } catch (PDOException $e) {
        return false;
    }
}


function getProductsWithDiscount($pdo, $category_id = null) {

    $sql = "
        SELECT 
            sp.*,
            sp.gia AS gia_goc,
            g.loai_giam_gia,
            g.gia_tri
        FROM san_pham sp
        LEFT JOIN san_pham_giam_gia spgg ON sp.id = spgg.product_id
        LEFT JOIN giam_gia g ON spgg.sale_id = g.id
        WHERE 1
    ";

    if ($category_id !== null) {
        $sql .= " AND sp.danh_muc_id = :cat ";
    }

    $stmt = $pdo->prepare($sql);

    if ($category_id !== null) {
        $stmt->bindParam(":cat", $category_id, PDO::PARAM_INT);
    }

    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $products = [];

    foreach ($rows as $sp) {
        $calc = apply_discount($sp);

        $sp['gia_goc'] = $calc['gia_goc'];
        $sp['gia_moi'] = $calc['gia_moi'];
        $sp['discount_percent'] = $calc['discount_percent'];

        $products[] = $sp;
    }

    return $products;
}

function getProductWithDiscount(PDO $pdo, $id) {

    $sql = "
        SELECT 
            sp.*,
            sp.gia AS gia_goc,

            gg.loai_giam_gia,
            gg.gia_tri AS gia_tri_giam

        FROM san_pham sp
        LEFT JOIN san_pham_giam_gia spgg ON sp.id = spgg.san_pham_id
        LEFT JOIN giam_gia gg ON spgg.giam_gia_id = gg.id
            AND gg.ngay_bat_dau <= NOW()
            AND gg.ngay_ket_thuc >= NOW()

        WHERE sp.id = :id
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) return null;

    return applyDiscount($product);
}

?>