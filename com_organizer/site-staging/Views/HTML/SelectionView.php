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

use Joomla\CMS\Uri\Uri;
use Organizer\Adapters;
use Organizer\Helpers;
use Organizer\Helpers\HTML;
use Organizer\Helpers\Languages;

/**
 * Class loads the resource form into display context. Specific resource determined by extending class.
 */
abstract class SelectionView extends BaseView
{
	protected $layout = 'selection';

	protected $hiddenFields = [];

	public $sets = [];

	public $preview = false;

	/**
	 * Method to get display
	 *
	 * @param   Object  $tpl  template  (default: null)
	 *
	 * @return void
	 */
	public function display($tpl = null)
	{
		$this->setSets();
		$this->modifyDocument();

		parent::display($tpl);
	}

	/**
	 * Checks whether the view has been set for seeing impaired users.
	 *
	 * @return bool true if the view has been configured for seeing impaired users, otherwise false
	 */
	protected function isSeeingImpaired(): bool
	{
		return (bool) Helpers\Input::getParams()->get('seeingImpaired');
	}

	/**
	 * Modifies document variables and adds links to external files
	 *
	 * @return void
	 */
	protected function modifyDocument()
	{
		$constant = 'ORGANIZER_' . strtoupper(preg_replace('/([a-z])([A-Z])/', '$1_$2', $this->getName()));
		HTML::setMenuTitle($constant);
		HTML::_('bootstrap.framework');

		if ($this->isSeeingImpaired())
		{
			$this->setLayout('export_si');
		}
		else
		{
			HTML::_('behavior.calendar');
			HTML::_('formbehavior.chosen', 'select');
		}

		Languages::script('ORGANIZER_ALL');
		Languages::script('ORGANIZER_COPY_SUBSCRIPTION');
		Languages::script('ORGANIZER_DOWNLOAD');
		Languages::script('ORGANIZER_GENERATE_LINK');
		Languages::script('ORGANIZER_LIST_SELECTION_WARNING');
		Languages::script('ORGANIZER_NONE');

		$rootURI = Uri::root();
		Adapters\Document::addScriptDeclaration("const rootURI = '$rootURI';");
		Adapters\Document::addStyleSheet(Uri::root() . 'components/com_organizer/css/table.css');
	}

	/**
	 * Translates list value/constant pairs, sorts them by their translated texts and creates an array of option objects
	 * from them.
	 *
	 * @param   array  $values  the values/constant pairs for output in the input
	 *
	 * @return array an array of options
	 */
	protected function getOptions(array $values): array
	{
		foreach ($values as $value => $constant)
		{
			$values[$value] = Languages::_($constant);
		}
		asort($values);
		$options = [];
		foreach ($values as $value => $text)
		{
			$options[] = HTML::_('select.option', $value, $text);
		}

		return $options;
	}

	/**
	 * Function to define field sets and fill sets with fields
	 *
	 * @return void sets the fields property
	 */
	abstract protected function setSets();

	/**
	 * Sets a field in a given set, adds a description and translates the title and description.
	 *
	 * @param   string  $fieldName  the name of the field
	 * @param   string  $set        the name of the set
	 * @param   string  $label      the text constant for the label
	 * @param   string  $input      the HTML of the input field
	 *
	 * @return void modifies the sets property
	 */
	protected function setField(string $fieldName, string $set, string $label, string $input)
	{
		$descConstant = "{$label}_EXPORT_DESC";

		$this->sets[$set]['fields'][$fieldName] = [
			'label'       => Languages::_($label),
			'description' => Languages::_($descConstant),
			'input'       => $input
		];
	}

	/**
	 * Adds a list field to a set.
	 *
	 * @param   string  $fieldName  the name of the field
	 * @param   string  $set        the name of the set
	 * @param   array   $values     the value/constant pairs which will define the options
	 * @param   array   $attribs    optional attributes
	 * @param   string  $default    a default value
	 */
	protected function setListField(string $fieldName, string $set, array $values, array $attribs = [], string $default = '')
	{
		$constant       = strtoupper($fieldName);
		$label          = "ORGANIZER_$constant";
		$selectConstant = "ORGANIZER_SELECT_$constant";
		$options        = [HTML::_('select.option', '', Languages::_($selectConstant))];
		$options        += $this->getOptions($values);

		$input = HTML::selectBox($options, $fieldName, $attribs, $default);

		$this->setField($fieldName, $set, $label, $input);
	}

	/**
	 * Adds a field listing resources to a set
	 *
	 * @param   string  $resource  the name of the resource type
	 * @param   string  $set       the name of the set
	 * @param   array   $attribs   optional attributes
	 * @param   bool    $fill      if true the helper class will be called to furnish options
	 *
	 * @return void modifies the set property
	 */
	protected function setResourceField(string $resource, string $set, array $attribs = [], bool $fill = false)
	{
		$rawConstant = strtolower($resource);
		$plural      = Helpers\OrganizerHelper::getPlural($rawConstant);
		$multiple    = !empty($attribs['multiple']);
		if ($multiple)
		{
			$fieldName = "{$rawConstant}IDs";
			$constant  = strtoupper($plural);
		}
		else
		{
			$fieldName = "{$rawConstant}ID";
			$constant  = strtoupper($rawConstant);
		}

		$label          = "ORGANIZER_$constant";
		$selectConstant = "ORGANIZER_SELECT_$constant";
		$options        = [HTML::_('select.option', '', Languages::_($selectConstant))];

		if ($fill)
		{
			/** @var Helpers\Selectable $helper */
			$helper  = 'Organizer\\Helpers\\' . ucfirst($plural);
			$options += $helper::getOptions();
		}

		$input = HTML::selectBox($options, $fieldName, $attribs);

		$this->setField($fieldName, $set, $label, $input);
	}

	/**
	 * Renders the field set
	 *
	 * @param   array  $set
	 */
	protected function renderSet(array $set)
	{
		if (empty($set['fields']))
		{
			return;
		}

		$attributes = empty($set['attributes']) ?
			'' : HTML::implodeAttributes($set['attributes']);

		echo "<div class=\"panel\" $attributes>";
		if (!empty($set['label']))
		{
			echo '<div class="panel-head"><div class="panel-title">';
			echo Languages::_($set['label']);
			echo '</div></div>';
		}
		echo '<div class="panel-body">';
		foreach ($set['fields'] as $fieldName => $field)
		{
			$this->renderField($fieldName, $field);
		}
		echo '</div></div>';
	}

	/**
	 * Renders a field (label, input and containing elements)
	 *
	 * @param   string  $fieldName  the name of the field
	 * @param   array   $field      the field attributes including the input itself
	 *
	 * @return void renders HTML
	 */
	protected function renderField(string $fieldName, array $field)
	{
		$hidden = in_array($fieldName, $this->hiddenFields) ? 'style="display: none;"' : '';
		echo "<div class=\"control-group\" $hidden><div class=\"control-label\">";
		echo '<label title="' . Languages::_($field['description']) . '" for="' . $fieldName . '">';
		echo '<span class="label-text">' . Languages::_($field['label']) . '</span><span class="icon-info"></span>';
		echo '</label>';
		echo '</div><div class="controls">';
		echo $field['input'];
		echo '</div></div>';
	}
}
