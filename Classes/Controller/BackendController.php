<?php
namespace Devskio\Typo3OhDearHealthCheck\Controller;

use DateTime;
use Devskio\Typo3OhDearHealthCheck\Service\OhDearHealthCheckService;
use Devskio\Typo3OhDearHealthCheck\Traits\Injection\InjectOhDearHealthCheckService;
use OhDear\HealthCheckResults\CheckResults;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

/**
 * Class OhDearHealthCheck
 * @package Devskio\Typo3OhDearHealthCheck\Controller
 */
class BackendController extends ActionController
{
    use InjectOhDearHealthCheckService;

    /**
     * @var OhDearHealthCheckService
     */
    protected $healthCheckService;

    public function __construct(OhDearHealthCheckService $healthCheckService)
    {
        $this->healthCheckService = $healthCheckService;;
    }

    /**
     * Action to perform the health check and return the data as JSON.
     */
    public function runAction(): void
    {
        $checkResults = new CheckResults(DateTime::createFromFormat('Y-m-d H:i:s', date('Y-m-d H:i:s')));

        $checkResults->addCheckResult($this->healthCheckService->getUsedDiskSpace());
        $checkResults->addCheckResult($this->healthCheckService->checkPHPErrorLogSize());
        $checkResults->addCheckResult($this->healthCheckService->getTYPO3ErrorLogSize());
        $checkResults->addCheckResult($this->healthCheckService->getMysqlSize());
        $checkResults->addCheckResult($this->healthCheckService->scanDocumentRootForForgottenFiles());
        $checkResults->addCheckResult($this->healthCheckService->getTYPO3DBLog());
        $checkResults->addCheckResult($this->healthCheckService->getTYPO3Version());

        echo $checkResults->toJson();
    }

}
