<?php

function ensureIdeasSchema(PDO $pdo): void
{
    if (!$pdo->query("SHOW COLUMNS FROM ideas LIKE 'work_status'")->fetch()) {
        $pdo->exec("ALTER TABLE ideas ADD COLUMN work_status ENUM('open','in_progress','completed') NOT NULL DEFAULT 'open' AFTER upvotes");
    }
    if (!$pdo->query("SHOW COLUMNS FROM ideas LIKE 'bot_id'")->fetch()) {
        $pdo->exec('ALTER TABLE ideas ADD COLUMN bot_id INT(11) NULL AFTER work_status');
    }
    if (!$pdo->query("SHOW COLUMNS FROM ideas LIKE 'bot_visibility'")->fetch()) {
        $pdo->exec("ALTER TABLE ideas ADD COLUMN bot_visibility ENUM('published','unlisted','private') NULL AFTER bot_id");
    }
    if (!$pdo->query("SHOW COLUMNS FROM ideas LIKE 'completed_at'")->fetch()) {
        $pdo->exec('ALTER TABLE ideas ADD COLUMN completed_at TIMESTAMP NULL DEFAULT NULL AFTER bot_visibility');
    }
    try {
        $pdo->exec("ALTER TABLE ideas MODIFY COLUMN bot_visibility ENUM('published','unlisted') NULL");
    } catch (PDOException $e) {
        // Column already matches target enum
    }
    if (!$pdo->query("SHOW COLUMNS FROM ideas LIKE 'unlisted_link'")->fetch()) {
        $pdo->exec('ALTER TABLE ideas ADD COLUMN unlisted_link VARCHAR(500) NULL AFTER bot_visibility');
    }
}

function ideaShareLink(array $idea, ?array $bot = null): string
{
    $custom = trim($idea['unlisted_link'] ?? '');
    if ($custom !== '') {
        return $custom;
    }
    if ($bot) {
        return botPlayLink($bot);
    }
    return '';
}

function botPlayLink(array $bot): string
{
    $url = trim($bot['rp_platform_url'] ?? '');
    if ($url !== '') {
        return $url;
    }
    return '/anyn/pages/bot_detail.php?id=' . (int)$bot['id'];
}

function isIdeasAdmin(): bool
{
    return isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === 1;
}

function ideasIntroHtml(): string
{
    return '
    <p>This is where <strong>anyone</strong> can share character concepts they want <strong>Anyn</strong> to bring to life — completely <strong>free</strong>, no payment required.</p>
    <ul>
        <li><strong>Submit</strong> your idea with appearance, scenario, and an optional reference image.</li>
        <li><strong>Upvote</strong> ideas you like (sign in required) — popular concepts rise to the top.</li>
        <li>Over time, Anyn may pick the <strong>highest-voted</strong> ideas, or ones he personally loves, to develop into bots.</li>
        <li>Anyn decides how each bot is released: <strong>published</strong> on the store, or <strong>unlisted</strong> (exclusive link sent to the idea author).</li>
    </ul>
    <p style="margin-bottom:0;font-size:13px;color:var(--text-3);">Ideas marked <span style="color:#16a34a;font-weight:700;">In Progress</span> are actively being worked on. Finished concepts appear in <strong>Completed Bots</strong> below.</p>';
}

function visibilityLabel(?string $v): string
{
    return match ($v) {
        'published' => 'Published',
        'unlisted'  => 'Unlisted',
        default     => '',
    };
}
