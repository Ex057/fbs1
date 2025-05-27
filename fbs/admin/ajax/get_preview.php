<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require '/fbs/admin/connect.php';
require '/fbs/admin/dhis2/dhis2_get_function.php';

try {
    $instance = $_GET['instance'] ?? '';
    $domain = $_GET['domain'] ?? '';
    $resource = $_GET['resource'] ?? '';

    if (empty($instance) || empty($domain) || empty($resource)) {
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

    // Fetch resource preview from DHIS2
    $url = rtrim($instanceData['url'], '/') . "/api/$domain/{$resource}.json?fields=id,name,dataElements[id,name,valueType,optionSet[id,name,options[name]]],programTrackedEntityAttributes[trackedEntityAttribute[id,name,valueType,optionSet[id,name,options[name]]]]";
    $auth = base64_encode($instanceData['username'] . ':' . $instanceData['password']);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Basic $auth",
        "Content-Type: application/json"
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception('Failed to fetch resource from DHIS2');
    }

    $data = json_decode($response, true);

    // Prepare preview structure
    $preview = [
        'id' => $data['id'] ?? '',
        'name' => $data['name'] ?? '',
        'dataElements' => [],
        'attributes' => []
    ];

    // Data Elements
    if (isset($data['dataElements']) && is_array($data['dataElements'])) {
        foreach ($data['dataElements'] as $element) {
            $options = [];
            if (isset($element['optionSet']['options']) && is_array($element['optionSet']['options'])) {
                foreach ($element['optionSet']['options'] as $opt) {
                    $options[] = $opt['name'];
                }
            }
            $preview['dataElements'][] = [
                'name' => $element['name'] ?? '',
                'valueType' => $element['valueType'] ?? '',
                'options' => $options
            ];
        }
    }

    // Attributes
    if (isset($data['programTrackedEntityAttributes']) && is_array($data['programTrackedEntityAttributes'])) {
        foreach ($data['programTrackedEntityAttributes'] as $attr) {
            $attrData = $attr['trackedEntityAttribute'] ?? [];
            $options = [];
            if (isset($attrData['optionSet']['options']) && is_array($attrData['optionSet']['options'])) {
                foreach ($attrData['optionSet']['options'] as $opt) {
                    $options[] = $opt['name'];
                }
            }
            $preview['attributes'][] = [
                'name' => $attrData['name'] ?? '',
                'valueType' => $attrData['valueType'] ?? '',
                'options' => $options
            ];
        }
    }

    echo json_encode(['success' => true, 'preview' => $preview]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}