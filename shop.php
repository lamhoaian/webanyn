<?php
require_once '../config/database.php';
require_once '../includes/gems_lib.php';

ensureGemsSchema($pdo);

if (!isset($_SESSION['user_id'])) {
    require_once '../includes/header.php';
    echo "<div style='text-align:center;padding:60px;background:var(--surface);border-radius:var(--radius);'><h3>Sign in required</h3><p style='color:var(--text-3);'><a href='login.php' style='color:var(--pink-dark);font-weight:700;'>Log in</a> to use the Gem Shop.</p></div>";
    require_once '../includes/footer.php';
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$tab = $_GET['tab'] ?? 'missions';
if (!in_array($tab, ['missions', 'shop', 'inventory'], true)) {
    $tab = 'missions';
}

$flash = '';
$flash_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['daily_checkin'])) {
        $r = tryAwardMission($pdo, $user_id, 'daily_checkin');
        $flash = $r['message'];
        $flash_type = $r['ok'] ? 'success' : 'error';
        $tab = 'missions';
    } elseif (isset($_POST['buy_item'])) {
        $r = purchaseShopItem($pdo, $user_id, $_POST['buy_item'] ?? '');
        $flash = $r['message'];
        $flash_type = $r['ok'] ? 'success' : 'error';
        $tab = 'shop';
    } elseif (isset($_POST['equip_type'])) {
        $r = equipCosmetic($pdo, $user_id, $_POST['equip_type'], $_POST['equip_key'] ?: null);
        $flash = $r['message'];
        $flash_type = $r['ok'] ? 'success' : 'error';
        $tab = 'inventory';
    }
}

$gems = getUserGems($pdo, $user_id);
$missions = getMissionProgress($pdo, $user_id);
$shop_items = getShopItems($pdo);
$inventory = getUserInventory($pdo, $user_id);

$owned_keys = [];
foreach ($inventory as $inv) {
    if ($inv['used_at'] === null) {
        $owned_keys[$inv['item_key']] = true;
    }
}

$u = $pdo->prepare('SELECT active_chat_frame, active_avatar_frame FROM users WHERE id = ?');
$u->execute([$user_id]);
$user_cos = $u->fetch();

require_once '../includes/header.php';
?>

<style>
    .gems-hero {
        background: linear-gradient(135deg, var(--pink-soft), #ede9fe);
        border: 1px solid var(--pink-border);
        border-radius: var(--radius);
        padding: 24px 28px;
        margin-bottom: 24px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 16px;
    }
    .gems-balance { font-size: 32px; font-weight: 800; color: var(--pink-dark); display: flex; align-items: center; gap: 10px; }
    .gems-balance i { color: #a855f7; }
    .shop-tabs { display: flex; gap: 8px; margin-bottom: 24px; flex-wrap: wrap; }
    .shop-tab {
        padding: 10px 18px; border-radius: 99px; font-weight: 700; font-size: 13px;
        text-decoration: none; border: 1.5px solid var(--border); color: var(--text-2); background: var(--surface);
    }
    .shop-tab.active { background: var(--pink-soft); border-color: var(--pink); color: var(--pink-dark); }
    .mission-card, .shop-item-card {
        background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-sm);
        padding: 16px 18px; margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center;
        gap: 12px; flex-wrap: wrap; box-shadow: var(--shadow-sm);
    }
    .mission-card h4, .shop-item-card h4 { margin: 0 0 4px; font-size: 15px; color: var(--text); }
    .mission-card p, .shop-item-card p { margin: 0; font-size: 12px; color: var(--text-3); }
    .gem-reward { color: #7c3aed; font-weight: 800; font-size: 14px; white-space: nowrap; }
    .progress-bar { height: 6px; background: var(--surface-2); border-radius: 99px; margin-top: 8px; overflow: hidden; max-width: 200px; }
    .progress-bar span { display: block; height: 100%; background: var(--pink); border-radius: 99px; }
    .shop-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 16px; }
    .shop-item-card { flex-direction: column; align-items: stretch; }
    .shop-item-card .shop-item-foot { display: flex; justify-content: space-between; align-items: center; margin-top: 12px; width: 100%; }
    .btn-gem {
        padding: 8px 16px; border-radius: 10px; border: none; font-weight: 700; font-size: 12px;
        cursor: pointer; font-family: inherit; background: linear-gradient(135deg, #c084fc, #a855f7); color: white;
    }
    .btn-gem:disabled { opacity: .5; cursor: not-allowed; }
    .btn-gem-secondary { background: var(--surface-2); color: var(--text-2); border: 1px solid var(--border); }
    .btn-checkin { background: #10b981; color: white; padding: 12px 24px; border: none; border-radius: 12px; font-weight: 700; cursor: pointer; font-family: inherit; font-size: 14px; }
    .category-title { font-size: 16px; font-weight: 800; color: var(--pink-dark); margin: 24px 0 12px; }
    .inv-item { display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap; }
    .badge-owned { background: #dcfce7; color: #15803d; font-size: 10px; font-weight: 800; padding: 3px 8px; border-radius: 99px; }
    .badge-used { background: var(--surface-2); color: var(--text-3); font-size: 10px; font-weight: 800; padding: 3px 8px; border-radius: 99px; }
    .shop-note { font-size: 13px; color: var(--text-3); line-height: 1.6; margin-bottom: 20px; }
</style>

<div class="gems-hero animate-in">
    <div>
        <h2 class="section-title" style="margin:0 0 6px;">Gem Shop & Missions</h2>
        <p style="margin:0;color:var(--text-2);font-size:14px;">Earn gems from activity, spend them on cosmetics &amp; rewards.</p>
    </div>
    <div class="gems-balance"><i class="fa-solid fa-gem"></i> <?= number_format($gems) ?> gems</div>
</div>

<?php if ($flash): ?>
<div class="alert-<?= $flash_type === 'success' ? 'success' : 'error' ?>" style="padding:12px 16px;border-radius:10px;margin-bottom:20px;font-weight:600;background:<?= $flash_type === 'success' ? '#f0fdf4;color:#16a34a' : '#fef2f2;color:#ef4444' ?>;">
    <?= htmlspecialchars($flash) ?>
</div>
<?php endif; ?>

<div class="shop-tabs">
    <a href="shop.php?tab=missions" class="shop-tab <?= $tab === 'missions' ? 'active' : '' ?>"><i class="fa-solid fa-list-check"></i> Missions</a>
    <a href="shop.php?tab=shop" class="shop-tab <?= $tab === 'shop' ? 'active' : '' ?>"><i class="fa-solid fa-store"></i> Shop</a>
    <a href="shop.php?tab=inventory" class="shop-tab <?= $tab === 'inventory' ? 'active' : '' ?>"><i class="fa-solid fa-box-open"></i> Inventory</a>
</div>

<?php if ($tab === 'missions'): ?>
    <p class="shop-note">Complete daily missions to earn gems. Limits reset at midnight.</p>

    <form method="POST" style="margin-bottom:24px;">
        <button type="submit" name="daily_checkin" class="btn-checkin" <?= $missions['daily_checkin']['done'] >= 1 ? 'disabled' : '' ?>>
            <i class="fa-solid fa-calendar-check"></i>
            <?= $missions['daily_checkin']['done'] >= 1 ? 'Checked in today' : 'Daily check-in (+5 gems)' ?>
        </button>
    </form>

    <?php foreach ($missions as $key => $m):
        if ($key === 'daily_checkin') continue;
        $pct = $m['daily_max'] > 0 ? min(100, ($m['done'] / $m['daily_max']) * 100) : 0;
    ?>
    <div class="mission-card">
        <div>
            <h4><?= htmlspecialchars($m['name']) ?></h4>
            <p><?= (int)$m['done'] ?> / <?= (int)$m['daily_max'] ?> today</p>
            <div class="progress-bar"><span style="width:<?= $pct ?>%"></span></div>
        </div>
        <span class="gem-reward">+<?= (int)$m['gems'] ?> <i class="fa-solid fa-gem"></i> / each</span>
    </div>
    <?php endforeach; ?>

<?php elseif ($tab === 'shop'): ?>
    <p class="shop-note">30 gems ≈ $3 value for image sets. Commission vouchers are used when placing an order. Chat &amp; avatar frames equip automatically on purchase.</p>

    <?php
    $by_cat = [];
    foreach ($shop_items as $si) {
        $by_cat[$si['category']][] = $si;
    }
    foreach ($by_cat as $cat => $items):
    ?>
    <h3 class="category-title"><?= htmlspecialchars(shopCategoryLabel($cat)) ?></h3>
    <div class="shop-grid">
        <?php foreach ($items as $si):
            $owned = isset($owned_keys[$si['item_key']]) && in_array($cat, ['chat_frame', 'avatar_frame'], true);
            $can_afford = $gems >= (int)$si['cost_gems'];
        ?>
        <div class="shop-item-card">
            <h4><?= htmlspecialchars($si['name']) ?></h4>
            <p><?= htmlspecialchars($si['description'] ?? '') ?></p>
            <div class="shop-item-foot">
                <span class="gem-reward"><?= (int)$si['cost_gems'] ?> <i class="fa-solid fa-gem"></i></span>
                <?php if ($owned): ?>
                    <span class="badge-owned">Owned</span>
                <?php else: ?>
                <form method="POST">
                    <input type="hidden" name="buy_item" value="<?= htmlspecialchars($si['item_key']) ?>">
                    <button type="submit" class="btn-gem" <?= !$can_afford ? 'disabled' : '' ?>>Buy</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>

<?php else: ?>
    <p class="shop-note">Equip chat &amp; avatar frames. Use commission vouchers on the <a href="commission.php" style="color:var(--pink-dark);font-weight:700;">Commission</a> page.</p>

    <h3 class="category-title">Equipped</h3>
    <div class="mission-card">
        <div>
            <h4>Chat frame</h4>
            <p><?= htmlspecialchars($user_cos['active_chat_frame'] ?: 'None') ?></p>
        </div>
        <form method="POST" style="display:flex;gap:6px;flex-wrap:wrap;">
            <input type="hidden" name="equip_type" value="chat_frame">
            <select name="equip_key" class="form-input" style="margin:0;max-width:180px;padding:8px;">
                <option value="">— None —</option>
                <?php foreach ($inventory as $inv):
                    if ($inv['item_type'] !== 'chat_frame' || $inv['used_at']) continue;
                ?>
                <option value="<?= htmlspecialchars($inv['item_key']) ?>" <?= $user_cos['active_chat_frame'] === $inv['item_key'] ? 'selected' : '' ?>><?= htmlspecialchars($inv['name'] ?? $inv['item_key']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn-gem btn-gem-secondary">Equip</button>
        </form>
    </div>
    <div class="mission-card">
        <div>
            <h4>Avatar frame</h4>
            <p><?= htmlspecialchars($user_cos['active_avatar_frame'] ?: 'None') ?></p>
        </div>
        <form method="POST" style="display:flex;gap:6px;flex-wrap:wrap;">
            <input type="hidden" name="equip_type" value="avatar_frame">
            <select name="equip_key" class="form-input" style="margin:0;max-width:180px;padding:8px;">
                <option value="">— None —</option>
                <?php foreach ($inventory as $inv):
                    if ($inv['item_type'] !== 'avatar_frame' || $inv['used_at']) continue;
                ?>
                <option value="<?= htmlspecialchars($inv['item_key']) ?>" <?= $user_cos['active_avatar_frame'] === $inv['item_key'] ? 'selected' : '' ?>><?= htmlspecialchars($inv['name'] ?? $inv['item_key']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn-gem btn-gem-secondary">Equip</button>
        </form>
    </div>

    <h3 class="category-title">All items</h3>
    <?php if (empty($inventory)): ?>
        <p style="color:var(--text-3);">Your inventory is empty. Visit the Shop!</p>
    <?php else: ?>
        <?php foreach ($inventory as $inv): ?>
        <div class="mission-card inv-item">
            <div>
                <h4><?= htmlspecialchars($inv['name'] ?? $inv['item_key']) ?></h4>
                <p><?= htmlspecialchars(shopCategoryLabel($inv['item_type'])) ?></p>
            </div>
            <?php if ($inv['used_at']): ?>
                <span class="badge-used">Used</span>
            <?php else: ?>
                <span class="badge-owned">Available</span>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
