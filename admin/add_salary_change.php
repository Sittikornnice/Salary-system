<?php
session_start();
$conn = new mysqli('localhost', 'root', '', 'salary_system');
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employee_id = intval($_POST['employee_id']);
    $change_date = $_POST['change_date'];
    $amount = floatval($_POST['amount']);
    $reason = $_POST['reason'] ?? '';
    $note = $_POST['note'] ?? '';

    $conn = new mysqli('localhost', 'root', '', 'salary_system');
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    $stmt = $conn->prepare("INSERT INTO salary_changes (employee_id, change_date, amount, reason, note) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("isdss", $employee_id, $change_date, $amount, $reason, $note);
    $stmt->execute();
    $stmt->close();
    $conn->close();

    // กลับไปหน้า dashboard พร้อม anchor ไป modal เดิม
    header("Location: dashboard.php");
    exit;
}
?>