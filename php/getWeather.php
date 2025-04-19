<?php

/**
 * Get weather data for a city
 * Uses the OpenWeather API
 */

require_once 'utilities.php';

// Check if city parameter is provided
if (!isset($_GET['city'])) {
    echo json_encode(formatResponse(null, STATUS_ERROR, 'Missing city parameter'));
    exit;
}

$city = $_GET['city'];

// Make the API request to get current weather
$result = makeApiRequest(OPENWEATHER_API_URL . '/weather', 'GET', [
    'q' => $city,
    'appid' => OPENWEATHER_API_KEY,
    'units' => 'metric'
]);

// Check for errors
if ($result['statusCode'] !== 200 || !isset($result['response'])) {
    echo json_encode(formatResponse(null, STATUS_ERROR, 'Failed to get weather data'));
    exit;
}

$weatherData = $result['response'];

// Format the weather data
$data = [
    'current' => [
        'temp' => round($weatherData['main']['temp']),
        'description' => $weatherData['weather'][0]['description'],
        'icon' => $weatherData['weather'][0]['icon'],
        'humidity' => $weatherData['main']['humidity'],
        'wind' => $weatherData['wind']['speed']
    ]
];

echo json_encode(formatResponse($data, STATUS_OK));
