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

// Fetch survey details for survey_name as a fallback title
// Added survey_name to fetch for default title if not in localStorage
$surveyStmt = $conn->prepare("SELECT id, type, name FROM survey WHERE id = ?");
$surveyStmt->bind_param("i", $surveyId);
$surveyStmt->execute();
$surveyResult = $surveyStmt->get_result();
$survey = $surveyResult->fetch_assoc();

if (!$survey) {
    die("Survey not found.");
}

// Default survey title from database, used if no custom title in localStorage
$defaultSurveyTitle = htmlspecialchars($survey['survey_name'] ?? 'Ministry of Health Client Satisfaction Feedback Tool');

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

// Determine survey type for conditional rendering
$surveyType = 'local'; // Default
$typeResult = $conn->query("SELECT type FROM survey WHERE id = " . intval($surveyId));
if ($typeResult && $typeRow = $typeResult->fetch_assoc()) {
    $surveyType = $typeRow['type'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $defaultSurveyTitle; ?></title>
    <link rel="stylesheet" href="../styles.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f8f9fa;
            margin: 0;
            padding: 0;
        }
        .question-number {
            font-weight: bold;
            margin-right: 8px;
        }
        .logo-container {
            text-align: center;
            margin-bottom: 20px;
        }
        .logo-container img {
            max-width: 100%;
            height: 170px;
            object-fit: contain;
        }

        /* Default flag bar colors (can be overridden by JS) */
        .flag-bar {
            display: flex;
            height: 10px;
            margin-top: 10px;
            margin-bottom: 20px;
        }
        .flag-black, .flag-yellow, .flag-red {
            flex: 1;
            /* These default colors will be overridden by JS if settings exist */
            background-color: #000; /* Default black */
        }
        .flag-yellow { background-color: #FCD116; /* Default yellow */ }
        .flag-red { background-color: #D21034; /* Default red */ }


        /* Utility class for hiding elements */
        .hidden-element {
            display: none !important;
        }

        /* Top controls for language and print (if you want to keep them) */
        .top-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            background: #fff;
            border-bottom: 1px solid #ddd;
            margin-bottom: 20px; /* Space before the form content */
        }
        .language-switcher select {
            padding: 5px;
            border-radius: 4px;
            border: 1px solid #ccc;
        }
        .print-button button {
            background: none;
            border: none;
            cursor: pointer;
        }
        .print-button img {
            width: 30px; /* Adjust as needed */
            height: 30px;
        }

    </style>
</head>
<body>
    <div class="top-controls">
        <div class="language-switcher">
            <label for="languageSelect" data-translate="select_language">
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

        <div class="header-section" id="logo-section">
            <div class="logo-container">
                <img id="moh-logo" src="asets/asets/img/loog.jpg" alt="Ministry of Health Logo">
            </div>
            </div>

        <div class="flag-bar" id="flag-bar">
            <div class="flag-black" id="flag-black-color"></div>
            <div class="flag-yellow" id="flag-yellow-color"></div>
            <div class="flag-red" id="flag-red-color"></div>
        </div>

        <h2 id="survey-title" data-translate="title"><?php echo $defaultSurveyTitle; ?></h2>
        <h3 id="survey-subtitle" data-translate="client_satisfaction_tool"><?php echo $translations['client_satisfaction_tool'] ?? 'CLIENT SATISFACTION FEEDBACK TOOL'; ?></h3>
        <p class="subheading" id="survey-subheading" data-translate="subheading">
            <?php echo $translations['subheading'] ?? 'This tool is used to obtain clients\' feedback about their experience with the services and promote quality improvement, accountability, and transparency within the healthcare system.'; ?>
        </p>

        <form action="survey_page_submit.php" method="POST" onsubmit="return validateForm()">
            <input type="hidden" name="survey_id" value="<?php echo htmlspecialchars($surveyId); ?>">
            <input type="hidden" name="submission_language" value="en">

            <div class="facility-section" id="facility-section">
                <div class="form-group">
                    <label for="facility-search">Health Facility:</label>
                    <div class="searchable-dropdown">
                        <input type="text" id="facility-search" placeholder="Type to search facilities..." autocomplete="off" required>
                        <div class="dropdown-results" id="facility-results"></div>
                        <input type="hidden" id="facility_id" name="facility_id">
                    </div>
                </div>

                <div class="hierarchy-path" id="hierarchy-path">
                    <div class="path-display" id="path-display"></div>
                </div>

                <input type="hidden" id="hierarchy_data" name="hierarchy_data">
            </div>

            <select id="facility" name="facility" style="display: none;">
                <option value="">None Selected</option>
            </select>

            <?php if ($surveyType === 'local'): ?>

                <div class="location-row" id="location-row-general">
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

                <div class="location-row" id="location-row-period-age">
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
                </div>

                <div class="radio-group" id="ownership-section">
                    <label for="ownership" class="radio-label" data-translate="ownership"><?php echo $translations['ownership'] ?? 'Ownership'; ?></label>
                    <div class="radio-options" id="ownership-options">
                        </div>
                </div>
                <p id="rating-instruction-1" data-translate="rating_instruction"><?php echo $translations['rating_instruction'] ?? '1. Please rate each of the following parameters according to your experience today on a scale of 1 to 4.'; ?></p>
                <p id="rating-instruction-2" data-translate="rating_scale" style="color: red; font-size: 12px; font-style: italic;"><?php echo $translations['rating_scale'] ?? 'where \'0\' means Poor, \'1\' Fair, \'2\' Good and \'3\' Excellent'; ?></p>

            <?php endif; ?>

            <?php foreach ($questionsArray as $index => $question): ?>
                <div class="form-group">
                    <div class="radio-label">
                        <span class="question-number"><?php echo ($index + 1) . '.'; ?></span>
                        <?php echo htmlspecialchars($question['label']); ?>
                    </div>

                    <?php if ($question['question_type'] == 'radio'): ?>
                     <div class="radio-options" style="display: flex; flex-wrap: wrap; gap: 12px;">
                            <?php foreach ($question['options'] as $option): ?>
                               <div class="radio-option" style="flex: 1 1 220px; min-width: 180px;">
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
                           <div class="checkbox-options" style="display: flex; flex-wrap: wrap; gap: 12px;">
                            <?php foreach ($question['options'] as $option): ?>
                                <div class="checkbox-option" style="flex: 1 1 220px; min-width: 180px;">
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

            <button type="submit" id="submit-button-final" data-translate="submit"><?php echo $translations['submit'] ?? 'Submit'; ?></button>
        </form>
    </div>

    <script>
        // Global variables for elements that will be dynamically updated
        const mohLogo = document.getElementById('moh-logo');
        const flagBlackElement = document.getElementById('flag-black-color');
        const flagYellowElement = document.getElementById('flag-yellow-color');
        const flagRedElement = document.getElementById('flag-red-color');
        const flagBarElement = document.getElementById('flag-bar');
        const surveyTitleElement = document.getElementById('survey-title');
        const surveySubtitleElement = document.getElementById('survey-subtitle'); // Added for the subtitle "CLIENT SATISFACTION FEEDBACK TOOL"
        const surveySubheadingElement = document.getElementById('survey-subheading');
        const ratingInstruction1Element = document.getElementById('rating-instruction-1');
        const ratingInstruction2Element = document.getElementById('rating-instruction-2');
        const logoSectionElement = document.getElementById('logo-section');
        const facilitySectionElement = document.getElementById('facility-section');
        const locationRowGeneralElement = document.getElementById('location-row-general');
        const locationRowPeriodAgeElement = document.getElementById('location-row-period-age');
        const ownershipSectionElement = document.getElementById('ownership-section');
        const submitButtonFinal = document.getElementById('submit-button-final');

        document.addEventListener('DOMContentLoaded', function() {
            // Function to apply settings loaded from localStorage
            function applySavedSettings() {
                const settings = JSON.parse(localStorage.getItem('surveyPreviewSettings_<?php echo $surveyId; ?>')) || {};

                // Apply Logo Settings
                if (settings.logoSrc) {
                    mohLogo.src = settings.logoSrc;
                }
                if (settings.showLogo !== undefined) {
                    logoSectionElement.classList.toggle('hidden-element', !settings.showLogo);
                }

                // Apply Flag Bar Colors
                if (settings.flagBlackColor) {
                    flagBlackElement.style.backgroundColor = settings.flagBlackColor;
                }
                if (settings.flagYellowColor) {
                    flagYellowElement.style.backgroundColor = settings.flagYellowColor;
                }
                if (settings.flagRedColor) {
                    flagRedElement.style.backgroundColor = settings.flagRedColor;
                }
                if (settings.showFlagBar !== undefined) {
                    flagBarElement.classList.toggle('hidden-element', !settings.showFlagBar);
                }

                // Apply Title Settings
                if (settings.titleText) {
                    surveyTitleElement.textContent = settings.titleText;
                    // Also update the document title (browser tab title)
                    document.title = settings.titleText;
                } else {
                    // Fallback to PHP default if no custom title in localStorage
                    surveyTitleElement.textContent = '<?php echo $defaultSurveyTitle; ?>';
                    document.title = '<?php echo $defaultSurveyTitle; ?>';
                }
                if (settings.showTitle !== undefined) {
                    surveyTitleElement.classList.toggle('hidden-element', !settings.showTitle);
                }

                // Apply Subtitle (CLIENT SATISFACTION FEEDBACK TOOL) - Assuming it's not directly editable in preview,
                // but its visibility might be controlled if you add a toggle in preview_form.
                // For now, it remains based on PHP translations.
                // If you add a toggle for surveySubtitle, you'd apply settings.showSubtitle here.

                // Apply Subheading Settings
                if (settings.subheadingText) {
                    surveySubheadingElement.textContent = settings.subheadingText;
                }
                if (settings.showSubheading !== undefined) {
                    surveySubheadingElement.classList.toggle('hidden-element', !settings.showSubheading);
                }

                // Apply Rating Instructions
                if (settings.ratingInstruction1Text) {
                    ratingInstruction1Element.textContent = settings.ratingInstruction1Text;
                }
                if (settings.ratingInstruction2Text) {
                    ratingInstruction2Element.textContent = settings.ratingInstruction2Text;
                }
                if (settings.showRatingInstructions !== undefined) {
                    ratingInstruction1Element.classList.toggle('hidden-element', !settings.showRatingInstructions);
                    ratingInstruction2Element.classList.toggle('hidden-element', !settings.showRatingInstructions);
                } else {
                     // If showRatingInstructions is not defined (e.g., old settings), ensure they are visible by default
                     ratingInstruction1Element.classList.remove('hidden-element');
                     ratingInstruction2Element.classList.remove('hidden-element');
                }


                // Apply Visibility Toggles for Sections
                if (settings.showFacilitySection !== undefined) {
                    facilitySectionElement.classList.toggle('hidden-element', !settings.showFacilitySection);
                } else {
                    facilitySectionElement.classList.remove('hidden-element'); // Default to visible
                }

                if (settings.showLocationRowGeneral !== undefined) {
                    locationRowGeneralElement.classList.toggle('hidden-element', !settings.showLocationRowGeneral);
                } else {
                    locationRowGeneralElement.classList.remove('hidden-element'); // Default to visible
                }

                if (settings.showLocationRowPeriodAge !== undefined) {
                    locationRowPeriodAgeElement.classList.toggle('hidden-element', !settings.showLocationRowPeriodAge);
                } else {
                    locationRowPeriodAgeElement.classList.remove('hidden-element'); // Default to visible
                }

                if (settings.showOwnershipSection !== undefined) {
                    ownershipSectionElement.classList.toggle('hidden-element', !settings.showOwnershipSection);
                } else {
                    ownershipSectionElement.classList.remove('hidden-element'); // Default to visible
                }

                if (settings.showSubmitButton !== undefined) {
                    submitButtonFinal.classList.toggle('hidden-element', !settings.showSubmitButton);
                } else {
                    submitButtonFinal.classList.remove('hidden-element'); // Default to visible
                }

                 // Check if the overall "header-section" (containing logo) should be hidden if logo is hidden
                if (settings.showLogo === false && logoSectionElement) {
                    // You might need a more robust way to decide if the *entire* header-section should be hidden
                    // if it contains other static elements. For now, we only toggle the logo container.
                    // If "THE REPUBLIC OF UGANDA" and "MINISTRY OF HEALTH" are static, they will remain.
                    // If you want them to hide with the logo, consider wrapping them inside logo-container
                    // or add a separate toggle for them in the preview_form.
                }

                // Check if the subtitle (Client Satisfaction Feedback Tool) should be hidden
                if (settings.showClientSatisfactionSubtitle !== undefined) { // Assuming a new toggle in preview_form.php
                     surveySubtitleElement.classList.toggle('hidden-element', !settings.showClientSatisfactionSubtitle);
                }
            }

            // Apply settings when the page loads
            applySavedSettings();
        });


        // Language switcher function
        function changeLanguage() {
            var selectedLang = document.getElementById('languageSelect').value;
            window.location.href = "survey_page.php?survey_id=<?php echo $surveyId; ?>&language=" + selectedLang;
        }

        // Print function
        function printForm() {
            window.print();
        }

        // Form validation function
        function validateForm() {
            // Your existing validation logic here
            const facilityId = document.getElementById('facility_id').value;
            if (!facilityId) {
                alert('Please select a Health Facility.');
                return false;
            }
            // Add other required field checks here (e.g., for age, service unit if they become hidden and are required)
            // You might need to adjust validation based on what elements are visible.
            // For example:
            // if (!locationRowGeneralElement.classList.contains('hidden-element')) {
            //     if (!document.getElementById('serviceUnit').value) {
            //         alert('Please select a Service Unit.');
            //         return false;
            //     }
            // }

            return true; // Return true to submit the form, false to prevent submission
        }

    </script>

    <script defer src="./survey_page.js"></script>

    <script defer src="../translations.js"></script>
</body>
</html>