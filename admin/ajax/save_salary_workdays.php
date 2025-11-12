<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    echo json_encode(['success'=>false, 'error'=>'invalid_input']); exit;
}
$company_tax_id = trim($input['company_tax_id'] ?? '');
$month = trim($input['month'] ?? '');
$rows = $input['rows'] ?? [];

if (!$company_tax_id || !$month || !is_array($rows) || count($rows) === 0) {
    echo json_encode(['success'=>false, 'error'=>'missing_data']); exit;
}

$mysqli = new mysqli('localhost','root','','salary_system');
if ($mysqli->connect_error) {
    echo json_encode(['success'=>false, 'error'=>'db_connect']); exit;
}

try {
    $mysqli->begin_transaction();
    $saved = [];

    // prepare statements we'll reuse
    $selByIdStmt = $mysqli->prepare("SELECT id, company_tax_id FROM salary_workdays WHERE id = ? LIMIT 1");
    $selByKeyStmt = $mysqli->prepare("SELECT id FROM salary_workdays WHERE company_tax_id = ? AND employee_id = ? AND month = ? LIMIT 1");
    $insStmt = $mysqli->prepare("INSERT INTO salary_workdays (employee_id, company_tax_id, month, workdays_9hr, workdays_12hr, total_amount) VALUES (?, ?, ?, ?, ?, ?)");
    $updStmt = $mysqli->prepare("UPDATE salary_workdays SET workdays_9hr = ?, workdays_12hr = ?, total_amount = ? WHERE id = ?");
    $selDataStmt = $mysqli->prepare("SELECT id FROM data WHERE ref_table = 'salary_workdays' AND ref_id = ? LIMIT 1");
    $insDataStmt = $mysqli->prepare("INSERT INTO data (ref_table, ref_id, payload) VALUES ('salary_workdays', ?, ?)");
    $updDataStmt = $mysqli->prepare("UPDATE data SET payload = ? WHERE id = ?");

    foreach ($rows as $r) {
        $rowId = isset($r['id']) ? intval($r['id']) : 0;
        $employee_id = intval($r['employee_id']);
        $wd9 = intval($r['workdays_9hr'] ?? 0);
        $wd12 = intval($r['workdays_12hr'] ?? 0);
        $total_amount = floatval($r['total_amount'] ?? 0);

        $finalId = 0;

        // If id provided, verify belongs to company (optional safety) and update
        if ($rowId > 0) {
            $selByIdStmt->bind_param('i', $rowId);
            $selByIdStmt->execute();
            $res = $selByIdStmt->get_result();
            if ($row = $res->fetch_assoc()) {
                // optional check: ensure company_tax_id matches (if session or provided)
                if (isset($row['company_tax_id']) && $row['company_tax_id'] !== $company_tax_id) {
                    // skip or throw
                    throw new Exception('ownership_mismatch_for_id_' . $rowId);
                }
                // update
                $updStmt->bind_param('ssdi', $wd9, $wd12, $total_amount, $rowId);
                $updStmt->execute();
                $finalId = $rowId;
            } else {
                // provided id not found -> treat as new (fallthrough)
                $rowId = 0;
            }
            $res->free();
        }

        // If no id or id not found: try find by key (company + employee + month)
        if ($finalId === 0) {
            $selByKeyStmt->bind_param('sis', $company_tax_id, $employee_id, $month);
            $selByKeyStmt->execute();
            $res2 = $selByKeyStmt->get_result();
            if ($row2 = $res2->fetch_assoc()) {
                $foundId = intval($row2['id']);
                // update existing
                $updStmt->bind_param('ssdi', $wd9, $wd12, $total_amount, $foundId);
                $updStmt->execute();
                $finalId = $foundId;
            } else {
                // insert new
                $insStmt->bind_param('issiid', $employee_id, $company_tax_id, $month, $wd9, $wd12, $total_amount);
                $insStmt->execute();
                $finalId = $mysqli->insert_id;
            }
            $res2->free();
        }

        if ($finalId <= 0) throw new Exception('failed_save_row');

        // Build payload for data table
        $payload = json_encode([
            'salary_workdays_id' => $finalId,
            'company_tax_id' => $company_tax_id,
            'month' => $month,
            'employee_id' => $employee_id,
            'workdays_9hr' => $wd9,
            'workdays_12hr' => $wd12,
            'total_amount' => $total_amount,
            'saved_at' => date('c')
        ], JSON_UNESCAPED_UNICODE);

        // upsert into data table (select then update/insert)
        $selDataStmt->bind_param('i', $finalId);
        $selDataStmt->execute();
        $res3 = $selDataStmt->get_result();
        if ($dataRow = $res3->fetch_assoc()) {
            $dataId = intval($dataRow['id']);
            $updDataStmt->bind_param('si', $payload, $dataId);
            $updDataStmt->execute();
        } else {
            $insDataStmt->bind_param('is', $finalId, $payload);
            $insDataStmt->execute();
        }
        $res3->free();

        $saved[] = ['employee_id' => $employee_id, 'id' => $finalId];
    }

    $mysqli->commit();

    // close statements
    @$selByIdStmt->close();
    @$selByKeyStmt->close();
    @$insStmt->close();
    @$updStmt->close();
    @$selDataStmt->close();
    @$insDataStmt->close();
    @$updDataStmt->close();

    echo json_encode(['success'=>true, 'saved_ids'=>$saved]);
    exit;
} catch (Exception $e) {
    $mysqli->rollback();
    error_log('save_salary_workdays error: ' . $e->getMessage());
    echo json_encode(['success'=>false, 'error'=>$e->getMessage()]);
    exit;
}
