<?php

/**
 * Get the list of countries for the dropdown
 * Parses the GeoJSON file to extract country names and codes
 */

require_once 'utilities.php';

// Load the country borders
$countryBorders = loadCountryBorders();

if ($countryBorders === null) {
    echo json_encode(formatResponse(null, STATUS_SERVER_ERROR, 'Failed to load country borders data'));
    exit;
}

// Extract country names and codes
$countries = [];

foreach ($countryBorders['features'] as $feature) {
    // Skip if the country doesn't have required properties
    if (
        !isset($feature['properties']['name']) ||
        !isset($feature['properties']['iso_a2']) ||
        empty($feature['properties']['name']) ||
        empty($feature['properties']['iso_a2'])
    ) {
        continue;
    }

    // Use ISO_A2 code (2-letter code) as the preferred code
    $countryCode = $feature['properties']['iso_a2'];

    // If ISO_A2 is not valid, fall back to ISO_A3
    if ($countryCode === '-99' && isset($feature['properties']['iso_a3']) && $feature['properties']['iso_a3'] !== '-99') {
        $countryCode = $feature['properties']['iso_a3'];
    }

    // Skip countries with invalid codes
    if ($countryCode === '-99') {
        continue;
    }

    $countries[] = [
        'name' => $feature['properties']['name'],
        'code' => $countryCode
    ];
}

// Return the country list
echo json_encode(formatResponse($countries, STATUS_OK));
