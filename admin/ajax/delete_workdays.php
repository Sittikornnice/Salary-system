<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json; charset=utf-8');

// read JSON body
$input = json_decode(file_get_contents('php://input'), true);
$id = isset($input['id']) ? intval($input['id']) : 0;
if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'invalid_id']);
    exit;
}

// DB connection (adjust credentials if needed)
$mysqli = new mysqli('localhost', 'root', '', 'salary_system');
if ($mysqli->connect_error) {
    echo json_encode(['success' => false, 'error' => 'db_connect']);
    exit;
}

$companyTax = $_SESSION['selected_company_tax_id'] ?? null;

try {
    // optional: verify ownership by selected company (if session stores it)
    if ($companyTax) {
        $stmt = $mysqli->prepare("SELECT id FROM salary_workdays WHERE id = ? AND company_tax_id = ? LIMIT 1");
        if (!$stmt) throw new Exception('prepare_failed_select');
        $stmt->bind_param('is', $id, $companyTax);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows === 0) {
            echo json_encode(['success' => false, 'error' => 'not_found_or_not_allowed']);
            $stmt->close();
            $mysqli->close();
            exit;
        }
        $stmt->close();
    }

    $mysqli->begin_transaction();

    $totalDeletedData = 0;

    // 1) Delete where ref_table explicitly points to known variants (most likely)
    $stmt = $mysqli->prepare("
        DELETE FROM data
        WHERE (ref_table = 'salary_workdays' OR ref_table = 'salary_workday' OR ref_table LIKE '%workday%' OR ref_table LIKE '%work_days%')
          AND ref_id = ?
    ");
    if ($stmt) {
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $totalDeletedData += $stmt->affected_rows;
        $stmt->close();
    }

    // 2) Delete where ref_id matches even if ref_table is NULL/empty (fallback)
    $stmt = $mysqli->prepare("
        DELETE FROM data
        WHERE (ref_table IS NULL OR ref_table = '')
          AND ref_id = ?
    ");
    if ($stmt) {
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $totalDeletedData += $stmt->affected_rows;
        $stmt->close();
    }

    // 3) Fallback: try to remove rows that reference the workday id inside payload (payload is plain text/json)
    // This is a broader match; we look for the id as a quoted string or as a number substring in payload.
    // Note: this may be necessary if the app stored linkage inside payload instead of ref_table/ref_id.
    $likeIdQuoted = '%"' . $id . '"%';
    $likeIdNumber = '%:' . $id . ',%'; // rough pattern (e.g. "some":123,)
    $likeIdEnd = '%:' . $id . '}%';
    $stmt = $mysqli->prepare("
        DELETE FROM data
        WHERE payload LIKE ? OR payload LIKE ? OR payload LIKE ?
    ");
    if ($stmt) {
        $stmt->bind_param('sss', $likeIdQuoted, $likeIdNumber, $likeIdEnd);
        $stmt->execute();
        $totalDeletedData += $stmt->affected_rows;
        $stmt->close();
    }

    // --- NEW: remove salary_adjustments rows linked to this workdays id and their data entries ---
    // find adjustments ids referencing this workdays id
    $adjIds = [];
    $stmt = $mysqli->prepare("SELECT id FROM salary_adjustments WHERE workdays_id = ?");
    if ($stmt) {
        $stmt->bind_param('i', $id);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $adjIds[] = intval($row['id']);
            }
        }
        $stmt->close();
    }
    if (!empty($adjIds)) {
        // delete data rows that reference those adjustment ids
        // build placeholders for IN(...)
        $placeholders = implode(',', array_fill(0, count($adjIds), '?'));
        // prepare types and params for bind_param
        $types = str_repeat('i', count($adjIds));
        $sql = "DELETE FROM data WHERE ref_table = 'salary_adjustments' AND ref_id IN ($placeholders)";
        $stmt = $mysqli->prepare($sql);
        if ($stmt) {
            // dynamic bind
            $refs = [];
            $refs[] = & $types;
            for ($i = 0; $i < count($adjIds); $i++) {
                $refs[] = & $adjIds[$i];
            }
            // call_user_func_array expects array of references
            call_user_func_array([$stmt, 'bind_param'], $refs);
            $stmt->execute();
            $totalDeletedData += $stmt->affected_rows;
            $stmt->close();
        } else {
            // fallback: delete each by id individually
            foreach ($adjIds as $aid) {
                $dstmt = $mysqli->prepare("DELETE FROM data WHERE ref_table = 'salary_adjustments' AND ref_id = ?");
                if ($dstmt) {
                    $dstmt->bind_param('i', $aid);
                    $dstmt->execute();
                    $totalDeletedData += $dstmt->affected_rows;
                    $dstmt->close();
                }
            }
        }

        // delete the salary_adjustments rows themselves
        // use IN(...) with prepared statement similarly
        $placeholders2 = implode(',', array_fill(0, count($adjIds), '?'));
        $types2 = str_repeat('i', count($adjIds));
        $sql2 = "DELETE FROM salary_adjustments WHERE id IN ($placeholders2)";
        $stmt2 = $mysqli->prepare($sql2);
        if ($stmt2) {
            $refs2 = [];
            $refs2[] = & $types2;
            for ($i = 0; $i < count($adjIds); $i++) {
                $refs2[] = & $adjIds[$i];
            }
            call_user_func_array([$stmt2, 'bind_param'], $refs2);
            $stmt2->execute();
            $stmt2->close();
        } else {
            // fallback: delete individually
            foreach ($adjIds as $aid) {
                $dstmt = $mysqli->prepare("DELETE FROM salary_adjustments WHERE id = ?");
                if ($dstmt) {
                    $dstmt->bind_param('i', $aid);
                    $dstmt->execute();
                    $dstmt->close();
                }
            }
        }
    }
    // --- END NEW ---

    // 4) Finally delete salary_workdays row
    $stmt = $mysqli->prepare("DELETE FROM salary_workdays WHERE id = ?");
    if (!$stmt) throw new Exception('prepare_failed_delete_workdays');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    $mysqli->commit();

    echo json_encode(['success' => true, 'deleted_rows' => $affected, 'deleted_data_rows' => $totalDeletedData]);
} catch (Exception $e) {
    $mysqli->rollback();
    error_log('delete_workdays error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$mysqli->close();
