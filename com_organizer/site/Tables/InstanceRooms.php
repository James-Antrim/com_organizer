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
 * Models the organizer_instance_rooms table.
 */
class InstanceRooms extends BaseTable
{
	/**
	 * The id of the instance persons entry referenced.
	 * INT(20) UNSIGNED NOT NULL
	 *
	 * @var int
	 */
	public $assocID;

	/**
	 * The textual description of the associations last change. Values: changed, <empty>, new, removed.
	 * VARCHAR(10) NOT NULL DEFAULT ''
	 *
	 * @var string
	 */
	public $delta;

	/**
	 * The timestamp of the time at which the last change to the entry occurred.
	 * TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
	 *
	 * @var int
	 */
	public $modified;

	/**
	 * The id of the room entry referenced.
	 * INT(11) UNSIGNED NOT NULL
	 *
	 * @var int
	 */
	public $roomID;

	/**
	 * Declares the associated table
	 *
	 * @param   JDatabaseDriver &$dbo  A database connector object
	 */
	public function __construct(&$dbo = null)
	{
		parent::__construct('#__organizer_instance_rooms', 'id', $dbo);
	}

	/**
	 * Set the table column names which are allowed to be null
	 *
	 * @return boolean  true
	 */
	public function check()
	{
		$this->modified = null;

		return true;
	}
}
