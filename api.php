<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'Pagination.php';

/**
 * DataTable API Endpoint
 * Serves paginated data with search and filtering capabilities
 */

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

/**
 * Fetch data from external API (based on your existing code)
 */
function fetchExternalData($limit = 1000) {
    $data = new stdClass(); 
    $url = "http://192.168.33.11/ammonite/index.php?option=com_toms_slave&task=api.api";
    $data->api_username = "CRM";
    $data->command = "search_person";
    $data->limit = $limit;
    $data_json = json_encode($data);
    $signature = hash_hmac('sha256', $data_json, "afc9272eea51136653de909b90fe72e486c5e53e3311bb314e4dd7d9df3e13b4dd");
    $data->signature = $signature;
    $data_json = json_encode($data);
    
    $bodyData = array('data' => $data_json);
    $bodyStr = http_build_query($bodyData);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/x-www-form-urlencoded',
        'Content-Length: '.strlen($bodyStr)
    ));
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $bodyStr);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && $response) {
        $results = json_decode($response, true);
        return $results;
    }
    
    return [];
}

/**
 * Generate sample data for demo purposes
 */
function generateSampleData($count = 100) {
    $sampleData = [];
    $firstNames = ['John', 'Jane', 'Mike', 'Sarah', 'David', 'Lisa', 'Chris', 'Emma', 'Alex', 'Anna'];
    $lastNames = ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez', 'Martinez'];
    $companies = ['Tech Corp', 'Global Inc', 'Innovation Ltd', 'Future Systems', 'Digital Solutions', 'Smart Tech', 'Advanced Corp', 'Next Gen', 'Pro Services', 'Elite Group'];
    $cities = ['New York', 'Los Angeles', 'Chicago', 'Houston', 'Phoenix', 'Philadelphia', 'San Antonio', 'San Diego', 'Dallas', 'San Jose'];
    
    for ($i = 1; $i <= $count; $i++) {
        $sampleData[] = [
            'id' => $i,
            'first_name' => $firstNames[array_rand($firstNames)],
            'last_name' => $lastNames[array_rand($lastNames)],
            'email' => strtolower($firstNames[array_rand($firstNames)] . '.' . $lastNames[array_rand($lastNames)] . '@example.com'),
            'company' => $companies[array_rand($companies)],
            'city' => $cities[array_rand($cities)],
            'age' => rand(22, 65),
            'salary' => rand(30000, 120000),
            'join_date' => date('Y-m-d', strtotime('-' . rand(1, 1825) . ' days'))
        ];
    }
    
    return $sampleData;
}

try {
    // Get request parameters
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $recordsPerPage = isset($_GET['per_page']) ? max(1, min(100, intval($_GET['per_page']))) : 10;
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $useExternalAPI = isset($_GET['use_external']) && $_GET['use_external'] === 'true';
    
    // Fetch data
    if ($useExternalAPI) {
        // Try to fetch from external API
        $data = fetchExternalData();
        
        // If external API fails, fall back to sample data
        if (empty($data)) {
            $data = generateSampleData(50);
        }
    } else {
        // Use sample data for demo
        $data = generateSampleData(150);
    }
    
    // Create pagination instance
    $pagination = new Pagination($data, $recordsPerPage);
    
    // Apply search if provided
    if (!empty($search)) {
        $pagination->search($search);
    }
    
    // Set current page
    $pagination->setCurrentPage($page);
    
    // Get paginated response
    $response = $pagination->getResponse();
    
    // Add additional metadata
    $response['success'] = true;
    $response['timestamp'] = date('Y-m-d H:i:s');
    $response['request_params'] = [
        'page' => $page,
        'per_page' => $recordsPerPage,
        'search' => $search,
        'use_external' => $useExternalAPI
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
}
?>