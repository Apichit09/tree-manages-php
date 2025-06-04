<?php
// pages/register.php
session_start();

// เรียกไฟล์เชื่อมต่อฐานข้อมูล และฟังก์ชันช่วยเหลือ
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

// ถ้าผู้ใช้ล็อกอินแล้ว ให้เด้งกลับไปหน้า dashboard
if (isLoggedIn()) {
    redirect('dashboard.php');
}

// เก็บข้อความ error/ success
$errors = [];
$success = "";

// กรณีมีการกดปุ่มสมัคร (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1) ตรวจสอบ CSRF token
    if (empty($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $errors[] = "Session หมดอายุ กรุณาลองใหม่อีกครั้ง";
    } else {
        // 2) sanitize ข้อมูลจากฟอร์ม
        $name             = trim($_POST['name'] ?? '');
        $email            = trim($_POST['email'] ?? '');
        $username         = trim($_POST['username'] ?? '');
        $password         = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        // 3) ตรวจสอบความสมบูรณ์ของข้อมูล
        if (empty($name)) {
            $errors[] = "กรุณากรอกชื่อ";
        }
        if (empty($email)) {
            $errors[] = "กรุณากรอกอีเมล";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "รูปแบบอีเมลไม่ถูกต้อง";
        }
        if (empty($username)) {
            $errors[] = "กรุณากรอกชื่อผู้ใช้ (username)";
        } elseif (strlen($username) < 4) {
            $errors[] = "ชื่อผู้ใช้ต้องมีอย่างน้อย 4 ตัวอักษร";
        }
        if (empty($password)) {
            $errors[] = "กรุณากรอกรหัสผ่าน";
        } elseif (strlen($password) < 8) {
            $errors[] = "รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร";
        }
        if ($password !== $confirm_password) {
            $errors[] = "รหัสผ่านไม่ตรงกัน";
        }

        // 4) ถ้าไม่มี error ในเบื้องต้น ให้ตรวจสอบความซ้ำซ้อนของ email และ username
        if (empty($errors)) {
            // ตรวจสอบ email ซ้ำ
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
            $stmt->execute(['email' => $email]);
            if ($stmt->fetch()) {
                $errors[] = "อีเมลนี้ถูกใช้งานแล้ว";
            }

            // ตรวจสอบ username ซ้ำ
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :username LIMIT 1");
            $stmt->execute(['username' => $username]);
            if ($stmt->fetch()) {
                $errors[] = "ชื่อผู้ใช้นี้ถูกใช้งานแล้ว";
            }
        }

        // 5) ถ้ายังไม่มี error ให้ทำการ insert ข้อมูล
        if (empty($errors)) {
            // เข้ารหัสรหัสผ่าน
            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            $sql = "INSERT INTO users (name, email, username, password, created_at, status) 
                    VALUES (:name, :email, :username, :password, NOW(), 'customer')";
            $stmt = $pdo->prepare($sql);
            try {
                $stmt->execute([
                    'name'     => $name,
                    'email'    => $email,
                    'username' => $username,
                    'password' => $password_hash
                ]);

                $success = "สมัครสมาชิกเรียบร้อย สามารถ <a href='login.php'>เข้าสู่ระบบ</a> ได้ทันที";
                // ล้างข้อมูลในตัวแปรเพื่อไม่ให้แสดงซ้ำตอน refresh
                $name = $email = $username = '';
            } catch (Exception $e) {
                $errors[] = "เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . $e->getMessage();
            }
        }
    }
}

// สร้าง CSRF token ใหม่ (หรือคงตัวเดิมไว้ถ้ามีแล้ว)
$csrf_token = generateCsrfToken();
?>

<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-7">
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-header text-center bg-white py-3 border-0">
                    <h4 class="card-title mb-0">
                        <i class="bi bi-person-plus text-primary me-2"></i>สมัครสมาชิก
                    </h4>
                </div>
                
                <div class="card-body p-4">
                    <!-- Success Message -->
                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success alert-dismissible fade show d-flex align-items-center" role="alert">
                            <i class="bi bi-check-circle-fill flex-shrink-0 me-2"></i>
                            <div><?= $success ?></div>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Error Message -->
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

                    <!-- Form สมัครสมาชิก -->
                    <form action="<?= e(basename($_SERVER['PHP_SELF'])) ?>" method="post" novalidate class="needs-validation">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                        
                        <div class="mb-3">
                            <label for="name" class="form-label">ชื่อ-นามสกุล</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">
                                    <i class="bi bi-person"></i>
                                </span>
                                <input type="text" id="name" name="name" class="form-control" 
                                       placeholder="กรอกชื่อ-นามสกุล"
                                       value="<?= isset($name) ? e($name) : '' ?>" required>
                                <div class="invalid-feedback">
                                    กรุณากรอกชื่อ-นามสกุล
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">อีเมล</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">
                                    <i class="bi bi-envelope"></i>
                                </span>
                                <input type="email" id="email" name="email" class="form-control" 
                                       placeholder="example@domain.com"
                                       value="<?= isset($email) ? e($email) : '' ?>" required>
                                <div class="invalid-feedback">
                                    กรุณากรอกอีเมลให้ถูกต้อง
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="username" class="form-label">ชื่อผู้ใช้</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">
                                    <i class="bi bi-at"></i>
                                </span>
                                <input type="text" id="username" name="username" class="form-control" 
                                       placeholder="username (4 ตัวอักษรขึ้นไป)"
                                       value="<?= isset($username) ? e($username) : '' ?>" required>
                                <div class="invalid-feedback">
                                    ชื่อผู้ใช้ต้องมีอย่างน้อย 4 ตัวอักษร
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">รหัสผ่าน</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">
                                    <i class="bi bi-lock"></i>
                                </span>
                                <input type="password" id="password" name="password" class="form-control" 
                                       placeholder="อย่างน้อย 8 ตัวอักษร" required>
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <div class="invalid-feedback">
                                    รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร
                                </div>
                            </div>
                            <div class="form-text">
                                <i class="bi bi-info-circle me-1"></i>รหัสผ่านควรมีอย่างน้อย 8 ตัวอักษร
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="confirm_password" class="form-label">ยืนยันรหัสผ่าน</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">
                                    <i class="bi bi-shield-lock"></i>
                                </span>
                                <input type="password" id="confirm_password" name="confirm_password" 
                                       class="form-control" placeholder="กรอกรหัสผ่านอีกครั้ง" required>
                                <div class="invalid-feedback">
                                    กรุณายืนยันรหัสผ่าน
                                </div>
                            </div>
                        </div>

                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="agree_terms" required>
                            <label class="form-check-label" for="agree_terms">
                                ฉันยอมรับ <a href="#" class="text-decoration-none">เงื่อนไขการใช้งานและนโยบายความเป็นส่วนตัว</a>
                            </label>
                            <div class="invalid-feedback">
                                กรุณายอมรับเงื่อนไขการใช้งาน
                            </div>
                        </div>

                        <div class="d-grid gap-2 mb-3">
                            <button type="submit" class="btn btn-primary py-2">
                                <i class="bi bi-person-plus-fill me-2"></i>สมัครสมาชิก
                            </button>
                        </div>
                    </form>
                </div>
                
                <div class="card-footer bg-white text-center border-0 py-3">
                    <p class="mb-0">มีบัญชีผู้ใช้แล้ว? 
                        <a href="login.php" class="text-decoration-none">เข้าสู่ระบบ</a>
                    </p>
                </div>
            </div>
            
            <!-- คำแนะนำการสมัครสมาชิก -->
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-body">
                    <h5 class="card-title">
                        <i class="bi bi-lightbulb text-warning me-2"></i>คำแนะนำการสมัครสมาชิก
                    </h5>
                    <ul class="mb-0">
                        <li>ใช้อีเมลที่ใช้งานได้จริง เพื่อรับแจ้งเตือนและยืนยันตัวตน</li>
                        <li>รหัสผ่านควรผสมตัวอักษร ตัวเลข และอักขระพิเศษเพื่อความปลอดภัย</li>
                        <li>โปรดจำชื่อผู้ใช้และรหัสผ่านของคุณ</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle password visibility
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');
    
    if (togglePassword && passwordInput) {
        togglePassword.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.innerHTML = type === 'password' ? '<i class="bi bi-eye"></i>' : '<i class="bi bi-eye-slash"></i>';
        });
    }
    
    // Password matching validation
    const confirmPasswordInput = document.getElementById('confirm_password');
    const form = document.querySelector('form');
    
    if (form) {
        form.addEventListener('submit', function(event) {
            if (passwordInput.value !== confirmPasswordInput.value) {
                event.preventDefault();
                confirmPasswordInput.setCustomValidity("รหัสผ่านไม่ตรงกัน");
            } else {
                confirmPasswordInput.setCustomValidity("");
            }
        });
        
        // Clear custom validity when typing
        confirmPasswordInput.addEventListener('input', function() {
            if (passwordInput.value === confirmPasswordInput.value) {
                confirmPasswordInput.setCustomValidity("");
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
.alert {
    border-radius: 8px;
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>
