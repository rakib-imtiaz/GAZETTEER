<?php

/**
 * GeoJSON Validator and Fixer
 * This script analyzes the countryBorders.geo.json file,
 * checks for common formatting issues, and attempts to fix them.
 */

// Set unlimited execution time for large files
set_time_limit(0);
ini_set('memory_limit', '1G');

// File paths
$sourceFile = __DIR__ . '/data/countryBorders.geo.json';
$backupFile = __DIR__ . '/data/countryBorders.geo.json.bak';
$fixedFile = __DIR__ . '/data/countryBorders.geo.json.fixed';

// Check if source file exists
if (!file_exists($sourceFile)) {
    die("Error: Source file not found at $sourceFile\n");
}

echo "Starting GeoJSON validation and repair process...\n";
echo "File size: " . round(filesize($sourceFile) / (1024 * 1024), 2) . " MB\n";

// Create backup
if (!file_exists($backupFile)) {
    echo "Creating backup... ";
    if (copy($sourceFile, $backupFile)) {
        echo "Done.\n";
    } else {
        die("Failed to create backup file!\n");
    }
} else {
    echo "Backup file already exists.\n";
}

// Read the file
echo "Reading file contents...\n";
$contents = file_get_contents($sourceFile);
if ($contents === false) {
    die("Error: Failed to read the source file.\n");
}

// Try to detect the issue
echo "Analyzing file structure...\n";
$firstChar = substr(trim($contents), 0, 1);
$valid = false;
$fixApplied = false;

if ($firstChar === '{') {
    echo "File appears to start with a valid JSON object.\n";

    // Try to decode the JSON
    $data = json_decode($contents, true);
    if ($data !== null) {
        echo "File is valid JSON and can be parsed.\n";

        // Check if it's a proper GeoJSON
        if (isset($data['type']) && $data['type'] === 'FeatureCollection' && isset($data['features'])) {
            echo "File is a valid GeoJSON FeatureCollection.\n";
            $valid = true;
        } else {
            echo "File is valid JSON but not a proper GeoJSON FeatureCollection.\n";
            $fixApplied = true;

            // Wrap the existing JSON in a FeatureCollection if it looks like a Feature
            if (isset($data['type']) && $data['type'] === 'Feature') {
                echo "Converting single Feature to FeatureCollection...\n";
                $newData = [
                    'type' => 'FeatureCollection',
                    'features' => [$data]
                ];
                $contents = json_encode($newData);
            }
        }
    } else {
        echo "File starts with '{' but is not valid JSON: " . json_last_error_msg() . "\n";
    }
} else if ($firstChar === '[') {
    echo "File starts with an array '['.\n";

    // Try to decode as JSON array
    $data = json_decode($contents, true);
    if ($data !== null) {
        echo "File is a valid JSON array.\n";

        // Convert array to GeoJSON if it looks like an array of features
        if (isset($data[0]['type']) && $data[0]['type'] === 'Feature') {
            echo "Converting array of Features to FeatureCollection...\n";
            $newData = [
                'type' => 'FeatureCollection',
                'features' => $data
            ];
            $contents = json_encode($newData);
            $fixApplied = true;
        } else {
            echo "Array does not contain Features. Manual inspection required.\n";
        }
    } else {
        echo "File starts with '[' but is not a valid JSON array: " . json_last_error_msg() . "\n";
    }
} else {
    echo "File does not start with a valid JSON structure ('{' or '[').\n";
    echo "First character is: " . $firstChar . "\n";

    // Check if it might be missing the wrapper
    if (preg_match('/\[\s*\[\s*[\d\.-]+\s*,\s*[\d\.-]+/', $contents)) {
        echo "File appears to contain coordinate arrays but is missing proper GeoJSON structure.\n";

        // Try to wrap the content in a proper structure
        echo "Attempting to fix by wrapping content in proper GeoJSON structure...\n";

        // Look for sequences that might indicate feature boundaries
        if (preg_match_all('/"name"\s*:\s*"([^"]+)"/', $contents, $matches)) {
            echo "Found " . count($matches[0]) . " potential features by name property.\n";

            // This is a complex repair that would require more parsing
            echo "Complex repair needed. Writing current content to fixed file for manual editing.\n";
            $contents = '{"type":"FeatureCollection","features":[' . $contents . ']}';
            $fixApplied = true;
        }
    }
}

// Write the fixed content
if ($fixApplied) {
    echo "Applying fixes and writing to $fixedFile...\n";
    if (file_put_contents($fixedFile, $contents)) {
        echo "Fixed file has been written successfully.\n";
    } else {
        echo "Error: Failed to write fixed file.\n";
    }
} else if ($valid) {
    echo "No fixes needed. File is already valid.\n";
} else {
    echo "Could not automatically fix the file. Manual inspection required.\n";

    // Try a different approach - read first and last lines to analyze structure
    echo "Analyzing file structure by reading first and last lines...\n";

    $handle = fopen($sourceFile, 'r');
    if ($handle) {
        $firstLine = fgets($handle);

        // Go to the end minus a few KB to read the last lines
        fseek($handle, -4096, SEEK_END);
        $lastChunk = '';
        while (!feof($handle)) {
            $lastChunk .= fread($handle, 4096);
        }
        fclose($handle);

        $lastLines = array_slice(explode("\n", $lastChunk), -10);
        $lastLine = end($lastLines);

        echo "First line: " . substr(trim($firstLine), 0, 100) . "...\n";
        echo "Last line: " . substr(trim($lastLine), 0, 100) . "...\n";

        // Output more information for manual inspection
        echo "\nThis information should help with manual inspection and repair.\n";
    }
}

echo "\nProcess completed.\n";
echo "For further analysis, you can try a JSON validator tool or consider manually restructuring the file.\n";
