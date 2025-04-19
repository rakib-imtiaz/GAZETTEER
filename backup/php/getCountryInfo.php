<?php

/**
 * Get detailed information about a country
 * Uses the RestCountries API to fetch country information
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

// Make the API request to RestCountries
$apiUrl = RESTCOUNTRIES_API_URL . '/alpha/' . $countryCode;
$result = makeApiRequest($apiUrl);

// Check for errors
if ($result['statusCode'] !== 200 || !isset($result['response']) || empty($result['response'])) {
    echo json_encode(formatResponse(null, STATUS_ERROR, 'Failed to get country information'));
    exit;
}

$countryData = $result['response'];

// Extract and format the response data
$languages = [];
if (isset($countryData['languages'])) {
    foreach ($countryData['languages'] as $code => $name) {
        $languages[] = $name;
    }
}

$currency = [
    'name' => 'N/A',
    'code' => 'N/A',
    'symbol' => 'N/A'
];

if (isset($countryData['currencies'])) {
    $currencyCode = array_key_first($countryData['currencies']);
    if ($currencyCode) {
        $currencyInfo = $countryData['currencies'][$currencyCode];
        $currency = [
            'name' => $currencyInfo['name'] ?? 'N/A',
            'code' => $currencyCode,
            'symbol' => $currencyInfo['symbol'] ?? 'N/A'
        ];
    }
}

// Format the data
$data = [
    'name' => $countryData['name']['common'] ?? 'N/A',
    'officialName' => $countryData['name']['official'] ?? 'N/A',
    'capital' => isset($countryData['capital']) && !empty($countryData['capital']) ? $countryData['capital'][0] : 'N/A',
    'region' => $countryData['region'] ?? 'N/A',
    'subregion' => $countryData['subregion'] ?? 'N/A',
    'population' => $countryData['population'] ?? 0,
    'area' => $countryData['area'] ?? 0,
    'languages' => $languages,
    'flag' => $countryData['flags']['png'] ?? '',
    'latlng' => $countryData['latlng'] ?? [0, 0],
    'timezones' => $countryData['timezones'] ?? ['UTC'],
    'borders' => $countryData['borders'] ?? [],
    'currency' => $currency,
    'alpha2Code' => $countryData['cca2'] ?? 'N/A',
    'alpha3Code' => $countryData['cca3'] ?? 'N/A'
];

// Return the country information
echo json_encode(formatResponse($data, STATUS_OK));
