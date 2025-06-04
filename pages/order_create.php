<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$stmtGarden = $pdo->query("SELECT id, name FROM gardens ORDER BY name ASC");
$gardens = $stmtGarden->fetchAll(PDO::FETCH_ASSOC);

$sqlTrees = "
    SELECT t.id, t.name, t.size, t.price,
           (SELECT image_url FROM tree_images WHERE tree_id = t.id LIMIT 1) AS thumb
    FROM trees t
    ORDER BY t.name ASC
";
$stmtTree = $pdo->query($sqlTrees);
$trees = $stmtTree->fetchAll(PDO::FETCH_ASSOC);

$errors = [];
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $errors[] = "Session หมดอายุ กรุณาลองใหม่อีกครั้ง";
    } else {
        $garden_id         = (int)($_POST['garden_id'] ?? 0);
        $customer_name     = trim($_POST['customer_name'] ?? '');
        $customer_phone    = trim($_POST['customer_phone'] ?? '');
        $extra_cost        = trim($_POST['extra_cost'] ?? '0');
        $transport_fee     = trim($_POST['transportation_fee'] ?? '0');

        $item_tree_ids     = $_POST['item_tree_id']     ?? [];
        $item_sizes        = $_POST['item_size']        ?? [];
        $item_quantities   = $_POST['item_quantity']    ?? [];
        $item_unitprices   = $_POST['item_unit_price']  ?? [];
        $itemCount = count($item_tree_ids);

        if ($garden_id <= 0) {
            $errors[] = "กรุณาเลือกสวน";
        }
        if (empty($customer_name)) {
            $errors[] = "กรุณากรอกชื่อลูกค้า";
        }
        if ($extra_cost === '' || !is_numeric($extra_cost) || $extra_cost < 0) {
            $errors[] = "กรุณาระบุค่าราคาอื่น ๆ (Extra Cost) ให้ถูกต้อง หรือปล่อยเป็น 0";
        }
        if ($transport_fee === '' || !is_numeric($transport_fee) || $transport_fee < 0) {
            $errors[] = "กรุณาระบุค่าขนส่ง (Transportation Fee) ให้ถูกต้อง หรือปล่อยเป็น 0";
        }

        if ($itemCount < 1) {
            $errors[] = "กรุณาเพิ่มรายการต้นไม้อย่างน้อย 1 รายการ";
        } else {
            $validatedItems = [];
            for ($i = 0; $i < $itemCount; $i++) {
                $treeId     = (int)$item_tree_ids[$i];
                $size       = trim($item_sizes[$i] ?? '');
                $qty        = trim($item_quantities[$i] ?? '');
                $unitPrice  = trim($item_unitprices[$i] ?? '');

                if ($treeId <= 0) {
                    $errors[] = "รายการต้นไม้ที่ " . ($i+1) . " ไม่ถูกต้อง (กรุณาเลือกต้นไม้)";
                }
                if ($size === '') {
                    $errors[] = "รายการต้นไม้ที่ " . ($i+1) . " กรุณาระบุขนาด";
                }
                if ($qty === '' || !is_numeric($qty) || (int)$qty <= 0) {
                    $errors[] = "รายการต้นไม้ที่ " . ($i+1) . " กรุณาระบุจำนวน (ตัวเลข ≥ 1)";
                }
                if ($unitPrice === '' || !is_numeric($unitPrice) || (float)$unitPrice < 0) {
                    $errors[] = "รายการต้นไม้ที่ " . ($i+1) . " กรุณาระบุราคา/ต้น (฿) ให้ถูกต้อง (≥ 0)";
                }

                if (empty($errors)) {
                    $validatedItems[] = [
                        'tree_id'   => $treeId,
                        'size'      => $size,
                        'quantity'  => (int)$qty,
                        'unit_price'=> (float)$unitPrice
                    ];
                }
            }
        }

        if (empty($errors)) {
            $subtotal = 0;
            foreach ($validatedItems as $it) {
                $subtotal += $it['quantity'] * $it['unit_price'];
            }
            $totalPrice = $subtotal + (float)$extra_cost + (float)$transport_fee;

            $order_code = 'ORD-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));

            $pdo->beginTransaction();
            try {
                $sqlOrder = "
                    INSERT INTO orders 
                    (order_code, garden_id, customer_name, customer_phone, created_at, extra_cost, transportation_fee, total_price)
                    VALUES 
                    (:ocode, :gid, :cname, :cphone, NOW(), :extra, :trans, :total)
                ";
                $stmtOrder = $pdo->prepare($sqlOrder);
                $stmtOrder->execute([
                    'ocode'  => $order_code,
                    'gid'    => $garden_id,
                    'cname'  => $customer_name,
                    'cphone' => $customer_phone,
                    'extra'  => $extra_cost,
                    'trans'  => $transport_fee,
                    'total'  => $totalPrice
                ]);
                $newOrderId = $pdo->lastInsertId();

                $sqlItem = "
                    INSERT INTO order_items 
                    (order_id, tree_name, size, quantity, unit_price, total_price, image_link)
                    VALUES 
                    (:oid, :tname, :tsize, :qty, :uprice, :tprice, :imglink)
                ";
                $stmtItem = $pdo->prepare($sqlItem);

                $stmtUpdateTree = $pdo->prepare("
                    UPDATE trees 
                    SET sold = sold + :qty_sold, 
                        quantity = GREATEST(quantity - :qty_sold, 0) 
                    WHERE id = :tid
                ");

                foreach ($validatedItems as $it) {
                    $stmtTreeInfo = $pdo->prepare("SELECT name FROM trees WHERE id = :tid");
                    $stmtTreeInfo->execute(['tid' => $it['tree_id']]);
                    $treeInfo = $stmtTreeInfo->fetch(PDO::FETCH_ASSOC);
                    $treeName = $treeInfo ? $treeInfo['name'] : 'Unknown';
                    $stmtThumb = $pdo->prepare("SELECT image_url FROM tree_images WHERE tree_id = :tid LIMIT 1");
                    $stmtThumb->execute(['tid' => $it['tree_id']]);
                    $thumbRow = $stmtThumb->fetch(PDO::FETCH_ASSOC);
                    $imgLink = $thumbRow ? $thumbRow['image_url'] : '';

                    $itemTotal = $it['quantity'] * $it['unit_price'];
                    $stmtItem->execute([
                        'oid'    => $newOrderId,
                        'tname'  => $treeName,
                        'tsize'  => $it['size'],
                        'qty'    => $it['quantity'],
                        'uprice' => $it['unit_price'],
                        'tprice' => $itemTotal,
                        'imglink'=> $imgLink
                    ]);

                    $stmtUpdateTree->execute([
                        'qty_sold' => $it['quantity'],
                        'tid'      => $it['tree_id']
                    ]);
                }

                $pdo->commit();
                $_SESSION['flash_message'] = "สร้างออเดอร์ (รหัส: {$order_code}) สำเร็จแล้ว";

                redirect("order_view.php?code={$order_code}");
            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = "เกิดข้อผิดพลาดในการบันทึกออเดอร์: " . $e->getMessage();
            }
        }
    }
}

$csrf_token = generateCsrfToken();
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="container">
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="orders.php" class="text-decoration-none">ออเดอร์/บิล</a></li>
            <li class="breadcrumb-item active" aria-current="page">สร้างออเดอร์ใหม่</li>
        </ol>
    </nav>

    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="mb-0">
                    <i class="bi bi-plus-circle-fill text-success me-2"></i>สร้างออเดอร์ / ใบเสนอราคาใหม่
                </h2>
            </div>
        </div>
    </div>

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

    <form action="<?= e(basename($_SERVER['PHP_SELF'])) ?>" method="post" novalidate>
        <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">

        <div class="row">
            <div class="col-lg-8">
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-person-fill text-primary me-2"></i>ข้อมูลลูกค้า
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="garden_id" class="form-label">เลือกสวน</label>
                            <select id="garden_id" name="garden_id" class="form-select" required>
                                <option value="">-- เลือกสวน --</option>
                                <?php foreach ($gardens as $g): ?>
                                    <option value="<?= $g['id'] ?>"
                                        <?= (isset($garden_id) && $garden_id == $g['id']) ? 'selected' : '' ?>>
                                        <?= e($g['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">กรุณาเลือกสวน</div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="customer_name" class="form-label">ชื่อลูกค้า</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light">
                                        <i class="bi bi-person"></i>
                                    </span>
                                    <input type="text" id="customer_name" name="customer_name" class="form-control"
                                        value="<?= isset($customer_name) ? e($customer_name) : '' ?>" 
                                        required placeholder="ระบุชื่อลูกค้า">
                                    <div class="invalid-feedback">กรุณากรอกชื่อลูกค้า</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="customer_phone" class="form-label">เบอร์ลูกค้า</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light">
                                        <i class="bi bi-telephone"></i>
                                    </span>
                                    <input type="text" id="customer_phone" name="customer_phone" class="form-control"
                                        value="<?= isset($customer_phone) ? e($customer_phone) : '' ?>" 
                                        placeholder="ระบุเบอร์โทร (ไม่บังคับ)">
                                </div>
                                <div class="form-text">ไม่บังคับ</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-tree-fill text-success me-2"></i>รายการต้นไม้
                            </h5>
                            <button type="button" id="btnAddRow" class="btn btn-sm btn-success px-3">
                                <i class="bi bi-plus-lg me-1"></i> เพิ่มรายการ
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table mb-0 border-bottom" id="orderItemsTable">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width:5%;" class="ps-3">#</th>
                                        <th style="width:25%;">เลือกต้นไม้</th>
                                        <th style="width:13%;">ขนาด</th>
                                        <th style="width:12%;">ราคา/ต้น (฿)</th>
                                        <th style="width:10%;">จำนวน</th>
                                        <th style="width:15%;">ราคารวม (฿)</th>
                                        <th style="width:10%;" class="text-center">รูปภาพ</th>
                                        <th style="width:5%;" class="text-center">ลบ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if (!empty($_POST) && !empty($validatedItems ?? null)): ?>
                                    <?php foreach ($validatedItems as $index => $item):
                                        $thumbUrl = '';
                                        if (isset($item['tree_id'])) {
                                            $stmtTb = $pdo->prepare("SELECT image_url FROM tree_images WHERE tree_id = :tid LIMIT 1");
                                            $stmtTb->execute(['tid' => $item['tree_id']]);
                                            $trb = $stmtTb->fetch(PDO::FETCH_ASSOC);
                                            $thumbUrl = $trb ? $trb['image_url'] : '';
                                        }
                                    ?>
                                    <tr>
                                        <td class="align-middle ps-3"><?= $index + 1 ?></td>
                                        <td>
                                            <select name="item_tree_id[]" class="form-select item-tree-select" required>
                                                <option value="">-- เลือกต้นไม้ --</option>
                                                <?php foreach ($trees as $t): ?>
                                                    <option value="<?= $t['id'] ?>"
                                                        <?= ($item['tree_id'] == $t['id']) ? 'selected' : '' ?>
                                                        data-size="<?= e($t['size']) ?>"
                                                        data-price="<?= e($t['price']) ?>"
                                                        data-thumb="<?= e($t['thumb']) ?>">
                                                        <?= e($t['name']) ?> (<?= e($t['size']) ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td>
                                            <input type="text" name="item_size[]" class="form-control bg-light" 
                                                value="<?= e($item['size']) ?>" readonly required>
                                        </td>
                                        <td>
                                            <div class="input-group">
                                                <input type="number" step="0.01" name="item_unit_price[]" class="form-control text-end bg-light" 
                                                    value="<?= e($item['unit_price']) ?>" readonly required>
                                                <span class="input-group-text bg-light">฿</span>
                                            </div>
                                        </td>
                                        <td>
                                            <input type="number" name="item_quantity[]" class="form-control item-qty" 
                                                value="<?= e($item['quantity']) ?>" min="1" required>
                                        </td>
                                        <td>
                                            <div class="input-group">
                                                <input type="text" name="item_total_price[]" class="form-control text-end bg-light item-total" 
                                                    value="<?= number_format($item['quantity'] * $item['unit_price'], 2) ?>" readonly>
                                                <span class="input-group-text bg-light">฿</span>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($thumbUrl): ?>
                                                <img src="<?= e($thumbUrl) ?>" alt="thumb" class="img-thumbnail" style="width:48px; height:48px; object-fit:cover;">
                                            <?php else: ?>
                                                <div class="bg-light d-flex align-items-center justify-content-center rounded" style="width:48px; height:48px; margin:0 auto;">
                                                    <i class="bi bi-image text-muted"></i>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-sm btn-outline-danger rounded-circle btn-remove-row">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td class="align-middle ps-3">1</td>
                                        <td>
                                            <select name="item_tree_id[]" class="form-select item-tree-select" required>
                                                <option value="">-- เลือกต้นไม้ --</option>
                                                <?php foreach ($trees as $t): ?>
                                                    <option value="<?= $t['id'] ?>" 
                                                        data-size="<?= e($t['size']) ?>"
                                                        data-price="<?= e($t['price']) ?>"
                                                        data-thumb="<?= e($t['thumb']) ?>">
                                                        <?= e($t['name']) ?> (<?= e($t['size']) ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td>
                                            <input type="text" name="item_size[]" class="form-control bg-light" readonly required>
                                        </td>
                                        <td>
                                            <div class="input-group">
                                                <input type="number" step="0.01" name="item_unit_price[]" class="form-control text-end bg-light" readonly required>
                                                <span class="input-group-text bg-light">฿</span>
                                            </div>
                                        </td>
                                        <td>
                                            <input type="number" name="item_quantity[]" class="form-control item-qty" value="1" min="1" required>
                                        </td>
                                        <td>
                                            <div class="input-group">
                                                <input type="text" name="item_total_price[]" class="form-control text-end bg-light item-total" value="0.00" readonly>
                                                <span class="input-group-text bg-light">฿</span>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <div class="bg-light d-flex align-items-center justify-content-center rounded" style="width:48px; height:48px; margin:0 auto;">
                                                <i class="bi bi-image text-muted"></i>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-sm btn-outline-danger rounded-circle btn-remove-row">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-cash-coin text-success me-2"></i>ค่าใช้จ่ายเพิ่มเติม
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="extra_cost" class="form-label">ค่าใช้จ่ายอื่น ๆ (฿)</label>
                                <div class="input-group">
                                    <input type="number" step="0.01" id="extra_cost" name="extra_cost" class="form-control"
                                        value="<?= isset($extra_cost) ? e($extra_cost) : '0.00' ?>" min="0">
                                    <span class="input-group-text">฿</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="transportation_fee" class="form-label">ค่าขนส่ง (฿)</label>
                                <div class="input-group">
                                    <input type="number" step="0.01" id="transportation_fee" name="transportation_fee" class="form-control"
                                        value="<?= isset($transport_fee) ? e($transport_fee) : '0.00' ?>" min="0">
                                    <span class="input-group-text">฿</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="d-flex justify-content-end mb-4">
                    <a href="orders.php" class="btn btn-outline-secondary me-2">
                        <i class="bi bi-x-circle me-1"></i> ยกเลิก
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i> บันทึกออเดอร์
                    </button>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="position-sticky" style="top: 1rem;">
                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-header bg-primary bg-opacity-10 py-3">
                            <h5 class="card-title mb-0 text-primary">
                                <i class="bi bi-receipt me-2"></i>สรุปรายการ
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="summary-item d-flex justify-content-between mb-2">
                                <span>ราคาต้นไม้รวม:</span>
                                <span class="fw-bold" id="subtotal-display">฿ 0.00</span>
                            </div>
                            <div class="summary-item d-flex justify-content-between mb-2">
                                <span>ค่าใช้จ่ายอื่นๆ:</span>
                                <span id="extra-cost-display">฿ 0.00</span>
                            </div>
                            <div class="summary-item d-flex justify-content-between mb-2">
                                <span>ค่าขนส่ง:</span>
                                <span id="transport-fee-display">฿ 0.00</span>
                            </div>
                            <hr class="my-3">
                            <div class="summary-item d-flex justify-content-between">
                                <span class="fs-5 fw-bold">ยอดรวมทั้งสิ้น:</span>
                                <span class="fs-5 fw-bold text-primary" id="total-display">฿ 0.00</span>
                            </div>
                            <input type="text" id="grand_total" name="grand_total" class="form-control d-none" value="0.00" readonly>
                        </div>
                    </div>
                    
                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-header bg-light py-3">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-lightbulb me-2 text-warning"></i>คำแนะนำ
                            </h5>
                        </div>
                        <div class="card-body">
                            <ul class="mb-0 ps-3">
                                <li class="mb-2">เลือกต้นไม้จากรายการและระบุจำนวน</li>
                                <li class="mb-2">สามารถเพิ่มรายการต้นไม้ได้ไม่จำกัด</li>
                                <li class="mb-2">ราคารวมจะคำนวณอัตโนมัติ</li>
                                <li>ระบบจะปรับลดสต็อกต้นไม้โดยอัตโนมัติ</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const tableBody = document.querySelector('#orderItemsTable tbody');
    const btnAddRow = document.getElementById('btnAddRow');
    const extraCostInput = document.getElementById('extra_cost');
    const transportFeeInput = document.getElementById('transportation_fee');
    const grandTotalInput = document.getElementById('grand_total');
    
    const subtotalDisplay = document.getElementById('subtotal-display');
    const extraCostDisplay = document.getElementById('extra-cost-display');
    const transportFeeDisplay = document.getElementById('transport-fee-display');
    const totalDisplay = document.getElementById('total-display');

    function formatCurrency(amount) {
        return new Intl.NumberFormat('th-TH', { 
            style: 'currency', 
            currency: 'THB',
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(amount).replace('฿', '฿ ');
    }

    function updateGrandTotal() {
        let subtotal = 0;
        document.querySelectorAll('.item-total').forEach(function(el) {
            subtotal += parseFloat(el.value.replace(/,/g, '')) || 0;
        });
        
        const extraCost = parseFloat(extraCostInput.value) || 0;
        const transportFee = parseFloat(transportFeeInput.value) || 0;
        const total = subtotal + extraCost + transportFee;
        
        grandTotalInput.value = total.toFixed(2);
        
        subtotalDisplay.textContent = formatCurrency(subtotal);
        extraCostDisplay.textContent = formatCurrency(extraCost);
        transportFeeDisplay.textContent = formatCurrency(transportFee);
        totalDisplay.textContent = formatCurrency(total);
    }

    function updateRowTotal(row) {
        const qtyInput   = row.querySelector('.item-qty');
        const priceInput = row.querySelector('input[name="item_unit_price[]"]');
        const totalInput = row.querySelector('.item-total');
        const qty = parseInt(qtyInput.value) || 0;
        const price = parseFloat(priceInput.value) || 0;
        totalInput.value = (qty * price).toFixed(2);
        updateGrandTotal();
    }

    tableBody.addEventListener('change', function(event) {
        if (event.target.classList.contains('item-tree-select')) {
            const select = event.target;
            const row = select.closest('tr');
            const selectedOption = select.options[select.selectedIndex];
            const size = selectedOption.getAttribute('data-size') || '';
            const price = selectedOption.getAttribute('data-price') || '0';
            const thumb = selectedOption.getAttribute('data-thumb') || '';

            row.querySelector('input[name="item_size[]"]').value = size;
            row.querySelector('input[name="item_unit_price[]"]').value = parseFloat(price).toFixed(2);
            const imgCell = row.querySelector('td:nth-child(7)');
            if (thumb) {
                imgCell.innerHTML = `<img src="${thumb}" alt="thumb" class="img-thumbnail" style="width:48px; height:48px; object-fit:cover;">`;
            } else {
                imgCell.innerHTML = `
                    <div class="bg-light d-flex align-items-center justify-content-center rounded" style="width:48px; height:48px; margin:0 auto;">
                        <i class="bi bi-image text-muted"></i>
                    </div>
                `;
            }
            updateRowTotal(row);
        }
    });

    tableBody.addEventListener('input', function(event) {
        if (event.target.classList.contains('item-qty')) {
            const row = event.target.closest('tr');
            updateRowTotal(row);
        }
    });

    tableBody.addEventListener('click', function(event) {
        if (event.target.classList.contains('btn-remove-row') || event.target.parentElement.classList.contains('btn-remove-row')) {
            const rows = document.querySelectorAll('#orderItemsTable tbody tr');
            if (rows.length <= 1) {
                alert('ต้องมีรายการอย่างน้อย 1 รายการ');
                return;
            }
            
            const row = event.target.closest('tr');
            row.remove();
            
            document.querySelectorAll('#orderItemsTable tbody tr').forEach(function(r, idx) {
                r.querySelector('td:first-child').textContent = idx + 1;
            });
            updateGrandTotal();
        }
    });

    btnAddRow.addEventListener('click', function() {
        const rowCount = document.querySelectorAll('#orderItemsTable tbody tr').length;
        const newRow = document.createElement('tr');
        newRow.innerHTML = `
            <td class="align-middle ps-3">${rowCount + 1}</td>
            <td>
                <select name="item_tree_id[]" class="form-select item-tree-select" required>
                    <option value="">-- เลือกต้นไม้ --</option>
                    <?php foreach ($trees as $t): ?>
                        <option value="<?= $t['id'] ?>"
                            data-size="<?= e($t['size']) ?>"
                            data-price="<?= e($t['price']) ?>"
                            data-thumb="<?= e($t['thumb']) ?>">
                            <?= e($t['name']) ?> (<?= e($t['size']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td>
                <input type="text" name="item_size[]" class="form-control bg-light" readonly required>
            </td>
            <td>
                <div class="input-group">
                    <input type="number" step="0.01" name="item_unit_price[]" class="form-control text-end bg-light" readonly required>
                    <span class="input-group-text bg-light">฿</span>
                </div>
            </td>
            <td>
                <input type="number" name="item_quantity[]" class="form-control item-qty" value="1" min="1" required>
            </td>
            <td>
                <div class="input-group">
                    <input type="text" name="item_total_price[]" class="form-control text-end bg-light item-total" value="0.00" readonly>
                    <span class="input-group-text bg-light">฿</span>
                </div>
            </td>
            <td class="text-center">
                <div class="bg-light d-flex align-items-center justify-content-center rounded" style="width:48px; height:48px; margin:0 auto;">
                    <i class="bi bi-image text-muted"></i>
                </div>
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-outline-danger rounded-circle btn-remove-row">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        `;
        tableBody.appendChild(newRow);
    });

    extraCostInput.addEventListener('input', updateGrandTotal);
    transportFeeInput.addEventListener('input', updateGrandTotal);
    updateGrandTotal();
});
</script>

<style>
.btn-primary {
    background-color: #2e7d32;
    border-color: #2e7d32;
}
.btn-primary:hover {
    background-color: #1b5e20;
    border-color: #1b5e20;
}
.text-primary {
    color: #2e7d32 !important;
}
.bg-primary {
    background-color: #2e7d32 !important;
}
.form-control:focus, .form-select:focus {
    border-color: #81c784;
    box-shadow: 0 0 0 0.25rem rgba(46, 125, 50, 0.25);
}
.card {
    border-radius: 8px;
    margin-bottom: 1rem;
}
.img-thumbnail {
    transition: transform 0.2s;
}
.img-thumbnail:hover {
    transform: scale(1.1);
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>
