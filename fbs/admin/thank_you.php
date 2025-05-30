<?php
session_start();

// Check if the user has submitted the form
if (!isset($_SESSION['submitted_uid'])) {
    // Redirect to the form page if no submission is found
    header("Location: survey_page.php");
    exit;
}

// Get the submission UID
$uid = $_SESSION['submitted_uid'];

// Database connection
$servername = "localhost";
$username = "root";
$password = "root";
$dbname = "fbtv3";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get basic submission data
$stmt = $conn->prepare("
    SELECT * FROM submission WHERE uid = ?
");
$stmt->bind_param("s", $uid);
$stmt->execute();
$result = $stmt->get_result();
$submission = $result->fetch_assoc();

// If no submission found with this UID
if (!$submission) {
    die("Submission not found.");
}

// Get facility name
$facilityId = $submission['location_id'];
$facilityQuery = $conn->query("SELECT name FROM location WHERE id = $facilityId");
$facility = $facilityQuery->fetch_assoc();

// Check survey type before fetching service unit and ownership
$surveyId = $submission['survey_id'] ?? null;
$surveyType = 'local';

if ($surveyId) {
    $surveyTypeQuery = $conn->query("SELECT type FROM survey WHERE id = $surveyId");
    $surveyTypeRow = $surveyTypeQuery->fetch_assoc();
    if ($surveyTypeRow && isset($surveyTypeRow['type'])) {
        $surveyType = $surveyTypeRow['type'];
    }
}

$serviceUnit = null;
$ownership = null;

if ($surveyType === 'local') {
    // Get service unit name
    $serviceUnitId = $submission['service_unit_id'];
    $serviceUnitQuery = $conn->query("SELECT name FROM service_unit WHERE id = $serviceUnitId");
    $serviceUnit = $serviceUnitQuery->fetch_assoc();

    // Get ownership name
    $ownershipId = $submission['ownership_id'];
    $ownershipQuery = $conn->query("SELECT name FROM owner WHERE id = $ownershipId");
    $ownership = $ownershipQuery->fetch_assoc();
}

// Get responses
$submissionId = $submission['id'];
$responsesQuery = $conn->query("
    SELECT sr.question_id, sr.response_value, q.label 
    FROM submission_response sr
    JOIN question q ON sr.question_id = q.id
    WHERE sr.submission_id = $submissionId
");

$responses = [];
while ($row = $responsesQuery->fetch_assoc()) {
    $responses[] = $row;
}

// Close database connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thank You for Your Feedback</title>
    <link rel="stylesheet" href="../styles.css">
    <style>
        /* Thank you container styling */
        .thank-you-container {
            max-width: 800px;
            margin: 40px auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header-section {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .logo-container {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .logo-container img {
            max-width: 100%;
            height: 170px;
        }
        
        .title {
            font-size: 20px;
            font-weight: bold;
            margin-top: 10px;
        }
        
        .subtitle {
            font-size: 18px;
            margin-top: 5px;
        }
        
        .flag-bar {
            display: flex;
            height: 10px;
            width: 100%;
            margin: 15px 0;
        }
        
        .flag-black {
            background-color: black;
            flex: 1;
        }
        
        .flag-yellow {
            background-color: #ffce00;
            flex: 1;
        }
        
        .flag-red {
            background-color: red;
            flex: 1;
        }
        
        h2 {
            color: #006400;
            text-align: center;
            margin: 20px 0;
        }
        
        .success-message {
            background-color: #dff0d8;
            color: #3c763d;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .reference-id {
            background-color: #f7f7f9;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
            border: 1px dashed #ccc;
        }
        
        .reference-id span {
            font-weight: bold;
            font-family: monospace;
            font-size: 16px;
        }
        
        .action-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin: 20px 0;
        }
        
        .action-button {
            padding: 10px 20px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        
        .action-button.secondary {
            background-color: #6c757d;
        }
        
        .action-button:hover {
            opacity: 0.9;
        }
        
        /* Submission details styling */
        .submission-details {
            display: none;
            margin-top: 30px;
            border-top: 1px solid #ddd;
            padding-top: 20px;
        }
        
        .details-section {
            margin-bottom: 20px;
        }
        
        .details-section h3 {
            color: #006400;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        th {
            background-color: #f2f2f2;
            font-weight: 600;
            width: 40%;
        }
        
        @media print {
            .action-buttons, .print-hidden {
                display: none !important;
            }
            
            .submission-details {
                display: block !important;
            }
            
            body {
                font-size: 12pt;
            }
            
            .thank-you-container {
                box-shadow: none;
                margin: 0;
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <div class="thank-you-container" id="printableArea">
        <!-- Header Section (similar to survey_page.php) -->
        <div class="header-section">
            <div class="logo-container">
                <img src="asets/asets/img/loog.jpg" alt="Ministry of Health Logo">
            </div>
            <div class="title">THE REPUBLIC OF UGANDA</div>
            <div class="subtitle">MINISTRY OF HEALTH</div>
        </div>
        
        <div class="flag-bar">
            <div class="flag-black"></div>
            <div class="flag-yellow"></div>
            <div class="flag-red"></div>
        </div>
        
        <h2>CLIENT SATISFACTION FEEDBACK TOOL</h2>
        
        <div class="success-message">
            Thank you for taking the time to provide your valuable feedback! Your insights will help us improve healthcare services.
        </div>
        
        <div class="reference-id">
            Your Reference ID: <span><?php echo htmlspecialchars($uid); ?></span>
        </div>
        
        <div class="action-buttons print-hidden">
            <button class="action-button" id="viewDetailsBtn">View Your Responses</button>
            <button class="action-button" onclick="printSummary()">Download/Print Summary</button>
            
        </div>
        
        <!-- Submission Details Section (Hidden by Default) -->
        <div class="submission-details" id="submissionDetails">
            <div class="details-section">
                <h3>Respondent Information</h3>
                <table>
                    <tr>
                        <th>Reference ID</th>
                        <td><?php echo htmlspecialchars($uid); ?></td>
                    </tr>
                    <tr>
                        <th>Facility</th>
                        <td><?php echo htmlspecialchars($facility['name'] ?? 'Not specified'); ?></td>
                    </tr>
                    <tr>
                        <th>Service Unit</th>
                        <td><?php echo htmlspecialchars($serviceUnit['name'] ?? 'Not specified'); ?></td>
                    </tr>
                    <tr>
                        <th>Age</th>
                        <td><?php echo htmlspecialchars($submission['age'] ?? 'Not specified'); ?></td>
                    </tr>
                    <tr>
                        <th>Sex</th>
                        <td><?php echo htmlspecialchars($submission['sex'] ?? 'Not specified'); ?></td>
                    </tr>
                    <tr>
                        <th>Date</th>
                        <td><?php echo htmlspecialchars(date('d/m/Y', strtotime($submission['period'] ?? 'now'))); ?></td>
                    </tr>
                    <tr>
                        <th>Ownership</th>
                        <td><?php echo htmlspecialchars($ownership['name'] ?? 'Not specified'); ?></td>
                    </tr>
                </table>
            </div>
            
            <div class="details-section">
                <h3>Your Responses</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Question</th>
                            <th>Your Response</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($responses) > 0): ?>
                            <?php foreach ($responses as $response): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($response['label']); ?></td>
                                    <td><?php echo htmlspecialchars($response['response_value']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="2" style="text-align: center;">No responses recorded</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <script>
        // Toggle submission details
        document.getElementById('viewDetailsBtn').addEventListener('click', function() {
            var details = document.getElementById('submissionDetails');
            if (details.style.display === 'block') {
                details.style.display = 'none';
                this.textContent = 'View Your Responses';
            } else {
                details.style.display = 'block';
                this.textContent = 'Hide Your Responses';
            }
        });
        
        // Print summary function
        function printSummary() {
            // Make sure details are visible before printing
            document.getElementById('submissionDetails').style.display = 'block';
            window.print();
        }
    </script>
</body>
</html>