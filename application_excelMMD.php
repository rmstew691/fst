<?php

session_start();

// Load the database configuration file
require_once 'config.php';

//grab current time (centeral) for calculations
date_default_timezone_set('America/Chicago');
$date = strtotime(date('d-m-y h:i:s'));

//Step 1: Initialize excel handler
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

//excel formatting to be used later in the document
//add light borders to inside cells of BOM
$thin_borders = [
	'borders' => [
		'allBorders' => [
			'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
		],
	],
];

//add medium borders to outline bom
$medium_borders = [
	'borders' => [
		'outline' => [
			'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM,
		],
	],
];

//add medium borders to outline bom
$header_style = [
	'borders' => [
		'outline' => [
			'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM,
		],
	],
];

// Get info about quote
$quote = $_POST['quote'];
$quoteNumber = substr($quote, 0, strpos($quote, "v"));
$vers = substr($quote,  strpos($quote, "v") + 1, 3); 

//initialize arrays
$d = json_decode($_POST['bom_description']); //descriptions
$m = json_decode($_POST['bom_manufacturer']); //manufacturer
$pn = json_decode($_POST['bom_parts']); //partNumber
$q = json_decode($_POST['bom_quantity']); //quantity
$p = json_decode($_POST['bom_price']); //price
$mmd = json_decode($_POST['bom_mmd']); //mmd

//grab quote template file to use
$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load('ExcelTemplates/MMD-Template.xlsx');
$worksheet = $spreadsheet->getActiveSheet();

//write out basic request information to excel form
$worksheet->getCell('B2')->setValue($_POST['project_name'] . " - " . $_POST['vp_num'] . " - " . $_POST['phase']);
$worksheet->getCell('B3')->setValue(TRIM($_POST['address1'] . " " . $_POST['address2']));
$worksheet->getCell('B4')->setValue($_POST['city'] . ", " . $_POST['state'] . " " . $_POST['zip']);
$worksheet->getCell('B5')->setValue("Customer PN: " . $_POST['customer_pn']);
$worksheet->getCell('G2')->setValue($_POST['vp_num']);

//set header styles
$spreadsheet->getActiveSheet()->getStyle('B8:G8')->getFont()->getColor()->setARGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE);
$spreadsheet->getActiveSheet()->getStyle('B12:G12')->getFont()->getColor()->setARGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE);
$spreadsheet->getActiveSheet()->getStyle('B15:G15')->getFont()->getColor()->setARGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE);

// Insert # of values needed for MMD and Non-MMD
if (isset(array_count_values($mmd)["Yes"]))
	$mmd_count = array_count_values($mmd)["Yes"];
else
	$mmd_count = 0;

if (isset(array_count_values($mmd)["No"]))
	$non_mmd_count = array_count_values($mmd)["No"];
else
	$non_mmd_count = 0;

// Init starting place for both
$start_mmd = 9;
$start_non_mmd = 13 + $mmd_count;

// Insert rows needed for each table
if ($non_mmd_count > 0)
	$worksheet->insertNewRowBefore(14, $non_mmd_count);
if ($mmd_count > 0)
	$worksheet->insertNewRowBefore(10, $mmd_count);

//add BOM (if applicable)
if (sizeof($pn) > 0){

	// Set current count & totals
	$curr_mmd_count = 0;
	$curr_non_mmd_count = 0;
	$mmd_total = 0;
	$non_mmd_total = 0;

    // First MMD
    for ($i = 0; $i < sizeof($pn); $i++){

		// Only add if MMD
		if ($mmd[$i] == "Yes"){
			$worksheet->setCellValueByColumnAndRow(2, $curr_mmd_count + $start_mmd, $d[$i]);
			$worksheet->setCellValueByColumnAndRow(3, $curr_mmd_count + $start_mmd, $m[$i]);
			$worksheet->setCellValueByColumnAndRow(4, $curr_mmd_count + $start_mmd, $pn[$i]);
			$worksheet->setCellValueByColumnAndRow(5, $curr_mmd_count + $start_mmd, $q[$i]);
			$worksheet->setCellValueByColumnAndRow(6, $curr_mmd_count + $start_mmd, $p[$i]);
			$worksheet->setCellValueByColumnAndRow(7, $curr_mmd_count + $start_mmd, floatval($q[$i]) * floatval($p[$i]));
			$mmd_total += floatval($q[$i]) * floatval($p[$i]);
			$curr_mmd_count++;
		}
		elseif ($mmd[$i] == "No"){
			$worksheet->setCellValueByColumnAndRow(2, $curr_non_mmd_count + $start_non_mmd, $d[$i]);
			$worksheet->setCellValueByColumnAndRow(3, $curr_non_mmd_count + $start_non_mmd, $m[$i]);
			$worksheet->setCellValueByColumnAndRow(4, $curr_non_mmd_count + $start_non_mmd, $pn[$i]);
			$worksheet->setCellValueByColumnAndRow(5, $curr_non_mmd_count + $start_non_mmd, $q[$i]);
			$worksheet->setCellValueByColumnAndRow(6, $curr_non_mmd_count + $start_non_mmd, $p[$i]);
			$worksheet->setCellValueByColumnAndRow(7, $curr_non_mmd_count + $start_non_mmd, floatval($q[$i]) * floatval($p[$i]));
			$non_mmd_total += floatval($q[$i]) * floatval($p[$i]);
			$curr_non_mmd_count++;
		}
    }
}

// Set range for adding borders
// Set inner to thin, outer to medium
$bom_range = 'B9:G' . strval($start_mmd + $mmd_count - 1);
$worksheet->getStyle($bom_range)->applyFromArray($thin_borders);
$bom_range = 'B' . strval($start_non_mmd) . ':G' . strval($start_non_mmd + $non_mmd_count - 1);
$worksheet->getStyle($bom_range)->applyFromArray($thin_borders);

// Set total rows
// MMD
$worksheet->mergeCells('B' . strval($start_mmd + $mmd_count) . ':F' . strval($start_mmd + $mmd_count));
$worksheet->getStyle('B' . strval($start_mmd + $mmd_count))->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
$mmd_range = 'B' . strval($start_mmd + $mmd_count) . ':G' . strval($start_mmd + $mmd_count);
$worksheet->getStyle($mmd_range)->getFont()->setBold(true);
$worksheet->getStyle($mmd_range)->applyFromArray($thin_borders);
$worksheet->getCell('B' . strval($start_mmd + $mmd_count))->setValue("Total MMD Materials");
$worksheet->getCell('G' . strval($start_mmd + $mmd_count))->setValue($mmd_total);

// Non-MMD
$worksheet->mergeCells('B' . strval($start_non_mmd + $non_mmd_count) . ':F' . strval($start_non_mmd + $non_mmd_count));
$worksheet->getStyle('B' . strval($start_non_mmd + $non_mmd_count))->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
$non_mmd_range = 'B' . strval($start_non_mmd + $non_mmd_count) . ':G' . strval($start_non_mmd + $non_mmd_count);
$worksheet->getStyle($non_mmd_range)->getFont()->setBold(true);
$worksheet->getStyle($non_mmd_range)->applyFromArray($thin_borders);
$worksheet->getCell('B' . strval($start_non_mmd + $non_mmd_count))->setValue("Total Non-MMD Materials");
$worksheet->getCell('G' . strval($start_non_mmd + $non_mmd_count))->setValue($non_mmd_total);

// Total Materials
$worksheet->getCell('G' . strval($start_non_mmd + $non_mmd_count + 3))->setValue($mmd_total + $non_mmd_total);
$worksheet->getCell('G' . strval($start_non_mmd + $non_mmd_count + 4))->setValue($mmd_total + $non_mmd_total);

//autofit columns B - F
//$worksheet->getColumnDimension('B')->setAutoSize(true);
$worksheet->getColumnDimension('C')->setAutoSize(true);
$worksheet->getColumnDimension('D')->setAutoSize(true);
$worksheet->getColumnDimension('E')->setAutoSize(true);
$worksheet->getColumnDimension('F')->setAutoSize(true);
$worksheet->getColumnDimension('G')->setAutoSize(true);

//save to excel and download, then remove from server
//excel name
$date = date('m-d-Y');
$excel = $_POST['file_name'];

// We'll be outputting an excel file
header('Content-type: application/vnd.ms-excel');

// It will be called file.xls
header('Content-Disposition: attachment; filename="' . $excel. '"');

// Write file to the browser
$writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
//$writer->save('php://output');

//save as xlsx
$writer->save($excel);