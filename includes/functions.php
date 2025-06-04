<?php
session_start();

function isLoggedIn()
{
    return isset($_SESSION['user_id']);
}

function redirect($url)
{
    header("Location: $url");
    exit;
}

function generateCsrfToken()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token)
{
    if (isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token)) {
        return true;
    }
    return false;
}

function e($str)
{
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}
?>