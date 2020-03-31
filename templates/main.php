<?php
script('cospend', '../node_modules/emojionearea/dist/emojionearea.min');
script('cospend', 'cospend');

style('cospend', 'cospend');
style('cospend', '../node_modules/chart.js/dist/Chart');
style('cospend', '../node_modules/emojionearea/dist/emojionearea.min');

?>

<div id="app">
    <div id="app-navigation">
            <?php print_unescaped($this->inc('mainnavigation')); ?>
    </div>
    <div id="app-content">
            <?php print_unescaped($this->inc('maincontent')); ?>
    </div>
</div>
