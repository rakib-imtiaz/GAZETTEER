<?php

/**
 * Get weather data for a location
 * Uses the OpenWeather API to fetch current weather and forecast
 */

require_once 'utilities.php';

// Check if the request has required parameters
if ((!isset($_GET['city']) || empty($_GET['city'])) &&
    (!isset($_GET['lat']) || !isset($_GET['lng']))
) {
    echo json_encode(formatResponse(null, STATUS_ERROR, 'Missing location parameters (city or coordinates)'));
    exit;
}

// Use coordinates if provided, otherwise use city name
if (isset($_GET['lat']) && isset($_GET['lng']) && is_numeric($_GET['lat']) && is_numeric($_GET['lng'])) {
    $lat = $_GET['lat'];
    $lng = $_GET['lng'];

    // Validate latitude and longitude
    if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
        echo json_encode(formatResponse(null, STATUS_ERROR, 'Invalid latitude or longitude values'));
        exit;
    }

    // Request parameters for OpenWeather API (current weather)
    $currentParams = [
        'lat' => $lat,
        'lon' => $lng,
        'appid' => OPENWEATHER_API_KEY,
        'units' => 'metric'
    ];

    // Request parameters for OpenWeather API (forecast)
    $forecastParams = [
        'lat' => $lat,
        'lon' => $lng,
        'appid' => OPENWEATHER_API_KEY,
        'units' => 'metric'
    ];
} else {
    $city = urlencode($_GET['city']);

    // Request parameters for OpenWeather API (current weather)
    $currentParams = [
        'q' => $city,
        'appid' => OPENWEATHER_API_KEY,
        'units' => 'metric'
    ];

    // Request parameters for OpenWeather API (forecast)
    $forecastParams = [
        'q' => $city,
        'appid' => OPENWEATHER_API_KEY,
        'units' => 'metric'
    ];
}

// Make API requests
$currentResult = makeApiRequest(OPENWEATHER_API_URL . '/weather', 'GET', $currentParams);
$forecastResult = makeApiRequest(OPENWEATHER_API_URL . '/forecast', 'GET', $forecastParams);

// Check for errors in current weather
if ($currentResult['statusCode'] !== 200 || !isset($currentResult['response'])) {
    echo json_encode(formatResponse(null, STATUS_ERROR, 'Failed to get current weather data'));
    exit;
}

// Current weather data
$currentWeather = [
    'temp' => round($currentResult['response']['main']['temp']),
    'description' => ucfirst($currentResult['response']['weather'][0]['description']),
    'icon' => $currentResult['response']['weather'][0]['icon'],
    'humidity' => $currentResult['response']['main']['humidity'],
    'wind' => round($currentResult['response']['wind']['speed'], 1),
    'pressure' => $currentResult['response']['main']['pressure'],
    'sunrise' => date('H:i', $currentResult['response']['sys']['sunrise']),
    'sunset' => date('H:i', $currentResult['response']['sys']['sunset'])
];

// Forecast data
$forecast = [];

if ($forecastResult['statusCode'] === 200 && isset($forecastResult['response']['list'])) {
    $processedDates = [];

    foreach ($forecastResult['response']['list'] as $item) {
        $date = date('Y-m-d', $item['dt']);

        // Only take one forecast per day (noon if available)
        $hour = date('H', $item['dt']);

        // If we haven't processed this date yet, or if this is noon (better representation of the day)
        if (!in_array($date, $processedDates) || $hour == 12) {
            $forecast[] = [
                'date' => date('D', $item['dt']), // Day of week (e.g., "Mon")
                'temp' => round($item['main']['temp']),
                'description' => ucfirst($item['weather'][0]['description']),
                'icon' => $item['weather'][0]['icon'],
                'humidity' => $item['main']['humidity'],
                'wind' => round($item['wind']['speed'], 1)
            ];

            if (!in_array($date, $processedDates)) {
                $processedDates[] = $date;
            }

            // If we have enough days, stop
            if (count($processedDates) >= 5) {
                break;
            }
        }
    }
}

// Combine current and forecast data
$data = [
    'current' => $currentWeather,
    'forecast' => $forecast
];

// Return the weather data
echo json_encode(formatResponse($data, STATUS_OK));
