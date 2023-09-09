<?php

/****************
 * 
 * Created by: Alex Borchers (4/27/23)
 * This file is intended only for mobile use. If a user accessing the JHA form they will be redirected here.
 * 
 *****************/

// load in dependencies
session_start();
include('phpFunctions_html.php');
include('phpFunctions.php');
include('constants.php');

// load db configuration
require_once 'config.php';

//used to grab actual link for the current address
$actual_link = "https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

//sub link
$sub_link = substr($actual_link, 0, strpos($actual_link, "quote"));

//Save current site so we can return after log in
$_SESSION['returnAddress'] = $actual_link;

//init fstUser array
$fstUser = [];

//Make sure user has privileges
//check session variable first
if (isset($_SESSION['email'])){
	$query = "SELECT * from fst_users where email = '".$_SESSION['email']."';";
	$result = $mysqli->query($query);

	if ($result->num_rows > 0)
		$fstUser = mysqli_fetch_array($result);
	else
		$fstUser['accessLevel'] = "None";
}
else
	$fstUser['accessLevel'] = "None";

//verify user
sessionCheck($fstUser['accessLevel']);

// get quote info
$query = "SELECT * FROM fst_grid WHERE quoteNumber = '" . $_GET['quote'] . "';";
$result = mysqli_query($con, $query);
$grid = mysqli_fetch_array($result);

// load in 'keys' for JHA form (table field names to guide form updates)
$jha_key = [];
$query = "DESCRIBE jha_form;";
$result = mysqli_query($con, $query);
while($rows = mysqli_fetch_assoc($result)){
	array_push($jha_key, $rows['Field']);
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

</style>
<!-- add any external style sheets here -->
<link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css'> 
<link rel='stylesheet' href='stylesheets/element-styles.css'> 
<link rel='stylesheet' href='stylesheets/mobile-jha.css'> 
<link rel="shortcut icon" type="image/x-icon" href="images/P-Logo.png" />
<link href = "stylesheets/jquery-ui-themes-1.13.0/themes/ui-lightness/jquery-ui.css" rel = "stylesheet">
<title>JHA Mobile (v<?= $version ?>) - Pierson Wireless</title>
</head>
<body>

<?php

// if access is temporary, manually adjust all user fields
if ($fstUser['accessLevel'] == "Temporary"){
  $query = "DESCRIBE fst_users;";
  $result = mysqli_query($con, $query);
  while($rows = mysqli_fetch_assoc($result)){
    if (!in_array($rows['Field'], ["firstName", "lastName", "email", "accessLevel"]))
      $fstUser[$rows['Field']] = "";
  }
}

//render header by using create_navigation_bar function (takes two arrays = 1 = name of buttons, 2 = id's of divs to open)
$header_names = ['JHA'];   //what will appear on the tabs
$header_ids = ['jha'];      //must match a <div> element inside of body

echo create_navigation_bar($header_names, $header_ids, "", $fstUser);

?>

<div id = 'jha' class = 'tabcontent' style = 'display:none'>
  <h2>JHA Form</h2>
  <!-- content loaded with load_jha() -->
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

<script>

    // Pass relevant data to js
    const grid = <?= json_encode($grid); ?>,
          fst_version = "<?= $version; ?>",
          jha_key = <?= json_encode($jha_key); ?>,
          user_info = <?= json_encode($fstUser); ?>;

    // Global to determine if JHA has already been loaded or not
	var jha_loaded = false;

	/** 
	 * Handles loading jha_form template from html_templates folder. When complete, call function to show jha info
	 * @author Alex Borchers
	 * 
	 * Globals
	 * quote (const quote = matches quote # on page loaded)
	 * 
	 * @returns void
	 */
	function load_jha(){
		
		// check if JHA has already been loaded
		if (jha_loaded){
			get_existing_jha(grid.quoteNumber);
			return;
		}

		// use XML request to get JHA from indexOf
		const xhr = new XMLHttpRequest();
		const jha_container = u.eid("jha");

		xhr.onload = function (){
			if (this.status === 200)
				jha_container.innerHTML += xhr.responseText;
			else
				console.warn('Did not receive 200 OK');
		}

		xhr.open('get', 'html_templates/jha_form.html?3' + fst_version);
		xhr.addEventListener("load", function(){
			get_existing_jha(grid.quoteNumber);
		});
		xhr.send();

		// set jha_load boolean to true so we do not load again
		jha_loaded = true;
	}

	/** 
	 * Handles checking for existing JHA in our database, if we find a match, call function to load in info, otherwise set defaults
	 * @author Alex Borchers
	 * @param quote {string} matches entry for fst_grid, used to determine parts able to request
	 * 
	 * Globals
	 * user_info {object} (information about the user - matches a row in fst_users db table)
	 * 
	 * @returns void
	 */
	function get_existing_jha(quote){

		// init form data
		var fd = new FormData();
		
		// pass quote #, user_info, & tell
		fd.append('quote', quote);
		fd.append('user_info', JSON.stringify(user_info));
		fd.append('tell', 'get_jha');
		
		//send info to ajax, set up handler for response
		$.ajax({
			url: 'home_ops_helper.php',
			type: 'POST',
			processData: false,
			contentType: false,
			data: fd,
			success: function (response) {
				
				// if we return an error, let the user know
				var error_check = response.substr(0, 5);
				if (error_check == "Error"){
					alert("There may have been an error. Please screenshot & send to fst@piersonwireless.com: " + response);
					console.log(response);
					return;
				}

				// since no error, check to see if we returned results
				if (response != ""){
					var jha_info = $.parseJSON(response);
					show_jha(quote, jha_info);
				}
				else
					show_jha(quote);
			}
		});
	}

	/** 
	 * Handles filling in with previously entered data (if available)
	 * @author Alex Borchers
	 * @param quote {string} matches entry for fst_grid, used to determine parts able to request
	 * 
	 * Globals
	 * grid (all matches quotes for user accessing dashboard)
	 * jha_key (all column names in jha_form db table)
	 * 
	 * @returns void
	 */
	function show_jha(quote, jha_info = null){

		// reset statue of JHA form
		reset_jha_form(jha_key);

		// first check if a JHA agreement has been filled out in the past
		if (jha_info !== null){

			// update to existing
			for (var i = 0; i < jha_key.length; i++){

				// ignore certain rows
				if (["created_by", "created", "last_update", "submitted"].includes(jha_key[i]))
					continue; 

				// depending on the type of input field, set checked or value
				if (u.eid(jha_key[i]).type == "checkbox")
					u.eid(jha_key[i]).checked = parseInt(jha_info[jha_key[i]]);
				else
					u.eid(jha_key[i]).value = jha_info[jha_key[i]];
					//document.getElementByID().value
				
				// if jha has been submitted, lock field, otherwise unlock
				if (parseInt(jha_info['submitted'])){

					if (u.eid(jha_key[i]).type == "checkbox")
						u.eid(jha_key[i]).addEventListener("click", revert_checkbox);
					else
						u.eid(jha_key[i]).readOnly = true;
				}
			}

			// if jha has been submitted, lock additional fields
			if (parseInt(jha_info['submitted'])){
				u.eid("save_jha_button").disabled = true;
				u.eid("submit_jha_button").disabled = true;
				//u.eid("revise_jha_button").disabled = false;
				u.eid("acknowledge_jha_button").disabled = false;
			}
		}
		else{

			//if no JHA filled out prior, get index from fst_grid (to load in existing data to form)
			u.eid("project_name").value = grid.location_name + " " + grid.phaseName;
			u.eid("quote_number").value = grid.quoteNumber;
			u.eid("revision").value = "0";
		}

		// update user user_name
		u.eid("acknowledge_user").innerHTML = user_info['firstName'] + " " + user_info['lastName'];

		// hide fields based on user access
		if (user_info['manager'] != "checked"){
			document.querySelectorAll('.manager_only').forEach(function(a){
				a.style.display = "none";
			})
		}
	}

	/**
	 * Handles resetting jha_form keys
	 * @param {array} jha_keys (DESCRIBE jha_form in db)
	 * @returns void
	 */
	function reset_jha_form(jha_key){

		// loop through keys & set to default values / enable edit access
		for (var i = 0; i < jha_key.length; i++){

			// ignore certain rows
			if (["created_by", "created", "last_update", "submitted", "quote_number", "project_name", "revision"].includes(jha_key[i]))
				continue; 

			// depending on the type of input field, set checked or value
			if (u.eid(jha_key[i]).type == "checkbox"){
				u.eid(jha_key[i]).checked = false;
				u.eid(jha_key[i]).removeEventListener("click", revert_checkbox);
			}
			else{
				u.eid(jha_key[i]).readOnly = false;
				u.eid(jha_key[i]).value = "";
			}
		}

		// unlock all required buttons
		u.eid("save_jha_button").disabled = false;
		u.eid("submit_jha_button").disabled = false;
		u.eid("revise_jha_button").disabled = false;
		u.eid("acknowledge_jha_button").disabled = true;

		// show all potentially hidden fields
		document.querySelectorAll('.manager_only').forEach(function(a){
			a.style.display = "";
		})

		// unset acknowledgement checkbox
		u.eid("acknowledge").checked = false;
	}

	/**
	 * Simple function that does not allow a checkbox to be changed
	 * Uses 'this' <input type='checkbox'> that the use clicks
	 * @author Alex Borchers
	 * @returns void
	 */
	function revert_checkbox(){
		this.checked = !this.checked;
	}

	/** 
	 * Handles modifying a JHA form in some way (determined on the action passed as the paramater)
	 * @author Alex Borchers
	 * @param {string} action (save/submit/revise/acknowledge)
	 * 
	 * Globals
	 * jha_key 		(DESCRIBE jha_form in db)
	 * user_info 	(matches row from fst_users)
	 * 
	 * @returns void
	 */
	function modify_jha(action){

		// if action is acknowledge, make sure user has hit the required checkbox
		if (action == "acknowledge" && !u.eid("acknowledge").checked){
			alert("[Error] Please acknowledge that you have read and understand the contents of this form.");
			return;
		}

		// init form data
		var fd = new FormData();

		// use jha_keys array to submit form data
		for (var i = 0; i < jha_key.length; i++){

			// ignore certain rows
			if (["created_by", "created", "last_update", "submitted"].includes(jha_key[i]))
				continue; 

			// depending on the type of input field, set checked or value
			if (u.eid(jha_key[i]).type == "checkbox")
				fd.append(jha_key[i], + u.eid(jha_key[i]).checked);		// prefix boolean with + to convert to integer
			else
				fd.append(jha_key[i], u.eid(jha_key[i]).value);
		}
		
		// pass jha_keys to use in query creation
		fd.append('jha_key', JSON.stringify(jha_key));
		
		// pass user_info, & tell/action
		fd.append('user_info', JSON.stringify(user_info));
		fd.append('tell', 'modify_jha');
		fd.append('action', action);
		
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

				// depending on the action, requested, alert user different message & update screen accordingly
				if (action == "save")
					alert("The JHA has been saved successfully.");
				else if (action == "submit"){
					alert("The JHA has been submitted, your technicians have been notified.");
					//close_dialog("jha_dialog");
				}	
				else if (action == "revise"){
					load_jha();
					alert("The JHA has been revised.");
				}
				else if (action == "acknowledge"){
					alert("Thank you! Your acknowledgement has been recorded.");
					window.close();
					//close_dialog("jha_dialog");
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
      load_jha();
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