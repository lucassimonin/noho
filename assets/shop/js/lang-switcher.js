document.addEventListener('DOMContentLoaded', function() {
    var switcher = document.getElementById('nohoLangSwitcher');
    var menu = document.getElementById('nohoLangMenu');
    var btn = document.getElementById('nohoLangBtn');
    
    if (!switcher || !menu || !btn) return;
    
    function positionMenu() {
        var rect = btn.getBoundingClientRect();
        menu.style.top = (rect.bottom + 8) + 'px';
        menu.style.right = (window.innerWidth - rect.right) + 'px';
    }
    
    function toggleMenu(e) {
        e.preventDefault();
        e.stopPropagation();
        
        if (menu.classList.contains('open')) {
            menu.classList.remove('open');
            switcher.classList.remove('open');
        } else {
            positionMenu();
            menu.classList.add('open');
            switcher.classList.add('open');
        }
    }
    
    function closeMenu() {
        menu.classList.remove('open');
        switcher.classList.remove('open');
    }
    
    btn.addEventListener('click', toggleMenu);
    
    document.addEventListener('click', function(e) {
        if (!switcher.contains(e.target) && !menu.contains(e.target)) {
            closeMenu();
        }
    });
    
    window.addEventListener('scroll', closeMenu);
    window.addEventListener('resize', closeMenu);
});
