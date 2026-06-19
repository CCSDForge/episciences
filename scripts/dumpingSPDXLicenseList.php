<?php
/**
 * Dump the SPDX base (official JSON: https://spdx.org/licenses/licenses.json)
 *
 */

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

require __DIR__ . '/../vendor/autoload.php';

echo "[" . date('Y-m-d H:i:s') . "] Starting the process...\n";

$url = 'https://spdx.org/licenses/licenses.json';

// Get the system temporary directory
$tempDir = sys_get_temp_dir();
$sqlFilePath = sprintf("%s/license_list_dump-%s.sql", $tempDir, date('Y-m-d-H-i-s'));

$logger = getLogger($tempDir);

try {
    $licenses = json_decode(getLicenses($url), true, 512, JSON_THROW_ON_ERROR)['licenses'] ?? [];
} catch (GuzzleException|JsonException $e) {
    $logger?->critical($e->getMessage());
    die(sprintf("[%s] %s \n", date('Y-m-d H:i:s'), $e->getMessage()));
}

$count = count($licenses);

if ($count === 0) {
    die(sprintf("[%s] %s \n", date('Y-m-d H:i:s'), 'Empty result'));
}

$result = processLicenses($licenses);
$processed = $result['processed'] ?? 0;
$added = $result['added']['count'] ?? 0;

//// Write the SQL dump to the file
file_put_contents($sqlFilePath, $result['sqlDump']);

// Provide summary information to the user
echo "[" . date('Y-m-d H:i:s') . "] loading the SPDX base (official JSON: https://spdx.org/licenses/licenses.json)\n";
echo "[" . date('Y-m-d H:i:s') . "] Total licenses found (SPDX base): " . $count . "\n";
echo "[" . date('Y-m-d H:i:s') . "] Total licenses processed: " . $processed . "\n";
echo "[" . date('Y-m-d H:i:s') . "] Process completed successfully.\n";
echo "[" . date('Y-m-d H:i:s') . "] SQL dump file written to: " . $sqlFilePath . "\n";

/**
 * @param string $url
 * @return string|null
 * @throws GuzzleException
 */
function getLicenses(string $url): ?string
{
    $client = new Client();
    $response = $client->get($url);
    return $response->getBody()->getContents();
}

function processLicenses(array $response): array
{
    $date = date('Y-m-d H:i:s');
    $count = count($response);
    $tableName = 'license_spdx';

// Prepare the SQL dump content
    $sqlDump = <<<SQL
--
-- Table structure
--

CREATE TABLE IF NOT EXISTS `$tableName` (
  `code` varchar(64) COLLATE utf8mb4_general_ci NOT NULL PRIMARY KEY,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `recommended` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
--  [$date] Dumping data from the SPDX base (official JSON: https://spdx.org/licenses/licenses.json) 
--
--  Total SPDX licenses found: $count

-- Data export 

SQL;

    $count = 0;
    foreach ($response as $licenseInfo) {
        $code = htmlspecialchars($licenseInfo['licenseId'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $name = htmlspecialchars($licenseInfo['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $isRecommended = (int)isRecommended($code);
        $sqlDump .= "\n";
        $sqlDump .= "INSERT INTO `$tableName` (`code`, `name`, `recommended`) VALUES ('$code', '$name', $isRecommended) ON DUPLICATE KEY UPDATE `name` = '$name', `recommended` = $isRecommended;";
        ++$count;
    }

    return ['sqlDump' => $sqlDump, 'processed' => $count];

}

function getLogger(string $dir): ?Logger
{
    $logger = new Logger('licenses');
    try {
        $logger->pushHandler(new StreamHandler(rtrim($dir, '/') . '/licenses.log', Logger::DEBUG));
    } catch (Exception $e) {
        trigger_error($e->getMessage());
        return null;
    }
    return $logger;
}

function isRecommended($code): bool|int
{
    $regex = '#^CC-BY(?:-NC)?(?:-(?:ND|SA))?-4\.0$#i';
    return preg_match($regex, $code);
}


