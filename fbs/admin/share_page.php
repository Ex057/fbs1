<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ministry of Health - Feedback</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-blue: #007bff;
            --dark-blue: #0056b3;
            --uganda-black: #000000;
            --uganda-yellow: #FFCE00;
            --uganda-red: #FF0000;
            --light-blue-bg: #e6f7ff;
        }
        
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background-color: #f8f9fa;
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            color: #333;
            line-height: 1.6;
        }
        
        .feedback-container {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
            max-width: 600px;
            width: 100%;
            position: relative;
            overflow: hidden;
        }
        
        .header-section {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 25px;
        }
        
        .logo-container {
            width: 120px;
            height: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 10px;
            background: white;
        }
        
        .logo-container img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        
        .title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 5px;
            color: #555;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .subtitle {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 25px;
            color: var(--primary-blue);
            position: relative;
            padding-bottom: 10px;
        }
        
        .subtitle::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 3px;
            background: linear-gradient(90deg, var(--uganda-black), var(--uganda-yellow), var(--uganda-red));
        }
        
        .flag-bar {
            height: 24px;
            width: 100%;
            margin: 25px 0;
            display: flex;
            border-radius: 4px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .flag-black {
            flex: 1;
            background-color: var(--uganda-black);
        }
        
        .flag-yellow {
            flex: 1;
            background-color: var(--uganda-yellow);
        }
        
        .flag-red {
            flex: 1;
            background-color: var(--uganda-red);
        }
        
        .qr-section {
            background-color: var(--light-blue-bg);
            padding: 30px;
            border-radius: 8px;
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-top: 30px;
            border: 1px solid #ccefff;
        }
        
        .qr-code-container {
            margin-bottom: 25px;
            padding: 15px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.05);
        }
        
        .qr-code-container img {
            width: 200px;
            height: 200px;
            display: block;
        }
        
        .instructions {
            font-size: 18px;
            line-height: 1.5;
            text-align: center;
            margin: 20px 0;
            color: #444;
            font-weight: 500;
        }
        
        .download-button {
            margin-top: 25px;
            padding: 12px 25px;
            background-color: var(--primary-blue);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
            box-shadow: 0 3px 6px rgba(0, 123, 255, 0.2);
        }
        
        .download-button:hover {
            background-color: var(--dark-blue);
            transform: translateY(-2px);
            box-shadow: 0 5px 10px rgba(0, 123, 255, 0.3);
        }
        
        .download-button:active {
            transform: translateY(0);
        }
        
        .footer-note {
            margin-top: 30px;
            font-size: 14px;
            color: #777;
            text-align: center;
            border-top: 1px solid #eee;
            padding-top: 15px;
        }
        
        @media (max-width: 480px) {
            .feedback-container {
                padding: 25px;
            }
            
            .subtitle {
                font-size: 24px;
            }
            
            .qr-section {
                padding: 20px;
            }
            
            .qr-code-container img {
                width: 180px;
                height: 180px;
            }
        }
    </style>
</head>
<body>
    <div class="feedback-container">
        <div class="header-section">
            <div class="logo-container">
                <img src="asets/asets/img/loog.jpg" alt="Ministry of Health Logo" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIxMjAiIGhlaWdodD0iMTIwIiB2aWV3Qm94PSIwIDAgMjAwIDIwMCI+PHJlY3Qgd2lkdGg9IjIwMCIgaGVpZ2h0PSIyMDAiIGZpbGw9IiNlZWVlZWUiLz48dGV4dCB4PSIxMDAiIHk9IjEwMCIgZm9udC1mYW1pbHk9IkFyaWFsIiBmb250LXNpemU9IjE2IiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBhbGlnbm1lbnQtYmFzZWxpbmU9Im1pZGRsZSIgZmlsbD0iIzY2NiI+TWluaXN0cnkgb2YgSGVhbHRoIExvZ288L3RleHQ+PC9zdmc+'">
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
                Give your feedback about<br>
                services received by scanning<br>
                this QR Code
            </div>
            
            <button class="download-button" onclick="downloadPage()">
                <i class="fas fa-download"></i> Download Feedback Page
            </button>
        </div>
        
        <div class="footer-note">
            Thank you for helping us improve our services
        </div>
    </div>

    <!-- QR Code Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
        // Generate QR Code
        const surveyUrl = decodeURIComponent("<?php echo $_GET['url']; ?>");


        
        new QRCode(document.getElementById('qr-code'), { 
            text: surveyUrl,
            width: 200,
            height: 200,
            colorDark: "#000000",
            colorLight: "#ffffff",
            correctLevel: QRCode.CorrectLevel.H
        });

        

        // Function to download the page as a complete HTML file
        function downloadPage() {
            // Create a clone of the current document
            const htmlContent = `<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ministry of Health - Feedback</title>
    <style>
        ${Array.from(document.styleSheets)
            .map(sheet => {
                try {
                    return Array.from(sheet.cssRules)
                        .map(rule => rule.cssText)
                        .join('\n');
                } catch (e) {
                    return '';
                }
            })
            .join('\n')}
    </style>
</head>
<body>
    ${document.querySelector('.feedback-container').outerHTML}
    
    <!-- Embedded QR Code (already rendered) -->
    <div id="qr-code" style="display:none;">
        <img src="${document.querySelector('#qr-code img').src}" alt="QR Code" width="200" height="200">
    </div>
</body>
</html>`;

            // Create a blob with the HTML content
            const blob = new Blob([htmlContent], { type: 'text/html' });
            
            // Create a download link and trigger it
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = 'ministry-of-health-feedback.html';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        }
    </script>
</body>
</html>