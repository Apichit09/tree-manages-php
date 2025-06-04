<?php
// logout.php
session_start();

// ลบ session ทั้งหมด
$_SESSION = [];

// ลบ cookie session (ถ้ามี)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// ทำลาย session
session_destroy();

// รีไดเร็กไปหน้า login หรือหน้า public_trees.php
redirect('login.php');
