<?php

/**
 * Configuration file for the Gazetteer application
 * Contains API keys, constants, and configuration settings
 */

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// API Keys (you should replace these with your own keys)
define('OPENCAGE_API_KEY', 'e12a6c2f9f184cc38b3939eea80aff71');
define('OPENWEATHER_API_KEY', 'e0d67ef2ce31ccd0bdf4248b59e69e2d');
define('GEONAMES_USERNAME', 'dhanyaal19882');
define('OPENEXCHANGE_API_KEY', 'f346af9f386f0efe128c8a41531f360e');

// API Endpoints
define('OPENCAGE_API_URL', 'https://api.opencagedata.com/geocode/v1/json');
define('OPENWEATHER_API_URL', 'https://api.openweathermap.org/data/2.5');
define('RESTCOUNTRIES_API_URL', 'https://restcountries.com/v3.1');
define('GEONAMES_API_URL', 'http://api.geonames.org');
define('OPENEXCHANGE_API_URL', 'https://openexchangerates.org/api');

// File paths
define('COUNTRY_BORDERS_FILE', '../data/countryBorders.geo.json');

// Response status codes
define('STATUS_OK', 200);
define('STATUS_ERROR', 400);
define('STATUS_NOT_FOUND', 404);
define('STATUS_SERVER_ERROR', 500);

// Misc settings
define('POI_RADIUS', 50); // Radius in km for Points of Interest search
define('WIKIPEDIA_RESULTS_LIMIT', 5); // Number of Wikipedia results to return 