<?php
session_start();

// Redirect to login if not logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit();
}

require 'connect.php';

// Set default dates
$defaultStartDate = date('Y-m-d');
$defaultEndDate = date('Y-m-d', strtotime('+6 months'));

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn = new PDO("mysql:host=localhost;dbname=fbtv3;charset=utf8", "root", "root");
        $conn->beginTransaction();
        
        if (isset($_POST['create_local_survey'])) {
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
            $stmt->execute([$surveyName, $startDate, $endDate, $isActive, $dhis2Instance]);
            
            $conn->commit();
            header("Location: survey.php?success=Local survey created successfully!");
            exit();
        }
        
        if (isset($_POST['create_dhis2_survey'])) {
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
            $stmt->execute([$programName, $dhis2Instance, $programId, $defaultStartDate, $defaultEndDate]);
            
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

// Get active DHIS2 instances
function getActiveDhis2Instances($pdo) {
    $stmt = $pdo->query("SELECT `key`, description FROM dhis2_instances WHERE status = 1 ORDER BY `key`");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    .survey-wizard {
      max-width: 800px;
      margin: 0 auto;
      padding: 20px;
    }
    
    .step {
      display: none;
      background: white;
      border-radius: 10px;
      padding: 30px;
      box-shadow: 0 0 15px rgba(0,0,0,0.1);
    }
    
    .step.active { display: block; }
    
    .step-header {
      text-align: center;
      margin-bottom: 30px;
      padding-bottom: 20px;
      border-bottom: 1px solid #eee;
    }
    
    .option-card {
      cursor: pointer;
      transition: all 0.3s ease;
      border: 2px solid transparent;
    }
    
    .option-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 10px 25px rgba(0,0,0,0.1);
    }
    
    .option-card.selected {
      border-color: #5e72e4;
      background-color: #f8f9fe;
    }
    
    .loading-overlay {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0,0,0,0.5);
      z-index: 9999;
      align-items: center;
      justify-content: center;
    }
    
    .loading-content {
      background: white;
      padding: 30px;
      border-radius: 10px;
      text-align: center;
    }
    
    .nav-buttons {
      display: flex;
      justify-content: space-between;
      margin-top: 30px;
      padding-top: 20px;
      border-top: 1px solid #eee;
    }
    
    .preview-section {
      background: #f8f9fe;
      padding: 20px;
      border-radius: 8px;
      margin-bottom: 20px;
    }
    
    .preview-item {
      background: white;
      padding: 15px;
      border-radius: 5px;
      margin-bottom: 10px;
      border-left: 4px solid #5e72e4;
    }
    
    .option-tag {
      display: inline-block;
      background: #e9ecef;
      padding: 2px 8px;
      border-radius: 12px;
      font-size: 11px;
      margin: 2px;
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
          <div class="card">
            <div class="card-header text-center">
              <h2 class="text-primary mb-0">
                <i class="fas fa-plus-circle me-2"></i>Create New Survey
              </h2>
            </div>
            
            <div class="card-body">
              <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger">
                  <?= htmlspecialchars($_GET['error']) ?>
                </div>
              <?php endif; ?>
              
              <div class="survey-wizard">
                <!-- Step 1: Select Instance -->
                <div id="step1" class="step active">
                  <div class="step-header">
                    <h3><i class="fas fa-server text-primary me-2"></i>Select DHIS2 Instance</h3>
                    <p class="text-muted">Choose the DHIS2 instance to work with</p>
                  </div>
                  
                  <div class="form-group">
                    <label class="form-label">DHIS2 Instance</label>
                    <select id="dhis2Instance" class="form-control form-control-lg">
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
                  
                  <div class="nav-buttons">
                    <a href="survey.php" class="btn btn-secondary">
                      <i class="fas fa-times me-2"></i>Cancel
                    </a>
                    <button class="btn btn-primary" onclick="nextStep()" disabled id="nextBtn1">
                      Next <i class="fas fa-arrow-right ms-2"></i>
                    </button>
                  </div>
                </div>
                
                <!-- Step 2: Select Survey Type -->
                <div id="step2" class="step">
                  <div class="step-header">
                    <h3><i class="fas fa-clipboard-list text-primary me-2"></i>Select Survey Type</h3>
                    <p class="text-muted">Choose how you want to create your survey</p>
                  </div>
                  
                  <div class="row">
                    <div class="col-md-6">
                      <div class="card option-card h-100" data-type="local">
                        <div class="card-body text-center d-flex flex-column justify-content-center">
                          <i class="fas fa-file-alt fa-4x mb-3 text-primary"></i>
                          <h4 class="mb-3">Local Survey</h4>
                          <p class="text-muted">Create a custom survey with your own questions</p>
                        </div>
                      </div>
                    </div>
                    
                    <div class="col-md-6">
                      <div class="card option-card h-100" data-type="dhis2">
                        <div class="card-body text-center d-flex flex-column justify-content-center">
                          <i class="fas fa-exchange-alt fa-4x mb-3 text-success"></i>
                          <h4 class="mb-3">DHIS2 Program/Dataset</h4>
                          <p class="text-muted">Import from DHIS2 program or dataset</p>
                        </div>
                      </div>
                    </div>
                  </div>
                  
                  <div class="nav-buttons">
                    <button class="btn btn-secondary" onclick="prevStep()">
                      <i class="fas fa-arrow-left me-2"></i>Back
                    </button>
                    <button class="btn btn-primary" onclick="nextStep()" disabled id="nextBtn2">
                      Next <i class="fas fa-arrow-right ms-2"></i>
                    </button>
                  </div>
                </div>
                
                <!-- Step 3a: Local Survey Form -->
                <div id="step3a" class="step">
                  <div class="step-header">
                    <h3><i class="fas fa-edit text-primary me-2"></i>Local Survey Details</h3>
                    <p class="text-muted">Enter the details for your local survey</p>
                  </div>
                  
                  <form method="POST" id="localSurveyForm">
                    <input type="hidden" name="dhis2_instance" id="localInstanceInput">
                    
                    <div class="form-group mb-3">
                      <label class="form-label">Survey Name *</label>
                      <input type="text" name="survey_name" class="form-control form-control-lg" required>
                    </div>
                    
                    <div class="row">
                      <div class="col-md-6">
                        <div class="form-group mb-3">
                          <label class="form-label">Start Date</label>
                          <input type="date" name="start_date" class="form-control" value="<?= $defaultStartDate ?>">
                          <small class="form-text text-muted">Defaults to today if not specified</small>
                        </div>
                      </div>
                      <div class="col-md-6">
                        <div class="form-group mb-3">
                          <label class="form-label">End Date</label>
                          <input type="date" name="end_date" class="form-control" value="<?= $defaultEndDate ?>">
                          <small class="form-text text-muted">Defaults to 6 months from start date</small>
                        </div>
                      </div>
                    </div>
                    
                    <div class="form-check form-switch">
                      <input class="form-check-input" type="checkbox" name="is_active" id="isActive" checked>
                      <label class="form-check-label" for="isActive">Active Survey</label>
                    </div>
                    
                    <div class="nav-buttons">
                      <button type="button" class="btn btn-secondary" onclick="prevStep()">
                        <i class="fas fa-arrow-left me-2"></i>Back
                      </button>
                      <button type="submit" name="create_local_survey" class="btn btn-success">
                        <i class="fas fa-check me-2"></i>Create Survey
                      </button>
                    </div>
                  </form>
                </div>
                
                <!-- Step 3b: DHIS2 Domain Selection -->
                <div id="step3b" class="step">
                  <div class="step-header">
                    <h3><i class="fas fa-sitemap text-primary me-2"></i>Select Domain Type</h3>
                    <p class="text-muted">Choose the type of DHIS2 resource</p>
                  </div>
                  
                  <div class="form-group mb-4">
                    <label class="form-label">Domain Type</label>
                    <select id="domainType" class="form-control form-control-lg">
                      <option value="">-- Select Domain --</option>
                      <option value="tracker">Tracker Program</option>
                      <option value="aggregate">Aggregate Dataset</option>
                    </select>
                  </div>
                  
                  <div id="resourceSection" style="display: none;">
                    <div class="form-group">
                      <label id="resourceLabel" class="form-label">Select Resource</label>
                      <select id="resourceSelect" class="form-control form-control-lg">
                        <option value="">-- Loading... --</option>
                      </select>
                    </div>
                  </div>
                  
                  <div class="nav-buttons">
                    <button class="btn btn-secondary" onclick="prevStep()">
                      <i class="fas fa-arrow-left me-2"></i>Back
                    </button>
                    <button class="btn btn-primary" onclick="nextStep()" disabled id="nextBtn3b">
                      Next <i class="fas fa-arrow-right ms-2"></i>
                    </button>
                  </div>
                </div>
                
                <!-- Step 4b: DHIS2 Preview -->
                <div id="step4b" class="step">
                  <div class="step-header">
                    <h3><i class="fas fa-eye text-primary me-2"></i>Survey Preview</h3>
                    <p class="text-muted">Review the imported structure before creating</p>
                  </div>
                  
                  <div id="previewContent"></div>
                  
                  <form method="POST" id="dhis2SurveyForm" style="display: none;">
                    <input type="hidden" name="dhis2_instance" id="dhis2InstanceInput">
                    <input type="hidden" name="program_id" id="programIdInput">
                    <input type="hidden" name="program_name" id="programNameInput">
                    
                    <div class="nav-buttons">
                      <button type="button" class="btn btn-secondary" onclick="prevStep()">
                        <i class="fas fa-arrow-left me-2"></i>Back
                      </button>
                      <button type="submit" name="create_dhis2_survey" class="btn btn-success">
                        <i class="fas fa-check me-2"></i>Create Survey
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
  </main>

  <!-- Loading Overlay -->
  <div class="loading-overlay" id="loadingOverlay">
    <div class="loading-content">
      <div class="spinner-border text-primary mb-3" style="width: 3rem; height: 3rem;"></div>
      <h5>Loading...</h5>
      <p class="text-muted" id="loadingText">Please wait while we fetch the data</p>
    </div>
  </div>

  <!-- Scripts -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="argon-dashboard-master/assets/js/core/bootstrap.min.js"></script>
  <script src="argon-dashboard-master/assets/js/argon-dashboard.min.js"></script>

  <script>
    class SurveyWizard {
      constructor() {
        this.currentStep = 1;
        this.selectedInstance = null;
        this.selectedType = null;
        this.selectedDomain = null;
        this.selectedResource = null;
        this.init();
      }
      
      init() {
        this.bindEvents();
      }
      
      bindEvents() {
        // Instance selection
        $('#dhis2Instance').on('change', (e) => {
          this.selectedInstance = e.target.value;
          $('#nextBtn1').prop('disabled', !this.selectedInstance);
        });
        
        // Survey type selection
        $('.option-card').on('click', (e) => {
          const card = $(e.currentTarget);
          const type = card.data('type');
          
          $('.option-card').removeClass('selected');
          card.addClass('selected');
          
          this.selectedType = type;
          $('#nextBtn2').prop('disabled', false);
        });
        
        // Domain type selection
        $('#domainType').on('change', (e) => {
          this.selectedDomain = e.target.value;
          if (this.selectedDomain) {
            this.loadResources();
          } else {
            $('#resourceSection').hide();
            $('#nextBtn3b').prop('disabled', true);
          }
        });
        
        // Resource selection
        $('#resourceSelect').on('change', (e) => {
          this.selectedResource = e.target.value;
          $('#nextBtn3b').prop('disabled', !this.selectedResource);
        });
      }
      
      showLoading(text = 'Loading...') {
        $('#loadingText').text(text);
        $('#loadingOverlay').css('display', 'flex');
      }
      
      hideLoading() {
        $('#loadingOverlay').hide();
      }
      
      goToStep(step) {
        $('.step').removeClass('active');
        $(`#step${step}`).addClass('active');
        this.currentStep = step;
      }
      
      nextStep() {
        if (this.currentStep === 1) {
          if (!this.selectedInstance) {
            alert('Please select a DHIS2 instance');
            return;
          }
          this.goToStep(2);
        } 
        else if (this.currentStep === 2) {
          if (!this.selectedType) {
            alert('Please select a survey type');
            return;
          }
          
          if (this.selectedType === 'local') {
            $('#localInstanceInput').val(this.selectedInstance);
            this.goToStep('3a');
          } else {
            this.goToStep('3b');
          }
        }
        else if (this.currentStep === '3b') {
          if (!this.selectedDomain || !this.selectedResource) {
            alert('Please select domain type and resource');
            return;
          }
          this.loadPreview();
        }
      }
      
      prevStep() {
        if (this.currentStep === 2) {
          this.goToStep(1);
        } 
        else if (this.currentStep === '3a' || this.currentStep === '3b') {
          this.goToStep(2);
        }
        else if (this.currentStep === '4b') {
          this.goToStep('3b');
        }
      }
      
      loadResources() {
        this.showLoading(`Loading ${this.selectedDomain === 'tracker' ? 'programs' : 'datasets'}...`);
        
        $.ajax({
          url: 'ajax/get_resources.php',
          method: 'GET',
          data: {
            instance: this.selectedInstance,
            domain: this.selectedDomain
          },
          success: (data) => {
            this.hideLoading();
            
            if (data.success) {
              const select = $('#resourceSelect');
              select.empty().append('<option value="">-- Select --</option>');
              
              data.resources.forEach(resource => {
                select.append(`<option value="${resource.id}">${resource.name}</option>`);
              });
              
              $('#resourceLabel').text(
                `Select ${this.selectedDomain === 'tracker' ? 'Program' : 'Dataset'}`
              );
              $('#resourceSection').show();
            } else {
              alert(data.message || 'Failed to load resources');
            }
          },
          error: () => {
            this.hideLoading();
            alert('Error loading resources. Please try again.');
          }
        });
      }
      
      loadPreview() {
        this.showLoading('Loading preview...');
        
        $.ajax({
          url: 'ajax/get_preview.php',
          method: 'GET',
          data: {
            instance: this.selectedInstance,
            domain: this.selectedDomain,
            resource: this.selectedResource
          },
          success: (data) => {
            this.hideLoading();
            
            if (data.success) {
              this.renderPreview(data.preview);
              
              // Populate form
              $('#dhis2InstanceInput').val(this.selectedInstance);
              $('#programIdInput').val(this.selectedResource);
              $('#programNameInput').val(data.preview.name);
              
              $('#dhis2SurveyForm').show();
              this.goToStep('4b');
            } else {
              alert(data.message || 'Failed to load preview');
            }
          },
          error: () => {
            this.hideLoading();
            alert('Error loading preview. Please try again.');
          }
        });
      }
      
      renderPreview(preview) {
        let html = `
          <div class="preview-section">
            <h4><i class="fas fa-info-circle text-primary me-2"></i>${preview.name}</h4>
            <p class="text-muted">ID: ${preview.id}</p>
          </div>
        `;
        
        if (preview.dataElements && preview.dataElements.length > 0) {
          html += `
            <div class="preview-section">
              <h5><i class="fas fa-database text-info me-2"></i>Data Elements (${preview.dataElements.length})</h5>
          `;
          
          preview.dataElements.forEach(element => {
            html += `
              <div class="preview-item">
                <strong>${element.name}</strong>
                ${element.valueType ? `<small class="text-muted d-block">Type: ${element.valueType}</small>` : ''}
                ${element.options ? `<div class="mt-2">${element.options.map(opt => `<span class="option-tag">${opt}</span>`).join('')}</div>` : ''}
              </div>
            `;
          });
          
          html += '</div>';
        }
        
        if (preview.attributes && preview.attributes.length > 0) {
          html += `
            <div class="preview-section">
              <h5><i class="fas fa-tags text-warning me-2"></i>Attributes (${preview.attributes.length})</h5>
          `;
          
          preview.attributes.forEach(attr => {
            html += `
              <div class="preview-item">
                <strong>${attr.name}</strong>
                ${attr.valueType ? `<small class="text-muted d-block">Type: ${attr.valueType}</small>` : ''}
                ${attr.options ? `<div class="mt-2">${attr.options.map(opt => `<span class="option-tag">${opt}</span>`).join('')}</div>` : ''}
              </div>
            `;
          });
          
          html += '</div>';
        }
        
        $('#previewContent').html(html);
      }
    }
    
    // Global functions for buttons
    let wizard;
    
    function nextStep() {
      wizard.nextStep();
    }
    
    function prevStep() {
      wizard.prevStep();
    }
    
    // Initialize wizard when document is ready
    $(document).ready(() => {
      wizard = new SurveyWizard();
    });
  </script>

</body>
</html>