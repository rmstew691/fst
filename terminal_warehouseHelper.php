<?php

// Load in dependencies (including starting a session)
session_start();
include('phpFunctions.php');
include('phpFunctions_html.php');
include('constants.php');

// Load the database configuration file
require_once 'config.php';

//grab basic info about the quote
$shop = $_GET["shop"];

//used to grab actual link for the current address
$actual_link = "https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

//Save current site so we can return after log in
$_SESSION['returnAddress'] = $actual_link;

//sub link
$sub_link = substr($actual_link, 0, strpos($actual_link, "home"));

//load in any material orders open for this shop
//$query = "select id, mo_id, status from fst_allocations_mo where ship_from = '" . $shop . "' AND status NOT IN ('Closed') order by mo_id;";
$query = "SELECT id, mo_id, status FROM fst_allocations_mo WHERE ship_from = '" . $shop . "' AND status IN ('Open', 'In Progress', 'Shipping Later', 'Staged') ORDER BY status, mo_id;";
$result = mysqli_query($con, $query);

//initialize arrays to be passed to js
//open/pending
$material_orders = [];

//cycle thorugh query and assign to different arrays
//add entry for each mo 
while($rows = mysqli_fetch_assoc($result)){
	array_push($material_orders, $rows);
}

?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
    
    /** insert styles here **/
    .tabcontent{
      padding: 70px 20px;
    }
    #open_side_nav_icon{
      display: none;
    }

    /**add styles to the table */
    table {
      width: 100%;
      border-collapse: collapse;
      margin: 0 auto;
      text-align: left;
    }
    th, td {
      padding: 10px;
      border: 1px solid #ccc;
    }
    thead {
      background-color: #f5f5f5;
    }
    tbody tr:nth-child(even) {
      background-color: #f2f2f2;
    }

    /**style specific inputs that appear on the screen */
    input[type="text"], select {
      font-size: 1rem;
      padding: 1rem !important;
      border-radius: 0.5rem;
      border: 1px solid #ccc;
      width: 100%;
      box-sizing: border-box;
      margin-bottom: 1rem;
    }
    input[type="checkbox"] {
      font-size: 1.5rem;
      padding: 1rem;
      border-radius: 0.5rem;
      border: 1px solid #ccc;
      box-sizing: border-box;
      -ms-transform: scale(1.5);
      -webkit-transform: scale(1.5);
      transform: scale(1.5);
    }

    .mobile_select{
      font-size: 2rem;
      color: black;
      background-color: #BBDFFA;
      border-color: #000B51;
      border-width: medium;
      cursor: pointer;
      margin-top:1em;
    }

    .nav-bar li{
      width:100%;
    }

    /**adjust checkbox_cell class attributes */
    .checkbox_cell{
      text-align: center;
    }

    /**adjust quantity_input class width */
    .quantity_cell{
      width: 10% !important;
    }

    /**adjust pallet_input class width */
    .pallet_cell{
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
    .mobile_label_button:hover, .mobile_button:hover {
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

    @media screen and (max-width: 768px) {
      /* Styles for screens smaller than 768px */
      input[type="checkbox"] {
        -ms-transform: scale(0.8);
        -webkit-transform: scale(0.8);
        transform: scale(0.8);
      } 
    }

</style>
<!-- add any external style sheets here -->
<link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css'> 
<link rel='stylesheet' href='stylesheets/element-styles.css'> 
<link rel="shortcut icon" type="image/x-icon" href="images/P-Logo.png" />
<link href = "stylesheets/jquery-ui-themes-1.13.0/themes/ui-lightness/jquery-ui.css" rel = "stylesheet">
<title>Warehouse Helper (v<?= $version ?>) - Pierson Wireless</title>
</head>
<body>

<?php

// Unset all relevant fields
$query = "DESCRIBE fst_users;";
$result = mysqli_query($con, $query);
while($rows = mysqli_fetch_assoc($result)){
  $fstUser[$rows['Field']] = "";
}

//render header by using create_navigation_bar function (takes two arrays = 1 = name of buttons, 2 = id's of divs to open)
$header_names = ['Warehouse Attachments'];   //what will appear on the tabs
$header_ids = ['attachments'];      //must match a <div> element inside of body

echo create_navigation_bar($header_names, $header_ids, "", $fstUser);

?>

<div id = 'attachments' class = 'tabcontent' style = 'display:none'>

    <div id = 'receiving_main'>

      <h2>Select Material Order:</h2>
      <!-- <input type = 'radio' name = 'mo_type' value = 'Open/Pending' id = 'open_pending'>
      <label for = 'open_pending'>Open / Pending</label><br>
      <input type = 'radio' name = 'mo_type' value = 'Staged/Ship Later' id = 'staged_ship_later'>
      <label for = 'staged_ship_later'>Staged / Ship Later</label> -->
      <select id = 'mo_id' class = 'mobile_select'>
        <option></option>
        <optgroup label = "Open / In Progress">
        <?php

        // Set boolean to flip once we reach staged/ship later projects
        $open_in_progress = true;

        foreach ($material_orders as $mo){

          // Flip once we hit our first staged/ship later job
          if ($open_in_progress && ($mo['status'] == "Staged" || $mo['status'] == "Shipping Later")){
            echo "</optgroup>";
            echo "<optgroup label = 'Staged / Shipping Later'>";
            $open_in_progress = false;
          }

          ?>

          <option value = '<?= $mo['id']; ?>'><?= $mo['mo_id']; ?></option>

          <?php

        }

        ?>
        </optgroup>
      </select>

      <label for="image-input" class = 'mobile_label_button'>Take Image</label>
      <input id="image-input" type="file" name="image" style="display: none;">
      <ul id="image-list"></ul>
      <label for="receive_button" class = "mobile_label_button">Add To Terminal</label>
      <input type = 'button' class = 'mobile_button' id = 'receive_button' style="display: none;" value = 'Submit' onclick = 'add_to_terminal()'>
      
    </div>

    <div id = 'receiving_thankyou' style = 'display:none'>

        <h2>Thank you! You can exit this screen now.</h2>

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

<script>

    // Pass relevant data to js
    const material_orders = <?= json_encode($material_orders); ?>;

    // Add event listener to our image-input file picker & keep track of all files selected on change
    const inputElement = document.getElementById("image-input");
    const listElement = document.getElementById("image-list");
    let selectedFiles = []; // will keep track of all files.

    function addListItem(file) {
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

    //handles processing completes material orders
		function add_to_terminal(){
			
      // initialize array for file_reference
      var file_reference = [];

			//initalize form data (will carry all form data over to server side)
			var fd = new FormData();
      
      // grab all files attached
      for (var i = 0; i < selectedFiles.length; i++){
        var file = selectedFiles[i];
        fd.append('file' + i, file);
        file_reference.push('file' + i);
      }

      // pass information needed in helper file
      fd.append('file_reference', JSON.stringify(file_reference));
      fd.append('id', u.eid("mo_id").value);
			fd.append('tell', 'attachment_helper');
			
			//call ajax
			$.ajax({
				url: 'MO_handler.php',
				type: 'POST',
				processData: false,
				contentType: false,
				data: fd,
				success: function (response) {
					
					if (response != ""){
						alert(response);
						
					}
					else{
						alert("Your files have been uploaded successfully.")
						window.location.reload();
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