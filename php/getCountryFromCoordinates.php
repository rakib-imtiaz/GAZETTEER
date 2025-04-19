<?php

/**
 * Get country information from coordinates
 * Uses the OpenCage API for reverse geocoding
 */

require_once 'utilities.php';

// Check if required parameters are provided
if (!isset($_GET['lat']) || !isset($_GET['lng'])) {
    echo json_encode(formatResponse(null, STATUS_ERROR, 'Missing latitude or longitude parameters'));
    exit;
}

$lat = $_GET['lat'];
$lng = $_GET['lng'];

// Validate coordinates
if (!is_numeric($lat) || !is_numeric($lng) || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
    echo json_encode(formatResponse(null, STATUS_ERROR, 'Invalid coordinates'));
    exit;
}

// Make API request to OpenCage
$result = makeApiRequest(OPENCAGE_API_URL, 'GET', [
    'q' => "$lat,$lng",
    'key' => OPENCAGE_API_KEY,
    'no_annotations' => 1
]);

// Check for errors
if ($result['statusCode'] !== 200 || !isset($result['response']['results']) || empty($result['response']['results'])) {
    echo json_encode(formatResponse(null, STATUS_ERROR, 'Failed to get country from coordinates'));
    exit;
}

// Extract country information
$countryInfo = $result['response']['results'][0]['components'];
$countryCode = isset($countryInfo['country_code']) ? strtoupper($countryInfo['country_code']) : null;
$countryName = isset($countryInfo['country']) ? $countryInfo['country'] : null;

if (empty($countryCode) || empty($countryName)) {
    echo json_encode(formatResponse(null, STATUS_NOT_FOUND, 'No country found at these coordinates'));
    exit;
}

// Return the country information
$data = [
    'countryName' => $countryName,
    'countryCode' => $countryCode
];

echo json_encode(formatResponse($data, STATUS_OK));
