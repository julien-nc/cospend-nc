<?php
script('cospend', 'moment-timezone-with-data.min');
script('cospend', 'kjua.min');
script('cospend', 'sorttable');
script('cospend', 'Chart.min');
script('cospend', 'cospend');

//style('cospend', 'style');
style('cospend', 'fontawesome/css/all.min');
style('cospend', 'cospend');

?>

<div id="app">
    <div id="app-navigation">
            <?php print_unescaped($this->inc('mainnavigation')); ?>
    </div>
    <div id="app-content">
            <?php print_unescaped($this->inc('maincontent')); ?>
    </div>
</div>
