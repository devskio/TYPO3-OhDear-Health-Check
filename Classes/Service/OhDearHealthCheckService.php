<?php

namespace Devskio\Typo3OhDearHealthCheck\Service;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use OhDear\HealthCheckResults\CheckResult;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class OhDearHealthCheckService
 * @package Devskio\Typo3OhDearHealthCheck\Service
 */
class OhDearHealthCheckService
{
    private $forgottenFilesList = array();
    private $typo3VersionInstalled = '';
    private $typo3VersionInstalledMajor = '';
    private $typo3VersionLatest = '';
    private $ohdearHealthCheckSecrets = [
        'eurospine' => 'fy6c46anNuvU3SYh',
    ];


// Function to check if the "oh-dear-health-check-secret" header value matches
    public function checkSecret() {
        $headerKey = "oh-dear-health-check-secret";
        $expectedValue = "fy6c46anNuvU3SYh";
        // Check if the header exists
        if (isset($_SERVER['HTTP_' . str_replace('-', '_', strtoupper($headerKey))])) {
            // Get the header value
            $headerValue = $_SERVER['HTTP_' . str_replace('-', '_', strtoupper($headerKey))];

            foreach($this->ohdearHealthCheckSecrets as $key=>$expectedValue) {
                // Compare the header value with the expected value
                if ($headerValue === $expectedValue) {
                    return true;
                }
            }
        }

        // disable access if not authorized
        header("HTTP/1.1 401 Unauthorized");
        exit;
    }

    /**
     * Function to calculate the percentage of used disk space from total space and return health check result.
     *
     * @return CheckResult
     */
    public function getUsedDiskSpace(): CheckResult
    {
        $totalSpace = @disk_total_space('/');
        $usedSpace = $totalSpace - @disk_free_space('/');

        // Handle the case when total space is zero
        if ($totalSpace == 0) {
            return $this->createHealthCheckResult(
                'UsedDiskSpace',
                'Used disk space',
                CheckResult::STATUS_SKIPPED,
                0,
                'SKIPPED',
                ['disk_space_used_percentage' => '0%']
            );
        }

        // Calculate the percentage with 2 decimal points
        $percentage = ($usedSpace / $totalSpace) * 100;
        $usedSpaceInPercentage = round($percentage, 2); // Round to 2 decimal places

        // Set the status
        if (intval($usedSpaceInPercentage) > 90) {
            $status = CheckResult::STATUS_FAILED;
        } else if (intval($usedSpaceInPercentage) > 75) {
            $status = CheckResult::STATUS_WARNING;
        } else {
            $status = CheckResult::STATUS_OK;
        }

        return $this->createHealthCheckResult(
            'UsedDiskSpace',
            'Used Disk Space',
            $status,
            'Disk usage: ' . $status . ' (' . $usedSpaceInPercentage . '% used)',
            $usedSpaceInPercentage . '%',
            ['disk_space_used_percentage' => $usedSpaceInPercentage]
        );
    }

    /**
     * Function to get the size of the PHP error log and add the health check result.
     */
    public function checkPHPErrorLogSize(): CheckResult
    {
        $errorLogPath = ini_get('error_log');
        $errorLogFilesizeReadable = 0;

        // Check if the error log file exists
        if (file_exists($errorLogPath)) {
            $errorLogFilesize = filesize($errorLogPath);

            // Format the filesize in a human-readable format
            $errorLogFilesizeReadable = $this->formatBytes($errorLogFilesize);

            // Determine the status based on the filesize
            if ($errorLogFilesize > 524288000) {
                $status = CheckResult::STATUS_FAILED;
            } else if ($errorLogFilesize > 52428800) {   // 50 MB
                $status = CheckResult::STATUS_WARNING;
            } else {
                $status = CheckResult::STATUS_OK;
            }
        } else {
            $status = CheckResult::STATUS_SKIPPED;
        }
        return $this->createHealthCheckResult(
            'PHPErrorLogSize',
            'PHP Error Log Size',
            $status,
            'Error Log Filesize: ' . $status . ' (' . $errorLogFilesizeReadable . ')',
            'Error Log Filesize: ' . $status . ' (' . $errorLogFilesizeReadable . ')',
            ['error_log_filesize' => $errorLogFilesizeReadable]
        );
    }

    /**
     * Function to get number and size of TYPO3 error logs
     */
    public function getTYPO3ErrorLogSize(): CheckResult
    {
        $logFolderPath = '../var/log/';
        $files = scandir($logFolderPath);
        $files = array_diff($files, ['.', '..']);
        $fullLogsSize = 0;

        // Check if the error log file exists
        if (!empty($files)) {
            foreach ($files as $file) {
                $filePath = $logFolderPath . '/' . $file;

                // Check if the item is a file (not a directory)
                if (is_file($filePath)) {
                    // Get the file size in bytes
                    $fileSizeBytes = filesize($filePath);

                    // Convert file size to MB
                    $fullLogsSize += round($fileSizeBytes / (1024 * 1024), 2);
                }
            }

            // 500 MB
            if ($fullLogsSize > 524288000) {
                $status = CheckResult::STATUS_FAILED;
            } else if ($fullLogsSize > 52428800) { // 50 MB
                $status = CheckResult::STATUS_WARNING;
            } else {
                $status = CheckResult::STATUS_OK;
            }

            return $this->createHealthCheckResult(
                'TYPO3ErrorLogSize',
                'TYPO3 Error Log Size',
                $status,
                'ErrorLogFilesize: ' . $status . ' (' . $fullLogsSize . 'MB)',
                'Error Log Filesize: ' . $status . ' (' . $fullLogsSize . 'MB)',
                ['error_log_filesize' => $fullLogsSize]
            );

        } else {
            return $this->createHealthCheckResult(
                'PHPErrorLogSize',
                'PHP Error Log Size',
                CheckResult::STATUS_SKIPPED,
                'Error Log File Not Found',
                'SKIPPED',
                []
            );
        }
    }

    /**
     * Function to get size of MySQL database
     */
    public function getMysqlSize(): CheckResult
    {
        // Get the database connection
        /** @var ConnectionPool $connectionPool */
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        $databaseConfigurations = $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections'];

        // Loop through each database configuration and get the connection
        foreach ($databaseConfigurations as $databaseName => $databaseConfig) {
            try {
                // Get the database connection for the current database
                $databaseConnection = $connectionPool->getConnectionByName($databaseName);
                $queryBuilder = $databaseConnection->createQueryBuilder();

                $query = $queryBuilder
                    ->selectLiteral('table_schema AS "Database"')
                    ->addSelectLiteral('SUM(data_length + index_length) AS "size"')
                    ->from('information_schema.tables')
                    ->where(
                        $queryBuilder->expr()->eq('table_schema', $queryBuilder->createNamedParameter($databaseConfig['dbname']))
                    )
                    ->groupBy('table_schema');

                $result = $query->execute();

                if ($result) {
                    $databaseSize = $result->fetchAssociative();

                    // Check if the database size is available
                    if ($databaseSize) {
                        $sizeInBytes = (int)$databaseSize['size'];
                        $sizeInMB = round($sizeInBytes / (1024 * 1024), 2);

                        // 5000 MB
                        if ($sizeInMB > 5242880000) {
                            $status = CheckResult::STATUS_FAILED;
                        } elseif ($sizeInMB > 4194304000) {
                            $status = CheckResult::STATUS_WARNING;
                        } else {
                            $status = CheckResult::STATUS_OK;
                        }

                        // list 5 biggest tables
                        $query2 = $queryBuilder
                            ->selectLiteral('table_name AS "Table"')
                            ->addSelectLiteral('ROUND((data_length + index_length) / (1024 * 1024), 2) AS `Size (MB)`')
                            ->from('information_schema.tables')
                            ->where(
                                $queryBuilder->expr()->eq('table_schema', $queryBuilder->createNamedParameter($databaseConfig['dbname']))
                            )
                            ->groupBy('table_name') // Add this line to group the results by table_name
                            ->orderBy('Size (MB)', 'DESC')
                            ->setMaxResults(5);

                        $result2 = $query2->execute()->fetchAllAssociative();
                        $biggestTables = [];
                        foreach ($result2 as $row) {
                            $biggestTables[$row['Table']] = $row['Size (MB)'] . ' MB';
                        }

                        return $this->createHealthCheckResult(
                            'MysqlSize' . $databaseName,
                            'Mysql Size (' . $databaseConfig['dbname'] . ')',
                            $status,
                            'Database size: ' . $sizeInMB . ' MB',
                            $sizeInMB . ' MB',
                            ["biggest_tables" => $biggestTables]
                        );
                    }
                }
            } catch (\Exception $e) {
                $status = CheckResult::STATUS_SKIPPED;
            }
        }
        return $this->createHealthCheckResult(
            'MysqlSize' . $databaseName,
            'Mysql Size (' . $databaseConfig['dbname'] . ')',
            $status,
            'Database size: ' . $sizeInMB . ' MB',
            $sizeInMB . ' MB',
            []
        );
    }

    // Function to format bytes to a human-readable format
    private function formatBytes(float $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    /**
     * Scan a specified folder for commonly forgotten files or folders by developers.
     * TODO: Refactor and use allowed filename patterns instead of disallowed
     *
     * @return CheckResult
     */
    public function scanDocumentRootForForgottenFiles(): CheckResult
    {
        // Array of commonly forgotten patterns
        $patterns = array(
            'phpinfo',
            'pma',
            'phpmyadmin',
            'adminer',
            'backup',
            'bkp',
            'bak',
            'log',
            'old',
            'test',
            'tmp',
            'dev',
            'dump',
            'demo',
            'backup',
            'unused',
            'tgz',
            'zip',
            'sql',
            'csv'
        );

        // Initialize count variable
        $count = 0;

        // Get the list of files and folders in the folder
        $items = scandir($_SERVER['DOCUMENT_ROOT']);

        // Iterate through each item
        foreach ($items as $item) {
            // Exclude "." and ".." directories
            if ($item !== '.' && $item !== '..') {
                // Check if the item matches any of the patterns
                foreach ($patterns as $pattern) {
                    if (stripos($item, $pattern) !== false) {
                        // Save the matched item
                        $this->forgottenFilesList[] = $item;
                        $count++;
                        break;
                    }
                }
            }
        }

        // Display count
        if ($count > 0) {
            return $this->createHealthCheckResult(
                'ForgottenFiles',
                'Forgotten Files',
                CheckResult::STATUS_FAILED,
                'Found ' . $count . ' forgotten files or folders',
                $count . ' forgotten files',
                ['forgotten_files_list' => $this->forgottenFilesList]
            );
        } else {
            return $this->createHealthCheckResult(
                'ForgottenFiles',
                'Forgotten Files',
                CheckResult::STATUS_OK,
                'No forgotten files or folders found',
                'No forgotten files found',
                []
            );
        }
    }

    /**
     * Function to get MySQL credentials from TYPO3 LocalConfiguration.php file.
     *
     * @return array|null Returns an array containing MySQL credentials if available, otherwise null.
     */
    public function getMysqlCredentials(): ?array
    {
        // Path to the TYPO3 LocalConfiguration.php file
        $localConfigurationPath = GeneralUtility::getFileAbsFileName('typo3conf/LocalConfiguration.php');

        // Read the TYPO3 LocalConfiguration.php file
        $localConfiguration = include($localConfigurationPath);

        // Get the MySQL credentials from the configuration
        $credentials = $localConfiguration['DB']['Connections']['Default'] ?? null;

        return $credentials;
    }

    /**
     * Get TYPO3 database error log records for the last one month.
     *
     * @return CheckResult
     */
    public function getTYPO3DBLog(): CheckResult
    {
        // Get the MySQL credentials from the configuration
        $credentials = $this->getMysqlCredentials();

        if ($credentials !== null) {
            try {
                // Get the database connection for the current database
                $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
                $databaseConnection = $connectionPool->getConnectionByName('Default');
                $queryBuilder = $databaseConnection->createQueryBuilder();

                $oneMonthAgo = strtotime('-1 month');
                $query = $queryBuilder
                    ->selectLiteral('COUNT(*) AS num_records')
                    ->from('sys_log')
                    ->where(
                        $queryBuilder->expr()->eq('error', $queryBuilder->createNamedParameter(2))
                    )
                    ->andWhere(
                        $queryBuilder->expr()->gte('tstamp', $queryBuilder->createNamedParameter($oneMonthAgo))
                    );

                $result = $query->execute();
                $numRecords = $result->fetchOne();

                // Check if there are more than 500 errors in the last month
                $status = ($numRecords > 500) ? CheckResult::STATUS_FAILED : CheckResult::STATUS_OK;

                return $this->createHealthCheckResult(
                    'TYPO3DBLog',
                    'TYPO3 Database Error Log',
                    $status,
                    'Found ' . $numRecords . ' error log records in the last month',
                    $status,
                    ['num_records' => $numRecords]
                );
            } catch (\Exception $e) {
                return $this->createHealthCheckResult(
                    'TYPO3DBLog',
                    'TYPO3 Database Error Log',
                    CheckResult::STATUS_CRASHED,
                    'Error executing the database query: ' . $e->getMessage(),
                    'CRASHED',
                    []
                );
            }
        } else {
            return $this->createHealthCheckResult(
                'TYPO3DBLog',
                'TYPO3 Database Error Log',
                CheckResult::STATUS_SKIPPED,
                'MySQL credentials not available',
                'SKIPPED',
                []
            );
        }
    }

    /**
     * Check if installed TYPO3 version is the latest.
     *
     * @return CheckResult
     */
    public function getTYPO3Version(): CheckResult
    {
        // Get the TYPO3 composer.lock file path
        $composerFilePath = '../../composer.lock';

        if (!file_exists($composerFilePath)) {
            // try second path
            $composerFilePath = '../composer.lock';

            if (!file_exists($composerFilePath)) {
                return $this->createHealthCheckResult(
                    'TYPO3Version',
                    'TYPO3 Version',
                    CheckResult::STATUS_CRASHED,
                    'Cannot find composer.lock file',
                    'CRASHED',
                    []
                );
            }
        }

        $composerJson = file_get_contents($composerFilePath);
        $composerData = json_decode($composerJson, true);

        if (is_array($composerData) && count($composerData) > 0) {

            // Get the TYPO3 version from composer.lock
            $this->typo3VersionInstalled = $this->getTYPO3VersionFromComposerLock($composerData, "name", "typo3/cms-core");
            $this->typo3VersionInstalledMajor = $this->extractFirstNumber($this->typo3VersionInstalled);

            // Fetch all TYPO3 version data
            $versionUrl = 'https://get.typo3.org/json';
            $versionJson = file_get_contents($versionUrl);
            $versionData = json_decode($versionJson, true);

            if (is_array($versionData) && count($versionData) > 0) {

                // Get the latest TYPO3 version in the current branch
                if (function_exists('array_key_first')) {
                    $this->typo3VersionLatest = array_key_first($versionData[$this->typo3VersionInstalledMajor]['releases']);
                } else {
                    reset($versionData[$this->typo3VersionInstalledMajor]['releases']);
                    $this->typo3VersionLatest =  key($versionData[$this->typo3VersionInstalledMajor]['releases']);
                }

                // Compare the versions
                if ($this->typo3VersionLatest === $this->typo3VersionInstalled) {
                    return $this->createHealthCheckResult(
                        'TYPO3Version',
                        'TYPO3 Version',
                        CheckResult::STATUS_OK,
                        'Installed TYPO3 version ' . $this->typo3VersionInstalled . ' is up to date',
                        CheckResult::STATUS_OK,
                        ['installed_version' => $this->typo3VersionInstalled]
                    );
                } else {
                    return $this->createHealthCheckResult(
                        'TYPO3Version',
                        'TYPO3 Version',
                        CheckResult::STATUS_WARNING,
                        'Update available: Installed TYPO3 version is ' . $this->typo3VersionInstalled . ', Latest version is ' . $this->typo3VersionLatest,
                        CheckResult::STATUS_WARNING,
                        ['installed_version' => $this->typo3VersionInstalled, 'latest_version' => $this->typo3VersionLatest]
                    );
                }
            } else {
                return $this->createHealthCheckResult(
                    'TYPO3Version',
                    'TYPO3 Version',
                    CheckResult::STATUS_CRASHED,
                    'Error fetching TYPO3 version data from server',
                    'CRASHED',
                    []
                );
            }
        } else {
            return $this->createHealthCheckResult(
                'TYPO3Version',
                'TYPO3 Version',
                CheckResult::STATUS_CRASHED,
                'Error parsing composer.lock file',
                'CRASHED',
                []
            );
        }
    }

    /**
     * Extract substring from string before the first ".".
     *
     * @param string $str
     * @return int|null
     */
    private function extractFirstNumber($str): ?int
    {
        $dotPosition = strpos($str, ".");
        if ($dotPosition !== false) {
            $number = substr($str, 0, $dotPosition);
            return (int)$number;
        }
        return null;
    }

    /**
     * Get TYPO3 version from composer.lock.
     *
     * @param array $array
     * @param string $key
     * @param string $value
     * @return string|null
     */
    private function getTYPO3VersionFromComposerLock($array, $key, $value): ?string
    {
        foreach ($array as $item) {
            if (isset($item[$key]) && $item[$key] === $value) {
                return str_replace("v", "", $item["version"]);
            }

            if (is_array($item)) {
                $result = $this->getTYPO3VersionFromComposerLock($item, $key, $value);
                if ($result !== null) {
                    return $result;
                }
            }
        }
        return null;
    }



    /**
     * Helper function to create the health check result in the required format.
     *
     * @param string $name
     * @param string $label
     * @param string $status
     * @param string $notificationMessage
     * @param mixed $shortSummary
     * @param array $meta
     * @return CheckResult
     */
    private function createHealthCheckResult(
        string $name,
        string $label,
        string $status,
        string $notificationMessage,
        mixed $shortSummary,
        array $meta
    ): CheckResult
    {
        return new CheckResult(
            name: $name,
            label: $label,
            notificationMessage: $notificationMessage,
            shortSummary: $shortSummary,
            status: $status,
            meta: $meta
        );
    }
}
