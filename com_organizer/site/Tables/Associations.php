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

/**
 * Models the organizer_associations table.
 */
class Associations extends BaseTable
{
	/**
	 * The id of the category entry referenced.
	 * INT(11) UNSIGNED DEFAULT NULL
	 *
	 * @var int
	 */
	public $categoryID;

	/**
	 * The id of the event entry referenced.
	 * INT(11) DEFAULT NULL
	 *
	 * @var int
	 */
	public $eventID;

	/**
	 * The id of the group entry referenced.
	 * INT(11) DEFAULT NULL
	 *
	 * @var int
	 */
	public $groupID;

	/**
	 * The id of the organization entry referenced.
	 * INT(11) UNSIGNED NOT NULL
	 *
	 * @var int
	 */
	public $organizationID;

	/**
	 * The id of the person entry referenced.
	 * INT(11) DEFAULT NULL
	 *
	 * @var int
	 */
	public $personID;

	/**
	 * Declares the associated table
	 *
	 * @param   \JDatabaseDriver &$dbo  A database connector object
	 */
	public function __construct(&$dbo = null)
	{
		parent::__construct('#__organizer_associatons', 'id', $dbo);
	}
}
