<?php
script('cospend', 'cospend');

style('cospend', 'cospend');
style('cospend', '../node_modules/chart.js/dist/Chart');

?>

<div id="app">
    <div id="app-navigation">
            <?php print_unescaped($this->inc('mainnavigation')); ?>
    </div>
    <div id="app-content">
            <?php print_unescaped($this->inc('maincontent')); ?>
    </div>
</div>
