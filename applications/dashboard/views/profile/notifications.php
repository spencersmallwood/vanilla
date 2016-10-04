<?php if (!defined('APPLICATION')) exit();

echo '<div class="DataListWrap">';
echo '<h2 class="H">'.t('Notifications').'</h2>';

if (count($this->data('Activities'))) {
    echo '<ul class="DataList Activities Notifications">';
    include($this->fetchViewLocation('activities', 'activity', 'dashboard'));
    echo '</ul>';
    echo PagerModule::write(array('CurrentRecords' => count($this->data('Activities'))));
} else {
    ?>
    <div class="Empty"><?php echo t('Notifications will appear here.', sprintf(t('You do not have any %s yet.'), t('notifications'))); ?></div>
<?php
}
echo '</div>';
