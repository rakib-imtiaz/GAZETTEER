<?php

/**
 * Get the country code from coordinates
 * Uses OpenCage Geocoding API to reverse geocode the given coordinates
 */

require_once 'utilities.php';

// Check if the request has latitude and longitude parameters
if (!isset($_GET['lat']) || !isset($_GET['lng'])) {
    echo json_encode(formatResponse(null, STATUS_ERROR, 'Missing latitude or longitude parameters'));
    exit;
}

$lat = $_GET['lat'];
$lng = $_GET['lng'];

// Validate latitude and longitude
if (!is_numeric($lat) || !is_numeric($lng) || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
    echo json_encode(formatResponse(null, STATUS_ERROR, 'Invalid latitude or longitude values'));
    exit;
}

// Request parameters for OpenCage API
$params = [
    'q' => "$lat,$lng",
    'key' => OPENCAGE_API_KEY,
    'no_annotations' => 1,
    'language' => 'en'
];

// Make the API request
$result = makeApiRequest(OPENCAGE_API_URL, 'GET', $params);

// Check for errors
if ($result['statusCode'] !== 200 || !isset($result['response']) || !isset($result['response']['results']) || empty($result['response']['results'])) {
    echo json_encode(formatResponse(null, STATUS_ERROR, 'Failed to get country information from coordinates'));
    exit;
}

// Extract country information from the response
$country = $result['response']['results'][0]['components']['country'];
$countryCode = $result['response']['results'][0]['components']['country_code'];

if (empty($countryCode)) {
    echo json_encode(formatResponse(null, STATUS_NOT_FOUND, 'No country found for the given coordinates'));
    exit;
}

// Return the country information
$data = [
    'country' => $country,
    'countryCode' => strtoupper($countryCode)
];

echo json_encode(formatResponse($data, STATUS_OK));
