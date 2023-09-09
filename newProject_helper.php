<?php

//init session variables
session_start();

// Load the database configuration file
require_once 'config.php';

//include php functions
include('phpFunctions.php');

//look for tell
//handles new customer entry
if ($_POST['tell'] == "new_customer"){

	//grab next index
	//create query to add note
	$query = "SELECT cust_id FROM fst_customers ORDER BY cust_id desc LIMIT 1;";
	$result = $mysqli->query($query);
	$last_id = mysqli_fetch_array($result);

	//increment last id by 1 (unless less than 2000, then make 2000)
	if ($last_id['cust_id'] < 2000)
		$cust_id = 2000;
	else
		$cust_id = $last_id['cust_id'] + 1;

	//DIMA TO ADD NEW COLUMNS & VALUES TO $query BELOW
	
	//create query to add customer
	$query = "INSERT INTO fst_customers (cust_id, customer, phone, street, city, state, zip, type, created_by, email, date_created) 
								VALUES (" . $cust_id . ", '" . mysql_escape_mimic($_POST['customer']) . "',
										'" . mysql_escape_mimic($_POST['customer_number']) . "', '" . mysql_escape_mimic($_POST['customer_street']) . "',
										'" . mysql_escape_mimic($_POST['customer_city']) . "', '" . mysql_escape_mimic($_POST['customer_state']) . "',
										'" . mysql_escape_mimic($_POST['customer_zip']) . "', '" . mysql_escape_mimic($_POST['customer_type']) . "', 
										'" . mysql_escape_mimic($_POST['user_email']) . "', '" . mysql_escape_mimic($_POST['customer_email']) . "', 
										NOW());" ;

	//call custom_query (executes query (2nd parameter) returns false if successful (true if error is returned) - in phpFunctions.php)
	//outputs error message if error occurs & sends to fst@pw.com for troubleshooting
	if(custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__))
		return;

	//return new id
	echo 'ID|' . $cust_id;

	return;
}

//updates customer info (not complete)
if ($_POST['tell'] == "update_customer"){
	//edit existing notes
	if($_POST['type'] == 1){

		//create query to add note
		$query = "UPDATE fst_notes SET notes = '" . $_POST['note'] . "' WHERE id = '" . $_POST['id'] . "';";
		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

		return;

	}

}

//updates address info
if ($_POST['tell'] == "add-address"){

	//create query to add note
	$query = "UPDATE fst_locations SET street = '" . mysql_escape_mimic($_POST['street']) . "', city = '" . mysql_escape_mimic($_POST['city']) . "', state = '" . mysql_escape_mimic($_POST['state']) . "', zip = '" . mysql_escape_mimic($_POST['zip']) . "', poc_name = '" . mysql_escape_mimic($_POST['name']) . "', poc_number = '" . mysql_escape_mimic($_POST['number']) . "', poc_email = '" . mysql_escape_mimic($_POST['email']) . "', industry = '" . mysql_escape_mimic($_POST['industry']) . "' WHERE id = '" . $_POST['location_id'] . "';";
	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

	return;

}