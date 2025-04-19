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
 * @param string $method The HTTP method (GET, POST, etc.)
 * @param array $params The parameters to send with the request
 * @param array $headers Additional headers to send with the request
 * @return array The response and status code
 */
function makeApiRequest($url, $method = 'GET', $params = [], $headers = [])
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

    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
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
 * @param array $data The data to return
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
 * 
 * @return array|null The country borders data or null on error
 */
function loadCountryBorders()
{
    $filePath = __DIR__ . '/' . COUNTRY_BORDERS_FILE;

    if (!file_exists($filePath)) {
        return null;
    }

    $fileContents = file_get_contents($filePath);
    if ($fileContents === false) {
        return null;
    }

    $countryBorders = json_decode($fileContents, true);
    if ($countryBorders === null) {
        return null;
    }

    return $countryBorders;
}

/**
 * Find a country in the GeoJSON file by ISO code
 * 
 * @param string $countryCode The ISO 2-character country code
 * @return array|null The country data or null if not found
 */
function findCountryByCode($countryCode)
{
    $countryBorders = loadCountryBorders();

    if ($countryBorders === null) {
        return null;
    }

    foreach ($countryBorders['features'] as $feature) {
        if ($feature['properties']['iso_a2'] === $countryCode || $feature['properties']['iso_a3'] === $countryCode) {
            return $feature;
        }
    }

    return null;
}

/**
 * Convert a value from one unit to another
 * 
 * @param float $value The value to convert
 * @param string $from The unit to convert from
 * @param string $to The unit to convert to
 * @return float The converted value
 */
function convertUnits($value, $from, $to)
{
    // Conversion factors
    $factors = [
        'km_to_miles' => 0.621371,
        'miles_to_km' => 1.60934,
        'c_to_f' => function ($c) {
            return ($c * 9 / 5) + 32;
        },
        'f_to_c' => function ($f) {
            return ($f - 32) * 5 / 9;
        },
        'm_to_ft' => 3.28084,
        'ft_to_m' => 0.3048
    ];

    $conversionKey = strtolower($from) . '_to_' . strtolower($to);

    if (isset($factors[$conversionKey])) {
        $factor = $factors[$conversionKey];

        if (is_callable($factor)) {
            return $factor($value);
        } else {
            return $value * $factor;
        }
    }

    // If no conversion factor found, return the original value
    return $value;
}

/**
 * Format a number with commas for thousands
 * 
 * @param int|float $number The number to format
 * @param int $decimals The number of decimal places
 * @return string The formatted number
 */
function formatNumber($number, $decimals = 0)
{
    return number_format($number, $decimals);
}
