<?php

// Auto-sync .env credentials to MariaDB if .env is modified
$syncHelper = dirname(__DIR__) . '/public/legacy/custom/include/RadioCRM/AutoSyncEnvCredentials.php';
if (file_exists($syncHelper)) {
    require_once $syncHelper;
}

use App\Kernel;

require_once dirname(__DIR__) . '/vendor/autoload_runtime.php';

return function (array $context) {
    return new Kernel($context['APP_ENV'], (bool)$context['APP_DEBUG']);
};
