<?php
// pages/edit_tree.php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

// ถ้ายังไม่ล็อกอิน → เด้งไปหน้า login
if (!isLoggedIn()) {
    redirect('login.php');
}

// รับ tree_id จาก GET
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    redirect('trees.php');
}
$treeId = (int)$_GET['id'];

// 1) ดึงข้อมูลต้นไม้ปัจจุบัน
$stmt = $pdo->prepare("
    SELECT id, name, size, price, quantity, sold, died, category_id 
    FROM trees 
    WHERE id = :tid
    LIMIT 1
");
$stmt->execute(['tid' => $treeId]);
$tree = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tree) {
    redirect('trees.php');
}

$errors = [];
$success = "";

// 2) เมื่อส่งฟอร์ม (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ตรวจ CSRF
    if (empty($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $errors[] = "Session หมดอายุ กรุณาลองใหม่อีกครั้ง";
    } else {
        // sanitize ค่าที่จะอัปเดต
        $newSold = isset($_POST['sold']) ? (int)$_POST['sold'] : $tree['sold'];
        $newDied = isset($_POST['died']) ? (int)$_POST['died'] : $tree['died'];

        // ตรวจความถูกต้อง
        if ($newSold < 0) {
            $errors[] = "จำนวนที่ขายต้องเป็นตัวเลข ≥ 0";
        }
        if ($newDied < 0) {
            $errors[] = "จำนวนที่ตายต้องเป็นตัวเลข ≥ 0";
        }

        // ถ้าไม่มี error ในการ validate
        if (empty($errors)) {
            // คำนวณความแตกต่างจากเดิม เพื่อคืน/หักสต็อก (quantity)
            $diffSold = $newSold - (int)$tree['sold'];
            $diffDied = $newDied - (int)$tree['died'];

            // ถ้า diffSold หรือ diffDied บวก ก็หมายถึงต้องลด quantity, ถ้ลบ คือคืน stock
            $newQuantity = (int)$tree['quantity'] - $diffSold - $diffDied;
            if ($newQuantity < 0) {
                $errors[] = "สต็อกไม่พอ (จำนวนคงเหลือจะติดลบ)";
            }
        }

        if (empty($errors)) {
            // อัปเดตตาราง trees
            $stmtUpd = $pdo->prepare("
                UPDATE trees
                SET sold = :newSold,
                    died = :newDied,
                    quantity = :newQty
                WHERE id = :tid
            ");
            $stmtUpd->execute([
                'newSold' => $newSold,
                'newDied' => $newDied,
                'newQty'  => $newQuantity,
                'tid'     => $treeId
            ]);

            $_SESSION['flash_message'] = "อัปเดตต้นไม้ ID={$treeId} เรียบร้อยแล้ว";
            redirect("trees.php");
        }
    }
}

// สร้าง CSRF token
$csrf_token = generateCsrfToken();
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="container py-4">
    <!-- Breadcrumb navigation -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="trees.php" class="text-decoration-none">จัดการต้นไม้</a></li>
            <li class="breadcrumb-item active" aria-current="page">แก้ไขต้นไม้</li>
        </ol>
    </nav>

    <!-- แสดง Flash Message -->
    <?php if (!empty($_SESSION['flash_message'])): ?>
        <div class="alert alert-success d-flex align-items-center" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>
            <div>
                <?= e($_SESSION['flash_message']) ?>
                <?php unset($_SESSION['flash_message']); ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-pencil-square text-primary me-2"></i>
                        แก้ไขต้นไม้: <?= e($tree['name']) ?>
                    </h5>
                    <span class="badge bg-light text-dark border">ID: <?= $tree['id'] ?></span>
                </div>
                <div class="card-body">
                    <!-- แสดง Error -->
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger d-flex align-items-center" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <div>
                                <ul class="mb-0 ps-3">
                                    <?php foreach ($errors as $err): ?>
                                        <li><?= e($err) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- ฟอร์มแก้ไข sold / died -->
                    <form action="<?= e(basename($_SERVER['PHP_SELF'])) . '?id=' . $treeId ?>" method="post" novalidate class="needs-validation">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">

                        <!-- ส่วนข้อมูลทั่วไป (Read-only) -->
                        <div class="card bg-light mb-4">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-3 text-muted">
                                    <i class="bi bi-info-circle me-2"></i>ข้อมูลต้นไม้
                                </h6>
                                
                                <div class="row mb-3">
                                    <label class="col-sm-3 col-form-label fw-bold">ชื่อ:</label>
                                    <div class="col-sm-9">
                                        <p class="form-control-plaintext"><?= e($tree['name']) ?></p>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <label class="col-sm-3 col-form-label fw-bold">ขนาด:</label>
                                    <div class="col-sm-9">
                                        <p class="form-control-plaintext"><?= e($tree['size']) ?></p>
                                    </div>
                                </div>

                                <div class="row">
                                    <label class="col-sm-3 col-form-label fw-bold">ราคา:</label>
                                    <div class="col-sm-9">
                                        <p class="form-control-plaintext">฿ <?= number_format($tree['price'], 2) ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ส่วนการจัดการจำนวน -->
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-3 text-muted">
                                    <i class="bi bi-box me-2"></i>จัดการสต็อก
                                </h6>

                                <!-- คงเหลือปัจจุบัน -->
                                <div class="row mb-3 align-items-center">
                                    <label class="col-sm-3 col-form-label fw-bold">จำนวนคงเหลือ:</label>
                                    <div class="col-sm-9">
                                        <div class="input-group">
                                            <span class="input-group-text bg-light">
                                                <i class="bi bi-boxes text-success"></i>
                                            </span>
                                            <input type="text" class="form-control bg-light fw-bold text-success" 
                                                value="<?= (int)$tree['quantity'] ?>" readonly>
                                        </div>
                                        <small class="text-muted d-block mt-1">
                                            จำนวนคงเหลือจะถูกปรับอัตโนมัติเมื่อแก้ไขค่าด้านล่าง
                                        </small>
                                    </div>
                                </div>

                                <!-- จำนวนที่ขาย (editable) -->
                                <div class="row mb-3">
                                    <label for="sold" class="col-sm-3 col-form-label fw-bold">จำนวนที่ขาย:</label>
                                    <div class="col-sm-9">
                                        <div class="input-group">
                                            <span class="input-group-text">
                                                <i class="bi bi-cart-check text-primary"></i>
                                            </span>
                                            <input type="number" name="sold" id="sold" class="form-control"
                                                value="<?= (int)$tree['sold'] ?>" min="0" required>
                                        </div>
                                        <small class="text-muted d-block mt-1">
                                            ปัจจุบัน: <?= (int)$tree['sold'] ?> ต้น (ป้อนค่าใหม่ทั้งหมด)
                                        </small>
                                    </div>
                                </div>

                                <!-- จำนวนที่ตาย (editable) -->
                                <div class="row mb-3">
                                    <label for="died" class="col-sm-3 col-form-label fw-bold">จำนวนที่ตาย:</label>
                                    <div class="col-sm-9">
                                        <div class="input-group">
                                            <span class="input-group-text">
                                                <i class="bi bi-x-circle text-danger"></i>
                                            </span>
                                            <input type="number" name="died" id="died" class="form-control"
                                                value="<?= (int)$tree['died'] ?>" min="0" required>
                                        </div>
                                        <small class="text-muted d-block mt-1">
                                            ปัจจุบัน: <?= (int)$tree['died'] ?> ต้น (ป้อนค่าใหม่ทั้งหมด)
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- การคำนวณการเปลี่ยนแปลง (preview) -->
                        <div class="alert alert-info d-flex align-items-center mb-4" role="alert">
                            <i class="bi bi-info-circle-fill me-2"></i>
                            <div>
                                <strong>หมายเหตุ:</strong> จำนวนคงเหลือจะถูกคำนวณใหม่โดยอัตโนมัติจากจำนวนที่ขายและตาย
                                <div id="stockChangePreview" class="mt-2 fw-bold"></div>
                            </div>
                        </div>

                        <!-- ปุ่มบันทึกและย้อนกลับ -->
                        <div class="d-flex">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save me-1"></i> บันทึกการแก้ไข
                            </button>
                            <a href="trees.php" class="btn btn-outline-secondary ms-2">
                                <i class="bi bi-x-circle me-1"></i> ยกเลิก
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4 mt-4 mt-lg-0">
            <!-- แสดงภาพต้นไม้ (ถ้ามี) -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body text-center">
                    <h6 class="card-subtitle mb-3 text-muted">
                        <i class="bi bi-image me-2"></i>ภาพต้นไม้
                    </h6>
                    <div id="treeImage" class="text-center">
                        <div class="placeholder-glow">
                            <div class="rounded bg-light d-inline-block" style="width:180px;height:180px;">
                                <i class="bi bi-tree text-success" style="font-size:60px;line-height:180px;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- คำแนะนำ -->
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="card-subtitle mb-3 text-muted">
                        <i class="bi bi-lightbulb me-2"></i>คำแนะนำ
                    </h6>
                    <ul class="mb-0 ps-3">
                        <li class="mb-2">ปรับจำนวนต้นไม้ที่ขายเมื่อมีการขายเพิ่ม</li>
                        <li class="mb-2">ปรับจำนวนต้นไม้ที่ตายเมื่อพบว่ามีต้นไม้ตายเพิ่ม</li>
                        <li class="mb-2">ระบบจะคำนวณจำนวนคงเหลือให้อัตโนมัติ</li>
                        <li>ตรวจสอบให้แน่ใจว่าป้อนจำนวนที่ถูกต้อง</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // โหลดรูปภาพต้นไม้ (ถ้ามี)
    fetch(`get_tree_images.php?tree_id=<?= $treeId ?>`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.images.length > 0) {
                const imgElem = document.createElement('img');
                imgElem.src = data.images[0];
                imgElem.className = 'img-fluid rounded shadow-sm';
                imgElem.style.maxHeight = '200px';
                document.getElementById('treeImage').innerHTML = '';
                document.getElementById('treeImage').appendChild(imgElem);
            }
        })
        .catch(error => console.error('Error loading image:', error));
    
    // คำนวณการเปลี่ยนแปลงสต็อกแบบ real-time
    const soldInput = document.getElementById('sold');
    const diedInput = document.getElementById('died');
    const previewDiv = document.getElementById('stockChangePreview');
    
    function updateStockPreview() {
        const currentSold = <?= (int)$tree['sold'] ?>;
        const currentDied = <?= (int)$tree['died'] ?>;
        const currentQuantity = <?= (int)$tree['quantity'] ?>;
        
        const newSold = parseInt(soldInput.value) || 0;
        const newDied = parseInt(diedInput.value) || 0;
        
        const diffSold = newSold - currentSold;
        const diffDied = newDied - currentDied;
        const newQuantity = currentQuantity - diffSold - diffDied;
        
        let message = `จำนวนคงเหลือหลังปรับปรุง: <span class="${newQuantity >= 0 ? 'text-success' : 'text-danger'}">${newQuantity} ต้น</span>`;
        
        if (diffSold !== 0 || diffDied !== 0) {
            message += ' (';
            
            if (diffSold !== 0) {
                message += `ขาย ${diffSold > 0 ? '+' : ''}${diffSold}`;
            }
            
            if (diffSold !== 0 && diffDied !== 0) {
                message += ', ';
            }
            
            if (diffDied !== 0) {
                message += `ตาย ${diffDied > 0 ? '+' : ''}${diffDied}`;
            }
            
            message += ')';
        }
        
        previewDiv.innerHTML = message;
        
        if (newQuantity < 0) {
            previewDiv.innerHTML += '<div class="text-danger mt-1"><i class="bi bi-exclamation-triangle me-1"></i> คำเตือน: จำนวนคงเหลือจะติดลบ!</div>';
        }
    }
    
    soldInput.addEventListener('input', updateStockPreview);
    diedInput.addEventListener('input', updateStockPreview);
    
    // คำนวณค่าเริ่มต้นตอนโหลดเพจ
    updateStockPreview();
});
</script>

<style>
.form-control:focus, .form-select:focus {
    border-color: #81c784;
    box-shadow: 0 0 0 0.25rem rgba(46, 125, 50, 0.25);
}
.btn-primary {
    background-color: #2e7d32;
    border-color: #2e7d32;
}
.btn-primary:hover {
    background-color: #1b5e20;
    border-color: #1b5e20;
}
.card {
    border-radius: 8px;
}
.card-header {
    border-top-left-radius: 8px !important;
    border-top-right-radius: 8px !important;
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>
