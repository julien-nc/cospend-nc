<?php
// todo ;-)
?>
<form id="loginform" method="POST">
<label for="projectid" id="projectidlabel">Lala</label>
<input id="projectid" name="projectid" type="text" value="<?php 
if (array_key_exists('projectid', $_)) {
    p($_['projectid']);
}
?>"/>

<label for="projectpassword" id="passwordlabel">Password</label>
<input id="password" name="password" type="password" value=""/>

<button id="okbutton" type="submit">Ok</button>

</form>
