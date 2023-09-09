<?php
session_start();

//used to grab actual link for the current address
$actual_link = "https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

//Save current site so we can return after log in
$_SESSION['returnAddress'] = $actual_link;

//sub link
$sub_link = substr($actual_link, 0, strpos($actual_link, "newProject"));

//include php functions sheet
include('phpFunctions.php');

//include php HTML renderings
include('phpFunctions_html.php');

//include constants sheet
include('constants.php');

// Load the database configuration file
require_once 'config.php';

//Make sure user has privileges
$query = "SELECT * from fst_users where email = '" . $_SESSION['email'] . "'";
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

// Load in states from CSV
$states = load_csv_array("Static/states.csv");

//set quote (pass to js even if blank) init other variables used
$quote = "";
$existing = 'false';
$duplicate = 'false';
$init_values = [];

//check to see if target_quote is set (if so, we'll base all new values off of this)
if (isset($_GET['target_quote'])) {

	//get quote
	$quote = $_GET['target_quote'];

	//load existing project locations
	$query = "select * from fst_grid WHERE quoteNumber = '" . $quote . "' LIMIT 1;";
	$result = mysqli_query($con, $query);

	//set temp services
	$init_values = mysqli_fetch_array($result);

	//set existing to true;
	$existing = 'true';
}

//check to see if duplicate is set (if so, set duplicate quote field)
elseif (isset($_GET['duplicate'])) {

	//get quote
	$quote  = $_GET['duplicate'];

	//set $duplicate to true;
	$duplicate = 'true';
}

//load designer, market, solution types for dropdown (queries are ran later in the file)
$designerQ = "SElECT * FROM general_designer";
$marketQ = "SElECT * FROM general_market";

//initialize array to be passed to JS
$locations = [];

$cust_drive = [];
$cust_folder = [];
$cust_project = [];

//load existing project locations
$query = "select * from fst_locations";
$result = mysqli_query($con, $query);

//read from query into arrays
while ($rows = mysqli_fetch_assoc($result)) {
	array_push($locations, $rows);
}

//init customer arrays
$customer = [];
$cust_id = [];
$cust_contact = [];
$cust_pl = [];
$cust_phone = [];
$cust_email = [];

//load existing customers in
$query = "select * from fst_customers order by customer";
$result = mysqli_query($con, $query);

//read from query into arrays
while ($rows = mysqli_fetch_assoc($result)) {
	array_push($customer, $rows['customer']);
	array_push($cust_id, $rows['cust_id']);
}

//load in customer contacts
$query = "SELECT * FROM fst_contacts ORDER BY customer, project_lead";
$result = mysqli_query($con, $query);

//read from query into arrays
while ($rows = mysqli_fetch_assoc($result)) {
	array_push($cust_contact, $rows['customer']);
	array_push($cust_pl, $rows['project_lead']);
	array_push($cust_phone, $rows['number']);
	array_push($cust_email, $rows['email']);
}

//load in existing customer / location combinations
$query = "select * from fst_cust_folders order by project_id, customer";
$result = mysqli_query($con, $query);

//read from query into arrays
while ($rows = mysqli_fetch_assoc($result)) {
	array_push($cust_drive, $rows['folder_id']);
	array_push($cust_folder, $rows['customer']);
	array_push($cust_project, $rows['project_id']);
}

//load in employee designations (quote creator, designer, pl, etc.)
//first init arrays
$users_email = [];	//list of user emails
$users_qc = [];		//quote creator
$users_des = [];	//designers
$users_pl = [];		//Project Owner
$users_pc = [];		//program coordinators
$users_ol = [];		//opts lead

$query = "SELECT * FROM fst_users ORDER BY firstName;";
$result = mysqli_query($con, $query);

//read from query into arrays
while ($rows = mysqli_fetch_assoc($result)) {
	//push email
	array_push($users_email, $rows['email']);

	//look to see who applies to certain designations
	if ($rows['qc'] == "checked")
		array_push($users_qc, $rows['firstName'] . " " . $rows['lastName']);
	if ($rows['des'] == "checked")
		array_push($users_des, $rows['firstName'] . " " . $rows['lastName']);
	if ($rows['pl'] == "checked")
		array_push($users_pl, $rows['firstName'] . " " . $rows['lastName']);
	if ($rows['pc'] == "checked")
		array_push($users_pc, $rows['firstName'] . " " . $rows['lastName']);
	if ($rows['ol'] == "checked")
		array_push($users_ol, $rows['firstName'] . " " . $rows['lastName']);
}

//read in state-market designations
$state_pref = [];		//opts lead

$query = "SELECT * FROM inv_statepref;";
$result = mysqli_query($con, $query);

//read from query into arrays
while ($rows = mysqli_fetch_assoc($result)) {

	//push state and market to temp array
	$temp = array(
		'state' => $rows['stAbv'],
		'market' => $rows['market']
	);

	//push to array
	array_push($state_pref, $temp);
}

//read in all non-legacy quote numbers
$grid = [];

$query = "select quoteNumber, location_name, phaseName, quote_type, address, city, state, zip, customer, sow, sqft from fst_grid WHERE legacy != 'Y' OR legacy is null;";
$result = mysqli_query($con, $query);

//read from query into arrays
while ($rows = mysqli_fetch_assoc($result)) {

	//push to array
	array_push($grid, $rows);
}

//load existing labor rates & travel rates
$labor_rates = [];

$query = "select * from fst_laborrates;";
$result = mysqli_query($con, $query);

//read from query into arrays
while ($rows = mysqli_fetch_assoc($result)) {

	//push to array
	array_push($labor_rates, $rows);
}

$travel_rates = [];

$query = "select * from fst_travelcosts;";
$result = mysqli_query($con, $query);

//read from query into arrays
while ($rows = mysqli_fetch_assoc($result)) {

	//push to array
	array_push($travel_rates, $rows);
}

// get project types
$project_types = [];
$query = "SElECT * FROM general_type;";
$result = mysqli_query($con, $query);

//read from query into arrays
while ($rows = mysqli_fetch_assoc($result)) {
	array_push($project_types, $rows);
}

// get sub types
$sub_types = [];
$query = "SElECT * FROM general_subtype;";
$result = mysqli_query($con, $query);

//read from query into arrays
while ($rows = mysqli_fetch_assoc($result)) {
	array_push($sub_types, $rows);
}

//set open_all info based on user
if ($fstUser['lastName'] == "Borchers" || $fstUser['lastName'] == "Barrows" || $fstUser['lastName'] == "Hart")
	$open_all_info_display = "block";
else
	$open_all_info_display = "none";

?>

<!doctype html>
<html>

<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="google-signin-client_id" content="573761357198-hin7ae7q19qgvoab7t0781b41530546g.apps.googleusercontent.com">
	<link rel="shortcut icon" type="image/x-icon" href="images/P-Logo.png" />
	<link rel="stylesheet" href="stylesheets/element-styles.css?<?= $version; ?>" />
	<link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css'>
	<link href="stylesheets/jquery-ui-themes-1.13.0/themes/ui-lightness/jquery-ui.css" rel="stylesheet">
	<title>New Project Creation (v<?= $version ?>) - Pierson Wireless</title>

	<style>
		/**temporary style for a temporary button (please remove) */
		#open_all_info_button {
			display: <?= $open_all_info_display; ?>
		}

		/**style <divs> to lay in the middle of the page */
		#step1 {
			width: 46em;
		}

		#step2 {
			width: 51em;
		}

		#step3 {
			width: 50em;
		}

		/**class applied to all <divs> (step 1-3) to make info pop-out*/
		.standout_div {
			margin-bottom: 1em;
			margin: 0px auto;
			padding: 3em;
			box-shadow: 8px 8px 20px 5px grey;
			background: #ecf0f3;
			margin-top: 3em;
			border-radius: 15px;
		}

		.lRate_quickstart {
			width: 66px;
		}

		.new_project {
			height: 17px;
		}

		.ui-menu {
			overflow-y: scroll !important;
			max-height: 15em !important;

		}

		.custom-combobox-input {
			background: #BBDFFA !important;
			border-color: #000B51 !important;
			border-width: medium !important;
			font-size: 14px !important;
			font-family: Arial, Helvetica, sans-serif !important;
			padding-left: 3px !important;
			height: 25px !important;
			width: 29.2em !important;
			font-weight: lighter !important;
			color: black !important;
			margin-right: 1px !important;
		}

		.custome-select {
			background-color: #BBDFFA !important;
			height: 10px;
			border: medium solid black;
			font-family: Arial;
			font-size: 15px;
			font-weight: normal !important;
		}

		.ui-button {
			height: 22px;
			margin-left: 1px;
			color: #1c94c4;
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

		select:focus {
			border-width: 3.8px !important;
		}

		.stock {
			text-align: center;
			font-weight: bold;
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

		.custom-select {
			background-color: #BBDFFA;
			border-color: #000B51;
			border-width: medium;
			cursor: pointer;

		}

		.standard_input {
			width: 30em;
			-ms-box-sizing: content-box;
			-moz-box-sizing: content-box;
			box-sizing: content-box;
			-webkit-box-sizing: content-box;
		}

		.standard_textarea {
			width: 30em;
			height: 4em;
			resize: vertical;
			-ms-box-sizing: content-box;
			-moz-box-sizing: content-box;
			box-sizing: content-box;
			-webkit-box-sizing: content-box;
		}

		.standard_select {
			width: 30em;
			-ms-box-sizing: content-box;
			-moz-box-sizing: content-box;
			box-sizing: content-box;
			-webkit-box-sizing: content-box;
		}

		.standard_date {
			width: 30em;
			-ms-box-sizing: content-box;
			-moz-box-sizing: content-box;
			box-sizing: content-box;
			-webkit-box-sizing: content-box;
		}

		/* Style the tab content (and add height:100% for full page content) */
		.tabcontent {
			padding: 50px 20px;
			/* height: 100%; */
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

		.loc_tables th {
			text-align: left;
			padding-right: 0.5em;
		}

		/**overwrite background color of label checkboxes */
		.design_required_label {
			background-color: #f6f6f6 !important;
		}
	</style>
</head>

<body>

	<?php

	//define array of names & Id's to generate headers
	$header_names = ['New Project Set Up'];
	$header_ids = ['new_project'];

	//pass to php function to create navigation bars
	echo create_navigation_bar($header_names, $header_ids, "", $fstUser);

	?>

	<div id='new_project' class='tabcontent'>

		<!Quote Info Header>
			<form action='newProject_handler.php' method='POST'>

				<div id='step1' class='standout_div'>

					<h1>Step 1A: Select / Create Project Location</h1>

					<h2>Option 1: Search for Existing Location</h2>
					<p><strong style='padding-right: 47px'>Location Name </strong><input type='text' class='location standard_input' id='location_name'></p>
					<table id='location_table' class='loc_tables' style='display:none'>
						<tr>
							<th>Location Name</th>
							<td id='location_description'></td>
						</tr>
						<tr>
							<th>Street</th>
							<td id='location_street'></td>
						</tr>
						<tr>
							<th>City, State, Zip</th>
							<td id='location_csz'></td>
						</tr>
						<tr>
							<th>POC Name</th>
							<td id='location_name'></td>
						</tr>
						<tr>
							<th>POC Number</th>
							<td id='location_number'></td>
						</tr>
						<tr>
							<th>POC Email</th>
							<td id='location_email'></td>
						</tr>
						<tr>
							<th>Industry</th>
							<td id='location_industry'></td>
						</tr>
						<tr>
							<td><button onClick='z.next("step1", 1)' form=''>Use This Location?</button></td>
						</tr>
					</table>

					<h2>Option 2: Create New Location</h2>

					<table id='newloc_table' class='loc_tables'>
						<tr>
							<th>*Location Name</th>
							<td><input type='text' id='newloc_description' class='standard_input required_newloc' maxlength='30'> <i>Limit 30 Characters</i></td>
						</tr>
						<tr>
							<th>*Street</th>
							<td><input type='text' id='newloc_street' class='standard_input required_newloc'></td>
						</tr>
						<tr>
							<th>*City, State, Zip</th>
							<td><input type='text' id='newloc_city' class='required_newloc' style='width: 10em'> &nbsp;
								<select type='text' id='newloc_state' style='width: 5em' class='custom-select required_newloc'>
									<option></option>
									<?= create_select_options($states, "", "Abbreviation"); ?>
								</select> &nbsp; <input type='text' id='newloc_zip' style='width: 5em' class='required_newloc'>
							</td>
						</tr>
						<tr>
							<th>POC Name</th>
							<td><input type='text' id='newloc_name' class='standard_input'></td>
						</tr>
						<tr>
							<th>POC Number</th>
							<td><input type='text' id='newloc_number' class='standard_input'></td>
						</tr>
						<tr>
							<th>POC Email</th>
							<td><input type='text' id='newloc_email' class='standard_input'></td>
						</tr>
						<tr>
							<th>*Industry</th>
							<td>
								<select class='custom-select standard_select required_newloc' id='newloc_industry'>

									<option></option>

									<?php

									//grabbing variables from database (takes options saved in table in db (fst_newloc_industry) to drop down options in url)
									$query = "SELECT * FROM fst_newloc_industry";
									$result = mysqli_query($con, $query);

									while ($rows = mysqli_fetch_assoc($result)) {

									?>

										<option value='<?= $rows['newloc_industry']; ?>'><?= $rows['newloc_industry']; ?></option>

									<?php

									}

									?>

								</select>
							</td>
						</tr>
						<tr>
							<td><button onClick='z.next("step1", 2)' form=''>Create New Location?</button></td>
						</tr>
					</table>

				</div>

				<div id='step2' class='standout_div' style='display:none'>

					<h1>Step 1B: Enter Project Information</h1>
					<button onClick='z.next("step2", 1)' form=''>Back to Step 1</button>
					<br><br>

					<table id='project_table' class='loc_tables'>
						<tr>
							<th colspan='2'>
								<h2>Location Info</h2>
							</th>

						</tr>
						<tr>
							<th>Location Name</th>
							<td><input type='text' id='project_location' class='standard_input new_project' maxlength='20' readonly></td>
						</tr>
						<tr>
							<th>Street</th>
							<td><input type='text' id='project_street' class='standard_input new_project' readonly></td>
						</tr>
						<tr>
							<th>City, State, Zip</th>
							<td><input type='text' id='project_city' style='width: 10em' class='new_project' readonly> &nbsp; <input type='text' id='project_state' style='width: 5em' class='new_project' readonly> &nbsp; <input type='text' id='project_zip' style='width: 5em' class='new_project' readonly></td>
						</tr>
						<tr>
							<th>POC Name</th>
							<td><input type='text' id='project_name' class='standard_input new_project' readonly></td>
						</tr>
						<tr>
							<th>POC Number</th>
							<td><input type='text' id='project_number' class='standard_input new_project' readonly></td>
						</tr>
						<tr>
							<th>POC Email</th>
							<td><input type='text' id='project_email' class='standard_input new_project' readonly></td>
						</tr>
						<tr>
							<th>Industry</th>
							<td>
								<input type='text' id='project_industry' class='standard_input new_project' readonly>
							</td>
						</tr>
						<tr>
							<th colspan='2'>
								<h2>Customer Info</h2>
							</th>
						</tr>
						<tr>
							<th>*Customer</th>
							<td>
								<div class="ui-widget">
									<select id="customer-combobox">
										<option></option>
										<?php

										//loop through customers
										for ($i = 0; $i < sizeof($customer); $i++) {

										?>
											<option value="<?= $customer[$i]; ?>"><?= $customer[$i]; ?></option>
										<?php

										}

										?>
									</select>
								</div>
							</td>
						</tr>
						<tr>
							<th>*Customer PM</th>
							<td>
								<div class="ui-widget">
									<select id="customer-pm-combobox">
										<option></option>
									</select>
								</div>
							</td>
						</tr>
						<tr>
							<th>*Customer PM Number</th>
							<td>
								<input type='text' class='standard_input required new_project' id='project_custLead_number'>
							</td>
						</tr>
						<tr>
							<th>Customer PM Email</th>
							<td>
								<input type='text' class='standard_input new_project' id='project_custLead_email'>
							</td>
						</tr>
						<tr>
							<th colspan='2'>
								<h2>Project Info</h2>
							</th>
						</tr>
						<tr>
							<th>Use Duplicate Quote #</th>
							<td>
								<input type='text' id='project_duplicate_target' class='standard_input new_project quotes' onchange='check_duplicate_quote()'>
							</td>
						</tr>
						<tr>
							<th>*Project Description</th>
							<td>
								<input type='text' id='project_description' class='standard_input required new_project' maxlength='30'> <i>Limit 30 Characters</i>
							</td>
						</tr>
						<tr>
							<th>*Preliminary SOW</th>
							<td>
								<textarea id='project_prelim_sow' class='standard_textarea required new_project'></textarea>
							</td>
						</tr>
						<tr>
							<th>Site Walk Date (blank if NA)</th>
							<td>
								<input type='date' id='project_site_walk_date' class='standard_date new_project'>
							</td>
						</tr>
						<tr>
							<th>*Market</th>
							<td>
								<select id='project_market' class='custom-select standard_select required new_project'>
									<option></option>
									<?php
									for ($i = 0; $i < sizeof($market); $i++) {
									?>
										<option><?= $market[$i]; ?></option>

									<?php
									}
									?>
								</select>
							</td>
						</tr>
						<tr>
							<th>*Project Owner</th>
							<td>
								<select id='project_pwLead' class='custom-select standard_select required new_project'>
									<option></option>
									<?php
									for ($i = 0; $i < sizeof($users_pl); $i++) {
									?>
										<option><?= $users_pl[$i]; ?></option>

									<?php
									}
									?>
								</select>
							</td>
						</tr>
						<tr style='visibility: collapse'>
							<th>Estimator</th>
							<td>
								<select id='project_quoteCreator' class='custom-select standard_select new_project disabled' disabled>
									<option></option>
									<?php
									for ($i = 0; $i < sizeof($users_qc); $i++) {
									?>
										<option><?= $users_qc[$i]; ?></option>

									<?php
									}
									?>
								</select>
							</td>
						</tr>
						<tr style='visibility: collapse'>
							<th>Designer</th>
							<td>
								<select id='project_designer' class='custom-select standard_select new_project disabled' disabled>
									<option></option>
									<?php
									for ($i = 0; $i < sizeof($users_des); $i++) {
									?>
										<option><?= $users_des[$i]; ?></option>

									<?php
									}
									?>
									<option>Business Development</option>
								</select>
							</td>
						</tr>
						<tr>
							<th>Program Coordinator</th>
							<td>
								<select id='project_pc' class='custom-select standard_select new_project'>
									<option></option>
									<option>NA</option>
									<?php
									for ($i = 0; $i < sizeof($users_pc); $i++) {
									?>
										<option><?= $users_pc[$i]; ?></option>

									<?php
									}
									?>
								</select>
							</td>
						</tr>
						<tr style='visibility: collapse'>
							<th>Opts Lead</th>
							<td>
								<select id='project_opsLead' class='custom-select standard_select new_project disabled' disabled>
									<option></option>
									<option>NA</option>
									<?php
									for ($i = 0; $i < sizeof($users_ol); $i++) {
									?>
										<option><?= $users_ol[$i]; ?></option>

									<?php
									}
									?>
								</select>
							</td>
						</tr>
						<tr>
							<th>*Project Type</th>
							<td>
								<select class='custom-select standard_select required new_project' id='project_type' onchange='update_sub_type_dropdown_handler(this, "project_sub")'>
									<option></option>
									<?php

									// render select options
									echo create_select_options($project_types, "", "type");

									?>
								</select>
							</td>
						</tr>
						<tr>
							<th>Project Sub-Type</th>
							<td>
								<select class='custom-select standard_select new_project' id='project_sub'>
								</select>
							</td>
						</tr>
						<tr>
							<th>OEM Registration #</th>
							<td>
								<input type='text' id='project_oemNum' class='standard_input new_project'>
							</td>
						</tr>
						<tr>
							<th>Customer Bid # / Site ID</th>
							<td>
								<input type='text' id='project_custNum' class='standard_input new_project'>
							</td>
						</tr>
						<tr>
							<th>(Requested) Designer Due Date</th>
							<td>
								<input type='date' id='project_des_due_date' class='standard_date new_project'>
							</td>
						</tr>
						<tr>
							<th>(Requested) Quote Due Date</th>
							<td>
								<input type='date' id='project_quote_due_date' class='standard_date new_project'>
							</td>
						</tr>
						<tr>
							<th>Request Design Team to</th>
							<td>
								<input id='project_design_required' class='custom-select new_project standard_select' style='display:none' value="No Services Requested">
								<div class="widget">
									<fieldset class='design_required_fieldset' style='border:0'>
										<label for="des_required1" class='design_required_label'>Initial Design</label>
										<input type="checkbox" id="des_required1" class='design_required_checkbox' onclick='update_design_required()'>
										<label for="des_required2" class='design_required_label'>Cost Estimate</label>
										<input type="checkbox" id="des_required2" class='design_required_checkbox' onclick='update_design_required()'>
									</fieldset>
								</div>
								<!-- <input type='checkbox' id = 'project_design_required' class = 'standard_input new_project'> -->
							</td>
						</tr>
						<tr>
							<th>Is this for a budgetary quote?</th>
							<td>
								<select class='custom-select standard_select new_project' id='project_budgetary'>
									<option>No</option>
									<option>Yes</option>
								</select>
							</td>
						</tr>
						<tr style='height: 2em'>
							<td><button onClick='z.next("step2", 2)' form=''>Create New Project</button></td>
						</tr>
						<tr style='height: 2em'>
							<!-- gap for work orders -->
						</tr>
						<tr>
							<th><i>Required for Work Orders</i></th>
						</tr>
						<tr>
							<th>Billing Option</th>
							<td>
								<select id='project_requestType' class='custom-select standard_select required_wo new_project'>
									<option></option>
									<option value='Non-Billable'>Non-Billable</option>
									<option value='Billable'>Billable</option>
								</select>
							</td>
						</tr>
						<tr style='height: 2em'>
							<td><button onClick='z.next("step2", 3)' form=''>Create New Work Order</button></td>
						</tr>
					</table>

				</div>

				<div id='step3' class='standout_div' style='display: none'>
					<h1>Your Project has been successfully created.</h1>
					<p>Project Link: <a id='project-link' href='' target="_blank">LINK</a></p>
					<p>Material Entry Link: <a id='material-link' href='' target="_blank">LINK</a></p>
					<p>Google Drive Link: <a id='gdrive_link' href='' target="_blank"></a></p>
					<button form='' id='open_all_info_button' onclick='open_all_info()'>Start Over</button>
				</div>
	</div>

	<div class='ui-widget' id='add-address' style='display:none' title='Please Update Location Information'>
		<table class='loc_tables' align="center" border="0px" style="line-height:20px;">
			<tr>
				<th>*Street</th>
				<td><input type='text' id='add_street' class='standard_input'></td>
			</tr>
			<tr>
				<th>*City, State, Zip</th>
				<td><input type='text' id='add_city' style='width: 10em'> &nbsp;
					<select type='text' id='add_state' style='width: 5em' class='custom-select'>
						<option></option>
						<?= create_select_options($states, "", "Abbreviation"); ?>
					</select> &nbsp;
					<input type='text' id='add_zip' style='width: 5em'>
				</td>
			</tr>
			<tr>
				<th>POC Name</th>
				<td><input type='text' id='add_name' class='standard_input'></td>
			</tr>
			<tr>
				<th>POC Number</th>
				<td><input type='text' id='add_number' class='standard_input'></td>
			</tr>
			<tr>
				<th>POC Email</th>
				<td><input type='text' id='add_email' class='standard_input'></td>
			</tr>
			<tr>
			<tr>
				<th>*Industry</th>
				<td><select class='custom-select standard_select' id='add_industry'>
						<option></option>
						<option>Healthcare</option>
						<option>Education</option>
						<option>Industrial</option>
						<option>Commerical Real Estate</option>
						<option>Other</option>
					</select>
				</td>
			</tr>
			<tr>
				<td><button onclick='add_address_handler()'>Save Info</button></td>
			</tr>
		</table>
	</div>

	<div class='ui-widget' id='new-customer' style='display:none' title='Add New Customer'>
		<table class='loc_tables' align="center" border="0px" style="line-height:20px;">
			<tr>
				<th>*Customer Name</th>
				<td><input type='text' id='new_customer_name' style='width: 20em;' class='new_customer_required'></td>
			</tr>
			<tr>
				<th id='type_info'>*Customer Type</th>
				<td><select class='custom-select new_customer_required' id='new_customer_type' style='width: 20em;'>
						<option></option>

						<?php

						//init customer types
						$cust_types = [];

						//query for customer types
						$query = "SELECT * FROM general_customer_type;";
						$result = mysqli_query($con, $query);

						while ($rows = mysqli_fetch_assoc($result)) {

							$temp = array(
								'type' => $rows['type'],
								'description' => $rows['description']
							);

							//grab descriptions to pass to script
							array_push($cust_types, $temp);

						?>

							<option value='<?= $rows['type'] ?>'><?= $rows['type']; ?></option>

						<?php

						}

						?>

					</select>
				</td>
			</tr>
			<tr style='height:1.5em;'>
				<th colspan='2'>Customer Address <i>(Not necessarily site address)</i></th>
			</tr>
			<tr>
				<th>Street</th>
				<td><input type='text' id='new_customer_street' style='width: 20em;'></td>
			</tr>
			<tr>
				<th>City, State, Zip</th>
				<td><input type='text' id='new_customer_city' class='' style='width: 9.1em'> &nbsp;
					<select type='text' id='new_customer_state' style='width: 4em' class='custom-select '>
						<option></option>
						<?= create_select_options($states, "", "Abbreviation"); ?>
					</select> &nbsp; <input type='text' id='new_customer_zip' style='width: 98px' class=''>
				</td>
			</tr>
			<tr>
				<th>*Customer Contact Number</th>
				<td><input type='text' id='new_customer_number' style='width: 20em;' class='new_customer_required'></td>
			</tr>
			<tr>
				<th>*Invoice Email Address</th>
				<td><input type='text' id='new_customer_email' style='width: 20em;' class='new_customer_required'></td>
			</tr>
			<tr>
				<td colspan="2" style='width: 28em;'>
					<i>New customers will be setup as taxable until we receive their Tax Exemption letter. When you receive this letter please email it to ____@piersonwireless.com</i>
				</td>
			</tr>
			<tr>
				<td style='padding-top: 1em;'><button onclick='z.create_customer()'>Create New Customer</button></td>
			</tr>
		</table>
	</div>

	<div id="duplicate-dialog" style="display:none" Title="Select rates & services.">

		<h2><input type='checkbox' class='large-checkbox' id='quickstart_laborRates' checked>Include Labor Rates?</h2>
		<fieldset style="width: 260px">
			<legend><b>Labor Rates</b></legend>
			<table>
				<tr>
					<td> <label for='quickstart_quickstart_instRate'>Installer</label></td>
					<td> <input class='lRate_quickstart' type='number' id='quickstart_instRate' disabled></td>
				</tr>

				<tr>
					<td> <label for='quickstart_supRate'>Supervisor</label></td>
					<td> <input class='lRate_quickstart' type='number' id='quickstart_supRate' disabled></td>
				</tr>

				<tr>
					<td> <label for='quickstart_engRate'>Engineer</label></td>
					<td> <input class='lRate_quickstart' type='number' id='quickstart_engRate' disabled></td>
				</tr>

				<tr>
					<td> <label for='quickstart_desRate'>Designer</label></td>
					<td> <input class='lRate_quickstart' type='number' id='quickstart_desRate' disabled></td>
				</tr>
				<tr>
					<td> <label for='quickstart_projCRate'>Project Coordinator</label></td>
					<td> <input class='lRate_quickstart' type='number' id='quickstart_projCRate' disabled></td>
				</tr>
			</table>

		</fieldset>

		<h2><input type='checkbox' class='large-checkbox' id='quickstart_travelRates' checked>Include Travel Rates?</h2>
		<fieldset style="width: 260px">
			<legend><b>Travel Rates</b></legend>
			<table>
				<tr>
					<td> <label for='quickstart_airfare'>Round-Trip Airfare</label></td>
					<td> <input class='lRate_quickstart' type='number' id='quickstart_airfare' disabled></td>
				</tr>

				<tr>
					<td> <label for='quickstart_lodging'>Lodging per Day</label></td>
					<td> <input class='lRate_quickstart' type='number' id='quickstart_lodging' disabled></td>
				</tr>

				<tr>
					<td> <label for='quickstart_food'>Food per day</label></td>
					<td> <input class='lRate_quickstart' type='number' id='quickstart_food' disabled></td>
				</tr>

				<tr>
					<td> <label for='quickstart_grdRental'>Grd/Rental per day </label></td>
					<td> <input class='lRate_quickstart' type='number' id='quickstart_grdRental' disabled></td>
				</tr>
			</table>
		</fieldset>

		<h2><input type='checkbox' class='large-checkbox' id='quickstart_travelRates' checked>Use Previous BOM Pricing?</h2>

		<br>

		<button onclick='z.create_folders("PM")'>Create New Project</button>

	</div>

	<!-- externally defined js files -->
	<!-- used for ajax call -->
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
	<script src="https://apis.google.com/js/platform.js?onload=init" async defer></script>

	<!-- used for jquery -->
	<script src="https://code.jquery.com/jquery-1.12.4.js"></script>
	<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>

	<!-- internally defined js files -->
	<script src="javascript/js_helper.js?<?= $version ?>-2"></script>
	<script src="javascript/utils.js"></script>

	<script>
		//Namespace
		var z = {}

		//load user
		const user = "<?= $_SESSION['firstName'] . ' ' . $_SESSION['lastName']; ?>";
		const user_info = <?= json_encode($fstUser); ?>;

		//initialze location index (to be used throughout js)
		var loc_index = -1;
		var cust_index = -1;

		//new will be used to determine what kind of project this is
		var new_project = null;

		var locations = <?= json_encode($locations); ?>,
			customer = <?= json_encode($customer); ?>,
			cust_id = <?= json_encode($cust_id); ?>,
			cust_folder_id = <?= json_encode($cust_drive); ?>,
			cust_home_id = <?= json_encode($cust_folder); ?>,
			cust_parent_id = <?= json_encode($cust_project); ?>,
			cust_contact = <?= json_encode($cust_contact); ?>,
			cust_pl = <?= json_encode($cust_pl); ?>,
			cust_phone = <?= json_encode($cust_phone); ?>,
			cust_email = <?= json_encode($cust_email); ?>,
			state_pref = <?= json_encode($state_pref); ?>,
			cust_types = <?= json_encode($cust_types); ?>;

		//preset variables
		const existing = <?= $existing; ?>,
			duplicate = <?= $duplicate; ?>,
			qn = "<?= $quote; ?>",
			init_values = <?= json_encode($init_values); ?>,
			grid = <?= json_encode($grid); ?>,
			labor_rates = <?= json_encode($labor_rates); ?>,
			travel_rates = <?= json_encode($travel_rates); ?>,
			project_types = <?= json_encode($project_types); ?>,
			sub_types = <?= json_encode($sub_types); ?>;

		//pass URL to JS
		const sub_url = '<?= $sub_link ?>';

		//set options to locations array
		var options_location = {
			source: locations.map(a => a.description),
			minLength: 2
		};

		//choose selector (input with location as class)
		var selector_location = 'input.location';

		//on keydown, show autocomplete after 2 characters
		$(document).on('keydown.autocomplete', selector_location, function() {
			$(this).autocomplete(options_location);
		});

		//set options to locations array
		var options_quotes = {
			source: grid.map(a => a.quoteNumber),
			minLength: 2
		};

		//choose selector (input with location as class)
		var selector_quotes = 'input.quotes';

		//on keydown, show autocomplete after 2 characters
		$(document).on('keydown.autocomplete', selector_quotes, function() {
			$(this).autocomplete(options_quotes);
		});

		// Add special type of listeners to autoselect menu for location name
		$('#location_name').on('keyup change', function() {
			z.search_handler(this);
		});

		$('#location_name').on('autocompleteselect', function(e, ui) {
			e.currentTarget.value = ui.item.value;
			z.search_handler(e.currentTarget);
		});

		//set options to locations array
		var options_customer = {
			source: customer,
			minLength: 2
		};

		//choose selector (input with location as class)
		var selector_customer = 'input.customer';

		//on keydown, show autocomplete after 2 characters
		$(document).on('keydown.autocomplete', selector_customer, function() {
			$(this).autocomplete(options_customer);
		});

		//set options to locations array
		var options_customer = {
			source: customer,
			minLength: 0
		};

		//choose selector (input with location as class)
		var selector_customer = 'input.customer2';

		//on keydown, show autocomplete after 2 characters
		$(document).on('focus.autocomplete', selector_customer, function() {
			$(this).autocomplete(options_customer);
		});

		$(document).keypress(
			function(event) {
				if (event.which == '13') {
					event.preventDefault();
				}
			});

		//handles identifying customers for a given location and updating the clock in code
		//event can be 'location' (tells we need to update customer drop down) or 'clock_in' else it is just null
		function vp_input_handler(event = null) {

			//check for locaiton event
			if (event == "location") {

				//check size of location name (if over 30 provide error)
				if (u.eid("vp_location").value.length >= 30) {
					u.eid("count_error").style.display = "block";
				} else {
					u.eid("count_error").style.display = "none";
				}


			}

			//only update clock in description if event is not "clock_in"
			if (event != "clock_in") {

				//update clock in description
				var vp_location = u.eid("vp_location").value,
					vp_description = u.eid("vp_description").value,
					vp_clockIn = "yyr-xxxxzz " + vp_location + " " + vp_description;

				//update clock in value and length value
				u.eid("vp_clockIn").value = vp_clockIn;
				u.eid("vp_count").innerHTML = vp_clockIn.length;
			} else {
				//update length value
				u.eid("vp_count").innerHTML = u.eid("vp_clockIn").value.length;

			}

			//update background color of length span based on length
			if (u.eid("vp_clockIn").value.length <= 40) {
				u.eid("vp_count").style.background = "#77ff8f";
			} else if (u.eid("vp_clockIn").value.length > 40 && u.eid("vp_clockIn").value.length <= 50) {
				u.eid("vp_count").style.background = "#f1ff87";
			} else {
				u.eid("vp_count").style.background = "#f15c5c";
			}
		}

		//handles change of location input field
		z.search_handler = function(loc) {

			//look for a match
			loc_index = locations.findIndex(object => {
				return object.description == loc.value;
			});

			//update create new locatin at the same time
			u.eid("newloc_description").value = loc.value.substr(0, 30);

			//if -1 (no match), if not (show table and read in values)
			if (loc_index == -1) {
				u.eid("location_table").style.display = "none";

				//close add-address table (if showing)
				$("#add-address").dialog('close');

			} else {
				//set new values, display table
				u.eid("location_description").innerHTML = locations[loc_index].description;
				u.eid("location_street").innerHTML = locations[loc_index].street;
				u.eid("location_csz").innerHTML = locations[loc_index].city + ", " + locations[loc_index].state + " " + locations[loc_index].zip;
				u.eid("location_name").innerHTML = locations[loc_index].poc_name;
				u.eid("location_number").innerHTML = locations[loc_index].poc_number;
				u.eid("location_email").innerHTML = locations[loc_index].poc_email;
				u.eid("location_industry").innerHTML = locations[loc_index].industry;
				u.eid("location_table").style.display = "block";

			}
		}

		//handles ajax request to create new customer
		z.create_customer = function() {

			//make sure all input fields are filled in
			//set return value to true (if we run across any errors, flip to false)
			var error = false;

			//check to make sure required fields are filled in
			document.querySelectorAll('.new_customer_required').forEach(function(a) {
				if (a.value == "") {
					//highlight yellow if required field is not filled 
					a.classList.add("required_error");

					//set error to true
					error = true;
				} else {
					//return to normal color
					a.classList.remove("required_error");
				}
			})

			//check error value
			if (error) {
				alert("Please make sure all required fields are filled out.");
				return;
			}

			//grab new customer and customer type
			var new_customer = u.eid("new_customer_name").value.trim(),
				new_customer_type = u.eid("new_customer_type").value,
				new_customer_number = u.eid("new_customer_number").value,
				new_customer_email = u.eid("new_customer_email").value,
				new_customer_street = u.eid("new_customer_street").value,
				new_customer_city = u.eid("new_customer_city").value,
				new_customer_state = u.eid("new_customer_state").value,
				new_customer_zip = u.eid("new_customer_zip").value;


			//send to ajax to save values
			//add to form data
			var fd = new FormData();

			//pass any $_POST variables through the form
			fd.append('customer', new_customer)
			fd.append('customer_type', new_customer_type);
			fd.append('customer_number', new_customer_number);
			fd.append('customer_email', new_customer_email);
			fd.append('customer_street', new_customer_street);
			fd.append('customer_city', new_customer_city);
			fd.append('customer_state', new_customer_state);
			fd.append('customer_zip', new_customer_zip);
			fd.append('user_email', user_info.email)

			//set tell
			fd.append('tell', 'new_customer');

			//send request to ajax to create folder / save info to database
			$.ajax({
				url: 'newProject_helper.php',
				type: 'POST',
				processData: false,
				contentType: false,
				data: fd,
				success: function(response) {

					//check for error
					var check = response.substr(0, 2);

					//should say ID
					if (check == "ID") {

						//grab everthing after the 2nd letter
						var id = response.substr(3);
						id = parseInt(id);

						//push customer & id to arrays in case we decide to reenter customer info
						customer.push(new_customer);
						cust_id.push(id);

						//set cust index to length of arrays - 1 (last position)
						cust_index = customer.length - 1;

						//render customer dropdown list & render customer pl list
						z.render_cust_input();
						z.render_cust_pl(new_customer);

						//realign customer name to what the user entered
						u.eid("project_customer").value = new_customer;

						//alert successful addition
						alert("The customer has been successfully created.");

						//close dialog
						$("#new-customer").dialog('close');

					} else {
						alert("ERROR: Please screenshot and send to fst@piersonwireless.com. Official error: " + response);
					}
				}
			});
		}

		//hold global for new contact
		//if false, need to add customer
		//if true, use existing
		var contact_tell = false;

		//handles searching for customer pl and filling in name and number
		z.cust_pl_search = function(pl) {
			//grab current customer
			var customer = u.eid("project_customer").value

			//search through customer pl, break and list phone and email once we find a match
			for (var i = 0; i < cust_pl.length; i++) {
				if (pl == cust_pl[i] && customer == cust_contact[i])
					break;
			}

			//check to see if we found a match
			//if we do not, it will equal the length of the array
			if (i == cust_pl.length) {
				u.eid("project_custLead_number").value = "";
				u.eid("project_custLead_email").value = "";
				contact_tell = false;
			}
			//write to user
			else {
				u.eid("project_custLead_number").value = cust_phone[i];
				u.eid("project_custLead_email").value = cust_email[i];
				contact_tell = true;

				//select next field
				u.eid("project_description").select();
			}
		}

		//use to search through customers
		z.cust_search_handler = function(targ_customer) {

			//reset customer PM info
			u.eid("project_custLead").value = "";
			u.eid("project_custLead_number").value = "";
			u.eid("project_custLead_email").value = "";

			//look for a match
			cust_index = z.find_customer(targ_customer);

			//check cust_index
			if (cust_index == -1) {
				//let user know that this customer does not exist and ask if they would like to assign a new customer ID

				$("#new-customer").dialog({
					width: "auto",
					height: "auto",

				});

				var target = $("#project_customer");
				$("#new-customer").dialog("widget").position({
					my: 'left',
					at: 'right',
					of: target
				});

				//set customer name as current customer in new dialog
				u.eid("new_customer_name").value = targ_customer;

				/*
				
				//if no, allow them to reenter the customer, if yes, create customer
				//create message
				var message = "This customer does not currently exist in our database. Would you like to create a new customer?\n\n";
				message += "[OK] Create '" + targ_customer + "' as a Customer.\n";
				message += "[Cancel] Re-enter Customer.";

				//send message to user
				if (confirm(message)){
					//call new customer creation with customer name
					z.create_customer(targ_customer);
					z.render_cust_pl(targ_customer);
				}
				*/
			} else {
				//update field to match case
				u.eid("project_customer").value = customer[cust_index];

				//update customer pl autocomplete
				z.render_cust_pl(customer[cust_index]);
			}
		}

		//renders customer Project Owner autoselect.
		z.render_cust_pl = function(customer) {

			//remove all options from select list
			document.querySelectorAll('.cust_pl_option').forEach(function(a) {
				a.remove();
			})

			//grab select list
			var select = u.eid("customer-pm-combobox");

			//search through customer pl arrays and grab contracts for given customer
			for (var i = 0; i < cust_pl.length; i++) {
				if (customer.toLowerCase() == cust_contact[i].toLowerCase()) {
					var opt = document.createElement('option');
					opt.value = cust_pl[i];
					opt.innerHTML = cust_pl[i];
					opt.classList.add("cust_pl_option");
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
							.addClass("custom-combobox-input ui-widget ui-widget-content ui-state-default ui-corner-left required new_project")
							.attr("id", "project_custLead")
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

						z.cust_pl_search(this.input.val());

					},
				});

				$("#customer-pm-combobox").combobox();
			});

		}

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
						.addClass("custom-combobox-input ui-widget ui-widget-content ui-state-default ui-corner-left required new_project")
						.attr("id", "project_customer")
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

					//hit customer search handler
					z.cust_search_handler(this.input.val());
				},
			});

			$("#customer-combobox").combobox();
		});

		//renders customer Project Owner autoselect.
		z.render_cust_input = function() {

			//set options to locations array
			var options_customer = {
				source: customer,
				minLength: 2
			};

			//choose selector (input with location as class)
			var selector_customer = 'input.customer';

			//on keydown, show autocomplete after 2 characters
			$(document).on('keydown.autocomplete', selector_customer, function() {
				$(this).autocomplete(options_customer);
			});

		}

		//finds customer, returns index
		z.find_customer = function(targ) {
			for (var i = 0; i < customer.length; i++) {
				if (targ.toLowerCase() == customer[i].toLowerCase()) {
					return i;
				}
			}
			return -1;
		}

		//handles adding customer from add-customer dialog
		function add_address_handler() {

			//add to local variables
			var street = u.eid("add_street").value,
				city = u.eid("add_city").value,
				state = u.eid("add_state").value,
				zip = u.eid("add_zip").value,
				industry = u.eid("add_industry").value,
				name = u.eid("add_name").value,
				number = u.eid("add_number").value,
				email = u.eid("add_email").value;

			//check to make sure fields are filled out
			if (street == "" || city == "" || state == "" || zip == "" || industry == "") {
				alert('Please fill out all required fields.');
				return;
			}

			//send to ajax to save values
			//add to form data
			var fd = new FormData();

			fd.append('location_id', locations[loc_index].id)
			fd.append('street', street);
			fd.append('city', city);
			fd.append('state', state);
			fd.append('zip', zip);
			fd.append('industry', industry);
			fd.append('name', name);
			fd.append('number', number);
			fd.append('email', email);

			//set tell
			fd.append('tell', 'add-address');

			//send info to ajax, set up handler for response
			$.ajax({
				url: 'newProject_helper.php',
				type: 'POST',
				processData: false,
				contentType: false,
				data: fd,
				success: function(response) {

					//if reponse has anything, then tell user, if not, then lets save values to global and move forward.
					if (response != "") {
						alert(response);
						console.log(response);
					}
					//if not, let the user know that we received an error
					else {
						//transfer to global arrays
						locations[loc_index].street = street;
						locations[loc_index].city = city;
						locations[loc_index].state = state;
						locations[loc_index].zip = zip;

						//close add-address table
						$("#add-address").dialog('close');

						//transfer location to step 2 and proceed
						z.transfer_loc(1);
						u.eid("project_location").value = locations[loc_index].description;
						u.eid("project_street").value = street;
						u.eid("project_city").value = city;
						u.eid("project_state").value = state;
						u.eid("project_zip").value = zip;
						u.eid("project_name").value = name;
						u.eid("project_number").value = number;
						u.eid("project_email").value = email;
						u.eid("project_industry").value = industry;

					}

				}
			});





		}

		//global to hold drive link from server
		var drive_link = null;

		//global that holds boolean value to call output handle if needed
		var new_project_bool = null;

		//moves from one step to the next
		z.next = function(step, opt) {
			var error = z.check(step, opt);

			//will need to be removed, have this in place during testing
			error = false;

			if (!error) {

				//used to check if address has been entered
				var index = -1;

				if (step == 'step1') {
					if (opt == 2) {
						index = z.checkLoc(); //check entered address to see if we have a match

						if (index == -1) {
							//check to make sure that we have necessary info filled out
							if (class_checker('required_newloc'))
								return;

							//transfer location values if we pass check
							z.transfer_loc(opt);

						} else {
							//let user know we found another address that matches
							//let the user make a decision, if they press okay, move to step 2, if not, use location that was found
							//create message
							var message = "We found a location that matches your address:\n\n";
							message += locations[index].description + "\n";
							message += locations[index].street + "\n";
							message += locations[index].city + ", " + locations[index].state + " " + locations[index].zip + "\n\n";
							message += "Would you like to use this location?\n";
							message += "[OK] Use " + locations[index].description + ".\n";
							message += "[Cancel] Use " + u.eid("newloc_description").value + ".";

							//send message to user
							if (confirm(message)) {
								//move found project to option 1, display relevant info
								var project = u.eid("location_name");
								project.value = locations[index].description;
								z.search_handler(project);
							} else {
								//transfer location values
								z.transfer_loc(opt);
							}
						}
					} else {

						//check to see if the address has been entered for customer
						if (locations[loc_index].street == "" || locations[loc_index].street == null) {
							$("#add-address").dialog({
								width: "auto",
								height: "auto",

							});

							var target = $("#location_name");
							$("#add-address").dialog("widget").position({
								my: 'left',
								at: 'right',
								of: target
							});

							return;
						}

						//transfer location values
						z.transfer_loc(opt);

					}

				}

				//Second page of creating project
				if (step == 'step2') {

					//go back to step 1
					if (opt == 1) {
						//hide step 2, show step 1
						u.eid("step2").style.display = "none";
						u.eid("step1").style.display = "block";
					}

					//create new project
					else if (opt == 2) {
						//check to see if we have duplicate quote (if so prompt dialog)
						/*if (u.eid("project_duplicate_target").value != ""){
							//show dialog
							$( "#duplicate-dialog" ).dialog({
								width: "auto",
								height: "auto",
								dialogClass: "fixedDialog",
							});
						}
						//skip to creating project							
						else*/
						z.create_folders("PM");

					}

					//create new work order
					else if (opt == 3) {
						//hide step 2, show step 3
						z.create_folders("SM");

					}


				}

			}


		}

		//handles checking a class for errors, highlights yellow, and returns boolean value (false if anything is blank, true if nothing is blank)
		function class_checker(targ_class) {

			//init error value
			var error = false;

			//loop through all required class fields, highlight yellow any required fields that are not filled and return false
			document.querySelectorAll('.' + targ_class).forEach(function(a) {
				if (a.value == "") {
					//highlight yellow
					a.classList.add("required_error");

					//set error to true
					error = true;
				} else {
					//return to normal color
					a.classList.remove("required_error");
				}
			})

			//if error is true, send error message
			if (error)
				alert('Please fill out the fields highlighted in yellow.')

			//return error value
			return error;

		}

		//handle google drive folder and project creation
		//param 1 = (PM / SM) -> type of project to create
		z.create_folders = function(type) {

			//run checks to see if all required fields are filled out
			var error = z.last_check(type);

			//if we return an error, exit the function
			if (error)
				return;

			//if type is SM, default is design required? to no servies
			if (type == "SM")
				u.eid("project_design_required").value = "No Services Requested";

			//enable all disabled cells (will cause errors if disabled)
			document.querySelectorAll('.disabled').forEach(function(a) {
				a.disabled = false;
			})

			//update estimator to user IF design is not needed
			if (u.eid("project_design_required").value == "No Services Requested")
				u.eid("project_quoteCreator").value = user;

			//mark new_project as true
			new_project_bool = true;

			//initalize form data (will carry all form data over to server side)
			var fd = new FormData();

			//create new naming convention
			var new_name = z.create_name(u.eid("project_description").value);

			//add to form 
			fd.append('new_name', new_name);

			//loop through "new_project" and add to form data with ID as identifier
			document.querySelectorAll('.new_project').forEach(function(a) {
				fd.append(a.id, a.value);
			})

			//init other variables needed
			var location_folder_id = "NA",
				location_id = "NA",
				customer_folder_id = "NA",
				customer_id = cust_id[cust_index];

			//make decisions based on other information entered
			if (cust_index != -1) {
				customer_id = cust_id[cust_index];
			} else {
				customer_id = "new";
			}

			//initialize variables used
			var previous_phase = 0;

			//check for existing folder id's, project id's, etc.
			if (!new_project) {
				//read in current parent folders and id's				
				location_folder_id = locations[loc_index].folder_id;
				location_id = locations[loc_index].id;


				//check for customer/location combo
				if (cust_index != -1)
					customer_folder_id = z.check_customer(cust_id[cust_index]);

			}
			//check if this is PM (we can ignore SM)
			else if (type == "PM") {

				//set up prompt for previous phase
				var previous_prompt = prompt("Please enter the previous phase for this location. (If this is the first phase, enter 0)");

				//turn to int (if possible)
				previous_prompt = parseInt(previous_prompt);

				//test to see if what the user entered is a number
				var number_test = Number.isInteger(previous_prompt);

				//check if this is a number (blank will return NAN)
				if (!number_test) {
					alert('Please enter a valid number.');
					return;
				}
				if (previous_prompt >= 99) {
					alert('Please enter a smaller phase #. (If this is 99 or more, start over at 0)');
					return;
				}

				//hand to previous_phase
				previous_phase = previous_prompt;

			}

			//add to form data
			fd.append('customer_id', customer_id);
			fd.append('location_folder_id', location_folder_id);
			fd.append('location_id', location_id);
			fd.append('customer_folder_id', customer_folder_id);
			fd.append('contact_tell', contact_tell);
			fd.append('previous_phase', previous_phase);

			//add type (PM / SM)
			fd.append('type', type);
			fd.append('user_info', JSON.stringify(user_info));

			//send info to ajax, set up handler for response
			$.ajax({
				url: 'newProject_create.php',
				type: 'POST',
				processData: false,
				contentType: false,
				data: fd,
				success: function(response) {
					console.log(response);
					drive_link = response;
				}
			});

		}

		//handles checking all necessary required fields
		//param 1 = (PM / SM)
		z.last_check = function(type) {

			//set return value to true (if we run across any errors, flip to false)
			var error = false;

			//loop through all required class fields, highlight yellow any required fields that are not filled and return false
			document.querySelectorAll('.required').forEach(function(a) {
				if (a.value == "") {
					//highlight yellow
					a.classList.add("required_error");

					//set error to true
					error = true;
				} else {
					//return to normal color
					a.classList.remove("required_error");
				}
			})

			//if type is WO, check for WO required fields
			if (type == "SM") {
				document.querySelectorAll('.required_wo').forEach(function(a) {
					if (a.value == "") {
						//highlight yellow
						a.classList.add("required_error");

						//set error to true
						error = true;
					} else {
						//return to normal color
						a.classList.remove("required_error");
					}
				})
			}

			//if error is true, return error message and kick back to user
			if (cust_index == -1) {
				alert("Please re-enter the customer name AND fill out any missing information identified.");
				u.eid("project_customer").classList.add("required_error");
				u.eid("project_customer").value = "";
				error = true;
				return error;
			} else if (error) {
				alert("Please fill out any missing information identified.");
				u.eid("project_customer").classList.remove("required_error");
				return error;
			}

			//if user has entered a duplicate quote target, check to make sure the types match
			if (u.eid("project_duplicate_target").value != "" && u.eid("project_duplicate_target").value != null) {
				//check index in grid
				var grid_index = grid.findIndex(object => {
					return object.quoteNumber == u.eid("project_duplicate_target").value;
				});

				//if grid[].type != type selected, alert user.
				if (grid[grid_index].quote_type != type) {
					alert("The quote you are trying to duplicate from is " + grid[grid_index].quote_type + ". You are trying to create a " + type + " project. Please select a new quote or remove the target duplicate quote.");
					return true;
				}
			}

			return error;
		}

		//creates folder name sequence
		z.create_name = function(name) {
			return name;

		}

		//check to see if folder has already been created for customer under this location
		z.check_customer = function(customer) {

			for (var i = 0; i < cust_parent_id.length; i++) {
				if (cust_parent_id[i] == locations[loc_index].id)
					break;
			}

			while (cust_parent_id[i] == locations[loc_index].id) {
				if (cust_home_id[i] == customer)
					return cust_folder_id[i];

				i++;
			}

			//did not find a match
			return "NA";
		}

		//checks form to make sure correct values are filled out
		z.check = function(step, opt) {

			var error = false;
			var id_array = [];
			opt = 3;

			//checks step 1, option 2 = Create new location
			if (step == "step1" && opt == 2) {

				//assign id array related to this check
				id_array = ['description', 'street', 'city', 'state', 'zip', 'name', 'number', 'email', 'industry'];

				for (var i = 0; i < id_array.length; i++) {
					if (u.eid("newloc_" + id_array[i]).value == "") {
						u.eid("newloc_" + id_array[i]).classList.add("required_error");
						error = true;
					} else {
						u.eid("newloc_" + id_array[i]).classList.remove("required_error");
					}
				}
			}

			//checks step 2, option 2 = Create new project
			if (step == "step2" && opt == 2) {

				//assign id array related to this check
				id_array = ['customer', 'custLead', 'custLead_number', 'custLead_email', 'description', 'market', 'region', 'pwLead', 'designer', 'pc', 'type', 'sub'];

				for (var i = 0; i < id_array.length; i++) {
					if (u.eid("project_" + id_array[i]).value == "") {
						u.eid("project_" + id_array[i]).classList.add("required_error");
						error = true;
					} else {
						u.eid("project_" + id_array[i]).classList.remove("required_error");
					}
				}
			}

			if (error == true)
				alert("There is some incomplete information. Please check the fields highlighted in Yellow and make sure that they have been filled out.");

			return error;

		}

		//checks to see if address already exists
		z.checkLoc = function() {
			var targ = u.eid("newloc_street").value.trim().toLowerCase();
			for (var i = 0; i < locations.length; i++) {
				if (locations[i].street == "" || locations[i].street == null) {
					//do nothing
				} else if (targ.toLowerCase() == locations[i].street.toLowerCase()) {
					return i;
				}
			}
			return -1;
		}

		//transfer entered into to next screen
		z.transfer_loc = function(opt) {
			//if opt = 1, use existing location
			if (opt == 1) {
				//transfer values
				u.eid("project_location").value = locations[loc_index].description;
				u.eid("project_street").value = locations[loc_index].street;
				u.eid("project_city").value = locations[loc_index].city;
				u.eid("project_state").value = locations[loc_index].state;
				u.eid("project_zip").value = locations[loc_index].zip;
				u.eid("project_name").value = locations[loc_index].poc_name;
				u.eid("project_number").value = locations[loc_index].poc_number;
				u.eid("project_email").value = locations[loc_index].poc_email;
				u.eid("project_industry").value = locations[loc_index].industry;

				//new_project = false (using existing project)
				new_project = false;
			}
			//if opt = 2, use new info
			else if (opt == 2) {
				//transfer values
				u.eid("project_location").value = u.eid("newloc_description").value;
				u.eid("project_street").value = u.eid("newloc_street").value;
				u.eid("project_city").value = u.eid("newloc_city").value;
				u.eid("project_state").value = u.eid("newloc_state").value;
				u.eid("project_zip").value = u.eid("newloc_zip").value;
				u.eid("project_name").value = u.eid("newloc_name").value;
				u.eid("project_number").value = u.eid("newloc_number").value;
				u.eid("project_email").value = u.eid("newloc_email").value;
				u.eid("project_industry").value = u.eid("newloc_industry").value;

				//new_project = true (create new project)
				new_project = true;
			}

			//depending on state, update market
			for (var i = 0; i < state_pref.length; i++) {
				if (u.eid("project_state").value == state_pref[i].state) {
					u.eid("project_market").value = state_pref[i].market;
					break;
				}
			}

			//hide step 1, show step 2
			u.eid("step1").style.display = "none";
			u.eid("step2").style.display = "block";



		}

		/**@author Alex Borchers
		 * Handles passing information to update_sub_type_dropdown (found in javascript/js_helper.js)
		 * @param targ {HTML Entity} <select> element that is changed
		 * @param sub_id {string} the ID that we want to update
		 * @return void
		 */
		function update_sub_type_dropdown_handler(targ, sub_id) {
			update_sub_type_dropdown(targ, sub_id, sub_types);
		}

		z.output_handle = function() {
			//split variable into drive link and quote #
			var carrot_index = drive_link.indexOf("^");

			var drive = drive_link.substr(0, carrot_index);
			var quote = drive_link.substr(carrot_index + 1);

			//set links
			u.eid("gdrive_link").href = "https://drive.google.com/drive/folders/" + drive;
			u.eid("gdrive_link").innerHTML = "https://drive.google.com/drive/folders/" + drive;

			u.eid("project-link").href = sub_url + "application.php?quote=" + quote;
			u.eid("project-link").innerHTML = sub_url + "application.php?quote=" + quote;

			//material entry link
			u.eid("material-link").href = sub_url + "materialEntry.php?quote=" + quote;
			u.eid("material-link").innerHTML = sub_url + "materialEntry.php?quote=" + quote;

			//show step 3
			u.eid("step2").style.display = "none";
			u.eid("step3").style.display = "block";

			//reset new_project_bool
			new_project_bool = false

		}

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

		//handles adjusting certain inputs to use existing quote info
		function use_existing() {

			//find location name based on location id (name may have changed)
			var grid_index = grid.findIndex(object => {
				return object.quoteNumber == qn;
			});

			//get location description from locatoins object
			var index = locations.findIndex(object => {
				return object.id == grid[grid_index].location_id;
			});

			//read in all applicable values
			if (index != -1)
				u.eid("location_name").value = locations[index].description;
			else
				u.eid("location_name").value = init_values.location_name;

			u.eid("project_customer").value = init_values.customer;
			u.eid("project_market").value = init_values.market;
			u.eid("project_pwLead").value = init_values.projectLead;
			u.eid("location_name").value = init_values.location_name;
			u.eid("project_oemNum").value = init_values.oemNum;
			u.eid("project_custNum").value = init_values.custID;

			//run any functions needed
			z.search_handler(u.eid("location_name"));
			z.cust_search_handler(init_values.customer);

			//set cust lead
			u.eid("project_custLead").value = init_values.customer_pm;

			//get phone and email
			z.cust_pl_search(u.eid("project_custLead").value);

		}

		//handles checking if this quotes exists in our system (could potentially update to add high level info about quote)
		function check_duplicate_quote() {

			//get quote number
			var quote = u.eid("project_duplicate_target").value;

			//check for existance in fst_grid
			var grid_index = grid.findIndex(object => {
				return object.quoteNumber == quote;
			});

			//if no match, return error and empty out field
			if (grid_index == -1) {
				alert("The quote numbered entered does not match a quote in our system.");
				u.eid("project_duplicate_target").value = "";
				return;
			}

			//set preliminary sow
			u.eid("project_prelim_sow").value = highlight_potential_locations_for_change(grid[grid_index].sow, grid[grid_index])

			//resize sow text area
			$("#project_prelim_sow").each(function() {
				this.style.height = (this.scrollHeight) + 'px';
			});
		}

		//handles checking an existing SOW for places that need to be changed (ex. address, location name, etc.)
		//param 1 = scope of work that we need to check
		//param 2 = fst_grid entry (whatever quote is referenced in previous function)
		function highlight_potential_locations_for_change(sow, grid) {

			//if sow is blank or null, return nothing
			if (sow == "" || sow == null)
				return "";

			//init array of area's that we would like to look for
			var check = ['location_name', 'phaseName', 'customer', 'address', 'city', 'state', 'zip', 'address_full', 'sqft'];

			//create any custom fields defined in grid
			grid['address_full'] = grid['address'] + ", " + grid['city'] + ", " + grid['state'] + " " + grid['zip'];

			//search SOW for matches (if we find any, surround in brackets)
			for (var i = 0; i < check.length; i++) {

				//check for index
				var pos = sow.indexOf(grid[check[i]]);

				//if we find a match, try to replace
				if (pos > -1 && (grid[check[i]] != null && grid[check[i]] != "")) {

					//replace instance with check[] entry 
					sow = sow.replaceAll(grid[check[i]], "[" + check[i] + "]");
				}

			}

			return sow;
		}

		//handles passing radio box value to input being used to save data into database
		//if you would like to see how this works, remove "display:none" from project_design_required
		function update_design_required() {

			//compare values to see what needed to be written to 'project_design_required'
			var design = u.eid("des_required1").checked,
				estimate = u.eid("des_required2").checked;

			//if both false, nothing required
			if (!design && !estimate) {
				u.eid("project_design_required").value = "No Services Requested";
			}
			//if design but not estimate, then initial design only
			else if (design && !estimate) {
				u.eid("project_design_required").value = "Initial Design";
			}
			//if estimate but not design, then cost estimate only
			else if (estimate && !design) {
				u.eid("project_design_required").value = "Initial Estimation";
			}
			//otherwise, both are true, set to both
			else {
				u.eid("project_design_required").value = "Initial Design & Estimation";
			}
		}

		//windows onload
		window.onload = function() {
			u.eid("defaultOpen").click();

			//render customer pl
			z.render_cust_pl(u.eid("project_customer").value);

			//if existing it true, update all values applicable
			if (existing)
				use_existing();

			//if duplicate is true, we want to duplicate an existing quote with new info
			if (duplicate) {

				//update duplicate target and set to readonly
				u.eid("project_duplicate_target").value = qn;
				u.eid("project_duplicate_target").readOnly = true;
				check_duplicate_quote();

				//adjust sow height
				u.eid("step2").style.display = "block";
				$("#project_prelim_sow").each(function() {
					this.style.height = (this.scrollHeight) + 'px';
				});
				u.eid("step2").style.display = "none";

			}

			//render checkbox for design required as jquery elements
			$(function() {
				$(".design_required_checkbox").checkboxradio();
				$(".design_required_fieldset").controlgroup();
			});

		}

		$(document).ajaxStart(function() {
			waiting('on');
		});

		$(document).ajaxStop(function() {
			waiting('off');

			//if were creating a new project, call the output_handle
			if (new_project_bool) {
				z.output_handle();
			}

		});

		function open_all_info() {
			u.eid("step1").style.display = "block";
			u.eid("step2").style.display = "none";
			u.eid("step3").style.display = "none";
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