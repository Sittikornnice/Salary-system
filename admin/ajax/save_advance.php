<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

// Basic DB connection - adjust if you use a central include
$conn = new mysqli('localhost','root', '', 'salary_system');
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'DB connection failed']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$payload = $_POST;
// allow JSON body as well
if (empty($payload)) {
    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true) ?: [];
}

// expected fields: id (optional), employee_id, company_tax_id, advance_date, amount, note_internal, note_slip, slip_image
$employee_id = isset($payload['employee_id']) ? intval($payload['employee_id']) : 0;
$company_tax_id = isset($payload['company_tax_id']) ? $conn->real_escape_string(trim($payload['company_tax_id'])) : null;
$advance_date = isset($payload['advance_date']) ? $conn->real_escape_string(trim($payload['advance_date'])) : null;
$amount = isset($payload['amount']) ? (float)$payload['amount'] : 0.00;
// note_internal ไม่ใช้แล้ว, slip_image ใช้แทน
$note_slip = isset($payload['note_slip']) ? $conn->real_escape_string(trim($payload['note_slip'])) : null;
$slip_image = isset($payload['slip_image']) ? $conn->real_escape_string(trim($payload['slip_image'])) : null;
$id = isset($payload['id']) ? intval($payload['id']) : 0;
$admin_id = $_SESSION['admin_id'] ?? null;

if ($employee_id <= 0 || $amount <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Invalid data']);
    exit;
}

// สร้าง JSON payload เพื่อเก็บลงตาราง data
$payload_for_data = $payload;
unset($payload_for_data['note_internal']); // ไม่ใช้ note_internal แล้ว
if ($slip_image) $payload_for_data['slip_image'] = $slip_image;
$json_payload = json_encode($payload_for_data, JSON_UNESCAPED_UNICODE);
if ($json_payload === false) { $json_payload = json_encode([]); }

// เริ่ม transaction เพื่อความสอดคล้องระหว่างตาราง advance_payments และ data
$conn->begin_transaction();

if ($id > 0) {
    // update advance_payments
    $stmt = $conn->prepare("UPDATE advance_payments SET employee_id=?, company_tax_id=?, advance_date=?, amount=?, slip_image=?, note_slip=?, updated_by=? WHERE id = ?");
    $stmt->bind_param('issdssii', $employee_id, $company_tax_id, $advance_date, $amount, $slip_image, $note_slip, $admin_id, $id);
    $ok = $stmt->execute();
    $stmt->close();

    if (!$ok) {
        $conn->rollback();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $conn->error]);
        $conn->close();
        exit;
    }

    // upsert ลงตาราง data (update ถ้ามี row, ถ้าไม่มีให้ insert)
    $stmt = $conn->prepare("UPDATE `data` SET payload=? WHERE ref_table='advance_payments' AND ref_id=?");
    $stmt->bind_param('si', $json_payload, $id);
    $stmt->execute();
    if ($stmt->affected_rows === 0) {
        $stmt->close();
        $stmt = $conn->prepare("INSERT INTO `data` (ref_table, ref_id, payload) VALUES ('advance_payments', ?, ?)");
        $stmt->bind_param('is', $id, $json_payload);
        $stmt->execute();
    }
    $stmt->close();

    $conn->commit();
    echo json_encode(['success' => true, 'id' => $id]);
} else {
    // insert advance_payments
    $stmt = $conn->prepare("INSERT INTO advance_payments (employee_id, company_tax_id, advance_date, amount, slip_image, note_slip, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('issdssi', $employee_id, $company_tax_id, $advance_date, $amount, $slip_image, $note_slip, $admin_id);
    $ok = $stmt->execute();
    $newId = $stmt->insert_id;
    $stmt->close();

    if (!$ok) {
        $conn->rollback();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $conn->error]);
        $conn->close();
        exit;
    }

    // ใส่ payload ลงตาราง data
    $stmt = $conn->prepare("INSERT INTO `data` (ref_table, ref_id, payload) VALUES ('advance_payments', ?, ?)");
    $stmt->bind_param('is', $newId, $json_payload);
    $ok2 = $stmt->execute();
    $stmt->close();

    if (!$ok2) {
        $conn->rollback();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $conn->error]);
        $conn->close();
        exit;
    }

    $conn->commit();
    echo json_encode(['success' => true, 'id' => $newId]);
}

$conn->close();

