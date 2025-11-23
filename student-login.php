<?php
session_start();
// Enable error reporting to catch system errors
error_reporting(E_ALL);
ini_set('display_errors', 1);

include('db.php');

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'student') {
    header("Location: student-dashboard.php");
    exit;
}

$error = '';
$debug_message = ''; // Variable to hold debug info

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    if (empty($email) || empty($password)) {
        $error = "Please enter both email and password.";
    } else {
        // DEBUG: 1. Check if email exists at all in users table
        $check_stmt = $conn->prepare("SELECT id, role, password, first_name, surname FROM users WHERE email = ?");
        if (!$check_stmt) {
            die("Database Error: " . $conn->error);
        }
        $check_stmt->bind_param("s", $email);
        $check_stmt->execute();
        $result = $check_stmt->get_result();

        if ($result->num_rows === 1) {
            $row = $result->fetch_assoc();
            
            // DEBUG: 2. Check Role
            if ($row['role'] !== 'student') {
                $error = "Login Failed: This email exists, but the role is '" . htmlspecialchars($row['role']) . "', not 'student'.";
            } else {
                // DEBUG: 3. Check Password
                if (password_verify($password, $row['password'])) {
                    // Success!
                    $_SESSION['user_id'] = $row['id'];
                    $_SESSION['role'] = $row['role'];
                    $_SESSION['name'] = $row['first_name'] . ' ' . $row['surname'];
                    
                    header("Location: student-dashboard.php");
                    exit;
                } else {
                    // Debugging password issues
                    $error = "Login Failed: Invalid Password. <br><small>Debug: Input='" . htmlspecialchars($password) . "' vs Hash='" . substr($row['password'], 0, 10) . "...'</small>";
                }
            }
        } else {
            $error = "Login Failed: No account found with this email address.";
        }
        $check_stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Login (Debug Mode)</title>
    <style>
        /* Basic Styles */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: sans-serif; }
        body { height: 100vh; display: flex; justify-content: center; align-items: center; background-color: #f4f4f9; }
        .login-container { background: white; padding: 40px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); width: 100%; max-width: 400px; text-align: center; }
        h2 { margin-bottom: 20px; color: #333; }
        input { width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ccc; border-radius: 4px; }
        button { width: 100%; padding: 10px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background-color: #0056b3; }
        .error-msg { background-color: #ffdddd; color: #d8000c; padding: 10px; margin-bottom: 15px; border: 1px solid #ffbaba; text-align: left; font-size: 14px; }
        .links { margin-top: 15px; font-size: 14px; }
        .links a { color: #007bff; text-decoration: none; }
    </style>
</head>
<body>

    <div class="login-container">
        <h2>Student Login</h2>
        
        <?php if ($error): ?>
            <div class="error-msg"><?= $error ?></div>
        <?php endif; ?>

        <form action="student-login.php" method="POST">
            <input type="email" name="email" placeholder="Email Address" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Login</button>
        </form>

        <div class="links">
            <p>Don't have a password? <a href="register.php">Register Here</a></p>
        </div>
    </div>

</body>
</html>

