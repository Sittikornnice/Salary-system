<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
$date = $input['date'] ?? ($input['month'] ?? '');
$company_tax_id = $_SESSION['selected_company_tax_id'] ?? ($input['company_tax_id'] ?? '');

if (!$company_tax_id || !$date) {
    echo json_encode(['success' => false, 'error' => 'missing_parameters', 'received' => ['date'=>$date, 'company_tax_id'=>$company_tax_id]]);
    exit;
}

$month = substr($date, 0, 7);

$conn = new mysqli('localhost', 'root', '', 'salary_system');
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'db_connect', 'msg' => $conn->connect_error]);
    exit;
}

$sql = "SELECT sw.id AS workdays_id, sw.employee_id, sw.month, sw.total_amount,
               e.firstname, e.lastname, e.position, d.payload AS payload
        FROM salary_workdays sw
        LEFT JOIN employees e ON e.id = sw.employee_id
        LEFT JOIN data d ON d.ref_table = 'salary_workdays' AND d.ref_id = sw.id
        WHERE sw.company_tax_id = ? AND sw.month = ?
        ORDER BY sw.employee_id ASC";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['success' => false, 'error' => 'prepare_failed', 'msg' => $conn->error]);
    $conn->close();
    exit;
}
$stmt->bind_param('ss', $company_tax_id, $month);
if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'error' => 'execute_failed', 'msg' => $stmt->error]);
    $stmt->close();
    $conn->close();
    exit;
}
$res = $stmt->get_result();

$rows = [];
while ($r = $res->fetch_assoc()) {
    $payment_date = null;
    if (!empty($r['payload'])) {
        $pl = json_decode($r['payload'], true);
        if (is_array($pl)) {
            if (!empty($pl['payment_date'])) $payment_date = $pl['payment_date'];
            elseif (!empty($pl['pay_date'])) $payment_date = $pl['pay_date'];
            elseif (!empty($pl['date'])) $payment_date = $pl['date'];
            elseif (!empty($pl['advance_date'])) $payment_date = $pl['advance_date'];
        }
    }
    // if you store payment_date directly on salary_workdays in future, prefer that (example)
    if (isset($r['payment_date']) && $r['payment_date']) $payment_date = $r['payment_date'];

    $rows[] = [
        'workdays_id' => (int)$r['workdays_id'],
        'employee_id' => (int)$r['employee_id'],
        'firstname' => $r['firstname'] ?? '',
        'lastname' => $r['lastname'] ?? '',
        'position' => $r['position'] ?? '',
        'total_amount' => $r['total_amount'] !== null ? (float)$r['total_amount'] : null,
        'month' => $r['month'],
        'payment_date' => $payment_date // may be null or 'YYYY-MM-DD' or 'YYYY-MM' or with time
    ];
}

$stmt->close();
$conn->close();

echo json_encode(['success' => true, 'rows' => $rows]);