<?php
session_start();
require_once('db.php'); // PDO $pdo

// Optional: authorize admin
/*
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: /login");
    exit;
}
*/

$status_msg = '';

// Handle deletion (GET)
try {
    if (isset($_GET['delete_id'])) {
        $allocation_id = intval($_GET['delete_id']);
        if ($allocation_id > 0) {
            $del = $pdo->prepare("DELETE FROM subject_allocation WHERE id = :id");
            $del->execute([':id' => $allocation_id]);
            // redirect to avoid re-delete on refresh
            header("Location: view-allocations.php?status=deleted");
            exit;
        }
    }

    // Show status messages (after redirect)
    if (isset($_GET['status']) && $_GET['status'] === 'deleted') {
        $status_msg = "<p class='message success'>Allocation removed successfully.</p>";
    }

    // Check whether subject_allocation has a 'section' column
    $colCheck = $pdo->prepare("
        SELECT column_name FROM information_schema.columns
        WHERE table_schema = current_schema()
          AND table_name = 'subject_allocation'
          AND column_name = 'section'
        LIMIT 1
    ");
    $colCheck->execute();
    $hasSectionColumn = (bool) $colCheck->fetchColumn();

    // Fetch allocations using LEFT JOIN so we don't drop rows missing class_id
    // We select sa.section if exists; otherwise NULL
    $alloc_sql = "
        SELECT
            sa.id AS alloc_id,
            u.first_name,
            u.surname,
            s.subject_code,
            s.name AS subject_name,
            c.name AS class_name,
            " . ($hasSectionColumn ? "sa.section AS section_value," : "NULL AS section_value,") . "
            sa.class_id
        FROM subject_allocation sa
        LEFT JOIN users u ON sa.staff_id = u.id
        LEFT JOIN subjects s ON sa.subject_id = s.id
        LEFT JOIN classes c ON sa.class_id = c.id
        ORDER BY u.first_name NULLS LAST, s.subject_code NULLS LAST, c.name NULLS LAST
    ";
    $alloc_stmt = $pdo->query($alloc_sql);
    $allocations = $alloc_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database error: " . htmlspecialchars($e->getMessage()));
}

$current_sl_no = 1;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>View Subject Allocations</title>
<style>
    :root { --space-cadet: #2b2d42; --cool-gray: #8d99ae; --antiflash-white: #edf2f4; --red-pantone: #ef233c; --fire-engine-red: #d90429; }
    body { font-family: 'Segoe UI', sans-serif; margin: 0; padding: 20px; background: var(--space-cadet); color: var(--antiflash-white); }
    .navbar { display:flex; justify-content:space-between; align-items:center; max-width:1200px; margin:0 auto 20px; padding:10px 20px; background: rgba(141,153,174,0.08); border-radius:10px; }
    .navbar h2 { margin:0; color:var(--antiflash-white); }
    .navbar-links a { font-weight:bold; color:var(--antiflash-white); text-decoration:none; margin-left:16px; }
    .navbar-links a:hover { text-decoration:underline; }
    .container { width:90%; max-width:1200px; margin:18px auto; background:#fff; padding:18px; border-radius:8px; box-shadow:0 2px 8px rgba(0,0,0,0.08); color:var(--space-cadet); }
    table { width:100%; border-collapse:collapse; margin-top:10px; }
    th, td { border:1px solid #e6e6e6; padding:10px; text-align:left; vertical-align:middle; }
    th { background:var(--space-cadet); color:#fff; }
    tr:nth-child(even) { background:#fafafa; }
    .action-btn { padding:6px 10px; border-radius:4px; text-decoration:none; color:#fff; font-size:0.9em; display:inline-block; }
    .remove-btn { background:#dc3545; }
    .remove-btn:hover { background:#b52d3b; }
    .message { padding:10px; border-radius:6px; margin-bottom:12px; font-weight:700; text-align:center; }
    .success { background:#d4edda; color:#155724; border:1px solid #c3e6cb; }
    .error { background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; }
    .muted { color:#666; font-size:0.95rem; }
</style>
</head>
<body>
    <div class="navbar">
        <h2>Subject Allocations</h2>
        <div class="navbar-links">
            <a href="/admin">Back to Dashboard</a>
            <a href="/logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <?php if ($status_msg) echo $status_msg; ?>

        <table>
            <thead>
                <tr>
                    <th>Sl. No.</th>
                    <th>Staff Name</th>
                    <th>Subject Code</th>
                    <th>Subject Name</th>
                    <th>Class / Section</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($allocations)): ?>
                    <tr>
                        <td colspan="6" style="text-align:center;">No subject allocations found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($allocations as $alloc): ?>
                        <?php
                            // Determine what to show for class/section:
                            $class_display = '—';
                            if (!empty($alloc['class_name'])) {
                                $class_display = $alloc['class_name'];
                            } elseif (!empty($alloc['section_value'])) {
                                $class_display = $alloc['section_value'];
                            } elseif (!empty($alloc['class_id'])) {
                                // class_id exists but no name found (class missing) -> show placeholder with id
                                $class_display = 'Class ID: ' . intval($alloc['class_id']);
                            }
                        ?>
                        <tr>
                            <td><?= $current_sl_no++ ?></td>
                            <td><?= htmlspecialchars(trim($alloc['first_name'] . ' ' . $alloc['surname'])) ?></td>
                            <td><?= htmlspecialchars($alloc['subject_code'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($alloc['subject_name'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($class_display) ?></td>
                            <td>
                                <a href="?delete_id=<?= intval($alloc['alloc_id']) ?>" class="action-btn remove-btn"
                                   onclick="return confirm('Are you sure you want to remove this allocation?')">Remove</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <p class="muted">Note: If a class/section does not exist in the <code>classes</code> table, this page will show the entered section (if stored) or a placeholder.</p>
    </div>
</body>
</html>
