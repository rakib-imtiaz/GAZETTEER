<?php

/**
 * Get exchange rates for a currency
 * Uses the Open Exchange Rates API to fetch exchange rates
 */

require_once 'utilities.php';

// Check if the request has a currency parameter
if (!isset($_GET['currency']) || empty($_GET['currency'])) {
    echo json_encode(formatResponse(null, STATUS_ERROR, 'Missing currency parameter'));
    exit;
}

$currencyCode = strtoupper($_GET['currency']);

// Validate currency code (basic validation)
if (!preg_match('/^[A-Z]{3}$/', $currencyCode)) {
    echo json_encode(formatResponse(null, STATUS_ERROR, 'Invalid currency code format'));
    exit;
}

// Request parameters for Open Exchange Rates API
$params = [
    'app_id' => OPENEXCHANGE_API_KEY,
    'base' => 'USD'  // Free plan only supports USD as base
];

// Make the API request
$result = makeApiRequest(OPENEXCHANGE_API_URL . '/latest.json', 'GET', $params);

// Check for errors
if ($result['statusCode'] !== 200 || !isset($result['response']) || !isset($result['response']['rates'])) {
    echo json_encode(formatResponse(null, STATUS_ERROR, 'Failed to get exchange rates'));
    exit;
}

$rates = $result['response']['rates'];

// If the requested currency is not USD, we need to convert rates
// (since the free plan only provides USD as base)
if ($currencyCode !== 'USD') {
    // Check if the currency is in the rates
    if (!isset($rates[$currencyCode])) {
        echo json_encode(formatResponse(null, STATUS_ERROR, 'Currency not found in exchange rates'));
        exit;
    }

    // Rate for the requested currency against USD
    $baseCurrencyRate = $rates[$currencyCode];

    // Convert all rates to use the requested currency as base
    $convertedRates = [];
    foreach ($rates as $currency => $rate) {
        $convertedRates[$currency] = $rate / $baseCurrencyRate;
    }
    $rates = $convertedRates;
}

// Return the exchange rates
echo json_encode(formatResponse($rates, STATUS_OK));
