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

//check if temporary access has been granted (see index_external_helper.php)
if (isset($_SESSION['temporary_access']))
  $fstUser['accessLevel'] = "Temporary";

//verify user
sessionCheck($fstUser['accessLevel'], true);

// Check if required $_GET[] is set
if (!isset($_GET['sr_id']) || !isset($_GET['container'])){
  header("Location: home.php?");
}

// get information related to shipment
$pq_detail = [];
$query = "SELECT * FROM fst_pq_detail WHERE ship_request_id = '" . $_GET['sr_id'] . "' AND wh_container = 'Container " . $_GET['container'] . "' AND status <> 'Received' ORDER BY wh_container;";
//$query = "SELECT * FROM fst_pq_detail WHERE ship_request_id = '" . $_GET['sr_id'] . "' ORDER BY wh_container;";
$result = mysqli_query($con, $query);
while($rows = mysqli_fetch_assoc($result)){
	array_push($pq_detail, $rows);
}

// get request info
$query = "SELECT * FROM fst_pq_ship_request WHERE id = '" . $_GET['sr_id'] . "';";
$result = mysqli_query($con, $query);
$ship_request = mysqli_fetch_array($result);

// get quote info
$query = "SELECT * FROM fst_grid WHERE quoteNumber = '" . $ship_request['quoteNumber'] . "';";
$result = mysqli_query($con, $query);
$grid = mysqli_fetch_array($result);

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
    
    .sticky-header-qr{
      position: sticky;
      top: 46px;
      z-index: 100;
      background: #114b95;
      color: white;
    }

    /**adjust received_input class attributes */
    .received_input{
      width: 100%;
    }
    .received_cell{
      width: 25% !important;
    }
    .issue_cell{
      width: 5% !important;
      text-align: center;
    }

    /**adjust quantity_input class width */
    .quantity_cell{
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
<link href = "stylesheets/jquery-ui-themes-1.13.0/themes/ui-lightness/jquery-ui.css" rel = "stylesheet">
<title>QR Receiving (v<?= $version ?>) - Pierson Wireless</title>
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
$header_names = ['Receiving Items'];   //what will appear on the tabs
$header_ids = ['receive_items'];      //must match a <div> element inside of body

echo create_navigation_bar($header_names, $header_ids, "", $fstUser);

?>

<div id = 'receive_items' class = 'tabcontent' style = 'display:none'>

    <div id = 'receiving_main'>

      <h2>Shipment ID: <?= $_GET['sr_id']; ?></h2>  
      <h2>Container: <?= $_GET['container']; ?></h2>

      <table>
        <tr class = 'sticky-header-qr'>
          <th>Part #</th>
          <th class = 'quantity_cell'>Qty</th>
          <th>Rec'd</th>
          <th>Issue</th>
        </tr>
        
        <?php

        // loop through all parts related to ship request
        foreach ($pq_detail as $part){

          ?>

          <tr>
            <td><?= $part['part_id']; ?></td>
            <td class = 'quantity_cell'><?= $part['q_allocated']; ?></td>
            <td class = 'received_cell' ><input class = 'received_input' type = 'number' id = '<?= $part['id']; ?>' value = '<?= $part['q_allocated']; ?>' min="0"></td>
            <td class = 'issue_cell' >
              <button onclick = 'add_note(this)'><i class="fa fa-edit"></i>
              <input class = 'notes_input' type = 'text' style = 'display:none' readonly>
            </td>
          </tr>

          <?php

        }

        ?>

      </table>

      <label for="image-input" class = 'mobile_label_button'>Take Image</label>
      <input id="image-input" type="file" name="image" style="display: none;">
      <ul id="image-list"></ul>
      <label for="receive_button" class = "mobile_label_button">Submit</label>
      <input type = 'button' class = 'mobile_button' id = 'receive_button' style="display: none;" value = 'Submit' onclick = 'qr_receive_items()'>

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
    const grid = <?= json_encode($grid); ?>,
          ship_request = <?= json_encode($ship_request); ?>,
          user_info = <?= json_encode($fstUser); ?>,
          sr_id = "<?= $_GET['sr_id']; ?>",
          container = "<?= $_GET['container']; ?>",
          size = <?= sizeof($pq_detail); ?>,
          access_level = "<?= $fstUser['accessLevel']; ?>";

    // Add event listener to our image-input file picker & keep track of all files selected on change
    const inputElement = document.getElementById("image-input");
    const listElement = document.getElementById("image-list");
    let selectedFiles = []; // will keep track of all files.
    //let seq = 1;

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
    function add_note(input){

      // Work back to previous value
      previous_note = input.parentNode.childNodes[1];

      // Prompt user to add note
      let note = prompt("Add/Adjust Note", previous_note.value);

      if (note != null)
        previous_note.value = note;
    }

    function qr_receive_items(){

      // loop through checkbox items and determine which ones are selected
      var received_items = [];
      var type = "Full";    // flip to partial if not full
      document.querySelectorAll('.received_input').forEach(function(a){

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
      if (received_items.length == 0){
        alert("[Error] At least 1 item must be received.");
        return;
      }

      // verify at least 1 image has been taken
      if (selectedFiles.length == 0){
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
      for (var i = 0; i < selectedFiles.length; i++){
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
        success : function (response) {

          // check for error
          if (response != ""){
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

      //if no items found, alert user & redirect from page
      if (size == 0){
        alert("[Error] All parts have been received for this shipment.");
        window.location = "index_external.php";
      }

      //place any functions you would like to run on page load inside of here
      u.eid("defaultOpen").click(); 

      // if user is temporary, remove side nav & notification icon
      if (access_level == "Temporary"){
        //u.eid("notification_button").remove();
        u.eid("open_side_nav_icon").remove();
      }
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