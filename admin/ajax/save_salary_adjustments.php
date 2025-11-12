<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) { echo json_encode(['success'=>false,'error'=>'invalid_json']); exit; }

$company_tax_id = isset($input['company_tax_id']) ? trim($input['company_tax_id']) : '';
$rows = isset($input['rows']) && is_array($input['rows']) ? $input['rows'] : [];

if ($company_tax_id === '') {
    echo json_encode(['success'=>false,'error'=>'missing_company_tax_id']); exit;
}

$conn = new mysqli('localhost', 'root', '', 'salary_system');
if ($conn->connect_errno) {
    echo json_encode(['success'=>false,'error'=>'db_connect','message'=>$conn->connect_error]); exit;
}
$conn->set_charset('utf8mb4');

// create table if not exists (safe to run repeatedly)
$createSql = "CREATE TABLE IF NOT EXISTS salary_adjustments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  company_tax_id VARCHAR(64) NOT NULL,
  employee_id INT NOT NULL,
  workdays_id INT DEFAULT NULL,
  month VARCHAR(7) NOT NULL,
  items JSON,
  net_amount DECIMAL(12,2) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY ux_company_emp_month_workdays (company_tax_id, employee_id, month, workdays_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
if (!$conn->query($createSql)) {
    echo json_encode(['success'=>false,'error'=>'create_table_failed','message'=>$conn->error]); $conn->close(); exit;
}

$saved = [];
foreach ($rows as $r) {
    $employee_id = isset($r['employee_id']) ? intval($r['employee_id']) : 0;
    $workdays_id = (isset($r['workdays_id']) && $r['workdays_id'] !== null && $r['workdays_id'] !== '') ? intval($r['workdays_id']) : null;
    $month = isset($r['month']) ? trim($r['month']) : '';
    $net_amount = isset($r['net_amount']) ? floatval($r['net_amount']) : 0.0;
    $items = isset($r['items']) ? $r['items'] : null;

    if ($employee_id <= 0 || $month === '') {
        $saved[] = ['employee_id'=>$employee_id,'success'=>false,'error'=>'invalid_row'];
        continue;
    }

    $items_json = $items !== null ? json_encode($items, JSON_UNESCAPED_UNICODE) : json_encode(new stdClass());

    // check existing
    if ($workdays_id === null) {
        $stmt = $conn->prepare("SELECT id FROM salary_adjustments WHERE company_tax_id=? AND employee_id=? AND month=? AND workdays_id IS NULL LIMIT 1");
        $stmt->bind_param("sis", $company_tax_id, $employee_id, $month);
    } else {
        $stmt = $conn->prepare("SELECT id FROM salary_adjustments WHERE company_tax_id=? AND employee_id=? AND month=? AND workdays_id=? LIMIT 1");
        $stmt->bind_param("sisi", $company_tax_id, $employee_id, $month, $workdays_id);
    }
    if (!$stmt->execute()) {
        $saved[] = ['employee_id'=>$employee_id,'success'=>false,'error'=>'select_failed','message'=>$stmt->error];
        $stmt->close(); continue;
    }
    $res = $stmt->get_result();
    $existing = $res->fetch_assoc();
    $stmt->close();

    if ($existing && isset($existing['id'])) {
        $id = intval($existing['id']);
        $upd = $conn->prepare("UPDATE salary_adjustments SET items = ?, net_amount = ?, updated_at = NOW() WHERE id = ?");
        if (!$upd) {
            $saved[] = ['employee_id'=>$employee_id,'success'=>false,'error'=>'prepare_update_failed','message'=>$conn->error];
            continue;
        }
        $upd->bind_param("sdi", $items_json, $net_amount, $id);
        $ok = $upd->execute();
        $msg = $ok ? null : $upd->error;
        $upd->close();
        $saved[] = ['employee_id'=>$employee_id,'id'=>$id,'success'=>$ok ? true : false, 'message'=>$msg];
    } else {
        $ins = $conn->prepare("INSERT INTO salary_adjustments (company_tax_id, employee_id, workdays_id, month, items, net_amount) VALUES (?, ?, ?, ?, ?, ?)");
        if (!$ins) {
            $saved[] = ['employee_id'=>$employee_id,'success'=>false,'error'=>'prepare_insert_failed','message'=>$conn->error];
            continue;
        }
        // bind workdays_id as integer or null (mysqli requires a value, so use null and set as string 'NULL' not supported - pass as value and allow NULL by using is_null handling)
        if ($workdays_id === null) {
            // bind as NULL: pass null and use 'sissd' types but convert workdays_id param to null via bind_param - use 'i' and null works
            $ins->bind_param("siissd", $company_tax_id, $employee_id, $workdays_id, $month, $items_json, $net_amount);
        } else {
            $ins->bind_param("siissd", $company_tax_id, $employee_id, $workdays_id, $month, $items_json, $net_amount);
        }
        $ok = $ins->execute();
        $newId = $ins->insert_id ?: null;
        $msg = $ok ? null : $ins->error;
        $ins->close();
        $saved[] = ['employee_id'=>$employee_id,'id'=>$newId,'success'=>$ok ? true : false, 'message'=>$msg];
    }
}

$conn->close();
echo json_encode(['success'=>true,'rows'=>$saved]);
exit;
?>
