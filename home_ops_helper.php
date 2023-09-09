<?php

// Load in dependencies (including starting a session)
session_start();
include('phpFunctions.php');	// loaded in Notifications.php
include('phpFunctions_views.php');
include('constants.php');
include('phpFunctions_drive.php');
include('PHPClasses/Notifications.php');

//Import PHPMailer classes into the global namespace
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once 'PHPMailer/Exception.php';
require_once 'PHPMailer/PHPMailer.php';
require_once 'PHPMailer/SMTP.php';

// Load the database configuration file
require_once 'config.php';

//handles saving any changes made from the dashboard (start date, project status, Project Owner, etc.)
if ($_POST['tell'] == "save_changes") {

	// decode arrays sent from javascript
	$updated_grid = json_decode($_POST['updated_grid'], true);
	$updated_requests = json_decode($_POST['updated_requests'], true);
	$new_part_notifications = json_decode($_POST['new_part_notifications'], true);
	$new_quote_notifications = json_decode($_POST['new_quote_notifications'], true);
	$curr_fields_quote = json_decode($_POST['curr_fields_quote'], true);
	$user_info = json_decode($_POST['user_info'], true);

	// loop through update info object & write query to update relevant info
	for ($i = 0; $i < sizeof($updated_grid); $i++) {

		//create custom update sql query (custom function in phpFunctions.php)
		$query = create_custom_update_sql_query($updated_grid[$i], $curr_fields_quote, "fst_grid", ['quoteNumber']);
		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

		// if project is in post-installation, check to see if all necessary fields are complete to move project to complete (and timestamp it)
		if ($updated_grid[$i]['fst_status'] == "Post-Installation" && $updated_grid[$i]['cop_task'] == "Complete" && $updated_grid[$i]['invoice_complete'] == 1) {
			$query = "UPDATE fst_grid 
						SET fst_status = 'Complete', ops_complete_timestamp = NOW() 
						WHERE quoteNumber = '" . $updated_grid[$i]['quoteNumber'] . "';";
			custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
		}

		// if the ops_status has been moved to complete, make sure we remove services requested & timestamp
		elseif ($updated_grid[$i]['ops_status'] == "Complete" && $updated_grid[$i]['ops_services'] != "") {
			$query = "UPDATE fst_grid 
						SET ops_services = '', ops_complete_timestamp = NOW() 
						WHERE quoteNumber = '" . $updated_grid[$i]['quoteNumber'] . "';";
			custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
		}
	}

	// loop and update service request changes
	foreach ($updated_requests as $request) {

		// if status is complete for the first time, timestamp it
		if ($request['status'] == "Complete") {

			// Get current status
			$query = "SELECT status FROM fst_grid_service_request WHERE id = '" . $request['id'] . "';";
			$result = mysqli_query($con, $query);
			$row = mysqli_fetch_array($result);
			if ($row['status'] != "Complete") {
				$query = "UPDATE fst_grid_service_request SET timestamp_completed = NOW() WHERE id = '" . $request['id'] . "'";
				custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
				$notification = new Notifications($con, "ops_request", "Ops Request Complete", $request['quoteNumber'], $use);
				$notification->log_notification($user_info['id']);
			}
		}

		//create custom update sql query (custom function in phpFunctions.php)
		$query = "UPDATE fst_grid_service_request 
					SET date_from = '" . $request['date_from'] . "', date_to = '" . $request['date_to'] . "', 
						status = '" . $request['status'] . "'
					WHERE id = '" . $request['id'] . "';";
		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

		// Update opsLead
		$query = "UPDATE fst_grid SET opsLead = '" . $request['opsLead'] . "' WHERE quoteNumber = '" . $request['quoteNumber'] . "';";
		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

		// Notify user of if assigned for the first time (check assigned flag)
		if ($request['assigned'] == 1)
			notify_user_assignment($request['id'], $user_info);
	}

	// If we have new quote notification settings (related to parts) update these
	if (sizeof($new_quote_notifications) > 0) {

		// Delete all notifications related to each quote / user combo
		foreach ($new_quote_notifications as $quote) {
			$query = "DELETE FROM fst_users_notifications_parts WHERE quoteNumber = '" . $quote . "' AND user_id = '" . $user_info['id'] . "';";
			custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
		}

		// Loop through all new notifications & enter preferences
		foreach ($new_part_notifications as $part) {
			$query = "INSERT INTO fst_users_notifications_parts 
										(`quoteNumber`, `user_id`, `partNumber`) 
								VALUES ('" . $part['quoteNumber'] . "', '" . $user_info['id'] . "', '" . mysql_escape_mimic($part['partNumber']) . "');";
			custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
		}
	}

	// check if we need to refresh items in the queue & send back
	if ($_POST['refresh_queue'] == 'true') {

		// Create new instance of user so we can get operations views
		$user = new User($user_info['id'], $con);
		$ops_view = get_operations_views($con, $user);

		//return to user
		echo json_encode($ops_view);
	}

	return;
}

//handles updating tech assignments from home_ops.php
if ($_POST['tell'] == "update_techs") {

	//if type is add, then create query to add new tech
	if ($_POST['type'] == "add") {
		//create custom update sql query (custom function in phpFunctions.php)
		$query = "INSERT INTO fst_grid_tech_assignments (quoteNumber, tech) VALUES ('" . $_POST['quote'] . "', '" . mysql_escape_mimic($_POST['tech']) . "');";
		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
	} else if ($_POST['type'] == "remove") {
		//create custom query to remove tech
		$query = "DELETE FROM fst_grid_tech_assignments WHERE quoteNumber = '" . $_POST['quote'] . "' AND tech = '" . mysql_escape_mimic($_POST['tech']) . "';";
		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
	}

	return;
}

// handles gettting jha information for a quote #
if ($_POST['tell'] == "get_jha") {

	// run query to check if we have JHA information saved
	$query = "SELECT * FROM jha_form 
				WHERE quote_number = '" . $_POST['quote'] . "' 
				ORDER BY revision DESC LIMIT 1;";
	$result = mysqli_query($con, $query);

	if (mysqli_num_rows($result) > 0) {

		// get jha info, return as encoded JSON object
		$jha_info = mysqli_fetch_array($result);
		echo json_encode($jha_info);
	}

	return;
}

// handles modification of JHA form
if ($_POST['tell'] == "modify_jha") {

	if ($_POST['action'] == "save")
		save_jha($con, $_POST);
	elseif ($_POST['action'] == "submit")
		submit_jha($con, $_POST);
	elseif ($_POST['action'] == "revise")
		revise_jha($con, $_POST);
	elseif ($_POST['action'] == "acknowledge")
		acknowledge_jha($con, $_POST);

	return;
}

// handles notifying new tech addition to review jha
if ($_POST['tell'] == "alert_tech_jha") {

	// decode json objects
	$user_info = json_decode($_POST['user_info'], true);

	// initialize mailer object & settings
	$mail = new PHPMailer();
	$mail = init_mail_settings($mail, $user_info);

	// look at tech's assigned to quote, send out email asking them to review the contents of the JHA w/link
	$query = "SELECT email FROM fst_users WHERE CONCAT(firstName, ' ', lastName) = '" . mysql_escape_mimic($_POST['tech']) . "';";
	$result = mysqli_query($con, $query);

	// if no match, return with error
	if (mysqli_num_rows($result) == 0) {
		echo "No email found for technician in our system.";
		return;
	}

	// grab email & add to $mail object
	$fst_users = mysqli_fetch_array($result);
	$mail->addAddress($fst_users['email']);

	// create body
	$body  = "Hello,<br><br>";
	$body .= "You have a JHA to review. Please review & acknowledge using the link below:<br>";
	$body .= "https://pw-fst.northcentralus.cloudapp.azure.com/FST/application.php?quote=" . $_POST['quote'] . "&jha=1<br><br>";
	$body .= "Thank you";

	// set subject & body & send to users
	$mail->isHTML(true);
	$mail->Subject = "Please Review JHA Agreement for " . $_POST['project_name'];
	$mail->Body = $body;
	$mail->send();

	// close smtp connection
	$mail->smtpClose();
}

// used to request services from other workgroups
if ($_POST['tell'] == "service_request") {

	// call function to handle service request
	// found in phpFuctions.php
	initiate_service_request_handler($con, $_POST);
}

// used to request services for fse remote support
if ($_POST['tell'] == "fse_remote_support_request") {

	// call function to handle this specific request
	// found in phpFunctions.php
	initiate_fse_service_request($con, $_POST);
}

// use to process shipping requests
if ($_POST['tell'] == "submit_shipping_request") {

	// decode user_info and other arrays
	$user_info = json_decode($_POST['user_info'], true);
	$requested_parts = json_decode($_POST['requested_parts']);

	// Create query to find where these parts are coming from
	$requested_part_string = "('" . join("','", $requested_parts) . "')";
	$query = "SELECT shop_staged FROM fst_pq_detail WHERE id IN " . $requested_part_string . " GROUP BY shop_staged;";
	$result = mysqli_query($con, $query);

	// Loop through, create new shipment for each. Log, send out notification, and update database.
	while ($rows = mysqli_fetch_assoc($result)) {

		// create new shipment
		$query = "INSERT INTO `fst_pq_ship_request`
					(`quoteNumber`, `ship_from`, `created_by`, `created`, `email_cc`, 
					`additional_instructions`, `poc`, `poc_phone`, `poc_email`, 
					`ship_location`, `ship_address`, `ship_city`, `ship_state`, 
					`ship_zip`, `liftgate_opt`, `due_by_date`, `scheduled_delivery`) 
				VALUES ('" . mysql_escape_mimic($_POST['quoteNumber']) . "', '" . mysql_escape_mimic($rows['shop_staged']) . "', 
						'" . mysql_escape_mimic($user_info['id']) . "', NOW(), '" . mysql_escape_mimic($_POST['email_cc']) . "', 
						'" . mysql_escape_mimic($_POST['additional_instructions']) . "', '" . mysql_escape_mimic($_POST['poc']) . "', 
						'" . mysql_escape_mimic($_POST['poc_phone']) . "', '" . mysql_escape_mimic($_POST['poc_email']) . "', 
						'" . mysql_escape_mimic($_POST['ship_location']) . "', '" . mysql_escape_mimic($_POST['ship_address']) . "', 
						'" . mysql_escape_mimic($_POST['ship_city']) . "', '" . mysql_escape_mimic($_POST['ship_state']) . "', 
						'" . mysql_escape_mimic($_POST['ship_zip']) . "', '" . mysql_escape_mimic($_POST['liftgate_opt']) . "', 
						'" . mysql_escape_mimic($_POST['due_by_date']) . "', '" . mysql_escape_mimic($_POST['scheduled_delivery']) . "');";
		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

		// get ID inserted
		$id = mysqli_insert_id($con);

		// reset shop count
		$shop_count = 0;

		// loop through given arrays and set to requested
		foreach ($requested_parts as $part_id) {
			$query = "UPDATE fst_pq_detail SET ship_request_id = '" . $id . "', status = 'Ship Requested' WHERE id = '" . $part_id . "' AND shop_staged = '" . $rows['shop_staged'] . "';";
			custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
			$shop_count += intval(mysqli_affected_rows($con));
		}

		// Create new log for action
		$notification = new Notifications($con, "ship_request", "Initiated (ID: " . $id . ")", $_POST['quoteNumber'], $use);
		$notification->log_notification($user_info['id']);

		// Send email to warehouse
		// New instance of PHPMailer + initialize mail settings
		$mail = new PHPMailer();
		$mail = init_mail_settings($mail, $user_info);

		// cc groups needed
		if ($use == "test")
			$mail->addAddress($user_info['email']);								// testing = go to user testing
		else {
			$mail->addCC('allocations@piersonwireless.com'); 					// cc allocations
			if ($rows['shop_staged'] == "OMA")
				$mail->addAddress('OmahaLogistics@piersonwireless.com');		// go to OMA warehouse
			elseif ($rows['shop_staged'] == "CHA")
				$mail->addAddress('CharlotteLogistics@piersonwireless.com');	// go to CHA warehouse
		}

		// Content
		$mail->isHTML(true);
		$mail->Subject =  "[Shipping Request] " . $rows['shop_staged'];
		$mail->Body = "Hello,<br><br>There has been a ship request made from your shop for " . $shop_count . " items.<br><br>Thank you,";
		$mail->send();
	}
}

// used to upload data collection or site survey results
if ($_POST['tell'] == "upload_results") {

	// get user info & any files attached
	$user_info = json_decode($_POST['user_info'], true);
	$files = json_decode($_POST['file_reference']);
	$file_paths = [];
	$file_names = [];

	// upload subcontractor quotes to subcontractor folder
	$target_dir = getcwd(); // target director (if sending attachments)

	// loop through files and upload to drive folder
	foreach ($files as $file) {

		// save file locally
		$target_file = $target_dir . "\\uploads\\" . basename($_FILES[$file]["name"]);
		array_push($file_paths, $target_file);
		array_push($file_names, $_FILES[$file]["name"]);

		// if file is added successfully, push to google drive folder, then remove locally
		if (!move_uploaded_file($_FILES[$file]["tmp_name"], $target_file))
			echo "Error, there was an issue uploading your file to the server.";
	}

	// call function to add files to google-drive (all at once)
	// if we run into issues uploading, let user know
	if (!upload_ops_service_data_to_drive($file_paths, $file_names, $_POST['google_drive_link'], $_POST['service']))
		echo "Error, there was an issue uploading your file to google drive.";

	// loop back through paths and unlink
	foreach ($files as $file) {
		$target_file = $target_dir . "\\uploads\\" . basename($_FILES[$file]["name"]);
		unlink($target_file);
	}

	return;
}

//function defined to return array of objects (should match queries to get info in terminal_orders.php)
//param 1 = type (determines the type of info to be returned / query executed)
//param 2 = SQL connection
function get_db_info($type, $con)
{

	//decide which query based on type
	if ($type == "fst_grid")
		$query = "SELECT * from fst_grid WHERE quoteStatus like 'Award%' order by lastUpdate DESC;";
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
