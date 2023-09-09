<?php

// load in dependencies
session_start();
include('phpFunctions.php');
include('phpFunctions_html.php');
include('constants.php');
include('PHPClasses/Notifications.php');

// load in DB configurations
require_once 'config.php';

// Save current site so we can return after log in
$_SESSION['returnAddress'] = "https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

// Make sure user has privileges
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

//check session (phpFunctions.php)
sessionCheck($fstUser['accessLevel']);

//if user is deployment, hide $ values
$deployHide = "";
if ($fstUser['accessLevel'] == "Deployment")
	$deployHide = "none";
	
//reset error message
unset($_SESSION['errorMessage']);

//init objects/arrays
$solution_type = [];
$typeQ = "SElECT * FROM general_type;";
$result = mysqli_query($con, $typeQ);
while($rows = mysqli_fetch_assoc($result)){
	array_push($solution_type, $rows);
}

//load in grid
$grid = [];
$query = "SELECT * from fst_grid order by lastUpdate DESC;";

// if GET['customer'] is set in URL, adjust the project that show up
if (isset($_GET["customer"]))
	$query = "SELECT * from fst_grid WHERE customer = '" . mysql_escape_mimic($_GET["customer"]) . "' order by lastUpdate DESC;";

// if GET['location'] is set in URL, adjust the project that show up
if (isset($_GET["location"]))
	$query = "SELECT * from fst_grid WHERE location_name = '" . mysql_escape_mimic($_GET["location"]) . "' order by lastUpdate DESC;";

$result = mysqli_query($con, $query);
while($rows = mysqli_fetch_assoc($result)){
	array_push($grid, $rows);
}

//grab fst_grid columns (used for power searches)
$grid_columns = [];
$query = "DESCRIBE fst_grid;";
$result = mysqli_query($con, $query);

while($rows = mysqli_fetch_assoc($result)){
	array_push($grid_columns, $rows['Field']);
}

// Trim market
$use_market = [];
foreach($market as $m){
	array_push($use_market, substr($m, 0, 4));
}

?>

<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=0.8">
<meta name="google-signin-client_id" content="573761357198-hin7ae7q19qgvoab7t0781b41530546g.apps.googleusercontent.com">
<link rel="shortcut icon" type="image/x-icon" href="images/P-Logo.png" />
<link rel="stylesheet" href="stylesheets/element-styles.css?<?= $version; ?>1">
<link rel="stylesheet" href="stylesheets/dashboard-styles.css?<?= $version; ?>">
<link rel="stylesheet" href="stylesheets/dashboard-styles-mobile.css?<?= $version; ?>1">
<link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css'> 
<link href = "stylesheets/jquery-ui-themes-1.13.0/themes/ui-lightness/jquery-ui.css" rel = "stylesheet">
<title>Home (v<?= $version ?>) - Pierson Wireless</title>
	
<style>

	/**styles search divs */
	.search_div{
		padding: 0em 2em 1em 0em;
		float: left;
	}
	.search_h4{
		margin-bottom: 10px;
	}
	.search_table input[type=checkbox]{
		-ms-transform: scale(1.2);
		-webkit-transform: scale(1.2);
		transform: scale(1.2);
	}
	.search_table td{
		border: none;
	}
	
	/** style widths of columns & input fields */
	.quote_button{
		width: 100%;
	}
	.input_value{
		display: <?= $deployHide?>;
	}

	/**shows elements that hide for deployment */
	.hide_for_deployment{
		display: <?= $deployHide?>;
	}
	
</style>
</head>

<body>

	<?php

		//define array of names & Id's to generate headers
		$header_names = ['Home'];
		$header_ids = ['home'];

		//pass to php function to create navigation bars
		echo create_navigation_bar($header_names, $header_ids, "", $fstUser);

	?>

	<div id = 'home' class ='tabcontent'>

		<h1> Welcome <?= $fstUser['fullName'] ?> </h1>
		<a href = 'home.php?desktop=on' style = 'margin-top:-1em;'>Desktop View</a>
						
		<!First iteration of Quotes table>			
		<fieldset>
			<legend><h3>Job Filters</h3></legend>

			<div style = 'clear:both'>
				<input id = 'power_search' placeholder='Search all project fields' > <button onclick = 'filter_table_body()' form = ''>&#128269;</button>
				<br>
				Search limit 
				<select id = 'filter_limit' onchange = 'filter_table_body()' class = 'custom-select'>
					<option>50</option>
					<option>100</option>
					<option>200</option>
				</select>
			</div>

			<div class = 'search_div'>
				<h4 class = 'search_h4'>Quote Status</h4>	
				<table class = 'search_table'>
					<tr>
						<td>
							<input type="checkbox" id="created" onclick="filter_table_body()">
							<label for="created">Created</label> 
						</td>
						<td>
							<input type="checkbox" id="submitted" onclick="filter_table_body()">
							<label for="submitted">Submitted</label>
						</td>
					</tr>
					<tr>
						<td>
							<input type="checkbox" id="awarded" onclick="filter_table_body()">
							<label for="awarded">Awarded</label> 
						</td>
						<td>
							<input type="checkbox" id="budget" onclick="filter_table_body()">
							<label for="budget">Budget</label>
						</td>
					</tr>
					<tr>
						<td>
							<input type="checkbox" id="archived" onclick="filter_table_body()">
							<label for="archived">Archived</label>
						</td>
						<td>
							<input type="checkbox" id="dead" onclick="filter_table_body()">
							<label for="dead">Dead</label>
						</td>
					</tr>
					<tr>
						<td>
							<input type="checkbox" id="forecast" onclick="filter_table_body()">
							<label for="forecast">Forecast</label> 
						</td>
					</tr>
				</table>
			</div>
			<div class = 'search_div'>
				<h4 class = 'search_h4'>Region</h4>	
				<table class = 'search_table'>
					<tr>
						<td>
							<input type="checkbox" id="24" onclick="filter_table_body()">
							<label for="24">West</label>
						</td>
					</tr>
					<tr>
						<td>
							<input type="checkbox" id="3" onclick="filter_table_body()">
							<label for="3">Central</label>
						</td>
					</tr>
					<tr>
						<td>
							<input type="checkbox" id="56" onclick="filter_table_body()">
							<label for="56">East</label>
						</td>
					</tr>
				</table>
			</div>
		</fieldset>

		<div class = 'sub_header'>
			<h3 style = 'margin-left: 7px;'>
				All Projects 
			</h3>
		</div>
		
		<a id="hold_download" style="display:none"></a>
			
		<table id = "main_table" class = "homeTables all_class">
			<thead>
				<tr class = 'searchBars sticky_thead all_searchBars'>
					<td class = 'col_quote_num'><input type="text" class = 'inputs' placeholder="Quote #" id = 'search_quote'></td>
					<td class = 'col_project_name'><input type="text" class = 'inputs' placeholder = "Project Name" id = 'search_name'></td>
					<td class = 'col_market'>
						<select class='custom-select inputs' placeholder = "Market" id = 'search_market'>
							<option></option>
							<?= create_select_options($use_market);?>
						</select> 
					</td>
					<td class = 'col_customer'><input type="text" class = 'inputs' placeholder="Customer" id = 'search_customer'></td>
				</tr>
				<tr>
					<th class = 'col_quote_num'> Quote # </th>
					<th class = 'col_project_name'> Project Name </th>
					<th class = 'col_market'> Market </th>
					<th class = 'col_customer'> Customer </th>
				</tr>
			</thead>
			<tbody>
								
			<?php

				// only load first 50 OR to the # of results in grid
				$length = min(sizeof($grid), 50);
				for ($j = 0; $j < $length; $j++){

					?>

					<tr class = 'table_row'>
						<td class="col_quote_num">
							<p class="quotes" style="display: none;"><?= $grid[$j]['quoteNumber']; ?></p>
							<a href="application.php?quote=<?= $grid[$j]['quoteNumber']; ?>">
								<button class="quote_button"><?= $grid[$j]['quoteNumber']; ?></button>
							</a>
						</td>
						<td class = 'col_project_name'><p> <?= $grid[$j]['location_name'] . " " . $grid[$j]['phaseName']; ?> </p></td>
						<td class = 'col_market'><p> <?= substr($grid[$j]['market'], 0, 4); ?> </p></td>
						<td class = 'col_customer'><p> <?= $grid[$j]['customer']; ?> </p></td>
					</tr>

					<?php
				}
			?>
			
			</tbody>
		</table>

		<div style = 'padding-bottom: 5em;'> 
			<!-- extra space below table -->
		</div>
		
	</div>
	
	<!-- used for ajax -->
	<script	src = "https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
	<script src="https://apis.google.com/js/platform.js?onload=init" async defer></script>

	<!-- internally defined js files -->
	<script src = "javascript/utils.js"></script>
	<script src = "javascript/accounting.js"></script>
	<script src="javascript/fst_js_functions.js"></script>
	<script src="javascript/js_helper.js?<?= $version ?>-2"></script>

	<!-- external libary for jquery functionallity -->
	<script src="https://code.jquery.com/jquery-1.12.4.js"></script>
	<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
	
	<script>
		
		//Namespace
		var z = {}
		
		//used to load in projects from grid
		const grid = <?= json_encode($grid); ?>;
		
		//pass info that helps decide grid layout
		const user_info = <?= json_encode($fstUser); ?>,
			  grid_columns = <?= json_encode($grid_columns); ?>;

		//handles outputting currently displayed quotes as CSV
		function download_projects(){

			//go through quote_list and add to csv
			let csvContent = "data:text/csv;charset=utf-8,";
			
			//init arrays to handle CSV creation (body will match columns in fst_grid)
			body = ['quoteNumber','quote_type','co_number','vpProjectNumber','vpContractNumber','customer','location_name','phaseName','designer','projectLead','quoteCreator','fs_engineer','asd_member','customer_pm','projectType','subType','market','quoteStatus','mPrice','sPrice','totalPrice','customer_id','oem','remotes','antennas','lfCable','lfFiber','environment','sqft','sectorZones','submitUser','submitDate','sub_date','exp_date','projected_po_date','timestamp_submitted','timestamp_awarded','timestamp_budgetary','timestamp_dead'];

			// add headers to CSV
			for (var i = 0; i < body.length; i++){

				//handle last entry differently
				if (i == body.length - 1)
					csvContent += body[i] + "\r\n";
				else 
					csvContent += body[i] + ",";

			}
			
			//if current filter is empty, assume we have not searched for anything yet
			if (current_filtered.length == 0)
				current_filtered = grid;
						
			//loop through bill of materials and add each part
			for (var i = 0; i < current_filtered.length; i++){

				//remove odd characters from all fields
				for (var j = 0; j < body.length; j++){

					//create temp field entry (w/ scrubbed info)
					var temp = scrub_string(current_filtered[i][body[j]]);

					//handle last entry differently
					if (j == body.length - 1)
						csvContent += temp + "\r\n";
					else 
						csvContent += temp + ",";

				}
			}
			
			//set encoded uri for download, push to link, name and force download
			var encodedUri = encodeURI(csvContent);
			var link = u.eid("hold_download");
			link.setAttribute("href", encodedUri);			
			link.setAttribute("download", "project_dump.csv");
			link.click();
		}

		//handle sanitizing string
		function scrub_string(targ){
			
			//check for blanks
			if (targ == "" || targ == null)
				return " ";
				
			//used to remove unfriendly characters
			var regexp = new RegExp('#','g');
			targ = targ.replace(regexp, '');
			targ = targ.replace(/,/g, ';');
			targ = targ.replace('\r', '');
			targ = targ.replace(/[\r\n]/gm, '');
			targ = targ.trim();
			
			return targ;
		}

		//saves current filtered projects for export
		var current_filtered = [];

		//function used to filter table (similar to init_table_body, created better)
		//only currently in use for opt=all
		function filter_table_body(){

			//set limit on # of entries
			var limit = u.eid("filter_limit").value, current_shown = 0;

			//get potential filters
			var quote = u.eid("search_quote").value,
				value = u.eid("search_value").value,
				project_name = u.eid("search_name").value,
				market = u.eid("search_market").value,
				customer = u.eid("search_customer").value;

			//if value includes $ OR comma, remove it
			value = value.replaceAll("$", "").replaceAll(",", "");
				
			//filter grid on all potential filters
			var filtered_grid = grid.filter(function (p) {
				return (quote == "" || p.quoteNumber.includes(quote)) &&									//checks quote #
						(value == "" || p.totalPrice.includes(value)) &&									//checks value
						(project_name == "" || (p.location_name.toLowerCase() + " " + p.phaseName.toLowerCase()).includes(project_name.toLowerCase())) &&	//checks project_name
						(market == "" || p.market.substr(0, 4) == market) &&								//checks market
						(customer == "" || p.customer.toLowerCase().includes(customer.toLowerCase()))
			});

			//filter on power_search if applicable
			if (u.eid("power_search").value != ""){
				filtered_grid = filtered_grid.filter(function (p) {
					return power_search_handler(p)
				});
			}

			// run function to check which checkbox filters user has selected
			filtered_grid = checkbox_filter(filtered_grid, "quoteStatus");
			filtered_grid = checkbox_filter(filtered_grid, "region");

			//update current filtered (used for export)
			current_filtered = filtered_grid;

			//remove previous entries (if they exist)
			document.querySelectorAll('.table_row').forEach(function(a){
				a.remove()
			})

			//get max of filtered_grid length and limit
			if (limit == "All")
				limit = filtered_grid.length;
			else
				limit = Math.min(parseInt(limit), filtered_grid.length);

			//loop through filtered_grid till we reach our limit
			for (var i = 0; i < limit; i++){
				add_home_row(filtered_grid[i], all_order, "All Projects", "white");
			}
		}

		/**
		 * Handles sorting projects based on the checkbox filters defined
		 * @author Dima Abdo
		 * Edited by Alex Borchers (3/6/2023)
		 * 
		 * @param {array[object]} 	filtered_grid 	(matches fst_grid)
		 * @param {string}			type			(quoteStatus / region)
		 * @returns {array[object]} return_grid		(drill-down of filtered_grid)
		 */
		function checkbox_filter(filtered_grid, type){

			// initialize objects to be used throughout function
			var return_grid = []; 
			var keys = [];
			var current_filter = [];
			var searching = false; 		// keep track if at least 1 <input> is checked

			// set keys based on type
			if (type == "quoteStatus")
				keys = ["created", "submitted", "awarded", "budget", "archived", "dead", "forecast"];
			else if (type == "region")
				keys = ["24", "3", "56"];

			// loop through keys & check if user has checked the input field
			for (var i = 0; i < keys.length; i++){

				// if user has clicked, add to return grid
				if (u.eid(keys[i]).checked){

					// set searching to true
					searching = true;

					// store current filter & add to return array
					if (type == "quoteStatus")
						current_filter = filtered_grid.filter(function(quotes) {
							return quotes.quoteStatus.toLowerCase().includes(keys[i])
						})
					else if (type == "region")
						current_filter = filtered_grid.filter(function(quotes) {
							return keys[i].includes(quotes.market.substr(0, 1))
						})
					return_grid = return_grid.concat(current_filter);
				}
			}

			// if nothing is selected, return the entire list
			if (return_grid.length == 0 && !searching){
				return filtered_grid;
			}
			
			// sort grid by last update
			return_grid.sort((a, b) => b["lastUpdate"].localeCompare(a["lastUpdate"]));

			// return results
			return return_grid;

		}

		//handles power_search conditional statement
		function power_search_handler(grid_row){

			var power_value = u.eid("power_search").value.toLowerCase().trim();

			//if no value, return true
			if (power_value == "" || power_value === null)
				return true;

			//otherwise, search through fst_grid columns for a match
			for (var i = 0; i < grid_columns.length; i++){

				if (grid_row[grid_columns[i]] === null)
					grid_row[grid_columns[i]] = "";

				//check for match
				if (grid_row[grid_columns[i]].toLowerCase().includes(power_value))
					return true;
			}

			//check for exceptions (ex. project name + phase name = full project name)
			var full_name = grid_row['location_name'].toLowerCase() + " " + grid_row['phaseName'].toLowerCase();
			if (full_name.includes(power_value))
				return true;

			//make it to the end = no match
			return false;

		}

		//global used to guide table creation
		const all_order = ['quote_num', 'project_name','market', 'customer'];
					
		//handles adding a table row on home screen
		//param 1 = object in grid that we want to add row for
		//param 2 = headers to guide table creation
		//param 3 = type of row we need to add (dtermines if we need input fields)
		//param 4 = background color for row (to denote if supervisor is responsible for it)
		function add_home_row(grid_row, heads, type, background){
			
			//get table/create new row
			var table = u.eid("main_table").getElementsByTagName('tbody')[0];
			var row = table.insertRow(-1);
			row.classList.add("table_row");
			//row.style.backgroundColor = background;

			//loop through heads and add to table
			for (var i = 0; i < heads.length; i++){
				add_row_cell(grid_row, heads[i], type, i, row);
			}
		}
		
		//handles adding cell to table row
		//param 1 = object in fst_grid to be added
		//param 2 = header to guide cell creation
		//param 3 = type of row we need to add (dtermines if we need input fields)
		//param 4 = cell index (what level we are creating)
		//param 5 = row to add to 
		function add_row_cell(grid_row, head, type, cell_index, row){

			//create cell based on index
			var cell = row.insertCell(cell_index);
			
			//depedning on head, add info
			if (head == "quote_num"){
				
				var p = document.createElement("p");
				p.style.display = "none";
				p.innerHTML = grid[cell_index].quoteNumber;
				p.classList.add("quotes");
				var a = document.createElement("a");
				a.href = "application.php?quote=" + grid[cell_index].quoteNumber;
				a.innerHTML ="<button class = 'quote_button'>" + grid[cell_index].quoteNumber + "</button>"
				
				cell.appendChild(p);
				cell.appendChild(a);
			}
			else if (head == "project_name"){
				var p = document.createElement("p");
				p.innerHTML = grid_row.location_name + " " + grid_row.phaseName;
				cell.appendChild(p);
			}
			else if (head == "value"){
				var p = document.createElement("p");
				
				//if all just list out value 
				if (type == "All Projects")
					p.innerHTML = accounting.formatMoney(grid_row.totalPrice);
				//otherwise, check to see if we have a contract for this project
				else if (grid_row.quoteStatus == 'Awarded (PO Received)')
					p.innerHTML = "(C) " + accounting.formatMoney(grid_row.totalPrice);
				else
					p.innerHTML = accounting.formatMoney(grid_row.totalPrice);
				
				cell.appendChild(p);
			}
			else if (head == "market"){
				var p = document.createElement("p");
				
				if (type == "All Projects"){
					p.innerHTML = grid_row.market.substr(0, 4);
					cell.appendChild(p);
				}
				else{
					if (grid_row.market !== null)
						p.innerHTML = grid_row.market.substring(0, 4);
					
					cell.appendChild(p);
				}
			}
			else if (head == "customer"){
				var p = document.createElement("p");
				p.innerHTML = grid_row.customer;
				cell.appendChild(p);
			}

			//add class
			cell.classList.add("col_" + head);
			
		}

		// handles changing tabs
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

		//windows onload
		window.onload = function () {
						
			// Get the element with id="defaultOpen" and click on it
			u.eid("defaultOpen").click();

			//add event listeners to input elements inside all_searchBars sticky_thead row
			$('.searchBars select').on("change", function(){
				filter_table_body();
			});

			$('.searchBars input').on("keyup", function(){
				filter_table_body();
			});

			// update profile info
			u.eid("search_market").value = user_info['profileMarket'];

			// if 1 of the defaults is filled out, re-sort table
			if (user_info['profileMarket'] != null && user_info['profileMarket'] != "")
				filter_table_body();
			
		}
		
		// show mouse waiting while sending ajax request
		$(document).ajaxStart(function () {
		  waiting('on');
		});
		
		$(document).ajaxStop(function () {
		  waiting('off');
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