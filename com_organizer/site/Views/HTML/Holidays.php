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
use Organizer\Helpers\HTML; // Exception for frequency of use
use Organizer\Helpers\Languages; // Exception for frequency of use

/**
 * Class loads persistent information a filtered set of holidays into the display context.
 */
class Holidays extends ListView
{
	const OPTIONAL = 1, PARTIAL = 2, BLOCKING = 3;

	/**
	 * Method to generate buttons for user interaction
	 *
	 * @return void
	 */
	protected function addToolBar()
	{
		HTML::setTitle(Languages::_('ORGANIZER_HOLIDAYS'), 'calendar');
		$toolbar = Toolbar::getInstance();
		$toolbar->appendButton('Standard', 'new', Languages::_('ORGANIZER_ADD'), 'holidays.add', false);
		$toolbar->appendButton('Standard', 'edit', Languages::_('ORGANIZER_EDIT'), 'holidays.edit', true);
		$toolbar->appendButton(
			'Confirm',
			Languages::_('ORGANIZER_DELETE_CONFIRM'),
			'delete',
			Languages::_('ORGANIZER_DELETE'),
			'holidays.delete',
			true
		);
	}

	/**
	 * Function determines whether the user may access the view.
	 *
	 * @return bool true if the use may access the view, otherwise false
	 */
	protected function allowAccess()
	{
		return Helpers\Can::administrate();
	}

	/**
	 * Function to set the object's headers property
	 *
	 * @return void sets the object headers property
	 */
	public function setHeaders()
	{
		$ordering  = $this->state->get('list.ordering');
		$direction = $this->state->get('list.direction');
		$headers   = [
			'checkbox'  => '',
			'name'      => HTML::sort('NAME', 'name', $direction, $ordering),
			'startDate' => HTML::sort('DATE', 'startDate', $direction, $ordering),
			'type'      => HTML::sort('TYPE', 'type', $direction, $ordering),
			'status'    => Languages::_('ORGANIZER_STATUS')
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
		$link            = 'index.php?option=com_organizer&view=holiday_edit&id=';
		$structuredItems = [];

		foreach ($this->items as $item)
		{

			$dateString = Helpers\Dates::getDisplay($item->startDate, $item->endDate);
			$today      = Helpers\Dates::formatDate();
			$startDate  = Helpers\Dates::formatDate($item->startDate);
			$endDate    = Helpers\Dates::formatDate($item->endDate);
			$year       = date('Y', strtotime($item->startDate));

			if ($endDate < $today)
			{
				$status = Languages::_('ORGANIZER_EXPIRED');
			}
			elseif ($startDate > $today)
			{
				$status = Languages::_('ORGANIZER_PENDING');
			}
			else
			{
				$status = Languages::_('ORGANIZER_CURRENT');
			}

			$thisLink                             = $link . $item->id;
			$structuredItems[$index]              = [];
			$structuredItems[$index]['checkbox']  = HTML::_('grid.id', $index, $item->id);
			$structuredItems[$index]['name']      = HTML::_('link', $thisLink, $item->name) . ' (' . HTML::_('link',
					$thisLink, $year) . ')';
			$structuredItems[$index]['startDate'] = HTML::_('link', $thisLink, $dateString);
			$structuredItems[$index]['type']      = HTML::_('link', $thisLink,
				($item->type == self::OPTIONAL ? Languages::_('ORGANIZER_PLANNING_OPTIONAL') : ($item->type == self::PARTIAL ? Languages::_('ORGANIZER_PLANNING_MANUAL')
					: Languages::_('ORGANIZER_PLANNING_BLOCKED'))));
			$structuredItems[$index]['status']    = HTML::_('link', $thisLink, $status);

			$index++;
		}

		$this->items = $structuredItems;
	}
}