<?php
// student-login.php (improved + secure)

// --- SESSION SETUP ---
// Use same writable session folder as your other pages (ensure folder exists and is writable)
session_save_path('/var/www/sessions');

// Secure cookie params (adjust secure flag if you are using HTTPS)
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => $_SERVER['HTTP_HOST'] ?? '',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax'
]);

session_start();

// --- REQUIRES ---
require_once('db.php'); // provides $pdo (PDO)

// If already logged in, redirect to dashboard
if (isset($_SESSION['student_id'])) {
    header("Location: student-dashboard.php");
    exit;
}

// Simple brute-force protection (per-session)
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['login_first_attempt_time'] = time();
}
$MAX_ATTEMPTS = 5;
$LOCKOUT_WINDOW = 15 * 60; // 15 minutes

$error = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Throttle by attempts in session
    $now = time();
    if ($_SESSION['login_attempts'] >= $MAX_ATTEMPTS &&
        ($now - ($_SESSION['login_first_attempt_time'] ?? $now)) < $LOCKOUT_WINDOW) {
        $error = "Too many login attempts. Please try again after 15 minutes.";
    } else {
        // Normalize + validate input
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';

        if ($email === '' || $password === '') {
            $error = "Email and password are required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        } else {
            try {
                // Prepared statement: fetch user by email (case-insensitive)
                $stmt = $pdo->prepare("SELECT id, email, password FROM students WHERE LOWER(email) = LOWER(:email) LIMIT 1");
                $stmt->execute([':email' => $email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user && password_verify($password, $user['password'])) {
                    // Successful login
                    session_regenerate_id(true); // prevent session fixation
                    $_SESSION['student_id'] = (int)$user['id'];
                    $_SESSION['student_email'] = $user['email'];

                    // Reset attempt counters
                    unset($_SESSION['login_attempts'], $_SESSION['login_first_attempt_time']);

                    header("Location: student-dashboard.php");
                    exit;
                } else {
                    // Failed login: increment attempts
                    if (empty($_SESSION['login_first_attempt_time']) || ($now - $_SESSION['login_first_attempt_time']) > $LOCKOUT_WINDOW) {
                        // Reset window
                        $_SESSION['login_attempts'] = 1;
                        $_SESSION['login_first_attempt_time'] = $now;
                    } else {
                        $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
                    }

                    // Generic error (do not reveal whether email exists)
                    $error = "Invalid email or password.";
                }
            } catch (PDOException $e) {
                // Don't expose DB error to users in production
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
    <meta charset="UTF-8">
    <title>Student Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        /* General Styles (kept from your original) */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Arial', sans-serif; }
        body { background-color: #f4f4f9; font-size: 16px; color: #333; line-height: 1.6; overflow-x: hidden; }
        .video-background { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: -1; }
        #bg-video { object-fit: cover; width: 100%; height: 100%; }
        .login-container { display: flex; justify-content: center; align-items: center; height: 100vh; position: relative; z-index: 1; }
        .login-form { background-color: rgba(0, 0, 0, 0.7); color: #fff; padding: 30px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.5); width: 100%; max-width: 420px; text-align: center; }
        .login-form h2 { margin-bottom: 20px; font-size: 2rem; }
        .form-group { margin-bottom: 20px; text-align: left; }
        .form-group label { font-size: 1rem; color: #fff; display:block; margin-bottom:6px; }
        .form-group input { width: 100%; padding: 10px; margin-top: 0; border: 1px solid #ddd; border-radius: 5px; font-size: 1rem; }
        .form-group input:focus { outline: none; border-color: #3498db; }
        button.btn { width: 100%; padding: 10px; background-color: #3498db; color: white; font-size: 1.1rem; border-radius: 5px; border: none; cursor: pointer; transition: background-color 0.3s; }
        button.btn:hover { background-color: #2980b9; }
        .error-message { color: #ffcccc; background-color: rgba(255,0,0,0.12); border: 1px solid rgba(255,0,0,0.25); padding: 10px; border-radius: 5px; font-size: 1rem; margin-bottom: 15px; text-align:left; }
        .hint { margin-top:10px; color: rgba(255,255,255,0.6); font-size:0.9rem; text-align:left; }
        @media (max-width: 480px) {
            .login-form { width: 95%; padding: 20px; }
            .login-form h2 { font-size: 1.6rem; }
            .form-group input { font-size: 14px; padding: 12px; }
            button.btn { padding: 12px; font-size: 14px; }
        }
    </style>
</head>
<body>
    <div class="video-background" aria-hidden="true">
        <video autoplay muted loop id="bg-video" playsinline>
            <source src="video/back.mp4" type="video/mp4">
            <!-- fallback image -->
        </video>
    </div>
    <div class="login-container">
        <div class="login-form" role="main" aria-labelledby="login-title">
            <h2 id="login-title">Student Login</h2>

            <?php if (!empty($error)): ?>
                <div class="error-message" role="alert"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form action="student-login.php" method="POST" novalidate>
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" name="email" id="email" required value="<?= isset($email) ? htmlspecialchars($email) : '' ?>">
                </div>
                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" name="password" id="password" required>
                </div>

                <button class="btn" type="submit">Login</button>

                <div class="hint">
                    <?php
                    // show attempt info if there are attempts
                    if (!empty($_SESSION['login_attempts'])) {
                        $attempts_left = max(0, $MAX_ATTEMPTS - (int)$_SESSION['login_attempts']);
                        echo "Attempts left: " . $attempts_left . ".";
                    }
                    ?>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
