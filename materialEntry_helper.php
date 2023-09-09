<?php

/*****************
 * 
 * THE PURPOSE OF THIS FILE IS TO ASSIST WITH READ/WRITE COMMANDS TO DATABASE & SERVER FROM 
 * materialEntry.php.
 * 
 * All requestes from materialEntry.php must come with a $_POST['tell'] variable (loaded into a form via fd.append('tell', [enter tell]);)
 * EXAMPLE
 * fd.append('tell', 'example') => ($_POST['tell'] == 'example') = true
 * 
 ****************/

// Load dependencies & session variables
session_start();
include('phpFunctions.php');
include('PHPClasses/Part.php');
include('PHPClasses/Notifications.php');

// Load the database configuration file
require_once 'config.php';

//handles updating kit information
if ($_POST['tell'] == "kit_update") {

	//look at target_ids (any that have changed)
	$updated_kit = json_decode($_POST['updated_kit'], true);

	//remove previous entries related to kit
	$query = "DELETE FROM fst_bom_kits_detail WHERE kit_id = '" . $_POST['kit_id'] . "';";
	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

	//loop through updated_kit and insert new entries into 
	for ($i = 0; $i < sizeof($updated_kit); $i++) {

		//write and execute (with error_handler)
		$query = "INSERT INTO fst_bom_kits_detail (kit_id, partNumber, quantity, type) 
                                        VALUES ('" . mysql_escape_mimic($_POST['kit_id']) . "', '" . mysql_escape_mimic($updated_kit[$i]['part']) . "', 
                                                '" . mysql_escape_mimic($updated_kit[$i]['quantity']) . "', '" . mysql_escape_mimic($updated_kit[$i]['type']) . "');";
		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
	}

	//update price in invreport 
	$query = "UPDATE invreport SET price = '" . mysql_escape_mimic($_POST['kit_price']) . "' WHERE partNumber = '" . $_POST['kit_id'] . "';";
	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

	//update price in fst_bom_kits 
	$query = "UPDATE fst_bom_kits SET phase = '" . mysql_escape_mimic($_POST['kit_phase']) . "' WHERE kit_part_id = '" . $_POST['kit_id'] . "';";
	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

	return;
}

//handles adding parts to order
if ($_POST['tell'] == "add_to_order") {

	//look at target_ids (any that have changed)
	$new_parts = json_decode($_POST['new_parts'], true);
	$pq_overview = json_decode($_POST['pq_overview'], true);
	$user_info = json_decode($_POST['user_info'], true);

	//loop through updated_kit and insert new entries into 
	for ($i = 0; $i < sizeof($new_parts); $i++) {

		//create new instance of part
		$part = new part($new_parts[$i]['part'], $con);

		//check if this is identified as a manual part, if so, this means orders JUST created part, so we need to update with info from fst_newparts
		if ($part->manual_part) {

			//get info from fst_newparts table
			$query = "SELECT * FROM fst_newparts WHERE partNumber = '" . mysql_escape_mimic($new_parts[$i]['part']) . "' ORDER BY date desc LIMIT 1;";
			$result = mysqli_query($con, $query);
			$new_part_info = mysqli_fetch_array($result);

			//transfer relevant info over to $part instance
			$part->info['partNumber'] = $new_part_info['partNumber'];
			$part->info['partCategory'] = $new_part_info['category'];
			$part->info['partDescription'] = $new_part_info['description'];
			$part->info['manufacturer'] = $new_part_info['manufacturer'];
			$part->info['uom'] = $new_part_info['uom'];
		}

		//write query to add part to fst_boms
		$query = "INSERT INTO fst_boms (type, quoteNumber, partNumber, partCategory, description, manufacturer, quantity, cost, price, uom, allocated) 
					VALUES ('A', '" . mysql_escape_mimic($pq_overview['quoteNumber']) . "','" . mysql_escape_mimic($part->info['partNumber']) . "', 
							'" . mysql_escape_mimic($part->info['partCategory']) . "', '" . mysql_escape_mimic($part->info['partDescription']) . "', 
							'" . mysql_escape_mimic($part->info['manufacturer']) . "', '" . mysql_escape_mimic($new_parts[$i]['quantity']) . "', 
							'" . mysql_escape_mimic($new_parts[$i]['cost']) . "', '0', '" . mysql_escape_mimic($part->info['uom']) . "', 
							'" . mysql_escape_mimic($new_parts[$i]['quantity']) . "');";

		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

		//also write query to add part to fst_pq_detail
		if ($_POST['pq_type'] == "orders") {
			$query = "INSERT INTO fst_pq_detail (id, project_id, part_id, quantity, q_allocated, decision, send, status, vendor_qty, vendor_cost) 
                                VALUES (null, '" . $_POST['pq_id'] . "', '" . mysql_escape_mimic($part->info['partNumber']) . "', 
										'" . mysql_escape_mimic($new_parts[$i]['quantity']) . "', '" . mysql_escape_mimic($new_parts[$i]['quantity']) . "', 
										'PO', 'true', 'Pending', '" . mysql_escape_mimic($new_parts[$i]['quantity']) . "', 
										'" . mysql_escape_mimic($new_parts[$i]['cost']) . "');";
		} else {
			$query = "INSERT INTO fst_pq_detail (id, project_id, part_id, quantity, q_allocated, send, status) 
                                VALUES (null, '" . $_POST['pq_id'] . "', '" . mysql_escape_mimic($part->info['partNumber']) . "', 
										'" . mysql_escape_mimic($new_parts[$i]['quantity']) . "', '" . mysql_escape_mimic($new_parts[$i]['quantity']) . "', 
										'true', 'Requested');";
		}

		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
	}

	// Create reason
	$reason = sizeof($new_parts) . " Additional Part(s) added from " . $_POST['pq_type'] . " team";
	if ($_POST['reason'] == "Other")
		$reason .= " (" . $_POST['reason_other'] . ")";
	else
		$reason .= " (" . $_POST['reason'] . ")";

	// log material change
	$notification = new Notifications($con, "material_change", $reason, $pq_overview['quoteNumber'], $use);
	$notification->log_notification($user_info['id']);

	return;
}

//add to BOM from material entry page
if ($_POST['tell'] == "material_entry") {

	//get quote and user info
	$quote = $_POST['quote'];
	$user_info = json_decode($_POST['user_info'], true);

	//grab material logistics percentage from int-calcs
	$query = "select value from fst_intcalcs WHERE intCalcs = 'matLogisticPerc';";
	$result = mysqli_query($con, $query);
	$intCalc = mysqli_fetch_array($result);

	for ($i = 0; $i < sizeof($_POST['partsArray']); $i++) {

		//search for each the given part in the inventory report
		$part = new part($_POST['partsArray'][$i], $con);

		//will be able to pull info directly from invreport
		if (!$part->manual_part) {

			//update phase based on catagory
			$phase = $part->get_phase_code();

			//grab sub string (seperated by |)
			$sub_string = implode("|", $_POST['subOpts'][$i]);

			//write query
			$query = "INSERT INTO fst_boms (id, type, quoteNumber, description, partCategory, manufacturer, partNumber, quantity, cost, price, matL, phase, uom, mmd, manual, subs, subs_list, allocated) 
                                    VALUES (NULL, 'P', '" . $quote . "', '" . mysql_escape_mimic($part->info['partDescription']) . "', 
                                    '" . $part->info['partCategory'] . "', '" . $part->info['manufacturer'] . "', 
                                    '" . mysql_escape_mimic($part->info['partNumber']) . "', '" . $_POST['quantityArray'][$i] . "', 
                                    '" . $_POST['costArray'][$i] . "', '" . $_POST['priceArray'][$i] . "', 
                                    '" . $part->get_material_logistics() . "', '" . $phase . "',  '" . $part->info['uom'] . "', 
                                    '" . $_POST['mmdArray'][$i] . "', NULL, '" . $_POST['subsArray'][$i] . "', 
                                    '" . mysql_escape_mimic($sub_string) . "', 0)";

			//execute query, if success bring back information, if error, notify user.
			custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

			//log that part has been added to a quote
			//type "ME" = "Material Entry"
			$part->log_part_update($user_info['id'], 'Part added to quote ' . $quote . ' (QTY: ' . $_POST['quantityArray'][$i] . ')', 'ME');
			//$part->log_part_update($_SESSION['employeeID'], 'Part added to quote ' . $quote . ' (' . $_SESSION['firstName'] . ' ' . $_SESSION['lastName'] . ')', 'ME');

		}
		//handles manual parts (will need to look for relevant info in fst_newparts)
		else {
			//search for each the given part in the inventory report
			$query = "SELECT * FROM fst_newparts WHERE partNumber = '" . mysql_escape_mimic($_POST['partsArray'][$i]) . "' order by date desc limit 1;";
			$result = mysqli_query($con, $query);

			//if we do not find a match, fill in blanks for values that we are writing to query
			if (mysqli_num_rows($result) > 0) {
				$invArray = mysqli_fetch_array($result);
			} else {
				$invArray['category'] = "";
				$invArray['manufacturer'] = "";
				$invArray['uom'] = "";
				$invArray['description'] = "";
			}

			if ($invArray['category'] == 'ACT-DASHE' || $invArray['category'] == 'ACT-DASREM') {
				$phase = '03000';
			} else {
				$phase = '06000';
			}

			$description = mysql_escape_mimic($invArray['description']);

			$query = "INSERT INTO fst_boms (id, type, quoteNumber, description, partCategory, manufacturer, partNumber, quantity, cost, price, matL, phase, uom, mmd, manual, subs, allocated) 
									VALUES (NULL, 'P', '" . $quote . "', '" . mysql_escape_mimic($description) . "', '" . mysql_escape_mimic($invArray['category']) . "', '" . mysql_escape_mimic($invArray['manufacturer']) . "', '" . mysql_escape_mimic($_POST['partsArray'][$i]) . "', '" . $_POST['quantityArray'][$i] . "', '" . $_POST['costArray'][$i] . "', '" . $_POST['priceArray'][$i] . "', '-1', '" . $phase . "',  '" . $invArray['uom'] . "', '" . $_POST['mmdArray'][$i] . "', NULL, '" . $_POST['subsArray'][$i] . "', 0)";
			custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
		}
	}

	// Log successful material addition
	$notification = new Notifications($con, "material_change", strval(sizeof($_POST['partsArray'])) . " Part(s) Added", $quote, $use);
	$notification->log_notification($user_info['id']);

	// Process any mc_request_parts if available
	if (isset($_POST['mc_request_parts'])) {
		if (sizeof($_POST['mc_request_parts']) > 0) {
			mat_creation_email($_POST['mc_request_parts'], $quote);
		}
	}

	return;
}

//add to BOM FST (using duplicate function)
if ($_POST['tell'] == "existing_quote") {

	$quote = $_POST['quote'];
	$desc = 0;

	for ($i = 0; $i < sizeof($_POST['partsArray']); $i++) {

		//will be able to pull info directly from invreport
		if ($_POST['tellArray'][$i] == "A") {

			//search for each the given part in the inventory report
			$invQuery = "SELECT partDescription, partCategory, manufacturer, uom FROM invreport WHERE partNumber = '" . $_POST['partsArray'][$i] . "' AND active = 'True';";
			$runInvQ = mysqli_query($con, $invQuery);
			$invArray = mysqli_fetch_array($runInvQ);

			if ($invArray['partCategory'] == 'ACT-DASHE' || $invArray['partCategory'] == 'ACT-DASREM') {
				$phase = '03000';
			} else {
				$phase = '06000';
			}

			$description = mysql_escape_mimic($invArray['partDescription']);

			$query = "INSERT INTO fst_boms (id, type, quoteNumber, description, partCategory, manufacturer, partNumber, quantity, cost, price, phase, uom, mmd, manual, subs, allocated) VALUES (NULL, 'P', '" . $quote . "', '" . $description . "', '" . $invArray['partCategory'] . "', '" . $invArray['manufacturer'] . "', '" . mysql_escape_mimic($_POST['partsArray'][$i]) . "', '" . $_POST['quantityArray'][$i] . "', '" . $_POST['costArray'][$i] . "', '" . $_POST['priceArray'][$i] . "', '" . $phase . "',  '" . $invArray['uom'] . "', " . $_POST['mmdArray'][$i] . "', NULL, '" . $_POST['subsArray'][$i] . "', 0)";
		}
		//hanldes manual parts (will need to look for relevant info in fst_newparts)
		else {
			//search for each the given part in the inventory report
			$query = "SELECT * FROM fst_newparts WHERE partNumber = '" . $_POST['partsArray'][$i] . "' order by date desc limit 1";
			$result = mysqli_query($con, $invQuery);
			$invArray = mysqli_fetch_array($result);

			if ($invArray['partCategory'] == 'ACT-DASHE' || $invArray['partCategory'] == 'ACT-DASREM') {
				$phase = '03000';
			} else {
				$phase = '06000';
			}

			$description = mysql_escape_mimic($invArray['description']);

			$query = "INSERT INTO fst_boms (id, type, quoteNumber, description, partCategory, manufacturer, partNumber, quantity, cost, price, phase, uom, mmd, manual, subs, allocated) VALUES (NULL, 'P', '" . $quote . "', '" . $description . "', '" . mysql_escape_mimic($invArray['category']) . "', '" . mysql_escape_mimic($invArray['manufacturer']) . "', '" . mysql_escape_mimic($_POST['partsArray'][$i]) . "', '" . $_POST['quantityArray'][$i] . "', '" . $_POST['costArray'][$i] . "', '" . $_POST['priceArray'][$i] . "', '" . $phase . "',  '" . $invArray['uom'] . "', '" . $_POST['mmdArray'][$i] . "', NULL, '" . $_POST['subsArray'][$i] . "', 0)";
		}

		//call custom_query (executes query (2nd parameter) returns false if successful (true if error is returned) - in phpFunctions.php)
		//outputs error message if error occurs & sends to fst@pw.com for troubleshooting
		if (custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__))
			$desc = 1;
	}

	if ($desc == 0) {
		//header("Location: application.php?quote=" . $quote);
	} else {
		echo "Information was not saved correctly.";
	}
}
