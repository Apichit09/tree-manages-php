<?php

?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สวนไม้ - ดูต้นไม้</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #2e7d32;
            --secondary-color: #81c784;
            --light-green: #e8f5e9;
        }

        body {
            font-family: 'Sarabun', sans-serif;
            background-color: #fafafa;
        }

        .tree-img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border: 1px solid #ddd;
            border-radius: 4px;
            transition: transform 0.3s ease;
        }

        .tree-img:hover {
            transform: scale(1.05);
        }

        .navbar {
            background-color: var(--light-green) !important;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .navbar-brand {
            color: var(--primary-color) !important;
            font-weight: bold;
        }

        .nav-link {
            color: #333 !important;
            font-weight: 500;
            transition: color 0.2s;
        }

        .nav-link:hover {
            color: var(--primary-color) !important;
        }

        .nav-link.active {
            color: var(--primary-color) !important;
            border-bottom: 2px solid var(--primary-color);
        }

        .navbar-contact {
            color: var(--primary-color);
            font-weight: bold;
        }

        .card {
            transition: transform 0.3s, box-shadow 0.3s;
            border-radius: 8px;
            overflow: hidden;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-primary:hover {
            background-color: #1b5e20;
            border-color: #1b5e20;
        }

        .btn-outline-primary {
            color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-outline-primary:hover {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
    </style>
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-light sticky-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="#">
                <i class="bi bi-tree-fill me-2 text-success"></i>
                สวนไม้
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#publicNav"
                aria-controls="publicNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="publicNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link active" href="/pages/public_trees.php">
                            <i class="bi bi-tree me-1"></i> ดูต้นไม้
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">
                            <i class="bi bi-flower1 me-1"></i> สินค้าแนะนำ
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">
                            <i class="bi bi-info-circle me-1"></i> เกี่ยวกับเรา
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">
                            <i class="bi bi-telephone me-1"></i> ติดต่อเรา
                        </a>
                    </li>
                </ul>
                <span class="navbar-text navbar-contact">
                    <i class="bi bi-telephone-fill me-1"></i> ติดต่อสอบถาม: 081-234-5678
                </span>
            </div>
        </div>
    </nav>
    <main class="py-4">