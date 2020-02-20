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
 * Class loads subject information into the display context.
 */
class SubjectSelection extends ListView
{
	protected $_layout = 'list_modal';

	/**
	 * Method to generate buttons for user interaction
	 *
	 * @return void
	 */
	protected function addToolBar()
	{
		$toolbar = Toolbar::getInstance();
		$toolbar->appendButton('Standard', 'new', Helpers\Languages::_('ORGANIZER_ADD'), 'pools.addSubject', true);
	}

	/**
	 * Function determines whether the user may access the view.
	 *
	 * @return bool true if the use may access the view, otherwise false
	 */
	protected function allowAccess()
	{
		return (bool) Helpers\Can::documentTheseOrganizations();
	}

	/**
	 * Modifies document variables and adds links to external files
	 *
	 * @return void
	 */
	protected function modifyDocument()
	{
		parent::modifyDocument();

		Factory::getDocument()->addStyleSheet(Uri::root() . 'components/com_organizer/css/modal.css');
	}

	/**
	 * Function to set the object's headers property
	 *
	 * @return void sets the object headers property
	 */
	protected function setHeaders()
	{
		$direction = $this->state->get('list.direction');
		$ordering  = $this->state->get('list.ordering');
		$headers   = [
			'checkbox' => Helpers\HTML::_('grid.checkall'),
			'name'     => Helpers\HTML::sort('NAME', 'name', $direction, $ordering),
			'program'  => Helpers\Languages::_('ORGANIZER_PROGRAMS')
		];

		$this->headers = $headers;
	}

	/**
	 * Processes the items in a manner specific to the view, so that a generalized  output in the layout can occur.
	 *
	 * @return void processes the class items property
	 */
	protected function structureItems()
	{
		$index           = 0;
		$structuredItems = [];

		foreach ($this->items as $subject)
		{
			if (!Helpers\Can::document('subject', $subject->id))
			{
				continue;
			}

			$name = $subject->name;
			$name .= empty($subject->code) ? '' : " - $subject->code";

			$structuredItems[$index]             = [];
			$structuredItems[$index]['checkbox'] = Helpers\HTML::_('grid.id', $index, $subject->id);
			$structuredItems[$index]['name']     = $name;
			$structuredItems[$index]['programs'] = $name;

			$index++;
		}

		$this->items = $structuredItems;
	}
}
