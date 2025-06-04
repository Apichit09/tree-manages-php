<?php
// pages/trees.php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

// ถ้ายังไม่ล็อกอิน → เด้งไปหน้าล็อกอิน
if (!isLoggedIn()) {
    redirect('login.php');
}

// 1) ดึงข้อมูลต้นไม้ทั้งหมด (พร้อมหมวดหมู่, images)
$sql = "
    SELECT 
        t.id,
        t.name,
        t.size,
        t.price,
        t.quantity,
        t.sold,
        t.died,
        DATE_FORMAT(t.added_at, '%d/%m/%Y') AS added_date,
        c.name AS category_name,
        (SELECT image_url FROM tree_images WHERE tree_id = t.id LIMIT 1) AS thumb
    FROM trees t
    LEFT JOIN categories c ON t.category_id = c.id
    ORDER BY t.added_at DESC
";
$stmt = $pdo->query($sql);
$trees = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="container">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <h2 class="mb-3 mb-md-0">
                    <i class="bi bi-tree-fill text-success me-2"></i> จัดการต้นไม้ทั้งหมด
                </h2>
                <div class="d-flex gap-2">
                    <a href="tree_add.php" class="btn btn-success">
                        <i class="bi bi-plus-circle me-1"></i> เพิ่มต้นไม้ใหม่
                    </a>
                    <button class="btn btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#filterOptions">
                        <i class="bi bi-funnel me-1"></i> กรองข้อมูล
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter options (collapsible) -->
    <div class="collapse mb-4" id="filterOptions">
        <div class="card card-body border-0 shadow-sm">
            <div class="row g-3">
                <div class="col-md-4">
                    <label for="categoryFilter" class="form-label">หมวดหมู่</label>
                    <select id="categoryFilter" class="form-select">
                        <option value="">ทั้งหมด</option>
                        <!-- จะเพิ่มตัวเลือกด้วย JavaScript -->
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="searchInput" class="form-label">ค้นหาจากชื่อ</label>
                    <input type="text" id="searchInput" class="form-control" placeholder="พิมพ์ชื่อต้นไม้...">
                </div>
                <div class="col-md-4">
                    <label for="sortOption" class="form-label">เรียงตาม</label>
                    <select id="sortOption" class="form-select">
                        <option value="latest">วันที่เพิ่ม (ล่าสุด)</option>
                        <option value="name_asc">ชื่อ (ก-ฮ)</option>
                        <option value="price_low">ราคา (ต่ำ-สูง)</option>
                        <option value="price_high">ราคา (สูง-ต่ำ)</option>
                        <option value="quantity">จำนวนคงเหลือ (มาก-น้อย)</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Table with responsive scrolling on mobile -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="treesTable" class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="py-3" style="width:5%;">#</th>
                            <th class="py-3" style="width:10%;">รูป</th>
                            <th class="py-3" style="width:25%;">ชื่อ</th>
                            <th class="py-3" style="width:10%;">หมวดหมู่</th>
                            <th class="py-3" style="width:10%;">ขนาด</th>
                            <th class="py-3 text-end" style="width:10%;">ราคา (฿)</th>
                            <th class="py-3 text-center" style="width:8%;">คงเหลือ</th>
                            <th class="py-3 text-center" style="width:7%;">ขาย</th>
                            <th class="py-3 text-center" style="width:7%;">ตาย</th>
                            <th class="py-3 text-center" style="width:8%;">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($trees)): ?>
                            <tr>
                                <td colspan="10" class="text-center py-4">
                                    <div class="py-5">
                                        <i class="bi bi-tree text-muted fs-1"></i>
                                        <p class="mt-3 text-muted">ยังไม่มีต้นไม้ในระบบ</p>
                                        <a href="tree_add.php" class="btn btn-sm btn-success mt-2">
                                            <i class="bi bi-plus-circle me-1"></i> เพิ่มต้นไม้ใหม่
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($trees as $idx => $t): ?>
                                <tr class="tree-row">
                                    <td><?= $idx + 1 ?></td>
                                    <td class="text-center">
                                        <?php if ($t['thumb']): ?>
                                            <img src="<?= e($t['thumb']) ?>" 
                                                alt="<?= e($t['name']) ?>" 
                                                class="rounded tree-thumb"
                                                style="width:60px; height:60px; object-fit:cover; cursor:pointer;"
                                                data-bs-toggle="modal"
                                                data-bs-target="#imagesModal"
                                                data-treeid="<?= $t['id'] ?>"
                                                data-treename="<?= e($t['name']) ?>">
                                        <?php else: ?>
                                            <div class="bg-light rounded d-flex align-items-center justify-content-center" 
                                                 style="width:60px; height:60px;">
                                                <i class="bi bi-image text-secondary"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="fw-bold"><?= e($t['name']) ?></div>
                                        <div class="small text-muted">เพิ่มเมื่อ: <?= e($t['added_date']) ?></div>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border">
                                            <?= e($t['category_name'] ?? 'ไม่มีหมวดหมู่') ?>
                                        </span>
                                    </td>
                                    <td><?= e($t['size']) ?></td>
                                    <td class="text-end fw-bold"><?= number_format($t['price'], 2) ?></td>
                                    <td class="text-center">
                                        <?php if ((int)$t['quantity'] > 10): ?>
                                            <span class="badge bg-success"><?= (int)$t['quantity'] ?></span>
                                        <?php elseif ((int)$t['quantity'] > 0): ?>
                                            <span class="badge bg-warning text-dark"><?= (int)$t['quantity'] ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center"><?= (int)$t['sold'] ?></td>
                                    <td class="text-center"><?= (int)$t['died'] ?></td>
                                    <td>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" 
                                                    data-bs-toggle="dropdown" aria-expanded="false">
                                                จัดการ
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li>
                                                    <a class="dropdown-item" href="edit_tree.php?id=<?= $t['id'] ?>">
                                                        <i class="bi bi-pencil-square text-warning me-2"></i>แก้ไข
                                                    </a>
                                                </li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <a class="dropdown-item text-danger" href="delete_tree.php?id=<?= $t['id'] ?>"
                                                    onclick="return confirm('ต้องการลบต้นไม้ ID <?= $t['id'] ?> จริงหรือไม่?');">
                                                        <i class="bi bi-trash text-danger me-2"></i>ลบ
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <?php if (!empty($trees)): ?>
    <!-- Pagination -->
    <div class="d-flex justify-content-between align-items-center mt-4">
        <div class="text-muted small">
            แสดง <?= count($trees) ?> รายการ
        </div>
        <nav aria-label="Page navigation">
            <ul class="pagination pagination-sm">
                <li class="page-item disabled"><a class="page-link" href="#">ก่อนหน้า</a></li>
                <li class="page-item active"><a class="page-link" href="#">1</a></li>
                <li class="page-item"><a class="page-link" href="#">2</a></li>
                <li class="page-item"><a class="page-link" href="#">3</a></li>
                <li class="page-item"><a class="page-link" href="#">ถัดไป</a></li>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<!-- Modal แสดงรูปทั้งหมดของต้นไม้ -->
<div class="modal fade" id="imagesModal" tabindex="-1" aria-labelledby="imagesModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header">
        <h5 class="modal-title" id="imagesModalLabel">
            <i class="bi bi-images me-2"></i> รูปต้นไม้
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
      </div>
      <div class="modal-body">
        <div id="modalTreeName" class="mb-3 fw-bold"></div>
        <div id="imageGallery" class="row g-3">
          <!-- รูปทั้งหมดจะถูก load เข้ามาที่นี่ด้วย JavaScript -->
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script>
// ฟังก์ชั่นสำหรับกรองข้อมูลในตาราง
function filterTable() {
    const searchValue = document.getElementById('searchInput').value.toLowerCase();
    const categoryValue = document.getElementById('categoryFilter').value.toLowerCase();
    const rows = document.querySelectorAll('.tree-row');
    
    rows.forEach(row => {
        const name = row.children[2].textContent.toLowerCase();
        const category = row.children[3].textContent.toLowerCase();
        
        const matchesSearch = name.includes(searchValue);
        const matchesCategory = categoryValue === '' || category.includes(categoryValue);
        
        if (matchesSearch && matchesCategory) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

// เมื่อเพจโหลดเสร็จ
document.addEventListener('DOMContentLoaded', function() {
    // ดึงข้อมูลหมวดหมู่เข้า dropdown
    const categories = new Set();
    document.querySelectorAll('.tree-row').forEach(row => {
        const category = row.children[3].textContent.trim();
        if (category) categories.add(category);
    });
    
    const categoryFilter = document.getElementById('categoryFilter');
    categories.forEach(category => {
        const option = document.createElement('option');
        option.value = category;
        option.textContent = category;
        categoryFilter.appendChild(option);
    });
    
    // เพิ่ม event listeners สำหรับฟิลเตอร์
    document.getElementById('searchInput').addEventListener('input', filterTable);
    document.getElementById('categoryFilter').addEventListener('change', filterTable);
    
    // Event listener สำหรับการเรียงลำดับข้อมูล
    document.getElementById('sortOption').addEventListener('change', function() {
        const value = this.value;
        const tbody = document.querySelector('#treesTable tbody');
        const rows = Array.from(document.querySelectorAll('.tree-row'));
        
        rows.sort((a, b) => {
            switch(value) {
                case 'name_asc':
                    return a.children[2].textContent.localeCompare(b.children[2].textContent);
                case 'price_low':
                    return parseFloat(a.children[5].textContent.replace(/,/g, '')) - 
                           parseFloat(b.children[5].textContent.replace(/,/g, ''));
                case 'price_high':
                    return parseFloat(b.children[5].textContent.replace(/,/g, '')) - 
                           parseFloat(a.children[5].textContent.replace(/,/g, ''));
                case 'quantity':
                    return parseInt(b.children[6].textContent) - parseInt(a.children[6].textContent);
                default: // latest
                    return 0; // ไม่เปลี่ยนแปลงลำดับ (คงค่าเดิม)
            }
        });
        
        // เอาแถวออกจาก tbody เพื่อจะเรียงใหม่
        rows.forEach(row => tbody.removeChild(row));
        
        // ใส่แถวกลับเข้าไปใหม่ตามลำดับที่เรียงแล้ว
        rows.forEach(row => tbody.appendChild(row));
        
        // อัปเดตลำดับเลขที่แถว
        rows.forEach((row, idx) => {
            row.children[0].textContent = idx + 1;
        });
    });
});

// เมื่อ modal เปิด ให้ดึงรูปทั้งหมดผ่าน AJAX แล้วแสดง
document.querySelector('#imagesModal').addEventListener('shown.bs.modal', function (event) {
    const button = event.relatedTarget;
    const treeId = button.dataset.treeid;
    const treeName = button.dataset.treename;
    document.querySelector('#modalTreeName').textContent = 'ต้นไม้: ' + treeName;

    // ล้าง gallery เดิม
    document.querySelector('#imageGallery').innerHTML = `
        <div class="col-12 text-center py-5">
            <div class="spinner-border text-success" role="status">
                <span class="visually-hidden">กำลังโหลด...</span>
            </div>
            <p class="mt-2">กำลังโหลดรูปภาพ...</p>
        </div>
    `;

    // AJAX เรียกไปยังไฟล์ get_tree_images.php เพื่อดึงภาพทั้งหมด
    fetch('get_tree_images.php?tree_id=' + treeId)
        .then(response => response.json())
        .then(data => {
            const gallery = document.querySelector('#imageGallery');
            gallery.innerHTML = '';
            
            if (data.success && data.images.length > 0) {
                data.images.forEach(imgUrl => {
                    gallery.innerHTML += `
                        <div class="col-md-4">
                            <div class="card h-100 border-0 shadow-sm">
                                <img src="${imgUrl}" class="card-img-top" 
                                     style="height:200px; object-fit:cover;" 
                                     alt="รูปต้นไม้">
                                <div class="card-footer bg-white border-0 d-flex justify-content-center p-2">
                                    <a href="${imgUrl}" class="btn btn-sm btn-outline-primary" target="_blank">
                                        <i class="bi bi-eye me-1"></i> ดูเต็ม
                                    </a>
                                </div>
                            </div>
                        </div>
                    `;
                });
            } else {
                gallery.innerHTML = `
                    <div class="col-12 text-center py-5">
                        <i class="bi bi-image text-muted fs-1"></i>
                        <p class="mt-3 text-muted">ไม่พบรูปภาพสำหรับต้นไม้นี้</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            document.querySelector('#imageGallery').innerHTML = `
                <div class="col-12 text-center py-5">
                    <i class="bi bi-exclamation-triangle text-warning fs-1"></i>
                    <p class="mt-3 text-muted">เกิดข้อผิดพลาดในการโหลดรูปภาพ</p>
                </div>
            `;
            console.error('Error loading images:', error);
        });
});
</script>

<style>
.tree-thumb {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.tree-thumb:hover {
    transform: scale(1.05);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}
.table th {
    font-weight: 600;
    color: #495057;
}
.table td {
    font-size: 0.9375rem;
}
</style>
