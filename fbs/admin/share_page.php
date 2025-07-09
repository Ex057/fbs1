<?php
session_start();

// Get the survey URL and survey_id from the URL parameters
$surveyUrl = $_GET['url'] ?? '';
$surveyId = $_GET['survey_id'] ?? null; // Explicitly passed from preview_form.php

// Fallback for default survey title if no custom title is found in localStorage
// or if surveyId is missing.
$defaultSurveyTitle = 'Ministry of Health Client Satisfaction Feedback Tool';

// Database connection for a more robust fallback for survey name
$servername = "localhost";
$username = "root";
$password = "root";
$dbname = "fbtv3";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    // Log error, but allow the page to load with default title
    error_log("Database Connection failed in share_page.php: " . $conn->connect_error);
} else {
    if ($surveyId) {
        $stmt = $conn->prepare("SELECT name FROM survey WHERE id = ?");
        $stmt->bind_param("i", $surveyId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $defaultSurveyTitle = htmlspecialchars($row['name']);
        }
        $stmt->close();
    }
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $defaultSurveyTitle; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Your existing CSS here */
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
            background: linear-gradient(135deg, #f0f4f8, #e6e9ee);
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
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            max-width: 650px;
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
            width: 130px;
            height: 130px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 25px;
            border: 1px solid #eee;
            border-radius: 50%;
            padding: 10px;
            background: white;
            box-shadow: 0 4px 10px rgba(0,0,0,0.08);
            overflow: hidden; /* Ensure logo doesn't overflow circular container */
        }

        .logo-container img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .title-uganda, .subtitle-moh {
            text-align: center;
        }

        .title-uganda {
            font-size: 19px;
            font-weight: 600;
            margin-bottom: 8px;
            color: #555;
            text-transform: uppercase;
            letter-spacing: 1.5px;
        }

        .subtitle-moh {
            font-size: 34px;
            font-weight: 700;
            margin-bottom: 30px;
            color: var(--primary-blue);
            position: relative;
            padding-bottom: 12px;
        }
        .subtitle-moh::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 100px;
            height: 4px;
            background: linear-gradient(90deg, var(--uganda-black), var(--uganda-yellow), var(--uganda-red));
            border-radius: 2px;
        }

        .flag-bar {
            height: 28px;
            width: 100%;
            margin: 30px 0;
            display: flex;
            border-radius: 6px;
            overflow: hidden;
            box-shadow: 0 3px 8px rgba(0,0,0,0.1);
        }
        .flag-black, .flag-yellow, .flag-red {
            flex: 1;
            /* Default colors are set in JS, but these provide a fallback */
            background-color: var(--uganda-black);
        }
        .flag-yellow { background-color: var(--uganda-yellow); }
        .flag-red { background-color: var(--uganda-red); }

        .qr-section {
            background-color: var(--light-blue-bg);
            padding: 35px;
            border-radius: 10px;
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-top: 35px;
            border: 1px solid #ccefff;
            box-shadow: inset 0 2px 8px rgba(0, 123, 255, 0.1);
        }

        .qr-code-container {
            margin-bottom: 30px;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 6px 15px rgba(0,0,0,0.1);
            border: 2px solid var(--primary-blue);
            transition: transform 0.3s ease-in-out;
            display: flex; /* To center QR code image if it's the content */
            justify-content: center;
            align-items: center;
        }

        .qr-code-container:hover {
            transform: scale(1.02);
        }

        .qr-code-container canvas,
        .qr-code-container img { /* Style for QR code canvas/image */
            width: 200px;
            height: 200px;
            display: block;
        }

        .instructions {
            font-size: 20px;
            line-height: 1.6;
            text-align: center;
            margin: 25px 0;
            color: var(--text-color-dark);
            font-weight: 600;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 15px;
        }

        .instructions .icon {
            font-size: 36px;
            color: var(--primary-blue);
        }

        .download-button {
            margin-top: 30px;
            padding: 14px 30px;
            background-color: var(--primary-blue);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 18px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 12px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(0, 123, 255, 0.3);
        }

        .download-button:hover {
            background-color: var(--dark-blue);
            transform: translateY(-3px);
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

        /* Utility class for hiding elements */
        .hidden-element {
            display: none !important;
        }

        @media (max-width: 600px) {
            body {
                padding: 15px;
            }
            .feedback-container {
                padding: 30px 20px;
            }

            .subtitle-moh {
                font-size: 28px;
            }

            .qr-section {
                padding: 25px 15px;
            }

            .qr-code-container canvas,
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
                <img id="moh-logo" src="argon-dashboard-master/assets/img/loog.jpg" alt="Ministry of Health Logo" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIxMjAiIGhlaWdodD0iMTIwIiB2aWV3Qm94PSIwIDAgMjAwIDIwMCI+PHJlY3Qgd2lkdGg9IjIwMCIgaGVpZ2h0PSIyMDAiIGZpbGw9IiNlZWVlZWUiLz48dGV4dCB4PSIxMDAiIHk9IjEwMCIgZm9udC1mYW1pbHk9IkFyaWFsIiBmb250LXNpemU9IjE2IiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBhbGlnbm1lbnQtYmFzZWxpbmU9Im1pZGRsZSIgZmlsbD0iIzY2NiI+SW1hZ2UgTm90IEZvdW5kPC90ZXh0Pjwvc3ZnP+'">
            </div>
            <div class="title-uganda" id="republic-title-share">THE REPUBLIC OF UGANDA</div>
            <div class="subtitle-moh" id="ministry-subtitle-share">MINISTRY OF HEALTH</div>
        </div>

        <div class="flag-bar" id="flag-bar-share">
            <div class="flag-black" id="flag-black-color-share"></div>
            <div class="flag-yellow" id="flag-yellow-color-share"></div>
            <div class="flag-red" id="flag-red-color-share"></div>
        </div>

        <div class="qr-section">
            <div class="qr-code-container">
                <div id="qr-code"></div>
            </div>

            <div class="instructions" id="qr-instructions-text">
                <i class="fas fa-qrcode icon"></i>
                <span>Scan this QR Code to Give Your Feedback<br>on Services Received</span>
            </div>

            <button class="download-button" onclick="downloadPage()">
                <i class="fas fa-download"></i> Download Feedback Page
            </button>
        </div>

        <div class="footer-note" id="footer-note-text">
            Thank you for helping us improve our services.
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
        // --- Get Survey ID and URL ---
        const urlParams = new URLSearchParams(window.location.search);
        const currentSurveyId = urlParams.get('survey_id'); // THIS IS THE KEY LINE
        const passedSurveyUrl = urlParams.get('url'); // This is the survey_page.php URL for QR code

        // Default QR code URL (important for when settings are loaded but no explicit URL is given yet)
        // Ensure the QR code URL is correctly constructed using the currentSurveyId
        const qrCodeUrl = passedSurveyUrl || `<?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]"; ?>/qpv3/admin/survey_page.php?survey_id=${currentSurveyId}`;


        // --- Element References ---
        const mohLogo = document.getElementById('moh-logo');
        const republicTitleElement = document.getElementById('republic-title-share');
        const ministrySubtitleElement = document.getElementById('ministry-subtitle-share');
        const flagBarElement = document.getElementById('flag-bar-share');
        const flagBlackElement = document.getElementById('flag-black-color-share');
        const flagYellowElement = document.getElementById('flag-yellow-color-share');
        const flagRedElement = document.getElementById('flag-red-color-share');
        const qrInstructionsElement = document.getElementById('qr-instructions-text');
        const qrInstructionsSpan = qrInstructionsElement.querySelector('span'); // The text within the instructions
        const footerNoteElement = document.getElementById('footer-note-text');

        document.addEventListener('DOMContentLoaded', function() {
            // --- Generate QR code ---
            if (qrCodeUrl && document.getElementById('qr-code')) {
                new QRCode(document.getElementById('qr-code'), {
                    text: qrCodeUrl,
                    width: 200,
                    height: 200,
                    colorDark: "#000000",
                    colorLight: "#ffffff",
                    correctLevel: QRCode.CorrectLevel.H
                });
            } else {
                document.getElementById('qr-code').innerHTML = '<p style="color:red; text-align:center;">Error: QR code URL missing.</p>';
            }

            // --- Apply saved settings from localStorage ---
            if (currentSurveyId) {
                // Construct the exact localStorage key used by preview_form.php
                const localStorageKey = 'surveyPreviewSettings_' + currentSurveyId;
                const settings = JSON.parse(localStorage.getItem(localStorageKey)) || {};

                // Debugging: Log loaded settings
                console.log("Loaded settings for survey ID", currentSurveyId, ":", settings);

                // Apply Logo URL and visibility (controlled by 'showLogoUrl' and 'logoUrl')
                if (settings.hasOwnProperty('showLogoUrl') && !settings.showLogoUrl) {
                    mohLogo.classList.add('hidden-element');
                } else {
                    mohLogo.classList.remove('hidden-element');
                    // Only update src if a logoUrl is saved, otherwise keep the default HTML src
                    if (settings.logoUrl) {
                        mohLogo.src = settings.logoUrl;
                    }
                }

                // Apply Republic Title (controlled by 'showRepublicTitleShare' and 'republicTitleText')
                if (settings.hasOwnProperty('showRepublicTitleShare') && !settings.showRepublicTitleShare) {
                    republicTitleElement.classList.add('hidden-element');
                } else {
                    republicTitleElement.classList.remove('hidden-element');
                    republicTitleElement.textContent = settings.republicTitleText || 'THE REPUBLIC OF UGANDA';
                }

                // Apply Ministry Subtitle (controlled by 'showMinistrySubtitleShare' and 'ministrySubtitleText')
                if (settings.hasOwnProperty('showMinistrySubtitleShare') && !settings.showMinistrySubtitleShare) {
                    ministrySubtitleElement.classList.add('hidden-element');
                } else {
                    ministrySubtitleElement.classList.remove('hidden-element');
                    ministrySubtitleElement.textContent = settings.ministrySubtitleText || 'MINISTRY OF HEALTH';
                }

                // Apply Flag Bar (controlled by 'showFlagBar', 'flagBlackColor', 'flagYellowColor', 'flagRedColor')
                if (settings.hasOwnProperty('showFlagBar') && !settings.showFlagBar) {
                    flagBarElement.classList.add('hidden-element');
                } else {
                    flagBarElement.classList.remove('hidden-element');
                    flagBlackElement.style.backgroundColor = settings.flagBlackColor || 'var(--uganda-black)';
                    flagYellowElement.style.backgroundColor = settings.flagYellowColor || 'var(--uganda-yellow)';
                    flagRedElement.style.backgroundColor = settings.flagRedColor || 'var(--uganda-red)';
                }

                // Apply QR Instructions Text and visibility (controlled by 'showQrInstructionsShare' and 'qrInstructionsText')
                if (settings.hasOwnProperty('showQrInstructionsShare') && !settings.showQrInstructionsShare) {
                    qrInstructionsElement.classList.add('hidden-element');
                } else {
                    qrInstructionsElement.classList.remove('hidden-element');
                    qrInstructionsSpan.textContent = settings.qrInstructionsText || 'Scan this QR Code to Give Your Feedback\non Services Received';
                }

                // Apply Footer Note Text and visibility (controlled by 'showFooterNoteShare' and 'footerNoteText')
                if (settings.hasOwnProperty('showFooterNoteShare') && !settings.showFooterNoteShare) {
                    footerNoteElement.classList.add('hidden-element');
                } else {
                    footerNoteElement.classList.remove('hidden-element');
                    footerNoteElement.textContent = settings.footerNoteText || 'Thank you for helping us improve our services.';
                }

                // Update document title if 'titleText' from preview is saved
                if (settings.titleText) {
                    document.title = settings.titleText;
                }
            } else {
                console.warn("Survey ID not found in URL. Cannot load localStorage settings.");
            }
        });

        // --- Download Page Function ---
        function downloadPage() {
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

            // Generate the current QR code image data URL from the canvas
            const qrCodeCanvas = document.querySelector('#qr-code canvas');
            const qrCodeImgSrc = qrCodeCanvas ? qrCodeCanvas.toDataURL('image/png') : '';

            // Clone the container to remove the download button for the downloaded HTML
            const containerToDownload = document.querySelector('.feedback-container').cloneNode(true);
            const downloadBtn = containerToDownload.querySelector('.download-button');
            if (downloadBtn) {
                downloadBtn.remove(); // Remove the button from the cloned content
            }

            // Replace the QR code div with a static image for the downloaded file
            const qrCodeDivInCloned = containerToDownload.querySelector('#qr-code');
            if (qrCodeDivInCloned && qrCodeImgSrc) {
                qrCodeDivInCloned.innerHTML = `<img src="${qrCodeImgSrc}" alt="QR Code" style="width:200px; height:200px; display:block;">`;
            }

            const htmlContent = `<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>${document.title}</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        ${inlineCss}
    </style>
</head>
<body>
    ${containerToDownload.outerHTML}
</body>
</html>`;

            const blob = new Blob([htmlContent], { type: 'text/html' });
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = 'ministry-of-health-feedback-share.html';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(a.href);
        }
    </script>
</body>
</html>