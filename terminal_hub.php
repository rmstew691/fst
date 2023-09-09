<?php
session_start();

//used to grab actual link for the current address
$actual_link = "https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

//Save current site so we can return after log in
$_SESSION['returnAddress'] = $actual_link;

//sub link
$sub_link = substr($actual_link, 0, strpos($actual_link, "home"));

//include funtcions & constants
include('phpFunctions.php');

//include php HTML renderings
include('phpFunctions_html.php');

include('constants.php');

// Load the database configuration file
require_once 'config.php';

//Make sure user has privileges
$query = "SELECT * FROM fst_users where email = '" . $_SESSION['email'] . "'";
$result = $mysqli->query($query);

if ($result->num_rows > 0) {
	$fstUser = mysqli_fetch_array($result);
} else {
	$fstUser['accessLevel'] = "None";
}

sessionCheck($fstUser['accessLevel']);

//check for GET variables (look for id)
$new_part = "";

if (isset($_GET['newPart'])) {
	$new_part = $_GET['newPart'];
}

//grab attachments taken from 
$shipping_locations = [];

$query = "select * from general_shippingadd WHERE customer = 'PW';";
$result = mysqli_query($con, $query);

while ($rows = mysqli_fetch_assoc($result)) {

	//push to array
	array_push($shipping_locations, $rows);
}

//grab attachments taken from 
$inventory = [];
$parts = [];
$oem = [];
$category = [];

$query = "select * from invreport;";
$result = mysqli_query($con, $query);

while ($rows = mysqli_fetch_assoc($result)) {

	//add class to rows 
	$rows['class'] = 'catalog';

	//push to array
	array_push($inventory, $rows);
	array_push($parts, $rows['partNumber']);
	array_push($oem, $rows['manufacturer']);
	array_push($category, $rows['partCategory']);
}

//grab any extra parts
$query = "select * from fst_newparts;";
$result = mysqli_query($con, $query);

while ($rows = mysqli_fetch_assoc($result)) {

	//add class to rows 
	$rows['class'] = 'new_part';

	//push to array
	array_push($inventory, $rows);
}

//grab part & shop combos from inv_locations
$inv_locations = [];
$query = "select shop, partNumber FROM inv_locations;";
$result = mysqli_query($con, $query);

while ($rows = mysqli_fetch_assoc($result)) {
	array_push($inv_locations, $rows);
}

// get list of all unique shops
$all_shops = [];
$query = "select shop FROM inv_locations GROUP BY shop;";
$result = mysqli_query($con, $query);

while ($rows = mysqli_fetch_assoc($result)) {
	array_push($all_shops, $rows['shop']);
}

//grab mo request info
$mo_requests = [];

$query = "select id, mo_id, project_id, status from fst_allocations_mo;";
$result = mysqli_query($con, $query);

while ($rows = mysqli_fetch_assoc($result)) {

	//push to array
	array_push($mo_requests, $rows);
}

//grab inventory attribute assignments
$query = "select * from inv_attributes_assignments;";
$attributes_assignments = get_pointer_options($query, 'category', 'options');

//grab attribute drop_down options
$query = "select * from inv_attributes_options order by attribute_key, cast(inv_attributes_options.order as unsigned) asc;";
$attributes_options = get_pointer_options($query, 'attribute_key', 'options');

//grab attribute keys
$attributes_key = [];

$query = "select * from inv_attributes_key;";
$result = mysqli_query($con, $query);

while ($rows = mysqli_fetch_assoc($result)) {

	//push to array
	array_push($attributes_key, $rows);
}

//grab part logs
$part_logs = [];

$query = "select CONCAT(b.firstName, ' ', b.lastName) as name, a.* from invreport_logs a, fst_users b WHERE a.user = b.id AND type = 'PA' order by time_stamp;";
$result = mysqli_query($con, $query);

while ($rows = mysqli_fetch_assoc($result)) {

	//push to array
	array_push($part_logs, $rows);
}

//grab pq_overview
$pq_overview = [];

$query = "select id, quoteNumber, type, requested_by, project_id, urgency, requested from fst_pq_overview;";
$result = mysqli_query($con, $query);

while ($rows = mysqli_fetch_assoc($result)) {
	//push to array
	array_push($pq_overview, $rows);
}

//grab pq_detail
$pq_detail = [];
$result = mysqli_query($con, pq_detail_query);	//in constants.php

while ($rows = mysqli_fetch_assoc($result)) {
	//push to array
	array_push($pq_detail, $rows);
}

// grab info about staging areas
$staging_areas = [];
$query = "SELECT * FROM inv_staging_areas;";
$result = mysqli_query($con, $query);	//in constants.php
while ($rows = mysqli_fetch_assoc($result)) {
	array_push($staging_areas, $rows);
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
	<link href="stylesheets/jquery-ui-themes-1.13.0/themes/ui-lightness/jquery-ui.css" rel="stylesheet">
	<title>Allocations Admin (v<?= $version ?>) - Pierson Wireless</title>

	<style>
		/**styles div containing checkboxes for inv_locations */
		.inv_locations_div {
			clear: both;
			width: 68em;
			margin-left: 1.3em;
		}

		#part_log {
			text-align: left;
			display: none;
		}

		#part_log_head {
			padding-top: 1em;
			margin-left: 1em;
			text-align: left;
		}

		.reels_length_list,
		.reels_location_list {
			width: 10em !important;
		}

		.table_id {
			text-align: left;
		}

		.attribute_header {
			text-align: center;
			font-weight: bold;
			border: none !important;
			position: sticky;
			top: 0;
			z-index: 20;
			background: white;
			padding: 6px !important;
		}

		.fixed-column {
			left: 0;
			position: sticky;
			background: white;
		}

		.fixTableHead {
			overflow-y: auto;
			height: 70vh;
		}

		.fixTableHead thead th {
			position: sticky;
			top: 0;
			z-index: 20;
		}

		.fixTableHead th {
			background: #FFFFFF;
		}

		#searchTable {
			width: max-content;
			overflow-x: scroll;
		}

		input[type=text].inventory_input,
		input[type=number].inventory_input,
		select.inventory_input {
			width: 90%;

		}

		.edit_field {
			width: 13.22em;
		}

		body {
			overflow-x: hidden;
		}

		.partTables {
			padding: 2em 0em 2em 2em;
			float: left;

		}

		.partTables td {
			border: none;
		}

		#clar_column {
			width: 40em;
			min-height: 2em;
		}

		#clarifications {
			width: 400em;
			overflow-x: scroll;
		}

		.column {
			width: 5em;
		}

		input:read-only:not([type=button]):not([type=submit]):not([type=file]) {
			background-color: #C8C8C8;
		}

		input:read-write,
		textarea {
			background-color: #BBDFFA;
			border-color: #000B51;
			border-width: medium;
		}

		#basicInfo {
			float: left;
		}

		.admin-tables {
			border-collapse: collapse;
			border: 1em;

		}

		.admin-tables th {
			padding: 4px;
		}

		.admin-tables td {
			border: 1px solid #000000;
			padding: 4px;
		}


		.custom-select {
			background-color: #BBDFFA;
			border-color: #000B51;
			border-width: medium;
			cursor: pointer;
			width: 10em;
		}

		/* Style the tab content (and add height:100% for full page content) */
		.tabcontent {
			padding: 25px 20px;
			height: 100%;
		}

		.custom-button {
			width: 9.5em;
		}

		textarea {
			font-family: Cambria, "Hoefler Text", "Liberation Serif", Times, "Times New Roman", "serif";

		}

		.new_clar {
			width: 40em;
			height: 3em;
		}

		.edit_clar_text {
			width: 40em;
			height: 3em;

		}

		.custom-check {
			text-align: center;

		}

		.hub_tables th {
			text-align: left;
			padding-right: 2em;
		}

		#inv_category {
			width: 25.7em;
		}

		.inv_input,
		.shop_input {
			width: 25em;
		}

		.ui-autocomplete {
			max-height: 300px;
			overflow-y: auto;
			/* prevent horizontal scrollbar */
			overflow-x: hidden;
		}

		.custom-new-part {
			height: 29px !important;

		}

		#warehouse_grid {
			list-style-type: none;
			margin: 0;
			padding: 0;
			width: 640px;
		}

		#warehouse_grid li {
			margin: 3px 3px 3px 0;
			padding: 1px;
			float: left;
			width: 70px;
			height: 70px;
			font-size: 14px;
			text-align: center;
			cursor: grab;
		}

		#warehouse_grid li:active {
			cursor: grabbing;
		}

		.warehouse_grid_outline {
			width: 617px;
			height: 641px;
			border: 1px solid black;
			padding: 8px;
			margin: 2em;
		}

		.remove {
			color: red;
			cursor: pointer;
			margin-left: 5px;
		}
	</style>
</head>

<body>

	<?php

	//define array of names & Id's to generate headers
	$header_names = ['Edit Catalog', 'Shipping Locations', 'Edit MO/PO Requests', 'Warehouse Setup'];
	$header_ids = ['inventory', 'shipping', 'mo-po', 'warehouse_setup'];

	//pass to php function to create navigation bars
	echo create_navigation_bar($header_names, $header_ids, "", $fstUser);

	?>

	<div style='padding-left:1em;padding-top:4em;'>

		<!--CONSTANT IN constants.php-->
		<?= constant('terminal_navigation') ?>

		<button onclick='export_analytics()' form='' style='margin-left:-4px;'>Export Analytics to CSV</button>
		<a id="hold_pq_info" style="display:none"></a>

	</div>

	<div id='add_new_part_dialog' style='display: none' title='Add Part Dialog'>
		<table id='add_new_table'>
			<table class="partTables">
				<tbody>
					<tr>
						<th colspan="2">Basic Part Info</th>
					</tr>
					<tr>
						<td class="table_td">Part Number</td>
						<td><input class="new_field" id="new_part" onkeyup='check_existing(this.value)' onchange='check_existing(this.value)'> <span style='display: none; color:red' id='existing_part'>This part already exists in our database.</span></td>
					</tr>
					<tr>
						<td class="table_td">Description</td>
						<td><input class="new_field" id="new_description" style="width: 25em;" maxlength="60"></td>
					</tr>
					<tr>
						<td class="table_td">Part Category</td>
						<td><select class="custom-new-part custom-select-header new_field" id="new_category" onchange="z.additional_critera(this.value, 'new-part')">

								<option value=""></option>

								<?php
								//array to hold all categories (used in js)
								$all_categories = [];
								$all_categories_full = [];

								$categories = "select * from general_material_categories WHERE category NOT IN ('PW-KITS');";
								$result = mysqli_query($con, $categories);

								while ($rows = mysqli_fetch_assoc($result)) {
								?>
									<option value="<?= $rows['category']; ?>"><?= $rows['description']; ?></option>
								<?php
									array_push($all_categories, $rows['category']);
									array_push($all_categories_full, $rows['description']);
								}
								?>

							</select>
						</td>
					</tr>
					<tr>
						<td class="table_td">Manufacturer</td>
						<td><select class="custom-new-part custom-select-header new_field" id="new_manufacturer">

								<option value=""></option>

								<?php

								//array to hold all categories (used in js)
								$all_manufacturers = [];

								$query = "select trim(manufacturer) as manufacturer from invreport where manufacturer <> '' group by manufacturer order by trim(manufacturer);";
								$result = mysqli_query($con, $query);
								while ($rows = mysqli_fetch_assoc($result)) {

								?>
									<option value="<?= $rows['manufacturer']; ?>" /><?= $rows['manufacturer']; ?></option>

								<?php

									array_push($all_manufacturers, $rows['manufacturer']);
								}

								?>

							</select>
						</td>
					</tr>
					<tr>
						<td class="table_td">UOM</td>
						<td>
							<select class="custom-new-part custom-select-header new_field" id="new_uom">
								<option></option>
								<option>EA</option>
								<option>LF</option>
								<option>BDL</option>
							</select>
						</td>
					</tr>
					<tr>
						<td class="table_td">Cut Sheet Link</td>
						<td><input class="new_field" id="new_cut_sheet"></td>
					</tr>
					<tr>
						<td class="table_td">Cost</td>
						<td><input type="number" class="new_field" id="new_cost"></td>
					</tr>
					<tr>
						<td class="table_td">Price</td>
						<td><input type="number" class="new_field" id="new_price"></td>
					</tr>
					<tr>
						<td class="table_td">Material Logistics</td>
						<td><input type="number" class="new_field" id="new_matL" readonly><input type="checkbox" id="new_matL_check" onclick="toggle_matL('new')" checked>5%</td>
					</tr>
					<tr>
						<td class="table_td">Last Quote Date</td>
						<td><input type="date" class="new_field" id="new_quote_date"></td>
					</tr>
					<tr>
						<td class="table_td">Status</td>
						<td>
							<select class="custom-new-part custom-select-header new_field" id="new_status">
								<option>Active</option>
								<option>Special Order</option>
								<option>EOL</option>
								<option>Discontinued</option>
							</select>
						</td>
					</tr>
					<tr>
						<td class="table_td">Preferred Part</td>
						<td><input type="checkbox" class="new_field" id="new_pref_part"></td>
					</tr>
					<tr>
						<td class="table_td">Hot List</td>
						<td><input type="checkbox" class="new_field" id="new_hot"></td>
					</tr>
					<tr style='padding-top: 1em;'>
						<td colspan='2'><button id='add_part_button' onclick='material_creation()'>Add Part To Catalog</button></td>
					</tr>
				</tbody>
			</table>
			<table class="partTables" id="new-attributes-table">
				<tbody>
					<tr>
						<th colspan="2">Part Attributes</th>
					</tr>
					<tr style='visibility: collapse' id='no-attr-row'>
						<th colspan="2"><i>There are no attributes that apply to this part. </i></th>
					</tr>
				</tbody>
			</table>
			<table class="partTables" id="subs_table_new">
				<tbody>
					<tr>
						<th colspan="2">Subs List</th>
					</tr>
					<tr>
						<td><button onclick="add_sub('new')">Add Sub</button></td>
						<td><input class="new_field part_search" id="new_sub" placeholder="Substitute Part"></td>
					</tr>
					<tr style="height: 2em;"></tr>
				</tbody>
			</table>
		</table>
	</div>

	<div id='inventory' class='tabcontent'>

		<table style='padding-bottom: 5em;' id='parts-search-table'>
			<tr>
				<td> Enter a part: </td>
				<td>
					<input type='text' class='inv_input part_search' id='search_part'><button id='add_new_part'>Add New Part</button>
				</td>
			</tr>
			<tr>
				<td>OR</td>
			</tr>
			<tr>
				<td> Select a Part Category: </td>
				<td>
					<select class='custom-select-header' id='cat-dropdown' onchange='z.update_dropdown(this)'>
						<option class='c-option'></option>
						<?php
						//add categories
						for ($i = 0; $i < sizeof($all_categories); $i++) {
						?>
							<option class='c-option' value="<?= $all_categories[$i]; ?>" /><?= $all_categories_full[$i]; ?></option>
						<?php

						}
						?>
					</select>
				</td>
			</tr>
			<tr>
				<td> Select an OEM: </td>
				<td>
					<select class='custom-select-header' id='manufacturer-dropdown' onchange='z.update_dropdown(this)'>
						<option class='m-option'></option>

						<?php

						//add manufacturers
						for ($i = 0; $i < sizeof($all_manufacturers); $i++) {

						?>

							<option class='m-option' value='<?= $all_manufacturers[$i]; ?>'><?= $all_manufacturers[$i]; ?></option>

						<?php
						}
						?>

					</select>
				</td>
			</tr>
			<tr>
				<td><button onclick='z.grabParts()'>Search for parts</button> </td>
			</tr>
			<tr>
				<td><button onclick='save_updates()'>Save Edits</button></td>
			</tr>
			<tr>
				<td><b>OR</b></td>
			</tr>
			<tr>
				<td colspan='2'><button onclick='document.getElementById("parts_csv").click()'>Update Parts From CSV</button> <span onclick='download_csv();' class='download'>Download CSV Template</span><input type='file' onchange='process_csv()' id='parts_csv' style='display:none'><a id="hold_template" style="display:none"></a> <i>Please limit to 1,000 rows per upload</i></td>
			</tr>


		</table>

		<?php

		//set headers / keys here so we can change order easily later
		$headers = array("Part Number", "Part Description", "Cost", "Price", "Preferred Part", "Hot List", "Material Logistics", "Last Quote Date", "Status", "Active (VP)");
		$header_keys = array("partNumber", "partDescription", "cost", "price", "pref_part", "hot_part", "matL", "quoteDate", "status", "active");
		$col_width = array("width: 15em;", "width: 25em;", "width: 7em;", "width: 7em;", "width: 5em;", "width: 5em", "width: 7em;", "width: 7em;", "width: 7em;", "width: 7em;", "width: 38em");
		$all_keys = array("partNumber", "partDescription", "partCategory", "manufacturer", "uom", "cost", "price", "pref_part", "hot_part", "matL", "quoteDate", "status", "active", "cut_sheet", "altPN");

		?>

		<div class='fixTableHead'>
			<table id="searchTable" class="standardTables">
				<colgroup>
					<col>

					<?php

					//loop and set column widths
					for ($i = 0; $i < sizeof($col_width); $i++) {


					?>

						<col>

					<?php

					}

					?>

				</colgroup>
				<thead>
					<tr id='searchTable_head'>
						<th> </th>

						<?php

						//loop and set column widths
						for ($i = 0; $i < sizeof($headers); $i++) {


						?>

							<th <?php if ($headers[$i] == "Part Number") echo "class = 'fixed-column' style = 'z-index: 30;" . $col_width[$i] . "'";
								else echo "style = '" . $col_width[$i] . "'" ?>><?= $headers[$i]; ?></th>

						<?php

						}

						?>
					</tr>
				</thead>
				<tbody style='text-align: center'></tbody>
			</table>
		</div>
	</div>

	<div id='shipping' class='tabcontent' style='display:none'>

		<table id='shipping_table' class='hub_tables'>
			<tr>
				<th>Shop Name</th>
				<td>
					<select id='ref_shop' class='shop_input custom-select' onChange="show_shipping(this)">
						<option></option>
						<option value="new">Create New Location</option>
						<?php

						//loop through locations
						for ($i = 0; $i < sizeof($shipping_locations); $i++) {

						?>

							<option value="<?= $shipping_locations[$i]['name']; ?>"><?= $shipping_locations[$i]['name']; ?></option>

						<?php

						}

						?>
					</select>
			</tr>
			<tr style='height: 10px'>
				<!-- blank space !-->
			</tr>
			<tr>
				<th>Shop Name</th>
				<td><input id='shop_name' class='shop_input'></td>
			</tr>
			<tr>
				<th>Shop Abv</th>
				<td><input id='shop_abv' class='shop_input'></td>
			</tr>
			<tr>
				<th>Street</th>
				<td><input id='shop_street' class='shop_input'></td>
			</tr>
			<tr>
				<th>City</th>
				<td><input id='shop_city' class='shop_input'></td>
			</tr>
			<tr>
				<th>State</th>
				<td><input id='shop_state' class='shop_input'></td>
			</tr>
			<tr>
				<th>Zip</th>
				<td><input id='shop_zip' class='shop_input'></td>
			</tr>
			<tr>
				<th>Recipient</th>
				<td><input id='shop_recipient' class='shop_input'></td>
			</tr>
			<tr>
				<th>Phone</th>
				<td><input id='shop_phone' class='shop_input'></td>
			</tr>
			<tr>
				<th>Email</th>
				<td><input id='shop_email' class='shop_input'></td>
			</tr>
			<tr id='edit_shop_row' style='visibility: collapse'>
				<td><button onClick='update_shop("edit")' form=''>Update Shipping Location</button></td>
			</tr>
			<tr id='add_shop_row' style='visibility: collapse'>
				<td><button onClick='update_shop("add")' form=''>Add New Shipping Location</button></td>
			</tr>
			<tr id='delete_shop_row' style='visibility: collapse'>
				<td><button onClick='update_shop("delete")' form=''>Remove Shipping Location</button></td>
			</tr>
		</table>

	</div>

	<div id='mo-po' class='tabcontent' style='display: none'>

		<table>
			<tr>
				<td>Project #:</td>
				<td><input type='text' id='search_project'></td>
			</tr>
			<tr>
				<td>MO #:</td>
				<td><input type='text' id='search_MO'></td>
			</tr>
			<tr>
				<td><button onclick='sort_by()'>Search</button></td>
			</tr>
		</table>

		<table id='edit_mo_table'>

		</table>
	</div>

	<div id='warehouse_setup' class='tabcontent' style='display:none'>

		<select id='warehouse_setup_shop' class='custom-select' onchange='update_staging_area(this)'>
			<option>OMA</option>
			<option>CHA</option>
		</select>

		<input id='warehouse_setup_new_area'>
		<button onclick='add_new_staging_area()'>Add New Area</button>
		<button onclick='save_staging_area()'>Save Staging Area</button>

		<div class='warehouse_grid_outline'>
			<ul id="warehouse_grid">
				<?php

				for ($i = 1; $i < 65; $i++) {

				?>
					<li class="warehouse_grid_option ui-state-default">[empty]</li>
				<?php
				}

				?>

			</ul>
		</div>
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
		const shipping_locations = <?= json_encode($shipping_locations); ?>,
			inventory = <?= json_encode($inventory); ?>,
			mo_requests = <?= json_encode($mo_requests); ?>,
			parts = <?php echo json_encode($parts); ?>,
			oem_array = <?php echo json_encode($oem); ?>,
			category_array = <?php echo json_encode($category); ?>,
			all_categories = <?php echo json_encode($all_categories); ?>,
			all_categories_full = <?php echo json_encode($all_categories_full); ?>,
			all_manufacturers = <?php echo json_encode($all_manufacturers); ?>,
			header_keys = <?php echo json_encode($header_keys); ?>,
			all_keys = <?php echo json_encode($all_keys); ?>,
			part_logs = <?= json_encode($part_logs); ?>,
			pq_overview = <?= json_encode($pq_overview); ?>,
			pq_detail = <?= json_encode($pq_detail); ?>,
			staging_areas = <?= json_encode($staging_areas); ?>;

		//transfer attribute objects
		var attributes_assignments = <?= json_encode($attributes_assignments); ?>,
			attributes_options = <?= json_encode($attributes_options); ?>,
			attributes_key = <?= json_encode($attributes_key); ?>,
			inv_locations = <?= json_encode($inv_locations); ?>,
			all_shops = <?= json_encode($all_shops); ?>;

		//used to tell if we need to start with new part entry
		const new_part = '<?= $new_part; ?>';
		const user_info = <?= json_encode($fstUser); ?>;

		$(function() {
			$("#warehouse_grid").sortable({
				placeholder: "ui-state-highlight"
			});
			$("#warehouse_grid").disableSelection();
		});

		$(document).on('click', '.remove', function() {

			// set element back to "empty"
			this.parentNode.innerHTML = "[empty]";

		});

		function add_new_staging_area() {

			// find last available empty element & update
			var options = u.class("warehouse_grid_option");

			for (var i = 0; i < options.length; i++) {
				if (options[i].innerHTML == "[empty]") {
					options[i].innerHTML = u.eid("warehouse_setup_new_area").value + "<span class = 'remove'>X</span>";
					break;
				}
			}
		}

		function update_staging_area(targ) {

			// Filter out information related to shop
			var shop_area = staging_areas.filter(function(a) {
				return a.shop == targ.value;
			});

			console.log(shop_area);

			// Go through all available grid options, reset to empty
			var options = u.class("warehouse_grid_option");

			for (var i = 0; i < options.length; i++) {
				options[i].innerHTML = "[empty]";
			}

			// Go through all filtered shop info, set options value based on coordinates
			for (var i = 0; i < shop_area.length; i++) {

				// Get position based on coordinates
				var position = parseInt(shop_area[i].x) + parseInt(shop_area[i].y) * 8;

				// Set location name of position
				options[position].innerHTML = shop_area[i].location_name + "<span class = 'remove'>X</span>";
			}
		}

		function save_staging_area() {

			// Loop through all area's
			var staging_area = [];
			var options = u.class("warehouse_grid_option");

			for (var i = 0; i < options.length; i++) {
				if (options[i].innerHTML != "[empty]") {
					var span_pos = options[i].innerHTML.indexOf("<span", 0);
					staging_area.push({
						location_name: options[i].innerHTML.substr(0, span_pos),
						x: i % 8,
						y: Math.floor(i / 8)
					})
				}
			}

			// Pass to PHP and save current state
			//initalize form data (will carry all form data over to server side)
			var fd = new FormData();

			// Add info so we know what we are doing on server side
			fd.append('tell', "save_staging_area");
			fd.append('shop', u.eid("warehouse_setup_shop").value);
			fd.append('user_info', JSON.stringify(user_info));
			fd.append('staging_area', JSON.stringify(staging_area));

			//access database
			$.ajax({
				url: 'terminal_hub_helper.php',
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

					// Alert success for user
					alert("The staging area has been updated.");
					window.location.reload();

				}
			});
		}

		//set options to parts array (renders autocomplete options)
		var options = {
			source: parts,
			minLength: 2
		};

		//choose selector (input with part as class)
		var selector = '.part_search';

		//on keydown, show autocomplete after 2 characters
		$(document).on('keydown.autocomplete', selector, function() {
			$(this).autocomplete(options);
		});

		/************FUNCTIONS**************/

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
						csvContent += custom_utc_to_local(pq_overview[i].requested) + ", "; //create to adjust UTC to local but keep in date format in excel file
						//csvContent+= pq_overview[i].requested + ", ";
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

		//convert utc to local
		function custom_utc_to_local(date) {

			//check for blank times
			if (date == "0000-00-00 00:00:00")
				return "";

			var date_local = new Date(date + ' UTC');

			//date & time, convert to central time zone
			var y = date_local.getFullYear(),
				m = date_local.getMonth() + 1,
				d = date_local.getDate(),
				hours = date_local.getHours(),
				minutes = date_local.getMinutes(),
				seconds = date_local.getSeconds();

			//var time = hours + ":" + minutes + ":" + seconds;
			var time = custom_time_format(hours, minutes)

			return m + "-" + d + "-" + y + " " + time;
		}

		//changes military time to standard
		function custom_time_format(hours, minutes) {

			//init time to be returned
			var timeValue;

			//use hours to check if this needs to be am or pm
			if (hours > 0 && hours <= 12) {
				timeValue = "" + hours;
			} else if (hours > 12) {
				timeValue = "" + (hours - 12);
			} else if (hours == 0) {
				timeValue = "12";
			}

			timeValue += (minutes < 10) ? ":0" + minutes : ":" + minutes; // get minutes
			timeValue += (hours >= 12) ? " PM" : " AM"; // get AM/PM

			// return value
			return timeValue;
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

		//handles searching for MO requests by MO and Project #
		function sort_by() {

			//clear out old rows
			document.querySelectorAll('.edit_mo_row').forEach(function(a) {
				a.remove();
			})

			//grab MO and PO numbers
			var mo_id = u.eid("search_MO").value,
				proj_num = u.eid("search_project").value;

			//loop through MO request
			for (var i = 0; i < mo_requests.length; i++) {

				//check conditions (add table row if true)
				if ((mo_id == mo_requests[i].mo_id || mo_id == "") && (proj_num == mo_requests[i].project_id || proj_num == ""))
					add_edit_table_row(i);

			}

		}

		//adds a row to edit_mo_table (according to array index)
		function add_edit_table_row(index) {

			//grab table
			var table = u.eid("edit_mo_table");

			//insert new row at bottom of table
			var row = table.insertRow(-1);
			row.classList.add("edit_mo_row")

			//MO
			var cell = row.insertCell(0);
			cell.innerHTML = mo_requests[index].mo_id;

			//Project #
			var cell = row.insertCell(1);
			cell.innerHTML = mo_requests[index].project_id;

			//Currnet Status (as dropdown)
			var cell = row.insertCell(2);
			cell.innerHTML = '<select class="custom-select" id = "select-' + mo_requests[index].id + '"><option value="Open">Open</option><option value="In Progress">In Progress</option><option value="Shipping Later">Shipping Later</option><option value="Closed">Closed</option></select>';
			u.eid("select-" + mo_requests[index].id).value = mo_requests[index].status;

			//update button
			var cell = row.insertCell(3);
			cell.innerHTML = '<button onclick = "update_mo(' + mo_requests[index].id + ')">Update</button>';

		}

		//handles updating given MO based on id 
		function update_mo(id) {

			//grab new status
			var status = u.eid("select-" + id).value;

			//init form data
			var fd = new FormData();

			//pass quote, type and filename over
			fd.append('status', status);
			fd.append('id', id);
			fd.append('tell', 'update_mo');

			//send info to ajax, set up handler for response
			$.ajax({
				url: 'terminal_hub_helper.php',
				type: 'POST',
				processData: false,
				contentType: false,
				data: fd,
				success: function(response) {

					//if we return an error, let the user know
					if (response != "") {
						alert(response);
					} else {
						alert("Status has been successfully updated.");
					}

				}
			});

		}

		//handles showing shipping info for a given location
		function show_shipping(targ) {

			//if targ is blank or new then set all inputs to blank
			if (targ.value == "" || targ.value == "new") {
				u.eid("shop_name").value = "";
				u.eid("shop_abv").value = "";
				u.eid("shop_street").value = "";
				u.eid("shop_city").value = "";
				u.eid("shop_state").value = "";
				u.eid("shop_zip").value = "";
				u.eid("shop_recipient").value = "";
				u.eid("shop_phone").value = "";
				u.eid("shop_email").value = "";

				//if this is new, show add location button, else hide all buttons
				if (targ.value == "new")
					u.eid("add_shop_row").style.visibility = "visible";

				u.eid("edit_shop_row").style.visibility = "collapse";
				u.eid("delete_shop_row").style.visibility = "collapse";
				return;
			}

			//look for index of shop location
			for (var i = 0; i < shipping_locations.length; i++) {
				if (targ.value == shipping_locations[i].name)
					break;
			}

			//check index
			if (i == shipping_locations.length) {
				alert("Something went wrong.")
				return;
			}

			//hide add_show_row/show edit
			u.eid("add_shop_row").style.visibility = "collapse";
			u.eid("edit_shop_row").style.visibility = "visible";
			u.eid("delete_shop_row").style.visibility = "visible";

			//update shop values with current values
			u.eid("shop_name").value = shipping_locations[i].name;
			u.eid("shop_abv").value = shipping_locations[i].abv;
			u.eid("shop_street").value = shipping_locations[i].address;
			u.eid("shop_city").value = shipping_locations[i].city;
			u.eid("shop_state").value = shipping_locations[i].state;
			u.eid("shop_zip").value = shipping_locations[i].zip;
			u.eid("shop_recipient").value = shipping_locations[i].recipient;
			u.eid("shop_phone").value = shipping_locations[i].phone;
			u.eid("shop_email").value = shipping_locations[i].email;
		}

		//handles updating or adding new shipping location
		//takes type = [add, edit, delete]
		function update_shop(type) {

			//if delete, send confirmation message to user
			if (type == "delete") {
				var message = "Are you sure you want to remove this location? (this cannot be undone) \n\n"
				message += "[OK] Delete Location\n";
				message += "[Cancel] Go Back";

				//send message to user
				if (!confirm(message))
					return;

			} else {
				//check to make sure there are no empty inputs
				document.querySelectorAll('.shop_input').forEach(function(a) {
					if (a.value == "") {
						alert("Please make sure all input is filled out");
						return;
					}
				})
			}

			//grab info and pass to server
			var old_name = u.eid("ref_shop").value,
				name = u.eid("shop_name").value,
				abv = u.eid("shop_abv").value,
				address = u.eid("shop_street").value,
				city = u.eid("shop_city").value,
				state = u.eid("shop_state").value,
				zip = u.eid("shop_zip").value,
				recipient = u.eid("shop_recipient").value,
				phone = u.eid("shop_phone").value,
				email = u.eid("shop_email").value;

			//init form data
			var fd = new FormData();

			//pass quote, type and filename over
			fd.append('old_name', old_name);
			fd.append('name', name);
			fd.append('abv', abv);
			fd.append('address', address);
			fd.append('city', city);
			fd.append('state', state);
			fd.append('zip', zip);
			fd.append('recipient', recipient);
			fd.append('phone', phone);
			fd.append('email', email);
			fd.append('tell', 'update_shop');
			fd.append('type', type);

			//send info to ajax, set up handler for response
			$.ajax({
				url: 'terminal_hub_helper.php',
				type: 'POST',
				processData: false,
				contentType: false,
				data: fd,
				success: function(response) {

					//if we return an error, let the user know
					if (response != "") {
						alert(response);
					} else {
						if (type == "add")
							alert("This location has been successfully added to our database.");
						else if (type == "edit")
							alert("This location has been successfully changed in our database.");
						else if (type == "delete")
							alert("This location has been successfully removed from our database.");

						window.location.reload();
					}

				}
			});
		}

		//handles searching for part in our catalog
		function search_part(targ) {

			//values to determine which buttons need to be shown
			var add_row = "visible",
				edit_row = "collapse";

			//update color of cell/hide new part dialog
			u.eid("ref_part").classList.remove("required_error");
			u.eid("new_part").style.display = "none";

			//look for index in inventory object
			for (var i = 0; i < inventory.length; i++) {
				if (targ.value.toLowerCase() == inventory[i].partNumber.toLowerCase())
					break;
			}

			//check to see if we found a match
			if (i == inventory.length) {
				u.eid("inv_description").value = "";
				u.eid("inv_category").value = "";
				u.eid("inv_manufacturer").value = "";
				u.eid("inv_uom").value = "";
				u.eid("inv_cost").value = "";
				u.eid("inv_price").value = "";
				u.eid("add_inv_row").style.visibility = add_row;
				u.eid("edit_inv_row").style.visibility = edit_row;
				return;
			}

			//if this is not a new part, change add/edit ops
			if (inventory[i].class == "catalog") {
				add_row = "collapse",
					edit_row = "visible";
			}
			//change color to denote not in catalog
			else {
				u.eid("ref_part").classList.add("required_error");
				u.eid("new_part").style.display = "block";
			}

			//show/hide needed buttons
			u.eid("add_inv_row").style.visibility = add_row;
			u.eid("edit_inv_row").style.visibility = edit_row;

			//if so, add values from inventory array
			u.eid("inv_description").value = inventory[i].partDescription;
			u.eid("inv_category").value = inventory[i].partCategory;
			u.eid("inv_manufacturer").value = inventory[i].manufacturer;
			u.eid("inv_uom").value = inventory[i].uom;
			u.eid("inv_cost").value = inventory[i].cost;
			u.eid("inv_price").value = inventory[i].price;
		}

		//handles updating other dropdown based on category
		z.update_dropdown = function(drop_down) {

			//init values needed
			var className, sel, option, rows, rowLoc, tempLen2, tempLen1 = 0,
				tempString, opt = 'update_manufacturer',
				previous, check_array = [],
				grab_array = [],
				blank_array = [];

			var targ = drop_down.value
			var opt = drop_down.id;

			//init return array
			var return_array = [];

			//depending on the option, grab necessary varialbe to do the job
			if (opt == 'manufacturer-dropdown') {
				className = "c-option";
				sel = u.eid("cat-dropdown");
				previous = u.eid("cat-dropdown").value;
				check_array = oem_array;
				grab_array = category_array;
				blank_array = all_categories;

			} else if (opt == 'cat-dropdown') {
				className = "m-option";
				sel = u.eid("manufacturer-dropdown");
				previous = u.eid("manufacturer-dropdown").value;
				check_array = category_array;
				grab_array = oem_array;
				blank_array = all_manufacturers;
				z.additional_critera(targ, 'search');
			}

			//clear current elements in the dropdown
			document.querySelectorAll('.' + className).forEach(function(a) {
				a.remove()
			})

			//if value read in is null or blank, reset the select menu
			if (targ == "" || targ == null) {
				return_array = blank_array;
			} else {

				//loop through inventory array and add any matches for the given critera 
				for (var i = 0; i < check_array.length; i++) {
					//check to see if we find any matches in the check array
					if (targ == check_array[i]) {
						return_array = z.check_and_add(return_array, grab_array[i]);
					}


				}

			}

			//sort array A-Z 
			return_array.sort((a, b) => a.localeCompare(b, undefined, {
				sensitivity: 'base'
			}));

			//add elements to dropdown
			//by default, create the first element which will be blank 
			option = document.createElement('option');
			option.classList.add(className);
			sel.appendChild(option);

			//look through return array and add to list
			for (var i = 0; i < return_array.length; i++) {

				option = document.createElement('option');
				option.value = return_array[i];

				//if category dropdown, search through category full and insert text node where there is a match
				if (opt == "manufacturer-dropdown") {
					for (var j = 0; j < all_categories_full.length; j++) {
						if (return_array[i] == all_categories[j]) {
							option.innerHTML = all_categories_full[j];
							break;
						}
					}
				}
				//else jsut insert innerHTML for manufacturer name
				else {
					option.innerHTML = return_array[i];
				}

				option.classList.add(className);
				sel.appendChild(option);

			}

			//reset original value
			sel.value = previous;

		}

		//handles adding additional search criteria drop downs based on part category
		z.additional_critera = function(category, type) {

			//clear out previous criteria
			document.querySelectorAll('.add-criteria-row-' + type).forEach(function(a) {
				a.remove()
			})

			//first search through attribute_assignment object to see if we have attributes for this part
			var index = attributes_assignments.findIndex(object => {
				return object.key.toLowerCase() == category.toLowerCase();
			});

			//no match
			if (index == -1) {
				if (type == "new-part") {
					u.eid("no-attr-row").style.visibility = "visible";
				}
				return;
			}

			//grab table/row_index
			var table = u.eid("parts-search-table");
			var row_index = 3;

			//switch if new part
			if (type == "new-part") {
				u.eid("no-attr-row").style.visibility = "collapse";
				table = u.eid("new-attributes-table");
				row_index = 1;
			}

			//loop through options and create dropdowns in table
			for (var i = 0; i < attributes_assignments[index].options.length; i++) {

				//insert new row and add classname to it
				var row = table.insertRow(row_index);
				row.classList.add("add-criteria-row-" + type);
				row_index++;

				//grab key description from attributes_key object
				var desc_index = attributes_key.findIndex(object => {
					return object.id == attributes_assignments[index].options[i];
				});

				//create cell & add description to line
				var cell = row.insertCell(0);
				cell.innerHTML = attributes_key[desc_index].description + ": ";

				//create select (with first option blank, & styling) to append to cell
				var select = document.createElement("select");
				select.appendChild(document.createElement("option"));

				//if new-part, add extra class
				if (type == 'new-part') {
					select.classList.add("custom-new-part");
					select.classList.add("new-attributes");
				}

				select.classList.add("custom-select-header");
				select.classList.add("search_attributes");
				select.id = type + "-" + attributes_key[desc_index].id;

				//match attributes_assignments options to attribute_options key
				var options_index = attributes_options.findIndex(object => {
					return object.key == attributes_assignments[index].options[i];
				});

				//loop attributes_options and add to drop_down to select
				for (var j = 0; j < attributes_options[options_index].options.length; j++) {

					//create option
					var option = document.createElement("option");
					option.value = attributes_options[options_index].options[j];
					option.innerHTML = attributes_options[options_index].options[j];

					//append to select
					select.appendChild(option);

				}

				//create next cell and add select
				var cell = row.insertCell(1);
				cell.appendChild(select);

			}

		}

		//returns array of unique values
		//array = array to be checked
		//check = value that needs to be checked (only add if unique)
		z.check_and_add = function(array, check) {

			//check for null or blank
			if (check == null || check == "")
				return array;

			//run through all array elements
			for (var i = 0; i < array.length; i++) {

				//if element already exists in array, return current array unchanged
				if (array[i].toLowerCase() == check.toLowerCase())
					return array;

			}

			//if we get through entire array, add element and return
			array.push(check);
			return array;


		}

		//global to hold current search id's (passes to csv download)
		var search_indexes = [];

		//handles grabbing parts related to category and manufacturer selected
		z.grabParts = function() {

			//reset current transfer idnex
			current_transfer = -1;

			//clear indexes 
			search_indexes = [];

			//remove everything from current table
			document.querySelectorAll('.invBody').forEach(function(a) {
				a.remove();
			})

			//also remove edit rows
			document.querySelectorAll('.edit_row').forEach(function(a) {
				a.remove();
			})

			//Initialize the ranges that will be searched in ajax query
			var category = u.eid('cat-dropdown').value,
				partNumber,
				manufacturer = u.eid('manufacturer-dropdown').value,
				part_number = u.eid("search_part").value;

			//if all are blank, return (do nothing)
			if (category == "" && manufacturer == "" && part_number == "")
				return;

			//check for any search attributes available
			var attributes = [];
			var class_attributes = u.class("search_attributes");

			for (var i = 0; i < class_attributes.length; i++) {

				//only push if user has requested to filter by something
				if (class_attributes[i].value != "")
					attributes.push({
						type: class_attributes[i].id.substr(7),
						value: class_attributes[i].value
					});
			}

			//cycle through parts and grab any that match our search critera
			for (var i = 0; i < inventory.length; i++) {

				//if part_number is not blank, we only need to check part
				if (part_number != "") {
					//now check if we have a match
					if (part_number == inventory[i].partNumber && inventory[i].class == "catalog") {
						add_row(i);
						break;
					}
				}
				//check category and manufacturer (if either is null or blank, continue as if they are a match)
				else if ((category == null || category == "" || category.toLowerCase() == inventory[i].partCategory.toLowerCase()) &&
					(manufacturer == null || manufacturer == "" || manufacturer.toLowerCase() == inventory[i].manufacturer.toLowerCase()) && (inventory[i].class == "catalog")) {

					//at this point, set add bool to true
					var add = true;

					//cycle through attributes and see if we find any matches
					for (var j = 0; j < attributes.length; j++) {
						if (inventory[i][attributes[j]['type']] != attributes[j]['value'])
							add = false;
					}

					//add row to table (if we pass attribute check)
					if (add)
						add_row(i);
				}
			}

			//update search list
			target_rows = $('#searchTable tbody tr');

		}

		//function to add a single row to the table
		function add_row(index) {

			//push index
			search_indexes.push(index);

			//init vars needed
			var table = u.eid("searchTable").getElementsByTagName('tbody')[0],
				input, target_list;

			//if we pass checks, add to table
			//insert new row and add classname to it
			var row = table.insertRow(-1);
			row.classList.add("invBody");

			//insert first column, allows user to select part
			var cell = row.insertCell(0);
			cell.innerHTML = "<button onclick='transfer_part(this)' id = 'edit_" + index + "'>&#9660;</button>";

			//set offset (used to offset column # if attributes are used)
			var column_offset = 0;

			//loop through header keys and add to table
			for (var i = 0; i < header_keys.length; i++) {

				//create cell
				var cell = row.insertCell(i + 1 + column_offset);

				//depending on key, this will be treated differently
				//number
				if (header_keys[i] == "cost" || header_keys[i] == "price" || header_keys[i] == "matL") {
					input = init_inputs("input", header_keys[i]);
					input.type = "number";
					input.value = inventory[index][header_keys[i]];
				}
				//checkbox
				else if (header_keys[i] == "hot_part" || header_keys[i] == "pref_part" || header_keys[i] == "active") {
					input = init_inputs("input", header_keys[i]);
					input.type = "checkbox";

					//check true/false
					if (inventory[index][header_keys[i]].toLowerCase() == "true")
						input.checked = true;
				}
				//select list
				else if (header_keys[i] == "partCategory" || header_keys[i] == "manufacturer" || header_keys[i] == "uom" || header_keys[i] == "status") {

					//set input
					input = init_inputs("select", header_keys[i]);

					//refresh target list
					target_list = [];

					//select target list
					if (header_keys[i] == "partCategory")
						target_list = all_categories;
					else if (header_keys[i] == "manufacturer")
						target_list = all_manufacturers;
					else if (header_keys[i] == "uom")
						target_list = ["EA", "LF", "BDL"];
					else if (header_keys[i] == "status")
						target_list = ["Active", "Special Order", "EOL", "Discontinued"];

					//set first option as blank if not category, uom, or manufacturer
					if (header_keys[i] != "partCategory" && header_keys[i] != "manufacturer" && header_keys[i] != "uom")
						input.appendChild(document.createElement("option"));

					//cycle through target list
					for (var j = 0; j < target_list.length; j++) {

						//set option with value and innerhtml
						var option = document.createElement("option");
						option.value = target_list[j];
						option.innerHTML = target_list[j];
						input.appendChild(option);

					}

					//set value
					if (inventory[index][header_keys[i]] != null)
						input.value = inventory[index][header_keys[i]].trim();

					//add class for style
					input.classList.add("custom-select-header");

				}
				//date
				else if (header_keys[i] == "quoteDate") {
					input = init_inputs("input", header_keys[i]);
					input.type = "date";
					input.value = inventory[index][header_keys[i]];
				}
				//standard input
				else {
					input = init_inputs("input", header_keys[i]);
					input.type = "text";
					input.value = inventory[index][header_keys[i]];
				}

				//if part number or cost, set to readonly
				if (header_keys[i] == "partNumber") {
					cell.classList.add("fixed-column");
					input.readOnly = true;
				} else if (header_keys[i] == "cost")
					input.readOnly = true;

				//add event listener
				input.addEventListener("change", check_add);

				//append input to cell
				cell.appendChild(input);

				//if material logistics, add checkbox
				if (header_keys[i] == "matL") {

					//adjust matL width
					input.style.width = "60%";
					input = init_inputs("input", header_keys[i]); //create new input for checkbox
					input.type = "checkbox";

					//add event listener
					input.addEventListener("change", check_add);

					//append input to cell
					cell.appendChild(input);

					//check value of matL
					if (inventory[index][header_keys[i]] == -1) {
						input.checked = true;
						cell.childNodes[0].readOnly = true;
						cell.childNodes[0].value = "";
					}

				}

			}

		}

		//handles initializing inputs
		function init_inputs(type, class_opt) {

			var input = document.createElement(type);
			input.classList.add(class_opt);
			input.classList.add("inventory_input");

			return input;

		}

		//global used to handle parts that need to be updated on save
		var update_parts = [];

		//handles checking part # and adding to global array
		//param 1 = event OR input passed by js (if event, use 'this' otherwise use the input passed)
		function check_add(e = null) {

			//init field
			var field;

			//if targ is not null, use as field, otherwise use 'this'
			if (e instanceof Event)
				field = this;
			else
				field = e;

			//step 1: get part number from ast & class from field
			var td, tr, cell, partNumber;
			var targ_class = field.classList[0];
			if (targ_class == "inv_locations_check") {
				partNumber = u.eid("edit_part").value;
			} else {
				td = field.parentNode;
				tr = td.parentNode;
				cell = tr.childNodes[1];
				partNumber = cell.childNodes[0].value;
			}

			//if partNumber = this.value, then we can use edit_part id to get target part (we know this is taking place inside the open dialog)
			if (partNumber == $(this)[0].value)
				partNumber = u.eid("edit_part").value;

			//step 2: check update_parts to see if we already have this part number saved
			for (var i = 0; i < update_parts.length; i++) {
				if (update_parts[i].partNumber == partNumber)
					break;
			}

			//step 3a: if we did not find a match, i will equal length, then create a new entry
			if (i == update_parts.length) {

				var tempArray = {
					partNumber: partNumber,
					partDescription: "",
					partCategory: "",
					manufacturer: "",
					uom: "",
					cost: "",
					price: "",
					altPN: "",
					subPN: "",
					pref_part: "",
					hot_part: "",
					active: "",
					matL: "",
					quoteDate: "",
					status: "",
					endpoint_a: "",
					endpoint_b: "",
					endpoint_a_type: "",
					endpoint_b_type: "",
					cable_type: "",
					cable_length: "",
					cable_jacket_rating: "",
					diameter: "",
					fiber_endpoint_a: "",
					fiber_endpoint_b: "",
					max_wattage: "",
					jumper_cable_diameter: "",
					rack_width: "",
					connector_type: "",
					unit_length: "",
					db_association: "",
					gender_type: "",
					connector_install_type: "",
					connection_style: "",
					rating: "",
					fiber_count: "",
					conductor_count: "",
					radio_configuration: "",
					antenna_style: "",
					length_l: "",
					width_w: "",
					height_h: "",
					depth_d: "",
					n_way: "",
					color: "",
					gauge_size: "",
					cut_sheet: "",
					inv_locations: get_current_inv_locations(partNumber)
				};

				//check if targ_class is pref_part or hot_list (treated a little differently)
				if (targ_class == "pref_part" || targ_class == "hot_part" || targ_class == "active") {

					//if it is checked, set true
					if (field.checked)
						tempArray[targ_class] = "TRUE";
					//else set false
					else
						tempArray[targ_class] = "FALSE";

				}
				//material logistics (need to see if checkbox is checked AND what the value is)
				else if (targ_class == "matL") {

					//if checked, set input to readonly, set update_parts to -1, set input to null
					if (td.childNodes[1].checked) {
						td.childNodes[0].readOnly = true;
						tempArray[targ_class] = -1;
						td.childNodes[0].value = "";
					}
					//if not, set input readonly to false, set update_parts input val
					else {
						td.childNodes[0].readOnly = false;
						tempArray[targ_class] = parseFloat(td.childNodes[0].value);
					}

				} else if (targ_class == "subPN" || targ_class == "reels") {
					//run function to get subs_string
					tempArray[targ_class] = get_sub_string('existing');
				} else if (targ_class == "inv_locations_check") {

					//update temp with function
					tempArray.inv_locations = get_current_inv_locations(partNumber);

				} else {
					//update temp
					tempArray[targ_class] = field.value;
				}

				//push to global
				update_parts.push(tempArray);

			}
			//step 3b: if did, update target value
			else {

				//check if targ_class is pref_part or hot_list (treated a little differently)
				if (targ_class == "pref_part" || targ_class == "hot_part" || targ_class == "active") {

					//if it is checked, set true
					if (field.checked)
						update_parts[i][targ_class] = "TRUE";
					//else set false
					else
						update_parts[i][targ_class] = "FALSE";

				}
				//material logistics (need to see if checkbox is checked AND what the value is)
				else if (targ_class == "matL") {

					//if checked, set input to readonly, set update_parts to -1, set input to null
					if (td.childNodes[1].checked) {
						td.childNodes[0].readOnly = true;
						update_parts[i][targ_class] = -1;
						td.childNodes[0].value = "";
					}
					//if not, set input readonly to false, set update_parts input val
					else {
						td.childNodes[0].readOnly = false;
						update_parts[i][targ_class] = parseFloat(td.childNodes[0].value);
					}

				} else if (targ_class == "reels" || targ_class == "subPN") {
					//run function to get subs_string
					update_parts[i][targ_class] = get_sub_string('existing');
				} else if (targ_class == "inv_locations_check") {

					//update temp with function
					update_parts[i].inv_locations = get_current_inv_locations(partNumber);

				} else {
					//update temp
					update_parts[i][targ_class] = field.value;
				}

			}

			//update inventory object
			var inv_index = inventory.findIndex(object => {
				return object.partNumber.toLowerCase() == partNumber.toLowerCase();
			});

			//save in inventory
			if (targ_class != "inv_locations_check")
				inventory[inv_index][targ_class] = update_parts[i][targ_class];

		}

		/**@author Alex Borchers
		 * Gets list of current inv_locations that are on the screen
		 * @param part {string} part number, passed so we can update inv_locations
		 * @returns {object} 'location' => true/false 
		 */
		function get_current_inv_locations(part) {

			// init array to be returned
			var current_locations = {};

			// loop through class of inv_locations on screen
			var locs = u.class("inv_locations_check");

			// if length is 0, default to current settings in inv_locations array
			if (locs.length == 0) {
				// loop through all shops
				for (var i = 0; i < all_shops.length; i++) {

					// check if we have a shop / part combo in inv_locations
					var loc_index = inv_locations.findIndex(object => {
						return object.partNumber.toLowerCase() == part.toLowerCase() && object.shop == all_shops[i];
					});

					if (loc_index == -1)
						current_locations[all_shops[i]] = false;
					else
						current_locations[all_shops[i]] = true;

				}
			} else {
				document.querySelectorAll('.inv_locations_check').forEach(function(a) {
					current_locations[a.id] = a.checked

					// update global (inv_locations)
					// (1) check for index
					var index = inv_locations.findIndex(object => {
						return object.partNumber.toLowerCase() == part.toLowerCase() && object.shop == a.id;
					});

					// if checked, add if we did not find an index
					if (a.checked && index == -1) {
						inv_locations.push({
							shop: a.id,
							partNumber: part
						})
					}
					// if not checked, make sure we remove if we found an index
					else if (!a.checked && index != -1) {
						inv_locations.splice(index, 1);
					}
				})
			}

			console.log(current_locations);

			return current_locations;

		}

		//handles formatting subs list into string seperated by comma's
		function get_sub_string(type) {

			//part substitutes
			var subs = u.class(type + "_subs_list"),
				subs_string = "";

			//loop through and create subs list CSV to insert into db
			for (var i = 0; i < subs.length; i++) {

				//treat last differnet
				if (i == subs.length - 1)
					subs_string += subs[i].value;
				else
					subs_string += subs[i].value + ",";

			}

			//send back
			return subs_string;

		}

		//handles saving any updated parts
		function save_updates() {

			//if update_parts is empty do nothing
			if (update_parts.length == 0) {
				alert("There are currently no parts that have changed.");
				return;
			}

			//else pass to ajax

			//loop through update parts and update any parts that have blanks
			for (var i = 0; i < update_parts.length; i++) {

				//get index in parts array
				var index = parts.indexOf(update_parts[i].partNumber);

				//update any blank spaces in array
				for (var j = 0; j < all_keys.length; j++) {

					if (update_parts[i][all_keys[j]] == "")
						update_parts[i][all_keys[j]] = inventory[index][all_keys[j]];

				}

				//also look through attributes and update any blank spaces
				for (var j = 0; j < attributes_options.length; j++) {

					if (update_parts[i][attributes_options[j].key] == "")
						update_parts[i][attributes_options[j].key] = inventory[index][attributes_options[j].key];

				}

				//set subs list
				update_parts[i]['subs_list'] = inventory[index]['subPN'];

			}

			console.log(update_parts);

			//init form data
			var fd = new FormData();

			//transfer standard_info (header_keys) & part attributes (attributes_options) to php
			fd.append('standard_info', JSON.stringify(all_keys));
			fd.append('attributes', JSON.stringify(attributes_options));
			fd.append('all_shops', JSON.stringify(all_shops));

			//transfer update_parts to json
			fd.append('update_parts', JSON.stringify(update_parts));

			//transfer user info
			fd.append('user_info', JSON.stringify(user_info));

			//tell * type
			fd.append('tell', 'update_inv');
			fd.append('type', 'save');

			//send info to ajax, set up handler for response
			$.ajax({
				url: 'terminal_hub_helper.php',
				type: 'POST',
				processData: false,
				contentType: false,
				data: fd,
				success: function(response) {

					//if we return an error, let the user know
					if (response != "") {
						alert(response);
						console.log(response);
					} else {
						//alert success to user
						alert("All changes have been saved.");

						//reset update parts
						update_parts = [];

					}

				}
			});

		}

		//handles reading in CSV and updating parts
		function process_csv() {

			//grab file from id
			var file = $('#parts_csv')[0].files[0];

			//initialize form elements
			var fd = new FormData();
			fd.append('theFile', file);

			$.ajax({
				url: 'terminal_hub_csv.php',
				type: 'POST',
				processData: false,
				contentType: false,
				data: fd,
				success: function(response) {

					//decode response
					var message = $.parseJSON(response);

					console.log(message);

					//check length for errors
					if (message[0].length > 0) {
						var errors = "The following errors were found: \n";

						//outer loop for row numbers
						for (var i = 0; i < message[0].length; i++) {
							errors += "Row " + message[0][i] + ": (";

							//inner loop for columns that gave issues
							for (var j = 0; j < message[1][i].length; j++) {

								//treat last row differently
								if (j == message[1][i].length - 1)
									errors += message[1][i][j] + ")\n";
								else
									errors += message[1][i][j] + ", ";

							}

						}

						//alert error message
						alert(errors);
						//u.eid("parts_csv").value = "";
						window.location.reload();

					} else {
						alert("The parts have been successfully updated.");
						window.location.reload();
					}

				}
			});

		}

		//handles csv output for BOM
		function download_csv() {

			//initialize csvContent to export csv
			let csvContent = "data:text/csv;charset=utf-8,";

			//add headers to CSV
			//loop through all_keys first (this is basic info like part number, description, etc.)
			for (var i = 0; i < all_keys.length; i++) {
				csvContent += all_keys[i] + ",";
			}

			//loop through attribute_options second (this is all attributes like enpoint_a, b_ etc.)
			for (var i = 0; i < attributes_options.length; i++) {

				//handle last element differently (no comma)
				if (i == attributes_options.length - 1)
					csvContent += attributes_options[i].key + "\r\n";
				else
					csvContent += attributes_options[i].key + ",";
			}

			//if blank, add all indexes
			if (search_indexes.length == 0) {
				for (var i = 0; i < inventory.length; i++) {
					if (inventory[i].class = "catalog")
						search_indexes.push(i);
				}
			}

			//loop through indexes and add to csv 
			for (var i = 0; i < search_indexes.length; i++) {

				//init array to pass to csv
				var csvArray = [];

				//remove comma's and #'s from strings (loop through all_keys and attribute options to do so)
				for (var j = 0; j < all_keys.length; j++) {
					csvArray.push(scrub_string(inventory[search_indexes[i]][all_keys[j]]));
				}

				for (var j = 0; j < attributes_options.length; j++) {
					csvArray.push(scrub_string(inventory[search_indexes[i]][attributes_options[j].key]));
				}

				//set csv content (loop through csv array)
				for (var j = 0; j < csvArray.length; j++) {

					//replace new lines with a blank
					csvArray[j] = csvArray[j].replace(/[\r\n]/gm, '')

					//treat the last one differently (no comma, move to next row)
					if (j == csvArray.length - 1)
						csvContent += csvArray[j] + "\n";
					else
						csvContent += csvArray[j] + ',';
				}

			}

			//set encoded uri for download, push to link, name and force download
			var encodedUri = encodeURI(csvContent);
			var link = u.eid("hold_template");
			link.setAttribute("href", encodedUri);
			link.setAttribute("download", "partdump.csv");
			link.click();
		}

		//handle sanitizing string
		function scrub_string(targ) {

			//check for blanks
			if (targ == "" || targ == null || targ == " ")
				return " ";

			//used to remove unfriendly characters
			var regexp = new RegExp('#', 'g');
			targ = targ.replace(regexp, '');
			targ = targ.replace(/,/g, ';');
			targ = targ.replace('\r', '');

			return targ;
		}

		//used to check if existing part 
		function check_existing(part) {

			//check inventory to see if we have part in inventory OR if a user has entered previously
			var index = inventory.findIndex(object => {
				return object.partNumber.toLowerCase() == part.toLowerCase();
			});

			//if -1 return, else check class
			if (index == -1) {
				//enable button/hide error
				u.eid("existing_part").style.display = "none";
				u.eid("add_part_button").disabled = false;
				return;
			}

			if (inventory[index].class == "catalog") {
				u.eid("existing_part").style.display = "block";
				u.eid("add_part_button").disabled = true;

				//alert("This part is already in the Web FST inventory catalog.");
				return;
			} else {
				//enable button/hide error
				u.eid("existing_part").style.display = "none";
				u.eid("add_part_button").disabled = false;

				//set any info that we already have
				u.eid("new_description").value = inventory[index].description;
				u.eid("new_category").value = inventory[index].category;
				u.eid("new_manufacturer").value = inventory[index].manufacturer;
				u.eid("new_uom").value = inventory[index].uom;
				u.eid("new_cost").value = inventory[index].cost;

				//update attributes
				z.additional_critera(u.eid("new_category").value, "new-part");

			}
		}

		//used to open add new part dialog box
		$('#add_new_part').on('click', function() {

			//fill in part number based on field
			u.eid("new_part").value = u.eid("search_part").value;

			//get initial part attributes added
			check_existing(u.eid("new_part").value);
			z.additional_critera(u.eid("new_category").value, "new-part");

			//adjust size of dialog
			$("#add_new_part_dialog").dialog({
				width: "auto",
				height: "auto",
				dialogClass: "fixedDialog",
				close: function(event) {

					//set dialog to default
					set_add_new_default();
				}
			});
		});

		//handles setting all add_new dialog inputs to default
		function set_add_new_default() {

			//class new_field contains all regular inputs in "add part dialog"
			document.querySelectorAll('.new_field').forEach(function(a) {
				a.value = "";
			})

			//update status & material logistics defaults
			u.eid("new_matL").value = "";
			u.eid("new_matL").readOnly = true;
			u.eid("new_matL_check").checked = true;
			u.eid("new_status").value = "Active";

			//remove any subs in the subs list
			document.querySelectorAll('.sub_row').forEach(function(a) {
				a.remove();
			})

		}

		//used to load in part to new part dialog (found in $_GET['newPart'])
		function start_with_part(id) {

			//open dialog
			u.eid("add_new_part").click();

			//get index
			var index = inventory.findIndex(object => {
				return object.id == id;
			});

			debugger

			//if index = -1, let user know that we did not find a match
			if (index == -1) {
				alert("The part you are looking for is not in our records, please enter the part manually.");
				return;
			}

			//load in to input and check
			u.eid("new_part").value = inventory[index].partNumber;
			check_existing(inventory[index].partNumber);
		}

		//holds current transfer
		var current_transfer = -1;

		//handles transfering part to edit dialog
		function transfer_part(targ) {

			//delete the previous edit row if applicable
			document.querySelectorAll('.edit_row').forEach(function(a) {
				a.remove();
			})

			//parse out index from id
			var index = targ.id.substr(5);
			index = parseInt(index);

			//check current transfer (if not -1, update previous and set to current)
			if (current_transfer == index) {
				//reset current_transfer, reset caret
				u.eid("edit_" + index).innerHTML = "&#9660;";
				current_transfer = -1;
				return;
			} else if (current_transfer != -1) {
				//reset caret
				u.eid("edit_" + current_transfer).innerHTML = "&#9660;";
			}

			//update current edit caret & current_transfer
			u.eid("edit_" + index).innerHTML = "&#9650;";
			current_transfer = index;

			//get row index based on button clicked
			var row_index = targ.parentNode.parentNode.rowIndex;

			//grab target table
			var table = u.eid("searchTable").getElementsByTagName('tbody')[0];

			//add row
			var row = table.insertRow(row_index);
			row.classList.add("edit_row");
			var cell = row.insertCell(0);
			cell.colSpan = "11";

			//add table to row to edit a given part number
			var attributes_table = render_table('basic');
			var search_table = render_table('search_criteria', index);
			var subs_table = render_table('part_subs', index);
			var inv_locations = render_table('inv_locations', index);
			var logs = "<h3 id = 'part_log_head'><button onclick = 'toggle_logs(this)'>+</button> Part Logs</h3><div id='part_log'></div>";
			//var reels_table = render_table('reels', index);

			//set inner HTML to rendered tables
			cell.innerHTML = attributes_table + search_table + subs_table + inv_locations + logs;

			//update values based on assigned id's
			u.eid("edit_part").value = inventory[index].partNumber;
			u.eid("edit_category").value = inventory[index].partCategory;
			u.eid("edit_manufacturer").value = inventory[index].manufacturer;
			u.eid("edit_uom").value = inventory[index].uom.trim();
			u.eid("edit_cut_sheet").value = inventory[index].cut_sheet;
			u.eid("edit_altPN").value = inventory[index].altPN;

			//add attribute values if available
			//get attribute index (shows index)
			var attribute_index = attributes_assignments.findIndex(object => {
				return object.key.toLowerCase() == inventory[index].partCategory.toLowerCase();
			});

			//if we found an attribute index, loop through and add to table
			if (attribute_index != -1) {

				//loop through options and write values to screen
				for (var i = 0; i < attributes_assignments[attribute_index].options.length; i++) {
					var key = attributes_assignments[attribute_index].options[i];
					u.eid(key).value = inventory[index][key];
				}

			}

			//parse subs into array based on comma
			var result = [];

			if (inventory[index].subPN != null)
				result = inventory[index].subPN.split(",");

			//get subs list class elements
			var subs_list = document.getElementsByClassName("existing_subs_list");

			//update results in table
			for (var i = 0; i < result.length; i++) {

				//check result value
				if (result[i] != "")
					subs_list[i].value = result[i];
			}

			//parse reels into array based on comma
			/*
			if (inventory[index].reels != null){
				var length = inventory[index].reels_length.split(",");
				var location = inventory[index].reels_location.split(",");
			
				//get subs list class elements
				var reels_length_list = u.class("reels_length_list");
				var reels_location_list = u.class("reels_location_list");

				//update results in table
				for (var i = 0; i < result.length; i++){

					//check length value
					if (length[i] != "")
						reels_length_list[i].value = length[i];
					
					//check location value
					if (location[i] != "")
						reels_location_list[i].value = location[i];
				}
			}
			*/

			//initialize any part logs
			init_part_logs(inventory[index].partNumber);

			//add listeners to inputs
			render_listeners();

		}

		//handles rendering listeners
		function render_listeners(type = null) {

			//add event listener to fields/selects/checks
			$('#searchTable').find('input, select, textarea').each(function() {
				this.addEventListener("change", check_add);
			});
			//add event listener to buttons
			//$('#searchTable').find('button').each(function(){
			//	this.addEventListener("click", check_add);
			//});

		}

		//handles rendering table (currently done in html, could be transitioned to js)
		function render_table(type, index = null) {

			//init table
			var table = "";

			//depending on type, bulid table
			if (type == "basic") {

				//init read only
				var read_only = "readonly",
					button = ""

				//create table
				table = "<table class = 'partTables'>";
				table += "<tr><th colspan = '2'>Basic Part Info</th></tr>"
				table += "<tr><td class = 'table_td'>Part Number</td><td><input class = 'partNumber edit_field' id = 'edit_part' " + read_only + "></td></tr>";

				//add event listener to partCategory dropdown if adding a new part
				var event_list = "onchange = 'z.additional_critera(this.value, 'new-part')'";
				table += "<tr><td class = 'table_td'>Part Category</td><td><select class = 'partCategory custom-select-header' id = 'edit_category' " + event_list + ">";

				//create part category options on a loop
				for (var i = 0; i < all_categories.length; i++) {
					table += "<option value = '" + all_categories[i] + "'>" + all_categories[i] + "</option>";
				}

				table += "</select></td></tr>";
				table += "<tr><td class = 'table_td'>Manufacturer</td><td><select class = 'manufacturer custom-select-header' id = 'edit_manufacturer'>";

				//create manufacturer options on a loop
				for (var i = 0; i < all_manufacturers.length; i++) {
					table += "<option value = '" + all_manufacturers[i] + "'>" + all_manufacturers[i] + "</option>";
				}

				table += "</select></td></tr>";
				table += "<tr><td class = 'table_td'>UOM</td><td><select class = 'uom custom-select-header' id = 'edit_uom'><option>EA</option><option>LF</option><option>BDL</option></select></td></tr>";
				table += "<tr><td class = 'table_td'>Cut Sheet Link</td><td><input class = 'cut_sheet edit_field' id = 'edit_cut_sheet'></td></tr>";
				table += "<tr><td class = 'table_td'>Alternate Part Number</td><td><input class = 'altPN edit_field' id = 'edit_altPN'></td></tr>";

				//add button (if applicable) and close table
				table += button;
				table += "</table>";

			} else if (type == "search_criteria") {

				//create table
				table = "<table class = 'partTables' id = 'part-attributes-table'>";
				table += "<tr><th colspan = '2'>Part Attributes</th></tr>";

				//only if not a new part					
				//get attribute index (shows index)
				var attribute_index = attributes_assignments.findIndex(object => {
					return object.key.toLowerCase() == inventory[index].partCategory.toLowerCase();
				});

				//if we found an attribute index, loop through and add to table
				if (attribute_index != -1) {

					//loop through attribute assignments and add dropdowns for any matching criteria
					for (var i = 0; i < attributes_assignments[attribute_index].options.length; i++) {

						//refresh target list
						var target_list = [];

						//get options index (index that points towards array of options based on attribute)
						var options_index = attributes_options.findIndex(object => {
							return object.key.toLowerCase() == attributes_assignments[attribute_index].options[i].toLowerCase();
						});

						//select target list
						target_list = attributes_options[options_index].options;

						//get header from attributes_key
						var header_index = attributes_key.findIndex(object => {
							return object.id.toLowerCase() == attributes_assignments[attribute_index].options[i].toLowerCase();
						});

						//for each criteria generate the description cell
						table += "<tr><td class = 'table_td'>" + attributes_key[header_index].description + "</td><td><select class = '" + attributes_key[header_index].id + " custom-select-header' id = '" + attributes_key[header_index].id + "'><option></option>";

						//loop through search_dropdowns to generate options for select list
						for (var j = 0; j < target_list.length; j++) {
							table += "<option value = '" + target_list[j] + "'>" + target_list[j] + "</option>";
						}

						//close select, cell, and row
						table += "</select></td></tr>";

					}

				} else {
					table += "<tr><td>There are no attributes that apply to this part.</td></tr>";
				}

				//close table
				table += "</table>";

			} else if (type == "part_subs") {

				//create table
				table = "<table class = 'partTables' id = 'subs_table_existing'>";
				table += "<tr><th colspan = '2'>Subs List</th></tr>";
				table += "<tr><td><button onclick = 'add_sub(\"existing\")' >Add Sub</button></td><td><input class = 'subPN edit_field part_search' id = 'existing_sub' placeholder = 'Substitute Part'></td></tr>";
				table += "<tr style = 'height: 2em;'></tr>"

				//init result
				var result = [];

				//parse subs into array based on comma (only if not add part)
				if (inventory[index].subPN != null)
					result = inventory[index].subPN.split(",");

				//loop through existing subs that we have
				for (var i = 0; i < result.length; i++) {

					//check to make sure it is not blank
					if (result[i] != "")
						table += "<tr class = 'sub_row'><td class = 'table_td'><button style = 'float: right;' onclick = 'remove_sub_or_reel(this)'>X</button></td><td><input class = 'edit_field existing_subs_list' readonly></td></tr>";

				}

				//close table
				table += "</table>";
			} else if (type == "inv_locations") {

				//create table (<div> in this case)
				table = "<div class = 'inv_locations_div'>";

				// loop through all shops
				for (var i = 0; i < all_shops.length; i++) {

					// add Shop
					table += "(" + all_shops[i];

					// check if we have a shop / part combo in inv_locations
					var loc_index = inv_locations.findIndex(object => {
						return object.partNumber.toLowerCase() == inventory[index].partNumber.toLowerCase() && object.shop == all_shops[i];
					});

					// create shop & <input> combo based on 
					if (loc_index == -1)
						table += "<input id = '" + all_shops[i] + "' type = 'checkbox' class = 'inv_locations_check' >)  ";
					else
						table += "<input id = '" + all_shops[i] + "' type = 'checkbox' class = 'inv_locations_check' checked>)  ";

				}

				//close div
				table += "</div>";
			}
			/*
			else if (type == "reels"){
				
				//create table
				table ="<table class = 'partTables' id = 'reels_table'>";
				table += "<tr><th colspan = '3'>Reels</th></tr>";
				table += "<tr><td><button onclick = 'add_reel()'>Add Reel</button></td><td><input class = 'reels edit_field' id = 'new_reel_length' placeholder = 'Reel Length' style = 'width: 10em;'></td><td><input class = 'reels edit_field' id = 'new_reel_location' placeholder = 'Reel Location' style = 'width: 10em;'></td></tr>";
				table += "<tr style = 'height: 2em;'></tr>"
				
				//init result
				var result = "";
				
				//parse subs into array based on comma (only if not add part)
				if (index != "add_part" && inventory[index].reels != null)
					result = inventory[index].reels.split(",");
												
				//loop through existing subs that we have
				for (var i = 0; i < result.length; i++){
					
					//check to make sure it is not blank
					if (result[i] != "")
						table += "<tr class = 'reels_row'><td class = 'table_td'><button style = 'float: right;' onclick = 'remove_sub_or_reel(this)'>X</button></td><td><input class = 'edit_field reels_length_list' readonly></td><td><input class = 'edit_field reels_location_list' readonly></td></tr>";					
					
				}
				
				//close table
				table += "</table>";
			}
			*/

			return table;

		}

		//creates part logs in accordion
		//param 1 = partNumber we need logs for
		function init_part_logs(partNumber) {

			//init any variables needed
			var previous_log = "",
				h3, div, li, ul;

			//create ul to be added to throughout
			ul = document.createElement("ul");

			//loop through logs and generate content
			part_logs.forEach((log) => {

				//see if we have a match for part number
				if (log.partNumber.toLowerCase() == partNumber.toLowerCase()) {

					//create first li and append
					li = document.createElement("li");
					li.appendChild(document.createTextNode(log.description + " on " + z.utc_to_local(log.time_stamp) + " (" + log.name + ")"));

					//append to ul
					ul.appendChild(li);
				}


			});

			//append elements to body
			u.eid("part_log").appendChild(ul);
		}

		//handles toggling project logs (showing and hiding)
		//param 1 = tell.innerHTML = (+ / -)
		function toggle_logs(tell) {

			//based on what is in the button, we need to show or hide info
			if (tell.innerHTML == "+") {
				tell.innerHTML = "-";
				u.eid("part_log").style.display = "block";
			} else {
				tell.innerHTML = "+";
				u.eid("part_log").style.display = "none";
			}

		}

		//formats UTC time to local
		//param 1 = date in UTC time
		z.utc_to_local = function(date) {

			var date_local = new Date(date + ' UTC');
			var date_utc = new Date(date);

			//date & time, convert to central time zone
			var y = date_local.getFullYear(),
				m = date_local.getMonth() + 1,
				d = date_local.getDate(),
				hours = date_local.getHours(),
				minutes = date_local.getMinutes();

			var time = z.time_format(hours, minutes);

			return m + "-" + d + "-" + y + " at " + time;
		}

		//changes military time to standard
		//param 1 = hours
		//param 2 = minutes
		z.time_format = function(hours, minutes) {

			//init time to be returned
			var timeValue;

			//use hours to check if this needs to be am or pm
			if (hours > 0 && hours <= 12) {
				timeValue = "" + hours;
			} else if (hours > 12) {
				timeValue = "" + (hours - 12);
			} else if (hours == 0) {
				timeValue = "12";
			}

			timeValue += (minutes < 10) ? ":0" + minutes : ":" + minutes; // get minutes
			timeValue += (hours >= 12) ? " P.M." : " A.M."; // get AM/PM

			// return value
			return timeValue;
		}

		//handles toggling material logistics field
		function toggle_matL(type) {

			//if checked, flip to readonly
			if (u.eid(type + "_matL_check").checked) {
				u.eid(type + "_matL").readOnly = true;
				u.eid(type + "_matL").value = "";
			} else {
				u.eid(type + "_matL").readOnly = false;
			}

		}

		//handles adding new sub to 
		function add_sub(type) {

			//check to make sure we have a match
			var index = -1,
				targetPN = u.eid(type + "_sub").value;

			//loop through inventory until we find match in catalog
			for (var i = 0; i < inventory.length; i++) {
				if (inventory[i].partNumber.toLowerCase() == targetPN.toLowerCase() && inventory[i].class == "catalog") {
					index = i;
					break;
				}
			}

			//if not -1, add to sub list
			if (index != -1) {

				//holds boolean to determine if we need to exit (return inside foreach will not work)
				var exit_function = false;

				//loop through subs list, check if it has already been added
				document.querySelectorAll('.' + type + '_subs_list').forEach(function(a) {
					if (a.value.toLowerCase() == inventory[index].partNumber.toLowerCase()) {
						alert("Sub has already been added.");
						exit_function = true;
					}
				})

				if (exit_function)
					return;

				//grab table
				var table = u.eid("subs_table_" + type);

				//insert new row and add classname to it
				var row = table.insertRow(-1);
				row.classList.add("sub_row");

				//add remove button
				var cell = row.insertCell(0);
				cell.className = "table_td"
				cell.innerHTML = "<button style = 'float: right;' onclick = 'remove_sub_or_reel(this)'>X</button>";

				//part number
				var cell = row.insertCell(1);
				cell.innerHTML = "<input class = 'edit_field " + type + "_subs_list' id = '" + type + "_subs_" + index + "' readonly>";

				//set part number
				u.eid(type + "_subs_" + index).value = inventory[index].partNumber;

				//clear out previous entry
				u.eid(type + "_sub").value = "";
			} else {
				alert("No match found for " + targetPN + " please try typing a new part in.");
			}

			//if existing, refresh subs in array
			if (type == "existing")
				refresh_subs();

		}

		//handles refreshing subs list in global array
		function refresh_subs() {

			//get current part
			var partNumber = u.eid("edit_part").value.toLowerCase();

			//init string to hold subs
			var subs_string = get_sub_string('existing');

			//add to inventory object (get index then add)
			var inv_index = inventory.findIndex(object => {
				return object.partNumber.toLowerCase() == partNumber;
			});

			//update object
			inventory[inv_index].subPN = subs_string;

			//get update_inv index (used to save updates)
			var update_index = update_parts.findIndex(object => {
				return object.partNumber.toLowerCase() == partNumber;
			});

			if (update_index == -1) {
				check_add(u.eid("edit_part"));
				update_index = update_parts.length - 1;
			}

			//update object
			update_parts[update_index].subPN = subs_string;

			console.log(inventory[inv_index]);

		}

		//handles adding new reel to list
		/*
		function add_reel(){
			
			//grab table
			var table = u.eid("reels_table");

			//insert new row and add classname to it
			var row = table.insertRow(-1);
			row.classList.add("reels_row");

			//add remove button
			var cell = row.insertCell(0);
			cell.className = "table_td"
			cell.innerHTML = "<button style = 'float: right;' onclick = 'remove_sub_or_reel(this)'>X</button>";

			//reel length
			var cell = row.insertCell(1);
			var input = document.createElement("input");
			input.classList.add("edit_field");
			input.classList.add("reels_length_list");
			input.readOnly = true;
			input.value = u.eid("new_reel_length").value;
			cell.appendChild(input);
			
			//reel location
			var cell = row.insertCell(2);
			var input = document.createElement("input");
			input.classList.add("edit_field");
			input.classList.add("reels_location_list");
			input.readOnly = true;
			input.value = u.eid("new_reel_location").value;
			cell.appendChild(input);

			//clear out previous entry
			u.eid("new_reel_length").value = "";
			u.eid("new_reel_location").value = "";
			
		}
		*/

		//handles removing existing sub
		function remove_sub_or_reel(button) {

			//work your way to the row and remove
			var td = button.parentNode;
			var tr = td.parentNode;
			var input = tr.childNodes[1].childNodes[0];
			var class_check = input.classList[1];

			//remove row
			tr.remove();

			//if existing, refresh subs
			if (class_check == "existing_subs_list")
				refresh_subs();

		}

		//handles change in inventory
		//type = [edit, add]
		function material_creation() {

			// check to see if user entered part is already in our catalog
			var index = inventory.findIndex(object => {
				return object.partNumber.toLowerCase() == u.eid("new_part").value.toLowerCase() && object.category == "catalog";
			});

			if (index != -1) {
				alert("[Error] This part already exists in the catalog.")
				return;
			}

			//init form data
			var fd = new FormData();

			//pass part info to server (make sure left side matches invreport fields)
			//basic info
			fd.append('partNumber', u.eid("new_part").value);
			fd.append('partDescription', u.eid("new_description").value);
			fd.append('partCategory', u.eid("new_category").value);
			fd.append('manufacturer', u.eid("new_manufacturer").value);
			fd.append('uom', u.eid("new_uom").value);
			fd.append('cut_sheet', u.eid("new_cut_sheet").value);
			fd.append('cost', u.eid("new_cost").value);
			fd.append('price', u.eid("new_price").value);
			fd.append('quoteDate', u.eid("new_quote_date").value);
			fd.append('status', u.eid("new_status").value);

			//check value for matL
			if (u.eid("new_matL_check").checked)
				fd.append('matL', -1);
			else
				fd.append('matL', u.eid("new_matL").value);

			//check value for pref and hot
			if (u.eid("new_pref_part").checked)
				fd.append('pref_part', "TRUE");
			else
				fd.append('pref_part', "FALSE");

			if (u.eid("new_hot").checked)
				fd.append('hot_part', "TRUE");
			else
				fd.append('hot_part', "FALSE");

			//part substitutes
			var subs_string = get_sub_string('new');

			//push to form
			fd.append("subPN", subs_string);

			//search criteria
			var criteria = u.class("new-attributes");

			//loop through given criteria and push to array
			for (var i = 0; i < criteria.length; i++) {

				//cut id down to anything past 9
				//ex. new-part-cable_type => cable_type (we need right side to match invreport database columns)
				fd.append(criteria[i].id.substr(9), criteria[i].value);

			}

			//add tell, type, and user info
			fd.append('tell', 'update_inv');
			fd.append('type', 'add');
			fd.append('user_info', JSON.stringify(user_info));

			//last add arrays to create query on server side
			fd.append('standard_info', JSON.stringify(all_keys));
			fd.append('attributes', JSON.stringify(attributes_options));

			//lock add part button
			u.eid("add_part_button").disabled = true;

			//send info to ajax, set up handler for response
			$.ajax({
				url: 'terminal_hub_helper.php',
				type: 'POST',
				processData: false,
				contentType: false,
				data: fd,
				success: function(response) {

					// if we return an error, let the user know
					if (response != "") {
						alert(response);
						return;
					}

					// push new part to catalog (just part so we don't add twice)
					inventory.push({
						partNumber: u.eid("new_part").value,
						class: 'catalog'
					})

					//let user know part has been added
					alert("This part has been successfully added to our catalog (please refresh for updates to take affect).");
					u.eid("add_part_button").disabled = false;

				}
			});

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

			// If this is warehouse setup, update to selected
			if (pageName == "warehouse_setup")
				update_staging_area(u.eid("warehouse_setup_shop"))
		}

		//overrides default up and down arrows
		document.onkeyup = function KeyUp(e) {

			//up arrow
			if (e.keyCode == 38) {
				e.preventDefault();
				try {
					input_check(this.activeElement, 38)
				} catch (error) {
					console.log(error);
				}

			}
			//down arrow
			else if (e.keyCode == 40) {
				e.preventDefault();
				try {
					input_check(this.activeElement, 40)
				} catch (error) {
					console.log(error);
				}
			}

		}

		document.onkeydown = function KeyDown(e) {

			//up arrow
			if (e.keyCode == 38) {
				e.preventDefault();
			}
			//down arrow
			else if (e.keyCode == 40) {
				e.preventDefault();
			}

		}

		//handles checking for input element
		function input_check(targ, e) {

			var class_name = targ.classList[0];

			//loop through header_keys 
			for (var i = 0; i < header_keys.length; i++) {
				if (class_name == header_keys[i])
					break;

			}

			//if i is not equal to lenght, we found a match
			if (i != header_keys.length) {

				var td = targ.parentNode;
				var cellIndex = td.cellIndex;
				var tr = td.parentNode;
				var rowIndex = tr.rowIndex;
				var table = tr.parentNode.parentNode;

				//if key up, subtract row index by 2
				if (e == 38)
					rowIndex -= 2;

				//focus on elemnt 
				table = table.childNodes[5];
				table = table.childNodes[rowIndex];
				table = table.childNodes[cellIndex];
				table = table.childNodes[0];
				table.focus();
				table.select();

			}

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

			//add event listener logic to notify the user before they exit the site if they have potential unsaved data
			window.addEventListener("beforeunload", function(e) {

				if (update_parts.length == 0) {
					return undefined;
				}

				var confirmationMessage = 'It looks like you have been editing something. ' +
					'If you leave before saving, your changes will be lost.';

				(e || window.event).returnValue = confirmationMessage; //Gecko + IE
				return confirmationMessage; //Gecko + Webkit, Safari, Chrome etc.
			});

			//u.eid("cat-dropdown").value = "COAX-CABLE";
			//z.grabParts();			

			//open new part dialog if new_part is not blank
			if (new_part != "")
				start_with_part(new_part);

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