<?php

/****************
 * 
 * Created by: Alex Borchers (5/8/23)
 * This file is intended only for mobile use. This should be used as a template for future mobile screen development
 * 
 *****************/

// load in dependencies
session_start();
include('phpFunctions_html.php');
include('phpFunctions_views.php');
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

// Get quote info
$_GET['quote'] = "223-006901-011";
$pq_detail = [];
$containers = [];
if (isset($_GET['quote'])){
	$quote = $_GET['quote'];

	// Get project info
	$query = "SELECT * FROM fst_grid WHERE quoteNumber = '" . $quote . "';";
	$result = mysqli_query($con, $query);
	$grid = mysqli_fetch_array($result);

	// Get pq_detail (parts staged)
	$query = "SELECT a.* FROM fst_pq_detail a
				LEFT JOIN fst_pq_overview b
					ON a.project_id = b.id
				WHERE b.quoteNumber = '" . $quote . "'
				AND a.status = 'Staged'
				ORDER BY a.wh_container;";
	$result = mysqli_query($con, $query);
	while($rows = mysqli_fetch_assoc($result)){
		array_push($pq_detail, $rows);

		// Check if container / shop combo is already in list
		$container_string = $rows['wh_container'] . " (" . $rows['shop_staged'] . ")";
		if (!in_array($container_string, $containers))
			array_push($containers, $container_string);
	}
}
else{
	$quote = "";
	$grid = [];
}

// Get user email list
$users_email = [];
$query = "SELECT email FROM fst_users WHERE status = 'Active' ORDER BY firstName;";
$result = mysqli_query($con, $query);
while($rows = mysqli_fetch_assoc($result)){
	array_push($users_email, $rows['email']);	
}

?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">
<style>
    
    /** insert styles here **/
    .tabcontent{
      padding: 46px 20px;
    }
	.ui-autocomplete {
		max-height: 150px;
		overflow-y: auto;
		overflow-x: hidden;
		z-index:4000 !important;
	}

</style>
<!-- add any external style sheets here -->
<link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css'> 
<link rel='stylesheet' href='stylesheets/element-styles.css'>
<link rel='stylesheet' href='stylesheets/mobile-element-styles.css'> 
<link rel="shortcut icon" type="image/x-icon" href="images/P-Logo.png" />
<link href = "stylesheets/jquery-ui-themes-1.13.0/themes/ui-lightness/jquery-ui.css" rel = "stylesheet">
<title>Mobile Ship Request (v<?= $version ?>) - Pierson Wireless</title>
</head>
<body>

<?php

// if access is temporary, manually adjust all user fields
if ($user->info['accessLevel'] == "Temporary"){
  $query = "DESCRIBE fst_users;";
  $result = mysqli_query($con, $query);
  while($rows = mysqli_fetch_assoc($result)){
    if (!in_array($rows['Field'], ["firstName", "lastName", "email", "accessLevel"]))
      $user->info[$rows['Field']] = "";
  }
}

//render header by using create_navigation_bar function (takes two arrays = 1 = name of buttons, 2 = id's of divs to open)
$header_names = ['Ship Request'];   //what will appear on the tabs
$header_ids = ['ship_request'];      //must match a <div> element inside of body

echo create_navigation_bar($header_names, $header_ids, "", $user->info);

?>

<div id = 'ship_request' class = 'tabcontent' style = 'display:none'>
  
  	<div id = 'ship_request_dialog_page1'>
	  	<h4>Please select containers to be shipped</h4>
		<table id = 'ship_request_container_table'>
			<tr>
				<th><!--Expand--></th>
				<th>Request?</th>
				<th>Container</th>
			</tr>
			<tbody>
			<?php

			// Loop through list of containers in each shop
			foreach($containers as $container){

				?>

				<tr>
					<td><button onclick="expand_collapse_container(this)">+</button></td>
					<td style = 'text-align: center;'><input type="checkbox" class="shipping_containers" checked></td>
					<td><?= $container; ?></td>
				</tr>

				<?php
			}
			?>
			</tbody>
		</table>

		<h4>The following parts are not found in containers</h4>
		<table class = "standardTables" id = 'ship_request_unlabeled_table'>
			<tr>
				<th>Request?</th>
				<th>Part Number</th>
				<th>Quantity</th>
			</tr>
			<tbody>
			<!-- rows to be added by add_parts_no_containers() -->
			</tbody>
		</table>
		<button id='ship_request_page2' class = 'mobile_button_full'>Go To Page 2</button>
	</div>

	<div id = 'ship_request_dialog_page2' style = 'display: none'>

		<h3 class = 'page_title'>Ship Request (page 1)</h3>

		<div class = 'form_title'>
			*CC (semicolon-delimited):
		</div>
		<div class = 'form_answer'>
			<textarea class = "emails required_SR ship_request" type = "text" id = 'email_cc'><?= $user->info['email']; ?>; </textarea>
		</div>
		<div class = 'form_title'>
			Additional Instructions:
		</div>
		<div class = 'form_answer'>
			<textarea id = 'additional_instructions' class = 'ship_request' placeholder="(Please list any additional instructions here that you would like to show up in the body of the email - are there specific part requirements, etc.)"></textarea>
		</div>

		<div id = 'pr_ship_div'>
			<h3>Shipping Information:</h3>
			<div class = 'form_title'>
				Select from PW shop locations:
			</div>
			<div class = 'form_answer'>
				<select class = 'custom-select' id = "pw_shippingLocations" onchange = "shippingSelect(this.value)">
					<option></option>
					<?php

					$query = "SELECT * from general_shippingadd where customer = 'PW' order by name";
					$result = mysqli_query($con, $query);

					while($rows = mysqli_fetch_assoc($result)){
						?>
						<option value = "<?= '|PW|' . $rows['recipient'] . '|' . $rows['phone'] . '|' . $rows['email'] . '|' . $rows['address'] . '|' . $rows['city'] . '|' . $rows['state'] . '|' . $rows['zip'] . '|' . $rows['name'] . '|'?>"> <?= $rows['name'] ?></option>
						<?php
					}

					?>
				</select>
			</div>
			<h4>Contact Information:</h4>
			<div class = 'form_title'>
				*POC Receiving Shipment:
			</div>
			<div class = 'form_answer'>
				<input class = "required_SR ship_request" type = "text" id = 'poc' >
			</div>
			<div class = 'form_title'>
				*POC Phone Number:
			</div>
			<div class = 'form_answer'>
				<input class = "required_SR ship_request" type = "text" id = 'poc_phone' >
			</div>
			<div class = 'form_title'>
				Email Address(if available):
			</div>
			<div class = 'form_answer'>
				<input type = "text" class = 'ship_request' id = 'poc_email' >
			</div>
			<h4>Shipping Location:</h4>
			<div class = 'form_title'>
				*Location Name:
			</div>
			<div class = 'form_answer'>
				<input class = "required_SR ship_request" type = "text" id = 'ship_location' >
			</div>
			<div class = 'form_title'>
				*Shipping Address:
			</div>
			<div class = 'form_answer'>
				<input class = "required_SR ship_request" type = "text" id = 'ship_address' >
			</div>
			<div class = 'form_title'>
				Address (line 2):
			</div>
			<div class = 'form_answer'>
				<input type = "text" id = 'ship_address2' class = 'ship_request'>
			</div>
			<div class = 'form_title'>
				*City:
			</div>
			<div class = 'form_answer'>
				<input class = "required_SR ship_request" type = "text" id = 'ship_city' >
			</div>
			<div class = 'form_title'>
				*State:
			</div>
			<div class = 'form_answer'>
				<select class = "custom-select required_SR ship_request" id = 'ship_state' >
					<option></option>
					<?= create_select_options($states); ?>
				</select>
			</div>
			<div class = 'form_title'>
				*Zip:
			</div>
			<div class = 'form_answer'>
				<input class = "required_SR ship_request" type = "text" id = 'ship_zip' >
			</div>
			<h4>Other Info:</h4>
			<div class = 'form_title'>
				Liftgate Required?
			</div>
			<div class = 'form_answer'>
				<select class = 'custom-select ship_request' id = 'liftgate_opt' >
					<option value="N">N</option>
					<option value="Y">Y</option>
				</select>
			</div>
			<div class = 'form_title'>
				*Due By Date
			</div>
			<div class = 'form_answer'>
				<input class = "required_SR ship_request" id = 'due_by_date' type = "date">
			</div>
			<div class = 'form_title'>
				Scheduled Delivery Required?
			</div>
			<div class = 'form_answer'>
				<select class = 'custom-select ship_request' id = 'scheduled_delivery' onchange = "scheduleSelect(this.value)">
					<option value="N">N</option>
					<option value="Y">Y</option>
				</select>
			</div>
			<div id = 'schedRow1' style = 'display:none;'>
				<div class = 'form_title'>
					Scheduled Delivery Date
				</div>
				<div class = 'form_answer'>
					<input class = 'ship_request' id = 'scheduled_date' type = "date">
				</div>
			</div>
			<div id = 'schedRow2' style = 'display:none;'>
				<div class = 'form_title'>
					Scheduled Delivery Time
				</div>
				<div class = 'form_answer'>
					<input class = 'ship_request' id = 'scheduled_time' type = "time">
				</div>
			</div>
		</div>
		<i>Ship requests made after 2PM CT may not be processed until the next day. <br>(any overnight shipments will be done on a best effort basis but cannot be guaranteed)</i>
		<button id = 'pq_button' class = 'mobile_button_full' onclick="submit_shipping_request()" >Submit Request</button>
		<button id='ship_request_page1' class = 'mobile_button_full'>Go To Page 1</button>	
	</div>
</div>

<!-- external libraries used for particular functionallity (NOTE YOU MAKE NEED TO LOAD MORE EXTERNAL FILES FOR THINGS LIKE PDF RENDERINGS)-->
<!--load libraries used to make ajax calls-->
<script	src = "https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
<script src="https://apis.google.com/js/platform.js?onload=init" async defer></script>

<!-- jquery -->
<script src="https://code.jquery.com/jquery-1.12.4.js"></script>
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>

<!-- interally defined js files -->
<script src = "javascript/utils.js"></script>
<script type = "text/javascript" src="javascript/js_helper.js?<?= $version; ?>-5"></script>
<script src = "javascript/accounting.js"></script>
<script src = "javascript/fst_js_functions.js"></script>

<script>

    // Pass relevant data to js
    const user_info = <?= json_encode($user->info); ?>,
          grid = <?= json_encode($grid); ?>,
		  pq_detail = <?= json_encode($pq_detail); ?>,
		  availableTags = <?= json_encode($users_email); ?>;
	
	/**
	 * Handles expanding/collapsing container contents
	 * @author Alex Borchers
	 * @param {HTMLElement} targ (the <button> clicked by the user)
	 * 
	 * @returns void	 
	 */
	function expand_collapse_container(targ){

		// depending on if user wants to show/collapse items, perform different action
		// case for expanding
		if (targ.innerHTML == "+"){
			targ.innerHTML = "-";
			show_container_contents(targ);
		}
		// case for collapsing
		else{
			targ.innerHTML = "+";

			// get row that current holds expanded info
			var td = targ.parentNode;
			var tr = td.parentNode;
			var next_tr = tr.nextElementSibling;
			next_tr.remove();
		}
	}

	/**
	 * Handles getting list of parts related to container
	 * @author Alex Borchers
	 * @param {HTMLElement} targ (the <button> clicked by the user)
	 * 
	 * @returns void
	 */
	function get_container_contents(targ){

		// work back to name of container
		var td = targ.parentNode;
		var tr = td.parentNode;
		var parentTable = tr.parentNode.parentNode;		// tr.tbody.table
		var container_full = tr.children[2].innerHTML;

		// Seperate container into physical container and staged location
		// Container 1 (OMA) => Container 1 ... OMA 
		var container = container_full.substr(0, container_full.indexOf("(") - 1).trim();
		var shop_staged = container_full.substr(container_full.indexOf("(") + 1, 3).trim();

		// find matching list of parts for this container
		var container_parts = pq_detail.filter(function (p) {
			return p.wh_container.includes(container) && p.shop_staged == shop_staged && p.status == "Staged";
		});

		return container_parts;
		
	}

	/**
	 * Handles listing out parts related to container on user click
	 * @author Alex Borchers
	 * @param {HTMLElement} targ (the <button> clicked by the user)
	 * 
	 * @returns void
	 */
	function show_container_contents(targ){

		// create new table to add elements to
		var table = document.createElement("table");
		table.classList.add("standardTables", "shipping_request_container");
		table = create_table_headers(table);

		// get parts related to container
		var parts = get_container_contents(targ);

		// loop through containers & add row to shipping request table
		for (var i = 0; i < parts.length; i++){
			
			//call function to insert row to table
			insert_container_part_row(parts[i], table, true);
		}

		// work back to name of container
		var td = targ.parentNode;
		var tr = td.parentNode;
		var parentTable = tr.parentNode.parentNode;		// tr.tbody.table

		// insert new row into existing table (under the row where user clicked targ <button>)
		var row = parentTable.insertRow(tr.rowIndex + 1);
		var cell = row.insertCell(0);
		cell.colSpan = tr.cells.length;
		cell.appendChild(table);
		
	}

	/**
	 * Creates table headers for shipping request view tables
	 * @author Alex Borchers
	 * @param {HTMLElement} table
	 * @returns {HTMLElement} table (with headers)
	 */
	function create_table_headers(table){

		// create array of Headers
		var headers = ["Request?", "Part Number", "Quantity"];

		// create new row
		var row = table.insertRow(-1);
		row.classList.add("shipping_request_row");

		for (var i = 0; i < headers.length; i++){
			var cell = row.insertCell(i);
			cell.innerHTML = headers[i];
			cell.classList.add("ship_request_head");
		}

		return table;

	}

	/**
	 * Handles inserting a part row into a container table (may be coming form no container or some container)
	 * @author Alex Borchers
	 * @param {object} 		part		matches entry from fst_boms db table (added detail from update_order_info)
	 * @param {HTMLElement} table		the table we are adding to
	 * @param {boolean}		container 	(true/false)
	 * 
	 * @returns void
	 */
	function insert_container_part_row(part, table, container){

		// insert new row
		var row = table.insertRow(-1);
		row.classList.add("shipping_request_row");

		// insert first row (expand button)
		var cell = row.insertCell(0);
		cell.style.textAlign = "center";
		if (container)
			cell.innerHTML = "<input type = 'checkbox' checked disabled>";
		else
			cell.innerHTML = "<input type = 'checkbox' class = 'shipping_no_containers' id = '" + part.id + "' checked>";

		// insert part #
		var cell = row.insertCell(1);
		cell.innerHTML = part.part_id + " (" + part.shop_staged+ ")";

		// insert quantity
		var cell = row.insertCell(2);
		cell.innerHTML = part.q_allocated;

	}

	/**
	 * The next 2 listeners move from page 1 to 2 and back for the shipping request dialogClass 
	 */
	$('#ship_request_page2').on('click', function() {

		//open page 2, close page 1
		u.eid("ship_request_dialog_page2").style.display = "";
		u.eid("ship_request_dialog_page1").style.display = "None";

	});

	$('#ship_request_page1').on('click', function() {

		//open page 1 & close page2
		u.eid("ship_request_dialog_page2").style.display = "None";
		u.eid("ship_request_dialog_page1").style.display = "";

	});

	/**
	 * Handles updating shipping address
	 * 
	 * @param address (string) an encoded value which holds shipping info |[PW/Customer]|Name|Phone|Email|Address|City|State|Zip|Location
	 * 
	 * @return void (fills in IDs with correct info, defined in the function)
	 */
	function shippingSelect (address){
		var location = [], tempLen1 = 1, currSpot = 0, tempLen2;
		
		if (address == ""){
			//contact info 
			u.eid("poc").value = "";
			u.eid("poc_phone").value = "";
			u.eid("poc_email").value = "";
			
			//shipping info
			u.eid("ship_address").value = "";
			u.eid("ship_city").value = "";
			u.eid("ship_state").value = "";
			u.eid("ship_zip").value = "";
			u.eid("ship_location").value = "";
			
		}
		
		else{

			while (tempLen1 > 0 && currSpot < 9){
				tempLen2 = address.indexOf("|", tempLen1)
				location[currSpot] = address.substring(tempLen1, tempLen2);
				tempLen1 = tempLen2 + 1;
				currSpot++;
			}
			
			//contact info 
			u.eid("poc").value = location[1];
			u.eid("poc_phone").value = location[2];
			u.eid("poc_email").value = location[3];
			
			//shipping info
			u.eid("ship_address").value = location[4];
			u.eid("ship_city").value = location[5];
			u.eid("ship_state").value = location[6];
			u.eid("ship_zip").value = location[7];
			u.eid("ship_location").value = location[8];			
		}
	}

	/**
	 * Handles showing/hiding scheduled delivery options
	 * @author Alex Borchers
	 * @param {String} check (Y/N)
	 * @returns void
	 */
	function scheduleSelect(check){
		if (check == "Y"){
			u.eid("schedRow1").style.display= "block",
			u.eid("schedRow2").style.display= "block";
		}
		else{
			u.eid("schedRow1").style.display = "none",
			u.eid("schedRow2").style.display= "none";
		}
	}

	/**
	 * Handles submitting shipping request to terminal
	 * @author Alex Borchers
	 * @returns void
	 */
	function submit_shipping_request(){

		// validate all required information is filled in (found in js_helper.js)
		if (check_submit(u.class("required_SR"))){
			alert("[Error] Please fill in all required fields (highlighted in yellow).");
			return;
		}

		// initialize array to carry over parts being requested
		var requested_parts = [];

		// loop through container items to see what is checked
		document.querySelectorAll('.shipping_containers').forEach(function(a){
			if (a.checked){

				// get parts related to container
				var parts = get_container_contents(a);

				// loop through parts and push to array
				for (var i = 0; i < parts.length; i++){
					requested_parts.push(parts[i].id);
				}
			}
		})

		// loop through non-container items to see what is checked
		document.querySelectorAll('.shipping_no_containers').forEach(function(a){
			if (a.checked){

				// add id to request
				requested_parts.push(a.id);
			}
		})

		// make sure user has selected some elements to be requested
		if (requested_parts.length == 0){
			alert("[Error] At least 1 container or item must be selected to submit a shipping request.");
			return;
		}
		
		// pass information to server via ajax
		// init form data
		var fd = new FormData();
		
		// pass shipping information
		// loop through 'ship_request' class, push all values with id as pointer
		document.querySelectorAll('.ship_request').forEach(function(a){
			fd.append(a.id, a.value);
		})

		// pass arrays, user info & tell
		fd.append('quoteNumber', grid.quoteNumber)
		fd.append('requested_parts', JSON.stringify(requested_parts));
		fd.append('user_info', JSON.stringify(user_info));
		fd.append('tell', 'submit_shipping_request');

		// Show spinner to tell user we are working on it
		u.eid("ship_request").style.display = "none";
		create_spinner(); // found in js_helper.js
		
		//send info to ajax, set up handler for response
		$.ajax({
			url: 'home_ops_helper.php',
			type: 'POST',
			processData: false,
			contentType: false,
			data: fd,
			success: function (response) {
				
				// if we return an error, let the user know
				if (response != ""){
					alert("There may have been an error. Please screenshot & send to fst@piersonwireless.com: " + response);
					console.log(response);
					return;
				}

				// Convert spinner to checkmark
				$('.circle-loader').toggleClass('load-complete');
  				$('.checkmark').toggle();
				window.location.href = "mobile_home_ops.php";

				// alert user of success & close dialog
				alert("The shipping request has been processed successfully.");
				window.close();	// attempt to close window
			}
		});
	}

    //handles tabs up top that toggle between divs
    function change_tabs (pageName, elmnt, color) {

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
	$(document).ajaxStart(function () { 
		// change curse to spin
		waiting('on'); 
	});

	$(document).ajaxStop(function () {
		// remove curse spin
		waiting('off'); 
	});

    //windows onload
	window.onload = function () {
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
$mysqli -> close();

?>