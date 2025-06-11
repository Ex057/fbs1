<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FormBase Survey Tool</title>
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1e40af;
            --secondary: #fbbf24;
            --secondary-dark: #f59e42;
            --accent: #10b981;
            --background-gradient: linear-gradient(135deg, #e0e7ff 0%, #f0fdfa 100%);
            --header-bg: #f9fafb;
            --feature-bg: #f3f4f6;
            --feature-hover: #fffbe6;
            --text-main: #22223b;
            --text-muted: #6b7280;
            --footer-bg: #22223b;
            --footer-text: #fbbf24;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: var(--text-main);
            background: var(--background-gradient);
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        header {
            background: var(--header-bg);
            backdrop-filter: blur(10px);
            padding: 1rem 0;
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.06);
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .logo {
            font-size: 1.8rem;
            font-weight: bold;
            color: var(--primary-dark);
            letter-spacing: 1px;
        }

        .nav-buttons {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            text-align: center;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(37, 99, 235, 0.18);
        }

        .btn-secondary {
            background: var(--secondary);
            color: var(--text-main);
            border: 2px solid var(--secondary-dark);
        }

        .btn-secondary:hover {
            background: var(--secondary-dark);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(251, 191, 36, 0.18);
        }

        .hero {
            padding: 80px 0;
            text-align: center;
            color: var(--primary-dark);
        }

        .hero h1 {
            font-size: 3.5rem;
            margin-bottom: 20px;
            font-weight: 700;
            text-shadow: 0 2px 4px rgba(37, 99, 235, 0.08);
        }

        .hero p {
            font-size: 1.3rem;
            margin-bottom: 40px;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
            opacity: 0.95;
            color: var(--text-muted);
        }

        .features {
            background: var(--feature-bg);
            padding: 80px 0;
            margin-top: -40px;
            border-radius: 40px 40px 0 0;
        }

        .features h2 {
            text-align: center;
            font-size: 2.5rem;
            margin-bottom: 60px;
            color: var(--primary-dark);
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 40px;
            margin-bottom: 60px;
        }

        .feature-card {
            background: white;
            padding: 40px 30px;
            border-radius: 15px;
            text-align: center;
            transition: all 0.3s ease;
            border: 1px solid #e5e7eb;
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.04);
        }

        .feature-card:hover {
            transform: translateY(-5px) scale(1.03);
            box-shadow: 0 15px 35px rgba(251, 191, 36, 0.10);
            background: var(--feature-hover);
        }

        .feature-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 2rem;
            color: white;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.10);
        }

        .feature-card h3 {
            font-size: 1.5rem;
            margin-bottom: 15px;
            color: var(--primary-dark);
        }

        .feature-card p {
            color: var(--text-muted);
            line-height: 1.6;
        }

        .cta-section {
            background: linear-gradient(135deg, var(--accent), var(--primary));
            padding: 80px 0;
            text-align: center;
            color: white;
        }

        .cta-section h2 {
            font-size: 2.5rem;
            margin-bottom: 20px;
        }

        .cta-section p {
            font-size: 1.2rem;
            margin-bottom: 40px;
            opacity: 0.95;
        }

        .cta-buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn-large {
            padding: 16px 32px;
            font-size: 1.1rem;
        }

        .btn-white {
            background: white;
            color: var(--primary);
        }

        .btn-white:hover {
            background: var(--feature-bg);
            color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.08);
        }

        footer {
            background: var(--footer-bg);
            color: var(--footer-text);
            text-align: center;
            padding: 40px 0;
        }

        @media (max-width: 768px) {
            .hero h1 {
                font-size: 2.5rem;
            }

            .hero p {
                font-size: 1.1rem;
            }

            .features h2 {
                font-size: 2rem;
            }

            .cta-section h2 {
                font-size: 2rem;
            }

            .header-content {
                justify-content: center;
                text-align: center;
            }

            .nav-buttons {
                justify-content: center;
            }

            .cta-buttons {
                flex-direction: column;
                align-items: center;
            }
        }

        @media (max-width: 480px) {
            .btn {
                padding: 10px 20px;
                font-size: 0.9rem;
            }

            .btn-large {
                padding: 14px 28px;
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <div class="logo">FormBase Survey Tool</div>
                <div class="nav-buttons">
                    <a href="fbs/admin/login" class="btn btn-primary">Login</a>
                    <a href="fbs/admin/register" class="btn btn-secondary">Register</a>
                </div>
            </div>
        </div>
    </header>

    <main>
        <section class="hero">
            <div class="container">
                <h1>Advanced Survey Management</h1>
                <p>Create, customize, and deploy powerful surveys with seamless DHIS2 integration and multi-language support</p>
            </div>
        </section>

        <section class="features">
            <div class="container">
                <h2>Powerful Features</h2>
                <div class="features-grid">
                    <div class="feature-card">
                        <div class="feature-icon">🌐</div>
                        <h3>Multi-Language Support</h3>
                        <p>Create surveys in multiple languages with built-in translation capabilities to reach diverse audiences effectively.</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">⚙️</div>
                        <h3>Customizable Questions</h3>
                        <p>Design and edit questions with various input types, validation rules, and conditional logic to suit your specific needs.</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">📊</div>
                        <h3>DHIS2 Integration</h3>
                        <p>Seamlessly create surveys from DHIS2 data and automatically sync results back to your DHIS2 instance for comprehensive analytics.</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">📱</div>
                        <h3>Mobile-First Design</h3>
                        <p>Respondents can easily take surveys on any device by simply scanning a QR code - perfect for field data collection.</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">📋</div>
                        <h3>Survey Builder</h3>
                        <p>Intuitive drag-and-drop interface to create complex surveys, attach questions, and configure survey flow with ease.</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">📈</div>
                        <h3>Real-time Results</h3>
                        <p>Monitor survey responses in real-time with automatic data synchronization and comprehensive reporting capabilities.</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="cta-section">
            <div class="container">
                <h2>Ready to Get Started?</h2>
                <p>Join thousands of organizations using our survey tool for better data collection and insights</p>
                <div class="cta-buttons">
                    <a href="fbs/admin/register" class="btn btn-white btn-large">Create Account</a>
                    <a href="fbs/admin/login" class="btn btn-secondary btn-large">Sign In</a>
                </div>
            </div>
        </section>
    </main>

    <footer>
        <div class="container" style="overflow: hidden; position: relative; height: 2.5em;">
            <div id="footer-marquee" style="white-space: nowrap; display: inline-block; position: absolute; left: 0; top: 0;">
                <p style="display: inline; font-size: 1rem;">&copy; 2025 FormBase Survey Tool. Empowering data collection worldwide.</p>
            </div>
        </div>
        <script>
            const marquee = document.getElementById('footer-marquee');
            const container = marquee.parentElement;
            let pos = container.offsetWidth;

            function animateMarquee() {
                pos -= 1;
                if (pos < -marquee.offsetWidth) {
                    pos = container.offsetWidth;
                }
                marquee.style.transform = `translateX(${pos}px)`;
                requestAnimationFrame(animateMarquee);
            }

            // Ensure correct width after fonts load
            window.addEventListener('load', () => {
                pos = container.offsetWidth;
                animateMarquee();
            });
            window.addEventListener('resize', () => {
                pos = container.offsetWidth;
            });
        </script>
    </footer>
</body>
</html>
