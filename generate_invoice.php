<?php
error_reporting(0);
if (ob_get_length()) {
    ob_clean();
}
ob_start();

require_once __DIR__ . '/vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

session_start();
if (!isLoggedIn()) {
    echo "คุณต้องล็อกอินเพื่อดาวน์โหลด PDF";
    exit;
}

$order_code = $_GET['code'] ?? '';
if (empty($order_code)) {
    redirect('orders.php');
}

$stmtOrder = $pdo->prepare("
    SELECT 
        o.id,
        o.order_code,
        o.created_at,
        o.extra_cost,
        o.transportation_fee,
        o.total_price,
        g.name AS garden_name,
        g.phone AS garden_phone,
        g.address AS garden_address,
        o.customer_name,
        o.customer_phone
    FROM orders o
    LEFT JOIN gardens g ON o.garden_id = g.id
    WHERE o.order_code = :ocode
    LIMIT 1
");
$stmtOrder->execute(['ocode' => $order_code]);
$order = $stmtOrder->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    redirect('orders.php');
}

$orderId = $order['id'];
$stmtItems = $pdo->prepare("
    SELECT *
    FROM order_items
    WHERE order_id = :order_id
    ORDER BY id ASC
");
$stmtItems->execute(['order_id' => $orderId]);
$orderItems = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

$subtotal = array_sum(array_column($orderItems, 'total_price'));

$currentDate = date('d/m/Y');
$thaiMonths = [
    1 => 'มกราคม',
    2 => 'กุมภาพันธ์',
    3 => 'มีนาคม',
    4 => 'เมษายน',
    5 => 'พฤษภาคม',
    6 => 'มิถุนายน',
    7 => 'กรกฎาคม',
    8 => 'สิงหาคม',
    9 => 'กันยายน',
    10 => 'ตุลาคม',
    11 => 'พฤศจิกายน',
    12 => 'ธันวาคม'
];
$day = date('j');
$month = $thaiMonths[(int) date('n')];
$year = date('Y') + 543;
$thaiDate = "$day $month $year";

$fontDir = realpath(__DIR__ . '/public/fonts/Chakra_Petch');
$regularFont = $fontDir . '/ChakraPetch-Regular.ttf';
$boldFont = $fontDir . '/ChakraPetch-Bold.ttf';

if (!file_exists($regularFont) || !file_exists($boldFont)) {
    echo "ไม่พบไฟล์ฟอนต์ ChakraPetch! กรุณาตรวจสอบใน public/fonts/Chakra_Petch/";
    exit;
}

$html = '
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <style>
        /* ===== ฟอนต์ Chakra Petch ===== */
        @font-face {
            font-family: "ChakraPetch";
            src: url("file://' . str_replace("\\", "/", $regularFont) . '") format("truetype");
            font-weight: normal;
            font-style: normal;
        }
        @font-face {
            font-family: "ChakraPetch";
            src: url("file://' . str_replace("\\", "/", $boldFont) . '") format("truetype");
            font-weight: bold;
            font-style: normal;
        }
        
        /* ===== สไตล์พื้นฐาน ===== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: "ChakraPetch", sans-serif;
            font-size: 12px;
            line-height: 1.5;
            color: #333;
            background-color: #fff;
        }
        
        .container {
            position: relative;
            width: 100%;
            max-width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            padding: 15mm 15mm;
            background-color: #fff;
        }
        
        /* ===== องค์ประกอบตกแต่ง ===== */
        .invoice-background {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -2;
            opacity: 0.04;
            background: radial-gradient(circle, #fff 10%, transparent 10%) 0 0,
            radial-gradient(circle, #fff 10%, transparent 10%) 8px 8px,
            radial-gradient(rgba(0, 0, 0, 0.1) 15%, transparent 20%) 0 1px,
            radial-gradient(rgba(0, 0, 0, 0.1) 15%, transparent 20%) 8px 9px;
            background-color: #f9f9f9;
            background-size: 16px 16px;
        }
        
        .page-border {
            position: absolute;
            top: 8mm;
            left: 8mm;
            right: 8mm;
            bottom: 8mm;
            border: 1px solid #ddd;
            border-radius: 2mm;
            z-index: -1;
        }
        
        .corner-decoration {
            position: absolute;
            width: 40mm;
            height: 40mm;
            opacity: 0.08;
            z-index: -1;
        }
        
        .corner-top-left {
            top: 5mm;
            left: 5mm;
            border-top: 5px solid #45b39d;
            border-left: 5px solid #45b39d;
            border-top-left-radius: 5mm;
        }
        
        .corner-top-right {
            top: 5mm;
            right: 5mm;
            border-top: 5px solid #3498db;
            border-right: 5px solid #3498db;
            border-top-right-radius: 5mm;
        }
        
        .corner-bottom-left {
            bottom: 5mm;
            left: 5mm;
            border-bottom: 5px solid #3498db;
            border-left: 5px solid #3498db;
            border-bottom-left-radius: 5mm;
        }
        
        .corner-bottom-right {
            bottom: 5mm;
            right: 5mm;
            border-bottom: 5px solid #45b39d;
            border-right: 5px solid #45b39d;
            border-bottom-right-radius: 5mm;
        }
        
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-30deg);
            font-size: 80px;
            opacity: 0.04;
            color: #000;
            font-weight: bold;
            white-space: nowrap;
            z-index: -1;
        }
        
        /* ===== เฮดเดอร์เอกสาร ===== */
        .invoice-header {
            position: relative;
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #3498db;
        }
        
        .company-logo {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }
        
        .logo-placeholder {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #45b39d, #3498db);
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 10px;
            color: white;
            font-weight: bold;
            font-size: 20px;
            text-shadow: 1px 1px 1px rgba(0,0,0,0.3);
        }
        
        .company-name {
            font-size: 18px;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
        
        .company-tagline {
            font-size: 10px;
            color: #666;
        }
        
        .document-details {
            text-align: right;
        }
        
        .document-type {
            display: inline-block;
            font-size: 28px;
            font-weight: bold;
            color: #3498db;
            margin-bottom: 10px;
            padding: 5px 15px;
            border-bottom: 2px solid #45b39d;
            border-radius: 5px 5px 0 0;
        }
        
        .document-number {
            font-size: 14px;
            margin-bottom: 5px;
            color: #333;
        }
        
        .document-number span {
            background: #f6f9fe;
            padding: 2px 8px;
            border-radius: 15px;
            font-weight: bold;
            color: #3498db;
            border: 1px dashed #3498db;
        }
        
        .document-date {
            font-size: 12px;
            color: #666;
        }
        
        /* ===== ข้อมูลธุรกิจและลูกค้า ===== */
        .info-container {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .info-box {
            flex: 1;
            border: 1px solid #e8e8e8;
            border-radius: 5px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .info-header {
            background: linear-gradient(to right, #3498db, #45b39d);
            color: white;
            padding: 8px 12px;
            font-weight: bold;
            font-size: 14px;
            border-bottom: 3px solid rgba(0,0,0,0.05);
        }
        
        .info-content {
            padding: 12px;
            background: #f9f9f9;
        }
        
        .info-row {
            margin-bottom: 5px;
            display: flex;
        }
        
        .info-row:last-child {
            margin-bottom: 0;
        }
        
        .info-label {
            flex: 1;
            font-weight: bold;
            color: #45b39d;
        }
        
        .info-value {
            flex: 2;
            color: #333;
        }
        
        /* ===== ตารางรายการสินค้า ===== */
        .items-section {
            margin-bottom: 25px;
        }
        
        .section-title {
            font-size: 16px;
            font-weight: bold;
            color: #3498db;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 2px solid #e8e8e8;
            position: relative;
        }
        
        .section-title:after {
            content: "";
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 60px;
            height: 2px;
            background-color: #45b39d;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            border-radius: 5px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .items-table thead th {
            background: linear-gradient(to right, #3498db, #45b39d);
            color: white;
            font-weight: bold;
            text-align: center;
            padding: 10px;
            font-size: 13px;
            border: none;
        }
        
        .items-table tbody td {
            padding: 10px;
            border: 1px solid #e8e8e8;
            text-align: center;
            color: #333;
            background-color: #fff;
            font-size: 12px;
        }
        
        .items-table tbody tr:nth-child(even) td {
            background-color: #f9f9f9;
        }
        
        .text-center {
            text-align: center;
        }
        
        .text-right {
            text-align: right;
        }
        
        .text-left {
            text-align: left;
        }
        
        /* ===== สรุปยอดรวม ===== */
        .summary-container {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 25px;
        }
        
        .summary-box {
            width: 45%;
            border: 1px solid #e8e8e8;
            border-radius: 5px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .summary-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .summary-table tr td {
            padding: 10px;
            border-bottom: 1px solid #e8e8e8;
        }
        
        .summary-table tr:last-child td {
            border-bottom: none;
        }
        
        .summary-label {
            font-weight: bold;
            color: #333;
            text-align: left;
        }
        
        .summary-value {
            text-align: right;
            font-weight: bold;
            color: #333;
        }
        
        .grand-total {
            background: linear-gradient(to right, #3498db, #45b39d);
            color: white !important;
        }
        
        .grand-total td {
            padding: 12px 10px;
            font-size: 14px;
            color: white !important;
        }
        
        /* ===== ส่วนท้ายและลายเซ็น ===== */
        .signature-section {
            display: flex;
            justify-content: space-between;
            margin-top: 40px;
        }
        
        .signature-box {
            width: 45%;
            text-align: center;
            position: relative;
        }
        
        .signature-line {
            margin: 40px auto 5px;
            width: 70%;
            border-bottom: 1px solid #333;
        }
        
        .signature-title {
            font-size: 13px;
            font-weight: bold;
            margin-bottom: 0;
            color: #333;
        }
        
        .signature-person {
            font-size: 11px;
            color: #666;
        }
        
        .invoice-footer {
            margin-top: 50px;
            padding-top: 10px;
            border-top: 1px dotted #ccc;
            text-align: center;
            font-size: 10px;
            color: #777;
        }
        
        .footer-text {
            margin-bottom: 5px;
        }
        
        .footer-notice {
            font-weight: bold;
            color: #3498db;
        }
        
        /* ===== ระบายสีและความสวยงาม ===== */
        .accent-bg {
            background-color: rgba(52, 152, 219, 0.05);
        }
        
        .highlight {
            background-color: #f9f4d4;
            padding: 2px 5px;
            border-radius: 3px;
            color: #333;
            border: 1px dashed #f0e68c;
        }
        
        .badge {
            display: inline-block;
            padding: 3px 7px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: bold;
            color: white;
            background: linear-gradient(to right, #3498db, #45b39d);
            margin-left: 5px;
        }
        
        .ribbon {
            position: absolute;
            top: 0;
            right: 30px;
            width: 40px;
            height: 60px;
            background-color: #3498db;
            color: white;
            font-size: 14px;
            text-align: center;
            text-transform: uppercase;
            padding-top: 15px;
            box-shadow: 0 5px 10px rgba(0,0,0,0.1);
            z-index: -1;
            opacity: 0.9;
        }
        
        .ribbon:after {
            content: "";
            position: absolute;
            bottom: -15px;
            left: 0;
            width: 0;
            height: 0;
            border-left: 20px solid transparent;
            border-right: 20px solid transparent;
            border-top: 15px solid #3498db;
        }
        
        /* ===== Responsive Design ===== */
        @page {
            size: A4;
            margin: 0;
        }
        
        @media print {
            .container {
                width: 100%;
                padding: 10mm;
                max-width: none;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- องค์ประกอบตกแต่ง -->
        <div class="invoice-background"></div>
        <div class="page-border"></div>
        <div class="corner-decoration corner-top-left"></div>
        <div class="corner-decoration corner-top-right"></div>
        <div class="corner-decoration corner-bottom-left"></div>
        <div class="corner-decoration corner-bottom-right"></div>
        <div class="watermark">OFFICIAL RECEIPT</div>
        <div class="ribbon">PAID</div>
        
        <!-- เฮดเดอร์เอกสาร -->
        <div class="invoice-header">
            <div class="company-logo">
                <div class="logo-placeholder">TS</div>
                <div class="company-name">ระบบบริหารจัดการสวนต้นไม้</div>
                <div class="company-tagline">คุณภาพเหนือระดับ บริการประทับใจ</div>
            </div>
            <div class="document-details">
                <div class="document-type">ใบเสร็จรับเงิน</div>
                <div class="document-number">เลขที่ <span>' . e($order['order_code']) . '</span></div>
                <div class="document-date">วันที่ออกเอกสาร: ' . $thaiDate . '</div>
            </div>
        </div>
        
        <!-- ข้อมูลธุรกิจและลูกค้า -->
        <div class="info-container">
            <div class="info-box">
                <div class="info-header">
                    <i>&#10042;</i> ข้อมูลสวน
                </div>
                <div class="info-content">
                    <div class="info-row">
                        <div class="info-label">ชื่อสวน:</div>
                        <div class="info-value">' . e($order['garden_name'] ?? 'ไม่ระบุ') . '</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">เบอร์โทรศัพท์:</div>
                        <div class="info-value">' . e($order['garden_phone'] ?? 'ไม่ระบุ') . '</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">ที่อยู่:</div>
                        <div class="info-value">' . e($order['garden_address'] ?? 'ไม่ระบุ') . '</div>
                    </div>
                </div>
            </div>
            
            <div class="info-box">
                <div class="info-header">
                    <i>&#10042;</i> ข้อมูลลูกค้า
                </div>
                <div class="info-content">
                    <div class="info-row">
                        <div class="info-label">ชื่อลูกค้า:</div>
                        <div class="info-value">' . e($order['customer_name']) . '</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">เบอร์โทรศัพท์:</div>
                        <div class="info-value">' . (e($order['customer_phone']) ?: 'ไม่ระบุ') . '</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">วันที่สั่งซื้อ:</div>
                        <div class="info-value highlight">' . date('d/m/Y', strtotime($order['created_at'])) . '</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- ตารางรายการสินค้า -->
        <div class="items-section">
            <div class="section-title">รายการสินค้า <span class="badge">สินค้าคุณภาพ</span></div>
            <table class="items-table">
                <thead>
                    <tr>
                        <th width="8%">ลำดับ</th>
                        <th width="42%">รายการต้นไม้</th>
                        <th width="12%">ขนาด</th>
                        <th width="13%">ราคา/ต้น</th>
                        <th width="10%">จำนวน</th>
                        <th width="15%">รวม (บาท)</th>
                    </tr>
                </thead>
                <tbody>';

foreach ($orderItems as $idx => $it) {
    $html .= '
                    <tr>
                        <td class="text-center">' . ($idx + 1) . '</td>
                        <td class="text-left">' . e($it['tree_name']) . '</td>
                        <td class="text-center">' . e($it['size']) . '</td>
                        <td class="text-right">' . number_format($it['unit_price'], 2) . '</td>
                        <td class="text-center">' . (int) $it['quantity'] . '</td>
                        <td class="text-right">' . number_format($it['total_price'], 2) . '</td>
                    </tr>';
}

$html .= '
                </tbody>
            </table>
        </div>
        
        <!-- สรุปยอดรวม -->
        <div class="summary-container">
            <div class="summary-box">
                <table class="summary-table">
                    <tr>
                        <td class="summary-label">ยอดรวมต้นไม้:</td>
                        <td class="summary-value">' . number_format($subtotal, 2) . ' บาท</td>
                    </tr>
                    <tr>
                        <td class="summary-label">ค่าใช้จ่ายเพิ่มเติม:</td>
                        <td class="summary-value">' . number_format($order['extra_cost'], 2) . ' บาท</td>
                    </tr>
                    <tr>
                        <td class="summary-label">ค่าขนส่ง:</td>
                        <td class="summary-value">' . number_format($order['transportation_fee'], 2) . ' บาท</td>
                    </tr>
                    <tr class="grand-total">
                        <td class="summary-label">ยอดรวมทั้งสิ้น:</td>
                        <td class="summary-value">' . number_format($order['total_price'], 2) . ' บาท</td>
                    </tr>
                </table>
            </div>
        </div>
        
        <!-- ส่วนลายเซ็น -->
        <div class="signature-section">
            <div class="signature-box">
                <div class="signature-title">ลงชื่อผู้รับเงิน</div>
                <div class="signature-line"></div>
                <div class="signature-person">(................................................)</div>
                <div class="signature-position">ผู้มีอำนาจลงนาม</div>
            </div>
            
            <div class="signature-box">
                <div class="signature-title">ลงชื่อผู้จ่ายเงิน</div>
                <div class="signature-line"></div>
                <div class="signature-person">(................................................)</div>
                <div class="signature-position">ผู้จ่ายเงิน</div>
            </div>
        </div>
        
        <!-- ส่วนท้าย -->
        <div class="invoice-footer">
            <div class="footer-text">เอกสารฉบับนี้ออกโดยระบบคอมพิวเตอร์ มีความถูกต้องตามกฎหมาย ไม่จำเป็นต้องมีลายเซ็นหรือประทับตรา</div>
            <div class="footer-notice">กรุณาเก็บเอกสารนี้ไว้เป็นหลักฐานการชำระเงิน</div>
            <div class="footer-text">ขอบคุณที่ใช้บริการ</div>
        </div>
    </div>
</body>
</html>
';

$options = new Options();
$options->set('defaultFont', 'ChakraPetch');
$options->setChroot([__DIR__, __DIR__ . '/public/fonts/Chakra_Petch']);
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');

$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$pdfOutput = $dompdf->output();
$pdfDir = __DIR__ . '/pdf';
if (!is_dir($pdfDir)) {
    mkdir($pdfDir, 0755, true);
}
$pdfFile = $pdfDir . '/bill_' . $order['order_code'] . '.pdf';
file_put_contents($pdfFile, $pdfOutput);

if (ob_get_length()) {
    ob_end_clean();
}
header('Content-Type: application/pdf; charset=UTF-8');
header('Content-Disposition: inline; filename="bill_' . e($order['order_code']) . '.pdf"');
echo $pdfOutput;
exit;
