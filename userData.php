<?php

//kill previous session as we are about to start a new one
session_start();

//include phpFunctions (for error_handler())
include('phpFunctions.php');

//used to grab actual link for the current address
$actual_link = "https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

//init return address
$returnAddress = "";

//if we have a return address saved, then make sure we grab it for later in the file
if(isset($_SESSION['returnAddress']) && $_SESSION['returnAddress'] = "")
	$returnAddress = $_SESSION['returnAddress'];

//reset all session variables
session_unset();
session_destroy();
session_start();

// Load the database configuration file
require_once 'config.php';

//initialize variables for user entry (sent from index.php)
$firstName = substr($_POST['nameVal'], 0, strpos($_POST['nameVal'], " "));
$lastName = substr($_POST['nameVal'], strpos($_POST['nameVal'], " ") + 1, 100);
$email = $_POST['emailVal'];
$authID = $_POST['authVal'];

// Check if picVal is valid
if (isset($_POST['picVal']))
	$picURL = $_POST['picVal'];
else{

	// If pic is invalid, use existing pic value
	$query = "SELECT picture from fst_users where email = '".$email."'";
	$result = $mysqli->query($query);

	// if invalid, use previously saved picval
	if ($result->num_rows > 0){
		$user = mysqli_fetch_array($result);
		$picURL = $user['picture'];
	}
	else
		$picURL = "";
}

$timezone_offset_minutes = $_POST['timezone_offset_minutes'] - 60;
$timezone_name = timezone_name_from_abbr("", $timezone_offset_minutes*60, false);

//initialize return message
$return = [];
$return['message'] = "";
$return['dashboard'] = "home.php";

// Check for existing user entry
$query = "SELECT * from fst_users where email = '".$email."'";
$result = $mysqli->query($query);

//check to see if we return any results
if ($result->num_rows > 0){

	//check access level
	$fstUser = mysqli_fetch_array($result);
	
	//if the user has no access, we can return this as the message and exit here
	if ($fstUser['accessLevel'] == "None"){
		$return['message'] = "none";
	}
	else{

		//lets update the users oauth_id, picture, and last modified
		$query = "UPDATE fst_users SET oauth_uid = '" . $authID . "', picture = '". $picURL . "', modified = NOW() WHERE id = '".$fstUser['id']."'";
		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

		//set session variables to use in application
		$_SESSION['loggedin'] = True;
		$_SESSION['last_action'] = time();
		$_SESSION['firstName'] = $firstName;
		$_SESSION['lastName'] = $lastName;
		$_SESSION['email'] = $email;
		$_SESSION['pic'] = $picURL;
		$_SESSION['returnAddress'] = $returnAddress;
		$_SESSION['employeeID'] = $fstUser['id'];
		$_SESSION['timezone'] = $timezone_name;
		$_SESSION['dashboard_link'] = $fstUser['dashboard_link'];

		//update error message based on access level
		if ($fstUser['accessLevel'] == "" || $fstUser['accessLevel'] == null)
			$return['message'] = "newUser";
		else{
			$return['message'] = "success";
			$return['dashboard'] = $fstUser['dashboard_link'];
		}	
	}
}
//recognizes that user is signing into system for the first time
else{

	//insert initial entry into user database
	$query = "INSERT INTO fst_users (id, oauth_provider, oauth_uid, firstName, lastName, fullName, email, picture, created, modified, accessLevel, dashboard_link) 
							VALUES (NULL, 'google', '". $authID ."', '".$firstName."', '".$lastName."', '".$firstName." ".$lastName."', '".$email."', '".$picURL."', NOW(), NOW(), 'None', 'home.php')";
							
	//on success, set session & return to index.php
	//use custom_query (saves error message if encountered)
	if (!custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__)){
		$query = "SELECT * from fst_users where oauth_uid = '".$authID."'";
		$result = $mysqli->query($query);
		$fstUser = mysqli_fetch_array($result);

		// insert value into fst_users_notification as well
		$query = "INSERT INTO fst_users_notifications (id) VALUES ('" . $fstUser['id'] . "');";
		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
		
		// set session variables
		$_SESSION['loggedin'] = True;
		$_SESSION['last_action'] = time();
		$_SESSION['firstName'] = $firstName;
		$_SESSION['lastName'] = $lastName;
		$_SESSION['email'] = $email;
		$_SESSION['pic'] = $picURL;
		$_SESSION['returnAddress'] = $returnAddress;
		$_SESSION['employeeID'] = $fstUser['id'];
		$_SESSION['timezone'] = $timezone_name;
		$_SESSION['dashboard_link'] = $fstUser['dashboard_link'];
		
		//set return message
		$return['message'] = "newUser";
	}
}

//return our error message and dashboard link
echo json_encode($return);
return;