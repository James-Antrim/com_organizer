<?php
/**
 * @package     Organizer
 * @extension   com_organizer
 * @author      James Antrim, <james.antrim@nm.thm.de>
 * @copyright   2020 TH Mittelhessen
 * @license     GNU GPL v.3
 * @link        www.thm.de
 */

namespace Organizer\Helpers;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\Filter\InputFilter;
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;

/**
 * Class provides generalized functions useful for several component files.
 */
class Input
{
	private static $filter = null;

	private static $filterItems = false;

	private static $formItems = false;

	private static $input = null;

	private static $listItems = false;

	private static $params = false;

	/**
	 * Filters the given source data according to the type parameter.
	 *
	 * @param   mixed   $source  the data to be filtered
	 * @param   string  $type    the type against which to filter the source data
	 *
	 * @return mixed
	 */
	private static function filter($source, $type = 'string')
	{
		if (empty(self::$filter))
		{
			self::$filter = new InputFilter();
		}

		return self::$filter->clean($source, $type);
	}

	/**
	 * Retrieves the specified parameter.
	 *
	 * @param   string  $property  Name of the property to get.
	 *
	 * @return mixed the value found, or false if the property could not be found
	 */
	private static function find($property)
	{
		if ($value = self::getFormItems()->get($property, false))
		{
			return $value;
		}

		if ($value = self::getParams()->get($property, false))
		{
			return $value;
		}

		if ($value = self::getInput()->get($property, false, 'raw'))
		{
			return $value;
		}

		return false;
	}

	/**
	 * Retrieves the specified parameter.
	 *
	 * @param   string  $property  Name of the property to get.
	 * @param   mixed   $default   Default value to return if variable does not exist.
	 *
	 * @return bool
	 */
	public static function getBool($property, $default = false)
	{
		if ($value = self::find($property))
		{
			return self::filter($value, 'bool');
		}

		return self::filter($default, 'bool');
	}

	/**
	 * Retrieves the specified parameter.
	 *
	 * @param   string  $property  Name of the property to get.
	 * @param   mixed   $default   Default value to return if variable does not exist.
	 *
	 * @return string
	 */
	public static function getCMD($property, $default = '')
	{
		if ($value = self::find($property))
		{
			return self::filter($value, 'cmd');
		}

		return self::filter($default, 'cmd');
	}

	/**
	 * Returns the application's input object.
	 *
	 * @param   string  $resource  the name of the resource upon which the ids being sought reference
	 * @param   int     $default   the default value
	 *
	 * @return int the filter id
	 */
	public static function getFilterID($resource, $default = 0)
	{
		$filterIDs = self::getFilterIDs($resource);

		return empty($filterIDs) ? $default : $filterIDs[0];
	}

	/**
	 * Returns the application's input object.
	 *
	 * @param   string  $resource  the name of the resource upon which the ids being sought reference
	 *
	 * @return array the filter ids
	 */
	public static function getFilterIDs($resource)
	{
		$filterItems = self::getFilterItems();
		$listItems   = self::getListItems();

		$pluralIndex = "{$resource}IDs";

		if ($values = $filterItems->get($pluralIndex, false))
		{
			return self::formatIDValues($values);
		}

		if ($values = $listItems->get($pluralIndex, false))
		{
			return self::formatIDValues($values);
		}

		$singularIndex = "{$resource}ID";

		if ($value = $filterItems->get($singularIndex, false))
		{
			$values = [$value];

			return self::formatIDValues($values);
		}

		if ($value = $listItems->get($singularIndex, false))
		{
			$values = [$value];

			return self::formatIDValues($values);
		}

		return [];
	}

	/**
	 * Retrieves the filter items from the request and creates a registry with the data.
	 *
	 * @return Registry
	 */
	public static function getFilterItems()
	{
		if (self::$filterItems === false)
		{
			self::$filterItems = new Registry(self::getInput()->get('filter', [], 'array'));
		}

		return self::$filterItems;
	}

	/**
	 * Retrieves the request form.
	 *
	 * @return Registry with the request data if available
	 */
	public static function getFormItems()
	{
		if (self::$formItems === false)
		{
			self::$formItems = new Registry(self::getInput()->get('jform', [], 'array'));
		}

		return self::$formItems;
	}

	/**
	 * Retrieves the id parameter.
	 *
	 * @return int
	 */
	public static function getID()
	{
		return self::getInt('id');
	}

	/**
	 * Retrieves the id parameter.
	 *
	 * @param   string  $name  the input field name at which the value should be found
	 *
	 * @return array the ids
	 */
	public static function getIntCollection($name)
	{
		$collection = self::getInput()->get($name, [], 'array');

		return self::formatIDValues($collection);
	}

	/**
	 * Retrieves the specified parameter.
	 *
	 * @param   string  $property  Name of the property to get.
	 * @param   mixed   $default   Default value to return if variable does not exist.
	 *
	 * @return int
	 */
	public static function getInt($property, $default = 0)
	{
		if ($value = self::find($property))
		{
			return self::filter($value, 'int');
		}

		return self::filter($default, 'int');
	}

	/**
	 * Retrieves the id of the requested menu item / menu item configuration.
	 *
	 * @return int
	 */
	public static function getItemid()
	{
		$app     = OrganizerHelper::getApplication();
		$default = (empty($app->getMenu()) or empty($app->getMenu()->getActive())) ?
			0 : $app->getMenu()->getActive()->id;

		return self::getInt('Itemid', $default);
	}

	/**
	 * Returns the application's input object.
	 *
	 * @return \JInput
	 */
	public static function getInput()
	{
		if (empty(self::$input))
		{
			self::$input = OrganizerHelper::getApplication()->input;
		}

		return self::$input;
	}

	/**
	 * Retrieves the list items from the request and creates a registry with the data.
	 *
	 * @return Registry
	 */
	private static function getListItems()
	{
		if (self::$listItems === false)
		{
			self::$listItems = new Registry(self::getInput()->get('list', [], 'array'));
		}

		return self::$listItems;
	}

	/**
	 * Consolidates the application, component and menu parameters to a single registry with one call.
	 *
	 * @return Registry
	 */
	public static function getParams()
	{
		if (empty(self::$params))
		{
			$app          = OrganizerHelper::getApplication();
			self::$params = method_exists($app, 'getParams') ?
				$app->getParams() : ComponentHelper::getParams('com_organizer');
		}

		return self::$params;
	}

	/**
	 * Returns the selected resource id.
	 *
	 * @param   int  $default  the default value
	 *
	 * @return int the selected id
	 */
	public static function getSelectedID($default = 0)
	{
		$selectedIDs = self::getSelectedIDs();

		return empty($selectedIDs) ? $default : $selectedIDs[0];
	}

	/**
	 * Returns the selected resource ids.
	 *
	 * @return array the selected ids
	 */
	public static function getSelectedIDs()
	{
		$input = self::getInput();

		// List Views
		$selectedIDs = $input->get('cid', [], 'array');
		$selectedIDs = ArrayHelper::toInteger($selectedIDs);

		if (!empty($selectedIDs))
		{
			return $selectedIDs;
		}

		// Forms
		$formItems = self::getFormItems();
		if ($formItems->count())
		{
			// Merge Views
			if ($selectedIDs = $formItems->get('ids'))
			{
				$formattedValues = self::formatIDValues($selectedIDs);
				if (count($formattedValues))
				{
					asort($formattedValues);

					return $formattedValues;
				}
			}

			// Edit Views
			if ($id = $formItems->get('id'))
			{
				$selectedIDs = [$id];

				return self::formatIDValues($selectedIDs);
			}
		}

		// Default: explicit GET/POST parameter
		$selectedID = self::getID();

		return empty($selectedID) ? [] : [$selectedID];
	}

	/**
	 * Retrieves the specified parameter.
	 *
	 * @param   string  $property  Name of the property to get.
	 * @param   mixed   $default   Default value to return if variable does not exist.
	 *
	 * @return string
	 */
	public static function getString($property, $default = '')
	{
		if ($value = self::find($property))
		{
			return self::filter($value, 'string');
		}

		return self::filter($default, 'string');
	}

	/**
	 * Retrieves the task parameter.
	 *
	 * @return string
	 */
	public static function getTask()
	{
		// TODO add parameters and parsing of/for the controller.task format
		return self::getCmd('task');
	}

	/**
	 * Retrieves the view parameter.
	 *
	 * @return string
	 */
	public static function getView()
	{
		return self::getCMD('view');
	}

	/**
	 * Resolves a comma separated list of id values to an array of id values.
	 *
	 * @param   mixed  $idValues  the id values as an array or string
	 *
	 * @return array the id values, empty if the values were invalid or the input was not an array or a string
	 */
	public static function formatIDValues(&$idValues)
	{
		if (is_string($idValues))
		{
			$idValues = explode(',', $idValues);
		}
		elseif (is_array($idValues) and count($idValues) === 1 and preg_match('/^(\d+,)*\d+$/', $idValues[0]) !== -1)
		{
			$idValues = explode(',', $idValues[0]);
		}
		elseif (!is_array($idValues))
		{
			$idValues = [];
		}

		$idValues = ArrayHelper::toInteger($idValues);

		return array_filter($idValues);
	}
}
