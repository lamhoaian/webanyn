<?php
require_once '../config/database.php';
require_once '../includes/ideas_lib.php';
require_once '../includes/gems_lib.php';

ensureIdeasSchema($pdo);
ensureGemsSchema($pdo);

$is_admin = isIdeasAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_idea_id'])) {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ideas.php?error=login');
        exit;
    }
    $idea_id = (int)$_POST['delete_idea_id'];
    $owner = $pdo->prepare('SELECT user_id, work_status FROM ideas WHERE id = ?');
    $owner->execute([$idea_id]);
    $row = $owner->fetch();
    if ($row && (int)$row['user_id'] === (int)$_SESSION['user_id'] && $row['work_status'] === 'open') {
        $pdo->prepare('DELETE FROM idea_upvotes WHERE idea_id = ?')->execute([$idea_id]);
        $pdo->prepare('DELETE FROM ideas WHERE id = ?')->execute([$idea_id]);
        header('Location: ideas.php?msg=deleted');
        exit;
    }
    header('Location: ideas.php?error=forbidden');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upvote_id'])) {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ideas.php?error=login');
        exit;
    }
    $idea_id = (int)$_POST['upvote_id'];
    $st = $pdo->prepare("SELECT work_status FROM ideas WHERE id = ?");
    $st->execute([$idea_id]);
    $idea_row = $st->fetch();
    if (!$idea_row || $idea_row['work_status'] !== 'open') {
        header('Location: ideas.php');
        exit;
    }
    $user_id = (int)$_SESSION['user_id'];
    $check = $pdo->prepare('SELECT user_id FROM idea_upvotes WHERE user_id = ? AND idea_id = ?');
    $check->execute([$user_id, $idea_id]);
    if ($check->rowCount() === 0) {
        $pdo->prepare('INSERT INTO idea_upvotes (user_id, idea_id) VALUES (?, ?)')->execute([$user_id, $idea_id]);
        $pdo->prepare('UPDATE ideas SET upvotes = upvotes + 1 WHERE id = ?')->execute([$idea_id]);
        tryAwardMission($pdo, $user_id, 'upvote_idea');
    }
    header('Location: ideas.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_admin) {
    if (isset($_POST['idea_set_progress'])) {
        $id = (int)$_POST['idea_id'];
        $pdo->prepare("UPDATE ideas SET work_status = 'in_progress' WHERE id = ? AND work_status = 'open'")->execute([$id]);
        header('Location: ideas.php?msg=progress');
        exit;
    }
    if (isset($_POST['idea_reopen'])) {
        $id = (int)$_POST['idea_id'];
        $pdo->prepare("UPDATE ideas SET work_status = 'open', bot_id = NULL, bot_visibility = NULL, unlisted_link = NULL, completed_at = NULL WHERE id = ?")->execute([$id]);
        header('Location: ideas.php?msg=reopened');
        exit;
    }
    if (isset($_POST['idea_complete'])) {
        $id = (int)$_POST['idea_id'];
        $bot_id = (int)($_POST['bot_id'] ?? 0);
        $vis = $_POST['bot_visibility'] ?? 'published';
        if (!in_array($vis, ['published', 'unlisted'], true)) {
            $vis = 'published';
        }
        if ($vis === 'unlisted') {
            $link = trim($_POST['unlisted_link'] ?? '');
            if ($link === '') {
                header('Location: ideas.php?error=link_required');
                exit;
            }
            $pdo->prepare("UPDATE ideas SET work_status = 'completed', bot_id = NULL, bot_visibility = 'unlisted', unlisted_link = ?, completed_at = NOW() WHERE id = ?")
                ->execute([$link, $id]);
            header('Location: ideas.php?msg=completed');
            exit;
        }
        $bot_id_val = $bot_id > 0 ? $bot_id : null;
        $pdo->prepare("UPDATE ideas SET work_status = 'completed', bot_id = ?, bot_visibility = 'published', unlisted_link = NULL, completed_at = NOW() WHERE id = ?")
            ->execute([$bot_id_val, $id]);
        header('Location: ideas.php?msg=completed');
        exit;
    }
}

require_once '../includes/header.php';

$flash_msgs = [
    'deleted'   => 'Your idea has been deleted.',
    'updated'   => 'Your idea has been updated.',
    'progress'  => 'Idea marked as In Progress.',
    'completed' => 'Idea marked as completed.',
    'reopened'  => 'Idea moved back to the open board.',
];
$flash_errors = [
    'login'        => 'You need to log in to perform this action.',
    'forbidden'    => 'You can only edit or delete your own open ideas.',
    'link_required' => 'Please enter a link for the unlisted bot.',
];
$flash = isset($_GET['msg'], $flash_msgs[$_GET['msg']]) ? $flash_msgs[$_GET['msg']] : null;
$error = isset($_GET['error'], $flash_errors[$_GET['error']]) ? $flash_errors[$_GET['error']] : null;

$base_sql = 'SELECT ideas.*, users.username FROM ideas JOIN users ON ideas.user_id = users.id';

$completed = $pdo->query("
    $base_sql
    WHERE ideas.work_status = 'completed'
    ORDER BY ideas.completed_at DESC, ideas.created_at DESC
")->fetchAll();

$completed_enriched = [];
foreach ($completed as $c) {
    if ($c['bot_id']) {
        $bs = $pdo->prepare('SELECT id, name, image_url, description, rp_platform_url FROM bots WHERE id = ?');
        $bs->execute([$c['bot_id']]);
        $c['bot'] = $bs->fetch() ?: null;
    } else {
        $c['bot'] = null;
    }
    $completed_enriched[] = $c;
}

$active_ideas = $pdo->query("
    $base_sql
    WHERE ideas.work_status IN ('open', 'in_progress')
    ORDER BY FIELD(ideas.work_status, 'in_progress', 'open'), ideas.upvotes DESC, ideas.created_at DESC
")->fetchAll();

$bots_list = $is_admin ? $pdo->query('SELECT id, name FROM bots ORDER BY name ASC')->fetchAll() : [];
$current_user_id = $_SESSION['user_id'] ?? null;

$my_unlisted_rewards = [];
if ($current_user_id) {
    $ur = $pdo->prepare("
        SELECT ideas.id AS idea_id, ideas.title AS idea_title, ideas.unlisted_link,
               bots.id AS bot_id, bots.name AS bot_name, bots.rp_platform_url
        FROM ideas
        LEFT JOIN bots ON ideas.bot_id = bots.id
        WHERE ideas.user_id = ? AND ideas.work_status = 'completed' AND ideas.bot_visibility = 'unlisted'
        ORDER BY ideas.completed_at DESC
    ");
    $ur->execute([(int)$current_user_id]);
    foreach ($ur->fetchAll() as $row) {
        $bot = $row['bot_id'] ? ['id' => $row['bot_id'], 'rp_platform_url' => $row['rp_platform_url']] : null;
        $row['bot_link'] = ideaShareLink($row, $bot);
        $row['display_name'] = $row['bot_name'] ?: 'Your bot';
        if ($row['bot_link'] !== '') {
            $my_unlisted_rewards[] = $row;
        }
    }
}

$completed_published = array_values(array_filter(
    $completed_enriched,
    static fn($c) => ($c['bot_visibility'] ?? 'published') === 'published'
));

function renderIdeaCard(array $idea, ?int $current_user_id, bool $is_admin, array $bots_list): void
{
    $status = $idea['work_status'] ?? 'open';
    $can_vote = $status === 'open';
    $can_edit = $current_user_id && (int)$current_user_id === (int)$idea['user_id'] && $status === 'open';
    $card_class = 'idea-card';
    if ($status === 'in_progress') {
        $card_class .= ' idea-card-progress';
    }
    ?>
    <div class="<?= $card_class ?>">
        <div class="vote-col">
            <?php if ($can_vote): ?>
            <form method="POST" action="">
                <input type="hidden" name="upvote_id" value="<?= (int)$idea['id'] ?>">
                <button type="submit" class="btn-upvote" title="Upvote this idea"><i class="fa-solid fa-caret-up"></i></button>
            </form>
            <?php else: ?>
            <div class="btn-upvote btn-upvote-disabled" title="Voting closed"><i class="fa-solid fa-caret-up"></i></div>
            <?php endif; ?>
            <div class="vote-count"><?= (int)$idea['upvotes'] ?></div>
        </div>

        <div class="content-col">
            <div class="idea-title-row">
                <div>
                    <?php if ($status === 'in_progress'): ?>
                        <span class="status-badge status-progress"><i class="fa-solid fa-spinner"></i> In Progress</span>
                    <?php endif; ?>
                    <h3 class="idea-title" style="margin:0;"><?= htmlspecialchars($idea['title']) ?></h3>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-start;">
                    <?php if ($can_edit): ?>
                    <div class="idea-owner-actions">
                        <a href="edit_idea.php?id=<?= (int)$idea['id'] ?>" class="btn-idea-edit"><i class="fa-solid fa-pen"></i> Edit</a>
                        <form method="POST" style="margin:0;" onsubmit="return confirm('Delete this idea permanently?');">
                            <input type="hidden" name="delete_idea_id" value="<?= (int)$idea['id'] ?>">
                            <button type="submit" class="btn-idea-delete"><i class="fa-solid fa-trash"></i> Delete</button>
                        </form>
                    </div>
                    <?php endif; ?>
                    <?php if ($is_admin && $status !== 'completed'): ?>
                    <div class="admin-idea-actions">
                        <?php if ($status === 'open'): ?>
                        <form method="POST" style="margin:0;">
                            <input type="hidden" name="idea_id" value="<?= (int)$idea['id'] ?>">
                            <button type="submit" name="idea_set_progress" class="btn-admin-progress"><i class="fa-solid fa-hammer"></i> Start work</button>
                        </form>
                        <?php endif; ?>
                        <?php if ($status === 'in_progress'): ?>
                        <form method="POST" class="complete-form" style="margin:0;" data-complete-form>
                            <input type="hidden" name="idea_id" value="<?= (int)$idea['id'] ?>">
                            <select name="bot_visibility" class="admin-select admin-vis-select" onchange="toggleIdeaCompleteFields(this)">
                                <option value="published">Published</option>
                                <option value="unlisted">Unlisted</option>
                            </select>
                            <div class="complete-published-fields" data-published-fields>
                                <select name="bot_id" class="admin-select admin-bot-select">
                                    <option value="">Bot (optional)…</option>
                                    <?php foreach ($bots_list as $b): ?>
                                        <option value="<?= (int)$b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="complete-unlisted-fields" data-unlisted-fields>
                                <input type="url" name="unlisted_link" class="admin-link-input" placeholder="https://… bot link">
                                <p class="complete-unlisted-note">Bạn có thể chia sẻ link này cho bất cứ ai.</p>
                            </div>
                            <button type="submit" name="idea_complete" class="btn-admin-done"><i class="fa-solid fa-check"></i> Complete</button>
                        </form>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="idea-author">Proposed by: <strong><?= htmlspecialchars($idea['username']) ?></strong> · <?= date('M d, Y', strtotime($idea['created_at'])) ?></div>
            <div class="idea-desc">
                <p style="margin-top:0;"><strong>Appearance:</strong> <?= nl2br(htmlspecialchars($idea['appearance'])) ?></p>
                <p style="margin-bottom:0;"><strong>Context:</strong> <?= nl2br(htmlspecialchars($idea['context'])) ?></p>
            </div>
        </div>

        <?php if ($idea['image_url']): ?>
        <div class="img-col">
            <img src="<?= htmlspecialchars($idea['image_url']) ?>" alt="Reference">
        </div>
        <?php endif; ?>
    </div>
    <?php
}
?>

<style>
    .ideas-header { display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px; margin-bottom: 20px; }
    .ideas-title { font-size: 28px; font-weight: bold; color: var(--pink-dark); margin: 0; }
    .btn-new-idea { background: var(--pink); color: white; padding: 10px 20px; border-radius: 20px; text-decoration: none; font-weight: bold; font-size: 15px; transition: 0.3s; box-shadow: 0 5px 15px rgba(255, 183, 197, 0.4); }
    .btn-new-idea:hover { background: var(--pink-dark); transform: translateY(-2px); }

    .ideas-intro {
        background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius);
        padding: 22px 24px; margin-bottom: 32px; box-shadow: var(--shadow-sm);
        color: var(--text-2); font-size: 14px; line-height: 1.7;
    }
    .ideas-intro ul { margin: 12px 0; padding-left: 20px; }
    .ideas-intro li { margin-bottom: 6px; }
    .ideas-intro strong { color: var(--text); }

    .section-heading { font-size: 20px; font-weight: 800; color: var(--text); margin: 0 0 18px; display: flex; align-items: center; gap: 10px; }
    .section-heading.completed { color: #7c3aed; }
    .completed-section { margin-bottom: 40px; }

    .completed-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; }
    .completed-card {
        background: var(--surface); border: 2px solid #c4b5fd; border-radius: var(--radius);
        overflow: hidden; box-shadow: var(--shadow-md); transition: all var(--transition);
    }
    .completed-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-lg); }
    .completed-card-top { padding: 14px 16px; border-bottom: 1px solid var(--border); background: linear-gradient(135deg, rgba(124,58,237,.08), rgba(255,183,197,.1)); }
    .completed-from { font-size: 12px; color: var(--text-3); margin-bottom: 4px; }
    .completed-idea-title { font-weight: 700; font-size: 15px; color: var(--text); margin: 0; }
    .completed-bot-area { padding: 16px; text-align: center; }
    .completed-bot-area img { width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 3px solid var(--pink); margin-bottom: 12px; }
    .completed-bot-name { font-size: 17px; font-weight: 800; color: var(--pink-dark); margin-bottom: 8px; }
    .vis-badge { display: inline-block; padding: 4px 10px; border-radius: 99px; font-size: 11px; font-weight: 800; text-transform: uppercase; }
    .vis-published { background: #dcfce7; color: #15803d; }
    .vis-unlisted { background: #fef3c7; color: #b45309; }

    .unlisted-modal-backdrop {
        display: none; position: fixed; inset: 0; background: rgba(0,0,0,.55);
        z-index: 500; align-items: center; justify-content: center; padding: 20px;
    }
    .unlisted-modal-backdrop.open { display: flex; }
    .unlisted-modal {
        background: var(--surface); border-radius: var(--radius); max-width: 440px; width: 100%;
        padding: 28px; box-shadow: var(--shadow-lg); border: 2px solid #fde68a;
        animation: slideUp .35s ease;
    }
    .unlisted-modal h3 { margin: 0 0 10px; color: var(--text); font-size: 20px; }
    .unlisted-modal > p { color: var(--text-2); font-size: 14px; line-height: 1.6; margin-bottom: 18px; }
    .unlisted-reward-item {
        background: var(--pink-soft); border: 1px solid var(--pink); border-radius: 12px;
        padding: 14px; margin-bottom: 12px;
    }
    .unlisted-reward-item strong { display: block; color: var(--text); margin-bottom: 8px; }
    .unlisted-link-box {
        display: flex; gap: 8px; align-items: center; flex-wrap: wrap;
    }
    .unlisted-link-box input {
        flex: 1; min-width: 0; padding: 8px 10px; border-radius: 8px; border: 1px solid var(--border);
        font-size: 12px; font-family: inherit; background: var(--surface); color: var(--text);
    }
    .btn-copy-link, .btn-open-unlisted {
        padding: 8px 14px; border-radius: 8px; font-size: 12px; font-weight: 700;
        cursor: pointer; font-family: inherit; border: none; text-decoration: none;
    }
    .btn-open-unlisted { background: var(--pink); color: white; }
    .btn-open-unlisted:hover { background: var(--pink-dark); }
    .btn-copy-link { background: var(--surface-2); color: var(--text-2); border: 1px solid var(--border); }
    .btn-modal-close {
        width: 100%; margin-top: 8px; padding: 11px; border-radius: 10px; border: 1px solid var(--border);
        background: var(--surface-2); font-weight: 700; cursor: pointer; font-family: inherit; color: var(--text-2);
    }
    .btn-view-bot { display: inline-block; margin-top: 12px; padding: 8px 18px; background: var(--pink); color: white; border-radius: 99px; font-weight: 700; font-size: 13px; text-decoration: none; }
    .btn-view-bot:hover { background: var(--pink-dark); }
    .completed-admin { padding: 0 16px 12px; text-align: center; }
    .btn-reopen { background: none; border: 1px solid var(--border); color: var(--text-3); padding: 5px 10px; border-radius: 8px; font-size: 11px; cursor: pointer; font-family: inherit; }

    .idea-card { background: var(--surface); padding: 25px; border-radius: 20px; margin-bottom: 25px; display: flex; gap: 25px; box-shadow: var(--shadow-md); border: 1px solid var(--border); transition: 0.3s; }
    .idea-card:hover { transform: translateY(-3px); border-color: var(--pink); }
    .idea-card-progress {
        border: 2px solid #86efac; background: linear-gradient(135deg, rgba(134,239,172,.12), var(--surface));
        box-shadow: 0 8px 24px rgba(34, 197, 94, 0.12);
    }
    .idea-card-progress:hover { border-color: #22c55e; }

    .status-badge { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 99px; font-size: 11px; font-weight: 800; text-transform: uppercase; margin-bottom: 8px; }
    .status-progress { background: #dcfce7; color: #15803d; border: 1px solid #86efac; }

    .vote-col { text-align: center; min-width: 60px; display: flex; flex-direction: column; align-items: center; justify-content: flex-start; }
    .btn-upvote { background: var(--pink-soft); border: 1px solid var(--pink); color: var(--pink-dark); width: 45px; height: 45px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.2s; font-size: 18px; margin-bottom: 8px; }
    .btn-upvote:hover { background: var(--pink); color: white; transform: scale(1.1); }
    .btn-upvote-disabled { opacity: 0.35; cursor: not-allowed; }
    .vote-count { font-size: 20px; font-weight: 800; color: var(--text-2); }

    .content-col { flex-grow: 1; min-width: 0; }
    .idea-title { color: var(--text); font-size: 20px; }
    .idea-author { font-size: 13px; color: var(--text-3); margin-bottom: 15px; }
    .idea-author strong { color: var(--pink-dark); }
    .idea-desc { background: var(--surface-2); border-left: 4px solid var(--pink); padding: 15px; border-radius: 8px; font-size: 14px; color: var(--text-2); line-height: 1.6; }
    .idea-title-row { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin-bottom: 5px; }
    .idea-owner-actions, .admin-idea-actions { display: flex; gap: 8px; flex-wrap: wrap; }
    .btn-idea-edit, .btn-idea-delete, .btn-admin-progress, .btn-admin-done {
        display: inline-flex; align-items: center; gap: 5px; padding: 6px 12px; border-radius: 8px;
        font-size: 12px; font-weight: 700; cursor: pointer; border: 1px solid; font-family: inherit; text-decoration: none;
    }
    .btn-idea-edit { background: var(--pink-soft); color: var(--pink-dark); border-color: var(--pink); }
    .btn-idea-delete { background: var(--surface-2); color: #ef4444; border-color: #fca5a5; }
    .btn-admin-progress { background: #dcfce7; color: #15803d; border-color: #86efac; }
    .btn-admin-done { background: #ede9fe; color: #6d28d9; border-color: #c4b5fd; }
    .complete-form { display: flex; flex-wrap: wrap; gap: 6px; align-items: flex-start; }
    .admin-select { padding: 6px 8px; border-radius: 8px; border: 1px solid var(--border); font-size: 12px; font-family: inherit; background: var(--surface); color: var(--text); max-width: 140px; }
    .complete-published-fields, .complete-unlisted-fields { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; }
    .complete-unlisted-fields { display: none; flex-direction: column; align-items: stretch; max-width: 280px; }
    .complete-unlisted-fields .admin-link-input { max-width: none; width: 100%; box-sizing: border-box; padding: 8px 10px; }
    .complete-unlisted-note { font-size: 11px; color: var(--text-3); line-height: 1.45; margin: 0; }
    .img-col img { width: 180px; height: 180px; border-radius: 12px; object-fit: cover; border: 1px solid var(--border); }
    .alert-error { background: #fef2f2; color: #ef4444; padding: 12px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #fca5a5; text-align: center; font-weight: 500; }
    .alert-success { background: #f0fdf4; color: #16a34a; padding: 12px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #bbf7d0; text-align: center; font-weight: 500; }

    @media (max-width: 640px) {
        .idea-card { flex-direction: column; }
        .img-col img { width: 100%; height: auto; max-height: 220px; }
    }
</style>

<div class="ideas-header">
    <h2 class="ideas-title">💡 Idea Board</h2>
    <a href="submit_idea.php" class="btn-new-idea"><i class="fa-solid fa-plus"></i> Submit New Idea</a>
</div>

<div class="ideas-intro animate-in">
    <?= ideasIntroHtml() ?>
</div>

<?php if ($flash): ?><div class="alert-success"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<?php if (!empty($my_unlisted_rewards)): ?>
    <div style="margin-bottom:20px;padding:12px 16px;background:#fffbeb;border:1px solid #fde68a;border-radius:10px;font-size:13px;color:#92400e;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
        <span><i class="fa-solid fa-link"></i> You have <?= count($my_unlisted_rewards) ?> unlisted bot link<?= count($my_unlisted_rewards) > 1 ? 's' : '' ?> from your ideas.</span>
        <button type="button" onclick="document.getElementById('unlistedModal')?.classList.add('open')" style="padding:6px 14px;border-radius:8px;border:none;background:#f59e0b;color:white;font-weight:700;cursor:pointer;font-family:inherit;font-size:12px;">View links</button>
    </div>
<?php endif; ?>

<?php if (!empty($completed_published)): ?>
<section class="completed-section">
    <h3 class="section-heading completed"><i class="fa-solid fa-circle-check"></i> Completed Bots from Ideas</h3>
    <div class="completed-grid">
        <?php foreach ($completed_published as $item):
            $bot = $item['bot'] ?? null;
        ?>
        <div class="completed-card animate-in">
            <div class="completed-card-top">
                <div class="completed-from">From idea by <?= htmlspecialchars($item['username']) ?></div>
                <p class="completed-idea-title"><?= htmlspecialchars($item['title']) ?></p>
            </div>
            <div class="completed-bot-area">
                <?php if ($bot): ?>
                    <img src="<?= htmlspecialchars($bot['image_url']) ?>" alt="<?= htmlspecialchars($bot['name']) ?>">
                    <div class="completed-bot-name"><?= htmlspecialchars($bot['name']) ?></div>
                <?php elseif (!empty($item['image_url'])): ?>
                    <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="<?= htmlspecialchars($item['title']) ?>" style="border-radius:12px;">
                    <div class="completed-bot-name"><?= htmlspecialchars($item['title']) ?></div>
                <?php else: ?>
                    <div class="completed-bot-name" style="color:var(--text-3);"><i class="fa-solid fa-check"></i> Completed</div>
                <?php endif; ?>
                <span class="vis-badge vis-published"><?= visibilityLabel('published') ?></span>
                <?php if ($bot): ?>
                    <br><a href="bot_detail.php?id=<?= (int)$bot['id'] ?>" class="btn-view-bot">View character</a>
                <?php endif; ?>
            </div>
            <?php if ($is_admin): ?>
            <div class="completed-admin">
                <form method="POST" onsubmit="return confirm('Move this idea back to the open board?');">
                    <input type="hidden" name="idea_id" value="<?= (int)$item['id'] ?>">
                    <button type="submit" name="idea_reopen" class="btn-reopen">Reopen idea</button>
                </form>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<h3 class="section-heading"><i class="fa-regular fa-lightbulb"></i> Open Ideas &amp; In Progress</h3>

<div class="ideas-list">
    <?php if (count($active_ideas) > 0): ?>
        <?php foreach ($active_ideas as $idea) {
            renderIdeaCard($idea, $current_user_id, $is_admin, $bots_list);
        } ?>
    <?php else: ?>
        <div style="text-align:center;padding:50px;background:var(--surface);border-radius:15px;box-shadow:var(--shadow-md);">
            <i class="fa-regular fa-lightbulb" style="font-size:40px;color:var(--text-3);margin-bottom:15px;display:block;"></i>
            <p style="color:var(--text-3);margin:0;font-size:15px;">No open ideas right now. Be the first to submit one!</p>
        </div>
    <?php endif; ?>
</div>

<?php if (!empty($my_unlisted_rewards)): ?>
<div id="unlistedModal" class="unlisted-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="unlistedModalTitle">
    <div class="unlisted-modal">
        <h3 id="unlistedModalTitle"><i class="fa-solid fa-gift" style="color:var(--pink-dark);"></i> Your idea is live!</h3>
        <p>Anyn completed your idea as an <strong>unlisted</strong> bot. It will not appear on the public store — you can share this link with anyone you like.</p>
        <?php foreach ($my_unlisted_rewards as $reward):
            $link = $reward['bot_link'];
            $is_external = str_starts_with($link, 'http');
        ?>
        <div class="unlisted-reward-item" data-idea-id="<?= (int)$reward['idea_id'] ?>">
            <strong><?= htmlspecialchars($reward['idea_title']) ?></strong>
            <div style="font-size:13px;color:var(--pink-dark);margin-bottom:8px;"><?= htmlspecialchars($reward['display_name']) ?></div>
            <div class="unlisted-link-box">
                <input type="text" readonly value="<?= htmlspecialchars($link) ?>" id="unlisted-link-<?= (int)$reward['idea_id'] ?>">
                <button type="button" class="btn-copy-link" onclick="copyUnlistedLink(<?= (int)$reward['idea_id'] ?>)"><i class="fa-regular fa-copy"></i> Copy</button>
                <a href="<?= htmlspecialchars($link) ?>" class="btn-open-unlisted" <?= $is_external ? 'target="_blank" rel="noopener noreferrer"' : '' ?>>
                    <i class="fa-solid fa-arrow-up-right-from-square"></i> Open bot
                </a>
            </div>
        </div>
        <?php endforeach; ?>
        <button type="button" class="btn-modal-close" onclick="closeUnlistedModal()">Got it</button>
    </div>
</div>
<script>
(function(){
    const modal = document.getElementById('unlistedModal');
    if (!modal) return;
    const items = modal.querySelectorAll('.unlisted-reward-item');
    let show = false;
    items.forEach(el => {
        const id = el.dataset.ideaId;
        if (!sessionStorage.getItem('unlisted_seen_' + id)) show = true;
    });
    if (show) modal.classList.add('open');
})();
function closeUnlistedModal() {
    document.querySelectorAll('.unlisted-reward-item').forEach(el => {
        sessionStorage.setItem('unlisted_seen_' + el.dataset.ideaId, '1');
    });
    document.getElementById('unlistedModal').classList.remove('open');
}
function copyUnlistedLink(id) {
    const input = document.getElementById('unlisted-link-' + id);
    input.select();
    input.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(input.value).catch(() => {});
}
document.getElementById('unlistedModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeUnlistedModal();
});
</script>
<?php endif; ?>

<script>
function toggleIdeaCompleteFields(selectEl) {
    const form = selectEl.closest('[data-complete-form]');
    if (!form) return;
    const isUnlisted = selectEl.value === 'unlisted';
    const pub = form.querySelector('[data-published-fields]');
    const unl = form.querySelector('[data-unlisted-fields]');
    const botSel = form.querySelector('.admin-bot-select');
    const linkInp = form.querySelector('input[name="unlisted_link"]');
    if (pub) pub.style.display = isUnlisted ? 'none' : 'flex';
    if (unl) unl.style.display = isUnlisted ? 'flex' : 'none';
    if (botSel) botSel.required = false;
    if (linkInp) linkInp.required = isUnlisted;
}
document.querySelectorAll('.admin-vis-select').forEach(function(sel) {
    toggleIdeaCompleteFields(sel);
});
</script>

<?php require_once '../includes/footer.php'; ?>
