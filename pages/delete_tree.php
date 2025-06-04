<?php
// pages/delete_tree.php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

// ถ้ายังไม่ล็อกอิน → เด้งไปหน้า login
if (!isLoggedIn()) {
    redirect('login.php');
}

// รับ tree ID จาก GET
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    redirect('trees.php');
}
$treeId = (int)$_GET['id'];

$pdo->beginTransaction();
try {
    // 1) ดึง URL ของรูปทั้งหมดที่เกี่ยวข้องกับต้นไม้
    $stmtFetchImages = $pdo->prepare("
        SELECT image_url 
        FROM tree_images 
        WHERE tree_id = :tid
    ");
    $stmtFetchImages->execute(['tid' => $treeId]);
    $images = $stmtFetchImages->fetchAll(PDO::FETCH_ASSOC);

    // 2) ลบไฟล์รูปแต่ละไฟล์จากระบบไฟล์ (ถ้ามี)
    foreach ($images as $row) {
        $url = $row['image_url'];
        if ($url) {
            // สมมติ image_url เป็น path แบบ relative เช่น "/uploads/trees/1/img.jpg"
            $relativePath = ltrim($url, '/');
            $fullPath = __DIR__ . '/../' . $relativePath;
            if (is_file($fullPath)) {
                unlink($fullPath);
            }
        }
    }

    // 3) ลบโฟลเดอร์ย่อยถ้ายังว่างอยู่ (uploads/trees/<treeId>/)
    $uploadDir = __DIR__ . '/../uploads/trees/' . $treeId;
    if (is_dir($uploadDir)) {
        // เช็คว่าโฟลเดอร์ว่างหรือไม่
        $filesInDir = array_diff(scandir($uploadDir), ['.', '..']);
        if (empty($filesInDir)) {
            rmdir($uploadDir);
        }
    }

    // 4) ลบ record ใน tree_images (ถ้ามี FK ON DELETE CASCADE จะลบอัตโนมัติ แต่ลบไว้เผื่อไม่มี)
    $stmtDelImages = $pdo->prepare("DELETE FROM tree_images WHERE tree_id = :tid");
    $stmtDelImages->execute(['tid' => $treeId]);

    // 5) ลบ record ใน trees
    $stmtDelTree = $pdo->prepare("DELETE FROM trees WHERE id = :tid");
    $stmtDelTree->execute(['tid' => $treeId]);

    $pdo->commit();
    $_SESSION['flash_message'] = "ลบต้นไม้ ID={$treeId} เรียบร้อยแล้ว";
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['flash_message'] = "เกิดข้อผิดพลาดขณะลบต้นไม้: " . e($e->getMessage());
}

redirect('trees.php');
