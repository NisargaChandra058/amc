<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once('db.php'); // Your PDO connection

// Check if student is logged in
if (!isset($_SESSION['student_id'])) {
    header('Location: student-login.php');
    exit;
}

$student_id = $_SESSION['student_id'];
$test_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$message = '';

if (!$test_id) {
    die("Invalid Test ID specified.");
}

try {
    // Handle Test Submission
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $answers = $_POST['answers'] ?? [];
        $answers_json = json_encode($answers); // Store as JSON

        $marks = rand(70, 95); // Placeholder: random marks

        $sql = "INSERT INTO ia_results (student_id, qp_id, marks, content)
                VALUES (:student_id, :qp_id, :marks, :content)
                ON CONFLICT (student_id, qp_id) DO UPDATE SET
                marks = EXCLUDED.marks, content = EXCLUDED.content";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':student_id' => $student_id,
            ':qp_id' => $test_id,
            ':marks' => $marks,
            ':content' => $answers_json
        ]);

        $message = "<p class='message success'>Your test has been submitted successfully! <a href='student-dashboard.php'>Back to Dashboard</a></p>";
    }

    // Fetch Test Content
    $stmt = $pdo->prepare("SELECT title, content FROM question_papers WHERE id = :id");
    $stmt->execute([':id' => $test_id]);
    $test = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$test) {
        die("Test not found.");
    }

    // Decode JSON content
    $questions = json_decode($test['content'], true);
    if (!is_array($questions)) {
        die("Invalid test format.");
    }

    // Check if test already submitted
    $check_stmt = $pdo->prepare("SELECT id FROM ia_results WHERE student_id = :student_id AND qp_id = :qp_id");
    $check_stmt->execute([':student_id' => $student_id, ':qp_id' => $test_id]);
    if ($check_stmt->fetch()) {
        $message = "<p class='message error'>You have already submitted this test. <a href='student-dashboard.php'>Back to Dashboard</a></p>";
    }

} catch (PDOException $e) {
    die("Database error: " . htmlspecialchars($e->getMessage()));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Take Test: <?= htmlspecialchars($test['title']) ?></title>
<style>
body { font-family: sans-serif; background: #2b2d42; color: #edf2f4; padding: 20px; }
.container { max-width: 800px; margin: auto; background: #333; padding: 20px; border-radius: 8px; }
.question { margin-bottom: 20px; padding: 10px; background: #444; border-radius: 5px; }
.question h3 { margin: 0 0 10px 0; }
button { padding: 10px 20px; font-size: 16px; background: #d90429; color: #edf2f4; border: none; border-radius: 5px; cursor: pointer; }
.message { padding: 10px; margin-bottom: 20px; border-radius: 5px; }
.success { background: #d4edda; color: #155724; }
.error { background: #f8d7da; color: #721c24; }
</style>
</head>
<body>
<a href="student-dashboard.php" style="color:#edf2f4;">&laquo; Back to Dashboard</a>
<div class="container">
<?php if ($message): ?>
    <div class="message <?= (strpos($message, 'success') !== false) ? 'success' : 'error' ?>">
        <?= $message ?>
    </div>
<?php else: ?>
    <h1><?= htmlspecialchars($test['title']) ?></h1>
    <form method="POST">
        <?php foreach ($questions as $index => $q): ?>
            <div class="question">
                <h3><?= ($index+1) . '. ' . htmlspecialchars($q['question']) ?></h3>
                <?php foreach ($q['options'] as $key => $value): ?>
                    <label>
                        <input type="radio" name="answers[<?= $index ?>]" value="<?= htmlspecialchars($key) ?>">
                        <?= htmlspecialchars($key . '. ' . $value) ?>
                    </label><br>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
        <button type="submit">Submit Test</button>
    </form>
<?php endif; ?>
</div>
</body>
</html>
