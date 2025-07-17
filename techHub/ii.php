<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TechHub Electronics - Your Tech Destination</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #0c0c0c 0%, #1a1a2e 50%, #16213e 100%);
            color: #ffffff;
            overflow-x: hidden;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* Header */
        header {
            position: fixed;
            top: 0;
            width: 100%;
            background: rgba(12, 12, 12, 0.95);
            backdrop-filter: blur(20px);
            z-index: 1000;
            transition: all 0.3s ease;
        }

        nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
        }

        .logo {
            font-size: 1.8rem;
            font-weight: 800;
            background: linear-gradient(45deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .nav-links {
            display: flex;
            list-style: none;
            gap: 2rem;
            align-items: center;
        }

        .nav-links a {
            color: #ffffff;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            position: relative;
        }

        .nav-links a:hover {
            color: #667eea;
            transform: translateY(-2px);
        }

        .nav-links a::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 0;
            height: 2px;
            background: linear-gradient(45deg, #667eea, #764ba2);
            transition: width 0.3s ease;
        }

        .nav-links a:hover::after {
            width: 100%;
        }

        .auth-buttons {
            display: flex;
            gap: 1rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 25px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            position: relative;
            overflow: hidden;
        }

        .btn-primary {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
        }

        .btn-secondary {
            background: transparent;
            color: #667eea;
            border: 2px solid #667eea;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }

        /* Hero Section */
        .hero {
            min-height: 100vh;
            display: flex;
            align-items: center;
            position: relative;
            background: radial-gradient(circle at 50% 50%, rgba(102, 126, 234, 0.1) 0%, transparent 70%);
        }

        .hero-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            align-items: center;
        }

        .hero-text h1 {
            font-size: 3.5rem;
            font-weight: 900;
            margin-bottom: 1.5rem;
            background: linear-gradient(45deg, #ffffff, #667eea);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1.2;
        }

        .hero-text p {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            opacity: 0.9;
            line-height: 1.6;
        }

        .hero-visual {
            position: relative;
            height: 500px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .tech-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            animation: float 6s ease-in-out infinite;
        }

        .tech-card {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 15px;
            padding: 1.5rem;
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }

        .tech-card:hover {
            transform: translateY(-5px);
            background: rgba(102, 126, 234, 0.2);
        }

        /* Section Styles */
        .section {
            padding: 80px 0;
            opacity: 0;
            transform: translateY(50px);
            transition: all 0.8s ease;
        }

        .section.visible {
            opacity: 1;
            transform: translateY(0);
        }

        .section-title {
            text-align: center;
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 3rem;
            background: linear-gradient(45deg, #ffffff, #667eea);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Products Section */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-top: 3rem;
        }

        .product-card {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            padding: 2rem;
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .product-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(102, 126, 234, 0.1), transparent);
            transition: left 0.5s ease;
        }

        .product-card:hover::before {
            left: 100%;
        }

        .product-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(102, 126, 234, 0.3);
        }

        .product-card h3 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: #667eea;
        }

        .product-price {
            font-size: 1.5rem;
            font-weight: 700;
            color: #00ff88;
            margin-bottom: 1rem;
        }

        .product-rating {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .stars {
            color: #ffd700;
        }

        /* About Section */
        .about-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            align-items: center;
        }

        .about-text {
            font-size: 1.1rem;
            line-height: 1.8;
            opacity: 0.9;
        }

        .about-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 2rem;
        }

        .stat-card {
            text-align: center;
            padding: 2rem;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            backdrop-filter: blur(10px);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 900;
            color: #667eea;
            margin-bottom: 0.5rem;
        }

        /* Contact Section */
        .contact-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
        }

        .contact-form {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            padding: 2rem;
            backdrop-filter: blur(10px);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #667eea;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 1rem;
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.1);
            color: #ffffff;
            font-size: 1rem;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 20px rgba(102, 126, 234, 0.3);
        }

        .contact-info {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }

        .contact-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1.5rem;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            backdrop-filter: blur(10px);
        }

        .contact-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(45deg, #667eea, #764ba2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        /* Footer */
        footer {
            background: rgba(12, 12, 12, 0.95);
            padding: 3rem 0 1rem;
            margin-top: 5rem;
        }

        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .footer-section h3 {
            color: #667eea;
            margin-bottom: 1rem;
        }

        .footer-section a {
            color: #ffffff;
            text-decoration: none;
            display: block;
            margin-bottom: 0.5rem;
            transition: color 0.3s ease;
        }

        .footer-section a:hover {
            color: #667eea;
        }

        .footer-bottom {
            text-align: center;
            padding-top: 2rem;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
            opacity: 0.7;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(10px);
            z-index: 2000;
        }

        .modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: linear-gradient(135deg, #1a1a2e, #16213e);
            padding: 2rem;
            border-radius: 20px;
            width: 90%;
            max-width: 400px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            font-size: 1.5rem;
            cursor: pointer;
            color: #ffffff;
        }

        .close:hover {
            color: #667eea;
        }

        /* Animations */
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .nav-links {
                display: none;
            }
            
            .hero-content {
                grid-template-columns: 1fr;
                text-align: center;
            }
            
            .hero-text h1 {
                font-size: 2.5rem;
            }
            
            .about-content,
            .contact-content {
                grid-template-columns: 1fr;
            }
            
            .about-stats {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <header>
        <nav class="container">
            <div class="logo">TechHub</div>
            <ul class="nav-links">
                <li><a href="#home">Home</a></li>
                <li><a href="#products">Products</a></li>
                <li><a href="#about">About</a></li>
                <li><a href="#contact">Contact</a></li>
            </ul>
            <div class="auth-buttons">
                <button class="btn btn-secondary" onclick="openModal('signin')">Sign In</button>
                <button class="btn btn-primary" onclick="openModal('signup')">Sign Up</button>
            </div>
        </nav>
    </header>

    <main>
        <section id="home" class="hero">
            <div class="container">
                <div class="hero-content">
                    <div class="hero-text">
                        <h1>Welcome to TechHub Electronics</h1>
                        <p>Discover the latest in technology with our curated selection of premium electronics. From smartphones to smart home devices, we bring you the future today.</p>
                        <button class="btn btn-primary" onclick="document.getElementById('products').scrollIntoView({behavior: 'smooth'})">
                            Explore Products
                        </button>
                    </div>
                    <div class="hero-visual">
                        <div class="tech-grid">
                            <div class="tech-card">📱 Smartphones</div>
                            <div class="tech-card">💻 Laptops</div>
                            <div class="tech-card">🎧 Audio</div>
                            <div class="tech-card">🎮 Gaming</div>
                            <div class="tech-card">🏠 Smart Home</div>
                            <div class="tech-card">⚡ Accessories</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="products" class="section">
            <div class="container">
                <h2 class="section-title">Featured Products</h2>
                <div class="products-grid" id="productsGrid">
                    <!-- Products will be loaded here -->
                </div>
            </div>
        </section>

        <section id="about" class="section">
            <div class="container">
                <h2 class="section-title">About TechHub</h2>
                <div class="about-content">
                    <div class="about-text">
                        <p>TechHub Electronics has been at the forefront of technology retail since our founding. We're passionate about bringing you the latest innovations in consumer electronics, from cutting-edge smartphones to revolutionary smart home solutions.</p>
                        <p>Our team of tech enthusiasts carefully curates every product in our catalog, ensuring that you have access to only the best technology available. We believe in quality, innovation, and exceptional customer service.</p>
                        <p>Whether you're a tech professional, gaming enthusiast, or someone who simply appreciates quality electronics, TechHub is your destination for premium technology products.</p>
                    </div>
                    <div class="about-stats">
                        <div class="stat-card">
                            <div class="stat-number">1000+</div>
                            <div>Happy Customers</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number">25+</div>
                            <div>Product Categories</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number">5★</div>
                            <div>Customer Rating</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number">24/7</div>
                            <div>Support Available</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="contact" class="section">
            <div class="container">
                <h2 class="section-title">Contact Us</h2>
                <div class="contact-content">
                    <div class="contact-form">
                        <h3>Get in Touch</h3>
                        <form id="contactForm">
                            <div class="form-group">
                                <label for="name">Name</label>
                                <input type="text" id="name" name="name" required>
                            </div>
                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" id="email" name="email" required>
                            </div>
                            <div class="form-group">
                                <label for="message">Message</label>
                                <textarea id="message" name="message" rows="5" required></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Send Message</button>
                        </form>
                    </div>
                    <div class="contact-info">
                        <div class="contact-item">
                            <div class="contact-icon">📍</div>
                            <div>
                                <h4>Address</h4>
                                <p>Taguig, Metro Manila, Philippines</p>
                            </div>
                        </div>
                        <div class="contact-item">
                            <div class="contact-icon">📞</div>
                            <div>
                                <h4>Phone</h4>
                                <p>+63 (2) 123-4567</p>
                            </div>
                        </div>
                        <div class="contact-item">
                            <div class="contact-icon">✉️</div>
                            <div>
                                <h4>Email</h4>
                                <p>info@techhub.ph</p>
                            </div>
                        </div>
                        <div class="contact-item">
                            <div class="contact-icon">🕒</div>
                            <div>
                                <h4>Hours</h4>
                                <p>Mon - Sat: 9AM - 8PM</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>TechHub</h3>
                    <p>Your trusted technology partner, bringing you the latest innovations in electronics.</p>
                </div>
                <div class="footer-section">
                    <h3>Quick Links</h3>
                    <a href="#home">Home</a>
                    <a href="#products">Products</a>
                    <a href="#about">About</a>
                    <a href="#contact">Contact</a>
                </div>
                <div class="footer-section">
                    <h3>Categories</h3>
                    <a href="#">Smartphones</a>
                    <a href="#">Laptops</a>
                    <a href="#">Audio Devices</a>
                    <a href="#">Gaming</a>
                    <a href="#">Smart Home</a>
                </div>
                <div class="footer-section">
                    <h3>Support</h3>
                    <a href="#">Help Center</a>
                    <a href="#">Warranty</a>
                    <a href="#">Returns</a>
                    <a href="#">Shipping</a>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2025 TechHub Electronics. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Sign In Modal -->
    <div id="signinModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('signin')">&times;</span>
            <h2>Sign In</h2>
            <form id="signinForm">
                <div class="form-group">
                    <label for="signin-email">Email</label>
                    <input type="email" id="signin-email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="signin-password">Password</label>
                    <input type="password" id="signin-password" name="password" required>
                </div>
                <button type="submit" class="btn btn-primary">Sign In</button>
            </form>
        </div>
    </div>

    <!-- Sign Up Modal -->
    <div id="signupModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('signup')">&times;</span>
            <h2>Sign Up</h2>
            <form id="signupForm">
                <div class="form-group">
                    <label for="signup-name">Name</label>
                    <input type="text" id="signup-name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="signup-email">Email</label>
                    <input type="email" id="signup-email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="signup-password">Password</label>
                    <input type="password" id="signup-password" name="password" required>
                </div>
                <div class="form-group">
                    <label for="signup-confirm">Confirm Password</label>
                    <input type="password" id="signup-confirm" name="confirm" required>
                </div>
                <button type="submit" class="btn btn-primary">Sign Up</button>
            </form>
        </div>
    </div>

    <script>
        // XML Product Data
        const xmlData = `<?xml version="1.0" encoding="UTF-8"?>
        <store>
            <metadata>
                <name>TechHub Electronics</name>
                <version>1.0</version>
                <currency>PHP</currency>
                <last_updated>2025-06-28</last_updated>
            </metadata>
            <products>
                <product>
                    <id>3001</id>
                    <name>Galaxy S24 Ultra</name>
                    <category>Smartphones</category>
                    <price>65000</price>
                    <currency>PHP</currency>
                    <description>Latest flagship smartphone with AI-powered camera and S Pen</description>
                    <image>images/galaxy_s24_ultra.jpg</image>
                    <stock>24</stock>
                    <rating>4.8</rating>
                    <review_count>42</review_count>
                    <featured>true</featured>
                    <on_sale>false</on_sale>
                </product>
                <product>
                    <id>3002</id>
                    <name>iPhone 15 Pro</name>
                    <category>Smartphones</category>
                    <price>70000</price>
                    <currency>PHP</currency>
                    <description>Premium iPhone with titanium design and advanced camera system</description>
                    <image>images/iphone_15_pro.jpg</image>
                    <stock>18</stock>
                    <rating>4.9</rating>
                    <review_count>38</review_count>
                    <featured>true</featured>
                    <on_sale>false</on_sale>
                </product>
                <product>
                    <id>3005</id>
                    <name>MacBook Air M3</name>
                    <category>Laptops</category>
                    <price>75000</price>
                    <currency>PHP</currency>
                    <description>Ultra-thin laptop with Apple's latest M3 chip for exceptional performance</description>
                    <image>images/macbook_air_m3.jpg</image>
                    <stock>15</stock>
                    <rating>4.8</rating>
                    <review_count>35</review_count>
                    <featured>true</featured>
                    <on_sale>false</on_sale>
                </product>
                <product>
                    <id>3009</id>
                    <name>AirPods Pro (3rd Gen)</name>
                    <category>Audio Devices</category>
                    <price>12000</price>
                    <currency>PHP</currency>
                    <description>Premium wireless earbuds with adaptive transparency and spatial audio</description>
                    <image>images/airpods_pro_3.jpg</image>
                    <stock>40</stock>
                    <rating>4.8</rating>
                    <review_count>56</review_count>
                    <featured>true</featured>
                    <on_sale>false</on_sale>
                </product>
                <product>
                    <id>3010</id>
                    <name>Sony WH-1000XM5</name>
                    <category>Audio Devices</category>
                    <price>18000</price>
                    <currency>PHP</currency>
                    <description>Industry-leading noise canceling wireless headphones</description>
                    <image>images/sony_wh1000xm5.jpg</image>
                    <stock>25</stock>
                    <rating>4.9</rating>
                    <review_count>44</review_count>
                    <featured>true</featured>
                    <on_sale>false</on_sale>
                </product>
                <product>
                    <id>3013</id>
                    <name>PlayStation 5</name>
                    <category>Gaming</category>
                    <price>35000</price>
                    <currency>PHP</currency>
                    <description>Next-gen gaming console with 4K gaming and ray tracing</description>
                    <image>images/playstation_5.jpg</image>
                    <stock>12</stock>
                    <rating>4.9</rating>
                    <review_count>48</review_count>
                    <featured>true</featured>
                    <on_sale>false</on_sale>
                </product>
            </products>
        </store>`;

        // Parse XML and load products
        function loadProducts() {
            const parser = new DOMParser();
            const xmlDoc = parser.parseFromString(xmlData, "text/xml");
            const products = xmlDoc.getElementsByTagName("product");
            const productsGrid = document.getElementById("productsGrid");
            
            for (let i = 0; i < products.length; i++) {
                const product = products[i];
                const name = product.getElementsByTagName("name")[0].textContent;
                const price = product.getElementsByTagName("price")[0].textContent;
                const currency = product.getElementsByTagName("currency")[0].textContent;
                const description = product.getElementsByTagName("description")[0].textContent;
                const rating = product.getElementsByTagName("rating")[0].textContent;
                const reviewCount = product.getElementsByTagName("review_count")[0].textContent;
                const category = product.getElementsByTagName("category")[0].textContent;
                const stock = product.getElementsByTagName("stock")[0].textContent;
                
                const productCard = document.createElement("div");
                productCard.className = "product-card";
                productCard.innerHTML = `
                    <h3>${name}</h3>
                    <p>${description}</p>
                    <div class="product-price">${currency} ${parseInt(price).toLocaleString()}</div>
                    <div class="product-rating">
                        <span class="stars">${'★'.repeat(Math.floor(rating))}</span>
                        <span>${rating} (${reviewCount} reviews)</span>
                    </div>
                    <p><strong>Category:</strong> ${category}</p>
                    <p><strong>Stock:</strong> ${stock} units</p>
                    <button class="btn btn-primary" onclick="addToCart('${name}')">Add to Cart</button>
                `;
                productsGrid.appendChild(productCard);
            }
        }

        // Modal functions
        function openModal(type) {
            document.getElementById(type + 'Modal').style.display = 'block';
        }

        function closeModal(type) {
            document.getElementById(type + 'Modal').style.display = 'none';
        }

        // Form handlers
        document.getElementById('contactForm').addEventListener('submit', function(e) {
            e.preventDefault();
            alert('Thank you for your message! We will get back to you soon.');
            this.reset();
        });

        document.getElementById('signinForm').addEventListener('submit', function(e) {
            e.preventDefault();
            alert('Sign in functionality would be implemented here.');
            closeModal('signin');
        });

        document.getElementById('signupForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const password = document.getElementById('signup-password').value;
            const confirm = document.getElementById('signup-confirm').value;
            
            if (password !== confirm) {
                alert('Passwords do not match!');
                return;
            }
            
            alert('Sign up functionality would be implemented here.');
            closeModal('signup');
        });

        // Add to cart function
        function addToCart(productName) {
            alert(`${productName} added to cart!`);
        }

        // Scroll animations
        function handleScroll() {
            const sections = document.querySelectorAll('.section');
            const scrollTop = window.pageYOffset;
            const windowHeight = window.innerHeight;
            
            sections.forEach(section => {
                const sectionTop = section.offsetTop;
                const sectionHeight = section.offsetHeight;
                
                if (scrollTop > sectionTop - windowHeight + 100) {
                    section.classList.add('visible');
                }
            });
        }

        // Smooth scrolling for navigation
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

        // Header background on scroll
        function handleHeaderScroll() {
            const header = document.querySelector('header');
            if (window.scrollY > 100) {
                header.style.background = 'rgba(12, 12, 12, 0.98)';
            } else {
                header.style.background = 'rgba(12, 12, 12, 0.95)';
            }
        }

        // Close modal when clicking outside
        window.addEventListener('click', function(e) {
            const signinModal = document.getElementById('signinModal');
            const signupModal = document.getElementById('signupModal');
            
            if (e.target === signinModal) {
                closeModal('signin');
            }
            if (e.target === signupModal) {
                closeModal('signup');
            }
        });

        // Initialize
        window.addEventListener('load', function() {
            loadProducts();
            handleScroll();
        });

        window.addEventListener('scroll', function() {
            handleScroll();
            handleHeaderScroll();
        });

        // Mobile menu toggle (for future enhancement)
        function toggleMobileMenu() {
            const navLinks = document.querySelector('.nav-links');
            navLinks.classList.toggle('active');
        }

        // Product filtering (for future enhancement)
        function filterProducts(category) {
            const products = document.querySelectorAll('.product-card');
            products.forEach(product => {
                const productCategory = product.querySelector('p:last-of-type').textContent;
                if (category === 'all' || productCategory.includes(category)) {
                    product.style.display = 'block';
                } else {
                    product.style.display = 'none';
                }
            });
        }

        // Search functionality (for future enhancement)
        function searchProducts(query) {
            const products = document.querySelectorAll('.product-card');
            products.forEach(product => {
                const productName = product.querySelector('h3').textContent.toLowerCase();
                const productDesc = product.querySelector('p').textContent.toLowerCase();
                
                if (productName.includes(query.toLowerCase()) || productDesc.includes(query.toLowerCase())) {
                    product.style.display = 'block';
                } else {
                    product.style.display = 'none';
                }
            });
        }

        // Newsletter signup (for future enhancement)
        function subscribeNewsletter(email) {
            alert(`Thank you for subscribing with email: ${email}`);
        }

        // Social media sharing (for future enhancement)
        function shareProduct(productName) {
            if (navigator.share) {
                navigator.share({
                    title: productName,
                    text: `Check out this amazing product: ${productName}`,
                    url: window.location.href
                });
            } else {
                // Fallback for browsers that don't support Web Share API
                const shareUrl = `https://twitter.com/intent/tweet?text=Check out this amazing product: ${productName}&url=${window.location.href}`;
                window.open(shareUrl, '_blank');
            }
        }

        // Dark/Light mode toggle (for future enhancement)
        function toggleTheme() {
            document.body.classList.toggle('light-theme');
            const theme = document.body.classList.contains('light-theme') ? 'light' : 'dark';
            localStorage.setItem('theme', theme);
        }

        // Load saved theme
        function loadTheme() {
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme === 'light') {
                document.body.classList.add('light-theme');
            }
        }

        // Initialize theme
        loadTheme();
    </script>
</body>
</html>