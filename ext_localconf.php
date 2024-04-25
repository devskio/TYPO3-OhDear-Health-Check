<?php

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3_MODE') or die('Access denied.');

call_user_func(function ($extKey) {
    if (!isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations'][$extKey]) ||
        !is_array($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations'][$extKey])) {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations'][$extKey] = [];
    }

    // register check classes
    $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['typo3_ohdear_health_check']['checks'] = array_merge(
        [
            \Devskio\Typo3OhDearHealthCheck\Checks\DiskUsedSpace::class,
            \Devskio\Typo3OhDearHealthCheck\Checks\ForgottenFiles::class,
            \Devskio\Typo3OhDearHealthCheck\Checks\MySqlSize::class,
            \Devskio\Typo3OhDearHealthCheck\Checks\PhpErrorLogSize::class,
            \Devskio\Typo3OhDearHealthCheck\Checks\Typo3DatabaseLog::class,
            \Devskio\Typo3OhDearHealthCheck\Checks\Typo3Version::class,
            \Devskio\Typo3OhDearHealthCheck\Checks\VarFolderSize::class,
        ],
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['typo3_ohdear_health_check']['checks'] ?? []
    );

}, 'typo3_ohdear_health_check');
