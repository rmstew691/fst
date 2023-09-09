<?php

// Load in dependencies (including starting a session)
session_start();
include('constants.php');
include('phpFunctions.php');
include('PHPClasses/Part.php');
include('PHPClasses/Notifications.php');

// Load the database configuration file
require_once 'config.php';

//grab current time (centeral) for calculations
date_default_timezone_set('America/Chicago');
$date = strtotime(date('d-m-y h:i:s'));

// get user info
$user_info = json_decode($_POST['user_info'], true); 

//Step 1: Read in variables & write them to the database for safe keeping

//information that will change depending on where it comes from
//if wo_num set, it's a work order
if (isset($_POST['WO_Num'])){
	$WO_num  = $_POST['WO_Num'];
	$WO_name = $_POST['WO_Name'];
	
	//if this is a work order we are going to list what is in inventory
	$part_id = [];
	$oma1 = [];
	$cha1 = [];

	$query = "SELECT partNumber, `OMA-1`, `CHA-1` FROM invreport";
	$result =  mysqli_query($con, $query);
	
	//read into array
	while($rows = mysqli_fetch_assoc($result)){
		array_push($part_id, $rows['partNumber']);
		array_push($oma1, $rows['OMA-1']);
		array_push($cha1, $rows['CHA-1']);
	}
}

//if vpnum is set, it's a regular parts request
if (isset($_POST['VPNum'])){
	$quote = $_POST['quote'];
	$location_name = $_POST['location_name'];
	$VPNum = $_POST['VPNum'];
	$mmd = $_POST['mmd'];
	$oem_num = $_POST['oemNum'];
	$cust_pn = $_POST['custNum'];
	$bus_unit_num = $_POST['bus_unit_num'];
	$loc_id = $_POST['loc_id'];
}
else{
	$mmd = "NA";
	$location_name = "Work Order";
	$oem_num = "NA";
	$cust_pn = "NA";
	$bus_unit_num = "NA";
	$loc_id = "NA";
}

//information sent over from AJAX request (comes from both sources)
$createdBy = $_POST['createdBy'];
$delivOpt = $_POST['delivOpt'];
$schedDateTime = null;
$liftgateOpt = $_POST['liftgateOpt'];
$contact_name = $_POST['contact_name'];
$contact_phone = $_POST['contact_phone'];
$contact_email = $_POST['contact_email'];
$shipping_address = $_POST['shipping_address'];
$shipping_city = $_POST['shipping_city'];
$shipping_state = $_POST['shipping_state'];
$shipping_zip = $_POST['shipping_zip'];
$shipping_location = $_POST['shipping_location'];
$dueDate = $_POST['dueDate'];
$schedDate = $_POST['schedDate'];
$schedTime = $_POST['schedTime'];
$emailTo = $_POST['email_to'];
$emailCC = $_POST['email_cc'];
$additionalInfo = $_POST['additionalInfo'];
$parts_requested = json_decode($_POST['parts_requested'], true);

//call urgency calculator
$urgency = urgency_calculator($dueDate);

//add info will hold new additional info line
$addInfo = "";

//check addition info and set email body
if ($additionalInfo == ""){
	$additionalInfo = "N/A";
}
else{
	$addInfo = "<br><br>Addtional Information: " . $additionalInfo;
}

//we will need to do different things if this is a standard request vs. a WO request

//(1) WO = just need to create the subject line and file, do not send query to DB
if (isset($_POST['WO_Num'])){
	$subjectLine = $urgency . ' [Parts Request] - ' . $WO_name . ' - Work Order #' . $WO_num;
	$excel = 'Parts Request - Work Order #' . $WO_num . '.xlsx';
	$body = 'Allocations Team,<br><br> Please see below the parts request for ' . $WO_name . ' - Work Order #' . $WO_num . "<br><br>";
	
	//set variable for next query
	$type = "WO";
	$pn = $WO_num;
	$name = $WO_name;
	$quote = "";
}

//(2) Standard = need to create subject line, file and send query to database
if (isset($_POST['VPNum'])){
	
	$subjectLine = $urgency . ' [Parts Request] - ' . $location_name . ' - ' . $VPNum . ' - Quote #' . $quote;
	$excel = $location_name . ' - ' . $VPNum . ' - Quote #' . $quote . '.xlsx';
	$body = 'Allocations Team,<br><br> Please see below the parts request for ' . $VPNum . ' ' . $location_name . ' <br> Link: https://pw-fst.northcentralus.cloudapp.azure.com/FST/application.php?quote=' . $quote . '.<br><br>';

	//do a few checks to see if we have certain information
	if ($cust_pn == ""){
		$cust_pn = "N/A";
	}
	if ($oem_num == ""){
		$oem_num = "N/A";
	}
	
	//set variable for next query
	$type = "PM";
	$pn = $VPNum;
	$name = $location_name;
	
}


//query to see if any parts requests have been made under this project id so far. 
$query = "SELECT id FROM fst_pq_overview WHERE project_id = '" . $pn . "' ORDER BY id desc LIMIT 1;";
$result =  mysqli_query($con, $query);

//if we have had a request already, mark as additional parts, if not, kickoff.
if (mysqli_num_rows($result) > 0)
	$classification = "Additional Parts";
else
	$classification = "Kickoff";




//build basic request info (this will appear in the body of the email)
$request_info = "<table>
					<tr>
						<th style = 'text-align: left'><i>Contact Info / Shipping Location</i></th>
						<td></td>
					</tr>
					<tr>
						<td>" . $shipping_location . " </td>
					</tr>
					<tr>
						<td>Attn: " . $contact_name . " " . $contact_phone . "</td>
					</tr>
					<tr>
						<td>" . $shipping_address . "</td>
					</tr>
					<tr>
						<td>" . $shipping_city . ", " . $shipping_state . " " . $shipping_zip . "</td> 
					</tr>
					
				<tr style = 'height: 1em;'></tr>
					
					<tr>
						<th style = 'text-align: left'><i>Delivery Requirements</i></th>
						<td></td>
					</tr>
					<tr>
						<th style = 'text-align: left'>&nbsp;&nbsp;Required Date: </th>
						<td>" . date("m-d-Y", strtotime($dueDate)) . "</td>
					</tr>
					<tr>
						<th style = 'text-align: left'>&nbsp;&nbsp;Liftgate Required? </th>
						<td>" . $liftgateOpt . "</td>
					</tr>
					<tr>
						<th style = 'text-align: left'>&nbsp;&nbsp;Scheduled Delivery? </th>
						<td>" . $delivOpt . "</td>
					</tr>";

if ($delivOpt == "Y"){
	$request_info.= "<tr>
						<th style = 'text-align: left'>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Delivery Date: </th>
						<td>" . date("m-d-Y", strtotime($schedDate)) . "</td>
					</tr>
					<tr>
						<th style = 'text-align: left'>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Delivery Time: </th>
						<td>" . date('h:i a', strtotime($schedTime)) . "</td>
					</tr>";
	$schedDateTime = date('Y-m-d H:i:s', strtotime($schedDate . " " . $schedTime));
}

	//add in project info
	$request_info.="<tr style = 'height: 1em;'></tr>
						<tr>
							<th style = 'text-align: left'><i>Project Info</i></th>
							<td></td>
						</tr>
						<tr>
							<th style = 'text-align: left'>&nbsp;&nbsp;Is MMD Required?</th>
							<td>" . $mmd . "</td>
						</tr>
						<tr>
							<th style = 'text-align: left'>&nbsp;&nbsp;Customer Bid # / Site ID: </th>
							<td>" . $cust_pn . "</td>
						</tr>
						<tr>
							<th style = 'text-align: left'>&nbsp;&nbsp;OEM Registration #: </th>
							<td>" . $oem_num . "</td>
						</tr>
						<tr>
							<th style = 'text-align: left'>&nbsp;&nbsp;Justification: </th>
							<td>" . $classification . "</td>
						</tr>";

$request_info.="</table>";


//add table and additional info to body
$body.= $request_info . $addInfo . '. <br><br>';

//adjust cc email (peel back last semi-colon if applicable)
$emailCC = trim($emailCC);

$check = substr($emailCC, strlen($emailCC)-1);

//if check returns semi-colon, remove
if ($check == ";"){
	$emailCC = substr($emailCC, 0, strlen($emailCC)-1);
}

//update staging location (adjust if WO)
if (isset($_POST['WO_Num']))
	$staging_location = "";
else
	$staging_location = $_POST['staging_location'];

//get manager based on quote market
$query = "select b.manager from fst_grid a, general_market b WHERE a.quoteNumber = '" . $quote . "' AND a.market = b.market LIMIT 1;";
$result = mysqli_query($con, $query);

if (mysqli_num_rows($result) > 0)
	$manager = mysqli_fetch_array($result);
else
	$manager['manager'] = '';

//write to new data tables that will be used long term
$query = "INSERT INTO fst_pq_overview (id, type, project_id, quoteNumber, cc_email, project_name, requested_by, manager, poc_name, poc_number, poc_email, shipping_loc, shipping_street, shipping_city, shipping_state, shipping_zip, staging_loc, liftgate, due_date, sched_opt, sched_time, add_instructions, cust_pn, oem_num, bus_unit_num, loc_id, urgency, requested) 
								VALUES (null, '" . $type . "', '" . $pn . "', '" . $quote . "', '" . mysql_escape_mimic($emailCC) . "', '" . mysql_escape_mimic($name) . "', '" . mysql_escape_mimic($createdBy) . "', '" . mysql_escape_mimic($manager['manager']) . "', '" . $contact_name . "', '" . $contact_phone . "', '" . $contact_email . "', '" . mysql_escape_mimic($shipping_location) . "', '" . mysql_escape_mimic($shipping_address) . "', '" . mysql_escape_mimic($shipping_city) . "', '" . $shipping_state . "', '" . $shipping_zip . "', '" . $staging_location . "', '" . $liftgateOpt . "',  '" . $dueDate . "',  '" . $delivOpt . "',  '" . $schedDateTime . "', '" . mysql_escape_mimic($additionalInfo) . "', '" . mysql_escape_mimic($cust_pn) . "', '" . mysql_escape_mimic($oem_num) . "', '" . mysql_escape_mimic($bus_unit_num) . "', '" . mysql_escape_mimic($loc_id) . "', '" . $urgency . "', NOW())";
custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

//grab id of previous query
$curr_id = mysqli_insert_id($con);

//NEW CODE (10.6.21) 
//HANDLES BOM TABLE INTO BODY
$padding = "8px"; //padding of cells (right and left)

if (isset($_POST['VPNum'])){
	//initialize body_table with headers
	$table = "<table style = 'border-collapse: collapse; margin-left: 1em;' >
				<tr>
					<th style = 'padding: " . $padding . "; width: 15em;'>Part #</th>
					<th style = 'padding: " . $padding . "'>Quantity</th>
					<th style = 'padding: " . $padding . "'>Pull From</th>
					<th style = 'padding: " . $padding . "'>MO/PO</th>
					<th style = 'padding: " . $padding . "'>Subs?</th>
					<th style = 'padding: " . $padding . "'>MMD?</th>
				</tr>";

}
else{
	//initialize body_table with headers
	$table = "<table style = 'border-collapse: collapse; margin-left: 1em;'>
				<tr>
					<th style = 'padding: " . $padding . "; width: 15em;'>Part #</th>
					<th style = 'padding: " . $padding . "'>Quantity</th>
					<th style = 'padding: " . $padding . "'>Subs?</th>
					<th style = 'padding: " . $padding . "'>OMA-1</th>
					<th style = 'padding: " . $padding . "'>CHA-1</th>
				</tr>";
}

$color1 = "#c2e2ff"; //color used for every even table row
$color2 = "#d9edff"; //color used for odd rows

$color = $color1; //set to first color

//sheet 2 will be handled differently depending on what type it is, lets check it here
if (isset($_POST['VPNum'])){
	
	//grab existing BOM (used to see if we have any changes to the BOM)
	$query = "SELECT fst_boms.id, partNumber FROM fst_boms WHERE quoteNumber = '" . $quote . "';";
	$result = $mysqli->query($query);

	//initialize current_bom
	$current_bom = [];

	if ($result->num_rows > 0){
		while($rows = mysqli_fetch_assoc($result)){
			
			//push array with key
			$current_bom[intval($rows['id'])] = $rows['partNumber'];
			
		}
	}

	//write out requested parts to BOM page
	for ($i = 0; $i < sizeof($parts_requested); $i++){

		//init the 11th row in array (going to be used to hold any subs)
		$parts_requested[$i]['hold_subs'] = "";

		//first, lets update values in database
		//add requested + allocated
		$new_allocated = intval($parts_requested[$i]['quantity']) + intval($parts_requested[$i]['allocated']);

		//check to see if this was manually entered
		if ($parts_requested[$i]['description'] !== "Manually Entered"){
			
			//check to see if new part number matches old part number
			if (str_replace("\"", "''", $current_bom[intval($parts_requested[$i]['id'])]) != str_replace("\"", "''", $parts_requested[$i]['partNumber'])){
				
				//perform part substitution (located in phpFunctions.php)
				$temp = part_sub_handler($current_bom[intval($parts_requested[$i]['id'])], $parts_requested[$i]['id'], $parts_requested[$i]['partNumber'], $quote, $new_allocated, $user_info['id']);
				
				//update BOM Array to match new values
				$parts_requested[$i]['description'] = $temp['partDescription'];
				$parts_requested[$i]['manufacturer'] = $temp['manufacturer'];
				$parts_requested[$i]['location'] = "[SUB] OMA (" . $temp['OMA'] . ") - CHA (" . $temp['CHA'] . ")";	
				
				//update part number to let allocations know there was a change
				$parts_requested[$i]['partNumber'] = $parts_requested[$i]['partNumber'];
				$parts_requested[$i]['hold_subs'] = $current_bom[intval($parts_requested[$i]['id'])];
			}
			else{
				//query to update allocated amount & subs
				$query = "UPDATE fst_boms SET allocated = " . $new_allocated . ", subs = '" . $parts_requested[$i]['subs'] . "' WHERE fst_boms.id = " . $parts_requested[$i]['id'];
				custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
			}
							
		}
		//if it is, lets save it as an additional part (TYPE = A)
		else{
			//query for part in inv report
			$query = "SELECT * FROM invreport WHERE partNumber = '".$parts_requested[$i]['partNumber']."' LIMIT 1;";
			$result = $mysqli->query($query);

			//initialize partinfo
			$partInfo = [];
			
			if ($result->num_rows > 0){
				$partInfo = mysqli_fetch_array($result);
				
				//update phase
				if ($partInfo['partCategory'] == "ACT-DASHE" || $partInfo['partCategory'] == "ACT-DASREM"){
					$partInfo['phase'] = "03000";
				}
				else{
					$partInfo['phase'] = "06000";
				}
			}
			else{
				//set decision boolean variable
				$index = -1;
				
				//check to see if any new part info has been loaded in
				if (isset($_POST['new_part_info'])){
					//pass to php array
					$np_array = json_decode($_POST['new_part_info']);

					//search through new part info and see if we find any matches
					for ($j = 0; $j < sizeof($np_array); $j++){
						if ($parts_requested[$i]['partNumber'] == $np_array[$j][0]){
							$index = $j;
							break;
						}
					}				
				}
				
				//check index to see if we found anything
				if ($index == -1){
					$partInfo['partDescription'] = "Unknown";
					$partInfo['partCategory'] = "Unknown";
					$partInfo['manufacturer'] = "Unknown";
					$partInfo['cost'] = "0";
					$partInfo['uom'] = "EA";		//default to EA
					$partInfo['phase'] = "06000"; 	//default to 06000
				}
				else{
					$partInfo['partDescription'] = $np_array[$index][1];
					$partInfo['partCategory'] = "Unknown";
					$partInfo['manufacturer'] = $np_array[$index][2];
					$partInfo['cost'] = $np_array[$index][4];
					$partInfo['uom'] = "EA";		//default to EA
					$partInfo['phase'] = "06000"; 	//default to 06000
				}
			}
			
			//save to database for future reference
			$query = "INSERT INTO fst_boms (id, type, quoteNumber, description, partCategory, manufacturer, partNumber, quantity, cost, price, uom, phase, subs, allocated) 
									VALUES (null, 'A', '" . $quote . "', '" . mysql_escape_mimic($partInfo['partDescription']) . "', 
											'" . mysql_escape_mimic($partInfo['partCategory']) . "', '" . mysql_escape_mimic($partInfo['manufacturer']) . "', 
											'" . mysql_escape_mimic($parts_requested[$i]['partNumber']) . "', '" . $parts_requested[$i]['quantity'] . "', '" . $partInfo['cost'] . "', '0', 
											'" . $partInfo['uom'] . "', '" . $partInfo['phase'] . "', '" . $parts_requested[$i]['subs'] . "', '" . $parts_requested[$i]['quantity'] . "');";
			custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
			
		}

		//this will handle writing out the table body of the email
		if ($color == $color1){
			$color = $color2;
		}
		else{
			$color = $color1;
		}

		//write out each cell in the row
		$table .= "<tr style = 'background-color:" . $color . "'>";

		//$table .= "<td>" . $rows['description'] . "</td>";
		//$table .= "<td>" . $rows['manufacturer'] . "</td>";
		$table .= "<td style = 'padding-right: " . $padding . "; border: 1px solid black;'>" . $parts_requested[$i]['partNumber'] . "</td>"; //pn
		$table .= "<td style = 'padding-right: " . $padding . "; padding-left: " . $padding . ";border: 1px solid black; text-align: center'>" . $parts_requested[$i]['quantity'] . "</td>"; //quantity
		$table .= "<td style = 'padding-right: " . $padding . "; padding-left: " . $padding . ";border: 1px solid black; text-align: center'>" . $parts_requested[$i]['location'] . "</td>"; //pref location
		$table .= "<td style = 'padding-right: " . $padding . "; padding-left: " . $padding . ";border: 1px solid black; text-align: center'>" . $parts_requested[$i]['decision'] . "</td>"; //decision
		$table .= "<td style = 'padding-right: " . $padding . "; padding-left: " . $padding . ";border: 1px solid black; text-align: center'>" . $parts_requested[$i]['subs'] . "</td>"; //subs
		$table .= "<td style = 'padding-right: " . $padding . "; padding-left: " . $padding . ";border: 1px solid black; text-align: center'>" . $parts_requested[$i]['mmd'] . "</td>"; //mmd

		$table .= "</tr>";
		
		//set note if mmd
		$note = "";
		if ($parts_requested[$i]['mmd'] == "Yes")
			$note = "This is MMD.";

		//save requested parts to database for reference later (use different query if we have a sub)
		if ($parts_requested[$i]['hold_subs'] == "")
			$query = "INSERT INTO fst_pq_detail (id, project_id, part_id, quantity, q_allocated, subs, mmd, instructions) 
										VALUES (null, '" . $curr_id . "', '" . mysql_escape_mimic($parts_requested[$i]['partNumber']) . "', '" . $parts_requested[$i]['quantity'] . "', '" . $parts_requested[$i]['quantity'] . "', '" . $parts_requested[$i]['subs'] . "', '" . $parts_requested[$i]['mmd'] . "', '" . $note . "');";
		else
			$query = "INSERT INTO fst_pq_detail (id, project_id, part_id, previous_part, quantity, q_allocated, subs, mmd, instructions) 
										VALUES (null, '" . $curr_id . "', '" . mysql_escape_mimic($parts_requested[$i]['partNumber']) . "', '" . mysql_escape_mimic($parts_requested[$i]['hold_subs']) . "', '" . $parts_requested[$i]['quantity'] . "', '" . $parts_requested[$i]['quantity'] . "', '" . $parts_requested[$i]['subs'] . "', '" . $parts_requested[$i]['mmd'] . "', '" . $note . "');";
		
		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
		$get_id = mysqli_insert_id($con);

		//check 10th index (used to hold any reels assigned)
		if (sizeof($parts_requested[$i]['use_reels']) > 0){

			//update status of reels 
			for ($j = 0; $j < sizeof($parts_requested[$i]['use_reels']); $j++){

				//create entry into inv_reel_requests so we can track throughout process
				$query = "INSERT INTO inv_reel_requests (pq_detail_id, reel_id, partNumber, quantity)
												VALUES ('" . $get_id . "', '" . mysql_escape_mimic($parts_requested[$i]['use_reels'][$j]['reel']) . "', 
														'" . mysql_escape_mimic($parts_requested[$i]['partNumber']) . "', '" . mysql_escape_mimic($parts_requested[$i]['use_reels'][$j]['quantity']) . "');";
				custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);	

				//create query to update quantity requested for reel assignments (so we do not over allocate reels)
				$query = "UPDATE inv_reel_assignments 
								SET quantity_requested = quantity_requested + " . $parts_requested[$i]['use_reels'][$j]['quantity'] . "
								WHERE id = '" . $parts_requested[$i]['use_reels'][$j]['reel'] . "';";
				custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);				

			}
		}

		//check if this is a PW-KIT (if so, we also need to create entries for all kit members)
		$part = new part($parts_requested[$i]['partNumber'], $con);

		if ($part->info['partCategory'] == "PW-KITS"){
			
			//update pq_detail kit_tell
			$query = "UPDATE fst_pq_detail SET kit_tell = 'Yes', send = 'false' WHERE id = '" . $get_id . "';";
			custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

			//get kit_detail & add any kit parts to the request
			$kit_detail = $part->get_kit_detail();
			$part->add_kit_detail_to_parts_request($kit_detail, $parts_requested[$i]['quantity'], $parts_requested[$i]['subs'], $parts_requested[$i]['mmd'], $note, $curr_id);

		}
	}
}

if (isset($_POST['WO_Num'])){
	
	//write out requested parts to BOM page
	for ($i = 0; $i < sizeof($parts_requested); $i++){
		
		//this will handle writing out the table body of the email
		if ($color == $color1){
			$color = $color2;
		}
		else{
			$color = $color1;
		}
		
		$index = search_array($part_id, $parts_requested[$i]['partNumber']);

		//write out each cell in the row
		$table .= "<tr style = 'background-color:" . $color . "; border: 1px solid black;'>";

		//$table .= "<td>" . $rows['description'] . "</td>";
		//$table .= "<td>" . $rows['manufacturer'] . "</td>";
		$table .= "<td style = 'padding-right: " . $padding . "; border: 1px solid black;'>" . $parts_requested[$i]['partNumber'] . "</td>";
		$table .= "<td style = 'padding-right: " . $padding . "; padding-left: " . $padding . ";border: 1px solid black; text-align: center'>" . $parts_requested[$i]['quantity'] . "</td>";
		$table .= "<td style = 'padding-right: " . $padding . "; padding-left: " . $padding . ";border: 1px solid black; text-align: center'>" . $parts_requested[$i]['subs'] . "</td>";

		if ($index != -1){
			$table .= "<td style = 'padding-right: " . $padding . "; padding-left: " . $padding . ";border: 1px solid black; text-align: center'>" . $oma1[$index] . "</td>";
			$table .= "<td style = 'padding-right: " . $padding . "; padding-left: " . $padding . ";border: 1px solid black; text-align: center'>" . $cha1[$index] . "</td>";
		}
		else{
			$table .= "<td style = 'padding-right: " . $padding . "; padding-left: " . $padding . ";border: 1px solid black; text-align: center'>0</td>";
			$table .= "<td style = 'padding-right: " . $padding . "; padding-left: " . $padding . ";border: 1px solid black; text-align: center'>0</td>";
		}
		
		$table .= "</tr>";	
		
		//save requested parts to database for reference later
		$query = "INSERT INTO fst_pq_detail (project_id, part_id, quantity, q_allocated, subs) VALUES ('" . $curr_id . "', '" . mysql_escape_mimic($parts_requested[$i]['partNumber']) . "', '" . $parts_requested[$i]['quantity'] . "', '" . $parts_requested[$i]['quantity'] . "', '" . $parts_requested[$i]['subs'] . "');";
		custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);

	}
	
}


//close off table, add any new parts request info if applicable
$table .= "</table>";

//check to see if new part info is included
if (isset($_POST['new_part_info'])){
	//initialize new info
	$new_parts = "";

	//pass to php array
	$np_array = json_decode($_POST['new_part_info']);

	$new_parts.= "<br><br>";// extra space
	$new_parts.= "Please create the following material(s) in Viewpoint:<br>"; //header info

	for ($i = 0; $i < sizeof($np_array); $i++){
		$new_parts.= "<br>";//extra space
		
		//check to see if user created an abbreviation
		if (isset($np_array[$i][6])){
			if ($np_array[$i][6] != ""){
				$new_parts.= "<b style = 'background-color: yellow'>PW Part ID: </b><span style = 'color:red; background-color: yellow'>" . $np_array[$i][6] . "</span><br>";
				$new_parts.= "<b>OEM Part # (Actual): </b><span style = 'color:red'>" . $np_array[$i][0] . "</span><br>";
			}
			else{
				$new_parts.= "<b>PW Part ID: </b><span style = 'color:red'>" . $np_array[$i][0] . "</span><br>";
				$new_parts.= "<b>OEM Part # (Actual): </b><span style = 'color:red'>" . $np_array[$i][0] . "</span><br>";
			}			
		}
		else{
			$new_parts.= "<b>PW Part ID: </b><span style = 'color:red'>" . $np_array[$i][0] . "</span><br>";
			$new_parts.= "<b>OEM Part # (Actual): </b><span style = 'color:red'>" . $np_array[$i][0] . "</span><br>";
		}
		
		$new_parts.= "<b>Part Description: </b><span style = 'color:red'>" . $np_array[$i][1] . "</span><br>";
		$new_parts.= "<b>Manufacturer: </b><span style = 'color:red'>" . $np_array[$i][2] . "</span><br>";
		$new_parts.= "<b>Vendor (if known): </b><span style = 'color:red'>" . $np_array[$i][3] . "</span><br>";
		$new_parts.= "<b>Cost to PW: </b><span style = 'color:red'>" . $np_array[$i][4] . "</span><br>";
		$new_parts.= "<b>Proposed Labor Rate: </b><span style = 'color:red'>" . $np_array[$i][5] . "</span><br>";

	}
}

//Step 3: Create Excel file to be attached in email
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load('ExcelTemplates/PartsRequest-Template.xlsx');

$worksheet = $spreadsheet->getActiveSheet();

//write out requested parts to BOM page
for ($i = 0; $i < sizeof($parts_requested); $i++){
	
	//fill part # and quantity regardless of what type of project it is
	$worksheet->setCellValueByColumnAndRow(4, $i + 4, $parts_requested[$i]['partNumber']); //Part #
	$worksheet->setCellValueByColumnAndRow(5, $i + 4, $parts_requested[$i]['quantity']); //Quantity
	$worksheet->setCellValueByColumnAndRow(8, $i + 4, $parts_requested[$i]['subs']); //Subs Allowed?
	
	//if this is a standard project, fill in applicable info
	if (isset($_POST['VPNum'])){
		$worksheet->setCellValueByColumnAndRow(2, $i + 4, $parts_requested[$i]['description']); //Description
		$worksheet->setCellValueByColumnAndRow(3, $i + 4, $parts_requested[$i]['manufacturer']); //Manufacturer
		
		//depending on decision var, set pref location
		if ($parts_requested[$i]['decision'] == "PO" || $parts_requested[$i]['decision'] == "Substitution"){
			$worksheet->setCellValueByColumnAndRow(7, $i + 4, $parts_requested[$i]['decision']); //Pref Location
		}
		else{
			$worksheet->setCellValueByColumnAndRow(7, $i + 4, "MO (" . $parts_requested[$i]['location'] . ")"); //Pref Location
		}
		
		$worksheet->setCellValueByColumnAndRow(9, $i + 4, $parts_requested[$i]['mmd']); //MMD?
	}
	//hide columns not needed for work orders
	else{
		$index = search_array($part_id, $parts_requested[$i]['partNumber']);
		
		if ($index > -1)
			$worksheet->setCellValueByColumnAndRow(7, $i + 4, "OMA(" . $oma1[$index] . ") - CHA(" . $cha1[$index] . ")"); //Pref Location
	}
}

//autofit columns B - G
$worksheet->getColumnDimension('B')->setAutoSize(true);
$worksheet->getColumnDimension('C')->setAutoSize(true);
$worksheet->getColumnDimension('D')->setAutoSize(true);
$worksheet->getColumnDimension('E')->setAutoSize(true);
$worksheet->getColumnDimension('G')->setAutoSize(true);
$worksheet->getColumnDimension('H')->setAutoSize(true);

//hide columns not needed for work orders
if (isset($_POST['WO_Num'])){
	$worksheet->getColumnDimension('B')->setVisible(false);
	$worksheet->getColumnDimension('C')->setVisible(false);
	$worksheet->getColumnDimension('I')->setVisible(false);
}

//save as excel file
$excel = "Parts Request Form.xlsx";
$writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
$writer->save($excel);

//Step 4: Send an email to the allocations team with the excel file attached.

//Import PHPMailer classes into the global namespace
//These must be at the top of your script, not inside a function
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once 'PHPMailer/Exception.php';
require_once 'PHPMailer/PHPMailer.php';
require_once 'PHPMailer/SMTP.php';

//if we have new parts, send out the request to material creation first
if (isset($_POST['new_part_info'])){
	//Instantiation and passing `true` enables exceptions
	$mail_newPart = new PHPMailer(); 
	$mail_newPart = init_mail_settings($mail_newPart, $user_info);
	
	//Recipients
	//$mail_newPart->addAddress("amb035@morningside.edu"); //send to material creation group
	$mail_newPart->addAddress("MaterialsCreation@piersonwireless.com"); //send to material creation group
	$mail_newPart->addCC($user_info['email']); //cc yourself
	
	//Content
	$mail_newPart->isHTML(true);
	$mail_newPart->Subject =  "Material Creation Request";
	$mail_newPart->Body = "Material Creation Team," . $new_parts . "<br>Thank you,";
	$mail_newPart->send();
	
	//close smtp connection
	$mail_newPart->smtpClose();
}

//Instantiation and passing `true` enables exceptions
$mail = new PHPMailer();
$mail = init_mail_settings($mail, $user_info);

//check to see if a semicolon is present, if so, parse through email line & add all emails to group
//add to
$check = 0;
$check = strpos($emailTo, ";");

if($check > 0){
	$emails = explode(";", $emailTo);
	for ($i = 0; $i < sizeof($emails); $i++){
		$mail->addAddress(trim($emails[$i]));
	}
	
}
else{
	$email = trim($emailTo);
	$mail->addAddress($emailTo);
}

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

//add bcc's (myself to make sure everything is working)
$mail->addCC($user_info['email']); //cc yourself

//Attachments
//add attachments if they are set
$target_dir = getcwd(); //target directory (if sending attachments)

if (isset($_FILES['file1'])){
	//save file
	$target_file = $target_dir . "\\uploads\\" . basename($_FILES["file1"]["name"]);

	//if file is added successfully, add to email
	if (move_uploaded_file($_FILES["file1"]["tmp_name"], $target_file)) {
		$mail->addAttachment($target_file, $_FILES["file1"]["name"]); 
	}
	else {
		echo "Sorry, there was an error uploading your file.";
	 }
}

//file 2 attachment logic
if (isset($_FILES['file2'])){
	//save file
	$target_file = $target_dir . "\\uploads\\" . basename($_FILES["file2"]["name"]);

	//if file is added successfully, add to email
	if (move_uploaded_file($_FILES["file2"]["tmp_name"], $target_file)) {
		$mail->addAttachment($target_file, $_FILES["file2"]["name"]); 
	}
	else {
		echo "Sorry, there was an error uploading your file.";
	 }
}

//file 3 attachment logic
if (isset($_FILES['file3'])){
	//save file
	$target_file = $target_dir . "\\uploads\\" . basename($_FILES["file3"]["name"]);

	//if file is added successfully, add to email
	if (move_uploaded_file($_FILES["file3"]["tmp_name"], $target_file)) {
		$mail->addAttachment($target_file, $_FILES["file3"]["name"]); 
	}
	else {
		echo "Sorry, there was an error uploading your file.";
	 }
}
	
//attach excel file
$mail->addAttachment($excel);

//rewrite body if applicable
$body .= $table . "<br>";

//add goodbye message
$body .= "Thank you,";


//Content
	$mail->isHTML(true);
	$mail->Subject =  $subjectLine;
	$mail->Body = $body;

//if mail sends correctly, return success
if($mail->send()){
	echo "success";
	/*
	//save message ID so we can reply once it is in progress
	$query = "UPDATE fst_pq_overview SET message_id = '" . mysql_escape_mimic($mail->getLastMessageID()) . "' WHERE id = '" . $curr_id . "';";

	if (!$mysqli -> query($query)) {
		echo("Error description: " . $mysqli -> error);
	}
	*/
}

//unlink any files that may have been used
if (isset($_FILES['file1'])){
	//save file
	$target_file = $target_dir . "\\uploads\\" . basename($_FILES["file1"]["name"]);
	unlink($target_file);
}

//file 2 attachment logic
if (isset($_FILES['file2'])){
	//save file
	$target_file = $target_dir . "\\uploads\\" . basename($_FILES["file2"]["name"]);
	unlink($target_file);
}

//file 3 attachment logic
if (isset($_FILES['file3'])){
	//save file
	$target_file = $target_dir . "\\uploads\\" . basename($_FILES["file3"]["name"]);
	unlink($target_file);
}

//Delete the file & close the mail connection
unlink($excel);

//close smtp connection
$mail->smtpClose();

//log successful parts request
if (isset($_POST['VPNum'])){
	
	// Log parts request & order id
	$notification = new Notifications($con, "parts_request", "Order ID: " . $curr_id, $quote, $use);
	$notification->log_notification($user_info['id']);
}