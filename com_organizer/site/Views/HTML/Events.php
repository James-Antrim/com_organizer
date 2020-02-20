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

use Organizer\Helpers;

/**
 * Class loads persistent information a filtered set of events into the display context.
 */
class Events extends ListView
{
	protected $rowStructure = [
		'checkbox'        => '',
		'name'            => 'link',
		'organization'    => 'link',
		'campus'          => 'link',
		'maxParticipants' => 'link'
	];

	/**
	 * Function determines whether the user may access the view.
	 *
	 * @return bool true if the use may access the view, otherwise false
	 */
	protected function allowAccess()
	{
		return (bool) Helpers\Can::scheduleTheseOrganizations();
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
			'checkbox'        => '',
			'name'            => Helpers\HTML::sort('NAME', 'name', $direction, $ordering),
			'organization'    => Helpers\HTML::sort('ORGANIZATION', 'name', $direction, $ordering),
			'campus'          => Helpers\Languages::_('ORGANIZER_CAMPUS'),
			'maxParticipants' => Helpers\Languages::_('ORGANIZER_MAX_PARTICIPANTS')
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
		$link            = 'index.php?option=com_organizer&view=event_edit&id=';
		$structuredItems = [];

		foreach ($this->items as $item)
		{
			$item->campus            = Helpers\Campuses::getName($item->campusID);
			$item->maxParticipants   = empty($item->maxParticipants) ? 1000 : $item->maxParticipants;
			$structuredItems[$index] = $this->structureItem($index, $item, $link . $item->id);
			$index++;
		}

		$this->items = $structuredItems;
	}
}