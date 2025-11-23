<?php
// view-result.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once('db.php'); // $pdo PDO

// --- Config ---
$PASS_PERCENT = 40; // pass threshold percent (change as needed)

// --- Auth ---
if (!isset($_SESSION['student_id'])) {
    header('Location: student-login.php');
    exit;
}
$student_id = (int) $_SESSION['student_id'];

// --- Input ---
$test_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$test_id) {
    die("Invalid test id.");
}

try {
    // Fetch saved submission
    $resStmt = $pdo->prepare("SELECT id, marks, content, created_at, updated_at FROM ia_results WHERE student_id = :sid AND qp_id = :qid LIMIT 1");
    $resStmt->execute([':sid' => $student_id, ':qid' => $test_id]);
    $submission = $resStmt->fetch(PDO::FETCH_ASSOC);

    if (!$submission) {
        // no submission
        $noSubmission = true;
    } else {
        $noSubmission = false;
        $saved_marks = is_null($submission['marks']) ? null : (int)$submission['marks'];
        $saved_content = $submission['content'] ?? '';

        // decode student's answers (could be JSON array or object)
        $student_answers = json_decode($saved_content, true);
        if ($student_answers === null && $saved_content !== '') {
            // fallback: maybe content stored as plain text
            $student_answers = $saved_content;
        }
    }

    // Fetch test questions
    $qStmt = $pdo->prepare("SELECT id, title, content FROM question_papers WHERE id = :id LIMIT 1");
    $qStmt->execute([':id' => $test_id]);
    $test = $qStmt->fetch(PDO::FETCH_ASSOC);

    if (!$test) {
        throw new Exception("Test not found.");
    }

    $questions = json_decode($test['content'], true);
    if (!is_array($questions)) {
        throw new Exception("Invalid test content format. Expecting JSON array of questions.");
    }

    // Compute max marks from questions array (if marks provided in each question)
    $max_marks = 0;
    foreach ($questions as $q) {
        $q_marks = isset($q['marks']) ? (int)$q['marks'] : 1; // default 1 mark if not specified
        $max_marks += $q_marks;
    }

    // Recompute marks by checking answers (useful if stored marks missing)
    $recomputed_marks = 0;
    $per_question = []; // store per-question details
    foreach ($questions as $idx => $q) {
        $correct = isset($q['correct']) ? (string)$q['correct'] : null;
        $q_marks = isset($q['marks']) ? (int)$q['marks'] : 1;
        $student_choice = null;

        // Answers may be an associative array keyed by index or numeric array
        if (is_array($student_answers)) {
            // try numeric index
            if (array_key_exists($idx, $student_answers)) {
                $student_choice = $student_answers[$idx];
            } else {
                // maybe student_answers is an associative array with string keys
                // try casting idx to string
                $idxKey = (string)$idx;
                if (array_key_exists($idxKey, $student_answers)) {
                    $student_choice = $student_answers[$idxKey];
                }
            }
        } else {
            // student_answers is plain text (essay) - treat as answer string for first question
            if ($idx === 0 && is_string($student_answers)) {
                $student_choice = $student_answers;
            }
        }

        $is_correct = false;
        if ($correct !== null && $student_choice !== null) {
            // compare strictly as string
            if ((string)$student_choice === (string)$correct) {
                $is_correct = true;
                $recomputed_marks += $q_marks;
            }
        } else {
            // if no 'correct' provided (subjective), we can't auto-grade; leave is_correct null
            $is_correct = null;
        }

        $per_question[$idx] = [
            'question' => $q['question'] ?? '',
            'options' => $q['options'] ?? null,
            'correct' => $correct,
            'student' => $student_choice,
            'marks' => $q_marks,
            'is_correct' => $is_correct
        ];
    }

    // Determine final marks to show: prefer saved marks if present, else recomputed
    $display_marks = is_null($saved_marks) ? $recomputed_marks : $saved_marks;
    $percentage = ($max_marks > 0) ? round(($display_marks / $max_marks) * 100, 2) : 0;
    $passed = $percentage >= $PASS_PERCENT;

} catch (Exception $e) {
    die("Error: " . htmlspecialchars($e->getMessage()));
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<title>Result — <?= htmlspecialchars($test['title']) ?></title>
<meta name="viewport" content="width=device-width,initial-scale=1" />
<style>
    :root { --space-cadet:#2b2d42; --antiflash-white:#edf2f4; --muted:#8d99ae; --accent:#d90429; }
    body{font-family:Arial,Helvetica,sans-serif;background:var(--space-cadet);color:var(--antiflash-white);margin:0;padding:20px;}
    .wrap{max-width:900px;margin:18px auto;}
    .card{background:rgba(141,153,174,0.06);padding:18px;border-radius:10px;border:1px solid rgba(141,153,174,0.12);}
    h1{margin:0 0 8px}
    .meta{color:var(--muted);margin-bottom:12px}
    .score-box{background:#fff;color:#111;padding:12px;border-radius:8px;margin-bottom:16px}
    .q{background:#fff;color:#111;padding:12px;border-radius:6px;margin-bottom:10px}
    .q .q-head{font-weight:700;margin-bottom:8px}
    .option{margin-left:12px}
    .correct{color:green;font-weight:700}
    .wrong{color:#b52d3b;font-weight:700}
    .note{color:var(--muted);font-size:0.95rem}
    .back{display:inline-block;margin-top:10px;color:var(--muted);text-decoration:none}
</style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h1>Result: <?= htmlspecialchars($test['title']) ?></h1>
        <div class="meta">Student ID: <?= htmlspecialchars($student_id) ?> &nbsp; | &nbsp; Test ID: <?= htmlspecialchars($test_id) ?></div>

        <?php if ($noSubmission): ?>
            <div class="score-box">
                <strong>No submission found.</strong>
                <p class="note">You haven't submitted this test yet. <a href="take-test.php?id=<?= htmlspecialchars($test_id) ?>">Take the test now</a>.</p>
            </div>
        <?php else: ?>
            <div class="score-box">
                <div><strong>Marks:</strong> <?= htmlspecialchars($display_marks) ?> / <?= htmlspecialchars($max_marks) ?></div>
                <div><strong>Percentage:</strong> <?= htmlspecialchars($percentage) ?>%</div>
                <div><strong>Result:</strong>
                    <?php if ($passed): ?>
                        <span class="correct">Passed</span>
                    <?php else: ?>
                        <span class="wrong">Failed</span>
                    <?php endif; ?>
                </div>
                <?php if (!empty($submission['updated_at'])): ?>
                    <div class="meta">Submitted: <?= htmlspecialchars($submission['updated_at']) ?></div>
                <?php elseif (!empty($submission['created_at'])): ?>
                    <div class="meta">Submitted: <?= htmlspecialchars($submission['created_at']) ?></div>
                <?php endif; ?>
            </div>

            <!-- Per-question breakdown -->
            <?php foreach ($per_question as $i => $pq): ?>
                <div class="q">
                    <div class="q-head">Q<?= $i + 1 ?>. <?= htmlspecialchars($pq['question']) ?> <span style="font-weight:600"> (<?= $pq['marks'] ?> mark<?= $pq['marks'] > 1 ? 's':'' ?>)</span></div>

                    <?php if (is_array($pq['options']) && count($pq['options'])): ?>
                        <?php foreach ($pq['options'] as $optKey => $optText): ?>
                            <?php
                                $isStudent = ((string)$optKey === (string)$pq['student']);
                                $isCorrectOpt = ($pq['correct'] !== null && (string)$optKey === (string)$pq['correct']);
                                $cls = $isCorrectOpt ? 'correct' : ($isStudent && !$isCorrectOpt ? 'wrong' : '');
                            ?>
                            <div class="option">
                                <span class="<?= $cls ?>"><?= htmlspecialchars($optKey) ?>. <?= htmlspecialchars($optText) ?></span>
                                <?php if ($isStudent): ?>
                                    <strong> &nbsp; ← Your answer</strong>
                                <?php endif; ?>
                                <?php if ($isCorrectOpt): ?>
                                    <strong> &nbsp; ← Correct answer</strong>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>

                    <?php else: ?>
                        <!-- No options (subjective) -->
                        <div><strong>Your answer:</strong></div>
                        <div style="background:#f8f8f8;color:#111;padding:10px;border-radius:6px;margin-top:6px;"><?= nl2br(htmlspecialchars((string)$pq['student'])) ?></div>
                        <?php if ($pq['correct'] !== null): ?>
                            <div style="margin-top:8px"><strong>Expected/Model answer:</strong> <?= nl2br(htmlspecialchars((string)$pq['correct'])) ?></div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <!-- show correctness & awarded marks -->
                    <div style="margin-top:8px">
                        <?php if ($pq['is_correct'] === true): ?>
                            <span class="correct">Correct</span> — awarded <?= $pq['marks'] ?> mark<?= $pq['marks'] > 1 ? 's':'' ?>.
                        <?php elseif ($pq['is_correct'] === false): ?>
                            <span class="wrong">Incorrect</span> — awarded 0 marks.
                        <?php else: ?>
                            <span class="note">Not auto-graded (subjective)</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <a class="back" href="student-dashboard.php">&laquo; Back to Dashboard</a>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
