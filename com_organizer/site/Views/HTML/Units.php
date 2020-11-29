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
use Organizer\Helpers\Languages;

/**
 * Class which loads data into the view output context
 */
class Units extends ListView
{
	private $statusDate;

	/**
	 * @inheritdoc
	 */
	public function __construct($config = [])
	{
		parent::__construct($config);
		$this->statusDate = date('Y-m-d H:i:s', strtotime('-14 days'));
	}

	/**
	 * Function determines whether the user may access the view.
	 *
	 * @return void
	 */
	protected function authorize()
	{
		if (!Helpers\Can::scheduleTheseOrganizations())
		{
			Helpers\OrganizerHelper::error(403);
		}
	}

	/**
	 * Adds a toolbar and title to the view.
	 *
	 * @return void  sets context variables
	 */
	protected function addToolBar()
	{
		Helpers\HTML::setTitle(Helpers\Languages::_("ORGANIZER_UNITS"), 'list-2');
		$toolbar = Toolbar::getInstance();

		$toolbar->appendButton(
			'Standard',
			'plus',
			Helpers\Languages::_('ORGANIZER_ADD_COURSE'),
			"units.addCourse",
			true
		);

		if (Helpers\Can::administrate())
		{
			/*$toolbar->appendButton('Standard', 'edit', Helpers\Languages::_('ORGANIZER_EDIT'), "units.edit", true);
			$toolbar->appendButton(
				'Confirm',
				Helpers\Languages::_('ORGANIZER_DELETE_CONFIRM'),
				'delete',
				Helpers\Languages::_('ORGANIZER_DELETE'),
				"units.delete",
				true
			);*/
		}
	}

	/**
	 * Created a structure for displaying status information as necessary.
	 *
	 * @param   object  $item  the instance item being iterated
	 *
	 * @return array|string
	 */
	private function getStatus($item)
	{
		$class = 'status-display hasToolTip';
		$title = '';

		// If removed are here at all, the status holds relevance irregardless of date
		if ($item->status === 'removed')
		{
			$date  = Helpers\Dates::formatDate($item->modified);
			$class .= ' unit-removed';
			$title = sprintf(Languages::_('ORGANIZER_UNIT_REMOVED_ON'), $date);
		}
		elseif ($item->status === 'new' and $item->modified >= $this->statusDate)
		{
			$date  = Helpers\Dates::formatDate($item->modified);
			$class .= ' unit-new';
			$title = sprintf(Languages::_('ORGANIZER_UNIT_ADDED_ON'), $date);

		}

		return $title ? ['attributes' => ['class' => $class, 'title' => $title], 'value' => ''] : '';
	}

	/**
	 * Function to set the object's headers property
	 *
	 * @return void sets the object headers property
	 */
	public function setHeaders()
	{
		$headers = [
			'checkbox' => Helpers\HTML::_('grid.checkall'),
			'status'   => '',
			'name'     => Languages::_('ORGANIZER_NAME'),
			'method'   => Languages::_('ORGANIZER_METHOD'),
			'dates'    => Languages::_('ORGANIZER_DATES'),
			'grid'     => Languages::_('ORGANIZER_GRID'),
			'code'     => Languages::_('ORGANIZER_UNTIS_ID'),
			//'run'      => Languages::_('ORGANIZER_RUN')
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
		$index = 0;
		//$link  = "index.php?option=com_organizer&view=unit_edit&id=";

		$structuredItems = [];

		foreach ($this->items as $item)
		{
			$endDate   = Helpers\Dates::formatDate($item->endDate);
			$startDate = Helpers\Dates::formatDate($item->startDate);

			// $thisLink = $link . $item->id;
			$structuredItems[$index]             = [];
			$structuredItems[$index]['checkbox'] = Helpers\HTML::_('grid.id', $index, $item->id);
			$structuredItems[$index]['status']   = $this->getStatus($item);
			$structuredItems[$index]['name']     = $item->name;
			$structuredItems[$index]['method']   = $item->method;
			$structuredItems[$index]['dates']    = "$startDate - $endDate";
			$structuredItems[$index]['grid']     = $item->grid;
			$structuredItems[$index]['code']     = $item->code;
			//$structuredItems[$index]['run']      = Helpers\HTML::_('link', $thisLink, $item->run);

			$index++;
		}

		$this->items = $structuredItems;
	}
}
