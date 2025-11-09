<?php
// FILE: src/functions.php

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
        // Câu SQL này dùng tên bảng và cột từ file hishop_db.sql của bạn
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
            
            -- (ĐÃ SỬA LỖI)
            -- Chỉ cần JOIN với bảng san_pham_noi_bat,
            -- KHÔNG cần lọc theo noi_bat_id = 1 nữa.
            JOIN san_pham_noi_bat AS spnb ON sp.id = spnb.san_pham_id
            
            -- Lấy thông tin giảm giá (nếu có và còn hạn)
            LEFT JOIN san_pham_giam_gia AS spgg ON sp.id = spgg.san_pham_id
            LEFT JOIN giam_gia AS gg ON spgg.giam_gia_id = gg.id 
                 AND gg.ngay_bat_dau <= NOW() 
                 AND gg.ngay_ket_thuc >= NOW()
            
            WHERE 
                sp.trang_thai = 1
            
            GROUP BY sp.id 
            LIMIT 8; -- Vẫn giữ giới hạn 8 sản phẩm cho carousel
        ";
        
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll();

    } catch (PDOException $e) {
        error_log($e->getMessage());
        return [];
    }
}

/**
 * Lấy thông tin chi tiết của một người dùng.
 * Khớp với bảng: `nguoi_dung`
 */
function getUserProfile(PDO $pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("SELECT ho_ten, email, so_dien_thoai, ngay_sinh, gioi_tinh 
                              FROM nguoi_dung WHERE id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log($e->getMessage());
        return null; // Trả về null nếu có lỗi
    }
}

/**
 * Cập nhật thông tin cá nhân của người dùng.
 * Khớp với bảng: `nguoi_dung`
 */
function updateUserProfile(PDO $pdo, $user_id, $ho_ten, $so_dien_thoai, $ngay_sinh, $gioi_tinh) {
    try {
        $sql = "UPDATE nguoi_dung 
                SET ho_ten = ?, so_dien_thoai = ?, ngay_sinh = ?, gioi_tinh = ?
                WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        
        // Xử lý các giá trị rỗng
        $so_dien_thoai = empty($so_dien_thoai) ? null : $so_dien_thoai;
        $ngay_sinh = empty($ngay_sinh) ? null : $ngay_sinh;

        $stmt->execute([$ho_ten, $so_dien_thoai, $ngay_sinh, $gioi_tinh, $user_id]);
        return true; // Trả về true nếu thành công
    } catch (PDOException $e) {
        error_log($e->getMessage());
        return false; // Trả về false nếu thất bại
    }
}

/**
 * Lấy lịch sử đơn hàng của người dùng.
 * Khớp với bảng: `don_hang`
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
 * Khớp với bảng: `dia_chi`
 */
function getUserAddresses(PDO $pdo, $user_id) {
    try {
        // (CSDL của bạn chỉ có cột `dia_chi_cu_the`, chúng ta sẽ dùng nó)
        $stmt = $pdo->prepare("SELECT id, dia_chi_cu_the 
                              FROM dia_chi WHERE nguoi_dung_id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log($e->getMessage());
        return [];
    }
}
// IMPORT THƯ VIỆN PHPMAILER
// (Phải có dòng "use" này ở đầu file hoặc ngay trên hàm)
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Hàm gửi email chung cho dự án
 */
function sendEmail($to_email, $to_name, $subject, $body) {
    // Khởi tạo PHPMailer
    $mail = new PHPMailer(true);

    try {
        // Cấu hình Server (SMTP)
        // $mail->SMTPDebug = SMTP::DEBUG_SERVER; // Bật debug (nếu cần xem lỗi)
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;       // Lấy từ config.php
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;   // Lấy từ config.php
        $mail->Password   = MAIL_PASSWORD;   // Lấy từ config.php
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Dùng SSL
        $mail->Port       = 465;             // Port cho Gmail SSL
        $mail->CharSet    = 'UTF-8';

        // Người gửi (Lấy từ config.php)
        $mail->setFrom(MAIL_USERNAME, MAIL_FROM_NAME);

        // Người nhận
        $mail->addAddress($to_email, $to_name);

        // Nội dung Email
        $mail->isHTML(true); // Gửi mail dạng HTML
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = strip_tags($body); // Nội dung (dạng text)

        $mail->send();
        return true; // Gửi thành công
    } catch (Exception $e) {
        // Ghi log lỗi
        error_log("Lỗi gửi mail: {$mail->ErrorInfo}");
        return false; // Gửi thất bại
    }
}

?>