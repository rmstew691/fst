<?php

/****************
 * 
 * Created by: Alex Borchers (4/27/23)
 * This file is intended only for mobile use. If a user accessing the FSE Remote Service Request they will be redirected here.
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
$fse_remote_keys = [];
$query = "DESCRIBE fst_cop_engineering_data_submission;";
$result = mysqli_query($con, $query);
while($rows = mysqli_fetch_assoc($result)){
	array_push($fse_remote_keys, $rows['Field']);
}

// Get note if set
if (isset($_GET['note']))
	$note = $_GET['note'];
else
	$note = "";

?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">

<style>
    
    /** insert styles here **/
    .tabcontent{
      padding: 70px 20px;
    }

</style>
<!-- add any external style sheets here -->
<link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css'> 
<link rel='stylesheet' href='stylesheets/element-styles.css'> 
<link rel='stylesheet' href='stylesheets/mobile-fse.css'> 
<link rel="shortcut icon" type="image/x-icon" href="images/P-Logo.png" />
<link href = "stylesheets/jquery-ui-themes-1.13.0/themes/ui-lightness/jquery-ui.css" rel = "stylesheet">
<title>FSE Request (v<?= $version ?>) - Pierson Wireless</title>
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
$header_names = ['FSE Request'];   //what will appear on the tabs
$header_ids = ['fse'];      //must match a <div> element inside of body

echo create_navigation_bar($header_names, $header_ids, "", $fstUser);

?>

<div id = 'fse' class = 'tabcontent' style = 'display:none'>
  <h2>FSE Questionnaire</h2>
  <!-- content loaded with load_fse_remote_support() -->
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
		  note = "<?= $note; ?>",
		  type = "<?= $_GET['type']; ?>",
          fst_version = "<?= $version; ?>",
          fse_remote_keys = <?= json_encode($fse_remote_keys); ?>,
          user_info = <?= json_encode($fstUser); ?>;

    // Global to determine if FSE Remote Support has already been loaded or not
	var fse_remote_loaded = false;

	/** 
	 * Handles loading fse_remote_support template from html_templates folder. When complete, add any additional project info to form
	 * @author Alex Borchers
	 * @param quote {string} matches entry for fst_grid, used to determine parts able to request
	 * @returns void
	 */
	function load_fse_remote_support(quote){

		// check if JHA has already been loaded
		if (fse_remote_loaded){
			load_fse_info(quote);
			return;
		}

		// use XML request to get JHA from indexOf
		const xhr = new XMLHttpRequest();
		const fse_remote_container = u.eid("fse");

		xhr.onload = function (){
			if (this.status === 200)
			fse_remote_container.innerHTML += xhr.responseText;
			else
				console.warn('Did not receive 200 OK');
		}

		xhr.open('get', 'html_templates/fse_remote_support.html?2' + fst_version);
		xhr.addEventListener("load", function(){

			//get screen height
			var screenHeight = $(window).height();
			var screenWidth = $(window).width();

			/*$( "#fse_remote_support_dialog" ).dialog({
				width: "auto",
				height: screenHeight - 50
			});

			open_dialog("fse_remote_support_dialog");*/
			load_fse_info(quote);
		});
		xhr.send();

		// set jha_load boolean to true so we do not load again
		fse_remote_loaded = true;
	}

	/**
	 * Handles loading any FSE form info from other places
	 * @auther Alex Borchers
	 * @param {string} quote
	 * @returns void
	 */
	function load_fse_info(quote){

		// set project name
		u.eid("project_name_phase").value = grid.location_name + " " + grid.phaseName;
		u.eid("fse_quote").value = quote;
		u.eid("fse_service_requested").value = type;

		// Replace all underscores with spaces (transferred from URL)
		var fixed_note = note.replace(/_/g, " ");
		u.eid("fse_service_notes").value = fixed_note;
		
	}

	/**
	 * Handles showing/hiding all divs related to yes/no questions
	 * @author Dima Abdo
	 * 
	 * @returns void
	 */
	function show_hide_divs_handler(){

		// PIM-Sweep Results
		if (u.eid("sweep_pim_yes").checked)
			u.eid("sweep_pim").style.display = "block";
		else
			u.eid("sweep_pim").style.display = "none";

		// CAT Cable Results
		if (u.eid("CAT_cables_yes").checked)
			u.eid("CAT_Testing").style.display = "block";
		else
			u.eid("CAT_Testing").style.display = "none";
		
		// OTDR / Scopes Results
		if (u.eid("OTDR_scopes_yes").checked)
			u.eid("OTDR_Scopes").style.display = "block";
		else
			u.eid("OTDR_Scopes").style.display = "none";

		// WIND Walk/PS Grid Testing Results
		if (u.eid("WINd_Scanner_yes").checked)
			u.eid("WINd_Public_Safety_Walks").style.display = "block";
		else
			u.eid("WINd_Public_Safety_Walks").style.display = "none";

		// WIND Walk/PS Grid Notify of upcoming walk
		if (u.eid("WINd_Scanner_notify").checked)
			u.eid("walk_date_div").style.display = "block";
		else
			u.eid("walk_date_div").style.display = "none";

	}

	/**
	 * Handles processing FSE remote support service request
	 * @author Alex Borchers
	 * 
	 * Globals
	 * fse_remote_keys (global used to idenitfy all column names in _ and ids in the remote support form)
	 */
	function fse_remote_support_request(){

		// check to make sure required fse fields are filled out (returns true if error)
		if (fse_remote_form_check())
			return;

		// init form data
		var fd = new FormData();

		// use fse_remote_keys array to submit form data
		for (var i = 0; i < fse_remote_keys.length; i++){

			// ignore certain rows
			if (["id", "user", "time_created", "quoteNumber"].includes(fse_remote_keys[i]))
				continue; 

			// grab potential candidates for data type
			var standard = $('#' + fse_remote_keys[i]);
			var radio = $('input[type=radio][name=' + fse_remote_keys[i] + ']');

			// depending on the type of input field, set checked or value
			if (radio.length > 0){

				// check if user has checked "Other"
				if ($('label[for="' + $('input[type=radio][name=' + fse_remote_keys[i] + ']:checked').attr('id') + '"]').html() == "Other")
					fd.append(fse_remote_keys[i], u.eid($('input[type=radio][name=' + fse_remote_keys[i] + ']:checked').attr('id') + '_text').value);
				else
					fd.append(fse_remote_keys[i], $('label[for="' + $('input[type=radio][name=' + fse_remote_keys[i] + ']:checked').attr('id') + '"]').html());
			}
			else if (standard[0].tagName == "DIV"){

				// create string to hold all values
				var checkbox_string = "";

				// loop through all checkboxes inside div & get value of checked
				$('#' + fse_remote_keys[i] + ' input').each(function () {
					if (this.checked){

						// check for "Other"
						if ($('label[for="' + this.id + '"]').html() == "Other")
							checkbox_string += u.eid(this.id + "_text").value +  ", ";
						else
							checkbox_string += $('label[for="' + this.id + '"]').html() + ", ";
					}
				});

				// if we found results, remove last 2 characters (extra comma)
				if (checkbox_string != "")
					checkbox_string = checkbox_string.substr(0, checkbox_string.length - 2);
				
				// push to form
				fd.append(fse_remote_keys[i], checkbox_string);
			}
			else
				fd.append(fse_remote_keys[i], u.eid(fse_remote_keys[i]).value);
		}

		// add quote & other applicable info
		fd.append('quoteNumber', u.eid("fse_quote").value);
		fd.append('project_name', u.eid("project_name_phase").value);
		fd.append('fse_service_notes', u.eid("fse_service_notes").value);
		fd.append('fse_service_requested', u.eid("fse_service_requested").value);
		
		// pass fse_remote_keys to use in query creation
		fd.append('fse_remote_keys', JSON.stringify(fse_remote_keys));
		
		// pass user_info, & tell
		fd.append('user_info', JSON.stringify(user_info));
		
		//send info to ajax, set up handler for response
		$.ajax({
			url: 'googlesheets_fse_remote_support.php',
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

				// alert user, close dialog
				alert("The form has been submitted to the FSE team.");
				window.close();
				//close_dialog("fse_remote_support_dialog");
			}
		});
	}

	/**
	 * Handles checking FSE form for required fields
	 * @author Alex Borchers
	 * 
	 * Globals
	 * fse_remote_keys (global used to idenitfy all column names in _ and ids in the remote support form)
	 * 
	 * @return {boolean} (True = error, False = passing)
	 */
	function fse_remote_form_check(){

		// initialize error variable (checks for required fields)
		var error = false;

		// loop through keys & check to make sure required fields are filled out
		for (var i = 0; i < fse_remote_keys.length; i++){

			// ignore certain rows
			if (["id", "user", "time_created", "quoteNumber", "fse_service_requested", "fse_service_notes"].includes(fse_remote_keys[i]))
				continue; 

			console.log(fse_remote_keys[i]);

			// grab potential candidates for data type
			var standard = $('#' + fse_remote_keys[i]);
			var radio = $('input[type=radio][name=' + fse_remote_keys[i] + ']');

			// depending on the type of input field, set checked or value
			if (radio.length > 0){

				console.log("radio");

				// Get area to highlight incorrect
				var question_card = radio[0].parentNode.parentNode;

				// ignore if field is not shown
				if (question_card.parentNode.style.display == "none")
					question_card.classList.remove("required_error_border");
				// make sure user has something filled out
				else if ($('input[type=radio][name=' + fse_remote_keys[i] + ']:checked').attr('id') == null){
					error = true;
					question_card.classList.add("required_error_border");
				}
				else
					question_card.classList.remove("required_error_border");						
			}
			else if (standard[0].tagName == "DIV"){

				// Get area to highlight incorrect
				var question_card = standard[0].parentNode;

				// init variable to determine if something is checked
				var one_is_checked = false;

				// loop through all checkboxes inside div & get value of checked
				$('#' + fse_remote_keys[i] + ' input').each(function () {

					if (this.checked)
						one_is_checked = true;
												
				});

				// ignore if field is not shown
				if (question_card.parentNode.style.display == "none")
					question_card.classList.remove("required_error_border");
				// check to make sure user has something filled out
				else if (one_is_checked)
					question_card.classList.remove("required_error_border");
				else{
					error = true;
					question_card.classList.add("required_error_border");
				}
			}	
			else{

				// Handle textareas
				if (standard.length > 0)
					standard = standard[0];

				// Get area to highlight incorrect
				var question_card = standard.parentNode.parentNode;

				// if value is blank, flag error
				// ignore if field is not shown
				if (question_card.parentNode.style.display == "none")
					question_card.classList.remove("required_error_border");
				else if (standard.value == ""){
					error = true;
					question_card.classList.add("required_error_border");
				}
				else
					question_card.classList.remove("required_error_border");	
			}
		}

		// alert if error
		if (error){
			alert("[Error] Please fill out all required fields (highlighted in yellow).");
			var errs = u.class("required_error_border");

			// Scroll to the element using the scrollTo method
			window.scrollTo({
				top: errs[0].offsetTop - 55,
				behavior: "smooth" // Use smooth scrolling animation
			});

			/*errs[0].scrollIntoView({
				behavior: 'smooth',
				block: 'start',
				inline: 'nearest',
				duration: 500
			});*/
		}
		
		return error;
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
      load_fse_remote_support(grid.quoteNumber);
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