<?php

/**
 * Test file to verify API functionality
 */

// Test API endpoints
$tests = [
    "getCountryList" => "/php/getCountryList.php",
    "getCountryInfo (US)" => "/php/getCountryInfo.php?country=US",
    "getCountryBorder (US)" => "/php/getCountryBorder.php?countryCode=US",
    "getWeather (London)" => "/php/getWeather.php?city=London",
    "getWikipediaLinks (United States)" => "/php/getWikipediaLinks.php?country=United+States"
];

// Get base URL
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
$baseUrl = $protocol . "://" . $_SERVER['HTTP_HOST'] . '/GAZETTEER';

// Output
echo "<h1>Gazetteer API Tests</h1>";
echo "<p>Testing API endpoints to verify functionality.</p>";
echo "<hr>";

// Test each endpoint
foreach ($tests as $name => $endpoint) {
    echo "<h3>Testing: $name</h3>";
    echo "<p>Endpoint: $endpoint</p>";

    $url = $baseUrl . $endpoint;
    echo "<p>Full URL: <a href='$url' target='_blank'>$url</a></p>";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $result = json_decode($response, true);

    echo "<p>Status: " . ($httpcode == 200 ? "<span style='color:green'>OK ($httpcode)</span>" : "<span style='color:red'>FAILED ($httpcode)</span>") . "</p>";

    if ($result && isset($result['status'])) {
        echo "<p>API Status: " . ($result['status']['code'] == 200 ? "<span style='color:green'>OK</span>" : "<span style='color:red'>ERROR</span>") . "</p>";
        echo "<p>Message: " . htmlentities($result['status']['message']) . "</p>";
    } else {
        echo "<p style='color:red'>Invalid response format</p>";
    }

    echo "<details><summary>View Response</summary>";
    echo "<pre>" . htmlentities($response) . "</pre>";
    echo "</details>";

    echo "<hr>";
}
