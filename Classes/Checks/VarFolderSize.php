<?php

namespace Devskio\Typo3OhDearHealthCheck\Checks;

use OhDear\HealthCheckResults\CheckResult;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Core\Environment;

/**
 * Class VarFolderSize
 * @package Devskio\Typo3OhDearHealthCheck\Checks
 */
class VarFolderSize extends AbstractCheck
{

    /**
     * AbstractCheck constructor.
     *
     * @param ExtensionConfiguration $extensionConfiguration
     */
    public function __construct(ExtensionConfiguration $extensionConfiguration)
    {
        parent::__construct($extensionConfiguration);
    }

    /**
     * Run the health check.
     *
     * @return CheckResult The result of the health check.
     */
    public function run(): CheckResult
    {
        $varFolderPath = Environment::getVarPath();
        $varFolderSize = $this->getFolderSize($varFolderPath);

        if ($varFolderSize === false) {
            return $this->createSkippedResult();
        }

        $status = $this->determineStatus(
            $varFolderSize,
            $this->varFolderSizeWarningThresholdError,
            $this->varFolderSizeWarningThresholdWarning
        );

        // Convert file size to MB
        $varFolderSizeInMB = round($varFolderSize / (1024 * 1024), 2);

        return $this->createHealthCheckResult(
            'TYPO3VarFolderSize',
            'TYPO3 Var Folder Size',
            $status,
            sprintf('TYPO3VarFolderSize: %s (%sMB)', $status, $varFolderSizeInMB),
            sprintf('TYPO3 Var Folder Size: %s (%sMB)', $status, $varFolderSizeInMB),
            ['var_folder_size' => $varFolderSizeInMB]
        );
    }

    /**
     * Get the size of a folder.
     *
     * @param string $folder The path to the folder.
     * @return bool|float The size of the folder in bytes, or false if the folder does not exist.
     */
    private function getFolderSize(string $folder): bool|float
    {
        if (!is_dir($folder)) {
            return false;
        }

        $totalSize = 0;
        $handle = opendir($folder);

        while (false !== ($entry = readdir($handle))) {
            if ($entry != "." && $entry != "..") {
                $entryPath = $folder . DIRECTORY_SEPARATOR . $entry;

                if (is_dir($entryPath)) {
                    $totalSize += $this->getFolderSize($entryPath);
                } elseif (is_file($entryPath)) {
                    $totalSize += filesize($entryPath);
                }
            }
        }

        closedir($handle);
        return $totalSize;
    }

    /**
     * Create a CheckResult with the status set to SKIPPED.
     *
     * @return CheckResult The created CheckResult.
     */
    private function createSkippedResult(): CheckResult
    {
        return $this->createHealthCheckResult(
            'TYPO3VarFolderSize',
            'TYPO3 Var Folder Size',
            CheckResult::STATUS_SKIPPED,
            'Var Folder Not Found',
            'SKIPPED',
            []
        );
    }
}
