<?php

namespace Devskio\Typo3OhDearHealthCheck\Checks;

use OhDear\HealthCheckResults\CheckResult;
use TYPO3\CMS\Core\Core\Environment;

/**
 * Class ForgottenFiles
 * @package Devskio\Typo3OhDearHealthCheck\Checks
 */
class ForgottenFiles extends AbstractCheck
{

    /**
     * The identifier of the check.
     *
     * @var string
     */
    const IDENTIFIER = 'forgottenFiles';

    /**
     * ForgottenFiles constructor.
     *
     * @param array $configuration
     */
    public function __construct(array $configuration)
    {
        parent::__construct($configuration);
        if (
            isset($this->configuration['allowedFilesWarningCustomCheckEnabled'])
            && $this->configuration['allowedFilesWarningCustomCheckEnabled']
            && isset($this->configuration['allowedFiles'])
            && !empty($this->configuration['allowedFiles'])
        ) {
            $this->configuration['allowedFiles'] = array_map('trim', explode("\n", $this->configuration['allowedFiles']));
        } else {
            $this->configuration['allowedFiles'] = $this->getDefaultConfiguration()['allowedFiles'];
        }
    }

    /**
     * Run the health check.
     *
     * @return CheckResult The result of the health check.
     */
    public function run(): CheckResult
    {
        $items = $this->getItemsInRootDirectory();

        $count = 0;
        foreach ($items as $item) {
            if (!$this->isAllowedItem($item)) {
                $this->forgottenFilesList[] = $item;
                $count++;
            }
        }

        if ($count > 0) {
            return $this->createHealthCheckResult(
                'ForgottenFiles',
                'Forgotten Files',
                CheckResult::STATUS_FAILED,
                sprintf('Found %d unallowed files or folders', $count),
                sprintf('%d unallowed files', $count),
                ['unallowed_files_list' => $this->forgottenFilesList]
            );
        } else {
            return $this->createHealthCheckResult(
                'ForgottenFiles',
                'Forgotten Files',
                CheckResult::STATUS_OK,
                'No unallowed files or folders found',
                'No unallowed files found',
                []
            );
        }
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
        $allowedFiles = array_merge($this->configuration['allowedFiles'], [
            '.htaccess',
            'index.php',
            'license.txt',
            'fileadmin',
            'uploads',
            'typo3',
            'typo3conf',
            'typo3temp',
        ]);

        if ($item === '.' || $item === '..') {
            return true;
        }

        foreach ($allowedFiles as $pattern) {
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
