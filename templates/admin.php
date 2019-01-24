<?php
OCP\Util::addscript('spend', 'admin');
OCP\Util::addstyle('spend', 'admin');
?>

<div class="section" id="spend">
    <h2><?php p($l->t('Spend')); ?></h2>
    <h3><?php p($l->t('Allow anonymous project creation')); ?> </h3>
    <label><?php p($l->t('Enabling this option will allow any visitor to create a project without having to log in.')); ?></label><br/>
    <label><?php p($l->t('This can be useful if you are in a local network and want to let anybody create a project.')); ?></label><br/>
    <label><?php p($l->t('Or maybe you are very generous and want to allow the world to use Spend without having an account on your Nextcloud instance.')); ?></label><br/>
    <br />
    <div id="spendinputs">
        <input id="allowAnonymousCreation" type="checkbox"
        <?php if ($_['allowAnonymousCreation'] !== 0) p('checked'); ?>
        />
        <label for="allowAnonymousCreation"><?php p($l->t('Allow anonymous project creation')); ?></label>
    </div>
</div>
