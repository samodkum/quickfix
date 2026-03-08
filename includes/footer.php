    <!-- We close the <main> tag that was opened in header.php -->
    <!-- This ensures our page structure stays intact -->
    </main>

    <!-- The global footer section -->
    <!-- Using HTML5 <footer> semantic tag -->
    <footer class="site-footer">
        <div class="container">
            <!-- Footer Grid -->
            <div class="footer-grid">
                
                <!-- Column 1: Brand details -->
                <div class="footer-col" style="grid-column: span 2;">
                    <span class="footer-brand">QuickFix</span>
                    <p style="color: #A3A3A3; max-width: 300px; line-height: 1.6;">
                        Your trusted partner for home services. We connect you with top-rated professionals for all your home needs, delivered with standard pricing and a quality guarantee.
                    </p>
                </div>

                <!-- Column 2: Quick navigation links -->
                <div class="footer-col">
                    <h4>Company</h4>
                    <ul>
                        <li><a href="about.php">About Us</a></li>
                        <li><a href="how-it-works.php">How It Works</a></li>
                        <li><a href="faq.php">Help Center</a></li>
                    </ul>
                </div>

                <!-- Column 3: Legal links -->
                <div class="footer-col">
                    <h4>Legal</h4>
                    <ul>
                        <li><a href="terms.php">Terms & Conditions</a></li>
                        <li><a href="terms.php">Privacy Policy</a></li>
                        <li><a href="contact.php">Contact Us</a></li>
                    </ul>
                </div>

            </div>

            <!-- Bottom bar for copyright info -->
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> QuickFix Technologies. All rights reserved.</p>
                <div style="display: flex; gap: 16px; font-size: 1.2rem;">
                    <a href="#" style="color: #A3A3A3;"><i class="fa-brands fa-twitter"></i></a>
                    <a href="#" style="color: #A3A3A3;"><i class="fa-brands fa-facebook"></i></a>
                    <a href="#" style="color: #A3A3A3;"><i class="fa-brands fa-instagram"></i></a>
                    <a href="#" style="color: #A3A3A3;"><i class="fa-brands fa-linkedin"></i></a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Link to custom javascript file (We will create this later for frontend interactivity/validations) -->
    <!-- It's placed at the bottom so it loads AFTER the HTML, speeding up page load time -->
    <script src="js/main.js"></script>

</body>
</html>
