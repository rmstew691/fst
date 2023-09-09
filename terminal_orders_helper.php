<?php

// load dependencies
session_start();
include('phpFunctions.php');
include('phpFunctions_drive.php');
include('constants.php');
include('PHPClasses/Part.php');
include('PHPClasses/Notifications.php');

// load db configuration
require_once 'config.php';

//Import PHPMailer classes into the global namespace
//These must be at the top of your script, not inside a function
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once 'PHPMailer/Exception.php';
require_once 'PHPMailer/PHPMailer.php';
require_once 'PHPMailer/SMTP.php';

//import phpSpreadsheet to copy and edit excel documents
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

//handles reading and writing pq_detail/overview to and from server (updates queue)
if ($_POST['tell'] == "update_queue") {

	//look at target_ids (any that have changed)
	$target_ids = json_decode($_POST['target_ids']);

	if (isset($_POST['user_info']))
		$user_info = json_decode($_POST['user_info'], true);
	else
		$user_info['id'] = $_SESSION['employeeID'];

	//if we find any that have changed, we need to go through and save them
	if (sizeof($target_ids) > 0) {

		//decode arrays related to allocation information
		$pq_detail = json_decode($_POST['pq_detail'], true);
		$allocations_mo = json_decode($_POST['allocations_mo'], true);
		$manual_locations = json_decode($_POST['manual_locations'], true);

		//loop through target id's (inner loop of array of id's to find a match)
		for ($i = 0; $i < sizeof($target_ids); $i++) {

			//get index in allocations_mo
			$allocation_index = array_search($target_ids[$i], array_column($allocations_mo, 'id'));

			//if index > -1, create query and save info
			if ($allocation_index > -1) {

				//create first part of query
				$query = "UPDATE fst_allocations_mo SET 
							notes = '" . mysql_escape_mimic($allocations_mo[$allocation_index]['notes']) . "', 
							shipping_place = '" . mysql_escape_mimic($allocations_mo[$allocation_index]['shipping_place']) . "'  
						WHERE id = '" . $allocations_mo[$allocation_index]['id'] . "';";
				custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
			}

			//update manually entered location (if we find an index)
			$m_index = array_search($target_ids[$i], array_column($manual_locations, 'allocation_id'));

			//if index > -1, create query and save info
			if ($m_index > -1) {

				//create first part of query
				$query = "REPLACE INTO fst_allocations_mo_manual_address (allocation_id, name, poc_name, poc_number, street, city, state, zip)
							VALUES ('" . $manual_locations[$m_index]['allocation_id'] . "', 
									'" . $manual_locations[$m_index]['name'] . "', 
									'" . $manual_locations[$m_index]['poc_name'] . "', 
									'" . $manual_locations[$m_index]['poc_number'] . "', 
									'" . $manual_locations[$m_index]['street'] . "', 
									'" . $manual_locations[$m_index]['city'] . "',
									'" . $manual_locations[$m_index]['state'] . "',
									'" . $manual_locations[$m_index]['zip'] . "');";
				custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
			}

			//same process for part info (pq_detail)
			for ($j = 0; $j < sizeof($pq_detail); $j++) {

				//look for match, update values
				if ($allocations_mo[$allocation_index]['pq_id'] == $pq_detail[$j]['project_id']) {

					//create query to update part in fst_pq_detail
					$query = "UPDATE fst_pq_detail 
								SET po_number = '" . mysql_escape_mimic($pq_detail[$j]['po_number']) . "', vendor = '" . mysql_escape_mimic($pq_detail[$j]['vendor']) . "', 
									vendor_qty = '" . mysql_escape_mimic($pq_detail[$j]['vendor_qty']) . "', vendor_cost = '" . mysql_escape_mimic($pq_detail[$j]['vendor_cost']) . "',
									external_po_notes = '" . mysql_escape_mimic($pq_detail[$j]['external_po_notes']) . "' 
								WHERE id = '" . $pq_detail[$j]['id'] . "';";
					custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
				}
			}
		}
	}

	//look at target_order_numbers (any vendor PO #'s that have changed)
	$target_order_numbers = json_decode($_POST['target_order_numbers']);
	$pq_orders = json_decode($_POST['pq_orders'], true);	//pq_orders is used for the next 2 checks

	//if we find any that have changed, we need to go through and save them
	if (sizeof($target_order_numbers) > 0) {

		//decode arrays related to vendor POs that need updated
		$vendor_keys = json_decode($_POST['vendor_keys']);

		//loop through target id's (inner loop of array of id's to find a match)
		for ($i = 0; $i < sizeof($target_order_numbers); $i++) {

			//get index in pq_orders
			$pq_index = array_search($target_order_numbers[$i], array_column($pq_orders, 'po_number'));

			//if index > -1, create query and save info
			if ($pq_index > -1) {

				//create first part of query
				$query = "UPDATE fst_pq_orders SET ";

				//create the rest of the query using update_ids array from js
				for ($j = 0; $j < sizeof($vendor_keys); $j++) {

					//treat last value differently
					if ($j == sizeof($vendor_keys) - 1)
						$query .= "`" . $vendor_keys[$j] . "` = '" . mysql_escape_mimic($pq_orders[$pq_index][$vendor_keys[$j]]) . "' ";
					else
						$query .= "`" . $vendor_keys[$j] . "` = '" . mysql_escape_mimic($pq_orders[$pq_index][$vendor_keys[$j]]) . "', ";
				}

				//end query
				$query .= " WHERE po_number = '" . $pq_orders[$pq_index]['po_number'] . "';";
				custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
			}
		}
	}

	//look at check_po_assignments (any PO assignments that may affect fst_pq_orders_assignments)
	$check_po_assignments = json_decode($_POST['check_po_assignments']);

	//if we find any that have changed, we need to go through and save them
	if (sizeof($check_po_assignments) > 0) {

		// loop through check_po_assignments, make updates to assignment table (manages multiple projects)
		foreach ($check_po_assignments as $po) {

			// search for current part assignments in our database (active state of assignments)
			$query = "SELECT po_number, project_id FROM fst_pq_detail WHERE po_number = '" . $po . "' GROUP BY po_number, project_id;";
			$result = mysqli_query($con, $query);

			// if query is empty, keep current assignment (we do not want to cause an order not to show up)
			if (mysqli_num_rows($result) == 0)
				break;

			// otherwise, drop current assignments, and add new assignments
			$query = "DELETE FROM fst_pq_orders_assignments WHERE po_number = '" . $po . "';";
			custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

			$query = "INSERT INTO fst_pq_orders_assignments (`po_number`, `pq_id`) 
						SELECT po_number, project_id FROM fst_pq_detail WHERE po_number = '" . $po . "' GROUP BY po_number, project_id;";
			custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
		}
	}

	//look at target_PO_numbers (any vendor PO #'s that have changed from the PO report tab)
	$target_po_numbers = json_decode($_POST['target_po_numbers']);

	//if we find any that have changed, we need to go through and save them
	if (sizeof($target_po_numbers) > 0) {

		//loop through target id's (inner loop of array of id's to find a match)
		for ($i = 0; $i < sizeof($target_po_numbers); $i++) {

			//get index in pq_orders
			$pq_index = array_search($target_po_numbers[$i], array_column($pq_orders, 'po_number'));

			//if index > -1, create query and save info
			if ($pq_index > -1) {

				//create first part of query
				$query = "UPDATE fst_pq_orders 
							SET need_by_date = '" . $pq_orders[$pq_index]['need_by_date'] . "', acknowledged = '" . $pq_orders[$pq_index]['acknowledged'] . "', 
							priority = '" . $pq_orders[$pq_index]['priority'] . "', vp_processed = '" . $pq_orders[$pq_index]['vp_processed'] . "' 
						WHERE po_number = '" . $pq_orders[$pq_index]['po_number'] . "';";
				custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
			}
		}
	}

	//look at target_PO_numbers (any vendor PO #'s that have changed from the PO report tab)
	$target_shipment_ids = json_decode($_POST['target_shipment_ids']);

	//if we find any that have changed, we need to go through and save them
	if (sizeof($target_shipment_ids) > 0) {

		//get current shipment info
		$pq_shipments = json_decode($_POST['pq_shipments'], true);
		$shipment_keys = json_decode($_POST['shipment_keys']);

		//loop through target id's (inner loop of array of id's to find a match)
		for ($i = 0; $i < sizeof($target_shipment_ids); $i++) {

			//get index in pq_orders
			$s_index = array_search($target_shipment_ids[$i], array_column($pq_shipments, 'shipment_id'));

			//if index > -1, create query and save info
			if ($s_index > -1) {

				// If shipping, grab old value before updating new
				if ($pq_shipments[$s_index]['shipped'] == "true" || $pq_shipments[$s_index]['shipped'] == 1) {
					$query = "SELECT shipped FROM fst_pq_orders_shipments WHERE shipment_id = '" . $pq_shipments[$s_index]['shipment_id'] . "';";
					$result = mysqli_query($con, $query);
					$previous = mysqli_fetch_array($result);
				}

				//create first part of query
				$query = "UPDATE fst_pq_orders_shipments SET ";

				//create the rest of the query using update_ids array from js
				for ($j = 0; $j < sizeof($shipment_keys); $j++) {

					//skip id
					if ($shipment_keys[$j] == "shipment_id") $j++;

					//treat last value differently
					if ($j == sizeof($shipment_keys) - 1)
						$query .= "`" . $shipment_keys[$j] . "` = '" . mysql_escape_mimic($pq_shipments[$s_index][$shipment_keys[$j]]) . "' ";
					else
						$query .= "`" . $shipment_keys[$j] . "` = '" . mysql_escape_mimic($pq_shipments[$s_index][$shipment_keys[$j]]) . "', ";
				}

				//end query
				$query .= " WHERE shipment_id = '" . $pq_shipments[$s_index]['shipment_id'] . "';";
				custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

				//if this shipment has shipped, run function to update status
				if ($pq_shipments[$s_index]['shipped'] == "true" || $pq_shipments[$s_index]['shipped'] == 1) {

					//get po_ship_to
					$query = "SELECT po_ship_to FROM fst_pq_orders WHERE po_number = '" . $pq_shipments[$s_index]['po_number'] . "';";
					$result = mysqli_query($con, $query);
					$use = mysqli_fetch_array($result);

					//pass to update function
					update_shipped_status($con, $use['po_ship_to'], $pq_shipments[$s_index]['shipment_id']);

					// Log shipment (if being moved to ship right now)
					if ($previous['shipped'] == "0") {

						// Account for all parts request tied to this shipment (may be multiple)
						$query = "SELECT quoteNumber FROM fst_pq_overview 
									WHERE id IN (SELECT project_id FROM fst_pq_detail WHERE shipment_id = '" . $pq_shipments[$s_index]['shipment_id'] . "' GROUP BY project_id);";
						$result =  mysqli_query($con, $query);

						// Loop through each quote & add notification
						while ($rows = mysqli_fetch_assoc($result)) {
							$notification = new Notifications($con, "parts_shipped", "Shipment ID: " . $pq_shipments[$s_index]['shipment_id'], $rows['quoteNumber'], $use);
							$notification->log_notification($user_info['id']);
						}
					}
				}
				//otherwise, set back to ordered
				else {
					$query = "UPDATE fst_pq_detail SET status = 'Ordered' WHERE shipment_id = '" . $pq_shipments[$s_index]['shipment_id'] . "';";
					$result = mysqli_query($con, $query);
				}
			}
		}
	}

	//initialize object that will hold all of this info (overwrite existing)
	$allocations_mo = get_db_info("fst_allocations_mo", $con);
	$pq_detail = get_db_info("fst_pq_detail", $con);
	$pq_overview = get_db_info("fst_pq_overview", $con);
	$pq_orders = get_db_info("fst_pq_orders", $con);

	// Filter parts with no description
	$parts_with_no_description = array_filter(
		$pq_detail,
		function ($obj) {
			return $obj['description'] == null;
		}
	);

	// Break into a string of target parts
	$target_parts = "'" . implode("','", array_column($parts_with_no_description, "part_id")) . "'";

	// Get info
	$user_entered_parts = get_db_info("user_entered_parts", $con, $target_parts);

	//compile object with both open and whh
	$return_array = [];
	array_push($return_array, $allocations_mo);
	array_push($return_array, $pq_detail);
	array_push($return_array, $pq_overview);
	array_push($return_array, $pq_orders);
	array_push($return_array, $user_entered_parts);

	//return to user
	echo json_encode($return_array);

	return;
}

//handles acknowleding a request (moved the request to in progress)
if ($_POST['tell'] == 'acknowledge') {

	//write new order to database
	$query = "UPDATE fst_allocations_mo SET status = 'In Progress', initiated = NOW() WHERE id = '" . $_POST['id'] . "';";
	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

	//go back to user
	return;
}

//handles removing a part from the request
if ($_POST['tell'] == 'remove_part') {

	//change part in fst_pq_detail to rejected
	$query = "DELETE FROM fst_pq_detail WHERE id = '" . $_POST['pq_detail_id'] . "';";
	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

	//update allocated info in fst_boms
	//step1 = get quoteNumber from fst_pq_overview using pq_overview_id
	$query = "SELECT quoteNumber FROM fst_pq_overview WHERE id = '" . $_POST['pq_overview_id'] . "';";
	$result = mysqli_query($con, $query);
	$info = mysqli_fetch_array($result);

	//step2 = use quote # and part number to reduce allocated number in fst_boms.
	$query = "UPDATE fst_boms SET allocated = allocated - " . $_POST['q_requested'] . " WHERE quoteNumber = '" . $info['quoteNumber'] . "' AND partNumber = '" . mysql_escape_mimic($_POST['part']) . "';";
	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

	//remove material order from queue if there are no longer any parts to be picked
	remove_from_queue_if_empty($_POST['pq_overview_id'], "purchasing");

	//log reject and detail (only if q_request is > 0 - if not, this is just a split)
	if ($_POST['q_requested'] > 0) {
		$query = "INSERT INTO fst_pq_detail_rejects (`partNumber`, `quoteNumber`, `type`, `reason`, `notes`, `user`, `time_stamp`) 
										VALUES ('" . $_POST['part'] . "', '" . $info['quoteNumber'] . "', 'Purchasing', '" . $_POST['remove_reason'] . "', '" . mysql_escape_mimic($_POST['remove_notes']) . "', '" . mysql_escape_mimic($_SESSION['firstName'] . " " . $_SESSION['lastName']) . "', NOW());";
		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
	}

	//get update fst_pq_detail and fst_allocations_mo list and send back
	$pq_detail = get_db_info("fst_pq_detail", $con);
	$allocations_mo = get_db_info("fst_allocations_mo", $con);

	//compile object with both required arrays
	$return_array = [];
	array_push($return_array, $pq_detail);
	array_push($return_array, $allocations_mo);

	//return to user
	echo json_encode($return_array);
	return;
}

//used to process a kick back with a part and report back to allocations
if ($_POST['tell'] == "kick_back") {

	// decode json objects to make decisions
	$user_info = json_decode($_POST['user_info'], true);
	$issue_parts = json_decode($_POST['issue_parts'], true);

	// initialize body early so we can add mulitple parts
	$body = "Team, <br><br>";
	$body .= "Purchasing has kicked back a part related to project #" . $_POST['project_number'] . " at the terminal.<br><br>";
	$body .= "The following parts have been kicked back due to: " . strtolower($_POST['issue_reason']) . ".<br><br>";

	// loop through issue parts & set 
	foreach ($issue_parts as $part) {

		// change part in fst_pq_detail to rejected
		$query = "UPDATE fst_pq_detail SET status = 'Requested', vendor = '', shipment_id = null, po_number = null WHERE id = '" . $part['id'] . "';";
		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

		// save reject and detail
		$query = "INSERT INTO fst_pq_detail_rejects (`partNumber`, `type`, `reason`, `notes`, `time_stamp`) VALUES ('" . $part['part'] . "', 'Purchasing', '" . $_POST['issue_reason'] . "', '" . mysql_escape_mimic($_POST['issue_notes']) . "', NOW());";
		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

		// add to body of email
		$body .= $part['part'] . "<br>";
	}

	//remove material order from queue if there are no longer any parts to be picked
	remove_from_queue_if_empty($_POST['pq_overview_id'], "purchasing");		//located in phpFunctions.php

	//change status of parts request to rejected
	$query = "UPDATE fst_pq_overview SET status = 'Rejected' WHERE id = '" . $_POST['pq_overview_id'] . "';";
	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

	// add additional space below last part
	$body .= "<br>";

	//check for notes
	if ($_POST['issue_notes'] != "")
		$body .= "Notes: " . $_POST['issue_notes'] . "<br><br>";

	$body .= "Thank you, ";

	//create subject line & body
	$subject_line = "[Item Kick Back] " . $_POST['urgency'] . " Job #" . $_POST['project_number'] . " (" . $_POST['project_name'] . ") Purchasing Team has requested a change to the original request";

	//send out email based on information provided
	//Instantiation and passing `true` enables exceptions
	$mail = new PHPMailer();
	$mail = init_mail_settings($mail);

	//Recipients
	//check to see if session email is set, if not lets use allocations
	$mail->setFrom($_SESSION['email'], $_SESSION['firstName'] . " " . $_SESSION['lastName']); //set from (name is optional)
	$mail->AddReplyTo($_SESSION['email'], $_SESSION['firstName'] . " " . $_SESSION['lastName']);

	//depending on the ship from location, tag either omaha logistics or charlotte
	if ($use == "test") {
		$mail->addAddress($_SESSION['email']);
	} else {
		$mail->addAddress('allocations@piersonwireless.com');
		$mail->addCC('purchasing@piersonwireless.com');
	}

	//add user to CC
	$mail->addCC($_SESSION['email']);

	//Content
	$mail->isHTML(true);
	$mail->Subject =  $subject_line;
	$mail->Body = $body;
	$mail->send();

	//close smtp connection
	$mail->smtpClose();

	//get update fst_pq_detail and fst_allocations_mo list and send back
	$pq_detail = get_db_info("fst_pq_detail", $con);
	$allocations_mo = get_db_info("fst_allocations_mo", $con);

	//compile object with both required arrays
	$return_array = [];
	array_push($return_array, $pq_detail);
	array_push($return_array, $allocations_mo);

	//return to user
	echo json_encode($return_array);
	return;
}

//handles splitting a line into multiple lines
if ($_POST['tell'] == 'split_line') {

	//grab previous part info based on 'part' and 'pq_id'
	$query = "SELECT * FROM fst_pq_detail WHERE id = '" . $_POST['pq_detail_id'] . "';";
	$result =  mysqli_query($con, $query);
	$pq_d = mysqli_fetch_array($result);

	//create a copy & add to fst_pq_detail
	$query = "INSERT INTO fst_pq_detail (id, project_id, part_id, previous_part, decision, mo_id, send, instructions, status, vendor_qty)
								VALUES (null, '" . $pq_d['project_id'] . "', '" . $pq_d['part_id'] . "', '" . $pq_d['previous_part'] . "','" . $pq_d['decision'] . "', '" . $pq_d['mo_id'] . "', '" . $pq_d['send'] . "', '" . $pq_d['instructions'] . "', '" . $pq_d['status'] . "', '0');";
	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

	//get pq_detail and send back to user
	$pq_orders = get_db_info("fst_pq_detail", $con);

	//echo return array
	echo json_encode($pq_orders);

	//go back to user
	return;
}

//handles creating new purchase orders
if ($_POST['tell'] == 'create_purchase_order') {

	//grab array for target items and vendor in use
	$po_items = json_decode($_POST['po_items']);
	$use_vendor = $_POST['use_vendor'];

	//get next available # and create new purchase order for vendor
	$full_po_new = get_new_fst_po_number($con);

	//write new order to database
	$query = "INSERT INTO fst_pq_orders (po_number, vendor_name) VALUES ('" . $full_po_new . "', '" . $use_vendor . "');";
	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

	//update all parts in request with new purchase order
	$pq_detail_ids = "'" . implode("','", $po_items) . "'";
	$query = "UPDATE fst_pq_detail SET po_number = '" . $full_po_new . "' WHERE id IN(" . $pq_detail_ids . ");";
	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

	//create new order in fst_pq_orders_assignments with all pq_ids that exist in current request
	$query = "REPLACE INTO fst_pq_orders_assignments (po_number, pq_id) 
				SELECT po_number, project_id FROM fst_pq_detail WHERE id IN (" . $pq_detail_ids . ") GROUP BY project_id;";
	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

	//refresh order info & parts info
	$pq_orders = get_db_info("fst_pq_orders", $con);
	$pq_detail = get_db_info("fst_pq_detail", $con);

	//set up array to be returned
	$return_array = [];
	array_push($return_array, $pq_orders);
	array_push($return_array, $pq_detail);

	//return to user
	echo json_encode($return_array);

	//go back to user
	return;
}

// handles removing a purchase order
if ($_POST['tell'] == "delete_po") {

	// write & execute queries to remove POs
	$query = "DELETE FROM fst_pq_orders WHERE po_number = '" . $_POST['po_number'] . "';";
	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

	$query = "DELETE FROM fst_pq_orders_assignments WHERE po_number = '" . $_POST['po_number'] . "';";
	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

	// set any parts assigned this PO to blank
	$query = "UPDATE fst_pq_detail SET po_number = '' WHERE po_number = '" . $_POST['po_number'] . "';";
	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

	return;
}

//used to upload copy of vendor purchase order before sending
if ($_POST['tell'] == "temp_vendor_pdf") {

	// pull the raw binary data from the POST array
	$data = substr($_POST['data'], strpos($_POST['data'], ",") + 1);

	// decode it
	$decodedData = base64_decode($data);

	// print out the raw data( debugging ) 
	//echo ($decodedData);
	$filename = "uploads/Purchase-Order-" . $_POST['po_number'] . ".pdf";

	// write the data out to the file
	$fp = fopen($filename, 'wb');
	fwrite($fp, $decodedData);

	//set target to file_name
	$target_file = $filename;

	// loop through target purchased orders
	// get all quote #'s related to po #
	$query = "SELECT a.po_number, b.quoteNumber FROM fst_pq_orders_assignments a
				LEFT JOIN fst_pq_overview b
					ON a.pq_id = b.id
				WHERE a.po_number = '" . $_POST['use_po_number'] . "';";
	$result =  mysqli_query($con, $query);

	// if we find matches, loop through quotes and save to google drive folder
	if (mysqli_num_rows($result) > 0) {
		while ($rows = mysqli_fetch_assoc($result)) {

			//get google drive id based on quote #
			$query = "SELECT googleDriveLink FROM fst_grid WHERE quoteNumber = '" . $rows['quoteNumber'] . "';";
			$result =  mysqli_query($con, $query);

			if (mysqli_num_rows($result) > 0) {
				$grid = mysqli_fetch_array($result);

				//parse out just ID from google drive link
				//googleDriveLink = https://drive.google.com/drive/folders/1AVksK2E5wO5QCP1Mlpdj7ImoPqPdgssp
				//g_drive_id = 1AVksK2E5wO5QCP1Mlpdj7ImoPqPdgssp
				$folder_pos = strpos($grid['googleDriveLink'], "folders") + 8;
				$g_drive_id = substr($grid['googleDriveLink'], $folder_pos);

				//run upload quote to drive (this works for vendor_pos as well) save error as temp (if any)
				$temp = upload_quote_to_drive($target_file, "Purchase-Order-" . $_POST['po_number'] . ".pdf", $g_drive_id);
			}
		}
	}

	//close connection
	fclose($fp);

	return;
}

//used to process MO request back from the warehouse team
if ($_POST['tell'] == "send_vendor_pos") {

	//current directory
	$target_dir = getcwd();

	//read in user_info
	$user_info = json_decode($_POST['user_info'], true);

	//loop through list of purchase order # and send email to vendors
	$purchase_orders = json_decode($_POST['purchase_orders']);
	$pq_orders = json_decode($_POST['pq_orders'], true);

	for ($i = 0; $i < sizeof($purchase_orders); $i++) {

		//get index in pq_orders (so we can access pq_order info)
		$order_index = array_search($purchase_orders[$i], array_column($pq_orders, 'po_number'));

		//adjust purchase order # if this is a revision
		if ($pq_orders[$order_index]['revision'] != "0")
			$purchase_orders[$i] .= "-" . $pq_orders[$order_index]['revision'];

		//get poc contact info from our database
		$query = "select * from fst_vendor_list_poc WHERE name = '" . mysql_escape_mimic($pq_orders[$order_index]['vendor_poc']) . "' AND vendor_id = '" . $pq_orders[$order_index]['vendor_id'] . "';";
		$result =  mysqli_query($con, $query);

		//if we do not find a vendor in the vendor list, stop here (it is critical to have vendor POC email for the next step)
		/*if (mysqli_num_rows($result) == 0){
			echo "Error: There was an error accessing the vendor POCs email. Please try saving & refreshing and sending the purchase order again. If this persists, please reach out to fst@piersonwireless.com for help.";
			return;
		}*/


		//skip sending email if type is none (selected by user)
		if ($_POST['type'] != "none") {

			//Step 1: Send email to cc'd individuals
			//Instantiation and passing `true` enables exceptions
			$mail = new PHPMailer();
			$mail = init_mail_settings($mail, $user_info);
			//$mail = init_mail_settings_oauth($mail, $user_info);

			if ($use == "test")
				$mail->addAddress($user_info['email']);
			else {
				$mail->addCC("orders@piersonwireless.com"); 			//cc orders team on all requests
			}

			//use custom function to add to, cc, and bcc
			if ($pq_orders[$order_index]['email_to'] != "")
				$mail = add_custom_recipients($mail, $pq_orders[$order_index]['email_to'], "To");

			if ($pq_orders[$order_index]['email_cc'] != "")
				$mail = add_custom_recipients($mail, $pq_orders[$order_index]['email_cc'], "CC");

			if ($pq_orders[$order_index]['email_bcc'] != "")
				$mail = add_custom_recipients($mail, $pq_orders[$order_index]['email_bcc'], "BCC");

			//add to address based on POC info
			$mail->addBCC($user_info['email']); 					//bcc yourself

			//check to see if pick-ticket exists on server and add as attachment
			$target_vendor_po = $target_dir . "\\uploads\\Purchase-Order-" . $purchase_orders[$i] . ".pdf";

			if (file_exists($target_vendor_po))
				$mail->addAttachment($target_vendor_po);
			else {
				echo "Error. Failed to send purchase order #" . $purchase_orders[$i] . ". Please try again (if issues continue, please fst@piersonwireless.com).";
				return;
			}

			//create email body & subject
			$body = str_replace("\n", "<br>", $pq_orders[$order_index]['email_body']) . "<br><br>";
			$body .= create_signature($mail, $user_info);

			//Content
			$mail->isHTML(true);
			$mail->Subject =  $pq_orders[$order_index]['email_subject'];
			$mail->Body = $body;
			$mail->send();

			//close smtp connection
			$mail->smtpClose();

			//delete pick-ticket created for the email from server
			if (file_exists($target_vendor_po))
				unlink($target_vendor_po);
		}

		//update pq_order not captured elsewhere
		//set array of columns we would like to update
		$col = ['total_price'];

		//begin query
		$query = "UPDATE fst_pq_orders SET ";

		//loop through col's
		for ($j = 0; $j < sizeof($col); $j++) {
			$query .= "`" . $col[$j] . "` = '" . mysql_escape_mimic($pq_orders[$order_index][$col[$j]]) . "', ";
		}

		//add time_stamp and set WHERE condition
		$query .= "date_issued = NOW() WHERE po_number = '" . $pq_orders[$order_index]['po_number'] . "';";
		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

		//reset purchase order to original state (for next part -> updating pq_detatil, pq_orders, etc.)
		//we need to reset just in case we added revision # at the beginning
		$purchase_orders[$i] = $pq_orders[$order_index]['po_number'];

		//insert first note into fst_pq_orders_po
		//$query = "INSERT INTO fst_pq_orders_notes (id, po_number, notes, user, date) 
		//									VALUES (null, '" . $purchase_orders[$i] . "', '" . mysql_escape_mimic($_POST['first_note']) . "', '" . $user_info['id'] . "', NOW())";
		//custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

		//reset shipment_ids if they no longer apply to this purchase order
		$query = "UPDATE fst_pq_detail 
					SET shipment_id = null
					WHERE po_number = '" . $purchase_orders[$i] . "' AND shipment_id NOT IN (select shipment_id from fst_pq_orders_shipments WHERE po_number = '" . $purchase_orders[$i] . "');";
		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

		//get list of shipment_ids related to order, update part statuses accordingly (this is done if an order was revised but already had parts shipped)
		$query = "SELECT shipment_id, shipped FROM fst_pq_orders_shipments WHERE po_number = '" . $purchase_orders[$i] . "';";
		$result = mysqli_query($con, $query);

		//if we have shipments assigned, enter condition
		if (mysqli_num_rows($result) > 0) {

			//loop through rows, if shipped, call query to update statuses
			while ($rows = mysqli_fetch_assoc($result)) {
				if ($rows['shipped'] == 1)	//stored as boolean (1 = true, 0 = false)
					update_shipped_status($con, $pq_orders[$order_index]['po_ship_to'], $rows['shipment_id']);	//in phpFunctions.php
			}
		}
	}

	//update all parts in pq_detail which match the parts in this request
	//pot. unsafe against SQL injected, will not be an issue since id's are defined by us on client
	$query = "UPDATE fst_pq_detail SET status = 'Ordered' WHERE po_number IN('" . implode("','", $purchase_orders) . "') AND status = 'Pending';";
	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

	//update purchase order #'s
	$query = "UPDATE fst_pq_orders SET status = 'Submitted' WHERE po_number IN('" . implode("','", $purchase_orders) . "');";
	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

	//get pq_id's from fst_pq_detail
	//use to check if all parts have been processed for request and it is OK to remove from queue
	$project_ids = [];
	$query = "SELECT pq_id FROM fst_pq_orders_assignments WHERE po_number IN('" . implode("','", $purchase_orders) . "') GROUP BY pq_id;";
	$result = mysqli_query($con, $query);
	while ($rows = mysqli_fetch_assoc($result)) {
		array_push($project_ids, $rows['pq_id']);
	}

	//loop through all project_ids and run checks to see if this needs to be removed from queue
	foreach ($project_ids as $pq_id) {
		//remove request from orders queue if all parts are spoken for
		$query = "SELECT * FROM fst_pq_detail WHERE project_id = '" . $pq_id . "' AND status = 'Pending' AND decision = 'PO';";
		$result = mysqli_query($con, $query);
		if (mysqli_num_rows($result) == 0) {
			$query = "UPDATE fst_allocations_mo SET status = 'Shipped', closed = NOW() WHERE pq_id = '" . $pq_id . "' AND mo_id = 'PO';";
			custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
		}
	}

	//get parts related to purchase orders & update cost in invreport
	$query = "SELECT part_id, vendor_cost, vendor_qty FROM fst_pq_detail WHERE po_number IN('" . implode("','", $purchase_orders) . "');";
	$result = mysqli_query($con, $query);

	//loop and update in invreport
	while ($rows = mysqli_fetch_assoc($result)) {

		//create new instance of part
		$part = new Part($rows['part_id'], $con);
		$part->log_part_update($user_info, "Part Ordered (QTY: " . $rows['vendor_qty'] . " | Cost: " . $rows['vendor_cost'] . ")", "PO");

		//query to update cost in invreport
		/*$query = "UPDATE invreport SET cost = '" . $rows['vendor_cost'] . "' WHERE partNumber = '" . mysql_escape_mimic($rows['part_id']) . "';";
		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

		//query to log part update
		$query = "INSERT INTO invreport_logs (partNumber, description, user, type, time_stamp) 
									VALUES ('" . mysql_escape_mimic($rows['part_id']) . "', 'Part Ordered (QTY: " . $part->info['cost'] . " | new: " . $rows['vendor_cost'] . ")', 
											'" . $user_info['id'] . "', 'PA', NOW());";
		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);*/
	}

	//return without updates (browser will refresh)
	return;
}

//used to create new vendors & contact accounting about vendor creation
if ($_POST['tell'] == "create_new_vendor") {

	//insert vendor to database
	$query = "INSERT INTO fst_vendor_list (id, vendor, poc, phone, street, city, state, zip, country) 
								   VALUES (null, '" . mysql_escape_mimic($_POST['nv_vendor']) . "', '" . mysql_escape_mimic($_POST['nv_poc']) . "', '" . mysql_escape_mimic($_POST['nv_phone']) . "', '" . mysql_escape_mimic($_POST['nv_street']) . "', '" . mysql_escape_mimic($_POST['nv_city']) . "', '" . mysql_escape_mimic($_POST['nv_state']) . "', '" . mysql_escape_mimic($_POST['nv_zip']) . "', '" . mysql_escape_mimic($_POST['nv_country']) . "');";
	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

	//get id from previous entry
	$last_id = mysqli_insert_id($con);

	//insert first POC into vendor POC list
	$query = "INSERT INTO fst_vendor_list_poc (vendor_id, name, phone, email) 
								   VALUES ('" . $last_id . "', '" . mysql_escape_mimic($_POST['nv_poc']) . "', '" . mysql_escape_mimic($_POST['nv_phone']) . "', '" . mysql_escape_mimic($_POST['nv_email']) . "');";
	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

	//return new vendor id
	echo $last_id;

	return;
}

//used to create new vendors & contact accounting about vendor creation
if ($_POST['tell'] == "create_new_location") {

	//insert vendor to database
	$query = "INSERT INTO general_shippingadd (customer, name, recipient, address, city, state, zip, phone, email) 
									VALUES ('PW-Custom', '" . mysql_escape_mimic($_POST['cl_name']) . "', 
											'" . mysql_escape_mimic($_POST['cl_attention']) . "', '" . mysql_escape_mimic($_POST['cl_street']) . "', 
											'" . mysql_escape_mimic($_POST['cl_city']) . "', '" . mysql_escape_mimic($_POST['cl_state']) . "', 
											'" . mysql_escape_mimic($_POST['cl_zip']) . "', '" . mysql_escape_mimic($_POST['cl_phone']) . "', 
											'" . mysql_escape_mimic($_POST['cl_email']) . "');";
	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

	return;
}

//used to create new vendors & contact accounting about vendor creation
if ($_POST['tell'] == "create_revision") {

	//look up po_number (we need previous revision number)
	$query = "SELECT * FROM fst_pq_orders WHERE po_number = '" . $_POST['po_number'] . "';";
	$result = mysqli_query($con, $query);
	$pq_orders = mysqli_fetch_array($result);

	//incremenet revision by 1
	$new_revision = intval($pq_orders['revision']) + 1;

	//update pq_orders, fst_allocations_mo, & pq_detail
	//pq_orders
	$query = "UPDATE fst_pq_orders SET status = 'Open', revision = '" . $new_revision . "', ready = 0, acknowledged = 0 WHERE po_number = '" . $_POST['po_number'] . "';";
	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

	//pq_detail
	$query = "UPDATE fst_pq_detail SET status = 'Pending' WHERE po_number = '" . $_POST['po_number'] . "';";
	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

	//fst_allocations_mo
	$query = "UPDATE fst_allocations_mo SET status = 'Revision' 
				WHERE pq_id IN(SELECT pq_id FROM fst_pq_orders_assignments WHERE po_number = '" . $_POST['po_number'] . "') AND mo_id = 'PO';";
	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

	return;
}

//used to create new vendors & contact accounting about vendor creation
if ($_POST['tell'] == "create_new_vendor_poc") {

	//insert first POC into vendor POC list
	$query = "INSERT INTO fst_vendor_list_poc (vendor_id, name, phone, email) 
								   VALUES ('" . $_POST['vendor_id'] . "', '" . mysql_escape_mimic($_POST['nvp_poc']) . "', '" . mysql_escape_mimic($_POST['nvp_phone']) . "', '" . mysql_escape_mimic($_POST['nvp_email']) . "');";
	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

	return;
}

//used to create new shipment
if ($_POST['tell'] == "create_new_shipment") {

	//decode shipment_ids passed from js
	$shipment_part_ids = json_decode($_POST['shipment_part_ids'], true);
	$pq_detail = json_decode($_POST['pq_detail'], true);
	$pq_overview = json_decode($_POST['pq_overview'], true);
	$user_info = json_decode($_POST['user_info'], true);

	//use 1st ID and get po_number from server (may find better way to pass from JS in future)
	$query = "SELECT po_number, project_id FROM fst_pq_detail WHERE id = '" . $shipment_part_ids[0]['id'] . "';";
	$result = mysqli_query($con, $query);
	$use = mysqli_fetch_array($result);

	//$use['po_number'] is the po_number we will reference for shipment creation. Once we have created a new shipment ID, we will tie this to the parts in shipment_part_ids
	//insert shipment info to database
	$query = "INSERT INTO fst_pq_orders_shipments (shipment_id, po_number, tracking, carrier, cost, ship_date, arrival, shipped, date_created) 
								   VALUES (null, '" . mysql_escape_mimic($use['po_number']) . "', '" . mysql_escape_mimic($_POST['ns_tracking']) . "', 
											'" . mysql_escape_mimic($_POST['ns_carrier']) . "', '" . mysql_escape_mimic($_POST['ns_cost']) . "',
											'" . mysql_escape_mimic($_POST['ns_ship_date']) . "', '" . mysql_escape_mimic($_POST['ns_arrival']) . "', 
											" . mysql_escape_mimic($_POST['ns_shipped']) . ", NOW());";
	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

	//get id from previous entry
	$last_id = mysqli_insert_id($con);

	//insert first note for shipment if applicable
	if ($_POST['ns_notes'] != "") {

		//create query to add note
		$query = "INSERT INTO fst_pq_orders_notes (id, po_number, shipment_id, notes, user, date) 
											VALUES (null, '" . mysql_escape_mimic($use['po_number']) . "', 
													'" . mysql_escape_mimic($last_id) . "', '" . mysql_escape_mimic($_POST['ns_notes']) . "', 
													'" . $_SESSION['employeeID'] . "', NOW())";
		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
	}

	//loop through shipment IDs and update shipment ID's for parts affected
	for ($i = 0; $i < sizeof($shipment_part_ids); $i++) {

		//get index in pq_detail
		$index = array_search($shipment_part_ids[$i]['id'], array_column($pq_detail, 'id'));

		//compare quantities
		//if equal, update the ID and move to the next part
		if ($shipment_part_ids[$i]['quantity'] == $pq_detail[$index]['vendor_qty']) {

			//update fst_pq_detail, set shipment ID to ID we just created
			$query = "UPDATE fst_pq_detail SET shipment_id = '" . $last_id . "' WHERE id = '" . $shipment_part_ids[$i]['id'] . "';";
			custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
		}
		//if not equal, we need to create a new entry so they can be tracked seperately
		else {
			//first, update current row with shipment ID, and adjust quantity
			$query = "UPDATE fst_pq_detail SET shipment_id = '" . $last_id . "', vendor_qty = '" . $shipment_part_ids[$i]['quantity'] . "' WHERE id = '" . $shipment_part_ids[$i]['id'] . "';";
			custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

			//now set new quantity and insert new record to fst_pq_detail with matching info
			$new_quantity = intval($pq_detail[$index]['vendor_qty']) - intval($shipment_part_ids[$i]['quantity']);
			$query = "INSERT INTO fst_pq_detail (id, project_id, part_id, previous_part, decision, send, instructions, status, po_number, vendor, vendor_qty, vendor_cost) 
										VALUES (null, '" . mysql_escape_mimic($pq_detail[$index]['project_id']) . "', '" . mysql_escape_mimic($pq_detail[$index]['part_id']) . "', 
													'" . mysql_escape_mimic($pq_detail[$index]['previous_part']) . "', '" . mysql_escape_mimic($pq_detail[$index]['decision']) . "', 
													'" . mysql_escape_mimic($pq_detail[$index]['send']) . "', '" . mysql_escape_mimic($pq_detail[$index]['instructions']) . "',
													'" . mysql_escape_mimic($pq_detail[$index]['status']) . "', '" . mysql_escape_mimic($pq_detail[$index]['po_number']) . "',
													'" . mysql_escape_mimic($pq_detail[$index]['vendor']) . "', '" . $new_quantity . "', 
													'" . mysql_escape_mimic($pq_detail[$index]['vendor_cost']) . "');";
			custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
		}
	}

	//get id in pq_overview (used for quoteNumber)
	$overview_index = array_search($use['project_id'], array_column($pq_overview, 'id'));

	//if this shipment has shipped, run function to update status
	if ($_POST['ns_shipped'] == "true" || $_POST['ns_shipped'] == 1) {
		//get po_ship_to
		$query = "SELECT po_ship_to FROM fst_pq_orders WHERE po_number = '" . $use['po_number'] . "';";
		$result = mysqli_query($con, $query);
		$use_place = mysqli_fetch_array($result);

		update_shipped_status($con, $use_place['po_ship_to'], $last_id);

		// Account for all parts request tied to this shipment (may be multiple)
		$query = "SELECT quoteNumber FROM fst_pq_overview 
					WHERE id IN (SELECT project_id FROM fst_pq_detail WHERE shipment_id = '" . $last_id . "' GROUP BY project_id);";
		$result =  mysqli_query($con, $query);

		// Loop through each quote & add notification
		while ($rows = mysqli_fetch_assoc($result)) {
			$notification = new Notifications($con, "parts_shipped", "Shipment ID: " . $last_id, $rows['quoteNumber'], $use);
			$notification->log_notification($user_info['id']);
		}
	}

	//get fst_pq_detail info & shipping info, and return to user.
	$pq_detail = get_db_info("fst_pq_detail", $con);
	$shipping_info = get_db_info("fst_pq_orders_shipments", $con);
	$notes = get_db_info("fst_pq_orders_notes", $con);

	//compile object with both required arrays
	$return_array = [];
	array_push($return_array, $pq_detail);
	array_push($return_array, $shipping_info);
	array_push($return_array, $notes);

	//return to user
	echo json_encode($return_array);

	return;
}

//handles removing items from shipment OR delete shipment ID
if ($_POST['tell'] == "delete_shipment") {

	//check type (if full, remove shipment id)
	if ($_POST['type'] == "full") {

		//remove shipment_id for all pq_detail parts
		$query = "UPDATE fst_pq_detail SET shipment_id = null, status = 'Ordered' WHERE shipment_id = '" . $_POST['shipment_id'] . "';";
		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

		//delete shipment_id from fst_pq_orders_shipments
		$query = "DELETE FROM fst_pq_orders_shipments WHERE shipment_id = '" . $_POST['shipment_id'] . "';";
		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
	}
	//otherwise this is just a part #, update pq_detail and set shipment_id to '' where we have a match
	else {

		//remove shipment_id for all pq_detail parts
		$query = "UPDATE fst_pq_detail SET shipment_id = null, status = 'Ordered' WHERE shipment_id = '" . $_POST['shipment_id'] . "' AND part_id = '" . mysql_escape_mimic($_POST['type']) . "';";
		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
	}

	//get fst_pq_detail info & shipping info, and return to user.
	$pq_detail = get_db_info("fst_pq_detail", $con);
	$shipping_info = get_db_info("fst_pq_orders_shipments", $con);
	$notes = get_db_info("fst_pq_orders_notes", $con);

	//compile object with both required arrays
	$return_array = [];
	array_push($return_array, $pq_detail);
	array_push($return_array, $shipping_info);
	array_push($return_array, $notes);

	//return to user
	echo json_encode($return_array);
	return;
}

//handles updating po # (temporary placeholder until issues get resolved.)
if ($_POST['tell'] == "temp_new_po") {

	$query = "SELECT * FROM fst_pq_orders WHERE po_number = '" . $_POST['new_po'] . "';";
	$result = mysqli_query($con, $query);

	if (mysqli_num_rows($result) > 0) {
		echo "Error: This PO number is already taken. Please refresh the page and try again for more detail.";
		return;
	}

	$query = "update fst_pq_orders SET po_number = '" . $_POST['new_po'] . "' WHERE po_number = '" . $_POST['curr_po'] . "';";
	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

	$query = "update fst_pq_orders_notes SET po_number = '" . $_POST['new_po'] . "' WHERE po_number = '" . $_POST['curr_po'] . "';";
	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

	$query = "update fst_pq_detail SET po_number = '" . $_POST['new_po'] . "' WHERE po_number = '" . $_POST['curr_po'] . "';";
	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

	$query = "update fst_pq_orders_shipments SET po_number = '" . $_POST['new_po'] . "' WHERE po_number = '" . $_POST['curr_po'] . "';";
	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

	$query = "update fst_pq_orders_assignments SET po_number = '" . $_POST['new_po'] . "' WHERE po_number = '" . $_POST['curr_po'] . "';";
	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

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
		$query = "INSERT INTO fst_pq_orders_notes (id, po_number, shipment_id, notes, user, date) 
											VALUES (null, '" . $_POST['po_number'] . "', '" . $_POST['shipment_id'] . "', '" . mysql_escape_mimic($_POST['note']) . "', '" . $_SESSION['employeeID'] . "', NOW())";

		//call custom_query (executes query (2nd parameter) returns false if successful (true if error is returned) - in phpFunctions.php)
		//outputs error message if error occurs & sends to fst@pw.com for troubleshooting
		if (custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__))
			return;
	}

	//edit existing notes
	if ($_POST['type'] == 1) {

		//create query to add note
		$query = "UPDATE fst_pq_orders_notes SET notes = '" . $_POST['note'] . "' WHERE id = '" . $_POST['id'] . "';";

		//call custom_query (executes query (2nd parameter) returns false if successful (true if error is returned) - in phpFunctions.php)
		//outputs error message if error occurs & sends to fst@pw.com for troubleshooting
		if (custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__))
			return;
	}


	//remove existing note
	if ($_POST['type'] == 2) {

		//create query to add note
		$query = "DELETE FROM fst_pq_orders_notes WHERE id = '" . $_POST['id'] . "';";

		//call custom_query (executes query (2nd parameter) returns false if successful (true if error is returned) - in phpFunctions.php)
		//outputs error message if error occurs & sends to fst@pw.com for troubleshooting
		if (custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__))
			return;
	}

	//grab new list of notes
	$notes = get_db_info("fst_pq_orders_notes", $con);

	//echo return array
	echo json_encode($notes);

	return;
}

if ($_POST['tell'] == "create_custom_order") {

	return;

	//first insert entry into fst_pq_overview, set status = 'Submitted'
	/*$query = "INSERT INTO fst_pq_overview (id, type, status, project_id, project_name, cc_email, 
											requested_by, poc_name, poc_email, shipping_loc, shipping_street, 
											shipping_city, shipping_state, shipping_zip, liftgate, due_date, 
											sched_opt, sched_time, urgency, requested)
									VALUES (null, 'PM', 'Submitted', '" . $_POST[''] . "');"
	

	//next, create entry into fst_pq_orders with id from previous entry


	$query = "update fst_pq_orders SET po_number = '" . $_POST['new_po'] . "' WHERE po_number = '" . $_POST['curr_po'] . "';";
	
	//first query should throw error if we try to change to PO # already assigned
	if (custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__))
		return;
	
	$query = "update fst_pq_detail SET po_number = '" . $_POST['new_po'] . "' WHERE po_number = '" . $_POST['curr_po'] . "';";
	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

	$query = "update fst_pq_orders_shipments SET po_number = '" . $_POST['new_po'] . "' WHERE po_number = '" . $_POST['curr_po'] . "';";
	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

	return;
	*/
}

if ($_POST['tell'] == "open_stock_order") {

	//write & execute query
	$query = "UPDATE fst_allocations_mo SET status = 'Open' WHERE project_id = '" . $_POST['id'] . "' AND mo_id = 'PO';";
	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
}

//function defined to return array of objects (should match queries to get info in terminal_orders.php)
//param 1 = type (determines the type of info to be returned / query executed)
//param 2 = SQL connection
function get_db_info($type, $con, $target_parts = null)
{

	//decide which query based on type
	if ($type == "fst_allocations_mo")
		$query = "select * from fst_allocations_mo where ship_from = 'PO' order by closed asc, date_created asc;";
	elseif ($type == "fst_pq_detail")
		$query = "SELECT a.*, 
					b.uom, b.partDescription, b.partCategory, b.cost, b.manufacturer, b.`OMA-1` + b.`OMA-2` + b.`OMA-3` + b.`CHA-1` + b.`CHA-3` AS 'qty_main',
					c.quoteNumber, c.project_id AS 'vp_id', c.project_name, c.shipping_loc, c.staging_loc, d.shipping_place, d.id AS 'allocation_id'
					FROM fst_pq_detail a
					LEFT JOIN invreport b
						ON a.part_id = b.partNumber
					LEFT JOIN fst_pq_overview c
						ON a.project_id = c.id
					LEFT JOIN fst_allocations_mo d
						ON a.project_id = d.pq_id AND d.mo_id = 'PO'
					WHERE a.decision = 'PO' AND a.send = 'true' AND a.status <> 'Requested'
					ORDER BY a.id;";
	elseif ($type == "fst_pq_overview")
		$query = "select id, cust_pn, oem_num, bus_unit_num, loc_id, staging_loc FROM fst_pq_overview WHERE status != 'Closed';";
	elseif ($type == "fst_pq_orders_shipments")
		$query = "select * FROM fst_pq_orders_shipments;";
	elseif ($type == "fst_pq_orders") {
		$query = "SELECT z.pq_id, b.quoteNumber, b.project_id as 'vp_id', c.googleDriveLink, CONCAT(c.location_name, ' ', c.phaseName) as 'project_name', count(d.id) as 'unassigned_items', a.* 
					FROM fst_pq_orders_assignments z
						LEFT JOIN fst_pq_orders a
							ON z.po_number = a.po_number
						LEFT JOIN fst_pq_overview b
							ON z.pq_id = b.id
						LEFT JOIN fst_grid c
							ON b.quoteNumber = c.quoteNumber
						LEFT JOIN fst_pq_detail d
							ON a.po_number = d.po_number AND (d.shipment_id is null or d.shipment_id = '')
						GROUP BY a.po_number, z.pq_id
						ORDER BY a.po_number ASC;";
	} elseif ($type == "fst_pq_orders_notes")
		$query = "SELECT CONCAT(b.firstName, ' ', b.lastName) as name, a.* FROM fst_pq_orders_notes a, fst_users b WHERE a.user = b.id ORDER BY a.id desc;";
	elseif ($type == "user_entered_parts")
		$query = "SELECT partNumber, description, manufacturer, uom, cost FROM fst_newparts WHERE partNumber IN (" . $target_parts . ") ORDER BY date DESC;";


	//execute selected query
	$result = mysqli_query($con, $query);

	//init return array
	$return_array = [];

	//loop and add to arrays
	//if this is pq_detail, treat a little differently
	if ($type == "fst_pq_orders") {

		//set previous_po
		$previous_po = "";

		while ($rows = mysqli_fetch_assoc($result)) {

			//update googledrive AND project_name if null
			if ($rows['googleDriveLink'] === null) {
				$rows['googleDriveLink'] = "";
				$rows['project_name'] = "";
			}

			// update temporary shipping for certain jobs
			if ($rows['status'] != "Open" && $rows['po_ship_to'] == "Temporary Location")
				$rows['po_ship_to'] = $rows['po_ship_to_temporary'];

			//add column for PO filtering
			if (strlen($rows['po_number']) <= 4)
				$rows['po_number_filter'] = intval($rows['po_number']);
			else
				$rows['po_number_filter'] = intval(substr($rows['po_number'], 4));

			//if previous PO matches, update certain attributes to include new info
			if ($previous_po == $rows['po_number']) {
				$return_array[sizeof($return_array) - 1]['quoteNumber'] .= "|" . $rows['quoteNumber'];
				//$pq_orders[sizeof($pq_orders) - 1]['googleDriveLink'] .= "|" . $rows['googleDriveLink'];
				$return_array[sizeof($return_array) - 1]['project_name'] .= "|" . $rows['project_name'];
				$return_array[sizeof($return_array) - 1]['vp_id'] .= "|" . $rows['vp_id'];
			}
			//otherwise push new entry
			else
				array_push($return_array, $rows);

			//set new previous po
			$previous_po = $rows['po_number'];
		}
	} else {
		while ($rows = mysqli_fetch_assoc($result)) {
			array_push($return_array, $rows);
		}
	}

	return $return_array;
}
