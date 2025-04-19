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

    // Add BD and BI to specifically test the Bangladesh vs Burundi issue
    $testCodes[] = 'BD';  // Bangladesh
    $testCodes[] = 'BI';  // Burundi

    $results = [];

    foreach ($testCodes as $code) {
        $country = findCountryByCode($code);
        $results[$code] = [
            'found' => ($country !== null),
            'properties' => $country !== null ? array_keys($country['properties']) : [],
            'name' => $country !== null ? $country['properties']['name'] : 'Not found',
            'ISO2' => $country !== null ? ($country['properties']['ISO3166-1-Alpha-2'] ?? 'N/A') : 'N/A',
            'ISO3' => $country !== null ? ($country['properties']['ISO3166-1-Alpha-3'] ?? 'N/A') : 'N/A'
        ];
    }
    $debug['test_countries'] = $results;

    // Special section to diagnose Bangladesh/Burundi confusion
    $debug['bangladesh_burundi_diagnosis'] = diagnoseCountryIssue($countryBorders, 'BD', 'BI');
}

/**
 * Function to diagnose issues with country code matching
 * 
 * @param array $countryBorders The loaded GeoJSON data
 * @param string $code1 First country code to check (e.g., BD)
 * @param string $code2 Second country code to check (e.g., BI)
 * @return array Diagnostic information
 */
function diagnoseCountryIssue($countryBorders, $code1, $code2)
{
    $diagnosis = [
        'searched_codes' => [$code1, $code2],
        'found_countries' => []
    ];

    // ISO field names we commonly see in the GeoJSON
    $isoFields = ['ISO3166-1-Alpha-2', 'ISO3166-1-Alpha-3', 'iso_a2', 'ISO_A2', 'iso_a3', 'ISO_A3'];

    // Look through all features for any potential matches with either code
    foreach ($countryBorders['features'] as $index => $feature) {
        if (!isset($feature['properties']) || !isset($feature['properties']['name'])) {
            continue;
        }

        $matched = false;
        $matchDetails = [];

        foreach ($isoFields as $field) {
            if (isset($feature['properties'][$field])) {
                $value = strtoupper($feature['properties'][$field]);
                if ($value === $code1 || $value === $code2) {
                    $matched = true;
                    $matchDetails[$field] = $value;
                }
            }
        }

        // Also check if the name contains these codes
        $name = $feature['properties']['name'];
        if (stripos($name, $code1) !== false || stripos($name, $code2) !== false) {
            $matched = true;
            $matchDetails['name_contains_code'] = true;
        }

        // If matched, add to the list
        if ($matched) {
            $diagnosis['found_countries'][] = [
                'index' => $index,
                'name' => $name,
                'match_details' => $matchDetails,
                'properties' => array_map(function ($val) {
                    return is_string($val) ? $val : gettype($val);
                }, $feature['properties'])
            ];
        }
    }

    // Check if there are any features with name Bangladesh or Burundi
    $diagnosis['specific_searches'] = [];

    $specificNames = ['Bangladesh', 'Burundi'];
    foreach ($specificNames as $searchName) {
        $found = false;
        foreach ($countryBorders['features'] as $index => $feature) {
            if (
                isset($feature['properties']['name']) &&
                stripos($feature['properties']['name'], $searchName) !== false
            ) {
                $found = true;
                $diagnosis['specific_searches'][$searchName] = [
                    'found' => true,
                    'index' => $index,
                    'properties' => array_map(function ($val) {
                        return is_string($val) ? $val : gettype($val);
                    }, $feature['properties'])
                ];
                break;
            }
        }

        if (!$found) {
            $diagnosis['specific_searches'][$searchName] = ['found' => false];
        }
    }

    return $diagnosis;
}

// Output the debug information
echo json_encode($debug, JSON_PRETTY_PRINT);
