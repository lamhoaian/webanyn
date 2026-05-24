<?php
require_once '../config/database.php';
require_once '../includes/header.php';

if (!isset($_GET['id'])) {
    echo "<h3 style='color: #ef4444; text-align: center; margin-top: 50px;'>Collection not found!</h3>";
    require_once '../includes/footer.php';
    exit;
}

$group_id = $_GET['id'];

// Lấy tên nhóm
$group_stmt = $pdo->prepare("SELECT name FROM bot_groups WHERE id = ?");
$group_stmt->execute([$group_id]);
$group = $group_stmt->fetch();

if (!$group) {
    echo "<h3 style='color: #ef4444; text-align: center; margin-top: 50px;'>Collection does not exist!</h3>";
    require_once '../includes/footer.php';
    exit;
}

// Lấy danh sách bot thuộc nhóm này kèm tags
$sql = "
    SELECT b.*, GROUP_CONCAT(t.name SEPARATOR ',') as theme_names 
    FROM bots b 
    JOIN bot_group_members bgm ON b.id = bgm.bot_id
    LEFT JOIN bot_themes bt ON b.id = bt.bot_id 
    LEFT JOIN themes t ON bt.theme_id = t.id 
    WHERE bgm.group_id = ?
    GROUP BY b.id 
    ORDER BY b.is_pinned DESC, b.created_at DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$group_id]);
$bots = $stmt->fetchAll();
?>

<!-- STYLE CHO TRANG GROUP DETAIL -->
<style>
    /* Bê nguyên cái Banner Sakura nét đứt sang */
    .store-banner {
        background: linear-gradient(to right, #fff5f7, #fce4e8);
        padding: 40px; 
        border-radius: 15px; 
        text-align: center;
        border: 2px dashed var(--pink); 
        margin-bottom: 40px;
    }
    .store-name { 
        font-size: 28px; 
        color: var(--pink-dark); 
        margin: 0 0 10px 0; 
        font-family: 'Playfair Display', serif;
    }
    .store-sub { 
        color: #666; 
        font-size: 14px; 
        margin: 0; 
    }
    .section-title { 
        font-size: 20px; 
        border-left: 4px solid var(--pink); 
        padding-left: 10px; 
        margin: 20px 0 30px 0; 
        color: var(--text);
    }

    /* CHỈNH LẠI LƯỚI GRID CHO CÁC BOT VUÔNG VẮN NHƯ MANGA */
    .bot-grid { 
        display: grid; 
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); 
        gap: 20px; 
    }
    .bot-card-mini { 
        background: var(--surface); 
        border-radius: 12px; 
        overflow: hidden; 
        border: 1px solid #eaeaea; 
        transition: 0.3s; 
        position: relative;
    }
    .bot-card-mini:hover { 
        transform: translateY(-5px); 
        box-shadow: 0 10px 20px rgba(246, 165, 178, 0.2); 
        border-color: var(--pink); 
    }
    .bot-card-mini img { 
        width: 100%; 
        height: 280px; 
        object-fit: cover; 
    }
    .bot-card-info { 
        padding: 15px; 
    }
    .bot-card-title { 
        font-size: 16px; 
        font-weight: bold; 
        margin: 0 0 5px 0; 
        color: var(--text);
        white-space: nowrap; 
        overflow: hidden; 
        text-overflow: ellipsis;
    }
    .bot-card-rate { 
        color: #f39c12; 
        font-size: 13px; 
        font-weight: 600; 
        margin-bottom: 10px;
    }
    .bot-card-tags {
        display: flex;
        flex-wrap: wrap;
        gap: 4px;
        margin-bottom: 15px;
        height: 22px; /* Giữ chiều cao cố định để không bị lệch form */
        overflow: hidden;
    }
    .tag-mini {
        background: var(--pink-soft);
        color: var(--pink-dark);
        padding: 2px 8px;
        border-radius: 10px;
        font-size: 10px;
        font-weight: bold;
        border: 1px solid var(--pink);
    }
    .btn-chat-mini { 
        display: block; 
        text-align: center; 
        background: var(--pink); 
        color: white; 
        padding: 10px; 
        border-radius: 8px; 
        text-decoration: none; 
        font-size: 13px; 
        font-weight: bold;
        transition: 0.2s;
    }
    .btn-chat-mini:hover {
        background: var(--pink-dark);
    }
</style>

<!-- BANNER HỒNG MỘNG MƠ -->
<div class="store-banner">
    <h1 class="store-name">📁 Collection: <?= htmlspecialchars($group['name']) ?></h1>
    <p class="store-sub">Explore all related characters in this universe. Brought to you by Anyn.</p>
</div>

<h2 class="section-title">📚 All Characters (<?= count($bots) ?>)</h2>

<?php if (count($bots) > 0): ?>
    <div class="bot-grid">
        <?php foreach ($bots as $bot): ?>
            <div class="bot-card-mini">
                
                <!-- Huy hiệu ghim -->
                <?php if($bot['is_pinned']): ?>
                    <div style="position: absolute; top: 10px; right: 10px; background: #ef4444; color: white; border-radius: 50%; width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; font-size: 12px; box-shadow: 0 2px 5px rgba(0,0,0,0.3); z-index: 10;">📌</div>
                <?php endif; ?>

                <!-- Ảnh Bot (Click vào thì xem chi tiết) -->
                <a href="bot_detail.php?id=<?= $bot['id'] ?>" style="display: block;">
                    <img src="<?= htmlspecialchars($bot['image_url']) ?>" alt="<?= htmlspecialchars($bot['name']) ?>">
                </a>
                
                <!-- Thông tin Bot -->
                <div class="bot-card-info">
                    <h3 class="bot-card-title"><?= htmlspecialchars($bot['name']) ?></h3>
                    <div class="bot-card-rate">★ <?= number_format($bot['total_rating'], 1) ?> / 5.0</div>
                    
                    <div class="bot-card-tags">
                        <?php 
                        $tags = !empty($bot['theme_names']) ? explode(',', $bot['theme_names']) : [];
                        // Chỉ lấy tối đa 2 tag đầu tiên để thẻ gọn gàng
                        $display_tags = array_slice($tags, 0, 2);
                        foreach($display_tags as $tag): 
                        ?>
                            <span class="tag-mini"><?= htmlspecialchars(trim($tag)) ?></span>
                        <?php endforeach; ?>
                        <?php if(count($tags) > 2): ?>
                            <span class="tag-mini">...</span>
                        <?php endif; ?>
                    </div>
                    
                    <a href="<?= htmlspecialchars($bot['rp_platform_url']) ?>" target="_blank" class="btn-chat-mini">START ROLEPLAY</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div style="text-align:center; padding: 50px; background: var(--surface); border-radius: 12px; border: 1px dashed #ccc;">
        <p style="color: var(--text-3); margin: 0;">No characters have been added to this collection yet.</p>
    </div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>