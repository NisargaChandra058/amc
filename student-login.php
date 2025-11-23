<?php
// student-login.php - robust login + debug fallback
error_reporting(E_ALL);
ini_set('display_errors', 1);

// --- SESSION SETUP ---
// Use same writable session folder as your other pages if required
// Make sure the folder exists and PHP-FPM/Apache user can write to it.
$session_dir = '/var/www/sessions';
if (!is_dir($session_dir)) {
    // try to create it (best-effort)
    @mkdir($session_dir, 0755, true);
}
if (is_dir($session_dir) && is_writable($session_dir)) {
    session_save_path($session_dir);
}

// simpler cookie params - avoid domain when unsure
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax'
]);

// start the session
session_start();

// If already logged in, redirect
if (isset($_SESSION['student_id'])) {
    header('Location: student-dashboard.php');
    exit;
}

require_once('db.php'); // provides $pdo

$error = '';
$emailValue = ''; // persist email in form if needed

// Simple brute-force protection (session-scoped)
$MAX_ATTEMPTS = 6;
$LOCKOUT_WINDOW = 15 * 60; // 15 minutes
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['login_first_attempt_time'] = time();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $now = time();

    // reset attempts window if expired
    if (!empty($_SESSION['login_first_attempt_time']) && ($now - $_SESSION['login_first_attempt_time']) > $LOCKOUT_WINDOW) {
        $_SESSION['login_attempts'] = 0;
        $_SESSION['login_first_attempt_time'] = $now;
    }

    if ($_SESSION['login_attempts'] >= $MAX_ATTEMPTS) {
        $error = "Too many login attempts. Please try again after 15 minutes.";
    } else {
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $emailValue = htmlspecialchars($email);

        if ($email === '' || $password === '') {
            $error = "Email and password are required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        } else {
            try {
                // fetch user (case-insensitive email match)
                $stmt = $pdo->prepare("SELECT id, email, password FROM students WHERE LOWER(email) = LOWER(:email) LIMIT 1");
                $stmt->execute([':email' => $email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user && password_verify($password, $user['password'])) {
                    // successful login
                    session_regenerate_id(true);
                    $_SESSION['student_id'] = (int)$user['id'];
                    $_SESSION['student_email'] = $user['email'];

                    // reset attempts
                    unset($_SESSION['login_attempts'], $_SESSION['login_first_attempt_time']);

                    // Attempt header redirect
                    header('Location: student-dashboard.php');
                    // If headers already sent or redirect ignored, provide JS/meta fallback and exit
                    echo "<!doctype html><html><head><meta charset='utf-8'><title>Redirecting...</title>";
                    echo "<meta http-equiv='refresh' content='0;url=student-dashboard.php' />";
                    echo "<script>window.location.href='student-dashboard.php';</script>";
                    echo "</head><body>If you are not redirected automatically, <a href='student-dashboard.php'>click here</a>.</body></html>";
                    exit;
                } else {
                    // failed auth
                    $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
                    if (empty($_SESSION['login_first_attempt_time'])) $_SESSION['login_first_attempt_time'] = $now;
                    $attempts_left = max(0, $MAX_ATTEMPTS - $_SESSION['login_attempts']);
                    $error = "Invalid email or password. Attempts left: {$attempts_left}.";
                }
            } catch (PDOException $e) {
                error_log("Login DB error: " . $e->getMessage());
                $error = "An internal error occurred. Please try again later.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<title>Student Login</title>
<meta name="viewport" content="width=device-width,initial-scale=1" />
<style>
/* Minimal styles, keeps your video background and layout */
*{box-sizing:border-box}
body{font-family:Arial,Helvetica,sans-serif;background:#f4f4f9;margin:0;padding:0}
.video-bg{position:fixed;inset:0;z-index:-1}
#bg-video{object-fit:cover;width:100%;height:100%}
.center{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.card{width:100%;max-width:420px;background:rgba(0,0,0,0.75);color:#fff;padding:26px;border-radius:10px}
label{display:block;margin-bottom:6px;font-weight:700}
input{width:100%;padding:10px;border-radius:6px;border:1px solid #ccc;margin-bottom:12px}
button{width:100%;padding:10px;border-radius:6px;border:none;background:#3498db;color:#fff;font-weight:700}
.error{background:rgba(255,0,0,0.12);padding:10px;border-radius:6px;color:#ffd3d3;margin-bottom:12px}
.hint{color:#ddd;font-size:0.9rem;margin-top:8px}
</style>
</head>
<body>
<div class="video-bg" aria-hidden="true">
    <video autoplay muted loop id="bg-video" playsinline>
        <source src="video/back.mp4" type="video/mp4">
    </video>
</div>

<div class="center">
    <div class="card" role="main" aria-labelledby="login-title">
        <h2 id="login-title" style="margin-top:0;margin-bottom:14px">Student Login</h2>

        <?php if ($error): ?>
            <div class="error" role="alert"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="student-login.php" novalidate>
            <label for="email">Email</label>
            <input id="email" name="email" type="email" required value="<?= $emailValue ?>">

            <label for="password">Password</label>
            <input id="password" name="password" type="password" required>

            <button type="submit">Login</button>

            <div class="hint">If you cannot log in, ensure your account exists and the password was created with <code>password_hash()</code>.</div>
        </form>

        <noscript>
            <p style="color:#ffd3d3;margin-top:12px">JavaScript is disabled — if login succeeds but redirect fails, click the link shown after logging in.</p>
        </noscript>
    </div>
</div>
</body>
</html>
