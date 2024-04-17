<?php

namespace Devskio\Typo3OhDearHealthCheck\Checks;

use OhDear\HealthCheckResults\CheckResult;

/**
 * Class Typo3Version
 * @package Devskio\Typo3OhDearHealthCheck\Checks
 */
class Typo3Version extends AbstractCheck
{

    /**
     * The identifier of the check.
     *
     * @var string
     */
    const IDENTIFIER = 'typo3Version';

    /**
     * Typo3 version url
     */
    const TYPO3_VERSION_URL = 'https://get.typo3.org/json';

    /**
     * Typo3Version constructor.
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
        $composerFilePath = $this->getComposerFilePath();

        if ($composerFilePath === null) {
            return $this->createCrashedResult('Cannot find composer.lock file');
        }

        $composerData = $this->getComposerData($composerFilePath);

        if ($composerData === null) {
            return $this->createCrashedResult('Error parsing composer.lock file');
        }

        $this->typo3VersionInstalled = $this->getTYPO3VersionFromComposerLock($composerData, "name", "typo3/cms-core");
        $this->typo3VersionInstalledMajor = $this->extractFirstNumber($this->typo3VersionInstalled);

        $versionData = $this->fetchTypo3VersionData();

        if ($versionData === null) {
            return $this->createCrashedResult('Error fetching TYPO3 version data from server');
        }

        $this->typo3VersionLatest = $this->getLatestTypo3Version($versionData);

        if ($this->typo3VersionLatest === $this->typo3VersionInstalled) {
            return $this->createHealthCheckResult(
                'TYPO3Version',
                'TYPO3 Version',
                CheckResult::STATUS_OK,
                sprintf('Installed TYPO3 version %s is up to date', $this->typo3VersionInstalled),
                CheckResult::STATUS_OK,
                ['installed_version' => $this->typo3VersionInstalled]
            );
        } else {
            return $this->createHealthCheckResult(
                'TYPO3Version',
                'TYPO3 Version',
                CheckResult::STATUS_WARNING,
                sprintf('Update available: Installed TYPO3 version is %s, Latest version is %s', $this->typo3VersionInstalled, $this->typo3VersionLatest),
                CheckResult::STATUS_WARNING,
                ['installed_version' => $this->typo3VersionInstalled, 'latest_version' => $this->typo3VersionLatest]
            );
        }
    }

    /**
     * Get the composer file path.
     *
     * @return string|null The composer file path, or null if the file does not exist.
     */
    private function getComposerFilePath(): ?string
    {
        $composerFilePath = '../../composer.lock';

        if (!file_exists($composerFilePath)) {
            $composerFilePath = '../composer.lock';

            if (!file_exists($composerFilePath)) {
                return null;
            }
        }

        return $composerFilePath;
    }

    /**
     * Get the composer data.
     *
     * @param string $composerFilePath The path to the composer file.
     * @return array|null The composer data, or null if the data could not be parsed.
     */
    private function getComposerData(string $composerFilePath): ?array
    {
        $composerJson = file_get_contents($composerFilePath);
        $composerData = json_decode($composerJson, true);

        return is_array($composerData) && count($composerData) > 0 ? $composerData : null;
    }

    /**
     * Fetch TYPO3 version data.
     *
     * @return array|null The TYPO3 version data, or null if the data could not be fetched.
     */
    private function fetchTypo3VersionData(): ?array
    {
        $versionJson = file_get_contents(self::TYPO3_VERSION_URL);
        $versionData = json_decode($versionJson, true);
        return is_array($versionData) && count($versionData) > 0 ? $versionData : null;
    }

    /**
     * Get the latest TYPO3 version.
     *
     * @param array $versionData The TYPO3 version data.
     * @return string The latest TYPO3 version.
     */
    private function getLatestTypo3Version(array $versionData): string
    {
        if (function_exists('array_key_first')) {
            return array_key_first($versionData[$this->typo3VersionInstalledMajor]['releases']);
        } else {
            reset($versionData[$this->typo3VersionInstalledMajor]['releases']);
            return key($versionData[$this->typo3VersionInstalledMajor]['releases']);
        }
    }

    /**
     * Create a CheckResult with the status set to CRASHED.
     *
     * @param string $message The message to include in the CheckResult.
     * @return CheckResult The created CheckResult.
     */
    private function createCrashedResult(string $message): CheckResult
    {
        return $this->createHealthCheckResult(
            'TYPO3Version',
            'TYPO3 Version',
            CheckResult::STATUS_CRASHED,
            $message,
            'CRASHED',
            []
        );
    }

    /**
     * Extract substring from string before the first ".".
     *
     * @param string $str
     * @return int|null
     */
    private function extractFirstNumber($str): ?int
    {
        $dotPosition = strpos($str, ".");
        if ($dotPosition !== false) {
            $number = substr($str, 0, $dotPosition);
            return (int)$number;
        }
        return null;
    }

    /**
     * Get TYPO3 version from composer.lock.
     *
     * @param array $array
     * @param string $key
     * @param string $value
     * @return string|null
     */
    private function getTYPO3VersionFromComposerLock($array, $key, $value): ?string
    {
        foreach ($array as $item) {
            if (isset($item[$key]) && $item[$key] === $value) {
                return str_replace("v", "", $item["version"]);
            }

            if (is_array($item)) {
                $result = $this->getTYPO3VersionFromComposerLock($item, $key, $value);
                if ($result !== null) {
                    return $result;
                }
            }
        }
        return null;
    }
}
