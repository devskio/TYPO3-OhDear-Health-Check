<?php

namespace Devskio\Typo3OhDearHealthCheck\Checks;

use OhDear\HealthCheckResults\CheckResult;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Class DiskUsedSpace
 * @package Devskio\Typo3OhDearHealthCheck\Checks
 */
class DiskUsedSpace extends AbstractCheck
{
    /**
     * Run the health check.
     *
     * @return CheckResult The result of the health check.
     */
    public function run(): CheckResult
    {
        $diskUsage = $this->getEmptyResult();
        if ($this->configuration['diskSpaceWarningCustomCheckEnabled']) {
            $diskUsage = $this->calculateDiskUsage();
        }

        $identifier = self::getIdentifier();
        return new CheckResult(
            $identifier,
            LocalizationUtility::translate("check.{$identifier}.label", 'typo3_ohdear_health_check'),
            LocalizationUtility::translate("check.{$identifier}.notificationMessage", 'typo3_ohdear_health_check', [$diskUsage['disk_space_used_percentage']]),
            LocalizationUtility::translate("check.{$identifier}.shortSummary", 'typo3_ohdear_health_check', [$diskUsage['disk_space_used_percentage']]),
            $diskUsage['status'],
            [
                'disk_space_used_percentage' => $diskUsage['disk_space_used_percentage'] . '%',
                'total_space' => $diskUsage['total_space'],
                'used_space' => $diskUsage['used_space'],
            ]
        );
    }

    /**
     * Calculate the used disk space and determine the status.
     *
     * @return array An array with the used space in percentage, the status, the total space and the used space.
     */
    private function calculateDiskUsage(): CheckResult|array
    {
        $path = Environment::getPublicPath();

        $totalSpace = @disk_total_space($path);

        if (!$totalSpace) {
            return $this->getEmptyResult(CheckResult::STATUS_CRASHED);
        }

        $usedSpace = ($totalSpace - @disk_free_space($path)) ?? 0;
        // Calculate the percentage with 2 decimal points
        $percentage = ($usedSpace / $totalSpace) * 100;
        $usedSpaceInPercentage = round($percentage, 2); // Round to 2 decimal places

        // Set the status
        $status = $this->determineStatus(
            intval($usedSpaceInPercentage),
            intval($this->configuration['diskSpaceWarningThresholdError']),
            intval($this->configuration['diskSpaceWarningThresholdWarning'])
        );

        return [
            'disk_space_used_percentage' => $usedSpaceInPercentage,
            'status' => $status,
            'total_space' => $this->formatBytes($totalSpace),
            'used_space' => $this->formatBytes($usedSpace),
        ];
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

    /**
     * Get empty result.
     * @param string $status
     *
     * @return array
     */
    protected function getEmptyResult(string $status = CheckResult::STATUS_SKIPPED): array
    {
        return [
            'disk_space_used_percentage' => 0,
            'status' => $status ?? CheckResult::STATUS_SKIPPED,
            'total_space' => 'N/A',
            'used_space' => 'N/A'
        ];
    }
}
