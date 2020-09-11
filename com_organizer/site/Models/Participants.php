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
use Joomla\CMS\Form\Form;
use Organizer\Helpers;

/**
 * Class retrieves information for a filtered set of participants.
 */
class Participants extends ListModel
{
	protected $defaultOrdering = 'fullName';

	protected $filter_fields = ['attended', 'duplicates', 'paid', 'programID'];

	/**
	 * Method to get a list of resources from the database.
	 *
	 * @return JDatabaseQuery
	 */
	protected function getListQuery()
	{
		$query = $this->_db->getQuery(true);

		$query->select('pa.id, pa.programID, u.email')
			->select($query->concatenate(['pa.surname', "', '", 'pa.forename'], '') . ' AS fullName')
			->from('#__organizer_participants AS pa')
			->innerJoin('#__users AS u ON u.id = pa.id')
			->innerJoin('#__organizer_programs AS pr ON pr.id = pa.programID');

		$this->setSearchFilter($query, ['pa.forename', 'pa.surname', 'pr.name_de', 'pr.name_en']);
		$this->setValueFilters($query, ['programID']);

		if ($courseID = $this->state->get('filter.courseID'))
		{
			$query->select('cp.attended, cp.paid, cp.status')
				->innerJoin('#__organizer_course_participants AS cp ON cp.participantID = pa.id')
				->where("cp.courseID = $courseID");
		}

		if ($this->state->get('filter.duplicates'))
		{
			$likePAFN   = $query->concatenate(["'%'", 'TRIM(pa.forename)', "'%'"], '');
			$likePA2FN  = $query->concatenate(["'%'", 'TRIM(pa2.forename)', "'%'"], '');
			$conditions = "((pa.forename LIKE $likePA2FN OR pa2.forename LIKE $likePAFN)";

			$conditions .= " AND ";

			$likePASN   = $query->concatenate(["'%'", 'TRIM(pa.surname)', "'%'"], '');
			$likePA2SN  = $query->concatenate(["'%'", 'TRIM(pa2.surname)', "'%'"], '');
			$conditions .= "(pa.surname LIKE $likePA2SN OR pa2.surname LIKE $likePASN))";
			$query->leftJoin("#__organizer_participants AS pa2 ON $conditions")
				->where('pa.id != pa2.id')
				->group('pa.id');

			if ($courseID)
			{
				$query->innerJoin('#__organizer_course_participants AS cp2 ON cp2.participantID = pa2.id')
					->where("cp2.courseID = $courseID");
			}
		}

		$this->setOrdering($query);

		return $query;
	}

	/**
	 * Method to auto-populate the model state.
	 *
	 * @param   string  $ordering   An optional ordering field.
	 * @param   string  $direction  An optional direction (asc|desc).
	 *
	 * @return void populates state properties
	 */
	protected function populateState($ordering = null, $direction = null)
	{
		parent::populateState($ordering, $direction);

		if ($courseID = Helpers\Input::getFilterID('course'))
		{
			$this->setState('filter.courseID', $courseID);
		}
	}
}
