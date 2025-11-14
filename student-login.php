<?php
// --- THIS IS THE FIX ---
// Tell PHP to use the same writable session folder as your other pages
session_save_path('/var/www/sessions');
session_start();
// --- END OF FIX ---

require_once('db.php'); // Use our PDO connection ($pdo)

// If already logged in, redirect to dashboard
if (isset($_SESSION['student_id'])) {
    header("Location: student-dashboard.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    if (empty($email) || empty($password)) {
        $error = "Email and password are required.";
    } else {
        try {
            // Use $pdo from db.php
            $stmt = $pdo->prepare("SELECT id, email, password FROM students WHERE email = :email");
            $stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                // Password is correct, start session
                $_SESSION['student_id'] = $user['id'];
                $_SESSION['student_email'] = $user['email'];
                header("Location: student-dashboard.php");
                exit;
            } else {
                $error = "Invalid email or password.";
            }
        } catch (PDOException $e) {
            $error = "An error occurred. Please try again later.";
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
        /* General Styles */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Arial', sans-serif; }
        body { background-color: #f4f4f9; font-size: 16px; color: #333; line-height: 1.6; overflow-x: hidden; }
        h1, h2, h3, h4 { color: #f4f4f9; }
        .video-background { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: -1; }
        #bg-video { object-fit: cover; width: 100%; height: 100%; }
        .login-container { display: flex; justify-content: center; align-items: center; height: 100vh; position: relative; z-index: 1; }
        .login-form { background-color: rgba(0, 0, 0, 0.7); color: #fff; padding: 30px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.5); width: 100%; max-width: 400px; text-align: center; }
        .login-form h2 { margin-bottom: 20px; font-size: 2rem; }
        .form-group { margin-bottom: 20px; text-align: left; }
        .form-group label { font-size: 1rem; color: #fff; }
        .form-group input { width: 100%; padding: 10px; margin-top: 5px; border: 1px solid #ddd; border-radius: 5px; font-size: 1rem; }
        .form-group input:focus { outline: none; border-color: #3498db; }
        button.btn { width: 100%; padding: 10px; background-color: #3498db; color: white; font-size: 1.2rem; border-radius: 5px; border: none; cursor: pointer; transition: background-color 0.3s; }
        button.btn:hover { background-color: #2980b9; }
        .error-message { color: #ffcccc; background-color: rgba(255,0,0,0.2); border: 1px solid red; padding: 10px; border-radius: 5px; font-size: 1rem; margin-bottom: 15px; }
        @media (max-width: 480px) {
            .login-form { width: 95%; padding: 20px; }
            .login-form h2 { font-size: 1.6rem; }
            .form-group input { font-size: 14px; padding: 12px; }
            button.btn { padding: 12px; font-size: 14px; }
        }
    </style>
</head>
<body>
    <div class="video-background">
        <video autoplay muted loop id="bg-video">
            <source src="video/back.mp4" type="video/mp4">
            Your browser does not support the video tag.
        </video>
    </div>
    <div class="login-container">
        <div class="login-form">
            <h2>Student Login</h2>
            <?php if (isset($error)): ?>
                <p class='error-message'><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>
            <form action="student-login.php" method="POST">
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" name="email" id="email" required>
                </div>
                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" name="password" id="password" required>
                </div>
                <button class="btn" type="submit">Login</button>
            </form>
        </div>
    </div>
</body>
</html>
