<?php
$appId = OCA\Cospend\AppInfo\Application::APP_ID;
\OCP\Util::addScript($appId, $appId . '-login');
\OCP\Util::addStyle($appId, 'login');
?>

<div id="app">
	<div id="app-content">
			<?php print_unescaped($this->inc('logincontent')); ?>
	</div>
</div>
