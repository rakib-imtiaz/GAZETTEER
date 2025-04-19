<?php

/**
 * Get Wikipedia links related to a country
 * Uses the GeoNames API to fetch Wikipedia articles
 */

require_once 'utilities.php';

// Check if the request has a country parameter
if (!isset($_GET['country']) || empty($_GET['country'])) {
    echo json_encode(formatResponse(null, STATUS_ERROR, 'Missing country parameter'));
    exit;
}

$country = $_GET['country'];

// Request parameters for GeoNames API
$params = [
    'q' => $country,
    'maxRows' => WIKIPEDIA_RESULTS_LIMIT,
    'username' => GEONAMES_USERNAME,
    'type' => 'json',
    'title' => 'wikipedia'
];

// Make the API request
$result = makeApiRequest(GEONAMES_API_URL . '/wikipediaSearch', 'GET', $params);

// Check for errors
if ($result['statusCode'] !== 200 || !isset($result['response']) || !isset($result['response']['geonames'])) {
    echo json_encode(formatResponse(null, STATUS_ERROR, 'Failed to get Wikipedia links'));
    exit;
}

// Format the Wikipedia links
$wikiLinks = [];
foreach ($result['response']['geonames'] as $article) {
    $wikiLinks[] = [
        'title' => $article['title'],
        'summary' => $article['summary'],
        'url' => "https://{$article['wikipediaUrl']}",
        'thumbnailUrl' => isset($article['thumbnailImg']) ? $article['thumbnailImg'] : null
    ];
}

// Return the Wikipedia links
echo json_encode(formatResponse($wikiLinks, STATUS_OK));
