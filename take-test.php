<?php
error_reporting(E_ALL);
ini_set('display_errors', 1); // Good for debugging, remove in production

session_save_path('/var/www/sessions');
session_start();
require_once('db.php'); // Make sure this connects to PostgreSQL via PDO

// --- Check if student is logged in ---
if (!isset($_SESSION['student_id'])) {
    header('Location: student-login.php');
    exit;
}

$student_id = $_SESSION['student_id'];

// --- Ensure test ID is provided ---
$test_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$test_id) {
    die("Invalid Test ID specified.");
}

$message = '';
$test = null;
$questions = [];

try {
    // --- Handle test submission ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $submitted_answers = $_POST['answers'] ?? [];
        
        // Fetch the test again to get the correct answers for grading
        $stmt = $pdo->prepare("SELECT content FROM question_papers WHERE id = :id");
        $stmt->execute([':id' => $test_id]);
        $test_content = $stmt->fetchColumn();
        
        $questions = json_decode($test_content, true);
        $total_marks = 0;

        if (is_array($questions)) {
            foreach ($questions as $index => $q) {
                // Check if an answer was submitted for this question
                if (isset($submitted_answers[$index])) {
                    // Check if the submitted answer (e.g., "B") matches the correct answer
                    if ($submitted_answers[$index] === $q['correct']) {
                        $total_marks += (int)$q['marks']; // Add the marks for this question
                    }
                }
            }
        }
        
        // Save the results
        $answers_json = json_encode($submitted_answers); // Store what the student answered
        
        $sql = "INSERT INTO ia_results (student_id, qp_id, marks, content)
                VALUES (:student_id, :qp_id, :marks, :content)
                ON CONFLICT (student_id, qp_id)
                DO UPDATE SET marks = EXCLUDED.marks, content = EXCLUDED.content";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':student_id' => $student_id,
            ':qp_id' => $test_id,
            ':marks' => $total_marks,
            ':content' => $answers_json
        ]);
        
        $message = "<p class='message success'>Test submitted successfully! You scored $total_marks. <a href='student-dashboard.php'>Back to Dashboard</a></p>";
    }

    // --- Fetch test content for display ---
    $stmt = $pdo->prepare("SELECT title, content FROM question_papers WHERE id = :id");
    $stmt->execute([':id' => $test_id]);
    $test = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$test) {
        die("Test not found.");
    }

    // --- Check if already submitted ---
    $stmt = $pdo->prepare("SELECT id, marks FROM ia_results WHERE student_id = :student_id AND qp_id = :qp_id");
    $stmt->execute([':student_id' => $student_id, ':qp_id' => $test_id]);
    $submitted = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($submitted && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        $message = "<p class='message error'>You have already submitted this test and scored {$submitted['marks']}. <a href='student-dashboard.php'>Back to Dashboard</a></p>";
    }

    // --- Decode JSON for rendering ---
    $questions = json_decode($test['content'], true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        // If content is not JSON, treat it as plain text (fallback)
        $questions = null;
    }

} catch (PDOException $e) {
    // --- THIS IS THE FIX ---
    die(sprintf("Database error: %s", $e->getMessage()));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Take Test: <?= htmlspecialchars($test['title']) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
    :root { --space-cadet: #2b2d42; --cool-gray: #8d99ae; --antiflash-white: #edf2f4; --red-pantone: #ef233c; --fire-engine-red: #d90429; }
    body { font-family: 'Segoe UI', sans-serif; margin: 0; padding: 20px; background: var(--space-cadet); color: var(--antiflash-white); }
    .back-link { display: block; max-width: 1000px; margin: 0 auto 20px auto; text-align: right; font-weight: bold; color: var(--antiflash-white); text-decoration: none; }
    .back-link:hover { text-decoration: underline; }
    .container { max-width: 1000px; margin: 20px auto; padding: 30px; background: rgba(141, 153, 174, 0.1); border-radius: 15px; border: 1px solid rgba(141, 153, 174, 0.2); }
    h1 { margin-top: 0; }
    
    .question-paper { background: #fff; color: #333; padding: 30px; border-radius: 8px; }
    .question-block { margin-bottom: 25px; border-bottom: 1px solid #eee; padding-bottom: 15px; }
    .question-block h4 { font-size: 1.2em; margin-bottom: 10px; }
    .question-block p { font-size: 0.9em; color: #666; margin-bottom: 10px; }
    .options .option { margin-bottom: 8px; }
    .options label { font-weight: normal; margin-left: 8px; }

    textarea { width: 100%; min-height: 200px; padding: 10px; font-size: 1em; border: 1px solid var(--cool-gray); border-radius: 5px; margin-top: 20px; }
    button { padding: 12px 20px; border: none; border-radius: 5px; background-color: var(--fire-engine-red); color: var(--antiflash-white); font-weight: bold; cursor: pointer; width: 100%; font-size: 1.1em; margin-top: 20px; }
    button:hover { background-color: var(--red-pantone); }
    .message { padding: 15px; border-radius: 5px; margin-bottom: 1em; text-align: center; font-weight: bold; font-size: 1.1em; }
    .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    .message a { color: #0056b3; font-weight: bold; }
</style>
</head>
<body>
<div class="container">
    <a href="student-dashboard.php" class="back-link">&laquo; Back to Dashboard</a>
    <h1><?= htmlspecialchars($test['title']) ?></h1>

    <?php if ($message): ?>
        <div class="message <?= (strpos($message, 'success') !== false) ? 'success' : 'error' ?>">
            <?= $message ?>
        </div>
    <?php elseif (!$submitted): ?>
        
        <div class="question-paper">
            <form method="POST" action="take-test.php?id=<?= htmlspecialchars($test_id) ?>">
                <?php if (is_array($questions)): // Check if we have JSON questions ?>
                    <?php foreach ($questions as $index => $q): ?>
                        <div class="question-block">
                            <h4>Q<?= $index + 1 ?>: <?= htmlspecialchars($q['question']) ?></h4>
                            <p>Marks: <?= htmlspecialchars($q['marks']) ?></p>
                            <div class="options">
                                <?php foreach ($q['options'] as $key => $option): ?>
                                    <div class="option">
                                        <input type="radio" name="answers[<?= $index ?>]" value="<?= htmlspecialchars($key) ?>" id="q<?= $index ?>_<?= $key ?>" required>
                                        <label for="q<?= $index ?>_<?= $key ?>"><?= htmlspecialchars($option) ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: // Fallback if content is just plain text ?>
                    <div class="content">
                        <?= nl2br(htmlspecialchars($test['content'])) ?>
                    </div>
                <?php endif; ?>
                
                <button type="submit">Submit Test</button>
            </form>
        </div>

    <?php endif; ?>
</div>
</body>
</html>
