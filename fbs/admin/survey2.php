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

// Set default dates
$defaultStartDate = date('Y-m-d');
$defaultEndDate = date('Y-m-d', strtotime('+6 months'));

// Handle form submission for creating survey
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn = new PDO("mysql:host=localhost;dbname=fbtv3;charset=utf8", "root", "root");
        $conn->beginTransaction();
        
        if (isset($_POST['create_local_survey'])) {
            // Handle local survey creation
            $surveyName = trim($_POST['survey_name']);
            $startDate = !empty($_POST['start_date']) ? $_POST['start_date'] : $defaultStartDate;
            $endDate = !empty($_POST['end_date']) ? $_POST['end_date'] : $defaultEndDate;
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            $dhis2Instance = $_POST['dhis2_instance'] ?? null;

            // Check if survey with same name already exists
            $stmt = $conn->prepare("SELECT id FROM survey WHERE name = ?");
            $stmt->execute([$surveyName]);
            if ($stmt->fetch()) {
                throw new Exception("A survey with this name already exists.");
            }

            $stmt = $conn->prepare("INSERT INTO survey (name, type, start_date, end_date, is_active, dhis2_instance) 
                                  VALUES (?, 'local', ?, ?, ?, ?)");
            $stmt->execute([
                $surveyName,
                $startDate,
                $endDate,
                $isActive,
                $dhis2Instance
            ]);
            
            $surveyId = $conn->lastInsertId();
            $conn->commit();
            header("Location: survey.php?success=Local survey created successfully!");
            exit();
        }
        elseif (isset($_POST['create_dhis2_survey'])) {
            // Handle DHIS2 survey creation
            $programId = $_POST['program_id'];
            $programName = trim($_POST['program_name']);
            $dhis2Instance = $_POST['dhis2_instance'];

            // Check if survey already exists by program_dataset (UID)
            $stmt = $conn->prepare("SELECT id FROM survey WHERE program_dataset = ?");
            $stmt->execute([$programId]);
            if ($stmt->fetch()) {
                throw new Exception("A survey for this program/dataset already exists.");
            }

            // Check if survey with same name already exists
            $stmt = $conn->prepare("SELECT id FROM survey WHERE name = ?");
            $stmt->execute([$programName]);
            if ($stmt->fetch()) {
                throw new Exception("A survey with name '".htmlspecialchars($programName)."' already exists.");
            }
            
            // Create survey entry
            $stmt = $conn->prepare("INSERT INTO survey (name, type, dhis2_instance, program_dataset, start_date, end_date, is_active) 
                                  VALUES (?, 'dhis2', ?, ?, ?, ?, 1)");
            $stmt->execute([
                $programName,
                $dhis2Instance,
                $programId,
                $defaultStartDate,
                $defaultEndDate
            ]);
            $surveyId = $conn->lastInsertId();
            
            // Process data elements and create questions
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
                                $stmt = $conn->prepare("INSERT INTO option_set_values 
                                                      (option_set_id, option_value) 
                                                      VALUES (?, ?)");
                                $stmt->execute([$optionSetId, $option['name']]);
                            }
                            
                            // Map option to DHIS2 if code exists
                            if (!empty($option['code'])) {
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

                    // Update question with option set id
                    $stmt = $conn->prepare("UPDATE question SET option_set_id = ? WHERE id = ?");
                    $stmt->execute([$optionSetId, $questionId]);
                }
            };
            
            // Process data elements
            foreach ($dataElements as $deId => $element) {
                $processQuestion($element, $deId, $surveyId, $position, $conn);
            }
            
            // Process attributes if tracker program
            if (isset($_POST['attributes']) && !empty($_POST['attributes'])) {
                $attributes = json_decode($_POST['attributes'], true);
                foreach ($attributes as $attrId => $attr) {
                    $processQuestion($attr, $attrId, $surveyId, $position, $conn);
                }
            }
            
            $conn->commit();
            header("Location: survey.php?success=DHIS2 survey created successfully!");
            exit();
        }
    } catch (Exception $e) {
        if (isset($conn)) $conn->rollBack();
        header("Location: survey.php?error=" . urlencode($e->getMessage()));
        exit();
    }
}

// Fetch active DHIS2 instances from database
function getActiveDhis2Instances($pdo) {
    $stmt = $pdo->query("SELECT `key`, description FROM dhis2_instances WHERE status = 1");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get DHIS2 programs or datasets based on domain type
function getDhis2ProgramsOrDatasets($instanceKey, $domain) {
    try {
        // First get the instance credentials from database
        $pdo = new PDO("mysql:host=localhost;dbname=fbtv3;charset=utf8", "root", "root");
        $stmt = $pdo->prepare("SELECT url, username, password FROM dhis2_instances WHERE `key` = ? AND status = 1");
        $stmt->execute([$instanceKey]);
        $instance = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$instance) {
            throw new Exception("DHIS2 instance not found or inactive");
        }
        
        // Configure the instance for API calls
        $config = [
            'url' => $instance['url'],
            'username' => $instance['username'],
            'password' => $instance['password']
        ];
        
        if ($domain === 'tracker') {
            $programs = dhis2_get('/api/programs?fields=id,name', $config);
            return $programs['programs'] ?? [];
        } else {
            $datasets = dhis2_get('/api/dataSets?fields=id,name', $config);
            return $datasets['dataSets'] ?? [];
        }
    } catch (Exception $e) {
        throw $e;
    }
}

// Get program details from DHIS2
function getProgramDetails($instanceKey, $domain, $programId) {
    try {
        // Get the instance credentials from database
        $pdo = new PDO("mysql:host=localhost;dbname=fbtv3;charset=utf8", "root", "root");
        $stmt = $pdo->prepare("SELECT url, username, password FROM dhis2_instances WHERE `key` = ? AND status = 1");
        $stmt->execute([$instanceKey]);
        $instance = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$instance) {
            throw new Exception("DHIS2 instance not found or inactive");
        }
        
        // Configure the instance for API calls
        $config = [
            'url' => $instance['url'],
            'username' => $instance['username'],
            'password' => $instance['password']
        ];
        
        $result = [
            'program' => null,
            'dataElements' => [],
            'attributes' => [],
            'optionSets' => []
        ];
        
        if ($domain === 'tracker') {
            // Get program details
            $programInfo = dhis2_get('/api/programs/'.$programId.'?fields=id,name,programStages[id,name,programStageDataElements[dataElement[id,name,optionSet[id,name]]]', $config);
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
                            
                            if (!empty($de['optionSet'])) {
                                $result['optionSets'][$de['optionSet']['id']] = $de['optionSet'];
                            }
                        }
                    }
                }
            }
            
            // Get program attributes
            $programAttrs = dhis2_get('/api/programs/'.$programId.'?fields=programTrackedEntityAttributes[trackedEntityAttribute[id,name,optionSet[id,name]]]', $config);
            if (!empty($programAttrs['programTrackedEntityAttributes'])) {
                foreach ($programAttrs['programTrackedEntityAttributes'] as $attr) {
                    $tea = $attr['trackedEntityAttribute'];
                    $result['attributes'][$tea['id']] = [
                        'name' => $tea['name'],
                        'optionSet' => $tea['optionSet'] ?? null
                    ];
                    
                    if (!empty($tea['optionSet'])) {
                        $result['optionSets'][$tea['optionSet']['id']] = $tea['optionSet'];
                    }
                }
            }
        } elseif ($domain === 'aggregate') {
            // Get dataset details
            $datasetInfo = dhis2_get('/api/dataSets/'.$programId.'?fields=id,name,dataSetElements[dataElement[id,name,optionSet[id,name]]]', $config);
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
                    
                    if (!empty($de['optionSet'])) {
                        $result['optionSets'][$de['optionSet']['id']] = $de['optionSet'];
                    }
                }
            }
        }
        
        // Fetch option values for all option sets
        foreach ($result['optionSets'] as $optionSetId => &$optionSet) {
            $optionSetDetails = dhis2_get('/api/optionSets/'.$optionSetId.'?fields=id,name,options[id,name,code]', $config);
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
    } catch (Exception $e) {
        throw $e;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Create Survey</title>
  <link href="argon-dashboard-master/assets/css/nucleo-icons.css" rel="stylesheet">
  <link href="argon-dashboard-master/assets/css/nucleo-svg.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
  <link href="argon-dashboard-master/assets/css/argon-dashboard.min.css" rel="stylesheet">
  <style>
    .create-survey-container {
      max-width: 800px;
      margin: 0 auto;
      padding: 20px;
      background-color: #fff;
      border-radius: 10px;
      box-shadow: 0 0 15px rgba(0,0,0,0.1);
    }
    .step {
      display: none;
    }
    .step.active {
      display: block;
    }
    .step-header {
      border-bottom: 1px solid #eee;
      padding-bottom: 10px;
      margin-bottom: 20px;
    }
    .option-card {
      cursor: pointer;
      transition: all 0.3s;
      margin-bottom: 15px;
    }
    .option-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    .option-card.selected {
      border: 2px solid #5e72e4;
      background-color: #f8f9fe;
    }
    .form-section {
      margin-bottom: 25px;
      padding: 20px;
      background-color: #f8f9fe;
      border-radius: 8px;
    }
    .form-section-title {
      font-weight: 600;
      margin-bottom: 15px;
      color: #5e72e4;
    }
    .preview-item {
      padding: 10px;
      border-radius: 5px;
      background-color: #f8f9fe;
      margin-bottom: 10px;
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
    .navigation-buttons {
      display: flex;
      justify-content: space-between;
      margin-top: 30px;
    }
    .loading-spinner {
      display: none;
      text-align: center;
      margin: 10px 0;
    }
  </style>
</head>
<body>

  <?php include 'components/aside.php'; ?>
  
  <main class="main-content position-relative border-radius-lg">
    <?php include 'components/navbar.php'; ?>
    
    <div class="container-fluid py-4">
      <div class="row">
        <div class="col-12">
          <div class="card mb-4">
            <div class="card-header pb-0">
              <h2 class="text-primary text-center">
                <i class="fas fa-plus-circle me-2"></i> Create New Survey
              </h2>
            </div>
            
            <div class="card-body px-4">
              <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger" role="alert">
                  <?= htmlspecialchars($_GET['error']) ?>
                </div>
              <?php endif; ?>
              
              <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success" role="alert">
                  <?= htmlspecialchars($_GET['success']) ?>
                </div>
              <?php endif; ?>
              
              <div class="create-survey-container">
                <!-- Step 1: Select Instance -->
                <div id="step1" class="step active">
                  <div class="step-header">
                    <h3>Select DHIS2 Instance</h3>
                    <p class="text-muted">Choose the DHIS2 instance to connect to</p>
                  </div>
                  
                  <div class="form-section">
                    <div class="form-group">
                      <label class="form-label">DHIS2 Instance</label>
                      <select id="dhis2Instance" class="form-control" required>
                        <option value="">-- Select Instance --</option>
                        <?php
                        try {
                            $pdo = new PDO("mysql:host=localhost;dbname=fbtv3;charset=utf8", "root", "root");
                            $instances = getActiveDhis2Instances($pdo);
                            foreach ($instances as $instance): ?>
                                <option value="<?= htmlspecialchars($instance['key']) ?>">
                                    <?= htmlspecialchars($instance['key']) ?>
                                    <?= !empty($instance['description']) ? ' - ' . htmlspecialchars($instance['description']) : '' ?>
                                </option>
                            <?php endforeach;
                        } catch (Exception $e) {
                            echo '<option value="">Error loading instances</option>';
                        }
                        ?>
                      </select>
                    </div>
                  </div>
                  
                  <div class="navigation-buttons">
                    <a href="survey.php" class="btn btn-secondary">
                      <i class="fas fa-arrow-left me-2"></i> Cancel
                    </a>
                    <button class="btn btn-primary" onclick="nextStep()" id="nextBtn1">
                      Next <i class="fas fa-arrow-right ms-2"></i>
                    </button>
                  </div>
                </div>
                
                <!-- Step 2: Select Survey Type -->
                <div id="step2" class="step">
                  <div class="step-header">
                    <h3>Select Survey Type</h3>
                    <p class="text-muted">Choose how you want to create your survey</p>
                  </div>
                  
                  <div class="row">
                    <div class="col-md-6">
                      <div class="card option-card" onclick="selectSurveyType('local')">
                        <div class="card-body text-center">
                          <i class="fas fa-file-alt fa-3x mb-3 text-primary"></i>
                          <h4>Local Survey</h4>
                          <p class="text-muted">Build a custom survey with your own questions</p>
                        </div>
                      </div>
                    </div>
                    
                    <div class="col-md-6">
                      <div class="card option-card" onclick="selectSurveyType('dhis2')">
                        <div class="card-body text-center">
                          <i class="fas fa-exchange-alt fa-3x mb-3 text-success"></i>
                          <h4>DHIS2 Program/Dataset</h4>
                          <p class="text-muted">Create a survey based on a DHIS2 program or dataset</p>
                        </div>
                      </div>
                    </div>
                  </div>
                  
                  <div class="navigation-buttons">
                    <button type="button" class="btn btn-secondary" onclick="prevStep()">
                      <i class="fas fa-arrow-left me-2"></i> Back
                    </button>
                    <button class="btn btn-primary" onclick="nextStep()" disabled id="nextBtn2">
                      Next <i class="fas fa-arrow-right ms-2"></i>
                    </button>
                  </div>
                </div>
                
                <!-- Step 3a: Local Survey Form -->
                <div id="step3a" class="step">
                  <div class="step-header">
                    <h3>Create Local Survey</h3>
                    <p class="text-muted">Enter details for your new survey</p>
                  </div>
                  
                  <form id="localSurveyForm" method="POST">
                    <input type="hidden" name="dhis2_instance" id="localDhis2Instance" value="">
                    
                    <div class="form-section">
                      <div class="form-group">
                        <label for="survey_name" class="form-label">Survey Name *</label>
                        <input type="text" id="survey_name" name="survey_name" class="form-control" required>
                      </div>
                      
                      <div class="row mt-3">
                        <div class="col-md-6">
                          <div class="form-group">
                            <label for="start_date" class="form-label">Start Date</label>
                            <input type="date" id="start_date" name="start_date" class="form-control" value="<?= $defaultStartDate ?>">
                          </div>
                        </div>
                        <div class="col-md-6">
                          <div class="form-group">
                            <label for="end_date" class="form-label">End Date</label>
                            <input type="date" id="end_date" name="end_date" class="form-control" value="<?= $defaultEndDate ?>">
                          </div>
                        </div>
                      </div>
                      
                      <div class="form-check form-switch ps-0 mt-3">
                        <input class="form-check-input ms-auto" type="checkbox" id="isActive" name="is_active" checked>
                        <label class="form-check-label text-body ms-3 text-truncate w-80 mb-0" for="isActive">Active Survey</label>
                      </div>
                    </div>
                    
                    <div class="navigation-buttons">
                      <button type="button" class="btn btn-secondary" onclick="prevStep()">
                        <i class="fas fa-arrow-left me-2"></i> Back
                      </button>
                      <button type="submit" name="create_local_survey" class="btn btn-success">
                        <i class="fas fa-check me-2"></i> Create Survey
                      </button>
                    </div>
                  </form>
                </div>
                
                <!-- Step 3b: DHIS2 Domain Selection -->
                <div id="step3b" class="step">
                  <div class="step-header">
                    <h3>Select DHIS2 Domain Type</h3>
                    <p class="text-muted">Choose the type of DHIS2 resource to import</p>
                  </div>
                  
                  <div class="form-section">
                    <div class="form-group">
                      <label class="form-label">Domain Type</label>
                      <select id="domainType" class="form-control" onchange="loadProgramsOrDatasets()">
                        <option value="">-- Select Domain --</option>
                        <option value="tracker">Tracker Program</option>
                        <option value="aggregate">Aggregate Dataset</option>
                      </select>
                    </div>
                    
                    <div id="resourceLoading" class="loading-spinner">
                      <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                      </div>
                      <p>Loading resources...</p>
                    </div>
                    
                    <div id="resourceSelection" class="mt-3" style="display: none;">
                      <div class="form-group">
                        <label id="resourceLabel" class="form-label">Select Program</label>
                        <select id="resourceId" class="form-control">
                          <option value="">-- Select --</option>
                        </select>
                      </div>
                    </div>
                  </div>
                  
                  <div class="navigation-buttons">
                    <button type="button" class="btn btn-secondary" onclick="prevStep()">
                      <i class="fas fa-arrow-left me-2"></i> Back
                    </button>
                    <button class="btn btn-primary" onclick="nextStep()" disabled id="nextBtn3b">
                      Next <i class="fas fa-arrow-right ms-2"></i>
                    </button>
                  </div>
                </div>
                
                <!-- Step 4b: DHIS2 Program Preview -->
                <div id="step4b" class="step">
                  <div class="step-header">
                    <h3>Program Preview</h3>
                    <p class="text-muted">Review the program details before creating the survey</p>
                  </div>
                  
                  <div id="programPreview">
                    <div class="loading-spinner" id="previewLoading">
                      <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                      </div>
                      <p>Loading program details...</p>
                    </div>
                  </div>
                  
                  <form method="POST" id="dhis2SurveyForm" style="display: none;">
                    <input type="hidden" name="dhis2_instance" id="dhis2InstanceInput">
                    <input type="hidden" name="domain" id="domainInput">
                    <input type="hidden" name="program_id" id="programIdInput">
                    <input type="hidden" name="program_name" id="programNameInput">
                    <input type="hidden" name="data_elements" id="dataElementsInput">
                    <input type="hidden" name="attributes" id="attributesInput">
                    
                    <div class="navigation-buttons">
                      <button type="button" class="btn btn-secondary" onclick="prevStep()">
                        <i class="fas fa-arrow-left me-2"></i> Back
                      </button>
                      <button type="submit" name="create_dhis2_survey" class="btn btn-success">
                        <i class="fas fa-check me-2"></i> Create Survey
                      </button>
                    </div>
                  </form>
                </div>
              </div>
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
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

  <script>
    let currentStep = 1;
    let selectedSurveyType = null;
    let selectedDhis2Instance = null;
    
    function goToStep(step) {
      document.querySelectorAll('.step').forEach(stepEl => {
        stepEl.classList.remove('active');
      });
      document.getElementById(`step${step}`).classList.add('active');
      currentStep = step;
    }
    
    function nextStep() {
      if (currentStep === 1) {
        // Store selected DHIS2 instance
        selectedDhis2Instance = document.getElementById('dhis2Instance').value;
        if (!selectedDhis2Instance) {
          alert('Please select a DHIS2 instance');
          return;
        }
        document.getElementById('localDhis2Instance').value = selectedDhis2Instance;
        goToStep(2);
      } 
      else if (currentStep === 2) {
        if (!selectedSurveyType) {
          alert('Please select a survey type');
          return;
        }
        if (selectedSurveyType === 'local') {
          goToStep(3);
        } else {
          goToStep(3);
        }
      }
      else if (currentStep === 3 && selectedSurveyType === 'dhis2') {
        const domainType = document.getElementById('domainType').value;
        const resourceId = document.getElementById('resourceId').value;
        if (!domainType) {
          alert('Please select a domain type');
          return;
        }
        if (!resourceId) {
          alert(`Please select a ${domainType === 'tracker' ? 'program' : 'dataset'}`);
          return;
        }
        loadProgramPreview(selectedDhis2Instance, domainType, resourceId);
        goToStep(4);
      }
    }
    
    function prevStep() {
      if (currentStep === 2) {
        goToStep(1);
      } 
      else if (currentStep === 3) {
        if (selectedSurveyType === 'local') {
          goToStep(2);
        } else {
          goToStep(2);
        }
      }
      else if (currentStep === 4) {
        goToStep(3);
      }
    }
    
    function selectSurveyType(type) {
      selectedSurveyType = type;
      document.querySelectorAll('.option-card').forEach(card => {
        card.classList.remove('selected');
      });
      event.currentTarget.classList.add('selected');
      document.getElementById('nextBtn2').disabled = false;
      
      // Update the step we'll go to next
      if (type === 'local') {
        document.getElementById('step3a').style.display = 'block';
        document.getElementById('step3b').style.display = 'none';
      } else {
        document.getElementById('step3a').style.display = 'none';
        document.getElementById('step3b').style.display = 'block';
      }
    }
    
    function loadProgramsOrDatasets() {
      const domainType = document.getElementById('domainType').value;
      if (!domainType || !selectedDhis2Instance) return;
      
      document.getElementById('resourceLoading').style.display = 'block';
      document.getElementById('resourceSelection').style.display = 'none';
      document.getElementById('nextBtn3b').disabled = true;
      
      $.ajax({
        url: 'ajax_get_programs_datasets.php',
        type: 'GET',
        data: {
          dhis2_instance: selectedDhis2Instance,
          domain: domainType
        },
        success: function(data) {
          const select = document.getElementById('resourceId');
          select.innerHTML = '<option value="">-- Select ' + (domainType === 'tracker' ? 'Program' : 'Dataset') + ' --</option>';
          
          if (data && data.length > 0) {
            data.forEach(item => {
              const option = document.createElement('option');
              option.value = item.id;
              option.textContent = item.name;
              select.appendChild(option);
            });
            
            document.getElementById('resourceLabel').textContent = 'Select ' + (domainType === 'tracker' ? 'Program' : 'Dataset');
            document.getElementById('resourceSelection').style.display = 'block';
          } else {
            document.getElementById('resourceSelection').style.display = 'block';
            select.innerHTML = '<option value="">No ' + (domainType === 'tracker' ? 'programs' : 'datasets') + ' found</option>';
          }
          
          document.getElementById('resourceLoading').style.display = 'none';
        },
        error: function() {
          alert('Error loading resources');
          document.getElementById('resourceLoading').style.display = 'none';
          document.getElementById('resourceSelection').style.display = 'block';
          document.getElementById('resourceId').innerHTML = '<option value="">Error loading resources</option>';
        }
      });
    }
    
    function loadProgramPreview(instanceKey, domain, programId) {
      const previewDiv = document.getElementById('programPreview');
      previewDiv.innerHTML = '';
      document.getElementById('previewLoading').style.display = 'block';
      document.getElementById('dhis2SurveyForm').style.display = 'none';
      
      $.ajax({
        url: 'ajax_get_program_details.php',
        type: 'GET',
        data: {
          dhis2_instance: instanceKey,
          domain: domain,
          program_id: programId
        },
        success: function(data) {
          document.getElementById('previewLoading').style.display = 'none';
          
          if (data.error) {
            previewDiv.innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
            return;
          }
          
          let html = `
            <div class="form-section">
              <h4>${data.program.name}</h4>
          `;
          
          if (data.dataElements && data.dataElements.length > 0) {
            html += `
              <div class="preview-section">
                <h5>Data Elements</h5>
            `;
            
            data.dataElements.forEach(element => {
              html += `
                <div class="preview-item">
                  <strong>${element.name}</strong>
              `;
              
              if (element.optionSet) {
                html += `
                  <div>
                    <small>Option Set: ${element.optionSet.name}</small>
                `;
                
                if (element.options && element.options.length > 0) {
                  html += '<div class="mt-2">';
                  element.options.forEach(option => {
                    html += `<span class="option-item">${option.name}</span>`;
                  });
                  html += '</div>';
                }
                
                html += '</div>';
              }
              
              html += '</div>';
            });
            
            html += '</div>';
          }
          
          if (domain === 'tracker' && data.attributes && data.attributes.length > 0) {
            html += `
              <div class="preview-section">
                <h5>Tracked Entity Attributes</h5>
            `;
            
            data.attributes.forEach(attr => {
              html += `
                <div class="preview-item">
                  <strong>${attr.name}</strong>
              `;
              
              if (attr.optionSet) {
                html += `
                  <div>
                    <small>Option Set: ${attr.optionSet.name}</small>
                `;
                
                if (attr.options && attr.options.length > 0) {
                  html += '<div class="mt-2">';
                  attr.options.forEach(option => {
                    html += `<span class="option-item">${option.name}</span>`;
                    });
                  html += '</div>';
                }
                
                html += '</div>';
              }
              
              html += '</div>';
            });
            
            html += '</div>';
          }
          
          html += '</div>';
          
          previewDiv.innerHTML = html;
          
          // Populate form inputs
          document.getElementById('dhis2InstanceInput').value = instanceKey;
          document.getElementById('domainInput').value = domain;
          document.getElementById('programIdInput').value = programId;
          document.getElementById('programNameInput').value = data.program.name;
          document.getElementById('dataElementsInput').value = JSON.stringify(data.dataElements || {});
          document.getElementById('attributesInput').value = JSON.stringify(data.attributes || {});
          
          document.getElementById('dhis2SurveyForm').style.display = 'block';
        },
        error: function() {
          document.getElementById('previewLoading').style.display = 'none';
          previewDiv.innerHTML = '<div class="alert alert-danger">Error loading program details</div>';
        }
      });
    }
    
    // Enable/disable next button based on resource selection
    document.getElementById('resourceId').addEventListener('change', function() {
      document.getElementById('nextBtn3b').disabled = !this.value;
    });
    
    // Initialize step navigation
    document.addEventListener('DOMContentLoaded', function() {
      // Set up step navigation based on survey type
      if (selectedSurveyType === 'local') {
        document.getElementById('step3a').style.display = 'block';
        document.getElementById('step3b').style.display = 'none';
        document.getElementById('step4b').style.display = 'none';
      } else if (selectedSurveyType === 'dhis2') {
        document.getElementById('step3a').style.display = 'none';
        document.getElementById('step3b').style.display = 'block';
        document.getElementById('step4b').style.display = 'block';
      }
    });
  </script>

</body>
</html>
