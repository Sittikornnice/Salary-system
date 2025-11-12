<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// เปลี่ยนบริษัท (redirect กลับมาหน้าเดิม)
if (isset($_GET['change_company'])) {
  $_SESSION['selected_company_tax_id'] = $_GET['change_company'];
  header("Location: company_settings.php");
  exit;
}

$conn = new mysqli('localhost', 'root', '', 'salary_system');
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }
$tax_id = $_SESSION['selected_company_tax_id'] ?? '';
$company = [];
if ($tax_id) {
  $result = $conn->query("SELECT * FROM companies WHERE tax_id='$tax_id' LIMIT 1");
  $company = $result->fetch_assoc();
}
// ดึงรายชื่อบริษัททั้งหมด
$companies = [];
$result = $conn->query("SELECT name, tax_id FROM companies");
while ($row = $result->fetch_assoc()) {
  $companies[] = $row;
}
// ดึงตำแหน่งงานทั้งหมด
$positions = [];
$result = $conn->query("SELECT * FROM positions ORDER BY id ASC");
while ($row = $result->fetch_assoc()) {
  $positions[] = $row;
}
// --- เพิ่มตำแหน่งงาน ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_position'])) {
  $new_position = trim($_POST['new_position']);
  if ($new_position !== '') {
    $conn = new mysqli('localhost', 'root', '', 'salary_system');
    $stmt = $conn->prepare("INSERT INTO positions (name) VALUES (?)");
    $stmt->bind_param("s", $new_position);
    $stmt->execute();
    $stmt->close();
    $conn->close();
    echo "<script>window.location='dashboard.php?page=company_settings.php';</script>";
    exit;
  }
}

// --- ลบตำแหน่งงาน ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_position_id'])) {
  $delete_id = intval($_POST['delete_position_id']);
  $conn = new mysqli('localhost', 'root', '', 'salary_system');
  $stmt = $conn->prepare("DELETE FROM positions WHERE id=?");
  $stmt->bind_param("i", $delete_id);
  $stmt->execute();
  $stmt->close();
  $conn->close();
  echo "<script>window.location='dashboard.php?page=company_settings.php';</script>";
  exit;
}
$conn->close();
?>
<?php if (isset($_SESSION['success_message'])): ?>
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    <?= $_SESSION['success_message']; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  <?php unset($_SESSION['success_message']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['error_message'])): ?>
  <div class="alert alert-danger alert-dismissible fade show" role="alert">
    <?= $_SESSION['error_message']; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  <?php unset($_SESSION['error_message']); ?>
<?php endif; ?>
  <!-- company-header เหมือน dashboard.php -->
<div class="company-header d-flex justify-content-between align-items-center flex-wrap shadow-sm">
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
               href="dashboard.php?page=company_settings.php&change_company=<?= htmlspecialchars($c['tax_id']) ?>"
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
  <div class="panel-title"><i class="bi bi-gear-fill me-2"></i>ตั้งค่าบริษัท</div>
  <button class="btn btn-add d-flex align-items-center" style="font-weight:500;" data-bs-toggle="modal" data-bs-target="#editCompanyModal">
    <i class="bi bi-pencil-square me-2"></i> แก้ไขข้อมูลบริษัท
  </button>
</div>

<!-- ข้อมูลบริษัท -->
<div class="table-box shadow-sm">
  <div class="row">
    <!-- ข้อมูลบริษัท (ซ้าย) -->
    <div class="col-md-7">
      <div class="info-card shadow-sm mb-3">
        <div class="card-title mb-3">ข้อมูลบริษัท</div>
        <div class="info-list mb-2">
          <div><strong>ชื่อ:</strong> <?= htmlspecialchars($company['name'] ?? '') ?></div>
          <div><strong>เลขประจำตัวผู้เสียภาษี:</strong> <?= htmlspecialchars($company['tax_id'] ?? '') ?></div>
          <div><strong>ที่อยู่:</strong> <?= htmlspecialchars($company['address'] ?? '') ?></div>
          <div><strong>โทรศัพท์:</strong> <?= htmlspecialchars($company['phone'] ?? '') ?></div>
          <div><strong>อีเมล:</strong> <?= htmlspecialchars($company['email'] ?? '') ?></div>
        </div>
      </div>
    </div>
    <!-- ตำแหน่งงาน (ขวา) -->
    <div class="col-md-5">
      <div class="position-card shadow-sm mb-3">
        <div class="card-title mb-3">ตำแหน่งงาน</div>
        <?php if (count($positions) > 0): ?>
          <ul class="list-group mb-3">
            <?php foreach ($positions as $pos): ?>
              <li class="list-group-item d-flex justify-content-between align-items-center">
                <?= htmlspecialchars($pos['name']) ?>
                <form method="post" action="" style="margin:0;">
                  <input type="hidden" name="delete_position_id" value="<?= $pos['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('ลบตำแหน่งนี้?')">
                    <i class="bi bi-trash"></i>
                  </button>
                </form>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <div class="text-muted mb-3">ยังไม่มีตำแหน่งงาน</div>
        <?php endif; ?>
        <!-- ฟอร์มเพิ่มตำแหน่ง -->
        <form method="post" action="" class="d-flex gap-2">
          <input type="text" name="new_position" class="form-control" placeholder="ชื่อตำแหน่งงานใหม่" required>
          <button type="submit" class="btn btn-add-position">
            <i class="bi bi-plus-lg me-1"></i> เพิ่ม
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Modal แก้ไขบริษัท -->
<div class="modal fade" id="editCompanyModal" tabindex="-1" aria-labelledby="editCompanyModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" method="POST" action="edit_company.php">
      <div class="modal-header">
        <h5 class="modal-title" id="editCompanyModalLabel">แก้ไขข้อมูลบริษัท</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="tax_id" value="<?= htmlspecialchars($company['tax_id'] ?? '') ?>">
        <div class="mb-2">
          <label class="form-label">ชื่อบริษัท</label>
          <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($company['name'] ?? '') ?>" required>
        </div>
        <div class="mb-2">
          <label class="form-label">ที่อยู่</label>
          <input type="text" name="address" class="form-control" value="<?= htmlspecialchars($company['address'] ?? '') ?>">
        </div>
        <div class="mb-2">
          <label class="form-label">โทรศัพท์</label>
          <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($company['phone'] ?? '') ?>">
        </div>
        <div class="mb-2">
          <label class="form-label">อีเมล</label>
          <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($company['email'] ?? '') ?>">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
        <button type="submit" class="btn btn-success">บันทึก</button>
      </div>
    </form>
  </div>
</div>
      </main>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>