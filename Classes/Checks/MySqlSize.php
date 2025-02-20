<?php

namespace Devskio\Typo3OhDearHealthCheck\Checks;

use OhDear\HealthCheckResults\CheckResult;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Class MySqlSize
 * @package Devskio\Typo3OhDearHealthCheck\Checks
 */
class MySqlSize extends AbstractCheck
{
    /**
     * Run the health check.
     *
     * @return CheckResult The result of the health check.
     */
    public function run(): CheckResult
    {
        $databaseConfigurations = $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections'];
        $identifier = self::getIdentifier();

        if ($this->configuration['databaseSizeWarningCustomCheckEnabled']) {
            foreach ($databaseConfigurations as $databaseConfiguration => $databaseConfig) {
                $biggestTables = [];
                $sizeInBytes = 0;

                try {
                    $sizeInBytes = $this->getDatabaseSize($databaseConfiguration, $databaseConfig['dbname']);

                    $status = $this->determineStatus(
                        $sizeInBytes,
                        $this->configuration['databaseSizeWarningThresholdError'] * $this->toBytesModifier,
                        $this->configuration['databaseSizeWarningThresholdWarning'] * $this->toBytesModifier
                    );

                    $biggestTables = $this->getBiggestTables($databaseConfiguration, $databaseConfig['dbname']);
                } catch (\Exception $e) {
                    $status = CheckResult::STATUS_CRASHED;
                } finally {
                    return new CheckResult(
                        $identifier . $databaseConfiguration,
                        LocalizationUtility::translate("check.{$identifier}.label", 'typo3_ohdear_health_check', [$databaseConfig['dbname']]),
                        LocalizationUtility::translate("check.{$identifier}.notificationMessage", 'typo3_ohdear_health_check', [$this->formatBytes($sizeInBytes)]),
                        LocalizationUtility::translate("check.{$identifier}.shortSummary", 'typo3_ohdear_health_check', [$this->formatBytes($sizeInBytes)]),
                        $status,
                        ["biggest_tables" => $biggestTables]
                    );
                }
            }
        }

        return new CheckResult(
            $identifier,
            LocalizationUtility::translate("check.{$identifier}.label", 'typo3_ohdear_health_check'),
            LocalizationUtility::translate("check.{$identifier}.notificationMessage", 'typo3_ohdear_health_check', [0]),
            LocalizationUtility::translate("check.{$identifier}.shortSummary", 'typo3_ohdear_health_check', [0]),
            CheckResult::STATUS_SKIPPED,
            []
        );
    }

    /**
     * Get the size of the given database.
     *
     * @param string $connectionName The name of the database connection.
     * @param string $databaseName The name of the database.
     * @return int The size of the database in bytes.
     */
    private function getDatabaseSize(string $connectionName, string $databaseName): int
    {
        $queryBuilder = $this->createQueryBuilder($connectionName);

        $query = $queryBuilder
            ->selectLiteral('SUM(data_length + index_length) AS size')
            ->from('information_schema.tables')
            ->where(
                $queryBuilder->expr()->eq('table_schema', $queryBuilder->createNamedParameter($databaseName))
            )
            ->groupBy('table_schema');

        $result = $query->execute();
        $databaseSize = $result->fetchAssociative();

        return $databaseSize ? (int)$databaseSize['size'] : 0;
    }

    /**
     * Get the five biggest tables in the given database.
     *
     * @param string $connectionName The name of the database connection.
     * @param string $databaseName The name of the database.
     * @return array An array of the biggest tables and their sizes.
     */
    private function getBiggestTables(string $connectionName, string $databaseName): array
    {
        $queryBuilder = $this->createQueryBuilder($connectionName);
        $query = $this->createBiggestTablesQuery($queryBuilder, $databaseName);

        $result = $query->execute()->fetchAllAssociative();
        $biggestTables = [];
        foreach ($result as $row) {
            $biggestTables[$row['Table']] = $row['Size (MB)'] . ' MB';
        }

        return $biggestTables;
    }

    /**
     * Create a query to get the five biggest tables in the given database.
     *
     * @param QueryBuilder $queryBuilder The QueryBuilder instance.
     * @param string $databaseName The name of the database.
     * @return QueryBuilder The created QueryBuilder instance.
     */
    private function createBiggestTablesQuery(QueryBuilder $queryBuilder, string $databaseName): QueryBuilder
    {
        return $queryBuilder
            ->selectLiteral('table_name AS "Table"')
            ->addSelectLiteral('ROUND(SUM(data_length + index_length) / (1024 * 1024), 2) AS "Size (MB)"')
            ->from('information_schema.tables')
            ->where(
                $queryBuilder->expr()->eq('table_schema', $queryBuilder->createNamedParameter($databaseName))
            )
            ->groupBy('table_name')
            ->orderBy('Size (MB)', 'DESC')
            ->setMaxResults(5);
    }

    /**
     * Create a QueryBuilder instance for the given database.
     *
     * @param string $databaseName The name of the database.
     * @return QueryBuilder The created QueryBuilder instance.
     */
    private function createQueryBuilder(string $databaseName): QueryBuilder
    {
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        $databaseConnection = $connectionPool->getConnectionByName($databaseName);
        return $databaseConnection->createQueryBuilder();
    }

    /**
     * Default configuration for this check.
     *
     * @return array
     */
    public function getDefaultConfiguration(): array
    {
        return [
            'databaseSizeWarningCustomCheckEnabled' => true,
            'databaseSizeWarningThresholdError' => 500,
            'databaseSizeWarningThresholdWarning' => 50,
        ];
    }
}
