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

/**
 * Class provides methods for communication with the LSF curriculum documentation system.
 */
class LSF
{
	public $clientSet = false;

	private $client;

	private $username;

	private $password;

	/**
	 * Constructor to set up the client
	 */
	public function __construct()
	{
		$params         = Input::getParams();
		$this->username = $params->get('wsUsername');
		$this->password = $params->get('wsPassword');
		$uri            = $params->get('wsURI');
		$options        = ['uri' => $uri, 'location' => $uri];
		$this->client   = new \SoapClient(null, $options);
	}

	/**
	 * Determines the resource type pool|subject|invalid
	 *
	 * @param   object &$resource
	 *
	 * @return string pool|subject|invalid
	 */
	public static function determineType(&$resource)
	{
		$type = (string) $resource->pordtyp;

		if ($type == 'M')
		{
			return 'subject';
		}

		return (isset($resource->modulliste->modul) and $type == 'K') ? 'pool' : 'invalid';
	}

	/**
	 * Method to perform a soap request based on a certain lsf query
	 *
	 * @param   string  $query  Query structure
	 *
	 * @return mixed  SimpleXMLElement if the query was successful, otherwise false
	 */
	private function getDataXML($query)
	{
		$result = $this->client->__soapCall('getDataXML', ['xmlParams' => $query]);

		if (!$result)
		{
			OrganizerHelper::message('ORGANIZER_SOAP_FAIL', 'error');

			return false;
		}

		if ($result == 'error in soap-request')
		{
			OrganizerHelper::message('ORGANIZER_SOAP_INVALID', 'error');

			return false;
		}

		$xml = simplexml_load_string("<?xml version='1.0' encoding='utf-8'?>" . $result);

		return $xml;
	}

	/**
	 * Method to get the module by mni number
	 *
	 * @param   int  $moduleID  The module mni number
	 *
	 * @return Mixed <void, string, unknown> Returns the xml strucutre of a given lsf module id
	 */
	public function getModuleByModulid($moduleID)
	{
		$XML = $this->header('ModuleAll');
		$XML .= "<modulid>$moduleID</modulid>";
		$XML .= '</condition></SOAPDataService>';

		return self::getDataXML($XML);
	}

	/**
	 * Performs a soap request, in order to get the xml structure of the given
	 * configuration
	 *
	 * @param   string  $program     degree program code
	 * @param   string  $degree      associated degree
	 * @param   string  $accredited  year of accreditation
	 *
	 * @return SimpleXMLElement
	 */
	public function getModules($program, $degree = null, $accredited = null)
	{
		$XML = $this->header('studiengang');
		$XML .= "<stg>$program</stg>";
		$XML .= "<abschl>$degree</abschl>";
		$XML .= "<pversion>$accredited</pversion>";
		$XML .= '</condition></SOAPDataService>';

		return self::getDataXML($XML);
	}

	/**
	 * Creates the header used by all XML queries
	 *
	 * @param   string  $objectType  the LSF object type
	 *
	 * @return string  the header of the XML query
	 */
	private function header($objectType)
	{
		$header = '<?xml version="1.0" encoding="UTF-8"?><SOAPDataService>';
		$header .= "<general><object>$objectType</object></general><user-auth>";
		$header .= "<username>$this->username</username>";
		$header .= "<password>$this->password</password>";
		$header .= '</user-auth><condition>';

		return $header;
	}
}
