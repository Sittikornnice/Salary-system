<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
$company = $input['company_tax_id'] ?? '';
$month = $input['month'] ?? '';
$rows = $input['rows'] ?? [];

if (!$company || !$month || !is_array($rows)) {
    echo json_encode(['success'=>false, 'error'=>'missing_params']);
    exit;
}

$conn = new mysqli('localhost', 'root', '', 'salary_system');
if ($conn->connect_error) {
    echo json_encode(['success'=>false, 'error'=>'db_connect']);
    exit;
}

// Prepare statements
$sel = $conn->prepare("SELECT id FROM salary_workdays WHERE employee_id = ? AND company_tax_id = ? AND month = ? LIMIT 1");
$ins = $conn->prepare("INSERT INTO salary_workdays (employee_id, company_tax_id, month, workdays_9hr, workdays_12hr, total_amount) VALUES (?, ?, ?, ?, ?, ?)");
$upd = $conn->prepare("UPDATE salary_workdays SET workdays_9hr = ?, workdays_12hr = ?, total_amount = ? WHERE id = ?");

if (!$sel || !$ins || !$upd) {
    echo json_encode(['success'=>false, 'error'=>'prepare_failed']);
    $conn->close();
    exit;
}

$allOk = true;
foreach ($rows as $r) {
    $empId = intval($r['employee_id'] ?? 0);
    $w9 = intval($r['workdays_9hr'] ?? 0);
    $w12 = intval($r['workdays_12hr'] ?? 0);
    $total = floatval($r['total_amount'] ?? 0.00);
    if ($empId <= 0) continue;

    // check existing
    $sel->bind_param('iss', $empId, $company, $month);
    $sel->execute();
    $res = $sel->get_result();
    if ($row = $res->fetch_assoc()) {
        $id = intval($row['id']);
        $upd->bind_param('ii di', $w9, $w12, $total, $id); // note: fix types below before execute
        // mysqli bind_param types: i - integer, d - double, s - string
        // prepare correct binding:
        $upd->close();
        $upd = $conn->prepare("UPDATE salary_workdays SET workdays_9hr = ?, workdays_12hr = ?, total_amount = ? WHERE id = ?");
        $upd->bind_param('iidi', $w9, $w12, $total, $id);
        $ok = $upd->execute();
        if (!$ok) $allOk = false;
    } else {
        $ins->bind_param('issiid', $empId, $company, $month, $w9, $w12, $total);
        $ok = $ins->execute();
        if (!$ok) $allOk = false;
    }
}
$sel->close();
$ins->close();
if($upd) $upd->close();
$conn->close();

if ($allOk) echo json_encode(['success'=>true]);
else echo json_encode(['success'=>false, 'error'=>'some_failed']);
exit;
