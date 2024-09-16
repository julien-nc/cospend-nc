<?php
$appId = OCA\Cospend\AppInfo\Application::APP_ID;
\OCP\Util::addScript($appId, $appId . '-adminSettings');
\OCP\Util::addStyle($appId, $appId . '-adminSettings');
?>

<div id="cospend_prefs"></div>
