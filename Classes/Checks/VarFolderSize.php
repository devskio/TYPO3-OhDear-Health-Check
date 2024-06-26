<?php

namespace Devskio\Typo3OhDearHealthCheck\Checks;

use OhDear\HealthCheckResults\CheckResult;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Class VarFolderSize
 * @package Devskio\Typo3OhDearHealthCheck\Checks
 */
class VarFolderSize extends AbstractCheck
{
    /**
     * Run the health check.
     *
     * @return CheckResult The result of the health check.
     */
    public function run(): CheckResult
    {
        $identifier = self::getIdentifier();
        $status = CheckResult::STATUS_SKIPPED;
        $varFolderSize = 0;

        if ($this->configuration['varFolderSizeWarningCustomCheckEnabled']) {
            $varFolderPath = Environment::getVarPath();
            $varFolderSize = $this->getFolderSize($varFolderPath);

            if ($varFolderSize) {
                $status = $this->determineStatus(
                    $varFolderSize,
                    $this->configuration['varFolderSizeWarningThresholdError'],
                    $this->configuration['varFolderSizeWarningThresholdWarning']
                );
            } else {
                $status = CheckResult::STATUS_CRASHED;
            }
        }

        return new CheckResult(
            $identifier,
            LocalizationUtility::translate("check.{$identifier}.label", 'typo3_ohdear_health_check'),
            LocalizationUtility::translate("check.{$identifier}.notificationMessage", 'typo3_ohdear_health_check', [$this->formatBytes($varFolderSize)]),
            LocalizationUtility::translate("check.{$identifier}.shortSummary", 'typo3_ohdear_health_check', [$this->formatBytes($varFolderSize)]),
            $status,
            ['var_folder_size' => $this->formatBytes($varFolderSize)]
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
     * Default configuration for this check.
     *
     * @return array
     */
    public function getDefaultConfiguration(): array
    {
        return [
            'varFolderSizeWarningCustomCheckEnabled' => true,
            'varFolderSizeWarningThresholdError' => 500000000,
            'varFolderSizeWarningThresholdWarning' => 50000000,
        ];
    }
}
