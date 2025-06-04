<?php

?>
</main>
<footer class="bg-light py-5 border-top">
  <div class="container">
    <div class="row g-4">
      <div class="col-md-4">
        <h5 class="mb-3 text-success"><i class="bi bi-tree-fill me-2"></i>สวนไม้</h5>
        <p class="text-muted">
          จำหน่ายต้นไม้หลากหลายชนิด ทั้งไม้ดอก ไม้ประดับ และต้นไม้ฟอกอากาศ คุณภาพดี ราคาเป็นมิตร
        </p>
      </div>
      <div class="col-md-4">
        <h5 class="mb-3">ติดต่อเรา</h5>
        <ul class="list-unstyled">
          <li class="mb-2"><i class="bi bi-geo-alt-fill me-2 text-success"></i> 123 ถ.สวนไม้ ต.ในเมือง อ.เมือง</li>
          <li class="mb-2"><i class="bi bi-telephone-fill me-2 text-success"></i> 081-234-5678</li>
          <li class="mb-2"><i class="bi bi-envelope-fill me-2 text-success"></i> contact@tree-garden.com</li>
        </ul>
      </div>
      <div class="col-md-4">
        <h5 class="mb-3">เวลาทำการ</h5>
        <ul class="list-unstyled">
          <li class="mb-2"><i class="bi bi-clock-fill me-2 text-success"></i> จันทร์ - ศุกร์: 8:00 - 17:00 น.</li>
          <li class="mb-2"><i class="bi bi-clock-fill me-2 text-success"></i> เสาร์ - อาทิตย์: 9:00 - 16:00 น.</li>
        </ul>
        <div class="mt-3">
          <a href="#" class="text-decoration-none me-3 fs-5"><i class="bi bi-facebook text-primary"></i></a>
          <a href="#" class="text-decoration-none me-3 fs-5"><i class="bi bi-line text-success"></i></a>
          <a href="#" class="text-decoration-none fs-5"><i class="bi bi-instagram text-danger"></i></a>
        </div>
      </div>
    </div>
    <hr class="my-4">
    <div class="text-center">
      <small class="text-muted">© <?= date('Y') ?> สวนไม้. สงวนลิขสิทธิ์.</small>
    </div>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const currentPath = window.location.pathname;
    const navLinks = document.querySelectorAll('.nav-link');

    navLinks.forEach(link => {
      if (link.getAttribute('href') === currentPath) {
        link.classList.add('active');
      }
    });
  });
</script>
</body>

</html>