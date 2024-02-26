<?php
namespace Devskio\Typo3OhDearHealthCheck\Events;

use OhDear\HealthCheckResults\CheckResults;
use Psr\EventDispatcher\StoppableEventInterface;

class CustomHealthCheckEvent implements StoppableEventInterface
{
    private CheckResults $checkResults;
    private bool $isPropagationStopped = false;

    public function __construct(CheckResults $checkResults)
    {
        $this->checkResults = $checkResults;
    }

    public function getCheckResults(): CheckResults
    {
        return $this->checkResults;
    }

    public function isPropagationStopped(): bool
    {
        return $this->isPropagationStopped;
    }

    public function stopPropagation(): void
    {
        $this->isPropagationStopped = true;
    }
}
