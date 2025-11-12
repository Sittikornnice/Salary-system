<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>ระบบตรวจสอบเงินเดือน</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400&display=swap" rel="stylesheet">
  <style>
   body {
  display: flex;
  justify-content: center;
  align-items: center;
  background: #eaf6fb;
  font-family: 'Prompt', sans-serif;
  font-weight: 300;
  min-height: 100vh;
  overflow-y: hidden; /* ปิด scrollbar ด้านขวา */
  padding: 20px; /* ป้องกันไม่ให้ฟอร์มติดขอบจอเกินไป */
}

    .admin-btn {
      position: absolute;
      top: 30px;
      right: 40px;
      z-index: 10;
    }
      .form-container {
      width: 100%;
      max-width: 480px;
      background: #fff;
      padding: 44px 40px 40px 40px;
      border-radius: 18px;
      box-shadow: 0 8px 32px rgba(0, 176, 116, 0.08);
      border: none;
    }

    .form-title {
      text-align: center;
      margin-bottom: 8px;
      color: #009688;
      font-weight: 400;
      font-size: 1.35rem;
      letter-spacing: 1px;
    }
    .form-desc {
      text-align: center;
      color: #7b8a8b;
      font-size: 1rem;
      margin-bottom: 18px;
      font-weight: 300;
    }
    .form-label {
      font-weight: 300;
      color: #009688;
      letter-spacing: 0.5px;
      font-size: 1rem;
    }
    .form-control, .form-select {
      border-radius: 10px;
      border: 1.5px solid #e0f2f1;
      font-weight: 300;
      background: #f7fafc;
      font-size: 1rem;
      margin-bottom: 12px;
    }
    .form-control:focus, .form-select:focus {
      border-color: #26a69a;
      box-shadow: 0 0 0 0.15rem rgba(0, 150, 136, 0.10);
      background: #e0f2f1;
    }
    .btn-primary {
      background-color: #7be7c4;
      color: #00695c;
      border: none;
      font-weight: 400;
      font-size: 1.08rem;
      letter-spacing: 1px;
      border-radius: 8px;
      transition: background 0.2s;
    }
    .btn-primary:hover {
      background-color: #4dd0a1;
      color: #004d40;
    }
    .bi-credit-card-2-front {
      font-size: 2.2rem;
      color: #009688;
      display: block;
      margin: 0 auto 10px auto;
    }
    @media (max-width: 600px) {
      .form-container { padding: 24px 8px; }
      .admin-btn { right: 10px; top: 10px; }
    }
  </style>
</head>
<body>
  <a href="#" class="btn btn-outline-info admin-btn" data-bs-toggle="modal" data-bs-target="#adminLoginModal">
    <i class="bi bi-person-gear"></i> สำหรับผู้ดูแลระบบ
  </a>

  <!-- Modal สำหรับฟอร์มผู้ดูแลระบบ -->
  <div class="modal fade" id="adminLoginModal" tabindex="-1" aria-labelledby="adminLoginModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
                <!-- <div class="modal-header justify-content-center">
          <h5 class="modal-title text-center w-100" id="adminLoginModalLabel">เข้าสู่ระบบผู้ดูแล</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div> -->
        <div class="modal-body p-0">
          <iframe src="/ระบบเงินเดือน/admin/admin_login.php" style="border:none;width:100%;height:450px;"></iframe>
        </div>
      </div>
    </div>
  </div>
  <div class="form-container">
    <div class="text-center mb-2">
      <i class="bi bi-credit-card-2-front"></i>
    </div>
    <div class="form-title">ระบบตรวจสอบเงินเดือน</div>
    <div class="form-desc">ตรวจสอบข้อมูลการจ่ายเงินเดือนของคุณ</div>
    <form method="POST" action="salary_check.php">
      <input type="text" class="form-control" id="firstName" name="firstName" placeholder="ชื่อ" required>
      <input type="text" class="form-control" id="lastName" name="lastName" placeholder="นามสกุล" required>
      <input type="text" class="form-control" id="idCard" name="idCard" maxlength="13" placeholder="เลขบัตรประจำตัวประชาชน" required>
   <select class="form-select" id="payPeriod" name="payPeriod" required>
  <option value="">-- กรุณาเลือกงวด --</option>
  <?php
    $months = [
      'มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน',
      'กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'
    ];
    $currentYear = date('Y');
    $thaiYear = $currentYear + 543; // แปลงเป็นปีไทยอัตโนมัติ
    $currentMonth = date('n');
    $currentDay = date('j');
    // คำนวณจำนวนวันในแต่ละเดือนแบบอัตโนมัติ
    for ($i = 1; $i <= $currentMonth; $i++) {
      $monthName = $months[$i - 1];
      $endDay = cal_days_in_month(CAL_GREGORIAN, $i, $currentYear); // 29, 30, หรือ 31
      // งวด 1-15
      if ($i == $currentMonth && $currentDay >= 16) {
        echo "<option value='1-15/$i/$thaiYear' disabled style='color:#aaa;'>1-15 $monthName $thaiYear</option>";
      } else {
        echo "<option value='1-15/$i/$thaiYear'>1-15 $monthName $thaiYear</option>";
      }
      // งวด 16-29, 16-30 หรือ 16-31
      $periodLabel = "16-$endDay $monthName $thaiYear";
      $periodValue = "16-$endDay/$i/$thaiYear";
      if ($i == $currentMonth && $currentDay < 16) {
        echo "<option value='$periodValue' disabled style='color:#aaa;'>$periodLabel</option>";
      } else {
        echo "<option value='$periodValue'>$periodLabel</option>";
      }
    }
  ?>
</select>
      <button type="submit" class="btn btn-primary w-100 mt-2">
        <i class="bi bi-search"></i> ตรวจสอบข้อมูล
      </button>
    </form>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>