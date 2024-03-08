<?php

namespace Devskio\Typo3OhDearHealthCheck\Checks;

use OhDear\HealthCheckResults\CheckResult;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

/**
 * Class PhpErrorLogSize
 * @package Devskio\Typo3OhDearHealthCheck\Checks
 */
class PhpErrorLogSize extends AbstractCheck
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
        $errorLogPath = $this->getErrorLogFile();
        if ($errorLogPath instanceof CheckResult) {
            return $errorLogPath;
        }
        $errorLogFilesize = filesize($errorLogPath);
        $errorLogFilesizeReadable = $this->formatBytes($errorLogFilesize);

        $status = $this->determineStatus(
            $errorLogFilesizeReadable,
            $this->errorLogSizeWarningThresholdError,
            $this->errorLogSizeWarningThresholdWarning
        );

        return $this->createHealthCheckResult(
            'PHPErrorLogSize',
            'PHP Error Log Size',
            $status,
            'Error Log Filesize: ' . $status . ' (' . $errorLogFilesizeReadable . ')',
            $errorLogFilesizeReadable,
            ['error_log_filesize' => $errorLogFilesizeReadable]
        );
    }

    /**
     *  Get Php Error Log File
     *
     * @return CheckResult|string File or skipped result check
     */
    private function getErrorLogFile (): CheckResult|string
    {
        $errorLogPath = ini_get('error_log');
        if (!file_exists($errorLogPath)) {
            return $this->createHealthCheckResult(
                'PHPErrorLogSize',
                'PHP Error Log Size',
                CheckResult::STATUS_SKIPPED,
                'Error Log File does not exist',
                'SKIPPED',
                ['error_log_filesize' => 'SKIPPED']
            );
        }
        return $errorLogPath;
    }
}
