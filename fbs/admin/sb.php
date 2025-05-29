<?php
session_start();

// Redirect to login if not logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit();
}

require 'connect.php';
require 'dhis2/dhis2_shared.php';
require 'dhis2/dhis2_get_function.php';

// Handle form submission for creating survey
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_survey'])) {
    try {
        $conn = new PDO("mysql:host=localhost;dbname=fbtv3;charset=utf8", "root", "root");
        // Begin transaction
        $conn->beginTransaction();
        
        // Check if survey already exists by program_dataset (UID) first
        $stmt = $conn->prepare("SELECT id FROM survey WHERE program_dataset = ?");
        $stmt->execute([$_POST['program_id']]);
        $existingSurvey = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existingSurvey) {
            $conn->rollBack();
            $error_message = "A survey for this program/dataset (UID) already exists.";
            goto end_processing;
        }

        // If not found by UID, check by name
        $stmt = $conn->prepare("SELECT id FROM survey WHERE name = ?");
        $stmt->execute([$_POST['program_name']]);
        $existingSurvey = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existingSurvey) {
            $conn->rollBack();
            $error_message = "A survey with name '".htmlspecialchars($_POST['program_name'])."' already exists.";
            goto end_processing;
        }
        
        // 1. Create survey entry (handles both local and dhis2)
        if (isset($_POST['dhis2_instance']) && isset($_POST['program_id'])) {
            // DHIS2 survey
            $stmt = $conn->prepare("INSERT INTO survey (name, type, dhis2_instance, program_dataset) VALUES (?, 'dhis2', ?, ?)");
            $stmt->execute([$_POST['program_name'], $_POST['dhis2_instance'], $_POST['program_id']]);
        } else {
            // Local survey with default instance and program_dataset
            $stmt = $conn->prepare("INSERT INTO survey (name, type, dhis2_instance, program_dataset) VALUES (?, 'local', ?, ?)");
            $stmt->execute([
              $_POST['local_survey_name'] ?? '', // fallback if not set
              'UiO',
              'GfDOw2s4mCj'
            ]);
        }
        $surveyId = $conn->lastInsertId();
        
        // 2. Process data elements and create questions
        $dataElements = json_decode($_POST['data_elements'], true);
        $position = 1;
        
        $processQuestion = function($element, $elementId, $surveyId, &$position, $conn) {
    // Determine question type based on option set
    $questionType = !empty($element['optionSet']) ? 'select' : 'text';
    
    // Create question
    $stmt = $conn->prepare("INSERT INTO question (label, question_type, is_required) VALUES (?, ?, 1)");
    $stmt->execute([$element['name'], $questionType]);
    $questionId = $conn->lastInsertId();
    
    // Add question to survey
    $stmt = $conn->prepare("INSERT INTO survey_question (survey_id, question_id, position) VALUES (?, ?, ?)");
    $stmt->execute([$surveyId, $questionId, $position]);
    $position++;
    
    // Map question to DHIS2 element
    $stmt = $conn->prepare("INSERT INTO question_dhis2_mapping (question_id, dhis2_dataelement_id, dhis2_option_set_id) VALUES (?, ?, ?)");
    $stmt->execute([
        $questionId, 
        $elementId, 
        !empty($element['optionSet']) ? $element['optionSet']['id'] : null
    ]);
    
    // Process option sets if exists
    if (!empty($element['optionSet']) && !empty($element['options'])) {
        // Check if option set already exists by name
        $stmt = $conn->prepare("SELECT id FROM option_set WHERE name = ?");
        $stmt->execute([$element['optionSet']['name']]);
        $existingOptionSet = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existingOptionSet) {
            // Use existing option set
            $optionSetId = $existingOptionSet['id'];
        } else {
            // Create new option set
            $stmt = $conn->prepare("INSERT INTO option_set (name) VALUES (?)");
            $stmt->execute([$element['optionSet']['name']]);
            $optionSetId = $conn->lastInsertId();

            // Add option values
            foreach ($element['options'] as $option) {
                // Check if this exact option value already exists in this option set
                $stmt = $conn->prepare("SELECT id FROM option_set_values 
                                      WHERE option_set_id = ? AND option_value = ?");
                $stmt->execute([$optionSetId, $option['name']]);
                $existingOptionValue = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$existingOptionValue) {
                    // Check if the option value exists in ANY option set
                    $stmt = $conn->prepare("SELECT option_value FROM option_set_values 
                                          WHERE option_value = ? LIMIT 1");
                    $stmt->execute([$option['name']]);
                    $valueExists = $stmt->fetch();

                    if ($valueExists) {
                        // Option value exists elsewhere - just create new reference
                        $stmt = $conn->prepare("INSERT INTO option_set_values 
                                              (option_set_id, option_value) 
                                              VALUES (?, ?)");
                        $stmt->execute([$optionSetId, $option['name']]);
                    } else {
                        // Completely new option value
                        $stmt = $conn->prepare("INSERT INTO option_set_values 
                                              (option_set_id, option_value) 
                                              VALUES (?, ?)");
                        $stmt->execute([$optionSetId, $option['name']]);
                    }
                }
                
                // Map option to DHIS2 if code exists
                if (!empty($option['code'])) {
                    // Check if mapping exists
                    $stmt = $conn->prepare("SELECT id FROM dhis2_option_set_mapping 
                                          WHERE local_value = ? 
                                          AND dhis2_option_code = ? 
                                          AND dhis2_option_set_id = ?");
                    $stmt->execute([
                        $option['name'], 
                        $option['code'], 
                        $element['optionSet']['id']
                    ]);
                    
                    if (!$stmt->fetch()) {
                        try {
                            $stmt = $conn->prepare("INSERT INTO dhis2_option_set_mapping 
                                                  (local_value, dhis2_option_code, dhis2_option_set_id) 
                                                  VALUES (?, ?, ?)");
                            $stmt->execute([
                                $option['name'], 
                                $option['code'], 
                                $element['optionSet']['id']
                            ]);
                        } catch (PDOException $e) {
                            if ($e->errorInfo[1] != 1062) { // Ignore duplicate entry errors
                                throw $e;
                            }
                        }
                    }
                }
            }
        }

        // Update question with option set id
        $stmt = $conn->prepare("UPDATE question SET option_set_id = ? WHERE id = ?");
        $stmt->execute([$optionSetId, $questionId]);
    }
};
        
        // Process data elements
        foreach ($dataElements as $deId => $element) {
            $processQuestion($element, $deId, $surveyId, $position, $conn);
        }
        
        // Process attributes if tracker program (now as questions)
        if (isset($_POST['attributes']) && !empty($_POST['attributes'])) {
            $attributes = json_decode($_POST['attributes'], true);
            
            foreach ($attributes as $attrId => $attr) {
                $processQuestion($attr, $attrId, $surveyId, $position, $conn);
            }
        }
        
        $conn->commit();
        $success_message = "Survey successfully created from DHIS2 program";
    } catch (Exception $e) {
        $conn->rollBack();
        $error_message = "Error creating survey: " . $e->getMessage();
    }
}

end_processing:

/**
 * Get all active DHIS2 instances using getDhis2Config from dhis2_shared.php.
 * Returns an array of instance configs keyed by their 'key'.
 */
function getLocalDHIS2Config() {
  // Use the shared function to fetch all active instances from the DB
  $instances = [];
  $dbHost = 'localhost';
  $dbUser = 'root';
  $dbPass = 'root';
  $dbName = 'fbtv3';

  $mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
  if ($mysqli->connect_errno) {
    throw new Exception("Database connection failed: " . $mysqli->connect_error);
  }

  $result = $mysqli->query("SELECT `key` FROM dhis2_instances WHERE status = 1");
  while ($row = $result->fetch_assoc()) {
    $config = getDhis2Config($row['key']);
    if ($config) {
      $instances[$row['key']] = $config;
    }
  }
  $result->free();
  $mysqli->close();

  return $instances;
}

function getTrackerPrograms($instance) {
    $programs = dhis2_get('/api/programs?fields=id,name', $instance);
    return $programs['programs'] ?? [];
}

function getDatasets($instance) {
    $datasets = dhis2_get('/api/dataSets?fields=id,name', $instance);
    return $datasets['dataSets'] ?? [];
}

function getProgramDetails($instance, $domain, $programId) {
    $result = [
        'program' => null,
        'dataElements' => [],
        'attributes' => [],
        'optionSets' => []
    ];
    
    if ($domain === 'tracker') {
        // Get program details
        $programInfo = dhis2_get('/api/programs/'.$programId.'?fields=id,name,programStages[id,name,programStageDataElements[dataElement[id,name,optionSet[id,name]]]', $instance);
        $result['program'] = [
            'id' => $programInfo['id'],
            'name' => $programInfo['name']
        ];
        
        // Get data elements
        if (!empty($programInfo['programStages'])) {
            foreach ($programInfo['programStages'] as $stage) {
                if (isset($stage['programStageDataElements'])) {
                    foreach ($stage['programStageDataElements'] as $psde) {
                        $de = $psde['dataElement'];
                        $result['dataElements'][$de['id']] = [
                            'name' => $de['name'],
                            'optionSet' => $de['optionSet'] ?? null
                        ];
                        
                        // Check for option set
                        if (!empty($de['optionSet'])) {
                            $result['optionSets'][$de['optionSet']['id']] = $de['optionSet'];
                        }
                    }
                }
            }
        }
        
        // Get program attributes
        $programAttrs = dhis2_get('/api/programs/'.$programId.'?fields=programTrackedEntityAttributes[trackedEntityAttribute[id,name,optionSet[id,name]]]', $instance);
        if (!empty($programAttrs['programTrackedEntityAttributes'])) {
            foreach ($programAttrs['programTrackedEntityAttributes'] as $attr) {
                $tea = $attr['trackedEntityAttribute'];
                $result['attributes'][$tea['id']] = [
                    'name' => $tea['name'],
                    'optionSet' => $tea['optionSet'] ?? null
                ];
                
                // Check for option set
                if (!empty($tea['optionSet'])) {
                    $result['optionSets'][$tea['optionSet']['id']] = $tea['optionSet'];
                }
            }
        }
    } elseif ($domain === 'aggregate') {
        // Get dataset details
        $datasetInfo = dhis2_get('/api/dataSets/'.$programId.'?fields=id,name,dataSetElements[dataElement[id,name,optionSet[id,name]]]', $instance);
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
                    'optionSet' => $de['optionSet'] ?? null
                ];
                
                // Check for option set
                if (!empty($de['optionSet'])) {
                    $result['optionSets'][$de['optionSet']['id']] = $de['optionSet'];
                }
            }
        }
    }
    
    // Fetch option values for all option sets
    foreach ($result['optionSets'] as $optionSetId => &$optionSet) {
        $optionSetDetails = dhis2_get('/api/optionSets/'.$optionSetId.'?fields=id,name,options[id,name,code]', $instance);
        if (!empty($optionSetDetails['options'])) {
            $optionSet['options'] = $optionSetDetails['options'];
            
            // Add options to data elements and attributes
            foreach ($result['dataElements'] as $deId => &$de) {
                if (!empty($de['optionSet']) && $de['optionSet']['id'] === $optionSetId) {
                    $de['options'] = $optionSetDetails['options'];
                }
            }
            
            foreach ($result['attributes'] as $attrId => &$attr) {
                if (!empty($attr['optionSet']) && $attr['optionSet']['id'] === $optionSetId) {
                    $attr['options'] = $optionSetDetails['options'];
                }
            }
        }
    }
    
    return $result;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>DHIS2 Questions</title>
  <link href="argon-dashboard-master/assets/css/nucleo-icons.css" rel="stylesheet">
  <link href="argon-dashboard-master/assets/css/nucleo-svg.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
  <link href="argon-dashboard-master/assets/css/argon-dashboard.min.css" rel="stylesheet">
  <style>
    body {
      font-family: Arial, sans-serif;
      background-color: #f9f9f9;
      margin: 0;
      padding: 0;
    }
    h1 {
      color: #333;
      margin-bottom: 30px;
    }
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
    .preview-section h4 {
      border-bottom: 1px solid #eee;
      padding-bottom: 10px;
      margin-bottom: 15px;
      color: #5e72e4;
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
  
  <!-- Only include aside and navbar ONCE at the top level, not inside AJAX-loaded DHIS2 section -->
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

              <?php if (isset($success_message)): ?>
                <div class="alert alert-success" role="alert" id="success-alert">
                  <?= htmlspecialchars($success_message) ?>
                </div>
                <script>
                  setTimeout(function() {
                    window.location.href = 'survey.php';
                  }, 2000);
                </script>
              <?php endif; ?>

              <?php if (isset($error_message)): ?>
                <div class="alert alert-danger" role="alert">
                  <?= htmlspecialchars($error_message) ?>
                </div>
              <?php endif; ?>

              <?php if (!isset($_GET['survey_source'])): ?>
                <!-- Survey Source Selection Cards -->
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
                  <a href="survey.php" class="btn btn-danger action-btn shadow">
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

                    <!-- Toggle for Attach Existing Questions -->
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

                    <!-- Toggle for Add New Questions -->
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
                    // Handle local survey creation POST
                    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_local_survey'])) {
                      try {
                        $conn = new PDO("mysql:host=localhost;dbname=fbtv3;charset=utf8", "root", "root");
                        $conn->beginTransaction();
                        // Check for duplicate survey name
                        $stmt = $conn->prepare("SELECT id FROM survey WHERE name = ?");
                        $stmt->execute([$_POST['local_survey_name']]);
                        if ($stmt->fetch()) {
                          throw new Exception("A survey with this name already exists.");
                        }
                        // Insert survey
                        $stmt = $conn->prepare("INSERT INTO survey (name, type, start_date, end_date, is_active) VALUES (?, 'local', ?, ?, ?)");
                        $startDate = !empty($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-d');
                        $endDate = !empty($_POST['end_date']) ? $_POST['end_date'] : date('Y-m-d', strtotime($startDate . ' +6 months'));
                        $isActive = isset($_POST['is_active']) ? 1 : 0;
                        $stmt->execute([$_POST['local_survey_name'], $startDate, $endDate, $isActive]);
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
                            if (!empty($q['label'])) {
                              $stmt = $conn->prepare("INSERT INTO question (label, question_type, is_required) VALUES (?, ?, 1)");
                              $stmt->execute([$q['label'], $q['type']]);
                              $questionId = $conn->lastInsertId();
                              $stmt = $conn->prepare("INSERT INTO survey_question (survey_id, question_id, position) VALUES (?, ?, ?)");
                              $stmt->execute([$surveyId, $questionId, $position++]);
                            }
                          }
                        }
                        $conn->commit();
                        echo '<div class="alert alert-success mt-3" id="success-alert">Local survey created successfully.</div>';
                        echo '<script>setTimeout(function(){ window.location.href = "survey.php"; }, 2000);</script>';
                      } catch (Exception $e) {
                        if ($conn && $conn->inTransaction()) $conn->rollBack();
                        echo '<div class="alert alert-danger mt-3">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
                      }
                    }
                  ?>
                  <div class="text-center mt-3">
                    <a href="sb.php" class="btn btn-secondary action-btn shadow">
                      <i class="fas fa-arrow-left me-2"></i> Back
                    </a>
                  </div>
                <?php
                // DHIS2 SURVEY CREATION
                elseif ($_GET['survey_source'] == 'dhis2') :
                ?>
                  <div id="dhis2-survey-container"></div>
                  <script>
                  // AJAX loader for DHIS2 survey creation
                  function loadDHIS2SurveyForm(params = {}) {
                    let url = '<?= basename($_SERVER['PHP_SELF']) ?>?survey_source=dhis2';
                    if (params.dhis2_instance) url += '&dhis2_instance=' + encodeURIComponent(params.dhis2_instance);
                    if (params.domain) url += '&domain=' + encodeURIComponent(params.domain);
                    if (params.program_id) url += '&program_id=' + encodeURIComponent(params.program_id);

                    document.getElementById('dhis2-survey-container').innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div></div>';
                    fetch(url + '&ajax=1')
                    .then(res => res.text())
                    .then(html => {
                      // Remove aside and navbar if present
                      let aside = document.querySelector('aside');
                      if (aside) aside.style.display = 'none';
                      let navbar = document.querySelector('.main-content .navbar');
                      if (navbar) navbar.style.display = 'none';
                      document.getElementById('dhis2-survey-container').innerHTML = html;
                      // Attach event listeners for selects
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
                      let progSel = document.getElementById('program-select');
                      if (progSel) progSel.onchange = function() {
                      loadDHIS2SurveyForm({
                        dhis2_instance: document.getElementById('dhis2-instance-select').value,
                        domain: document.getElementById('domain-select').value,
                        program_id: this.value
                      });
                      };
                    });
                  }
                  <?php if (isset($_GET['ajax']) && $_GET['ajax'] == 1): ?>
                    // Do nothing (handled below)
                  <?php else: ?>
                    loadDHIS2SurveyForm({
                    <?php if (isset($_GET['dhis2_instance'])): ?>dhis2_instance: "<?= htmlspecialchars($_GET['dhis2_instance']) ?>",<?php endif; ?>
                    <?php if (isset($_GET['domain'])): ?>domain: "<?= htmlspecialchars($_GET['domain']) ?>",<?php endif; ?>
                    <?php if (isset($_GET['program_id'])): ?>program_id: "<?= htmlspecialchars($_GET['program_id']) ?>",<?php endif; ?>
                    });
                  <?php endif; ?>
                  </script>
                  <?php
                  // AJAX partial for DHIS2 form
                  if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
                  // DO NOT include aside.php or navbar.php here!
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
                         isset($_GET['domain']) && !empty($_GET['domain'])): ?>
                    <div class="col-md-4">
                      <div class="form-group mb-3">
                      <label class="form-control-label">
                        <?= $_GET['domain'] == 'tracker' ? 'Select Program' : 'Select Dataset' ?>
                      </label>
                      <select name="program_id" class="form-control" id="program-select">
                        <option value="">-- Select <?= $_GET['domain'] == 'tracker' ? 'Program' : 'Dataset' ?> --</option>
                        <?php 
                        try {
                          $programs = $_GET['domain'] == 'tracker' 
                            ? getTrackerPrograms($_GET['dhis2_instance']) 
                            : getDatasets($_GET['dhis2_instance']);
                            
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
                    isset($_GET['program_id']) && !empty($_GET['program_id'])) {
                    
                    try {
                      $programDetails = getProgramDetails(
                        $_GET['dhis2_instance'], 
                        $_GET['domain'], 
                        $_GET['program_id']
                      );
                      
                      if ($programDetails['program']) {
                        ?>
                        <div class="program-preview shadow-sm mb-4">
                          <h3 class="mb-3 text-primary">Program Preview: <?= htmlspecialchars($programDetails['program']['name']) ?></h3>
                          
                          <?php if (!empty($programDetails['dataElements'])): ?>
                          <div class="preview-section">
                          <h4>Data Elements</h4>
                          <?php foreach ($programDetails['dataElements'] as $deId => $element): ?>
                            <div class="preview-item">
                            <strong><?= htmlspecialchars($element['name']) ?></strong>
                            <?php if (!empty($element['optionSet'])): ?>
                              <div>
                              <small>Option Set: <?= htmlspecialchars($element['optionSet']['name']) ?></small>
                              <?php if (!empty($element['options'])): ?>
                                <div class="mt-2">
                                <?php foreach ($element['options'] as $option): ?>
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
                          
                          <?php if (!empty($programDetails['attributes']) && $_GET['domain'] == 'tracker'): ?>
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
                          <input type="hidden" name="data_elements" value="<?= htmlspecialchars(json_encode($programDetails['dataElements'])) ?>">
                          <?php if ($_GET['domain'] == 'tracker'): ?>
                            <input type="hidden" name="attributes" value="<?= htmlspecialchars(json_encode($programDetails['attributes'])) ?>">
                          <?php endif; ?>
                          
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
                  exit;
                  }
                  ?>
                <?php endif; ?>

              <?php endif; ?>

            </div>
          </div>
        </div>
      </div>
    </div>

    <?php include 'components/fixednav.php'; ?>
  </main>

  <!-- Argon Dashboard JS -->
  <script src="argon-dashboard-master/assets/js/core/popper.min.js"></script>
  <script src="argon-dashboard-master/assets/js/core/bootstrap.min.js"></script>
  <script src="argon-dashboard-master/assets/js/plugins/perfect-scrollbar.min.js"></script>
  <script src="argon-dashboard-master/assets/js/plugins/smooth-scrollbar.min.js"></script>
  <script src="argon-dashboard-master/assets/js/argon-dashboard.min.js"></script>

</body>
</html>