<?php

// load in dependencies
session_start();
include('phpFunctions.php');
include('constants.php');

// load db configuration
require_once 'config.php';

// validate PIN for sr_id (if we find a match, this means pin is correct)
$query = "SELECT site_pin 
			FROM fst_grid 
			WHERE quoteNumber = (SElECT quoteNumber FROM fst_pq_ship_request WHERE id = '" . $_POST['sr_id'] . "') 
				AND site_pin = '" . $_POST['pin'] . "';";
$result = mysqli_query($con, $query);
if (mysqli_num_rows($result) == 0)
	echo "Invalid Pin";
else{
	$_SESSION['loggedin'] = True;
	$_SESSION['last_action'] = time();
	$_SESSION['firstName'] = $_POST['first_name'];
	$_SESSION['lastName'] = $_POST['last_name'];
	$_SESSION['email'] = $_POST['email'];
	$_SESSION['temporary_access'] = True;
}