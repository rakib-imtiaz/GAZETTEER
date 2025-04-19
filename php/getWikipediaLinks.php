<?php

/**
 * Get Wikipedia links related to a country
 * Uses the Geonames API
 */

require_once 'utilities.php';

// Check if country parameter is provided
if (!isset($_GET['country'])) {
    echo json_encode(formatResponse(null, STATUS_ERROR, 'Missing country parameter'));
    exit;
}

$country = $_GET['country'];

// Make the API request
$result = makeApiRequest(GEONAMES_API_URL . '/wikipediaSearch', 'GET', [
    'q' => $country,
    'title' => $country,
    'maxRows' => WIKIPEDIA_RESULTS_LIMIT,
    'username' => GEONAMES_USERNAME,
    'type' => 'json'
]);

// Check for errors
if ($result['statusCode'] !== 200 || !isset($result['response']['geonames'])) {
    echo json_encode(formatResponse(null, STATUS_ERROR, 'Failed to get Wikipedia links'));
    exit;
}

$wikiEntries = $result['response']['geonames'];

// Format the Wikipedia entries
$links = [];
foreach ($wikiEntries as $entry) {
    $links[] = [
        'title' => $entry['title'],
        'summary' => isset($entry['summary']) ? $entry['summary'] : 'No summary available',
        'url' => 'https://' . $entry['wikipediaUrl']
    ];
}

echo json_encode(formatResponse($links, STATUS_OK));
