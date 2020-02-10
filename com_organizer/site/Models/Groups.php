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

/**
 * Class retrieves information for a filtered set of groups.
 */
class Groups extends ListModel
{
	protected $defaultOrdering = 'gr.untisID';

	protected $filter_fields = ['categoryID', 'organizationID', 'gridID'];

	/**
	 * Method to get a list of resources from the database.
	 *
	 * @return JDatabaseQuery
	 */
	protected function getListQuery()
	{
		$authorized = Can::scheduleTheseOrganizations();

		$query = $this->_db->getQuery(true);
		$query->select('DISTINCT gr.id, gr.untisID, gr.fullName, gr.name, gr.categoryID, gr.gridID')
			->select('a.organizationID')
			->from('#__organizer_groups AS gr')
			->innerJoin('#__organizer_categories AS cat ON cat.id = gr.categoryID')
			->leftJoin('#__organizer_associations AS a ON a.categoryID = gr.categoryID')
			->where('(a.organizationID IN (' . implode(',', $authorized) . ') OR a.organizationID IS NULL)');

		$this->setSearchFilter($query, ['gr.fullName', 'gr.name', 'gr.untisID']);
		$this->setValueFilters($query, ['gr.categoryID', 'a.organizationID', 'gr.gridID']);

		$this->setOrdering($query);

		return $query;
	}
}
