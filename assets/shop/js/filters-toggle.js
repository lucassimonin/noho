document.addEventListener('DOMContentLoaded', function() {
    var toggle = document.getElementById('filtersToggle');
    var content = document.getElementById('filtersContent');
    
    if (!toggle || !content) return;
    
    function isMobile() {
        return window.innerWidth <= 1024;
    }
    
    function updateState() {
        if (isMobile()) {
            content.classList.add('collapsible');
            if (!content.classList.contains('open')) {
                content.style.display = 'none';
            }
        } else {
            content.classList.remove('collapsible');
            content.classList.remove('open');
            content.style.display = 'block';
        }
    }
    
    toggle.addEventListener('click', function() {
        if (!isMobile()) return;
        
        var isOpen = content.classList.contains('open');
        
        if (isOpen) {
            content.style.display = 'none';
            content.classList.remove('open');
            toggle.classList.remove('open');
        } else {
            content.style.display = 'block';
            content.classList.add('open');
            toggle.classList.add('open');
        }
    });
    
    updateState();
    window.addEventListener('resize', updateState);
});
