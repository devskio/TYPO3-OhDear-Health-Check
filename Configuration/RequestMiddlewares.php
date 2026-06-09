<?php
declare(strict_types=1);

return [
    'frontend' => [
        'devskio/typo3-ohdear-health-check/health-check' => [
            'target' => \Devskio\Typo3OhDearHealthCheck\Middleware\HealthCheckMiddleware::class,
            'before' => [
                'typo3/cms-frontend/page-resolver',
            ],
            'after' => [
                'typo3/cms-frontend/site-resolver',
            ],
        ],
    ],
];

