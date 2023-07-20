<?php
namespace Devskio\Typo3OhDearHealthCheck\Traits\Injection;

use Devskio\Typo3OhDearHealthCheck\Service\OhDearHealthCheckService;

/**
 * Trait OhDearHealthCheckService
 * @package Devskio\Typo3OhDearHealthCheck\Traits\Injection
 */
trait InjectOhDearHealthCheckService
{
    /**
     * @var OhDearHealthCheckService
     */
    protected $ohDearHealthCheckService;

    /**
     * @param OhDearHealthCheckService $ohDearHealthCheckService
     */
    public function injectOhDearHealthCheckService(OhDearHealthCheckService $ohDearHealthCheckService)
    {
        $this->ohDearHealthCheckService = $ohDearHealthCheckService;
    }
}
