<?php

/**
 * Utilities for the Gazetteer application
 * Contains helper functions for API requests and responses
 */

require_once 'config.php';

/**
 * Make an API request using cURL
 * 
 * @param string $url The URL to make the request to
 * @param string $method The HTTP method (GET, POST)
 * @param array $params The parameters to send with the request
 * @return array The response data and status code
 */
function makeApiRequest($url, $method = 'GET', $params = [])
{
    $ch = curl_init();

    // Set cURL options
    if ($method === 'GET' && !empty($params)) {
        $url .= '?' . http_build_query($params);
    }

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    }

    // Execute cURL request
    $response = curl_exec($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    // Check for errors
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        return [
            'response' => null,
            'statusCode' => STATUS_SERVER_ERROR,
            'error' => $error
        ];
    }

    curl_close($ch);

    // Parse JSON response
    $responseData = json_decode($response, true);

    return [
        'response' => $responseData,
        'statusCode' => $statusCode
    ];
}

/**
 * Format a standardized API response
 * 
 * @param mixed $data The data to return
 * @param int $statusCode The HTTP status code
 * @param string $message A message to include with the response
 * @return array The formatted response
 */
function formatResponse($data = null, $statusCode = STATUS_OK, $message = '')
{
    $response = [
        'status' => [
            'code' => $statusCode,
            'message' => $message ?: ($statusCode === STATUS_OK ? 'Success' : 'Error')
        ]
    ];

    if ($data !== null) {
        $response['data'] = $data;
    }

    return $response;
}

/**
 * Load country borders from the GeoJSON file
 * Uses caching to improve performance
 * 
 * @return array|null The country borders data or null on error
 */
function loadCountryBorders()
{
    static $countryBorders = null;

    // Return cached data if available
    if ($countryBorders !== null) {
        return $countryBorders;
    }

    $filePath = __DIR__ . '/' . COUNTRY_BORDERS_FILE;

    // Debug info
    error_log("Trying to load country borders from: " . $filePath);

    if (!file_exists($filePath)) {
        error_log("File not found: " . $filePath);

        // Try alternative path
        $altPath = __DIR__ . '/../data/countryBorders.geo.json';
        error_log("Trying alternative path: " . $altPath);

        if (file_exists($altPath)) {
            $filePath = $altPath;
            error_log("Found file at alternative path: " . $altPath);
        } else {
            error_log("File not found at alternative path either");
            return null;
        }
    }

    // Check file size and adjust memory limit if needed
    $fileSize = filesize($filePath);
    error_log("GeoJSON file size: " . ($fileSize / 1024 / 1024) . " MB");

    // For large files, increase memory limit
    if ($fileSize > 50 * 1024 * 1024) {
        ini_set('memory_limit', '512M');
        error_log("Increased memory limit to 512M for large GeoJSON file");
    }

    $fileContents = file_get_contents($filePath);
    if ($fileContents === false) {
        error_log("Failed to read file contents from: " . $filePath);
        return null;
    }

    $countryBorders = json_decode($fileContents, true);
    if ($countryBorders === null) {
        error_log("Failed to parse JSON from file: " . $filePath . " - JSON error: " . json_last_error_msg());
        return null;
    }

    error_log("Successfully loaded country borders: " . count($countryBorders['features']) . " features");
    return $countryBorders;
}

/**
 * Find a country in the GeoJSON file by ISO code
 * 
 * @param string $countryCode The ISO country code
 * @return array|null The country data or null if not found
 */
function findCountryByCode($countryCode)
{
    $countryBorders = loadCountryBorders();

    if ($countryBorders === null) {
        return null;
    }

    // Ensure country code is uppercase
    $countryCode = strtoupper($countryCode);

    // From test results, we know the actual fields used in this GeoJSON file
    $iso2Field = 'ISO3166-1-Alpha-2';
    $iso3Field = 'ISO3166-1-Alpha-3';

    // Search for the country in the GeoJSON features
    foreach ($countryBorders['features'] as $feature) {
        if (!isset($feature['properties'])) {
            continue;
        }

        // Check for the exact field names we discovered in the test
        if (
            isset($feature['properties'][$iso2Field]) &&
            strtoupper($feature['properties'][$iso2Field]) === $countryCode
        ) {
            return $feature;
        }

        if (
            isset($feature['properties'][$iso3Field]) &&
            strtoupper($feature['properties'][$iso3Field]) === $countryCode
        ) {
            return $feature;
        }

        // Check standard field variants as fallback
        $standardFields = ['iso_a2', 'ISO_A2', 'iso_a3', 'ISO_A3', 'ISO2', 'ISO3'];
        foreach ($standardFields as $field) {
            if (
                isset($feature['properties'][$field]) &&
                strtoupper($feature['properties'][$field]) === $countryCode
            ) {
                return $feature;
            }
        }

        // Check if the country code is part of the name (last resort)
        if (isset($feature['properties']['name'])) {
            $name = $feature['properties']['name'];
            // Either the code equals the name exactly or the name contains the code
            if (
                strtoupper($name) === $countryCode ||
                strpos(strtoupper($name), $countryCode) !== false
            ) {
                return $feature;
            }
        }
    }

    // For 2-letter codes, try to convert to 3-letter code if no match yet
    if (strlen($countryCode) === 2) {
        // Map of some common 2-letter to 3-letter codes for countries
        $iso2to3 = [
            'US' => 'USA',
            'GB' => 'GBR',
            'FR' => 'FRA',
            'DE' => 'DEU',
            'JP' => 'JPN',
            'CN' => 'CHN',
            'CA' => 'CAN',
            'AU' => 'AUS',
            'BR' => 'BRA',
            'RU' => 'RUS',
            'IN' => 'IND',
            'ZA' => 'ZAF'
        ];

        if (isset($iso2to3[$countryCode])) {
            // Search using the 3-letter code
            foreach ($countryBorders['features'] as $feature) {
                if (!isset($feature['properties'])) {
                    continue;
                }

                if (
                    isset($feature['properties'][$iso3Field]) &&
                    strtoupper($feature['properties'][$iso3Field]) === $iso2to3[$countryCode]
                ) {
                    return $feature;
                }
            }
        }
    }

    return null;
}
