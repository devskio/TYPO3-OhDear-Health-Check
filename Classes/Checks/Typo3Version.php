<?php

namespace Devskio\Typo3OhDearHealthCheck\Checks;

use OhDear\HealthCheckResults\CheckResult;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Class Typo3Version
 * @package Devskio\Typo3OhDearHealthCheck\Checks
 */
class Typo3Version extends AbstractCheck
{
    /**
     * Typo3 version url
     */
    const TYPO3_VERSION_URL = 'https://get.typo3.org/json';

    /**
     * Run the health check.
     *
     * @return CheckResult The result of the health check.
     */
    public function run(): CheckResult
    {
        $identifier = self::getIdentifier();

        try {
            $typo3VersionInstalled = $this->getTYPO3VersionFromComposerLock();
            $typo3VersionLatest = $this->getLatestMinorTypo3Version($typo3VersionInstalled);

            $status = $typo3VersionLatest === $typo3VersionInstalled ? CheckResult::STATUS_OK : CheckResult::STATUS_WARNING;
            $message = LocalizationUtility::translate("check.{$identifier}.notificationMessage" . ($status === CheckResult::STATUS_OK ? '.ok' : ''), 'typo3_ohdear_health_check', [$typo3VersionInstalled, $typo3VersionLatest]);
        } catch (\Exception $e) {
            $status = CheckResult::STATUS_CRASHED;
            $message = $e->getMessage();
        }

        return new CheckResult(
            $identifier,
            LocalizationUtility::translate("check.{$identifier}.label", 'typo3_ohdear_health_check'),
            $message,
            LocalizationUtility::translate("check.{$identifier}.shortSummary", 'typo3_ohdear_health_check', [$status]),
            $status,
            ['installed_version' => $typo3VersionInstalled, 'latest_version' => $typo3VersionLatest]
        );
    }

    /**
     * Get the latest minor TYPO3 version.
     *
     * @param array $versionData The TYPO3 version data.
     * @return string The latest TYPO3 version.
     */
    private function getLatestMinorTypo3Version(string $installedVersion): string
    {
        $identifier = self::getIdentifier();

        $typo3VersionJson = file_get_contents(self::TYPO3_VERSION_URL);
        if ($typo3VersionJson === false) {
            throw new \Exception(LocalizationUtility::translate("check.{$identifier}.notificationMessage.error_fetching", 'typo3_ohdear_health_check'));
        }

        $typo3VersionData = json_decode($typo3VersionJson, true);
        if (!is_array($typo3VersionData)) {
            throw new \Exception(LocalizationUtility::translate("check.{$identifier}.notificationMessage.error_fetching", 'typo3_ohdear_health_check'));
        }

        $installedMajorVersion = substr($installedVersion, 0, strpos($installedVersion, "."));

        reset($typo3VersionData[$installedMajorVersion]['releases']);
        return key($typo3VersionData[$installedMajorVersion]['releases']);
    }

    /**
     * Get TYPO3 version from composer.lock.
     *
     * @param array $array
     * @param string $key
     * @param string $value
     * @return string|null
     *
     * @throws \Exception
     */
    private function getTYPO3VersionFromComposerLock(): ?string
    {
        $identifier = self::getIdentifier();

        $composerFilePath = Environment::getProjectPath() . '/composer.lock';
        if (!file_exists($composerFilePath)) {
            throw new \Exception(LocalizationUtility::translate("check.{$identifier}.notificationMessage.not_found", 'typo3_ohdear_health_check'));
        }

        $composerJson = file_get_contents($composerFilePath);
        if ($composerJson === false) {
            throw new \Exception(LocalizationUtility::translate("check.{$identifier}.notificationMessage.not_found", 'typo3_ohdear_health_check'));
        }

        $composerData = json_decode($composerJson, true);
        if (!is_array($composerData) || !array_key_exists('packages', $composerData)) {
            throw new \Exception(LocalizationUtility::translate("check.{$identifier}.notificationMessage.error_parsing", 'typo3_ohdear_health_check'));
        }

        foreach ($composerData['packages'] as $package) {
            if ($package['name'] === 'typo3/cms-core') {
                return str_replace("v", "", $package['version']);
            }
        }

        throw new \Exception(LocalizationUtility::translate("check.{$identifier}.notificationMessage.not_found", 'typo3_ohdear_health_check'));
    }
}
