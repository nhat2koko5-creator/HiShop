<?php
require_once 'client/layouts/header.php';

$cart = $_SESSION['cart'] ?? [];

function price_format($n) {
    return number_format($n, 0, ',', '.') . "₫";
}

$img_folder = "assets/img/products";
$default_img = "assets/img/no-image.png";
?>

<div class="cart-container">

    <h1>🛒 Giỏ hàng</h1>

    <?php if (empty($cart)): ?>
<div class="empty-cart-box">
    
    <!-- ICON giống header -->
    <i class="fa-solid fa-cart-shopping empty-icon"></i>

    <h2>Giỏ hàng của bạn đang trống</h2>
    <p>Hãy thêm sản phẩm để tiếp tục mua sắm.</p>

    <a href="index.php?page=product_list" class="btn-primary empty-btn">
        Tiếp tục mua hàng
    </a>

</div>

    <?php else: ?>

    <div class="cart-wrapper">

        <!-- LEFT: ITEMS -->
        <div class="cart-list">
            <?php foreach ($cart as $key => $item): ?>
                <?php
                $img = (!empty($item['image']) && file_exists($img_folder . '/' . $item['image']))
                    ? $img_folder . '/' . $item['image']
                    : $default_img;
                ?>
                <div class="cart-item" id="item-<?= $key ?>">

                    <img src="<?= $img ?>" class="cart-img">

                    <div class="cart-info">
                        <h3><?= htmlspecialchars($item['name']) ?></h3>
                        <div class="vars">
                            <span>Màu: <strong><?= $item['color'] ?></strong></span>
                            <span>SSD: <strong><?= $item['ssd'] ?></strong></span>
                        </div>
                    </div>

                    <div class="qty-box">
                        <button class="qty-btn" data-key="<?= $key ?>" data-change="-1">-</button>
                        <input type="number" class="qty-input" value="<?= $item['quantity'] ?>" min="1" id="qty-<?= $key ?>">
                        <button class="qty-btn" data-key="<?= $key ?>" data-change="1">+</button>
                    </div>

                    <div class="cart-price" id="total-<?= $key ?>">
                        <?= price_format($item['price'] * $item['quantity']) ?>
                    </div>

                    <!-- DELETE BUTTON MOVED RIGHT -->
                    <div class="delete-col">
                        <span class="remove-btn" data-key="<?= $key ?>">❌</span>
                    </div>

                </div>
            <?php endforeach; ?>
        </div>

        <!-- RIGHT: SUMMARY -->
        <?php 
            $subtotal = 0;
            foreach ($cart as $item) $subtotal += $item['price'] * $item['quantity'];
        ?>

        <div class="summary-box">

            <div class="sum-row">
                <span>Tạm tính</span>
                <span id="subtotal"><?= price_format($subtotal) ?></span>
            </div>

            <div class="sum-title">Mã giảm giá</div>

            <div class="voucher-box">
                <input type="text" id="voucherInput" placeholder="Nhập mã giảm giá...">
                <button id="applyVoucherBtn">Áp dụng</button>
            </div>

            <div class="sum-row" id="discountRow" style="display:none;">
                <span>Giảm giá</span>
                <span id="discountAmount">0₫</span>
            </div>

            <div class="sum-row total">
                <span>Tổng cộng</span>
                <span id="total"><?= price_format($subtotal) ?></span>
            </div>

            <button class="checkout-btn" onclick="window.location.href='index.php?page=checkout'">
                Thanh toán ngay
            </button>

        </div>

    </div>

    <?php endif; ?>

</div>


<!-- ====================== JS ======================= -->
<script>
// REMOVE PRODUCT
document.querySelectorAll(".remove-btn").forEach(btn => {
    btn.onclick = function () {
        let key = this.dataset.key;

        fetch("cart-handler.php", {
            method: "POST",
            headers: {"Content-Type":"application/x-www-form-urlencoded"},
            body: `action=delete&key=${key}`
        })
        .then(r=>r.json())
        .then(data=>{
            if (data.status === "success") {
                document.getElementById("item-" + key).remove();
                updateSummary();
                if (data.cart_empty) location.reload();
            }
        });
    };
});

// UPDATE QUANTITY
function updateQty(key, qty) {
    fetch("cart-handler.php", {
        method: "POST",
        headers: { "Content-Type":"application/x-www-form-urlencoded" },
        body: `action=update&key=${key}&quantity=${qty}`
    })
    .then(r=>r.json())
    .then(data=>{
        if (data.status === "success") {
            document.getElementById("total-"+key).innerText = data.item_total;
            updateSummary();
        }
    });
}

// PLUS / MINUS
document.querySelectorAll(".qty-btn").forEach(btn=>{
    btn.onclick = function(){
        let key = this.dataset.key;
        let change = parseInt(this.dataset.change);
        let input = document.getElementById("qty-"+key);
        let newQty = parseInt(input.value) + change;
        if (newQty < 1) newQty = 1;
        input.value = newQty;
        updateQty(key, newQty);
    };
});

// DIRECT INPUT
document.querySelectorAll(".qty-input").forEach(input=>{
    input.onchange = function(){
        let key = this.id.replace("qty-","");
        let val = parseInt(this.value);
        if (val < 1) val = 1;
        this.value = val;
        updateQty(key, val);
    };
});

// UPDATE TOTAL
function updateSummary() {
    let subtotal = 0;

    document.querySelectorAll(".cart-price").forEach(p=>{
        subtotal += parseInt(p.innerText.replace(/[₫.]/g,"")) || 0;
    });

    document.getElementById("subtotal").innerText = subtotal.toLocaleString("vi-VN") + "₫";

    let discountText = document.getElementById("discountAmount").innerText.replace(/[₫.]/g,"");
    let discount = parseInt(discountText) || 0;

    let total = subtotal - discount;
    if (total < 0) total = 0;

    document.getElementById("total").innerText = total.toLocaleString("vi-VN") + "₫";
}


// APPLY VOUCHER
document.getElementById("applyVoucherBtn").onclick = function () {
    let code = document.getElementById("voucherInput").value.trim();
    if (!code) return alert("Bạn chưa nhập mã!");

    fetch("cart-handler.php", {
        method: "POST",
        headers: {"Content-Type":"application/x-www-form-urlencoded"},
        body: `action=apply_coupon&code=${encodeURIComponent(code)}`
    })
    .then(r=>r.json())
    .then(data=>{
        if (data.status === "success") {

            let subtotal = parseInt(document.getElementById("subtotal").innerText.replace(/[₫.]/g,""));
            let discount = 0;

            if (data.coupon.type === "percent") {
                discount = Math.floor(subtotal * (data.coupon.value / 100));
            } else {
                discount = data.coupon.value;
            }

            document.getElementById("discountAmount").innerText = discount.toLocaleString("vi-VN") + "₫";
            document.getElementById("discountRow").style.display = "flex";

            document.getElementById("total").innerText =
                (subtotal - discount).toLocaleString("vi-VN") + "₫";

            alert("Áp dụng mã thành công!");
        } else {
            alert(data.message);
        }
    });
};
</script>


<!-- ====================== CSS ======================= -->
<style>
    .empty-cart-box {
    text-align: center;
    padding: 60px 0;
}

.empty-icon {
    font-size: 80px;      /* nhỏ hơn trước, rất gọn */
    color: #777;          /* cùng tone header */
    margin-bottom: 20px;
    opacity: 0.85;
}

.empty-btn {
    padding: 12px 25px;
    font-size: 17px;
    border-radius: 8px;
    display: inline-block;
}

.cart-container { width: 90%; margin: auto; padding-top: 20px; }
.cart-wrapper { display: flex; gap: 25px; align-items: flex-start; }

.cart-list { flex: 1; }
.cart-item{
    display: grid;
    grid-template-columns: 100px 1fr 130px 130px 40px;
    align-items: center;
    gap: 15px;
    padding: 15px 0;
    border-bottom: 1px solid #eee;
}

.cart-img{
    width: 90px; height: 90px; object-fit: cover; border-radius: 6px;
}

.cart-info h3{ margin: 0 0 5px; font-size: 17px; }

.qty-box{
    display: flex; align-items: center; gap: 5px;
}

.qty-btn{
    width: 32px; height: 32px; font-size: 18px;
    border: 1px solid #ccc; border-radius: 6px;
    background: #fff; cursor: pointer;
}

.qty-input{
    width: 50px; text-align: center;
    border: 1px solid #bbb; border-radius: 6px;
}

.cart-price{
    text-align: right;
    font-weight: bold; font-size: 18px;
}

.delete-col{
    display: flex;
    justify-content: center;
}

.remove-btn{
    cursor: pointer;
    color: #dd2222;
    font-size: 20px;
}

/* SUMMARY */
.summary-box{
    width: 330px;
    background: #f8f8f8;
    padding: 18px;
    border-radius: 12px;
    position: sticky;
    top: 20px;
}

.sum-row{
    display: flex; justify-content: space-between;
    margin-bottom: 12px;
}

.sum-row.total span:last-child{
    color: #0f62fe; font-size: 20px; font-weight: bold;
}

.voucher-box{
    display: flex; gap: 10px;
    margin-bottom: 15px;
}

.voucher-box input{
    flex:1; padding: 8px; border-radius: 6px; border: 1px solid #ccc;
}

.voucher-box button{
    padding: 8px 15px; background: #0f62fe;
    color: white; border: none; border-radius: 6px; cursor: pointer;
}

.checkout-btn{
    width: 100%; padding: 12px;
    background: #0f62fe; color: white;
    border: none; border-radius: 8px;
    margin-top: 15px; cursor: pointer; font-size: 16px;
}

/* EMPTY CART */
.empty-cart-box{
    text-align: center;
    padding: 50px 0;
}

.empty-img{
    width: 200px; opacity: 0.6; margin-bottom: 20px;
}

.empty-btn{
    padding: 12px 22px;
    font-size: 17px;
    display: inline-block;
    margin-top: 15px;
}
/* Hộp số lượng */
.quantity-box {
    display: flex;
    align-items: center;
    gap: 8px;
    border: 1px solid #ddd;
    padding: 6px 10px;
    border-radius: 8px;
    width: fit-content;
    background: #fafafa;
}

/* Nút +/- */
.qty-btn {
    width: 32px;
    height: 32px;
    border-radius: 6px;
    border: 1px solid #ccc;
    background: #fff;
    cursor: pointer;
    font-size: 18px;
    font-weight: 600;
    transition: 0.2s;
}

.qty-btn:hover {
    background: #eaeaea;
}

/* Ô nhập số */
.qty-input {
    width: 50px;
    text-align: center;
    font-size: 16px;
    border: none;
    outline: none;
    background: transparent;
}

</style>

<?php require_once 'client/layouts/footer.php'; ?>
