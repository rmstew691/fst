<?php

// Load dependencies (and session)
session_start();
include('phpFunctions.php');
include('phpFunctions_html.php');
include('constants.php');

// Load the database configuration file
require_once 'config.php';

//grab basic info about the quote
$shop = $_GET["shop"];

//used to grab actual link for the current address
$actual_link = "https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
$sub_link = substr($actual_link, 0, strpos($actual_link, "home"));

//Save current site so we can return after log in
$_SESSION['returnAddress'] = $actual_link;

//Make sure user has privileges
$query = "SELECT * FROM fst_users where email = '" . $_SESSION['email'] . "'";
$result = $mysqli->query($query);

if ($result->num_rows > 0) {
	$fstUser = mysqli_fetch_array($result);
} else {
	$fstUser['accessLevel'] = "None";
}

sessionCheck($fstUser['accessLevel']);

//if admin, display admin button
$admin = "none";

if ($fstUser['accessLevel'] == "Admin") {
	$admin = "";
}

//reset error message
$_SESSION['errorMessage'] = "";

//initialize allocations_mo object to be passes to js
$allocations_mo = [];

//load in any material orders open for this shop
$query = "select status, project_id, staged_loc from fst_allocations_mo where ship_from = '" . $shop . "' order by closed asc, date_created asc;";
$result = mysqli_query($con, $query);

//cycle thorugh query and assign to different arrays
//add entry for each mo 
while ($rows = mysqli_fetch_assoc($result)) {

	//push info to allocations_mo
	array_push($allocations_mo, $rows);
}

//init arrays
$pq_detail = [];
//grabs detail (actual parts requested)
$query = "select a.quoteNumber, a.project_id as vp_number, b.* from fst_pq_overview a
			LEFT JOIN fst_pq_detail b 
				ON a.id = b.project_id
			WHERE b.status IN('Shipped', 'Staged', 'In-Transit') OR b.decision like '" . $shop . "%';";
$result =  mysqli_query($con, $query);

while ($rows = mysqli_fetch_assoc($result)) {

	//push temp array to project array
	array_push($pq_detail, $rows);
}

//get PW shipping addresses
$pw_shipping = [];
$shop_ship_to = [];	//used to determine which shipping addresses are used to this location

$query = "select * from general_shippingadd WHERE customer = 'PW';";
$result =  mysqli_query($con, $query);

while ($rows = mysqli_fetch_assoc($result)) {
	array_push($pw_shipping, $rows);

	//check if used for this location
	if ($rows['abv'] == $shop)
		array_push($shop_ship_to, $rows['name']);
}

//get fst_pq_orders_shipments (related to shop)
$pq_shipments = [];

// query used until back log is complete
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
$result =  mysqli_query($con, $query);
while ($rows = mysqli_fetch_assoc($result)) {

	// if project_id is null, overwrite to empty string
	if (is_null($rows['project_id']))
		$rows['project_id'] = "";

	//add type & push to array
	$rows['type'] = 'PO';
	$rows['mo_id'] = '';
	array_push($pq_shipments, $rows);
}

// query used until back log is complete
$query = "SELECT * 
			FROM fst_allocations_mo 
			WHERE mo_id <> 'PO' AND status = 'Closed';";
$result =  mysqli_query($con, $query);

while ($rows = mysqli_fetch_assoc($result)) {

	//create array object to match format of fst_pq_orders_shipments
	array_push($pq_shipments, [
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

//grab physical locations stored for each part/shop & quantities
$physical_locations = [];
$query = "SELECT shop, partNumber, location, quantity, prime FROM invreport_physical_locations ORDER BY partNumber, prime DESC;";
$result =  mysqli_query($con, $query);

while ($rows = mysqli_fetch_assoc($result)) {
	array_push($physical_locations, $rows);
}

// grab info about staging areas
$staging_areas = [];
$query = "SELECT * FROM inv_staging_areas WHERE shop = '" . $shop . "';";
$result = mysqli_query($con, $query);	//in constants.php
while ($rows = mysqli_fetch_assoc($result)) {
	array_push($staging_areas, $rows['location_name']);
}

?>

<!doctype html>
<html>

<head>
	<meta charset="utf-8">
	<meta name="google-signin-client_id" content="573761357198-hin7ae7q19qgvoab7t0781b41530546g.apps.googleusercontent.com">
	<link rel="shortcut icon" type="image/x-icon" href="images/P-Logo.png" />
	<link href="stylesheets/jquery-ui-themes-1.13.0/themes/ui-lightness/jquery-ui.css" rel="stylesheet">
	<title>Warehouse Receiving (v<?= $version ?>) - Pierson Wireless</title>
	<link rel="stylesheet" href="stylesheets/element-styles.css?<?= $version; ?>" />
	<link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css'>

	<style>
		/**style span holding job # */
		.job_span {
			margin-left: 2.4em;
			margin-top: 2em;
			float: left;
			font-weight: bold;
			font-style: italic;
		}

		/**used to style submit receiving button in receiving module */
		.submit_receiving_button {
			margin-left: 2.1em;
			margin-bottom: 2em;
		}

		/**used to style table made availabe in collapsable shipment info on receiving tab */
		.shipping_parts_table {
			margin: 4.6em 2em;
		}

		.shipping_parts_table td {
			padding: 7px;
		}

		.shipping_parts_header td {
			font-weight: bold;
			border: 0px solid #000000;
			text-align: center;
		}

		/**force header rows on inventory sheet to be sticky */
		.sticky-header-wh1-receiving {
			position: sticky;
			top: 46px;
			z-index: 100;
			background: white;
		}

		/**adjust width of staging area drop-down */
		.staging_area {
			width: 100%;
		}

		/* Style the tab content (and add height:100% for full page content) */
		.tabcontent {
			padding: 71px 20px;
			height: 100%;
			float: left;
		}
	</style>
</head>

<body>

	<?php

	//define array of names & Id's to generate headers
	if ($fstUser['allocations_admin'] == 'checked') {
		//$header_names = ['Open/Pending', 'Closed', 'Greensheet', 'Inventory', 'Receiving', 'BOM Lookup'];
		//$header_ids = ['Open', 'Closed', 'Greensheet', 'inventory', 'receiving', 'bom_lookup'];
		$header_names = ['Open/Pending', 'Closed', 'Greensheet', 'Inventory', 'Receiving'];
		$header_ids = ['Open', 'Closed', 'Greensheet', 'inventory', 'receiving'];
		$header_redirect = [
			'terminal_warehouse_main.php?shop=' . $shop, 'terminal_warehouse_main.php?shop=' . $shop,
			'terminal_warehouse_main.php?shop=' . $shop, 'terminal_warehouse_inventory.php?shop=' . $shop, ''
		];
	} else {
		$header_names = ['Open/Pending', 'Closed', 'Greensheet', 'Inventory'];
		$header_ids = ['Open', 'Closed', 'Greensheet', 'inventory'];
		$header_redirect = [
			'terminal_warehouse_main.php?shop=' . $shop, 'terminal_warehouse_main.php?shop=' . $shop,
			'terminal_warehouse_main.php?shop=' . $shop, 'terminal_warehouse_inventory.php?shop=' . $shop
		];
	}

	//pass to php function to create navigation bars
	echo create_navigation_bar($header_names, $header_ids, "check_orders()", $fstUser, 'receiving', $header_redirect);

	?>

	<div id='receiving' class='tabcontent' style='display:none;'>

		<table class='standardTables'>
			<tr>
				<td>Job #:</td>
				<td><input type='text' id='receiving_search_project' class='receiving_search'></td>
			</tr>
			<tr>
				<td>Part #:</td>
				<td><input type='text' id='receiving_search_part' class='receiving_search'></td>
			</tr>
			<tr>
				<td>Purchase Order #:</td>
				<td><input type='text' id='receiving_search_po' class='receiving_search'></td>
			</tr>
			<tr>
				<td>Material Order #:</td>
				<td><input type='text' id='receiving_search_mo' class='receiving_search'></td>
			</tr>
			<tr>
				<td>Carrier:</td>
				<td><input type='text' id='receiving_search_carrier' class='receiving_search'></td>
			</tr>
			<tr>
				<td>Tracking #:</td>
				<td><input type='text' id='receiving_search_tracking' class='receiving_search'></td>
			</tr>
			<tr>
				<td style='border: 0'><button onclick='filter_receiving()'>Search</button></td>
			</tr>
		</table>

		<table id='receiving_main_table' class='standardTables'>

			<thead>
				<!-- header rows -->
				<tr class='sticky-header-wh1-receiving'>
					<th><!--placeholder for +/- icon --></th>
					<th>PO # / MO #</th>
					<th>Tracking</th>
					<th>Carrier</th>
					<th>Est. Ship Date</th>
					<th>Expected Date</th>
					<th>Job #</th>
				</tr>
			</thead>
			<tbody>
				<!-- info added from filter_receiving() function -->
			</tbody>
		</table>

	</div>

	<!-- internal js files -->
	<script src="javascript/js_helper.js?<?= $version ?>-3"></script>
	<script src="javascript/utils.js"></script>
	<script src="javascript/accounting.js"></script>

	<!-- external js libraries -->
	<!-- enable ajax use -->
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
	<script src="https://apis.google.com/js/platform.js?onload=init" async defer></script>

	<!-- enable jquery use -->
	<script src="https://code.jquery.com/jquery-1.12.4.js"></script>
	<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>

	<script>
		//global = holds current index
		var current_index = null;

		//lets the system know if it is auto-refreshing
		var refreshing = false;

		//pulls through all relevant info from php
		var allocations_mo = <?= json_encode($allocations_mo); ?>, //allocations MO info sent from allocations
			physical_locations = <?= json_encode($physical_locations); ?>,
			pq_detail = <?= json_encode($pq_detail); ?>, //parts requested & designated to terminal
			pw_shipping = <?= json_encode($pw_shipping); ?>, //PW shipping info
			potential_ship_to = <?= json_encode($shop_ship_to); ?>, //potential ship to locations for given shop
			pq_shipments = <?= json_encode($pq_shipments); ?>, //shipments going to this shop
			shop_ship_to = <?= json_encode($shop_ship_to); ?>,
			staging_areas = <?= json_encode($staging_areas); ?>;

		// pull other info from PHP used to make decisions
		const use_shop = '<?= $shop; ?>';
		var hold_response;
		var user_info = <?= json_encode($fstUser); ?>;

		//handles filtering receiving info based on criteria found on receiving tab
		function filter_receiving() {

			//init filters and shipments
			var filters = [],
				matching_shipments = [],
				all_empty = true;

			//set filter object where id => value
			document.querySelectorAll('.receiving_search').forEach(function(a) {
				filters[a.id] = a.value;

				//verify user has some entered criteria
				if (a.value != "")
					all_empty = false;
			})

			//default to false for testing (comment if production)
			all_empty = false;

			//remove previous entries
			document.querySelectorAll('.temp_receiving_row').forEach(function(a) {
				a.remove();
			})

			//filter out matching shipments (only if user has some criteria entered)
			if (!all_empty) {
				matching_shipments = pq_shipments.filter(function(p) {
					return (filters['receiving_search_project'] == "" || p.project_id.includes(filters['receiving_search_project'])) && //matches user entered project id or no project_id entered
						(filters['receiving_search_po'] == "" || p.po_number.includes(filters['receiving_search_po'])) && //matches user entered PO # or no PO # entered
						(filters['receiving_search_mo'] == "" || p.mo_id.includes(filters['receiving_search_mo'])) && //matches user entered MO # id or no MO # entered
						(filters['receiving_search_carrier'] == "" || p.carrier.toLowerCase().includes(filters['receiving_search_carrier'].toLowerCase())) && //matches user entered carrier or no carrier entered
						(filters['receiving_search_tracking'] == "" || p.tracking.toLowerCase().includes(filters['receiving_search_tracking'].toLowerCase())); //matches user entered tracking or no tracking entered
				});
			}

			//if searching for part #, use seperate function to process a more complex search
			if (filters['receiving_search_part'] != "")
				matching_shipments = search_for_receiving_part(matching_shipments, filters['receiving_search_part']);

			// limit search to 1 shop
			//matching_shipments = matching_shipments.filter(function (p) {
			//	return shop_ship_to.includes(p.po_ship_to);
			//});

			//get table, init index
			var table = u.eid("receiving_main_table").getElementsByTagName("tbody")[0],
				index;

			//limit results to 50
			var limit = Math.min(matching_shipments.length, 50);
			limit = matching_shipments.length;

			//insert new row at bottom of table for each matching quote
			for (var i = 0; i < limit; i++) {

				//run function for new row
				insert_new_receiving_row(table, matching_shipments[i]);
			}
		}

		//handles searching across all open shipments for matching parts
		// param 1 = already filtered matching shipments based on user defined criteria
		// param 2 = part # we are searching for
		function search_for_receiving_part(matching_shipments, target_part) {

			//convert matching shipments into two seperate categories (1 for MOs, 1 for POs) which need to be searched differently
			var po_matching_shipments = matching_shipments.filter(function(p) {
				return p.type == "PO";
			})

			var mo_matching_shipments = matching_shipments.filter(function(p) {
				return p.type == "MO";
			})

			//convert each array of objects into array of IDs (MO #s and shipment ids)
			var shipment_ids = po_matching_shipments.map(a => a.shipment_id);
			var mo_numbers = mo_matching_shipments.map(a => a.mo_id);

			//filter pq_detail for all matches to shipment_ids & part #
			var po_parts = pq_detail.filter(function(part) {
				return shipment_ids.includes(part.shipment_id) && part.part_id.toLowerCase() == target_part.toLowerCase();
			});

			//filter pq_detail for all matches to mo_numbers & part #
			var mo_parts = pq_detail.filter(function(part) {
				return mo_numbers.includes(part.mo_id) && part.part_id.toLowerCase() == target_part.toLowerCase();;
			});

			//now filter matching_shipments 1 more time to get new results
			//check to make sure that a shipment has at least 1 part with a matching shipment id
			var po_matching_shipments = matching_shipments.filter(function(a) {
				return po_parts.some(function(b) {
					return a.shipment_id == b.shipment_id;
				});
			});

			//check to make sure that a shipment has at least 1 part with a matching mo_id
			var mo_matching_shipments = matching_shipments.filter(function(a) {
				return mo_parts.some(function(b) {
					return a.mo_id == b.mo_id;
				});
			});

			//return the two arrays merged together
			Array.prototype.push.apply(po_matching_shipments, mo_matching_shipments);
			return po_matching_shipments;
		}

		//global to help create receiving table in js (used in insert_new_receiving_row)
		const receiving_keys = ['show_shipping_parts', 'po_mo', 'tracking', 'carrier', 'ship_date', 'arrival', 'project_id'];

		//handles inserting new row into inventory table
		//param 1 = table to be inserted To
		//param 2 = shipment object which holds shipping info
		function insert_new_receiving_row(table, shipment) {

			//insert new row
			var row = table.insertRow(-1);
			row.classList.add("temp_receiving_row");

			//update po_mo with corrent # based on type
			if (shipment.type == "PO")
				shipment.po_mo = shipment.po_number;
			else
				shipment.po_mo = shipment.mo_id;

			//cycle through receiving_keys array and generate table row
			for (var i = 0; i < receiving_keys.length; i++) {

				//insert new cell
				var cell = row.insertCell(i);

				//two scenarios right now. 1) regular input field that is readonly. 2) button that needs to be clicked on
				//first, check if we need to create a button
				if (receiving_keys[i] == "show_shipping_parts") {

					//create button & add class (will control event handler) event handler at following:
					//ctrl + f $(document).on('click', '.show_shipping_parts'
					var input = document.createElement("button");
					input.classList.add(receiving_keys[i]);

					//set ID based on type
					if (shipment.type == "PO")
						input.id = shipment.type + "_" + shipment.shipment_id;
					else
						input.id = shipment.type + "_" + shipment.mo_id;

					//update inner html based on key
					if (receiving_keys[i] == "show_shipping_parts")
						input.innerHTML = "+";
				}
				//used for dates
				else if (receiving_keys[i] == "ship_date" || receiving_keys[i] == "arrival") {

					//create input field, add value and any other attributes
					var input = document.createElement("input");
					input.readOnly = true;
					input.classList.add(receiving_keys[i])
					input.value = format_date(shipment[receiving_keys[i]]);

				}
				//last, place all other cases
				else {

					//create input field, add value and any other attributes
					var input = document.createElement("input");
					input.readOnly = true;
					input.classList.add(receiving_keys[i])
					input.value = shipment[receiving_keys[i]];

				}

				//if received_by, adjust readonly
				if (receiving_keys[i] == "received_by")
					input.readOnly = false;

				//append to cell
				cell.append(input);

			}
		}

		//handles showing parts related to a shipment on click
		$(document).on('click', '.show_shipping_parts', function() {

			//get type & identifier from ID
			var type = this.id.substr(0, 2);
			var po_mo_number = this.id.substr(3);

			//work to shipment_id
			var td = this.parentNode;
			var tr = td.parentNode;

			//work to vp_number
			var vp_number = tr.childNodes[6].childNodes[0].value;

			//filter and add parts below shipment ID row
			//grab show/hide symbol
			var show_hide = this.innerHTML;

			//based on innerHTML, show/hide content
			if (show_hide == "+") {

				//show shipment info and change button +/-
				get_shipping_parts(type, po_mo_number, tr.rowIndex, vp_number);
				this.innerHTML = "-";
			} else {
				//remove previous shipment info & change button +/-
				u.eid("shipment_" + po_mo_number).remove();
				this.innerHTML = "+";
			}
		});

		//used to get list of open_order parts based on given po number
		//param 1 = type (PO or MO)
		//param 2 = shipment_id / mo number
		//param 3 = row index (where the parts need to be inserted)
		//param 4 = vp job #
		function get_shipping_parts(type, shipment_id, row_index, vp_number) {

			//init list of parts to add to table, and index to be used
			var parts = [];

			//get list of parts assigned po number
			if (type == "PO") {
				parts = pq_detail.filter(function(part) {
					return part.shipment_id == shipment_id;
				});
			}
			//get list of parts assigned to a MO number
			else if (type == "MO") {
				parts = pq_detail.filter(function(part) {
					return part.mo_id == shipment_id;
				});
			}

			//loop and create a table with these parts
			var table = document.createElement("table");
			table.classList.add("standardTables");
			table.classList.add("shipping_parts_table");

			//set array of headers
			var headers = ["Part Number", "Quantity", "Quantity Received", "Staging Area", "Container"];

			//create main header
			var row = table.insertRow(-1);
			row.classList.add("shipping_parts_header");
			row.style.backgroundColor = "white";

			//create cell, colspan length of sub-headers, and set to main header name
			var cell = row.insertCell(0);
			//cell.colSpan = headers.length;
			cell.innerHTML = "For Job";
			cell.fontSize = "24px";
			cell.style.textAlign = "left";

			//create headers
			var row = table.insertRow(-1);
			row.classList.add("shipping_parts_header");
			row.style.backgroundColor = "white";

			//loop through sub headers and add to table
			for (var i = 0; i < headers.length; i++) {
				var cell = row.insertCell(i);
				cell.innerHTML = headers[i];
			}

			//create 2nd table to hold parts ordered for shop
			var shop_parts = [];

			//reset global to hold staging location
			hold_staged_area = null;

			//loop through parts and call function to create row for given part
			for (var i = 0; i < parts.length; i++) {

				//if the receiving_qty is > 0, we need to make adjustments on q_allocated & vendory_qty
				if (parseInt(parts[i].received_qty) > 0)
					parts[i] = make_qty_adjustments(parts[i]);

				//check if vendor qty is larger than allocated (means part of the order is for stock)
				if (type == "PO" && parseInt(parts[i].vendor_qty) > parseInt(parts[i].q_allocated))
					shop_parts.push(parts[i]);

				//only add if q_allocated > 0
				if (parts[i].q_allocated > 0)
					add_shipping_parts_row(table, parts[i], type);
			}

			//if we found 'shop_parts', create new table and append to cell
			if (shop_parts.length > 0) {
				var shop_table = document.createElement("table");
				shop_table.classList.add("standardTables");
				shop_table.classList.add("shipping_parts_table");

				//set array of headers
				var headers = ["Part Number", "Quantity", "Quantity Received", "Shelf Location"];

				//create main header
				var row = shop_table.insertRow(-1);
				row.classList.add("shipping_parts_header");
				row.style.backgroundColor = "white";

				//create cell, colspan length of sub-headers, and set to main header name
				var cell = row.insertCell(0);
				//cell.colSpan = headers.length;
				cell.innerHTML = "For Shop";
				cell.fontSize = "24px";
				cell.style.textAlign = "left";

				//create sub headers
				var row = shop_table.insertRow(-1);
				row.classList.add("shipping_parts_header");
				row.style.backgroundColor = "white";

				//loop through headers and add
				for (var i = 0; i < headers.length; i++) {
					var cell = row.insertCell(i);
					cell.innerHTML = headers[i];
				}

				//loop through shop parts and add to table
				for (var i = 0; i < shop_parts.length; i++) {
					add_shipping_parts_row(shop_table, shop_parts[i], type, true);
				}
			}

			//add row to orders table and push table to new row
			var receiving_table = u.eid("receiving_main_table").getElementsByTagName('tbody')[0];
			var row = receiving_table.insertRow(row_index);
			row.classList.add("temp_receiving_row");
			row.style.backgroundColor = "white";
			row.id = 'shipment_' + shipment_id;

			//create span that hold job #
			var span = document.createElement("span");
			span.classList.add("job_span");
			span.innerHTML = "Job #: " + vp_number;

			//insert new cell, set colspan, add newly created table
			var cell = row.insertCell(0);
			cell.colSpan = receiving_table.rows[0].cells.length;
			cell.append(span);
			cell.append(table);

			//appending shop_table (if applicable)
			if (shop_parts.length > 0)
				cell.append(shop_table);

			//create button to submit order
			var button = document.createElement("button");
			button.innerHTML = "Submit Only";
			button.id = "button_" + type + "_" + shipment_id;
			button.classList.add("submit_receiving_button")
			button.addEventListener('click', process_receiving);
			cell.append(button);
		}

		// handles making adjustments to qty (vendor & allocated) for parts that are partially received
		function make_qty_adjustments(part) {

			//check the type of the remaining balance (shop or job)
			if (part.not_received_type == "job") {

				//decide what to do based on decision (PO or not)
				if (part.decision == "PO") {
					part.q_allocated = parseInt(part.vendor_qty) - parseInt(part.received_qty);
					part.vendor_qty = 0;
				} else
					part.q_allocated = parseInt(part.q_allocated) - parseInt(part.received_qty);

			} else if (part.not_received_type == "shop") {

				//reduce vendor_qty by received_qty, remove q_allocated
				part.vendor_qty = parseInt(part.vendor_qty) - parseInt(part.received_qty);
				part.q_allocated = 0;

			}

			//set received_qty = 0 so we don't end up here again
			part.received_qty = 0;

			//return adjusted part
			return part;

		}

		//globals that define sql id's for a given part
		//const shipping_parts_content = ['part_id', 'vendor_qty', 'receiving_qty', 'description', 'manufacturer', 'uom'];
		const shipping_parts_content = ['part_id', 'vendor_qty', 'receiving_qty', 'staging_area', 'container'];

		// handles adding part to receiving row
		// param 1 = table (the table to add rows to)
		// param 2 = part (part to be added - matches row of fst_pq_detail)
		// param 3 = type (MO / PO)
		// param 4 = shop_part (default false) if this part is being added to "stock" table
		function add_shipping_parts_row(table, part, type, shop_part = false) {

			//set default based on inventory (if available)
			/*var inv_index = inventory.findIndex(object => {
				return object.partNumber.toLowerCase() == part.part_id.toLowerCase();
			}); 

			//check for index
			if (inv_index > -1){
				part.description = inventory[inv_index].partDescription;
				part.manufacturer = inventory[inv_index].manufacturer;
				part.uom = inventory[inv_index].uom;
			}
			else{
				part.description = "";
				part.manufacturer = "";
				part.uom = "";
			}*/

			//create new row from given table
			var row = table.insertRow(-1);

			//add class depending on shop_part
			if (shop_part)
				row.classList.add("shop");
			else
				row.classList.add("job");

			//loop through global which defines sql table id's and read out information to user
			for (var i = 0; i < shipping_parts_content.length; i++) {

				//create new cell & write out to html
				var cell = row.insertCell(i);

				//change cell contents based on shipping_parts_content
				if (shipping_parts_content[i] == 'receiving_qty') {
					var input = document.createElement("input");
					input.classList.add(shipping_parts_content[i]);
					input.type = "number";

					//set value & add classList based on type
					if (type == "MO") {
						input.value = part.q_allocated;
						input.classList.add(shipping_parts_content[i] + "_" + type + "_" + part.mo_id)
					} else {
						//if this is a shop part, take vendor - allocated
						if (shop_part)
							input.value = parseInt(part.vendor_qty) - parseInt(part.q_allocated);
						else
							input.value = part.q_allocated;

						input.classList.add(shipping_parts_content[i] + "_" + type + "_" + part.shipment_id)
					}

					cell.appendChild(input);
				} else if (shipping_parts_content[i] == 'staging_area') {

					var input = create_select_list(staging_areas);
					input.classList.add(shipping_parts_content[i]);

					//try to fill in staging area value if previous job is already staged
					input.value = get_staging_area(part);

					//if this is for a shop, re-write input as a select list with the drop-down options as the current physical locations
					if (shop_part)
						input = get_physical_location_select_list(part)

					//set classList based on type
					if (type == "MO")
						input.classList.add(shipping_parts_content[i] + "_" + type + "_" + part.mo_id)
					else
						input.classList.add(shipping_parts_content[i] + "_" + type + "_" + part.shipment_id)

					cell.appendChild(input);
				} else if (shipping_parts_content[i] == 'container') {

					var input = create_select_list(['Container 1', 'Container 2', 'Container 3', 'Container 4', 'Container 5', 'Container 6', 'Container 7', 'Container 8', 'Container 9', 'Container 10']);
					input.classList.add(shipping_parts_content[i]);
					input.value = get_staging_area(part.wh_container);

					//set classList based on type
					if (type == "MO")
						input.classList.add(shipping_parts_content[i] + "_" + type + "_" + part.mo_id)
					else
						input.classList.add(shipping_parts_content[i] + "_" + type + "_" + part.shipment_id)

					cell.appendChild(input);
				} else if (shipping_parts_content[i] == 'vendor_qty') {
					//if this is a shop part, take vendor - allocated
					if (shop_part)
						cell.innerHTML = parseInt(part.vendor_qty) - parseInt(part.q_allocated);
					else
						cell.innerHTML = part.q_allocated;
				} else
					cell.innerHTML = part[shipping_parts_content[i]];

				//add special class list of part number
				if (shipping_parts_content[i] == 'part_id') {
					if (type == "MO")
						cell.classList.add("part_id_" + "_" + type + "_" + part.mo_id)
					else
						cell.classList.add("part_id_" + "_" + type + "_" + part.shipment_id)

					//add ID as pq_detail id
					cell.id = part.id;
				} else if (shipping_parts_content[i] == 'vendor_qty') {
					if (type == "MO")
						cell.classList.add("vendor_qty_" + "_" + type + "_" + part.mo_id)
					else
						cell.classList.add("vendor_qty_" + "_" + type + "_" + part.shipment_id)
				}
			}
		}

		//creats standard select list
		//param 1 = list of items you want turned into a select list
		function create_select_list(list) {

			//create select list, add standard class lists, push new list of items
			var select = document.createElement("select");
			select.classList.add('custom-select');

			//add blank option to select list
			select.appendChild(document.createElement("option"));

			//loop and add tasks
			for (var i = 0; i < list.length; i++) {
				var option = document.createElement("option");
				option.value = list[i];
				option.innerHTML = list[i];
				select.appendChild(option);
			}

			return select;

		}


		//global to hold staging area (so we only search each job once)
		var hold_staged_area = null;

		//handles getting staging area related to a part (if it exists)
		function get_staging_area(part) {

			//check hold_staged_area, if not null, return value (we've already found it)
			if (hold_staged_area !== null)
				return hold_staged_area;

			//otherwise, we need to search for a previous staged area. 
			//look for any orders that are staged to ship later in the current shops queue
			var index = allocations_mo.findIndex(object => {
				return object.status == "Shipping Later" && object.project_id == part.vp_number;
			});

			//if we find a match, use the staged location saved, otherwisre return nothing
			if (index != -1)
				hold_staged_area = allocations_mo[index].staged_loc;
			else
				hold_staged_area = "";

			return hold_staged_area;
		}

		// handles getting list of physical locations and turning into a select list
		// param 1 = part (see fst_pq_detail for available attributes)
		// return select list with physical locations as options
		function get_physical_location_select_list(part) {

			//create select list
			var select = document.createElement("select");
			select.classList.add("custom-select");

			//create 1st option as blank option (default)
			var option = document.createElement("option");
			select.appendChild(option);

			//create 2nd option as "new location" which allows user to create new physical location here
			var option = document.createElement("option");
			option.innerHTML = "Create New Location (not live)";
			option.value = "Create New Location (not live)";
			select.appendChild(option);

			//look at inv_locations, search for list of matching entries
			var targ_locations = physical_locations.filter(function(p) {
				return p.shop.toLowerCase().includes(use_shop.toLowerCase()) && p.partNumber.toLowerCase() == part.part_id.toLowerCase();
			});

			//loop through target locations, and add option for each location
			for (var i = 0; i < targ_locations.length; i++) {
				var option = document.createElement("option");
				option.innerHTML = targ_locations[i].location + " (" + targ_locations[i].shop + ")";
				option.value = targ_locations[i].location + "|" + targ_locations[i].shop;
				select.appendChild(option);
			}

			return select;
		}

		//handles processing the receiving of parts
		function process_receiving() {

			//use assigned ID (see get_shipping_parts())
			//pull out type & mo / po value
			var type = this.id.substr(7, 2);
			var po_mo = this.id.substr(10);

			//init list to send to server
			var received_parts = [];

			//loop through classlists assigned for quantity & staging for this shipment
			var qty = u.class("receiving_qty_" + type + "_" + po_mo),
				staging_area = u.class("staging_area_" + type + "_" + po_mo),
				container = u.class("container_" + type + "_" + po_mo),
				parts = u.class("part_id_" + "_" + type + "_" + po_mo),
				expected_qty = u.class("vendor_qty_" + "_" + type + "_" + po_mo);

			for (var i = 0; i < qty.length; i++) {

				//if qty received > 0, push to array
				if (qty[i].value > 0)
					received_parts.push({
						qty: qty[i].value,
						expected_qty: expected_qty[i].innerHTML,
						staging_area: staging_area[i].value,
						container: container[i].value,
						id: parts[i].id,
						part: parts[i].innerHTML,
						type: parts[i].parentNode.classList[0]
					});
			}

			//initialize form elements
			var fd = new FormData();

			//add received parts & user info
			fd.append('received_parts', JSON.stringify(received_parts));
			fd.append('user_info', JSON.stringify(user_info));

			//add type (MO, PO) and reference #
			fd.append('type', type);
			fd.append('po_mo', po_mo);
			fd.append('shop', use_shop);

			//add tell
			fd.append('tell', 'process_receiving');

			$.ajax({
				url: 'terminal_warehouse_helper.php',
				type: 'POST',
				processData: false,
				contentType: false,
				data: fd,
				success: function(response) {

					console.log(response);

					// If the response is not formatted correctly, report error
					try {
						//convert response to array[object{}] & refresh
						pq_shipments = $.parseJSON(response);
						filter_receiving();

						//alert user of success
						alert("These parts have been received.");
					} catch (error) {
						alert("There may have been an error. Please screenshot & send to fst@piersonwireless.com: " + response);
						console.log(response);
						return;
					}
				}
			});
		}

		//handles tabs up top that toggle between divs
		function change_tabs(pageName, elmnt, color) {

			// redirect depending on user selection
			if (pageName == "receiving")
				filter_receiving();

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

		//windows onload
		window.onload = function() {

			// Get the element with id="defaultOpen" and click on it
			u.eid("defaultOpen").click();
		}

		//used to toggle wait for mouse
		$(document).ajaxStart(function() {
			//add mouse spinning
			if (!refreshing)
				waiting('on');
		});

		$(document).ajaxStop(function() {

			//remove mouse spinning
			waiting('off');

			//set refresh to false
			refreshing = false;

		});
	</script>

</body>

<?php

//reset return address once the page has loaded
unset($_SESSION['returnAddress']);

//close SQL connection
$mysqli->close();

?>

</html>