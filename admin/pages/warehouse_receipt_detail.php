<?php
// FILE: admin/pages/warehouse_receipt_detail.php

// 1. Kiểm tra ID phiếu
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "<script>alert('Mã phiếu không hợp lệ!'); window.location.href='index.php?page=warehouse_history';</script>";
    exit;
}
$phieu_id = intval($_GET['id']);

// 2. Lấy thông tin Header (Phiếu kho + Kho hàng + Người tạo)
$sqlHeader = "
    SELECT pk.*, k.ten_kho, k.dia_chi as dia_chi_kho, k.so_dien_thoai, nd.ho_ten as nguoi_tao
    FROM phieu_kho pk
    JOIN kho_hang k ON pk.kho_hang_id = k.id
    LEFT JOIN nguoi_dung nd ON pk.nguoi_dung_id = nd.id
    WHERE pk.id = ?
";
$stmt = $pdo->prepare($sqlHeader);
$stmt->execute([$phieu_id]);
$phieu = $stmt->fetch();

if (!$phieu) {
    echo "<script>alert('Không tìm thấy phiếu kho!'); window.location.href='index.php?page=warehouse_history';</script>";
    exit;
}

// 3. TÍNH TOÁN TỔNG QUÁT (Query riêng để đảm bảo đúng tổng tiền khi phân trang)
$sqlSummary = "
    SELECT 
        COUNT(*) as total_items,
        SUM(so_luong) as total_qty,
        SUM(so_luong * don_gia) as total_money
    FROM chi_tiet_phieu_kho
    WHERE phieu_kho_id = ?
";
$stmtSum = $pdo->prepare($sqlSummary);
$stmtSum->execute([$phieu_id]);
$summary = $stmtSum->fetch();

// 4. XỬ LÝ PHÂN TRANG CHO DANH SÁCH SẢN PHẨM
$limit = 10; // Số dòng mỗi trang
$page = isset($_GET['p']) ? max(1, intval($_GET['p'])) : 1;
$offset = ($page - 1) * $limit;
$total_pages = ceil($summary['total_items'] / $limit);

// 5. Lấy danh sách chi tiết (Có LIMIT OFFSET)
$sqlDetail = "
    SELECT 
        ct.*, 
        sp.ten as ten_san_pham, 
        sp.hinh_anh as hinh_cha,
        bt.mau_sac, 
        bt.dung_luong_ssd, 
        bt.hinh_anh as hinh_con
    FROM chi_tiet_phieu_kho ct
    JOIN san_pham sp ON ct.san_pham_id = sp.id
    LEFT JOIN bien_the_san_pham bt ON ct.bien_the_id = bt.id
    WHERE ct.phieu_kho_id = ?
    ORDER BY ct.id ASC
    LIMIT $limit OFFSET $offset
";
$stmtDetail = $pdo->prepare($sqlDetail);
$stmtDetail->execute([$phieu_id]);
$items = $stmtDetail->fetchAll();
?>

<link rel="stylesheet" href="../assets/css/admin/warehouse_receipt_detail.css">

<div class="admin-page-container">
    
    <div class="action-bar">
        <a href="index.php?page=warehouse_history" class="btn-action btn-back">
            <i class="fa-solid fa-arrow-left"></i> Quay lại
        </a>
        <button onclick="window.print()" class="btn-action btn-print">
            <i class="fa-solid fa-print"></i> In Phiếu
        </button>
    </div>

    <div class="invoice-wrapper">
        
        <div class="invoice-header">
            <div class="brand-section">
                <h1>HISHOP</h1>
                <div style="font-size: 13px; color: #666;">Hệ thống quản lý kho vận & bán hàng</div>
                <div style="font-size: 13px; color: #666;">Website: hishop.vn | Hotline: 1900 xxxx</div>
            </div>
            <div class="invoice-meta">
                <div class="invoice-title <?= $phieu['loai_phieu'] == 'nhap' ? 'import' : 'export' ?>">
                    <?= $phieu['loai_phieu'] == 'nhap' ? 'PHIẾU NHẬP KHO' : 'PHIẾU XUẤT KHO' ?>
                </div>
                <div class="meta-row">Số phiếu: <strong>#<?= $phieu['ma_phieu'] ?></strong></div>
                <div class="meta-row">Ngày tạo: <strong><?= date('d/m/Y H:i', strtotime($phieu['ngay_tao'])) ?></strong></div>
            </div>
        </div>

        <div class="info-grid">
            <div class="info-col">
                <h3>Thông tin kho hàng</h3>
                <div style="font-weight: 700; color: #4e73df;"><?= htmlspecialchars($phieu['ten_kho']) ?></div>
                <div><?= htmlspecialchars($phieu['dia_chi_kho']) ?></div>
                <div>SĐT: <?= htmlspecialchars($phieu['so_dien_thoai']) ?></div>
            </div>
            <div class="info-col">
                <h3>Người thực hiện</h3>
                <div style="font-weight: 700;"><?= htmlspecialchars($phieu['nguoi_tao']) ?></div>
                <div>ID : #<?= $phieu['nguoi_dung_id'] ?></div>
                <div>Trạng thái: <span style="color: green; font-weight: bold;">Hoàn thành</span></div>
            </div>
            <div class="info-col">
                <h3>Ghi chú / Lý do</h3>
                <div class="note-text">
                    <?= !empty($phieu['ghi_chu']) ? nl2br(htmlspecialchars($phieu['ghi_chu'])) : 'Không có ghi chú thêm.' ?>
                </div>
            </div>
        </div>

        <table class="invoice-table">
            <thead>
                <tr>
                    <th width="5%" class="text-center">STT</th>
                    <th width="40%">Sản phẩm</th>
                    <th width="20%">Phân loại</th>
                    <th width="10%" class="text-center">SL</th>
                    
                    <?php if($phieu['loai_phieu'] == 'nhap'): ?>
                        <th width="15%" class="text-end">Đơn giá</th>
                        <th width="15%" class="text-end">Thành tiền</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php 
                $i = $offset + 1;
                foreach ($items as $item): 
                    $img = !empty($item['hinh_con']) ? $item['hinh_con'] : $item['hinh_cha'];
                ?>
                <tr>
                    <td class="text-center"><?= $i++ ?></td>
                    <td>
                        <img src="../assets/img/products/<?= $img ?>" class="prod-thumb" onerror="this.src='../assets/img/no-image.png'">
                        <strong><?= htmlspecialchars($item['ten_san_pham']) ?></strong>
                    </td>
                    <td>
                        <?php if($item['bien_the_id']): ?>
                            <span class="variant-badge">
                                <?= htmlspecialchars($item['mau_sac']) ?> - <?= htmlspecialchars($item['dung_luong_ssd']) ?>
                            </span>
                        <?php else: ?>
                            <span style="color:#999; font-style:italic;">-- Mặc định --</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <strong style="font-size:15px;"><?= $item['so_luong'] ?></strong>
                    </td>

                    <?php if($phieu['loai_phieu'] == 'nhap'): ?>
                        <td class="text-end"><?= number_format($item['don_gia']) ?>đ</td>
                        <td class="text-end" style="font-weight: 700;"><?= number_format($item['don_gia'] * $item['so_luong']) ?>đ</td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($total_pages > 1): ?>
        <div class="pagination-area">
            <div style="font-size: 13px; color: #666;">
                Đang xem trang <strong><?= $page ?></strong> / <?= $total_pages ?>
            </div>
            <div>
                <?php 
                    $queryParams = $_GET; unset($queryParams['page']); // Giữ lại tham số id
                ?>
                
                <?php if ($page > 1): ?>
                    <a href="index.php?page=warehouse_receipt_detail&id=<?= $phieu_id ?>&p=<?= $page - 1 ?>" class="page-link-custom">&laquo;</a>
                <?php endif; ?>

                <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                    <a href="index.php?page=warehouse_receipt_detail&id=<?= $phieu_id ?>&p=<?= $p ?>" 
                       class="page-link-custom <?= ($p == $page) ? 'active' : '' ?>">
                       <?= $p ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="index.php?page=warehouse_receipt_detail&id=<?= $phieu_id ?>&p=<?= $page + 1 ?>" class="page-link-custom">&raquo;</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="summary-container">
            <div class="summary-box">
                <div class="summary-row">
                    <span>Tổng số mặt hàng (SKU):</span>
                    <strong><?= $summary['total_items'] ?></strong>
                </div>
                <div class="summary-row">
                    <span>Tổng số lượng sản phẩm:</span>
                    <strong><?= number_format($summary['total_qty']) ?></strong>
                </div>
                
                <?php if($phieu['loai_phieu'] == 'nhap'): ?>
                <div class="summary-row total">
                    <span>TỔNG GIÁ TRỊ:</span>
                    <span><?= number_format($summary['total_money']) ?>đ</span>
                </div>
                <div style="text-align: right; font-size: 12px; color: #888; margin-top: 5px;">
                    (Đã bao gồm thuế và chi phí liên quan)
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="signature-grid">
            <div>
                <div class="sign-title">Người lập phiếu</div>
                <div style="margin-top: 10px; font-weight: 600;"><?= htmlspecialchars($phieu['nguoi_tao']) ?></div>
                <div style="font-size: 12px; color: #888;">(Đã ký điện tử)</div>
            </div>
            <div>
                <div class="sign-title">Thủ kho / Quản lý</div>
                <div style="margin-top: 80px; border-top: 1px dashed #ccc; display: inline-block; width: 150px; padding-top: 5px;">
                    (Ký, họ tên)
                </div>
            </div>
        </div>

    </div>
</div>