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

//send user error message if one exists
if (isset($_SESSION['errorMessage']) && $_SESSION['errorMessage'] !== "")
	$session_error = $_SESSION['errorMessage'];
else
	$session_error = "";
	
//reset error message
unset($_SESSION['errorMessage']);

// get notification keys
$notification_keys = [];
$query = "SELECT * FROM fst_users_notifications_key ORDER BY group_name;";
$result = mysqli_query($con, $query);
while($rows = mysqli_fetch_assoc($result)){
	array_push($notification_keys, $rows);
}

// get notification key groups
$notification_groups = [];
$query = "SELECT group_name FROM fst_users_notifications_key GROUP BY group_name;";
$result = mysqli_query($con, $query);
while($rows = mysqli_fetch_assoc($result)){
	array_push($notification_groups, $rows['group_name']);
}

// Read in PW personnel
$des = [];
$qc = [];
$fstUsers = "SELECT firstName, lastName, des, qc from fst_users order by firstName";
$result = mysqli_query($con, $fstUsers);
while($rows = mysqli_fetch_assoc($result)){	
	//check if designer or Estimator
	if ($rows['des'] == "checked")
		array_push($des, $rows['firstName'] . " " . $rows['lastName']);
	if ($rows['qc'] == "checked")
		array_push($qc, $rows['firstName'] . " " . $rows['lastName']);
}

?>

<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="google-signin-client_id" content="573761357198-hin7ae7q19qgvoab7t0781b41530546g.apps.googleusercontent.com">
<link rel="shortcut icon" type="image/x-icon" href="images/P-Logo.png" />
<link rel="stylesheet" href="stylesheets/element-styles.css?<?= $version; ?>1">
<link rel="stylesheet" href="stylesheets/dashboard-styles.css?<?= $version; ?>">
<link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css'> 
<link href = "stylesheets/jquery-ui-themes-1.13.0/themes/ui-lightness/jquery-ui.css" rel = "stylesheet">
<title>Home (v<?= $version ?>) - Pierson Wireless</title>
	
<style>

	<?php

	// If this user is not a manager, make sure certain fields are not showing. 
	if ($fstUser['manager'] != "checked"){
		echo 
		"#notification_settings_table tr > *:nth-child(4), #notification_settings_table tr > *:nth-child(5), .show_manager {
			display: none;
		}";
	}

	?>

	.pw_logo_wrapper{
		margin-top: 1em;
	}

	#market_table {
		border-collapse: collapse;
		margin: 1em;
	}	

	#market_table th {
		padding: 9px;
	}

	#market_table td {
		border: 1px solid #555555;
    	padding: 9px;
	}

	.fa-info-circle{
		margin-right: 3px;
		color: #114C95;
	}
	.tooltip:hover {
		cursor: help;
		position: relative;
	}
	.tooltip p {
		display: none;
	}
	.tooltip:hover p {
		display: block;
		z-index: 150;
		left: 0px;
		margin: 13px;
		margin-left:75px;
		width: 300px;
		position: absolute;
		top: 14px;
		text-decoration: none;
		padding: 12px 21px;
		background: #eeeeee;
		color: black;
		border-radius: 20px;
		font: bold 15px "Helvetica Neue", Sans-Serif;
		box-shadow: 0px 0px 3px 0px black;
	}

	.notification_td{
		text-align: center;
		-ms-transform: scale(1.2);
		-webkit-transform: scale(1.2);
		transform: scale(1.2);
	}

	/**styles search divs */
	.search_div{
		padding:1em;
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

	.notification_group td{
		border:none !important;
		font-weight:bold;
		padding-top:1em !important;
	}

	/** updating padding of profile tab */
	#profile{
		padding-top: 4em;
	}
	
	/**styles added to profile settings table on profile tab */
	.profileTable{
		border-collapse: collapse;
	}

	.profileTable td{
		border: 1px solid #000000;
		padding: 8px;
	}
	.profileTable th{
		padding: 10px;
	}
	.profile_select{
		width: 225px;
	}

	.tabcontent{
		margin: 0 auto;
		width: 47em;
		margin-top: 5em;
	}

	/* Importing fonts from Google */
	@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap');

	.wrapper {
		min-height: 500px;
		margin: 80px auto;
		padding: 40px 30px 30px 30px;
		background-color: #ecf0f3;
		border-radius: 15px;
		box-shadow: 8px 8px 20px 5px grey
	}

	.wrapper_about{
		max-width: 350px;
	}

	.wrapper_preferences{
		max-width: 750px;
	}

	.wrapper_assets{
		max-width: 350px;
	}

	.wrapper input, .wrapper select{
		width: 100%;
		padding: 5px;
		margin: 4px 0px;
		border-radius: 12px;
	}

	select:required:invalid {
		color: #666;
	}
	option[value=""][disabled] {
		display: none;
	}

	.logo {
		width: 80px;
		margin: auto;
		padding-bottom:2em;
	}

	.logo img {
		width: 100%;
		height: 80px;
		object-fit: cover;
		border-radius: 50%;
		box-shadow: 0px 0px 3px #5f5f5f,
			0px 0px 0px 5px #ecf0f3,
			8px 8px 15px #a7aaa7,
			-8px -8px 15px #fff;
	}

	.wrapper .name {
		font-weight: 600;
		font-size: 1.4rem;
		letter-spacing: 1.3px;
		padding-left: 10px;
		color: #555;
	}

	.wrapper .form-field input {
		width: 100%;
		display: block;
		border: none;
		outline: none;
		background: none;
		font-size: 1.2rem;
		color: #666;
		padding: 10px 15px 10px 10px;
		/* border: 1px solid red; */
	}

	.wrapper .form-field {
		padding-left: 10px;
		margin-bottom: 20px;
		border-radius: 20px;
		box-shadow: inset 8px 8px 8px #cbced1, inset -8px -8px 8px #fff;
	}

	.wrapper .form-field .fas {
		color: #555;
	}

	.form-label{
		font-weight:bold;
		padding: 10px 0px;
	}

	.form-label-small{
		font-weight: bold;
		padding: 10px 0px 1px 0px;
		font-size: 13px;
	}

	.wrapper .btn {
		box-shadow: none;
		width: 100%;
		height: 40px;
		background-color: #a90d0e;
		color: #fff;
		border-radius: 25px;
		box-shadow: 3px 3px 3px #b1b1b1,
			-3px -3px 3px #fff;
		letter-spacing: 1.3px;
	}

	.wrapper .btn:hover {
		background-color: #500000;
		cursor: pointer;
	}

	.wrapper a {
		text-decoration: none;
		font-size: 0.8rem;
		color: #a90d0e;
	}

	.wrapper a:hover {
		color: #500000;
		cursor: pointer;
	}

	@media(max-width: 380px) {
		.wrapper {
			margin: 30px 20px;
			padding: 40px 15px 15px 15px;
		}
	}
	
</style>
</head>

<body>

	<?php

		//define array of names & Id's to generate headers
		$header_names = ['About', 'Preferences', 'Assigned Assets [Coming soon]'];
		$header_ids = ['about', 'preferences', 'assigned_assets'];

		//pass to php function to create navigation bars
		echo create_navigation_bar($header_names, $header_ids, "update_preferences()", $fstUser);

	?>

	<div id ='about' class ='tabcontent' style='display:none'>
		<div class="wrapper wrapper_about">
			<div class="logo">
				<img src="<?= $fstUser['picture']; ?>" alt="">
			</div>
			<div class = 'form-label'>
				Personal Information
			</div>
			<div class = 'form-label-small'>
				Name
			</div>
			<input type="text" value = "<?= $fstUser['firstName'] . ' ' . $fstUser['lastName']; ?>" readonly>
			<div class = 'form-label-small'>
				Email
			</div>
			<input type="text" value = "<?= $fstUser['email']; ?>" readonly>
			<div class = 'form-label-small'>
				Phone
			</div>
			<input type="text" value = "<?= $fstUser['phone']; ?>" readonly>
			<div class = 'form-label-small'>
				Job Title
			</div>
			<input type="text" value = "<?= $fstUser['job_title']; ?>" readonly>
			<div class = 'form-label-small'>
				Shipping Info
			</div>
			<input type="text" id="street1" placeholder="Address Line 1" value = '<?= $fstUser['street1']; ?>'>
			<input type="text" id="street2" placeholder="Address Line 2" value = '<?= $fstUser['street2']; ?>'>
			<input type="text" id="city" placeholder="City" value = '<?= $fstUser['city']; ?>'>
			<select id="state" class = 'custom-select'>
				<option value=""></option>
				<?= create_select_options($states, $fstUser['state']); ?>
			</select>
			<input type="text" id="zip" placeholder="Zip" value = '<?= $fstUser['zip']; ?>'>
		</div>
	</div>

	<div id ='preferences' class ='tabcontent' style='display:none;'>

		<div class="wrapper wrapper_preferences">
			<div class="logo">
				<img src="<?= $fstUser['picture']; ?>" alt="">
			</div>
			
			<h4>Assigned Markets <i>(Please reach out to fst@piersonwireless.com for an adjustment)</i></h4>
				<input class = 'clickable' style = 'width: 30em;margin-left:1em;' value = '<?= $fstUser['assigned_markets']; ?>' readonly></h4>

			<h4 style = 'margin-bottom: 0em;'>Notification Settings</h4>

			<table class = "profileTable" id = 'notification_settings_table' style = 'width: 100%;'>

				<tr>
					<th></th>
					<th colspan="2" class = 'tooltip'><span class="fa fa-info-circle"></span>Standard Settings<p>Notifications for projects you are assigned to.</p></th>
					<th colspan="2" class = 'show_manager tooltip'><span class="fa fa-info-circle"></span>Manager Settings<p>Notifications for projects you manage.</p></th>			
				</tr>
				<tr>
					<th>Type</th>
					<th style = 'width: 1em;'>Dashboard Notifications</th>
					<th style = 'width: 1em;'>Email Notifications</th>
					<th style = 'width: 1em;'>Dashboard Notifications</th>
					<th style = 'width: 1em;'>Email Notifications</th>
				</tr>

				<?php

				// get users current notifications
				$user = new User($fstUser['id'], $con);
				$user_preferences = $user->get_notification_preferences();

				// add table row for all notification types
				//foreach($notification_keys as $n){
				foreach($notification_groups as $group){

					// Convert group to lowercase & replace space with underscore (to create class name)
					// ex. Due Date Change => due_date_change
					$group_class = strtolower(str_replace(" ", "_", $group));

					?>
					<tr class = 'notification_group'>
						<td colspan = '5'><button class = 'notification_button' onclick = 'show_hide_notifications(this, "<?= $group_class; ?>")'>+</button> <?= $group; ?></td>
						<!-- <td colspan = '5'> </td> -->
					</tr>
					<?php

					// Filter out notifications for a given group
					$keys_in_group = array_filter(
						$notification_keys, 
						function ($obj) use ($group){
							return $obj['group_name'] == $group;
						}
					);

					// Add row for each notification in group
					foreach($keys_in_group as $n){

					?>

						<tr class = '<?= $group_class; ?>' style = 'display:none'>
							<td class = 'tooltip'><span class="fa fa-info-circle"></span><?= $n['full']; ?> <p><?= $n['description']; ?></p></td>
							<td class = 'notification_td'><input type = 'checkbox' class = '<?= $n['id']; ?>' value = '1' <?php if (str_contains($user_preferences[$n['id']], "1")) echo "checked"; ?>></td>
							<td class = 'notification_td'><input type = 'checkbox' class = '<?= $n['id']; ?>' value = '2' <?php if (str_contains($user_preferences[$n['id']], "2")) echo "checked"; ?>></td>
							<td class = 'notification_td'><input type = 'checkbox' class = '<?= $n['id']; ?>' value = '3' <?php if (str_contains($user_preferences[$n['id']], "3")) echo "checked"; ?>></td>
							<td class = 'notification_td'><input type = 'checkbox' class = '<?= $n['id']; ?>' value = '4' <?php if (str_contains($user_preferences[$n['id']], "4")) echo "checked"; ?>></td>
						</tr>

					<?php

					}
				}

				?>
			</table>

			<h4>Filters (View All Projects)</h4>

			<table class = "profileTable" style = 'margin-bottom: 5em;'>
				<tr>
					<td>Designer</td>
					<td><select class = 'custom-select' id='designerOpt' style = 'width: 200px'>
							<option></option>
							<?= create_select_options($des, $fstUser['profileDes']);?>								
						</select> 
					</td>
				</tr>
				<tr>
					<td>Estimator</td>
					<td><select class = 'custom-select' id='qcOpt' style = 'width: 200px'>
							<option></option>
							<?= create_select_options($qc, $fstUser['profileQC']);?>
						</select> 
					</td>
				</tr>
				<tr>
					<td>Market</td>
					<td><select class = 'custom-select' id='marketOpt' style = 'width: 200px'>
							<option></option>
							<?= create_select_options($market, $fstUser['profileMarket']);?>
						</select> 
					</td>
				</tr>
			</table>	
		</div>
	</div>

	<div id ='assigned_assets' class ='tabcontent' style='display:none'>

		<div class="wrapper wrapper_assets">
			<div class="logo">
				<img src="<?= $fstUser['picture']; ?>" alt="">
			</div>
			<!-- <table>
				<tr>
					<th>Asset</th>
					<th>Assigned On</th>
					<th>Return</th>
				</tr>
			</table> -->
		</div>
	</div>

	<div id = 'assign_market_dialog' title = 'Select Markets' style = 'display:none'>

		<table id = 'market_table'>
			<tr>
				<th>Asgn</th>
				<th>Market</th>
			</tr>

			<?php

			foreach($market as $mkt){

				// create abv (ex. 2001 - Pacific => 2001)
				$mkt_abv = substr($mkt, 0, 4);

				?>

				
					<tr>
						<td style = 'text-align: center'>
							<input id = 'market_<?= $mkt_abv; ?>' 
								value = '<?= $mkt_abv; ?>' 
								type = 'checkbox' 
								class = 'market_checkbox'
								onchange = 'adjust_market()'>
						</td>
						<td><label class = 'clickable-noblue' for= 'market_<?= $mkt_abv; ?>'><?= $mkt; ?></label></td>
					</tr>
				

				<?php

			}

			?>

		</table>
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
		
		//pass info that helps decide grid layout
		const user_info = <?= json_encode($fstUser); ?>,
			notification_keys = <?= json_encode($notification_keys); ?>;

		// Global to hold last item clicked (to update as we go)
		var assigned_market_input = null;

		/**
		 * Handles opening market options dialog
		 * @author Alex Borchers
		 * @param {HTMLElement} targ 	(<input> tag clicked)
		 * @returns void
		 */
		function open_market_options(targ){
			
			// Reset market checkboxes
			document.querySelectorAll('.market_checkbox').forEach(function(a){
				a.checked = false;
			})

			// Create array from targ.value
			var markets = targ.value.split(",");

			// Loop through markets & set table checkboxes
			for (var i = 0; i < markets.length; i++){
				if (markets[i] != "")
					u.eid("market_" + markets[i]).checked = true;
			}

			// Open dialog
			open_dialog("assign_market_dialog");
			$( "#assign_market_dialog" ).dialog({
				width: "auto",
				height: "auto",
				dialogClass: "fixedDialog",
				close: function (){
					assigned_market_input.classList.remove("target");
				} 
			});
			
			// If previous element has been clicked, reset style
			if (assigned_market_input != null)
				assigned_market_input.classList.remove("target");

			// Store clicked element
			targ.classList.add("target");
			assigned_market_input = targ;

		}

		/**
		 * Handles adjusting markets for user
		 * @author Alex Borchers
		 * @param {int} 		id 		(fst_user db table id)
		 * @param {HTMLElement} targ 	(<input> tag clicked)
		 * @returns void
		 */
		function adjust_market(id, targ){
			
			// Create string to hold all market selections
			var assigned_markets = "";

			// Reset market checkboxes
			document.querySelectorAll('.market_checkbox').forEach(function(a){
				if (a.checked)
					assigned_markets += a.value + ",";
			})

			// If empty, return, otherwise remove last 2 chars and return
			if (assigned_markets == "")
				assigned_market_input.value = assigned_markets;
			else
				assigned_market_input.value = assigned_markets.substring(0, assigned_markets.length - 1);

		}

		/**
		 * Handles showing/hiding notification options based
		 * @author Alex Borchers
		 * @param {HTMLElement} targ <button> clicked by user
		 * @param {String} class_name (the classname to show/hide)
		 */
		function show_hide_notifications(targ, class_name){

			// Depending on innerHTML of button, make decision
			if (targ.innerHTML == "+"){
				var display_opt = "table-row";
				targ.innerHTML = "-";
			}
			else{
				var display_opt = "none";
				targ.innerHTML = "+";
			}

			// Go through class objects and update display
			document.querySelectorAll('.' + class_name).forEach(function(obj){
				obj.style.display = display_opt;
			})
		}
		
		/**
		 * Handles updating user preferences
		 * @author Alex Borchers
		 * @returns void
		 */
		function update_preferences(){

			// Initalize form data (will carry all form data over to server side)
			var fd = new FormData();
			
			// Transfer all other information to our form data variable
			fd.append('designer', u.eid('designerOpt').value);
			fd.append('market', u.eid('designerOpt').value);
			fd.append('quoteCreator', u.eid('designerOpt').value);
			fd.append('street1', u.eid('street1').value);
			fd.append('street2', u.eid('street2').value);
			fd.append('city', u.eid('city').value);
			fd.append('state', u.eid('state').value);
			fd.append('zip', u.eid('zip').value);

			// Loop through notification_types and add to form
			for (var i = 0; i < notification_keys.length; i++){

				// Reset notification pref string
				var pref_string = "";

				// Loop through all options from a class & build a string of options selected
				document.querySelectorAll('.' + notification_keys[i].id).forEach(function(a){
					if (a.checked)
						pref_string += a.value.toString();
				})
				console.log(pref_string);
				fd.append(notification_keys[i].id, pref_string);
			}
			
			// Serialize arrays so we can pass them to php
			fd.append('notification_keys', JSON.stringify(notification_keys));
			fd.append('user_info', JSON.stringify(user_info));			
			
			//ajax request to communicate with database
			$.ajax({
				type : "POST",  					//type of method
				url  : "home_profile_helper.php",  	//your page
				processData: false,
				contentType: false,
				data : fd,
				success : function (response) {
					// check for error message
					if (response != ""){
						alert(response);
						console.log(response);
						return;
					}
					
					// otherwise return success message
					alert("Your settings have been updated.");
				}
			});
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

		// show mouse waiting while sending ajax request
		$(document).ajaxStart(function () {
			waiting('on');
		});
		
		$(document).ajaxStop(function () {
			waiting('off');
		});

		//windows onload
		window.onload = function () {
						
			// Get the element with id="defaultOpen" and click on it
			u.eid("defaultOpen").click();

		}	

	</script>

</body>

<?php

//reset return address once the page has loaded
unset($_SESSION['returnAddress']);

//close SQL connection
$mysqli -> close();

?>

</html>