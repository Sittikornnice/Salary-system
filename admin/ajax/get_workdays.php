<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
$company = $input['company_tax_id'] ?? '';
$month = $input['month'] ?? '';

if (!$company || !$month) {
    echo json_encode(['success'=>false, 'error'=>'missing_params']);
    exit;
}

$conn = new mysqli('localhost', 'root', '', 'salary_system');
if ($conn->connect_error) {
    echo json_encode(['success'=>false, 'error'=>'db_connect']);
    exit;
}

$stmt = $conn->prepare("SELECT employee_id, workdays_9hr, workdays_12hr, total_amount, 0 AS adjustments FROM salary_workdays WHERE company_tax_id = ? AND month = ? ORDER BY employee_id ASC");
if (!$stmt) {
    echo json_encode(['success'=>false, 'error'=>'prepare_failed']);
    $conn->close();
    exit;
}
$stmt->bind_param('ss', $company, $month);
$stmt->execute();
$res = $stmt->get_result();
$rows = [];
while ($r = $res->fetch_assoc()) {
    $rows[] = [
        'employee_id' => intval($r['employee_id']),
        'workdays_9hr' => intval($r['workdays_9hr']),
        'workdays_12hr' => intval($r['workdays_12hr']),
        'adjustments' => floatval($r['adjustments'] ?? 0),
        'total_amount' => floatval($r['total_amount'])
    ];
}
$stmt->close();
$conn->close();

echo json_encode(['success'=>true, 'rows'=>$rows]);
exit;
