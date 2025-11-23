<?php
session_start(); // enable sessions if you use them (uncomment if needed)
// require auth here if needed
// if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { header("Location: /login"); exit; }

require_once('db.php'); // $pdo should be a PDO instance from db.php

$message = ''; // For success/error messages

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['allocate_subject'])) {
    // Validate and sanitize numeric inputs
    $staff_id   = filter_input(INPUT_POST, 'staff_id', FILTER_VALIDATE_INT);
    $subject_id = filter_input(INPUT_POST, 'subject_id', FILTER_VALIDATE_INT);
    $class_id   = filter_input(INPUT_POST, 'class_id', FILTER_VALIDATE_INT);

    if ($staff_id && $subject_id && $class_id) {
        try {
            // Ensure your table has a suitable unique/index on (staff_id, subject_id, class_id)
            $sql = "INSERT INTO subject_allocation (staff_id, subject_id, class_id)
                    VALUES (:staff_id, :subject_id, :class_id)
                    ON CONFLICT (staff_id, subject_id, class_id) DO NOTHING";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':staff_id'   => $staff_id,
                ':subject_id' => $subject_id,
                ':class_id'   => $class_id
            ]);

            if ($stmt->rowCount() > 0) {
                $message = "<p class='message success'>Subject allocated successfully!</p>";
            } else {
                $message = "<p class='message error'>This subject is already allocated to this staff for this class.</p>";
            }
        } catch (PDOException $e) {
            // In production, log the error rather than showing raw message
            $message = "<p class='message error'>Database error: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    } else {
        $message = "<p class='message error'>Invalid input. Please select a class, subject and staff member.</p>";
    }
}

// --- Fetch Data for Dropdowns ---
try {
    // Semesters
    $sem_stmt = $pdo->query("SELECT id, name FROM semesters ORDER BY name");
    $semesters = $sem_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Classes grouped by semester (map NULL -> 'unassigned' key)
    $class_stmt = $pdo->query("SELECT id, name, semester_id FROM classes ORDER BY name");
    $classes_by_semester = [];
    while ($class = $class_stmt->fetch(PDO::FETCH_ASSOC)) {
        $semKey = ($class['semester_id'] === null) ? 'unassigned' : (string)$class['semester_id'];
        if (!isset($classes_by_semester[$semKey])) $classes_by_semester[$semKey] = [];
        $classes_by_semester[$semKey][] = [
            'id' => (int)$class['id'],
            'name' => $class['name']
        ];
    }

    // Subjects grouped by semester (map NULL -> 'unassigned')
    $subject_stmt = $pdo->query("
        SELECT id, name AS subject_name, subject_code, semester_id
        FROM subjects
        ORDER BY subject_code
    ");
    $subjects_by_semester = [];
    while ($subject = $subject_stmt->fetch(PDO::FETCH_ASSOC)) {
        $semKey = ($subject['semester_id'] === null) ? 'unassigned' : (string)$subject['semester_id'];
        if (!isset($subjects_by_semester[$semKey])) $subjects_by_semester[$semKey] = [];
        $subjects_by_semester[$semKey][] = [
            'id' => (int)$subject['id'],
            'subject_name' => $subject['subject_name'],
            'subject_code' => $subject['subject_code']
        ];
    }

    // Staff members
    $staff_stmt = $pdo->query("SELECT id, first_name, surname FROM users WHERE role = 'staff' ORDER BY first_name");
    $staff_members = $staff_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // fatal error fetching data
    die("Error fetching data: " . htmlspecialchars($e->getMessage()));
}

// JSON encode safely for JS consumption
$classes_json  = json_encode($classes_by_semester, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP);
$subjects_json = json_encode($subjects_by_semester, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Allocate Subject to Staff</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        :root { --space-cadet: #2b2d42; --cool-gray: #8d99ae; --antiflash-white: #edf2f4; --red-pantone: #ef233c; --fire-engine-red: #d90429; }
        body { font-family: 'Segoe UI', Tahoma, Arial, sans-serif; margin: 0; padding: 20px; background: var(--space-cadet); color: var(--antiflash-white); }
        .back-link { display: block; max-width: 860px; margin: 0 auto 20px auto; text-align: right; font-weight: bold; color: var(--antiflash-white); text-decoration: none; }
        .back-link:hover { text-decoration: underline; }
        .container { max-width: 600px; margin: 20px auto; padding: 30px; background: rgba(141,153,174,0.08); border-radius: 15px; border: 1px solid rgba(141,153,174,0.12); }
        h2 { text-align: center; margin-bottom: 20px; }
        form { display: flex; flex-direction: column; gap: 10px; }
        label { display: block; margin-bottom: 5px; font-weight: 600; }
        select, input[type="text"], input[type="number"] { width: 100%; padding: 10px; margin-bottom: 10px; border-radius: 5px; border: 1px solid var(--cool-gray); background: rgba(43,45,66,0.5); color: var(--antiflash-white); box-sizing: border-box; }
        select:disabled { background: rgba(43,45,66,0.2); color: var(--cool-gray); }
        button { padding: 12px 20px; border: none; border-radius: 5px; background-color: var(--fire-engine-red); color: var(--antiflash-white); font-weight: bold; cursor: pointer; width: 100%; font-size: 1.05em; margin-top: 10px; }
        button:hover { background-color: var(--red-pantone); }
        .message { padding: 10px; border-radius: 5px; margin-bottom: 1em; text-align: center; font-weight: bold; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        small.note { display:block; margin-top:6px; color:var(--cool-gray); }
    </style>
</head>
<body>
    <a href="/admin" class="back-link">&laquo; Back to Admin Dashboard</a>
    <div class="container">
        <h2>Allocate Subject to Staff</h2>

        <?php if (!empty($message)) echo $message; ?>

        <form action="subject-allocation.php" method="POST" id="allocForm">
            <label for="semester_id">Select Semester:</label>
            <select name="semester_id" id="semester_id" required>
                <option value="">-- Select a Semester --</option>
                <?php foreach ($semesters as $semester): ?>
                    <option value="<?= htmlspecialchars($semester['id']) ?>">
                        <?= htmlspecialchars($semester['name']) ?>
                    </option>
                <?php endforeach; ?>
                <option value="unassigned">-- Unassigned / No Semester --</option>
            </select>

            <label for="class_id">Select Class / Section:</label>
            <select name="class_id" id="class_id" required disabled>
                <option value="">-- First Select a Semester --</option>
            </select>

            <label for="subject_id">Select Subject:</label>
            <select name="subject_id" id="subject_id" required disabled>
                <option value="">-- First Select a Semester --</option>
            </select>

            <label for="staff_id">Select Staff:</label>
            <select name="staff_id" id="staff_id" required>
                <option value="">-- Select Staff --</option>
                <?php foreach ($staff_members as $staff): ?>
                    <option value="<?= htmlspecialchars($staff['id']) ?>">
                        <?= htmlspecialchars($staff['first_name'] . ' ' . $staff['surname']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit" name="allocate_subject">Allocate Subject</button>
        </form>
    </div>

    <script>
        // Data from server
        const classesBySemester  = <?= $classes_json ?: '{}' ?>;
        const subjectsBySemester = <?= $subjects_json ?: '{}' ?>;

        // Elements
        const semesterSelect = document.getElementById('semester_id');
        const classSelect    = document.getElementById('class_id');
        const subjectSelect  = document.getElementById('subject_id');

        // Utility to reset a select element
        function resetSelect(selectEl, placeholderText) {
            selectEl.innerHTML = '';
            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = placeholderText;
            selectEl.appendChild(placeholder);
        }

        // initialize
        resetSelect(classSelect, '-- First Select a Semester --');
        resetSelect(subjectSelect, '-- First Select a Semester --');
        classSelect.disabled = true;
        subjectSelect.disabled = true;

        // debug: show loaded data in console (remove in production)
        console.log('classesBySemester:', classesBySemester);
        console.log('subjectsBySemester:', subjectsBySemester);

        semesterSelect.addEventListener('change', function() {
            const rawValue = this.value;
            // Normalize to string keys: use 'unassigned' for the explicit unassigned option
            const selectedSemesterId = (rawValue === 'unassigned') ? 'unassigned' : String(rawValue);

            // Reset selects
            resetSelect(classSelect, '-- Select a Class --');
            resetSelect(subjectSelect, '-- Select a Subject --');

            // Enable/disable selects
            const enabled = !!selectedSemesterId;
            classSelect.disabled = !enabled;
            subjectSelect.disabled = !enabled;

            // Populate classes
            if (enabled && classesBySemester[selectedSemesterId] && classesBySemester[selectedSemesterId].length) {
                classesBySemester[selectedSemesterId].forEach(function(cls) {
                    const opt = document.createElement('option');
                    opt.value = cls.id;
                    opt.textContent = cls.name;
                    classSelect.appendChild(opt);
                });
            } else if (enabled) {
                resetSelect(classSelect, '-- No classes found for this semester --');
            }

            // Populate subjects
            if (enabled && subjectsBySemester[selectedSemesterId] && subjectsBySemester[selectedSemesterId].length) {
                subjectsBySemester[selectedSemesterId].forEach(function(sub) {
                    const opt = document.createElement('option');
                    opt.value = sub.id;
                    const code = sub.subject_code ? (sub.subject_code + ' - ') : '';
                    opt.textContent = code + sub.subject_name;
                    subjectSelect.appendChild(opt);
                });
            } else if (enabled) {
                resetSelect(subjectSelect, '-- No subjects found for this semester --');
            }
        });

        // Optional: client-side validation before submit (ensures selects not disabled)
        document.getElementById('allocForm').addEventListener('submit', function(e) {
            if (classSelect.disabled || subjectSelect.disabled) {
                e.preventDefault();
                alert('Please select a semester first so classes and subjects are loaded.');
            }
        });
    </script>
</body>
</html>
