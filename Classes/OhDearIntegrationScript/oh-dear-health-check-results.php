<?php
/* 
Documentation: https://ohdear.app/docs/features/application-health-monitoring#health-check-results-format
These are the possible values for status:
    ok: the check ran ok
    warning: the check is closed to failing
    failed: the check did fail
    crashed: something went wrong running the check itself
    skipped: the check wasn't performed at all
*/

// TODO change skipped to crashed?
// change failed to warning?
// TODO low prio: Adapt to WP

class HealthCheck {
    const STATUS_OK = 'ok';
    const STATUS_WARNING = 'warning';
    const STATUS_FAILED = 'failed';
    const STATUS_CRASHED = 'crashed';
    const STATUS_SKIPPED = 'skipped';

    private $ohdearHealthCheckSecrets = [
        'wuk' => '8J5MbAnvx9CQMN0X',
    ];

    private $data;
    
    // variables for disk space checker
    private $usedSpaceInPercentage = '';
    private $usedSpaceStatus = '';
    
    // variables for error log filesize checker
    private $errorLogFilesize = '';
    private $errorLogFilesizeReadable = '';
    private $errorLogStatus = '';

    // variables for typo3 error log checker
    private $typo3LogStatus = '';    
    private $typo3LogTotalSize = '';    
    private $typo3LogTotalSizeReadable = '';    
    private $typo3LogNumberOfFiles = '';    
    private $typo3LogFolderPath = '';

    // variables for mysql checker
    private $mysqlSize = '';
    private $mysqlTableSizes = array();
    private $mysqlSizeReadable = '';
    private $mysqlLogStatus = '';

    // variables for "forgotten files on the server"
    private $forgottenFilesStatus = '';
    private $forgottenFilesCount = 0;
    private $forgottenFilesList = array();

    // variables for typo3 db log checker
    private $typo3DBLogNumOfRecords = '';
    private $typo3DBLogStatus = '';

    // variables for typo3 version check
    private $typo3VersionInstalled = '';    
    private $typo3VersionInstalledMajor = '';
    private $typo3VersionLatest = '';    
    private $typo3VersionStatus = '';    
    private $typo3VersionUpdateAvailable = false;    

    // Function to format bytes to a human-readable format
    private function formatBytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    // Function to check if the "oh-dear-health-check-secret" header value matches
    public function checkSecret() {
        $headerKey = "oh-dear-health-check-secret";
        $expectedValue = "8J5MbAnvx9CQMN0X";
        
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


    // Function to calculate the percentage of free disk space from total space
    function getUsedDiskSpace() {
        $totalSpace = @disk_total_space('/');
        $usedSpace = $totalSpace - @disk_free_space('/');

        // TODO: handle this case
        if ($totalSpace == 0) {
            $this->usedSpaceStatus = self::STATUS_SKIPPED;
            return 0; // To avoid division by zero error
        }

        // Calculate the percentage with 2 decimal points
        $percentage = ($usedSpace / $totalSpace) * 100;
        $this->usedSpaceInPercentage =  round($percentage, 2); // Round to 2 decimal places

        // Set the status
        if (intval($this->usedSpaceInPercentage) > 90) {
            $this->usedSpaceStatus = self::STATUS_FAILED;
        } else if (intval($this->usedSpaceInPercentage) > 75) {
            $this->usedSpaceStatus = self::STATUS_WARNING;
        } else {
            $this->usedSpaceStatus = self::STATUS_OK;
        }
    }

    // function to get size of error log
    // TODO: needs to be rewriten eg. via bash script
    function getPHPErrorLogSize() {
        $errorLogPath = ini_get('error_log');
        
        // Check if the error log file exists
        if (file_exists($errorLogPath)) {
            $this->errorLogFilesize = filesize($errorLogPath);
            
            // Format the filesize in a human-readable format
            $this->errorLogFilesizeReadable = $this->formatBytes($this->errorLogFilesize);
            
            // 500 MB
            if ($this->errorLogFilesize > 524288000) {
                $this->errorLogStatus = self::STATUS_FAILED;    
            } else if ($this->errorLogFilesize > 52428800) {   // 50 MB
                $this->errorLogStatus = self::STATUS_WARNING;    
            } else {
                $this->errorLogStatus = self::STATUS_OK;
            }

        } else {
            $this->errorLogStatus = self::STATUS_SKIPPED;
        }
    }

    // get number and size of typo3 error log
    function getTYPO3ErrorLogSize() {
        // Initialize count and size variables
        $count = 0;
        $size = 0;

        // Get the list of files in the folder
        $this->typo3LogFolderPath = $_SERVER["DOCUMENT_ROOT"].'/../var/log/';
        $files = array();
        $files = @scandir($this->typo3LogFolderPath);

        // if var folder doesn't exist, try second one
        if ($files === false) {
            $this->typo3LogFolderPath = $_SERVER["DOCUMENT_ROOT"].'/typo3temp/var/log/';
            $files = array();
            $files = @scandir($this->typo3LogFolderPath);
        }

        if (is_array($files) && count($files)) {
            
            // Iterate through each file
            foreach ($files as $file) {
                // Exclude "." and ".." directories
                if ($file !== '.' && $file !== '..') {    

                    // Check if the file matches the desired pattern
                    if (strpos($file, 'typo3_') === 0 && substr($file, -4) === '.log') {
                        // Increment count
                        $count++;

                        // Get file size in bytes
                        $fileSize = filesize($this->typo3LogFolderPath . $file);
                        
                        // Convert file size to MB
                        $size += $fileSize;
                    }
                }
            }  
            $this->typo3LogTotalSize = $size;
            $this->typo3LogTotalSizeReadable = $this->formatBytes($size);
            $this->typo3LogNumberOfFiles = $count;
            
            // if more than 100MB
            if ($this->typo3LogTotalSize > 104857600) {
                $this->typo3LogStatus = self::STATUS_FAILED;
            } 
            // if more than 50MB
            else if ($this->typo3LogTotalSize > 52428800) {
                $this->typo3LogStatus = self::STATUS_WARNING;
            } 
            else {
                $this->typo3LogStatus = self::STATUS_OK;
            }
            
        } else {
            $this->typo3LogStatus = self::STATUS_SKIPPED;
        }   
    }

    // function to get size of mysql database
    // todo: switch between typo3, wp
    function getMysqlSize() {
        // Path to the TYPO3 LocalConfiguration.php file
        $localConfigurationPath = '../typo3conf/LocalConfiguration.php';

        // Read the TYPO3 LocalConfiguration.php file
        $localConfiguration = include($localConfigurationPath);

        // Get the MySQL credentials from the configuration
        $credentials = $localConfiguration['DB']['Connections']['Default'];

        // Connect to MySQL server
        $mysqli = new mysqli($credentials['host'], $credentials['user'], $credentials['password'], $credentials['dbname']);

        // Check connection
        if ($mysqli->connect_error) {
            $this->mysqlLogStatus = self::STATUS_CRASHED;
            return;
        } else {
             // Get the size of the database
            $query = "SELECT table_schema AS 'Database', SUM(data_length + index_length) AS 'size' 
                        FROM information_schema.tables 
                        WHERE table_schema = '".$credentials['dbname']."' GROUP BY table_schema";
            $result = $mysqli->query($query);
            $row = $result->fetch_assoc();
            
            if ($row['size']) {
                $this->mysqlSize = $row['size'];
                $this->mysqlSizeReadable = $this->formatBytes($row['size']);    

                // 5000 MB
                if ($this->mysqlSize > 5242880000) {
                    $this->mysqlLogStatus = self::STATUS_FAILED;    
                } else {
                    $this->mysqlLogStatus = self::STATUS_OK;
                }
            } else {
                $this->mysqlLogStatus = self::STATUS_SKIPPED;
                return;
            }
        }

        // list 5 biggest tables
        $query = "SELECT table_name AS `Table`, round(((data_length + index_length) / 1024 / 1024), 2) AS `Size (MB)`
              FROM information_schema.tables
              WHERE table_schema = '".$credentials['dbname']."'
              ORDER BY `Size (MB)` DESC
              LIMIT 5";
            
        $result = $mysqli->query($query);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $this->mysqlTableSizes[$row['Table']] = $row['Size (MB)'].' MB';
                
            }
        }
    }

    // Scan a specified folder for commonly forgotten files or folders by developers.
    // TODO: Refactor and use allowed filename patterns instead of disallowed
    function scanDocumentRootForForgottenFiles() {
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
                        // save the matched item
                        $this->forgottenFilesList[] = $item;
                        $count++;
                        break; 
                    }
                }
            }
        }

        // Display count
        if ($count > 0) {
            $this->forgottenFilesStatus = self::STATUS_FAILED;
            $this->forgottenFilesCount = $count;
            
        } else {
            $this->forgottenFilesStatus = self::STATUS_OK;
        }
    }
    
    // get typo3 db error log records for the last one month
    function getTYPO3DBLog() { 
        // ----------------- TODO BEGIN: Move upper part to new function, because of duplicated code -----------------
        // Path to the TYPO3 LocalConfiguration.php file
        $localConfigurationPath = '../typo3conf/LocalConfiguration.php';

        // Read the TYPO3 LocalConfiguration.php file
        $localConfiguration = include($localConfigurationPath);

        // Get the MySQL credentials from the configuration
        $credentials = $localConfiguration['DB']['Connections']['Default'];
        // ----------------- TODO END: Move upper part to new function, because of duplicated code -----------------

        // Connect to MySQL server
        // Connect to MySQL server
        $mysqli = new mysqli($credentials['host'], $credentials['user'], $credentials['password'], $credentials['dbname']);

        // Check connection
        if ($mysqli->connect_error) {
            $this->mysqlLogStatus = self::STATUS_CRASHED;
        } else {
            $oneMonthAgo = strtotime('-1 month');
            $sql = "SELECT COUNT(*) AS num_records FROM sys_log WHERE error = 2 AND tstamp >= ".$oneMonthAgo;
            $result = $mysqli->query($sql);

            // Check if the query was successful
            if ($result) {
                $row = $result->fetch_assoc();
                $numRecords = $row['num_records'];

                // Check if there are more than 500 errors in the last month
                $this->typo3DBLogNumOfRecords = $numRecords;
                if ($numRecords > 500) {
                    $this->typo3DBLogStatus = self::STATUS_FAILED;
                } else {
                    $this->typo3DBLogStatus = self::STATUS_OK;
                }
            } else {
                $this->typo3DBLogStatus = self::STATUS_CRASHED;
            }

            // Close the database connection
            $mysqli->close();
        }
    }

    // check if installed typo3 version is the latest
    // TODO: also check for upgrade
    function getTYPO3Version() {
        // Get the TYPO3 composer.lock file path
        $composerFilePath = '../../composer.lock';

        if (!file_exists($composerFilePath)) {
            // try second path
            $composerFilePath = '../composer.lock'; 

            if (!file_exists($composerFilePath)) {
                $this->$typo3VersionStatus = self::STATUS_CRASHED;
                return;
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

                // Get the latest TYPO3 version in current branch
                if (function_exists('array_key_first')) {
                   $this->typo3VersionLatest = array_key_first($versionData[$this->typo3VersionInstalledMajor]['releases']);
                } else {
                    reset($versionData[$this->typo3VersionInstalledMajor]['releases']);
                    $this->typo3VersionLatest =  key($versionData[$this->typo3VersionInstalledMajor]['releases']);
                }
                
                // Compare the versions
                if ($this->typo3VersionLatest === $this->typo3VersionInstalled) {
                    $this->typo3VersionStatus = self::STATUS_OK;
                    $this->typo3VersionUpdateAvailable = false; 
                } else {
                    $this->typo3VersionStatus = self::STATUS_WARNING;    
                    $this->typo3VersionUpdateAvailable = true;
                }
            } else {
                $this->typo3VersionStatus = self::STATUS_CRASHED;
                return;
            }
        } else {
            $this->typo3VersionStatus = self::STATUS_CRASHED;
            return;
        }

    }

    // extract substring from string before the first "."
    private function extractFirstNumber($str) {
        $dotPosition = strpos($str, ".");
        if ($dotPosition !== false) {
            $number = substr($str, 0, $dotPosition);
            return (int)$number;
        }
        return null;
    }

    // get typo3 version from composer.lock
    private function getTYPO3VersionFromComposerLock($array, $key, $value) {
        foreach ($array as $item) {
            if (isset($item[$key]) && $item[$key] === $value) {
                 return str_replace("v", "", $item["version"]) ;
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


    // function to populate data for ohdear response
    function generateData() {
        $this->data = array(
            'finishedAt' => time(),
            'checkResults' => array(
                array(
                    "name" => "UsedDiskSpace",
                    "label" => "Used Disk Space",
                    "status" => $this->usedSpaceStatus,
                    "notificationMessage" => "Disk usage: ".$this->usedSpaceStatus." (".$this->usedSpaceInPercentage."% used)",
                    "shortSummary" => $this->usedSpaceInPercentage."%",
                    "meta" => array("disk_space_used_percentage" => $this->usedSpaceInPercentage)
                ),
                array(
                    "name" => "ErrorLogFilesize",
                    "label" => "Error Log Filesize",
                    "status" => $this->errorLogStatus,
                    "notificationMessage" => "Error Log Filesize: ".$this->errorLogStatus." (".$this->errorLogFilesizeReadable.")",
                    "shortSummary" => $this->errorLogFilesizeReadable,
                    "meta" => array("error_log_filesize" => $this->errorLogFilesizeReadable)
                ),
                array(
                    "name" => "MysqlSize",
                    "label" => "Mysql Size",
                    "status" => $this->mysqlLogStatus,
                    "notificationMessage" => "Mysql size: ".$this->mysqlLogStatus." (".$this->mysqlSizeReadable.")",
                    "shortSummary" => $this->mysqlSizeReadable,
                    "meta" => $this->mysqlTableSizes
                ),
                 array(
                    "name" => "TYPO3LogSize",
                    "label" => "TYPO3 Log Size",
                    "status" => $this->typo3LogStatus,
                    "notificationMessage" => "TYPO3 log ".$this->typo3LogFolderPath." size: ".$this->typo3LogStatus." (".$this->typo3LogTotalSizeReadable.")",
                    "shortSummary" => $this->typo3LogTotalSizeReadable,
                    "meta" => array("typo3_log_filesize" => $this->typo3LogTotalSizeReadable)
                 ),
                 array(
                    "name" => "ForgottenFiles",
                    "label" => "Forgotten Files",
                    "status" => $this->forgottenFilesStatus,
                    "notificationMessage" => "Forgotten Files ".$this->forgottenFilesStatus." (count: ".$this->forgottenFilesCount.")",
                    "shortSummary" => "count: ".$this->forgottenFilesCount,
                    "meta" => $this->forgottenFilesList
                ),
                array(
                    "name" => "TYPO3DBLog",
                    "label" => "TYPO3 DB Log",
                    "status" => $this->typo3DBLogStatus,
                    "notificationMessage" => "TYPO3 DB Log ".$this->typo3DBLogStatus." (Num errors for last month: ".$this->typo3DBLogNumOfRecords.")",
                    "shortSummary" => "Num errors for last month: ".$this->typo3DBLogNumOfRecords,
                    "meta" => array()
                ),
                 array(
                    "name" => "TYPO3Version",
                    "label" => "TYPO3 Version",
                    "status" => $this->typo3VersionStatus,
                    "notificationMessage" => (!$this->typo3VersionUpdateAvailable ? "TYPO3 is running the latest version " . $this->typo3VersionInstalled : "TYPO3 update available"),
                    "shortSummary" => "" . (!$this->typo3VersionUpdateAvailable ? "TYPO3 version OK " : "TYPO3 update available"),
                    "meta" => array(
                        "typo3_version_installed" => $this->typo3VersionInstalled,
                        "typo3_version_available" => $this->typo3VersionLatest,
                    )
                )
            ),
        );
    }

    // function to send data for ohdear
    function sendData() {
        // Convert the array to JSON format
        $jsonData = json_encode($this->data, JSON_PRETTY_PRINT);

        // Output the JSON data
        header('Content-Type: application/json');
        echo $jsonData;
    }
}

$healthCheck = new HealthCheck();
// TODO: add security check: $healthCheck->checkSecret(); 

$healthCheck->getUsedDiskSpace();
$healthCheck->getPHPErrorLogSize();
$healthCheck->getTYPO3ErrorLogSize();
$healthCheck->getMysqlSize();
$healthCheck->scanDocumentRootForForgottenFiles();
$healthCheck->getTYPO3DBLog();
$healthCheck->getTYPO3Version();
$healthCheck->generateData();
$healthCheck->sendData();

?>
