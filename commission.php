<?php
require_once '../config/database.php';
require_once '../includes/gems_lib.php';
ensureGemsSchema($pdo);

if (!isset($_SESSION['user_id'])) {
    require_once '../includes/header.php';
    echo "<div style='text-align:center; padding: 50px; background: var(--surface); border-radius: 15px; box-shadow: var(--shadow-md); margin-top: 30px;'>";
    echo "<h3 style='color: var(--text-2); margin-bottom: 15px;'>Access Denied</h3>";
    echo "<p style='color: var(--text-3);'>You need to <a href='login.php' style='color: var(--pink); font-weight: bold;'>log in</a> to request a Commission!</p>";
    echo "</div>";
    require_once '../includes/footer.php';
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$paypal_email = 'prismvow.art@gmail.com';
$coupon_publish = countUnusedInventory($pdo, $user_id, 'commission_publish');
$coupon_unlisted = countUnusedInventory($pdo, $user_id, 'commission_unlisted');

$step = 1;
$commission_id = 0;
$amount = 0;
$title = '';
$error_msg = '';
$used_voucher = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['submit_commission'])) {
        $title = $_POST['title'];
        $appearance = $_POST['appearance'];
        $context = $_POST['context'];
        $voucher = $_POST['use_voucher'] ?? '';
        $user_id = (int)$_SESSION['user_id'];

        if ($voucher === 'publish') {
            if (!consumeInventoryCoupon($pdo, $user_id, 'commission_publish')) {
                $error_msg = 'No Published commission voucher available. Buy one in the Gem Shop.';
            } else {
                $is_private = 0;
                $amount = 5;
                $used_voucher = true;
            }
        } elseif ($voucher === 'unlisted') {
            if (!consumeInventoryCoupon($pdo, $user_id, 'commission_unlisted')) {
                $error_msg = 'No Unlisted commission voucher available. Buy one in the Gem Shop.';
            } else {
                $is_private = 1;
                $amount = 5;
                $used_voucher = true;
            }
        } else {
            $amount = floatval($_POST['amount']);
            $is_private = isset($_POST['allow_public']) ? 0 : 1;
            if ($amount < 5) {
                $is_private = 1;
            }
        }

        // XỬ LÝ UPLOAD ẢNH MINH HỌA
        $final_image_url = '';
        if (isset($_FILES['com_image_file']) && $_FILES['com_image_file']['error'] == 0) {
            $target_dir = "../uploads/commissions/";
            if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);

            $ext = strtolower(pathinfo($_FILES["com_image_file"]["name"], PATHINFO_EXTENSION));
            $new_name = time() . "_com_" . uniqid() . "." . $ext;
            $target_file = $target_dir . $new_name;

            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                if (move_uploaded_file($_FILES["com_image_file"]["tmp_name"], $target_file)) {
                    $final_image_url = "/anyn/uploads/commissions/" . $new_name;
                } else {
                    $error_msg = "Failed to upload image.";
                }
            } else {
                $error_msg = "Invalid image format. Allowed: JPG, PNG, GIF, WEBP.";
            }
        } elseif (!empty($_POST['image_url'])) {
            $final_image_url = trim($_POST['image_url']);
        }

        if ($error_msg == '') {
            $admin_note = $used_voucher ? ('Gem voucher: ' . ($is_private ? 'Unlisted' : 'Published')) : '';
            $stmt = $pdo->prepare("INSERT INTO commissions (user_id, title, appearance, context, image_url, amount_paid, is_private, status, admin_note) VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending', ?)");
            $stmt->execute([$user_id, $title, $appearance, $context, $final_image_url, $amount, $is_private, $admin_note]);
            $commission_id = $pdo->lastInsertId();
            tryAwardMission($pdo, $user_id, 'submit_commission');
            $step = $used_voucher ? 3 : 2;
        }
    }
    // XỬ LÝ BƯỚC 2: USER BẤM XÁC NHẬN ĐÃ CHUYỂN KHOẢN
    elseif (isset($_POST['confirm_payment'])) {
        $step = 3;
    }
}

require_once '../includes/header.php';
?>

<style>
    .com-container { max-width: 600px; margin: 0 auto; background: var(--surface); padding: 40px; border-radius: 20px; box-shadow: var(--shadow-md); }
    .com-header { text-align: center; margin-bottom: 30px; }
    .com-title { font-size: 28px; color: var(--pink-dark); margin: 0 0 10px 0; }
    .com-sub { color: var(--text-3); font-size: 14px; line-height: 1.6; }
    
    .form-group { margin-bottom: 20px; }
    .form-group label { display: block; font-weight: 600; font-size: 14px; margin-bottom: 8px; color: var(--text-2); }
    .form-group input[type="text"], .form-group input[type="number"], .form-group textarea { width: 100%; padding: 12px 15px; border: 1.5px solid var(--border); border-radius: 12px; font-family: inherit; font-size: 14px; outline: none; transition: 0.3s; box-sizing: border-box; background: #fcfcfc; }
    .form-group input:focus, .form-group textarea:focus { border-color: var(--pink); box-shadow: 0 0 0 3px rgba(255, 183, 197, 0.2); }
    
    .upload-section { background: var(--pink-soft); padding: 20px; border-radius: 12px; border: 1px dashed var(--pink); margin-bottom: 25px; }
    .upload-title { color: var(--pink-dark); font-weight: bold; margin-bottom: 15px; font-size: 14px; display: flex; align-items: center; gap: 8px; }

    .public-box { display: none; background: #f0fdf4; padding: 15px; border-radius: 12px; border: 1px dashed #10b981; margin-bottom: 20px; animation: fadeIn 0.3s; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }
    
    .btn-submit { width: 100%; background: var(--pink); color: white; border: none; padding: 15px; border-radius: 12px; font-size: 16px; font-weight: bold; cursor: pointer; transition: 0.3s; box-shadow: 0 5px 15px rgba(255, 183, 197, 0.4); }
    .btn-submit:hover { background: var(--pink-dark); transform: translateY(-2px); }
    
    .btn-paypal { width: 100%; background: #ffc439; color: #003087; border: none; padding: 15px; border-radius: 12px; font-size: 18px; font-weight: 800; cursor: pointer; transition: 0.3s; display: flex; align-items: center; justify-content: center; gap: 10px; box-shadow: 0 5px 15px rgba(255, 196, 57, 0.4); }
    .btn-paypal:hover { background: #f4b625; transform: translateY(-2px); }

    .payment-info-box { display: none; margin-top: 25px; text-align: left; background: #fff9fa; border: 2px dashed var(--pink); padding: 25px; border-radius: 15px; animation: fadeIn 0.4s; }
    
    .alert-error { background: #fef2f2; color: #ef4444; border: 1px solid #fca5a5; padding: 15px; border-radius: 10px; margin-bottom: 20px; font-size: 14px; font-weight: 500; text-align: center; }
</style>

<div class="com-container">

    <!-- BƯỚC 1: ĐIỀN FORM -->
    <?php if ($step == 1): ?>
        
        <!-- Nút Order History đã được đẩy lên trên cùng, canh phải -->
        <div style="display: flex; justify-content: flex-end; margin-bottom: 10px;">
            <a href="my_commissions.php" style="background: var(--pink-soft); color: var(--pink-dark); padding: 8px 15px; border-radius: 10px; text-decoration: none; font-size: 13px; font-weight: bold; border: 1px solid var(--pink); transition: 0.2s;">
                <i class="fa-solid fa-clock-rotate-left"></i> Order History
            </a>
        </div>

        <div class="com-header">
            <h2 class="com-title">Custom Commissions 🎨</h2>
            <p class="com-sub">Describe the character you want. All orders under $5 are strictly private. Orders of <strong>$5 or more</strong> can optionally be published to the main store for everyone to enjoy.</p>
        </div>

        <?php if($error_msg): ?> <div class="alert-error"><?= $error_msg ?></div> <?php endif; ?>

        <form method="POST" action="" enctype="multipart/form-data">
            <div class="form-group">
                <label>Request Title</label>
                <input type="text" name="title" required placeholder="e.g., A fantasy knight...">
            </div>
            
            <div class="form-group">
                <label>Appearance</label>
                <textarea name="appearance" rows="3" required placeholder="Describe how the character looks..."></textarea>
            </div>
            
            <div class="form-group">
                <label>Context / Scenario</label>
                <textarea name="context" rows="4" required placeholder="Describe the setting and personality..."></textarea>
            </div>

            <!-- PHẦN UPLOAD ẢNH (Optional) -->
            <div class="upload-section">
                <div class="upload-title"><i class="fa-regular fa-image"></i> Reference Image (Optional)</div>
                <div style="margin-bottom: 15px;">
                    <div style="font-size: 13px; color: #666; margin-bottom: 5px;">Method 1: Upload from device</div>
                    <input type="file" name="com_image_file" accept="image/*" style="width: 100%; font-size: 13px; color: var(--text-2);">
                </div>
                <div style="text-align: center; color: #aaa; font-size: 12px; margin-bottom: 15px;">— OR —</div>
                <div>
                    <div style="font-size: 13px; color: #666; margin-bottom: 5px;">Method 2: Paste an Image URL</div>
                    <input type="text" name="image_url" placeholder="https://..." style="width: 100%; padding: 10px; border: 1.5px solid var(--border); border-radius: 8px; font-size: 13px; outline: none; box-sizing: border-box;">
                </div>
            </div>
            
            <?php if ($coupon_publish > 0 || $coupon_unlisted > 0): ?>
            <div class="upload-section" style="margin-bottom:20px;">
                <div class="upload-title"><i class="fa-solid fa-gem"></i> Use Gem Shop voucher</div>
                <label style="display:flex;align-items:center;gap:8px;margin-bottom:8px;font-size:13px;cursor:pointer;">
                    <input type="radio" name="use_voucher" value="" checked onchange="toggleVoucherPay()"> Pay with PayPal (normal)
                </label>
                <?php if ($coupon_publish > 0): ?>
                <label style="display:flex;align-items:center;gap:8px;margin-bottom:8px;font-size:13px;cursor:pointer;">
                    <input type="radio" name="use_voucher" value="publish" onchange="toggleVoucherPay()"> Published voucher (<?= $coupon_publish ?> left)
                </label>
                <?php endif; ?>
                <?php if ($coupon_unlisted > 0): ?>
                <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;">
                    <input type="radio" name="use_voucher" value="unlisted" onchange="toggleVoucherPay()"> Unlisted voucher (<?= $coupon_unlisted ?> left)
                </label>
                <?php endif; ?>
                <p style="font-size:12px;color:var(--text-3);margin:12px 0 0;">Buy vouchers in the <a href="shop.php" style="color:var(--pink-dark);font-weight:700;">Gem Shop</a>.</p>
            </div>
            <?php endif; ?>

            <div id="paypal_pay_block">
            <div class="form-group">
                <label>Amount to Pay (USD)</label>
                <input type="number" name="amount" id="amount_input" min="1" step="1" required placeholder="e.g., 5" oninput="checkPublicLimit()">
                <div style="font-size: 12px; color: var(--text-3); margin-top: 8px;">* By default, your bot is Private (only for you).</div>
            </div>
            
            <div id="public_box" class="public-box">
                <label style="cursor: pointer; display: flex; align-items: center; gap: 10px; font-weight: 600; color: #10b981; margin: 0;">
                    <input type="checkbox" name="allow_public" value="1" style="width: 18px; height: 18px; accent-color: #10b981;"> 
                    Allow Admin to publish this bot to the Main Store
                </label>
                <div style="font-size: 12px; color: var(--text-2); margin-left: 28px; margin-top: 5px;">Thank you for supporting the community!</div>
            </div>
            </div>
            
            <button type="submit" name="submit_commission" class="btn-submit" id="com_submit_btn">Proceed to Payment</button>
        </form>

        <script>
        function toggleVoucherPay() {
            const v = document.querySelector('input[name="use_voucher"]:checked')?.value;
            const block = document.getElementById('paypal_pay_block');
            const btn = document.getElementById('com_submit_btn');
            const amt = document.getElementById('amount_input');
            if (v === 'publish' || v === 'unlisted') {
                block.style.display = 'none';
                if (amt) amt.removeAttribute('required');
                btn.textContent = 'Submit with voucher';
            } else {
                block.style.display = 'block';
                if (amt) amt.setAttribute('required', 'required');
                btn.textContent = 'Proceed to Payment';
            }
        }
        function checkPublicLimit() {
            var amount = document.getElementById('amount_input').value;
            var publicBox = document.getElementById('public_box');
            if (amount >= 5) {
                publicBox.style.display = 'block';
            } else {
                publicBox.style.display = 'none';
                publicBox.querySelector('input').checked = false; 
            }
        }
        </script>

    <!-- BƯỚC 2: XÁC NHẬN VÀ HIỂN THỊ THÔNG TIN CHUYỂN KHOẢN -->
    <?php elseif ($step == 2): ?>
        <div class="com-header" style="margin-bottom: 20px;">
            <i class="fa-solid fa-circle-check" style="font-size: 60px; color: #10b981; margin-bottom: 15px;"></i>
            <h2 class="com-title" style="color: var(--text);">Request Saved!</h2>
        </div>
        
        <div style="background: #f9f9f9; padding: 20px; border-radius: 12px; text-align: left; border: 1px solid var(--border); margin-bottom: 25px;">
            <p style="margin: 0 0 10px 0; color: var(--text-2);">Title: <strong style="color: var(--text);"><?= htmlspecialchars($title) ?></strong></p>
            <p style="margin: 0; color: var(--text-2);">Total Amount: <strong style="color: #10b981; font-size: 18px;">$<?= number_format($amount, 2) ?> USD</strong></p>
        </div>
        
        <button type="button" class="btn-paypal" onclick="document.getElementById('payment_info_box').style.display='block'; this.style.display='none';">
            <i class="fa-brands fa-paypal"></i> Pay with PayPal
        </button>

        <div id="payment_info_box" class="payment-info-box">
            <h3 style="margin-top: 0; color: var(--pink-dark); font-size: 18px;">Payment Instructions</h3>
            
            <p style="color: var(--text-2); font-size: 14px;">1. Send exactly <strong style="color: #10b981;">$<?= number_format($amount, 2) ?> USD</strong> to this PayPal email:</p>
            <div style="background: white; padding: 12px; border-radius: 8px; border: 1.5px solid var(--border); text-align: center; font-size: 18px; font-weight: bold; color: var(--text); margin-bottom: 20px;">
                <?= $paypal_email ?>
            </div>

            <p style="color: var(--text-2); font-size: 14px;">2. <strong>Crucial:</strong> Add this exact code to your transfer note so Anyn can verify your order:</p>
            <div style="background: white; padding: 12px; border-radius: 8px; border: 1.5px solid var(--border); text-align: center; font-size: 18px; font-weight: bold; color: #ef4444; margin-bottom: 25px; letter-spacing: 1px;">
                ORDER-<?= $commission_id ?>
            </div>

            <form method="POST" action="">
                <input type="hidden" name="confirm_payment" value="1">
                <button type="submit" class="btn-submit" style="background: #10b981; box-shadow: 0 5px 15px rgba(16, 185, 129, 0.3);">
                    I have transferred the money ✅
                </button>
            </form>
        </div>

    <!-- BƯỚC 3: THÔNG BÁO HOÀN TẤT & ĐỢI DISCORD -->
    <?php elseif ($step == 3): ?>
        <div style="text-align: center; padding: 20px 0;">
            <i class="fa-solid fa-paper-plane" style="font-size: 60px; color: var(--pink); margin-bottom: 20px;"></i>
            <h2 class="com-title" style="color: var(--text); font-size: 26px;">Request Sent Successfully!</h2>
            
            <p style="color: var(--text-2); line-height: 1.8; font-size: 15px; margin-bottom: 30px; padding: 0 10px;">
                Please wait for Anyn to verify your payment. <br><br>
                You can also join our <strong>Discord community</strong> to chat with Anyn about your bot setup and details.
            </p>

            <a href="<?= ANYN_DISCORD_URL ?>" class="btn-submit" target="_blank" rel="noopener noreferrer" style="display: inline-flex; align-items: center; gap: 8px; width: auto; padding: 12px 30px; text-decoration: none; margin-bottom: 12px; background: #5865F2; box-shadow: 0 5px 15px rgba(88, 101, 242, 0.35);">
                <i class="fa-brands fa-discord"></i> Join Discord
            </a>
            <br>
            <a href="../index.php" class="btn-submit" style="display: inline-block; width: auto; padding: 12px 30px; text-decoration: none; background: var(--pink);">Return to Home</a>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>