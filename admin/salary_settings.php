<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$conn = new mysqli('localhost', 'root', '', 'salary_system');
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

// ดึงข้อมูลบริษัทที่เลือก
$tax_id = $_SESSION['selected_company_tax_id'] ?? '';
$company = [];
if ($tax_id) {
  $result = $conn->query("SELECT * FROM companies WHERE tax_id='$tax_id' LIMIT 1");
  $company = $result->fetch_assoc();
}
// ดึง salary_types ของบริษัท (แยก allowance / deduction)
$salary_allowances = [];
$salary_deductions = [];
if ($tax_id) {
    $stmt = $conn->prepare("SELECT id, name, `type` FROM salary_types WHERE company_tax_id = ? ORDER BY created_at ASC, id ASC");
    $stmt->bind_param("s", $tax_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        if ($r['type'] === 'deduction') $salary_deductions[] = $r;
        else $salary_allowances[] = $r;
    }
    $stmt->close();
}
// ดึงข้อมูลผู้ดูแลระบบ
$admins = [];
$result = $conn->query("SELECT * FROM admin");
while ($row = $result->fetch_assoc()) {
    $admins[] = $row;
}
// ดึงข้อมูลพนักงานสำหรับบริษัทที่เลือก (ใช้ในการเติม select ของ UI)
$employees = [];
if ($tax_id) {
  $stmt = $conn->prepare("SELECT id, prefix, firstname, lastname, fullname, position, status, is_insured FROM employees WHERE company_tax_id = ? ORDER BY fullname ASC, id ASC");
  $stmt->bind_param("s", $tax_id);
  $stmt->execute();
  $res = $stmt->get_result();
  while ($r = $res->fetch_assoc()) {
    // ensure fullname exists
    if (empty($r['fullname'])) {
      $r['fullname'] = trim(($r['prefix'] ? $r['prefix'] . ' ' : '') . ($r['firstname'] ?? '') . ' ' . ($r['lastname'] ?? ''));
    }
    $employees[] = $r;
  }
  $stmt->close();
}
// นับจำนวนพนักงานที่ status = 'ทำงานอยู่'
$working_count = 0;
foreach ($employees as $emp) {
  if (isset($emp['status']) && $emp['status'] === 'ทำงานอยู่') {
    $working_count++;
  }
}
// ดึงรายการจ่ายเงินล่วงหน้าของบริษัท (สำหรับ modal) พร้อมวันที่จาก data (ถ้ามี)
$advances = [];
if ($tax_id) {
  $stmt = $conn->prepare("SELECT ap.*, d.payload 
    FROM advance_payments ap 
    LEFT JOIN data d ON d.ref_table='advance_payments' AND d.ref_id=ap.id 
    WHERE ap.company_tax_id = ? 
    ORDER BY ap.advance_date ASC, ap.id ASC");
  if ($stmt) {
    $stmt->bind_param("s", $tax_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
      // ถ้ามี payload (json) ใน data ให้ดึง advance_date จาก payload (ถ้ามี) มาแทน
      if (!empty($r['payload'])) {
        $payload = json_decode($r['payload'], true);
        if (!empty($payload['advance_date'])) {
          $r['advance_date'] = $payload['advance_date'];
        }
      }
      $advances[] = $r;
    }
    $stmt->close();
  }
}

// --- เพิ่ม: ดึงค่าอัตราแรงล่าสุดจาก salary_history สำหรับพนักงานที่โหลดมา ---
$emp_latest_salary = [];
if (!empty($employees)) {
    $ids = array_map('intval', array_column($employees, 'id'));
    if (!empty($ids)) {
        $id_list = implode(',', $ids);
        $sql = "SELECT sh.employee_id, sh.amount
                FROM salary_history sh
                JOIN (
                  SELECT employee_id, MAX(change_date) AS md
                  FROM salary_history
                  WHERE employee_id IN ($id_list)
                  GROUP BY employee_id
                ) m ON sh.employee_id = m.employee_id AND sh.change_date = m.md";
        if ($res2 = $conn->query($sql)) {
            while ($row2 = $res2->fetch_assoc()) {
                $emp_latest_salary[intval($row2['employee_id'])] = floatval($row2['amount']);
            }
            $res2->free();
        }
    }
}

// --- เพิ่ม: ส่งสถานะ is_insured ของพนักงานให้ JS ใช้งาน ---
$emp_js = [];
foreach ($employees as $e) {
    $emp_js[intval($e['id'])] = [
        'is_insured' => $e['is_insured'],
    ];
}

$conn->close();
?>

<?php if (isset($_SESSION['success_message'])): ?>
  <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
    <?= $_SESSION['success_message']; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  <?php unset($_SESSION['success_message']); ?>
<?php endif; ?>

<!-- company-header เหมือนหน้า company_settings.php -->
<div class="company-header d-flex justify-content-between align-items-center flex-wrap shadow-sm mb-4">
  <div>
    <div class="company-name"><?= htmlspecialchars($company['name'] ?? '') ?></div>
    <div class="tax-id">เลขประจำตัวผู้เสียภาษี: <?= htmlspecialchars($company['tax_id'] ?? '') ?></div>
  </div>
  <div>
    <div class="dropdown">
      <button class="btn btn-add dropdown-toggle px-4 py-2" type="button" id="companyDropdown" data-bs-toggle="dropdown" aria-expanded="false"
        style="font-weight:500;box-shadow:0 2px 8px rgba(0,176,116,0.08);border-radius:8px;background:#009688;">
        <i class="bi bi-building me-2"></i>
        <span><?= htmlspecialchars($company['name'] ?? '') ?></span>
      </button>
      <ul class="dropdown-menu shadow" aria-labelledby="companyDropdown" style="min-width:220px;border-radius:12px;">
        <?php foreach ($companies as $c): ?>
          <li>
            <a class="dropdown-item d-flex align-items-center<?= ($c['tax_id'] == ($company['tax_id'] ?? '')) ? ' active selected-company' : '' ?>"
               href="dashboard.php?page=admin_settings.php&change_company=<?= htmlspecialchars($c['tax_id']) ?>"
               style="font-size:1.08rem;padding:10px 18px;border-radius:8px;">
              <i class="bi bi-building me-2" style="color:#009688;"></i>
              <span><?= htmlspecialchars($c['name']) ?></span>
              <?php if ($c['tax_id'] == ($company['tax_id'] ?? '')): ?>
                <i class="bi bi-check-circle ms-auto" style="color:#fff;margin-left:5px;"></i>
              <?php endif; ?>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
</div>
<!-- Panel title and action buttons -->
<div class="panel-title-row">
  <div class="panel-title"><i class="bi bi-cash-coin me-2"></i> ข้อมูลเงินเดือน</div>
  <div class="d-flex gap-2">
    <button id="exportCsvBtn" class="btn btn-outline-success" title="Export to CSV" style="border-radius:8px;">
      <i class="bi bi-download me-2"></i> Export to CSV
    </button>
    <button id="advancePayBtn" class="btn btn-light" title="จ่ายเงินล่วงหน้า" style="border-radius:8px;border:1px solid #e0f2f1;color:#009688;">
      <i class="bi bi-wallet2 me-2"></i> จ่ายเงินล่วงหน้า
    </button>
    <button id="paySalaryBtn" class="btn btn-add" title="จ่ายเงินเดือน" style="border-radius:8px;">
      <i class="bi bi-currency-dollar me-2"></i> จ่ายเงินเดือน
    </button>
  </div>
</div>

<!-- Date selector (เลือกวัน/เดือน/ปี) -->
<div class="table-box shadow-sm">
  <div class="row align-items-center mb-3">
    <div class="col-md-4">
      <label class="form-label">เลือกเดือน/ปี และวันที่จ่าย:</label>
      <div class="input-group">
        <span class="input-group-text bg-white"><i class="bi bi-calendar-event" style="color:#009688;"></i></span>
        <input type="month" id="salary_month" class="form-control" value="<?= date('Y-m') ?>" />
        <select id="salary_day" class="form-select" style="max-width:110px;">
          <option value="05">วันที่ 5</option>
          <option value="20">วันที่ 20</option>
        </select>
      </div>
      <div id="selectedDateDisplay" class="mt-2 text-muted" style="font-size:0.95rem;"></div>
    </div>
  <div class="alert alert-light border-0" style="background:#fff;">
    เลือกวันที่ เพื่อแสดงข้อมูลการจ่ายเงินเดือนในวันที่เลือก ถ้ายังไม่มีข้อมูลจะแสดงรายการว่าง
  </div>

  <!-- Salary table -->
  <div class="table-responsive">
    <table class="table table-bordered align-middle">
      <thead>
        <tr>
          <th style="width:40px">ลำดับ</th>
          <th>ชื่อ - นามสกุล</th>
          <th style="width:160px">ตำแหน่ง</th>
          <th style="width:140px">วันที่</th>
          <th style="width:140px">เงินเดือน (บาท)</th>
          <th style="width:140px">รายการเพิ่ม</th>
          <th style="width:120px">รายการหัก</th>
          <th style="width:140px">ยอดเงินทั้งหมดหลัง +/-</th>
        </tr>
      </thead>
 <tbody id="salaryTableBody">
        <tr>
          <td colspan="7" class="text-center text-muted">ยังไม่มีข้อมูลการจ่ายในวันที่เลือก</td>
          <td colspan="8" class="text-center text-muted">ยังไม่มีข้อมูลการจ่ายในวันที่เลือก</td>        
          <td colspan="8" class="text-center text-muted">ยังไม่มีข้อมูลการจ่ายในวันที่เลือก</td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal placeholders (เพิ่มเติมเมื่อต้องการ) -->
<!-- ... -->

<!-- JS -->
<script>
  // ฟังก์ชันแปลงวันที่ (YYYY-MM-DD) -> รูปแบบไทย: D เดือน พ.ศ.
  function formatThaiDate(isoDate) {
    if (!isoDate) return '';
    const monthsThai = ['มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
    const parts = isoDate.split('-'); // [YYYY,MM,DD]
    if (parts.length !== 3) return isoDate;
    const y = parseInt(parts[0], 10) + 543;
    const m = parseInt(parts[1], 10);
    const d = parseInt(parts[2], 10);
    return d + ' ' + monthsThai[m-1] + ' ' + y;
  }

  function getSelectedIsoDate() {
    const monthEl = document.getElementById('salary_month');
    const dayEl = document.getElementById('salary_day');
    if (!monthEl) return '';
    const ym = monthEl.value; // "YYYY-MM"
    const day = (dayEl && dayEl.value) ? dayEl.value : '05';
    if (!ym) return '';
    return ym + '-' + day;
  }

  function updateSelectedDisplay() {
    const iso = getSelectedIsoDate();
    const disp = document.getElementById('selectedDateDisplay');
    disp.textContent = iso ? 'วันที่ที่เลือก: ' + formatThaiDate(iso) : '';
  }

  // โหลดข้อมูลเมื่อเปลี่ยน เดือน หรือ วันที่ (ใช้ 05 / 20)
  function loadSalaryForSelected() {
    const isoDate = getSelectedIsoDate(); // YYYY-MM-DD
    const tbody = document.getElementById('salaryTableBody');
    updateSelectedDisplay();
    if (!isoDate) {
      tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted">กรุณาเลือกเดือนและวันที่</td></tr>';
      return;
    }
    tbody.innerHTML = '<tr><td colspan="8" class="text-center">กำลังโหลดข้อมูลสำหรับ ' + formatThaiDate(isoDate) + ' ...</td></tr>';
    const selectedDay = (document.getElementById('salary_day') && document.getElementById('salary_day').value) ? document.getElementById('salary_day').value : '';
    fetch('ajax/get_salaries.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ date: isoDate, company_tax_id: '<?= addslashes($company['tax_id'] ?? '') ?>' })
    })
    .then(r => r.json())
    .then(data => {
      if (!data || !data.success) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-danger">เกิดข้อผิดพลาดในการโหลดข้อมูล</td></tr>';
        console.error('get_salaries error', data);
        return;
      }
      let rows = data.rows || [];

      // Keep only rows that have a recorded payment_date whose day matches selectedDay (05 or 20)
      rows = rows.filter(function(r){
        if (!r.payment_date) return false;
        // normalize iso like 'YYYY-MM-DD' or with time
        var pd = String(r.payment_date).split('T')[0];
        var parts = pd.split('-');
        if (parts.length !== 3) return false;
        // pad day to two digits for comparison
        var day = parts[2].padStart(2, '0');
        return day === String(selectedDay).padStart(2, '0');
      });

      if (rows.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted">ยังไม่มีข้อมูลการจ่ายในวันที่เลือก</td></tr>';
        return;
      }

      // build rows
      tbody.innerHTML = '';
      rows.forEach(function(r, idx){
        const name = (r.firstname || '') + (r.lastname ? ' ' + r.lastname : '');
        const pos = r.position || '';
        // use the actual payment_date (parsed) to display
        var pd = String(r.payment_date).split('T')[0];
        const dateDisp = formatThaiDate(pd.length === 10 ? pd : isoDate);
        const salary = (r.total_amount !== undefined && r.total_amount !== null) ? Number(r.total_amount).toFixed(2) : '0.00';
        const additions = '-';
        const deductions = '-';
        const totalAfter = salary;
        const tr = document.createElement('tr');
        tr.innerHTML =
          '<td style="width:40px">' + (idx+1) + '</td>' +
          '<td>' + escapeHtml(name) + '</td>' +
          '<td style="width:160px">' + escapeHtml(pos) + '</td>' +
          '<td style="width:140px">' + escapeHtml(dateDisp) + '</td>' +
          '<td style="width:140px; text-align:right;">' + salary + '</td>' +
          '<td style="width:140px; text-align:right;">' + additions + '</td>' +
          '<td style="width:120px; text-align:right;">' + deductions + '</td>' +
          '<td style="width:140px; text-align:right; font-weight:700;">' + Number(totalAfter).toFixed(2) + '</td>';
        tbody.appendChild(tr);
      });
    })
    .catch(err => {
      console.error(err);
      tbody.innerHTML = '<tr><td colspan="8" class="text-center text-danger">เกิดข้อผิดพลาดในการโหลดข้อมูล</td></tr>';
    });
  }

  // small helper to avoid XSS in inserted text
  function escapeHtml(str){
    if (!str) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }
  document.getElementById('salary_month').addEventListener('change', loadSalaryForSelected);
  document.getElementById('salary_day').addEventListener('change', loadSalaryForSelected);

  // ตั้งค่าเริ่มต้น: ถ้าวันปัจจุบัน >=20 ให้เลือกวันที่ 20 มิฉะนั้น 05
  (function setDefaultDay() {
    const today = new Date();
    const dd = today.getDate();
    const daySelect = document.getElementById('salary_day');
    if (daySelect) {
      daySelect.value = dd >= 20 ? '20' : '05';
    }
  })();

  // โหลดเริ่มต้นเมื่อ DOM พร้อม
  window.addEventListener('DOMContentLoaded', function() {
    updateSelectedDisplay();
    loadSalaryForSelected();
  });

  // ปุ่มตัวอย่าง (ส่ง iso date ถ้าต้องการ)
  document.getElementById('exportCsvBtn').addEventListener('click', function() {
    const iso = getSelectedIsoDate();
    alert('Export to CSV — วันที่: ' + (iso || '-') + ' (implement server export)');
  });
  document.getElementById('advancePayBtn').addEventListener('click', function() {
    // แสดง modal จ่ายเงินล่วงหน้า
    var modal = document.getElementById('advanceModal');
    if (modal) modal.style.display = 'block';
  });
  document.getElementById('paySalaryBtn').addEventListener('click', function() {
    // แสดง modal จ่ายเงินเดือน (UI only)
    var modal = document.getElementById('paySalaryModal');
    if (modal) modal.style.display = 'block';
  });
</script>

<!-- Advance Pay Modal (UI only) -->
<div id="advanceModal" class="modal" style="display:none; position:fixed; z-index:1050; left:0; top:0; width:100vw; height:100vh; overflow:auto; background:rgba(0,0,0,0.4);">
  <div style="background:#fff; margin:36px auto; padding:22px 20px 18px 20px; border-radius:8px; width:95%; max-width:760px; position:relative; box-shadow:0 8px 32px rgba(0,0,0,0.12);">
    <button id="closeAdvanceModal" class="btn" style="position:absolute; right:12px; top:10px; font-size:22px; background:transparent; border:none;">&times;</button>
    <h4 style="margin-top:4px; margin-bottom:6px;">จ่ายเงินล่วงหน้า</h4>
    <div style="display:flex; gap:18px; align-items:flex-start; margin-bottom:12px;">
      <div style="flex:1; min-width:180px;">
        <label style="font-weight:600; font-size:0.95rem;">วันที่จ่ายเงิน</label>
        <input id="advance_date" type="date" value="<?= date('Y-m-d') ?>" style="width:100%; padding:8px; border-radius:6px; border:1px solid #e6e6e6;">
        <div style="margin-top:8px; color:#666; font-size:0.9rem;">เดือนที่เลือก: <span id="advance_selected_month">-</span></div>
        <div style="margin-top:6px; color:#888; font-size:0.85rem;">หมายเหตุ: จะจ่ายเงินล่วงหน้าได้เฉพาะพนักงานที่ยังไม่ได้รับเงินเดือนในเดือนนี้เท่านั้น</div>
      </div>
    </div>

    <div style="margin-top:6px; margin-bottom:14px; display:flex; justify-content:space-between; align-items:center;">
      <div style="font-weight:600;">รายการจ่ายเงินล่วงหน้า</div>
      <button id="addAdvanceRow2" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;">+ เพิ่มรายการ</button>
    </div>

    <div style="overflow:auto; max-height:340px;">
      <table id="advanceTable" style="width:100%; border-collapse:collapse;">
        <thead>
          <tr style="background:#fafafa;">
            <th style="padding:10px; text-align:left; border-bottom:1px solid #eee;">พนักงาน</th>
            <th style="padding:10px; text-align:left; border-bottom:1px solid #eee; width:140px;">จำนวนเงิน (บาท)</th>
            <th style="padding:10px; text-align:left; border-bottom:1px solid #eee; width:220px;">หมายเหตุ</th>
            <th style="padding:10px; text-align:center; border-bottom:1px solid #eee; width:80px;">จัดการ</th>
          </tr>
        </thead>
        <tbody id="advanceTbody">
          <?php if (!empty($advances)): ?>
            <?php foreach ($advances as $adv): ?>
              <?php
                // find employee display name if available
                $empName = '';
                foreach ($employees as $ee) { if ($ee['id'] == $adv['employee_id']) { $empName = $ee['fullname']; if (!empty($ee['position'])) $empName .= ' — ' . $ee['position']; break; } }
              ?>
              <tr data-advance-id="<?= intval($adv['id']) ?>" data-slip-image="<?= htmlspecialchars($adv['slip_image'] ?? '') ?>">
                <td style="padding:10px;">
                  <select style="width:100%; padding:8px; border-radius:6px;" class="advanceEmployeeSelect">
                    <option value="">-- เลือกพนักงาน --</option>
                    <?php foreach ($employees as $emp): ?>
                      <?php if (!isset($emp['status']) || $emp['status'] !== 'ทำงานอยู่') continue; ?>
                      <option value="<?= intval($emp['id']) ?>"<?= ($emp['id'] == $adv['employee_id']) ? ' selected' : '' ?>><?= htmlspecialchars($emp['fullname']) ?><?= isset($emp['position']) && $emp['position'] ? ' — ' . htmlspecialchars($emp['position']) : '' ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
                <td style="padding:10px;"><input type="number" min="0" step="0.01" value="<?= number_format($adv['amount'], 2, '.', '') ?>" style="width:100%; padding:8px; border-radius:6px; border:1px solid #e6e6e6;"></td>
                <td style="padding:10px;"><input type="text" placeholder="หมายเหตุ (ถ้ามี)" value="<?= htmlspecialchars($adv['note_slip'] ?? '') ?>" style="width:100%; padding:8px; border-radius:6px; border:1px solid #e6e6e6;"></td>
                <td style="padding:10px; text-align:center; display:flex; gap:6px; justify-content:center;">
                  <button class="editAdvanceBtn btn btn-sm" title="แก้ไข" style="border-radius:6px; background:#fff; border:1px solid #eef0f2; padding:6px 8px;"><i class="bi bi-pencil-square" style="color:#0d6efd;"></i></button>
                  <button class="removeAdvanceBtn btn btn-sm" title="ลบรายการ" style="border-radius:6px; background:#fff; border:1px solid #f0f0f0; padding:6px 8px;"><i class="bi bi-trash" style="color:#d9534f;"></i></button>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td style="padding:10px;">
                <select style="width:100%; padding:8px; border-radius:6px;" class="advanceEmployeeSelect">
                  <option value="">-- เลือกพนักงาน --</option>
                  <?php if (!empty($employees)): ?>
                    <?php foreach ($employees as $emp): ?>
                      <?php if (!isset($emp['status']) || $emp['status'] !== 'ทำงานอยู่') continue; ?>
                      <option value="<?= intval($emp['id']) ?>"><?= htmlspecialchars($emp['fullname']) ?><?= isset($emp['position']) && $emp['position'] ? ' — ' . htmlspecialchars($emp['position']) : '' ?></option>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </select>
              </td>
              <td style="padding:10px;"><input type="number" min="0" step="0.01" value="0.00" style="width:100%; padding:8px; border-radius:6px; border:1px solid #e6e6e6;"></td>
              <td style="padding:10px;"><input type="text" placeholder="หมายเหตุ (ถ้ามี)" style="width:100%; padding:8px; border-radius:6px; border:1px solid #e6e6e6;"></td>
              <td style="padding:10px; text-align:center; display:flex; gap:6px; justify-content:center;">
                <button class="editAdvanceBtn btn btn-sm" title="แก้ไข" style="border-radius:6px; background:#fff; border:1px solid #eef0f2; padding:6px 8px;"><i class="bi bi-pencil-square" style="color:#0d6efd;"></i></button>
                <button class="removeAdvanceBtn btn btn-sm" title="ลบรายการ" style="border-radius:6px; background:#fff; border:1px solid #f0f0f0; padding:6px 8px;"><i class="bi bi-trash" style="color:#d9534f;"></i></button>
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div style="display:flex; justify-content:flex-end; gap:12px; margin-top:14px;">
      <button id="cancelAdvance2" class="btn btn-light">ยกเลิก</button>
      <button id="saveAdvanceDemo" class="btn btn-success">บันทึกทั้งหมด</button>
    </div>
  </div>
</div>

<script>
<?php
$__employee_options_html = '';
if (!empty($employees)) {
    foreach ($employees as $emp) {
        // only include employees who are currently working
        if (!isset($emp['status']) || $emp['status'] !== 'ทำงานอยู่') continue;
        $opt = '<option value="' . intval($emp['id']) . '">' . htmlspecialchars($emp['fullname']);
        if (!empty($emp['position'])) $opt .= ' — ' . htmlspecialchars($emp['position']);
        // do not include status text in option (we know they are working)
        $opt .= '</option>';
        $__employee_options_html .= $opt;
    }
}
?>
var employeeOptionsHtml = <?= json_encode($__employee_options_html) ?>;
function buildEmployeeOptionsHtml() {
  return '<option value="">-- เลือกพนักงาน --</option>' + employeeOptionsHtml;
}

document.getElementById('addAdvanceRow2').addEventListener('click', function(){
  var tbody = document.getElementById('advanceTbody');
  // ลบแถวที่ไม่มีข้อมูล (placeholder) ออกก่อนเพิ่มแถวใหม่
  var emptyRows = tbody.querySelectorAll('tr td[colspan]');
  emptyRows.forEach(function(td){
    var tr = td.parentNode;
    if (tr) tr.remove();
  });

  // สร้างแถวใหม่แบบเดียวกับที่โหลดจากฐานข้อมูล
  var tr = document.createElement('tr');
  tr.dataset.advanceId = '';
  tr.dataset.slipImage = '';
  tr.dataset.noteSlip = '';
  tr.innerHTML =
    '<td style="padding:10px;">' +
      '<select style="width:100%; padding:8px; border-radius:6px;" class="advanceEmployeeSelect">' +
        buildEmployeeOptionsHtml() +
      '</select>' +
    '</td>' +
    '<td style="padding:10px;">' +
      '<input type="number" min="0" step="0.01" value="0.00" style="width:100%; padding:8px; border-radius:6px; border:1px solid #e6e6e6;">' +
    '</td>' +
    '<td style="padding:10px;">' +
      '<input type="text" placeholder="หมายเหตุ (ถ้ามี)" style="width:100%; padding:8px; border-radius:6px; border:1px solid #e6e6e6;">' +
    '</td>' +
    '<td style="padding:10px; text-align:center; display:flex; gap:6px; justify-content:center;">' +
      '<button class="editAdvanceBtn btn btn-sm" title="แก้ไข" style="border-radius:6px; background:#fff; border:1px solid #eef0f2; padding:6px 8px;">' +
        '<i class="bi bi-pencil-square" style="color:#0d6efd;"></i>' +
      '</button>' +
      '<button class="removeAdvanceBtn btn btn-sm" title="ลบรายการ" style="border-radius:6px; background:#fff; border:1px solid #f0f0f0; padding:6px 8px;">' +
        '<i class="bi bi-trash" style="color:#d9534f;"></i>' +
      '</button>' +
    '</td>';

  tbody.appendChild(tr);
  tr.scrollIntoView({ behavior: "smooth", block: "center" });
});

// Ensure removeAdvanceBtn works
document.getElementById('advanceTbody').addEventListener('click', function(e){
  if(e.target && (e.target.classList.contains('removeAdvanceBtn') || e.target.closest('.removeAdvanceBtn'))){
    var tr = e.target.closest('tr'); if(tr) tr.remove();
  }
});
</script>

<!-- Pay Salary Modal (UI only) -->
<div id="paySalaryModal" class="modal" style="display:none; position:fixed; z-index:1060; left:0; top:0; width:100vw; height:100vh; overflow:auto; background:rgba(0,0,0,0.45);">
      <div style="background:#fff; margin:28px auto; padding:18px 18px 16px 18px; border-radius:8px; width:96%; max-width:920px; position:relative; box-shadow:0 12px 40px rgba(0,0,0,0.18);">
        <button id="closePayModal" class="btn" style="position:absolute; right:12px; top:10px; font-size:22px; background:transparent; border:none;">&times;</button>
        <h4 style="margin-top:0;">จ่ายเงินเดือน</h4>

        <!-- Header: month + date -->
        <div style="display:flex; gap:16px; align-items:center; margin-bottom:12px;">
          <div style="flex:1; background:#fafafa; padding:12px; border-radius:8px;">
            <div style="font-size:0.95rem; color:#666;">สำหรับเดือน</div>
            <div id="pay_month_display" style="font-weight:700; margin-top:6px;"><?= date('F Y') ?></div>
          </div>
          <div style="width:220px; background:#fafafa; padding:12px; border-radius:8px; text-align:center;">
            <div style="font-size:0.95rem; color:#666;">วันที่จ่าย</div>
            <div id="pay_date_display" style="font-weight:700; margin-top:6px;"><?= date('j M Y') ?></div>
          </div>
        </div>

        <!-- Summary boxes -->
        <div style="display:flex; gap:12px; margin-bottom:12px;">
          <div style="flex:1; background:#e9f7ef; padding:16px; border-radius:8px; text-align:center;">
            <div style="color:#777;">จำนวนพนักงาน</div>
            <div id="summary_count" style="font-size:20px; font-weight:700; margin-top:6px; color:#009688;">
              <?= $working_count ?> คน
            </div>
          </div>
          <div style="flex:1; background:#eef6ff; padding:16px; border-radius:8px; text-align:center;">
            <div style="color:#777;">จำนวนเงินรวม</div>
            <div id="summary_total" style="font-size:20px; font-weight:700; margin-top:6px; color:#0b5ed7;">0.00 บาท</div>
          </div>
        </div>

        <div style="margin-bottom:10px; color:#666;">รายชื่อพนักงานที่จะจ่ายเงินเดือนในเดือน <span id="list_month_label"><?= date('F Y') ?></span></div>

        <div style="display:flex; gap:12px; align-items:center; margin-bottom:10px;">
          <select id="addEmployeeSelect" style="flex:1; padding:8px; border-radius:6px; border:1px solid #e6e6e6;">
            <option value="">-- เลือกพนักงานที่ต้องการเพิ่ม --</option>
            <?php if (!empty($employees)): ?>
              <?php foreach ($employees as $emp): ?>
                <?php if (isset($emp['status']) && $emp['status'] === 'ทำงานอยู่'): ?>
                  <option value="<?= intval($emp['id']) ?>">
                    <?= htmlspecialchars($emp['fullname']) ?>
                    <?= isset($emp['position']) && $emp['position'] ? ' — ' . htmlspecialchars($emp['position']) : '' ?>
                  </option>
                <?php endif; ?>
              <?php endforeach; ?>
            <?php endif; ?>
          </select>
          <button id="addEmployeeBtn" class="btn btn-secondary" style="border-radius:8px;">เพิ่มพนักงาน</button>
        </div>

       <div style="overflow:auto; max-height:360px; border:1px solid #f0f0f0; border-radius:8px; padding:8px;">
          <table style="width:100%; border-collapse:collapse;">
            <thead>
              <tr style="background:#fafafa;">
                <th style="padding:10px;">ชื่อพนักงาน</th>
                <th style="padding:10px; width:90px; text-align:center;">วันทำงาน (9 ชม)</th>
                <th style="padding:10px; width:90px; text-align:center;">วันทำงาน (12ชม)</th>
                <th style="padding:10px; width:120px; text-align:center;">ประกันสังคม</th>
                <th style="padding:10px; width:120px; text-align:center;">รายการเพิ่ม/หัก</th>
                <th style="padding:10px; width:100px; text-align:center;">รวมเงิน</th>
                <th style="padding:10px; width:80px; text-align:center;">จัดการ</th>
              </tr>
            </thead>
            <tbody id="paySalaryTbody">
              <!-- sample empty row -->
              <tr>
                <td style="padding:10px;"><div style="color:#666;">-- ยังไม่มีพนักงาน --</div></td>
                <td style="padding:8px; text-align:center;"><input class="wd9" type="number" min="0" value="0" style="width:64px; padding:6px; border-radius:6px; border:1px solid #e6e6e6;"></td>
                <td style="padding:8px; text-align:center;"><input class="wd12" type="number" min="0" value="0" style="width:64px; padding:6px; border-radius:6px; border:1px solid #e6e6e6;"></td>
                <td style="padding:8px; text-align:center;"><div style="color:#999;">ไม่เข้าร่วมประกันสังคม</div></td>
                <td style="padding:8px; text-align:center; display:flex; justify-content:center; gap:6px; align-items:center;">
                  <input type="number" min="0" value="0.00" step="0.01" class="adjustmentsInput" style="width:90px; padding:6px; border-radius:6px; border:1px solid #e6e6e6;">
                  <button class="btn btn-sm openAdjustments" title="จัดการรายการเพิ่ม/หัก" style="border-radius:6px; padding:6px 8px; background:#fff; border:1px solid #eef0f2;"><i class="bi bi-pencil-square" style="color:#0d6efd;"></i></button>
                </td>
                <td style="padding:8px; text-align:center;"><div class="rowTotal" style="font-weight:700;">0.00</div></td>
                <td style="padding:8px; text-align:center;"><button class="removePayRow btn btn-sm" title="ลบ" style="border-radius:6px; background:#fff; border:1px solid #f0f0f0; padding:6px 8px;"><i class="bi bi-trash" style="color:#d9534f;"></i></button></td>
              </tr>
            </tbody>
          </table>
        </div>

        <div style="display:flex; justify-content:flex-end; gap:12px; margin-top:12px;">
          <button id="cancelPayBtn" class="btn btn-light">ยกเลิก</button>
          <button id="savePayDemo" class="btn btn-success">จ่ายเงินเดือน</button>
        </div>
      </div>
    </div>

    <script>
    // Pay modal behaviors (UI only)
    document.getElementById('closePayModal').addEventListener('click', function(){ document.getElementById('paySalaryModal').style.display = 'none'; });
    document.getElementById('cancelPayBtn').addEventListener('click', function(){ document.getElementById('paySalaryModal').style.display = 'none'; });

    // remove pay row -> open delete-note modal then remove on confirm
    document.getElementById('paySalaryTbody').addEventListener('click', function(e){
      if(e.target && (e.target.classList.contains('removePayRow') || e.target.closest('.removePayRow'))){
        var tr = e.target.closest('tr');
        if(!tr) return;

        // ถ้ามี workdaysId (บันทึกแล้ว) ให้เรียก API ลบบนเซิร์ฟเวอร์
        var workdaysId = parseInt(tr.dataset.workdaysId || 0);
        if (workdaysId > 0) {
          if (!confirm('ยืนยันการลบรายการเงินเดือนจากระบบ?')) return;
          fetch('ajax/delete_workdays.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: workdaysId })
          })
          .then(function(r){ return r.json(); })
          .then(function(res){
            if(res && res.success){
              tr.remove();
              updateSummaryTotal();
            } else {
              console.error(res);
              alert('ลบไม่สำเร็จ (ดูคอนโซล)');
            }
          })
          .catch(function(err){
            console.error(err);
            alert('เกิดข้อผิดพลาดขณะลบ');
          });
        } else {
          // ยังไม่ได้บันทึก -> ลบเฉพาะ DOM ได้เลย (ไม่ต้องมีการเพิ่มพนักงาน)
          if (!confirm('ยืนยันการลบรายการนี้?')) return;
          tr.remove();
          updateSummaryTotal();
        }
      }
    });

    // Replace the demo save handler with real AJAX save to salary_workdays
    document.getElementById('savePayDemo').addEventListener('click', function(){
      var tbody = document.getElementById('paySalaryTbody');
      var rows = Array.from(tbody.querySelectorAll('tr')).filter(function(tr){
        return tr.dataset && tr.dataset.empId && tr.dataset.empId !== '';
      });
      if(rows.length === 0){ alert('ไม่มีพนักงานที่จะบันทึก'); return; }

      var monthVal = document.getElementById('salary_month') ? document.getElementById('salary_month').value : '';
      if(!monthVal){ alert('โปรดเลือกเดือนก่อนบันทึก'); return; }

      // build payload
      var payloadRows = rows.map(function(tr){
        var empId = parseInt(tr.dataset.empId);
        var wd9 = parseInt(tr.querySelector('.wd9')?.value || 0) || 0;
        var wd12 = parseInt(tr.querySelector('.wd12')?.value || 0) || 0;
        var total = parseFloat(tr.dataset.totalAmount || 0) || 0;
        var rowId = parseInt(tr.dataset.workdaysId || 0) || 0;
        // also include adjustments JSON if any
        var adjustments = null;
        try { adjustments = tr.querySelector('.adjustmentsInput')?.dataset.adjustments ? JSON.parse(tr.querySelector('.adjustmentsInput').dataset.adjustments) : null; } catch(e){ adjustments = null; }
        return { id: rowId, employee_id: empId, workdays_9hr: wd9, workdays_12hr: wd12, total_amount: total, adjustments: adjustments };
      });

      var payload = {
        company_tax_id: '<?= addslashes($company['tax_id'] ?? '') ?>',
        month: monthVal,
        rows: payloadRows
      };

      var btn = document.getElementById('savePayDemo');
      btn.disabled = true;
      btn.textContent = 'กำลังบันทึก...';

      fetch('ajax/save_salary_workdays.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      }).then(function(r){ return r.json(); })
        .then(function(res){
          if(res && res.success){
            // ถ้าเซิร์ฟเวอร์ส่ง back saved_ids [{employee_id:..., id:...}, ...] ให้เก็บไว้ใน tr.dataset.workdaysId
            if(Array.isArray(res.saved_ids)){
              res.saved_ids.forEach(function(item){
                try{
                  if(item && item.employee_id && item.id){
                    var tr = document.querySelector('#paySalaryTbody tr[data-emp-id="'+item.employee_id+'"]');
                    if(tr) tr.dataset.workdaysId = item.id;
                  }
                }catch(e){ console.error('apply saved id failed', e); }
              });
            }

            // --- NEW: collect adjustments rows and send to new endpoint to persist ---
            var adjustRows = [];
            rows.forEach(function(tr){
              var empId = parseInt(tr.dataset.empId);
              var workdaysId = parseInt(tr.dataset.workdaysId || 0) || null;
              var adjInput = tr.querySelector('.adjustmentsInput');
              var adjustments = null;
              try { adjustments = adjInput && adjInput.dataset.adjustments ? JSON.parse(adjInput.dataset.adjustments) : null; } catch(e){ adjustments = null; }
              if(adjustments){
                // base amount (จาก workdays total) และ new_total = base + net
                var baseAmt = parseFloat(tr.dataset.totalAmount || 0) || 0;
                var netAmt = parseFloat(adjustments.net || 0) || 0;
                adjustRows.push({
                  employee_id: empId,
                  workdays_id: workdaysId,
                  month: monthVal,
                  items: adjustments, // {adds:[], deducts:[], net}
                  net_amount: netAmt,
                  base_amount: baseAmt,
                  new_total: baseAmt + netAmt
                });
              }
            });

            if(adjustRows.length > 0){
              fetch('ajax/save_salary_adjustments.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ company_tax_id: '<?= addslashes($company['tax_id'] ?? '') ?>', rows: adjustRows })
              }).then(function(r){ return r.json(); }).then(function(ar){
                if(!(ar && ar.success)){
                  console.error('save adjustments failed', ar);
                  alert('การบันทึกรายการเพิ่ม/หักล้มเหลว (ดูคอนโซล)');
                } else {
                  console.log('adjustments saved', ar);
                  // update DOM totals using adjustRows (new_total) so UI matches DB
                  try {
                    adjustRows.forEach(function(adj){
                      try{
                        var tr = document.querySelector('#paySalaryTbody tr[data-emp-id="'+adj.employee_id+'"]');
                        if(tr){
                          tr.dataset.totalAmount = adj.new_total;
                          var cell = tr.querySelector('.rowTotal');
                          if(cell) cell.textContent = fmt2(adj.new_total);
                        }
                      }catch(e){ console.error('apply new_total failed', e); }
                    });
                    updateSummaryTotal();
                  } catch(e){ console.error('update UI after adjustments failed', e); }
                }
              }).catch(function(err){ console.error('save adjustments error', err); alert('เกิดข้อผิดพลาดขณะบันทึกรายการเพิ่ม/หัก'); });
            }

            alert('บันทึกข้อมูลการจ่ายเงินเดือนเรียบร้อย');
            document.getElementById('paySalaryModal').style.display = 'none';
          } else {
            console.error(res);
            alert('เกิดข้อผิดพลาดขณะบันทึกข้อมูล');
          }
        })
        .catch(function(err){
          console.error(err);
          alert('เกิดข้อผิดพลาดขณะบันทึก (ดูคอนโซล)');
        })
        .finally(function(){
          btn.disabled = false;
          btn.textContent = 'จ่ายเงินเดือน';
        });
    });
    </script>
    <script>
    // Add selected employee into paySalaryTbody
    // --- เพิ่มการใช้ค่า wage จาก salary_history และคำนวณเฉพาะจากวันทำงาน (ไม่รวม adjustments) ---
    var empSalary = <?= json_encode($emp_latest_salary, JSON_UNESCAPED_UNICODE) ?>;
    var empData = <?= json_encode($emp_js, JSON_UNESCAPED_UNICODE) ?>;

    function fmt2(v){ return (isNaN(v) ? 0 : Number(v)).toFixed(2); }

    function updatePayRowTotal(tr){
      if(!tr) return;
      var empId = tr.dataset.empId || '';
      var wage = 0;
      if(empId && empSalary[empId] !== undefined) wage = parseFloat(empSalary[empId]) || 0;
      var wd9 = parseFloat(tr.querySelector('.wd9')?.value || 0) || 0;
      var wd12 = parseFloat(tr.querySelector('.wd12')?.value || 0) || 0;
      // base amount from workdays (9hr + 12hr) * wage
      var base = (wd9 + wd12) * wage;

      // compute adjustments net: prefer dataset.adjustments.net (signed), otherwise fallback to numeric adjustmentsInput.value (treated as positive addition)
      var net = 0;
      try {
        var adjInput = tr.querySelector('.adjustmentsInput');
        if(adjInput){
          if(adjInput.dataset && adjInput.dataset.adjustments){
            var parsed = JSON.parse(adjInput.dataset.adjustments);
            if(parsed && parsed.net !== undefined && parsed.net !== null){
              net = parseFloat(parsed.net) || 0;
            } else {
              net = parseFloat(adjInput.value || 0) || 0;
            }
          } else {
            // no structured adjustments stored; use numeric input as positive addition
            net = parseFloat(adjInput.value || 0) || 0;
          }
        }
      } catch(e){
        net = parseFloat(tr.querySelector('.adjustmentsInput')?.value || 0) || 0;
      }

      var total = base + net;
      var totalCell = tr.querySelector('.rowTotal');
      if(totalCell) totalCell.textContent = fmt2(total);
      tr.dataset.totalAmount = total;
      updateSummaryTotal();
    }

    function updateSummaryTotal(){
      var tbody = document.getElementById('paySalaryTbody');
      var sum = 0;
      Array.from(tbody.querySelectorAll('tr')).forEach(function(tr){
        var t = parseFloat(tr.dataset.totalAmount || 0) || 0;
        sum += t;
      });
      document.getElementById('summary_total').textContent = fmt2(sum) + ' บาท';
    }

    // delegated listener for wd9/wd12 changes (unchanged but now uses updated updatePayRowTotal)
    document.getElementById('paySalaryTbody').addEventListener('input', function(e){
      var el = e.target;
      if(!el) return;
      var tr = el.closest('tr');
      if(!tr) return;
      if(el.classList.contains('wd9') || el.classList.contains('wd12')){
        updatePayRowTotal(tr);
      }
    });

    // --- NEW: update when user edits adjustments input directly ---
    document.getElementById('paySalaryTbody').addEventListener('input', function(e){
      var el = e.target;
      if(!el) return;
      var tr = el.closest('tr');
      if(!tr) return;
      if(el.classList && el.classList.contains('adjustmentsInput')){
        // If user edits numeric value directly, treat it as a positive net (unless dataset.adjustments exists)
        // remove any stored structured adjustments if user manually changed the numeric to avoid stale dataset mismatch
        if(el.dataset && el.dataset.adjustments && !el.dataset._locked_by_modal){
          // keep dataset if it was set by modal; if user manually edited numeric, we will preserve dataset but prefer numeric value now
        }
        // Recalculate totals (updatePayRowTotal handles dataset.adjustments fallback)
        updatePayRowTotal(tr);
      }
    });

    document.getElementById('addEmployeeBtn').addEventListener('click', function(){
      var sel = document.getElementById('addEmployeeSelect');
      if(!sel) return;
      var empId = sel.value;
      var empText = sel.options[sel.selectedIndex] ? sel.options[sel.selectedIndex].text : '';
      if(!empId){ alert('โปรดเลือกพนักงาน'); return; }
      var tbody = document.getElementById('paySalaryTbody');
      var exists = Array.from(tbody.querySelectorAll('tr')).some(function(tr){
        return tr.dataset.empId == empId;
      });
      if(exists){
        alert('พนักงานนี้ถูกเพิ่มแล้ว');
        return;
      }
      var emptyRow = tbody.querySelector('tr td div');
      if(emptyRow && emptyRow.textContent.includes('-- ยังไม่มีพนักงาน --')){
        tbody.innerHTML = '';
      }
      // ตรวจสอบสถานะประกันจาก empData (สร้างจาก PHP)
      var insuredText = 'ไม่เข้าร่วมประกันสังคม';
      try {
        var insuredFlag = (typeof empData !== 'undefined' && empData[empId] !== undefined) ? empData[empId].is_insured : null;
        if (insuredFlag == 1 || insuredFlag === 'Y') insuredText = 'เข้าร่วมประกันสังคม';
      } catch(e) { insuredText = 'ไม่เข้าร่วมประกันสังคม'; }

      var tr = document.createElement('tr');
      tr.dataset.empId = empId;
      tr.dataset.totalAmount = 0;
      var wage = empSalary[empId] !== undefined ? fmt2(empSalary[empId]) : fmt2(0);
      tr.innerHTML = '<td style="padding:10px;">'+empText+'<div style="font-size:0.85rem;color:#666;margin-top:4px;">อัตราเงินล่าสุด: '+wage+'</div></td>'+
                     '<td style="padding:8px; text-align:center;"><input class="wd9" type="number" min="0" value="0" style="width:64px; padding:6px; border-radius:6px; border:1px solid #e6e6e6;"></td>'+
                     '<td style="padding:8px; text-align:center;"><input class="wd12" type="number" min="0" value="0" style="width:64px; padding:6px; border-radius:6px; border:1px solid #e6e6e6;"></td>'+
                     '<td style="padding:8px; text-align:center;"><div style="color:#999;">'+insuredText+'</div></td>'+
                     '<td style="padding:8px; text-align:center; display:flex; justify-content:center; gap:6px; align-items:center;">'+
                       '<input type="number" min="0" value="0.00" step="0.01" class="adjustmentsInput" style="width:90px; padding:6px; border-radius:6px; border:1px solid #e6e6e6;">'+
                       '<button class="btn btn-sm openAdjustments" title="จัดการรายการเพิ่ม/หัก" style="border-radius:6px; padding:6px 8px; background:#fff; border:1px solid #eef0f2;"><i class="bi bi-pencil-square" style="color:#0d6efd;"></i></button>'+
                     '</td>'+
                     '<td style="padding:8px; text-align:center;"><div class="rowTotal" style="font-weight:700;">0.00</div></td>'+
                     '<td style="padding:8px; text-align:center;"><button class="removePayRow btn btn-sm" title="ลบ" style="border-radius:6px; background:#fff; border:1px solid #f0f0f0; padding:6px 8px;"><i class="bi bi-trash" style="color:#d9534f;"></i></button></td>';
      tbody.appendChild(tr);

      // wire remove button
      // (do not attach openDeleteNoteModal here — deletion handled by delegated listener above)
      // var rem = tr.querySelector('.removePayRow');
      // if(rem) rem.addEventListener('click', function(){
      //   openDeleteNoteModal(tr, function(){ tr.remove(); updateSummaryTotal(); });
      // });
      // wire adjustments button (unchanged behavior)
      var adjBtn = tr.querySelector('.openAdjustments');
      if(adjBtn) adjBtn.addEventListener('click', function(){ openAdjustmentsModal(adjBtn); });

      // initial compute (wage may be zero if not found)
      updatePayRowTotal(tr);
    });
    </script>
    <script>
    // Delete note modal helper
    var deleteNoteModal = document.getElementById('deleteNoteModal');
    var deleteNoteInternal = document.getElementById('deleteNoteInternal');
    var deleteNoteSlip = document.getElementById('deleteNoteSlip');
    var deleteCallback = null;

    function openDeleteNoteModal(targetRow, cb){
      deleteCallback = cb || null;
      // clear inputs
      deleteNoteInternal.value = '';
      deleteNoteSlip.value = '';
      // try to prefill slip note with row info (employee / amount) when available
      try{
        if(targetRow){
          var sel = targetRow.querySelector('select');
          var empText = '';
          if(sel && sel.options && sel.selectedIndex>=0){ empText = sel.options[sel.selectedIndex].text.trim(); }
          else {
            var firstTd = targetRow.querySelector('td');
            if(firstTd) empText = firstTd.textContent.trim();
          }
          var amtInput = targetRow.querySelector('input[type=number]');
          var amt = amtInput ? (amtInput.value || '').toString().trim() : '';
          var parts = [];
          if(empText) parts.push(empText);
          if(amt) parts.push(amt + ' บาท');
          if(parts.length) deleteNoteSlip.value = parts.join(' — ');
        }
      }catch(e){ console.error('prefill delete note failed', e); }
      deleteNoteModal.dataset.targetRowIndex = targetRow ? Array.prototype.indexOf.call(targetRow.parentNode.children, targetRow) : -1;
      deleteNoteModal.style.display = 'block';
      // focus first input
      deleteNoteInternal.focus();
    }

    document.getElementById('closeDeleteNote').addEventListener('click', function(){ deleteNoteModal.style.display = 'none'; });
    document.getElementById('cancelDeleteNote').addEventListener('click', function(){ deleteNoteModal.style.display = 'none'; });
    document.getElementById('confirmDeleteNote').addEventListener('click', function(){
      var notes = { internal: deleteNoteInternal.value.trim(), slip: deleteNoteSlip.value.trim() };
      // call callback then hide
      if(typeof deleteCallback === 'function'){
        try{ deleteCallback(notes); } catch(e){ console.error(e); }
      }
      deleteNoteModal.style.display = 'none';
    });
    </script>

    <!-- Adjustments modal (รายการเพิ่ม/หัก) - tabbed Add / Deduct -->
    <div id="adjustmentsModal" class="modal" style="display:none; position:fixed; z-index:1070; left:0; top:0; width:100vw; height:100vh; overflow:auto; background:rgba(0,0,0,0.45);">
      <div style="background:#fff; margin:36px auto; padding:18px; border-radius:8px; width:94%; max-width:760px; position:relative;">
        <button id="closeAdjustments" class="btn" style="position:absolute; right:18px; top:12px; font-size:22px; background:transparent; border:none;">&times;</button>
        <h5 style="margin-top:0;">จัดการรายการเพิ่ม/หัก <span id="adjModalTargetLabel" style="font-weight:400; color:#666; font-size:0.9rem; margin-left:8px;"></span></h5>

        <!-- Tabs -->
        <div style="display:flex; gap:8px; margin-bottom:12px;">
          <button id="tabAdd" class="btn" style="flex:1; background:#e8f6ef; color:#0b845e; border-radius:8px; font-weight:600;">รายการเพิ่ม</button>
          <button id="tabDeduct" class="btn" style="flex:1; background:#fff0f0; color:#a60000; border-radius:8px; font-weight:600;">รายการหัก</button>
        </div>

        <!-- Add panel -->
        <div id="addPanel" style="display:block; margin-bottom:8px; background:#f6fffb; padding:12px; border-radius:8px; border:1px solid #eaf7ef;">
  <div style="display:flex; gap:8px; align-items:center; margin-bottom:10px;">
    <select id="adjAddType" style="padding:8px; border-radius:6px; border:1px solid #e6e6e6; width:220px;">
      <option value="other">เลือกประเภทรายการ</option>
      <?php foreach ($salary_allowances as $a): ?>
        <option value="<?= intval($a['id']) ?>" data-kind="allowance"><?= htmlspecialchars($a['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <input id="adjAddDesc" placeholder="รายการเพิ่ม (เช่น โบนัส/OT)" style="flex:1; padding:8px; border-radius:6px; border:1px solid #e6e6e6;">
    <input id="adjAddAmount" type="number" min="0" step="0.01" value="0.00" style="width:140px; padding:8px; border-radius:6px; border:1px solid #e6e6e6; text-align:right;">
    <button id="addAdjItemAdd" class="btn btn-success" style="border-radius:8px; padding:8px 12px;">+ เพิ่ม</button>
  </div>
          <div style="color:#149b2a; font-weight:600; margin-bottom:6px;">รายการเพิ่มปัจจุบัน</div>
          <div style="max-height:200px; overflow:auto;">
            <table style="width:100%; border-collapse:collapse;">
              <thead><tr style="background:#f8fff8;"><th style="padding:8px; width:50px;">#</th><th style="padding:8px;">รายการ</th><th style="padding:8px; text-align:right; width:140px;">จำนวน</th><th style="padding:8px; text-align:center; width:80px;">จัดการ</th></tr></thead>
              <tbody id="adjAddList"></tbody>
            </table>
          </div>
        </div>

        <!-- Deduct panel -->
        <div id="deductPanel" style="display:none; margin-bottom:8px; background:#fff6f6; padding:12px; border-radius:8px; border:1px solid #fdecea;">
          <div style="display:flex; gap:8px; align-items:center; margin-bottom:10px;">
                <select id="adjDedType" style="padding:8px; border-radius:6px; border:1px solid #e6e6e6; width:220px;">
            <option value="other">เลือกประเภทรายการ</option>
            <?php foreach ($salary_deductions as $d): ?>
              <option value="<?= intval($d['id']) ?>" data-kind="deduction"><?= htmlspecialchars($d['name']) ?></option>
            <?php endforeach; ?>
          </select>
            <input id="adjDedDesc" placeholder="รายการหัก (เช่น ยืมเงิน)" style="flex:1; padding:8px; border-radius:6px; border:1px solid #e6e6e6;">
            <input id="adjDedAmount" type="number" min="0" step="0.01" value="0.00" style="width:140px; padding:8px; border-radius:6px; border:1px solid #e6e6e6; text-align:right;">
            <button id="addAdjItemDed" class="btn" style="background:#e05b5b; color:#fff; border-radius:8px; padding:8px 12px;">+ เพิ่ม</button>
          </div>
          <div style="color:#c53030; font-weight:600; margin-bottom:6px;">รายการหักปัจจุบัน</div>
          <div style="max-height:200px; overflow:auto;">
            <table style="width:100%; border-collapse:collapse;">
              <thead><tr style="background:#fff7f7;"><th style="padding:8px; width:50px;">#</th><th style="padding:8px;">รายการ</th><th style="padding:8px; text-align:right; width:140px;">จำนวน</th><th style="padding:8px; text-align:center; width:80px;">จัดการ</th></tr></thead>
              <tbody id="adjDedList"></tbody>
            </table>
          </div>
        </div>

        <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:12px;">
          <button id="cancelAdj" class="btn btn-light">ยกเลิก</button>
          <button id="saveAdj" class="btn btn-success">บันทึก</button>
        </div>
      </div>
    </div>

    <script>
    // Adjustments modal - tabbed Add/Deduct implementation
    var currentAdjustmentsTarget = null; // the input element to write net amount into
    var currentAdjustmentsEmpId = null; // employee id for which adjustments apply (if any)
    var adjModal = document.getElementById('adjustmentsModal');
    var adjAddList = document.getElementById('adjAddList');
    var adjDedList = document.getElementById('adjDedList');
    // helper to parse option text (lowercase) for deduction type keywords
    function isSocialSecurityOption(text){ return text && text.toLowerCase().indexOf('ประกัน') !== -1; }
    function isWithholdingOption(text){ return text && text.toLowerCase().indexOf('ณ') !== -1 && text.toLowerCase().indexOf('จ่าย') !== -1; }

    // keep a single change listener for adjDedType to auto-calc percentage when selected
    (function attachDeductTypeListener(){
      var el = document.getElementById('adjDedType');
      if(!el) return;
      el.addEventListener('change', function(){
        var selText = (el.options[el.selectedIndex] && el.options[el.selectedIndex].text) ? el.options[el.selectedIndex].text : '';
        var amtEl = document.getElementById('adjDedAmount');
        if(!amtEl) return;
        // determine base wage (use empSalary if emp id known)
        var wage = 0;
        if(currentAdjustmentsEmpId && typeof empSalary !== 'undefined' && empSalary[currentAdjustmentsEmpId] !== undefined){
          wage = parseFloat(empSalary[currentAdjustmentsEmpId]) || 0;
        }
        if(isSocialSecurityOption(selText)){
          // 5% ของอัตราเงินล่าสุด
          amtEl.value = (wage * 0.05).toFixed(2);
        } else if(isWithholdingOption(selText)){
          // 3% ของอัตราเงินล่าสุด
          amtEl.value = (wage * 0.03).toFixed(2);
        }
      });
    })();

    function openAdjustmentsModal(btn){
      var tr = btn.closest('tr'); if(!tr) return;
      currentAdjustmentsTarget = tr.querySelector('.adjustmentsInput');
      // capture current employee id from the row (if present) so we can compute percentages
      currentAdjustmentsEmpId = tr.dataset.empId ? tr.dataset.empId : null;
      // set label to help debug (optional)
      var nameCell = tr.querySelector('td');
      document.getElementById('adjModalTargetLabel').textContent = nameCell ? ('สำหรับ: ' + nameCell.textContent.trim()) : '';
      // clear lists
      adjAddList.innerHTML = '';
      adjDedList.innerHTML = '';
      // default open Add tab
      showAddTab();
      adjModal.style.display = 'block';
    }

    // wire existing pencil buttons (including dynamically created rows should re-run this if needed)
    document.querySelectorAll('.openAdjustments').forEach(function(btn){ btn.addEventListener('click', function(e){ openAdjustmentsModal(btn); }); });

    document.getElementById('closeAdjustments').addEventListener('click', function(){ adjModal.style.display = 'none'; });
    document.getElementById('cancelAdj').addEventListener('click', function(){ adjModal.style.display = 'none'; });

    // Tab functions
    function showAddTab(){
      document.getElementById('addPanel').style.display = 'block';
      document.getElementById('deductPanel').style.display = 'none';
      document.getElementById('tabAdd').style.background = '#e8f6ef';
      document.getElementById('tabDeduct').style.background = '#fff';
    }
    function showDeductTab(){
      document.getElementById('addPanel').style.display = 'none';
      document.getElementById('deductPanel').style.display = 'block';
      document.getElementById('tabAdd').style.background = '#fff';
      document.getElementById('tabDeduct').style.background = '#fff6f6';
    }
    document.getElementById('tabAdd').addEventListener('click', showAddTab);
    document.getElementById('tabDeduct').addEventListener('click', showDeductTab);

    // Add item to Add list
    document.getElementById('addAdjItemAdd').addEventListener('click', function(){
      var type = document.getElementById('adjAddType').value;
      var desc = document.getElementById('adjAddDesc').value.trim() || '-';
      var amt = parseFloat(document.getElementById('adjAddAmount').value) || 0;
      var idx = adjAddList.children.length + 1;
      var tr = document.createElement('tr');
      tr.dataset.type = type;
      tr.dataset.desc = desc;
      tr.dataset.amount = amt.toFixed(2);
      tr.innerHTML = '<td style="padding:8px;">'+idx+'</td><td style="padding:8px;">'+desc+' <span style="color:#666; font-size:0.85rem;">('+type+')</span></td><td style="padding:8px; text-align:right;">'+amt.toFixed(2)+'</td><td style="padding:8px; text-align:center;"><button class="btn btn-sm removeAdjAdd" style="border-radius:6px; background:#fff; border:1px solid #e6e6e6; padding:4px 8px;"><i class="bi bi-trash" style="color:#d9534f;"></i></button></td>';
      adjAddList.appendChild(tr);
      document.getElementById('adjAddDesc').value = '';
      document.getElementById('adjAddAmount').value = '0.00';
    });

    // Add item to Deduct list
    document.getElementById('addAdjItemDed').addEventListener('click', function(){
      var type = document.getElementById('adjDedType').value;
      var desc = document.getElementById('adjDedDesc').value.trim() || '-';
      var amt = parseFloat(document.getElementById('adjDedAmount').value) || 0;
      var idx = adjDedList.children.length + 1;
      var tr = document.createElement('tr');
      tr.dataset.type = type;
      tr.dataset.desc = desc;
      tr.dataset.amount = amt.toFixed(2);
      tr.innerHTML = '<td style="padding:8px;">'+idx+'</td><td style="padding:8px;">'+desc+' <span style="color:#666; font-size:0.85rem;">('+type+')</span></td><td style="padding:8px; text-align:right;">'+amt.toFixed(2)+'</td><td style="padding:8px; text-align:center;"><button class="btn btn-sm removeAdjDed" style="border-radius:6px; background:#fff; border:1px solid #e6e6e6; padding:4px 8px;"><i class="bi bi-trash" style="color:#d9534f;"></i></button></td>';
      adjDedList.appendChild(tr);
      document.getElementById('adjDedDesc').value = '';
      document.getElementById('adjDedAmount').value = '0.00';
    });

    // Remove handlers (event delegation) -> prompt for notes before removal
    adjAddList.addEventListener('click', function(e){
      if(e.target && (e.target.classList.contains('removeAdjAdd') || e.target.closest('.removeAdjAdd'))){
        var tr = e.target.closest('tr'); if(!tr) return;
        // if delete-note modal exists, use it; otherwise use simple confirm and remove
        if (document.getElementById('deleteNoteModal')) {
          openDeleteNoteModal(tr, function(notes){
            console.log('Delete add-item notes:', notes);
            tr.remove(); renumberLists();
          });
        } else {
          if (confirm('ยืนยันการลบรายการนี้?')) {
            tr.remove(); renumberLists();
          }
        }
      }
    });
    adjDedList.addEventListener('click', function(e){
      if(e.target && (e.target.classList.contains('removeAdjDed') || e.target.closest('.removeAdjDed'))){
        var tr = e.target.closest('tr'); if(!tr) return;
        // if delete-note modal exists, use it; otherwise use simple confirm and remove
        if (document.getElementById('deleteNoteModal')) {
          openDeleteNoteModal(tr, function(notes){
            console.log('Delete deduct-item notes:', notes);
            tr.remove(); renumberLists();
          });
        } else {
          if (confirm('ยืนยันการลบรายการนี้?')) {
            tr.remove(); renumberLists();
          }
        }
      }
    });

    function renumberLists(){
      Array.from(adjAddList.children).forEach(function(r,i){ r.children[0].textContent = i+1; });
      Array.from(adjDedList.children).forEach(function(r,i){ r.children[0].textContent = i+1; });
    }

    // Save: sum adds and deducts and write net (adds - deducts) to target input
    document.getElementById('saveAdj').addEventListener('click', function(){
      var sumAdd = 0, sumDed = 0;
      var adds = [], deds = [];
      Array.from(adjAddList.children).forEach(function(r){
        var amt = parseFloat(r.dataset.amount || r.children[2].textContent.replace(/,/g,'')) || 0;
        sumAdd += amt;
        adds.push({ type: r.dataset.type || '', desc: r.dataset.desc || (r.children[1] ? r.children[1].textContent.trim() : ''), amount: amt });
      });
      Array.from(adjDedList.children).forEach(function(r){
        var amt = parseFloat(r.dataset.amount || r.children[2].textContent.replace(/,/g,'')) || 0;
        sumDed += amt;
        deds.push({ type: r.dataset.type || '', desc: r.dataset.desc || (r.children[1] ? r.children[1].textContent.trim() : ''), amount: amt });
      });
      var net = sumAdd - sumDed; // net can be negative (more deductions)
      if(currentAdjustmentsTarget) {
        // store net numeric into input value (abs to keep UI same)
        currentAdjustmentsTarget.value = Math.abs(net).toFixed(2);
        // store full items as JSON in dataset for later saving to DB
        try {
          currentAdjustmentsTarget.dataset.adjustments = JSON.stringify({ adds: adds, deducts: deds, net: net });
          // mark that this dataset was set by modal (optional flag)
          currentAdjustmentsTarget.dataset._locked_by_modal = '1';
        } catch(e){ console.error('set dataset adjust fail', e); }
        // --- IMMEDIATE UI UPDATE: apply adjustments to the row total right away ---
        try {
          var parentRow = currentAdjustmentsTarget.closest('tr');
          if(parentRow){
            // update using centralized function (will read dataset.adjustments)
            updatePayRowTotal(parentRow);
          }
        } catch(e){ console.error('apply adjustments to row failed', e); }
      }
      adjModal.style.display = 'none';
    });
  </script>
  <script>
    // Modal behaviors (advance)
    document.getElementById('closeAdvanceModal').addEventListener('click', function(){ document.getElementById('advanceModal').style.display = 'none'; });
    document.getElementById('cancelAdvance2').addEventListener('click', function(){ document.getElementById('advanceModal').style.display = 'none'; });

    // set displayed month when date changes
    var advanceDateEl = document.getElementById('advance_date');
    function updateAdvanceSelectedMonth(){
      var v = advanceDateEl.value; if(!v) { document.getElementById('advance_selected_month').textContent = '-'; return; }
      var parts = v.split('-'); if(parts.length<2) return; document.getElementById('advance_selected_month').textContent = parts[0]+'-'+parts[1];
    }
    advanceDateEl.addEventListener('change', updateAdvanceSelectedMonth);
    updateAdvanceSelectedMonth();

    // <<< REMOVED duplicated addAdvanceRow2 listener (the block that created tr.innerHTML with template string).
    // The correct addAdvanceRow2 listener earlier (which uses buildEmployeeOptionsHtml()) remains and will handle adding one row per click. >>>

    // edit / delete advance actions
    document.getElementById('advanceTbody').addEventListener('click', function(e){
      // edit advance
      if(e.target && (e.target.classList.contains('editAdvanceBtn') || e.target.closest('.editAdvanceBtn'))){
        var tr = e.target.closest('tr'); if(!tr) return; openEditAdvanceModal(tr); return;
      }
      // delete advance
      if(e.target && (e.target.classList.contains('removeAdvanceBtn') || e.target.closest('.removeAdvanceBtn'))){
        var tr = e.target.closest('tr'); if(!tr) return;
        var advId = tr.dataset.advanceId ? parseInt(tr.dataset.advanceId) : 0;
        if(advId > 0){
          if(!confirm('ยืนยันการลบรายการนี้?')) return;
          fetch('ajax/delete_advance.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: advId })
          })
          .then(r => r.json())
          .then(res => {
            if(res && res.success){
              tr.remove();
            }else{
              alert('เกิดข้อผิดพลาดขณะลบ');
            }
          })
          .catch(() => alert('เกิดข้อผิดพลาดขณะลบ'));
        }else{
          tr.remove();
        }
      }
    });

    // Edit Advance modal and logic
    var currentAdvanceEditRow = null;
    // add modal markup (insert near other modals)
    var editAdvanceModalHtml = `
<div id="editAdvanceModal" class="modal" style="display:none; position:fixed; z-index:1090; left:0; top:0; width:100vw; height:100vh; overflow:auto; background:rgba(0,0,0,0.45);">
  <div style="background:#fff; margin:36px auto; padding:18px; border-radius:8px; width:92%; max-width:520px; position:relative;">
    <button id="closeEditAdvance" class="btn" style="position:absolute; right:12px; top:8px; font-size:20px; background:transparent; border:none;">&times;</button>
    <h5 style="margin-top:0;">แก้ไขหมายเหตุ</h5>
    <div style="margin-top:8px;">
      <label style="font-weight:600; font-size:0.95rem;">แนบสลิปรูปภาพ</label>
      <input id="editSlipImage" type="file" accept="image/*" style="width:100%; padding:8px; border-radius:6px; border:1px solid #e6e6e6; margin-bottom:8px;">
      <div id="editSlipPreview" style="margin-bottom:8px;"></div>
      <label style="font-weight:600; font-size:0.95rem;">หมายเหตุสลิป</label>
      <input id="editNoteSlip" placeholder="ระบุหมายเหตุที่จะเเสดงในสลิป" style="width:100%; padding:8px; border-radius:6px; border:1px solid #e6e6e6;">
    </div>
    <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:12px;">
      <button id="cancelEditAdvance" class="btn btn-light">ยกเลิก</button>
      <button id="saveEditAdvance" class="btn btn-success">บันทึก</button>
    </div>
  </div>
</div>`;
    // append modal HTML to body
    document.body.insertAdjacentHTML('beforeend', editAdvanceModalHtml);

    function openEditAdvanceModal(tr){
      currentAdvanceEditRow = tr;
      // read existing slip image from data attribute (if any)
      var slipImg = tr.dataset.slipImage || '';
      var slip = tr.dataset.noteSlip || '';
      var noteInput = tr.querySelector('input[type=text]');
      if(!slip && noteInput) slip = noteInput.value || '';
      document.getElementById('editSlipImage').value = '';
      document.getElementById('editNoteSlip').value = slip;
      // preview image if exists
      var preview = document.getElementById('editSlipPreview');
      if (slipImg) {
        preview.innerHTML = '<img src="' + slipImg + '" style="max-width:100%;max-height:120px;border-radius:6px;">';
      } else {
        preview.innerHTML = '';
      }
      document.getElementById('editAdvanceModal').style.display = 'block';
    }

    document.getElementById('editSlipImage').addEventListener('change', function(e){
      var file = e.target.files[0];
      var preview = document.getElementById('editSlipPreview');
      if(file){
        var reader = new FileReader();
        reader.onload = function(ev){
          preview.innerHTML = '<img src="'+ev.target.result+'" style="max-width:100%;max-height:120px;border-radius:6px;">';
        };
        reader.readAsDataURL(file);
      }else{
        preview.innerHTML = '';
      }
    });

    document.getElementById('closeEditAdvance').addEventListener('click', function(){ document.getElementById('editAdvanceModal').style.display = 'none'; });
    document.getElementById('cancelEditAdvance').addEventListener('click', function(){ document.getElementById('editAdvanceModal').style.display = 'none'; });

    document.getElementById('saveEditAdvance').addEventListener('click', function(){
      if(!currentAdvanceEditRow) return;
      var slip = document.getElementById('editNoteSlip').value.trim();
      // handle slip image
      var fileInput = document.getElementById('editSlipImage');
      var file = fileInput.files[0];
      if(file){
        var reader = new FileReader();
        reader.onload = function(ev){
          // save base64 image data to row dataset (for demo, in real use upload to server)
          currentAdvanceEditRow.dataset.slipImage = ev.target.result;
          currentAdvanceEditRow.dataset.noteSlip = slip;
          var noteInput = currentAdvanceEditRow.querySelector('input[type=text]');
          if(noteInput) noteInput.value = slip;
          document.getElementById('editAdvanceModal').style.display = 'none';
          currentAdvanceEditRow = null;
        };
        reader.readAsDataURL(file);
      }else{
        // no new image, just update slip note
        currentAdvanceEditRow.dataset.noteSlip = slip;
        var noteInput = currentAdvanceEditRow.querySelector('input[type=text]');
        if(noteInput) noteInput.value = slip;
        document.getElementById('editAdvanceModal').style.display = 'none';
        currentAdvanceEditRow = null;
      }
    });
    // Save all advance rows (persist to DB)
    document.getElementById('saveAdvanceDemo').addEventListener('click', function(){
      var tbody = document.getElementById('advanceTbody');
      var rows = Array.from(tbody.querySelectorAll('tr'));
      if(rows.length === 0){ alert('ไม่มีรายการที่จะบันทึก'); return; }
      var advanceDate = document.getElementById('advance_date').value;

      var promises = rows.map(function(tr){
        var sel = tr.querySelector('select');
        var empId = sel ? (sel.value ? parseInt(sel.value) : 0) : 0;
        var amtInput = tr.querySelector('input[type=number]');
        var amount = amtInput ? parseFloat(amtInput.value) || 0 : 0;
        var slipInput = tr.querySelector('input[type=text]');
        var note_slip = slipInput ? (slipInput.value || '') : '';
        var advId = tr.dataset.advanceId ? parseInt(tr.dataset.advanceId) : 0;
        var slip_image = tr.dataset.slipImage || '';

        // ใช้ advance_date แยกแต่ละแถว (ถ้ามีใน dataset ให้ใช้ของแถวนั้น)
        var rowAdvanceDate = tr.dataset.advanceDate || advanceDate;

        // basic validation
        if(empId <= 0 || amount <= 0){
          return Promise.resolve({ success: false, error: 'invalid', row: tr });
        }
        var payload = {
          id: advId,
          employee_id: empId,
          company_tax_id: '<?= addslashes($company['tax_id'] ?? '') ?>',
          advance_date: rowAdvanceDate,
          amount: amount,
          slip_image: slip_image,
          note_slip: note_slip
        };
        return fetch('ajax/save_advance.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        }).then(function(r){ return r.json(); }).then(function(res){
          if(res && res.success && res.id){ tr.dataset.advanceId = res.id; }
          // อัปเดต dataset.advanceDate ให้ตรงกับที่บันทึก
          tr.dataset.advanceDate = rowAdvanceDate;
          return res;
        }).catch(function(err){ console.error('save error', err); return { success:false, error:err }; });
      });

      Promise.all(promises).then(function(results){
        var anyFail = results.some(function(r){ return !(r && r.success); });
        if(anyFail){ alert('บันทึกเสร็จสิ้น แต่มีบางรายการไม่สำเร็จ (ดูคอนโซล)'); }
        else { alert('บันทึกข้อมูลเรียบร้อย'); }
        document.getElementById('advanceModal').style.display = 'none';
      }).catch(function(err){ console.error(err); alert('เกิดข้อผิดพลาดเมื่อบันทึก'); });
    });
  </script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>