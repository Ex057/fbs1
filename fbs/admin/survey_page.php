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
    <link rel="stylesheet" href="../styles.css">
    <style>
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
    <div class="top-controls">
        <div class="language-switcher">
            <label for="language" data-translate="select_language">
                <?php echo $translations['select_language'] ?? 'Select Language'; ?>
            </label>
            <select id="languageSelect" onchange="changeLanguage()">
                <?php
                $languages = [
                    'en' => 'English',
                    'lg' => 'Luganda',
                    'rn' => 'Runyakole',
                    'rk' => 'Rukiga',
                    'ac' => 'Acholi',
                    'at' => 'Ateso',
                    'ls' => 'Lusoga'
                ];
                foreach ($languages as $code => $name): ?>
                    <option value="<?php echo htmlspecialchars($code); ?>" 
                            <?php echo ($code == $language) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="print-button">
            <button id="print-button" onclick="printForm()">
                <img src="../print.jpg" alt="Print">
            </button>
        </div>
    </div>

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

  
        <form action="survey_page_submit.php" method="POST" onsubmit="return validateForm()">
        <input type="hidden" name="survey_id" value="<?php echo htmlspecialchars($surveyId); ?>">
        <input type="hidden" name="submission_language" value="en">
           
    <!-- <legend><?php echo $translations['location_details'] ?? 'Location Details'; ?></legend> -->
  <!-- In your survey_page.php -->
<div class="facility-section">
    <div class="form-group">
        <label for="facility-search">Health Facility:</label>
        <div class="searchable-dropdown">
            <input type="text" id="facility-search" placeholder="Type to search facilities..." autocomplete="off" required>
            <div  class="dropdown-results" id="facility-results"></div>
            <input type="hidden" id="facility_id" name="facility_id">
        </div>
    </div>
    
    <div class="hierarchy-path" id="hierarchy-path">
        <div class="path-display" id="path-display"></div>
    </div>
    
    <input type="hidden" id="hierarchy_data" name="hierarchy_data">
</div>

<!-- Ensure this exists for the original select -->
<select id="facility" name="facility" style="display: none;">
    <option value="">None Selected</option>
</select>

<!-- Remove the original location dropdowns -->



                    <?php
                    // Fetch the survey type for the current survey
                    $surveyType = 'local';
                    $typeResult = $conn->query("SELECT type FROM survey WHERE id = " . intval($surveyId));
                    if ($typeResult && $typeRow = $typeResult->fetch_assoc()) {
                        $surveyType = $typeRow['type'];
                    }
                    ?>

                    <?php if ($surveyType === 'local'): ?>
                                          
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
                                <label for="reporting_period" data-translate="reporting_period"><?php echo $translations['reporting_period'] ?? 'Present Day'; ?></label>
                                <input 
                                    type="date" 
                                    id="reporting_period" 
                                    name="reporting_period" 
                                    required
                                    value="<?php echo date('Y-m-d'); ?>"
                                    readonly
                                    style="background-color: #f5f5f5; cursor: not-allowed;"
                                >
                                <span class="placeholder-text">Current date is automatically selected</span>
                            </div>
                        </div>

                        <div class="form-group" style="width: 400px; padding: 0px;">
                            <label for="age" data-translate="age"><?php echo $translations['age'] ?? 'Age'; ?>:</label>
                            <input 
                                type="number" 
                                id="age" 
                                name="age" 
                                min="14" 
                                max="99" 
                                onblur="this.value = Math.max(14, Math.min(99, parseInt(this.value) || ''))"
                                required
                                oninvalid="this.setCustomValidity('Please enter an age between 14 and 99')"
                                oninput="this.setCustomValidity('')"
                            >
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
                        <select class="form-control"   name="question_<?php echo $question['id']; ?>" style="width: 60%;">
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
                                  rows="3"
                                  style="width: 80%;"></textarea>
                                  
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <button type="submit" data-translate="submit"><?php echo $translations['submit'] ?? 'Submit'; ?></button>
        </form>
    </div>

    <script>
        function changeLanguage() {
            var selectedLang = document.getElementById('languageSelect').value;
            window.location.href = "survey_page.php?survey_id=<?php echo $surveyId; ?>&language=" + selectedLang;
        }
    </script>
    
   
    <script defer src="./survey_page.js"></script>
    
    <script defer src="../translations.js"></script>
</body>
</html>