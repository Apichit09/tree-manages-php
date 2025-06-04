<?php
// pages/logs.php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

// ถ้ายังไม่ล็อกอิน → เด้งไปหน้าล็อกอิน
if (!isLoggedIn()) {
    redirect('login.php');
}

// Clear all logs if requested
if (isset($_GET['clear_all'])) {
    try {
        $pdo->exec("TRUNCATE TABLE activity_logs");
        $_SESSION['flash_message'] = "ล้างแจ้งเตือนทั้งหมดเรียบร้อยแล้ว";
    } catch (PDOException $e) {
        $_SESSION['flash_message'] = "เกิดข้อผิดพลาดขณะล้างข้อมูล: " . $e->getMessage();
    }
    header("Location: logs.php");
    exit();
}

// ลบข้อมูลแจ้งเตือน (ถ้ามีการส่ง delete_id มา)
if (isset($_GET['delete_id']) && is_numeric($_GET['delete_id'])) {
    $deleteId = (int)$_GET['delete_id'];
    
    try {
        $stmt = $pdo->prepare("DELETE FROM activity_logs WHERE id = :id");
        $stmt->execute(['id' => $deleteId]);
        
        $_SESSION['flash_message'] = "ลบข้อมูลแจ้งเตือนเรียบร้อยแล้ว";
    } catch (PDOException $e) {
        $_SESSION['flash_message'] = "เกิดข้อผิดพลาดในการลบข้อมูล: " . $e->getMessage();
    }
    
    // Redirect to refresh the page without the GET parameter
    header("Location: logs.php");
    exit();
}

// ดึงข้อมูลแจ้งเตือน/บันทึกกิจกรรมจากตาราง activity_logs
$stmt = $pdo->query("
    SELECT al.*, t.name AS tree_name 
    FROM activity_logs al
    LEFT JOIN trees t ON al.tree_id = t.id
    ORDER BY al.timestamp DESC
    LIMIT 100
");
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="container">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <h2 class="mb-3 mb-md-0">
                    <i class="bi bi-bell text-primary me-2"></i>รายการแจ้งเตือน
                </h2>
                <div>
                    <?php if (!empty($logs)): ?>
                    <button type="button" class="btn btn-outline-danger" id="clearAllLogs">
                        <i class="bi bi-trash me-1"></i> ล้างแจ้งเตือนทั้งหมด
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Flash Message -->
    <?php if (!empty($_SESSION['flash_message'])): ?>
        <div class="alert alert-success d-flex align-items-center alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>
            <div><?= e($_SESSION['flash_message']) ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            <?php unset($_SESSION['flash_message']); ?>
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="bi bi-bell-fill text-primary me-2"></i>รายการแจ้งเตือน / กิจกรรมล่าสุด
                </h5>
                <div>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="refreshLogs">
                        <i class="bi bi-arrow-clockwise me-1"></i> รีเฟรช
                    </button>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <?php if (empty($logs)): ?>
                <div class="p-5 text-center">
                    <i class="bi bi-bell-slash text-muted" style="font-size: 3rem;"></i>
                    <p class="mt-3 text-muted">ยังไม่มีข้อมูลแจ้งเตือน</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3" style="width:5%;">#</th>
                                <th style="width:25%;">ชื่อไม้</th>
                                <th style="width:20%;">เหตุการณ์</th>
                                <th style="width:25%;">วันที่/เวลา</th>
                                <th style="width:15%;">IP Address</th>
                                <th class="text-center" style="width:10%;">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $idx => $row): ?>
                                <tr>
                                    <td class="ps-3"><?php echo $idx + 1; ?></td>
                                    <td>
                                        <?php if (!empty($row['tree_name'])): ?>
                                            <div class="d-flex align-items-center">
                                                <i class="bi bi-tree-fill text-success me-2"></i>
                                                <?php echo e($row['tree_name']); ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                            if ($row['event'] === 'view') {
                                                echo '<span class="badge bg-info text-white">เข้าชม (view)</span>';
                                            } elseif ($row['event'] === 'order_sent') {
                                                echo '<span class="badge bg-success text-white">ส่งบิล (order_sent)</span>';
                                            } else {
                                                echo '<span class="badge bg-secondary text-white">' . e($row['event']) . '</span>';
                                            }
                                        ?>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-calendar-event text-muted me-2"></i>
                                            <div>
                                                <div><?php echo date('d/m/Y', strtotime($row['timestamp'])); ?></div>
                                                <small class="text-muted"><?php echo date('H:i:s', strtotime($row['timestamp'])); ?> น.</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-globe text-muted me-2"></i>
                                            <?php echo e($row['ip_address']); ?>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-outline-danger delete-log" data-id="<?php echo $row['id']; ?>">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination (if needed) -->
                <?php if (count($logs) > 20): ?>
                <div class="d-flex justify-content-between align-items-center p-3 border-top">
                    <div class="small text-muted">
                        แสดง <?php echo count($logs); ?> รายการล่าสุด
                    </div>
                    <nav aria-label="Page navigation">
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item disabled">
                                <a class="page-link" href="#" tabindex="-1" aria-disabled="true">ก่อนหน้า</a>
                            </li>
                            <li class="page-item active"><a class="page-link" href="#">1</a></li>
                            <li class="page-item"><a class="page-link" href="#">2</a></li>
                            <li class="page-item"><a class="page-link" href="#">3</a></li>
                            <li class="page-item">
                                <a class="page-link" href="#">ถัดไป</a>
                            </li>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Confirmation Modal -->
<div class="modal fade" id="deleteLogModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title">ยืนยันการลบ</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <i class="bi bi-exclamation-triangle text-warning" style="font-size: 3rem;"></i>
                <p class="mt-3">ยืนยันลบแจ้งเตือน ID <span id="logIdToDelete"></span> หรือไม่?</p>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                <a href="#" id="confirmDelete" class="btn btn-danger">ลบข้อมูล</a>
            </div>
        </div>
    </div>
</div>

<!-- Clear All Logs Confirmation Modal -->
<div class="modal fade" id="clearAllModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-danger text-white border-0">
                <h5 class="modal-title">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    ยืนยันการลบทั้งหมด
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center p-4">
                <i class="bi bi-trash text-danger" style="font-size: 3.5rem;"></i>
                <h4 class="mt-3">คุณแน่ใจหรือไม่?</h4>
                <p class="text-muted">การลบข้อมูลแจ้งเตือนทั้งหมดไม่สามารถเรียกคืนได้</p>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="button" id="confirmClearAll" class="btn btn-danger">
                    <i class="bi bi-trash me-1"></i> ยืนยันการลบทั้งหมด
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Delete log confirmation
    const deleteButtons = document.querySelectorAll('.delete-log');
    const logIdElement = document.getElementById('logIdToDelete');
    const confirmDeleteBtn = document.getElementById('confirmDelete');
    const deleteModal = new bootstrap.Modal(document.getElementById('deleteLogModal'));
    
    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            const logId = this.dataset.id;
            logIdElement.textContent = logId;
            confirmDeleteBtn.href = `logs.php?delete_id=${logId}`;
            deleteModal.show();
        });
    });
    
    // Clear all logs button
    const clearAllBtn = document.getElementById('clearAllLogs');
    const clearAllModal = new bootstrap.Modal(document.getElementById('clearAllModal'));
    
    if (clearAllBtn) {
        clearAllBtn.addEventListener('click', function() {
            clearAllModal.show();
        });
    }
    
    // Confirm clear all logs
    const confirmClearAllBtn = document.getElementById('confirmClearAll');
    if (confirmClearAllBtn) {
        confirmClearAllBtn.addEventListener('click', function() {
            // Send AJAX request or redirect to clear logs
            window.location.href = 'logs.php?clear_all=1';
        });
    }
    
    // Refresh button functionality
    document.getElementById('refreshLogs')?.addEventListener('click', function() {
        window.location.reload();
    });
});
</script>

<style>
.badge {
    font-weight: 500;
    padding: 0.5em 0.8em;
}
.table th {
    font-weight: 600;
    color: #495057;
}
.delete-log {
    border-radius: 50%; 
    width: 32px; 
    height: 32px; 
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>
