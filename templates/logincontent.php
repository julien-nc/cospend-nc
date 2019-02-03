<?php
// todo ;-)
?>
<form id="loginform" method="POST">
<h2><?php p($l->t('Authentication')); ?></h2>

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
<input id="passwordInput" name="password" type="password" value=""/>

<br/>
<button id="okbutton" type="submit">Submit</button>

</form>
