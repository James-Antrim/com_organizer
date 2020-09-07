<?php
/**
 * @package     Organizer
 * @extension   com_organizer
 * @author      James Antrim, <james.antrim@nm.thm.de>
 * @copyright   2020 TH Mittelhessen
 * @license     GNU GPL v.3
 * @link        www.thm.de
 */

use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Uri\Uri;
use Organizer\Helpers;

$query = Uri::getInstance()->getQuery();

if (!$this->clientContext)
{
	echo Helpers\OrganizerHelper::getApplication()->JComponentTitle;
	echo $this->subtitle;
	echo $this->supplement;
}
?>
<div id="j-main-container" class="span10">
	<?php if (!$this->clientContext) : ?>
		<?php echo Toolbar::getInstance()->render(); ?>
	<?php endif; ?>
    <form action="<?php echo Uri::base() . "?$query"; ?>" id="adminForm" method="post" name="adminForm"
          class="form-horizontal form-validate" enctype="multipart/form-data">
		<?php echo $this->form->renderFieldset('details'); ?>
		<?php echo Helpers\HTML::_('form.token'); ?>
        <input type="hidden" name="option" value="com_organizer"/>
        <input type="hidden" name="task" value=""/>
        <input type="hidden" name="view" value="<?php echo $this->get('name'); ?>"/>
		<?php echo Helpers\HTML::_('form.token'); ?>
    </form>
</div>
