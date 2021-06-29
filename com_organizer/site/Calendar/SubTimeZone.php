<?php
/**
 * @package     Organizer
 * @extension   com_organizer
 * @author      James Antrim, <james.antrim@nm.thm.de>
 * @copyright   2021 TH Mittelhessen
 * @license     GNU GPL v.3
 * @link        www.thm.de
 */

namespace Organizer\Calendar;

/**
 * Purpose:
 *
 * Describes the nesting VTimeZone for a given period of time.
 *
 * Description:
 *
 * For a given time zone, there may be multiple unique definitions of the observances over a period of time. Each
 * observance is described using either a "STANDARD" or "DAYLIGHT" sub-component. The collection of these sub-components
 * is used to describe the time zone for a given period of time. The offset to apply at any given time is found by
 * locating the observance that has the last onset date and time before the time in question, and using the offset value
 * from that observance.
 *
 * The collection of properties that are used to define the "STANDARD" and "DAYLIGHT" sub-components include:
 *
 * - The mandatory "DTSTART" property gives the effective onset date and local time for the time zone sub-component
 *   definition. "DTSTART" in this usage MUST be specified as a date with a local time value.
 * - The mandatory "TZOFFSETFROM" property gives the UTC offset that is in use when the onset of this time zone
 *   observance begins. "TZOFFSETFROM" is combined with "DTSTART" to define the effective onset for the time zone
 *   sub-component definition.  For example, the following represents the time at which the observance of Standard Time
 *   took effect in Fall 1967 for New York City:
 *
 *     DTSTART:19671029T020000
 *     TZOFFSETFROM:-0400
 *
 * - The mandatory "TZOFFSETTO" property gives the UTC offset for the time zone sub-component (Standard Time or Daylight
 *   Saving Time) when this observance is in use.
 * - The optional "TZNAME" property is the customary name for the time zone. This could be used for displaying dates.
 * - The onset DATE-TIME values for the observance defined by the time zone sub-component is defined by the "DTSTART",
 *   "RRULE", and "RDATE" properties.
 * - The "RRULE" property defines the recurrence rule for the onset of the observance defined by this time zone
 *   sub-component. Some specific requirements for the usage of "RRULE" for this purpose include:
 *
 *   - If observance is known to have an effective end date, the "UNTIL" recurrence rule parameter MUST be used to
 *     specify the last valid onset of this observance (i.e., the UNTIL DATE-TIME will be equal to the last instance
 *     generated by the recurrence pattern).  It MUST be specified in UTC time.
 *   - The "DTSTART" and the "TZOFFSETFROM" properties MUST be used when generating the onset DATE-TIME values
 *     (instances) from the "RRULE".
 *
 * - The "RDATE" property can also be used to define the onset of the observance by giving the individual onset date
 *   and times. "RDATE" in this usage MUST be specified as a date with local time value, relative to the UTC offset
 *   specified in the "TZOFFSETFROM" property.
 * - The optional "COMMENT" property is also allowed for descriptive explanatory text.
 *
 * Format Definition:
 *
 * standardc = "BEGIN" ":" "STANDARD" CRLF
 *             tzprop
 *             "END" ":" "STANDARD" CRLF
 *
 * tzprop = *(
 *   dtstart / tzoffsetfrom / tzoffsetto - required, can only once
 *   rrule - optional, should only once
 *   comment / iana-prop✓ / rdate / tzname / x-prop✓ - optional, may more than once
 * )
 *
 * @url https://datatracker.ietf.org/doc/html/rfc5545#section-3.6.5
 */
class SubTimeZone extends VComponent
{
	/**
	 * @inheritDoc
	 */
	public function getProps(&$output)
	{
		$this->getIANAProps($output);
		$this->getXProps($output);
	}
}