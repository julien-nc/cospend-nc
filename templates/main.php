<?php
script('payback', 'moment-timezone-with-data.min');
script('payback', 'kjua.min');
script('payback', 'payback');

//style('payback', 'style');
style('payback', 'fontawesome/css/all.min');
style('payback', 'payback');

?>

<div id="app">
    <div id="app-navigation">
            <?php print_unescaped($this->inc('mainnavigation')); ?>
    </div>
    <div id="app-content">
            <?php print_unescaped($this->inc('maincontent')); ?>
    </div>
</div>
