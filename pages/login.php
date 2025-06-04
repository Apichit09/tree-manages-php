<?php
session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (isLoggedIn()) {
    redirect('dashboard.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $errors[] = "Session หมดอายุ กรุณาลองใหม่อีกครั้ง";
    } else {
        $username_or_email = trim($_POST['username_or_email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username_or_email)) {
            $errors[] = "กรุณากรอกชื่อผู้ใช้หรืออีเมล";
        }
        if (empty($password)) {
            $errors[] = "กรุณากรอกรหัสผ่าน";
        }

        if (empty($errors)) {
            $sql = "SELECT id, name, username, email, password, status 
                    FROM users 
                    WHERE username = :ue OR email = :ue 
                    LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['ue' => $username_or_email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                if (password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['status'] = $user['status'];
                    unset($_SESSION['csrf_token']);
                    redirect('dashboard.php');
                } else {
                    $errors[] = "รหัสผ่านไม่ถูกต้อง";
                }
            } else {
                $errors[] = "ไม่พบชื่อผู้ใช้หรืออีเมลนี้ในระบบ";
            }
        }
    }
}

$csrf_token = generateCsrfToken();
?>

<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card border-0 shadow-sm mt-5">
                <div class="card-header text-center bg-white py-3 border-0">
                    <h4 class="card-title mb-0">
                        <i class="bi bi-box-arrow-in-right text-primary me-2"></i>เข้าสู่ระบบ
                    </h4>
                </div>

                <div class="card-body p-4">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger d-flex align-items-center" role="alert">
                            <i class="bi bi-exclamation-triangle-fill flex-shrink-0 me-2"></i>
                            <div>
                                <ul class="mb-0 ps-3">
                                    <?php foreach ($errors as $err): ?>
                                        <li><?= e($err) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    <?php endif; ?>

                    <form action="<?= e(basename($_SERVER['PHP_SELF'])) ?>" method="post" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">

                        <div class="mb-3">
                            <label for="username_or_email" class="form-label">ชื่อผู้ใช้หรืออีเมล</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">
                                    <i class="bi bi-person"></i>
                                </span>
                                <input type="text" id="username_or_email" name="username_or_email" class="form-control"
                                    placeholder="กรอกชื่อผู้ใช้หรืออีเมล"
                                    value="<?= isset($username_or_email) ? e($username_or_email) : '' ?>" required>
                            </div>
                        </div>

                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center">
                                <label for="password" class="form-label">รหัสผ่าน</label>
                                <a href="#" class="text-decoration-none small">ลืมรหัสผ่าน?</a>
                            </div>
                            <div class="input-group">
                                <span class="input-group-text bg-light">
                                    <i class="bi bi-lock"></i>
                                </span>
                                <input type="password" id="password" name="password" class="form-control"
                                    placeholder="กรอกรหัสผ่าน" required>
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="rememberMe">
                            <label class="form-check-label" for="rememberMe">จดจำฉันในระบบ</label>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary py-2">
                                <i class="bi bi-box-arrow-in-right me-2"></i>เข้าสู่ระบบ
                            </button>
                        </div>
                    </form>
                </div>

                <div class="card-footer bg-white text-center border-0 py-3">
                    <p class="mb-0">ยังไม่มีบัญชีผู้ใช้?
                        <a href="register.php" class="text-decoration-none">สมัครสมาชิก</a>
                    </p>
                </div>
            </div>

            <div class="text-center mt-4">
                <p class="text-muted small">
                    <i class="bi bi-info-circle me-1"></i>
                    หากมีปัญหาการเข้าสู่ระบบ กรุณาติดต่อผู้ดูแลระบบ
                </p>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');

        if (togglePassword && passwordInput) {
            togglePassword.addEventListener('click', function () {
                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    togglePassword.innerHTML = '<i class="bi bi-eye-slash"></i>';
                } else {
                    passwordInput.type = 'password';
                    togglePassword.innerHTML = '<i class="bi bi-eye"></i>';
                }
            });
        }
    });
</script>

<style>
    .btn-primary {
        background-color: var(--primary-color);
        border-color: var(--primary-color);
    }

    .btn-primary:hover {
        background-color: #1b5e20;
        border-color: #1b5e20;
    }

    .form-control:focus,
    .form-select:focus,
    .form-check-input:focus {
        border-color: var(--secondary-color);
        box-shadow: 0 0 0 0.25rem rgba(46, 125, 50, 0.25);
    }

    .form-check-input:checked {
        background-color: var(--primary-color);
        border-color: var(--primary-color);
    }

    .text-primary {
        color: var(--primary-color) !important;
    }

    a {
        color: var(--primary-color);
    }

    a:hover {
        color: #1b5e20;
    }

    .card {
        border-radius: 10px;
    }
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>