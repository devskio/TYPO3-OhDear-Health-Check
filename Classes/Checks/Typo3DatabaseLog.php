<?php

namespace Devskio\Typo3OhDearHealthCheck\Checks;

use OhDear\HealthCheckResults\CheckResult;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\DebuggerUtility;

/**
 * Class Typo3DatabaseLog
 * @package Devskio\Typo3OhDearHealthCheck\Checks
 */
class Typo3DatabaseLog extends AbstractCheck
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
        $credentials = $this->getMysqlCredentials();

        if ($credentials !== null) {
            try {
                $queryBuilder = $this->createQueryBuilder();
                $numRecords = $this->getNumRecords($queryBuilder);

                $status = ($numRecords > 500) ? CheckResult::STATUS_FAILED : CheckResult::STATUS_OK;

                return $this->createHealthCheckResult(
                    'TYPO3DBLog',
                    'TYPO3 Database Error Log',
                    $status,
                    sprintf('Found %d error log records in the last month', $numRecords),
                    $status,
                    ['num_records' => $numRecords]
                );
            } catch (\Exception $e) {
                return $this->createHealthCheckResult(
                    'TYPO3DBLog',
                    'TYPO3 Database Error Log',
                    CheckResult::STATUS_CRASHED,
                    sprintf('Error executing the database query: %s', $e->getMessage()),
                    'CRASHED',
                    []
                );
            }
        } else {
            return $this->createHealthCheckResult(
                'TYPO3DBLog',
                'TYPO3 Database Error Log',
                CheckResult::STATUS_SKIPPED,
                'MySQL credentials not available',
                'SKIPPED',
                []
            );
        }
    }

    /**
     * Get the number of error log records in the last month.
     *
     * @param QueryBuilder $queryBuilder The QueryBuilder instance.
     * @return int The number of records.
     */
    private function getNumRecords(QueryBuilder $queryBuilder): int
    {
        $oneMonthAgo = strtotime('-1 month');
        $query = $queryBuilder
            ->selectLiteral('COUNT(*) AS num_records')
            ->from('sys_log')
            ->where(
                $queryBuilder->expr()->eq('error', $queryBuilder->createNamedParameter(2))
            )
            ->andWhere(
                $queryBuilder->expr()->gte('tstamp', $queryBuilder->createNamedParameter($oneMonthAgo))
            );

        $result = $query->execute();

        return $result->fetchOne();
    }

    /**
     * Function to get MySQL credentials from TYPO3 LocalConfiguration.php file.
     *
     * @return array|null Returns an array containing MySQL credentials if available, otherwise null.
     */
    public function getMysqlCredentials(): ?array
    {
        $localConfigurationPath = GeneralUtility::getFileAbsFileName('typo3conf/LocalConfiguration.php');
        $localConfiguration = include($localConfigurationPath);
        $credentials = $localConfiguration['DB']['Connections']['Default'] ?? null;

        return $credentials;
    }

    /**
     * Create a QueryBuilder instance.
     *
     * @return QueryBuilder The created QueryBuilder instance.
     */
    private function createQueryBuilder(): QueryBuilder
    {
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        $databaseConnection = $connectionPool->getConnectionByName('Default');

        return $databaseConnection->createQueryBuilder();
    }

}
