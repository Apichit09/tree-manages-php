<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/functions.php';
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบจัดการสวนไม้</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/public/css/style.css">
    <style>
        :root {
            --primary-color: #2e7d32;
            --secondary-color: #81c784;
        }

        body {
            font-family: 'Sarabun', sans-serif;
            background-color: #f8f9fa;
        }

        .navbar {
            background-color: var(--primary-color) !important;
            box-shadow: 0 2px 4px rgba(0, 0, 0, .1);
        }

        .navbar-brand,
        .nav-link {
            color: white !important;
        }

        .nav-link:hover {
            color: var(--secondary-color) !important;
        }

        .nav-link.active {
            font-weight: bold;
            border-bottom: 2px solid var(--secondary-color);
        }

        main {
            min-height: calc(100vh - 160px);
            padding: 2rem 0;
        }
    </style>
</head>

<body>
    <header>
        <nav class="navbar navbar-expand-lg navbar-dark">
            <div class="container">
                <a class="navbar-brand d-flex align-items-center" href="/tree-manages/">
                    <i class="bi bi-tree-fill me-2"></i>
                    <span>ระบบจัดการสวนไม้</span>
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                    aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ms-auto">
                        <?php if (isLoggedIn()): ?>
                            <li class="nav-item"><a class="nav-link" href="/tree-manages/pages/dashboard.php"><i
                                        class="bi bi-speedometer2 me-1"></i> Dashboard</a></li>
                            <li class="nav-item"><a class="nav-link" href="/tree-manages/pages/trees.php"><i
                                        class="bi bi-tree me-1"></i> จัดการต้นไม้</a></li>
                            <li class="nav-item"><a class="nav-link" href="/tree-manages/pages/orders.php"><i
                                        class="bi bi-cart me-1"></i> ออเดอร์/บิล</a></li>
                            <li class="nav-item"><a class="nav-link" href="/tree-manages/pages/logs.php"><i
                                        class="bi bi-bell me-1"></i> แจ้งเตือน</a></li>
                            <li class="nav-item"><a class="nav-link" href="/tree-manages/pages/profile.php"><i
                                        class="bi bi-person-circle me-1"></i> โปรไฟล์</a></li>
                            <li class="nav-item"><a class="nav-link" href="/tree-manages/pages/logout.php"><i
                                        class="bi bi-box-arrow-right me-1"></i> Logout</a></li>
                        <?php else: ?>
                            <li class="nav-item"><a class="nav-link" href="/tree-manages/pages/login.php"><i
                                        class="bi bi-box-arrow-in-right me-1"></i> Login</a></li>
                            <li class="nav-item"><a class="nav-link" href="/tree-manages/pages/register.php"><i
                                        class="bi bi-person-plus me-1"></i> Register</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </nav>
    </header>
    <main class="container py-4">