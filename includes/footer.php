</main>
<footer class="bg-light py-4 mt-5 border-top">
    <div class="container">
        <div class="row">
            <div class="col-md-6 text-center text-md-start">
                <p class="mb-0">
                    <i class="bi bi-tree-fill text-success me-2"></i>
                    &copy; <?= date('Y') ?> ระบบจัดการสวนไม้
                </p>
            </div>
            <div class="col-md-6 text-center text-md-end">
                <p class="mb-0 text-muted">
                    <small>v1.0.0</small>
                </p>
            </div>
        </div>
    </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="/public/js/script.js"></script>
<script>
    // Add active class to current nav item
    document.addEventListener('DOMContentLoaded', function() {
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
