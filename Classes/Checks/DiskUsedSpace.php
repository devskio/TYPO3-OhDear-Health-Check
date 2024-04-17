<?php

namespace Devskio\Typo3OhDearHealthCheck\Checks;

use OhDear\HealthCheckResults\CheckResult;

/**
 * Class DiskUsedSpace
 * @package Devskio\Typo3OhDearHealthCheck\Checks
 */
class DiskUsedSpace extends AbstractCheck
{

    /**
     * The identifier of the check.
     *
     * @var string
     */
    const IDENTIFIER = 'diskUsedSpace';

    /**
     * DiskUsedSpace constructor.
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
        list($usedSpaceInPercentage, $status) = $this->calculateDiskUsage();

        return $this->createHealthCheckResult(
            'UsedDiskSpace',
            'Used Disk Space',
            $status,
            sprintf('Disk usage: %s (%.2f%% used)', $status, $usedSpaceInPercentage),
            sprintf('%.2f%%', $usedSpaceInPercentage),
            ['disk_space_used_percentage' => $usedSpaceInPercentage]
        );
    }

    /**
     * Calculate the used disk space and determine the status.
     *
     * @return array|CheckResult An array with the used space in percentage and the status, or a CheckResult instance if total space is zero.
     */
    private function calculateDiskUsage(): CheckResult|array
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
        $status = $this->determineStatus(
            intval($usedSpaceInPercentage),
            $this->configuration['diskSpaceWarningThresholdError'],
            $this->configuration['diskSpaceWarningThresholdWarning']
        );

        return [$usedSpaceInPercentage, $status];
    }

    /**
     * Default configuration for this check.
     *
     * @return array
     */
    public function getDefaultConfiguration(): array
    {
        return [
            'diskSpaceWarningCustomCheckEnabled' => true,
            'diskSpaceWarningThresholdError' => 90,
            'diskSpaceWarningThresholdWarning' => 80,
        ];
    }
}
