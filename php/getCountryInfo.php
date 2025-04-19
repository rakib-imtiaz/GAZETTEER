<?php

/**
 * Get detailed information about a country
 * Uses the REST Countries API
 */

require_once 'utilities.php';

// Check if country code is provided
if (!isset($_GET['country'])) {
    echo json_encode(formatResponse(null, STATUS_ERROR, 'Missing country parameter'));
    exit;
}

$countryCode = $_GET['country'];

// Make the API request
$result = makeApiRequest(RESTCOUNTRIES_API_URL . '/alpha/' . $countryCode);

// Check for errors
if ($result['statusCode'] !== 200 || !isset($result['response'][0])) {
    echo json_encode(formatResponse(null, STATUS_NOT_FOUND, 'Country not found'));
    exit;
}

$countryData = $result['response'][0];

// Format the country data
$data = [
    'name' => $countryData['name']['common'],
    'officialName' => $countryData['name']['official'],
    'capital' => isset($countryData['capital'][0]) ? $countryData['capital'][0] : 'N/A',
    'population' => $countryData['population'],
    'area' => isset($countryData['area']) ? $countryData['area'] : 0,
    'region' => $countryData['region'],
    'subregion' => isset($countryData['subregion']) ? $countryData['subregion'] : '',
    'flag' => $countryData['flags']['png'],
    'latlng' => $countryData['latlng'],
    'timezones' => $countryData['timezones'],
    'currencies' => [],
    'languages' => []
];

// Format currency information
if (isset($countryData['currencies'])) {
    foreach ($countryData['currencies'] as $code => $currency) {
        $data['currency'] = [
            'code' => $code,
            'name' => $currency['name'],
            'symbol' => isset($currency['symbol']) ? $currency['symbol'] : ''
        ];
        break; // Just take the first currency
    }
}

// Format language information
if (isset($countryData['languages'])) {
    foreach ($countryData['languages'] as $language) {
        $data['languages'][] = $language;
    }
}

echo json_encode(formatResponse($data, STATUS_OK));
