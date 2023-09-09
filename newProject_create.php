<?php

/**@author Alex Borchers
 * Handles managing new project creation
 *
 */

// load in all dependencies 
session_start();
include('config.php');
include('phpFunctions.php');
include('constants.php');
include('phpFunctions_drive.php');
include('PHPClasses/Notifications.php');

//Import PHPMailer classes into the global namespace
//These must be at the top of your script, not inside a function
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once 'PHPMailer/Exception.php';
require_once 'PHPMailer/PHPMailer.php';
require_once 'PHPMailer/SMTP.php';

// set functions to be used in the file
function save_query($tell, $folder, $parent_id = null)
{

	//include database config
	include('config.php');

	//save location variables passed from client side
	if ($tell == "location") {

		//build query for new location
		$query = "INSERT INTO fst_locations (id, description, street, city, state, zip, current_phase, poc_name, poc_number, poc_email, industry, folder_id) 
									VALUES ('" . $_POST['location_id'] . "', '" . mysql_escape_mimic($_POST['project_location']) . "', '" . mysql_escape_mimic($_POST['project_street']) . "', '" . mysql_escape_mimic($_POST['project_city']) . "', '" . $_POST['project_state'] . "', '" . $_POST['project_zip'] . "', '" . $_POST['previous_phase'] . "', '" . mysql_escape_mimic($_POST['project_name']) . "', '" . $_POST['project_number'] . "', '" . mysql_escape_mimic($_POST['project_email']) . "', '" . $_POST['project_industry'] . "', '" . $folder . "');";
		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
	}

	//if we already have an id, and just need to update the location folder id
	else if ($tell == "update_location") {
		//build query for new location
		$query = "UPDATE fst_locations SET folder_id ='" . $folder . "' WHERE id = '" . $_POST['location_id'] . "';";
		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
	}

	//handles inserting customer folder id into database
	else if ($tell == "customer") {

		//build query for new customer - folders
		$query = "INSERT INTO fst_cust_folders (folder_id, customer, project_id) VALUES ('" . $folder . "', '" . $_POST['customer_id'] . "', '" . $_POST['location_id'] . "');";
		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
	}

	//handles creating new project
	else if ($tell == "project") {

		//check to see if contract # exists already, if not add to database
		$query = "SELECT * FROM fst_contracts WHERE number = '" . $_POST['contract_num'] . "';";
		$result = mysqli_query($con, $query);

		//if we find a match, use this contract #, if not, create a new one
		if (mysqli_num_rows($result) == 0 && $_POST['type'] == "PM") {
			//save contract # into database
			$query = "INSERT INTO fst_contracts (number, location_id, cust_id, date_assigned) VALUES ('" . $_POST['contract_num'] . "', '" . $_POST['location_id'] . "', '" . $_POST['customer_id'] . "', NOW());";
			custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
		}

		//create google drive link
		$gdrive_link = "https://drive.google.com/drive/folders/" . $folder;

		//save project to database
		$query = "INSERT INTO fst_grid (quoteNumber, location_name, location_id, vpProjectNumber, vpContractNumber, designer, phaseName, projectType, subType, customer, customer_id, customer_pm, market, address, city, state, zip, projectLead, quoteCreator, programCoordinator, opsLead, quoteStatus, googleDriveLink, custID, oemNum, lastUpdate, sow, hideServ_opt, hidelog_opt, analytics_dec, site_walk_date, quote_type, billing_opt, site_pin, cop_distribution) 
								VALUES ('" . $_POST['quote_num'] . "', '" .  mysql_escape_mimic($_POST['project_location']) . "', '" . $_POST['location_id'] . "', '" . $_POST['project_num'] . "', '" . $_POST['contract_num'] . "', '" .  mysql_escape_mimic($_POST['project_designer']) . "', '" .  mysql_escape_mimic($_POST['new_name']) . "', '" . $_POST['project_type'] . "', '" . $_POST['project_sub'] . "', '" . mysql_escape_mimic($_POST['project_customer']) . "', '" . $_POST['customer_id'] . "', '" . mysql_escape_mimic($_POST['project_custLead']) . "', '" . $_POST['project_market'] . "', '" .  mysql_escape_mimic($_POST['project_street']) . "', '" .  mysql_escape_mimic($_POST['project_city']) . "', '" . $_POST['project_state'] . "', '" . $_POST['project_zip'] . "', '" .  mysql_escape_mimic($_POST['project_pwLead']) . "', '" .  mysql_escape_mimic($_POST['project_quoteCreator']) . "', '" .  mysql_escape_mimic($_POST['project_pc']) . "', '" .  mysql_escape_mimic($_POST['project_opsLead']) . "', 'Created', '" . mysql_escape_mimic($gdrive_link) . "', '" . $_POST['project_custNum'] . "', '" . $_POST['project_oemNum'] . "', NOW(), '" . mysql_escape_mimic($_POST['project_prelim_sow']) . "', 'on', 'on', 'on', '" . mysql_escape_mimic($_POST['project_site_walk_date']) . "', '" . $_POST['type'] . "', '" . $_POST['project_requestType'] . "', '" . rand(100000, 999999) . "', '" . mysql_escape_mimic($_POST['project_custLead'] . " (" . $_POST['project_custLead_email']) . ")');";

		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

		// get user info
		$user_info = json_decode($_POST['user_info'], true);

		// Log new project creation
		$notification = new Notifications($con, "job_creation", "", $_POST['quote_num'], $use);
		$notification->log_notification($user_info['id']);

		//process design service request appropriately (function found in phpFunctions.php)
		if ($_POST['project_design_required'] != "No Services Requested")
			$request_id = initiate_service_request($con, "des", $_POST['quote_num'], $_POST['project_design_required'], $_POST['project_des_due_date'], "", $user_info, $_POST['project_quote_due_date'], $_POST['project_prelim_sow'], $_POST['project_site_walk_date']);

		// Log quote status as created
		$notification = new Notifications($con, "quote_status", "Created", $_POST['quote_num'], $use);
		$notification->log_notification($user_info['id']);
	}

	//handles creating new contact
	else if ($tell == "contact") {

		//check to see if PM exists, if so, just update values, else insert it
		$query = "SELECT * FROM fst_contacts WHERE customer = '" . mysql_escape_mimic($_POST['project_customer']) . "' AND project_lead = '" . mysql_escape_mimic($_POST['project_custLead']) . "';";
		$result = mysqli_query($con, $query);

		//if we find a match, just update the existing entry
		if (mysqli_num_rows($result) > 0) {
			//build query for new customer - folders
			$query = "UPDATE fst_contacts SET number = '" . $_POST['project_custLead_number'] . "', email = '" . $_POST['project_custLead_email'] . "' WHERE customer = '" . mysql_escape_mimic($_POST['project_customer']) . "' AND project_lead = '" . mysql_escape_mimic($_POST['project_custLead']) . "';";
			custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
		}
		//else insert new record
		else {
			//build query for new customer - folders
			$query = "INSERT INTO fst_contacts (customer, project_lead, number, email) VALUES ('" . mysql_escape_mimic($_POST['project_customer']) . "', '" . mysql_escape_mimic($_POST['project_custLead']) . "', '" . $_POST['project_custLead_number'] . "', '" . $_POST['project_custLead_email'] . "');";
			custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
		}
	}
}

// generates new contract #
function contract_handler()
{

	//Checking if it's a work order 
	if ($_POST["type"] == "SM")
		return ""; //If yes, return empty

	//include database config
	include('config.php');

	$year = date("y"); //current year (yy)
	$region = substr($_POST['project_market'], 0, 1); //region (r)

	//create region identifier
	$region_abv = $year . $region;

	//create query that looks for customer, location, region, and year, returns any matches
	$query = "select number from fst_contracts where cust_id = '" . $_POST['customer_id'] . "' and location_id = '" . $_POST['location_id'] . "' and left(number, 3) = '" . $region_abv . "' order by number desc limit 1;";
	$result = mysqli_query($con, $query);

	//if we find a match, use this contract #, if not, create a new one
	if (mysqli_num_rows($result) > 0) {
		$contract = mysqli_fetch_array($result);
		return $contract['number'];
	}
	//else create new
	else {
		//find most recent contract # assigned, add 1 to the end
		$query = "select right(number, 4) as abv from fst_contracts where left(number, 3) = '" . $region_abv . "' order by number desc limit 1;";
		$result = mysqli_query($con, $query);

		//increment last contract # by 1 and return
		if (mysqli_num_rows($result) > 0) {
			$contract = mysqli_fetch_array($result);
			$last_4 = intval($contract['abv']); //turns last 4 to integer
			$last_4++; //incremenet by 1
			$last_4 = strval($last_4);

			//need to create abbreveviation of length 4 depending on the new number
			$last_4 = substr("0000", 0, 4 - strlen($last_4)) . $last_4;

			return $region_abv . "-" . $last_4;
		}
		//if this is the first time we've assigned a contract for this region in this year, start with 0001
		else {
			return $region_abv . "-0001";
		}
	}
}

//generates new project #
function project_handler($contract, $loc_id)
{

	//include database config
	include('config.php');

	//look for projects that exist with this contract
	$query = "select current_phase FROM fst_locations where id = '" . $loc_id . "' LIMIT 1;";
	$result = mysqli_query($con, $query);

	//if we find a match, increment the most recent vpProject # by 1 and return
	if (mysqli_num_rows($result) > 0) {
		$phase = mysqli_fetch_array($result);
		$previous_phase = intval($phase['current_phase']); //turns last 2 to integer
		$previous_phase++; //incremenet by 1
		$previous_phase = strval($previous_phase); //turn to string

		//need to create abbreveviation of length 2 depending on the new number
		if (strlen($previous_phase) == 1) {
			$previous_phase = "0" . $previous_phase;
		}
	}
	//if for some reason we don't return anything, lets assume P1
	else {
		$previous_phase = "01";
	}

	//create current phase as int to save to database
	$current_phase = intval($previous_phase);

	//update current phase in database in database
	$query = "UPDATE fst_locations SET current_phase = " . $current_phase . " WHERE id = '" . $loc_id . "';";
	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

	//return contract + new phase number
	return $contract . $previous_phase;
}

// generates new work order #
function work_order_handler()
{

	//include database config
	include('config.php');

	$year = date("y"); //current year (yy)
	$region = substr($_POST['project_market'], 0, 1); //region (r)

	//create region identifier
	$region_abv = $year . $region;

	//create query that looks for most recent work order
	$query = "select wo_number from fst_work_order WHERE LEFT(wo_number, 3) = '" . $region_abv . "' order by wo_number desc limit 1;";
	$result = mysqli_query($con, $query);

	//if we find a match, use this contract #, if not, create a new one
	if (mysqli_num_rows($result) > 0) {
		$wo = mysqli_fetch_array($result);
		$new_wo_number = intval($wo['wo_number']);
		$new_wo_number++;
	}
	//else (must be first of the year)
	else {
		$new_wo_number = $region_abv . "0001";
	}

	//save WO to database
	$query = "INSERT INTO fst_work_order(wo_number) VALUES ('" . $new_wo_number . "')";
	$result = mysqli_query($con, $query);

	//return to user
	return $new_wo_number;
}


//generates new naming convention
function abbreviate_name($name, $number, $tell)
{

	//create new location name based on rules
	if ($tell == "location") {

		//grab abreviation of location id (RM) = (Region Market)
		$location_abv = substr($number, 0, 2);

		//grab length of name
		$len = strlen($name);

		if ($len > 25) {
			$first = substr($name, 0, 21);
			$last = substr($name, $len - 4, 4);
			return $location_abv . " " . $first . "..." . $last;
		} else {
			return $location_abv . " " . $name;
		}
	}

	//create new project name based on rules
	if ($tell == "project") {

		//grab length of name
		$len = strlen($name);

		if ($len > 25) {
			$first = substr($name, 0, 15);
			$last = substr($name, $len - 7, 7);
			return $number . " " . $first . "..." . $last;
		} else {
			return $number . " " . $name;
		}
	}
}

//generates new location ID
function location_id_handler($market)
{

	//include database config
	include('config.php');

	$first = substr($market, 0, 1);
	$second = substr($market, 3, 1);

	//created market string (first digit region, second digit market)
	$market_abv = $first . $second;

	//find last id in the database
	$result = mysqli_query($con, "SELECT id FROM fst_locations WHERE left(id, 2) = '" . $market_abv . "' ORDER BY id DESC LIMIT 1;");
	$last_id = mysqli_fetch_array($result);

	//here till we get things rolling
	if (!isset($last_id['id'])) {
		$last_id['id'] = $market_abv .= "0000";
	}

	return find_next_id($last_id['id']);
}

//read last ID entered, returns new ID.
function find_next_id($last_id)
{

	//charcters used to generate new id
	$characters = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z');

	//this will hold the "end" of the string. Since we're only incremenet 1, we can just save what needs to be changed
	$tail = "";
	$fill = ""; //every time we find a z, we'll add a 0 to this and tag it on the end when we are finished

	//work our way from the end of the string to the start
	for ($i = 5; $i > 0; $i--) {

		//grab character
		$check = substr($last_id, $i, 1);

		//loop through the list to find the index
		for ($j = 0; $j < sizeof($characters); $j++) {
			if ($check == $characters[$j])
				break;
		}

		//if this is not z, we can break and incremenet this position by 1
		if ($j <> sizeof($characters) - 1) {
			$tail .= $characters[$j + 1];
			break;
		} else {
			$fill .= "0";
		}

		//if it is z, we need to set the first character to 0, and check the next

	}

	//build new id
	$new = substr($last_id, 0, $i) . $tail . $fill;
	return $new;
}

//grabs current region folder id 
function region_folder($region)
{

	//include database config
	include('config.php');

	$abv = substr($region, 0, 1);

	//find region folder id in database
	$result = mysqli_query($con, "SELECT folder_id FROM fst_region_folders WHERE region = '" . $abv . "' LIMIT 1;");
	$region_abv = mysqli_fetch_array($result);
	return $region_abv['folder_id'];
}

//create new customer id and return
function new_customer($name)
{

	//include database config
	include('config.php');

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

	//create query to add customer
	$query = "INSERT INTO fst_customers (cust_id, customer) VALUES (" . $cust_id . ", '" . mysql_escape_mimic($name) . "');";
	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

	return $cust_id;
}

//current ajax call handlers used in newProject.php
if (isset($_POST['new_name'])) {

	//this will hold the current parent folder id and will change throughout
	$curr_folder_id = region_folder($_POST['project_market']);
	$sub_tell = false; //will only apply to project level

	//(1) Is this a new location?
	//if this is a new location, we need to save values to the database, lets do that here.
	if ($_POST['location_folder_id'] == "NA") {

		//generate new location id
		$_POST['location_id'] = location_id_handler($_POST['project_market']);

		//take first 2 letters of location id (RM)
		$new_name = abbreviate_name($_POST['project_location'], $_POST['location_id'], "location");

		//run create_folder, do not need to pass, save as curr_folder_id
		$curr_folder_id = create_folder($new_name, $curr_folder_id);
		save_query('location', $curr_folder_id); //saves values to database, grab new location id

	}
	//if this is an existing location, but we do not have a folder assigned to it yet, lets create it here.
	else if ($_POST['location_folder_id'] == "") {
		//take first 2 letters of location id (RM)
		$new_name = abbreviate_name($_POST['project_location'], $_POST['location_id'], "location");
		//run create_folder, do not need to pass, save as curr_folder_id
		$curr_folder_id = create_folder($new_name, $curr_folder_id);
		save_query('update_location', $curr_folder_id); //saves values to database, grab new location id
	} else {
		//just grab existing folder id and move on
		$curr_folder_id = $_POST['location_folder_id'];
	}


	//(2) Is this a new customer?
	//if this is a new customer, we need to create a folder and grab the new id, if not grab the existing (no need to save this instance)
	if ($_POST['customer_folder_id'] == "NA") {

		//check to see if we have customer id
		if ($_POST['customer_id'] == "new") {
			$_POST['customer_id'] = new_customer($_POST['customer']);
		}

		//generate customer name to be saved in folder structure
		$cust_name = $_POST['customer_id'] . "-" . substr($_POST['project_customer'], 0, 10);

		//run create_folder, pass curr_folder_id as the parent folder
		$curr_folder_id = create_folder($cust_name, $curr_folder_id, $sub_tell);
		save_query('customer', $curr_folder_id);
	} else {
		//just grab existing folder id and move on
		$curr_folder_id = $_POST['customer_folder_id'];
	}

	//(3) Now that we have the folder structure in place, lets add a new project to the structure and save that to the database as well. 
	$sub_tell = true;

	//save project to database, create VP# and quote #
	$_POST['contract_num'] = contract_handler(); //function to generate contract #

	if ($_POST['type'] == "PM")
		$_POST['project_num'] = project_handler($_POST['contract_num'], $_POST['location_id']); //based on contract #, generate new project #
	elseif ($_POST['type'] == "SM")
		$_POST['project_num'] = work_order_handler();

	$_POST['quote_num'] = $_POST['project_num'] . "-011"; //first quote will always be 011
	$new_name = abbreviate_name($_POST['new_name'], $_POST['project_num'], "project");

	//create folder with new name
	$curr_folder_id = create_folder($new_name, $curr_folder_id, $sub_tell);

	//save values to database
	save_query('project', $curr_folder_id);

	//if a duplicate quote is entered, copy all other project info (bom, services, SOW, etc.) to new quote
	if ($_POST['project_duplicate_target'] != "" && $_POST['project_duplicate_target'] != null) {
		copy_all_quote_information($_POST['project_duplicate_target'], $_POST['quote_num'], 'duplicate', $con);
	}

	//check if this is a new customer contact, if so, save it to database
	if ($_POST['contact_tell']) {
		save_query('contact', "NA");
	}

	//(5) Send Email to DTVP for visibility (only if this is not a budgetary quote)
	if ($_POST['project_budgetary'] == "No") {

		$mail = new PHPMailer();
		$mail = init_mail_settings($mail);

		//Recipients
		$mail->setFrom($_SESSION['email'], 'Web FST Automated Email System'); //set from (name is optional)
		$mail->AddReplyTo($_SESSION['email'], $_SESSION['firstName'] . " " . $_SESSION['lastName']);
		$mail->AddReplyTo($_SESSION['email'], $_SESSION['firstName'] . " " . $_SESSION['lastName']);

		//add session email as the recipient
		$mail->addAddress($_SESSION['email']);

		//cc design team viewpoint processing
		$mail->addCC('alex.borchers@piersonwireless.com');
		$mail->addCC('paige.barrows@piersonwireless.com');

		//add PW personell (if not testing)
		if ($use == "azure") {
			//go through any personell included and grab emails from fst_users to CC to email
			//Project Owner
			$target = $_POST['project_pwLead'];

			if ($target != "" && $target != "NA") {
				$query = "SELECT email FROM fst_users WHERE firstName = '" . substr($target, 0, strpos($target, " "))  . "' AND lastName = '" . substr($target, strpos($target, " ") + 1) . "' LIMIT 1;";
				$result = mysqli_query($con, $query);

				//if we return a result, add to CC group
				if (mysqli_num_rows($result) > 0) {
					$grid = mysqli_fetch_array($result);
					$mail->addCC($grid['email']);
				}
			}

			//quote creator
			$target = $_POST['project_quoteCreator'];

			if ($target != "" && $target != "NA") {
				$query = "SELECT email FROM fst_users WHERE firstName = '" . substr($target, 0, strpos($target, " "))  . "' AND lastName = '" . substr($target, strpos($target, " ") + 1) . "' LIMIT 1;";
				$result = mysqli_query($con, $query);

				//if we return a result, add to CC group
				if (mysqli_num_rows($result) > 0) {
					$grid = mysqli_fetch_array($result);
					$mail->addCC($grid['email']);
				}
			}

			//designer
			$target = $_POST['project_designer'];

			if ($target != "" && $target != "NA") {
				$query = "SELECT email FROM fst_users WHERE firstName = '" . substr($target, 0, strpos($target, " "))  . "' AND lastName = '" . substr($target, strpos($target, " ") + 1) . "' LIMIT 1;";
				$result = mysqli_query($con, $query);

				//if we return a result, add to CC group
				if (mysqli_num_rows($result) > 0) {
					$grid = mysqli_fetch_array($result);
					$mail->addCC($grid['email']);
				}
			}

			//project coordinator
			$target = $_POST['project_pc'];

			if ($target != "" && $target != "NA") {
				$query = "SELECT email FROM fst_users WHERE firstName = '" . substr($target, 0, strpos($target, " "))  . "' AND lastName = '" . substr($target, strpos($target, " ") + 1) . "' LIMIT 1;";
				$result = mysqli_query($con, $query);

				//if we return a result, add to CC group
				if (mysqli_num_rows($result) > 0) {
					$grid = mysqli_fetch_array($result);
					$mail->addCC($grid['email']);
				}
			}

			//opts lead
			$target = $_POST['project_opsLead'];

			if ($target != "" && $target != "NA") {
				$query = "SELECT email FROM fst_users WHERE firstName = '" . substr($target, 0, strpos($target, " "))  . "' AND lastName = '" . substr($target, strpos($target, " ") + 1) . "' LIMIT 1;";
				$result = mysqli_query($con, $query);

				//if we return a result, add to CC group
				if (mysqli_num_rows($result) > 0) {
					$grid = mysqli_fetch_array($result);
					$mail->addCC($grid['email']);
				}
			}

			//if this is a WO make sure we tag the work order email group
			if ($_POST['type'] == "SM") {
				$mail->addCC("workorders@piersonwireless.com");
			}
		}

		//generate body of email 
		$body = "Hello,<br><br>";
		$body .= "DTVP, please create the following project in Viewpoint:<br><br>";
		$body .= "<b>Project #:</b> " . $_POST['project_num'] . "<br>";
		$body .= "<b>Contract #:</b> " . $_POST['contract_num'] . "<br>";
		$body .= "<b>Location/Description:</b> " . $_POST['project_location'] . " " . $_POST['new_name'] . "<br>";
		$body .= "<b>Address:</b> " . $_POST['project_street'] . ", " . $_POST['project_city'] . ", " . $_POST['project_state'] . " " . $_POST['project_zip'] . "<br>";
		$body .= "<b>Market:</b> " . $_POST['project_market'] . "<br>";
		$body .= "<b>Project Type:</b> " . $_POST['project_type'] . "<br><br>";
		$body .= $_POST['project_num'] . " " . $_POST['project_location'] . " " . $_POST['new_name'] . "<br>";
		$body .= "FST: https://pw-fst.northcentralus.cloudapp.azure.com/FST/application.php?quote=" . $_POST['quote_num'] . "<br>";
		$body .= "Drive: https://drive.google.com/drive/folders/" . $curr_folder_id . "<br><br>";
		$body .= "Thank you.";

		//Content
		$mail->isHTML(true);
		$mail->Subject =  "[DT VP-Processing] [Pre-Bid Creation] " . $_POST['project_num'] . " " . $_POST['project_location'] . " " . $_POST['new_name'];
		$mail->Body = $body;
		$mail->send();

		//close smtp connection
		$mail->smtpClose();
	}

	//return new google drive ID -> this will be passed to the user for the link
	echo $curr_folder_id . "^" . $_POST['quote_num'];
}
