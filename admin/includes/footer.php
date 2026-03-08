        </div> <!-- Close main-content-scrollable opened in header -->
    </main>

    <script>
        // Toggle Sidebar Logic
        function toggleSidebar() {
            document.body.classList.toggle('collapsed-sidebar');
            // Save state to localStorage
            const isCollapsed = document.body.classList.contains('collapsed-sidebar');
            localStorage.setItem('sidebarState', isCollapsed ? 'collapsed' : 'expanded');
        }

        // Toggle Dark Mode Logic
        function toggleTheme() {
            const htmlAttr = document.documentElement;
            const themeIcon = document.getElementById('theme-icon');
            
            if (htmlAttr.getAttribute('data-theme') === 'dark') {
                htmlAttr.setAttribute('data-theme', 'light');
                themeIcon.classList.replace('fa-sun', 'fa-moon');
                localStorage.setItem('theme', 'light');
            } else {
                htmlAttr.setAttribute('data-theme', 'dark');
                themeIcon.classList.replace('fa-moon', 'fa-sun');
                localStorage.setItem('theme', 'dark');
            }
        }

        // Restore UI state on load
        document.addEventListener("DOMContentLoaded", () => {
            // Restore Sidebar
            if (localStorage.getItem('sidebarState') === 'collapsed') {
                document.body.classList.add('collapsed-sidebar');
            }
            
            // Restore Theme
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme === 'dark') {
                document.documentElement.setAttribute('data-theme', 'dark');
                const themeIcon = document.getElementById('theme-icon');
                if (themeIcon) themeIcon.classList.replace('fa-moon', 'fa-sun');
            }

            // Dynamic Greeting
            const hour = new Date().getHours();
            const greetingEl = document.getElementById('greeting');
            if (greetingEl) {
                if (hour < 12) greetingEl.textContent = 'Good morning, ';
                else if (hour < 18) greetingEl.textContent = 'Good afternoon, ';
                else greetingEl.textContent = 'Good evening, ';
            }
        });
    </script>
</body>
</html>
