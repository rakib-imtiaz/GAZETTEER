<?php

/**
 * Test file for debugging country borders
 */

require_once 'utilities.php';

// Set headers for JSON output
header('Content-Type: application/json');

// Output debug information
$debug = [
    'time' => date('Y-m-d H:i:s'),
    'server' => $_SERVER['SERVER_NAME'],
    'php_version' => PHP_VERSION,
    'memory_limit' => ini_get('memory_limit')
];

// Check if countryBorders.geo.json exists in various locations
$possiblePaths = [
    __DIR__ . '/' . COUNTRY_BORDERS_FILE,  // From the config
    __DIR__ . '/../data/countryBorders.geo.json',  // Direct path
    '/var/www/html/GAZETTEER/data/countryBorders.geo.json'  // Absolute path
];

$fileInfo = [];
foreach ($possiblePaths as $path) {
    $fileInfo[$path] = [
        'exists' => file_exists($path),
        'size' => file_exists($path) ? filesize($path) : 0,
        'readable' => file_exists($path) ? is_readable($path) : false
    ];
}
$debug['file_paths'] = $fileInfo;

// Try to load the country borders file
$countryBorders = loadCountryBorders();
$debug['country_borders_loaded'] = ($countryBorders !== null);

if ($countryBorders !== null) {
    $debug['feature_count'] = count($countryBorders['features']);

    // Test a few country codes
    $testCodes = ['US', 'GB', 'FR', 'DE', 'JP', 'BR', 'AU', 'ZA', 'IN', 'CA'];
    $results = [];

    foreach ($testCodes as $code) {
        $country = findCountryByCode($code);
        $results[$code] = [
            'found' => ($country !== null),
            'properties' => $country !== null ? array_keys($country['properties']) : []
        ];
    }
    $debug['test_countries'] = $results;
}

// Output the debug information
echo json_encode($debug, JSON_PRETTY_PRINT);
