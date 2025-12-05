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
        SELECT 
            sp.*,
            ts.man_hinh,
            ts.o_cung,
            ts.cpu,
            ts.gpu,
            ts.ram
        FROM san_pham sp
        
        -- Subquery 1: Lấy ID sản phẩm và ID nổi bật tối đa
        JOIN (
            SELECT san_pham_id, MAX(noi_bat_id) AS max_nb
            FROM san_pham_noi_bat
            GROUP BY san_pham_id
        ) nb_max ON sp.id = nb_max.san_pham_id
        
        -- Subquery 2: Lấy thông số kỹ thuật (đảm bảo mỗi sản phẩm chỉ có 1 bộ thông số)
        LEFT JOIN (
            SELECT 
                spts.san_pham_id,
                MAX(ts.man_hinh) AS man_hinh,
                MAX(ts.o_cung) AS o_cung,
                MAX(ts.cpu) AS cpu,
                MAX(ts.gpu) AS gpu,
                MAX(ts.ram) AS ram
            FROM san_pham_thong_so spts
            JOIN thong_so ts ON spts.thong_so_id = ts.id
            GROUP BY spts.san_pham_id
        ) ts ON sp.id = ts.san_pham_id
        
        ORDER BY nb_max.max_nb DESC
        LIMIT 8
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Load variants cho từng sản phẩm (Giữ nguyên logic của bạn)
    foreach ($products as &$sp) {
        $sp['variants'] = getProductVariants($pdo, $sp['id']);
    }

    return $products;
}

/* =============================
   LẤY SẢN PHẨM GIẢM GIÁ
   ============================= */
function getDiscountProducts($pdo) {
    $sql = "
        SELECT 
            sp.id,
            sp.ten,
            sp.hinh_anh,
            sp.gia,

            -- Sử dụng MAX() để chọn ra giá trị giảm giá cao nhất (giả sử bạn muốn ưu tiên giảm giá cao)
            -- Hoặc chỉ cần MIN/MAX trên các cột không phải ID để đảm bảo GROUP BY hoạt động
            MAX(gg.loai_giam_gia) AS loai_giam_gia,
            MAX(gg.gia_tri) AS gia_tri,

            MAX(gg.ngay_bat_dau) AS ngay_bat_dau,
            MAX(gg.ngay_ket_thuc) AS ngay_ket_thuc,

            -- Lấy CPU và RAM (sử dụng MAX() để chọn 1 giá trị duy nhất)
            MAX(ts.cpu) AS cpu,
            MAX(ts.ram) AS ram,

            -- Tính toán dựa trên giá trị giảm giá (MAX(gg.gia_tri))
            CASE 
                WHEN MAX(gg.loai_giam_gia) = 'percent' THEN MAX(gg.gia_tri)
                WHEN MAX(gg.loai_giam_gia) = 'amount' THEN ROUND(MAX(gg.gia_tri) / sp.gia * 100)
                ELSE 0
            END AS giam_phan_tram,

            CASE 
                WHEN MAX(gg.loai_giam_gia) = 'percent' THEN sp.gia - (sp.gia * MAX(gg.gia_tri) / 100)
                WHEN MAX(gg.loai_giam_gia) = 'amount' THEN sp.gia - MAX(gg.gia_tri)
                ELSE sp.gia
            END AS gia_da_giam

        FROM san_pham sp
        -- Lấy thông tin giảm giá
        JOIN san_pham_giam_gia spgg ON spgg.san_pham_id = sp.id
        JOIN giam_gia gg ON gg.id = spgg.giam_gia_id
        
        -- JOIN Thông số
        LEFT JOIN san_pham_thong_so spts ON spts.san_pham_id = sp.id
        LEFT JOIN thong_so ts ON ts.id = spts.thong_so_id

        WHERE 
            (gg.ngay_bat_dau IS NULL OR gg.ngay_bat_dau <= NOW())
            AND (gg.ngay_ket_thuc IS NULL OR gg.ngay_ket_thuc >= NOW())
            
        -- Chỉ GROUP BY các cột không phải hàm tổng hợp (Aggregate Function)
        GROUP BY sp.id, sp.ten, sp.hinh_anh, sp.gia;
    ";

    // ... Phần còn lại của hàm PHP giữ nguyên ...
    // Phần xử lý biến thể:
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

 foreach ($products as &$p) {
        // Lấy biến thể
        $stmtVar = $pdo->prepare("
            SELECT id, mau_sac, dung_luong_ssd, gia, gia, so_luong_ton, hinh_anh
            FROM bien_the_san_pham
            WHERE san_pham_id = ?
        ");
        $stmtVar->execute([$p['id']]);
        $p['variants'] = $stmtVar->fetchAll(PDO::FETCH_ASSOC);
    }
    return $products;
}
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
        // (ĐÃ SỬA) Thêm 'avatar' vào danh sách cột cần lấy
        $stmt = $pdo->prepare("SELECT ho_ten, email, so_dien_thoai, ngay_sinh, gioi_tinh, avatar 
                              FROM nguoi_dung WHERE id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log($e->getMessage());
        return null;
    }
}

/**
 * (MỚI) Lấy chi tiết sản phẩm của 1 đơn hàng
 */
function getOrderItems(PDO $pdo, $order_id) {
    try {
        $sql = "
            SELECT 
                ct.*, 
                sp.ten AS ten_san_pham, 
                sp.hinh_anh,
                -- Lấy thông tin biến thể
                bt.mau_sac,
                bt.dung_luong_ssd,
                bt.hinh_anh as hinh_bien_the
            FROM chi_tiet_don_hang ct
            JOIN san_pham sp ON ct.san_pham_id = sp.id
            -- Join trái để nếu không có biến thể thì vẫn lấy được sản phẩm
            LEFT JOIN bien_the_san_pham bt ON ct.bien_the_id = bt.id
            WHERE ct.don_hang_id = ?
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$order_id]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}
/**
 * Cập nhật thông tin cá nhân của người dùng.
 */
function updateUserProfile(PDO $pdo, $user_id, $ho_ten, $so_dien_thoai, $ngay_sinh, $gioi_tinh, $avatar = null) {
    try {
        // Chuẩn bị dữ liệu số điện thoại và ngày sinh (tránh lỗi rỗng)
        $so_dien_thoai = empty($so_dien_thoai) ? null : $so_dien_thoai;
        $ngay_sinh = empty($ngay_sinh) ? null : $ngay_sinh;

        // Kiểm tra xem có file avatar mới được gửi lên không
        if ($avatar) {
            // Có avatar -> Cập nhật cả cột 'avatar'
            $sql = "UPDATE nguoi_dung 
                    SET ho_ten = ?, so_dien_thoai = ?, ngay_sinh = ?, gioi_tinh = ?, avatar = ?
                    WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$ho_ten, $so_dien_thoai, $ngay_sinh, $gioi_tinh, $avatar, $user_id]);
        } else {
            // Không có avatar -> Chỉ cập nhật thông tin văn bản (Giữ nguyên ảnh cũ)
            $sql = "UPDATE nguoi_dung 
                    SET ho_ten = ?, so_dien_thoai = ?, ngay_sinh = ?, gioi_tinh = ?
                    WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$ho_ten, $so_dien_thoai, $ngay_sinh, $gioi_tinh, $user_id]);
        }
        
        return true; 
    } catch (PDOException $e) {
        error_log("Lỗi update profile: " . $e->getMessage());
        return false; 
    }
}

/**
 * (ĐÃ SỬA) Lấy lịch sử đơn hàng
 * Thêm cột d.trang_thai_thanh_toan để xử lý logic hủy đơn
 */
function getUserOrders(PDO $pdo, $user_id, $keyword = '') {
    try {
        // [FIX] Thêm d.trang_thai_thanh_toan vào danh sách cột
        $sql = "SELECT DISTINCT d.id, d.ngay_dat, d.tong_tien, d.trang_thai_don_hang, d.trang_thai_thanh_toan
                FROM don_hang d
                LEFT JOIN chi_tiet_don_hang ct ON d.id = ct.don_hang_id
                LEFT JOIN san_pham sp ON ct.san_pham_id = sp.id
                WHERE d.nguoi_dung_id = ?";
        
        $params = [$user_id];

        if (!empty($keyword)) {
            $sql .= " AND (d.id LIKE ? OR sp.ten LIKE ?)";
            $params[] = "%$keyword%";
            $params[] = "%$keyword%";
        }

        $sql .= " ORDER BY d.ngay_dat DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log($e->getMessage());
        return [];
    }
}

/**
 * (MỚI) Lấy thông tin chi tiết của 1 đơn hàng (Header)
 */
function getOrderById(PDO $pdo, $order_id, $user_id) {
    try {
        // Join thêm bảng thanh_toan để kiểm tra
        $sql = "
            SELECT d.*, 
                   (SELECT COUNT(*) FROM thanh_toan t WHERE t.don_hang_id = d.id AND t.trang_thai = 'success') as is_paid_online
            FROM don_hang d
            WHERE d.id = ? AND d.nguoi_dung_id = ?
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$order_id, $user_id]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        return null;
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
 * CẬP NHẬT: Logic tính doanh thu theo trạng thái tiếng Việt
 */
function getAdminDashboardStats(PDO $pdo) {
    $stats = [];
    
    // 1. Tổng doanh thu (chỉ tính đơn đã thanh toán 'Đã thanh toán')
    // Sửa: trang_thai = 'paid' -> trang_thai_thanh_toan = 'Đã thanh toán'
    $stmt1 = $pdo->query("SELECT SUM(tong_tien) as total_revenue FROM don_hang WHERE trang_thai_thanh_toan = 'Đã thanh toán'");
    $stats['total_revenue'] = $stmt1->fetchColumn() ?: 0; // Thêm ?: 0 để tránh null nếu chưa có đơn

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
 * CẬP NHẬT: Đổi d.trang_thai -> d.trang_thai_don_hang
 */
function getRecentOrders(PDO $pdo, $limit = 5) {
    try {
        // Sửa d.trang_thai -> d.trang_thai_don_hang
        $sql = "
            SELECT d.id, d.ngay_dat, d.tong_tien, d.trang_thai_don_hang, n.ho_ten
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
            gh.id,
            gh.so_luong,

            sp.ten AS ten_san_pham,
            sp.hinh_anh AS hinh_cha,

            bt.id AS bien_the_id,
            bt.mau_sac,
            bt.dung_luong_ssd,
            bt.hinh_anh AS hinh_bien_the,
            bt.gia AS gia_bien_the,

            gg.loai_giam_gia,
            gg.gia_tri

        FROM gio_hang gh

        JOIN san_pham sp 
            ON gh.san_pham_id = sp.id

        LEFT JOIN bien_the_san_pham bt 
            ON gh.bien_the_id = bt.id

        LEFT JOIN san_pham_giam_gia spg 
            ON spg.san_pham_id = sp.id

        LEFT JOIN giam_gia gg 
            ON gg.id = spg.giam_gia_id
            AND (gg.ngay_bat_dau IS NULL OR gg.ngay_bat_dau <= NOW())
            AND (gg.ngay_ket_thuc IS NULL OR gg.ngay_ket_thuc >= NOW())

        WHERE gh.nguoi_dung_id = ?
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total = 0;

    foreach ($items as &$item) {

        // 1. chọn đúng ảnh
        $item["hinh_anh"] = $item["hinh_bien_the"] ?: $item["hinh_cha"];

        // 2. giá gốc = giá biến thể
        $gia = (float) $item["gia_bien_the"];

        // 3. áp dụng giảm giá
        if ($item["loai_giam_gia"] === "percent") {
            $gia = $gia - ($gia * ($item["gia_tri"] / 100));
        } elseif ($item["loai_giam_gia"] === "amount") {
            $gia = $gia - $item["gia_tri"];
        }

        if ($gia < 0) $gia = 0;

        $item["gia"] = $gia;

        // 4. tính tổng tiền giỏ hàng
        $total += $gia * $item["so_luong"];
    }

    return [
        "items" => $items,
        "total" => $total
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
    // SỬA LỖI: Thay product_id -> san_pham_id, sale_id -> giam_gia_id
    $sql = "
        SELECT 
            sp.*,
            sp.gia AS gia_goc,
            g.loai_giam_gia,
            g.gia_tri
        FROM san_pham sp
        JOIN san_pham_giam_gia spgg ON sp.id = spgg.san_pham_id 
        JOIN giam_gia g ON spgg.giam_gia_id = g.id
        WHERE (g.ngay_bat_dau IS NULL OR g.ngay_bat_dau <= NOW())
          AND (g.ngay_ket_thuc IS NULL OR g.ngay_ket_thuc >= NOW())
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
        // Gọi hàm apply_discount (đã thêm ở bước trước)
        $calc = apply_discount($sp); 
        
        $sp['gia_goc'] = $calc['gia_goc'];
        $sp['gia_moi'] = $calc['gia_moi'];
        $sp['phan_tram_giam'] = $calc['phan_tram_giam']; // Đồng bộ key

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
 /* Lấy chi tiết mã khuyến mãi theo tên (code) và kiểm tra hạn sử dụng.
 */
function getCouponByCode(PDO $pdo, $code) {
    try {
        $sql = "SELECT ten, loai_khuyen_mai, gia_tri, dieu_kien FROM ma_khuyen_mai 
                WHERE ten = ? 
                AND (ngay_bat_dau IS NULL OR ngay_bat_dau <= NOW())
                AND (ngay_ket_thuc IS NULL OR ngay_ket_thuc >= NOW())
                LIMIT 1";

        $stmt = $pdo->prepare($sql);
        // Chuyển mã sang chữ hoa để nhất quán (VD: WELCOME100K)
        $stmt->execute([strtoupper(trim($code))]); 
        return $stmt->fetch(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        // Ghi log lỗi CSDL để phục vụ debug
        error_log("Lỗi truy vấn coupon: " . $e->getMessage());
        return null;
    }
}

/**
 * Hàm tính toán giá giảm (Thêm vào cuối file functions.php)
 */
function apply_discount($product) {
    $price_original = $product['gia'];
    $price_final = $price_original;
    $discount_percent = 0;

    if (!empty($product['loai_giam_gia']) && isset($product['gia_tri'])) {
        if ($product['loai_giam_gia'] === 'percent') {
            $discount_amount = $price_original * ($product['gia_tri'] / 100);
            $price_final = $price_original - $discount_amount;
            $discount_percent = $product['gia_tri'];
        } elseif ($product['loai_giam_gia'] === 'amount') {
            $price_final = $price_original - $product['gia_tri'];
            if ($price_original > 0) {
                $discount_percent = round(($product['gia_tri'] / $price_original) * 100);
            }
        }
    }

    if ($price_final < 0) $price_final = 0;

    return [
        'gia_goc' => $price_original,
        'gia_moi' => $price_final,
        'phan_tram_giam' => $discount_percent,
        'is_discounted' => ($price_final < $price_original)
    ];
}

// Alias để tránh lỗi gọi tên hàm cũ
function applyDiscount($product) {
    return apply_discount($product);
}

?>