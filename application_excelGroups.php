<?php

/*******************
*
* The purpose of this file is to sort a bill of materials for a project into groups designated in general_material_categories (groupings column)
* The request is made from application.php and used the bill of materials loaded into the project from fst_boms and matches quoteNumber in fst_grid
* The files returns nothing, but an excel file will be create once this file is complete. The excel file is removed after it has been downloaded on the users screen
*
*******************/

//required to get access to $_SESSION variables.
session_start();

//establish sql connection
require_once 'config.php';

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

//store quote #
$quote = $_POST['quote'];

//initialize arrays
$d = json_decode($_POST['bom_description']); 	//descriptions
$m = json_decode($_POST['bom_manufacturer']); 	//manufacturer
$pn = json_decode($_POST['bom_parts']); 		//partNumber
$q = json_decode($_POST['bom_quantity']); 		//quantity
$p = json_decode($_POST['bom_price']); 			//price
$c = json_decode($_POST['bom_category']); 		//category
$groups = json_decode($_POST['bom_category']);	// copy of category (to convert to group)

//grab quote template file to use
$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load('ExcelTemplates/Group-Template.xlsx');
$worksheet = $spreadsheet->getActiveSheet();

//the first step will require looping through the bom_categories and finding all groups required to create this excel document
//init categories array
$use_categories = [];

//get material categories & groups
$query = "SELECT * FROM general_material_categories;";
$result = mysqli_query($con, $query);

while ($rows = mysqli_fetch_assoc($result)){

	//get index of group in $use_categories (used for 2nd part of conditional)
	$check_index = array_search($rows['grouping'], array_column($use_categories, 'grouping'));

	//echo $check_index;

	//look for index in $c (if we find it, create new entry un use_categories) (second condition makes sure it is unique)
	if (in_array($rows['category'], $c) && $check_index == null)
		array_push($use_categories, $rows);

	//get indexes in $groups, loop and convert groups to true group
	$ind = array_keys($groups, $rows['category']);

	for ($i = 0; $i < sizeof($ind); $i++){
		$groups[$ind[$i]] = $rows['grouping'];
	}
}

//default current row
$curr_row = 2;	//starts on first header of Group-Template.xlsx

//loop through $use, filter out parts related to a group, run function to add to excel document
for ($i = 0; $i < sizeof($use_categories); $i++){

	//get indexes related to group
	$group_indexes = array_keys($groups, $use_categories[$i]['grouping']);

	//set header info
	$worksheet->setCellValueByColumnAndRow(2, $curr_row, "Provided By Pierson Wireless - " . $use_categories[$i]['grouping']);
	$worksheet->setCellValueByColumnAndRow(2, $curr_row + 1, "Description");
	$worksheet->setCellValueByColumnAndRow(3, $curr_row + 1, "Manufacturer");
	$worksheet->setCellValueByColumnAndRow(4, $curr_row + 1, "Part #");
	$worksheet->setCellValueByColumnAndRow(5, $curr_row + 1, "Quantity");

	//style headers (PW - [group])
	$worksheet->getStyle('B' . strval($curr_row) . ':E' . strval($curr_row))->getFont()->setBold(true);

	//set range for header column
	$header_range = 'B' . strval($curr_row + 1) . ':E' . strval($curr_row + 1);
	$worksheet->getStyle($header_range)->getFont()->getColor()->setARGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE);
	$worksheet->getStyle($header_range)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('114B95');

	//increment row
	$curr_row += 2;

	//set range for adding borders
	$bom_range = 'B' . strval($curr_row - 1) . ':E' . strval($curr_row + sizeof($group_indexes) - 1);

	//set inner to thin, outer to medium
	$worksheet->getStyle($bom_range)->applyFromArray($thin_borders);

	//loop through indexes and add info to sheet
	for ($j = 0; $j < sizeof($group_indexes); $j++){

		$worksheet->setCellValueByColumnAndRow(2, $curr_row, $d[$group_indexes[$j]]); 	//Description
        $worksheet->setCellValueByColumnAndRow(3, $curr_row, $m[$group_indexes[$j]]); 	//Manufacturer
        $worksheet->setCellValueByColumnAndRow(4, $curr_row, $pn[$group_indexes[$j]]); 	//Part #
        $worksheet->setCellValueByColumnAndRow(5, $curr_row, $q[$group_indexes[$j]]); 	//Quantity

		//increment $row
		$curr_row += 1;
	}

	//move $row to next header
	$curr_row += 2;

}

//autofit columns B - F
//$worksheet->getColumnDimension('B')->setAutoSize(true);
$worksheet->getColumnDimension('C')->setAutoSize(true);
$worksheet->getColumnDimension('D')->setAutoSize(true);
$worksheet->getColumnDimension('E')->setAutoSize(true);

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