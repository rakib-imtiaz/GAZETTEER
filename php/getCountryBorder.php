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

// Log debugging information
error_log("Request for country border: " . $countryCode);

// First, get the country name from RESTCountries API for reliable matching
$result = makeApiRequest(RESTCOUNTRIES_API_URL . '/alpha/' . $countryCode, 'GET', [
    'fields' => 'name,cca2,cca3'
]);

// Debug REST Countries API response
if ($result['statusCode'] === 200 && isset($result['response'][0])) {
    error_log("REST Countries API found country: " . $result['response'][0]['name']['common']);
    if (isset($result['response'][0]['cca2'])) {
        error_log("Country ISO2 code: " . $result['response'][0]['cca2']);
    }
    if (isset($result['response'][0]['cca3'])) {
        error_log("Country ISO3 code: " . $result['response'][0]['cca3']);
    }
} else {
    error_log("REST Countries API could not find country with code: " . $countryCode);
}

// Get country name variations to try matching with GeoJSON
$countryNames = [];
$iso3Code = null;
$countryFound = false;
$country = null;

if ($result['statusCode'] === 200 && isset($result['response'][0]['name'])) {
    // Add common name and official name to the list of names to check
    $countryNames[] = $result['response'][0]['name']['common'];
    $countryNames[] = $result['response'][0]['name']['official'];

    // Store ISO3 code if available
    if (isset($result['response'][0]['cca3'])) {
        $iso3Code = $result['response'][0]['cca3'];
    }

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
    error_log("Failed to load country borders file");
    echo json_encode(formatResponse(null, STATUS_ERROR, 'Failed to load country borders file'));
    exit;
}

// First, try to find direct match by ISO code (most reliable)
$country = findCountryByCode($countryCode);

// If we have ISO3 code and direct match failed, try with ISO3
if ($country === null && $iso3Code !== null) {
    error_log("Trying to find country with ISO3 code: " . $iso3Code);
    $country = findCountryByCode($iso3Code);
}

// If still not found, try to find by country name
if ($country === null) {
    error_log("Direct code match failed, trying name match");
    foreach ($countryNames as $name) {
        // Skip empty names
        if (empty($name)) continue;

        error_log("Trying to match country by name: " . $name);
        foreach ($countryBorders['features'] as $feature) {
            if (!isset($feature['properties']) || !isset($feature['properties']['name'])) {
                continue;
            }

            // Check for exact name match (case insensitive)
            if (strtolower($feature['properties']['name']) === strtolower($name)) {
                $country = $feature;
                error_log("Found exact name match: " . $feature['properties']['name']);
                $countryFound = true;
                break 2;
            }
        }
    }
}

if ($country === null) {
    error_log("Country not found in GeoJSON file: " . $countryCode);
    echo json_encode(formatResponse(null, STATUS_NOT_FOUND, 'Country not found in GeoJSON file'));
    exit;
}

// Verify we have the correct country by checking the properties
error_log("Found country in GeoJSON: " . ($country['properties']['name'] ?? 'Unknown'));

// Return the country border geometry
echo json_encode(formatResponse($country, STATUS_OK));
