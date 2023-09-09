<?php

// Load in dependencies (including starting a session)
session_start();
include('phpFunctions.php');
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

// used to upload data collection or site survey results
if ($_POST['tell'] == "receive_parts"){

	// get user info & any files attached
	$user_info = json_decode($_POST['user_info'], true);
	$file_reference = json_decode($_POST['file_reference'], true);
	$received_items = json_decode($_POST['received_items'], true);
	$file_paths = [];
	$file_names = [];

	// set full name of user
	$user_full = $user_info['firstName'] . ' ' . $user_info['lastName'];

	// set log (change if a potential issue is found)
	$log = "received in full.";

	$cell_style = "style = 'padding: 8px; border: 1px solid #ccc;'";

	// Initialie <table> to be sent in email to group
	$email_body = "<table style = 'border-collapse: collapse;'>";
	$email_body.= "<thead style = 'background-color: #114B95; color: white;' ><tr><th ".$cell_style.">Part #</th><th ".$cell_style.">Qty</th><th ".$cell_style.">Rec'd</th><th ".$cell_style.">Issue</th></tr></thead><tbody>";

	// Row color options (alternating)
	$odd_color = " style = 'background-color: #f2f2f2;'";
	$even_color = "";
	$color = $even_color;

	// loop through received items & move to received
	foreach ($received_items as $item){

		// Use type & entered qty detail entry
		$query = "UPDATE fst_pq_detail 
					SET status = 'Received', site_received = '" . $item['type'] . "', site_received_by = '" . mysql_escape_mimic($user_full) . "', 
						site_received_qty = '" . $item['qty'] . "', site_received_note = '" . mysql_escape_mimic($item['note']) . "',
						site_received_time = NOW() WHERE id = '" . $item['id'] . "';";
		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
		
		// based on type, flip the type of email body
		if ($item['type'] == "Partial" || $item['type'] == "Excess" || $item['note'] != "")
			$log = "received with errors.";

		// Switch colors
		if ($color == $even_color)
			$color = $odd_color;
		else
			$color = $even_color;

		// Add new entry into email table
		$email_body.= "<tr " . $color . ">";
		$email_body.= "<td ".$cell_style.">" . $item['part'] . "</td>";
		$email_body.= "<td ".$cell_style.">" . $item['expected_qty'] . "</td>";
		$email_body.= "<td ".$cell_style.">" . $item['qty'] . "</td>";
		$email_body.= "<td ".$cell_style.">" . $item['note'] . "</td>";
		$email_body.= "</tr>";
		
	}

	// Close out table
	$email_body.= "</tbody></table>";

	// check if all items from a given shipment have been received yet
	$query = "SELECT * FROM fst_pq_detail WHERE ship_request_id = '" . $_POST['sr_id'] . "' AND status <> 'Received';";
	$result = mysqli_query($con, $query);

	// if so, update status for shipment request
	if (mysqli_num_rows($result) == 0){
		$query = "UPDATE fst_pq_ship_request SET status = 'Received' WHERE id = '" . $_POST['sr_id'] . "';";
		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
	}

	// upload subcontractor quotes to subcontractor folder
	$target_dir = getcwd(); // target director (if sending attachments)

	// loop through files and upload to drive folder
	foreach ($file_reference as $file){

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
	$google_drive_id = upload_qr_receiving_images($file_paths, $file_names, $_POST['google_drive_link'], $_POST['sr_id']);
	if ($google_drive_id == false)
		echo "Error, there was an issue uploading your file to google drive.";
	else
		$email_body.= "<br>Image Link: https://drive.google.com/drive/folders/" . $google_drive_id;

	// loop back through paths and unlink
	foreach ($files as $file){
		$target_file = $target_dir . "\\uploads\\" . basename($_FILES[$file]["name"]);
		unlink($target_file);
	}

	// Query DB to get list of quotes related to request
	$query = "SELECT quoteNumber FROM fst_pq_overview WHERE id IN (SELECT project_id FROM fst_pq_detail WHERE ship_request_id = '13' GROUP BY project_id);";
	$result = mysqli_query($con, $query);
	while($rows = mysqli_fetch_assoc($result)){
		
		// Send out notification
		$notification = new Notifications($con, "parts_received", "Shipment " . $_POST['sr_id'] . " (" . $_POST['container'] . ") " . $log, $rows['quoteNumber'], $use);
		$notification->log_notification($user_info['id'], $email_body);

	}
	
	return;
}