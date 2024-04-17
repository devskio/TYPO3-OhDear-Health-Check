<?php

namespace Devskio\Typo3OhDearHealthCheck\Checks;

use OhDear\HealthCheckResults\CheckResult;

/**
 * Class PhpErrorLogSize
 * @package Devskio\Typo3OhDearHealthCheck\Checks
 */
class PhpErrorLogSize extends AbstractCheck
{

    /**
     * The identifier of the check.
     *
     * @var string
     */
    const IDENTIFIER = 'phpErrorLogSize';

    /**
     * PhpErrorLogSize constructor.
     *
     * @param array $configuration
     */
    public function __construct(array $configuration)
    {
        parent::__construct($configuration);
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
            $this->configuration['errorLogSizeWarningThresholdError'],
            $this->configuration['errorLogSizeWarningThresholdWarning']
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

    /**
     * Default configuration for this check.
     *
     * @return array
     */
    public function getDefaultConfiguration(): array
    {
        return [
            'errorLogSizeWarningCustomCheckEnabled' => true,
            'errorLogSizeWarningThresholdError' => 10000000,
            'errorLogSizeWarningThresholdWarning' => 5000000,
        ];
    }
}
