<?php
// todo ;-)
?>
<div class="app-navigation-new">
    <button id="newprojectbutton" type="button" class="icon-triangle-e">
        New project
    </button>
    <div id="newprojectdiv">

        <label for="projectidinput"><?php p($l->t('project id')); ?></label>
        <input id="projectidinput" type="text" value="id"/>

        <label for="projectnameinput"><?php p($l->t('project name')); ?></label>
        <input id="projectnameinput" type="text" value="name"/>

        <label for="projectpasswordinput"><?php p($l->t('project password')); ?></label>
        <input id="projectpasswordinput" type="password" value="lala"/>
        <button id="createproject" type="button" class="icon-add">
            Add project
        </button>

    </div>

</div>
<ul id="projectlist">
</ul>
