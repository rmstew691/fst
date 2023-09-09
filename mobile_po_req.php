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
include('phpFunctions_drive.php');
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

// Get quote info & subcontractor detail
$_GET['quote'] = "233-001632-011";
$sub_detail = [];
if (isset($_GET['quote'])){
	$quote = $_GET['quote'];

	// Project info
	$query = "SELECT * FROM fst_grid WHERE quoteNumber = '" . $quote . "';";
	$result = mysqli_query($con, $query);
	$grid = mysqli_fetch_array($result);

	// Sub detail
	$query = "SELECT * FROM fst_grid_subcontractors WHERE quoteNumber = '" . $quote . "';";
	$result = mysqli_query($con, $query);
	while($rows = mysqli_fetch_assoc($result)){
		array_push($sub_detail, $rows);
	}
}
else{
	$quote = "";
	$grid = [];
}

// write query to get sub contractors and compliance info
$subcontractors = [];
$query = "SELECT a.id, a.vendor, b.coi_verified, b.coi_expiration, b.w9_verified, a.poc, a.phone, a.email, a.pw_contact
			FROM fst_vendor_list a
			LEFT JOIN fst_vendor_compliance b
				ON a.id = b.id
			ORDER BY a.vendor;";
$result = mysqli_query($con, $query);
while($rows = mysqli_fetch_assoc($result)){
	array_push($subcontractors, $rows);
}

// Load options for user emails
$users_email = [];
$query = "SELECT email FROM fst_users ORDER BY firstName;";
$result = mysqli_query($con, $query);
while($rows = mysqli_fetch_assoc($result)){
	array_push($users_email, $rows['email']);
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

	/* style list elements */
	.google-drive-file-list{
		font-size: 12px;
		padding-inline-start: 0px;
		list-style: none;
	}

</style>
<!-- add any external style sheets here -->
<link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css'> 
<link rel='stylesheet' href='stylesheets/element-styles.css'> 
<link rel='stylesheet' href='stylesheets/mobile-element-styles.css'> 
<link rel="shortcut icon" type="image/x-icon" href="images/P-Logo.png" />
<link href = "stylesheets/jquery-ui-themes-1.13.0/themes/ui-lightness/jquery-ui.css" rel = "stylesheet">
<title>Mobile PO Request (v<?= $version ?>) - Pierson Wireless</title>
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
$header_names = ['PO Request'];   //what will appear on the tabs
$header_ids = ['po_request'];      //must match a <div> element inside of body

echo create_navigation_bar($header_names, $header_ids, "", $user->info);

?>

<div id = 'po_request' class = 'tabcontent'>
	<div id = po_req_page1>
		<h3 style = 'margin-bottom: 2em;'>Select a Sub</h3>
		<table>
			<tr>
				<th>Subs</th>
				<th>Price</th>
				<th>Description</th>
				<th>PO #</th>
			</tr>

			<?php

			// Iterate through subs and list out row for each
			foreach ($sub_detail as $sub){

				?>

				<tr>
					<td><?= $sub['vendor']; ?></td>
					<td><?= convert_money('%.2n', $sub['price']); ?></td>
					<td><?= $sub['short_description']; ?></td>
					<td>
						<?php
						
						// If we have a PO # already, output it, otherwise, output button for user to request po
						if ($sub['po_number'] == "" || $sub['po_number'] == null)
							echo "<button onclick = 'open_request_sub_po(" . $sub['id'] . ")'><i class='fa fa-paper-plane' aria-hidden='true'></i></button>";
						else
							echo $sub['po_number'];

						?>
					</td>
				</tr>

				<?php

			}

			?>

		</table>
	</div>
	<div id = 'po_req_page2' style = 'display:none'>
		<h3 style = 'margin-bottom: 2em;'>Required Info</h3>
		<div class = 'form_title'>
			Vendor Name
		</div>
		<div class = 'form_answer'>
			<input id = 'subcontractor_po_name' class = 'subcontractor_po_required' type = 'text' readonly>
			<input id = 'subcontractor_po_id' style = 'display:none;' readonly>
		</div>
		<div class = 'form_title'>
			Quote Amount
		</div>
		<div class = 'form_answer'>
			<input id = 'subcontractor_po_amount' class = 'subcontractor_po_required' type = 'text' readonly>
		</div>
		<div class = 'form_title'>
			Vendor Quote #
		</div>
		<div class = 'form_answer'>
			<input id = 'subcontractor_po_quote' class = 'subcontractor_po_required' type = 'text'>
		</div>
		<div class = 'form_title'>
			Contact Name
		</div>
		<div class = 'form_answer'>
			<input id = 'subcontractor_po_contact_name' class = 'subcontractor_po_required' type = 'text'>
		</div>
		<div class = 'form_title'>
			Contact Email
		</div>
		<div class = 'form_answer'>
			<input id = 'subcontractor_po_contact_email' class = 'subcontractor_po_required' type = 'text'>
		</div>
		<div class = 'form_title'>
			Email CC
		</div>
		<div class = 'form_answer'>
			<textarea id = 'subcontractor_po_cc_email' class = 'subcontractor_po_required emails'></textarea>
		</div>
		<div class = 'form_title'>
			Select Quote to Attach
		</div>
		<div class = 'form_answer'>
			<?= get_subcontractor_files($grid['googleDriveLink']); ?>
		</div>
		<button onclick = 'request_sub_po()' class = 'mobile_button_full'>Request PO</button>
		<button onclick = 'go_back()' class = 'mobile_button_full'>Go Back to Subs</button>
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

    // Pass relevant data to js (constants)
    const user_info = <?= json_encode($user->info); ?>,
		sub_detail = <?= json_encode($sub_detail); ?>,
		grid = <?= json_encode($grid); ?>,
		subcontractors = <?= json_encode($subcontractors); ?>,
		availableTags = <?= json_encode($users_email); ?>;
	
	/**@author Alex Borchers
	 * Handles opening dialog box to issue PO to accounts payable
	 * @param id {int} matches id from fst_grid_subcontractors db table
	 * 
	 * @returns void
	 */
	function open_request_sub_po(id){

		// get index in global array based on ID
		var index = sub_detail.findIndex(object => {
			return object.id == id;
		});

		// get index in global array for vendor
		var v_list_index = subcontractors.findIndex(object => {
			return object.vendor.toLowerCase() == sub_detail[index].vendor.toLowerCase();
		});

		// update info relevant to this sub
		u.eid("subcontractor_po_name").value = sub_detail[index].vendor;
		u.eid("subcontractor_po_id").value = sub_detail[index].id;
		u.eid("subcontractor_po_amount").value = accounting.formatMoney(sub_detail[index].price);
		u.eid("subcontractor_po_quote").value = sub_detail[index].customer_quote;
		u.eid("subcontractor_po_contact_name").value = subcontractors[v_list_index].poc;
		u.eid("subcontractor_po_contact_email").value = subcontractors[v_list_index].email;

		// highlight & select matching subcontractor documents
		var files = u.class("google-drive-file");
		var names = u.class("google-drive-file-name");

		for (var i = 0; i < files.length; i++){
			
			// if match, check & highlight, otherwise uncheck and unhighlight
			if (names[i].innerHTML.includes(sub_detail[index].vendor_id)){
				names[i].classList.add("highlight");
				files[i].checked = true;
			}
			else{
				names[i].classList.remove("highlight");
				files[i].checked = false;
			}

		}

		// Show new page, close current
		u.eid("po_req_page1").style.display = "none";
		u.eid("po_req_page2").style.display = "";
	}

	/**
	 * Handles going back to page 1
	 * @author Alex Borchers
	 */
	function go_back(){
		u.eid("po_req_page1").style.display = "";
		u.eid("po_req_page2").style.display = "none";
	}

	/**@author Alex Borchers
	 * Handles checking if subcontractor is complient
	 * @param sub {object} matches row from fst_vendor_compliance
	 * 
	 * @returns {boolean} (true = compliant, false = not compliant)
	 */
	function check_sub_compliance(sub){

		// convert stored date to date object, get today's date
		var coi_expiration = new Date(sub.coi_expiration);
		var today = new Date();

		// check fields required for compliance
		if (sub.w9_verified == "Y" && coi_expiration > today)
			return true;
		else
			return false;

	}

	/**@author Alex Borchers
	 * Handles requesting compliance for a given subcontractor
	 * @param subcontractor {object} matches $subcontractor object created earlier in file
	 * @param type {string} (po_request / add_to_quote / awarded) determines at which stage this request is being triggered
	 * @returns void
	 */
	function request_sub_compliance(subcontractor, type){

		// init form data variable
		var fd = new FormData();

		// pass sub & type& quote
		fd.append('subcontractor', subcontractor.vendor);
		fd.append('id', subcontractor.id);
		fd.append('w9_verified', subcontractor.w9_verified);
		fd.append('coi_verified', subcontractor.coi_verified);
		fd.append('coi_expiration', subcontractor.coi_expiration);
		fd.append('type', type);
		fd.append('quote', grid.quote);

		// pass tell & user info
		fd.append('tell', 'request_sub_compliance');
		fd.append('user_info', JSON.stringify(user_info));

		//ajax request to communicate with database
		$.ajax({
			url: 'application_helper.php',
			type: 'POST',
			processData: false,
			contentType: false,
			data: fd,
			success : function (response) {

				// if we found no results, let the user know
				if (response != ""){
					alert("There has been an error, please contact fst@piersonwireless.com for assistance. Official Message: " + response);
					console.log(response);
					return;
				}
			}
		});
	}

	/**@author Alex Borchers
	 * Handles requesting PO for subcontractor
	 */
	function request_sub_po(){

		// get index of sub in global array
		var index = subcontractors.findIndex(object => {
			return object.vendor.toLowerCase() == u.eid("subcontractor_po_name").value.toLowerCase();
		});

		// verify that sub is compliant
		var compliant = check_sub_compliance(subcontractors[index]);

		// if non-complient, send user message, still allow them to add to quote if they would like to
		if (!compliant){
			alert("[ERROR] Subcontractor is currently non-compliant. A notice is being sent directly to the subcontractor to update our documentation. You cannot request a purchase order until this has been updated.");
			request_sub_compliance(subcontractors[index], "po_request");
			return;
		}

		// check to make sure info is filled out correctly
		var error = check_submit(u.class("subcontractor_po_required"));

		// if error, alert user and send back
		if (error){
			alert("[ERROR] Please fill in all required fields.");
			return;
		}

		// init form data variable
		var fd = new FormData();

		// loop through class of select items
		var file_ids = [];
		var gdrive_files = u.class("google-drive-file");
		var gdrive_file_names = u.class("google-drive-file-name");

		for (var i = 0; i < gdrive_files.length; i++){
			//if checked, push to list
			if (gdrive_files[i].checked)
				file_ids.push({
					id: gdrive_files[i].id,
					name: gdrive_file_names[i].innerHTML
				});
		}

		// check to make sure we have at least 1 attachment included in request
		if (file_ids.length == 0){
			alert("[ERROR] At least 1 attachment is required.");
			return;
		}

		// pass file ids to form data
		fd.append('file_ids', JSON.stringify(file_ids));
		fd.append('file_reference', JSON.stringify([]));	// user attached files not allowed in mobile app

		// pass project & po specific info
		fd.append('quote', grid.quote);
		fd.append('quote_type', grid['quote_type']);
		fd.append('project_number', grid['vpProjectNumber']);
		fd.append('project_name', grid['location_name'] + " " + grid['phaseName']);

		// user entered
		fd.append('name', u.eid("subcontractor_po_name").value);
		fd.append('id', u.eid("subcontractor_po_id").value);
		fd.append('amount', accounting.unformat(u.eid("subcontractor_po_amount").value));
		fd.append('sub_quote', u.eid("subcontractor_po_quote").value);
		fd.append('contact_name', u.eid("subcontractor_po_contact_name").value);
		fd.append('contact_email', u.eid("subcontractor_po_contact_email").value);
		fd.append('email_cc', u.eid("subcontractor_po_cc_email").value);

		// pass tell & user info
		fd.append('tell', 'request_po');
		fd.append('user_info', JSON.stringify(user_info));

		// Show spinner to tell user we are working on it
		u.eid("part_request").style.display = "none";
		create_spinner(); // found in js_helper.js

		//ajax request to communicate with database
		$.ajax({
			url: 'application_helper.php',
			type: 'POST',
			processData: false,
			contentType: false,
			data: fd,
			success : function (response) {
				
				// check first 3 characters (should be FST, to prefix $po_number)
				var error_check = response.substr(0, 3);

				// if we found no results, let the user know
				if (error_check != "FST"){
					alert("There has been an error, please contact fst@piersonwireless.com for assistance. Official Message: " + response);
					console.log(response);
					return;
				}

				// Convert spinner to checkmark
				$('.circle-loader').toggleClass('load-complete');
				$('.checkmark').toggle();

				// otherwise, let user know that we have submitted the request successfully & close dialog
				alert("The request has been submitted successfully.");
				window.location.reload();
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