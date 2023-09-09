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
$_GET['quote'] = "233-001632-011";
if (isset($_GET['quote'])){
	$quote = $_GET['quote'];
	$query = "SELECT b.poc as 'pi_shipping_poc', b.poc_phone as 'pi_shipping_poc_phone', b.poc_email as 'pi_shipping_poc_email', 
					b.location_name as 'pi_shipping_location_name', b.street1 as 'pi_shipping_street1', b.street2 as 'pi_shipping_street2', 
					b.city as 'pi_shipping_city', b.state as 'pi_shipping_city', b.zip as 'pi_shipping_zip', 
					a.* FROM fst_grid a 
				LEFT JOIN fst_grid_address b
					ON a.quoteNumber = b.quoteNumber AND b.type = 'Shipping'
				WHERE a.quoteNumber = '" . $quote . "';";
	$result = mysqli_query($con, $query);
	$grid = mysqli_fetch_array($result);
}
else{
	$quote = "";
	$grid = [];
}

// Get the BOM for the project and check to see where these parts can be allocated from (checks primary first, then secondary, then OMA-2 by default)
$materials = [];
$primary = 'CHA-1';
$secondary = 'OMA-1';
$query = "SELECT a.*, 
			CASE
				WHEN a.mmd = 'Yes' THEN ''
				WHEN a.partNumber = b.partNumber AND b.`" . $primary . "` >= a.quantity THEN '" . $primary . "'
				WHEN a.partNumber = b.partNumber AND b.`" . $secondary . "` >= a.quantity THEN '" . $secondary . "'
				WHEN a.partNumber = b.partNumber AND  b.`OMA-2` >= a.quantity THEN 'OMA-2'
				ELSE ''
			END AS choice, 
		b.uom as in_inventory
		FROM fst_boms a
		LEFT JOIN invreport b ON b.partNumber = a.partNumber
		WHERE a.quoteNumber = '" . $quote . "' AND type = 'P' GROUP BY id ORDER BY mmd desc, partCategory, id;";
$result = mysqli_query($con, $query);
while($rows = mysqli_fetch_assoc($result)){
	array_push($materials, $rows);
}

// Convert list of materials to comma seperated string to inject into query
$part_string = custom_join(array_column($materials, 'partNumber'));

// Get possible reels related to request
$reels = [];
$query = "SELECT id, bulk, partNumber, shop, quantity - quantity_requested as 'quantity', location 
			FROM inv_reel_assignments 
			WHERE partNumber IN (" . $part_string . ") AND status = 'Available' AND quantity - quantity_requested > 0
			ORDER BY partNumber, bulk ASC, quantity DESC;";

$result = mysqli_query($con, $query);
while($rows = mysqli_fetch_assoc($result)){
	array_push($reels, $rows);	
}

// Get user email list
$users_email = [];
$query = "SELECT email FROM fst_users WHERE status = 'Active' ORDER BY firstName;";
$result = mysqli_query($con, $query);
while($rows = mysqli_fetch_assoc($result)){
	array_push($users_email, $rows['email']);	
}

// Get list of active parts
$active_parts = [];
$query = "SELECT partNumber FROM invreport WHERE active = 'True';";
$result = mysqli_query($con, $query);
while($rows = mysqli_fetch_assoc($result)){
	array_push($active_parts, $rows['partNumber']);	
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
	.ui-autocomplete {
		max-height: 150px;
		overflow-y: auto;
		overflow-x: hidden;
		z-index:4000 !important;
	}
	.description{
		font-size: 10px;
		font-style: italic;
	}

</style>
<!-- add any external style sheets here -->
<link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css'> 
<link rel='stylesheet' href='stylesheets/element-styles.css'>
<link rel='stylesheet' href='stylesheets/mobile-element-styles.css'> 
<link rel="shortcut icon" type="image/x-icon" href="images/P-Logo.png" />
<link href = "stylesheets/jquery-ui-themes-1.13.0/themes/ui-lightness/jquery-ui.css" rel = "stylesheet">
<title>Mobile Parts Request (v<?= $version ?>) - Pierson Wireless</title>
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
$header_names = ['Parts Request'];   //what will appear on the tabs
$header_ids = ['part_request'];      //must match a <div> element inside of body

echo create_navigation_bar($header_names, $header_ids, "", $user->info);

?>

<div id = 'part_request' class = 'tabcontent' style = 'display:none'>
  
  <div id = 'partsRequest-dialog-page1'>

	<h3 class = 'page_title'>Parts Request (page 1)</h3>
				
        <?php
				
		// Preset parts request info
		$partsRequestArray['requested_by'] = $user->info['firstName'] . " " . $user->info['lastName'];
		$partsRequestArray['cc_email'] = $user->info['email'];
		$partsRequestArray['cust_pn'] = $grid['custID'];
		$partsRequestArray['oem_num'] = $grid['oemNum'];
		$partsRequestArray['bus_unit_num'] = $grid['bus_unit_num'];
		$partsRequestArray['loc_id'] = $grid['loc_id'];
		
		?>

		<div class = 'form_title'>
			*To (semicolon-delimited):
		</div>
		<div class = 'form_answer'>
			<input class = "emails requiredPR" type = "text" class = 'email_address' name = 'email_to' id = 'email_to' form="saveInfo" value = 'alex.borchers@piersonwireless.com; '>
		</div>
		<div class = 'form_title'>
			*CC (semicolon-delimited):
		</div>
		<div class = 'form_answer'>
			<textarea class = "emails requiredPR" type = "text" name = 'email_cc' id = 'email_cc' form="saveInfo" ><?= $partsRequestArray['cc_email'] ?>; </textarea>
		</div>
		<div class = 'form_title'>
			*Created By:
		</div>
		<div class = 'form_answer'>
			<input class = "requiredPR pq_input" type = "text" name = 'createdBy' id = 'createdBy' form="saveInfo" value = '<?= $user->info['fullName']; ?>'>
		</div>
		<div class = 'form_title'>
			*Due By Date
		</div>
		<div class = 'form_answer'>
			<input class = "requiredSR" name = 'dueDate' id = 'dueDate' form="saveInfo" value = '' type = "date">
		</div>
		<div class = 'form_title'>
			Customer Bid # / Site ID:
		</div>
		<div class = 'form_answer'>
			<input type = "text" name = 'pq_custNum' id = 'pq_custNum' value = '<?= $partsRequestArray['cust_pn']; ?>' class = 'pq_input'>
		</div>
		<div class = 'form_title'>
			OEM Reg #:
		</div>
		<div class = 'form_answer'>
			<input type = "text" name = 'pq_oemReg' id = 'pq_oemReg' value ='<?= $partsRequestArray['oem_num']; ?>'  class = 'pq_input'>
		</div>
		<div class = 'form_title'>
			Business Unit #:
		</div>
		<div class = 'form_answer'>
			<input type = "text" name = 'pq_busUnit' id = 'pq_busUnit' value ='<?= $partsRequestArray['bus_unit_num']; ?>'  class = 'pq_input'>
		</div>
		<div class = 'form_title'>
			Customer Project ID:
		</div>
		<div class = 'form_answer'>
			<input type = "text" name = 'pq_locID' id = 'pq_locID' value ='<?= $partsRequestArray['loc_id']; ?>'  class = 'pq_input'>
		</div>
		<div class = 'form_title'>
			Additional Instructions:
		</div>
		<div class = 'form_answer'>
			<textarea name = 'additionalInfo' id = 'additionalInfo' form="saveInfo" rows = '5' placeholder="(Please list any additional instructions here that you would like to show up in the body of the email - are there specific part requirements, etc.)"></textarea>
		</div>
		<label for="pr_type1" class = 'pr_type_label'>Request Parts to be Staged</label>
		<input type="radio" name = 'pr_type' id="pr_type1" class = 'pr_type_radio' onclick = 'pr_type_required()'>
		<label for="pr_type2" class = 'pr_type_label'>Request Parts to Ship Right Away</label>
		<input type="radio" name = 'pr_type' id="pr_type2" class = 'pr_type_radio' onclick = 'pr_type_required()'><br>

		<div id = 'pr_ship_div' style = 'display:none'>
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
				<input class = "requiredSR" type = "text" name = 'contact_name' id = 'contact_name' form="saveInfo">
			</div>
			<div class = 'form_title'>
				*POC Phone Number:
			</div>
			<div class = 'form_answer'>
				<input class = "requiredSR" type = "text" name = 'contact_phone' id = 'contact_phone' form="saveInfo">
			</div>
			<div class = 'form_title'>
				Email Address(if available):
			</div>
			<div class = 'form_answer'>
				<input type = "text" name = 'contact_email' id = 'contact_email' form="saveInfo">
			</div>
			<h4>Shipping Location:</h4>
			<div class = 'form_title'>
				*Location Name:
			</div>
			<div class = 'form_answer'>
				<input class = "requiredSR" type = "text" name = 'shipping_location' id = 'shipping_location' form="saveInfo">
			</div>
			<div class = 'form_title'>
				*Shipping Address:
			</div>
			<div class = 'form_answer'>
				<input class = "requiredSR" type = "text" name = 'shipping_address' id = 'shipping_address' form="saveInfo">
			</div>
			<div class = 'form_title'>
				*City:
			</div>
			<div class = 'form_answer'>
				<input class = "requiredSR" type = "text" name = 'shipping_city' id = 'shipping_city' form="saveInfo">
			</div>
			<div class = 'form_title'>
				*State:
			</div>
			<div class = 'form_answer'>
				<select class = "custom-select requiredSR" name = 'shipping_state' id = 'shipping_state' form="saveInfo">
					<option></option>
					<?= create_select_options($states); ?>
				</select>
			</div>
			<div class = 'form_title'>
				*Zip:
			</div>
			<div class = 'form_answer'>
				<input class = "requiredSR" type = "text" name = 'shipping_zip' id = 'shipping_zip' form="saveInfo">
			</div>
			<div class = 'form_title'>
				Liftgate Required?
			</div>
			<div class = 'form_answer'>
				<select class = 'custom-select' name = 'liftgateOpt' id = 'liftgateOpt' form="saveInfo">
					<option value="N">N</option>
					<option value="Y">Y</option>
				</select>
			</div>
			<div class = 'form_title'>
				Scheduled Delivery Required?
			</div>
			<div class = 'form_answer'>
				<select class = 'custom-select' name = 'delivOpt' id = 'delivOpt' form="saveInfo" onchange = "scheduleSelect(this.value)">
					<option value="N">N</option>
					<option value="Y">Y</option>
				</select>
			</div>
			<div id = 'schedRow1' style = 'display:none;'>
				<div class = 'form_title'>
					Scheduled Delivery Date
				</div>
				<div class = 'form_answer'>
					<input name = 'schedDate' id = 'schedDate' form="saveInfo" type = "date">
				</div>
			</div>
			<div id = 'schedRow2' style = 'display:none;'>
				<div class = 'form_title'>
					Scheduled Delivery Time
				</div>
				<div class = 'form_answer'>
					<input name = 'schedTime' id = 'schedTime' form="saveInfo" value = '' type = "time">
				</div>
			</div>
		</div>
		<i>Parts requested after 2PM CT may not be processed until the next day. <br>(any overnight shipments will be done on a best effort basis but cannot be guaranteed)</i>
		<button id='partsReqPage2' class = 'mobile_button_full'>Go To Page 2</button>
	</div>

	<div id = 'partsRequest-dialog-page2' style = 'display: none'>
		<h4>Please select parts/quantities to be allocated</h4>
		<table id = 'partsReq-table' class = 'color_tbody' style = "line-height:20px;">
			<tr>
				<td style = "text-align: center; border: 0px; background-color: white;"> <!Requested Column>
					<input checked type = 'checkbox' onchange = "changeRequest(this.checked)"/>
				</td>
			</tr>
			<tr>
				<th>Req?</th>
				<th>Part #</th>
				<th>Qty Req</th>
				<th>Qty BOM</th>
				<th>Prev Req</th>
				<!-- <th>Pull From</th>
				<th>MO/PO</th>
				<th>Allow Sub?</th> -->
			</tr>

			<?php

			//loop through parts and add to parts request form as well
			foreach ($materials as $material){

				//initialize checkbox to be checked or disabled (certain misc parts are not allowed to be ordered)
				if ($material['partNumber'] == "Erico-Caddy" || $material['partNumber'] == "miscellaneous" || $material['partNumber'] == "FireRetardantBackbrd")
					$checkbox = "disabled";
				else
					$checkbox = "checked";

				?>

				<tr>
					<td style = "text-align: center">
						<input <?= $checkbox; ?> type = 'checkbox' id = "<?= $material['id']; ?>" class = 'request' />
					</td>
					<td><span class = 'part_number'><?= $material['partNumber']; ?></span><br><span class = 'description'><?= $material['description']; ?></span></td>
					<td>
						<input type = "number" class = 'quantity' value = "<?= $material['quantity'] - $material['allocated']; ?>" max = '<?= $material['quantity'] - $material['allocated']; ?>' min = '0' onchange = 'partQuantity(this)'>
					</td>
					<td>
						<?= $material['quantity']; ?>
					</td>
					<td>
						<?= $material['allocated'] ?>
					</td>
				</tr>
				<?php
			}
			?>
			<tr>
				<td style = "text-align: center">
					<input type = 'checkbox' class = 'extra_request'>
				</td>
				<td>
					<input type = 'text' class = 'extra_part_number' onchange = 'validate_part(this)'>
				</td>
				<td>
					<input type = "number" class = 'extra_quantity' onchange = 'validate_part(this)'>
				</td>
				<td></td><td></td>
			</tr>
		</table>	
		<button id = 'pq_button' class = 'mobile_button_full' onclick="check_for_reels(false)" >Submit Request</button>
		<button id='partsReqPage1' class = 'mobile_button_full' onclick = 'show_hide_divs("partsRequest-dialog-page1", "partsRequest-dialog-page2")'>Go To Page 1</button>	
	</div>

	<div id = 'reel_request_dialog' style = 'display: none'>

		<h4>Some available reels have been identified for 1 or more parts that you are requesting. Please select from the list of reels</h4>
		<h4 style = 'background-color: yellow'>NOTE 1: Leave reels unchecked if you do not want to request any reels.</h4>
		<h4 style = 'background-color: yellow'>NOTE 2: Some Bulk Reels (BR) may be available. Please input the quantity you would like from these reels, otherwise the reels are required to be selected in full. Please reach out to Dustin if what is shown does not work for your job.</h4>

		<div id = 'reel_request_div'>
			<!-- a table for each part # with applicable reels will be added here via add_applicable_reels() -->			
		</div>

		<button onclick = 'sendEmail()' id = 'reels_opt1'>Process Request</button>
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
		  materials = <?= json_encode($materials); ?>,
		  availableTags = <?= json_encode($users_email); ?>,
		  active_parts = <?= json_encode($active_parts); ?>;

	// Create list of lower case parts for searching
	const active_parts_lc = active_parts.map(function(element) {
		return element.toLowerCase();
	});
	
	// Create autocomplete drop-down for new parts entered
	var part_options = {
		source: active_parts,
		minLength: 1
	};
	
	// On keydown, show autocomplete menu for target class
	$(document).on('keydown.autocomplete', 'input.extra_part_number', function() {
		$(this).autocomplete(part_options);
	});
	
	// Depending on value of PR type, show/hide relevant info
	function pr_type_required(){
		if (u.eid("pr_type2").checked){
			u.eid("pr_ship_div").style.display = "block";

			// pre-fill shipping info (if available)
			u.eid("contact_name").value = grid.pi_shipping_poc;
			u.eid("contact_phone").value = grid.pi_shipping_poc_phone;
			u.eid("contact_email").value = grid.pi_shipping_poc_email;
			u.eid("shipping_address").value = grid.pi_shipping_street1 + " " + grid.pi_shipping_street2;
			u.eid("shipping_city").value = grid.pi_shipping_city;
			u.eid("shipping_state").value = grid.pi_shipping_state;
			u.eid("shipping_zip").value = grid.pi_shipping_zip;
		}
		else{
			u.eid("pr_ship_div").style.display = "none";

			// unset shipping info
			u.eid("contact_name").value = "";
			u.eid("contact_phone").value = "";
			u.eid("contact_email").value = "";
			u.eid("shipping_address").value = "";
			u.eid("shipping_city").value = "";
			u.eid("shipping_state").value = "";
			u.eid("shipping_zip").value = "";
		}
	}
	
	// Used to show parts request page 2
	$('#partsReqPage2').on('click', function() {
		
		// init error vairable
		var error = false;
		var first_elt = null;
		
		// loop through all required inputs
		$(".requiredPR").each(function(){
			
			//reset background to regular colorByRowLabel
			this.classList.remove("required_error");
			
			// Test if the div element is empty, if so, set background to yellow and flip error to true
			if (!$(this).val()){
				this.classList.add("required_error");
				error = true;
				if (first_elt === null)
					first_elt = this;
			}
		});

		// check to make sure parts are requested to be staged or ship right away
		if (!u.eid("pr_type1").checked && !u.eid("pr_type2").checked){
			error = true;
			alert("Please select what type of request this is.");
			return;
		}

		// if pr_type2 (ship right away) make sure all required info is filled out
		if (u.eid("pr_type2").checked){
			$(".requiredSR").each(function(){

				//reset background to regular colorByRowLabel
				this.classList.remove("required_error");
				
				// Test if the div element is empty, if so, set background to yellow and flip error to true
				if (!$(this).val()){
					this.classList.add("required_error");
					error = true;
					if (first_elt === null)
						first_elt = this;
				}
			});
		}
		
		//only move to next page if all required elements have been filled out
		if (!error){
			u.eid("partsRequest-dialog-page1").style.display = "none";
			u.eid("partsRequest-dialog-page2").style.display = "block";

			// Scroll to top
			window.scrollTo({
				top: 0,
				behavior: "instant"
			});
		}
		// Alert user, scroll first elt into view
		else{
			alert("[Error] Please fill in all required fields (highlighted in yellow).");
			window.scrollTo({
				top: first_elt.offsetTop - 80,
				behavior: "smooth" // Use smooth scrolling animation
			});
		}
	});

	/**
	 * Handles showing/hiding HTML elements
	 * @param {String} show (element to show)
	 * @param {String} hide (element to hide)
	 */
	function show_hide_divs(show = null, hide = null){
		if (show !== null)
			u.eid(show).style.display = "block";
		if (hide !== null)
			u.eid(hide).style.display = "none";

		// Scroll to top
		window.scrollTo({
			top: 0,
			behavior: "instant"
		});
	}

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
			u.eid("contact_name").value = "";
			u.eid("contact_phone").value = "";
			u.eid("contact_email").value = "";
			
			//shipping info
			u.eid("shipping_address").value = "";
			u.eid("shipping_city").value = "";
			u.eid("shipping_state").value = "";
			u.eid("shipping_zip").value = "";
			u.eid("shipping_location").value = "";
			
		}
		
		else{

			while (tempLen1 > 0 && currSpot < 9){
				tempLen2 = address.indexOf("|", tempLen1)
				location[currSpot] = address.substring(tempLen1, tempLen2);
				tempLen1 = tempLen2 + 1;
				currSpot++;
			}
			
			//contact info 
			u.eid("contact_name").value = location[1];
			u.eid("contact_phone").value = location[2];
			u.eid("contact_email").value = location[3];
			
			//shipping info
			u.eid("shipping_address").value = location[4];
			u.eid("shipping_city").value = location[5];
			u.eid("shipping_state").value = location[6];
			u.eid("shipping_zip").value = location[7];
			u.eid("shipping_location").value = location[8];			
		}
	}

	/**
	 * Handles saving customer addresses to database
	 * @author Alex Borchers
	 * @param {String} desc (type of customer)
	 */
	function addressHandler(desc){
		var name = u.eid("shipping_name").value, 
			address = u.eid("shipping_address").value, 
			city = u.eid("shipping_city").value, 
			state = u.eid("shipping_state").value, 
			zip = u.eid("shipping_zip").value, 
			location = u.eid("shipping_location").value, 
			customer = u.eid("customer").value;			
		
		$.ajax({
			type : "POST",  //type of method
					url  : "addressHandler.php",  //your page
					data : { 	
							name : name, 
							address : address, 
							city : city, 
							state : state, 
							zip : zip, 
							location : location, 
							customer : customer,
							desc : desc
						},
			success : function (response) {
				if (response == "updated")
					alert("Thank you, your address has been updated successfully. Your new details will take place once the page has been refreshed.")
				else if (response == "added")
					alert("Thank you, your address has been added successfully. You will be able to see this in the Customer Dropdown for any projects related to " + customer + " (once you refresh the page).");
				else
					alert("There has been an error. Please send URL link to the allocations email manually. Please screenshot this error and send to fst@piersonwireless.com for troubleshooting. Sorry for the inconvenience.\n Official Response: " + response);
				
			}
		});
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

	function changeRequest(value){
		document.querySelectorAll('.request').forEach(function(obj){
			obj.checked = value;
		})
	}

	/**
	 * Restricts user from going over max for any reason
	 * @author Alex Borchers
	 * @param {HTMLElement} targ <input> changed
	 */
	function partQuantity(targ){		
		if (targ.value > targ.max)
			targ.value = targ.max;
	}
		
	/**
	 * Handles validating extra part #'s entered by user (and adding a new row if applicable)
	 * @author Alex Borchers
	 * @param {HTML Element} targ <input> changed - may be part or quantity
	 */
	function validate_part (targ){

		// If part #, check if blank (no action required)
		if (targ.classList[0] == "extra_part_number" && targ.value == "")
			return;

		// If quantity, check if 0 quantity (no action required)
		if (targ.classList[0] == "extra_quantity" && parseInt(targ.value) == 0)
			return;

		// Get part & quantity classes
		var extra_parts = u.class("extra_part_number");
		var extra_quantity = u.class("extra_quantity");
		var extra_request = u.class("extra_request");
		
		// Iterate through classes, look for matching index
		for (var i = 0; i < extra_parts.length; i++){
			if (targ == extra_parts[i] || targ == extra_quantity[i])
				break;
		}

		// If did not find a match, report error
		if (i == extra_parts.length){
			alert("[Error] The system was unable to process your request. Please reach out to fst@piersonwireless.com for help. Any additional parts may not be processed.");
			return;
		}

		// Validate part # and add new row if necessary
		var index = active_parts_lc.indexOf(extra_parts[i].value.toLowerCase());

		// If no match found, alert error to user
		if (index == -1){
			alert("[Error] Additional parts must be active in our catalog. New part creation is not supported on the mobile version. Please visit the web application to make this request.");
			return;
		}

		// Update with correct case matching
		extra_parts[i].value = active_parts[index];

		// If part is valid & qty > 0, and this is the last element in the class list, add new row
		if (parseInt(extra_quantity[i].value) > 0 && i == extra_parts.length - 1){
			extra_request[i].checked = true;
			add_additional_pr_row();
		}		
	}
	
	/**
	 * Handles adding new row to parts request table (allows for unlimited additional parts)
	 * @author Alex Borchers
	 */
	function add_additional_pr_row (){
		
		// Get table
		var table = u.eid("partsReq-table");
		
		// Insert new row at bottom of table
		var row = table.insertRow(-1);
		
		// Checkbox
		var cell = row.insertCell(0);
		cell.style.textAlign = 'center';
		cell.innerHTML = '<input type="checkbox" class = "extra_request">';
		
		// Part Number
		var cell = row.insertCell(1);
		cell.innerHTML = '<input type="text" class="extra_part_number" onchange="validate_part(this)">';

		// Qty
		var cell = row.insertCell(2);
		cell.innerHTML = '<input type="number" class="extra_quantity" onchange="validate_part(this)">';
		
		// Blank filler cells
		var cell = row.insertCell(3);
		var cell = row.insertCell(4);
		
	}

	/**
	 * Handles checking if there are any reels availabe before sending a parts request
	 * @author Alex Borchers
	 * @param {Boolean} ship_right_away (T/F)
	 * @returns void
	 */
	function check_for_reels(ship_right_away){

		// Get value to see if this is being shipped right away
		var ship_right_away = u.eid("pr_type2").checked;

		//init array to hold list of reels applicable to project & unique parts
		var applicable_reels = [], unique_parts = [], quantities = [];

		// Get classes need to make decisions
		var request = u.class("request"),
			qty = u.class("quantity"),
			part = u.class("part_number");

		//loop through BOM and see if there are any matching reels
		for (var i = 0; i < request.length; i++){

			//check if part is selected to be requested
			if (request[i].checked && parseInt(qty[i].value) > 0){

				//look for match in reels array
				var curr_reels = reels.filter(function (r) {
					return r.partNumber.toLowerCase() == part[i].innerHTML.toLowerCase() &&
							r.shop.substr(0, 3) == materials[i].choice.substr(0, 3);
				});

				//push to array if we find any
				if (curr_reels.length > 0){

					//check if we've added this in unique_parts, if not, then push
					if (unique_parts.indexOf(part[i].innerHTML) == -1){

						applicable_reels.push(curr_reels);
						unique_parts.push(part[i].innerHTML);
						quantities.push(parseInt(qty[i].value));

					}
					else{
						//update quantities
						quantities[unique_parts.indexOf(part[i].innerHTML)] += parseInt(qty[i].value);
					}
				}
			}			
		}

		//if we have potential reels, display to user before sending request
		if (applicable_reels.length > 0){

			// run function to add reels and show dialog
			add_applicable_reels(applicable_reels, unique_parts, quantities);

		}
		// otherwise send email like normal
		else
			sendEmail();
	}

	// handles adding tables to dialog box which allow users to select any reels they would like to apply to their request
	// param 1 = reels applicable to parts on the BOM (found in check_for_reels)
	// param 2 = unique list of parts that have reels
	// param 3 = quantities related to each part
	function add_applicable_reels(applicable_reels, u_parts, quantities){

		//remove any previously added tables
		document.querySelectorAll('.reel_selection_tables').forEach(function(a){
			a.remove()
		})

		//loop through parts, create a new table for each part and add a row for each potential reel
		for (var i = 0; i < u_parts.length; i++){
			
			//create table
			var table = document.createElement("table");
			table.classList.add("standardTables");			//general table styling for our application
			table.classList.add("reel_selection_tables")	//class added so we can remove it if needed later

			//create first row (header for part name)
			var row = table.insertRow(-1);
			var cell = row.insertCell(0);
			cell.classList.add("reel_header");	//bolds head text, removes border
			cell.colSpan = 4;
			cell.innerHTML = "Part #: " + u_parts[i];

			//add header rows
			var headers = ['Request', 'Reel ID', 'Quantity Selected', 'Quantity Available'];
			var row = table.insertRow(-1);

			for (var j = 0; j < headers.length; j++){
				var cell = row.insertCell(j);
				cell.classList.add("reel_header");	//bolds head text, removes border
				cell.innerHTML = headers[j];
			}

			//loop through applicable reels, related to this part, push rows to table
			for (var j = 0; j < applicable_reels[i].length; j++){

				//add new row
				var row = table.insertRow(-1);

				//add checkbox
				var cell = row.insertCell(0);
				cell.style.textAlign = "center";
				cell.innerHTML = "<input type = 'checkbox' class = 'use_reel_checkbox' onchange = 'update_reel_totals(this)'>";

				//add reel ID
				var cell = row.insertCell(1);
				cell.classList.add("use_reel_id");

				//add pre-fix "BR" to code if this is a bulk reel
				if (parseInt(applicable_reels[i][j].bulk) == 1)
					cell.innerHTML = "BR" + applicable_reels[i][j].id;
				else
					cell.innerHTML = applicable_reels[i][j].id;
				
				//add input for quantity field (allow to be editable if bulk)
				var cell = row.insertCell(2);

				if (parseInt(applicable_reels[i][j].bulk) == 1)
					cell.innerHTML = "<input type = 'number' max = '" + applicable_reels[i][j].quantity + "' class = 'use_reel_quantity' value  = '" + applicable_reels[i][j].quantity + "' onchange = 'update_reel_totals(this)'>";	
				else
					cell.innerHTML = "<input type = 'number' readonly class = 'use_reel_quantity' value  = '" + applicable_reels[i][j].quantity + "'>";	

				//add quantity available for reel
				var cell = row.insertCell(3);
				cell.innerHTML = applicable_reels[i][j].quantity;	
				
			}

			//insert summary information
			//row quantity on BOM
			var row = table.insertRow(-1);
			var cell = row.insertCell(0);
			//cell.classList.add("reel_header");	//bolds head text, removes border
			cell.colSpan = 2;
			cell.innerHTML = "Quantity on BOM:"

			var cell = row.insertCell(1);
			cell.colSpan = 2;
			cell.innerHTML = quantities[i];

			//row quantity from reels
			var row = table.insertRow(-1);
			var cell = row.insertCell(0);
			//cell.classList.add("reel_header");	//bolds head text, removes border
			cell.colSpan = 2;
			cell.innerHTML = "Quantity from Reels:"

			var cell = row.insertCell(1);
			cell.innerHTML = 0;
			cell.colSpan = 2;
			cell.classList.add("reel_quantity_total");
			
			//push new table to div
			u.eid("reel_request_div").appendChild(table);

		}

		// show reel request div so user can make selection
		u.eid("reel_request_dialog").style.display = "block";
	}

	// global used to collect any select reels from the reel request form 
	// will be in the form { partNumber : 'example', 'reels' : ['reel1', 'reel2',...]}
	var user_selected_reels = [];

	// handles updating reel totals when different checkboxes are clicked
	function update_reel_totals(targ){

		//work way back to table tag
		var td = targ.parentNode;
		var tr = td.parentNode;
		var tbody = tr.parentNode;
		var table = tbody.parentNode;

		var checkboxes = table.querySelectorAll(".use_reel_checkbox");
		var quantities = table.querySelectorAll(".use_reel_quantity");
		var reel_ids = table.querySelectorAll(".use_reel_id");

		//loop through checkboxes & add quantity together if checked
		var total = 0, use_reels = [];

		for (var i = 0; i < checkboxes.length; i++){

			//.checked needs to be true
			if (checkboxes[i].checked){
				total += parseInt(quantities[i].value);	//add sum of quantities so far

				//if reel is BR, chop off first two letters (Reels are saved with 2 digit prefix codes)
				var use_id = reel_ids[i].innerHTML;
				var bulk = 0;
				if (use_id.includes("BR")){
					use_id = use_id.substr(2);
					bulk = 1;
				}

				//add to use_reels
				use_reels.push({
					reel: use_id,
					bulk: bulk,
					quantity: quantities[i].value
				});

				console.log(use_reels);
			}
		}

		//add to total reels line
		var total_reel = table.querySelectorAll(".reel_quantity_total");
		total_reel[0].innerHTML = total;

		//get part number
		var part_header = table.querySelectorAll(".reel_header");
		var part = part_header[0].innerHTML.substr(8);

		//check if we have saved anything for this reel
		var index = user_selected_reels.findIndex(object => {
			return object.partNumber == part;
		});

		//if we do not find a match, push new values
		if (index == -1){
			user_selected_reels.push(({
				partNumber : part,
				reels : use_reels
			}))
		}
		//otherwise, update existing values
		else{
			user_selected_reels[index].reels = use_reels;
		}
	}
	
	// Send parts request email to allocations and write material order info to database.
	function sendEmail(){

		//initalize form data (will carry all form data over to server side)
		var fd = new FormData();

		//initialize variables to be used in next loop
		var parts_requested = [], mmd = "No";

		// Get classes need to make decisions
		var request = u.class("request"),
			qty = u.class("quantity"),
			part = u.class("part_number");
		
		// Loop through full list of materials
		for (var i = 0; i < request.length; i++){
			
			// Check to see if any of the parts are MMD
			if (materials[i].mmd == "Yes")
				mmd = "Yes";
			
			// Convert qty to float
			var quantity = parseFloat(qty[i].value);
			
			//if user has checked the request box & the quantity is greater than 0, add to the parts_requested
			if (request[i].checked && quantity > 0){

				//init temporary reels list. check to see if we have any reels selected
				var use_reels = [];

				var index = user_selected_reels.findIndex(object => {
					return object.partNumber == part[i].innerHTML;
				});

				//if index is not -1 (we found a match).. use reels collected at an earlier point (in update_reel_totals())
				if (index != -1)
					use_reels = user_selected_reels[index].reels;					

				// push object for all info
				parts_requested.push({
					description: materials[i].description,
					manufacturer: materials[i].manufacturer,
					partNumber: part[i].innerHTML,
					quantity: quantity,
					location: materials[i].choice,
					subs: materials[i].subs,
					mmd: materials[i].mmd,
					id: materials[i].id,
					allocated: parseFloat(materials[i].allocated),
					decision: "Mobile (NA)",
					use_reels: use_reels
				});
			}
		}
		
		// Get classes need to make on additional parts
		var extra_request = u.class("extra_request"),
			extra_part_number = u.class("extra_part_number"),
			extra_quantity = u.class("extra_quantity");
		
		// Loop through full list of materials
		for (var i = 0; i < extra_request.length; i++){
			
			// Convert qty to float
			var quantity = parseFloat(extra_quantity[i].value);

			// Get index of part in inventory
			var index = active_parts_lc.indexOf(extra_part_number[i].value.toLowerCase());
			
			// If part is requested, qty > 0, and we have it in our catalog, then add to list of parts
			if (extra_request[i].checked && quantity > 0 && index != -1){		

				// push object for all info
				parts_requested.push({
					description: 'Manually Entered',
					manufacturer: '',
					partNumber: extra_part_number[i].value,
					quantity: quantity,
					location: 'Add Part',
					subs: '',
					mmd: 'No',
					id: '',
					allocated: '',
					decision: "Mobile (NA)",
					use_reels: []
				});
			}
		}

		//if no parts have been read in, send error message
		if (parts_requested.length == 0){
			alert("There are no parts in this request, please review the info and submit again.");
			return;
		}

		console.log(parts_requested);
		return;
		
		//transfer all other information to our form data variable
		fd.append('createdBy', u.eid('createdBy').value);
		fd.append('delivOpt', u.eid('delivOpt').value);
		fd.append('liftgateOpt', u.eid('liftgateOpt').value);
		fd.append('contact_name', u.eid('contact_name').value);
		fd.append('contact_phone', u.eid('contact_phone').value);
		fd.append('contact_email', u.eid('contact_email').value);
		fd.append('shipping_address', u.eid('shipping_address').value);
		fd.append('shipping_city', u.eid('shipping_city').value);
		fd.append('shipping_state', u.eid('shipping_state').value);
		fd.append('shipping_zip', u.eid('shipping_zip').value);
		fd.append('shipping_location', u.eid('shipping_location').value);
		fd.append('staging_location', grid.staging_location);
		fd.append('dueDate', u.eid('dueDate').value);
		fd.append('schedDate', u.eid('schedDate').value);
		fd.append('schedTime', u.eid('schedTime').value);
		fd.append('location_name', grid.location_name);
		fd.append('VPNum', grid.vpProjectNumber);
		fd.append('email_to', u.eid("email_to").value);
		fd.append('email_cc', u.eid("email_cc").value);
		fd.append('additionalInfo', u.eid("additionalInfo").value);
		fd.append('oemNum', u.eid("pq_oemReg").value);
		fd.append('custNum', u.eid("pq_custNum").value);
		fd.append('bus_unit_num', u.eid("pq_busUnit").value);
		fd.append('loc_id', u.eid("pq_locID").value);
		fd.append('mmd', mmd);
		fd.append('quote', grid.quoteNumber);

		//serialize arrays so we can pass them to php
		fd.append('parts_requested', JSON.stringify(parts_requested));
		fd.append('user_info', JSON.stringify(user_info));

		// Push request type
		if (u.eid("pr_type2").checked)
			fd.append('pr_type', "Request to Ship Right Away");
		else
			fd.append('pr_type', "Request to be Staged");
		
		//only send new part info if we have that info
		if (new_part_info.length > 0)
			fd.append('new_part_info', JSON.stringify(new_part_info));

		// Show spinner to tell user we are working on it
		u.eid("part_request").style.display = "none";
		create_spinner(); // found in js_helper.js
		
		//send info to server through ajax request
		$.ajax({
			type : "POST",  //type of method
			url  : "sendEmail.php",  //your page
			processData: false,
			contentType: false,
			data : fd,
			success : function (response) {

				//unlock button
				u.eid("pq_button").disabled = false;
				
				if (response == "success"){
					// Alert user and refresh
					alert("The email has been sent successfully.");
					
					// Convert spinner to checkmark
					$('.circle-loader').toggleClass('load-complete');
					$('.checkmark').toggle();
					window.location.href = "mobile_home_ops.php";
				}
				else{
					alert("Please screenshot this error and send to fst@piersonwireless.com. " + response);
				}
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

		//render checkbox for parts request type as jquery elements
		$( function() {
			$( ".pr_type_radio" ).checkboxradio();
		});
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