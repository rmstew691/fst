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



//Step 2: Grab parts from fst_boms and load into necessary arrays

//grab quote and breakout to just quote with version
//$quote = $_POST['quote'];
if (isset($_POST['quote'])){
	$quote = $_POST['quote'];
}
else{
	$quote = '55230v1'; //testing
}
$quoteNumber = substr($quote, 0, strpos($quote, "v"));
$vers = substr($quote,  strpos($quote, "v") + 1, 3); 

//initialize arrays
$d = json_decode($_POST['bom_description']); //descriptions
$m = json_decode($_POST['bom_manufacturer']); //manufacturer
$pn = json_decode($_POST['bom_parts']); //partNumber
$q = json_decode($_POST['bom_quantity']); //quantity
$p = json_decode($_POST['bom_price']); //price

//grab quote template file to use
$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load('ExcelTemplates/Quote-Template.xlsx');
$worksheet = $spreadsheet->getActiveSheet();

//write out basic request information to excel form
$worksheet->getCell('B2')->setValue($_POST['project_name'] . " - " . $_POST['vp_num'] . " - " . $_POST['phase']);
$worksheet->getCell('B3')->setValue(TRIM($_POST['address1'] . " " . $_POST['address2']));
$worksheet->getCell('B4')->setValue($_POST['city'] . ", " . $_POST['state'] . " " . $_POST['zip']);
$worksheet->getCell('B5')->setValue("Customer PN: " . $_POST['customer_pn']);

$worksheet->getCell('B7')->setValue($_POST['customer']);
$worksheet->getCell('B8')->setValue($_POST['cust_contact']);
$worksheet->getCell('B9')->setValue($_POST['cust_phone']);
$worksheet->getCell('B10')->setValue($_POST['cust_email']);

//check size of quote number (if greateer than 8, treat differently)
if (strlen($quoteNumber) > 8){
	$worksheet->getCell('G2')->setValue($_POST['vp_num']);
	$worksheet->getCell('G3')->setValue(substr($quoteNumber, strlen($quoteNumber) - 3));
}
//old way
else{
	$worksheet->getCell('G2')->setValue($_POST['vp_num']);
	$worksheet->getCell('G3')->setValue($quoteNumber);
}

//set date submitted and expiration
$worksheet->getCell('G5')->setValue($_POST['date_submitted']);
$worksheet->getCell('G6')->setValue($_POST['date_expired']);

//set header styles
$spreadsheet->getActiveSheet()->getStyle('B14:G14')->getFont()->getColor()->setARGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE);
$spreadsheet->getActiveSheet()->getStyle('B17:G17')->getFont()->getColor()->setARGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE);
$spreadsheet->getActiveSheet()->getStyle('B20:G20')->getFont()->getColor()->setARGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE);

//insert # of rows for size of BOM
$worksheet->insertNewRowBefore(16, sizeof($p) + 2); // (right before, # of rows)

//starting row for BOM
$start_bom = 15;
$start_services = 18 + sizeof($p) + 2;

//keep sum while moving through bom
$mat_sum = 0;

//add BOM (if applicable)
if (sizeof($p) > 0){

	//cycle through BOM and write out info
	for ($i = 0; $i < sizeof($p); $i++){
		//write out information for each line
		$worksheet->setCellValueByColumnAndRow(2, $i + $start_bom, $d[$i]); //Description
		$worksheet->setCellValueByColumnAndRow(3, $i + $start_bom, $m[$i]); //Manufacturer
		$worksheet->setCellValueByColumnAndRow(4, $i + $start_bom, $pn[$i]); //Part #
		$worksheet->setCellValueByColumnAndRow(5, $i + $start_bom, $q[$i]); //Quantity
		$worksheet->setCellValueByColumnAndRow(6, $i + $start_bom, $p[$i]); //Price
		$worksheet->setCellValueByColumnAndRow(7, $i + $start_bom, $q[$i] * $p[$i]); //Total Price (price * quantity)

		//add to sum
		$mat_sum += ($q[$i] * $p[$i]);
	}

	//add total Materials line = sum of total price of BOM materials
	$worksheet->setCellValueByColumnAndRow(2, $i + $start_bom, "PW Provided Materials");
	$worksheet->setCellValueByColumnAndRow(7, $i + $start_bom, $mat_sum);
	
	//add material logistics
	$worksheet->setCellValueByColumnAndRow(2, $i + $start_bom + 1, "Material Logistics");
	$worksheet->setCellValueByColumnAndRow(7, $i + $start_bom + 1, $_POST['material_logistics']);
	
	//add total Materials line = sum of total price of BOM materials
	$worksheet->setCellValueByColumnAndRow(2, $i + $start_bom + 2, "Total Materials");
	$worksheet->setCellValueByColumnAndRow(7, $i + $start_bom + 2, $mat_sum + $_POST['material_logistics']);

	//set total both lines to bold
	$worksheet->getStyle('B' . strval($i + $start_bom) . ':G' . strval($i + $start_bom + 2))->getFont()->setBold(true);

	//merge service lines together
	$worksheet->mergeCells('B' . strval($i + $start_bom) . ':F' . strval($i + $start_bom));
	$worksheet->mergeCells('B' . strval($i + $start_bom + 1) . ':F' . strval($i + $start_bom + 1));
	$worksheet->mergeCells('B' . strval($i + $start_bom + 2) . ':F' . strval($i + $start_bom + 2));
	
	//align right
	$worksheet->getStyle('B' . strval($i + $start_bom) . ':B' . strval($i + $start_bom + 2))->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);

	//set range
	$bom_range = 'B15:G' . strval($start_bom + sizeof($p) + 2);

	//set inner to thin, outer to medium
	$worksheet->getStyle($bom_range)->applyFromArray($thin_borders);
	//$worksheet->getStyle($bom_range)->applyFromArray($medium_borders);

	//set to no bold
	//$worksheet->getStyle($bom_range)->getFont()->setBold(false);

}

//move to services (if applicable)
//init service arrays
$service_price = json_decode($_POST['service_price']); //price
$service_description = json_decode($_POST['service_description']); //description
$service_size = 0;
$service_sum = 0;


//cycle through services to see how many lines need to be added
for ($i = 0; $i < sizeof($service_price); $i++){
	
	//check price > 0
	if ($service_price[$i] > 0)
		$service_size++;
	
}

//if no services, remove table
if ($service_size == 0){
	
}
//else go through and add services
else{
	
	//insert # of rows for services
	$worksheet->insertNewRowBefore($start_services + 1, $service_size); // (right before, # of rows)
	
	//cycle through services
	for ($i = 0; $i < sizeof($service_price); $i++){
		
		//check again if price > 0
		if ($service_price[$i] > 0){
			$worksheet->setCellValueByColumnAndRow(2, $i + $start_services, $service_description[$i]); //service line
			$worksheet->setCellValueByColumnAndRow(7, $i + $start_services, $service_price[$i]); //$ amount

			//merge service lines together
			$worksheet->mergeCells('B' . strval($i + $start_services) . ':F' . strval($i + $start_services));

			//add up service lines
			$service_sum += $service_price[$i];
			
		}
		
	}

	//add total Materials line = sum of total price of BOM materials
	$worksheet->setCellValueByColumnAndRow(2, $service_size + $start_services, "Total Services");
	$worksheet->setCellValueByColumnAndRow(7, $service_size + $start_services, $service_sum);

	//set total both lines to bold
	$worksheet->getStyle('B' . strval($service_size + $start_services))->getFont()->setBold(true);
	$worksheet->getStyle('G' . strval($service_size + $start_services))->getFont()->setBold(true);

	//merge service lines together
	$worksheet->mergeCells('B' . strval($service_size + $start_services) . ':F' . strval($service_size + $start_services));

	//align right
	$worksheet->getStyle('B' . strval($service_size + $start_services))->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);

	//set range
	$service_range = 'B' . strval($start_services) . ':G' . strval($start_services + $service_size);

	//set inner to thin, outer to medium
	$worksheet->getStyle($service_range)->applyFromArray($thin_borders);
	//$worksheet->getStyle($service_range)->applyFromArray($medium_borders);
	
	
}


//set summary lines
//(initialize first summary line)
$first_summary = 21;

//add size of services and mats to get to current row
if (sizeof($p) > 0){
	$first_summary += sizeof($p) + 2;
}
else{
	//need to reduce the size since these cells will be removed
}

if ($service_size > 0){
	$first_summary += $service_size;
}
else{
	//need to reduce the size since these cells will be removed
}

//set summary lines
$worksheet->setCellValueByColumnAndRow(7, $first_summary, $mat_sum + $_POST['material_logistics']); //total materials
$worksheet->setCellValueByColumnAndRow(7, $first_summary + 1, $service_sum); //total services
$worksheet->setCellValueByColumnAndRow(7, $first_summary + 2, $mat_sum + $service_sum + $_POST['material_logistics']); //total project (1)

//add scope of work (SOW) and clarifications at the end
$sow_start = $first_summary + 4;

$worksheet->setCellValueByColumnAndRow(2, $sow_start, "Scope Of Work"); //SOW
$worksheet->setCellValueByColumnAndRow(2, $sow_start + 1, $_POST['SOW']); //SOW

//grab clarifications and loop through them, adding to the excel sheet
$clarifications = json_decode($_POST['clarifications']);

//set header
$worksheet->setCellValueByColumnAndRow(2, $sow_start + 3, "Clarifications"); //SOW

for ($i = 0; $i < sizeof($clarifications); $i++){
	$worksheet->setCellValueByColumnAndRow(2, $sow_start + $i + 4, "• " . $clarifications[$i]); //clarification
}


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

//save as xlsx
$writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
$writer->save($excel);

while (!file_exists($excel)){
	//do nothing wait for file to save
}


//Step 5: Force Download to user
/*
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="'.basename($excel).'"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($excel));
flush(); // Flush system output buffer
readfile($excel);
*/

//DO THIS SEPERATELY
//Step 6: Delete the file
//unlink($excel);
//return;


