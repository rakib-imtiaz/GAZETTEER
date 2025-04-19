<?php

/**
 * Get country border geometry
 * Uses the local GeoJSON file combined with RESTCountries API for reliable country matching
 */

require_once 'utilities.php';

// Check if country code is provided
if (!isset($_GET['countryCode'])) {
    echo json_encode(formatResponse(null, STATUS_ERROR, 'Missing country code parameter'));
    exit;
}

$countryCode = strtoupper($_GET['countryCode']);

// First, get the country name from RESTCountries API for reliable matching
$result = makeApiRequest(RESTCOUNTRIES_API_URL . '/alpha/' . $countryCode, 'GET', [
    'fields' => 'name'
]);

// Get country name variations to try matching with GeoJSON
$countryNames = [];
$countryFound = false;
$country = null;

if ($result['statusCode'] === 200 && isset($result['response'][0]['name'])) {
    // Add common name and official name to the list of names to check
    $countryNames[] = $result['response'][0]['name']['common'];
    $countryNames[] = $result['response'][0]['name']['official'];

    // Add native name variations if available
    if (isset($result['response'][0]['name']['nativeName'])) {
        foreach ($result['response'][0]['name']['nativeName'] as $lang => $names) {
            $countryNames[] = $names['common'];
            $countryNames[] = $names['official'];
        }
    }
}

// Always include the country code
$countryNames[] = $countryCode;

// Load country borders
$countryBorders = loadCountryBorders();
if ($countryBorders === null) {
    echo json_encode(formatResponse(null, STATUS_ERROR, 'Failed to load country borders file'));
    exit;
}

// Try to find the country using its name or code
foreach ($countryBorders['features'] as $feature) {
    if (!isset($feature['properties'])) {
        continue;
    }

    // Check all properties for a match with any country name variation
    foreach ($feature['properties'] as $key => $value) {
        if (!is_string($value)) {
            continue;
        }

        // Check the value against all country names
        foreach ($countryNames as $name) {
            if (
                strtoupper($value) === strtoupper($name) ||
                strpos(strtoupper($value), strtoupper($name)) !== false ||
                strpos(strtoupper($name), strtoupper($value)) !== false
            ) {
                $country = $feature;
                $countryFound = true;
                break 3; // Break all loops
            }
        }
    }
}

// If still not found, try to directly match country code
if (!$countryFound) {
    foreach ($countryBorders['features'] as $feature) {
        if (!isset($feature['properties'])) {
            continue;
        }

        // Common fields that might contain country codes
        $codeFields = ['iso_a2', 'ISO_A2', 'iso_a3', 'ISO_A3', 'ADM0_A3', 'ISO_CODE', 'FIPS'];

        foreach ($codeFields as $field) {
            if (
                isset($feature['properties'][$field]) &&
                strtoupper($feature['properties'][$field]) === $countryCode
            ) {
                $country = $feature;
                $countryFound = true;
                break 2;
            }
        }
    }
}

if (!$countryFound || $country === null) {
    echo json_encode(formatResponse(null, STATUS_NOT_FOUND, 'Country not found in GeoJSON file'));
    exit;
}

// Return the country border geometry
echo json_encode(formatResponse($country, STATUS_OK));
