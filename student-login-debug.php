<?php
// student-login-debug.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// === config: adjust if needed ===
$session_dir = '/var/www/sessions'; // same as your other pages
$test_user_email = 'test.student@example.com';
$test_user_password = 'Test@1234';

// === ensure session dir exists (best-effort) ===
if (!is_dir($session_dir)) {
    @mkdir($session_dir, 0755, true);
}
if (is_dir($session_dir) && is_writable($session_dir)) {
    session_save_path($session_dir);
}

// session cookie params (simple)
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax'
]);

// start session and collect initial session id
session_start();
$pre_session_id = session_id();

// load DB
require_once('db.php'); // must set $pdo

// helper for output
function H($v){ return htmlspecialchars((string)$v, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }

// handle test user creation (one click)
$created_test_user = false;
$create_err = '';
if (isset($_GET['create_test']) && $_GET['create_test'] == '1') {
    try {
        $email = $test_user_email;
        $pw = password_hash($test_user_password, PASSWORD_DEFAULT);
        // Try to insert; if students table uses different columns adjust accordingly
        $ins = $pdo->prepare("INSERT INTO students (student_name, email, password, semester, section) VALUES (:name, :email, :pw, 1, 'A') ON CONFLICT (email) DO NOTHING");
        $ins->execute([':name' => 'Test Student', ':email' => $email, ':pw' => $pw]);
        $created_test_user = true;
    } catch (Exception $e) {
        $create_err = $e->getMessage();
    }
}

// Attempt simple DB test
$db_ok = false;
$db_message = '';
try {
    $r = $pdo->query('SELECT 1')->fetchColumn();
    $db_ok = ($r == 1);
    $db_message = 'SELECT 1 returned: ' . H($r);
} catch (Exception $e) {
    $db_message = 'DB test failed: ' . H($e->getMessage());
}

// Handle POST login attempt
$post_result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $time = time();

    // fetch user
    try {
        $stmt = $pdo->prepare("SELECT id, email, password FROM students WHERE LOWER(email) = LOWER(:email) LIMIT 1");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $post_result = [
            'status' => 'error',
            'message' => 'DB error: ' . $e->getMessage()
        ];
        $user = null;
    }

    // store session id before verification
    $before_verify_session_id = session_id();

    if (!$user) {
        $post_result = [
            'status' => 'fail',
            'message' => 'No user found for that email.',
            'email_provided' => $email
        ];
    } else {
        // Check password_verify, but also show raw hash length and prefix
        $hash = $user['password'];
        $pw_verify = password_verify($password, $hash);
        $pw_needs_rehash = password_needs_rehash($hash, PASSWORD_DEFAULT);

        if ($pw_verify) {
            // Login success — regenerate session and set session vars
            session_regenerate_id(true);
            $_SESSION['student_id'] = (int)$user['id'];
            $_SESSION['student_email'] = $user['email'];

            $after_login_session_id = session_id();

            // Attempt header redirect; but we will still print debug and provide JS/meta fallback
            $post_result = [
                'status' => 'ok',
                'message' => 'Password verified. Session set.',
                'user_id' => $user['id'],
                'before_verify_session_id' => $before_verify_session_id,
                'after_login_session_id' => $after_login_session_id,
                'hash_info' => [
                    'length' => strlen($hash),
                    'prefix' => substr($hash, 0, 10)
                ],
                'needs_rehash' => $pw_needs_rehash
            ];

            // try to redirect
            header('Location: student-dashboard.php');
            // fallback output (JS/meta) will be displayed below
            echo "<!doctype html><html><head><meta charset='utf-8'><title>Redirecting...</title>";
            echo "<meta http-equiv='refresh' content='0;url=student-dashboard.php' />";
            echo "<script>window.location.href='student-dashboard.php'</script>";
            echo "</head><body>If not redirected, <a href='student-dashboard.php'>click here</a>.</body></html>";
            // flush and exit to avoid continuing the debug output interfering with redirect in some setups
            flush();
            exit;
        } else {
            $post_result = [
                'status' => 'fail',
                'message' => 'Password did not match.',
                'hash_info' => [
                    'length' => strlen($hash),
                    'prefix' => substr($hash, 0, 10)
                ],
                'before_verify_session_id' => $before_verify_session_id
            ];
        }
    }
}

// show page with debugging information
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<title>Login Debug</title>
<meta name="viewport" content="width=device-width,initial-scale=1" />
<style>
body{font-family:Arial,Helvetica,sans-serif;background:#222;color:#eee;padding:18px}
.container{max-width:1000px;margin:0 auto}
.box{background:#111;padding:14px;border-radius:8px;margin-bottom:12px;border:1px solid #333}
pre{background:#000;color:#0f0;padding:10px;border-radius:6px;overflow:auto}
label{display:block;margin-top:8px}
input{width:100%;padding:8px;border-radius:6px;border:1px solid #444;background:#111;color:#eee}
button{padding:8px 12px;border-radius:6px;border:none;background:#2b9cff;color:#002}
.btn-danger{background:#cc4444;color:white}
.small{font-size:0.9rem;color:#bbb}
.success{color:lightgreen}
.fail{color:#ff8b8b}
</style>
</head>
<body>
<div class="container">
    <h1>Student Login — Debug Helper</h1>

    <div class="box">
        <h2>Environment</h2>
        <p class="small">Session save path, writable, current session id, cookies, headers-sent status, DB test.</p>
        <pre><?php
            echo "session_save_path(): " . H(session_save_path()) . "\n";
            echo "session id: " . H($pre_session_id) . "\n";
            echo "is session dir writable? " . (is_dir($session_dir) ? (is_writable($session_dir) ? 'YES' : 'NO (not writable)') : 'NO (missing dir)') . "\n";
            echo "headers_sent(): " . (headers_sent($file, $line) ? "YES — $file:$line" : "NO") . "\n\n";

            echo "COOKIE (browser-sent):\n";
            echo H(json_encode($_COOKIE, JSON_PRETTY_PRINT)) . "\n\n";

            echo "SESSION (server):\n";
            echo H(json_encode($_SESSION, JSON_PRETTY_PRINT)) . "\n\n";

            echo "DB test: " . H($db_message) . "\n";
        ?></pre>
    </div>

    <div class="box">
        <h2>Create Test User (One-click)</h2>
        <p class="small">Creates a student row with email <strong><?= H($test_user_email) ?></strong> and password <strong><?= H($test_user_password) ?></strong>. Only for local testing.</p>
        <?php if ($created_test_user): ?>
            <p class="success">Test user created (or already existed).</p>
        <?php elseif ($create_err): ?>
            <p class="fail">Create failed: <?= H($create_err) ?></p>
        <?php endif; ?>
        <form method="get" action="">
            <input type="hidden" name="create_test" value="1">
            <button type="submit">Create Test User</button>
        </form>
    </div>

    <div class="box">
        <h2>Login Form (debug)</h2>
        <form method="post" action="">
            <input type="hidden" name="action" value="login">
            <label for="email">Email</label>
            <input id="email" name="email" type="email" required value="<?= isset($email) ? H($email) : H($test_user_email) ?>">
            <label for="password">Password</label>
            <input id="password" name="password" type="password" required value="<?= isset($password) ? H($password) : H($test_user_password) ?>">
            <div style="margin-top:10px;">
                <button type="submit">Attempt Login</button>
            </div>
        </form>
        <p class="small">After clicking Submit the script will attempt to `header('Location: student-dashboard.php')` and also show fallback.</p>
    </div>

    <div class="box">
        <h2>Last POST result</h2>
        <?php if ($post_result === null): ?>
            <p class="small">No POST yet.</p>
        <?php else: ?>
            <pre><?php echo H(json_encode($post_result, JSON_PRETTY_PRINT)); ?></pre>
        <?php endif; ?>
    </div>

    <div class="box">
        <h2>Quick checks & next steps</h2>
        <ol>
            <li>Make sure this debug page shows <code>is session dir writable? YES</code>. If NO then fix folder permissions: <code>sudo chown www-data:www-data /var/www/sessions</code> (or appropriate user) and <code>chmod 755</code>.</li>
            <li>After successful login the debug output should show <code>"status":"ok"</code> and a non-empty <code>after_login_session_id</code>. If it shows OK but <code>$_SESSION</code> on a later page (dashboard) is empty, the problem is cookies not being stored — check browser's cookies for your domain.</li>
            <li>If DB test fails, your <code>db.php</code> is not connecting; check credentials and that the PDO instance is in $pdo.</li>
            <li>If password_verify always fails and your DB password column stores plain text, you must migrate to hashed passwords using <code>password_hash()</code>. Use the Create Test User button above to create a correctly hashed test user.</li>
        </ol>
    </div>

    <div class="box">
        <h2>If still not working — paste the following output here</h2>
        <p>Copy the content of the <strong>Environment</strong> box and the <strong>Last POST result</strong> box and paste here. I will interpret it and give the exact fix (file permission command, cookie setting tweak, or DB migration SQL).</p>
    </div>
</div>
</body>
</html>
