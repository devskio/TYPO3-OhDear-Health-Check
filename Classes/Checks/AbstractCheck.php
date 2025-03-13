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
     * Function to format bytes to a human-readable format in MB.
     *
     * @param float $bytes The size in bytes.
     * @param int $precision The number of decimal places to round to.
     * @return string The size in MB.
     */
    protected function formatBytes(float $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB']; /// upravit na MB, GB, TB pripominu
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    /**
     * Function to change human-readable format in KB,MB,GB,TB to bytes.
     *
     * @param mixed $size The size from input.
     * @return float The size in bytes.
     */
    protected function convertToBytes(mixed $size): float
    {
        $size = trim($size);
        $unit = strtoupper(substr($size, -2));
        $value = (float) trim(substr($size, 0, -2));

        switch ($unit) {
            case 'KB':
                return $value * 1024;
            case 'MB':
                return $value * 1024 * 1024;
            case 'GB':
                return $value * 1024 * 1024 * 1024;
            case 'TB':
                return $value * 1024 * 1024 * 1024 * 1024;
            default:
                return (float)$size;
        }
    }

    /**
     * Function to change human-readable format of time in days, hours or minutes into seconds.
     *
     * @param mixed $time The time from input.
     * @return float The time in seconds.
     */
    function convertToSeconds($time) {
        $time = trim($time);
        $unit = strtoupper(substr($time, -1));
        $value = (float) trim(substr($time, 0, -1));

        switch ($unit) {
            case 'M':
                return $value * 60;
            case 'H':
                return $value * 60 * 60;
            case 'D':
                return $value * 60 * 60 * 24;
            default:
                return (float)$time;
        }
    }

    /**
     * Function to determine the status of the check based on the value and thresholds.
     *
     * @param int $value The value to check.
     * @param int $errorThreshold The error threshold.
     * @param int $warningThreshold The warning threshold.
     * @return string The status of the check.
     */
    protected function determineStatus(int $value, int $errorThreshold, int $warningThreshold): string
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

    /**
     * @return string
     */
    public static function getIdentifier(): string
    {
        return lcfirst((new \ReflectionClass(get_called_class()))->getShortName());
    }
}
