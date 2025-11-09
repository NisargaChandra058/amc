<?php
session_save_path('/var/www/sessions');
session_start();
require_once('db.php'); // Use PDO connection

// --- Security Check (Placeholder) ---
/*
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}
*/

$student_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$student = null;
$message = '';

if (!$student_id) {
    die("Invalid student ID.");
}

// --- Handle Form Update ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_student'])) {
    // Retrieve and sanitize form data
    $usn = trim(htmlspecialchars($_POST['usn'], ENT_QUOTES, 'UTF-8'));
    $name = trim(htmlspecialchars($_POST['student_name'], ENT_QUOTES, 'UTF-8'));
    $email = trim(htmlspecialchars($_POST['email'], ENT_QUOTES, 'UTF-8'));
    $semester = filter_input(INPUT_POST, 'semester', FILTER_VALIDATE_INT);
    $section = trim(htmlspecialchars($_POST['section'], ENT_QUOTES, 'UTF-8'));

    if (empty($usn) || empty($name) || empty($email) || empty($semester) || empty($section)) {
        $message = "<p class='message error'>All fields are required!</p>";
    } else {
        try {
            // Check for duplicate USN or Email, excluding the current student
            $check_stmt = $pdo->prepare("SELECT id FROM students WHERE (usn = :usn OR email = :email) AND id != :id LIMIT 1");
            $check_stmt->execute([':usn' => $usn, ':email' => $email, ':id' => $student_id]);
            
            if ($check_stmt->fetch()) {
                $message = "<p class='message error'>Error: USN or Email is already in use by another student.</p>";
            } else {
                // Update the student record
                $sql = "UPDATE students SET 
                            usn = :usn, 
                            student_name = :student_name, 
                            email = :email, 
                            semester = :semester,
                            section = :section
                        WHERE id = :id";
                
                $update_stmt = $pdo->prepare($sql);
                $update_stmt->execute([
                    ':usn' => $usn,
                    ':student_name' => $name,
                    ':email' => $email,
                    ':semester' => $semester,
                    ':section' => $section,
                    ':id' => $student_id
                ]);
                
                $message = "<p class='message success'>Student details updated successfully!</p>";
            }
        } catch (PDOException $e) {
            $message = "<p class='message error'>Database error: " . $e->getMessage() . "</p>";
        }
    }
}

// --- Fetch Student Details for the Form ---
try {
    // --- THIS IS THE FIX ---
    // Changed `name` to `student_name`
    $stmt = $pdo->prepare("SELECT id, usn, student_name, email, semester, section FROM students WHERE id = :id");
    // --- END OF FIX ---
    
    $stmt->bindParam(':id', $student_id, PDO::PARAM_INT);
    $stmt->execute();
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student && empty($message)) { // Only show "not found" if there isn't already another message
        $message = "<p class='message error'>No student record found.</p>";
    }
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Student Details</title>
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
        .form-row { display: flex; gap: 10px; }
        .form-row .form-group { flex: 1; }
        label { display: block; margin-bottom: 5px; font-weight: 600; }
        input[type="text"], input[type="email"], select {
            width: 100%; padding: 10px; margin-bottom: 10px; border-radius: 5px; border: 1px solid var(--cool-gray); background: rgba(43, 45, 66, 0.5); color: var(--antiflash-white); box-sizing: border-box;
        }
        button { padding: 12px 20px; border: none; border-radius: 5px; background-color: var(--fire-engine-red); color: var(--antiflash-white); font-weight: bold; cursor: pointer; width: 100%; font-size: 1.1em; margin-top: 10px; }
        button:hover { background-color: var(--red-pantone); }
        .message { padding: 10px; border-radius: 5px; margin-bottom: 1em; text-align: center; font-weight: bold; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>
    <a href="view-students.php" class="back-link">&laquo; Back to Students List</a>
    <div class="container">
        <h2>Edit Student Details</h2>
        <?php if (!empty($message)) echo $message; ?>

        <?php if ($student): ?>
        <form action="edit-student.php?id=<?= htmlspecialchars($student_id) ?>" method="POST">
            <label for="usn">USN:</label>
            <input type="text" id="usn" name="usn" value="<?= htmlspecialchars($student['usn'] ?? '') ?>" required>

            <label for="student_name">Full Name:</label>
            <input type="text" id="student_name" name="student_name" value="<?= htmlspecialchars($student['student_name'] ?? '') ?>" required>

            <label for="email">Email:</label>
            <input type="email" id="email" name="email" value="<?= htmlspecialchars($student['email'] ?? '') ?>" required>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="semester">Semester:</label>
                    <select id="semester" name="semester" required>
                        <option value="">-- Select --</option>
                        <?php for ($i = 1; $i <= 8; $i++): ?>
                            <option value="<?= $i ?>" <?= ($student['semester'] == $i) ? 'selected' : '' ?>>Semester <?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="section">Section:</label>
                    <select id="section" name="section" required>
                        <option value="">-- Select --</option>
                        <option value="A" <?= ($student['section'] == 'A') ? 'selected' : '' ?>>A Section</option>
                        <option value="B" <?= ($student['section'] == 'B') ? 'selected' : '' ?>>B Section</option>
                        <option value="C" <?= ($student['section'] == 'C') ? 'selected' : '' ?>>C Section</option>
                        <option value="D" <?= ($student['section'] == 'D') ? 'selected' : '' ?>>D Section</option>
                    </select>
                </div>
            </div>

            <button type="submit" name="update_student">Update Details</button>
        </form>
        <?php else: ?>
            <p style="text-align: center;">No student record was found.</p>
        <?php endif; ?>
    </div>
</body>
</html>
