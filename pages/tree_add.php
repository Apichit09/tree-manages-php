<?php

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$stmtCat = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC");
$categories = $stmtCat->fetchAll(PDO::FETCH_ASSOC);

$errors = [];
$name = $size = $price = $quantity = '';
$category_id = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $errors[] = "Session หมดอายุ กรุณาลองใหม่อีกครั้ง";
    } else {
        $name = trim($_POST['name'] ?? '');
        $size = trim($_POST['size'] ?? '');
        $price = trim($_POST['price'] ?? '');
        $quantity = trim($_POST['quantity'] ?? '');
        $category_id = (int) ($_POST['category_id'] ?? 0);

        if ($name === '') {
            $errors[] = "กรุณากรอกชื่อต้นไม้";
        }
        if ($size === '') {
            $errors[] = "กรุณากรอกขนาดต้นไม้";
        }
        if ($price === '' || !is_numeric($price) || (float) $price < 0) {
            $errors[] = "กรุณากรอกราคาที่ถูกต้อง";
        }
        if ($quantity === '' || !ctype_digit($quantity) || (int) $quantity < 0) {
            $errors[] = "กรุณากรอกจำนวนต้นไม้เป็นตัวเลข ≥ 0";
        }
        $validCat = false;
        foreach ($categories as $cat) {
            if ($cat['id'] === $category_id) {
                $validCat = true;
                break;
            }
        }
        if (!$validCat) {
            $errors[] = "กรุณาเลือกหมวดหมู่ที่ถูกต้อง";
        }

        $uploadedFiles = $_FILES['images'] ?? null;
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        if ($uploadedFiles && isset($uploadedFiles['name']) && is_array($uploadedFiles['name'])) {
            for ($i = 0; $i < count($uploadedFiles['name']); $i++) {
                if ($uploadedFiles['error'][$i] === UPLOAD_ERR_OK) {
                    if (!in_array($uploadedFiles['type'][$i], $allowedTypes)) {
                        $errors[] = "ไฟล์รูปภาพที่อัปโหลดไม่รองรับ (JPEG, PNG, GIF เท่านั้น)";
                        break;
                    }
                    if ($uploadedFiles['size'][$i] > 2 * 1024 * 1024) {
                        $errors[] = "ไฟล์รูปภาพแต่ละไฟล์ต้องไม่เกิน 2MB";
                        break;
                    }
                } elseif ($uploadedFiles['error'][$i] !== UPLOAD_ERR_NO_FILE) {
                    $errors[] = "เกิดข้อผิดพลาดในการอัปโหลดไฟล์รูปภาพ";
                    break;
                }
            }
        }

        if (empty($errors)) {
            $sql = "INSERT INTO trees (name, size, price, quantity, added_at, category_id, died, sold)
                    VALUES (:name, :size, :price, :quantity, NOW(), :cat_id, 0, 0)";
            $stmt = $pdo->prepare($sql);

            try {
                $stmt->execute([
                    'name' => $name,
                    'size' => $size,
                    'price' => (float) $price,
                    'quantity' => (int) $quantity,
                    'cat_id' => $category_id
                ]);
                $newTreeId = (int) $pdo->lastInsertId();

                if ($uploadedFiles && isset($uploadedFiles['name']) && is_array($uploadedFiles['name'])) {
                    $treeDir = __DIR__ . '/../uploads/trees/' . $newTreeId . '/';
                    if (!is_dir($treeDir)) {
                        if (!mkdir($treeDir, 0755, true) && !is_dir($treeDir)) {
                            throw new Exception('ไม่สามารถสร้างโฟลเดอร์เก็บรูปภาพได้');
                        }
                    }

                    for ($i = 0; $i < count($uploadedFiles['name']); $i++) {
                        if ($uploadedFiles['error'][$i] === UPLOAD_ERR_OK) {
                            $tmpName = $uploadedFiles['tmp_name'][$i];
                            $origName = basename($uploadedFiles['name'][$i]);
                            $ext = pathinfo($origName, PATHINFO_EXTENSION);
                            $newFilename = uniqid('tree_' . $newTreeId . '_') . '.' . $ext;
                            $destination = $treeDir . $newFilename;

                            if (move_uploaded_file($tmpName, $destination)) {
                                $url = '/uploads/trees/' . $newTreeId . '/' . $newFilename;
                                $stmtImg = $pdo->prepare("INSERT INTO tree_images (tree_id, image_url) VALUES (:tid, :url)");
                                $stmtImg->execute([
                                    'tid' => $newTreeId,
                                    'url' => $url
                                ]);
                            }
                        }
                    }
                }

                $_SESSION['flash_message'] = "เพิ่มต้นไม้ใหม่เรียบร้อยแล้ว";
                redirect('trees.php');
            } catch (Exception $e) {
                $errors[] = "เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . e($e->getMessage());
            }
        }
    }
}

$csrf_token = generateCsrfToken();
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="container py-4">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="trees.php" class="text-decoration-none">จัดการต้นไม้</a></li>
            <li class="breadcrumb-item active" aria-current="page">เพิ่มต้นไม้ใหม่</li>
        </ol>
    </nav>

    <div class="row">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-plus-circle-fill text-success me-2"></i>เพิ่มต้นไม้ใหม่
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger d-flex align-items-center" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <div>
                                <ul class="mb-0 ps-3">
                                    <?php foreach ($errors as $err): ?>
                                        <li><?= e($err) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    <?php endif; ?>

                    <form action="<?= e(basename($_SERVER['PHP_SELF'])) ?>" method="post" enctype="multipart/form-data"
                        novalidate class="needs-validation">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">

                        <div class="mb-3">
                            <label for="name" class="form-label">ชื่อต้นไม้</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-tree"></i></span>
                                <input type="text" id="name" name="name" class="form-control" value="<?= e($name) ?>"
                                    required placeholder="ระบุชื่อต้นไม้">
                                <div class="invalid-feedback">กรุณากรอกชื่อต้นไม้</div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="size" class="form-label">ขนาด</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-rulers"></i></span>
                                    <input type="text" id="size" name="size" class="form-control"
                                        value="<?= e($size) ?>" required placeholder="เช่น กระถาง 6 นิ้ว, สูง 30 ซม.">
                                    <div class="invalid-feedback">กรุณากรอกขนาดต้นไม้</div>
                                </div>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label for="price" class="form-label">ราคา (฿)</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-tag"></i></span>
                                    <input type="number" step="0.01" min="0" id="price" name="price"
                                        class="form-control" value="<?= e($price) ?>" required placeholder="0.00">
                                    <div class="invalid-feedback">กรุณากรอกราคาที่ถูกต้อง</div>
                                </div>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label for="quantity" class="form-label">จำนวนต้นไม้</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-hash"></i></span>
                                    <input type="number" id="quantity" name="quantity" class="form-control"
                                        value="<?= e($quantity) ?>" required min="0" placeholder="0">
                                    <div class="invalid-feedback">กรุณากรอกจำนวนต้นไม้เป็นตัวเลข ≥ 0</div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="category_id" class="form-label">หมวดหมู่</label>
                            <select id="category_id" name="category_id" class="form-select" required>
                                <option value="" selected disabled>-- เลือกหมวดหมู่ --</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" <?= ($category_id === (int) $cat['id']) ? 'selected' : '' ?>>
                                        <?= e($cat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">กรุณาเลือกหมวดหมู่</div>
                        </div>

                        <div class="mb-4">
                            <label for="images" class="form-label">รูปภาพต้นไม้</label>
                            <div class="card bg-light border p-3">
                                <div class="file-upload-wrapper">
                                    <label for="images" class="d-block mb-3 text-center">
                                        <div class="upload-area p-5 bg-white rounded border-2 border-dashed d-flex flex-column align-items-center justify-content-center"
                                            id="drop-area" style="cursor: pointer; border-style: dashed;">
                                            <i class="bi bi-cloud-arrow-up fs-1 text-success"></i>
                                            <span class="mt-2">คลิกเพื่อเลือกไฟล์หรือลากไฟล์มาวางที่นี่</span>
                                            <small class="text-muted mt-1">อัปโหลดได้หลายไฟล์ (JPEG, PNG, GIF)</small>
                                        </div>
                                    </label>
                                    <input type="file" id="images" name="images[]" class="d-none" multiple
                                        accept="image/jpeg,image/png,image/gif">
                                    <div class="invalid-feedback">ไฟล์รูปภาพไม่ถูกต้อง</div>
                                </div>
                                <div id="preview-container" class="row g-2 mt-2">
                                </div>
                                <small class="form-text text-muted mt-2">
                                    <i class="bi bi-info-circle me-1"></i> ไฟล์ JPEG, PNG, GIF ขนาดไม่เกิน 2MB ต่อไฟล์
                                </small>
                            </div>
                        </div>

                        <div class="d-flex mt-4 border-top pt-3">
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-check-circle me-1"></i> บันทึก
                            </button>
                            <a href="trees.php" class="btn btn-outline-secondary ms-2">
                                <i class="bi bi-arrow-left me-1"></i> ยกเลิก
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4 mt-4 mt-lg-0">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h6 class="mb-0"><i class="bi bi-lightbulb me-2 text-warning"></i>คำแนะนำ</h6>
                </div>
                <div class="card-body">
                    <ul class="mb-0 ps-3">
                        <li class="mb-2">ควรอัปโหลดรูปภาพมากกว่า 1 ภาพเพื่อแสดงรายละเอียดต้นไม้ได้ชัดเจน</li>
                        <li class="mb-2">ระบุขนาดต้นไม้ให้ชัดเจน เช่น ความสูง เส้นผ่าศูนย์กลางกระถาง</li>
                        <li class="mb-2">การตั้งราคาควรคำนึงถึงต้นทุนและราคาตลาดปัจจุบัน</li>
                        <li>เลือกหมวดหมู่ให้ถูกต้องเพื่อความสะดวกในการค้นหา</li>
                    </ul>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h6 class="mb-0"><i class="bi bi-link-45deg me-2 text-primary"></i>ลิงก์ที่เกี่ยวข้อง</h6>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <a href="categories.php"
                            class="list-group-item list-group-item-action d-flex align-items-center">
                            <i class="bi bi-folder2 me-2 text-primary"></i> จัดการหมวดหมู่
                        </a>
                        <a href="reports.php" class="list-group-item list-group-item-action d-flex align-items-center">
                            <i class="bi bi-file-earmark-text me-2 text-primary"></i> รายงานสินค้าคงเหลือ
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.querySelector('.needs-validation');
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        });

        const fileInput = document.getElementById('images');
        const previewContainer = document.getElementById('preview-container');
        const dropArea = document.getElementById('drop-area');

        fileInput.addEventListener('change', handleFileSelect);

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            dropArea.addEventListener(eventName, highlight, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, unhighlight, false);
        });

        function highlight() {
            dropArea.classList.add('bg-light');
        }

        function unhighlight() {
            dropArea.classList.remove('bg-light');
        }

        dropArea.addEventListener('drop', handleDrop, false);

        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            fileInput.files = files;
            handleFileSelect();
        }

        function handleFileSelect() {
            previewContainer.innerHTML = '';

            if (!fileInput.files || fileInput.files.length === 0) {
                return;
            }

            Array.from(fileInput.files).forEach(file => {

                if (!file.type.match('image.*')) {
                    return;
                }

                const col = document.createElement('div');
                col.className = 'col-4 col-md-3';

                const card = document.createElement('div');
                card.className = 'card h-100';

                const img = document.createElement('img');
                img.className = 'card-img-top';
                img.style.height = '80px';
                img.style.objectFit = 'cover';

                const cardBody = document.createElement('div');
                cardBody.className = 'card-body p-2 text-center';
                cardBody.innerHTML = `<small class="text-truncate d-block">${file.name}</small>`;

                const reader = new FileReader();
                reader.onload = (e) => {
                    img.src = e.target.result;
                };
                reader.readAsDataURL(file);
                card.appendChild(img);
                card.appendChild(cardBody);
                col.appendChild(card);
                previewContainer.appendChild(col);
            });
        }

        dropArea.addEventListener('click', () => {
            fileInput.click();
        });
    });
</script>

<style>
    .border-dashed {
        border-style: dashed !important;
        border-color: #dee2e6;
    }

    .upload-area:hover {
        background-color: #f8f9fa;
        border-color: #2e7d32 !important;
    }

    .form-control:focus,
    .form-select:focus {
        border-color: #81c784;
        box-shadow: 0 0 0 0.25rem rgba(46, 125, 50, 0.25);
    }

    .btn-success {
        background-color: #2e7d32;
        border-color: #2e7d32;
    }

    .btn-success:hover {
        background-color: #1b5e20;
        border-color: #1b5e20;
    }
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>