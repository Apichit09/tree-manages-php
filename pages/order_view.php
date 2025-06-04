<?php
// pages/order_view.php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

// 1) ตรวจล็อกอิน
if (!isLoggedIn()) {
    redirect('login.php');
}

// 2) รับพารามิเตอร์ order_code (code) จาก GET
if (!isset($_GET['code'])) {
    redirect('orders.php');
}
$order_code = $_GET['code'];

// ดึงข้อมูลออเดอร์โดยใช้ order_code
$stmtOrder = $pdo->prepare("
    SELECT o.*, g.name AS garden_name, g.phone AS garden_phone, g.address AS garden_address
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

// ดึงรายการ order_items
$stmtItems = $pdo->prepare("
    SELECT * 
    FROM order_items 
    WHERE order_id = :oid
");
$stmtItems->execute(['oid' => $order['id']]);
$orderItems = $stmtItems->fetchAll(PDO::FETCH_ASSOC);
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="container">
    <!-- Breadcrumb navigation -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="orders.php" class="text-decoration-none">ออเดอร์/บิล</a></li>
            <li class="breadcrumb-item active" aria-current="page">รายละเอียดบิล</li>
        </ol>
    </nav>
    
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex align-items-center justify-content-between flex-wrap">
                <div class="d-flex align-items-center mb-3 mb-md-0">
                    <h2 class="mb-0 me-3">
                        <i class="bi bi-receipt text-success me-2"></i>รายละเอียดบิล
                    </h2>
                    <span class="badge bg-light text-dark border fs-6 p-2">
                        <?= e($order['order_code']); ?>
                    </span>
                </div>
                <div class="d-flex">
                    <a href="orders.php" class="btn btn-outline-secondary me-2">
                        <i class="bi bi-arrow-left me-1"></i> กลับไปรายการบิล
                    </a>
                    <a href="../generate_invoice.php?code=<?= e($order['order_code']); ?>" target="_blank" 
                       class="btn btn-success">
                        <i class="bi bi-file-pdf me-1"></i> ดาวน์โหลด PDF
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <!-- ตารางรายการต้นไม้ -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-tree-fill text-success me-2"></i>รายการต้นไม้ในบิล
                        </h5>
                        <span class="badge bg-success"><?= count($orderItems) ?> รายการ</span>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0 table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3" style="width:5%;">#</th>
                                    <th style="width:30%;">ต้นไม้</th>
                                    <th style="width:15%;">ขนาด</th>
                                    <th class="text-end" style="width:15%;">ราคา/ต้น (฿)</th>
                                    <th class="text-center" style="width:15%;">จำนวน</th>
                                    <th class="text-end" style="width:15%;">ราคารวม (฿)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($orderItems)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-5">
                                            <i class="bi bi-inbox text-muted fs-1"></i>
                                            <p class="text-muted mt-3">ไม่มีรายการต้นไม้</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($orderItems as $idx => $it): ?>
                                        <tr>
                                            <td class="ps-3"><?= $idx + 1 ?></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <?php if (!empty($it['image_link'])): ?>
                                                    <div class="me-2">
                                                        <img src="<?= e($it['image_link']) ?>" 
                                                            class="rounded" alt="<?= e($it['tree_name']) ?>" 
                                                            style="width:40px; height:40px; object-fit:cover;">
                                                    </div>
                                                    <?php else: ?>
                                                    <div class="me-2 bg-light rounded d-flex align-items-center justify-content-center"
                                                        style="width:40px; height:40px;">
                                                        <i class="bi bi-tree text-muted"></i>
                                                    </div>
                                                    <?php endif; ?>
                                                    <span><?= e($it['tree_name']) ?></span>
                                                </div>
                                            </td>
                                            <td><?= e($it['size']) ?></td>
                                            <td class="text-end"><?= number_format($it['unit_price'], 2) ?></td>
                                            <td class="text-center">
                                                <span class="badge bg-light text-dark border">
                                                    <?= (int)$it['quantity'] ?>
                                                </span>
                                            </td>
                                            <td class="text-end fw-bold"><?= number_format($it['total_price'], 2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <!-- ข้อมูลบิล -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-info-circle text-primary me-2"></i>ข้อมูลบิล
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3 pb-3 border-bottom">
                        <div class="row mb-2">
                            <div class="col-5 text-muted">รหัสบิล</div>
                            <div class="col-7 fw-bold"><?= e($order['order_code']); ?></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-5 text-muted">วันที่สร้าง</div>
                            <div class="col-7">
                                <i class="bi bi-calendar2 me-1 text-muted"></i>
                                <?= date('d/m/Y', strtotime($order['created_at'])); ?>
                                <small class="text-muted"><?= date('H:i', strtotime($order['created_at'])); ?> น.</small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- ข้อมูลสวน -->
                    <div class="mb-3 pb-3 border-bottom">
                        <h6 class="card-subtitle text-muted mb-3">
                            <i class="bi bi-flower1 me-1"></i>ข้อมูลสวน
                        </h6>
                        <div class="mb-1">
                            <span class="badge bg-success bg-opacity-10 text-success">
                                <?= e($order['garden_name'] ?? 'ไม่ระบุ'); ?>
                            </span>
                        </div>
                        <?php if (!empty($order['garden_phone'])): ?>
                            <div class="small mb-1">
                                <i class="bi bi-telephone me-1 text-muted"></i>
                                <?= e($order['garden_phone']); ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($order['garden_address'])): ?>
                            <div class="small">
                                <i class="bi bi-geo-alt me-1 text-muted"></i>
                                <?= e($order['garden_address']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- ข้อมูลลูกค้า -->
                    <div class="mb-3 pb-3 border-bottom">
                        <h6 class="card-subtitle text-muted mb-3">
                            <i class="bi bi-person me-1"></i>ข้อมูลลูกค้า
                        </h6>
                        <div class="mb-1">
                            <i class="bi bi-person-badge me-1 text-muted"></i>
                            <?= e($order['customer_name']); ?>
                        </div>
                        <?php if (!empty($order['customer_phone'])): ?>
                            <div class="small">
                                <i class="bi bi-telephone me-1 text-muted"></i>
                                <?= e($order['customer_phone']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- สรุปราคา -->
                    <h6 class="card-subtitle text-muted mb-3">
                        <i class="bi bi-cash-coin me-1"></i>สรุปราคา
                    </h6>
                    <?php $subtotal = array_sum(array_column($orderItems, 'total_price')); ?>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">ยอดต้นไม้รวม</span>
                        <span><?= number_format($subtotal, 2) ?> ฿</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">ค่าปลูก / ค่าปุ้ย / ค่าผ้าคลุม</span>
                        <span><?= number_format($order['extra_cost'], 2) ?> ฿</span>
                    </div>
                    <div class="d-flex justify-content-between mb-3">
                        <span class="text-muted">ค่าขนส่ง</span>
                        <span><?= number_format($order['transportation_fee'], 2) ?> ฿</span>
                    </div>
                    <div class="border-top pt-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fw-bold">ยอดทั้งหมด</span>
                            <span class="fs-4 fw-bold text-success"><?= number_format($order['total_price'], 2) ?> ฿</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- ปุ่มดำเนินการ -->
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <a href="../generate_invoice.php?code=<?= e($order['order_code']); ?>" 
                       class="btn btn-success w-100 mb-2" target="_blank">
                        <i class="bi bi-file-pdf me-1"></i> พิมพ์ใบเสร็จ
                    </a>
                    <a href="orders.php?cancel_id=<?= $order['id'] ?>"
                       class="btn btn-outline-warning w-100 mb-2"
                       onclick="return confirm('ยืนยันยกเลิกออเดอร์ ID <?= $order['id'] ?> หรือไม่? จะคืนสต็อกต้นไม้ด้วย');">
                        <i class="bi bi-x-circle me-1"></i> ยกเลิกออเดอร์ (คืนสต็อก)
                    </a>
                    <a href="orders.php" class="btn btn-outline-secondary w-100">
                        <i class="bi bi-arrow-left me-1"></i> กลับไปรายการบิล
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.badge {
    font-weight: 500;
}
.table td, .table th {
    padding: 0.75rem;
}
.card {
    border-radius: 8px;
    overflow: hidden;
}
.card-header {
    border-bottom: 1px solid rgba(0,0,0,0.05);
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>
