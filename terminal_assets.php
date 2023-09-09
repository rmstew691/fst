<?php

// Include dependencies
session_start();
include('phpFunctions.php');
include('phpFunctions_html.php');
include('constants.php');

// Load the database configuration file
require_once 'config.php';

//used to grab actual link for the current address
$actual_link = "https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

//Save current site so we can return after log in
$_SESSION['returnAddress'] = $actual_link;

//sub link
$sub_link = substr($actual_link, 0, strpos($actual_link, "home"));

//Make sure user has privileges
$query = "SELECT * FROM fst_users where email = '" . $_SESSION['email'] . "'";
$result = $mysqli->query($query);

if ($result->num_rows > 0) {
	$fstUser = mysqli_fetch_array($result);
} else {
	$fstUser['accessLevel'] = "None";
}

// Make sure user has access
sessionCheck($fstUser['accessLevel']);

// [check if user is asset_admin OR admin, if not redirect]

// Load in user directory
$directory = [];
$query = "SELECT * FROM fst_users WHERE status = 'Active' ORDER BY firstName;";
$result = mysqli_query($con, $query);
while ($rows = mysqli_fetch_assoc($result)) {
	array_push($directory, $rows['firstName'] . " " . $rows['lastName']);
}

//grab from assets from DB
$asset = [];
$query = "select * from assets order by equipment_code;";
$result = mysqli_query($con, $query);
while ($rows = mysqli_fetch_assoc($result)) {
	array_push($asset, $rows);
}

//grab from shops from DB
$shops = [];
/*$query = "select * from shops_asset order by id;";
$result = mysqli_query($con, $query);
while ($rows = mysqli_fetch_assoc($result)) {
	array_push($shops, $rows);
}*/

// Grab asset categories from DB
$asset_categories = [];
$asset_subtypes = [];
$query = "SELECT * FROM asset_categories;";
$result = mysqli_query($con, $query);
while ($rows = mysqli_fetch_assoc($result)) {
	array_push($asset_categories, $rows);
	$asset_subtypes[$rows['code']] = [];

	// Query subtypes based on main type
	$query2 = "SELECT * FROM asset_category_subtypes WHERE category_code = '" . $rows['code'] . "';";
	$result2 = mysqli_query($con, $query2);
	while ($rows2 = mysqli_fetch_assoc($result2)) {
		array_push($asset_subtypes[$rows['code']], $rows2);
	}
}

?>

<!doctype html>
<html>

<head>
	<meta charset="utf-8">
	<meta name="google-signin-client_id" content="573761357198-hin7ae7q19qgvoab7t0781b41530546g.apps.googleusercontent.com">
	<link rel="shortcut icon" type="image/x-icon" href="images/PW_P Logo.png" />
	<link rel="stylesheet" href="stylesheets/element-styles.css?<?= $version; ?>">
	<link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css'>
	<link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
	<link href="stylesheets/jquery-ui-themes-1.13.0/themes/ui-lightness/jquery-ui.css" rel="stylesheet">
	<title>Allocations Admin (v<?= $version ?>) - Pierson Wireless</title>

	<style>
		/* Style the tab content (and add height:100% for full page content) */
		.tabcontent {
			padding: 25px 20px;
			height: 100%;
		}

		.ui-autocomplete {
			max-height: 300px;
			overflow-y: auto;
			/* prevent horizontal scrollbar */
			overflow-x: hidden;
		}

		.assetTables {
			margin: 1em
		}

		.asset_label_border {
			border: 0px !important;
		}

		.partTables {
			padding: 2em 0em;
			float: left;

		}

		.partTables td {
			border: none;
			padding-left: 2em;
		}
	</style>
</head>

<body>

	<?php

	//define array of names & Id's to generate headers
	$header_names = ['Asset handling'];
	$header_ids = ['asset', 'project_assign'];

	//pass to php function to create navigation bars
	echo create_navigation_bar($header_names, $header_ids, "save_asset_changes()", $fstUser);

	?>

	<div id='asset' class='tabcontent' style='display: none'>

		<br>
		<h2>Search bars</h2>

		<table style='padding-bottom: 5em;' id='parts-search-table'>
			<tr>
				<td>Purchase Date:</td>
				<td><input type='date' id='search_purchase_date' onkeyup="init_asset_list()" onchange="init_asset_list()"></td>
			</tr>
			<tr>
				<td>Location:</td>
				<td><input type='text' id='search_location' onkeyup="init_asset_list()" onchange="init_asset_list()"></td>
			</tr>
			<tr>
				<td>Category & Category description:</td>
				<td><input type='text' id='search_category' onkeyup="init_asset_list()" onchange="init_asset_list()"></td>
			</tr>
		</table>

		<br><br>
		<td><button id='create_new_asset_button'>Create New Asset</button></td>
		<br><br>
		<td><button hidden="hidden" id='assign_to_person_button'>Make/Modify Personnel</button></td>
		<td><button hidden="hidden" id='assign_to_shop_button'>Make/Modify Shop</button></td>
		<!-- <br><br><td><button onclick = 'open_dialog("asset_type")'>Create New Asset</button></td> -->

		<table id='asset_table' class='standardTables' style='margin: 2em;'>
			<thead>
				<tr class='sticky_order_header'>
					<th></th>
					<th> Equipment code </th>
					<th> Description </th>
					<th> Make </th>
					<th> Model </th>
					<th> Status </th>
					<th> Exp. Date </th>
					<th> Purchase Date </th>
					<th> Equipment Color </th>
					<th> Notes </th>
					<th> Assigned to </th>
					<!-- <th hidden="hidden"> QR Code </th> -->
					<th></th>
				</tr>
				<tr class='sticky_order_header2'>
					<td></td>
					<td><input type='text' id='search_equipment_code' onkeyup="init_asset_list()" onchange="init_asset_list()"></td>
					<td><input type='text' id='search_description' style='width: 25em;' onkeyup="init_asset_list()" onchange="init_asset_list()"></td>
					<td><input type='text' id='search_make' onkeyup="init_asset_list()" onchange="init_asset_list()"></td>
					<td><input type='text' id='search_model' onkeyup="init_asset_list()" onchange="init_asset_list()"></td>
					<td><input type='text' id='search_status' onkeyup="init_asset_list()" onchange="init_asset_list()"></td>
					<td><input type='date' id='search_exp_date' onkeyup="init_asset_list()" onchange="init_asset_list()"></td>
					<td><input type='date' id='search_purchase_date' onkeyup="init_asset_list()" onchange="init_asset_list()"></td>
					<td><input type='text' id='search_equip_color' onkeyup="init_asset_list()" onchange="init_asset_list()"></td>
					<td><input type='text' id='search_notes' onkeyup="init_asset_list()" onchange="init_asset_list()"></td>
					<td><select type='text' id='search_asset_assignment' class='custom-select standard_select required new_project' onkeyup="init_asset_list()" onchange="init_asset_list()">
							<option></option>
							<?= create_select_options($directory); ?>
						</select>
					</td>
					<!-- <td><input hidden="hidden" type='text' id='search_asset_qr' onkeyup="init_asset_list()" onchange = "init_asset_list()"></td> -->
				</tr>
			</thead>
			<tbody>
				<!--to be entered by init_asset_list() !-->
			</tbody>
		</table>
	</div>

	<div style='padding:50em 0em;'><!-- blank space under table --></div>

	<div class="asset_block" style="display: none">
		<h2>Asset block</h2>

		<div class='question_card'>
			<div class='question_title'>
				department
			</div>
			<div class='question_answer'>
				<br><input type='text' class='question_input'>
			</div>
		</div>

		</table>
	</div>

	<div id='asset_dialog' style='display: none' title='Asset Handling'>
		<table id='add_asset' class="partTables table_td">
			<tbody>
				<tr>
					<td>VIN/Serial#</td>
					<td><input class="vin_serial"></td>
					<td># of axles</td>
					<td><input class="#_of_axles"></td>
					<td>Capitalized</td>
					<td><input class="capitalized"></td>
				</tr>
				<tr>
					<td>Year</td>
					<td><input class="year"></td>
					<td>Width</td>
					<td><input class="overall_width"></td>
					<td>Attach to equipment</td>
					<td><input class="attach_to_equipment"></td>
				</tr>
				<tr>
					<td>Department</td>
					<td><input class="department"></td>
					<td>Overall length</td>
					<td><input class="overall_length"></td>
					<td>Hour reading</td>
					<td><input class="hours_reading"></td>
				</tr>
				<tr>
					<td>Category</td>
					<td>
						<input class="category">
					</td>
					<td>Tire size</td>
					<td><input class="tire_size"></td>
					<td>Hour date</td>
					<td><input type='date' class="hour_reading_date"></td>
				</tr>
				<tr>
					<td>Category Description</td>
					<td><input class="category_description"></td>
					<td>Ownership status</td>
					<td><input class="ownership_status"></td>
					<td>Odo. Reading</td>
					<td><input class="odometer_reading"></td>
				</tr>
				<tr>
					<td>Fuel Drop Rate</td>
					<td><input class="fuel_drop_rate"></td>
					<td>Dealer</td>
					<td><input class="dealer"></td>
					<td>Odo. Date</td>
					<td><input type='date' class="odo_date"></td>
				</tr>
				<tr>
					<td>Fuel Capacity</td>
					<td><input class="fuel_capacity"></td>
					<td>Price</td>
					<td><input type='number' class="purchase_cost"></td>
					<td>Mechanic notes</td>
					<td><input class="mechanic_notes"></td>
				</tr>
				<tr>
					<td>Weight</td>
					<td><input class="asset_weight"></td>
					<td>In service date</td>
					<td><input type='date' class="in_service_date"></td>
				</tr>
				<tr>
					<td>Tare Weight</td>
					<td><input class="tare_weight"></td>
					<td>Expected life</td>
					<td><input class="expected_lifespan"></td>
				</tr>
				<tr>
					<td>Height</td>
					<td><input class="height"></td>
					<td>Sold date</td>
					<td><input type='date' class="sold_date"></td>
				</tr>
				<tr>
					<td>Wheelbase</td>
					<td><input class="wheelbase"></td>
					<td>Sale price</td>
					<td><input type='number' class="sale_price"></td>
				</tr>
			</tbody>
		</table>
	</div>
	<div class='ui-widget' id='asset_type' style='display:none' title="Asset Type">
		<table id='parts-search-table'>
			<tr>
				<td> Select asset type: </td>
				<td>
					<select type='text' id='create_new_asset_category' class='custom-select standard_select required new_project' onchange='create_asset_options(this)'>
						<option></option>
						<?= create_select_options($asset_categories, "", "category"); ?>

					</select>
				</td>
			</tr>
		</table>
		<button onclick='create_new_asset()'>Create New Asset</button>
		<div id='create_new_asset_all_inputs'>
			<div>
				<table class="partTables table_vehicles">
					<tbody>
						<tr>
							<td>Description</td>
							<td><input class="description"></td>
						</tr>
						<tr>
							<td>Make</td>
							<td><input class="make"></td>
						</tr>
						<tr>
							<td>Model</td>
							<td><input class="model"></td>
						</tr>
						<tr>
							<td>Status</td>
							<td><input class="status"></td>
						</tr>
						<tr>
							<td>Exp. Date</td>
							<td><input type='date' class="expiration_date"></td>
						</tr>
						<tr>
							<td>Purchase Date</td>
							<td><input type='date' class="purchase_date"></td>
						</tr>
						<tr>
							<td>Equipment Color</td>
							<td><input class="equipment_color"></td>
						</tr>
						<tr>
							<td>Notes</td>
							<td><input class="notes"></td>
						</tr>
						<tr>
							<td>Assigned to</td>
							<td><input class="assign_to"></td>
						</tr>
						<tr>
							<td>QR Code</td>
							<td><input class="qr_code" hidden="hidden"></td>
						</tr>
					</tbody>
				</table>
			</div>
			<div id='asset_options'>

			</div>
		</div>
	</div>

	<div class='ui-widget' id='select_person' style='display:none'>
		<table>
			<tr>
				<td> Select personnel: </td>
				<td>
					<select type='text' class='custom-select standard_select required new_project' onchange='create_people_options(this)'>
						<option></option>
						<?= create_select_options($directory); ?>
					</select>
					</select>
				</td>
			</tr>
		</table>
		<div id='DA_options'>
		</div>
	</div>
	<div class='ui-widget' id='select_shop' style='display:none'>
		<table>
			<tr>
				<td> Select shop: </td>
				<td>
					<select type='text' id='select_shop' class='custom-select standard_select required new_project' onkeyup="init_asset_list()" onchange="init_asset_list()">
						<option></option>
						<?= create_select_options($shops); ?>
					</select>
					</select>
				</td>
			</tr>
		</table>
	</div>

	<div style='display: none' title='Asset Handling'>
		<table id='Vehicles' class="partTables table_vehicles">
			<tbody>
				<tr>
					<td>VIN/Serial#</td>
					<td><input class="vin_serial"></td>
					<td>Year</td>
					<td><input class="year"></td>
					<td># of axles</td>
					<td><input class="#_of_axles"></td>
				</tr>
				<tr>
					<td>Width</td>
					<td><input class="overall_width"></td>
					<td>Attach to equipment</td>
					<td><input class="attach_to_equipment"></td>
					<td>Capitalized</td>
					<td><input class="capitalized"></td>
				</tr>
				<tr>
					<td>Department</td>
					<td><input class="department"></td>
					<td>Overall length</td>
					<td><input class="overall_length"></td>
					<td>Hour reading</td>
					<td><input class="hours_reading"></td>
				</tr>
				<tr>
					<td>Category</td>
					<td>
						<select class='category_description custom-select'>
							<option></option>
							<?= create_select_options($asset_subtypes['VH'], "", 'subtype'); ?>
						</select>
					</td>
					<td>Tire size</td>
					<td><input class="tire_size"></td>
					<td>Hour date</td>
					<td><input type='date' class="hour_reading_date"></td>
				</tr>
				<tr>
					<td>Ownership status</td>
					<td><input class="ownership_status"></td>
					<td>Odo. Reading</td>
					<td><input class="odometer_reading"></td>
				</tr>
				<tr>
					<td>Fuel Drop Rate</td>
					<td><input class="fuel_drop_rate"></td>
					<td>Dealer</td>
					<td><input class="dealer"></td>
					<td>Odo. Date</td>
					<td><input type='date' class="odo_date"></td>
				</tr>
				<tr>
					<td>Fuel Capacity</td>
					<td><input class="fuel_capacity"></td>
					<td>Price</td>
					<td><input type='number' class="purchase_cost"></td>
					<td>Mechanic notes</td>
					<td><input class="mechanic_notes"></td>
				</tr>
				<tr>
					<td>Weight</td>
					<td><input class="asset_weight"></td>
					<td>In service date</td>
					<td><input type='date' class="in_service_date"></td>
					<td>Expected life</td>
					<td><input class="expected_lifespan"></td>
				</tr>
				<tr>
					<td>Height</td>
					<td><input class="height"></td>
					<td>Sold date</td>
					<td><input type='date' class="sold_date"></td>
				</tr>
				<tr>
					<td>Wheelbase</td>
					<td><input class="wheelbase"></td>
					<td>Sale price</td>
					<td><input type='number' class="sale_price"></td>
				</tr>
			</tbody>
		</table>
	</div>
	<div style='display: none' title='Asset Handling'>
		<table id='RF_equipment' class="partTables table_rf">
			<tbody>
				<tr>
					<td>Year</td>
					<td><input class="year"></td>
				</tr>
				<tr>
					<td>Width</td>
					<td><input class="overall_width"></td>
				</tr>
				<tr>
					<td>Attach to equipment</td>
					<td><input class="attach_to_equipment"></td>
				</tr>
				<tr>
					<td>Category</td>
					<td>
						<select class='category_description custom-select'>
							<option></option>
							<?= create_select_options($asset_subtypes['RF'], "", 'subtype'); ?>
						</select>
					</td>
				</tr>
				<tr>
					<td>Department</td>
					<td><input class="department"></td>
				</tr>
				<tr>
					<td>Overall length</td>
					<td><input class="overall_length"></td>
				</tr>
				<tr>
					<td>Price</td>
					<td><input type='number' class="purchase_cost"></td>
				</tr>
				<tr>
					<td>Weight</td>
					<td><input class="asset_weight"></td>
				</tr>
				<tr>
					<td>Ownership status</td>
					<td><input class="ownership_status"></td>
				</tr>
				<tr>
					<td>Height</td>
					<td><input class="height"></td>
				</tr>
				<tr>
					<td>Sold date</td>
					<td><input type='date' class="sold_date"></td>
				</tr>
				<tr>
					<td>Sale price</td>
					<td><input type='number' class="sale_price"></td>
				</tr>
				<tr>
					<td>Expected life</td>
					<td><input class="expected_lifespan"></td>
				</tr>
			</tbody>
		</table>
	</div>
	<div style='display: none' title='Asset Handling'>
		<table id='fiber_optic_equipment' class="partTables table_rf">
			<tbody>
				<tr>
					<td>Year</td>
					<td><input class="year"></td>
				</tr>
				<tr>
					<td>Width</td>
					<td><input class="overall_width"></td>
				</tr>
				<tr>
					<td>Attach to equipment</td>
					<td><input class="attach_to_equipment"></td>
				</tr>
				<tr>
					<td>Category</td>
					<td>
						<select class='category_description custom-select'>
							<option></option>
							<?= create_select_options($asset_subtypes['FO'], "", 'subtype'); ?>
						</select>
					</td>
				</tr>
				<tr>
					<td>Department</td>
					<td><input class="department"></td>
				</tr>
				<tr>
					<td>Overall length</td>
					<td><input class="overall_length"></td>
				</tr>
				<tr>
					<td>Price</td>
					<td><input type='number' class="purchase_cost"></td>
				</tr>
				<tr>
					<td>Weight</td>
					<td><input class="asset_weight"></td>
				</tr>
				<tr>
					<td>Ownership status</td>
					<td><input class="ownership_status"></td>
				</tr>
				<tr>
					<td>Height</td>
					<td><input class="height"></td>
				</tr>
				<tr>
					<td>Sold date</td>
					<td><input type='date' class="sold_date"></td>
				</tr>
				<tr>
					<td>Sale price</td>
					<td><input type='number' class="sale_price"></td>
				</tr>
				<tr>
					<td>Expected life</td>
					<td><input class="expected_lifespan"></td>
				</tr>
			</tbody>
		</table>
	</div>
	<div style='display: none' title='Asset Handling'>
		<table id='desktops' class="partTables table_rf">
			<tbody>
				<tr>
					<td>Year</td>
					<td><input class="year"></td>
				</tr>
				<tr>
					<td>Width</td>
					<td><input class="overall_width"></td>
				</tr>
				<tr>
					<td>Attach to equipment</td>
					<td><input class="attach_to_equipment"></td>
				</tr>
				<tr>
					<td>Category</td>
					<td>
						<select class='category_description custom-select'>
							<option></option>
							<?= create_select_options($asset_subtypes['DT'], "", 'subtype'); ?>
						</select>
					</td>
				</tr>
				<tr>
					<td>Department</td>
					<td><input class="department"></td>
				</tr>
				<tr>
					<td>Overall length</td>
					<td><input class="overall_length"></td>
				</tr>
				<tr>
					<td>Price</td>
					<td><input type='number' class="purchase_cost"></td>
				</tr>
				<tr>
					<td>Weight</td>
					<td><input class="asset_weight"></td>
				</tr>
				<tr>
					<td>Ownership status</td>
					<td><input class="ownership_status"></td>
				</tr>
				<tr>
					<td>Height</td>
					<td><input class="height"></td>
				</tr>
				<tr>
					<td>Sold date</td>
					<td><input type='date' class="sold_date"></td>
				</tr>
				<tr>
					<td>Sale price</td>
					<td><input type='number' class="sale_price"></td>
				</tr>
				<tr>
					<td>Expected life</td>
					<td><input class="expected_lifespan"></td>
				</tr>
			</tbody>
		</table>
	</div>
	<div style='display: none' title='Asset Handling'>
		<table id='laptops' class="partTables table_rf">
			<tbody>
				<tr>
					<td>Year</td>
					<td><input class="year"></td>
				</tr>
				<tr>
					<td>Width</td>
					<td><input class="overall_width"></td>
				</tr>
				<tr>
					<td>Attach to equipment</td>
					<td><input class="attach_to_equipment"></td>
				</tr>
				<tr>
					<td>Category</td>
					<td>
						<select class='category_description custom-select'>
							<option></option>
							<?= create_select_options($asset_subtypes['LT'], "", 'subtype'); ?>
						</select>
					</td>
				</tr>
				<tr>
					<td>Department</td>
					<td><input class="department"></td>
				</tr>
				<tr>
					<td>Overall length</td>
					<td><input class="overall_length"></td>
				</tr>
				<tr>
					<td>Price</td>
					<td><input type='number' class="purchase_cost"></td>
				</tr>
				<tr>
					<td>Weight</td>
					<td><input class="asset_weight"></td>
				</tr>
				<tr>
					<td>Ownership status</td>
					<td><input class="ownership_status"></td>
				</tr>
				<tr>
					<td>Height</td>
					<td><input class="height"></td>
				</tr>
				<tr>
					<td>Sold date</td>
					<td><input type='date' class="sold_date"></td>
				</tr>
				<tr>
					<td>Sale price</td>
					<td><input type='number' class="sale_price"></td>
				</tr>
				<tr>
					<td>Expected life</td>
					<td><input class="expected_lifespan"></td>
				</tr>
			</tbody>
		</table>
	</div>
	<div style='display: none' title='Asset Handling'>
		<table id='tablets' class="partTables table_rf">
			<tbody>
				<tr>
					<td>Year</td>
					<td><input class="year"></td>
				</tr>
				<tr>
					<td>Width</td>
					<td><input class="overall_width"></td>
				</tr>
				<tr>
					<td>Attach to equipment</td>
					<td><input class="attach_to_equipment"></td>
				</tr>
				<tr>
					<td>Category</td>
					<td>
						<select class='category_description custom-select'>
							<option></option>
							<?= create_select_options($asset_subtypes['TB'], "", 'subtype'); ?>
						</select>
					</td>
				</tr>
				<tr>
					<td>Department</td>
					<td><input class="department"></td>
				</tr>
				<tr>
					<td>Overall length</td>
					<td><input class="overall_length"></td>
				</tr>
				<tr>
					<td>Price</td>
					<td><input type='number' class="purchase_cost"></td>
				</tr>
				<tr>
					<td>Weight</td>
					<td><input class="asset_weight"></td>
				</tr>
				<tr>
					<td>Ownership status</td>
					<td><input class="ownership_status"></td>
				</tr>
				<tr>
					<td>Height</td>
					<td><input class="height"></td>
				</tr>
				<tr>
					<td>Sold date</td>
					<td><input type='date' class="sold_date"></td>
				</tr>
				<tr>
					<td>Sale price</td>
					<td><input type='number' class="sale_price"></td>
				</tr>
				<tr>
					<td>Expected life</td>
					<td><input class="expected_lifespan"></td>
				</tr>
			</tbody>
		</table>
	</div>
	<div style='display: none' title='Asset Handling'>
		<table id='phones' class="partTables table_rf">
			<tbody>
				<tr>
					<td>Year</td>
					<td><input class="year"></td>
				</tr>
				<tr>
					<td>Width</td>
					<td><input class="overall_width"></td>
				</tr>
				<tr>
					<td>Attach to equipment</td>
					<td><input class="attach_to_equipment"></td>
				</tr>
				<tr>
					<td>Category</td>
					<td>
						<select class='category_description custom-select'>
							<option></option>
							<?= create_select_options($asset_subtypes['PH'], "", 'subtype'); ?>
						</select>
					</td>
				</tr>
				<tr>
					<td>Department</td>
					<td><input class="department"></td>
				</tr>
				<tr>
					<td>Overall length</td>
					<td><input class="overall_length"></td>
				</tr>
				<tr>
					<td>Price</td>
					<td><input type='number' class="purchase_cost"></td>
				</tr>
				<tr>
					<td>Weight</td>
					<td><input class="asset_weight"></td>
				</tr>
				<tr>
					<td>Ownership status</td>
					<td><input class="ownership_status"></td>
				</tr>
				<tr>
					<td>Height</td>
					<td><input class="height"></td>
				</tr>
				<tr>
					<td>Sold date</td>
					<td><input type='date' class="sold_date"></td>
				</tr>
				<tr>
					<td>Sale price</td>
					<td><input type='number' class="sale_price"></td>
				</tr>
				<tr>
					<td>Expected life</td>
					<td><input class="expected_lifespan"></td>
				</tr>
			</tbody>
		</table>
	</div>
	<div style='display: none' title='Asset Handling'>
		<table id='sim_cards' class="partTables table_rf">
			<tbody>
				<tr>
					<td>Year</td>
					<td><input class="year"></td>
				</tr>
				<tr>
					<td>Attach to equipment</td>
					<td><input class="attach_to_equipment"></td>
				</tr>
				<tr>
					<td>Category</td>
					<td>
						<select class='category_description custom-select'>
							<option></option>
							<?= create_select_options($asset_subtypes['SC'], "", 'subtype'); ?>
						</select>
					</td>
				</tr>
				<tr>
					<td>Department</td>
					<td><input class="department"></td>
				</tr>
				<tr>
					<td>Price</td>
					<td><input type='number' class="purchase_cost"></td>
				</tr>
				<tr>
					<td>Ownership status</td>
					<td><input class="ownership_status"></td>
				</tr>
				<tr>
					<td>Sold date</td>
					<td><input type='date' class="sold_date"></td>
				</tr>
				<tr>
					<td>Sale price</td>
					<td><input type='number' class="sale_price"></td>
				</tr>
			</tbody>
		</table>
	</div>
	<div style='display: none' title='Asset Handling'>
		<table id='office_equipment' class="partTables table_rf">
			<tbody>
				<tr>
					<td>Year</td>
					<td><input class="year"></td>
				</tr>
				<tr>
					<td>Width</td>
					<td><input class="overall_width"></td>
				</tr>
				<tr>
					<td>Attach to equipment</td>
					<td><input class="attach_to_equipment"></td>
				</tr>
				<tr>
					<td>Category</td>
					<td>
						<select class='category_description custom-select'>
							<option></option>
							<?= create_select_options($asset_subtypes['OE'], "", 'subtype'); ?>
						</select>
					</td>
				</tr>
				<tr>
					<td>Department</td>
					<td><input class="department"></td>
				</tr>
				<tr>
					<td>Overall length</td>
					<td><input class="overall_length"></td>
				</tr>
				<tr>
					<td>Price</td>
					<td><input type='number' class="purchase_cost"></td>
				</tr>
				<tr>
					<td>Weight</td>
					<td><input class="asset_weight"></td>
				</tr>
				<tr>
					<td>Ownership status</td>
					<td><input class="ownership_status"></td>
				</tr>
				<tr>
					<td>Height</td>
					<td><input class="height"></td>
				</tr>
				<tr>
					<td>Sold date</td>
					<td><input type='date' class="sold_date"></td>
				</tr>
				<tr>
					<td>Sale price</td>
					<td><input type='number' class="sale_price"></td>
				</tr>
				<tr>
					<td>Expected life</td>
					<td><input class="expected_lifespan"></td>
				</tr>
			</tbody>
		</table>
	</div>
	<div style='display: none' title='Asset Handling'>
		<table id='construction_equipment' class="partTables table_rf">
			<tbody>
				<tr>
					<td>Year</td>
					<td><input class="year"></td>
				</tr>
				<tr>
					<td>Width</td>
					<td><input class="overall_width"></td>
				</tr>
				<tr>
					<td>Attach to equipment</td>
					<td><input class="attach_to_equipment"></td>
				</tr>
				<tr>
					<td>Category</td>
					<td>
						<select class='category_description custom-select'>
							<option></option>
							<?= create_select_options($asset_subtypes['CE'], "", 'subtype'); ?>
						</select>
					</td>
				</tr>
				<tr>
					<td>Department</td>
					<td><input class="department"></td>
				</tr>
				<tr>
					<td>Overall length</td>
					<td><input class="overall_length"></td>
				</tr>
				<tr>
					<td>Price</td>
					<td><input type='number' class="purchase_cost"></td>
				</tr>
				<tr>
					<td>Weight</td>
					<td><input class="asset_weight"></td>
				</tr>
				<tr>
					<td>Ownership status</td>
					<td><input class="ownership_status"></td>
				</tr>
				<tr>
					<td>Height</td>
					<td><input class="height"></td>
				</tr>
				<tr>
					<td>Sold date</td>
					<td><input type='date' class="sold_date"></td>
				</tr>
				<tr>
					<td>Sale price</td>
					<td><input type='number' class="sale_price"></td>
				</tr>
				<tr>
					<td>Expected life</td>
					<td><input class="expected_lifespan"></td>
				</tr>
			</tbody>
		</table>
	</div>

	<div style='display: none' title='Asset Handling'>
		<table id='DA' class="partTables table_vehicles">
			<tbody>
				<tr>
					<td>Equipment code</td>
					<td>VIN/Serial#</td>
					<td># of axles</td>
					<td>Capitalized</td>
				</tr>
			</tbody>
		</table>
	</div>
	<div style='display: none' title='Asset Handling'>
		<table id='AB' class="partTables table_vehicles">
			<tbody>
				<tr>
					<td>Equipment code</td>
					<td><input class="equipment_code"></td>
					<td>VIN/Serial#</td>
					<td><input class="vin_serial"></td>
					<td># of axles</td>
					<td><input class="#_of_axles"></td>
					<td>Capitalized</td>
					<td><input class="capitalized"></td>
				</tr>
			</tbody>
		</table>
	</div>

	<!--local scripts-->
	<script src="javascript/accounting.js"></script>
	<script src="javascript/js_helper.js?<?= $version ?>2"></script>
	<script src="javascript/utils.js"></script>

	<!-- external js libraries -->
	<!--jquery capabilities-->
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
	<script src="https://code.jquery.com/jquery-1.12.4.js"></script>
	<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>

	<!--google APIs-->
	<script src="https://apis.google.com/js/platform.js?onload=init" async defer></script>

	<script>
		//Namespace
		var z = {}

		//pass php arrays to js
		const directory = <?= json_encode($directory); ?>;
		var asset = <?= json_encode($asset); ?>;
		const asset_categories = <?= json_encode($asset_categories); ?>;

		//used to tell if we need to start with new part entry
		const user_info = <?= json_encode($fstUser); ?>;

		// Used to open asset type dialog
		$('#create_new_asset_button').on('click', function() {

			var screenheight = $(window).height();
			var screenwidth = $(window).width();

			$("#asset_type").dialog({
				width: "2110px",
				height: "auto",
				dialogClass: "fixedDialog",
			});
		});

		/**
		 * Handles creating new asset
		 * @author Alex Borchers
		 */
		function create_new_asset() {

			// Check all required fields are entered (not empty)

			// To do later

			// Create form element
			var fd = new FormData();

			// Loop through all inputs related to new asset and add to form data
			// Select all input and select elements within the div
			var keys = [];
			var asset_inputs = u.eid("create_new_asset_all_inputs").querySelectorAll('input, select');
			var error = false;

			// Iterate through the inputs and select elements
			asset_inputs.forEach(function(obj) {
				if (obj.classList[0] == "category_description" && obj.value == "") {
					alert("[Error] This category description is required.");
					error = true;
					return;
				}
				fd.append(obj.classList[0], obj.value);
				keys.push(obj.classList[0]);
			});

			// Do not move forward with error
			if (error)
				return;

			// Add asset category to form
			fd.append('category', u.eid("create_new_asset_category").value);

			//Adding array used to update DB
			//JSON.stringify is used for converting to and from JSON (takes a java object then translates it to JSON and sends it to php)
			fd.append('keys', JSON.stringify(keys));

			//Add tell and user info
			fd.append('tell', 'create_new_asset');
			fd.append('user_info', JSON.stringify(user_info));

			$.ajax({
				url: 'terminal_assets_helper.php',
				type: 'POST',
				processData: false,
				contentType: false,
				data: fd,
				success: function(response) {

					if (response != "") {
						alert("[Error] There was a system issue:" + response);
						console.log(response);
						return;
					}

					//alert success to user
					alert("This asset has been created.");
					close_dialog("asset_type");

				}
			});
		}

		$('#assign_to_person_button').on('click', function() {

			var screenheight = $(window).height();
			var screenwidth = $(window).width();

			$("#select_person").dialog({
				width: screenwidth - 50,
				height: screenheight - 20,
				dialogClass: "fixedDialog",
			});
		});

		$('#assign_to_shop_button').on('click', function() {

			var screenheight = $(window).height();
			var screenwidth = $(window).width();

			$("#select_shop").dialog({
				width: screenwidth - 50,
				height: screenheight - 20,
				dialogClass: "fixedDialog",
			});
		});


		/**
		 * [documentation]
		 */
		function init_asset_list() {

			//get user entered search bars
			// NOTE: u.eid is short for document.getElementByID() (see javascript/utils.js for other short-hands)
			var equipment_code = u.eid("search_equipment_code").value.toLowerCase(),
				description = u.eid("search_description").value.toLowerCase(),
				make = u.eid("search_make").value.toLowerCase(),
				model = u.eid("search_model").value.toLowerCase(),
				status = u.eid("search_status").value.toLowerCase(),
				expiration_date = u.eid("search_exp_date").value.toLowerCase(),
				equipment_color = u.eid("search_equip_color").value.toLowerCase(),
				purchase_date = u.eid("search_purchase_date").value.toLowerCase(),
				notes = u.eid("search_notes").value.toLowerCase(),
				location = u.eid("search_location").value.toLowerCase(),
				category = u.eid("search_category").value.toLowerCase(),
				assign_to = u.eid("search_asset_assignment").value.toLowerCase()
			//qr_code = u.eid("search_asset_qr").value.toLowerCase();

			//filter vendor_list based on search criteria
			var filtered_assets = asset.filter(function(v) {
				return (equipment_code == "" || v.id.toLowerCase().includes(id)) && //checks id
					(description == "" || v.description.toLowerCase().includes(description)) && //checks description
					(make == "" || v.make.toLowerCase().includes(make)) && //checks make
					(model == "" || v.model.toLowerCase().includes(model)) && //checks model
					(status == "" || v.status.toLowerCase().includes(status)) && //checks status
					(expiration_date == "" || v.expiration_date.toLowerCase().includes(expiration_date)) &&
					(equipment_color == "" || v.equipment_color.toLowerCase().includes(equipment_color)) &&
					(notes == "" || v.notes.toLowerCase().includes(notes)) &&
					(purchase_date == "" || v.purchase_date.toLowerCase().includes(purchase_date)) &&
					(location == "" || v.location.toLowerCase().includes(location)) &&
					(category == "" || v.category.toLowerCase().includes(category)) &&
					(assign_to == "" || v.assign_to.toLowerCase().includes(assign_to))
				//(qr_code == "" || v.qr_code.toLowerCase().includes(qr_code))
			});

			//console.log(filtered_assets)

			//remove previous entries (if they exist)
			//vendor_table_row is a class added to all rows in add_vendor_row() function
			document.querySelectorAll('.asset_table_row').forEach(function(a) {
				a.remove()
			})

			//set max limit of results to 50 (force user to search further + decrease time for table rendering)
			var limit = Math.min(50, filtered_assets.length);

			//get table that we want to add to (specifically, get <tbody>)
			//differentiating between <tbody> and <thead> can be useful when using filter bars
			var table = u.eid("asset_table").getElementsByTagName('tbody')[0];

			//loop through filtered_grid till we reach our limit
			for (var i = 0; i < limit; i++) {
				add_asset_row(filtered_assets[i], table);
			}
		}

		function add_asset_row(target_asset, table) {

			//create new row
			var row = table.insertRow(-1);
			row.classList.add("asset_table_row"); //add class so we can remove this for future searches

			//init array of cells to be entered (should match columns in fst_vendor_list sql table)
			var keys = ['expand_button', 'equipment_code', 'description', 'make', 'model', 'status', 'expiration_date', 'purchase_date', 'equipment_color', 'notes', 'assign_to'];

			//loop through keys and add a cell for each key to the table
			for (var i = 0; i < keys.length; i++) {

				//create new cell on given row
				var cell = row.insertCell(i);

				//create input field to append to cell
				var input = document.createElement("input");

				//set value of input (use keys to do so)
				input.value = target_asset[keys[i]];

				//create specific rules for certain keys
				//if this is the equipment code, we do not want this changing, set this to readonly
				if (keys[i] == "asset" || keys[i] == "equipment_code")
					input.readOnly = true;

				// if key is expand_button, overwrite <input> set to <button>
				if (keys[i] == "expand_button") {
					input = document.createElement("button");
					input.id = "expand_" + target_asset.equipment_code;
					input.addEventListener("click", expand_row);
					input.innerHTML = "+";
				} else if (keys[i] == "assign_to") {

					var input = create_select(directory);
					input.value = target_asset[keys[i]];
				} else if (keys[i] == "expiration_date" || keys[i] == "purchase_date") {
					input.type = "date";
					input.value = target_asset[keys[i]];
				}

				// Adjust width for description
				if (keys[i] == "description")
					input.style.width = "25em";

				//Add key as classlist
				input.classList.add(keys[i]);

				//append input to cell
				cell.appendChild(input);
			}
		}

		//creats standard select list
		//param 1 = list of items you want turned into a select list
		function create_select(list) {

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

		function expand_row() {

			// get vendor ID from <button> id attribute (structured like "expand_[id]")
			var equipment_code = this.id.substr(7);

			// depending on innerHTML of button, show or remove row
			if (this.innerHTML == "+") {

				// update to '-'
				this.innerHTML = "-";

				// work back to <tr>
				var td = this.parentNode;
				var tr = td.parentNode;

				// call function to expand row
				//add_expanded_section(equipment_code, tr.rowIndex);
				add_expanded_section2(equipment_code, tr.rowIndex);

			} else {

				// update to '-'
				this.innerHTML = "+";

				// remove existing row
				u.eid("expanded_row_" + equipment_code).remove();

			}
		}

		function add_expanded_section2(equipment_code, rowIndex) {
			var table2 = u.eid("asset_table").getElementsByTagName('tbody')[0];
			var row2 = table2.insertRow(rowIndex - 1);
			row2.id = "expanded_row_" + equipment_code;
			row2.classList.add("asset_table_row");

			var cell2 = row2.insertCell(0);
			cell2.colSpan = 11;
			//cell2 = u.eid("add_asset").getElementsByTagName('tbody')[0];

			// Get category based on first 2 of equipment code
			var index = asset_categories.findIndex(object => {
				return object.code == equipment_code.substr(0, 2);
			});

			// If we don't find a match, report error to user
			if (index == -1) {
				alert("Error: Could not find category for equipment code " + equipment_code.substr(0, 2));
				return;
			}

			// Call function to get original table
			const original_table = get_table_from_category(asset_categories[index].category);

			// Create a copy of the table
			const newTable = original_table.cloneNode(true);

			// Add the new table as a child to the parent element
			cell2.appendChild(newTable);

			// Get index related to asset
			var asset_index = asset.findIndex(object => {
				return object.equipment_code == equipment_code;
			});

			//var asset = document.createElement("asset");
			// Select all input and select elements within the div
			var asset_inputs = newTable.querySelectorAll('input, select');

			// Iterate through the inputs and select elements
			asset_inputs.forEach(function(obj) {
				obj.value = asset[asset_index][obj.classList[0]];
			});
		}

		function add_expanded_section(equipment_code, row_index) {

			//get orders table (used throughout function)
			var asset_t = u.eid("asset_table").getElementsByTagName('tbody')[0];

			//get list of parts assigned po number
			//var contacts = asset_poc.filter(function (object) {
			//return object.equipment_code == equipment_code;
			//});

			//loop and create a table with these parts
			var table = document.createElement("table");
			table.classList.add("standardTables");
			table.classList.add("contacts_table")

			//create headers
			var row = table.insertRow(-1);
			row.classList.add("contacts_header");

			// add column for name, phone, email
			var cell = row.insertCell(0);
			cell.innerHTML = "Name";
			var cell = row.insertCell(1);
			cell.innerHTML = "Phone";
			var cell = row.insertCell(2);
			cell.innerHTML = "Email";

			// loop through contacts and call function to create row for given contact
			for (var i = 0; i < contacts.length; i++) {
				add_expanded_row(table, contacts[i]);
			}

			// create button to send create new shipments
			var button = document.createElement("button");
			button.innerHTML = "Update Info";
			button.id = "update_poc_" + equipment_code;
			button.addEventListener("click", update_contacts);

			//add row to orders table and push table to new row
			var row = asset_t.insertRow(row_index - 1);
			row.id = "expanded_row_" + equipment_code;
			row.classList.add("expanded_row");
			var cell = row.insertCell(0);
			cell.colSpan = asset_t.rows[0].cells.length;
			cell.append(button);

			//add newly created table to row
			cell.append(table);

		}

		function add_expanded_row(table, contact) {

			//create new row from given table
			var row = table.insertRow(-1);

			// set content
			var content = ["name", "phone", "email"];

			//loop through global which defines sql table id's and read out information to user
			for (var i = 0; i < content.length; i++) {

				//create new cell
				var cell = row.insertCell(i);

				//create input element & add classlist
				var input = document.createElement("input");
				input.classList.add(content[i] + "_" + contact.equipment_code);
				input.value = contact[content[i]];
				cell.appendChild(input);

				//if "name", add ID to input
				if (content[i] == "name")
					input.id = "poc_" + contact.id;

			}

			//add button to remove shipment at end of each row
			var cell = row.insertCell(content.length);
			cell.innerHTML = "&#10006";
			cell.classList.add("delete_contact");
			cell.id = "delete_" + contact.id;
		}

		//This is an event listener alerting the user when a field is changed
		$(document).on('change', '.asset_table_row input', function() {
			//A place to store the changes
			record_asset_changes(this);
		});

		//We're creating a global variable to maintain the field we need throughout the whole function
		var asset_changes = [];

		//Records all changes done in the editable fields depending on the equipment_code
		function record_asset_changes(input) {
			//We are getting the equipment code to any changes made to the row 
			var td = input.parentNode;
			var tr = td.parentNode;

			if (tr.classList.contains("asset_table_row")) {
				//grabbing equipment code
				var ec_cell = tr.childNodes[1];
				var equipment_code = ec_cell.childNodes[0].value;
			} else {
				var tbody = tr.parentNode;
				var table = tbody.parentNode;
				var td = table.parentNode;
				var tr = td.parentNode;
				//debugger;
				var equipment_code = tr.id.substr(13);
			}

			//Checking if we have the changes made recorded, if not add them to global array
			if (!asset_changes.includes(equipment_code)) {
				asset_changes.push(equipment_code);

			}

			//Update info in global asset array
			//we are searching through the whole asset list and identifying where we have a match then we are storing that index tp reference later
			var index = asset.findIndex(object => {
				return object.equipment_code == equipment_code;
			});

			//Update asset attributes based on the classlist
			asset[index][input.classList[0]] = input.value;

			//console.log(asset[index][input.classList[0]]);

		}

		/**
		 * Handles saving asset changes
		 */
		function save_asset_changes() {

			var fd = new FormData();

			var update_assets = asset.filter(function(obj) {
				return asset_changes.includes(obj.equipment_code);
			});

			//console.log(update_assets);

			//Adding array used to update DB
			//JSON.stringify is used for converting to and from JSON (takes a java object then translates it to JSON and sends it to php)
			fd.append('update_assets', JSON.stringify(update_assets));

			//Add tell and user info
			fd.append('tell', 'save_asset_changes');
			fd.append('user_info', JSON.stringify(user_info));

			$.ajax({
				url: 'terminal_assets_helper.php',
				type: 'POST',
				processData: false,
				contentType: false,
				data: fd,
				success: function(response) {

					if (response != "") {
						alert("[Error] There was a system issue:" + response);
						console.log(response);
						return;
					}

					//console.log(response);
					update_assets = [];

					//alert success to user
					alert("Your changes have been saved.");

				}
			});
		}

		/**
		 * Handles saving asset changes
		 */
		function save_new_asset_button() {

			var fd = new FormData();

			var update_assets = asset.filter(function(obj) {
				return asset_changes.includes(obj.equipment_code);
			});

			//console.log(update_assets);

			//Adding array used to update DB
			//JSON.stringify is used for converting to and from JSON (takes a java object then translates it to JSON and sends it to php)
			fd.append('update_assets', JSON.stringify(update_assets));

			//Add tell and user info
			fd.append('tell', 'save_new_asset_button');
			fd.append('user_info', JSON.stringify(user_info));

			$.ajax({
				url: 'terminal_assets_helper.php',
				type: 'POST',
				processData: false,
				contentType: false,
				data: fd,
				success: function(response) {

					if (response != "") {
						alert("[Error] There was a system issue:" + response);
						// console.log(response);
						// return;
					}

					//console.log(response);
					update_assets = [];

					//alert success to user
					alert("Your changes have beeen saved.");

				}
			});
		}


		function create_asset_options(targ) {
			//alert("testing");

			// Get the table element by its id
			// const original_asset_options_table = document.getElementById('Vehicles');
			// const original_asset_options_table_rf = document.getElementById('RF_equipment');

			// // Create a copy of the table
			// const new_asset_option_Table = original_asset_options_table.cloneNode(true);
			// const new_asset_option_Table_rf = original_asset_options_table_rf.cloneNode(true);

			var original_table;
			var new_table;

			var asset_options = document.getElementById('asset_options');

			while (asset_options.firstChild) {
				asset_options.removeChild(asset_options.firstChild);
			}

			// Call function to get original table
			original_table = get_table_from_category(targ.value);

			// Copy original, add to create new asset dialog
			new_table = original_table.cloneNode(true);
			asset_options.appendChild(new_table);

			// if (document.getElementById('Vehicles').selected)
			// 	asset_options.appendChild(new_asset_option_Table);
			// else if (document.getElementById('RF_equipment').selected)
			// 	asset_options.appendChild(new_asset_option_Table_rf);
		}

		/**
		 * Handles getting attribute table based on category
		 * @author Alex Borchers
		 * @param {string} category - The category of the asset
		 * @returns {HTMLElement} The table that holds the required asset attributes
		 */
		function get_table_from_category(category) {

			// Initialize original table
			var original_table;

			// Depending on the category, grab table
			if (category == "Vehicles")
				original_table = document.getElementById('Vehicles');
			else if (category == "RF Equipment")
				original_table = document.getElementById('RF_equipment');
			else if (category == "Fiber Optic Equipment")
				original_table = document.getElementById('fiber_optic_equipment');
			else if (category == "Desktops")
				original_table = document.getElementById('desktops');
			else if (category == "Laptops")
				original_table = document.getElementById('laptops');
			else if (category == "Tablets")
				original_table = document.getElementById('tablets');
			else if (category == "Phones")
				original_table = document.getElementById('phones');
			else if (category == "Sim Cards")
				original_table = document.getElementById('sim_cards');
			else if (category == "Office Equipment")
				original_table = document.getElementById('office_equipment');
			else if (category == "Construction Equipment")
				original_table = document.getElementById('construction_equipment');

			return original_table;

		}

		function create_people_options(targ) {

			var DA_table;
			var DA_new_table;

			var DA_options = document.getElementById('DA_options');

			while (DA_options.firstChild) {
				DA_options.removeChild(DA.firstChild);
			}

			if (targ.value == "Dima Abdo")
				DA_table = document.getElementById('DA');
			else if (targ.value == "Alex Borchers")
				DA_table = document.getElementById('AB');

			DA_new_table = DA_table.cloneNode(true);
			DA_options.appendChild(DA_new_table);
		}

		//manages changes tabs
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

		//turns wait mouse on during ajax call
		$(document).ajaxStart(function() {
			waiting('on');
		});

		$(document).ajaxStop(function() {
			waiting('off');
		});

		//windows onload
		window.onload = function() {

			// Get the element with id="defaultOpen" and click on it
			u.eid("defaultOpen").click();

			// Load the first 50 of the asset list
			init_asset_list();

			//add event listener logic to notify the user before they exit the site if they have potential unsaved data
			window.addEventListener("beforeunload", function(e) {

				/*
				if (update_parts.length == 0) {
					return undefined;
				}
				*/
				return undefined;

				var confirmationMessage = 'It looks like you have been editing something. ' +
					'If you leave before saving, your changes will be lost.';

				(e || window.event).returnValue = confirmationMessage; //Gecko + IE
				return confirmationMessage; //Gecko + Webkit, Safari, Chrome etc.
			});
		}
	</script>
</body>

<?php

//reset return address once the page has loaded
unset($_SESSION['returnAddress']);

//close SQL connection
$mysqli->close();

?>

</html>