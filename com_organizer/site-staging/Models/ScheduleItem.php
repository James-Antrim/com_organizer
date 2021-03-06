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

use Organizer\Helpers;
use Organizer\Helpers\Input;

/**
 * Class retrieves information for use in a schedule display form.
 */
class ScheduleItem extends BaseModel
{
	public $grids = [];

	public $organizations;

	public $displayName;

	public $params;

	/**
	 * Schedule constructor.
	 *
	 * @param   array  $config  options
	 */
	public function __construct(array $config)
	{
		parent::__construct($config);

		$params         = Input::getParams();
		$organizationID = Input::getFilterID('organization');

		$this->params                   = [];
		$this->params['organizationID'] = $organizationID;
		$this->params['showCategories'] = Input::getInt('showCategories', $params->get('showCategories', 1));
		$this->params['showGroups']     = Input::getInt('showGroups', $params->get('showGroups', 1));
		$this->params['showRooms']      = Input::getInt('showRooms', $params->get('showRooms', 1));
		$this->params['showRoomtypes']  = Input::getInt('showRoomtypes', $params->get('showRoomtypes', 1));

		$showPersonsParam            = Input::getInt('showPersons', $params->get('showPersons', 1));
		$privilegedAccess            = Helpers\Can::view('organization', $organizationID);
		$personEntryExists           = Helpers\Persons::getIDByUserID();
		$showPersons                 = (($privilegedAccess or $personEntryExists) and $showPersonsParam);
		$this->params['showPersons'] = $showPersons;

		$this->params['delta'] = Input::getInt('delta', $params->get('delta', 5));

		$defaultEnabled                    = Input::getInt('showOrganizations', $params->get('showOrganizations', 1));
		$this->params['showOrganizations'] = $organizationID ? 0 : $defaultEnabled;

		// Default title: menu > organization
		$displayName                 = ($params->get('show_page_heading') and $params->get('page_heading')) ?
			$params->get('page_heading') : Helpers\Organizations::getName($this->params['organizationID']);
		$this->displayName           = $displayName;
		$this->params['displayName'] = $displayName;

		// Planned resources
		if ($this->setResourceArray('course'))
		{
			$this->displayName           = Helpers\Courses::getName($this->params['courseIDs']);
			$this->params['displayName'] = $this->displayName;

			return;
		}

		if ($this->setResourceArray('event'))
		{
			$this->displayName           = Helpers\Events::getName($this->params['eventIDs']);
			$this->params['displayName'] = $this->displayName;

			return;
		}

		if ($this->params['showGroups'] and $this->setResourceArray('group', false))
		{
			if (count($this->params['groupIDs']) === 1)
			{
				$this->displayName           = Helpers\Groups::getFullName($this->params['groupIDs'][0]);
				$this->params['displayName'] = $this->displayName;
			}
			else
			{
				$this->params['showGroups'] = 1;
			}

			return;
		}

		if ($this->params['showPersons'] and $this->setResourceArray('person'))
		{
			$this->displayName           = Helpers\Persons::getDefaultName($this->params['personIDs']);
			$this->params['displayName'] = $this->displayName;

			return;
		}

		if ($this->params['showRooms'] and $this->setResourceArray('room', false))
		{
			if (count($this->params['roomIDs']) === 1)
			{
				$this->displayName           = Helpers\Rooms::getName($this->params['roomIDs'][0]);
				$this->params['displayName'] = $this->displayName;
			}
			else
			{
				$this->params['showRooms'] = 1;
			}

			return;
		}

		if ($this->setResourceArray('subject'))
		{
			$this->displayName           = Helpers\Subjects::getName($this->params['subjectIDs']);
			$this->params['displayName'] = $this->displayName;

			return;
		}

		// Planned resource categorizations
		if ($this->params['showCategories'] and $this->setResourceArray('category', false))
		{
			if (count($this->params['categoryIDs']) === 1)
			{
				$this->displayName           = Helpers\Categories::getName($this->params['categoryIDs'][0]);
				$this->params['displayName'] = $this->displayName;
			}
			else
			{
				$this->params['showCategories'] = 1;
			}

			$this->params['showGroups'] = 1;

			return;
		}

		if ($this->params['showRoomtypes'] and $this->setResourceArray('roomtype'))
		{
			$this->displayName           = Helpers\Roomtypes::getName($this->params['roomtypeIDs']);
			$this->params['displayName'] = $this->displayName;

			$this->params['showRooms'] = 1;

			return;
		}
	}

	/**
	 * Checks for ids for a given resource type and sets them in the parameters
	 *
	 * @param   string  $resourceName  the name of the resource type
	 * @param   bool    $singular      true if only one resource is allowed
	 *
	 * @return bool true if resources were set, otherwise false
	 */
	private function setResourceArray($resourceName, $singular = true)
	{
		$resourceIDs = $singular ? Input::getFilterID($resourceName) : Input::getFilterIDs($resourceName);
		if ($resourceIDs)
		{
			$this->params["{$resourceName}IDs"] = $resourceIDs;

			// Disable all, reenable relevant on return
			$this->params['showCategories']    = 0;
			$this->params['showOrganizations'] = 0;
			$this->params['showGroups']        = 0;
			$this->params['showPersons']       = 0;
			$this->params['showRooms']         = 0;
			$this->params['showRoomtypes']     = 0;

			return true;
		}

		return false;
	}
}
