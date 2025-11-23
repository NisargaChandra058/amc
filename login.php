<?php
/**
 * Universal Login Page
 * Handles login for Admin, Principal, HOD, Staff, and Students.
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include the PDO database connection
require_once __DIR__ . '/db.php';

// 1. Middleware: If already logged in, redirect based on role
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'admin':
            header('Location: admin-panel.php');
            break;
        case 'student':
            header('Location: student-dashboard.php');
            break;
        case 'staff':
            header('Location: staff-panel.php');
            break;
        case 'HOD':
            header('Location: hod-panel.php');
            break;
        case 'principal':
            header('Location: principal-panel.php');
            break;
        default:
            // Fallback for unknown roles
            header('Location: index.php'); 
            break;
    }
    exit;
}

$error = '';
$email = '';

// 2. Handle Login Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = "Please enter both email and password.";
    } else {
        try {
            // Prepare SQL to find user by email
            // We select 'role' so we know where to redirect them
            $stmt = $pdo->prepare("SELECT id, first_name, surname, password, role FROM users WHERE email = :email LIMIT 1");
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Verify User and Password
            if ($user && password_verify($password, $user['password'])) {
                
                // Login Success: Set Session Variables
                session_regenerate_id(true); // Prevent session fixation
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['name'] = $user['first_name'] . ' ' . $user['surname'];

                // Redirect based on Role
                switch ($user['role']) {
                    case 'admin':
                        header('Location: admin-panel.php');
                        exit;
                    case 'student':
                        header('Location: student-dashboard.php');
                        exit;
                    case 'staff':
                        header('Location: staff-panel.php');
                        exit;
                    case 'HOD':
                        header('Location: hod-panel.php');
                        exit;
                    case 'principal':
                        header('Location: principal-panel.php');
                        exit;
                    default:
                        $error = "Login Successful, but your role is undefined.";
                        session_destroy(); // Logout if role is invalid
                        break;
                }
            } else {
                $error = "Invalid email or password.";
            }
        } catch (PDOException $e) {
            $error = "System Error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - College Portal</title>
    <style>
        /* General Styles */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: #f4f4f9; height: 100vh; display: flex; justify-content: center; align-items: center; overflow: hidden; }
        
        /* Background Video */
        .video-background { position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: -1; overflow: hidden; }
        #bg-video { min-width: 100%; min-height: 100%; object-fit: cover; }
        .video-overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.6); z-index: -1; }

        /* Login Container */
        .login-container {
            background: rgba(255, 255, 255, 0.95);
            padding: 40px 30px;
            border-radius: 12px;
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37);
            width: 100%;
            max-width: 400px;
            text-align: center;
            animation: fadeIn 0.5s ease-out;
        }

        h2 { margin-bottom: 20px; color: #333; font-size: 2rem; }
        
        .form-group { margin-bottom: 20px; text-align: left; }
        label { font-size: 0.9rem; color: #555; font-weight: 600; margin-bottom: 5px; display: block; }
        input { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 6px; font-size: 1rem; transition: border 0.3s; }
        input:focus { border-color: #007bff; outline: none; }

        button.btn {
            width: 100%;
            padding: 12px;
            background-color: #007bff;
            color: white;
            font-size: 1.1rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.3s, transform 0.1s;
        }
        button.btn:hover { background-color: #0056b3; }
        button.btn:active { transform: scale(0.98); }

        .links { margin-top: 20px; font-size: 0.9rem; }
        .links a { color: #007bff; text-decoration: none; margin: 0 5px; }
        .links a:hover { text-decoration: underline; }

        .error-msg {
            background-color: #ffdddd;
            color: #d8000c;
            border: 1px solid #ffbaba;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Mobile Responsiveness */
        @media (max-width: 480px) {
            .login-container { margin: 20px; padding: 30px 20px; }
        }
    </style>
</head>
<body>

    <!-- Background Video -->
    <div class="video-background">
        <div class="video-overlay"></div>
        <video autoplay muted loop id="bg-video" playsinline>
            <!-- Check this path: usually just video/back.mp4 or assets/video/back.mp4 -->
            <source src="video/back.mp4" type="video/mp4">
        </video>
    </div>

    <div class="login-container">
        <h2>Login</h2>
        
        <?php if ($error): ?>
            <div class="error-msg"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form action="login.php" method="POST">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars($email) ?>" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="btn">Sign In</button>
        </form>

        <div class="links">
            <p>Don't have an account? <a href="register.php">Register here</a></p>
            <p><a href="forgot-password.php">Forgot Password?</a></p>
        </div>
    </div>

</body>
</html>
