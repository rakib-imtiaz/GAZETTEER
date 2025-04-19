<?php

/**
 * Get a list of all countries with their ISO codes
 * Uses the REST Countries API
 */

require_once 'utilities.php';

// Make the API request
$result = makeApiRequest(RESTCOUNTRIES_API_URL . '/all', 'GET', [
    'fields' => 'name,cca2'
]);

// Check for errors
if ($result['statusCode'] !== 200 || !isset($result['response']) || empty($result['response'])) {
    echo json_encode(formatResponse(null, STATUS_ERROR, 'Failed to get country list'));
    exit;
}

// Format the country data
$countries = [];
foreach ($result['response'] as $country) {
    $countries[] = [
        'name' => $country['name']['common'],
        'code' => $country['cca2']
    ];
}

// Return the list of countries
echo json_encode(formatResponse($countries, STATUS_OK));
