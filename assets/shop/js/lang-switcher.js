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
    
    function openMenu() {
        positionMenu();
        menu.classList.add('open');
        switcher.classList.add('open');
    }
    
    function closeMenu() {
        menu.classList.remove('open');
        switcher.classList.remove('open');
    }
    
    function toggleMenu(e) {
        e.preventDefault();
        e.stopPropagation();
        
        if (menu.classList.contains('open')) {
            closeMenu();
        } else {
            openMenu();
        }
    }
    
    btn.addEventListener('click', toggleMenu);
    
    // Close when clicking outside, but not on menu items (let them navigate)
    document.addEventListener('click', function(e) {
        // If clicking on a lang item, let it navigate
        if (e.target.closest('.noho-lang-item')) {
            return;
        }
        // If clicking outside switcher, close menu
        if (!switcher.contains(e.target)) {
            closeMenu();
        }
    });
    
    window.addEventListener('scroll', closeMenu);
    window.addEventListener('resize', closeMenu);
});
