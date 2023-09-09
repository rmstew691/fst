<?php

//load dependencies
session_start();
require_once 'config.php';
include('constants.php');
include('phpFunctions.php');
include('phpFunctions_drive.php');
include('PHPClasses/Part.php');
include('PHPClasses/Notifications.php');
require_once 'vendor/autoload.php';

//Initialize PHP Mailer (in case we need to send an email)
//These must be at the top of your script, not inside a function
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once 'PHPMailer/Exception.php';
require_once 'PHPMailer/PHPMailer.php';
require_once 'PHPMailer/SMTP.php';

//handles sending email out to material creation team
if ($_POST['tell'] == "mat_creation") {

	//convert incoming array from json
	$issue_parts = json_decode($_POST['issue_parts']);

	//call function (located in phpFunction.php)
	mat_creation_email($issue_parts, $_POST['reference_quote']);

	return;
}

//handles updating contract # given a users request
if ($_POST['tell'] == "get_new_contract") {

	//get quote specific info needed based on quote #
	$query = "SELECT customer_id, location_id, market FROM fst_grid WHERE quoteNumber = '" . $_POST['quote'] . "';";
	$result = mysqli_query($con, $query);
	$grid = mysqli_fetch_array($result);

	//if customer_id or location_id are blank, return error
	if ($grid['customer_id'] == "" || $grid['customer_id'] == null || $grid['location_id'] == "" || $grid['location_id'] == null) {
		echo "Error accessing fields customer_id or location_id in fst_grid. Please make sure they're not blank for this quote.";
		return;
	}

	//use year and region to get first three characters
	$year = date("y"); 							//current year (yy)
	$region = substr($grid['market'], 0, 1); 	//region (r)
	$region_abv = $year . $region;

	//create query that looks for customer, location, region, and year, returns any matches
	$query = "select number from fst_contracts where cust_id = '" . $grid['customer_id'] . "' and location_id = '" . $grid['location_id'] . "' and left(number, 3) = '" . $region_abv . "' order by number desc limit 1;";
	$result = mysqli_query($con, $query);

	//if we find a match, use this contract #, if not, create a new one
	if (mysqli_num_rows($result) > 0 && $_POST['overwrite'] == "No") {
		$contract = mysqli_fetch_array($result);
		$use_contract = $contract['number'];
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

			//update contract to use
			$use_contract = $region_abv . "-" . $last_4;
		}
		//if this is the first time we've assigned a contract for this region in this year, start with 0001
		else {
			$use_contract = $region_abv . "-0001";
		}

		//insert entry into fst_contracts
		$query = "INSERT INTO fst_contracts (`number`, `location_id`, `cust_id`, `date_assigned`) 
									VALUES ('" . $use_contract . "', '" . $grid['location_id'] . "', '" . $grid['customer_id'] . "', NOW());";

		//exit function if we reach an error
		if (custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__))
			return;
	}

	//save to grid
	$query = "UPDATE fst_grid SET vpContractNumber = '" . $use_contract . "' WHERE quoteNumber = '" . $_POST['quote'] . "';";

	//exit function if we reach an error
	if (custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__))
		return;

	//send back contract and return to user
	echo $use_contract;
	return;
}

// Handles refreshing the contact list if a customer changes
if ($_POST['tell'] == "update_customer_contacts") {

	// Create array, run query, save values, return to application.php
	$customer_pm = [];
	$query = 'select * from fst_contacts WHERE customer = "' . mysql_escape_mimic($_POST['customer']) . '" ORDER BY project_lead;';
	$result = mysqli_query($con, $query);
	while ($rows = mysqli_fetch_assoc($result)) {
		array_push($customer_pm, $rows);
	}

	echo json_encode($customer_pm);
	return;
}

//handles any adjustments to notes
if ($_POST['tell'] == "notes") {

	//based on type we will either add, edit, or delete
	//0 = add
	//1 = edit
	//2 = delete

	//add new note
	if ($_POST['type'] == 0) {

		//create query to add note
		$query = "INSERT INTO fst_notes (id, quoteNumber, notes, user, date) VALUES (null, '" . $_POST['quote'] . "', '" . mysql_escape_mimic($_POST['note']) . "', '" . $_SESSION['employeeID'] . "', NOW())";
		if (custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__))
			return;

		//grab previous id
		$id = mysqli_insert_id($con);

		//if we are sending back to home, grab all note info and return, otherwise just send id
		if (!isset($_POST['home']))
			echo $id;
	}

	//edit existing notes
	if ($_POST['type'] == 1) {

		//create query to add note
		$query = "UPDATE fst_notes SET notes = '" . mysql_escape_mimic($_POST['note']) . "' WHERE id = '" . $_POST['id'] . "';";
		if (custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__))
			return;
	}


	//remove existing note
	if ($_POST['type'] == 2) {

		//create query to add note
		$query = "DELETE FROM fst_notes WHERE id = '" . $_POST['id'] . "';";
		if (custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__))
			return;
	}

	//if accessing via home, send back all updated notes
	if (isset($_POST['home'])) {

		//init array
		$notes = [];

		//grabs detail (actual parts requested)
		//change query based on where it is coming from 
		$query = "SELECT CONCAT(b.firstName, ' ', b.lastName) as name, a.* FROM fst_notes a, fst_users b WHERE a.user = b.id ORDER BY a.id desc;";
		$result =  mysqli_query($con, $query);

		while ($rows = mysqli_fetch_assoc($result)) {

			//push temp array to project array
			array_push($notes, $rows);
		}

		//echo return array
		echo json_encode($notes);
	}

	return;
}


//Handles searching a reading back services, travel info, labor rates, etc for another quote
if ($_POST['tell'] == "services") {

	//will hold desc value so we know if nothing comes back
	$desc = false;

	//initialize arrays to send back
	//main services
	$services = [];
	$temp_services = [];

	//materials
	//$materials = [];
	//$temp_materials = [];

	//travel info
	$travel_rates = [];

	//labor rate
	$labor_rates = [];

	//run query to see if this project exists
	$query = "select * from fst_values where quoteNumber = '" . $_POST['reference_quote'] . "' and mainCat NOT IN ('Materials') AND price > 0;";
	$result = mysqli_query($con, $query);

	//check to see if we returned anything
	if (mysqli_num_rows($result) > 0) {
		//flip desc
		$desc = true;

		//loop and add to arrays
		while ($rows = mysqli_fetch_assoc($result)) {
			//clear temp_services
			$temp_services = [];

			//create array for row of services
			$temp_services = array(
				'key' => $rows['key'],
				'mainCat' => $rows['mainCat'],
				'subCat' => $rows['subCat'],
				'phaseCostType' => $rows['phaseCostType'],
				'role' => $rows['role'],
				'people' => $rows['people'],
				'days' => $rows['days'],
				'localPeople' => $rows['localPeople'],
				'grdUber' => $rows['grdUber'],
				'travelLabor' => $rows['travelLabor'],
				'markupPerc' => $rows['markupPerc'],
				'manualOpt' => $rows['manualOpt'],
				'checkBox' => $rows['checkBox'],
				'indiTrav' => $rows['indiTrav'],
				'cost' => $rows['cost'],
				'air_perc' => $rows['air_perc'],
				'lodge_perc' => $rows['lodge_perc'],
				'food_perc' => $rows['food_perc'],
				'grnd_perc' => $rows['grnd_perc'],
			);

			//push to main array
			array_push($services, $temp_services);
		}
	}

	/* phased out on (2.10.22)
	//run query to see if any materials exist for the project
	$query = "select * from fst_boms where quoteNumber = '" . $_POST['reference_quote'] . "' ORDER BY id;";
	$result = mysqli_query($con, $query);
	
	//check to see if we returned anything
	if (mysqli_num_rows($result) > 0) {
		//flip desc
		$desc = true;
		
		//loop and add to arrays
		while($rows = mysqli_fetch_assoc($result)){
			//clear temp_services
			$temp_materials = [];
			
			//create array for row of services
			$temp_materials = array('key'=>$rows['id'], 
								   'partNumber'=>$rows['partNumber'], 
								   'quantity'=>$rows['quantity']
								   );
			
			//push to main array
			array_push($materials, $temp_materials);
		}                                                                                                                                                                                                                                                                  
		
	}
	*/

	//run query to see if travel rates exist
	$query = "select * from fst_travelcosts where quoteNumber = '" . $_POST['reference_quote'] . "' LIMIT 1;";
	$result = mysqli_query($con, $query);

	//check to see if we returned anything
	if (mysqli_num_rows($result) > 0) {
		//grab array
		$travel = mysqli_fetch_array($result);

		$travel_rates = array(
			'airfare' => $travel['airfare'],
			'lodging' => $travel['lodging'],
			'food' => $travel['food'],
			'grdRental' => $travel['grdRental']
		);
	} else {
		$travel_rates = array(
			'airfare' => 0,
			'lodging' => 150,
			'food' => 50,
			'grdRental' => 0
		);
	}

	//run query to see if labor rates exist
	$query = "select * from fst_laborrates where quoteNumber = '" . $_POST['reference_quote'] . "' LIMIT 1;";
	$result = mysqli_query($con, $query);

	//check to see if we returned anything
	if (mysqli_num_rows($result) > 0) {
		//grab array
		$labor = mysqli_fetch_array($result);

		$labor_rates = array(
			'instRate' => $labor['instRate'],
			'supRate' => $labor['supRate'],
			'engRate' => $labor['engRate'],
			'desRate' => $labor['desRate'],
			'projCRate' => $labor['projCRate'],
		);
	} else {
		$labor_rates = array(
			'instRate' => 150,
			'supRate' => 175,
			'engRate' => 175,
			'desRate' => 125,
			'projCRate' => 125,
		);
	}

	if ($desc) {
		//send back arrays and return
		echo json_encode(array($labor_rates, $travel_rates, $services));
	} else {
		echo "None";
	}

	return;
}

//Handles searching a reading back sow and clarifications
if ($_POST['tell'] == "steal-sow") {

	//will hold desc value so we know if nothing comes back
	$desc = false;

	//initialize arrays to send back
	$sow = "";
	$clarifications = [];

	//grab quote
	$quote = $_POST['reference_quote'];

	//run query to see if sow exists
	$query = "select * from fst_grid WHERE quoteNumber = '" . $quote . "' LIMIT 1;";
	$result = mysqli_query($con, $query);

	//check to see if we returned anything
	if (mysqli_num_rows($result) > 0) {
		//flip desc
		$desc = true;

		//grab array of values
		$grid = mysqli_fetch_array($result);
	}

	//run query to see if clarifications exists
	$query = "select * from fst_quote_clarifications WHERE type = 1 AND quoteNumber = '" . $_POST['reference_quote'] . "' order by type, clar_order;";
	$result = mysqli_query($con, $query);

	//check to see if we returned anything
	if (mysqli_num_rows($result) > 0) {
		//flip desc
		$desc = true;

		//loop and add to arrays
		while ($rows = mysqli_fetch_assoc($result)) {
			//clear temp_services
			$temp_clar = [];

			//create array for row of services
			$temp_clar = array(
				'type' => $rows['type'],
				'clar_id' => $rows['clar_id'],
				'clar_full' => $rows['clar_full']
			);

			//push to main array
			array_push($clarifications, $temp_clar);
		}
	}

	//if we found something, return both, if not, return none
	if ($desc) {
		//send back arrays and return
		echo json_encode(array($grid, $clarifications));
	} else {
		echo "None";
	}

	return;
}

//handles all new info coming from existing quote
if ($_POST['tell'] == "new_info") {

	//decode arrays on server
	$new_info = json_decode($_POST['selected_info']);

	//loop through each iteration and add info if necessary
	//$new_info[0] = labor rates / we won't enter loop if there are none applicable
	if (sizeof($new_info[0]) > 0) {
		$query = "REPLACE INTO fst_laborrates (quoteNumber, instRate, supRate, engRate, desRate, projCRate) 
						VALUES ('" . $_POST['quote'] . "', 
								'" . $new_info[0][0] . "', 
								'" . $new_info[0][1] . "', 
								'" . $new_info[0][2] . "', 
								'" . $new_info[0][3] . "', 
								'" . $new_info[0][4] . "' );";
		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
	}

	//$new_info[1] = travel rates / we won't enter loop if there are none applicable
	if (sizeof($new_info[1]) > 0) {
		$query = "REPLACE INTO fst_travelcosts (quoteNumber, airfare, lodging, food, grdRental) 
						VALUES ('" . $_POST['quote'] . "', 
								'" . $new_info[1][0] . "', 
								'" . $new_info[1][1] . "', 
								'" . $new_info[1][2] . "', 
								'" . $new_info[1][3] . "' );";
		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
	}

	//$new_info[2] = services / we won't enter loop if there are none applicable
	for ($i = 0; $i < sizeof($new_info[2]); $i++) {
		//run query/grab array of values
		$query = "SELECT * FROM fst_values WHERE quoteNumber = '" . $_POST['reference_quote'] . "' AND fst_values.key = '" . $new_info[2][$i] . "' LIMIT 1;";
		$result = $mysqli->query($query);
		$result = $result->fetch_array(MYSQLI_NUM);

		//strategy to reduce manual creation of query
		//step 1, implode result array
		//step 2, replace reference quote with current quote
		//step 3, use this as the values portion of the query
		$result_string = "'" . implode("','", $result) . "'";

		//replace reference quote with our new quote
		$result_string = str_replace($_POST['reference_quote'], $_POST['quote'], $result_string);

		//create query to insert new line based on criteria
		$query = "REPLACE INTO fst_values VALUES (" . $result_string . ");";
		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
	}

	//$new_info[2] = materials / we won't enter loop if there are none applicable
	for ($i = 0; $i < sizeof($new_info[3]); $i++) {
		//run query/grab array of values
		$query = "SELECT * FROM fst_boms WHERE id = '" . $new_info[3][$i] . "' LIMIT 1;";
		$result = $mysqli->query($query);
		$result = $result->fetch_array(MYSQLI_NUM);

		//strategy to reduce manual creation of query
		//step 1, implode result array
		//step 2, replace reference quote with current quote
		//step 3, use this as the values portion of the query
		$result_string = "'" . implode("','", $result) . "'";

		//replace reference quote with our new quote
		$result_string = str_replace($_POST['reference_quote'], $_POST['quote'], $result_string);

		//replace id with NULL
		$result_string = str_replace($new_info[3][$i], NULL, $result_string);

		//create query to insert new line based on criteria
		$query = "REPLACE INTO fst_boms VALUES (" . $result_string . ");";
		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
	}

	return;
}

//handles creating revision of previous quote
if ($_POST['tell'] == "revision") {

	//grab quote
	$quote = $_POST['reference_quote'];
	$user_info = json_decode($_POST['user_info'], true);

	//decode arrays on server
	$query = "SELECT * FROM fst_grid WHERE quoteNumber = '" . $quote . "';";
	$result = mysqli_query($con, $query);

	//check to see if we returned anything
	if (mysqli_num_rows($result) > 0) {

		//send reference quote to function to get new revision
		$quote = get_new_quote($_POST['reference_quote']);

		//grab grid values
		$grid = mysqli_fetch_array($result);

		//use describe to get all columns from fst_grid (we want to create exact replica so copy ALL values (not primary key))
		$query = "DESCRIBE fst_grid;";
		$result = mysqli_query($con, $query);
		$describe_grid = [];

		while ($rows = mysqli_fetch_assoc($result)) {
			array_push($describe_grid, $rows);
		}

		//init query
		$query = "INSERT INTO fst_grid (";

		//loop through describe and create query for entry into fst_grid
		for ($i = 0; $i < sizeof($describe_grid); $i++) {

			//treat last entry differently
			if ($i == sizeof($describe_grid) - 1)
				$query .= " `" . $describe_grid[$i]['Field'] . "`) VALUES (";
			else
				$query .= "`" . $describe_grid[$i]['Field'] . "`, ";
		}

		//add new quoteNumber (only thing that should change)
		$query .= "'" . $quote . "', ";

		//default quote status to 'created'
		$old_status = $grid['quoteStatus'];	//swap so we can check old status later
		$grid['quoteStatus'] = 'Created';

		//loop through describe again and create values to go into fst_grid
		for ($i = 0; $i < sizeof($describe_grid); $i++) {

			//treat last entry differently (skip quoteNumber)
			if ($i == sizeof($describe_grid) - 1)
				$query .= " '" . mysql_escape_mimic($grid[$describe_grid[$i]['Field']]) . "');";
			elseif ($describe_grid[$i]['Field'] != "quoteNumber")
				$query .= "'" . mysql_escape_mimic($grid[$describe_grid[$i]['Field']]) . "', ";
		}

		//execute created query
		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

		//set previous quote to archived
		//don't update previous status IF this current one is dead
		if (substr($old_status, 0, 4) != "Dead") {

			//update previous quote to archived and log it
			$query = "UPDATE fst_grid SET quoteStatus = 'Archived' WHERE quoteNumber = '" . $_POST['reference_quote'] . "';";
			custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

			// Log quote status change
			$notification = new Notifications($con, "quote_status", "Archived", $_POST['reference_quote'], $use);
			$notification->log_notification($user_info['id']);
		}

		//log changes made to original (we duplicated AND archived)
		$notification = new Notifications($con, "job_revision", $_POST['reference_quote'] . " duplicated to " . $quote, $_POST['reference_quote'], $use);
		$notification->log_notification($user_info['id']);

		//call function to copy values from existing quote
		copy_all_quote_information($_POST['reference_quote'], $quote, $con, $mysqli);

		//log that quote was created and by who
		$notification = new Notifications($con, "job_creation", "Revised from " . $_POST['reference_quote'], $quote, $use);
		$notification->log_notification($user_info['id']);

		// Log quote status created
		$notification = new Notifications($con, "quote_status", "Created", $quote, $use);
		$notification->log_notification($user_info['id']);

		//alert WO team if this is a WO
		if ($grid['quote_type'] == "SM") {

			//Instantiation and passing `true` enables exceptions
			$mail = new PHPMailer();
			$mail = init_mail_settings($mail);

			//send email to work orders (see phpFunctions)
			alert_work_orders($mail, $quote, "Revision", $use, $grid['totalPrice']);
		}

		//return quote
		echo "success|" . $quote;
	} else {
		echo "There was an error, please contact fst@piersonwireless.com.";
	}

	return;
}

//handles creating major revision of previous quote
if ($_POST['tell'] == "sow") {

	//parse out version and quote
	$quote = $_POST['reference_quote'];

	//decode arrays on server
	$query = "SELECT * FROM fst_grid WHERE quoteNumber = '" . $quote . "';";
	$result = mysqli_query($con, $query);

	//check to see if we returned anything
	if (mysqli_num_rows($result) > 0) {

		//grab grid values
		$grid = mysqli_fetch_array($result);

		//if the reference quote is less than 10 characters, update the quote number
		if (strlen($_POST['reference_quote']) < 10)
			$_POST['reference_quote'] = $grid['vpProjectNumber'] . "-011";

		//send reference quote to function to get new sow (major revision)
		$quote = get_new_sow($_POST['reference_quote']);

		//build query to insert new entry
		$query = "INSERT INTO fst_grid (quoteNumber, location_name, location_id, vpProjectNumber, vpContractNumber, designer, phaseName, projectType, subType, customer, customer_id, customer_pm, market, address, address2, city, state, zip, projectLead, quoteCreator, programCoordinator, opsLead, quoteStatus, googleDriveLink, custID, oemNum, lastUpdate, analytics_dec, quote_type, site_pin) 
								VALUES ('" . $quote . "', '" . mysql_escape_mimic($grid['location_name']) . "', '" . $grid['location_id'] . "', '" . $grid['vpProjectNumber'] . "', '" . $grid['vpContractNumber'] . "', '" . $grid['designer'] . "', '" . mysql_escape_mimic($grid['phaseName']) . "', '" . $grid['projectType'] . "', '" . $grid['subType'] . "', '" . mysql_escape_mimic($grid['customer']) . "', '" . mysql_escape_mimic($grid['customer_id']) . "', '" . mysql_escape_mimic($grid['customer_pm']) . "', '" . $grid['market'] . "', '" . mysql_escape_mimic($grid['address']) . "', '" . mysql_escape_mimic($grid['address2']) . "', '" . mysql_escape_mimic($grid['city']) . "', '" . $grid['state'] . "', '" . $grid['zip'] . "', '" . $grid['projectLead'] . "', '" . $grid['quoteCreator'] . "', '" . $grid['programCoordinator'] . "', '" . $grid['opsLead'] . "', 'Created', '" . mysql_escape_mimic($grid['googleDriveLink']) . "', '" . $grid['custID'] . "', '" . $grid['oemNum'] . "', NOW(), 'on', '" . $grid['quote_type'] . "', '" . $grid['site_pin'] . "');";
		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

		//log that quote was created and by who
		$notification = new Notifications($con, "job_creation", "", $quote, $use);
		$notification->log_notification($user_info['id']);

		// Log quote status as created
		$notification = new Notifications($con, "quote_status", "Created", $quote, $use);
		$notification->log_notification($user_info['id']);

		//if quote size is < 8, return with v1, otherwise just return
		if (strlen($quote) < 8)
			echo "success|" . $quote . "v1";
		else
			echo "success|" . $quote;

		//alert WO team if this is a WO
		if ($grid['quote_type'] == "SM") {

			//Instantiation and passing `true` enables exceptions
			$mail = new PHPMailer();
			$mail = init_mail_settings($mail);

			//send email to work orders (see phpFunctions)
			alert_work_orders($mail, $quote, "SOW", $use, $grid['totalPrice']);
		}
	} else {
		echo "There was an error, please contact fst@piersonwireless.com.";
	}

	return;
}

//handles creating a change order for a quote
if ($_POST['tell'] == "change order") {

	//parse out version and quote
	$quote = $_POST['reference_quote'];

	//decode arrays on server
	$query = "SELECT * FROM fst_grid WHERE quoteNumber = '" . $quote . "';";
	$result = mysqli_query($con, $query);

	//check to see if we returned anything
	if (mysqli_num_rows($result) > 0) {

		//grab grid values
		$grid = mysqli_fetch_array($result);

		// Get the largest change order currently associated with this quote
		$query = "SELECT co_number FROM fst_grid WHERE quoteNumber LIKE '" . substr($grid['quoteNumber'], 0, 10) . "%' ORDER BY co_number DESC LIMIT 1;";
		$result = mysqli_query($con, $query);

		// Check to see if we returned anything
		if (mysqli_num_rows($result) > 0) {
			$previous_co = mysqli_fetch_array($result);
			$co_number = intval($previous_co['co_number']) + 1;
		} else {
			$co_number = "1";
		}

		//if the reference quote is less than 10 characters, update the quote number
		if (strlen($_POST['reference_quote']) < 10)
			$_POST['reference_quote'] = $grid['vpProjectNumber'] . "-011";

		//send reference quote to function to get new sow (major revision)
		$quote = get_new_sow($_POST['reference_quote']);

		//build query to insert new entry
		$query = "INSERT INTO fst_grid (quoteNumber, location_name, location_id, vpProjectNumber, vpContractNumber, designer, phaseName, projectType, subType, customer, customer_id, customer_pm, market, address, address2, city, state, zip, projectLead, quoteCreator, programCoordinator, opsLead, quoteStatus, googleDriveLink, custID, oemNum, lastUpdate, analytics_dec, quote_type, site_pin, co_number) 
								VALUES ('" . $quote . "', '" . mysql_escape_mimic($grid['location_name']) . "', '" . $grid['location_id'] . "', '" . $grid['vpProjectNumber'] . "', '" . $grid['vpContractNumber'] . "', '" . $grid['designer'] . "', '" . mysql_escape_mimic($grid['phaseName']) . "', '" . $grid['projectType'] . "', '" . $grid['subType'] . "', '" . mysql_escape_mimic($grid['customer']) . "', '" . mysql_escape_mimic($grid['customer_id']) . "', '" . mysql_escape_mimic($grid['customer_pm']) . "', '" . $grid['market'] . "', '" . mysql_escape_mimic($grid['address']) . "', '" . mysql_escape_mimic($grid['address2']) . "', '" . mysql_escape_mimic($grid['city']) . "', '" . $grid['state'] . "', '" . $grid['zip'] . "', '" . $grid['projectLead'] . "', '" . $grid['quoteCreator'] . "', '" . $grid['programCoordinator'] . "', '" . $grid['opsLead'] . "', 'Created', '" . mysql_escape_mimic($grid['googleDriveLink']) . "', '" . $grid['custID'] . "', '" . $grid['oemNum'] . "', NOW(), 'on', '" . $grid['quote_type'] . "', '" . $grid['site_pin'] . "', '" . $co_number . "');";
		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

		//log that quote was created and by who
		$notification = new Notifications($con, "job_creation", "", $quote, $use);
		$notification->log_notification($user_info['id']);

		// Log quote status as created
		$notification = new Notifications($con, "quote_status", "Created", $quote, $use);
		$notification->log_notification($user_info['id']);

		//if quote size is < 8, return with v1, otherwise just return
		if (strlen($quote) < 8)
			echo "success|" . $quote . "v1";
		else
			echo "success|" . $quote;

		//alert WO team if this is a WO
		if ($grid['quote_type'] == "SM") {

			//Instantiation and passing `true` enables exceptions
			$mail = new PHPMailer();
			$mail = init_mail_settings($mail);

			//send email to work orders (see phpFunctions)
			alert_work_orders($mail, $quote, "SOW", $use, $grid['totalPrice']);
		}
	} else {
		echo "There was an error, please contact fst@piersonwireless.com.";
	}

	return;
}

//handles generating new quote number
function get_new_quote($old_quote)
{

	//initialize db configuration
	require 'config.php';

	//depending on length, use different logic
	//grab version and turn to int, increment by 1
	if (strlen($old_quote) > 10) {

		//get position of dash in string (offset 4 to avoid first dash in newer quote #'s)
		$dash_pos = strpos($old_quote, "-", 4);

		//pop version from dash position
		$version = substr($old_quote, $dash_pos + 3);
		$version = intval($version);
		$version++;

		//turn into new quote
		$quote = substr($old_quote, 0, $dash_pos + 3) . $version;
		$new_quote = $quote;
	} else {
		$version = substr($old_quote, strpos($old_quote, "v") + 1);
		$version = intval($version);
		$version++;

		$quote = substr($old_quote, 0, strpos($old_quote, "v"));
		$new_quote = $quote . "v" . $version;
	}

	//check to see if new revision exists, if so, call get_new_quote recursively
	$query = "SELECT quoteNumber FROM fst_grid WHERE quoteNumber = '" . $quote . "';";
	$result = mysqli_query($con, $query);

	//check to see if we returned anything
	if (mysqli_num_rows($result) > 0) {
		$new_quote = get_new_quote($new_quote);
	}

	return $new_quote;
}

//handles generating new quote number
function get_new_sow($old_quote)
{

	//initialize db configuration
	require 'config.php';

	//grab major revision number
	//222-0004206-(01)4
	//get position of dash in string (offset 4 to avoid first dash in newer quote #'s)
	$dash_pos = strpos($old_quote, "-", 4);

	$version = substr($old_quote, $dash_pos + 1, 2);
	$version = intval($version);

	//increment revision by 1
	//(01)=>(2)
	$version++;

	//turn back to string
	$version = strval($version);

	//check for size
	if (strlen($version) == 1)
		$version = "0" . $version;
	elseif (strlen($version == 3))
		$version = "A" . substr($version, 0, 1);

	//turn into new quote
	$new_quote = substr($old_quote, 0, $dash_pos + 1) . $version . "1";

	//check to see if new revision exists, if so, call get_new_quote recursively
	$query = "SELECT quoteNumber FROM fst_grid WHERE quoteNumber = '" . $new_quote . "';";
	$result = mysqli_query($con, $query);

	//check to see if we returned anything
	if (mysqli_num_rows($result) > 0) {
		$new_quote = get_new_sow($new_quote);
	}

	return $new_quote;
}

//used to send email to accounting for new customer creation
if ($_POST['tell'] == "alert_new_customer") {

	//get customer info from customer_id
	$query = "SELECT * FROM fst_customers WHERE cust_id = '" . $_POST['customer_id'] . "';";
	$result = mysqli_query($con, $query);

	//check to see if we returned anything
	if (mysqli_num_rows($result) == 0)
		return;
	else
		$customer_info = mysqli_fetch_array($result);

	//if customer has already been created, reject request
	if ($customer_info['created_in_vp'] == "Yes")
		return;

	//change 'created_in_vp' to Yes (so we do not request again)
	$query = "UPDATE fst_customers SET created_in_vp = 'Yes' WHERE cust_id = '" . $_POST['customer_id'] . "';";
	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

	//Instantiation and passing `true` enables exceptions
	$mail = new PHPMailer();

	//init mail settings (found in phpFunction.php)
	$mail = init_mail_settings($mail);

	//Recipients
	$mail->setFrom($_SESSION['email'], $_SESSION['firstName'] . " " . $_SESSION['lastName']); //set from (name is optional)
	$mail->AddReplyTo($_SESSION['email'], $_SESSION['firstName'] . " " . $_SESSION['lastName']);

	//depending on the ship from location, tag either omaha logistics or charlotte
	if ($use == "test") {
		$mail->addAddress($_SESSION['email']);
		//$mail->addAddress('workorders@piersonwireless.com');
	} else {
		$mail->addAddress('acctsrcvaccounting@piersonwireless.com');
	}

	//CC user
	$mail->addCC($_SESSION['email']);
	$mail->addBCC('alex.borchers@piersonwireless.com'); 	//bcc me for now

	//set subject & body of email
	$subject_line = "[New Customer Creation] " . $customer_info['customer'] . " | ID " . $customer_info['cust_id'];
	$body = "Hello Accounts Receivable,<br><br>";
	$body .= "Please create customer <b>" . $customer_info['customer'] . "</b> in Viewpoint. See the user entered detail below: <br><br>";
	$body .= "Customer ID: " . $customer_info['cust_id'] . "<br>";
	$body .= "Customer Name: " . $customer_info['customer'] . "<br>";
	$body .= "Street: " . $customer_info['street'] . "<br>";
	$body .= "City, State Zip: " . $customer_info['city'] . ", " . $customer_info['state'] . " " . $customer_info['zip'] . "<br>";
	$body .= "Contact Number: " . $customer_info['phone'] . "<br>";
	$body .= "Invoice Email: " . $customer_info['email'] . "<br><br>";
	$body .= "Please reach out to " . $customer_info['created_by'] . " for any additional information.<br><br>";
	$body .= "Thank you,";

	//Content
	$mail->isHTML(true);
	$mail->Subject =  $subject_line;
	$mail->Body = $body;
	$mail->send();

	//close smtp connection
	$mail->smtpClose();

	return;
}

//used to send email to WO if project is awarded
if ($_POST['tell'] == "alert_work_order") {

	//send out email based on information provided
	//Instantiation and passing `true` enables exceptions
	$mail = new PHPMailer();
	$mail = init_mail_settings($mail);

	//send email to work orders (see phpFunctions)
	alert_work_orders($mail, $_POST['quote'], $_POST['status'], $use, $_POST['price']);
	return;
}

// used to create new subcontractor
if ($_POST['tell'] == "create_new_sub") {

	// get user info
	$user_info = json_decode($_POST['user_info'], true);

	// get new vendor # from vendor list
	$query = "select id from fst_vendor_list order by id desc LIMIT 1;";
	$result = mysqli_query($con, $query);
	$previous = mysqli_fetch_array($result);
	$new = intval($previous['id']) + 1;

	// insert new entry to fst_vendor_list
	$query = "INSERT INTO fst_vendor_list (vendor, poc, phone, email, street, street2, city, state, zip, last_compliance_request, pw_contact)
									VALUES ('" . mysql_escape_mimic($_POST['subcontractor_name']) . "', '" . mysql_escape_mimic($_POST['subcontractor_poc_name']) . "', 
											'" . mysql_escape_mimic($_POST['subcontractor_poc_phone']) . "', '" . mysql_escape_mimic($_POST['subcontractor_poc_email']) . "', 
											'" . mysql_escape_mimic($_POST['subcontractor_street']) . "', '" . mysql_escape_mimic($_POST['subcontractor_street2']) . "', 
											'" . mysql_escape_mimic($_POST['subcontractor_city']) . "', '" . mysql_escape_mimic($_POST['subcontractor_state']) . "', 
											'" . mysql_escape_mimic($_POST['subcontractor_zip']) . "', NOW(), '" . mysql_escape_mimic($_POST['subcontractor_pw_contact']) . "')";
	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

	// insert new entry to fst_vendor_compliance using ID received from fst_vendor_list
	$id = mysqli_insert_id($con);
	$query = "INSERT INTO fst_vendor_compliance (id, vendor_name)
									VALUES ('" . $id . "', '" . mysql_escape_mimic($_POST['subcontractor_name']) . "')";
	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

	// insert first contact into fst_vendor_list_poc
	$query = "INSERT INTO fst_vendor_list_poc (vendor_id, name, phone, email)
									VALUES ('" . $id . "', '" . mysql_escape_mimic($_POST['subcontractor_poc_name']) . "',
											'" . mysql_escape_mimic($_POST['subcontractor_poc_phone']) . "', '" . mysql_escape_mimic($_POST['subcontractor_poc_email']) . "')";
	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

	// return new vendor ID to user
	echo $id;

	// Send onboarding package
	//send_subcontractor_onboarding($use, $con, $id, $user_info, $_POST['quote']);
	return;
}

// used to request subcontractor compliance
if ($_POST['tell'] == "request_sub_compliance") {

	// get user info
	$user_info = json_decode($_POST['user_info'], true);

	// depending on the type of request, call different function to initiate email
	if ($_POST['type'] == "po_request")
		send_subcontractor_package($use, $con, $_POST['subcontractor'], $_POST['subcontractor_poc_email'], $user_info, $_POST['quote'], $_POST['type']);
	elseif ($_POST['type'] == "awarded")
		send_subcontractor_reminder($use, $con, $_POST['subcontractor'], $user_info, $_POST['quote'], $_POST['type']);
	elseif ($_POST['type'] == "onboarding")
		send_subcontractor_onboarding($use, $con, $_POST['id'], $user_info, $_POST['quote']);
	else {

		// extract reason for non-compliance
		$compliance_reason = "";

		if ($_POST['w9_verified'] != "Y")
			$compliance_reason .= "W9 unverified, ";
		if ($_POST['coi_verified'] != "Y")
			$compliance_reason .= "COI unverified, ";
		if ($_POST['w9_verified'] != "Y")
			$compliance_reason .= "COI expired, ";

		// remove last 2 characters
		if ($compliance_reason != "")
			$compliance_reason = substr($compliance_reason, 0, strlen($compliance_reason) - 2);

		// write log of sub compliance
		$notification = new Notifications($con, "sub_add", mysql_escape_mimic($_POST['subcontractor']) . " non-complaint: " . $compliance_reason, $_POST['quote'], $use);
		$notification->log_notification($user_info['id']);
	}

	return;
}

// used to assign new subcontractor to quote
if ($_POST['tell'] == "add_sub") {

	// get user info & any files attached
	$user_info = json_decode($_POST['user_info'], true);
	$files = json_decode($_POST['file_reference']);

	// upload subcontractor quotes to subcontractor folder
	$target_dir = getcwd(); // target director (if sending attachments)

	// call function to add new subcontractor files
	add_subcontractor_quotes($target_dir, $files, $_FILES, $_POST['vendor_id'], $_POST['google_drive_link']);

	// create description for fst_values
	$fst_description = $_POST['vendor'] . " " . $_POST['short_description'];

	// check fst_value_key (treat differently if this is a legacy project)
	if ($_POST['fst_value_key'] != "legacy") {

		// insert row into fst_values (placeholder for user to overwrite in the future)
		$query = "REPLACE INTO fst_values 
						(`quoteNumber`, `key`, `mainCat`, `subCat`, `phaseCostType`, `role`, `people`, `days`, `laborHrs`, `laborRate`, `markupPerc`, `cost`, `margin`, `price`) 
					VALUES ('" . $_POST['quote'] . "', '" . $_POST['fst_value_key'] . "', 
							'Installation and Testing of Components', '" . mysql_escape_mimic($fst_description) . "', 
							'04000-Installation/Subcontractor', 'Installer', '0', '0', '0', '0', '0', '" . mysql_escape_mimic($_POST['price']) . "', '0', '0');";
		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
	}

	// insert row into fst_grid_subcontractors
	$query = "INSERT INTO fst_grid_subcontractors (`quoteNumber`, `vendor_id`, `vendor`, `price`, `customer_quote`, `short_description`, `full_description`, `value_key`)
									VALUES ('" . $_POST['quote'] . "', '" . mysql_escape_mimic($_POST['vendor_id']) . "',
											'" . mysql_escape_mimic($_POST['vendor']) . "', '" . mysql_escape_mimic($_POST['price']) . "',
											'" . mysql_escape_mimic($_POST['customer_quote']) . "', '" . mysql_escape_mimic($_POST['short_description']) . "', 
											'" . mysql_escape_mimic($_POST['full_description']) . "', '" . mysql_escape_mimic($_POST['fst_value_key']) . "')";
	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

	// get inserted ID & return to user
	$id = mysqli_insert_id($con);
	echo $id;

	return;
}

// used to update subcontractor info
if ($_POST['tell'] == "update_sub") {

	// get user info & any files attached
	$user_info = json_decode($_POST['user_info'], true);
	$files = json_decode($_POST['file_reference'], true);

	// create description for fst_values
	$fst_description = $_POST['vendor'] . " " . $_POST['short_description'];

	// only update fst_values if not legacy
	if ($_POST['fst_value_key'] != "legacy") {
		$query = "UPDATE fst_values 
						SET `subCat` = '" . mysql_escape_mimic($fst_description) . "' 
						WHERE `quoteNumber` = '" . $_POST['quote'] . "' AND `key` = '" . $_POST['fst_value_key'] . "';";
		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
	}

	// insert row into fst_grid_subcontractors
	$query = "UPDATE fst_grid_subcontractors 
				SET `price` = '" . mysql_escape_mimic($_POST['price']) . "',
					`customer_quote` = '" . mysql_escape_mimic($_POST['customer_quote']) . "',
					`short_description` = '" . mysql_escape_mimic($_POST['short_description']) . "',
					`full_description` = '" . mysql_escape_mimic($_POST['full_description']) . "'
				WHERE `id` = '" . $_POST['id'] . "';";
	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

	// call function to add new subcontractor files
	$target_dir = getcwd();
	add_subcontractor_quotes($target_dir, $files, $_FILES, $_POST['vendor_id'], $_POST['google_drive_link']);

	return;
}

// used to remove subcontractor info
if ($_POST['tell'] == "remove_sub") {

	// get user info & any files attached
	$user_info = json_decode($_POST['user_info'], true);

	// set query to remove from fst_values
	$query = "DELETE FROM fst_values 
				WHERE `quoteNumber` = '" . $_POST['quote'] . "' AND `key` = '" . $_POST['fst_value_key'] . "';";
	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

	// set query to remove from fst_grid_subcontractors
	$query = "DELETE FROM fst_grid_subcontractors 
				WHERE `id` = '" . $_POST['id'] . "';";
	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

	return;
}

// used to request PO for subcontractor from Accounts Payable
if ($_POST['tell'] == "request_po") {

	// get user info & any file id's brought over
	$user_info = json_decode($_POST['user_info'], true);
	$file_ids = json_decode($_POST['file_ids'], true);

	// get next PO #
	$po_number = get_new_fst_po_number($con);

	// insert into orders table (set status to submitted)
	$query = "INSERT INTO fst_pq_orders (po_number, vendor_name, status) VALUES ('" . $po_number . "', '" . $_POST['name'] . "', 'Submitted');";
	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

	// update sub-contrator detail
	$query = "UPDATE fst_grid_subcontractors SET po_number = '" . $po_number . "' WHERE id = '" . $_POST['id'] . "';";
	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

	// set up php mailer to send request
	// Instantiation and passing `true` enables exceptions
	$mail = new PHPMailer();
	$mail = init_mail_settings($mail, $user_info);	//found in phpFunctions.php

	//depending on the ship from location, tag either omaha logistics or charlotte
	if ($use == "test")
		$mail->addAddress($user_info['email']);
	else
		$mail->addAddress('AccountsPayable@piersonwireless.com');

	// CC user defined criteria
	$mail = add_custom_recipients($mail, $_POST['email_cc'], "CC");
	$mail->addBCC('alex.borchers@piersonwireless.com'); 	//bcc me for now

	// array to hold all file names / paths
	$file_reference = [];

	// loop through files and add to email
	foreach ($file_ids as $file) {
		$file_path = download_file($file['id'], $file['name']);
		array_push($file_reference, $file_path);
		$mail->addAttachment($file_path);
	}

	// get attached files
	$files = json_decode($_POST['file_reference']);

	// upload subcontractor quotes to subcontractor folder
	$target_dir = getcwd(); // target director (if sending attachments)

	// loop through attached files and attach to email (if any)
	foreach ($files as $file) {

		// save file locally
		// prefix all file names with vendor ID
		$target_file = $target_dir . "\\uploads\\" . basename($_FILES[$file]["name"]);

		// if file is added successfully, push to google drive folder, then remove locally
		if (move_uploaded_file($_FILES[$file]["tmp_name"], $target_file)) {
			$mail->addAttachment($target_file);
		} else {
			echo "Sorry, there was an error uploading your file.";
		}
	}

	// create email body 
	$body  = "Hello Accounts Payable Team,<br><br>";
	$body .= "Please submit a PO <b><i><u>today</u></i></b> to the following vendor:,<br>";
	$body .= "<ul>";
	$body .= "<li><b>PO Number:</b> " . $po_number . "</li>";

	// differentiate this part depending on quote type
	if ($_POST['quote_type'] == "SM")
		$body .= "<li><b>WO Number:</b> " . $_POST['project_number'] . "</li>";
	else
		$body .= "<li><b>Project Number:</b> " . $_POST['project_number'] . "</li>";

	$body .= "<li><b>Project Name:</b> " . $_POST['project_name'] . "</li>";

	// open new sub list
	$body .= "<ul>";
	$body .= "<li><b>Vendor Quote:</b> " . $_POST['sub_quote'] . "</li>";
	$body .= "<li><b>Vendor Name:</b> " . $_POST['name'] . "</li>";
	$body .= "<li><b>Attached (Yes/No):</b> Y</li>";
	$body .= "<li><b>Quoted Amount:</b> " . convert_money('%.2n', $_POST['amount']) . "</li>";
	$body .= "</ul>";	//close sub list

	$body .= "<li><b>Contact to Submit Invoices to:</b></li>";

	// open new sub list
	$body .= "<ul>";
	$body .= "<li><b>Contact Name:</b> " . $_POST['contact_name'] . "</li>";
	$body .= "<li><b>Contact Email:</b> " . $_POST['contact_email'] . "</li>";
	$body .= "</ul>";	//close sub list

	// close original list
	$body .= "</ul><br>";
	$body .= "Please let me know if any additional information is needed.";

	// Content
	$mail->isHTML(true);
	$mail->Subject =  "Purchase Order Request - Viewpoint Job ID: " . $_POST['project_number'];
	$mail->Body = $body;

	// if send is a success, go back and remove all attachments
	if ($mail->send()) {
		// gmail files
		foreach ($file_reference as $file) {
			unlink($file);
		}
		// user attached files
		foreach ($files as $file) {
			$target_file = $target_dir . "\\uploads\\" . basename($_FILES[$file]["name"]);
			unlink($target_file);
		}
	}

	// close smtp connection
	$mail->smtpClose();

	// write log of PO request
	$notification = new Notifications($con, "sub_po_request", mysql_escape_mimic($_POST['name']) . " PO Requested", $_POST['quote'], $use);
	$notification->log_notification($user_info['id']);

	// return $po_number to user
	echo $po_number;

	return;
}

// used to update service requests
if ($_POST['tell'] == "update_service_request") {

	// Get json values passed from ajax call
	$user_info = json_decode($_POST['user_info'], true);
	$service_request_key = json_decode($_POST['service_req_keys'], true);

	// Get current service request info
	$query = "SELECT * FROM fst_grid_service_request WHERE id = '" . $_POST['id'] . "';";
	$result = mysqli_query($con, $query);
	$service_request = mysqli_fetch_assoc($result);

	// Create update query and execute
	$query = create_custom_update_sql_query($_POST, $service_request_key, "fst_grid_service_request", ['id']);
	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

	// Create difference log
	$diff = "";
	foreach ($service_request_key as $key) {
		if ($_POST[$key] != $service_request[$key]) {

			// Replace $service item or $_POST item IF empty to say "empty"
			if ($service_request[$key] == "")
				$service_request[$key] = "empty";
			if ($_POST[$key] == "")
				$_POST[$key] = "empty";

			$diff .= convert_underscores_to_spaces($key) . " changed from " . $service_request[$key] . " to " . $_POST[$key] . ", ";
		}
	}

	// If we found a difference, wrap in paranthesis
	if ($diff != "") {
		$diff = substr($diff, 0, -2);
		$diff = "(" . $diff . ")";
	}

	// Create log of update
	$query = "SELECT * FROM fst_grid_service_request WHERE id = '" . $_POST['id'] . "';";
	$result = mysqli_query($con, $query);
	$service_request = mysqli_fetch_assoc($result);
	if ($service_request['group'] == "Design")
		$notification = new Notifications($con, "des_est_request", trim("Design Request Change " . $diff), $_POST['quote'], $use);
	elseif ($service_request['group'] == "COP")
		$notification = new Notifications($con, "cop_request", trim("COP Request Change " . $diff), $_POST['quote'], $use);
	elseif ($service_request['group'] == "FSE")
		$notification = new Notifications($con, "fse_request", trim("FSE Request Change " . $diff), $_POST['quote'], $use);
	elseif ($service_request['group'] == "Ops")
		$notification = new Notifications($con, "ops_request", trim("Ops Request Change " . $diff), $_POST['quote'], $use);

	// Log & Send notification
	$notification->log_notification($user_info['id']);
}

// used to remove service requests
if ($_POST['tell'] == "delete_service_request") {

	// Get json values passed from ajax call
	$user_info = json_decode($_POST['user_info'], true);

	// Create log of update
	$query = "SELECT * FROM fst_grid_service_request WHERE id = '" . $_POST['id'] . "';";
	$result = mysqli_query($con, $query);
	$service_request = mysqli_fetch_assoc($result);
	if ($service_request['group'] == "Design")
		$notification = new Notifications($con, "des_est_request", "Design Request Removed", $_POST['quoteNumber'], $use);
	elseif ($service_request['group'] == "COP")
		$notification = new Notifications($con, "cop_request", "COP Request Removed", $_POST['quoteNumber'], $use);
	elseif ($service_request['group'] == "FSE")
		$notification = new Notifications($con, "fse_request", "FSE Request Removed", $_POST['quoteNumber'], $use);
	elseif ($service_request['group'] == "Ops")
		$notification = new Notifications($con, "ops_request", "Ops Request Removed", $_POST['quoteNumber'], $use);

	// Log & Send notification
	$notification->log_notification($user_info['id']);

	// Create query to remove request and execute
	$query = "DELETE FROM fst_grid_service_request WHERE id = '" . $_POST['id'] . "';";
	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

	/*
	// Send out notification letting relevant users know what happened
	// Instantiation and passing `true` enables exceptions
	$mail = new PHPMailer();
	$mail = init_mail_settings($mail, $user_info);

	if ($use == "test")
		$mail->addAddress($user_info['email']);		// testing developer
	else {
		// Get responsible party info
		$query = "SELECT b.email 
			FROM fst_grid_service_request a
			LEFT JOIN fst_users b
				ON a.personnel = b.fullName
			WHERE a.id = '" . $_POST['id'] . "';";
		$result = mysqli_query($con, $query);

		// If we returned a result, add to email
		if (mysqli_num_rows($result) > 0) {
			$to = mysqli_fetch_array($result);
			$mail->addAddress($to['email']);
			$mail->addCC($user_info['email']);
		} else
			$mail->addAddress($user_info['email']);

		// CC Des team member responsible on job
		$query = "SELECT designer FROM fst_grid WHERE quoteNumber = '" . $_POST['quoteNumber'] . "';";
		$result = mysqli_query($con, $query);
		$grid = mysqli_fetch_array($result);
		$mail->addCC($grid['designer']);
	}

	//Content
	$mail->isHTML(true);
	$mail->Subject =  "[FST] Service Request Removed - " . $_POST['quoteNumber'] . " - " . $_POST['task'];
	$mail->Body = "Hello,<br><br>The following service request has been removed:<br><br>Task: " . $_POST['task'] . "<br>Reason: [Reason]<br>User: " . $user_info['fullName'] . "<br><br>Thank you,";
	$mail->send();

	//close smtp connection
	$mail->smtpClose();
	*/
}

// used to request services from other work groups
if ($_POST['tell'] == "service_request") {

	// call function to handle service request
	// found in phpFuctions.php
	initiate_service_request_handler($con, $_POST);
}

// Used to store pim-sweep values
if ($_POST['tell'] == "save_pim_sweep") {

	// Get user info, keys and any other info from ajax call
	$user_info = json_decode($_POST['user_info'], true);
	$pim_sweep_key = json_decode($_POST['pim_sweep_key'], true);

	$query = create_custom_insert_sql_query($_POST, $pim_sweep_key, "fst_values_pimsweep", true);
	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
}
