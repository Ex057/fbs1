<?php
session_start();

// Enhanced error handling for database connection
try {
    // Database connection using mysqli with error handling
    $servername = "localhost";
    $username = "root";
    $password = "root";
    $dbname = "fbtv3";

    $conn = new mysqli($servername, $username, $password, $dbname);

    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
} catch (Exception $e) {
    // Log error (in production, use a proper logging mechanism)
    error_log($e->getMessage());
    die("A system error occurred. Please try again lat.");
}

// Input validation function
function validateLanguage($language) {
    $validLanguages = ['en', 'lg', 'rn', 'rk', 'ac', 'at', 'ls'];
    return in_array($language, $validLanguages) ? $language : 'en';
}

// Fetch and validate language
$language = isset($_GET['language']) 
    ? validateLanguage($_GET['language']) 
    : 'en';

// Enhanced questions and options fetching
try {
    // Check if deployed questions are available in the session
    if (isset($_SESSION['deployed_questions'])) {
        $questionsArray = $_SESSION['deployed_questions'];
        unset($_SESSION['deployed_questions']); // Clear the session data after use
    } else {
        // Fetch all questions and their options from the database with error handling
        $questions = $conn->query("SELECT * FROM question ORDER BY created ASC");
        if (!$questions) {
            throw new Exception("Failed to fetch questions: " . $conn->error);
        }

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
    }
} catch (Exception $e) {
    error_log("Questions Fetch Error: " . $e->getMessage());
    $questionsArray = []; // Fallback to empty array
}

// Enhanced translations fetching
try {
    $translations = [];
    $query = "SELECT key_name, translations FROM default_text";
    $translations_result = $conn->query($query);
    
    if (!$translations_result) {
        throw new Exception("Failed to fetch translations: " . $conn->error);
    }

    while ($row = $translations_result->fetch_assoc()) {
        $decoded_translations = json_decode($row['translations'], true);
        $translations[$row['key_name']] = 
            $decoded_translations[$language] ?? 
            $row['key_name'];
    }
} catch (Exception $e) {
    error_log("Translations Fetch Error: " . $e->getMessage());
    $translations = []; // Fallback to key names
}

// Enhanced translations for questions and options
foreach ($questionsArray as &$question) {
    // Safely handle question translations
    $questionTranslations = !empty($question['translations']) 
        ? json_decode($question['translations'], true) 
        : [];
    
    $question['label'] = $questionTranslations[$language] ?? $question['label'];

    // Safely handle option translations
    foreach ($question['options'] as &$option) {
        $optionTranslations = !empty($option['translations']) 
            ? json_decode($option['translations'], true) 
            : [];
        
        $option['option_value'] = $optionTranslations[$language] ?? $option['option_value'];
    }
}


unset($question);
unset($option);

// Predefined list of languages with enhanced structure
$languages = [
    'en' => 'English',
    'lg' => 'Luganda',
    'rn' => 'Runyakole',
    'rk' => 'Rukiga',
    'ac' => 'Acholi',
    'at' => 'Ateso',
    'ls' => 'Lusoga'
];
?>

<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($language); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($translations['Ministry of Health Client Satisfaction Feedback Tool'] ?? 'Feedback Web Form'); ?></title>
    <link rel="stylesheet" href="styles.css">
    <!-- Add security headers -->
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self' 'unsafe-inline';">
    <!-- Include Select2 CSS and JS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

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
        /* Customize Select2 dropdown */
.select2-container--default .select2-selection--single {
    height: 38px;
    padding: 6px 12px;
    border: 1px solid #ccc;
    border-radius: 4px;
}

.select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 36px;
}

.select2-container--default .select2-search--dropdown .select2-search__field {
    border: 1px solid #ccc;
    border-radius: 4px;
}
            
    </style>
</head>
<body>
    <div class="top-controls">
        <div class="language-switcher">
            <label for="language" data-translate="select_language">
                <?php echo htmlspecialchars($translations['select_language'] ?? 'Select Language'); ?>
            </label>
            <select id="languageSelect" onchange="changeLanguage()">
                <?php foreach ($languages as $code => $name): ?>
                    <option value="<?php echo htmlspecialchars($code); ?>" 
                            <?php echo ($code == $language) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="print-button">
            <button id="print-button" onclick="printForm()">
                <img src="print.jpg" alt="Print" loading="lazy">
            </button>
        </div>
    </div>

     <div class="container" id="form-content">
     
        <div class="header-section">
            <div class="logo-container">
            <img src="admin/asets/asets/img/loog.jpg" alt="Ministry of Health Logo">
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

        <form action="submit.php" method="POST" onsubmit="return validateForm()" novalidate>

       
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

    <div class="form-group" >
    <label for="age" data-translate="age"><?php echo $translations['age'] ?? 'Age'; ?>:</label>
    <input type="number" id="age" name="age" min="10" max= "99">
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






            
           <!-- Dynamically Generate Questions -->
           <?php foreach ($questionsArray as $question): ?>

                

<div class="form-group">
    <div class="radio-label">
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
        <textarea  class="form-control" 
                  name="question_<?php echo $question['id']; ?>"
                  rows="3"></textarea>
    <?php endif; ?>
</div>
<?php endforeach; ?>



<button type="submit" data-translate="submit"><?php echo $translations['submit'] ?? 'Submit'; ?></button>
</form>
    </div>

    <script>
        function changeLanguage() {
            var selectedLang = document.getElementById('languageSelect').value;
            // Add basic validation
            const validLanguages = ['en', 'lg', 'rn', 'rk', 'ac', 'at', 'ls'];
            if (validLanguages.includes(selectedLang)) {
                window.location.href = "?language=" + encodeURIComponent(selectedLang);
            }
        }

        function validateForm() {
            let isValid = true;
            const requiredQuestions = document.querySelectorAll('.form-group input[required], .form-group select[required]');
            
            requiredQuestions.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.classList.add('error');
                } else {
                    field.classList.remove('error');
                }
            });

            return isValid;
        }
    </script>

        <!-- jQuery (required for Select2) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script defer src="script.js"></script>
    <script defer src="translations.js"></script>
</body>
</html>
