<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EcoWaste Solutions - Modern Waste Management</title>
    <link rel="stylesheet" href="style.css?v=2">
    <link rel="stylesheet" href="theme.css?v=2">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
</head>
<body>

    <header>
        <nav>
            <div class="container">
                <a href="#" class="logo">EcoWaste</a>
                <button id="theme-toggle" class="theme-toggle" aria-label="Toggle theme"></button>
                <ul class="nav-links">
                    <li><a href="#about">About Us</a></li>
                    <li><a href="#services">Services</a></li>
                    <li><a href="#how-it-works">How It Works</a></li>
                    <li><a href="#contact">Contact</a></li>
                    <li><a href="login.php" class="btn btn-primary">User Login</a></li>
                    <li><a href="driver_login.php" class="btn btn-secondary">Driver Login</a></li>
                    <li><a href="admin_login.php" class="btn btn-secondary">Admin Login</a></li>
                </ul>
            </div>
        </nav>
    </header>

    <main>
        <section id="hero">
            <div class="container">
                <h1>A Cleaner Planet, One Bin at a Time.</h1>
                <p>Innovative waste management solutions for a sustainable future.</p>
                <a href="#services" class="btn btn-secondary">Explore Our Services</a>
            </div>
        </section>

        <section id="about">
            <div class="container">
                <h2>About EcoWaste Solutions</h2>
                <p>We are dedicated to revolutionizing waste management through technology and community engagement. Our mission is to create efficient, sustainable, and eco-friendly systems that reduce landfill waste and promote recycling for a healthier planet.</p>
            </div>
        </section>

        <section id="services">
            <div class="container">
                <h2>Our Services</h2>
                <div class="services-grid">
                    <div class="service-card">
                        <h3>Residential Pickup</h3>
                        <p>Scheduled and on-demand waste collection services for homes and apartments. We handle everything from general waste to recyclables.</p>
                    </div>
                    <div class="service-card">
                        <h3>Commercial Solutions</h3>
                        <p>Customized waste management plans for businesses, schools, and organizations of all sizes. Includes waste audits and staff training.</p>
                    </div>
                    <div class="service-card">
                        <h3>Recycling Programs</h3>
                        <p>Advanced sorting and processing for paper, plastics, glass, and metals. We make recycling easy and effective for everyone.</p>
                    </div>
                </div>
            </div>
        </section>
        
        <section id="how-it-works">
            <div class="container">
                <h2>How It Works</h2>
                <div class="steps-grid">
                    <div class="step">
                        <div class="step-number">1</div>
                        <h3>Segregate Your Waste</h3>
                        <p>Separate your waste into categories: Organic, Recyclable, and General. Proper segregation is the first step to effective recycling.</p>
                    </div>
                    <div class="step">
                        <div class="step-number">2</div>
                        <h3>Schedule a Pickup</h3>
                        <p>Use our website or mobile app to schedule a convenient pickup time. You'll receive a confirmation and reminder.</p>
                    </div>
                    <div class="step">
                        <div class="step-number">3</div>
                        <h3>We Collect & Process</h3>
                        <p>Our team collects the segregated waste and transports it to our state-of-the-art facilities for responsible processing and recycling.</p>
                    </div>
                </div>
            </div>
        </section>

        <section id="contact">
            <div class="container">
                <h2>Get in Touch</h2>
                <p>Have questions or want to sign up for a service? Send us a message!</p>
                <form id="contact-form">
                    <input type="text" name="name" placeholder="Your Name" required>
                    <input type="email" name="email" placeholder="Your Email" required>
                    <textarea name="message" rows="5" placeholder="Your Message" required></textarea>
                    <button type="submit" class="btn btn-primary">Send Message</button>
                </form>
            </div>
        </section>
    </main>

    <footer>
        <div class="container">
            <p>&copy; 2025 EcoWaste Solutions. All Rights Reserved.</p>
        </div>
    </footer>

    <script src="theme.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const contactForm = document.getElementById('contact-form');

            contactForm.addEventListener('submit', function(e) {
                e.preventDefault();
                showAlert('Thank you for your message! We will get back to you soon.', 'success');
                contactForm.reset();
            });
        });
    </script>
</body>
</html>