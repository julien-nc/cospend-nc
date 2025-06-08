<?php

require_once __DIR__ . '/../../../tests/bootstrap.php';

use OCA\Cospend\AppInfo\Application;
use OCP\App\IAppManager;

\OC::$server->get(IAppManager::class)->loadApp(Application::APP_ID);
OC_Hook::clear();
