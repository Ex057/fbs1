<?php
session_start();

// Redirect to login if not logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit();
}

require 'connect.php'; // Ensure this connects to your database
require 'dhis2/dhis2_shared.php'; // Ensure this provides getDhis2Config() and dhis2_get()
require 'dhis2/dhis2_get_function.php'; // Ensure this has necessary DHIS2 API call functions

$success_message = null;
$error_message = null;

// Handle form submission for creating survey (both local and DHIS2)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn = new PDO("mysql:host=localhost;dbname=fbtv3;charset=utf8", "root", "root");
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); // Set error mode for PDO
        $conn->beginTransaction();

        if (isset($_POST['create_local_survey'])) {
            // Logic for Local Survey Creation (NO CHANGE)
            $surveyName = trim($_POST['local_survey_name']);
            if (empty($surveyName)) {
                throw new Exception("Survey name cannot be empty.");
            }

            // Check for duplicate survey name
            $stmt = $conn->prepare("SELECT id FROM survey WHERE name = ?");
            $stmt->execute([$surveyName]);
            if ($stmt->fetch()) {
                throw new Exception("A survey with the name '" . htmlspecialchars($surveyName) . "' already exists.");
            }

            // Insert survey
            $startDate = !empty($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-d');
            $endDate = !empty($_POST['end_date']) ? $_POST['end_date'] : date('Y-m-d', strtotime($startDate . ' +6 months'));
            $isActive = isset($_POST['is_active']) ? 1 : 0;

            $stmt = $conn->prepare("INSERT INTO survey (name, type, start_date, end_date, is_active) VALUES (?, 'local', ?, ?, ?)");
            $stmt->execute([$surveyName, $startDate, $endDate, $isActive]);
            $surveyId = $conn->lastInsertId();
            $position = 1;

            // Attach existing questions
            if (!empty($_POST['attach_questions']) && is_array($_POST['attach_questions'])) {
                foreach ($_POST['attach_questions'] as $qid) {
                    $stmt = $conn->prepare("INSERT INTO survey_question (survey_id, question_id, position) VALUES (?, ?, ?)");
                    $stmt->execute([$surveyId, $qid, $position++]);
                }
            }

            // Insert new questions (skip empty labels)
            if (!empty($_POST['questions']) && is_array($_POST['questions'])) {
                foreach ($_POST['questions'] as $q) {
                    $qLabel = trim($q['label']);
                    if (!empty($qLabel)) {
                        $stmt = $conn->prepare("INSERT INTO question (label, question_type, is_required) VALUES (?, ?, 1)");
                        $stmt->execute([$qLabel, $q['type']]);
                        $questionId = $conn->lastInsertId();
                        $stmt = $conn->prepare("INSERT INTO survey_question (survey_id, question_id, position) VALUES (?, ?, ?)");
                        $stmt->execute([$surveyId, $questionId, $position++]);
                    }
                }
            }

            $conn->commit();
            $success_message = "Local survey successfully created.";

        } elseif (isset($_POST['create_survey'])) {
            // Logic for DHIS2 Survey Creation (MODIFIED for no dhis2_id in DB)
            $dhis2Instance = $_POST['dhis2_instance'];
            $programId = $_POST['program_id'];
            $programName = $_POST['program_name'];
            $domain = $_POST['domain']; // Get the domain
            $programType = $_POST['program_type'] ?? null; // Get program_type for tracker/event

            // Check if survey already exists by program_dataset (UID) first
            $stmt = $conn->prepare("SELECT id FROM survey WHERE program_dataset = ?");
            $stmt->execute([$programId]);
            $existingSurvey = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existingSurvey) {
                throw new Exception("A survey for this program/dataset (UID) already exists.");
            }

            // If not found by UID, check by name
            $stmt = $conn->prepare("SELECT id FROM survey WHERE name = ?");
            $stmt->execute([$programName]);
            $existingSurvey = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existingSurvey) {
                throw new Exception("A survey with name '" . htmlspecialchars($programName) . "' already exists.");
            }

            // 1. Create survey entry
            $stmt = $conn->prepare("INSERT INTO survey (name, type, dhis2_instance, program_dataset) VALUES (?, 'dhis2', ?, ?)");
            $stmt->execute([$programName, $dhis2Instance, $programId]);
            $surveyId = $conn->lastInsertId();

            // Decode all data passed from the frontend
            $dataElements = json_decode($_POST['data_elements'], true) ?? [];
            $attributes = json_decode($_POST['attributes'], true) ?? [];
            $optionSets = json_decode($_POST['option_sets'], true) ?? []; // All option sets, including synthetic COC ones

            $position = 1;


            /**
             * Helper function to process DHIS2 elements (Data Elements, Attributes).
             * This function will handle Category Combinations for Data Elements,
             * especially for Aggregate domain.
             *
             * @param array $element The DHIS2 element data (Data Element or Attribute).
             * @param int $surveyId The ID of the local survey.
             * @param int &$position The current question position, passed by reference.
             * @param PDO $conn The database connection object.
             * @param array $allOptionSets All option sets fetched from DHIS2, including synthetic ones.
             * @param string $domain The domain ('tracker' or 'aggregate').
             */
            $processDhis2Element = function ($element, $surveyId, &$position, $conn, $allOptionSets, $domain) {
                $questionLabel = trim($element['name']);
                if (empty($questionLabel)) {
                    return; // Skip if label is empty
                }

                $questionType = 'text'; // Default to text
                $optionSetId = null; // Local option_set.id
                $dhis2LinkedOptionSetId = null; // Original DHIS2 option set ID (if applicable)
                $dhis2LinkedOptionSetName = null; // Original DHIS2 option set Name (for local lookup)

                // Determine question type and linked option set
                // Priority: Aggregate DE with Category Combo -> Regular Option Set -> Text
                if ($domain === 'aggregate' && !empty($element['categoryCombo']['id'])) {
                    $categoryComboId = $element['categoryCombo']['id'];
                    $syntheticOptionSetId = 'dhis2_cc_' . $categoryComboId . '_coc_optset';

                    if (isset($allOptionSets[$syntheticOptionSetId])) {
                        $dhis2CategoryComboOptionSet = $allOptionSets[$syntheticOptionSetId];
                        $questionType = 'select'; // If it has a synthetic option set, it's a select type
                        $dhis2LinkedOptionSetId = $syntheticOptionSetId; // Link to this synthetic ID
                        $dhis2LinkedOptionSetName = $dhis2CategoryComboOptionSet['name']; // Use name for local lookup
                    }
                } elseif (!empty($element['optionSet'])) { // Regular DHIS2 Option Set
                    $dhis2LinkedOptionSetId = $element['optionSet']['id']; // Original DHIS2 OptionSet UID
                    $dhis2LinkedOptionSetName = $element['optionSet']['name']; // Original DHIS2 OptionSet Name
                    $questionType = 'select';
                }

                // Process the determined DHIS2 linked option set (synthetic or regular)
                if (!empty($dhis2LinkedOptionSetName) && isset($allOptionSets[$dhis2LinkedOptionSetId])) {
                    $dhis2OptionSetToProcess = $allOptionSets[$dhis2LinkedOptionSetId];

                    // Check if option set already exists locally by its NAME (since dhis2_id is not allowed)
                    $stmt = $conn->prepare("SELECT id FROM option_set WHERE name = ?");
                    $stmt->execute([$dhis2OptionSetToProcess['name']]);
                    $existingOptionSet = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($existingOptionSet) {
                        $optionSetId = $existingOptionSet['id'];
                    } else {
                        // Create new local option set (WITHOUT dhis2_id column)
                        $stmt = $conn->prepare("INSERT INTO option_set (name) VALUES (?)");
                        $stmt->execute([$dhis2OptionSetToProcess['name']]);
                        $optionSetId = $conn->lastInsertId();

                        // Add option values to the new local option set
                        if (!empty($dhis2OptionSetToProcess['options'])) {
                            foreach ($dhis2OptionSetToProcess['options'] as $option) {
                                $stmt = $conn->prepare("INSERT INTO option_set_values (option_set_id, option_value) VALUES (?, ?)");
                                $stmt->execute([$optionSetId, $option['name']]);
                                // Without dhis2_id for options, mapping back will be hard
                            }
                        }
                    }
                }

                // Insert into question table
                $stmt = $conn->prepare("INSERT INTO question (label, question_type, is_required, option_set_id) VALUES (?, ?, 1, ?)");
                $stmt->execute([$questionLabel, $questionType, $optionSetId]);
                $questionId = $conn->lastInsertId();

                // Add question to survey
                $stmt = $conn->prepare("INSERT INTO survey_question (survey_id, question_id, position) VALUES (?, ?, ?)");
                $stmt->execute([$surveyId, $questionId, $position]);
                $position++;

                // Insert into question_dhis2_mapping
                // ASSUMPTION: Your `question_dhis2_mapping` table has AT LEAST `question_id`, `dhis2_dataelement_id`, `dhis2_option_set_id`.
                // If you cannot add `dhis2_attribute_id`, `dhis2_category_combo_id`, `dhis2_entity_type`,
                // then we must simplify the INSERT statement here.
                // For now, I'll remove the columns that are most likely missing if you haven't altered the DB.
                // This means you lose the ability to easily differentiate data elements, attributes, and category combos
                // in your mapping table based on their DHIS2 UIDs, relying only on what can be mapped to a 'dataelement_id'.

                $dhis2DataElementIdForMapping = null;
                // If it's a data element or attribute, map its primary DHIS2 ID to dhis2_dataelement_id.
                // This is a forced fit due to schema constraints.
                if (isset($element['dhis2_type']) && ($element['dhis2_type'] === 'dataElement' || $element['dhis2_type'] === 'attribute')) {
                    $dhis2DataElementIdForMapping = $element['dhis2_id'];
                }
                // dhis2_option_set_id in mapping will now be the DHIS2 UID of the *linked* option set (synthetic or real)
                // This allows you to at least know which DHIS2 option set provided the values.

                $stmt = $conn->prepare("INSERT INTO question_dhis2_mapping (question_id, dhis2_dataelement_id, dhis2_option_set_id) VALUES (?, ?, ?)");
                $stmt->execute([
                    $questionId,
                    $dhis2DataElementIdForMapping,
                    $dhis2LinkedOptionSetId // Store the DHIS2 UID (synthetic or real) here
                ]);
                // WARNING: Without dhis2_attribute_id, dhis2_category_combo_id, and dhis2_entity_type columns,
                // you will struggle to differentiate between data elements, attributes, and category combos
                // when processing data or exporting it back to DHIS2.
            };

            // Order of processing:
            // Process Data Elements (including aggregate DEs whose options are COCs)
            foreach ($dataElements as $deId => $element) {
                $processDhis2Element($element, $surveyId, $position, $conn, $optionSets, $domain);
            }

            // Process Tracked Entity Attributes (for Tracker Programs ONLY)
            if ($domain === 'tracker' && $programType === 'tracker') {
                foreach ($attributes as $attrId => $attr) {
                    $processDhis2Element($attr, $surveyId, $position, $conn, $optionSets, $domain);
                }
            }

            $conn->commit();
            $success_message = "Survey successfully created from DHIS2 program.";

        } // End of DHIS2 survey creation logic

    } catch (Exception $e) {
        if ($conn && $conn->inTransaction()) {
            $conn->rollBack();
        }
        $error_message = "Error creating survey: " . $e->getMessage();
    }
}


/**
 * Get all active DHIS2 instances using getDhis2Config from dhis2_shared.php.
 * Returns an array of instance configs keyed by their 'key'.
 */
function getLocalDHIS2Config() {
    // Use the shared function to fetch all active instances from the DB
    $instances = [];
    $dbHost = 'localhost'; // Ensure this matches your actual DB host
    $dbUser = 'root'; // Ensure this matches your actual DB user
    $dbPass = 'root'; // Ensure this matches your actual DB password
    $dbName = 'fbtv3'; // Ensure this matches your actual DB name

    $mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
    if ($mysqli->connect_errno) {
        throw new Exception("Database connection failed: " . $mysqli->connect_error);
    }

    $result = $mysqli->query("SELECT `key` FROM dhis2_instances WHERE status = 1");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $config = getDhis2Config($row['key']); // Assuming getDhis2Config is accessible
            if ($config) {
                $instances[$row['key']] = $config;
            }
        }
        $result->free();
    } else {
        throw new Exception("Failed to query DHIS2 instances: " . $mysqli->error);
    }
    $mysqli->close();

    return $instances;
}

/**
 * Fetches programs from DHIS2 based on program type.
 * 'event' will fetch event programs.
 * 'tracker' will fetch tracker programs.
 * If no type is specified, it fetches all.
 */
function getPrograms($instance, $programType = null) {
    $filter = '';
    if ($programType === 'event') {
        $filter = '&filter=programType:eq:WITHOUT_REGISTRATION';
    } elseif ($programType === 'tracker') {
        $filter = '&filter=programType:eq:WITH_REGISTRATION';
    }
    $programs = dhis2_get('/api/programs?fields=id,name,programType' . $filter, $instance);
    return $programs['programs'] ?? [];
}

function getDatasets($instance) {
    // dhis2_get is assumed to be defined in dhis2_shared.php or dhis2_get_function.php
    $datasets = dhis2_get('/api/dataSets?fields=id,name', $instance);
    return $datasets['dataSets'] ?? [];
}

/**
 * Get details for a specific DHIS2 program or dataset, including data elements and attributes.
 * This version integrates Category Combinations/COCs for aggregate data elements as their option sets,
 * and passes Category Combo info for event data elements without creating new questions.
 *
 * @param string $instance The DHIS2 instance key.
 * @param string $domain 'tracker' or 'aggregate'.
 * @param string $programId The ID of the program or dataset.
 * @param string|null $programType 'event' or 'tracker' if domain is 'tracker'.
 * @return array Structured array with program details, data elements, attributes, and option sets.
 * @throws Exception If API call fails or response is invalid.
 */
function getProgramDetails($instance, $domain, $programId, $programType = null) {
    $result = [
        'program' => null,
        'dataElements' => [],
        'attributes' => [],
        'optionSets' => [] // Will contain both regular and synthetic COC option sets
    ];

    if ($domain === 'tracker') {
        if ($programType === 'tracker') { // WITH_REGISTRATION program (Tracker Program)
            // Existing logic: Fetch program details and tracked entity attributes only.
            // No changes here, as category combos are typically not relevant for TEAs in this context.
            $programInfo = dhis2_get('/api/programs/' . $programId . '?fields=id,name,programType,programTrackedEntityAttributes[trackedEntityAttribute[id,name,optionSet[id,name]]]', $instance);

            $result['program'] = [
                'id' => $programInfo['id'],
                'name' => $programInfo['name'],
                'programType' => $programInfo['programType']
            ];

            // Get program attributes (tracker-level data) - NO CHANGE in structure here
            if (!empty($programInfo['programTrackedEntityAttributes'])) {
                foreach ($programInfo['programTrackedEntityAttributes'] as $attr) {
                    $tea = $attr['trackedEntityAttribute'];
                    $result['attributes'][$tea['id']] = [
                        'name' => $tea['name'],
                        'optionSet' => $tea['optionSet'] ?? null,
                        'dhis2_id' => $tea['id'], // Store DHIS2 ID
                        'dhis2_type' => 'attribute'
                    ];
                    if (!empty($tea['optionSet'])) {
                        $result['optionSets'][$tea['optionSet']['id']] = $tea['optionSet'];
                    }
                }
            }

        } elseif ($programType === 'event') { // WITHOUT_REGISTRATION program (Event Program)
            // MODIFIED: Fetch program details for event program, including categoryCombo for data elements
            $programInfo = dhis2_get('/api/programs/' . $programId . '?fields=id,name,programType,programStages[id,name,programStageDataElements[dataElement[id,name,optionSet[id,name],categoryCombo[id,name]]]]', $instance);

            $result['program'] = [
                'id' => $programInfo['id'],
                'name' => $programInfo['name'],
                'programType' => $programInfo['programType']
            ];

            // Get data elements from program stages
            if (!empty($programInfo['programStages'])) {
                foreach ($programInfo['programStages'] as $stage) {
                    if (isset($stage['programStageDataElements'])) {
                        foreach ($stage['programStageDataElements'] as $psde) {
                            $de = $psde['dataElement'];
                            $result['dataElements'][$de['id']] = [
                                'name' => $de['name'],
                                'optionSet' => $de['optionSet'] ?? null,
                                'categoryCombo' => $de['categoryCombo'] ?? null, // Capture categoryCombo
                                'dhis2_id' => $de['id'],
                                'dhis2_type' => 'dataElement'
                            ];
                            // Add original option set if it exists
                            if (!empty($de['optionSet'])) {
                                $result['optionSets'][$de['optionSet']['id']] = $de['optionSet'];
                            }
                        }
                    }
                }
            }
        } else {
            throw new Exception("Invalid program type for tracker domain.");
        }
    } elseif ($domain === 'aggregate') { // Aggregate Program (Dataset)
        // MODIFIED: Get dataset details, including categoryCombo for data elements
        $datasetInfo = dhis2_get('/api/dataSets/' . $programId . '?fields=id,name,dataSetElements[dataElement[id,name,optionSet[id,name],categoryCombo[id,name]]]', $instance);
        $result['program'] = [
            'id' => $datasetInfo['id'],
            'name' => $datasetInfo['name']
        ];

        // Get data elements
        if (!empty($datasetInfo['dataSetElements'])) {
            foreach ($datasetInfo['dataSetElements'] as $dse) {
                $de = $dse['dataElement'];
                $result['dataElements'][$de['id']] = [
                    'name' => $de['name'],
                    'optionSet' => $de['optionSet'] ?? null,
                    'categoryCombo' => $de['categoryCombo'] ?? null, // Capture categoryCombo
                    'dhis2_id' => $de['id'],
                    'dhis2_type' => 'dataElement'
                ];
                // Add original option set if it exists
                if (!empty($de['optionSet'])) {
                    $result['optionSets'][$de['optionSet']['id']] = $de['optionSet'];
                }
            }
        }
    }

    // --- LOGIC FOR HANDLING DHIS2 CATEGORY COMBINATIONS ---
    // This will generate "synthetic" option sets for category combinations
    // These will be used for aggregate data elements to define their options.
    // For event data elements, it just provides full details without altering their options.

    $processedCategoryCombos = []; // To avoid redundant API calls for shared category combos

    // Iterate through data elements to find those with category combos
    foreach ($result['dataElements'] as $deId => &$element) {
        if (!empty($element['categoryCombo']['id'])) {
            $categoryComboId = $element['categoryCombo']['id'];
            $categoryComboName = $element['categoryCombo']['name'];

            // Only fetch category combo details if not already processed
            if (!isset($processedCategoryCombos[$categoryComboId])) {
                // Fetch details of the category combination including its categoryOptionCombos
                $categoryComboFullDetails = dhis2_get('/api/categoryCombos/' . $categoryComboId . '?fields=id,name,categoryOptionCombos[id,name,code]', $instance);
                $processedCategoryCombos[$categoryComboId] = $categoryComboFullDetails;
            } else {
                $categoryComboFullDetails = $processedCategoryCombos[$categoryComboId];
            }


            if (!empty($categoryComboFullDetails['categoryOptionCombos'])) {
                $syntheticOptionSetId = 'dhis2_cc_' . $categoryComboId . '_coc_optset'; // Unique ID for COC option set

                // Create the synthetic option set structure for Category Option Combinations (COCs)
                $syntheticOptionSet = [
                    'id' => $syntheticOptionSetId,
                    'name' => $categoryComboName . ' Combinations', // e.g., "Gender by Age Group Combinations"
                    'options' => $categoryComboFullDetails['categoryOptionCombos']
                ];

                // Add this synthetic option set to the main optionSets collection
                $result['optionSets'][$syntheticOptionSetId] = $syntheticOptionSet;

                // For AGGREGATE data elements:
                // Overwrite their existing optionSet with this synthetic one (Category Combo as Option Set)
                if ($domain === 'aggregate') {
                    $element['optionSet'] = [
                        'id' => $syntheticOptionSetId,
                        'name' => $syntheticOptionSet['name']
                    ];
                    // Also clear any original options linked, as they are superseded by COCs
                    unset($element['options']);
                }
                // For EVENT data elements:
                // Keep their original 'optionSet' (if any)
                // The full category combo details are available in $element['categoryCombo']
                // for display or later export mapping, but they do NOT become the question's options.
            }
        }
    }

    // --- END CATEGORY COMBINATION LOGIC ---

    // Fetch actual option values for all *standard* option sets (if they haven't been fetched yet)
    // Synthetic ones already have their 'options' populated above.
    foreach ($result['optionSets'] as $optionSetId => &$optionSet) {
        // Only fetch if not already populated (synthetic) and not a synthetic ID
        if (!isset($optionSet['options']) && !str_starts_with($optionSetId, 'dhis2_cc_')) {
            $optionSetDetails = dhis2_get('/api/optionSets/' . $optionSetId . '?fields=id,name,options[id,name,code]', $instance);
            if (!empty($optionSetDetails['options'])) {
                $optionSet['options'] = $optionSetDetails['options'];
            }
        }
    }

    // Link options back to relevant data elements and attributes (ensure all elements have options if available)
    foreach ($result['dataElements'] as $deId => &$de) {
        if (!empty($de['optionSet']) && isset($result['optionSets'][$de['optionSet']['id']])) {
            $de['options'] = $result['optionSets'][$de['optionSet']['id']]['options'] ?? [];
        }
    }
    foreach ($result['attributes'] as $attrId => &$attr) {
        if (!empty($attr['optionSet']) && isset($result['optionSets'][$attr['optionSet']['id']])) {
            $attr['options'] = $result['optionSets'][$attr['optionSet']['id']]['options'] ?? [];
        }
    }

    return $result;
}

// Check if this is an AJAX request for DHIS2 form content
if (isset($_GET['ajax']) && $_GET['ajax'] == 1 && $_GET['survey_source'] == 'dhis2') {
    // Clear any previous output buffer to ensure only the desired HTML is sent
    ob_clean();
    ?>
    <form method="GET" action="" class="p-3 rounded bg-light shadow-sm">
      <input type="hidden" name="survey_source" value="dhis2">
      <div class="row mb-4">
        <div class="col-md-4">
          <div class="form-group mb-3">
            <label class="form-control-label">Select DHIS2 Instance</label>
            <select name="dhis2_instance" class="form-control" id="dhis2-instance-select">
              <option value="">-- Select Instance --</option>
              <?php
                try {
                    $jsonConfig = getLocalDHIS2Config();
                    foreach ($jsonConfig as $key => $config) : ?>
                    <option value="<?= htmlspecialchars($key) ?>" <?= (isset($_GET['dhis2_instance']) && $_GET['dhis2_instance'] == $key) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($key) ?>
                    </option>
                    <?php endforeach;
                } catch (Exception $e) {
                    echo '<option value="">Error: ' . htmlspecialchars($e->getMessage()) . '</option>';
                }
                ?>
            </select>
          </div>
        </div>

        <?php if (isset($_GET['dhis2_instance']) && !empty($_GET['dhis2_instance'])): ?>
        <div class="col-md-4">
          <div class="form-group mb-3">
            <label class="form-control-label">Select Domain Type</label>
            <select name="domain" class="form-control" id="domain-select">
              <option value="">-- Select Domain --</option>
              <option value="tracker" <?= (isset($_GET['domain']) && $_GET['domain'] == 'tracker') ? 'selected' : '' ?>>Tracker</option>
              <option value="aggregate" <?= (isset($_GET['domain']) && $_GET['domain'] == 'aggregate') ? 'selected' : '' ?>>Aggregate</option>
            </select>
          </div>
        </div>
        <?php endif; ?>

        <?php if (isset($_GET['dhis2_instance']) && !empty($_GET['dhis2_instance']) &&
             isset($_GET['domain']) && $_GET['domain'] == 'tracker'): // Only show program type if domain is tracker ?>
        <div class="col-md-4">
          <div class="form-group mb-3">
            <label class="form-control-label">Select Program Type</label>
            <select name="program_type" class="form-control" id="program-type-select">
              <option value="">-- Select Program Type --</option>
              <option value="event" <?= (isset($_GET['program_type']) && $_GET['program_type'] == 'event') ? 'selected' : '' ?>>Event Program</option>
              <option value="tracker" <?= (isset($_GET['program_type']) && $_GET['program_type'] == 'tracker') ? 'selected' : '' ?>>Tracker Program</option>
            </select>
          </div>
        </div>
        <?php endif; ?>


        <?php if (isset($_GET['dhis2_instance']) && !empty($_GET['dhis2_instance']) &&
             isset($_GET['domain']) && !empty($_GET['domain']) &&
             (($_GET['domain'] == 'tracker' && isset($_GET['program_type']) && !empty($_GET['program_type'])) || $_GET['domain'] == 'aggregate')): ?>
        <div class="col-md-4">
          <div class="form-group mb-3">
            <label class="form-control-label">
              <?php
                if ($_GET['domain'] == 'tracker') {
                    echo ($_GET['program_type'] == 'tracker' ? 'Select Tracker Program' : 'Select Event Program');
                } else {
                    echo 'Select Dataset';
                }
              ?>
            </label>
            <select name="program_id" class="form-control" id="program-select">
              <option value="">-- Select
                <?php
                if ($_GET['domain'] == 'tracker') {
                    echo ($_GET['program_type'] == 'tracker' ? 'Tracker Program' : 'Event Program');
                } else {
                    echo 'Dataset';
                }
                ?>
                --</option>
              <?php
                try {
                    $programs = [];
                    if ($_GET['domain'] == 'tracker' && isset($_GET['program_type'])) {
                        $programs = getPrograms($_GET['dhis2_instance'], $_GET['program_type']);
                    } elseif ($_GET['domain'] == 'aggregate') {
                        $programs = getDatasets($_GET['dhis2_instance']);
                    }


                    foreach ($programs as $program) : ?>
                    <option value="<?= htmlspecialchars($program['id']) ?>"
                      <?= (isset($_GET['program_id']) && $_GET['program_id'] == $program['id']) ? 'selected' : '' ?>>
                      <?= htmlspecialchars($program['name']) ?>
                    </option>
                    <?php endforeach;
                } catch (Exception $e) {
                    echo '<option value="">Error: ' . htmlspecialchars($e->getMessage()) . '</option>';
                }
                ?>
            </select>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </form>
    <?php
    // Display program preview if all selections are made
    if (isset($_GET['dhis2_instance']) && !empty($_GET['dhis2_instance']) &&
        isset($_GET['domain']) && !empty($_GET['domain']) &&
        isset($_GET['program_id']) && !empty($_GET['program_id']) &&
        (($_GET['domain'] == 'tracker' && isset($_GET['program_type']) && !empty($_GET['program_type'])) || $_GET['domain'] == 'aggregate')) {

        try {
            $programDetails = getProgramDetails(
                $_GET['dhis2_instance'],
                $_GET['domain'],
                $_GET['program_id'],
                ($_GET['domain'] == 'tracker' ? $_GET['program_type'] : null)
            );

            if ($programDetails['program']) {
                ?>
                <div class="program-preview shadow-sm mb-4">
                  <h3 class="mb-3 text-primary">Program Preview: <?= htmlspecialchars($programDetails['program']['name']) ?></h3>
                  <p><strong>Domain:</strong> <?= htmlspecialchars(ucfirst($_GET['domain'])) ?></p>
                  <?php if ($_GET['domain'] == 'tracker'): ?>
                  <p><strong>Program Type:</strong> <?= htmlspecialchars(ucfirst($_GET['program_type'])) ?></p>
                  <?php endif; ?>

                  <?php if (!empty($programDetails['dataElements'])): ?>
                  <div class="preview-section">
                    <h4>Data Elements</h4>
                    <?php foreach ($programDetails['dataElements'] as $deId => $element): ?>
                      <div class="preview-item">
                        <strong><?= htmlspecialchars($element['name']) ?></strong>
                        <?php
                        // Display option set if exists (either regular or synthetic COC options)
                        if (!empty($element['optionSet'])):
                            $isCategoryComboOptionSet = str_starts_with($element['optionSet']['id'], 'dhis2_cc_');
                            $optionSetName = htmlspecialchars($element['optionSet']['name']);
                            ?>
                          <div>
                            <small>
                                Option Set: <?= $optionSetName ?>
                                <?php if ($isCategoryComboOptionSet): ?>
                                    (from Category Combination)
                                <?php endif; ?>
                            </small>
                            <?php if (!empty($element['options'])): ?>
                              <div class="mt-2">
                                <?php foreach ($element['options'] as $option): ?>
                                  <span class="option-item"><?= htmlspecialchars($option['name']) ?></span>
                                <?php endforeach; ?>
                              </div>
                            <?php endif; ?>
                          </div>
                        <?php endif; ?>

                        <?php
                        // Display Associated Category Combo for Event Programs if not default
                        // Aggregate's CC is now the option set itself, so no need for 'Associated Category Combo'
                        if (($_GET['domain'] == 'tracker' && $_GET['program_type'] == 'event') &&
                            !empty($element['categoryCombo']) &&
                            $element['categoryCombo']['name'] !== 'default' // Only show if not 'default'
                            ):
                        ?>
                          <div>
                            <small>Associated Category Combination: <?= htmlspecialchars($element['categoryCombo']['name']) ?></small>
                          </div>
                        <?php endif; ?>
                      </div>
                    <?php endforeach; ?>
                  </div>
                  <?php endif; ?>

                  <?php if (!empty($programDetails['attributes']) && $_GET['domain'] == 'tracker' && $_GET['program_type'] == 'tracker'): ?>
                  <div class="preview-section">
                    <h4>Tracked Entity Attributes</h4>
                    <?php foreach ($programDetails['attributes'] as $attrId => $attr): ?>
                      <div class="preview-item">
                        <strong><?= htmlspecialchars($attr['name']) ?></strong>
                        <?php if (!empty($attr['optionSet'])): ?>
                          <div>
                            <small>Option Set: <?= htmlspecialchars($attr['optionSet']['name']) ?></small>
                            <?php if (!empty($attr['options'])): ?>
                              <div class="mt-2">
                                <?php foreach ($attr['options'] as $option): ?>
                                  <span class="option-item"><?= htmlspecialchars($option['name']) ?></span>
                                <?php endforeach; ?>
                              </div>
                            <?php endif; ?>
                          </div>
                        <?php endif; ?>
                      </div>
                    <?php endforeach; ?>
                  </div>
                  <?php endif; ?>

                  <form method="POST" action="" class="mt-4">
                    <input type="hidden" name="dhis2_instance" value="<?= htmlspecialchars($_GET['dhis2_instance']) ?>">
                    <input type="hidden" name="domain" value="<?= htmlspecialchars($_GET['domain']) ?>">
                    <input type="hidden" name="program_id" value="<?= htmlspecialchars($_GET['program_id']) ?>">
                    <input type="hidden" name="program_name" value="<?= htmlspecialchars($programDetails['program']['name']) ?>">
                    <input type="hidden" name="program_type" value="<?= htmlspecialchars($_GET['program_type'] ?? '') ?>">

                    <input type="hidden" name="data_elements" value="<?= htmlspecialchars(json_encode($programDetails['dataElements'])) ?>">
                    <input type="hidden" name="attributes" value="<?= htmlspecialchars(json_encode($programDetails['attributes'])) ?>">
                    <input type="hidden" name="option_sets" value="<?= htmlspecialchars(json_encode($programDetails['optionSets'])) ?>">
                    <div class="text-center mt-4">
                      <button type="submit" name="create_survey" class="btn btn-primary action-btn shadow">
                        <i class="fas fa-sync-alt me-2"></i> Create Survey from DHIS2
                      </button>
                    </div>
                  </form>
                </div>
                <?php
            }
        } catch (Exception $e) {
            echo '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
    ?>
    <div class="text-center mt-3">
      <a href="sb.php" class="btn btn-secondary action-btn shadow">
        <i class="fas fa-arrow-left me-2"></i> Back
      </a>
    </div>
    <?php
    // Stop further processing for AJAX request
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Create New Survey</title>
  <link href="argon-dashboard-master/assets/css/nucleo-icons.css" rel="stylesheet">
  <link href="argon-dashboard-master/assets/css/nucleo-svg.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
  <link href="argon-dashboard-master/assets/css/argon-dashboard.min.css" rel="stylesheet">
  <style>
    /* Custom styles to apply #1e3c72 and ensure coordination */
    :root {
        --primary-color: #1e3c72; /* Your desired primary color */
        --primary-hover-color: #162c57; /* A slightly darker shade for hover */
        --primary-light-color: #3b5a9a; /* A lighter shade for text/borders */
    }

    body {
      font-family: Arial, sans-serif;
      background-color: #f9f9f9;
      margin: 0;
      padding: 0;
    }
    h1, h2 {
      color: var(--primary-color);
      margin-bottom: 30px;
    }
    .card-header.bg-gradient-primary {
      background-image: linear-gradient(310deg, var(--primary-color) 0%, var(--primary-light-color) 100%) !important;
    }
    .btn-outline-primary {
      color: var(--primary-color);
      border-color: var(--primary-color);
    }
    .btn-outline-primary:hover,
    .btn-outline-primary:focus {
      background-color: var(--primary-color);
      color: #fff;
      border-color: var(--primary-color);
    }
    .btn-primary {
      background-color: var(--primary-color);
      border-color: var(--primary-color);
    }
    .btn-primary:hover,
    .btn-primary:focus {
      background-color: var(--primary-hover-color);
      border-color: var(--primary-hover-color);
    }
    .text-primary {
      color: var(--primary-color) !important;
    }
    .preview-section h4 {
      border-bottom: 1px solid #eee;
      padding-bottom: 10px;
      margin-bottom: 15px;
      color: var(--primary-color); /* Apply primary color to section headers */
    }
    /* Rest of your existing styles */
    .program-preview {
      background-color: #fff;
      border-radius: 10px;
      padding: 20px;
      box-shadow: 0 0 15px rgba(0,0,0,0.1);
      margin-bottom: 30px;
    }
    .preview-section {
      margin-bottom: 25px;
    }
    .preview-item {
      padding: 10px;
      border-radius: 5px;
      background-color: #f8f9fe;
      margin-bottom: 10px;
    }
    .preview-item:hover {
      background-color: #e9ecef;
    }
  
    .option-item {
      display: inline-block;
      background-color: #e9ecef;
      padding: 3px 8px;
      border-radius: 4px;
      margin-right: 5px;
      margin-bottom: 5px;
      font-size: 12px;
    }
    .action-btn {
      font-size: 18px;
      padding: 15px 25px;
      width: 100%;
      max-width: 400px;
      display: block;
      margin: 0 auto;
    }
    .alert {
      margin-bottom: 20px;
    }
  </style>
</head>
<body>

  <?php include 'components/aside.php'; ?>

  <main class="main-content position-relative border-radius-lg">
    <?php include 'components/navbar.php'; ?>

    <div class="container-fluid py-4">
      <div class="row justify-content-center">
        <div class="col-12 col-lg-10">
          <div class="card shadow-lg mb-5">
            <div class="card-header pb-0 text-center bg-gradient-primary text-white rounded-top">
              <h1 class="mb-1">
                <i class="fas fa-exclamation-circle me-2"></i> Create New Survey
              </h1>
              <p class="mb-0">Choose how you want to create your survey</p>
            </div>
            <div class="card-body px-4">

              <?php if ($success_message): ?>
                <div class="alert alert-success" role="alert" id="success-alert">
                  <?= htmlspecialchars($success_message) ?>
                </div>
                <script>
                  setTimeout(function() {
                    window.location.href = 'survey.php';
                  }, 2000);
                </script>
              <?php endif; ?>

              <?php if ($error_message): ?>
                <div class="alert alert-danger" role="alert">
                  <?= htmlspecialchars($error_message) ?>
                </div>
              <?php endif; ?>

              <?php if (!isset($_GET['survey_source'])): ?>
                <div class="row justify-content-center mb-4">
                  <div class="col-md-5 mb-3">
                    <form method="get" action="">
                      <input type="hidden" name="survey_source" value="local">
                      <button type="submit" class="w-100 btn btn-outline-primary py-4 shadow-sm" style="border-radius: 16px; border-width: 2px;">
                        <div class="mb-2" style="font-size: 2.5rem;">
                          <i class="fa-solid fa-pen-to-square"></i>
                        </div>
                        <div class="fw-bold" style="font-size: 1.3rem;">Local Survey</div>
                        <div class="text-secondary mt-2">Create a custom survey with your own questions</div>
                      </button>
                    </form>
                  </div>
                  <div class="col-md-5 mb-3">
                    <form method="get" action="">
                      <input type="hidden" name="survey_source" value="dhis2">
                      <button type="submit" class="w-100 btn btn-outline-primary py-4 shadow-sm" style="border-radius: 16px; border-width: 2px;">
                        <div class="mb-2" style="font-size: 2.5rem;">
                          <i class="fa-solid fa-database"></i>
                        </div>
                        <div class="fw-bold" style="font-size: 1.3rem;">DHIS2 Program/Dataset</div>
                        <div class="text-secondary mt-2">Import from DHIS2 program or dataset</div>
                      </button>
                    </form>
                  </div>
                </div>
                <div class="text-center mt-4">
                  <a href="survey.php" class="btn btn-secondary action-btn shadow">
                    <i class="fas fa-arrow-left me-2"></i> Back
                  </a>
                </div>
              <?php else: ?>

                <?php
                // LOCAL SURVEY CREATION
                if ($_GET['survey_source'] == 'local') :
                ?>
                  <div class="text-center mb-4">
                    <h2 class="mb-1">Local Survey Details</h2>
                    <div class="text-secondary mb-3">Enter the details for your local survey</div>
                  </div>
                  <form method="POST" action="" class="p-3 rounded bg-light shadow-sm">
                    <div class="row mb-4">
                      <div class="col-md-6">
                        <div class="form-group mb-3">
                          <label class="form-control-label">Survey Name <span class="text-danger">*</span></label>
                          <input type="text" name="local_survey_name" class="form-control" required>
                        </div>
                      </div>
                      <div class="col-md-6"></div>
                    </div>
                    <div class="row mb-4">
                      <div class="col-md-6">
                        <div class="form-group mb-3">
                          <label class="form-control-label">Start Date</label>
                          <input type="date" name="start_date" class="form-control">
                          <small class="text-muted">Defaults to today if not specified</small>
                        </div>
                      </div>
                      <div class="col-md-6">
                        <div class="form-group mb-3">
                          <label class="form-control-label">End Date</label>
                          <input type="date" name="end_date" class="form-control">
                          <small class="text-muted">Defaults to 6 months from start date</small>
                        </div>
                      </div>
                    </div>
                    <div class="form-check mb-4">
                      <input class="form-check-input" type="checkbox" name="is_active" id="activeSurvey" checked>
                      <label class="form-check-label" for="activeSurvey">
                        Active Survey
                      </label>
                    </div>

                    <div class="mb-3">
                      <button type="button" class="btn btn-outline-info shadow-sm" id="toggle-existing-questions">
                      <i class="fas fa-link"></i> Attach Existing Questions (optional)
                      </button>
                    </div>
                    <div id="existing-questions-section" style="display:none;">
                      <div class="mb-4">
                      <h5>Attach Existing Questions</h5>
                      <input type="text" id="search-existing-questions" class="form-control mb-2" placeholder="Search questions...">
                      <div id="existing-questions-list" style="max-height: 250px; overflow-y: auto; border: 1px solid #eee; border-radius: 8px; padding: 10px; background: #f8f9fa;">
                        <?php
                        // Fetch existing questions with their option sets
                        try {
                          $conn = new PDO("mysql:host=localhost;dbname=fbtv3;charset=utf8", "root", "root");
                          $stmt = $conn->query("SELECT q.id, q.label, q.question_type, q.is_required, q.option_set_id, os.name AS option_set_name
                                    FROM question q
                                    LEFT JOIN option_set os ON q.option_set_id = os.id
                                    ORDER BY q.label ASC");
                          $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        } catch (Exception $e) {
                          $questions = [];
                          echo '<div class="alert alert-danger">Could not load questions: ' . htmlspecialchars($e->getMessage()) . '</div>';
                        }
                        foreach ($questions as $q):
                        ?>
                        <div class="form-check mb-2 existing-question-item">
                          <input class="form-check-input" type="checkbox" name="attach_questions[]" value="<?= $q['id'] ?>" id="q<?= $q['id'] ?>">
                          <label class="form-check-label" for="q<?= $q['id'] ?>">
                          <strong><?= htmlspecialchars($q['label']) ?></strong>
                          <span class="badge bg-secondary ms-2"><?= htmlspecialchars($q['question_type']) ?></span>
                          <?php if ($q['option_set_name']): ?>
                            <span class="badge bg-info ms-2"><?= htmlspecialchars($q['option_set_name']) ?></span>
                          <?php endif; ?>
                          </label>
                          <?php
                          // Show option set values if any
                          if ($q['option_set_id']) {
                            $optStmt = $conn->prepare("SELECT option_value FROM option_set_values WHERE option_set_id = ?");
                            $optStmt->execute([$q['option_set_id']]);
                            $opts = $optStmt->fetchAll(PDO::FETCH_COLUMN);
                            if ($opts) {
                            echo '<div class="mt-1" style="font-size:12px;">';
                            foreach ($opts as $opt) {
                              echo '<span class="option-item">'.htmlspecialchars($opt).'</span> ';
                            }
                            echo '</div>';
                            }
                          }
                          ?>
                        </div>
                        <?php endforeach; ?>
                      </div>
                      <small class="text-muted">Scroll and search to select questions to attach to this survey.</small>
                      </div>
                    </div>
                    <script>
                      // Search/filter for existing questions
                      document.addEventListener('DOMContentLoaded', function() {
                      var searchInput = document.getElementById('search-existing-questions');
                      var items = document.querySelectorAll('.existing-question-item');
                      if (searchInput) {
                        searchInput.addEventListener('input', function() {
                        var val = this.value.trim().toLowerCase();
                        items.forEach(function(item) {
                          var text = item.textContent.toLowerCase();
                          item.style.display = text.indexOf(val) !== -1 ? '' : 'none';
                        });
                        });
                      }
                      });
                    </script>

                    <div class="mb-3">
                      <button type="button" class="btn btn-outline-info shadow-sm" id="toggle-new-questions">
                        <i class="fas fa-plus"></i> Add New Questions (optional)
                      </button>
                    </div>
                    <div id="questions-section" style="display:none;">
                      <div id="questions-container">
                        <div class="row mb-2 question-row">
                          <div class="col-md-6">
                            <input type="text" name="questions[0][label]" class="form-control" placeholder="Question label">
                          </div>
                          <div class="col-md-4">
                            <select name="questions[0][type]" class="form-control">
                              <option value="text">Text</option>
                              <option value="select">Select</option>
                              <option value="number">Number</option>
                              <option value="date">Date</option>
                              <option value="boolean">Yes/No</option>
                            </select>
                          </div>
                          <div class="col-md-2">
                            <button type="button" class="btn btn-danger btn-sm remove-question" style="display:none;">Remove</button>
                          </div>
                        </div>
                      </div>
                      <div class="mb-3">
                        <button type="button" class="btn btn-secondary shadow-sm" id="add-question-btn">
                          <i class="fas fa-plus"></i> Add Question
                        </button>
                      </div>
                    </div>

                    <div class="text-center mt-4">
                      <button type="submit" name="create_local_survey" class="btn btn-primary action-btn shadow">
                        <i class="fas fa-check me-2"></i> Create Survey
                      </button>
                    </div>
                  </form>
                  <div class="text-center mt-3">
                    <a href="survey.php" class="btn btn-secondary action-btn shadow">
                      <i class="fas fa-arrow-left me-2"></i> Back
                    </a>
                  </div>
                  <script>
                    // Toggle sections
                    document.getElementById('toggle-existing-questions').onclick = function() {
                      const sec = document.getElementById('existing-questions-section');
                      sec.style.display = sec.style.display === 'none' ? '' : 'none';
                    };
                    document.getElementById('toggle-new-questions').onclick = function() {
                      const sec = document.getElementById('questions-section');
                      sec.style.display = sec.style.display === 'none' ? '' : 'none';
                    };

                    // Add/remove new questions
                    let qIndex = 1;
                    document.getElementById('add-question-btn').onclick = function() {
                      const container = document.getElementById('questions-container');
                      const row = document.createElement('div');
                      row.className = 'row mb-2 question-row';
                      row.innerHTML = `
                        <div class="col-md-6">
                          <input type="text" name="questions[${qIndex}][label]" class="form-control" placeholder="Question label">
                        </div>
                        <div class="col-md-4">
                          <select name="questions[${qIndex}][type]" class="form-control">
                            <option value="text">Text</option>
                            <option value="select">Select</option>
                            <option value="number">Number</option>
                            <option value="date">Date</option>
                            <option value="boolean">Yes/No</option>
                          </select>
                        </div>
                        <div class="col-md-2">
                          <button type="button" class="btn btn-danger btn-sm remove-question">Remove</button>
                        </div>
                      `;
                      container.appendChild(row);
                      qIndex++;
                      updateRemoveButtons();
                    };
                    function updateRemoveButtons() {
                      document.querySelectorAll('.remove-question').forEach(btn => {
                        btn.style.display = document.querySelectorAll('.question-row').length > 1 ? '' : 'none';
                        btn.onclick = function() {
                          btn.closest('.question-row').remove();
                          updateRemoveButtons();
                        };
                      });
                    }
                    updateRemoveButtons();
                  </script>
                <?php
                // DHIS2 SURVEY CREATION
                elseif ($_GET['survey_source'] == 'dhis2') :
                ?>
                  <div class="text-center mb-4">
                    <h2 class="mb-1">DHIS2 Program/Dataset</h2>
                    <div class="text-secondary mb-3">Select your DHIS2 instance, domain, and program/dataset</div>
                  </div>
                  <div id="dhis2-survey-container">
                    </div>
                  <script>
                  // AJAX loader for DHIS2 survey creation
                  function loadDHIS2SurveyForm(params = {}) {
                    let url = '<?= basename($_SERVER['PHP_SELF']) ?>?survey_source=dhis2';
                    if (params.dhis2_instance) url += '&dhis2_instance=' + encodeURIComponent(params.dhis2_instance);
                    if (params.domain) url += '&domain=' + encodeURIComponent(params.domain);
                    // Add program_type to the URL parameters
                    if (params.program_type) url += '&program_type=' + encodeURIComponent(params.program_type);
                    if (params.program_id) url += '&program_id=' + encodeURIComponent(params.program_id);


                    document.getElementById('dhis2-survey-container').innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div><p class="mt-3">Loading DHIS2 details...</p></div>';
                    fetch(url + '&ajax=1')
                    .then(res => res.text())
                    .then(html => {
                      document.getElementById('dhis2-survey-container').innerHTML = html;
                      // Re-attach event listeners for selects within the newly loaded content
                      let instanceSel = document.getElementById('dhis2-instance-select');
                      if (instanceSel) instanceSel.onchange = function() {
                        loadDHIS2SurveyForm({dhis2_instance: this.value});
                      };
                      let domainSel = document.getElementById('domain-select');
                      if (domainSel) domainSel.onchange = function() {
                        loadDHIS2SurveyForm({
                          dhis2_instance: document.getElementById('dhis2-instance-select').value,
                          domain: this.value
                        });
                      };
                      // New: Event listener for Program Type Select
                      let programTypeSel = document.getElementById('program-type-select');
                      if (programTypeSel) programTypeSel.onchange = function() {
                        loadDHIS2SurveyForm({
                          dhis2_instance: document.getElementById('dhis2-instance-select').value,
                          domain: document.getElementById('domain-select').value,
                          program_type: this.value // Pass the selected program type
                        });
                      };
                      let progSel = document.getElementById('program-select');
                      if (progSel) progSel.onchange = function() {
                        loadDHIS2SurveyForm({
                          dhis2_instance: document.getElementById('dhis2-instance-select').value,
                          domain: document.getElementById('domain-select').value,
                          program_type: document.getElementById('program-type-select') ? document.getElementById('program-type-select').value : null, // Ensure program_type is passed
                          program_id: this.value
                        });
                      };
                    })
                    .catch(error => {
                        console.error('Error loading DHIS2 survey form:', error);
                        document.getElementById('dhis2-survey-container').innerHTML = '<div class="alert alert-danger">Failed to load DHIS2 form. Please try again.</div>';
                    });
                  }
                  // Initial load of the DHIS2 form when the page loads
                  loadDHIS2SurveyForm({
                    <?php if (isset($_GET['dhis2_instance'])): ?>dhis2_instance: "<?= htmlspecialchars($_GET['dhis2_instance']) ?>",<?php endif; ?>
                    <?php if (isset($_GET['domain'])): ?>domain: "<?= htmlspecialchars($_GET['domain']) ?>",<?php endif; ?>
                    <?php if (isset($_GET['program_type'])): ?>program_type: "<?= htmlspecialchars($_GET['program_type']) ?>",<?php endif; ?>
                    <?php if (isset($_GET['program_id'])): ?>program_id: "<?= htmlspecialchars($_GET['program_id']) ?>",<?php endif; ?>
                  });
                  </script>
                <?php endif; ?>

              <?php endif; ?>

            </div>
          </div>
        </div>
      </div>
    </div>

    <?php include 'components/fixednav.php'; ?>
  </main>

  <script src="argon-dashboard-master/assets/js/core/popper.min.js"></script>
  <script src="argon-dashboard-master/assets/js/core/bootstrap.min.js"></script>
  <script src="argon-dashboard-master/assets/js/plugins/perfect-scrollbar.min.js"></script>
  <script src="argon-dashboard-master/assets/js/plugins/smooth-scrollbar.min.js"></script>
  <script src="argon-dashboard-master/assets/js/argon-dashboard.min.js"></script>

</body>
</html>