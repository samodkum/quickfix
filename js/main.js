/* 
    js/main.js
    This file handles small frontend Javascript interactions.
*/

// Example: Add a tiny shadow effect to the header when people scroll down the page
document.addEventListener('DOMContentLoaded', () => {
    
    const header = document.querySelector('.site-header');
    
    if(header) {
        window.addEventListener('scroll', () => {
            if (window.scrollY > 10) {
                // If scrolled down more than 10 pixels, make shadow darker
                header.style.boxShadow = '0 4px 6px -1px rgba(0, 0, 0, 0.1)';
            } else {
                // If at the top, reset to default small shadow
                header.style.boxShadow = '0 1px 2px 0 rgba(0, 0, 0, 0.05)';
            }
        });
    }
    
});
