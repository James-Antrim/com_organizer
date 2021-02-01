<?php
/**
 * @package     Organizer\Layouts\XLS\Rooms
 * @extension   Organizer
 * @author      James Antrim, <james.antrim@nm.thm.de>
 * @copyright   2020 TH Mittelhessen
 * @license     GNU GPL v.3
 * @link        www.thm.de
 */

namespace Organizer\Layouts\XLS\Workload;

use Exception;
use Organizer\Helpers;
use Organizer\Helpers\Languages;
use Organizer\Layouts\XLS\BaseLayout;
use Organizer\Views\XLS\BaseView;
use Organizer\Views\XLS\XLConstants;
use PHPExcel_Worksheet_Drawing;

/**
 * Class generates the room statistics XLS file.
 */
class Workload extends BaseLayout
{
	/**
	 * @var \array[][] Border definitions
	 */
	private $borders = [
		'cell'      => [
			'left'   => [
				'style' => XLConstants::THIN
			],
			'right'  => [
				'style' => XLConstants::THIN
			],
			'bottom' => [
				'style' => XLConstants::THIN
			],
			'top'    => [
				'style' => XLConstants::THIN
			]
		],
		'data'      => [
			'left'   => [
				'style' => XLConstants::MEDIUM
			],
			'right'  => [
				'style' => XLConstants::MEDIUM
			],
			'bottom' => [
				'style' => XLConstants::THIN
			],
			'top'    => [
				'style' => XLConstants::THIN
			]
		],
		'header'    => [
			'left'   => [
				'style' => XLConstants::MEDIUM
			],
			'right'  => [
				'style' => XLConstants::MEDIUM
			],
			'bottom' => [
				'style' => XLConstants::MEDIUM
			],
			'top'    => [
				'style' => XLConstants::MEDIUM
			]
		],
		'signature' => [
			'left'   => [
				'style' => XLConstants::NONE
			],
			'right'  => [
				'style' => XLConstants::NONE
			],
			'bottom' => [
				'style' => XLConstants::NONE
			],
			'top'    => [
				'style' => XLConstants::MEDIUM
			]
		]
	];

	/**
	 * @var array[] Fill definitions
	 */
	private $fills = [
		'header' => [
			'type'  => XLConstants::SOLID,
			'color' => ['rgb' => '80BA24']
		],
		'index'  => [
			'type'  => XLConstants::SOLID,
			'color' => ['rgb' => 'FFFF00']
		],
		'data'   => [
			'type'  => XLConstants::SOLID,
			'color' => ['rgb' => 'DFEEC8']
		]
	];

	/**
	 * @var string[] Height definitions
	 */
	private $heights = [
		'basicField'    => '18.75',
		'sectionHead'   => '13.5',
		'sectionSpacer' => '8.25',
		'spacer'        => '6.25',
		'sum'           => '18.75'
	];

	/**
	 * @var int the id of the person whose workload this displays
	 */
	private $personID;

	/**
	 * @var int the id of the term where the workload was valid
	 */
	private $termID;

	/**
	 * Workload constructor.
	 *
	 * @param   BaseView  $view
	 */
	public function __construct(BaseView $view)
	{
		parent::__construct($view);
		$this->personID = Helpers\Input::getInt('personID');
		$this->termID   = Helpers\Input::getInt('termID');
	}

	/**
	 * Adds the arrow to the end of a function header.
	 *
	 * @param   string  $cell  the cell coordinates
	 *
	 * @return void
	 * @throws Exception
	 */
	private function addArrow(string $cell)
	{
		$objDrawing = new PHPExcel_Worksheet_Drawing();
		$objDrawing->setName('Summe Pfeil');
		$objDrawing->setDescription('Pfeil');
		$objDrawing->setPath(JPATH_COMPONENT_SITE . '/images/redarrow.png');
		$objDrawing->setCoordinates($cell);
		$objDrawing->setHeight($this->heights['sum']);
		$objDrawing->setOffsetX(5);
		$objDrawing->setOffsetY(5);
		$objDrawing->setWorksheet($this->view->getActiveSheet());
	}

	/**
	 * Adds a basic field (label and input box)
	 *
	 * @param   int     $row    the row where the cells should be edited
	 * @param   string  $label  the field label
	 *
	 * @return void
	 * @throws Exception
	 */
	private function addBasicField(int $row, string $label)
	{
		$sheet     = $this->view->getActiveSheet();
		$border    = $this->borders['header'];
		$coords    = "B$row";
		$fill      = $this->fills['header'];
		$cellStyle = $sheet->getStyle($coords);
		$cellStyle->applyFromArray(['borders' => $border, 'fill' => $fill]);
		$cellStyle->getAlignment()->setVertical(XLConstants::CENTER);
		$sheet->setCellValue($coords, $label);
		$cellStyle->getFont()->setSize('11');
		$cellStyle->getFont()->setBold(true);
		$sheet->getStyle("C$row:D$row")->applyFromArray(['borders' => $border]);
	}

	/**
	 * Adds a column header to the sheet
	 *
	 * @param   string  $startCell  the coordinates of the column headers top left most cell
	 * @param   string  $endCell    the coordinates of the column header's bottom right most cell
	 * @param   string  $text       the column header
	 * @param   array   $comments   the comments necessary for clarification of the column's contents
	 * @param   int     $height     the comment height
	 *
	 * @return void
	 * @throws Exception
	 */
	private function addColumnHeader(string $startCell, string $endCell, string $text, $comments = [], $height = 200)
	{
		$view  = $this->view;
		$style = [
			'alignment' => ['horizontal' => XLConstants::CENTER, 'vertical' => XLConstants::TOP, 'wrap' => true],
			'borders'   => $this->borders['header'],
			'fill'      => $this->fills['header'],
			'font'      => ['bold' => true]
		];
		$view->addRange($startCell, $endCell, $style, $text);

		if (!empty($comments))
		{
			foreach ($comments as $comment)
			{
				$this->addComment($startCell, $comment, $height);
			}
		}
	}

	/**
	 * Adds a comment to a specific cell
	 *
	 * @param   string  $cell     the cell coordinates
	 * @param   array   $comment  an associative array with a title and or text
	 * @param   int     $height   the comment height
	 *
	 * @return void
	 * @throws Exception
	 */
	private function addComment(string $cell, array $comment, int $height)
	{
		if (empty($comment['title']) and empty($comment['text']))
		{
			return;
		}

		$sheet = $this->view->getActiveSheet();
		$sheet->getComment($cell)->setWidth(320);
		$sheet->getComment($cell)->setHeight($height);

		if (!empty($comment['title']))
		{
			$commentTitle = $sheet->getComment($cell)->getText()->createTextRun($comment['title']);
			$commentTitle->getFont()->setBold(true);

			if (!empty($comment['text']))
			{
				$sheet->getComment($cell)->getText()->createTextRun("\r\n");
			}
		}

		if (!empty($comment['text']))
		{
			$sheet->getComment($cell)->getText()->createTextRun($comment['text']);
		}

		$sheet->getComment($cell)->getText()->createTextRun("\r\n");
	}

	/**
	 * Adds an event row at the given row number.
	 *
	 * @param   int  $row  the row number
	 *
	 * @return void
	 * @throws Exception
	 */
	private function addEventRow(int $row)
	{
		$sheet  = $this->view->getActiveSheet();
		$border = $this->borders['data'];

		$sheet->mergeCells("C$row:E$row");
		$sheet->mergeCells("K$row:L$row");

		for ($current = 'B'; $current <= 'M'; $current++)
		{
			$cellStyle = $sheet->getStyle("$current$row");

			if ($current === 'B' or $current === 'H' or $current === 'I')
			{
				$cellStyle->applyFromArray(['borders' => $border, 'fill' => $this->fills['index']]);

				continue;
			}

			$cellStyle->applyFromArray(['borders' => $border, 'fill' => $this->fills['data']]);
		}
	}

	/**
	 * Adds an event sub header row at the given row number
	 *
	 * @param   int     $row   the row number
	 * @param   string  $text  the text for the labeling column
	 *
	 * @return void
	 * @throws Exception
	 */
	private function addEventSubHeadRow(int $row, string $text)
	{
		$sheet = $this->view->getActiveSheet();
		$sheet->mergeCells("C$row:E$row");
		$sheet->mergeCells("K$row:L$row");

		for ($current = 'B'; $current <= 'M'; $current++)
		{
			$sheet->getStyle("$current$row")->applyFromArray([
				'borders' => $this->borders['data'],
				'fill'    => $this->fills['header']
			]);

			if ($current === 'B')
			{
				$sheet->setCellValue("B$row", $text);
				$sheet->getStyle("B$row")->getFont()->setBold(true);
				$sheet->getStyle("B$row")->getAlignment()->setWrapText(true);
			}
		}
	}

	/**
	 * Creates a section header
	 *
	 * @param   int     $row       the row number
	 * @param   string  $text      the section header text
	 * @param   string  $function  the function to execute
	 *
	 * @return void
	 * @throws Exception
	 */
	private function addFunctionHeader(int $row, string $text, string $function)
	{
		$view  = $this->view;
		$sheet = $view->getActiveSheet();
		$sheet->getRowDimension($row)->setRowHeight($this->heights['sum']);
		$style = [
			'alignment' => ['vertical' => XLConstants::CENTER],
			'borders'   => $this->borders['header'],
			'fill'      => $this->fills['header'],
			'font'      => ['bold' => true]
		];
		$view->addRange("B$row", "L$row", $style, $text);

		$style['numberformat'] = ['code' => XLConstants::NUMBER_00];
		$sheet->getStyle("M$row")->applyFromArray($style);
		$sheet->setCellValue("M$row", $function);
		$this->addArrow("N$row");
		$sheet->getRowDimension(++$row)->setRowHeight($this->heights['sectionSpacer']);
	}

	/**
	 * Adds an instruction cell to the active sheet.
	 *
	 * @param   int     $row     the row number
	 * @param   float   $height  the row height
	 * @param   string  $text    the cell text
	 * @param   bool    $bold    whether the text should be displayed in a bold font
	 *
	 * @return void
	 * @throws Exception
	 */
	private function addInstruction(int $row, float $height, string $text, $bold = false)
	{
		$sheet  = $this->view->getActiveSheet();
		$coords = 'B' . $row;
		$sheet->getRowDimension($row)->setRowHeight($height);
		$sheet->setCellValue($coords, $text);
		$cellStyle = $sheet->getStyle($coords);
		$cellStyle->getAlignment()->setWrapText(true);

		if ($bold)
		{
			$cellStyle->getFont()->setBold(true);
		}

		$cellStyle->getAlignment()->setVertical(XLConstants::TOP);
		$sheet->getStyle($coords)->getFont()->setSize('14');
	}

	/**
	 * Creates an instructions sheet
	 *
	 * @return void
	 * @throws Exception
	 */
	private function addInstructionSheet()
	{
		$view = $this->view;
		$view->setActiveSheetIndex();
		$sheet     = $view->getActiveSheet();
		$pageSetup = $sheet->getPageSetup();
		$pageSetup->setOrientation(XLConstants::PORTRAIT);
		$pageSetup->setPaperSize(XLConstants::A4);
		$pageSetup->setFitToPage(true);

		$sheet->setTitle('Anleitung');
		$sheet->setShowGridlines(false);
		$sheet->getColumnDimension()->setWidth(5);
		$sheet->getColumnDimension('B')->setWidth(75);
		$sheet->getColumnDimension('C')->setWidth(5);
		$sheet->getRowDimension('1')->setRowHeight('85');

		$this->addLogo('B1', 60, 25);

		$text = 'Mit dem ablaufenden Wintersemester 2017/18 wird ein leicht veränderter B-Bogen in Umlauf ';
		$text .= 'gesetzt. Er dient einer dezi\ndieteren Kostenrechnung. Bitte nutzen Sie ausschließlich diesen ';
		$text .= 'Bogen.';
		$this->addInstruction(2, 90, $text);

		$this->addInstruction(3, 35, 'Hinweise:', true);

		$text = 'In der Spalte "Studiengang" ist eine Auswahlliste für Ihren Fachbereich hinterlegt. ';
		$text .= 'Bitte klicken Sie den entsprechenden Studiengang an.';
		$this->addInstruction(4, 55, $text);

		$text = 'Sollten Sie in der Auswahlliste einen Studiengang nicht finden, so nutzen Sie bitte die ';
		$text .= 'letzte Rubrik "nicht vorgegeben". ';
		$this->addInstruction(5, 55, $text);

		$text = 'Sollte eine Lehrveranstaltung in mehreren Studiengängen sein, so können Sie, dann über ';
		$text .= 'mehrere Zeilen, nach Ihrem Ermessen quoteln.';
		$this->addInstruction(6, 55, $text);

		$this->addInstruction(7, 45, 'So können alle Studiengänge berücksichtigt werden. ');

		$text = 'Sollten Sie eine Lehrveranstaltung gehalten haben, die in mehreren Fachbereichen ';
		$text .= 'angeboten wird, so verfahren Sie bitte analog, nutzen aber die Rubrik "mehrere ';
		$text .= 'Fachbereiche", da dort eine  Auswahlliste hinterlegt ist, die alle Studiengänge ';
		$text .= 'der THM enthält.';
		$this->addInstruction(8, 90, $text);

		$this->addInstruction(9, 20, 'Die Liste ist nach Fachbereichen geordnet.');
		$sheet->getRowDimension('10')->setRowHeight('20');
		$this->addInstruction(11, 20, 'Für Ihre Mühe danke ich Ihnen,');
		$this->addInstruction(12, 20, 'Prof. Olaf Berger');

		$noOutline = ['borders' => ['outline' => ['style' => XLConstants::NONE]]];
		$sheet->getStyle('A1:C12')->applyFromArray($noOutline);
	}

	/**
	 * Adds the THM Logo to a cell.
	 *
	 * @param   string  $cell     the cell coordinates
	 * @param   int     $height   the display height of the logo
	 * @param   int     $offsetY  the offset from the top of the cell
	 *
	 * @return void
	 * @throws Exception
	 */
	private function addLogo(string $cell, int $height, int $offsetY)
	{
		$objDrawing = new PHPExcel_Worksheet_Drawing();
		$objDrawing->setName('THM Logo');
		$objDrawing->setDescription('THM Logo');
		$objDrawing->setPath(JPATH_COMPONENT_SITE . '/images/logo.png');
		$objDrawing->setCoordinates($cell);
		$objDrawing->setHeight($height);
		$objDrawing->setOffsetY($offsetY);
		$objDrawing->setWorksheet($this->view->getActiveSheet());
	}

	/**
	 * Adds the main work sheet to the document.
	 *
	 * @return void
	 * @throws Exception
	 */
	private function addProgramSheet1()
	{
		$view = $this->view;
		$view->createSheet();
		$view->setActiveSheetIndex(2);
		$sheet = $view->getActiveSheet();
		$sheet->setTitle('Studiengänge');
	}

	/**
	 * Adds the main work sheet to the document.
	 *
	 * @return void
	 * @throws Exception
	 */
	private function addProgramSheet2()
	{
		$view = $this->view;
		$view->createSheet();
		$view->setActiveSheetIndex(2);
		$sheet = $view->getActiveSheet();
		$sheet->setTitle('Studiengänge (2)');
	}

	/**
	 * Creates and formats a row to be used for a workload relevant role listing.
	 *
	 * @param   int  $row  the row to add
	 *
	 * @return void
	 * @throws Exception
	 */
	private function addRoleRow(int $row)
	{
		$view   = $this->view;
		$sheet  = $view->getActiveSheet();
		$border = $this->borders['cell'];
		$fill   = $this->fills['data'];

		$view->addRange("B$row", "C$row", ['borders' => $border]);
		$view->addRange("D$row", "G$row", ['borders' => $border]);
		$view->addRange("H$row", "L$row", ['borders' => $border, 'fill' => $fill]);
		$sheet->getStyle("M$row")->applyFromArray(['borders' => $border, 'fill' => $fill]);
	}

	/**
	 * Adds the section which lists held lessons to the worksheet
	 *
	 * @param   int  $row  the current row number
	 *
	 * @return void
	 * @throws Exception
	 */
	private function addSectionA(int $row)
	{
		$this->addSectionHeader($row, "A. Lehrveranstaltungen", true);

		$startRow = $row + 2;
		$endRow   = $row + 4;

		$this->addColumnHeader("B14", "B16", 'ModulNr');

		$text = '„Die Lehrende teilen jeweils am Ende eines Semesters unter thematischer Bezeichnung der ';
		$text .= 'einzelnen Lehrveranstaltungen Art und Umfang ihrer Lehrtätigkeit und die Zahl der ';
		$text .= 'gegebenenfalls mitwirkenden Lehrkräfte, bei Lehrveranstaltungen mit beschränkter ';
		$text .= 'Teilnehmerzahl auch die Zahl der teilnehmenden Studierenden sowie der betreuten ';
		$text .= 'Abschlussarbeiten und vergleichbaren Studienarbeiten der Fachbereichsleitung schriftlich mit. ';
		$text .= 'Wesentliche Unterbrechungen, die nicht ausgeglichen worden sind, sind anzugeben. Bei ';
		$text .= 'Nichterfüllung der Lehrverpflichtung unterrichtet die Fachbereichsleitung die ';
		$text .= 'Hochschulleitung.“';

		$comments = [
			['title' => 'Nur auszufüllen, wenn entsprechende Module definiert und bezeichnet sind.'],
			['title' => 'LVVO vom 10.9.2013, § 4 (5)', 'text' => $text]
		];

		$this->addColumnHeader("C$startRow", "E$endRow", 'Lehrveranstaltung', $comments, 345);

		$comments = [
			['title' => 'Veranstaltungsarten sind:'],
			['text' => 'V – Vorlesung'],
			['text' => 'Ü – Übung'],
			['text' => 'P – Praktikum'],
			['text' => 'S – Seminar']
		];

		$this->addColumnHeader("F$startRow", "F$endRow", 'Art (Kürzel)', $comments, 105);

		$text1 = '"Nach Prüfungsordnungen, Studienordnungen oder Studienplänen nicht vorgesehene Lehrveranstaltungen ';
		$text1 .= 'werden angerechnet, wenn alle nach diesen Vorschriften vorgesehenen Lehrveranstaltungen eines ';
		$text1 .= 'Faches durch hauptberuflich oder nebenberuflich an der Hochschule tätiges wissenschaftliches und ';
		$text1 .= 'künstlerisches Personal angeboten werden."';
		$text2 = 'Damit das Dekanat hier eine entsprechende Zuordnung erkennen kann, sind Lehrumfang, Studiengang und ';
		$text2 .= 'Semester sowie Pflichtstatus anzugeben.';

		$comments = [
			['title' => 'LVVO vom 10.9.2013, § 2 (2):', 'text' => $text1],
			['text' => ' '],
			['text' => $text2]
		];

		$this->addColumnHeader("G$startRow", "G$endRow", "Lehrumfang gemäß SWS Lehrumfang", $comments);

		// TODO what is LVVO?
		// TODO what does the difference in dates say when presenting the same text?

		$comments = [
			['title' => 'LVVO vom 2.8.2006, § 2 (2):', 'text' => $text1],
			['text' => ' '],
			['text' => $text2]
		];

		$this->addColumnHeader("H$startRow", "H$endRow", "Studien-\ngang", $comments);
		$this->addColumnHeader("I$startRow", "I$endRow", 'Semester');

		$comments = [
			['title' => 'LVVO vom 10.9.2013, § 2 (2):', 'text' => $text1],
			['text' => ' '],
			['text' => $text2],
			['text' => 'P: Pflichtmodul'],
			['text' => 'WP: Wahlpflichtmodul'],
			['text' => 'W: Wahlmodul']
		];

		$this->addColumnHeader("J$startRow", "J$endRow", "Pflicht-\nstatus\n(Kürzel)", $comments);

		$text1 = 'explizite Angabe der Wochentage und Stunden nötig; der Verweis auf den Stundenplan reicht nicht aus! ';
		$text1 .= 'Bei Block-Veranstaltungen bitte die jeweiligen Datumsangaben machen!';
		$text2 = '"Lehrveranstaltungen, die nicht in Wochenstunden je Semester ausgedrückt sind, sind entsprechend ';
		$text2 .= 'umzurechnen; je Tag werden höchstens acht Lehrveranstaltungsstunden berücksichtigt."';

		$comments = [
			['text' => $text1],
			['title' => 'LVVO § 2 (7):', 'text' => $text2]
		];

		$this->addColumnHeader("K$startRow", "L$endRow", "Wochentag u. Stunde\n(bei Blockveranst. Datum)", $comments);

		$comments = [
			['title' => 'LVVO § 2 (7):', 'text' => $text2]
		];

		$this->addColumnHeader("M$startRow", "M$endRow", "Gemeldetes\nDeputat\n(SWS)", $comments, 105);

		$row      = $row + 5;
		$ownRange = ['start' => $row, 'end' => $row + 11];
		for ($current = $ownRange['start']; $current <= $ownRange['end']; $current++)
		{
			$this->addEventRow($current);
			$row++;
		}

		$this->addEventSubHeadRow($row++, 'Mehrere Fachbereiche');
		$otherRange = ['start' => $row, 'end' => $row + 3];
		for ($current = $otherRange['start']; $current <= $otherRange['end']; $current++)
		{
			$this->addEventRow($current);
			$row++;
		}

		$this->addEventSubHeadRow($row++, 'Nicht vorgegeaben');
		$unknownRange = ['start' => $row, 'end' => $row + 1];
		for ($current = $unknownRange['start']; $current <= $unknownRange['end']; $current++)
		{
			$this->addEventRow($current);
			$row++;
		}

		$ranges = [$ownRange, $otherRange, $unknownRange];

		$this->addSumRow($row++, 'A', $ranges);

		$sheet = $this->view->getActiveSheet();
		$sheet->getRowDimension($row)->setRowHeight($this->heights['spacer']);
	}

	/**
	 * Adds the section which lists thesis supervisions
	 *
	 * @param   int  $row  the current row number
	 *
	 * @return void
	 * @throws Exception
	 */
	private function addSectionB(int $row)
	{
		$comments = [
			['title' => 'Olaf Berger:', 'text' => 'Laut LVVO und HMWK ist eine maximale Grenze von 2 SWS zu beachten.']
		];

		$this->addSectionHeader($row, "B. Betreuung von Studien- und Abschlussarbeiten", true, $comments, 70);

		$startRow = $row + 2;
		$endRow   = $row + 4;

		$text = '"Die Betreuung von Abschlussarbeiten und vergleichbaren Studienarbeiten kann durch die Hochschule ';
		$text .= 'unter Berücksichtigung des notwendigen Aufwandes bis zu einem Umfang von zwei ';
		$text .= 'Lehrveranstaltungsstunden auf die Lehrverpflichtung angerechnet werden;…“';

		$comments = [
			['title' => 'LVVO vom 10.9.2013, §2 (5):', 'text' => $text]
		];

		$this->addColumnHeader("B$startRow", "B$endRow", 'Rechtsgrundlage gemäß LVVO', $comments, 150);

		$label = 'Art der Abschlussarbeit (nur bei Betreuung als Referent/in)';
		$this->addColumnHeader("C$startRow", "F$endRow", $label, $comments, 150);
		$label = 'Umfang der Anrechnung in SWS je Arbeit (insgesamt max. 2 SWS)';
		$this->addColumnHeader("G$startRow", "J$endRow", $label);
		$this->addColumnHeader("K$startRow", "L$endRow", "Anzahl der Arbeiten");
		$this->addColumnHeader("M$startRow", "M$endRow", "Gemeldetes\nDeputat\n(SWS)");
		$row = $endRow + 1;

		$startRow = $row;
		$diploma  = ['text' => 'Betreuung von Diplomarbeit(en) ', 'weight' => .4];
		$this->addSupervisionRow($row++, $diploma);
		$bachelor = ['text' => 'Betreuung von Bachelorarbeit(en) ', 'weight' => .3];
		$this->addSupervisionRow($row++, $bachelor);
		$master = ['text' => 'Betreuung von Masterarbeit(en)', 'weight' => .6];
		$this->addSupervisionRow($row++, $master);
		$projects = ['text' => 'Betreuung von Projekt- und Studienarbeiten, BPS', 'weight' => .15];
		$this->addSupervisionRow($row++, $projects);
		$endRow = $row;
		$doctor = ['text' => 'Betreuung von Promotionen (bis max 6 Semester)', 'weight' => .65];
		$this->addSupervisionRow($row++, $doctor);

		$ranges = [['start' => $startRow, 'end' => $endRow]];
		$this->addSumRow($row++, 'B', $ranges);

		$sheet = $this->view->getActiveSheet();
		$sheet->getRowDimension($row)->setRowHeight($this->heights['spacer']);
	}

	/**
	 * Adds the section which lists roles for which workload is calculated
	 *
	 * @param   int  $row  the current row number
	 *
	 * @return void
	 * @throws Exception
	 */
	private function addSectionC(int $row)
	{
		$sheet = $this->view->getActiveSheet();
		$this->addSectionHeader($row++, "C. Deputatsfreistellungen", true);

		// For the table headers
		$startRow = ++$row;
		$endRow   = ++$row;

		$this->addColumnHeader("B$startRow", "C$endRow", 'Rechtsgrundlage gemäß LVVO');
		$this->addColumnHeader("D$startRow", "G$endRow", 'Grund für Deputatsfreistellung');
		$label = 'Bezeichnung aus dem Genehmigungsschreiben bzw. Dekanatsunterlagen';
		$this->addColumnHeader("H$startRow", "L$endRow", $label);
		$this->addColumnHeader("M$startRow", "M$endRow", "Gemeldetes\nDeputat\n(SWS)");
		$row++;

		// For the table
		$startRow = $row;

		$this->addRoleRow($row);
		$sheet->setCellValue("B$row", 'LVVO § 5 (1)');
		$title = 'LVVO § 5 (1):';
		$text  = '"Bei Wahrnehmung einer Funktion in der Hochschulleitung kann die Lehrverpflichtung um bis zu 100 ';
		$text  .= 'Prozent, bei Wahrnehmung einer Funktion in der Fachbereichsleitung um bis zu 75 Prozent ermäßigt ';
		$text  .= 'werden. Soweit eine Ermäßigung für mehrere Personen in der Fachbereichsleitung erfolgt, ';
		$text  .= 'darf die durchschnittliche Ermäßigung 50 Prozent nicht übersteigen."';
		$this->addComment("B$row", ['title' => $title, 'text' => $text], 200);

		$sheet->setCellValue("D$row", 'Dekanatsamt (Dekan, Studiendekan, Prodekan)');
		$row++;

		$this->addRoleRow($row);
		$sheet->setCellValue("B$row", "LVVO § 5 (2, 4 und 5)");
		$title = "LVVO vom 10.9.2013, § 5 (2):";
		$text  = '"Die Lehrverpflichtung kann für die Wahrnehmung weiterer Aufgaben und Funktionen innerhalb der ';
		$text  .= 'Hochschule, insbesondere für besondere Aufgaben der Studienreform, für die Leitung von ';
		$text  .= 'Sonderforschungsbereichen und für Studienfachberatung unter Berücksichtigung des Lehrbedarfs im ';
		$text  .= 'jeweiligen Fach ermäßigt werden; die Ermäßigung soll im Einzelfall zwei Lehrveranstaltungsstunden ';
		$text  .= 'nicht überschreiten. Für die Teilnahme an der Entwicklung und Durchführung von hochschuleigenen ';
		$text  .= 'Auswahlverfahren und von Verfahren nach § 54 Abs. 4 des Hessischen Hochschulgesetzes sowie für die ';
		$text  .= 'Wahrnehmung der Mentorentätigkeit nach § 14 Satz 5 des Hessischen Hochschulgesetzes erhalten ';
		$text  .= 'Professorinnen und Professoren keine Ermäßigung der Lehrverpflichtung."';
		$this->addComment("B$row", ['title' => $title, 'text' => $text], 200);
		$sheet->getComment("B$row")->getText()->createTextRun("\r\n");
		$title = 'LVVO vom 10.9.2006, §5 (4):';
		$text  = '"An Fachhochschulen kann die Lehrverpflichtung für die Wahrnehmung von Forschungs- und ';
		$text  .= 'Entwicklungsaufgaben, für die Leitung und Verwaltung von zentralen Einrichtungen der Hochschule, ';
		$text  .= 'die Betreuung von Sammlungen einschließlich Bibliotheken sowie die Leitung des Praktikantenamtes ';
		$text  .= 'ermäßigt werden; die Ermäßigung soll zwölf Prozent der Gesamtheit der Lehrverpflichtungen der ';
		$text  .= 'hauptberuflich Lehrenden und bei einzelnen Professorinnen und Professoren vier ';
		$text  .= 'Lehrveranstaltungsstunden nicht überschreiten. Die personenbezogene Höchstgrenze gilt nicht im ';
		$text  .= 'Falle der Wahrnehmung von Forschungs- und Entwicklungsaufgaben. Soweit aus Einnahmen von ';
		$text  .= 'Drittmitteln für Forschungs- und Entwicklungsaufträge oder Projektdurchführung Lehrpersonal ';
		$text  .= 'finanziert wird, kann die Lehrverpflichtung von Professorinnen und Professoren in dem ';
		$text  .= 'entsprechenden Umfang auf bis zu vier Lehrveranstaltungsstunden reduziert werden; diese ';
		$text  .= 'Ermäßigungen sind auf die zulässige Höchstgrenze der Ermäßigung der Gesamtlehrverpflichtung ';
		$text  .= 'nicht anzurechnen. Voraussetzung für die Übernahme von Verwaltungsaufgaben ist, dass ';
		$text  .= 'diese Aufgaben von der Hochschulverwaltung nicht übernommen werden können und deren Übernahme ';
		$text  .= 'zusätzlich zu der Lehrverpflichtung wegen der damit verbundenen Belastung nicht zumutbar ist."';
		$this->addComment("B$row", ['title' => $title, 'text' => $text], 200);
		$sheet->getComment("B$row")->getText()->createTextRun("\r\n");
		$title = 'LVVO vom 10.9.2013, § 5 (5):';
		$text  = '"Liegen mehrere Ermäßigungsvoraussetzungen nach Abs. 1 bis 4 Satz 2 vor, soll die Lehrtätigkeit im ';
		$text  .= 'Einzelfall während eines Semesters 50 vom Hundert der jeweiligen Lehrverpflichtung nicht ';
		$text  .= 'unterschreiten."';
		$this->addComment("B$row", ['title' => $title, 'text' => $text], 200);

		$sheet->setCellValue("D$row", 'Weitere Deputatsreduzierungen');
		$row++;
		$endRow = $row;

		$this->addRoleRow($row);

		$sheet->setCellValue("B$row", "LVVO § 6");
		$title = "LVVO vom 10.9.2013, § 6:";
		$text  = "„Die Lehrverpflichtung schwerbehinderter Menschen im Sinne des Neunten Buches Sozialgesetzbuch - ";
		$text  .= "Rehabilitation und Teilhabe behinderter Menschen - vom 19. Juni 2001 (BGBl. I S. 1046, 1047), ";
		$text  .= "zuletzt geändert durch Gesetz vom 14. Dezember 2012 (BGBl. I S. 2598), kann auf Antrag von der ";
		$text  .= "Hochschulleitung ermäßigt werden.“";
		$this->addComment("B$row", ['title' => $title, 'text' => $text], 200);

		$sheet->setCellValue("D$row", 'Schwerbehinderung');
		$row++;

		$ranges = [['start' => $startRow, 'end' => $endRow]];
		$this->addSumRow($row++, 'C', $ranges);

		$sheet->getRowDimension($row)->setRowHeight($this->heights['spacer']);
	}

	/**
	 * Creates a section header
	 *
	 * @param   int     $row       the row number
	 * @param   string  $text      the section header text
	 * @param   bool    $break     whether or not a break should be displayed
	 * @param   array   $comments  an array of tips with title and/or text
	 * @param   int     $cHeight   the comment height
	 *
	 * @return void
	 * @throws Exception
	 */
	private function addSectionHeader(int $row, string $text, $break = false, $comments = [], $cHeight = 200)
	{
		$view  = $this->view;
		$sheet = $view->getActiveSheet();
		$sheet->getRowDimension($row)->setRowHeight($this->heights['sectionHead']);
		$style = [
			'alignment' => ['vertical' => XLConstants::CENTER],
			'borders'   => $this->borders['header'],
			'fill'      => $this->fills['header'],
			'font'      => ['bold' => true]
		];
		$view->addRange("B$row", "M$row", $style, $text);

		if (!empty($comments))
		{
			foreach ($comments as $comment)
			{
				$this->addComment("B$row", $comment, $cHeight);
			}
		}

		if ($break)
		{
			$sheet->getRowDimension(++$row)->setRowHeight($this->heights['sectionSpacer']);
		}
	}

	/**
	 * Adds a row summing section values
	 *
	 * @param   int     $row      the row where the sum will be added
	 * @param   string  $section  the section being summed (used for the label)
	 * @param   array   $ranges   the row ranges to be summed
	 *
	 * @return void
	 * @throws Exception
	 */
	private function addSumRow(int $row, string $section, $ranges = [])
	{
		$sheet  = $this->view->getActiveSheet();
		$border = $this->borders['header'];

		$sheet->getStyle("L$row")->applyFromArray(['borders' => $border, 'fill' => $this->fills['header']]);
		$sheet->getStyle("L$row")->getAlignment()->setHorizontal(XLConstants::CENTER);
		$sheet->setCellValue("L$row", "Summe $section:");
		$sheet->getStyle("L$row")->getFont()->setBold(true);
		$sheet->getStyle("M$row")->applyFromArray(['borders' => $border, 'fill' => $this->fills['index']]);

		if (count($ranges) === 1)
		{
			$formula = "=SUM(M{$ranges[0]['start']}:M{$ranges[0]['end']})";
		}
		else
		{
			$sums = [];
			foreach ($ranges as $range)
			{
				$sums[] = "SUM(M{$range['start']}:M{$range['end']})";
			}
			$formula = '=SUM(' . implode(',', $sums) . ')';
		}

		$sheet->setCellValue("M$row", $formula);
	}

	/**
	 * Creates a row evaluating the valuation of a type and quantity of supervisions
	 *
	 * @param   int    $row       the row number
	 * @param   array  $category  an array containing the category text and it's calculation weight
	 *
	 * @return void
	 * @throws Exception
	 */
	private function addSupervisionRow(int $row, array $category)
	{
		$view   = $this->view;
		$sheet  = $view->getActiveSheet();
		$border = $this->borders['cell'];

		$sheet->getStyle("B$row")->applyFromArray(['borders' => $border]);
		$sheet->setCellValue("B$row", 'LVVO § 2 (5)');
		$view->addRange("C$row", "F$row", ['borders' => $border], $category['text']);
		$view->addRange("G$row", "J$row", ['borders' => $border], $category['weight']);
		$sheet->getStyle("G$row")->getNumberFormat()->setFormatCode(XLConstants::NUMBER_00);
		$view->addRange("K$row", "L$row", ['borders' => $border]);
		$sheet->getStyle("K$row")->getNumberFormat()->setFormatCode(XLConstants::FORMAT_NUMBER);
		$sheet->getStyle("M$row")->applyFromArray(['borders' => $border]);
		$sheet->getStyle("M$row")->getNumberFormat()->setFormatCode(XLConstants::NUMBER_00);
		$sheet->setCellValue("M$row", '=IF(K' . $row . '<>0,G' . $row . '*K' . $row . ',0)');
	}

	/**
	 * Adds the main work sheet to the document.
	 *
	 * @return void
	 * @throws Exception
	 */
	private function addWorkSheet()
	{
		$view = $this->view;
		$view->createSheet();
		$view->setActiveSheetIndex(1);

		$sheet = $view->getActiveSheet();
		$sheet->setTitle('B-Bogen');
		$this->formatWorkSheet();

		$sheet->getRowDimension('1')->setRowHeight('66');
		$this->addLogo('B1', 60, 10);

		$sheet->getRowDimension('2')->setRowHeight('22.5');
		$style = [
			'alignment' => ['horizontal' => XLConstants::CENTER, 'vertical' => XLConstants::CENTER],
			'borders'   => $this->borders['header'],
			'fill'      => $this->fills['header'],
			'font'      => ['bold' => true, 'size' => 14]
		];
		$text  = 'Bericht über die Erfüllung der Lehrverpflichtung gemäß § 4 (5) LVVO (Version 1.4; Stand 07.02.2018)';
		$view->addRange("B2", "M2", $style, $text);

		$sheet->getRowDimension('3')->setRowHeight($this->heights['sectionSpacer']);

		$sheet->getRowDimension('4')->setRowHeight($this->heights['basicField']);
		$this->addBasicField(4, 'Fachbereich');
		$sheet->getRowDimension('5')->setRowHeight($this->heights['spacer']);

		$sheet->getRowDimension('6')->setRowHeight($this->heights['basicField']);
		$this->addBasicField(6, 'Semester');
		$sheet->setCellValue('C6', Helpers\Terms::getName($this->termID));
		$sheet->getRowDimension('7')->setRowHeight($this->heights['spacer']);

		$sheet->getRowDimension('8')->setRowHeight($this->heights['basicField']);
		$this->addBasicField(8, 'Name');
		$sheet->setCellValue('C8', Helpers\Persons::getSurname($this->personID));
		$sheet->getRowDimension('9')->setRowHeight($this->heights['spacer']);

		$sheet->getRowDimension('10')->setRowHeight($this->heights['basicField']);
		$this->addBasicField(10, 'Vorname');
		$sheet->setCellValue('C10', Helpers\Persons::getForename($this->personID));
		$sheet->getRowDimension('11')->setRowHeight($this->heights['spacer']);

		$color = '9C132E';
		$style = [
			'alignment' => ['horizontal' => XLConstants::CENTER, 'vertical' => XLConstants::CENTER, 'wrap' => true],
			'borders'   => [
				'left'   => ['style' => XLConstants::THIN, 'color' => ['rgb' => $color]],
				'right'  => ['style' => XLConstants::THIN, 'color' => ['rgb' => $color]],
				'bottom' => ['style' => XLConstants::THIN, 'color' => ['rgb' => $color]],
				'top'    => ['style' => XLConstants::THIN, 'color' => ['rgb' => $color]]
			],
			'font'      => ['bold' => true, 'color' => ['rgb' => $color]]
		];
		$text  = 'Die Tabelle soll in Excel ausgefüllt werden. Durch Kontakt des Cursors mit der kleinen roten ';
		$text  .= 'Markierung in einem entsprechenden Feld öffntet sich ein Infofeld und Sie erhalten weiterführende ';
		$text  .= 'Informationen.';
		$view->addRange('G4', 'K10', $style, $text);

		$this->addSectionA(12);
		$this->addSectionB(39);
		$this->addSectionC(51);

		$function = '=SUM(M37,M49,M59)';
		$this->addFunctionHeader(60, 'D. Gemeldetes Gesamtdeputat (A + B + C) für das Semester', $function);
		$sheet->getRowDimension(61)->setRowHeight($this->heights['spacer']);

		$this->addSectionHeader(62, "E. Deputatsübertrag aus den Vorsemestern");
		$view->addRange('B63', 'L63', ['borders' => $this->borders['cell']], 'Deputatsüberhang / -defizit');

		$sheet->getStyle('M63')->applyFromArray(['borders' => $this->borders['cell'], 'fill' => $this->fills['data']]);
		$sheet->getStyle('M63')->getNumberFormat()->setFormatCode(XLConstants::NUMBER_00);
		$sheet->setCellValue('M63', 0);
		$sheet->getRowDimension(64)->setRowHeight($this->heights['spacer']);

		$this->addSectionHeader(65, "F. Soll-Deputat");
		$sheet->getStyle('M66')->applyFromArray(['borders' => $this->borders['cell'], 'fill' => $this->fills['data']]);
		$sheet->getStyle('M66')->getNumberFormat()->setFormatCode(XLConstants::NUMBER_00);
		$sheet->setCellValue('M66', 18);
		$sheet->getRowDimension(67)->setRowHeight($this->heights['spacer']);

		$function = '=SUM(M60,M63)-M66';
		$this->addFunctionHeader(68, 'G. Saldo zum Ende des Semesters und Deputatsübertrag für Folgesemester', $function);
		$sheet->getStyle('M68')->applyFromArray(['fill' => $this->fills['index']]);
		$sheet->getRowDimension(69)->setRowHeight($this->heights['spacer']);

		$style = ['borders' => $this->borders['cell'], 'fill' => $this->fills['data']];
		$this->addSectionHeader(70, "H. Sonstige Mitteilungen");
		$view->addRange('B71', 'M71', $style);
		$view->addRange('B72', 'M72', $style);
		$view->addRange('B73', 'M73', $style);

		$style = ['font' => ['bold' => true, 'size' => 11]];
		$view->addRange('B75', 'G75', $style, 'Ich versichere die Richtigkeit der vorstehenden Angaben:');
		$view->addRange('H75', 'M75', $style, 'Gegenzeichnung Dekanat:');

		$view->addRange('B77', 'G77', [], 'Gießen/Friedberg, den');
		$view->addRange('H77', 'M77', [], 'Gießen/Friedberg, den');

		$style = ['borders' => $this->borders['signature'], 'font' => ['size' => 11]];
		$view->addRange('C80', 'F80', $style, 'Datum, Unterschrift');
		$view->addRange('J80', 'M80', $style, 'Datum, Unterschrift');

		$sheet->setCellValue('B84', 'Hinweise');
		$sheet->setCellValue('B86', 'Prozessbeschreibung zum Umgang mit diesem Berichtsformular:');

		$text = '(1) Ausfüllen des Excel-Deputatsberichts durch die berichtende Professorin/ den berichtenden ';
		$text .= 'Professor.';
		$sheet->setCellValue('B88', $text);
		$text = '(2) Ausdruck des Formulars auf Papier durch die berichtende Professorin/ den berichtenden Professor.';
		$sheet->setCellValue('B89', $text);
		$sheet->setCellValue('B90', '(3) Versehen mit handschriftlich geleisteter Unterschrift.');
		$text = '(4) Rückgabe des Ausdrucks in Papierform an das Dekanat bis zum jeweils gesetzten Termin. ';
		$text .= 'Archivierung desselben im Dekanat.';
		$sheet->setCellValue('B91', $text);
		$text = 'Ob der B-Bogen noch in elektronischer Form dem Dekanat zugesandt werden sollen, bleibt diesem ';
		$text .= 'überlassen, dies anzuordnen.';
		$sheet->setCellValue('B92', $text);

	}

	/**
	 * @inheritDoc
	 */
	public function fill()
	{
		$this->view->getDefaultStyle()->getFont()->setName('Arial')->setSize();
		$this->addInstructionSheet();
		$this->addWorkSheet();
		$this->addProgramSheet1();
		$this->addProgramSheet2();
		$this->view->setActiveSheetIndex(1);
	}

	/**
	 * Adds formatting attributes for the work sheet.
	 *
	 * @return void
	 */
	private function formatWorkSheet()
	{
		$sheet = $this->view->getActiveSheet();

		$pageSetUp = $sheet->getPageSetup();
		$pageSetUp->setOrientation(XLConstants::PORTRAIT);
		$pageSetUp->setPaperSize(XLConstants::A4);
		$pageSetUp->setFitToPage(true);

		$sheet->setShowGridlines(false);
		$sheet->getColumnDimension()->setWidth(2);
		$sheet->getColumnDimension('B')->setWidth(18);
		$sheet->getColumnDimension('C')->setWidth(10.71);
		$sheet->getColumnDimension('D')->setWidth(10.71);
		$sheet->getColumnDimension('E')->setWidth(9.71);
		$sheet->getColumnDimension('F')->setWidth(10.71);
		$sheet->getColumnDimension('G')->setWidth(11.71);
		$sheet->getColumnDimension('H')->setWidth(10.86);
		$sheet->getColumnDimension('I')->setWidth(10.71);
		$sheet->getColumnDimension('J')->setWidth(10.71);
		$sheet->getColumnDimension('K')->setWidth(11.43);
		$sheet->getColumnDimension('L')->setWidth(13.29);
		$sheet->getColumnDimension('M')->setWidth(14.29);
		$sheet->getColumnDimension('N')->setWidth(2.29);

	}

	/**
	 * @inheritDoc
	 */
	public function getDescription(): string
	{
		$person = Helpers\Persons::getDefaultName($this->personID);
		$term   = Helpers\Terms::getFullName($this->termID);
		$date   = Helpers\Dates::formatDate(date('Y-m-d'));

		return Languages::sprintf('ORGANIZER_WORKLOAD_XLS_DESCRIPTION', $person, $term, $date);
	}

	/**
	 * @inheritDoc
	 */
	public function getTitle(): string
	{
		$person = Helpers\Persons::getLNFName($this->personID);
		$term   = Helpers\Terms::getName($this->termID);

		return Languages::_('ORGANIZER_WORKLOAD') . ": $person - $term";
	}

}