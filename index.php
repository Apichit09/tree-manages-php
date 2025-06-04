<?php
session_start();
require_once 'config/db.php';
require_once 'includes/functions.php';

if (isLoggedIn()) {
    redirect('pages/dashboard.php');
} else {
    redirect('pages/login.php');
}
