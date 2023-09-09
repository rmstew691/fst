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

//initialize arrays
$d = []; 	//descriptions
$m = []; 	//manufacturer
$pn = []; 	//partNumber
$q = []; 	//quantity

//Step 2: Grab parts from fst_boms and load into necessary arrays
//Step 2.1: Check if this request is coming from the warehouse (if so, we need to populate all of this info right now)
if ($_POST['type'] == "warehouse"){

	//use pq_id to get quote number, from quote number, generate any other info needed
	$query = "select quoteNumber from fst_pq_overview WHERE id = '" . $_POST['pq_id'] . "';";
	$result = mysqli_query($con, $query);
	
	if (mysqli_num_rows($result) > 0){

		//grab quotenumber
		$pq_overview = mysqli_fetch_array($result);

		//use quote # to query fst_boms and fst_grid for additional info needed
		//fst_boms (bill of materials saved for the quoteNumber)
		$query = "select * from fst_boms WHERE quoteNumber = '" . $pq_overview['quoteNumber'] . "';";
		$result = mysqli_query($con, $query);

		while($rows = mysqli_fetch_assoc($result)){
			array_push($d, $rows['description']);
			array_push($m, $rows['manufacturer']);
			array_push($pn, $rows['partNumber']);
			array_push($q, $rows['quantity']);			
		}

		//check size of arrays
		if (sizeof($pn) == 0){
			echo "ERROR: The BOM cannot be exported. There is an issue with the quote number stored for the given parts request. Please reach out to fst@piersonwireless.com for additional information.";
			return;
		}

		//fst_grid (project info)
		$query = "select * from fst_grid WHERE quoteNumber = '" . $pq_overview['quoteNumber'] . "';";
		$result = mysqli_query($con, $query);
		$grid = mysqli_fetch_array($result);
		
		//update POST variables used
		$_POST['project_name'] = $grid['location_name'];
		$_POST['vp_num'] = $grid['vpProjectNumber'];
		$_POST['phase'] = $grid['phaseName'];

		//update quote and vers
		$quoteNumber = $pq_overview['quoteNumber'];
		$vers = "";
	}
	else{
		echo "ERROR: The BOM cannot be exported. The parts request ID does not appear to have a record in our database. Please reach out to fst@piersonwireless.com for additional information.";
		return;
	}
}
else if ($_POST['type'] == "cop_dashboard"){

	//use quote # to query fst_boms and fst_grid for additional info needed
	//fst_boms (bill of materials saved for the quoteNumber)
	$query = "select * from fst_boms WHERE quoteNumber = '" . $_POST['quoteNumber'] . "';";
	$result = mysqli_query($con, $query);

	while($rows = mysqli_fetch_assoc($result)){
		array_push($d, $rows['description']);
		array_push($m, $rows['manufacturer']);
		array_push($pn, $rows['partNumber']);
		array_push($q, $rows['quantity']);			
	}

	//check size of arrays
	if (sizeof($pn) == 0){
		echo "ERROR: The BOM cannot be exported. There is an issue with the quote number stored for the given parts request. Please reach out to fst@piersonwireless.com for additional information.";
		return;
	}

	//update quote and vers
	$quoteNumber = $_POST['quoteNumber'];
	$vers = "";
}
else{

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
	
}


//grab quote template file to use
$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load('ExcelTemplates/BOM-Template.xlsx');
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
	$worksheet->getCell('E2')->setValue($_POST['vp_num']);
	$worksheet->getCell('E3')->setValue(substr($quoteNumber, strlen($quoteNumber) - 3));
}
//old way
else{
	$worksheet->getCell('E2')->setValue($_POST['vp_num']);
	$worksheet->getCell('E3')->setValue($quoteNumber);
}

//set date submitted and expiration
$worksheet->getCell('E5')->setValue($_POST['date_submitted']);
$worksheet->getCell('E6')->setValue($_POST['date_expired']);

//set header styles
//set header styles
$spreadsheet->getActiveSheet()->getStyle('B14:E14')->getFont()->getColor()->setARGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE);

//init start_bom var
$start_bom = 15;

//add BOM (if applicable)
if (sizeof($pn) > 0){

    //cycle through BOM and write out info
    for ($i = 0; $i < sizeof($pn); $i++){
        //write out information for each line
        $worksheet->setCellValueByColumnAndRow(2, $i + $start_bom, $d[$i]); //Description
        $worksheet->setCellValueByColumnAndRow(3, $i + $start_bom, $m[$i]); //Manufacturer
        $worksheet->setCellValueByColumnAndRow(4, $i + $start_bom, $pn[$i]); //Part #
        $worksheet->setCellValueByColumnAndRow(5, $i + $start_bom, $q[$i]); //Quantity
    }
}

//set range for adding borders
$bom_range = 'B15:E' . strval($start_bom + sizeof($pn) - 1);

//set inner to thin, outer to medium
$worksheet->getStyle($bom_range)->applyFromArray($thin_borders);

//autofit columns B - F
//$worksheet->getColumnDimension('B')->setAutoSize(true);
$worksheet->getColumnDimension('C')->setAutoSize(true);
$worksheet->getColumnDimension('D')->setAutoSize(true);
$worksheet->getColumnDimension('E')->setAutoSize(true);

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
//readfile($excel);