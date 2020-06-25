<?php
/**
 * @package     Organizer
 * @extension   com_organizer
 * @author      James Antrim, <james.antrim@nm.thm.de>
 * @copyright   2020 TH Mittelhessen
 * @license     GNU GPL v.3
 * @link        www.thm.de
 */

namespace Organizer\Views\HTML;

use Joomla\CMS\Factory;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Uri\Uri;
use Organizer\Helpers;

/**
 * Class loads the (subject) pool form into display context.
 */
class PoolEdit extends EditView
{
	use Subordinate;

	protected $_layout = 'tabs';

	/**
	 * Method to generate buttons for user interaction
	 *
	 * @return void
	 */
	protected function addToolBar()
	{
		if ($this->item->id)
		{
			$apply  = 'ORGANIZER_APPLY';
			$cancel = 'ORGANIZER_CLOSE';
			$save   = 'ORGANIZER_SAVE';
			$title  = "ORGANIZER_POOL_EDIT";
		}
		else
		{
			$apply  = 'ORGANIZER_CREATE';
			$cancel = 'ORGANIZER_CANCEL';
			$save   = 'ORGANIZER_CREATE';
			$title  = "ORGANIZER_POOL_NEW";
		}

		Helpers\HTML::setTitle(Helpers\Languages::_($title), 'list-2');
		$toolbar = Toolbar::getInstance();
		$toolbar->appendButton('Standard', 'apply', Helpers\Languages::_($apply), 'pools.apply', false);
		$toolbar->appendButton('Standard', 'save', Helpers\Languages::_($save), 'pools.save', false);

		$baseURL = 'index.php?option=com_organizer&tmpl=component';
		$baseURL .= "&type=pool&id={$this->item->id}&view=";

		$poolLink = $baseURL . 'pool_selection';
		$toolbar->appendButton('Popup', 'list', Helpers\Languages::_('ORGANIZER_ADD_POOL'), $poolLink);

		$subjectLink = $baseURL . 'subject_selection';
		$toolbar->appendButton('Popup', 'book', Helpers\Languages::_('ORGANIZER_ADD_SUBJECT'), $subjectLink);

		$toolbar->appendButton('Standard', 'cancel', Helpers\Languages::_($cancel), 'pools.cancel', false);
	}
}
