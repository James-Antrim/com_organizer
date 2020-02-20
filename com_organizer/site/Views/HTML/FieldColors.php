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
 * Class loads persistent information a filtered set of fields (of expertise) into the display context.
 */
class FieldColors extends ListView
{
	protected $rowStructure = ['checkbox' => '', 'name' => 'link', 'code' => 'link', 'color' => 'value'];

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
	 * Function to set the object's headers property
	 *
	 * @return void sets the object headers property
	 */
	public function setHeaders()
	{
		$ordering  = $this->state->get('list.ordering');
		$direction = $this->state->get('list.direction');
		$headers   = [
			'checkbox' => '',
			'field'    => Helpers\HTML::sort('NAME', 'field', $direction, $ordering),
			'code'     => Helpers\HTML::sort('CODE', 'code', $direction, $ordering),
			'color'    => Helpers\Languages::_('ORGANIZER_COLOR')
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
		$link            = 'index.php?option=com_organizer&view=field_color_edit&id=';
		$locked          = '<span class="icon-checkedout"></span>';
		$locked          .= Helpers\Languages::_('ORGANIZER_SELECT_ORGANIZATION');
		$organizationID  = $this->state->get('filter.organizationID', 0);
		$structuredItems = [];

		foreach ($this->items as $item)
		{
			$item->color = $organizationID ? Helpers\Colors::getListDisplay($item->color, $item->colorID) : $locked;

			$structuredItems[$index] = $this->structureItem($index, $item, $link . $item->id);
			$index++;
		}

		$this->items = $structuredItems;
	}
}
