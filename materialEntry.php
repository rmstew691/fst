<?php
//access session vars
session_start();

//included to leverage self made php functions
include('phpFunctions.php');

//include php HTML renderings
include('phpFunctions_html.php');

// Load the database configuration file
require_once 'config.php';

//include constants sheet
include('constants.php');

//used to grab actual link for the current address
$actual_link = "https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

//init $quote & $kit_id and any associated values to avoid errors on page load.
$quote = "";
$grid['customer'] = "";
$grid['submitBox'] = "";
$grid['quote_type'] = "";
$kit_id = "";
$kit_parts = [];
$pq_id = "";
$pq_type = "";
$pq_overview = [];
$hide_for_kit = "";
$hide_for_quote = "";
$show_for_orders = "display:none;";

//grab quote number (parse into actual number and version)
if (isset($_GET["quote"])) {
	$quote = $_GET["quote"];

	//check if quote number exists in fst_grid
	$query = "select customer, submitBox, quote_type from fst_grid WHERE quoteNumber = '" . $quote . "';";
	$result = mysqli_query($con, $query);
	if (mysqli_num_rows($result) == 0)
		header("Location: home.php");

	//check to see if the customer is Verizon (2 different displays)
	$grid = mysqli_fetch_array($result);

	//set 'hide_for_quote' display to none
	$hide_for_quote = "display:none;";
}

if (isset($_GET['kit'])) {
	$kit_id = $_GET["kit"];

	//check if kit id exists in fst_boms_kits
	//$query = "select * from fst_bom_kits WHERE kit_part_id = 'pwk-example1';"; => represents example of query below
	$query = "select * from fst_bom_kits WHERE kit_part_id = '" . $kit_id . "';";
	$result = mysqli_query($con, $query);

	if (mysqli_num_rows($result) == 0)
		header("Location: kit_home.php");	//replace home.php with destination like kit_admin.php or kitCreation.php
	//if we find a match, search for parts within this kit
	else {

		//get row information from kit (from query in line 52)
		$kit_info = mysqli_fetch_array($result);
		$query = "select * from fst_bom_kits_detail WHERE kit_id = '" . $kit_id . "';";
		$result = mysqli_query($con, $query);

		while ($rows = mysqli_fetch_assoc($result)) {
			//store an array of objects
			//$kit_parts[0] => {kit_id : pwk-example1, partNumber : 12/WP, quantity: 100}...
			array_push($kit_parts, $rows);
		}
	}

	//set 'hide_for_kit' display to none
	$hide_for_kit = "display:none;";
}

// Check for entry from allocations or orders
if (isset($_GET['PQID']) || isset($_GET['PQID_allocations'])) {

	// Set $pq_type & $pq_id based on GET variable
	if (isset($_GET['PQID'])) {
		$pq_type = "orders";
		$pq_id = $_GET["PQID"];
	} else {
		$pq_type = "allocations";
		$pq_id = $_GET["PQID_allocations"];
	}

	//check if kit id exists in fst_boms_kits
	//$query = "select * from fst_bom_kits WHERE kit_part_id = 'pwk-example1';"; => represents example of query below
	$query = "select project_id, project_name, quoteNumber, type from fst_pq_overview WHERE id = '" . $pq_id . "';";
	$result = mysqli_query($con, $query);

	if (mysqli_num_rows($result) == 0)
		header("Location: terminal_orders.php");	//replace home.php with destination like kit_admin.php or kitCreation.php
	else
		$pq_overview = mysqli_fetch_array($result);

	//set 'hide_for_kit' display to none
	$hide_for_kit = "display:none;";
	$hide_for_quote = "display:none;";
	$show_for_orders = "";
}

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

sessionCheck($fstUser['accessLevel']);

//if admin, display admin button
$admin = "none";

if ($fstUser['accessLevel'] == "Admin") {
	$admin = "";
}

//if deployment, can only search through fst's, cannot create a new one
$protect_header = "";

if ($fstUser['accessLevel'] == "Deployment") {
	$protect_header = "disabled";
}

//look for GET variable (holds mo_id if accessing from email)


//will hold arrays on load to transfer to javascript
$inventory = [];
$oem = [];
$category = [];

$loadParts = "select * from invreport WHERE active = 'True' order by hot_part desc, pref_part desc, partCategory asc, partNumber asc;";
$categories = "select * from general_material_categories;";
$manufacturers = "select manufacturer from invreport group by manufacturer order by manufacturer";
$result = mysqli_query($con, $loadParts);
while ($rows = mysqli_fetch_assoc($result)) {
	array_push($inventory, $rows);
	array_push($oem, $rows['manufacturer']);
	array_push($category, $rows['partCategory']);
}

//load in WS subs (created by Jeremy J late 2021)
$ws_subs = [];

$query = "select * from invreport_ws_subs ORDER BY partNumber, priority;";
$result = mysqli_query($con, $query);
while ($rows = mysqli_fetch_assoc($result)) {
	array_push($ws_subs, $rows);
}

//init arrays to hold information from parts created in the last 30 days (m is for manual)
$manual_parts = [];

//grab manually created parts from the last 30 days
$query = "SELECT * FROM fst_newparts WHERE date > now() - INTERVAL 30 day OR partNumber IN('Erico-Caddy - Passive', 'Erico-Caddy - Active') ORDER BY date desc;";
$result = mysqli_query($con, $query);
while ($rows = mysqli_fetch_assoc($result)) {
	array_push($manual_parts, $rows);
}

//set properties to be changed
$mmdOpt = null;
$summOpt = "display: none;";
$float = "float: left;";
$mmd_tell = "yes";

if (strtolower($grid['customer']) !== "verizon" || $grid['quote_type'] == "SM") {
	$mmdOpt = "display: none;";
	$summOpt = null;
	$float = null;
	$mmd_tell = "no";
}

//grab list of quotes for interactive list
$quotes = [];

$query = "select quoteNumber from fst_boms group by quoteNumber;";
$result = mysqli_query($con, $query);
while ($rows = mysqli_fetch_assoc($result)) {
	array_push($quotes, $rows['quoteNumber']);
	# array_push is like append() in Python. Here it pushes quoteNumber to the list of quotes
}

//grab template list and list of template names
$templates = [];

$query = "select * from fst_bom_templates ORDER BY template, quantity desc;";
$result = mysqli_query($con, $query);
while ($rows = mysqli_fetch_assoc($result)) {
	array_push($templates, $rows);
}

//just names
$template_names = [];

$query = "select template from fst_bom_templates GROUP BY template ORDER BY template;";
$result = mysqli_query($con, $query);
while ($rows = mysqli_fetch_assoc($result)) {
	array_push($template_names, $rows['template']);
}

//grab inventory attribute assignments
$query = "select * from inv_attributes_assignments;";
$attributes_assignments = get_pointer_options($query, 'category', 'options');

//grab attribute drop_down options
$query = "select * from inv_attributes_options order by attribute_key, inv_attributes_options.order;";
$attributes_options = get_pointer_options($query, 'attribute_key', 'options');

//grab attribute keys
$attributes_key = [];

$query = "select * from inv_attributes_key;";
$result = mysqli_query($con, $query);

while ($rows = mysqli_fetch_assoc($result)) {

	//push to array
	array_push($attributes_key, $rows);
}

//load in kits
$kit_detail = [];

$query = "select * from fst_bom_kits_detail;";
$result = mysqli_query($con, $query);

while ($rows = mysqli_fetch_assoc($result)) {

	//push to array
	array_push($kit_detail, $rows);
}

?>

<!doctype html>
<html>

<head>
	<meta charset="utf-8">
	<meta name="google-signin-client_id" content="573761357198-hin7ae7q19qgvoab7t0781b41530546g.apps.googleusercontent.com">
	<link rel="shortcut icon" type="image/x-icon" href="images/P-Logo.png" />
	<link rel="stylesheet" href="stylesheets/element-styles.css?<?= $version; ?>1" />
	<link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css'>
	<link href="stylesheets/jquery-ui-themes-1.13.0/themes/ui-lightness/jquery-ui.css" rel="stylesheet">
	<title>Material Entry (v<?= $version ?>) - Pierson Wireless</title>

	<style>
		/**style type input fields to certain height */
		.type {
			width: 6em;
			height: 1.7em !important;
		}

		.hide_for_kit {
			<?= $hide_for_kit; ?>;
		}

		.hide_for_quote {
			<?= $hide_for_quote; ?>;
		}

		.show_for_orders {
			<?= $show_for_orders; ?>;
		}

		.no_border td {
			border: none;
		}

		input:focus,
		select:focus,
		textarea:focus {
			box-shadow: 0 0 20px #114B95;
		}

		/* Style read-only inputs to gray */
		input:read-only:not([type=button]):not([type=submit]):not([type=file]) {
			background-color: #C8C8C8;
		}

		.ui-autocomplete-category {
			font-weight: bold;
			padding: .2em .4em;
			margin: .8em 0 .2em;
			line-height: 1.5;
		}

		.quantity {
			width: 75px;
		}

		.wait {
			cursor: wait;
		}

		.ui-autocomplete {
			max-height: 300px;
			overflow-y: auto;
			/* prevent horizontal scrollbar */
			overflow-x: hidden;
			padding-right: 40px;
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

		#shoppingCart-table td {
			text-align: center;
		}


		/* Style the tab content (and add height:100% for full page content) */
		.tabcontent {
			padding: 0px 20px;
			height: 100%;
		}

		/* Style custom inputs*/
		.custom-select {
			height: 31px;
		}

		.custom-select-header {
			background-color: #BBDFFA;
			border-color: #000B51;
			border-width: medium;
			cursor: pointer;
			width: 14em;

		}

		.ui-widget {
			padding-bottom: 10px;
		}

		.price,
		.cost,
		.phase {
			width: 7em;
		}

		.remove:hover {
			cursor: pointer;
		}

		.remove {
			color: red !important;
		}

		.stock {
			display: none;
		}

		.mmdOpt {
			<?= $mmdOpt; ?>
		}

		#matSummary {
			<?= $summOpt; ?>;
			border-collapse: collapse;
		}

		#matSummary td {
			border: 1px solid #555555;
		}

		#mmdSummary {
			border-collapse: collapse;
		}

		#mmdSummary td {
			border: 1px solid #555555;
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
			max-height: 10px;
		}

		.ui-menu-item {
			padding-top: 7px;
		}

		.mc_head {
			text-align: right;
		}

		.mc_info {
			width: 20em;
		}
	</style>
</head>

<body>

	<?php

	//define array of names & Id's to generate headers
	$header_names = ['Material Entry'];
	$header_ids = ['entry'];

	//pass to php function to create navigation bars
	echo create_navigation_bar($header_names, $header_ids, "", $fstUser);

	?>

	<div style='padding-left: 20px;padding-top:5em;'>
		<a href='application.php?quote=<?= $quote; ?>' class='hide_for_kit'><button>Return to Quote <?= $quote; ?></button></a>
		<a href='kit_home.php?' class='hide_for_quote'><button>Return to Kit Home</button></a>
	</div>

	<div class='tabcontent' id='entry'>

		<h1 class='hide_for_kit'> Material Entry Form </h1>
		<h1 class='hide_for_quote'> Kit Edit Form </h1>
		<span id='pos'></span>

		<div style='float: left'>

		</div>

		<div class='ui-widget' style='padding-bottom: 20em; float:left'>
			<table class='searchTable' id='shoppingCart-table'>

				<colgroup>
					<col>
					<col>
					<col>
					<col>
					<col>
					<col>
					<col style='display:none'>
					<col>
					<col>
					<col>
					<col>
				</colgroup>

				<tr class='no_border'>
					<td colspan='3' style='text-align: left'>
						<!--some area's of header section will be hidden when accessing kits-->
						<b>Quick Start </b><br><br>
						<div class='hide_for_kit'>
							<input type='text' id='reference_quote' placeholder="Quote Number" style='width: 13em'> <button onclick='z.use_existing_bom()'>Search</button><br>
							<select class='custom-select' id='template_name' style='width: 13em'>
								<option></option>

								<?php

								for ($i = 0; $i < sizeof($template_names); $i++) {

								?>
									<option><?= $template_names[$i]; ?></option>

								<?php
								}
								?>

							</select>
							<button onclick=z.use_template()>Add to BOM</button><br>
							<button onclick='document.getElementById("ibWave_file").click()' style='width: 7em;'>iBwave BOM</button> <input type="file" id="ibWave_file" onChange="z.addBOM('ibWave')" style='display:none'><br>
						</div>

						<button onclick='document.getElementById("csv_file").click()' style='width: 7em;'>CSV File</button> <span onclick='z.download_csv_template();' class='download'>Download CSV Template</span><input type="file" id="csv_file" onChange="z.addBOM('csv')" style='display:none'><a id='csv_template'></a><br><br>

						<b>Limit Search </b><br><br>
						<button id='openCatalog'>Parts Catalog</button><br>
						Just in stock <input type='checkbox' onchange='z.update_object(this.checked)'><br><br>
					</td>
					<td style='text-align: left; vertical-align: top'>
						<div class='hide_for_kit'>
							<b>Defaults</b><br><br>
							Allow all subs <input type='checkbox' id='default_all_subs' checked><br>
							Subs&nbsp;&nbsp;<select class='custom-select' id='default_subs' onchange='update_recent()'>
								<option>No</option>
								<option selected>Yes</option>
							</select><br>
							<span class="mmdOpt">MMD <select class='custom-select' id='default_MMD' onchange='update_recent()'>
									<option>Yes</option>
									<option>No</option>
									<option>Misc</option>
								</select></span><br>
						</div>
					</td>
					<td style='text-align: left'>

					</td>
					<td colspan='5'>
						<table id='matSummary' style='float:right'>
							<tr>
								<th colspan='2'>Summary</th>
							</tr>
							<tr>
								<td>Total Cost</td>
								<td id='totalCost'>$0.00</td>
							</tr>
							<tr>
								<td>Total Margin</td>
								<td id='totalMargin'>$0.00</td>
							</tr>
							<tr>
								<td><?php if (isset($_GET['kit'])) {
										echo "Suggested Price";
									} else {
										echo "Total Price";
									} ?></td>
								<td id='totalPrice'>$0.00</td>
							</tr>
							<tr class='hide_for_quote'>
								<td>Kit Price</td>
								<td><input id='kit_price' style='width:7em;' onchange='manual_kit_price=true'></td>
							</tr>

							<tr class='hide_for_quote'>
								<td>Phase</td>
								<td>
									<select onchange="kit_phase()" id='kit_phase' class='custom-select' style='width:7em;'>
										<option>Active</option>
										<option <?php if ($kit_info['phase'] == 'Passive') {
													echo "selected";
												} ?>>Passive</option>
									</select>
								</td>
							</tr>
						</table>

						<table class='searchTable mmdOpt hide_for_kit' id=mmdSummary style='float:right'>
							<tr>
								<th colspan='3'>MMD Summary</th>
							</tr>
							<tr>
								<th></th>
								<th>Cost</th>
								<th>Price</th>
							</tr>
							<tr>
								<td>MMD - Shipping and Handling (Overhead)</td>
								<td id='mmdSH_cost'>$0.00</td>
								<td id='mmdSH_price'>$0.00</td>
							</tr>
							<tr>
								<td>PW Materials - Non-MMD</td>
								<td id='nonMMD_cost'>$0.00</td>
								<td id='nonMMD_price'>$0.00</td>
							</tr>
							<tr>
								<td>Materials (MMD)</td>
								<td id='mmd_cost'>$0.00</td>
								<td id='mmd_price'>$0.00</td>
							</tr>
							<tr>
								<td>Miscellaneous Materials (MMD)</td>
								<td id='mmdMisc_cost'>$0.00</td>
								<td id='mmdMisc_price'>$0.00</td>
							</tr>
							<tr>
								<td>Total Materials</td>
								<td id='mmdTotal_cost'>$0.00</td>
								<td id='mmdTotal_price'>$0.00</td>
							</tr>

						</table>

					</td>

					<td class='stock'></td>

				</tr>
				<tr style='height: 1em;'></tr>
				<tr>
					<th></th>
					<th style='width: 15em'> Part Number </th>
					<th style='width: 6em'> Quantity </th>
					<th style='width: 9em'> Part Category </th>
					<th style='width: 28em'> Description </th>
					<th style='width: 16em'> OEM </th>
					<th class='hide_for_kit'> Subs </th>
					<th style='width: 5em' class='mmdOpt'> MMD </th>
					<th style='width: 5em' class='hide_for_quote'> Required/Optional </th>
					<th style='width: 7em'> Cost </th>
					<th style='width: 7em'> Price </th>
					<th style='width: 7em' class='stock'> Stock </th>


				</tr>
				<tr class='shoppingCart-row'>
					<td class='remove' onclick='z.removePart(this)'>&#10006</td>
					<td style='width: 15em'> <input class='parts' onchange='z.partInfo(this, null)' placeholder='Part Number' style='width: 15em' disabled> </td>
					<td> <input class='quantity' style='width: 6em' type='number' value='0' min='0' onchange='z.totals()'> </td>
					<td style='width: 9em' class='part_category'></td>
					<td style='width: 28em'></td>
					<td style='width: 16em'></td>
					<td class='hide_for_kit'>
						<table>
							<tr>
								<td style='border:none'>
									<select class='custom-select subs'>
										<option>No</option>
										<option selected>Yes</option>
									</select>
								</td>
								<td style='border:none; display:none'><button onclick='z.checkSubs(this)'>?</button></td>
							</tr>
						</table>
					</td>
					<td style='width: 5em' class='mmdOpt'>
						<select style='width: 5em' class='custom-select mmd' onChange="z.totals()">
							<option>Yes</option>
							<option>No</option>
							<option>Misc</option>
						</select>
					</td>

					<td style='width: 5em' class='hide_for_quote'>
						<select class='custom-select type' onchange='z.totals()'>
							<option>Required</option>
							<option>Optional</option>
						</select>
					</td>


					<td style='width: 7em'>
						<input class='cost' type='number' min='0' step='any' onchange='z.totals()' <?php if (isset($_GET['kit'])) echo "readOnly"; ?>>
					</td>
					<td style='width: 7em'> <input class='price' type='number' min='0' onchange='z.totals()'> </td>
					<td class='stock'></td>
					<td style='display:none' class='add_materials'> <button onclick='z.addToDialog(this)'>Add Material</button> </td>
					<td style='display:none'><input class='manual_tell' value='A'></td>
				</tr>
			</table>

			<button onclick='add_to_quote()' id='add_to_quote' class='hide_for_kit'>Add Parts to Quote</button>
			<button onclick='save_current_state_of_kit()' class='hide_for_quote'>Save Current State of Kit</button>
			<button onclick='add_to_order_reason()' class='show_for_orders'>Add Parts to Order</button>

		</div>
	</div>
	<div class='tabcontent' id='templates' style='display: none'>


	</div>

	<div class='ui-widget' id='parts-catalog' style='display:none' title="Parts Catalog">
		<table id='parts-search-table'>
			<tr>
				<td colspan='2'><a href="https://drive.google.com/drive/folders/1v0v5LgDfU6XCWNS9oYmSuiLXWOFywIji?usp=sharing" target="_blank" style="color:blue">PW Catalogs & Templates</a></td>
			</tr>
			<tr>
				<td> Select a Part Category: </td>
				<td>
					<select class='custom-select-header' id='cat-dropdown' onchange='z.update_dropdown(this)'>
						<option class='c-option'></option>
						<?php
						//array to hold all categories (used in js)
						$all_categories = [];
						$all_categories_full = [];

						$result = mysqli_query($con, $categories);
						while ($rows = mysqli_fetch_assoc($result)) {
						?>
							<option class='c-option' value="<?= $rows['category']; ?>" /><?= $rows['description']; ?></option>
						<?php
							array_push($all_categories, $rows['category']);
							array_push($all_categories_full, $rows['description']);
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
						//array to hold all categories (used in js)
						$all_manufacturers = [];

						$query = "SELECT manufacturer from invreport GROUP BY manufacturer ORDER BY manufacturer";
						$result = mysqli_query($con, $query);
						while ($rows = mysqli_fetch_assoc($result)) {
						?>
							<option class='m-option' value="<?= $rows['manufacturer']; ?>" /><?= $rows['manufacturer']; ?></option>
						<?php
							array_push($all_manufacturers, $rows['manufacturer']);
						}
						?>
					</select>
				</td>
			</tr>
			<tr>
				<td> Search by Part #: </td>
				<td>
					<input id='part-search' style='width: 14em;'>
				</td>
			</tr>
			<tr>
				<td><button onclick='filter_catalog_handler()'>Search for parts</button> </td>
			</tr>
		</table>

		<table id="searchTable" class="searchTable" style="display: none;">
			<thead>
				<tr>
					<td style="border: none"></td>
					<td style="border: none"></td>
					<td style="border: none"></td>
					<td style="border: none"><input type="text" id="search4" style="width: 100%" placeholder="Search Part Description"></td>
					<td style="border: none"><input type="text" id="search5" style="width: 100%" placeholder="Search Part Number"></td>
				</tr>
				<tr class='sticky-header'>
					<th id="home-header1"> Add to Cart </th>
					<th id="home-header2"> Quantity </th>
					<th id="home-header3"> Part Category </th>
					<th id="home-header4"> Part Description </th>
					<th id="home-header5"> Part Number </th>
					<th id="home-header6"> Manufacturer </th>
					<th id="home-header7"> UOM </th>
					<th id="home-header8"> Cost </th>
					<th id="home-header9"> Price </th>
					<th id="home-header10"> OMA </th>
					<th id="home-header11"> CHA </th>

				</tr>
			</thead>
			<tbody></tbody>
		</table>

	</div>


	<!------------------------ DIALOG BOXES!---------------------->

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
				<td id='o_pn'>12-NM</td>
				<td id='o_cost'>3.65</td>
				<td id='o_stock'>45</td>
			</tr>

			<tr>
				<td colspan="4" style='border: 0; height: 5px'></td>
			</tr>

			<tr class='subParts'>
				<td rowspan="2"><b>Substitutes</b></td>
				<td>L4TNM-PSA</td>
				<td>14.51</td>
				<td>283</td>
				<td><button>Substitute</button></td>
			</tr>

			<tr class='subParts'>
				<td>UXP-NM-12</td>
				<td>27.89</td>
				<td>695</td>
				<td><button>Substitute</button></td>
			</tr>

		</table>

		<br>
		<button onclick='save_subs()'>Save Substitions</button>
	</div>

	<div class='ui-widget' id='optional_parts_dialog' style='display:none' title='Optional Kit Parts'>

		<h3 style='width:28em;'>A few parts that usually are quoted with this kit are listed below, please ignore if none of these parts apply</h3>

		<table class="standardTables" id='optional_parts_table'>
			<tr>
				<th> Add to BOM </th>
				<th> Part # </th>
				<th> Quantity </th>
				<th> Description </th>
			</tr>
			<!--all new rows will be added by open_kit_optional_part_dialog() -->
		</table>
	</div>

	<div id="add_to_order_dialog" style='display:none' title='Add to Order/Allocations Reasoning'>
		<h3>Please provide a reason for adding this part to your order.</h3>
		<select id='add_to_order_reason' class='custom-select' onchange='add_to_order_check()'>
			<option></option>
			<option>Subbing multiple parts for 1 part</option>
			<option>Requesting Stock for Order</option>
			<option>Other</option>
		</select>
		<br>
		<textarea id='add_to_order_other' style='height: 145px; width: 529px; display:none; margin-top: 1em;'></textarea>
		<br>
		<button onclick='add_to_order()'>Add Parts</button>
	</div>

	<div class='ui-widget' id='new-part' style='display:none'>
		<table class=" fstTable" align="center" border="0px" style="line-height:20px;">
			<tr>
				<th colspan='2'>
					<h3>Material Creation Information</h3> <button style='display:none' id='prev_button' onclick='z.use_previous()'>Use Previously Entered Info</button>
				</th>
			</tr>
			<tr>
				<th class='mc_head'> Part # </th>
				<td>
					<input id='newPart' class='mc_info' onchange='align_parts()' onkeyup='align_parts()' />
				</td>
			</tr>
			<tr>
				<th class='mc_head'> Part Description </th>
				<td>
					<input id='newDescription' class='mc_info' />
				</td>
			</tr>
			<tr>
				<th class='mc_head'> Manufacturer </th>
				<td>
					<input id='newManufacturer' class='mc_info' />
				</td>
			</tr>
			<tr>
				<th class='mc_head'> Part Category </th>
				<td>
					<select id='newCategory' class='custom-select mc_info' style='width: 20em'>
						<option></option>
						<?php
						$result = mysqli_query($con, $categories);
						while ($rows = mysqli_fetch_assoc($result)) {
						?>
							<option /><?= $rows['category']; ?></option>
						<?php
						}
						?>
					</select>
				</td>
			</tr>
			<tr>
				<th class='mc_head'> UOM </th>
				<td>
					<select id='newUOM' class='custom-select mc_info' style='width: 20em'>
						<option></option>
						<option>LF</option>
						<option>EA</option>
					</select>
				</td>
			</tr>
			<tr>
				<th class='mc_head'> Vendor of Origin (if known) </th>
				<td>
					<input id='newVendor' class='mc_info' />
				</td>
			</tr>
			<tr>
				<th class='mc_head'> Cost to Pierson Wireless </th>
				<td>
					<input type='number' id='newCost' class='mc_info' />
				</td>
			</tr>
			<tr>
				<th class='mc_head'> Add Cut Sheet Link </th>
				<td><input type='text' id='cut_sheet_link' style='width: 20em'></td>
			</tr>
			<tr>
				<th class='mc_head'> Add Cut Sheet </th>
				<td><input type='file' id='cut_sheet'></td>
			</tr>
			<tr style='<?php if ($grid['submitBox'] != "on") echo "visibility: collapse"; ?>'>
				<td colspan='2'> <i>All MC requests will be sent out when adding parts to quote since this quote is locked. </i></td>
			</tr>
			<tr>
				<td style='padding: 1em;'><button onclick=z.matCreation()>Submit Info</button></td>
			</tr>
		</table>
	</div>

	<button id='new-part-button' style='display:none'>Add Material</button>
	<button id='partSub-button' style='display:none'>Add Subs</button>

	<!-- externally defined js files -->
	<!-- used for ajax -->
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
	<script src="https://apis.google.com/js/platform.js?onload=init" async defer></script>

	<!-- used for jquery -->
	<script src="https://code.jquery.com/jquery-1.12.4.js"></script>
	<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>

	<!-- internally defined js files -->
	<script src="javascript/js_helper.js?<?= $version ?>-2"></script>
	<script src="javascript/accounting.js"></script>
	<script src="javascript/utils.js"></script>

	<script>
		//Namespace
		var z = {}

		//holds current part index
		var current_index = null;

		//holds index of newly created part
		var edit_index = null;

		//holds quoteNumber
		var quote = '<?= $quote ?>';
		var submitting = false;
		var quote_type = '<?= $grid['quote_type']; ?>';

		var download = false;
		var mmd_tell = '<?= $mmd_tell; ?>';

		//holds info related to pq_overview (if applicable)
		var pq_id = '<?= $pq_id ?>';
		var pq_type = '<?= $pq_type ?>';
		var pq_overview = <?= json_encode($pq_overview); ?>;

		//pass user info to js
		var user_info = <?= json_encode($fstUser); ?>;

		//read in inventory array & other arrays needed to run js
		var inventory = <?php echo json_encode($inventory); ?>,
			all_categories = <?php echo json_encode($all_categories); ?>,
			all_categories_full = <?php echo json_encode($all_categories_full); ?>,
			all_manufacturers = <?php echo json_encode($all_manufacturers); ?>,
			ws_subs = <?= json_encode($ws_subs); ?>;

		//transfer attribute objects
		var attributes_assignments = <?= json_encode($attributes_assignments); ?>,
			attributes_options = <?= json_encode($attributes_options); ?>,
			attributes_key = <?= json_encode($attributes_key); ?>;

		//transfer manually created parts
		var manual_parts = <?= json_encode($manual_parts); ?>;

		//pass customer
		var customer = "<?= $grid['customer']; ?>",
			locked = '<?= $grid['submitBox']; ?>';

		//transfer template info
		var templates = <?= json_encode($templates); ?>;

		//transfer attribute objects
		var attributes_assignments = <?= json_encode($attributes_assignments); ?>,
			attributes_options = <?= json_encode($attributes_options); ?>,
			attributes_key = <?= json_encode($attributes_key); ?>;

		//transfer KIT parts over to js (if applicable)
		var kit_id = '<?= $kit_id; ?>',
			kit_parts = <?= json_encode($kit_parts); ?>,
			kit_detail = <?= json_encode($kit_detail); ?>;

		//initialize data array
		var data = [];
		for (var i = 0; i < inventory.length; i++) {

			//only push if active
			if (inventory[i].status != "Inactive") {

				var tempArray;
				tempArray = {
					label: inventory[i].partNumber,
					pref_part: inventory[i].pref_part,
					hot_part: inventory[i].hot_part,
					partDescription: inventory[i].partDescription,
					total: inventory[i].total,
					price: inventory[i].price

				};

				data.push(tempArray);
			}
		}

		//read in quoteNumbers
		var quotes = <?= json_encode($quotes); ?>;

		//set options to parts array (relevant for parts request new parts)
		var options = {
			source: quotes,
			minLength: 2
		};

		//choose selector (input with part as class)
		var selector = '#reference_quote';

		//on keydown, show autocomplete after 2 characters
		$(document).on('keydown.autocomplete', selector, function() {
			$(this).autocomplete(options);
		});

		/**@author Alex Borchers
		 * Handles getting filters and passing to larger filter catalog function
		 * 
		 */
		function filter_catalog_handler() {

			// Initialize the ranges that will be searched in the following function
			var category = u.eid('cat-dropdown').value,
				partNumber,
				manufacturer = u.eid('manufacturer-dropdown').value,
				part = u.eid("part-search").value,
				table_id = "searchTable",
				class_attributes = u.class("search_attributes");

			// pass relevant info to filter_catalog function (found in javascript/js_helper.js)
			filter_catalog(inventory, table_id, category, manufacturer, part, class_attributes, 'materialEntry');

			// update search list
			target_rows = $('#' + table_id + ' tbody tr');
		}

		//handles showing/hiding kit show_kit_contents
		//uses 'targ' to make decisions (the actual element clicked)
		function show_kit_contents(targ) {

			//work way to kit id through table structure
			var td = targ.parentNode;
			var tr = td.parentNode;
			var kit_td = tr.childNodes[4];
			var kit_id = kit_td.childNodes[0].textContent;

			//grab show/hide symbol
			var show_hide = targ.innerHTML;

			//set all buttons to expand (get's rid of any previous)
			document.querySelectorAll('.show_hide_button').forEach(function(a) {
				a.innerHTML = "+";
			})

			//remove any prior detail
			document.querySelectorAll('.kit_detail').forEach(function(a) {
				a.remove();
			})

			//based on innerHTML, show/hide content
			if (show_hide == "+") {
				targ.innerHTML = "-";
				//create_new_shipment_button();
				get_kit_detail(kit_id, tr.rowIndex);
			}
		}

		//used to get list of parts based on kit_id
		//param 1 = kit id
		//param 2 = row index (where the parts need to be inserted)
		function get_kit_detail(kit_id, row_index) {

			//get search table (used throughout function)
			var search_t = u.eid("searchTable").getElementsByTagName('tbody')[0];
			0

			//get list of parts assigned to kit_id
			var kit_parts = kit_detail.filter(function(kit_part) {
				return kit_part.kit_id == kit_id;
			});

			//debugger;

			//loop and create a table with these parts
			var table = document.createElement("table");
			table.classList.add("standardTables");
			table.classList.add("kit_detail_table")
			//table.id = "shipments_summary_table";

			//create headers
			var kit_headers = ['Part Number', 'Quantity', 'Description', 'Category', 'OMA-1', 'CHA-1'];
			var row = table.insertRow(-1);
			row.classList.add("kit_detail_header");

			for (var i = 0; i < kit_headers.length; i++) {
				var cell = row.insertCell(i);
				cell.innerHTML = kit_headers[i];
			}

			//define attributes shown for each part in kit
			//should match columns in invreport table 
			var kit_attributes = ['partDescription', 'partCategory', 'OMA-1', 'CHA-1'];

			//break into required kit components and optional
			var required_components = kit_parts.filter(function(p) {
				return p.type == "Required";
			});

			var optional_components = kit_parts.filter(function(p) {
				return p.type == "Optional";
			});

			//insert row to denote required
			var row = table.insertRow(-1);
			var cell = row.insertCell(0);
			cell.innerHTML = "Required";
			cell.style.fontStyle = "italic";
			cell.style.border = "0";

			//loop through parts listed as required
			for (var i = 0; i < required_components.length; i++) {
				insert_kit_detail_row(required_components[i], table, kit_attributes);
			}

			//insert row to denote optional
			var row = table.insertRow(-1);
			var cell = row.insertCell(0);
			cell.innerHTML = "Optional";
			cell.style.fontStyle = "italic";
			cell.style.border = "0";
			cell.style.paddingTop = "2em";

			//loop through parts and call function to create row for given part
			for (var i = 0; i < optional_components.length; i++) {
				insert_kit_detail_row(optional_components[i], table, kit_attributes);
			}

			//add row to orders table and push table to new row
			var row = search_t.insertRow(row_index - 1);
			row.classList.add("kit_detail");
			row.classList.add("invBody");
			var cell = row.insertCell(0);
			cell.colSpan = search_t.rows[0].cells.length;
			//cell.append(button);
			cell.append(table);

		}

		//function that handles adding kit detail row (quantity, part, description, stock, etc)
		// param 1 = target part
		// param 2 = table to append to
		// param 3 = key info (see get_kit_detail)
		function insert_kit_detail_row(part, table, kit_attributes) {

			//part number
			var row = table.insertRow(-1);
			var cell = row.insertCell(0);
			cell.innerHTML = part.partNumber;

			//quantity
			var cell = row.insertCell(1);
			cell.innerHTML = part.quantity;

			//find index in invreport
			var index = inventory.findIndex(object => {
				return object.partNumber.toLowerCase() == part.partNumber.toLowerCase();
			});

			//loop through definded attributes above and add to table
			for (var j = 0; j < kit_attributes.length; j++) {

				//next cell
				var cell = row.insertCell(j + 2);
				cell.innerHTML = inventory[index][kit_attributes[j]];

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

		//handles updating other dropdown based on category
		z.update_dropdown = function(drop_down) {

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
				check_array = inventory.map(a => a.manufacturer);
				grab_array = inventory.map(a => a.partCategory);
				blank_array = all_categories;

			} else if (opt == 'cat-dropdown') {
				className = "m-option";
				sel = u.eid("manufacturer-dropdown");
				previous = u.eid("manufacturer-dropdown").value;
				check_array = inventory.map(a => a.partCategory);
				grab_array = inventory.map(a => a.manufacturer);
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

		// z.checkAvailableMatterials = function(dropdown) {  // the dropdown evinced by the click

		// 	// z.update_dropdown(dropdown);  // this may not even be necessary

		// 	$materialsQuery = "SELECT ID FROM FST_BOMS"  // this seems wrong, but I'll fix it later
		// 	$resultMaterialsQuery = mysqli_query($con, $materialsQuery);

		// }

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
			var row_index = 1;

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
				select.addEventListener('change', filter_catalog_handler);
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

		//transfer part from inv report shopping cart
		/**@author Alex Borchers
		 * Handles moving part from catalog to cart
		 * @param targ {HTML Entity} the button clicked on <button>
		 * @returns void
		 */
		function add_from_catalog(targ) {

			// work back to part # & quantity
			var td = targ.parentNode;
			var tr = td.parentNode;
			var part_td = tr.childNodes[4];
			var partNumber = part_td.innerHTML;
			var part_quantity = tr.childNodes[1].childNodes[0].value;

			// If this is a <a> link for cutlink, move 1 more down
			if (part_td.childNodes[0].localName == "a")
				partNumber = part_td.childNodes[0].innerHTML;

			// remove unnecessary info from part #
			partNumber = partNumber.replace("[Discontinued] ", "");
			partNumber = partNumber.replace("HOT LIST: ", "");
			partNumber = partNumber.replace("[EOL] ", "");

			//grab table
			var table = u.eid("shoppingCart-table");

			//grab last index of table
			var lastIndex = table.rows.length - 1;

			//part number
			var pn = table.rows[lastIndex].cells[1].children;
			pn[0].value = partNumber;
			partNumber

			//call partInfo with partNumber filled in
			z.partInfo(pn[0], null);

			//quantity
			var quantity = table.rows[lastIndex].cells[2].children;
			quantity[0].value = part_quantity;

		}

		//checks for part alternatives (needs some work)
		z.findAlt = function(targ, type) {

			//search for part in manually entered arrays
			if (type == "manual") {

				for (var i = 0; i < manual_parts.length; i++) {
					if (targ == manual_parts[i].partNumber) {
						return i;
					}
				}
				return -1;

			}

			//search for alternative part #'s'
			if (type == "alt") {
				var tell = false; //hold tell if this part has an alt
				var in_stock = 0; //so we grab the one with the most stock
				var new_arr = []; //will be used to split found filed into array to make comparisons

				for (var i = 0; i < inventory.length; i++) {

					if (inventory[i].altPN != "" && inventory[i].altPN != null)
						tell = inventory[i].altPN.includes(targ);
					else
						tell = false;

					if (tell) {
						new_arr = inventory[i].altPN.split(",");
						for (var j = 0; j < new_arr.length; j++) {
							if (new_arr[j] == targ)
								return i;
						}

					}

				}

				return -1;
			}

			//search for part in WS Subs list
			if (type == "WS Sub") {

				for (var i = 0; i < ws_subs.length; i++) {
					if (targ.toLowerCase() == ws_subs[i].partNumber.toLowerCase()) {
						return i;
					}
				}
				return -1;

			}

		}

		//handled inputing previously entered info from arrays
		z.use_previous = function(row, index, type) {

			//grab table
			var table = u.eid("shoppingCart-table");

			//category
			table.rows[row].cells[3].innerHTML = manual_parts[index].category;

			//description
			table.rows[row].cells[4].innerHTML = manual_parts[index].description;

			//OEM
			table.rows[row].cells[5].innerHTML = manual_parts[index].manufacturer;

			//Cost
			var cost = table.rows[row].cells[9].children;
			cost[0].value = manual_parts[index].cost;

			//hide button if showing
			table.rows[row].cells[12].style.display = "none";

			//manual entry
			var input = table.rows[row].cells[13].children;
			input[0].value = "M";

			//add another row
			z.addOne();

		}

		//init active categories (used to tell if we need to quote a part)
		const active_cat = ["ACT-DASHE", "ACT-DASREM", "REPTR-BDA", "ASiR", "CBRS_CBSD", "ALU-BTS", "ERCSNDOT", "ERCSN-ENDB", "JMA-XRAN", "MODEMS", "NETW-EQUIP", "NOKIA-MM", "PS-REAPTER", "SFP-CARD", "SMCL-PICOC", "SPDRCLOUD", "WIFIAP&HDW", "PLTE-EPC", "PLTE-EUD", "PLTE-RAN", "PLTE-SAS", "PLTE-SIMS", "PS-REAPTER", "SAM-BTS"];
		const need_quote = ["COR1-CABLE"];

		//MAIN FUNCTION, this handles all new parts that are entered
		z.partInfo = function(x, type) {

			//init variables used
			var part = x.value.trim(),
				tempLen2, tempLen1, tempString, partInfo = [],
				mmd, cost, price, button;

			//use to find row index (found on stack overflow https://stackoverflow.com/questions/6470877/javascript-getting-td-index)
			var cellAndRow = $(x).parents('td,tr');
			var cellIndex = cellAndRow[0].cellIndex
			var rowIndex = cellAndRow[1].rowIndex; //holds current row index

			//grab table
			var table = u.eid("shoppingCart-table");

			//reset input color(changes if inactive)
			table.rows[rowIndex].cells[1].children[0].classList.remove("required_error");

			//# of rows in the table
			var count = table.rows.length;

			//if part is blank, clear and do nothing
			if (part == "") {
				//category
				table.rows[rowIndex].cells[3].innerHTML = null;
				//description
				table.rows[rowIndex].cells[4].innerHTML = null;
				//OEM
				table.rows[rowIndex].cells[5].innerHTML = null;
				//Cost
				cost = table.rows[rowIndex].cells[9].children;
				cost[0].value = null;
				//Price
				price = table.rows[rowIndex].cells[10].children;
				price[0].value = null;
				//Stock
				table.rows[rowIndex].cells[11].innerHTML = null;
				return;
			}

			//returns index in array
			var index = inventory.findIndex(object => {
				return object.partNumber.toLowerCase().trim() == part.toLowerCase().trim();
			});

			//first check for alternative, if you find a match, ask the user if they would like to use it
			if (index == -1) {
				//check for previously entered parts
				var manual_index = z.findAlt(part, "manual");
				if (manual_index != -1) {
					var message = "Someone else has entered information for this part, would you like to use it? \n\n";
					message += "Part Number: " + manual_parts[manual_index].partNumber + "\n";
					message += "Category: " + manual_parts[manual_index].category + "\n";
					message += "Description: " + manual_parts[manual_index].description + "\n";
					message += "Manufacturer: " + manual_parts[manual_index].manufacturer + "\n";
					message += "Cost: " + accounting.formatMoney(manual_parts[manual_index].cost) + "\n\n";
					message += "Use this part instead?\n";
					message += "[OK] Use this information.\n";
					message += "[Cancel] Enter Part Information.";

					//send message to user
					if (confirm(message)) {
						//if yes, call function to handle inputting manually created info, and end function call
						z.use_previous(rowIndex, manual_index, type);
						return;
					}

				}

				//check for alternative parts
				var alt_index = z.findAlt(part, "alt");
				if (alt_index != -1) {
					var message = "This part is currently not in Viewpoint, but we found an alternative: \n\n";
					message += "Part Number: " + inventory[alt_index].partNumber + "\n";
					message += "Part Description: " + inventory[alt_index].partDescription + "\n";
					message += "In Stock: " + inventory[alt_index].total + "\n\n";
					message += "Use this part instead?\n";
					message += "[OK] Use " + inventory[alt_index].partNumber + ".\n";
					message += "[Cancel] Use " + part + ".";

					//send message to user
					if (confirm(message)) {
						//if yes, save new index and use that
						index = alt_index
						x.value = inventory[alt_index].partNumber;
					}

				}

				//check in WS Subs list (returns the list of parts)
				var ws_index = z.findAlt(part, "WS Sub");
				if (ws_index != -1) {

					//check for index in inventory report
					var inv_index = inventory.findIndex(object => {
						return object.partNumber.toLowerCase() == ws_subs[ws_index].wsSub.toLowerCase();
					});

					//if we have it in invreport, ask if user wants to sub it
					if (inv_index > -1) {
						var message = "This part is currently not in Viewpoint, but we found an alternative: \n\n";
						message += "Part Number: " + inventory[inv_index].partNumber + "\n";
						message += "Part Description: " + inventory[inv_index].partDescription + "\n";
						message += "In Stock: " + inventory[inv_index].total + "\n\n";
						message += "Use this part instead?\n";
						message += "[OK] Use " + inventory[inv_index].partNumber + ".\n";
						message += "[Cancel] Use " + part + ".";

						//send message to user
						if (confirm(message)) {
							//if yes, save new index and use that
							index = inv_index
							x.value = inventory[index].partNumber;
						}
					}
				}
			}

			//if we did not find anything in previous searched, null out current row and enter into new part dialog
			if (index == -1) {

				//category
				table.rows[rowIndex].cells[3].innerHTML = null;

				//description
				table.rows[rowIndex].cells[4].innerHTML = null;

				//OEM
				table.rows[rowIndex].cells[5].innerHTML = null;

				//Cost
				cost = table.rows[rowIndex].cells[9].children;
				cost[0].value = null;

				//Price
				price = table.rows[rowIndex].cells[10].children;
				price[0].value = null;

				//Stock
				table.rows[rowIndex].cells[11].innerHTML = null;

				//show add material button
				table.rows[rowIndex].cells[12].style.display = "block";


				if (type == null) {
					//click button
					button = table.rows[rowIndex].cells[12].children;
					button[0].click();
				}
			}
			//we found a match, fill in info with invreport detail
			else {

				//if this is a kit, run 'kit validation'
				if (kit_id != "" && inventory[index].partCategory == "PW-KITS") {

					//will return true if this problem causes an error
					if (kit_validation(index)) {
						x.value = "";
						return;
					}
				}

				//update part number with how it sits in catalog
				var input = table.rows[rowIndex].cells[1].children;
				input[0].value = inventory[index].partNumber;

				//check status for inactive (if inactive return)
				if (inventory[index].status == "Discontinued") {
					alert(inventory[index].partNumber + " has been discontinued. Please select another part.");
					input[0].style.backgroundColor = "Red";
				} else if (inventory[index].status == "EOL") {
					alert("[WARNING] " + inventory[index].partNumber + " is end of life. You may still add this part to your BOM but this part may not be available when requesting parts.");
				}

				//category
				table.rows[rowIndex].cells[3].innerHTML = inventory[index].partCategory;

				//description
				table.rows[rowIndex].cells[4].innerHTML = inventory[index].partDescription;

				//OEM
				table.rows[rowIndex].cells[5].innerHTML = inventory[index].manufacturer;

				//check category and update cost/price
				for (var i = 0; i < active_cat.length; i++) {
					if (inventory[index].partCategory.toLowerCase() == active_cat[i].toLowerCase()) {
						inventory[index].cost = 0;
						inventory[index].price = 0;
						break;
					}
				}

				for (var i = 0; i < need_quote.length; i++) {
					if (inventory[index].partCategory.toLowerCase() == need_quote[i].toLowerCase()) {
						inventory[index].cost = 0;
						inventory[index].price = 0;
						break;
					}
				}

				//Cost
				cost = table.rows[rowIndex].cells[9].children;
				cost[0].value = inventory[index].cost;

				//if this is a PW kits, calculate cost of each part inside kit for total cost (and open dialog if kit has optional parts)
				if (inventory[index].partCategory == "PW-KITS")
					cost[0].value = get_kit_cost(inventory[index].partNumber);

				//Price
				price = table.rows[rowIndex].cells[10].children;
				price[0].value = inventory[index].price;

				//total stock
				table.rows[rowIndex].cells[11].innerHTML = inventory[index].total;

				//hide button if showing
				table.rows[rowIndex].cells[12].style.display = "none";

				//grab subs dropdown (to be modified in next step)
				var subs = table.rows[rowIndex].cells[6].children;
				var getOptions = subs[0].children; //grab children of original cell (table)
				getOptions = getOptions[0].children; //grab children of table (tr)
				getOptions = getOptions[0].children; //grab children of tr (td)
				var getSelect = getOptions[0].children; //grab 1st child of td (select)

				//get indexed list of ws_subs
				var ws_sub_list = get_ws_subs(inventory[index].partNumber);

				//if subparts is not blank, highlight yellow (we have subs available)
				if (inventory[index].subPN != "" || ws_sub_list.length > 0) {
					getSelect[0].style.borderColor = "Yellow";
					getOptions[1].style.display = "block";

					//if all subs is checked, then add any listed
					if (u.eid("default_all_subs").checked) {
						//get button and click it (set auto_sub to true so we don't show dialog)
						auto_sub = true;

						var temp = table.rows[rowIndex];
						temp = temp.cells[6];

						//check for null
						if (temp.childNodes[1] == null)
							temp = temp.childNodes[0];
						else
							temp = temp.childNodes[1];

						//check for null
						if (temp.childNodes[1] == null)
							temp = temp.childNodes[0];
						else
							temp = temp.childNodes[1];

						temp = temp.childNodes[0];
						temp = temp.cells[1];
						temp = temp.childNodes[0];
						temp.click();
						save_subs();

						//turn auto_sub off in case we click and modify
						auto_sub = false;
					}
				} else {
					getSelect[0].style.borderColor = "#000B51";
					getOptions[1].style.display = "none";
				}
			}

			//if last row, add another row
			if (count == rowIndex + 1) {
				z.addOne();
			}

			//mark that the form has been modified
			form_modified = true;

			//render autocomplete menu for new items
			z.render_autocomplete();

		}

		//gets total cost of a kit given a kit_id
		//param 1 = kit_id (matches id in fst_bom_kits)
		function get_kit_cost(kit_id) {

			//get list of parts related to kit_id in kit_detail
			var kit_parts = kit_detail.filter(function(kit_part) {
				return kit_part.kit_id == kit_id && kit_part.type == "Required";
			});

			//cycle through parts, get inventory cost and add to total_cost
			var total_cost = 0;

			for (var i = 0; i < kit_parts.length; i++) {

				//get index in inventory
				var index = inventory.findIndex(object => {
					return object.partNumber.toLowerCase() == kit_parts[i].partNumber.toLowerCase();
				});

				//add to total
				total_cost += (parseFloat(inventory[index].cost) * parseInt(kit_parts[i].quantity));

			}

			//look for optional parts & open dialog if they exist
			var optional_parts = kit_detail.filter(function(kit_part) {
				return kit_part.kit_id == kit_id && kit_part.type == "Optional";
			});

			if (optional_parts.length > 0)
				open_kit_optional_part_dialog(optional_parts);

			return total_cost;
		}

		// THIS IS A RECURSIVE FUNCTION
		// handles validating any parts entered onto a kit (we want to make sure there is no kit loops)
		// ex. kit a has kit b, kit b has kit c, kit c has kit a (would cause major issues later on)
		// param 1 = index for inventry array (see invreport db table)
		// param 2 = current list of kits (init as empty array, add each kit instance as we go. If we run into the same kit, throw error)
		function kit_validation(index, curr_kits = []) {

			//step 1: check (make sure index does not equal current kit)
			if (inventory[index].partNumber == kit_id) {
				alert("Error: The user entered part # is either the kit referenced OR has this kit somewhere within it. You cannot use this part in this kit.");
				return true;
			}

			//step 2: check if we have this part # within the kit somewhere
			if (curr_kits.indexOf(inventory[index].partNumber) > -1) {
				alert("Error: This kit has a cycle at some point. You cannot use this part in this kit.");
				return true;
			}

			//step 3: push current kit to curr_kits
			curr_kits.push(inventory[index].partNumber);

			//step 4: filter out parts related to this kit
			var check_parts = kit_detail.filter(function(p) {
				return p.kit_id == inventory[index].partNumber;
			});

			//step 5: loop through parts. If we see a PW-KITS part, call kit_validation recursively
			for (var i = 0; i < check_parts.length; i++) {

				//get index in invreport
				var inv_index = inventory.findIndex(object => {
					return object.partNumber.toLowerCase() == check_parts[i].partNumber.toLowerCase();
				});

				if (inv_index != -1 && inventory[inv_index].partCategory == "PW-KITS")
					return kit_validation(inv_index, curr_kits);

			}

			//if we pass all checks, return true
			return false;

		}

		//handles opening dialog box to add optional parts to BOM as a part of a new kit
		// param 1 = optional parts (in the same format as fst_bom_kits_detail db table)
		function open_kit_optional_part_dialog(opt_parts) {

			//remove any previously entered optional parts
			document.querySelectorAll('.optional_parts_row').forEach(function(a) {
				a.remove()
			})

			//loop through optional parts and add to table
			var table = u.eid("optional_parts_table");

			for (var i = 0; i < opt_parts.length; i++) {

				//insert row
				var row = table.insertRow(-1);
				row.classList.add("optional_parts_row");

				//insert first cell (button to add)
				var cell = row.insertCell(0);
				cell.innerHTML = "<button onclick = 'add_kit_opt_to_cart(this)'>Add to Cart</button>";

				//insert part #
				var cell = row.insertCell(1);
				cell.innerHTML = opt_parts[i].partNumber;

				//insert quantity placeholder
				var cell = row.insertCell(2);
				cell.innerHTML = opt_parts[i].quantity;

				//insert description 
				var cell = row.insertCell(3);

				//get index in inventory
				var index = inventory.findIndex(object => {
					return object.partNumber.toLowerCase() == opt_parts[i].partNumber.toLowerCase();
				});

				cell.innerHTML = inventory[index].partDescription;

			}

			//open dialog box
			$("#optional_parts_dialog").dialog({
				width: "auto",
				height: "auto",
				dialogClass: "fixedDialog",
			});
		}

		//handles adding kit option to kart
		// param 1 = 'this' (element that is clicked)
		function add_kit_opt_to_cart(targ) {

			//work our way back to the row <tr> tag
			var td = targ.parentNode;
			var tr = td.parentNode;

			//work to part # and quantity
			var part = tr.childNodes[1].innerHTML;
			var quantity = tr.childNodes[2].innerHTML;

			//grab table
			var table = u.eid("shoppingCart-table");

			//grab last index of table
			var lastIndex = table.rows.length - 1;

			//part number
			var pn = table.rows[lastIndex].cells[1].children;
			pn[0].value = part;

			//call partInfo with part filled in
			z.partInfo(pn[0], null);

			//quantity
			var q = table.rows[lastIndex].cells[2].children;
			q[0].value = quantity;

			//refresh totals
			z.totals();

			//remove row
			tr.remove();

			//if no more rows are left, close dialog box
			var check_class = u.class("optional_parts_row");

			if (check_class.length == 0)
				$("#optional_parts_dialog").dialog('close');

		}

		//gets list of ws_subs indexes
		//param 1 = target part #
		function get_ws_subs(part) {

			//init array of indexes
			var indexes = [];

			//look through ws list and send back array of indexes
			for (var i = 0; i < ws_subs.length; i++) {
				if (ws_subs[i].partNumber.toLowerCase() == part.toLowerCase())
					indexes.push(i);
			}

			return indexes;
		}

		//renders autocomplete settings when a new row has been added.
		z.render_autocomplete = function() {

			//renders autcomplete menu for all inputs with parts as class
			$(".parts").on("focus", function(event) {
				$(this).autocomplete({
					minLength: 2,
					source: custom_source,
					focus: function(event, ui) {
						//$( this ).val( ui.item.label );
						return false;
					},
					select: function(event, ui) {
						$(this).val(ui.item.pn);
						//$( "#project-id" ).val( ui.item.value );
						//$( "#project-description" ).html( ui.item.desc );
						//return false;
					}
				}).each(function() {
					$(this).data('ui-autocomplete')._renderItem = function(ul, item) {

						if (item.pref_part == 'TRUE') {
							return $("<li>")
								.append("<a><b>*(PN: " + item.label + ") " + item.partDescription + " (Qty: " + item.total + ") (Price: " + accounting.formatMoney(item.price) + ")</b></a>")
								.appendTo(ul);
						} else if (item.hot_part == 'TRUE') {
							return $("<li>")
								.append("<a><b>HOT LIST: (PN: " + item.label + ") " + item.partDescription + " (Qty: " + item.total + ") (Price: " + accounting.formatMoney(item.price) + ")</b></a>")
								.appendTo(ul);
						} else {
							return $("<li>")
								.append("<a>(PN: " + item.label + ") " + item.partDescription + " (Qty: " + item.total + ") (Price: " + accounting.formatMoney(item.price) + ")</a>")
								.appendTo(ul);
						}


					};

				});
			});

		}

		//updates data object depending on settings
		z.update_object = function(just_stock) {

			data = [];
			for (var i = 0; i < inventory.length; i++) {

				//depending on checkbox value, only include values we have in stock
				if ((!just_stock || inventory[i].total > 0) && inventory[i].status != "Inactive") {

					var tempArray;
					tempArray = {
						label: inventory[i].partNumber,
						pref_part: inventory[i].pref_part,
						hot_part: inventory[i].hot_part,
						partDescription: inventory[i].partDescription,
						total: inventory[i].total,
						price: inventory[i].price

					};

					data.push(tempArray);
				}
			}

		}

		//updates most recent sub or mmd cell
		function update_recent() {

			//grab subs and mmd defaults / classes
			var sub = u.eid("default_subs").value,
				mmd = u.eid("default_MMD").value,
				subs_class = u.class("subs"),
				mmd_class = u.class("mmd");

			//update most recent value
			subs_class[subs_class.length - 1].value = sub;
			mmd_class[mmd_class.length - 1].value = mmd;

		}

		//adds one part from the catalog on click
		z.singleAdd = function() {

			var myTable = u.eid("singlePart");
			var objCells = myTable.rows.item(1).cells;
			var currPart = [];

			//collect current part into
			for (var i = 1; i < 6; i++) {
				currPart.push(objCells.item(i).innerHTML);
			}

			//grab quantity
			currPart[5] = u.eid("single_quantity").value;

			//grab table
			var table = u.eid("shoppingCart-table");

			//insert new row and add classname to it
			var row = table.insertRow(-1);
			row.classList.add("shoppingCart-row");

			//bring over information from the line
			//part number
			var cell = row.insertCell(0);
			cell.innerHTML = currPart[0];

			//description
			var cell = row.insertCell(1);
			cell.innerHTML = currPart[1];

			//OEM
			var cell = row.insertCell(2);
			cell.innerHTML = currPart[2];

			//Cost
			var cell = row.insertCell(3);
			cell.innerHTML = currPart[3];

			//Price
			var cell = row.insertCell(4);
			cell.innerHTML = currPart[4];

			//Quantity
			var cell = row.insertCell(5);
			cell.innerHTML = currPart[5];

			z.addOne();

		}

		//adds extra row to material entry table if applicable
		z.addOne = function() {
			//grab table
			var table = u.eid("shoppingCart-table");

			//insert new row and add classname to it
			var row = table.insertRow(-1);
			row.classList.add("shoppingCart-row");

			//X to remove row
			var cell = row.insertCell(0);
			cell.className = "remove"
			cell.addEventListener('click', z.removePart);
			cell.innerHTML = "&#10006";

			//part number
			var cell = row.insertCell(1);
			cell.innerHTML = "<input class = 'parts ui-autocomplete-input' onchange = 'z.partInfo(this, null)' placeholder = 'Part Number' style = 'width: 15em' autocomplete = 'off'>";

			//quantity
			var cell = row.insertCell(2);
			cell.innerHTML = "<input class = 'quantity' style = 'width: 6em' type = 'number' value = '0' min = '0' onchange = 'z.totals()'>";

			//part category
			var cell = row.insertCell(3);
			cell.classList.add('part_category');

			//description
			var cell = row.insertCell(4);

			//oem
			var cell = row.insertCell(5);

			//subs
			//depending on default value, add different selected item
			var yes_sel = "";
			if (u.eid("default_subs").value == "Yes") {
				yes_sel = "selected";
			}

			var cell = row.insertCell(6);
			cell.classList.add("hide_for_kit");
			var table = "<table>";
			table += "<tr>";
			table += "<td style = 'border:none'><select class = 'custom-select subs' ><option>No</option><option " + yes_sel + ">Yes</option></select></td>";
			table += "<td style = 'border:none; display: none'><button onclick='z.checkSubs(this)'>?</button></td>";
			table += "</tr>";
			table += "</table>";

			cell.innerHTML = table;

			//depending on default value, add different selected item
			var no_sel = "",
				misc_sel = "";
			if (u.eid("default_MMD").value == "No")
				no_sel = "selected";
			else if (u.eid("default_MMD").value == "Misc")
				misc_sel = "selected";

			//mmd
			var cell = row.insertCell(7);
			cell.classList.add("mmdOpt");
			cell.innerHTML = "<select style = 'width: 5em' class = 'custom-select mmd' onchange = 'z.totals()'><option>Yes</option><option " + no_sel + ">No</option><option " + misc_sel + ">Misc</option></select>";

			//type
			var cell = row.insertCell(8);
			cell.classList.add("hide_for_quote");
			cell.innerHTML = "<select class = 'custom-select type' onchange = 'z.totals()'><option>Required</option><option>Optional</option></select>";

			//cost
			var cell = row.insertCell(9);

			//if this is for a kit, make cost read-only
			if (kit_id == "" || kit_id == null)
				cell.innerHTML = "<input class = 'cost' type = 'number' min = '0' onchange = 'z.totals()'>";
			else
				cell.innerHTML = "<input class = 'cost' type = 'number' min = '0' onchange = 'z.totals()' readOnly>";

			//price
			var cell = row.insertCell(10);
			cell.innerHTML = "<input class = 'price' type = 'number' min = '0' onchange = 'z.totals()'>";

			//price
			var cell = row.insertCell(11);
			cell.className = "stock"

			//add new material button
			var cell = row.insertCell(12);
			cell.style.display = "none";
			cell.className = "add_materials";
			cell.innerHTML = "<button onclick = 'z.addToDialog(this)'>Add Material</button>"

			//add last column which will hold manual/auto tell
			var cell = row.insertCell(13);
			cell.style.display = "none";
			cell.innerHTML = "<input class = 'manual_tell' value = 'A'>";

			// refresh pricing
			z.totals();

		}

		//pushs parts from material entry to quote
		function add_to_quote() {

			//init arrays and variables to be used
			var part_error = false,
				quantity_error = false,
				price_error = false,
				inactive_error = false,
				cost_error = false;

			var partArray = [],
				costArray = [],
				priceArray = [],
				quantityArray = [],
				mmdArray = [],
				subsArray = [],
				tellArray = [],
				mmd_opt = false,
				subOpts = [];

			var parts = u.class("parts");
			var cost = u.class("cost");
			var price = u.class("price");
			var quantity = u.class("quantity");
			var subs = u.class("subs");
			var mmd = u.class("mmd");
			var tell = u.class("manual_tell");
			var add_button_class = u.class("add_materials");

			//if mmd, set opt to true
			if (mmd_tell == "yes")
				mmd_opt = true;

			for (var i = 0; i < parts.length - 1; i++) {

				//check for blank part number & inactive parts
				if (parts[i].value == "") {
					part_error = true;
					parts[i].classList.add("required_error");
				} else if (parts[i].style.backgroundColor == "red") {
					inactive_error = true;
				} else {
					parts[i].classList.remove("required_error");
				}

				//check for blank or 0 quantity
				if (quantity[i].value == 0) {
					quantity_error = true;
					quantity[i].classList.add("required_error");
				} else {
					quantity[i].classList.remove("required_error");
				}

				//check for blank or 0 price
				if ((price[i].value == 0 || price[i].value == "") && mmd[i].value != "Yes") {
					price_error = true;
					price[i].classList.add("required_error");
				} else {
					price[i].classList.remove("required_error");
				}

				//check for blank or 0 cost
				if (cost[i].value == 0 || cost[i].value == "") {
					cost_error = true;
				}

				//check if "Add Material" button is showing (still need to enter info)
				if (add_button_class[i].style.display == "block") {
					alert("Part Number: " + parts[i].value + " is not in our catalog. Please press the [Add Material] button and enter the required information for this part.");
					return;
				}

				//tells if we found a match
				var same_part = false;

				//check to see if part has already been added, if so group together
				for (var j = 0; j < partArray.length; j++) {
					if (partArray[j] == parts[i].value) {
						same_part = true;
						break;
					}
				}

				//if we found a match, add the quantity to the existing part, and move on
				if (same_part) {
					quantityArray[j] += parseInt(quantity[i].value);
				} else {
					partArray.push(parts[i].value);
					costArray.push(cost[i].value);
					priceArray.push(price[i].value);
					quantityArray.push(parseInt(quantity[i].value));
				}

				if (mmd_opt) {
					mmdArray.push(mmd[i].value);
				} else {
					mmdArray.push("No");
				}

				subsArray.push(subs[i].value);
				tellArray.push(tell[i].value);

				//cycle through subs array and grab any matches
				//updated to move backwards (this accounts for auto-save feature so we can make sure we hit user suggestions first)
				for (var j = part_sub_list.length - 1; j > -1; j--) {

					//if we find a match, add to subsArray to pass to server
					if (parts[i].value == part_sub_list[j][0]) {
						subOpts.push(part_sub_list[j][1]);
						break;
					}
				}

				//if we do not find a match, add a blank array
				if (j == -1)
					subOpts.push(['']);

			}

			//check length of parts array (make sure user has at least 1 part)
			if (partArray.length == 0) {
				alert("Error: Must have at least 1 part in table to add to quote.");
				return;
			}

			//once we have our list, check for any error messages that came through (may send back to user)
			if (part_error || quantity_error || price_error || inactive_error) {

				//init error message to user, add helpful hints depending on what error we found
				var err_msg = "There are issues with the parts that you are trying to add. Problems identified are in yellow (inactive in red):\n";
				var counter = 1;

				if (part_error) {
					err_msg += counter + ") At least one of your parts is blank. Please remove it or add a part.\n";
					counter++;
				}
				if (quantity_error) {
					err_msg += counter + ") At least one of your parts is missing a quantity or is listed as 0.\n";
					counter++;
				}
				if (price_error) {
					err_msg += counter + ") At least one of your parts is missing a price or is listed as 0.\n";
					counter++;
				}
				if (inactive_error) {
					err_msg += counter + ") At least one of your parts is inactive. Please select another part.\n";
					counter++;
				}
				alert(err_msg);
				return;
			}

			//if we found any parts with no cost, lets check to make sure they want to add it
			if (cost_error) {

				var message = "One of your parts does not have a cost, would you like to proceed?\n\n";
				message += "[OK] Add parts anyways.\n";
				message += "[Cancel] Go back and update cost.";

				//send message to user
				if (!confirm(message)) {
					//if no, return to page
					return;
				}

			}

			//ajax request to communicate with database
			$.ajax({
				type: "POST", //type of method
				url: "materialEntry_helper.php", //your page
				data: {
					partsArray: partArray,
					costArray: costArray,
					priceArray: priceArray,
					quantityArray: quantityArray,
					subsArray: subsArray,
					mmdArray: mmdArray,
					tellArray: tellArray,
					subOpts: subOpts,
					mc_request_parts: mc_request_parts,
					quote: quote,
					tell: 'material_entry',
					user_info: JSON.stringify(user_info)
				}, // passing the values
				success: function(response) {

					if (response != "") {
						alert(response);

					} else {
						alert("Your parts have been entered successfully, returning to FST...");
						form_modified = false;
						submitting = true;

					}
				}
			});
		}

		//handles reading data into database to save current state of a kit
		function save_current_state_of_kit() {

			//loop through 'parts' & 'quantity' classes and push to updated_kit
			var parts = u.class("parts");
			var quantity = u.class("quantity");
			var type = u.class("type");

			var updated_kit = [];

			//only loop through length - 1 (last entry will not be valid)
			for (var i = 0; i < parts.length - 1; i++) {

				//if not blank, then push
				if (parts[i].value != "" && parts[i].value != null) {
					updated_kit.push({
						part: parts[i].value,
						quantity: quantity[i].value,
						type: type[i].value
					});
				}
			}

			//init form data to send to server
			var fd = new FormData();

			//add kit information
			fd.append("kit_id", kit_id);
			fd.append("updated_kit", JSON.stringify(updated_kit));
			fd.append("kit_price", u.eid("kit_price").value);
			fd.append("kit_phase", u.eid("kit_phase").value);

			//add tell
			fd.append("tell", "kit_update");

			//access database
			$.ajax({
				url: 'materialEntry_helper.php',
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

					//let user know info has been saved & redirect back to kit home
					alert("The Kit has been saved.")
					//location = 'kit_home.php?';

				}
			})
		}

		//handles popping up dialog before user submits request
		function add_to_order_reason() {
			open_dialog("add_to_order_dialog");
		}

		function add_to_order_check() {
			if (u.eid("add_to_order_reason").value == "Other")
				u.eid("add_to_order_other").style.display = "block";
			else
				u.eid("add_to_order_other").style.display = "none";
		}

		//handles reading data into database to add any new parts to an order
		function add_to_order() {

			// Make sure user has provided a reason
			if (u.eid("add_to_order_reason").value == "" || (u.eid("add_to_order_reason").value == "Other" && u.eid("add_to_order_other").value == "")) {
				alert("Please provide a reason for adding these parts to the order.");
				return;
			}

			//loop through 'parts' & 'quantity' classes and push to updated_kit
			var parts = u.class("parts");
			var quantity = u.class("quantity");
			var cost = u.class("cost");

			var new_parts = [];

			//only loop through length - 1 (last entry will not be valid)
			for (var i = 0; i < parts.length - 1; i++) {

				//if not blank, then push
				if (parts[i].value != "" && parts[i].value != null) {
					new_parts.push({
						part: parts[i].value,
						quantity: quantity[i].value,
						cost: cost[i].value
					});
				}
			}

			//init form data to send to server
			var fd = new FormData();

			//add kit information
			fd.append("pq_id", pq_id);
			fd.append("pq_type", pq_type);
			fd.append("new_parts", JSON.stringify(new_parts));
			fd.append("pq_overview", JSON.stringify(pq_overview));
			fd.append("user_info", JSON.stringify(user_info));
			fd.append("reason", u.eid("add_to_order_reason").value);
			fd.append("reason_other", u.eid("add_to_order_other").value);

			//add tell
			fd.append("tell", "add_to_order");

			//access database
			$.ajax({
				url: 'materialEntry_helper.php',
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

					//let user know info has been saved & redirect back to kit home
					alert("The part(s) has been added to your order. Returning to orders module..");

					if (pq_type == "orders")
						location = 'terminal_orders.php';
					else
						location = 'terminal_allocations_new.php';
				}
			})
		}

		//global to determine if price has been manually changed since page load
		var manual_kit_price = false;

		//recalculates totals
		z.totals = function() {

			var totalCost = 0,
				totalPrice = 0,
				total_MMD = 0,
				total_nonMMDc = 0,
				total_nonMMDp = 0,
				total_misc_c = 0,
				total_misc_p = 0;
			var cost = u.class("cost");
			var price = u.class("price");
			var quantity = u.class("quantity");
			var mmd = u.class("mmd");
			var type = u.class("type");

			for (var i = 0; i < cost.length; i++) {

				//treat the summary section different if this is verizon
				if (customer.toLowerCase() == "verizon") {

					//look for mmd value to see what bucket it falls in
					if (mmd[i].value == "Yes") {
						// default price & cost to $0
						cost[i].value = 0;
						price[i].value = 0;

						total_MMD += price[i].value * quantity[i].value;
						totalCost += price[i].value * quantity[i].value;

					} else if (mmd[i].value == "Misc") {
						total_misc_c += cost[i].value * quantity[i].value;
						total_misc_p += price[i].value * quantity[i].value;
						totalCost += cost[i].value * quantity[i].value;
					} else {
						total_nonMMDc += cost[i].value * quantity[i].value;
						total_nonMMDp += price[i].value * quantity[i].value;
						totalCost += cost[i].value * quantity[i].value;

					}
				}
				//just need total cost
				else {

					//if this is a kit, make sure we only add required, otherwise add to total cost
					if (kit_id == "" || type[i].value == "Required")
						totalCost += cost[i].value * quantity[i].value;
				}

				//if this is a kit, make sure we only add required, otherwise add to total price
				if (kit_id == "" || type[i].value == "Required")
					totalPrice += price[i].value * quantity[i].value;
			}

			if (customer.toLowerCase() == "verizon") {
				//MMD Summary Table
				//mmd overhead
				u.eid("mmdSH_cost").innerHTML = accounting.formatMoney(0);
				u.eid("mmdSH_price").innerHTML = accounting.formatMoney(total_MMD * 0.25);

				//non-mmd
				u.eid("nonMMD_cost").innerHTML = accounting.formatMoney(total_nonMMDc);
				u.eid("nonMMD_price").innerHTML = accounting.formatMoney(total_nonMMDp);

				//mmd
				u.eid("mmd_cost").innerHTML = accounting.formatMoney(total_MMD);
				u.eid("mmd_price").innerHTML = accounting.formatMoney(total_MMD);

				//misc materials
				u.eid("mmdMisc_cost").innerHTML = accounting.formatMoney(total_misc_c);
				u.eid("mmdMisc_price").innerHTML = accounting.formatMoney(total_misc_p);

				//totals
				u.eid("mmdTotal_cost").innerHTML = accounting.formatMoney(totalPrice);
				u.eid("mmdTotal_price").innerHTML = accounting.formatMoney(totalPrice * 1.25);

			} else {
				//summary table
				u.eid("totalCost").innerHTML = accounting.formatMoney(totalCost);
				u.eid("totalMargin").innerHTML = accounting.formatMoney(totalPrice - totalCost);
				u.eid("totalPrice").innerHTML = accounting.formatMoney(totalPrice);

				//if kit has been adjusted manually, don't update again
				if (!manual_kit_price)
					u.eid("kit_price").value = totalPrice.toFixed(2);
			}

			//if this is a kit, call kit_passive_active() to pre-set phase 
			if (kit_id != "")
				u.eid("kit_phase").value = kit_passive_active();
		}

		//handles determining if a kit is active or passive based on the parts added to a kit
		function kit_passive_active() {

			//loop through part categories, if we find an active category, auto phase to active, otherwise passive
			var categories = u.class("part_category");
			for (var i = 0; i < categories.length; i++) {

				if (active_cat.indexOf(categories[i].innerHTML) != -1)
					return "Active";

			}

			//if we don't find an active part, assume passive
			return "Passive"

		}

		//used to import BOM to file. type refers to two option (ibwave = ibwave file|csv refers to self made CSV file)
		z.addBOM = function(type) {

			var file = $('#' + type + '_file')[0].files[0];
			var fd = new FormData();
			var test, input;

			fd.append('theFile', file);
			fd.append('type', type);
			$.ajax({
				url: 'addBOM.php',
				type: 'POST',
				processData: false,
				contentType: false,
				data: fd,
				success: function(response) {

					//check for error message
					test = response.substr(0, 5)

					if (test == "ERROR") {
						alert(response);

					} else if (type == "ibWave") {
						//parse resonse into array of arrays 
						var result = $.parseJSON(response);

						//grab current length of table
						var table = u.eid("shoppingCart-table");
						var count = table.rows.length;

						for (var i = 0; i < result[0].length; i++) {
							z.addOne();
						}

						for (var i = 0; i < result[0].length; i++) {
							//part Number
							input = table.rows[count - 1].cells[1].children;
							input[0].value = result[0][i];

							//run function to grab relevant info
							z.partInfo(input[0], 'import');

							//quantity
							input = table.rows[count - 1].cells[2].children;
							input[0].value = Math.round(result[4][i]);

							count++;

							//category
							//table.rows[rowIndex].cells[2].innerHTML = partInfo[0];								

						}



					} else if (type == "csv") {
						//parse resonse into array of arrays 
						var result = $.parseJSON(response);

						//grab current length of table
						var table = u.eid("shoppingCart-table");
						var count = table.rows.length;

						//add number of rows necessary
						for (var i = 0; i < result[0].length - 1; i++) {
							z.addOne();
						}

						//loop through result array and add to BOM
						for (var i = 1; i < result.length; i++) {

							//part Number
							input = table.rows[count - 1].cells[1].children;
							input[0].value = result[i][0];

							//run function to grab relevant info
							z.partInfo(input[0], 'import');

							//quantity
							input = table.rows[count - 1].cells[2].children;
							input[0].value = Math.round(result[i][1]);

							count++;

						}

					}
				}
			});

			u.eid(type + "_file").value = null;
			z.totals();

		}

		//handles downloading csv template
		z.download_csv_template = function() {

			//initialize csvContent to export csv
			let csvContent = "data:text/csv;charset=utf-8,";

			// add headers to CSV
			csvContent += "Part Number,Quantity\r\n";

			//set encoded uri for download
			var encodedUri = encodeURI(csvContent);
			var link = u.eid("csv_template");
			link.setAttribute("href", encodedUri);
			link.setAttribute("download", "CSV File Template.csv");
			link.click();
		}

		//removes line from tables
		z.removePart = function() {
			//grab current length of table
			var table = u.eid("shoppingCart-table");
			var count = table.rows.length;

			var row = $(this).parent().index();
			row = Math.abs(row);

			if (row == 1)
				row = row + 2;

			if (row == 3 && count == 3 || row == (count - 1)) {
				//do nothing
			} else {
				table.deleteRow(row);

			}
			z.totals();
		}

		//global variable to hold current row
		var new_mat_row;

		//adds current part to dialog and shows dialog box
		z.addToDialog = function(x) {
			//grab current length of table
			var table = u.eid("shoppingCart-table");
			var count = table.rows.length;
			var input;

			//use to find row index (found on stack overflow https://stackoverflow.com/questions/6470877/javascript-getting-td-index)
			var cellAndRow = $(x).parents('td,tr');
			var cellIndex = cellAndRow[0].cellIndex
			var row = cellAndRow[1].rowIndex; //holds current row index

			//input part # into table
			input = table.rows[row].cells[1].children;
			u.eid("newPart").value = input[0].value;

			//set current row
			new_mat_row = row;

			//show dialog
			u.eid("new-part-button").click();
		}

		//aligns any changes for new parts
		function align_parts() {

			//grab current table
			var table = u.eid("shoppingCart-table");

			//grab target input
			var input = table.rows[new_mat_row].cells[1].children;

			//update input
			input[0].value = u.eid("newPart").value;

		}

		//global array that holds parts we will send MC request for
		var mc_request_parts = [];

		//add material to material creation db
		z.matCreation = function() {

			//pass information to variables
			var part = u.eid("newPart").value,
				description = u.eid("newDescription").value,
				manufacturer = u.eid("newManufacturer").value,
				category = u.eid("newCategory").value,
				uom = u.eid("newUOM").value,
				vendor = u.eid("newVendor").value,
				cost = u.eid("newCost").value,
				cut_sheet_link = u.eid("cut_sheet_link").value,
				phase, input;

			//check to see if any required info is blank
			if (part == "" || description == "" || manufacturer == "" || category == "" || uom == "" || cost == "") {
				alert("Missing info");
				return;
			}

			//default phase to passive
			phase = '06000';

			//if it matches an active category flip it to active
			for (var i = 0; i < active_cat.length; i++) {
				if (category.toLowerCase() == active_cat[i].toLowerCase()) {
					phase = '03000';
					break;
				}
			}

			//initalize form data (will carry all form data over to server side)
			var fd = new FormData();

			//add file (if applicable)
			if (u.eid("cut_sheet").value != "") {
				var file = $("#cut_sheet")[0].files[0];
				fd.append("cut_sheet", file);
			}

			//add other applicable info
			fd.append('part', part);
			fd.append('description', description);
			fd.append('manufacturer', manufacturer);
			fd.append('category', category);
			fd.append('uom', uom);
			fd.append('vendor', vendor);
			fd.append('cost', cost);
			fd.append('phase', phase);
			fd.append('cut_sheet_link', cut_sheet_link)
			fd.append('quote', quote);
			fd.append('tell', 'material_creation')

			//ajax request to communicate with database
			$.ajax({
				url: 'updatePart.php',
				type: "POST", //type of method
				processData: false,
				contentType: false,
				data: fd,
				success: function(response) {

					//check for error
					if (response != "") {
						alert(response);
						return;
					}

					var table = u.eid("shoppingCart-table");
					var count = table.rows.length;
					count = Math.abs(count);

					//search through table till we find a match
					for (var i = 3; i < count; i++) {

						//grab part for this row
						input = table.rows[i].cells[1].children;

						if (input[0].value == part) {
							break;
						}

					}

					//category
					table.rows[i].cells[3].innerHTML = category;

					//description
					table.rows[i].cells[4].innerHTML = description;

					//OEM
					table.rows[i].cells[5].innerHTML = manufacturer;

					//Cost
					input = table.rows[i].cells[9].children;
					input[0].value = cost;

					//set tell to manual
					input = table.rows[i].cells[13].children;
					input[0].value = "M";

					//close dialog
					$("#new-part").dialog('close');

					//hide button
					table.rows[i].cells[12].style.display = "none";

					//set inputs to null
					u.eid("newPart").value = null,
						u.eid("newDescription").value = null,
						u.eid("newManufacturer").value = null,
						u.eid("newCategory").value = null,
						u.eid("newUOM").value = null,
						u.eid("newVendor").value = null,
						u.eid("newCost").value = null;
					u.eid("cut_sheet_link").value = null,
						u.eid("cut_sheet").value = null;


					//if this quote is locked, push to mc_array
					if (locked == "on")
						mc_request_parts.push(part);

					//let user know this info has been saved 
					alert("This information has been saved.");

				}
			});

		}

		//global that omits adding dialog box (only used if adding subs for all)
		var auto_sub = false;

		//check parts for subs if they select yes
		z.checkSubs = function(x) {

			//adjust x (since we are housing this in a table now)
			x = x.parentNode; //parent of button (td)
			x = x.parentNode; //parent of td (tr)
			x = x.parentNode; //parent of tr (tbody)
			x = x.parentNode; //parent of tbody (tabe)

			//use to find row index (found on stack overflow https://stackoverflow.com/questions/6470877/javascript-getting-td-index)
			var cellAndRow = $(x).parents('td,tr');
			var cellIndex = cellAndRow[0].cellIndex;
			var rowIndex = cellAndRow[1].rowIndex; //holds current row index

			//grab table
			var table = u.eid("shoppingCart-table");

			//grab part for this row
			var input = table.rows[rowIndex].cells[1].children;
			var subPart = input[0].value;

			//if input is blank, return
			if (subPart == "")
				return;

			//cost
			input = table.rows[rowIndex].cells[9].children;
			var cost = input[0].value;

			//grab stock for this part
			var stock = table.rows[rowIndex].cells[11].innerHTML;

			//grab index for current part
			var index = inventory.findIndex(object => {
				return object.partNumber.toLowerCase() == subPart.toLowerCase();
			});

			//get list of ws_subs
			var ws_sub_list = get_ws_subs(subPart);

			//look at sub parts for this index
			//if there is something there, create dialog. If not, let user know there is no match
			if ((inventory[index].subPN == "" && ws_sub_list.length == 0) || index == -1) {
				//alert("No Match");
			} else {
				u.eid("partSub-button").click();

				//remove old sub rows
				document.querySelectorAll('.subParts').forEach(function(a) {
					a.remove()
				})

				//parse resonse into array of arrays 
				var result = [];

				if (inventory[index].subPN !== null && inventory[index].subPN != "")
					result = inventory[index].subPN.split(",");

				//go through result and add any ws_subs that were found
				for (var i = 0; i < ws_sub_list.length; i++) {
					if (!result.includes(ws_subs[ws_sub_list[i]].wsSub))
						result.push(ws_subs[ws_sub_list[i]].wsSub);
				}

				//(part, tell, stock, cost)
				//pass sub part through with o = original
				z.subHandler(subPart, "o", stock, cost);

				for (var i = 0; i < result.length; i++) {

					//grab index of substitute part
					var j = inventory.findIndex(object => {
						return object.partNumber.toLowerCase() == result[i].trim().toLowerCase();
					});

					//only pass if not -1
					if (j != -1) {
						//pass sub parts through with s = sub, and relevant stock/cost (1, 2)
						z.subHandler(result[i].trim(), "s", inventory[j].total, inventory[j].cost);
					} else {
						//pass sub parts through with s = sub, (in this case we did not find part in catalog)
						z.subHandler(result[i].trim(), "s", 'part not found in catalog', 'Error');
					}

				}
			}
		}

		//handles creating rows in the subs table for the user to select from
		z.subHandler = function(part, tell, stock, cost) {

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
				var dis = "checked";
				if (cost == "Error")
					dis = "disabled";

				//sub part checkbox
				var cell = row.insertCell(index);
				cell.innerHTML = "<input type = 'checkbox' class = 'sub_check' " + dis + ">";

				//sub part button
				var cell = row.insertCell(index);
				cell.innerHTML = "<button onclick = 'z.subPart(this)'>Substitute</button>";
			}

			//handle original part
			else if (tell == 'o') {
				u.eid("o_pn").innerHTML = part;
				u.eid("o_cost").innerHTML = cost;
				u.eid("o_stock").innerHTML = stock;

			}

		}

		//global object to hold part substitutions
		var part_sub_list = [];

		//handles creating a list of substitute parts
		function save_subs() {

			//grab checks sub list
			var sub_check = u.class("sub_check");

			//init array to be used
			var sub_list = [],
				temp_list = [];

			//loop through list
			for (var i = 0; i < sub_check.length; i++) {
				//if checked, find part and add it to the list
				if (sub_check[i].checked) {
					//use to find row index (found on stack overflow https://stackoverflow.com/questions/6470877/javascript-getting-td-index)
					var cellAndRow = $(sub_check[i]).parents('td,tr');
					var rowIndex = cellAndRow[1].rowIndex; //holds current row index

					//grab part subs table
					var table = u.eid("partSubs");

					//grab part for this row
					var part = table.rows[rowIndex].cells[0].innerHTML;

					//check to make sure we read in the correct info
					if (part == '<b>Substitutes</b>') {
						part = table.rows[rowIndex].cells[1].innerHTML;
					}

					//push to sub list
					sub_list.push(part);

				}

			}

			//grab the targ part number
			var target = u.eid("o_pn").innerHTML;

			//if sub_list is empty, add blank array
			if (sub_list.length != 0) {

				//push to temp and then global array
				temp_list.push(target);
				temp_list.push(sub_list);
				part_sub_list.push(temp_list);

			} else {

				//push to temp and then global array
				temp_list.push(target);
				temp_list.push(['']);
				part_sub_list.push(temp_list);

			}

			//send success message and close dialog
			if (!auto_sub) {
				alert("Substitutions have been saved.");
				$("#partSub-dialog").dialog('close');
			}

			console.log(part_sub_list);
		}

		//handles substituting a part directly
		z.subPart = function(x) {

			//use to find row index (found on stack overflow https://stackoverflow.com/questions/6470877/javascript-getting-td-index)
			var cellAndRow = $(x).parents('td,tr');
			var cellIndex = cellAndRow[0].cellIndex
			var rowIndex = cellAndRow[1].rowIndex; //holds current row index

			//grab part subs table
			var table = u.eid("partSubs");

			//grab part for this row
			var part = table.rows[rowIndex].cells[0].innerHTML;

			//check to make sure we read in the correct info
			if (part == '<b>Substitutes</b>') {
				part = table.rows[rowIndex].cells[1].innerHTML;
			}

			//part we need to replace
			var target = u.eid("o_pn").innerHTML;

			//grab table and length for bom
			var table = u.eid("shoppingCart-table");
			var count = table.rows.length;

			//variable used in loop
			var tempString, input;

			//go through current bom, once we find a match, replace it

			for (var i = 3; i < count; i++) {
				input = table.rows[i].cells[1].children;
				tempString = input[0].value;

				if (tempString == target) {
					input[0].value = part;
					z.partInfo(input[0], null);
					break;
				}
			}

			//close dialog
			$("#partSub-dialog").dialog('close');

		}

		//Enable Functions
		$(document).ready(function() {
			//used to open sub type description on mouseover
			$('#openCatalog').on('click', function() {

				var screenheight = $(window).height();

				$("#parts-catalog").dialog({
					width: "100em",
					height: screenheight - 50,
					dialogClass: "fixedDialog",

				});

			});

			//clear catalog table on close
			$('div#parts-catalog').on('dialogclose', function(event) {
				//remove everything from catalog table
				document.querySelectorAll('.invBody').forEach(function(a) {
					a.remove()
				})
			});

			//used to open sub type description on mouseover
			$('#new-part-button').on('click', function() {

				var row = $(this).parent().index();
				row = Math.abs(row);

				var screenheight = $(window).height();

				$("#new-part").dialog({
					width: "auto",
					height: "auto",
					dialogClass: "fixedDialog",
				});

				//var pos = $('#add_to_quote');
				//$("#new-part").dialog("widget").position({
				//   my: 'left top',
				//   at: 'right bottom',
				//   of: pos
				//});

			});

			//used to open sub type description on mouseover
			$('#partSub-button').on('click', function() {

				if (!auto_sub) {

					$("#partSub-dialog").dialog({
						width: "auto",
						height: "auto",
						dialogClass: "fixedDialog",

					});

				}

			});


		});

		$(document).ajaxStart(function() {
			waiting('on');
		});

		$(document).ajaxStop(function() {
			z.totals();
			waiting('off');

			if (submitting) {
				//location = 'application.php?quote=' + quote;
				location = 'application.php?quote=' + quote;
			}

		});

		//handles searching for existing BOM
		z.use_existing_bom = function() {

			//grab quote number
			var reference_quote = u.eid("reference_quote").value;

			//ajax request to communicate with database
			$.ajax({
				type: "POST", //type of method
				url: "grabInventory.php", //your page
				data: {
					reference_quote: reference_quote
				}, // passing the values
				success: function(response) {

					//if we found no results, let the user know
					if (response == "None") {
						alert("Sorry, no parts were found under Quote " + reference_quote + ".");
						return;
					}

					//init input variable to be used
					var input;

					//parse resonse into array of arrays 
					var result = $.parseJSON(response);

					//grab current length of table
					var table = u.eid("shoppingCart-table");
					var count = table.rows.length;

					//add number of rows necessary
					for (var i = 0; i < result[0].length; i++) {
						z.addOne();
					}

					//loop through table and add part # followed by partinfo function (grabs part information)
					for (var i = 0; i < result[0].length; i++) {

						//part Number
						input = table.rows[count - 1].cells[1].children;
						input[0].value = result[0][i];

						//run function to grab relevant info
						z.partInfo(input[0], 'import');

						//quantity
						input = table.rows[count - 1].cells[2].children;
						input[0].value = Math.round(result[1][i]);

						//increment current count
						count++;

						//potentially add cost and price later

					}

				}
			});
		}

		//handles adding template parts to shopping cart
		z.use_template = function() {

			//grab template name
			var template = u.eid("template_name").value;

			//grab current length of table
			var table = u.eid("shoppingCart-table");
			var count = table.rows.length;

			//input var to be used in loop
			var input;

			//cycle through template array and add any parts that apply
			for (var i = 0; i < templates.length; i++) {

				//check for match
				if (template == templates[i].template) {
					//part Number
					input = table.rows[count - 1].cells[1].children;
					input[0].value = templates[i].partNumber;

					//run function to grab relevant info
					z.partInfo(input[0], 'import');

					//quantity
					input = table.rows[count - 1].cells[2].children;
					input[0].value = templates[i].quantity;

					//increment current count
					count++;

				}

			}

			//go back to material entry tab
			u.eid("defaultOpen").click();

		}

		//handles adding template parts to shopping cart
		z.use_kit = function() {

			//grab current length of table
			var table = u.eid("shoppingCart-table");
			var count = table.rows.length;

			//input var to be used in loop
			var input;

			//cycle through kit parts loaded in through $kit_parts
			for (var i = 0; i < kit_parts.length; i++) {

				//part Number
				input = table.rows[count - 1].cells[1].children; //grabs input field at last row in table
				input[0].value = kit_parts[i].partNumber; //sets input to the part number			

				//run function to grab relevant info
				z.partInfo(input[0], 'import');

				//quantity
				input = table.rows[count - 1].cells[2].children;
				input[0].value = kit_parts[i].quantity;

				//primary/optional
				input = table.rows[count - 1].cells[8].children;
				input[0].value = kit_parts[i].type;

				//increment current count
				count++;

			}
		}

		//hold global variable to tell if form is being modified
		var form_modified = false;

		//handles changing tabs
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

		//handle filters for parts in catalog
		//initialize globals
		var target_rows;
		var filters = [
			[],
			[],
			[],
			[],
			[],
			[],
			[],
			[],
			[],
			[],
			[]
		];

		//look for keyup on search id's
		$("[id*=search]").keyup(function() {
			var col = this.id.replace(/[a-z]/gi, "") - 1;
			filters[col] = $(this).val().trim().replace(/ +/g, ' ').toLowerCase().split(",").filter(l => l.length);
			target_rows.show();
			if (filters.some(f => f.length)) {
				target_rows.filter(function() {
					var texts = $(this).children().map((i, td) => $(td).text().replace(/\s+/g, ' ').toLowerCase()).get();
					return !texts.every((t, col) => {
						return filters[col].length == 0 || filters[col].some((f, i) => t.indexOf(f) >= 0);
					})
				}).hide();
			}
		})

		//windows onload
		window.onload = function() {

			//add event listener logic to notify the user before they exit the site if they have potential unsaved data
			window.addEventListener("beforeunload", function(e) {

				if (!form_modified) {
					return undefined;
				}

				var confirmationMessage = 'It looks like you have been editing something. ' +
					'If you leave before saving, your changes will be lost.';

				(e || window.event).returnValue = confirmationMessage; //Gecko + IE
				return confirmationMessage; //Gecko + Webkit, Safari, Chrome etc.
			});

			//render autocomplete dropdown for parts class
			z.render_autocomplete();

			//click on default open button
			u.eid("defaultOpen").click();

			document.querySelectorAll('.parts').forEach(function(a) {
				a.disabled = false;
			})

			//load in kit info if applicable
			if (kit_id != "")
				z.use_kit();

			//refresh summary section
			z.totals();

			// If quotetype is SM, default MMD to no
			if (quote_type == "SM") {
				u.eid("default_MMD").value = "No";
				update_recent();
			}
		}

		function custom_source(request, response) {
			var matcher = new RegExp($.ui.autocomplete.escapeRegex(request.term), "i");
			response($.grep(data, function(value) {
				return matcher.test(value.label) || matcher.test(value.partDescription);
			}));
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