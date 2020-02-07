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
 * Models the organizer_participants table.
 */
class Participants extends BaseTable
{
	/**
	 * The physical address of the resource.
	 * VARCHAR(60) NOT NULL DEFAULT ''
	 *
	 * @var string
	 */
	public $address;

	/**
	 * The city in which the resource is located.
	 * VARCHAR(60) NOT NULL
	 *
	 * @var string
	 */
	public $city;

	/**
	 * The person's first and middle names.
	 * VARCHAR(255) NOT NULL DEFAULT ''
	 *
	 * @var string
	 */
	public $forename;

	/**
	 * The primary key, FK to #__users.
	 * INT(11) NOT NULL
	 *
	 * @var int
	 */
	public $id;

	/**
	 * A flag displaying whether the user wishes to receive emails regarding schedule changes.
	 * TINYINT(1) UNSIGNED NOT NULL DEFAULT 0
	 *
	 * @var bool
	 */
	public $notify;

	/**
	 * The id of the program entry referenced.
	 * INT(11) UNSIGNED DEFAULT NULL
	 *
	 * @var int
	 */
	public $programID;

	/**
	 * The person's surnames.
	 * VARCHAR(255) NOT NULL DEFAULT ''
	 *
	 * @var string
	 */
	public $surname;

	/**
	 * The ZIP code of the resource.
	 * INT(11) NOT NULL DEFAULT 0
	 *
	 * @var string
	 */
	public $zipCode;

	/**
	 * Declares the associated table
	 *
	 * @param   JDatabaseDriver &$dbo  A database connector object
	 */
	public function __construct(&$dbo = null)
	{
		parent::__construct('#__organizer_participants', 'id', $dbo);
	}

	/**
	 * Set the table column names which are allowed to be null
	 *
	 * @return boolean  true
	 */
	public function check()
	{
		if (empty($this->programID))
		{
			$this->programID = null;
		}

		return true;
	}
}
