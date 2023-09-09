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
if (isset($_GET['quote'])){
	$quote = $_GET['quote'];
	$query = "SELECT * FROM fst_grid WHERE quoteNumber = '" . $quote . "';";
	$result = mysqli_query($con, $query);
	$grid = mysqli_fetch_array($result);
}
else{
	$quote = "";
	$grid = [];
}

$des_services = [];
$query = "SELECT task FROM general_des_task WHERE service_request = 'Y' ORDER BY priority;";
$result = mysqli_query($con, $query);
while($rows = mysqli_fetch_assoc($result)){
	array_push($des_services, $rows['task']);
}

$fse_service = [];
$query = "SELECT task FROM general_fse_task WHERE service_request = 'Y' ORDER BY priority;";
$result = mysqli_query($con, $query);
while($rows = mysqli_fetch_assoc($result)){
	array_push($fse_service, $rows['task']);
}

$cop_task = [];
$query = "SELECT task FROM general_cop_task ORDER BY priority;";
$result = mysqli_query($con, $query);
while($rows = mysqli_fetch_assoc($result)){
	array_push($cop_task, $rows['task']);
}

// get list of operations services that can be requested
$ops_services = [];
$query = "SELECT * FROM general_ops_task ORDER BY priority;";
$result = mysqli_query($con, $query);
while($rows = mysqli_fetch_assoc($result)){
	array_push($ops_services, $rows);
}

?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0, user-scalable=0">
<style>
    
    /** insert styles here **/
    .tabcontent{
      padding: 46px 20px;
    }

</style>
<!-- add any external style sheets here -->
<link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css'> 
<link rel='stylesheet' href='stylesheets/element-styles.css'> 
<link rel='stylesheet' href='stylesheets/mobile-element-styles.css'> 
<link rel="shortcut icon" type="image/x-icon" href="images/P-Logo.png" />
<link href = "stylesheets/jquery-ui-themes-1.13.0/themes/ui-lightness/jquery-ui.css" rel = "stylesheet">
<title>Mobile Service Request (v<?= $version ?>) - Pierson Wireless</title>
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
$header_names = ['Service Request'];   //what will appear on the tabs
$header_ids = ['service_request'];      //must match a <div> element inside of body

echo create_navigation_bar($header_names, $header_ids, "", $user->info);

?>

<div id = 'service_request' class = 'tabcontent' style = 'display:none'>
	<h3 style = 'margin-bottom: 2em;'>Service Request Menu</h3>
	<table>
		<tr>
			<td>Quote Number</td>
			<td style = 'width: 60%;'><input id = 'service_request_quote' type = 'text' readonly></td>
		</tr>
		<tr>
			<td>Service Request Options</td>
			<td> 
				<select id='service_option' class = 'custom-select' onchange = 'show_service_options(this)'>
					<option></option>
					<option value = 'des'>Design</option>
					<option value = 'fse'>Field Service Engineering</option>
					<option value = 'ops'>Operations</option>
					<option value = 'cop'>Closeout & Documentation</option>
				</select>
			</td>
		</tr>
		<tr class = 'des_service_row service_row'>
			<td>Design</td>
			<td> 
				<select id='request_des_services' name='request_des_services' class = 'custom-select service_request_options des_service_input des_service_input_req' >
					<option></option>
					<?= create_select_options($des_services); ?>
				</select>
			</td>
		</tr>
		<tr class = 'des_service_row service_row'>
			<td>Design Due Date</td>
			<td> 
				<input type = 'date' id = 'request_design_due_date' class = 'service_request_options des_service_input'>
			</td>
		</tr>
		<tr class = 'des_service_row service_row'>
			<td>Estimation Due Date</td>
			<td> 
				<input type = 'date' id = 'request_estimation_due_date' class = 'service_request_options des_service_input'>
			</td>
		</tr>
		<tr class = 'fse_service_row service_row'>
			<td>Field Service Engineering</td>
			<td> 
				<select id='request_fse_services' class = 'custom-select service_request_options fse_service_input fse_service_input_req' >
					<option></option>
					<?= create_select_options($fse_service); ?>
				</select>
			</td>
		</tr>
		<tr class = 'fse_service_row service_row'>
			<td>FSE Type of Support</td>
			<td> 
				<select id='request_fse_type' class = 'custom-select service_request_options fse_service_input fse_service_input_req' onchange = 'show_fse_options(this)'>
					<option></option>
					<option>On-Site Support</option>
					<option>Remote Support</option>
				</select>
			</td>
		</tr>
		<tr class = 'fse_service_row fse_on_site_row service_row'>
			<td>FSE Date From</td>
			<td> 
				<input type = 'date' id = 'request_fse_date_from' class = 'service_request_options fse_service_input fse_service_input_req'>
			</td>
		</tr>
		<tr class = 'fse_service_row fse_on_site_row service_row'>
			<td>FSE Date To</td>
			<td> 
				<input type = 'date' id = 'request_fse_date_to' class = 'service_request_options fse_service_input fse_service_input_req'>
			</td>
		</tr>
		<tr class = 'ops_service_row service_row'>
			<td>Operations</td>
			<td> 
				<select id='request_ops_services' class = 'custom-select service_request_options ops_service_input ops_service_input_req' >
					<option></option>
					<?= create_select_options($ops_services, null, "task"); ?>
				</select>
			</td>
		</tr>
		<tr class = 'ops_service_row service_row'>
			<td>Ops Due Date</td>
			<td> 
				<input type = 'date' id = 'request_ops_due_date' class = 'service_request_options ops_service_input ops_service_input_req'>
			</td>
		</tr>
		<tr class = 'cop_service_row service_row'>
			<td>Closeout & Documentation</td>
			<td> 
				<select id='request_cop_services' class = 'custom-select service_request_options cop_service_input cop_service_input_req' >
					<option></option>
					<?= create_select_options($cop_task); ?>
				</select>
			</td>
		</tr>
		<tr class = 'cop_service_row service_row'>
			<td>COP Due Date (Default 10 Bus. Days)</td>
			<td> 
				<input type = 'date' id = 'request_cop_due_date' class = 'service_request_options cop_service_input cop_service_input_req' readonly>
				<input type = 'checkbox' onchange = 'enable_cop_due_date(this.checked);'><i style = 'padding-left: 5px;'>Expedite?</i>
			</td>
		</tr>
		<tr class = 'cop_service_row service_row'>
			<td>Design As Built Updates Required?</td>
			<td> 
				<select id='request_cop_as_built' class = 'custom-select service_request_options cop_service_input cop_service_input_req' >
					<option></option>
					<option>Yes</option>
					<option>No</option>
				</select>
			</td>
		</tr>
		<tr class = 'cop_service_row service_row'>
			<td>COP Distribution</td>
			<td> 
				<textarea id = 'request_cop_distribution' class = 'service_request_options cop_service_input cop_service_input_req'></textarea>
			</td>
		</tr>
		<tr>
			<td>Notes</td>
			<td> 
				<textarea id = 'request_note' class = 'service_request_options'></textarea>
			</td>
		</tr>
		<tr class = 'fse_service_row fse_on_site_row service_row'>
			<td colspan="2" class = 'fse_critical_content'>Kickoff Call with FSE requested.  Please schedule a time to discuss objectives and expectations with team members who will be involved in the project at least 24 hours in advance.</td>
		</tr>
	</table>
	<button onclick = 'process_service_request()' class = 'mobile_button_full'>Request Services</button>
</div>

<!-- <div id="spinner" style = 'display:none'></div> -->

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

<script>

    // Pass relevant data to js (constants)
    const user_info = <?= json_encode($user->info); ?>;

	// Pass relevant data to js (variables)
	var quote = <?= json_encode($quote); ?>,
		grid = <?= json_encode($grid); ?>;
	
	/**@author Alex Borchers
	 * Handles showing dialog of service request options
	 * Global param quote = $_GET from current quote loaded on page
	 * @returns void
	 */
	function show_service_request_options(){

		//reset all service options
		document.querySelectorAll('.service_request_options').forEach(function(a){
			a.value = "";
		})

		//transfer quote to reference input field & set service option to be blank
		u.eid("service_request_quote").value = quote;
		u.eid("service_option").value = "";

		// call function to show/hide relevant options
		show_service_options(u.eid("service_option"));
	}

	/**@author Alex Borchers
	 * Handles showing required fields for service options
	 * @param targ {HTML Entity} <select> that user has changed
	 * @returns void
	 */
	function show_service_options(targ){

		// hide all rows for different options
		document.querySelectorAll('.service_row').forEach(function(a){
			a.style.display = "none";
		})

		// if <select> value is blank, this means nothing is selected
		if (targ.value == "")
			return;

		// use targ.value to see the work-group that we need to show
		document.querySelectorAll('.' + targ.value + '_service_row').forEach(function(a){
			a.style.display = "table-row";
		})

		// check optional parameters for certain categories
		show_fse_options(u.eid("request_fse_type"));

		// If this is COP, default due date to 10 business days out
		if (targ.value == "cop"){
			u.eid("request_cop_due_date").value = add_business_days_to_date(10);
			u.eid("request_cop_distribution").value = u.eid("cop_distribution").value;
		}
	}

	// Small function to enalbe/disable COP request due date
	function enable_cop_due_date(desc){
		u.eid("request_cop_due_date").readOnly = !desc;
	}

	/**
	 * Handles showing FSE options for specific types of requests
	 * @author Alex Borchers
	 * @param {HTMLElement} targ (the <select> user is interacting with)
	 * @returns void
	 */
	function show_fse_options(targ){

		// depending on value, show/hide additional info
		if (targ.value == "On-Site Support"){
			document.querySelectorAll('.fse_on_site_row').forEach(function(a){
				a.style.display = "table-row";
			})
		}
		else{
			document.querySelectorAll('.fse_on_site_row').forEach(function(a){
				a.style.display = "none";
			})
		}
	}

	/**@author Alex Borchers
	 * Handles processing a service request
	 * @returns void
	 */
	function process_service_request(){

		// check to make sure that a user has an option selected
		if (u.eid("service_option").value == "")
			return;

		// check to see if any additional options are required
		if (u.eid("service_option").value == "fse" && u.eid("request_fse_type").value == "Remote Support"){
			
			// load FSE support questionaire, close current dialog
			load_fse_remote_support(u.eid("service_request_quote").value);
			return;
		}

		// init error variable
		var error = false;

		// if a service is selected, make sure necessary fields that may be required are filled in.
		// exclude remote support for time being
		if (!(u.eid("service_option").value == "fse" && u.eid("request_fse_type").value == "Remote Support")){
			document.querySelectorAll('.' + u.eid("service_option").value + '_service_input_req').forEach(function(field){
				if (field.value == ""){
					field.classList.add("required_error");
					error = true;
				}
				else
					field.classList.remove("required_error");
			})
		}

		// check for error
		if (error){
			alert("[Error] Please fill in all required fields.");
			return;
		}

		// grab required info and pass to server
		// init form data variable
		var fd = new FormData();
		
		// pass required info (service, due date, note)
		document.querySelectorAll('.' + u.eid("service_option").value + '_service_input').forEach(function(field){
			fd.append(field.id, field.value);
		})

		// if COP, pass SOW as well
		if (u.eid("service_option").value == "cop")
			fd.append("sow", grid.quote_SOW);

		fd.append("note", u.eid("request_note").value);

		// pass reference quote, tell, type & user info
		fd.append('quote', grid.quoteNumber);
		fd.append('tell', 'service_request');
		fd.append('type', u.eid("service_option").value);
		fd.append('user_info', JSON.stringify(user_info));

		// Show spinner to tell user we are working on it
		u.eid("service_request").style.display = "none";
		create_spinner(); // found in js_helper.js
		
		// ajax request to communicate with database
		$.ajax({
			url: 'application_helper.php',
			type: 'POST',
			processData: false,
			contentType: false,
			data: fd,
			success : function (response) {
				
				// if we found no results, let the user know
				if (response != ""){
					alert("[Error] Please screenshot and send to fst@piersonwireless.com. " + response);
					console.log(response);
					return;
				}

				// Convert spinner to checkmark
				$('.circle-loader').toggleClass('load-complete');
  				$('.checkmark').toggle();
				window.location.href = "mobile_home_ops.php";

				// Alert user of success
				alert ("The service request has been processed successfully");

			}
		});
	}

	/** 
	 * Handles loading fse_remote_support template from html_templates folder. From this file, we will always redirect to mobile version of form
	 * @author Alex Borchers
	 * @param quote {string} matches entry for fst_grid, used to determine parts able to request
	 * @returns void
	 */
	function load_fse_remote_support(quote){

		// Build link
		var note = u.eid("request_note").value.replace(/ /g, "_");
		var link = "mobile_fse_request.php?quote=" + quote + "&type=" + u.eid("request_fse_services").value
		if (note != "")
			link += "&note=" + note;

		window.open(link, "_blank");
		return;
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
		u.eid("service_request_quote").value = quote;
		show_service_request_options();
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