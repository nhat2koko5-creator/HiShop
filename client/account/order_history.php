<?php
// FILE: client/account/order_history.php

// --- 1. XỬ LÝ HỦY ĐƠN HÀNG ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cancel_order_id'])) {
    $cancel_id = $_POST['cancel_order_id'];
    $reason_select = $_POST['cancel_reason_select'] ?? '';
    $reason_other  = trim($_POST['cancel_reason_other'] ?? '');
    
    // Gộp lý do
    $final_reason = $reason_select;
    if ($reason_select == 'Lý do khác' && !empty($reason_other)) {
        $final_reason = $reason_other;
    }

    // Kiểm tra điều kiện an toàn
    $stmt_check = $pdo->prepare("
        SELECT id 
        FROM don_hang 
        WHERE id = ? 
          AND nguoi_dung_id = ? 
          AND trang_thai_don_hang = 'Chờ xử lý' 
          AND trang_thai_thanh_toan = 'Chưa thanh toán' 
    ");
    $stmt_check->execute([$cancel_id, $user_id]);
    
    if ($stmt_check->rowCount() > 0) {
        // Thực hiện hủy
        $stmt_cancel = $pdo->prepare("UPDATE don_hang SET trang_thai_don_hang = 'Đã hủy', ly_do_huy = ? WHERE id = ?");
        $stmt_cancel->execute([$final_reason, $cancel_id]);
        
        // Lưu thông báo thành công
        $_SESSION['notification'] = [
            'type' => 'success',
            'title' => 'Hủy đơn thành công!',
            'message' => 'Đơn hàng #' . $cancel_id . ' đã được hủy theo yêu cầu của bạn.'
        ];
    } else {
        // Lưu thông báo lỗi
        $_SESSION['notification'] = [
            'type' => 'error',
            'title' => 'Hủy đơn thất bại',
            'message' => 'Không thể hủy đơn hàng này. Vui lòng liên hệ CSKH.'
        ];
    }
    
    // [FIX LỖI HEADER] Dùng JS để chuyển hướng
    echo "<script>window.location.href='index.php?page=account&section=orders';</script>";
    exit;
}

// --- 2. XỬ LÝ KHÁCH XÁC NHẬN ĐÃ NHẬN HÀNG ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_received_id'])) {
    $conf_id = $_POST['confirm_received_id'];
    
    // Kiểm tra: Chỉ đơn "Đang giao hàng" mới được xác nhận
    $stmt_check = $pdo->prepare("SELECT id, phuong_thuc_thanh_toan FROM don_hang WHERE id = ? AND nguoi_dung_id = ? AND trang_thai_don_hang = 'Đang giao hàng'");
    $stmt_check->execute([$conf_id, $user_id]);
    $order_info = $stmt_check->fetch();

    if ($order_info) {
        // [QUAN TRỌNG] CẬP NHẬT NGAY_HOAN_THANH ĐỂ TÍNH DOANH THU CHO HÔM NAY
        $stmt_update = $pdo->prepare("
            UPDATE don_hang 
            SET trang_thai_don_hang = 'Đã giao hàng',
                trang_thai_thanh_toan = 'Đã thanh toán',
                ngay_hoan_thanh = NOW()
            WHERE id = ?
        ");
        $stmt_update->execute([$conf_id]);

        $_SESSION['notification'] = [
            'type' => 'success',
            'title' => 'Cảm ơn bạn!',
            'message' => 'Bạn đã xác nhận nhận hàng thành công. Đơn hàng hoàn tất.'
        ];
    }
    
    echo "<script>window.location.href='index.php?page=account&section=orders&status=delivered';</script>";
    exit;
}

// --- 2. CÁC PHẦN CÒN LẠI GIỮ NGUYÊN ---
$keyword = $_GET['q'] ?? '';
$all_orders = getUserOrders($pdo, $user_id, $keyword); 

$status_mapping = [
    'pending'   => 'Chờ xử lý',
    'confirmed' => 'Đã xác nhận', 
    'shipping'  => 'Đang giao hàng', 
    'delivered' => 'Đã giao hàng',
    'cancelled' => 'Đã hủy'
];

$tabs = [
    'all'       => 'Tất cả',
    'pending'   => 'Chờ xác nhận',
    'confirmed' => 'Đã xác nhận', 
    'shipping'  => 'Đang giao',   
    'delivered' => 'Hoàn tất',
    'cancelled' => 'Đã hủy'
];

$current_tab = $_GET['status'] ?? 'all';

$filtered_orders = [];
if (!empty($all_orders)) {
    foreach ($all_orders as $order) {
        $db_status_vietnamese = $order['trang_thai_don_hang'];
        if ($current_tab == 'all') {
            $filtered_orders[] = $order;
        } else {
            if (isset($status_mapping[$current_tab]) && $status_mapping[$current_tab] === $db_status_vietnamese) {
                $filtered_orders[] = $order;
            }
        }
    }
}
?>
<link rel="stylesheet" href="assets/css/client/account.css">

<div class="cps-card full-width" style="min-height: 500px;">
    
    <div class="order-page-header">
        <h3 class="section-title-simple">Đơn hàng của tôi</h3>
        <form action="index.php" method="GET" class="order-search-box">
            <input type="hidden" name="page" value="account">
            <input type="hidden" name="section" value="orders">
            <input type="text" name="q" value="<?= htmlspecialchars($keyword) ?>" placeholder="Tìm theo mã đơn hoặc tên sản phẩm...">
            <button type="submit"><i class="fa-solid fa-magnifying-glass"></i></button>
        </form>
    </div>

    <div class="fpt-tabs-wrapper">
        <div class="fpt-tabs">
            <?php foreach ($tabs as $key => $label): ?>
                <a href="index.php?page=account&section=orders&status=<?= $key ?>" 
                   class="fpt-tab-item <?= ($current_tab == $key) ? 'active' : '' ?>">
                   <?= $label ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="cps-card-body" style="background-color: #f8f9fa; padding: 15px;">
        
        <?php if (empty($filtered_orders)): ?>
            <div class="empty-order-state">
                <img src="assets/img/empty-box.png" onerror="this.src='https://cdn-icons-png.flaticon.com/512/4076/4076432.png'" alt="Empty">
                <p>Không tìm thấy đơn hàng phù hợp</p>
                <a href="index.php?page=product_list" class="btn btn-primary-red">Khám phá ngay</a>
            </div>
        <?php else: ?>
            
            <div class="order-list-group">
                <?php foreach ($filtered_orders as $order): 
                    $items = getOrderItems($pdo, $order['id']);
                    $status_text = $order['trang_thai_don_hang'];
                    $pay_status = $order['trang_thai_thanh_toan'] ?? 'Chưa thanh toán';

                    $status_class = '';
                    switch ($status_text) {
                        case 'Chờ xử lý':       $status_class = 'pending'; break; 
                        case 'Đã xác nhận':     $status_class = 'processing'; break; 
                        case 'Đang giao hàng':  $status_class = 'shipping'; break; 
                        case 'Đã giao hàng':    $status_class = 'delivered'; break; 
                        case 'Đã hủy':          $status_class = 'cancelled'; break; 
                        default:                $status_class = 'pending';
                    }

                    $buy_again_url = 'index.php?page=product_list'; 
                    if (!empty($items) && isset($items[0]['san_pham_id'])) {
                        $buy_again_url = 'index.php?page=product_detail&id=' . $items[0]['san_pham_id'];
                    }
                ?>
                    <div class="fpt-order-card">
                        <div class="foc-header">
                            <div class="foc-id">
                                <strong>#<?= htmlspecialchars($order['id']) ?></strong>
                                <span class="foc-date"><?= date('d/m/Y H:i', strtotime($order['ngay_dat'])) ?></span>
                            </div>
                            <div class="foc-status <?= $status_class ?>">
                                <?= htmlspecialchars($status_text) ?>
                            </div>
                        </div>

                        <div class="foc-body">
                            <?php foreach ($items as $item): 
                                $imgName = !empty($item['hinh_bien_the']) ? $item['hinh_bien_the'] : $item['hinh_anh'];
                                $img = !empty($imgName) ? "assets/img/products/".$imgName : "assets/img/no-image.png";
                            ?>
                            <div class="foc-product-item">
                                <div class="foc-img"><img src="<?= $img ?>" alt="Product"></div>
                                <div class="foc-info">
                                    <div class="foc-name"><?= htmlspecialchars($item['ten_san_pham']) ?></div>
                                    <?php if (!empty($item['mau_sac'])): ?>
                                        <div class="foc-variant">
                                            <?= htmlspecialchars($item['mau_sac']) ?> <?= !empty($item['dung_luong_ssd']) ? '- '.$item['dung_luong_ssd'] : '' ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="foc-variant" style="background:none; border:none; padding:0; color:#888;">x<?= $item['so_luong'] ?></div>
                                </div>
                                <div class="foc-price"><?= number_format($item['don_gia']) ?>₫</div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="foc-footer">
                            <div class="foc-total">
                                <span>Thành tiền:</span>
                                <strong class="total-price"><?= number_format($order['tong_tien']) ?>₫</strong>
                            </div>
                          <div class="foc-actions">                               
                                <?php if ($status_text == 'Chờ xử lý'): ?>                                
                                    <?php if ($pay_status == 'Chưa thanh toán'): ?>
                                        <button type="button" class="btn btn-outline" style="border-color: #999; color: #666;" 
                                                onclick="openCancelModal(<?= $order['id'] ?>)">
                                            Hủy đơn
                                        </button>                                  
                                    <?php else: ?>
                                        <button type="button" class="btn btn-outline" style="border-color: #f59e0b; color: #f59e0b; background: #fffbeb;" 
                                                onclick="openRefundModal(<?= $order['id'] ?>)">
                                            <i class="fa-solid fa-rotate-left"></i> Hủy Đơn/Yêu Cầu Hoàn Tiền
                                        </button>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <?php if ($status_text == 'Đang giao hàng'): ?>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Bạn xác nhận đã nhận được hàng và sản phẩm không có vấn đề gì chứ?');">
                                        <input type="hidden" name="confirm_received_id" value="<?= $order['id'] ?>">
                                        <button type="submit" class="btn btn-primary" style="background-color: #10b981; border-color: #10b981; color: white;">
                                            <i class="fa-solid fa-check-circle"></i> Đã nhận được hàng
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <?php if ($status_text == 'Đã giao hàng' || $status_text == 'Đã hủy'): ?>
                                    <a href="<?= $buy_again_url ?>" class="btn btn-outline-red">Mua lại</a>
                                <?php endif; ?>

                                <a href="index.php?page=order_detail&id=<?= $order['id'] ?>" class="btn btn-solid-red">Xem chi tiết</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div id="cancelOrderModal" class="cancel-modal">
    <div class="cancel-modal-content">
        <h3>Xác nhận hủy đơn hàng</h3>
        <p style="margin-bottom: 20px; color: #666; font-size: 14px;">Bạn có chắc chắn muốn hủy đơn hàng này? Thao tác này không thể hoàn tác.</p>
        <form method="POST" id="cancelForm">
            <input type="hidden" name="cancel_order_id" id="modal_cancel_order_id">
            <div class="cancel-form-group">
                <label>Lý do hủy đơn *</label>
                <select name="cancel_reason_select" id="reasonSelect" required onchange="toggleOtherReason()">
                    <option value="" disabled selected>-- Chọn lý do --</option>
                    <option value="Thay đổi địa chỉ nhận hàng">Thay đổi địa chỉ nhận hàng</option>
                    <option value="Muốn thay đổi sản phẩm">Muốn thay đổi sản phẩm</option>
                    <option value="Tìm thấy giá rẻ hơn ở nơi khác">Tìm thấy giá rẻ hơn ở nơi khác</option>
                    <option value="Đổi ý, không muốn mua nữa">Đổi ý, không muốn mua nữa</option>
                    <option value="Lý do khác">Lý do khác...</option>
                </select>
            </div>
            <div class="cancel-form-group" id="otherReasonGroup" style="display: none;">
                <label>Nhập lý do chi tiết *</label>
                <textarea name="cancel_reason_other" rows="3" placeholder="Vui lòng cho chúng tôi biết lý do..."></textarea>
            </div>
            <div class="cancel-actions">
                <button type="button" class="btn btn-close-modal" onclick="closeCancelModal()">Đóng</button>
                <button type="submit" class="btn btn-confirm-cancel">Xác nhận hủy</button>
            </div>
        </form>
    </div>
</div>


<div id="customNotifyModal" class="notify-modal-overlay">
    <div class="notify-card" id="notifyCard">
        <div class="notify-icon-box"><i class="fa-solid" id="notifyIcon"></i></div>
        <h3 class="notify-title" id="notifyTitle">Thông báo</h3>
        <p class="notify-msg" id="notifyMsg">Nội dung thông báo</p>
        <button class="btn-notify" onclick="closeNotifyModal()">Đã hiểu</button>
    </div>
</div>
<div id="refundModal" class="cancel-modal">
    <div class="cancel-modal-content" style="max-width: 500px; text-align: center;">
        <div style="font-size: 50px; color: #f59e0b; margin-bottom: 15px;">
            <i class="fa-solid fa-headset"></i>
        </div>
        <h3 style="color: #333; margin-bottom: 10px;">Yêu cầu hỗ trợ hoàn tiền</h3>
        
        <p style="color: #555; font-size: 15px; line-height: 1.6; margin-bottom: 20px;">
            Đơn hàng <strong>#<span id="refund_order_id"></span></strong> của bạn đã được thanh toán. 
            Để đảm bảo an toàn tài chính, vui lòng liên hệ bộ phận CSKH để được hỗ trợ hủy đơn và hoàn tiền về tài khoản ngân hàng.
        </p>

        <div style="background: #f9fafb; padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: left;">
            <div style="margin-bottom: 8px;"><strong><i class="fa-solid fa-phone"></i> Hotline:</strong> 0987.654.321</div>
            <div><strong><i class="fa-solid fa-envelope"></i> Email:</strong> support@hishop.vn</div>
        </div>

        <div class="cancel-actions" style="justify-content: center;">
            <button type="button" class="btn btn-close-modal" onclick="closeRefundModal()">Đóng</button>
            <a href="index.php?page=contact" class="btn btn-primary" style="background: #0088ff; border:none; text-decoration:none;">
                <i class="fa-regular fa-paper-plane"></i> Liên hệ hỗ trợ ngay
            </a>
        </div>
    </div>
</div>
<script>
     function openCancelModal(orderId) {
        document.getElementById('modal_cancel_order_id').value = orderId;
        document.getElementById('cancelOrderModal').style.display = 'flex';
    }
    function closeCancelModal() { document.getElementById('cancelOrderModal').style.display = 'none'; }
    function toggleOtherReason() {
        var select = document.getElementById('reasonSelect');
        var otherGroup = document.getElementById('otherReasonGroup');
        if (select.value === 'Lý do khác') {
            otherGroup.style.display = 'block';
            otherGroup.querySelector('textarea').required = true;
        } else {
            otherGroup.style.display = 'none';
            otherGroup.querySelector('textarea').required = false;
        }
    }
    window.onclick = function(event) {
        if (event.target == document.getElementById('cancelOrderModal')) closeCancelModal();
    }
    function closeNotifyModal() {
        const modal = document.getElementById('customNotifyModal');
        modal.classList.remove('show');
    }
    document.addEventListener('DOMContentLoaded', function() {
        <?php if (isset($_SESSION['notification'])): ?>
            const notify = <?php echo json_encode($_SESSION['notification']); ?>;
            const modal = document.getElementById('customNotifyModal');
            const card = document.getElementById('notifyCard');
            const icon = document.getElementById('notifyIcon');
            const title = document.getElementById('notifyTitle');
            const msg = document.getElementById('notifyMsg');

            title.textContent = notify.title;
            msg.textContent = notify.message;

            if (notify.type === 'success') {
                card.className = 'notify-card success';
                icon.className = 'fa-solid fa-check';
            } else {
                card.className = 'notify-card error';
                icon.className = 'fa-solid fa-xmark';
            }
            modal.classList.add('show');
            <?php unset($_SESSION['notification']); ?>
        <?php endif; ?>
    });
    const notifyOverlay = document.getElementById('customNotifyModal');
    if(notifyOverlay){
        notifyOverlay.addEventListener('click', function(e){
            if(e.target === notifyOverlay) closeNotifyModal();
        });
    }
        // Script cho Modal Hoàn tiền
    function openRefundModal(orderId) {
        document.getElementById('refund_order_id').textContent = orderId;
        document.getElementById('refundModal').style.display = 'flex';
    }

    function closeRefundModal() {
        document.getElementById('refundModal').style.display = 'none';
    }

    // Đóng khi click ra ngoài (Cập nhật để xử lý cả 2 modal)
    window.onclick = function(event) {
        let cancelModal = document.getElementById('cancelOrderModal');
        let refundModal = document.getElementById('refundModal');
        
        if (event.target == cancelModal) closeCancelModal();
        if (event.target == refundModal) closeRefundModal();
    }
</script>
