<?php
OCP\Util::addscript('cospend', 'admin');
OCP\Util::addstyle('cospend', 'admin');
?>

<div class="section" id="cospend">
    <h2><?php p($l->t('Cospend')); ?></h2>
    <h3><?php p($l->t('Allow guests to create projects')); ?> </h3>
    <label><?php p($l->t('Enabling this option will allow any visitor to create a project without having to log in.')); ?></label><br/>
    <label><?php p($l->t('This can be useful if you are in a local network and want to let anybody create a project.')); ?></label><br/>
    <label><?php p($l->t('Or maybe you are very generous and want to allow the entire world to use Cospend without having an account on your Nextcloud instance.')); ?></label><br/>
    <br />
    <div id="cospendinputs">
        <input id="allowAnonymousCreation" type="checkbox"
        <?php if ($_['allowAnonymousCreation'] === '1') p('checked'); ?>
        />
        <label for="allowAnonymousCreation"><?php p($l->t('Allow guests to create projects')); ?></label>
    </div>
</div>
