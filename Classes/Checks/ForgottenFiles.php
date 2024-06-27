<?php

namespace Devskio\Typo3OhDearHealthCheck\Checks;

use OhDear\HealthCheckResults\CheckResult;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Class ForgottenFiles
 * @package Devskio\Typo3OhDearHealthCheck\Checks
 */
class ForgottenFiles extends AbstractCheck
{
    /**
     * List of allowed files and folders.
     *
     * @var array
     */
    protected $allowedFiles = [
        '.htaccess',
        'index.php',
        'robots.txt',
        '_assets',
        'fileadmin',
        'typo3',
        'typo3conf',
        'typo3temp',
    ];

    /**
     * ForgottenFiles constructor.
     *
     * @param array $configuration
     */
    public function __construct(array $configuration)
    {
        parent::__construct($configuration);
        if (
            $this->configuration['allowedFilesWarningCustomCheckEnabled']
            && !empty($this->configuration['allowedFiles'])
        ) {
            $this->allowedFiles = array_merge(
                $this->allowedFiles,
                array_map('trim', explode("\n", $this->configuration['allowedFiles'])),
            );
        }
    }

    /**
     * Run the health check.
     *
     * @return CheckResult The result of the health check.
     */
    public function run(): CheckResult
    {
        $count = 0;
        $forgottenFilesList = [];
        $items = $this->getItemsInRootDirectory();
        $status = CheckResult::STATUS_SKIPPED;

        if ($this->configuration['allowedFilesWarningCustomCheckEnabled']) {
            foreach ($items as $item) {
                if (!$this->isAllowedItem($item)) {
                    $forgottenFilesList[] = $item;
                    $count++;
                }
            }

            $status = ($count > 0) ? CheckResult::STATUS_FAILED : CheckResult::STATUS_OK;
        }

        $identifier = self::getIdentifier();
        return new CheckResult(
            $identifier,
            LocalizationUtility::translate("check.{$identifier}.label", 'typo3_ohdear_health_check'),
            LocalizationUtility::translate("check.{$identifier}.notificationMessage", 'typo3_ohdear_health_check', [$count]),
            LocalizationUtility::translate("check.{$identifier}.shortSummary", 'typo3_ohdear_health_check', [$count]),
            $status,
            ['unallowed_files_list' => $forgottenFilesList]
        );
    }

    /**
     * Get the list of items in the root directory.
     *
     * @return array The list of items.
     */
    private function getItemsInRootDirectory(): array
    {
        return scandir($_SERVER['DOCUMENT_ROOT']);
    }

    /**
     * Check if an item is allowed.
     *
     * @param string $item The item to check.
     * @return bool True if the item is allowed, false otherwise.
     */
    private function isAllowedItem(string $item): bool
    {
        if ($item === '.' || $item === '..') {
            return true;
        }

        foreach ($this->allowedFiles as $pattern) {
            if (fnmatch($pattern, $item)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Default configuration for this check.
     *
     * @return array
     */
    public function getDefaultConfiguration(): array
    {
        return [
            'allowedFilesWarningCustomCheckEnabled' => true,
            'allowedFiles' => []
        ];
    }
}
