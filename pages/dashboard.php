<?php
// pages/dashboard.php

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

// ถ้ายังไม่ล็อกอิน ให้ไปหน้า login
if (!isLoggedIn()) {
    redirect('login.php');
}

// ฟังก์ชันช่วยดึงข้อมูลสรุปของเดือนนี้
function getMonthlySummary(PDO $pdo, int $year, int $month)
{
    // 1) จำนวนต้นไม้ที่เพิ่มเข้ามาในเดือนนี้
    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS count_added
        FROM trees
        WHERE YEAR(added_at) = :year
          AND MONTH(added_at) = :month
    ");
    $stmt->execute(['year' => $year, 'month' => $month]);
    $countAdded = (int)$stmt->fetch(PDO::FETCH_ASSOC)['count_added'];

    // 2) จำนวนต้นไม้ที่ตายในเดือนนี้ (SUM จากฟิลด์ died ของต้นไม้ที่มีวันที่ added_at ภายในเดือนนี้)
    //    สมมติว่า `died` คือจำนวนต้นไม้ที่ตายสะสม ณ ปัจจุบัน
    //    เราอาจประเมินว่า “ต้นไม้ที่ตายในเดือนนี้” เท่ากับ SUM(died) ในต้นไม้ที่อัปเดตข้อมูลภายในเดือนนั้น
    //    แต่ในกรณีฐานข้อมูลไม่มี timestamp อัปเดต `died` แยก เราจะใช้ค่า SUM(died) ของต้นไม้ที่ added_at ในเดือนนั้น
    $stmt = $pdo->prepare("
        SELECT SUM(died) AS sum_died
        FROM trees
        WHERE YEAR(added_at) = :year
          AND MONTH(added_at) = :month
    ");
    $stmt->execute(['year' => $year, 'month' => $month]);
    $sumDied = (int)$stmt->fetch(PDO::FETCH_ASSOC)['sum_died'];

    // 3) จำนวนต้นไม้ที่ขายในเดือนนี้ (SUM(sold) ของต้นไม้ที่ added_at ในเดือนนี้)
    $stmt = $pdo->prepare("
        SELECT SUM(sold) AS sum_sold
        FROM trees
        WHERE YEAR(added_at) = :year
          AND MONTH(added_at) = :month
    ");
    $stmt->execute(['year' => $year, 'month' => $month]);
    $sumSold = (int)$stmt->fetch(PDO::FETCH_ASSOC)['sum_sold'];

    // 4) ยอดรายรับในเดือนนี้ (SUM total_price จาก orders)
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(total_price), 0) AS income
        FROM orders
        WHERE YEAR(created_at) = :year
          AND MONTH(created_at) = :month
    ");
    $stmt->execute(['year' => $year, 'month' => $month]);
    $income = (float)$stmt->fetch(PDO::FETCH_ASSOC)['income'];

    // 5) ยอดรายจ่ายในเดือนนี้ (สมมติว่าไม่มีตาราง expenses หากคุณมีตารางรายจ่ายโปรดปรับ query ตรงนี้)
    //    ตอนนี้ให้ตั้งค่า default เป็น 0 (หรือคุณอาจสร้างตาราง expenses เพิ่มในภายหลัง)
    $expense = 0.00;

    return [
        'added'   => $countAdded,
        'died'    => $sumDied,
        'sold'    => $sumSold,
        'income'  => $income,
        'expense' => $expense
    ];
}

// กำหนดปีและเดือนปัจจุบัน
$currentYear  = (int)date('Y');
$currentMonth = (int)date('m');

// ดึงข้อมูลสรุปของเดือนนี้
$summaryThisMonth = getMonthlySummary($pdo, $currentYear, $currentMonth);

// เตรียมข้อมูลกราฟย้อนหลัง 12 เดือน (ช่วงนี้เริ่มตั้งแต่เดือนปัจจุบันถอยหลัง 11 เดือน)
$labels          = [];
$dataIncome      = [];
$dataExpense     = [];
$dataCountAdded  = [];
$dataSumDied     = [];
$dataSumSold     = [];

for ($i = 11; $i >= 0; $i--) {
    $dt = new DateTime("first day of -{$i} month");
    $y  = (int)$dt->format('Y');
    $m  = (int)$dt->format('m');
    $labels[] = $dt->format('M Y'); // เช่น “Jul 2024”, “Aug 2024” เป็นต้น

    $monthSummary = getMonthlySummary($pdo, $y, $m);
    $dataIncome[]     = $monthSummary['income'];
    $dataExpense[]    = $monthSummary['expense'];
    $dataCountAdded[] = $monthSummary['added'];
    $dataSumDied[]    = $monthSummary['died'];
    $dataSumSold[]    = $monthSummary['sold'];
}

// แปลงเป็น JSON เพื่อใช้ใน JavaScript (Chart.js)
$labelsJson         = json_encode($labels, JSON_UNESCAPED_UNICODE);
$dataIncomeJson     = json_encode($dataIncome);
$dataExpenseJson    = json_encode($dataExpense);
$dataCountAddedJson = json_encode($dataCountAdded);
$dataSumDiedJson    = json_encode($dataSumDied);
$dataSumSoldJson    = json_encode($dataSumSold);

?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="container">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="mb-0"><i class="bi bi-speedometer2 me-2 text-primary"></i>แดชบอร์ดสรุปประจำเดือน <?= date('F Y') ?></h2>
                <div>
                    <button class="btn btn-sm btn-outline-success">
                        <i class="bi bi-download me-1"></i> ส่งออกรายงาน
                    </button>
                </div>
            </div>
            <hr class="my-2">
        </div>
    </div>

    <!-- สรุปไฮไลต์ -->
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-5 g-4 mb-4">
        <!-- ต้นไม้ที่เพิ่มเข้ามา -->
        <div class="col">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-success bg-opacity-10 p-3 me-3">
                            <i class="bi bi-plus-circle-fill text-success fs-4"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">ต้นไม้เพิ่ม</h6>
                            <h3 class="mb-0"><?= $summaryThisMonth['added'] ?></h3>
                            <small class="text-muted">เดือนนี้</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- ต้นไม้ที่ตาย -->
        <div class="col">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-warning bg-opacity-10 p-3 me-3">
                            <i class="bi bi-x-circle-fill text-warning fs-4"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">ต้นไม้ตาย</h6>
                            <h3 class="mb-0"><?= $summaryThisMonth['died'] ?></h3>
                            <small class="text-muted">เดือนนี้</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- ต้นไม้ที่ขาย -->
        <div class="col">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-info bg-opacity-10 p-3 me-3">
                            <i class="bi bi-bag-check-fill text-info fs-4"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">ต้นไม้ขาย</h6>
                            <h3 class="mb-0"><?= $summaryThisMonth['sold'] ?></h3>
                            <small class="text-muted">เดือนนี้</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- รายรับ -->
        <div class="col">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-success bg-opacity-10 p-3 me-3">
                            <i class="bi bi-cash-stack text-success fs-4"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">รายรับ</h6>
                            <h3 class="mb-0">฿<?= number_format($summaryThisMonth['income'], 0) ?></h3>
                            <small class="text-muted">เดือนนี้</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- รายจ่าย (ถ้ามี) -->
        <div class="col">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-danger bg-opacity-10 p-3 me-3">
                            <i class="bi bi-credit-card text-danger fs-4"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">รายจ่าย</h6>
                            <h3 class="mb-0">฿<?= number_format($summaryThisMonth['expense'], 0) ?></h3>
                            <small class="text-muted">เดือนนี้</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <!-- กราฟรายรับ vs รายจ่าย ย้อนหลัง 12 เดือน -->
        <div class="col-lg-6 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-graph-up text-primary me-2"></i>รายรับ - รายจ่าย
                    </h5>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-light" type="button" id="incomeDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-three-dots-vertical"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="incomeDropdown">
                            <li><a class="dropdown-item" href="#"><i class="bi bi-download me-2"></i>ดาวน์โหลด</a></li>
                            <li><a class="dropdown-item" href="#"><i class="bi bi-fullscreen me-2"></i>ขยายกราฟ</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="#"><i class="bi bi-info-circle me-2"></i>ข้อมูลเพิ่มเติม</a></li>
                        </ul>
                    </div>
                </div>
                <div class="card-body">
                    <canvas id="incomeExpenseChart" height="240"></canvas>
                </div>
            </div>
        </div>

        <!-- กราฟสถิติ ต้นไม้เพิ่ม/ตาย/ขาย ย้อนหลัง 12 เดือน -->
        <div class="col-lg-6 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-bar-chart-fill text-primary me-2"></i>สถิติต้นไม้
                    </h5>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-light" type="button" id="treesDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-three-dots-vertical"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="treesDropdown">
                            <li><a class="dropdown-item" href="#"><i class="bi bi-download me-2"></i>ดาวน์โหลด</a></li>
                            <li><a class="dropdown-item" href="#"><i class="bi bi-fullscreen me-2"></i>ขยายกราฟ</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="#"><i class="bi bi-info-circle me-2"></i>ข้อมูลเพิ่มเติม</a></li>
                        </ul>
                    </div>
                </div>
                <div class="card-body">
                    <canvas id="treesStatChart" height="240"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- ข้อมูลสรุปต้นไม้ทั้งหมด -->
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                    <h5 class="card-title mb-0"><i class="bi bi-tree-fill text-success me-2"></i>ภาพรวมสวนไม้</h5>
                    <div>
                        <a href="/tree-manages/pages/trees.php" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-eye me-1"></i> ดูทั้งหมด
                        </a>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="p-4 text-center">
                        <div class="row g-4">
                            <div class="col-md-3">
                                <div class="border-end pe-2">
                                    <h4 class="mb-0" id="totalTrees">-</h4>
                                    <small class="text-muted">จำนวนต้นไม้ทั้งหมด</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="border-end pe-2">
                                    <h4 class="mb-0" id="availableTrees">-</h4>
                                    <small class="text-muted">จำนวนต้นไม้ที่พร้อมขาย</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="border-end pe-2">
                                    <h4 class="mb-0" id="totalSpecies">-</h4>
                                    <small class="text-muted">จำนวนสายพันธุ์</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div>
                                    <h4 class="mb-0" id="totalValue">-</h4>
                                    <small class="text-muted">มูลค่าประเมินทั้งหมด</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- โหลด Chart.js ผ่าน CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // ข้อมูลจาก PHP
    const labels = <?= $labelsJson ?>;
    const dataIncome     = <?= $dataIncomeJson ?>;
    const dataExpense    = <?= $dataExpenseJson ?>;
    const dataCountAdded = <?= $dataCountAddedJson ?>;
    const dataSumDied    = <?= $dataSumDiedJson ?>;
    const dataSumSold    = <?= $dataSumSoldJson ?>;

    // กำหนดสีให้สอดคล้องกับ theme
    Chart.defaults.font.family = "'Sarabun', sans-serif";
    Chart.defaults.color = '#6c757d';
    
    // กราฟรายรับ vs รายจ่าย
    const ctxIE = document.getElementById('incomeExpenseChart').getContext('2d');
    new Chart(ctxIE, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'รายรับ (฿)',
                    data: dataIncome,
                    borderColor: '#2e7d32',
                    backgroundColor: 'rgba(46, 125, 50, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.3,
                    pointBackgroundColor: '#2e7d32'
                },
                {
                    label: 'รายจ่าย (฿)',
                    data: dataExpense,
                    borderColor: '#d32f2f',
                    backgroundColor: 'rgba(211, 47, 47, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.3,
                    pointBackgroundColor: '#d32f2f'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    align: 'end'
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            if (context.parsed.y !== null) {
                                label += new Intl.NumberFormat('th-TH', { style: 'currency', currency: 'THB' }).format(context.parsed.y);
                            }
                            return label;
                        }
                    }
                }
            },
            scales: {
                x: { 
                    grid: {
                        display: false
                    }
                },
                y: {
                    display: true,
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return value.toLocaleString('th-TH') + ' ฿';
                        }
                    }
                }
            }
        }
    });

    // กราฟสถิติ ต้นไม้เพิ่ม/ตาย/ขาย
    const ctxTrees = document.getElementById('treesStatChart').getContext('2d');
    new Chart(ctxTrees, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'ต้นไม้เพิ่ม',
                    data: dataCountAdded,
                    backgroundColor: 'rgba(46, 125, 50, 0.7)',
                    borderColor: 'rgba(46, 125, 50, 1)',
                    borderWidth: 1
                },
                {
                    label: 'ต้นไม้ตาย',
                    data: dataSumDied,
                    backgroundColor: 'rgba(255, 152, 0, 0.7)',
                    borderColor: 'rgba(255, 152, 0, 1)',
                    borderWidth: 1
                },
                {
                    label: 'ต้นไม้ขาย',
                    data: dataSumSold,
                    backgroundColor: 'rgba(3, 169, 244, 0.7)',
                    borderColor: 'rgba(3, 169, 244, 1)',
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    align: 'end'
                },
                tooltip: {
                    mode: 'index',
                    intersect: false
                }
            },
            scales: {
                x: { 
                    stacked: true,
                    grid: {
                        display: false
                    }
                },
                y: {
                    stacked: true,
                    beginAtZero: true
                }
            }
        }
    });

    // ตัวอย่างการดึงข้อมูลสรุป (ในสถานการณ์จริงคุณต้องดึงข้อมูลเหล่านี้จากเซิร์ฟเวอร์)
    document.addEventListener('DOMContentLoaded', function() {
        // สมมติข้อมูล - ในกรณีจริงต้องใช้ AJAX เพื่อดึงข้อมูล
        document.getElementById('totalTrees').textContent = '423';
        document.getElementById('availableTrees').textContent = '369';
        document.getElementById('totalSpecies').textContent = '42';
        document.getElementById('totalValue').textContent = '฿ 236,500';
    });
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
