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
 * Class loads persistent information about a subject into the display context.
 */
class SubjectEdit extends EditView
{
	protected $_layout = 'tabs';

	/**
	 * Method to generate buttons for user interaction
	 *
	 * @return void
	 */
	protected function addToolBar()
	{
		$new   = empty($this->item->id);
		$title = $new ? Helpers\Languages::_('ORGANIZER_SUBJECT_NEW') : Helpers\Languages::_('ORGANIZER_SUBJECT_EDIT');
		Helpers\HTML::setTitle($title, 'book');
		$toolbar   = Toolbar::getInstance();
		$applyText = $new ? Helpers\Languages::_('ORGANIZER_CREATE') : Helpers\Languages::_('ORGANIZER_APPLY');
		$toolbar->appendButton('Standard', 'apply', $applyText, 'subjects.apply', false);
		$toolbar->appendButton('Standard', 'save', Helpers\Languages::_('ORGANIZER_SAVE'), 'subjects.save', false);
		$toolbar->appendButton(
			'Standard',
			'save-new',
			Helpers\Languages::_('ORGANIZER_SAVE2NEW'),
			'subjects.save2new',
			false
		);
		$cancelText = $new ? Helpers\Languages::_('ORGANIZER_CANCEL') : Helpers\Languages::_('ORGANIZER_CLOSE');
		$toolbar->appendButton('Standard', 'cancel', $cancelText, 'subjects.cancel', false);
	}

	/**
	 * Adds styles and scripts to the document
	 *
	 * @return void  modifies the document
	 */
	protected function modifyDocument()
	{
		parent::modifyDocument();

		Factory::getDocument()->addStyleSheet(Uri::root() . 'components/com_organizer/css/curriculum_settings.css');
	}
}
