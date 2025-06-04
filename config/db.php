<?php
$host = 'localhost';
$db   = 'tree_garden_db';
$user = 'root';
$pass = 'root';
$charset = 'utf8mb4';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=$charset", $user, $pass);
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage();
    exit;
}
?>
