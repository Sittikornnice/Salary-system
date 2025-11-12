<?php
session_start();

// เชื่อมต่อฐานข้อมูล
$conn = new mysqli('localhost', 'root', '', 'salary_system');
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

// เพิ่มบริษัทใหม่ (ถ้ามีการกรอกข้อมูล)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_company'])) {
  $name = trim($_POST['company_name'] ?? '');
  $tax_id = trim($_POST['company_tax_id'] ?? '');

  if ($name === '' || $tax_id === '') {
    $_SESSION['company_error'] = 'กรุณากรอกชื่อบริษัทและเลขประจำตัวผู้เสียภาษี';
  } else {
    // ใช้ prepared statement ป้องกัน SQL injection
    $stmt = $conn->prepare("INSERT INTO companies (name, tax_id) VALUES (?, ?)");
    if ($stmt) {
      $stmt->bind_param('ss', $name, $tax_id);
      if ($stmt->execute()) {
        $_SESSION['success_message'] = 'เพิ่มบริษัทเรียบร้อยแล้ว';
        $stmt->close();
        $conn->close();
        header("Location: company.php");
        exit;
      } else {
        $_SESSION['company_error'] = 'ไม่สามารถบันทึกข้อมูลได้: ' . $stmt->error;
        $stmt->close();
      }
    } else {
      $_SESSION['company_error'] = 'เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL';
    }
  }
}

// เลือกบริษัท
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selected_company'])) {
  $tax_id = trim($_POST['selected_company'] ?? '');
  if ($tax_id !== '') {
    $stmt = $conn->prepare("SELECT name, tax_id FROM companies WHERE tax_id = ? LIMIT 1");
    if ($stmt) {
      $stmt->bind_param('s', $tax_id);
      $stmt->execute();
      $res = $stmt->get_result();
      if ($company = $res->fetch_assoc()) {
        $_SESSION['selected_company_name'] = $company['name'];
        $_SESSION['selected_company_tax_id'] = $company['tax_id'];
        $stmt->close();
        $conn->close();
  echo "<script>window.top.location.href='dashboard.php';</script>";
        exit;
      }
      $stmt->close();
    }
  }
}

// ดึงข้อมูลบริษัทจากฐานข้อมูล
$companies = [];
if ($res = $conn->query("SELECT name, tax_id FROM companies ORDER BY name ASC")) {
  while ($row = $res->fetch_assoc()) {
    $companies[] = $row;
  }
  $res->free();
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>เลือกบริษัท</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400&display=swap" rel="stylesheet">
  <style>
    body {
      background: #eaf6fb;
      font-family: 'Prompt', sans-serif;
      font-weight: 300;
      min-height: 100vh;
      display: flex;
      justify-content: center;
      align-items: center;
      padding: 20px;
    }
    .popup-box {
      background: #fff;
      border-radius: 16px;
      box-shadow: 0 8px 32px rgba(0, 176, 116, 0.08);
      padding: 32px;
      max-width: 520px;
      width: 100%;
    }
    .popup-title {
      font-size: 1.4rem;
      font-weight: 400;
      color: #009688;
      margin-bottom: 12px;
      text-align: center;
    }
    .popup-subtitle {
      font-size: 1rem;
      color: #7b8a8b;
      text-align: left;
      margin-bottom: 16px;
      padding-left: 2px;
    }
    .company-option {
      border: 1.5px solid #e0f2f1;
      border-radius: 10px;
      padding: 12px 16px;
      margin-bottom: 12px;
      background: #f7fafc;
      cursor: pointer;
      transition: background 0.2s;
      display: flex;
      align-items: center;
      gap: 12px;
      width: 100%;
      box-sizing: border-box;
    }
    .popup-box label {
      display: block;
      width: 100%;
      cursor: pointer;
    }
    .company-option:hover {
      background: #e0f2f1;
    }
    .company-icon {
      font-size: 1.6rem;
      color: #009688;
    }
    .company-details { flex-grow: 1; }
    .company-name { font-weight: 400; color: #00695c; }
    .tax-id { font-size: 0.95rem; color: #7b8a8b; }
    .btn-group { display: flex; justify-content: space-between; margin-top: 24px; }
    .btn-confirm {
      background-color: #7be7c4;
      color: #00695c;
      border: none;
      border-radius: 8px;
      padding: 10px 20px;
      font-weight: 400;
    }
    .btn-cancel {
      background-color: #eee;
      color: #555;
      border: none;
      border-radius: 8px;
      padding: 10px 20px;
    }
    input[type="radio"] { display: none; }
    input[type="radio"]:checked + .company-option { border-color: #26a69a; background: #e0f2f1; }
    .add-company-box {
      background: #f7fafc;
      border-radius: 10px;
      padding: 16px;
      margin-bottom: 18px;
      border: 1.5px solid #e0f2f1;
      display: none;
    }
    .add-company-btn {
      background: #009688;
      color: #fff;
      border: none;
      border-radius: 8px;
      padding: 8px 14px;
    }
  </style>
  <script>
    function showAddCompany() {
      document.getElementById('addCompanyBox').style.display = 'block';
      const el = document.querySelector('input[name="company_name"]');
      if (el) el.focus();
    }
    document.addEventListener('click', function(e){
      const label = e.target.closest('label');
      if (label) {
        const radio = label.querySelector('input[type="radio"]');
        if (radio) radio.checked = true;
      }
    });
  </script>
</head>
<body>
  <form method="POST" class="popup-box" novalidate>
    <div class="popup-title">
      <i class="bi bi-diagram-3-fill me-2"></i>เลือกบริษัท
    </div>
    <div class="popup-subtitle">รายการบริษัท</div>

    <?php if (!empty($_SESSION['success_message'])): ?>
      <div class="alert alert-success small"><?= htmlspecialchars($_SESSION['success_message'], ENT_QUOTES) ?></div>
      <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['company_error'])): ?>
      <div class="alert alert-danger small"><?= htmlspecialchars($_SESSION['company_error'], ENT_QUOTES) ?></div>
      <?php unset($_SESSION['company_error']); ?>
    <?php endif; ?>

    <!-- ปุ่มเพิ่มบริษัท -->
    <button type="button" class="btn btn-success mb-3" onclick="showAddCompany();">
      <i class="bi bi-plus-circle me-1"></i> เพิ่มบริษัท
    </button>

    <!-- ฟอร์มเพิ่มบริษัท -->
    <div id="addCompanyBox" class="add-company-box mb-3">
      <div class="add-company-title"><i class="bi bi-plus-circle me-1"></i>เพิ่มบริษัทใหม่</div>
      <div class="row g-2">
        <div class="col-7">
          <input type="text" name="company_name" class="form-control" placeholder="ชื่อบริษัท" required>
        </div>
        <div class="col-5">
          <input type="text" name="company_tax_id" class="form-control" placeholder="เลขประจำตัวผู้เสียภาษี" maxlength="13" required>
        </div>
      </div>
      <button type="submit" name="add_company" class="add-company-btn mt-2">
        <i class="bi bi-plus-circle me-1"></i> เพิ่มบริษัท
      </button>
    </div>

    <?php if (empty($companies)): ?>
      <div class="text-center text-muted mb-3">ยังไม่มีรายการบริษัท</div>
    <?php endif; ?>

    <?php foreach ($companies as $company): ?>
      <label>
        <input type="radio" name="selected_company" value="<?= htmlspecialchars($company['tax_id'], ENT_QUOTES) ?>" required>
        <div class="company-option">
          <i class="bi bi-building company-icon"></i>
          <div class="company-details">
            <div class="company-name"><?= htmlspecialchars($company['name'], ENT_QUOTES) ?></div>
            <div class="tax-id">เลขประจำตัวผู้เสียภาษี: <?= htmlspecialchars($company['tax_id'], ENT_QUOTES) ?></div>
          </div>
        </div>
      </label>
    <?php endforeach; ?>

    <div class="btn-group">
      <button type="submit" class="btn btn-confirm">
        <i class="bi bi-check-circle me-1"></i> ตกลง
      </button>
      <!-- ปรับเป็นปุ่มยกเลิกที่ส่งกลับไปหน้า index.php (ใช้ window.top ถ้าไฟล์เปิดใน iframe/modal) -->
      <button type="button" class="btn btn-cancel" onclick="window.top.location.href='http://localhost:8080/%E0%B8%A3%E0%B8%B0%E0%B8%9A%E0%B8%9A%E0%B9%80%E0%B8%87%E0%B8%B4%E0%B8%99%E0%B9%80%E0%B8%94%E0%B8%B7%E0%B8%AD%E0%B8%99/user/index.php'">
        <i class="bi bi-x-circle me-1"></i> ยกเลิก
      </button>
    </div>
  </form>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
