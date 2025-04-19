<?php

/**
 * Get the country border in GeoJSON format
 * Retrieves the country border from the GeoJSON file
 */

require_once 'utilities.php';

// Check if the request has a country parameter
if (!isset($_GET['country'])) {
    echo json_encode(formatResponse(null, STATUS_ERROR, 'Missing country parameter'));
    exit;
}

$countryCode = strtoupper($_GET['country']);

// Validate country code (basic validation)
if (!preg_match('/^[A-Z]{2,3}$/', $countryCode)) {
    echo json_encode(formatResponse(null, STATUS_ERROR, 'Invalid country code format'));
    exit;
}

// Find the country in the GeoJSON file
$country = findCountryByCode($countryCode);

if ($country === null) {
    echo json_encode(formatResponse(null, STATUS_NOT_FOUND, 'Country borders not found or GeoJSON file error'));
    exit;
}

// Return the country border
echo json_encode(formatResponse($country, STATUS_OK));
