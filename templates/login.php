<?php
$appId = OCA\Cospend\AppInfo\Application::APP_ID;
script('cospend', $appId . '-login');
style('cospend', 'login');
?>

<div id="app">
	<div id="app-content">
			<?php print_unescaped($this->inc('logincontent')); ?>
	</div>
</div>
