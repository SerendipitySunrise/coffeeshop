document.addEventListener('DOMContentLoaded', () => {
    const burgerMenu = document.getElementById('burger-menu');
    const mainNav = document.getElementById('main-nav');
    const mainHeader = document.querySelector('.main-header'); 

    if (burgerMenu && mainNav && mainHeader) {
        
        const closeMenu = () => {
            mainNav.classList.remove('active');
            burgerMenu.classList.remove('active');
            mainHeader.classList.remove('menu-open'); 
            document.body.classList.remove('menu-is-open'); 
        };
        
        burgerMenu.addEventListener('click', (e) => {
            e.stopPropagation();
            mainNav.classList.toggle('active');
            burgerMenu.classList.toggle('active');
            mainHeader.classList.toggle('menu-open'); 
            
            document.body.classList.toggle('menu-is-open');
        });
        
        const navLinks = document.querySelectorAll('.nav-link');
        navLinks.forEach(link => {
            link.addEventListener('click', closeMenu);
        });
        
        document.addEventListener('click', (e) => {
            if (!mainNav.contains(e.target) && !burgerMenu.contains(e.target) && mainNav.classList.contains('active')) {
                closeMenu();
            }
        });
        
        window.addEventListener('resize', () => {
            if (window.innerWidth > 768) {
                closeMenu();
            }
        });
    }
});