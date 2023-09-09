<?php
//initialize session
session_start();

//used to grab actual link for the current address
$actual_link = "https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

//Save current site so we can return after log in
$_SESSION['returnAddress'] = $actual_link;

//sub link
$sub_link = substr($actual_link, 0, strpos($actual_link, "home"));

//include php functions sheet
include('phpFunctions.php');

//include php HTML renderings
include('phpFunctions_html.php');

//include constants sheet
include('constants.php');

// Load the database configuration file
require_once 'config.php';

//Make sure user has privileges
//check session variable first
if (isset($_SESSION['email'])){
	$query = "SELECT * from fst_users where email = '".$_SESSION['email']."';";
	$result = $mysqli->query($query);

	if ($result->num_rows > 0){
		$fstUser = mysqli_fetch_array($result);
	}
	else{
		$fstUser['accessLevel'] = "None";
	}
}
else{
	$fstUser['accessLevel'] = "None";
	
}

sessionCheck($fstUser['accessLevel']);

//if admin, display admin button
$admin = "none";

if ($fstUser['accessLevel'] == "Admin"){
	$admin = "";
}

//if user is deployment, hide $ values
$deployHide = "";
if ($fstUser['accessLevel'] == "Deployment"){
	$deployHide = "none";
}

//if deployment, can only search through fst's, cannot create a new one

$protect_header = "";

if ($fstUser['accessLevel'] == "Deployment"){
	$protect_header = "disabled";
}

//will hold arrays on load to transfer to javascript
$parts = [];
$stock = [];

$loadParts = "select partNumber, `OMA-1` + `CHA-1` + `OMA-2` as 'tot' from invreport WHERE active = 'True' order by partCategory, partNumber";
$result = mysqli_query($con, $loadParts);

while($rows = mysqli_fetch_assoc($result)){
	array_push($parts, $rows['partNumber']);
	array_push($stock, $rows['tot']);
}

//user emails
$emails = [];
$directory = [];
$query = "select firstName, lastName, email from fst_users order by email";
$result = mysqli_query($con, $query);

while($rows = mysqli_fetch_assoc($result)){
	array_push($emails, $rows['email']);
	array_push($directory, $rows['firstName'] . " " . $rows['lastName']);
}

//grabs basic info that is listed on fst grid
$query = "SELECT * FROM fst_pq_overview;";
$result =  mysqli_query($con, $query);

//init arrays
$temp_array = [];
$pq_array = [];

while($rows = mysqli_fetch_assoc($result)){
	
	//reset temparray
	$temp_array = [];
	
	//set temp array
	$temp_array = array('type'=>$rows['type'], 
						'project_id'=>$rows['project_id'], 
						'project_name'=>$rows['project_name'], 
						'requested_by'=>$rows['requested_by'], 
						'urgency'=>$rows['urgency'], 
						'requested'=>$rows['requested']
					);
	
	//push temp array to project array
	array_push($pq_array, $temp_array);
	
}

?>


<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="google-signin-client_id" content="573761357198-hin7ae7q19qgvoab7t0781b41530546g.apps.googleusercontent.com">
<link rel="shortcut icon" type="image/x-icon" href="images/P-Logo.png" />
<link rel="stylesheet" href="stylesheets/element-styles.css?<?= $version; ?>">
<link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css'> 
<title>Allocations Hub (v<?= $version ?>) - Pierson Wireless</title>
	
<style>

	.stock{
		text-align: center;
		font-weight: bold;
	}

	#basicInfo {
			float: left;
		}

	.homeTables {
		border-collapse: collapse;

	}	

	.homeTables tbody {
		display: block;
		overflow-y:scroll;
		height: 500px;
		width: 1950px;
	}

	.homeTables thead, .homeTables tbody tr{
		display: table;
		table-layout: fixed;
	}

	.homeTables tbody td {
		border-bottom: 1px solid #000000;
		border-right: 1px solid #000000;
		border-left: 1px solid #000000;

	}

	.homeTables thead th {
		border: 1px solid #000000;

	}

	#profileTable{
		border-collapse: collapse;
	}

	#profileTable td{
		border: 1px solid #000000;
		padding: 5px;
	}

	#profileTable th{
		padding: 5px;
	}

	/* Style the tab content (and add height:100% for full page content) */
	.tabcontent {
	  padding: 25px 20px;
	  height: 100%;
	}

	.basic-table{
		display: inline-block;
		padding-bottom: 5px;
	}

	.basic-table td{
		padding-right: 5px;
	}
	.newPart_input{
		width: 400px;
	}
	.newPart_th{
		text-align: left;
	}
	.pr_tables{
		padding-bottom: 2em;
		
	}
	.shipping_row{
		visibility: collapse;
	}



</style>
</head>

<body>

<?php

	//define array of names & Id's to generate headers
	$header_names = ['Material Orders', 'Purchase Orders'];
	$header_ids = ['MO', 'PO'];

	//pass to php function to create navigation bars
	echo create_navigation_bar($header_names, $header_ids, "", $fstUser);

?>
	
	<div style = 'padding-left:1em;padding-top:4em;'>

		<!--CONSTANT IN constants.php-->
		<?= constant('terminal_navigation') ?>
		
	</div>
	
	<div id ='MO' class = 'tabcontent'>
	
		<h1> Material Order Screen </h1>

		<div>

			<input type="button" value="Submit Material Order(s)" onclick="z.submit_mo()" style ='margin-top: 1em' id = 'submit_button_mo'/>

			<?php

			//use php to create the same table 5 tables, incrementing the $row_id by 1 each time (easier for updates)
			for ($row_id = 1; $row_id < 6; $row_id++){

				$display = "display:none";
				$optional = "(Optional)";
				$render_button = "<button onclick = 'z.show_hide(" . $row_id . ")' id = 'button_" . $row_id . "'>+</button>    ";
				$use_header = "<tr><td>Use MO #1 Header Info? </td><td><input type = 'checkbox' id = 'copy_header" . $row_id . "' onclick = 'use_header(" . $row_id . ")' ></td></tr>";

				//first row, don't show the table, don't list as optional, don't render button
				if ($row_id == 1){
					$display = "";
					$optional = "";
					$render_button = "";
					$use_header = "";
				}


			?>
				<h3><?= $render_button; ?>Material Order #<?= $row_id; ?> <?= $optional; ?></h3>
				<table class = "pr_tables" id = "pr_table<?= $row_id; ?>" style = "line-height:20px;<?= $display; ?>">
					<?= $use_header; ?>
					<tr>
						<td>Greensheet? </td>
						<td class = "partRequestTD"><input type = "checkbox" id = 'greensheet<?= $row_id; ?>' onclick = 'greensheet_fill(<?= $row_id; ?>)' ></td>
					</tr>
					<tr>
						<td>*Project #: </td>
						<td class = "partRequestTD"><input class = "requiredPR<?= $row_id; ?>" type = "text" id = 'project_id<?= $row_id; ?>' style = "width: 250px" ></td>
					</tr>
					<tr>
						<td>*Material Order #: </td>
						<td class = "partRequestTD"><input class = "requiredPR<?= $row_id; ?>" type = "text" id = 'mo_id<?= $row_id; ?>' style = "width: 250px" ></td>
					</tr>
					<tr>
						<td>*Requested By: </td>
						<td class = "partRequestTD">
							<select class = "requiredPR<?= $row_id; ?> custom-select" id = 'requested_by<?= $row_id; ?>' style = "width: 260px" >
									<option></option>
									<option value = 'Other'>Other</option>
									<?php
										//loop through employee directory
										for ($i = 0; $i < sizeof($directory); $i++){

									?>

									<option value = "<?= $directory[$i]; ?>"> <?= $directory[$i] ?></option>

									<?php
										}
									?>
							</select>
						</td>

					</tr>
					<tr>
						<td>*Ship From: </td>
						<td class = "partRequestTD">
							<select class = "requiredPR<?= $row_id; ?> custom-select" id = 'ship_from<?= $row_id; ?>' style = "width: 260px" >
								<option></option>
								<option>OMA</option>
								<option>CHA</option>
							</select>
						</td>
					</tr>
					<tr>
						<td>*Ship To: </td>
						<td class = "partRequestTD">
							<select class = "requiredPR<?= $row_id; ?> custom-select" id = 'ship_to<?= $row_id; ?>' style = "width: 260px" onchange = 'z.ship_to_toggle(this.value, <?= $row_id; ?>)'>
									<option></option>
									<option value = 'Other'>Other</option>
									<?php
										//run query to grab locations
										$shippingLocQ = "SELECT * from general_shippingadd where customer = 'PW' order by name";
										$result = mysqli_query($con, $shippingLocQ);

										//init arrays
										$locations = [];
										$street = [];
										$city = [];
										$state = [];
										$zip = [];

										while($rows = mysqli_fetch_assoc($result))
										{
										array_push($locations, $rows['name']);	
										array_push($street, $rows['address']);	
										array_push($city, $rows['city']);	
										array_push($state, $rows['state']);	
										array_push($zip, $rows['zip']);	

									?>

									<option value = "<?= $rows['name']; ?>"> <?= $rows['name'] ?></option>

									<?php
										}
									?>
									<option value = "Greensheet">Greensheet</option>
							</select>
						</td>

					</tr>
					<tr class = 'shipping_row' id = 'shipping_row<?= $row_id; ?>_1'>
						<td>*Address 1: </td>
						<td class = "partRequestTD"><input class = "requiredShip<?= $row_id; ?>" type = "text" id = 'street1<?= $row_id; ?>' style = "width: 250px" ></td>
					</tr>
					<tr class = 'shipping_row' id = 'shipping_row<?= $row_id; ?>_2'>
						<td>Address 2: </td>
						<td class = "partRequestTD"><input type = "text" id = 'street2<?= $row_id; ?>' style = "width: 250px" ></td>
					</tr>
					<tr class = 'shipping_row' id = 'shipping_row<?= $row_id; ?>_3'>
						<td>*City: </td>
						<td class = "partRequestTD"><input class = "requiredShip<?= $row_id; ?>" type = "text" id = 'city<?= $row_id; ?>' style = "width: 250px" ></td>
					</tr>
					<tr class = 'shipping_row' id = 'shipping_row<?= $row_id; ?>_4'>
						<td>*State: </td>
						<td class = "partRequestTD">
							<select class = "requiredShip<?= $row_id; ?> custom-select" id = 'state<?= $row_id; ?>' style = "width: 260px">
									<option></option>
									<?php
										for ($i = 0; $i < sizeof($states); $i++){

									?>

									<option><?= $states[$i]; ?></option>

									<?php
										}
									?>
							</select>
						</td>
					</tr>
					<tr class = 'shipping_row' id = 'shipping_row<?= $row_id; ?>_5'>
						<td>*Zip: </td>
						<td class = "partRequestTD"><input class = "requiredShip<?= $row_id; ?>" type = "text" id = 'zip<?= $row_id; ?>' style = "width: 250px" ></td>
					</tr>
					<tr>
						<td>*Due By: </td>
						<td class = "partRequestTD">
							<input class = "requiredPR<?= $row_id; ?>" type = "date" id = 'date_required<?= $row_id; ?>' style = "width: 253px" >
							<input type = 'checkbox' id = 'early_delivery<?= $row_id; ?>'> <i>Early Delivery Accepted?</i>
						</td>
						
					</tr>
					<tr>
						<td>Attachment 1: </td>
						<td><input type="file" id="attachment<?= $row_id; ?>-1" onchange = 'z.show_attachment(<?= $row_id; ?>, 1)'></td>
					</tr>
					<tr id = 'att_row<?= $row_id; ?>-2' style = 'visibility: collapse'>
						<td>Attachment 2: </td>
						<td><input type="file" id="attachment<?= $row_id; ?>-2" onchange = 'z.show_attachment(<?= $row_id; ?>, 2)'></td>
					</tr>
					<tr id = 'att_row<?= $row_id; ?>-3' style = 'visibility: collapse'>
						<td>Attachment 3: </td>
						<td><input type="file" id="attachment<?= $row_id; ?>-3"></td>
					</tr>
					<tr>
						<td>*Add CC: </td>
						<td class = "partRequestTD">
							<textarea class = "requiredPR<?= $row_id; ?> emails" id = 'email_cc<?= $row_id; ?>' style = "width: 500px; height: 50px; resize: vertical" ></textarea>
							<!--<input class = "requiredPR<?= $row_id; ?> emails" type = "text" id = 'email_cc<?= $row_id; ?>' style = "width: 250px" >!-->
						</td>
					</tr>
					<tr>
						<td>Notes: </td>
						<td class = "partRequestTD">
							<textarea id = 'notes<?= $row_id; ?>' style = "width: 500px; height: 50px; resize: vertical" ></textarea>
						</td>
					</tr>
					
					<tr>
						<td>*MO Line Items </td>
						<td class = "partRequestTD"><input type = 'number' class = "requiredPR<?= $row_id; ?>" id = 'line_items<?= $row_id; ?>' style = "width: 250px" ></td>
						
					</tr>
					<tr>
						<td>Has Reels? </td>
						<td class = "partRequestTD">
							<input type = 'checkbox' id = 'hasReels<?= $row_id; ?>'>
						</td>
					</tr>
					<tr>
						<td>Amending MO: </td>
						<td class = "partRequestTD">
							<input type = 'checkbox' id = 'amend<?= $row_id; ?>' onchange = 'z.show_reason("amend", <?= $row_id; ?>)'>
						</td>
					</tr>
					<tr style = 'visibility: collapse' id = 'amend_row<?= $row_id; ?>'>
						<td>*Amending Reason: </td>
						<td class = "partRequestTD">
							<select class = "custom-select" id = 'amend_reason<?= $row_id; ?>' style = "width: 260px">
								<option></option>
								<option>Cut lengths won't work for field team (we have the quantity in the system but pieces)</option>
								<option>Changes to the job (item removed/changed)</option>
								<option>Warehouse didn't have enough stock</option>
								<option>Field Team / PM requested a quantity change</option>
								<option>Changes to quantity (other reason)</option>
							</select>
						</td>
					</tr>
					<tr>
						<td>Reship Request: </td>
						<td class = "partRequestTD">
							<input type = 'checkbox' id = 'reship<?= $row_id; ?>' onchange = 'z.show_reason("reship", <?= $row_id; ?>)'>
						</td>
					</tr>
					<tr style = 'visibility: collapse' id = 'reship_row<?= $row_id; ?>'>
						<td>*Reship Reason: </td>
						<td class = "partRequestTD">
							<select class = "custom-select" id = 'reship_reason<?= $row_id; ?>' style = "width: 260px">
								<option></option>
								<option>Address change</option>
								<option>Team decided to ship to a different address but didn't let us know</option>
								<option>Carrier lost item (shipment lost)</option>
								<option>Needed item faster</option>
								<option>MFG labeled component incorrectly</option>
								<option>Receiving Team misplaced the item(s)</option>
							</select>
						</td>
					</tr>

				</table>

			<?php

			}

			?>

			<div style = 'padding-bottom: 10em;'></div>	

		</div>
	</div>
	
	<div id = 'PO' class = 'tabcontent' style = 'display: none'>
		<h1> Purchase Order Screen </h1>

		<div>

			<input type="button" value="Submit Purchase Order Info" onclick="z.submit_po()" id = 'submit_button_po' style ='margin-top: 1em'/>

			<?php

			//make PO id's next avaialbe in the sequence
			//set limit to row_id + 1 (only created one table)
			$next_row = $row_id + 1;
			
			//keep loop (only going to create one row, just in case we want more in the future)
			for ($row_id; $row_id < $next_row; $row_id++){

				$display = "display:none";
				$optional = "(Optional)";
				$render_button = "<button onclick = 'z.show_hide(" . $row_id . ")' id = 'button_" . $row_id . "'>+</button>    ";

				//first row, don't show the table, don't list as optional, don't render button
				if ($row_id == $next_row - 1){
					$display = "";
					$optional = "";
					$render_button = "";
				}


			?>
				<h3><?= $render_button; ?>Purchase Order Info<?= $optional; ?></h3>
				<table class = "pr_tables" id = "po_table<?= $row_id; ?>" style = "line-height:20px;<?= $display; ?>">
					<tr>
						<td>*Project #: </td>
						<td class = "partRequestTD"><input class = "requiredPR<?= $row_id; ?>" type = "text" id = 'project_id<?= $row_id; ?>' style = "width: 250px" ></td>
					</tr>
					<tr>
						<td>*Requested By: </td>
						<td class = "partRequestTD">
							<select class = "requiredPR<?= $row_id; ?> custom-select" id = 'requested_by<?= $row_id; ?>' style = "width: 260px" >
									<option></option>
									<option value = 'Other'>Other</option>
									<?php
										//loop through employee directory
										for ($i = 0; $i < sizeof($directory); $i++){

									?>

									<option value = "<?= $directory[$i]; ?>"> <?= $directory[$i] ?></option>

									<?php
										}
									?>
							</select>
						</td>

					</tr>
					<tr>
						<td>*Ship To: </td>
						<td class = "partRequestTD">
							<select class = "requiredPR<?= $row_id; ?> custom-select" id = 'ship_to<?= $row_id; ?>' style = "width: 260px" onchange = 'z.ship_to_toggle(this.value, <?= $row_id; ?>)'>
									<option></option>
									<option value = 'Other'>Other</option>
									<?php
										//run query to grab locations
										$shippingLocQ = "SELECT * from general_shippingadd where customer = 'PW' order by name";
										$result = mysqli_query($con, $shippingLocQ);

										//init arrays
										$locations = [];
										$street = [];
										$city = [];
										$state = [];
										$zip = [];

										while($rows = mysqli_fetch_assoc($result))
										{
										array_push($locations, $rows['name']);	
										array_push($street, $rows['address']);	
										array_push($city, $rows['city']);	
										array_push($state, $rows['state']);	
										array_push($zip, $rows['zip']);	

									?>

									<option value = "<?= $rows['name']; ?>"> <?= $rows['name'] ?></option>

									<?php
										}
									?>
							</select>
						</td>

					</tr>
					<tr class = 'shipping_row' id = 'shipping_row<?= $row_id; ?>_1'>
						<td>*Address 1: </td>
						<td class = "partRequestTD"><input class = "requiredShip<?= $row_id; ?>" type = "text" id = 'street1<?= $row_id; ?>' style = "width: 250px" ></td>
					</tr>
					<tr class = 'shipping_row' id = 'shipping_row<?= $row_id; ?>_2'>
						<td>Address 2: </td>
						<td class = "partRequestTD"><input type = "text" id = 'street2<?= $row_id; ?>' style = "width: 250px" ></td>
					</tr>
					<tr class = 'shipping_row' id = 'shipping_row<?= $row_id; ?>_3'>
						<td>*City: </td>
						<td class = "partRequestTD"><input class = "requiredShip<?= $row_id; ?>" type = "text" id = 'city<?= $row_id; ?>' style = "width: 250px" ></td>
					</tr>
					<tr class = 'shipping_row' id = 'shipping_row<?= $row_id; ?>_4'>
						<td>*State: </td>
						<td class = "partRequestTD">
							<select class = "requiredShip<?= $row_id; ?> custom-select" id = 'state<?= $row_id; ?>' style = "width: 260px">
									<option></option>
									<?php
										for ($i = 0; $i < sizeof($states); $i++){

									?>

									<option><?= $states[$i]; ?></option>

									<?php
										}
									?>
							</select>
						</td>
					</tr>
					<tr class = 'shipping_row' id = 'shipping_row<?= $row_id; ?>_5'>
						<td>*Zip: </td>
						<td class = "partRequestTD"><input class = "requiredShip<?= $row_id; ?>" type = "text" id = 'zip<?= $row_id; ?>' style = "width: 250px" ></td>
					</tr>
					<tr>
						<td>*Due By: </td>
						<td class = "partRequestTD">
							<input class = "requiredPR<?= $row_id; ?>" type = "date" id = 'date_required<?= $row_id; ?>' style = "width: 253px" >
							<input type = 'checkbox' id = 'early_delivery<?= $row_id; ?>'> <i>Early Delivery Accepted?</i>
						</td>
						
					</tr>
					<tr>
						<td>Attachment 1: </td>
						<td><input type="file" id="attachment<?= $row_id; ?>-1" onchange = 'z.show_attachment(<?= $row_id; ?>, 1)'></td>
					</tr>
					<tr id = 'att_row<?= $row_id; ?>-2' style = 'visibility: collapse'>
						<td>Attachment 2: </td>
						<td><input type="file" id="attachment<?= $row_id; ?>-2" onchange = 'z.show_attachment(<?= $row_id; ?>, 2)'></td>
					</tr>
					<tr id = 'att_row<?= $row_id; ?>-3' style = 'visibility: collapse'>
						<td>Attachment 3: </td>
						<td><input type="file" id="attachment<?= $row_id; ?>-3"></td>
					</tr>
					<tr>
						<td>*Add CC: </td>
						<td class = "partRequestTD">
							<textarea class = "requiredPR<?= $row_id; ?> emails" id = 'email_cc<?= $row_id; ?>' style = "width: 500px; height: 50px; resize: vertical" ></textarea>
						</td>
					</tr>
					<tr>
						<td>Notes: </td>
						<td class = "partRequestTD">
							<textarea id = 'notes<?= $row_id; ?>' style = "width: 500px; height: 50px; resize: vertical" ></textarea>
						</td>
					</tr>

				</table>

			<?php

			}

			?>

			<div style = 'padding-bottom: 10em;'></div>	

		</div>
	</div>
	
	<!-- enternally defined js libraries -->
	<!-- enables ajax use -->
	<script	src = "https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
	<script src="https://apis.google.com/js/platform.js?onload=init" async defer></script>
	
	<!-- allows jquery use -->
	<script src="https://code.jquery.com/jquery-1.12.4.js"></script>
	<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
	
	<!-- internally defined js files -->
	<script src="javascript/js_helper.js?<?= $version ?>-1"></script>
	<script src="javascript/fst_js_functions.js"></script>
	<script src = "javascript/utils.js"></script>
	
	<script>

		//Namespace
		var z = {}
		
		//read in location to names and address
		var locations = <?= json_encode($locations); ?>, 
			street = <?= json_encode($street); ?>, 
			city = <?= json_encode($city); ?>, 
			state = <?= json_encode($state); ?>, 
			zip = <?= json_encode($zip); ?>;
		
		//used to load email tags
		var availableTags = <?= json_encode($emails); ?>;
		
		//init current update id (used to update existing)
		var update_id = null;
		
		//pass project array
		var pq_array = <?= json_encode($pq_array); ?>;
		
		//handles csv output for BOM
		function export_analytics(){
			
			var regexp = new RegExp('#','g');
			
			//initialize csvContent to export csv
			let csvContent = "data:text/csv;charset=utf-8,";
			
			// add headers to CSV
			csvContent += "(PM/WO),Requestor,Project Number,Status,Date Requested\r\n";
						
			//loop through bill of materials and add each part
			for (var i = 0; i < pq_array.length; i++){
				
				//convert time to local
				pq_array[i].requested = z.utc_to_local(pq_array[i].requested);
				
				//remove comma's and #'s from strings
				pq_array[i].type = scrub_string(pq_array[i].type);
				pq_array[i].requested_by = scrub_string(pq_array[i].requested_by);
				pq_array[i].project_id = scrub_string(pq_array[i].project_id);
				pq_array[i].urgency = scrub_string(pq_array[i].urgency);
				pq_array[i].requested = scrub_string(pq_array[i].requested);
				
					
				//used to remove unfriendly characters
				/*
				part = part.replace(regexp, '');
				part = part.replace(/,/g, ';');
				quantity = quantity.replace(regexp, '');
				quantity = quantity.replace(/,/g, ';');
				description = description.replace(regexp, '');
				description = description.replace(/,/g, ';');
				*/
				
				//set csv content
				csvContent+= pq_array[i].type + ',';
				csvContent+= pq_array[i].requested_by + ",";
				csvContent+= pq_array[i].project_id + ',';				
				csvContent+= pq_array[i].urgency + ",";
				csvContent+= pq_array[i].requested;
				csvContent+= '\r\n';
				
			}
			
			
			//set encoded uri for download, push to link, name and force download
			var encodedUri = encodeURI(csvContent);
			var link = u.eid("hold_pq_info");
			var today = date_string();
			console.log(today);
			link.setAttribute("href", encodedUri);			
			link.setAttribute("download", "PQ Analytics (" + today + ").csv");
			link.click();
		}
		
		//formats UTC time to locat
		z.utc_to_local = function(date){
			
			var date_local = new Date(date + ' UTC');
			var date_utc = new Date(date);
			
			//date & time, convert to central time zone
			var y = date_local.getFullYear(),
				m = date_local.getMonth() + 1,
				d = date_local.getDate(),
				hours = date_local.getHours(),
				minutes = date_local.getMinutes();

			var time = z.time_format(hours, minutes);

			return m + "/" + d + "/" + y + " " + time;
		}
		
		//changes military time to standard
		z.time_format = function(hours, minutes){
			
			//init time to be returned
			var timeValue;
			
			//use hours to check if this needs to be am or pm
			if (hours > 0 && hours <= 12) {
			  timeValue= "" + hours;
			} 
			else if (hours > 12) {
			  timeValue= "" + (hours - 12);
			} 
			else if (hours == 0) {
			  timeValue= "12";
			}
			
			timeValue += (minutes < 10) ? ":0" + minutes : ":" + minutes;  // get minutes
			timeValue += (hours >= 12) ? " PM" : " AM";  // get AM/PM

			// return value
			return timeValue;
		}
		
		//grabs nicely formatted date
		function date_string(){
			var t = new Date();
			var day = t.getDate();
			var month = t.getMonth() + 1;
			var year = t.getFullYear();
			
			return month + "-" + day + "-" + year;
		}
		
		//handle sanitizing string
		function scrub_string(targ){
			
			//check if blank
			if (targ == "" || targ == null)
				return targ;
			
			//used to remove unfriendly characters
			var regexp = new RegExp('#','g');
			targ = targ.replace(regexp, '');
			targ = targ.replace(/,/g, ';');
			
			return targ;
		}
		
		//function to fill in values for greensheets
		function greensheet_fill(id){
			
			//if checked, fill, if not, clear
			if (u.eid("greensheet" + id).checked){
			
				//set header info based on MO #1
				u.eid("project_id" + id).value = u.eid("project_id1").value;
				u.eid("requested_by" + id).value = u.eid("requested_by1").value;
				u.eid("ship_to" + id).value = "Greensheet";
				u.eid("date_required" + id).valueAsDate = new Date();
				u.eid("notes" + id).value = "GREENSHEET";
				
				//close out ship_to rows
				z.ship_to_toggle("", id);
			}
			else{
				//clear out header information
				u.eid("project_id" + id).value = "";
				u.eid("requested_by" + id).value = "";
				u.eid("ship_to" + id).value = "";
				u.eid("date_required" + id).value = "";
				u.eid("email_cc" + id).value = "";
				
				//close out ship_to rows
				z.ship_to_toggle("", id);
				u.eid("street1" + id).value = "";
				u.eid("street2" + id).value = "";
				u.eid("city" + id).value = "";
				u.eid("state" + id).value = "";
				u.eid("zip" + id).value = "";
				u.eid("notes" + id).value = "";
			}
			
		}
		
		//function that takes header information from MO #1 and places it in the target material order
		function use_header(id){
			
			//if the checkbox value is true, update to MO #1 values, else clear the fields
			if (u.eid("copy_header" + id).checked){
			
				//set header info based on MO #1
				u.eid("project_id" + id).value = u.eid("project_id1").value;
				u.eid("requested_by" + id).value = u.eid("requested_by1").value;
				u.eid("ship_to" + id).value = u.eid("ship_to1").value;
				u.eid("date_required" + id).value = u.eid("date_required1").value;
				u.eid("email_cc" + id).value = u.eid("email_cc1").value;
				
				//if ship_to = other, then bring other fields over as well
				if (u.eid("ship_to" + id).value == "Other"){
					z.ship_to_toggle("Other", id);
					u.eid("street1" + id).value = u.eid("street11").value;
					u.eid("street2" + id).value = u.eid("street21").value;
					u.eid("city" + id).value = u.eid("city1").value;
					u.eid("state" + id).value = u.eid("state1").value;
					u.eid("zip" + id).value = u.eid("zip1").value;
				}
			}
			else{
				//clear out header information
				u.eid("project_id" + id).value = "";
				u.eid("requested_by" + id).value = "";
				u.eid("ship_to" + id).value = "";
				u.eid("date_required" + id).value = "";
				u.eid("email_cc" + id).value = "";
				
				//close out ship_to rows
				z.ship_to_toggle("", id);
				u.eid("street1" + id).value = "";
				u.eid("street2" + id).value = "";
				u.eid("city" + id).value = "";
				u.eid("state" + id).value = "";
				u.eid("zip" + id).value = "";
			}
		}
		
		//handles showing reason dropdowns if selected
		//targ = reship amend (same functionallity, different id's used)
		//index = 1-5 (related to material order clicked)
		z.show_reason = function(targ, index){
			
			//check value to see if we need to show or hide
			//show if it is checked
			if (u.eid(targ + index).checked)
				u.eid(targ + "_row" + index).style.visibility = "visible";
			//hide if not checked
			else
				u.eid(targ + "_row" + index).style.visibility = "collapse";

		}
		
		//handles toggle ship-to rows
		z.ship_to_toggle = function(opt, num){
			//hold display option
			var display;
			
			//if other, then show toggle
			if (opt == "Other")
				display = "visible";
			else
				display = "collapse";
				
			//cycle through shipping rows and show/hide depending on opt
			for (var i = 1; i < 6; i++){
				u.eid("shipping_row" + num + "_" + i).style.visibility = display;					

			}
		}
		
		//handles showing and hiding table
		z.show_hide = function(id){
			
			var check = u.eid("button_" + id).innerHTML;
			if (check == "+"){
				u.eid("button_" + id).innerHTML = "-";
				u.eid("pr_table" + id).style.display = "block";
			}
			else{
				u.eid("button_" + id).innerHTML = "+";
				u.eid("pr_table" + id).style.display = "none";
			}
			
			
		}
		
		//handles showing additional attachments as you add them
		z.show_attachment = function(row, id){
			
			//grab current attachment
			var check_file = u.eid("attachment" + row + "-" + id).value;
						
			//check if not blank
			if (check_file != ""){
				id++;
				u.eid("att_row" + row + "-" + id).style.visibility = 'visible';
			}
			
		}
		
		//handles checking if required fields have been entered
		z.submit_check = function(class_id){
			
			//decision variable, passed back at the end of the function
			var desc = true;
			
			//cycle through class, if anything is blank, highlight yellow and return false
			$(".requiredPR" + class_id).each(function(){
				
				//first turn to blue background
				this.classList.remove("required_error");
				
				// Test if the element is empty, if so turn it yellow, mark desc as false
				if (!$(this).val()){
					this.classList.add("required_error");
					desc = false;
				}
			});
			
			//check to see if ship to is "other"
			if (u.eid("ship_to" + class_id).value == "Other"){
				//cycle through class, if anything is blank, highlight yellow and return false
				$(".requiredShip" + class_id).each(function(){

					//first turn to blue background
					$(this).css("background", good_color);

					// Test if the element is empty, if so turn it yellow, mark desc as false
					if (!$(this).val()){
							$(this).css("background", bad_color);
							desc = false;
					}
				});
			}
			
			//if this is a mo, we need to check the amend and reship buttons
			if (class_id < 6){

				//check to see if amend or reship is checked. If so verify that we have a reason select for both
				if (u.eid("amend" + class_id).checked){

					if (u.eid("amend_reason" + class_id).value == ""){
						u.eid("amend_reason" + class_id).style.background = bad_color;
						desc = false;
					}
					else{
						u.eid("amend_reason" + class_id).style.background = good_color;
					}
				}

				if (u.eid("reship" + class_id).checked){

					if (u.eid("reship_reason" + class_id).value == ""){
						u.eid("reship_reason" + class_id).style.background = bad_color;
						desc = false;
					}
					else{
						u.eid("reship_reason" + class_id).style.background = good_color;
					}
				}
			}
			
			//desc = true;
			
			//let user know if there was an error
			if (!desc && class_id < 6)
				alert("There has been an error with Material Order #" + class_id + ". Required fields have been highlighted in yellow.");
			else if (!desc)
				alert("There has been an error with the Purchase Order info. Required fields have been highlighted in yellow.");
						
			//return result
			return desc;
			
		}
		
		//used to send info over to fst_allocations_mos (MOs)
		z.submit_mo = function(){
						
			//init array to hold tables we need to check
			var MO_IDs = [];
			var check = null;
			var test;
			
			//loop through all MO's and decide what will be used
			//not dynamic, currently only 5 MO tables to check
			for (var i = 1; i < 6; i++){
				
				if (i == 1){
					//add id to MO_IDs array (used to pass info to ajax)
					MO_IDs.push(i);
					
					//check required fields, if we hit an error, reject
					if(!z.submit_check(i))
						return;
				}
				else{
					//check operator sign in button (+ = no, - = yes)
					if (u.eid("button_" + i).innerHTML == "-"){
						
						//add id to MO_IDs array (used to pass info to ajax)
						MO_IDs.push(i);

						//check required fields, if we hit an error, reject
						if(!z.submit_check(i))
							return;
					}
					
				}
					
			}	
			
			//initialize arrays that will pass information to ajax
			var project_id = [],
				mo_id = [],
				requested_by = [],
				ship_from = [],
				ship_to = [],
				mo_street = [],
				mo_city = [], 
				mo_state = [], 
				mo_zip = [],
				date_required = [], 
				early_delivery = [],
				cc = [],
				notes = [], 
				attachments = [], 
				mo_lines = [],
				has_reels = [],
				amending_mo = [], 
				reship_request = [];
			
			//used to hold address index if needed
			var index = [];
			var address_index; 
			
			//initalize form data (will carry all form data over to server side)
			var fd = new FormData();
			
			//loop through MO_IDs array, send any relevant info to the ajax server			
			for (var i = 0; i < MO_IDs.length; i++){
				
				//push values to array 
				project_id.push(u.eid("project_id" + MO_IDs[i]).value);
				mo_id.push(u.eid("mo_id" + MO_IDs[i]).value);
				requested_by.push(u.eid("requested_by" + MO_IDs[i]).value);
				ship_from.push(u.eid("ship_from" + MO_IDs[i]).value);
				ship_to.push(u.eid("ship_to" + MO_IDs[i]).value);
				
				//if ship_to is other, grab address, if not, read in address from arrays
				if (u.eid("ship_to" + MO_IDs[i]).value == "Other"){
					//create street variable (street 1 + 2)
					var street_name = u.eid("street1" + MO_IDs[i]).value + " " + u.eid("street2" + MO_IDs[i]).value
					mo_street.push(street_name.trim());
					mo_city.push(u.eid("city" + MO_IDs[i]).value);
					mo_state.push(u.eid("state" + MO_IDs[i]).value);
					mo_zip.push(u.eid("zip" + MO_IDs[i]).value);
					
				}
				else if(u.eid("ship_to" + MO_IDs[i]).value == "Greensheet"){
					mo_street.push("");
					mo_city.push("");
					mo_state.push("");
					mo_zip.push("");
				}
				else{
					//get index
					address_index = locations.findIndex(element => element == u.eid("ship_to" + MO_IDs[i]).value); 
					mo_street.push(street[address_index]);
					mo_city.push(city[address_index]);
					mo_state.push(state[address_index]);
					mo_zip.push(zip[address_index]);
				}
				
				date_required.push(u.eid("date_required" + MO_IDs[i]).value);
				
				//check early delivery check, save as yes or no
				if (u.eid("early_delivery" + MO_IDs[i]).checked)
					early_delivery.push("Yes");
				else
					early_delivery.push("No");
				
				//check has reels as well
				if (u.eid("hasReels" + MO_IDs[i]).checked)
					has_reels.push("Yes");
				else
					has_reels.push("No");
				
				cc.push(u.eid("email_cc" + MO_IDs[i]).value);
				notes.push(u.eid("notes" + MO_IDs[i]).value);

				//grab any files if applicable
				var temp_attachments = [];
				
				for (var j = 1; j < 4; j++){
					if(u.eid("attachment" + MO_IDs[i] + "-" + j).files.length > 0){
						
						var file = $("#attachment" + MO_IDs[i] + "-" + j)[0].files[0];
						fd.append('file' + MO_IDs[i] + "-" + j, file);
						
						//save name in attachments array
						temp_attachments.push(MO_IDs[i] + "-" + j);
					}
					else{
						temp_attachments.push("");
					}
				}
				
				//push any attachments found to actual attachments array
				attachments.push(temp_attachments);
				
				//add MO line items
				mo_lines.push(u.eid("line_items" + MO_IDs[i]).value);
				
				//if amend or reship are checked, add the reason, if not, add a blank
				if (u.eid("amend" + MO_IDs[i]).checked)
					amending_mo.push(u.eid("amend_reason" + MO_IDs[i]).value);
				else
					amending_mo.push("");
				
				if (u.eid("reship" + MO_IDs[i]).checked)
					reship_request.push(u.eid("reship_reason" + MO_IDs[i]).value);
				else
					reship_request.push("");
				
				
			}
			
			//serialize arrays and pass them to fd 
			fd.append('project_id', JSON.stringify(project_id));
			fd.append('mo_id', JSON.stringify(mo_id));
			fd.append('requested_by', JSON.stringify(requested_by));
			fd.append('ship_from', JSON.stringify(ship_from));
			fd.append('ship_to', JSON.stringify(ship_to));
			fd.append('street', JSON.stringify(mo_street));
			fd.append('city', JSON.stringify(mo_city));
			fd.append('state', JSON.stringify(mo_state));
			fd.append('zip', JSON.stringify(mo_zip));
			fd.append('date_required', JSON.stringify(date_required));
			fd.append('early_delivery', JSON.stringify(early_delivery));
			fd.append('cc', JSON.stringify(cc));
			fd.append('notes', JSON.stringify(notes));
			fd.append('attachments', JSON.stringify(attachments));
			fd.append('mo_lines', JSON.stringify(mo_lines));
			fd.append('has_reels', JSON.stringify(has_reels));
			fd.append('amending_mo', JSON.stringify(amending_mo));
			fd.append('reship_request', JSON.stringify(reship_request));
			
			//add tell variable
			fd.append('tell', 'process_mo');
			fd.append('type', 'MO');
			
			//lock submit button before ajax call
			u.eid("submit_button_mo").disabled = true;
						
				$.ajax({
					url: 'MO_handler.php',
					type: 'POST',
					processData: false,
					contentType: false,
					data: fd,
					success: function (response) {
						
						//check for error (if response says anything)
						if (response != ""){
							alert(response);
							
						}
						else{
							alert("The submitted Material Order(s) has been processed successfully.");
							window.location.reload();
						}
												
					}
				});
			
			//unlock after ajax (only relevant if we have an error and the MO is rejected)
			u.eid("submit_button_mo").disabled = false;
						
		}
		
		//used to send info over to fst_allocations_mos (POs)
		z.submit_po = function(){
			
			//init test var (used in ajax call)
			var test;
			
			//set po_index (could change if we add or subtract # of pos and mos)
			var po_index = "6";
			
			//check PO entries
			if (!z.submit_check(po_index))
				return;
			
			//initialize vars that will pass information to ajax
			var project_id,
				requested_by,
				ship_to,
				mo_street,
				mo_city, 
				mo_state, 
				mo_zip,
				date_required, 
				early_delivery,
				cc,
				attachments = [], 
				notes;
			
			//used to hold address index if needed
			var index = [];
			var address_index; 
			
			//initalize form data (will carry all form data over to server side)
			var fd = new FormData();

			//push values to array 
			project_id = u.eid("project_id" + po_index).value;
			requested_by = u.eid("requested_by" + po_index).value;
			ship_to = u.eid("ship_to" + po_index).value;

			//if ship_to is other, grab address, if not, read in address from arrays
			if (u.eid("ship_to" + po_index).value == "Other"){
				//create street variable (street 1 + 2)
				var street_name = u.eid("street1" + po_index).value + " " + u.eid("street2" + po_index).value
				mo_street = street_name.trim();
				mo_city = u.eid("city" + po_index).value;
				mo_state = u.eid("state" + po_index).value;
				mo_zip = u.eid("zip" + po_index).value;

			}
			else{
				//get index
				address_index = locations.findIndex(element => element == u.eid("ship_to" + po_index).value); 
				mo_street = street[address_index];
				mo_city = city[address_index];
				mo_state = state[address_index];
				mo_zip = zip[address_index];
			}

			date_required = u.eid("date_required" + po_index).value;

			//check early delivery check, save as yes or no
			if (u.eid("early_delivery" + po_index).checked)
				early_delivery = "Yes";
			else
				early_delivery = "No";

			//set cc field & notes
			cc = u.eid("email_cc" + po_index).value;
			notes = u.eid("notes" + po_index).value;
			
			//grab any files if applicable
			var temp_attachments = [];

			for (var j = 1; j < 4; j++){
				if(u.eid("attachment" + po_index + "-" + j).files.length > 0){

					var file = $("#attachment" + po_index + "-" + j)[0].files[0];
					fd.append('file' + po_index + "-" + j, file);

					//save name in attachments array
					temp_attachments.push(po_index + "-" + j);
				}
				else{
					temp_attachments.push("");
				}
			}

			//push any attachments found to actual attachments array
			attachments.push(temp_attachments);
				
			//serialize arrays and pass them to fd 
			fd.append('project_id', project_id);
			fd.append('requested_by', requested_by);
			fd.append('ship_to', ship_to);
			fd.append('street', mo_street);
			fd.append('city', mo_city);
			fd.append('state', mo_state);
			fd.append('zip', mo_zip);
			fd.append('date_required', date_required);
			fd.append('early_delivery', early_delivery);
			fd.append('cc', cc);
			fd.append('notes', notes);
			fd.append('attachments', JSON.stringify(attachments));
			
			//add tell
			fd.append('tell', 'process_mo');
			fd.append('type', 'PO');
			
			//lock submit button before ajax call
			u.eid("submit_button_po").disabled = true;
						
				$.ajax({
					url: 'MO_handler.php',
					type: 'POST',
					processData: false,
					contentType: false,
					data: fd,
					success: function (response) {
					
						//check for error (if response says anything)
						if (response != ""){
							alert(response);
							
						}
						else{
							alert("The submitted Purchase Order has been processed successfully.");
							window.location.reload();
						}
												
					}
				});
			
			//unlock after ajax (only relevant if we have an error and the PO is rejected)
			u.eid("submit_button_po").disabled = false;
						
		}
		
		//function designed to insert current email address onto the most recent line selected on parts request form
		z.insertEmail = function(){
			var email = u.eid("insert_email").value,
				recent = u.eid("email_" + email_recent).value;
			
			if (recent == ""){
				recent = email;
			}
			
			else{
				recent = recent + "; " + email;
			}
			
			u.eid("email_" + email_recent).value = recent;
		}
		
		//handles shipping location dropdown and event
		z.shippingSelect = function(address){
			var location = [], tempLen1 = 1, currSpot = 0, tempLen2;
			
			if (address == ""){
				u.eid("shipping_address").value = "";
				u.eid("shipping_city").value = "";
				u.eid("shipping_state").value = "";
				u.eid("shipping_zip").value = "";
				u.eid("shipping_location").value = "";
				
			}
			
			else{

				while (tempLen1 > 0 && currSpot < 7){
					tempLen2 = address.indexOf("|", tempLen1)
					location[currSpot] = address.substring(tempLen1, tempLen2);
					tempLen1 = tempLen2 + 1;
					currSpot++;
				}
				
				u.eid("shipping_address").value = location[2];
				u.eid("shipping_city").value = location[3];
				u.eid("shipping_state").value = location[4];
				u.eid("shipping_zip").value = location[5];
				u.eid("shipping_location").value = location[6];
				
				
			}
			
		}
		
		//opens up schedule fields if yes on schedule has been selected
		z.scheduleSelect = function(check){
			console.log(check);
			if (check == "Y"){
				u.eid("schedRow1").style.visibility = "visible",
				u.eid("schedRow2").style.visibility = "visible";
					
			}
			else{
				u.eid("schedRow1").style.visibility = "collapse",
				u.eid("schedRow2").style.visibility = "collapse";
				
			}
			
		}
		
		//function designed to hold most recent value selected in the parts request form (init to cc since we anticipate this being used the most)
		z.recent = function(id){
			u.eid("email_to").style.borderWidth = "medium";
			u.eid("email_cc").style.borderWidth = "medium";
			u.eid("email_bcc").style.borderWidth = "medium";
			
			u.eid("email_" + id).style.borderWidth = "thick";
			email_recent = id;
			
		}
		
		var total_parts = 0;
		var new_part_info = [];
		var curr_part_index = null;
		
		//handles parts request form check (if both rows filled, add row)
		z.form_check = function(targ){
			
			//if this is a new part, check to make sure info is filled out, then push to array
			if (targ == "new"){
				var temp_arr = []; //holds temp array that will be pushed to new part_info if we pass checks
				
				//check all fields (ignore vendor of origin)
				for (var i = 0; i < new_part_ids.length; i++){
					if (new_part_ids[i] == "vendor" || new_part_ids[i] == "laborRate"){
						//exempt from checks
					}
					else if (u.eid("newPart_" + new_part_ids[i]).value == ""){
						alert (new_part_ids[i] + " needs to be filled out.");
						return;
					}
					temp_arr.push(u.eid("newPart_" + new_part_ids[i]).value);
				}
				
				//if part# is over 20 characters, need to check abbreviation field and add to array
				if (temp_arr[0].length > 20){
					if (u.eid("newPart_abv").value == ""){
						alert ("Abbreviation needs to be filled out.");
						return;
					}
					//add new part id to array, pass back to parts request form
					temp_arr.push(u.eid("newPart_abv").value);
					u.eid("extra_pn" + curr_part_index).value = u.eid("newPart_abv").value;
				}
				else{
					temp_arr.push("");
				}
				
				//if we pass, then save to new array and proceed
				new_part_info.push(temp_arr);
				
				//close new part info dialog, turn color of part to blue, check requested
				alert("The part information has been saved. \nNote: This info will be lost if you close out of this page.");
				$( "#workOrders-dialog-newPart" ).dialog('close');
				u.eid("extra_pn" + curr_part_index).style.backgroundColor = "#BBDFFA";
				u.eid("extra_checked" + curr_part_index).checked = true;
				
				//check to see if we need to add new row
				z.checkAdd(curr_part_index);
				
			}
			//else, we need to do normal form check
			else{


				var part = u.eid("extra_pn" + targ).value;
				var index = z.findPart(part);
				
				//if part is empty, ignore
				if (part == "")
					return;
				
				//debugger;

				if (index == -1){
					
					//check to see if we have saved this info already
					for (var i = 0; i < new_part_info.length; i++){
						if (new_part_info[i][0] == part){
							u.eid("extra_pn" + targ).style.backgroundColor = "#BBDFFA";
							z.checkAdd(targ);
							return;
						}
						if (new_part_info[i][6] == part){
							u.eid("extra_pn" + targ).style.backgroundColor = "#BBDFFA";
							z.checkAdd(targ);
							return;
						}
					}
					
					//no match found, this part is not in our catalog
					u.eid("extra_pn" + targ).style.backgroundColor = "red";
					u.eid("extra_stock" + targ).innerHTML = null;

					//uncheck request (only allowed once we have saved info into array)
					u.eid("extra_checked" + targ).checked = false;

					//move new part over to new form
					u.eid("newPart_" + new_part_ids[0]).value = part;

					//clear existing new part info (start at 1 since we don't want to remove part id)
					for (var i = 1; i < new_part_ids.length; i++){
						u.eid("newPart_" + new_part_ids[i]).value = null;
					}
					
					//save new part index
					curr_part_index = targ;
					
					//set abbrevation to null	
					u.eid("newPart_abv").value = null;

					//open new part dialog
					$( "#workOrders-dialog-newPart" ).dialog({
						width: "auto",
						height: "auto",
						dialogClass: "fixedDialog",
					});
					
					//check to see how long the current part is longer than 20 characters
					if (part.length > 20){
						u.eid("newPart_abv_row1").style.visibility = 'visible';
						u.eid("newPart_abv_row2").style.visibility = 'visible';
						u.eid("newPart_abv_row3").style.visibility = 'visible';
						
						//change verbiage of part ID td
						u.eid("newPart_partID").innerHTML = "Part ID (full part): "
						
						//select next element
						u.eid("newPart_abv").select();
					}
					else{
						//change verbiage of part ID td
						u.eid("newPart_partID").innerHTML = "Part ID: ";
						
						u.eid("newPart_abv_row1").style.visibility = 'collapse';
						u.eid("newPart_abv_row2").style.visibility = 'collapse';
						u.eid("newPart_abv_row3").style.visibility = 'collapse';
						//select next element
						u.eid("newPart_" + new_part_ids[1]).select();
					}
					
				}
				else{
					//we found a match, list out what we have in stock
					u.eid("extra_pn" + targ).style.backgroundColor = "#BBDFFA";

					if (stock[index] == null)
						u.eid("extra_stock" + targ).innerHTML = 0;
					else
						u.eid("extra_stock" + targ).innerHTML = stock[index];
				}

				//check to see if we need to add new row
				z.checkAdd(targ);
			}
		}
		
		//checks to see if this part is in inventory
		z.findPart = function(targ){
			for (var i = 0; i < partsArray.length; i++){
				if (targ == partsArray[i]){
					return i;
				}
			}
			return -1;
			
		}

		
		//check to see if we need to add a new row
		z.checkAdd = function(targ){
			//grab table and table length
			var table = u.eid("workOrders-table"), 
				count = table.rows.length;
			
			//set adj to adjust for headers
			var adj = 2; 
			
			if (u.eid("extra_pn" + targ).value !== "" && u.eid("extra_q" + targ).value !== "" && (targ + adj) == count)
				z.add_one(targ);
		}
		
		//add new row to parts request tables
		z.add_one = function(targ){
			//increment extra_part by 1 so we know where to look for parts
			total_parts++;
			
			//move targ +1 for new IDs
			targ++;
			
			//grab table
			var table = u.eid("workOrders-table");
			
			//insert new row at bottom of table
			var row = table.insertRow(-1);
			
			//checkbox
			var cell = row.insertCell(0);
			cell.style.textAlign = 'center';
			cell.innerHTML = '<input id = "extra_checked' + targ + '" type="checkbox" align="center" onchange="z.form_check(' + targ + ')" checked>';
			
			//part Number
			var cell = row.insertCell(1);
			cell.innerHTML = '<input style="width: 400px" id="extra_pn' + targ + '" class="parts ui-autocomplete-input" onchange="z.form_check(' + targ + ')" autocomplete = "off">';
			
			//quantity
			var cell = row.insertCell(2);
			cell.innerHTML = '<input type="number" value="" style="width: 100px" min="0" id="extra_q' + targ + '" onchange="z.form_check(' + targ + ')">';
			
			//subs allowed
			var cell = row.insertCell(3);
			cell.innerHTML = '<select class="custom-select" style="width: 100px" id="extra_subs' + targ + '" ><option value="No">No</option><option value="Yes">Yes</option></select>';
			
			//in stock 
			var cell = row.insertCell(4);
			cell.classList.add("stock");
			cell.id = 'extra_stock' + targ;
			
		}
		
		//windows onload
		window.onload = function () {
			// Get the element with id="defaultOpen" and click on it
			u.eid("defaultOpen").click();
			
		}
		
		$(document).ajaxStart(function () {
		  waiting('on');
		});
		
		$(document).ajaxStop(function () {
		  waiting('off');
			
		});
		
		$( function() {
		var availableTags = <?= json_encode($emails); ?>;
		function split( val ) {
		  return val.split( /,\s*/ );
		}
		function extractLast( term ) {
		  return split( term ).pop();
		}

		$( ".tags" )
		  // don't navigate away from the field on tab when selecting an item
		  .on( "keydown", function( event ) {
			if ( event.keyCode === $.ui.keyCode.TAB &&
				$( this ).autocomplete( "instance" ).menu.active ) {
			  event.preventDefault();
			}
		  })
		  .autocomplete({
			minLength: 0,
			source: function( request, response ) {
			  // delegate back to autocomplete, but extract the last term
			  response( $.ui.autocomplete.filter(
				availableTags, extractLast( request.term ) ) );
			},
			focus: function() {
			  // prevent value inserted on focus
			  return false;
			},
			select: function( event, ui ) {
			  var terms = split( this.value );
			  // remove the current input
			  terms.pop();
			  // add the selected item
			  terms.push( ui.item.value );
			  // add placeholder to get the comma-and-space at the end
			  terms.push( "" );
			  this.value = terms.join( "; " );
			  return false;
			}
		  });
	  	} );
		
		//handles tabs up top that toggle between divs
		function change_tabs (pageName, elmnt, color) {
						
		  // Hide all elements with class="tabcontent" by default */
		  var i, tabcontent, tablinks;
		  tabcontent = u.class("tabcontent");
		  for (i = 0; i < tabcontent.length; i++) {
			tabcontent[i].style.display = "none";
		  }

		  // Remove the background color of all tablinks/buttons
		  tablinks = u.class("tablink");
		  for (i = 0; i < tablinks.length; i++) {
			tablinks[i].style.backgroundColor = "";
		  }

		  // Show the specific tab content
		  u.eid(pageName).style.display = "block";

		  // Add the specific color to the button used to open the tab content
		  elmnt.style.backgroundColor = color;		  
		}
	

	</script>
	
</body>
	
<?php

//reset return address once the page has loaded
unset($_SESSION['returnAddress']);

//close SQL connection
$mysqli -> close();

?>
	
</html>