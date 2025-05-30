<?php
session_start();

// Database connection using mysqli
$servername = "localhost";
$username = "root";
$password = "root";
$dbname = "fbtv3";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get survey_id from the URL
$surveyId = $_GET['survey_id'] ?? null;

if (!$surveyId) {
    die("Survey ID is missing.");
}

// Fetch survey details
$surveyStmt = $conn->prepare("SELECT * FROM survey WHERE id = ?");
$surveyStmt->bind_param("i", $surveyId);
$surveyStmt->execute();
$surveyResult = $surveyStmt->get_result();
$survey = $surveyResult->fetch_assoc();

if (!$survey) {
    die("Survey not found.");
}

// Fetch questions and options for the selected survey, ordered by position
$questions = $conn->query("
    SELECT q.id, q.label, q.question_type, q.is_required, q.translations, q.option_set_id, sq.position
    FROM question q
    JOIN survey_question sq ON q.id = sq.question_id
    WHERE sq.survey_id = $surveyId 
    ORDER BY sq.position ASC
");

$questionsArray = [];
while ($question = $questions->fetch_assoc()) {
    $question['options'] = [];
    
    // Fetch options for the question with original order
    if ($question['option_set_id']) {
        $options = $conn->query("
            SELECT * FROM option_set_values 
            WHERE option_set_id = " . $conn->real_escape_string($question['option_set_id']) . " 
            ORDER BY id ASC
        ");
        
        if ($options) {
            while ($option = $options->fetch_assoc()) {
                $question['options'][] = $option;
            }
        }
    }
    $questionsArray[] = $question;
}

// Fetch translations for the selected language
$language = isset($_GET['language']) ? $_GET['language'] : 'en'; // Default to English
$translations = [];
$query = "SELECT key_name, translations FROM default_text";
$translations_result = $conn->query($query);
while ($row = $translations_result->fetch_assoc()) {
    $decoded_translations = json_decode($row['translations'], true);
    $translations[$row['key_name']] = $decoded_translations[$language] ?? $row['key_name'];
}

// Fetch translations for questions and options
foreach ($questionsArray as &$question) {
    $questionTranslations = $question['translations'] ? json_decode($question['translations'], true) : [];
    $question['label'] = $questionTranslations[$language] ?? $question['label'];

    foreach ($question['options'] as &$option) {
        $optionTranslations = $option['translations'] ? json_decode($option['translations'], true) : [];
        $option['option_value'] = $optionTranslations[$language] ?? $option['option_value'];
    }
}

unset($question);
unset($option);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($translations['Ministry of Health Client Satisfaction Feedback Tool'] ?? 'Feedback Web Form'); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../styles.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f8f9fa;
            margin: 0;
            padding: 0;
        }
        .top-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            background: #fff;
            border-bottom: 1px solid #ddd;
        }
        .top-controls button {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 16px;
        }
        .top-controls button:hover {
            color: #007bff;
        }
        .question-number {
            font-weight: bold;
            margin-right: 8px;
        }
        .logo-container {
            text-align: center; /* Center the logo */
            margin-bottom: 20px; /* Add some space below the logo */
        }
        .logo-container img {
            max-width: 100%; /* Ensure the logo is responsive */
            height: 170px; /* Maintain aspect ratio */
        }
    </style>
</head>
<body>
    <!-- Top Controls -->
    <div class="top-controls">
        <!-- Back Button -->
        <button onclick="window.location.href='update_form?survey_id=<?php echo $surveyId; ?>'" class="control-btn">
            <i class="fas fa-arrow-left"></i> Back
        </button>
        <!-- Share Button -->
        <button onclick="window.location.href='share_page?url=' + encodeURIComponent(window.location.origin + '/qpv3/admin/survey_page.php?survey_id=<?php echo $surveyId; ?>')" class="control-btn">
            <i class="fas fa-share"></i> Share
        </button>
        <button onclick="window.location.href='survey_page.php?survey_id=<?php echo $surveyId; ?>'">
            <i class="fas fa-rocket"></i> Generate
        </button>
    </div>

    <!-- Survey Preview -->
    <div class="container" id="form-content">
     
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
     

     <h2 data-translate="title"><?php echo $translations['Ministry of Health Client Satisfaction Feedback Tool'] ?? 'Ministry of Health Client Satisfaction Feedback Tool'; ?></h2>
     <h3 data-translate="client_satisfaction_tool"><?php echo $translations['client_satisfaction_tool'] ?? 'CLIENT SATISFACTION FEEDBACK TOOL'; ?></h3>
     <p class="subheading" data-translate="subheading">
         <?php echo $translations['subheading'] ?? 'This tool is used to obtain clients\' feedback about their experience with the services and promote quality improvement, accountability, and transparency within the healthcare system.'; ?>
     </p>

         
     <!-- <legend><?php echo $translations['location_details'] ?? 'Location Details'; ?></legend> -->
    
     <div class="facility-section">
    <div class="form-group">
        <label for="facility-search">Health Facility:</label>
        <div class="searchable-dropdown">
            <input type="text" id="facility-search" placeholder="Type to search facilities..." autocomplete="off" required>
            <div class="dropdown-results" id="facility-results"></div>
            <input type="hidden" id="facility_id" name="facility_id">
        </div>
    </div>
    
    <!-- Hierarchy path display -->
    <div class="hierarchy-path" id="hierarchy-path">
        <div class="path-display" id="path-display"></div>
    </div>
    
    <!-- Hidden inputs for form submission -->
    <input type="hidden" id="hierarchy_data" name="hierarchy_data">
</div>


                        <?php if ($survey['type'] === 'local'): ?>
                            <div class="location-row">

                                <!-- Service Unit -->
                                <div class="form-group">
                                    <label for="serviceUnit" data-translate="service_unit"><?php echo $translations['service_unit'] ?? 'Service Unit'; ?>:</label>
                                    <select id="serviceUnit" name="serviceUnit" required>
                                        <option value="">none selected</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="sex" data-translate="sex"><?php echo $translations['sex'] ?? 'Sex'; ?>:</label>
                                    <select id="sex" name="sex">
                                        <option value="" disabled selected>none selected</option>
                                        <option value="Male" data-translate="male"><?php echo $translations['male'] ?? 'Male'; ?></option>
                                        <option value="Female" data-translate="female"><?php echo $translations['female'] ?? 'Female'; ?></option>
                                    </select>
                                </div>
                            </div>

                            <div class="location-row">
                                <div class="reporting-period-container">
                                    <label for="reporting_period" data-translate="reporting_period"><?php echo $translations['reporting_period'] ?? 'Reporting Period'; ?></label>
                                    <input 
                                        type="date" 
                                        id="reporting_period" 
                                        name="reporting_period" 
                                        placeholder="Select Reporting Period"
                                        required
                                        min="2010-01-01"
                                        max="2030-12-31"
                                    >
                                    <span class="placeholder-text">Click to select reporting period</span>
                                </div>

                                <div class="form-group">
                                    <label for="age" data-translate="age"><?php echo $translations['age'] ?? 'Age'; ?>:</label>
                                    <input type="number" id="age" name="age" min="10" max="99">
                                </div>
                            </div>

                            <!-- Ownership -->
                            <div class="radio-group">
                                <label for="ownership" class="radio-label" data-translate="ownership"><?php echo $translations['ownership'] ?? 'Ownership'; ?></label>
                                <div class="radio-options" id="ownership-options">
                                    <!-- Radio buttons will be populated here -->
                                </div>
                            </div>

 <p data-translate="rating_instruction"><?php echo $translations['rating_instruction'] ?? '1. Please rate each of the following parameters according to your experience today on a scale of 1 to 4.'; ?></p>
                        <p data-translate="rating_scale" style="color: red; font-size: 12px; font-style: italic;"><?php echo $translations['rating_scale'] ?? 'where \'0\' means Poor, \'1\' Fair, \'2\' Good and \'3\' Excellent'; ?></p>

                            
                        <?php endif; ?>

                      



        <!-- Dynamically Generate Questions -->
        <?php foreach ($questionsArray as $index => $question): ?>
            <div class="form-group">
                <div class="radio-label">
                    <!-- Add numbering here -->
                    <span class="question-number"><?php echo ($index + 1) . '.'; ?></span>
                    <?php echo htmlspecialchars($question['label']); ?>
                </div>
                
                <?php if ($question['question_type'] == 'radio'): ?>
                    <div class="radio-options">
                        <?php foreach ($question['options'] as $option): ?>
                            <div class="radio-option">
                                <input type="radio" 
                                       id="option_<?php echo $question['id']; ?>_<?php echo $option['id']; ?>"
                                       name="question_<?php echo $question['id']; ?>" 
                                       value="<?php echo htmlspecialchars($option['option_value']); ?>">
                                <label for="option_<?php echo $question['id']; ?>_<?php echo $option['id']; ?>">
                                    <?php echo htmlspecialchars($option['option_value']); ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php elseif ($question['question_type'] == 'checkbox'): ?>
                    <div class="radio-options">
                        <?php foreach ($question['options'] as $option): ?>
                            <div class="radio-option">
                                <input type="checkbox" 
                                       id="option_<?php echo $question['id']; ?>_<?php echo $option['id']; ?>"
                                       name="question_<?php echo $question['id']; ?>[]" 
                                       value="<?php echo htmlspecialchars($option['option_value']); ?>">
                                <label for="option_<?php echo $question['id']; ?>_<?php echo $option['id']; ?>">
                                    <?php echo htmlspecialchars($option['option_value']); ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php elseif ($question['question_type'] == 'select'): ?>
                    <select class="form-control" name="question_<?php echo $question['id']; ?>">
                        <option value="">Select an option</option>
                        <?php foreach ($question['options'] as $option): ?>
                            <option value="<?php echo htmlspecialchars($option['option_value']); ?>">
                                <?php echo htmlspecialchars($option['option_value']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php elseif ($question['question_type'] == 'text'): ?>
                    <input type="text" 
                           class="form-control" 
                           name="question_<?php echo $question['id']; ?>">
                <?php elseif ($question['question_type'] == 'textarea'): ?>
                    <textarea class="form-control" 
                              name="question_<?php echo $question['id']; ?>"
                              rows="3"></textarea>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <!-- Submit Button -->
        <button type="submit"><?php echo $translations['submit'] ?? 'Submit'; ?></button>
    </div>

    <script>
        // Share Button Functionality
        document.getElementById('share-btn').addEventListener('click', function() {
            const surveyUrl = window.location.href;
            window.location.href = `share_page.php?url=${encodeURIComponent(surveyUrl)}`;
        });
    </script>

<script defer src="survey_page.js"></script>
<script defer src="translations.js"></script>
</body>
</html>
