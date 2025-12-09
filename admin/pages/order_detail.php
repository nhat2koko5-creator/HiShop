<?php
// FILE: admin/pages/order_detail.php

$order_id = $_GET['id'] ?? 0;

// 1. XỬ LÝ CẬP NHẬT TRẠNG THÁI
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_status'])) {
        $new_status = $_POST['order_status'];
        $cancel_reason = ($new_status == 'Đã hủy') ? trim($_POST['cancel_reason'] ?? 'Admin hủy đơn hàng') : null;

        $sql = "UPDATE don_hang SET trang_thai_don_hang = ?";
        $params = [$new_status];

        if ($new_status == 'Đã hủy') {
            $sql .= ", ly_do_huy = ?";
            $params[] = $cancel_reason;
        }
        $sql .= " WHERE id = ?";
        $params[] = $order_id;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        // --- LOGIC TỰ ĐỘNG CẬP NHẬT TIỀN (CHUẨN) ---
        
        // A. Giao thành công -> Auto "Đã thanh toán"
        if ($new_status == 'Đã giao hàng') {
             $pdo->prepare("UPDATE don_hang SET trang_thai_thanh_toan = 'Đã thanh toán' WHERE id = ?")->execute([$order_id]);
        }
        
        // B. Hủy đơn -> Chỉ hoàn tiền NẾU trước đó đã thanh toán
        if ($new_status == 'Đã hủy') {
            $check = $pdo->prepare("SELECT trang_thai_thanh_toan FROM don_hang WHERE id = ?");
            $check->execute([$order_id]);
            $current_money = $check->fetchColumn();
            
            if ($current_money == 'Đã thanh toán') {
                $pdo->prepare("UPDATE don_hang SET trang_thai_thanh_toan = 'Đã hoàn tiền' WHERE id = ?")->execute([$order_id]);
            } else {
                // Nếu chưa thanh toán (COD) thì hủy xong vẫn là chưa thanh toán (không đổi thành hoàn tiền)
            }
        }
        
        // C. Quay lại trạng thái chờ/đang giao -> Reset tiền nếu bị sai (Chỉ COD)
        if (in_array($new_status, ['Chờ xử lý', 'Đã xác nhận', 'Đang giao hàng'])) {
             $pdo->prepare("UPDATE don_hang SET trang_thai_thanh_toan = 'Chưa thanh toán' WHERE id = ? AND phuong_thuc_thanh_toan != 'VNPAY'")->execute([$order_id]);
        }

        echo "<script>window.location.href='index.php?page=order_detail&id=$order_id';</script>";
    }
}

// 2. LẤY DỮ LIỆU
$sql = "SELECT d.*, u.ho_ten as user_name, u.email as user_email, u.so_dien_thoai as user_phone, u.avatar
        FROM don_hang d
        LEFT JOIN nguoi_dung u ON d.nguoi_dung_id = u.id
        WHERE d.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$order_id]);
$order = $stmt->fetch();

if (!$order) { echo "<div class='alert alert-danger'>Không tìm thấy đơn hàng</div>"; return; }

// --- [FIX LỖI LOGIC DỮ LIỆU] TỰ ĐỘNG SỬA NẾU SAI ---
// Nếu đơn đang "Chờ xử lý" mà tiền lại "Đã hoàn tiền" -> Sửa lại thành "Chưa thanh toán" ngay lập tức
if ($order['trang_thai_don_hang'] == 'Chờ xử lý' && $order['trang_thai_thanh_toan'] == 'Đã hoàn tiền') {
    $pdo->prepare("UPDATE don_hang SET trang_thai_thanh_toan = 'Chưa thanh toán' WHERE id = ?")->execute([$order_id]);
    $order['trang_thai_thanh_toan'] = 'Chưa thanh toán'; // Cập nhật biến hiển thị
}
// ---------------------------------------------------

$items = getOrderItems($pdo, $order_id);

// Cấu hình danh sách trạng thái Admin được phép chọn
$status_list = ['Chờ xử lý', 'Đã xác nhận', 'Đang giao hàng', 'Đã hủy'];

// Nếu đơn hàng hiện tại ĐÃ LÀ "Đã giao hàng" (do khách bấm), thì thêm nó vào để hiển thị
if ($order['trang_thai_don_hang'] == 'Đã giao hàng') {
    $status_list[] = 'Đã giao hàng';
}

function getStatusColor($status) {
    switch($status) {
        case 'Chờ xử lý': return '#f59e0b';
        case 'Đã xác nhận': return '#3b82f6';
        case 'Đang giao hàng': return '#6366f1';
        case 'Đã giao hàng': return '#10b981';
        case 'Đã hủy': return '#ef4444';
        default: return '#64748b';
    }
}
?>
<link rel="stylesheet" href="../assets/css/admin/orders-detail.css">

<div class="admin-page-content admin-detail-page">
    
    <div class="top-action-bar no-print">
        <a href="index.php?page=orders_list" class="btn-back">
            <i class="fa-solid fa-arrow-left"></i> Quay lại danh sách đơn hàng
        </a>
        <button onclick="window.print()" class="btn-print">
            <i class="fa-solid fa-print"></i> In hóa đơn
        </button>
    </div>

    <div class="od-layout">
        
        <div class="col-left">
            <div class="od-card">
                <div class="od-card-header">
                    <div>
                        <h2 class="order-main-title">
                            Đơn hàng #<?= $order['id'] ?>
                            <span class="status-badge" style="background-color: <?= getStatusColor($order['trang_thai_don_hang']) ?>; margin-left: 10px;">
                                <?= $order['trang_thai_don_hang'] ?>
                            </span>
                        </h2>
                        <div class="order-sub-meta">
                            <span><?= date('H:i d/m/Y', strtotime($order['ngay_dat'])) ?></span>
                            <span style="color: #cbd5e1;">|</span>
                            <span>PTTT: <strong style="color: #334155;"><?= $order['phuong_thuc_thanh_toan'] ?? 'COD' ?></strong></span>
                        </div>
                    </div>
                    <div class="badge-count"><?= count($items) ?> sản phẩm</div>
                </div>

                <div class="od-card-body">
                    <table class="table-products">
                        <thead>
                            <tr>
                                <th width="50%">Sản phẩm</th>
                                <th width="15%" style="text-align: right;">Đơn giá</th>
                                <th width="10%" style="text-align: center;">SL</th>
                                <th width="25%" style="text-align: right;">Thành tiền</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): 
                                $img = !empty($item['hinh_bien_the']) ? "../assets/img/products/".$item['hinh_bien_the'] : (!empty($item['hinh_anh']) ? "../assets/img/products/".$item['hinh_anh'] : "../assets/img/no-image.png");
                            ?>
                            <tr>
                                <td>
                                    <div class="prod-flex">
                                        <img src="<?= $img ?>" class="prod-thumb" alt="">
                                        <div class="prod-info">
                                            <div><?= htmlspecialchars($item['ten_san_pham']) ?></div>
                                            <?php if ($item['mau_sac'] || $item['dung_luong_ssd']): ?>
                                                <span class="prod-variant">
                                                    <?= $item['mau_sac'] ?> <?= $item['dung_luong_ssd'] ? '• '.$item['dung_luong_ssd'] : '' ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td style="text-align: right;"><?= number_format($item['don_gia']) ?></td>
                                <td style="text-align: center;">x<?= $item['so_luong'] ?></td>
                                <td style="text-align: right; font-weight: 600;"><?= number_format($item['don_gia'] * $item['so_luong']) ?>đ</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <div class="summary-wrap">
                        <div class="summary-table">
                            <div class="sum-row">
                                <span>Tạm tính</span>
                                <span><?= number_format($order['tong_tien']) ?>đ</span>
                            </div>
                            <div class="sum-row">
                                <span>Phí vận chuyển</span>
                                <span>0đ (Miễn phí)</span>
                            </div>
                            <div class="sum-row total">
                                <span>Tổng cộng</span>
                                <span class="text-red"><?= number_format($order['tong_tien']) ?>đ</span>
                            </div>
                        </div>
                    </div>

                    <?php if(!empty($order['ghi_chu'])): ?>
                    <div style="margin-top: 20px; padding: 15px; background: #fffbeb; border: 1px dashed #f59e0b; border-radius: 6px; font-size: 13px; color: #b45309;">
                        <strong><i class="fa-solid fa-note-sticky"></i> Ghi chú:</strong> <?= htmlspecialchars($order['ghi_chu']) ?>
                    </div>
                    <?php endif; ?>

                    <div class="timeline-section no-print">
                        <div class="timeline-title">Lịch sử xử lý</div>
                        <ul class="timeline">
                            <li class="timeline-item active">
                                <div class="timeline-dot"></div>
                                <div class="t-title">Đặt hàng thành công</div>
                                <span class="t-time"><?= date('H:i d/m/Y', strtotime($order['ngay_dat'])) ?></span>
                                <p class="t-desc">Đơn hàng khởi tạo bởi khách hàng.</p>
                            </li>

                            <?php if(($order['phuong_thuc_thanh_toan'] ?? 'COD') == 'VNPAY' && $order['trang_thai_thanh_toan'] == 'Đã thanh toán'): ?>
                            <li class="timeline-item success">
                                <div class="timeline-dot"></div>
                                <div class="t-title" style="color:#10b981">Thanh toán thành công</div>
                                <p class="t-desc">Giao dịch qua VNPAY.</p>
                            </li>
                            <?php endif; ?>

                            <?php if(in_array($order['trang_thai_don_hang'], ['Đã xác nhận', 'Đang giao hàng', 'Đã giao hàng'])): ?>
                            <li class="timeline-item active">
                                <div class="timeline-dot"></div>
                                <div class="t-title">Đã xác nhận đơn hàng</div>
                                <p class="t-desc">Admin đã duyệt đơn.</p>
                            </li>
                            <?php endif; ?>

                            <?php if(in_array($order['trang_thai_don_hang'], ['Đang giao hàng', 'Đã giao hàng'])): ?>
                            <li class="timeline-item active">
                                <div class="timeline-dot"></div>
                                <div class="t-title">Đang vận chuyển</div>
                                <p class="t-desc">Đơn vị vận chuyển đã lấy hàng.</p>
                            </li>
                            <?php endif; ?>

                            <?php if($order['trang_thai_don_hang'] == 'Đã giao hàng'): ?>
                            <li class="timeline-item success">
                                <div class="timeline-dot"></div>
                                <div class="t-title" style="color:#10b981">Giao hàng thành công</div>
                                <p class="t-desc">Khách đã nhận hàng.</p>
                            </li>
                            <?php if(($order['phuong_thuc_thanh_toan'] ?? 'COD') != 'VNPAY' && $order['trang_thai_thanh_toan'] == 'Đã thanh toán'): ?>
                            <li class="timeline-item success">
                                <div class="timeline-dot"></div>
                                <div class="t-title" style="color:#10b981">Đã thu tiền COD</div>
                                <p class="t-desc">Shipper đã nộp tiền.</p>
                            </li>
                            <?php endif; ?>
                            <?php endif; ?>

                            <?php if($order['trang_thai_don_hang'] == 'Đã hủy'): ?>
                            <li class="timeline-item danger">
                                <div class="timeline-dot"></div>
                                <div class="t-title" style="color:#ef4444">Đã hủy đơn hàng</div>
                                <p class="t-desc">Lý do: <strong><?= $order['ly_do_huy'] ?></strong></p>
                            </li>
                            <?php endif; ?>
                            
                            <?php if($order['trang_thai_thanh_toan'] == 'Đã hoàn tiền'): ?>
                            <li class="timeline-item danger">
                                <div class="timeline-dot"></div>
                                <div class="t-title" style="color:#ef4444">Đã hoàn tiền</div>
                                <p class="t-desc">Tiền đã được hoàn trả lại.</p>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-right no-print">
            
            <div class="od-card">
                <div class="od-card-header"><span class="od-card-title">Khách hàng</span></div>
                <div class="od-card-body">
                    <div class="cust-profile">
                        <div class="cust-avatar"><?= strtoupper(substr($order['ho_ten_nguoi_nhan'], 0, 1)) ?></div>
                        <div class="cust-info">
                            <div><?= htmlspecialchars($order['ho_ten_nguoi_nhan']) ?></div>
                            <span>ID: #<?= $order['nguoi_dung_id'] ?></span>
                        </div>
                    </div>
                    <div class="contact-row"><i class="fa-solid fa-phone"></i> <?= htmlspecialchars($order['sdt_nguoi_nhan']) ?></div>
                    <div class="contact-row"><i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($order['dia_chi_giao_hang']) ?></div>
                    <div class="contact-row"><i class="fa-solid fa-envelope"></i> <?= htmlspecialchars($order['user_email'] ?? '---') ?></div>
                </div>
            </div>

            <div class="od-card" style="border-top: 3px solid #4f46e5;">
                <div class="od-card-header"><span class="od-card-title">Xử lý đơn hàng</span></div>
                <div class="od-card-body">
                    
                    <form method="POST" id="updateStatusForm">
                        <input type="hidden" name="update_status" value="1">
                        <input type="hidden" name="cancel_reason" id="hiddenCancelReason">

                        <div class="form-group" style="margin-bottom: 0;">
                            <label style="display:block; font-size:12px; font-weight:600; color:#64748b; margin-bottom:6px;">TRẠNG THÁI ĐƠN</label>
                            <select name="order_status" class="status-select" id="statusSelect">
                                <?php foreach ($status_list as $st): ?>
                                    <option value="<?= $st ?>" <?= $order['trang_thai_don_hang'] == $st ? 'selected' : '' ?>><?= $st ?></option>
                                <?php endforeach; ?>
                            </select>
                            
                            <button type="button" class="btn-primary" style="margin-top: 10px;" onclick="handleStatusUpdate()">
                                <i class="fa-solid fa-arrows-rotate"></i> Cập nhật
                            </button>
                        </div>
                    </form>

                    <div style="margin-top: 15px; padding-top: 15px; border-top: 1px dashed #e2e8f0; font-size: 13px; color: #64748b;">
                        <div>Thanh toán: <strong style="color: <?= $order['trang_thai_thanh_toan']=='Đã thanh toán'?'#10b981':'#f59e0b' ?>"><?= $order['trang_thai_thanh_toan'] ?></strong></div>
                        <div style="font-size: 11px; margin-top: 4px;">*Trạng thái tiền sẽ tự động cập nhật.</div>
                    </div>

                </div>
            </div>

        </div>
    </div>
</div>
<div id="adminCancelModal" class="admin-modal">
    <div class="admin-modal-content">
        <div class="am-header">
            <h3>Xác nhận hủy đơn hàng</h3>
        </div>
        <div class="am-body">
            
            <p id="modalWarningText" style="display:none; color:#ef4444; background:#fef2f2; padding:10px; border:1px solid #fecaca; border-radius:6px; margin-bottom:15px; font-weight:600; text-align:center;">
                ⚠️ Đơn hàng này ĐÃ THANH TOÁN.<br>Hãy chắc chắn bạn đã hoàn tiền cho khách trước khi hủy!
            </p>

            <p style="font-size:14px; color:#64748b; margin-bottom:10px;">
                Bạn đang hủy đơn hàng <strong>#<?= $order['id'] ?></strong>. Vui lòng nhập lý do.
            </p>

            <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 15px;">
                <button type="button" class="tag-reason" onclick="setReason('Hết hàng trong kho')">Hết hàng</button>
                <button type="button" class="tag-reason" onclick="setReason('Khách yêu cầu hủy')">Khách hủy</button>
                <button type="button" class="tag-reason" onclick="setReason('Sai thông tin giá')">Sai giá</button>
                <button type="button" class="tag-reason" onclick="setReason('Khách không nghe máy')">Ko nghe máy</button>
                <button type="button" class="tag-reason" onclick="setReason('Boom hàng')">Boom hàng</button>
            </div>

            <textarea id="modalReasonInput" class="am-textarea" placeholder="Hoặc nhập lý do chi tiết tại đây..."></textarea>
        </div>
        <div class="am-footer">
            <button type="button" class="btn-close-modal" onclick="closeCancelModal()">Đóng</button>
            <button type="button" class="btn-confirm-cancel" onclick="submitCancelForm()">Xác nhận Hủy</button>
        </div>
    </div>
</div>

<script>
    // [MỚI] Hàm điền lý do nhanh
    function setReason(text) {
        var input = document.getElementById('modalReasonInput');
        input.value = text;
        input.focus(); // Focus vào ô text để admin có thể sửa thêm nếu muốn
    }

    function handleStatusUpdate() {
        var statusSelect = document.getElementById('statusSelect');
        var selectedValue = statusSelect.value;
        var currentPaymentStatus = "<?= $order['trang_thai_thanh_toan'] ?>";

        if (selectedValue === 'Đã hủy') {
            if (currentPaymentStatus === 'Đã thanh toán') {
                document.getElementById('modalWarningText').style.display = 'block';
            } else {
                document.getElementById('modalWarningText').style.display = 'none';
            }
            document.getElementById('adminCancelModal').classList.add('show');
        } else {
            document.getElementById('updateStatusForm').submit();
        }
    }

    function closeCancelModal() { 
        document.getElementById('adminCancelModal').classList.remove('show'); 
    }

    function submitCancelForm() {
        var reason = document.getElementById('modalReasonInput').value.trim();
        if (reason === "") {
            alert("Vui lòng chọn hoặc nhập lý do hủy đơn!");
            return;
        }
        document.getElementById('hiddenCancelReason').value = reason;
        document.getElementById('updateStatusForm').submit();
    }

    window.onclick = function(event) { 
        if (event.target == document.getElementById('adminCancelModal')) {
            closeCancelModal();
        }
    }
</script>