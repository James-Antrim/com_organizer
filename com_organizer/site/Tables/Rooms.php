<?php
/**
 * @package     Organizer
 * @extension   com_organizer
 * @author      James Antrim, <james.antrim@nm.thm.de>
 * @copyright   2020 TH Mittelhessen
 * @license     GNU GPL v.3
 * @link        www.thm.de
 */

namespace Organizer\Tables;

use JDatabaseDriver;

/**
 * Models the organizer_rooms table.
 */
class Rooms extends BaseTable
{
	use Activated, Aliased;

	/**
	 * The id of the building entry referenced.
	 * INT(11) UNSIGNED DEFAULT NULL
	 *
	 * @var int
	 */
	public $buildingID;

	/**
	 * The rooms maximum occupancy for participants.
	 * INT(4) UNSIGNED DEFAULT NULL
	 *
	 * @var string
	 */
	public $capacity;

	/**
	 * An abbreviated nomenclature for the resource. Currently corresponding to the identifier in Untis scheduling
	 * software.
	 * VARCHAR(60) DEFAULT NULL
	 *
	 * @var string
	 */
	public $code;

	/**
	 * The resource's name.
	 * VARCHAR(150) NOT NULL
	 *
	 * @var string
	 */
	public $name;

	/**
	 * The id of the roomtype entry referenced.
	 * INT(11) UNSIGNED DEFAULT NULL
	 *
	 * @var int
	 */
	public $roomtypeID;

	/**
	 * Declares the associated table
	 *
	 * @param   JDatabaseDriver &$dbo  A database connector object
	 */
	public function __construct(&$dbo = null)
	{
		parent::__construct('#__organizer_rooms', 'id', $dbo);
	}

	/**
	 * Set the table column names which are allowed to be null
	 *
	 * @return boolean  true
	 */
	public function check()
	{
		$nullColumns = ['alias', 'buildingID', 'code'];
		foreach ($nullColumns as $nullColumn)
		{
			if (!strlen($this->$nullColumn))
			{
				$this->$nullColumn = null;
			}
		}

		return true;
	}
}
