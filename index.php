<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CanCare - Your Personal Health Companion</title>
    <style>
        :root {
            --primary-color: #695CFE;
            --secondary-color: #4a3db7;
            --accent-color: #d62828;
            --light-color: #f0f4f8;
            --dark-color: #003049;
            --text-color: #333;
            --white: #ffffff;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: var(--light-color);
            color: var(--text-color);
            line-height: 1.6;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        /* Header Styles */
        header {
            background-color: var(--white);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
        }
        
        nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
        }
        
        .logo {
            display: flex;
            align-items: center;
        }
        
        .logo img {
            height: 40px;
            margin-right: 10px;
        }
        
        .logo h1 {
            color: var(--primary-color);
            font-size: 24px;
        }
        
        .nav-links {
            display: flex;
            list-style: none;
        }
        
        .nav-links li {
            margin-left: 30px;
        }
        
        .nav-links a {
            text-decoration: none;
            color: var(--text-color);
            font-weight: 500;
            transition: color 0.3s;
        }
        
        .nav-links a:hover {
            color: var(--primary-color);
        }
        
        .auth-buttons .btn {
            margin-left: 15px;
            padding: 8px 20px;
            border-radius: 20px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .btn-login {
            background-color: transparent;
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
        }
        
        .btn-login:hover {
            background-color: var(--primary-color);
            color: var(--white);
        }
        
        .btn-register {
            background-color: var(--primary-color);
            border: 2px solid var(--primary-color);
            color: var(--white);
        }
        
        .btn-register:hover {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }
        
        /* Hero Section */
        .hero {
            padding: 180px 0 100px;
            background: linear-gradient(135deg, rgba(105,92,254,0.1) 0%, rgba(255,255,255,1) 100%);
            text-align: center;
        }
        
        .hero h2 {
            font-size: 48px;
            margin-bottom: 20px;
            color: var(--dark-color);
        }
        
        .hero p {
            font-size: 20px;
            max-width: 700px;
            margin: 0 auto 40px;
            color: var(--text-color);
        }
        
        .hero-image {
            max-width: 800px;
            margin: 40px auto 0;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .hero-image img {
            width: 100%;
            border-radius: 10px;
        }
        
        /* Features Section */
        .features {
            padding: 80px 0;
            background-color: var(--white);
        }
        
        .section-title {
            text-align: center;
            margin-bottom: 60px;
        }
        
        .section-title h3 {
            font-size: 36px;
            color: var(--dark-color);
            margin-bottom: 15px;
        }
        
        .section-title p {
            color: #666;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
        }
        
        .feature-card {
            background-color: var(--light-color);
            border-radius: 10px;
            padding: 30px;
            text-align: center;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .feature-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            background-color: var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .feature-icon img {
            width: 40px;
            height: 40px;
        }
        
        .feature-card h4 {
            font-size: 22px;
            margin-bottom: 15px;
            color: var(--dark-color);
        }
        
        /* CTA Section */
        .cta {
            padding: 100px 0;
            background-color: var(--primary-color);
            color: var(--white);
            text-align: center;
        }
        
        .cta h3 {
            font-size: 36px;
            margin-bottom: 20px;
        }
        
        .cta p {
            font-size: 18px;
            max-width: 600px;
            margin: 0 auto 40px;
        }
        
        .btn-cta {
            display: inline-block;
            background-color: var(--white);
            color: var(--primary-color);
            padding: 12px 30px;
            border-radius: 30px;
            font-weight: 600;
            text-decoration: none;
            font-size: 18px;
            transition: all 0.3s;
        }
        
        .btn-cta:hover {
            background-color: var(--light-color);
            transform: translateY(-3px);
        }
        
        /* Footer */
        footer {
            background-color: var(--dark-color);
            color: var(--white);
            padding: 50px 0 20px;
        }
        
        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 40px;
            margin-bottom: 40px;
        }
        
        .footer-column h4 {
            font-size: 18px;
            margin-bottom: 20px;
            position: relative;
            padding-bottom: 10px;
        }
        
        .footer-column h4::after {
            content: '';
            position: absolute;
            left: 0;
            bottom: 0;
            width: 50px;
            height: 2px;
            background-color: var(--primary-color);
        }
        
        .footer-column ul {
            list-style: none;
        }
        
        .footer-column ul li {
            margin-bottom: 10px;
        }
        
        .footer-column ul li a {
            color: #ccc;
            text-decoration: none;
            transition: color 0.3s;
        }
        
        .footer-column ul li a:hover {
            color: var(--white);
        }
        
        .copyright {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
            color: #ccc;
            font-size: 14px;
        }
        
        /* Responsive Styles */
        @media (max-width: 768px) {
            .hero h2 {
                font-size: 36px;
            }
            
            .hero p {
                font-size: 18px;
            }
            
            nav {
                flex-direction: column;
                padding: 15px 0;
            }
            
            .logo {
                margin-bottom: 15px;
            }
            
            .nav-links {
                margin-bottom: 15px;
            }
            
            .nav-links li {
                margin: 0 10px;
            }
            
            .auth-buttons {
                margin-top: 15px;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header>
        <div class="container">
            <nav>
                <div class="logo">
                    <img src="zphotos/logo.png" alt="CanCare Logo">
                    <h1>CanCare</h1>
                </div>
                <ul class="nav-links">
                    <li><a href="#features">Features</a></li>
                    <li><a href="#about">About</a></li>
                    <li><a href="#contact">Contact</a></li>
                </ul>
                <div class="auth-buttons">
                    <a href="loginPC.html" class="btn btn-login">Login</a>
                    <a href="registerPC.html" class="btn btn-register">Register</a>
                </div>
            </nav>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <h2>Your Personal Health Companion</h2>
            <p>Track your health metrics, manage appointments, and stay on top of your wellness journey with CanCare's comprehensive health management platform.</p>
            <div class="hero-buttons">
                <a href="registerPC.html" class="btn-cta">Get Started</a>
                <a href="#features" class="btn btn-login" style="margin-left: 15px;">Learn More</a>
            </div>
            <div class="hero-image">
                <img src="zphotos/app-dashboard.png" alt="CanCare Dashboard">
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features" id="features">
        <div class="container">
            <div class="section-title">
                <h3>Powerful Features</h3>
                <p>Everything you need to manage your health in one place</p>
            </div>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <img src="zphotos/icon-health.png" alt="Health Tracking">
                    </div>
                    <h4>Health Tracking</h4>
                    <p>Monitor your vital statistics, medications, and health progress over time.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <img src="zphotos/icon-appointment.png" alt="Appointment Management">
                    </div>
                    <h4>Appointment Management</h4>
                    <p>Schedule and keep track of all your medical appointments in one calendar.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <img src="zphotos/icon-nutrition.png" alt="Nutrition Tracking">
                    </div>
                    <h4>Nutrition Tracking</h4>
                    <p>Log your meals, track calories, and monitor your nutritional intake.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <img src="zphotos/icon-exercise.png" alt="Exercise Logging">
                    </div>
                    <h4>Exercise Logging</h4>
                    <p>Record your workouts and track your fitness progress over time.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <img src="zphotos/icon-medication.png" alt="Medication Reminders">
                    </div>
                    <h4>Medication Reminders</h4>
                    <p>Never miss a dose with our smart medication reminder system.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <img src="zphotos/icon-doctor.png" alt="Doctor Connect">
                    </div>
                    <h4>Doctor Connect</h4>
                    <p>Communicate with your healthcare providers directly through the platform.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta">
        <div class="container">
            <h3>Ready to take control of your health?</h3>
            <p>Join thousands of users who are already managing their health with CanCare.</p>
            <a href="registerPC.html" class="btn-cta">Create Your Free Account</a>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-column">
                    <h4>CanCare</h4>
                    <p>Your personal health companion for tracking and managing all aspects of your wellness journey.</p>
                </div>
                <div class="footer-column">
                    <h4>Quick Links</h4>
                    <ul>
                        <li><a href="#features">Features</a></li>
                        <li><a href="#about">About Us</a></li>
                        <li><a href="#contact">Contact</a></li>
                        <li><a href="CanCare/loginPC.html">Login</a></li>
                        <li><a href="CanCare/registerPC.html">Register</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h4>Support</h4>
                    <ul>
                        <li><a href="#">Help Center</a></li>
                        <li><a href="#">Privacy Policy</a></li>
                        <li><a href="#">Terms of Service</a></li>
                        <li><a href="#">FAQs</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h4>Contact Us</h4>
                    <ul>
                        <li>Email: info@CanCare.com</li>
                        <li>Phone: +1 (123) 456-7890</li>
                        <li>Address: 123 Health St, Wellness City</li>
                    </ul>
                </div>
            </div>
            <div class="copyright">
                <p>&copy; 2023 CanCare. All rights reserved.</p>
            </div>
        </div>
    </footer>
</body>
</html>