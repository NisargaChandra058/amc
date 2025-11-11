<?php
session_save_path('/var/www/sessions');
session_start();
require_once('db.php'); // Use our PDO connection ($pdo)

// Check if student is logged in
if (!isset($_SESSION['student_id'])) {
    header('Location: student-login.php'); // Redirect to login if not logged in
    exit;
}

$student_id = $_SESSION['student_id'];
$tests = [];
$student_name = 'Student';

try {
    // Fetch student's name, email, and class_id
    $stmt = $pdo->prepare("SELECT student_name, email, class_id FROM students WHERE id = :id");
    $stmt->execute([':id' => $student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($student) {
        $student_name = $student['student_name'];
        if ($student['class_id']) {
            // Fetch all tests allocated to this student's class
            $test_stmt = $pdo->prepare("
                SELECT qp.id, qp.title 
                FROM test_allocation ta
                JOIN question_papers qp ON ta.qp_id = qp.id
                WHERE ta.class_id = :class_id
            ");
            $test_stmt->execute([':class_id' => $student['class_id']]);
            $tests = $test_stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

} catch (PDOException $e) {
    die("Database error: " . htmlspecialchars($e->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        :root { --space-cadet: #2b2d42; --cool-gray: #8d99ae; --antiflash-white: #edf2f4; --red-pantone: #ef233c; --fire-engine-red: #d90429; }
        body { font-family: 'Segoe UI', sans-serif; margin: 0; padding: 20px; background: var(--space-cadet); color: var(--antiflash-white); }
        .navbar { display: flex; justify-content: space-between; align-items: center; max-width: 1000px; margin: 0 auto 20px auto; padding: 10px 20px; background: rgba(141, 153, 174, 0.1); border-radius: 10px; }
        .navbar h1 { margin: 0; font-size: 1.5em; }
        .logout-btn { display: inline-block; padding: 8px 12px; background-color: var(--fire-engine-red); color: white; text-decoration: none; border-radius: 5px; font-weight: bold; }
        .container { max-width: 1000px; margin: 20px auto; padding: 30px; background: rgba(141, 153, 174, 0.1); border-radius: 15px; border: 1px solid rgba(141, 153, 174, 0.2); }
        .test-list { list-style: none; padding: 0; }
        .test-list li { background: rgba(43, 45, 66, 0.5); border: 1px solid var(--cool-gray); border-radius: 8px; margin-bottom: 10px; }
        .test-list a { display: block; padding: 20px; text-decoration: none; color: var(--antiflash-white); font-weight: bold; font-size: 1.2em; }
        .test-list a:hover { background: rgba(141, 153, 174, 0.2); }
        .no-tests { color: var(--cool-gray); }

        /* --- THIS IS THE NEW STYLE --- */
        .results-btn {
            display: inline-block;
            padding: 12px 20px;
            background-color: #007bff; /* Blue */
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            margin-bottom: 20px;
        }
        .results-btn:hover {
            background-color: #0056b3;
        }
        /* --- END OF NEW STYLE --- */
    </style>
</head>
<body>
    <div class="navbar">
        <h1>Welcome, <?= htmlspecialchars($student_name) ?>!</h1>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>

    <div class="container">
        <h2>My Dashboard</h2>
        
        <!-- --- THIS IS THE NEW BUTTON --- -->
        <a href="ia-results.php" class="results-btn">View My Results</a>
        <!-- --- END OF NEW BUTTON --- -->

        <h3 style="margin-top: 20px; border-bottom: 1px solid var(--cool-gray); padding-bottom: 10px;">Available Tests</h3>
        <ul class="test-list">
            <?php if (empty($tests)): ?>
                <li class="no-tests" style="padding: 20px;">You have no new tests assigned at this time.</li>
            <?php else: ?>
                <?php foreach ($tests as $test): ?>
                    <li>
                        <!-- This link is correct -->
                        <a href="take-test.php?id=<?= htmlspecialchars($test['id']) ?>">
                            Take Test: <?= htmlspecialchars($test['title']) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>
    </div>
</body>
</html>
