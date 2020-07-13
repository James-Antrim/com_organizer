<?php
/**
 * @package     Organizer
 * @extension   com_organizer
 * @author      James Antrim, <james.antrim@nm.thm.de>
 * @copyright   2020 TH Mittelhessen
 * @license     GNU GPL v.3
 * @link        www.thm.de
 */

namespace Organizer\Fields;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;
use Organizer\Helpers;

/**
 * Class creates a select box for explicitly associating subjects with events. This is also done
 * implicitly during the schedule import process according to degree programs and the subject's module number.
 */
class SubjectEventsField extends FormField
{
	use Translated;

	protected $type = 'SubjectEvents';

	/**
	 * Returns a selectionbox where stored coursepool can be chosen as a parent node
	 *
	 * @return string  the HTML output
	 */
	public function getInput()
	{
		$dbo       = Factory::getDbo();
		$fieldName = $this->getAttribute('name');
		$subjectID = Helpers\Input::getID();
		$tag       = Helpers\Languages::getTag();

		$eQuery = $dbo->getQuery(true);
		$eQuery->select("id AS value, name_$tag AS name")->from('#__organizer_events')->order('name');
		$dbo->setQuery($eQuery);

		$events = Helpers\OrganizerHelper::executeQuery('loadAssocList', []);

		$options = [Helpers\HTML::_('select.option', '', Helpers\Languages::_('ORGANIZER_SELECT_EVENT'))];
		foreach ($events as $event)
		{
			$options[] = Helpers\HTML::_('select.option', $event['value'], $event['name']);
		}

		$sQuery = $dbo->getQuery(true);
		$sQuery->select('eventID')->from('#__organizer_subject_events')->where("subjectID = '$subjectID'");
		$dbo->setQuery($sQuery);
		$selected = Helpers\OrganizerHelper::executeQuery('loadColumn', []);


		$attributes = ['multiple' => 'multiple', 'size' => '10'];

		return Helpers\HTML::selectBox($options, $fieldName, $attributes, $selected, true);
	}
}