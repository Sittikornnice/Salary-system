<?php
if (session_status() === PHP_SESSION_NONE) {
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
// ดึงรายชื่อบริษัททั้งหมด
$companies = [];
$result = $conn->query("SELECT name, tax_id FROM companies");
while ($row = $result->fetch_assoc()) {
  $companies[] = $row;
}

// --- จัดการผู้ดูแลระบบ ---
if (isset($_POST['edit_username'])) {
    $id = intval($_POST['id']);
    $username = $conn->real_escape_string($_POST['username']);
    $conn->query("UPDATE admin SET username='$username' WHERE id=$id");
    $_SESSION['success_message'] = "เปลี่ยนชื่อผู้ดูแลระบบเรียบร้อยแล้ว";
    echo "<script>window.location='dashboard.php?page=admin_settings.php';</script>";
    exit;
}
if (isset($_POST['edit_password'])) {
    $id = intval($_POST['id']);
    $password = $conn->real_escape_string($_POST['password']);
    $conn->query("UPDATE admin SET password='$password' WHERE id=$id");
    $_SESSION['success_message'] = "เปลี่ยนรหัสผ่านเรียบร้อยแล้ว";
    echo "<script>window.location='dashboard.php?page=admin_settings.php';</script>";
    exit;
}
if (isset($_POST['add_admin'])) {
    $username = $conn->real_escape_string($_POST['username']);
    $password = $conn->real_escape_string($_POST['password']);
    $conn->query("INSERT INTO admin (username, password) VALUES ('$username', '$password')");
    $_SESSION['success_message'] = "เพิ่มผู้ดูแลระบบใหม่เรียบร้อยแล้ว";
    echo "<script>window.location='dashboard.php?page=admin_settings.php';</script>";
    exit;
}
if (isset($_POST['delete_admin_id'])) {
    $id = intval($_POST['delete_admin_id']);
    $conn->query("DELETE FROM admin WHERE id=$id");
    $_SESSION['success_message'] = "ลบผู้ดูแลระบบเรียบร้อยแล้ว";
    echo "<script>window.location='dashboard.php?page=admin_settings.php';</script>";
    exit;
}

// ดึงข้อมูลผู้ดูแลระบบ
$admins = [];
$result = $conn->query("SELECT * FROM admin");
while ($row = $result->fetch_assoc()) {
    $admins[] = $row;
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

<!-- Panel Title -->
<div class="panel-title-row mb-3 d-flex justify-content-between align-items-center">
  <div class="panel-title"><i class="bi bi-person-badge me-2"></i>ตั้งค่าผู้ดูแลระบบ</div>
  <button class="btn btn-add d-flex align-items-center" style="font-weight:500;" data-bs-toggle="modal" data-bs-target="#addAdminModal">
    <i class="bi bi-person-plus me-2"></i> เพิ่มผู้ดูแล
  </button>
</div>

<!-- ตารางผู้ดูแลระบบ -->
<div class="table-box shadow-sm">
  <div class="row">
    <div class="col-md-12">
      <div class="info-card shadow-sm mb-3">
        <div class="card-title mb-3">รายชื่อผู้ดูแลระบบ</div>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th style="width:25%">ชื่อผู้ใช้</th>
                <th style="width:25%">เปลี่ยนชื่อ</th>
                <th style="width:25%">เปลี่ยนรหัสผ่าน</th>
                <th style="width:15%">ลบ</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($admins as $admin): ?>
              <tr>
                <td>
                  <span class="fw-semibold"><?= htmlspecialchars($admin['username']) ?></span>
                </td>
                <td>
                  <form method="POST" class="d-flex gap-2">
                    <input type="hidden" name="id" value="<?= $admin['id'] ?>">
                    <input type="text" name="username" class="form-control form-control-sm" value="<?= htmlspecialchars($admin['username']) ?>" required>
                    <button type="submit" name="edit_username" class="btn btn-warning btn-sm">บันทึก</button>
                  </form>
                </td>
                <td>
                  <form method="POST" class="d-flex gap-2">
                    <input type="hidden" name="id" value="<?= $admin['id'] ?>">
                    <input type="text" name="password" class="form-control form-control-sm" placeholder="รหัสผ่านใหม่" required>
                    <button type="submit" name="edit_password" class="btn btn-primary btn-sm">เปลี่ยนรหัสผ่าน</button>
                  </form>
                </td>
                <td>
                  <form method="POST" onsubmit="return confirm('คุณต้องการลบผู้ดูแลระบบนี้ใช่หรือไม่?');">
                    <input type="hidden" name="delete_admin_id" value="<?= $admin['id'] ?>">
                    <button type="submit" class="btn btn-danger btn-sm"><i class="bi bi-trash"></i> ลบ</button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal เพิ่มผู้ดูแลระบบ -->
<div class="modal fade" id="addAdminModal" tabindex="-1" aria-labelledby="addAdminModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form method="POST" class="modal-content">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title" id="addAdminModalLabel"><i class="bi bi-person-plus me-2"></i> เพิ่มผู้ดูแลระบบใหม่</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">ชื่อผู้ใช้</label>
          <input type="text" name="username" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">รหัสผ่าน</label>
          <input type="text" name="password" class="form-control" required>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" name="add_admin" class="btn btn-success w-100">เพิ่มผู้ดูแล</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>