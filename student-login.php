<?php
session_start();
// 1. Include the CORRECT database connection (matches your dashboard)
require_once('db.php'); // Changed from 'db-config.php'

$error = ""; // Variable to hold login error messages

// Handle POST request
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    // Basic validation
    if (empty($email) || empty($password)) {
        $error = "Email and password are required.";
    } else {
        try {
            // 2. Prepare the query using the CORRECT variable (matches your dashboard)
            $stmt = $pdo->prepare("SELECT id, email, password FROM students WHERE email = ?"); // Changed from $conn
            
            // 3. Execute the query
            $stmt->execute([$email]);
            
            // 4. Fetch the student record
            $student = $stmt->fetch(PDO::FETCH_ASSOC);

            // 5. Verify if student exists and password matches
            // This checks your HASHED password in the database
            if ($student && password_verify($password, $student['password'])) {
                
                // Password is correct, set session variables
                session_regenerate_id(true); // Prevent session fixation
                $_SESSION['student_id'] = $student['id'];
                $_SESSION['student_email'] = $student['email'];
                
                // Redirect to the student dashboard
                header("Location: student-dashboard.php");
                exit; // ALWAYS call exit after a header redirect
                
            } else {
                // Invalid email or password
                $error = "Invalid email or password.";
            }
        } catch (PDOException $e) {
            // Handle potential database errors
            $error = "Login failed due to a system error. Please try again later.";
            // Log the detailed error for your own debugging
            error_log("Student Login DB Error: " . $e->getMessage()); 
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
        /* General Styles */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Arial', sans-serif; }
        body { background-color: #f4f4f9; font-size: 16px; color: #333; line-height: 1.6; overflow-x: hidden; }
        h1, h2, h3, h4 { color: #f4f4f9; }
        /* Background Video */
        .video-background { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: -1; overflow: hidden; }
        #bg-video { object-fit: cover; width: 100%; height: 100%; }
        /* Login Form Container */
        .login-container { display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 20px; }
        /* Login Form Styling */
        .login-form { background-color: rgba(0, 0, 0, 0.75); color: #fff; padding: 30px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.5); width: 100%; max-width: 400px; text-align: center; }
        .login-form h2 { margin-bottom: 25px; font-size: 2rem; }
        .form-group { margin-bottom: 20px; text-align: left; }
        .form-group label { font-size: 1rem; color: #eee; display: block; margin-bottom: 5px; }
        .form-group input { width: 100%; padding: 12px; margin-top: 5px; border: 1px solid #555; border-radius: 5px; font-size: 1rem; background-color: #333; color: #fff; }
        .form-group input:focus { outline: none; border-color: #3498db; box-shadow: 0 0 5px rgba(52, 152, 219, 0.5); }
        button.btn { width: 100%; padding: 12px; background-color: #3498db; color: white; font-size: 1.2rem; font-weight: bold; border-radius: 5px; border: none; cursor: pointer; transition: background-color 0.3s; margin-top: 10px; }
        button.btn:hover { background-color: #2980b9; }
        /* Error Message */
        .error-message { color: #ff6b6b; background-color: rgba(255, 107, 107, 0.1); border: 1px solid #ff6b6b; padding: 10px; border-radius: 5px; font-size: 1rem; margin-top: 15px; text-align: center; }
        /* Back Link */
         .back-link { display: block; margin-top: 15px; text-decoration: none; color: #bdc3c7; font-size: 0.9rem; }
         .back-link:hover { color: #fff; text-decoration: underline; }
    </style>
</head>
<body>
    
    <div class="video-background">
        <video autoplay muted loop playsinline id="bg-video">
            <source src="../video/back.mp4" type="video/mp4"> 
            Your browser does not support the video tag.
        </video>
    </div>

    <div class="login-container">
        <div class="login-form">
            <h2>Student Login</h2>
            
            <?php 
            // Display error message if it exists
            if (!empty($error)) {
                 // Use htmlspecialchars for security, just in case
                 echo "<p class='error-message'>" . htmlspecialchars($error) . "</p>"; 
            }
            ?>
            
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
             <a href="../index.php" class="back-link">Back to Home</a> 
        </div>
    </div>

</body>
</html>
