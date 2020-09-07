<?php
/**
 * @package     Organizer
 * @extension   com_organizer
 * @author      James Antrim, <james.antrim@nm.thm.de>
 * @copyright   2020 TH Mittelhessen
 * @license     GNU GPL v.3
 * @link        www.thm.de
 */

namespace Organizer\Views\HTML;

use Organizer\Helpers;

/**
 * Class loads the subject into the display context.
 */
class CourseItem extends ItemView
{
	// Participant statuses
	const UNREGISTERED = null, PENDING = 0, ACCEPTED = 1;

	// Course Statuses
	const EXPIRED = -1, ONGOING = 1;

	/**
	 * Adds supplemental information to the display output.
	 *
	 * @return void modifies the object property supplement
	 */
	protected function addSupplement()
	{
		$course = $this->item;
		if ($course['courseStatus'] === self::EXPIRED)
		{
			$color = '';
		}
		elseif ($course['registrationStatus'] === self::ACCEPTED)
		{
			$color = 'green';
		}
		elseif ($course['courseStatus'] === self::ONGOING)
		{
			$color = 'red';
		}
		elseif ($course['registrationStatus'] === self::PENDING)
		{
			$color = 'blue';
		}
		else
		{
			$color = 'yellow';
		}

		$text = '<div class="tbox-' . $color . '">';

		$texts = [];
		if ($course['courseStatus'] === self::EXPIRED or $course['courseStatus'] === self::ONGOING)
		{
			$texts[] = $course['courseText'];
		}
		elseif ($course['courseStatus'] !== self::EXPIRED)
		{
			$texts[] = $course['registrationText'];
			if (!$course['courseStatus'] === self::ONGOING)
			{
				$texts[] = $course['registrationAllowed'];
			}
			if ($course['registrationStatus'] === self::UNREGISTERED)
			{
				$texts[] = $course['registrationType'];
			}
		}

		$text .= implode(' ', $texts);
		$text .= '</div>';

		$this->supplement = $text;
	}

	/**
	 * Creates a subtitle element from the term name and the start and end dates of the course.
	 *
	 * @return void modifies the course
	 */
	protected function setSubtitle()
	{
		$dates  = Helpers\Courses::getDateDisplay($this->item['id']);
		$termID = $this->item['preparatory'] ? Helpers\Terms::getNextID($this->item['termID']) : $this->item['termID'];
		$term   = Helpers\Terms::getName($termID);

		$this->subtitle = "<h6 class=\"sub-title\">$term $dates</h6>";
	}
}
/*
					$toolbar->appendButton(
						'Standard',
						'enter',
						Languages::_('ORGANIZER_REGISTER'),
						'courses.register',
						true
					);
					$toolbar->appendButton(
						'Standard',
						'exit',
						Languages::_('ORGANIZER_DEREGISTER'),
						'courses.register',
						true
					);*/