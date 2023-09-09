<?php

/****************
 * 
 * THIS FILE IS INTENDED TO BE A TEMPLATE FOR FUTURE USE
 * 
 * PLEASE UPDATE THIS SECTION WITH THE PURPOSE OF THE FILE
 * 
 *****************/

// load in dependencies
session_start();
include('phpFunctions_html.php');
include('phpFunctions.php');
include('constants.php');
include('PHPClasses/User.php');

// load db configuration
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

//initialize allocations_mo object to be passes to js
$allocations_mo = [];

//load in any material orders open for this shop
$query = "SELECT status, project_id, staged_loc FROM fst_allocations_mo ORDER BY closed ASC, date_created ASC;";
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
			WHERE b.status IN('Shipped', 'Staged', 'In-Transit');";
$result =  mysqli_query($con, $query);

while ($rows = mysqli_fetch_assoc($result)) {

	//push temp array to project array
	array_push($pq_detail, $rows);
}

//get PW shipping addresses
$pw_shipping = [];
$query = "select * from general_shippingadd WHERE customer = 'PW';";
$result =  mysqli_query($con, $query);
while ($rows = mysqli_fetch_assoc($result)) {
	array_push($pw_shipping, $rows);
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
$query = "SELECT * FROM inv_staging_areas;";
$result = mysqli_query($con, $query);	//in constants.php
while ($rows = mysqli_fetch_assoc($result)) {
	array_push($staging_areas, $rows);
}

?>

<!DOCTYPE html>
<html>

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<style>
		/** insert styles here **/
		.tabcontent {
			padding: 70px 20px;
		}

		/**add styles to the table */
		table {
			width: 100%;
			border-collapse: collapse;
			margin: 0 auto;
			text-align: left;
		}

		th,
		td {
			padding: 10px;
			border: 1px solid #ccc;
		}

		thead {
			background-color: #f5f5f5;
		}

		tbody tr:nth-child(even) {
			background-color: #f2f2f2;
		}

		.sticky-header-qr {
			position: sticky;
			top: 46px;
			z-index: 100;
			background: #114b95;
			color: white;
		}

		/**adjust received_input class attributes */
		.received_input {
			width: 100%;
		}

		.received_cell {
			width: 25% !important;
		}

		.issue_cell {
			width: 5% !important;
			text-align: center;
		}

		/**adjust quantity_input class width */
		.quantity_cell {
			width: 20% !important;
		}

		/**adjust size of button */
		/* .mobile_button{
      width: 100%;
      margin-top: 1rem;
      font-size: 2rem !important;
      font-weight: bold;
      height: 5rem;
      border-radius: 4px;
      border: none;
      color: black;
      border: 1px solid black;
      cursor: pointer;
    } */

		/**adjust formatting of file picker */
		.mobile_label_button {
			width: 100%;
			margin-top: 1rem;
			font-size: 2rem !important;
			height: 3rem;
			display: inline-block;
			font-weight: bold;
			text-align: center;
			background-color: #f0f0f0;
			border: 1px solid black;
			border-radius: 4px;
			cursor: pointer;
			margin-top: 1;
			padding-top: 0em;
			padding-top: 1rem;
			padding-bottom: 0.2rem;
			color: black;
		}

		.mobile_label_button:hover,
		.mobile_button:hover {
			background-color: #ccc;
		}

		/**styles related to image list */
		.image-item {
			align-items: center;
			justify-content: space-between;
			margin-bottom: 8px;
		}

		.image-name {
			margin-right: 8px;
		}

		.remove-button {
			background: none;
			border: none;
			color: red;
			font-size: 1.2em !important;
			cursor: pointer;
		}

		/**style thank you menu */
		#receiving_thankyou h2 {
			text-align: center;
			display: flex;
			justify-content: center;
			align-items: center;
			margin: 0 auto;
			padding-top: 4em;
		}

		.card {
			transition: transform 0.3s ease-in-out;
		}

		.swipe-right {
			transform: translateX(105%);
		}

		.swipe-left {
			transform: translateX(-105%);
		}

		/* @media screen and (max-width: 768px) {
      /* Styles for screens smaller than 768px
      input[type="checkbox"] {
        -ms-transform: scale(0.8);
        -webkit-transform: scale(0.8);
        transform: scale(0.8);
      } 
    } */
	</style>
	<!-- add any external style sheets here -->
	<link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css'>
	<link rel='stylesheet' href='stylesheets/element-styles.css'>
	<link rel='stylesheet' href='stylesheets/mobile-element-styles.css'>
	<link rel="shortcut icon" type="image/x-icon" href="images/P-Logo.png" />
	<link href="stylesheets/jquery-ui-themes-1.13.0/themes/ui-lightness/jquery-ui.css" rel="stylesheet">
	<title>Mobile Receiving Lookup #f2f2f2(v<?= $version ?>) - Pierson Wireless</title>
</head>

<body>

	<?php

	//render header by using create_navigation_bar function (takes two arrays = 1 = name of buttons, 2 = id's of divs to open)
	$header_names = ['Mobile Receiving'];   //what will appear on the tabs
	$header_ids = ['mobile_receiving'];      //must match a <div> element inside of body

	echo create_navigation_bar($header_names, $header_ids, "", $user->info);

	?>

	<div id='mobile_receiving' class='tabcontent' style='display:none'>
		<div class='card-container'>
			<div class='card' id='receiving_main'>

				<h3>Search Filters</h3>

				<div class='form_title'>
					Job #
				</div>
				<div class='form_answer'>
					<input type="text" id='job_number' class='search_filters'>
				</div>
				<div class='form_title'>
					Purchase Order #
				</div>
				<div class='form_answer'>
					<input type="text" id='po_number' class='search_filters'>
				</div>
				<div class='form_title'>
					Material Order #
				</div>
				<div class='form_answer'>
					<input type="text" id='mo_number' class='search_filters'>
				</div>
				<div class='form_title'>
					Tracking #
				</div>
				<div class='form_answer'>
					<input type="text" id='tracking_number' class='search_filters'>
				</div>

				<button onclick='filter_receiving()'>Search</button>

				<table>
					<thead>
						<tr>
							<th>PO # / MO #</th>
							<th>Carrier</th>
							<th>Expected Date</th>
							<th>Job #</th>
							<th></th>
						</tr>
					</thead>
					<tbody id='receiving_table_body'>
					</tbody>
				</table>

				<label for="image-input" class='mobile_label_button'>Take Image</label>
				<input id="image-input" type="file" name="image" style="display: none;">
				<ul id="image-list"></ul>
				<label for="receive_button" class="mobile_label_button">Submit</label>
				<input type='button' class='mobile_button' id='receive_button' style="display: none;" value='Submit' onclick='qr_receive_items()'>


			</div>

			<div class='card swipe-right' id='receiving_breakout' style='display:none'>

				<button id='return_to_search'><i class="fa fa-arrow-left"></i></button>

				<table class="standardTables shipping_parts_table">
					<tbody id='receiving_parts_table_tbody'>
						<tr class="shipping_parts_header" style="background-color: white;">
							<td style="text-align: left; border: 0px; font-weight: bold;">For Job <span id='receiving_job'></span></td>
						</tr>
						<tr class="shipping_parts_header" style="background-color: white;">
							<td>Part Number</td>
							<td>Quantity</td>
							<td>Quantity Received</td>
							<td>Staging Area</td>
							<td>Container</td>
						</tr>
					</tbody>
				</table>

				<div>For Shop <span></span></div>

				<table>
					<tr class='shipping_parts_header'>
						<td>Part Number</td>
						<td>Quantity</td>
						<td>Quantity Received</td>
						<td>Shelf Location</td>
					</tr>
				</table>

				<!-- var shop_table = document.createElement("table");
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
				for (var i = 0; i < headers.length; i++) { var cell=row.insertCell(i); cell.innerHTML=headers[i]; }  -->
				<button onclick='process_receiving()' class='submit_receiving_button'>Submit Only</button>
				<input id='receiving_type' style='display:none'>
				<input id='receiving_id' style='display:none'>

			</div>
		</div>

		<div id='receiving_thankyou' style='display:none'>
			<h2>Thank you! You can exit this screen now.</h2>
		</div>
	</div>

	<!-- external libraries used for particular functionallity (NOTE YOU MAKE NEED TO LOAD MORE EXTERNAL FILES FOR THINGS LIKE PDF RENDERINGS)-->
	<!--load libraries used to make ajax calls-->
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
	<script src="https://apis.google.com/js/platform.js?onload=init" async defer></script>

	<!-- jquery -->
	<script src="https://code.jquery.com/jquery-1.12.4.js"></script>
	<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>

	<!-- interally defined js files -->
	<script src="javascript/utils.js"></script>
	<script type="text/javascript" src="javascript/js_helper.js?<?= $version; ?>-5"></script>
	<script src="javascript/accounting.js"></script>

	<script>
		//pulls through all relevant info from php
		const allocations_mo = <?= json_encode($allocations_mo); ?>, //allocations MO info sent from allocations
			physical_locations = <?= json_encode($physical_locations); ?>,
			pq_detail = <?= json_encode($pq_detail); ?>, //parts requested & designated to terminal
			pw_shipping = <?= json_encode($pw_shipping); ?>, //PW shipping info
			pq_shipments = <?= json_encode($pq_shipments); ?>, //shipments going to this shop
			staging_areas = <?= json_encode($staging_areas); ?>,
			user_info = <?= json_encode($user->info); ?>;

		// Add event listener to our image-input file picker & keep track of all files selected on change
		const inputElement = document.getElementById("image-input");
		const listElement = document.getElementById("image-list");
		let selectedFiles = []; // will keep track of all files.

		function addListItem(file) {

			// Adjust file name
			//const fileExtension = file.name.split(".").pop();
			//new_name = file.name.substr(0, file.name.length - fileExtension.length - 1) + seq + "." + fileExtension;
			//seq++;

			const listItem = document.createElement("li");
			listItem.classList.add("image-item");

			const nameSpan = document.createElement("span");
			nameSpan.classList.add("image-name");
			nameSpan.textContent = file.name;

			const removeButton = document.createElement("button");
			removeButton.classList.add("remove-button");
			removeButton.innerHTML = "&times;";
			removeButton.addEventListener("click", function() {
				const index = selectedFiles.indexOf(file);
				if (index !== -1) {
					selectedFiles.splice(index, 1);
					listItem.remove();
				}
			});

			listItem.appendChild(nameSpan);
			listItem.appendChild(removeButton);
			listElement.appendChild(listItem);
		}

		inputElement.addEventListener("change", function(event) {
			const files = event.target.files;
			for (let i = 0; i < files.length; i++) {
				const file = files[i];
				selectedFiles.push(file);
				addListItem(file);
			}
		});

		/**
		 * Add event listener to notes_input field to prompt user to enter note when clicked
		 * @author Alex Borchers
		 * @param {HTMLElement} input (<input> tag clicked by user)
		 * @returns {void}
		 */
		function add_note(input) {

			// Work back to previous value
			previous_note = input.parentNode.childNodes[1];

			// Prompt user to add note
			let note = prompt("Add/Adjust Note", previous_note.value);

			if (note != null)
				previous_note.value = note;
		}

		function qr_receive_items() {

			// loop through checkbox items and determine which ones are selected
			var received_items = [];
			var type = "Full"; // flip to partial if not full
			document.querySelectorAll('.received_input').forEach(function(a) {

				// Get expected qty & notes
				var tr = a.parentNode.parentNode;
				var part = tr.children[0].innerHTML;
				var expected_qty = tr.children[1].innerHTML;
				var note = tr.children[3].childNodes[1].value;
				var type = "Full";

				// Compare value entered to expected, label the type of receiving
				if (parseInt(a.value) > parseInt(expected_qty))
					type = "Excess";
				else if (parseInt(a.value) < parseInt(expected_qty))
					type = "Partial";
				else if (parseInt(a.value) == 0)
					type = "None";

				// Push to received object
				received_items.push({
					id: a.id,
					part: part,
					expected_qty: expected_qty,
					qty: a.value,
					type: type,
					note: note
				});
			})

			// error if user has no items selected
			if (received_items.length == 0) {
				alert("[Error] At least 1 item must be received.");
				return;
			}

			// verify at least 1 image has been taken
			if (selectedFiles.length == 0) {
				alert("[Error] Please take at least 1 image.");
				return;
			}

			// disable submit button (do not submit twice)
			u.eid("receive_button").disabled = true;

			// initialize array for file_reference
			var file_reference = [];

			// init form data variable
			var fd = new FormData();

			// grab all files attached
			for (var i = 0; i < selectedFiles.length; i++) {
				var file = selectedFiles[i];
				fd.append('file' + i, file);
				file_reference.push('file' + i);
			}

			// pass information needed in helper file
			fd.append('quote', grid.quoteNumber);
			fd.append('type', type);
			fd.append('sr_id', sr_id);
			fd.append('container', container);
			fd.append('google_drive_link', get_google_drive_id(grid.googleDriveLink));
			fd.append('file_reference', JSON.stringify(file_reference));
			fd.append('received_items', JSON.stringify(received_items));

			// pass tell & user info
			fd.append('tell', 'receive_parts');
			fd.append('user_info', JSON.stringify(user_info));

			// ajax request to communicate with database
			$.ajax({
				url: 'terminal_qr_receiving_helper.php',
				type: 'POST',
				processData: false,
				contentType: false,
				data: fd,
				success: function(response) {

					// check for error
					if (response != "") {
						alert("[ERROR] Please screenshot & send to fst@piersonwireless.com. Official message: " + response);
						console.log(response);
						return;
					}

					// send user back to log-in
					alert("Thank you! The materials have been received successfully. All images are available in the jobs google-drive folders.");
					u.eid("receive_button").disabled = false;
					u.eid("receiving_main").style.display = "none";
					u.eid("receiving_thankyou").style.display = "block";


				}
			});
		}

		//handles filtering receiving info based on criteria found on receiving tab
		function filter_receiving() {

			//init filters and shipments
			var filters = [],
				matching_shipments = [],
				all_empty = true;

			//set filter object where id => value
			document.querySelectorAll('.search_filters').forEach(function(a) {
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
					return (filters['job_number'] == "" || p.project_id.includes(filters['job_number'])) && //matches user entered project id or no project_id entered
						(filters['po_number'] == "" || p.po_number.includes(filters['po_number'])) && //matches user entered PO # or no PO # entered
						(filters['mo_number'] == "" || p.mo_id.includes(filters['mo_number'])) && //matches user entered MO # id or no MO # entered
						(filters['tracking_number'] == "" || p.tracking.toLowerCase().includes(filters['tracking_number'].toLowerCase())); //matches user entered tracking or no tracking entered
				});
			}

			//get table, init index
			var table = u.eid("receiving_table_body"),
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

		//global to help create receiving table in js (used in insert_new_receiving_row)
		const receiving_keys = ['po_mo', 'carrier', 'arrival', 'project_id', 'show_shipping_parts'];

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
					var button = document.createElement("button");
					button.classList.add(receiving_keys[i]);
					var i = document.createElement("i");
					i.classList.add("fa");
					i.classList.add("fa-arrow-right");
					button.appendChild(i);

					//set ID based on type
					if (shipment.type == "PO")
						button.id = shipment.type + "_" + shipment.shipment_id;
					else
						button.id = shipment.type + "_" + shipment.mo_id;

					//append to cell
					cell.append(button);
				} else {
					cell.innerHTML = shipment[receiving_keys[i]];
				}
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
			var vp_number = tr.childNodes[3].childNodes[0].value;

			//filter and add parts below shipment ID row
			$('#receiving_main').addClass("swipe-left");
			$('#receiving_main').on("transitionend", function() {
				$('#receiving_main').css('display', 'none');
				$("#receiving_breakout").css('display', 'block');
				$('#receiving_breakout').removeClass("swipe-right");
				$('#receiving_main').off("transitionend");
			});

			//show shipment info and change button +/-
			get_shipping_parts(type, po_mo_number, tr.rowIndex, vp_number);
		});

		//handles showing parts related to a shipment on click
		$(document).on('click', '#return_to_search', function() {

			// Remove previous rows
			document.querySelectorAll('.temp_receiving_parts_row').forEach(function(a) {
				a.remove();
			})

			// Transition back to search
			$('#receiving_breakout').addClass("swipe-right");
			$('#receiving_breakout').on("transitionend", function() {
				$('#receiving_breakout').css('display', 'none');
				$("#receiving_main").css('display', 'block');
				$('#receiving_main').removeClass("swipe-left");
				$('#receiving_breakout').off("transitionend");
			});
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
			var table = u.eid("receiving_parts_table_tbody");

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
			} else {

			}

			//add row to orders table and push table to new row
			var receiving_table = u.eid("receiving_table_body");
			var row = receiving_table.insertRow(row_index);
			row.classList.add("temp_receiving_parts_row");
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

			// Set ID and type for hidden input fields to determine action on submit
			u.eid("receiving_type").value = type;
			u.eid("receiving_id").value = shipment_id;
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

		//handles tabs up top that toggle between divs
		function change_tabs(pageName, elmnt, color) {

			//check to see if clicking the same tab (do nothing if so)
			if (elmnt.style.backgroundColor == color)
				return;

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

		// if we execute an ajax request, we want the cursor to switch to 'waiting'
		$(document).ajaxStart(function() {
			// change curse to spin
			waiting('on');
		});

		$(document).ajaxStop(function() {
			// remove curse spin
			waiting('off');
		});

		//windows onload
		window.onload = function() {

			//place any functions you would like to run on page load inside of here
			u.eid("defaultOpen").click();
		}
	</script>

</body>

</html>

<?php
//perform any actions once page is entirely loaded

//reset return address once the page has loaded
unset($_SESSION['returnAddress']);

//close SQL connection
$mysqli->close();

?>