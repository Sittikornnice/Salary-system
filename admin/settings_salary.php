<?php
// ...existing code...
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$conn = new mysqli('localhost', 'root', '', 'salary_system');
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

// ดึงข้อมูลบริษัทที่เลือก
$tax_id = $_SESSION['selected_company_tax_id'] ?? '';
$company = [];
if ($tax_id) {
  $stmt = $conn->prepare("SELECT * FROM companies WHERE tax_id=? LIMIT 1");
  $stmt->bind_param("s", $tax_id);
  $stmt->execute();
  $result = $stmt->get_result();
  $company = $result->fetch_assoc() ?: [];
  $stmt->close();
}

// ดึงรายชื่อบริษัททั้งหมด
$companies = [];
$result = $conn->query("SELECT name, tax_id FROM companies");
while ($row = $result->fetch_assoc()) {
  $companies[] = $row;
}

// --- เพิ่มประเภทเงินเดือน (allowance / deduction) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_salary_type']) && $tax_id) {
  $name = trim($_POST['type_name'] ?? '');
  $stype = ($_POST['type_kind'] ?? '') === 'deduction' ? 'deduction' : 'allowance';
  if ($name !== '') {
    $stmt = $conn->prepare("INSERT INTO salary_types (company_tax_id, name, `type`) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $tax_id, $name, $stype);
    $stmt->execute();
    $stmt->close();
  }
  echo "<script>window.location='dashboard.php?page=settings_salary.php';</script>";
  exit;
}

// --- ลบประเภทเงินเดือน ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_salary_type_id'])) {
  $del_id = intval($_POST['delete_salary_type_id']);
  $stmt = $conn->prepare("DELETE FROM salary_types WHERE id = ? AND company_tax_id = ?");
  $stmt->bind_param("is", $del_id, $tax_id);
  $stmt->execute();
  $stmt->close();
  echo "<script>window.location='dashboard.php?page=settings_salary.php';</script>";
  exit;
}

// ดึง salary types ของบริษัท (แยก allowance / deduction)
$allowances = [];
$deductions = [];
if ($tax_id) {
  $stmt = $conn->prepare("SELECT id, name, `type` FROM salary_types WHERE company_tax_id = ? ORDER BY created_at ASC, id ASC");
  $stmt->bind_param("s", $tax_id);
  $stmt->execute();
  $res = $stmt->get_result();
  while ($r = $res->fetch_assoc()) {
    if ($r['type'] === 'deduction') $deductions[] = $r;
    else $allowances[] = $r;
  }
  $stmt->close();
}
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
               href="dashboard.php?page=settings_salary.php&change_company=<?= htmlspecialchars($c['tax_id']) ?>"
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

        <!-- Panel Title -->
<div class="panel-title-row">
  <div class="panel-title"><i class="bi bi-gear-fill me-2"></i>ตั้งค่าเงินเดือน</div>
</div>

<!-- เพิ่มสไตล์ให้กรอบของรายการเงินเดือนเท่ากับกรอบ company-header -->
<style>
  /* เลียนแบบ shadow-sm ของ Bootstrap, ขอบโค้ง และ padding ให้เท่ากับ company-header */
  .salary-types-box {
    background: #fff;
    border-radius: 12px;
    padding: 18px;
    box-shadow: 0 .125rem .25rem rgba(0,0,0,.075); /* เทียบกับ shadow-sm */
    margin-bottom: 1rem;
  }

  /* ทำให้การ์ดภายในไม่ยืดจนเกิน */
  .salary-types-box .card { box-shadow: none; border: 0; }
  .salary-types-box .card .card-body { padding: 0; }
  .salary-types-box .card .card-body > .d-flex:first-child { padding-bottom: 12px; }
  /* ช่องว่างระหว่างคอลัมน์ เล็กน้อย */
  @media (min-width: 768px) {
    .salary-types-row > .col-md-6 { padding-left: 10px; padding-right: 10px; }
  }
</style>

<!-- แสดงประเภทเพิ่ม (allowances) และ ประเภทหัก (deductions) -->
  <div class="row g-1 align-items-start salary-types-row">
    <div class="col-md-6 d-flex">
      <div class="card shadow-sm flex-fill h-100">
        <div class="card-body d-flex flex-column">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="card-title mb-0">ประเภทรายการเพิ่ม</h5>
            <button class="btn btn-success btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#addAllowanceForm" aria-expanded="false">
              เพิ่มประเภท
            </button>
          </div>

          <div class="collapse mb-3" id="addAllowanceForm">
            <form method="post" class="d-flex gap-2">
              <input type="hidden" name="add_salary_type" value="1">
              <input type="hidden" name="type_kind" value="allowance">
              <input type="text" name="type_name" class="form-control" placeholder="ชื่อประเภท เช่น โบนัส" required>
              <button type="submit" class="btn btn-primary">บันทึก</button>
            </form>
          </div>

          <?php if (count($allowances) > 0): ?>
            <ul class="list-group flex-fill">
              <?php foreach ($allowances as $a): ?>
                <li class="list-group-item d-flex align-items-center justify-content-between">
                  <span><?= htmlspecialchars($a['name']) ?></span>
                  <form method="post" style="margin:0;">
                    <input type="hidden" name="delete_salary_type_id" value="<?= intval($a['id']) ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('ลบประเภทนี้?')">
                      <i class="bi bi-x-lg"></i>
                    </button>
                  </form>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <div class="text-muted">ยังไม่มีประเภทเพิ่ม</div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="col-md-6 d-flex">
      <div class="card shadow-sm flex-fill h-100">
        <div class="card-body d-flex flex-column">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="card-title mb-0">ประเภทรายการหัก</h5>
            <button class="btn btn-success btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#addDeductionForm" aria-expanded="false">
              เพิ่มประเภท
            </button>
          </div>

          <div class="collapse mb-3" id="addDeductionForm">
            <form method="post" class="d-flex gap-2">
              <input type="hidden" name="add_salary_type" value="1">
              <input type="hidden" name="type_kind" value="deduction">
              <input type="text" name="type_name" class="form-control" placeholder="ชื่อประเภท เช่น หักประกัน" required>
              <button type="submit" class="btn btn-primary">บันทึก</button>
            </form>
          </div>

          <?php if (count($deductions) > 0): ?>
            <ul class="list-group flex-fill">
              <?php foreach ($deductions as $d): ?>
                <li class="list-group-item d-flex align-items-center justify-content-between">
                  <span><?= htmlspecialchars($d['name']) ?></span>
                  <form method="post" style="margin:0;">
                    <input type="hidden" name="delete_salary_type_id" value="<?= intval($d['id']) ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('ลบประเภทนี้?')">
                      <i class="bi bi-x-lg"></i>
                    </button>
                  </form>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <div class="text-muted">ยังไม่มีประเภทหัก</div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>


<?php
$conn->close();
?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
