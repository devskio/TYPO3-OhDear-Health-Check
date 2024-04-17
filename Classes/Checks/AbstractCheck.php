<?php

namespace Devskio\Typo3OhDearHealthCheck\Checks;

use OhDear\HealthCheckResults\CheckResult;

/**
 * Class AbstractCheck
 * @package Devskio\Typo3OhDearHealthCheck\Checks
 */
abstract class AbstractCheck
{

    /**
     * AbstractCheck constructor.
     *
     * @param array $configuration
     */
    public function __construct(array $configuration)
    {
        $defaultConfiguration = $this->getDefaultConfiguration();

        foreach ($defaultConfiguration as $key => $defaultValue) {
            if (isset($configuration[$key]) && $configuration[$key] !== '') {
                $defaultConfiguration[$key] = $configuration[$key];
            }
        }
        $this->configuration = $defaultConfiguration;
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
    protected function createHealthCheckResult(
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

    /**
     * Function to format bytes to a human-readable format in MB.
     *
     * @param float $bytes The size in bytes.
     * @param int $precision The number of decimal places to round to.
     * @return string The size in MB.
     */
    protected function formatBytes(float $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    /**
     * Function to determine the status of the check based on the value and thresholds.
     *
     * @param string $value The value to check.
     * @param string $errorThreshold The error threshold.
     * @param string $warningThreshold The warning threshold.
     * @return string The status of the check.
     */
    protected function determineStatus(string $value, string $errorThreshold, string $warningThreshold): string
    {
        if ($value > $errorThreshold) {
            return CheckResult::STATUS_FAILED;
        } else if ($value > $warningThreshold) {
            return CheckResult::STATUS_WARNING;
        } else {
            return CheckResult::STATUS_OK;
        }
    }

    /**
     * Abstract method to be implemented by each check.
     *
     * @return CheckResult
     */
    abstract public function run(): CheckResult;

    /**
     * Default configuration for this check.
     *
     * @return array
     */
    public function getDefaultConfiguration(): array
    {
        return [];
    }
}
