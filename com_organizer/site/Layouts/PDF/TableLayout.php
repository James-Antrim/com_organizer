<?php
/**
 * @package     Organizer
 * @extension   com_organizer
 * @author      James Antrim, <james.antrim@nm.thm.de>
 * @copyright   2020 TH Mittelhessen
 * @license     GNU GPL v.3
 * @link        www.thm.de
 */


namespace Organizer\Layouts\PDF;


abstract class TableLayout extends BaseLayout
{
	protected $headers;

	protected $widths;

	/**
	 * Adds a new line, if the length exceeds page length a new page is added.
	 *
	 * @return void
	 */
	protected function addLine()
	{
		$view = $this->view;
		$view->Ln();

		if ($view->GetY() > 275)
		{
			$this->addTablePage();
		}
	}

	/**
	 * Adds a new page to the document and creates the column headers for the table
	 *
	 * @return void
	 */
	protected function addTablePage()
	{
		$view = $this->view;
		$view->AddPage();

		// create the column headers for the page
		$view->SetFillColor(210);
		$view->changeSize(10);
		$initial = true;
		foreach (array_keys($this->headers) as $column)
		{
			if ($initial)
			{
				$view->renderCell($this->widths[$column], 7, $this->headers[$column], $view::CENTER, 'BLRT', 1);
				$initial = false;
				continue;
			}
			$view->renderCell($this->widths[$column], 7, $this->headers[$column], $view::CENTER, 'BRT', 1);
		}
		$view->Ln();

		// reset styles
		$view->SetFillColor(255);
		$view->changeSize(8);
	}
}