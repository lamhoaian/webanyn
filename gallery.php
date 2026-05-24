<?php
require_once '../config/database.php';
require_once '../includes/gems_lib.php';
ensureGemsSchema($pdo);

function ensureGallerySchema(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS gallery_groups (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(100) NULL,
        bot_id INT(11) NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_gallery_groups_bot (bot_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    if (!$pdo->query("SHOW COLUMNS FROM gallery LIKE 'group_id'")->fetch()) {
        $pdo->exec('ALTER TABLE gallery ADD COLUMN group_id INT(11) NULL AFTER title');
        $pdo->exec('ALTER TABLE gallery ADD INDEX idx_gallery_group (group_id)');
    }
}

function isGalleryAdmin(): bool
{
    return isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === 1;
}

function uploadGalleryImage(array $file, string $target_dir): ?string
{
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
        return null;
    }
    $name = time() . '_art_' . uniqid() . '.' . $ext;
    $path = $target_dir . $name;
    if (move_uploaded_file($file['tmp_name'], $path)) {
        return '/anyn/uploads/gallery/' . $name;
    }
    return null;
}

function resolveGalleryGroupId(PDO $pdo, array $post): int
{
    if (($post['group_mode'] ?? '') === 'existing' && !empty($post['existing_group_id'])) {
        return (int)$post['existing_group_id'];
    }
    $group_title = trim($post['group_title'] ?? '') ?: null;
    $bot_id = !empty($post['bot_id']) ? (int)$post['bot_id'] : null;
    $pdo->prepare('INSERT INTO gallery_groups (title, bot_id) VALUES (?, ?)')->execute([$group_title, $bot_id]);
    return (int)$pdo->lastInsertId();
}

function deleteGalleryItem(PDO $pdo, int $gallery_id): void
{
    $row = $pdo->prepare('SELECT group_id FROM gallery WHERE id = ?');
    $row->execute([$gallery_id]);
    $item = $row->fetch();
    if (!$item) {
        return;
    }
    $pdo->prepare('DELETE FROM gallery_reactions WHERE gallery_id = ?')->execute([$gallery_id]);
    $pdo->prepare('DELETE FROM gallery WHERE id = ?')->execute([$gallery_id]);
    if ($item['group_id']) {
        $cnt = $pdo->prepare('SELECT COUNT(*) FROM gallery WHERE group_id = ?');
        $cnt->execute([$item['group_id']]);
        if ((int)$cnt->fetchColumn() === 0) {
            $pdo->prepare('DELETE FROM gallery_groups WHERE id = ?')->execute([$item['group_id']]);
        }
    }
}

ensureGallerySchema($pdo);

$message = '';
$message_type = 'success';
$preview_limit = 4;
$is_admin = isGalleryAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_gallery_id']) && $is_admin) {
    deleteGalleryItem($pdo, (int)$_POST['delete_gallery_id']);
    header('Location: gallery.php?msg=deleted');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_gallery_id']) && $is_admin) {
    $gid = (int)$_POST['update_gallery_id'];
    $title = trim($_POST['title'] ?? '') ?: null;
    $pdo->prepare('UPDATE gallery SET title = ? WHERE id = ?')->execute([$title, $gid]);
    header('Location: gallery.php?msg=updated');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_group_id']) && $is_admin) {
    $ggid = (int)$_POST['update_group_id'];
    $title = trim($_POST['group_title'] ?? '') ?: null;
    $bot_id = !empty($_POST['bot_id']) ? (int)$_POST['bot_id'] : null;
    $pdo->prepare('UPDATE gallery_groups SET title = ?, bot_id = ? WHERE id = ?')->execute([$title, $bot_id, $ggid]);
    header('Location: gallery.php?msg=group_updated');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_art']) && $is_admin) {
    $image_title = trim($_POST['title'] ?? '') ?: null;
    $group_id = resolveGalleryGroupId($pdo, $_POST);
    $target_dir = '../uploads/gallery/';
    $uploaded = 0;
    $errors = [];

    if (isset($_FILES['art_file']['name']) && is_array($_FILES['art_file']['name'])) {
        foreach ($_FILES['art_file']['name'] as $i => $name) {
            if ($name === '' || $_FILES['art_file']['error'][$i] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $file = [
                'name'     => $_FILES['art_file']['name'][$i],
                'type'     => $_FILES['art_file']['type'][$i],
                'tmp_name' => $_FILES['art_file']['tmp_name'][$i],
                'error'    => $_FILES['art_file']['error'][$i],
                'size'     => $_FILES['art_file']['size'][$i],
            ];
            $url = uploadGalleryImage($file, $target_dir);
            if ($url) {
                $pdo->prepare('INSERT INTO gallery (title, group_id, image_url) VALUES (?, ?, ?)')
                    ->execute([$image_title, $group_id, $url]);
                $uploaded++;
            } else {
                $errors[] = htmlspecialchars($name) . ': invalid or failed upload.';
            }
        }
    } elseif (isset($_FILES['art_file']) && $_FILES['art_file']['error'] === UPLOAD_ERR_OK) {
        $url = uploadGalleryImage($_FILES['art_file'], $target_dir);
        if ($url) {
            $pdo->prepare('INSERT INTO gallery (title, group_id, image_url) VALUES (?, ?, ?)')
                ->execute([$image_title, $group_id, $url]);
            $uploaded++;
        } else {
            $errors[] = 'File upload failed or invalid format.';
        }
    }

    if (!empty(trim($_POST['image_url'] ?? ''))) {
        $urls = preg_split('/\r\n|\r|\n/', trim($_POST['image_url']));
        foreach ($urls as $raw) {
            $url = trim($raw);
            if ($url === '') {
                continue;
            }
            $pdo->prepare('INSERT INTO gallery (title, group_id, image_url) VALUES (?, ?, ?)')
                ->execute([$image_title, $group_id, $url]);
            $uploaded++;
        }
    }

    if ($uploaded > 0) {
        header('Location: gallery.php?msg=uploaded&n=' . $uploaded);
        exit;
    }
    $message_type = 'error';
    $message = $errors ? implode(' ', $errors) : 'Please provide at least one image.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['react'])) {
    if (!isset($_SESSION['user_id'])) {
        header('Location: gallery.php?error=login');
        exit;
    }
    $g_id = (int)$_POST['gallery_id'];
    $r_type = $_POST['reaction_type'];
    $u_id = $_SESSION['user_id'];

    $stmt = $pdo->prepare('SELECT reaction_type FROM gallery_reactions WHERE user_id = ? AND gallery_id = ?');
    $stmt->execute([$u_id, $g_id]);
    $existing = $stmt->fetch();

    if ($existing) {
        if ($existing['reaction_type'] === $r_type) {
            $pdo->prepare('DELETE FROM gallery_reactions WHERE user_id = ? AND gallery_id = ?')->execute([$u_id, $g_id]);
        } else {
            $pdo->prepare('UPDATE gallery_reactions SET reaction_type = ? WHERE user_id = ? AND gallery_id = ?')
                ->execute([$r_type, $u_id, $g_id]);
        }
    } else {
        $pdo->prepare('INSERT INTO gallery_reactions (user_id, gallery_id, reaction_type) VALUES (?, ?, ?)')
            ->execute([$u_id, $g_id, $r_type]);
        tryAwardMission($pdo, $u_id, 'react_gallery');
    }
    header('Location: gallery.php');
    exit;
}

$flash = [
    'uploaded'       => 'Artwork published successfully!',
    'deleted'        => 'Image removed from gallery.',
    'updated'        => 'Image title updated.',
    'group_updated'  => 'Collection updated.',
];
if (isset($_GET['msg']) && isset($flash[$_GET['msg']])) {
    $message = $flash[$_GET['msg']];
    if ($_GET['msg'] === 'uploaded' && isset($_GET['n'])) {
        $message = (int)$_GET['n'] . ' image(s) published successfully!';
    }
}
if (isset($_GET['error']) && $_GET['error'] === 'login') {
    $message = 'You must be logged in to react!';
    $message_type = 'error';
}

$gallery_rows = $pdo->query("
    SELECT g.*, gg.title AS group_title, gg.bot_id, b.name AS bot_name, gg.created_at AS group_created
    FROM gallery g
    LEFT JOIN gallery_groups gg ON g.group_id = gg.id
    LEFT JOIN bots b ON gg.bot_id = b.id
    ORDER BY COALESCE(gg.created_at, g.uploaded_at) DESC, g.uploaded_at DESC
")->fetchAll();

$display_groups = [];
foreach ($gallery_rows as $row) {
    $key = $row['group_id'] ? 'g' . $row['group_id'] : 's' . $row['id'];
    if (!isset($display_groups[$key])) {
        $display_groups[$key] = [
            'group_id'    => $row['group_id'],
            'group_title' => $row['group_title'],
            'bot_id'      => $row['bot_id'],
            'bot_name'    => $row['bot_name'],
            'items'       => [],
        ];
    }
    $display_groups[$key]['items'][] = $row;
}

$reactions_stmt = $pdo->query('SELECT gallery_id, reaction_type, COUNT(*) as count FROM gallery_reactions GROUP BY gallery_id, reaction_type');
$react_counts = [];
foreach ($reactions_stmt->fetchAll() as $row) {
    $react_counts[$row['gallery_id']][$row['reaction_type']] = $row['count'];
}

$user_reactions = [];
if (isset($_SESSION['user_id'])) {
    $user_reacts_stmt = $pdo->prepare('SELECT gallery_id, reaction_type FROM gallery_reactions WHERE user_id = ?');
    $user_reacts_stmt->execute([$_SESSION['user_id']]);
    foreach ($user_reacts_stmt->fetchAll() as $row) {
        $user_reactions[$row['gallery_id']] = $row['reaction_type'];
    }
}

$bots_list = $pdo->query('SELECT id, name FROM bots ORDER BY name ASC')->fetchAll();
$existing_groups = $pdo->query("
    SELECT gg.id, gg.title, b.name AS bot_name, COUNT(g.id) AS img_count
    FROM gallery_groups gg
    LEFT JOIN gallery g ON g.group_id = gg.id
    LEFT JOIN bots b ON gg.bot_id = b.id
    GROUP BY gg.id
    ORDER BY gg.created_at DESC
")->fetchAll();

function galleryGroupLabel(array $group): string
{
    if (!empty(trim($group['group_title'] ?? ''))) {
        return $group['group_title'];
    }
    if (!empty($group['bot_name'])) {
        return $group['bot_name'];
    }
    foreach ($group['items'] as $item) {
        if (!empty(trim($item['title'] ?? ''))) {
            return $item['title'];
        }
    }
    return 'Artwork Collection';
}

require_once '../includes/header.php';
?>

<style>
    .gal-groups { display: flex; flex-direction: column; gap: 28px; }
    .gal-group {
        background: var(--surface);
        border: 1.5px solid var(--border);
        border-radius: var(--radius);
        padding: 20px;
        box-shadow: var(--shadow-sm);
    }
    .gal-group-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
        margin-bottom: 16px;
        padding-bottom: 14px;
        border-bottom: 1px solid var(--border);
    }
    .gal-group-title { font-size: 18px; font-weight: 700; color: var(--text); margin: 0 0 4px; }
    .gal-group-meta { font-size: 12px; color: var(--text-3); }
    .gal-group-meta .bot-link { color: var(--pink-dark); font-weight: 600; }
    .gal-group-items {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: 18px;
    }
    .gal-group.is-collapsible:not(.expanded) .gal-group-items .gal-card:nth-child(n+<?= $preview_limit + 1 ?>) {
        display: none;
    }
    .gal-card {
        background: var(--surface-2);
        border-radius: var(--radius-sm);
        overflow: hidden;
        border: 1px solid var(--border);
        transition: all var(--transition);
    }
    .gal-card:hover { border-color: var(--pink); box-shadow: var(--shadow-md); }
    .gal-img-wrap { position: relative; }
    .gal-img { width: 100%; aspect-ratio: 1/1; object-fit: cover; display: block; }
    .gal-card-body { padding: 12px 14px 14px; }
    .gal-card-title { font-weight: 700; font-size: 14px; color: var(--text); margin-bottom: 4px; min-height: 1.2em; }
    .gal-card-title.is-untitled { color: var(--text-3); font-weight: 500; font-style: italic; }
    .gal-date { font-size: 11px; color: var(--text-3); margin-bottom: 10px; }
    .reaction-bar { display: flex; gap: 6px; flex-wrap: wrap; padding-top: 10px; border-top: 1px solid var(--border); }
    .btn-react {
        padding: 4px 10px; border-radius: 99px; font-weight: 700; font-size: 11px;
        border: 1.5px solid var(--border); cursor: pointer; transition: all var(--transition);
        background: var(--surface); color: var(--text-2); font-family: inherit;
    }
    .btn-react:hover { transform: scale(1.05); }
    .react-like.active  { background: #e0f2fe; color: #0284c7; border-color: #bae6fd; }
    .react-love.active  { background: #fce7f3; color: #db2777; border-color: #fbcfe8; }
    .react-fire.active  { background: #ffedd5; color: #ea580c; border-color: #fed7aa; }
    [data-theme="dark"] .react-like.active { background: #0c2233; color: #38bdf8; border-color: #164e63; }
    [data-theme="dark"] .react-love.active { background: #2d0a1e; color: #f472b6; border-color: #831843; }
    [data-theme="dark"] .react-fire.active { background: #2c1006; color: #fb923c; border-color: #7c2d12; }
    .gal-show-more {
        display: block; width: 100%; margin-top: 16px; padding: 10px;
        background: var(--pink-soft); border: 1px solid var(--pink); color: var(--pink-dark);
        border-radius: 10px; font-weight: 700; font-size: 13px; cursor: pointer;
        font-family: inherit; transition: all .2s;
    }
    .gal-show-more:hover { background: var(--pink); color: white; }
    .gal-group.expanded .gal-show-more .more-label { display: none; }
    .gal-group:not(.expanded) .gal-show-more .less-label { display: none; }
    .gal-group:not(.is-collapsible) .gal-show-more { display: none; }
    .admin-img-actions { display: flex; gap: 6px; margin-top: 8px; flex-wrap: wrap; }
    .btn-gal-del, .btn-gal-save {
        padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 700;
        cursor: pointer; font-family: inherit; border: 1px solid;
    }
    .btn-gal-del { background: #fef2f2; color: #ef4444; border-color: #fca5a5; }
    .btn-gal-del:hover { background: #fee2e2; }
    .btn-gal-save { background: var(--pink-soft); color: var(--pink-dark); border-color: var(--pink); }
    .title-edit-input {
        width: 100%; padding: 6px 8px; font-size: 12px; border: 1px solid var(--border);
        border-radius: 6px; margin-bottom: 6px; background: var(--surface); color: var(--text);
        font-family: inherit; box-sizing: border-box;
    }
    .upload-panel label.field-label { display: block; font-size: 12px; font-weight: 700; color: var(--text-2); margin-bottom: 6px; text-transform: uppercase; letter-spacing: .4px; }
    .upload-panel .form-row { margin-bottom: 14px; }
    .group-mode-row { display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 8px; }
    .group-mode-row label { font-size: 13px; color: var(--text-2); cursor: pointer; display: flex; align-items: center; gap: 6px; }
    #existingGroupWrap { display: none; }
    #existingGroupWrap.visible { display: block; }
    .alert-box { padding: 12px 16px; border-radius: 10px; font-size: 13px; font-weight: 600; margin-bottom: 20px; }
    .alert-success { background: #f0fdf4; color: #16a34a; }
    .alert-error { background: #fef2f2; color: #ef4444; }
    .group-admin-form { display: flex; gap: 8px; flex-wrap: wrap; align-items: flex-end; margin-top: 8px; }
    .group-admin-form input, .group-admin-form select { font-size: 12px; padding: 6px 10px; border-radius: 8px; border: 1px solid var(--border); background: var(--surface-2); color: var(--text); font-family: inherit; }
</style>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:28px;flex-wrap:wrap;gap:12px;">
    <h2 class="section-title" style="margin-bottom:0;">Official Artwork & Gallery</h2>
    <p style="color:var(--text-3);font-size:14px;">Exclusive art and previews of upcoming characters. 🎨</p>
</div>

<?php if ($message): ?>
    <div class="alert-box alert-<?= $message_type === 'error' ? 'error' : 'success' ?>"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<?php if ($is_admin): ?>
<div class="upload-panel" style="background:var(--surface);border:1.5px dashed var(--pink);border-radius:var(--radius);padding:24px;margin-bottom:32px;">
    <h3 style="margin:0 0 16px;color:var(--pink-dark);font-size:16px;"><i class="fa-solid fa-crown"></i> Admin: Upload Artwork</h3>
    <form method="POST" enctype="multipart/form-data">
        <div class="form-row">
            <span class="field-label">Collection</span>
            <div class="group-mode-row">
                <label><input type="radio" name="group_mode" value="new" checked onchange="toggleGroupMode()"> New collection</label>
                <?php if (!empty($existing_groups)): ?>
                <label><input type="radio" name="group_mode" value="existing" onchange="toggleGroupMode()"> Add to existing</label>
                <?php endif; ?>
            </div>
            <div id="newGroupFields" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:10px;">
                <div>
                    <span class="field-label">Collection title (optional)</span>
                    <input type="text" name="group_title" placeholder="e.g. Summer set, Character sketches" class="form-input">
                </div>
                <div>
                    <span class="field-label">Related bot (optional)</span>
                    <select name="bot_id" class="form-input">
                        <option value="">— None —</option>
                        <?php foreach ($bots_list as $b): ?>
                            <option value="<?= (int)$b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div id="existingGroupWrap" class="form-row">
                <span class="field-label">Choose collection</span>
                <select name="existing_group_id" class="form-input"<?= empty($existing_groups) ? ' disabled' : '' ?>>
                    <?php foreach ($existing_groups as $eg): ?>
                        <option value="<?= (int)$eg['id'] ?>">
                            <?= htmlspecialchars($eg['title'] ?: $eg['bot_name'] ?: 'Collection #' . $eg['id']) ?>
                            (<?= (int)$eg['img_count'] ?> images)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-row">
            <span class="field-label">Image title (optional — can add later)</span>
            <input type="text" name="title" placeholder="Leave blank if unsure" class="form-input">
        </div>

        <div class="form-row">
            <span class="field-label">Images</span>
            <input type="file" name="art_file[]" accept="image/*" multiple style="font-size:13px;color:var(--text-2);margin-bottom:10px;">
            <span style="font-size:12px;color:var(--text-3);display:block;margin-bottom:8px;">— or paste URL(s), one per line —</span>
            <textarea name="image_url" rows="3" placeholder="https://..." class="form-input" style="resize:vertical;"></textarea>
        </div>

        <button type="submit" name="upload_art" class="btn-pink"><i class="fa-solid fa-upload"></i> Publish</button>
    </form>
</div>
<script>
function toggleGroupMode() {
    const isExisting = document.querySelector('input[name="group_mode"][value="existing"]').checked;
    document.getElementById('existingGroupWrap').classList.toggle('visible', isExisting);
    document.getElementById('newGroupFields').style.display = isExisting ? 'none' : 'grid';
}
</script>
<?php endif; ?>

<div class="gal-groups">
    <?php if (empty($display_groups)): ?>
        <div style="text-align:center;padding:60px;color:var(--text-3);background:var(--surface);border-radius:var(--radius);">
            <i class="fa-regular fa-image" style="font-size:40px;display:block;margin-bottom:12px;"></i>
            No artwork yet. Stay tuned!
        </div>
    <?php endif; ?>

    <?php foreach ($display_groups as $group):
        $label = galleryGroupLabel($group);
        $count = count($group['items']);
        $collapsible = $count > $preview_limit;
        $group_id = $group['group_id'];
    ?>
    <section class="gal-group <?= $collapsible ? 'is-collapsible' : '' ?> animate-in">
        <div class="gal-group-header">
            <div>
                <h3 class="gal-group-title"><?= htmlspecialchars($label) ?></h3>
                <div class="gal-group-meta">
                    <?= $count ?> image<?= $count !== 1 ? 's' : '' ?>
                    <?php if (!empty($group['bot_name'])): ?>
                        · <span class="bot-link"><i class="fa-solid fa-robot"></i> <?= htmlspecialchars($group['bot_name']) ?></span>
                    <?php endif; ?>
                </div>
                <?php if ($is_admin && $group_id): ?>
                <form method="POST" class="group-admin-form">
                    <input type="hidden" name="update_group_id" value="<?= (int)$group_id ?>">
                    <input type="text" name="group_title" placeholder="Collection title" value="<?= htmlspecialchars($group['group_title'] ?? '') ?>">
                    <select name="bot_id">
                        <option value="">No bot</option>
                        <?php foreach ($bots_list as $b): ?>
                            <option value="<?= (int)$b['id'] ?>" <?= (int)($group['bot_id'] ?? 0) === (int)$b['id'] ? 'selected' : '' ?>><?= htmlspecialchars($b['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn-gal-save">Save collection</button>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="gal-group-items">
            <?php foreach ($group['items'] as $item):
                $gid = (int)$item['id'];
                $ur  = $user_reactions[$gid] ?? null;
                $has_title = !empty(trim($item['title'] ?? ''));
            ?>
            <article class="gal-card">
                <div class="gal-img-wrap">
                    <img src="<?= htmlspecialchars($item['image_url']) ?>" class="gal-img" alt="<?= htmlspecialchars($item['title'] ?: 'Artwork') ?>" loading="lazy">
                </div>
                <div class="gal-card-body">
                    <?php if ($has_title): ?>
                        <div class="gal-card-title"><?= htmlspecialchars($item['title']) ?></div>
                    <?php else: ?>
                        <div class="gal-card-title is-untitled">Untitled</div>
                    <?php endif; ?>
                    <div class="gal-date"><?= date('M d, Y', strtotime($item['uploaded_at'])) ?></div>

                    <?php if ($is_admin): ?>
                    <form method="POST" class="admin-img-actions">
                        <input type="hidden" name="update_gallery_id" value="<?= $gid ?>">
                        <input type="text" name="title" class="title-edit-input" placeholder="Add title..." value="<?= htmlspecialchars($item['title'] ?? '') ?>">
                        <button type="submit" class="btn-gal-save">Save title</button>
                    </form>
                    <form method="POST" onsubmit="return confirm('Delete this image?');">
                        <input type="hidden" name="delete_gallery_id" value="<?= $gid ?>">
                        <button type="submit" class="btn-gal-del"><i class="fa-solid fa-trash"></i> Delete</button>
                    </form>
                    <?php endif; ?>

                    <div class="reaction-bar">
                        <?php foreach (['like' => '👍', 'love' => '❤️', 'fire' => '🔥'] as $rtype => $emoji):
                            $cnt    = $react_counts[$gid][$rtype] ?? 0;
                            $active = ($ur === $rtype) ? 'active' : '';
                        ?>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="gallery_id" value="<?= $gid ?>">
                            <input type="hidden" name="reaction_type" value="<?= $rtype ?>">
                            <button type="submit" name="react" class="btn-react react-<?= $rtype ?> <?= $active ?>"><?= $emoji ?> <?= $cnt ?></button>
                        </form>
                        <?php endforeach; ?>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>

        <?php if ($collapsible): ?>
        <button type="button" class="gal-show-more" onclick="this.closest('.gal-group').classList.toggle('expanded')">
            <span class="more-label">Show all (<?= $count ?> images)</span>
            <span class="less-label">Show less</span>
        </button>
        <?php endif; ?>
    </section>
    <?php endforeach; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
