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
use Organizer\Tables;
use SimpleXMLElement;

/**
 * Class which manages stored (subject) pool data.
 */
class Pool extends CurriculumResource
{
	use Associated, SubOrdinate, SuperOrdinate;

	protected $helper = 'Pools';

	protected $resource = 'pool';

	/**
	 * Method to import data associated with a resource from LSF
	 *
	 * @param   int  $resourceID  the id of the program to be imported
	 *
	 * @return bool  true on success, otherwise false
	 */
	public function importSingle($resourceID)
	{
		// There is no legitimate call to this method.
		return false;
	}

	/**
	 * Creates a pool entry if none exists and calls
	 *
	 * @param   SimpleXMLElement  $XMLObject       a SimpleXML object containing rudimentary subject data
	 * @param   int               $organizationID  the id of the organization to which this data belongs
	 * @param   int               $parentID        the id of the parent entry
	 *
	 * @return bool  true on success, otherwise false
	 */
	public function processResource($XMLObject, $organizationID, $parentID)
	{
		$lsfID = empty($XMLObject->pordid) ? (string) $XMLObject->modulid : (string) $XMLObject->pordid;
		if (empty($lsfID))
		{
			return false;
		}

		$blocked = !empty($XMLObject->sperrmh) and strtolower((string) $XMLObject->sperrmh) === 'x';
		$noChildren = !isset($XMLObject->modulliste->modul);
		$validTitle = $this->validTitle($XMLObject);

		$pool = new Tables\Pools();

		if (!$pool->load(['lsfID' => $lsfID]))
		{
			// There isn't one and shouldn't be one
			if ($blocked or !$validTitle or $noChildren)
			{
				return true;
			}

			$pool->lsfID = $lsfID;
			$this->setNameAttributes($pool, $XMLObject);

			if (!$pool->store())
			{
				return false;
			}
		}
		elseif ($blocked or !$validTitle or $noChildren)
		{
			return $this->deleteSingle($pool->id);
		}

		$curricula = new Tables\Curricula();

		if (!$curricula->load(['parentID' => $parentID, 'poolID' => $pool->id]))
		{
			$range             = [];
			$range['parentID'] = $parentID;
			$range['poolID']   = $pool->id;

			$range['ordering'] = $this->getOrdering($parentID, $pool->id);
			if (!$this->shiftUp($parentID, $range['ordering']))
			{
				return false;
			}

			if (!$this->addRange($range))
			{
				return false;
			}

			$curricula->load(['parentID' => $parentID, 'poolID' => $pool->id]);
		}

		$association = new Tables\Associations();
		if (!$association->load(['organizationID' => $organizationID, 'poolID' => $pool->id]))
		{
			$association->save(['organizationID' => $organizationID, 'poolID' => $pool->id]);
		}

		return $this->processCollection($XMLObject->modulliste->modul, $organizationID, $curricula->id);
	}

	/**
	 * Attempts to save the resource.
	 *
	 * @param   array  $data  form data
	 *
	 * @return mixed int id of the resource on success, otherwise bool false
	 */
	public function save($data = [])
	{
		$data = empty($data) ? Helpers\Input::getFormItems()->toArray() : $data;

		if (empty($data['id']))
		{
			if (!Helpers\Can::documentTheseOrganizations())
			{
				Helpers\OrganizerHelper::error(403);
			}
		}
		elseif (is_numeric($data['id']))
		{
			if (!Helpers\Can::document('pool', (int) $data['id']))
			{
				Helpers\OrganizerHelper::error(403);
			}
		}
		else
		{
			return false;
		}

		$table = new Tables\Pools();

		if (!$table->save($data))
		{
			return false;
		}

		$data['id'] = $table->id;

		if (!empty($data['organizationIDs']) and !$this->updateAssociations($data['id'], $data['organizationIDs']))
		{
			return false;
		}

		$superOrdinates = $this->getSuperOrdinates($data);

		if (!$this->addNew($data, $superOrdinates))
		{
			return false;
		}

		$this->removeDeprecated($table->id, $superOrdinates);

		return $table->id;
	}
}
