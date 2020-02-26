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
 * Models the organizer_persons table.
 */
class Persons extends BaseTable
{
	use Activated, Aliased, Suppressed;

	/**
	 * An abbreviated nomenclature for the resource. Currently corresponding to the identifier in Untis scheduling
	 * software.
	 * VARCHAR(60) DEFAULT NULL
	 *
	 * @var string
	 */
	public $code;

	/**
	 * The person's first and middle names.
	 * VARCHAR(255) NOT NULL DEFAULT ''
	 *
	 * @var string
	 */
	public $forename;

	/**
	 * The person's surnames.
	 * VARCHAR(255) NOT NULL
	 *
	 * @var string
	 */
	public $surname;

	/**
	 * The person's titles.
	 * VARCHAR(45) NOT NULL DEFAULT ''
	 *
	 * @var string
	 */
	public $title;

	/**
	 * The person's user name.
	 * VARCHAR(150) DEFAULT NULL
	 *
	 * @var string
	 */
	public $username;

	/**
	 * Declares the associated table
	 *
	 * @param   JDatabaseDriver &$dbo  A database connector object
	 */
	public function __construct(&$dbo = null)
	{
		parent::__construct('#__organizer_persons', 'id', $dbo);
	}

	/**
	 * Set the table column names which are allowed to be null
	 *
	 * @return boolean  true
	 */
	public function check()
	{
		// All three fields can recieve data from at least two systems.
		$nullColumns = ['alias', 'code', 'username'];
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
