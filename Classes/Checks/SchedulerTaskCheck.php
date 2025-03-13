<?php

namespace Devskio\Typo3OhDearHealthCheck\Checks;

use OhDear\HealthCheckResults\CheckResult;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Class SchedulerTaskCheck
 * @package Devskio\Typo3OhDearHealthCheck\Checks
 */
class SchedulerTaskCheck extends AbstractCheck
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
            $problematicTasks = $this->getOverdueTasks();
            $numRecords = count($problematicTasks);
            $failedTasks = '';
            $failedTasksNum = 0;
            $overdueTasks = '';
            $overdueTasksNum = 0;
            foreach ($problematicTasks as $task)
            {
                if($task['lastexecution_failure'] != null){
                    $failedTasks .= $task['uid'] . ',';
                    $failedTasksNum++;
                }
                else
                {
                    $overdueTasks .= $task['uid'] . ',';
                    $overdueTasksNum++;
                }
            }
            $failedTasks = rtrim($failedTasks, ',');
            $overdueTasks = rtrim($overdueTasks, ',');
            $message = LocalizationUtility::translate("check.{$identifier}.notificationMessage", 'typo3_ohdear_health_check', [$overdueTasksNum,$failedTasksNum]);
            $status = ($numRecords > 0) ? CheckResult::STATUS_FAILED : CheckResult::STATUS_OK;
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
            ['num_problematic_task' => $numRecords, 'failed_task' => $failedTasks, 'overdue_tasks' => $overdueTasks]
        );
    }

    /**
     * Get overdue tasks that haven't run in the expected time
     *
     * @return array
     */
    protected function getOverdueTasks(): array
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('tx_scheduler_task');

        $queryBuilder = $connection->createQueryBuilder();
        $thresholdTime = time() - $this->convertToSeconds($this->configuration['SchedulerTaskCheckWarningCustomCheckOverdueTreshHold']); // Define overdue threshold (e.g., 1 hour)

        return $queryBuilder
            ->select('uid', 'lastexecution_time', 'nextexecution', 'lastexecution_failure', 'disable')
            ->from('tx_scheduler_task')
            ->where(
                $queryBuilder->expr()->orX(
                    $queryBuilder->expr()->notLike('lastexecution_failure', '""'),
                    $queryBuilder->expr()->lt('nextexecution', $thresholdTime)
                )
            )
            ->executeQuery()
            ->fetchAllAssociative();
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
            'SchedulerTaskCheckWarningCustomCheckEnabled' => true,
            'SchedulerTaskCheckWarningCustomCheckOverdueTreshHold' => '24H',
        ];
    }

}
