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
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\MVC\Model\ListModel as ParentModel;
use Organizer\Helpers;
use stdClass;

/**
 * Class provides a standardized framework for the display of listed resources.
 */
abstract class ListModel extends ParentModel
{
	use Named;

	const ALL = '', BACKEND = true, FRONTEND = false, NONE = -1;

	protected $clientContext;

	protected $defaultOrdering = 'name';

	protected $defaultDirection = 'ASC';

	protected $defaultLimit = null;

	protected $option = 'com_organizer';

	/**
	 * Constructor.
	 *
	 * @param   array  $config  An optional associative array of configuration settings.
	 */
	public function __construct($config = [])
	{
		parent::__construct($config);

		$app                  = Helpers\OrganizerHelper::getApplication();
		$this->clientContext  = $app->isClient('administrator');
		$this->filterFormName = strtolower(Helpers\OrganizerHelper::getClass($this));

		if (!is_int($this->defaultLimit))
		{
			$this->defaultLimit = $app->get('list_limit', 50);
		}
	}

	/**
	 * Filters out form inputs which should not be displayed due to menu settings.
	 *
	 * @param   Form  $form  the form to be filtered
	 *
	 * @return void modifies $form
	 */
	protected function filterFilterForm(&$form)
	{
		if ($this->clientContext === self::BACKEND)
		{
			$form->removeField('languageTag', 'list');

			return;
		}
	}

	/**
	 * Method to get the total number of items for the data set. Joomla erases critical fields for complex data sets.
	 * This method fixes the erroneous output of undesired duplicate entries.
	 *
	 * @param   string  $idColumn  the main id column of the list query
	 *
	 * @return integer  The total number of items available in the data set.
	 */
	public function getTotal($idColumn = null)
	{
		if (empty($idColumn))
		{
			return parent::getTotal();
		}

		// Get a storage key.
		$store = $this->getStoreId('getTotal');

		// Try to load the data from internal storage.
		if (isset($this->cache[$store]))
		{
			return $this->cache[$store];
		}

		// Load the total.
		$query = $this->getListQuery();
		$query->clear('select')->clear('limit')->clear('offset')->clear('order');
		$query->select("COUNT(DISTINCT ($idColumn))");
		$this->_db->setQuery($query);

		$total = (int) Helpers\OrganizerHelper::executeQuery('loadResult', 0);

		// Add the total to the internal cache.
		$this->cache[$store] = $total;

		return $this->cache[$store];
	}

	/**
	 * Method to get a form object.
	 *
	 * @param   string          $name     The name of the form.
	 * @param   string          $source   The form source. Can be XML string if file flag is set to false.
	 * @param   array           $options  Optional array of options for the form creation.
	 * @param   boolean         $clear    Optional argument to force load a new form.
	 * @param   string|boolean  $xpath    An optional xpath to search for the fields.
	 *
	 * @return  Form|boolean  Form object on success, False on error.
	 */
	protected function loadForm($name, $source = null, $options = array(), $clear = false, $xpath = false)
	{
		Form::addFormPath(JPATH_COMPONENT_SITE . '/Forms');
		Form::addFieldPath(JPATH_COMPONENT_SITE . '/Fields');
		$form = parent::loadForm($name, $source, $options, $clear, $xpath);
		$this->filterFilterForm($form);

		return $form;
	}

	/**
	 * Method to get the data that should be injected in the form.
	 *
	 * @return mixed  The data for the form.
	 */
	protected function loadFormData()
	{
		// Check the session for previously entered form data.
		$data = Helpers\OrganizerHelper::getApplication()->getUserState($this->context, new stdClass);

		// Pre-create the list options
		if (!property_exists($data, 'list'))
		{
			$data->list = [];
		}

		if (!property_exists($data, 'filter'))
		{
			$data->filter = [];
		}

		foreach ((array) $this->state as $property => $value)
		{
			if (strpos($property, 'list.') === 0)
			{
				$listProperty              = substr($property, 5);
				$data->list[$listProperty] = $value;
			}
			elseif (strpos($property, 'filter.') === 0)
			{
				$filterProperty                = substr($property, 7);
				$data->filter[$filterProperty] = $value;
			}
		}

		return $data;
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
		$app = Helpers\OrganizerHelper::getApplication();

		// Receive & set filters
		$filters = $app->getUserStateFromRequest($this->context . '.filter', 'filter', [], 'array');
		foreach ($filters as $input => $value)
		{
			$this->setState('filter.' . $input, $value);
		}

		$list = $app->getUserStateFromRequest($this->context . '.list', 'list', [], 'array');
		foreach ($list as $input => $value)
		{
			$this->setState("list.$input", $value);
		}

		$direction    = 'ASC';
		$fullOrdering = "{$this->defaultOrdering} ASC";
		$ordering     = $this->defaultOrdering;

		if (!empty($list['fullordering']) and strpos($list['fullordering'], 'null') === false)
		{
			$pieces          = explode(' ', $list['fullordering']);
			$validDirections = ['ASC', 'DESC', ''];

			switch (count($pieces))
			{
				case 1:
					if (in_array($pieces[0], $validDirections))
					{
						$direction    = empty($pieces[0]) ? 'ASC' : $pieces[0];
						$fullOrdering = "{$this->defaultDirection} $direction";
						$ordering     = $this->defaultDirection;
						break;
					}

					$direction    = $pieces[0];
					$fullOrdering = "$pieces[0] ASC";
					$ordering     = 'ASC';
					break;
				case 2:
					$direction    = !in_array($pieces[1], $validDirections) ? 'ASC' : $pieces[1];
					$ordering     = $pieces[0];
					$fullOrdering = "$ordering $direction";
					break;
			}
		}

		$this->setState('list.fullordering', $fullOrdering);
		$this->setState('list.ordering', $ordering);
		$this->setState('list.direction', $direction);

		$limit = (isset($list['limit']) && is_numeric($list['limit'])) ? $list['limit'] : $this->defaultLimit;
		$this->setState('list.limit', $limit);

		$value = $this->getUserStateFromRequest('limitstart', 'limitstart', 0);
		$start = ($limit != 0 ? (floor($value / $limit) * $limit) : 0);
		$this->setState('list.start', $start);
	}

	/**
	 * Sets a campus filter for a given resource.
	 *
	 * @param   JDatabaseQuery  $query  the query to modify
	 * @param   string          $alias  the alias for the linking table
	 */
	public function setCampusFilter($query, $alias)
	{
		$campusID = $this->state->get('filter.campusID');
		if (empty($campusID))
		{
			return;
		}

		if ($campusID === '-1')
		{
			$query->leftJoin("#__organizer_campuses AS campusAlias ON campusAlias.id = $alias.campusID")
				->where("campusAlias.id IS NULL");

			return;
		}

		$query->innerJoin("#__organizer_campuses AS campusAlias ON campusAlias.id = $alias.campusID")
			->where("(campusAlias.id = $campusID OR campusAlias.parentID = $campusID)");
	}

	/**
	 * Adds a date status filter for a given resource.
	 *
	 * @param   JDatabaseQuery  $query   the query to modify
	 * @param   string          $status  name of the field in filter
	 * @param   string          $start   the name of the column containing the resource start date
	 * @param   string          $end     the name of the column containing the resource end date
	 */
	public function setDateStatusFilter($query, $status, $start, $end)
	{
		$value = $this->state->get('filter.' . $status);

		switch ($value)
		{
			case '1' :
				$query->where($end . " < CURDATE()");
				break;
			case '2' :
				$query->where($start . " > CURDATE()");
				break;
			case '3' :
				$query->where("CURDATE() BETWEEN $start AND $end");
				break;
		}
	}

	/**
	 * Provides a default method for setting filters based on id/unique values
	 *
	 * @param   JDatabaseQuery  $query       the query to modify
	 * @param   string          $idColumn    the id column in the table
	 * @param   string          $filterName  the filter name to look for the id in
	 *
	 * @return void
	 */
	protected function setIDFilter($query, $idColumn, $filterName)
	{
		$value = $this->state->get($filterName, '');
		if ($value === '')
		{
			return;
		}

		/**
		 * Special value reserved for empty filtering. Since an empty is dependent upon the column default, we must
		 * check against multiple 'empty' values. Here we check against empty string and null. Should this need to
		 * be extended we could maybe add a parameter for it later.
		 */
		if ($value == '-1')
		{
			$query->where("$idColumn IS NULL");

			return;
		}

		// IDs are unique and therefore mutually exclusive => one is enough!
		$query->where("$idColumn = $value");

		return;
	}

	/**
	 * Provides a default method for setting the list ordering
	 *
	 * @param   JDatabaseQuery  $query  the query to modify
	 *
	 * @return void
	 */
	protected function setOrdering($query)
	{
		$defaultOrdering = "{$this->defaultOrdering} {$this->defaultDirection}";
		$session         = Factory::getSession();
		$listOrdering    = $this->state->get('list.fullordering', $defaultOrdering);

		if (strpos($listOrdering, 'null') !== false)
		{
			$sessionOrdering = $session->get('ordering', '');
			if (empty($sessionOrdering))
			{
				$session->set($this->context . '.ordering', $defaultOrdering);
				$query->order($defaultOrdering);

				return;
			}
		}

		$query->order($listOrdering);
	}

	/**
	 * Sets an organization filter for the given resource.
	 *
	 * @param   JDatabaseQuery  $query    the query to modify
	 * @param   string          $context  the resource context from which this function was called
	 * @param   string          $alias    the alias of the table onto which the organizations table will be joined as
	 *                                    needed
	 *
	 * @return void
	 */
	protected function setOrganizationFilter($query, $context, $alias)
	{
		$authorizedOrgIDs = $this->clientContext === self::BACKEND ?
			Helpers\Can::documentTheseOrganizations() : Helpers\Organizations::getIDs();
		$organizationID   = $this->state->get('filter.organizationID', 0);

		if (!$authorizedOrgIDs or !$organizationID)
		{
			return;
		}

		$joinStatement = "#__organizer_associations AS a on a.{$context}ID = $alias.id";

		if ($organizationID == '-1')
		{
			$query->leftJoin($joinStatement)->where('a.organizationID IS NULL');

			return;
		}

		$query->innerJoin($joinStatement);

		if (in_array($organizationID, $authorizedOrgIDs))
		{
			$query->where("a.organizationID = $organizationID");

			return;
		}

		$query->where('(a.organizationID IN (' . implode(',', $authorizedOrgIDs) . ') OR a.organizationID IS NULL)');

		return;

	}

	/**
	 * Sets the search filter for the query
	 *
	 * @param   JDatabaseQuery  $query        the query to modify
	 * @param   array           $columnNames  the column names to use in the search
	 *
	 * @return void
	 */
	protected function setSearchFilter($query, $columnNames)
	{
		$userInput = $this->state->get('filter.search', '');
		if (empty($userInput))
		{
			return;
		}
		$search  = '%' . $this->_db->escape($userInput, true) . '%';
		$wherray = [];
		foreach ($columnNames as $name)
		{
			$wherray[] = "$name LIKE '$search'";
		}
		$where = implode(' OR ', $wherray);
		$query->where("($where)");
	}

	/**
	 * Provides a default method for setting filters for non-unique values
	 *
	 * @param   JDatabaseQuery  $query         the query to modify
	 * @param   array           $queryColumns  the filter names. names should be synonymous with db column names.
	 *
	 * @return void
	 */
	protected function setValueFilters($query, $queryColumns)
	{
		$app     = Helpers\OrganizerHelper::getApplication();
		$filters = $app->getUserStateFromRequest($this->context . '.filter', 'filter', [], 'array');
		$lists   = $app->getUserStateFromRequest($this->context . '.list', 'list', [], 'array');

		// The view level filters
		foreach ($queryColumns as $column)
		{
			$filterName = strpos($column, '.') === false ? $column : explode('.', $column)[1];

			if (array_key_exists($filterName, $filters))
			{
				$value = $this->state->get("filter.$filterName");
			}
			elseif (array_key_exists($filterName, $lists))
			{
				$value = $this->state->get("list.$filterName");
			}
			else
			{
				continue;
			}

			if ($value === '')
			{
				continue;
			}

			/**
			 * Special value reserved for empty filtering. Since an empty is dependent upon the column default, we must
			 * check against multiple 'empty' values. Here we check against empty string and null. Should this need to
			 * be extended we could maybe add a parameter for it later.
			 */
			if ($value == '-1')
			{
				$query->where("( $column = '' OR $column IS NULL )");
				continue;
			}

			$value = is_numeric($value) ? $value : "'$value'";
			$query->where("$column = $value");
		}
	}
}
