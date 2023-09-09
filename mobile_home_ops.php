<?php

/****************
 * 
 * Created by: Alex Borchers (5/5/23)
 * This file is intended only for mobile use. If a user accessing the ops dashboard, they are redirected here
 * 
 *****************/

// load in dependencies
session_start();
include('phpFunctions.php');
include('phpFunctions_views.php');
include('phpFunctions_html.php');
include('constants.php');
include('PHPClasses/Notifications.php');

// load in DB configurations
require_once 'config.php';

// Save current site so we can return after log in
$_SESSION['returnAddress'] = "https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

// Make sure user has privileges
// If employeeID is set in session, create new instance of user
if (isset($_SESSION['employeeID']))
	$user = new User($_SESSION['employeeID'], $con);
else
	$user = new User(-1, $con);

// Check session (phpFunctions.php)
sessionCheck($user->info['accessLevel']);

// call custom function to get operations needed info
$ops_views = get_operations_views($con, $user);

// get list of operations services that can be requested
$ops_services = [];
$query = "SELECT * FROM general_ops_task ORDER BY priority;";
$result = mysqli_query($con, $query);
while ($rows = mysqli_fetch_assoc($result)) {
	array_push($ops_services, $rows);
}

//init objects/arrays
$ops_tasks = [];
$query = "SELECT * FROM general_fst_status ORDER BY priority;";
//$query = "SELECT task as 'status', code, dashboard FROM general_ops_task ORDER BY priority;";
$result = mysqli_query($con, $query);
while ($rows = mysqli_fetch_assoc($result)) {
	array_push($ops_tasks, $rows);
}

//grab fst_grid columns (used for power searches)
$grid_columns = [];
$query = "DESCRIBE fst_grid;";
$result = mysqli_query($con, $query);

while ($rows = mysqli_fetch_assoc($result)) {
	array_push($grid_columns, $rows['Field']);
}

// Trim market
$use_market = [];
foreach ($market as $m) {
	array_push($use_market, substr($m, 0, 4));
}

?>

<!doctype html>
<html>

<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0">
	<meta name="google-signin-client_id" content="573761357198-hin7ae7q19qgvoab7t0781b41530546g.apps.googleusercontent.com">
	<link rel="shortcut icon" type="image/x-icon" href="images/P-Logo.png" />
	<link rel="stylesheet" href="stylesheets/element-styles.css?<?= $version; ?>1">
	<link rel="stylesheet" href="stylesheets/mobile-element-styles.css?<?= $version; ?>1">
	<link rel="stylesheet" href="stylesheets/dashboard-styles.css?<?= $version; ?>">
	<link rel="stylesheet" href="stylesheets/dashboard-styles-mobile.css?<?= $version; ?>1">
	<link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css'>
	<link href="stylesheets/jquery-ui-themes-1.13.0/themes/ui-lightness/jquery-ui.css" rel="stylesheet">
	<title>Mobile Ops Dashboard (v<?= $version ?>) - Pierson Wireless</title>

	<style>
		/**styles search divs */
		.search_div {
			padding: 0em 2em 1em 0em;
			float: left;
		}

		.search_h4 {
			margin-bottom: 10px;
		}

		.search_table input[type=checkbox] {
			-ms-transform: scale(1.2);
			-webkit-transform: scale(1.2);
			transform: scale(1.2);
		}

		.search_table td {
			border: none;
		}

		/** style widths of columns & input fields */
		.quote_button {
			width: 100%;
		}

		.input_value {
			display: <?= $deployHide ?>;
		}

		/**shows elements that hide for deployment */
		.hide_for_deployment {
			display: <?= $deployHide ?>;
		}

		/* The container <div> - needed to position the dropdown content */
		.quote {
			position: relative;
			display: inline-block;
		}

		/* quote Content (Hidden by Default) */
		.quote-content {
			display: none;
			position: absolute;
			background-color: #f1f1f1;
			min-width: 160px;
			box-shadow: 0px 8px 16px 0px rgba(0, 0, 0, 0.2);
			z-index: 200;
		}

		/* Links inside the quote */
		.quote-content a {
			color: black;
			padding: 12px 16px;
			text-decoration: none;
			display: block;
			border: 1px solid #c1c1c1;
		}

		/* Change color of quote links on hover */
		.quote-content a:hover {
			background-color: #ddd;
		}

		/* Show the quote menu on hover */
		.quote:hover .quote-content {
			display: block;
		}

		@media screen and (max-width: 768px) {
			.pw_logo_wrapper {
				margin-right: 7.0em !important;
			}

			.nav-bar {
				height: 44px;
			}
		}
	</style>
</head>

<body>

	<?php

	//define array of names & Id's to generate headers
	$header_names = ['Ops Dashboard'];
	$header_ids = ['ops_dashboard'];

	//pass to php function to create navigation bars
	echo create_navigation_bar($header_names, $header_ids, "", $user->info);

	?>

	<div id='ops_dashboard' class='tabcontent' style='padding-top: 3em;'>

		<h2> Operations Dashboard - <?= $_SESSION['firstName'] ?> <?= $_SESSION['lastName'] ?> </h2>

		<?php

		//initalize button as "show all project" and adjust accordingly
		$opt = "";

		//init groups and headers
		$groups = [];
		$groups_header = [];
		$groups_class = [];
		$group_codes = [];

		// check if user is designated as manager, this will determine if they need to see unassigned queues
		if ($user->info['manager'] == "checked") {

			// add group for pre-installation jobs
			array_push($groups, "Pre-Installation");
			array_push($groups_header, "Pre-Installation");
			array_push($groups_class, "pre_install_class");
			array_push($group_codes, "pre_install");

			//add unassigned groups based on values in fstUser['assigned_markets']
			//assigned markets is in fst_users table, and will hold combination of markets (2001,2002,...,6003)

			//check for west (W)
			if (preg_match('(2001|2002|4001)', $user->info['assigned_markets']) === 1) {

				array_push($groups, "Pre-Installation");
				array_push($groups_header, "Unassigned (West)");
				array_push($groups_class, "unassigned_class");
				array_push($group_codes, "unassigned_west");
			}

			//check for central (C)
			if (preg_match('(3001|3002|3004)', $user->info['assigned_markets']) === 1) {

				array_push($groups, "Pre-Installation");
				array_push($groups_header, "Unassigned (Central)");
				array_push($groups_class, "unassigned_class");
				array_push($group_codes, "unassigned_central");
			}

			//check for east (E)
			if (preg_match('(5001|5002|6001|6002|6003)', $user->info['assigned_markets']) === 1) {

				array_push($groups, "Pre-Installation");
				array_push($groups_header, "Unassigned (East)");
				array_push($groups_class, "unassigned_class");
				array_push($group_codes, "unassigned_east");
			}
		}

		// add services for ops_tasks
		foreach ($ops_services as $service) {

			// only push to $groups IF dashboard is approved
			array_push($groups, $service['task']);
			array_push($groups_header, $service['task']);
			array_push($group_codes, $service['code']);
			array_push($groups_class, "standard_class");
		}

		// loop through ops_tasks read in earlier in the file
		foreach ($ops_tasks as $task) {

			// only push to $groups IF dashboard is approved
			if ($task['dashboard'] == "Y") {
				array_push($groups, $task['status']);
				array_push($groups_header, $task['status']);
				array_push($group_codes, $task['code']);
				array_push($groups_class, "standard_class");
			}
		}

		//default table heights if no changes made
		for ($i = 0; $i < sizeof($group_codes); $i++) {
			$table_heights[$group_codes[$i]] = "400";
		}


		//update max-height for multiple table view
		$max_height = "max-height: 400px";

		//echo out power search bar
		echo "<input id = 'power_search' placeholder='Search all project fields' > <button onclick = 'search_table_body()' form = ''>&#128269;</button>";

		//array used to determine determine PW personell columns (reference fst_grid)
		$pw_personnel = array("designer", "projectLead", "quoteCreator", "programCoordinator", "opsLead");

		?>

		<div style='margin-bottom: 1em;'>
			<button onclick='expand_all_tables(true)'>Expand All</button><button onclick='expand_all_tables(false)'>Collapse All</button>
		</div>

		<?php

		//loop through group filters and list projects as such
		for ($i = 0; $i < sizeof($groups); $i++) {

			// Open sub_header <div> for stylying
			echo "<div class = 'sub_header' id = 'sub_header" . $i . "' onclick = 'expand_dashboard_table(this, " . $i . ")'>";

			//if complete, denote last 30 days
			if ($groups_header[$i] == "Complete") {
				echo '<h3>';
				echo '<span class="ui-accordion-header-icon ui-icon ui-icon-triangle-1-e"></span>';
				echo $groups_header[$i] . " (last 30 days)  (<span id = '" . $group_codes[$i] . "_count'></span>)</h3>";
				$groups_class[$i] = "complete_class";
			} else {
				echo '<h3 class = "ops_expand">';
				echo '<span class="ui-accordion-header-icon ui-icon ui-icon-triangle-1-e"></span>';
				echo $groups_header[$i] . " (<span id = '" . $group_codes[$i] . "_count'></span>)</h3>";
			}


			// Close <div>
			echo "</div>";

		?>

			<table id="searchTable<?= $i; ?>" class="homeTables <?= $groups_class[$i]; ?> hide">

				<thead>
					<tr class='searchBars sticky_thead all_searchBars'>
						<td class='col_quote_num'><input type="text" class='inputs' placeholder="Quote #" id='search_quote'></td>
						<td class='col_project_name'><input type="text" class='inputs' placeholder="Project Name" id='search_name'></td>
						<td class='col_market'>
							<select class='custom-select inputs' placeholder="Market" id='search_market'>
								<option></option>
								<?= create_select_options($use_market); ?>
							</select>
						</td>
						<td class='col_customer'><input type="text" class='inputs' placeholder="Customer" id='search_customer'></td>
					</tr>
					<tr>
						<th class='col_quote_num'> Quote # </th>
						<th class='col_project_name'> Project Name </th>
						<th class='col_market'> Market </th>
						<th class='col_customer'> Customer </th>
					</tr>
				</thead>

				<?php

				//header used for unnassigned projects
				if (str_contains($groups_header[$i], "Unassigned")) {

				?>

				<?php

					//headers otherwise
				} elseif ($groups_header[$i] == "Pre-Installation") {

				?>

				<?php

					//headers otherwise
				} else {

				?>

				<?php

				}

				?>
				<tbody class='dashboard_tbody'>
					<!--TBODY TO BE FILLED BY JS (SEE init_table_body())!-->
				</tbody>
			</table>

		<?php

			//close for loop
		}

		?>

	</div>

	<!-- used for ajax -->
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
	<script src="https://apis.google.com/js/platform.js?onload=init" async defer></script>

	<!-- internally defined js files -->
	<script src="javascript/utils.js"></script>
	<script src="javascript/accounting.js"></script>
	<script src="javascript/fst_js_functions.js"></script>
	<script src="javascript/js_helper.js?<?= $version ?>-2"></script>

	<!-- external libary for jquery functionallity -->
	<script src="https://code.jquery.com/jquery-1.12.4.js"></script>
	<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>

	<script>
		//Namespace
		var z = {}

		//pass info that helps decide grid layout
		const grid = <?= json_encode($ops_views['grid']); ?>,
			tech_assignments = <?= json_encode($ops_views['tech_assignments']); ?>,
			user_info = <?= json_encode($user->info); ?>,
			supervising = <?= json_encode($user->supervising); ?>,
			grid_columns = <?= json_encode($grid_columns); ?>,
			groups = <?= json_encode($groups); ?>,
			groups_header = <?= json_encode($groups_header); ?>,
			group_codes = <?= json_encode($group_codes); ?>,
			pw_personnel = ["designer", "projectLead", "quoteCreator", "programCoordinator", "opsLead"],
			markets = <?= json_encode($market); ?>;

		//init filters as a global
		var filters = [
			[],
			[],
			[],
			[]
		];
		var group_filters = [];

		//push to number of tables we may need
		group_filters.push(filters);
		group_filters.push(filters);
		group_filters.push(filters);
		group_filters.push(filters);
		group_filters.push(filters);
		group_filters.push(filters);
		group_filters.push(filters);
		group_filters.push(filters);

		//listen to inputs on change
		$(".inputs").on("change keyup", function() {

			//grab table id
			var table_id = this.parentNode; //td
			table_id = table_id.parentNode; //tr
			table_id = table_id.parentNode; //thead
			table_id = table_id.parentNode; //table
			table_id = parseInt(table_id.id.replace(/[a-z]/gi, ""));

			//grab rows and column number 
			var $rows = $('#searchTable' + table_id + ' tbody tr');
			//var col = this.id.replace(/[a-z]/gi, "") - 1;
			var col = $(this).closest("td").index();

			group_filters[table_id][col] = $(this).val().trim().replace(/ +/g, ' ').toLowerCase().split(",").filter(l => l.length);
			$rows.show()

			if (group_filters[table_id].some(f => f.length)) {
				$rows.filter(function() {
					var texts = $(this).children().map((i, td) => $(td).text().replace(/\s+/g, ' ').toLowerCase()).get();
					return !texts.every((t, col) => {
						return group_filters[table_id][col].length == 0 || group_filters[table_id][col].some((f, i) => t.indexOf(f) >= 0);
					})
				}).hide();
			}

			// Apply styles to new table
			$('#searchTable' + table_id + ' tbody tr:visible').removeClass("even odd").filter(":even").addClass("even").end().filter(":odd").addClass("odd");
		});

		//saves current filtered projects for export
		var relevant_quotes = [];

		//function to init table body
		function init_table_body() {

			//reset relevant quotes
			relevant_quotes = [];

			//remove previous entries (if they exist)
			document.querySelectorAll('.table_row').forEach(function(a) {
				a.remove()
			})

			//set user_name based on const user (fst_user entry)
			var user_name = user_info['firstName'] + " " + user_info['lastName'];

			//add new rows
			for (var i = 0; i < grid.length; i++) {

				// get index in tech assignments to see if applicable
				var tech_tell = tech_assignments.findIndex(object => {
					return object.quoteNumber == grid[i].quoteNumber && object.tech == user_name;
				});

				//loop through groups, add depending on match
				for (var j = 0; j < groups.length; j++) {

					//init background color
					var background = "white";

					if (grid[i].market == null)
						grid[i].market = "";

					if (grid[i].ops_services == null)
						grid[i].ops_services = "";

					if (grid[i].ops_status == null)
						grid[i].ops_status = "";

					//explanation of conditions
					//(1) if the design team status = the group AND
					//(2) if the user is listed as a PW personnel member OR someone they supervise is listed as a PW personnel member
					var is_in_group = (grid[i].fst_status == groups[j]);
					var is_relevant = (grid[i][pw_personnel[0]] == user_name || grid[i][pw_personnel[1]] == user_name ||
						grid[i][pw_personnel[2]] == user_name || grid[i][pw_personnel[3]] == user_name ||
						grid[i][pw_personnel[4]] == user_name || supervising.includes(grid[i][pw_personnel[0]]) ||
						supervising.includes(grid[i][pw_personnel[1]]) || supervising.includes(grid[i][pw_personnel[2]]) ||
						supervising.includes(grid[i][pw_personnel[3]]) || supervising.includes(grid[i][pw_personnel[4]]));

					// If this is pre-install, check to make sure it is not awarded
					if (groups[j] == "Pre-Installation" && is_in_group && grid[i].quoteStatus.includes("Award") && !groups_header[j].includes("Unassigned"))
						is_in_group = false;

					// if group is data collection or site survey, check fst_services for match
					if (["Data Collection", "Site Survey"].includes(groups[j]))
						is_in_group = (grid[i].ops_services == groups[j]);

					//if groups is unassigned & this user is a supervisor, then default condition3 to true
					if (groups_header[j].includes("Unassigned") && user_info['manager'] == "checked") {

						//default is_relevant to false and make sure it fits in one of the regions
						is_relevant = false;

						//check if relevant to region
						if (group_codes[j] == "unassigned_west" && (grid[i].market.substr(0, 1) == "2" || grid[i].market.substr(0, 1) == "4"))
							is_relevant = true;
						else if (group_codes[j] == "unassigned_central" && grid[i].market.substr(0, 1) == "3")
							is_relevant = true;
						else if (group_codes[j] == "unassigned_east" && (grid[i].market.substr(0, 1) == "5" || grid[i].market.substr(0, 1) == "6"))
							is_relevant = true;

						// if this is a service request, check the ops_status, if assigned, make sure we do not move this job into an unassigned queue
						if (grid[i].ops_services != "" && grid[i].ops_status != "")
							is_relevant = false;

					} else if (user_info['manager'] == "checked") {

						// Loop through markets & check for relevancy
						for (var k = 0; k < markets.length; k++) {
							if ((grid[i].market.substr(0, 4) == markets[k].substr(0, 4)) && user_info['assigned_markets'].includes(markets[k].substr(0, 4))) {
								is_relevant = true;
								break;
							}
						}
					}

					if (is_in_group && is_relevant) {

						add_home_row(grid[i], all_order, j, groups_header[j]);

						/*
						if (groups_header[j].includes("Unassigned"))
							add_home_row(i, unassigned_order, j, groups_header[j], background);
						else if (groups[j] == "Pre-Installation")
							add_home_row(i, pre_install_order, j, groups_header[j], background);
						else if (groups[j] == "Complete")
							add_home_row(i, standard_order, j, groups_header[j], background);
						else if (groups[j] == "Post-Installation")
							add_home_row(i, post_install_order, j, groups_header[j], background);
						else if (groups[j] == "Data Collection" || groups[j] == "Site Survey")
							add_home_row(i, service_request_order, j, groups_header[j], background);
						else if (groups[j] != "Complete")
						*/

						//push to relevant quote
						relevant_quotes.push(grid[i].quoteNumber);
						break;
					}
				}
			}

			// run power search
			if (window.initialLoad)
				search_table_body();

			// Update table counts (found in javascript/js_helper.js)
			update_table_counts(group_codes);
		}

		// Global to handle toggling which tables need to be visible
		var visible_tables = [];

		/**
		 * Handles searching table body from power search bar
		 * 1) Shows all rows in all tables
		 * 2) Loops through all quotes and hides tables that do not match our criteria
		 * @author Alex Borchers
		 * @return void
		 */
		function search_table_body() {

			// show all rows in all tables
			document.querySelectorAll('.table_row').forEach(function(a) {
				a.style.display = "table-row";
			})

			// Reset visible_tables
			visible_tables = [];

			// reset state of dashboard tables
			expand_all_tables(true);

			// get power search value (user entered)
			var power_value = u.eid("power_search").value.toLowerCase().trim();

			// if power_value is blank, return now
			if (power_value == "" || power_value == null)
				return;

			// loop through all <button> elements (users can click to redirect to quote)
			document.querySelectorAll('.quotes').forEach(function(obj) {
				if (!power_search_handler(obj.innerHTML, power_value))
					hide_table_row(obj);
				else {
					// Get table ID and add to visible_tables
					var td = obj.parentNode;
					var tr = td.parentNode;
					var tbody = tr.parentNode;
					var table = tbody.parentNode;
					if (!visible_tables.includes(table.id))
						visible_tables.push(table.id);
				}
			})

			console.log(visible_tables);

			// Show relevant tables, hide tables with no results
			var tables = u.class("homeTables");
			var dropdowns = u.class("ui-accordion-header-icon");
			show_relevant_tables(tables, dropdowns, visible_tables);
		}

		/**
		 * Handles hiding a table row given an element inside of the table
		 * @author Alex Borchers
		 * @param {HTMLElement} p relates to the <p> element in the table we need to hide
		 * @return void
		 */
		function hide_table_row(p) {

			// work our way to the <tr> Element
			var td = p.parentNode;
			var tr = td.parentNode;
			tr.style.display = "none";
		}

		/**
		 * Handles running a power search on all project info columns based on user's critera
		 * @author Alex Borchers
		 * @param {String} 	quote 			the quote we are interested in looking at
		 * @param {String} 	power_value		the value we are using to search
		 * @return {Boolean}				(True if we found a match, False otherwise)
		 */
		function power_search_handler(quote, power_value) {

			// look for index in grid
			var index = grid.findIndex(object => {
				return object.quoteNumber == quote;
			});

			// no match, return false
			if (index == -1)
				return false;

			var grid_item = grid[index];

			// otherwise, search through fst_grid columns for a match
			for (var i = 0; i < grid_columns.length; i++) {

				if (grid_item[grid_columns[i]] === null || grid_item[grid_columns[i]] === undefined)
					grid_item[grid_columns[i]] = "";

				//check for match
				if (grid_item[grid_columns[i]].toLowerCase().includes(power_value))
					return true;
			}

			// check for exceptions (ex. project name + phase name = full project name)
			var full_name = grid_item['location_name'].toLowerCase() + " " + grid_item['phaseName'].toLowerCase();
			if (full_name.includes(power_value))
				return true;

			// make it to the end = no match
			return false;

		}

		//global used to guide table creation
		const all_order = ['quote_num', 'project_name', 'market', 'customer'];

		//handles adding a table row on home screen
		//param 1 = object in grid that we want to add row for
		//param 2 = headers to guide table creation
		//param 3 = type of row we need to add (dtermines if we need input fields)
		function add_home_row(grid_row, heads, t_index, type) {

			//get table/create new row
			var table = u.eid("searchTable" + t_index).getElementsByTagName('tbody')[0];
			var row = table.insertRow(-1);
			row.classList.add("table_row");

			//loop through heads and add to table
			for (var i = 0; i < heads.length; i++) {
				add_row_cell(grid_row, heads[i], type, i, row);
			}
		}

		//handles adding cell to table row
		//param 1 = object in fst_grid to be added
		//param 2 = header to guide cell creation
		//param 3 = type of row we need to add (dtermines if we need input fields)
		//param 4 = cell index (what level we are creating)
		//param 5 = row to add to 
		function add_row_cell(grid_row, head, type, cell_index, row) {

			//create cell based on index
			var cell = row.insertCell(cell_index);

			//depedning on head, add info
			if (head == "quote_num") {

				// Create <p> tag so quote is searchable
				var p = document.createElement("p");
				p.style.display = "none";
				p.innerHTML = grid_row.quoteNumber;
				p.classList.add("quotes");

				// Create drop-down div for quote
				var div = document.createElement("div");
				div.classList.add("quote");

				// Create button & div to go inside div quote
				var button = document.createElement("button");
				button.innerHTML = grid_row.quoteNumber + " &#9660;";
				var div2 = document.createElement("div");
				div2.classList.add("quote-content");
				div2.innerHTML += '<a href="mobile_parts_req.php?quote=' + grid_row.quoteNumber + '">Parts Request</a>';
				div2.innerHTML += '<a href="mobile_service_req.php?quote=' + grid_row.quoteNumber + '">Service Request</a>';
				div2.innerHTML += '<a href="mobile_ship_req.php?quote=' + grid_row.quoteNumber + '">Ship Request</a>';
				div2.innerHTML += '<a href="mobile_po_req.php?quote=' + grid_row.quoteNumber + '">PO Request</a>';
				div2.innerHTML += '<a href="' + grid_row.googleDriveLink + '">Google Drive</a>';

				// Add button & div to quote div
				div.appendChild(button);
				div.appendChild(div2);

				// Append to table cell
				cell.appendChild(p);
				cell.appendChild(div);
			} else if (head == "project_name") {
				var p = document.createElement("p");
				p.innerHTML = grid_row.location_name + " " + grid_row.phaseName;
				cell.appendChild(p);
			} else if (head == "value") {
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
			} else if (head == "market") {
				var p = document.createElement("p");

				if (type == "All Projects") {
					p.innerHTML = grid_row.market.substr(0, 4);
					cell.appendChild(p);
				} else {
					if (grid_row.market !== null)
						p.innerHTML = grid_row.market.substring(0, 4);

					cell.appendChild(p);
				}
			} else if (head == "customer") {
				var p = document.createElement("p");
				p.innerHTML = grid_row.customer;
				cell.appendChild(p);
			}

			//add class
			cell.classList.add("col_" + head);

		}

		// handles changing tabs
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

		//windows onload
		window.onload = function() {

			// Get the element with id="defaultOpen" and click on it
			u.eid("defaultOpen").click();

			// Initialize table layout (only on dashboard)
			init_table_body();

		}

		// show mouse waiting while sending ajax request
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