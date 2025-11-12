<?php
// ...existing code...
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$conn = new mysqli('localhost', 'root', '', 'salary_system');
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

// ดึงข้อมูลบริษัทที่เลือก (session เก็บ tax_id)
$tax_id = $_SESSION['selected_company_tax_id'] ?? '';
$company = [];
if ($tax_id) {
    // เตรียม statement ให้ค้นโดยใช้ tax_id (ไม่ใช่ name)
    $stmt = $conn->prepare("SELECT * FROM companies WHERE tax_id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("s", $tax_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $company = $result ? $result->fetch_assoc() : [];
        $stmt->close();
    }
}
// รับข้อมูลจากฟอร์ม
mb_internal_encoding('UTF-8');
$firstName = isset($_POST['firstName']) ? trim($_POST['firstName']) : '';
$lastName  = isset($_POST['lastName']) ? trim($_POST['lastName']) : '';
$idCard    = isset($_POST['idCard']) ? preg_replace('/\s+/', '', trim($_POST['idCard'])) : '';
$payPeriod = isset($_POST['payPeriod']) ? trim($_POST['payPeriod']) : '';

// เก็บชื่อที่ผู้ใช้ส่งมาไว้เพื่อใช้ตรวจว่าให้ payload ทับหรือไม่
$submittedFullname = trim($firstName . ' ' . $lastName);

// ตรวจสอบข้อมูล
if ($firstName === '' || $lastName === '' || $idCard === '' || $payPeriod === '') {
	header('Location: index.php');
	exit;
}

// --- เริ่ม: ดึงข้อมูลพนักงานจากตาราง employees ตาม idCard และ company tax_id ---
// เปลี่ยน: ใช้คอลัมน์ `idcard` (ตรงกับโครงสร้าง employees.sql)
$emp_fullname = $submittedFullname;
$emp_nickname = '';
$emp_position = '-';
$emp_status = 'ไม่ระบุ';

// เพิ่มการประกาศค่าเริ่มต้นของ salary/savings ก่อนการค้นหา employees
$salary = 0.00;
$savings = 0.00;

// company tax id ที่ใช้ค้น (จาก session/DB)
$company_tax = $company['tax_id'] ?? $tax_id ?? '';

// หากมี idCard ให้ค้นในตาราง employees
if (!empty($idCard)) {
	// หยั่งเชิงโดยใช้ idcard + company_tax_id ก่อน
	if (!empty($company_tax)) {
		// เพิ่ม column salary
		$stmt = $conn->prepare("SELECT fullname, nickname, position, status, salary FROM employees WHERE idcard = ? AND company_tax_id = ? LIMIT 1");
		if ($stmt) {
			$stmt->bind_param('ss', $idCard, $company_tax);
			$stmt->execute();
			$res = $stmt->get_result();
			if ($res && ($row = $res->fetch_assoc())) {
				$emp_fullname = !empty($row['fullname']) ? $row['fullname'] : $emp_fullname;
				$emp_nickname = $row['nickname'] ?? '';
				$emp_position = $row['position'] ?? '-';
				$emp_status = $row['status'] ?? 'ไม่ระบุ';
				// ดึง salary มาใช้เป็นเงินสะสม (ตามคำขอ)
				if (isset($row['salary']) && $row['salary'] !== null && $row['salary'] !== '') {
					$salary = (float)$row['salary'];
					$savings = $salary;
				}
			}
			$stmt->close();
		}
	}
	// หากยังไม่พบ ให้ fallback ค้นเฉพาะ idcard
	if ($emp_nickname === '' && ($emp_position === '-' || $emp_status === 'ไม่ระบุ')) {
		// เพิ่ม column salary ใน fallback
		$stmt2 = $conn->prepare("SELECT fullname, nickname, position, status, salary FROM employees WHERE idcard = ? LIMIT 1");
		if ($stmt2) {
			$stmt2->bind_param('s', $idCard);
			$stmt2->execute();
			$res2 = $stmt2->get_result();
			if ($res2 && ($row2 = $res2->fetch_assoc())) {
				$emp_fullname = !empty($row2['fullname']) ? $row2['fullname'] : $emp_fullname;
				$emp_nickname = $row2['nickname'] ?? '';
				$emp_position = $row2['position'] ?? '-';
				$emp_status = $row2['status'] ?? 'ไม่ระบุ';
				if (isset($row2['salary']) && $row2['salary'] !== null && $row2['salary'] !== '') {
					$salary = (float)$row2['salary'];
					$savings = $salary;
				}
			}
			$stmt2->close();
		}
	}
}

if (!empty($idCard) || !empty($company_tax)) {
	$like = '%' . $conn->real_escape_string($idCard) . '%';
	$stmtd = $conn->prepare("SELECT payload FROM `data` WHERE ref_table = 'advance_payments' AND payload LIKE ? ORDER BY updated_at DESC LIMIT 1");
	if ($stmtd) {
		$stmtd->bind_param('s', $like);
		$stmtd->execute();
		$resd = $stmtd->get_result();
		if ($resd && ($rowd = $resd->fetch_assoc())) {
			$payload = $rowd['payload'];
			$json = json_decode($payload, true);
			if (is_array($json)) {
				// ใช้ payload เป็น fallback เท่านั้น: ถ้าค่าปัจจุบันว่างหรือยังเป็นค่าที่ผู้ใช้ส่งมา (meaning employees ไม่พบ) ให้เติมจาก payload
				// ตัวอย่าง payload keys: fullname, nickname, position, status ...
				if (!empty($json['fullname']) && (empty($emp_fullname) || $emp_fullname === $submittedFullname)) {
					$emp_fullname = $json['fullname'];
				}
				if (!empty($json['nickname']) && (empty($emp_nickname))) {
					$emp_nickname = $json['nickname'];
				}
				if (!empty($json['position']) && ($emp_position === '-' || empty($emp_position))) {
					$emp_position = $json['position'];
				}
				if (!empty($json['status']) && ($emp_status === 'ไม่ระบุ' || empty($emp_status))) {
					$emp_status = $json['status'];
				}

				// หาก payload มีคีย์ salary และเราไม่มีค่า salary จาก employees ให้ใช้ค่าใน payload เป็น fallback
				if ((empty($salary) || $salary == 0.00) && isset($json['salary']) && $json['salary'] !== '') {
					$salary = (float)str_replace(',', '', $json['salary']);
					$savings = $salary;
				}

				// หาก payload มี employee_id แต่เราไม่มีรายละเอียดจริงจาก employees ให้พยายามดึงโดย id
				if ((empty($emp_nickname) || $emp_position === '-' || $emp_status === 'ไม่ระบุ' || $emp_fullname === $submittedFullname) && !empty($json['employee_id'])) {
					$eid = (int)$json['employee_id'];
					$stmtE = $conn->prepare("SELECT fullname, nickname, position, status, salary FROM employees WHERE id = ? LIMIT 1");
					if ($stmtE) {
						$stmtE->bind_param('i', $eid);
						$stmtE->execute();
						$resE = $stmtE->get_result();
						if ($resE && ($rE = $resE->fetch_assoc())) {
							if (!empty($rE['fullname']))  $emp_fullname = $rE['fullname'];
							if (!empty($rE['nickname']))  $emp_nickname = $rE['nickname'];
							if (!empty($rE['position']))  $emp_position = $rE['position'];
							if (!empty($rE['status']))    $emp_status   = $rE['status'];
							if (isset($rE['salary']) && $rE['salary'] !== null && $rE['salary'] !== '') {
								$salary = (float)$rE['salary'];
								$savings = $salary;
							}
						}
						$stmtE->close();
					}
				}
			}
		}
		$stmtd->close();
	}
}
// ฟังก์ชันช่วยแปลงวันที่เป็นรูปแบบไทยสั้น เช่น "5 ต.ค. 68"
function thaiDateShort($timestamp = null) {
    $months = ['ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
    $t = $timestamp ? $timestamp : time();
    $d = (int)date('j', $t);
    $m = (int)date('n', $t);
    $y = (int)date('y', $t); // สองหลัก
    return $d . ' ' . $months[$m-1] . ' ' . $y;
}

// หาไฟล์ฟอนต์ที่รองรับภาษาไทย (เพิ่มเส้นทางที่เป็นไปได้)
$possibleFonts = [
	__DIR__ . '/fonts/THSarabunNew.ttf',
	'C:\\Windows\\Fonts\\THSarabunNew.ttf',
	'C:\\Windows\\Fonts\\THSarabun.ttf',
	'C:\\Windows\\Fonts\\ARIAL.TTF',
	'/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
	'/usr/share/fonts/truetype/freefont/FreeSans.ttf'
];
$fontPath = null;
foreach ($possibleFonts as $f) {
	if (file_exists($f) && is_readable($f)) { $fontPath = $f; break; }
}

// --- เพิ่มฟังก์ชันช่วยตรวจ/คัดลอกฟอนต์ไปยัง temp เพื่อหลีกเลี่ยงปัญหา path ที่มีอักขระพิเศษ ---
function ensure_font_path($path) {
	if (empty($path) || !file_exists($path)) return null;
	// ถ้าไฟล์อ่านได้ตรงๆ ให้ลองใช้ก่อน
	if (is_readable($path)) {
		// แต่บางครั้ง GD/FreeType บน Windows มีปัญหากับพาธที่มีอักขระพิเศษ -> คัดลอกไป temp และใช้ชื่อ ASCII
		$tempDir = sys_get_temp_dir();
		$base = basename($path);
		$safeBase = 'webfont_' . preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $base);
		$tempPath = $tempDir . DIRECTORY_SEPARATOR . $safeBase;
		// คัดลอกเฉพาะเมื่อยังไม่มีหรือขนาดไม่ตรงกัน
		$needCopy = true;
		if (file_exists($tempPath) && is_readable($tempPath) && filesize($tempPath) > 0) {
			if (filesize($tempPath) === filesize($path)) $needCopy = false;
		}
		if ($needCopy) {
			@copy($path, $tempPath);
			// หาก copy ล้มเหลว พยายามอ่าน/เขียนแบบ stream
			if ((!file_exists($tempPath) || filesize($tempPath) === 0) && @is_readable($path)) {
				$in = @fopen($path, 'rb');
				$out = @fopen($tempPath, 'wb');
				if ($in && $out) {
					while (!feof($in)) {
						fwrite($out, fread($in, 8192));
					}
					fclose($in); fclose($out);
				}
			}
		}
		// ถ้าไฟล์ temp ใช้งานได้ ให้ใช้ temp path
		if (file_exists($tempPath) && is_readable($tempPath) && filesize($tempPath) > 0) {
			return $tempPath;
		}
		// ถ้าไม่สำเร็จ ให้ fallback กลับไปใช้พาธเดิม (ถ้าอ่านได้)
		if (is_readable($path)) return $path;
	}
	return null;
}

// เรียกใช้ฟังก์ชันเพื่อให้ได้พาธฟอนต์ที่แน่นอน
$fontPath = ensure_font_path($fontPath);

// ตรวจสอบเบื้องต้น: GD + FreeType และ font file ต้องใช้ได้
if (!function_exists('imagettfbbox') || !function_exists('imagettftext')) {
	header('Content-Type: text/html; charset=UTF-8');
	echo '<h3>GD/FreeType ไม่ถูกเปิดใช้งาน</h3>';
	echo '<p>โปรดเปิดใช้งานส่วนขยาย GD พร้อม FreeType ใน PHP (php_gd) แล้วลองใหม่</p>';
	exit;
}

if (!$fontPath) {
	header('Content-Type: text/html; charset=UTF-8');
	echo '<!doctype html><html lang="th"><head><meta charset="utf-8"><title>ต้องมีฟอนต์ไทย</title></head><body style="font-family:arial, sans-serif;margin:40px;">';
	echo '<h3>ไม่พบฟอนต์ TTF ที่รองรับภาษาไทยบนเซิร์ฟเวอร์ หรือไม่สามารถอ่านไฟล์ได้</h3>';
	echo '<p>วางไฟล์ฟอนต์ภาษาไทย (เช่น THSarabunNew.ttf) ไว้ที่ <code>' . htmlspecialchars(__DIR__ . '/fonts/') . '</code> หรือที่เส้นทาง ASCII ที่ PHP อ่านได้</p>';
	echo '<p>ตรวจสอบสิทธิ์การอ่านไฟล์, ชื่อไฟล์/พาธเป็น ASCII หรือลองวางไฟล์ในโฟลเดอร์ temp, และบันทึกไฟล์ PHP เป็น UTF-8 (ไม่มี BOM)</p>';
	echo '<p>หากใช้ Windows: ตรวจสอบว่า Apache/PHP รันด้วยสิทธิ์ที่สามารถอ่านไฟล์ได้</p>';
	echo '</body></html>';
	exit;
}

// --- helper: วาดข้อความด้วย TTF (ไม่แปลง encoding) ---
function draw_ttf_text($im, $size, $angle, $x, $y, $color, $fontFile, $text) {
	// ส่งเป็น UTF-8 ตรง ๆ ให้ imagettftext — หากฟอนต์รองรับไทยและไฟล์อ่านได้ จะไม่เพี้ยน
	@imagettftext($im, $size, $angle, $x, $y, $color, $fontFile, $text);
}
// --- จบ helper ---

// สร้างภาพ
$width  = 1100;
$height = 900; // เพิ่มความสูงเพื่อรองรับเนื้อหาใหม่
$im = imagecreatetruecolor($width, $height);

// สี
$bg = imagecolorallocate($im, 234, 246, 251);
$panel = imagecolorallocate($im, 255, 255, 255);
$accent = imagecolorallocate($im, 0, 150, 136);
$textColor = imagecolorallocate($im, 33, 33, 33);
$muted = imagecolorallocate($im, 120, 138, 139);

// เติมพื้นหลังและกล่อง
imagefilledrectangle($im, 0, 0, $width, $height, $bg);
$pad = 36;
imagefilledrectangle($im, $pad, $pad, $width-$pad, $height-$pad, $panel);
imagerectangle($im, $pad, $pad, $width-$pad, $height-$pad, $accent);

// หัวเรื่องและงวด (ตัวอย่าง)
// เปลี่ยนให้ใช้ชื่อบริษัทจาก DB ถ้ามี หากไม่มีค่อย fallback เป็นข้อความตัวอย่าง
$company = $company['name'] ?? 'บริษัท ตัวอย่าง จำกัด สาขา00001';
$subtitle = 'สลิปเงินเดือนประจำงวด: ' . $payPeriod;
$headerFontSize = 34;
$subFontSize = 20;

// ตรวจสอบว่า imagettfbbox ทำงานได้กับฟอนต์นี้ก่อนใช้ (ไม่ให้เกิด warning/fatal)
$bbox = @imagettfbbox($headerFontSize, 0, $fontPath, $company);
if ($bbox === false) {
	header('Content-Type: text/html; charset=UTF-8');
	echo '<!doctype html><html lang="th"><head><meta charset="utf-8"><title>ฟอนต์ไม่สามารถเปิดได้</title></head><body style="font-family:arial, sans-serif;margin:40px;">';
	echo '<h3>ไม่สามารถเปิดไฟล์ฟอนต์สำหรับการวาดข้อความได้</h3>';
	echo '<p>ตรวจสอบว่าไฟล์ฟอนต์ <code>' . htmlspecialchars($fontPath) . '</code> เป็นไฟล์ .ttf ที่ถูกต้อง และ PHP/Apache สามารถอ่านไฟล์ได้ (permissions)</p>';
	echo '<p>ถ้าชื่อโฟลเดอร์หรือพาธมีอักขระไทย ให้ลองวางไฟล์ฟอนต์ไว้ในโฟลเดอร์ที่มีชื่อ ASCII เช่น <code>' . htmlspecialchars(__DIR__ . '/fonts/') . '</code></p>';
	echo '<p>ตรวจสอบด้วยว่าไฟล์ PHP นี้ถูกบันทึกเป็น UTF-8 (no BOM)</p>';
	echo '</body></html>';
	exit;
}

// หากถึงตรงนี้ imagettfbbox ใช้ได้ ให้วาดต่อ (ตัวอย่างย่อ)
$bbox = imagettfbbox($headerFontSize, 0, $fontPath, $company);
$textWidth = $bbox[2] - $bbox[0];
$centerX = 1100 / 2;
draw_ttf_text($im, $headerFontSize, 0, $centerX - ($textWidth/2), 36 + 70, $textColor, $fontPath, $company);
draw_ttf_text($im, $subFontSize, 0, $centerX - (($bbox[2]-$bbox[0])/2), 36 + 110, $muted, $fontPath, $subtitle);

// เตรียมข้อมูลเพื่อแสดง (จัดลำดับตามตัวอย่าง)
$left = [
    'ชื่อ-นามสกุล:' => $emp_fullname,
    'ชื่อเล่น:' => $emp_nickname,
    'วันที่จ่าย:' => thaiDateShort()
];
$right = [
    'ตำแหน่ง:' => $emp_position,
    // แสดงจากคอลัมน์ salary ของ employees (ค่าได้มาจากการค้นหา DB หรือ payload fallback)
    'เงินสะสม:' => number_format((float)$salary, 2) . ' บาท',
    'สถานะ:' => $emp_status
];

// เริ่มวาดคอลัมน์ข้อมูล
$labelSize = 20;
$valueSize = 20;
$startY = $pad + 160;
$lineHeight = 48;
$leftXLabel = $pad + 40;
$leftXValue = $leftXLabel + 220;
$rightXLabel = $width/2 + 20;
$rightXValue = $rightXLabel + 220;

$y = $startY;
foreach ($left as $label => $value) {
	draw_ttf_text($im, $labelSize, 0, $leftXLabel, $y, $accent, $fontPath, $label);
	draw_ttf_text($im, $valueSize, 0, $leftXValue, $y, $textColor, $fontPath, $value);
	$y += $lineHeight;
}

$y = $startY;
foreach ($right as $label => $value) {
	draw_ttf_text($im, $labelSize, 0, $rightXLabel, $y, $accent, $fontPath, $label);
	draw_ttf_text($im, $valueSize, 0, $rightXValue, $y, $textColor, $fontPath, $value);
	$y += $lineHeight;
}

// เพิ่มตัวแปรสำหรับรายรับ-รายจ่าย
$base_salary = 0.00;  // เงินเดือนพื้นฐาน (salary_workdays.total_amount)
$diligence = 0.00;    // เบี้ยขยัน (salary_adjustments.net_amount)
$advance = 0.00;      // เบิกล่วงหน้า (advance_payments.amount)

// ดึงข้อมูลเงินเดือนพื้นฐานจาก salary_workdays
if (!empty($idCard) && !empty($company_tax)) {
    $month = date('Y-m'); // เดือนปัจจุบันหรือจาก payPeriod
    $stmt = $conn->prepare("SELECT total_amount FROM salary_workdays WHERE employee_id = ? AND company_tax_id = ? AND month = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('iss', $eid, $company_tax, $month);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $base_salary = (float)$row['total_amount'];
        }
        $stmt->close();
    }

    // ดึงข้อมูลเบี้ยขยันจาก salary_adjustments
    $stmt = $conn->prepare("SELECT net_amount FROM salary_adjustments WHERE employee_id = ? AND company_tax_id = ? AND month = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('iss', $eid, $company_tax, $month);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $diligence = (float)$row['net_amount'];
        }
        $stmt->close();
    }

    // ดึงข้อมูลเบิกล่วงหน้าจาก advance_payments
    $stmt = $conn->prepare("SELECT amount FROM advance_payments WHERE employee_id = ? AND company_tax_id = ? AND DATE_FORMAT(advance_date, '%Y-%m') = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('iss', $eid, $company_tax, $month);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $advance = (float)$row['amount'];
        }
        $stmt->close();
    }
}

// คำนวณประกันสังคม 5%
$social_security = $base_salary * 0.05;

// คำนวณยอดรวม
$total_income = $base_salary + $diligence;
$total_deduction = $advance + $social_security;
$net_amount = $total_income - $total_deduction;

// วาดส่วนรายรับ (เพิ่มเติม)
$startY = $pad + 350; // เริ่มต่อจากส่วนข้อมูลเดิม
draw_ttf_text($im, 24, 0, $leftXLabel, $startY, $accent, $fontPath, "รายการรับ");
$startY += 40;

$income_items = [
    'เงินเดือนพื้นฐาน:' => $base_salary,
    'เบี้ยขยัน:' => $diligence
];

foreach ($income_items as $label => $amount) {
    draw_ttf_text($im, $labelSize, 0, $leftXLabel, $startY, $textColor, $fontPath, $label);
    draw_ttf_text($im, $valueSize, 0, $leftXValue + 100, $startY, $textColor, $fontPath, number_format($amount, 2));
    $startY += 35;
}

// วาดส่วนรายหัก
$startY = $pad + 350;
draw_ttf_text($im, 24, 0, $rightXLabel, $startY, $accent, $fontPath, "รายการหัก");
$startY += 40;

$deduction_items = [
    'เบิกล่วงหน้า:' => $advance,
    'ประกันสังคม (5%):' => $social_security
];

foreach ($deduction_items as $label => $amount) {
    draw_ttf_text($im, $labelSize, 0, $rightXLabel, $startY, $textColor, $fontPath, $label);
    draw_ttf_text($im, $valueSize, 0, $rightXValue + 100, $startY, $textColor, $fontPath, number_format($amount, 2));
    $startY += 35;
}

// วาดเส้นคั่น
$lineY = $startY + 20;
imageline($im, $pad + 40, $lineY, $width - $pad - 40, $lineY, $accent);

// แสดงยอดรวมสุทธิ
$startY = $lineY + 40;
draw_ttf_text($im, 24, 0, $centerX - 200, $startY, $accent, $fontPath, "ยอดรับสุทธิ");
draw_ttf_text($im, 24, 0, $centerX + 100, $startY, $accent, $fontPath, number_format($net_amount, 2) . " บาท");

// เพิ่มการแสดงรายการรวม
$startY += 50; // เพิ่มระยะห่างจากรายการด้านบน

// แสดงรวมรายรับ
draw_ttf_text($im, 20, 0, $leftXLabel, $startY, $accent, $fontPath, "รวมรายรับ:");
draw_ttf_text($im, 20, 0, $leftXValue + 100, $startY, $accent, $fontPath, number_format($total_income, 2) . " บาท");

// แสดงรวมรายหัก
draw_ttf_text($im, 20, 0, $rightXLabel, $startY, $accent, $fontPath, "รวมรายหัก:");
draw_ttf_text($im, 20, 0, $rightXValue + 100, $startY, $accent, $fontPath, number_format($total_deduction, 2) . " บาท");

// เส้นคั่นก่อนแสดงยอดสุทธิ
$lineY = $startY + 30;
imageline($im, $pad + 40, $lineY, $width - $pad - 40, $lineY, $accent);

// แสดงเงินได้สุทธิ
$startY = $lineY + 40;
draw_ttf_text($im, 24, 0, $centerX - 150, $startY, $accent, $fontPath, "เงินได้สุทธิ:");
draw_ttf_text($im, 24, 0, $centerX + 50, $startY, $accent, $fontPath, number_format($net_amount, 2) . " บาท");

// หมายเหตุด้านล่าง
$note = 'หมายเหตุ: ข้อมูลตัวอย่าง หากต้องการข้อมูลจริงให้เชื่อมต่อฐานข้อมูลและส่งค่ากลับ';
draw_ttf_text($im, 14, 0, $pad + 40, $height - $pad - 30, $muted, $fontPath, $note);

// ส่งผลลัพธ์เป็น PNG ให้ดาวน์โหลด
header('Content-Type: image/png');
$filename = 'salary_' . preg_replace('/[^0-9A-Za-z_\-]/', '_', $idCard) . '.png';
header('Content-Disposition: attachment; filename="' . $filename . '"');
imagepng($im);
imagedestroy($im);
exit;
