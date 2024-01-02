<?php
$appId = OCA\Cospend\AppInfo\Application::APP_ID;
\OCP\Util::addScript($appId, $appId . '-sharePassword');
\OCP\Util::addStyle($appId, 'login');
?>

<div id="app">
	<div id="app-content">
		<form id="loginform" method="POST">
			<h2><?php p($l->t('Authentication')); ?></h2>

			<?php
			if ($_['wrong']) {
				echo '<p id="wrongcredentials">';
				p($l->t('Wrong link password'));
				echo '</p>';
			}
			?>

			<input id="tokenInput" name="token" type="hidden" maxlength="64" value="<?php
			if (isset($_['token'])) {
				p($_['token']);
			}
			?>"/>

			<br/>
			<label for="passwordInput" id="passwordlabel"><?php p($l->t('Share link password')); ?></label>
			<br/>
			<input id="passwordInput" name="password" type="password" value="<?php
			if (array_key_exists('password', $_)) {
				p($_['password']);
			}
			?>"/>

			<br/>
			<button id="okbutton" type="submit">Submit</button>

		</form>
	</div>
</div>
