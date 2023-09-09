<?php

// load dependencies
session_start();
include('phpFunctions.php');
include('PHPClasses/Part.php');
include('phpFunctions_drive.php');
include('constants.php');
include('PHPClasses/Notifications.php');
//include('PHPClasses/User.php');

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

//handles update MO from open to pending
if ($_POST['tell'] == "update_request_status"){
	
	// Check type to determine action
	if ($_POST['type'] == "material_order")
		$query = "UPDATE fst_allocations_mo SET status = 'In Progress', initiated = NOW() WHERE id = '" . $_POST['update'] . "';";
	else
		$query = "UPDATE fst_pq_ship_request SET status = 'In Progress' WHERE id = '" . $_POST['update'] . "';";
	
	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
	
	//go back to user
	return;
}

//used to update warehouse views
if ($_POST['tell'] == "check_orders"){

	//look at target_ids (any that have changed)
	$target_ids = json_decode($_POST['target_ids']);
			
	//if we find any that have changed, we need to go through and save them
	if (sizeof($target_ids) > 0){
					
		//decode allocations_mo info
		$allocations_mo = json_decode($_POST['allocations_mo'], true);

		//loop through target id's (inner loop of array of id's to find a match)
		for ($i = 0; $i < sizeof($target_ids); $i++){

			//get index of 'id' inside $allocations_mo ($target_ids[$i] == some $allocations_mo['id'])
			$index = array_search($target_ids[$i], array_column($allocations_mo, 'id'));

			//create query from match
			$query = "UPDATE fst_allocations_mo 
						SET carrier = '" . mysql_escape_mimic($allocations_mo[$index]['carrier']) . "', tracking = '" . mysql_escape_mimic($allocations_mo[$index]['tracking']) . "', 
							receipt = '" . mysql_escape_mimic($allocations_mo[$index]['receipt']) . "', ship_cost = '" . mysql_escape_mimic($allocations_mo[$index]['ship_cost']) . "', 
							picked_by = '" . mysql_escape_mimic($allocations_mo[$index]['picked_by']) . "', processed_by = '" . mysql_escape_mimic($allocations_mo[$index]['processed_by']) . "', 
							checked_by = '" . mysql_escape_mimic($allocations_mo[$index]['checked_by']) . "', staged_loc = '" . mysql_escape_mimic($allocations_mo[$index]['staged_loc']) . "', 
							staged_opt = '" . $allocations_mo[$index]['staged_opt'] . "', warehouse_notes = '" . mysql_escape_mimic($allocations_mo[$index]['warehouse_notes']) . "' 
						WHERE id = '" . $allocations_mo[$index]['id'] . "';";
			custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
		}
	}
	
	//look at pq_detail id's (any that have changed)
	$target_pq_ids = json_decode($_POST['target_pq_ids']);
	
	//if we find any that have changed, we need to go through and save them
	if (sizeof($target_pq_ids) > 0){
					
		//decode other arrays
		$pq_detail = json_decode($_POST['pq_detail'], true);

		//loop through target id's (inner loop of array of id's to find a match)
		for ($i = 0; $i < sizeof($target_pq_ids); $i++){

			//get index of 'id' inside $pq_detail ($target_pq_ids[$i] == some $pq_detail['id'])
			$index = array_search($target_pq_ids[$i], array_column($pq_detail, 'id'));

			//create query from matched index
			$query = "UPDATE fst_pq_detail 
						SET wh_container = '" . mysql_escape_mimic($pq_detail[$index]['wh_container']) . "', wh_notes = '" . mysql_escape_mimic($pq_detail[$index]['wh_notes']) . "' 
						WHERE id = '" . $target_pq_ids[$i] . "';";
			custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

		}
	}

	//look at pq_detail id's (any that have changed)
	$target_ship_ids = json_decode($_POST['target_ship_ids']);
	
	//if we find any that have changed, we need to go through and save them
	if (sizeof($target_ship_ids) > 0){
					
		//decode other arrays
		$ship_requests = json_decode($_POST['ship_requests'], true);

		//loop through target id's (inner loop of array of id's to find a match)
		for ($i = 0; $i < sizeof($target_ship_ids); $i++){

			//get index of 'id' inside $pq_detail ($target_pq_ids[$i] == some $pq_detail['id'])
			$index = array_search($target_ship_ids[$i], array_column($ship_requests, 'id'));

			//create query from match
			$query = "UPDATE fst_pq_ship_request 
						SET carrier = '" . mysql_escape_mimic($ship_requests[$index]['carrier']) . "', tracking = '" . mysql_escape_mimic($ship_requests[$index]['tracking']) . "', 
							receipt = '" . mysql_escape_mimic($ship_requests[$index]['receipt']) . "', ship_cost = '" . mysql_escape_mimic($ship_requests[$index]['ship_cost']) . "', 
							picked_by = '" . mysql_escape_mimic($ship_requests[$index]['picked_by']) . "', processed_by = '" . mysql_escape_mimic($ship_requests[$index]['processed_by']) . "', 
							checked_by = '" . mysql_escape_mimic($ship_requests[$index]['checked_by']) . "', staged_loc = '" . mysql_escape_mimic($ship_requests[$index]['staged_loc']) . "', 
							staged_opt = '" . $ship_requests[$index]['staged_opt'] . "', warehouse_notes = '" . mysql_escape_mimic($ship_requests[$index]['warehouse_notes']) . "' 
						WHERE id = '" . $ship_requests[$index]['id'] . "';";
			custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

		}
	}
	
	//initialize object that will hold all of this info
	$open_mo = [];
	
	//load in any material orders open for this shop
	$query = "select * from fst_allocations_mo where ship_from = '" . $_POST['shop'] . "' order by closed asc, date_created asc;";
	$result = mysqli_query($con, $query);

	//loop and add to arrays
	while($rows = mysqli_fetch_assoc($result)){
		
		//push to open MO array
		array_push($open_mo, $rows);
	
	}
	
	//init whh array
	$whh = [];
	
	//load in warehouse attachments
	$query = "select id, detail from fst_allocations_warehouse WHERE type = 'WHH';";
	$result = mysqli_query($con, $query);

	while($rows = mysqli_fetch_assoc($result)){
		
		//push to whh attachments
		array_push($whh, $rows);
		
	}
	
	//init pq_detail array & ship_requests array
	$pq_detail = get_db_info("fst_pq_detail", $con, $_POST['shop']);
	$ship_requests = get_db_info("fst_pq_ship_request", $con, $_POST['shop']);
	
	//compile object with both open and whh
	$return_array = [];
	array_push($return_array, $open_mo);
	array_push($return_array, $whh);
	array_push($return_array, $pq_detail);
	array_push($return_array, $ship_requests);
	
	//return to user
	echo json_encode($return_array);

	return;
}

// used to update min-max values from terminal_warehouse_inventory.php
if ($_POST['tell'] == "update_min_max"){
	
	//look at min_stock levels (check to see if any have changed)
	$update_min_max = json_decode($_POST['update_min_max'], true);

	//if we find any that have changed, go through and save them
	if (sizeof($update_min_max) > 0){

		//loop through target id's (inner loop of array of id's to find a match)
		for ($i = 0; $i < sizeof($update_min_max); $i++){

			//if min > max, set max = min
			if ((intval($update_min_max[$i]['min_stock']) > intval($update_min_max[$i]['max_stock'])) || $update_min_max[$i]['max_stock'] == "")
				$update_min_max[$i]['max_stock'] = $update_min_max[$i]['min_stock'];

			//create query from matched index
			$query = "UPDATE inv_locations 
						SET min_stock = '" . mysql_escape_mimic($update_min_max[$i]['min_stock']) . "', max_stock = '" . mysql_escape_mimic($update_min_max[$i]['max_stock']) . "',
						 	min_primary = '" . mysql_escape_mimic($update_min_max[$i]['min_primary']) . "', max_primary = '" . mysql_escape_mimic($update_min_max[$i]['max_primary']) . "'
						WHERE partNumber = '" . mysql_escape_mimic($update_min_max[$i]['partNumber']) . "' AND shop = '" . mysql_escape_mimic($update_min_max[$i]['shop']) . "';";
			custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

		}
	}
	
	return; 

}

//handles acknowleding a request (moved the request to in progress)
if ($_POST['tell'] == 'adjust_on_hand'){
	
	//new instance of part (update part shop)
	$part = new Part($_POST['part'], $con);
	$part->info['shop'] = $_POST['shop'];

	//check if we ran into an error (if manual_part is true, we did not find a match in our catalog)
	if ($part->manual_part){
		echo "Error: We did not find a match in our catalog for this part. Please check with Dustin & fst@piersonwireless.com to resolve this error.";
		return;
	}

	//if this is a -3 shop, default cost to 0
	if (str_contains($_POST['shop'], "-3"))
		$part->info['cost'] = 0;
	
	//update physical locations (only if coming from warehouse)
	if(!isset($_POST['type']))
		$part->update_physical_locations(json_decode($_POST['phys_locations'], true));

	//calculate inventory adjusted (|old - new| * cost)
	$adjusted = (intval($_POST['new_on_hand']) - intval($part->info[$_POST['shop']])) * floatval($part->info['cost']);
	$threshold = 200;	//manually set right now, to be pulled from admin table at later time

	//read in user_info
	$user_info = json_decode($_POST['user_info'], true);

	//check to see if this needs to be confirmed by logistics managers (adjustment over some threshold & this is not an admin)
	if (abs($adjusted) >= $threshold && !isset($_POST['type']) && $user_info['allocations_admin'] != "checked"){

		//log requested update
		//type "IA" = "Inventory Adjustment"
		$part->log_part_update($_SESSION['employeeID'], 'Old (' . $part->info[$_POST['shop']] . ') - New (' . $_POST['new_on_hand'] . ')', 'IA', 'Pending');

		//get log id
		$log_id = $con->insert_id;

		//send email for confirmation to logistics admin
		//init instance of PHPMailer
		$mail = new PHPMailer();

		//init $mail settings
		$mail = init_mail_settings($mail);

		//Recipients
		$mail->setFrom($_SESSION['email'], $_SESSION['firstName'] . " " . $_SESSION['lastName']); //set from (name is optional)
		$mail->AddReplyTo($_SESSION['email'], $_SESSION['firstName'] . " " . $_SESSION['lastName']);

		//depending environment (local / production) send to email group
		//also adjust sublink
		if ($use == "test"){
			$mail->addAddress($_SESSION['email']);
			$sublink = "localhost/FST/";
		}
		//inventory@pw
		else{
			$mail->addAddress("Inventory@piersonwireless.com");
			//$mail->addAddress("dustin@piersonwireless.com");
			//$mail->addAddress("chad@piersonwireless.com");
			$sublink = "https://pw-fst.northcentralus.cloudapp.azure.com/FST/";
		}

		// create link for user to click to approve adjustment
		// replace any potentially bad characters in URL 
		$part_fix = str_replace("+","%2B", $part->info['partNumber']);

		$approve_link = $sublink . 'terminal_warehouse_confirmation.php?type=Approved&part=' . $part_fix . "&shop=" . $_POST['shop'] . "&new=" . $_POST['new_on_hand'] . "&old=" . $part->info[$_POST['shop']] . "&log_id=" . $log_id;
		$approve_html = "<a href = '" . $approve_link . "'>Approve Adjustment</a>";

		$reject_link = $sublink . 'terminal_warehouse_confirmation.php?type=Rejected&part=' . $part_fix . "&shop=" . $_POST['shop'] . "&new=" . $_POST['new_on_hand'] . "&old=" . $part->info[$_POST['shop']] . "&log_id=" . $log_id;
		$reject_html = "<a href = '" . $reject_link . "'>Reject Adjustment</a>"; 

		//create body of email
		$body = "Hello,<br><br>";
		$body.= "Please click one of the following links to confirm adjustment:<br><br>";
		$body.= $approve_html . "<br>";
		$body.= $reject_html . "<br><br>";
		$body.= "An adjustment for part #" . $part->info['partNumber'] . " has been requested that is over the threshold ($" . strval($threshold) . ").<br><br>";
		$body.= "<b>Adjustment Summary</b><br>";
		$body.= "Part #: " . $part->info['partNumber'] . "<br>";
		$body.= "Shop: " . $part->info['shop'] . "<br>";
		$body.= "Current Stock: " . $part->info[$_POST['shop']] . "<br>";
		$body.= "Adjusted Stock: " . $_POST['new_on_hand'] . "<br>";
		$body.= "Affect on Cost: $" . strval($adjusted) . "<br><br>";
		$body.= $part->get_update_summary("added");
		$body.= $part->get_update_summary("removed");
		$body.= "Thank you,";
				
		//Content
		$mail->isHTML(true);
		$mail->Subject = "[Inventory Adjustment] Part #" . $part->info['partNumber'];
		$mail->Body = $body;
		$mail->send();

		//close smtp connection
		$mail->smtpClose();

		//get physical location and return
		$physical_locations = get_db_info('invreport_physical_locations', $con);
		echo json_encode($physical_locations);

		return;
	}
	//log approved update (this is being done by admin OR not above threshold
	elseif(!isset($_POST['type'])){

		//call function to update on hand
		$part->update_on_hand($_POST['new_on_hand']);
		
		//log requested update
		//type "IA" = "Inventory Adjustment"
		$part->log_part_update($_SESSION['employeeID'], 'Old (' . $part->info[$_POST['shop']] . ') - New (' . $_POST['new_on_hand'] . ') (' . $user_info['firstName'] . ' ' . $user_info['lastName'] . ')', 'IA', 'Approved');
		
		//send email so dustin is aware of update
		//init instance of PHPMailer
		$mail = new PHPMailer();

		//init $mail settings
		$mail = init_mail_settings($mail);

		//Recipients
		$mail->setFrom($_SESSION['email'], $_SESSION['firstName'] . " " . $_SESSION['lastName']); //set from (name is optional)
		$mail->AddReplyTo($_SESSION['email'], $_SESSION['firstName'] . " " . $_SESSION['lastName']);

		//depending environment (local / production) send to email group
		//also adjust sublink
		if ($use == "test")
			$mail->addAddress($_SESSION['email']);
		//inventory@pw
		else
			$mail->addAddress("Inventory@piersonwireless.com");

		//create body of email
		$body = "Hello,<br><br>";
		$body.= "An adjustment for part #" . $part->info['partNumber'] . " has been made that is not over the threshold ($" . strval($threshold) . ") OR has been made my an administrator.<br><br>";
		$body.= "<b>Adjustment Summary</b><br>";
		$body.= "Part #: " . $part->info['partNumber'] . "<br>";
		$body.= "Shop: " . $part->info['shop'] . "<br>";
		$body.= "Current Stock: " . $part->info[$_POST['shop']] . "<br>";
		$body.= "Adjusted Stock: " . $_POST['new_on_hand'] . "<br>";
		$body.= "Affect on Cost: $" . strval($adjusted) . "<br><br>";
		$body.= $part->get_update_summary("added");
		$body.= $part->get_update_summary("removed");
		$body.= "Thank you,";		

		//Content
		$mail->isHTML(true);
		$mail->Subject = "[Inventory Adjustment] Part #" . $part->info['partNumber'];
		$mail->Body = $body;
		$mail->send();

		//close smtp connection
		$mail->smtpClose();

		//get physical location and return
		$physical_locations = get_db_info('invreport_physical_locations', $con);
		echo json_encode($physical_locations);

		return;
	}

	//if old doesn't match current, deny update
	if ($part->info[$_POST['shop']] != $_POST['on_hand']){
		echo "Error: Inventory has been changed since the request was made, this request is no longer valid.";
		return;
	}

	//if type is set, make sure to log either approval or rejection
	if (isset($_POST['type'])){

		//check log to see if it has already been processed
		$query = "SELECT status FROM invreport_logs WHERE id = '" . $_POST['log_id'] . "';";
		$result = mysqli_query($con, $query);
		$log = mysqli_fetch_array($result);

		if ($log['status'] != "Pending"){
			echo "Error: This request has already been " . $log['status'] . ", the request is no longer valid.";
			return;
		}

		//call function to update on hand (only if approved)
		if ($_POST['type'] == "Approved")
			$part->update_on_hand($_POST['new_on_hand']);

		//log approval/rejection
		$part->update_log_status($_SESSION['employeeID'], $_POST['log_id'], $_POST['type']);
	}

	//go back to user
	return;
}

//used to process greensheet requests
if ($_POST['tell'] == "greensheet"){
	
	//grab parts & quantity array from js
	$request = json_decode($_POST['request'], true);
	$format_date = date('m-d-Y', strtotime($_POST['greensheet_date']));
	
	//split greensheet name into user and email
	$user = substr($_POST['greensheet_name'], 0, strpos($_POST['greensheet_name'], "|"));	
	$email = substr($_POST['greensheet_name'], strpos($_POST['greensheet_name'], "|") + 1);

	//get quote info from the quote #
	$query = "SELECT * FROM fst_grid WHERE quoteNumber = '" . $_POST['greensheet_quote'] . "';";
	$result = mysqli_query($con, $query);
	$grid = mysqli_fetch_array($result);		
	
	//save greensheet overview info
	//$query = "INSERT INTO fst_greensheet_overview (id, name, date, pn, shop) VALUES (null, '" . $user . "', '" . $format_date . "', '" . $_POST['greensheet_quote'] . "', '" . $_POST['greensheet_shop'] . "');";
	$query = "INSERT INTO fst_pq_overview (type, project_id, quoteNumber, project_name, requested_by, add_instructions, urgency, requested, staging_loc, greensheet) 
									VALUES ('GS', '" . mysql_escape_mimic($grid['vpProjectNumber']) . "', '" . mysql_escape_mimic($grid['quoteNumber']) . "', 
											'" . mysql_escape_mimic($grid['location_name']) . "', '" . mysql_escape_mimic($_POST['greensheet_name']) . "', 
											'This is a greensheet.', '[Greensheet]', NOW(), 'Greensheet', 1);";
	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);	
	
	//grab previous id
	$id = mysqli_insert_id($con);
	
	//padding of cells (right and left)
	$padding = "8px"; 
	
	$color1 = "#c2e2ff"; //color used for every even table row
	$color2 = "#d9edff"; //color used for odd rows
	$color = $color2; //set initial color
	
	//initialize body_table with headers
	$table = "<table style = 'border-collapse: collapse; margin-left: 1em;'>
				<tr>
					<th style = 'padding: " . $padding . "; width: 15em;'>Part #</th>
					<th style = 'padding: " . $padding . "'>Quantity</th>
				</tr>";
	
	//cycle through parts and add to table/save to database
	for ($i = 0; $i < sizeof($request); $i++){
		
		//add to table
		//flip colors to look nicer to the user
		if ($color == $color1)
			$color = $color2;
		else
			$color = $color1;

		//write out each cell in the row
		$table .= "<tr style = 'background-color:" . $color . "'>";

		$table .= "<td style = 'padding-right: " . $padding . "; border: 1px solid black;'>" . $request[$i]['part'] . "</td>"; //pn
		$table .= "<td style = 'padding-right: " . $padding . "; padding-left: " . $padding . ";border: 1px solid black; text-align: center'>" . $request[$i]['quantity'] . "</td>"; //quantity
		
		$table .= "</tr>";
		
		//write query with material info
		$query = "INSERT INTO fst_pq_detail (project_id, part_id, quantity, q_allocated, subs) 
									 VALUES ('". $id . "', '" . mysql_escape_mimic($request[$i]['part']) . "', 
									 		'" . mysql_escape_mimic($request[$i]['quantity']) . "', '" . mysql_escape_mimic($request[$i]['quantity']) . "',
											'Yes');";
		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

		//create new instance of part
		$part = new Part($request[$i]['part'], $con);

		//insert row to fst_boms (main table for parts on a quote) - type = G (greensheet)
		$query = "INSERT INTO fst_boms (type, quoteNumber, partNumber, partCategory, description, manufacturer, quantity, cost, allocated) 
								VALUES ('G', '" . $_POST['greensheet_quote'] . "', '" . mysql_escape_mimic($request[$i]['part']) . "', 
										'" . mysql_escape_mimic($part->info['partCategory']) . "', '" . mysql_escape_mimic($part->info['partDescription']) . "', 
										'" . mysql_escape_mimic($part->info['manufacturer']) . "', '" . $request[$i]['quantity'] . "', 
										'" . $part->info['cost'] . "', '" . $request[$i]['quantity'] . "');";
		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
		
	}
	
	//close out table
	$table .= "</table>";
	
	//create the body of the email 
	$body = "The following parts were removed from your warehouse on <span style = 'color:red'>" . $format_date . "</span> by <span style = 'color:red'>" . $user . "</span> for Quote #: <span style = 'color:red'>" . $_POST['greensheet_quote']. "</span>.  A Material Order will be issued to you shortly, by Allocations.  Please process the material order upon your review and satisfaction.";
	
	//add table to body 
	$body .= "<br><br>" . $table;

	//Step 1: Send email to cc'd individuals
	//Instantiation and passing `true` enables exceptions
	$mail = new PHPMailer();

	//init $mail settings
	$mail = init_mail_settings($mail);
	
	//Recipients
	$mail->setFrom($_SESSION['email'], $_SESSION['firstName'] . " " . $_SESSION['lastName']); //set from (name is optional)
	$mail->AddReplyTo($_SESSION['email'], $_SESSION['firstName'] . " " . $_SESSION['lastName']);
	
	if ($use == "test"){
		$mail->addAddress($_SESSION['email']);
	}
	else{
		$mail->addAddress("allocations@piersonwireless.com"); //send to allocations
	}
	
	$mail->addCC($_SESSION['email']); 		//cc yourself
	$mail->addCC($email); 					//cc person who requested materials

	//Content
	$mail->isHTML(true);
	$mail->Subject =  "Greensheet Submitted (" . $_POST['greensheet_shop'] . ") - Quote #: " . $_POST['greensheet_quote'];
	$mail->Body = "Hello, <br><br>" . $body . "<br><br>Thank you,";
	$mail->send();

	//close smtp connection
	$mail->smtpClose();
	
	return;
		
}

//handles assigning new reel IDs
if ($_POST['tell'] == 'assign_reels'){
	
	//get new instance of part & set shop
	$part = new Part($_POST['part'], $con);
	$part->info['shop'] = $_POST['shop'];

	//validate that we can assign reel for this part
	if (!$part->validate_reel_category()){
		echo "Error: The category that you are attempting to assign a reel for is not supported.";
		return;
	}

	//if we pass validation, assign new reel and echo to user
	echo $part->assign_reel_id($_POST['type']);

	//go back to user
	return;
}

//handles updating reel information
if ($_POST['tell'] == 'update_reels'){
	
	//get new instance of part & set shop
	$part = new Part($_POST['part'], $con);
	$part->info['shop'] = $_POST['shop'];

	//convert reel detail to php array
	$reel_detail = json_decode($_POST['reel_detail'], true);

	//pass reel_detail to part method which updates reel info
	$part->update_reel_info($reel_detail);

	//go back to user
	return;
}

//handles remove reel from database
if ($_POST['tell'] == 'remove_reel'){
	
	//create & run query to remove reel
	$query = "DELETE FROM inv_reel_assignments WHERE id = '" . $_POST['reel_id'] . "';";
    custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

	//go back to user
	return;
}

//handles acknowleding a request (moved the request to in progress)
if ($_POST['tell'] == 'process_receiving'){
	
	//get part info
	$received_parts = json_decode($_POST['received_parts'], true);

	//get user full name
	$user_info = json_decode($_POST['user_info'], true);
	$user_full_name = $user_info['firstName'] . " " . $user_info['lastName'];

	//loop through parts received & update fst_pq_detail
	foreach ($received_parts as $part){

		//create new instance of part
		$partC = new Part($part['part'], $con);

		//create status of new part (flipped if for a shop)
		$status = "Staged";

		//if this is a shop, breakout $shop and change $part['staging_area'] to just physical location
		if ($part['type'] == "shop"){
			$shop = substr($part['staging_area'], strpos($part['staging_area'], "|") + 1);
			$part['staging_area'] = substr($part['staging_area'], 0, strpos($part['staging_area'], "|"));
			$status = "Received";
		}

		//check if this is complete received material
		if (intval($part['expected_qty']) == intval($part['qty'])){

			$query = "UPDATE fst_pq_detail 
						SET received_qty = received_qty + " . $part['qty'] . ", received_date = NOW(), 
							received_by = '" . mysql_escape_mimic($user_full_name) . "', received_staged_loc = '" . mysql_escape_mimic($part['staging_area']) . "',
							wh_container = '" . mysql_escape_mimic($part['container']) . "'
						WHERE id = '" . $part['id'] . "';";
			custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
		}
		else{

			//update received amount, set non-received type = to remaining type (may be job or shop)
			$query = "UPDATE fst_pq_detail 
						SET received_qty = received_qty + " . $part['qty'] . ", received_date = NOW(), 
							received_by = '" . mysql_escape_mimic($user_full_name) . "', received_staged_loc = '" . mysql_escape_mimic($part['staging_area']) . "',
							not_received_type = '" . mysql_escape_mimic($part['type']) . "', wh_container = '" . mysql_escape_mimic($part['container']) . "'
						WHERE id = '" . $part['id'] . "';";
			custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
		}

		//if type is shop, make sure we add to inventory totals
		if ($part['type'] == "shop"){

			//add qty to shop total
			$partC->info['shop'] = $shop; //use $shop from $part['staging_area']
			$new_qty = intval($part['qty']) + $partC->info[$partC->info['shop']];
			$partC->update_on_hand($new_qty);

			//update physical location quantity
			$query = "UPDATE invreport_physical_locations 
						SET quantity = quantity + " . $part['qty'] . " 
						WHERE shop = '" . $shop . "' AND partNumber = '" . mysql_escape_mimic($part['part']) . "' AND location = '" . mysql_escape_mimic($part['staging_area']) . "';";
			custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

			//email team that VP adjustment is required.
			//init instance of PHPMailer
			$mail = new PHPMailer();

			//init $mail settings
			$mail = init_mail_settings($mail);

			//Recipients
			$mail->setFrom($user_info['email'], $user_info['firstName'] . " " . $user_info['lastName']); //set from (name is optional)
			$mail->AddReplyTo($user_info['email'], $user_info['firstName'] . " " . $user_info['lastName']);

			//depending environment (local / production) send to email group
			//also adjust sublink
			if ($use == "test")
				$mail->addAddress($user_info['email']);
			else
				$mail->addAddress("Inventory@piersonwireless.com");

			//Content
			$mail->isHTML(true);
			$mail->Subject = "[Material Received] Shop (" . $partC->info['shop'] . ") Part #" . $partC->info['partNumber'];
			$mail->Body = "Hello,<br><br>";
			$mail->Body .= "This is a notification to process the received material in Viewpoint.<br><br>";
			$mail->Body .= "Part #: <b>" . $partC->info['partNumber'] . "</b><br>";
			$mail->Body .= "Shop: <b>" . $partC->info['shop'] . "</b><br>";
			$mail->Body .= "Quantity Received: <b>" . $part['qty'] . "</b><br>";
			$mail->Body .= "Old Quantity: <b>" . $partC->info[$partC->info['shop']] . "</b><br>";
			$mail->Body .= "New Quantity: <b>" . $new_qty . "</b><br>";
			$mail->Body .= "Thank you,";
			$mail->send();

			//close smtp connection
			$mail->smtpClose();

		}
		else{

			// Check if notification requested on this part
			$query = "SELECT * FROM fst_users_notifications_parts 
			WHERE partNumber = '" . mysql_escape_mimic($part['part']) . "' AND
				quoteNumber = (SELECT quoteNumber FROM fst_pq_overview 
								WHERE id = (SELECT project_id FROM fst_pq_detail WHERE id = '" . $part['id'] . "')
								)";
			$result = mysqli_query($con, $query);

			if (mysqli_num_rows($result) > 0){

				// Get grid for quote
				$grid_query = "SELECT * FROM fst_grid WHERE quoteNumber = (SELECT quoteNumber FROM fst_pq_overview 
																		WHERE id = (SELECT project_id FROM fst_pq_detail WHERE id = '" . $part['id'] . "')
																	);";
				$grid_result = mysqli_query($con, $query);
				$grid = mysqli_fetch_array($grid_result);

				// Get user instance
				$user_inst = new User($user_info['id'], $con);

				// Loop through results from 'SELECT * FROM fst_users_notitifications....' and send notification
				while($rows = mysqli_fetch_assoc($result)){
					$notification = new Notifications($con, "parts_staged", "Part #" . $part['part'] . " staged (QTY: " . $part['qty'] . ")", $grid['quoteNumber'], $use);
					$notification->grid = $grid;
					$notification->user = $user_inst;
					$user = new User($rows['id'], $con);
					$notification->send_email_notification($user, false, "This part has been staged in " . $_POST['shop'] . " and is ready to ship.");
				}
			}
		}

		// Log part received
		$partC->log_part_update($user_info['id'], "Material Received (QTY: " . $part['qty'] . ") (Type: " . $part['type'] . ")", "REC");
	}

	//depending on type, check status of all parts (if all parts received, move order to received)
	if ($_POST['type'] == "MO"){

		// update all parts that have been fully received
		$query = "UPDATE fst_pq_detail SET status = '" . $status . "', shop_staged = '" . $_POST['shop'] . "' WHERE received_qty > 0 AND mo_id = '" . $_POST['po_mo'] . "';";
		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

		// check if all parts have been received for request & move request to staged if so
		$query = "SELECT * FROM fst_pq_detail WHERE mo_id = '" . $_POST['po_mo'] . "' AND status NOT IN ('Staged', 'Received', 'In-Transit');";
		$result = mysqli_query($con, $query);

		// if so, set status of request to received
		if (mysqli_num_rows($result) == 0){
			$query = "UPDATE fst_allocations_mo SET status = 'Received' WHERE mo_id = '" . $_POST['po_mo'] . "';";
			custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
		}
	}
	elseif ($_POST['type'] == "PO"){

		//update all parts that have been fully received
		$query = "UPDATE fst_pq_detail SET status = '" . $status . "', shop_staged = '" . $_POST['shop'] . "' WHERE received_qty > 0 AND shipment_id = '" . $_POST['po_mo'] . "';";
		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

		//check to make sure all parts have been received
		$query = "SELECT * FROM fst_pq_detail WHERE status IN ('Received', 'Staged') AND q_allocated > received_qty AND shipment_id = '" . $_POST['po_mo'] . "';";
		$result = mysqli_query($con, $query);

		//if so, set status of request to received
		if (mysqli_num_rows($result) == 0){
			$query = "UPDATE fst_pq_orders_shipments SET status = 'Received' WHERE shipment_id = '" . $_POST['po_mo'] . "';";
			custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
		}

		//check to see if all orders for a given purchase order have been received
		//get PO # related to current shipment, search the entire table for any not received
		$query = "SELECT * FROM fst_pq_orders_shipments WHERE po_number = (select po_number from fst_pq_orders_shipments WHERE shipment_id = '" . $_POST['po_mo'] . "') AND status NOT IN ('Received', 'Staged');";
		$result = mysqli_query($con, $query);

		//if so, set status of PO to closed
		if (mysqli_num_rows($result) == 0){
			$query = "UPDATE fst_pq_orders SET status = 'Received' WHERE po_number = (select po_number from fst_pq_orders_shipments WHERE shipment_id = '" . $_POST['po_mo'] . "');";
			custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
		}
	}

	// run queries to update receiving info
	$pq_shipping = get_db_info("fst_pq_shipments", $con);
	echo json_encode($pq_shipping);

	// go back to user
	return;
}

//used to upload copy of pick ticket before sending
if ($_POST['tell'] == "temp_pdf"){
		
	// pull the raw binary data from the POST array
	$data = substr($_POST['data'], strpos($_POST['data'], ",") + 1);

	// decode it
	$decodedData = base64_decode($data);

	// print out the raw data( debugging ) 
	//echo ($decodedData);
	$filename = "uploads/" . $_POST['mo_id'] . "-pick-ticket.pdf";

	// write the data out to the file
	$fp = fopen($filename, 'wb');
	fwrite($fp, $decodedData);

	return;
	
}

//used to process MO request back from the warehouse team
if ($_POST['tell'] == "close_and_submit"){
		
	//get user info
	$user_info = json_decode($_POST['user_info'], true);

	//step 1 - save attachments to server and save info to database 

	//current directory
	$target_dir = getcwd();
	
	//decode attachments array from client side
	$poc_attachments = json_decode($_POST['poc_attachments']);

	//save any files associated with the project
	for ($j = 0; $j < sizeof($poc_attachments); $j++){

		//build target file path
		$target_file = $target_dir . "\\warehouse_attachments\\" . basename($_FILES["file" . $poc_attachments[$j]]["name"]);

		//grab file type from target path
		$fileType = strtolower(pathinfo($target_file,PATHINFO_EXTENSION));

		//pass file name to new_name function, returns unique file name
		$_FILES["file" . $poc_attachments[$j]]["name"] = new_name($_FILES["file" . $poc_attachments[$j]]["name"], $fileType);

		//recreate file path with new name
		$target_file = $target_dir . "\\warehouse_attachments\\" . basename($_FILES["file" . $poc_attachments[$j]]["name"]);

		//add attachment to MO folder on server
		if (move_uploaded_file($_FILES["file" . $poc_attachments[$j]]["tmp_name"], $target_file)) {
			//if we are successful, save attachment name to be passed to email and save detail in database
			
			//save file name
			$poc_attachments[$j] = $_FILES["file" . $poc_attachments[$j]]["name"];
			
			//save to database
			$query = "INSERT INTO fst_allocations_warehouse (id, type, detail) VALUES('" . $_POST['unique_id'] . "', 'poc', '" . $poc_attachments[$j] . "');";
			custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
			
		} 
		else {
			//return error, reset attachment name
			echo "Sorry, there was an error uploading your file.";
			$poc_attachments[$j] = "";
				
		}
	}
	
	//save bol if applicable
	if (isset($_FILES["bol"])){
		//build target file path
		$target_file = $target_dir . "\\warehouse_attachments\\" . basename($_FILES["bol"]["name"]);

		//grab file type from target path
		$fileType = strtolower(pathinfo($target_file,PATHINFO_EXTENSION));

		//pass file name to new_name function, returns unique file name
		$_FILES["bol"]["name"] = new_name($_FILES["bol"]["name"], $fileType);

		//recreate file path with new name
		$target_file = $target_dir . "\\warehouse_attachments\\" . basename($_FILES["bol"]["name"]);

		//add attachment to MO folder on server
		if (move_uploaded_file($_FILES["bol"]["tmp_name"], $target_file)) {
			//if we are successful, save attachment name to be saved in database
			$bol_name = $_FILES["bol"]["name"];
			
			//save to database
			$query = "INSERT INTO fst_allocations_warehouse (id, type, detail) VALUES('" . $_POST['unique_id'] . "', 'bol', '" . $bol_name . "');";
			custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
			
		} 
		else {
			//return error, reset attachment name
			echo "Sorry, there was an error uploading your file.";
			$bol_name = "";

		}
	}
	
	//save contents of container (if applicable)
	$cop_body = ""; //used to add cop to body of email
	//pass to php array
	$cop = json_decode($_POST['cop']);

	//cycle through cop
	for ($i = 0; $i < sizeof($cop); $i++){
		
		//if not blank, save to database
		if ($cop[$i] != ""){
			//build query to save to db
			$query = "INSERT INTO fst_allocations_warehouse (id, type, detail) VALUES('" . $_POST['unique_id'] . "', 'cop', 'Container " . $i+1 . " - " . mysql_escape_mimic($cop[$i]) . "');";
			custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

			//add to body of email
			$cop_body.= $i+1 . ") "  . $cop[$i] . "<br>";
		}
	}

	//Step 1: Send email to cc'd individuals
	//Instantiation and passing `true` enables exceptions
	$mail = new PHPMailer();

	//init $mail settings
	$mail = init_mail_settings($mail);
	
	//Recipients
	$mail->setFrom($user_info['email'], $user_info['firstName'] . " " . $user_info['lastName']); //set from (name is optional)
	$mail->AddReplyTo($user_info['email'], $user_info['firstName'] . " " . $user_info['lastName']);
	//$mail->addAddress("amb035@morningside.edu"); 			//send to material creation group

	if ($use == "test")
		$mail->addAddress($user_info['email']);
	else
		$mail->addAddress("allocations@piersonwireless.com"); 	//send to material creation group

	//assign email cc
	$emailCC = $_POST['cc'];

	//add cc's
	$check = 0;
	$check = strpos($emailCC, ";");

	if($check > 0){
		$emails = explode(";", $emailCC);
		for ($i = 0; $i < sizeof($emails); $i++){
			$mail->addCC(trim($emails[$i]));
		}

	}
	else{
		$email = trim($emailCC);
		$mail->addCC($emailCC);
	}

	$mail->addCC($user_info['email']); //cc yourself

	//save standard info to database
	//ship for Staged to be checked (if so, do not send out email, just save data.)
	if ($_POST['input_staged'] == 'true'){

		//build query & execute
		$query = "UPDATE fst_allocations_mo 
					SET carrier = '" . mysql_escape_mimic($_POST['input_carrier']) . "', 
						tracking = '" . mysql_escape_mimic($_POST['input_tracking']) . "', 
						receipt = '" . mysql_escape_mimic($_POST['input_receipt']) . "', 
						ship_cost = '" . mysql_escape_mimic($_POST['input_ship_cost']) . "', 
						status = 'Staged', 
						picked_by = '" . $_POST['input_picked_by'] . "', 
						processed_by = '" . $_POST['input_processed_by'] . "', 
						checked_by = '" . $_POST['input_checked_by'] . "', 
						staged_loc = '" . mysql_escape_mimic($_POST['input_staged_loc']) . "', 
						closed = NOW(), 
						shipping_later = NOW() 
					WHERE mo_id = '" . $_POST['mo_id'] . "' AND ship_from = '" . $_POST['shop'] . "'";
		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
		
		//check for project name
		$query = "SELECT location_name FROM fst_grid WHERE vpProjectNumber = '" . $_POST['project_id'] . "' ORDER BY lastUpdate desc LIMIT 1;";
		$result = mysqli_query($con, $query);

		if ($result->num_rows > 0){
			$project_name = mysqli_fetch_array($result);
			$subject_line = "PW Warehouse (" . $_POST['shop'] . "): Project #" . $_POST['project_id'] . " (" . $project_name['location_name'] . ") | Material Order #" . $_POST['mo_id'] . " Staged";
		}
		else{
			$subject_line = "PW Warehouse (" . $_POST['shop'] . "): Project #" . $_POST['project_id'] . " | Material Order #" . $_POST['mo_id'] . " Staged";
		}

		$style1 = "style = color:red";

		//create the first line based on if this is a local pick-up
		$body = "Parts for Material Order <span " . $style1 . ">" . $_POST['mo_id'] . "</span> have been staged in (" . $_POST['shop'] . ") are set to ship at a later date. These will ship in full once all materials arrive or at a date specified by the project manager.<br>";
		
		//set the rest of the body 
		//$body .= "Arrival Date: <span " . $style1 . ">" . $_POST['input_receipt'] . "</span><br>";
		//$body .= "Carrier: <span " . $style1 . ">" . $_POST['input_carrier'] . "</span><br>";
		//$body .= "Tracking Number: <span " . $style1 . ">" . $_POST['input_tracking'] . "</span><br>";
		//$body .= "Shipping Cost: <span " . $style1 . ">" . convert_money('%.2n', $_POST['input_cost']) . "</span><br>";
		
	}
	else{
	
		//create query based on current array
		$query = "UPDATE fst_allocations_mo 
					SET carrier = '" . mysql_escape_mimic($_POST['input_carrier']) . "', 
						tracking = '" . mysql_escape_mimic($_POST['input_tracking']) . "', 
						receipt = '" . mysql_escape_mimic($_POST['input_receipt']) . "', 
						ship_cost = '" . mysql_escape_mimic($_POST['input_ship_cost']) . "', 
						status = 'Closed', 
						picked_by = '" . $_POST['input_picked_by'] . "',
						processed_by = '" . $_POST['input_processed_by'] . "', 
						checked_by = '" . $_POST['input_checked_by'] . "', 
						staged_loc = '" . mysql_escape_mimic($_POST['input_staged_loc']) . "', 
						closed = NOW() 
					WHERE mo_id = '" . $_POST['mo_id'] . "' AND ship_from = '" . $_POST['shop'] . "'";
		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

		//check for project name
		$query = "SELECT location_name FROM fst_grid WHERE vpProjectNumber = '" . $_POST['project_id'] . "' ORDER BY lastUpdate desc LIMIT 1;";
		$result = mysqli_query($con, $query);

		if ($result->num_rows > 0){
			$project_name = mysqli_fetch_array($result);
			$subject_line = "PW Warehouse (" . $_POST['shop'] . "): Project #" . $_POST['project_id'] . " (" . $project_name['location_name'] . ") | Material Order #" . $_POST['mo_id'] . " Has Shipped";
		}
		else{
			$subject_line = "PW Warehouse (" . $_POST['shop'] . "): Project #" . $_POST['project_id'] . " | Material Order #" . $_POST['mo_id'] . " Has Shipped";
		}

		$style1 = "style = color:red";

		//create the first line based on if this is a local pick-up
		if ($_POST['input_carrier'] == "Local Pick-Up")
			$body = "Material Order <span " . $style1 . ">" . $_POST['mo_id'] . "</span> has been staged in (" . $_POST['shop'] . ").<br><br>";
		else
			$body = "Material Order <span " . $style1 . ">" . $_POST['mo_id'] . "</span> has shipped.<br><br>";
		
		//create the rest of the body
		$body .= "Arrival Date: <span " . $style1 . ">" . $_POST['input_receipt'] . "</span><br>";
		$body .= "Carrier: <span " . $style1 . ">" . $_POST['input_carrier'] . "</span><br>";
		$body .= "Tracking Number: <span " . $style1 . ">" . $_POST['input_tracking'] . "</span><br>";
		$body .= "Shipping Cost: <span " . $style1 . ">" . convert_money('%.2n', $_POST['input_ship_cost']) . "</span><br>";

		//if user entered staged in, add to body, if not, add "Not Applicable"
		if ($_POST['input_staged_loc'] == ""){
			$body .= "Staged Location: <span " . $style1 . ">Not Applicable</span><br>";
		}
		else{
			$body .= "Staged Location: <span " . $style1 . ">" . $_POST['input_staged_loc'] . "</span><br>";
		}

		//if we have containers, add them to the email
		if ($cop_body != ""){
			$body .= "<br>Container Contents:<br>" . $cop_body;
		}

		//cycle through attachments needed and add those
		for ($i = 0; $i < sizeof($poc_attachments); $i++){
			if ($poc_attachments[$i] != ""){
				//grab target file, add to email
				$target_file = $target_dir . "\\warehouse_attachments\\" . $poc_attachments[$i];
				$mail->addAttachment($target_file, $poc_attachments[$i]); 
			}
		}

		//add bol (if applicable)
		if (isset($_FILES["bol"])){
			//grab target file, add to email
			$target_file = $target_dir . "\\warehouse_attachments\\" . $bol_name;
			$mail->addAttachment($target_file, $bol_name); 
		}

		//check for original attachments sent to warehouse, attach those as well

		//file 1 attachment syntax
		if ($_POST['mo_attachment1'] != ""){
			//grab target file, add to email
			$target_file = $target_dir . "\\materialOrders\\" . $_POST['mo_attachment1'];
			$mail->addAttachment($target_file, $_POST['mo_attachment1']); 
		}

		//file 2 attachment syntax
		if ($_POST['mo_attachment2'] != ""){
			//grab target file, add to email
			$target_file = $target_dir . "\\materialOrders\\" . $_POST['mo_attachment2'];
			$mail->addAttachment($target_file, $_POST['mo_attachment2']); 
		}

		//file 3 attachment syntax
		if ($_POST['mo_attachment3'] != ""){
			//grab target file, add to email
			$target_file = $target_dir . "\\materialOrders\\" . $_POST['mo_attachment3'];
			$mail->addAttachment($target_file, $_POST['mo_attachment3']); 
		}
		
		//loop through any warehouse helper attachments added to the material order
		$query = "select detail from fst_allocations_warehouse WHERE type = 'WHH' AND id = " . $_POST['unique_id'] . ";";
		$result = mysqli_query($con, $query);

		while($rows = mysqli_fetch_assoc($result)){

			//grab target file, add to email
			$target_file = $target_dir . "\\warehouse_attachments\\" . $rows['detail'];
			$mail->addAttachment($target_file, $rows['detail']);
		}
	}

	//update all parts in database to Complete & adjust inventory
	adjust_inventory($_POST['mo_id'], $user_info, "Pending");

	//update fst_pq_detail statuses based on if this is Staged.
	if ($_POST['input_staged'] == 'true') {
		$query = "UPDATE fst_pq_detail 
					SET received_qty = q_allocated, status = 'Staged', 
						received_by = '" . $user_info['firstName'] . " " . $user_info['lastName'] . "', received_date = NOW(),
						received_staged_loc = '" . mysql_escape_mimic($_POST['input_staged_loc']) . "', shop_staged = '" . mysql_escape_mimic($_POST['shop']) . "'
					WHERE mo_id = '" . $_POST['mo_id'] . "';";
	}
	else{

		//if shipping now, check if this is shop to shop
		//query shipping locations table to check if this is going to a PW shop
		$query = "SELECT name FROM general_shippingadd WHERE name = '" . mysql_escape_mimic($_POST['ship_to']) . "' AND customer = 'PW';";
		$result = mysqli_query($con, $query);

		//if we return results, set to In-Transit, otherwise, set to Shipped
		if (mysqli_num_rows($result) > 0)
			$query = "UPDATE fst_pq_detail 
						SET status = 'In-Transit', received_staged_loc = null,
							received_qty = 0, received_date = null
						WHERE mo_id = '" . $_POST['mo_id'] . "';";
		else
			$query = "UPDATE fst_pq_detail 
						SET status = 'Shipped', received_staged_loc = null
						WHERE mo_id = '" . $_POST['mo_id'] . "';";

	}
	//execute with error handler
	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
	
	//set all applicable reels to complete
	$pq_detail_ids = json_decode($_POST['pq_detail_ids']);

	//loop through all pq_ids and check for BULK assignments
	foreach ($pq_detail_ids as $curr_id){

		//get any reel requests related to this part
		//$query = "select * from inv_reel_requests WHERE pq_detail_id = '" . $curr_id ."';";
		$query = "select a.*, b.bulk from inv_reel_requests a
					LEFT JOIN inv_reel_assignments b
						ON a.reel_id = b.id
					WHERE a.pq_detail_id = '" . $curr_id ."' AND a.status = 'Pending';";
		$result = mysqli_query($con, $query);

		//check for results
		if (mysqli_num_rows($result) > 0){

			//if we return results, loop through all returned rows and adjust inv_reel_assignments table
			while($rows = mysqli_fetch_assoc($result)){

				//if bulk, adjust quantity available
				if (intval($rows['bulk']) == 1)
					$query = "UPDATE inv_reel_assignments 
								SET quantity = quantity - " . $rows['quantity'] . ", quantity_requested = quantity_requested - " . $rows['quantity'] . "
								WHERE id = '" . $rows['reel_id'] . "';";
				// otherwise, update status of reel assignment so we cannot allocate again				
				else
					$query = "UPDATE inv_reel_assignments 
								SET status = 'Processed'
								WHERE id = '" . $rows['reel_id'] . "';";
				custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

				//update status of request to complete
				$query = "UPDATE inv_reel_requests 
								SET status = 'Complete'
								WHERE id = '" . $rows['id'] . "';";
				custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

			}
		}
	}

	//check to see if pick-ticket exists on server and add as attachment
	$target_pick_ticket = $target_dir . "\\uploads\\" . $_POST['mo_id'] . "-pick-ticket.pdf";
	
	if(file_exists($target_pick_ticket))
		$mail->addAttachment($target_pick_ticket);
	
	//Content
	$mail->isHTML(true);
	$mail->Subject =  $subject_line;
	$mail->Body = "Hello, <br><br>" . $body . "<br>Thank you,";
	$mail->send();

	//close smtp connection
	$mail->smtpClose();
	
	//delete pick-ticket created for the email from server
	if(file_exists($target_pick_ticket))
		unlink($target_pick_ticket);

	return;
		
}

//used remove whh attachments
if ($_POST['tell'] == 'remove_whh'){
	
	//remove file from server
	unlink("warehouse_attachments\\" . $_POST['detail']);
	
	//remove info from database
	$query = "DELETE FROM fst_allocations_warehouse WHERE id = " . $_POST['key'] . " AND detail = '" . $_POST['detail'] . "';";
	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

	return;
		
}

//used to process a rejected part
if ($_POST['tell'] == "reject"){
	
	//change part in fst_pq_detail to rejected
	//treat different depending on the type of rejection
	if ($_POST['reject_detail'] == "full"){
		$query = "UPDATE fst_pq_detail SET status = 'Requested', mo_id = '' WHERE id = '" . $_POST['pq_detail_id'] . "';";
		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
		
		//update detail for $body
		$_POST['reject_detail'] = "fully";
		
	}
	//if partial, need to create a new line for rejected amount, update the existing to what we are sending from warehouse.
	else{
		
		//create new line
		$query = "INSERT INTO fst_pq_detail (project_id, part_id, quantity, status) VALUES ('" . $_POST['pq_overview_id'] . "', '" . mysql_escape_mimic($_POST['reject_part']) . "', '" . $_POST['reject_quantity'] . "', 'Requested');";
		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
		
		//update existing line
		$query = "UPDATE fst_pq_detail SET quantity = quantity - " . $_POST['reject_quantity'] . " WHERE id = '" . $_POST['pq_detail_id'] . "';";
		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

		//update detail for $body
		$_POST['reject_detail'] = "partially";
		
	}
	
	//remove material order from queue if there are no longer any parts to be picked
	remove_from_queue_if_empty($_POST['mo_number'], "warehouse");

	//change status of parts request to rejected
	$query = "UPDATE fst_pq_overview SET status = 'Rejected' WHERE id = '" . $_POST['pq_overview_id'] . "';";
	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
	
	//save reject and detail
	$query = "INSERT INTO fst_pq_detail_rejects (`partNumber`, `quantity`, `type`, `reason`, `notes`, `time_stamp`) VALUES ('" . $_POST['reject_part'] . "', '" . $_POST['reject_quantity'] . "', 'Warehouse', '" . $_POST['reject_reason'] . "', '" . mysql_escape_mimic($_POST['reject_notes']) . "', NOW());";
	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
	
	//create subject line & body
	//check for project name (used in subject line)
	$query = "SELECT location_name FROM fst_grid WHERE vpProjectNumber = '" . $_POST['project_number'] . "' ORDER BY lastUpdate desc LIMIT 1;";
	$result = mysqli_query($con, $query);

	if ($result->num_rows > 0){
		$project_name = mysqli_fetch_array($result);
		$subject_line = "[Item Rejected] " . $_POST['urgency'] . " Job #" . $_POST['project_number'] . " (" . $project_name['location_name'] . ") MO #" . $_POST['mo_number'] . " has requested a change to the original MO";
	}
	else{
		$subject_line = "[Item Rejected] " . $_POST['urgency'] . " Job #" . $_POST['project_number'] . " - MO #" . $_POST['mo_number'] . " has requested a change to the original MO";
	}
	
	//create body
	$body = "Team, <br><br>";
	$body.= "Material Order #" . $_POST['mo_number'] . " has been rejected at the terminal.<br><br>";
	$body.= "Part number " . $_POST['reject_part'] . " has been " . $_POST['reject_detail'] . " rejected: " . strtolower($_POST['reject_reason']) . ".<br><br>";
	
	//check for notes
	if ($_POST['reject_notes'] != "")
		$body.= "Warehouse Notes: " . $_POST['reject_notes'] . "<br><br>";
	
	$body.= "Thank you, ";
	
	//send out email based on information provided
	//Instantiation and passing `true` enables exceptions
	$mail = new PHPMailer();

	//init $mail settings
	$mail = init_mail_settings($mail);

	//Recipients
	//check to see if session email is set, if not lets use allocations
	$mail->setFrom($_SESSION['email'], $_SESSION['firstName'] . " " . $_SESSION['lastName']); //set from (name is optional)
	$mail->AddReplyTo($_SESSION['email'], $_SESSION['firstName'] . " " . $_SESSION['lastName']);

	//depending on the ship from location, tag either omaha logistics or charlotte
	if ($use == "test"){
		$mail->addAddress($_SESSION['email']);
	}
	else{
		$mail->addAddress('allocations@piersonwireless.com');
	}
	
	//add shop as CC
	if ($use == "test"){
		$mail->addAddress($_SESSION['email']);
	}
	else{
		$mail->addCC($_SESSION['email']);
	}

	//$mail->addBCC('alex.borchers@piersonwireless.com'); 	//bcc me for now

	//Content
	$mail->isHTML(true);
	$mail->Subject =  $subject_line;
	$mail->Body = $body;
	$mail->send();

	//close smtp connection
	$mail->smtpClose();
	
	return;
	
}

//used to process MO request back from the warehouse team
if ($_POST['tell'] == "close_shipment"){
	
	// read in user_info & other required arrays/objects
	$user_info = json_decode($_POST['user_info'], true);
	$shipping_containers = json_decode($_POST['shipping_containers'], true);
	$ship_request = json_decode($_POST['ship_request'], true);;

	// loop through shipping containers, update fst_pq_detail
	foreach ($shipping_containers as $container){

		// if container is empty (or other) treat differently
		if ($container == "" || $container == "Other")
			$query = "UPDATE fst_pq_detail SET status = 'Shipped' WHERE ship_request_id = '" . $ship_request['id'] . "' AND wh_container = '" . mysql_escape_mimic($container) . "';";
		else
			$query = "UPDATE fst_pq_detail SET status = 'Shipped' WHERE ship_request_id = '" . $ship_request['id'] . "' AND wh_container = '" . mysql_escape_mimic($container) . "';";
		
		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

	}

	// check the type of request
	if ($_POST['type'] == "Full"){

		// write/execute query
		$query = "UPDATE fst_pq_ship_request SET status = 'Shipped' WHERE id = '" . $ship_request['id'] . "';";
		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

	}
	else{

		// completed query will look like 
		// INSERT INTO fst_pq_ship_request (column1, column2, column3, ...)
		// SELECT column1, column2, column3, ...
		// FROM fst_pq_ship_request
		// WHERE condition;

		// create array of columns to push into new table
		$cols = ["quoteNumber", "ship_from", "created_by", "created", "email_cc", "additional_instructions", "poc", 
				"poc_phone", "poc_email", "ship_location", "ship_address", "ship_city", "ship_state", "ship_zip", 
				"liftgate_opt", "due_by_date", "scheduled_delivery", "scheduled_date", "scheduled_time"];

		// create new shipment to assign all open items to
		$query = "INSERT INTO fst_pq_ship_request (";

		// loop through $cols to set up query
		foreach ($cols as $col){
			$query .= $col . ", ";
		}

		// remove last 2 characters
		$query = substr($query, 0, strlen($query) - 2);
		$query .= ") SELECT ";

		// loop through again to set up 2nd part of query
		foreach ($cols as $col){
			
			// for created, use timestamp
			if ($col == "created")
				$query .= "NOW(), ";
			else
				$query .= $col . ", ";
		}

		// remove last 2 characters, complete query and run
		$query = substr($query, 0, strlen($query) - 2);
		$query .= " FROM fst_pq_ship_request WHERE id = '" . $ship_request['id'] . "';";
		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

		// update all staged items for previous ship request id, to new id
		$id = mysqli_insert_id($con);
		$query = "UPDATE fst_pq_detail SET ship_request_id = '" . $id . "' WHERE ship_request_id = '" . $ship_request['id'] . "' AND status <> 'Shipped';";
		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
		
	}

	// update status of shipping request to shipped
	$query = "UPDATE fst_pq_ship_request SET status = 'Shipped' WHERE id = '" . $ship_request['id'] . "';";
	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

	// log shipped items on job, alert user
	// to be completed


	return;
		
}

//used to process MO request back from the warehouse team
if ($_POST['tell'] == "unassigned_containers"){
	
	// Read in user_info & other required arrays/objects
	$user_info = json_decode($_POST['user_info'], true);
	$unassigned_containers = json_decode($_POST['new_assignments'], true);

	// loop through shipping containers, update fst_pq_detail
	foreach ($unassigned_containers as $new_container){
		$query = "UPDATE fst_pq_detail SET wh_container = '" . $new_container['container'] . "' WHERE id = '" . $new_container['id'] . "';";
		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
	}

	return;
		
}

//function defined to return array of objects (should match queries to get info in terminal_orders.php)
//param 1 = type (determines the type of info to be returned / query executed)
//param 2 = SQL connection
function get_db_info($type, $con, $shop = null){

	//decide which query based on type
	if ($type == "invreport_physical_locations")
		$query = "select shop, partNumber, location, quantity, prime from invreport_physical_locations;";
	elseif($type == "fst_pq_detail")
		$query = "SELECT a.quoteNumber, a.project_id AS vp_number, b.* FROM fst_pq_overview a
					LEFT JOIN fst_pq_detail b 
						ON a.id = b.project_id
					WHERE b.status IN('Shipped', 'Staged', 'In-Transit', 'Ship Requested') OR b.decision LIKE '" . $shop . "%' OR b.shop_staged LIKE '" . $shop . "%';";
	elseif($type == "fst_pq_shipments"){

		$query = "SELECT c.project_id, a.*, d.po_ship_to
					FROM fst_pq_orders_shipments a
					LEFT JOIN fst_pq_orders_assignments b
						ON a.po_number = b.po_number
					LEFT JOIN fst_pq_overview c 
						ON b.pq_id = c.id
					LEFT JOIN fst_pq_orders d
						ON b.po_number = d.po_number
					WHERE a.shipped = 1 AND a.status <> 'Received'
					GROUP BY a.shipment_id;";
						
	}
	elseif ($type == "fst_pq_ship_request"){
		$query = "SELECT a.*, CONCAT(b.vpProjectNumber, ' ', b.location_name) AS 'project_name' FROM fst_pq_ship_request a
					LEFT JOIN fst_grid b
						ON a.quoteNumber = b.quoteNumber
					WHERE a.id IN (SELECT ship_request_id FROM fst_pq_detail WHERE ship_request_id IS NOT NULL AND shop_staged = '" . $shop . "' GROUP BY ship_request_id)
					AND a.status IN ('Open', 'In Progress');";
	}

	//execute selected query
	$result = mysqli_query($con, $query);

	//init return array
	$return_array = [];

	// treat differently for shipments
	if ($type == "fst_pq_shipments"){

		//execute first query
		$result =  mysqli_query($con, $query);
		while($rows = mysqli_fetch_assoc($result)){

			//reset project_id if null
			if (is_null($rows['project_id']))
				$rows['project_id'] = "";

			//add type & push to array
			$rows['type'] = 'PO';
			$rows['mo_id'] = '';
			array_push($return_array, $rows);
		}

		// write & execute next query
		$query = "SELECT * 
					FROM fst_allocations_mo 
					WHERE mo_id <> 'PO' AND status = 'Closed';";
		$result =  mysqli_query($con, $query);

		while($rows = mysqli_fetch_assoc($result)){
			
			//create array object to match format of fst_pq_orders_shipments
			array_push($return_array, [
				'project_id' => $rows['project_id'],
				'shipment_id' => '',
				'po_number' => '',
				'mo_id' => $rows['mo_id'],
				'tracking' => $rows['tracking'],
				'carrier' => $rows['carrier'],
				'ship_date' => substr($rows['closed'], 0, 10),
				'arrival' => $rows['receipt'],
				'received_by' => $rows['received_by'],
				'notes' => $rows['notes'],
				'type' => 'MO',
				'po_ship_to' => $rows['ship_to']
			]);
		}
	}
	else{
		//loop and add to arrays
		while($rows = mysqli_fetch_assoc($result)){
			array_push($return_array, $rows);
		}
	}

	return $return_array;
}