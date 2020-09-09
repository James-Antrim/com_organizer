<?php
/**
 * @package     Organizer
 * @extension   com_organizer
 * @author      James Antrim, <james.antrim@nm.thm.de>
 * @copyright   2020 TH Mittelhessen
 * @license     GNU GPL v.3
 * @link        www.thm.de
 */

namespace Organizer\Controllers;

use Exception;
use Joomla\CMS\Router\Route;
use Organizer\Controller;
use Organizer\Helpers;
use Organizer\Models;

/**
 * Class receives user actions and performs access checks and redirection.
 */
class Courses extends Controller
{
	const UNREGISTERED = null;

	protected $listView = 'courses';

	protected $resource = 'course';

	/**
	 * De-/registers a participant from/to a course.
	 *
	 * @return void
	 */
	public function deregister()
	{
		$referrer = Helpers\Input::getInput()->server->getString('HTTP_REFERER');

		$model = new Models\Course();

		if ($model->deregister())
		{
			Helpers\OrganizerHelper::message('ORGANIZER_STATUS_CHANGE_SUCCESS');
		}
		else
		{
			Helpers\OrganizerHelper::message('ORGANIZER_STATUS_CHANGE_FAIL', 'error');
		}

		$this->setRedirect($referrer);
	}

	/**
	 * De-/registers a participant from/to a course.
	 *
	 * @return void
	 */
	public function register()
	{
		$referrer = Helpers\Input::getInput()->server->getString('HTTP_REFERER');
		if (!Helpers\Participants::canRegister())
		{
			Helpers\OrganizerHelper::message('ORGANIZER_PROFILE_INCOMPLETE', 'error');
		}
		else
		{
			$model = new Models\Course();

			if ($model->register())
			{
				Helpers\OrganizerHelper::message('ORGANIZER_STATUS_CHANGE_SUCCESS');
			}
			else
			{
				Helpers\OrganizerHelper::message('ORGANIZER_STATUS_CHANGE_FAIL', 'error');
			}
		}


		$this->setRedirect($referrer);
	}
}
