<?php
/**
 * @package     Organizer
 * @extension   com_organizer
 * @author      James Antrim, <james.antrim@nm.thm.de>
 * @copyright   2020 TH Mittelhessen
 * @license     GNU GPL v.3
 * @link        www.thm.de
 */

namespace Organizer\Helpers;

use Organizer\Adapters;

/**
 * Provides general functions for room type access checks, data retrieval and display.
 */
class Roomtypes extends ResourceHelper implements Selectable
{
	use Filtered;

	const NO = 0;

	const YES = 1;

	/**
	 * Retrieves a list of resources in the form of name => id.
	 *
	 * @return array the resources, or empty
	 */
	public static function getOptions()
	{
		$options = [];
		foreach (self::getResources() as $type)
		{
			$options[] = HTML::_('select.option', $type['id'], $type['name']);
		}

		return $options;
	}

	/**
	 * Retrieves the resource items.
	 *
	 * @param   bool  $associated  whether the type needs to be associated with a room
	 * @param   bool  $public
	 *
	 * @return array the available resources
	 */
	public static function getResources($associated = self::YES, $suppress = self::NO)
	{
		$tag = Languages::getTag();

		$query = Adapters\Database::getQuery(true);
		$query->select("DISTINCT t.*, t.id AS id, t.name_$tag AS name")
			->from('#__organizer_roomtypes AS t');

		if ($suppress === self::YES or $suppress === self::NO)
		{
			$query->where("t.suppress = $suppress");
		}

		if ($associated === self::YES)
		{
			$query->innerJoin('#__organizer_rooms AS r ON r.roomtypeID = t.id');
		}
		elseif ($associated === self::NO)
		{
			$query->leftJoin('#__organizer_rooms AS r ON r.roomtypeID = t.id');
			$query->where('r.roomtypeID IS NULL');
		}

		self::addResourceFilter($query, 'building', 'b1', 'r');

		// This join is used specifically to filter campuses independent of buildings.
		$query->leftJoin('#__organizer_buildings AS b2 ON b2.id = r.buildingID');
		self::addCampusFilter($query, 'b2');

		$query->order('name');
		Adapters\Database::setQuery($query);

		return Adapters\Database::loadAssocList();
	}
}
