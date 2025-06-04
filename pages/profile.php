<?php
session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$userId = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT id, name, email, username, status, profile_pic FROM users WHERE id = :id LIMIT 1");
$stmt->execute(['id' => $userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    redirect('logout.php');
}

$errors = [];
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $errors[] = "Session หมดอายุ กรุณาลองใหม่อีกครั้ง";
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $confirmPassword = trim($_POST['confirm_password'] ?? '');

        if (empty($name)) {
            $errors[] = "กรุณากรอกชื่อ";
        } elseif (mb_strlen($name) > 100) {
            $errors[] = "ชื่อยาวเกินไป (ไม่เกิน 100 ตัวอักษร)";
        }

        if (empty($email)) {
            $errors[] = "กรุณากรอกอีเมล";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "รูปแบบอีเมลไม่ถูกต้อง";
        } else {
            $stmtEmail = $pdo->prepare("SELECT id FROM users WHERE email = :email AND id != :id LIMIT 1");
            $stmtEmail->execute(['email' => $email, 'id' => $userId]);
            if ($stmtEmail->fetch()) {
                $errors[] = "อีเมลนี้มีผู้ใช้งานแล้ว";
            }
        }

        if (empty($username)) {
            $errors[] = "กรุณากรอกชื่อผู้ใช้ (username)";
        } elseif (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
            $errors[] = "ชื่อผู้ใช้ต้องเป็นตัวอักษร a-z, ตัวเลข หรือ _ (3-20 ตัวอักษร)";
        } else {
            $stmtUser = $pdo->prepare("SELECT id FROM users WHERE username = :username AND id != :id LIMIT 1");
            $stmtUser->execute(['username' => $username, 'id' => $userId]);
            if ($stmtUser->fetch()) {
                $errors[] = "ชื่อผู้ใช้นี้มีคนอื่นใช้แล้ว";
            }
        }

        $newPasswordHash = null;
        if ($password !== '' || $confirmPassword !== '') {
            if (mb_strlen($password) < 6) {
                $errors[] = "รหัสผ่านใหม่ต้องมีความยาวอย่างน้อย 6 ตัวอักษร";
            }
            if ($password !== $confirmPassword) {
                $errors[] = "รหัสผ่านใหม่กับยืนยันรหัสผ่านไม่ตรงกัน";
            }
            if (empty($errors)) {
                $newPasswordHash = password_hash($password, PASSWORD_DEFAULT);
            }
        }

        $profilePic = $user['profile_pic'];
        if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] !== UPLOAD_ERR_NO_FILE) {
            $uploaded = $_FILES['profile_pic'];
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if ($uploaded['error'] === UPLOAD_ERR_OK) {
                if (!in_array($uploaded['type'], $allowedTypes)) {
                    $errors[] = "ไฟล์รูปภาพไม่รองรับ (รองรับ JPEG, PNG, GIF เท่านั้น)";
                }
                if ($uploaded['size'] > 2 * 1024 * 1024) {
                    $errors[] = "ไฟล์รูปภาพต้องไม่เกิน 2MB";
                }
            } else {
                $errors[] = "เกิดข้อผิดพลาดในการอัปโหลดรูปภาพ";
            }

            if (empty($errors)) {
                $profileDir = __DIR__ . '/../uploads/profiles/' . $userId . '/';
                if (!is_dir($profileDir)) {
                    mkdir($profileDir, 0755, true);
                }
                if (!empty($user['profile_pic'])) {
                    $oldPicPath = __DIR__ . '/..' . $user['profile_pic'];
                    if (file_exists($oldPicPath)) {
                        unlink($oldPicPath);
                    }
                }
                $ext = pathinfo($uploaded['name'], PATHINFO_EXTENSION);
                $newFilename = 'profile_' . $userId . '_' . uniqid() . '.' . $ext;
                $destination = $profileDir . $newFilename;
                if (move_uploaded_file($uploaded['tmp_name'], $destination)) {
                    $profilePic = '/uploads/profiles/' . $userId . '/' . $newFilename;
                } else {
                    $errors[] = "ไม่สามารถบันทึกรูปโปรไฟล์ได้ กรุณาลองใหม่";
                }
            }
        }

        if (empty($errors)) {
            $sqlUpdate = "UPDATE users SET name = :name, email = :email, username = :username, profile_pic = :profile_pic";
            $params = [
                'name' => $name,
                'email' => $email,
                'username' => $username,
                'profile_pic' => $profilePic,
                'id' => $userId
            ];

            if ($newPasswordHash !== null) {
                $sqlUpdate .= ", password = :password";
                $params['password'] = $newPasswordHash;
            }

            $sqlUpdate .= " WHERE id = :id";
            $stmtUpd = $pdo->prepare($sqlUpdate);
            $stmtUpd->execute($params);
            $_SESSION['username'] = $username;
            $_SESSION['flash_message'] = "อัปเดตโปรไฟล์เรียบร้อยแล้ว";
            redirect('profile.php');
        }
    }
}

$csrf_token = generateCsrfToken();
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="container">
    <div class="row">
        <div class="col-12">
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php" class="text-decoration-none">หน้าหลัก</a></li>
                    <li class="breadcrumb-item active" aria-current="page">โปรไฟล์ของฉัน</li>
                </ol>
            </nav>
        </div>
    </div>

    <?php if (!empty($_SESSION['flash_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show d-flex align-items-center" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>
            <div><?= e($_SESSION['flash_message']); ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            <?php unset($_SESSION['flash_message']); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger d-flex align-items-center" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <div>
                <ul class="mb-0 ps-3">
                    <?php foreach ($errors as $err): ?>
                        <li><?= e($err); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-4 mb-4 mb-md-0">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-person-circle text-primary me-2"></i>โปรไฟล์
                    </h5>
                </div>
                <div class="card-body text-center">
                    <div class="mb-4 position-relative mx-auto" style="width:150px; height:150px;">
                        <?php if (!empty($user['profile_pic'])): ?>
                            <img src="<?= e($user['profile_pic']); ?>" alt="Profile" class="rounded-circle img-thumbnail"
                                style="width:100%; height:100%; object-fit:cover;">
                        <?php else: ?>
                            <div class="bg-light rounded-circle d-flex align-items-center justify-content-center"
                                style="width:100%; height:100%;">
                                <i class="bi bi-person-fill text-secondary" style="font-size: 4rem;"></i>
                            </div>
                        <?php endif; ?>
                    </div>

                    <h5 class="mt-2 mb-1"><?= e($user['name']); ?></h5>
                    <p class="text-muted mb-2">@<?= e($user['username']); ?></p>
                    <div class="d-flex justify-content-center align-items-center gap-1 mb-3">
                        <span class="badge bg-success">Active</span>
                    </div>

                    <div class="d-flex justify-content-center mb-2">
                        <label for="profile_pic" class="btn btn-outline-primary">
                            <i class="bi bi-camera-fill me-1"></i>เปลี่ยนรูปโปรไฟล์
                        </label>
                    </div>
                    <div class="small text-muted">รองรับไฟล์ JPEG, PNG, GIF ขนาดไม่เกิน 2MB</div>
                </div>
                <div class="card-footer bg-white border-top py-3">
                    <div class="d-flex align-items-center mb-2">
                        <i class="bi bi-envelope me-2 text-muted"></i>
                        <span class="text-truncate"><?= e($user['email']); ?></span>
                    </div>
                    <div class="d-flex align-items-center">
                        <i class="bi bi-shield-check me-2 text-muted"></i>
                        <span>บัญชีสร้างเมื่อ <?= date('d/m/Y') ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-pencil-square text-primary me-2"></i>แก้ไขข้อมูลส่วนตัว
                    </h5>
                </div>
                <div class="card-body">
                    <form action="<?= e(basename($_SERVER['PHP_SELF'])); ?>" method="post" enctype="multipart/form-data"
                        novalidate>
                        <input type="hidden" name="csrf_token" value="<?= e($csrf_token); ?>">
                        <input type="file" name="profile_pic" id="profile_pic" class="d-none" accept="image/*">

                        <div class="mb-3">
                            <label for="name" class="form-label">ชื่อ</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-person"></i></span>
                                <input type="text" name="name" id="name" class="form-control"
                                    value="<?= e($user['name']); ?>" required maxlength="100">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">อีเมล</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                <input type="email" name="email" id="email" class="form-control"
                                    value="<?= e($user['email']); ?>" required>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="username" class="form-label">ชื่อผู้ใช้</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-at"></i></span>
                                <input type="text" name="username" id="username" class="form-control"
                                    value="<?= e($user['username']); ?>" required pattern="[a-zA-Z0-9_]{3,20}">
                            </div>
                            <div class="form-text">ประกอบด้วย a-z, ตัวเลข หรือ _ (3-20 ตัวอักษร)</div>
                        </div>

                        <div class="card bg-light border-0 mb-4">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-3 text-muted">
                                    <i class="bi bi-shield-lock me-1"></i>เปลี่ยนรหัสผ่าน (ไม่บังคับ)
                                </h6>

                                <div class="mb-3">
                                    <label for="password" class="form-label">รหัสผ่านใหม่</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-key"></i></span>
                                        <input type="password" name="password" id="password" class="form-control"
                                            autocomplete="new-password">
                                    </div>
                                    <div class="form-text">เว้นว่างไว้หากไม่ต้องการเปลี่ยน (อย่างน้อย 6 ตัวอักษร)</div>
                                </div>

                                <div class="mb-0">
                                    <label for="confirm_password" class="form-label">ยืนยันรหัสผ่านใหม่</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-shield"></i></span>
                                        <input type="password" name="confirm_password" id="confirm_password"
                                            class="form-control" autocomplete="new-password">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end">
                            <a href="dashboard.php" class="btn btn-outline-secondary me-2">
                                <i class="bi bi-x-circle me-1"></i>ยกเลิก
                            </a>
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-save me-1"></i>บันทึกการเปลี่ยนแปลง
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelector('label[for="profile_pic"]').addEventListener('click', function () {
            document.getElementById('profile_pic').click();
        });

        document.getElementById('profile_pic').addEventListener('change', function () {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    const profileImages = document.querySelectorAll('.rounded-circle.img-thumbnail, .bg-light.rounded-circle');
                    profileImages.forEach(img => {
                        if (img.tagName === 'IMG') {
                            img.src = e.target.result;
                        } else {
                            const parent = img.parentNode;
                            const newImg = document.createElement('img');
                            newImg.src = e.target.result;
                            newImg.alt = 'Profile';
                            newImg.classList.add('rounded-circle', 'img-thumbnail');
                            newImg.style.width = '100%';
                            newImg.style.height = '100%';
                            newImg.style.objectFit = 'cover';
                            parent.replaceChild(newImg, img);
                        }
                    });
                }
                reader.readAsDataURL(this.files[0]);
            }
        });
    });
</script>

<style>
    .btn-success {
        background-color: #2e7d32;
        border-color: #2e7d32;
    }

    .btn-success:hover {
        background-color: #1b5e20;
        border-color: #1b5e20;
    }

    .form-control:focus {
        border-color: #81c784;
        box-shadow: 0 0 0 0.25rem rgba(46, 125, 50, 0.25);
    }

    .img-thumbnail {
        padding: 0.25rem;
        background-color: #fff;
        border: 1px solid #dee2e6;
        border-radius: 50%;
        max-width: 100%;
        height: auto;
    }
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>