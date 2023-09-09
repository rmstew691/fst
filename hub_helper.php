<?php

//init session variables
session_start();

// Load the database configuration file
require_once 'config.php';

//include constants sheet
include('constants.php');

//include php functions
include('phpFunctions.php');

//handles any adjustments to MO's
if ($_POST['tell'] == "update_mo"){
	
	//create query to update status
	$query = "UPDATE fst_allocations_mo SET `status` = '" . mysql_escape_mimic($_POST['status']) . "' WHERE `id` = " . mysql_escape_mimic($_POST['id']) . ";";
	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

	return;
	
}

//handles any adjustments to inventory
if ($_POST['tell'] == "update_inv"){

	//based on type we will either add or edit

	//add new part
	if($_POST['type'] == "add"){
		
		//create query to add note
		$query = "INSERT INTO invreport (`partCategory`, `partDescription`, `partNumber`, `manufacturer`, `uom`, `cost`, `price`) VALUES ('" . mysql_escape_mimic($_POST['partCategory']) . "', '" . mysql_escape_mimic($_POST['partDescription']) . "', '" . mysql_escape_mimic($_POST['partNumber']) . "', '" . mysql_escape_mimic($_POST['manufacturer']) . "', '" . mysql_escape_mimic($_POST['uom']) . "', '" . mysql_escape_mimic($_POST['cost']) . "', '" . mysql_escape_mimic($_POST['price']) . "')";
		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

		return;

	}
	
	//edit existing part
	if($_POST['type'] == "edit"){

		//create query to add note
		$query = "UPDATE invreport SET 
					`partCategory` = '" . mysql_escape_mimic($_POST['partCategory']) . "', 
					`partDescription` = '" . mysql_escape_mimic($_POST['partDescription']) . "', 
					`manufacturer` = '" . mysql_escape_mimic($_POST['manufacturer']) . "', 
					`uom` = '" . mysql_escape_mimic($_POST['uom']) . "', 
					`price` = '" . mysql_escape_mimic($_POST['price']) . "', 
					`cost` = '" . mysql_escape_mimic($_POST['cost']) . "'
					WHERE `partNumber` = '" . mysql_escape_mimic($_POST['partNumber']) . "';";
		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

		return;

	}
}

//handles any changes to our shipping locations table
if ($_POST['tell'] == "update_shop"){

	//based on type we will either add, edit, or delete

	//add new part
	if($_POST['type'] == "add"){
		
		//create query to add note
		$query = "INSERT INTO general_shippingadd (`customer`, `name`, `recipient`, `address`, `city`, `state`, `zip`, `phone`, `email`) VALUES ('PW', '" . mysql_escape_mimic($_POST['name']) . "', '" . mysql_escape_mimic($_POST['recipient']) . "', '" . mysql_escape_mimic($_POST['address']) . "', '" . mysql_escape_mimic($_POST['city']) . "', '" . mysql_escape_mimic($_POST['state']) . "', '" . mysql_escape_mimic($_POST['zip']) . "', '" . mysql_escape_mimic($_POST['phone']) . "', '" . mysql_escape_mimic($_POST['email']) . "')";
		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

		return;

	}
	
	//edit existing part
	if($_POST['type'] == "edit"){

		//create query to add note
		$query = "UPDATE general_shippingadd SET 
					`name` = '" . mysql_escape_mimic($_POST['name']) . "', 
					`recipient` = '" . mysql_escape_mimic($_POST['recipient']) . "', 
					`address` = '" . mysql_escape_mimic($_POST['address']) . "', 
					`city` = '" . mysql_escape_mimic($_POST['city']) . "', 
					`state` = '" . mysql_escape_mimic($_POST['state']) . "', 
					`zip` = '" . mysql_escape_mimic($_POST['zip']) . "',
					`phone` = '" . mysql_escape_mimic($_POST['phone']) . "', 
					`email` = '" . mysql_escape_mimic($_POST['email']) . "'
					WHERE `name` = '" . mysql_escape_mimic($_POST['old_name']) . "' AND `customer` = 'PW';";
		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

		return;

	}
	
	//delete existing part
	if($_POST['type'] == "delete"){

		//create query to add note
		$query = "DELETE FROM general_shippingadd WHERE `name` = '" . mysql_escape_mimic($_POST['old_name']) . "' AND `customer` = 'PW';";
		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

		return;

	}
}
