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
if (isset($_SESSION['email'])) {
	$query = "SELECT * from fst_users where email = '" . $_SESSION['email'] . "';";
	$result = $mysqli->query($query);

	if ($result->num_rows > 0) {
		$fstUser = mysqli_fetch_array($result);
	} else {
		$fstUser['accessLevel'] = "None";
	}
} else {
	$fstUser['accessLevel'] = "None";
}

sessionCheck($fstUser['accessLevel']);

//if admin, display admin button
$admin = "none";

if ($fstUser['accessLevel'] == "Admin") {
	$admin = "";
}

//if user is deployment, hide $ values
$deployHide = "";
if ($fstUser['accessLevel'] == "Deployment") {
	$deployHide = "none";
}

//if deployment, can only search through fst's, cannot create a new one

$protect_header = "";

if ($fstUser['accessLevel'] == "Deployment") {
	$protect_header = "disabled";
}

//user emails
$emails = [];
$directory = [];
$query = "select firstName, lastName, email from fst_users order by email";
$result = mysqli_query($con, $query);

while ($rows = mysqli_fetch_assoc($result)) {
	array_push($emails, $rows['email']);
	array_push($directory, $rows['firstName'] . " " . $rows['lastName']);
}

//init arrays
$pq_overview = [];
$pq_detail = [];
$allocated = [];
$inventory = [];
$state_pref = [];

//grabs overview info (parts request info)
$query = "SELECT * FROM fst_pq_overview WHERE status IN ('Open', 'In Progress', 'Rejected') OR closed > now() - INTERVAL 2 day ORDER BY closed, requested;";
$result =  mysqli_query($con, $query);

while ($rows = mysqli_fetch_assoc($result)) {

	//push temp array to project array
	array_push($pq_overview, $rows);
}

//grabs detail (actual parts requested)
$query = "SELECT a.quoteNumber, b.* FROM fst_pq_detail b 
			LEFT JOIN fst_pq_overview a
				ON a.id = b.project_id
			WHERE b.status <> 'Received';";
$result =  mysqli_query($con, $query);

while ($rows = mysqli_fetch_assoc($result)) {

	//push temp array to project array
	array_push($pq_detail, $rows);
}

// grabs parts allocated
$query = "SELECT part_id, SUM(q_allocated) as 'q_allocated', decision, status FROM fst_pq_detail 
			WHERE status = 'Pending' AND decision NOT IN ('PO', '') GROUP BY part_id, decision;";
$result =  mysqli_query($con, $query);
while ($rows = mysqli_fetch_assoc($result)) {
	array_push($allocated, $rows);
}

//build query to get inventory dynamically
$query = "SELECT partNumber, partCategory, partDescription, manufacturer, uom, cost, subPN";

//DESCRIBE invreport shows the columns in invreport. Use this to grab all stock locations
$describe_invreport = "DESCRIBE invreport;";
$result = mysqli_query($con, $describe_invreport);

while ($rows = mysqli_fetch_assoc($result)) {

	//only add fields with stock 
	if (str_contains($rows['Field'], "-"))
		$query .= ", `" . $rows['Field'] . "`";
}

//finish query
$query .= " FROM invreport WHERE active = 'True';";
$result = mysqli_query($con, $query);

//initialize arrays to be passed to js
$inventory = [];

//cycle thorugh query and assign to different arrays
//add entry for each mo 
while ($rows = mysqli_fetch_assoc($result)) {
	array_push($inventory, $rows);
}

//grabs state pref for decision making
$query = "SELECT stAbv, allocation FROM inv_statepref;";
$result =  mysqli_query($con, $query);

while ($rows = mysqli_fetch_assoc($result)) {

	//push temp array to project array
	array_push($state_pref, $rows);
}

//initialize array for fst_boms sub list
$bom_subs = [];

//query to grab any subs available
$query = "select quoteNumber, partNumber, subs_list from fst_boms WHERE subs_list <> '';";
$result =  mysqli_query($con, $query);

while ($rows = mysqli_fetch_assoc($result)) {

	//push temp array to project array
	array_push($bom_subs, $rows);
}

//initialize array for reels
$reels = [];

//query to grab any subs available
$query = "select a.*, b.shop, b.bulk from inv_reel_requests a
			LEFT JOIN inv_reel_assignments b
				ON a.reel_id = b.id;";
$result =  mysqli_query($con, $query);

while ($rows = mysqli_fetch_assoc($result)) {

	//push temp array to project array
	array_push($reels, $rows);
}

?>

<!doctype html>
<html>

<head>
	<meta charset="utf-8">
	<meta name="google-signin-client_id" content="573761357198-hin7ae7q19qgvoab7t0781b41530546g.apps.googleusercontent.com">
	<link rel="shortcut icon" type="image/x-icon" href="images/P-Logo.png" />
	<link href="stylesheets/jquery-ui-themes-1.13.0/themes/ui-lightness/jquery-ui.css" rel="stylesheet">
	<link rel="stylesheet" href="stylesheets/element-styles.css?v=1.0">
	<link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css'>

	<title>Allocations Hub (v<?= $version ?>) - Pierson Wireless</title>

	<style>
		/*item_value = ['split', 'send', 'part_id', 'quantity', 'uom', 'decision', 'mo_id', 'on_hand', 'allocated', 'subs', 'substitution', 'notes']*/
		#notes {
			width: 372px;
			height: 80px;
			resize: vertical
		}

		/*overwrite standardTable td settings for notes-select*/
		.notes-select {
			cursor: pointer;
			text-align: center;
			border: none !important;
			font-size: 16px;
			font-weight: bold;
			padding: 16px 4px !important;
		}

		/*used if note is current selected*/
		.notes-select-on {
			border: 1px solid #000000 !important;
		}

		.address-wrap {
			float: left;
		}

		.large_button {
			font-size: 20px !important;
			width: 14em;
			height: 2em;
			text-align: center;
		}

		.cancel_button {
			background: red;
			margin-bottom: 10em;
			font-weight: bold;
		}

		.cancel_button:hover {
			background: #a10000 !important;
		}

		.send,
		.reject {
			width: 18px;
			height: 18px;
		}

		.part_id {
			width: 15em;
		}

		.uom {
			width: 5em;
		}

		.quantity {
			width: 6em;
		}

		.q_allocated {
			width: 6em;
		}

		.uom {
			width: 5em;
		}

		.decision,
		.split_decision {
			width: 10em;
		}

		.mo_id {
			width: 10em;
		}

		.on_hand {
			width: 6em;
		}

		.allocated {
			width: 6em;
		}

		.subs {
			width: 5em;
		}

		.instructions {
			height: 22px;
			width: 300px;
		}

		.reel_instructions {
			width: 300px;
			cursor: pointer;
		}

		.stock {
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
			overflow-y: scroll;
			height: 500px;
			width: 1950px;
		}

		.homeTables thead,
		.homeTables tbody tr {
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

		#profileTable {
			border-collapse: collapse;
		}

		#profileTable td {
			border: 1px solid #000000;
			padding: 5px;
		}

		#profileTable th {
			padding: 5px;
		}

		/* Style the tab content (and add height:100% for full page content) */
		.tabcontent {
			padding: 15px 20px;
			height: 100%;
		}

		.basic-table {
			display: inline-block;
			padding-bottom: 5px;
		}

		.basic-table td {
			padding-right: 5px;
		}

		.newPart_input {
			width: 400px;
		}

		.newPart_th {
			text-align: left;
		}

		.pr_tables {
			padding-bottom: 2em;
			padding-right: 2em;
			float: left;
			line-height: 20px;
		}

		.pr_info {
			clear: left;
			display: none;
		}

		.shipping_row {
			/*visibility: collapse;*/
		}

		/*adjustments to queue styles*/

		.ui-state-active {
			background-color: #007fff !important;
		}

		.list-item {
			font-weight: bold !important;
			color: black !important;
		}
	</style>
</head>

<body>

	<?php

	//define array of names & Id's to generate headers
	$header_names = ['Parts Orders', 'Asset Orders', 'Clothing Orders', 'Shop to Shop Inv. Transfers'];
	$header_ids = ['MO', 'Asset', 'Clothing', 'Transfer'];

	//pass to php function to create navigation bars
	echo create_navigation_bar($header_names, $header_ids, "update_queue(false)", $fstUser);

	?>
	<div style='padding-left:1em;padding-top:4em;'>

		<!--CONSTANT IN constants.php-->
		<?= constant('terminal_navigation') ?>

	</div>

	<div id='MO' class='tabcontent'>

		<h1 id='pq_header'> Parts Orders </h1>

		<button onclick='update_queue(false)'>Save Info/Update Queue</button>

		<br>

		<div class="pq-wrap" id='pq-sel-div' style='float:left; padding-right: 2em'>
			<h3>Parts Request Queue</h3>
			<div id='pq-list' class="pq" style='padding-bottom: 5em; float: left;'>
				<label for="pq-default" class='list-item' style='display:none' id='pq-label-default'></label>
				<input type="radio" name="pq" id="pq-default" onclick='z.show_info(this)'>
			</div>
		</div>

		<div style='clear: left' class='pr_info'>
			<table class="pr_tables" id="pr_table">
				<tr>
					<th colspan="2">
						<h3>Project Details</h3>
					</th>
				</tr>
				<tr>
					<td>*Project #: </td>
					<td class="partRequestTD">
						<input class="requiredPR check_input" type="text" id='project_id' style="width: 250px">
						<input type="checkbox" id='greensheet' onclick='update_greensheet(this)' class='check_input'>Greensheet?
					</td>
				</tr>
				<tr>
					<td>*Project Name: </td>
					<td class="partRequestTD">
						<input class="requiredPR check_input" type="text" id='project_name' style="width: 250px">
					</td>
				</tr>
				<tr>
					<td>*Requested By: </td>
					<td class="partRequestTD">
						<select class="requiredPR custom-select check_input" id='requested_by' style="width: 250px">
							<option></option>
							<option value='Other'>Other</option>
							<?php
							//loop through employee directory
							for ($i = 0; $i < sizeof($directory); $i++) {

							?>

								<option value="<?= $directory[$i]; ?>"> <?= $directory[$i] ?></option>

							<?php
							}
							?>
						</select>
					</td>
				</tr>
				<tr>
					<td>*Due By: </td>
					<td class="partRequestTD">
						<input class="requiredSR check_input" type="date" id='due_date' style="width: 253px">
						<input type='checkbox' id='early_delivery'> <i>Early Delivery Accepted?</i>
					</td>

				</tr>
				<tr>
					<td>*Add CC: </td>
					<td class="partRequestTD">
						<textarea class="requiredPR emails check_input" id='cc_email' style="width: 460px; height: 50px; resize: vertical"></textarea>
						<!--<input class = "requiredPR emails" type = "text" id = 'cc_email' style = "width: 250px" >!-->
					</td>
				</tr>
				<tr>
					<td>Additional Instructions: </td>
					<td class="partRequestTD">
						<textarea id='add_instructions' style="width: 460px; height: 50px; resize: vertical" class='check_input'></textarea>
					</td>
				</tr>
				<tr>
					<td>Customer Project #: </td>
					<td class="partRequestTD">
						<input type="text" id='cust_pn' style="width: 250px" class='check_input'>
					</td>
				</tr>
				<tr>
					<td>OEM Reg #: </td>
					<td class="partRequestTD">
						<input type="text" id='oem_num' style="width: 250px" class='check_input'>
					</td>
				</tr>
				<tr>
					<td>Business Unit #: </td>
					<td class="partRequestTD">
						<input type="text" id='bus_unit_num' style="width: 250px" class='check_input'>
					</td>
				</tr>
				<tr>
					<td>Location ID: </td>
					<td class="partRequestTD">
						<input type="text" id='loc_id' style="width: 250px" class='check_input'>
					</td>
				</tr>
				<tr>
					<td>Attachment 1: </td>
					<td><input type="file" id="attachment-1" onchange='z.show_attachment(1)'></td>
				</tr>
				<tr id='att_row-2' style='visibility: collapse'>
					<td>Attachment 2: </td>
					<td><input type="file" id="attachment-2" onchange='z.show_attachment(2)'></td>
				</tr>
				<tr id='att_row-3' style='visibility: collapse'>
					<td>Attachment 3: </td>
					<td><input type="file" id="attachment-3"></td>
				</tr>
				<tr>
					<td>Amending MO: </td>
					<td class="partRequestTD">
						<input type='checkbox' id='amending' onchange='z.show_reason("amending")' class='check_input'>
					</td>
				</tr>
				<tr style='visibility: collapse' id='amending_row' class='check_input'>
					<td>*Amending Reason: </td>
					<td class="partRequestTD">
						<select class="custom-select" id='amending_reason' style="width: 250px">
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
					<td class="partRequestTD">
						<input type='checkbox' id='reship' onchange='z.show_reason("reship")' class='check_input'>
					</td>
				</tr>
				<tr style='visibility: collapse' id='reship_row'>
					<td>*Reship Reason: </td>
					<td class="partRequestTD">
						<select class="custom-select" id='reship_reason' style="width: 250px" class='check_input'>
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

			<div class='address-wrap'>
				<table class="pr_tables" style='float:none'>
					<tr>
						<th colspan="2">
							<h3>Staging Address</h3>
						</th>
					</tr>
					<tr>
						<td>*Location Name: </td>
						<td class="partRequestTD">
							<select class="requiredPR custom-select check_input" id='staging_loc' style="width: 250px" onchange='z.ship_to_toggle(this.value, "staging")'>
								<option></option>
								<option value='Ship To Final Destination'>Ship To Final Destination</option>
								<?php
								//run query to grab locations

								$shippingLocQ = "SELECT * from general_shippingadd where customer = 'PW' order by name";
								$result = mysqli_query($con, $shippingLocQ);

								//init arrays
								$locations = [];
								$abv = [];
								$street = [];
								$city = [];
								$state = [];
								$zip = [];

								while ($rows = mysqli_fetch_assoc($result)) {
									array_push($locations, $rows['name']);
									array_push($abv, $rows['abv']);
									array_push($street, $rows['address']);
									array_push($city, $rows['city']);
									array_push($state, $rows['state']);
									array_push($zip, $rows['zip']);
								?>

									<option value="<?= $rows['name']; ?>"> <?= $rows['name'] ?></option>

								<?php
								}

								?>
								<option value="Greensheet">Greensheet</option>
							</select>
						</td>
					</tr>
					<tr class='shipping_row'>
						<td>*Address: </td>
						<td class="partRequestTD"><input class="requiredShip check_input" type="text" id='staging_street' style="width: 250px"></td>
					</tr>
					<!--<tr class = 'shipping_row' id = 'shipping_row_2'>
							<td>Address 2: </td>
							<td class = "partRequestTD"><input type = "text" id = 'street2' style = "width: 250px" ></td>
						</tr>!-->
					<tr class='shipping_row'>
						<td>*City: </td>
						<td class="partRequestTD"><input class="requiredShip check_input" type="text" id='staging_city' style="width: 250px"></td>
					</tr>
					<tr class='shipping_row'>
						<td>*State: </td>
						<td class="partRequestTD">
							<select class="requiredShip custom-select check_input" id='staging_state' style="width: 250px">
								<option></option>
								<?php

								for ($i = 0; $i < sizeof($states); $i++) {

								?>

									<option><?= $states[$i]; ?></option>

								<?php

								}

								?>
							</select>
						</td>
					</tr>
					<tr class='shipping_row'>
						<td>*Zip: </td>
						<td class="partRequestTD"><input class="requiredShip check_input" type="text" id='staging_zip' style="width: 250px"></td>
					</tr>
				</table>

				<table class="pr_tables">
					<tr>
						<th colspan="2">
							<h3>Final Destination Address</h3>
						</th>
					</tr>
					<tr>
						<td>POC Name: </td>
						<td><input class="requiredSR check_input" type="text" id='poc_name' style="width: 250px"></td>
					</tr>
					<tr>
						<td>POC Number: </td>
						<td><input class="requiredSR check_input" type="text" id='poc_number' style="width: 250px"></td>
					</tr>
					<tr>
						<td>POC Email: </td>
						<td><input class="requiredSR check_input" type="text" id='poc_email' style="width: 250px"></td>
					</tr>
					<tr>
						<td>Location Name: </td>
						<td class="partRequestTD">
							<input class="requiredSR check_input" id='shipping_loc' style="width: 250px">
						</td>
					</tr>
					<tr>
						<td>Address: </td>
						<td class="partRequestTD"><input class="requiredShip check_input" type="text" id='shipping_street' style="width: 250px"></td>
					</tr>
					<tr>
						<td>City: </td>
						<td class="partRequestTD"><input class="requiredShip check_input" type="text" id='shipping_city' style="width: 250px"></td>
					</tr>
					<tr>
						<td>State: </td>
						<td class="partRequestTD">
							<select class="requiredShip custom-select check_input" id='shipping_state' style="width: 250px">
								<option></option>
								<?php

								for ($i = 0; $i < sizeof($states); $i++) {

								?>

									<option><?= $states[$i]; ?></option>

								<?php

								}

								?>
							</select>
						</td>
					</tr>
					<tr>
						<td>Zip: </td>
						<td class="partRequestTD"><input class="requiredShip check_input" type="text" id='shipping_zip' style="width: 250px"></td>
					</tr>
					<tr>
						<td>Lift Gate Required? </td>
						<td class="partRequestTD">
							<select class="custom-select check_input" id='liftgate' style="width: 250px">
								<option></option>
								<option value='N'>N</option>
								<option value='Y'>Y</option>
							</select>
						</td>
					</tr>
					<tr>
						<td>Scheduled Delivery </td>
						<td class="partRequestTD">
							<select class="custom-select check_input" id='sched_opt' style="width: 250px">
								<option></option>
								<option value='N'>N</option>
								<option value='Y'>Y</option>
							</select>
						</td>
					</tr>
					<tr class='sched_row'>
						<td>Scheduled Date </td>
						<td class="partRequestTD"><input class="requiredShip check_input" type="date" id='sched_date' style="width: 250px"></td>
					</tr>
					<tr class='sched_row'>
						<td>Scheduled Time </td>
						<td class="partRequestTD"><input class="requiredShip check_input" type="time" id='sched_time' style="width: 250px"></td>
					</tr>
				</table>
			</div>

			<div class="notes-wrap">
				<table id='notes-table' class='pr_tables standardTables' style='min-width: 420px'>
					<tr>
						<th colspan="2">
							<h3>Notes</h3>
						</th>
					</tr>
					<tr class='notes-row'>
						<td class='notes-select'>OMA</td>
						<td id='notes-span' rowspan="3">
							<textarea id='notes' class='check_input'></textarea>
						</td>
					</tr>
					<tr class='notes-row'>
						<td class='notes-select'>CHA</td>
						<!--filled with textarea !-->
					</tr>
					<tr class='notes-row'>
						<td class='notes-select'>PO</td>
						<!--filled with textarea !-->
					</tr>
				</table>

			</div>

			<!-- potential resource http://www-db.deis.unibo.it/courses/TW/DOCS/w3schools/w3css/tryit.asp-filename=tryw3css_tabulators_sidenav.html !-->

			<div style='padding-bottom: 10em;'></div>

		</div>

		<div class='pr_info' style='padding-bottom: 5em;'>

			<button class='large_button' onclick='get_minimum_stock()' id='get_min_stock_button'>Get Min Stock Orders</button><br>
			<button class='large_button' onclick='assign_mo(false)'>Assign MO #s</button><br>
			<button class='large_button' onclick='submit_orders()' id='submit_mo_button' disabled>Submit Order(s)</button><br>

			<table id='pq-parts-table' style='border-collapse: collapse;'>
				<thead>
					<tr>
						<th>Reject</th>
						<th></th>
						<th>Send</th>
						<th>Part Number</th>
						<th style='width: 20px;'>Quantity (Total Req)</th>
						<th style='width: 20px;'>Quantity (Allocate)</th>
						<th>UOM</th>
						<th>Pull From</th>
						<th>MO/PO #</th>
						<th style='width: 20px;'>Parent Shops On-Hand</th>
						<th style='width: 20px;'>Parent Shops Allocated</th>
						<th style='width: 20px;'>Subs Allowed</th>
						<th></th>
						<th>Instructions</th>
						<th>Notes</th>
					</tr>
				</thead>

				<tbody>
					<!--filled with function add_item()!-->
				</tbody>

			</table>

			<form action="materialEntry.php" method="GET">
				<input id='material_entry_id' name="PQID_allocations" style='display:none;'>
				<input class='large_button' type="submit" value="Add Materials" style="margin-top: 1em;">
			</form>

			<button id='cancel_pq_button' class='large_button cancel_button' onclick='cancel_pq_handler()'>Cancel Parts Request</button>

		</div>

	</div>

	<div id='Asset' class='tabcontent' style='display: none'>
		COMING SOON
	</div>

	<div id='Clothing' class='tabcontent' style='display: none'>
		COMING SOON
	</div>

	<div id='Transfer' class='tabcontent' style='display: none'>
		COMING SOON
	</div>

	<!------------------------ DIALOG BOXES!---------------------->

	<!--- Parts Substitution Dialog Box !--->
	<div class='ui-widget' id='partSub-dialog' style='display:none' title='Part Substitions'>
		<table id='partSubs'>
			<tr>
				<th></th>
				<th style='width: 15em'>Part Number</th>
				<th style='width: 7em'>Cost</th>
				<th style='width: 7em'>Total Stock</th>
			</tr>
			<tr>
				<td><b>Original</b></td>
				<td id='o_pn'></td>
				<td id='o_cost'></td>
				<td id='o_stock'></td>
			</tr>
			<tr>
				<td colspan=" 4" style='border: 0; height: 5px'></td>
			</tr>
			<!--WHERE NEW ROWS WILL BE INSERTED !-->
		</table>

		<h3>OR</h3>

		<input id='new_sub' class='part_search' placeholder="Enter New Substitute"> <button onclick='add_new_substitute()'>Add Substitute</button>
	</div>

	<!--- Split Par Dialog Box !--->
	<div class='ui-widget' id='partSplit-dialog' style='display:none' title='Part Split'>
		<table id='partSplit-table' class='partDialog'>
			<tr>
				<th></th>
				<th style='width: 15em'>Part Number</th>
				<th style='width: 7em'>Quantity</th>
				<th style='width: 7em'>Shop</th>
			</tr>
			<tr>
				<td><b>Total</b></td>
				<td id='target_pn'></td>
				<td id='target_quantity'></td>
				<td id='target_shop'></td>
			</tr>
			<tr>
				<td colspan="4" style='border: 0; height: 5px'></td>
			</tr>
			<!--WHERE NEW ROWS WILL BE INSERTED !-->
		</table>
		<button onclick='split_handler.process_split()' class='large_button' style='margin-top: 1em;'>Split Part</button>
	</div>

	<div id='reject_dialog' style='display:none' title='Rejected Part'>

		<table>
			<tr>
				<td class='reject_td'>Part Number: </td>
				<td><input readonly id='reject_part'></td>
			</tr>
			<tr>
				<td class='reject_td'>Reason: </td>
				<td>
					<select id='reject_reason' class='custom-select'>
						<option></option>
						<option>Stock is Incorrect</option>
						<option>Needs Clarification</option>
						<option>Part # Not Found</option>
					</select>
				</td>
			</tr>
			<tr>
				<td colspan="2">
					<textarea id='reject_notes' placeholder="Enter any applicable notes" style='width: 400px; height: 80px; resize:vertical'></textarea>
				</td>
			</tr>
		</table>

		<br>

		<button onclick='reject_part()'>Reject Part</button>

		<span id='hold_detail_id' style='display:none'></span>
		<span id='hold_overview_id' style='display:none'></span>
	</div>

	<!--externally defined js libraries-->
	<!-- enables ajax functionality -->
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
	<script src="https://apis.google.com/js/platform.js?onload=init" async defer></script>

	<!-- allows jquery use -->
	<script src="https://code.jquery.com/jquery-1.12.4.js"></script>
	<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>

	<!-- internally defined js files -->
	<script src="javascript/js_helper.js?<?= $version ?>-3"></script>
	<script src="javascript/fst_js_functions.js"></script>
	<script src="javascript/utils.js"></script>
	<script src="javascript/accounting.js"></script>

	<script>
		//Namespace
		var z = {}

		//read in location to names and address
		const locations = <?= json_encode($locations); ?>,
			abv = <?= json_encode($abv); ?>,
			street = <?= json_encode($street); ?>,
			city = <?= json_encode($city); ?>,
			state = <?= json_encode($state); ?>,
			zip = <?= json_encode($zip); ?>,
			user_info = <?= json_encode($fstUser); ?>,
			shop_allocated = <?= json_encode($allocated); ?>;

		//used to load email tags
		var availableTags = <?= json_encode($emails); ?>;

		//init current update id (used to update existing)
		var update_id = null;

		//pass project array
		var pq_overview = <?= json_encode($pq_overview); ?>,
			pq_detail = <?= json_encode($pq_detail); ?>,
			inventory = <?= json_encode($inventory); ?>,
			state_pref = <?= json_encode($state_pref); ?>,
			bom_subs = <?= json_encode($bom_subs); ?>,
			reels = <?= json_encode($reels); ?>;

		//handles csv output for BOM
		function export_analytics() {

			//initalize form data (will carry all form data over to server side)
			var fd = new FormData();

			//add tell so we know what we're doing in php
			fd.append('tell', "bom_pricing");

			//access database
			$.ajax({
				url: 'terminal_allocations_helper.php',
				type: 'POST',
				processData: false,
				contentType: false,
				data: fd,
				success: function(response) {

					//check for error response
					var check = response.substr(0, 5);

					if (check == "Error") {
						alert(response);
					}

					//grab response and parse into bom pricing
					var bom_pricing = $.parseJSON(response);
					console.log(bom_pricing);

					//initialize csvContent to export csv
					let csvContent = "data:text/csv;charset=utf-8,";

					// add headers to CSV
					csvContent += "(PM/WO),Requestor,Project Number,Status,Date Requested,$ Value\r\n";

					//loop through bill of materials and add each part
					for (var i = 0; i < pq_overview.length; i++) {
						//remove comma's and #'s from strings

						pq_overview[i].type = scrub_string(pq_overview[i].type);
						pq_overview[i].requested_by = scrub_string(pq_overview[i].requested_by);
						pq_overview[i].project_id = scrub_string(pq_overview[i].project_id);
						pq_overview[i].urgency = scrub_string(pq_overview[i].urgency);
						pq_overview[i].requested = scrub_string(pq_overview[i].requested);


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
						csvContent += pq_overview[i].type + ',';
						csvContent += pq_overview[i].requested_by + ",";
						csvContent += pq_overview[i].project_id + ',';
						csvContent += pq_overview[i].urgency + ",";
						csvContent += pq_overview[i].requested + ", ";
						csvContent += get_order_cost(pq_overview[i].quoteNumber, pq_overview[i].id, bom_pricing);
						csvContent += '\r\n';

					}

					//set encoded uri for download, push to link, name and force download
					var encodedUri = encodeURI(csvContent);
					var link = u.eid("hold_pq_info");
					var today = date_string();

					link.setAttribute("href", encodedUri);
					link.setAttribute("download", "PQ Analytics (" + today + ").csv");
					link.click();

				}
			});
		}

		//gets the full cost of materials in a request
		//param 1 = target quote #
		//param 2 = target overview_id
		//param 3 = full array of bompricing
		function get_order_cost(quote, overview_id, bom_pricing) {

			//init sum of cost
			total_cost = 0;

			//get array of parts in a given request
			var parts = pq_detail.filter(function(part) {
				return part.project_id == overview_id;
			});

			//loop through parts, search for part in bom_pricing that matches quote & part #, add to total cost
			for (var i = 0; i < parts.length; i++) {

				//get index of match
				var index = bom_pricing.findIndex(object => {
					return object.partNumber == parts[i].part_id && object.quoteNumber == quote;
				});

				//if we found a match, add to total cost
				if (index != -1)
					total_cost += (parseFloat(bom_pricing[index].cost) * parseFloat(bom_pricing[index].quantity));

			}

			return total_cost;
		}

		//grabs nicely formatted date
		function date_string() {
			var t = new Date();
			var day = t.getDate();
			var month = t.getMonth() + 1;
			var year = t.getFullYear();

			return month + "-" + day + "-" + year;
		}

		//handle sanitizing string
		function scrub_string(targ) {

			//check if blank
			if (targ == "" || targ == null)
				return targ;

			//used to remove unfriendly characters
			var regexp = new RegExp('#', 'g');
			targ = targ.replace(regexp, '');
			targ = targ.replace(/,/g, ';');

			return targ;
		}

		/**
		 * Handles updating fields based on if greensheet is checked or not
		 * 
		 * @param targ HTML Entity (<input type = 'checkbox'>)
		 *  
		 * @returnss void 
		 */
		function update_greensheet(targ) {

			// set RO_Setting based on if targ is checked or not
			var RO_setting = targ.checked;

			// look through user fields and update to match RO_setting
			document.querySelectorAll('.check_input').forEach(function(a) {
				if (a.id != "greensheet")
					a.disabled = RO_setting;
			})

			// check for stock shops (always overwrite)
			if (active_min_stock_shops.includes(u.eid("project_id").value)) {
				u.eid("get_min_stock_button").style.display = "";
				u.eid("project_id").readOnly = true;
				u.eid("project_name").readOnly = true;
			} else {
				u.eid("get_min_stock_button").style.display = "none";
				u.eid("project_id").readOnly = false;
				u.eid("project_name").readOnly = false;
			}
		}

		//handles showing reason dropdowns if selected
		//targ = reship amend (same functionallity, different id's used)
		//index = 1-5 (related to material order clicked)
		z.show_reason = function(targ) {

			//check value to see if we need to show or hide
			//show if it is checked
			if (u.eid(targ).checked)
				u.eid(targ + "_row").style.visibility = "visible";
			//hide if not checked
			else
				u.eid(targ + "_row").style.visibility = "collapse";

		}

		//handles toggle ship-to rows
		z.ship_to_toggle = function(opt, type = null) {
			//hold display option
			var display;

			//if other, then show toggle
			if (opt == "Other")
				display = "visible";
			else
				display = "collapse";

			//cycle through shipping rows and show/hide depending on opt
			var shipping_rows = u.class("shipping_row");

			for (var i = 0; i < shipping_rows.length; i++) {
				shipping_rows[i].style.visibility = display;

			}
		}

		//handles showing additional attachments as you add them
		z.show_attachment = function(id) {

			//grab current attachment
			var check_file = u.eid("attachment-" + id).value;

			//check if not blank
			if (check_file != "") {
				id++;
				u.eid("att_row-" + id).style.visibility = 'visible';
			}

		}

		//handles checking if required fields have been entered
		z.submit_check = function() {

			//decision variable, passed back at the end of the function
			var desc = true;

			//cycle through class, if anything is blank, highlight yellow and return false
			$(".requiredPR").each(function() {

				//first turn to blue background
				this.classList.remove("required_error");

				// Test if the element is empty, if so turn it yellow, mark desc as false
				if (!$(this).val()) {
					this.classList.add("required_error");
					desc = false;
				}
			});

			//check to see if ship to is "other"
			if (u.eid("ship_to").value == "Other") {
				//cycle through class, if anything is blank, highlight yellow and return false
				$(".requiredShip").each(function() {

					//first turn to blue background
					$(this).css("background", good_color);

					// Test if the element is empty, if so turn it yellow, mark desc as false
					if (!$(this).val()) {
						$(this).css("background", bad_color);
						desc = false;
					}
				});
			}

			//check to see if amend or reship is checked. If so verify that we have a reason select for both
			if (u.eid("amending").checked) {

				if (u.eid("amending_reason").value == "") {
					u.eid("amending_reason").style.background = bad_color;
					desc = false;
				} else {
					u.eid("amending_reason").style.background = good_color;
				}
			}

			if (u.eid("reship").checked) {

				if (u.eid("reship_reason").value == "") {
					u.eid("reship_reason").style.background = bad_color;
					desc = false;
				} else {
					u.eid("reship_reason").style.background = good_color;
				}
			}

			//let user know if there was an error
			if (!desc)
				alert("There has been an error with your request. Required fields have been highlighted in yellow.");

			//return result
			return desc;

		}

		//handle assigning MO numbers (ajax call to database to assign MO's. On successful return, run submit_check())
		//passes boolean editing which tells if we are running this while editing (splitting, subbing, etc.)
		function assign_mo(editing) {

			//save any changed info
			//update_queue(false);

			//init array to hold unique shop values
			var target_shops = [],
				part_assignments = [],
				temp = [];

			//get classes that we will traverse
			var decision = document.getElementsByClassName("decision");
			var mo_id = document.getElementsByClassName("mo_id");
			var send = document.getElementsByClassName("send");
			var parts = document.getElementsByClassName("part_id");

			//loop through decision values and add any uniques that do not already have an MO_ID
			for (var i = 0; i < decision.length; i++) {

				//adjust send to true/false string (php issues reading boolean values)
				var send_string = 'false';

				//if true, then flip
				if (send[i].checked)
					send_string = 'true'

				//add part and pull from shop to part_assignments via temp
				temp = {
					part_id: parts[i].id,
					shop: decision[i].value,
					send: send_string
				};

				part_assignments.push(temp);

				//turn shop into abbreviated shop (so we send same MO for OMA-1 and OMA-2)
				var abv_shop = decision[i].value;

				//if there is a hyphen, abbreviate it
				if (abv_shop.indexOf("-") > 0)
					abv_shop = abv_shop.substr(0, abv_shop.indexOf("-"));

				//simple method found https://stackoverflow.com/questions/36719477/array-push-and-unique-items
				//checks for index in an array and adds if there is none
				if (target_shops.indexOf(abv_shop) === -1) {
					target_shops.push(abv_shop);
				}

			}

			//initalize form data (will carry all form data over to server side)
			var fd = new FormData();

			//serialize arrays and pass them to fd 
			fd.append('target_shops', JSON.stringify(target_shops));
			fd.append('part_assignments', JSON.stringify(part_assignments));
			fd.append('id', pq_overview[current_index].id);

			//add tell variable
			fd.append('tell', 'init_mo');

			//access database
			$.ajax({
				url: 'terminal_allocations_helper.php',
				type: 'POST',
				processData: false,
				contentType: false,
				data: fd,
				success: function(response) {

					console.log(response);

					//check for error response
					var check = response.substr(0, 5);

					if (check == "Error") {
						alert(response);
					}

					//grab response and parse
					var result = $.parseJSON(response);

					//depending on response, go back through parts and fill in MO where applicable
					for (var i = 0; i < decision.length; i++) {

						//only initialize the ones checked to send 
						if (send[i].checked) {
							//look to see if we have a matching shop
							for (var j = 0; j < result.length; j++) {
								if (decision[i].value.indexOf(result[j].shop) >= 0) {
									mo_id[i].value = result[j].mo_id;
									break;
								}
							}
						}

						//if it is blank or it is a PO we want to remove any mo_id from its field
						if (decision[i].value == "" || decision[i].value == "PO" || !send[i].checked)
							mo_id[i].value = "";

					}

					//only alert if not splitting
					if (!editing)
						alert("The Material Order numbers have been assigned.");

					//run checks to see if we are ready to submit MO
					if (!editing)
						open_submit();
					//u.eid("submit_mo_button").disabled = false;

				}
			});
		}

		//handles logic to open/close submit button
		function open_submit() {

			//boolean to determine if we can open the class or not
			var pass = true;

			//grab send class and pull from class
			var send_class = u.class("send"),
				decision_class = u.class("decision"),
				mo_class = u.class("mo_id"),
				quantity_class = u.class("q_allocated");

			//array to hold all potential pull-from locations
			var potential_locs = [];

			//if (this.classList[1] == 'decision')
			//	debugger;

			//loop through send buttons (if checked, make sure we have something for pull from)
			for (var i = 0; i < send_class.length; i++) {

				//check checkbox value
				if (send_class[i].checked) {

					//check if we have a decision made
					if (decision_class[i].value == "")
						pass = false;
					//check if we have a decision but haven't assigned an MO
					else if (decision_class[i].value != "PO" && mo_class[i].value == "")
						pass = false;

					//push to array if unique
					//turn shop into abbreviated shop (so we send same MO for OMA-1 and OMA-2)
					var abv_shop = decision_class[i].value;

					//if there is a hyphen, abbreviate it
					if (abv_shop.indexOf("-") > 0)
						abv_shop = abv_shop.substr(0, abv_shop.indexOf("-"));

					//if unique and SEND is checked, add
					if (potential_locs.indexOf(abv_shop) === -1) {
						potential_locs.push(abv_shop);
					}

				}

				//check for remote shops (turn border yellow)
				if (decision_class[i].value == "" || decision_class[i].value == "PO" || decision_class[i].value.substr(0, 3) == "OMA" || decision_class[i].value.substr(0, 3) == "CHA")
					decision_class[i].style.borderColor = "#000B51";
				else
					decision_class[i].style.borderColor = "Yellow";

				//check quantity against available
				if (decision_class[i].value != "" && decision_class[i].value != "PO") {

					//use select value to work our way back to quantity listed in dropdown
					var shop_pos = decision_class[i].textContent.indexOf(decision_class[i].value);
					var parenthesis_start = decision_class[i].textContent.indexOf("(", shop_pos);
					var parenthesis_end = decision_class[i].textContent.indexOf(")", shop_pos);
					var shop_quantity = parseInt(decision_class[i].textContent.substr(parenthesis_start + 1, parenthesis_end - parenthesis_start - 1));

					if (shop_quantity < quantity_class[i].value) {

						if (send_class[i].checked)
							pass = false;

						quantity_class[i].style.color = "red";
						quantity_class[i].style.borderColor = "red";
						quantity_class[i].style.borderWidth = "Medium";

					} else {
						quantity_class[i].style.color = "black";
						quantity_class[i].style.borderColor = "black";
						quantity_class[i].style.borderWidth = "Medium";

					}
				} else {
					//reset quantity colors
					quantity_class[i].style.color = "black";
					quantity_class[i].style.borderColor = "black";
					quantity_class[i].style.borderWidth = "Medium";
				}
			}

			//if pass is true, allow user to submit request
			if (pass)
				u.eid("submit_mo_button").disabled = false;
			else
				u.eid("submit_mo_button").disabled = true;

			//if we have potential locations, open notes fields based on locations
			if (potential_locs.length != 0)
				generate_notes(potential_locs);
			//otherwise, still click first available
			else
				u.class("notes-select")[0].click();

		}

		//global to decide which note to show first
		var curr_note_state;

		//handles generating which locations may require a note
		function generate_notes(potential_locs) {

			//sort array
			potential_locs = potential_locs.sort();

			//remove existing
			document.querySelectorAll('.notes-row').forEach(function(a) {
				a.remove();
			})

			//break out current note state into the pq_index and the location
			var pq_index;
			var location;

			if (curr_note_state != null) {
				pq_index = curr_note_state.substr(0, curr_note_state.indexOf("|"));
				location = curr_note_state.substr(curr_note_state.indexOf("|") + 1);
			}

			if (current_index != pq_index)
				curr_note_state = null;

			//add back new locations
			for (var i = 0; i < potential_locs.length; i++) {
				add_notes_row(potential_locs[i]);
			}

			//reset state if applicable
			if (curr_note_state != null) {
				document.querySelectorAll('.notes-select').forEach(function(a) {
					if (a.innerHTML == location) {
						a.click();
						return;
					}

				})
			}

			//if we get through all resent note state and find no match, click the top-most element
			u.class("notes-select")[0].click();
		}

		//handles adding individual notes row
		function add_notes_row(loc) {

			//get table
			var table = u.eid("notes-table");

			//insert new row and add classname to it
			var row = table.insertRow(-1);
			row.classList.add("notes-row");

			//part number
			var cell = row.insertCell(0);
			cell.innerHTML = loc;
			cell.classList.add("notes-select");

			//if text area span exists, increment rowspan by 1
			var note_span = u.eid("notes-ta-span")
			if (note_span) {
				note_span.rowSpan++;
			} else {
				//insert cell to hold textarea
				var cell = row.insertCell(1);
				cell.id = 'notes-ta-span';
				cell.innerHTML = "<textarea id='notes' class='check_input' onchange = 'refresh_target()'></textarea>"
			}
		}

		//add event listener to notes-select class (changes shop designated note)
		$(document).on('click', 'td.notes-select', function() {

			//remove class from all elements
			document.querySelectorAll('.notes-select').forEach(function(a) {
				a.classList.remove("notes-select-on");
			})

			//apply class 
			this.classList.add("notes-select-on");

			//add corresponding note to textarea
			var location = this.innerHTML;

			//search for location in notes (adjust if null)
			if (pq_overview[current_index].notes == null)
				pq_overview[current_index].notes = "";

			var loc_index = pq_overview[current_index].notes.indexOf(location + "|");
			var blank_tell = pq_overview[current_index].notes.indexOf(location + "||");

			//add existing note if we found an index
			if (loc_index == blank_tell) {
				u.eid("notes").value = "";
			} else if (loc_index != -1) {
				var start_loc = pq_overview[current_index].notes.indexOf("|", loc_index) + 1;
				var end_loc = pq_overview[current_index].notes.indexOf("|", start_loc);
				var note = pq_overview[current_index].notes.substr(start_loc, end_loc - start_loc);
				u.eid("notes").value = note;
			} else {
				u.eid("notes").value = "";
			}

			//save current state
			curr_note_state = current_index + "|" + location;
		})

		//handles formatting notes to save for multiple locations
		function convert_notes(pq_index) {

			//get current location & note
			var location = u.class("notes-select-on")[0].innerHTML;
			var curr_note = pq_overview[pq_index].notes;
			var new_note = location + "|" + u.eid("notes").value + "|";

			//search for location in notes
			var loc_index = pq_overview[pq_index].notes.indexOf(location + "|");

			//if no index, add new entry
			if (loc_index == -1) {
				curr_note += new_note;
			} else {

				//get start & end index of location note
				var start_loc = curr_note.indexOf("|", loc_index) + 1;
				var end_loc = curr_note.indexOf("|", start_loc);
				var old_note = location + "|" + curr_note.substr(start_loc, end_loc - start_loc) + "|";

				curr_note = curr_note.replace(old_note, new_note);

			}

			//update note
			pq_overview[pq_index].notes = curr_note;

		}

		//global used to keep track of indexes of changes
		var target_ids = [];

		//handles refreshing target arrays and updating globals
		function refresh_target() {

			//if current index is -1, ignore
			if (current_index === -1)
				return;

			//push index if not already in array
			if (target_ids.indexOf(pq_overview[current_index].id) === -1) {
				target_ids.push(pq_overview[current_index].id);
			}

			//refresh globals
			refresh_globals();

		}

		//array used to guide updating global (db col's need to match id's)
		const update_ids = ['project_id', 'project_name', 'cc_email', 'requested_by', 'poc_name', 'poc_number', 'poc_email', 'shipping_loc', 'shipping_street', 'shipping_city', 'shipping_state', 'shipping_zip', 'staging_loc', 'staging_street', 'staging_city', 'staging_state', 'staging_zip', 'liftgate', 'sched_opt', 'sched_time', 'due_date', 'greensheet', 'early_delivery', 'add_instructions', 'notes', 'cust_pn', 'oem_num', 'bus_unit_num', 'loc_id', 'amending', 'amending_reason', 'reship', 'reship_reason'];

		//handles refreshing globals according to current index
		function refresh_globals() {

			//loop through update_ids and update globals
			for (var i = 0; i < update_ids.length; i++) {

				//checkboxes are treated differently
				if (update_ids[i] == "amending" || update_ids[i] == "reship" || update_ids[i] == "greensheet" || update_ids[i] == "early_delivery") {
					//if true, set to 1, false set to 0
					if (u.eid(update_ids[i]).checked)
						pq_overview[current_index][update_ids[i]] = 1;
					else
						pq_overview[current_index][update_ids[i]] = 0;
				} else if (update_ids[i] == "sched_time") {
					pq_overview[current_index][update_ids[i]] = u.eid("sched_date").value + " " + u.eid("sched_time").value;
				} else if (update_ids[i] == "notes") {
					convert_notes(current_index);
				} else {
					pq_overview[current_index][update_ids[i]] = u.eid(update_ids[i]).value;
				}

			}

			console.log(pq_overview[current_index]);

			//update part info
			var parts = u.class("part_id");
			var instructions = u.class("instructions");
			var pull_from = u.class("decision");
			var q_allocated = u.class("q_allocated");
			var send = u.class("send");
			var reject = u.class("reject");

			//loop through and update instructions
			for (var i = 0; i < parts.length; i++) {

				//find index of parts.id
				var detail_index = pq_detail.findIndex(object => {
					return object.id == parts[i].id;
				});

				//init send / reject values
				var send_value = "false";
				var reject_value = "false";

				//adjust send / reject
				if (send[i].checked)
					send_value = "true";

				if (reject[i].checked)
					reject_value = "true";

				pq_detail[detail_index].instructions = instructions[i].value;
				pq_detail[detail_index].q_allocated = q_allocated[i].value;
				pq_detail[detail_index].decision = pull_from[i].value;
				pq_detail[detail_index].send = send_value;
				pq_detail[detail_index].reject = reject_value;

			}
		}

		//determines if we are updating or not
		var updating = false;

		//handles saving any updated values to database
		function update_queue(update = true) {

			//set updating
			updating = update;

			//initalize form data (will carry all form data over to server side)
			var fd = new FormData();

			//add arrays to send to server (saves any changed values)
			fd.append('target_ids', JSON.stringify(target_ids));
			fd.append('update_ids', JSON.stringify(update_ids));
			fd.append('pq_overview', JSON.stringify(pq_overview));
			fd.append('pq_detail', JSON.stringify(pq_detail));

			//add tell
			fd.append('tell', 'update_queue');

			$.ajax({
				url: 'terminal_allocations_helper.php',
				type: 'POST',
				processData: false,
				contentType: false,
				data: fd,
				success: function(response) {

					hold_response = $.parseJSON(response);

					console.log(hold_response);

					//update objects
					pq_overview = hold_response[0];
					pq_detail = hold_response[1];

					//refresh info
					refresh_queue();

					//clear update array
					target_ids = [];

					//if updating is false already, let user know that the info has been saved
					if (!updating)
						alert("All changes have been saved.");

					//unset updating
					updating = false;

					//reset interval
					clearInterval(myInterval);
					myInterval = setInterval(update_queue, 60000);

				}
			});
		}

		//handles refreshing queue
		function refresh_queue() {

			//remove previous queue items
			document.querySelectorAll('.open-pending').forEach(function(a) {
				a.remove()
			})

			//loop through array and add any projects not in close to open parts request
			for (var i = 0; i < pq_overview.length; i++) {
				if (pq_overview[i].status == "Open" || pq_overview[i].status == "In Progress" ||
					pq_overview[i].status == "Rejected" || pq_overview[i].status == "Submitted" && check_closed_today(pq_overview[i].closed))
					new_list_item(i);

			}

			//hold current index
			var temp_hold = current_index;

			//click default, then click indexed
			//this way the same list item is still clicked after sort
			//u.eid("pq-default").click();

			//update class of previous selected
			if (current_index != -1) {
				u.eid("pq-label-" + current_index).classList.add("ui-corner-top");
				u.eid("pq-label-" + current_index).classList.add("ui-checkboxradio-checked");
				u.eid("pq-label-" + current_index).classList.add("ui-state-active");
			}

			//if (temp_hold >= 0)
			//	u.eid("pq-" + temp_hold).click();

		}

		//used to send info over to fst_allocations_mos (MOs)
		function submit_orders() {

			//check required inputs
			var error = check_submit(u.class('requiredPR'));

			//if this is a greensheet, overwrite error
			if (u.eid("greensheet").checked)
				error = false;

			//if error, send message to user and return
			if (error) {
				alert("Missing required fields.");
				return;
			}

			//lock submit button before ajax call
			u.eid("submit_mo_button").disabled = true;

			//save info/MO values one last time
			//update_queue(true);
			assign_mo(true);
			update_queue(true);

			//init array to hold pull_from locations & bom info
			var pull_from = [],
				bom = [],
				reject_check = false;

			//grab classes needed to loop through bom
			var decision_class = u.class("decision"),
				send_class = u.class("send"),
				reject_class = u.class("reject"),
				parts_class = u.class("part_id");

			//loop through bom and collect info needed to process request
			for (var i = 0; i < decision_class.length; i++) {

				//only request an MO to be issued IF send is checked
				if (send_class[i].checked) {
					//turn shop into abbreviated shop (so we send same MO for OMA-1 and OMA-2)
					var abv_shop = decision_class[i].value;

					//if there is a hyphen, abbreviate it
					if (abv_shop.indexOf("-") > 0)
						abv_shop = abv_shop.substr(0, abv_shop.indexOf("-"));

					//if unique and SEND is checked, add
					if (pull_from.indexOf(abv_shop) === -1) {
						pull_from.push(abv_shop);
					}

					//get index of part in pq_detail, and push to bom[]
					var index = pq_detail.findIndex(object => {
						return object.id == parts_class[i].id;
					});
					bom.push(pq_detail[index]);
				}

				//if reject is flipped, change bool
				if (reject_class[i].checked)
					reject_check = true;
			}

			//if we have no pull from, reject
			if (pull_from.length == 0 && !reject_check) {
				alert("No parts to be allocated.");
				return;
			}

			//initalize form data (will carry all form data over to server side)
			var fd = new FormData();

			//grab any files if applicable
			var attachments = [];

			for (var i = 1; i < 4; i++) {
				if (u.eid("attachment-" + i).files.length > 0) {

					var file = $("#attachment-" + i)[0].files[0];
					fd.append("file" + i, file);

					//save name in attachments array
					attachments.push(i);
				} else {
					attachments.push("");
				}
			}

			//serialize arrays and pass them to fd 
			fd.append('pull_from', JSON.stringify(pull_from));
			fd.append('attachments', JSON.stringify(attachments));
			fd.append('requested_parts', JSON.stringify(bom));

			//make a few adjustments for checkbox values
			var early_delivery = "No";

			if (u.eid("early_delivery").checked)
				early_delivery = "Yes";

			//add any other info sent over in ajax
			fd.append('project_id', u.eid("project_id").value);
			fd.append('project_name', u.eid("project_name").value);
			fd.append('due_date', u.eid("due_date").value);
			fd.append('early_delivery', early_delivery);
			fd.append('cc', u.eid("cc_email").value);
			fd.append('notes', pq_overview[current_index].notes);
			fd.append('amending_mo', u.eid("amending_reason").value);
			fd.append('reship_request', u.eid("reship_reason").value);

			//get staginc loc index in locations array
			var loc_index = locations.indexOf(u.eid("staging_loc").value);

			//if -1, use other options, otherwise use preset address
			if (loc_index != -1) {
				fd.append('staging_loc', locations[loc_index]);
				fd.append('staging_abv', abv[loc_index]);
				fd.append('staging_street', street[loc_index]);
				fd.append('staging_city', city[loc_index]);
				fd.append('staging_state', state[loc_index]);
				fd.append('staging_zip', zip[loc_index]);

			} else if (u.eid("staging_loc").value == "Ship To Final Destination") {
				fd.append('staging_loc', u.eid("staging_loc").value);
				fd.append('staging_abv', "");
				fd.append('staging_street', "");
				fd.append('staging_city', "");
				fd.append('staging_state', "");
				fd.append('staging_zip', "");
			} else {
				fd.append('staging_loc', u.eid("staging_loc").value);
				fd.append('staging_abv', "");
				fd.append('staging_street', u.eid("staging_street").value);
				fd.append('staging_city', u.eid("staging_city").value);
				fd.append('staging_state', u.eid("staging_state").value);
				fd.append('staging_zip', u.eid("staging_zip").value);
			}

			fd.append('poc_name', u.eid("poc_name").value);
			fd.append('poc_number', u.eid("poc_number").value);
			fd.append('requested_by', u.eid("requested_by").value);
			fd.append('liftgate', u.eid("liftgate").value);
			fd.append('sched_opt', pq_overview[current_index].sched_opt);
			fd.append('sched_time', pq_overview[current_index].sched_time);
			fd.append('ship_to', u.eid("shipping_loc").value);
			fd.append('street', u.eid("shipping_street").value);
			fd.append('city', u.eid("shipping_city").value);
			fd.append('state', u.eid("shipping_state").value);
			fd.append('zip', u.eid("shipping_zip").value);
			fd.append('greensheet', u.eid("greensheet").checked);

			//get market manager & quote number
			fd.append('manager', pq_overview[current_index].manager);
			fd.append('quoteNumber', pq_overview[current_index].quoteNumber);

			//add tell variable & user info
			fd.append('tell', 'process_orders');
			fd.append('user_info', JSON.stringify(user_info));

			//add project id
			fd.append('pq_id', pq_overview[current_index].id)

			$.ajax({
				url: 'terminal_allocations_helper.php',
				type: 'POST',
				processData: false,
				contentType: false,
				data: fd,
				success: function(response) {

					//check for error (if response says anything)
					if (response != "") {
						alert(response);
						console.log(response);
						u.eid("submit_mo_button").disabled = false;

					} else {
						alert("The submitted Order(s) has been processed successfully.");
						target_ids = [];
						window.location.reload();
					}

					//unlock after ajax (only relevant if we have an error and the MO is rejected)
					//u.eid("submit_mo_button").disabled = false;

				}
			});
		}

		function cancel_pq_handler() {

			//create message to user as safety guard
			var message = "Are you sure? This cannot be undone.";

			//send message to user
			if (confirm(message)) {

				//pass id to parts cancel function
				cancel_parts_request(pq_overview[current_index].id);

			}
		}

		//handles cancelling a parts request
		//param 1 = parts request id (will match id in fst_pq_overview & fst_pq_detail)
		function cancel_parts_request(id) {

			//turn updating off (makes mouse spin on click)
			updating = false;

			//disable button
			u.eid("cancel_pq_button").disabled = true;

			//initalize form data (will carry all form data over to server side)
			var fd = new FormData();

			//append id
			fd.append('pq_id', id);
			fd.append('tell', 'cancel_pq');

			$.ajax({
				url: 'terminal_allocations_helper.php',
				type: 'POST',
				processData: false,
				contentType: false,
				data: fd,
				success: function(response) {

					//send back response
					if (response != "") {
						alert(response);
						return;
					}

					//update queue
					u.eid("pq-default").click();
					update_queue(true);
					alert("This parts request has been successfully cancelled.")

					//enable cancel button
					u.eid("cancel_pq_button").disabled = false;
				}
			});
		}

		//function designed to insert current email address onto the most recent line selected on parts request form
		z.insertEmail = function() {
			var email = u.eid("insert_email").value,
				recent = u.eid("email_" + email_recent).value;

			if (recent == "") {
				recent = email;
			} else {
				recent = recent + "; " + email;
			}

			u.eid("email_" + email_recent).value = recent;
		}

		//handles shipping location dropdown and event
		z.shippingSelect = function(address) {
			var location = [],
				tempLen1 = 1,
				currSpot = 0,
				tempLen2;

			if (address == "") {
				u.eid("shipping_address").value = "";
				u.eid("shipping_city").value = "";
				u.eid("shipping_state").value = "";
				u.eid("shipping_zip").value = "";
				u.eid("shipping_location").value = "";

			} else {

				while (tempLen1 > 0 && currSpot < 7) {
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
		z.scheduleSelect = function(check) {
			console.log(check);
			if (check == "Y") {
				u.eid("schedRow1").style.visibility = "visible",
					u.eid("schedRow2").style.visibility = "visible";

			} else {
				u.eid("schedRow1").style.visibility = "collapse",
					u.eid("schedRow2").style.visibility = "collapse";

			}

		}

		//global that holds current index being viewed in pq_overview
		var current_index = -1;

		//global that holds active shops that hold min orders
		var active_min_stock_shops = ["233-999899", "236-999899"];

		//shows relevant info for parts request
		z.show_info = function(targ = null) {

			//grab id from z.show_info
			var index = $(this).attr('id');
			if (index == undefined)
				index = targ.id;

			//if default, just ignore
			if (index == "pq-default") {

				//hide current info
				document.querySelectorAll('.pr_info').forEach(function(a) {
					a.style.display = "none";
				})

				current_index = -1;
				return;
			}

			//update index to actual id
			//first look for "closed in the id"
			if (index.indexOf("closed") > 0)
				index = index.substring(10);
			else
				index = index.substring(3);

			//check to see if index matches current index (if so, unhighlight blue and hide main area)
			if (current_index == index) {
				u.eid("pq-default").click();

				//loop through pr_info and hide
				document.querySelectorAll('.pr_info').forEach(function(a) {
					a.style.display = "none";
				})

				return;
			} else {
				//loop through pr_info and show
				document.querySelectorAll('.pr_info').forEach(function(a) {
					a.style.display = "block";
				})
			}

			//update current index
			current_index = index;

			//scroll to open orders
			//$('html,body').animate({scrollTop: $("#pq_header").offset().top},'slow');

			// Set material_entry_id
			u.eid("material_entry_id").value = pq_overview[index].id;

			//update values according to index
			//Project Details
			u.eid("project_id").value = pq_overview[index].project_id;
			u.eid("project_name").value = pq_overview[index].project_name;
			u.eid("requested_by").value = pq_overview[index].requested_by;
			u.eid("due_date").value = pq_overview[index].due_date;
			u.eid("cc_email").value = pq_overview[index].cc_email;
			u.eid("add_instructions").value = pq_overview[index].add_instructions;
			//u.eid("notes").value = pq_overview[index].notes;
			u.eid("cust_pn").value = pq_overview[index].cust_pn;
			u.eid("oem_num").value = pq_overview[index].oem_num;
			u.eid("bus_unit_num").value = pq_overview[index].bus_unit_num;
			u.eid("loc_id").value = pq_overview[index].loc_id;

			//add checkboxes
			if (pq_overview[index].greensheet == 1)
				u.eid("greensheet").checked = true;
			else
				u.eid("greensheet").checked = false;

			//Staging Address
			u.eid("staging_loc").value = pq_overview[index].staging_loc;
			u.eid("staging_street").value = pq_overview[index].staging_street;
			u.eid("staging_city").value = pq_overview[index].staging_city;
			u.eid("staging_state").value = pq_overview[index].staging_state;
			u.eid("staging_zip").value = pq_overview[index].staging_zip;

			//Final Destinal Address
			u.eid("poc_name").value = pq_overview[index].poc_name;
			u.eid("poc_number").value = pq_overview[index].poc_number;
			u.eid("poc_email").value = pq_overview[index].poc_email;
			u.eid("shipping_loc").value = pq_overview[index].shipping_loc;
			z.ship_to_toggle(u.eid("shipping_loc").value);
			u.eid("shipping_street").value = pq_overview[index].shipping_street;
			u.eid("shipping_city").value = pq_overview[index].shipping_city;
			u.eid("shipping_state").value = pq_overview[index].shipping_state;
			u.eid("shipping_zip").value = pq_overview[index].shipping_zip;
			u.eid("liftgate").value = pq_overview[index].liftgate;

			//set sched field based on yes or no
			if (pq_overview[index].sched_opt == "Y") {
				u.eid("sched_opt").value = pq_overview[index].sched_opt;
				u.eid("sched_date").value = pq_overview[index].sched_time.substr(0, 10);
				u.eid("sched_time").value = pq_overview[index].sched_time.substr(11);

				document.querySelectorAll('.sched_row').forEach(function(a) {
					a.style.visibility = "visible";
				})
			} else {
				u.eid("sched_opt").value = pq_overview[index].sched_opt;
				u.eid("sched_date").value = null;
				u.eid("sched_time").value = null;
				document.querySelectorAll('.sched_row').forEach(function(a) {
					a.style.visibility = "collapse";
				})
			}

			//reset color of input fields
			document.querySelectorAll('.requiredPR').forEach(function(a) {
				a.style.background = "#BBDFFA";
			})

			//show bill of materials for request
			show_bom(pq_overview[index].id);

			//check if we can submit MO
			open_submit();

			//if status is open, acknowledge request (move to in progress, reply to email)
			if (pq_overview[index].status == "Open")
				acknowledge_request();

			// call greensheet function based on what user has entered for greensheet (also handles configuring project_id & min_stock button)
			update_greensheet(u.eid("greensheet"));
		}

		//handles showing BOM items for a selected parts request
		function show_bom(pq_id) {

			//clear previous BOM
			document.querySelectorAll('.pq-parts-row').forEach(function(a) {
				a.remove();
			})

			//reset 'previous kit' variable
			previous_was_kit = false;

			//init array to hold pq_detail
			var current_detail;

			//filter pq_detail and grab parts related to request
			var target_detail = pq_detail.filter(function(part) {
				return part.project_id == pq_id && part.status == "Requested";
			});

			//work way backwards through target_detail, add a "add" attribute, default to true
			//the reason we reverse the order is so the next step occurs in the correct order.. We pop elements from the end of the list and add as we go until it is empty
			var reverse_detail = [];

			for (var i = target_detail.length - 1; i > -1; i--) {
				current_detail = target_detail[i];
				current_detail.add = true;
				reverse_detail.push(current_detail);
			}

			//loop through and check for repeats (pop elements as you go)
			while (reverse_detail.length > 0) {

				//add current if not added previous
				current_detail = reverse_detail.pop();
				if (current_detail.add && reverse_detail.length == 0)
					add_item(current_detail, true); //styling last element added differently
				else if (current_detail.add)
					add_item(current_detail);

				//check the rest of the array for matching parts (we want to add right after)
				for (var i = 0; i < reverse_detail.length; i++) {
					if (current_detail.part_id == reverse_detail[i].part_id && reverse_detail[i].kit_id == "") {
						//add and set .add to false
						if (reverse_detail[i].add) {
							add_item(reverse_detail[i]);
							reverse_detail[i].add = false;
						}
					}
				}
			}

			//add event listener to reject class
			$(".reject").on('click', function() {

				//do nothing if unchecked
				if (!this.checked)
					return;

				//update part # and quantity
				//traverse through AST and grab index[1] & [3] for rows
				var td = this.parentElement;
				var tr = td.parentElement;
				var part = tr.childNodes[3].childNodes[0].value;
				var detail_id = parseInt(tr.childNodes[3].childNodes[0].id);
				var overview_id = pq_overview[current_index].id;

				//get index in pq_detail object
				var detail_index = pq_detail.findIndex(object => {
					return object.id == detail_id;
				});

				//check to see if we saved the previous reject
				check_reject_save();

				//check if we have a reason saved (can return, no need to save again)
				if (pq_detail[detail_index].reject_id != "" && pq_detail[detail_index].reject_id !== null)
					return;

				//update current reject
				current_reject_index = detail_id;

				//show dialog
				$("#reject_dialog").dialog({
					width: "auto",
					height: "auto",
					dialogClass: "fixedDialog",
					close: function() {
						check_reject_save()
					}
				});

				//update id placeholders & part in dialog box
				u.eid("hold_detail_id").innerHTML = detail_id;
				u.eid("hold_overview_id").innerHTML = overview_id;
				u.eid("reject_part").value = part;
			})
		}

		//handles checking if reject reason has been saved yet
		function check_reject_save() {

			console.log(current_reject_index);

			//if current index is still set, remove checkbox (reason is required to reject)
			if (current_reject_index != -1) {
				var index_string = current_reject_index.toString();
				var part = u.eid(index_string);
				var part_td = part.parentNode;
				var tr = part_td.parentNode;
				var reject_td = tr.childNodes[0];
				var reject = reject_td.childNodes[0];

				reject.checked = false;
				current_reject_index = -1;
			}
		}

		//init globals that will assist in add_item (and make it much easier to edit/style)
		const item_value = ['reject', 'split', 'send', 'part_id', 'quantity', 'q_allocated', 'uom', 'decision', 'mo_id', 'on_hand', 'allocated', 'subs', 'substitution', 'reel_instructions', 'instructions'],
			item_type = ['checkbox', 'NA', 'checkbox', 'text', 'number', 'number', 'text', 'text', 'text', 'number', 'number', 'text', 'NA', 'text', 'textarea'],
			item_readOnly = [false, true, true, true, true, false, true, false, true, true, true, true, true, true, false];

		//global used to determine if previous part was a kit (so we know where to end the blue wrap)
		var previous_was_kit = false;

		//handles adding a row based on index of pq_detail
		//param 1 (object that holds a given part number. Has properties decision, mmd, mo_id, part_id, project_id, quantity, quoteNumber, subs, and uom)
		//param 2 (last_item boolean (used to determine if last item being added))
		function add_item(item, last_item = false) {

			//init vars needed
			var table = u.eid("pq-parts-table").getElementsByTagName('tbody')[0],
				input, search;

			//check part Number for "(" - shows that the part was subbed
			if (item.part_id.indexOf("[") > 0) {
				search = item.part_id.substr(0, item.part_id.indexOf("[") - 1)
			} else {
				search = item.part_id;
			}

			//check if substitutions are allowed for this part
			var sub_tell = sub_check(item);

			//set search to lower case
			search = search.toLowerCase();

			//get inventory index (if available)
			var inv_index = inventory.findIndex(object => {
				return object.partNumber.toLowerCase() == search;
			});

			//reset shops & allocated to be used in logic
			var shops = [],
				allocated = {};

			//if we do not find a match, set UOM and decision to be purchased
			if (inv_index == -1) {
				//alert ("ERROR");
				item.uom = "";
				item.decision = "PO";
				item.on_hand = 0;
				item.allocated = 0;

				//don't allow sub button if not in catalog
				sub_tell = "disabled";

			} else {
				//update uom/onhand/allocated according to inventory
				item.uom = inventory[inv_index].uom;
				item.on_hand = 0;
				item.allocated = 0;

				//loop through one of the shop prefs (use west for now)
				for (var i = 0; i < west_pref.length; i++) {
					if (west_pref[i].substr(0, 3) == "OMA" || west_pref[i].substr(0, 3) == "CHA")
						item.on_hand += parseFloat(inventory[inv_index][west_pref[i]]);

					//if > 0, push to shops array
					if (parseFloat(inventory[inv_index][west_pref[i]]) > 0)
						shops.push(west_pref[i]);
				}

				//use shops to get allocated numbers
				allocated = get_allocated(item.part_id, shops, shop_allocated);

				//loop through allocated & add to item.allocated
				for (var i = 0; i < shops.length; i++) {
					item.allocated += allocated[shops[i]];
				}

				//only adjust decision if a decision has not been saved yet
				if (item.decision == "" || item.decision == null)
					item.decision = pull_from(item, inv_index);

				//overwrite sub tell (check if we have any subs in catalog)
				if (item.subs != "No")
					sub_tell = "";
				else
					sub_tell = "disabled";
			}

			//set disabled based on part status
			var disabled = false,
				disabled_string = "";

			if (item.status == "Warehouse" || item.status == "Shipped" || item.status == "Received" || (inv_index != -1 && inventory[inv_index].partCategory == "PW-KITS")) {
				disabled = true;
				disabled_string = "disabled";
			}

			//set values that aren't found on item
			//item.reject =  "<button class = 'reject' onclick = 'reject_handler(this)' " + disabled_string + ">Reject</button>";
			item.split = "<button class = 'split' onclick = 'split_handler.button_click(this)' " + disabled_string + ">Split Line</button>";
			item.substitution = "<button class = 'substitution' " + sub_tell + " onclick = 'show_subs(" + item.id + ")' " + disabled_string + ">Substitution</button>";
			item.reel_instructions = get_reel_string(item.id, reels); //get_reel_string found in javascript/js_helper.js

			//insert new row and add classname to it
			var row = table.insertRow(-1);
			row.classList.add("pq-parts-row");

			//loop through item_value array to help guide creation of table
			for (var i = 0; i < item_value.length; i++) {

				//create new cell
				var cell = row.insertCell(i);

				//check input_type (if na, just write HTML)
				if (item_type[i] == "NA") {
					cell.innerHTML = item[item_value[i]];
				}
				//text area
				else if (item_type[i] == "textarea") {
					input = document.createElement("TEXTAREA");
					input.value = item[item_value[i]];
					input.classList.add(item_value[i]);
					input.disabled = disabled;
					input.addEventListener("change", refresh_target);
					cell.appendChild(input);

				}
				//else set value and type based on item
				else {
					//cell.classList.add(item_value[i]);
					input = document.createElement("input");
					input.addEventListener("change", refresh_target);
					input.type = item_type[i];

					//if send, treat differently (as a checkbox)
					if (item_value[i] == "send") {
						cell.style.textAlign = "center";

						//check send checked value
						if (item.send == 'true' || item.send == null || item.send == "")
							input.checked = true;

						//assign event listener
						input.addEventListener("click", open_submit);
						input.addEventListener("click", reject_send_toggle);

					} else if (item_value[i] == "reject") {
						cell.style.textAlign = "center";

						//check send checked value
						if (item.reject == 'true')
							input.checked = true;

						//assign event listener
						input.addEventListener("click", open_submit);
						input.addEventListener("click", reject_send_toggle);

					} else if (item_value[i] == "decision") {

						//instead of using input, we'll use select
						input = render_select(item_value[i], inv_index, allocated);

						//set value and readonly value
						input.value = item[item_value[i]];
						input.readOnly = item_readOnly[i];
						input.addEventListener('change', open_submit);
						input.addEventListener('change', refresh_target);

					}
					//set id attribute if part_id (and check for subs)
					else if (item_value[i] == "part_id") {

						//if we have a previous part (something was subbed) show to user
						if (item.previous_part != "" && item.previous_part != null)
							input.value = item[item_value[i]] + " [subbed for " + item.previous_part + "]";
						else
							input.value = item[item_value[i]];

						input.readOnly = item_readOnly[i];
						input.id = item.id;
					} else {
						input.value = item[item_value[i]];
						input.readOnly = item_readOnly[i];

					}

					//set tab_index to -1 if readonly is true
					if (item_readOnly[i] == true)
						input.tabIndex = -1;

					//update input depending on disabled status
					input.disabled = disabled;

					//add class list with id name
					input.classList.add(item_value[i]);

					//append to cell
					cell.appendChild(input);
				}

				//check item category (if inv_index != -1) to see if this is a kit. If so, highlight the top and left
				if (item_value[i] == "part_id") {
					if (inv_index != -1 && inventory[inv_index].partCategory == "PW-KITS") {
						cell.style.borderTop = "3px solid blue";
						cell.style.borderLeft = "3px solid blue";
					} else if (item.kit_id != "" && item.kit_id != null) {
						cell.style.borderLeft = "3px solid blue";
						previous_was_kit = true;
					} else if (previous_was_kit) {
						cell.style.borderTop = "3px solid blue";
						previous_was_kit = false;
					}

					//if last item & previous_was_kit is still true, add style to top
					if (last_item && previous_was_kit) {
						cell.style.borderBottom = "3px solid blue";
						previous_was_kit = false;
					}
				}
			}
		}

		//globals that hold the order for west and east shops
		const east_pref = ["CHA-1", "CHA-2", "CHA-3", "OMA-1", "OMA-2", "OMA-3", "GCS-1", "MIN-1", "MIN-3", "KC-1", "KC-3", "HOU-1", "HOU-3", "CHI-1", "CHI-3", "DAL-1", "DAL-3", "IND-1", "IND-3", "LAN-1", "LAN-3", "LAS-1", "LAS-3", "NJ-1", "NJ-3", "PHI-1", "PHI-3", "PIT-1", "PIT-3", "SAF-1", "SAF-3"],
			west_pref = east_pref;

		//handles deciding what location we should pull a material from
		function pull_from(part, inv_index) {

			//if mmd is yes, return PO
			if (part.mmd == "Yes")
				return "PO";

			//init variables needed throughout
			var state = "NE",
				pref = "West",
				pref_array;

			//find index in pq_overview (used to get state)
			var pq_index = pq_overview.findIndex(object => {
				return object.id == part.proj_id;
			});

			//if pq_index != -1 then update state
			if (pq_index != -1 && pq_overview[pq_index].shipping_state != "" && pq_overview[pq_index].shipping_state != null)
				state = pq_overview[pq_index].shipping_state;

			//based on state, set west/east preference
			var state_index = state_pref.findIndex(object => {
				return object.stAbv == state;
			});

			//if state_index != -1, use found location preference
			if (state_index != -1)
				pref = state_pref[state_index].allocation;

			//based on preference, decide what to return to user
			if (pref == "West")
				pref_array = west_pref;
			else if (pref == "East")
				pref_array = east_pref;

			//loop through pref_array and see if we find a shop with enough stock
			for (var i = 0; i < pref_array.length; i++) {

				//compare stock to inventory
				if (parseFloat(inventory[inv_index][pref_array[i]]) >= parseFloat(part.quantity))
					return pref_array[i];
			}

			//return total (will help make decision)
			return "PO";

		}

		//holds our options for shops to select form
		const shop_options = ['PO', 'CHA-1', 'CHA-2', 'CHA-3', 'OMA-1', 'OMA-2', 'OMA-3', 'GCS-1', 'MIN-1', 'MIN-3', 'KC-1', 'KC-3', 'HOU-1', 'HOU-3', 'CHI-1', 'CHI-3', 'DAL-1', 'DAL-3', 'IND-1', 'IND-3', 'LAN-1', 'LAN-3', 'LAS-1', 'LAS-3', 'NJ-1', 'NJ-3', 'PHI-1', 'PHI-3', 'PIT-1', 'PIT-3', 'SAF-1', 'SAF-3'];

		//handles creating select list for input elements in allocation table
		//pass type (helped decide global to use) and inv_index (index of the part in inventory (null if not passed))
		//param 1 = type of select list (may be used to hold multiple hold multiple)
		//param 2 = inv_index (default null) (index for inventory object)
		//param 3 = allocated totals (default null) 
		function render_select(type, inv_index = null, allocated = null) {

			//init select list
			var select = document.createElement("select");

			//add empty option
			//var option = document.createElement("option");
			//select.append(option);

			//renders select list of shop locations we can allocate from
			if (type == "decision") {
				for (var i = 0; i < shop_options.length; i++) {

					//only add to drop-down if we have inventory for the part
					//always add PO to drop-down (condition 1)
					//add shop if 1) we found a match in our catalog, 2) shops quantity is above 0, 3) this is not a stock order project
					if (shop_options[i] == "PO" ||
						(inv_index != -1 && parseInt(inventory[inv_index][shop_options[i]]) > 0 && !active_min_stock_shops.includes(pq_overview[current_index].project_id))
					) {
						var option = document.createElement("option");
						option.value = shop_options[i];

						//add stock if not PO
						if (shop_options[i] == "PO")
							option.innerHTML = shop_options[i];
						else {
							var shop_available = inventory[inv_index][shop_options[i]] - allocated[shop_options[i]];
							option.innerHTML = shop_options[i] + " (" + shop_available + ")";
						}

						select.append(option);
					}
				}
			}

			//style select
			select.classList.add("custom-select");

			//return new input element
			return select;

		}

		//handles acknowledging a request (move to in progress, reply to email)
		function acknowledge_request() {

			//init form data to send to server
			var fd = new FormData();

			//add pq_overview ID and tell
			fd.append("id", pq_overview[current_index].id);
			fd.append("tell", "acknowledge")

			//access database
			$.ajax({
				url: 'terminal_allocations_helper.php',
				type: 'POST',
				processData: false,
				contentType: false,
				data: fd,
				success: function(response) {

					//check for error response
					if (response != "") {
						alert(response);
						return;
					}

					//update global & label
					pq_overview[current_index].status = "In Progress";
					u.eid("status" + current_index).innerHTML = "In Progress";

				}
			})

		}

		//handles deciding if we should show substitution button as readonly or not
		function sub_check(part) {

			//loop through bom subs and look for a part/quote match
			for (var i = 0; i < bom_subs.length; i++) {
				if (bom_subs[i].quoteNumber == part.quoteNumber && bom_subs[i].partNumber == part.part_id && bom_subs[i].subs_list != "" && part.subs == "Yes")
					return "";
			}

			//if we do not find a match, return readonly
			return "disabled";

		}

		//global used to tell which part is being subbed
		var sub_id = -1;

		//handles showing dialog with subs
		//passes index (shows index in pq_detail)
		function show_subs(id) {

			//save part id
			sub_id = id;

			//find index based on id
			var index = pq_detail.findIndex(object => {
				return object.id == id;
			});

			//grab given part object needed
			var part = pq_detail[index];

			//check part Number for "(" - shows that the part was subbed
			if (part.part_id.indexOf("[") > 0) {
				part.part_id = part.part_id.substr(0, part.part_id.indexOf("[") - 1)
			} else {
				part.part_id = part.part_id;
			}

			//get inventory index (if available)
			var inv_index = inventory.findIndex(object => {
				return object.partNumber.toLowerCase() == part.part_id.toLowerCase();
			});

			//change subs from NULL if needed
			if (inventory[inv_index].subPN == null)
				inventory[inv_index].subPN = "";

			//once we find a match, create dialog
			if (inv_index > -1 && part.subs != "No") {

				//remove old sub rows
				document.querySelectorAll('.subParts').forEach(function(a) {
					a.remove()
				})

				//parse resonse into array of arrays 
				var result = inventory[inv_index].subPN.split(",");

				//get index of inventory for main part
				var inv_index = inventory.findIndex(object => {
					return object.partNumber == part.part_id;
				});

				//only pass if not -1
				if (inv_index != -1) {
					//pass sub parts through with s = sub, and relevant stock/cost (1, 2)
					subHandler(part.part_id, "o", get_total(inventory[inv_index]), inventory[inv_index].cost);
				} else {
					//pass sub parts through with s = sub, (in this case we did not find part in catalog)
					subHandler(part.part_id, "o", 'part not found in catalog', 'Error');
				}

				//loop through list of subs
				for (var i = 0; i < result.length; i++) {

					//get index of inventory
					var inv_index = inventory.findIndex(object => {
						return object.partNumber == result[i].trim();
					});

					//only pass if not -1
					if (inv_index != -1) {
						//pass sub parts through with s = sub, and relevant stock/cost (1, 2)
						subHandler(result[i].trim(), "s", get_total(inventory[inv_index]), inventory[inv_index].cost, inv_index);
					} else {
						//pass sub parts through with s = sub, (in this case we did not find part in catalog)
						subHandler(result[i].trim(), "s", 'part not found in catalog', 'Error');
					}

				}

				//open dialog
				$("#partSub-dialog").dialog({
					width: "auto",
					height: "auto",
					dialogClass: "fixedDialog",
				});

				return;
			}
		}

		/**@author Alex Borchers
		 * Handles getting total inventory (from all shops)
		 * 
		 * @param part {object} matches row from invreport db table
		 * @returns total {int} total in main shops
		 */
		function get_total(part) {

			// referece west_pref global to see current shops
			var sum = 0;

			for (var i = 0; i < west_pref.length; i++) {
				sum += parseInt(part[west_pref[i]]);
			}

			return sum;

		}

		//handles creating rows in the subs table for the user to select from
		function subHandler(part, tell, stock, cost, inv_index) {

			var table = u.eid("partSubs");
			var subSpan = u.eid("subSpan")

			//handle sub parts
			if (tell == 's') {
				var index = 0;
				//insert new row and add classname to it
				var row = table.insertRow(-1);
				row.classList.add("subParts");

				var subSpan = u.eid("subSpan");
				//if it exists, incremenet rowspan by 1
				if (subSpan) {
					subSpan.rowSpan++;
				} else {
					//insert cell to hold "Substitution"
					var cell = row.insertCell(index);
					cell.id = 'subSpan';
					cell.innerHTML = "<b>Substitutes</b>"
					index++;
				}

				//part number
				var cell = row.insertCell(index);
				cell.innerHTML = part;
				index++;

				//cost
				var cell = row.insertCell(index);
				cell.innerHTML = cost;
				index++;

				//Stock
				var cell = row.insertCell(index);
				cell.innerHTML = stock;
				index++;

				//if cost = error, then disable
				var dis = "";
				if (cost == "Error")
					dis = "disabled";

				//sub part button
				var cell = row.insertCell(index);
				cell.innerHTML = "<button onclick = 'subPart(" + inv_index + ")' " + dis + ">Substitute</button>";
			}

			//handle original part
			else if (tell == 'o') {
				u.eid("o_pn").innerHTML = part;
				u.eid("o_cost").innerHTML = cost;
				u.eid("o_stock").innerHTML = stock;

			}

		}

		//handles actually substituting the part requested
		//param 1 = index in inventory object (part subbing for)
		function subPart(inv_index) {

			//grab part for this row
			var part = inventory[inv_index].partNumber;

			//save data before ajax 
			assign_mo(true);

			//initalize form data (will carry all form data over to server side)
			var fd = new FormData();

			//use stored info in fst_pq_detail to get old part (can cause issues with subs for subs)
			var pq_index = pq_detail.findIndex(object => {
				return object.id == sub_id;
			});

			//pass info needed to make substitution
			fd.append('old_part', pq_detail[pq_index].part_id);
			fd.append('new_part', part);
			fd.append('part_id', sub_id);
			fd.append('quote', pq_overview[current_index].quoteNumber);

			//add tell variable & user
			fd.append('tell', 'process_sub');
			fd.append('user_info', JSON.stringify(user_info));

			//access database
			$.ajax({
				url: 'terminal_allocations_helper.php',
				type: 'POST',
				processData: false,
				contentType: false,
				data: fd,
				success: function(response) {

					//check for error response
					var check = response.substr(0, 5);
					if (check == "Error") {
						alert(response);
						return;
					}

					//grab response and parse
					var result = $.parseJSON(response);
					pq_detail = result;

					//update table view
					show_bom(pq_overview[current_index].id);

					//close dialog
					$("#partSub-dialog").dialog('close');

					//alert user of success
					alert("The substitution has been processed.");

					//run checks to see if we are ready to submit MO
					open_submit();

				}
			});
		}

		//handles adding a new substitution to our catalog for a given part
		function add_new_substitute() {

			//validate the target part (just in case it clipped through the cracks)
			var target_part = u.eid("o_pn").innerHTML;
			var inv_index = inventory.findIndex(object => {
				return object.partNumber.toLowerCase() == target_part.toLowerCase();
			});

			if (inv_index == -1) {
				alert("The 'Original' part (" + target_part + ") is not in our catalog. Therefore you cannot create a new substitute for it. Please create the part or reject the part.");
				return;
			}

			//validate the the part entered is in our catlog
			var new_sub = u.eid("new_sub").value;
			var inv_index = inventory.findIndex(object => {
				return object.partNumber.toLowerCase() == new_sub.toLowerCase();
			});

			if (inv_index == -1) {
				alert("The part entered is not in our catalog. Please enter a new part.");
				return;
			}

			//initalize form data (will carry all form data over to server side)
			var fd = new FormData();

			//pass info needed to make substitution
			fd.append('target_part', target_part);
			fd.append('new_sub', inventory[inv_index].partNumber);

			//add tell variable
			fd.append('tell', 'add_new_sub');

			//access database
			$.ajax({
				url: 'terminal_allocations_helper.php',
				type: 'POST',
				processData: false,
				contentType: false,
				data: fd,
				success: function(response) {

					//check for error response
					if (response != "") {
						alert(response);
						return;
					}

					//if successful, make substitution
					subPart(inv_index);

				}
			});
		}

		//handles toggling reject button
		//function comes with a 'this' variable which is the actual input that user interacted with
		function reject_send_toggle() {

			//check class list (send or reject)
			if (this.classList[0] == "send") {

				//if checked, we need to uncheck reject
				if (this.checked) {

					//work our way to send checkbox & uncheck
					var td = this.parentNode;
					var tr = td.parentNode;
					var send_td = tr.childNodes[0];
					var send_checkbox = send_td.childNodes[0];

					send_checkbox.checked = false;

				}

			} else if (this.classList[0] == "reject") {

				//if checked, we need to uncheck the send button and prompt the user to enter a reason for rejecting
				if (this.checked) {

					//work our way to send checkbox & uncheck
					var td = this.parentNode;
					var tr = td.parentNode;
					var send_td = tr.childNodes[2];
					var send_checkbox = send_td.childNodes[0];

					send_checkbox.checked = false;

					//prompt user to enter reason for rejecting

				} else {

				}

			}
		}

		//global to hold current rejected part (init at -1 = inactive)
		var current_reject_index = -1;

		//handles rejecting parts
		//passes pq_detail_id as parameter (this is the id in pq_detail table for each part) & pq_overview_id (id in pq_overview table for project)
		function reject_part() {

			//first ask if they really want to reject (may be a mis-click)
			var message = "Are you sure you would like to reject this part?";

			//send message to user (return if cancel)
			if (!confirm(message))
				return;

			//initalize form data (will carry all form data over to server side)
			var fd = new FormData();

			//append info needed to reject part
			fd.append('pq_detail_id', u.eid("hold_detail_id").innerHTML);
			fd.append('pq_overview_id', u.eid("hold_overview_id").innerHTML);
			fd.append('reject_part', u.eid("reject_part").value);
			fd.append('reject_reason', u.eid("reject_reason").value);
			fd.append('reject_notes', u.eid("reject_notes").value);
			fd.append('project_number', pq_overview[current_index].project_id);
			fd.append('quoteNumber', pq_overview[current_index].quoteNumber);

			//add tell
			fd.append('tell', 'reject');

			$.ajax({
				url: 'terminal_allocations_helper.php',
				type: 'POST',
				processData: false,
				contentType: false,
				data: fd,
				success: function(response) {

					//if we return a response it is an error
					if (response != "") {
						alert(response);

					}
					//otherwise, update our tables
					else {
						alert("This part has been successfully rejected.");

						//update pq_detail with correct reason
						var detail_index = pq_detail.findIndex(object => {
							return object.id == current_reject_index;
						});

						pq_detail[detail_index].reject_id = reject_reason;
						current_reject_index = -1;

						//reset reason/textboxes in dialog box
						u.eid("reject_reason").value = "";
						u.eid("reject_notes").value = "";

						//close dialog, reinit submit button
						$("#reject_dialog").dialog('close');
						open_submit();
					}

				}
			});

		}

		//handles rejecting a part
		function reject_handler(targ) {

			//create message to user as safety guard
			var message = "Are you sure you want to reject this part? (part must be submitted in a seperate parts request)";

			//send message to user (return if they hit cancel)
			if (!confirm(message))
				return;

			//work our way to the pq_detail id (id of part number input field)
			var td = targ.parentNode;
			var tr = td.parentNode;

			//get part number & id from table
			var part_td = tr.childNodes[3];
			var id = part_td.childNodes[0].id;
			var part_number = part_td.childNodes[0].value;

			//also need quantity
			var quantity_td = tr.childNodes[4];
			var quantity = quantity_td.childNodes[0].value;

			//initalize form data (will carry all form data over to server side)
			var fd = new FormData();

			//get values and pass them to fd 
			fd.append('id', id);
			fd.append('partNumber', part_number);
			fd.append('quantity', quantity);
			fd.append('quoteNumber', pq_overview[current_index].quoteNumber);

			//add tell variable
			fd.append('tell', 'reject_part');

			//send to server
			$.ajax({
				url: 'terminal_allocations_helper.php',
				type: 'POST',
				processData: false,
				contentType: false,
				data: fd,
				success: function(response) {

					//check for error response
					var check = response.substr(0, 5);
					if (check == "Error") {
						alert(response);
						return;
					}

					//grab response and parse
					var result = $.parseJSON(response);
					pq_detail = result;

					//show bill of materials for request
					show_bom(pq_overview[current_index].id);

					//alert user of success
					alert("This part has been successfuly rejected.");

					//run checks to see if we are ready to submit MO
					open_submit();

				}
			});

		}

		//handles functions related to splitting lines
		var split_handler = {

			//handles button click
			button_click: function(targ) {

				//clear previous splits
				document.querySelectorAll('.splitParts').forEach(function(a) {
					a.remove();
				})

				//work our way to the part number
				var td = targ.parentNode;
				var tr = td.parentNode;
				var part_td = tr.childNodes[3];
				var part = part_td.childNodes[0].value;

				//grab parts, quantity, and pull-from (decision) class
				var pq_parts = u.class("part_id"),
					pq_quantity = u.class("q_allocated"),
					pq_decision = u.class("decision");

				//initialize parts object (will hold all parts, quantites, and shop locations as we move through)
				var split_parts = [];

				//init total quantity on parts request
				var total_quantity = 0;

				//init PO check (flips to true if we have a PO assigned already)
				var po_check = false;

				//loop through part class to see if we have any matching parts (used to help make decisions during the split process)
				for (var i = 0; i < pq_parts.length; i++) {
					if (pq_parts[i].value == part) {

						//only add to split_parts array if we have a decision made
						if (pq_decision[i].value != "") {

							//create temp object to add to split parts
							var temp = {
								part: pq_parts[i].value,
								quantity: pq_quantity[i].value,
								decision: pq_decision[i].value,
								id: pq_parts[i].id
							};

							//push temp to split_parts
							split_parts.push(temp);
						}

						//add to total quantity
						total_quantity += parseFloat(pq_quantity[i].value);

						//check for PO
						if (pq_decision[i].value == "PO")
							po_check = true;

					}
				}

				//check part Number for "[" - shows that the part was subbed
				//init search
				var search = "";

				if (part.indexOf("[") > 0) {
					search = part.substr(0, part.indexOf("[") - 1);
				} else {
					search = part;
				}

				//set search to lower case
				search = search.toLowerCase();

				//get inventory index (if available)
				var inv_index = inventory.findIndex(object => {
					return object.partNumber.toLowerCase() == search;
				});

				//loop through pref shops and add any locations to the list that we do not have yet
				for (var i = 0; i < west_pref.length; i++) {

					//used to tell if we found a match
					var found = false;

					//check if we have stock (if not skip regardless)
					if (inventory[inv_index][west_pref[i]] > 0) {

						//loop through collected object
						for (var j = 0; j < split_parts.length; j++) {

							//if we find a match, break loop
							if (split_parts[j].decision == west_pref[i]) {
								found = true;
								break;
							}

						}

						//if we did not find, add to split_parts with 0 quantity
						if (!found) {

							//create temp object to add to split parts
							var temp = {
								part: inventory[inv_index].partNumber,
								quantity: 0,
								decision: west_pref[i],
								id: "NA"
							};

							//push to split_parts
							split_parts.push(temp);
						}
					}
				}

				//one last check for to see if we need to add PO to bottom of split
				if (!po_check) {

					//create temp object to add to split parts
					var temp = {
						part: inventory[inv_index].partNumber,
						quantity: 0,
						decision: "PO",
						id: "NA"
					};

					//push to split_parts
					split_parts.push(temp);

				}

				//once we have all parts, add to dialog and help make decision

				//set targets in table
				u.eid("target_pn").innerHTML = part;
				u.eid("target_quantity").innerHTML = total_quantity;
				//u.eid("target_shop").innerHTML = stock;

				//open dialog
				$("#partSplit-dialog").dialog({
					width: "auto",
					height: "auto",
					dialogClass: "fixedDialog",
				});

				//grab table to add rows
				var table = u.eid("partSplit-table");

				//loop through existing entries and to table
				for (var i = 0; i < split_parts.length; i++) {
					this.add_row(table, split_parts[i], inv_index);
				}

				//add final row to show current quantity
				var row = table.insertRow(-1);
				row.classList.add("splitParts");

				var cell = row.insertCell(0);
				cell.style.border = "none";

				var cell = row.insertCell(1);
				cell.style.border = "none";
				cell.style.fontWeight = "bold";
				cell.innerHTML = "Total Quantity";

				var cell = row.insertCell(2);
				cell.style.border = "none";
				cell.id = 'split_total';
				cell.fontWeight = "bold";
				cell.innerHTML = total_quantity;

				//update total w/color
				this.update_split_total();

			},

			//handles adding a line related to the part split table
			add_row: function(table, targ, inv_index) {

				//insert new row and add classname to it
				var row = table.insertRow(-1);
				row.classList.add("splitParts");

				//use cell_index to determine which cell is being inserted
				var cell_index = 0;

				//grab splitSpan (for styling) 
				var splitSpan = u.eid("splitSpan");

				//if it exists, incremenet rowspan by 1
				if (splitSpan) {
					splitSpan.rowSpan++;
				}
				//if not, create a new one
				else {
					//insert cell to hold "Substitution"
					var cell = row.insertCell(cell_index);
					cell.id = 'splitSpan';
					cell.innerHTML = "<b>Splits</b>"
					cell_index++;
				}

				//part number
				var cell = row.insertCell(cell_index);
				cell.innerHTML = inventory[inv_index].partNumber;
				cell_index++;

				//quantity
				var cell = row.insertCell(cell_index);
				var input = document.createElement("input");
				input.type = "number";
				input.value = targ.quantity;
				input.classList.add("quantity");
				input.classList.add("split_quantity");
				input.style.textAlign = "center";
				input.style.paddingLeft = "1em";
				input.addEventListener("change", this.update_split_total);

				//depending on decision, include inventory max number
				if (targ.decision != "PO")
					input.max = inventory[inv_index][targ.decision];

				//if id not NA then add id attribute
				if (targ.id != "NA")
					input.id = targ.id + "_q";

				cell.append(input);
				cell_index++;

				//Shop + Quantity
				var cell = row.insertCell(cell_index);
				var input = document.createElement("input");
				input.type = "text";

				//depending on decision, include inventory
				if (targ.decision == "PO")
					input.value = targ.decision;
				else
					input.value = targ.decision + " (" + inventory[inv_index][targ.decision] + ")";

				input.readOnly = true;
				input.classList.add("split_decision");
				input.tabIndex = -1;
				cell.append(input);
				cell_index++;
			},

			//adds up current split totals and lists at the bottom
			update_split_total: function() {

				//hold total
				var total = 0;

				//loop through each
				document.querySelectorAll('.split_quantity').forEach(function(a) {
					total += parseFloat(a.value);
				});

				//update total
				u.eid("split_total").innerHTML = total;

				//compare totals and color
				if (u.eid("split_total").innerHTML == u.eid("target_quantity").innerHTML)
					u.eid("split_total").style.backgroundColor = "#a2e9a2";
				else
					u.eid("split_total").style.backgroundColor = "#f99393";

			},

			//handles processing a split
			process_split: function() {

				//run basic checks to make sure we have sufficient part splits in place (sum of splits = total)

				//grab total
				var split_total = parseFloat(u.eid("target_quantity").innerHTML);

				//grab class for split quantity & split decision
				var split_quantity = u.class("split_quantity"),
					split_decision = u.class("split_decision");

				//loop through splits and subtract from total
				for (var i = 0; i < split_quantity.length; i++)
					split_total -= split_quantity[i].value;

				//total should be 0
				if (split_total != 0) {
					alert("Designated splits do not add up to total quantity");
					return;
				}

				//if we pass checks, 1) send info through ajax to update our database and 2) update user's screen
				var part = u.eid("target_pn").innerHTML,
					part_splits = [];

				//loop through split_quantity again, this time create object with id (if available) and shop
				for (var i = 0; i < split_quantity.length; i++) {

					//grab shop
					var shop = split_decision[i].value;

					//if it is not PO we need to parse to just shop
					if (shop != "PO")
						shop = shop.substr(0, shop.indexOf("(") - 1);

					//push to main array
					part_splits.push({
						quantity: split_quantity[i].value,
						shop: shop,
						id: split_quantity[i].id.substr(0, split_quantity[i].id.indexOf("_"))
					});

				}

				console.log(part_splits);

				//save current values before saving split
				//assign_mo(true);
				update_queue(true);

				//initalize form data (will carry all form data over to server side)
				var fd = new FormData();

				//serialize arrays and pass them to fd 
				fd.append('part', part);
				fd.append('pq_id', pq_overview[current_index].id);
				fd.append('part_splits', JSON.stringify(part_splits));

				//add tell variable
				fd.append('tell', 'process_split');

				//access database
				$.ajax({
					url: 'terminal_allocations_helper.php',
					type: 'POST',
					processData: false,
					contentType: false,
					data: fd,
					success: function(response) {

						//check for error response
						var check = response.substr(0, 5);
						if (check == "Error") {
							alert(response);
							return;
						}

						//grab response and parse
						var result = $.parseJSON(response);
						pq_detail = result;

						//show bill of materials for request
						show_bom(pq_overview[current_index].id);

						//alert user of success & close dialog
						alert("The split has been saved.");
						$("#partSplit-dialog").dialog('close');

						//run checks to see if we are ready to submit MO
						open_submit();

					}
				});
			}
		}

		//handle updating tables if we have identifz.ied a change
		function new_list_item(index) {

			//grab div element & set class list to use
			var div = u.eid("pq-list");
			var use_classlist = "open-pending";

			//add label 
			var label = document.createElement("Label");
			label.setAttribute("for", "pq-" + index);
			label.id = 'pq-label-' + index;

			//depending on urgency, add classname
			if (pq_overview[index].status == "Submitted")
				label.classList.add("closed_style");
			else if (pq_overview[index].status == "Rejected")
				label.classList.add("rejected_style");
			else if (pq_overview[index].urgency == "[Standard]")
				label.classList.add("standard_style");
			else if (pq_overview[index].urgency == "[Urgent]")
				label.classList.add("urgent_style");
			else if (pq_overview[index].urgency == "[Greensheet]")
				label.classList.add("greensheet_style");
			else
				label.classList.add("overnight_style");

			//add class to label so we know to remove it if we resort
			label.classList.add(use_classlist);

			//add additional class name
			label.classList.add("list-item");

			//add to div
			label.innerHTML += pq_overview[index].urgency + " P#: " + pq_overview[index].project_id + " | ";

			//create span element with open
			var span = document.createElement("SPAN");
			span.id = 'status' + index;

			//text node for status span
			var text = document.createTextNode(pq_overview[index].status);
			span.appendChild(text);

			//add to label
			label.appendChild(span);

			//add the rest of the label text
			label.innerHTML += " | Due: " + format_date(pq_overview[index].due_date) + " | <i class = 'date_required'>" + utc_to_local(pq_overview[index].requested) + "</i>";

			//if this is amended, add a big A
			//if (amended[index] != "" && amended[index] != null){
			//	label.innerHTML += "&nbsp;&nbsp;&nbsp;<span style = 'color:red; font-size:22px'>(A)</span>"
			//}

			//append to parent div
			div.appendChild(label);

			//add input
			var input = document.createElement("input");
			input.type = "radio";
			input.setAttribute("name", "pq");
			input.id = "pq-" + index;
			input.addEventListener('click', z.show_info);
			input.classList.add(use_classlist);
			div.appendChild(input);

			//reinitialize toggle menu
			$(".shape-bar, .pq").controlgroup();
			$(".pq").controlgroup({
				direction: "vertical"
			});

		}

		$(function() {
			var availableTags = <?= json_encode($emails); ?>;

			function split(val) {
				return val.split(/,\s*/);
			}

			function extractLast(term) {
				return split(term).pop();
			}

			$(".tags")
				// don't navigate away from the field on tab when selecting an item
				.on("keydown", function(event) {
					if (event.keyCode === $.ui.keyCode.TAB &&
						$(this).autocomplete("instance").menu.active) {
						event.preventDefault();
					}
				})
				.autocomplete({
					minLength: 0,
					source: function(request, response) {
						// delegate back to autocomplete, but extract the last term
						response($.ui.autocomplete.filter(
							availableTags, extractLast(request.term)));
					},
					focus: function() {
						// prevent value inserted on focus
						return false;
					},
					select: function(event, ui) {
						var terms = split(this.value);
						// remove the current input
						terms.pop();
						// add the selected item
						terms.push(ui.item.value);
						// add placeholder to get the comma-and-space at the end
						terms.push("");
						this.value = terms.join("; ");
						return false;
					}
				});
		});

		//set options to parts array (renders autocomplete options)
		var options = {
			source: inventory.map(a => a.partNumber),
			minLength: 2
		};

		//choose selector (input with part as class)
		var selector = '.part_search';

		//on keydown, show autocomplete after 2 characters
		$(document).on('keydown.autocomplete', selector, function() {
			$(this).autocomplete(options);
		});

		//handles getting parts that have minimum stock
		function get_minimum_stock() {

			//initalize form data (will carry all form data over to server side)
			var fd = new FormData();

			//pass shop variable based on project_id
			if (u.eid("project_id").value.substr(2) == "3-999899")
				fd.append('shop', "OMA");
			else if (u.eid("project_id").value.substr(2) == "6-999899")
				fd.append('shop', "CHA");

			//pass shop info (stored in project_id) & project id
			fd.append('id', pq_overview[current_index].id);
			fd.append('vp_num', u.eid("project_id").value);

			//add tell variable
			fd.append('tell', 'get_minimum_stock');

			//send to server
			$.ajax({
				url: 'terminal_allocations_helper.php',
				type: 'POST',
				processData: false,
				contentType: false,
				data: fd,
				success: function(response) {

					//check for error response
					var check = response.substr(0, 5);
					if (check == "Error") {
						alert(response);
						return;
					}

					//grab response and parse
					var result = $.parseJSON(response);
					pq_detail = result;

					//show bill of materials for request
					show_bom(pq_overview[current_index].id);

					//alert user of success
					alert("Parts under the minimum order have been added to the queue.");

					//run checks to see if we are ready to submit MO
					open_submit();

				}
			});
		}

		//handles tabs up top that toggle between divs
		function change_tabs(pageName, elmnt, color) {

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

		//add event listener to instructions notes, allows for auto expansion and collapse on double-click
		$(document).on('dblclick', '.instructions', function() {

			//resets height and width
			this.style.height = "22px";
			this.style.width = "300px";

		});

		$(document).ajaxStart(function() {
			if (!updating)
				waiting('on');
		});

		$(document).ajaxStop(function() {
			if (!updating)
				waiting('off');
		});

		//windows onload
		window.onload = function() {

			//add event listener logic to notify the user before they exit the site if they have potential unsaved data
			window.addEventListener("beforeunload", function(e) {

				if (target_ids.length == 0) {
					return undefined;
				}

				var confirmationMessage = 'It looks like you have been editing something. ' +
					'If you leave before saving, your changes will be lost.';

				(e || window.event).returnValue = confirmationMessage; //Gecko + IE
				return confirmationMessage; //Gecko + Webkit, Safari, Chrome etc.
			});

			//add event listener to inputs
			$('.check_input').on("change", function() {
				refresh_target();
			});

			// Get the element with id="defaultOpen" and click on it
			u.eid("defaultOpen").click();

			//init queue
			refresh_queue();

		}

		//set interval to check orders every 30 seconds
		var myInterval = setInterval(update_queue, 60000);
	</script>

</body>

<?php

//reset return address once the page has loaded
unset($_SESSION['returnAddress']);

//close SQL connection
$mysqli->close();

?>

</html>