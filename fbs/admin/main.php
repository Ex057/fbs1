<?php
session_start();

// Redirect to login if not logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit();
}

require 'connect.php'; // Database connection



// Get survey statistics
$surveyStats = $pdo->query("
    SELECT 
        COUNT(id) as total_surveys,
        (SELECT COUNT(id) FROM submission) as total_submissions,
        (SELECT COUNT(DISTINCT survey_id) FROM submission) as active_surveys
    FROM survey
")->fetch(PDO::FETCH_ASSOC);

// Get recent submissions (MODIFIED TO INCLUDE sub.id)
$recentSubmissions = $pdo->query("
    SELECT sub.id, s.name as survey_name, sub.created, sub.uid, 
           COUNT(sr.id) as response_count
    FROM submission sub
    JOIN survey s ON sub.survey_id = s.id
    LEFT JOIN submission_response sr ON sub.id = sr.submission_id
    GROUP BY sub.id
    ORDER BY sub.created DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Get survey participation data for chart
$surveyParticipation = $pdo->query("
    SELECT s.name, COUNT(sub.id) as submissions
    FROM survey s
    LEFT JOIN submission sub ON s.id = sub.survey_id
    GROUP BY s.id
    ORDER BY submissions DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// Format data for chart
$chartLabels = [];
$chartData = [];
foreach ($surveyParticipation as $survey) {
    $chartLabels[] = $survey['name'];
    $chartData[] = $survey['submissions'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <!-- Include all necessary CSS/JS from previous components -->
    <link href="argon-dashboard-master/assets/css/nucleo-icons.css" rel="stylesheet">
    <link href="argon-dashboard-master/assets/css/nucleo-svg.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="argon-dashboard-master/assets/css/argon-dashboard.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="g-sidenav-show bg-gray-100">
    <?php include 'components/aside.php'; ?>
    
    <div class="main-content position-relative border-radius-lg">
        <?php include 'components/navbar.php'; ?>
        
        <div class="container-fluid py-4">
            <!-- Summary Cards -->
            <div class="row mb-4">
                <div class="col-xl-4 col-sm-6 mb-xl-0 mb-4">
                    <div class="card">
                        <div class="card-body p-3">
                            <div class="row">
                                <div class="col-8">
                                    <div class="numbers">
                                        <p class="text-sm mb-0 text-uppercase font-weight-bold">Total Surveys</p>
                                        <h5 class="font-weight-bolder">
                                            <?= $surveyStats['total_surveys'] ?>
                                        </h5>
                                        <p class="mb-0">
                                            <span class="text-success text-sm font-weight-bolder">+<?= $surveyStats['active_surveys'] ?></span>
                                            active
                                        </p>
                                    </div>
                                </div>
                                <div class="col-4 text-end">
                                    <div class="icon icon-shape bg-gradient-primary shadow-primary text-center rounded-circle">
                                        <i class="fas fa-clipboard-list text-lg opacity-10"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-4 col-sm-6 mb-xl-0 mb-4">
                    <div class="card">
                        <div class="card-body p-3">
                            <div class="row">
                                <div class="col-8">
                                    <div class="numbers">
                                        <p class="text-sm mb-0 text-uppercase font-weight-bold">Total Submissions</p>
                                        <h5 class="font-weight-bolder">
                                            <?= $surveyStats['total_submissions'] ?>
                                        </h5>
                                        <p class="mb-0">
                                            <span class="text-success text-sm font-weight-bolder">+5.2%</span>
                                            from last month
                                        </p>
                                    </div>
                                </div>
                                <div class="col-4 text-end">
                                    <div class="icon icon-shape bg-gradient-danger shadow-danger text-center rounded-circle">
                                        <i class="fas fa-paper-plane text-lg opacity-10"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-4 col-sm-6">
                    <div class="card">
                        <div class="card-body p-3">
                            <div class="row">
                                <div class="col-8">
                                    <div class="numbers">
                                        <p class="text-sm mb-0 text-uppercase font-weight-bold">Avg. Responses</p>
                                        <h5 class="font-weight-bolder">
                                            <?= $surveyStats['total_submissions'] > 0 ? 
                                                round($surveyStats['total_submissions'] / $surveyStats['total_surveys'], 1) : 0 ?>
                                        </h5>
                                        <p class="mb-0">
                                            <span class="text-danger text-sm font-weight-bolder">-2%</span>
                                            completion rate
                                        </p>
                                    </div>
                                </div>
                                <div class="col-4 text-end">
                                    <div class="icon icon-shape bg-gradient-success shadow-success text-center rounded-circle">
                                        <i class="fas fa-chart-bar text-lg opacity-10"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Charts Row -->
            <div class="row mb-4">
                <div class="col-lg-8">
                    <div class="card z-index-2">
                        <div class="card-header pb-0">
                            <h6>Survey Participation</h6>
                            <p class="text-sm">
                                <i class="fa fa-arrow-up text-success"></i>
                                <span class="font-weight-bold">4% more</span> this month
                            </p>
                        </div>
                        <div class="card-body p-3">
                            <div class="chart">
                                <canvas id="surveyChart" class="chart-canvas" height="300"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header pb-0">
                            <h6>Submission Types</h6>
                            <p class="text-sm">
                                <i class="fa fa-info-circle text-primary"></i>
                                Breakdown by survey type
                            </p>
                        </div>
                        <div class="card-body p-3">
                            <div class="chart">
                                <canvas id="typeChart" class="chart-canvas" height="300"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Submissions -->
            <div class="row">
                <div class="col-12">
                    <div class="card mb-4">
                        <div class="card-header pb-0">
                            <h6>Recent Submissions</h6>
                            <p class="text-sm">
                                <i class="fa fa-history text-primary"></i>
                                Last 5 survey responses
                            </p>
                        </div>
                        <div class="card-body px-0 pt-0 pb-2">
                            <div class="table-responsive p-0">
                                <table class="table align-items-center mb-0">
                                    <thead>
                                        <tr>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Survey</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">UID</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Responses</th>
                                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Date</th>
                                            <th class="text-secondary opacity-7"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentSubmissions as $submission): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex px-2 py-1">
                                                        <div class="d-flex flex-column justify-content-center">
                                                            <h6 class="mb-0 text-sm"><?= htmlspecialchars($submission['survey_name']) ?></h6>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <p class="text-xs font-weight-bold mb-0"><?= htmlspecialchars($submission['uid']) ?></p>
                                                </td>
                                                <td>
                                                    <span class="badge badge-sm bg-gradient-success"><?= $submission['response_count'] ?></span>
                                                </td>
                                                <td>
                                                    <span class="text-secondary text-xs font-weight-bold"><?= date('M d, Y H:i', strtotime($submission['created'])) ?></span>
                                                </td>
                                                <td class="align-middle">
                                                    <a href="view_record.php?submission_id=<?= htmlspecialchars($submission['id']) ?>" 
                                                    class="text-secondary font-weight-bold text-xs" 
                                                    data-toggle="tooltip" 
                                                    title="View Details">
                                                    View
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'components/fixednav.php'; ?>
    
    <!-- Core JS Files -->
    <script src="argon-dashboard-master/assets/js/core/popper.min.js"></script>
    <script src="argon-dashboard-master/assets/js/core/bootstrap.min.js"></script>
    <script src="argon-dashboard-master/assets/js/plugins/perfect-scrollbar.min.js"></script>
    <script src="argon-dashboard-master/assets/js/plugins/smooth-scrollbar.min.js"></script>
    <script src="argon-dashboard-master/assets/js/argon-dashboard.js"></script>
    
    <!-- Dashboard Charts -->
    <script>
        // Survey Participation Chart
        const ctx1 = document.getElementById('surveyChart').getContext('2d');
        new Chart(ctx1, {
            type: 'line',
            data: {
                labels: <?= json_encode($chartLabels) ?>,
                datasets: [{
                    label: 'Submissions',
                    data: <?= json_encode($chartData) ?>,
                    backgroundColor: [
                        'rgba(94, 114, 228, 0.8)',
                        'rgba(23, 201, 100, 0.8)',
                        'rgba(244, 106, 106, 0.8)',
                        'rgba(255, 159, 64, 0.8)',
                        'rgba(112, 44, 249, 0.8)'
                    ],
                    borderColor: [
                        'rgba(94, 114, 228, 1)',
                        'rgba(23, 201, 100, 1)',
                        'rgba(244, 106, 106, 1)',
                        'rgba(255, 159, 64, 1)',
                        'rgba(112, 44, 249, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                if (Number.isInteger(value)) {
                                    return value;
                                }
                            }
                        }
                    }
                }
            }
        });

        // Submission Types Chart (example - replace with your actual data)
        const ctx2 = document.getElementById('typeChart').getContext('2d');
        new Chart(ctx2, {
            type: 'doughnut',
            data: {
                labels: ['Health', 'Education', 'Business', 'Other'],
                datasets: [{
                    data: [45, 25, 20, 10],
                    backgroundColor: [
                        'rgba(94, 114, 228, 0.8)',
                        'rgba(23, 201, 100, 0.8)',
                        'rgba(244, 106, 106, 0.8)',
                        'rgba(255, 159, 64, 0.8)'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'right'
                    }
                },
                cutout: '70%'
            }
        });

        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    </script>
</body>
</html>