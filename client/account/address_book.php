<?php
// FILE: client/account/address_book.php
?>
<link rel="stylesheet" href="assets/css/account.css">
<div class="cps-card full-width">
    <div class="cps-card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h3>Sổ địa chỉ</h3>
        <button class="btn-sm btn-primary" id="btnAddAddress">+ Thêm địa chỉ</button>
    </div>
    
    <div class="cps-card-body">
        <?php if (empty($data)): ?>
            <div class="empty-state">
                <img src="assets/img/shipper.png" alt="Empty" style="width: 80px; opacity: 0.5; margin-bottom: 15px;">
                <p>Bạn chưa lưu địa chỉ nào.</p>
            </div>
        <?php else: ?>
            <div class="address-list">
                <?php foreach ($data as $address): ?>
                    <div class="address-item-card">
                        <div class="aic-content">
                            <div class="aic-icon">📍</div>
                            <div class="aic-text">
                                <strong>Địa chỉ nhận hàng</strong>
                                <p class="addr-text"><?php echo htmlspecialchars($address['dia_chi_cu_the']); ?></p>
                            </div>
                        </div>
                        <div class="aic-actions">
                            <button class="btn-text btn-edit" 
                                    data-id="<?= $address['id'] ?>" 
                                    data-text="<?= htmlspecialchars($address['dia_chi_cu_the']) ?>">
                                Sửa
                            </button>

                            <button class="btn-text text-red btn-delete" 
                                    data-id="<?= $address['id'] ?>">
                                Xóa
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="cps-modal-overlay" id="addressModal">
    <div class="cps-modal">
        <div class="cps-modal-header">
            <h3 id="modalTitle">Thêm địa chỉ mới</h3>
            <button class="cps-modal-close" id="closeAddressModal">&times;</button>
        </div>
        <form method="POST" action="index.php?page=account&section=addresses">
            <div class="cps-modal-body" style="text-align: left;">
                <input type="hidden" name="action" id="formAction" value="add_address">
                <input type="hidden" name="address_id" id="addressId" value="">
                
                <div class="form-group">
                    <label for="dia_chi_moi">Địa chỉ chi tiết</label>
                    <input type="text" id="dia_chi_input" name="dia_chi_moi" class="cps-input" placeholder="Số nhà, tên đường..." required>
                </div>
            </div>
            <div class="cps-modal-footer">
                <button type="button" class="btn btn-outline" id="cancelAddressModal">Hủy</button>
                <button type="submit" class="btn btn-primary" id="btnSaveAddress">Lưu địa chỉ</button>
            </div>
        </form>
    </div>
</div>

<div class="cps-modal-overlay" id="deleteModal">
    <div class="cps-modal">
        <div class="cps-modal-header">
            <h3>Xóa địa chỉ</h3>
            <button class="cps-modal-close" id="closeDeleteModal">&times;</button>
        </div>
        <div class="cps-modal-body">
            <p>Bạn có chắc chắn muốn xóa địa chỉ này không?</p>
            <p style="font-size: 13px; color: #666; margin-top: 5px;">Hành động này không thể hoàn tác.</p>
        </div>
        <div class="cps-modal-footer">
            <button class="btn btn-outline" id="cancelDeleteModal">Không</button>
            
            <form method="POST" action="index.php?page=account&section=addresses" style="flex: 1; margin: 0;">
                <input type="hidden" name="action" value="delete_address">
                <input type="hidden" name="address_id" id="deleteAddressId" value="">
                <button type="submit" class="btn btn-primary" style="background-color: #dc3545; border-color: #dc3545; width: 100%;">Xóa</button>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- LOGIC MODAL THÊM/SỬA ---
    const modal = document.getElementById('addressModal');
    const btnAdd = document.getElementById('btnAddAddress');
    const btnsEdit = document.querySelectorAll('.btn-edit');
    const closeBtn = document.getElementById('closeAddressModal');
    const cancelBtn = document.getElementById('cancelAddressModal');
    
    const modalTitle = document.getElementById('modalTitle');
    const formAction = document.getElementById('formAction');
    const addressId = document.getElementById('addressId');
    const addressInput = document.getElementById('dia_chi_input');
    const btnSave = document.getElementById('btnSaveAddress');

    function openModal() { modal.classList.add('show'); }
    function closeModal() { modal.classList.remove('show'); }

    if (btnAdd) {
        btnAdd.addEventListener('click', function(e) {
            e.preventDefault();
            modalTitle.textContent = "Thêm địa chỉ mới";
            formAction.value = "add_address";
            addressId.value = "";
            addressInput.value = "";
            btnSave.textContent = "Lưu địa chỉ";
            openModal();
        });
    }

    btnsEdit.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const id = this.dataset.id;
            const text = this.dataset.text;
            modalTitle.textContent = "Cập nhật địa chỉ";
            formAction.value = "edit_address";
            addressId.value = id;
            addressInput.value = text;
            btnSave.textContent = "Cập nhật";
            openModal();
        });
    });

    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
    if (modal) modal.addEventListener('click', e => { if(e.target === modal) closeModal(); });

    // --- (MỚI) LOGIC MODAL XÓA ---
    const deleteModal = document.getElementById('deleteModal');
    const btnsDelete = document.querySelectorAll('.btn-delete');
    const closeDelete = document.getElementById('closeDeleteModal');
    const cancelDelete = document.getElementById('cancelDeleteModal');
    const deleteInput = document.getElementById('deleteAddressId');

    function openDeleteModal() { deleteModal.classList.add('show'); }
    function closeDeleteModalFunc() { deleteModal.classList.remove('show'); }

    btnsDelete.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            // Lấy ID từ nút xóa và đưa vào form ẩn trong modal
            const id = this.dataset.id;
            deleteInput.value = id;
            openDeleteModal();
        });
    });

    if (closeDelete) closeDelete.addEventListener('click', closeDeleteModalFunc);
    if (cancelDelete) cancelDelete.addEventListener('click', closeDeleteModalFunc);
    if (deleteModal) deleteModal.addEventListener('click', e => { if(e.target === deleteModal) closeDeleteModalFunc(); });
});
</script>