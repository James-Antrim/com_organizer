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

use Joomla\CMS\Toolbar\Toolbar;
use Organizer\Helpers;
use Organizer\Helpers\Languages; // Exception for frequency of use

/**
 * Class loads persistent information a filtered set of persons into the display context.
 */
class Persons extends ListView
{
	protected $rowStructure = [
		'checkbox'       => '',
		'surname'        => 'link',
		'forename'       => 'link',
		'username'       => 'link',
		'code'           => 'link',
		'organizationID' => 'link'
	];

	/**
	 * Method to generate buttons for user interaction
	 *
	 * @return void
	 */
	protected function addToolBar()
	{
		Helpers\HTML::setTitle(Languages::_('ORGANIZER_TEACHERS'), 'users');
		$toolbar = Toolbar::getInstance();
		$toolbar->appendButton('Standard', 'new', Languages::_('ORGANIZER_ADD'), 'persons.add', false);
		$toolbar->appendButton('Standard', 'edit', Languages::_('ORGANIZER_EDIT'), 'persons.edit', true);

		if (Helpers\Can::administrate())
		{
			$toolbar->appendButton(
				'Confirm',
				Helpers\Languages::_('ORGANIZER_DELETE_CONFIRM'),
				'delete',
				Helpers\Languages::_('ORGANIZER_DELETE'),
				"persons.delete",
				true
			);

			/*$toolbar->appendButton(
				'Standard',
				'attachment',
				Languages::_('ORGANIZER_MERGE'),
				'persons.mergeView',
				true
			);*/
		}
	}

	/**
	 * Function determines whether the user may access the view.
	 *
	 * @return bool true if the use may access the view, otherwise false
	 */
	protected function allowAccess()
	{
		return Helpers\Can::manage('persons');
	}

	/**
	 * Function to set the object's headers property
	 *
	 * @return void sets the object headers property
	 */
	public function setHeaders()
	{
		$headers = [
			'checkbox'       => '',
			'surname'        => Languages::_('ORGANIZER_SURNAME'),
			'forename'       => Languages::_('ORGANIZER_FORENAME'),
			'username'       => Languages::_('ORGANIZER_USERNAME'),
			't.code'         => Languages::_('ORGANIZER_UNTIS_ID'),
			'organizationID' => Languages::_('ORGANIZER_ORGANIZATION')
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

		foreach ($this->items as $item)
		{
			$item->forename = empty($item->forename) ? '' : $item->forename;
			$item->username = empty($item->username) ? '' : $item->username;
			$item->code     = empty($item->code) ? '' : $item->code;

			if (!$organizations = Helpers\Persons::getOrganizationNames($item->id))
			{
				$item->organizationID = Languages::_('JNONE');
			}
			elseif (count($organizations) === 1)
			{
				$item->organizationID = $organizations[0];
			}
			else
			{
				$item->organizationID = Languages::_('ORGANIZER_MULTIPLE_ORGANIZATIONS');
			}

			$structuredItems[$index] = $this->structureItem($index, $item, $item->link);
			$index++;
		}

		$this->items = $structuredItems;
	}
}
