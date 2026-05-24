<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/site_links.php';

$nav_gems = 0;
if (isset($_SESSION['user_id']) && isset($pdo)) {
    require_once __DIR__ . '/gems_lib.php';
    ensureGemsSchema($pdo);
    $nav_gems = getUserGems($pdo, (int)$_SESSION['user_id']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
<?php require __DIR__ . '/theme_early.php'; ?>
    <title>ANYN — Bot Store</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <style>
        /* =============================================
           DESIGN TOKENS — Light & Dark via data-theme
        ============================================= */
        :root {
            --pink:        #ffb7c5;
            --pink-dark:   #ff7f9e;
            --pink-soft:   #fff0f4;
            --pink-border: #ffd6e0;

            --bg:          #faf8f9;
            --surface:     #ffffff;
            --surface-2:   #f6f3f4;
            --border:      #f0ecee;

            --text:        #2d2730;
            --text-2:      #7a6e72;
            --text-3:      #b0a8ac;

            --shadow-sm:   0 2px 8px rgba(180,130,140,.08);
            --shadow-md:   0 8px 24px rgba(180,130,140,.12);
            --shadow-lg:   0 20px 50px rgba(180,130,140,.18);

            --sidebar-w:   76px;
            --nav-h:       64px;
            --radius:      16px;
            --radius-sm:   10px;

            --transition:  .22s cubic-bezier(.4,0,.2,1);
        }

        [data-theme="dark"] {
            --bg:          #131015;
            --surface:     #1d191f;
            --surface-2:   #252028;
            --border:      #332d36;

            --text:        #f0eaed;
            --text-2:      #a099a3;
            --text-3:      #5c5560;

            --pink-soft:   #2a1d22;
            --shadow-sm:   0 2px 8px rgba(0,0,0,.3);
            --shadow-md:   0 8px 24px rgba(0,0,0,.45);
            --shadow-lg:   0 20px 50px rgba(0,0,0,.6);
        }

        /* =============================================
           RESET & BASE
        ============================================= */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        html { scroll-behavior: smooth; }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            transition: background var(--transition), color var(--transition);
        }

        a { color: inherit; text-decoration: none; }
        button { font-family: inherit; }
        img { display: block; max-width: 100%; }

        /* Custom scrollbar */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: var(--pink-border); border-radius: 99px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--pink); }

        /* =============================================
           SIDEBAR
        ============================================= */
        .sidebar {
            position: fixed;
            left: 0; top: 0;
            width: var(--sidebar-w);
            height: 100%;
            background: var(--surface);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20px 0;
            gap: 4px;
            z-index: 200;
            transition: background var(--transition), border-color var(--transition);
        }

        .sidebar-logo {
            font-family: 'Playfair Display', serif;
            font-size: 20px;
            font-weight: 700;
            color: var(--pink-dark);
            margin-bottom: 24px;
            letter-spacing: -.5px;
        }

        .sidebar-link {
            position: relative;
            width: 46px; height: 46px;
            display: flex; align-items: center; justify-content: center;
            border-radius: var(--radius-sm);
            color: var(--text-2);
            transition: all var(--transition);
        }

        .sidebar-link:hover,
        .sidebar-link.active {
            background: var(--pink-soft);
            color: var(--pink-dark);
        }

        .sidebar-link i { font-size: 19px; }

        /* Tooltip */
        .sidebar-link::after {
            content: attr(data-tip);
            position: absolute;
            left: calc(100% + 12px);
            top: 50%;
            transform: translateY(-50%);
            background: var(--text);
            color: var(--bg);
            padding: 5px 10px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
            pointer-events: none;
            opacity: 0;
            transition: opacity var(--transition);
        }

        .sidebar-link:hover::after { opacity: 1; }

        .sidebar-bottom {
            margin-top: auto;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
        }

        .sidebar-link-discord:hover {
            background: rgba(88, 101, 242, 0.14);
            color: #5865F2;
        }

        .site-footer {
            margin: 48px 0 28px;
            padding-top: 22px;
            border-top: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-wrap: wrap;
            gap: 12px 20px;
        }

        .site-footer a {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--text-3);
            font-size: 13px;
            font-weight: 600;
            transition: color var(--transition);
        }

        .site-footer a:hover { color: #5865F2; }

        .site-footer .discord-pill {
            padding: 8px 14px;
            border-radius: 99px;
            border: 1px solid var(--border);
            background: var(--surface-2);
        }

        .site-footer .discord-pill:hover {
            border-color: #5865F2;
            background: rgba(88, 101, 242, 0.1);
        }

        /* =============================================
           TOP NAVBAR
        ============================================= */
        .top-navbar {
            position: sticky; top: 0;
            height: var(--nav-h);
            margin-left: var(--sidebar-w);
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            padding: 0 32px;
            display: flex;
            align-items: center;
            gap: 16px;
            z-index: 100;
            transition: background var(--transition), border-color var(--transition);
        }

        /* =============================================
           SEARCH
        ============================================= */
        .search-wrapper {
            position: relative;
            flex: 1;
            max-width: 340px;
        }

        .search-bar {
            display: flex;
            align-items: center;
            gap: 8px;
            background: var(--surface-2);
            border: 1.5px solid var(--border);
            border-radius: 99px;
            padding: 0 16px;
            height: 40px;
            transition: border-color var(--transition), box-shadow var(--transition);
        }

        .search-bar:focus-within {
            border-color: var(--pink);
            box-shadow: 0 0 0 3px rgba(255,183,197,.2);
        }

        .search-bar input {
            border: none;
            background: transparent;
            outline: none;
            font-family: inherit;
            font-size: 14px;
            color: var(--text);
            width: 100%;
        }

        .search-bar input::placeholder { color: var(--text-3); }

        .search-bar button {
            background: none;
            border: none;
            cursor: pointer;
            color: var(--text-3);
            padding: 0;
            display: flex;
            align-items: center;
            font-size: 14px;
        }

        /* Suggestions dropdown */
        .suggestions-box {
            position: absolute;
            top: calc(100% + 8px);
            left: 0; right: 0;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
            overflow: hidden;
            display: none;
            z-index: 300;
        }

        .suggestion-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 11px 16px;
            color: var(--text);
            transition: background var(--transition);
            border-bottom: 1px solid var(--border);
            cursor: pointer;
        }

        .suggestion-item:last-child { border-bottom: none; }

        .suggestion-item:hover,
        .suggestion-item.focused {
            background: var(--pink-soft);
        }

        .suggestion-img {
            width: 38px; height: 38px;
            border-radius: 50%;
            object-fit: cover;
            border: 1.5px solid var(--border);
            flex-shrink: 0;
        }

        .suggestion-name { font-weight: 600; font-size: 13px; }

        .suggestion-empty {
            padding: 16px;
            text-align: center;
            color: var(--text-3);
            font-size: 13px;
        }

        /* =============================================
           USER MENU
        ============================================= */
        .user-menu {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-pink {
            background: var(--pink);
            color: #fff;
            padding: 8px 20px;
            border-radius: 99px;
            font-weight: 700;
            font-size: 13px;
            border: none;
            cursor: pointer;
            transition: all var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-pink:hover {
            background: var(--pink-dark);
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(255,127,158,.3);
        }

        .btn-pink.admin-btn {
            background: var(--pink-dark);
        }

        .profile-pill {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px 14px;
            border-radius: 99px;
            border: 1.5px solid var(--border);
            font-size: 13px;
            font-weight: 600;
            color: var(--text-2);
            transition: all var(--transition);
        }

        .profile-pill:hover {
            border-color: var(--pink);
            color: var(--pink-dark);
            background: var(--pink-soft);
        }

        .logout-link {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-3);
            padding: 6px 10px;
            border-radius: 8px;
            transition: all var(--transition);
        }

        .logout-link:hover { color: var(--text-2); background: var(--surface-2); }

        /* =============================================
           DARK MODE TOGGLE
        ============================================= */
        .theme-btn {
            width: 40px; height: 40px;
            border-radius: 99px;
            border: 1.5px solid var(--border);
            background: var(--surface-2);
            color: var(--text-2);
            font-size: 16px;
            cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            transition: all var(--transition);
            flex-shrink: 0;
        }

        .theme-btn:hover {
            background: var(--pink-soft);
            border-color: var(--pink);
            color: var(--pink-dark);
        }

        /* =============================================
           MOBILE HAMBURGER
        ============================================= */
        .hamburger {
            display: none;
            width: 40px; height: 40px;
            border: 1.5px solid var(--border);
            background: var(--surface-2);
            border-radius: var(--radius-sm);
            cursor: pointer;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 5px;
            transition: all var(--transition);
        }

        .hamburger span {
            width: 18px; height: 2px;
            background: var(--text-2);
            border-radius: 2px;
            transition: all var(--transition);
        }

        .hamburger:hover { background: var(--pink-soft); border-color: var(--pink); }

        .mobile-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,.45);
            z-index: 190;
            backdrop-filter: blur(2px);
        }

        /* =============================================
           PAGE CONTAINER
        ============================================= */
        .container {
            margin-left: var(--sidebar-w);
            padding: 36px 40px;
            max-width: calc(1280px + var(--sidebar-w));
        }

        /* =============================================
           REUSABLE COMPONENTS
        ============================================= */
        .section-title {
            font-family: 'Playfair Display', serif;
            font-size: 24px;
            color: var(--text);
            margin-bottom: 28px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .section-title::before {
            content: '';
            width: 4px; height: 28px;
            background: var(--pink);
            border-radius: 2px;
            flex-shrink: 0;
        }

        /* Bot Cards */
        .bot-card {
            background: var(--surface);
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            border: 1.5px solid var(--border);
            transition: all var(--transition);
            cursor: pointer;
        }

        .bot-card:hover {
            transform: translateY(-6px);
            box-shadow: var(--shadow-lg);
            border-color: var(--pink);
        }

        .bot-card img {
            width: 100%;
            aspect-ratio: 3/4;
            object-fit: cover;
            display: block;
        }

        .bot-card-body {
            padding: 14px 16px 16px;
        }

        .bot-title {
            font-weight: 700;
            font-size: 15px;
            color: var(--text);
            margin-bottom: 8px;
        }

        /* Tag Pills */
        .tag-pill {
            display: inline-flex;
            align-items: center;
            font-size: 10px;
            font-weight: 700;
            background: var(--pink-soft);
            color: var(--pink-dark);
            padding: 2px 9px;
            border-radius: 99px;
            border: 1px solid var(--pink-border);
            letter-spacing: .3px;
        }

        .filter-pill {
            padding: 7px 18px;
            background: var(--surface);
            color: var(--text-2);
            border-radius: 99px;
            font-weight: 600;
            font-size: 13px;
            border: 1.5px solid var(--border);
            text-decoration: none;
            transition: all var(--transition);
            white-space: nowrap;
        }

        .filter-pill:hover,
        .filter-pill.active {
            background: var(--pink);
            color: #fff;
            border-color: var(--pink);
        }

        /* Rating stars */
        .star-rating { color: #f4a22d; font-size: 14px; font-weight: 700; }

        /* Badges */
        .badge-featured {
            position: absolute;
            top: 10px; left: 10px;
            background: var(--pink-dark);
            color: #fff;
            padding: 3px 10px;
            border-radius: 6px;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .5px;
        }

        /* Form elements */
        .form-input,
        .form-select,
        .form-textarea {
            width: 100%;
            padding: 11px 14px;
            background: var(--surface-2);
            border: 1.5px solid var(--border);
            border-radius: var(--radius-sm);
            font-family: inherit;
            font-size: 14px;
            color: var(--text);
            outline: none;
            transition: border-color var(--transition), box-shadow var(--transition);
        }

        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus {
            border-color: var(--pink);
            box-shadow: 0 0 0 3px rgba(255,183,197,.18);
        }

        .form-textarea { resize: vertical; min-height: 90px; }

        /* Cards (generic white card) */
        .card {
            background: var(--surface);
            border: 1.5px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow-sm);
            transition: background var(--transition), border-color var(--transition);
        }

        /* Comment frames */
        .comment-wrapper { display: flex; gap: 14px; }

        .comment-avatar {
            width: 44px; height: 44px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--border);
            flex-shrink: 0;
        }

        .comment-avatar.premium {
            border-color: var(--pink);
            box-shadow: 0 0 10px rgba(255,127,158,.35);
        }

        .frame-normal {
            background: var(--surface-2);
            padding: 14px 18px;
            border-radius: 12px;
            border-left: 3px solid var(--pink);
            flex-grow: 1;
        }

        .frame-bloom {
            background: #160f13;
            padding: 14px 18px;
            border-radius: 12px;
            border: 1px solid var(--pink-dark);
            box-shadow: 0 0 18px rgba(255,127,158,.2), inset 0 0 12px rgba(255,127,158,.07);
            flex-grow: 1;
            position: relative;
            overflow: hidden;
        }

        .frame-bloom::after {
            content: "🌸";
            position: absolute;
            right: 10px; bottom: 4px;
            font-size: 22px;
            opacity: .5;
        }

        .comment-author { font-weight: 700; font-size: 14px; color: var(--text); }
        .frame-bloom .comment-author { color: var(--pink); }
        .comment-text { font-size: 13px; color: var(--text-2); line-height: 1.65; margin-top: 5px; }
        .frame-bloom .comment-text { color: #ccc; }
        .comment-time { font-size: 11px; color: var(--text-3); }
        .user-rate-badge { color: #f4a22d; font-size: 12px; font-weight: 600; margin-left: 8px; }

        /* Tables */
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th {
            background: var(--surface-2);
            color: var(--text-2);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .5px;
            text-transform: uppercase;
            padding: 12px 16px;
            text-align: left;
            border-bottom: 2px solid var(--border);
        }
        .data-table td {
            padding: 14px 16px;
            border-bottom: 1px solid var(--border);
            font-size: 14px;
            color: var(--text);
        }
        .data-table tr:hover td { background: var(--surface-2); }

        /* Toast notifications */
        .toast {
            position: fixed;
            bottom: 80px; right: 24px;
            background: var(--text);
            color: var(--bg);
            padding: 12px 20px;
            border-radius: var(--radius-sm);
            font-size: 13px;
            font-weight: 600;
            box-shadow: var(--shadow-md);
            z-index: 9999;
            transform: translateY(20px);
            opacity: 0;
            transition: all .3s;
            pointer-events: none;
        }

        .toast.show { transform: translateY(0); opacity: 1; }

        /* Gem shop — chat & avatar cosmetics */
        .msg-avatar.gem-avt-avt_gold { border-color: #f59e0b !important; box-shadow: 0 0 12px rgba(245, 158, 11, 0.55); }
        .msg-avatar.gem-avt-avt_crystal { border-color: #38bdf8 !important; box-shadow: 0 0 12px rgba(56, 189, 248, 0.5); }
        .msg-avatar.gem-avt-avt_bloom { border-color: #f472b6 !important; box-shadow: 0 0 12px rgba(244, 114, 182, 0.5); }
        .profile-avatar.gem-avt-avt_gold { border-color: #f59e0b !important; box-shadow: 0 0 16px rgba(245, 158, 11, 0.45); }
        .profile-avatar.gem-avt-avt_crystal { border-color: #38bdf8 !important; box-shadow: 0 0 16px rgba(56, 189, 248, 0.45); }
        .profile-avatar.gem-avt-avt_bloom { border-color: #f472b6 !important; box-shadow: 0 0 16px rgba(244, 114, 182, 0.45); }
        .msg-bubble.gem-chat-chat_sakura { border: 2px solid #ffb7c5 !important; box-shadow: 0 0 14px rgba(255, 183, 197, 0.35); }
        .msg-bubble.gem-chat-chat_neon { border: 2px solid #22d3ee !important; box-shadow: 0 0 14px rgba(34, 211, 238, 0.35); }
        .msg-wrapper.my-msg .msg-bubble.gem-chat-chat_neon { background: linear-gradient(135deg, #ec4899, #8b5cf6) !important; color: white !important; }
        .msg-bubble.gem-chat-chat_royal { border: 2px solid #f59e0b !important; box-shadow: 0 0 14px rgba(245, 158, 11, 0.3); }
        .msg-bubble.gem-chat-chat_stardust { border: 2px solid #c4b5fd !important; }
        .msg-wrapper.my-msg .msg-bubble.gem-chat-chat_stardust { background: linear-gradient(135deg, #a855f7, #ec4899) !important; color: white !important; }
        .nav-gems-pill {
            display: inline-flex; align-items: center; gap: 5px; padding: 6px 12px;
            background: linear-gradient(135deg, #f3e8ff, #fce7f3); border: 1px solid #e9d5ff;
            border-radius: 99px; font-size: 12px; font-weight: 800; color: #7c3aed; text-decoration: none;
        }
        .nav-gems-pill:hover { border-color: #a855f7; color: #6d28d9; }

        /* =============================================
           RESPONSIVE — MOBILE
        ============================================= */
        @media (max-width: 768px) {
            :root { --sidebar-w: 0px; }

            .sidebar {
                transform: translateX(-100%);
                transition: transform var(--transition);
                width: 220px;
                padding: 24px 16px;
                align-items: flex-start;
            }

            .sidebar.open { transform: translateX(0); }

            .sidebar-link {
                width: 100%; height: auto;
                padding: 10px 12px;
                border-radius: var(--radius-sm);
                gap: 12px;
                font-size: 14px;
                font-weight: 600;
            }

            .sidebar-link::after { display: none; }

            .sidebar-link-label { display: block; }

            .mobile-overlay.show { display: block; }

            .hamburger { display: flex; }

            .top-navbar { margin-left: 0; padding: 0 16px; gap: 10px; }

            .search-wrapper { max-width: none; flex: 1; }

            .container { padding: 20px 16px; }
        }

        @media (min-width: 769px) {
            .sidebar-link-label { display: none; }
            .hamburger { display: none; }
        }

        /* =============================================
           PAGE LOAD ANIMATION
        ============================================= */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .animate-in { animation: fadeUp .4s ease both; }

        .animate-in:nth-child(1) { animation-delay: .05s; }
        .animate-in:nth-child(2) { animation-delay: .10s; }
        .animate-in:nth-child(3) { animation-delay: .15s; }
        .animate-in:nth-child(4) { animation-delay: .20s; }
        .animate-in:nth-child(5) { animation-delay: .25s; }
        .animate-in:nth-child(6) { animation-delay: .30s; }

        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px) scale(.97); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }
    </style>
</head>
<body>

<!-- ===== SIDEBAR ===== -->
<div class="sidebar" id="sidebar">
    <span class="sidebar-logo">A</span>

    <a href="/anyn/index.php" class="sidebar-link" data-tip="Home">
        <i class="fa-solid fa-house"></i>
        <span class="sidebar-link-label">Home</span>
    </a>
    <a href="/anyn/pages/ideas.php" class="sidebar-link" data-tip="Ideas">
        <i class="fa-regular fa-lightbulb"></i>
        <span class="sidebar-link-label">Ideas</span>
    </a>
    <a href="/anyn/pages/commission.php" class="sidebar-link" data-tip="Commissions">
        <i class="fa-solid fa-cart-shopping"></i>
        <span class="sidebar-link-label">Commissions</span>
    </a>
    <a href="/anyn/pages/gallery.php" class="sidebar-link" data-tip="Official Gallery">
        <i class="fa-regular fa-image"></i>
        <span class="sidebar-link-label">Gallery</span>
    </a>
    <a href="/anyn/pages/community_gallery.php" class="sidebar-link" data-tip="Community Gallery">
        <i class="fa-solid fa-users-viewfinder"></i>
        <span class="sidebar-link-label">Community</span>
    </a>
    <a href="/anyn/pages/chat.php" class="sidebar-link" data-tip="Global Chat">
        <i class="fa-regular fa-comments"></i>
        <span class="sidebar-link-label">Global Chat</span>
    </a>
    <?php if (isset($_SESSION['user_id'])): ?>
    <a href="/anyn/pages/shop.php" class="sidebar-link" data-tip="Gem Shop">
        <i class="fa-solid fa-gem"></i>
        <span class="sidebar-link-label">Shop</span>
    </a>
    <?php endif; ?>

    <div class="sidebar-bottom">
        <a href="<?= ANYN_DISCORD_URL ?>" class="sidebar-link sidebar-link-discord" data-tip="Discord Community" target="_blank" rel="noopener noreferrer">
            <i class="fa-brands fa-discord"></i>
            <span class="sidebar-link-label">Discord</span>
        </a>
        <?php if(isset($_SESSION['user_id']) && $_SESSION['user_id'] == 1): ?>
        <a href="/anyn/pages/admin_community.php" class="sidebar-link" data-tip="Moderate Community">
            <i class="fa-solid fa-gavel"></i>
        </a>
        <a href="/anyn/pages/admin_bots.php" class="sidebar-link" data-tip="Admin">
            <i class="fa-solid fa-screwdriver-wrench"></i>
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Mobile overlay -->
<div class="mobile-overlay" id="mobileOverlay" onclick="closeSidebar()"></div>

<!-- ===== TOP NAVBAR ===== -->
<nav class="top-navbar">
    <button class="hamburger" id="hamburger" onclick="openSidebar()" aria-label="Open menu">
        <span></span><span></span><span></span>
    </button>

    <a href="/anyn/index.php" style="font-family:'Playfair Display',serif; font-size:20px; font-weight:700; color:var(--pink-dark); flex-shrink:0;">ANYN</a>

    <!-- Search -->
    <div class="search-wrapper">
        <form action="/anyn/index.php" method="GET" class="search-bar">
            <i class="fa-solid fa-magnifying-glass" style="color:var(--text-3); font-size:13px;"></i>
            <input type="text" name="search" id="searchInput"
                   placeholder="Search characters..."
                   autocomplete="off"
                   value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
            <button type="submit" aria-label="Search"></button>
        </form>
        <div id="searchSuggestions" class="suggestions-box" role="listbox"></div>
    </div>

    <!-- User menu -->
    <div class="user-menu">
        <button class="theme-btn" id="themeBtn" onclick="toggleTheme()" aria-label="Toggle dark mode">
            <i class="fa-solid fa-moon" id="themeIcon"></i>
        </button>

        <?php if(isset($_SESSION['user_id'])): ?>
            <a href="/anyn/pages/shop.php" class="nav-gems-pill" title="Gem Shop">
                <i class="fa-solid fa-gem"></i> <?= number_format($nav_gems) ?>
            </a>
            <a href="/anyn/pages/profile.php" class="profile-pill">
                <i class="fa-regular fa-circle-user"></i>
                <?= htmlspecialchars($_SESSION['username']) ?>
            </a>
            <?php if($_SESSION['user_id'] == 1): ?>
                <a href="/anyn/pages/admin_bots.php" class="btn-pink admin-btn" style="display:none;" aria-hidden="true">Admin</a>
            <?php endif; ?>
            <a href="/anyn/pages/logout.php" class="logout-link">Logout</a>
        <?php else: ?>
            <a href="/anyn/pages/login.php" class="btn-pink">
                <i class="fa-regular fa-user"></i> Sign In
            </a>
        <?php endif; ?>
    </div>
</nav>

<!-- Toast -->
<div class="toast" id="toast"></div>

<!-- ===== PAGE CONTENT START ===== -->
<div class="container">

<script>
// ===== THEME (icon sync; theme applied in <head> via theme_early.php) =====
(function(){
    const saved = localStorage.getItem('anyn_theme') || 'light';
    const icon = document.getElementById('themeIcon');
    if(saved === 'dark' && icon) {
        icon.className = 'fa-solid fa-sun';
    }
})();

function toggleTheme() {
    const html = document.documentElement;
    const isDark = html.getAttribute('data-theme') === 'dark';
    const next = isDark ? 'light' : 'dark';
    html.setAttribute('data-theme', next);
    localStorage.setItem('anyn_theme', next);
    const icon = document.getElementById('themeIcon');
    icon.className = isDark ? 'fa-solid fa-moon' : 'fa-solid fa-sun';
}

// ===== MOBILE SIDEBAR =====
function openSidebar() {
    document.getElementById('sidebar').classList.add('open');
    document.getElementById('mobileOverlay').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeSidebar() {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('mobileOverlay').classList.remove('show');
    document.body.style.overflow = '';
}

// Active sidebar link
(function(){
    const links = document.querySelectorAll('.sidebar-link');
    links.forEach(link => {
        if(link.href && location.pathname.startsWith(link.getAttribute('href').split('?')[0])) {
            link.classList.add('active');
        }
    });
})();

// ===== SEARCH SUGGESTIONS =====
document.addEventListener('DOMContentLoaded', function(){
    const input = document.getElementById('searchInput');
    const box   = document.getElementById('searchSuggestions');
    if(!input || !box) return;

    let focusIndex = -1;
    let debounceTimer;

    function updateFocus(items) {
        items.forEach((el, i) => el.classList.toggle('focused', i === focusIndex));
    }

    input.addEventListener('input', function(){
        clearTimeout(debounceTimer);
        focusIndex = -1;
        const q = this.value.trim();
        if(!q) { box.style.display='none'; return; }

        debounceTimer = setTimeout(() => {
            fetch(`/anyn/search_suggestions.php?q=${encodeURIComponent(q)}`)
                .then(r => r.json())
                .then(data => {
                    box.innerHTML = '';
                    if(data.length === 0) {
                        box.innerHTML = '<div class="suggestion-empty">No characters found</div>';
                    } else {
                        data.forEach(bot => {
                            const a = document.createElement('a');
                            a.href = `/anyn/pages/bot_detail.php?id=${bot.id}`;
                            a.className = 'suggestion-item';
                            a.setAttribute('role','option');
                            const img = bot.image_url ? bot.image_url : 'https://via.placeholder.com/38';
                            a.innerHTML = `<img src="${img}" class="suggestion-img" alt=""><span class="suggestion-name">${bot.name}</span>`;
                            box.appendChild(a);
                        });
                    }
                    box.style.display = 'block';
                });
        }, 180);
    });

    // Keyboard navigation
    input.addEventListener('keydown', function(e){
        const items = box.querySelectorAll('.suggestion-item');
        if(!items.length) return;
        if(e.key === 'ArrowDown') {
            e.preventDefault();
            focusIndex = Math.min(focusIndex+1, items.length-1);
            updateFocus(items);
        } else if(e.key === 'ArrowUp') {
            e.preventDefault();
            focusIndex = Math.max(focusIndex-1, -1);
            updateFocus(items);
        } else if(e.key === 'Enter' && focusIndex >= 0) {
            e.preventDefault();
            items[focusIndex].click();
        } else if(e.key === 'Escape') {
            box.style.display = 'none';
            focusIndex = -1;
        }
    });

    document.addEventListener('click', e => {
        if(!input.contains(e.target) && !box.contains(e.target)) box.style.display = 'none';
    });
});

// ===== TOAST =====
function showToast(msg, duration=3000) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), duration);
}
</script>
