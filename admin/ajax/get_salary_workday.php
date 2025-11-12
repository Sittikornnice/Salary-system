<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) { echo json_encode(['success'=>false,'error'=>'invalid_input']); exit; }

$mysqli = new mysqli('localhost','root','','salary_system');
if ($mysqli->connect_error) { echo json_encode(['success'=>false,'error'=>'db_connect']); exit; }

try {
    $resp = ['success'=>false];
    // find by id first
    if (!empty($input['id'])) {
        $id = intval($input['id']);
        $stmt = $mysqli->prepare("SELECT * FROM salary_workdays WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $resp['row'] = $row;
            // try get data payload
            $d = $mysqli->prepare("SELECT payload FROM data WHERE ref_table='salary_workdays' AND ref_id = ? LIMIT 1");
            $d->bind_param('i', $id);
            $d->execute();
            $dr = $d->get_result();
            if ($pdata = $dr->fetch_assoc()) {
                $payload = $pdata['payload'];
                // try decode JSON
                $decoded = json_decode($payload, true);
                $resp['data_payload'] = $decoded !== null ? $decoded : $payload;
            } else {
                $resp['data_payload'] = null;
            }
            $resp['success'] = true;
        } else {
            $resp['error'] = 'not_found';
        }
        $stmt->close();
    } else if (!empty($input['employee_id']) && !empty($input['month']) && !empty($input['company_tax_id'])) {
        $employee_id = intval($input['employee_id']);
        $month = $mysqli->real_escape_string($input['month']);
        $company_tax_id = $mysqli->real_escape_string($input['company_tax_id']);
        $stmt = $mysqli->prepare("SELECT * FROM salary_workdays WHERE company_tax_id = ? AND employee_id = ? AND month = ? LIMIT 1");
        $stmt->bind_param('sis', $company_tax_id, $employee_id, $month);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $resp['row'] = $row;
            $id = intval($row['id']);
            $d = $mysqli->prepare("SELECT payload FROM data WHERE ref_table='salary_workdays' AND ref_id = ? LIMIT 1");
            $d->bind_param('i', $id);
            $d->execute();
            $dr = $d->get_result();
            if ($pdata = $dr->fetch_assoc()) {
                $payload = $pdata['payload'];
                $decoded = json_decode($payload, true);
                $resp['data_payload'] = $decoded !== null ? $decoded : $payload;
            } else {
                $resp['data_payload'] = null;
            }
            $resp['success'] = true;
        } else {
            $resp['error'] = 'not_found';
        }
        $stmt->close();
    } else {
        $resp['error'] = 'missing_parameters';
    }

    echo json_encode($resp);
} catch (Exception $e) {
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
$mysqli->close();
