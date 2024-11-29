<?php
// Display start time
echo "[" . date('Y-m-d H:i:s') . "] Starting the download process...\n";

// Set the URL of the XML file
$url = 'https://www.aeaweb.org/econlit/classifications.xml';

// Get the system temporary directory
$tempDir = sys_get_temp_dir();

// Define file paths for the downloaded XML and the SQL dump
$xmlFilePath = $tempDir . '/classifications.xml';
$sqlFilePath = sprintf("%s/jel_dump-%s.sql", $tempDir, date('Y-m-d-H-i-s'));

// Download the XML file and save it to the system temporary directory
file_put_contents($xmlFilePath, file_get_contents($url));

echo "[" . date('Y-m-d H:i:s') . "] File downloaded to: " . $xmlFilePath . "\n";

// Load the XML content
$xml = simplexml_load_file($xmlFilePath);

if (!$xml) {
    die("[" . date('Y-m-d H:i:s') . "] Error loading the XML file.\n");
}

echo "[" . date('Y-m-d H:i:s') . "] XML file successfully loaded.\n";
$tableName = 'classification_jel';

// Prepare the SQL dump content
$sqlDump = <<<SQL
-- SQL Dump for JEL Classifications

CREATE TABLE IF NOT EXISTS `$tableName` (
  `code` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
    PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SQL;

$count = 0;
$classificationCount = count($xml->classification);

// Process each classification in the XML and convert it to an SQL INSERT statement
foreach ($xml->classification as $classification) {
    $code = (string) $classification->code;
    $label = (string) $classification->description;

    // Sanitize the code and label
    $code = htmlspecialchars($code, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $label = htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    // Add the INSERT statement to the SQL dump
    $sqlDump .= "INSERT INTO `$tableName` (`code`, `label`) VALUES ('$code', '$label');\n";
    $count++;
}

// Write the SQL dump to the file
file_put_contents($sqlFilePath, $sqlDump);

echo "[" . date('Y-m-d H:i:s') . "] SQL dump file written to: " . $sqlFilePath . "\n";

// Provide summary information to the user
echo "[" . date('Y-m-d H:i:s') . "] Total classifications found: " . $classificationCount . "\n";
echo "[" . date('Y-m-d H:i:s') . "] Total classifications processed: " . $count . "\n";

echo "[" . date('Y-m-d H:i:s') . "] Process completed successfully.\n";
