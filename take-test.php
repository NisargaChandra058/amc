<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once('db.php'); // Make sure this file connects to DB via PDO

// --- Check if student is logged in ---
if (!isset($_SESSION['student_id'])) {
    header('Location: student-login.php');
    exit;
}

$student_id = $_SESSION['student_id'];
$test_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$test_id) {
    die("Invalid Test ID specified.");
}

$message = '';

try {
    // --- Create tables if they don't exist ---
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS question_papers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            content TEXT NOT NULL
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ia_results (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            qp_id INT NOT NULL,
            marks INT,
            content TEXT,
            UNIQUE(student_id, qp_id)
        )
    ");

    // --- Handle Test Submission ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $answers = $_POST['answers'] ?? 'No answer provided.';
        $marks = rand(70, 95); // placeholder marks

        $sql = "INSERT INTO ia_results (student_id, qp_id, marks, content)
                VALUES (:student_id, :qp_id, :marks, :content)
                ON DUPLICATE KEY UPDATE
                marks = VALUES(marks), content = VALUES(content)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':student_id' => $student_id,
            ':qp_id' => $test_id,
            ':marks' => $marks,
            ':content' => $answers
        ]);

        $message = "<p style='color:green;'>Test submitted successfully! <a href='dashboard.php'>Back to Dashboard</a></p>";
    }

    // --- Fetch Test Content ---
    $stmt = $pdo->prepare("SELECT title, content FROM question_papers WHERE id = :id");
    $stmt->execute([':id' => $test_id]);
    $test = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$test) {
        die("Test not found.");
    }

    // --- Check if test already submitted ---
    $stmt = $pdo->prepare("SELECT id FROM ia_results WHERE student_id = :student_id AND qp_id = :qp_id");
    $stmt->execute([':student_id' => $student_id, ':qp_id' => $test_id]);
    $submitted = $stmt->fetch();

    if ($submitted) {
        $message = "<p style='color:red;'>You have already submitted this test. <a href='dashboard.php'>Back to Dashboard</a></p>";
    }

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Take Test: <?= htmlspecialchars($test['title']) ?></title>
</head>
<body style="font-family:Arial; padding:20px;">

<a href="dashboard.php">&laquo; Back to Dashboard</a>

<h1><?= htmlspecialchars($test['title']) ?></h1>
<div style="border:1px solid #ccc; padding:15px; margin-bottom:20px;">
    <?= nl2br(htmlspecialchars($test['content'])) ?>
</div>

<?php if ($message): ?>
    <?= $message ?>
<?php elseif (!$submitted): ?>
    <form method="POST">
        <label for="answers">Your Answers:</label><br>
        <textarea id="answers" name="answers" rows="10" style="width:100%;"></textarea><br><br>
        <button type="submit">Submit Test</button>
    </form>
<?php endif; ?>

</body>
</html>
