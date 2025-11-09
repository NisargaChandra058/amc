<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once('db.php'); // Make sure this connects to PostgreSQL via PDO

// --- Check if student is logged in ---
if (!isset($_SESSION['student_id'])) {
    header('Location: student-login.php');
    exit;
}

$student_id = $_SESSION['student_id'];

// --- Ensure test ID ---
$test_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// --- Setup tables and sample test ---
try {
    // Create question_papers table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS question_papers (
            id SERIAL PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            content TEXT NOT NULL,
            staff_id INT DEFAULT 1,
            subject_id INT DEFAULT 1
        )
    ");

    // Create ia_results table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ia_results (
            id SERIAL PRIMARY KEY,
            student_id INT NOT NULL,
            qp_id INT NOT NULL,
            marks INT,
            content TEXT,
            UNIQUE(student_id, qp_id)
        )
    ");

    // Insert a sample test if none exists
    $stmt = $pdo->query("SELECT COUNT(*) FROM question_papers");
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("
            INSERT INTO question_papers (title, content, staff_id, subject_id)
            VALUES (
                'Sample Test',
                '1. What is PHP?\n2. Explain sessions.\n3. Write a SQL query to select all students.',
                1,
                1
            )
        ");
    }

    // If no ID provided, pick first test automatically
    if (!$test_id) {
        $stmt = $pdo->query("SELECT id FROM question_papers ORDER BY id ASC LIMIT 1");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $test_id = $row['id'] ?? 0;
    }

    if (!$test_id) {
        die("No test available.");
    }

    // --- Handle test submission ---
    $message = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $answers = $_POST['answers'] ?? 'No answer provided.';
        $marks = rand(70, 95); // placeholder marks

        $sql = "INSERT INTO ia_results (student_id, qp_id, marks, content)
                VALUES (:student_id, :qp_id, :marks, :content)
                ON CONFLICT (student_id, qp_id)
                DO UPDATE SET marks = EXCLUDED.marks, content = EXCLUDED.content";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':student_id' => $student_id,
            ':qp_id' => $test_id,
            ':marks' => $marks,
            ':content' => $answers
        ]);

        $message = "<p style='color:green;'>Test submitted successfully! <a href='dashboard.php'>Back to Dashboard</a></p>";
    }

    // --- Fetch test content ---
    $stmt = $pdo->prepare("SELECT * FROM question_papers WHERE id = :id");
    $stmt->execute([':id' => $test_id]);
    $test = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$test) {
        die("Test not found.");
    }

    // --- Check if already submitted ---
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
<style>
body { font-family: Arial, sans-serif; padding: 20px; background: #f0f0f0; }
.container { max-width: 800px; margin: auto; background: #fff; padding: 20px; border-radius: 8px; }
textarea { width: 100%; height: 200px; }
button { padding: 10px 20px; margin-top: 10px; }
.message { font-weight: bold; margin-bottom: 15px; }
</style>
</head>
<body>
<div class="container">
    <a href="dashboard.php">&laquo; Back to Dashboard</a>
    <h1><?= htmlspecialchars($test['title']) ?></h1>
    <div style="border:1px solid #ccc; padding:15px; margin-bottom:20px;">
        <?= nl2br(htmlspecialchars($test['content'])) ?>
    </div>

    <?php if ($message): ?>
        <div class="message"><?= $message ?></div>
    <?php elseif (!$submitted): ?>
        <form method="POST">
            <label for="answers">Your Answers:</label><br>
            <textarea name="answers" id="answers" placeholder="Type your answers here..."></textarea><br>
            <button type="submit">Submit Test</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
