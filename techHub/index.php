<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TechHub - Your Ultimate Tech Destination</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            background: linear-gradient(135deg, #0a0e1a 0%, #1a2332 100%);
            color: #ccd6f6;
            line-height: 1.6;
            overflow-x: hidden;
        }

        /* Header */
        header {
            position: fixed;
            top: 0;
            width: 100%;
            background: rgba(10, 14, 26, 0.95);
            backdrop-filter: blur(10px);
            z-index: 1000;
            padding: 1rem 0;
            border-bottom: 1px solid rgba(0, 212, 255, 0.2);
        }

        nav {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 2rem;
        }

        .logo {
            font-size: 1.8rem;
            font-weight: bold;
            color: #00d4ff;
            text-shadow: 0 0 20px rgba(0, 212, 255, 0.5);
        }

        .nav-links {
            display: flex;
            list-style: none;
            gap: 2rem;
        }

        .auth-buttons {
            display: flex;
            gap: 1rem;
        }

        .sign-in-btn, .sign-up-btn {
            padding: 0.7rem 1.5rem;
            border: none;
            border-radius: 25px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9rem;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .sign-in-btn {
            background: transparent;
            color: #ccd6f6;
            border: 2px solid #00d4ff;
        }

        .sign-in-btn:hover {
            background: #00d4ff;
            color: #0a0e1a;
            box-shadow: 0 5px 15px rgba(0, 212, 255, 0.3);
        }

        .sign-up-btn {
            background: transparent;
            color: #ccd6f6;
            border: 2px solid #00d4ff;
        }

        .sign-up-btn:hover {
            background: #00d4ff;
            color: #0a0e1a;
            box-shadow: 0 5px 15px rgba(0, 212, 255, 0.3);
        }

        .nav-links a {
            color: #ccd6f6;
            text-decoration: none;
            transition: all 0.3s ease;
            position: relative;
        }

        .nav-links a:hover {
            color: #00d4ff;
            text-shadow: 0 0 10px rgba(0, 212, 255, 0.5);
        }

        .nav-links a::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: -5px;
            left: 0;
            background: linear-gradient(90deg, #00d4ff, #0099cc);
            transition: width 0.3s ease;
        }

        .nav-links a:hover::after {
            width: 100%;
        }

        /* Hero Slideshow Section */
        .hero {
            height: 100vh;
            position: relative;
            overflow: hidden;
        }

        .slideshow-container {
            position: relative;
            width: 100%;
            height: 100%;
        }

        .slide {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            transition: opacity 1s ease-in-out;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
        }

        .slide.active {
            opacity: 1;
        }

        .slide1 {
            background: radial-gradient(circle at 30% 40%, rgba(0, 212, 255, 0.15) 0%, transparent 50%),
                        linear-gradient(135deg, #0a0e1a 0%, #1a2332 100%);
        }

        .slide2 {
            background: radial-gradient(circle at 70% 60%, rgba(255, 0, 150, 0.15) 0%, transparent 50%),
                        linear-gradient(135deg, #0a0e1a 0%, #2a1832 100%);
        }

        .slide3 {
            background: radial-gradient(circle at 50% 30%, rgba(0, 255, 100, 0.15) 0%, transparent 50%),
                        linear-gradient(135deg, #0a0e1a 0%, #1a3228 100%);
        }

        .slide-content {
            max-width: 800px;
            padding: 0 2rem;
            animation: slideInUp 1s ease;
        }

        .slide h1 {
            font-size: 3.5rem;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, #ccd6f6, #00d4ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .slide p {
            font-size: 1.3rem;
            margin-bottom: 2rem;
            opacity: 0.9;
        }

        .cta-button {
            display: inline-block;
            padding: 1rem 2rem;
            background: linear-gradient(135deg, #00d4ff, #0099cc);
            color: #0a0e1a;
            text-decoration: none;
            border-radius: 50px;
            font-weight: bold;
            transition: all 0.3s ease;
            box-shadow: 0 10px 30px rgba(0, 212, 255, 0.3);
            position: relative;
            overflow: hidden;
        }

        .cta-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(0, 212, 255, 0.5);
        }

        .cta-button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .cta-button:hover::before {
            left: 100%;
        }

        /* Slideshow Navigation */
        .slide-nav {
            position: absolute;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 15px;
            z-index: 10;
        }

        .slide-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .slide-dot.active {
            background: #00d4ff;
            box-shadow: 0 0 10px rgba(0, 212, 255, 0.5);
        }

        .slide-arrows {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            font-size: 2rem;
            color: rgba(255, 255, 255, 0.7);
            cursor: pointer;
            transition: all 0.3s ease;
            user-select: none;
            z-index: 10;
        }

        .slide-arrows:hover {
            color: #00d4ff;
            text-shadow: 0 0 10px rgba(0, 212, 255, 0.5);
        }

        .prev-arrow {
            left: 30px;
        }

        .next-arrow {
            right: 30px;
        }

        /* Features Section */
        .features {
            padding: 5rem 0;
            max-width: 1200px;
            margin: 0 auto;
        }

        .section-title {
            text-align: center;
            font-size: 2.5rem;
            margin-bottom: 3rem;
            color: #00d4ff;
            text-shadow: 0 0 20px rgba(0, 212, 255, 0.3);
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            padding: 0 2rem;
        }

        .feature-card {
            background: rgba(26, 35, 50, 0.5);
            padding: 2rem;
            border-radius: 15px;
            border: 1px solid rgba(0, 212, 255, 0.2);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .feature-card:hover {
            transform: translateY(-10px);
            border-color: #00d4ff;
            box-shadow: 0 20px 40px rgba(0, 212, 255, 0.2);
        }

        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #00d4ff, #0099cc);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .feature-card:hover::before {
            transform: scaleX(1);
        }

        .feature-icon {
            font-size: 3rem;
            color: #00d4ff;
            margin-bottom: 1rem;
            text-shadow: 0 0 20px rgba(0, 212, 255, 0.5);
        }

        .feature-card h3 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: #ccd6f6;
        }

        /* About Us Section */
        .about {
            padding: 5rem 0;
            background: rgba(26, 35, 50, 0.3);
        }

        .about-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            align-items: center;
        }

        .about-text h2 {
            font-size: 2.5rem;
            margin-bottom: 2rem;
            color: #00d4ff;
            text-shadow: 0 0 20px rgba(0, 212, 255, 0.3);
        }

        .about-text p {
            margin-bottom: 1.5rem;
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .about-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }

        .stat-card {
            text-align: center;
            padding: 2rem;
            background: rgba(10, 14, 26, 0.7);
            border-radius: 15px;
            border: 1px solid rgba(0, 212, 255, 0.2);
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            border-color: #00d4ff;
            box-shadow: 0 10px 30px rgba(0, 212, 255, 0.2);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #00d4ff;
            margin-bottom: 0.5rem;
            text-shadow: 0 0 20px rgba(0, 212, 255, 0.5);
        }

        .stat-label {
            color: #ccd6f6;
            font-size: 1.1rem;
        }

        /* Contact Us Section */
        .contact {
            padding: 5rem 0;
            max-width: 1200px;
            margin: 0 auto;
        }

        .contact-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            padding: 0 2rem;
        }

        .contact-info h2 {
            font-size: 2.5rem;
            margin-bottom: 2rem;
            color: #00d4ff;
            text-shadow: 0 0 20px rgba(0, 212, 255, 0.3);
        }

        .contact-item {
            display: flex;
            align-items: center;
            margin-bottom: 2rem;
            padding: 1.5rem;
            background: rgba(26, 35, 50, 0.5);
            border-radius: 10px;
            border: 1px solid rgba(0, 212, 255, 0.2);
            transition: all 0.3s ease;
        }

        .contact-item:hover {
            border-color: #00d4ff;
            box-shadow: 0 10px 20px rgba(0, 212, 255, 0.2);
        }

        .contact-icon {
            font-size: 2rem;
            color: #00d4ff;
            margin-right: 1rem;
            text-shadow: 0 0 10px rgba(0, 212, 255, 0.5);
        }

        .contact-form {
            background: rgba(26, 35, 50, 0.5);
            padding: 2rem;
            border-radius: 15px;
            border: 1px solid rgba(0, 212, 255, 0.2);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #ccd6f6;
            font-weight: bold;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 1rem;
            background: rgba(10, 14, 26, 0.7);
            border: 1px solid rgba(0, 212, 255, 0.3);
            border-radius: 8px;
            color: #ccd6f6;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #00d4ff;
            box-shadow: 0 0 10px rgba(0, 212, 255, 0.3);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }

        .submit-btn {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, #00d4ff, #0099cc);
            color: #0a0e1a;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 212, 255, 0.4);
        }

        /* Footer */
        footer {
            background: #0a0e1a;
            padding: 3rem 0 1rem;
            border-top: 1px solid rgba(0, 212, 255, 0.2);
        }

        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
        }

        .footer-section h3 {
            color: #00d4ff;
            margin-bottom: 1rem;
            text-shadow: 0 0 10px rgba(0, 212, 255, 0.3);
        }

        .footer-section ul {
            list-style: none;
        }

        .footer-section ul li {
            margin-bottom: 0.5rem;
        }

        .footer-section ul li a {
            color: #ccd6f6;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .footer-section ul li a:hover {
            color: #00d4ff;
        }

        .footer-bottom {
            text-align: center;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid rgba(0, 212, 255, 0.1);
            color: #ccd6f6;
            opacity: 0.7;
        }

        /* Animations */
        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in {
            opacity: 0;
            transform: translateY(30px);
            transition: all 0.8s ease;
        }

        .fade-in.visible {
            opacity: 1;
            transform: translateY(0);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .slide h1 {
                font-size: 2.5rem;
            }

            .slide p {
                font-size: 1.1rem;
            }

            .about-content {
                grid-template-columns: 1fr;
                gap: 2rem;
            }

            .contact-content {
                grid-template-columns: 1fr;
                gap: 2rem;
            }

            .nav-links {
                display: none;
            }

            .features-grid {
                grid-template-columns: 1fr;
            }

            .slide-arrows {
                font-size: 1.5rem;
            }

            .prev-arrow {
                left: 15px;
            }

            .next-arrow {
                right: 15px;
            }
        }

        /* Glowing effects */
        .glow {
            animation: glow 2s ease-in-out infinite alternate;
        }

        @keyframes glow {
            from {
                text-shadow: 0 0 20px rgba(0, 212, 255, 0.5);
            }
            to {
                text-shadow: 0 0 30px rgba(0, 212, 255, 0.8);
            }
        }
    </style>
</head>
<body>
    <header>
        <nav>
            <div class="logo glow">TechHub</div>
            <ul class="nav-links">
                <li><a href="#home">Home</a></li>
                <!-- <li><a href="#products">Products</a></li> -->
                <li><a href="#about">About</a></li>
                <li><a href="#contact">Contact</a></li>
            </ul>
            <div class="auth-buttons">
                <a href="sign-in.php" class="sign-in-btn">Sign In</a>
                <a href="sign-up.php" class="sign-up-btn">Sign Up</a>
            </div>
        </nav>
    </header>

    

    <section class="hero" id="home">
        <div class="slideshow-container">
            <!-- Slide 1 -->
            <div class="slide slide1 active">
                <div class="slide-content">
                    <h1>Welcome to TechHub</h1>
                    <p>Your ultimate destination for cutting-edge technology. Discover the latest gadgets, innovative solutions, and tech accessories that power your digital lifestyle.</p>
                    <a href="sign-in.php" class="cta-button">Explore Products</a>
                </div>
            </div>

            <!-- Slide 2 -->
            <div class="slide slide2">
                <div class="slide-content">
                    <h1>Latest Gaming Gear</h1>
                    <p>Level up your gaming experience with our premium collection of gaming laptops, mechanical keyboards, and high-performance accessories.</p>
                    <a href="sign-in.php" class="cta-button">Shop Gaming</a>
                </div>
            </div>

            <!-- Slide 3 -->
            <div class="slide slide3">
                <div class="slide-content">
                    <h1>Smart Home Solutions</h1>
                    <p>Transform your home into a smart haven with our IoT devices, smart speakers, and automated systems for the modern lifestyle.</p>
                    <a href="sign-in.php" class="cta-button">Go Smart</a>
                </div>
            </div>

            <!-- Navigation arrows -->
            <div class="slide-arrows prev-arrow" onclick="changeSlide(-1)">‹</div>
            <div class="slide-arrows next-arrow" onclick="changeSlide(1)">›</div>

            <!-- Navigation dots -->
            <div class="slide-nav">
                <div class="slide-dot active" onclick="currentSlide(1)"></div>
                <div class="slide-dot" onclick="currentSlide(2)"></div>
                <div class="slide-dot" onclick="currentSlide(3)"></div>
            </div>
        </div>
    </section>

    <section class="features">
        <h2 class="section-title fade-in">Why Choose TechHub?</h2>
        <div class="features-grid">
            <div class="feature-card fade-in">
                <div class="feature-icon">⚡</div>
                <h3>Lightning Fast Delivery</h3>
                <p>Get your tech gear delivered in record time with our express shipping options and nationwide logistics network.</p>
            </div>
            <div class="feature-card fade-in">
                <div class="feature-icon">🛡️</div>
                <h3>Warranty Protection</h3>
                <p>All products come with comprehensive warranty coverage and 24/7 technical support for peace of mind.</p>
            </div>
            <div class="feature-card fade-in">
                <div class="feature-icon">🔥</div>
                <h3>Latest Technology</h3>
                <p>Stay ahead with the newest releases from top brands. We stock the latest innovations as soon as they launch.</p>
            </div>
        </div>
    </section>

    <section class="about" id="about">
        <div class="about-content">
            <div class="about-text fade-in">
                <h2>About TechHub</h2>
                <p>Founded in 2018, TechHub has revolutionized the way people shop for technology. We're more than just an e-commerce platform – we're your trusted partner in the digital age.</p>
                <p>Our mission is to make cutting-edge technology accessible to everyone. From smartphones and laptops to smart home devices and gaming gear, we curate the best products from leading brands worldwide.</p>
                <p>With over 50,000 satisfied customers and growing, TechHub continues to set new standards in tech retail through innovation, quality, and exceptional customer service.</p>
            </div>
            <div class="about-stats fade-in">
                <div class="stat-card">
                    <div class="stat-number">50K+</div>
                    <div class="stat-label">Happy Customers</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">10K+</div>
                    <div class="stat-label">Products</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">99.8%</div>
                    <div class="stat-label">Satisfaction Rate</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">24/7</div>
                    <div class="stat-label">Support</div>
                </div>
            </div>
        </div>
    </section>

    <section class="contact" id="contact">
        <h2 class="section-title fade-in">Contact Us</h2>
        <div class="contact-content">
            <div class="contact-info fade-in">
                <h2>Get In Touch</h2>
                <div class="contact-item">
                    <div class="contact-icon">📍</div>
                    <div>
                        <h4>Address</h4>
                        <p>123 Tech Street, Digital City<br>Metro Manila, Philippines 1630</p>
                    </div>
                </div>
                <div class="contact-item">
                    <div class="contact-icon">📞</div>
                    <div>
                        <h4>Phone</h4>
                        <p>+63 2 8123 4567<br>+63 917 123 4567</p>
                    </div>
                </div>
                <div class="contact-item">
                    <div class="contact-icon">✉️</div>
                    <div>
                        <h4>Email</h4>
                        <p>info@techhub.com<br>support@techhub.com</p>
                    </div>
                </div>
                <div class="contact-item">
                    <div class="contact-icon">🕒</div>
                    <div>
                        <h4>Business Hours</h4>
                        <p>Mon - Fri: 9:00 AM - 6:00 PM<br>Sat - Sun: 10:00 AM - 4:00 PM</p>
                    </div>
                </div>
            </div>
            <div class="contact-form fade-in">
                <form>
                    <div class="form-group">
                        <label for="name">Full Name</label>
                        <input type="text" id="name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="subject">Subject</label>
                        <input type="text" id="subject" name="subject" required>
                    </div>
                    <div class="form-group">
                        <label for="message">Message</label>
                        <textarea id="message" name="message" required></textarea>
                    </div>
                    <button type="submit" class="submit-btn">Send Message</button>
                </form>
            </div>
        </div>
    </section>

    <footer>
        <div class="footer-content">
            <div class="footer-section">
                <h3>Products</h3>
                <ul>
                    <li><a href="#">Smartphones</a></li>
                    <li><a href="#">Laptops</a></li>
                    <li><a href="#">Gaming</a></li>
                    <li><a href="#">Smart Home</a></li>
                    <li><a href="#">Accessories</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h3>Support</h3>
                <ul>
                    <li><a href="#">Help Center</a></li>
                    <li><a href="#">Track Order</a></li>
                    <li><a href="#">Returns</a></li>
                    <li><a href="#">Warranty</a></li>
                    <li><a href="#contact">Contact Us</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h3>Company</h3>
                <ul>
                    <li><a href="#about">About Us</a></li>
                    <li><a href="#">Careers</a></li>
                    <li><a href="#">Press</a></li>
                    <li><a href="#">Blog</a></li>
                    <li><a href="#">Partners</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h3>Connect</h3>
                <ul>
                    <li><a href="#">Newsletter</a></li>
                    <li><a href="#">Social Media</a></li>
                    <li><a href="#">Community</a></li>
                    <li><a href="#">Events</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2025 TechHub. All rights reserved. | Privacy Policy | Terms of Service</p>
        </div>
    </footer>

    <script>
        // Slideshow functionality
        let currentSlideIndex = 0;
        const slides = document.querySelectorAll('.slide');
        const dots = document.querySelectorAll('.slide-dot');
        const totalSlides = slides.length;

        function showSlide(index) {
            // Remove active class from all slides and dots
            slides.forEach(slide => slide.classList.remove('active'));
            dots.forEach(dot => dot.classList.remove('active'));
            
            // Add active class to current slide and dot
            slides[index].classList.add('active');
            dots[index].classList.add('active');
        }

        function changeSlide(direction) {
            currentSlideIndex += direction;
            
            if (currentSlideIndex >= totalSlides) {
                currentSlideIndex = 0;
            } else if (currentSlideIndex < 0) {
                currentSlideIndex = totalSlides - 1;
            }
            
            showSlide(currentSlideIndex);
        }

        function currentSlide(index) {
            currentSlideIndex = index - 1;
            showSlide(currentSlideIndex);
        }

        // Auto-advance slideshow
        setInterval(() => {
            changeSlide(1);
        }, 5000); // Change slide every 5 seconds

        // Smooth scrolling for navigation links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Fade in animation on scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                }
            });
        }, observerOptions);

        document.querySelectorAll('.fade-in').forEach(el => {
            observer.observe(el);
        });

        // Add some interactive effects
        document.querySelectorAll('.feature-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-10px) scale(1.02)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
        });

        // Header background on scroll
        window.addEventListener('scroll', () => {
            const header = document.querySelector('header');
            if (window.scrollY > 100) {
                header.style.background = 'rgba(10, 14, 26, 0.98)';
            } else {
                header.style.background = 'rgba(10, 14, 26, 0.95)';
            }
        });

        // Contact form submission
        document.querySelector('.contact-form form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Get form data
            const formData = new FormData(this);
            const name = formData.get('name');
            const email = formData.get('email');
            const subject = formData.get('subject');
            const message = formData.get('message');
            
            // Simple validation
            if (name && email && subject && message) {
                alert('Thank you for your message! We will get back to you soon.');
                this.reset();
            } else {
                alert('Please fill in all fields.');
            }
        });

        // Add loading animation to CTA buttons
        document.querySelectorAll('.cta-button').forEach(button => {
            button.addEventListener('click', function(e) {
                if (this.getAttribute('href').startsWith('#')) {
                    return; // Let smooth scroll handle it
                }
                
                const originalText = this.textContent;
                this.textContent = 'Loading...';
                this.style.pointerEvents = 'none';
                
                setTimeout(() => {
                    this.textContent = originalText;
                    this.style.pointerEvents = 'auto';
                }, 1000);
            });
        });

        // Keyboard navigation for slideshow
        document.addEventListener('keydown', function(e) {
            if (e.key === 'ArrowLeft') {
                changeSlide(-1);
            } else if (e.key === 'ArrowRight') {
                changeSlide(1);
            }
        });

        // Touch/swipe support for mobile slideshow
        let startX = 0;
        let endX = 0;

        document.querySelector('.slideshow-container').addEventListener('touchstart', function(e) {
            startX = e.changedTouches[0].screenX;
        });

        document.querySelector('.slideshow-container').addEventListener('touchend', function(e) {
            endX = e.changedTouches[0].screenX;
            handleSwipe();
        });

        function handleSwipe() {
            const swipeThreshold = 50;
            const diff = startX - endX;
            
            if (Math.abs(diff) > swipeThreshold) {
                if (diff > 0) {
                    changeSlide(1); // Swipe left, go to next slide
                } else {
                    changeSlide(-1); // Swipe right, go to previous slide
                }
            }
        }
        </script>

        </body>
    </html>