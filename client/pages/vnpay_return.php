<?php
// FILE: client/pages/vnpay_return.php
require_once 'src/config.php';

// 1. LẤY DỮ LIỆU TỪ VNPAY
$vnp_SecureHash = $_GET['vnp_SecureHash'] ?? '';
$inputData = array();
foreach ($_GET as $key => $value) {
    if (substr($key, 0, 4) == "vnp_") {
        $inputData[$key] = $value;
    }
}

unset($inputData['vnp_SecureHash']);
ksort($inputData);
$i = 0;
$hashData = "";
foreach ($inputData as $key => $value) {
    if ($i == 1) {
        $hashData = $hashData . '&' . urlencode($key) . "=" . urlencode($value);
    } else {
        $hashData = $hashData . urlencode($key) . "=" . urlencode($value);
        $i = 1;
    }
}

$secureHash = hash_hmac('sha512', $hashData, VNP_HASH_SECRET);

// 2. XỬ LÝ KẾT QUẢ
$order_id_raw = $_GET['vnp_TxnRef'] ?? '';
// Tách lấy ID thật (vì lúc gửi ta gửi dạng ID_Time)
$parts = explode('_', $order_id_raw);
$real_order_id = $parts[0];

$amount = isset($_GET['vnp_Amount']) ? $_GET['vnp_Amount'] / 100 : 0;
$vnp_BankCode = $_GET['vnp_BankCode'] ?? 'Không xác định';
$vnp_TransactionNo = $_GET['vnp_TransactionNo'] ?? '0';
$order_desc = $_GET['vnp_OrderInfo'] ?? '';
$vnp_ResponseCode = $_GET['vnp_ResponseCode'] ?? '';

$status_msg = "";
$status_class = "";
$status_icon = "";

if ($secureHash == $vnp_SecureHash) {
    if ($vnp_ResponseCode == '00') {
        // --- THANH TOÁN THÀNH CÔNG ---
        $status_msg = "Giao dịch thành công!";
        $status_class = "success";
        $status_icon = "✅";

        // [QUAN TRỌNG] CẬP NHẬT TRẠNG THÁI ĐƠN HÀNG VÀO DB NGAY TẠI ĐÂY
        // Vì localhost không nhận được IPN nên phải cập nhật ở đây
      try {
            // Cập nhật cả 2 cột:
            // 1. Trạng thái thanh toán -> 'paid'
            // 2. Trạng thái đơn hàng -> 'confirmed' (Đã xác nhận, chờ đóng gói)
            $stmt = $pdo->prepare("UPDATE don_hang SET trang_thai = 'pending', trang_thai_thanh_toan = 'paid' WHERE id = ?");
            $stmt->execute([$real_order_id]);

            // 2. Lưu lịch sử thanh toán (Kiểm tra xem đã lưu chưa để tránh trùng)
            $check = $pdo->prepare("SELECT id FROM thanh_toan WHERE ma_giao_dich = ?");
            $check->execute([$vnp_TransactionNo]);
            
            if ($check->rowCount() == 0) {
                $stmt_log = $pdo->prepare("INSERT INTO thanh_toan (don_hang_id, so_tien, phuong_thuc, trang_thai, ngay_thanh_toan, ma_giao_dich) VALUES (?, ?, 'VNPAY', 'success', NOW(), ?)");
                $stmt_log->execute([$real_order_id, $amount, $vnp_TransactionNo]);
            }
            
        } catch (Exception $e) {
            error_log("Lỗi cập nhật DB tại vnpay_return: " . $e->getMessage());
        }

    } else {
        // --- THANH TOÁN THẤT BẠI ---
        $status_msg = "Giao dịch thất bại!";
        $status_class = "error";
        $status_icon = "❌";
        
        // Có thể cập nhật trạng thái đơn thành 'failed' hoặc 'cancelled' nếu muốn
        $stmt = $pdo->prepare("UPDATE don_hang SET trang_thai = 'failed' WHERE id = ?");
        $stmt->execute([$real_order_id]);
    }
} else {
    $status_msg = "Chữ ký không hợp lệ!";
    $status_class = "error";
    $status_icon = "⚠️";
}
?>

<?php require_once 'client/layouts/header.php'; ?>

<style>

</style>

<div class="container">
    <div class="result-box">
        <div class="result-icon <?= $status_class ?>"><?= $status_icon ?></div>
        <h2 class="result-title"><?= $status_msg ?></h2>
        
        <?php if ($secureHash == $vnp_SecureHash): ?>
        <div class="result-details">
            <div class="detail-row">
                <span class="detail-label">Mã đơn hàng:</span>
                <span class="detail-value">#<?= htmlspecialchars($real_order_id) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Số tiền:</span>
                <span class="detail-value"><?= number_format($amount, 0, ',', '.') ?>₫</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Ngân hàng:</span>
                <span class="detail-value"><?= htmlspecialchars($vnp_BankCode) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Mã giao dịch:</span>
                <span class="detail-value"><?= htmlspecialchars($vnp_TransactionNo) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Nội dung:</span>
                <span class="detail-value"><?= htmlspecialchars($order_desc) ?></span>
            </div>
        </div>
        <?php endif; ?>

        <div class="action-buttons">
            <a href="index.php?page=home" class="btn btn-outline">Về trang chủ</a>
            <a href="index.php?page=order_detail&id=<?= $real_order_id ?>" class="btn btn-primary">Xem chi tiết đơn hàng</a>
        </div>
    </div>
</div>

<?php require_once 'client/layouts/footer.php'; ?>