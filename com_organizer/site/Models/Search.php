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

use Organizer\Helpers\Can;
use Organizer\Helpers;
use Organizer\Helpers\Input;
use Organizer\Helpers\Languages;
use Organizer\Helpers\OrganizerHelper;

/**
 * Class searches THM Organizer resources for resources and views relevant to the given search query.
 */
class Search extends BaseModel
{
	private $authorized;

	public $tag;

	private $programResults;

	public $results;

	private $personID;

	private $terms;

	/**
	 * Aggregates inclusive conditions into one 'where' clause
	 *
	 * @param   object &$query       the query object
	 * @param   array   $conditions  the conditions to be added to the query
	 *
	 * @return void modifies the query
	 */
	private function addInclusiveConditions(&$query, $conditions)
	{
		$query->where('(' . implode(' OR ', $conditions) . ')');

		return;
	}

	/**
	 * Iterates through the various match strengths and removes redundant entries in weaker strengths.
	 *
	 * @return void modifies the $results property
	 */
	private function cleanResults()
	{
		$strengths = array_keys($this->results);

		foreach ($strengths as $outerStrength)
		{
			$osResults = $this->results[$outerStrength];

			foreach ($osResults as $resource => $rResults)
			{
				foreach (array_keys($rResults) as $resultID)
				{
					foreach ($strengths as $innerStrength)
					{
						if ($outerStrength == $innerStrength)
						{
							continue;
						}

						if (!empty($this->results[$innerStrength][$resource])
							and !empty($this->results[$innerStrength][$resource][$resultID]))
						{
							unset($this->results[$innerStrength][$resource][$resultID]);

							// Check if there is nothing left to avoid unnecessary iteration in the output
							if (empty($this->results[$innerStrength][$resource]))
							{
								unset($this->results[$innerStrength][$resource]);
							}
						}
					}
				}
			}
		}

		foreach ($this->results as $strength => $sResults)
		{
			foreach ($sResults as $resource => $rResults)
			{
				usort($this->results[$strength][$resource], ['OrganizerModelSearch', 'sortItems']);
			}
		}
	}

	/**
	 * Filters lessons according to status and term
	 *
	 * @param   object &$query   the query object to filter
	 * @param   int     $termID  the id of the term for lesson results
	 *
	 * @return void modifies the query
	 */
	private function filterLessons(&$query, $termID = null)
	{
		$query->where("(lcrs.delta IS NULL OR lcrs.delta != 'removed')")
			->where("(l.delta IS NULL OR l.delta != 'removed')");

		if (!empty($termID) and is_int($termID))
		{
			$query->where("l.termID = '$termID'");
		}

		return;
	}

	/**
	 * Finds degrees which can be associated with the terms. Possible return strengths exact and strong.
	 *
	 * @param   array  $terms  the search terms
	 *
	 * @return array an array of degreeIDs, grouped by strength
	 */
	private function getDegrees($terms)
	{
		$query = $this->_db->getQuery(true);
		$query->select('*')
			->select("REPLACE(LOWER(abbreviation), '.', '') AS stdAbbr")
			->from('#__organizer_degrees');
		$this->_db->setQuery($query);

		$degrees = OrganizerHelper::executeQuery('loadAssocList', [], 'id');

		// Abbreviation or (title and type) matched
		$exactMatches = [];

		// Title or type matched
		$strongMatches = [];

		foreach ($degrees as $degreeID => $degree)
		{
			$key = array_search($degree['stdAbbr'], $terms);

			$nameParts = explode(' of ', $degree['name']);
			$title     = strtolower(array_shift($nameParts));
			$subject   = strtolower(implode(' of ', $nameParts));

			$titleFoundAt   = array_search($title, $terms);
			$subjectFoundAt = array_search($subject, $terms);

			$exactMatch = ($key !== false or ($titleFoundAt !== false and $subjectFoundAt !== false));

			if ($exactMatch)
			{
				// The abbreviated degree name only relevant here and can create false positives elsewhere => delete
				if ($key !== false)
				{
					unset($this->terms[$key]);
				}

				$exactMatches[$degreeID] = $degree;
			}
			elseif ($subjectFoundAt !== false or $titleFoundAt !== false)
			{
				$strongMatches[$degreeID] = $degree;
			}
		}

		return ['exact' => $exactMatches, 'strong' => $strongMatches];
	}

	/**
	 * Searches for Organizer resources and creates links to relevant views
	 *
	 * @return array the results grouped by match strength
	 */
	public function getResults()
	{
		$this->personID   = Helpers\Persons::getIDByUserID();
		$this->authorized = Can::scheduleTheseOrganizations();

		/**
		 * Exact     => exact match for the whole search independent of capitalization
		 * Strong    => exact match on one of the search terms
		 * Good      => similar to one or more of the search terms
		 * Related   => matches via a relation with an exact/partial/strong match
		 * Mentioned => one or more of the terms is a part of the extended text for the resource
		 */
		$this->results = ['exact' => [], 'strong' => [], 'good' => [], 'related' => [], 'mentioned' => []];
		$this->tag     = Languages::getTag();

		$rawSearch = trim(Input::getString('search'));

		// New call or a hard reset
		if ($rawSearch === '')
		{
			return $this->results;
		}

		$this->setTerms($rawSearch);

		// Programs are searched for initially and set as an object property for use by organizations, pools and programs
		$this->setPrograms();

		// Ordered by what I imagine their relative search frequency will be
		$this->searchSubjects();
		$this->searchPools();
		$this->searchPrograms();
		$this->searchPersons();
		$this->searchRooms();
		$this->searchOrganizations();

		$this->cleanResults();

		return $this->results;
	}

	/**
	 * Checks for room types which match the the capacity and unresolvable terms.
	 *
	 * @param   array  $misc      an array of terms which could not be resolved
	 * @param   int    $capacity  the requested capacity
	 *
	 * @return array the room type ids which matched the criteria
	 */
	private function getRoomtypes(&$misc, $capacity = 0)
	{
		if (empty($misc) and empty($capacity))
		{
			return [];
		}

		$query = $this->_db->getQuery(true);
		$query->select('id')->from('#__organizer_roomtypes');

		$roomTypeIDs    = [];
		$standardClause = "(name_de LIKE '%XXX%' OR name_en LIKE '%XXX%' ";
		$standardClause .= "OR description_de LIKE '%XXX%' OR description_en LIKE '%XXX%')";

		if (!empty($misc))
		{
			foreach ($misc as $key => $term)
			{
				$query->clear('where');
				if (!empty($capacity))
				{
					// Opens conjunctive clause and cap from type
					$query->where("(minCapacity IS NULL OR minCapacity = '0' OR minCapacity <= '$capacity')");
					$query->where("(maxCapacity IS NULL OR maxCapacity = '0' OR maxCapacity >= '$capacity')");
				}

				$tempClause = str_replace('XXX', $term, $standardClause);
				$query->where($tempClause);
				$this->_db->setQuery($query);
				$typeResults = OrganizerHelper::executeQuery('loadColumn', []);

				if (!empty($typeResults))
				{
					unset($misc[$key]);
					$roomTypeIDs = array_merge($roomTypeIDs, $typeResults);
				}
			}
		}
		elseif (!empty($capacity))
		{
			$query->where("(minCapacity IS NULL OR minCapacity = '0' OR minCapacity <= '$capacity')");
			$query->where("(maxCapacity IS NULL OR maxCapacity = '0' OR maxCapacity >= '$capacity')");

			$maxCapacityValid = "(maxCapacity IS NOT NULL AND maxCapacity > '0')";
			$minCapacityValid = "(minCapacity IS NOT NULL AND minCapacity > '0')";
			$query->where("($maxCapacityValid OR $minCapacityValid)");

			$this->_db->setQuery($query);

			$typeResults = OrganizerHelper::executeQuery('loadColumn', []);

			if (!empty($typeResults))
			{
				$roomTypeIDs = array_merge($roomTypeIDs, $typeResults);
			}
		}

		return array_unique($roomTypeIDs);
	}

	/**
	 * Processes organization results into a standardized array for output
	 *
	 * @param   array  $results  the organization results
	 *
	 * @return array modifies the results property
	 */
	private function processOrganizations($results)
	{
		$organizations = [];

		if (!empty($results))
		{
			foreach ($results as $organizationID)
			{
				$organizationName = Helpers\Organizations::getName($organizationID);

				$organizations[$organizationID]         = [];
				$organizations[$organizationID]['text'] = Languages::_('ORGANIZER_ORGANIZATION') . ": {$organizationName}";

				$links['schedule']   = "?option=com_organizer&view=schedule_item&organizationIDs=$organizationID";
				$links['event_list'] = "?option=com_organizer&view=event_list&organizationIDs=$organizationID";

				$organizations[$organizationID]['links'] = $links;
			}
		}

		return $organizations;
	}

	/**
	 * Processes pool results into a standardized array for output
	 *
	 * @param   array  &$pools    the array that the pools are to be stored in
	 * @param   array   $results  the pool id results
	 * @param   string  $type     the type of pool ids being processed
	 *
	 * @return mixed
	 */
	private function processPools(&$pools, $results, $type)
	{
		foreach ($results as $result)
		{
			if ($type == 'real')
			{
				$index = "d{$result['id']}";
				$text  = Helpers\Pools::getName($result['id']);
				$links = ['subjects' => "?option=com_organizer&view=subjects&poolIDs={$result['id']}"];
			}
			else
			{
				$index               = "p{$result['id']}";
				$text                = Helpers\Groups::getName($result['id']);
				$links['schedule']   = "?option=com_organizer&view=schedule_item&poolIDs={$result['id']}";
				$links['event_list'] = "?option=com_organizer&view=event_list&groupIDs={$result['id']}";
			}

			$pools[$index]          = [];
			$pools[$index]['text']  = Languages::_('ORGANIZER_POOL') . ": {$result['program']}, $text";
			$pools[$index]['links'] = $links;
		}

		return $pools;
	}

	/**
	 * Processes category results into a standardized array for output
	 *
	 * @param   array  $programs    the program documentation results
	 * @param   array  $categories  the category results
	 *
	 * @return array $programs
	 */
	private function processPrograms($programs, $categories)
	{
		$results = [];

		if (!empty($programs))
		{
			foreach ($programs as $category)
			{
				$invalidMapping =
					(empty($category['lft']) or empty($category['rgt']) or $category['rgt'] - $category['lft'] < 2);

				$noPlan = empty($category['categoryID']);

				// Any linked view would be empty
				if ($invalidMapping and $noPlan)
				{
					continue;
				}

				$pIndex     = "d{$category['id']}";
				$programID  = $category['id'];
				$categoryID = $category['categoryID'];

				$results[$pIndex]               = [];
				$results[$pIndex]['programID']  = $programID;
				$results[$pIndex]['categoryID'] = $categoryID;
				$results[$pIndex]['lft']        = $category['lft'];
				$results[$pIndex]['rgt']        = $category['rgt'];

				$text                     = Helpers\Programs::getName($programID);
				$results[$pIndex]['name'] = $text;
				$results[$pIndex]['text'] = Languages::_('ORGANIZER_PROGRAM') . ": $text";

				$links = [];

				$invalidMapping =
					(empty($category['lft']) or empty($category['rgt']) or $category['rgt'] - $category['lft'] < 2);

				// If the mapping is invalid only an empty data set would be displayed for subject list and curriculum
				if (!$invalidMapping)
				{
					$links['subjects']   = "?option=com_organizer&view=subjects&programIDs=$programID";
					$links['curriculum'] = "?option=com_organizer&view=curriculum&programIDs=$programID";
				}

				if (!$noPlan)
				{
					$links['schedule']   = "?option=com_organizer&view=schedule_item&programIDs=$categoryID";
					$links['event_list'] = "?option=com_organizer&view=event_list&categoryIDs=$categoryID";
				}

				$results[$pIndex]['links'] = $links;
			}
		}

		if (!empty($categories))
		{
			foreach ($categories as $category)
			{
				$cIndex     = "p{$category['categoryID']}";
				$programID  = $category['id'];
				$categoryID = $category['categoryID'];

				$scheduleLink  = "?option=com_organizer&view=schedule_item&programIDs=$categoryID";
				$eventListLink = "?option=com_organizer&view=event_list&categoryIDs=$categoryID";

				// Subject was found
				if (!empty($category['id']))
				{
					$pIndex = "d{$category['id']}";

					// No redundant subject entries
					if (!empty($pIndex) and !empty($results[$pIndex]))
					{
						$results[$pIndex]['categoryID']        = $categoryID;
						$results[$pIndex]['links']['schedule'] = $scheduleLink;

						continue;
					}
				}

				$results[$cIndex]               = [];
				$results[$cIndex]['categoryID'] = $categoryID;
				$text                           = Helpers\Categories::getName($categoryID);
				$results[$cIndex]['name']       = $text;
				$results[$cIndex]['text']       = Languages::_('ORGANIZER_PROGRAM') . ": $text";

				$links = [];

				$invalidMapping =
					(empty($category['lft']) or empty($category['rgt']) or $category['rgt'] - $category['lft'] < 2);

				if (!$invalidMapping)
				{
					$results[$cIndex]['programID'] = $programID;

					$links['subjects']   = "?option=com_organizer&view=subjects&programIDs=$programID";
					$links['curriculum'] = "?option=com_organizer&view=curriculum&programIDs=$programID";
				}

				$links['schedule']         = $scheduleLink;
				$links['event_list']       = $eventListLink;
				$results[$cIndex]['links'] = $links;
			}
		}

		return $results;
	}

	/**
	 * Processes room results into a standardized array for output
	 *
	 * @param   array &$results  the room results
	 *
	 * @return array of formatted room results
	 */
	private function processRooms($results)
	{
		$rooms = [];

		if (!empty($results))
		{
			foreach ($results as $room)
			{
				$roomID         = $room['id'];
				$rooms[$roomID] = [];

				$rooms[$roomID]['text'] = Languages::_('ORGANIZER_ROOM') . ": {$room['name']}";

				$description = empty($room['description']) ? $room['type'] : $room['description'];

				if (empty($room['capacity']))
				{
					$capacity = '';
				}
				else
				{
					$capacity = ' (~' . $room['capacity'] . ' ' . Languages::_('ORGANIZER_SEATS') . ')';
				}

				$rooms[$roomID]['description'] = "$description$capacity";

				$rooms[$roomID]['links'] =
					['schedule' => "?option=com_organizer&view=schedule_item&roomIDs={$room['id']}"];
			}
		}

		return $rooms;
	}

	/**
	 * Processes subject/lesson results into a standardized array for output
	 *
	 * @param   array  $sResults   the subject documentation results
	 * @param   array  $coResults  the course results
	 *
	 * @return array $subjects
	 */
	private function processSubjects($sResults, $coResults)
	{
		$subjects = [];

		if (!empty($sResults))
		{
			foreach ($sResults as $sID => $subject)
			{
				$subjectID = "s$sID";

				$subjects[$subjectID] = [];

				$text = Helpers\Subjects::getName($sID, true);

				$subjects[$subjectID]['text'] = Languages::_('ORGANIZER_SUBJECT') . ": $text";

				$links = [];

				$links['subject_item'] = "?option=com_organizer&view=subject_item&id=$sID";

				if (!empty($subject['courseID']))
				{
					$links['schedule'] =
						"?option=com_organizer&view=schedule_item&subjectIDs={$subject['courseID']}";
				}

				$subjects[$subjectID]['links']       = $links;
				$subjects[$subjectID]['description'] = Helpers\Subjects::getPrograms($sID);
			}
		}

		if (!empty($coResults))
		{
			foreach ($coResults as $courseID => $course)
			{
				$courseID         = "p$courseID";
				$scheduleLink     = "?option=com_organizer&view=schedule_item&subjectIDs=$courseID";
				$scheduleListLink = "?option=com_organizer&view=event_list&subjectIDs=$courseID";

				// Subject was found
				if (!empty($course['sID']))
				{
					$subjectID = "s{$course['sID']}";

					// No redundant subject entries
					if (!empty($subjects[$subjectID]))
					{
						if (empty($subjects[$subjectID]['links']['schedule']))
						{
							$subjects[$subjectID]['links']['schedule']   = $scheduleLink;
							$subjects[$subjectID]['links']['event_list'] = $scheduleListLink;
						}

						continue;
					}
				}

				$subjects[$courseID] = [];

				$text = Helpers\Courses::getName($courseID, true);

				$subjects[$courseID]['text'] = Languages::_('ORGANIZER_SUBJECT') . ": $text";

				$links = [];

				if (!empty($course['sID']))
				{
					$links['subject_item'] = "?option=com_organizer&view=subject_item&id={$course['sID']}";
				}

				$links['schedule']                  = $scheduleLink;
				$links['event_list']                = $scheduleListLink;
				$subjects[$courseID]['links']       = $links;
				$subjects[$courseID]['description'] = Helpers\Courses::getCategories($courseID);
			}
		}

		return $subjects;
	}

	/**
	 * Processes person results into a standardized array for output
	 *
	 * @param   array  $results  the person results
	 *
	 * @return array $persons
	 */
	private function processPersons($results)
	{
		$persons = [];

		if (!empty($results))
		{
			foreach ($results as $person)
			{
				$documented = Helpers\Subjects::teaches(0, $person['id']);
				$teaches    = Helpers\Events::teaches(0, $person['id']);

				// Nothing to link
				if (!$documented and !$teaches)
				{
					continue;
				}

				$personName = Helpers\Persons::getDefaultName($person['id']);

				$persons[$person['id']]         = [];
				$persons[$person['id']]['text'] = Languages::_('ORGANIZER_TEACHER') . ": {$personName}";

				$links = [];

				if ($documented)
				{
					$links['subjects'] = "?option=com_organizer&view=subjects&personIDs={$person['id']}";
				}

				$overlap = array_intersect(
					$this->authorized,
					Helpers\Persons::getOrganizationIDs($person['id'])
				);

				$isPerson = $this->personID == $person['id'];
				if ($teaches and (count($overlap) or $isPerson))
				{
					$links['schedule'] = "?option=com_organizer&view=schedule_item&personIDs={$person['id']}";
				}

				$persons[$person['id']]['links'] = $links;
			}
		}

		return $persons;
	}

	/**
	 * Retrieves prioritized organization search results
	 *
	 * @return void adds to the results property
	 */
	private function searchOrganizations()
	{
		$eWherray = [];
		$sWherray = [];

		foreach ($this->terms as $term)
		{
			if (is_numeric($term))
			{
				$clause     = "name_de LIKE '$term %' OR name_en LIKE '$term %' ";
				$clause     .= "OR shortName_de LIKE '$term %' OR shortName_en LIKE '$term %'";
				$eWherray[] = $clause;
				$sWherray[] = $clause;
			}
			elseif (strlen($term) < 4)
			{
				$eClause    = "shortName_de LIKE '%$term' OR shortName_en LIKE '%$term'";
				$eWherray[] = $eClause;
				$sClause    = "shortName_de LIKE '%$term%' OR shortName_en LIKE '%$term%'";
				$sWherray[] = $sClause;
			}
			else
			{
				$eClause    = "shortName_de LIKE '%$term' OR shortName_en LIKE '%$term'";
				$eClause    .= " OR name_de LIKE '%$term' OR name_en LIKE '%$term'";
				$eWherray[] = $eClause;
				$sClause    = "shortName_de LIKE '%$term%' OR shortName_en LIKE '%$term%'";
				$sClause    .= " OR name_de LIKE '%$term%' OR name_en LIKE '%$term%'";
				$sWherray[] = $sClause;
			}
		}

		$query = $this->_db->getQuery(true);
		$query->select('cat.id AS categoryID, o.id AS organizationID')
			->from('#__organizer_categories AS cat')
			->innerJoin('#__organizer_associations AS a ON a.categoryID = cat.ID')
			->innerJoin('#__organizer_organizations AS o ON o.id = a.organizationID');

		// Exact
		$this->addInclusiveConditions($query, $eWherray);
		$this->_db->setQuery($query);

		$associations = OrganizerHelper::executeQuery('loadAssocList');
		if (empty($associations))
		{
			return;
		}

		$organizationIDs = [];

		foreach ($associations as $association)
		{
			$organizationIDs[$association['organizationID']] = $association['organizationID'];
		}

		$this->results['exact']['organizations'] = $this->processOrganizations($organizationIDs);

		$programs                             = [];
		$this->results['related']['programs'] = $this->processPrograms($programs, $associations);

		// Strong Related programs will not be displayed => no selection and no secondary processing.
		$query->clear('SELECT');
		$query->clear('where');

		$query->select('DISTINCT d.id');
		$this->addInclusiveConditions($query, $sWherray);
		$this->_db->setQuery($query);
		$organizationIDs = OrganizerHelper::executeQuery('loadColumn', []);

		if (empty($organizationIDs))
		{
			return;
		}

		$this->results['strong']['organizations'] = $this->processOrganizations($organizationIDs);
	}

	/**
	 * Retrieves prioritized pool search results
	 *
	 * @return void adds to the results property
	 */
	private function searchPools()
	{
		foreach ($this->terms as $index => $term)
		{
			if ($index === 0)
			{
				continue;
			}

			/*$epWherray[] = "REPLACE(LCASE(name), '.', '') LIKE '$term'";

			$eClause    = "REPLACE(LCASE(pl.name_de), '.', '') LIKE '$term' ";
			$eClause    .= "OR REPLACE(LCASE(pl.name_en), '.', '') LIKE '$term' ";
			$eClause    .= "OR REPLACE(LCASE(pl.shortName_de), '.', '') LIKE '$term' ";
			$eClause    .= "OR REPLACE(LCASE(pl.shortName_en), '.', '') LIKE '$term' ";
			$eClause    .= "OR REPLACE(LCASE(pl.abbreviation_de), '.', '') LIKE '$term' ";
			$eClause    .= "OR REPLACE(LCASE(pl.abbreviation_en), '.', '') LIKE '$term'";
			$eWherray[] = $eClause;*/

			$clause    = "REPLACE(LCASE(pl.name_de), '.', '') LIKE '%$term%' ";
			$clause    .= "OR REPLACE(LCASE(pl.name_en), '.', '') LIKE '%$term%' ";
			$clause    .= "OR REPLACE(LCASE(pl.shortName_de), '.', '') LIKE '%$term%' ";
			$clause    .= "OR REPLACE(LCASE(pl.shortName_en), '.', '') LIKE '%$term%' ";
			$clause    .= "OR REPLACE(LCASE(pl.abbreviation_de), '.', '') LIKE '%$term%' ";
			$clause    .= "OR REPLACE(LCASE(pl.abbreviation_en), '.', '') LIKE '%$term%'";
			$wherray[] = $clause;
		}

		// Plan programs have to be found in strings => standardized name as extra temp variable for comparison
		$groupQuery = $this->_db->getQuery(true);
		$groupQuery->from('#__organizer_groups');

		$poolQuery = $this->_db->getQuery(true);
		$poolQuery->from('#__organizer_pools AS pl')
			->innerJoin('#__organizer_mappings AS m ON m.poolID = pl.id');

		foreach ($this->programResults as $strength => $programs)
		{
			$pools = [];

			foreach ($programs as $program)
			{
				$groupQuery->clear('SELECT');
				$groupQuery->clear('where');
				$poolQuery->clear('SELECT');
				$poolQuery->clear('where');

				if (!empty($program['categoryID']))
				{
					$groupQuery->select("DISTINCT id, '{$program['name']}' AS program");
					$groupQuery->where("programID = '{$program['categoryID']}'");
					$this->_db->setQuery($groupQuery);

					$groupIDs = OrganizerHelper::executeQuery('loadAssocList');
				}

				if (!empty($groupIDs))
				{
					$this->processPools($pools, $groupIDs, 'plan');
				}

				if (!empty($program['lft']) and !empty($program['rgt']))
				{
					$poolQuery->select("DISTINCT pl.id, '{$program['name']}' AS program");

					if (!empty($wherray))
					{
						$this->addInclusiveConditions($poolQuery, $wherray);
					}

					$poolQuery->where("(m.lft > '{$program['lft']}' AND m.rgt < '{$program['rgt']}')");
					$this->_db->setQuery($poolQuery);

					$poolIDs = OrganizerHelper::executeQuery('loadAssocList');
				}

				if (!empty($poolIDs))
				{
					$this->processPools($pools, $poolIDs, 'real');
				}
			}

			if (!empty($pools))
			{
				$this->results[$strength]['pools'] = $pools;
			}
		}
	}

	/**
	 * Retrieves prioritized program search results
	 *
	 * @return void adds to the results property
	 */
	private function searchPrograms()
	{
		$programResults = $this->programResults;

		foreach ($programResults as $strength => $programs)
		{
			$this->results[$strength]['programs'] = $programs;
		}
	}

	/**
	 * Retrieves prioritized room search results
	 *
	 * @return void adds to the results property
	 */
	private function searchRooms()
	{
		$select = 'r.id , r.name, r.capacity, ';
		$select .= "rt.name_{$this->tag} as type, rt.description_{$this->tag} as description";
		$query  = $this->_db->getQuery(true);
		$query->select($select)
			->from('#__organizer_rooms AS r')
			->leftJoin('#__organizer_roomtypes AS rt ON rt.id = r.roomtypeID')
			->order('r.name ASC');

		// EXACT

		$wherray = [];

		foreach ($this->terms as $term)
		{
			$wherray[] = "r.name LIKE '$term'";
		}

		$this->addInclusiveConditions($query, $wherray);
		$this->_db->setQuery($query);

		$eRooms = OrganizerHelper::executeQuery('loadAssocList');

		$this->results['exact']['rooms'] = $this->processRooms($eRooms);

		// STRONG => has name relevance
		$query->clear('where');

		$buildings = [];
		$capacity  = 0;
		$misc      = [];

		// Strong matches
		foreach ($this->terms as $index => $term)
		{
			// The reserved index for the complete search is irrelevant as such here
			if (count($this->terms) > 1 and $index === 0)
			{
				continue;
			}

			// This could probably be done with one expression, but I don't want to invest the time right now.
			$isBuilding = preg_match("/^[\p{L}}][\d]{1,2}$/", $term, $matches);
			$isFloor    = preg_match("/^[\p{L}}][\d]{1,2}\.[\d]{1,2}\.*$/", $term, $matches);

			if (!empty($isBuilding) or !empty($isFloor))
			{
				$buildings[] = $term;
				continue;
			}

			// Only a number, the only real context for a numerical search term
			$isCapacity = preg_match("/^\d+$/", $term, $matches);

			if (!empty($isCapacity))
			{
				$number = (int) $term;

				// The number most likely denotes a module sequence
				if ($number < 5)
				{
					continue;
				}

				// Bigger numbers will trump smaller ones in the search, so they are superfluous.
				$capacity = $number > $capacity ? (int) $term : $capacity;
				continue;
			}

			// Relevance cannot be determined, if relevant than a non-conforming name
			$misc[] = $term;
		}

		$roomTypeIDs = $this->getRoomtypes($misc, $capacity);
		$typeString  = empty($roomTypeIDs) ? '' : "'" . implode("', '", $roomTypeIDs) . "'";

		if (!empty($misc))
		{
			foreach ($misc as $term)
			{
				$query->where("(r.name LIKE '%$term%')");
			}
		}

		if (!empty($buildings))
		{
			$query->where("(r.name LIKE '" . implode("%' OR r.name LIKE '", $buildings) . "%')");
		}

		$performStrongQuery = (!empty($misc) or !empty($buildings));

		if ($performStrongQuery)
		{
			if (!empty($capacity) and !empty($typeString))
			{
				// Opens main clause and room cap existent
				$query->where("((r.capacity >= '$capacity' OR r.capacity = '0') AND rt.id IN ($typeString))");
			}
			elseif (!empty($capacity))
			{
				$query->where("r.capacity >= '$capacity'");
			}
			elseif (!empty($typeString))
			{
				$query->where("rt.id IN ($typeString)");
			}
			$this->_db->setQuery($query);

			$sRooms = OrganizerHelper::executeQuery('loadAssocList');

			$this->results['strong']['rooms'] = $this->processRooms($sRooms);
		}

		// Related => has type or capacity relevance

		$query->clear('where');

		if (!empty($capacity) and !empty($typeString))
		{
			// Opens main clause and room cap existent
			$query->where("((r.capacity >= '$capacity' OR r.capacity = '0') AND rt.id IN ($typeString))");
		}
		elseif (!empty($capacity))
		{
			$query->where("r.capacity >= '$capacity'");
		}
		elseif (!empty($typeString))
		{
			$query->where("rt.id IN ($typeString)");
		}

		$performRelatedQuery = (!empty($capacity) or !empty($typeString));

		if ($performRelatedQuery)
		{
			$this->_db->setQuery($query);

			$rRooms = OrganizerHelper::executeQuery('loadAssocList');

			$this->results['related']['rooms'] = $this->processRooms($rRooms);
		}
	}

	/**
	 * Retrieves prioritized subject/lesson search results
	 *
	 * @return void adds to the results property
	 */
	private function searchSubjects()
	{
		$terms = $this->terms;

		foreach ($terms as $index => $term)
		{
			$short     = strlen($term) < 3;
			$isRoman   = preg_match("/^([ix|iv|v]{1}|[i]+)$/", $term, $matches);
			$isNumeric = is_numeric($term);

			if ($short and !($isRoman or $isNumeric))
			{
				unset($terms[$index]);
			}
		}

		if (empty($terms))
		{
			return;
		}

		$termCount = count($terms);

		// A course does not necessarily have subject documentation
		$courseQuery = $this->_db->getQuery(true);
		$courseQuery->select('DISTINCT co.id AS courseID, s.id AS sID')
			->from('#__organizer_course AS co')
			->innerJoin('#__organizer_lesson_courses AS lcrs ON lcrs.courseID = co.id')
			->innerJoin('#__organizer_lessons AS l ON l.id = lcrs.lessonID')
			->leftJoin('#__organizer_subject_mappings AS sm ON sm.courseID = co.id')
			->leftJoin('#__organizer_subjects AS s ON s.id = sm.subjectID');

		// Subject documentation does not necessarily have planned lesson instances
		$subjectQuery = $this->_db->getQuery(true);
		$subjectQuery->select('DISTINCT s.id AS sID, co.id AS courseID')
			->from('#__organizer_subjects AS s')
			->leftJoin('#__organizer_subject_mappings AS sm ON sm.subjectID = s.id')
			->leftJoin('#__organizer_courses AS co ON co.id = sm.courseID')
			->leftJoin('#__organizer_lesson_courses AS lcrs ON lcrs.courseID = co.id')
			->leftJoin('#__organizer_lessons AS l ON l.id = lcrs.lessonID');

		// EXACT => exact (case independent) match for the search term
		$initialTerm = current($terms);

		$courseClause = "(co.name LIKE '$initialTerm' OR co.subjectNo LIKE '$initialTerm'";

		$sClause = "(s.code LIKE '$initialTerm' OR s.name_de LIKE '$initialTerm' OR ";
		$sClause .= "s.name_en LIKE '$initialTerm' OR s.shortName_de LIKE '$initialTerm' OR ";
		$sClause .= "s.shortName_en LIKE '$initialTerm' OR s.abbreviation_de LIKE '$initialTerm' OR ";
		$sClause .= "s.abbreviation_en LIKE '$initialTerm'";

		foreach ($terms as $term)
		{
			$courseClause .= " OR co.subjectNo LIKE '$term'";
			$sClause      .= "OR s.code LIKE '$term'";
		}

		$courseClause .= ')';
		$sClause      .= ')';

		$this->filterLessons($courseQuery);
		$courseQuery->where($courseClause);

		$this->filterLessons($subjectQuery);
		$subjectQuery->where($sClause);

		$this->_db->setQuery($courseQuery);
		$courses = OrganizerHelper::executeQuery('loadAssocList', [], 'courseID');
		$this->_db->setQuery($subjectQuery);
		$subjects = OrganizerHelper::executeQuery('loadAssocList', [], 'sID');

		$this->results['exact']['subjects'] = $this->processSubjects($subjects, $courses);

		if (empty($terms))
		{
			return;
		}

		// STRONG => exact match on at least one term
		$courseQuery->clear('where');
		$subjectQuery->clear('where');
		$nameDEArray = [];
		$nameENArray = [];

		foreach ($terms as $index => $term)
		{
			$asNumber = false;

			preg_match("/^([ix|iv|v]{1}|[i]+)$/", $term, $matches);

			if (!empty($matches) or is_numeric($term))
			{
				$asNumber = true;
			}

			// Direct comparison delivers false positives because of how like evaluates integers. Space necessary.
			if ($asNumber)
			{
				$courseQuery->where("co.name LIKE '% $term'");
				$nameDEArray[] = "s.name_de LIKE '% $term'";
				$nameENArray[] = "s.name_en LIKE '% $term'";
			}
			else
			{
				$courseQuery->where("co.name LIKE '%$term%'");
				$nameDEArray[] = "s.name_de LIKE '%$term%'";
				$nameENArray[] = "s.name_en LIKE '%$term%'";
			}
		}

		$this->filterLessons($courseQuery);
		$this->_db->setQuery($courseQuery);

		$nameDEClause = '(' . implode(' AND ', $nameDEArray) . ')';
		$nameENClause = '(' . implode(' AND ', $nameENArray) . ')';
		$subjectQuery->where("($nameDEClause OR $nameENClause)");
		$this->filterLessons($subjectQuery);

		$this->_db->setQuery($courseQuery);
		$courses = OrganizerHelper::executeQuery('loadAssocList', [], 'courseID');
		$this->_db->setQuery($subjectQuery);
		$subjects = OrganizerHelper::executeQuery('loadAssocList', [], 'sID');

		$this->results['strong']['subjects'] = $this->processSubjects($subjects, $courses);

		// Good
		$courseQuery->clear('where');
		$subjectQuery->clear('where');

		$sWherray  = [];
		$coWherray = [];

		foreach ($terms as $index => $term)
		{
			$asNumber = false;

			preg_match("/^([ix|iv|v]{1}|[i]+)$/", $term, $matches);

			if (!empty($matches) or is_numeric($term))
			{
				$asNumber = true;
			}

			// Direct comparison delivers false positives because of how like evaluates integers. Space necessary.
			if ($asNumber)
			{
				$sClause     = "s.name_de LIKE '% $term' OR s.name_en LIKE '% $term' OR ";
				$sClause     .= "s.shortName_de REGEXP '%$term' OR s.shortName_en REGEXP '%$term' OR ";
				$sClause     .= "s.abbreviation_de REGEXP '%$term' OR s.abbreviation_en REGEXP '%$term'";
				$sWherray[]  = $sClause;
				$coWherray[] = "co.name LIKE '% $term' OR co.subjectNo REGEXP '%$term%'";
			}
			else
			{
				$sClause     = "s.name_de LIKE '%$term%' OR s.name_en LIKE '%$term%' OR ";
				$sClause     .= "s.shortName_de LIKE '%$term%' OR s.shortName_en LIKE '%$term%' OR ";
				$sClause     .= "s.abbreviation_de LIKE '%$term%' OR s.abbreviation_en LIKE '%$term%'";
				$sWherray[]  = $sClause;
				$coWherray[] = "co.name REGEXP '%$term%' OR co.subjectNo REGEXP '%$term%'";
			}
		}

		// There were only numeric values in the search so the conditions are empty => don't execute queries
		if (empty($coWherray) and empty($sWherray))
		{
			return;
		}

		$this->filterLessons($courseQuery);
		$this->addInclusiveConditions($courseQuery, $coWherray);

		$this->filterLessons($subjectQuery);
		$this->addInclusiveConditions($subjectQuery, $sWherray);

		if (!empty($coWherray))
		{
			$this->_db->setQuery($courseQuery);
			$courses = OrganizerHelper::executeQuery('loadAssocList', [], 'courseID');
		}
		else
		{
			$courses = null;
		}

		if (!empty($sWherray))
		{
			$this->_db->setQuery($subjectQuery);
			$subjects = OrganizerHelper::executeQuery('loadAssocList', [], 'sID');
		}
		else
		{
			$subjects = null;
		}

		$this->results['good']['subjects'] = $this->processSubjects($subjects, $courses);

		// Mentioned Looks for mention of the terms in the differing text fields of the module descriptions.

		$subjectQuery->clear('where');
		$courses = null;

		$sWherray = [];

		foreach ($terms as $index => $term)
		{
			// Numeric values deliver true for everything
			if (count($this->terms) > 1 and $index === 0)
			{
				continue;
			}

			$sClause    = "s.content_de LIKE '% $term%' OR s.content_en LIKE '% $term%' OR ";
			$sClause    .= "s.description_de LIKE '% $term %' OR s.description_en LIKE '% $term%' OR ";
			$sClause    .= "s.objective_de LIKE '% $term%' OR s.objective_en LIKE '% $term%'";
			$sWherray[] = $sClause;
		}

		// There were only numeric values in the search so the conditions are empty => don't execute queries
		if (empty($sWherray))
		{
			return;
		}

		$this->filterLessons($subjectQuery);
		$this->addInclusiveConditions($subjectQuery, $sWherray);
		$this->_db->setQuery($subjectQuery);

		$subjects = OrganizerHelper::executeQuery('loadAssocList', [], 'sID');

		$this->results['mentioned']['subjects'] = $this->processSubjects($subjects, $courses);

		// Related
		$courseQuery->clear('where');
		$subjectQuery->clear('where');

		$courseQuery->innerJoin('#__organizer_lesson_persons AS lt ON lt.lessonCourseID = lcrs.id')
			->innerJoin('#__organizer_persons AS t ON t.id = lt.personID');

		$subjectQuery->innerJoin('#__organizer_subject_persons AS st ON st.subjectID = s.id')
			->innerJoin('#__organizer_persons AS t ON t.id = st.personID');

		if ($termCount == 1)
		{
			$courseQuery->where("t.surname LIKE '%$initialTerm%'");
			$subjectQuery->where("t.surname LIKE '%$initialTerm%'");
		}
		else
		{
			$wherray    = [];
			$innerTerms = $terms;

			foreach ($terms as $outerTerm)
			{
				foreach ($terms as $iKey => $innerTerm)
				{
					if ($outerTerm == $innerTerm)
					{
						unset($innerTerms[$iKey]);
						continue;
					}

					// lnf/fnf
					$wherray[] = "(t.surname LIKE '%$outerTerm%' AND t.forename LIKE '%$innerTerm%')";
					$wherray[] = "(t.surname LIKE '%$innerTerm%' AND t.forename LIKE '%$outerTerm%')";
				}
			}

			$this->addInclusiveConditions($courseQuery, $wherray);
			$this->addInclusiveConditions($subjectQuery, $wherray);
		}

		$this->_db->setQuery($courseQuery);
		$courses = OrganizerHelper::executeQuery('loadAssocList', [], 'courseID');
		$this->_db->setQuery($subjectQuery);
		$subjects = OrganizerHelper::executeQuery('loadAssocList', [], 'sID');

		$this->results['related']['subjects'] = $this->processSubjects($subjects, $courses);
	}

	/**
	 * Retrieves prioritized person search results
	 *
	 * @return void adds to the results property
	 */
	private function searchPersons()
	{
		$terms = $this->terms;

		foreach ($terms as $index => $term)
		{
			if (strlen($term) < 2)
			{
				unset($terms[$index]);
			}
		}

		$termCount = count($terms);

		if ($termCount == 0)
		{
			return;
		}

		$query = $this->_db->getQuery(true);
		$query->select('id , surname, forename, title')
			->from('#__organizer_persons')
			->order('forename, surname ASC');

		// EXACT => requires a forename and surname match

		if ($termCount >= 2)
		{
			$wherray    = [];
			$innerTerms = $terms;

			foreach ($terms as $outerTerm)
			{
				foreach ($innerTerms as $iKey => $innerTerm)
				{
					if ($outerTerm == $innerTerm)
					{
						unset($innerTerms[$iKey]);
						continue;
					}

					// lnf/fnf
					$wherray[] = "(surname LIKE '%$outerTerm%' AND forename LIKE '%$innerTerm%')";
					$wherray[] = "(surname LIKE '%$innerTerm%' AND forename LIKE '%$outerTerm%')";
				}
			}

			$this->addInclusiveConditions($query, $wherray);
			$this->_db->setQuery($query);

			$ePersons = OrganizerHelper::executeQuery('loadAssocList');

			$this->results['exact']['persons'] = $this->processPersons($ePersons);
		}

		// Strong

		$query->clear('where');
		$wherray = [];

		foreach ($terms as $term)
		{
			// lnf/fnf
			$wherray[] = "surname LIKE '$term'";
		}

		$this->addInclusiveConditions($query, $wherray);
		$this->_db->setQuery($query);

		$sPersons = OrganizerHelper::executeQuery('loadAssocList');

		$this->results['strong']['persons'] = $this->processPersons($sPersons);

		// Good

		$query->clear('where');
		$wherray = [];

		foreach ($terms as $term)
		{
			// lnf/fnf
			$wherray[] = "surname LIKE '%$term%' OR forename LIKE '%$term%'";
		}

		$this->addInclusiveConditions($query, $wherray);
		$this->_db->setQuery($query);

		$gPersons = OrganizerHelper::executeQuery('loadAssocList');

		$this->results['good']['persons'] = $this->processPersons($gPersons);
	}

	/**
	 * Finds programs which can be associated with the terms. Possible return strengths exact, strong and good.
	 *
	 * @return void set the program results property
	 */
	private function setPrograms()
	{
		// Clone for editing.
		$terms     = $this->terms;
		$firstTerm = $terms[0];
		unset($terms[0]);

		foreach ($terms as $index => $term)
		{
			$terms[$index] = str_replace('.', '', $term);
		}

		$programResults = [];
		$degrees        = $this->getDegrees($terms);

		$ePWherray  = [];
		$sPWherray  = [];
		$ePPWherray = [];
		$sPPWherray = [];

		foreach ($terms as $term)
		{
			$ePWherray[] = "p.name_de LIKE '$term$' OR p.name_en LIKE '$term%'";
			$sPWherray[] = "p.name_de LIKE '%$term%' OR p.name_en LIKE '%$term%'";

			// Plan program degrees have to be resolved by string comparison
			$ePPWherray[] = "REPLACE(LCASE(cat.name), '.', '') LIKE '$term%'";
			$sPPWherray[] = "REPLACE(LCASE(cat.name), '.', '') LIKE '%$term%'";
		}

		$programQuery = $this->_db->getQuery(true);
		$programQuery->select("p.id, name_{$this->tag} AS name, degreeID, cat.id AS categoryID, lft, rgt")
			->from('#__organizer_programs AS p')
			->innerJoin('#__organizer_mappings AS m ON m.programID = p.ID')
			->leftJoin('#__organizer_categories AS cat ON cat.id = p.categoryID');

		// Plan programs have to be found in strings => standardized name as extra temp variable for comparison
		$categoryQuery = $this->_db->getQuery(true);
		$categoryQuery->select("p.id, name_{$this->tag} AS name, degreeID, cat.id AS categoryID, lft, rgt")
			->from('#__organizer_categories AS cat')
			->leftJoin('#__organizer_programs AS p ON p.categoryID = cat.ID')
			->leftJoin('#__organizer_mappings AS m ON m.programID = p.ID');

		// Exact => program name and degree
		if (!empty($degrees['exact']))
		{
			$degreeIDs = array_keys($degrees['exact']);
			$programQuery->where("p.degreeID IN ('" . implode("','", $degreeIDs) . "')");
			$this->addInclusiveConditions($programQuery, $ePWherray);

			$degreeWherray = [];
			$this->addInclusiveConditions($categoryQuery, $ePPWherray);

			foreach ($degrees['exact'] as $degree)
			{
				$degreeWherray[] = "REPLACE(LCASE(cat.name), '.', '') LIKE '%{$degree['stdAbbr']}%'";
			}

			$this->addInclusiveConditions($categoryQuery, $degreeWherray);

			$this->_db->setQuery($categoryQuery);
			$categories = OrganizerHelper::executeQuery('loadAssocList');
			$this->_db->setQuery($programQuery);
			$programs = OrganizerHelper::executeQuery('loadAssocList');

			$programResults['exact'] = $this->processPrograms($programs, $categories);
		}

		// Strong => full program name
		$wherray   = [];
		$wherray[] = "(name LIKE '%$firstTerm%')";

		$this->addInclusiveConditions($categoryQuery, $wherray);
		$this->_db->setQuery($categoryQuery);
		$sGroups = OrganizerHelper::executeQuery('loadAssocList');

		$this->addInclusiveConditions($programQuery, $wherray);
		$this->_db->setQuery($programQuery);
		$sPrograms = OrganizerHelper::executeQuery('loadAssocList');

		$programResults['strong'] = $this->processPrograms($sPrograms, $sGroups);

		// Good => parts of the program name
		$wherray = [];
		foreach ($this->terms as $term)
		{
			$wherray[] = "(name LIKE '%$term%')";
		}

		$this->addInclusiveConditions($categoryQuery, $wherray);
		$this->_db->setQuery($categoryQuery);
		$gCategories = OrganizerHelper::executeQuery('loadAssocList');

		$this->addInclusiveConditions($programQuery, $wherray);
		$this->_db->setQuery($programQuery);
		$gPrograms = OrganizerHelper::executeQuery('loadAssocList');

		$programResults['good'] = $this->processPrograms($gPrograms, $gCategories);
		$this->programResults   = $programResults;
	}

	/**
	 * Set the search terms.
	 *
	 * @param   string  $rawSearch  the raw string from the request
	 *
	 * @return void sets the $terms property
	 */
	private function setTerms($rawSearch)
	{
		$prohibited     = ['\\', '\'', '"', '%', '_', '(', ')'];
		$safeSearch     = str_replace($prohibited, '', $rawSearch);
		$standardSearch = strtolower($safeSearch);

		// Remove English and German ordinals
		$standardSearch = preg_replace('/ (.*[1-9])(?:\.|st|nd|rd|th)(.*)/', "$1$2", $standardSearch);

		// Filter out semester terms so that both the number and the word semster are one term.
		preg_match_all('/[1-9] (semester|sem)/', $standardSearch, $semesters);

		$this->terms = [];

		// Remove the semester terms from the search and add them to the terms
		if (!empty($semesters))
		{
			foreach ($semesters[0] as $semester)
			{
				$this->terms[]  = $semester;
				$standardSearch = str_replace($semester, '', $standardSearch);
			}
		}

		// Add the original search to the beginning of the array
		array_unshift($this->terms, $standardSearch);

		$remainingTerms = explode(' ', $standardSearch);

		$whiteNoise = [
			'der',
			'die',
			'das',
			'den',
			'dem',
			'des',
			'einer',
			'eine',
			'ein',
			'einen',
			'einem',
			'eines',
			'und',
			'the',
			'a',
			'and',
			'oder',
			'or',
			'aus',
			'von',
			'of',
			'from',
		];

		foreach ($remainingTerms as $term)
		{
			$isWhiteNoise   = in_array($term, $whiteNoise);
			$isSingleLetter = (!is_numeric($term) and strlen($term) < 2);

			if ($isWhiteNoise or $isSingleLetter)
			{
				continue;
			}

			$this->terms[] = $term;
		}

		// Remove non-unique terms to prevent bloated queries
		$this->terms = array_unique($this->terms);
	}

	/**
	 * Function used as a call back for sorting results by their names. (Callable)
	 *
	 * @param   array  $itemOne  the first item
	 * @param   array  $itemTwo  the second item
	 *
	 * @return bool true if the text for the first item should come after the second item, otherwise false
	 *
	 * @SuppressWarnings(PMD.UnusedPrivateMethod)
	 */
	private function sortItems($itemOne, $itemTwo)
	{
		return $itemOne['text'] > $itemTwo['text'];
	}
}
