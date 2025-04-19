<?php

/**
 * Get points of interest near a location
 * Uses the GeoNames API to fetch points of interest
 */

require_once 'utilities.php';

// Check if the request has required parameters
if (!isset($_GET['lat']) || !isset($_GET['lng'])) {
    echo json_encode(formatResponse(null, STATUS_ERROR, 'Missing coordinates parameters'));
    exit;
}

$lat = $_GET['lat'];
$lng = $_GET['lng'];

// Validate latitude and longitude
if (!is_numeric($lat) || !is_numeric($lng) || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
    echo json_encode(formatResponse(null, STATUS_ERROR, 'Invalid latitude or longitude values'));
    exit;
}

// Request parameters for GeoNames API
$params = [
    'lat' => $lat,
    'lng' => $lng,
    'radius' => POI_RADIUS,
    'maxRows' => 20,
    'username' => GEONAMES_USERNAME,
    'style' => 'full',
    'featureClass' => 'P', // Populated places
    'featureClass' => 'A', // Administrative areas
    'featureClass' => 'L', // Parks, areas, etc.
    'featureClass' => 'H', // Streams, lakes, etc.
    'featureClass' => 'V', // Forest, heaths, etc.
    'featureClass' => 'T', // Mountains, etc.
    'featureClass' => 'R', // Roads, railroads, etc.
    'featureClass' => 'S', // Spots, buildings, farms, etc.
];

// Make the API request
$result = makeApiRequest(GEONAMES_API_URL . '/findNearbyJSON', 'GET', $params);

// Check for errors
if ($result['statusCode'] !== 200 || !isset($result['response']) || !isset($result['response']['geonames'])) {
    echo json_encode(formatResponse(null, STATUS_ERROR, 'Failed to get points of interest'));
    exit;
}

// Format the points of interest
$pois = [];
foreach ($result['response']['geonames'] as $poi) {
    $pois[] = [
        'name' => $poi['name'],
        'countryName' => $poi['countryName'],
        'lat' => $poi['lat'],
        'lng' => $poi['lng'],
        'feature' => $poi['fcodeName'] ?? 'Point of Interest',
        'population' => $poi['population'] ?? null,
        'elevation' => $poi['elevation'] ?? null
    ];
}

// Return the points of interest
echo json_encode(formatResponse($pois, STATUS_OK));
