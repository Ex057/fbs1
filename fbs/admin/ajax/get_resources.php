<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require __DIR__ . '/../../admin/connect.php';
require __DIR__ . '/../dhis2/dhis2_get_function.php';
require __DIR__ . '/../dhis2/dhis2_shared.php';

try {
    $instance = $_GET['instance'] ?? '';
    $domain = $_GET['domain'] ?? '';
    
    if (empty($instance) || empty($domain)) {
        throw new Exception('Missing required parameters');
    }
    
    // Get instance credentials from database
    $pdo = new PDO("mysql:host=localhost;dbname=fbtv3;charset=utf8", "root", "root");
    $stmt = $pdo->prepare("SELECT url, username, password FROM dhis2_instances WHERE `key` = ? AND status = 1");
    $stmt->execute([$instance]);
    $instanceData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$instanceData) {
        throw new Exception('Instance not found or inactive');
    }
    
    $config = [
        'url' => $instanceData['url'],
        'username' => $instanceData['username'],
        'password' => $instanceData['password']
    ];
    
    $resources = [];
    
    if ($domain === 'tracker') {
        if (!function_exists('dhis2_get')) {
            throw new Exception('Required function dhis2_get not found');
        }
        $response = dhis2_get('/api/programs?fields=id,name&paging=false', $config);
        $resources = $response['programs'] ?? [];
    } elseif ($domain === 'aggregate') {
        if (!function_exists('dhis2_get')) {
            throw new Exception('Required function dhis2_get not found');
        }
        $response = dhis2_get('/api/dataSets?fields=id,name&paging=false', $config);
        $resources = $response['dataSets'] ?? [];
    } else {
        throw new Exception('Invalid domain type');
    }
    
    echo json_encode([
        'success' => true,
        'resources' => $resources
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}