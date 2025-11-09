<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if (!isset($_SESSION['student_id'])) {
    // For testing, force a student login
    $_SESSION['student_id'] = 1;
}

// Now continue with your code
$student_id = $_SESSION['student_id'];
echo "Student ID: $student_id"; // check session works
?>
