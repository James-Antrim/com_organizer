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
 * Resources which can be reached over a URL are addressable.
 */
trait Modified
{
	/**
	 * The resource's delta status. Possible values: '', 'new,' 'removed'.
	 * VARCHAR(10) NOT NULL DEFAULT ''
	 *
	 * @var string
	 */
	public $delta;

	/**
	 * The timestamp at which the entry was last modified.
	 * TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
	 *
	 * @var int
	 */
	public $modified;
}