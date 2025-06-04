<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    redirect('trees.php');
}
$treeId = (int) $_GET['id'];

$pdo->beginTransaction();
try {
    $stmtFetchImages = $pdo->prepare("
        SELECT image_url 
        FROM tree_images 
        WHERE tree_id = :tid
    ");
    $stmtFetchImages->execute(['tid' => $treeId]);
    $images = $stmtFetchImages->fetchAll(PDO::FETCH_ASSOC);

    foreach ($images as $row) {
        $url = $row['image_url'];
        if ($url) {
            $relativePath = ltrim($url, '/');
            $fullPath = __DIR__ . '/../' . $relativePath;
            if (is_file($fullPath)) {
                unlink($fullPath);
            }
        }
    }

    $uploadDir = __DIR__ . '/../uploads/trees/' . $treeId;
    if (is_dir($uploadDir)) {
        $filesInDir = array_diff(scandir($uploadDir), ['.', '..']);
        if (empty($filesInDir)) {
            rmdir($uploadDir);
        }
    }

    $stmtDelImages = $pdo->prepare("DELETE FROM tree_images WHERE tree_id = :tid");
    $stmtDelImages->execute(['tid' => $treeId]);

    $stmtDelTree = $pdo->prepare("DELETE FROM trees WHERE id = :tid");
    $stmtDelTree->execute(['tid' => $treeId]);

    $pdo->commit();
    $_SESSION['flash_message'] = "ลบต้นไม้ ID={$treeId} เรียบร้อยแล้ว";
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['flash_message'] = "เกิดข้อผิดพลาดขณะลบต้นไม้: " . e($e->getMessage());
}

redirect('trees.php');
