<?php
script('cospend', 'emojionearea.min');
script('cospend', 'cospend');

style('cospend', 'cospend');
style('cospend', 'Chart.min');
style('cospend', 'emojionearea.min');

?>

<div id="app">
    <div id="app-navigation">
            <?php print_unescaped($this->inc('mainnavigation')); ?>
    </div>
    <div id="app-content">
            <?php print_unescaped($this->inc('maincontent')); ?>
    </div>
</div>
