<?php
// todo ;-)
?>
<div class="app-navigation-new">
    <button id="newprojectbutton" type="button" class="icon-triangle-e">
        <?php p($l->t('New project')); ?>
    </button>
    <div id="newprojectdiv">

        <form id="newprojectform" autocomplete="off">
            <label for="projectidinput"><?php p($l->t('project id')); ?></label>
            <input id="projectidinput" type="text" value="<?php p($l->t('myProjectId')); ?>"/>

            <label for="projectnameinput"><?php p($l->t('name')); ?></label>
            <input id="projectnameinput" type="text" value="<?php p($l->t('My project name')); ?>"/>

            <label for="projectpasswordinput"><?php p($l->t('password (optional, required to connect with MoneyBuster)')); ?></label>
            <input id="projectpasswordinput" type="password" value="" autocomplete="off"/>
            <button id="createproject" type="button" class="icon-add">
                <?php p($l->t('Add project')); ?>
            </button>
        </form>

    </div>
    <button id="newBillButton" type="button" class="icon-add">
        <?php p($l->t('New bill')); ?>
    </button>

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
        <button id="addextprojectbutton" type="button" class="icon-triangle-e">
            <?php p($l->t('Add external project')); ?>
        </button>
        <div id="addextprojectdiv">

            <form id="addextprojectform" autocomplete="off">
                <label for="ncurlinput"><?php p($l->t('Nextcloud address')); ?></label>
                <input id="ncurlinput" type="text" value="<?php p($l->t('https://other.nextcloud.instance.net')); ?>"/>
                <br/>

                <label for="extprojectidinput"><?php p($l->t('project id')); ?></label>
                <input id="extprojectidinput" type="text" value="<?php p($l->t('myProjectId')); ?>"/>
                <br/>

                <label for="extprojectpasswordinput"><?php p($l->t('password')); ?></label>
                <input id="extprojectpasswordinput" type="password" value="" autocomplete="off"/>
                <button id="addextproject" type="button" class="icon-add">
                    <?php p($l->t('Add')); ?>
                </button>
            </form>

            <hr/>
        </div>
        <button id="importProjectButton" class="icon-download" >
            <?php p($l->t('Import csv project file')); ?>
        </button>
        <button id="importSWProjectButton" class="icon-download" >
            <?php p($l->t('Import SplitWise project file')); ?>
        </button>
        <button id="generalGuestLinkButton" class="icon-clippy" >
            <?php p($l->t('Guest access link')); ?>
        </button>
        <div id="set-output-div">
            <button id="changeOutputButton" class="icon-folder" >
                <?php p($l->t('Change output directory')); ?>
            </button>
            <label id="outputDirectory">/Cospend</label>
        </div>
    </div>
</div>
<p id="projectid"><?php p($_['projectid']); ?></p>
<p id="password"><?php p($_['password']); ?></p>
<img id="dummylogo"/>
<input id="membercolorinput" type="color"></input>
