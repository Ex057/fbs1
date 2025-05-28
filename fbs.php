<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Form Base Survey Tool</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 1rem 0;
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.1);
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
            color: #4a5568;
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
            background: #4299e1;
            color: white;
        }

        .btn-primary:hover {
            background: #3182ce;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(66, 153, 225, 0.4);
        }

        .btn-secondary {
            background: transparent;
            color: #4a5568;
            border: 2px solid #4a5568;
        }

        .btn-secondary:hover {
            background: #4a5568;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(74, 85, 104, 0.3);
        }

        .hero {
            padding: 80px 0;
            text-align: center;
            color: white;
        }

        .hero h1 {
            font-size: 3.5rem;
            margin-bottom: 20px;
            font-weight: 700;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }

        .hero p {
            font-size: 1.3rem;
            margin-bottom: 40px;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
            opacity: 0.9;
        }

        .features {
            background: white;
            padding: 80px 0;
            margin-top: -40px;
            border-radius: 40px 40px 0 0;
        }

        .features h2 {
            text-align: center;
            font-size: 2.5rem;
            margin-bottom: 60px;
            color: #2d3748;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 40px;
            margin-bottom: 60px;
        }

        .feature-card {
            background: #f7fafc;
            padding: 40px 30px;
            border-radius: 15px;
            text-align: center;
            transition: all 0.3s ease;
            border: 1px solid #e2e8f0;
        }

        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            background: white;
        }

        .feature-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 2rem;
        }

        .feature-card h3 {
            font-size: 1.5rem;
            margin-bottom: 15px;
            color: #2d3748;
        }

        .feature-card p {
            color: #4a5568;
            line-height: 1.6;
        }

        .cta-section {
            background: linear-gradient(135deg, #4299e1, #667eea);
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
            opacity: 0.9;
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
            color: #4299e1;
        }

        .btn-white:hover {
            background: #f7fafc;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(255, 255, 255, 0.3);
        }

        footer {
            background: #2d3748;
            color: white;
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
                <div class="logo">Form Base Survey Tool</div>
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
        <div class="container">
            <p>&copy; 2025 Form Base Survey Tool. Empowering data collection worldwide.</p>
        </div>
    </footer>
</body>
</html>