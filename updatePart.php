<?php

// Load dependencies
session_start();
include('phpFunctions.php');
include('PHPClasses/Part.php');

// Load the database configuration file
require_once 'config.php';

//if category is set, this means that we are creating a new part and saving its info
if ($_POST['tell'] == "material_creation"){
	
	//default file name to save
	$save_file_name = "";
	
	//save file to server (if this is set from js)
	if (isset($_FILES["cut_sheet"])){
	
		//current director
		$target_dir = getcwd();

		//build target file path
		$target_file = $target_dir . "\\cutsheets\\" . basename($_FILES["cut_sheet"]["name"]);

		//grab file type from target path
		$fileType = strtolower(pathinfo($target_file,PATHINFO_EXTENSION));

		//pass file name to new_name function, returns unique file name
		$_FILES["cut_sheet"]["name"] = new_name($_FILES["cut_sheet"]["name"], $fileType);

		//recreate file path with new name
		$target_file = $target_dir . "\\cutsheets\\" . basename($_FILES["cut_sheet"]["name"]);

		//add attachment to MO folder on server
		if (move_uploaded_file($_FILES["cut_sheet"]["tmp_name"], $target_file)) {

			//save file name
			$save_file_name = $_FILES["cut_sheet"]["name"];

		}
		else {

			//return error, reset attachment name
			echo "Sorry, there was an error uploading your file.";

		}
	}
	
	//create query
	$query = "INSERT INTO fst_newparts (id, quoteNumber, employeeID, partNumber, description, manufacturer, category, uom, vendor, cost, date, cutsheet, cutsheet_link) VALUES (NULL, '" . $_POST['quote'] . "', '" . $_SESSION['employeeID'] . "', '" . mysql_escape_mimic($_POST['part']) . "', '" . mysql_escape_mimic($_POST['description']) . "', '" . $_POST['manufacturer'] . "', '" . $_POST['category'] . "', '" . $_POST['uom'] . "', '" . $_POST['vendor'] . "', '" . $_POST['cost'] . "', NOW(), '" . mysql_escape_mimic($save_file_name) . "', '" . mysql_escape_mimic($_POST['cut_sheet_link']) . "')";
	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
		
	return;
	
}

// used to update cost & price from application.php
if ($_POST['tell'] == "Cost"){
	
	$part = new part($_POST['part'], $con, $_POST['id']);
	$part->update_cost($_POST['newP'], $_POST['opt'], $_POST['qDate']);	
	return;
	
}
if ($_POST['tell'] == "Price"){
	
	$part = new part($_POST['part'], $con, $_POST['id']);
	$part->update_price($_POST['newP'], $_POST['opt']);
	return;
	
}