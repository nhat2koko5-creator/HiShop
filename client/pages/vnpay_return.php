<?php
// FILE: client/pages/vnpay_return.php
require_once 'src/config.php';

// --- PHẦN 1: LOGIC XỬ LÝ (GIỮ NGUYÊN LOGIC CŨ CỦA BẠN) ---
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

$order_id_raw = $_GET['vnp_TxnRef'] ?? '';
$real_order_id = $order_id_raw; 
$amount = isset($_GET['vnp_Amount']) ? $_GET['vnp_Amount'] / 100 : 0;
$vnp_BankCode = $_GET['vnp_BankCode'] ?? 'Không xác định';
$vnp_TransactionNo = $_GET['vnp_TransactionNo'] ?? '0';
$order_desc = $_GET['vnp_OrderInfo'] ?? '';
$vnp_ResponseCode = $_GET['vnp_ResponseCode'] ?? '';

$is_success = false;
$status_title = "";
$status_desc = "";

if ($secureHash == $vnp_SecureHash) {
    if ($vnp_ResponseCode == '00') {
        // --- THANH TOÁN THÀNH CÔNG ---
        $is_success = true;
        $status_title = "Thanh toán thành công!";
        $status_desc = "Cảm ơn bạn đã mua hàng. Đơn hàng đã được thanh toán và đang chờ xử lý.";

        try {
            // Cập nhật DB (Logic cũ của bạn)
            $stmt = $pdo->prepare("UPDATE don_hang SET trang_thai_don_hang = 'Chờ xử lý', trang_thai_thanh_toan = 'Đã thanh toán' WHERE id = ?");
            $stmt->execute([$real_order_id]);

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
        $is_success = false;
        $status_title = "Thanh toán thất bại!";
        $status_desc = "Giao dịch không thành công hoặc đã bị hủy.";
        // Cập nhật trạng thái hủy
        $stmt = $pdo->prepare("UPDATE don_hang SET trang_thai_don_hang = 'Đã hủy' WHERE id = ?");
        $stmt->execute([$real_order_id]);
    }
} else {
    // --- LỖI BẢO MẬT ---
    $is_success = false;
    $status_title = "Cảnh báo bảo mật!";
    $status_desc = "Chữ ký không hợp lệ. Vui lòng liên hệ bộ phận hỗ trợ.";
}
?>

<?php require_once 'client/layouts/header.php'; ?>
<link rel="stylesheet" href="assets/css/client/checkout.css">

<div class="payment-result-container">
    <div class="payment-result-card <?= $is_success ? 'success-mode' : 'error-mode' ?>">
        
        <div class="pr-header">
            <div class="pr-icon-wrapper">
                <?php if ($is_success): ?>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="pr-svg-icon">
                        <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12zm13.36-1.814a.75.75 0 10-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.14-.094l3.75-5.25z" clip-rule="evenodd" />
                    </svg>
                <?php else: ?>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="pr-svg-icon">
                        <path fill-rule="evenodd" d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25zm-1.72 6.97a.75.75 0 10-1.06 1.06L10.94 12l-1.72 1.72a.75.75 0 101.06 1.06L12 13.06l1.72 1.72a.75.75 0 101.06-1.06L13.06 12l1.72-1.72a.75.75 0 10-1.06-1.06L12 10.94l-1.72-1.72z" clip-rule="evenodd" />
                    </svg>
                <?php endif; ?>
            </div>
            <h2 class="pr-title"><?= $status_title ?></h2>
            <p class="pr-desc"><?= $status_desc ?></p>
        </div>

        <?php if ($secureHash == $vnp_SecureHash): ?>
        <div class="pr-key-info">
            <div class="pr-info-item">
                <span class="pr-label">Mã đơn hàng</span>
                <span class="pr-value highlight">#<?= htmlspecialchars($real_order_id) ?></span>
            </div>
            <div class="pr-info-item">
                <span class="pr-label">Tổng thanh toán</span>
                <span class="pr-value total-amount"><?= number_format($amount, 0, ',', '.') ?>₫</span>
            </div>
            <div class="pr-info-item">
                <span class="pr-label">Phương thức</span>
                <span class="pr-value">VNPAY <img src="assets/img/vnpay.jpg" alt="VNPAY" style="height: 20px; vertical-align: middle; margin-left: 5px;"></span>
            </div>
        </div>

        <div class="pr-tech-details-wrapper">
            <button type="button" class="pr-toggle-btn" onclick="toggleDetails()">
                Xem chi tiết giao dịch <i class="fa-solid fa-chevron-down" id="toggleIcon"></i>
            </button>
            <div class="pr-tech-details-content" id="techDetails">
                <div class="detail-row">
                    <span class="detail-label">Ngân hàng:</span>
                    <span class="detail-value"><?= htmlspecialchars($vnp_BankCode) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Mã GD VNPAY:</span>
                    <span class="detail-value"><?= htmlspecialchars($vnp_TransactionNo) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Nội dung:</span>
                    <span class="detail-value"><?= htmlspecialchars($order_desc) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Thời gian:</span>
                    <span class="detail-value"><?= date('d/m/Y H:i:s') ?></span>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="pr-actions">
            <a href="index.php?page=order_detail&id=<?= $real_order_id ?>" class="btn btn-primary btn-block">
                Xem chi tiết đơn hàng
            </a>
            <a href="index.php?page=home" class="btn btn-outline btn-block">
                Tiếp tục mua sắm
            </a>
        </div>
    </div>
</div>

<script>
    // Script đơn giản để đóng mở phần chi tiết
    function toggleDetails() {
        const details = document.getElementById('techDetails');
        const icon = document.getElementById('toggleIcon');
        details.classList.toggle('show');
        icon.classList.toggle('fa-chevron-up');
        icon.classList.toggle('fa-chevron-down');
    }
</script>

<?php require_once 'client/layouts/footer.php'; ?>