<?php
session_start();
require 'connect.php';

$regionId = $_GET['id'] ?? 0;

// Get region details
$stmt = $pdo->prepare("SELECT * FROM location WHERE id = ?");
$stmt->execute([$regionId]);
$region = $stmt->fetch(PDO::FETCH_ASSOC);

// Get child locations
$childLocations = getChildLocations($pdo, $regionId);

function getChildLocations($pdo, $parentId) {
    $query = "SELECT id, uid, name, path, hierarchylevel, parent_id FROM location 
              WHERE parent_id = ? ORDER BY name";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$parentId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function buildTreeView($pdo, $parentId) {
    $html = '';
    $children = getChildLocations($pdo, $parentId);
    
    if (!empty($children)) {
        $html .= '<ul class="tree">';
        foreach ($children as $child) {
            $hasChildren = !empty(getChildLocations($pdo, $child['id']));
            $html .= '<li>';
            $html .= '<span class="tree-item' . ($hasChildren ? ' has-children' : '') . '">';
            $html .= htmlspecialchars($child['name']) . ' <small class="text-muted">(' . $child['uid'] . ')</small>';
            $html .= '</span>';
            $html .= buildTreeView($pdo, $child['id']);
            $html .= '</li>';
        }
        $html .= '</ul>';
    }
    
    return $html;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Locations</title>
    <!-- Favicon -->
    <link rel="icon" href="argon-dashboard-master/assets/img/brand/favicon.png" type="image/png">
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700" rel="stylesheet">
    <!-- Icons -->
    <link href="argon-dashboard-master/assets/css/nucleo-icons.css" rel="stylesheet">
    <link href="argon-dashboard-master/assets/css/nucleo-svg.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Argon CSS -->
    <link href="argon-dashboard-master/assets/css/argon-dashboard.css" rel="stylesheet">
    <!-- Sweet Alert -->
    <link href="argon-dashboard-master/assets/css/sweetalert2.min.css" rel="stylesheet">
</head>
<body class="g-sidenav-show bg-gray-100">
    <?php include 'components/aside.php'; ?>
  

    <div class="main-content position-relative border-radius-lg">
    <?php include 'components/navbar.php'; ?>
        <div class="container-fluid py-4">
            <div class="card">
                <div class="card-header bg-light">
                    <div class="row align-items-center">
                        <div class="col">
                            <h4 class="mb-0"><?= htmlspecialchars($region['name']) ?> Hierarchy</h4>
                        </div>
                        <div class="col-auto">
                            <a href="settings.php?tab=view" class="btn btn-sm btn-secondary">
                                <i class="fas fa-arrow-left me-1"></i> Back to Regions
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div id="locationTree">
                        <?= buildTreeView($pdo, $regionId) ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Same scripts as settings.php -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const treeItems = document.querySelectorAll('.tree-item.has-children');
        
        treeItems.forEach(item => {
            item.addEventListener('click', function(e) {
                e.stopPropagation();
                const nestedList = this.nextElementSibling;
                if (nestedList) {
                    nestedList.classList.toggle('active');
                    this.classList.toggle('active');
                }
            });
        });
    });
    </script>
</body>
</html>