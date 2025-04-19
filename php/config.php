<?php
// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Increase memory limit for large GeoJSON files
ini_set('memory_limit', '256M');

// API Keys
define('OPENCAGE_API_KEY', 'e12a6c2f9f184cc38b3939eea80aff71');
define('OPENWEATHER_API_KEY', 'e0d67ef2ce31ccd0bdf4248b59e69e2d');
define('GEONAMES_USERNAME', 'dhanyaal19882');

// API Endpoints
define('OPENCAGE_API_URL', 'https://api.opencagedata.com/geocode/v1/json');
define('OPENWEATHER_API_URL', 'https://api.openweathermap.org/data/2.5');
define('RESTCOUNTRIES_API_URL', 'https://restcountries.com/v3.1');
define('GEONAMES_API_URL', 'http://api.geonames.org');

// File paths
define('COUNTRY_BORDERS_FILE', '../data/countryBorders.geo.json');

// Response status codes
define('STATUS_OK', 200);
define('STATUS_ERROR', 400);
define('STATUS_NOT_FOUND', 404);
define('STATUS_SERVER_ERROR', 500);

// Misc settings
define('WIKIPEDIA_RESULTS_LIMIT', 3); // Number of Wikipedia results to return
