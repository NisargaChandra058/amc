<?php
// session_start(); // Enable if using sessions
require_once('db.php'); // Use PDO connection

// --- Security Check (Placeholder) ---
/*
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}
*/

$message = ''; // For success/error messages

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Retrieve and sanitize form data
    $usn = isset($_POST['usn']) ? trim(htmlspecialchars($_POST['usn'], ENT_QUOTES, 'UTF-8')) : '';
    $name = isset($_POST['name']) ? trim(htmlspecialchars($_POST['name'], ENT_QUOTES, 'UTF-8')) : '';
    $email = isset($_POST['email']) ? trim(htmlspecialchars($_POST['email'], ENT_QUOTES, 'UTF-8')) : '';
    $dob = isset($_POST['dob']) ? trim(htmlspecialchars($_POST['dob'], ENT_QUOTES, 'UTF-8')) : '';
    $address = isset($_POST['address']) ? trim(htmlspecialchars($_POST['address'], ENT_QUOTES, 'UTF-8')) : '';
    $password_plain = isset($_POST['password']) ? trim($_POST['password']) : '';
    $semester = isset($_POST['semester']) ? filter_input(INPUT_POST, 'semester', FILTER_VALIDATE_INT) : null;
    $section = isset($_POST['section']) ? trim(htmlspecialchars($_POST['section'], ENT_QUOTES, 'UTF-8')) : ''; // <-- NEW

    // Basic Validation
    if (empty($usn) || empty($name) || empty($email) || empty($dob) || empty($password_plain) || empty($semester) || empty($section)) { // <-- UPDATED
        $message = "<p class='message error'>All fields are required!</p>";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
         $message = "<p class='message error'>Invalid email format!</p>";
    } else {
        try {
            // Check if USN or Email already exists using PDO
            $check_stmt = $pdo->prepare("SELECT usn FROM students WHERE usn = :usn OR email = :email LIMIT 1");
            $check_stmt->bindParam(':usn', $usn, PDO::PARAM_STR);
            $check_stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $check_stmt->execute();

            if ($check_stmt->fetch()) {
                $message = "<p class='message error'>Error: USN or Email already exists!</p>";
            } else {
                // Hash the password
                $password_hashed = password_hash($password_plain, PASSWORD_DEFAULT);

                // Insert student into students table using PDO
                $insert_stmt = $pdo->prepare(
                    "INSERT INTO students (usn, student_name, email, dob, permanent_address, password, semester, section) 
                     VALUES (:usn, :student_name, :email, :dob, :address, :password, :semester, :section)" // <-- UPDATED
                );
                
                $insert_stmt->bindParam(':usn', $usn, PDO::PARAM_STR);
                $insert_stmt->bindParam(':student_name', $name, PDO::PARAM_STR);
                $insert_stmt->bindParam(':email', $email, PDO::PARAM_STR);
                $insert_stmt->bindParam(':dob', $dob);
                $insert_stmt->bindParam(':address', $address, PDO::PARAM_STR);
                $insert_stmt->bindParam(':password', $password_hashed, PDO::PARAM_STR);
                $insert_stmt->bindParam(':semester', $semester, PDO::PARAM_INT);
                $insert_stmt->bindParam(':section', $section, PDO::PARAM_STR); // <-- NEW

                if ($insert_stmt->execute()) {
                    $message = "<p class='message success'>Student added successfully!</p>";
                } else {
                    $message = "<p class='message error'>Error adding student.</p>";
                }
            }
        } catch (PDOException $e) {
            if ($e->getCode() == '23505') {
                 $message = "<p class='message error'>Error: USN or Email already exists!</p>";
            } else {
                $message = "<p class='message error'>Database error: " . $e->getMessage() . "</p>";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Student</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        /* Reusing styles from admin.php */
        :root { --space-cadet: #2b2d42; --cool-gray: #8d99ae; --antiflash-white: #edf2f4; --red-pantone: #ef233c; --fire-engine-red: #d90429; }
        body { font-family: 'Segoe UI', sans-serif; margin: 0; padding: 20px; background: var(--space-cadet); color: var(--antiflash-white); }
        .back-link { display: block; max-width: 860px; margin: 0 auto 20px auto; text-align: right; font-weight: bold; color: var(--antiflash-white); text-decoration: none; }
        .back-link:hover { text-decoration: underline; }
        .container { max-width: 600px; margin: 20px auto; padding: 30px; background: rgba(141, 153, 174, 0.1); border-radius: 15px; border: 1px solid rgba(141, 153, 174, 0.2); }
        h2 { text-align: center; margin-bottom: 20px; }
        form { display: flex; flex-direction: column; gap: 10px; }
        .form-row { display: flex; gap: 10px; } /* For semester and section */
        .form-row .form-group { flex: 1; } /* Each takes half the space */
        label { display: block; margin-bottom: 5px; font-weight: 600; }
        input[type="text"], input[type="email"], input[type="date"], input[type="password"], textarea, select {
            width: 100%; padding: 10px; margin-bottom: 10px; border-radius: 5px; border: 1px solid var(--cool-gray); background: rgba(43, 45, 66, 0.5); color: var(--antiflash-white); box-sizing: border-box;
        }
        textarea { resize: vertical; min-height: 80px; }
        button { padding: 12px 20px; border: none; border-radius: 5px; background-color: var(--fire-engine-red); color: var(--antiflash-white); font-weight: bold; cursor: pointer; width: 100%; font-size: 1.1em; margin-top: 10px; }
        button:hover { background-color: var(--red-pantone); }
        .message { padding: 10px; border-radius: 5px; margin-bottom: 1em; text-align: center; font-weight: bold; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>
    <a href="/admin-panel.php" class="back-link">&laquo; Back to Admin Dashboard</a>
    <div class="container">
        <h2>Add New Student</h2>
        <?php if (!empty($message)) echo $message; ?>

        <form action="add-student.php" method="POST">
            <label for="usn">USN:</label>
            <input type="text" id="usn" name="usn" required>

            <label for="name">Full Name:</label>
            <input type="text" id="name" name="name" required>

            <label for="email">Email:</label>
            <input type="email" id="email" name="email" required>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="semester">Semester:</label>
                    <select id="semester" name="semester" required>
                        <option value="">-- Select --</option>
                        <?php for ($i = 1; $i <= 8; $i++): ?>
                            <option value="<?= $i ?>">Semester <?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="section">Section:</label>
                    <select id="section" name="section" required>
                        <option value="">-- Select --</option>
                        <option value="A">A Section</option>
                        <option value="B">B Section</option>
                        <option value="C">C Section</option>
                        <option value="D">D Section</option>
                    </select>
                </div>
            </div>

            <label for="dob">Date of Birth:</label>
            <input type="date" id="dob" name="dob" required>

            <label for="address">Address:</label>
            <textarea id="address" name="address"></textarea>

            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required>

            <button type="submit">Add Student</button>
        </form>
    </div>
</body>
</html>
