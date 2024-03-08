<?php

namespace Devskio\Typo3OhDearHealthCheck\Checks;

use OhDear\HealthCheckResults\CheckResult;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

/**
 * Class DiskUsedSpace
 * @package Devskio\Typo3OhDearHealthCheck\Checks
 */
class DiskUsedSpace extends AbstractCheck
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
        $status = $this->determineStatus(intval($usedSpaceInPercentage), $this->diskSpaceWarningThresholdError, $this->diskSpaceWarningThresholdWarning);

        return [$usedSpaceInPercentage, $status];
    }
}
