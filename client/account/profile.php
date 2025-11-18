<?php
// FILE: client/account/profile.php
// Biến $data, $update_success, $update_error được truyền từ account.php
?>

<div class="cps-card full-width">
    <div class="cps-card-header">
        <h3>Thông tin tài khoản</h3>
    </div>
    <div class="cps-card-body">
        
        <?php if (isset($update_success) && $update_success): ?>
            <div class="alert-box success">
                <span class="icon">✅</span>
                <span><?php echo $update_success; ?></span>
            </div>
        <?php endif; ?>
        
        <?php if (isset($update_error) && $update_error): ?>
            <div class="alert-box error">
                <span class="icon">❌</span>
                <span><?php echo $update_error; ?></span>
            </div>
        <?php endif; ?>

        <form class="cps-form" method="POST" action="index.php?page=account&section=profile">
            
            <div class="form-section">
                <h4 class="form-section-title">Thông tin cá nhân</h4>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="ho_ten">Họ và tên</label>
                        <input type="text" id="ho_ten" name="ho_ten" class="cps-input" value="<?php echo htmlspecialchars($data['ho_ten']); ?>" required placeholder="Nhập họ tên của bạn">
                    </div>
                    <div class="form-group">
                        <label for="so_dien_thoai">Số điện thoại</label>
                        <input type="tel" id="so_dien_thoai" name="so_dien_thoai" class="cps-input" value="<?php echo htmlspecialchars($data['so_dien_thoai'] ?? ''); ?>" placeholder="Nhập số điện thoại">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="gioi_tinh">Giới tính</label>
                        <div class="radio-group">
                            <label class="radio-label">
                                <input type="radio" name="gioi_tinh" value="male" <?php echo ($data['gioi_tinh'] == 'male') ? 'checked' : ''; ?>>
                                <span>Nam</span>
                            </label>
                            <label class="radio-label">
                                <input type="radio" name="gioi_tinh" value="female" <?php echo ($data['gioi_tinh'] == 'female') ? 'checked' : ''; ?>>
                                <span>Nữ</span>
                            </label>
                            <label class="radio-label">
                                <input type="radio" name="gioi_tinh" value="other" <?php echo ($data['gioi_tinh'] == 'other') ? 'checked' : ''; ?>>
                                <span>Khác</span>
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="ngay_sinh">Ngày sinh</label>
                        <input type="date" id="ngay_sinh" name="ngay_sinh" class="cps-input" value="<?php echo htmlspecialchars($data['ngay_sinh'] ?? ''); ?>">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h4 class="form-section-title">Thông tin đăng nhập</h4>
                <div class="form-group full-width">
                    <label for="email">Email</label>
                    <input type="email" id="email" class="cps-input disabled" value="<?php echo htmlspecialchars($data['email']); ?>" disabled>
                    <span class="input-note">Bạn không thể thay đổi địa chỉ email đăng nhập.</span>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary btn-lg">Cập nhật thông tin</button>
            </div>
        </form>
    </div>
</div>