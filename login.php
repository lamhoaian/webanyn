<?php
require_once '../config/database.php';
require_once '../includes/site_links.php';

$error = '';
$remembered_email = isset($_COOKIE['remember_email']) ? $_COOKIE['remember_email'] : '';

if (isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email    = trim($_POST['email']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']);

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id']  = $user['id'];
        $_SESSION['username'] = $user['username'];

        if ($remember) {
            setcookie('remember_email', $email, time() + (86400 * 30), "/");
        } else {
            setcookie('remember_email', '', time() - 3600, "/");
        }
        header("Location: ../index.php");
        exit();
    } else {
        $error = "Invalid email or password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
<?php require __DIR__ . '/../includes/theme_early.php'; ?>
    <title>Sign In — Anyn</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --pink:       #ffb7c5;
            --pink-dark:  #ff7f9e;
            --pink-soft:  #fff0f4;
            --bg:         #faf8f9;
            --surface:    #ffffff;
            --border:     #f0ecee;
            --text:       #2d2730;
            --text-2:     #7a6e72;
            --text-3:     #b0a8ac;
        }
        [data-theme="dark"] {
            --bg:         #131015;
            --surface:    #1d191f;
            --border:     #332d36;
            --text:       #f0eaed;
            --text-2:     #a099a3;
            --text-3:     #5c5560;
            --pink-soft:  #2a1d22;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            align-items: stretch;
        }

        /* Split layout */
        .auth-left {
            flex: 1;
            background: linear-gradient(160deg, #ff8fa3 0%, #ffb7c5 50%, #ffd6e0 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 60px 40px;
            position: relative;
            overflow: hidden;
        }

        .auth-left::before {
            content: '';
            position: absolute;
            inset: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.06'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }

        .auth-left-logo {
            font-family: 'Playfair Display', serif;
            font-size: 48px;
            font-weight: 700;
            color: white;
            margin-bottom: 16px;
            text-shadow: 0 4px 20px rgba(0,0,0,.15);
            position: relative;
        }

        .auth-left-tagline {
            color: rgba(255,255,255,.85);
            font-size: 16px;
            text-align: center;
            max-width: 280px;
            line-height: 1.65;
            position: relative;
        }

        .auth-features {
            display: flex;
            flex-direction: column;
            gap: 14px;
            margin-top: 48px;
            position: relative;
        }

        .auth-feature-item {
            display: flex;
            align-items: center;
            gap: 12px;
            color: rgba(255,255,255,.9);
            font-size: 14px;
            font-weight: 500;
        }

        .auth-feature-item i {
            width: 32px; height: 32px;
            background: rgba(255,255,255,.2);
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 15px;
            flex-shrink: 0;
        }

        .auth-char-img {
            position: absolute;
            bottom: 0; right: -20px;
            height: 70%;
            max-height: 480px;
            object-fit: contain;
            filter: drop-shadow(0 -10px 30px rgba(0,0,0,.15));
        }

        /* Right form panel */
        .auth-right {
            width: 480px;
            flex-shrink: 0;
            background: var(--surface);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 60px 50px;
        }

        .auth-form { width: 100%; max-width: 360px; }

        .auth-heading {
            font-family: 'Playfair Display', serif;
            font-size: 30px;
            color: var(--text);
            margin-bottom: 8px;
        }

        .auth-sub {
            color: var(--text-3);
            font-size: 14px;
            margin-bottom: 32px;
        }

        .form-group { margin-bottom: 18px; }

        .form-group label {
            display: block;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .5px;
            text-transform: uppercase;
            color: var(--text-2);
            margin-bottom: 7px;
        }

        .input-wrap { position: relative; }

        .input-wrap i.icon-l {
            position: absolute;
            left: 14px; top: 50%;
            transform: translateY(-50%);
            color: var(--text-3);
            font-size: 14px;
        }

        .input-wrap i.icon-r {
            position: absolute;
            right: 14px; top: 50%;
            transform: translateY(-50%);
            color: var(--text-3);
            font-size: 14px;
            cursor: pointer;
            transition: color .2s;
        }

        .input-wrap i.icon-r:hover { color: var(--text-2); }

        .input-wrap input {
            width: 100%;
            padding: 12px 40px;
            background: var(--bg);
            border: 1.5px solid var(--border);
            border-radius: 12px;
            font-size: 14px;
            font-family: inherit;
            color: var(--text);
            outline: none;
            transition: border-color .2s, box-shadow .2s;
        }

        .input-wrap input:focus {
            border-color: var(--pink-dark);
            box-shadow: 0 0 0 3px rgba(255,183,197,.2);
        }

        .options-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 13px;
            margin-bottom: 24px;
        }

        .options-row label {
            display: flex; align-items: center; gap: 7px;
            color: var(--text-2); cursor: pointer;
        }

        .options-row input[type=checkbox] { accent-color: var(--pink-dark); }
        .options-row a { color: var(--pink-dark); font-weight: 600; }

        .btn-submit {
            width: 100%;
            background: linear-gradient(135deg, var(--pink) 0%, var(--pink-dark) 100%);
            color: white;
            border: none;
            padding: 14px;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            box-shadow: 0 8px 24px rgba(255,127,158,.3);
            transition: all .2s;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 28px rgba(255,127,158,.4);
        }

        .divider {
            display: flex; align-items: center; gap: 12px;
            margin: 24px 0;
            color: var(--text-3);
            font-size: 12px;
        }

        .divider::before, .divider::after {
            content: ''; flex: 1;
            height: 1px; background: var(--border);
        }

        .social-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; margin-bottom: 24px; }

        .social-btn {
            border: 1.5px solid var(--border);
            background: var(--surface);
            padding: 10px 8px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            display: flex; align-items: center; justify-content: center; gap: 6px;
            color: var(--text-2);
            transition: all .2s;
        }

        .social-btn:hover { border-color: var(--pink); color: var(--text); background: var(--pink-soft); }

        .signup-row {
            text-align: center;
            font-size: 14px;
            color: var(--text-3);
        }

        .signup-row a { color: var(--pink-dark); font-weight: 700; }

        .error-box {
            background: #fef2f2;
            color: #dc2626;
            padding: 11px 15px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 20px;
            border: 1px solid #fca5a5;
            display: flex; align-items: center; gap: 8px;
        }

        .back-link {
            position: absolute;
            top: 28px; left: 36px;
            display: flex; align-items: center; gap: 8px;
            color: var(--text-2);
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            transition: color .2s;
        }

        .back-link:hover { color: var(--pink-dark); }

        @media (max-width: 860px) {
            .auth-left { display: none; }
            .auth-right { width: 100%; padding: 40px 24px; }
        }
    </style>
</head>
<body>
    <!-- Left decorative panel -->
    <div class="auth-left">
        <a href="../index.php" style="position:absolute;top:28px;left:32px;color:rgba(255,255,255,.75);font-size:13px;font-weight:600;text-decoration:none;display:flex;align-items:center;gap:6px;">
            <i class="fa-solid fa-arrow-left"></i> Back to Store
        </a>
        <div class="auth-left-logo">Anyn</div>
        <p class="auth-left-tagline">Your gateway to hundreds of unique roleplay characters.</p>
        <div class="auth-features">
            <div class="auth-feature-item"><i class="fa-solid fa-cat"></i> Unique Characters</div>
            <div class="auth-feature-item"><i class="fa-solid fa-book-open"></i> Immersive Stories</div>
            <div class="auth-feature-item"><i class="fa-solid fa-rotate"></i> Regular Updates</div>
            <div class="auth-feature-item"><i class="fa-regular fa-heart"></i> Made with Passion</div>
        </div>
       
    </div>

    <!-- Right form panel -->
    <div class="auth-right">
        <div class="auth-form">
            <h1 class="auth-heading">Welcome back 🌸</h1>
            <p class="auth-sub">Sign in to continue to Anyn</p>

            <?php if($error): ?>
                <div class="error-box"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>Email</label>
                    <div class="input-wrap">
                        <i class="fa-regular fa-envelope icon-l"></i>
                        <input type="email" name="email" placeholder="you@example.com"
                               value="<?= htmlspecialchars($remembered_email) ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <div class="input-wrap">
                        <i class="fa-solid fa-lock icon-l"></i>
                        <input type="password" name="password" id="pwInput" placeholder="Your password" required>
                        <i class="fa-regular fa-eye-slash icon-r" id="pwToggle"></i>
                    </div>
                </div>

                <div class="options-row">
                    <label>
                        <input type="checkbox" name="remember" <?= $remembered_email ? 'checked' : '' ?>>
                        Remember me
                    </label>
                    <a href="#" onclick="alert('Coming soon! Contact admin for support.'); return false;">Forgot password?</a>
                </div>

                <button type="submit" class="btn-submit">Sign In</button>
            </form>

            <div class="divider">or continue with</div>

            <div class="social-grid">
                <button class="social-btn" onclick="alert('Coming soon!');return false;">
                    <img src="https://upload.wikimedia.org/wikipedia/commons/c/c1/Google_%22G%22_logo.svg" width="14"> Google
                </button>
                <a href="<?= ANYN_DISCORD_URL ?>" class="social-btn" target="_blank" rel="noopener noreferrer" style="text-decoration:none;">
                    <i class="fa-brands fa-discord" style="color:#5865F2;font-size:15px;"></i> Discord
                </a>
                <button class="social-btn" onclick="alert('Coming soon!');return false;">
                    <i class="fa-brands fa-apple" style="font-size:17px;"></i> Apple
                </button>
            </div>

            <p class="signup-row">
                Don't have an account? <a href="register.php">Sign up free</a>
            </p>
        </div>
    </div>

    <script>
        document.getElementById('pwToggle').addEventListener('click', function(){
            const inp = document.getElementById('pwInput');
            const showing = inp.type === 'text';
            inp.type = showing ? 'password' : 'text';
            this.className = showing ? 'fa-regular fa-eye-slash icon-r' : 'fa-regular fa-eye icon-r';
        });
    </script>
</body>
</html>
