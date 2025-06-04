<?php
// pages/public_trees.php

// ไม่มี session_start() เพราะหน้า Public ไม่ต้องตรวจล็อกอิน

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

// ฟังก์ชันช่วยดึงรูปภาพตัวแรก (thumbnail) ของต้นไม้แต่ละต้น
function getFirstImage(PDO $pdo, int $treeId) {
    $stmt = $pdo->prepare("SELECT image_url FROM tree_images WHERE tree_id = :tid LIMIT 1");
    $stmt->execute(['tid' => $treeId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? $row['image_url'] : null;
}

// 1) ดึงข้อมูลต้นไม้ทั้งหมดที่มีจำนวน > 0 พร้อมชื่อหมวดหมู่
$sql = "
    SELECT t.id,
           t.name,
           t.size,
           t.price,
           t.quantity,
           DATE_FORMAT(t.added_at, '%d/%m/%Y') AS added_date,
           c.name AS category_name
    FROM trees t
    LEFT JOIN categories c ON t.category_id = c.id
    WHERE t.quantity > 0
    ORDER BY t.added_at DESC
";
$stmt = $pdo->query($sql);
$trees = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<?php include __DIR__ . '/../includes/public_header.php'; ?>

<div class="container">
    <h2 class="mt-4 mb-3">🌳 รายการต้นไม้ทั้งหมด</h2>
    <p>คุณสามารถดูรายละเอียดต้นไม้ของเราได้โดยไม่ต้องล็อกอิน</p>

    <?php if (empty($trees)): ?>
        <div class="alert alert-info">
            ขณะนี้ยังไม่มีต้นไม้คงเหลือในสต็อก
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover table-bordered">
                <thead class="thead-light">
                    <tr>
                        <th style="width:5%;">#</th>
                        <th style="width:15%;">รูป</th>
                        <th style="width:25%;">ชื่อ</th>
                        <th style="width:15%;">หมวดหมู่</th>
                        <th style="width:10%;">ขนาด</th>
                        <th style="width:15%;">ราคา (บาท)</th>
                        <th style="width:10%;">จำนวนคงเหลือ</th>
                        <th style="width:15%;">วันที่เพิ่ม</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($trees as $index => $tree): ?>
                        <?php
                        // ดึง URL รูปแรก ถ้ามี
                        $thumb = getFirstImage($pdo, $tree['id']);
                        ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td class="text-center">
                                <?php if ($thumb): ?>
                                    <img src="<?php echo e($thumb); ?>" alt="thumbnail" class="tree-img">
                                <?php else: ?>
                                    <span class="text-muted">ไม่มีรูป</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo e($tree['name']); ?></td>
                            <td><?php echo e($tree['category_name'] ?? '-'); ?></td>
                            <td><?php echo e($tree['size']); ?></td>
                            <td class="text-right"><?php echo number_format($tree['price'], 2); ?></td>
                            <td class="text-center"><?php echo (int)$tree['quantity']; ?></td>
                            <td><?php echo e($tree['added_date']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="mt-2"><em>หากต้องการขอใบเสนอราคาหรือติดต่อซื้อ สามารถบันทึกข้อมูลต้นไม้ไปติดต่อกลับได้</em></p>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/public_footer.php'; ?>
