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

use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Organizer\Helpers;
use Organizer\Tables;

/**
 * Class retrieves information about daily events for display on monitors.
 */
class RoomDisplay extends BaseModel
{
	const SCHEDULE = 1;

	const ALTERNATING = 2;

	const CONTENT = 3;

	public $blocks = [];

	private $grid;

	public $monitorID = null;

	public $params = [];

	public $roomID = null;

	public $roomName = '';

	/**
	 * Constructor
	 */
	public function __construct()
	{
		parent::__construct();
		$this->setRoomData();
		$this->ensureComponentTemplate();
		$this->setDisplayParams();
		$this->setGrid();
		$this->getDay();
	}

	/**
	 * Redirects to the component template if it has not already been done
	 *
	 * @return void redirects to component template
	 */
	protected function ensureComponentTemplate()
	{
		$app         = Helpers\OrganizerHelper::getApplication();
		$templateSet = $app->input->getString('tmpl', '') == 'component';
		if (!$templateSet)
		{
			$query = $app->input->server->get('QUERY_STRING', '', 'raw') . '&tmpl=component';
			$app->redirect(Uri::root() . 'index.php?' . $query);
		}
	}

	/**
	 * Gets the room information for a day
	 *
	 * @return void  room information for the given day is added to the $blocks object variable
	 */
	private function getDay()
	{
		$date     = date('Y-m-d');
		$isSunday = date('l', strtotime($date)) == 'Sunday';
		if ($isSunday)
		{
			return;
		}

		$events = $this->getEvents($date);
		$blocks = $this->processBlocks($events);

		if (count($blocks))
		{
			$this->blocks = $blocks;

			return;
		}
	}

	/**
	 * Sets event information for the given block in the given schedule
	 *
	 * @param   string  $date  the date on which the events occur
	 *
	 * @return array the events for the given date
	 */
	protected function getEvents($date)
	{
		$tag   = Helpers\Languages::getTag();
		$query = $this->_db->getQuery(true);

		$select = "DISTINCT conf.id, conf.configuration, cal.startTime, cal.endTime, ";
		$select .= "l.id as lessonID, m.abbreviation_$tag AS method, ";
		$select .= "co.name AS courseName, s.name_$tag AS sName";
		$query->select($select)
			->from('#__organizer_calendar AS cal')
			->innerJoin('#__organizer_calendar_configuration_map AS ccm ON ccm.calendarID = cal.id')
			->innerJoin('#__organizer_lesson_configurations AS conf ON conf.id = ccm.configurationID')
			->innerJoin('#__organizer_lessons AS l ON l.id = cal.lessonID')
			->innerJoin('#__organizer_lesson_courses AS lcrs ON lcrs.lessonID = l.id AND lcrs.id = conf.lessonID')
			->innerJoin('#__organizer_courses AS co ON co.id = lcrs.courseID')
			->leftJoin('#__organizer_methods AS m ON m.id = l.methodID')
			->leftJoin('#__organizer_subject_events AS se ON se.courseID = co.id')
			->leftJoin('#__organizer_subjects AS s ON s.id = se.subjectID')
			->where("cal.schedule_date = '$date'")
			->where("cal.delta != 'removed'")
			->where("l.delta != 'removed'")
			->where("lcrs.delta != 'removed'");
		$this->_db->setQuery($query);

		$results = Helpers\OrganizerHelper::executeQuery('loadAssocList', []);
		if (empty($results))
		{
			return [];
		}

		$events = [];
		foreach ($results as $result)
		{
			$configuration = json_decode($result['configuration'], true);
			$relevant      = $this->hasRelevantRoom($configuration['rooms']);
			if (!$relevant)
			{
				continue;
			}

			$startTime = substr(str_replace(':', '', $result['startTime']), 0, 4);
			$endTime   = substr(str_replace(':', '', $result['endTime']), 0, 4);
			$times     = "$startTime-$endTime";

			if (empty($events[$times]))
			{
				$events[$times] = [];
			}

			$lessonID = $result['lessonID'];

			if (empty($events[$startTime][$lessonID]))
			{
				$events[$times][$lessonID]              = [];
				$events[$times][$lessonID]['titles']    = [];
				$events[$times][$lessonID]['persons']   = [];
				$events[$times][$lessonID]['method']    = empty($result['method']) ? '' : " - {$result['method']}";
				$events[$times][$lessonID]['startTime'] = $startTime;
				$events[$times][$lessonID]['endTime']   = $endTime;
			}

			$title = empty($result['sName']) ? $result['courseName'] : $result['sName'];

			if (!in_array($title, $events[$times][$lessonID]['titles']))
			{
				$events[$times][$lessonID]['titles'][] = $title;
			}

			if (empty($events[$times][$lessonID]['persons']))
			{
				$events[$times][$lessonID]['persons'] = $this->getEventPersons($configuration['persons']);
			}
			else
			{
				$existingPersons                      = $events[$times][$lessonID]['persons'];
				$newPersons                           = $this->getEventPersons($configuration['persons']);
				$events[$times][$lessonID]['persons'] = array_merge($existingPersons, $newPersons);
			}
		}

		return $events;
	}

	/**
	 * Adds the person names to the person instances index.
	 *
	 * @param   array  $instancePersons  the persons associated with the instance
	 *
	 * @return array an array of persons in the form id => 'surname(s), forename(s)'
	 */
	private function getEventPersons(&$instancePersons)
	{
		$persons = [];

		foreach ($instancePersons as $personID => $delta)
		{
			if ($delta == 'removed')
			{
				unset($instancePersons[$personID]);
				continue;
			}

			$persons[$personID] = Helpers\Persons::getLNFName($personID);
		}

		asort($persons);

		return $persons;
	}

	/**
	 * Adds the room names to the room instances index, if the room was requested.
	 *
	 * @param   array  $instanceRooms  the rooms associated with the instance
	 *
	 * @return bool true if the instance is associated with a requested room
	 */
	private function hasRelevantRoom(&$instanceRooms)
	{
		foreach ($instanceRooms as $roomID => $delta)
		{
			if ($delta == 'removed' or $roomID != $this->roomID)
			{
				continue;
			}

			return true;
		}

		return false;
	}

	/**
	 * Determines which display behaviour is desired based on which layout was previously used
	 *
	 * @return void
	 */
	private function setAlternatingLayout()
	{
		$session   = Factory::getSession();
		$displayed = $session->get('displayed', 'schedule');

		if ($displayed == 'schedule')
		{
			$this->params['layout'] = 'content';
		}

		if ($displayed == 'content')
		{
			$this->params['layout'] = 'schedule';
		}

		$session->set('displayed', $this->params['layout']);
	}

	/**
	 * Sets display parameters
	 *
	 * @return void
	 */
	private function setDisplayParams()
	{
		if (!empty($this->monitorID))
		{
			$monitorEntry = new Tables\Monitors();
			$monitorEntry->load($this->monitorID);
		}

		if (isset($monitorEntry) and !$monitorEntry->useDefaults)
		{
			$this->params['display']         = empty($monitorEntry->display) ? self::SCHEDULE : $monitorEntry->display;
			$this->params['scheduleRefresh'] = $monitorEntry->scheduleRefresh;
			$this->params['contentRefresh']  = $monitorEntry->contentRefresh;
			$this->params['content']         = $monitorEntry->content;
		}
		else
		{
			$params                          = Helpers\Input::getParams();
			$this->params['display']         = $params->get('display', self::SCHEDULE);
			$this->params['scheduleRefresh'] = $params->get('scheduleRefresh', 60);
			$this->params['contentRefresh']  = $params->get('contentRefresh', 60);

			$this->params['content'] = Helpers\Input::getParams()->get('content');
		}

		// No need for special handling if no content has been set
		if (empty($this->params['content']))
		{
			$this->params['display'] = self::SCHEDULE;
		}

		switch ($this->params['display'])
		{
			case self::ALTERNATING:
				$this->setAlternatingLayout();
				break;
			case self::CONTENT:
				$this->params['layout'] = 'content';
				break;
			case self::SCHEDULE:
			default:
				$this->params['layout'] = 'schedule';
		}
	}

	/**
	 * Gets the main grid from the first schedule
	 *
	 * @return void  sets the object grid variable
	 */
	private function setGrid()
	{
		$query = $this->_db->getQuery(true);
		$query->select('grid')->from('#__organizer_grids')->where('isDefault = 1');
		$this->_db->setQuery($query);

		$rawGrid = Helpers\OrganizerHelper::executeQuery('loadResult', '');

		$this->grid = json_decode($rawGrid, true);
	}

	/**
	 * Checks whether the accessing agent is a monitor
	 *
	 * @return void sets instance variables
	 */
	private function setRoomData()
	{
		$roomsTable = new Tables\Rooms();

		if ($remoteAddress = Helpers\Input::getInput()->server->getString('REMOTE_ADDR', ''))
		{
			$monitorTable = new Tables\Monitors();
			$registered   = $monitorTable->load(['ip' => $remoteAddress]);

			if ($registered and $monitorTable->roomID and $roomsTable->load($monitorTable->roomID))
			{
				$this->monitorID = $monitorTable->id;
				$this->roomID    = $roomsTable->id;
				$this->roomName  = $roomsTable->name;

				return;
			}
		}

		if ($name = Helpers\Input::getString('name') and $roomsTable->load(['name' => $name]))
		{
			$this->roomID   = $roomsTable->id;
			$this->roomName = $name;

			return;
		}

		// Room could not be resolved => redirect to home
		Helpers\OrganizerHelper::getApplication()->redirect(Uri::root());
	}

	/**
	 * Resolves the daily events to their respective grid blocks
	 *
	 * @param   array  $events  the events for the given day
	 *
	 * @return array the blocks
	 */
	private function processBlocks($events)
	{
		$blocks = [];
		foreach ($this->grid['periods'] as $blockNo => $block)
		{
			$blocks[$blockNo]              = [];
			$blockStartTime                = Dates::formatTime($block['startTime']);
			$blockEndTime                  = Dates::formatTime($block['endTime']);
			$blocks[$blockNo]['startTime'] = $blockStartTime;
			$blocks[$blockNo]['endTime']   = $blockEndTime;
			$blocks[$blockNo]['lessons']   = [];

			foreach ($events as $times => $eventInstances)
			{
				list($eventStartTime, $eventEndTime) = explode('-', $times);
				$eventStartTime = Dates::formatTime($eventStartTime);
				$eventEndTime   = Dates::formatTime($eventEndTime);
				$before         = $eventEndTime < $blockStartTime;
				$after          = $eventStartTime > $blockEndTime;

				if ($before or $after)
				{
					continue;
				}

				$divTime    = '';
				$startSynch = $blockStartTime == $eventStartTime;
				$endSynch   = $blockEndTime == $eventEndTime;

				if (!$startSynch or !$endSynch)
				{
					$startTime = Dates::formatTime($eventStartTime);
					$endTime   = Dates::formatTime($eventEndTime);
					$divTime   .= " ($startTime -  $endTime)";
				}

				foreach ($eventInstances as $lessonID => $eventInstance)
				{
					$instancePersons = $eventInstance['persons'];
					if (empty($blocks[$blockNo]['lessons'][$lessonID]))
					{
						$blocks[$blockNo]['lessons'][$lessonID]            = [];
						$blocks[$blockNo]['lessons'][$lessonID]['persons'] = $instancePersons;
						$blocks[$blockNo]['lessons'][$lessonID]['titles']  = $eventInstance['titles'];
						$blocks[$blockNo]['lessons'][$lessonID]['method']  = $eventInstance['method'];
						$blocks[$blockNo]['lessons'][$lessonID]['divTime'] = $divTime;
						continue;
					}

					$existingPersons = $blocks[$blockNo]['lessons'][$lessonID]['persons'];

					$blocks[$blockNo]['lessons'][$lessonID]['persons']
						= array_unique(array_merge($instancePersons, $existingPersons));

					$blocks[$blockNo]['lessons'][$lessonID]['titles'] = array_unique(array_merge(
						$blocks[$blockNo]['lessons'][$lessonID]['titles'],
						$eventInstance['titles']
					));
				}
			}
		}

		return $blocks;
	}
}
