<?php
/**
 * @package     Organizer
 * @extension   com_organizer
 * @author      James Antrim, <james.antrim@nm.thm.de>
 * @copyright   2020 TH Mittelhessen
 * @license     GNU GPL v.3
 * @link        www.thm.de
 */

namespace Organizer\Models;

use Organizer\Helpers\Units as UnitsHelper;
use Organizer\Tables\Units as UnitsTable;

/**
 * Class loads a form for editing unit data.
 */
class UnitEdit extends EditModel
{
	/**
	 * Method to get a single record.
	 *
	 * @param   integer  $pk  The id of the primary key
	 *
	 * @return mixed Object on success, false on failure
	 */
	public function getItem($pk = null)
	{
		$this->item          = parent::getItem($pk);
		$this->item->eventID = UnitsHelper::getEventID($this->item->id);

		return $this->item;
	}

	/**
	 * Method to get a table object, load it if necessary.
	 *
	 * @param   string  $name     The table name. Optional.
	 * @param   string  $prefix   The class prefix. Optional.
	 * @param   array   $options  Configuration array for model. Optional.
	 *
	 * @return UnitsTable A Table object
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	public function getTable($name = '', $prefix = '', $options = [])
	{
		return new UnitsTable;
	}
}
