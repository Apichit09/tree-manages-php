<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

if (isset($_GET['cancel_id']) && is_numeric($_GET['cancel_id'])) {
    $cancelId = (int) $_GET['cancel_id'];

    $pdo->beginTransaction();
    try {
        $stmtFetchItems = $pdo->prepare("
            SELECT tree_name, size, quantity 
            FROM order_items 
            WHERE order_id = :oid
        ");
        $stmtFetchItems->execute(['oid' => $cancelId]);
        $itemsToReturn = $stmtFetchItems->fetchAll(PDO::FETCH_ASSOC);

        $stmtUpdateTree = $pdo->prepare("
            UPDATE trees
            SET sold = GREATEST(sold - :qty, 0),
                quantity = quantity + :qty
            WHERE name = :tname AND size = :tsize
            LIMIT 1
        ");

        foreach ($itemsToReturn as $itm) {
            $stmtUpdateTree->execute([
                'qty' => $itm['quantity'],
                'tname' => $itm['tree_name'],
                'tsize' => $itm['size']
            ]);
        }

        $stmtItems = $pdo->prepare("DELETE FROM order_items WHERE order_id = :oid");
        $stmtItems->execute(['oid' => $cancelId]);

        $stmtOrder = $pdo->prepare("DELETE FROM orders WHERE id = :oid");
        $stmtOrder->execute(['oid' => $cancelId]);

        $pdfPattern = __DIR__ . '/../pdf/bill_' . $cancelId . '.pdf';
        if (is_file($pdfPattern)) {
            unlink($pdfPattern);
        }

        $pdo->commit();
        $_SESSION['flash_message'] = "ยกเลิกออเดอร์ ID={$cancelId} เรียบร้อยแล้ว และคืนสต็อกต้นไม้แล้ว";
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['flash_message'] = "เกิดข้อผิดพลาดขณะยกเลิกออเดอร์: " . e($e->getMessage());
    }

    redirect('orders.php');
}

if (isset($_GET['delete_id']) && is_numeric($_GET['delete_id'])) {
    $deleteId = (int) $_GET['delete_id'];

    $pdo->beginTransaction();
    try {
        $stmtItems = $pdo->prepare("DELETE FROM order_items WHERE order_id = :oid");
        $stmtItems->execute(['oid' => $deleteId]);

        $stmtOrder = $pdo->prepare("DELETE FROM orders WHERE id = :oid");
        $stmtOrder->execute(['oid' => $deleteId]);

        $pdfPattern = __DIR__ . '/../pdf/bill_' . $deleteId . '.pdf';
        if (is_file($pdfPattern)) {
            unlink($pdfPattern);
        }

        $pdo->commit();
        $_SESSION['flash_message'] = "ลบออเดอร์ ID={$deleteId} เรียบร้อยแล้ว";
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['flash_message'] = "เกิดข้อผิดพลาดขณะลบออเดอร์: " . e($e->getMessage());
    }

    redirect('orders.php');
}

$sql = "
    SELECT 
        o.id,
        o.order_code,
        g.name AS garden_name,
        o.customer_name,
        o.customer_phone,
        o.created_at,
        o.total_price
    FROM orders o
    LEFT JOIN gardens g ON o.garden_id = g.id
    ORDER BY o.created_at DESC
";
$stmt = $pdo->query($sql);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="container">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <h2 class="mb-3 mb-md-0">
                    <i class="bi bi-receipt text-primary me-2"></i>จัดการออเดอร์ / ใบเสนอราคา
                </h2>
                <div>
                    <a href="order_create.php" class="btn btn-success">
                        <i class="bi bi-plus-circle me-1"></i> สร้างออเดอร์ใหม่
                    </a>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($_SESSION['flash_message'])): ?>
        <div class="alert alert-success d-flex align-items-center alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>
            <div><?= e($_SESSION['flash_message']) ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            <?php unset($_SESSION['flash_message']); ?>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text bg-light">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="text" id="searchInput" class="form-control" placeholder="ค้นหาออเดอร์...">
                    </div>
                </div>
                <div class="col-md-3">
                    <select id="gardenFilter" class="form-select">
                        <option value="">ทุกสวน</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select id="dateFilter" class="form-select">
                        <option value="all">ทุกช่วงเวลา</option>
                        <option value="today">วันนี้</option>
                        <option value="week">7 วันล่าสุด</option>
                        <option value="month">30 วันล่าสุด</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-outline-secondary w-100" id="resetFilters">
                        <i class="bi bi-x-circle me-1"></i> ล้างตัวกรอง
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="ordersTable">
                    <thead class="bg-light">
                        <tr>
                            <th class="py-3">Order ID</th>
                            <th class="py-3">Order Code</th>
                            <th class="py-3">ชื่อสวน</th>
                            <th class="py-3">ชื่อลูกค้า</th>
                            <th class="py-3">เบอร์ลูกค้า</th>
                            <th class="py-3">วันที่สร้าง</th>
                            <th class="py-3 text-end">ราคารวม (฿)</th>
                            <th class="py-3 text-center">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orders)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-5">
                                    <i class="bi bi-inbox text-muted fs-1"></i>
                                    <p class="text-muted mt-3">ยังไม่มีออเดอร์ในระบบ</p>
                                    <a href="order_create.php" class="btn btn-sm btn-success mt-2">
                                        <i class="bi bi-plus-circle me-1"></i> สร้างออเดอร์ใหม่
                                    </a>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($orders as $o): ?>
                                <tr class="order-row">
                                    <td>
                                        <span class="badge bg-light text-dark border">#<?= (int) $o['id'] ?></span>
                                    </td>
                                    <td>
                                        <div class="fw-bold"><?= e($o['order_code']) ?></div>
                                    </td>
                                    <td>
                                        <?php if (!empty($o['garden_name'])): ?>
                                            <span class="badge bg-success bg-opacity-10 text-success">
                                                <i class="bi bi-flower1 me-1"></i><?= e($o['garden_name']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div><?= e($o['customer_name']) ?></div>
                                    </td>
                                    <td>
                                        <?php if (!empty($o['customer_phone'])): ?>
                                            <div class="small text-muted">
                                                <i class="bi bi-telephone me-1"></i><?= e($o['customer_phone']) ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-calendar2 me-2 text-muted"></i>
                                            <div>
                                                <div><?= date('d/m/Y', strtotime($o['created_at'])) ?></div>
                                                <small class="text-muted"><?= date('H:i', strtotime($o['created_at'])) ?>
                                                    น.</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-end fw-bold">฿ <?= number_format($o['total_price'], 2) ?></td>
                                    <td>
                                        <div class="d-flex justify-content-center gap-1">
                                            <a href="order_view.php?code=<?= e($o['order_code']) ?>"
                                                class="btn btn-sm btn-primary" title="ดูรายละเอียด">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <button type="button" class="btn btn-sm btn-warning cancel-order-btn"
                                                data-id="<?= $o['id'] ?>" title="ยกเลิกออเดอร์และคืนสต็อก">
                                                <i class="bi bi-x-circle"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-danger delete-order-btn"
                                                data-id="<?= $o['id'] ?>" title="ลบออเดอร์">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php if (!empty($orders)): ?>
        <div class="d-flex justify-content-between align-items-center mt-4">
            <div class="text-muted small">
                กำลังแสดง <span id="displayedOrders"><?= count($orders) ?></span> จากทั้งหมด <span
                    id="totalOrders"><?= count($orders) ?></span> รายการ
            </div>
            <nav aria-label="Page navigation">
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item disabled"><a class="page-link" href="#">ก่อนหน้า</a></li>
                    <li class="page-item active"><a class="page-link" href="#">1</a></li>
                    <li class="page-item"><a class="page-link" href="#">2</a></li>
                    <li class="page-item"><a class="page-link" href="#">3</a></li>
                    <li class="page-item"><a class="page-link" href="#">ถัดไป</a></li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>
<div class="modal fade" id="cancelOrderModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-warning bg-opacity-10">
                <h5 class="modal-title">
                    <i class="bi bi-exclamation-triangle text-warning me-2"></i>
                    ยืนยันยกเลิกออเดอร์
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>ยืนยันการยกเลิกออเดอร์ ID <span id="cancelOrderId" class="fw-bold">0</span> หรือไม่?</p>
                <div class="alert alert-info d-flex align-items-center" role="alert">
                    <i class="bi bi-info-circle-fill me-2"></i>
                    <div>การยกเลิกออเดอร์จะคืนสต็อกต้นไม้โดยอัตโนมัติ</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x me-1"></i> ยกเลิก
                </button>
                <a href="#" id="confirmCancelBtn" class="btn btn-warning">
                    <i class="bi bi-check-circle me-1"></i> ยืนยันการยกเลิก
                </a>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteOrderModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-danger bg-opacity-10">
                <h5 class="modal-title">
                    <i class="bi bi-trash text-danger me-2"></i>
                    ยืนยันลบออเดอร์
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>ยืนยันการลบออเดอร์ ID <span id="deleteOrderId" class="fw-bold">0</span> หรือไม่?</p>
                <div class="alert alert-danger d-flex align-items-center" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <div>คำเตือน: การลบออเดอร์จะไม่คืนสต็อกต้นไม้ การกระทำนี้ไม่สามารถย้อนกลับได้</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x me-1"></i> ยกเลิก
                </button>
                <a href="#" id="confirmDeleteBtn" class="btn btn-danger">
                    <i class="bi bi-trash me-1"></i> ยืนยันการลบ
                </a>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const gardens = new Set();
        document.querySelectorAll('.order-row').forEach(row => {
            const garden = row.children[2].textContent.trim();
            if (garden && garden !== '-') {
                gardens.add(garden);
            }
        });

        const gardenFilter = document.getElementById('gardenFilter');
        gardens.forEach(garden => {
            const option = document.createElement('option');
            option.value = garden;
            option.textContent = garden;
            gardenFilter.appendChild(option);
        });

        function filterOrders() {
            const searchValue = document.getElementById('searchInput').value.toLowerCase();
            const gardenValue = document.getElementById('gardenFilter').value;
            const dateValue = document.getElementById('dateFilter').value;

            let visibleCount = 0;
            const rows = document.querySelectorAll('.order-row');
            const today = new Date();
            const oneDay = 24 * 60 * 60 * 1000;

            rows.forEach(row => {
                const orderId = row.children[0].textContent.toLowerCase();
                const orderCode = row.children[1].textContent.toLowerCase();
                const garden = row.children[2].textContent.trim();
                const customer = row.children[3].textContent.toLowerCase();
                const phone = row.children[4].textContent.toLowerCase();
                const dateText = row.children[5].textContent;

                const dateParts = dateText.trim().split('/');
                const orderDate = new Date(`${dateParts[2]}-${dateParts[1]}-${dateParts[0]}`);
                const daysDiff = Math.round(Math.abs((today - orderDate) / oneDay));

                const matchesSearch = orderId.includes(searchValue) ||
                    orderCode.includes(searchValue) ||
                    garden.toLowerCase().includes(searchValue) ||
                    customer.includes(searchValue) ||
                    phone.includes(searchValue);

                const matchesGarden = !gardenValue || garden.includes(gardenValue);

                let matchesDate = true;
                if (dateValue === 'today') {
                    matchesDate = daysDiff < 1;
                } else if (dateValue === 'week') {
                    matchesDate = daysDiff <= 7;
                } else if (dateValue === 'month') {
                    matchesDate = daysDiff <= 30;
                }

                const isVisible = matchesSearch && matchesGarden && matchesDate;
                row.style.display = isVisible ? '' : 'none';

                if (isVisible) visibleCount++;
            });

            document.getElementById('displayedOrders').textContent = visibleCount;
        }

        document.getElementById('searchInput').addEventListener('input', filterOrders);
        document.getElementById('gardenFilter').addEventListener('change', filterOrders);
        document.getElementById('dateFilter').addEventListener('change', filterOrders);
        document.getElementById('resetFilters').addEventListener('click', function () {
            document.getElementById('searchInput').value = '';
            document.getElementById('gardenFilter').value = '';
            document.getElementById('dateFilter').value = 'all';
            filterOrders();
        });

        const cancelOrderModal = new bootstrap.Modal(document.getElementById('cancelOrderModal'));
        document.querySelectorAll('.cancel-order-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                const orderId = this.dataset.id;
                document.getElementById('cancelOrderId').textContent = orderId;
                document.getElementById('confirmCancelBtn').href = `orders.php?cancel_id=${orderId}`;
                cancelOrderModal.show();
            });
        });

        const deleteOrderModal = new bootstrap.Modal(document.getElementById('deleteOrderModal'));
        document.querySelectorAll('.delete-order-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                const orderId = this.dataset.id;
                document.getElementById('deleteOrderId').textContent = orderId;
                document.getElementById('confirmDeleteBtn').href = `orders.php?delete_id=${orderId}`;
                deleteOrderModal.show();
            });
        });
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

    .card {
        border-radius: 0.5rem;
        overflow: hidden;
    }

    .badge {
        font-weight: 500;
        padding: 0.5em 0.8em;
    }

    .table th {
        font-weight: 600;
        color: #495057;
    }
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>