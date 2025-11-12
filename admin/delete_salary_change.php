<?php
session_start();
$conn = new mysqli('localhost', 'root', '', 'salary_system');
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);

    $conn = new mysqli('localhost', 'root', '', 'salary_system');
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    $stmt = $conn->prepare("DELETE FROM salary_changes WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    $conn->close();

    header("Location: dashboard.php");
    exit;
}
?>