<form id="loginform" method="POST">
<h2><?php p($l->t('Authentication')); ?></h2>

<?php
if ($_['wrong']) {
    echo '<p id="wrongcredentials">';
    p($l->t('Wrong project Id or password'));
    echo '</p>';
}
?>

<label for="projectidInput" id="projectidlabel"><?php p($l->t('Project ID')); ?></label>
<br/>
<input id="projectidInput" name="projectid" type="text" value="<?php 
if (array_key_exists('projectid', $_)) {
    p($_['projectid']);
}
?>"/>

<br/>
<label for="passwordInput" id="passwordlabel"><?php p($l->t('Project password (aka Access code)')); ?></label>
<br/>
<input id="passwordInput" name="password" type="password" value="<?php 
if (array_key_exists('password', $_)) {
    p($_['password']);
}
?>"/>

<br/>
<button id="okbutton" type="submit">Submit</button>

</form>
