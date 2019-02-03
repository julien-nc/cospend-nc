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
            <?php p($l->t('Add project')); ?>
        </button>

    </div>
    <button id="newBillButton" type="button" class="icon-add">
        <?php p($l->t('New bill')); ?>
    </button>

    <div id="newmemberdiv">
        <hr/>
        <label for="newmembername"><?php p($l->t('member name')); ?></label>
        <input id="newmembername" type="text" value=""/>
        <button id="newmemberbutton" projectid="" type="button" class="icon-add">
        </button>
    </div>

</div>

<ul id="projectlist">
</ul>

<div id="app-settings">
    <div id="app-settings-header">
        <button class="settings-button"
                data-apps-slide-toggle="#app-settings-content">
            <?php p($l->t('Settings')); ?>
        </button>
    </div>
    <div id="app-settings-content">
        <!-- Your settings content here -->
        <button id="statsButton" type="button">
            <?php p($l->t('Project statistics')); ?>
        </button>
        <button id="settleButton" type="button">
            <?php p($l->t('Settle')); ?>
        </button>
        <button id="generalGuestLinkButton" type="button">
            <?php p($l->t('Guest access link')); ?>
        </button>
    </div>
</div>
<p id="projectid"><?php p($_['projectid']); ?></p>
<p id="password"><?php p($_['password']); ?></p>
