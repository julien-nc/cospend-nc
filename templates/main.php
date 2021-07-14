<?php
$appId = OCA\Cospend\AppInfo\Application::APP_ID;
script('cospend', $appId . '-main');
?>

<p id="projectid"><?php p($_['projectid']); ?></p>
<p id="password"><?php p($_['password']); ?></p>
