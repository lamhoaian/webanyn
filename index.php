<?php
require_once 'config/database.php';
require_once 'includes/header.php';

$search = $_GET['search'] ?? '';
$theme  = $_GET['theme']  ?? '';

$sql = "SELECT b.*, GROUP_CONCAT(t.name SEPARATOR ',') as theme_names 
        FROM bots b 
        LEFT JOIN bot_themes bt ON b.id = bt.bot_id 
        LEFT JOIN themes t ON bt.theme_id = t.id WHERE 1=1 ";
$params = [];

if($search) { $sql .= " AND b.name LIKE ? "; $params[] = "%$search%"; }
if($theme)  { 
    $sql .= " AND b.id IN (SELECT bt2.bot_id FROM bot_themes bt2 JOIN themes t2 ON bt2.theme_id = t2.id WHERE t2.name = ?) "; 
    $params[] = $theme; 
}

$sql .= " GROUP BY b.id ORDER BY b.is_pinned DESC, b.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bots = $stmt->fetchAll();

$all_themes = $pdo->query("SELECT name FROM themes ORDER BY name ASC")->fetchAll(PDO::FETCH_COLUMN);
?>

<style>
    .filters-row {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        margin-bottom: 32px;
        align-items: center;
    }

    .bots-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
        gap: 22px;
    }

    .empty-state {
        grid-column: 1/-1;
        text-align: center;
        padding: 80px 20px;
        color: var(--text-3);
    }

    .empty-state i { font-size: 48px; margin-bottom: 16px; display: block; }
    .empty-state p { font-size: 16px; }

    .card-img-wrap {
        position: relative;
        overflow: hidden;
    }

    .card-img-wrap img {
        transition: transform .4s ease;
    }

    .bot-card:hover .card-img-wrap img {
        transform: scale(1.04);
    }

    .card-tags {
        display: flex;
        flex-wrap: wrap;
        gap: 4px;
        justify-content: center;
        margin-bottom: 10px;
    }

    .card-meta {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        font-size: 13px;
        font-weight: 700;
        color: #f4a22d;
    }

    @media (max-width: 480px) {
        .bots-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; }
    }
</style>

<h2 class="section-title">
    <?= $search ? 'Search: "'.htmlspecialchars($search).'"' : 'Recommended Characters' ?>
</h2>

<!-- Filter Pills -->
<div class="filters-row">
    <a href="index.php" class="filter-pill <?= empty($theme) && empty($search) ? 'active' : '' ?>">All</a>
    <?php foreach($all_themes as $th): ?>
        <a href="index.php?theme=<?= urlencode($th) ?>"
           class="filter-pill <?= $theme === $th ? 'active' : '' ?>">
            <?= htmlspecialchars($th) ?>
        </a>
    <?php endforeach; ?>
</div>

<!-- Bot Grid -->
<div class="bots-grid">
    <?php if(empty($bots)): ?>
        <div class="empty-state">
            <i class="fa-regular fa-face-sad-tear"></i>
            <p>No characters found. Try a different search.</p>
        </div>
    <?php endif; ?>

    <?php foreach ($bots as $bot): ?>
        <div class="bot-card animate-in" onclick="window.location.href='pages/bot_detail.php?id=<?= $bot['id'] ?>';">
            <div class="card-img-wrap">
                <img src="<?= htmlspecialchars($bot['image_url']) ?>" alt="<?= htmlspecialchars($bot['name']) ?>" loading="lazy">
                <?php if($bot['is_pinned']): ?>
                    <div class="badge-featured">FEATURED</div>
                <?php endif; ?>
            </div>
            <div class="bot-card-body">
                <div class="bot-title"><?= htmlspecialchars($bot['name']) ?></div>
                <?php if(!empty($bot['theme_names'])): ?>
                <div class="card-tags">
                    <?php foreach(explode(',', $bot['theme_names']) as $t): ?>
                        <span class="tag-pill"><?= htmlspecialchars(trim($t)) ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <div class="card-meta">
                    <i class="fa-solid fa-star" style="font-size:12px;"></i>
                    <?= number_format($bot['total_rating'], 1) ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
