<?php

namespace Devskio\Typo3OhDearHealthCheck\Checks;

use OhDear\HealthCheckResults\CheckResult;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Class PhpErrorLogSize
 * @package Devskio\Typo3OhDearHealthCheck\Checks
 */
class PhpErrorLogSize extends AbstractCheck
{
    /**
     * Run the health check.
     *
     * @return CheckResult The result of the health check.
     */
    public function run(): CheckResult
    {
        $errorLogFilesizeReadable = 'N/A';
        $status = CheckResult::STATUS_SKIPPED;

        if ($this->configuration['errorLogSizeWarningCustomCheckEnabled']) {
            $errorLogPath = $this->getErrorLogFile();
            if ($errorLogPath) {
                $errorLogFilesize = filesize($errorLogPath);
                $errorLogFilesizeReadable = $this->formatBytes($errorLogFilesize);
                $status = $this->determineStatus(
                    $errorLogFilesize,
                    $this->configuration['errorLogSizeWarningThresholdError'],
                    $this->configuration['errorLogSizeWarningThresholdWarning']
                );
            }
        }

        $identifier = self::getIdentifier();
        return new CheckResult(
            $identifier,
            LocalizationUtility::translate("check.{$identifier}.label", 'typo3_ohdear_health_check'),
            LocalizationUtility::translate("check.{$identifier}.notificationMessage", 'typo3_ohdear_health_check', [$errorLogFilesizeReadable]),
            LocalizationUtility::translate("check.{$identifier}.shortSummary", 'typo3_ohdear_health_check', [$errorLogFilesizeReadable]),
            $status,
            []
        );
    }

    /**
     *  Get Php Error Log File
     *
     * @return bool|string File or false if file does not exist.
     */
    private function getErrorLogFile(): bool|string
    {
        $errorLogPath = ini_get('error_log');
        if (!file_exists($errorLogPath)) {
            return false;
        }
        return $errorLogPath;
    }

    /**
     * Default configuration for this check.
     *
     * @return array
     */
    public function getDefaultConfiguration(): array
    {
        return [
            'errorLogSizeWarningCustomCheckEnabled' => 1,
            'errorLogSizeWarningThresholdError' => 500000000,
            'errorLogSizeWarningThresholdWarning' => 50000000,
        ];
    }
}
