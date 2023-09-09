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
$directory = [];
$query = "select CONCAT(firstName, ' ', lastName) as full_name, email from fst_users order by email";
$result = mysqli_query($con, $query);

while ($rows = mysqli_fetch_assoc($result)) {
	array_push($directory, $rows);
}

//initialize allocations_mo object to be passes to js
$allocations_mo = [];

//load in any material orders open for this shop
$query = "select * from fst_allocations_mo where ship_from = 'PO' order by closed asc, date_created asc;";
$result = mysqli_query($con, $query);

//cycle thorugh query and assign to different arrays
//add entry for each mo 
while ($rows = mysqli_fetch_assoc($result)) {

	//push info to allocations_mo
	array_push($allocations_mo, $rows);
}

//init arrays
$pq_detail = [];
$pq_overview = [];
$pq_orders = [];
$pq_shipments = [];
$state_pref = [];

//init array of parts with no descriptoin
$parts_with_no_description = [];

//grabs detail (actual parts requested)
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
$result =  mysqli_query($con, $query);

while ($rows = mysqli_fetch_assoc($result)) {
	array_push($pq_detail, $rows);

	//if description is null, add to array (need to try to pull in a different description)
	if (is_null($rows['partDescription']))
		array_push($parts_with_no_description, $rows['part_id']);
}

//look for parts with no description in our fst_newparts table (user entered parts)
$target_parts = "'" . implode("','", $parts_with_no_description) . "'";

//only accept tasks that are not blank or denoted with no service request & if a status is complete, only include if within the last 30 days
$query = "SELECT partNumber, description, manufacturer, uom, cost FROM fst_newparts WHERE partNumber IN (" . $target_parts . ") ORDER BY date DESC;";
$result =  mysqli_query($con, $query);

//reuse parts_with_no_description
$parts_with_no_description = [];

while ($rows = mysqli_fetch_assoc($result)) {
	array_push($parts_with_no_description, $rows);
}

//grabs relevant pq_overview info needed
$query = "select id, quoteNumber, project_name, cust_pn, oem_num, bus_unit_num, loc_id, staging_loc FROM fst_pq_overview WHERE status != 'Closed';";
$result =  mysqli_query($con, $query);

while ($rows = mysqli_fetch_assoc($result)) {

	//push temp array to project array
	array_push($pq_overview, $rows);
}

//grabs previous order info
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
				WHERE a.po_number IS NOT NULL
				GROUP BY a.po_number, z.pq_id
				ORDER BY a.po_number ASC;";
$result =  mysqli_query($con, $query);

//set previous po_number (used to group multiple together)
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
		$pq_orders[sizeof($pq_orders) - 1]['quoteNumber'] .= "|" . $rows['quoteNumber'];
		//$pq_orders[sizeof($pq_orders) - 1]['googleDriveLink'] .= "|" . $rows['googleDriveLink'];
		$pq_orders[sizeof($pq_orders) - 1]['project_name'] .= "|" . $rows['project_name'];
		$pq_orders[sizeof($pq_orders) - 1]['vp_id'] .= "|" . $rows['vp_id'];
		$pq_orders[sizeof($pq_orders) - 1]['pq_id'] .= "|" . $rows['pq_id'];
	}
	//otherwise push new entry
	else
		array_push($pq_orders, $rows);

	//set new previous po
	$previous_po = $rows['po_number'];
}

//grabs shipment info
$query = "select * FROM fst_pq_orders_shipments;";
$result =  mysqli_query($con, $query);

while ($rows = mysqli_fetch_assoc($result)) {

	//push temp array to project array
	array_push($pq_shipments, $rows);
}

//grabs state pref for decision making
$query = "SELECT stAbv, allocation FROM inv_statepref;";
$result =  mysqli_query($con, $query);

while ($rows = mysqli_fetch_assoc($result)) {

	//push temp array to project array
	array_push($state_pref, $rows);
}

//initialize array for previous vendor list
$previous_orders = [];

//query to grab any subs available
$query = "SELECT part_id, sum(q_allocated) as 'q_allocated', vendor, sum(vendor_qty) as 'vendor_qty' 
			FROM fst_pq_detail 
			WHERE vendor is not null AND vendor <> '' GROUP BY part_id, vendor ORDER BY part_id, vendor_qty desc;";
$result =  mysqli_query($con, $query);

while ($rows = mysqli_fetch_assoc($result)) {

	//push temp array to project array
	array_push($previous_orders, $rows);
}

//run query to grab locations
$query = "SELECT * from general_shippingadd where customer = 'PW' OR customer = 'PW-Custom' order by name";
$result = mysqli_query($con, $query);

//init arrays
$staging_locations = [];

while ($rows = mysqli_fetch_assoc($result)) {

	//push temp array to project array
	array_push($staging_locations, $rows);
}

//run query to grab manually entered addresses
$query = "SELECT * from fst_allocations_mo_manual_address;";
$result = mysqli_query($con, $query);

//init arrays
$manual_locations = [];

while ($rows = mysqli_fetch_assoc($result)) {

	//push temp array to project array
	array_push($manual_locations, $rows);
}

//get pq_order notes
$po_notes = [];
$query = "SELECT CONCAT(b.firstName, ' ', b.lastName) as name, a.* FROM fst_pq_orders_notes a, fst_users b WHERE a.user = b.id ORDER BY a.id desc;";
$result =  mysqli_query($con, $query);

while ($rows = mysqli_fetch_assoc($result)) {
	array_push($po_notes, $rows);
}

?>

<!doctype html>
<html>

<head>
	<meta charset="utf-8">
	<meta name="google-signin-client_id" content="573761357198-hin7ae7q19qgvoab7t0781b41530546g.apps.googleusercontent.com">
	<link rel="shortcut icon" type="image/x-icon" href="images/P-Logo.png" />
	<link href="stylesheets/jquery-ui-themes-1.13.0/themes/ui-lightness/jquery-ui.css" rel="stylesheet">
	<link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css'>
	<link rel="stylesheet" href="stylesheets/element-styles.css?<?= $version; ?>">

	<title>Orders Hub (v<?= $version ?>) - Pierson Wireless</title>

	<style>
		/**style open, closed, complete _orders_row,  on hover */
		.open_orders_row:hover,
		.complete_orders_row:hover,
		.closed_orders_row:hover {
			background-color: #a7c2ee;
		}

		/**overwrite for orders_detail class */
		.orders_detail {
			background-color: #f7f9fb !important;
		}

		/**style cursor on headers so user knows they can click on it */
		.orders_table_headers th {
			cursor: pointer;
		}

		/**force open order header to be 'sticky' */
		.sticky_order_header {
			position: sticky;
			top: 46px;
			z-index: 100;
			background: white;
		}

		/**style active cell */
		.active_cell {
			border: 3px solid #114b95 !important;
		}

		/**style email textares */
		.emails,
		.email_text_areas {
			width: 798px;
			resize: vertical;
		}

		/**assign color and mouse to remove part X */
		.remove_part {
			color: red;
			padding-right: 3px;
			cursor: pointer;
		}

		/**Assign widths to purchase order report input fields */
		.inputs {
			width: 95%;
		}

		.po_ship_to {
			width: 15em;
		}

		.taxes,
		.freight,
		.total_price,
		.date_issued {
			width: 7em;
		}

		.unassigned_items {
			width: 3em;
		}

		.expected_date_overview {
			width: 8.5em;
		}

		/**style notes so users know they can click on it */
		.notes {
			cursor: pointer;
		}

		/* style X to it is red */
		.delete_shipment,
		.delete_shipment_item {
			color: red;
			cursor: pointer;
		}

		.quoteNumber {
			width: 8em;
		}

		.project_name {
			width: 100%
		}

		.shipping_qty {
			width: 4.5em;
		}

		.shipment_checkbox {
			height: 1em;
		}

		.shipment_button {
			margin-left: 2em;
			margin-top: 2em;
		}

		/**style input field & drop-down so they always appear next to each other */
		.vendor {
			vertical-align: top;
			float: left;
			display: inline-block;
			width: 12em;
		}

		.show_all_vendors {
			cursor: pointer !important;
			height: 26px;
			width: 28px;
			padding: 0;
			color: #81c9ff;
			background: white;
			border: 1.5px solid lightgrey;
			display: inline-block;
			vertical-align: top;
			margin-left: 161px;
			margin-top: -26px;
			float: left;
		}

		.order_detail_table {
			margin: 1.6em 2em;
		}

		.order_detail_table td {
			padding: 7px;
		}

		.order_detail_header td {
			font-weight: bold;
			border: 0px solid #000000;
			text-align: center;
		}

		.custom-combobox-input {
			background: #BBDFFA !important;
			border-color: #000B51 !important;
			border-width: medium !important;
			font-size: 14px !important;
			font-family: Arial, Helvetica, sans-serif !important;
			padding-left: 3px !important;
			height: 8px !important;
			font-weight: lighter !important;
			color: black !important;
			width: 19em !important;
		}

		.custom-combobox {
			position: relative;
			display: inline-block;
		}

		.custom-combobox-toggle {
			position: absolute;
			top: 0;
			bottom: 0;
			margin-left: -1px;
			padding: 0;
		}

		.custom-combobox-input {
			margin: 0;
			padding: 5px 10px;
		}

		/**style po_number link  */
		.po_number_link {
			color: #1700ff;
			text-decoration: underline;
			cursor: pointer;
		}

		.cost,
		.vendor_qty,
		.vendor_cost,
		.po_number {
			width: 6em;
		}

		.vendor_total {
			width: 8em;
			cursor: pointer;
		}

		.standard_input {
			width: 22.8em;
			-ms-box-sizing: content-box;
			-moz-box-sizing: content-box;
			box-sizing: content-box;
			-webkit-box-sizing: content-box;
		}

		#vendor_poc_table th {
			padding-right: 2.4em;
		}

		.vendor_po_tables th {
			text-align: left;
			padding-right: 1em;
		}

		.vendor_po_tables {
			padding: 1em;
		}

		#shipment_parts_table {
			margin: 0em 2em 1em 2em;
		}

		#shipment_parts_table th {
			text-align: center;
		}

		.ui-menu {
			overflow-y: scroll !important;
			max-height: 15em !important;
			z-index: 500;
		}

		.ui-menu-item {
			padding: 5px 0px;
			z-index: 500;
		}

		#create_order_button {
			margin-top: 1em;
		}

		.large_button {
			font-size: 20px !important;
			width: 14em;
			height: 2em;
			text-align: center;
		}

		.cancel_button {
			background: red;
			margin-top: 1em;
			font-weight: bold;
		}

		.ready {
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
			width: 5em;
		}

		.q_allocated {
			width: 5em;
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

		.qty_main,
		.qty_on_order,
		.qty_on_project {
			width: 5em;
		}

		.subs {
			width: 5em;
		}

		.instructions {
			height: 13px;
			width: 300px;
			resize: horizontal;
		}

		.stock {
			text-align: center;
			font-weight: bold;
		}

		/* Style the tab content (and add height:100% for full page content) */
		.tabcontent {
			padding: 15px 20px !important;
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

		.pq_info {
			clear: left;
			display: none;
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
	//$header_names = ['Order Requests', 'Vendor List', 'Purchase Order Report'];
	//$header_ids = ['parts_orders', 'vendor_list', 'open_orders'];
	$header_names = ['Order Requests', 'Purchase Request', 'Purchase Order Report'];
	$header_ids = ['parts_orders', 'purchase_request', 'open_orders'];

	//pass to php function to create navigation bars
	echo create_navigation_bar($header_names, $header_ids, "update_queue(false)", $fstUser);

	?>

	<!--CONSTANT IN constants.php-->

	<div style='padding-left:1em;padding-top:4em;'>

		<!--CONSTANT IN constants.php-->
		<?= constant('terminal_navigation') ?>

		<?php

		//if this is dustin OR admin, show temp PO replacement
		if ($_SESSION['employeeID'] == "4" || $fstUser['accessLevel'] == "Admin") {
			echo "<a href = 'terminal_orders_updatePO.php' style = 'margin-left:-4px;'> <button>[Temp] Update PO # </button></a>";
		}

		?>

	</div>

	<div id='parts_orders' class='tabcontent'>

		<h1 id='pq_header'> Parts Orders </h1>

		<a href='terminal_orders_vendors.php?' target='_blank'>Vendor List</a><br><br>
		<button onclick='open_stock_order("OMA")' style='float:left;'>Open OMA Stock Order</button>
		<button onclick='open_stock_order("CHA")' style='float:left;'>Open CHA Stock Order</button> <br><br>

		<div class="pq-wrap" id='pq-sel-div' style='float:left; padding-right: 2em'>
			<h3>Purchase Order Queue</h3>
			<div id='pq-list' class="pq" style='padding-bottom: 5em; float: left;'>

			</div>
		</div>

		<div style='clear: left' class='pq_info'>
			<table class="pr_tables" id="pr_table">
				<tr>
					<th colspan="2">
						<h3>Project Details</h3>
					</th>
				</tr>
				<tr>
					<td>Project #: </td>
					<td class="partRequestTD">
						<input type="text" id='project_id' style="width: 250px" readonly>
					</td>
				</tr>
				<tr>
					<td>Project Name: </td>
					<td class="partRequestTD">
						<input type="text" id='project_name' style="width: 250px" readonly>
					</td>
				</tr>
				<tr>
					<td>Requested By: </td>
					<td class="partRequestTD">
						<input type="text" id='requested_by' style="width: 250px" readonly>
					</td>
				</tr>
				<tr>
					<td>Due By: </td>
					<td class="partRequestTD">
						<input type="date" id='date_required' style="width: 253px" readonly>
						<input type='checkbox' id='early_delivery' disabled> <i>Early Delivery Accepted?</i>
					</td>
				</tr>
				<tr>
					<td>Add CC: </td>
					<td class="partRequestTD">
						<textarea class="emails" id='cc_email' style="width: 460px; height: 50px; resize: vertical" readonly></textarea>
						<!--<input class = "requiredPR emails" type = "text" id = 'cc_email' style = "width: 250px" >!-->
					</td>
				</tr>
				<tr>
					<td>Notes: </td>
					<td class="partRequestTD">
						<textarea id='notes' style="width: 460px; height: 50px; resize: vertical"></textarea>
					</td>
				</tr>
				<tr>
					<td>Customer Project #: </td>
					<td class="partRequestTD">
						<input type="text" id='cust_pn' style="width: 250px" readonly>
					</td>
				</tr>
				<tr>
					<td>OEM Reg #: </td>
					<td class="partRequestTD">
						<input type="text" id='oem_num' style="width: 250px" readonly>
					</td>
				</tr>
				<tr>
					<td>Business Unit #: </td>
					<td class="partRequestTD">
						<input type="text" id='bus_unit_num' style="width: 250px" readonly>
					</td>
				</tr>
				<tr>
					<td>Location ID: </td>
					<td class="partRequestTD">
						<input type="text" id='loc_id' style="width: 250px" readonly>
					</td>
				</tr>
			</table>

			<table class="pr_tables">
				<tr>
					<th colspan="2">
						<h3>Ship To Address</h3>
					</th>
				</tr>
				<tr>
					<td>Ship To: </td>
					<td>
						<select class="custom-select check_input" id='shipping_place' style="width: 250px" onchange='adjust_ship_to(this.value)'>
							<option>Staging Location</option>
							<option>Final Destination</option>
							<option>Manual Entry</option>
						</select>
					</td>
				</tr>
				<tr>
					<td>POC Name: </td>
					<td><input class="requiredPR check_input" type="text" id='poc_name' style="width: 250px"></td>
				</tr>
				<tr>
					<td>POC Number: </td>
					<td><input class="requiredPR check_input" type="text" id='poc_number' style="width: 250px"></td>
				</tr>
				<tr>
					<td>Location Name: </td>
					<td class="partRequestTD">
						<input class="requiredPR check_input" id='ship_to' style="width: 250px">
					</td>
				</tr>
				<tr>
					<td>Address: </td>
					<td class="partRequestTD"><input class="requiredShip check_input" type="text" id='street' style="width: 250px"></td>
				</tr>
				<tr>
					<td>City: </td>
					<td class="partRequestTD"><input class="requiredShip check_input" type="text" id='city' style="width: 250px"></td>
				</tr>
				<tr>
					<td>State: </td>
					<td class="partRequestTD">
						<select class="requiredShip custom-select check_input" id='state' style="width: 250px">
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
					<td class="partRequestTD"><input class="requiredShip check_input" type="text" id='zip' style="width: 250px"></td>
				</tr>
				<tr>
					<td>Lift Gate Required? </td>
					<td class="partRequestTD">
						<select class="custom-select check_input" id='liftgate' style="width: 250px" disabled>
							<option></option>
							<option value='N'>N</option>
							<option value='Y'>Y</option>
						</select>
					</td>
				</tr>
				<tr>
					<td>Scheduled Delivery </td>
					<td class="partRequestTD">
						<select class="custom-select check_input" id='sched_opt' style="width: 250px" onchange='z.scheduleSelect(this.value)' disabled>
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

			<div style='padding-bottom: 10em;'></div>

		</div>

		<div class='pq_info' style='padding-bottom: 5em;'>

			<table id='pq-parts-table' style='border-collapse: collapse;'>
				<thead>
					<tr>
						<th><!-- remove part column--></th>
						<th><!-- kick back column--></th>
						<th><!-- split line column--></th>
						<th>Part Number</th>
						<th style='width: 3em;'>Qty Req'd</th>
						<th>Unit Cost</th>
						<th>UOM</th>
						<th>Manufacturer</th>
						<th>Order #</th>
						<th style='width: 10em;'>Vendor</th>
						<th>Order Qty</th>
						<th>Cost/Unit</th>
						<th>Line Total</th>
						<th style='width: 3em;'>Qty in Main Shops</th>
						<th style='width: 3em;'>Excess on Order</th>
						<th style='width: 3em;'>Project on Order</th>
						<th style='width: 10em;'>External Notes</th>
					</tr>
				</thead>

				<tbody>
					<!--filled with function add_item()!-->
				</tbody>

			</table>

			<br>

			<form action="materialEntry.php" method="get">
				<input id='material_entry_id' name='PQID' style='display:none'>
				<input class='large_button' type='submit' value='Add Materials' style='font-size: 20px !important'>
			</form>

		</div>

	</div>

	<div id='vendor_list' class='tabcontent' style='display: none'>
		<table id='vendor_list_table' class='standardTables'>
			<thead>
				<tr>
					<th>Vendor Name</th>
					<th>Vendor POC</th>
					<th>Vendor Phone #</th>
					<th>Vendor Address</th>
					<th>Vendor City</th>
					<th>Vendor State</th>
					<th>Vendor Zip Code</th>
				</tr>
			</thead>
			<tbody>

				<?php

				$vendors = [];
				$query = "select * from fst_vendor_list order by vendor;";
				$result = mysqli_query($con, $query);

				while ($rows = mysqli_fetch_assoc($result)) {
					array_push($vendors, $rows);
					/*
					?>

					<!--<tr>
						<td><input value = "<?= $rows['vendor']; ?>"></td>
						<td><input value = "<?= $rows['poc']; ?>"></td>
						<td><input value = "<?= $rows['phone']; ?>"></td>
						<td><input value = "<?= $rows['street']; ?>"></td>
						<td><input value = "<?= $rows['city']; ?>"></td>
						<td><input value = "<?= $rows['state']; ?>"></td>
						<td><input value = "<?= $rows['zip']; ?>"></td>
						
					</tr>!-->
				
					<?php
					*/
				}

				//same thing for vendor poc's
				$vendor_poc = [];
				$query = "select * from fst_vendor_list_poc order by vendor_id;";
				$result = mysqli_query($con, $query);

				while ($rows = mysqli_fetch_assoc($result)) {
					array_push($vendor_poc, $rows);
				}

				?>

				<!--to be entered by init_vendor_list() !-->
			</tbody>

		</table>
	</div>

	<div id='purchase_request' class='tabcontent' style='display: none'>

		<h3>Open Vendor Items</h3>

		<div id='vendor-list' class="vendor-checkbox" style='float: left; padding-bottom:20em; padding-right:2em;'></div>

		<table id='vendor-parts-table' style='border-collapse: collapse; margin-top: 3em; display: none;'>
			<thead>
				<tr>
					<th><!-- placeholder for checkbox --></th>
					<th>Project #</th>
					<th>Project Name</th>
					<th>Ship To</th>
					<th>Part Number</th>
					<th>Order #</th>
					<th style='width: 10em;'>Vendor</th>
					<th>Order Qty</th>
					<th>Cost/Unit</th>
					<th>Line Total</th>
					<th style='width: 10em;'>External Notes</th>
				</tr>
			</thead>

			<tbody>
				<!--filled with function po_add_item()!-->

				<tr>
					<td><!-- placeholder for checkbox --></td>
					<td colspan='10'>
						<button class='large_button' id='create_order_button' onclick='create_purchase_orders()'>Create Purchase Order</button>
					</td>
				</tr>
			</tbody>

		</table>

		<!--WRAPPER FOR LIST ITEMS & PDF SO INFO FALLS BELOW VENDOR LIST-->
		<div style='clear:left'>

			<h3>Open Purchase Orders</h3>

			<div id='po-list' class="po-radiobox" style='float: left; padding-bottom:70em;'></div>

			<!--WRAPPER FOR LIST ITEMS SO THEY STAY LEFT OF PDF-->
			<div class='vendor_info' id='vendor_po_div' style='float:left; margin-left: 3em; display: none'>

				<button onclick='export_pdf_handler("preview")' id='preview_vendor_po'>Preview Vendor PO</button>
				<button onclick='validate_pdf_handler("current")' id='send_button'>Send to Vendor</button>
				<button onclick='validate_pdf_handler("all")' id='send_button_all'>Send to all Vendors</button>
				<button onclick='validate_pdf_handler("none")' id='send_button_none'>Process (do not send)</button>
				<button onclick='delete_purchase_order()' id='remove_po_button'>Delete Purchase Order</button>
				<!-- <button onclick = 'update_queue(false, "current")' id = 'send_button'>Send to Vendor</button>
                <button onclick = 'update_queue(false, "all")' id = 'send_button_all'>Send to all Vendors</button>
                <button onclick = 'update_queue(false, "none")' id = 'send_button_none'>Process (do not send)</button> -->

				<br><br>

				<b style='float:left; padding-right: 1.2em; padding-left: 1.2em'>Finalize Order:</b> <input type='checkbox' class='refresh_vendor ready' id='ready'>

				<br>

				<!--WRAPPER FOR FIRST TWO TABLES SO THEY STAY ON TOP-->
				<div style='float:left'>

					<table class='vendor_po_tables' style='float:left'>
						<tr>
							<th>PO #/Revision</th>
							<td>
								<input type='text' id='po_number' style='width:10.9em;' class='standard_input refresh_vendor' readonly>
								<input type='text' id='revision' style='width:10.9em;' class='standard_input refresh_vendor' readonly>
							</td>
						</tr>
						<tr>
							<th>Vendor</th>
							<td><input type='text' id='vendor_name' class='standard_input refresh_vendor' readonly> </td>
						</tr>
						<tr>
							<th>Vendor ID</th>
							<td><input type='text' id='vendor_id' class='standard_input refresh_vendor' readonly> </td>
						</tr>
						<tr>
							<th>Street</th>
							<td><input type='text' id='vendor_street' class='standard_input refresh_vendor'></td>
						</tr>
						<tr>
							<th>Street 2</th>
							<td><input type='text' id='vendor_street2' class='standard_input refresh_vendor'></td>
						</tr>
						<tr>
							<th>*City, State, Zip</th>
							<td><input type='text' id='vendor_city' style='width: 10em' class='refresh_vendor'> &nbsp;
								<select type='text' id='vendor_state' style='width: 5em' class='custom-select refresh_vendor'>
									<option></option>
									<?php
									//read from query into arrays
									for ($i = 0; $i < sizeof($states); $i++) {
									?>
										<option><?= $states[$i]; ?></option>
									<?php
									}
									?>
								</select> &nbsp; <input type='text' id='vendor_zip' style='width: 6.6em' class='refresh_vendor'>
							</td>
						</tr>
					</table>

					<table class='vendor_po_tables' style='float:right'>
						<tr>
							<th>*Date Ordered</th>
							<td><input type='date' id='date_ordered' class='standard_input refresh_vendor'> </td>
						</tr>
						<tr>
							<th>*Ordered By</th>
							<td><input type='text' id='ordered_by' class='standard_input refresh_vendor'> </td>
						</tr>
						<tr>
							<th>*Need By Date</th>
							<td><input type='date' id='need_by_date' class='standard_input refresh_vendor'></td>
						</tr>
						<tr>
							<th>*Payment Terms</th>
							<td>
								<select type='text' id='payment_terms' style='width: 23.6em' class='custom-select refresh_vendor'>
									<option>Net 30 Days</option>
									<option>Credit Card</option>
								</select>
							</td>
						</tr>
						<tr>
							<th>*Ship Via</th>
							<td>
								<select type='text' id='ship_via' style='width: 23.6em' class='custom-select refresh_vendor'>
									<option></option>

									<?php

									$query = "SELECT * FROM general_shipping_options;";
									$result = mysqli_query($con, $query);

									while ($rows = mysqli_fetch_assoc($result)) {

									?>

										<option><?= $rows['option']; ?></option>

									<?php
									}

									?>
								</select>
							</td>
						</tr>
					</table>
				</div>

				<!--WRAPPER FOR LAST TWO TABLES SO THEY STAY ON BOTTOM-->
				<div style='float:left; clear:left'>
					<table class='vendor_po_tables' style='float:left'>
						<tr>
							<th>Bill To</th>
							<td><input type='text' id='bill_to' class='standard_input refresh_vendor' readonly> </td>
						</tr>
						<tr>
							<th>Street</th>
							<td><input type='text' id='bill_to_street' class='standard_input refresh_vendor' readonly></td>
						</tr>
						<tr>
							<th>*City, State, Zip</th>
							<td>
								<input type='text' id='bill_to_city' style='width: 10em' class='refresh_vendor' readonly> &nbsp;
								<input type='text' id='bill_to_state' style='width: 4.5em' class='refresh_vendor' readonly> &nbsp;
								<input type='text' id='bill_to_zip' style='width: 5em; float: right;' class='refresh_vendor' readonly>
							</td>
						</tr>
					</table>

					<table class='vendor_po_tables' style='float:right'>
						<tr>
							<th>*Ship To</th>
							<td>
								<select class='custom-select standard_input refresh_vendor' id="po_ship_to" onchange="update_shipping_info(this.value, 'po')">
									<option></option>
									<option>Temporary Location</option>
									<option>Add Custom Location</option>
									<optgroup label="PW Locations">
										<?php

										//read out staging locations to select drop-down
										for ($i = 0; $i < sizeof($staging_locations); $i++) {

											if ($staging_locations[$i]['customer'] == "PW") {

										?>

												<option> <?= $staging_locations[$i]['name']; ?></option>

										<?php

											}
										}

										?>
									</optgroup>
									<optgroup label="Custom Locations" id='custom_staging_locations'>
										<?php

										//read out staging locations to select drop-down
										for ($i = 0; $i < sizeof($staging_locations); $i++) {

											if ($staging_locations[$i]['customer'] == "PW-Custom") {

										?>

												<option> <?= $staging_locations[$i]['name']; ?></option>

										<?php

											}
										}

										?>
									</optgroup>

								</select>
						</tr>
						<tr id='po_ship_to_temporary_row' style='visibility: collapse;'>
							<th>Ship To <span style='font-size: 15px;'>(manual)</span></th>
							<td><input type='text' id='po_ship_to_temporary' class='standard_input refresh_vendor'></td>
						</tr>
						<tr>
							<th>*Street</th>
							<td><input type='text' id='po_ship_to_street' class='standard_input refresh_vendor'></td>
						</tr>
						<tr>
							<th>Street 2</th>
							<td><input type='text' id='po_ship_to_street2' class='standard_input refresh_vendor'></td>
						</tr>
						<tr>
							<th>*City, State, Zip</th>
							<td><input type='text' id='po_ship_to_city' style='width: 10em' class='refresh_vendor'> &nbsp;
								<select type='text' id='po_ship_to_state' style='width: 5em' class='custom-select refresh_vendor'>
									<option></option>
									<?php
									//read from query into arrays
									for ($i = 0; $i < sizeof($states); $i++) {
									?>
										<option><?= $states[$i]; ?></option>
									<?php
									}
									?>
								</select> &nbsp; <input type='text' id='po_ship_to_zip' style='width: 6.6em' class='refresh_vendor'>
							</td>
						</tr>
						<tr>
							<th>*Attention</th>
							<td><input type='text' id='attention' class='standard_input refresh_vendor'></td>
						</tr>
						<tr>
							<th>Email</th>
							<td><input type='text' id='attention_email' class='standard_input refresh_vendor'></td>
						</tr>
						<tr>
							<th>*Phone</th>
							<td><input type='text' id='attention_phone' class='standard_input refresh_vendor'></td>
						</tr>
					</table>
				</div>

				<br>

				<table id='vendor_poc_table' class='vendor_po_tables' style='float:left'>
					<tr>
						<th>Vendor POC</th>
						<td>
							<div class="ui-widget">
								<select id="vendor-poc-combobox">
									<option></option>
								</select>
							</div>
						</td>
					</tr>
					<tr>
						<th>POC Phone</th>
						<td><input class='standard_input' id='vendor_phone' readonly></td>
					</tr>
					<tr>
						<th>POC Email</th>
						<td><input class='standard_input' id='vendor_email' readonly></td>
					</tr>
				</table>

				<table class='vendor_po_tables' style='padding-left: 1.3em; padding-bottom: 2em;'>
					<tr>
						<th style='padding-right: 5.4em;'>Taxes</th>
						<td><input class='standard_input refresh_vendor' type='number' id='taxes'></td>
					</tr>
					<tr>
						<th>Freight</th>
						<td><input class='standard_input refresh_vendor' type='number' id='freight'></td>
					</tr>
					<tr>
						<th><input class='standard_input refresh_vendor' id='additional_expense' style='width: 5em;'></th>
						<td><input class='standard_input refresh_vendor' type='number' id='additional_expense_cost'></td>
					</tr>
				</table>

				<table class='vendor_po_tables' style='padding-bottom: 2em;'>
					<tr>
						<th style='width: 126px;'>*To (semicolon-delimited):</th>
						<td>
							<textarea class="emails refresh_vendor" id='email_to' style='margin-left: -8px;'></textarea>
						</td>
					</tr>
					<tr>
						<th style='width: 126px;'>CC (semicolon-delimited):</th>
						<td>
							<textarea class="emails refresh_vendor" id='email_cc' style='margin-left: -8px;'>orders@piersonwireless.com</textarea>
						</td>
					</tr>
					<tr>
						<th style='width: 126px;'>BCC (semicolon-delimited):</th>
						<td>
							<textarea class="emails refresh_vendor" id='email_bcc' style='margin-left: -8px;'></textarea>
						</td>
					</tr>
					<tr>
						<th style='width: 126px;'>Subject:</th>
						<td>
							<textarea class="email_text_areas refresh_vendor" id='email_subject' style='margin-left: -8px;'></textarea>
						</td>
					</tr>
					<tr>
						<th style='width: 126px;'>Email Body:</th>
						<td>
							<textarea class='refresh_vendor email_text_areas' style='height: 209px; margin-left: -8px;' name='email_body' id='email_body'></textarea>
						</td>
					</tr>
				</table>

				<br>

				<b style='float:left; padding-right: 1.2em; padding-left: 1.2em'>Notes:</b>
				<textarea id='vendor_po_notes' style='height: 209px; margin-left: 4.8em; margin-bottom: 10em;' class='refresh_vendor email_text_areas'></textarea>
			</div>

			<div id='print_preview_window' style='white-space: nowrap'>
				<iframe src="" id='vendor_po_iframe' width='800px' height='1000px' style='display:none'></iframe>
			</div>

		</div>

		<div style='padding-bottom:10em;'><!--blank space for padding at bottom of page !--></div>

	</div>

	<div id='open_orders' class='tabcontent' style='display: none'>

		<table>
			<tr>
				<td><input id='order_part_power_search' placeholder='Search for specific parts'>
				<td>
				<td><button onclick='refresh_orders_report(true)' form=''>&#128269;</button></td>
			</tr>
			<tr>
				<td><input id='order_po_power_search' placeholder='Search for specific PO' onKeyUp='refresh_orders_report(true)'>
				<td>
			</tr>
		</table>

		<?php

		//set up groups to be added to 
		$orders_groups = ["Open", "Complete", "Closed"];

		//loop through $order_groups and set up table needed to manage it
		for ($i = 0; $i < sizeof($orders_groups); $i++) {

			//set function to call on change/keyup
			if ($i == 0 || $i == 1)
				$fn = "get_open_orders()";
			else
				$fn = "get_closed_orders()";

		?>

			<h2>
				<button onclick='show_orders("<?= strtolower($orders_groups[$i]); ?>", this)' class='expand_order_tables'>
					<?php if ($orders_groups[$i] == "Open") {
						echo "-";
					} else {
						echo "+";
					} ?>
				</button>
				<?= $orders_groups[$i]; ?> Orders
			</h2>

			<table id='<?= strtolower($orders_groups[$i]); ?>_orders_table' class='standardTables order_report_tables' style='margin-bottom: 4em;<?php if ($orders_groups[$i] != "Open") {
																																						echo "display:none";
																																					} ?>'>
				<colgroup>
					<col>
					<col style='width:3em;'>
					<col>
					<col style='width:6em;'>
					<col>
					<col>
					<col style='width:7em;'>
					<col style='width:13em;'>
					<col>
					<col>
					<col>
					<col>
					<col>
					<col>
				</colgroup>
				<thead>
					<tr class='orders_table_headers sticky_order_header'>
						<th><!--placeholder for (+-) icon--></th>
						<th>PO #</th>
						<th>Vendor</th>
						<th>Date Issued<?php if ($orders_groups[$i] == "Open") {
											echo '<span id="filter_by_arrow">&#129095;</span>';
										} ?></th>
						<th>PO Total</th>
						<th>Project Name / Job Name</th>
						<th>Quote #</th>
						<th>Ship To</th>
						<th>Need by Date:</th>
						<th>Ack'd</th>
						<th>Priority</th>
						<th>VP</th>
						<th><!--placeholder for revision column--></th>
						<th style='cursor: default'>Internal Notes</th>
						<th>Est. Ship Date(s)</th>
						<th style='width: 1em;'>Unasgd Items</th>
					</tr>
					<tr class='searchBars sticky_thead'>
						<td><!--placeholder for (+/-) icon--></td>
						<td><input type='text' id='po_number<?= $i; ?>' onchange='<?= $fn; ?>' onkeyup='<?= $fn; ?>' class='inputs' style='width:95%;'></td>
						<td><input type='text' id='vendor<?= $i; ?>' onchange='<?= $fn; ?>' onkeyup='<?= $fn; ?>' class='inputs'></td>
						<td><input type='text' id='date_issued<?= $i; ?>' onchange='<?= $fn; ?>' onkeyup='<?= $fn; ?>' class='inputs'></td>
						<td></td><!--placeholder for po total-->
						<td><input type='text' id='project_name<?= $i; ?>' onchange='<?= $fn; ?>' onkeyup='<?= $fn; ?>' class='inputs'></td>
						<td><input type='text' id='quote<?= $i; ?>' onchange='<?= $fn; ?>' onkeyup='<?= $fn; ?>' class='inputs'></td>
						<td><input type='text' id='ship_to<?= $i; ?>' onchange='<?= $fn; ?>' onkeyup='<?= $fn; ?>' class='inputs'></td>
						<td></td>
						<td></td>
						<td></td>
						<td></td>
						<td></td>
						<td></td>
						<td></td><!--placeholder for acknowledged, create revision-->
					</tr>
				</thead>
				<tbody>
					<!--to be entered by get_open_orders() !-->
				</tbody>

			</table>

		<?php

		}

		?>

		<div style='padding-bottom:10em;'><!--blank space for padding at bottom of page !--></div>

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
				<td colspan="4" style='border: 0; height: 5px'></td>
			</tr>
			<!--WHERE NEW ROWS WILL BE INSERTED !-->
		</table>
	</div>

	<!--- Split Par Dialog Box !--->
	<!--
	<div class = 'ui-widget' id = 'partSplit-dialog' style = 'display:none' title = 'Part Split'>
		<table id = 'partSplit-table' class = 'partDialog'>
			<tr>
				<th></th>
				<th style = 'width: 15em'>Part Number</th>
				<th style = 'width: 7em'>Quantity</th>
			</tr>
			<tr>
				<td><b>Total</b></td>
				<td id = 'target_pn'></td>
				<td id = 'target_quantity'></td>
			</tr>
			<tr><td colspan="3" style = 'border: 0; height: 5px'></td></tr>
			<!--WHERE NEW ROWS WILL BE INSERTED !
		</table>
		<button onclick='split_handler.process_split()' class = 'large_button' style = 'margin-top: 1em;'>Split Part</button>
	</div>
	-->

	<div id='issue_dialog' style='display:none' title='Kick Back to Allocations'>

		<table>
			<tr>
				<td class='issue_td'>Reason: </td>
				<td>
					<select id='issue_reason' class='custom-select'>
						<option></option>
						<option>No Vendor Stock</option>
						<option>Due Date Cannot Be Met</option>
						<option>Discontinued Material</option>
						<option>MOQ Not Met</option>
						<option>Using Existing Stock Instead</option>
						<option>Need To Use Substitute</option>
						<option>Use Incoming Stock</option>
					</select>
				</td>
			</tr>
			<tr>
				<td colspan="2">
					<textarea id='issue_notes' placeholder="Enter any applicable notes" style='width: 400px; height: 80px; resize:vertical'></textarea>
				</td>
			</tr>
		</table>

		<br>

		<button onclick='send_kick_back_part()'>Kick Back to Allocations</button>

		<span id='hold_overview_id' style='display:none'></span>
	</div>

	<div id='remove_dialog' style='display:none' title='Remove Part From Request'>

		<table>
			<tr>
				<td class='issue_td'>Part Number: </td>
				<td><input readonly id='remove_part'></td>
			</tr>
			<tr>
				<td class='issue_td'>Reason: </td>
				<td>
					<select id='remove_reason' class='custom-select'>
						<option></option>
						<option>Reason 1</option>
						<option>Reason 2</option>
						<option>Reason 3</option>
					</select>
				</td>
			</tr>
			<tr>
				<td colspan="2">
					<textarea id='remove_notes' placeholder="Enter any applicable notes" style='width: 400px; height: 80px; resize:vertical'></textarea>
				</td>
			</tr>
		</table>

		<br>

		<button onclick='remove_part()'>Remove Part from Request</button>

		<span id='remove_detail_id' style='display:none'></span>
	</div>

	<div id='new_vendor_dialog' style='display:none' title='Create New Vendor'>

		<table class='vendor_po_tables'>
			<tr>
				<th class='vendor_td'>Vendor: </th>
				<td><input readonly id='nv_vendor' style='width: 14.5em' class='nv_required'></td>
			</tr>
			<tr>
				<th>Street: </th>
				<td><input type='text' id='nv_street' style='width: 14.5em' class='nv_required'></td>
			</tr>
			<tr>
				<th>City: </th>
				<td><input type='text' id='nv_city' style='width: 14.5em' class='nv_required'></td>
			</tr>
			<tr>
				<th>State, Zip, Country: </th>
				<td>
					<select type='text' id='nv_state' style='width: 5em' class='custom-select nv_required'>
						<option></option>
						<?php
						//read from query into arrays
						for ($i = 0; $i < sizeof($states); $i++) {
						?>
							<option><?= $states[$i]; ?></option>
						<?php
						}
						?>
					</select> &nbsp;
					<input type='text' id='nv_zip' style='width: 5em' class='nv_required'>
					<input type='text' id='nv_country' style='width: 3em;' class='nv_required'>
				</td>
			</tr>
			<tr>
				<th class='vendor_td'>Vendor POC: </th>
				<td><input id='nv_poc' style='width: 14.5em' class='nv_required'></td>
			</tr>
			<tr>
				<th class='vendor_td'>Phone: </th>
				<td><input id='nv_phone' style='width: 14.5em' class='nv_required'></td>
			</tr>
			<tr>
				<th class='vendor_td'>Email: </th>
				<td><input id='nv_email' style='width: 14.5em' class='nv_required'></td>
			</tr>
			<tr>
				<th class='vendor_td'>Vendor Docs (W9 Form): </th>
				<td><input type='file' id='nv_w9'></td>
			</tr>
		</table>

		<button onclick='create_new_vendor()'>Create Vendor</button>

	</div>

	<div id='custom_location_dialog' style='display:none' title='Add Custom Location'>

		<table class='vendor_po_tables'>
			<tr>
				<th class='vendor_td'>Location Name: </th>
				<td><input id='cl_name' style='width: 14.5em' class='cl_required'></td>
			</tr>
			<tr>
				<th>Street: </th>
				<td><input type='text' id='cl_street' style='width: 14.5em' class='cl_required'></td>
			</tr>
			<tr>
				<th>City: </th>
				<td><input type='text' id='cl_city' style='width: 14.5em' class='cl_required'></td>
			</tr>
			<tr>
				<th>State, Zip: </th>
				<td>
					<select type='text' id='cl_state' style='width: 5em' class='custom-select cl_required'>
						<option></option>
						<?php
						//read from query into arrays
						for ($i = 0; $i < sizeof($states); $i++) {
						?>
							<option><?= $states[$i]; ?></option>
						<?php
						}
						?>
					</select> &nbsp;
					<input type='text' id='cl_zip' style='width: 5em' class='cl_required'>
				</td>
			</tr>
			<tr>
				<th class='vendor_td'>Attention: </th>
				<td><input id='cl_attention' style='width: 14.5em' class='cl_required'></td>
			</tr>
			<tr>
				<th class='vendor_td'>Phone: </th>
				<td><input id='cl_phone' style='width: 14.5em' class='cl_required'></td>
			</tr>
			<tr>
				<th class='vendor_td'>Email: </th>
				<td><input id='cl_email' style='width: 14.5em' class='cl_required'></td>
			</tr>
		</table>

		<button onclick='create_new_location()'>Create Location</button>

	</div>

	<div id='new_vendor_poc_dialog' style='display:none' title='Create New Vendor POC'>

		<table class='vendor_po_tables'>
			<tr>
				<th class='vendor_td'>Vendor POC: </th>
				<td><input readonly id='nvp_poc' style='width: 14.5em'></td>
			</tr>
			<tr>
				<th>Phone: </th>
				<td><input type='text' id='nvp_phone' style='width: 14.5em' class='nvp_required'></td>
			</tr>
			<tr>
				<th>Email: </th>
				<td><input type='text' id='nvp_email' style='width: 14.5em' class='nvp_required'></td>
			</tr>
		</table>

		<button onclick='create_new_vendor_poc()'>Create Vendor POC</button>

	</div>

	<div id='new_shipment_dialog' style='display:none' title='Create New Shipment'>

		<table class='vendor_po_tables' style='padding: 0 6em'>
			<tr>
				<th class='vendor_td'>Tracking: </th>
				<td><input id='ns_tracking' class='ns_field' style='width: 14.5em'></td>
			</tr>
			<tr>
				<th class='vendor_td'>Carrier: </th>
				<td><input id='ns_carrier' class='ns_field' style='width: 14.5em'></td>
			</tr>
			<tr>
				<th class='vendor_td'>Shipping Cost: </th>
				<td><input type='number' id='ns_cost' class='ns_field' style='width: 14.5em'></td>
			</tr>
			<tr>
				<th class='vendor_td'>Est. Ship Date: </th>
				<td><input id='ns_ship_date' class='ns_field' style='width: 14.66em' type='date'></td>
			</tr>
			<tr>
				<th class='vendor_td'>Estimated Arrival: </th>
				<td><input id='ns_arrival' class='ns_field' style='width: 14.66em' type='date'></td>
			</tr>
			<tr>
				<th class='vendor_td'>Shipped: </th>
				<td style='height: 1.5em;'><input type='checkbox' class='ns_field' id='ns_shipped'></td>
			</tr>
			<tr>
				<th class='vendor_td'>Notes: </th>
				<td><textarea id='ns_notes' class='ns_field' style='width: 14.5em; height: 5em;'></textarea></td>
			</tr>
		</table>

		<table id='shipment_parts_table' class='vendor_po_tables standardTables'>
			<tr>
				<th style="width: 4.5em; padding-left:1em;">
					<input type='checkbox' id='check_all_checkbox' onclick='update_add_to_shipment(this)'><br>
					Add To Shipment
				</th>
				<th style="width: 10em;">Part Number</th>
				<th style="width: 4.5em;">Qty on new Shipment</th>
				<th style="width: 4.5em;">Total Qty on Order</th>
				<th style="width: 4.5em;">Total Qty on Existing Shipments</th>
			</tr>
			<!-- body of table to be entered by get_new_shipment_parts() -->
		</table>

		<button onclick='create_new_shipment()'>Create Shipment</button>

	</div>

	<div id='notes_dialog' style='display:none' title='Add/Review Notes'>

		<!--holds current PO number -->
		<input id='notes_po_number' style='display:none'>
		<input id='notes_shipment_id' style='display:none'>

		<button onclick='z.update_notes(0)'>Add Note</button><br><br>
		<textarea id='new_notes' style='resize: vertical' class='enter_clar'></textarea>

		<table class='standardTables' id='notes_table' style='margin-top: 1em;'>
			<tr>
				<th><!--placeholder for edit --></th>
				<th>PO Number</th>
				<th>Shipment</th>
				<th>Note</th>
				<th>User</th>
				<th>Timestamp</th>
				<th><!--placeholder for X --></th>
			</tr>
			<!--new rows added from init_notes_dialog-->
		</table>

	</div>

	<!-- external js libraries -->
	<!--jquery capabilities-->
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
	<script src="https://code.jquery.com/jquery-1.12.4.js"></script>
	<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>

	<!--google APIs-->
	<script src="https://apis.google.com/js/platform.js?onload=init" async defer></script>

	<!--load pdf renderer-->
	<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.3.0-beta.1/pdfmake.min.js" integrity="sha512-G332POpNexhCYGoyPfct/0/K1BZc4vHO5XSzRENRML0evYCaRpAUNxFinoIJCZFJlGGnOWJbtMLgEGRtiCJ0Yw==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.3.0-beta.1/standard-fonts/Times.js" integrity="sha512-KSVIiw2otDZjf/c/0OW7x/4Fy4lM7bRBdR7fQnUVUOMUZJfX/bZNrlkCHonnlwq3UlVc43+Z6Md2HeUGa2eMqw==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

	<!--local scripts-->
	<script src="javascript/accounting.js"></script>
	<script src="javascript/js_helper.js?<?= $version ?>1"></script>
	<script src="javascript/utils.js"></script>
	<script src="javascript/fst_js_functions.js"></script>

	<script>
		//Namespace
		var z = {}

		//load in active user (first + last)
		const user = "<?= $_SESSION['firstName'] . " " . $_SESSION['lastName']; ?>";
		const user_info = <?= json_encode($fstUser); ?>;

		//used to load email tags
		const availableTags = <?= json_encode(array_column($directory, 'email')); ?>;

		//init current update id (used to update existing)
		var update_id = null;

		//pass project array
		var allocations_mo = <?= json_encode($allocations_mo); ?>,
			pq_detail = <?= json_encode($pq_detail); ?>,
			pq_overview = <?= json_encode($pq_overview); ?>,
			vendors = <?= json_encode($vendors); ?>,
			vendor_poc = <?= json_encode($vendor_poc); ?>,
			previous_orders = <?= json_encode($previous_orders); ?>,
			pq_orders = <?= json_encode($pq_orders); ?>,
			pq_shipments = <?= json_encode($pq_shipments); ?>,
			staging_locations = <?= json_encode($staging_locations); ?>,
			manual_locations = <?= json_encode($manual_locations); ?>,
			directory = <?= json_encode($directory); ?>,
			po_notes = <?= json_encode($po_notes); ?>,
			user_entered_parts = <?= json_encode($parts_with_no_description); ?>;

		//global array to keep track of order #'s that need to be updated
		var target_order_numbers = [];

		//global array to keep track of orders in PO report that need to be updated on save
		//this is seperate from target_order_number because these are on a different sheet
		var target_po_numbers = [];

		//global array to keep track of changes in shipment info
		var target_shipment_ids = [];

		//render simple auto-complete on 'attention' input (show PW employees)
		//set options as 'directory'
		var options = {
			source: directory.map(object => object.full_name),
			minLength: 2
		};

		//choose selector - attention as ID
		var selector = '#attention';

		//on keydown, show autocomplete after 2 characters
		$(document).on('keydown.autocomplete', selector, function() {
			$(this).autocomplete(options);
		});

		//handles updating shipment changes
		//param 1 = the po number that has changed
		//param 2 = the table row interacted with
		function refresh_open_order_globals(po_number, tr) {

			//get index of order in pq_orders
			var o_index = pq_orders.findIndex(object => {
				return object.po_number == po_number;
			});

			//update globals that apply
			var acknowledge_index = 9;
			var priority_index = 10;
			var vp_index = 11;

			pq_orders[o_index]['acknowledged'] = tr.childNodes[acknowledge_index].childNodes[0].checked;
			pq_orders[o_index]['priority'] = tr.childNodes[priority_index].childNodes[0].checked;
			pq_orders[o_index]['vp_processed'] = tr.childNodes[vp_index].childNodes[0].checked;

			//push order # to queue if it is not already on it
			if (target_po_numbers.indexOf(po_number) == -1)
				target_po_numbers.push(po_number);
		}

		//handles updating shipment changes
		//param 1 = the shipping ID that has changed
		//param 2 = the table row interacted with
		function refresh_shipment_globals(ship_id, tr) {

			//get index of order in pq_orders
			var s_index = pq_shipments.findIndex(object => {
				return object.shipment_id == ship_id;
			});

			//update globals that apply (use open_order_shipping_content, exclude shipment id)
			for (var i = 0; i < open_order_shipping_content.length; i++) {

				//treat checkboxes differently
				if (open_order_shipping_content[i] == "shipped")
					pq_shipments[s_index][open_order_shipping_content[i]] = tr.childNodes[i + 1].childNodes[0].checked;
				else if (open_order_shipping_content[i] != "shipment_id")
					pq_shipments[s_index][open_order_shipping_content[i]] = tr.childNodes[i + 1].childNodes[0].value;

			}

			//push order # to queue if it is not already on it
			if (target_shipment_ids.indexOf(ship_id) == -1)
				target_shipment_ids.push(ship_id);

		}

		//handles updating globals for allocations_mo on change & saving ids that need to be updated.
		function refresh_vendor_globals() {

			//loop through vendor_keys and save to current global
			for (var i = 0; i < vendor_keys.length; i++) {

				//treat checkboxes differnetly
				if (u.eid(vendor_keys[i]).type == "checkbox")
					pq_orders[current_order_index][vendor_keys[i]] = u.eid(vendor_keys[i]).checked;
				else
					pq_orders[current_order_index][vendor_keys[i]] = u.eid(vendor_keys[i]).value;
			}

			//push order # to queue if it is not already on it
			if (target_order_numbers.indexOf(pq_orders[current_order_index].po_number) == -1)
				target_order_numbers.push(pq_orders[current_order_index].po_number);

		}

		//global used to keep track of indexes of changes
		var target_ids = [];

		//handles refreshing target arrays and updating globals
		function refresh_target() {

			//if current index is -1, ignore
			if (current_request_index === -1)
				return;

			//push index if not already in array
			if (target_ids.indexOf(allocations_mo[current_request_index].id) === -1) {
				//use pq_id since parts can be linked to this (each allocation should have one)
				target_ids.push(allocations_mo[current_request_index].id);
			}

			//refresh globals
			refresh_globals();

		}

		//global used to hold ID of the field where a user tried to enter an unvalid vendor (used to write back out vendor name after creation)
		var no_vendor_id;

		//handles refreshing globals according to current index
		function refresh_globals() {

			//only update fields that are editable
			allocations_mo[current_request_index]['notes'] = u.eid('notes').value;
			allocations_mo[current_request_index]['shipping_place'] = u.eid('shipping_place').value;

			//if shipping_place = manual, save new entry
			if (allocations_mo[current_request_index]['shipping_place'] == "Manual Entry") {

				//check for existing entry
				var m_index = manual_locations.findIndex(object => {
					return object.allocation_id == allocations_mo[current_request_index].id;
				});

				//if we have entry, update, otherwise, add new
				if (m_index != -1) {

					manual_locations[m_index].poc_name = u.eid("poc_name").value;
					manual_locations[m_index].poc_number = u.eid("poc_name").value;
					manual_locations[m_index].name = u.eid("ship_to").value;
					manual_locations[m_index].street = u.eid("street").value;
					manual_locations[m_index].city = u.eid("city").value;
					manual_locations[m_index].state = u.eid("state").value;
					manual_locations[m_index].zip = u.eid("zip").value;

				} else {

					manual_locations.push({
						allocation_id: allocations_mo[current_request_index].id,
						poc_name: u.eid("poc_name").value,
						poc_number: u.eid("poc_name").value,
						name: u.eid("ship_to").value,
						street: u.eid("street").value,
						city: u.eid("city").value,
						state: u.eid("state").value,
						zip: u.eid("zip").value

					})

				}
			}

			//update part info
			var parts = u.class("part_id");
			var vendor_list = u.class("vendor");
			var vendor_qty = u.class("vendor_qty");
			var vendor_cost = u.class("vendor_cost");
			var external_po_notes = u.class("external_po_notes");

			//loop through and update instructions
			for (var i = 0; i < parts.length; i++) {

				//find index of parts.id
				var detail_index = pq_detail.findIndex(object => {
					return object.id == parts[i].id;
				});

				//find vendor index so we can get case correct when storing (do not save if not in our list)
				var vendor_index = vendors.findIndex(object => {
					return object.vendor.toLowerCase() == vendor_list[i].value.toLowerCase();
				});

				//if we do not find a match, show new vendor dialog, reset the vendor they tried to type
				if (vendor_index == -1 && vendor_list[i].value != "") {
					$("#new_vendor_dialog").dialog({
						width: "auto",
						height: "auto",
						dialogClass: "fixedDialog",
					});
					u.eid("nv_vendor").value = vendor_list[i].value;
					no_vendor_id = vendor_list[i].id;
					vendor_list[i].value = "";
				} else if (vendor_index != -1) {
					vendor_list[i].value = vendors[vendor_index].vendor;
				}

				pq_detail[detail_index].vendor = vendor_list[i].value;
				pq_detail[detail_index].vendor_qty = vendor_qty[i].value;
				pq_detail[detail_index].vendor_cost = vendor_cost[i].value;
				pq_detail[detail_index].external_po_notes = external_po_notes[i].value;
			}
		}

		//determines if we are updating or not (if false, then mouse spins, if true then does not - used for updating queue in background)
		var updating = false;

		/**@author Alex Borchers
		 * Handles saving any updated values to database
		 * @param update {true/false} determines if this update is auto or manual (do we need to see spinning mouse or not)
		 * 
		 * result:
		 * no output
		 * updated info in database, synced info with other uses working
		 */
		function update_queue(update = true) {

			//set updating
			updating = update;

			//initalize form data (will carry all form data over to server side)
			var fd = new FormData();

			//add arrays to send to server (saves any changed values)
			fd.append('target_ids', JSON.stringify(target_ids));
			fd.append('manual_locations', JSON.stringify(manual_locations));
			fd.append('allocations_mo', JSON.stringify(allocations_mo));

			//add arrays for vendor orders
			fd.append('vendor_keys', JSON.stringify(vendor_keys));
			fd.append('target_order_numbers', JSON.stringify(target_order_numbers));
			fd.append('pq_orders', JSON.stringify(pq_orders));

			//add array for purchase order report
			fd.append('target_po_numbers', JSON.stringify(target_po_numbers));
			fd.append('check_po_assignments', JSON.stringify(check_po_assignments));

			//add array for shipping info
			fd.append('target_shipment_ids', JSON.stringify(target_shipment_ids));
			fd.append('pq_shipments', JSON.stringify(pq_shipments));
			fd.append('shipment_keys', JSON.stringify(open_order_shipping_content));

			//add pq_detail (saves assigned vendors, quantities, cost, etc.)
			fd.append('pq_detail', JSON.stringify(pq_detail));

			//add tell
			fd.append('tell', 'update_queue');
			fd.append('user_info', JSON.stringify(user_info));

			$.ajax({
				url: 'terminal_orders_helper.php',
				type: 'POST',
				processData: false,
				contentType: false,
				data: fd,
				success: function(response) {

					//console.log(response);
					hold_response = $.parseJSON(response);

					//update objects
					allocations_mo = hold_response[0];
					pq_detail = hold_response[1];
					pq_overview = hold_response[2];
					pq_orders = hold_response[3];
					user_entered_parts = hold_response[4];

					console.log(pq_detail);

					//refresh info
					refresh_queue();

					//clear update arrays
					target_ids = [];
					target_order_numbers = [];
					target_po_numbers = [];
					target_shipment_ids = [];
					check_po_assignments = [];

					//if updating is false already, let user know that the info has been saved
					if (!updating)
						alert("All changes have been saved.");

					//unset updating
					updating = false;

					//reset interval
					clearInterval(myInterval);
					myInterval = setInterval(update_queue, timer);

				}
			});
		}

		//handles refreshing queue
		function refresh_queue() {

			//remove previous queue items
			document.querySelectorAll('.open-pending').forEach(function(a) {
				a.remove()
			})

			//get list of objects that are either open or in progress
			var open_requests = allocations_mo.filter(function(order) {
				return order.status == "Open" || order.status == "In Progress" || order.status == "Revision" || (order.status == "Complete" && check_closed_today(order.closed));
			});

			//loop through array and add any projects not in close to open parts request
			for (var i = 0; i < open_requests.length; i++) {
				new_order_request_list_item(open_requests[i]);
			}

			//update class of previous selected
			if (current_request_index != -1) {

				//check if element exists
				if (u.eid("pq-label-" + allocations_mo[current_request_index].id)) {
					u.eid("pq-label-" + allocations_mo[current_request_index].id).classList.add("ui-corner-top");
					u.eid("pq-label-" + allocations_mo[current_request_index].id).classList.add("ui-checkboxradio-checked");
					u.eid("pq-label-" + allocations_mo[current_request_index].id).classList.add("ui-state-active");
				}
			}
		}

		//used to adjust shipping address based on select list
		//param 1 = (Staging/Final/Manual)
		function adjust_ship_to(type) {

			//default all to readonly
			u.eid("poc_number").readOnly = true;
			u.eid("poc_name").readOnly = true;
			u.eid("ship_to").readOnly = true;
			u.eid("street").readOnly = true;
			u.eid("city").readOnly = true;
			u.eid("state").disabled = true;
			u.eid("zip").readOnly = true;

			//depending on option selected, update shipping information
			if (type == "Staging Location") {

				//look up staging in fst_pq_overview
				var pq_index = pq_overview.findIndex(object => {
					return object.id == allocations_mo[current_request_index].pq_id;
				});

				//if nothing found, alert user, otherwise match the staging location to an address
				if (pq_index == -1) {
					alert("No staging address found for this location. Please enter the address manually.")
					return;
				}

				//check if staging is listed as ship to final destination
				if (pq_overview[pq_index].staging_loc == "Ship To Final Destination") {
					alert("Staging Location is listed as 'Ship To Final Destination'. Please enter address manually if the order is going to a PW staging location.");
					u.eid("shipping_place").value = "Final Destination";
					adjust_ship_to("Final Destination")
					return;
				}

				//find index in shipping address object
				var s_index = staging_locations.findIndex(object => {
					return object.name == pq_overview[pq_index].staging_loc;
				});

				//check to make sure we find a match
				if (s_index == -1) {
					alert("The listed staging location [" + pq_overview[pq_index].staging_loc + "] does not have an address in out database. Please enter the address manually.");
				}

				//update shipping info from what we have saved in general_shippingadd table
				u.eid("poc_name").value = staging_locations[s_index].recipient;
				u.eid("poc_number").value = staging_locations[s_index].phone;
				u.eid("ship_to").value = staging_locations[s_index].name;
				u.eid("street").value = staging_locations[s_index].address;
				u.eid("city").value = staging_locations[s_index].city;
				u.eid("state").value = staging_locations[s_index].state;
				u.eid("zip").value = staging_locations[s_index].zip;

			} else if (type == "Final Destination") {

				//update shipping info from what we have saved in fst_allocations_mo table
				u.eid("poc_name").value = allocations_mo[current_request_index].poc_name;
				u.eid("poc_number").value = allocations_mo[current_request_index].poc_number;
				u.eid("ship_to").value = allocations_mo[current_request_index].ship_to;
				u.eid("street").value = allocations_mo[current_request_index].street;
				u.eid("city").value = allocations_mo[current_request_index].city;
				u.eid("state").value = allocations_mo[current_request_index].state;
				u.eid("zip").value = allocations_mo[current_request_index].zip;

			} else if (type == "Manual Entry") {

				//look for any info entered before
				var m_index = manual_locations.findIndex(object => {
					return object.allocation_id == allocations_mo[current_request_index].id;
				});

				//if we found a match, set, otherwise clear info
				if (m_index == -1) {

					//unset all shipping info
					/*u.eid("poc_name").value = "";
					u.eid("poc_number").value = "";
					u.eid("ship_to").value = "";
					u.eid("street").value = "";
					u.eid("city").value = "";
					u.eid("state").value = "";
					u.eid("zip").value = "";
					*/

				} else {

					//set to previously entered info
					u.eid("poc_name").value = manual_locations[m_index].poc_name;
					u.eid("poc_number").value = manual_locations[m_index].poc_number;
					u.eid("ship_to").value = manual_locations[m_index].name;
					u.eid("street").value = manual_locations[m_index].street;
					u.eid("city").value = manual_locations[m_index].city;
					u.eid("state").value = manual_locations[m_index].state;
					u.eid("zip").value = manual_locations[m_index].zip;
				}

				//open to edit
				u.eid("poc_number").readOnly = false;
				u.eid("poc_name").readOnly = false;
				u.eid("ship_to").readOnly = false;
				u.eid("street").readOnly = false;
				u.eid("city").readOnly = false;
				u.eid("state").disabled = false;
				u.eid("zip").readOnly = false;

			}

			// adjust pq_detail shipping_place based on new entry
			for (var i = 0; i < pq_detail.length; i++) {
				if (pq_detail[i].project_id == allocations_mo[current_request_index].pq_id)
					pq_detail[i].shipping_place = type;
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

		/**
		 * Handles opening up OMA or CHA order request
		 * 
		 * @param shop {string} OMA or CHA
		 */

		//handles opening custom order dialog box
		function open_stock_order(shop) {

			//convert shop to ID
			var id;

			if (shop == "OMA")
				id = "233-999899";
			else if (shop == "CHA")
				id = "236-999899";
			else
				return; //not able to handle any other requests

			//pass information through form element
			var fd = new FormData();

			//add shop
			fd.append('id', id);

			//add tell
			fd.append('tell', 'open_stock_order');

			$.ajax({
				url: 'terminal_orders_helper.php',
				type: 'POST',
				processData: false,
				contentType: false,
				data: fd,
				success: function(response) {

					//check for error
					if (response != "") {
						alert(response);
						return;
					}

					//find index of OMA or CHA and update index
					var index = allocations_mo.findIndex(object => {
						return object.project_id == id && object.project_name == shop + ' Stock Order';
					});

					//set job to open
					allocations_mo[index].status = "Open";

					//refresh queue
					refresh_queue();
				}
			});
		}

		//global that holds current index being viewed in allocations_mo
		var current_request_index = -1; //holds current index of "parts orders" tab
		var current_order_index = -1; //holds current index of "vendor POs" tab

		//function used to help navigate what action needs to be taken for showing info
		function show_info_handler() {

			//get tell from ID (first string to - should be pq, vendor, or po)
			var tell = this.id.substr(0, this.id.indexOf("-"));

			//check tell
			if (tell == 'pq')
				show_order_info(this);
			else if (tell == 'vendor')
				show_vendor_parts(this);
			else
				show_po_info(this);

		}

		//handles resetting list based on index passed (takes away active styling from list element on main screen)
		function unclick_current_list_element(id, type) {

			//remove classes that make list look like it's showing
			u.eid(type + "-label-" + id).classList.remove("ui-checkboxradio-checked");
			u.eid(type + "-label-" + id).classList.remove("ui-state-active");

			//loop through pq_info and hide
			document.querySelectorAll('.' + type + '_info').forEach(function(a) {
				a.style.display = "none";
			})

			//update current index
			if (type == "pq")
				current_request_index = -1;
			else if (type == "vendor")
				current_order_index = -1;

		}

		//shows relevant info for parts request
		function show_order_info(targ = null) {

			//grab id from z.show_info
			var id = targ.id;

			//update index to actual id
			//first look for "closed in the id"
			if (id.indexOf("closed") > 0)
				id = id.substring(10);
			else
				id = id.substring(3);

			//convert id to index
			var index = allocations_mo.findIndex(object => {
				return object.id == id;
			});

			//check to see if index matches current index (if so, unhighlight blue and hide main area)
			if (current_request_index == index) {

				unclick_current_list_element(id, "pq");
				return;
			} else {
				//add classes that make list look like it's showing
				u.eid("pq-label-" + id).classList.add("ui-checkboxradio-checked");
				u.eid("pq-label-" + id).classList.add("ui-state-active");

				//loop through pq_info and show
				document.querySelectorAll('.pq_info').forEach(function(a) {
					a.style.display = "block";
				})

			}

			//update current index
			current_request_index = index;

			//get order based on the index
			var order = allocations_mo[index];

			//update values according to index
			//Project Details
			u.eid("project_id").value = order.project_id;
			u.eid("project_name").value = order.project_name;
			u.eid("requested_by").value = order.requested_by;
			u.eid("date_required").value = order.date_required;
			u.eid("cc_email").value = order.cc_email;
			u.eid("notes").value = order.notes;

			//get project specific info from pq_overview
			//get index
			var overview_index = pq_overview.findIndex(object => {
				return object.id == order.pq_id;
			});

			if (overview_index > -1) {
				u.eid("cust_pn").value = pq_overview[overview_index].cust_pn;
				u.eid("oem_num").value = pq_overview[overview_index].oem_num;
				u.eid("bus_unit_num").value = pq_overview[overview_index].bus_unit_num;
				u.eid("loc_id").value = pq_overview[overview_index].loc_id;
			} else {
				u.eid("cust_pn").value = "";
				u.eid("oem_num").value = "";
				u.eid("bus_unit_num").value = "";
				u.eid("loc_id").value = "";
			}

			//Final Destinal Address
			//update ship to based on shipping option
			//if shipping_place is blank, check if this is going to final destination or not
			if (order.shipping_place == "") {
				if (order.staging_loc == "Ship To Final Destination")
					order.shipping_place = order.staging_loc;
				else
					order.shipping_place = "Ship to Final Destination";
			}

			u.eid("shipping_place").value = order.shipping_place;
			adjust_ship_to(order.shipping_place);

			//set sched field based on yes or no
			if (order.sched_opt == "Y") {
				u.eid("sched_opt").value = order.sched_opt;
				u.eid("sched_date").value = order.sched_time.substr(0, 10);
				u.eid("sched_time").value = order.sched_time.substr(11);

				document.querySelectorAll('.sched_row').forEach(function(a) {
					a.style.visibility = "visible";
				})
			} else {
				u.eid("sched_opt").value = order.sched_opt;
				u.eid("sched_date").value = null;
				u.eid("sched_time").value = null;
				document.querySelectorAll('.sched_row').forEach(function(a) {
					a.style.visibility = "collapse";
				})
			}

			//show bill of materials for request
			show_bom(order.pq_id);

			//update "add materials" link id
			u.eid("material_entry_id").value = order.pq_id;

			//render autocomplete drop-down (we are rendering first part prevent an error which prohibits the drop-down from appearing on the first element we interact with)
			var parts = u.class("part_id");
			if (parts.length > 0) {
				current_part = parts[0].value;
				//render_autocomplete();
			}

			//if status is open, acknowledge request (move to in progress, reply to email)
			if (order.status == "Open")
				acknowledge_request();
		}

		//global that sets up object key list (pq_orders column heads match the id's on the vendor PO's tab)
		var vendor_keys = ["ready", "po_number", "revision", "vendor_name", "vendor_id", "vendor_street", "vendor_street2", "vendor_city", "vendor_state", "vendor_zip", "date_ordered", "ordered_by",
			"need_by_date", "payment_terms", "ship_via", "bill_to", "bill_to_street", "bill_to_city", "bill_to_state", "bill_to_zip", "po_ship_to", "po_ship_to_temporary", "po_ship_to_street", "po_ship_to_street2",
			"po_ship_to_city", "po_ship_to_state", "po_ship_to_zip", "attention", "attention_email", "attention_phone", "vendor_poc", "taxes", "freight", "additional_expense", "additional_expense_cost", "email_to", "email_cc",
			"email_bcc", "email_subject", "email_body", "vendor_po_notes"
		];

		/**@author Alex Borchers
		 * Handles showing information related to specific purchase order
		 * 
		 * @param targ {HTMLEntity} <label> the element a user clicked on
		 * @returns void
		 */
		function show_po_info(targ = null) {

			//get purchase order from ID
			var po_number = targ.id.substr(8);

			//get index of po_number in pq_orders
			var index = pq_orders.findIndex(object => {
				return object.po_number == po_number;
			});

			//set global current index
			current_order_index = index;

			//update notes (temporary)
			if (pq_orders[index].vendor_po_notes == "" || pq_orders[index].vendor_po_notes == null) {
				pq_orders[index].vendor_po_notes = "****Buyer Purchaser Order Number must be included in all shipment labels/documents from the shipper, including factory drop Shipments";
				pq_orders[index].vendor_po_notes += "Routing:\n";
				pq_orders[index].vendor_po_notes += "****Ship via ground service, prepaid and added, unless otherwise noted.\n";
				pq_orders[index].vendor_po_notes += "****For shipments greater than 124#s, ship via LTL freight, 3rd party, Old Dominion: Acct# 12917556 or R&L Acct#PIEOMA.\n";
				pq_orders[index].vendor_po_notes += "****Partial Shipments by buyer authorization only.";
			}

			//scroll to open orders
			//$('html,body').animate({scrollTop: $("#pq_header").offset().top},'slow');

			//update any standard values that may be blank in pq_orders
			update_standard_pq_order_info(index);

			//render POC combobox
			render_vendor_poc_combobox(pq_orders[index].vendor_name);

			//update values according to index
			//user vendor_keys (id's match columns in database)
			for (var i = 0; i < vendor_keys.length; i++) {

				//treat checkboxes differnetly
				if (u.eid(vendor_keys[i]).type == "checkbox") {

					if (pq_orders[index][vendor_keys[i]] == 1)
						u.eid(vendor_keys[i]).checked = true;
					else
						u.eid(vendor_keys[i]).checked = false;
				} else
					u.eid(vendor_keys[i]).value = pq_orders[index][vendor_keys[i]];

			}

			// check if shipping to temporary location
			if (u.eid("po_ship_to").value == "Temporary Location")
				u.eid("po_ship_to_temporary_row").style.visibility = "visible";
			else
				u.eid("po_ship_to_temporary_row").style.visibility = "collapse";

			//update vendor info
			find_vendor_poc_information(pq_orders[index]['vendor_poc']);

			//close new vendor POC dialog box (if open)
			if ($("#new_vendor_poc_dialog").hasClass("ui-dialog-content"))
				$("#new_vendor_poc_dialog").dialog('close');

			//show vendor po div
			u.eid("vendor_po_div").style.display = "block";

			//hide po preview
			u.eid("vendor_po_iframe").style.display = "none";
		}

		//shows relevant info for parts request
		function show_vendor_parts(targ = null) {

			//get list of clicked vendors
			var vendors = get_clicked_vendors();

			//update list of parts related to vendor
			get_vendor_parts(vendors);
		}

		/**@author Alex Borchers
		 * Determines "clicked" vendors
		 * 
		 * @returns clicked_vendors {array}
		 */
		function get_clicked_vendors() {

			//init list of vendors to be returned
			var clicked_vendors = [];

			//loop through all items with class vendor-list-item
			document.querySelectorAll('.vendor-list-item').forEach(function(a) {

				//only consier <inputs>
				if (a.type == 'checkbox' && a.checked)
					clicked_vendors.push($('label[for="' + a.id + '"]')[0].childNodes[2].textContent)
			})

			return clicked_vendors;
		}

		//handles getting list of vendor parts
		function get_vendor_parts(vendors) {

			//remove old items
			document.querySelectorAll('.pq-parts-row').forEach(function(a) {
				a.remove();
			})

			//sort parts related to vendor
			//start by getting list of IDs related to open items
			var open_requests = allocations_mo.filter(function(order) {
				return order.status == "Open" || order.status == "In Progress" || order.status == "Revision";
			});

			//get list of parts related to open requests
			var open_items = pq_detail.filter(function(part) {
				return open_requests.some(function(request) {
					return part.project_id == request.pq_id;
				});
			});

			//now restrict down to only "Pending" items
			var vendor_items = open_items.filter(function(part) {
				return part.status == "Pending" && vendors.includes(part.vendor);
			});

			//sort items by vendor by default
			vendor_items.sort((a, b) => a['vendor'].localeCompare(b['vendor']));

			//loop through matching items and add to po vendor table
			for (var i = 0; i < vendor_items.length; i++) {
				po_add_item(vendor_items[i]);
			}

			//show table (and merge button)
			u.eid("vendor-parts-table").style.display = "block";
			u.eid("create_order_button").style.display = "block";
		}

		//updates any standard info in pq_order object
		//param 1 = index in array
		function update_standard_pq_order_info(index) {

			//get index in vendors object
			var vendor_index = vendors.findIndex(object => {
				return object.vendor == pq_orders[index].vendor_name;
			});

			//update id, street, city, state, zip if blank
			if (pq_orders[index].vendor_id == "" || pq_orders[index].vendor_id == null) {
				pq_orders[index].vendor_id = vendors[vendor_index].id;
				target_order_numbers.push(pq_orders[index].po_number)
			}

			if (pq_orders[index].vendor_street == "" || pq_orders[index].vendor_street == null)
				pq_orders[index].vendor_street = vendors[vendor_index].street;

			if (pq_orders[index].vendor_city == "" || pq_orders[index].vendor_city == null)
				pq_orders[index].vendor_city = vendors[vendor_index].city;

			if (pq_orders[index].vendor_state == "" || pq_orders[index].vendor_state == null)
				pq_orders[index].vendor_state = vendors[vendor_index].state;

			if (pq_orders[index].vendor_zip == "" || pq_orders[index].vendor_zip == null)
				pq_orders[index].vendor_zip = vendors[vendor_index].zip;

			//update ordered by to user's name
			if (pq_orders[index].ordered_by == "" || pq_orders[index].ordered_by == null)
				pq_orders[index].ordered_by = user;

			//default payment terms to Net 30
			if (pq_orders[index].payment_terms == "" || pq_orders[index].payment_terms == null)
				pq_orders[index].payment_terms = "Net 30 Days";

			//default "bill to" to PW
			if (pq_orders[index].bill_to == "" || pq_orders[index].bill_to == null)
				pq_orders[index].bill_to = "Pierson Wireless";

			if (pq_orders[index].bill_to_street == "" || pq_orders[index].bill_to_street == null)
				pq_orders[index].bill_to_street = "11414 S 145th St";

			if (pq_orders[index].bill_to_city == "" || pq_orders[index].bill_to_city == null)
				pq_orders[index].bill_to_city = "Omaha";

			if (pq_orders[index].bill_to_state == "" || pq_orders[index].bill_to_state == null)
				pq_orders[index].bill_to_state = "NE";

			if (pq_orders[index].bill_to_zip == "" || pq_orders[index].bill_to_zip == null)
				pq_orders[index].bill_to_zip = "68138";

			// check if po_shipping info
			if (pq_orders[index].po_ship_to == null)
				pq_orders[index].po_ship_to = "";

			if (pq_orders[index].po_ship_to_street == null)
				pq_orders[index].po_ship_to_street = "";

			if (pq_orders[index].po_ship_to_city == null)
				pq_orders[index].po_ship_to_city = "";

			if (pq_orders[index].po_ship_to_state == null)
				pq_orders[index].po_ship_to_state = "";

			if (pq_orders[index].po_ship_to_zip == null)
				pq_orders[index].po_ship_to_zip = "";

			// default "ship to" to final destination
			// get first pq_id from fst_orders query, assume the shipping addresses are the same, default to first read in
			var vert_bar_index = pq_orders[index].pq_id.indexOf("|");
			var assumed_pq_id = pq_orders[index].pq_id;

			if (vert_bar_index > -1)
				assumed_pq_id = pq_orders[index].pq_id.substr(0, vert_bar_index);

			// get index in allocations_mo
			var AM_index = allocations_mo.findIndex(object => {
				return object.pq_id == assumed_pq_id;
			});

			// if we find an index
			if (AM_index != -1) {

				if (pq_orders[index].po_ship_to == "" || pq_orders[index].po_ship_to == null)
					pq_orders[index].po_ship_to = allocations_mo[AM_index].ship_to;

				if (pq_orders[index].po_ship_to_street == "" || pq_orders[index].po_ship_to_street == null)
					pq_orders[index].po_ship_to_street = allocations_mo[AM_index].street;

				if (pq_orders[index].po_ship_to_city == "" || pq_orders[index].po_ship_to_city == null)
					pq_orders[index].po_ship_to_city = allocations_mo[AM_index].city;

				if (pq_orders[index].po_ship_to_state == "" || pq_orders[index].po_ship_to_state == null)
					pq_orders[index].po_ship_to_state = allocations_mo[AM_index].state;

				if (pq_orders[index].po_ship_to_zip == "" || pq_orders[index].po_ship_to_zip == null)
					pq_orders[index].po_ship_to_zip = allocations_mo[AM_index].zip;

				// default attention to Pierson main shop employee (if applicable)
				if (pq_orders[index].attention == "" || pq_orders[index].attention == null)
					pq_orders[index].attention = get_attention(allocations_mo[AM_index].ship_to);

			}

			//default attention to Pierson main shop employee (if applicable)
			if (pq_orders[index].attention == "" || pq_orders[index].attention == null)
				pq_orders[index].attention = "";

			//update 'attention' phone and email if applicable
			if ((pq_orders[index].attention_email == null || pq_orders[index].attention_email == "") &&
				(pq_orders[index].attention_phone == null || pq_orders[index].attention_phone == ""))
				update_attention_info(pq_orders[index]);

			//default date ordered to today
			if (pq_orders[index].date_ordered == "" || pq_orders[index].date_ordered == null)
				pq_orders[index].date_ordered = new Date().toISOString().split('T')[0];

			//default CC to orders (unless we have cc)
			if (pq_orders[index].email_cc == "" || pq_orders[index].email_cc == null)
				pq_orders[index].email_cc = "orders@piersonwireless.com; ";

			//default email subject
			if (pq_orders[index].email_subject == "" || pq_orders[index].email_subject == null) {
				// check if we have multiple projects
				if (pq_orders[index].vp_id.includes("|"))
					pq_orders[index].email_subject = "Purchase Order Attached Job MPO PO #" + pq_orders[index].po_number;
				else
					pq_orders[index].email_subject = "Purchase Order Attached Job " + pq_orders[index].vp_id + " PO #" + pq_orders[index].po_number;
			}

			//default email body
			if (pq_orders[index].email_body == "" || pq_orders[index].email_body == null) {
				pq_orders[index].email_body = "Hello,\n\n";
				pq_orders[index].email_body += "Please process the attached order and acknowledge receipt.\n";
				pq_orders[index].email_body += "***Reference Project # and/or Purchase Order # for all shipment documents.\n\n";
				pq_orders[index].email_body += "Thank you,";
			}

			//loop through vendor keys, set to "" if null
			for (var i = 0; i < vendor_keys.length; i++) {
				if (pq_orders[index][vendor_keys[i]] == null) pq_orders[index][vendor_keys[i]] = "";
			}
		}

		//get Pierson contact based on ship to location
		//param 1 = shipping location (should match location in general_shippingadd)
		function get_attention(ship_to) {

			//get index in staging_locations
			var index = staging_locations.findIndex(object => {
				return object.name == ship_to;
			});

			//if we have a result, return recipient at this location
			if (index != -1)
				return staging_locations[index].recipient;

			return "";

		}

		//handles getting 'attention' phone & email if available
		function update_attention_info(order) {

			//if attention is blank, return with no updates
			if (order.attention == "")
				return;

			//use 'attention' id field to gather phone and email info
			var attention = order.attention;

			//get email from fst_users array
			var index = directory.findIndex(object => {
				return object.full_name.toLowerCase() == attention.toLowerCase();
			});

			//if we find match, load in email
			if (index != -1)
				order.attention_email = directory[index].email;

			//get phone # from POC info (if we have a match)
			if (u.eid("poc_name").value == attention)
				order.attention_phone = u.eid("poc_number").value;

		}

		//renders customer Project Owner autoselect.
		function render_vendor_poc_combobox(vendor) {

			//remove all options from select list
			document.querySelectorAll('.vendor_poc_option').forEach(function(a) {
				a.remove();
			})

			//grab select list
			var select = u.eid("vendor-poc-combobox");

			//get vendor ID from vendor
			var v_index = vendors.findIndex(object => {
				return object.vendor == vendor;
			});

			//search through vendor poc object & grab any matching the current vendor
			for (var i = 0; i < vendor_poc.length; i++) {
				if (vendor_poc[i].vendor_id == vendors[v_index].id) {
					var opt = document.createElement('option');
					opt.value = vendor_poc[i].name;
					opt.innerHTML = vendor_poc[i].name;
					opt.classList.add("vendor_poc_option");
					select.appendChild(opt);
				}
			}

			//set options to customer_pl combobox
			$(function() {
				$.widget("custom.combobox", {
					_create: function() {
						this.wrapper = $("<span>")
							.addClass("custom-combobox")
							.insertAfter(this.element);

						this.element.hide();
						this._createAutocomplete();
						this._createShowAllButton();
					},

					_createAutocomplete: function() {
						var selected = this.element.children(":selected"),
							value = selected.val() ? selected.text() : "";

						this.input = $("<input>")
							.appendTo(this.wrapper)
							.val(value)
							.attr("title", "")
							.addClass("custom-combobox-input standard_input refresh_vendor ui-widget ui-widget-content ui-state-default ui-corner-left")
							.attr("id", "vendor_poc")
							.autocomplete({
								delay: 0,
								minLength: 0,
								source: this._source.bind(this)
							})
							.tooltip({
								classes: {
									"ui-tooltip": "ui-state-highlight"
								}
							});

						this._on(this.input, {
							autocompleteselect: function(event, ui) {
								ui.item.option.selected = true;
								this._trigger("select", event, {
									item: ui.item.option
								});
							},

							autocompletechange: "_removeIfInvalid"
						});
					},

					_createShowAllButton: function() {
						var input = this.input,
							wasOpen = false;

						$("<a>")
							.attr("tabIndex", -1)
							.attr("title", "Show All Items")
							.tooltip()
							.appendTo(this.wrapper)
							.button({
								icons: {
									primary: "ui-icon-triangle-1-s"
								},
								text: false
							})
							.removeClass("ui-corner-all")
							.addClass("custom-combobox-toggle ui-corner-right")
							.on("mousedown", function() {
								wasOpen = input.autocomplete("widget").is(":visible");
							})
							.on("click", function() {
								input.trigger("focus");

								// Close if already visible
								if (wasOpen) {
									return;
								}

								// Pass empty string as value to search for, displaying all results
								input.autocomplete("search", "");
							});
					},

					_source: function(request, response) {
						var matcher = new RegExp($.ui.autocomplete.escapeRegex(request.term), "i");
						response(this.element.children("option").map(function() {
							var text = $(this).text();
							if (this.value && (!request.term || matcher.test(text)))
								return {
									label: text,
									value: text,
									option: this
								};
						}));
					},

					_removeIfInvalid: function(event, ui) {

						find_vendor_poc_information(this.input.val());

					},
				});

				$("#vendor-poc-combobox").combobox();
			});
		}

		//handles searching for existing POC and filling in infromation (phone & email)
		//param 1 = potential poc
		function find_vendor_poc_information(poc) {

			//boolean to determine if we found match or not
			var found = false;

			//loop through vendor_poc_option class (holds all available options)
			document.querySelectorAll('.vendor_poc_option').forEach(function(a) {
				if (poc.toLowerCase() == a.innerHTML.toLowerCase()) {

					//get phone & email from vendor_poc list
					var index = vendor_poc.findIndex(object => {
						return object.name == poc;
					});

					u.eid("vendor_phone").value = vendor_poc[index].phone;
					u.eid("vendor_email").value = vendor_poc[index].email;
					refresh_vendor_globals();
					found = true;

					//add to "email_to" line if not already added
					var email_to = u.eid("email_to").value.trim();
					var new_email = vendor_poc[index].email;

					//if current email_to is empty, set new_email as the current
					if (email_to == "") {
						email_to = new_email + "; ";
					}
					//search for email in current string
					else if (email_to.toLowerCase().indexOf(new_email) == -1) {

						//check last character
						var last_char = email_to.substr(email_to.length - 1);

						//if last char is ';', just add new email, otherwise, add semi-colon
						if (last_char == ";")
							email_to = email_to + " " + new_email + "; ";
						else
							email_to = email_to + "; " + new_email + "; ";
					}

					//update email_to
					u.eid("email_to").value = email_to;

					//add POC to email body
					var email_body = u.eid("email_body").value.trim();


					return;
				}
			})

			//stop here if we found a match
			if (found)
				return;

			//if we do not find a match, open new vendor poc dialog
			$("#new_vendor_poc_dialog").dialog({
				width: "auto",
				height: "auto",
				dialogClass: "fixedDialog",
			});

			//set new vendor POC dialog
			u.eid("nvp_poc").value = poc;

			//reset POC
			u.eid("vendor_poc").value = "";

			//update globals
			refresh_vendor_globals();
		}

		//handles showing BOM items for a selected parts request
		function show_bom(pq_id) {

			//reset data
			data = [];

			//clear previous BOM
			document.querySelectorAll('.pq-parts-row').forEach(function(a) {
				a.remove();
			})

			//init array to hold pq_detail
			var current_detail;

			//filter pq_detail and grab parts related to request
			var target_detail = pq_detail.filter(function(part) {
				return part.project_id == pq_id && part.status == "Pending";
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
				if (current_detail.add)
					add_item(current_detail);

				//check the rest of the array for matching parts (we want to add right after)
				for (var i = 0; i < reverse_detail.length; i++) {
					if (current_detail.part_id == reverse_detail[i].part_id) {
						//add and set .add to false
						if (reverse_detail[i].add) {
							add_item(reverse_detail[i]);
							reverse_detail[i].add = false;
						}
					}
				}
			}
		}

		//init globals that will assist in add_item (and make it much easier to edit/style)
		const item_value = ['remove', 'issue', 'split', 'part_id', 'q_allocated', 'cost', 'uom', 'manufacturer', 'po_number', 'vendor', 'vendor_qty', 'vendor_cost', 'vendor_total', 'qty_main', 'qty_on_order', 'qty_on_project', 'external_po_notes'],
			item_type = ['NA', 'NA', 'NA', 'text', 'number', 'text', 'text', 'text', 'text', 'text', 'number', 'text', 'text', 'text', 'text', 'text', 'text'],
			item_readOnly = [true, true, true, true, true, true, true, true, true, false, false, false, true, true, true, true, false];

		//handles adding a row based on index of pq_detail
		//param 1 (object that holds a given part number. Has properties decision, mmd, mo_id, part_id, project_id, quantity, quoteNumber, subs, and uom)
		function add_item(item) {

			//init vars needed
			var table = u.eid('pq-parts-table').getElementsByTagName('tbody')[0],
				input, search;

			//if description is null, try to update it with user entered info
			if (item.partDescription == null)
				item = update_part_with_user_info(item);

			//update qty on Order
			item.qty_on_order = get_on_order(item.part_id, 'excess');
			item.qty_on_project = get_on_order(item.part_id, 'project');

			//set values that aren't found on item
			item.remove = "<span class = 'remove_part' onclick = 'open_remove_dialog(this)'>✖</span>"
			item.split = "<button class = 'split' style = 'width: 6em;height:24px;' onclick = 'split_handler.button_click(this)' >Split Line</button>";
			//item.issue = "<button class = 'issue' style = 'width: 6em;'>Kick Back</button>";
			item.issue = "<input class = 'issue' type ='checkbox'>";
			item.vendor_total = accounting.formatMoney(item.vendor_qty * item.vendor_cost);

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

					if (item_value[i] == "part_id") {
						input.value = item[item_value[i]];
						input.readOnly = item_readOnly[i];
						input.id = item.id;
					}
					//used to show $
					else if (item_value[i] == "cost") {
						input.value = accounting.formatMoney(item[item_value[i]]);
						//input.value = item[item_value[i]];
						input.readOnly = item_readOnly[i];
					}
					//add ID to vendor
					else if (item_value[i] == "vendor") {

						//overwrite "input" set as div
						/*
						var input = document.createElement("div");
						input.classList.add("ui-widget");
						var select = document.createElement("select");
						select.id = "vendor" + item.id;
						input.appendChild(select);
						*/


						/*<div class="ui-widget">
							<select id="vendor-poc-combobox">
								<option></option>
							</select>
						</div>*/

						input.value = item[item_value[i]];
						input.readOnly = item_readOnly[i];
						input.id = "vendor" + item.id;

					} else {

						input.value = item[item_value[i]];
						input.readOnly = item_readOnly[i];

					}

					//set tab_index to -1 if readonly is true
					if (item_readOnly[i] == true)
						input.tabIndex = -1;

					//add class list with id name
					input.classList.add(item_value[i]);

					//append to cell
					cell.appendChild(input);

					//if vendor, add button
					if (item_value[i] == "vendor") {
						var button = document.createElement("button");
						button.innerHTML = "&#9660;";
						button.title = "Show All Vendors"
						button.classList.add("show_all_vendors");
						cell.appendChild(button);
					}
				}
			}

			//render options for given part
			render_autocomplete(item);
		}

		//init globals that will assist in add_item (and make it much easier to edit/style)
		const po_item_value = ['add_part', 'vp_id', 'project_name', 'shipping_loc', 'part_id', 'po_number', 'vendor', 'vendor_qty', 'vendor_cost', 'vendor_total', 'external_po_notes'],
			po_item_type = ['NA', 'text', 'text', 'text', 'text', 'text', 'text', 'text', 'text', 'text', 'text', 'text'],
			po_item_readOnly = [false, true, true, true, true, true, true, true, true, true, true, true];

		/**
		 * Handles adding a new row for user to select and combine into PO on Purchase Request tab
		 * 
		 * @param item {object} matches row from fst_pq_detail 
		 * 
		 * @returns void
		 */
		function po_add_item(item) {

			//init vars needed
			var table = u.eid('vendor-parts-table').getElementsByTagName('tbody')[0],
				input, search;

			//set values that aren't found on item
			item.vendor_total = accounting.formatMoney(item.vendor_qty * item.vendor_cost);

			//set checkbox to readonly IF po_number is already assigned
			if (item.po_number == "" || item.po_number == null)
				item.add_part = "<input type = 'checkbox' class = 'new_po_item' id = 'po_item_" + item.id + "' onclick = 'update_po_pq_detail(this)'>";
			else
				item.add_part = "<input type = 'checkbox' class = 'new_po_item' id = 'po_item_" + item.id + "' disabled>";

			//insert new row and add classname to it
			var row = table.insertRow(table.rows.length - 1);
			row.classList.add("pq-parts-row");

			//loop through item_value array to help guide creation of table
			for (var i = 0; i < po_item_value.length; i++) {

				//create new cell
				var cell = row.insertCell(i);

				//check input_type (if na, just write HTML)
				if (po_item_type[i] == "NA") {
					cell.innerHTML = item[po_item_value[i]];
				}
				//if we are rendering purchase order #, create a custom drop-down for it
				else if (po_item_value[i] == "po_number") {

					// call function to render custom <select>
					var select = create_custom_select(item);
					select.value = item[po_item_value[i]];


					// add class to cell and append new <select>
					cell.classList.add(po_item_value[i]);
					cell.appendChild(select);

				}
				//else set value and type based on item
				else {

					//set value & readOnly based on po_item_type and value readOnly arrays
					var input = document.createElement("input");
					input.readOnly = po_item_readOnly[i];

					// update value differently if 'ship_to'
					if (po_item_value[i] == "shipping_loc")
						input.value = get_item_ship_to(item);
					else
						input.value = item[po_item_value[i]];

					//set tab_index to -1 if readonly is true
					if (po_item_readOnly[i] == true)
						input.tabIndex = -1;

					//add class list with id name
					input.classList.add(po_item_value[i]);

					//append to cell
					cell.appendChild(input);
				}
			}
		}

		/**@author Alex Borchers
		 * Handles getting custom ship to (can be final destination, staging, OR manual)
		 * 
		 * @param item {object} matches entry from fst_pq_detail (see custom query above)
		 * @returns ship_to {object}
		 */
		function get_item_ship_to(item) {

			// look at shipping_place to determine what do return
			if (item.shipping_place == null)
				return item.shipping_loc;
			else if (item.shipping_place == "Final Destination") {
				return item.shipping_loc;
			} else if (item.shipping_place == "Staging Location") {
				return item.staging_loc;
			} else if (item.shipping_place == "Manual Entry") {

				// get index of manual entry in manual_locations
				var m_index = manual_locations.findIndex(object => {
					return object.allocation_id == item.allocation_id;
				});

				// return name of location
				return manual_locations[m_index].name;
			}

			// if we get no matches, return empty string
			return "";
		}

		/**@author Alex Borchers
		 * Handles rendering custom <select> drop-down with option to add new PO
		 * 
		 * @param part {object} matches row from fst_pq_detail table
		 * 
		 * @returns select {HTMLEntity} created <select> to be appended to table cell
		 */
		function create_custom_select(part) {

			// create select
			var select = document.createElement("select");
			select.classList.add("custom-select");

			// push blank option
			select.appendChild(document.createElement("option"));

			// add option to "Add New PO"
			var option = document.createElement("option");
			option.innerHTML = "Add New PO";
			option.value = "Add New PO";
			select.appendChild(option);

			// find all open purchase orders that matches the vendor assigned to this part
			var matching_po = pq_orders.filter(function(order) {
				return order.status == "Open" && order.vendor_name == part.vendor;
			});

			// loop through results
			for (var i = 0; i < matching_po.length; i++) {

				// create option, set value & html to PO #
				var option = document.createElement("option");
				option.innerHTML = matching_po[i].po_number;
				option.value = matching_po[i].po_number;
				select.appendChild(option);
			}

			// add event listener
			select.addEventListener('change', update_po_pq_detail);

			// return completed select list
			return select;
		}

		// global used to keep track of potential PO number changes (we need to check and update fst_pq_orders_assignments)
		var check_po_assignments = [];

		/**@author Alex Borchers
		 * Handles updating information related to fst_pq_detail on the purchase request tab
		 * 
		 * @param targ {HTML Entity} in the form of 'this', whatever the user clicked on or changed
		 * @returns void
		 */
		function update_po_pq_detail(targ) {

			// if targ is a string, use "this" as the target
			if (targ.type != "checkbox")
				targ = this;

			// work back to both checkbox & order #
			var td = targ.parentNode;
			var tr = td.parentNode;
			var checkbox = tr.childNodes[0].childNodes[0];
			var order = tr.childNodes[5].childNodes[0];
			var pq_detail_id = checkbox.id.substr(8);

			// if this is a checkbox, if user has just checked & po_number is blank, update order # to "add new po"
			if (targ.type == "checkbox") {

				if (checkbox.checked)
					order.value = "Add New PO";
				else
					order.value = "";

			}
			// if user moves order # to 
			else {

				// reset checkbox disabled
				checkbox.disabled = false;

				// make decision based on what user selected
				if (order.value == "")
					checkbox.checked = false;
				else if (order.value == "Add New PO")
					checkbox.checked = true;
				else {
					checkbox.disabled = true;
					checkbox.checked = false;
				}

			}

			// update globals
			var index = pq_detail.findIndex(object => {
				return object.id == pq_detail_id;
			});

			// push po number to global if not already added (make sure we add before & after)
			if (check_po_assignments.indexOf(pq_detail[index].po_number) === -1 && pq_detail[index].po_number != "")
				check_po_assignments.push(pq_detail[index].po_number);

			// if "add new po" set to blank
			if (order.value != "Add New PO")
				pq_detail[index].po_number = order.value;

			// get fst_allocations_mo id
			var allocations_index = allocations_mo.findIndex(object => {
				return object.pq_id == pq_detail[index].project_id && object.mo_id == 'PO';
			});

			// push target_id of request to global so info is updated in save
			if (target_ids.indexOf(allocations_mo[allocations_index].id) === -1)
				target_ids.push(allocations_mo[allocations_index].id);

			// push po number to global if not already added
			if (check_po_assignments.indexOf(order.value) === -1 && pq_detail[index].po_number != "")
				check_po_assignments.push(pq_detail[index].po_number);
		}

		//handles getting user entered info from seperate table (only called if info is not already present)
		// param 1 = item user is looking to update
		function update_part_with_user_info(item) {

			//search in user entered arra
			var ue_index = user_entered_parts.findIndex(object => {
				return object.partNumber.toLowerCase() == item.part_id.toLowerCase();
			});

			//if we find a match, update relevant info, otherwise set to blank
			if (ue_index != -1) {
				item.partDescription = user_entered_parts[ue_index].description;
				item.uom = user_entered_parts[ue_index].uom;
				item.manufacturer = user_entered_parts[ue_index].manufacturer;
				item.cost = user_entered_parts[ue_index].cost;
			} else {
				item.partDescription = "";
				item.uom = "";
				item.manufacturer = "";
				item.cost = 0;
			}

			//set qty from main shops to 0
			item.qty_main = 0;

			return item;
		}

		//handles getting excess amount on order for a given part
		//param 1 = part number
		//param 2 = type 'excess' or 'project'
		function get_on_order(part, type) {

			//filter pq_detail for parts that match and have been moved to shipped (and not received on - future addition)
			var on_order = pq_detail.filter(function(p) {
				return p.part_id == part && p.status == "Shipped";
			});

			//check q_allocated against vendor_qty for all parts, return difference between two
			var sum_on_order = 0;

			for (var i = 0; i < on_order.length; i++) {

				//sum depending on type
				if (type == "excess")
					sum_on_order += parseInt(on_order[i].vendor_qty - on_order[i].q_allocated) || 0; //if null, return 0 for q_allocated or vendor_qty
				else if (type == "project")
					sum_on_order += parseInt(on_order[i].q_allocated) || 0; //if null, return 0 for q_allocated
			}

			//cannot return negative
			if (sum_on_order < 0)
				return 0;
			else
				return sum_on_order;

		}

		//globals to help toggle vendors
		//var c_v_id = null;
		//var c_v_open = true;

		//handles toggling vendor autocomplete drop-down
		$(document).on('click', '.show_all_vendors', function() {

			//get vendor_field
			var td = this.parentNode;
			var tr = td.parentNode;
			var vendor_field = tr.childNodes[9].childNodes[0];

			//show options for vendor list
			$(vendor_field).autocomplete("search", "");

		});

		/**
		 * Handles getting list of vendors assigned to open projects and rending list of icons to select from
		 *  
		 * @returns void
		 * */
		function initialize_vendor_list() {

			//start by getting list of IDs related to open items
			var open_requests = allocations_mo.filter(function(order) {
				return order.status == "Open" || order.status == "In Progress" || order.status == "Revision";
			});

			//get list of parts related to open requests
			var open_items = pq_detail.filter(function(part) {
				return open_requests.some(function(request) {
					return part.project_id == request.pq_id;
				});
			});

			//now restrict down to only "Pending" items
			var pending_items = open_items.filter(function(part) {
				return part.status == "Pending" && (part.vendor != "" && part.vendor != null);
			});

			//get list of unique vendors from this new list of parts
			var unique_vendors = [...new Set(pending_items.map(item => item.vendor))];

			//sort vendor alphabetically
			unique_vendors.sort();

			//pass unique vendors to function to create radio button for user to select from
			refresh_vendor_list(unique_vendors);
		}

		//handles refreshing vendor queue with
		//param 1 = list of vendors to add to list
		function refresh_vendor_list(current_vendors) {

			//get list of clicked vendors
			var clicked_vendors = get_clicked_vendors();

			//remove old vendors
			document.querySelectorAll('.vendor-list-item').forEach(function(a) {
				a.remove()
			})

			//loop and add each vendor to list
			for (var i = 0; i < current_vendors.length; i++) {
				new_vendor_list_item(current_vendors[i], i);
			}

			//go back through and 'click' vendors that were clicked before
			document.querySelectorAll('.vendor-list-item').forEach(function(a) {

				// check label to see if it matches a vendor
				if (a.type == 'checkbox') {
					var vendor = $('label[for="' + a.id + '"]')[0].childNodes[2].textContent;

					//if vendor matches previously clicked, click again
					if (clicked_vendors.includes(vendor))
						a.click();

				}
			})

			//scroll to vendor list
			//$('html,body').animate({scrollTop: $("#vendor-list").offset().top - 65},'slow');
		}

		//handles adding a new list item to vendor po list
		function new_vendor_list_item(vendor, order_num) {

			//grab div element & set class list to use
			var div = u.eid("vendor-list");
			var use_classlist = "vendor-list-item";

			//add label 
			var label = document.createElement("Label");
			label.setAttribute("for", "vendor-" + order_num);
			label.id = 'vendor-label-' + order_num;

			//add class to label so we know to remove it if we resort
			label.classList.add(use_classlist);

			//add additional class name
			label.classList.add("list-item");

			//add to div
			label.innerHTML += vendor;

			//append to parent div
			div.appendChild(label);

			//add input
			var input = document.createElement("input");
			input.type = "checkbox";
			input.setAttribute("name", "vendor");
			input.id = "vendor-" + order_num;
			input.addEventListener('click', show_info_handler);
			input.classList.add(use_classlist);
			div.appendChild(input);

			//reinitialize toggle menu
			//$( "#vendor-list" ).checkboxradio();
			$(".shape-bar, .vendor-checkbox").controlgroup();
			$(".vendor-checkbox").controlgroup({
				direction: "vertical"
			});
		}

		/**@author Alex Borchers
		 * handles creating new purchase order from selected items
		 * 
		 * @returns void
		 */
		function create_purchase_orders() {

			//hide all order content
			//u.eid("vendor_po_div").style.display = "none";
			//u.eid("vendor_po_iframe").style.display = "none";

			//loop through all select 
			var po_items = [];
			var po_checkbox = u.class("new_po_item");
			var vendors = u.class("vendor");
			var ship_to = u.class("shipping_loc");
			var use_vendor = "",
				use_ship_to = "",
				warn_ship_to = false;

			for (var i = 0; i < po_checkbox.length; i++) {

				//if user has selected, add to list
				if (po_checkbox[i].checked) {

					//ids are in the form "po_item" + id (take everything after 8 as id that matches id in fst_pq_detail)
					po_items.push(po_checkbox[i].id.substr(8));

					//if use_vendor is blank, set here
					if (use_vendor == "")
						use_vendor = vendors[i].value;

					//if vendor does not match use_vendor, send error message and exit function
					if (vendors[i].value != use_vendor) {
						alert("[ERROR] All selected items must have the same vendor.");
						return;
					}

					//if use_ship_to is blank, set here
					if (use_ship_to == "")
						use_ship_to = ship_to[i].value;

					//if vendor does not match use_vendor, send error message and exit function
					if (ship_to[i].value != use_ship_to) {
						warn_ship_to = true;
					}
				}
			}

			//check that at least 1 item is selected
			if (po_items.length == 0) {
				alert("[ERROR] Must select at least 1 item.");
				return;
			}

			//init form data to send to server
			var fd = new FormData();

			//add allocations_mo ID and tell
			fd.append("use_vendor", use_vendor);
			fd.append("po_items", JSON.stringify(po_items));
			fd.append("tell", "create_purchase_order");

			//access database
			$.ajax({
				url: 'terminal_orders_helper.php',
				type: 'POST',
				processData: false,
				contentType: false,
				data: fd,
				success: function(response) {

					//check for error response
					if (response.substr(0, 5) == "Error") {
						alert(response);
						return;
					}

					//transfer reponse and turn set pq_orders
					hold_response = $.parseJSON(response);
					pq_orders = hold_response[0];
					pq_detail = hold_response[1];

					//alert user of success
					alert("A new purchase order has been created.");

				}
			}).done(function(data) {

				// refresh vendor & po list
				initialize_vendor_list();
				refresh_po_list();

			});
		}

		/**@author Alex Borchers
		 * Handles refreshing list of POs which are still open
		 * 
		 * @returns void
		 */
		function refresh_po_list() {

			// initialize previously clicked item
			var previous_item = "";

			// remove any previous items from list
			document.querySelectorAll('.po-list-item').forEach(function(a) {
				if (a.type == "radio" && a.checked)
					previous_item = a.id;
				a.remove();
			})

			// filter orders that are still open
			var open_orders = pq_orders.filter(function(order) {
				return order.status == "Open";
			});

			// update purchase order list global
			purchase_orders = open_orders.map(a => a.po_number);

			// loop through open orders, create new select items
			for (var i = 0; i < open_orders.length; i++) {
				new_po_list_item(open_orders[i]);
			}

			// click previous item (if it exists)
			if (u.eid(previous_item))
				u.eid(previous_item).click();
		}

		/**@author Alex Borchers
		 * Handles adding new list item for a purchase order
		 * 
		 * @param order {object} matches entry in fst_pq_orders
		 * 
		 * @returns void
		 */
		//handles adding a new list item to vendor po list
		function new_po_list_item(order) {

			//grab div element & set class list to use
			var div = u.eid("po-list");
			var use_classlist = "po-list-item";

			//add label 
			var label = document.createElement("Label");
			label.setAttribute("for", "po-list-" + order.po_number);
			label.id = 'po-list-label-' + order.po_number;

			//add class to label so we know to remove it if we resort
			label.classList.add(use_classlist);

			//add additional class name
			label.classList.add("list-item");

			//add to div
			label.innerHTML += order.po_number + " (" + order.vendor_name + ")";

			//append to parent div
			div.appendChild(label);

			//add input
			var input = document.createElement("input");
			input.type = "radio";
			input.setAttribute("name", "po-list");
			input.id = "po-list-" + order.po_number;
			input.addEventListener('click', show_info_handler);
			input.classList.add(use_classlist);
			div.appendChild(input);

			//reinitialize toggle menu
			//$( "#vendor-list" ).checkboxradio();
			$(".shape-bar, .po-radiobox").controlgroup();
			$(".po-radiobox").controlgroup({
				direction: "vertical"
			});
		}

		//handles acknowledging a request (move to in progress, reply to email)
		function acknowledge_request() {

			//init form data to send to server
			var fd = new FormData();

			//add allocations_mo ID and tell
			fd.append("id", allocations_mo[current_request_index].id);
			fd.append("tell", "acknowledge")

			//access database
			$.ajax({
				url: 'terminal_orders_helper.php',
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
					allocations_mo[current_request_index].status = "In Progress";
					u.eid("status" + allocations_mo[current_request_index].id).innerHTML = "In Progress";

				}
			})
		}

		//global used to store current issue part
		var issue_id = -1;

		//on click for issues, open part issue dialog
		$(document).on('click', '.issue', function() {

			$("#issue_dialog").dialog({
				width: "auto",
				height: "auto",
				dialogClass: "fixedDialog",
			});

			//update global id
			u.eid("hold_overview_id").innerHTML = allocations_mo[current_request_index].pq_id;

		});

		//handles rejecting parts
		function send_kick_back_part() {

			//first ask if they really want to report issue (may be a mis-click)
			var message = "Are you sure you would like to kick this back to allocations?";

			//send message to user (return if cancel)
			if (!confirm(message))
				return;

			//save ID of current order (used to determine if it has been removed after ajax call)
			var current_id = allocations_mo[current_request_index].id;

			// loop through all checked fields & gather info to send to server
			var issue_parts = [];

			document.querySelectorAll('.issue').forEach(function(a) {
				if (a.checked) {

					// work backwards to id
					var td = a.parentNode;
					var tr = td.parentNode;
					var part_td = tr.childNodes[3];

					issue_parts.push({
						id: part_td.childNodes[0].id,
						part: part_td.childNodes[0].value
					});
				}
			})

			//initalize form data (will carry all form data over to server side)
			var fd = new FormData();

			//append info needed to kick back part
			fd.append('pq_overview_id', u.eid("hold_overview_id").innerHTML);
			fd.append('issue_parts', JSON.stringify(issue_parts));
			fd.append('issue_reason', u.eid("issue_reason").value);
			fd.append('issue_notes', u.eid("issue_notes").value);
			fd.append('project_number', allocations_mo[current_request_index].project_id);
			fd.append('project_name', allocations_mo[current_request_index].project_name);
			fd.append('urgency', allocations_mo[current_request_index].urgency);

			//add tell
			fd.append('tell', 'kick_back');
			fd.append('user_info', JSON.stringify(user_info));

			$.ajax({
				url: 'terminal_orders_helper.php',
				type: 'POST',
				processData: false,
				contentType: false,
				data: fd,
				success: function(response) {

					//check for error
					var error_check = response.substr(0, 5);

					if (error_check == "Error") {
						alert(response);
						return;
					}

					//otherwise, update globals, refresh table, and let user know this has been successful
					var convert_resp = $.parseJSON(response);
					pq_detail = convert_resp[0];
					allocations_mo = convert_resp[1];

					//alert user
					alert("This part has been successfully kicked back to allocations.");

					//reset reason/textboxes in dialog box
					u.eid("issue_reason").value = "";
					u.eid("issue_notes").value = "";

					//close dialog, reinit submit button
					$("#issue_dialog").dialog('close');

					//search for ID in current allocations_mo array (if not found, unset current_index and hide data)
					var index = allocations_mo.findIndex(object => {
						return object.id == current_id;
					});

					if (index == -1)
						unclick_current_list_element(current_id, "pq");

					//refresh queue & part list
					refresh_queue();
					show_bom(u.eid("hold_overview_id").innerHTML);

				}
			});
		}

		//handles creating a new vendor
		function create_new_vendor() {

			//check to make sure required fields are filled out
			//check_submit takes a classname, checks for blanks, highlights all blanks yellow, returns true if passes all tests
			var error = check_submit(u.class("nv_required"));

			//reset color of vendor field
			u.eid("nv_vendor").style.backgroundColor = "#C8C8C8";

			if (error) {
				alert("Please fill in all required information (highlighted in yellow).");
				return;
			}

			//initalize form data (will carry all form data over to server side)
			var fd = new FormData();

			//grab info needed to pass to server
			//loop through class (nv_required) save value as ID (check input id's for values to be passed to server)
			var nv_required = u.class("nv_required");

			for (var i = 0; i < nv_required.length; i++) {
				fd.append(nv_required[i].id, nv_required[i].value);
			}

			//if w9 is attached, add it to form
			if (u.eid("nv_w9").files.length > 0) {
				var file = $('#nv_w9')[0].files[0];
				fd.append('nv_w9', file)
			}

			//add tell variable
			fd.append('tell', 'create_new_vendor');

			//access database
			$.ajax({
				url: 'terminal_orders_helper.php',
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

					//insert new entry into vendor_list and add to row
					vendors.push({
						id: response,
						vendor: u.eid("nv_vendor").value,
						poc: u.eid("nv_poc").value,
						phone: u.eid("nv_phone").value,
						street: u.eid("nv_street").value,
						city: u.eid("nv_city").value,
						state: u.eid("nv_state").value,
						zip: u.eid("nv_zip").value,
						country: u.eid("nv_country").value
					})

					//update the input field with the new vendor
					u.eid(no_vendor_id).value = u.eid("nv_vendor").value;

					//alert user of success & close dialog
					alert("The vendor has been created.");
					$("#new_vendor_dialog").dialog('close');

					//reset vendor fields
					document.querySelectorAll('.nv_required').forEach(function(a) {
						a.value = "";
					})

					//get list of items related to order
					var update_detail = pq_detail.filter(function(part) {
						return part.project_id == allocations_mo[current_request_index].pq_id && part.status == "PO";
					});

					console.log(update_detail);

					//loop render autocomplete
					for (var i = 0; i < update_detail.length; i++) {
						render_autocomplete(update_detail[i]);
					}

				}
			});
		}

		//handles creating a new vendor
		function create_new_location() {

			//check to make sure required fields are filled out
			//check_submit takes a classname, checks for blanks, highlights all blanks yellow, returns true if passes all tests
			var error = check_submit(u.class("cl_required"));

			if (error) {
				alert("Please fill in all required information (highlighted in yellow).");
				return;
			}

			//initalize form data (will carry all form data over to server side)
			var fd = new FormData();

			//grab info needed to pass to server
			//loop through class (nv_required) save value as ID (check input id's for values to be passed to server)
			var cl_required = u.class("cl_required");

			for (var i = 0; i < cl_required.length; i++) {
				fd.append(cl_required[i].id, cl_required[i].value);
			}

			//add tell variable
			fd.append('tell', 'create_new_location');

			//access database
			$.ajax({
				url: 'terminal_orders_helper.php',
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

					//insert new entry into staging_locations and add to row
					staging_locations.push({
						name: u.eid("cl_name").value,
						recipient: u.eid("cl_attention").value,
						customer: "PW-Custom",
						phone: u.eid("cl_phone").value,
						email: u.eid("cl_email").value,
						address: u.eid("cl_street").value,
						city: u.eid("cl_city").value,
						state: u.eid("cl_state").value,
						zip: u.eid("cl_zip").value
					})

					//create new <option> item in <optgroup id = 'custom_staging_locations'>
					var option = document.createElement("option");
					option.innerHTML = u.eid("cl_name").value;
					option.value = u.eid("cl_name").value;
					u.eid("custom_staging_locations").appendChild(option);

					//update the input field with the new location
					u.eid("po_ship_to").value = u.eid("cl_name").value;
					update_shipping_info(u.eid("cl_name").value, 'po');

					//alert user of success & close dialog
					alert("The location has been created.");
					$("#custom_location_dialog").dialog('close');

					//update vendor globals
					refresh_vendor_globals();

					//reset location fields
					document.querySelectorAll('.cl_required').forEach(function(a) {
						a.value = "";
					})
				}
			});
		}

		//handles creating a new vendor
		function create_new_vendor_poc() {

			//check to make sure required fields are filled out
			//check_submit takes a classname, checks for blanks, highlights all blanks yellow, returns true if passes all tests
			var error = check_submit(u.class("nvp_required"));

			if (error) {
				alert("Please fill in all required information (highlighted in yellow).");
				return;
			}

			//initalize form data (will carry all form data over to server side)
			var fd = new FormData();

			//grab info needed to pass to server
			//loop through class (nv_required) save value as ID (check input id's for values to be passed to server)
			var nvp_required = u.class("nvp_required");

			for (var i = 0; i < nvp_required.length; i++) {
				fd.append(nvp_required[i].id, nvp_required[i].value);
			}

			//add vendor POC and vendorID
			fd.append("nvp_poc", u.eid("nvp_poc").value);
			fd.append('vendor_id', u.eid("vendor_id").value);

			//add tell variable
			fd.append('tell', 'create_new_vendor_poc');

			//access database
			$.ajax({
				url: 'terminal_orders_helper.php',
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

					//insert new entry into vendor_list and add to row
					vendor_poc.push({
						vendor_id: u.eid("vendor_id").value,
						name: u.eid("nvp_poc").value,
						phone: u.eid("nvp_phone").value,
						email: u.eid("nvp_email").value
					})

					//render new poc-dropdown
					render_vendor_poc_combobox(u.eid("vendor_name").value);

					//update the input field with the new vendor
					u.eid("vendor_poc").value = u.eid("nvp_poc").value;
					u.eid("vendor_phone").value = u.eid("nvp_phone").value;
					u.eid("vendor_email").value = u.eid("nvp_email").value;

					//alert user of success & close dialog
					alert("The POC has been created.");
					$("#new_vendor_poc_dialog").dialog('close');

					//reset vendor fields
					document.querySelectorAll('.nvp_required').forEach(function(a) {
						a.value = "";
					})

				}
			});
		}

		//opens remove dialog
		//param 1 = this (element clicked)
		function open_remove_dialog(targ) {

			//work our way to the part number
			var td = targ.parentNode;
			var tr = td.parentNode;
			var part_td = tr.childNodes[3];
			var pq_detail_id = part_td.childNodes[0].id;
			var part = part_td.childNodes[0].value;

			//send part & id to holding places
			u.eid("remove_part").value = part;
			u.eid("remove_detail_id").innerHTML = pq_detail_id;

			//open dialog
			$("#remove_dialog").dialog({
				width: "auto",
				height: "auto",
				dialogClass: "fixedDialog",
			});
		}

		//handles removing part from request

		function remove_part() {

			//get part # and pq_id
			var pq_detail_id = u.eid("remove_detail_id").innerHTML;
			var part = u.eid("remove_part").value;

			//ask user if they are sure about this (cannot be undone)
			var message = "Are you sure you would like to remove " + part + " from the request? This will not be sent to allocations and cannot be undone without resubmitting the parts request.";

			//send message to user (return if cancel)
			if (!confirm(message))
				return;

			//save current state
			update_queue(true);

			//set current id
			var current_id = allocations_mo[current_request_index].pq_id;

			//get index in pq_detail for part #
			var pq_d_index = pq_detail.findIndex(object => {
				return object.id == pq_detail_id;
			});

			//update request to 0 if blank
			if (pq_detail[pq_d_index].q_allocated == null || pq_detail[pq_d_index].q_allocated == "")
				pq_detail[pq_d_index].q_allocated = 0;

			//initalize form data (will carry all form data over to server side)
			var fd = new FormData();

			//add part & request ID
			fd.append("pq_detail_id", pq_detail_id);
			fd.append("pq_overview_id", current_id);
			fd.append("q_requested", pq_detail[pq_d_index].q_allocated);
			fd.append('pq_id', allocations_mo[current_request_index].pq_id);
			fd.append('part', part);
			fd.append('remove_reason', u.eid("remove_reason").value);
			fd.append('remove_notes', u.eid("remove_notes").value);

			//add tell variable
			fd.append('tell', 'remove_part');

			//access database
			$.ajax({
				url: 'terminal_orders_helper.php',
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

					//update globals and refresh queue
					var result = $.parseJSON(response);
					pq_detail = result[0];
					allocations_mo = result[1];

					//search for ID in current allocations_mo array (if not found, unset current_index and hide data)
					var index = allocations_mo.findIndex(object => {
						return object.pq_id == current_id;
					});

					if (index == -1)
						unclick_current_list_element(current_id, "pq");
					else
						show_bom(allocations_mo[current_request_index].pq_id);

					//refresh queue & part list
					refresh_queue();
					//show_bom(u.eid("hold_overview_id").innerHTML);

					//alert user & close dialog
					alert("The part has been removed from the request.");
					$("#remove_dialog").dialog('close');

				}
			});
		}

		//handles functions related to splitting lines
		var split_handler = {

			//handles initial button click
			button_click: function(targ) {

				//work our way to the part number
				var td = targ.parentNode;
				var tr = td.parentNode;
				var part_td = tr.childNodes[3];
				var pq_detail_id = part_td.childNodes[0].id;

				//run necessary checks related to this part
				var error = this.split_checks(pq_detail_id);

				if (error)
					return;

				//first ask if they really want to report issue (may be a mis-click)
				var message = "Are you sure you would like to split this part (add a new entry)?";

				//send message to user (return if cancel)
				if (!confirm(message))
					return;

				//save current state
				update_queue(true);

				//initalize form data (will carry all form data over to server side)
				var fd = new FormData();

				//add part & request ID
				fd.append("pq_detail_id", pq_detail_id);
				fd.append('pq_id', allocations_mo[current_request_index].pq_id);

				//add tell variable
				fd.append('tell', 'split_line');

				//access database
				$.ajax({
					url: 'terminal_orders_helper.php',
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

						//update globals and refresh purchase order items
						pq_detail = $.parseJSON(response);
						show_bom(allocations_mo[current_request_index].pq_id);

						//alert user
						alert("The part split has been processed.");

					}
				});

				return;

			},

			//handles checking for potentail errors when trying to split a part
			//param 1 = part id (used to find part # in table)
			split_checks: function(id) {

				//check how many parts we have against the quantity requested (we can only split up to the quantity requested)

				//get part
				var part = u.eid(id).value;

				//loop through all parts and get a count & total quantity requested for each part (parts already split will have quantity requested = 0)
				var p_count = 0,
					p_requested = 0;

				var parts = u.class("part_id");
				var q_requested = u.class("q_allocated");

				for (var i = 0; i < parts.length; i++) {

					//if we find a match to our target part, add to both count and requested
					if (parts[i].value == part) {
						p_count++;
						p_requested += parseInt(q_requested[i].value);
					}

				}

				//if count = requested, do not allow user to split this part
				if (p_count == p_requested) {
					alert("Error: You cannot split a part into more ways than the amount requested.");
					return true;
				}

				//if we pass all checks, return false (error)
				return false;

			}
		}

		//handles adding a new list item to our queue
		//param 1 = order (allocation_mo entry)
		function new_order_request_list_item(order) {

			//grab div element & set class list to use
			var div = u.eid("pq-list");
			var use_classlist = "open-pending";

			//add label 
			//adding a new comment
			var label = document.createElement("Label");
			label.setAttribute("for", "pq-" + order.id);
			label.id = 'pq-label-' + order.id;

			//depending on urgency, add classname
			if (order.status == "Complete")
				label.classList.add("closed_style");
			else if (order.status == "Revision")
				label.classList.add("rejected_style");
			else if (order.urgency == "[Standard]")
				label.classList.add("standard_style");
			else if (order.urgency == "[Urgent]")
				label.classList.add("urgent_style");
			else
				label.classList.add("overnight_style");

			//add class to label so we know to remove it if we resort
			label.classList.add(use_classlist);

			//add additional class name
			label.classList.add("list-item");

			//add to div
			label.innerHTML += order.urgency + " P#: " + order.project_id + " | ";

			//create span element with open
			var span = document.createElement("SPAN");
			span.id = 'status' + order.id;

			//text node for status span
			var text = document.createTextNode(order.status);
			span.appendChild(text);

			//add to label
			label.appendChild(span);

			//add the rest of the label text
			console.log(order);
			label.innerHTML += " | Due: " + format_date(order.date_required) + " | <i class = 'date_required'>" + utc_to_local(order.date_created) + "</i>";

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
			input.id = "pq-" + order.id;
			input.addEventListener('click', show_info_handler);
			input.classList.add(use_classlist);
			div.appendChild(input);

			//reinitialize toggle menu
			$(".shape-bar, .pq").controlgroup();
			$(".pq").controlgroup({
				direction: "vertical"
			});

		}

		$(function() {
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

		//handles tabs up top that toggle between divs
		function change_tabs(pageName, elmnt, color) {

			//check to see if clicking the same tab (do nothing if so)
			if (elmnt.style.backgroundColor == color)
				return;

			//if going to open_orders, get list before switching tabs
			if (pageName == "open_orders")
				get_open_orders();

			//if going to create order, init vendor list
			if (pageName == "purchase_request") {
				initialize_vendor_list();
				refresh_po_list();

				// if we have unsaved items, save changes
				if (target_ids.length > 0)
					update_queue(true);
			}

			//if this is going back to "order requests" tab, remove all orders to increase speed
			if (pageName == "parts_orders") {
				// jquery functiont o loop through all classlist items
				document.querySelectorAll('.open_orders_row').forEach(function(a) {
					a.remove()
				})

				document.querySelectorAll('.complete_orders_row').forEach(function(a) {
					a.remove()
				})

				document.querySelectorAll('.closed_orders_row').forEach(function(a) {
					a.remove()
				})

				// if current request is active, show parts
				if (current_request_index != -1)
					show_bom(allocations_mo[current_request_index].pq_id);
			}

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

		//handles showing/displaying tables on PO report tab
		function show_orders(type, targ) {

			//based on targ, show/hide table
			if (targ.innerHTML.trim() == "+") {
				//update show/hide button
				targ.innerHTML = "-";

				//set display value of type
				u.eid(type + "_orders_table").style.display = "block";

				//depending on type, run function to get orders
				if (type == "complete")
					get_open_orders();
				else if (type == "closed")
					get_closed_orders();
			} else {
				//update show/hide button
				targ.innerHTML = "+";

				//set display value of type
				u.eid(type + "_orders_table").style.display = "none";


			}
		}

		//global to hold current filter & current direction
		var order_filter_type = 'Date Issued',
			order_filter_ascending = true;

		// add event listener to table heads (allow all heads to be filterable)
		$(document).on('click', '.orders_table_headers th', function() {

			//extract header name (ignore "<span" element if already added)
			var span_pos = this.innerHTML.indexOf("<span");
			var check = this.innerHTML;

			//if we clicked on internal notes, ignore
			if (check == "Internal Notes")
				return;

			//if we find a span pos, trim it off
			if (span_pos > 0)
				check = this.innerHTML.substr(0, span_pos);

			//if check is a 'problem' column, we need to go through and update inv_locations
			var problem_heads = ['Est. Ship Date(s)'];

			if (problem_heads.indexOf(check) > -1)
				update_expected_dates();

			//check if element matches previously clicked
			if (order_filter_type == check) {

				//flip direction and filter inventory
				order_filter_ascending = !order_filter_ascending;

				//change arrow depending on current direction
				if (order_filter_ascending)
					u.eid("filter_by_arrow").innerHTML = "&#129095;";
				else
					u.eid("filter_by_arrow").innerHTML = "&#129093;";

			}
			//otherwise set type, auto ascending to true
			else {

				//delete arrow if it exists
				if (u.eid("filter_by_arrow"))
					u.eid("filter_by_arrow").remove();

				//create new one
				var span = document.createElement("span");
				span.innerHTML = "&#129095;";
				span.id = "filter_by_arrow";
				this.appendChild(span);

				//update globals and filter
				order_filter_type = check;
				order_filter_ascending = true;
			}

			//call function to refresh open orders
			refresh_orders_report();
		});

		//function to handle refreshing purchase orders report tab
		/**
		 * input:
		 * param 1 = search (optional) if true, set all order tables to open
		 * 
		 */
		function refresh_orders_report(search = false) {

			//if search is true, open all order tables
			if (search) {
				document.querySelectorAll('.expand_order_tables').forEach(function(a) {
					a.innerHTML = "-";
				})
				u.eid("open_orders_table").style.display = "block";
				u.eid("complete_orders_table").style.display = "block";
				u.eid("closed_orders_table").style.display = "block";
			}

			//get current state of orders
			get_expanded_orders();

			//re-filter jobs
			get_open_orders();
			get_closed_orders();

			//reset current state
			set_expanded_orders();

		}

		//global array that holds list of expanded orders and shipments
		var expanded_items = [];

		//handles getting list of opened projects, so we can re-open after filter
		function get_expanded_orders() {

			//reset array of expanded items
			expanded_items = [];

			//init each set of expanded arrays
			var expanded_orders = [],
				expanded_shipments = [];

			//get class that holds expanded buttons
			var exp_order = u.class("show_hide_button");
			var exp_shipment = u.class("expand_shipment");

			//first loop through orders
			for (var i = 0; i < exp_order.length; i++) {

				//if the inner html is '-', we know it's expanded, so push to global array
				if (exp_order[i].innerHTML == "-") {
					//work back to po_number
					//array.push		 <button>      <td>   <tr>      <td (po_number) <input>     value (acutal po number)
					expanded_orders.push(exp_order[i].parentNode.parentNode.childNodes[1].childNodes[0].value);
				}
			}

			//now loop through shipments and get list as well
			for (var i = 0; i < exp_shipment.length; i++) {

				//if the inner html is '-', we know it's expanded, so push to global array
				if (exp_shipment[i].innerHTML == "-") {
					//work back to po_number
					//array.push		 		<button> 		<td>     <tr>    <td (po_number) <input>     value (acutal po number)
					expanded_shipments.push(exp_shipment[i].parentNode.parentNode.childNodes[2].childNodes[0].value);
				}
			}

			//push to expanded_items global array for use in other functions
			expanded_items['expanded_orders'] = expanded_orders;
			expanded_items['expanded_shipments'] = expanded_shipments;

			console.log(expanded_items);
		}

		//handles open previously expanded items
		//works hand in hand with get_expanded_orders() 
		//get = gets list of expanded items
		//set = opens all previously expanded items
		function set_expanded_orders() {

			//get list of PO # elements using class list
			var po_nums = u.class("po_number");

			//loop through, if we find a match, click on the expand button
			for (var i = 0; i < po_nums.length; i++) {

				//look for match in expanded_items['expanded_orders'] array
				if (expanded_items['expanded_orders'].includes(po_nums[i].value)) {
					//work back to button and click it
					//<input>   <td>       <tr>       <td (button)   <button>     click()
					po_nums[i].parentNode.parentNode.childNodes[0].childNodes[0].click();

				}
			}

			//do the same for shipments
			//get list of shipment ID elements using class list
			var shipments = u.class("shipment_id");

			//loop through, if we find a match, click on the expand button
			for (var i = 0; i < shipments.length; i++) {

				//look for match in expanded_items['expanded_orders'] array
				if (expanded_items['expanded_shipments'].includes(shipments[i].value)) {
					//work back to button and click it
					//<input>   <td>       <tr>       <td (button)   <button>     click()
					shipments[i].parentNode.parentNode.childNodes[0].childNodes[0].click();

				}
			}
		}

		//handles updating each purchase order for closest "expected date"
		function update_expected_dates() {

			//loop through all pq_orders, for each, grab shipment with soonest expected ship date and add to array
			for (var i = 0; i < pq_orders.length; i++) {

				//skip if already filled in
				if (pq_orders[i]['expected_date_hold'] !== null) {

					//get shipments
					var shipments = pq_shipments.filter(function(shipment) {
						return shipment.po_number == pq_orders[i].po_number;
					});

					//order based on expected date
					shipments.sort((a, b) => a['ship_date'].localeCompare(b['ship_date']));

					//loop through shipments and create <input> for each expected date
					for (var j = 0; j < shipments.length; j++) {
						if (shipments[j].ship_date != "") {
							pq_orders[i]['expected_date_hold'] = shipments[j].ship_date;
							break;
						}
					}

					//if still null, set as blank
					if (pq_orders[i]['expected_date_hold'] == null)
						pq_orders[i]['expected_date_hold'] = "";
				}
			}
		}

		//handles sorting set of projects based on user defined criteria (whatever header they clicked on)
		//param 1 = set of pre-filtered orders that needs to be sorted
		function sort_orders(orders) {

			if (order_filter_type != "") {

				//init "filter_by" default to date_issued
				var filter_by = "po_number";

				//go through possible ways to sort array
				if (order_filter_type == "PO #")
					filter_by = "po_number";
				else if (order_filter_type == "Vendor")
					filter_by = "vendor_name";
				else if (order_filter_type == "Date Issued")
					filter_by = "date_issued";
				else if (order_filter_type == "PO Total")
					filter_by = "total_price";
				else if (order_filter_type == "Project Name / Job Name")
					filter_by = "project_name";
				else if (order_filter_type == "Quote #")
					filter_by = "quoteNumber";
				else if (order_filter_type == "Ship To")
					filter_by = "po_ship_to";
				else if (order_filter_type == "Need by Date:")
					filter_by = "need_by_date";
				else if (order_filter_type == "Ack'd")
					filter_by = "acknowledged";
				else if (order_filter_type == "Priority")
					filter_by = "priority";
				else if (order_filter_type == "VP")
					filter_by = "vp_processed";
				//else if (order_filter_type == "Internal Notes")
				//	filter_by = "notes";
				else if (order_filter_type == "Est. Ship Date(s)")
					filter_by = "expected_date_hold";
				else if (order_filter_type == "Unasgd Items")
					filter_by = "unassigned_items";

				//filter matching_inventory (depending on if we need to sort by strings AND ascending/descending)
				if (filter_by == "po_number" || filter_by == "total_price" || filter_by == "unassigned_items") {

					if (filter_by == "po_number")
						filter_by = "po_number_filter";

					if (order_filter_ascending)
						orders.sort((a, b) => a[filter_by] - b[filter_by]);
					else
						orders.sort((a, b) => b[filter_by] - a[filter_by]);

				} else {

					if (order_filter_ascending)
						orders.sort((a, b) => a[filter_by].localeCompare(b[filter_by]));
					else
						orders.sort((a, b) => b[filter_by].localeCompare(a[filter_by]));

				}
			}

			return orders;
		}

		//handles creating list of open orders
		function get_open_orders() {

			//remove any previous rows (if applicable (open and complete))
			document.querySelectorAll('.open_orders_row').forEach(function(a) {
				a.remove()
			})

			document.querySelectorAll('.complete_orders_row').forEach(function(a) {
				a.remove()
			})

			//init 'complete_orders'
			var complete_orders = [];

			//get list orders "Submitted"
			var open_orders = pq_orders.filter(function(order) {
				return order.status == "Submitted";
			});

			//order by priority
			open_orders.sort((a, b) => b['priority'] - a['priority']);

			//if user has part typed into power search, filter for just purchase orders related to that
			open_orders = run_power_search(open_orders);

			//sort current set of orders based on criteria defined by the user
			open_orders = sort_orders(open_orders);

			//go through orders & remove any that have shipped all parts
			for (var i = 0; i < open_orders.length; i++) {

				//get list of parts related to order
				var parts = pq_detail.filter(function(part) {
					return open_orders[i].po_number == part.po_number;
				});

				//filter to see if any parts have yet to receive shipment #
				var no_shipment = parts.filter(function(part) {
					return part.shipment_id == "" || part.shipment_id == null;
				});

				//if no_shipment returns 0 part, need to check status of shipment_ids
				if (no_shipment.length == 0) {

					//filter shipments that match some shipment on parts
					var order_shipments = pq_shipments.filter(function(shipment) {
						return parts.some(function(part) {
							return part.shipment_id == shipment.shipment_id && shipment.shipped == 0;
						});
					});

					//check length of object returned, if 0, remove from open section
					if (order_shipments.length == 0) {
						complete_orders.push(open_orders[i]);
						open_orders.splice(i, 1);
						i = i - 1; //reduce so we don't lose our place in the loop
					}
				}
			}

			//filter open_orders based on user entered info
			open_orders = filter_orders(open_orders, 0);

			//get table (to pass when adding a row)
			var table = u.eid("open_orders_table").getElementsByTagName('tbody')[0];

			//loop through open orders and add to table
			for (i = 0; i < open_orders.length; i++) {
				add_order_row(open_orders[i], table, "open");
			}

			//get table (to pass when adding a row)
			var table = u.eid("complete_orders_table").getElementsByTagName('tbody')[0];

			//loop through completed orders and add to table (if table is showing)
			if (u.eid("complete_orders_table").style.display != "none") {
				//filter complete_orders
				complete_orders = filter_orders(complete_orders, 1);
				for (i = 0; i < complete_orders.length; i++) {
					add_order_row(complete_orders[i], table, "complete");
				}
			}
		}

		//handles creating list of open orders
		function get_closed_orders() {

			//remove any previous rows (if applicable)
			document.querySelectorAll('.closed_orders_row').forEach(function(a) {
				a.remove()
			})

			//get list orders "Submitted"
			var closed_orders = pq_orders.filter(function(order) {
				return order.status == "Received";
			});

			//if user has part typed into power search, filter for just purchase orders related to that
			closed_orders = run_power_search(closed_orders);

			//sort & filter current set of orders based on criteria defined by the user
			closed_orders = sort_orders(closed_orders);
			closed_orders = filter_orders(closed_orders, 2);

			//get table (to pass when adding a row)
			var table = u.eid("closed_orders_table").getElementsByTagName('tbody')[0];

			//loop through open orders and add to table
			for (i = 0; i < closed_orders.length; i++) {
				add_order_row(closed_orders[i], table, "closed");
			}
		}

		/**@author Alex Borchers
		 * Filters orders based on user entered info
		 * 
		 * @param orders {array[objects]} respresents pre-sorted orders
		 * @param id {int} (0, 1, 2) 0 = open, 1 = complete, 2 = closed
		 * 
		 * @returns filtered_orders {array[objects]} filtered orders
		 */
		function filter_orders(orders, id) {

			//get user entered fields
			var po_number = u.eid("po_number" + id).value.toLowerCase().trim(),
				vendor = u.eid("vendor" + id).value.toLowerCase().trim(),
				date_issued = u.eid("date_issued" + id).value.toLowerCase().trim(),
				project_name = u.eid("project_name" + id).value.toLowerCase().trim(),
				quote = u.eid("quote" + id).value.toLowerCase().trim(),
				ship_to = u.eid("ship_to" + id).value.toLowerCase().trim();

			//filter orders based on input fields
			var filtered_orders = orders.filter(function(o) {
				return (po_number == "" || o.po_number.toLowerCase().includes(po_number)) &&
					(vendor == "" || o.vendor_name.toLowerCase().includes(vendor)) &&
					(date_issued == "" || o.date_issued.toLowerCase().includes(date_issued)) &&
					(project_name == "" || o.project_name.toLowerCase().includes(project_name)) &&
					(quote == "" || o.quoteNumber.toLowerCase().includes(quote)) &&
					(ship_to == "" || o.po_ship_to.toLowerCase().includes(ship_to));
			});

			//return filtered orders
			return filtered_orders;
		}

		//handles running power search for specific part or purchase order #
		/**
		 * input: 
		 * param 1 = current filtered list
		 * 
		 * output: 
		 * further filtered list (only matches for part # or po_number are returned)
		 * 
		 */
		//
		function run_power_search(filtered_list) {

			//get both power search inputs
			var power_part = u.eid("order_part_power_search").value.trim(),
				power_po_number = u.eid("order_po_power_search").value.trim().toLowerCase();

			//check base case (no filter entered for either)
			if (power_part == "" && power_po_number == "")
				return filtered_list;

			//check if we have a power searched part
			if (power_part != "" && power_part != null) {

				//search through pq_detail for parts related to "open orders" && matches the part given
				var open_parts = pq_detail.filter(function(part) {
					return filtered_list.some(function(order) {
						return order.po_number == part.po_number;
					});
				});

				//now search through open parts for part listed in search background
				var relevant_orders = open_parts.filter(function(part) {
					return part.part_id.toLowerCase() == power_part.toLowerCase();
				});

				//now get rid of repearts and sort low to highest
				filtered_list = filtered_list.filter(function(o_order) {
					return relevant_orders.some(function(r_order) {
						return o_order.po_number == r_order.po_number;
					});
				});
			}

			//check if we have a power searched po_number
			if (power_po_number != "" && power_po_number != null) {

				//now search through open parts for part listed in search background
				filtered_list = filtered_list.filter(function(order) {
					return order.po_number.toLowerCase().includes(power_po_number);
				});
			}

			//return filtered list
			return filtered_list;

		}

		//handles creating a row for open_orders_table
		//param 1 = open order object
		//param 2 = table to be added to
		//param 3 = type (open, complete, closed)
		function add_order_row(order, table, type) {

			//init array to help create each row
			var cell_key = ['po_number', 'vendor_name', 'date_issued', 'total_price', 'project_name', 'quoteNumber', 'po_ship_to',
				'need_by_date', 'acknowledged', 'priority', 'vp_processed', 'revision', 'notes', 'expected_date', 'unassigned_items'
			];

			//overwrite 'revision'
			order['revision'] = "<button onclick = 'create_revision'>Create Order Revision</button>"

			//set values not defined in the objects (held elsewhere)
			//order = set_undefined(order);

			//add new row to table & add class list
			var row = table.insertRow(-1);
			row.classList.add(type + "_orders_row");

			//add +/- icon so we can expand and contract rows for more info
			var cell = row.insertCell(0);
			var button = document.createElement("button");
			button.innerHTML = "+";
			button.classList.add("show_hide_button")
			button.addEventListener("click", show_hide_order_info);
			cell.appendChild(button);

			//loop through keys and add to table
			for (var i = 0; i < cell_key.length; i++) {

				var cell = row.insertCell(i + 1);
				var input = document.createElement("input");
				input.classList.add("refresh_" + type + "_order");
				input.classList.add(cell_key[i]);

				//for certain cells, change input type
				if (cell_key[i] == "acknowledged" || cell_key[i] == "priority" || cell_key[i] == "vp_processed") {
					input.type = "checkbox";
					cell.style.textAlign = "center";

					//default checked to saved value.. check if we have a number saved (from sql) and overwrite if we do
					input.checked = parseInt(order[cell_key[i]]);

					//db saves values as 0 == false, 1 == true
					if (order[cell_key[i]] == "0")
						input.checked = false
					else if (order[cell_key[i]] == "1")
						input.checked = true;
				} else if (cell_key[i] == "need_by_date")
					input.type = "date";

				//for certain cells, format as money
				if (cell_key[i] == "total_price")
					input.value = accounting.formatMoney(order[cell_key[i]]);
				//overwrite input for revision cell (render button)
				else if (cell_key[i] == "revision") {
					input = document.createElement("button");
					input.innerHTML = "Revise";
					input.addEventListener("click", create_revision);
				}
				//format date cells how team likes to see them (see javascript/js_helper.js)
				else if (cell_key[i] == "date_issued") {
					input.value = format_date(order[cell_key[i]]);
				}
				//get most recent note entry
				else if (cell_key[i] == "notes") {
					input.value = get_recent_note(order.po_number, 'po');
				}
				//add event listener to open google drive link
				else if (cell_key[i] == "po_number") {

					//set value
					input.value = order[cell_key[i]];

					//set href if not null
					if (order.googleDriveLink != null && order.googleDriveLink != "") {
						input.addEventListener("click", open_google_drive_folder);
						input.classList.add("po_number_link")
					}
				}
				//all other scenarios (exclude expected_date)
				else if (cell_key[i] != "expected_date")
					input.value = order[cell_key[i]];

				//set to read-only (some cells have excemptions from this)
				input.readOnly = true;

				//if this is cell that could hold more than 1 entry, treat differently
				if (cell_key[i] == "project_name" || cell_key[i] == "quoteNumber")
					cell.appendChild(create_custom_inputs(order, cell_key[i]));
				//add to table cell (treat expected_date differently)
				else if (cell_key[i] != "expected_date")
					cell.appendChild(input);
				else
					cell.innerHTML = get_expected_shipment_dates(order.po_number);
				//cell.appendChild(get_expected_shipment_dates(order.po_number));

			}
		}

		/**@author Alex Borchers
		 * Handles generating custom input for project_name and quoteNumber attributes (may have multiple entries)
		 * 
		 * @param order {object} matches row from fst_pq_orders (with info appended, see query in PHP)
		 * @param ckey {string} column key (project_name / quoteNumber)
		 * 
		 * @returns {HTMLEntity} <div> with 1 or more input fields (instantly appended to table)
		 */
		function create_custom_inputs(order, ckey) {

			// if key is null, update to blank
			if (order[ckey] == null)
				order[ckey] = "";

			//split string on |
			var text_array = order[ckey].split("|");

			//create div to append inputs to
			var div = document.createElement("div");

			//loop through array and push each value
			for (var i = 0; i < text_array.length; i++) {

				//create & format input & append to div
				var input = document.createElement("input");
				input.value = text_array[i];
				input.classList.add(ckey);
				input.readOnly = true;
				div.appendChild(input);
			}

			//return completed div
			return div;
		}

		//handles getting expected shipment dates in descending order 
		function get_expected_shipment_dates(po_number) {

			//find shipment related to PO number
			var shipments = pq_shipments.filter(function(shipment) {
				return shipment.po_number == po_number;
			});

			//order based on expected date
			shipments.sort((a, b) => a['ship_date'].localeCompare(b['ship_date']));

			//init <span> to hold all <inputs> to be returned
			var fake_span = "",
				no_ship_date = "";

			//loop through shipments and create <input> for each expected date
			for (var i = 0; i < shipments.length; i++) {
				if (shipments[i].ship_date != "")
					fake_span += "<input class = 'expected_date_overview' value = '(" + shipments[i].shipment_id + ") - " + format_date(shipments[i].ship_date) + "' readonly > ";
				else
					no_ship_date += shipments[i].shipment_id + ", ";
				// var input = document.createElement("input");
				// input.type = "date";
				// input.readOnly = true;
				// input.value = shipments[i].ship_date;
				// span.appendChild(input);
			}

			//if there are shipments with no ship date, include
			if (no_ship_date != "") {
				//trim last comma
				no_ship_date = no_ship_date.substr(0, no_ship_date.length - 2);

				//add to fake_span
				fake_span += "<input class = 'expected_date_overview' value = '(" + no_ship_date + ") - TBA' readonly >";
			}

			return fake_span;

		}

		//handles opening google drive folder when clicking on purchase order #
		function open_google_drive_folder() {

			//get po number from 'this'
			var po_number = this.value;

			//get index in pq_orders
			var index = pq_orders.findIndex(object => {
				return object.po_number == po_number;
			});

			// navigate to google-drive folder
			if (index != -1)
				window.open(pq_orders[index].googleDriveLink);
		}

		//handles creating a revision for a given PO
		function create_revision() {

			//work way back to purchase order
			var td = this.parentNode;
			var tr = td.parentNode;
			var po_td = tr.childNodes[1];
			var po_number = po_td.childNodes[0].value;
			//var vendor_td = tr.childNodes[2];
			//var vendor = vendor_td.childNodes[0].value;

			//send to server and reset status of purchase order/fst_allocations_mo entry, increase revision number
			//initalize form data (will carry all form data over to server side)
			var fd = new FormData();

			//serialize arrays and pass them to fd 
			fd.append('po_number', po_number);
			//fd.append('vendor', vendor);

			//add tell variable
			fd.append('tell', 'create_revision');

			//access database
			$.ajax({
				url: 'terminal_orders_helper.php',
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

					//give successful message and refresh page
					alert("A revision has been successfully create for PO #" + po_number + ". Refreshing page...");
					window.location.reload();

				}
			});
		}

		//handles showing/hiding information on open_order report
		//use 'this' to see if we need to show/hide content & to see which order we need to show
		function show_hide_order_info() {

			//work way to PO number through table structure
			var td = this.parentNode;
			var tr = td.parentNode;
			var po_td = tr.childNodes[1];
			var po_num = po_td.childNodes[0].value;

			//get table id
			//			  <tr>   <tbody>	<table>
			var table_id = tr.parentNode.parentNode.id;

			//use table_id to get type (open, complete, closed)
			var type = table_id.substr(0, table_id.indexOf("_"));

			//grab show/hide symbol
			var show_hide = this.innerHTML;

			//based on innerHTML, show/hide content
			if (show_hide == "+") {
				this.innerHTML = "-";
				//create_new_shipment_button();
				get_order_shipments(po_num, tr.rowIndex, type);
				td.classList.add("active_cell");
			} else {
				this.innerHTML = "+";

				//remove previously added row
				u.eid("orders_detail" + po_num).remove();
				td.classList.remove("active_cell");
			}
		}

		//handles generating new shipment table for orders team
		/*
		function create_new_shipment_table(type){

			//get open_orders table
			var orders_t = u.eid(type + "_orders_table").getElementsByTagName('tbody')[0];0
			var row = orders_t.insertRow(row_index);
			row.classList.add("orders_detail");
			row.classList.add("orders_row");
			var cell = row.insertCell(0);
			cell.colSpan = orders_t.rows[0].cells.length;
			cell.append(table);

		}*/

		//globals used to create headers and body of open order report
		var order_header = ['', 'Shipped', 'Shipment ID', 'Tracking', 'Carrier', 'Cost', 'Est. Ship Date', 'Expected Date', 'Received By', 'Notes', ''];

		//used to get list of open_order parts based on given po number
		//param 1 = po number
		//param 2 = row index (where the parts need to be inserted)
		//param 3 = type (open, complete, closed)
		function get_order_shipments(po_num, row_index, type) {

			//get orders table (used throughout function)
			var orders_t = u.eid(type + "_orders_table").getElementsByTagName('tbody')[0];
			0

			//get list of parts assigned po number
			var shipments = pq_shipments.filter(function(shipment) {
				return shipment.po_number == po_num;
			});

			//loop and create a table with these parts
			var table = document.createElement("table");
			table.classList.add("standardTables");
			table.classList.add("order_detail_table")
			table.id = "shipments_summary_table" + po_num;

			//create headers
			var row = table.insertRow(-1);
			row.classList.add("order_detail_header");

			for (var i = 0; i < order_header.length; i++) {
				var cell = row.insertCell(i);
				cell.innerHTML = order_header[i];
			}

			//loop through parts and call function to create row for given part
			for (var i = 0; i < shipments.length; i++) {
				add_order_shipment_row(table, shipments[i]);
			}

			//check parts related to this order, add unassigned row if not all parts under shipment
			var parts = pq_detail.filter(function(part) {
				return part.po_number == po_num && (part.shipment_id == "" || part.shipment_id == null);
			});

			if (parts.length > 0) {

				//create new row from given table
				var row = table.insertRow(-1);

				//insert expansion button as first element of row
				//create new cell
				var cell = row.insertCell(0);

				//create input element & add classlist
				var button = document.createElement("button");
				button.classList.add("expand_shipment");
				button.innerHTML = "+";

				//append to cell
				cell.appendChild(button);

				//create 1 more cell that says unassigned
				var cell = row.insertCell(1);
				var input = document.createElement("input");
				input.readOnly = true;
				input.value = "No shipments assigned";
				cell.appendChild(input);
				cell.colSpan = orders_t.rows[0].cells.length;
			}

			//create button to send create new shipments
			var button = document.createElement("button");
			button.innerHTML = "Create New Shipment";
			//button.classList.add("large_button");
			button.classList.add("shipment_button");

			//add row to orders table and push table to new row
			var row = orders_t.insertRow(row_index - 1);
			row.classList.add("orders_detail");
			row.classList.add(type + "_orders_row");
			row.id = "orders_detail" + po_num;
			var cell = row.insertCell(0);
			cell.classList.add("active_cell");
			cell.colSpan = orders_t.rows[0].cells.length;

			//only add button if we have parts to show
			if (parts.length > 0)
				cell.append(button);

			//add newly created table to row
			cell.append(table);

		}

		//globals that define sql id's for a given part
		var open_order_shipping_content = ['shipped', 'shipment_id', 'tracking', 'carrier', 'cost', 'ship_date', 'arrival', 'received_by', 'notes'];

		//adds open order row for shipment items
		//param 1 = the table that we are adding
		//param 2 = the shipment we are adding
		function add_order_shipment_row(table, part) {

			//create new row from given table
			var row = table.insertRow(-1);

			//insert expansion button as first element of row
			//create new cell
			var cell = row.insertCell(0);

			//create input element & add classlist
			var button = document.createElement("button");
			button.classList.add("expand_shipment");
			button.innerHTML = "+";

			//append to cell
			cell.appendChild(button);

			//loop through global which defines sql table id's and read out information to user
			for (var i = 0; i < open_order_shipping_content.length; i++) {

				//create new cell
				var cell = row.insertCell(i + 1);

				//create input element & add classlist
				var input = document.createElement("input");
				input.classList.add(open_order_shipping_content[i]);
				input.classList.add("refresh_shipping")

				//define input type depending on content
				if (open_order_shipping_content[i] == "shipped") {
					input.type = "checkbox";
					cell.style.textAlign = "center";

					//default checked to saved value.. check if we have a number saved (from sql) and overwrite if we do
					input.checked = part[open_order_shipping_content[i]];

					//db saves values as 0 == false, 1 == true
					if (part[open_order_shipping_content[i]] == "0")
						input.checked = false
					else if (part[open_order_shipping_content[i]] == "1")
						input.checked = true;

				} else if (open_order_shipping_content[i] == "ship_date" || open_order_shipping_content[i] == "arrival") {
					input.type = "date";
				} else if (open_order_shipping_content[i] == "cost") {
					input.type = "number";
				}

				//update input value based on saved parts info
				if (open_order_shipping_content[i] == "notes")
					input.value = get_recent_note(part.shipment_id, 'shipment');
				else
					input.value = part[open_order_shipping_content[i]];


				//append to cell (treat delete_shipment different)
				cell.appendChild(input);

				//set to readonly depending on type of content
				if (open_order_shipping_content[i] == "shipment_id" || open_order_shipping_content[i] == "notes")
					input.readOnly = true;

			}

			//add button to remove shipment at end of each row
			var cell = row.insertCell(open_order_shipping_content.length + 1);
			cell.innerHTML = "&#10006";
			cell.classList.add("delete_shipment");
		}

		//used to get list of open_order parts based on given po number
		//param 1 = po number
		//param 2 = row index (where the parts need to be inserted)
		function get_order_parts(shipment_id, row_index, po_num) {

			//get list of parts assigned po number
			var parts = [];

			//if shipment id = "No Shipment Assigned", then grab all parts which have nothing assigned yet
			if (shipment_id.substr(0, 12) == "no_shipments") {
				parts = pq_detail.filter(function(part) {
					return part.po_number == po_num && (part.shipment_id == "" || part.shipment_id == null);
				});
			}
			//otherwise find matching shipment parts
			else {
				parts = pq_detail.filter(function(part) {
					return part.shipment_id == shipment_id;
				});
			}

			//loop and create a table with these parts
			var table = document.createElement("table");
			table.classList.add("standardTables");
			table.classList.add("order_detail_table");

			//create headers
			var row = table.insertRow(-1);
			row.classList.add("order_detail_header");

			//set array of headers
			var headers = ["Part Number", "Quantity", "Unit Cost", "Description", "Manufacturer", "UOM"];

			for (var i = 0; i < headers.length; i++) {

				var cell = row.insertCell(i);
				cell.innerHTML = headers[i];

			}

			//loop through parts and call function to create row for given part
			for (var i = 0; i < parts.length; i++) {
				add_open_order_part_row(table, parts[i]);
			}

			//add row to orders table and push table to new row
			var shipment_t = u.eid("shipments_summary_table" + po_num).getElementsByTagName('tbody')[0];
			var row = shipment_t.insertRow(row_index + 1);
			row.classList.add("orders_detail");
			row.classList.add("shipment_detail_row");
			row.id = 'shipment_' + shipment_id;
			var cell = row.insertCell(0);
			cell.colSpan = shipment_t.rows[0].cells.length;
			cell.append(table);

		}

		//globals that define sql id's for a given part
		var open_order_content = ['part_id', 'vendor_qty', 'vendor_cost', 'partDescription', 'manufacturer', 'uom'];

		function add_open_order_part_row(table, part) {

			//check if null
			if (part.partDescription == null)
				part = update_part_with_user_info(part);

			//create new row from given table
			var row = table.insertRow(-1);

			//loop through global which defines sql table id's and read out information to user
			for (var i = 0; i < open_order_content.length; i++) {

				//create new cell & write out to html
				var cell = row.insertCell(i);

				//style differently for certain cells
				if (open_order_content[i] == "vendor_cost")
					cell.innerHTML = accounting.formatMoney(part[open_order_content[i]]);
				else if (open_order_content[i] == "vendor_qty")
					cell.innerHTML = get_quantity_breakout(part);
				else
					cell.innerHTML = part[open_order_content[i]];


			}

			//add button to remove shipment at end of each row
			var cell = row.insertCell(open_order_content.length);
			cell.innerHTML = "&#10006";
			cell.classList.add("delete_shipment_item");
		}

		//handles getting quantity breakout for a given part when expanding shipping lines
		//returns [quantity on shipment] / [quantity on order]
		//so we can see if a part is being partially shipped
		function get_quantity_breakout(part) {

			//filter out all parts on order
			on_order = pq_detail.filter(function(p) {
				return p.po_number == part.po_number && p.part_id == part.part_id;
			});

			//loop through and get order qty
			var order_qty = 0;

			for (var i = 0; i < on_order.length; i++) {
				order_qty += parseInt(on_order[i].vendor_qty);
			}

			//return breakout
			return part.vendor_qty + " / " + order_qty;
		}

		//handles updating shipping info on change
		//param 1 in the form of 'this' <select>
		function update_shipping_info(targ, type) {

			//handle differently depending on type
			if (type == "po") {

				//if using temporary location, show the custom name row and return
				if (targ == "Temporary Location") {
					u.eid("po_ship_to_temporary_row").style.visibility = "visible";
					return;
				} else
					u.eid("po_ship_to_temporary_row").style.visibility = "collapse";

				//if value is add new location, open dialog to do so
				if (targ == "Add Custom Location") {
					$("#custom_location_dialog").dialog({
						width: "auto",
						height: "auto",
						dialogClass: "fixedDialog",
					});
					return;
				}

				//targ.value will be the index of the address we need
				if (targ == "") {
					u.eid("po_ship_to_street").value = "";
					u.eid("po_ship_to_street2").value = "";
					u.eid("po_ship_to_city").value = "";
					u.eid("po_ship_to_state").value = "";
					u.eid("po_ship_to_zip").value = "";
					u.eid("attention").value = "";
					u.eid("attention_phone").value = "";
					u.eid("attention_email").value = "";
				} else {

					//find index in array
					var index = staging_locations.findIndex(object => {
						return object.name == targ;
					});

					//update values based on index
					u.eid("po_ship_to_street").value = staging_locations[index].address;
					u.eid("po_ship_to_city").value = staging_locations[index].city;
					u.eid("po_ship_to_state").value = staging_locations[index].state;
					u.eid("po_ship_to_zip").value = staging_locations[index].zip;
					u.eid("attention").value = staging_locations[index].recipient;
					u.eid("attention_phone").value = staging_locations[index].phone;
					u.eid("attention_email").value = staging_locations[index].email;
				}
			} else if (type == "custom") {

				//targ.value will be the index of the address we need
				if (targ == "") {
					u.eid("custom_street").value = "";
					u.eid("custom_city").value = "";
					u.eid("custom_state").value = "";
					u.eid("custom_zip").value = "";
					u.eid("custom_poc_name").value = "";
					u.eid("custom_poc_number").value = "";
				} else {

					//find index in array
					var index = staging_locations.findIndex(object => {
						return object.name == targ;
					});

					//update values based on index
					u.eid("custom_street").value = staging_locations[index].address;
					u.eid("custom_city").value = staging_locations[index].city;
					u.eid("custom_state").value = staging_locations[index].state;
					u.eid("custom_zip").value = staging_locations[index].zip;
					u.eid("custom_poc_name").value = staging_locations[index].recipient;
					u.eid("custom_poc_number").value = staging_locations[index].email;
				}
			}
		}

		/**@author Alex Borchers
		 * Handles deleting purchase order # and removing from database and queue
		 * 
		 * @returns void
		 */
		function delete_purchase_order() {

			// send validation message, make user confirm they want to do this.
			var message = "Are you sure you would like delete this Purchase Order? (This cannot be undone)";

			// send message to user, return if they do not confirm
			if (!confirm(message))
				return;

			// otherwise, grab purchase order # showing, send to server and remove
			var fd = new FormData();
			fd.append('po_number', u.eid("po_number").value);
			fd.append('tell', 'delete_po');

			$.ajax({
				type: 'POST',
				url: 'terminal_orders_helper.php', // Change to PHP filename
				data: fd,
				processData: false,
				contentType: false
			}).done(function(response) {

				// check for error
				if (response != "") {
					alert("[ERROR] There was an error deleting this PO, please contact fst@piersonwireless.com for assistance. " + response);
					return;
				}

				// send confirmation to user, remove po from fst_pq_orders
				alert("This PO has been successfully deleted.");

				// get index & remove
				var index = pq_orders.findIndex(object => {
					return object.po_number == u.eid("po_number").value;
				});
				pq_orders.splice(index, 1);

				// find any matches in pq_detail, and unset
				var unset_parts = pq_detail.filter(object => {
					return object.po_number == u.eid("po_number").value;
				});

				for (var i = 0; i < unset_parts.length; i++) {
					unset_parts[i].po_number = "";
				}

				// refresh queues
				initialize_vendor_list();
				refresh_po_list();

				// hide po info div
				u.eid("vendor_po_div").style.display = "none";
				u.eid("print_preview_window").style.display = "none";

			});
		}

		//handles validating PO output to vendors before sending
		function validate_pdf_handler(type) {

			//check if user has filled out necessary fields
			var check_fields = ['date_ordered', 'ordered_by', 'need_by_date', 'payment_terms', 'ship_via', 'po_ship_to', 'po_ship_to_street', 'po_ship_to_city',
				'po_ship_to_state', 'po_ship_to_zip', 'attention', 'attention_phone', 'email_to'
			];

			//sending only 1 PO (current)
			if (type == "current" || type == "none") {

				// update global for POs to use when sending
				use_orders = [];
				use_orders.push(pq_orders[current_order_index]['po_number']);

				//first check to make sure it is finalized
				if (pq_orders[current_order_index]['ready'] == 0 || pq_orders[current_order_index]['ready'] === false) {
					alert("Please finalize this PO before proceeding.");
					return;
				}

				//loop through required fields and make sure user has info entered
				var required_error = false;
				for (var j = 0; j < check_fields.length; j++) {
					if (u.eid(check_fields[j]).value == "") {
						u.eid(check_fields[j]).classList.add("required_error");
						required_error = true;
					} else
						u.eid(check_fields[j]).classList.remove("required_error");
				}

				//alert user if error
				if (required_error) {
					alert("The order you are trying to process is missing required information, see the missing information highlighted in yellow.");
					return;
				}
			}
			//sending ALL POs in queue (all)
			else if (type == "all") {

				// update global for POs to use when sending
				use_orders = purchase_orders;

				//determines if we pass checks or not
				var all_final = true,
					info_problems = "";

				//first, loop through all vendors, check to make sure they are finalized
				for (var i = 0; i < purchase_orders.length; i++) {

					// get index in pq_orders
					var order_index = pq_orders.findIndex(object => {
						return object.po_number == purchase_orders[i];
					});

					//sql saves true = 1, false = 0.. if changes are made during the session, they will be stored as true and false.. must check for either scenario of false (0 or false)
					if (pq_orders[order_index]['ready'] == 0 || pq_orders[order_index]['ready'] === false) {
						u.eid("po-list-label-" + purchase_orders[i]).style.backgroundColor = "red";
						all_final = false;
					}
					// otherwise, make sure the labels are colored correctly
					else
						u.eid("po-list-label-" + purchase_orders[i]).style.backgroundColor = "#ffffff";

					//loop through required fields and make sure user has info entered
					for (var j = 0; j < check_fields.length; j++) {
						if (pq_orders[order_index][check_fields[j]] == "")
							info_problems += pq_orders[order_index].po_number + " - " + check_fields[j] + "\n";
					}
				}

				//return error if not all final
				if (!all_final) {
					alert("Some of your orders have not been finalized. Please review any highlighted in red");
					return;
				}

				//return if we found errors with user entered info
				if (info_problems != "") {
					alert("Some of your orders are missing required information. Please see detail below: " + info_problems);
					return;
				}
			}

			//if we pass checks, send to update_queue (export_pdf_handler is called from their)
			export_pdf_handler(type);
		}

		//global to tell if project is a preview or not
		var print_preview = true,
			process_type;

		function export_pdf_handler(type) {

			//if we are just previewin the pdf
			if (type == "preview") {
				print_preview = true;
				render_vendor_po(current_order_index, true);
				return;
			}

			//set updating to true
			print_preview = false;

			//if sending, save info first
			update_queue(true);

			//update type to global variable
			process_type = type;

			//sending only 1 PO (current)
			if (type == "current" || type == "none") {

				//disable both buttons which could lead to submitting more POs
				u.eid("send_button").disabled = true;
				u.eid("send_button_all").disabled = true;
				u.eid("send_button_none").disabled = true;

				render_vendor_po(current_order_index, true);
			}
			//sending ALL POs in queue (all)
			else if (type == "all") {

				//disable both buttons which could lead to submitting more POs
				u.eid("send_button").disabled = true;
				u.eid("send_button_all").disabled = true;
				u.eid("send_button_none").disabled = true;

				//then, loop through all vendors, create POs and send to vendors emails
				for (var i = 0; i < purchase_orders.length; i++) {

					// get index in pq_orders
					var order_index = pq_orders.findIndex(object => {
						return object.po_number == purchase_orders[i];
					});

					//if the last one, set send to true
					if (i == purchase_orders.length - 1)
						render_vendor_po(order_index, true);
					else
						render_vendor_po(order_index, true);

				}
			}
		}

		//global that holds current index being rendered
		var index_being_rendered = null;

		//global used to determine if PO is for multiple projects
		var multiple_projects = false;

		//handles rendering PDF for vendor purchase order
		//param 1 = index (target index to create pdf with - matches index in pq_orders object & in fst_pq_orders table)
		//param 2 = send (trigger to send emails or not (boolean true/false))
		function render_vendor_po(index, send = false) {

			//used to generate image base 64 url
			var c = document.createElement('canvas');

			//depending on type, grab logo
			var img = u.eid('pw_logo');
			var logo_width = 175;
			var column_width = 400;

			c.height = img.naturalHeight;
			c.width = img.naturalWidth;
			var ctx = c.getContext('2d');
			ctx.drawImage(img, 0, 0, c.width, c.height);

			//hold image object used in pdf
			var base64String = c.toDataURL();

			//get all project info (through today's date)
			var po_number = pq_orders[index].po_number;

			//add revision if applicable
			if (pq_orders[index].revision != "0")
				po_number += "-" + pq_orders[index].revision;

			var vendor_id = pq_orders[index].vendor_id;
			var vendor = pq_orders[index].vendor_name;
			var vendor_street = pq_orders[index].vendor_street;
			var vendor_street2 = pq_orders[index].vendor_street2;
			var vendor_city = pq_orders[index].vendor_city;
			var vendor_state = pq_orders[index].vendor_state;
			var vendor_zip = pq_orders[index].vendor_zip;
			var date_ordered = pq_orders[index].date_ordered;
			var ordered_by = pq_orders[index].ordered_by;
			var need_by_date = pq_orders[index].need_by_date;
			var payment_terms = pq_orders[index].payment_terms;
			var notes = pq_orders[index].notes;
			var bill_to = pq_orders[index].bill_to;
			var bill_to_street = pq_orders[index].bill_to_street;
			var bill_to_city = pq_orders[index].bill_to_city;
			var bill_to_state = pq_orders[index].bill_to_state;
			var bill_to_zip = pq_orders[index].bill_to_zip;
			var po_ship_to_street = pq_orders[index].po_ship_to_street;
			var po_ship_to_street2 = pq_orders[index].po_ship_to_street2;
			var po_ship_to_city = pq_orders[index].po_ship_to_city;
			var po_ship_to_state = pq_orders[index].po_ship_to_state;
			var po_ship_to_zip = pq_orders[index].po_ship_to_zip;
			var notes = pq_orders[index].vendor_po_notes;
			var vp_number = pq_orders[index].vp_id.replaceAll("|", "\n");

			// update global if multple projects are included in PO
			if (pq_orders[index].vp_id.includes("|"))
				multiple_projects = true;
			else
				multiple_projects = false;

			//create addresses for vendor / PO based on entered info (if we have street2 or not)
			var vendor_address, po_ship_to_address;

			if (vendor_street2 == "" || vendor_street2 == null)
				vendor_address = vendor_street + "\n" + vendor_city + ", " + vendor_state + " " + vendor_zip;
			else
				vendor_address = vendor_street + "\n" + vendor_street2 + "\n" + vendor_city + ", " + vendor_state + " " + vendor_zip;

			if (po_ship_to_street2 == "" || po_ship_to_street2 == null)
				po_ship_to_address = po_ship_to_street + "\n" + po_ship_to_city + ", " + po_ship_to_state + " " + po_ship_to_zip;
			else
				po_ship_to_address = po_ship_to_street + "\n" + po_ship_to_street2 + "\n" + po_ship_to_city + ", " + po_ship_to_state + " " + po_ship_to_zip;

			//if we have location name, add to po_ship_to_address
			if (pq_orders[index].po_ship_to != "" && pq_orders[index].po_ship_to != "Temporary Location")
				po_ship_to_address = pq_orders[index].po_ship_to + "\n" + po_ship_to_address;
			else if (pq_orders[index].po_ship_to == "Temporary Location")
				po_ship_to_address = pq_orders[index].po_ship_to_temporary + "\n" + po_ship_to_address;

			//add ship via:
			po_ship_to_address += "\nShip Via: " + pq_orders[index].ship_via;;

			//add ship_to contact info
			po_ship_to_address += "\nAttention: " + pq_orders[index].attention;

			//add email if we have it
			if (pq_orders[index].attention_email != "")
				po_ship_to_address += "\n" + pq_orders[index].attention_email;

			//add phone if we have it
			if (pq_orders[index].attention_phone != "")
				po_ship_to_address += "\n" + pq_orders[index].attention_phone;

			//update current index being rendered
			index_being_rendered = index;

			//get today's date
			let today = new Date().toLocaleDateString();

			//generate document based on criteria
			var docDefinition = {
				pageSize: 'A4',
				pageMargins: [40, 30, 40, 100], //[horizontal, vertical] or [left, top, right, bottom]
				defaultStyle: {
					font: 'Times'
				},
				/*header: [
					{
					 	text: shop + " Pick Ticket", 
						alignment: 'left', 
						style: 'header_style'
					}
				],*/
				footer: function(currentPage, pageCount, pageSize) {
					// last page, add picked by and checked by at bottom
					if (currentPage == pageCount) {
						return [{
								text: "The value of this Purchase Order may not be exceeded without the written approval of Pierson Wireless Corp, which shall be in the form of an amendment or change order.",
								alignment: 'center',
								style: 'footer_style_red'
							},
							{
								text: "Pierson Wireless Corp. | 11414 S 145th St. | Omaha, Nebraska 68138\n\nPhone: 402-421-9000 | Fax: 866-525-8296",
								alignment: 'center',
								style: 'footer_style'
							}
						]
					}
				},
				content: [{
						columns: [{
								image: base64String,
								width: logo_width,
								style: 'header_logo',
								lineHeight: 6
							},
							{
								text: "Purchase Order",
								alignment: 'right',
								style: 'header_large'
							}
						],
					},
					{
						text: "Purchase Order #" + po_number,
						style: 'header_small',
						alignment: 'right'
					},
					{
						//black line to split purchase order with po # in header
						canvas: [{
							type: 'line',
							x1: 300,
							y1: -89,
							x2: 515,
							y2: -89,
							lineWidth: 3
						}]
					},
					{
						text: "Job: " + vp_number,
						style: 'header_vp_number',
						alignment: 'right'
					},
					{
						//black line that runs from top of vendor header
						canvas: [{
							type: 'line',
							x1: 0,
							y1: 0,
							x2: 515,
							y2: 0,
							lineWidth: 1
						}]
					},
					{
						columns: [
							render_pdf_table('single_block', 'Vendor:\n' + vendor_id),
							{
								text: vendor + "\n" + vendor_address,
								alignment: 'left',
								style: 'body_text',
								width: 160
							},
							{
								text: "",
								width: 10
							},
							{
								text: "Date Ordered: \nOrdered By: \nNeed By Date: \nPayment Terms: ",
								alignment: 'left',
								style: 'body_text'
							},
							{
								text: format_date(date_ordered) + "\n" + ordered_by + "\n" + format_date(need_by_date) + "\n" + payment_terms,
								alignment: 'left',
								style: 'body_text',
								width: 190
							},
						],
					},
					{
						text: "***Email Invoice to AccountsPayable@piersonwireless.com***",
						style: 'header_small_bold',
						alignment: 'left'
					},
					{
						//black line that runs from top of bill & ship to boxes
						canvas: [{
							type: 'line',
							x1: 0,
							y1: 0,
							x2: 515,
							y2: 0,
							lineWidth: 1
						}]
					},
					{
						columns: [
							render_pdf_table('single_block', 'Bill:'),
							{
								text: bill_to + "\n" + bill_to_street + "\n" + bill_to_city + ", " + bill_to_state + " " + bill_to_zip,
								alignment: 'left',
								style: 'body_text',
								width: 160
							},
							{
								text: "",
								width: 10
							},
							render_pdf_table('single_block', 'Ship To:'),
							{
								text: po_ship_to_address,
								alignment: 'left',
								style: 'body_text',
								width: 190
							},
						],
					},
					render_pdf_table("bom", "", index),
					{
						//change comment
						columns: [{
							text: notes,
							style: 'header_small',
							alignment: 'left',
							width: 350,
							lineHeight: 1.2
						}]
					}
				],
				styles: {
					header_style: {
						fontSize: 12,
						italics: true,
						color: 'gray',
						margin: [40, 15, 0, 0] //[left, top, right, bottom]
					},
					header_sub: {
						fontSize: 14,
						bold: true,
						italics: true,
						margin: [0, 0, 0, 10] //[left, top, right, bottom]
					},
					header_logo: {
						margin: [-20, -17, 0, 20] //[left, top, right, bottom]
					},
					header_main: {
						fontSize: 10.5,
						margin: [0, 20, 0, 0], //[left, top, right, bottom]
						lineHeight: 1.2
					},
					header_pick: {
						fontSize: 28,
						margin: [40, 0, 40, 10], //[left, top, right, bottom]
						bold: true
					},
					header_large: {
						fontSize: 24,
						bold: true,
						margin: [0, 0, 0, 0] //[left, top, right, bottom]
					},
					header_small: {
						fontSize: 11,
						margin: [0, -50, 0, 70] //[left, top, right, bottom]
					},
					header_vp_number: {
						fontSize: 11,
						margin: [0, -30, 0, 10], //[left, top, right, bottom]
						color: 'red'
					},
					header_small_bold: {
						fontSize: 11,
						bold: true,
						margin: [0, -20, 0, 5] //[left, top, right, bottom]
					},
					body_text: {
						fontSize: 11,
						margin: [0, 10, 0, 40], //[left, top, right, bottom]
						lineHeight: 1.2,
						alignment: 'justify'
					},
					body_text_bold: {
						fontSize: 11,
						margin: [0, 0, 0, 0], //[left, top, right, bottom]
						lineHeight: 1.2,
						alignment: 'justify',
						bold: true
					},
					red_header: {
						fontSize: 11,
						margin: [0, 0, 0, 0], //[left, top, right, bottom]
						lineHeight: 1.2,
						alignment: 'justify',
						bold: true,
						color: 'red'
					},
					table_header_margin: {
						fontSize: 10,
						bold: true,
						fillColor: '#114B95',
						color: 'white',
						alignment: 'Left',
						margin: [0, 5, 0, 5] //[left, top, right, bottom]

					},
					table_header: {
						fontSize: 10,
						bold: true,
						fillColor: '#114B95',
						color: 'white',
						alignment: 'center'
					},
					table_body: {
						fontSize: 10,
						margin: [0, 20, 0, 10], //[left, top, right, bottom]
						unbreakable: true,
						lineHeight: 1.2
					},
					single_block: {
						fontSize: 11,
						margin: [0, 0, 0, 10], //[left, top, right, bottom]
						unbreakable: true,
						lineHeight: 1.2
					},
					total_row: {
						fontSize: 9.5,
						bold: true
					},
					sub_total_style: {
						margin: [0, 10, 0, 10] //[left, top, right, bottom]
					},
					total_style: {
						bold: true,
						margin: [0, 5, 0, 5] //[left, top, right, bottom]
					},
					footer_style: {
						fontSize: 10,
						italics: true,
						color: 'gray',
						margin: [40, 10, 40, 0] //[left, top, right, bottom]
					},
					footer_style_red: {
						fontSize: 10,
						italics: true,
						color: 'red',
						margin: [40, 10, 40, 0] //[left, top, right, bottom]
					},
					italics_row: {
						italics: true,
						fontSize: 9
					}

				}
			};

			//********PDF PRINT PREVIEW
			if (print_preview) {


				pdfMake.createPdf(docDefinition).getDataUrl().then((dataUrl) => {
					//set src to dataURL
					u.eid("vendor_po_iframe").src = dataUrl;
				}, err => {
					console.error(err);
				});

				pdfMake.createPdf(docDefinition).getDataUrl();

				//show div holding this
				u.eid("vendor_po_iframe").style.display = "inline-block";

			}
			//*******SAVE TO SERVER & EXECUTE
			else {

				//get index in pq_detail that relates to this request (used to get quoteNumber)
				/*var detail_index = pq_overview.findIndex(request => {
					return request.id == allocations_mo[current_request_index].pq_id;
				});*/

				//if not print preview, save copy to server for email & to save to google drive
				pdfMake.createPdf(docDefinition).getBuffer().then(function(buffer) {

					var blob = new Blob([buffer]);

					var reader = new FileReader();

					// this function is triggered once a call to readAsDataURL returns
					reader.onload = function(event) {
						var fd = new FormData();
						fd.append('fname', 'temp.pdf');
						fd.append('data', event.target.result);
						fd.append('po_number', po_number);
						fd.append('use_po_number', pq_orders[index].po_number);
						fd.append('tell', 'temp_vendor_pdf');

						$.ajax({
							type: 'POST',
							url: 'terminal_orders_helper.php', // Change to PHP filename
							data: fd,
							processData: false,
							contentType: false
						}).done(function(data) {

							// print the output from the upload.php script
							console.log(data);

							//execute close & submit function (send MO) once finished
							if (send)
								submit_vendor_po();

						});
					};

					// trigger the read from the reader...
					reader.readAsDataURL(blob);
				});
			}
		}

		//builds table based on type of table
		function render_pdf_table(type, text = null, index = null) {

			//just send back formatting for single block
			if (type == "single_block") {

				//return format AND body 
				return {
					style: 'single_block',
					table: {
						widths: [55],
						heights: [20],
						headerRows: 1,
						body: render_pdf_body(type, text)
					}
				};
			} else if (type == "bom") {

				//return format AND body 
				return {
					style: 'table_body',
					table: {
						widths: ['auto', 130, '*', 'auto', 'auto', 50, 'auto'],
						headerRows: 1,
						dontBreakRows: true,
						body: render_pdf_body(type, "", index)
					}
				};
			}
		}

		//global for PO sub total
		var order_sub_total = 0;

		//renders PDF table headers and body if necessary
		function render_pdf_body(type, text = null, index) {

			//init body to send back
			var body = [];

			//just send back formatting for single block
			if (type == "single_block") {

				//pass table headers as array
				var headers = createHeaders([
					text
				], 'table_header_margin');

				//add headers to body
				body.push(headers);

			}
			//create BOM table
			else if (type == "bom") {

				//pass table headers as array
				var headers = createHeaders([
					"Seq #",
					"Part #",
					"Description",
					"UoM",
					"Qty",
					"Unit Cost",
					"Total"
				], 'table_header');

				//add headers to body
				body.push(headers);

				//reset seq count
				order_sub_total = 0;

				// filter out all matching parts
				var po_parts = pq_detail.filter(function(part) {
					return part.po_number == pq_orders[index].po_number && part.status == "Pending";
				});

				// create array of unique parts
				var unique_parts = [...new Set(po_parts.map(part => part.part_id))];

				// loop through unique and create new list (compile all parts into list of unique parts)
				for (var i = 0; i < unique_parts.length; i++) {

					// filter parts related to this unique part 
					var check_parts = po_parts.filter(function(part) {
						return part.part_id == unique_parts[i];
					});

					// loop through check parts and update unique part
					for (var j = 0; j < check_parts.length; j++) {

						// if this is the first iteration, set unique = check
						if (j == 0) {
							unique_parts[i] = check_parts[j];
							unique_parts[i].custom_vendor_qty = check_parts[j].vendor_qty;
							unique_parts[i].custom_q_allocated = check_parts[j].q_allocated;
							unique_parts[i].custom_vp_id = check_parts[j].vp_id;
							unique_parts[i].custom_external_po_notes = check_parts[j].external_po_notes;

							//for seperate qty's, use the min between what was allocated and what is entered for vendor
							var use_qty = Math.min(parseInt(check_parts[j].q_allocated), parseInt(check_parts[j].vendor_qty));
							unique_parts[i].custom_seperate_quantities = use_qty.toString();

						}
						// otherwise add info to existing
						else {
							unique_parts[i].custom_vendor_qty = parseInt(unique_parts[i].custom_vendor_qty) + parseInt(check_parts[j].vendor_qty);
							unique_parts[i].custom_q_allocated = parseInt(unique_parts[i].custom_q_allocated) + parseInt(check_parts[j].q_allocated);
							unique_parts[i].custom_vp_id += "|" + check_parts[j].vp_id;
							unique_parts[i].custom_external_po_notes += "|" + check_parts[j].external_po_notes;

							//for seperate qty's, use the min between what was allocated and what is entered for vendor
							var use_qty = Math.min(parseInt(check_parts[j].q_allocated), parseInt(check_parts[j].vendor_qty));
							unique_parts[i].custom_seperate_quantities += "|" + use_qty;
						}
					}
				}

				//loop through pq_detail and grab parts that match current MO
				for (var i = 0; i < unique_parts.length; i++) {
					body.push(render_pdf_body_row(unique_parts[i], (i + 1)));
				}

				//push subtotal lines to header
				body.push(render_total_lines("sub_total"));
				body.push(render_total_lines("total"));

			}

			//return our table
			return body;

		}

		//
		//param 1 = ship to location
		/**@author Alex Borchers
		 * Handles making decision on which shop excess inventory is allocated based on ship to location
		 * 
		 * @param ship_to {string} should match "name" column from general_shippingadd
		 * 
		 * @returns {string} "For Shop: [code]"
		 */
		function get_shop(ship_to) {

			// initialize shipping code
			var shipping_code = "TBD";

			// get index of shipping 
			var index = staging_locations.findIndex(object => {
				return object.name == ship_to;
			});

			// if we find a match & it is not blank, update shipping code
			if (index != -1 && (staging_locations[index].abv != "" && staging_locations[index].abv != null))
				shipping_code = staging_locations[index].abv;

			// return string
			return "For Shop: " + shipping_code;

		}

		//renders pdf body row for BOM items
		//takes item (the actual part we are adding)
		function render_pdf_body_row(item, seq) {

			//if we do not find a match, set UOM and decision to be purchased
			if (item.uom == null)
				item = update_part_with_user_info(item);

			//compare quantity against vendor quantity (if larger, add breakout rows)
			//var custom_pn = item.part_id;
			var custom_description = item.partDescription;
			var custom_qty = item.vendor_qty;
			var custom_total_price = item.vendor_cost * item.vendor_qty;

			// update external notes if null
			if (item.external_po_notes == null)
				item.external_po_notes = "";

			// logic used if PO is for multiple projects
			if (multiple_projects) {

				// update qty to use
				custom_qty = item.custom_vendor_qty;
				custom_total_price = item.vendor_cost * item.custom_vendor_qty;

				// split attributes that may hold info for multiple projects
				console.log(item.custom_vp_id);
				var vp_ids = item.custom_vp_id.split("|");
				var external_po_notes = item.custom_external_po_notes.split("|");
				var seperate_quantities = item.custom_seperate_quantities.split("|");

				// loop through info and build custom_descriptions
				for (var i = 0; i < vp_ids.length; i++) {
					custom_description += "\n";
					custom_description += "For Job #: " + vp_ids[i] + " (" + seperate_quantities[i] + ")";
				}

				//break out job # and shop quantity if not equal to the amount allocated
				if (item.custom_vendor_qty > item.custom_q_allocated && item.custom_q_allocated != null) {
					custom_description += "\n";
					custom_description += get_shop(pq_orders[index_being_rendered].po_ship_to) + " (" + (item.custom_vendor_qty - item.custom_q_allocated) + ")";
				}

				// loop through and add any external notes
				for (var i = 0; i < external_po_notes.length; i++) {
					if (external_po_notes[i] != "" && external_po_notes[i] != null) {
						custom_description += "\n";
						custom_description += "Note: (" + vp_ids[i] + ") " + external_po_notes[i];
					}
				}
			}
			// logic used if part belongs to 1 project
			else {

				//push notes if any are entered
				if (item.external_po_notes != "") {
					custom_description += "\n";
					custom_description += "Note: " + item.external_po_notes;
				}

				//update vendor_qty and q_allocated
				item.vendor_qty = parseInt(item.vendor_qty);
				item.q_allocated = parseInt(item.q_allocated);

				//break out job # and shop quantity if not equal to the amount allocated
				if (item.vendor_qty > item.q_allocated && item.q_allocated != null) {

					//update custom
					custom_description += "\n";
					custom_description += "For Job #: " + item.vp_id + " (" + item.q_allocated + ")";
					custom_description += "\n";
					custom_description += get_shop(pq_orders[index_being_rendered].po_ship_to) + " (" + (item.vendor_qty - item.q_allocated) + ")";
				}
			}

			//now that we have done our checks, add to pdf table
			var dataRow = [];
			dataRow.push({
				text: seq,
				alignment: 'center'
			});
			dataRow.push({
				text: item.part_id,
				alignment: 'left'
			});
			dataRow.push({
				text: custom_description,
				alignment: 'left'
			});
			dataRow.push({
				text: item.uom,
				alignment: 'center'
			});
			dataRow.push({
				text: custom_qty,
				alignment: 'center'
			});
			dataRow.push({
				text: accounting.formatMoney(item.vendor_cost),
				alignment: 'right'
			});
			dataRow.push({
				text: accounting.formatMoney(custom_total_price),
				alignment: 'right'
			});

			//increment seq count
			order_sub_total += custom_total_price;

			//push row to table
			return dataRow;

		}

		//renders pdf total rows for vendor PO
		//param 1 = type of total line needed (sub_total or total)		
		function render_total_lines(type) {

			//grab tax & freight
			var tax = parseFloat(u.eid("taxes").value) || 0,
				freight = parseFloat(u.eid("freight").value) || 0,
				other_text = u.eid("additional_expense").value,
				other = parseFloat(u.eid("additional_expense_cost").value) || 0;

			//init sub-total strings
			var sub_total_headers = "Sub Total:\nApplicable Tax:\nFreight:";
			var sub_total_price = accounting.formatMoney(order_sub_total) + "\n" + accounting.formatMoney(tax) + "\n" + accounting.formatMoney(freight);

			//if user has other expenses entered, add at this stage
			if (other_text != "" && other != 0) {
				sub_total_headers += "\n" + other_text + ":";
				sub_total_price += "\n" + accounting.formatMoney(other);
			}

			//init datarow to be returned
			var dataRow = [];

			//push blank rows (no border)
			dataRow.push({
				text: '',
				colSpan: 4,
				border: [false, false, false, false]
			});
			dataRow.push({});
			dataRow.push({});
			dataRow.push({});

			//create sub total, tax, and freight rows
			if (type == "sub_total") {

				dataRow.push({
					text: sub_total_headers,
					colSpan: 2,
					alignment: 'right',
					style: 'sub_total_style'
				});
				dataRow.push({});
				dataRow.push({
					text: sub_total_price,
					alignment: 'right',
					style: 'sub_total_style'
				});
			} else if (type == "total") {
				dataRow.push({
					text: "Total:",
					colSpan: 2,
					alignment: 'right',
					style: 'total_style'
				});
				dataRow.push({});
				dataRow.push({
					text: accounting.formatMoney(order_sub_total + tax + freight + other),
					alignment: 'center',
					style: 'total_style'
				});
			}

			//update global for freight, tax & total_price
			pq_orders[index_being_rendered].total_price = order_sub_total + tax + freight;

			//push to return object
			return dataRow;

		}

		//create table headers
		function createHeaders(keys, style) {
			var result = [];
			for (var i = 0; i < keys.length; i += 1) {
				result.push({
					text: keys[i],
					style: style
					//prompt: keys[i],
					//width: size[i],
					//align: "center",
					//padding: 0
				});
			}
			return result;
		}

		//global that holds purchase order #'s to be rendered & submitted
		var purchase_orders = [],
			use_orders = [];

		//handles submitting PO to customer
		function submit_vendor_po() {

			//seting updating to false (so the mouse will spin when running)
			updating = false;

			//init form data
			var fd = new FormData();

			//serialize arrays and pass them to fd 
			fd.append('purchase_orders', JSON.stringify(use_orders));
			fd.append('pq_orders', JSON.stringify(pq_orders));

			//add variables needed for body & subject
			//fd.append('project_number', [allocations_mo][current_request_index].project_id);
			//fd.append('pq_id', allocations_mo[current_request_index].pq_id);
			//fd.append('allocation_id', allocations_mo[current_request_index].id);

			//pass first note to attach to purchase orders
			//fd.append('first_note', u.eid("notes").value);

			//add user info
			fd.append('user_info', JSON.stringify(user_info));

			//add tell variable & type variable
			fd.append('tell', 'send_vendor_pos');
			fd.append('type', process_type); //type = (all, current, or none)

			$.ajax({
				url: 'terminal_orders_helper.php',
				type: 'POST',
				processData: false,
				contentType: false,
				data: fd,
				success: function(response) {

					//check for error
					if (response != "") {
						console.log(response);
						alert(response);
						return;
					}

					//save changes & refresh page
					alert("Purchase Order(s) have been sent.");
					window.location.reload();

				}
			});
		}

		//initialize data source for autocomplete menu
		var data = [];
		var cp = "";

		//renders autocomplete settings when a new row has been added.
		//param 1 = part number
		function render_autocomplete(part) {

			//set current part id, init data related to specific id
			cp = part.id;
			data[cp] = [];

			//re-order based on part # & populate list
			//var potential_vendors = [];

			//filter previous orders on part
			var use_vendors = previous_orders.filter(function(vend) {
				return vend.part_id.toLowerCase() == part.part_id.toLowerCase();
			});

			//filter previous orders and add to data[] object
			for (var i = 0; i < use_vendors.length; i++) {
				data[cp].push({
					label: use_vendors[i].vendor,
					quantity: use_vendors[i].vendor_qty
				});
			}

			//re-order data list to show highest quantities at the top of list
			data[cp] = data[cp].sort((a, b) => b.quantity - a.quantity);

			/*for (var i = 0; i < previous_orders.length; i++){
				
				//check for index in current data object
				var index = data[cp].findIndex(object => {
					return object.label == previous_orders[i].vendor;
				});

				//if first time we've seen this, push to data list
				if (previous_orders[i].part_id == part.part_id && index < 0 && previous_orders[i].vendor != ""){
				
					data[cp].push({
						label: previous_orders[i].vendor, 
						quantity : parseInt(previous_orders[i].vendor_qty)
					});
				}
				//if we have seen it previously, add to total quantity
				else if (previous_orders[i].part_id == part.part_id && index > -1){
					data[cp][index].quantity += parseInt(previous_orders[i].vendor_qty);
				}
				//otherwise push to list of potential vendors
				else if (index < 0){
					potential_vendors.push(previous_orders[i].vendor);
				}
			}*/



			//initialize temporary object to hold remaining vendors
			/*var remaining_vendors = [];

			//loop back through potential vendors and add to list (if not already on list)
			for (var i = 0; i < potential_vendors.length; i++){

				//check for index in current data object & remaining vendors
				var index1 = data[cp].findIndex(object => {
					return object.label == potential_vendors[i];
				});

				var index2 = remaining_vendors.findIndex(object => {
					return object.label == potential_vendors[i];
				});

				//if we do not have it yet, push to end of list
				if (index1 < 0 && index2 < 0)
					remaining_vendors.push({ label: potential_vendors[i], quantity: 0 });
					//data[cp].push({ label: potential_vendors[i], quantity: 0 });
			}*/

			//sort remaining vendors
			//remaining_vendors = remaining_vendors.sort((a, b) => a.label.localeCompare(b.label));

			//loop and push remaining to data object
			//for (var i = 0; i < remaining_vendors.length; i++){
			//	data[cp].push(remaining_vendors[i]);
			//}

			//filter for vendors not on list
			var extra_vendors = vendors.filter(function(vend1) {
				return data[cp].some(function(vend2) {
					return vend1.vendor != vend2.label;
				});
			});

			//if data[cp] length is 0, use all vendors
			if (data[cp].length == 0)
				extra_vendors = vendors;

			//loop through vendors, push to list if not already on list
			for (var i = 0; i < extra_vendors.length; i++) {
				data[cp].push({
					label: extra_vendors[i].vendor,
					quantity: 0
				});
			}

			//renders autcomplete menu for all inputs with parts as class
			$("#vendor" + part.id).autocomplete({
					minLength: 0,
					source: data[cp],
					focus: function(event, ui) {
						//$( "#vendor" + part.id ).val( ui.item.label );
						return false;
					},
					select: function(event, ui) {
						$("#vendor" + part.id).val(ui.item.label);
						refresh_target();
						return false;
					}
				})
				.autocomplete("instance")._renderItem = function(ul, item) {

					//style different depending on if we have ordered from this vendor in the past
					if (item.quantity == 0) {
						return $("<li>")
							.append("<a>" + item.label + "</a>")
							.appendTo(ul);
					} else {
						return $("<li>")
							.append("<b><a>" + item.label + "(" + item.quantity + ")</a></b>")
							.appendTo(ul);
					}
					return $("<li>")
				};
		}

		//handles opening dialog for new shipments
		$(document).on('click', '.shipment_button', function() {

			//add parts to shipment
			get_new_shipment_parts(this);

			// default check_all_checkbox to checked
			u.eid("check_all_checkbox").checked = true;

			//clear out all shipment fields
			document.querySelectorAll('.ns_field').forEach(function(a) {
				a.value = "";
			})

			//uncheck shipped field
			u.eid("ns_shipped").checked = false;

			//get screen height
			var screenheight = $(window).height();
			//var screenwidth = $(window).width();

			//open dialog box
			$("#new_shipment_dialog").dialog({
				width: "auto",
				height: screenheight - 100,
				dialogClass: "fixedDialog",
			});

		});

		//global to keep track of current shipment and current row
		var shipment_po, shipment_row;

		//handles getting list of parts to add to a new shipment
		function get_new_shipment_parts(create_new_ship_button) {

			//get po_number
			var td = create_new_ship_button.parentNode;
			var tr = td.parentNode;
			var prevRow = $(tr).prev()[0];
			var po_number = prevRow.childNodes[1].childNodes[0].value;

			//update globals
			shipment_po = po_number;
			shipment_row = tr.rowIndex - 1;

			//now, get parts from pq_orders that match this PO number and do not have a shipment_id
			var potential_parts = pq_detail.filter(function(part) {
				return part.po_number == po_number && (part.shipment_id == "" || part.shipment_id == null);
			});

			//remove previously entered shipment rows
			document.querySelectorAll('.pot_shipment_row').forEach(function(a) {
				a.remove();
			})

			//get table to add to 
			var table = u.eid("shipment_parts_table")

			//loop through parts & add to table
			for (var i = 0; i < potential_parts.length; i++) {
				//new row
				var row = table.insertRow(-1);
				row.classList.add("pot_shipment_row");

				//cell 0 (checkbox)
				var cell = row.insertCell(0);
				var input = document.createElement("input");
				input.type = "checkbox";
				input.checked = true; //default to true
				input.classList.add("shipment_checkbox");
				input.id = "ship_" + potential_parts[i].id;
				cell.appendChild(input);
				cell.style.textAlign = "center";

				//cell 1 (part number)
				var cell = row.insertCell(1);
				cell.innerHTML = potential_parts[i].part_id;

				//cell 2 (quantity on new shipment) (user enter field, default to total remaining)
				var cell = row.insertCell(2);
				var input = document.createElement("input");
				input.type = "number";
				input.value = potential_parts[i].vendor_qty;
				input.max = parseInt(potential_parts[i].vendor_qty);
				input.min = 0;
				input.classList.add("shipping_qty");

				//restrict to max on manual input as well
				input.addEventListener("change", function() {

					console.log(this.value);
					console.log(this.max);

					if (parseInt(this.value) > parseInt(this.max))
						this.value = this.max;
					else if (parseInt(this.value) < 0)
						this.value = 0;
				});

				input.id = "ship_qty_" + potential_parts[i].id;
				cell.appendChild(input);

				//cell 3 (total quantity on order)
				var cell = row.insertCell(3);
				var total_quantity = get_total_quantity(potential_parts[i]);
				cell.innerHTML = total_quantity;
				cell.style.textAlign = "center";

				//cell 4 (total quantity on existing shipments - already moved to an order)
				var cell = row.insertCell(4);
				cell.innerHTML = total_quantity - parseInt(potential_parts[i].vendor_qty);
				cell.style.textAlign = "center";
			}
		}

		//handles getting total quantity for a given part_id on a request
		//param id in pq_detail array
		function get_total_quantity(target_part) {

			//we need the part # and the PO number to get the total quantity
			var part_num = target_part.part_id;
			var po_number = target_part.po_number;

			//filter pq_detail based on these two things
			var specific_parts = pq_detail.filter(function(part) {
				return part.po_number == po_number && part.part_id == part_num;
			});

			//filter through parts and sum up total vendor_qty
			var total = 0;
			for (var i = 0; i < specific_parts.length; i++) {
				total += parseInt(specific_parts[i].vendor_qty);
			}

			return total;
		}

		//used to get most recent note from list
		function get_recent_note(ref_num, type) {

			//based on type, ref_num will either reference a po number or shipment number
			if (type == "po") {

				//filter out most recent note by po number
				var index = po_notes.findIndex(function(note) {
					return note.po_number == ref_num;
				});

			} else {

				//filter out most recent note by po number
				var index = po_notes.findIndex(function(note) {
					return note.shipment_id == ref_num;
				});

			}

			//return blank if nothing found
			if (index == -1)
				return "";

			//return first 10 characters
			return po_notes[index].notes.substr(0, 20);

		}

		//holds note element clicked on so we know what needs to be updated
		var notes_input_clicked;

		//add event listener to all notes fields
		$(document).on('click', '.notes', function() {

			//update what was clicked
			notes_input_clicked = this;

			//init po_num and shipment_id as empty string
			var po_num = "",
				shipment_id = "";

			//check if classlist includes 'refresh_shipping' = means this is coming from shipment row
			if (this.classList.contains('refresh_shipping')) {

				//get shipment ID and po_num
				var td = this.parentNode;
				var tr = td.parentNode;
				var shipment_td = tr.childNodes[2];
				shipment_id = shipment_td.childNodes[0].value;

				//get po_number as well
				var order_table_tr = tr.parentNode.parentNode.parentNode.parentNode; //shipment[tbody].[table].orders[td].[tr]
				var table_type = order_table_tr.classList[1].substr(0, order_table_tr.classList[1].indexOf("_"));
				var table = u.eid(table_type + "_orders_table").getElementsByTagName("tr");
				var po_tr = table[order_table_tr.rowIndex - 1];
				po_num = po_tr.childNodes[1].childNodes[0].value;

			} else {

				//get po_num
				var td = this.parentNode;
				var tr = td.parentNode;
				var po_cell = tr.childNodes[1];
				po_num = po_cell.childNodes[0].value;

			}

			init_notes_dialog(po_num, shipment_id);
			u.eid("notes_po_number").value = po_num;
			u.eid("notes_shipment_id").value = shipment_id;

			$("#notes_dialog").dialog({
				width: "auto",
				height: "auto",
				dialogClass: "fixedDialog",
			});

		});

		//handles creating notes dialog
		function init_notes_dialog(po_num, shipment_id) {

			//remove previous notes
			document.querySelectorAll('.notes_row').forEach(function(a) {
				a.remove()
			})

			//filter out matching notes
			var notes = po_notes.filter(function(note) {
				return note.po_number == po_num &&
					(shipment_id == "" || note.shipment_id == shipment_id);
			});

			//loop through notes and add any that match quote #
			for (var i = 0; i < notes.length; i++) {
				add_notes_row(notes[i])
			}
		}

		//handles adding new note row
		function add_notes_row(note) {

			//grab table
			var table = u.eid("notes_table");

			//insert new row and add classname to it
			var row = table.insertRow(-1);
			row.classList.add("notes_row");
			row.id = 'notes_row_' + note.id;

			//edit cell
			var cell = row.insertCell(0);
			cell.classList.add('edit_notes');
			cell.id = 'e_n_' + note.id;
			cell.style.color = 'green';
			cell.innerHTML = "✎   ";

			//po_number
			var cell = row.insertCell(1);
			cell.innerHTML = note.po_number;

			//shipment ID
			var cell = row.insertCell(2);
			cell.innerHTML = note.shipment_id;

			//notes
			var cell = row.insertCell(3);
			cell.classList.add('notes_cell')
			cell.id = 'notes_' + note.id;

			//if null, use textarea
			if (note == null)
				cell.innerHTML = u.eid('new_notes').value;
			else
				cell.innerHTML = note.notes;

			//user
			var cell = row.insertCell(4);
			cell.classList.add('name_cell');
			cell.innerHTML = note.name;

			//date
			var cell = row.insertCell(5);
			cell.classList.add('date_cell');
			//let today = new Date().toLocaleDateString();
			cell.innerHTML = utc_to_local(note.date);

			//delete cell
			var cell = row.insertCell(6);
			cell.classList.add('delete_notes');
			cell.id = 'd_n_' + note.id;
			cell.style.color = 'red';
			cell.innerHTML = "&#10006";

			//set text value to blank
			u.eid("new_notes").value = "";

		}

		//handles submitting edit to note
		$(document).on('click', '.delete_notes', function() {

			//grab id from 
			var id = this.id.substring(4);

			var message = "Are you sure you would like to remove this note? (This cannot be undone)";

			//send message to user
			if (confirm(message)) {
				//if yes, send request to server and remove row from table
				z.update_notes(2, id);
				z.remove_notes_row(id);
			}

		});

		//handles editing notes - prompts user with dialog to do so
		$(document).on('click', 'td.edit_notes', function() {

			//grab id from 
			var id = this.id.substring(4);

			//grab edited text
			var edit_text = u.eid("notes_" + id).innerHTML;

			//if user tries to hit edit twice, do nothing
			if (id == curr_note)
				return

			//check to see if variable is currently being edited
			if (curr_note != null) {
				//undo previous edit
				z.undo_note();
			}

			//save current id
			curr_note = id;
			curr_fullNote = edit_text;

			//set text area to desired text
			u.eid("notes_" + id).innerHTML = "<textarea id = 'edit_note' class = 'enter_clar' style = 'resize: vertical; height: 2em'>" + edit_text + "</textarea><button class = 'edit_notes_button'>Update</button><button onclick = 'z.undo_note()'>Undo</button>";

		});

		//handles submitting edit to note
		$(document).on('click', '.edit_notes_button', function() {

			//grab new text
			var edit_text = u.eid("edit_note").value;

			//set clar to new text
			u.eid("notes_" + curr_note).innerHTML = edit_text;

			//update on server
			z.update_notes(1, curr_note);

			//reset current id
			curr_note = null;
			curr_fullNote = null;


		});

		//global that holds current note
		var curr_note;
		var curr_fullNote;

		//handles undoing note
		z.undo_note = function() {
			//set clar to new text
			u.eid("notes_" + curr_note).innerHTML = curr_fullNote;

			//reset current idu
			curr_note = null;
			curr_fullNote = null;
		}

		//handles removing table row
		z.remove_notes_row = function(row) {
			var target_row = u.eid("notes_row_" + row);
			target_row.parentNode.removeChild(target_row);
		}

		//handles adding a new note
		//type 0 = add
		//type 1 = edit
		//type 2 = delete
		z.update_notes = function(type, id = -1) {

			//Initialize the note based on the type
			var note;

			//add
			if (type == 0) {
				note = u.eid('new_notes').value.trim();
			}
			//edit
			else if (type == 1) {
				note = u.eid("notes_" + id).innerHTML.trim();
			}
			//delete
			else if (type == 2) {
				note = u.eid("notes_" + id).innerHTML;
			}

			//ajax request to communicate with database
			$.ajax({
				type: "POST", //type of method
				url: "terminal_orders_helper.php", //your page
				data: {
					po_number: u.eid("notes_po_number").value,
					shipment_id: u.eid("notes_shipment_id").value,
					note: note,
					type: type,
					id: id,
					tell: 'notes'
				}, // passing the values
				success: function(response) {

					//first 5 letters will be error if it did not run successfully, else it will be the id we will use for our table
					var error_check = response.substring(0, 5);

					if (error_check == "Error") {
						alert(response);
					} else {
						//update global, refresh table, refresh div
						po_notes = $.parseJSON(response);
						init_notes_dialog(u.eid("notes_po_number").value, u.eid("notes_shipment_id").value);
						notes_input_clicked.value = note; //whatever was last clicked, update with new value

					}
				}
			});
		}

		/**
		 * Handles mass updating all parts related to a shipment (unchecking or checking)
		 * @author Alex Borchers
		 * @param {HTMLElement} targ (use targ.checked to see what state all elements should be in)
		 * @return void
		 */
		function update_add_to_shipment(targ) {

			// loop through class of checkboxes and set to same value as targ
			document.querySelectorAll('.shipment_checkbox').forEach(function(a) {
				a.checked = targ.checked;
			});

		}

		//handles creating a new shipment from selected parts
		function create_new_shipment() {

			//check to make sure all required fields are filled in
			/*var error = check_submit(u.class("ns_required"));

			if (error){
				alert("Please fill in the required fields (highlighted in yellow).");
				return;
			}*/

			//init array to be sent to server
			var shipment_parts = [];

			//look through checkboxes, add any checked parts
			document.querySelectorAll('.shipment_checkbox').forEach(function(ship_check) {

				//if true, push ID to array (exclude first 5 "ship_")
				//id's are structured like "ship_[pq_detail_id]" (see get_new_shipment_parts)
				//quantity inputs are structure "ship_qty_[pq_detail_id]" (see get_new_shipment_parts)
				if (ship_check.checked) {
					shipment_parts.push({
						id: ship_check.id.substr(5),
						quantity: u.eid("ship_qty_" + ship_check.id.substr(5)).value
					});
				}
			});

			//check to make sure we have at least 1 part checked
			if (shipment_parts.length == 0) {
				alert("At least 1 part is required for a new shipment.");
				return;
			}

			//transfer to form and send trough ajax
			//init form data
			var fd = new FormData();

			//serialize arrays and pass them to fd 
			fd.append('shipment_part_ids', JSON.stringify(shipment_parts));
			fd.append('pq_detail', JSON.stringify(pq_detail));
			fd.append('pq_overview', JSON.stringify(pq_overview));

			//loop and add required variables to the form
			document.querySelectorAll('.ns_field').forEach(function(field) {
				//in the form (id, value) EXAMPLE for "Tracking" = (ns_tracking, "123ABC")
				//treat checkboxes differently
				if (field.type == "checkbox")
					fd.append(field.id, field.checked);
				else
					fd.append(field.id, field.value);
			});

			//add tell variable
			fd.append('tell', 'create_new_shipment');
			fd.append('user_info', JSON.stringify(user_info));

			$.ajax({
				url: 'terminal_orders_helper.php',
				type: 'POST',
				processData: false,
				contentType: false,
				data: fd,
				success: function(response) {

					//check for error response
					var check = response.substr(0, 5);
					if (check == "Error") {
						alert(response);
						console.log(response);
						return;
					}

					//update globals and refresh purchase order report with new shipment
					var result = $.parseJSON(response);
					pq_detail = result[0];
					pq_shipments = result[1];
					po_notes = result[2];

					//remove any prior detail
					document.querySelectorAll('.orders_detail').forEach(function(a) {
						a.remove();
					})

					//refresh report
					//refresh_orders_report();

					//refresh shipments for this purchase order
					document.querySelectorAll('.show_hide_button').forEach(function(a) {
						if (a.innerHTML == "-") {
							//update +/- and click again
							a.innerHTML = "+";
							a.click();
						}
					})

					//find PO # using shipment_parts ID#, and update expected date(s) row on parent table
					//first look up PO # for this shipment
					var index = pq_detail.findIndex(object => {
						return object.id == shipment_parts[0].id;
					});

					//if we find index, do this the quick way, otherwise re-filter jobs
					if (index != -1) {

						//get current row using po #
						var currRow = u.eid("orders_detail" + pq_detail[index].po_number);
						var prevRow = $(currRow).prev()[0];

						//update expected Date(s) with new information
						prevRow.childNodes[13].innerHTML = get_expected_shipment_dates(pq_detail[index].po_number);

					} else {
						refresh_orders_report();
					}

					//save changes & refresh page
					alert("The new shipment has been successfully created.");

					//close dialog
					$("#new_shipment_dialog").dialog('close');
				}
			});
		}

		//handles showing parts related to a shipment on click
		$(document).on('click', '.expand_shipment', function() {

			//work to shipment_id
			var td = this.parentNode;
			var tr = td.parentNode;
			var shipment_id = "";

			//check [1] index (used for creating new shipments)
			//if it is a checkbox, we know that we need to use [2]
			if (tr.childNodes[1].childNodes[0].type == "checkbox")
				shipment_id = tr.childNodes[2].childNodes[0].value;
			else
				shipment_id = tr.childNodes[1].childNodes[0].value;

			//get po_number as well
			var order_table_tr = tr.parentNode.parentNode.parentNode.parentNode; //shipment[tbody].[table].orders[td].[tr]
			var prevRow = $(order_table_tr).prev()[0];
			var po_num = prevRow.childNodes[1].childNodes[0].value;

			//if "No shipment assigned" update shipment_id to po_number
			if (shipment_id == "No shipments assigned")
				shipment_id = "no_shipments_" + po_num;

			//filter and add parts below shipment ID row
			//grab show/hide symbol
			var show_hide = this.innerHTML;

			//based on innerHTML, show/hide content
			if (show_hide == "+") {
				//show shipment info and change button +/-
				get_order_parts(shipment_id, tr.rowIndex, po_num);
				this.innerHTML = "-";
			} else {
				//remove previous shipment info & change button +/-
				u.eid("shipment_" + shipment_id).remove();
				this.innerHTML = "+";
			}
		});

		//handles removing a shipment line related to a purchase order
		$(document).on('click', '.delete_shipment', function() {

			//work to shipment_id
			var tr = this.parentNode;
			var shipment_id = tr.childNodes[2].childNodes[0].value;

			//ask user if they are sure
			var message = "Are you sure you would like to delete this shipment?";

			//if confirmed, send to function to process deletion
			if (confirm(message))
				delete_shipment('full', shipment_id);

		});

		//handles removing a shipment item from a shipment id
		$(document).on('click', '.delete_shipment_item', function() {

			//work to shipment_id
			var tr = this.parentNode;
			var table = tr.parentNode;
			var outer_td = table.parentNode;
			var outer_tr = outer_td.parentNode;
			var target_tr = outer_tr.parentNode;
			var prevRow = $(target_tr).prev()[0];
			var shipment_id = prevRow.childNodes[2].childNodes[0].value;

			//also grab part #
			var part = tr.childNodes[0].innerHTML;

			//ask user if they are sure
			var message = "Are you sure you would like to delete this shipment?";

			//if confirmed, send to function to process deletion
			if (confirm(message))
				delete_shipment(part, shipment_id);
		});

		//function to handle removing shipments / shipment info
		//param 1 = type ('full' OR partNumber (partial))
		//param 2 = shipment_id (matches a shipment in fst_pq_orders_shipments)
		function delete_shipment(type, shipment_id) {

			//pass info to server to process
			var fd = new FormData();

			//serialize arrays and pass them to fd 
			fd.append('type', type);
			fd.append('shipment_id', shipment_id);

			//add tell variable
			fd.append('tell', 'delete_shipment');

			$.ajax({
				url: 'terminal_orders_helper.php',
				type: 'POST',
				processData: false,
				contentType: false,
				data: fd,
				success: function(response) {

					//check for error response
					var check = response.substr(0, 5);
					if (check == "Error") {
						alert(response);
						console.log(response);
						return;
					}

					//update globals and refresh purchase order report with new shipment
					var result = $.parseJSON(response);
					pq_detail = result[0];
					pq_shipments = result[1];
					po_notes = result[2];

					//refresh shipments for this purchase order
					document.querySelectorAll('.show_hide_button').forEach(function(a) {
						//click twice to refresh list
						if (a.innerHTML == "-") {
							a.click();
							a.click();
						}
					})

					//save changes & refresh page
					if (type == "full")
						alert("This shipment has been removed.");
					else
						alert("This part has been removed from the shipment.");
				}
			});
		}

		//handles updating total cost when changing vendor qty or cost
		$(document).on('change', '.vendor_qty, .vendor_cost', function() {

			//init variables to be used
			var qty, cost;

			//get row/column index
			var td = this.parentNode;
			var tr = td.parentNode;
			var row_index = tr.rowIndex;
			var cell_index = td.cellIndex;

			//depending on class, get qty or cost (whatever was not changed) multiply and add to total line
			if (this.classList[0] == "vendor_qty") {
				qty = this.value;
				cost = tr.childNodes[cell_index + 1].childNodes[0].value;
				tr.childNodes[cell_index + 2].childNodes[0].value = accounting.formatMoney(qty * cost);
			} else {
				qty = tr.childNodes[cell_index - 1].childNodes[0].value;
				cost = this.value;
				tr.childNodes[cell_index + 1].childNodes[0].value = accounting.formatMoney(qty * cost);
			}
		});

		//add event listener to all open order inputs (so we know to update globals)
		$(document).on('change', '.refresh_open_order', function() {

			//get po_number
			var td = this.parentNode;
			var tr = td.parentNode;
			var po_td = tr.childNodes[1];
			var po_number = po_td.childNodes[0].value;

			refresh_open_order_globals(po_number, tr);

			//if priority, re-order
			//if (this.classList.contains("priority"))
			//	get_open_orders()
		});

		//add event listener to all shipment inputs (so we know to update globals)
		$(document).on('change', '.refresh_shipping', function() {

			//get po_number
			var td = this.parentNode;
			var tr = td.parentNode;
			var ship_td = tr.childNodes[2];
			var ship_id = ship_td.childNodes[0].value;

			refresh_shipment_globals(ship_id, tr);

			//if we clicked in est. ship date, we need to update est. dates(s) row on row above
			if (this.classList.contains("ship_date")) {

				//work back to previous row
				//first look up PO # for this shipment
				var index = pq_shipments.findIndex(object => {
					return object.shipment_id == ship_id;
				});

				//get current row using po #
				var currRow = u.eid("orders_detail" + pq_shipments[index].po_number);
				var prevRow = $(currRow).prev()[0];

				//update expected Date(s) with new information
				prevRow.childNodes[13].innerHTML = get_expected_shipment_dates(pq_shipments[index].po_number);
			}
		});

		//handles click of class 'vendor_total'
		//prompts user to add total cost, then broken back into unit cost
		$(document).on('click', '.vendor_total', function() {

			//init total price
			var total_price = "";

			while (total_price === "") {

				//ask user to enter price
				total_price = prompt("Please enter total price", "0");

				//if user hits cancel, exit dialog
				if (total_price == null)
					return;

				//convert to float
				total_price = parseFloat(total_price);

				//check to make sure user entered a #
				if (isNaN(total_price)) {
					total_price = "";
					alert("Error, please enter a valid number.");
				}
			}

			//get quantity & unit cost input
			var td = this.parentNode;
			var tr = td.parentNode;
			var qty = tr.childNodes[10].childNodes[0].value;
			var unit_cost_input = tr.childNodes[11].childNodes[0];

			//calculate unit price from total
			var unit_price = total_price / parseInt(qty);

			//update total and unit cost
			this.value = accounting.formatMoney(total_price);
			unit_cost_input.value = unit_price.toFixed(4);

			//refresh vendor info
			refresh_target();
		});

		//on ajax call, turn mouse waiting ON
		$(document).ajaxStart(function() {
			if (!updating)
				waiting('on');

			//if sending to customer, start mouse spinning
			if (!print_preview)
				waiting('on');
		});

		//on ajax stop, turn mouse waiting OFF
		$(document).ajaxStop(function() {
			if (!updating)
				waiting('off');
		});

		//windows onload
		window.onload = function() {

			//add event listener logic to notify the user before they exit the site if they have potential unsaved data
			window.addEventListener("beforeunload", function(e) {

				//check target array's that identify if user has made any changes
				if (target_ids.length + target_order_numbers.length + target_po_numbers.length + target_shipment_ids.length + check_po_assignments.length == 0) {
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

			//add event listener to all vendor inputs (so we know to update globals)
			$('.refresh_vendor').on("change", function() {
				refresh_vendor_globals();
			});

			// Get the element with id="defaultOpen" and click on it
			u.eid("defaultOpen").click();

			//init queue
			refresh_queue();

		}

		//set interval to check orders every 60 seconds
		var timer = 60000; // every 1,000 is 1 second
		var myInterval = setInterval(update_queue, timer);

		//set interval to check for updates (every 1 sec - disabled after save)
		//var check_edit_interval = setInterval(check_edit, 2000);
	</script>

</body>

<?php

//reset return address once the page has loaded
unset($_SESSION['returnAddress']);

//close SQL connection
$mysqli->close();

?>

</html>