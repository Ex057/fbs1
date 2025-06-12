<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ministry of Health - Feedback</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-blue: #007bff;
            --dark-blue: #0056b3;
            --uganda-black: #000000;
            --uganda-yellow: #FFCE00;
            --uganda-red: #FF0000;
            --light-blue-bg: #e6f7ff;
            --primary-font: 'Poppins', sans-serif;
            --text-color-dark: #2c3e50;
        }

        body {
            font-family: var(--primary-font);
            background: linear-gradient(135deg, #f0f4f8, #e6e9ee); /* Subtle gradient */
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            color: var(--text-color-dark);
            line-height: 1.6;
        }

        .feedback-container {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15); /* Stronger shadow */
            max-width: 650px; /* Slightly wider */
            width: 100%;
            position: relative;
            overflow: hidden;
            border: 1px solid #f0f0f0;
        }

        .header-section {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 30px;
        }

        .logo-container {
            width: 130px; /* Slightly larger */
            height: 130px; /* Slightly larger */
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 25px;
            border: 1px solid #eee;
            border-radius: 50%; /* Circular logo container */
            padding: 10px;
            background: white;
            box-shadow: 0 4px 10px rgba(0,0,0,0.08);
        }

        .logo-container img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .title {
            font-size: 19px;
            font-weight: 600;
            margin-bottom: 8px;
            color: #555;
            text-transform: uppercase;
            letter-spacing: 1.5px;
        }

        .subtitle {
            font-size: 34px; /* Larger */
            font-weight: 700;
            margin-bottom: 30px;
            color: var(--primary-blue);
            position: relative;
            padding-bottom: 12px;
            text-align: center;
        }

        .subtitle::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 100px; /* Wider underline */
            height: 4px; /* Thicker underline */
            background: linear-gradient(90deg, var(--uganda-black), var(--uganda-yellow), var(--uganda-red));
            border-radius: 2px;
        }

        .flag-bar {
            height: 28px; /* Slightly taller */
            width: 100%;
            margin: 30px 0;
            display: flex;
            border-radius: 6px; /* More rounded */
            overflow: hidden;
            box-shadow: 0 3px 8px rgba(0,0,0,0.1);
        }

        .flag-black { flex: 1; background-color: var(--uganda-black); }
        .flag-yellow { flex: 1; background-color: var(--uganda-yellow); }
        .flag-red { flex: 1; background-color: var(--uganda-red); }

        .qr-section {
            background-color: var(--light-blue-bg);
            padding: 35px; /* More padding */
            border-radius: 10px; /* More rounded corners */
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-top: 35px;
            border: 1px solid #ccefff;
            box-shadow: inset 0 2px 8px rgba(0, 123, 255, 0.1); /* Inner shadow */
        }

        .qr-code-container {
            margin-bottom: 30px;
            padding: 20px; /* More padding */
            background: white;
            border-radius: 10px;
            box-shadow: 0 6px 15px rgba(0,0,0,0.1); /* Stronger shadow */
            border: 2px solid var(--primary-blue); /* Prominent border */
            transition: transform 0.3s ease-in-out;
        }

        .qr-code-container:hover {
            transform: scale(1.02); /* Slight scale up on hover */
        }
        
        .qr-code-container img {
            width: 200px;
            height: 200px;
            display: block;
        }

        .instructions {
            font-size: 20px; /* Larger instructions */
            line-height: 1.6;
            text-align: center;
            margin: 25px 0;
            color: var(--text-color-dark);
            font-weight: 600; /* Bolder */
            display: flex;
            flex-direction: column; /* Stack text and icon */
            align-items: center;
            gap: 15px; /* Space between icon and text */
        }

        .instructions .icon {
            font-size: 36px; /* Larger icon */
            color: var(--primary-blue);
        }
        
        .download-button {
            margin-top: 30px;
            padding: 14px 30px; /* Larger button */
            background-color: var(--primary-blue);
            color: white;
            border: none;
            border-radius: 8px; /* More rounded */
            cursor: pointer;
            font-size: 18px; /* Larger text */
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 12px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(0, 123, 255, 0.3);
        }

        .download-button:hover {
            background-color: var(--dark-blue);
            transform: translateY(-3px); /* More pronounced lift */
            box-shadow: 0 6px 18px rgba(0, 123, 255, 0.4);
            text-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .download-button:active {
            transform: translateY(0);
            box-shadow: 0 2px 5px rgba(0, 123, 255, 0.2);
        }
        
        .footer-note {
            margin-top: 35px;
            font-size: 15px;
            color: #666;
            text-align: center;
            border-top: 1px solid #eee;
            padding-top: 20px;
            font-weight: 500;
        }
        
        @media (max-width: 600px) { /* Adjusted breakpoint */
            .feedback-container {
                padding: 30px;
            }
            
            .subtitle {
                font-size: 28px;
            }
            
            .qr-section {
                padding: 25px;
            }
            
            .qr-code-container img {
                width: 180px;
                height: 180px;
            }
            
            .instructions {
                font-size: 17px;
            }
            
            .download-button {
                font-size: 16px;
                padding: 12px 25px;
            }
        }
    </style>
</head>
<body>
    <div class="feedback-container">
        <div class="header-section">
            <div class="logo-container">
                <img src="argon-dashboard-master/assets/img/loog.jpg" alt="Ministry of Health Logo" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIxMjAiIGhlaWdodD0iMTIwIiB2aWV3Qm94PSIwIDAgMjAwIDIwMCI+PHJlY3Qgd2lkdGg9IjIwMCIgaGVpZ2h0PSIyMDAiIGZpbGw9IiNlZWVlZWUiLz48dGV4dCB4PSIxMDAiIHk9IjEwMCIgZm9udC1mYW1pbHk9IkFyaWFsIiBmb250LXNpemU9IjE2IiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBhbGlnbm1lbnQtYmFzZWxpbmU9Im1pZGRsZSIgZmlsbD0iIzY2NiI+TWluaXN0cnkgb2YgSGVhbHRoIExvZ288L3RleHQ+PC9zdmc+'">
            </div>
            <div class="title">THE REPUBLIC OF UGANDA</div>
            <div class="subtitle">MINISTRY OF HEALTH</div>
        </div>
        
        <div class="flag-bar">
            <div class="flag-black"></div>
            <div class="flag-yellow"></div>
            <div class="flag-red"></div>
        </div>
        
        <div class="qr-section">
            <div class="qr-code-container">
                <div id="qr-code"></div>
            </div>
            
            <div class="instructions">
                <i class="fas fa-qrcode icon"></i> <span>Scan this QR Code to Give Your Feedback<br>on Services Received</span>
            </div>
            
            <button class="download-button" onclick="downloadPage()">
                <i class="fas fa-download"></i> Download Feedback Page
            </button>
        </div>
        
        <div class="footer-note">
            Thank you for helping us improve our services.
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
        // PHP will populate this. Ensure your file is saved as .php
        const surveyUrl = decodeURIComponent("<?php echo htmlspecialchars($_GET['url'] ?? ''); ?>");

        // Basic validation for the URL
        if (surveyUrl) {
            new QRCode(document.getElementById('qr-code'), {
                text: surveyUrl,
                width: 200,
                height: 200,
                colorDark: "#000000",
                colorLight: "#ffffff",
                correctLevel: QRCode.CorrectLevel.H
            });
        } else {
            document.getElementById('qr-code').innerHTML = '<p style="color:red;">Error: QR code URL missing.</p>';
        }

        function downloadPage() {
            // Function to get all CSS rules from stylesheets
            const getAllCssRules = () => {
                let css = '';
                Array.from(document.styleSheets).forEach(sheet => {
                    try {
                        Array.from(sheet.cssRules || sheet.rules).forEach(rule => {
                            css += rule.cssText + '\n';
                        });
                    } catch (e) {
                        console.warn('Could not read CSS from sheet:', sheet.href || sheet.ownerNode, e);
                    }
                });
                return css;
            };

            const inlineCss = getAllCssRules();
            
            // Generate the current QR code image data URL
            const qrCodeImgSrc = document.querySelector('#qr-code img') ? document.querySelector('#qr-code img').src : '';

            const htmlContent = `<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ministry of Health - Feedback</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        ${inlineCss}
    </style>
</head>
<body>
    ${document.querySelector('.feedback-container').outerHTML}
    
    <div id="qr-code-download" style="display:none;">
        <img src="${qrCodeImgSrc}" alt="QR Code" width="200" height="200">
    </div>
</body>
</html>`;

            const blob = new Blob([htmlContent], { type: 'text/html' });
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = 'ministry-of-health-feedback.html';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(a.href); // Clean up the object URL
        }
    </script>
</body>
</html>