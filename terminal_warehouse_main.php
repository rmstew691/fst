<?php

session_start();

//grab basic info about the quote
$shop = $_GET["shop"];

//if test, turn into actual
if ($shop == "OMA-Test")
	$shop = "OMA";
elseif($shop == "CHA-Test")
	$shop = "CHA";

//used to grab actual link for the current address
$actual_link = "https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
$host = "https://$_SERVER[HTTP_HOST]";

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
$query = "SELECT * FROM fst_users where email = '".$_SESSION['email']."'";
$result = $mysqli->query($query);

if ($result->num_rows > 0){
	$fstUser = mysqli_fetch_array($result);
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

//reset error message
$_SESSION['errorMessage'] = "";

//initialize allocations_mo object to be passes to js
$allocations_mo = [];

//load in any material orders open for this shop
$query = "select * from fst_allocations_mo where ship_from = '" . $shop . "' order by closed asc, date_created asc;";
$result = mysqli_query($con, $query);

//cycle thorugh query and assign to different arrays
//add entry for each mo 
while($rows = mysqli_fetch_assoc($result)){
	array_push($allocations_mo, $rows);
}

//build query to get inventory dynamically
$query = "SELECT partNumber, partCategory, partDescription, manufacturer, uom, status";

//DESCRIBE invreport shows the columns in invreport. Use this to grab all stock locations
$describe_invreport = "DESCRIBE invreport;";
$result = mysqli_query($con, $describe_invreport);

while($rows = mysqli_fetch_assoc($result)){
	
	//only add fields with stock 
	if (str_contains($rows['Field'], "-")){
		$query .= ", `" . $rows['Field'] . "`";
	}
}

//finish query
$query .= " FROM invreport;";
$result = mysqli_query($con, $query);

//initialize arrays to be passed to js
$inventory = [];

//cycle thorugh query and assign to different arrays
//add entry for each mo 
while($rows = mysqli_fetch_assoc($result)){
	array_push($inventory, $rows);
}

//user emails
$emails = [];
$directory = [];
$query = "select firstName, lastName, email from fst_users WHERE status = 'Active' order by email;";
$result = mysqli_query($con, $query);

while($rows = mysqli_fetch_assoc($result)){
	array_push($emails, $rows['email']);
	array_push($directory, $rows['firstName'] . " " . $rows['lastName']);
}

//grab attachments taken from 
$whh = [];
$query = "SELECT id, detail FROM fst_allocations_warehouse WHERE type = 'WHH';";
$result = mysqli_query($con, $query);

while($rows = mysqli_fetch_assoc($result)){	
	array_push($whh, $rows);
}

//init arrays
$pq_detail = [];

//grabs detail (actual parts requested)
$query = "SELECT a.quoteNumber, a.project_id AS vp_number, b.* FROM fst_pq_overview a
			LEFT JOIN fst_pq_detail b 
				ON a.id = b.project_id
			WHERE b.status IN('Shipped', 'Staged', 'In-Transit', 'Ship Requested') OR b.decision LIKE '" . $shop . "%' OR b.shop_staged LIKE '" . $shop . "%';";
$result =  mysqli_query($con, $query);

while($rows = mysqli_fetch_assoc($result)){
	array_push($pq_detail, $rows);
}

//grab physical locations stored for each part/shop & quantities
$physical_locations = [];
$query = "SELECT shop, partNumber, location, quantity, prime FROM invreport_physical_locations ORDER BY partNumber, prime DESC;";
$result =  mysqli_query($con, $query);

while($rows = mysqli_fetch_assoc($result)){
	array_push($physical_locations, $rows);
}

//grab current reel assignments
$reel_assignments = [];
$query = "select * from inv_reel_assignments order by partNumber, cast(substr(id, 3) as unsigned) asc;";
$result =  mysqli_query($con, $query);

while($rows = mysqli_fetch_assoc($result)){
	array_push($reel_assignments, $rows);
}

//grab current reel requests
$reel_requests = [];
$query = "select a.*, b.shop, b.bulk, b.location from inv_reel_requests a
			LEFT JOIN inv_reel_assignments b
				ON a.reel_id = b.id;";
$result =  mysqli_query($con, $query);

while($rows = mysqli_fetch_assoc($result)){
	array_push($reel_requests, $rows);
}

//grab quote # and project #
//quote # must be awarded or must be for a FSE/Truck/Warehouse job
$grid = [];
$query = "select quoteNumber, CONCAT(location_name, ' ', phaseName) as pname, vpProjectNumber, quoteStatus from fst_grid 
			WHERE quoteStatus LIKE 'Award%' OR (quoteNumber LIKE '%999999%');";
$result =  mysqli_query($con, $query);

while($rows = mysqli_fetch_assoc($result)){
	array_push($grid, $rows);
}

//get reel categories
$reel_categories = [];
$query = "SELECT * FROM inv_reel_categories;";
$result =  mysqli_query($con, $query);

while($rows = mysqli_fetch_assoc($result)){
	array_push($reel_categories, $rows['category']);
}

// get staging options for warehouse
$staging_options = [];
$query = "SELECT location_name FROM inv_staging_areas WHERE shop = '" . $shop . "';";
$execute =  mysqli_query($con, $query);
while($rows = mysqli_fetch_assoc($execute)){
	array_push($staging_options, $rows['location_name']);
}

// grab info about staging areas
$staging_areas = [];
$query = "SELECT * FROM inv_staging_areas WHERE shop = '" . $shop . "';";
$result = mysqli_query($con, $query);	//in constants.php
while($rows = mysqli_fetch_assoc($result)){

	// Filter current projects staged in this area
	$related_projects = array_filter(
		$allocations_mo, 
		function ($obj) use ($rows){
			return $obj['staged_loc'] == $rows['location_name'] && ($obj['status'] == "Shipping Later" || $obj['status'] == "Staged");
		}
	);

	// Convert object to seperated string of project IDs
	$related_projects = join(", ", array_column($related_projects, 'project_id'));

	// Surround in () if we found something
	if (strlen($related_projects) > 0)
		$related_projects = "(" . $related_projects . ")";

	// Add to $rows
	$rows['related_projects'] = $related_projects;
	array_push($staging_areas, $rows);
}

// get ship requests
$ship_requests = [];
$query = "SELECT a.*, CONCAT(b.vpProjectNumber, ' ', b.location_name) AS 'project_name' 
			FROM fst_pq_ship_request a
			LEFT JOIN fst_grid b
				ON a.quoteNumber = b.quoteNumber
			WHERE a.id IN (SELECT ship_request_id FROM fst_pq_detail WHERE ship_request_id IS NOT NULL AND shop_staged = '" . $shop . "' GROUP BY ship_request_id)
			AND a.status IN ('Open', 'In Progress');";
$result =  mysqli_query($con, $query);
while($rows = mysqli_fetch_assoc($result)){
	array_push($ship_requests, $rows);
}

?>

<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="google-signin-client_id" content="573761357198-hin7ae7q19qgvoab7t0781b41530546g.apps.googleusercontent.com">
<link rel="shortcut icon" type="image/x-icon" href="images/P-Logo.png" />
<link href = "stylesheets/jquery-ui-themes-1.13.0/themes/ui-lightness/jquery-ui.css?<?= $version; ?>" rel = "stylesheet">
<title>Warehouse Terminal (v<?= $version ?>) - Pierson Wireless</title>
<link rel="stylesheet" href="stylesheets/element-styles.css?<?= $version; ?>" />
<link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css'> 	
	
<style>

	.staging_location_label{
		background: white;
		color: black;
	}
	.staging_location_label:hover, .staging_location_label:focus{
		border: 1px solid blue;
		color: blue;
	}
	.small_label{
		font-size:12px;
	}

	/**add spacing in between tables */
	.table_gap{
		width:1em;
	}

	/**styles related to adjust container menu */
	.adjust_pallent_checkbox{
		text-align: center;
		cursor: pointer;
	}
	.adjust_pallent_checkbox input{
		-ms-transform: scale(1.7);
		-webkit-transform: scale(1.7);
		transform: scale(1.7);
		cursor: pointer;
	}

	/**styles specific to shipping summary table */
	.shipping_summary_table{
		margin-bottom: 1em;
	}
	.shipping_summary_table td{
		padding: 2px;
	}

	/**add styles to stacked buttons so they look similar */
	#shipping_close_button{
		width: 9em;
	}

	/**style iframe for shipping lables */
	#shipping_label_iframe{
		width: 99%;
		height: 600px;
		margin-top: 1em;
	}

	/**style wrap divs for queues */
	.pq-wrap{
		clear:both;
		float:left;
	}

	/**format all inputs for warehouse the same width */
	.wh_inputs{
		width: 14em;
	}

	/**style span holding job # */
	.job_span{
		margin-left: 2.4em;
		margin-top: 2em;
		float: left;
		font-weight: bold;
		font-style: italic;
	}

	/**used to style submit receiving button in receiving module */
	.submit_receiving_button{
		margin-left: 2.1em;
    	margin-bottom: 2em;
	}

	/**used to style table made availabe in collapsable shipment info on receiving tab */
	.shipping_parts_table{
		margin: 4.6em 2em;
	}
	.shipping_parts_table td{
		padding: 7px;
	}
	.shipping_parts_header td{
		font-weight: bold;
		border: 0px solid #000000;
		text-align: center;
	}

	/**style additional info tables (appear when expanding + icon in main dashboard) */
	.add_info_table{
		padding:1em;
		float:left;
	}
	.add_info_table td{
		border: 0 !important;
	}
	.add_info_table tbody{
		border: 0 !important;
		margin-bottom:0px !important;
		width: auto !important;
	}
	
	/**style expanded container info sections */
	.container_detail_table{
		padding:1em;
		margin: 1em 1em 2em 2em;
		float:left;
		border-collapse: collapse;
	}
	.container_detail_table td{
		border: 1px solid black;
		padding: 4px;
	}
	.container_detail_header td{
		font-weight: bold;
		border: none;
		text-align: center;
	}
	.container_detail_table tr{
		line-height:2em !important;
	}
	.container_detail_table tbody{
		border: 0 !important;
		margin-bottom:0px !important;
		width: auto !important;
	}

	/**style temp_inv_row on hover */
	#inventory_main_table tbody tr:hover, #receiving_main_table tbody tr:hover{
		background-color: #a7c2ee;
	}

	/**determine whether this class should be shown or hidden depnding on access level */
	.show_for_admin{
		<?php
		
		if ($fstUser['allocations_admin'] != "checked" && $fstUser['accessLevel'] != "Admin")
			echo "display:none";
		
		?>
	}

	/**give some space for the arrows when filtering inventory */
	#filter_by_arrow{
		margin-left: 0.5em;
		font-size: 20px;
	}

	/**force header rows on inventory sheet to be sticky */
	.sticky-header-wh1, .sticky-header-wh1-receiving{
		position: sticky;
		top: 46px;
		z-index: 100;
		background: white;
	}
	.sticky-header-wh1 th{
		cursor: pointer;
	}
	.sticky-header-wh2{
		position: sticky;
		top: 91px;
		z-index: 100;
		background: white;
	}

	/**style elements within inventory_reels_dialog */
	.reel_id, .reel_quantity, .reel_location{
		width: 5em;
	}

	/**styles bulk reel inputs */
	.bulk_reed_id{
		font-weight: bold;
	} 

	/**style elements inside of inventory adjustment div */
	.adjustment_physical_location, .adjustment_on_hand{
		width: 6em;
	}

	/**style input fields on inventory table */
	.shop{
		width:5em;
	}
	.partNumber{
		width:15em;
	}
	.allocated{
		width: 5em;
	}
	.overstock_location, .primary_location{
		/* cursor: pointer; */
		width: 6em;
	}
	.last_activity{
		width: 5.4em;
		cursor: pointer;
	}
	.lastCount{
		width: 5.4em;
	}
	.wh_container{
		width: 6em;
	}

	/**adjust style of divs inside of dialog boxes */
	.div_adjustment{
		margin-left:1em;
		margin-top: 1em;
	}

	.complete-row input[type='text']{
		color: red;
	}
	.complete-row input[type='number']{
		color: red;
	}

	.hide_text_area{
		width: 222px;
		background: white !important;
		border: none !important;
		font-size: 16px;
		font-family: Arial;
		min-height:40px;
		resize: unset;
		overflow: hidden;
		color: black;
	}
	
	#pq-parts-table{
		padding-bottom: 2em;
	}
	
	.large_button{
		font-size: 20px !important;
		width: 14em;
		height: 2em;
		text-align: center;
	}
	.part_id{
		width: 15em;
	}
	.description{
		width: 20em;
	}
	.uom{
		width: 5em;
	}
	.q_allocated{
		width: 75px;
	}
	.uom{
		width: 5em;
	}
	.decision, .split_decision{
		width: 10em;
	}
	.on_hand, .stock{
		width: 5em;
	}
	.location{
		width: 5em;
	}
	.container{
		width: 10em;
	}
	.wh_notes{
		height: 25.85px;
		width: 300px;
		resize: horizontal;
	}
	
	.whh-inputs, .remove_whh{
		cursor: pointer;
		
	}
	
	.ui-menu { width: 150px; }

	input:focus:not([type=submit]), textarea:focus, select:focus{
		border-width: 3.8px !important;
	}

	.wait {cursor: wait;}	

	input:read-only, textarea {
		background-color:#C8C8C8;
	}

	input:read-write, textarea {
		background-color:#BBDFFA;
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

	#shoppingCart-table td{
		text-align: center;
	}

	#mo_information{
		border-collapse: collapse;
		display: inline-block;
		vertical-align: middle;
		padding-bottom: 10em;
		
	}

	#mo_information tr{
		line-height: 0px;
	}

	/* Style the tab content (and add height:100% for full page content) */
	.tabcontent {
	  padding: 0px 20px;
	  height: 100%;
	  float: left;
	}

	/* Style customer inputs*/
	.custom-input-header{
		width: 13.2em;

	}

	.basic-table{
		display: inline-block;
		padding-bottom: 5px;
	}

	.basic-table td{
		padding-right: 5px;
	}

	.ui-widget{
		padding-bottom: 10px;
	}

	.price, .cost, .last_cost{
		width: 7em;
	}

	.remove:hover{
		cursor: pointer;
	}

	.stock_greensheet{
		text-align: center;
		font-weight: bold;
	}

	#partSubs td{
		border: 1px solid #000000;
		text-align: center;
	}

	#partSubs{
		border-collapse: collapse;
	}
    .toggle-wrap, .shape {
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
	.mo_head{
		font-weight: bold;
		padding-right: 5px;
	}
	.overnight_style{
		background-color: #FF6366;
	}
	.urgent_style{
		background-color: #FFFA7C;
	}
	.standard_style{
		background-color: #49FF58;
	}
	.closed_style{
		background-color: #767676;
	}
	.greensheet_style{
		background-color: #F98739;
	}
	.closed-page{
		visibility: collapse;
	}
	.required-greensheet{
		width: 20em;
	}
	.custom-files{
		width:14em;
	}
	.date_required{
		font-size: 14px;
	}
	.mo_info_header{
		font-size: 25px;
		padding-bottom: 1em;
	}
	#title_div{
		width: 100%;
		display: flex;
		justify-content: center;
		text-align: center;
	
	}
	.ui-state-active{
		background-color: #007fff !important;
		color: white !important;
	}
	.list-item:hover{
		background-color: #BBE4FF;
		cursor: pointer;
	}
	.list-item{
		font-weight: bold !important;
		color: black !important;
	}
	.reject_td{
		font-weight: bold;
		padding: 7px 20px 7px 0px;
	}
	#reject_reason{
		height: 31px !important;
    	width: 238px;
	}


</style>
	</head>
	
<body>

<?php

	//define array of names & Id's to generate headers
	$header_names = ['Open/Pending', 'Closed', 'Greensheet', 'Inventory', 'Receiving'];
	$header_ids = ['Open', 'Closed', 'Greensheet', 'inventory', 'receiving'];
	$header_redirect = ['', '', '', 'terminal_warehouse_inventory.php?shop=' . $shop, 'terminal_warehouse_receiving.php?shop=' . $shop];

	//pass to php function to create navigation bars
	echo create_navigation_bar($header_names, $header_ids, "check_orders()", $fstUser, 'Open', $header_redirect);

?>

<!--bring in "word" logo for shipping label-->
<img src='images/PW_Word_Logo.jpg' alt='PW Corporate Logo Words' id = 'pw_word_logo' style = 'display:none'>
	
<div style = 'padding: 2em; padding-top: 5em' id = 'header_div'>
	<button onclick = 'check_orders()'>Save and Refresh</button><br><br>
	Sort By: 
	<select onchange = 'refresh_queues()' id = 'sort_by' class = 'custom-select'>
		<option></option>
		<option value = '0'>MO #</option>
		<option value = '1'>Project #</option>
		<option value = '2'>Priority</option>
		<option value = '3' selected>Time Received</option>
	</select>
</div>
		
<div id = 'Open' class ='tabcontent'>

	<div class="pq-wrap" id = 'pq-sel-div' style = 'padding-right: 2em'>
	<h3>Open/Pending Orders:</h3>
		<div id = 'pq-list' class="pq" style = 'padding-bottom: 5em; float: left;'>
			<label for="pq-default" class = 'list-item' style = 'display:none' id = 'pq-label-default'></label>
			<input type="radio" name="pq" id="pq-default" onclick = 'show_info(this)'>
	
		</div>
	</div>

	<div class="pq-wrap" id = 'pq-sel-div-ship-request'>
		<h3>Ship Requests (coming soon) (<span id = 'request_number'><?= sizeof($ship_requests);?></span>): <button onclick = 'toggle_ship_requests(this)'>-</button></h3>
			<div id = 'sr-list-ship-request' class="pq" style = 'padding-bottom: 5em; float: left;'>
				
			</div>
	</div>

	<div class="pq-wrap" id = 'pq-sel-div-later'>
		<h3>Shipping Later (<span id = 'later_number'>0</span>): <button onclick = 'toggle_later(this)'>+</button></h3>
			<div id = 'pq-list-later' class="pq" style = 'padding-bottom: 60em; float: left; display: none'>
				

			</div>
	</div>

</div>
	
<div id = 'reject_dialog' style = 'display:none' title = 'Rejected Part'>
	
	<table>
		<tr>
			<td class = 'reject_td'>Part Number: </td>
			<td><input readonly id = 'reject_part'></td>
		</tr>
		<tr>
			<td class = 'reject_td'>Quantity Rejected: </td>
			<td><input id = 'reject_quantity' placeholder="Enter Quantity" type = 'number' min = '0' style = 'width: 228px;'></td>
		</tr>
		<tr>
			<td class = 'reject_td'>Reason: </td>
			<td>
				<select id = 'reject_reason' class = 'custom-select'>
					<option></option>
					<option>Stock is incorrect</option>
					<option>Unable to locate part</option>
				</select>
			</td>
		</tr>
		<tr>
			<td colspan="2">
				<textarea id = 'reject_notes' placeholder="Enter any applicable notes" style = 'width: 400px; height: 80px; resize:vertical'></textarea>	
			</td>
		</tr>
	</table>
	
	<br>
	
	<button onclick = 'reject_part()'>Reject Part</button>
	
	<span id = 'hold_detail_id' style = 'display:none'></span>
	<span id = 'hold_overview_id' style = 'display:none'></span>
</div>

<div id = 'shipping_dialog' style = 'display:none' title = 'Shipping Labels'>
	
	<h4>Shipping Summary</h4>
	
	<table class = 'shipping_summary_table'>
		<tr>
			<td class = 'mo_head'>Job #: </td>
			<td id = 'shipping_job'></td>
		</tr>
		<tr>
			<td class = 'mo_head'>Contents: </td>
			<td id = 'shipping_contents'></td>
		</tr>
		<tr>
			<td class = 'mo_head'>Tracking: </td>
			<td id = 'shipping_tracking'></td>
		</tr>
		<tr>
			<td class = 'mo_head'>Shipment ID: </td>
			<td id = 'shipping_id'></td>
		</tr>
		<tr>
			<td class = 'mo_head'>Type: </td>
			<td id = 'shipping_type'></td>
		</tr>
	</table>
	
	<button id = 'shipping_close_button' onclick = 'close_shipment()'>Close Shipment</button>
	<input id = 'label_acknowledgement' type = 'checkbox'><label for = 'label_acknowledgement'>Acknowledge that the shipping label has been printed.</label>
	<iframe src="" id = 'shipping_label_iframe' ></iframe>
</div>

<div id = 'adjust_container_dialog' style = 'display:none' title = 'Adjust Container Menu'>
		
	<table id = 'adjust_container_table' class = 'standardTables'>
		<thead>
			<tr>
				<th>Part #</th>

				<?php
				// create container headers on a loop
				for ($i = 1; $i < 11; $i++){

					?>
					<th>Container <?= $i; ?></th>
					<?php

				}
				?>
			</tr>
		</thead>
		<tbody>
			<!-- to be added by adjust_container_menu() -->
		</tbody>
	</table>
</div>

<div id = 'staged_location_dialog' style = 'display:none' title = 'Select Staged Locations'>
	<span class="ui-helper-hidden-accessible"><input type="text"/></span>
	<?php
	$prev_x = 0;
	foreach($staging_areas as $area){
		?>

		<label for="<?= $area['location_name']; ?>" class = 'staging_location_label'>
			<?= $area['location_name'] . "<br><span class = 'small_label'>" . $area['related_projects'] . "</span>"; ?>
		</label>
		<input type="checkbox" id="<?= $area['location_name']; ?>" class = 'staging_location' onchange = 'sync_staging_location()'><br>

		<?php
		/*if ($area['x'] > $prev_x){
			echo "<br>";
		}
		$prev_x = $area['x'];*/
	}
	?>
</div>
	
<div id = 'Closed' style = 'display: none' class ='tabcontent'>
	
	<table>
		<tr>
			<td colspan = "2" style = 'text-align: center'><i>Both dates are required</i></td>
			<td style = 'width: 2em'></td>
			<td colspan = "2" style = 'text-align: center'><i>Leave blank if searching for all</i></td>
		</tr>
		<tr>
			<td>Start Date:</td>
			<td><input type='date' id = 'start_date'></td>
			<td style = 'width: 2em;'></td>
			<td>Project #:</td>
			<td><input type='text' id = 'search_project'></td>
		</tr>	
		<tr>
			<td>End Date:</td>
			<td><input type='date' id = 'end_date'></td>
			<td style = 'width: 2em;'></td>
			<td>MO #:</td>
			<td><input type='text' id = 'search_MO'></td>
		</tr>
		<tr>
			<td><button onclick = 'refresh_queues()'>Search</button></td>
		</tr>
	</table>


	<div class="pq-wrap" id = 'pq-sel-div-closed' style = 'float:left; padding-right: 2em'>
		<h3>Completed Orders:</h3>
			<div id = 'pq-list-closed' class="pq" style = 'padding-bottom: 60em; float: left;'>
				

			</div>
	</div>
	
</div>
	
<div id = 'Greensheet' style ='display: none' class = 'tabcontent'>
	<h3>Greensheet Processing Menu</h3>
	<table>
		<tr>
			<td colspan="2"><i>If the quote # is unknown, please click on the question mark.</i></td>
		</tr>
		<tr>
			<td class = 'mo_head'>Quote #: <span style='font-size:18px;cursor:pointer' id = 'search_for_greensheet'>&#10068;</span></td>
			<td><input type = 'text' id = 'greensheet-quote' class = 'required-greensheet' onchange = 'check_greensheet_quote(this.value)'></td>
		</tr>
		<tr>
			<td class = 'mo_head'>Name: </td>
			<td>
				<select id = 'greensheet-name' class = 'required-greensheet custom-select' style = 'width: 20em'>
					<option></option>
					
					<?php
					
					for ($i = 0; $i < sizeof($emails); $i++){
					
					?>
					
					<option value = '<?= $directory[$i] . "|" . $emails[$i]; ?>'><?=$directory[$i]; ?></option>
					
					<?php
						
					}
					
					?>
					
				</select>
			</td>
		</tr>
		<tr>
			<td class = 'mo_head'>Date Picked Up: </td>
			<td><input type = 'date' id = 'greensheet-date' class = 'required-greensheet'></td>
		</tr>
	
	</table>
	<table class = "fstTable" id = 'greensheet_table' align = "center" border = "0px" style = "line-height:20px;">
		<tr>
			<th colspan = "4"> <h4>Please select parts/quantities that will be taken from the shop.</h4> </th>
		</tr>
		<tr></tr>
		<tr>
			<th style = "width: 400px"> Part Number </th>
			<th style = "width: 100px"> Quantity Requested</th>
			<th style = "width: 100px"> In Stock </th>
		</tr>
		<tr>
			<td> <!Part Number Column>
				<input style = "width: 400px" id = 'extra_pn1' class = 'parts' onchange = 'form_check(1)'/>
			</td>
			<td> <!Quantity Requested Column>
				<input type = "number" value = "" style = "width: 100px" min = '0' id = 'extra_q1' onchange = 'form_check(1)'>
			</td>
			<td id = 'extra_stock1' class = 'stock_greensheet'></td>
		</tr>

	</table>
	<br>
	<button onclick='check_greensheet()'>Submit Greensheet to Allocations</button>
</div>

<div style = 'display: none; white-space: nowrap; padding-top: 2em;' id = 'mo_details'>

	<table id = 'mo_information'>
		<tr class = 'mo_hide'>
			<th colspan="2" class = 'mo_info_header'>Project Data</th>
			<td class = 'table_gap'></td>
			<th colspan="2" class = 'mo_info_header'>Warehouse Inputs</th>
		</tr>
		<tr class = 'mo_hide'>
			<td class = 'mo_head'>Project # / Name: </td>
			<td class = "partRequestTD"><p id = 'project_id'></p></td>
			<!--<td class = "partRequestTD"><p id = 'project_id'></td>!-->

			<td class = 'table_gap'></td>

			<td class = 'mo_head'>*Input Carrier: </td>
			<td class = "partRequestTD" style = 'width: 12em;'><input id = 'input_carrier' class = 'warehouse_disable warehouse_required wh_inputs'></td>
			<td class = "partRequestTD"><span style = 'text-align: left;'><input type = 'checkbox' id = 'input_pickup' class = 'warehouse_disable' onchange = 'local_input(this)'>Local Pickup?</span></td>
		</tr>
		<tr class = 'mo_hide'>
			<td class = 'mo_head' id = 'project_data_id'>Material Order #: </td>
			<td class = "partRequestTD"><p id = 'mo_id'></p></td>

			<td class = 'table_gap'></td>

			<td class = 'mo_head'>*Input Tracking: </td>
			<td class = "partRequestTD"><input id = 'input_tracking' class = 'warehouse_disable warehouse_required wh_inputs'></td>
		</tr>
		<tr class = 'mo_hide'>
			<td class = 'mo_head'>Ship From: </td>
			<td class = "partRequestTD"><p id = 'ship_from'></p></td>

			<td class = 'table_gap'></td>

			<td class = 'mo_head'>*Estimated Receipt Date </td>
			<td class = "partRequestTD"><input type = 'date' id = 'input_receipt' class = 'warehouse_disable warehouse_required wh_inputs'></td>
		</tr>
		<tr class = 'mo_hide'>
			<td class = 'mo_head'>Ship To: </td>
			<td class = "partRequestTD"><p id = 'ship_to'></p></td>

			<td class = 'table_gap'></td>

			<td class = 'mo_head'>*Input Cost: </td>
			<td class = "partRequestTD"><input type = 'number' id = 'input_ship_cost' class = 'warehouse_disable warehouse_required wh_inputs'></td>
		</tr>
		<tr class = 'mo_hide'>
			<td class = 'mo_head'>Street: </td>
			<td class = "partRequestTD"><p id = 'street'></p></td>
			
			<td class = 'table_gap'></td>

			<td class = 'mo_head'>*Picked By (initials): </td>
			<td class = "partRequestTD"><input type = 'text' id = 'input_picked_by' class = 'warehouse_disable warehouse_required wh_inputs'></td>
		</tr>
		<tr class = 'mo_hide'>
			<td class = 'mo_head'>City, State, Zip </td>
			<td class = "partRequestTD"><p id = 'city_state_zip'></p></td>
			
			<td class = 'table_gap'></td>
		
			<td class = 'mo_head'>*Processed By (initials): </td>
			<td class = "partRequestTD"><input type = 'text' id = 'input_processed_by' class = 'warehouse_disable warehouse_required wh_inputs'></td>
		</tr>
		<tr class = 'mo_hide'>
			<td class = 'mo_head'>Due By: </td>
			<td class = "partRequestTD"><p id = 'date_required'></p></td>
			
			<td class = 'table_gap'></td>

			<td class = 'mo_head'>*Double Checked By (initials): </td>
			<td class = "partRequestTD"><input type = 'text' id = 'input_checked_by' class = 'warehouse_disable warehouse_required wh_inputs'></td>
		</tr>
		<tr class = 'mo_hide'>
			<td class = 'mo_head'>Early Delivery Accepted? </td>
			<td class = "partRequestTD"><p id = 'early_delivery'></p></td>
			
			<td class = 'table_gap'></td>

			<td class = 'mo_head'>Add Pictures of Contents: </td>
			<td class = "partRequestTD">
				<div id = 'poc_div'>
					<input class = 'warehouse_disable custom-files' type="file" id="poc-1" onchange = 'next_attachment(1)'>
				</div>		
			</td>
		</tr>
		<tr class = 'mo_hide'>
			<td class = 'mo_head'>Has Reels? </td>
			<td class = "partRequestTD"><p id = 'has_reels'></p></td>
			
			<td class = 'table_gap'></td>
			
			<td class = 'mo_head'>Add BOL: </td>
			<td class = "partRequestTD"><input class = 'warehouse_disable custom-files' type="file" id="bol"></td>
		</tr>
		<tr id = 'WHH_row' class = 'mo_hide'>
			<td class = 'mo_head'>Notes: </td>
			<td class = "partRequestTD"><textarea class = 'hide_text_area' disabled id = 'notes'></textarea></td>
			
			<td class = 'table_gap'></td>
			
			<td class = 'mo_head'>WHH Attachments </td>
			<td class = "partRequestTD">
				<div id = 'whh_div'>
					<input readonly type = 'attachment' class = 'whh-inputs' >
				</div>		
			</td>
		</tr>
		<tr class = 'mo_hide'>
			<td class = 'mo_head'>Shipping Instructions</td>
			<td><p id = 'shipping_opt' style = 'color:red;font-weight: bold;'></p></td>
			
			<td class = 'table_gap'></td>
			
			<td class = 'mo_head'>Content of Containers </td>
			<td class = "partRequestTD">
				<div id = 'cop_div'>
					<input type = 'text' id = 'cop-1' class = 'warehouse_disable wh_inputs' OnKeyUp = 'next_container(1)' placeholder="Container 1">
				</div>		
			</td>
			
		</tr>
		<tr class = 'mo_hide'>
			<td></td><td></td>
			
			<td class = 'table_gap'></td>
			
			<!-- <td class = 'mo_head clickable' onclick = 'adjust_staging_location()'>Location Staged In: </td> -->
			<td class = 'mo_head' >Location Staged In: </td>
			<td class = "partRequestTD">
				<!-- <input id = 'input_staged_loc' class = 'warehouse_disable wh_inputs'> -->
				<select id = 'input_staged_loc' class = 'warehouse_disable wh_inputs custom-select'>
					<option></option>
					<?= create_select_options($staging_options);?>
				</select>
			</td>
		</tr>
		<tr class = 'mo_hide'>
			<td></td><td></td>
			
			<td class = 'table_gap'></td>
			
			<td class = 'mo_head'>Warehouse Notes: </td>
			<td class = "partRequestTD"><textarea id = 'input_warehouse_notes' class = 'warehouse_disable wh_inputs' style = 'resize: vertical'></textarea></td>
		</tr>
		<tr class = 'mo_hide'>
			<td></td><td></td>
			
			<td class = 'table_gap'></td>
			
			<td class = 'mo_head'>Staging? </td>
			<td class = "partRequestTD"><input type = 'checkbox' id = 'input_staged' class = 'warehouse_disable'></td>
		</tr>
		<tr class = 'mo_hide'>
			<td></td><td></td>
			
			<td class = 'table_gap'></td>
			
			<td colspan='2'>
				<button onclick = 'close_and_submit_handler()' id = 'close_and_submit_button' class = 'warehouse_hide' style = 'margin-top:6px;' form="">Close and Submit Update</button>
				<button onclick = 'prep_shipment()' id = 'prep_shipment_button' style = 'margin-top:6px;' form="">Create Shipment</button> 
			</td>
		</tr>
		<tr style = 'height: 5em' class = 'mo_hide'></tr>
		<tr class = 'mo_hide'>
			<td colspan="5">
				<div style = 'display: inline-block'>
					<div style = 'float: left'><button id = 'pick_ticket_button' onclick = 'export_pdf_handler(true, "pw-pick-ticket")'>Preview Pick Ticket</button></div>
					<div style = 'float: left'><button id = 'download_bom_button' onclick = 'export_complete_bom()'>Download Complete BOM</button></div>
					<!--<div style = 'float: left'><button onclick = 'export_pdf_handler(true, "ws-pick-ticket")'>Print WS Pick Ticket</button></div>!-->
					<div style = 'float: left'><button id = 'attachment1' onclick = show_attachment(1)>Attachment 1</button></div>
					<div style = 'float: left'><button id = 'attachment2' onclick = show_attachment(2)>Attachment 2</button></div>
					<div style = 'float: left'><button id = 'attachment3' onclick = show_attachment(3)>Attachment 3</button></div>
				</div>
				<p id = 'amended_tell' style = 'display:none; color: red; font-size: 25px'>THIS IS AN AMENDED MO</p>
				<p><b>Key:</b> <input readonly style = 'width: 13em;' value = 'Part has not been processed'><input style = 'color: red; width: 15em;' value = 'Part has already been processed' readonly></p>
			</td>
		</tr>
		<tr>
			<td colspan = '7'>
				<table id = 'pq-parts-table'>
					<thead>
						<tr style = 'line-height: 20px;'>
							<th></th>
							<th>Part Number</th>
							<th>Description</th>
							<th>Quantity</th>
							<th>UOM</th>
							<th style = 'width: 4em;'>Shop<br>On-Hand</th>
							<th>Instructions</th>
							<th style = 'width: 4em;'>Physical<br>Location</th>
							<th onclick = 'adjust_container_menu()' class = 'clickable'>Container #</th>
							<th>Notes</th>
						</tr>
					</thead>

					<tbody>
						<!--filled with function add_pr_item()!-->
					</tbody>

				</table>
				<table id = 'sr-parts-table'>
					<thead>
						<tr style = 'line-height: 20px;'>
							<th><!-- placeholder for checkbox to send --></th>
							<th><!-- placeholder for expandable button --></th>
							<th>Container #</th>
							<th style = 'width: 4em;'>Staged<br>Location</th>
						</tr>
					</thead>

					<tbody>
						<!--filled with function add_sr_item()!-->
					</tbody>

				</table>
			</td>
		</tr>
		<tr class = 'mo_hide'>
			<td colspan = '5'>
				<iframe src="" id = 'attachment' width = '100%' height = '800px' ></iframe>
				<iframe id = 'my_iframe' style = 'display:none'></iframe>
				<img style = 'display: none; width: 50em' id = 'picture' src = ''>
			</td>
		</tr>
	</table>
	
</div>	

<div style = 'display: none;' id = 'greensheet_search' title = 'Search for quote #' class = 'div_adjustment'>

	<table class = 'standardTables'>
		<tr>
			<th colspan="2">Enter any of the following:</th>
		</tr>
		<tr>
			<td>Job Name</td>
			<td><input id = 'greensheet_search_project'></td>
		</tr>
		<tr>
			<td>Project #</td>
			<td><input id = 'greensheet_search_num'></td>
		</tr>
	</table>

	<button onclick = 'filter_quotes()'>Search for Quote</button>

	<table class = 'standardTables' id = 'greensheet-search-table'>
		<!-- info generated from filter_quotes() -->
	</table>
	
</div>	

	<!-- internal js files -->
	<script src="javascript/js_helper.js?<?= $version ?>-3"></script>
	<script src = "javascript/utils.js"></script>
	<script src = "javascript/accounting.js"></script>

	<!-- external js libraries -->
	<!-- enable ajax use -->
	<script	src = "https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
	<script src="https://apis.google.com/js/platform.js?onload=init" async defer></script>
	
	<!-- enable jquery use -->
	<script src="https://code.jquery.com/jquery-1.12.4.js"></script>
	<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
	
	<!-- Used to bring in PDF Make (PDF Renderer) !-->
	<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.3.0-beta.1/pdfmake.min.js" integrity="sha512-G332POpNexhCYGoyPfct/0/K1BZc4vHO5XSzRENRML0evYCaRpAUNxFinoIJCZFJlGGnOWJbtMLgEGRtiCJ0Yw==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.3.0-beta.1/standard-fonts/Times.js" integrity="sha512-KSVIiw2otDZjf/c/0OW7x/4Fy4lM7bRBdR7fQnUVUOMUZJfX/bZNrlkCHonnlwq3UlVc43+Z6Md2HeUGa2eMqw==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

	<script>
	
		//global = holds current index
		var current_index = null;
		
		//lets the system know if it is auto-refreshing
		var refreshing = false;
		
		//pulls through all relevant info from php
		var allocations_mo = <?= json_encode($allocations_mo); ?>,		//allocations MO info sent from allocations
			whh = <?= json_encode($whh); ?>,							//warehouse helper attachments
			pq_detail = <?= json_encode($pq_detail); ?>,				//parts requested & designated to terminal
			ship_requests = <?= json_encode($ship_requests); ?>;		//ship request info
		
		//load in inventory & inv_location info
		var inventory = <?= json_encode($inventory); ?>,
			physical_locations = <?= json_encode($physical_locations); ?>, 
			reel_assignments = <?= json_encode($reel_assignments); ?>,
			reel_requests = <?= json_encode($reel_requests); ?>;

		//pull fst_grid table into js
		var grid = <?= json_encode($grid); ?>;
			
		const use_shop = '<?= $shop; ?>',
				host = '<?= $host; ?>';
		var hold_response;
		var user_info = <?= json_encode($fstUser); ?>;
		
		//set options to parts array (relevant for parts request new parts)
		var options = {
			source: inventory.map(object => object.partNumber),
			minLength: 2
		};
		
		//choose selector (input with part as class)
		var selector = 'input.parts';
		
		//on keydown, show autocomplete after 2 characters
		$(document).on('keydown.autocomplete', selector, function() {
			$(this).autocomplete(options);
		});

		//set options for quote # input field
		var quotes = {
			source: grid.map(object => object.quoteNumber),
			minLength: 2
		};
		
		//choose selector (input with part as class)
		var selector = '#greensheet-quote';
		
		//on keydown, show autocomplete after 2 characters
		$(document).on('keydown.autocomplete', selector, function() {
			$(this).autocomplete(quotes);
		});
		
		//sets local pick up inputs
		function local_input(e){
			
			//if checked, disable elements, set to standard values
			if (e.checked){
				u.eid("input_carrier").value = "Local Pick-Up";
				u.eid("input_tracking").value = "N/A";
				u.eid("input_ship_cost").value = "0";
				
			}
			//if not checked, wipe values, enable elements
			else{
				u.eid("input_carrier").value = "";
				u.eid("input_tracking").value = "";
				u.eid("input_ship_cost").value = "";
				
			}
		}
		
		//global counts # of attachments & # of containers
		var num_attachments = 1;
		var num_containers = 1;
		
		//handles created next attachment for pictures of content
		function next_attachment (targ = null){
			
			//grab id from show_info
			var index = $(this).attr('id');
			if (index == undefined)
				index = "poc-" + targ;
			
			//check to see if previous attachment is blank(only create another if we've attached a file)
			if (index == "poc-" + num_attachments){
				
				//increment by 1
				num_attachments++;

				//add input
				var input = document.createElement("input");
				input.type = "file";
				input.id = "poc-" + num_attachments;
				input.addEventListener('click', next_attachment);
				input.classList.add("warehouse_disable");
				input.classList.add("custom-files");
				input.classList.add("reset");
				
				//add br first, then apppend child
				var break_el = document.createElement("BR");
				u.eid("poc_div").appendChild(break_el);
				u.eid("poc_div").appendChild(input);
			}
		}
		
		//handles creating next field for container description
		function next_container(targ = null){
			
			//grab id from show_info
			var index = $(this).attr('id');
			if (index == undefined)
				index = "cop-" + targ;
			
			//check to see if previous attachment is blank(only create another if we've attached a file)
			if (index == "cop-" + num_containers && u.eid(index).value != ""){
				
				//increment by 1
				num_containers++;

				//add input
				var input = document.createElement("input");
				input.type = "text";
				input.id = "cop-" + num_containers;
				input.addEventListener('keyup', next_container);
				input.classList.add("warehouse_disable");
				input.classList.add("reset");
				input.classList.add("wh_inputs");
				input.placeholder = "Container " + num_containers;
				
				//add br first, then apppend child
				var break_el = document.createElement("BR");
				u.eid("cop_div").appendChild(break_el);
				u.eid("cop_div").appendChild(input);
			}
			
		}

		//toggles if we show or hide ship requests
		function toggle_ship_requests(button){
			
			//check value of button (looking for + or -)
			//if plus, we want to show and change to -
			if (button.innerHTML == "+"){
				button.innerHTML = "-";
				u.eid("sr-list-ship-request").style.display = "block";
			}
			//opposite applies for -
			else{
				button.innerHTML = "+";
				u.eid("sr-list-ship-request").style.display = "none";
			}
		}
		
		//toggles if we show or hide shipping later
		function toggle_later(button){
			
			//check value of button (looking for + or -)
			//if plus, we want to show and change to -
			if (button.innerHTML == "+"){
				button.innerHTML = "-";
				u.eid("pq-list-later").style.display = "block";
			}
			//opposite applies for -
			else{
				button.innerHTML = "+";
				u.eid("pq-list-later").style.display = "none";
			}
		}
		
		/**
		 * Handles refreshing all queues on the screen
		 * @author Alex Borchers
		 * @returns void
		 */
		function refresh_queues(){

			// Handles open/pending & shipping later queues
			sort_by();

			// Handles ship request queues
			for (var i = 0; i < ship_requests.length; i++){
				new_ship_request_item(ship_requests[i]);
			}

			//hold current index
			var temp1 = current_index;
			var temp2 = ship_req_id;
			
			//update class of previous selected
			if (current_index != -1){

				//check if element exists
				if (u.eid("pq-label-" + current_index)){
					u.eid("pq-label-" + current_index).classList.add("ui-corner-top");
					u.eid("pq-label-" + current_index).classList.add("ui-checkboxradio-checked");
					u.eid("pq-label-" + current_index).classList.add("ui-state-active");
				}
			}
			else if (ship_req_id != -1){
				//check if element exists
				if (u.eid("sr-label-" + ship_req_id)){
					u.eid("sr-label-" + ship_req_id).classList.add("ui-corner-top");
					u.eid("sr-label-" + ship_req_id).classList.add("ui-checkboxradio-checked");
					u.eid("sr-label-" + ship_req_id).classList.add("ui-state-active");
				}
			}
		}

		//global used to count shipping later
		var later_count = 0;
		
		//handles sorting list of open/pending orders
		//sort can be a number (0-3)
		//0 = MO #
		//1 = Project #
		//2 = Priority
		//3 = Time Received
		function sort_by(){
			//grab sort by value
			var sort = u.eid("sort_by").value;
			
			//reset staged
			later_count = 0;
			
			//first clear the existing list
			document.querySelectorAll('.open-pending').forEach(function(a){
				a.remove()
			})
			//also clear closed
			document.querySelectorAll('.closed').forEach(function(a){
				a.remove()
			})
			//and clear shipping later
			document.querySelectorAll('.later').forEach(function(a){
				a.remove()
			})
			//and clear ship requests
			document.querySelectorAll('.ship_request').forEach(function(a){
				a.remove()
			})
			
			//init array used for comparison
			var target_array = [], new_index = [], index;

			//sort array
			//assign to targ1 & 2 so the for loop can just reference these
			if (sort == 0){
				target_array = allocations_mo.map(a => a.mo_id);
			}
			else if(sort == 1){
				target_array = allocations_mo.map(a => a.project_id);
			}
			else if(sort == 2){
				target_array = allocations_mo.map(a => a.date_required);
			}
			else if(sort == 3){
				//need to create a copy instead of setting the array as is
				Array.prototype.push.apply(target_array, allocations_mo.map(a => a.date_created));
				
				//if sorting by time received, lets go through and set all closed in target array future date so they hit the list last
				for (var i = 0; i < target_array.length; i++){
					if (allocations_mo[i].status == "Closed"){
						//grab year
						var year = target_array[i].substr(0, 4);
						
						//replace year with future date (2999)
						target_array[i] = target_array[i].replace(year, "2999");
					}
				}
			}
						
			//sort target array 1
			//we need the id's sorted most importantly, using method found on SO (https://stackoverflow.com/questions/11499268/sort-two-arrays-the-same-way)
			//1) combine the arrays:
			var list = [];
			for (var j = 0; j < target_array.length; j++) 
				list.push({'sort_value': target_array[j], 'id': j});

			//2) sort:
			list.sort(function(a, b) {
				return ((a.sort_value < b.sort_value) ? -1 : ((a.id < b.id) ? 0 : 1));
				//Sort could be modified to, for example, sort on the age 
				// if the name is the same.
			});

			//3) loop through new indexes and add one by one to list:
			for (var k = 0; k < list.length; k++) {
				//create new list item of index
				new_list_item(list[k].id);
			}			
		}
		
		//handles checking closed entries before adding.
		//targ = index needed to evaluate array
		//opt = 1 or 2 (1 = checks to see if it was closed today, 2 = checks to see if it is within our scope)
		function check_closed(targ, opt){
			
			//if opt = 1 see if it was closed today (return true)
			if (opt == 1){
				//turn date into short hand (YYYY-MM-DD)
				//var short_date = closed[targ].substr(0, 10);
								
				var targ_date = new Date(allocations_mo[targ].closed + ' UTC');
				var today = new Date();

				//grab year, month, day, from both
				var y1 = targ_date.getFullYear(),
					m1 = targ_date.getMonth(),
					d1 = targ_date.getDate(),
					y2 = today.getFullYear(),
					m2 = today.getMonth(),
					d2 = today.getDate();
											
				//compare values
				if (y1 == y2 && m1 == m2 && d1 == d2)
					return true;
				
			}
			//if opt = 2 see if it falls within our scope (return true)
			else if (opt == 2){

				//init boolean value (pass/fail) init as failing
				var pass = false;

				//grab variables needed
				var start_date = u.eid("start_date").value,
					end_date = u.eid("end_date").value, 
					project = u.eid("search_project").value,
					mo_id = u.eid("search_MO").value;

				//check start/end dates, if both not blank, check to see if it falls within users time frame
				if (start_date != "" && end_date != ""){

					if (start_date <= allocations_mo[targ].closed && end_date >= allocations_mo[targ].closed)
						pass = true;

				}
				
				//check for mo and project # (overwrite date scope)
				if (project != "" && allocations_mo[targ].project_id == project)
					pass = true;
				//otherwise, check if project entered is blank, if not set pass to false
				else if (project != "")
					pass = false;
				
				if (mo_id != "" && allocations_mo[targ].mo_id == mo_id)
					pass = true;
				//check if not blank
				else if (mo_id != "")
					pass = false;

				//return value for pass/fail
				return pass;
				
			}
			
			//if we do not meet criteria, return false
			return false;
			
		}
		
		//handle updating tables if we have identified a change
		function new_list_item (index){
			
			//grab div element & set class list to use
			var div = u.eid("pq-list");
			var use_classlist = "open-pending";
			
			//if closed, change div
			if ((allocations_mo[index].status == "Closed" || allocations_mo[index].status == "Received") && !check_closed(index, 1)){
				div = u.eid("pq-list-closed");
				use_classlist = "closed";
				
				//check closed date to see if we should add or not
				if (!check_closed(index, 2))
					return;
				
			}
			else if(allocations_mo[index].status == "Shipping Later"){
				div = u.eid("pq-list-later");
				use_classlist = "later";
				later_count++;
				u.eid("later_number").innerHTML = later_count;
			}
			else if (allocations_mo[index].status == "Staged")
				return;
			
			//add label 
			var label = document.createElement("Label");
			label.setAttribute("for", "pq-" + index);
			label.id = 'pq-label-' + index;
			
			//depending on urgency, add classname
			if (allocations_mo[index].status == "Closed" || allocations_mo[index].status == "Received")
				label.classList.add("closed_style");
			else if (allocations_mo[index].urgency == "[Standard]")
				label.classList.add("standard_style");
			else if (allocations_mo[index].urgency == "[Urgent]")
				label.classList.add("urgent_style");
			else if (allocations_mo[index].urgency == "[Greensheet]")
				label.classList.add("greensheet_style");
			else
				label.classList.add("overnight_style");

			//add class to label so we know to remove it if we resort
			label.classList.add(use_classlist);
			
			//add additional class name
			label.classList.add("list-item");
			
			//adjust project ID (if longer than 18) add to label
			var show_project_id = allocations_mo[index].project_id
			if (allocations_mo[index].project_id.length > 18)
				show_project_id = allocations_mo[index].project_id.substr(0, 15) + "...";

			label.innerHTML += allocations_mo[index].urgency + " P#: " + show_project_id + " | MO #" + allocations_mo[index].mo_id + " | "; 
			
			//create span element with open
			var span = document.createElement("SPAN");
			span.id = 'status' + index;
			
			//text node for status span
			var text = document.createTextNode(allocations_mo[index].status);
			span.appendChild(text);
			
			//add to label
			label.appendChild(span);

			//add the rest of the label text
			label.innerHTML += " | Due: " + format_date(allocations_mo[index].date_required) + " | <i class = 'date_required'>" + utc_to_local(allocations_mo[index].date_created) + "</i>";
			
			//if this is amended, add a big A
			if (allocations_mo[index].amending_reason != "" && allocations_mo[index].amending_reason != null){
				label.innerHTML += "&nbsp;&nbsp;&nbsp;<span style = 'color:red; font-size:22px'>(A)</span>"
			}
			
			//append to parent div
			div.appendChild(label);
			
			//add input
			var input = document.createElement("input");
			input.type = "radio";
			input.setAttribute("name", "pq");
			input.id = "pq-" + index;
			input.addEventListener('click', show_info);
			input.classList.add(use_classlist);
			div.appendChild(input);
			
			//reinitialize toggle menu
			$( ".shape-bar, .pq" ).controlgroup();
			$( ".pq" ).controlgroup( {
			  direction: "vertical"
			} );
			
		}

		/**
		 * Handles adding new ship request item to queues
		 * @author Alex Borchers
		 * @param {object} ship_request (matches row from fst_pq_ship_request)
		 * @returns void
		 */
		function new_ship_request_item (ship_request){
			
			//grab div element & set class list to use
			var div = u.eid("sr-list-ship-request");
			var use_classlist = "ship_request";
			
			//add label 
			var label = document.createElement("Label");
			label.setAttribute("for", "sr-" + ship_request.id);
			label.id = 'sr-label-' + ship_request.id;
			
			// add urgency depending on date requested
			var urgency = get_urgency(ship_request.due_by_date);

			if (urgency == "Standard")
				label.classList.add("standard_style");
			else if (urgency == "Urgent")
				label.classList.add("urgent_style");
			else
				label.classList.add("overnight_style");

			//add class to label so we know to remove it if we resort
			label.classList.add(use_classlist);
			
			//add additional class name
			label.classList.add("list-item");
			label.innerHTML += "[" + urgency + "] P#: " + ship_request.project_name.substr(0, 10) + " | "; 
			
			//create span element with open
			var span = document.createElement("SPAN");
			span.id = 'status' + ship_request.id;
			
			//text node for status span
			var text = document.createTextNode(ship_request.status);
			span.appendChild(text);
			
			//add to label
			label.appendChild(span);

			//add the rest of the label text
			label.innerHTML += " | Due: " + format_date(ship_request.due_by_date) + " | <i class = 'date_required'>" + utc_to_local(ship_request.created) + "</i>";
			
			//append to parent div
			div.appendChild(label);
			
			//add input
			var input = document.createElement("input");
			input.type = "radio";
			input.setAttribute("name", "pq");
			input.id = "sr-" + ship_request.id;
			input.addEventListener('click', show_ship_request_info);
			input.classList.add(use_classlist);
			div.appendChild(input);
			
			//reinitialize toggle menu
			$( ".shape-bar, .pq" ).controlgroup();
			$( ".pq" ).controlgroup( {
			  direction: "vertical"
			} );
			
		}

		/**
		 * Handles getting urgency based on date
		 * @author Alex Borchers
		 * @param {string} check_date
		 * @returns {string} Urgency [Standard, Urgent, Overnight]
		 */
		function get_urgency (check_date) {
			dt2 = new Date(check_date);
			dt1 = new Date();
			var date_diff_indays = Math.floor((Date.UTC(dt2.getFullYear(), dt2.getMonth(), dt2.getDate()) - Date.UTC(dt1.getFullYear(), dt1.getMonth(), dt1.getDate()) ) /(1000 * 60 * 60 * 24));
		
			console.log(date_diff_indays);

			if (date_diff_indays >= 7)
				return "Standard";
			else if (date_diff_indays >= 3)
				return "Urgent";
			else
				return "Overnight";
			
		}
		
		//handles formatting input value time and preparing for time_format
		//parameter is input time string in military(hh:mm:ss) and converts to hh:mm PM/AM
		function init_time (time){

			//get hours and minutes
			var hours = parseInt(time.substr(0, 2));
			var minutes = parseInt(time.substr(3, 5));
			
			return time_format(hours, minutes);
			
		}
		
		//globals used to help make decisions in show info
		var ignore = false, 
			previous_state = true,
			curr_attachment = "";
			
		//shows relevant MO info
		function show_info (targ = null, close_opt = false){

			// close open dialogs
			if (u.eid("adjust_container_dialog").style.display !== "none" && $('#adjust_container_dialog').dialog('isOpen'))
				close_dialog("adjust_container_dialog");
									
			//grab id from show_info
			var index = $(this).attr('id');
			if (index == undefined)
				index = targ.id;
			
			// reset ship_req_id index (used in show_ship_request_info())
			ship_req_id = -1;
			u.eid("project_data_id").innerHTML = "Material Order #:";

			//if default, just ignore
			if (index == "pq-default"){
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
			if (current_index == index){
				u.eid("mo_details").style.display = "none";
				u.eid("pq-default").click();
				//u.eid("pq-label-" + index).classList.remove("");
				current_index = -1;
				return;
			}
									
			//update current index
			current_index = index;
						
			//update values according to index
			u.eid("project_id").innerHTML = allocations_mo[index].project_id + " " + allocations_mo[index].project_name;
			u.eid("mo_id").innerHTML = allocations_mo[index].mo_id;
			u.eid("ship_from").innerHTML = allocations_mo[index].ship_from;
			u.eid("ship_to").innerHTML = allocations_mo[index].ship_to;
			u.eid("date_required").innerHTML = format_date(allocations_mo[index].date_required);
			u.eid("street").innerHTML = allocations_mo[index].street;
			u.eid("city_state_zip").innerHTML = allocations_mo[index].city + ", " + allocations_mo[index].state + " " + allocations_mo[index].zip;
			u.eid("early_delivery").innerHTML = allocations_mo[index].early_delivery;
			u.eid("has_reels").innerHTML = allocations_mo[index].has_reels;
			u.eid("notes").value = allocations_mo[index].notes;
			u.eid("shipping_opt").innerHTML = allocations_mo[index].shipping_opt;
			
			u.eid("notes").style.height = "40px";
			u.eid("notes").style.height =  u.eid("notes").scrollHeight + 10 + "px";
			
			// reset input elements to previously entered
			// check if user is currently in the cell before updating
			var inputs = ["carrier", "tracking", "receipt", "ship_cost", "picked_by", "processed_by", "checked_by", "staged_loc", "warehouse_notes"];
			
			for (var i = 0; i < inputs.length; i++){

				if (u.eid("input_" + inputs[i]) != document.activeElement)
					u.eid("input_" + inputs[i]).value = allocations_mo[index][inputs[i]];
			}

			//set local pick-up
			u.eid("input_pickup").checked = false;
			
			//check value to decide if we want this checked or not
			if (allocations_mo[index].staged_opt == 0)
				u.eid("input_staged").checked = false;
			else
				u.eid("input_staged").checked = true;
				
			//check for amended MO (need to notify user)(only applies to unclosed projects)
			//start by hiding it and only show it under certain conditions
			//same rules apply for our attachments
			u.eid("amended_tell").style.display = "none";
			u.eid("attachment1").innerHTML = "Attachment 1";
			u.eid("attachment2").innerHTML = "Attachment 2";
			u.eid("attachment3").innerHTML = "Attachment 3";
			
			if (!close_opt){
				if (allocations_mo[index].amending_reason != "" && allocations_mo[index].amending_reason != null){
					u.eid("amended_tell").style.display = "block";
					
					//update attachment buttons
					u.eid("attachment1").innerHTML = "Amended Version";
					u.eid("attachment2").innerHTML = "Previous Version";
				}
				
			}
			
			//hide picture
			u.eid("picture").style.display = "none";
			
			//if we have an attachement, show it, if not, hide the iframe
			if (allocations_mo[index].attachment1 != ""){
				u.eid("attachment").style.display = "block";
				//if we have the same attachment currently set, do nothing
				if (curr_attachment != "materialOrders/" + allocations_mo[index].attachment1 && curr_attachment != "materialOrders/" + allocations_mo[index].attachment2 && curr_attachment != "materialOrders/" + allocations_mo[index].attachment3){
					u.eid("attachment").src = "materialOrders/" + allocations_mo[index].attachment1;
					curr_attachment = "materialOrders/" + allocations_mo[index].attachment1;
				}

			}
			else{
				u.eid("attachment").style.display = "none";
			}

			//show/hide attachment buttons based on what is in attachments array
			//attach1
			if (allocations_mo[current_index].attachment1 == "" || allocations_mo[current_index].attachment1 == 'null')
				u.eid("attachment1").style.display = 'none';
			else
				u.eid("attachment1").style.display = 'block';
			
			//attach2
			if (allocations_mo[current_index].attachment2 == "" || allocations_mo[current_index].attachment2 == 'null')
				u.eid("attachment2").style.display = 'none';
			else
				u.eid("attachment2").style.display = 'block';
			
			//attach3
			if (allocations_mo[current_index].attachment3 == "" || allocations_mo[current_index].attachment3 == 'null')
				u.eid("attachment3").style.display = 'none';
			else
				u.eid("attachment3").style.display = 'block';

			// show buttons needed
			u.eid("download_bom_button").style.display = "block";
			u.eid("pick_ticket_button").style.display = "block";
			u.eid("close_and_submit_button").style.display = "block";
			u.eid("prep_shipment_button").style.display = "none";
			
			//show table
			u.eid("mo_details").style.display = 'block';
			
			//check if this has been opened yet, mark as pending if not
			if (!close_opt){
				check_pending(index, 'material_order');
				if (allocations_mo[index].status == "Closed")
					close_opt = true;
			}
			
			//reset color of input fields
			document.querySelectorAll('.warehouse_required').forEach(function(a){
				a.classList.remove("required_error");
			})
			
			//disable/hide necessay info if this is closed vs. if this is open
			secure_table(close_opt);
			
			//if shipping later is the status, disable checkbox
			if (allocations_mo[index].status == "Shipping Later"){
				u.eid("input_staged").checked = false;
				u.eid("input_staged").disabled = true;
			}
			
			//read in warehouse helper (whh) pictures
			show_whh(index);
			
			//show bill of materials
			show_pr_parts();
							
			//hold previous state for next time around
			previous_state = close_opt;
			
		}

		// global to hold current ship_request
		var ship_req_id = -1;

		/**
		 * Handles showing info related to a shipping request
		 * @author Alex Borchers
		 * @param {HTMLElement} targ (button clicked, optional = null)
		 * @returns void
		 */
		function show_ship_request_info(targ = null){

			// close open dialogs
			if (u.eid("adjust_container_dialog").style.display !== "none" && $('#adjust_container_dialog').dialog('isOpen'))
				close_dialog("adjust_container_dialog");
			
			//grab id from show_info
			var id = $(this).attr('id').substring(3);
			if (id == undefined)
				id = targ.id.substring(3);
			
			// reset show_info index (used in show_info())
			current_index = -1;
			u.eid("project_data_id").innerHTML = "Shipping Request ID:";
		
			//check to see if index matches current index (if so, unhighlight blue and hide main area)
			if (ship_req_id == id){
				u.eid("mo_details").style.display = "none";
				u.eid("pq-default").click();
				ship_req_id = -1;
				return;
			}
									
			// update global ship_req_id
			ship_req_id = id;

			// get index of ship_req_id
			var index = ship_requests.findIndex(object => {
				return object.id == id;
			});
						
			// update values according to index
			u.eid("project_id").innerHTML = ship_requests[index].project_name;
			u.eid("mo_id").innerHTML = ship_requests[index].id;
			u.eid("ship_from").innerHTML = ship_requests[index].ship_from;
			u.eid("ship_to").innerHTML = ship_requests[index].ship_location;
			u.eid("street").innerHTML = ship_requests[index].ship_address;
			u.eid("city_state_zip").innerHTML = ship_requests[index].ship_city + ", " + ship_requests[index].ship_state + " " + ship_requests[index].ship_zip;
			u.eid("date_required").innerHTML = format_date(ship_requests[index].due_by_date);
			u.eid("early_delivery").innerHTML = "";
			u.eid("has_reels").innerHTML = "";
			u.eid("notes").value = ship_requests[index].additional_instructions;
			u.eid("shipping_opt").innerHTML = "";

			// adjust notes height
			u.eid("notes").style.height = "40px";
			u.eid("notes").style.height =  u.eid("notes").scrollHeight + 10 + "px";
			
			// reset input elements to previously entered
			// check if user is currently in the cell before updating
			var inputs = ["carrier", "tracking", "receipt", "ship_cost", "picked_by", "processed_by", "checked_by", "staged_loc", "warehouse_notes"];

			for (var i = 0; i < inputs.length; i++){

				if (u.eid("input_" + inputs[i]) != document.activeElement)
					u.eid("input_" + inputs[i]).value = ship_requests[index][inputs[i]];
			}
			
			//set local pick-up
			u.eid("input_pickup").checked = false;
			
			//check value to decide if we want this checked or not
			if (ship_requests[index].staged_opt == 0)
				u.eid("input_staged").checked = false;
			else
				u.eid("input_staged").checked = true;
			
			//show table
			u.eid("mo_details").style.display = 'block';

			//reset color of input fields
			document.querySelectorAll('.warehouse_required').forEach(function(a){
				a.classList.remove("required_error");
			})

			//disable/hide necessay info if this is closed vs. if this is open
			secure_table(false);
			
			// hide attachments not needed
			u.eid("attachment1").style.display = "none";
			u.eid("attachment2").style.display = "none";
			u.eid("attachment3").style.display = "none";
			u.eid("download_bom_button").style.display = "none";
			u.eid("pick_ticket_button").style.display = "none";
			u.eid("close_and_submit_button").style.display = "none";
			u.eid("prep_shipment_button").style.display = "block";
			
			//show bill of materials
			show_sr_parts();

			// Remove WHH related items
			document.querySelectorAll('.whh-inputs, .remove_whh').forEach(function(elt){
				elt.remove();
			});

			// Flip to in progress if pending
			check_pending(ship_req_id, 'ship_request');
		}
		
		//handles showing warehouse helpers based on id
		function show_whh(index){
			
			//delete all previous whh attachments
			document.querySelectorAll('.whh-inputs').forEach(function(a){
				a.remove();
			})

			//delete all previous whh delete spans
			document.querySelectorAll('.remove_whh').forEach(function(a){
				a.remove();
			})
			
			//loop through whh array
			for (var i = 0; i < whh.length; i++){
				if (allocations_mo[index].id == whh[i].id)
					add_whh(i);
			}
		}
		
		//handles adding a row of whh attachments
		function add_whh(index){
			
			//create span first (will hold X and input)
			var span = document.createElement("SPAN");
			span.classList.add('remove_whh'); 
			span.id = 'whh' + index;
			span.style.color = 'red';
			span.innerHTML = "&#10006  ";
			
			//add input
			var input = document.createElement("input");
			input.type = "text";
			input.readOnly = true;
			input.addEventListener('click', show_attachment);
			input.classList.add("whh-inputs");
			input.value = whh[index].detail;

			//add br first, then apppend child
			var break_el = document.createElement("BR");
			break_el.classList.add("whh-inputs");
			u.eid("whh_div").appendChild(span);
			u.eid("whh_div").appendChild(input);
			u.eid("whh_div").appendChild(break_el);
			
		}
		
		//handles send message to user to confirm removing attachment
		$(document).on('click', 'span.remove_whh', function () {
									
			//grab id from 
			var id = this.id.substring(3);
			
			//send message to user as one last check
			var message = "Are you sure you would like to remove this attachment? (This cannot be undone)";

			//send message to user
			if (confirm(message)){
				//if yes, send request to server and remove row attachment from screen and refresh
				remove_whh(id);
			}
			
		});
		
		//handles removing the whh
		function remove_whh(key){
			
			//ajax request to communicate with database
			$.ajax({
				type : "POST",  //type of method
				url  : "terminal_warehouse_helper.php",  //your page
				data : { 
						key : whh[key].id,
						detail : whh[key].detail,
						tell : 'remove_whh'
					   },// passing the values
				success : function (response) {		
					
					//check for error
					if (response != "")
						alert(response);
					else{
						alert("The WHH attachment has been removed.");
						
						//hide attachment pane & remove old attachment
						u.eid("picture").style.display = "none";
					}
					
					//refresh user content
					check_orders();
				}	
			});
		}
		
		//handles securing certain cells so they may not be edited
		function secure_table(closed){
			
			//if closed tab, make sure input fields are disabled and buttons are hidden
			if (closed){
				//disable all elements with class warehouse_disable
				document.querySelectorAll('.warehouse_disable').forEach(function(a){
					a.disabled = true;
				})
				//hide all elements with class warehouse_hide
				document.querySelectorAll('.warehouse_hide').forEach(function(a){
					a.style.display = "none";
				})
				
			}
			//if not, make sure everything is visible and ready to go
			else{
				//enable all elements with class warehouse_disable
				document.querySelectorAll('.warehouse_disable').forEach(function(a){
					a.disabled = false;
				})
				//show all elements with class warehouse_hide
				document.querySelectorAll('.warehouse_hide').forEach(function(a){
					a.style.display = "block";
				})
			}
		}
		
		/**
		 * Handles checking status and changing from Open to In Progress if needed
		 * @author Alex Borchers
		 * @param {int} index (related to material order ID or ship request ID)
		 * @param {string} type (material_order / ship_request)
		 * @returns void
		 */
		function check_pending (index, type){
						
			//if status is open, move to pending, update class, and send request to ajax to update this
			if (u.eid("status" + index).innerHTML == "Open"){
				
				//update status on list
				u.eid("status" + index).innerHTML = "In Progress";
				
				//initalize form data (will carry all form data over to server side)
				var fd = new FormData();

				//append info needed to update database
				if (type == "material_order")
					fd.append('update', allocations_mo[current_index].id);
				else
					fd.append('update', index);

				fd.append('tell', 'update_request_status');
				fd.append('type', type);
				
				//send ajax to update status
				$.ajax({
					url: 'terminal_warehouse_helper.php',
					type: 'POST',
					processData: false,
					contentType: false,
					data: fd,
					success: function (response) {
						
						//if we return anything it will be an error
						if (response != "")
							alert(response);
																			
					}
				});
			}
		}

		/**
		 * Handles loading/opening menu for adjusting containers
		 * @author Alex Borchers
		 * @returns void
		 */
		function adjust_staging_location(){

			// get list of current staged locations
			var current_staged = u.eid("input_staged_loc").value.split(',');
			console.log(current_staged);

			// unset all previous selections
			document.querySelectorAll('.staging_location_label').forEach(function(a){
				if (a.classList.contains("ui-state-active"))
					a.click();
			})

			// loop through current_staged and update list
			for(var i = 0; i < current_staged.length; i++){
				if (u.eid(current_staged[i]))
					u.eid(current_staged[i]).click();
			}

			// show dialog
			open_dialog('staged_location_dialog');
		}

		/**
		 * Handles updating wh_container <select> option based on what user clicks in adjust container menu
		 * @author Alex Borchers
		 * Uses 'this' <input> element clicked (properties name & value set in add_container_menu_row)
		 * @returns void
		 */
		function sync_staging_location(){

			// initialize list to enter into staged loc field
			var staged_loc_string = "";

			// get list of wh_containers
			document.querySelectorAll('.staging_location').forEach(function(a){
				if (a.checked)
					staged_loc_string += a.id + ",";
			})

			// String last 1 off if we have any
			if (staged_loc_string != "")
				staged_loc_string = staged_loc_string.substr(0, staged_loc_string.length - 1);

			// Update staged loc field
			u.eid("input_staged_loc").value = staged_loc_string;
		}

		/**
		 * Handles loading/opening menu for adjusting containers
		 * @author Alex Borchers
		 * @returns void
		 */
		function adjust_container_menu(){

			// remove previous rows added to table (if any)
			document.querySelectorAll('.adjust_container_row').forEach(function(a){
				a.remove();
			})

			// grab class for part & containers
			var parts = u.class("part_id"),
				containers = u.class("wh_container");

			// get table tbody to add to
			var table = u.eid("adjust_container_table").getElementsByTagName('tbody')[0];

			// loop through part_id and add to adjust_container_table
			for (var i = 0; i < parts.length; i++){
				add_container_menu_row(table, parts[i].value, containers[i].value, i);
			}

			// show dialog
			open_dialog('adjust_container_dialog');
		}

		/**
		 * Handles loading/opening menu for adjusting containers
		 * @author Alex Borchers
		 * @param {HTML Element} 	table 	(table to be added to)
		 * @param {String} 			part 	(to the part being added)
		 * @param {String} 			container 	(the container already selected)
		 * @param {Int} 			index 	(index to set "name" of radiobuttons)
		 * @returns void
		 */
		function add_container_menu_row(table, part, container, index){

			// insert new row to table
			var row = table.insertRow(-1);
			row.classList.add("adjust_container_row");

			// add part as first cell
			var cell = row.insertCell(0);
			cell.innerHTML = part;

			// loop through the potential options for containers and populate checkboxes for ease of use
			for (var i = 1; i < 11; i++){

				var cell = row.insertCell(i);
				cell.classList.add('adjust_pallent_checkbox');
				cell.addEventListener('click', function(){
					var radio = this.childNodes[0];
					radio.click();
				});
				var input = document.createElement("input");
				input.type = "radio";
				input.name = index;
				input.value = "Container " + i;
				input.addEventListener('change', sync_container_options);

				// check previous container selected
				if (container == "Container " + i)
					input.checked = true;
				
				cell.appendChild(input);

			}
		}

		/**
		 * Handles updating wh_container <select> option based on what user clicks in adjust container menu
		 * @author Alex Borchers
		 * Uses 'this' <input> element clicked (properties name & value set in add_container_menu_row)
		 * @returns void
		 */
		function sync_container_options(){

			// get list of wh_containers
			var wh_container = u.class("wh_container");

			// update target container based on attributes of radio button
			// name = index of class object
			// value = target container name
			wh_container[parseInt(this.name)].value = this.value;
			update_pq_detail(wh_container[parseInt(this.name)]);
		}
		
		//handles showing parts table
		function show_pr_parts(){
			
			//remove previous parts
			document.querySelectorAll('.pq-parts-row').forEach(function(a){
				a.remove();
			})
			
			//grab current mo #
			var curr_mo = allocations_mo[current_index].mo_id;
						
			//loop through pq_detail and add any parts that we match this mo
			for (var i = 0; i < pq_detail.length; i++){
				if (curr_mo == pq_detail[i].mo_id && (pq_detail[i].status == "Pending" || pq_detail[i].status == "Shipped" || pq_detail[i].status == "Received" || pq_detail[i].status == "Staged" || pq_detail[i].status == "In-Transit"))
					add_pr_item(pq_detail[i], i);
			}
			
			//check to see if we found any parts by checking part_id class
			var part_check = u.class("part_id");
			
			//check length of class
			if (part_check.length == 0)
				u.eid("pq-parts-table").style.display = "none";
			else
				u.eid("pq-parts-table").style.display = "block";

			// hide shipping request table
			u.eid("sr-parts-table").style.display = "none";
			
			//add event listener to reject class
			$( ".reject" ).on('click', function(){
				$( "#reject_dialog" ).dialog({
					width: "auto",
					height: "auto",
					dialogClass: "fixedDialog",
				});
				
				//update id placeholder
				var dash1 = this.id.indexOf("_");
				var dash2 = this.id.indexOf("_", dash1 + 1)

				var detail_id = this.id.substr(dash1 + 1, dash2 - dash1 - 1);
				var overview_id = this.id.substr(dash2 + 1)

				u.eid("hold_detail_id").innerHTML = detail_id;
				u.eid("hold_overview_id").innerHTML = overview_id;
				
				//update part # and quantity
				//traverse through AST and grab index[1] & [3] for rows
				var td = this.parentElement;
				var tr = td.parentElement;
				var part = tr.childNodes[1].childNodes[0].value;
				var quantity = tr.childNodes[3].childNodes[0].value;
				
				u.eid("reject_part").value = part;
				u.eid("reject_quantity").value = quantity;
				u.eid("reject_quantity").max = parseInt(quantity);
			})
			
			//add event listener to pallent and notes
			$( ".wh_container").on('change', function(){
				
				update_pq_detail(this);
				//alert(this.value);
			})
			
			$( ".wh_notes").on('change', function(){

				update_pq_detail(this);
				//alert(this.value);
			})
		}
		
		//init globals that will assist in add_pr_item (and make it much easier to edit/style)
		const item_value = ['reject', 'part_id', 'description', 'q_allocated', 'uom', 'on_hand', 'instructions', 'location', 'wh_container', 'wh_notes'],
			item_type = ['NA', 'text', 'text', 'number', 'text', 'text', 'text', 'text', 'text', 'NA'],
			item_readOnly = [true, true, true, true, true, true, true, true, false, false];
		
		//handles adding a row based on index of pq_detail
		//param 1 (object that holds a given part number. Has properties decision, mmd, mo_id, part_id, project_id, quantity, quoteNumber, subs, and uom)
		//param 2 (index in pq_detail object)
		function add_pr_item(item, index){
			
			//init vars needed
			var table = u.eid("pq-parts-table").getElementsByTagName('tbody')[0], input, search;
			
			//check part Number for "(" - shows that the part was subbed
			if(item.part_id.indexOf("[") > 0){
				search = item.part_id.substr(0, item.part_id.indexOf("[") - 1)
			}
			else{
				search = item.part_id;
			}
						
			//set search to lower case
			search = search.toLowerCase();
			
			//get inventory index (if available)
			var inv_index = inventory.findIndex(object => {
			  return object.partNumber.toLowerCase() == search;
			});
			
			//if we do not find a match, set UOM and decision to be purchased
			if (inv_index == -1){
				item.uom = "";
				item.description = "";
				item.on_hand = 0;
				item.location = "";
			}
			else{
				item.part_id = inventory[inv_index].partNumber;
				item.uom = inventory[inv_index].uom;
				item.description = inventory[inv_index].partDescription;
				item.on_hand = parseInt(inventory[inv_index][use_shop + "-1"]) + parseInt(inventory[inv_index][use_shop + "-3"]);
				
				//if omaha, add -2
				if (use_shop == "OMA")
					item.on_hand+= parseInt(inventory[inv_index][use_shop + "-2"])
				
				item.location = get_location(inventory[inv_index].partNumber, item.decision);
			}
			
			//update instructions to include reel assignments
			//get_reel_string found in javascript/js_helper.js
			item.instructions = get_reel_string(item.id, reel_requests) + " " + item.instructions;
				
			//set values that aren't found on item
			//if item is complete, disable reject button
			if (item.status == "Shipped" || item.status == "Received" || item.status == "Staged" || item.status == "In-Transit")
				item.reject = "<button class = 'reject' id = 'reject_" + item.id + "_" + item.project_id + "' disabled>Reject</button>";
			else
				item.reject = "<button class = 'reject' id = 'reject_" + item.id + "_" + item.project_id + "' >Reject</button>";

			//insert new row and add classname to it
			var row = table.insertRow(-1);
			row.classList.add("pq-parts-row");
			row.id = "part" + item.id;
			
			//if complete, add complete status to row
			if (item.status == "Shipped" || item.status == "Received" || item.status == "Staged" || item.status == "In-Transit")
				row.classList.add("complete-row");

			//loop through item_value array to help guide creation of table
			for (var i = 0; i < item_value.length; i++){
				
				//create new cell
				var cell = row.insertCell(i);
				
				//check input_type (if na, just write HTML)
				if (item_type[i] == "NA"){

					//if value is null, update to ""
					if (item[item_value[i]] === null)
						item[item_value[i]] = ""
					
					//handle notes differently
					if (item_value[i] == 'wh_notes')
						cell.innerHTML = "<textarea class = 'wh_notes' >" + item[item_value[i]] + "</textarea>";
					else
						cell.innerHTML = item[item_value[i]];
				}
				// create custom <select> for containers
				else if (item_value[i] == "wh_container"){

					// call function & update value
					input = document.createElement("input");
					input.value = item[item_value[i]];
					input.readOnly = true;

					//add class list with id name
					input.classList.add(item_value[i]);
					
					//append to cell
					cell.appendChild(input);

				}
				//else set value and type based on item
				else{
					//create element and add type
					input = document.createElement("input");
					input.type = item_type[i];
					
					//set value and readonly properties
					input.value = item[item_value[i]];
					input.readOnly = item_readOnly[i];
					
					//if readonly, set tabindex to -1
					if (item_readOnly[i])
						input.tabIndex = -1;
					
					//add class list with id name
					input.classList.add(item_value[i]);
					
					//append to cell
					cell.appendChild(input);
				}
			}	
		}
		
		//handles finding physical location of part
		//function takes a part number & shop (decision)
		//returns physical location in physical_locations or nothing (if we find no match)
		function get_location(part, shop, id = null){
			
			//skip first part if id is null
			if (id !== null){

				//remove all NULL pq_detail IDs
				var reels = reel_requests.filter(function (reel) {
					return reel.pq_detail_id != null;
				});

				//filter all reels related to id
				var reel_list = reels.filter(function (reel) {
					return reel.pq_detail_id == id || reel.pq_detail_id.includes("|" + id + "(");
				});

				//if reel list > 0, send back where to pull these from
				if (reel_list.length > 0){

					//init return string
					var reel_string = "";

					//loop and push to string
					for (var i = 0; i < reel_list.length; i++){
						reel_string += reel_list[i].reel_id + " (" + reel_list[i].location + ") (" + reel_list[i].quantity + ")\n";	
					}

					return reel_string;
				}
			}

			//look for index physical_locations
			var location_list = physical_locations.filter(object => {
			  return object.partNumber.toLowerCase() == part.toLowerCase() && object.shop == shop;
			});

			//if we find a match, create string to return, otherwise return nothing
			if (location_list.length == 0)
				return "";
			
			//convert from array of objects to array
			var location_array = location_list.map(a => a.location);

			//convert array to string
			var loc_string = String(location_array);
			loc_string = loc_string.replace(",", ", ");

			//if index is not -1, return the location
			return loc_string;
			
		}

		/**@author Alex Borchers
		 * Handles creating custom <select> with options for 10 containers (generic container 1-10)
		 * @returns {HTML Entity} <select> 
		 */
		function create_wh_container_select(){

			// create <select>, add custom select (added to nearly all selects)
			var select = document.createElement("select");
			select.classList.add("custom-select");

			// default first value as blank option
			select.appendChild(document.createElement("option"));

			// loop through and create 10 options as container 1 - 10
			for (var i = 1; i < 11; i++){
				var option = document.createElement("option");
				option.innerHTML = "Container " + i;
				option.value = "Container " + i;
				select.appendChild(option);
			}

			/*
			// loop through and create 10 options as box 1 - 10
			for (var i = 1; i < 11; i++){
				var option = document.createElement("option");
				option.innerHTML = "Box " + i;
				option.value = "Box " + i;
				select.appendChild(option);
			}
			*/

			// return completed <select> element
			return select;

		}

		/**
		 * Handles showing shipping requests table
		 * @author Alex Borchers
		 * @returns void
		 */
		function show_sr_parts(){
			
			// remove previous parts
			document.querySelectorAll('.sr-parts-row').forEach(function(a){
				a.remove();
			})
			
			// filter parts based on selected ship request
			var parts = get_sr_parts(ship_req_id);

			// get list of containers from parts ( to see what exactly was ordered )
			var containers = [...new Set(parts.map(object => object.wh_container))];
			containers = containers.sort();
						
			// loop through containers, grab physical location, and send as object
			for (var i = 0; i < containers.length; i++){
				
				// get first index (assuming all parts under container are in the same location)
				var first_index = parts.findIndex(function (element) {
					return element.wh_container == containers[i];
				});

				add_sr_item({
					container: containers[i],
					staged_location: parts[first_index].received_staged_loc
				});
			}
			
			// show table (hide pr table)
			u.eid("sr-parts-table").style.display = "block";
			u.eid("pq-parts-table").style.display = "none";
			
			//add event listener to pallent and notes
			/*$( ".wh_container").on('change', function(){
				
				update_pq_detail(this);
				//alert(this.value);
			})
			
			$( ".wh_notes").on('change', function(){

				update_pq_detail(this);
				//alert(this.value);
			})*/
		}

		/**
		 * Handles getting shipping request parts based on ship request id
		 * @author Alex Borchers
		 * @param {string} id (matches id in fst_pq_ship_request)
		 * @returns {array} array of objects (related to matching rows in fst_pq_detail table)
		 */
		function get_sr_parts(id){
			return pq_detail.filter(function (element) {
				return element.ship_request_id == id;
			});
		}
		
		//init globals that will assist in add_pr_item (and make it much easier to edit/style)
		const sr_item = ['add_to_shipment', 'expand_container', 'container', 'staged_location'];
		
		/**
		 * Handles adding a row based on index of pq_detail
		 * @param {object} part (object that contains container & staging_location)
		 * @returns void
		 */
		function add_sr_item(part){
			
			// init vars needed
			var table = u.eid("sr-parts-table").getElementsByTagName('tbody')[0], input, search;
			
			// update static values
			part.expand_container = "<button class = 'expand_container' onclick = 'show_container_contents(this)'>+</button>";

			//insert new row and add classname to it
			var row = table.insertRow(-1);
			row.classList.add("sr-parts-row");
			
			//if complete, add complete status to row
			//if (part.status == "Shipped" || part.status == "Received" || part.status == "Staged" || part.status == "In-Transit")
			//	row.classList.add("complete-row");

			//loop through part_value array to help guide creation of table
			for (var i = 0; i < sr_item.length; i++){
				
				//create new cell
				var cell = row.insertCell(i);

				// depending on sr_item, create different element
				if (sr_item[i] == "expand_container"){
					cell.innerHTML = part[sr_item[i]];
				}
				else if (sr_item[i] == "add_to_shipment"){
					// create <input> checkbox
					input = document.createElement("input");
					input.type = "checkbox";
					input.checked = true;
					
					// add class list with id name
					input.classList.add(sr_item[i]);
					
					// append to cell
					cell.appendChild(input);
				}
				// Adjustment if container is blank
				else if (sr_item[i] == "container" && (part.container == null || part.container == "")){
					// create <input> and set value
					input = document.createElement("input");
					input.value = "Needs Assignment";
					input.readOnly = true;
					
					// add class list with id name
					input.classList.add(sr_item[i]);
					
					// append to cell
					cell.appendChild(input);
				}
				else{
					// create <input> and set value
					input = document.createElement("input");
					input.value = part[sr_item[i]];
					input.readOnly = true;
					
					// add class list with id name
					input.classList.add(sr_item[i]);
					
					// append to cell
					cell.appendChild(input);
				}
			}
		}

		/**
		 * Handles showing container contents on button click
		 * @author Alex Borchers
		 * @param {HTMLElement} targ <button> clicked
		 * @returns void
		 */
		function show_container_contents(targ){

			// work back to container # from the button clicked
			var td = targ.parentNode;
			var tr = td.parentNode;
			var tbody = tr.parentNode;
			var container = get_container_name(targ);

			// Adjust container if empty
			if (container == "Needs Assignment")
				container = "Empty";

			// check targ.innerhtml for +/- to see action required
			if (targ.innerHTML == "+"){
				targ.innerHTML = "-";
				var parts = get_container_contents(container);
				insert_container_detail(tbody, tr, parts);
			}
			else{
				// change button inner HTML and remove previous row added
				targ.innerHTML = "+";
				u.eid("sr-parts-table").deleteRow(tr.rowIndex + 1);
			}
		}

		/**
		 * Simple function to get container name or box name (used in multiple locations)
		 * @author Alex Borchers
		 * @param {HTML Element} <button> OR checkbox clicked
		 * @returns {string}
		 */
		function get_container_name(targ){
			// use HTML properties to work back to name
			var td = targ.parentNode;
			var tr = td.parentNode;
			var tbody = tr.parentNode;
			var container_td = tr.childNodes[2];
			var container = container_td.childNodes[0].value;
			return container;
		}

		/**
		 * Handles getting container contents
		 * @author Alex Borchers
		 * @param {string} container (related to the container clicked on)
		 * @returns {array[object]} an array containing all filtered parts related to container
		 */
		function get_container_contents(container){

			// initialize array to be returned
			var parts = [];

			// if container is "Other" treat differently
			if (container == "Empty"){
				parts = pq_detail.filter(function (object) {
					return object.ship_request_id == ship_req_id && (object.wh_container == "" || object.wh_container == null);
				});
			}
			else{
				parts = pq_detail.filter(function (object) {
					return object.ship_request_id == ship_req_id && object.wh_container == container;
				});
			}

			return parts;

		}

		/**
		 * Handles inserting container detail into expanded area
		 */
		function insert_container_detail(tbody, tr, parts){

			// add new row below row clicked
			var row = tbody.insertRow(tr.rowIndex);
			row.classList.add("container_detail_row");
			row.classList.add("sr-parts-row");
			var add_detail_cell = row.insertCell(0);
			add_detail_cell.colSpan = tbody.rows[0].cells.length + 1;

			// create new table 
			var table = document.createElement("table");
			table.classList.add("container_detail_table");

			// create two table headers
			var row = table.insertRow(-1);
			row.classList.add("container_detail_header");
			var cell = row.insertCell(0);
			cell.innerHTML = "Part #"
			var cell = row.insertCell(1);
			cell.innerHTML = "Qty";

			// if container is blank, add extra header for container
			if (parts[0].wh_container == "" || parts[0].wh_container == null){
				var cell = row.insertCell(2);
				cell.innerHTML = "Container";
			}

			// add all parts to table
			for (var i = 0; i < parts.length; i++){
				var row = table.insertRow(-1);

				// part #
				var cell = row.insertCell(0);
				cell.innerHTML = parts[i].part_id;

				// quantity
				var cell = row.insertCell(1);
				cell.innerHTML = parts[i].q_allocated;

				// if empty container, add drop-down for containers
				if (parts[i].wh_container == "" || parts[i].wh_container == null){
					var cell = row.insertCell(2);
					var input = create_wh_container_select();
					input.id = "container" + parts[i].id;
					input.classList.add("unassigned_containers");				
					cell.appendChild(input);
				}
			}

			// If empty container, add button to save adjustments
			if (parts[0].wh_container == "" || parts[0].wh_container == null){
				var row = table.insertRow(-1);
				var cell = row.insertCell(0);
				cell.colSpan = "3";
				cell.border = "none";
				cell.innerHTML = "<button onclick = 'update_unassigned()'>Update Containers</button>";
			}

			// append table to expanded area
			add_detail_cell.appendChild(table);
		}

		/**
		 * Handles updating unassiged containers
		 * @author Alex Borchers 
		 * 
		 * Globals:
		 * ship_req_id {int} (related to shipping request ID)
		 * 
		 * @returns void
		 */
		function update_unassigned(){

			// Loop through unassigned_containers & set up list of new containers
			var new_assignments = [];

			document.querySelectorAll('.unassigned_containers').forEach(function(a){
				if (a.value != ""){
					new_assignments.push({
						id: a.id.substring(9),
						container: a.value
					})

					// Get index in fst_pq_detail and update
					var index = pq_detail.findIndex(object => {
						return object.id == a.id.substring(9);
					});
					pq_detail[index].wh_container = a.value;
				}
			})

			// Check to make sure user has at least 1 new container assigned
			if (new_assignments.length == 0){
				alert("[Error] At least 1 new container assignment is required.");
				return;
			}

			// Initalize form data (will carry all form data over to server side)
			var fd = new FormData();

			// Add tell, user, & containers
			fd.append('tell', 'unassigned_containers');
			fd.append('user_info', JSON.stringify(user_info));
			fd.append('new_assignments', JSON.stringify(new_assignments));
			
			$.ajax({
				url: 'terminal_warehouse_helper.php',
				type: 'POST',
				processData: false,
				contentType: false,
				data: fd,
				success: function (response) {

					//if we return a response it is an error
					if (response.substr(0, 5) != ""){
						alert(response);
						return;
					}

					//otherwise, alert user and update tables/screen
					var temp_ship_req = ship_req_id;
					ship_req_id = -1;
					u.eid("sr-label-" + temp_ship_req).click();
					alert("This part has been successfully rejected and sent back to allocations for processing.");
				}
			});
		}

		//handles rejecting parts
		//passes pq_detail_id as parameter (this is the id in pq_detail table for each part) & pq_overview_id (id in pq_overview table for project)
		function reject_part(){
			
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
			fd.append('reject_quantity', u.eid("reject_quantity").value);
			fd.append('reject_reason', u.eid("reject_reason").value);
			fd.append('reject_notes', u.eid("reject_notes").value);
			fd.append('project_number', allocations_mo[current_index].project_id);
			fd.append('mo_number', allocations_mo[current_index].mo_id);
			fd.append('urgency', allocations_mo[current_index].urgency);
			
			//check if we are rejected full or partial
			if (u.eid("reject_quantity").value == u.eid("reject_quantity").max)
				fd.append('reject_detail', "full");
			else
				fd.append('reject_detail', "partial");
			
			//add tell
			fd.append('tell', 'reject');
			
			$.ajax({
				url: 'terminal_warehouse_helper.php',
				type: 'POST',
				processData: false,
				contentType: false,
				data: fd,
				success: function (response) {

					//if we return a response it is an error
					if (response != ""){
						alert(response);

					}
					//otherwise, update our tables
					else{
						alert("This part has been successfully rejected and sent back to allocations for processing.");
						$( "#reject_dialog" ).dialog('close');
						check_orders();
					}

				}
			});
			
		}
		
		//global used to handle print preview
		var print_preview = false;
		
		function export_pdf_handler(dec, type){
			print_preview = dec;
			render_pick_ticket(type);
		}
		
		//global that holds current pq_detail index
		var pq_det_index = -1;
		
		//handles rendering PDF for pick-ticket AND packing slip
		//passes logo as imgData and WS/PW as type
		function render_pick_ticket(type){
			
			//used to generate image base 64 url
			var c = document.createElement('canvas');
			
			//depending on type, grab logo
			var img = u.eid('pw_logo');
			var logo_width = 175;
			var column_width = 315;
			
			if (type == "ws-pick-ticket"){
				img = u.eid("ws_logo")
				logo_width = 250;
				column_width = 250;
			}
			
			c.height = img.naturalHeight;
			c.width = img.naturalWidth;
			var ctx = c.getContext('2d');
			ctx.drawImage(img, 0, 0, c.width, c.height);
			
			//hold image object used in pdf
			var base64String = c.toDataURL();
			
			//define column width
			var left_width = 75;
			
			//get today's date
			//let today = new Date().toLocaleDateString()
			
			//generate document based on criteria
			var docDefinition = {
				pageSize: 'A4',
				pageOrientation: 'landscape',
				pageMargins: [40, 30, 40, 60], //[horizontal, vertical] or [left, top, right, bottom]
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
					if (currentPage == pageCount){
						return [
							{
								columns: [
									{
										text: 'PICKED BY _______________', 
										style: 'footer_style',
										alignment: 'left'
									}, 
									{
										text: use_shop + " Pick Ticket", 
										alignment: 'center',
										style: 'footer_style'
									},
									{
										text: 'CHECKED BY _______________', 
										alignment: 'right',
										style: 'footer_style'
									}
								], 
							},
						]
					}
					else{
						return[
							{
								text: use_shop + " Pick Ticket", 
								alignment: 'center', 
								style: 'footer_style'
							}
						]
					}
				},
				content:[
					{
						columns: [
							{
								image: base64String,
								width: logo_width, 
								style: 'header_logo',
								lineHeight: 6
							}, 
							{
								text: 'Pick Ticket',
								width: column_width, 
								alignment: 'right',
								style: 'header_pick' 
								
							}, 
							{
								text: "Due By: " + format_date(allocations_mo[current_index].date_required), 
								alignment: 'right',
								style: 'header_date'
							}
						],
						
					},
					//render_pdf_table('single_block', 'PROJECT INFO'),
					render_body(type)
					
					//generates table based on type 
					//table_handler('pick'), 
					
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
						margin: [-20, -17, -30, 20] //[left, top, right, bottom]
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
					header_date: {
						fontSize: 24, 
						color: 'red',
						margin: [40, 0, 0, 10] //[left, top, right, bottom]
					}, 
					body_text: {
						fontSize: 12, 
						margin: [0, 0, 0, 0], //[left, top, right, bottom]
						lineHeight: 1.2,
						alignment: 'justify'
					},
					body_text_bold: {
						fontSize: 12, 
						margin: [0, 0, 0, 0], //[left, top, right, bottom]
						lineHeight: 1.2,
						alignment: 'justify',
						bold: true
					},
					red_header: {
						fontSize: 12, 
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
						alignment: 'center',
						margin: [0, 5, 0, 5] //[left, top, right, bottom]
						
					},
					table_header: {
						fontSize: 11, 
						bold: true, 
						fillColor: '#114B95', 
						color: 'white',
						alignment: 'center'						
					},
					table_body: {
						fontSize: 10, 
						margin: [0, 30, 0, 10],  //[left, top, right, bottom]
						unbreakable: true,
						lineHeight: 1.2
					}, 
					single_block: {
						fontSize: 9, 
						margin: [0, 10, 0, 10], //[left, top, right, bottom]
						unbreakable: true,
						lineHeight: 1.2
					}, 
					total_row: {
						fontSize: 9.5, 
						bold: true
					}, 
					footer_style: {
						fontSize: 12, 
						bold: true,
						margin:[40, 20, 40, 0] //[left, top, right, bottom]
					}, 
					italics_row: {
						italics: true, 
						fontSize: 9
					}
					
				}
			};			
			
			//********PDF PRINT PREVIEW
			if (print_preview){
				
				
				pdfMake.createPdf(docDefinition).getDataUrl().then((dataUrl) => {
					//set src to dataURL
					u.eid("attachment").src = dataUrl;
				}, err => {
					console.error(err);
				});

				pdfMake.createPdf(docDefinition).getDataUrl();
				
				//show div holding this
				u.eid("attachment").style.display = "block";
				
			}
			//*******SAVE TO SERVER & EXECUTE
			else{
				
				//if not print preview, save copy to server for email
				pdfMake.createPdf(docDefinition).getBuffer().then(function(buffer) {

					var blob = new Blob([buffer]);

					var reader = new FileReader();
					
					// this function is triggered once a call to readAsDataURL returns
					reader.onload = function(event) {
						var fd = new FormData();
						fd.append('fname', 'temp.pdf');
						fd.append('data', event.target.result);
						fd.append("mo_id", allocations_mo[current_index].mo_id);
						fd.append('tell', 'temp_pdf')

						$.ajax({
							type: 'POST',
							url: 'terminal_warehouse_helper.php', // Change to PHP filename
							data: fd,
							processData: false,
							contentType: false
						}).done(function(data) {
							
							//execute close & submit function (send MO) once finished
							close_and_submit();

						});
					};

					// trigger the read from the reader...
					reader.readAsDataURL(blob);
				});
			}
		}
		
		//global width used to create a flexible table width
		var flex_table_width = 0;
		
		//handles rendering the body of the pdf depending on the type (WS or PW)
		function render_body(type){
			
			//init body to be returned
			var return_body = [];
			
			//set global table width based on size of address		
			var base = 25;
			
			if (allocations_mo[current_index].ship_to.length < allocations_mo[current_index].street.length)
				base = allocations_mo[current_index].street.length;
			else
				base = allocations_mo[current_index].ship_to.length;
			
			//multiply table width based on base value
			if (base <= 30)
				flex_table_width = base * 6;
			else if(base <= 35)
				flex_table_width = base * 5.8;
			else if(base <= 40)
				flex_table_width = base * 5.6;
			else if(base <= 45)
				flex_table_width = base * 5.4;
			else if(base <= 50)
				flex_table_width = base * 5.2;
			else if(base <= 55)
				flex_table_width = base * 5;
			else if(base <= 60)
				flex_table_width = base * 4.8;
			else if(base <= 65)
				flex_table_width = base * 4.6;
			else
				flex_table_width = base * 4.4;
			
			//set minimum at 150
			if (flex_table_width < 150)
				flex_table_width = 150;
			
			//if PW, return the headers we require
			if (type == 'pw-pick-ticket'){
				return_body.push(render_header('Project #', 'project_id', "POC Name", "poc_name"));
				return_body.push(render_header('Material Order #', 'mo_id', 'POC Phone #', 'poc_number'));
				return_body.push(render_header('Date Issued', 'date_created', 'Location Name', 'ship_to'));
				return_body.push(render_header('PW MM', 'manager', 'Ship to Address', 'street'));
				return_body.push(render_header('Ordered By', 'requested_by', '', 'city_state_zip'));
				return_body.push(render_header('Notes', 'notes', 'Lift Gate Required', 'liftgate'));
				return_body.push(render_header('', '', 'Scheduled Delivery', 'sched_opt'));
				return_body.push(render_header('', '', 'shipping_opt', ''));
				return_body.push(render_pdf_table('bom'));
			}
			else if (type == "ws-pick-ticket"){
				
			}
			
			//return body
			return return_body;
			
		}
		
		//buildes two columns for header info
		function render_header(left_header_text, left_header_id, right_header_text, right_header_id){
			
			//init to be returned
			var return_header = [];
			
			//init styles
			var header_style = "body_text_bold",
				body_style = "body_text";
			
			//inint left id and right id text
			var left_id_text = "", right_id_text = "";

			//default header width on the right
			var right_header_width = 120;
			
			//set left, right text based on criteria read in
			if (right_header_text == "Scheduled Delivery"){
				if (allocations_mo[current_index][right_header_id] == "Y")
					right_id_text = allocations_mo[current_index][right_header_id] + " (" + format_date(allocations_mo[current_index]['sched_time'].substr(0, 10)) + " " + init_time(allocations_mo[current_index]['sched_time'].substr(11)) + ")";
				else
					right_id_text = allocations_mo[current_index][right_header_id]
			}
			else if (right_header_id == "city_state_zip"){
				right_id_text = u.eid(right_header_id).innerHTML; 
			}
			else if (right_header_text == "shipping_opt"){
				right_header_text = allocations_mo[current_index][right_header_text]; 
				header_style = "red_header"

				//adjust column widths
				right_header_width = 120 + flex_table_width;
				flex_table_width = 0;
			}
			else if (right_header_text != ""){
				right_id_text = allocations_mo[current_index][right_header_id]; 
			}
			
			if (left_header_id == "date_created")
				left_id_text = utc_to_local(allocations_mo[current_index].date_created, true)
			else if (left_header_id == "project_id")
				left_id_text = allocations_mo[current_index].project_id + " " + allocations_mo[current_index].project_name
			else if (left_header_text != "")
				left_id_text = allocations_mo[current_index][left_header_id];
			
			//create left side	
			return_header.push({
				columns: [
					{
						text: left_header_text,
						width: 100, 
						style: header_style
					},
					{
						text: left_id_text,
						style: body_style,
						width: "*"
					},
					/*{
						text: "",
						width: "*"	
					},*/
					{
						text: right_header_text,
						width: right_header_width, 
						style: header_style
					},
					{
						text: right_id_text,
						width: flex_table_width,
						style: body_style 
					}
				]
			});
			
			return return_header;
			
		}
		
		//builds table based on type of table
		function render_pdf_table(type, text = null){
			
			//just send back formatting for single block
			if (type == "single_block"){
				
				//return format AND body 
				return {
					style: 'single_block',
					table: {
						widths: [100],
						heights: [20],
						headerRows: 1,
						body: render_pdf_body(type, text)
					}
				};
			}
			else if (type == "bom"){
				
				//return format AND body 
				return {
					style: 'table_body',
					table: {
						widths: ['auto', 'auto', 'auto', '*', 'auto', 80, 160, 160, 'auto', 'auto'],
						headerRows: 1,
						dontBreakRows: true,
						body: render_pdf_body(type)
					}
				};
			}
			
			
			
		}
		
		//global for sequence count
		var seq_count = 1;
		
		//renders PDF table headers and body if necessary
		function render_pdf_body(type, text = null){
		
			//init body to send back
			var body = [];
			
			//just send back formatting for single block
			if (type == "single_block"){
				
				//pass table headers as array
				var headers = createHeaders([
					text
				], 'table_header_margin');

				//add headers to body
				body.push(headers);
				
			}
			//create BOM table
			else if (type == "bom"){
				
				//pass table headers as array
				var headers = createHeaders([
					"Shop",
					"Seq",
					"Picked", 
					"Part #", 
					"UoM", 
					"Shop On-Hand",
					"Description",
					"Pull From",
					"Qty",
					"Verified"
				], 'table_header');

				//add headers to body
				body.push(headers);
				
				//reset seq count
				seq_count = 1;
				
				//grab current mo #
				var curr_mo = allocations_mo[current_index].mo_id;
				
				//loop through pq_detail and grab parts that match current MO
				for (var i = 0; i < pq_detail.length; i++){
					if (curr_mo == pq_detail[i].mo_id && (pq_detail[i].status == "Pending" || pq_detail[i].status == "Shipped" || pq_detail[i].status == "Received" || pq_detail[i].status == "Staged" || pq_detail[i].status == "In-Transit"))
						body.push(render_pdf_body_row(pq_detail[i], i));
				}
				
			}
			
			//return our table
			return body;
			
		}
		
		//renders pdf body row for BOM items
		//takes item (the actual part we are adding)
		function render_pdf_body_row(item){

			//init search variable
			var search;
			
			//check part Number for "(" - shows that the part was subbed
			if(item.part_id.indexOf("[") > 0){
				search = item.part_id.substr(0, item.part_id.indexOf("[") - 1)
			}
			else{
				search = item.part_id;
			}
						
			//set search to lower case
			search = search.toLowerCase();
			
			//get inventory index (if available)
			var inv_index = inventory.findIndex(object => {
			  return object.partNumber.toLowerCase() == search;
			});

			//create placeholder for physical locations
			var phys_location = "";
			
			//if we do not find a match, set UOM and decision to be purchased
			if (inv_index == -1){
				item.uom = "";
				item.description = "";
				item.on_hand = 0;
				phys_location = "";
			}		
			else{
				item.part_id = inventory[inv_index].partNumber;
				item.uom = inventory[inv_index].uom;
				item.description = inventory[inv_index].partDescription;
				item.on_hand = parseInt(inventory[inv_index][use_shop + "-1"]) + parseInt(inventory[inv_index][use_shop + "-3"]);
				
				//if omaha, add -2
				if (use_shop == "OMA")
					item.on_hand+= parseInt(inventory[inv_index][use_shop + "-2"]);
				
					phys_location = get_location(inventory[inv_index].partNumber, item.decision, item.id);
			}
			
			//now that we have done our checks, add to pdf table
			var dataRow = [];
			dataRow.push({text: item.decision, alignment: 'center'});
			dataRow.push({text: seq_count, alignment: 'center'});
			dataRow.push({canvas: [ { type: 'rect', x: 11, y: 0.5, w: 10, h: 10, r: 1, lineColor: 'black'} ]});
			dataRow.push({text: item.part_id, alignment: 'left', color: 'red'});
			dataRow.push({text: item.uom, alignment: 'center'});
			dataRow.push({text: item.on_hand, alignment: 'center'});
			dataRow.push({text: item.description, alignment: 'left'});
			dataRow.push({text: phys_location, alignment: 'center', color: 'red'});
			dataRow.push({text: item.q_allocated, alignment: 'center', color: 'red'});
			dataRow.push({canvas: [ { type: 'rect', x: 14, y: 0.5, w: 10, h: 10, r: 1, lineColor: 'black'} ]});

			//increment seq count
			seq_count++;
			
			//push row to table
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
				
		//handles toggling between different attachments
		function show_attachment (targ){
									
			//if type = whh, use whh logic
			if (targ == 1 || targ == 2 || targ == 3){
				
				//show attachment, hide picture
				u.eid("attachment").style.display = "block";
				u.eid("picture").style.display = "none";
				
				//depending on targ, show relevant attachment
				if (targ == 1 && allocations_mo[current_index].attachment1 != ""){
					u.eid("attachment").src = "materialOrders/" + allocations_mo[current_index].attachment1;
					curr_attachment = "materialOrders/" + allocations_mo[current_index].attachment1;
				}
				else if (targ == 2 && allocations_mo[current_index].attachment2 != ""){
					u.eid("attachment").src = "materialOrders/" + allocations_mo[current_index].attachment2;
					curr_attachment = "materialOrders/" + allocations_mo[current_index].attachment2;
				}
				else if (targ == 3 && allocations_mo[current_index].attachment3 != ""){
					u.eid("attachment").src = "materialOrders/" + allocations_mo[current_index].attachment3;
					curr_attachment = "materialOrders/" + allocations_mo[current_index].attachment3;
				}
			}
			else{
				//show picture, hide attachment
				u.eid("attachment").style.display = "none";
				u.eid("picture").style.display = "block";
				u.eid("picture").src = "warehouse_attachments/" + targ.currentTarget.value;
			}
		}
		
		//handles processing completed material orders
		//first, run checks to make sure all required fields are filled in
		//second save the pick ticket (if applicable) to the server
		//third run function grabs data and sends email to all parties required
		function close_and_submit_handler(){
			
			//(1)check required fields
			var required_fields = u.class("warehouse_required"), error = false;
			
			//if staged is checked, ignore required fields
			if (!u.eid("input_staged").checked){
				for (var i = 0; i < required_fields.length; i++){
					if (required_fields[i].value == ""){
						error = true;
						required_fields[i].classList.add("required_error");
					}
					else{
						required_fields[i].classList.remove("required_error");
					}
				}
			}
			/*else{
				if (u.eid("input_staged_loc").value == ""){
					error = true;
					u.eid("input_staged_loc").classList.add("required_error");
				}
				else
					u.eid("input_staged_loc").classList.remove("required_error");
			}*/
						
			//check dec boolean 
			if (error){
				alert("[Error] Please fill in all required fields before submitting.");
				return;
			}

			// check to make sure user has filled out containers for all parts
			var wh_containers = u.class("wh_container");
			for (var i = 0; i < wh_containers.length; i++){
				if (wh_containers[i].value == ""){
					alert("[Error] Please make sure all parts are assigned a container or box.");
					return;
				}
			}
			
			// check to make sure we have a whh attachment included
			var whh_check = u.class("whh-inputs");
			
			if (whh_check.length == 0 && allocations_mo[current_index].urgency != "[Greensheet]"){
				alert("Please include a photobooth attachment (taken from warehouse tablet).");
				u.eid("whh_highlight").classList.add("required_error");
				return;
			}
			
			//(2)check to see if we have itemized BOM or not
			var parts = u.class("pq-parts-row");

			//lock "close and submit" button so that user does not click twice (this transaction may take some time to process)
			u.eid("close_and_submit_button").disabled = true;
			
			//if 0, skip to part 3
			if (parts.length == 0)
				close_and_submit();
			//if length != 0, then save a copy of the pick ticket
			else
				export_pdf_handler(false, "pw-pick-ticket");
						
			//(3) run function to process order
			//look up SAVE TO SERVER & EXECUTE
			//run close_and_submit() after saving to server
			
		}
		
		//handles processing completes material orders
		function close_and_submit (){
			
			//get list of IDs related to request
			var pq_detail_ids = [];
			var parts = u.class("pq-parts-row");

			for (var i = 0; i < parts.length; i++){
				pq_detail_ids.push(parts[i].id.substr(4));
			}

			//initalize form data (will carry all form data over to server side)
			var fd = new FormData();

			//append info needed to update database
			fd.append('input_carrier', u.eid("input_carrier").value);
			fd.append('input_tracking', u.eid("input_tracking").value);
			fd.append('input_receipt', u.eid("input_receipt").value);
			fd.append('input_ship_cost', u.eid("input_ship_cost").value);
			fd.append('input_checked_by', u.eid("input_checked_by").value);
			fd.append('input_processed_by', u.eid("input_processed_by").value);
			fd.append('input_picked_by', u.eid("input_picked_by").value);
			fd.append('input_staged_loc', u.eid("input_staged_loc").value);
			fd.append('input_staged', u.eid("input_staged").checked);
			fd.append('mo_id', allocations_mo[current_index].mo_id);
			fd.append('shop', use_shop);
			fd.append('cc', allocations_mo[current_index].cc_email);
			fd.append('unique_id', allocations_mo[current_index].id);
			fd.append('project_id', allocations_mo[current_index].project_id);
			fd.append('ship_to', allocations_mo[current_index].ship_to);
			fd.append('pq_detail_ids', JSON.stringify(pq_detail_ids));
			
			//add attachments
			//picture of contents first (this is dynamic so lets go one by one for num_attachments and check to see if we need to add anything)
			var poc_attachments = [];
			
			for (var i = 1; i < num_attachments; i++){
				if(u.eid("poc-" + i).files.length > 0){
					var file = $("#poc-" + i)[0].files[0];
					fd.append('file' + i, file);

					//save name in attachments array
					poc_attachments.push(i);
				}				
			}
			
			//add array of attachments to form
			fd.append('poc_attachments', JSON.stringify(poc_attachments));

			//bol next, just one attachment so this one should be easy
			if(u.eid("bol").files.length > 0){
				var file = $("#bol")[0].files[0];
				fd.append('bol', file);
			}
			
			//add contents of containers
			//same strategy as POC, lets loop through each input line and check to see if we have a value and add if we do
			var cop = [];
			
			for (var i = 1; i < num_containers; i++){
				if (u.eid("cop-" + i).value != "")
					cop.push(u.eid("cop-" + i).value);
				
			}
			
			//add cop to form data
			fd.append('cop', JSON.stringify(cop));
			
			//add list of attachments for current MO
			fd.append('mo_attachment1', allocations_mo[current_index].attachment1);
			fd.append('mo_attachment2', allocations_mo[current_index].attachment2);
			fd.append('mo_attachment3', allocations_mo[current_index].attachment3);
			
			//add tell & user
			fd.append('tell', 'close_and_submit');
			fd.append('user_info', JSON.stringify(user_info));
			
			//call ajax
			$.ajax({
				url: 'terminal_warehouse_helper.php',
				type: 'POST',
				processData: false,
				contentType: false,
				data: fd,
				success: function (response) {

					if (response != ""){
						alert(response);
						
					}
					else{
						alert("Your email has been successfully sent.")
						update_input = [];	//reset update_input length
						window.location.reload();
					}
											
				}
			});
		}

		// Global to keep track of containers user is going to ship
		let shipping_containers = [];

		/**
		 * Handles prepping shipments (checking for required fields, opening dialog box for shipping labels)
		 * @author Alex Borchers
		 * @returns void
		 */
		function prep_shipment (){

			// check required fields
			var required_fields = u.class("warehouse_required"), error = false;
			
			for (var i = 0; i < required_fields.length; i++){
				if (required_fields[i].value == ""){
					error = true;
					required_fields[i].classList.add("required_error");
				}
				else{
					required_fields[i].classList.remove("required_error");
				}
			}

			if (error){
				alert("[Error] Please fill in the required fields (yellow).");
				return;
			}

			// Make sure all parts have container assignments
			var containers = u.class("container");
			for (var i = 0; i < containers.length; i++){
				if (containers[i].value == "Needs Assignment"){
					containers[i].classList.add("required_error");
					alert("[Error] All parts must be assigned a container.");
					return;
				}
			}
			
			// get list of containers being processed
			var type = "Full";			// if at least 1 container is not shipping, set to partial
			shipping_containers = [];		// reset global
			var container_check = u.class("add_to_shipment");

			for (var i = 0; i < container_check.length; i++){
				if (container_check[i].checked){
					shipping_containers.push(get_container_name(container_check[i]));
				}
				else
					type = "Partial";		
			}

			// get index of shipping request
			var index = ship_requests.findIndex(object => {
				return object.id == ship_req_id;
			});

			// pass info to dialog box
			u.eid("shipping_job").innerHTML = ship_requests[index].project_name.substr(0, 10);
			u.eid("shipping_contents").innerHTML = shipping_containers.join(", ");
			u.eid("shipping_tracking").innerHTML = ship_requests[index].tracking;
			u.eid("shipping_id").innerHTML = ship_req_id;
			u.eid("shipping_type").innerHTML = type;
			
			// render shipping label pdf
			render_shipping_label();

			// manual dialog option (to customize width)
			var screenHeight = $(window).height();
			var screenWidth = $(window).width();

			$( "#shipping_dialog").dialog({
				width: "800px",
				height: screenHeight - 50,
				dialogClass: "fixedDialog",
				maxHeight: screenHeight - 50,
				maxWidth: screenWidth - 25,
				modal: true
			});			
		}

		/**
		 * Handles closing shipments (pass info to helper file through ajax)
		 * @author Alex Borchers
		 * @returns void
		 */
		function close_shipment (){

			// check to make sure the user has acknowledged printing the label.
			if (!u.eid("label_acknowledgement").checked){
				alert('[Error] Please acknowledge that the shipping label has been printed.');
				return;
			}

			// save info 1 last time
			check_orders(true);

			// get index of shipping request
			var index = ship_requests.findIndex(object => {
				return object.id == ship_req_id;
			});

			// initalize form data (will carry all form data over to server side)
			var fd = new FormData();

			// append info needed to update database
			fd.append('shipping_containers', JSON.stringify(shipping_containers));
			fd.append('ship_request', JSON.stringify(ship_requests[index]));
			fd.append('type', u.eid("shipping_type").innerHTML);
			
			//add tell & user
			fd.append('tell', 'close_shipment');
			fd.append('user_info', JSON.stringify(user_info));
			
			//call ajax
			$.ajax({
				url: 'terminal_warehouse_helper.php',
				type: 'POST',
				processData: false,
				contentType: false,
				data: fd,
				success: function (response) {

					if (response != ""){
						alert(response);
						console.log(response);
						return;
					}
					else{
						alert("The shipment has been processed successfully.")
						update_input = [];	//reset update_input length
						window.location.reload();
					}					
				}
			});
		}

		/**
		 * Handles rendering shipping label
		 * @author Alex Borchers
		 * @returns void (loads print preview window)
		 */
		function render_shipping_label(){

			// get todays date
			let today = new Date().toLocaleDateString();

			//generate document based on criteria
			var docDefinition = {
				pageSize: {
					width: 420,
					height: 280
				},
				pageOrientation: 'landscape',
				pageMargins: [30, 20, 30, 0], //[horizontal, vertical] or [left, top, right, bottom]
				defaultStyle: {
					font: 'Times'	
				},
				footer: function(currentPage, pageCount, pageSize) {
					return[
						{
							text: "Date: " + today, 
							alignment: 'center', 
							style: 'footer_style'
						}
					]
				},
				content:[
					shipping_label_content()					
				], 
				styles: {
					header_logo: {
						margin: [0, 0, 0, 20] //[left, top, right, bottom]
					}, 
					header_left: {
						fontSize: 12,
						margin: [0, -10, 0, 0] //[left, top, right, bottom]
					},
					header_right: {
						fontSize: 12,
						margin: [0, 0, 0, 0] //[left, top, right, bottom]
					},
					footer_style: {
						fontSize: 12, 
						margin:[0, -41, 0, 0] //[left, top, right, bottom]
					}, 
					label_detail: {
						fontSize: 12,
						margin: [0, 23, 0, 0] //[left, top, right, bottom]
					},
					qr_words_style: {
						fontSize: 12,
						margin: [0, -115, 0, 0] //[left, top, right, bottom]
					},
					qr_style: {
						margin: [0, 10, 0, 0] //[left, top, right, bottom]
					}
				}
			};			
			
			//********PDF PRINT PREVIEW	
			pdfMake.createPdf(docDefinition).getDataUrl().then((dataUrl) => {
				//set src to dataURL
				u.eid("shipping_label_iframe").src = dataUrl;
			}, err => {
				console.error(err);
			});

			pdfMake.createPdf(docDefinition).getDataUrl();
			
			//show div holding this
			u.eid("shipping_label_iframe").style.display = "block";
		}

		function shipping_label_content(){

			//used to generate image base 64 url
			var c = document.createElement('canvas');
			
			//depending on type, grab logo
			var img = u.eid('pw_word_logo');
			var logo_width = 100;
			var column_width = 400;			
			c.height = img.naturalHeight;
			c.width = img.naturalWidth;
			var ctx = c.getContext('2d');
			ctx.drawImage(img, 0, 0, c.width, c.height);
			
			//hold image object used in pdf
			var base64String = c.toDataURL();
			
			// get shipping request index
			var index = ship_requests.findIndex(object => {
				return object.id == ship_req_id;
			});

			// get info used to populate header info
			var location_name = u.eid("ship_to").innerHTML;
			var street = u.eid("street").innerHTML;
			var city_state_zip = u.eid("city_state_zip").innerHTML;
			var attn = "Attn: " + ship_requests[index].poc;
			var full_address = location_name + "\n" + street + "\n" + city_state_zip + "\n" + attn;

			// set arrays to hold label formatting
			var all_shipping_labels = [];
			var shipping_label = [];

			for (var i = 0; i < shipping_containers.length; i++){

				// reset individual shipping label
				shipping_label = [];
				
				// set contents for given shipping label
				shipping_label.push(
					{
						columns: [
							{
								image: base64String,
								width: logo_width, 
								style: 'header_logo'
							},
							{
								text: "",
								width: 100
							},
							{
								text: "Ship To: ",
								style: 'header_right',
								width: 50
							},
							{
								text: full_address, 
								style: 'header_right'
							},
						],
						
					}
				);
				shipping_label.push(
					{
						text: "1141 South 145th Street\nOmaha, NE 68138",
						style: 'header_left'
					}
				);
				shipping_label.push({

					//black line to split purchase order with po # in header
					canvas:
					[
						{
							type: 'line',
							x1: -40, y1: 10,
							x2: 600, y2: 10, 
							lineWidth: 1
						}
					]
				});
				shipping_label.push(
					{
						//black line to split purchase order with po # in header
						canvas:
						[
							{
								type: 'line',
								x1: 180, y1: -150,
								x2: 180, y2: 0, 
								lineWidth: 1
							}
						]
					}
				);
				shipping_label.push(
					{
						text: "Job #" + u.eid("project_id").innerHTML.substr(0, 10),
						style: 'label_detail'
					}
				);
				shipping_label.push(
					{
						text: shipping_containers[i] + " (" + (i + 1) + " of " + shipping_containers.length + ")",
						style: 'label_detail'
					}
				);
				shipping_label.push(
					{
						text: "Tracking #\n" + u.eid("input_tracking").value,
						style: 'label_detail'
					}
				);
				shipping_label.push(
					{
						text: "Shipment ID\n" + ship_requests[index].id,
						style: 'label_detail'
					}
				);
				shipping_label.push(
					{
						text: "Receiving Lookup",
						alignment: "right",
						style: 'qr_words_style'
					}
				);
				shipping_label.push(
					{ 
						qr: 'https://pw-fst.northcentralus.cloudapp.azure.com/FST/terminal_qr_receiving.php?sr_id=' + ship_requests[index].id + "&container=" + parseContainer(shipping_containers[i]),
						fit: '110',
						alignment: 'right',
						style: 'qr_style',
						version: 7
					}
				);

				if (i != shipping_containers.length - 1)
					shipping_label.push(
						{
							text: "",
							pageBreak: "after"
						}
					);

				// push to full content of shipping request
				all_shipping_labels.push(shipping_label);
			}

			// return all labels
			return all_shipping_labels;
		}

		// Simple function to strip just # off container
		function parseContainer(container){
			console.log(container.substr(10));
			return container.substr(10);
		}

		//on question mark click, open greensheet dialog
		$( "#search_for_greensheet" ).on('click', function(){
			$( "#greensheet_search" ).dialog({
				width: "auto",
				height: "auto",
				dialogClass: "fixedDialog",
			});
		});		
		
		//handles filtering quotes based on greensheet info
		function filter_quotes(){

			//get 2 search criteria's
			var name = u.eid("greensheet_search_project").value.toLowerCase(),
				num = u.eid("greensheet_search_num").value;

			//if both are blank, do nothing
			if (name == "" && num == ""){
				alert("Please enter some criteria.");
				return;
			}
			
			//remove previous entries
			document.querySelectorAll('.temp_gs_row').forEach(function(a){
				a.remove();
			})

			//search fst_grid for instances of either
			var matching = grid.filter(function (project) {
				return project.pname.toLowerCase().includes(name) && (project.vpProjectNumber == num || num == "");
				//return project.pname.toLowerCase().includes(name) && project.quoteStatus.includes("Award");
			});

			var table = u.eid("greensheet-search-table");

			//insert new row at bottom of table for each matching quote
			for (var i = 0; i < matching.length; i++){
				
				//new row
				var row = table.insertRow(-1);
				row.classList.add("temp_gs_row");
			
				//quote # button
				var cell = row.insertCell(0);
				cell.innerHTML = "<button onclick = 'add_to_greensheet(this.innerHTML)'>" + matching[i].quoteNumber + "</button>"

				//project name
				var cell = row.insertCell(1);
				cell.innerHTML = matching[i].pname;
				
			}

			//get screen height
			var screen_height = $( window ).height();

			//reset dialog
			$( "#greensheet_search" ).dialog({
				width: "auto",
				height: "auto",
				dialogClass: "fixedDialog",
				maxHeight: screen_height - 100
			});
		}

		//handles adding given quote # to greensheet
		function add_to_greensheet(quote){

			//add to field and close dialog
			u.eid("greensheet-quote").value = quote;
			$( "#greensheet_search" ).dialog('close');

		}

		//used to check if greensheet quote is valid
		//param 1 = quote we are looking for
		function check_greensheet_quote(quote){

			//look for index in grid
			var index = grid.findIndex(object => {
				return object.quoteNumber == quote;
			});

			if (index == -1){
				alert("The quote entered is not valid (must be an awarded quote in our system).");
				return false;
			}

			return true;

		}

		//handles running checks against greensheet info
		function check_greensheet (){
			
			//check quote #
			if (!check_greensheet_quote(u.eid("greensheet-quote").value))
				return;

			//decision variable
			var dec = true;
			
			//check required cells for greensheet
			document.querySelectorAll('.required-greensheet').forEach(function(a){
				if (a.value == ""){
					dec = false;
				}
			})
			
			//if we pass all checks, submit greensheet
			if (dec)
				submit_greensheet();
			else
				alert("Error, missing required information.")
		}
		
		//handles processing completes material orders
		function submit_greensheet (){
			
			//grab name, date, and pn
			var greensheet_quote = u.eid("greensheet-quote").value, 
				greensheet_name = u.eid("greensheet-name").value, 
				greensheet_date = u.eid("greensheet-date").value, 
				request = []
			
			//add parts and quantity to array
			for (var i = 1; i <= total_parts; i++){

				request.push({
					part: u.eid("extra_pn" + i).value,
					quantity: u.eid("extra_q" + i).value
				})
			}
			
			//if parts is empty, return error
			if (request.length == 0){
				alert("Error, no parts were found in the request, please enter the parts you will be taking.");
				return;
			}

			//initalize form data (will carry all form data over to server side)
			var fd = new FormData();

			//append info needed to update database
			fd.append('greensheet_quote', greensheet_quote);
			fd.append('greensheet_name', greensheet_name);
			fd.append('greensheet_date', greensheet_date);
			fd.append('request', JSON.stringify(request));
			fd.append('greensheet_shop', use_shop);

			//add tell
			fd.append("tell", "greensheet");
									
			$.ajax({
				url: 'terminal_warehouse_helper.php',
				type: 'POST',
				processData: false,
				contentType: false,
				data: fd,
				success: function (response) {
					
					if (response != ""){
						alert(response);
						
					}
					else{
						alert("Your greensheet has been submitted. Thank you.")
						window.location.reload();
					}
											
				}
			});
		}

		//send ajax request, returns updated list of material orders
		function check_orders(refresh = false){

			//update refreshing value (if this is auto, refresh is true = no mouse spinning)
			refreshing = refresh;
					
			//initalize form data (will carry all form data over to server side)
			var fd = new FormData();

			//append info needed to update database
			fd.append('shop', use_shop);
			
			//add arrays to send to server (saves any changed values)
			fd.append('target_ids', JSON.stringify(update_input));
			fd.append('allocations_mo', JSON.stringify(allocations_mo));
			
			//add arrays to update pq_detail
			fd.append('target_pq_ids', JSON.stringify(pq_detail_list));
			fd.append('pq_detail', JSON.stringify(pq_detail));

			//add arrays to update pq_detail
			fd.append('target_ship_ids', JSON.stringify(update_ship_request));
			fd.append('ship_requests', JSON.stringify(ship_requests));
			
			//add tell
			fd.append('tell', 'check_orders');
			
			$.ajax({
				url: 'terminal_warehouse_helper.php',
				type: 'POST',
				processData: false,
				contentType: false,
				data: fd,
				success: function (response) {
					
					//convert response to array[array[object{}]]
					hold_response = $.parseJSON(response);

					//update objects
					allocations_mo = hold_response[0];
					whh = hold_response[1];
					pq_detail = hold_response[2];
					ship_requests = hold_response[3];

					//refresh info
					refresh_queues();

					//clear update array
					update_input = [];
					pq_detail_list = [];
				}
			});
		}
		
		//global that identifies any elements that have changes (input applies for all allocations_mo, pq_detail applies for pq_detail)
		var update_input = [];
		var update_ship_request = [];
		var pq_detail_list = [];
		
		//Adds event listenerd to all input warehouse elements
		$(".warehouse_disable").on("change", function() {
			
			// check which type of request we are dealing with
			if (ship_req_id !== -1){
				update_ship_request = add_unique(update_ship_request, ship_req_id);

				// get index of id
				var index = ship_requests.findIndex(object => {
					return object.id == ship_req_id;
				});

				update_arrays(ship_requests[index]);

			}
			else if (current_index != -1){
				update_input = add_unique(update_input, allocations_mo[current_index].id);
				update_arrays(allocations_mo[current_index]);
			}
		});
		
		//updates array according to current warehouse inputs
		function update_arrays(object){
			
			//update object with user entered values
			object.carrier = u.eid("input_carrier").value;
			object.tracking = u.eid("input_tracking").value;
			object.receipt = u.eid("input_receipt").value;
			object.ship_cost = u.eid("input_ship_cost").value;
			object.picked_by = u.eid("input_picked_by").value;
			object.processed_by = u.eid("input_processed_by").value;
			object.checked_by = u.eid("input_checked_by").value;
			object.staged_loc = u.eid("input_staged_loc").value;
			object.warehouse_notes = u.eid("input_warehouse_notes").value;
			
			//check value to see what we need to save
			if (u.eid("input_staged").checked)
				object.staged_opt = 1;
			else
				object.staged_opt = 0;

			console.log(object);
						
		}
		
		//updates pq_detail array according to any changes (currently handles container # and notes)
		//param 1 = input element OR text area (wh_container or wh_notes)
		function update_pq_detail(target){
			
			//work back to id
			var td = target.parentNode;
			var tr = td.parentNode;
			var detail_id = parseInt(tr.id.substr(4));
			
			//get container and notes
			var container = tr.childNodes[8].childNodes[0].value;
			var notes = tr.childNodes[9].childNodes[0].value;
			
			//get index of pq_detail object
			var detail_index = pq_detail.findIndex(object => {
			  return object.id == detail_id;
			});
				
			//update object with user entered values
			pq_detail_list = add_unique(pq_detail_list, detail_id);
							
			//update globals
			pq_detail[detail_index].wh_container = container;
			pq_detail[detail_index].wh_notes = notes;
			
			//console.log(pq_detail[detail_index].wh_container);
			//console.log(pq_detail[detail_index].wh_notes);
			
		}
		
		//looks at an array and only adds it if it is unique
		function add_unique(arr, item){
			
			//search through array, if we find a match, return array, if not, push item and return array
			for (var i = 0; i < arr.length; i++){
				if (arr[i] == item)
					return arr;
			}
			
			arr.push(item);
			return arr;
			
		}
		
		 // Initalize widgets
		$( ".shape-bar, .pq" ).controlgroup();
		$( ".pq" ).controlgroup( {
		  direction: "vertical"
		} );

		$( ".toggle" ).on( "change", handleToggle );

		function handleToggle( e ) {
		  var target = $( e.target );




			var checked = target.is( ":checked" ),
			  value = $( "[name='pq']" )
				.filter( ":checked" )
				.attr( "data-" + target[ 0 ].id )
			$( ".shape" ).css( target[ 0 ].id, checked ? value : "" );

		}
		
		//global to hold all parts processed in greensheets
		var total_parts = 0;
		var new_part_info = [];
		var curr_part_index = null;
		
		//handles parts request form check (if both rows filled, add row)
		function form_check (targ){
			
			// get part & check for index
			var part = u.eid("extra_pn" + targ).value;
			var index = inventory.findIndex(object => {
				return object.partNumber.toLowerCase() == part.toLowerCase();
			});
			
			//if part is empty, ignore
			if (part == "")
				return;
			
			if (index == -1){
				
				alert("This part does not exist in the catalog");
				return;
				
			}
			else{

				//check status for inactive (if inactive return)
				if (inventory[index].status == "Discontinued"){
					alert(inventory[index].partNumber + " has been discontinued. Please select another part.");
					u.eid("extra_pn" + targ).value = "";
					return;
				}
				else if (inventory[index].status == "EOL"){
					alert("[WARNING] " + inventory[index].partNumber + " is end of life. You may still add this use this part but it may not be available in the near future.");
				}

				//we found a match, list out what we have in stock
				u.eid("extra_pn" + targ).classList.remove("required_error");
				u.eid("extra_stock" + targ).innerHTML = get_shop_total(inventory[index]);
			}

			//check to see if we need to add new row
			checkAdd(targ);
		}

		/**@author Alex Borchers
		 * Handles getting total inventory (from all shops)
		 * 
		 * @param part {object} matches row from invreport db table
		 * @returns total {int} total in current shop (use_shop)
		 */
		function get_shop_total(part){

			// referece use_shop global to see current shop
			var sum; 

			if (use_shop == "OMA")
				sum = parseInt(part['OMA-1']) + parseInt(part['OMA-2']) + parseInt(part['OMA-3']);
			else
				sum = parseInt(part[use_shop + '-1']) + parseInt(part[use_shop + '-3']);

			return sum;

		}
		
		//check to see if we need to add a new row
		function checkAdd (targ){
			//grab table and table length
			var table = u.eid("greensheet_table"), 
				count = table.rows.length;
			
			//set adj to adjust for headers
			var adj = 3; 
			
			if ((u.eid("extra_pn" + targ).value !== "" || u.eid("extra_q" + targ).value) !== "" && (targ + adj) == count)
				add_one(targ);
		}
		
		//add new row to parts request tables
		function add_one (targ){
			//increment extra_part by 1 so we know where to look for parts
			total_parts++;
			
			//move targ +1 for new IDs
			targ++;
			
			//grab table
			var table = u.eid("greensheet_table");
			
			//insert new row at bottom of table
			var row = table.insertRow(-1);
			
			//part Number
			var cell = row.insertCell(0);
			cell.innerHTML = '<input style="width: 400px" id="extra_pn' + targ + '" class="parts ui-autocomplete-input" onchange="form_check(' + targ + ')" autocomplete = "off">';
			
			//quantity
			var cell = row.insertCell(1);
			cell.innerHTML = '<input type="number" value="" style="width: 100px" min="0" id="extra_q' + targ + '" onchange="form_check(' + targ + ')">';
			
			//in stock 
			var cell = row.insertCell(2);
			cell.classList.add("stock_greensheet");
			cell.id = 'extra_stock' + targ;
			
		}

		//creates output file
		function export_complete_bom(){

			//file name
			var file_name = allocations_mo[current_index].project_id + " Complete BOM.xlsx";
			
			//initalize form data (will carry all form data over to server side)
			var fd = new FormData();
			
			//pass quote, type and filename over
			fd.append('pq_id', allocations_mo[current_index].pq_id);
			fd.append('file_name', file_name);
			
			//pass project info to form data
			fd.append('address1', "");
			fd.append('address2', "");
			fd.append('city', "");
			fd.append('state', "");
			fd.append('zip', "");
			fd.append('customer', "");
			fd.append('customer_pn', "");
			fd.append('cust_contact', "");
			fd.append('cust_phone', "");
			fd.append('cust_email', "");
			fd.append('date_submitted', "");
			fd.append('date_expired', "");

			//change the type so we can tell that we need to grab part information & project info on the server
			fd.append('type', 'warehouse');

			$.ajax({
				url: 'application_excelBOM.php',
				type: 'POST',
				processData: false,
				contentType: false,
				data: fd

			}).done(function(response){

				//download file and set interval to remove file
				u.eid('my_iframe').src = file_name;
				temp_interval = setInterval(remove_handler, 2000);

			});
		}

		//global that holds temporary interval (on remove_handler)
		var temp_interval;

		//handles downloading file after ajax is complete
		function remove_handler(){
			
			//get file name
			var file_name = allocations_mo[current_index].project_id + " Complete BOM.xlsx";

			//clear interval
			clearInterval(temp_interval);

			//call seperate ajax to remove target file
			$.ajax({
				type : "POST",  //type of method
					url  : "removeFile.php",  //your page
					data : { targ : file_name }
			})
		}

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
				
			//hide table
			u.eid("mo_details").style.display = 'none';
				
			//if we are going back to the open tab, set the filters for closed back to nothing (so we don't re-add these every time)
			if (pageName == "Open"){
				u.eid("start_date").value = null;
				u.eid("end_date").valueAsDate = new Date();
				u.eid("search_project").value = null;
				u.eid("search_MO").value = null;
			}
				
			//click default (unclicks all visible buttons)
			u.eid("pq-default").click();
				
		}

		//windows onload
		window.onload = function () {
			//set interval to check orders every 60 seconds
			setInterval(function(){
				//check_orders()}, 60000)
				check_orders(true)}, 60000)
			
			//add event listener logic to notify the user before they exit the site if they have potential unsaved data
			window.addEventListener("beforeunload", function (e) {
				
				if (update_input.length == 0) {
					return undefined;
				}

				var confirmationMessage = 'It looks like you have been editing something. '
										+ 'If you leave before saving, your changes will be lost.';

				(e || window.event).returnValue = confirmationMessage; //Gecko + IE
				return confirmationMessage; //Gecko + Webkit, Safari, Chrome etc.
			});
			
			// Get the element with id="defaultOpen" and click on it
			u.eid("defaultOpen").click();
			
			//set end date and greensheet pick up date to todays date
			u.eid("end_date").valueAsDate = new Date();
			u.eid("greensheet-date").valueAsDate = new Date();
			
			// refresh queues
			refresh_queues();

			// Convert staged locations to jquery styled selements
			$( function(){
				$( ".staging_location" ).checkboxradio({
				icon: false
				});
			});

			// used to quickly pull up shipping label
			//u.eid("sr-label-18").click();
			//u.eid("prep_shipment_button").click();
			
		}

		//used to toggle wait for mouse
		$(document).ajaxStart(function () {
			//add mouse spinning
			if (!refreshing)
				waiting('on');
		});

		$(document).ajaxStop(function () {

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
$mysqli -> close();

?>
	
</html>