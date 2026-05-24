<?php
require_once '../config/database.php';
require_once '../includes/header.php';

// Yêu cầu đăng nhập
if (!isset($_SESSION['user_id'])) {
    echo "<div style='text-align:center; padding: 50px; background: var(--surface); border-radius: 15px; box-shadow: var(--shadow-md); margin-top: 30px;'>";
    echo "<h3 style='color: var(--text-2); margin-bottom: 15px;'>Access Denied</h3>";
    echo "<p style='color: var(--text-3);'>You need to <a href='login.php' style='color: var(--pink); font-weight: bold;'>log in</a> to view your commissions!</p>";
    echo "</div>";
    require_once '../includes/footer.php';
    exit;
}

$user_id = $_SESSION['user_id'];

// Lấy danh sách commission của user hiện tại
$stmt = $pdo->prepare("SELECT * FROM commissions WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$commissions = $stmt->fetchAll();
?>

<style>
    .history-container { max-width: 800px; margin: 0 auto; background: var(--surface); padding: 40px; border-radius: 20px; box-shadow: var(--shadow-md); }
    .history-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 2px solid var(--pink-soft); }
    .history-title { font-size: 28px; color: var(--pink-dark); margin: 0; }
    
    .btn-back { background: var(--pink-soft); color: var(--pink-dark); padding: 10px 20px; border-radius: 12px; text-decoration: none; font-weight: bold; font-size: 14px; border: 1px solid var(--pink); transition: 0.3s; }
    .btn-back:hover { background: var(--pink); color: white; }

    .com-card { background: #fdfdfd; border: 1px solid var(--border); border-radius: 15px; padding: 20px; margin-bottom: 20px; transition: 0.3s; display: flex; flex-direction: column; gap: 15px; }
    .com-card:hover { border-color: var(--pink); box-shadow: 0 5px 15px rgba(255, 183, 197, 0.2); transform: translateY(-3px); }
    
    .com-card-top { display: flex; justify-content: space-between; align-items: flex-start; }
    .com-title-text { font-size: 18px; font-weight: bold; color: var(--text); margin: 0 0 5px 0; }
    .com-date { font-size: 12px; color: #999; }
    .com-price { font-size: 16px; font-weight: 800; color: #10b981; }

    .com-details { background: #f9f9f9; padding: 15px; border-radius: 10px; font-size: 13px; color: var(--text-2); line-height: 1.6; }
    .com-details strong { color: var(--text); }

    .com-card-bottom { display: flex; justify-content: space-between; align-items: center; margin-top: 5px; }
    
    /* Status Badges */
    .status-badge { padding: 6px 15px; border-radius: 20px; font-size: 12px; font-weight: bold; display: inline-flex; align-items: center; gap: 5px; }
    .status-pending { background: #fffbeb; color: #f59e0b; border: 1px solid #fcd34d; }
    .status-progress { background: #eff6ff; color: #3b82f6; border: 1px solid #bfdbfe; }
    .status-completed { background: #f0fdf4; color: #10b981; border: 1px solid #a7f3d0; }

    /* Privacy Badges */
    .privacy-badge { font-size: 12px; font-weight: 600; padding: 5px 10px; border-radius: 8px; }
    .badge-private { background: #fef2f2; color: #ef4444; }
    .badge-public { background: #f0fdf4; color: #10b981; }
</style>

<div class="history-container">
    <div class="history-header">
        <div>
            <h2 class="history-title">My Commissions 📜</h2>
            <p style="color: var(--text-3); margin: 5px 0 0 0; font-size: 14px;">Track the status of your requested characters here.</p>
        </div>
        <a href="commission.php" class="btn-back">+ New Request</a>
    </div>

    <?php if (count($commissions) > 0): ?>
        <?php foreach ($commissions as $com): ?>
            <div class="com-card">
                <div class="com-card-top">
                    <div>
                        <h3 class="com-title-text"><?= htmlspecialchars($com['title']) ?></h3>
                        <div class="com-date">Order ID: #<?= $com['id'] ?> • <?= date('M d, Y', strtotime($com['created_at'])) ?></div>
                    </div>
                    <div class="com-price">$<?= number_format($com['amount_paid'], 2) ?> USD</div>
                </div>

                <div class="com-details">
                    <p style="margin: 0 0 8px 0;"><strong>Appearance:</strong> <?= nl2br(htmlspecialchars($com['appearance'])) ?></p>
                    <p style="margin: 0;"><strong>Context:</strong> <?= nl2br(htmlspecialchars($com['context'])) ?></p>
					<?php if (!empty($com['admin_note'])): ?>
				<div style="background: #e0f2fe; border-left: 4px solid #0ea5e9; padding: 12px 15px; border-radius: 4px; margin-top: 15px;">
				<div style="font-size: 12px; font-weight: bold; color: #0284c7; margin-bottom: 5px;"><i class="fa-solid fa-message"></i> Message from Anyn:</div>
				<div style="font-size: 13px; color: #0369a1; line-height: 1.5; word-break: break-word;">
					<?php 
						// Hàm này tự động quét nếu thấy link (http) thì bọc thẻ <a> vào để khách bấm được luôn
						$note = htmlspecialchars($com['admin_note']);
						$note = preg_replace('/(https?:\/\/[^\s]+)/', '<a href="$1" target="_blank" style="color: #0284c7; text-decoration: underline; font-weight: bold;">$1</a>', $note);
						echo nl2br($note);
					?>
				</div>
				</div>
<?php endif; ?>	
                </div>

                <div class="com-card-bottom">
                    <div>
                        <!-- Hiển thị badge quyền riêng tư -->
                        <?php if ($com['is_private']): ?>
                            <span class="privacy-badge badge-private">🔒 Private Bot</span>
                        <?php else: ?>
                            <span class="privacy-badge badge-public">🌍 Public Bot</span>
                        <?php endif; ?>
                    </div>
                    
                    <div>
                        <!-- Quy đổi trạng thái từ Database ra Giao diện -->
                        <?php if ($com['status'] == 'Pending'): ?>
                            <span class="status-badge status-pending"><i class="fa-regular fa-clock"></i> Pending Approval</span>
                        <?php elseif ($com['status'] == 'In Progress'): ?>
                            <span class="status-badge status-progress"><i class="fa-solid fa-gear fa-spin"></i> In Progress</span>
                        <?php elseif ($com['status'] == 'Completed'): ?>
                            <span class="status-badge status-completed"><i class="fa-solid fa-check-double"></i> Completed</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div style="text-align:center; padding: 50px; background: #f9f9f9; border-radius: 15px; border: 1px dashed #ccc;">
            <i class="fa-solid fa-file-invoice-dollar" style="font-size: 50px; color: #ddd; margin-bottom: 15px;"></i>
            <p style="color: var(--text-3); margin: 0;">You haven't requested any custom commissions yet.</p>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>