<?php
$appId = OCA\Cospend\AppInfo\Application::APP_ID;
script('cospend', $appId . '-sharePassword');
style('cospend', 'login');
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

			<!--label for="projecttokenInput" id="projecttokenlabel"><?php p($l->t('Project share token')); ?></label>
			<br/-->
			<input id="projecttokenInput" name="projecttoken" type="hidden" maxlength="64" value="<?php
			if (array_key_exists('projecttoken', $_)) {
				p($_['projecttoken']);
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
