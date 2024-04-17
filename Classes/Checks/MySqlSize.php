<?php

namespace Devskio\Typo3OhDearHealthCheck\Checks;

use OhDear\HealthCheckResults\CheckResult;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class MySqlSize
 * @package Devskio\Typo3OhDearHealthCheck\Checks
 */
class MySqlSize extends AbstractCheck
{

    /**
     * The identifier of the check.
     *
     * @var string
     */
    const IDENTIFIER = 'mysqlSize';

    /**
     * MySqlSize constructor.
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
        $databaseConfigurations = $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections'];

        foreach ($databaseConfigurations as $databaseName => $databaseConfig) {
            try {
                $sizeInBytes = $this->getDatabaseSize($databaseName, $databaseConfig['dbname']);
                $sizeInMB = round($sizeInBytes / (1024 * 1024), 2);

                $status = $this->determineStatus(
                    $sizeInBytes,
                    $this->configuration['databaseSizeWarningThresholdError'],
                    $this->configuration['databaseSizeWarningThresholdWarning']
                );

                $biggestTables = $this->getBiggestTables($databaseName, $databaseConfig['dbname']);

                return $this->createHealthCheckResult(
                    'MysqlSize' . $databaseName,
                    'Mysql Size (' . $databaseConfig['dbname'] . ')',
                    $status,
                    sprintf('Database size: %s MB', $sizeInMB),
                    sprintf('%s MB', $sizeInMB),
                    ["biggest_tables" => $biggestTables]
                );
            } catch (\Exception $e) {
                $status = CheckResult::STATUS_SKIPPED;
            }
        }

        return $this->createHealthCheckResult(
            'MysqlSize' . $databaseName,
            'Mysql Size (' . $databaseConfig['dbname'] . ')',
            $status,
            'Database size: ' . $sizeInMB . ' MB',
            $sizeInMB . ' MB',
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
            'databaseSizeWarningThresholdError' => 5000000000,
            'databaseSizeWarningThresholdWarning' => 500000000,
        ];
    }
}
