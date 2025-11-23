<?php
session_start();
require_once('db.php'); // PDO $pdo

// Optional: restrict page to admin
/*
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}
*/

$message = '';           // General message (single insert)
$bulk_result = null;     // Results from bulk CSV upload

// DEFAULT PASSWORD FOR CSV ROWS WITH EMPTY PASSWORD (change as needed)
define('CSV_DEFAULT_PASSWORD', 'ChangeMe123'); // plain text; will be hashed before storing

// ---------- Single student insert ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['single_add'])) {
    // Retrieve and sanitize form data
    $usn = isset($_POST['usn']) ? trim($_POST['usn']) : '';
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $dob = isset($_POST['dob']) ? trim($_POST['dob']) : '';
    $address = isset($_POST['address']) ? trim($_POST['address']) : '';
    $password_plain = isset($_POST['password']) ? $_POST['password'] : '';
    $semester = isset($_POST['semester']) ? filter_input(INPUT_POST, 'semester', FILTER_VALIDATE_INT) : null;
    $section = isset($_POST['section']) ? trim($_POST['section']) : '';

    // Basic Validation
    if (empty($usn) || empty($name) || empty($email) || empty($dob) || empty($password_plain) || empty($semester) || empty($section)) {
        $message = "<p class='message error'>All fields are required for single student add.</p>";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "<p class='message error'>Invalid email format!</p>";
    } else {
        try {
            // Check duplicates
            $check_stmt = $pdo->prepare("SELECT usn FROM students WHERE usn = :usn OR email = :email LIMIT 1");
            $check_stmt->execute([':usn' => $usn, ':email' => $email]);
            if ($check_stmt->fetch()) {
                $message = "<p class='message error'>Error: USN or Email already exists!</p>";
            } else {
                $password_hashed = password_hash($password_plain, PASSWORD_DEFAULT);

                $insert_stmt = $pdo->prepare(
                    "INSERT INTO students (usn, student_name, email, dob, permanent_address, password, semester, section) 
                     VALUES (:usn, :student_name, :email, :dob, :address, :password, :semester, :section)"
                );
                $ok = $insert_stmt->execute([
                    ':usn' => $usn,
                    ':student_name' => $name,
                    ':email' => $email,
                    ':dob' => $dob,
                    ':address' => $address,
                    ':password' => $password_hashed,
                    ':semester' => $semester,
                    ':section' => $section
                ]);
                if ($ok) {
                    $message = "<p class='message success'>Student added successfully!</p>";
                } else {
                    $message = "<p class='message error'>Error adding student.</p>";
                }
            }
        } catch (PDOException $e) {
            // handle duplicate unique violation gracefully
            if ($e->getCode() === '23505') {
                $message = "<p class='message error'>Error: USN or Email already exists!</p>";
            } else {
                $message = "<p class='message error'>Database error: " . htmlspecialchars($e->getMessage()) . "</p>";
            }
        }
    }
}

// ---------- Bulk CSV upload handler ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_upload'])) {
    $bulk_result = [
        'inserted' => 0,
        'skipped'  => 0,
        'errors'   => [] // array of "row => message"
    ];

    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $bulk_result['errors'][] = "File upload error or no file uploaded.";
    } else {
        $fileTmp = $_FILES['csv_file']['tmp_name'];
        $fileName = $_FILES['csv_file']['name'];
        $fileType = mime_content_type($fileTmp);

        // Basic file validation: accept text/csv or octet-stream for some setups
        $allowed_mimes = ['text/plain', 'text/csv', 'application/vnd.ms-excel', 'text/comma-separated-values', 'application/octet-stream'];
        if (!in_array($fileType, $allowed_mimes)) {
            // not strict; we still try to parse it but warn
            $bulk_result['errors'][] = "Uploaded file appears to be type: {$fileType}. Expected CSV. Will attempt to parse anyway.";
        }

        // Open and parse CSV
        if (($handle = fopen($fileTmp, 'r')) !== false) {
            // Expect header row. Accept headers in any case-insensitive order:
            // required columns: usn, name, email, dob, semester, section
            // optional: address, password
            $header = fgetcsv($handle);
            if ($header === false) {
                $bulk_result['errors'][] = "CSV file is empty or unreadable.";
                fclose($handle);
            } else {
                // normalize header: trim + lowercase
                $map = [];
                foreach ($header as $i => $h) {
                    $key = strtolower(trim($h));
                    $map[$key] = $i;
                }

                $required = ['usn','name','email','dob','semester','section'];
                foreach ($required as $col) {
                    if (!array_key_exists($col, $map)) {
                        $bulk_result['errors'][] = "Missing required column in CSV header: '{$col}'. Example header: usn,name,email,dob,semester,section,address,password";
                    }
                }

                // If header missing required cols, stop parsing
                if (!empty($bulk_result['errors'])) {
                    fclose($handle);
                } else {
                    // prepare statements: check duplicate and insert
                    $checkStmt = $pdo->prepare("SELECT usn FROM students WHERE usn = :usn OR email = :email LIMIT 1");
                    $insertStmt = $pdo->prepare(
                        "INSERT INTO students (usn, student_name, email, dob, permanent_address, password, semester, section)
                         VALUES (:usn, :name, :email, :dob, :address, :password, :semester, :section)"
                    );

                    $rowNumber = 1; // header already read
                    // Start transaction for bulk inserts
                    $pdo->beginTransaction();
                    try {
                        while (($row = fgetcsv($handle)) !== false) {
                            $rowNumber++;
                            // read values by header map (safe access)
                            $usn = isset($map['usn']) ? trim($row[$map['usn']] ?? '') : '';
                            $name = isset($map['name']) ? trim($row[$map['name']] ?? '') : '';
                            $email = isset($map['email']) ? trim($row[$map['email']] ?? '') : '';
                            $dob = isset($map['dob']) ? trim($row[$map['dob']] ?? '') : '';
                            $semester = isset($map['semester']) ? filter_var($row[$map['semester']] ?? '', FILTER_VALIDATE_INT) : null;
                            $section = isset($map['section']) ? trim($row[$map['section']] ?? '') : '';
                            $address = isset($map['address']) ? trim($row[$map['address']] ?? '') : '';
                            $pw_plain = isset($map['password']) ? trim($row[$map['password']] ?? '') : '';

                            // Validate required fields
                            if ($usn === '' || $name === '' || $email === '' || $dob === '' || !$semester || $section === '') {
                                $bulk_result['errors'][$rowNumber] = "Missing required fields (usn/name/email/dob/semester/section).";
                                $bulk_result['skipped']++;
                                continue;
                            }
                            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                                $bulk_result['errors'][$rowNumber] = "Invalid email format: {$email}";
                                $bulk_result['skipped']++;
                                continue;
                            }

                            // check duplicates
                            $checkStmt->execute([':usn' => $usn, ':email' => $email]);
                            if ($checkStmt->fetch()) {
                                $bulk_result['errors'][$rowNumber] = "Skipped duplicate USN or email: {$usn} / {$email}";
                                $bulk_result['skipped']++;
                                continue;
                            }

                            // password handling
                            if ($pw_plain === '') $pw_plain = CSV_DEFAULT_PASSWORD;
                            $pw_hashed = password_hash($pw_plain, PASSWORD_DEFAULT);

                            // execute insert
                            $insertStmt->execute([
                                ':usn' => $usn,
                                ':name' => $name,
                                ':email' => $email,
                                ':dob' => $dob,
                                ':address' => $address,
                                ':password' => $pw_hashed,
                                ':semester' => $semester,
                                ':section' => $section
                            ]);

                            if ($insertStmt->rowCount() > 0) {
                                $bulk_result['inserted']++;
                            } else {
                                $bulk_result['errors'][$rowNumber] = "Insert failed for USN {$usn}.";
                                $bulk_result['skipped']++;
                            }
                        } // end while
                        $pdo->commit();
                    } catch (PDOException $e) {
                        $pdo->rollBack();
                        $bulk_result['errors'][] = "Database error during bulk insert: " . htmlspecialchars($e->getMessage());
                    } finally {
                        fclose($handle);
                    }
                } // end else header ok
            } // end else not empty
        } // end if fopen
    } // end else file uploaded
}

// Helper: render error list nicely
function render_bulk_result($res) {
    if ($res === null) return '';
    $html = "<div class='bulk-result'>";
    $html .= "<p class='message success'>Inserted: " . intval($res['inserted']) . "</p>";
    $html .= "<p class='message error'>Skipped: " . intval($res['skipped']) . "</p>";
    if (!empty($res['errors'])) {
        $html .= "<div style='margin-top:10px;padding:10px;background:#fff;border-radius:6px;color:#222;max-height:260px;overflow:auto;'>";
        $html .= "<strong>Details:</strong><ul>";
        foreach ($res['errors'] as $k => $v) {
            $label = is_int($k) ? "Row {$k}" : $k;
            $html .= "<li><strong>" . htmlspecialchars($label) . ":</strong> " . htmlspecialchars($v) . "</li>";
        }
        $html .= "</ul></div>";
    }
    $html .= "</div>";
    return $html;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<title>Add Student (Single & Bulk)</title>
<meta name="viewport" content="width=device-width,initial-scale=1" />
<style>
    :root { --space-cadet:#2b2d42; --antiflash-white:#edf2f4; --cool-gray:#8d99ae; --fire-engine-red:#d90429; --red-pantone:#ef233c; }
    body{font-family:'Segoe UI',Tahoma,Arial,sans-serif;background:var(--space-cadet);color:var(--antiflash-white);margin:0;padding:20px;}
    .wrap{max-width:1000px;margin:20px auto;display:grid;grid-template-columns:1fr 1fr;gap:18px;}
    .card{background:rgba(141,153,174,0.08);padding:18px;border-radius:10px;border:1px solid rgba(141,153,174,0.12);}
    h2{margin-top:0}
    label{display:block;margin-top:10px;font-weight:700}
    input,select,textarea{width:100%;padding:10px;border-radius:6px;border:1px solid var(--cool-gray);background:rgba(43,45,66,0.5);color:var(--antiflash-white);box-sizing:border-box}
    textarea{min-height:100px}
    .btn{display:inline-block;padding:10px 12px;border-radius:6px;border:none;background:var(--fire-engine-red);color:#fff;font-weight:700;cursor:pointer;margin-top:12px}
    .btn.secondary{background:transparent;border:1px solid rgba(255,255,255,0.08)}
    .message{padding:10px;border-radius:6px;margin-top:10px;font-weight:700}
    .success{background:#d4edda;color:#155724;border:1px solid #c3e6cb}
    .error{background:#f8d7da;color:#721c24;border:1px solid #f5c6cb}
    .hint{font-size:0.9rem;color:var(--cool-gray);margin-top:8px}
    .small{font-size:0.9rem;color:var(--cool-gray)}
    .note{margin-top:10px;color:var(--cool-gray)}
    pre.sample{background:#fff;color:#111;padding:10px;border-radius:6px;overflow:auto}
    .bulk-result{margin-top:12px;}
    /* Responsive: single column on small screens */
    @media (max-width:900px){ .wrap{grid-template-columns:1fr} }
</style>
</head>
<body>
    <div style="max-width:1000px;margin:0 auto 18px;">
        <a href="/admin-panel.php" style="color:var(--antiflash-white);text-decoration:none;font-weight:700">&laquo; Back to Admin Dashboard</a>
    </div>

    <div class="wrap">
        <!-- Single Add Card -->
        <div class="card">
            <h2>Add Single Student</h2>
            <?php if (!empty($message)) echo $message; ?>
            <form method="post" action="add-student.php">
                <input type="hidden" name="single_add" value="1">
                <label for="usn">USN</label>
                <input id="usn" name="usn" required>

                <label for="name">Full Name</label>
                <input id="name" name="name" required>

                <label for="email">Email</label>
                <input id="email" name="email" type="email" required>

                <div style="display:flex;gap:10px;">
                    <div style="flex:1">
                        <label for="semester">Semester</label>
                        <select id="semester" name="semester" required>
                            <option value="">-- Select --</option>
                            <?php for ($i=1;$i<=8;$i++): ?>
                                <option value="<?= $i ?>">Semester <?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div style="flex:1">
                        <label for="section">Section</label>
                        <select id="section" name="section" required>
                            <option value="">-- Select --</option>
                            <option value="A">A</option>
                            <option value="B">B</option>
                            <option value="C">C</option>
                            <option value="D">D</option>
                        </select>
                    </div>
                </div>

                <label for="dob">Date of Birth</label>
                <input id="dob" name="dob" type="date" required>

                <label for="address">Address</label>
                <textarea id="address" name="address"></textarea>

                <label for="password">Password</label>
                <input id="password" name="password" type="password" required>

                <button class="btn" type="submit">Add Student</button>
            </form>
            <p class="note">Single add requires all fields. Password will be hashed using PHP's <code>password_hash()</code>.</p>
        </div>

        <!-- Bulk Upload Card -->
        <div class="card">
            <h2>Bulk Upload (CSV)</h2>

            <form method="post" action="add-student.php" enctype="multipart/form-data">
                <input type="hidden" name="bulk_upload" value="1">
                <label for="csv_file">CSV File</label>
                <input id="csv_file" name="csv_file" type="file" accept=".csv" required>

                <p class="hint">CSV must have a header row. Required columns (case-insensitive):</p>
                <pre class="sample">usn,name,email,dob,semester,section,address,password</pre>
                <p class="small">- <strong>address</strong> and <strong>password</strong> are optional. If password is empty, a default will be used (see code).<br>
                - <strong>semester</strong> must be an integer (1..8).<br>
                - Date format should be YYYY-MM-DD (ISO) to be safely inserted into DATE column.</p>

                <button class="btn" type="submit">Upload CSV</button>
            </form>

            <?php
                if ($bulk_result !== null) {
                    echo render_bulk_result($bulk_result);
                }
            ?>

            <p class="note">After upload, the page shows how many rows were inserted, skipped, and detailed errors per row.</p>
        </div>
    </div>
</body>
</html>
