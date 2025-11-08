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

// (Thêm các hàm back-end khác của bạn ở đây...)
?>