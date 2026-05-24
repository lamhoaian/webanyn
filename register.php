<?php
require_once '../config/database.php';
require_once '../includes/site_links.php';

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $email    = trim($_POST['email']);
    $password = $_POST['password'];

    if(strlen($username) < 3) {
        $error = "Username must be at least 3 characters.";
    } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif(strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } else {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        try {
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)");
            $stmt->execute([$username, $email, $hashed]);
            $success = "Account created! You can now sign in.";
        } catch(PDOException $e) {
            $error = "Username or Email already in use.";
        }
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
    <title>Create Account — Anyn</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --pink: #ffb7c5; --pink-dark: #ff7f9e; --pink-soft: #fff0f4;
            --bg: #faf8f9; --surface: #ffffff; --border: #f0ecee;
            --text: #2d2730; --text-2: #7a6e72; --text-3: #b0a8ac;
        }
        [data-theme="dark"] {
            --bg: #131015; --surface: #1d191f; --border: #332d36;
            --text: #f0eaed; --text-2: #a099a3; --text-3: #5c5560; --pink-soft: #2a1d22;
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; display: flex; align-items: stretch; }

        .auth-left {
            flex: 1;
            background: linear-gradient(160deg, #c9a0dc 0%, #ffb7c5 60%, #ffd6e0 100%);
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            padding: 60px 40px; position: relative; overflow: hidden;
        }
        .auth-left::before {
            content: ''; position: absolute; inset: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.06'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }
        .auth-left-logo { font-family: 'Playfair Display', serif; font-size: 48px; font-weight: 700; color: white; margin-bottom: 16px; text-shadow: 0 4px 20px rgba(0,0,0,.15); position: relative; }
        .auth-left-tagline { color: rgba(255,255,255,.85); font-size: 16px; text-align: center; max-width: 280px; line-height: 1.65; position: relative; }
        .auth-char-img { position: absolute; bottom: 0; right: -20px; height: 65%; max-height: 440px; object-fit: contain; filter: drop-shadow(0 -10px 30px rgba(0,0,0,.15)); }

        .auth-right { width: 480px; flex-shrink: 0; background: var(--surface); display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 60px 50px; }
        .auth-form { width: 100%; max-width: 360px; }
        .auth-heading { font-family: 'Playfair Display', serif; font-size: 30px; color: var(--text); margin-bottom: 8px; }
        .auth-sub { color: var(--text-3); font-size: 14px; margin-bottom: 32px; }

        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-size: 12px; font-weight: 700; letter-spacing: .5px; text-transform: uppercase; color: var(--text-2); margin-bottom: 7px; }
        .input-wrap { position: relative; }
        .input-wrap i.icon-l { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--text-3); font-size: 14px; }
        .input-wrap i.icon-r { position: absolute; right: 14px; top: 50%; transform: translateY(-50%); color: var(--text-3); font-size: 14px; cursor: pointer; transition: color .2s; }
        .input-wrap input { width: 100%; padding: 12px 40px; background: var(--bg); border: 1.5px solid var(--border); border-radius: 12px; font-size: 14px; font-family: inherit; color: var(--text); outline: none; transition: border-color .2s, box-shadow .2s; }
        .input-wrap input:focus { border-color: var(--pink-dark); box-shadow: 0 0 0 3px rgba(255,183,197,.2); }

        .btn-submit { width: 100%; background: linear-gradient(135deg, var(--pink) 0%, var(--pink-dark) 100%); color: white; border: none; padding: 14px; border-radius: 12px; font-size: 15px; font-weight: 700; font-family: inherit; cursor: pointer; box-shadow: 0 8px 24px rgba(255,127,158,.3); transition: all .2s; }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 12px 28px rgba(255,127,158,.4); }

        .signin-row { text-align: center; font-size: 14px; color: var(--text-3); margin-top: 24px; }
        .signin-row a { color: var(--pink-dark); font-weight: 700; }

        .error-box { background: #fef2f2; color: #dc2626; padding: 11px 15px; border-radius: 10px; font-size: 13px; font-weight: 500; margin-bottom: 20px; border: 1px solid #fca5a5; display: flex; align-items: center; gap: 8px; }
        .success-box { background: #f0fdf4; color: #16a34a; padding: 11px 15px; border-radius: 10px; font-size: 13px; font-weight: 500; margin-bottom: 20px; border: 1px solid #86efac; display: flex; align-items: center; gap: 8px; }

        @media (max-width: 860px) { .auth-left { display: none; } .auth-right { width: 100%; padding: 40px 24px; } }
    </style>
</head>
<body>
    <div class="auth-left">
        <a href="../index.php" style="position:absolute;top:28px;left:32px;color:rgba(255,255,255,.75);font-size:13px;font-weight:600;text-decoration:none;display:flex;align-items:center;gap:6px;">
            <i class="fa-solid fa-arrow-left"></i> Back to Store
        </a>
        <div class="auth-left-logo">Anyn</div>
        <p class="auth-left-tagline">Join our community and discover unique roleplay characters.</p>
       
    </div>

    <div class="auth-right">
        <div class="auth-form">
            <h1 class="auth-heading">Create account ✨</h1>
            <p class="auth-sub">Join Anyn — it's free</p>

            <?php if($error): ?>
                <div class="error-box"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if($success): ?>
                <div class="success-box"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($success) ?> <a href="login.php" style="color:#16a34a;font-weight:700;margin-left:4px;">Sign in →</a></div>
            <?php endif; ?>

            <?php if(!$success): ?>
            <form method="POST">
                <div class="form-group">
                    <label>Username</label>
                    <div class="input-wrap">
                        <i class="fa-regular fa-user icon-l"></i>
                        <input type="text" name="username" placeholder="Your username" required minlength="3"
                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Email</label>
                    <div class="input-wrap">
                        <i class="fa-regular fa-envelope icon-l"></i>
                        <input type="email" name="email" placeholder="you@example.com" required
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <div class="input-wrap">
                        <i class="fa-solid fa-lock icon-l"></i>
                        <input type="password" name="password" id="pwInput" placeholder="Min. 6 characters" required minlength="6">
                        <i class="fa-regular fa-eye-slash icon-r" id="pwToggle"></i>
                    </div>
                </div>

                <button type="submit" class="btn-submit">Create Account</button>
            </form>
            <?php endif; ?>

            <p class="signin-row">Already have an account? <a href="login.php">Sign in</a></p>
            <p class="signin-row" style="margin-top:16px;">
                <a href="<?= ANYN_DISCORD_URL ?>" target="_blank" rel="noopener noreferrer" style="display:inline-flex;align-items:center;gap:6px;">
                    <i class="fa-brands fa-discord" style="color:#5865F2;"></i> Join our Discord
                </a>
            </p>
        </div>
    </div>

    <script>
        const toggle = document.getElementById('pwToggle');
        if(toggle) toggle.addEventListener('click', function(){
            const inp = document.getElementById('pwInput');
            const showing = inp.type === 'text';
            inp.type = showing ? 'password' : 'text';
            this.className = showing ? 'fa-regular fa-eye-slash icon-r' : 'fa-regular fa-eye icon-r';
        });
    </script>
</body>
</html>
