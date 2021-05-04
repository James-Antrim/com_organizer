<?php
/**
 * @package     Organizer
 * @extension   com_organizer
 * @author      James Antrim, <james.antrim@nm.thm.de>
 * @copyright   2020 TH Mittelhessen
 * @license     GNU GPL v.3
 * @link        www.thm.de
 */

namespace Organizer\Views\XLS;

/**
 * Class creates a XLS file for the display of the filtered schedule information.
 */
class ScheduleExport extends BaseView
{
    use PHPExcelDependent;

    /**
     * Sets context variables and renders the view.
     *
     * @param   string  $tpl  The name of the template file to parse; automatically searches through the template paths.
     *
     * @return void
     */
    public function display($tpl = null)
    {
        $model      = $this->getModel();
        $parameters = $model->parameters;

        $fileName = $parameters['documentFormat'] . '_' . $parameters['xlsWeekFormat'];
        require_once __DIR__ . "/tmpl/$fileName.php";
        $export = new \OrganizerTemplateExport_XLS($parameters, $model->lessons);
        $export->render();
        ob_flush();
    }
}
