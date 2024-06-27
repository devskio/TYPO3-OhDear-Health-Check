<?php

namespace Devskio\Typo3OhDearHealthCheck\Checks;

use OhDear\HealthCheckResults\CheckResult;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Class Typo3DatabaseLog
 * @package Devskio\Typo3OhDearHealthCheck\Checks
 */
class Typo3DatabaseLog extends AbstractCheck
{
    /**
     * Run the health check.
     *
     * @return CheckResult The result of the health check.
     */
    public function run(): CheckResult
    {
        $identifier = self::getIdentifier();
        $numRecords = 0;

        try {
            $numRecords = $this->getNumRecords();

            $message = LocalizationUtility::translate("check.{$identifier}.notificationMessage", 'typo3_ohdear_health_check', [$numRecords]);
            $status = ($numRecords > 500) ? CheckResult::STATUS_FAILED : CheckResult::STATUS_OK;
        } catch (\Exception $e) {
            $message = LocalizationUtility::translate("check.{$identifier}.notificationMessage.error", 'typo3_ohdear_health_check', [$e->getMessage()]);
            $status = CheckResult::STATUS_CRASHED;
        }

        return new CheckResult(
            $identifier,
            LocalizationUtility::translate("check.{$identifier}.label", 'typo3_ohdear_health_check'),
            $message,
            LocalizationUtility::translate("check.{$identifier}.shortSummary", 'typo3_ohdear_health_check', [$status]),
            $status,
            ['num_records' => $numRecords]
        );
    }

    /**
     * Get the number of error log records in the last month.
     *
     * @return int The number of records.
     */
    private function getNumRecords(): int
    {
        $queryBuilder = $this->createQueryBuilder();
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

    /**
     * Default configuration for this check.
     *
     * @return array
     */
    public function getDefaultConfiguration(): array
    {
        return [
            'errorLogSizeWarningCustomCheckEnabled' => true,
            'errorLogSizeWarningThresholdError' => 10000000,
            'errorLogSizeWarningThresholdWarning' => 5000000,
        ];
    }

}
