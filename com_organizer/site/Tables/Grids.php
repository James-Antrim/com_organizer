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
 * Models the organizer_grids table.
 */
class Grids extends BaseTable
{
	/**
	 * An abbreviated nomenclature for the resource. Currently corresponding to the identifier in Untis scheduling
	 * software.
	 * VARCHAR(60) DEFAULT NULL
	 *
	 * @var string
	 */
	public $code;

	/**
	 * A flag to determine which grid is to be used if none is specified.
	 * TINYINT(1) UNSIGNED NOT NULL DEFAULT 0
	 *
	 * @var bool
	 */
	public $defaultGrid;

	/**
	 * A grid object modeled by a JSON string, containing the respective start and end times of the grid blocks.
	 * TEXT NOT NULL
	 *
	 * @var string
	 */
	public $grid;

	/**
	 * The resource's German name.
	 * VARCHAR(255) NOT NULL
	 *
	 * @var string
	 */
	public $name_de;

	/**
	 * The resource's English name.
	 * VARCHAR(255) NOT NULL
	 *
	 * @var string
	 */
	public $name_en;

	/**
	 * Declares the associated table
	 *
	 * @param   \JDatabaseDriver &$dbo  A database connector object
	 */
	public function __construct(&$dbo = null)
	{
		parent::__construct('#__organizer_grids', 'id', $dbo);
	}
}
