<?php
require_once '../config/database.php';

if (!isset($_GET['id'])) {
    header("Location: ../index.php"); 
    exit();
}

$bot_id = (int)$_GET['id'];

$stmt = $pdo->prepare("SELECT * FROM bots WHERE id = ?");
$stmt->execute([$bot_id]);
$bot = $stmt->fetch();

if (!$bot) {
    require_once '../includes/header.php';
    echo "<div style='text-align:center;padding:80px 20px;color:var(--text-3);'><i class='fa-regular fa-face-sad-tear' style='font-size:48px;display:block;margin-bottom:16px;'></i><p>Character not found.</p></div>";
    require_once '../includes/footer.php'; 
    exit();
}

// Handle rating POST
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_rating']) && isset($_SESSION['user_id'])) {
    $score   = max(1, min(5, (int)$_POST['score']));
    $user_id = $_SESSION['user_id'];

    $check = $pdo->prepare("SELECT id FROM ratings WHERE user_id = ? AND bot_id = ?");
    $check->execute([$user_id, $bot_id]);
    
    if ($check->rowCount() > 0) {
        $pdo->prepare("UPDATE ratings SET score = ? WHERE user_id = ? AND bot_id = ?")->execute([$score, $user_id, $bot_id]);
    } else {
        $pdo->prepare("INSERT INTO ratings (user_id, bot_id, score) VALUES (?, ?, ?)")->execute([$user_id, $bot_id, $score]);
    }
    
    $avg = $pdo->prepare("SELECT AVG(score) as avg_s FROM ratings WHERE bot_id = ?");
    $avg->execute([$bot_id]);
    $new_avg = round($avg->fetch()['avg_s'], 2);
    $pdo->prepare("UPDATE bots SET total_rating = ? WHERE id = ?")->execute([$new_avg, $bot_id]);
    header("Location: bot_detail.php?id=$bot_id"); 
    exit();
}

// Handle comment POST
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_comment']) && isset($_SESSION['user_id'])) {
    $content = trim($_POST['content']);
    if (!empty($content)) {
        $pdo->prepare("INSERT INTO comments (user_id, bot_id, content) VALUES (?, ?, ?)")->execute([$_SESSION['user_id'], $bot_id, $content]);
        header("Location: bot_detail.php?id=$bot_id"); 
        exit();
    }
}

// Fetch extra data
$stmt_tags = $pdo->prepare("SELECT t.name FROM themes t JOIN bot_themes bt ON t.id = bt.theme_id WHERE bt.bot_id = ?");
$stmt_tags->execute([$bot_id]);
$bot_tags = $stmt_tags->fetchAll(PDO::FETCH_COLUMN);

$count_stmt = $pdo->prepare("SELECT COUNT(id) FROM ratings WHERE bot_id = ?");
$count_stmt->execute([$bot_id]);
$rating_count = $count_stmt->fetchColumn();

// Get user's existing rating
$user_rating_val = null;
if(isset($_SESSION['user_id'])) {
    $ur = $pdo->prepare("SELECT score FROM ratings WHERE user_id = ? AND bot_id = ?");
    $ur->execute([$_SESSION['user_id'], $bot_id]);
    $user_rating_val = $ur->fetchColumn();
}

$stmt_comments = $pdo->prepare("
    SELECT c.*, u.username, u.total_spent, u.avatar_url, r.score as user_rating 
    FROM comments c 
    JOIN users u ON c.user_id = u.id 
    LEFT JOIN ratings r ON c.user_id = r.user_id AND r.bot_id = c.bot_id 
    WHERE c.bot_id = ? 
    ORDER BY c.created_at DESC
");
$stmt_comments->execute([$bot_id]);
$comments = $stmt_comments->fetchAll();

$stmt_col = $pdo->prepare("SELECT g.name, g.id FROM bot_groups g JOIN bot_group_members bgm ON g.id = bgm.group_id WHERE bgm.bot_id = ?");
$stmt_col->execute([$bot_id]);
$collections = $stmt_col->fetchAll();

require_once '../includes/header.php';
?>

<style>
    /* Detail layout */
    .detail-grid {
        display: grid;
        grid-template-columns: 340px 1fr;
        gap: 36px;
        margin-bottom: 40px;
    }

    @media (max-width: 860px) {
        .detail-grid { grid-template-columns: 1fr; }
    }

    .detail-img {
        width: 100%;
        border-radius: var(--radius);
        box-shadow: var(--shadow-md);
        aspect-ratio: 3/4;
        object-fit: cover;
    }

    .detail-info { min-width: 0; }

    .detail-name {
        font-family: 'Playfair Display', serif;
        font-size: 34px;
        font-weight: 700;
        color: var(--text);
        margin-bottom: 16px;
        line-height: 1.2;
    }

    .detail-tags { display: flex; flex-wrap: wrap; gap: 7px; margin-bottom: 18px; }

    .collection-link {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: #e3f2fd;
        color: #1565c0;
        padding: 5px 14px;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 700;
        margin-bottom: 18px;
        transition: background var(--transition);
    }

    [data-theme="dark"] .collection-link {
        background: #1a2a3a;
        color: #64b5f6;
    }

    .detail-access {
        font-size: 22px;
        font-weight: 800;
        color: var(--pink-dark);
        margin-bottom: 12px;
        letter-spacing: -.5px;
    }

    .detail-rating {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 20px;
        font-size: 17px;
        font-weight: 700;
        color: #f4a22d;
    }

    .detail-rating .count { color: var(--text-3); font-size: 13px; font-weight: 500; }

    .desc-box {
        background: var(--surface-2);
        padding: 20px;
        border-radius: var(--radius-sm);
        border-left: 3px solid var(--pink);
        margin-bottom: 24px;
        line-height: 1.75;
        font-size: 14px;
        color: var(--text-2);
        word-break: break-word;
    }

    .btn-start {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        background: linear-gradient(135deg, var(--pink) 0%, var(--pink-dark) 100%);
        color: #fff;
        padding: 15px 36px;
        border-radius: 99px;
        font-size: 16px;
        font-weight: 800;
        border: none;
        cursor: pointer;
        font-family: inherit;
        box-shadow: 0 8px 24px rgba(255,127,158,.35);
        transition: all var(--transition);
        letter-spacing: .3px;
    }

    .btn-start:hover {
        transform: translateY(-3px);
        box-shadow: 0 14px 32px rgba(255,127,158,.45);
    }

    /* Bottom grid */
    .bottom-grid {
        display: grid;
        grid-template-columns: 280px 1fr;
        gap: 28px;
        margin-bottom: 60px;
    }

    @media (max-width: 768px) {
        .bottom-grid { grid-template-columns: 1fr; }
    }

    /* Star Rating */
    .star-picker { display: flex; flex-direction: row-reverse; justify-content: flex-end; gap: 6px; margin-bottom: 18px; }

    .star-picker input { display: none; }

    .star-picker label {
        font-size: 28px;
        color: var(--border);
        cursor: pointer;
        transition: color var(--transition), transform var(--transition);
    }

    .star-picker label:hover,
    .star-picker label:hover ~ label,
    .star-picker input:checked ~ label {
        color: #f4a22d;
    }

    .star-picker label:hover { transform: scale(1.15); }

    /* Platform modal */
    .modal-backdrop {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,.65);
        z-index: 10000;
        align-items: center;
        justify-content: center;
        backdrop-filter: blur(6px);
    }

    .modal-backdrop.open { display: flex; }

    .modal-card {
        background: var(--surface);
        padding: 32px;
        border-radius: 24px;
        text-align: center;
        max-width: 380px;
        width: 90%;
        box-shadow: var(--shadow-lg);
        position: relative;
        animation: slideUp .3s ease;
        border: 1px solid var(--border);
    }

    .modal-close {
        position: absolute;
        top: 14px; right: 18px;
        background: none; border: none;
        font-size: 22px;
        cursor: pointer;
        color: var(--text-3);
        line-height: 1;
        transition: color var(--transition);
    }

    .modal-close:hover { color: var(--text); }

    .platform-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 12px;
        width: 100%;
        padding: 16px;
        border-radius: 14px;
        text-decoration: none;
        font-weight: 700;
        font-size: 15px;
        margin-bottom: 12px;
        transition: all var(--transition);
    }

    .platform-btn:last-child { margin-bottom: 0; }

    .platform-btn.juicy {
        background: #FF4B82;
        color: white;
        box-shadow: 0 4px 16px rgba(255,75,130,.3);
    }

    .platform-btn.crush {
        background: #6C5CE7;
        color: white;
        box-shadow: 0 4px 16px rgba(108,92,231,.3);
    }

    .platform-btn:hover { filter: brightness(1.1); transform: translateY(-2px); }

    /* Comments section */
    .comments-list { display: flex; flex-direction: column; gap: 18px; }

    .login-prompt {
        text-align: center;
        padding: 20px;
        background: var(--surface-2);
        border-radius: var(--radius-sm);
        border: 1.5px dashed var(--border);
        font-size: 14px;
        color: var(--text-2);
    }

    .login-prompt a { color: var(--pink-dark); font-weight: 700; }
</style>

<!-- Detail Section -->
<a href="../index.php" style="display:inline-flex;align-items:center;gap:8px;color:var(--text-3);font-size:13px;font-weight:600;margin-bottom:24px;transition:color var(--transition);" onmouseover="this.style.color='var(--pink-dark)'" onmouseout="this.style.color='var(--text-3)'">
    <i class="fa-solid fa-arrow-left"></i> Back to Characters
</a>

<div class="detail-grid card" style="padding:32px;">
    <div>
        <img src="<?= htmlspecialchars($bot['image_url']) ?>" class="detail-img" alt="<?= htmlspecialchars($bot['name']) ?>">
    </div>

    <div class="detail-info">
        <h1 class="detail-name"><?= htmlspecialchars($bot['name']) ?></h1>

        <?php if(!empty($bot_tags)): ?>
        <div class="detail-tags">
            <?php foreach($bot_tags as $tag_name): ?>
                <span class="tag-pill">🏷️ <?= htmlspecialchars($tag_name) ?></span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if($collections): ?>
        <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:18px;">
            <?php foreach($collections as $col): ?>
                <a href="group_detail.php?id=<?= $col['id'] ?>" class="collection-link">
                    <i class="fa-solid fa-folder-open"></i> <?= htmlspecialchars($col['name']) ?>
                </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="detail-access">✨ FREE ACCESS</div>

        <div class="detail-rating">
            <i class="fa-solid fa-star"></i>
            <?= number_format($bot['total_rating'], 1) ?>
            <span class="count">(<?= $rating_count ?> ratings)</span>
        </div>

        <div class="desc-box">
            <?php
            $full_desc = $bot['description'];
            $max_length = 450;
            $is_long = mb_strlen($full_desc, 'UTF-8') > $max_length;
            if ($is_long):
                $short_desc = mb_substr($full_desc, 0, $max_length, 'UTF-8') . '...';
            ?>
                <div id="desc-short"><?= nl2br(htmlspecialchars($short_desc)) ?></div>
                <div id="desc-full" style="display:none;"><?= nl2br(htmlspecialchars($full_desc)) ?></div>
                <button id="desc-toggle" onclick="toggleDesc()" style="background:none;border:none;color:var(--pink-dark);font-weight:700;padding:8px 0 0;cursor:pointer;font-size:13px;font-family:inherit;">
                    Show more ▼
                </button>
            <?php else: ?>
                <?= nl2br(htmlspecialchars($full_desc)) ?>
            <?php endif; ?>
        </div>

        <button onclick="document.getElementById('platformModal').classList.add('open')" class="btn-start">
            <i class="fa-solid fa-play"></i> Start Roleplay Now
        </button>
    </div>
</div>

<!-- Bottom: Rating + Comments -->
<div class="bottom-grid">

    <!-- Rating Column -->
    <div class="card" style="padding:26px; height:fit-content;">
        <h3 style="margin:0 0 18px; font-size:17px; padding-bottom:14px; border-bottom:1.5px solid var(--border);">
            Rate this Character
        </h3>

        <?php if(isset($_SESSION['user_id'])): ?>
            <form method="POST">
                <div class="star-picker">
                    <?php for($s = 5; $s >= 1; $s--): ?>
                        <input type="radio" name="score" id="star<?= $s ?>" value="<?= $s ?>"
                               <?= ($user_rating_val == $s) ? 'checked' : '' ?>>
                        <label for="star<?= $s ?>" title="<?= $s ?> star<?= $s>1?'s':'' ?>">★</label>
                    <?php endfor; ?>
                </div>
                <?php if($user_rating_val): ?>
                    <p style="font-size:12px;color:var(--text-3);margin-bottom:14px;">Your rating: <?= $user_rating_val ?> ★ — click to update</p>
                <?php endif; ?>
                <button type="submit" name="submit_rating" class="btn-pink" style="width:100%;justify-content:center;padding:11px;">
                    <i class="fa-solid fa-check"></i> Submit Rating
                </button>
            </form>
        <?php else: ?>
            <div class="login-prompt">
                Please <a href="login.php">log in</a> to rate this character.
            </div>
        <?php endif; ?>
    </div>

    <!-- Comments Column -->
    <div class="card" style="padding:26px;">
        <h3 style="margin:0 0 18px; font-size:17px; padding-bottom:14px; border-bottom:1.5px solid var(--border);">
            Community Discussions <span style="color:var(--text-3);font-size:14px;font-weight:500;">(<?= count($comments) ?>)</span>
        </h3>

        <?php if(isset($_SESSION['user_id'])): ?>
            <form method="POST" style="margin-bottom:28px;">
                <textarea name="content" required class="form-textarea"
                          placeholder="Share your experience with this character..."
                          style="margin-bottom:12px;"></textarea>
                <button type="submit" name="submit_comment" class="btn-pink" style="padding:10px 24px;">
                    <i class="fa-regular fa-paper-plane"></i> Post Comment
                </button>
            </form>
        <?php else: ?>
            <div class="login-prompt" style="margin-bottom:28px;">
                Please <a href="login.php">log in</a> to join the discussion.
            </div>
        <?php endif; ?>

        <div class="comments-list">
            <?php if(empty($comments)): ?>
                <p style="text-align:center;color:var(--text-3);font-style:italic;padding:30px 0;">
                    No comments yet. Be the first! 🌸
                </p>
            <?php endif; ?>

            <?php foreach($comments as $c):
                $is_premium  = ($c['total_spent'] >= 10);
                $avatar_img  = !empty($c['avatar_url']) ? htmlspecialchars($c['avatar_url']) : 'https://i.postimg.cc/mZh4H8hC/default-avatar.png';
                $frame_class = $is_premium ? 'frame-bloom' : 'frame-normal';
                $avt_class   = $is_premium ? 'comment-avatar premium' : 'comment-avatar';
            ?>
                <div class="comment-wrapper">
                    <img src="<?= $avatar_img ?>" class="<?= $avt_class ?>" alt="">
                    <div class="<?= $frame_class ?>" style="flex-grow:1;">
                        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:6px;">
                            <span class="comment-author">
                                <?= htmlspecialchars($c['username']) ?>
                                <?php if($is_premium): ?><span style="color:var(--pink);font-size:11px;margin-left:5px;">✨ Bloom</span><?php endif; ?>
                                <?php if(!empty($c['user_rating'])): ?>
                                    <span class="user-rate-badge"><?= $c['user_rating'] ?> ★</span>
                                <?php endif; ?>
                            </span>
                            <span class="comment-time"><?= date('M d, Y', strtotime($c['created_at'])) ?></span>
                        </div>
                        <p class="comment-text"><?= nl2br(htmlspecialchars($c['content'])) ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Platform Modal -->
<div id="platformModal" class="modal-backdrop" onclick="if(event.target===this)this.classList.remove('open')">
    <div class="modal-card">
        <button class="modal-close" onclick="document.getElementById('platformModal').classList.remove('open')">&times;</button>
        <h3 style="margin:0 0 8px;color:var(--text);font-size:22px;">Choose Platform</h3>
        <p style="color:var(--text-2);font-size:13px;margin-bottom:24px;">Select a platform to start roleplaying!</p>
        <a href="https://www.juicychat.ai/userdetailspace/1951319766699155458" target="_blank" class="platform-btn juicy">
            <i class="fa-solid fa-comment-dots"></i> JuicyChat
        </a>
        <a href="https://crushon.ai/en/profile/906c6ef0-3424-11f1-9a8e-8e74642fdb07?shared=true" target="_blank" class="platform-btn crush">
            <i class="fa-solid fa-heart-pulse"></i> CrushOn.AI
        </a>
    </div>
</div>

<script>
function toggleDesc() {
    const s = document.getElementById('desc-short');
    const f = document.getElementById('desc-full');
    const b = document.getElementById('desc-toggle');
    const showing = f.style.display !== 'none';
    s.style.display = showing ? '' : 'none';
    f.style.display = showing ? 'none' : '';
    b.textContent = showing ? 'Show more ▼' : 'Show less ▲';
}
</script>

<?php require_once '../includes/footer.php'; ?>
