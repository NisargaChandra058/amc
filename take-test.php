<?php
// ---- Session setup ----
session_save_path('/var/www/sessions');
session_start();

require_once('db.php');

// ---- Check login ----
if (!isset($_SESSION['student_id'])) {
    die("Session not found! You were redirected to login. 
        <br><a href='student-login.php'>Go to Login</a>");
}

$student_id = $_SESSION['student_id'];
$test_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$test_id) {
    die("Invalid test ID.");
}

// ---- Fetch test ----
$stmt = $pdo->prepare("SELECT title, content FROM question_papers WHERE id = :id");
$stmt->execute([':id' => $test_id]);
$test = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$test) {
    die("Test not found.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Take Test</title></head>
<body>
    <h2>Welcome Student #<?= htmlspecialchars($student_id) ?></h2>
    <h3><?= htmlspecialchars($test['title']) ?></h3>
    <p><?= nl2br(htmlspecialchars($test['content'])) ?></p>
    <form method="POST">
        <textarea name="answers" cols="50" rows="5"></textarea><br>
        <button type="submit">Submit</button>
    </form>
</body>
</html>
