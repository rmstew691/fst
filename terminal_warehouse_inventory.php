<?php

session_start();

//grab basic info about the quote
$shop = $_GET["shop"];

//if test, turn into actual
if ($shop == "OMA-Test")
	$shop = "OMA";
elseif ($shop == "CHA-Test")
	$shop = "CHA";

//used to grab actual link for the current address
$actual_link = "https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

//Save current site so we can return after log in
$_SESSION['returnAddress'] = $actual_link;

//sub link
$sub_link = substr($actual_link, 0, strpos($actual_link, "home"));

include('phpFunctions.php');

//include php HTML renderings
include('phpFunctions_html.php');

// Load the database configuration file
require_once 'config.php';

//include constants sheet
include('constants.php');

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

//init arrays
$inv_locations = [];

//grabs physical locations
$query = "select * from inv_locations;";
$result =  mysqli_query($con, $query);

while ($rows = mysqli_fetch_assoc($result)) {
	array_push($inv_locations, $rows);
}

//init part request detail (parts)
$pq_detail = [];

//grabs detail (actual parts requested)
$query = "SELECT part_id, status, q_allocated, mo_id, decision FROM fst_pq_detail WHERE status = 'Pending' AND decision <> 'PO';";
$result =  mysqli_query($con, $query);

while ($rows = mysqli_fetch_assoc($result)) {
	array_push($pq_detail, $rows);
}

//grab physical locations stored for each part/shop & quantities
$physical_locations = [];
$query = "SELECT shop, partNumber, location, quantity, prime FROM invreport_physical_locations ORDER BY partNumber, prime DESC;";
$result =  mysqli_query($con, $query);

while ($rows = mysqli_fetch_assoc($result)) {
	array_push($physical_locations, $rows);
}

//grab current reel assignments
$reel_assignments = [];
$query = "select * from inv_reel_assignments order by partNumber, cast(substr(id, 3) as unsigned) asc;";
$result =  mysqli_query($con, $query);

while ($rows = mysqli_fetch_assoc($result)) {
	array_push($reel_assignments, $rows);
}

//grab current reel requests
$reel_requests = [];
$query = "select a.*, b.shop, b.bulk, b.location from inv_reel_requests a
			LEFT JOIN inv_reel_assignments b
				ON a.reel_id = b.id;";
$result =  mysqli_query($con, $query);

while ($rows = mysqli_fetch_assoc($result)) {
	array_push($reel_requests, $rows);
}

//grab inventory logs
$inventory_logs = [];
$query = "SELECT a.partNumber, a.type, a.description, a.time_stamp, CONCAT(b.firstName, ' ' , b.lastName) as user_name FROM invreport_logs a, fst_users b WHERE a.type NOT IN ('PA', 'IA', 'CC') AND a.user = b.id;";
$result =  mysqli_query($con, $query);

while ($rows = mysqli_fetch_assoc($result)) {
	array_push($inventory_logs, $rows);
}

//get reel categories
$reel_categories = [];
$query = "SELECT * FROM inv_reel_categories;";
$result =  mysqli_query($con, $query);

while ($rows = mysqli_fetch_assoc($result)) {
	array_push($reel_categories, $rows['category']);
}

//get all available shops
$all_shops = [];
$query = "select shop from inv_locations WHERE shop NOT IN ('LMO', 'LPO', 'MIN-VZO', 'IN-TRANSIT') GROUP BY shop;";
$result =  mysqli_query($con, $query);

while ($rows = mysqli_fetch_assoc($result)) {
	array_push($all_shops, $rows['shop']);
}

?>

<!doctype html>
<html>

<head>
	<meta charset="utf-8">
	<meta name="google-signin-client_id" content="573761357198-hin7ae7q19qgvoab7t0781b41530546g.apps.googleusercontent.com">
	<link rel="shortcut icon" type="image/x-icon" href="images/P-Logo.png" />
	<link href="stylesheets/jquery-ui-themes-1.13.0/themes/ui-lightness/jquery-ui.css" rel="stylesheet">
	<title>Warehouse Inventory (v<?= $version ?>) - Pierson Wireless</title>
	<link rel="stylesheet" href="stylesheets/element-styles.css?<?= $version; ?>" />
	<link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css'>
	<link rel="stylesheet" type="text/css" href="stylesheets/terminal_warehouse_print.css" media="print">


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

		/**style additional info tables (appear when expanding + icon in main dashboard) */
		.add_info_table {
			padding: 1em;
			float: left;
		}

		.add_info_table td {
			border: 0 !important;
		}

		.add_info_table tbody {
			border: 0 !important;
			margin-bottom: 0px !important;
			width: auto !important;
		}

		/**style temp_inv_row on hover */
		#inventory_main_table tbody tr:hover,
		#receiving_main_table tbody tr:hover {
			background-color: #a7c2ee;
		}

		/**determine whether this class should be shown or hidden depnding on access level */
		.show_for_admin {
			<?php

			if ($fstUser['allocations_admin'] != "checked" && $fstUser['accessLevel'] != "Admin")
				echo "display:none";

			?>
		}

		/**give some space for the arrows when filtering inventory */
		#filter_by_arrow {
			margin-left: 0.5em;
			font-size: 20px;
		}

		/**force header rows on inventory sheet to be sticky */
		.sticky-header-wh1,
		.sticky-header-wh1-receiving {
			position: sticky;
			top: 46px;
			z-index: 100;
			background: white;
		}

		.sticky-header-wh1 th {
			cursor: pointer;
		}

		.sticky-header-wh2 {
			position: sticky;
			top: 91px;
			z-index: 100;
			background: white;
		}

		/**style elements within inventory_reels_dialog */
		.reel_id,
		.reel_quantity,
		.reel_location {
			width: 5em;
		}

		/**styles bulk reel inputs */
		.bulk_reed_id {
			font-weight: bold;
		}

		/**style elements inside of inventory adjustment div */
		.adjustment_physical_location,
		.adjustment_on_hand {
			width: 6em;
		}

		/**style input fields on inventory table */
		.shop {
			width: 5em;
		}

		.partNumber {
			width: 15em;
		}

		.allocated {
			width: 5em;
		}

		.overstock_location,
		.primary_location {
			/* cursor: pointer; */
			width: 6em;
		}

		.last_activity {
			width: 5.4em;
			cursor: pointer;
		}

		.lastCount {
			width: 5.4em;
		}

		/**adjust style of divs inside of dialog boxes */
		.div_adjustment {
			margin-left: 1em;
			margin-top: 1em;
		}

		.complete-row input[type='text'] {
			color: red;
		}

		.complete-row input[type='number'] {
			color: red;
		}

		.hide_text_area {
			width: 222px;
			background: white;
			border: none;
			font-size: 16px;
			font-family: Arial;
			height: 224px;
			min-height: 20px;
			resize: unset;
			color: black;
		}

		#pq-parts-table {
			padding-bottom: 2em;
		}

		.large_button {
			font-size: 20px !important;
			width: 14em;
			height: 2em;
			text-align: center;
		}

		.part_id {
			width: 15em;
		}

		.description {
			width: 20em;
		}

		.uom {
			width: 5em;
		}

		.q_allocated {
			width: 75px;
		}

		.uom {
			width: 5em;
		}

		.decision,
		.split_decision {
			width: 10em;
		}

		.on_hand,
		.stock {
			width: 5em;
		}

		.location {
			width: 5em;
		}

		.pallet {
			width: 5em;
		}

		.wh_notes {
			height: 12.85px;
			width: 300px;
			resize: horizontal;
		}

		.whh-inputs,
		.remove_whh {
			cursor: pointer;

		}

		.ui-menu {
			width: 150px;
		}

		input:focus:not([type=submit]),
		textarea:focus,
		select:focus {
			border-width: 3.8px !important;
		}

		.wait {
			cursor: wait;
		}

		input:read-only,
		textarea {
			background-color: #C8C8C8;
		}

		input:read-write,
		textarea {
			background-color: #BBDFFA;
			border-color: #000B51;
			border-width: medium;
			cursor: text;
		}

		#basicInfo {
			float: left;
		}

		.homeTables {
			border-collapse: collapse;
			margin-bottom: 20px;
			margin-right: 20px;

		}

		.homeTables th {
			padding: 4px;

		}

		.homeTables td {
			border: 1px solid #000000;
			padding: 4px;
		}

		#shoppingCart-table td {
			text-align: center;
		}

		#mo_information {
			border-collapse: collapse;
			display: inline-block;
			vertical-align: middle;
			padding-bottom: 10em;

		}

		#mo_information tr {
			line-height: 3px;
		}

		/* Style the tab content (and add height:100% for full page content) */
		.tabcontent {
			padding: 71px 20px;
			height: 100%;
			float: left;
		}

		/* Style customer inputs*/
		.custom-input-header {
			width: 13.2em;

		}

		.custom-select-header {
			background-color: #BBDFFA;
			border-color: #000B51;
			border-width: medium;
			cursor: pointer;
			width: 14em;

		}

		.basic-table {
			display: inline-block;
			padding-bottom: 5px;
		}

		.basic-table td {
			padding-right: 5px;
		}

		.ui-widget {
			padding-bottom: 10px;
		}

		.price,
		.cost,
		.last_cost {
			width: 7em;
		}

		.remove:hover {
			cursor: pointer;
		}

		.stock_greensheet {
			text-align: center;
			font-weight: bold;
		}

		#partSubs td {
			border: 1px solid #000000;
			text-align: center;
		}

		#partSubs {
			border-collapse: collapse;
		}

		.toggle-wrap,
		.shape {
			display: inline-block;
			vertical-align: top;
		}

		.shape {
			margin-left: 4em;
			margin-top: 2.5em;
			height: 8em;
			width: 8em;
			box-shadow: 4px 4px 8px;
			color: #ccc;
			background-repeat: no-repeat;
			background-size: 90%;
			background-position: 50%;
		}

		.table_gap {
			width: 50px;
		}

		.mo_head {
			font-weight: bold;
			padding-right: 5px;
		}

		.overnight_style {
			background-color: #FF6366;
		}

		.urgent_style {
			background-color: #FFFA7C;
		}

		.standard_style {
			background-color: #49FF58;
		}

		.closed_style {
			background-color: #767676;
		}

		.greensheet_style {
			background-color: #F98739;
		}

		.closed-page {
			visibility: collapse;
		}

		.required-greensheet {
			width: 20em;
		}

		.custom-files {
			width: 14em;
		}

		.date_required {
			font-size: 14px;
		}

		.mo_info_header {
			font-size: 25px;
			padding-bottom: 1em;
		}

		#title_div {
			width: 100%;
			display: flex;
			justify-content: center;
			text-align: center;

		}

		.ui-state-active {
			background-color: #007fff !important;
		}

		.list-item:hover {
			background-color: #BBE4FF;
			cursor: pointer;
		}

		.list-item {
			font-weight: bold !important;
			color: black !important;
		}

		.reject_td {
			font-weight: bold;
			padding: 7px 20px 7px 0px;
		}

		#reject_reason {
			height: 31px !important;
			width: 238px;
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
			'terminal_warehouse_main.php?shop=' . $shop, '', 'terminal_warehouse_receiving.php?shop=' . $shop
		];
	} else {
		$header_names = ['Open/Pending', 'Closed', 'Greensheet', 'Inventory'];
		$header_ids = ['Open', 'Closed', 'Greensheet', 'inventory'];
		$header_redirect = [
			'terminal_warehouse_main.php?shop=' . $shop, 'terminal_warehouse_main.php?shop=' . $shop,
			'terminal_warehouse_main.php?shop=' . $shop, ''
		];
	}

	//pass to php function to create navigation bars
	echo create_navigation_bar($header_names, $header_ids, "save_changes()", $fstUser, 'inventory', $header_redirect);

	?>

	<div id='inventory' class='tabcontent' style='display:none;'>

		<table style='float:left'>
			<tr>
				<td class='td_head'># of Results Per Page</td>
				<td>
					<select id='inventory_results_filter' class='custom-select' onchange='filter_inventory()'>
						<option>50</option>
						<option>100</option>
						<option>200</option>
						<option>Show All</option>
					</select>
				</td>
				<td colspan='2'>
					<input type='checkbox' id='inventory_exact_part' onchange='filter_inventory()'><i>Search for exact part #.</i>
				</td>
			</tr>
		</table>

		<table style='float:left; padding-left: 6em; padding-bottom: 2em;'>
			<tr>
				<th colspan='2'>Random Cycle Count Generator</th>
			</tr>
			<tr>
				<td class='td_head'>Cycle Count Qty:</td>
				<td><input id='cycle_count_qty' type='number' style='width:5em;' value='50'></td>
			</tr>
			<tr>
				<td class='td_head'>Shop:</td>
				<td><input id='cycle_count_shop' value='<?= $shop; ?>' style='width:5em;' readonly></td>
			</tr>
			<tr>
				<td>
					<button onclick='generate_cycle_count()'>Generate Cycle Count</button>
					<a id="hold_download" style="display:none"></a>
				</td>
			</tr>
			<tr>
				<td>
					<button onclick='document.getElementById("update_cycle_count_csv").click()'>Update Count</button>
					<input type='file' onchange='update_cycle_count()' id='update_cycle_count_csv' style='display:none'>
				</td>
			</tr>
		</table>

		<table style='float:left; padding-left: 6em;' class='show_for_admin'>
			<!-- <tr>
			<th colspan = '2'>Shop Values <i>(based on filtered results)</i><input id = 'get_totals' type = 'checkbox' onchange = 'filter_inventory()'></th>
		</tr> -->
			<tr>
				<td class='td_head'><b>Filtered Value:</b></td>
				<td><input id='filtered_shop_value' style='width:8em;' readonly></td>
			</tr>
		</table>

		<br><br>

		<table id='inventory_main_table' class='standardTables'>

			<thead>
				<!-- filter bars -->
				<tr class='sticky-header-wh1'>

					<th><!--placeholder for +/- icon --></th>
					<th>Shop<span id="filter_by_arrow">&#129095;</span></th>
					<th>Part #</th>
					<th>Category</th>
					<th>Description</th>
					<th>Manufacturer</th>
					<th style='width:0;'>Last Unit Cost</th>
					<th style='width:0;'>Shop On-Hand</th>
					<th>Allocated</th>
					<th style='width:0;'>Last Count Date</th>
					<th style='width:0;'>Pick Location</th>
					<th style='width:0;'>Overstock Location</th>
					<th style='width:0;'>Last Activity</th>
					<th><!--placeholder for make adjustment button --></th>
					<th><!--placeholder for assign reels button --></th>
					<th><!--placeholder for transfer button --></th>
				</tr>
				<!-- header row -->
				<tr class='sticky-header-wh2'>
					<th><!--placeholder for +/- icon --></th>
					<th>
						<?php

						//set shops (hard-coded, should be pulled into DB at a later time)
						//$shops = ["All Shops", "CHA-1", "CHA-3", "OMA-1", "OMA-2", "OMA-3", "MIN-1", "MIN-3", "KC-1", "KC-3", "HOU-1", "HOU-3", "CHI-1", "CHI-3", "DAL-1", "DAL-3", "IND-1", "IND-3", "LAN-1", "LAN-3", "LAS-1", "LAS-3", "NJ-1", "NJ-3", "PHI-1", "PHI-3", "PIT-1", "PIT-3", "SAF-1", "SAF-3"];
						$shops = ["All Shops", "CHA", "OMA", "GCS", "MIN", "KC", "HOU", "CHI", "DAL", "IND", "LAN", "LAS", "NJ", "PHI", "PIT", "SAF"];

						//overwrite with just shop location if user is not allocations admin
						if ($fstUser['allocations_admin'] != "checked" && $fstUser['accessLevel'] != "Admin")
							$shops = [$shop];
						?>

						<select id='inventory_shop_filter' class='custom-select' onchange='filter_inventory()' style='width: 100%'>

							<?php

							//loop through $shops array to create option drop-downs
							for ($i = 0; $i < sizeof($shops); $i++) {

							?>

								<option><?= $shops[$i]; ?></option>

							<?php

							}

							?>

						</select>
					</th>
					<th><input id='inventory_part_filter' onkeyup='filter_inventory()' style='width: 100%'></th>
					<th><input id='inventory_category_filter' onkeyup='filter_inventory()' style='width: 100%'></th>
					<th><input id='inventory_description_filter' onkeyup='filter_inventory()' style='width: 100%'></th>
					<th><input id='inventory_manufacturer_filter' onkeyup='filter_inventory()' style='width: 100%'></th>
					<th><!-- <input id = 'inventory_on_hand_filter' onkeyup = 'filter_inventory()'> --></th>
					<th></th>
					<th></th>
					<th style='width:0;'></th>
					<th style='width:0;'><input id='inventory_location_filter' onkeyup='filter_inventory()' style='width: 100%'></th>
					<th style='width:0;'><input id='inventory_overstock_filter' onkeyup='filter_inventory()' style='width: 100%'></th>
					<th><!--placeholder for last activity --></th>
					<th><!--placeholder for make adjustment button --></th>
					<th><!--placeholder for assign reels button --></th>
					<th><!--placeholder for transfer button --></th>
				</tr>
			</thead>
			<tbody>
				<!-- info added from filter_inventory() function -->
			</tbody>
		</table>
	</div>

	<div style='display: none;' id='inventory_adjustment_dialog' title='Inventory Adjustment' class='div_adjustment'>

		<table id='inventory_adjustment_table' class='partDialog'>
			<tr>
				<th style='width: 4em'>Shop</th>
				<th style='width: 15em'>Part Number</th>
				<th style='width: 3em'>UOM</th>
				<th style='width: 4em'>Current On-Hand</th>
				<th style='width: 4em'>Adjust To</th>
			</tr>
			<tr>
				<td><input id='adjustment_shop' style='width: 4em' readonly></td>
				<td><input id='adjustment_part' style='width: 15em' readonly></td>
				<td><input id='adjustment_uom' style='width: 3em' readonly></td>
				<td><input id='adjustment_on_hand' style='width: 4em' readonly></td>
				<td><input type='number' id='adjustment_new_on_hand' style='width: 4em' onchange='update_on_hand()'></td>
			</tr>
		</table>
		<button onclick='add_physical_location_row()' style='margin-top:2em; margin-bottom:1em;'>Add New Physical Location</button>
		<table id='inventory_adjustment_location_table'>
			<tr>
				<th><!-- placeholder for x button --></th>
				<th style='width: 3em'>Physical Location</th>
				<th style='width: 3em'>On-Hand</th>
				<th>Primary</th>
			</tr>
			<!--ROWS TO BE INSERTED BY add_physical_location_row() !-->
			<tr>
				<td colspan="2"></td>
				<td style='text-align:center' id='on_hand_row'><b id='on_hand_total'>0</b></td>
			</tr>
		</table>
		<button onclick='update_inventory_on_hand()' style='margin-top: 1em;'>Submit Adjustment</button>

	</div>

	<div style='display: none;' id='inventory_reels_dialog' title='Inventory Reels' class='div_reels'>

		<table class='partDialog'>
			<tr>
				<th style='width: 4em'>Shop</th>
				<th style='width: 15em'>Part Number</th>
				<th style='width: 4em'>On-Hand</th>
			</tr>
			<tr>
				<td><input id='reels_shop' style='width: 4em' readonly></td>
				<td><input id='reels_part' style='width: 15em' readonly></td>
				<td><input id='reels_on_hand' style='width: 4em' readonly></td>
			</tr>
		</table>
		<button onclick='assign_reel_id(0)' style='margin-top:2em; margin-bottom:1em;'>Create New Reel</button>
		<button onclick='assign_reel_id(1)' style='margin-top:2em; margin-bottom:1em;'>Create Bulk Reel</button>
		<table id='inventory_reels_table'>
			<tr>
				<th><!-- placeholder for x button --></th>
				<th style='width: 3em'>Reel ID</th>
				<th>Quantity</th>
				<th>Location</th>
			</tr>
			<!--ROWS TO BE INSERTED BY add_reel_row() !-->
			<tr>
				<td colspan="2"></td>
				<td style='text-align:center' id='reels_total_row'><b id='reels_total'>0</b></td>
			</tr>
		</table>

		<button onclick='update_reels()' style='margin-top: 1em;'>Save Reel Assignments</button>
	</div>

	<div style='display: none;' id='greensheet_search' title='Search for quote #' class='div_adjustment'>

		<table class='standardTables'>
			<tr>
				<th colspan="2">Enter any of the following:</th>
			</tr>
			<tr>
				<td>Job Name</td>
				<td><input id='greensheet_search_project'></td>
			</tr>
			<tr>
				<td>Project #</td>
				<td><input id='greensheet_search_num'></td>
			</tr>
		</table>

		<button onclick='filter_quotes()'>Search for Quote</button>

		<table class='standardTables' id='greensheet-search-table'>
			<!-- info generated from filter_quotes() -->
		</table>

	</div>

	<div id='activity_dialog' style='display:none' title='Last Activity'>

		<table class='standardTables' id='activity_table'>
			<thead>
				<tr>
					<th>Type</th>
					<th>Part Number</th>
					<th>UOM</th>
					<th>Description</th>
					<th>User</th>
					<th>Date</th>
				</tr>
				<tr>
					<td><input class='activity_filter'></td>
					<td><input class='activity_filter'></td>
					<td><input class='activity_filter'></td>
					<td><input class='activity_filter'></td>
					<td><input class='activity_filter'></td>
					<td><input class='activity_filter'></td>
				</tr>
			</thead>
			<tbody>
				<!--rows inserted by add_last_activity() -->
			</tbody>
		</table>

	</div>

	<div style='display: none;' id='transfer_dialog' title='Material Transfer'>

		<b style='padding-right:1em;'>Part Number</b><input id='transfer_part' style='margin-top:1em; width: 12em;' readonly>

		<div id='transfer_options_div' style='padding-top: 1em;'>
			<b>Are you:</b>
			<button onclick='transfer_options("request")'>Requesting a transfer</button><button onclick='transfer_options("make")'>Making a transfer</button>
		</div>

		<div id='request_transfer_div' class='transfer_options'>
			<table class='standardTables' style='margin-top:1em;'>
				<tr>
					<th>From (Shop)</th>
					<th>Qty</th>
					<th>To (Shop)</th>
				</tr>
				<tr>
					<td>
						<select id='request_transfer_from' class='custom-select' style='width:6em;'>
							<option></option>

							<?php

							for ($i = 0; $i < sizeof($all_shops); $i++) {

							?>

								<option><?= $all_shops[$i]; ?></option>

							<?php

							}

							?>
						</select>
					</td>
					<td><input type='number' id='request_transfer_qty' style='width: 5em' min=0></td>
					<td><input id='request_transfer_to' class='transfer_update_shop' style='width: 6em' readonly></td>
				</tr>
				<tr>
					<td colspan='2' style='display:none'><button onclick='transfer_part()' style='margin-top: 1em;'>Submit Transfer</button></td>
				</tr>
			</table>
		</div>

		<div id='make_transfer_div' class='transfer_options' style='margin-top: 1em;'>

			<b>Type of transfer:</b>

			<select id='make_transfer_options' class='custom-select' onchange='update_transfer(this)'>
				<option value='shop_to_shop'>Shop to Shop</option>
				<option value='loc_to_loc'>Physical Location to Physical Location</option>
				<!-- <option>Shop to Shop</option> -->
			</select>

			<table class='standardTables shop_to_shop_tables make_transfer_table' style='margin-top:1em;'>
				<tr>
					<th>From (Shop)</th>
					<th>Qty</th>
					<th>To (Shop)</th>
				</tr>
				<tr>
					<td><input id='make_transfer_from' class='transfer_update_shop' style='width: 6em' readonly></td>
					<td><input type='number' id='make_transfer_qty' style='width: 5em' min=0></td>
					<td>
						<select id='make_transfer_to' class='custom-select' style='width:6em;' onchange='update_transfer_to()'>
							<option></option>

							<?php

							for ($i = 0; $i < sizeof($all_shops); $i++) {

							?>

								<option><?= $all_shops[$i]; ?></option>

							<?php

							}

							?>
						</select>
					</td>
				</tr>
			</table>

			<table class='standardTables shop_to_shop_tables make_transfer_table' style='margin-top:1em;'>
				<tr>
					<td>Street</td>
					<td><input id='make_transfer_street'></td>
				</tr>
				<tr>
					<td>Street2</td>
					<td><input id='make_transfer_street2'></td>
				</tr>
				<tr>
					<td>City</td>
					<td><input id='make_transfer_city'></td>
				</tr>
				<tr>
					<td>State / Zip</td>
					<td>
						<select id='make_transfer_state' style='width: 5em' class='custom-select'>
							<option></option>
							<?php

							//read out $states from constants.php
							for ($i = 0; $i < sizeof($states); $i++) {

							?>
								<option><?= $states[$i]; ?></option>
							<?php

							}

							?>
						</select>
						<input id='make_transfer_zip' style='width: 8.6em;'>
					</td>
				</tr>
			</table>

			<table class='standardTables loc_to_loc_tables make_transfer_table' style='margin-top:1em;'>
				<tr>
					<th>Physical location (from)</th>
					<th>Qty</th>
					<th>Physical location (to)</th>
				</tr>
				<tr>
					<td>
						<select id='physical_location_from' class='custom-select'>
							<!--to be filled with insert_transfer_physical_locations() -->
						</select>
					</td>
					<td><input id='physical_locaiton_transfer_qty' style='width: 5em;'></td>
					<td>
						<select id='physical_location_to' class='custom-select'>
							<!--to be filled with insert_transfer_physical_locations() -->
						</select>
					</td>
				</tr>
			</table>

		</div>
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

	<!-- Used to bring in PDF Make (PDF Renderer) !-->
	<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.3.0-beta.1/pdfmake.min.js" integrity="sha512-G332POpNexhCYGoyPfct/0/K1BZc4vHO5XSzRENRML0evYCaRpAUNxFinoIJCZFJlGGnOWJbtMLgEGRtiCJ0Yw==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.3.0-beta.1/standard-fonts/Times.js" integrity="sha512-KSVIiw2otDZjf/c/0OW7x/4Fy4lM7bRBdR7fQnUVUOMUZJfX/bZNrlkCHonnlwq3UlVc43+Z6Md2HeUGa2eMqw==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

	<script>
		//load in inventory & inv_location info
		var inv_locations = <?= json_encode($inv_locations); ?>,
			physical_locations = <?= json_encode($physical_locations); ?>,
			reel_assignments = <?= json_encode($reel_assignments); ?>,
			reel_requests = <?= json_encode($reel_requests); ?>,
			inventory_logs = <?= json_encode($inventory_logs); ?>,
			pq_detail = <?= json_encode($pq_detail); ?>;

		const use_shop = '<?= $shop; ?>';
		var user_info = <?= json_encode($fstUser); ?>;

		//global to hold current filter & current direction
		var inv_filter_type = 'Shop',
			inv_filter_ascending = true,
			mass_update = false;

		// add event listener to table heads (allow all heads to be filterable)
		$(document).on('click', '.sticky-header-wh1 th', function() {

			//extract header name (ignore "<span" element if already added)
			var span_pos = this.innerHTML.indexOf("<span");
			var check = this.innerHTML;

			//if we find a span pos, trim it off
			if (span_pos > 0)
				check = this.innerHTML.substr(0, span_pos);

			//if check is a 'problem' column, we need to go through and update inv_locations
			var problem_heads = ['Allocated', 'Pick Location', 'Overstock Location'];

			if (problem_heads.indexOf(check) > -1 && !mass_update) {
				//pop-up one-moment please dialog
				update_inv_location();
			}

			//check if element matches previously clicked
			if (inv_filter_type == check) {

				//flip direction and filter inventory
				inv_filter_ascending = !inv_filter_ascending;
				filter_inventory();

				//change arrow depending on current direction
				if (inv_filter_ascending)
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
				inv_filter_type = check;
				inv_filter_ascending = true;
				filter_inventory();

			}
		});

		//used to mass update inv_location (for problem cells)
		function update_inv_location() {

			//init index to be used
			var index;

			//loop through inv_locations (matches inv_locations db table)
			for (var i = 0; i < inv_locations.length; i++) {

				//update 'part' with any attributes not availabe from inv_locations (but are available in invreport)
				inv_locations[i] = update_attributes(inv_locations[i]);
			}

			//set mass_update to true (so we don't do this again)
			mass_update = true;

		}

		//global to hold results from filter_inventory (use if we have multiple pages)
		var matching_inventory;

		//handles filtering parts based on criteria found on inventory tab
		function filter_inventory() {

			//get filters
			var shop = u.eid("inventory_shop_filter").value,
				part = u.eid("inventory_part_filter").value,
				location = u.eid("inventory_location_filter").value,
				overstock = u.eid("inventory_overstock_filter").value,
				category = u.eid("inventory_category_filter").value,
				description = u.eid("inventory_description_filter").value,
				manufacturer = u.eid("inventory_manufacturer_filter").value;

			//remove previous entries
			document.querySelectorAll('.temp_inv_row').forEach(function(a) {
				a.remove();
			})

			//if certain filters are set & we have not done a mass update, we need to do so now
			if ((location != "" || overstock != "") && !mass_update)
				update_inv_location();

			//scroll to top (if not currently at the top)
			//var minimum_top_pos = u.eid("inventory_results_filter").getBoundingClientRect().top;
			//window.scrollTo(minimum_top_pos, 0);

			//initialize array to hold info we need
			matching_inventory = [];

			//IMPORTANT TO KNOW review inv_locations table to understand how this works (will be held in array inv_locations[] in javascript)
			//step 1 = filter by shop (will always have something)
			if (shop == "All Shops")
				matching_inventory = inv_locations;
			else {

				matching_inventory = inv_locations.filter(function(p) {
					return p.shop.includes(shop);
				});
			}

			//step 2 = filter both other criteria (can do this all at once)
			//filter differently depending on how inventory_exact_part is check
			if (u.eid("inventory_exact_part").checked) {

				matching_inventory = matching_inventory.filter(function(p) {

					return (part == "" || p.partNumber.toLowerCase() == part.toLowerCase()) && //matches user entered part number or no part entered
						(category == "" || p.category.toLowerCase().includes(category.toLowerCase())) && //matches user entered category or no category
						(location == "" || p.primary_location.toLowerCase().includes(location.toLowerCase())) && //matches user entered part number or no part entered
						(overstock == "" || p.overstock_location.toLowerCase().includes(overstock.toLowerCase())) && //matches user entered part number or no part entered
						(description == "" || p.description.toLowerCase().includes(description.toLowerCase())) && //matches user entered part number or no part entered
						(manufacturer == "" || p.manufacturer.toLowerCase().includes(manufacturer.toLowerCase())); //matches user entered part number or no part entered
				});
			} else {
				matching_inventory = matching_inventory.filter(function(p) {

					return (part == "" || p.partNumber.toLowerCase().includes(part.toLowerCase())) && //matches user entered part number or no part entered
						(category == "" || p.category.toLowerCase().includes(category.toLowerCase())) && //matches user entered category or no category
						(location == "" || p.primary_location.toLowerCase().includes(location.toLowerCase())) && //matches user entered part number or no part entered
						(overstock == "" || p.overstock_location.toLowerCase().includes(overstock.toLowerCase())) && //matches user entered part number or no part entered
						(description == "" || p.description.toLowerCase().includes(description.toLowerCase())) && //matches user entered part number or no part entered
						(manufacturer == "" || p.manufacturer.toLowerCase().includes(manufacturer.toLowerCase())); //matches user entered part number or no part entered
				});
			}

			//step 3 = order by header clicked (if not null)
			if (inv_filter_type != "") {

				//init "filter_by" default to shop
				var filter_by = "shop";

				//go through possible ways to sort array
				if (inv_filter_type == "Shop")
					filter_by = "shop";
				else if (inv_filter_type == "Part #")
					filter_by = "partNumber";
				else if (inv_filter_type == "Category")
					filter_by = "category";
				else if (inv_filter_type == "Description")
					filter_by = "description";
				else if (inv_filter_type == "Manufacturer")
					filter_by = "manufacturer";
				else if (inv_filter_type == "Last Count Date")
					filter_by = "lastCount";
				else if (inv_filter_type == "Shop On-Hand")
					filter_by = "stock";
				else if (inv_filter_type == "Allocated")
					filter_by = "allocated";
				else if (inv_filter_type == "Pick Location")
					filter_by = "primary_location";
				else if (inv_filter_type == "Overstock Location")
					filter_by = "overstock_location";
				else if (inv_filter_type == "Last Unit Cost")
					filter_by = "last_cost";
				else if (inv_filter_type == "Last Activity")
					filter_by = "last_activity";

				//filter matching_inventory (depending on if we need to sort by strings AND ascending/descending)
				if (inv_filter_type == "Shop On-Hand" || inv_filter_type == "Allocated" || inv_filter_type == "Last Unit Cost" || inv_filter_type == "Min Stock Level") {

					if (inv_filter_ascending)
						matching_inventory.sort((a, b) => a[filter_by] - b[filter_by]);
					else
						matching_inventory.sort((a, b) => b[filter_by] - a[filter_by]);

				} else {

					if (inv_filter_ascending)
						matching_inventory.sort((a, b) => a[filter_by].localeCompare(b[filter_by]));
					else
						matching_inventory.sort((a, b) => b[filter_by].localeCompare(a[filter_by]));

				}
			}

			//get user selected amount
			var filter_len = u.eid("inventory_results_filter").value;

			//if show all, set equal to length of inv_locations, otherwise convert to integer
			if (filter_len == "Show All")
				filter_len = inv_locations.length;
			else
				filter_len = parseInt(filter_len);

			//limit # of results to user selected amount
			var loop_length = Math.min(matching_inventory.length, filter_len);

			//get table, init index
			var table = u.eid("inventory_main_table").getElementsByTagName("tbody")[0],
				index;

			//insert new row at bottom of table for each matching quote
			for (var i = 0; i < loop_length; i++) {

				//update any attributes needed
				matching_inventory[i] = update_attributes(matching_inventory[i]);

				//run function for new row
				//skip inserting IF 
				insert_new_inventory_row(table, matching_inventory[i]);
			}

			//get total values
			get_total_values(matching_inventory);

		}

		//global to help create inventory table in js (used in insert_new_inventory_row)
		const inventory_keys = ['show_more_info', 'shop', 'partNumber', 'category', 'description', 'manufacturer',
			'last_cost', 'stock', 'allocated', 'lastCount', 'primary_location',
			'overstock_location', 'last_activity', 'make_adjustment',
			'assign_reels', 'transfer'
		];

		//global to determine if part category requires reels
		const reel_categories = <?= json_encode($reel_categories); ?>;

		//handles inserting new row into inventory table
		//param 1 = table to be inserted To
		//param 2 = row of inv_locations db table
		function insert_new_inventory_row(table, part) {

			//insert new row
			var row = table.insertRow(-1);
			row.classList.add("temp_inv_row");

			if (part.allocated > 0)
				console.log(part);

			//cycle through inventory_keys array and generate table row
			for (var i = 0; i < inventory_keys.length; i++) {

				//insert new cell
				var cell = row.insertCell(i);

				//two scenarios right now. 1) regular input field that is readonly. 2) button that needs to be clicked on
				//first, check if we need to create a button
				if (inventory_keys[i] == "make_adjustment" || inventory_keys[i] == "assign_reels" ||
					inventory_keys[i] == "transfer" || inventory_keys[i] == "show_more_info") {

					//create button & add class (will control event handler) event handler at following:
					//ctrl + f $(document).on('click', '.make_adjustment'
					//ctrl + f $(document).on('click', '.assign_reels'
					var input = document.createElement("button");
					input.classList.add(inventory_keys[i]);

					//update inner html based on key
					if (inventory_keys[i] == "make_adjustment")
						input.innerHTML = "Make Adjustment";
					else if (inventory_keys[i] == "transfer")
						input.innerHTML = "Transfer";
					else if (inventory_keys[i] == "show_more_info")
						input.innerHTML = "+";
					else if (inventory_keys[i] == "assign_reels") {
						input.innerHTML = "Assign Reels";

						//lock if this category does not apply for reels
						if (!reel_categories.includes(part.category))
							input.disabled = true;
					}
				}
				//for descriptions, use textarea
				else if (inventory_keys[i] == "lastCount" || inventory_keys[i] == "last_activity") {

					//create input field, add value and any other attributes
					var input = document.createElement("input");
					input.readOnly = true;
					input.classList.add(inventory_keys[i]);

					//if null or blank, skip next part
					if (part[inventory_keys[i]] == null || part[inventory_keys[i]] == "")
						input.value = "";
					else {
						//re-format date
						var month = part[inventory_keys[i]].substr(5, 2);
						var day = part[inventory_keys[i]].substr(8, 2);
						var year = part[inventory_keys[i]].substr(0, 4);

						//output to user
						input.value = month + "/" + day + "/" + year;
					}

				}
				//last, place all other cases
				else {

					//create input field, add value and any other attributes
					var input = document.createElement("input");
					input.readOnly = true;
					input.classList.add(inventory_keys[i])
					input.value = part[inventory_keys[i]];

					// trim value to remove spaces in partNumbers
					//if (inventory_keys[i] == "partNumber")
					//	input.value = input.value.trim();

					//if this is the allocated, lets add title so users can see where these are
					if (inventory_keys[i] == "allocated")
						input.title = part.allocated_mo_ids;

				}

				//append to cell
				cell.append(input);

			}
		}

		//handles updating any attributes found in invreport but not inv_locations (reference db tables)
		//param 1 = part #
		function update_attributes(part) {

			//get allocated # & allocated mo's
			var allocated_info = get_allocated(part.partNumber, part.shop);
			part.allocated = allocated_info['sum'];
			part.allocated_mo_ids = allocated_info['mo_ids'];

			//update physical locations
			var temp_location = get_physical_location(part.partNumber, part.shop);
			part.primary_location = temp_location['primary'];
			part.overstock_location = temp_location['overstock'];

			//check if null, set to 0
			if (part.stock == null)
				part.stock = 0;
			else if (part.allocated == null)
				part.allocated = 0;

			//add 'updated' value (so we don't have to do it again)
			part.updated = true;

			return part;
		}

		//handles update physical location for partNumber
		//returns object { primary: '', overstock: ''}
		//param 1 = part # we are looking for
		//param 2 = shop we are looking for
		function get_physical_location(part, shop) {

			//filter out //filter out physical_locations related to specific part # and shop
			var targ_locations = physical_locations.filter(function(p) {
				return p.shop == shop && p.partNumber.toLowerCase() == part.toLowerCase();
			});

			//init primary and overstock locations to be returned
			var primary = "",
				overstock = "";

			//loop through target locations, create string for primary & overstock
			for (var i = 0; i < targ_locations.length; i++) {

				//check if primary or overstock
				if (parseInt(targ_locations[i].prime))
					primary += targ_locations[i].location + ", ";
				else
					overstock += targ_locations[i].location + ", ";

			}

			//remove last 2 characters from each if non-empty
			if (primary != "")
				primary = primary.substr(0, primary.length - 2);

			if (overstock != "")
				overstock = overstock.substr(0, overstock.length - 2);

			//return as object
			return {
				primary: primary,
				overstock: overstock
			}
		}

		/**@author Alex Borchers
		 * handles getting allocated number for part based on part #
		 * 
		 * @param part {string}
		 * @param part {string}
		 * @returns {object} contains total allocated and list of mo_ids
		 */
		function get_allocated(part, shop) {

			//filter pq_detail based on inventory part and get sum of parts
			allocated_parts = pq_detail.filter(function(p) {
				return p.part_id.toLowerCase() == part.toLowerCase() && p.status == "Pending" && p.decision == shop;
			});

			//init sum to return
			var sum = 0,
				mo_ids = "";

			//loop through allocated parts & add q_allocated to sum
			for (var i = 0; i < allocated_parts.length; i++) {
				sum += parseInt(allocated_parts[i].q_allocated);
				mo_ids += allocated_parts[i].mo_id + "\n";
			}

			return {
				sum: sum,
				mo_ids: mo_ids
			};
		}

		//handles getting total values (shop & individual) based on searched results
		// param 1 = inventory that we are looking for
		// param 2 = shop that we are looking for
		function get_total_values(matching_inventory, shop) {

			//init total value
			var total_value = 0;

			//loop through matching results and add calculate totals
			matching_inventory.forEach(part => {

				//use stock & average unit cost to calculate totals
				total_value += parseInt(part.stock) * parseFloat(part.cost)
			});

			//set total value
			u.eid("filtered_shop_value").value = accounting.formatMoney(total_value);

			//do the same for part value (only if searching for exact part)
			/*
			if (u.eid("inventory_exact_part").checked){

				//loop through matching results (passed in function parameters)
				matching_inventory.forEach(part => {
					
					//use stock & average unit cost to calculate totals
					part_value += parseInt(part.stock) * parseFloat(part.cost)
				});

				u.eid("individual_shop_value").value = accounting.formatMoney(part_value);

			}
			else{
				u.eid("individual_shop_value").value = "Need exact part.";
			}*/
		}

		//generate cycle count based on user entered info
		function generate_cycle_count() {

			//get qty and shop
			var qty = parseInt(u.eid("cycle_count_qty").value),
				shop = u.eid("cycle_count_shop").value;

			//check to make sure both are valid
			if (qty == "" || shop == "") {
				alert("Please enter a shop & quantity");
				return;
			}

			//filter out parts for this shop that have not been counted in certain duration
			target_parts = inv_locations.filter(function(p) {
				return p.shop.includes(shop) && p.shop != "OMA-WS" && p.stock > 0 && check_last_count(p.lastCount);
			});

			//check to make sure we are not stuck in infinite loop
			if (target_parts.length < qty) {
				alert("Error: The qty requested exceeds the amount of parts that match your defined criteria.");
				return;
			}

			//randomly generate qty amount of parts within this target set
			var indexes = [];
			while (indexes.length < qty) {
				var r = Math.floor(Math.random() * target_parts.length) + 1;
				if (indexes.indexOf(r) === -1) indexes.push(r);
			}

			//initialize csv content
			let csvContent = "data:text/csv;charset=utf-8,";

			//set first row as the title of the report
			var today = new Date();
			var ampm = today.getHours() >= 12 ? 'pm' : 'am';
			var today_string = (today.getMonth() + 1) + "/" + today.getDate() + "/" + today.getFullYear() + " @ " + today.getHours() + ":" + today.getMinutes() + ampm;
			var header_string = "Cycle Count Report - " + qty + " items - " + today_string;
			csvContent += header_string + "\r\n";

			//default # of locations
			var num_locs = 10;

			//add headers to CSV
			csvContent += "Shop,Part,Shop On Hand";

			//loop through number of locations and add to array header
			for (var i = 1; i <= num_locs; i++) {
				csvContent += ",Location " + i + ",Count";
			}

			//push CSV to next row
			csvContent += "\r\n";

			//loop through indexes and push to CSV for cycle count
			for (var i = 0; i < indexes.length; i++) {

				//push shop, part#, and current on-hand to csv
				csvContent += scrub_string(target_parts[indexes[i]].shop) + ",";
				csvContent += scrub_string(target_parts[indexes[i]].partNumber) + ",";
				csvContent += scrub_string(target_parts[indexes[i]].stock) + ",";

				//filter out physical_locations related to specific part # and shop
				targ_locations = physical_locations.filter(function(p) {
					return p.shop == target_parts[indexes[i]].shop && p.partNumber.toLowerCase() == target_parts[indexes[i]].partNumber.toLowerCase();
				});

				//loop through target locations, and add to CSV for each location
				for (var j = 0; j < targ_locations.length; j++) {
					csvContent += scrub_string(targ_locations[j].location) + ",";
					csvContent += scrub_string(targ_locations[j].quantity) + ",";
				}

				//remove last comma, set to next row
				csvContent = csvContent.substr(0, csvContent.length - 1);

				csvContent += "\r\n";
			}

			//set encoded uri for download, push to link, name and force download
			var encodedUri = encodeURI(csvContent);
			var link = u.eid("hold_download");
			link.setAttribute("href", encodedUri);

			//get date string to add to file name
			var date_string = "(" + (today.getMonth() + 1) + "." + today.getDate() + "." + today.getFullYear() + ")";
			link.setAttribute("download", "Random Cycle Count " + date_string + ".csv");
			link.click();
		}

		//handles checking if last update occured within last 30 days
		function check_last_count(check_date) {

			// get reference (then), today (now), and time between
			var then = new Date(check_date);
			var now = new Date();
			var msBetweenDates = Math.abs(then.getTime() - now.getTime());

			// convert ms to days                 	hour   min  sec   ms
			var daysBetweenDates = msBetweenDates / (24 * 60 * 60 * 1000);

			//if larger than 30 days, add to list
			if (daysBetweenDates > 30)
				return true;


			console.log(check_date);
			return false;

		}

		//handle sanitizing string
		function scrub_string(targ) {

			//check for blanks
			if (targ == "" || targ == null)
				return " ";

			//used to remove unfriendly characters
			var regexp = new RegExp('#', 'g');
			targ = targ.replace(regexp, '');
			targ = targ.replace(/,/g, ';');
			targ = targ.replace('\r', '');
			targ = targ.replace(/[\r\n]/gm, '');
			targ = targ.trim();

			return targ;
		}

		//handles updating cycle count via CSV file (called when a CSV file is attached)
		function update_cycle_count() {

			//grab file from id
			var file = $('#update_cycle_count_csv')[0].files[0];

			//initialize form elements
			var fd = new FormData();

			//add file
			fd.append('file', file);

			//add user info
			fd.append('user_info', JSON.stringify(user_info));

			$.ajax({
				url: 'terminal_warehouse_cycleCount_csv.php',
				type: 'POST',
				processData: false,
				contentType: false,
				data: fd,
				success: function(response) {

					//check for error message
					if (response != "") {
						alert("Parts may have not been updated successfully: " + response);
						console.log(response);
						u.eid("update_cycle_count_csv").value = null;
						return;
					} else {
						//reload page
						alert("The parts have been successfully updated.");
						window.location.reload();
					}
				}
			});
		}

		//handles showing dialog to adjust inventory 
		$(document).on('click', '.make_adjustment', function() {

			//work to part # and shop
			var td = this.parentNode;
			var tr = td.parentNode;
			var partNumber = tr.childNodes[2].childNodes[0].value;
			var shop = tr.childNodes[1].childNodes[0].value;
			var phys_location = tr.childNodes[10].childNodes[0].value;

			//update part # and location in dialog
			u.eid("adjustment_shop").value = shop;
			u.eid("adjustment_part").value = partNumber;

			//get index in inventory array (check invreport table)
			var index = inv_locations.findIndex(object => {
				return object.partNumber.toLowerCase() == partNumber.toLowerCase() && object.shop == shop;
			});

			//if we find match, update UOM & current On-Hand
			if (index != -1) {
				u.eid("adjustment_uom").value = inv_locations[index]['um'];
				u.eid("adjustment_on_hand").value = inv_locations[index]['stock'];
				u.eid("adjustment_new_on_hand").value = inv_locations[index]['stock']; //default new to current on_hand
			}

			//remove previous locations
			document.querySelectorAll('.temp_loc_row').forEach(function(a) {
				a.remove();
			})

			//check and add any existing physical locations
			add_physical_locations(index, shop);

			//open dialog box
			$("#inventory_adjustment_dialog").dialog({
				width: "auto",
				height: "auto",
				dialogClass: "fixedDialog"
			});

			//update on_hand totals
			update_on_hand();

		});

		//handles adding rows to table insdie of inventory_adjustment_dialog box related to a parts physical location
		function add_physical_locations(index, shop) {

			//if index -1, do nothing (no match found in inventory)
			if (index == -1)
				return;

			console.log(index);

			//filter out physical_locations related to specific part # and shop
			var targ_locations = physical_locations.filter(function(p) {
				return p.shop == shop && p.partNumber.toLowerCase() == inv_locations[index].partNumber.toLowerCase();
			});

			//loop through target locations, and add a row to the table for each
			for (var i = 0; i < targ_locations.length; i++) {
				add_physical_location_row(targ_locations[i]);
			}
		}

		//handles adding 1 row to physical location table
		//param 1 = current location (relates to row in inv_physical_locations)
		//default to "new" if null (inserts blank row)
		function add_physical_location_row(curr_loc = "new") {

			//get table
			var table = u.eid("inventory_adjustment_location_table");

			//insert new row
			var row = table.insertRow(table.rows.length - 1);
			row.classList.add("temp_loc_row");

			//remove button (always first cell)
			var cell = row.insertCell(0);
			cell.innerHTML = "<button onclick = 'remove_this_row(this)'>✖</button>"

			//check curr_loc (treat differently for 'new')
			if (curr_loc != "new") {

				//physical location
				var cell = row.insertCell(1);
				cell.innerHTML = "<input value = '" + curr_loc.location + "' class = 'adjustment_physical_location'>";

				//on hand
				var cell = row.insertCell(2);
				cell.innerHTML = "<input type = 'number' value = '" + curr_loc.quantity + "' class = 'adjustment_on_hand' onchange = 'update_on_hand()'>";

				//primary designation
				var cell = row.insertCell(3);

				//set checked based on saved value
				if (parseInt(curr_loc.prime))
					cell.innerHTML = "<input type = 'checkbox' class = 'adjustment_primary' checked>"
				else
					cell.innerHTML = "<input type = 'checkbox' class = 'adjustment_primary'>"

			} else {

				//physical location
				var cell = row.insertCell(1);
				cell.innerHTML = "<input class = 'adjustment_physical_location'>"

				//on hand
				var cell = row.insertCell(2);
				cell.innerHTML = "<input type = 'number' value = '0' class = 'adjustment_on_hand' onchange = 'update_on_hand()'>";

				//primary designation
				var cell = row.insertCell(3);
				cell.innerHTML = "<input type = 'checkbox' class = 'adjustment_primary'>"
			}
		}

		//handles removing row from table
		// param 1 = 'this' (the button clicked)
		// param 2 = type (certain instances, we want to prompt user to validate)
		function remove_this_row(targ, type = "NA") {

			//check type to see if anything else is needed
			if (type == "reels") {

				//prompt are you sure? if yes, send to ajax to remove from 
				var prompt = "Are you sure you would like to remove this reel? (This cannot be undone)";

				//send message to user
				if (confirm(prompt)) {
					//if yes, work back to reel ID and remove from db
					//<button>.<td>.<tr>.[reel_id]<td>.[reel_id]<input>.value
					var reel_id = targ.parentNode.parentNode.childNodes[1].childNodes[0].value

					// check if bulk
					if (reel_id.includes("BR"))
						reel_id = reel_id.substr(2);

					remove_reel(reel_id);
				}
				//if user reject, return without doing anything
				else
					return;
			}

			//remove row (path below)
			//<button>.<td>.<tr>.remove()
			targ.parentNode.parentNode.remove();

		}

		//handles removing reel ID from database
		// param 1 = reel id that we want to remove (should match id in inv_reel_assignments)
		function remove_reel(reel_id) {

			//init form data
			var fd = new FormData();

			//push any info required to form 
			fd.append('reel_id', reel_id);

			//add tell variable
			fd.append('tell', 'remove_reel');

			$.ajax({
				url: 'terminal_warehouse_helper.php',
				type: 'POST',
				processData: false,
				contentType: false,
				data: fd,
				success: function(response) {

					//check for error response
					if (response != "") {
						alert(response);
						console.log(response);
						return;
					}

					console.log(response);

					//find index in global and remove
					var index = reel_assignments.findIndex(object => {
						return object.id == reel_id;
					});

					//remove
					reel_assignments.splice(index, 1);

					//alert users of successful changes
					alert("This reel has been removed.");
					return;

				}
			});
		}

		//handles updating on hand total
		function update_on_hand() {

			//loop through 'adjustment_on_hand' class and add to total sum
			var sum = 0;

			document.querySelectorAll('.adjustment_on_hand').forEach(function(a) {
				sum += parseInt(a.value);
			})

			//update 'on_hand_total' to new sum
			u.eid("on_hand_total").innerHTML = sum;

			//get current new on hand value
			var new_on_hand = parseInt(u.eid("adjustment_new_on_hand").value);

			//update background compared to 'adjust to' value
			if (sum != new_on_hand)
				u.eid("on_hand_row").style.background = "#fdb2b2";
			else
				u.eid("on_hand_row").style.background = "#a5e292";
		}

		//handles updating inventory on-hand within dialog box
		function update_inventory_on_hand() {

			//get info saved in adjustment dialog box input fields
			var part = u.eid("adjustment_part").value,
				shop = u.eid("adjustment_shop").value,
				on_hand = u.eid("adjustment_on_hand").value,
				new_on_hand = u.eid("adjustment_new_on_hand").value;

			//loop through adjustment_physical_location & adjustment_on_hand classes and push to array
			var phys_locations = [];
			var loc_class = u.class("adjustment_physical_location"),
				loc_on_hand_class = u.class("adjustment_on_hand"),
				loc_primary = u.class("adjustment_primary");

			for (var i = 0; i < loc_class.length; i++) {

				//only push if not blank
				if (loc_class[i].value != "") {
					phys_locations.push({
						loc: loc_class[i].value,
						on_hand: loc_on_hand_class[i].value,
						primary: loc_primary[i].checked
					})
				}
			}

			//init form data
			var fd = new FormData();

			//push any info required to form 
			fd.append('part', part);
			fd.append('shop', shop);
			fd.append('on_hand', on_hand);
			fd.append('new_on_hand', new_on_hand);

			//serialize any arrays and add to form
			fd.append('phys_locations', JSON.stringify(phys_locations));

			//add user info
			fd.append('user_info', JSON.stringify(user_info));

			//add tell variable
			fd.append('tell', 'adjust_on_hand');

			$.ajax({
				url: 'terminal_warehouse_helper.php',
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

					//update physical locations
					physical_locations = $.parseJSON(response);

					//update on-hand for part & shop
					//get index in inv_locations (matches inv_locations db table)
					var inv_loc_index = inv_locations.findIndex(object => {
						return object.partNumber.toLowerCase() == part.toLowerCase() && object.shop == shop;
					});

					//check cost affect to see what error message to display
					//if this affects inventory over some threshold ($200), let user know this will be approved before adjusting
					var abs_quantity = Math.abs(parseInt(on_hand) - parseInt(new_on_hand));
					var cost_affect = parseFloat(inv_locations[inv_loc_index].cost) * abs_quantity;

					if (user_info['allocations_admin'] != "checked" && cost_affect >= 200) {
						alert("[MSG] Logistics Administration must approve any adjustments submitted over $200. This adjustment request has been submitted.");
					} else {
						//alert users of successful changes
						alert("An Inventory Adjustment has been made.");

						//update inventory & update inv_locations
						inv_locations[inv_loc_index]['stock'] = new_on_hand;
						inv_locations[inv_loc_index] = update_attributes(inv_locations[inv_loc_index]);
					}

					//close dialog
					$("#inventory_adjustment_dialog").dialog('close');

					//re-filter parts
					filter_inventory();
				}
			});
		}

		//handles showing dialog to assign reels
		$(document).on('click', '.assign_reels', function() {

			//work to part # and shop
			var td = this.parentNode;
			var tr = td.parentNode;
			var partNumber = tr.childNodes[2].childNodes[0].value;
			var shop = tr.childNodes[1].childNodes[0].value;
			var on_hand = tr.childNodes[7].childNodes[0].value;
			var phys_location = tr.childNodes[10].childNodes[0].value;

			//update part # and location in dialog
			u.eid("reels_shop").value = shop;
			u.eid("reels_part").value = partNumber;
			u.eid("reels_on_hand").value = on_hand;

			//remove previous locations
			document.querySelectorAll('.temp_reel_row').forEach(function(a) {
				a.remove();
			})

			//check and add any existing physical locations
			add_reels(partNumber, shop);

			//get screen height
			var screen_height = $(window).height();

			//open dialog box
			$("#inventory_reels_dialog").dialog({
				width: "auto",
				height: "auto",
				dialogClass: "fixedDialog",
				maxHeight: screen_height - 100
			});

			//update on_hand totals
			//update_on_hand();

		});

		//handles adding rows to table insdie of inventory_adjustment_dialog box related to a parts physical location
		function add_reels(part, shop) {

			//filter out reel_assignments related to specific part # and shop
			targ_reels = reel_assignments.filter(function(reel) {
				return reel.shop == shop && reel.partNumber.toLowerCase() == part.toLowerCase();
			});

			//loop through target locations, and add a row to the table for each
			for (var i = 0; i < targ_reels.length; i++) {
				add_reel_row(targ_reels[i]);
			}

			//calculate reels total
			get_reel_totals();
		}

		/**
		 * handles assigning a new reel ID
		 * @param {int} type (determines type of reel, will be 0=regular or 1=bulk)
		 * 
		 * @result new reel assigned in inv_reel_assignments db table, returned to user and inserted to dialog
		 * @returns null
		 */
		function assign_reel_id(type) {

			//get shop & part
			var shop = u.eid("reels_shop").value;
			var part = u.eid("reels_part").value;

			//init form data
			var fd = new FormData();

			//push any info required to form 
			fd.append('part', part);
			fd.append('shop', shop);

			//add tell & type variable
			fd.append('tell', 'assign_reels');
			fd.append('type', type);

			$.ajax({
				url: 'terminal_warehouse_helper.php',
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

					//create current reel and add to global
					var curr_reel = {
						id: response,
						bulk: type,
						partNumber: part,
						shop: shop,
						quantity: 0,
						location: "",
						status: "Available"
					};

					//add to global
					reel_assignments.push(curr_reel);

					//add reel to table
					add_reel_row(curr_reel);

					//alert users of successful changes
					alert("A new reel has been assigned for this part. Please update the quantity and location for this reel.");
				}
			});
		}

		//handles adding 1 row to physical location table
		//param 1 = current location (relates to row in inv_physical_locations)
		//default to "new" if null (inserts blank row)
		function add_reel_row(curr_reel) {

			//get table
			var table = u.eid("inventory_reels_table");

			//insert new row
			var row = table.insertRow(table.rows.length - 1);
			row.classList.add("temp_reel_row");

			//remove button (always first cell)
			var cell = row.insertCell(0);
			cell.innerHTML = "<button onclick = 'remove_this_row(this, \"reels\")'>✖</button>";

			//reel ID
			var cell = row.insertCell(1);

			//treat 'bulk' reel ids differently
			if (parseInt(curr_reel.bulk) == 1)
				cell.innerHTML = "<input class = 'reel_id bulk_reed_id' value = 'BR" + curr_reel.id + "' readonly>";
			else
				cell.innerHTML = "<input class = 'reel_id' value = '" + curr_reel.id + "' readonly>";

			//quantity
			var cell = row.insertCell(2);
			cell.innerHTML = "<input type = 'number' value = '" + curr_reel.quantity + "' class = 'reel_quantity' onchange = 'get_reel_totals()'>";

			//physical location
			var cell = row.insertCell(3);
			cell.innerHTML = "<input class = 'reel_location' value = '" + curr_reel.location + "'>";

		}

		/** Updates id 'reels_total' with total quantity for current group of reels
		 * 
		 * @returns void
		 */
		function get_reel_totals() {

			//init var to hold total
			var total = 0;

			//loop through class 'reel_quantity' and add up all values
			document.querySelectorAll('.reel_quantity').forEach(function(a) {
				total += parseInt(a.value);
			})

			//update holder for reel_quantity
			u.eid("reels_total").innerHTML = total;

			//get current new on hand value
			var on_hand = parseInt(u.eid("reels_on_hand").value);

			//update background compared to 'adjust to' value
			if (total != on_hand)
				u.eid("reels_total_row").style.background = "#fdb2b2";
			else
				u.eid("reels_total_row").style.background = "#a5e292";
		}

		//handles updating inventory on-hand within dialog box
		function update_reels() {

			//get info saved in adjustment dialog box input fields
			var part = u.eid("reels_part").value,
				shop = u.eid("reels_shop").value;

			//loop through adjustment_physical_location & adjustment_on_hand classes and push to array
			var reel_detail = [];
			var reels = u.class("reel_id"),
				quantity = u.class("reel_quantity"),
				locations = u.class("reel_location");

			for (var i = 0; i < reels.length; i++) {

				//if reel is BR, chop off first two letters (Reels are saved with 2 digit prefix codes)
				var use_id = reels[i].value;
				if (use_id.includes("BR"))
					use_id = use_id.substr(2);

				//push each reel (reels, quantity, & locations are in line)
				reel_detail.push({
					id: use_id,
					quantity: quantity[i].value,
					loc: locations[i].value
				});
			}

			//init form data
			var fd = new FormData();

			//push any info required to form 
			fd.append('part', part);
			fd.append('shop', shop);

			//serialize any arrays and add to form
			fd.append('reel_detail', JSON.stringify(reel_detail));

			//add tell variable
			fd.append('tell', 'update_reels');

			$.ajax({
				url: 'terminal_warehouse_helper.php',
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

					//update globals if successful
					for (var i = 0; i < reel_detail.length; i++) {

						//get index of reel id in reel_assignments array
						var index = reel_assignments.findIndex(object => {
							return object.id == reel_detail[i].id;
						});

						//update global info
						reel_assignments[index].quantity = reel_detail[i].quantity;
						reel_assignments[index].location = reel_detail[i].loc;

					}

					//alert users of successful changes
					alert("Reels have been saved.");

					//close dialog
					$("#inventory_reels_dialog").dialog('close');
				}
			});
		}

		//handles showing last_activity box on click
		$(document).on('click', '.last_activity', function() {

			//work to part #
			var td = this.parentNode;
			var tr = td.parentNode;
			var partNumber = tr.childNodes[2].childNodes[0].value;

			//remove previous locations
			document.querySelectorAll('.activity_row').forEach(function(a) {
				a.remove();
			})

			//check and add any existing physical locations
			add_last_activity(partNumber);

			//open dialog box
			open_dialog('activity_dialog');

		});

		//handles adding last activity to activity_table inside table
		//param 1 = partNumber
		function add_last_activity(part) {

			//find all entries in logs
			var logs = inventory_logs.filter(function(log) {
				return log.partNumber.toLowerCase() == part.toLowerCase();
			});

			//get table to add to
			var table = u.eid("activity_table").getElementsByTagName('tbody')[0];

			//loop through logs and add to table
			for (var i = 0; i < logs.length; i++) {

				add_last_activity_row(table, logs[i]);

			}
		}

		//handles adding 1 row for activity table
		//param 1 = table to add to
		//param 2 = log
		function add_last_activity_row(table, log) {

			//init arrays to be used
			var heads = ['type', 'partNumber', 'UM', 'description', 'user_name', 'time_stamp'],
				types = [{
						key: 'PA',
						full: 'Part Adjustment'
					},
					{
						key: 'IA',
						full: 'Inventory Adjustment'
					},
					{
						key: 'CC',
						full: 'Cycle Count'
					},
					{
						key: 'WP',
						full: 'Warehouse Processing'
					},
					{
						key: 'ME',
						full: 'Material Entry'
					},
					{
						key: 'REC',
						full: 'Material Received'
					},
					{
						key: 'PO',
						full: 'Purchase Order'
					},
					{
						key: 'LU',
						full: 'Location Update'
					}
				]

			//create new row
			var row = table.insertRow(-1);
			row.classList.add("activity_row")

			//loop through heads and add to table
			for (var i = 0; i < heads.length; i++) {

				//create cell and add info
				var cell = row.insertCell(i);

				//if type, change for full type description
				if (heads[i] == "type") {
					//get index
					var t_index = types.findIndex(object => {
						return object.key == log.type;
					});

					//update html
					cell.innerHTML = types[t_index].full;
				}
				//if UM, get from database
				else if (heads[i] == "UM") {
					//get index
					var i_index = inv_locations.findIndex(object => {
						return object.partNumber.toLowerCase() == log.partNumber.toLowerCase();
					});

					//update html
					cell.innerHTML = inv_locations[i_index].um;
				}
				// if timestamp, convert to local
				else if (heads[i] == 'time_stamp')
					cell.innerHTML = utc_to_local(log[heads[i]]);
				else
					cell.innerHTML = log[heads[i]];

			}

		}

		//init filters as a global
		var activity_filters = [
			[],
			[],
			[],
			[],
			[],
			[]
		];

		//listen to inputs on change
		$(".activity_filter").keyup(function() {

			//grab rows and column number 
			var $rows = $('#activity_table tbody tr');
			//var col = this.id.replace(/[a-z]/gi, "") - 1;
			var col = $(this).closest("td").index();

			activity_filters[col] = $(this).val().trim().replace(/ +/g, ' ').toLowerCase().split(",").filter(l => l.length);
			$rows.show();

			if (activity_filters.some(f => f.length)) {
				$rows.filter(function() {
					var texts = $(this).children().map((i, td) => $(td).text().replace(/\s+/g, ' ').toLowerCase()).get();
					return !texts.every((t, col) => {
						return activity_filters[col].length == 0 || activity_filters[col].some((f, i) => t.indexOf(f) >= 0);
					})
				}).hide();
			}
		});

		//handles showing dialog to make a part transfer
		$(document).on('click', '.transfer', function() {

			//work to part # and shop
			var td = this.parentNode;
			var tr = td.parentNode;
			var partNumber = tr.childNodes[2].childNodes[0].value;
			var shop = tr.childNodes[1].childNodes[0].value;
			var on_hand = tr.childNodes[7].childNodes[0].value;
			var allocated = tr.childNodes[8].childNodes[0].value;

			//update part # and location in dialog
			u.eid("transfer_part").value = partNumber;

			//update all potential shop inputs
			document.querySelectorAll('.transfer_update_shop').forEach(function(a) {
				a.value = shop;
			})

			//update max 'transfer_from' based on shop on-hand
			//u.eid("transfer_from_qty").max = parseInt(on_hand) - parseInt(allocated);

			//show request dialog, hide transfer_options
			u.eid("transfer_options_div").style.display = "block";

			document.querySelectorAll('.transfer_options').forEach(function(a) {
				a.style.display = "none";
			})

			//open dialog box
			$("#transfer_dialog").dialog({
				width: "auto",
				height: "auto",
				dialogClass: "fixedDialog"
			});

		});

		//handles showing/hiding info based on button clicked
		function transfer_options(type) {

			//hide options
			u.eid("transfer_options_div").style.display = "none";

			//show option selected
			u.eid(type + "_transfer_div").style.display = "block";

			//update_transfer if type = make, use drop-down as parameter
			if (type == "make")
				update_transfer(u.eid("make_transfer_options"));

		}

		//handles updating 'make transfer' options when user selects different type of transfer
		function update_transfer(targ) {

			//set all options to no display
			document.querySelectorAll('.make_transfer_table').forEach(function(a) {
				a.style.display = "none";
			})

			//use targ.value and update all classes related 
			document.querySelectorAll('.' + targ.value + '_tables').forEach(function(a) {
				a.style.display = "block";
			})

			//if this is location to location, update the location drop-downs
			if (targ.value == "loc_to_loc")
				insert_transfer_physical_locations()

		}

		//handles inserting physical locations to the drop-downs in the transfer menu
		function insert_transfer_physical_locations() {

			//get part & shop to build drop-downs from
			var shop = u.eid("make_transfer_from").value,
				part = u.eid("transfer_part").value;

			//get physical locations that match the criteria defined 
			targ_locations = physical_locations.filter(function(p) {
				return p.shop == shop && p.partNumber.toLowerCase() == part.toLowerCase();
			});

			//remove any previous locations
			document.querySelectorAll('.transfer_physical_loc_options').forEach(function(a) {
				a.remove();
			})

			//get target select list
			var select_from = u.eid("physical_location_from"),
				select_to = u.eid("physical_location_to");

			//add new locations
			for (var i = 0; i < targ_locations.length; i++) {

				//append <option> tags to select drop-down (see create_physical_location_option())
				select_from.appendChild(create_physical_location_option(targ_locations[i].location));
				select_to.appendChild(create_physical_location_option(targ_locations[i].location));
			}
		}

		//small function that builds <option> element to be added to physical location drop down
		function create_physical_location_option(text) {

			var option = document.createElement("option");
			option.value = text;
			option.innerHTML = text;
			option.classList.add("transfer_physical_loc_options");

			return option;

		}

		//handles update ship to info based on "to (shop)" selected in material transfer dialog
		function update_transfer_to() {

			//get ship_to info
			var ship_to = u.eid("make_transfer_to").value;

			//get abbreviated shop if applicable
			var dash_loc = ship_to.indexOf("-");

			if (dash_loc != -1)
				ship_to = ship_to.substr(0, dash_loc);

			//look for shop in pw shipping locations (if we find a match, update)
			var index = pw_shipping.findIndex(object => {
				return object.abv.toLowerCase() == ship_to.toLowerCase();
			});

			if (index != -1 && ship_to != "") {
				u.eid("make_transfer_street").value = pw_shipping[index].address;
				u.eid("make_transfer_city").value = pw_shipping[index].city;
				u.eid("make_transfer_state").value = pw_shipping[index].state;
				u.eid("make_transfer_zip").value = pw_shipping[index].zip;
			} else {
				u.eid("make_transfer_street").value = "";
				u.eid("make_transfer_city").value = "";
				u.eid("make_transfer_state").value = "";
				u.eid("make_transfer_zip").value = "";
			}
		}

		//handles expanding / collapsing additional project info (on click of +/- button)
		$(document).on('click', '.show_more_info', function() {

			//work to shop & part #
			var td = this.parentNode;
			var tr = td.parentNode;
			var tbody = tr.parentNode;
			var partNumber = tr.childNodes[2].childNodes[0].value;
			var shop = tr.childNodes[1].childNodes[0].value;

			//grab show/hide symbol
			var show_hide = this.innerHTML;

			//get shop/partNumber index from inv_locations
			var index = inv_locations.findIndex(object => {
				return object.partNumber.toLowerCase() == partNumber.toLowerCase() && object.shop == shop;
			});

			//if we don't find a match, return without doing anything
			if (index == -1) {
				alert("error: no match found: (" + partNumber + ") (" + shop + ")");
				return;
			}

			//based on innerHTML, show/hide content
			if (show_hide == "+") {
				//show quote info
				get_additional_part_info(index, tr.rowIndex, tbody);
				this.innerHTML = "-";
			} else {
				//remove previous shipment info & change button +/-
				//get next row & remove it
				var nextRow = $(tr).next()[0];
				nextRow.remove();

				//update to +
				this.innerHTML = "+";
			}
		})

		//handles loading in additional quote info under a given table row
		//param 1 = index in inv_locations (see inv_locations db table)
		//param 3 = the row that the user clicked
		//param 4 = tbody to be added to
		function get_additional_part_info(index, row_index, tbody) {

			/**
			 * 
			 * The plan for this function is to create a new row that spans the entire table
			 * Within the row, we will have tables that line up next to eachother (however many needed)
			 * 
			 */

			//add row to orders table and push table to new row
			var row = tbody.insertRow(row_index - 1);
			row.classList.add("temp_inv_row");
			row.style.backgroundColor = "white";
			var add_info_cell = row.insertCell(0);
			add_info_cell.colSpan = tbody.rows[0].cells.length;

			//create 1st table (Project Owner / designer)
			var table = create_additional_info_table("first_table", index);
			add_info_cell.appendChild(table);

			//scroll tbody to show new row
			//add_info_cell.scrollIntoView({block: "nearest", behavior: "smooth"});

		}

		//handles creating tables in the additional info expansion
		//param 1 = type (see get_additional_quote_info())
		//param 2 = index (in grid - see fst_grid table)
		function create_additional_info_table(type, index) {

			//create table and add classlists
			var table = document.createElement("table");
			table.classList.add("add_info_table");

			//customize table based on type
			if (type == "first_table") {

				//create arrays to help create the first table (all rows are structured similar)
				var side_header = ["Average Unit Cost", "Min (Shop)", "Max (Shop)", "Min (" + inv_locations[index].primary_location + "): ", "Max (" + inv_locations[index].primary_location + "): "],
					input_keys = ['cost', 'min_stock', 'max_stock', 'min_primary', 'max_primary']; //need to coincide with inv_location columns

				//loop through arrays and create rows
				for (var i = 0; i < side_header.length; i++) {

					//create new row
					var row = table.insertRow(-1);

					//side header
					var cell = row.insertCell(0);
					cell.innerHTML = side_header[i];

					//input field for reference (un editable)
					var cell = row.insertCell(1);
					var input = document.createElement("input");
					input.value = inv_locations[index][input_keys[i]];
					input.classList.add(input_keys[i]);
					input.classList.add("add_info_input");

					//set input readOnly attribute based on side_header
					if (side_header[i] == "Average Unit Cost")
						input.readOnly = true;
					else if ((input_keys[i] == "min_primary" || input_keys[i] == "max_primary") && inv_locations[index].primary_location == "")
						input.readOnly = true;

					cell.appendChild(input);

					//if max (shop) make note that max & min need to be filled outerHeight
					if (input_keys[i] == "max_stock") {
						var span = document.createElement("span");
						span.innerHTML = "    <i>*If Max < Min, Max will be set to Min</i>";
						cell.appendChild(span);
					}
				}
			}

			//return completed table
			return table;
		}

		//global that handles updating minimum stock
		var update_min_max = [];

		//handles updating min stock levels on change
		$(document).on('change', '.add_info_input', function() {

			//work to part # & shop
			var add_info_td = this.parentNode;
			var add_info_tr = add_info_td.parentNode;
			var add_info_tbody = add_info_tr.parentNode;
			var add_info_table = add_info_tbody.parentNode;
			var add_info_td2 = add_info_table.parentNode;
			var tr = add_info_td2.parentNode;
			var prev_tr = $(tr).prev()[0]; //prev_tr is the row that the shop, part #, category, etc are on
			var partNumber = prev_tr.childNodes[2].childNodes[0].value;
			var shop = prev_tr.childNodes[1].childNodes[0].value;

			//find in inv_locations and update
			var index = inv_locations.findIndex(object => {
				return object.partNumber.toLowerCase() == partNumber.toLowerCase() && object.shop == shop;
			});

			//error if we find no match
			if (index == -1) {
				alert("Error finding a match for the info you are updating. Changes will not be saved.");
				return;
			}

			//get first class list for reference
			var input_key = this.classList[0];
			inv_locations[index][input_key] = this.value;

			//check if shop/partNumber combo exists in update array
			var check_index = update_min_max.findIndex(object => {
				return object.partNumber.toLowerCase() == partNumber.toLowerCase() && object.shop == shop;
			});

			if (check_index == -1)
				update_min_max.push({
					partNumber: partNumber,
					shop: shop,
					min_stock: inv_locations[index].min_stock,
					max_stock: inv_locations[index].max_stock,
					min_primary: inv_locations[index].min_primary,
					max_primary: inv_locations[index].max_primary
				})
			else
				update_min_max[check_index][input_key] = this.value;

		});

		//send ajax request, returns updated list of material orders
		function save_changes(refresh = false) {

			//update refreshing value (if this is auto, refresh is true = no mouse spinning)
			refreshing = refresh;

			//initalize form data (will carry all form data over to server side)
			var fd = new FormData();

			//add arrays to update min_stock
			fd.append('update_min_max', JSON.stringify(update_min_max));

			//add tell
			fd.append('tell', 'update_min_max');

			$.ajax({
				url: 'terminal_warehouse_helper.php',
				type: 'POST',
				processData: false,
				contentType: false,
				data: fd,
				success: function(response) {

					// check for Error
					if (response != "") {
						alert("[ERROR] Please screenshot and send to fst@piersonwireless.com. " + response);
						return;
					}

					// updates have been saved
					alert("Changes have been saved.");

					// clear update array
					update_min_max = [];

				}
			});
		}

		//handles tabs up top that toggle between divs
		function change_tabs(pageName, elmnt, color) {

			// redirect depending on user selection
			if (pageName == "inventory")
				filter_inventory();

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

			//hide table
			u.eid("mo_details").style.display = 'none';

			//if we are going back to the open tab, set the filters for closed back to nothing (so we don't re-add these every time)
			if (pageName == "Open") {
				u.eid("start_date").value = null;
				u.eid("end_date").valueAsDate = new Date();
				u.eid("search_project").value = null;
				u.eid("search_MO").value = null;
			}

			//click default (unclicks all visible buttons)
			u.eid("pq-default").click();

		}

		//windows onload
		window.onload = function() {

			// Get the element with id="defaultOpen" and click on it
			u.eid("defaultOpen").click();

		}

		// used to toggle mouse waiting (spinning) on & off during ajax request
		$(document).ajaxStart(function() {
			waiting('on');
		});

		$(document).ajaxStop(function() {
			waiting('off');
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