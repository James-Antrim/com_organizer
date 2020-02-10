<?php
/**
 * @package     Organizer
 * @extension   com_organizer
 * @author      James Antrim, <james.antrim@nm.thm.de>
 * @copyright   2020 TH Mittelhessen
 * @license     GNU GPL v.3
 * @link        www.thm.de
 */

namespace Organizer\Models;

use JDatabaseQuery;
use Organizer\Helpers\Can;
use Organizer\Helpers\Languages;
use Organizer\Helpers\Mappings;

/**
 * Class retrieves information for a filtered set of (subject) pools. Modal view.
 */
class PoolSelection extends ListModel
{
	/**
	 * Method to get a list of resources from the database.
	 *
	 * @return JDatabaseQuery
	 */
	protected function getListQuery()
	{
		$tag   = Languages::getTag();
		$query = $this->_db->getQuery(true);

		$query->select("DISTINCT p.id, p.name_$tag AS name, p.fieldID")
			->from('#__organizer_pools AS p');

		$authorizedDepts = Can::documentTheseOrganizations();
		$query->where('(p.organizationID IN (' . implode(',', $authorizedDepts) . ') OR p.organizationID IS NULL)');

		$searchColumns = [
			'p.name_de',
			'p.shortName_de',
			'p.abbreviation_de',
			'p.description_de',
			'p.name_en',
			'p.shortName_en',
			'p.abbreviation_en',
			'p.description_en'
		];
		$this->setSearchFilter($query, $searchColumns);
		$this->setValueFilters($query, ['organizationID', 'fieldID']);

		$programID = $this->state->get('filter.programID', '');
		Mappings::setResourceIDFilter($query, $programID, 'program', 'pool');

		$this->setOrdering($query);

		return $query;
	}
}
