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
 * Models the organizer_instances table.
 */
class Instances extends BaseTable
{
	use Modified;

	/**
	 * The id of the block entry referenced.
	 * INT(11) UNSIGNED NOT NULL
	 *
	 * @var int
	 */
	public $blockID;

	/**
	 * The id of the event entry referenced.
	 * INT(11) UNSIGNED DEFAULT NULL
	 *
	 * @var int
	 */
	public $eventID;

	/**
	 * The id of the method entry referenced.
	 * INT(11) UNSIGNED DEFAULT NULL
	 *
	 * @var int
	 */
	public $methodID;

	/**
	 * The person's first and middle names.
	 * VARCHAR(255) NOT NULL DEFAULT ''
	 *
	 * @var string
	 */
	public $title;

	/**
	 * The id of the unit entry referenced.
	 * INT(11) UNSIGNED NOT NULL
	 *
	 * @var int
	 */
	public $unitID;

	/**
	 * Declares the associated table.
	 */
	public function __construct()
	{
		parent::__construct('#__organizer_instances');
	}

	/**
	 * @inheritDoc
	 */
	public function check(): bool
	{
		if (empty($this->methodID))
		{
			$this->methodID = null;
		}

		return true;
	}
}
