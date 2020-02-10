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

/**
 * Class retrieves information for a filtered set of categories.
 */
class Categories extends ListModel
{
	protected $defaultOrdering = 'cat.name';

	protected $filter_fields = ['organizationID'];

	/**
	 * Method to get a list of resources from the database.
	 *
	 * @return JDatabaseQuery
	 */
	protected function getListQuery()
	{
		$query = $this->_db->getQuery(true);
		$query->select('DISTINCT cat.id, cat.untisID, cat.name')
			->from('#__organizer_categories AS cat')
			->innerJoin('#__organizer_associations AS a ON a.categoryID = cat.id');

		$authorized = implode(",", Can::scheduleTheseOrganizations());
		$query->where("a.organizationID IN ($authorized)");

		$this->setSearchFilter($query, ['cat.name', 'cat.untisID']);
		$this->setValueFilters($query, ['organizationID', 'programID']);
		$this->setOrdering($query);

		return $query;
	}
}
