<footer class="footer mt-auto py-4">
    <div class="container">
        <div class="row">
            <div class="col-lg-4 mb-4 mb-lg-0">
                <h5 class="text-white mb-4"><i class="fas fa-graduation-cap me-2"></i>UniFlow by FNBB</h5>
                <p class="text-muted">Empowering students with financial solutions for their educational journey.</p>
                <div class="social-icons mt-3">
                    <a href="#" class="text-white me-3"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" class="text-white me-3"><i class="fab fa-twitter"></i></a>
                    <a href="#" class="text-white me-3"><i class="fab fa-instagram"></i></a>
                    <a href="#" class="text-white"><i class="fab fa-linkedin-in"></i></a>
                </div>
            </div>
            
            <div class="col-lg-2 col-md-6 mb-4 mb-md-0">
                <h5 class="text-white mb-4">Quick Links</h5>
                <ul class="list-unstyled">
                    <li class="mb-2"><a href="../index.html" class="text-muted">Home</a></li>
                    <li class="mb-2"><a href="../about.html" class="text-muted">About</a></li>
                    <li class="mb-2"><a href="../services.html" class="text-muted">Services</a></li>
                    <li class="mb-2"><a href="../contact.html" class="text-muted">Contact</a></li>
                </ul>
            </div>
            
            <div class="col-lg-2 col-md-6 mb-4 mb-md-0">
                <h5 class="text-white mb-4">Student</h5>
                <ul class="list-unstyled">
                    <li class="mb-2"><a href="login.php" class="text-muted">Login</a></li>
                    <li class="mb-2"><a href="register.php" class="text-muted">Register</a></li>
                    <li class="mb-2"><a href="forgot-password.php" class="text-muted">Forgot Password</a></li>
                    <li class="mb-2"><a href="faq.php" class="text-muted">FAQ</a></li>
                </ul>
            </div>
            
            <div class="col-lg-4 col-md-12">
                <h5 class="text-white mb-4">Contact Us</h5>
                <ul class="list-unstyled text-muted">
                    <li class="mb-2"><i class="fas fa-map-marker-alt me-2"></i> 123 FNBB</li>
                    <li class="mb-2"><i class="fas fa-phone me-2"></i> (267) 456-7890</li>
                    <li class="mb-2"><i class="fas fa-envelope me-2"></i> support@uniflow.fnb.co.bw</li>
                    <li class="mb-2"><i class="fas fa-clock me-2"></i> Mon-Fri: 9:00 AM - 5:00 PM</li>
                </ul>
            </div>
        </div>
        
        <hr class="my-4 bg-light">
        
        <div class="row align-items-center">
            <div class="col-md-6 text-center text-md-start">
                <p class="text-muted mb-0">&copy; <?php echo date('Y'); ?> UniFlow. All rights reserved.</p>
            </div>
            <div class="col-md-6 text-center text-md-end">
                <a href="../privacy.html" class="text-muted me-3">Privacy Policy</a>
                <a href="../terms.html" class="text-muted">Terms of Service</a>
            </div>
        </div>
    </div>
</footer>

<style>
    .footer {
        background: rgba(10, 22, 40, 0.95);
        backdrop-filter: blur(20px);
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        color: white;
    }
    
    .footer h5 {
        font-weight: 600;
        margin-bottom: 1.5rem;
    }
    
    .footer a {
        text-decoration: none;
        transition: all 0.3s ease;
    }
    
    .footer a:hover {
        color: #4ECDC4 !important;
    }
    
    .social-icons a {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.1);
        margin-right: 10px;
        transition: all 0.3s ease;
    }
    
    .social-icons a:hover {
        background: linear-gradient(135deg, #FF6B6B, #FF8E53);
        transform: translateY(-3px);
    }
    
    .text-muted {
        color: rgba(255, 255, 255, 0.6) !important;
    }
    
    hr {
        opacity: 0.1;
    }
</style>