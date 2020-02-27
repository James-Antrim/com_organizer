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
 * Models the organizer_instance_groups table.
 */
class InstanceGroups extends BaseTable
{
	use Modified;

	/**
	 * The id of the instance persons entry referenced.
	 * INT(20) UNSIGNED NOT NULL
	 *
	 * @var int
	 */
	public $assocID;

	/**
	 * The id of the group entry referenced.
	 * INT(11) UNSIGNED NOT NULL
	 *
	 * @var int
	 */
	public $groupID;

	/**
	 * Declares the associated table
	 *
	 * @param   JDatabaseDriver &$dbo  A database connector object
	 */
	public function __construct(&$dbo = null)
	{
		parent::__construct('#__organizer_instance_groups', 'id', $dbo);
	}
}
