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

// Fetch survey details including survey name AND TYPE
$surveyStmt = $conn->prepare("SELECT id, type, name FROM survey WHERE id = ?");
$surveyStmt->bind_param("i", $surveyId);
$surveyStmt->execute();
$surveyResult = $surveyStmt->get_result();
$survey = $surveyResult->fetch_assoc();

if (!$survey) {
    die("Survey not found.");
}

// Set the default survey title from the database
$defaultSurveyTitle = htmlspecialchars($survey['name'] ?? 'Ministry of Health Client Satisfaction Feedback Tool');

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

// Apply translations to questions and options
foreach ($questionsArray as &$question) {
    $questionTranslations = $question['translations'] ? json_decode($question['translations'], true) : [];
    $question['label'] = $questionTranslations[$language] ?? $question['label'];

    foreach ($question['options'] as &$option) {
        $optionTranslations = $option['translations'] ? json_decode($option['translations'], true) : [];
        $option['option_value'] = $optionTranslations[$language] ?? $option['option_value'];
    }
}
unset($question); // Break the reference with the last element
unset($option);   // Break the reference with the last element
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $defaultSurveyTitle; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../styles.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f8f9fa;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
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

        /* Preview and Control Panel Layout */
        .main-content {
            display: flex;
            flex-grow: 1;
            width: 100%;
        }
        .preview-container {
            flex: 2;
            padding: 20px;
            box-sizing: border-box;
            overflow-y: auto; /* Allow preview to scroll if content is long */
        }
        .control-panel {
            flex: 1;
            background-color: #f0f0f0;
            border-left: 1px solid #ddd;
            padding: 20px;
            box-sizing: border-box;
            overflow-y: auto; /* Allow control panel to scroll */
            padding-bottom: 80px; /* Space for the fixed bottom controls */
        }
        .control-panel h3 {
            margin-top: 0;
            color: #333;
        }
        .control-panel .setting-group {
            margin-bottom: 15px; /* Reduced margin, no bottom border now */
            /* Removed padding-bottom and border-bottom as accordion-content handles separation */
        }
        .control-panel label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
        }
        .control-panel input[type="text"],
        .control-panel textarea,
        .control-panel input[type="color"],
        .control-panel input[type="file"] {
            width: calc(100% - 10px);
            padding: 8px;
            margin-bottom: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .control-panel .checkbox-group label {
            display: flex;
            align-items: center;
            font-weight: normal;
        }
        .control-panel .checkbox-group input[type="checkbox"] {
            margin-right: 10px;
            width: auto;
        }
        .flag-bar {
            display: flex;
            height: 10px;
            margin-top: 10px;
            margin-bottom: 20px;
        }
        .flag-black, .flag-yellow, .flag-red {
            flex: 1;
        }
        /* Default colors for flag bar (can be overridden by JS) */
        .flag-black { background-color: #000; }
        .flag-yellow { background-color: #FCD116; /* Gold */ }
        .flag-red { background-color: #D21034; /* Red */ }

        /* Hideable elements */
        .hidden-element {
            display: none !important;
        }

        /* Accordion Styles */
        .accordion-item {
            border: 1px solid #ddd;
            margin-bottom: 10px;
            border-radius: 5px;
            background-color: #fff;
        }

        .accordion-header {
            background-color: #e9e9e9;
            color: #333;
            cursor: pointer;
            padding: 12px 15px;
            width: 100%;
            text-align: left;
            border: none;
            outline: none;
            transition: background-color 0.3s ease;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: bold;
            border-radius: 5px 5px 0 0;
        }

        .accordion-header:hover {
            background-color: #dcdcdc;
        }

        .accordion-header i {
            transition: transform 0.3s ease;
        }

        .accordion-header.active i {
            transform: rotate(180deg);
        }

        .accordion-content {
            padding: 15px;
            background-color: #f9f9f9;
            border-top: 1px solid #eee;
            display: none; /* Hidden by default */
            overflow: hidden;
            border-radius: 0 0 5px 5px;
        }

        .accordion-content.active {
            display: block;
        }

        /* Bottom Controls Styling (Fixed Footer) */
        .bottom-controls {
            position: fixed; /* Make it fixed */
            bottom: 0; /* Stick to the bottom */
            left: 0;
            width: 100%; /* Take full width */
            display: flex;
            justify-content: center;
            gap: 20px;
            padding: 15px 20px;
            background: #fff;
            border-top: 1px solid #ddd;
            box-shadow: 0 -2px 5px rgba(0,0,0,0.1); /* Add a subtle shadow */
            z-index: 1000; /* Ensure it's on top */
        }
        .action-button {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: background-color 0.3s ease;
        }
        .action-button:hover {
            background-color: #0056b3;
        }
        .action-button i {
            font-size: 18px;
        }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="container preview-container" id="form-content">

            <div class="header-section" id="logo-section">
                <div class="logo-container">
                    <img id="moh-logo" src="asets/asets/img/loog.jpg" alt="Ministry of Health Logo">
                </div>
                <div class="title hidden-element" id="republic-title">THE REPUBLIC OF UGANDA</div>
                <div class="subtitle hidden-element" id="ministry-subtitle">MINISTRY OF HEALTH</div>
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

            <div class="facility-section" id="facility-section">
                <div class="form-group">
                    <label for="facility-search">Locations:</label>
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

            <?php if ($survey['type'] === 'local'): ?>
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

            <button type="submit" id="submit-button-preview"><?php echo $translations['submit'] ?? 'Submit'; ?></button>
        </div>

        <div class="control-panel">
            <h3>Preview Settings</h3>

            <div class="accordion-item">
                <button class="accordion-header">Branding & Appearance <i class="fas fa-chevron-down"></i></button>
                <div class="accordion-content">
                    <div class="setting-group">
                        <label for="logo-upload">Upload Logo:</label>
                        <input type="file" id="logo-upload" accept="image/*">
                        <div class="checkbox-group">
                            <label>
                                <input type="checkbox" id="toggle-logo" checked> Show Logo
                            </label>
                        </div>
                    </div>

                    <div class="setting-group">
                        <h4>Flag Bar Colors:</h4>
                        <label for="flag-black-color-picker">First Strip Color:</label>
                        <input type="color" id="flag-black-color-picker" value="#000000">
                        <label for="flag-yellow-color-picker">Second Strip Color:</label>
                        <input type="color" id="flag-yellow-color-picker" value="#FCD116">
                        <label for="flag-red-color-picker">Third Strip Color:</label>
                        <input type="color" id="flag-red-color-picker" value="#D21034">
                        <div class="checkbox-group">
                            <label>
                                <input type="checkbox" id="toggle-flag-bar" checked> Show Color Bar
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="accordion-item">
                <button class="accordion-header">Survey Content <i class="fas fa-chevron-down"></i></button>
                <div class="accordion-content">
                    <div class="setting-group">
                        <label for="edit-title">Survey Title:</label>
                        <input type="text" id="edit-title" value="<?php echo $defaultSurveyTitle; ?>">
                        <div class="checkbox-group">
                            <label>
                                <input type="checkbox" id="toggle-title" checked> Show Title
                            </label>
                        </div>
                    </div>

                    <div class="setting-group">
                        <label for="edit-subheading">Survey Subheading:</label>
                        <textarea id="edit-subheading" rows="4"><?php echo htmlspecialchars($translations['subheading'] ?? 'This tool is used to obtain clients\' feedback about their experience with the services and promote quality improvement, accountability, and transparency within the healthcare system.'); ?></textarea>
                        <div class="checkbox-group">
                            <label>
                                <input type="checkbox" id="toggle-subheading" checked> Show Subheading
                            </label>
                        </div>
                    </div>

                    <div class="setting-group" id="rating-instructions-control-group">
                        <label for="edit-rating-instruction-1">Rating Instruction 1:</label>
                        <textarea id="edit-rating-instruction-1" rows="2"><?php echo htmlspecialchars($translations['rating_instruction'] ?? '1. Please rate each of the following parameters according to your experience today on a scale of 1 to 4.'); ?></textarea>
                        <label for="edit-rating-instruction-2">Rating Instruction 2:</label>
                        <textarea id="edit-rating-instruction-2" rows="2"><?php echo htmlspecialchars($translations['rating_scale'] ?? 'where \'0\' means Poor, \'1\' Fair, \'2\' Good and \'3\' Excellent'); ?></textarea>
                        <div class="checkbox-group">
                            <label>
                                <input type="checkbox" id="toggle-rating-instructions" checked> Show Rating Instructions
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="accordion-item" id="form-sections-accordion-item">
                <button class="accordion-header">Form Sections Visibility <i class="fas fa-chevron-down"></i></button>
                <div class="accordion-content">
                    <div class="setting-group" id="toggle-facility-section-group">
                        <div class="checkbox-group">
                            <label>
                                <input type="checkbox" id="toggle-facility-section" checked> Show Facility Section
                            </label>
                        </div>
                    </div>

                    <div class="setting-group" id="toggle-location-row-general-group">
                        <div class="checkbox-group">
                            <label>
                                <input type="checkbox" id="toggle-location-row-general" checked> Show Service Unit/Sex
                            </label>
                        </div>
                    </div>

                    <div class="setting-group" id="toggle-location-row-period-age-group">
                        <div class="checkbox-group">
                            <label>
                                <input type="checkbox" id="toggle-location-row-period-age" checked> Show Reporting Period/Age
                            </label>
                        </div>
                    </div>

                    <div class="setting-group" id="toggle-ownership-section-group">
                        <div class="checkbox-group">
                            <label>
                                <input type="checkbox" id="toggle-ownership-section" checked> Show Ownership
                            </label>
                        </div>
                    </div>

                    <div class="setting-group">
                        <div class="checkbox-group">
                            <label>
                                <input type="checkbox" id="toggle-submit-button" checked> Show Submit Button
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="accordion-item">
                <button class="accordion-header">Share Page Settings <i class="fas fa-chevron-down"></i></button>
                <div class="accordion-content">
                    <div class="setting-group">
                        <label for="edit-logo-url">Logo URL (for Share Page):</label>
                        <input type="text" id="edit-logo-url" value="asets/asets/img/loog.jpg" readonly>
                        <div class="checkbox-group">
                            <label>
                                <input type="checkbox" id="toggle-logo-url" checked> Show Logo on Share Page
                            </label>
                        </div>
                    </div>
                    <div class="setting-group">
                        <label for="edit-republic-title-share">Republic Title (Share Page):</label>
                        <input type="text" id="edit-republic-title-share" value="THE REPUBLIC OF UGANDA">
                        <div class="checkbox-group">
                            <label>
                                <input type="checkbox" id="toggle-republic-title-share" checked> Show Republic Title
                            </label>
                        </div>
                    </div>
                    <div class="setting-group">
                        <label for="edit-ministry-subtitle-share">Ministry Subtitle (Share Page):</label>
                        <input type="text" id="edit-ministry-subtitle-share" value="MINISTRY OF HEALTH">
                        <div class="checkbox-group">
                            <label>
                                <input type="checkbox" id="toggle-ministry-subtitle-share" checked> Show Ministry Subtitle
                            </label>
                        </div>
                    </div>
                    <div class="setting-group">
                        <label for="edit-qr-instructions-share">QR Instructions Text (Share Page):</label>
                        <textarea id="edit-qr-instructions-share" rows="3">Scan this QR Code to Give Your Feedback on Services Received</textarea>
                        <div class="checkbox-group">
                            <label>
                                <input type="checkbox" id="toggle-qr-instructions-share" checked> Show QR Instructions
                            </label>
                        </div>
                    </div>
                    <div class="setting-group">
                        <label for="edit-footer-note-share">Footer Note Text (Share Page):</label>
                        <textarea id="edit-footer-note-share" rows="2">Thank you for helping us improve our services.</textarea>
                        <div class="checkbox-group">
                            <label>
                                <input type="checkbox" id="toggle-footer-note-share" checked> Show Footer Note
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <hr> <button onclick="savePreviewSettings()">Save Preview Settings</button>
            <button onclick="resetPreviewSettings()">Reset Preview</button>
        </div>
    </div>

    <div class="bottom-controls">
        <button onclick="window.location.href='update_form?survey_id=<?php echo $surveyId; ?>'" class="action-button">
            <i class="fas fa-arrow-left"></i> Back
        </button>
        <button id="share-btn" class="action-button">
            <i class="fas fa-share"></i> Share
        </button>
        <button onclick="window.location.href='survey_page.php?survey_id=<?php echo $surveyId; ?>'" class="action-button">
            <i class="fas fa-rocket"></i> Generate
        </button>
    </div>

<script>
    // Pass the survey type from PHP to JavaScript
    const surveyType = "<?php echo $survey['type']; ?>"; // IMPORTANT: Get the actual type from PHP

    document.addEventListener('DOMContentLoaded', function() {
        // --- DOM Elements ---
        // Elements for Survey Page (Preview)
        const logoImg = document.getElementById('moh-logo');
        const logoUpload = document.getElementById('logo-upload');
        const toggleLogo = document.getElementById('toggle-logo');
        const logoSection = document.getElementById('logo-section');

        const flagBlackColorPicker = document.getElementById('flag-black-color-picker');
        const flagYellowColorPicker = document.getElementById('flag-yellow-color-picker');
        const flagRedColorPicker = document.getElementById('flag-red-color-picker');
        const flagBlackElement = document.getElementById('flag-black-color');
        const flagYellowElement = document.getElementById('flag-yellow-color');
        const flagRedElement = document.getElementById('flag-red-color');
        const toggleFlagBar = document.getElementById('toggle-flag-bar');
        const flagBarElement = document.getElementById('flag-bar');

        const editTitle = document.getElementById('edit-title');
        const surveyTitle = document.getElementById('survey-title');
        const toggleTitle = document.getElementById('toggle-title');

        const editSubheading = document.getElementById('edit-subheading');
        const surveySubheading = document.getElementById('survey-subheading');
        const toggleSubheading = document.getElementById('toggle-subheading');

        // Get elements for conditional sections in the control panel
        const ratingInstructionsControlGroup = document.getElementById('rating-instructions-control-group');
        const formSectionsAccordionItem = document.getElementById('form-sections-accordion-item');
        const toggleFacilitySectionGroup = document.getElementById('toggle-facility-section-group');
        const toggleLocationRowGeneralGroup = document.getElementById('toggle-location-row-general-group');
        const toggleLocationRowPeriodAgeGroup = document.getElementById('toggle-location-row-period-age-group');
        const toggleOwnershipSectionGroup = document.getElementById('toggle-ownership-section-group');

        // Get elements for conditional sections in the actual preview form
        const ratingInstruction1 = document.getElementById('rating-instruction-1');
        const ratingInstruction2 = document.getElementById('rating-instruction-2');
        const facilitySection = document.getElementById('facility-section');
        const locationRowGeneral = document.getElementById('location-row-general');
        const locationRowPeriodAge = document.getElementById('location-row-period-age');
        const ownershipSection = document.getElementById('ownership-section');


        const editRatingInstruction1 = document.getElementById('edit-rating-instruction-1');
        const editRatingInstruction2 = document.getElementById('edit-rating-instruction-2');
        const toggleRatingInstructions = document.getElementById('toggle-rating-instructions');


        const toggleFacilitySection = document.getElementById('toggle-facility-section');
        const toggleLocationRowGeneral = document.getElementById('toggle-location-row-general');
        const toggleLocationRowPeriodAge = document.getElementById('toggle-location-row-period-age');
        const toggleOwnershipSection = document.getElementById('toggle-ownership-section');

        const toggleSubmitButton = document.getElementById('toggle-submit-button');
        const submitButtonPreview = document.getElementById('submit-button-preview');

        // Elements for Share Page settings (and their preview on this page)
        const logoUrlInput = document.getElementById('edit-logo-url');
        const toggleLogoUrl = document.getElementById('toggle-logo-url');
        const republicTitleElement = document.getElementById('republic-title');
        const editRepublicTitleShare = document.getElementById('edit-republic-title-share');
        const toggleRepublicTitleShare = document.getElementById('toggle-republic-title-share');

        const ministrySubtitleElement = document.getElementById('ministry-subtitle');
        const editMinistrySubtitleShare = document.getElementById('edit-ministry-subtitle-share');
        const toggleMinistrySubtitleShare = document.getElementById('toggle-ministry-subtitle-share');

        const editQrInstructionsShare = document.getElementById('edit-qr-instructions-share');
        const toggleQrInstructionsShare = document.getElementById('toggle-qr-instructions-share');

        const editFooterNoteShare = document.getElementById('edit-footer-note-share');
        const toggleFooterNoteShare = document.getElementById('toggle-footer-note-share');

        // --- Functions ---

        /**
         * Applies conditional visibility to control panel sections and preview elements
         * based on survey type.
         */
        function applyTypeSpecificControls() {
            if (surveyType === 'dhis2') {
                // Hide relevant groups in the control panel for DHIS2 surveys
                if (formSectionsAccordionItem) formSectionsAccordionItem.classList.add('hidden-element');
                if (ratingInstructionsControlGroup) ratingInstructionsControlGroup.classList.add('hidden-element');

                // Ensure the preview elements themselves are also hidden for DHIS2
                if (facilitySection) facilitySection.classList.add('hidden-element');
                if (locationRowGeneral) locationRowGeneral.classList.add('hidden-element');
                if (locationRowPeriodAge) locationRowPeriodAge.classList.add('hidden-element');
                if (ownershipSection) ownershipSection.classList.add('hidden-element');
                if (ratingInstruction1) ratingInstruction1.classList.add('hidden-element');
                if (ratingInstruction2) ratingInstruction2.classList.add('hidden-element');

                // Also ensure their corresponding checkboxes in the control panel are unchecked
                if (toggleFacilitySection) toggleFacilitySection.checked = false;
                if (toggleLocationRowGeneral) toggleLocationRowGeneral.checked = false;
                if (toggleLocationRowPeriodAge) toggleLocationRowPeriodAge.checked = false;
                if (toggleOwnershipSection) toggleOwnershipSection.checked = false;
                if (toggleRatingInstructions) toggleRatingInstructions.checked = false;

            } else if (surveyType === 'local') {
                // Ensure they are visible in control panel for 'local' surveys
                if (formSectionsAccordionItem) formSectionsAccordionItem.classList.remove('hidden-element');
                if (ratingInstructionsControlGroup) ratingInstructionsControlGroup.classList.remove('hidden-element');
                // The visibility of the preview elements themselves will be handled by loadPreviewSettings
                // based on saved preferences, which is the correct behavior for 'local' surveys.
            }
            // Add conditions for other survey types if needed
        }

        /**
         * Loads preview settings from localStorage and applies them to the DOM.
         */
        window.loadPreviewSettings = function() {
            const settings = JSON.parse(localStorage.getItem('surveyPreviewSettings_<?php echo $surveyId; ?>')) || {};

            // Preview Form Settings (Branding & Global)
            logoImg.src = settings.logoSrc || 'asets/asets/img/loog.jpg'; // Default if not set
            toggleLogo.checked = settings.showLogo !== undefined ? settings.showLogo : true;
            logoSection.classList.toggle('hidden-element', !toggleLogo.checked);

            flagBlackColorPicker.value = settings.flagBlackColor || '#000000';
            flagBlackElement.style.backgroundColor = flagBlackColorPicker.value;
            flagYellowColorPicker.value = settings.flagYellowColor || '#FCD116';
            flagYellowElement.style.backgroundColor = flagYellowColorPicker.value;
            flagRedColorPicker.value = settings.flagRedColor || '#D21034';
            flagRedElement.style.backgroundColor = flagRedColorPicker.value;
            toggleFlagBar.checked = settings.showFlagBar !== undefined ? settings.showFlagBar : true;
            flagBarElement.classList.toggle('hidden-element', !toggleFlagBar.checked);

            editTitle.value = settings.titleText || '<?php echo $defaultSurveyTitle; ?>';
            surveyTitle.textContent = editTitle.value;
            toggleTitle.checked = settings.showTitle !== undefined ? settings.showTitle : true;
            surveyTitle.classList.toggle('hidden-element', !toggleTitle.checked);

            editSubheading.value = settings.subheadingText || '<?php echo htmlspecialchars($translations['subheading'] ?? 'This tool is used to obtain clients\' feedback about their experience with the services and promote quality improvement, accountability, and transparency within the healthcare system.'); ?>';
            surveySubheading.textContent = editSubheading.value;
            toggleSubheading.checked = settings.showSubheading !== undefined ? settings.showSubheading : true;
            surveySubheading.classList.toggle('hidden-element', !toggleSubheading.checked);

            // Conditional Form Section Settings (only apply if type is 'local' or if settings exist)
            // It's crucial here: if surveyType is 'dhis2', these elements are already hidden by applyTypeSpecificControls()
            // and we don't want loadPreviewSettings to make them visible again based on old saved settings.
            if (surveyType === 'local') {
                editRatingInstruction1.value = settings.ratingInstruction1Text || '<?php echo htmlspecialchars($translations['rating_instruction'] ?? '1. Please rate each of the following parameters according to your experience today on a scale of 1 to 4.'); ?>';
                ratingInstruction1.textContent = editRatingInstruction1.value;
                editRatingInstruction2.value = settings.ratingInstruction2Text || '<?php echo htmlspecialchars($translations['rating_scale'] ?? 'where \'0\' means Poor, \'1\' Fair, \'2\' Good and \'3\' Excellent'); ?>';
                ratingInstruction2.textContent = editRatingInstruction2.value;
                toggleRatingInstructions.checked = settings.showRatingInstructions !== undefined ? settings.showRatingInstructions : true;
                ratingInstruction1.classList.toggle('hidden-element', !toggleRatingInstructions.checked);
                ratingInstruction2.classList.toggle('hidden-element', !toggleRatingInstructions.checked);

                toggleFacilitySection.checked = settings.showFacilitySection !== undefined ? settings.showFacilitySection : true;
                facilitySection.classList.toggle('hidden-element', !toggleFacilitySection.checked);

                toggleLocationRowGeneral.checked = settings.showLocationRowGeneral !== undefined ? settings.showLocationRowGeneral : true;
                locationRowGeneral.classList.toggle('hidden-element', !toggleLocationRowGeneral.checked);

                toggleLocationRowPeriodAge.checked = settings.showLocationRowPeriodAge !== undefined ? settings.showLocationRowPeriodAge : true;
                locationRowPeriodAge.classList.toggle('hidden-element', !toggleLocationRowPeriodAge.checked);

                toggleOwnershipSection.checked = settings.showOwnershipSection !== undefined ? settings.showOwnershipSection : true;
                ownershipSection.classList.toggle('hidden-element', !toggleOwnershipSection.checked);
            }


            toggleSubmitButton.checked = settings.showSubmitButton !== undefined ? settings.showSubmitButton : true;
            submitButtonPreview.classList.toggle('hidden-element', !toggleSubmitButton.checked);

            // Share Page Settings (also affect local preview of these elements if present)
            logoUrlInput.value = settings.logoUrl || logoImg.src;
            toggleLogoUrl.checked = settings.showLogoUrl !== undefined ? settings.showLogoUrl : true;
            editRepublicTitleShare.value = settings.republicTitleText || 'THE REPUBLIC OF UGANDA';
            republicTitleElement.textContent = editRepublicTitleShare.value;
            toggleRepublicTitleShare.checked = settings.showRepublicTitleShare !== undefined ? settings.showRepublicTitleShare : true;
            republicTitleElement.classList.toggle('hidden-element', !toggleRepublicTitleShare.checked);

            editMinistrySubtitleShare.value = settings.ministrySubtitleText || 'MINISTRY OF HEALTH';
            ministrySubtitleElement.textContent = editMinistrySubtitleShare.value;
            toggleMinistrySubtitleShare.checked = settings.showMinistrySubtitleShare !== undefined ? settings.showMinistrySubtitleShare : true;
            ministrySubtitleElement.classList.toggle('hidden-element', !toggleMinistrySubtitleShare.checked);

            editQrInstructionsShare.value = settings.qrInstructionsText || 'Scan this QR Code to Give Your Feedback on Services Received';
            toggleQrInstructionsShare.checked = settings.showQrInstructionsShare !== undefined ? settings.showQrInstructionsShare : true;

            editFooterNoteShare.value = settings.footerNoteText || 'Thank you for helping us improve our services.';
            toggleFooterNoteShare.checked = settings.showFooterNoteShare !== undefined ? settings.showFooterNoteShare : true;
        };

        /**
         * Saves all current preview and share page settings to localStorage.
         */
        window.savePreviewSettings = function() {
            const settings = {
                // Preview Form Settings (Branding & Global)
                logoSrc: logoImg.src,
                showLogo: toggleLogo.checked,
                flagBlackColor: flagBlackColorPicker.value,
                flagYellowColor: flagYellowColorPicker.value,
                flagRedColor: flagRedColorPicker.value,
                showFlagBar: toggleFlagBar.checked,
                titleText: editTitle.value,
                showTitle: toggleTitle.checked,
                subheadingText: editSubheading.value,
                showSubheading: toggleSubheading.checked,
                showSubmitButton: toggleSubmitButton.checked,

                // Conditional Form Section Settings (only save if type is 'local')
                // This prevents saving irrelevant settings for DHIS2 surveys
                ...(surveyType === 'local' && {
                    ratingInstruction1Text: editRatingInstruction1.value,
                    ratingInstruction2Text: editRatingInstruction2.value,
                    showRatingInstructions: toggleRatingInstructions.checked,
                    showFacilitySection: toggleFacilitySection.checked,
                    showLocationRowGeneral: toggleLocationRowGeneral.checked,
                    showLocationRowPeriodAge: toggleLocationRowPeriodAge.checked,
                    showOwnershipSection: toggleOwnershipSection.checked,
                }),


                // Share Page Settings (always save these as they are universal for the share page)
                logoUrl: logoUrlInput.value,
                showLogoUrl: toggleLogoUrl.checked,
                republicTitleText: editRepublicTitleShare.value,
                showRepublicTitleShare: toggleRepublicTitleShare.checked,
                ministrySubtitleText: editMinistrySubtitleShare.value,
                showMinistrySubtitleShare: toggleMinistrySubtitleShare.checked,
                qrInstructionsText: editQrInstructionsShare.value,
                showQrInstructionsShare: toggleQrInstructionsShare.checked,
                footerNoteText: editFooterNoteShare.value,
                showFooterNoteShare: toggleFooterNoteShare.checked
            };
            localStorage.setItem('surveyPreviewSettings_<?php echo $surveyId; ?>', JSON.stringify(settings));
            alert('Preview settings saved!');
        };

        /**
         * Resets all settings for the current survey ID by clearing localStorage and reloading the page.
         */
        window.resetPreviewSettings = function() {
            localStorage.removeItem('surveyPreviewSettings_<?php echo $surveyId; ?>');
            location.reload(); // Reload page to revert to PHP defaults and re-load initial state
        };

        // --- Event Listeners for Live Preview Updates ---

        // Logo upload and URL input sync
        logoUpload.addEventListener('change', function(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    logoImg.src = e.target.result;
                    logoUrlInput.value = e.target.result; // Update URL input for share settings
                };
                reader.readAsDataURL(file);
            }
        });

        // Toggle Logo visibility
        toggleLogo.addEventListener('change', function() {
            logoSection.classList.toggle('hidden-element', !this.checked);
        });

        // Flag Bar Color Pickers
        flagBlackColorPicker.addEventListener('input', function() { flagBlackElement.style.backgroundColor = this.value; });
        flagYellowColorPicker.addEventListener('input', function() { flagYellowElement.style.backgroundColor = this.value; });
        flagRedColorPicker.addEventListener('input', function() { flagRedElement.style.backgroundColor = this.value; });
        toggleFlagBar.addEventListener('change', function() { flagBarElement.classList.toggle('hidden-element', !this.checked); });

        // Survey Title
        editTitle.addEventListener('input', function() { surveyTitle.textContent = this.value; });
        toggleTitle.addEventListener('change', function() { surveyTitle.classList.toggle('hidden-element', !this.checked); });

        // Survey Subheading
        editSubheading.addEventListener('input', function() { surveySubheading.textContent = this.value; });
        toggleSubheading.addEventListener('change', function() { surveySubheading.classList.toggle('hidden-element', !this.checked); });

        // Rating Instructions (only if applicable for local surveys)
        if (surveyType === 'local') {
            editRatingInstruction1.addEventListener('input', function() { ratingInstruction1.textContent = this.value; });
            editRatingInstruction2.addEventListener('input', function() { ratingInstruction2.textContent = this.value; });
            toggleRatingInstructions.addEventListener('change', function() {
                ratingInstruction1.classList.toggle('hidden-element', !this.checked);
                ratingInstruction2.classList.toggle('hidden-element', !this.checked);
            });
        }

        // Section Visibility Toggles (only if applicable for local surveys)
        if (surveyType === 'local') {
            toggleFacilitySection.addEventListener('change', function() { facilitySection.classList.toggle('hidden-element', !this.checked); });
            toggleLocationRowGeneral.addEventListener('change', function() { locationRowGeneral.classList.toggle('hidden-element', !this.checked); });
            toggleLocationRowPeriodAge.addEventListener('change', function() { locationRowPeriodAge.classList.toggle('hidden-element', !this.checked); });
            toggleOwnershipSection.addEventListener('change', function() { ownershipSection.classList.toggle('hidden-element', !this.checked); });
        }

        toggleSubmitButton.addEventListener('change', function() { submitButtonPreview.classList.toggle('hidden-element', !this.checked); });

        // Share Page Element Listeners (for their preview on this page)
        toggleLogoUrl.addEventListener('change', function() { /* No direct preview element on this page to toggle */ });
        editRepublicTitleShare.addEventListener('input', function() { republicTitleElement.textContent = this.value; });
        toggleRepublicTitleShare.addEventListener('change', function() { republicTitleElement.classList.toggle('hidden-element', !this.checked); });
        editMinistrySubtitleShare.addEventListener('input', function() { ministrySubtitleElement.textContent = this.value; });
        toggleMinistrySubtitleShare.addEventListener('change', function() { ministrySubtitleElement.classList.toggle('hidden-element', !this.checked); });
        editQrInstructionsShare.addEventListener('input', function() { /* No direct preview element on this page */ });
        toggleQrInstructionsShare.addEventListener('change', function() { /* No direct preview element on this page to toggle */ });
        editFooterNoteShare.addEventListener('input', function() { /* No direct preview element on this page */ });
        toggleFooterNoteShare.addEventListener('change', function() { /* No direct preview element on this page to toggle */ });

        // --- Accordion Logic ---
        const accordionHeaders = document.querySelectorAll('.accordion-header');
        accordionHeaders.forEach(header => {
            header.addEventListener('click', function() {
                const content = this.nextElementSibling;
                const icon = this.querySelector('i');

                accordionHeaders.forEach(otherHeader => {
                    if (otherHeader !== this && otherHeader.classList.contains('active')) {
                        otherHeader.classList.remove('active');
                        otherHeader.nextElementSibling.style.display = 'none';
                        otherHeader.querySelector('i').classList.remove('active');
                    }
                });

                this.classList.toggle('active');
                icon.classList.toggle('active');
                if (content.style.display === 'block') {
                    content.style.display = 'none';
                } else {
                    content.style.display = 'block';
                }
            });
        });

        // --- Initial Load ---
        applyTypeSpecificControls(); // Apply type-specific visibility first
        loadPreviewSettings();      // Then load saved settings (which might override visibility for 'local')

        // --- Share Button Functionality ---
        document.getElementById('share-btn').addEventListener('click', function() {
            savePreviewSettings();

            // Always use survey_page.php for share, as per your requirement
            const surveyUrl = window.location.origin + '/fbs/admin/survey_page.php?survey_id=<?php echo $surveyId; ?>';

            // Redirect to share_page.php, passing the constructed surveyUrl
            window.location.href = `share_page.php?survey_id=<?php echo $surveyId; ?>&url=${encodeURIComponent(surveyUrl)}`;
        });
    });
</script>

<script defer src="survey_page.js"></script>
<script defer src="translations.js"></script>
</body>
</html>