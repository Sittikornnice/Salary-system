<?php
session_start();
$conn = new mysqli('localhost', 'root', '', 'salary_system');
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

// รับข้อมูลจากฟอร์ม
$prefix = $_POST['prefix'] ?? '';
$firstname = $_POST['firstname'] ?? '';
$lastname = $_POST['lastname'] ?? '';
$nickname = $_POST['nickname'] ?? '';
$birthdate = $_POST['birthdate'] ?? '';
$phone = $_POST['phone'] ?? '';
$idcard = $_POST['idcard'] ?? '';
$bank_account = $_POST['bank_account'] ?? '';
$address = $_POST['address'] ?? '';
$subdistrict = $_POST['subdistrict'] ?? '';
$district = $_POST['district'] ?? '';
$province = $_POST['province'] ?? '';
$zipcode = $_POST['zipcode'] ?? '';
$start_work_date = $_POST['start_work_date'] ?? '';
$end_work_date = $_POST['end_work_date'] ?? '';
$position = $_POST['position'] ?? '';
$is_insured = isset($_POST['is_insured']) ? 1 : 0;
$salary = $_POST['salary'] ?? '';
$note = $_POST['note'] ?? '';
$salary_change_date = $_POST['salary_change_date'] ?? '';
$salary_change_amount = $_POST['salary_change_amount'] ?? '';
$salary_change_reason = $_POST['salary_change_reason'] ?? '';
$salary_change_note = $_POST['salary_change_note'] ?? '';
$status = $_POST['status'] ?? 'ทำงานอยู่';
$company_tax_id = $_SESSION['selected_company_tax_id'] ?? '';

// อัปโหลดไฟล์
function uploadFile($fileInput, $targetDir = "../uploads/") {
    if (!isset($_FILES[$fileInput]) || $_FILES[$fileInput]['error'] != UPLOAD_ERR_OK) return '';
    if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
    $filename = uniqid() . '_' . basename($_FILES[$fileInput]['name']);
    $targetFile = $targetDir . $filename;
    if (move_uploaded_file($_FILES[$fileInput]['tmp_name'], $targetFile)) {
        return $filename;
    }
    return '';
}
$photo = uploadFile('photo');
$idcard_file = uploadFile('idcard_file');
$house_file = uploadFile('house_file');
$other_file = uploadFile('other_file');

// สร้างชื่อเต็ม
$fullname = $prefix . $firstname . ' ' . $lastname;

// คำนวณอายุ
$age = '';
if ($birthdate) {
    $birth = new DateTime($birthdate);
    $now = new DateTime();
    $age = $now->diff($birth)->y;
}

// เพิ่มข้อมูลลงฐานข้อมูล
$stmt = $conn->prepare("INSERT INTO employees (
    prefix, firstname, lastname, fullname, nickname, birthdate, age, phone, idcard, bank_account, address, subdistrict, district, province, zipcode,
    start_work_date, end_work_date, position, is_insured, salary, note, salary_change_date, salary_change_amount, salary_change_reason, salary_change_note,
    status, company_tax_id, photo, idcard_file, house_file, other_file
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

$stmt->bind_param(
    "sssssssssssssssssssssssssssssss",
    $prefix, $firstname, $lastname, $fullname, $nickname, $birthdate, $age, $phone, $idcard, $bank_account, $address, $subdistrict, $district, $province, $zipcode,
    $start_work_date, $end_work_date, $position, $is_insured, $salary, $note, $salary_change_date, $salary_change_amount, $salary_change_reason, $salary_change_note,
    $status, $company_tax_id, $photo, $idcard_file, $house_file, $other_file
);

if ($stmt->execute()) {
    $new_emp_id = $conn->insert_id; // ได้ id พนักงานใหม่
    $stmt->close();

    // เพิ่มประวัติการปรับค่าแรง (เฉพาะถ้ามีการกรอกข้อมูลใหม่)
    if ($salary_change_date && $salary_change_amount !== '') {
        $stmt2 = $conn->prepare("INSERT INTO salary_history (employee_id, change_date, amount, reason, note) VALUES (?, ?, ?, ?, ?)");
        $stmt2->bind_param("isdss", $new_emp_id, $salary_change_date, $salary_change_amount, $salary_change_reason, $salary_change_note);
        $stmt2->execute();
        $stmt2->close();
    }

    $conn->close();
    echo "<script>alert('บันทึกข้อมูลเรียบร้อย');window.location='dashboard.php';</script>";
    exit;
} else {
    echo "<script>alert('เกิดข้อผิดพลาดในการบันทึกข้อมูล');window.location='dashboard.php';</script>";
    exit;
}
?>