<?php
session_start();
require_once 'dhis2/dhis2_submit.php'; 

// Database connection
$servername = "localhost";
$username = "root";
$password = "root";
$dbname = "fbtv3";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Function to sanitize input data
function sanitize($conn, $data) {
    return $conn->real_escape_string(trim($data));
}

// Function to generate a unique identifier (UID)
function generateUID() {
    return strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 10));
}

// Check if form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get survey ID
    $surveyId = isset($_POST['survey_id']) ? intval($_POST['survey_id']) : null;
    if (!$surveyId) {
        die("Survey ID is missing.");
    }

    // Get the submission language (will always be "en" based on our hidden field)
    $submissionLanguage = isset($_POST['submission_language']) ? sanitize($conn, $_POST['submission_language']) : 'en';

    // Generate a unique identifier (UID)
    $uid = generateUID();

    // --- Fetch survey type ---
    $surveyType = 'local'; // Default to 'local'
    $stmt = $conn->prepare("SELECT type FROM survey WHERE id = ?");
    $stmt->bind_param("i", $surveyId);
    $stmt->execute();
    $stmt->bind_result($fetchedType);
    if ($stmt->fetch()) {
        $surveyType = $fetchedType;
    }
    $stmt->close();
    // --- End fetch survey type ---

    // Initialize variables based on survey type
    $age = null;
    $sex = null;
    $reportingPeriod = null;
    $serviceUnitId = null;
    $ownershipId = null;

    if ($surveyType === 'local') {
        $age = isset($_POST['age']) ? intval($_POST['age']) : null;
        $sex = isset($_POST['sex']) ? sanitize($conn, $_POST['sex']) : null;
        $reportingPeriod = isset($_POST['reporting_period']) ? sanitize($conn, $_POST['reporting_period']) : null;
        $serviceUnitId = isset($_POST['serviceUnit']) ? intval($_POST['serviceUnit']) : null;
        $ownershipId = isset($_POST['ownership']) ? intval($_POST['ownership']) : null;
    }
    
    $locationId = isset($_POST['facility_id']) ? intval($_POST['facility_id']) : null;

    // Begin transaction
    $conn->begin_transaction();

    try {
        // Insert into submission table
        $insertSubmission = $conn->prepare("
            INSERT INTO submission (
                uid, 
                age, 
                sex, 
                period, 
                service_unit_id, 
                location_id, 
                ownership_id,
                survey_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $insertSubmission->bind_param(
            "sissiiii", 
            $uid, 
            $age, 
            $sex, 
            $reportingPeriod, 
            $serviceUnitId, 
            $locationId, 
            $ownershipId,
            $surveyId
        );

        $insertSubmission->execute();

        // Get the submission ID
        $submissionId = $conn->insert_id;

        // Get questions for this survey
        $questions = $conn->query("
            SELECT q.id, q.question_type 
            FROM question q
            JOIN survey_question sq ON q.id = sq.question_id
            WHERE sq.survey_id = $surveyId
        ");

        // Prepare statement for inserting responses
        $insertResponse = $conn->prepare("
            INSERT INTO submission_response (
                submission_id, 
                question_id, 
                response_value
            ) VALUES (?, ?, ?)
        ");

        // Process each question response
        while ($question = $questions->fetch_assoc()) {
            $questionId = $question['id'];
            $questionType = $question['question_type'];
            $responseKey = "question_" . $questionId;

            // Handle different question types
            if ($questionType == 'checkbox' && isset($_POST[$responseKey]) && is_array($_POST[$responseKey])) {
                // For checkboxes, we might have multiple values
                foreach ($_POST[$responseKey] as $value) {
                    $sanitizedValue = sanitize($conn, $value);
                    $insertResponse->bind_param("iis", $submissionId, $questionId, $sanitizedValue);
                    $insertResponse->execute();
                }
            } elseif (isset($_POST[$responseKey])) {
                // For other question types
                $responseValue = is_array($_POST[$responseKey]) ? 
                                 implode(", ", array_map(function($item) use ($conn) { 
                                     return sanitize($conn, $item); 
                                 }, $_POST[$responseKey])) : 
                                 sanitize($conn, $_POST[$responseKey]);

                $insertResponse->bind_param("iis", $submissionId, $questionId, $responseValue);
                $insertResponse->execute();
            }
        }

        // Commit transaction
        $conn->commit();

        // Store UID in session to prevent resubmission
        $_SESSION['submitted_uid'] = $uid;

        // In your survey_page_submit.php:
        $dhis2Submitter = new DHIS2SubmissionHandler($conn);
        $result = $dhis2Submitter->processSubmission($submissionId);

        if (!$result['success']) {
            // Handle error (log it, notify admin, etc.)
            error_log("Final submission error: " . $result['message']);
        } else {
            // Success - even if it was a duplicate
            // $result['message'] contains details
        }

        // Redirect to thank you page
        header("Location: thank_you.php?uid=$uid");
        exit;
    } catch (Exception $e) {
        // Roll back transaction on error
        $conn->rollback();
        echo "Error: " . $e->getMessage();
    }

    // Close prepared statements
    if (isset($insertSubmission)) $insertSubmission->close();
    if (isset($insertResponse)) $insertResponse->close();
} else {
    // If not a POST request, redirect to the survey page
    header("Location: survey_page.php");
    exit;
}

// Close database connection
$conn->close();
?>