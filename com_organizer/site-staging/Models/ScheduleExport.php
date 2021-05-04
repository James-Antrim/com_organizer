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

use Joomla\CMS\Application\ApplicationHelper;
use Organizer\Adapters\Database;
use Organizer\Helpers;
use Organizer\Helpers\Input;
use Organizer\Tables;

/**
 * Class retrieves information for the creation of a schedule export form.
 */
class ScheduleExport extends BaseModel
{
    public $defaultGrid = 1;

    public $docTitle;

    public $grid;

    public $lessons;

    public $pageTitle;

    public $parameters;

    /**
     * Schedule_Export constructor.
     *
     * @param   array  $config
     */
    public function __construct(array $config)
    {
        parent::__construct($config);
        $format        = Input::getCMD('format', 'html');
        $lessonFormats = ['pdf', 'ics', 'xls'];

        // Don't bother setting these variables for html and raw formats
        if (in_array($format, $lessonFormats)) {
            $this->setParameters();

            if ($format === 'pdf') {
                $this->setGrid();
            }

            $this->setTitles();
            $this->lessons = Helpers\Schedules::getLessons($this->parameters);
        }
    }

    /**
     * Retrieves organization options
     *
     * @return array an array of organization options
     */
    public function getOrganizationOptions()
    {
        $organizations = Helpers\Organizations::getOptions(false);
        $options       = [];
        $options['']   = Helpers\Languages::_('ORGANIZER_SELECT_ORGANIZATION');

        foreach ($organizations as $id => $name) {
            $options[$id] = $name;
        }

        return $options;
    }

    /**
     * Retrieves grid options
     *
     * @return array an array of grid options
     */
    public function getGridOptions()
    {
        $tag   = Helpers\Languages::getTag();
        $query = Database::getQuery();
        $query->select("id, name_$tag AS name, isDefault")->from('#__organizer_grids');
        Database::setQuery($query);

        $options = [];

        $grids = Database::loadAssocList();

        foreach ($grids as $grid) {
            if ($grid['isDefault']) {
                $this->defaultGrid = $grid['id'];
            }

            $options[$grid['id']] = $grid['name'];
        }

        return $options;
    }

    /**
     * Attempts to retrieve the titles for the document and page
     *
     * @return array the document and page names
     */
    private function getPoolTitles()
    {
        $titles  = ['docTitle' => '', 'pageTitle' => ''];
        $poolIDs = array_values($this->parameters['poolIDs']);

        if (empty($poolIDs)) {
            return $titles;
        }

        $table       = new Tables\Groups();
        $oneResource = count($poolIDs) === 1;

        foreach ($poolIDs as $poolID) {
            if ($table->load($poolID)) {
                $code = ApplicationHelper::stringURLSafe($table->code);

                if ($oneResource) {
                    $titles['docTitle']  = $code . '_';
                    $columnName          = 'fullName_' . Helpers\Languages::getTag();
                    $titles['pageTitle'] = $table->$columnName;

                    return $titles;
                }

                $titles['docTitle']  .= $code . '_';
                $titles['pageTitle'] .= empty($titles['pageTitle']) ? $table->code : ", {$table->code}";
            }
        }

        return $titles;
    }

    /**
     * Attempts to retrieve the titles for the document and page
     *
     * @return array the document and page names
     */
    private function getRoomTitles()
    {
        $titles  = ['docTitle' => '', 'pageTitle' => ''];
        $roomIDs = array_values($this->parameters['roomIDs']);

        if (empty($roomIDs)) {
            return $titles;
        }

        $table       = new Tables\Rooms();
        $oneResource = count($roomIDs) === 1;

        foreach ($roomIDs as $roomID) {
            if ($table->load($roomID)) {
                $untisID = ApplicationHelper::stringURLSafe($table->untisID);

                if ($oneResource) {
                    $titles['docTitle']  = $untisID . '_';
                    $titles['pageTitle'] = $table->name;

                    return $titles;
                }

                $titles['docTitle']  .= $untisID . '_';
                $titles['pageTitle'] .= empty($titles['pageTitle']) ? $table->name : ", {$table->name}";
            }
        }

        return $titles;
    }

    /**
     * Attempts to retrieve the titles for the document and page
     *
     * @return array the document and page names
     */
    private function getSubjectTitles()
    {
        $courseIDs = array_values($this->parameters['courseIDs']);
        $titles    = ['docTitle' => '', 'pageTitle' => ''];

        if (empty($courseIDs)) {
            return $titles;
        }

        $oneResource = count($courseIDs) === 1;
        $tag         = Helpers\Languages::getTag();

        $query = Database::getQuery();
        $query->select('co.name AS courseName, co.code')
            ->select("s.shortName_$tag AS shortName, s.name_$tag AS name")
            ->from('#__organizer_courses AS co')
            ->leftJoin('#__organizer_subject_events AS se ON se.courseID = co.id')
            ->leftJoin('#__organizer_subjects AS s ON s.id = se.subjectID');

        foreach ($courseIDs as $courseID) {
            $query->clear('where');
            $query->where("co.id = '$courseID'");
            Database::setQuery($query);
            $courseNames = Database::loadAssoc();

            if (!empty($courseNames)) {
                $untisID = ApplicationHelper::stringURLSafe($courseNames['code']);

                if (empty($courseNames['name'])) {
                    if (empty($courseNames['shortName'])) {
                        $name = $courseNames['courseName'];
                    } else {
                        $name = $courseNames['shortName'];
                    }
                } else {
                    $name = $courseNames['name'];
                }

                if ($oneResource) {
                    $titles['docTitle']  = $untisID . '_';
                    $titles['pageTitle'] = $name;

                    return $titles;
                }

                $titles['docTitle']  .= $untisID . '_';
                $titles['pageTitle'] .= empty($titles['pageTitle']) ? $untisID : ", {$untisID}";
            }
        }

        return $titles;
    }

    /**
     * Attempts to retrieve the titles for the document and page
     *
     * @return array the document and page names
     */
    private function getPersonTitles()
    {
        $titles    = ['docTitle' => '', 'pageTitle' => ''];
        $personIDs = array_values($this->parameters['personIDs']);

        if (empty($personIDs)) {
            return $titles;
        }

        $table       = new Tables\Persons();
        $oneResource = count($personIDs) === 1;

        foreach ($personIDs as $personID) {
            if ($table->load($personID)) {
                if ($oneResource) {
                    $displayName         = Helpers\Persons::getDefaultName($personID);
                    $titles['docTitle']  = ApplicationHelper::stringURLSafe($displayName) . '_';
                    $titles['pageTitle'] = $displayName;

                    return $titles;
                }

                $displayName         = Helpers\Persons::getLNFName($personID, true);
                $untisID             = ApplicationHelper::stringURLSafe($table->untisID);
                $titles['docTitle']  .= $untisID . '_';
                $titles['pageTitle'] .= empty($titles['pageTitle']) ? $displayName : ", {$displayName}";
            }
        }

        return $titles;
    }

    /**
     * Retrieves the selected grid from the database
     *
     * @return void sets object variables
     */
    private function setGrid()
    {
        $query = Database::getQuery();
        $query->select('grid')->from('#__organizer_grids');

        if (empty($this->parameters['gridID'])) {
            $query->where('isDefault = 1');
        } else {
            $query->where("id = {$this->parameters['gridID']}");
        }

        Database::setQuery($query);

        if (!$rawGrid = Database::loadString()) {
            return;
        }

        $gridSettings = json_decode($rawGrid, true);

        if (!empty($gridSettings['periods'])) {
            $this->grid = $gridSettings['periods'];
        }

        $this->parameters['startDay'] = $gridSettings['startDay'];
        $this->parameters['endDay']   = $gridSettings['endDay'];
    }

    /**
     * Sets the basic parameters from the request
     *
     * @return void sets object variables
     */
    private function setParameters()
    {
        $parameters                    = [];
        $parameters['organizationIDs'] = Input::getFilterIDs('organization');
        $parameters['format']          = Input::getCMD('format', 'pdf');
        $parameters['mySchedule']      = Input::getBool('myschedule', false);

        if (empty($parameters['mySchedule'])) {
            if (count($poolIDs = Input::getFilterIDs('pool'))) {
                $parameters['poolIDs'] = [$poolIDs];
            }
            if (count($personIDs = Input::getFilterIDs('person'))) {
                $parameters['personIDs'] = [$personIDs];
            }
            if (count($roomIDs = Input::getFilterIDs('room'))) {
                $parameters['roomIDs'] = [$roomIDs];
            }
        }

        $parameters['userID'] = Helpers\Users::getUser()->id;

        $allowedIntervals       = ['day', 'week', 'month', 'semester', 'custom'];
        $reqInterval            = Input::getCMD('interval');
        $parameters['interval'] = in_array($reqInterval, $allowedIntervals) ? $reqInterval : 'week';

        $parameters['date'] = Helpers\Dates::standardizeDate(Input::getCMD('date'));

        switch ($parameters['format']) {
            case 'pdf':
                $parameters['documentFormat'] = Input::getCMD('documentFormat', 'a4');
                $parameters['displayFormat']  = Input::getCMD('displayFormat', 'schedule');
                $parameters['gridID']         = Input::getInt('gridID');
                $parameters['grouping']       = Input::getInt('grouping', 1);
                $parameters['pdfWeekFormat']  = Input::getCMD('pdfWeekFormat', 'sequence');
                $parameters['titles']         = Input::getInt('titles', 1);
                break;
            case 'xls':
                $parameters['documentFormat'] = Input::getCMD('documentFormat', 'si');
                $parameters['xlsWeekFormat']  = Input::getCMD('xlsWeekFormat', 'sequence');
                break;
        }

        $parameters['delta'] = false;

        $this->parameters = $parameters;
    }

    /**
     * Sets the document and page titles
     *
     * @return void sets object variables
     */
    private function setTitles()
    {
        $docTitle      = Helpers\Languages::_('ORGANIZER_SCHEDULE') . '_';
        $pageTitle     = '';
        $useMySchedule = !empty($this->parameters['mySchedule']);
        $useLessons    = !empty($this->parameters['lessonIDs']);
        $useInstances  = !empty($this->parameters['instanceIDs']);
        $usePools      = !empty($this->parameters['poolIDs']);
        $usePersons    = !empty($this->parameters['personIDs']);
        $useRooms      = !empty($this->parameters['roomIDs']);
        $useSubjects   = !empty($this->parameters['subjectIDs']);

        if ($useMySchedule) {
            $docTitle  = 'mySchedule_';
            $pageTitle = Helpers\Languages::_('ORGANIZER_MY_SCHEDULE');
        } elseif ((!$useLessons and !$useInstances) and ($usePools xor $usePersons xor $useRooms xor $useSubjects)) {
            if ($usePools) {
                $titles    = $this->getPoolTitles();
                $docTitle  .= $titles['docTitle'];
                $pageTitle .= empty($pageTitle) ? $titles['pageTitle'] : ", {$titles['pageTitle']}";
            }

            if ($usePersons) {
                $titles    = $this->getPersonTitles();
                $docTitle  .= $titles['docTitle'];
                $pageTitle .= empty($pageTitle) ? $titles['pageTitle'] : ", {$titles['pageTitle']}";
            }

            if ($useRooms) {
                $titles    = $this->getRoomTitles();
                $docTitle  .= $titles['docTitle'];
                $pageTitle .= empty($pageTitle) ? $titles['pageTitle'] : ", {$titles['pageTitle']}";
            }

            if ($useSubjects) {
                $titles    = $this->getSubjectTitles();
                $docTitle  .= $titles['docTitle'];
                $pageTitle .= empty($pageTitle) ? $titles['pageTitle'] : ", {$titles['pageTitle']}";
            }
        } else {
            $docTitle  = 'Schedule_';
            $pageTitle = '';
        }

        // Constructed docTitle always ends with a '_' character at this point.
        $this->parameters['docTitle']  = $docTitle . date('Ymd');
        $this->parameters['pageTitle'] = $pageTitle;
    }
}
