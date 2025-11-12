<?php
session_start();
$conn = new mysqli('localhost', 'root', '', 'salary_system');
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }
$company_name = '';
$company_tax_id = '';
$companies = [];
$result = $conn->query("SELECT name, tax_id FROM companies");
while ($row = $result->fetch_assoc()) {
  $companies[] = $row;
}
if (isset($_POST['change_company'])) {
  $selected_tax_id = $conn->real_escape_string($_POST['change_company']);
  $_SESSION['selected_company_tax_id'] = $selected_tax_id;
  $result = $conn->query("SELECT name, tax_id FROM companies WHERE tax_id='$selected_tax_id' LIMIT 1");
  if ($row = $result->fetch_assoc()) {
    $company_name = $row['name'];
    $company_tax_id = $row['tax_id'];
  }
} elseif (isset($_SESSION['selected_company_tax_id'])) {
  $tax_id = $conn->real_escape_string($_SESSION['selected_company_tax_id']);
  $result = $conn->query("SELECT name, tax_id FROM companies WHERE tax_id='$tax_id' LIMIT 1");
  if ($row = $result->fetch_assoc()) {
    $company_name = $row['name'];
    $company_tax_id = $row['tax_id'];
  }
}
if (isset($_GET['change_company'])) {
  $selected_tax_id = $conn->real_escape_string($_GET['change_company']);
  $_SESSION['selected_company_tax_id'] = $selected_tax_id;
  header("Location: dashboard.php");
  exit;
}
// กรองข้อมูลพนักงานตามสถานะ
$employees = [];
$filter = $_GET['filter'] ?? 'all';
$where = "";
if ($company_tax_id) {
  if ($filter == 'working') {
    $where = " AND status='ทำงานอยู่' ";
  } elseif ($filter == 'resigned') {
    $where = " AND status='ลาออก' ";
  }
  $result = $conn->query("SELECT * FROM employees WHERE company_tax_id='$company_tax_id' $where");
  while ($row = $result->fetch_assoc()) {
    $employees[] = $row;
  }
}
$positions = [];
$result = $conn->query("SELECT id, name FROM positions");
while ($row = $result->fetch_assoc()) {
  $positions[] = $row;
}
$conn->close();

?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>Admin Panel - จัดการข้อมูลพนักงาน</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400&display=swap" rel="stylesheet">
  <style>
    body {
      background: #eaf6fb;
      font-family: 'Prompt', sans-serif;
      font-weight: 300;
      min-height: 100vh;
    }
    .sidebar {
      background: #009688;
      color: #fff;
      min-height: 100vh;
      padding: 24px 10px 18px 10px;
      box-shadow: 0 0 16px rgba(0,0,0,0.04);
      width: 180px;
      max-width: 100vw;
      transition: width 0.2s;
      flex-shrink: 0;
    }
    .sidebar h5 {
      font-weight: 400;
      margin-bottom: 32px;
      font-size: 1.25rem;
      letter-spacing: 1px;
    }
    .sidebar a {
      color: #fff;
      text-decoration: none;
      display: flex;
      align-items: center;
      margin-bottom: 18px;
      font-size: 1.08rem;
      padding: 10px 14px;
      border-radius: 8px;
      transition: background 0.18s;
    }
    .sidebar a.active, .sidebar a:hover {
      background: #00796b;
      text-decoration: none;
    }
    .sidebar .bi {
      font-size: 1.2rem;
      margin-right: 10px;
    }
    .main-content {
      background: #f7fafc;
      min-height: 100vh;
      padding: 0;
      border-top-right-radius: 12px;
      border-bottom-right-radius: 12px;
      box-shadow: 0 0 16px rgba(0,0,0,0.04);
      flex: 1 1 0%;
      width: 100%;
      max-width: 100vw;
      margin-left: 0;
    }
    .container-fluid > .row {
      display: flex;
      flex-wrap: nowrap;
    }
    @media (max-width: 900px) {
      .sidebar {
        width: 100vw;
        min-height: auto;
        padding: 12px 4px;
      }
      .main-content {
        border-radius: 0;
        margin-left: 0;
      }
    }
    .company-header {
      background: #fff;
      border-radius: 12px;
      margin: 32px 32px 0 32px;
      padding: 24px 32px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      box-shadow: 0 2px 12px rgba(0,176,116,0.07);
    }
    .company-name {
      font-size: 1.25rem;
      font-weight: 500;
      color: #00695c;
      margin-bottom: 2px;
    }
    .tax-id {
      font-size: 1rem;
      color: #7b8a8b;
    }
    .btn-tax {
      background-color: #ffeb3b;
      color: #333;
      font-weight: 400;
      border-radius: 6px;
      border: none;
      margin-right: 8px;
      padding: 8px 18px;
      font-size: 1rem;
      box-shadow: 0 2px 8px rgba(255,235,59,0.08);
    }
    .btn-add {
      background-color: #009688;
      color: #fff;
      font-weight: 400;
      border-radius: 6px;
      border: none;
      padding: 8px 18px;
      font-size: 1rem;
      box-shadow: 0 2px 8px rgba(0,176,116,0.08);
      transition: background 0.2s;
    }
    .btn-add:hover {
      background-color: #009688;
      color: #fff;
    }
    .panel-title-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin: 32px 32px 0 32px;
    }
    .panel-title {
      font-size: 1.35rem;
      font-weight: 500;
      color: #333;
      letter-spacing: 1px;
      margin-bottom: 0;
    }
    .filter-btns {
      margin: 24px 32px 0 32px;
      display: flex;
      gap: 12px;
    }
    .filter-btns .btn {
      font-size: 1rem;
      border-radius: 20px;
      padding: 6px 22px;
      font-weight: 400;
      background: #fff;
      color: #009688;
      border: 1.5px solid #e0f2f1;
      transition: background 0.2s, color 0.2s;
    }
    .filter-btns .btn.active, .filter-btns .btn:hover {
      background: #009688;
      color: #fff;
      border-color: #009688;
    }
    .table-box {
      margin: 24px 32px 32px 32px;
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 2px 12px rgba(0,176,116,0.07);
      padding: 24px 18px;
    }
    .table {
      margin-bottom: 0;
      background: #fff;
      border-radius: 12px;
      overflow: hidden;
    }
    .table th {
      background: #f7fafc;
      font-weight: 400;
      color: #009688;
      border-bottom: 2px solid #e0f2f1;
      font-size: 1.05rem;
      padding: 12px 8px;
    }
    .table td {
      vertical-align: middle;
      font-size: 1rem;
      padding: 10px 8px;
      border-bottom: 1.5px solid #e0f2f1;
      background: #fff;
    }
    /* .table td, .table th {
    text-align: center;
    vertical-align: middle;
  } */
    .status-label {
      font-weight: 400;
      border-radius: 16px;
      padding: 4px 16px;
      font-size: 0.98rem;
      display: inline-block;
    }
    .status-label-working {
      background: #e8f5e9;
      color: #388e3c;
    }
    .status-label-resigned {
      background: #ffeaea;
      color: #d32f2f;
    }
    .action-btns .btn {
      border-radius: 50%;
      width: 36px;
      height: 36px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      margin-right: 4px;
      font-size: 1.1rem;
      border: none;
      background: #f7fafc;
      color: #009688;
      transition: background 0.18s, color 0.18s;
    }
    .action-btns .btn:hover {
      background: #009688;
      color: #fff;
    }
    @media (max-width: 900px) {
      .company-header, .panel-title-row, .filter-btns, .table-box { margin: 16px 4px; padding: 12px 6px; }
      .sidebar { padding: 16px 4px; }
      .main-content { padding: 0; }
      .panel-title-row { flex-direction: column; align-items: stretch; gap: 12px; }
    }
    /* เพิ่มสี #009688 ให้กับรายการที่เลือกใน dropdown */
    .dropdown-menu .selected-company,
    .dropdown-menu .dropdown-item.active {
      background: #009688 !important;
      color: #fff !important;
    }
    .dropdown-menu .dropdown-item:hover {
      background: #e0f2f1 !important;
      color: #00695c !important;
    }
      /* ...existing code... */
    .accordion-button:not(.collapsed) {
      background: #009688;
      color: #fff;
      box-shadow: none;
    }
    .accordion-button {
      background: transparent;
      color: #fff;
      box-shadow: none;
    }
    .accordion-item {
      background: transparent;
      border: none;
    }
    .sidebar-sub-link:hover, .sidebar-sub-link.active {
      background: #00796b;
      color: #fff;
      text-deco
      ration: none;
    }
      .accordion-button::after {
        display: none !important; /* ซ่อนลูกศร bootstrap เดิม */
      }
      .custom-chevron {
        color: #fff !important;
        font-size: 1.1rem;
        transition: transform 0.2s;
      }
      /* หมุนขึ้นเมื่อเปิด, หมุนลงเมื่อปิด */
      .accordion-button[aria-expanded="true"] .custom-chevron {
        transform: rotate(180deg);
      }
      .accordion-button[aria-expanded="false"] .custom-chevron {
        transform: rotate(0deg);
}
  </style>
</head>
<body>

  <div class="container-fluid px-0">
    <div class="row gx-0">
      <!-- Sidebar -->
     <nav class="col-md-3 sidebar d-flex flex-column justify-content-between align-items-start" id="sidebar" style="transition:all 0.2s;min-width:220px;">
        <!-- Burger button เฉพาะจอเล็ก -->
        <button class="btn btn-outline-light d-md-none mb-3" id="sidebarBurger" type="button" style="border-radius:8px;">
          <i class="bi bi-list" style="font-size:2rem;"></i>
        </button>
        <div class="w-100">
          <h5 class="mb-4 mt-2"><i class="bi bi-person-gear me-2"></i>Admin Panel</h5>
          <a href="http://localhost:8080/%E0%B8%A3%E0%B8%B0%E0%B8%9A%E0%B8%9A%E0%B9%80%E0%B8%87%E0%B8%B4%E0%B8%99%E0%B9%80%E0%B8%94%E0%B8%B7%E0%B8%AD%E0%B8%99/admin/dashboard.php" class="active mb-2"><i class="bi bi-people-fill"></i> <span class="ms-2">พนักงาน</span></a>
         <a href="dashboard.php?page=salary_settings.php" class="mb-2"><i class="bi bi-cash-coin"></i> <span class="ms-2">เงินเดือน</span></a>
          <div class="accordion mb-2 w-100" id="sidebarSettingAccordion" style="background:transparent;border:none;">
            <div class="accordion-item" style="background:transparent;border:none;">
              <h2 class="accordion-header" id="headingSetting">
                <button class="accordion-button collapsed px-2 py-2" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSetting" aria-expanded="false" aria-controls="collapseSetting"
                      style="background:transparent;color:#fff;box-shadow:none;font-size:1.08rem;">
                      <i class="bi bi-gear-fill me-2"></i> ตั้งค่า
                      <span class="ms-auto">
                        <i class="bi bi-chevron-down custom-chevron"></i>
                      </span>
                    </button>
              </h2>
              <div id="collapseSetting" class="accordion-collapse collapse" aria-labelledby="headingSetting" data-bs-parent="#sidebarSettingAccordion">
                <div class="accordion-body py-2 px-0" style="background:transparent;">
                  <ul class="list-unstyled ms-4 mb-0">
                    <li>
                    <a class="sidebar-sub-link d-block py-1 px-2 rounded" href="dashboard.php?page=company_settings.php" style="color:#fff;">
                      ตั้งค่าบริษัท
                    </a>
                  </li>
                      
                    </li>     
                       <li>
              <a class="sidebar-sub-link d-block py-1 px-2 rounded" href="dashboard.php?page=settings_salary.php" style="color:#fff;">
                ตั้งค่าเงินเดือน       
              </a>
            </li>              
              <li>
              <a class="sidebar-sub-link d-block py-1 px-2 rounded" href="dashboard.php?page=admin_settings.php" style="color:#fff;">
                ตั้งค่าผู้ดูแลระบบ
              </a>
            </li>
         
             </ul>
                </div>
              </div>
            </div>
          </div>
        </div>
        <!-- ปุ่มออกจากระบบ -->
        <div class="w-100 mt-auto mb-2">
          <a href="http://localhost:8080/ระบบเงินเดือน/user/index.php" class="btn w-100 text-white mt-4" style="background-color:#d32f2f;border-radius:8px;">
            <i class="bi bi-box-arrow-left me-2"></i> ออกจากระบบ
          </a>
        </div>
      </nav>
      <!-- Main Content -->
      <main class="col-md-9 main-content px-0">
      <?php
          // ถ้าเลือกเมนู ตั้งค่าบริษัท ให้แสดงหน้า company_settings.php
          if (isset($_GET['page']) && $_GET['page'] === 'company_settings.php') {
            include 'company_settings.php';
          }
          // เพิ่มเงื่อนไขสำหรับ admin_settings.php
          elseif (isset($_GET['page']) && $_GET['page'] === 'admin_settings.php') {
            include 'admin_settings.php';
          }
          // เพิ่มเงื่อนไขสำหรับ salary_settings.php
          elseif (isset($_GET['page']) && $_GET['page'] === 'salary_settings.php') {
            include 'salary_settings.php';
          }
                // เพิ่มเงื่อนไขสำหรับ settings_salary.php
          elseif (isset($_GET['page']) && $_GET['page'] === 'settings_salary.php') {
            include 'settings_salary.php';
          }
          else {
      ?>
        <!-- ตกแต่ง dropdown ให้ใช้สี #009688 และดูสวยงาม -->
        <div class="company-header d-flex justify-content-between align-items-center flex-wrap shadow-sm">
          <div>
            <div class="company-name"><?= htmlspecialchars($company_name) ?></div>
            <div class="tax-id">เลขประจำตัวผู้เสียภาษี: <?= htmlspecialchars($company_tax_id) ?></div>
          </div>
          <div>
            <div class="dropdown">
              <button class="btn btn-add dropdown-toggle px-4 py-2" type="button" id="companyDropdown" data-bs-toggle="dropdown" aria-expanded="false"
                style="font-weight:500;box-shadow:0 2px 8px rgba(0,176,116,0.08);border-radius:8px;background:#009688;">
                <i class="bi bi-building me-2"></i>
                <span><?= htmlspecialchars($company_name) ?></span>
              </button>
              <ul class="dropdown-menu shadow" aria-labelledby="companyDropdown" style="min-width:220px;border-radius:12px;">
                <?php foreach ($companies as $company): ?>
                  <li>
                    <a class="dropdown-item d-flex align-items-center<?= ($company['tax_id'] == $company_tax_id) ? ' active selected-company' : '' ?>"
                       href="?change_company=<?= htmlspecialchars($company['tax_id']) ?>"
                       style="font-size:1.08rem;padding:10px 18px;border-radius:8px;">
                      <i class="bi bi-building me-2" style="color:#009688;"></i>
                      <span><?= htmlspecialchars($company['name']) ?></span>
                      <?php if ($company['tax_id'] == $company_tax_id): ?>
                        <i class="bi bi-check-circle ms-auto" style="color:#fff;margin-left:5px;"></i>
                      <?php endif; ?>
                    </a>
                  </li>
                <?php endforeach; ?>
              </ul>
            </div>
          </div>
        </div>
        <!-- ปรับ panel-title ให้มีปุ่มเพิ่มพนักงานอยู่ขวา -->
        <div class="panel-title-row">
          <div class="panel-title"><i class="bi bi-table me-2"></i>จัดการข้อมูลพนักงาน</div>
          <button class="btn btn-add d-flex align-items-center" style="font-weight:500;" data-bs-toggle="modal" data-bs-target="#addEmployeeModal">
            <i class="bi bi-person-plus me-2"></i> เพิ่มพนักงาน
          </button>
        </div>
        <div class="filter-btns mb-3">
          <a href="?filter=all"
            class="btn<?= ($filter=='all'||!isset($_GET['filter']))?' active':'' ?>"
            style="<?= ($filter=='all'||!isset($_GET['filter'])) ? 'background:#009688;color:#fff;' : '' ?>">
            ทั้งหมด
          </a>
          <a href="?filter=working"
            class="btn<?= ($filter=='working')?' active':'' ?>"
            style="<?= ($filter=='working') ? 'background:#4caf50;color:#fff;' : '' ?>">
            ทำงานอยู่
          </a>
          <a href="?filter=resigned"
            class="btn<?= ($filter=='resigned')?' active':'' ?>"
            style="<?= ($filter=='resigned') ? 'background:#d32f2f;color:#fff;' : '' ?>">
            ลาออกแล้ว
          </a>
        </div>
        <div class="table-box shadow-sm">
          <div class="table-responsive">
            <table class="table table-bordered align-middle">
              <thead>
                <tr>
                  <th>ชื่อ - นามสกุล</th>
                  <th>ชื่อเล่น</th>
                  <th>ตำแหน่ง</th>
                  <th>อายุ</th>
                  <th>สถานะ</th>
                  <th>ดำเนินการ</th>
                </tr>
              </thead>
              <tbody>
  <?php foreach ($employees as $emp): ?>
  <tr>
    <td><?= htmlspecialchars($emp['fullname']) ?></td>
    <td><?= htmlspecialchars($emp['nickname']) ?></td>
    <td><?= htmlspecialchars($emp['position']) ?></td>
    <td><?= htmlspecialchars($emp['age']) ?></td>
    <td>
      <?php
        $status = htmlspecialchars($emp['status']);
        if ($status == 'ทำงานอยู่') {
          echo '<span class="status-label status-label-working">'.$status.'</span>';
        } elseif ($status == 'ลาออก') {
          echo '<span class="status-label status-label-resigned">'.$status.'</span>';
        } else {
          echo '<span class="status-label">'.$status.'</span>';
        }
      ?>
    </td>
    <td class="action-btns">
    <!-- ดูข้อมูล -->
    <button class="btn" title="ดูข้อมูล" data-bs-toggle="modal" data-bs-target="#viewEmployeeModal<?= $emp['id'] ?>">
      <i class="bi bi-eye"></i>
    </button>
    <!-- แก้ไข: สีเหลือง -->
    <button class="btn" title="แก้ไข" style="color:#ff9800;" data-bs-toggle="modal" data-bs-target="#editEmployeeModal<?= $emp['id'] ?>">
      <i class="bi bi-pencil-square"></i>
    </button>
    <!-- ลบ: สีแดง -->
    <button class="btn" title="ลบ" style="color:#d32f2f;" onclick="if(confirm('ยืนยันการลบพนักงาน?')) location.href='delete_employee.php?id=<?= $emp['id'] ?>';">
      <i class="bi bi-trash"></i>
    </button>
  </td>
</tr>


<!-- Modal แก้ไขข้อมูลพนักงาน -->
<div class="modal fade" id="editEmployeeModal<?= $emp['id'] ?>" tabindex="-1" aria-labelledby="editEmployeeModalLabel<?= $emp['id'] ?>" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" action="edit_employee.php" enctype="multipart/form-data">
        <input type="hidden" name="id" value="<?= $emp['id'] ?>">
        <div class="modal-header">
          <h5 class="modal-title" id="editEmployeeModalLabel<?= $emp['id'] ?>">แก้ไขข้อมูลพนักงาน</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
        </div>
        <div class="modal-body">
          <!-- ข้อมูลส่วนตัว -->
          <h6 class="mb-3">ข้อมูลส่วนตัว</h6>
          <div class="row mb-2">
            <div class="col-md-2">
              <label class="form-label">คำนำหน้า</label>
              <select name="prefix" class="form-select">
                <option value="นาย" <?= $emp['prefix']=='นาย'?'selected':'' ?>>นาย</option>
                <option value="นาง" <?= $emp['prefix']=='นาง'?'selected':'' ?>>นาง</option>
                <option value="นางสาว" <?= $emp['prefix']=='นางสาว'?'selected':'' ?>>นางสาว</option>
              </select>
            </div>
            <div class="col-md-5">
              <label class="form-label">ชื่อ <span class="text-danger">*</span></label>
              <input type="text" name="firstname" class="form-control" value="<?= htmlspecialchars($emp['firstname']) ?>" required>
            </div>
            <div class="col-md-5">
              <label class="form-label">นามสกุล <span class="text-danger">*</span></label>
              <input type="text" name="lastname" class="form-control" value="<?= htmlspecialchars($emp['lastname']) ?>" required>
            </div>
          </div>
          <div class="row mb-2">
            <div class="col-md-4">
              <label class="form-label">ชื่อเล่น</label>
              <input type="text" name="nickname" class="form-control" value="<?= htmlspecialchars($emp['nickname']) ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">วันเกิด</label>
              <input type="date" name="birthdate" class="form-control" value="<?= htmlspecialchars($emp['birthdate']) ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">เบอร์โทรศัพท์</label>
              <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($emp['phone']) ?>">
            </div>
          </div>
          <div class="row mb-2">
            <div class="col-md-6">
              <label class="form-label">เลขบัตรประชาชน <span class="text-danger">*</span></label>
              <input type="text" name="idcard" class="form-control" value="<?= htmlspecialchars($emp['idcard']) ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">เลขบัญชีธนาคาร</label>
              <input type="text" name="bank_account" class="form-control" value="<?= htmlspecialchars($emp['bank_account']) ?>">
            </div>
          </div>
          <div class="row mb-2">
            <div class="col-md-12">
              <label class="form-label">ที่อยู่</label>
              <input type="text" name="address" class="form-control" value="<?= htmlspecialchars($emp['address']) ?>">
            </div>
          </div>
          <div class="row mb-2">
            <div class="col-md-3">
              <label class="form-label">ตำบล/แขวง</label>
              <input type="text" name="subdistrict" class="form-control" value="<?= htmlspecialchars($emp['subdistrict']) ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label">อำเภอ/เขต</label>
              <input type="text" name="district" class="form-control" value="<?= htmlspecialchars($emp['district']) ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label">จังหวัด</label>
              <input type="text" name="province" class="form-control" value="<?= htmlspecialchars($emp['province']) ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label">รหัสไปรษณีย์</label>
              <input type="text" name="zipcode" class="form-control" value="<?= htmlspecialchars($emp['zipcode']) ?>">
            </div>
          </div>
          <hr>
          <!-- ข้อมูลการทำงาน -->
          <h6 class="mb-3">ข้อมูลการทำงาน</h6>
          <div class="row mb-2">
            <div class="col-md-6">
              <label class="form-label">วันที่เริ่มงาน</label>
              <input type="date" name="start_work_date" class="form-control" value="<?= htmlspecialchars($emp['start_work_date']) ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">วันที่สิ้นสุดการทำงาน</label>
              <input type="date" name="end_work_date" id="edit_end_work_date<?= $emp['id'] ?>" class="form-control" value="<?= htmlspecialchars($emp['end_work_date']) ?>">            </div>
          </div>
          <div class="row mb-2">
            <div class="col-md-6">
              <label class="form-label">ตำแหน่งงาน</label>
              <select name="position" class="form-select">
                <option value="">เลือกตำแหน่งงาน</option>
                <?php foreach ($positions as $pos): ?>
                  <option value="<?= htmlspecialchars($pos['name']) ?>" <?= $emp['position']==$pos['name']?'selected':'' ?>>
                    <?= htmlspecialchars($pos['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <span class="text-danger small">ถ้าไม่เลือกจะใช้ค่าตำแหน่งตั้งต้น</span>
            </div>
            <div class="col-md-6 d-flex align-items-end">
              <div class="form-check me-3">
                <input class="form-check-input" type="checkbox" name="is_insured" id="is_insured<?= $emp['id'] ?>" value="1" <?= $emp['is_insured']?'checked':'' ?>>
                <label class="form-check-label" for="is_insured<?= $emp['id'] ?>">เป็นผู้ประกันตน</label>
              </div>
              <span class="text-danger small">ถ้าเป็นผู้ประกันตนให้ติ๊กถูก ถ้าไม่มีไม่ต้องติ๊กถูก</span>
            </div>
          </div>
          <div class="row mb-2">
            <div class="col-md-6">
              <label class="form-label">เงินสะสมเริ่มต้น (บาท)</label>
              <input type="number" name="salary" class="form-control" value="<?= htmlspecialchars($emp['salary']) ?>">
              <span class="text-muted small">หมายเหตุ: กรอกได้ถ้าต้องการใช้ค่าต่างจากที่ตั้งระบบ</span>
            </div>
            <div class="col-md-6">
              <label class="form-label">หมายเหตุ</label>
              <input type="text" name="note" class="form-control" value="<?= htmlspecialchars($emp['note']) ?>">
            </div>
          </div>
          <div class="row mb-2">
            <div class="col-md-12">
              <label class="form-label">ประวัติการปรับค่าแรง (รวม)</label>
              <div class="row">
              <div class="col-md-3">
                <input type="date" name="salary_change_date" class="form-control" value="<?= htmlspecialchars($emp['salary_change_date']) ?>" placeholder="วันที่">
              </div>
              <div class="col-md-3">
                <input type="number" name="salary_change_amount" class="form-control" value="<?= htmlspecialchars($emp['salary_change_amount']) ?>" placeholder="ค่าปรับ (บาท/วัน)">
              </div>
              <div class="col-md-3">
                <input type="text" name="salary_change_reason" class="form-control" value="<?= htmlspecialchars($emp['salary_change_reason']) ?>" placeholder="คำอธิบาย">
              </div>
              <div class="col-md-3">
                <input type="text" name="salary_change_note" class="form-control" value="<?= htmlspecialchars($emp['salary_change_note']) ?>" placeholder="หมายเหตุ">
              </div>
            </div>
            </div>
          </div>
          <!-- สถานะ -->
          <div class="row mb-2">
        <div class="col-md-6">
          <label class="form-label">สถานะ</label>
          <input type="text" name="status" id="edit_status<?= $emp['id'] ?>" class="form-control" value="<?= htmlspecialchars($emp['status']) ?>" readonly>
        </div>
      </div>
          <!-- แนบไฟล์เอกสาร/รูปถ่าย -->
          <div class="row mb-2">
            <div class="col-md-3 mb-2">
              <label class="form-label">เอกสารรูปภาพ</label>
              <input type="file" name="photo" class="form-control" accept="image/*">
              <?php if ($emp['photo']) { ?>
                <small class="text-muted">ไฟล์เดิม: <a href="../uploads/<?= htmlspecialchars($emp['photo']) ?>" target="_blank">ดูรูป</a></small>
              <?php } ?>
            </div>
            <div class="col-md-3 mb-2">
              <label class="form-label">บัตรประชาชน</label>
              <input type="file" name="idcard_file" class="form-control">
              <?php if ($emp['idcard_file']) { ?>
                <small class="text-muted">ไฟล์เดิม: <a href="../uploads/<?= htmlspecialchars($emp['idcard_file']) ?>" target="_blank">ดูไฟล์</a></small>
              <?php } ?>
            </div>
            <div class="col-md-3 mb-2">
              <label class="form-label">ทะเบียนบ้าน</label>
              <input type="file" name="house_file" class="form-control">
              <?php if ($emp['house_file']) { ?>
                <small class="text-muted">ไฟล์เดิม: <a href="../uploads/<?= htmlspecialchars($emp['house_file']) ?>" target="_blank">ดูไฟล์</a></small>
              <?php } ?>
            </div>
            <div class="col-md-3 mb-2">
              <label class="form-label">อื่นๆ</label>
              <input type="file" name="other_file" class="form-control">
              <?php if ($emp['other_file']) { ?>
                <small class="text-muted">ไฟล์เดิม: <a href="../uploads/<?= htmlspecialchars($emp['other_file']) ?>" target="_blank">ดูไฟล์</a></small>
              <?php } ?>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
          <button type="submit" class="btn btn-warning">บันทึกการแก้ไข</button>
        </div>
      </form>
    </div>
  </div>
</div>



  <!-- Modal ดูข้อมูลพนักงาน -->
  <div class="modal fade" id="viewEmployeeModal<?= $emp['id'] ?>" tabindex="-1" aria-labelledby="viewEmployeeModalLabel<?= $emp['id'] ?>" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="viewEmployeeModalLabel<?= $emp['id'] ?>">ข้อมูลพนักงาน</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
        </div>
        <div class="modal-body">
          <h6 class="mb-3">ข้อมูลส่วนตัว</h6>
          <div class="row mb-2">
            <div class="col-md-6">
              <strong>ชื่อ-สกุล:</strong> <?= htmlspecialchars($emp['fullname']) ?>
            </div>
            <div class="col-md-6">
              <strong>ชื่อเล่น:</strong> <?= htmlspecialchars($emp['nickname']) ?>
            </div>
          </div>
          <div class="row mb-2">
            <div class="col-md-6">
              <strong>วันเกิด:</strong>
              <?php
                  if ($emp['birthdate']) {
                        $birth = new DateTime($emp['birthdate']);
                        $now = new DateTime();
                        $age = $now->diff($birth)->y;
                        echo date('d/m/Y', strtotime($emp['birthdate'])) . " (อายุ $age ปี)";
                      } else {
                        echo "-";
                      }
                    ?>
                  </div>
                  <div class="col-md-6">
                    <strong>สถานะ:</strong>
                    <?php
                      if ($emp['status'] == 'ลาออก') {
                        echo '<span class="status-label status-label-resigned">ลาออก</span>';
                      } else {
                        echo '<span class="status-label status-label-working">ทำงานอยู่</span>';
                      }
                    ?>
                  </div>
                </div>
                <div class="row mb-2">
                  <div class="col-md-6">
                    <strong>เลขบัตรประชาชน:</strong> <?= htmlspecialchars($emp['idcard']) ?>
                  </div>
                  <div class="col-md-6">
                    <strong>โทรศัพท์:</strong> <?= htmlspecialchars($emp['phone']) ?>
                  </div>
                </div>
                <div class="row mb-2">
                  <div class="col-md-6">
                    <strong>ที่อยู่:</strong>
                    <?= htmlspecialchars($emp['address']) ?>
                    <?= htmlspecialchars($emp['subdistrict']) ?> <?= htmlspecialchars($emp['district']) ?> <?= htmlspecialchars($emp['province']) ?>
                  </div>
                  <div class="col-md-6">
                    <strong>เลขบัญชีธนาคาร:</strong> <?= htmlspecialchars($emp['bank_account'] ?? '-') ?>
                  </div>
                </div>
                <hr>
                <h6 class="mb-3">ข้อมูลการทำงาน</h6>
                <div class="row mb-2">
                  <div class="col-md-6">
                    <strong>ตำแหน่ง:</strong> <?= htmlspecialchars($emp['position']) ?>
                  </div>
                  <div class="col-md-6">
                    <strong>วันที่เริ่มงาน:</strong>
                    <?= $emp['start_work_date'] ? date('d/m/Y', strtotime($emp['start_work_date'])) : '-' ?>
                  </div>
                </div>
                <div class="row mb-2">
                  <div class="col-md-6">
                    <strong>ผู้ประกันตน:</strong> <?= $emp['is_insured'] ? 'ใช่' : 'ไม่ใช่' ?>
                  </div>
                  <div class="col-md-6">
                    <strong>วันที่สิ้นสุด:</strong>
                    <?= $emp['end_work_date'] ? date('d/m/Y', strtotime($emp['end_work_date'])) : '-' ?>
                  </div>
                </div>
                <div class="row mb-2">
                  <div class="col-md-6">
                    <strong>เงินสะสมเริ่มต้น:</strong>
                    <?= number_format($emp['salary'], 2) ?> บาท
                  </div>
                  <div class="col-md-6">
                    <strong>สถานะ:</strong>
                    <?php
                      if ($emp['status'] == 'ลาออก') {
                        echo '<span class="status-label status-label-resigned">ลาออก</span>';
                      } else {
                        echo '<span class="status-label status-label-working">ทำงานอยู่</span>';
                      }
                    ?>
                  </div>
                </div>
               <div class="row mb-2">
                  <div class="col-md-12">
                   <strong>ประวัติการปรับค่าแรง (รายวัน):</strong>
                  <?php
                    $salary_history = [];
                    $conn2 = new mysqli('localhost', 'root', '', 'salary_system');
                    if (!$conn2->connect_error) {
                      $emp_id = intval($emp['id']);
                      $res_history = $conn2->query("SELECT * FROM salary_history WHERE employee_id=$emp_id ORDER BY change_date DESC");
                      while ($row = $res_history->fetch_assoc()) {
                        $salary_history[] = $row;
                      }
                      $conn2->close();
                    }
                    if (count($salary_history) > 0) {
                      foreach ($salary_history as $history) {
                        echo date('d/m/Y', strtotime($history['change_date'])) . " | ";
                        echo "ค่าปรับ: " . number_format($history['amount'], 2) . " | ";
                        echo "เหตุผล: " . htmlspecialchars($history['reason']) . " | ";
                        echo "หมายเหตุ: " . htmlspecialchars($history['note']);
                        echo "<br>";
                      }
                    } else {
                      echo "ไม่มีประวัติ";
                    }
                  ?>
                </div>
                  </div>
                <hr>
                <h6 class="mb-3">เอกสาร</h6>
                <div class="row mb-2">
                  <div class="col-md-3">
                    <strong>รูปถ่าย:</strong>
                    <?php if ($emp['photo']) { ?>
                      <a href="../uploads/<?= htmlspecialchars($emp['photo']) ?>" target="_blank">ดูรูป</a>
                    <?php } else { echo "ไม่มีไฟล์"; } ?>
                  </div>
                  <div class="col-md-3">
                    <strong>บัตรประชาชน:</strong>
                    <?php if ($emp['idcard_file']) { ?>
                      <a href="../uploads/<?= htmlspecialchars($emp['idcard_file']) ?>" target="_blank">ดูไฟล์</a>
                    <?php } else { echo "ไม่มีไฟล์"; } ?>
                  </div>
                  <div class="col-md-3">
                    <strong>ทะเบียนบ้าน:</strong>
                    <?php if ($emp['house_file']) { ?>
                      <a href="../uploads/<?= htmlspecialchars($emp['house_file']) ?>" target="_blank">ดูไฟล์</a>
                    <?php } else { echo "ไม่มีไฟล์"; } ?>
                  </div>
                  <div class="col-md-3">
                    <strong>อื่นๆ:</strong>
                    <?php if ($emp['other_file']) { ?>
                      <a href="../uploads/<?= htmlspecialchars($emp['other_file']) ?>" target="_blank">ดูไฟล์</a>
                    <?php } else { echo "ไม่มีไฟล์"; } ?>
                  </div>
                </div>
                <div class="row mt-3">
                  <div class="col-md-12">
                    <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#fundHistoryModal<?= $emp['id'] ?>">ดูประวัติเงินสะสม</button>
                    <button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">ปิด</button>
                  </div>
                </div>
              </div>
              
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </tbody>
            </table>
          </div>
        </div>
        
        <!-- Modal เพิ่มพนักงาน -->
        <div class="modal fade" id="addEmployeeModal" tabindex="-1" aria-labelledby="addEmployeeModalLabel" aria-hidden="true">
          <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
              <form method="POST" action="add_employee.php" enctype="multipart/form-data">
                <div class="modal-header">
                  <h5 class="modal-title" id="addEmployeeModalLabel">เพิ่มข้อมูลพนักงาน</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
                </div>
                <div class="modal-body">
                  <!-- ข้อมูลส่วนตัว -->
                  <h6 class="mb-3">ข้อมูลส่วนตัว</h6>
                  <div class="row mb-2">
                    <div class="col-md-2">
                      <label class="form-label">คำนำหน้า</label>
                      <select name="prefix" class="form-select">
                        <option value="นาย">นาย</option>
                        <option value="นาง">นาง</option>
                        <option value="นางสาว">นางสาว</option>
                      </select>
                    </div>
                    <div class="col-md-5">
                      <label class="form-label">ชื่อ <span class="text-danger">*</span></label>
                      <input type="text" name="firstname" class="form-control" required>
                    </div>
                    <div class="col-md-5">
                      <label class="form-label">นามสกุล <span class="text-danger">*</span></label>
                      <input type="text" name="lastname" class="form-control" required>
                    </div>
                  </div>
                  <div class="row mb-2">
                    <div class="col-md-4">
                      <label class="form-label">ชื่อเล่น</label>
                      <input type="text" name="nickname" class="form-control">
                    </div>
                    <div class="col-md-4">
                      <label class="form-label">วันเกิด</label>
                      <input type="date" name="birthdate" class="form-control">
                    </div>
                    <div class="col-md-4">
                      <label class="form-label">เบอร์โทรศัพท์</label>
                      <input type="text" name="phone" class="form-control">
                    </div>
                  </div>
                  <div class="row mb-2">
                    <div class="col-md-6">
                      <label class="form-label">เลขบัตรประชาชน <span class="text-danger">*</span></label>
                      <input type="text" name="idcard" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">เลขบัญชีธนาคาร</label>
                      <input type="text" name="bank_account" class="form-control">
                    </div>
                  </div>
                  <div class="row mb-2">
                    <div class="col-md-12">
                      <label class="form-label">ที่อยู่</label>
                      <input type="text" name="address" class="form-control" placeholder="บ้านเลขที่ หมู่">
                    </div>
                  </div>
                  <div class="row mb-2">
                    <div class="col-md-3">
                      <label class="form-label">ตำบล/แขวง</label>
                      <input type="text" name="subdistrict" class="form-control">
                    </div>
                    <div class="col-md-3">
                      <label class="form-label">อำเภอ/เขต</label>
                      <input type="text" name="district" class="form-control">
                    </div>
                    <div class="col-md-3">
                      <label class="form-label">จังหวัด</label>
                      <input type="text" name="province" class="form-control">
                    </div>
                    <div class="col-md-3">
                      <label class="form-label">รหัสไปรษณีย์</label>
                      <input type="text" name="zipcode" class="form-control">
                    </div>
                  </div>
                  <hr>
                  <!-- ข้อมูลการทำงาน -->
                  <h6 class="mb-3">ข้อมูลการทำงาน</h6>
                  <div class="row mb-2">
                    <div class="col-md-6">
                      <label class="form-label">วันที่เริ่มงาน</label>
                      <input type="date" name="start_work_date" id="start_work_date" class="form-control" onchange="updateStatus()">
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">วันที่สิ้นสุดการทำงาน</label>
                      <input type="date" name="end_work_date" id="end_work_date" class="form-control" onchange="updateStatus()">
                    </div>
                  </div>
                  <div class="row mb-2">
                    <div class="col-md-6">
                      <label class="form-label">ตำแหน่งงาน</label>
                          <select name="position" class="form-select">
                          <option value="">เลือกตำแหน่งงาน</option>
                          <?php foreach ($positions as $pos): ?>
                            <option value="<?= htmlspecialchars($pos['name']) ?>"><?= htmlspecialchars($pos['name']) ?></option>
                          <?php endforeach; ?>
                        </select>
                      <span class="text-danger small">ถ้าไม่เลือกจะใช้ค่าตำแหน่งตั้งต้น</span>
                    </div>
                    <div class="col-md-6 d-flex align-items-end">
                      <div class="form-check me-3">
                        <input class="form-check-input" type="checkbox" name="is_insured" id="is_insured" value="1">
                        <label class="form-check-label" for="is_insured">เป็นผู้ประกันตน</label>
                      </div>
                      <span class="text-danger small">ถ้าเป็นผู้ประกันตนให้ติ๊กถูก ถ้าไม่มีไม่ต้องติ๊กถูก</span>
                    </div>
                  </div>
                  <div class="row mb-2">
                    <div class="col-md-6">
                      <label class="form-label">เงินสะสมเริ่มต้น (บาท)</label>
                      <input type="number" name="salary" class="form-control">
                      <span class="text-muted small">หมายเหตุ: กรอกได้ถ้าต้องการใช้ค่าต่างจากที่ตั้งระบบ</span>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">หมายเหตุ</label>
                      <input type="text" name="note" class="form-control">
                    </div>
                  </div>
                  <div class="row mb-2">
                  <div class="col-md-12">
                    <label class="form-label">ประวัติการปรับค่าแรง (รายวัน)</label>
                    <!-- ลบส่วนนี้ออก ไม่ต้องดึงข้อมูลจากฐานข้อมูลมาแสดง -->
                    <!--
                    <div style="max-height:150px;overflow-y:auto;border:1px solid #eee;padding:8px;border-radius:8px;margin-bottom:8px;">
                      <?php
                      // ไม่ต้องดึงข้อมูล salary_history ในหน้าเพิ่มพนักงาน
                      ?>
                    </div>
                    -->
                    <!-- ฟอร์มสำหรับกรอกข้อมูลปรับค่าแรงครั้งแรก -->
                    <div class="row">
                      <div class="col-md-3">
                        <input type="date" name="salary_change_date" class="form-control" value="">
                      </div>
                      <div class="col-md-3">
                        <input type="number" name="salary_change_amount" class="form-control" placeholder="ค่าปรับ (บาท/วัน)">
                      </div>
                      <div class="col-md-3">
                        <input type="text" name="salary_change_reason" class="form-control" placeholder="คำอธิบาย">
                      </div>
                      <div class="col-md-3">
                        <input type="text" name="salary_change_note" class="form-control" placeholder="หมายเหตุ">
                      </div>
                    </div>
                    <span class="text-muted small">* การแก้ไขจะเพิ่มรายการใหม่ใน salary_history</span>
                  </div>
                </div>
                  <!-- สถานะ -->
                  <div class="row mb-2">
                    <div class="col-md-6">
                      <label class="form-label">สถานะ</label>
                      <input type="text" name="status" id="status" class="form-control" value="ทำงานอยู่" readonly>
                    </div>
                  </div>
                  <!-- แนบไฟล์เอกสาร/รูปถ่าย -->
                  <div class="row mb-2">
                    <div class="col-md-3 mb-2">
                      <label class="form-label">เอกสารรูปภาพ</label>
                      <input type="file" name="photo" class="form-control" accept="image/*">
                    </div>
                    <div class="col-md-3 mb-2">
                      <label class="form-label">บัตรประชาชน</label>
                      <input type="file" name="idcard_file" class="form-control">
                    </div>
                    <div class="col-md-3 mb-2">
                      <label class="form-label">ทะเบียนบ้าน</label>
                      <input type="file" name="house_file" class="form-control">
                    </div>
                    <div class="col-md-3 mb-2">
                      <label class="form-label">อื่นๆ</label>
                      <input type="file" name="other_file" class="form-control">
                    </div>
                  </div>
                </div>
                <div class="modal-footer">
                  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                  <button type="submit" class="btn btn-success">บันทึกข้อมูล</button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </main>
    </div>
  </div>
  
  <script>
    function toggleSidebar() {
      const sidebar = document.getElementById('sidebar');
      sidebar.classList.toggle('d-none');
    }
    window.addEventListener('resize', function() {
      const sidebar = document.getElementById('sidebar');
      if (window.innerWidth < 768) {
        sidebar.classList.add('d-none');
      } else {
        sidebar.classList.remove('d-none');
      }
    });
    if (window.innerWidth < 768) {
      document.getElementById('sidebar').classList.add('d-none');
    }

    // อัปเดตสถานะอัตโนมัติ
    function updateStatus() {
      const start = document.getElementById('start_work_date').value;
      const end = document.getElementById('end_work_date').value;
      const status = document.getElementById('status');
      if (end) {
        status.value = "ลาออก";
      } else if (start) {
        status.value = "ทำงานอยู่";
      } else {
        status.value = "";
      }
    }
  </script>
  
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      document.querySelectorAll('[id^="edit_end_work_date"]').forEach(function(endInput) {
        const id = endInput.id.replace('edit_end_work_date', '');
        const statusInput = document.getElementById('edit_status' + id);
        if (statusInput) {
          endInput.addEventListener('change', function() {
            if (endInput.value) {
              statusInput.value = "ลาออก";
            } else {
              statusInput.value = "ทำงานอยู่";
            }
          });
        }
      });
    });
  </script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script></body>
</html> 
    <?php } ?>
    
</main>