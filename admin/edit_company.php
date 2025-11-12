<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tax_id = $_POST['tax_id'] ?? '';
    $name = $_POST['name'] ?? '';
    $address = $_POST['address'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $email = $_POST['email'] ?? '';

    // ป้องกัน SQL Injection
    $conn = new mysqli('localhost', 'root', '', 'salary_system');
    if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

    $tax_id = $conn->real_escape_string($tax_id);
    $name = $conn->real_escape_string($name);
    $address = $conn->real_escape_string($address);
    $phone = $conn->real_escape_string($phone);
    $email = $conn->real_escape_string($email);

    $sql = "UPDATE companies SET 
                name='$name',
                address='$address',
                phone='$phone',
                email='$email'
            WHERE tax_id='$tax_id'";

    if ($conn->query($sql) === TRUE) {
        $_SESSION['success_message'] = "แก้ไขข้อมูลบริษัทเรียบร้อยแล้ว";
    } else {
        $_SESSION['error_message'] = "เกิดข้อผิดพลาด: " . $conn->error;
    }
    $conn->close();
    header("Location: dashboard.php?page=company_settings.php");
    exit;
} else {
    header("Location: dashboard.php?page=company_settings.php");
    exit;
}