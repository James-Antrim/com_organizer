<?php
/**
 * @package     Organizer\Models
 * @extension   Organizer
 * @author      James Antrim, <james.antrim@nm.thm.de>
 * @copyright   2020 TH Mittelhessen
 * @license     GNU GPL v.3
 * @link        www.thm.de
 */

namespace Organizer\Models;

use JDatabaseQuery;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Organizer\Helpers;
use Organizer\Tables;

class Checkin extends FormModel
{
	private $participant;

	/**
	 * Constructor.
	 *
	 * @param   array  $config  An optional associative array of configuration settings.
	 */
	public function __construct($config = [])
	{
		parent::__construct($config);

		// Force component template
		if (Helpers\Input::getCMD('tmpl') !== 'component')
		{
			$URL = Uri::root() . "?option=com_organizer&view=checkin&tmpl=component";
			Helpers\OrganizerHelper::getApplication()->redirect($URL);
		}

		$form    = $this->getForm();
		$session = Factory::getSession();

		if ($username = $session->get('organizer.checkin.username'))
		{
			$form->setValue('username', null, $username);
		}
		if ($code = $session->get('organizer.checkin.code'))
		{
			$form->setValue('code', null, $code);
		}

		$participant = new Tables\Participants();

		if ($participantID = Helpers\Users::getID())
		{
			$participant->load($participantID);

			$form->setValue('id', null, $participantID);
			$form->setValue('address', null, $participant->address);
			$form->setValue('city', null, $participant->city);
			$form->setValue('forename', null, $participant->forename);
			$form->setValue('surname', null, $participant->surname);
			$form->setValue('telephone', null, $participant->telephone);
			$form->setValue('zipCode', null, $participant->zipCode);
		}

		$this->participant = $participant;
	}

	/**
	 * @inheritDoc
	 */
	protected function authorize()
	{
		return true;
	}

	/**
	 * Loads participant data for the current user.
	 *
	 * @return Tables\Participants
	 */
	public function getParticipant()
	{
		return $this->participant;
	}

	/**
	 * Gets the
	 * @return array
	 */
	public function getInstances()
	{
		if (!$participantID = Helpers\Users::getID())
		{
			return [];
		}

		$now = date('H:i:s');

		// Ongoing
		$query = $this->getQuery($participantID);
		$query->where("b.startTime <= '$now'")->where("b.endTime >= '$now'");
		$this->_db->setQuery($query);

		if (!$instanceIDs = Helpers\OrganizerHelper::executeQuery('loadColumn', []))
		{
			// Upcoming
			$then  = date('H:i:s', strtotime('+60 minutes'));
			$query = $this->getQuery($participantID);
			$query->where("b.startTime >= '$now'")->where("b.startTime <= '$then'");
			$this->_db->setQuery($query);

			if (!$instanceIDs = Helpers\OrganizerHelper::executeQuery('loadColumn', []))
			{
				return [];
			}
		}

		foreach ($instanceIDs as $index => $instanceID)
		{
			$instanceIDs[$index] = Helpers\Instances::getInstance($instanceID);
		}

		return $instanceIDs;
	}

	/**
	 * Gets a query where common statements are already included.
	 *
	 * @param   int  $participantID  the id of the participant for which to find checkins
	 *
	 * @return JDatabaseQuery
	 */
	private function getQuery(int $participantID)
	{
		$today = date('Y-m-d');
		$query = $this->_db->getQuery(true);
		$query->select('instanceID')
			->from('#__organizer_instance_participants AS ip')
			->innerJoin('#__organizer_instances AS i ON i.id = ip.instanceID')
			->innerJoin('#__organizer_blocks AS b ON b.id = i.blockID')
			->where("ip.participantID = $participantID")
			->where("ip.attended = 1")
			->where("b.date = '$today'");

		return $query;
	}
}