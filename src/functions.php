<?php
// FILE: src/functions.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Lấy tất cả danh mục đang hoạt động từ CSDL.
 * Khớp với bảng: `danh_muc`
 */
function getActiveCategories(PDO $pdo) {
    try {
        // Sử dụng tên bảng và cột từ CSDL của bạn
        $stmt = $pdo->query("SELECT id, ten FROM danh_muc WHERE trang_thai = 1 ORDER BY ten ASC");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log($e->getMessage());
        return []; // Trả về mảng rỗng nếu có lỗi
    }
}

/**
 * Lấy các sản phẩm nổi bật cho carousel trang chủ.
 * Khớp với bảng: `san_pham`, `san_pham_noi_bat`, `giam_gia`
 */
function getFeaturedProducts(PDO $pdo) {
    try {
        // (SỬA LỖI) Đây là câu SQL ĐÚNG cho hàm này
        $sql = "
            SELECT 
                sp.id, 
                sp.ten, 
                sp.gia AS gia_goc, 
                sp.hinh_anh,
                gg.loai_giam_gia,
                gg.gia_tri AS gia_tri_giam,
                
                CASE 
                    WHEN gg.loai_giam_gia = 'percent' THEN sp.gia * (1 - gg.gia_tri / 100)
                    WHEN gg.loai_giam_gia = 'amount' THEN sp.gia - gg.gia_tri
                    ELSE NULL 
                END AS gia_moi

            FROM san_pham AS sp
            
            JOIN san_pham_noi_bat AS spnb ON sp.id = spnb.san_pham_id
            
            LEFT JOIN san_pham_giam_gia AS spgg ON sp.id = spgg.san_pham_id
            LEFT JOIN giam_gia AS gg ON spgg.giam_gia_id = gg.id 
                 AND gg.ngay_bat_dau <= NOW() 
                 AND gg.ngay_ket_thuc >= NOW()
            
            WHERE 
                sp.trang_thai = 1
            
            GROUP BY sp.id 
            LIMIT 8; 
        ";
        
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll();

    } catch (PDOException $e) {
        error_log($e->getMessage());
        return [];
    }
}

/**
 * LẤY THÔNG TIN CHI TIẾT CỦA 1 SẢN PHẨM (cho trang PDP)
 */
function getProductDetails(PDO $pdo, $product_id) {
    $response = [
        'details' => null, // Thông tin chính
        'specs' => null   // Thông số kỹ thuật
    ];

    try {
        // --- Query 1: Lấy thông tin chính ---
        $sql_details = "
            SELECT 
                sp.id, 
                sp.ten, 
                sp.gia AS gia_goc, 
                sp.hinh_anh,
                sp.mo_ta, /* Lấy mô tả */
                sp.danh_muc_id,
                dm.ten AS ten_danh_muc,
                ms.ten AS ten_mau_sac,
                gg.loai_giam_gia,
                gg.gia_tri AS gia_tri_giam,
                COALESCE(kho.total_stock, 0) AS so_luong_ton,
                
                CASE 
                    WHEN gg.loai_giam_gia = 'percent' THEN sp.gia * (1 - gg.gia_tri / 100)
                    WHEN gg.loai_giam_gia = 'amount' THEN sp.gia - gg.gia_tri
                    ELSE NULL 
                END AS gia_moi

            FROM san_pham AS sp
            
            LEFT JOIN danh_muc AS dm ON sp.danh_muc_id = dm.id
            LEFT JOIN mau_sac AS ms ON sp.mau_sac_id = ms.id
            
            LEFT JOIN (
                SELECT san_pham_id, SUM(so_luong_ton) AS total_stock 
                FROM chi_tiet_kho_hang 
                GROUP BY san_pham_id
            ) AS kho ON sp.id = kho.san_pham_id
            
            LEFT JOIN san_pham_giam_gia AS spgg ON sp.id = spgg.san_pham_id
            LEFT JOIN giam_gia AS gg ON spgg.giam_gia_id = gg.id 
                 AND gg.ngay_bat_dau <= NOW() 
                 AND gg.ngay_ket_thuc >= NOW()
            
            WHERE 
                sp.trang_thai = 1 AND sp.id = ?
            
            LIMIT 1;
        ";
        
        $stmt_details = $pdo->prepare($sql_details);
        $stmt_details->execute([$product_id]);
        $response['details'] = $stmt_details->fetch();

        if (!$response['details']) {
            return null;
        }

        // --- Query 2: Lấy thông số kỹ thuật ---
        $sql_specs = "
            SELECT ts.*
            FROM thong_so AS ts
            JOIN san_pham_thong_so AS spts ON ts.id = spts.thong_so_id
            WHERE spts.san_pham_id = ?
            LIMIT 1;
        ";
        
        $stmt_specs = $pdo->prepare($sql_specs);
        $stmt_specs->execute([$product_id]);
        $response['specs'] = $stmt_specs->fetch();
        
        return $response;

    } catch (PDOException $e) {
        error_log($e->getMessage());
        return null; 
    }
}

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
?>