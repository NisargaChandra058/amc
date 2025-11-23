<?php
// student-login.php — robust login that accepts PDO or mysqli, or will build PDO from common config values
error_reporting(E_ALL);
ini_set('display_errors', 1);

// start session (adjust session_save_path if you use custom sessions)
session_start();

// If already logged in redirect
if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'student') {
    header("Location: student-dashboard.php");
    exit;
}

// Load DB config — your file should define either $pdo (PDO) or $conn (mysqli)
// or define DB_HOST, DB_NAME, DB_USER, DB_PASS (or $db_host, $db_name, $db_user, $db_pass)
require_once __DIR__ . '/db-config.php';

// --- DB detection / fallback logic ---
$db_type = null; // 'pdo' or 'mysqli'
$pdo = $pdo ?? null;
$conn = $conn ?? null;

if (isset($pdo) && $pdo instanceof PDO) {
    $db_type = 'pdo';
} elseif (isset($conn) && $conn instanceof mysqli) {
    $db_type = 'mysqli';
} else {
    // Try to construct a PDO from common constants/variables
    $host = defined('DB_HOST') ? DB_HOST : ($db_host ?? ($DB_HOST ?? null));
    $name = defined('DB_NAME') ? DB_NAME : ($db_name ?? ($DB_NAME ?? null));
    $user = defined('DB_USER') ? DB_USER : ($db_user ?? ($DB_USER ?? null));
    $pass = defined('DB_PASS') ? DB_PASS : ($db_pass ?? ($DB_PASS ?? null));

    // Try postgres-specific constants too (PG_HOST, etc.)
    $pg_host = defined('PG_HOST') ? PG_HOST : null;
    $pg_name = defined('PG_NAME') ? PG_NAME : null;
    $pg_user = defined('PG_USER') ? PG_USER : null;
    $pg_pass = defined('PG_PASS') ? PG_PASS : null;

    // Choose parameters
    if (!$host && $pg_host) { $host = $pg_host; $name = $pg_name; $user = $pg_user; $pass = $pg_pass; }

    if ($host && $name && $user) {
        // Attempt to create PDO — try MySQL first, then fallback to PostgreSQL DSN if MySQL fails and PG info exists
        try {
            $dsn_mysql = "mysql:host={$host};dbname={$name};charset=utf8mb4";
            $pdo = new PDO($dsn_mysql, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $db_type = 'pdo';
        } catch (PDOException $e_mysql) {
            // try Postgres DSN
            try {
                $dsn_pg = "pgsql:host={$host};dbname={$name}";
                $pdo = new PDO($dsn_pg, $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
                $db_type = 'pdo';
            } catch (PDOException $e_pg) {
                // both DSNs failed — leave db_type null and show clear error below
                $pdo = null;
            }
        }
    }
}

// If still no DB available, show actionable error and stop
if ($db_type === null) {
    // Helpful diagnostic: what the config file provided
    $provided = [
        'has_pdo' => isset($pdo) && $pdo instanceof PDO,
        'has_conn' => isset($conn) && $conn instanceof mysqli,
        'found_constants' => [
            'DB_HOST' => defined('DB_HOST') ? DB_HOST : null,
            'DB_NAME' => defined('DB_NAME') ? DB_NAME : null,
            'DB_USER' => defined('DB_USER') ? DB_USER : null,
            'DB_PASS' => defined('DB_PASS') ? '***' : null
        ]
    ];

    header('Content-Type: text/html; charset=utf-8');
    echo "<h2>Database configuration not found</h2>";
    echo "<p>Your <code>db-config.php</code> did not provide a <code>\$pdo</code> (PDO) or <code>\$conn</code> (mysqli) connection, and automatic connection attempt failed.</p>";
    echo "<p>Either:</p><ol>
        <li>Make <code>db-config.php</code> create a PDO instance: <code>\$pdo = new PDO(...)</code></li>
        <li>Or create a mysqli connection: <code>\$conn = new mysqli(...)</code></li>
    </ol>";
    echo "<p>Example <strong>PDO</strong> content for <code>db-config.php</code> (MySQL):</p>";
    echo "<pre>\$host = 'localhost'; \$db = 'your_db'; \$user = 'your_user'; \$pass = 'your_pass';
try {
  \$pdo = new PDO(\"mysql:host=\$host;dbname=\$db;charset=utf8mb4\", \$user, \$pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
  ]);
} catch (PDOException \$e) {
  die('DB connect error: '.\$e->getMessage());
}</pre>";
    echo "<p>Or example <strong>mysqli</strong> content:</p>";
    echo "<pre>\$conn = new mysqli('localhost','your_user','your_pass','your_db');
if (\$conn->connect_errno) { die('MySQLi connect error: '.\$conn->connect_error); }</pre>";

    echo "<h3>Diagnostic data detected</h3><pre>" . htmlspecialchars(json_encode($provided, JSON_PRETTY_PRINT)) . "</pre>";
    exit;
}

// At this point $db_type is either 'pdo' and $pdo is available, or 'mysqli' and $conn is available

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error = "Please enter both email and password.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        if ($db_type === 'pdo') {
            try {
                $stmt = $pdo->prepare("SELECT id, first_name, surname, password, role FROM users WHERE email = :email AND role = 'student' LIMIT 1");
                $stmt->execute([':email' => $email]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($row && isset($row['password']) && password_verify($password, $row['password'])) {
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = (int)$row['id'];
                    $_SESSION['role'] = $row['role'];
                    $_SESSION['name'] = trim(($row['first_name'] ?? '') . ' ' . ($row['surname'] ?? ''));
                    header("Location: student-dashboard.php");
                    echo "<!doctype html><html><head><meta http-equiv='refresh' content='0;url=student-dashboard.php'><script>location='student-dashboard.php'</script></head><body>If not redirected, <a href='student-dashboard.php'>click here</a>.</body></html>";
                    exit;
                } else {
                    $error = "Invalid email or password.";
                }
            } catch (PDOException $e) {
                error_log("Login PDO error: " . $e->getMessage());
                $error = "An internal error occurred. Please try again later.";
            }
        } else { // mysqli
            // prepare
            $stmt = $conn->prepare("SELECT id, first_name, surname, password, role FROM users WHERE email = ? AND role = 'student' LIMIT 1");
            if ($stmt === false) {
                error_log("MySQLi prepare error: " . $conn->error);
                $error = "Database error. Contact admin.";
            } else {
                $stmt->bind_param('s', $email);
                if (! $stmt->execute()) {
                    error_log("MySQLi execute error: " . $stmt->error);
                    $error = "Database error. Contact admin.";
                } else {
                    $res = $stmt->get_result();
                    $row = $res->fetch_assoc();
                    if ($row && isset($row['password']) && password_verify($password, $row['password'])) {
                        session_regenerate_id(true);
                        $_SESSION['user_id'] = (int)$row['id'];
                        $_SESSION['role'] = $row['role'];
                        $_SESSION['name'] = trim(($row['first_name'] ?? '') . ' ' . ($row['surname'] ?? ''));
                        $stmt->close();
                        header("Location: student-dashboard.php");
                        echo "<!doctype html><html><head><meta http-equiv='refresh' content='0;url=student-dashboard.php'><script>location='student-dashboard.php'</script></head><body>If not redirected, <a href='student-dashboard.php'>click here</a>.</body></html>";
                        exit;
                    } else {
                        $error = "Invalid email or password.";
                    }
                }
                $stmt->close();
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<title>Student Login</title>
<meta name="viewport" content="width=device-width,initial-scale=1" />
<style>
/* Basic styling kept similar to existing design */
*{box-sizing:border-box}
body{height:100vh;margin:0;font-family:Segoe UI,Arial;background:#f4f4f9;color:#333;display:flex;align-items:center;justify-content:center}
.video-background{position:fixed;inset:0;z-index:-1}
#bg-video{width:100%;height:100%;object-fit:cover}
.login-box{width:100%;max-width:420px;background:#fff;padding:30px;border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,0.12);text-align:left}
h2{color:#007bff;margin:0 0 16px}
label{display:block;margin-bottom:6px;font-weight:600}
input[type=email],input[type=password]{width:100%;padding:10px;border:1px solid #ccc;border-radius:6px;margin-bottom:12px}
button{width:100%;padding:12px;border:none;border-radius:6px;background:#007bff;color:#fff;font-weight:700;cursor:pointer}
.error{background:#ffe7e7;color:#b00000;padding:10px;border:1px solid #ffbaba;border-radius:6px;margin-bottom:12px}
.small{font-size:0.9rem;color:#666;margin-top:8px}
</style>
</head>
<body>
<div class="video-background" aria-hidden="true">
    <video autoplay muted loop id="bg-video" playsinline>
        <source src="video/back.mp4" type="video/mp4">
    </video>
</div>

<div class="login-box" role="main" aria-labelledby="login-title">
    <h2 id="login-title">Student Login</h2>

    <?php if ($error): ?>
        <div class="error" role="alert"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" action="student-login.php" novalidate>
        <label for="email">Email</label>
        <input id="email" name="email" type="email" required value="<?= htmlspecialchars($email) ?>">

        <label for="password">Password</label>
        <input id="password" name="password" type="password" required>

        <button type="submit">Login</button>

        <div class="small">If you have trouble logging in, ensure your DB config supplies <code>$pdo</code> (PDO) or <code>$conn</code> (mysqli) and that passwords are hashed with <code>password_hash()</code>.</div>
    </form>
</div>
</body>
</html>
