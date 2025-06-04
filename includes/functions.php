<?php
// includes/functions.php

session_start(); // เริ่ม session หากยังไม่ได้เริ่ม

/**
 * ตรวจสอบว่าผู้ใช้ล็อกอินอยู่หรือไม่
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * ฟังก์ชันสำหรับ redirect ไปหน้าอื่น
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * สร้าง CSRF token และเก็บไว้ใน session
 */
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * ตรวจสอบ CSRF token ที่ส่งมาจากฟอร์ม
 */
function verifyCsrfToken($token) {
    if (isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token)) {
        return true;
    }
    return false;
}

/**
 * ฟังก์ชันสำหรับทำ sanitize ข้อมูลที่มาจากผู้ใช้
 */
function e($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}
?>
