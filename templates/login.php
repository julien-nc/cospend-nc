<?php
script('cospend', 'login');

//style('cospend', 'style');
style('cospend', 'login');

?>

<div id="app">
    <div id="app-content">
            <?php print_unescaped($this->inc('logincontent')); ?>
    </div>
</div>
