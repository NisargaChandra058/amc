<?php
// login.php — combined presentation + POST handler
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Optional: set custom session folder if your app uses one
// session_save_path('/var/www/sessions');

session_start();

// If already logged in redirect to appropriate dashboard
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    $role = $_SESSION['role'];
    if ($role === 'admin') header('Location: admin-dashboard.php');
    elseif ($role === 'student') header('Location: student-dashboard.php');
    else header('Location: dashboard.php');
    exit;
}

// Load DB config — should define either $pdo (PDO) or $conn (mysqli) or DB_* constants
require_once __DIR__ . '/db.php';

// DB detection / fallback logic
$db_type = null;
$pdo = $pdo ?? null;
$conn = $conn ?? null;

if (isset($pdo) && $pdo instanceof PDO) {
    $db_type = 'pdo';
} elseif (isset($conn) && $conn instanceof mysqli) {
    $db_type = 'mysqli';
} else {
    // Try to build PDO from common vars/constants
    $host = defined('DB_HOST') ? DB_HOST : ($db_host ?? null);
    $name = defined('DB_NAME') ? DB_NAME : ($db_name ?? null);
    $user = defined('DB_USER') ? DB_USER : ($db_user ?? null);
    $pass = defined('DB_PASS') ? DB_PASS : ($db_pass ?? null);

    if ($host && $name && $user) {
        try {
            $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $db_type = 'pdo';
        } catch (PDOException $e) {
            try {
                $dsn = "pgsql:host={$host};dbname={$name}";
                $pdo = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
                $db_type = 'pdo';
            } catch (PDOException $e2) {
                $pdo = null;
            }
        }
    }
}

// If still no DB connection, show clear message
if ($db_type === null) {
    // Show a simple HTML explaining what's missing
    header('Content-Type: text/html; charset=utf-8');
    echo "<h2>Database connection not configured</h2>";
    echo "<p><strong>db.php</strong> must define either <code>\$pdo</code> (PDO) or <code>\$conn</code> (mysqli), or set DB_HOST/DB_NAME/DB_USER/DB_PASS for auto-creation.</p>";
    echo "<p>Example PDO snippet for db-config.php:</p><pre>\$pdo = new PDO('mysql:host=localhost;dbname=mydb;charset=utf8mb4','dbuser','dbpass');</pre>";
    exit;
}

// Initialize variables for form
$error = '';
$email = '';

// Handle POST in same file
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
                $stmt = $pdo->prepare("SELECT id, first_name, surname, password, role FROM users WHERE email = :email LIMIT 1");
                $stmt->execute([':email' => $email]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($row && isset($row['password']) && password_verify($password, $row['password'])) {
                    // success
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = (int)$row['id'];
                    $_SESSION['role'] = $row['role'] ?? 'user';
                    $_SESSION['name'] = trim(($row['first_name'] ?? '') . ' ' . ($row['surname'] ?? ''));
                    // redirect by role
                    if ($_SESSION['role'] === 'admin') {
                        header('Location: admin-dashboard.php');
                        exit;
                    } elseif ($_SESSION['role'] === 'student') {
                        header('Location: student-dashboard.php');
                        exit;
                    } else {
                        header('Location: dashboard.php');
                        exit;
                    }
                } else {
                    $error = "Invalid email or password.";
                }
            } catch (PDOException $e) {
                error_log("Login PDO error: " . $e->getMessage());
                $error = "An internal error occurred. Please try again later.";
            }
        } else {
            // mysqli path
            $stmt = $conn->prepare("SELECT id, first_name, surname, password, role FROM users WHERE email = ? LIMIT 1");
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
                        $_SESSION['role'] = $row['role'] ?? 'user';
                        $_SESSION['name'] = trim(($row['first_name'] ?? '') . ' ' . ($row['surname'] ?? ''));
                        $stmt->close();
                        if ($_SESSION['role'] === 'admin') {
                            header('Location: admin-dashboard.php');
                            exit;
                        } elseif ($_SESSION['role'] === 'student') {
                            header('Location: student-dashboard.php');
                            exit;
                        } else {
                            header('Location: dashboard.php');
                            exit;
                        }
                    } else {
                        $error = "Invalid email or password.";
                    }
                }
                $stmt->close();
            }
        }
    }
}

// HTML form (keeps original styling)
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - College Exam Section</title>
    <style>
/* General Styles */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Arial', sans-serif;
}

body {
    background-color: #f4f4f9;
    font-size: 16px;
    color: #333;
    line-height: 1.6;
    overflow-x: hidden;
}

h1, h2, h3, h4 {
    color: #333;
}

a {
    text-decoration: none;
    color: #3498db;
}

a:hover {
    color: #2980b9;
}

/* Background Video */
.video-background {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: -1;
}

#bg-video {
    object-fit: cover;
    width: 100%;
    height: 100%;
}

/* Login Form Container */
.login-container {
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100vh;
    position: relative;
    z-index: 1;
}

.login-form {
    background-color: rgba(0, 0, 0, 0.7);
    color: #fff;
    padding: 30px;
    border-radius: 10px;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.5);
    width: 100%;
    max-width: 400px;
    text-align: center;
}

.login-form h2 {
    margin-bottom: 20px;
    font-size: 2rem;
}

.form-group {
    margin-bottom: 20px;
    text-align: left;
}

.form-group label {
    font-size: 1rem;
    color: #fff;
}

.form-group input {
    width: 100%;
    padding: 10px;
    margin-top: 5px;
    border: 1px solid #ddd;
    border-radius: 5px;
    font-size: 1rem;
}

.form-group input:focus {
    outline: none;
    border-color: #3498db;
}

button.btn {
    width: 100%;
    padding: 10px;
    background-color: #3498db;
    color: white;
    font-size: 1.2rem;
    border-radius: 5px;
    border: none;
    cursor: pointer;
    transition: background-color 0.3s;
}

button.btn:hover {
    background-color: #2980b9;
}

.register-link {
    margin-top: 20px;
    font-size: 1rem;
    color: #fff;
}

.register-link a {
    color: #3498db;
}

.register-link a:hover {
    color: #2980b9;
}

/* Forget Password Link */
.forgot-password {
    margin-top: 10px;
    font-size: 1rem;
    color: #fff;
}

.forgot-password a {
    color: #e74c3c;
}

.forgot-password a:hover {
    color: #c0392b;
}

/* Responsive Styles */
@media (max-width: 768px) {
    .login-form {
        width: 90%;
        padding: 20px;
    }

    .login-form h2 {
        font-size: 1.8rem;
    }

    .form-group input {
        font-size: 1rem;
    }

    button.btn {
        font-size: 1rem;
    }
}
h1, h2, h3, h4 {
    color: #f4f4f9;
}
</style>
</head>
<body>
    <!-- Background Video -->
    <div class="video-background">
        <video autoplay muted loop id="bg-video" playsinline>
            <!-- NOTE: Make sure this video path is correct relative to your project structure -->
            <source src="assets/video/back.mp4" type="video/mp4">
            Your browser does not support the video tag.
        </video>
    </div>

    <!-- Login Form -->
    <div class="login-container">
        <div class="login-form">
            <h2>Login</h2>
            
            <?php if ($error): ?>
                <p style="color: #ff4d4d; text-align: center; font-weight: bold; margin-bottom: 15px;"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>

            <form action="login.php" method="POST">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required value="<?= htmlspecialchars($email) ?>">
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit" class="btn">Login</button>
            </form>
            <p class="register-link">Don't have an account? <a href="register.php">Register here</a></p>
            <p class="forgot-password"><a href="forgot-password.php">Forgot Password?</a></p>
        </div>
    </div>
</body>
</html>

