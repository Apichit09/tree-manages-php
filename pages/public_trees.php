<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

function getFirstImage(PDO $pdo, int $treeId)
{
    $stmt = $pdo->prepare("SELECT image_url FROM tree_images WHERE tree_id = :tid LIMIT 1");
    $stmt->execute(['tid' => $treeId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? $row['image_url'] : null;
}

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

<div class="container py-4">
    <div class="text-center mb-4">
        <h1 class="h3">🌳 รายการต้นไม้ทั้งหมด</h1>
        <p class="text-muted">ดูต้นไม้ของเราได้โดยไม่ต้องล็อกอิน</p>
    </div>

    <?php if (empty($trees)): ?>
        <div class="alert alert-info text-center">ไม่มีต้นไม้คงเหลือในสต็อก</div>
    <?php else: ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>รูป</th>
                                <th>ชื่อ</th>
                                <th>หมวดหมู่</th>
                                <th>ขนาด</th>
                                <th class="text-end">ราคา (฿)</th>
                                <th class="text-center">จำนวน</th>
                                <th>วันที่เพิ่ม</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($trees as $i => $tree):
                                $thumb = getFirstImage($pdo, $tree['id']);
                                ?>
                                <tr>
                                    <td><?php echo $i + 1; ?></td>
                                    <td class="text-center">
                                        <?php if ($thumb): ?>
                                            <img src="<?= e($thumb) ?>" alt="thumb" class="tree-img">
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= e($tree['name']) ?></td>
                                    <td><?= e($tree['category_name'] ?? '-') ?></td>
                                    <td><?= e($tree['size']) ?></td>
                                    <td class="text-end"><?= number_format($tree['price'], 2) ?></td>
                                    <td class="text-center"><?= (int) $tree['quantity'] ?></td>
                                    <td><?= e($tree['added_date']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <p class="text-center text-muted"><em>หากต้องการขอใบเสนอราคาหรือติดต่อซื้อ
                สามารถบันทึกข้อมูลต้นไม้และติดต่อกลับได้</em></p>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/public_footer.php'; ?>