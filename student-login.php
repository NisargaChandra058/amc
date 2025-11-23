<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include the Neon/PostgreSQL connection
require_once 'db.php';

// Redirect if already logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'student') {
    header("Location: student-dashboard.php");
    exit;
}

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    if (empty($email) || empty($password)) {
        $error = "Please enter both email and password.";
    } else {
        try {
            // ---------------------------------------------------------
            // FIX: Use $pdo (from db.php) instead of $conn
            // FIX: Use :email placeholder instead of ?
            // ---------------------------------------------------------
            $stmt = $pdo->prepare("SELECT id, first_name, surname, password, role FROM users WHERE email = :email");
            
            // FIX: Execute with an array (PDO style)
            $stmt->execute(['email' => $email]);
            
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                if ($row['role'] !== 'student') {
                    $error = "Login Failed: This email belongs to a " . htmlspecialchars($row['role']) . ", not a student.";
                } 
                elseif (password_verify($password, $row['password'])) {
                    $_SESSION['user_id'] = $row['id'];
                    $_SESSION['role'] = $row['role'];
                    $_SESSION['name'] = $row['first_name'] . ' ' . $row['surname'];
                    header("Location: student-dashboard.php");
                    exit;
                } else {
                    $error = "Invalid password.";
                }
            } else {
                $error = "No account found with this email.";
            }
        } catch (PDOException $e) {
            $error = "Database Error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Login</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: sans-serif; }
        body { height: 100vh; display: flex; justify-content: center; align-items: center; background-color: #f4f4f9; overflow: hidden; }
        .video-background { position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: -1; }
        #bg-video { min-width: 100%; min-height: 100%; object-fit: cover; }
        .video-overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: -1; }
        .login-container { background: rgba(255, 255, 255, 0.95); padding: 40px 30px; border-radius: 10px; width: 100%; max-width: 400px; text-align: center; }
        h2 { margin-bottom: 20px; color: #007bff; }
        input { width: 100%; padding: 12px; margin: 10px 0; border: 1px solid #ccc; border-radius: 5px; }
        button { width: 100%; padding: 12px; background-color: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; }
        button:hover { background-color: #0056b3; }
        .error-msg { background-color: #ffdddd; color: #d8000c; padding: 10px; border-radius: 5px; margin-bottom: 15px; }
        .links { margin-top: 15px; font-size: 14px; }
        .links a { color: #007bff; text-decoration: none; }
    </style>
</head>
<body>
    <div class="video-background">
        <div class="video-overlay"></div>
        <video autoplay muted loop id="bg-video">
            <source src="video/back.mp4" type="video/mp4">
        </video>
    </div>
    <div class="login-container">
        <h2>Student Login</h2>
        <?php if ($error): ?>
            <div class="error-msg"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST">
            <input type="email" name="email" placeholder="Email Address" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Login</button>
        </form>
        <div class="links">
            <p>New here? <a href="register.php">Register</a></p>
        </div>
    </div>
</body>
</html>
