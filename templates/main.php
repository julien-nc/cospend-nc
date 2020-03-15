<?php
script('cospend', '../node_modules/kjua/dist/kjua.min');
script('cospend', '../node_modules/sorttable/sorttable');
script('cospend', '../node_modules/chart.js/dist/Chart.min');
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
