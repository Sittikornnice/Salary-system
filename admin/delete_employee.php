<?php
session_start();
$conn = new mysqli('localhost', 'root', '', 'salary_system');
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

$id = intval($_GET['id'] ?? 0);
if (!$id) { echo "ไม่พบรหัสพนักงาน"; exit; }

// ลบประวัติการปรับค่าแรงของพนักงานนี้
$stmt1 = $conn->prepare("DELETE FROM salary_history WHERE employee_id=?");
$stmt1->bind_param("i", $id);
$stmt1->execute();
$stmt1->close();

// ลบข้อมูลพนักงาน
$stmt2 = $conn->prepare("DELETE FROM employees WHERE id=?");
$stmt2->bind_param("i", $id);
$stmt2->execute();
$stmt2->close();

$conn->close();
echo "<script>alert('ลบข้อมูลเรียบร้อย');window.location='dashboard.php';</script>";
exit;
?>