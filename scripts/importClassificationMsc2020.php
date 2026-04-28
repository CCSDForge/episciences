<?php
/**
 * Utility script to download MSC 2020 as a TSV file and convert it into an SQL dump file,
 * ready to be imported into a DB
 */

// Utility function to get the current timestamp
function logMessage($message): void
{
    echo "[" . date("Y-m-d H:i:s") . "] " . $message . "\n";
}

// Database and file information
$databaseName = 'episciences';
$tableName = 'classification_msc2020';
$dumpFileName = sprintf("msc2020-%s.sql", date('Y-m-d-H-i-s'));
$fileUrl = 'https://msc2020.org/MSC_2020.csv';

// Get the system's temporary directory
$tmpDir = sys_get_temp_dir();
$downloadedFile = $tmpDir . DIRECTORY_SEPARATOR . 'msc2020_downloaded.csv';
$utf8File = $tmpDir . DIRECTORY_SEPARATOR . 'msc2020_utf8.csv';
$dumpFile = $tmpDir . DIRECTORY_SEPARATOR . $dumpFileName;

try {
    // Step 1: Download the file
    logMessage("Downloading the file...");
    $fileContent = file_get_contents($fileUrl);
    if ($fileContent === false) {
        throw new Exception("Failed to download the file from $fileUrl.");
    }
    file_put_contents($downloadedFile, $fileContent);
    logMessage("File downloaded successfully to $downloadedFile.");

    // Step 2: Convert encoding from ISO-8859-15 to UTF-8
    logMessage("Converting file encoding from ISO-8859-15 to UTF-8...");
    $input = fopen($downloadedFile, 'r');
    $output = fopen($utf8File, 'w');

    if ($input === false || $output === false) {
        throw new Exception("Failed to open files for encoding conversion.");
    }

    // Count total lines in the downloaded file (excluding the header)
    $lineCount = 0;
    while (fgets($input) !== false) {
        $lineCount++;
    }
    rewind($input); // Go back to the beginning of the file after counting lines
    logMessage("The downloaded file contains $lineCount lines (including the header).");

    while (($line = fgets($input)) !== false) {
        // Convert each line from ISO-8859-15 to UTF-8
        $utf8Line = mb_convert_encoding($line, 'UTF-8', 'ISO-8859-15');
        fwrite($output, $utf8Line);
    }

    fclose($input);
    fclose($output);
    logMessage("File encoding converted to UTF-8 and saved as $utf8File.");

    // Step 3: Create MySQL dump file
    logMessage("Creating MySQL dump file...");

    $dumpFileContent = "-- MySQL dump file for $tableName table\n";
    $dumpFileContent .= "-- Created on " . date("Y-m-d H:i:s") . "\n\n";

    // Create database if it doesn't exist
    $dumpFileContent .= "CREATE DATABASE IF NOT EXISTS `$databaseName` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;\n";
    $dumpFileContent .= "USE `$databaseName`;\n\n";

    // Create table structure if it doesn't exist
    $dumpFileContent .= "CREATE TABLE IF NOT EXISTS `$tableName` (\n";
    $dumpFileContent .= "  `code` varchar(10) COLLATE utf8mb4_general_ci NOT NULL,\n";
    $dumpFileContent .= "  `label` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,\n";
    $dumpFileContent .= "  `description` mediumtext COLLATE utf8mb4_general_ci NOT NULL,\n";
    $dumpFileContent .= "  PRIMARY KEY (`code`)\n";
    $dumpFileContent .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;\n\n";

    // Insert data from the UTF-8 TSV file
    $handle = fopen($utf8File, 'r');
    if ($handle === false) {
        throw new Exception("Failed to open the UTF-8 file for reading.");
    }

    // Skip the header row
    fgetcsv($handle, 1000, "\t", '"');
    $lineCount--; // Exclude the header line from the line count

    $dumpFileContent .= "INSERT INTO `$tableName` (`code`, `label`, `description`) VALUES\n";

    $rowCount = 0;
    $first = true;
    while (($data = fgetcsv($handle, 1000, "\t", '"')) !== false) {
        // Safely handle undefined array keys using isset()
        $code = isset($data[0]) ? addslashes($data[0]) : '';
        $label = isset($data[1]) ? addslashes($data[1]) : '';
        $description = isset($data[2]) ? addslashes($data[2]) : '';

        // Check if all fields are present
        if ($code === '') {
            continue; // Skip the row if the `code` is empty
        }

        if (!$first) {
            $dumpFileContent .= ",\n";
        }
        $dumpFileContent .= "('$code', '$label', '$description')";
        $first = false;
        $rowCount++;
    }

    $dumpFileContent .= ";\n";

    fclose($handle);

    // Write the dump file
    file_put_contents($dumpFile, $dumpFileContent);
    logMessage("MySQL dump file created with $rowCount records, saved as $dumpFile.");

    // Output final message
    logMessage("Process completed. The MySQL dump file is located at: $dumpFile");
    logMessage("The downloaded file contained $lineCount lines (excluding the header), and $rowCount records were prepared for insertion.");

} catch (Exception $e) {
    logMessage("Error: " . $e->getMessage());
}

