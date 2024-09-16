<?php

$appId = OCA\Cospend\AppInfo\Application::APP_ID;
\OCP\Util::addScript($appId, $appId . '-main');
\OCP\Util::addStyle($appId, $appId . '-main');
