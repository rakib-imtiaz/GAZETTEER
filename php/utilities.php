<?php

/**
 * Utilities for the Gazetteer application
 * Contains helper functions for API requests and responses
 */

require_once 'config.php';

/**
 * Make an API request using cURL
 * 
 * @param string $url The URL to make the request to
 * @param string $method The HTTP method (GET, POST)
 * @param array $params The parameters to send with the request
 * @return array The response data and status code
 */
function makeApiRequest($url, $method = 'GET', $params = [])
{
    $ch = curl_init();

    // Set cURL options
    if ($method === 'GET' && !empty($params)) {
        $url .= '?' . http_build_query($params);
    }

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    }

    // Execute cURL request
    $response = curl_exec($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    // Check for errors
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        return [
            'response' => null,
            'statusCode' => STATUS_SERVER_ERROR,
            'error' => $error
        ];
    }

    curl_close($ch);

    // Parse JSON response
    $responseData = json_decode($response, true);

    return [
        'response' => $responseData,
        'statusCode' => $statusCode
    ];
}

/**
 * Format a standardized API response
 * 
 * @param mixed $data The data to return
 * @param int $statusCode The HTTP status code
 * @param string $message A message to include with the response
 * @return array The formatted response
 */
function formatResponse($data = null, $statusCode = STATUS_OK, $message = '')
{
    $response = [
        'status' => [
            'code' => $statusCode,
            'message' => $message ?: ($statusCode === STATUS_OK ? 'Success' : 'Error')
        ]
    ];

    if ($data !== null) {
        $response['data'] = $data;
    }

    return $response;
}

/**
 * Load country borders from the GeoJSON file
 * Uses caching to improve performance
 * 
 * @return array|null The country borders data or null on error
 */
function loadCountryBorders()
{
    static $countryBorders = null;

    // Return cached data if available
    if ($countryBorders !== null) {
        return $countryBorders;
    }

    $filePath = __DIR__ . '/' . COUNTRY_BORDERS_FILE;

    // Debug info
    error_log("Trying to load country borders from: " . $filePath);

    if (!file_exists($filePath)) {
        error_log("File not found: " . $filePath);

        // Try alternative path
        $altPath = __DIR__ . '/../data/countryBorders.geo.json';
        error_log("Trying alternative path: " . $altPath);

        if (file_exists($altPath)) {
            $filePath = $altPath;
            error_log("Found file at alternative path: " . $altPath);
        } else {
            error_log("File not found at alternative path either");
            return null;
        }
    }

    // Check file size and adjust memory limit if needed
    $fileSize = filesize($filePath);
    error_log("GeoJSON file size: " . ($fileSize / 1024 / 1024) . " MB");

    // For large files, increase memory limit
    if ($fileSize > 50 * 1024 * 1024) {
        ini_set('memory_limit', '512M');
        error_log("Increased memory limit to 512M for large GeoJSON file");
    }

    $fileContents = file_get_contents($filePath);
    if ($fileContents === false) {
        error_log("Failed to read file contents from: " . $filePath);
        return null;
    }

    $countryBorders = json_decode($fileContents, true);
    if ($countryBorders === null) {
        error_log("Failed to parse JSON from file: " . $filePath . " - JSON error: " . json_last_error_msg());
        return null;
    }

    error_log("Successfully loaded country borders: " . count($countryBorders['features']) . " features");
    return $countryBorders;
}

/**
 * Find a country in the GeoJSON file by ISO code
 * 
 * @param string $countryCode The ISO country code
 * @return array|null The country data or null if not found
 */
function findCountryByCode($countryCode)
{
    $countryBorders = loadCountryBorders();

    if ($countryBorders === null) {
        return null;
    }

    // Ensure country code is uppercase
    $countryCode = strtoupper($countryCode);

    // Track the best match found with priority scoring
    $bestMatch = null;
    $bestScore = -1;

    // From test results, we know the actual fields used in this GeoJSON file
    $iso2Field = 'ISO3166-1-Alpha-2';
    $iso3Field = 'ISO3166-1-Alpha-3';

    // Log the search attempt
    error_log("Searching for country with code: " . $countryCode);

    // Search for the country in the GeoJSON features
    foreach ($countryBorders['features'] as $feature) {
        if (!isset($feature['properties'])) {
            continue;
        }

        // Score starts at 0 for each feature
        $matchScore = 0;

        // Check ISO2 field (exact match) - highest priority
        if (
            isset($feature['properties'][$iso2Field]) &&
            strtoupper($feature['properties'][$iso2Field]) === $countryCode
        ) {
            $matchScore = 100; // Highest priority for exact ISO2 match
            error_log("Exact ISO2 match found for " . $countryCode . " with " . $feature['properties']['name']);
            return $feature; // Immediately return on exact ISO2 match
        }

        // Check ISO3 field (exact match) - high priority
        if (
            isset($feature['properties'][$iso3Field]) &&
            strtoupper($feature['properties'][$iso3Field]) === $countryCode
        ) {
            $matchScore = 90; // High priority for exact ISO3 match
            error_log("Exact ISO3 match found for " . $countryCode . " with " . $feature['properties']['name']);
        }

        // For 2-letter codes, check if the first 2 letters of ISO3 match (only if length is 2)
        if (
            strlen($countryCode) === 2 &&
            isset($feature['properties'][$iso3Field]) &&
            strtoupper(substr($feature['properties'][$iso3Field], 0, 2)) === $countryCode
        ) {
            $matchScore = 80; // Medium-high priority for ISO3 prefix match
        }

        // Check standard field variants as fallback
        $standardFields = ['iso_a2', 'ISO_A2', 'iso_a3', 'ISO_A3', 'ISO2', 'ISO3'];
        foreach ($standardFields as $field) {
            if (
                isset($feature['properties'][$field]) &&
                strtoupper($feature['properties'][$field]) === $countryCode
            ) {
                $matchScore = 70; // Lower priority for other standard fields
            }
        }

        // Check if exact country name (very reliable)
        if (isset($feature['properties']['name'])) {
            if (strtoupper($feature['properties']['name']) === strtoupper($countryCode)) {
                $matchScore = 60;
            }
        }

        // Keep track of the best match
        if ($matchScore > $bestScore) {
            $bestScore = $matchScore;
            $bestMatch = $feature;

            // If we found an extremely high confidence match, return it immediately
            if ($matchScore >= 90) {
                return $bestMatch;
            }
        }
    }

    // If we found a relatively good match, return it
    if ($bestScore >= 60) {
        error_log("Using best match for " . $countryCode . ": " .
            (isset($bestMatch['properties']['name']) ? $bestMatch['properties']['name'] : 'Unknown') .
            " with score " . $bestScore);
        return $bestMatch;
    }

    // For 2-letter codes, try to convert to 3-letter code if no match yet
    if (strlen($countryCode) === 2 && $bestScore < 60) {
        // Map of some common 2-letter to 3-letter codes for countries
        $iso2to3 = [
            'AD' => 'AND',
            'AE' => 'ARE',
            'AF' => 'AFG',
            'AG' => 'ATG',
            'AL' => 'ALB',
            'AM' => 'ARM',
            'AO' => 'AGO',
            'AR' => 'ARG',
            'AT' => 'AUT',
            'AU' => 'AUS',
            'AZ' => 'AZE',
            'BA' => 'BIH',
            'BB' => 'BRB',
            'BD' => 'BGD',
            'BE' => 'BEL',
            'BF' => 'BFA',
            'BG' => 'BGR',
            'BH' => 'BHR',
            'BI' => 'BDI',
            'BJ' => 'BEN',
            'BN' => 'BRN',
            'BO' => 'BOL',
            'BR' => 'BRA',
            'BS' => 'BHS',
            'BT' => 'BTN',
            'BW' => 'BWA',
            'BY' => 'BLR',
            'BZ' => 'BLZ',
            'CA' => 'CAN',
            'CD' => 'COD',
            'CF' => 'CAF',
            'CG' => 'COG',
            'CH' => 'CHE',
            'CI' => 'CIV',
            'CL' => 'CHL',
            'CM' => 'CMR',
            'CN' => 'CHN',
            'CO' => 'COL',
            'CR' => 'CRI',
            'CU' => 'CUB',
            'CV' => 'CPV',
            'CY' => 'CYP',
            'CZ' => 'CZE',
            'DE' => 'DEU',
            'DJ' => 'DJI',
            'DK' => 'DNK',
            'DM' => 'DMA',
            'DO' => 'DOM',
            'DZ' => 'DZA',
            'EC' => 'ECU',
            'EE' => 'EST',
            'EG' => 'EGY',
            'ER' => 'ERI',
            'ES' => 'ESP',
            'ET' => 'ETH',
            'FI' => 'FIN',
            'FJ' => 'FJI',
            'FM' => 'FSM',
            'FR' => 'FRA',
            'GA' => 'GAB',
            'GB' => 'GBR',
            'GD' => 'GRD',
            'GE' => 'GEO',
            'GH' => 'GHA',
            'GM' => 'GMB',
            'GN' => 'GIN',
            'GQ' => 'GNQ',
            'GR' => 'GRC',
            'GT' => 'GTM',
            'GW' => 'GNB',
            'GY' => 'GUY',
            'HN' => 'HND',
            'HR' => 'HRV',
            'HT' => 'HTI',
            'HU' => 'HUN',
            'ID' => 'IDN',
            'IE' => 'IRL',
            'IL' => 'ISR',
            'IN' => 'IND',
            'IQ' => 'IRQ',
            'IR' => 'IRN',
            'IS' => 'ISL',
            'IT' => 'ITA',
            'JM' => 'JAM',
            'JO' => 'JOR',
            'JP' => 'JPN',
            'KE' => 'KEN',
            'KG' => 'KGZ',
            'KH' => 'KHM',
            'KI' => 'KIR',
            'KM' => 'COM',
            'KN' => 'KNA',
            'KP' => 'PRK',
            'KR' => 'KOR',
            'KW' => 'KWT',
            'KZ' => 'KAZ',
            'LA' => 'LAO',
            'LB' => 'LBN',
            'LC' => 'LCA',
            'LI' => 'LIE',
            'LK' => 'LKA',
            'LR' => 'LBR',
            'LS' => 'LSO',
            'LT' => 'LTU',
            'LU' => 'LUX',
            'LV' => 'LVA',
            'LY' => 'LBY',
            'MA' => 'MAR',
            'MC' => 'MCO',
            'MD' => 'MDA',
            'ME' => 'MNE',
            'MG' => 'MDG',
            'MH' => 'MHL',
            'MK' => 'MKD',
            'ML' => 'MLI',
            'MM' => 'MMR',
            'MN' => 'MNG',
            'MR' => 'MRT',
            'MT' => 'MLT',
            'MU' => 'MUS',
            'MV' => 'MDV',
            'MW' => 'MWI',
            'MX' => 'MEX',
            'MY' => 'MYS',
            'MZ' => 'MOZ',
            'NA' => 'NAM',
            'NE' => 'NER',
            'NG' => 'NGA',
            'NI' => 'NIC',
            'NL' => 'NLD',
            'NO' => 'NOR',
            'NP' => 'NPL',
            'NR' => 'NRU',
            'NZ' => 'NZL',
            'OM' => 'OMN',
            'PA' => 'PAN',
            'PE' => 'PER',
            'PG' => 'PNG',
            'PH' => 'PHL',
            'PK' => 'PAK',
            'PL' => 'POL',
            'PT' => 'PRT',
            'PW' => 'PLW',
            'PY' => 'PRY',
            'QA' => 'QAT',
            'RO' => 'ROU',
            'RS' => 'SRB',
            'RU' => 'RUS',
            'RW' => 'RWA',
            'SA' => 'SAU',
            'SB' => 'SLB',
            'SC' => 'SYC',
            'SD' => 'SDN',
            'SE' => 'SWE',
            'SG' => 'SGP',
            'SI' => 'SVN',
            'SK' => 'SVK',
            'SL' => 'SLE',
            'SM' => 'SMR',
            'SN' => 'SEN',
            'SO' => 'SOM',
            'SR' => 'SUR',
            'SS' => 'SSD',
            'ST' => 'STP',
            'SV' => 'SLV',
            'SY' => 'SYR',
            'SZ' => 'SWZ',
            'TD' => 'TCD',
            'TG' => 'TGO',
            'TH' => 'THA',
            'TJ' => 'TJK',
            'TL' => 'TLS',
            'TM' => 'TKM',
            'TN' => 'TUN',
            'TO' => 'TON',
            'TR' => 'TUR',
            'TT' => 'TTO',
            'TV' => 'TUV',
            'TZ' => 'TZA',
            'UA' => 'UKR',
            'UG' => 'UGA',
            'US' => 'USA',
            'UY' => 'URY',
            'UZ' => 'UZB',
            'VA' => 'VAT',
            'VC' => 'VCT',
            'VE' => 'VEN',
            'VN' => 'VNM',
            'VU' => 'VUT',
            'WS' => 'WSM',
            'XK' => 'XKX',
            'YE' => 'YEM',
            'ZA' => 'ZAF',
            'ZM' => 'ZMB',
            'ZW' => 'ZWE'
        ];

        if (isset($iso2to3[$countryCode])) {
            $iso3Code = $iso2to3[$countryCode];
            error_log("Converting $countryCode to ISO3: $iso3Code");

            // Search using the 3-letter code
            foreach ($countryBorders['features'] as $feature) {
                if (!isset($feature['properties'])) {
                    continue;
                }

                if (
                    isset($feature['properties'][$iso3Field]) &&
                    strtoupper($feature['properties'][$iso3Field]) === $iso3Code
                ) {
                    error_log("Found match using ISO3 code conversion: " . $feature['properties']['name']);
                    return $feature;
                }
            }
        }
    }

    error_log("No match found for country code: " . $countryCode);
    return null;
}
