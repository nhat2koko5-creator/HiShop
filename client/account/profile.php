<?php
// FILE: client/account/profile.php

// 1. (FIX LỖI) Định nghĩa chế độ Sửa/Xem
$is_edit_mode = isset($_GET['mode']) && $_GET['mode'] == 'edit';

// 2. Helper function: Xác định đường dẫn ảnh đại diện
function getAvatarUrl($data) {
    if (!empty($data['avatar']) && file_exists('assets/img/avatars/' . $data['avatar'])) {
        return 'assets/img/avatars/' . $data['avatar'];
    } else {
        // Ảnh mặc định nếu chưa upload
        return 'https://ui-avatars.com/api/?name=' . urlencode($data['ho_ten']) . '&background=ffebd0&color=fd7e14&size=128';
    }
}
$current_avatar = getAvatarUrl($data);
?>

<div class="cps-card full-width" style="min-height: 400px;">
    <div class="cps-card-header">
        <h3>Thông tin cá nhân</h3>
    </div>
    
    <div class="cps-card-body">
        
        <?php if (isset($update_success) && $update_success): ?>
            <div class="alert-box success">
                <span class="icon">✅</span> <span><?php echo $update_success; ?></span>
            </div>
        <?php endif; ?>
        
        <?php if (isset($update_error) && $update_error): ?>
            <div class="alert-box error">
                <span class="icon">❌</span> <span><?php echo $update_error; ?></span>
            </div>
        <?php endif; ?>


        <?php if ($is_edit_mode): ?>
            <form class="cps-form profile-edit-form" method="POST" action="index.php?page=account&section=profile" enctype="multipart/form-data">
                
                <div class="form-group" style="text-align: center; margin-bottom: 25px;">
                    <label style="display: block; margin-bottom: 10px;">Ảnh đại diện</label>
                    <div class="avatar-upload-container">
                        <img id="avatar-preview" src="<?php echo $current_avatar; ?>" alt="Avatar Preview" style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 3px solid #eee; margin-bottom: 10px; cursor: pointer;">
                        <br>
                        <label for="avatar_file" class="btn btn-outline btn-sm" style="cursor: pointer;">
                            📷 Chọn ảnh mới
                         </label>
                        <input type="file" id="avatar_file" name="avatar_file" accept="image/*" style="display: none;">
                        <p style="font-size: 12px; color: #888; margin-top: 5px;">Chấp nhận: JPG, PNG, GIF (Tối đa 2MB)</p>
                    </div>
                </div>

                <div class="form-group">
                    <label>Họ và tên</label>
                    <input type="text" name="ho_ten" class="cps-input" value="<?php echo htmlspecialchars($data['ho_ten']); ?>" required>
                </div>
                <div class="form-group">
                    <label>Số điện thoại</label>
                    <input type="tel" name="so_dien_thoai" class="cps-input" value="<?php echo htmlspecialchars($data['so_dien_thoai'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Giới tính</label>
                    <div class="radio-group">
                        <label class="radio-label"><input type="radio" name="gioi_tinh" value="male" <?php echo ($data['gioi_tinh'] == 'male') ? 'checked' : ''; ?>> Nam</label>
                        <label class="radio-label"><input type="radio" name="gioi_tinh" value="female" <?php echo ($data['gioi_tinh'] == 'female') ? 'checked' : ''; ?>> Nữ</label>
                        <label class="radio-label"><input type="radio" name="gioi_tinh" value="other" <?php echo ($data['gioi_tinh'] == 'other') ? 'checked' : ''; ?>> Khác</label>
                    </div>
                </div>
                <div class="form-group">
                    <label>Ngày sinh</label>
                    <input type="date" name="ngay_sinh" class="cps-input" value="<?php echo htmlspecialchars($data['ngay_sinh'] ?? ''); ?>">
                </div>
                
                <div class="form-actions" style="justify-content: center; gap: 15px; margin-top: 20px;">
                    <a href="index.php?page=account&section=profile" class="btn btn-outline">Hủy bỏ</a>
                    <button type="submit" class="btn btn-primary">Lưu thay đổi</button>
                </div>
            </form>
            
            <script>
                document.getElementById('avatar_file').addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    if (file) {
                        // Kiểm tra loại file
                        if (!file.type.startsWith('image/')) {
                            alert('Vui lòng chọn file ảnh.');
                            this.value = ''; // Reset input
                            return;
                        }
                        // Hiển thị xem trước
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            document.getElementById('avatar-preview').src = e.target.result;
                        }
                        reader.readAsDataURL(file);
                    }
                });
                // Cho phép click vào ảnh để chọn file
                document.getElementById('avatar-preview').addEventListener('click', function() {
                    document.getElementById('avatar_file').click();
                });
            </script>

        <?php else: ?>
            <div class="profile-view-container">
                <div class="profile-avatar-large">
                    <img src="<?php echo $current_avatar; ?>" alt="Avatar" style="object-fit: cover;">
                </div>

                <div class="profile-info-list">
                    <div class="info-row">
                        <span class="info-label">Họ và tên</span>
                        <span class="info-value"><?php echo htmlspecialchars($data['ho_ten']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Số điện thoại</span>
                        <span class="info-value"><?php echo htmlspecialchars($data['so_dien_thoai'] ?? 'Chưa cập nhật'); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Giới tính</span>
                        <span class="info-value">
                            <?php 
                                if ($data['gioi_tinh'] == 'male') echo 'Nam';
                                elseif ($data['gioi_tinh'] == 'female') echo 'Nữ';
                                else echo 'Khác';
                            ?>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Ngày sinh</span>
                        <span class="info-value">
                            <?php echo !empty($data['ngay_sinh']) ? date('d/m/Y', strtotime($data['ngay_sinh'])) : 'Chưa cập nhật'; ?>
                        </span>
                    </div>
                </div>

                <div class="profile-actions">
                    <a href="index.php?page=account&section=profile&mode=edit" class="btn btn-danger btn-wide">Chỉnh sửa thông tin</a>
                </div>
            </div>
        <?php endif; ?>

    </div>
</div>