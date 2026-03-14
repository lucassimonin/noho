document.addEventListener('DOMContentLoaded', function() {
    var offcanvas = document.getElementById('navbarNav');
    
    if (!offcanvas) return;
    
    // Block scroll when offcanvas opens
    offcanvas.addEventListener('show.bs.offcanvas', function() {
        document.body.classList.add('offcanvas-open');
    });
    
    // Restore scroll when offcanvas closes
    offcanvas.addEventListener('hidden.bs.offcanvas', function() {
        document.body.classList.remove('offcanvas-open');
    });
});
