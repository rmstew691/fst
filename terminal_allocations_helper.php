<?php

// Load dependencies
session_start();
include('constants.php');
include('phpFunctions.php');
include('PHPClasses/Part.php');
include('PHPClasses/Notifications.php');

// Load the database configuration file
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

//handles getting BOM pricing for analytics and returning
if ($_POST['tell'] == "bom_pricing"){

	//load in parts request detail (parts)
	$bom_pricing = [];
	$result = mysqli_query($con, "select quoteNumber, partNumber, quantity, cost FROM fst_boms;");

	//loop through rows and add to array
	while($rows = mysqli_fetch_assoc($result)){
		array_push($bom_pricing, $rows);
	}

	//return to user
	echo json_encode($bom_pricing);
	return;
}

//handles reading and writing pq_detail/overview to and from server (updates queue)
if ($_POST['tell'] == "update_queue"){
	
	//look at target_ids (any that have changed)
	$target_ids = json_decode($_POST['target_ids']);

	//if we find any that have changed, we need to go through and save them
	if (sizeof($target_ids) > 0){

		//decode other arrays
		$pq_detail = json_decode($_POST['pq_detail'], true);
		$pq_overview = json_decode($_POST['pq_overview'], true);
		$update_ids = json_decode($_POST['update_ids']);

		//loop through target id's (inner loop of array of id's to find a match)
		for ($i = 0; $i < sizeof($target_ids); $i++){
			
			//loop through id's
			for ($j = 0; $j < sizeof($pq_overview); $j++){
				
				//look for match, update values
				if ($target_ids[$i] == $pq_overview[$j]['id']){

					//create first part of query
					$query = "UPDATE fst_pq_overview SET ";
					
					//create the rest of the query using update_ids array from js
					for ($k = 0; $k < sizeof($update_ids); $k++){
						
						//treat last value differently
						if ($k == sizeof($update_ids) - 1)
							$query .= "`" . $update_ids[$k] . "` = '" . mysql_escape_mimic($pq_overview[$j][$update_ids[$k]]) . "' "; 
						else
							$query .= "`" . $update_ids[$k] . "` = '" . mysql_escape_mimic($pq_overview[$j][$update_ids[$k]]) . "', "; 
						
					}
					
					//end query
					$query .= " WHERE id = '" . $pq_overview[$j]['id'] . "';";
					custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
					
					break;

				}
			}
						
			//same process for part info (pq_detail)
			for ($j = 0; $j < sizeof($pq_detail); $j++){
								
				//look for match, update values
				if ($target_ids[$i] == $pq_detail[$j]['project_id']){
					
					//create first part of query
					$query = "UPDATE fst_pq_detail SET instructions = '" . mysql_escape_mimic($pq_detail[$j]['instructions']) . "', decision = '" . mysql_escape_mimic($pq_detail[$j]['decision']) . "', q_allocated = '" . mysql_escape_mimic($pq_detail[$j]['q_allocated']) . "', send = '" . mysql_escape_mimic($pq_detail[$j]['send']) . "', reject = '" . mysql_escape_mimic($pq_detail[$j]['reject']) . "' WHERE id = '" . $pq_detail[$j]['id'] . "';";
					custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
				}
			}
		}
	}

	//initialize object that will hold all of this info (overwrite existing)
	$pq_overview = get_db_info("fst_pq_overview", $con);
	$pq_detail = get_db_info("fst_pq_detail", $con);

	//compile object with both open and whh
	$return_array = [];
	array_push($return_array, $pq_overview);
	array_push($return_array, $pq_detail);

	//return to user
	echo json_encode($return_array);
	
	return;
	
}

//handles initializing MO #s
if ($_POST['tell'] == 'init_mo'){
	
	//grab array for target shops
	$target_shops = json_decode($_POST['target_shops'], true);
	$part_assignments = json_decode($_POST['part_assignments'], true);
	
	//get year abbreviation
	$year = date("y");

	//get most recent mo assignment (in a given year)
	$query = "select * from fst_allocations_mo_assignment WHERE left(mo_id, 2) = '" . $year . "' order by mo_id desc LIMIT 1;";
	$result = $mysqli->query($query);

	if ($result->num_rows > 0){
		$result = mysqli_fetch_array($result);
		$mo_recent = $result['mo_id'];
	}
	else{
		$mo_recent = "xx-00000";
	}
	
	//turn mo_recent into an integer that we can use
	$just_id = intval(substr($mo_recent, strlen($mo_recent) - 5, 5));
	
	//initalize return array
	$return_array = [];
	
	//loop through shops and generate ID for each
	for ($i = 0; $i < sizeof($target_shops); $i++){
		
		//only execute this next part if not PO or not blank
		if ($target_shops[$i] != "" && $target_shops[$i] != "PO"){

			//init next_mo
			$next_mo = "NA";
			
			//first check to see if we already have an MO for this combo
			$query = "select * from fst_allocations_mo_assignment WHERE project_id = '" . $_POST['id'] . "' AND shop = '" . $target_shops[$i] . "' ORDER BY mo_id desc LIMIT 1;";
			$result = $mysqli->query($query);

			//check previous to see if closed
			if ($result->num_rows > 0){
				$result = mysqli_fetch_array($result);
				$next_mo = $result['mo_id'];
				
				//$query = "select mo_id from fst_allocations_mo WHERE mo_id = '" . $result['mo_id'] . "' AND status IN ('Closed', 'Received', 'Shipping Later');";
				$query = "select mo_id from fst_allocations_mo WHERE mo_id = '" . $result['mo_id'] . "' AND status IN ('Closed', 'Received');";
				$result = $mysqli->query($query);
				
				//if we return a closed MO, do not use
				if ($result->num_rows > 0) $next_mo = "NA";
			}
			
			//if next_mo is set to NA, increment the previous by 1 and set $next_mo
			if ($next_mo == "NA"){
				//incremenet to next
				$just_id++;

				//add strings depending on length of previous id
				if (strlen($just_id) == 1)
					$next_mo = "0000" . $just_id;
				elseif (strlen($just_id) == 2)
					$next_mo = "000" . $just_id;
				elseif (strlen($just_id) == 3)
					$next_mo = "00" . $just_id;
				elseif (strlen($just_id) == 4)
					$next_mo = "0" . $just_id;
				elseif (strlen($just_id) == 5)
					$next_mo = $just_id;
				else
					$next_mo = "ERROR OUT OF ROOM";

				//add to next_mo
				$next_mo = $year . "-" . $next_mo;

				//save mo_id to assignment databaase
				$query = "INSERT INTO fst_allocations_mo_assignment (mo_id, project_id, shop) VALUES ('" . $next_mo . "', '" . $_POST['id'] . "', '" . $target_shops[$i] . "');";
				$result = $mysqli->query($query);

			}

			//create temp array to push to return array
			$temp = array(
				'shop'=>$target_shops[$i],
				'mo_id'=>$next_mo
			);

			array_push($return_array, $temp);

			//loop through part assignments and save MO to any parts matching the target shop
			for ($j = 0; $j < sizeof($part_assignments); $j++){

				//check shops
				if (strpos($part_assignments[$j]['shop'], $target_shops[$i]) === 0 && $part_assignments[$j]['send'] == 'true'){

					//save mo_id to part in fst_pq_details
					$query = "UPDATE fst_pq_detail SET mo_id = '" . $next_mo . "', send = 'true', decision = '" . $part_assignments[$j]['shop'] . "' WHERE id = '" . $part_assignments[$j]['part_id'] . "';";
					custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
				}
			}
		}
	}
	
	//one last loop to remove MO_ids from any parts that are not checked and to remove any that need to be purchased
	for ($i = 0; $i < sizeof($part_assignments); $i++){
				
		//check shops
		if ($part_assignments[$i]['shop'] == "PO" || $part_assignments[$i]['shop'] == "" || $part_assignments[$i]['send'] == 'false'){
						
			//save mo_id to part in fst_pq_details
			$query = "UPDATE fst_pq_detail SET mo_id = null, send = '" . $part_assignments[$i]['send'] . "', decision = '" . $part_assignments[$i]['shop'] . "' WHERE id = '" . $part_assignments[$i]['part_id'] . "';";
			custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

		}
		
	}
	
	//echo return array
	echo json_encode($return_array);
	
	//go back to user
	return;
}

//handles processing request to reject a part (removed from pq_detail)
if ($_POST['tell'] == 'reject'){

	//add reject reason to log
	$query = "INSERT INTO fst_pq_detail_rejects (`partNumber`, `quoteNumber`, `type`, `reason`, `notes`, `user`, `time_stamp`) VALUES ('" . $_POST['reject_part'] . "', '" . $_POST['quoteNumber'] . "', 'Allocations', '" . $_POST['reject_reason'] . "', '" . mysql_escape_mimic($_POST['reject_notes']) . "', '" . $_SESSION['employeeID'] . "',  NOW());";
	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

	//get previous id
	$reject_id = mysqli_insert_id($con);

	//update fst_pq_detail with reject & reason
	$query = "UPDATE fst_pq_detail SET reject = 'true', reject_id = '" . $reject_id . "' WHERE id = '" . $_POST['pq_detail_id'] . "';";
	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
	
}

//handles processing ajax request to split lines
if ($_POST['tell'] == 'process_split'){
	
	//grab strings and arrays passed through ajax
	$part = $_POST['part'];
	$pq_id = $_POST['pq_id'];
	$part_splits = json_decode($_POST['part_splits'], true);
		
	//loop through part_splits, make a decision based on the quantity and ID, execute queries to update fst_pq_detail
	for ($i = 0; $i < sizeof($part_splits); $i++){
		
		//if quantity = 0, we need to remove from table
		if ($part_splits[$i]['quantity'] == 0){
			
			//if id is blank, do nothing, else we need to remove part based on id
			if ($part_splits[$i]['id'] != ""){
				$query = "DELETE FROM fst_pq_detail WHERE id = '" . $part_splits[$i]['id'] . "';";
				custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

			}
		}
		//else we need to either update or add quantity and shop location to database
		else{
			
			//if id is blank, insert new row
			if ($part_splits[$i]['id'] == ""){
				$query = "INSERT INTO fst_pq_detail (id, project_id, part_id, q_allocated, decision, send) VALUES (null, '" . $pq_id . "', '" . mysql_escape_mimic($part) . "', '" . $part_splits[$i]['quantity'] . "', '" . $part_splits[$i]['shop'] . "', 'true');";
				custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
			}
			//else update existing entry based on id
			else{
				$query = "UPDATE fst_pq_detail SET q_allocated = '" . $part_splits[$i]['quantity'] . "', decision = '" . $part_splits[$i]['shop'] . "' WHERE id = '" . $part_splits[$i]['id'] . "';";
				custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
			}
			
		}
		
	}
	
	//now that we have updated our parts, refresh fst_pq_overview and send back
	$pq_detail = get_db_info("fst_pq_detail", $con);
	
	//echo return array
	echo json_encode($pq_detail);
	
	//go back to user
	return;
}

//handles adding a new sub to our catalog
if ($_POST['tell'] == 'add_new_sub'){
	
	//grab strings and arrays passed through ajax
	$target_part = $_POST['target_part'];
	$new_sub = $_POST['new_sub'];

	//get current subs list from invreport
	$query = "SELECT subPN from invreport WHERE partNumber = '" . mysql_escape_mimic($target_part) . "';";
	$result =  mysqli_query($con, $query);
	$part = mysqli_fetch_array($result);

	//trim & check how to add
	$part['subPN'] = trim($part['subPN']);

	if ($part['subPN'] == "" || $part['subPN'] == null){
		$part['subPN'] = $new_sub;
	}
	elseif (substr($part['subPN'], -1) == ","){
		$part['subPN'] = $part['subPN'] . $new_sub;
	}
	else{
		$part['subPN'] = $part['subPN'] . "," . $new_sub;
	}
	
	//replace part in parts request form
	$query = "UPDATE invreport SET subPN = '" . $part['subPN'] . "' WHERE partNumber = '" . mysql_escape_mimic($target_part) . "';";
	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
	
	//go back to user
	return;
}

//handles processing ajax request to make substitution
if ($_POST['tell'] == 'process_sub'){
	
	//grab strings and arrays passed through ajax
	$old_part = $_POST['old_part'];
	$new_part = $_POST['new_part'];
	$part_id = $_POST['part_id'];
	$quote = $_POST['quote'];
	$user_info = json_decode($_POST['user_info'], true);
		
	//call sub handler
	part_sub_handler($old_part, -1, $new_part, $quote, -1, $user_info['id']);
	
	//replace part in parts request form
	$query = "UPDATE fst_pq_detail SET part_id = '" . mysql_escape_mimic($new_part) . "', previous_part = '" . mysql_escape_mimic($old_part) . "' WHERE id = '" . $part_id . "';";
	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
	
	//now that we have updated our parts, refresh fst_pq_overview and send back
	$pq_detail = get_db_info("fst_pq_detail", $con);
	
	//echo return array
	echo json_encode($pq_detail);
	
	//go back to user
	return;
}


//used to process new orders from terminal_allocations_new.php (submit_orders() function)
if ($_POST['tell'] == "process_orders"){
	
	//current director
	$target_dir = getcwd();

	//user info
	$user_info = json_decode($_POST['user_info'], true);

	//decode arrays on server and grab any info passed over
	$pq_id = $_POST['pq_id'];
	$pull_from = json_decode($_POST['pull_from']);
	$attachments = json_decode($_POST['attachments']);
	$requested_parts = $_POST['requested_parts'];
	$project_id = $_POST['project_id'];
	$project_name = $_POST['project_name'];
	$staging_loc = $_POST['staging_loc'];
	$staging_abv = $_POST['staging_abv'];
	$staging_street = $_POST['staging_street'];
	$staging_city = $_POST['staging_city'];
	$staging_state = $_POST['staging_state'];
	$staging_zip = $_POST['staging_zip'];
	$poc_name = $_POST['poc_name'];
	$poc_number = $_POST['poc_number'];
	$requested_by = $_POST['requested_by'];
	$liftgate = $_POST['liftgate'];
	$sched_opt = $_POST['sched_opt'];
	$sched_time = $_POST['sched_time'];
	$manager = $_POST['manager'];
	$ship_to = $_POST['ship_to'];
	$street = $_POST['street'];
	$city = $_POST['city'];
	$state = $_POST['state'];
	$zip = $_POST['zip'];
	$due_date = $_POST['due_date'];
	$early_delivery = $_POST['early_delivery'];
	$has_reels = "Yes"; //temp to be updated
	$cc = $_POST['cc'];
	$mo_lines = -1;
	$amending_mo = $_POST['amending_mo'];
	$reship_request = $_POST['reship_request'];

	//init number of MOs set to send
	$query = "select COUNT(DISTINCT left(decision, 3)) as 'count' FROM fst_pq_detail WHERE project_id = '" . $pq_id . "' AND (decision is not null AND decision <> '') AND send = 'true';";
	$result =  mysqli_query($con, $query);
	$mo_count = mysqli_fetch_array($result);
	
	//init processing array (holds shop and MO #'s)
	$mo_assignment = [];
	
	//cycle through pull_from locations to grab any MO's that have been assigned to these shops
	for ($i = 0; $i < sizeof($pull_from); $i++){
		
		//query to pull MOs
		$query = "SELECT * FROM fst_allocations_mo_assignment WHERE project_id = '" . $pq_id . "' AND shop = '" . $pull_from[$i] . "' ORDER BY mo_id desc LIMIT 1;";
		$result =  mysqli_query($con, $query);

		//check if we returned a result, add MO # to an array with the shop
		if (mysqli_num_rows($result) > 0){
			$result = mysqli_fetch_array($result);
			array_push($mo_assignment, array('mo'=>$result['mo_id'], 'shop'=>$result['shop']));
		}
		elseif($pull_from[$i] == "PO"){
			array_push($mo_assignment, array('mo'=>'PO', 'shop'=>'PO'));
		}
	}
	
	//save any files associated with the project
	for ($i = 0; $i < 3; $i++){

		//check if anything was passed through attachments
		if ($attachments[$i] != ""){

			//build target file path
			$target_file = $target_dir . "\\materialOrders\\" . basename($_FILES["file" . $attachments[$i]]["name"]);

			//grab file type from target path
			$fileType = strtolower(pathinfo($target_file,PATHINFO_EXTENSION));

			//pass file name to new_name function, returns unique file name
			$_FILES["file" . $attachments[$i]]["name"] = new_name($_FILES["file" . $attachments[$i]]["name"], $fileType);

			//recreate file path with new name
			$target_file = $target_dir . "\\materialOrders\\" . basename($_FILES["file" . $attachments[$i]]["name"]);

			//add attachment to MO folder on server
			if (move_uploaded_file($_FILES["file" . $attachments[$i]]["tmp_name"], $target_file)) {
				//if we are successful, save attachment name to be saved in database
				$attachments[$i] = $_FILES["file" . $attachments[$i]]["name"];
			} 
			else {
				//return error, reset attachment name
				echo "Sorry, there was an error uploading your file.";
				$attachments[$i] = "";

			}
		}
	}
	
	//loop through all entered MO's and process information
	for ($i = 0; $i < sizeof($mo_assignment); $i++){

		//if this is a greensheet, adjust inventory and continue to next MO
		if ($_POST['greensheet'] != 'false'){
			adjust_inventory($mo_assignment[$i]['mo'], $user_info, "Requested");
			continue;
		}

		//generate notes based on location
		//notes are saved in one line as [location]|[Notes]|
		$loc_index = strpos($_POST['notes'], $mo_assignment[$i]['shop'] . "|");

		if (is_int($loc_index)){
			$start_index = $loc_index + strlen($mo_assignment[$i]['shop']) + 1;
			$end_index = strpos($_POST['notes'], "|", $start_index);
			$notes = substr($_POST['notes'], $start_index, $end_index - $start_index);
		}
		else{
			$notes = "";
		}

		//call urgency calculator
		$urgency = urgency_calculator($due_date);
			
		//get MO lines
		$query = "select COUNT(id) as count from fst_pq_detail WHERE mo_id = '" . $mo_assignment[$i]['mo'] . "';";
		$result = mysqli_query($con, $query);

		//check number of rows
		if ($result->num_rows > 0){
			$temp = mysqli_fetch_array($result);
			$mo_lines = $temp['count'];
		}
		else{
			$mo_lines = 0;
		}
		
		//check for reels
		$query = "SELECT t.uom FROM fst_pq_detail LEFT JOIN (SELECT * FROM invreport GROUP BY partNumber) t ON t.partNumber = fst_pq_detail.part_id WHERE mo_id = '" . $mo_assignment[$i]['mo'] . "' AND t.uom = 'LF';";
		$result = mysqli_query($con, $query);

		//check number of rows
		if ($result->num_rows > 0)
			$has_reels = "Yes";
		else
			$has_reels = "No";

		//check to see if this is an amended PO
		//bool value (flips to true if we pass all tests)
		$amend_bool = false;

		//check for existing MO
		$query = "SELECT id, attachment1 FROM fst_allocations_mo WHERE mo_id = '" . $mo_assignment[$i]['mo'] . "' AND pq_id = '" . $pq_id . "' LIMIT 1;";
		$result = mysqli_query($con, $query);

		//check number of rows
		if ($result->num_rows > 0){
			$amend_bool = true;
			$existing = mysqli_fetch_array($result);
		}

		//update shipping address based on staging
		if ($staging_loc == "Ship To Final Destination"){
			$ship_opt = "Ship When Ready";
			$shipping_place = "Final Destination";
			$final_ship_to = $ship_to;
			$final_street = $street;
			$final_city = $city;
			$final_state = $state;
			$final_zip = $zip;

			//save list
		}
		elseif ($mo_assignment[$i]['shop'] == "PO"){
			$ship_opt = "Ship When Ready";
			$shipping_place = "Staging Location";

			//save listed location (we will update to staging location to send in the email)
			$final_ship_to = $ship_to;
			$final_street = $street;
			$final_city = $city;
			$final_state = $state;
			$final_zip = $zip;
			//$final_ship_to = $staging_loc;
			//$final_street = $staging_street;
			//$final_city = $staging_city;
			//$final_state = $staging_state;
			//$final_zip = $staging_zip;
		}
		elseif(is_int(strpos(strtolower($staging_abv), strtolower($mo_assignment[$i]['shop']))) && $mo_count['count'] > 1){
			$ship_opt = "Stage and Ship Complete";
			$shipping_place = "Final Destination";
			$final_ship_to = $ship_to;
			$final_street = $street;
			$final_city = $city;
			$final_state = $state;
			$final_zip = $zip;
		}
		elseif(is_int(strpos(strtolower($staging_abv), strtolower($mo_assignment[$i]['shop'])))){
			$ship_opt = "Ship When Ready";
			$shipping_place = "Final Destination";
			$final_ship_to = $ship_to;
			$final_street = $street;
			$final_city = $city;
			$final_state = $state;
			$final_zip = $zip;
		}
		else{
			$ship_opt = "Ship When Ready";
			$shipping_place = "Staging Location";
			$final_ship_to = $staging_loc;
			$final_street = $staging_street;
			$final_city = $staging_city;
			$final_state = $staging_state;
			$final_zip = $staging_zip;
		}

		//create query based on current array
		//if amend_bool is false, create new entry, if true, update existing)
		if ($amend_bool){
			
			//if we are amending, we will use existing ID (from previous query) and attachment 1 (as previous version)
			$query = 'UPDATE fst_allocations_mo SET 
						project_id = "' . mysql_escape_mimic($project_id) . '", 
						project_name = "' . mysql_escape_mimic($project_name) . '",
						pq_id = "' . mysql_escape_mimic($pq_id) . '", 
						poc_name = "' . mysql_escape_mimic($poc_name) . '", 
						poc_number = "' . mysql_escape_mimic($poc_number) . '", 
						requested_by = "' . mysql_escape_mimic($requested_by) . '", 
						liftgate = "' . mysql_escape_mimic($liftgate) . '", 
						sched_opt = "' . mysql_escape_mimic($sched_opt) . '", 
						sched_time = "' . mysql_escape_mimic($sched_time) . '", 
						manager = "' . mysql_escape_mimic($manager) . '", 
						ship_from = "' . mysql_escape_mimic($mo_assignment[$i]['shop']) . '", 
						ship_to = "' . mysql_escape_mimic($final_ship_to) . '", 
						street = "' . mysql_escape_mimic($final_street) . '", 
						city = "' . mysql_escape_mimic($final_city) . '", 
						state = "' . mysql_escape_mimic($final_state) . '", 
						zip = "' . mysql_escape_mimic($final_zip) . '",
						shipping_place = "' . mysql_escape_mimic($shipping_place) . '",
						shipping_opt = "' . mysql_escape_mimic($ship_opt) . '", 
						date_required = "' . mysql_escape_mimic($due_date) . '", 
						early_delivery = "' . mysql_escape_mimic($early_delivery) . '",
						has_reels = "' . mysql_escape_mimic($has_reels) . '",
						urgency = "' . mysql_escape_mimic($urgency) . '", 
						attachment1 = "' . mysql_escape_mimic($attachments[0]) . '", 
						attachment2 = "' . mysql_escape_mimic($existing['attachment1']) . '", 
						line_items = "' . mysql_escape_mimic($mo_lines) . '", 
						amending_reason = "' . mysql_escape_mimic($amending_mo) . '", 
						reship_reason = "' . mysql_escape_mimic($reship_request) . '", 
						status = "Open", 
						cc_email = "' . mysql_escape_mimic($cc) . '", 
						notes = "' . mysql_escape_mimic($notes) . '",
						staged_opt = 0,
						date_amended = NOW() 
						WHERE id = "' . $existing['id'] . '";';
		}
		else{
			
			$query = 'INSERT INTO fst_allocations_mo 
						(id, project_id, project_name, mo_id, pq_id, poc_name, poc_number, requested_by, liftgate, sched_opt, 
						sched_time, manager, ship_from, ship_to, street, city, state, zip, shipping_place, shipping_opt, date_required, early_delivery, 
						has_reels, urgency, attachment1, attachment2, attachment3, line_items, amending_reason, reship_reason, status, 
						cc_email, notes, staged_opt, date_created) 
						VALUES 
						(null, "' . mysql_escape_mimic($project_id) . '", "' . mysql_escape_mimic($project_name) . '", "' . mysql_escape_mimic($mo_assignment[$i]['mo']) . '", 
						"' . mysql_escape_mimic($pq_id) . '", "' . mysql_escape_mimic($poc_name) . '", "' . mysql_escape_mimic($poc_number) . '", 
						"' . mysql_escape_mimic($requested_by) . '", "' . mysql_escape_mimic($liftgate) . '", "' . mysql_escape_mimic($sched_opt) . '", 
						"' . mysql_escape_mimic($sched_time) . '", "' . mysql_escape_mimic($manager) . '", "' . mysql_escape_mimic($mo_assignment[$i]['shop']) . '", 
						"' . mysql_escape_mimic($final_ship_to) . '", "' . mysql_escape_mimic($final_street) . '", "' . mysql_escape_mimic($final_city) . '", 
						"' . mysql_escape_mimic($final_state) . '", "' . mysql_escape_mimic($final_zip) . '", "' . mysql_escape_mimic($shipping_place) . '", 
						"' . mysql_escape_mimic($ship_opt) . '", 
						"' . mysql_escape_mimic($due_date) . '", "' . mysql_escape_mimic($early_delivery) . '", "' . mysql_escape_mimic($has_reels) . '", 
						"' . mysql_escape_mimic($urgency) . '", "' . mysql_escape_mimic($attachments[0]) . '", "' . mysql_escape_mimic($attachments[1]) . '", 
						"' . mysql_escape_mimic($attachments[2]) . '", "' . mysql_escape_mimic($mo_lines) . '", "' . mysql_escape_mimic($amending_mo) . '", 
						"' . mysql_escape_mimic($reship_request) . '", "Open", "' . mysql_escape_mimic($cc) . '", "' . mysql_escape_mimic($notes) . '", 0, NOW());';
		}

		//call custom_query
		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

		//set subject line & body
		$subject_line = $urgency . " Job #" . $project_id . " (" . $project_name . ") MO #" . $mo_assignment[$i]['mo'] . " has been issued to the Shipping Terminal";
		$body = "Team, <br><br>Material Order #" . $mo_assignment[$i]['mo'] . " has been issued to the Shipping Terminal.<br>";

		//check if it has reels
		if ($has_reels == "Yes")
			$body.= "<br>MO contains " . $mo_lines . " line items with reels to be pulled.<br>";
		else
			$body.= "<br>MO contains " . $mo_lines . " line items with no reels to be pulled.<br>";

		//add thank you
		$body.= "<br>Thank you,";

		//send out email based on information provided
		//Instantiation and passing `true` enables exceptions
		$mail = new PHPMailer();
		$mail = init_mail_settings($mail);

		//Recipients
		//check to see if session email is set, if not lets use allocations

		$mail->setFrom($_SESSION['email'], $_SESSION['firstName'] . " " . $_SESSION['lastName']); //set from (name is optional)
		$mail->AddReplyTo($_SESSION['email'], $_SESSION['firstName'] . " " . $_SESSION['lastName']);

		//cc groups needed
		if ($use == "test"){
			$mail->addAddress($_SESSION['email']);
		}
		else{
			//cc allocations
			$mail->addCC('allocations@piersonwireless.com'); 	
		}
		
		//bcc me for now
		//$mail->addBCC('alex.borchers@piersonwireless.com'); 	

		
		//If this is a purchase order, we need to add an excel document, adjust the subject/body, add CC group
		if ($mo_assignment[$i]['mo'] == "PO"){
			
			//if we are not shipping to final, we want to list out staging address on email to orders (but we saved the final destination)
			if ($staging_loc != "Ship To Final Destination"){
				$final_ship_to = $staging_loc;
				$final_street = $staging_street;
				$final_city = $staging_city;
				$final_state = $staging_state;
				$final_zip = $staging_zip;
			}

			//add orders team
			if ($use != "test"){
				$mail->addAddress('orders@piersonwireless.com'); 	//cc orders
			}
			
			//update subject line & body
			$subject_line = $urgency . " [Parts for Order] Job #" . $project_id . " (" . $project_name . ") - Due By " . date("m-d-Y", strtotime($due_date));
			$excel_name = "Parts for Order - Job " . $project_id . " (" . $project_name . ").xlsx";
			
			$body = "Orders Team,<br><br>";
			$body.= "Please see Project #" . $project_id . " for items to be purchased. These items are needed in <span style = 'color: red'>" . $final_street . " " . $final_city . ", " . $final_state . " " . $final_zip . " </span> by <span style = 'color: red'>" . date("m-d-Y", strtotime($due_date)) . ".</span> Please reach out to allocations with any questions or contact the designer directly if necessary.<br><br>";
			
			//add note to body if applicable
			if ($notes != null && $notes != "")
				$body.= "Notes: " . $notes . "<br><br>";
			
			$body.= "Thank you,";

			//access template & activate sheet
			$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load('ExcelTemplates/PartsRequest-Template.xlsx');
			$worksheet = $spreadsheet->getActiveSheet();

			//look for parts set to be purchased
			$query = "SELECT fst_pq_detail.*, t.partDescription , t.manufacturer
						FROM fst_pq_detail
						LEFT JOIN (SELECT * FROM invreport GROUP BY partNumber) t
						ON t.partNumber = fst_pq_detail.part_id
						WHERE project_id = '" . $pq_id . "' AND send = 'true' AND decision = 'PO' AND fst_pq_detail.status = 'Requested';";
			$result = mysqli_query($con, $query);

			//add CC to excel doc
			$worksheet->setCellValueByColumnAndRow(1, 2, "cc:"); 

			//explode emails by ; and read out to excel
			$emails = explode(";", $cc);

			//loop and read out (add row for each new email)
			$emails_added = 0;

			for ($j = 0; $j < sizeof($emails); $j++){

				//trim email
				$emails[$j] = trim($emails[$j]);

				//check for blanks
				if ($emails[$j] != "" && $emails[$j] != null){

					//set column b(2) descending
					$worksheet->setCellValueByColumnAndRow(2, $emails_added + 2, $emails[$j]); 
					//$worksheet->getCellByColumnAndRow(2, $emails_added + 2)->getHyperlink()->setUrl($emails[$j]); 

					//insert row (don't add row for first email)
					$worksheet->insertNewRowBefore($emails_added + 3);

					//increment # added
					$emails_added++;
				}
			}

			//init first row of table
			$curr_row = 4 + $emails_added;
			
			if ($result->num_rows > 0){
				
				//write out requested parts to BOM page
				while($rows = mysqli_fetch_assoc($result)){

					//fill part # and quantity regardless of what type of project it is
					$worksheet->setCellValueByColumnAndRow(2, $curr_row, $rows['partDescription']); //Description
					$worksheet->setCellValueByColumnAndRow(3, $curr_row, $rows['manufacturer']); 	//Manufacturer
					$worksheet->setCellValueByColumnAndRow(4, $curr_row, $rows['part_id']); 		//Part #
					$worksheet->setCellValueByColumnAndRow(5, $curr_row, $rows['q_allocated']); 	//Quantity
					$worksheet->setCellValueByColumnAndRow(10, $curr_row, $rows['instructions']); 	//Notes
					$curr_row++;

					//update vendor_qty to match quantity requested
					$query = "UPDATE fst_pq_detail SET vendor_qty = q_allocated WHERE id = '" . $rows['id'] . "'";
					custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
				}
			}
			else{
				$worksheet->setCellValueByColumnAndRow(2, $curr_row, "No parts came through in this request. Please reach out to allocations or fst@piersonwireless.com for help, there may have been an error."); //Description
			}
			
			//autofit columns B - E & J
			$worksheet->getColumnDimension('B')->setAutoSize(true);
			$worksheet->getColumnDimension('C')->setAutoSize(true);
			$worksheet->getColumnDimension('D')->setAutoSize(true);
			$worksheet->getColumnDimension('E')->setAutoSize(true);
			$worksheet->getColumnDimension('J')->setAutoSize(true);
			
			//remove unneeded (F-I)
			$worksheet->removeColumnByIndex(6);
			$worksheet->removeColumnByIndex(6);
			$worksheet->removeColumnByIndex(6);
			$worksheet->removeColumnByIndex(6);
			
			//save as excel file
			$writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
			$writer->save($excel_name);
			$mail->addAttachment($excel_name);
			
		}
		elseif ($mo_assignment[$i]['shop'] == "OMA" && $use != "test"){
			$mail->addAddress("OmahaLogistics@piersonwireless.com");
		}
		elseif ($mo_assignment[$i]['shop'] == "CHA" && $use != "test"){
			$mail->addAddress("CharlotteLogistics@piersonwireless.com");
		}
		
		//Content
		$mail->isHTML(true);
		$mail->Subject =  $subject_line;
		$mail->Body = $body;
		$mail->send();

		//close smtp connection
		$mail->smtpClose();

		//if PO, remove excel doc from server
		if ($mo_assignment[$i]['mo'] == "PO")
			unlink($excel_name);
	}

	//use pq_id to get quoteNumber from fst_pq_overview table (use this to get change order value from fst_grid)
	$query = "SELECT quoteNumber FROM fst_pq_overview WHERE id = '" . $pq_id . "';";
	$result = mysqli_query($con, $query);
	$overview = mysqli_fetch_array($result);

	//get co_number from fst_grid
	$query = "SELECT co_number FROM fst_grid WHERE quoteNumber = '" . $overview['quoteNumber'] . "';";
	$result = mysqli_query($con, $query);

	if (mysqli_num_rows($result) > 0){

		//hold potential CO number
		$co = mysqli_fetch_array($result);

		//convert $co if null 
		if ($co['co_number'] == null)
			$co['co_number'] = "";

		//if not blank, convert to co string
		if ($co['co_number'] != "" && strlen($co['co_number']) == 1)
			$co['co_number'] = "-0" . strval($co['co_number']); 
		elseif ($co['co_number'] != "" && strlen($co['co_number']) == 2)
			$co['co_number'] = "-" . strval($co['co_number']);

	}
	else{
		//if no quote found in fst_grid, default co_number to blank
		$co['co_number'] = "";
	}	

	//once we have mo request, send CSV of parts to DTVP for processing
	//init array for data
	$bom_csv = [];
	
	$query = "SELECT * FROM fst_pq_detail WHERE project_id = '" . $pq_id . "' AND send = 'true' AND status = 'Requested';";
	$result = mysqli_query($con, $query);

	//cycle through parts related to this project
	while($rows = mysqli_fetch_assoc($result)){

		//create array to place into bom_csv (function found in phpFunction.php)
		//passes the given pq_detail row
		$bom_row = vp_bom_row($rows, $project_id, $co['co_number']);
								
		//push to bom_csv (only ones being procesed = if not yet in warehouse, on PO or complete)
		if ($rows['status'] != "Shipped" && $rows['status'] != "Received" && $rows['status'] != "PO" && $rows['status'] != "Warehouse")
			array_push($bom_csv, $bom_row);
		
		//decide what to push status to based on decision (if already complete, do not move back into warehouse)
		$status = "Pending";
		
		//move status to warehouse
		$query = "UPDATE fst_pq_detail SET status = '" . $status . "' WHERE id = '" . $rows['id'] . "';";
		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);	
	}
	
	//if we had matching materials, send to DTVP
	if (sizeof($bom_csv) > 0){
	
		//init active/passive bools which will be flipped while creating csv
		$active = false;
		$passive = false;

		//transfer bom_csv to actual csv to attach in email
		$filename = $project_id  . " Material Upload File.csv";

		// open csv file for writing
		$f = fopen($filename, 'w');

		if ($f === false) {
			die('Error opening the file ' . $filename);
		}

		// write each row at a time to a file
		foreach ($bom_csv as $row) {
			fputcsv($f, $row);

			//check for active/passive
			if (substr($row[3], 0, 5) == "06000")
				$passive = true;
			else
				$active = true;
		}

		//check to see if we need to add rows for active/passive materials
		if ($active)
			fputcsv($f, array("2", $project_id, "1", "03000" . $co['co_number'], "Active Components", "", "Y"));

		if ($passive)
			fputcsv($f, array("2", $project_id, "1", "06000" . $co['co_number'], "Passive Components", "", "Y"));

		//add contract line
		fputcsv($f, array("1", substr($project_id, 8), "1", "", "", $project_id, "0", "LS", "", "0"));

		// close the file
		fclose($f);

		//send out email to DTVP
		$mail = new PHPMailer();
		$mail = init_mail_settings($mail);

		//Recipients
		//check to see if session email is set, if not lets use allocations

		$mail->setFrom($_SESSION['email'], $_SESSION['firstName'] . " " . $_SESSION['lastName']); //set from (name is optional)
		$mail->AddReplyTo($_SESSION['email'], $_SESSION['firstName'] . " " . $_SESSION['lastName']);

		//depending on the ship from location, tag either omaha logistics or charlotte
		if ($use == "test"){
			$mail->addAddress($_SESSION['email']);
		}
		//DTVP
		else{
			$mail->addAddress("allocations@piersonwireless.com");
			//$mail->addAddress("DTVP-Processing@piersonwireless.com");
		}

		//add attachment
		$mail->addAttachment($filename); 

		//Content
		$mail->isHTML(true);
		$mail->Subject =  "[DT VP-Processing] [Material Upload] " . $project_id . " (" . $project_name . ")";;
		$mail->Body = "Hello DTVP,<br><br>Please use the attached CSV to upload materials for project #" . $project_id . "<br><br>Thank you,";
		$mail->send();

		//close smtp connection
		$mail->smtpClose();

		//Delete the file
		unlink($filename);
	}

	//process any rejected parts 
	$query = "SELECT * FROM fst_pq_detail WHERE project_id = '" . $pq_id . "' AND reject = 'true';";
	$result = mysqli_query($con, $query);

	//check for values in db
	if (mysqli_num_rows($result) > 0){

		//init body of email
		$body = "";

		//loop and add to email body/remove from list
		while($rows = mysqli_fetch_assoc($result)){

			//remove part from pq_detail
			$query = "DELETE FROM fst_pq_detail WHERE id = '" . $rows['id'] . "';";
			custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

			//update $rows['quantity'] if it is blank (should only be for a split parts)
			if ($rows['quantity'] == "" || $rows['quantity'] == null)
				$rows['quantity'] = 0;

			//update fst_boms for given quote
			$query = "UPDATE fst_boms SET allocated = (allocated - " . $rows['quantity'] . ") WHERE quoteNumber = '" . $_POST['quoteNumber'] . "' AND partNumber = '" . mysql_escape_mimic($rows['part_id']) . "';";
			custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

			//get reason and push to body of email
			$query = "select reason from fst_pq_detail_rejects WHERE id = '" . $rows['reject_id'] . "' LIMIT 1;";
			$result_reject = mysqli_query($con, $query);

			//check for values in db
			if (mysqli_num_rows($result_reject) > 0)
				$reject = mysqli_fetch_array($result_reject);
			else
				$reject['reason'] = 'No reason provided';
			
			$body.= $rows['part_id'] . " - " . $reject['reason'] . "<br>";
		}

		//send out email to necessary groups
		$mail = new PHPMailer();
		$mail = init_mail_settings($mail);

		//Recipients
		//check to see if session email is set, if not lets use allocations

		$mail->setFrom($_SESSION['email'], $_SESSION['firstName'] . " " . $_SESSION['lastName']); //set from (name is optional)
		$mail->AddReplyTo($_SESSION['email'], $_SESSION['firstName'] . " " . $_SESSION['lastName']);

		//depending on the ship from location, tag either omaha logistics or charlotte
		if ($use == "test"){
			$mail->addAddress($_SESSION['email']);
		}
		//allocations and parts requestor
		else{
			$mail->addCC("allocations@piersonwireless.com");
			//$mail->addAddress("DTVP-Processing@piersonwireless.com");
		} 

		//Content
		$mail->isHTML(true);
		$mail->Subject =  "[Item Rejected] Job #" . $project_id . " (" . $project_name . ") parts have been rejected";
		$mail->Body = "Hello Team,<br><br>The following parts have been rejected from your parts request:<br><br>" . $body . "<br>Thank you,";
		$mail->send();

		//close smtp connection
		$mail->smtpClose();
	}

	//update status of parts request to submitted (only flip to submitted IF send is true for all)
	$query = "SELECT * FROM fst_pq_detail WHERE project_id = '" . $pq_id . "' AND send = 'false' AND kit_tell = 'No';";
	$result = mysqli_query($con, $query);
	
	//if we return no rows, flip to submitted
	if (mysqli_num_rows($result) == 0){
		
		//if project_id is OMA or CHA, then do not update status (keep in queue)
		if ($project_id != "OMA" && $project_id != "CHA"){
			$query = "UPDATE fst_pq_overview SET status = 'Submitted', closed = NOW() WHERE id = '" . $pq_id . "';";
			custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
		}	
	}
	
	return;
}

//handles processing ajax request to make substitution
if ($_POST['tell'] == 'cancel_pq'){
	
	//go through parts listed in fst_pq_detail and reduce form requested on fst_bom
	$query = "select a.*, b.quoteNumber from fst_pq_detail a, fst_pq_overview b WHERE b.id = a.project_id AND b.id = '" . $_POST['pq_id'] . "';";
	$result = mysqli_query($con, $query);

	//loop and subtract from fst_boms
	while($rows = mysqli_fetch_assoc($result)){

		//create query based on results and reduce quantity allocated from BOM
		if ($rows['quantity'] != null && $rows['quantity'] != ""){
			$query = "UPDATE fst_boms SET allocated = (allocated - " . $rows['quantity'] . ") WHERE quoteNumber = '" . $rows['quoteNumber'] . "' AND partNumber = '" . mysql_escape_mimic($rows['part_id']) . "';";
			custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
		}
	}
	
	//write query to remove from fst_pq_overview & detail
	$query = "DELETE FROM fst_pq_detail WHERE project_id = '" . $_POST['pq_id'] . "';";
	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
	
	$query = "DELETE FROM fst_pq_overview WHERE id = '" . $_POST['pq_id'] . "';";
	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

	$query = "DELETE FROM fst_allocations_mo WHERE pq_id = '" . $_POST['pq_id'] . "';";
	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
	
	//go back to user
	return;
}

//handles processing ajax request to make substitution
if ($_POST['tell'] == 'acknowledge'){
	
	//get message_id so we know what email to reply to (saved as message_id in fst_pq_overview => saved when sending in sendEmail.php)
	$query = "select type, project_id, project_name, quoteNumber, cc_email, urgency, status from fst_pq_overview WHERE id = '" . $_POST['id'] . "';";
	$result = mysqli_query($con, $query);

	//check for results
	if (mysqli_num_rows($result) > 0){
		$pq_overview = mysqli_fetch_array($result);

		//if status is not open, return (someone else already clicked on it)
		if ($pq_overview['status'] != "Open")
			return;

	}
	else{
		$pq_overview['project_id'] = "";
		$pq_overview['project_name'] = "";
		$pq_overview['quoteNumber'] = "";
		$pq_overview['cc_email'] = "";
		$pq_overview['urgency'] = "";
		$pq_overview['type'] = "";
	}

	//create subject line from results
	//1) we found a quote #, use VP subject line
	if ($pq_overview['quoteNumber'] != "")
		$subjectLine = $pq_overview['urgency'] . ' [Parts Request] - ' . $pq_overview['project_name'] . ' - ' . $pq_overview['project_id'] . ' - Quote #' . $pq_overview['quoteNumber'];
	//2) no quote number BUT have ID (this is a work order)
	elseif($pq_overview['project_id'] != "")
		$subjectLine = $pq_overview['urgency'] . ' [Parts Request] - ' . $pq_overview['project_name'] . ' - Work Order #' . $pq_overview['project_id'];
	//3) no match found, don't try to send email
	else
		$subjectLine = "";
	
	//overwrite subject line to blank if type is GS (greensheet), no confirmation email needed
	if ($pq_overview['type'] == "GS")
		$subjectLine = "";

	//if we have ID, send email, otherwise do nothing
	if ($subjectLine != ""){
		//create new instance of PHP Mailer
		$mail = new PHPMailer();
		$mail = init_mail_settings($mail);

		//add body/subject & recipients
		$mail->isHTML(true);       
		$mail->addAddress($_SESSION['email']);
		$mail->setFrom($_SESSION['email'], $_SESSION['firstName'] . " " . $_SESSION['lastName']); //set from (name is optional)
		$mail->AddReplyTo($_SESSION['email'], $_SESSION['firstName'] . " " . $_SESSION['lastName']);

		//use cc_email to see who else this needs to go to
		$cc = explode(';', $pq_overview['cc_email']);

		//loop through $cc and add to email 
		for ($i = 0; $i < sizeof($cc); $i++){
			$mail->addCC($cc[$i]);
		}

		$mail->Subject = $subjectLine;
		$mail->Body    = '<i style = "font-size: 18px" >Parts Request Acknowledgement:<br><br>This request has been received and initiated.  We will reply once your shipment is staged and tracking # is available.</i>';

		//send message
		$mail->send();

		//close smtp connection
		$mail->smtpClose();
	}

	//update status in pq_overview
	$query = "UPDATE fst_pq_overview SET status = 'In Progress' WHERE id = '" . $_POST['id'] . "';";
	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

	//go back to user
	return;
}

//handles processing ajax request to make substitution
if ($_POST['tell'] == 'get_minimum_stock'){
	
	//get shop & id
	$shop = $_POST['shop'];
	$id = $_POST['id'];
	$vp_num = $_POST['vp_num'];

	//look for any parts that are under there minimum order quantity
	$query = "select shop, partNumber, (max_stock - stock) as reorder_qty from inv_locations WHERE min_stock >= stock  AND min_stock > 0 AND (max_stock - stock) > 0 AND shop LIKE '" . $shop . "%';";
	$result = mysqli_query($con, $query);

	//check for results
	if (mysqli_num_rows($result) > 0){

		//loop through results & add to pq_detail (if does not already exist)
		while($rows = mysqli_fetch_assoc($result)){

			//check if part is in pq_detail
			$query = "select id FROM fst_pq_detail 
						WHERE part_id = '" . mysql_escape_mimic($rows['partNumber']) . "' AND project_id = '" . $id . "' 
							AND status IN('Requested', 'Pending');";

			$pq_detail_check = mysqli_query($con, $query);

			//check for results (if 0, add new entry)
			if (mysqli_num_rows($pq_detail_check) == 0){
				$query = "INSERT INTO fst_pq_detail (project_id, part_id, quantity, q_allocated, decision, subs, instructions, status) 
										VALUES ('" . $id . "', '" . mysql_escape_mimic($rows['partNumber']) . "', '" . $rows['reorder_qty'] . "', 
												'" . $rows['reorder_qty'] . "', 'PO', 'Yes', 'Order for " . $rows['shop'] . "', 'Requested');";
				custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

				//get part detail
				$part = new Part($rows['partNumber'], $con);

				//add entry to fst_boms (type A = Additional)
				$query = "INSERT INTO fst_boms (type, quoteNumber, partNumber, partCategory, description, manufacturer, quantity, cost, uom, phase, allocated) 
										VALUES ('A', '" . mysql_escape_mimic($vp_num) . "-011', '" . mysql_escape_mimic($rows['partNumber']) . "', 
												'" . mysql_escape_mimic($part->info['partCategory']) . "', '" . mysql_escape_mimic($part->info['partDescription']) . "', 
												'" . mysql_escape_mimic($part->info['manufacturer']) . "', '" . mysql_escape_mimic($rows['reorder_qty']) . "', 
												'" . mysql_escape_mimic($part->info['cost']) . "', '" . mysql_escape_mimic($part->info['uom']) . "',
												'" . mysql_escape_mimic($part->get_phase_code()) . "', '" . mysql_escape_mimic($rows['reorder_qty']) . "');";
				custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
			}
		}
	}
	
	//get updated pq_detail
	$pq_detail = get_db_info("fst_pq_detail", $con);

	//return to user
	echo json_encode($pq_detail);
	return;
}

//function defined to return array of objects (should match queries to get info in terminal_orders.php)
//param 1 = type (determines the type of info to be returned / query executed)
//param 2 = SQL connection
function get_db_info($type, $con){

	//load in php functions
	//include('phpFunctions.php');

	//decide which query based on type
	if ($type == "fst_pq_overview")
		$query = "SELECT * FROM fst_pq_overview WHERE status IN ('Open', 'In Progress', 'Rejected') OR closed > now() - INTERVAL 2 day ORDER BY closed, requested;";
	elseif($type == "fst_pq_detail")
		$query = "SELECT a.quoteNumber, b.* FROM fst_pq_detail b 
					LEFT JOIN fst_pq_overview a 
						ON a.id = b.project_id
					WHERE b.status = 'Requested';";

	//execute selected query
	$result = mysqli_query($con, $query);

	//init return array
	$return_array = [];

	//loop and add to arrays
	while($rows = mysqli_fetch_assoc($result)){
		array_push($return_array, $rows);
	}

	return $return_array;
}