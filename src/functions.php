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
/* =========================================================================
   LẤY SẢN PHẨM NỔI BẬT (TỰ ĐỘNG DỰA TRÊN SỐ LƯỢNG BÁN CHẠY NHẤT)
   ========================================================================= */
function getFeaturedProducts($pdo) {
    // Logic: Join với bảng chi tiết đơn hàng, đếm tổng số lượng bán và sắp xếp giảm dần
    $sql = "
        SELECT 
            sp.*,
            COALESCE(SUM(ct.so_luong), 0) AS tong_da_ban, -- Tổng số lượng đã bán
            ts.man_hinh,
            ts.o_cung,
            ts.cpu,
            ts.gpu,
            ts.ram
        FROM san_pham sp
        
        -- 1. Join để tính số lượng bán (Chỉ tính đơn KHÔNG bị hủy)
        LEFT JOIN chi_tiet_don_hang ct ON sp.id = ct.san_pham_id
        LEFT JOIN don_hang dh ON ct.don_hang_id = dh.id AND dh.trang_thai_don_hang != 'Đã hủy'
        
        -- 2. Join lấy thông số kỹ thuật (Giữ nguyên để hiển thị đẹp)
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
        
        WHERE sp.trang_thai = 1
        GROUP BY sp.id
        
        -- 3. SẮP XẾP QUAN TRỌNG NHẤT: Bán nhiều nhất lên đầu
        ORDER BY tong_da_ban DESC, sp.id DESC
        LIMIT 8
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Load biến thể & Tính giá giảm (Giữ nguyên logic cũ)
    foreach ($products as &$sp) {
        $sp['variants'] = getProductVariants($pdo, $sp['id']);
        
        $discountData = apply_discount($sp);
        $sp['gia_moi'] = $discountData['gia_moi'];
        $sp['phan_tram_giam'] = $discountData['phan_tram_giam'];
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
            
            gg.loai_giam_gia,
            gg.gia_tri,

            MAX(ts.cpu) AS cpu,
            MAX(ts.ram) AS ram,

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

        LEFT JOIN san_pham_thong_so spts ON spts.san_pham_id = sp.id
        LEFT JOIN thong_so ts ON ts.id = spts.thong_so_id

        WHERE 
            (gg.ngay_bat_dau IS NULL OR gg.ngay_bat_dau <= NOW())
            AND (gg.ngay_ket_thuc IS NULL OR gg.ngay_ket_thuc >= NOW())

        GROUP BY sp.id
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Lấy biến thể + áp dụng giảm giá
    foreach ($products as &$p) {

        // Lấy biến thể đúng chuẩn
        $stmtVar = $pdo->prepare("
            SELECT id, mau_sac, dung_luong_ssd, gia, so_luong_ton, hinh_anh
            FROM bien_the_san_pham
            WHERE san_pham_id = ?
        ");
        $stmtVar->execute([$p['id']]);
        $variants = $stmtVar->fetchAll(PDO::FETCH_ASSOC);

        // Áp dụng giảm giá vào từng biến thể
        foreach ($variants as &$v) {

            if ($p['loai_giam_gia'] === 'percent') {
                $v['gia_giam'] = $v['gia'] - ($v['gia'] * $p['gia_tri'] / 100);
            } elseif ($p['loai_giam_gia'] === 'amount') {
                $v['gia_giam'] = $v['gia'] - $p['gia_tri'];
            } else {
                $v['gia_giam'] = $v['gia'];
            }
        }

        $p['variants'] = $variants;
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
 * Hàm gửi Email chung cho toàn hệ thống
 */
function sendMail($to, $subject, $content) {
    // [SỬA LỖI] Vì đầu file đã có "use PHPMailer\PHPMailer\PHPMailer;" 
    // nên ở đây chỉ cần gọi ngắn gọn là PHPMailer
    $mail = new PHPMailer(true);

    try {
        // 1. Cấu hình Server (SMTP)
        $mail->isSMTP();
        $mail->Host       = defined('MAIL_HOST') ? MAIL_HOST : 'smtp.gmail.com'; 
        $mail->SMTPAuth   = true;
        
        // Lấy thông tin từ config
        $mail->Username   = defined('MAIL_USERNAME') ? MAIL_USERNAME : 'nhat2koko5@gmail.com'; 
        $mail->Password   = defined('MAIL_PASSWORD') ? MAIL_PASSWORD : ''; // Mật khẩu ứng dụng
        
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // [SỬA] Dùng hằng số ngắn gọn
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        // 2. Người gửi & Người nhận
        $mail->setFrom($mail->Username, 'HIShop Notification');
        $mail->addAddress($to);

        // 3. Nội dung Email
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $content;
        $mail->AltBody = strip_tags($content);

        $mail->send();
        return true;

    } catch (Exception $e) {
        // Ghi log lỗi vào file error_log của server để debug thay vì hiện ra màn hình
        error_log("Gửi mail thất bại: {$mail->ErrorInfo}");
        return false;
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
 * Xử lý tạo đơn hàng & Trừ kho chi tiết (Admin quản lý)
 * Hàm này dùng cho cả COD và VNPAY
 */
/**
 * [ĐÃ CẬP NHẬT] Hàm xử lý đơn hàng có hỗ trợ GIẢM GIÁ (Coupon)
 */
function processCheckout(PDO $pdo, $user_id, $cart_items, $customer_info, $payment_method, $discount_amount = 0, $coupon_code = null) {
    try {
        $pdo->beginTransaction();

        $total_amount = 0;
        
        // 1. Tính tổng tiền hàng (Subtotal) & Check kho
        foreach ($cart_items as $item) {
            if (!empty($item['bien_the_id'])) {
                $stmt = $pdo->prepare("SELECT gia, so_luong_ton FROM bien_the_san_pham WHERE id = ? FOR UPDATE");
                $stmt->execute([$item['bien_the_id']]);
            } else {
                $stmt = $pdo->prepare("SELECT gia, so_luong FROM san_pham WHERE id = ? FOR UPDATE");
                $stmt->execute([$item['san_pham_id']]);
            }
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$product || $product['so_luong_ton'] < $item['so_luong']) { 
                 $pdo->rollBack(); 
                 return ['success' => false, 'message' => "Sản phẩm {$item['ten_san_pham']} không đủ số lượng."];
            }
            
            $total_amount += $item['gia'] * $item['so_luong'];
        }

        // 2. [QUAN TRỌNG] Áp dụng giảm giá và tìm ID mã khuyến mãi
        $final_total = $total_amount - $discount_amount;
        if ($final_total < 0) $final_total = 0;

        $coupon_id = null;
        if ($coupon_code) {
            // Tìm ID của mã giảm giá trong database để lưu vào đơn hàng
            $stmtC = $pdo->prepare("SELECT id FROM ma_khuyen_mai WHERE ten = ? LIMIT 1");
            $stmtC->execute([$coupon_code]);
            $coupon_id = $stmtC->fetchColumn();
        }

        // 3. Lưu đơn hàng (Với giá đã giảm và ID khuyến mãi)
        $sql_order = "INSERT INTO don_hang (ngay_dat, tong_tien, phuong_thuc_thanh_toan, trang_thai_don_hang, trang_thai_thanh_toan, nguoi_dung_id, ho_ten_nguoi_nhan, sdt_nguoi_nhan, dia_chi_giao_hang, ghi_chu, ma_khuyen_mai_id) 
                      VALUES (NOW(), ?, ?, 'Chờ xử lý', 'Chưa thanh toán', ?, ?, ?, ?, ?, ?)";
        
        $pdo->prepare($sql_order)->execute([
            $final_total, // Dùng giá sau giảm
            strtoupper($payment_method),
            $user_id, 
            $customer_info['ho_ten'], 
            $customer_info['sdt'], 
            $customer_info['dia_chi'], 
            $customer_info['ghi_chu'],
            $coupon_id // Lưu ID mã giảm giá
        ]);
        $order_id = $pdo->lastInsertId();
        if ($coupon_id) {
        $stmtUpdateCoupon = $pdo->prepare("UPDATE ma_khuyen_mai SET da_dung = da_dung + 1 WHERE id = ?");
        $stmtUpdateCoupon->execute([$coupon_id]);
        }

        // 4. Lưu chi tiết & Trừ kho (Giữ nguyên logic cũ)
        foreach ($cart_items as $item) {
            // Lưu chi tiết
            $sql_detail = "INSERT INTO chi_tiet_don_hang (don_hang_id, san_pham_id, bien_the_id, so_luong, don_gia) VALUES (?, ?, ?, ?, ?)";
            $pdo->prepare($sql_detail)->execute([
                $order_id, $item['san_pham_id'], $item['bien_the_id'], $item['so_luong'], $item['gia']
            ]);

            // Trừ kho tổng
            if (!empty($item['bien_the_id'])) {
                $pdo->prepare("UPDATE bien_the_san_pham SET so_luong_ton = so_luong_ton - ? WHERE id = ?")->execute([$item['so_luong'], $item['bien_the_id']]);
            } else {
                $pdo->prepare("UPDATE san_pham SET so_luong = so_luong - ? WHERE id = ?")->execute([$item['so_luong'], $item['san_pham_id']]);
            }

            // Trừ kho chi tiết (Admin)
            $qty_needed = $item['so_luong'];
            $sql_get_wh = "SELECT id, so_luong_ton FROM chi_tiet_kho_hang 
                           WHERE san_pham_id = ? 
                           AND (bien_the_id = ? OR (bien_the_id IS NULL AND ? IS NULL)) 
                           AND so_luong_ton > 0 
                           ORDER BY so_luong_ton DESC";
            $bt_param = !empty($item['bien_the_id']) ? $item['bien_the_id'] : null;
            $stmt_wh = $pdo->prepare($sql_get_wh);
            $stmt_wh->execute([$item['san_pham_id'], $bt_param, $bt_param]);
            $warehouses = $stmt_wh->fetchAll(PDO::FETCH_ASSOC);

            foreach ($warehouses as $wh) {
                if ($qty_needed <= 0) break; 
                $deduct = min($qty_needed, $wh['so_luong_ton']);
                $pdo->prepare("UPDATE chi_tiet_kho_hang SET so_luong_ton = so_luong_ton - ? WHERE id = ?")->execute([$deduct, $wh['id']]);
                $qty_needed -= $deduct;
            }
        }

        // 5. Xóa giỏ hàng
        $pdo->prepare("DELETE FROM gio_hang WHERE nguoi_dung_id = ?")->execute([$user_id]);

        $pdo->commit();
        
        return [
            'success' => true, 
            'message' => 'Thành công', 
            'order_id' => $order_id,
            'total' => $final_total // Trả về giá cuối cùng để gửi sang VNPAY
        ];

    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}


/**
 * (MỚI) Lấy tất cả sản phẩm và tổng tiền trong giỏ hàng của người dùng
 * Dựa trên bảng: `gio_hang`, `san_pham`
 */
function getCartItemsAndTotal(PDO $pdo, $user_id, $selected_ids = null) {

    // 1. Chuẩn bị câu SQL cơ bản
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
            bt.so_luong_ton,
            gg.loai_giam_gia,
            gg.gia_tri
        FROM gio_hang gh
        JOIN san_pham sp ON gh.san_pham_id = sp.id
        LEFT JOIN bien_the_san_pham bt ON gh.bien_the_id = bt.id
        LEFT JOIN san_pham_giam_gia spg ON spg.san_pham_id = sp.id
        LEFT JOIN giam_gia gg ON gg.id = spg.giam_gia_id
            AND (gg.ngay_bat_dau IS NULL OR gg.ngay_bat_dau <= NOW())
            AND (gg.ngay_ket_thuc IS NULL OR gg.ngay_ket_thuc >= NOW())
        WHERE gh.nguoi_dung_id = ?
    ";

    $params = [$user_id];

    // 2. [QUAN TRỌNG] Nếu có danh sách ID được chọn -> Thêm điều kiện lọc
    if (!empty($selected_ids) && is_array($selected_ids)) {
        // Tạo chuỗi dấu chấm hỏi (?,?,?) tương ứng số lượng ID
        $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
        $sql .= " AND gh.id IN ($placeholders)";
        
        // Gộp mảng params cũ với mảng ID mới
        $params = array_merge($params, $selected_ids);
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Tính toán tổng tiền (Logic cũ giữ nguyên)
    $total = 0;
    foreach ($items as &$item) {
        $item["hinh_anh"] = $item["hinh_bien_the"] ?: $item["hinh_cha"];
        $gia = (float) $item["gia_bien_the"];

        if ($item["loai_giam_gia"] === "percent") {
            $gia -= ($gia * ($item["gia_tri"] / 100));
        } elseif ($item["loai_giam_gia"] === "amount") {
            $gia -= $item["gia_tri"];
        }
        if ($gia < 0) $gia = 0;

        $item["gia"] = $gia;
        $item["so_luong_ton"] = isset($item["so_luong_ton"]) ? (int)$item["so_luong_ton"] : 999999;
        
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