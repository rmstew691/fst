<?php
session_start();

//used to grab actual link for the current address
$actual_link = "https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

//Save current site so we can return after log in
$_SESSION['returnAddress'] = $actual_link;

//sub link
$sub_link = substr($actual_link, 0, strpos($actual_link, "quoteLink"));

//include php functions sheet
include('phpFunctions.php');

//include php HTML renderings
include('phpFunctions_html.php');

//include constants sheet
include('constants.php');

// Load the database configuration file
require_once 'config.php';

//Make sure user has privileges
$query = "SELECT * FROM fst_users where email = '".$_SESSION['email']."'";
$result = $mysqli->query($query);

if ($result->num_rows > 0){
	$fstUser = mysqli_fetch_array($result);
}
else{
	$fstUser['accessLevel'] = "None";
}

sessionCheck($fstUser['accessLevel']);

//if admin, display admin button
$admin = "none";

if ($fstUser['accessLevel'] == "Admin"){
	$admin = "";
}

//init cq_quote varible 
$cq_quote = "";
$cq_sow = "";
$cq_description = "";
$cq_draft = "";
$cq_sub_date = "";
$cq_exp_date = "";
$quote_list = [];

//init display variable
$step1_display = "block";
$step2_display = "none";

//check to see if cq is set in header (if so we are using an existing combined quote)
if (isset($_GET["cq"])){
	//grab from GET var
	$cq_quote = $_GET["cq"];
	
	//break into version and quote
	$quote = substr($cq_quote, 0, strpos($cq_quote, "v"));
	$cq_version = substr($cq_quote, strpos($cq_quote, "v")+1, 10);
	
	//run query to grab sow and description
	$query = "select * from fst_grid WHERE quoteNumber = '" . $cq_quote . "';";
	$result = mysqli_query($con, $query);
	
	$temp = mysqli_fetch_array($result);
	
	//pass to cq variables
	$cq_sow = $temp['sow'];
	$cq_description = $temp['phaseName'];
	$cq_draft = $temp['draft'];
	$cq_sub_date = $temp['sub_date'];
	$cq_exp_date = $temp['exp_date'];
	
	//fix version based on length
	if (strlen($cq_version) == 1)
		$cq_version = "00" . $cq_version;
	elseif(strlen($cq_version) == 2)
		$cq_version = "0" . $cq_version;
		
	//grab quotes based cq_quote value
	$query = "select * from fst_grid_linked WHERE combinedQ = '" . $quote . '-' . $cq_version . "'";
	$result = mysqli_query($con, $query);

	//read from query into arrays
	while($rows = mysqli_fetch_assoc($result)){
		array_push($quote_list, $rows['quoteNumber']);
	}
	
	//flip display variable
	$step1_display = "none";
	$step2_display = "block";
}

//init location and customer variable
$init_customer = "";
$init_location = "";

//check for POST variables (customer and location)
if (isset($_POST['link_location'])){
	$init_location = $_POST['link_location'];
}
if (isset($_POST['link_customer'])){
	$init_customer = $_POST['link_customer'];
}

//init customer arrays
$customer = [];
$cust_id = [];

//load existing customers in
$query = "select * from fst_customers order by customer";
$result = mysqli_query($con, $query);

//read from query into arrays
while($rows = mysqli_fetch_assoc($result)){
	array_push($customer, $rows['customer']);
	array_push($cust_id, $rows['cust_id']);
}

//read in locations
$locations = [];

$query = "select location_name from fst_grid WHERE location_name <> '' GROUP BY location_name ORDER BY location_name asc;";
$result = mysqli_query($con, $query);

//read from query into arrays
while($rows = mysqli_fetch_assoc($result)){
	array_push($locations, $rows['location_name']);
}

//read in customers
$customers = [];
$customers_id = [];


$query = "select * from fst_customers where customer <> '' order by customer;";
$result = mysqli_query($con, $query);

//read from query into arrays
while($rows = mysqli_fetch_assoc($result)){
	array_push($customers, $rows['customer']);
	array_push($customers_id, $rows['cust_id']);
}

//grab customer PM's based on customer
$customer_pm = [];
$customer_pm_phone = [];
$customer_pm_email = [];

$query = "select * from fst_contacts;";
$result = mysqli_query($con, $query);

while($rows = mysqli_fetch_assoc($result)){
	array_push($customer_pm, $rows['project_lead']);
	array_push($customer_pm_phone, $rows['number']);
	array_push($customer_pm_email, $rows['email']);
}

//read in quotes
$quote = [];
$quote_locations = [];
$quote_customers = [];
$quote_value = [];
$quote_description = [];

$query = "select quoteNumber, location_name, customer, totalPrice, phaseName from fst_grid where location_name <> '' order by location_name;";
$result = mysqli_query($con, $query);

//read from query into arrays
while($rows = mysqli_fetch_assoc($result)){
	array_push($quote, $rows['quoteNumber']);
	array_push($quote_locations, $rows['location_name']);
	array_push($quote_customers, $rows['customer']);
	array_push($quote_value, $rows['totalPrice']);
	array_push($quote_description, $rows['phaseName']);
}

//read in main categories used for service section
$main_categories = [];

$query = "select mainCat from fst_body WHERE mainCat NOT IN ('Materials', 'Discounts', 'Travel') group by mainCat order by id;";
$result = mysqli_query($con, $query);

//read from query into arrays
while($rows = mysqli_fetch_assoc($result)){
	array_push($main_categories, $rows['mainCat']);
}

//load in material categories (used for grouping BOM)
$full_cat = [];
$full_cat_description = [];

$query = "SELECT * FROM general_material_categories;";
$result = mysqli_query($con, $query);

while($rows = mysqli_fetch_assoc($result)){
	array_push($full_cat, $rows['category']);
	array_push($full_cat_description, $rows['description']);
	
}

//used to see what groupings different categories fall under (pulled from fst_body)
$summary_array = array('');
$query = "SELECT id, category from fst_body ORDER BY id";
$result = mysqli_query($con, $query);

//create a string that can be transfered to javascript
while($rows = mysqli_fetch_assoc($result)){
	array_push($summary_array, $rows['category']);
}

?>


<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="google-signin-client_id" content="573761357198-hin7ae7q19qgvoab7t0781b41530546g.apps.googleusercontent.com">
<link rel="shortcut icon" type="image/x-icon" href="images/P-Logo.png" />
<link rel="stylesheet" href="stylesheets/element-styles.css?<?= $version; ?>" />
<link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css'> 
<link href = "stylesheets/jquery-ui-themes-1.13.0/themes/ui-lightness/jquery-ui.css" rel = "stylesheet">
<title>Quote Link (v<?= $version ?>) - Pierson Wireless</title>
	
<style>
	
	select:focus{
		border-width: 3.8px !important;
	}

	.stock{
		text-align: center;
		font-weight: bold;
	}

	input:read-only:not([type=button]):not([type=submit]):not([type=file]){
		background-color:#C8C8C8;
	}

	input:read-write, textarea {
		background-color:#BBDFFA;
		border-color: #000B51;
		border-width: medium;
		
	}

	#basicInfo {
			float: left;
		}

	.homeTables {
		border-collapse: collapse;

	}	

	.homeTables tbody {
		display: block;
		overflow-y:scroll;
		height: 500px;
		width: 1950px;
	}

	.homeTables thead, .homeTables tbody tr{
		display: table;
		table-layout: fixed;
	}

	.homeTables tbody td {
		border-bottom: 1px solid #000000;
		border-right: 1px solid #000000;
		border-left: 1px solid #000000;

	}

	.homeTables thead th {
		border: 1px solid #000000;

	}

	#profileTable{
		border-collapse: collapse;
	}

	#profileTable td{
		border: 1px solid #000000;
		padding: 5px;
	}

	#profileTable th{

		padding: 5px;
	}
	.custom-select{
		background-color:#BBDFFA;
		border-color: #000B51;
		border-width: medium;
		cursor: pointer;		

	}
	
	.standard_input{
		width: 30em;
		-ms-box-sizing:content-box;
		-moz-box-sizing:content-box;
		box-sizing:content-box;
		-webkit-box-sizing:content-box;
	}
	
	.standard_select{
		width: 30.3em;
		height: 16px;
		-ms-box-sizing:content-box;
		-moz-box-sizing:content-box;
		box-sizing:content-box;
		-webkit-box-sizing:content-box;
	}

	/* Style the tab content (and add height:100% for full page content) */
	.tabcontent {
	  padding: 60px 20px;
	  height: 100%;
	}

	/* Style customer inputs*/
	.custom-input-header{
		width: 13.2em;

	}	

	.custom-select-header{
		background-color:#BBDFFA;
		border-color: #000B51;
		border-width: medium;
		cursor: pointer;
		width: 14em;

	}

	.basic-table{
		display: inline-block;
		padding-bottom: 5px;
	}

	.basic-table td{
		padding-right: 5px;
	}
	.loc_tables th{
		text-align: left;
	}




</style>
</head>

<body>
	
<?php

	//define array of names & Id's to generate headers
	$header_names = ['Linked Quotes Module'];
	$header_ids = ['main_content'];

	//pass to php function to create navigation bars
	echo create_navigation_bar($header_names, $header_ids, "save()", $fstUser);

?>
	
	<div id = 'main_content' class = 'tabcontent' >

		<!Quote Info Header>
		<form action='newProject_handler.php' method='POST'>

			<div id = 'step1' style = 'display:<?= $step1_display; ?>'>
				
				<h2>Please enter the following information. </h2>
				<table>
					<tr>
						<th>Location: </th>
						<td><input id = 'location' value = "<?= $init_location;?>" style = 'width: 20em;'></td>
					</tr>
					<tr>
						<th>Customer: </th>
						<td><input id = 'customer' value = '<?= $init_customer;?>' style = 'width: 20em;'></td>
					</tr>
					<tr>
						<td><button onclick = 'z.next("Search")' form="">Search for Quotes</button></td>
					</tr>
					
				</table>
				
				<h2>Provide a brief description of the combined quote along with a Scope of Work. </h2>
				<table>
					<tr>
						<th>Description: </th>
						<td><input id = 'description' style = 'width: 800px' value="<?= $cq_description; ?>"></td>
					</tr>
					<tr>
						<th>Scope of Work: </th>
						<td><textarea id = 'scope_of_work' style = 'width: 800px; resize: vertical'><?= $cq_sow; ?></textarea>
						</td>
					</tr>
					
				</table>
				
				<div id = 'quote_div' style = 'display:none'>
					<h2>Select the Quotes that you would like to link from the entered criteria: </h2>
					<table id = 'quote_table'>
						<tr>
							<th></th>
							<th>Quote Number</th>
							<th>$ Value</th>
							<th>Description</th>
						</tr>

					</table>
					<button onclick = 'z.next("Create")' form = ''>Create Linked Quote</button>
				</div>

			</div>
			
			<div id = 'step2' style = 'display:<?= $step2_display; ?>'>
				<h2>Please make any final adjustments </h2>
				
				<button onclick = 'refresh_values()' class = '' form="">Refresh Values</button><br><br>
				<button onclick = 'z.export_pdf_handler(false)' class = '' form="">Export PDF</button>
				<button onclick = 'z.export_pdf_handler(true)' class = '' form="">Preview PDF</button><br><br>
				<button onclick = 'save()' form = ''>Save</button>
				
				<br><br>
				
				<table>
					<tr>
						<th>Description: </th>
						<td><input id = 'final_description' style = 'width: 800px'></td>
					</tr>
					<tr>
						<th>Scope of Work: </th>
						<td><textarea id = 'final_scope_of_work' style = 'width: 800px; resize: vertical'></textarea></td>
					</tr>
					<?php
						
						//depending on pdf_version value, hide rows or show rows
						if ($cq_draft == "Final"){
							$final = "selected";
							$display = "visible";

						}
						else{
							$final = "";
							$display = "collapse";
						}

					?>
					<tr>
						<th>Draft/Final</th>
						<td>
							<select id = 'pdf_version' class = 'custom-select' onchange = 'z.show_date_picker(this.value)' name = 'draft' form = 'saveInfo' >
								<option>Draft</option>
								<option <?= $final; ?>>Final</option>
							</select>
						</td>
					</tr>
					<tr style = 'visibility: <?= $display; ?>' id = 'sub_row'>
						<th>Submitted Date:</th>
						<td><input type = 'date' id = 'date_submitted' name = 'date_submitted' form = 'saveInfo' value = '<?= $cq_sub_date; ?>' onchange = 'z.update_expiration(this.value)'></td>
					</tr>
					<tr style = 'visibility: <?= $display; ?>' id = 'exp_row'>
						<th>Expiration Date:</th>
						<td><input type = 'date' id = 'date_expired' name = 'date_expired'  value = '<?= $cq_exp_date; ?>' readonly> </td>
					</tr>
					<tr>
						<td>
							<ul id = 'linked_ul'></ul>
						</td>
					</tr>
					
				</table>
				
				<div id = 'print_preview_window' style = 'flex: 1; display: none' >
					<iframe src = '' id = 'target_iframe' width = '1200px' height = '1200px'></iframe>
				</div>
					
			</div>
		
	</div>
	
	<div class = 'ui-widget' id = 'new-customer' style = 'display:none'> 
		<table class = 'loc_tables' align = "center" border = "0px" style = "line-height:20px;">
			<tr>
				<th colspan = '2'> <h3>Customer Information</h3> </th>
			</tr>
			<tr>
				<th class = 'mc_head'> Customer Name </th>
				<td> 
					<input id = 'newCust_name' class = 'standard_input cust_required' readonly/>
				</td>
			</tr>
			<tr>
				<th class = 'mc_head'> Address Line 1 </th>
				<td> 
					<input id = 'newCust_address1' class = 'standard_input cust_required'/>
				</td>
			</tr>
			<tr>
				<th class = 'mc_head'> Address Line 2 </th>
				<td> 
					<input id = 'newCust_address2' class = 'standard_input'/>
				</td>
			</tr>
			<tr>
				<th class = 'mc_head'> City, State, Zip </th>
				<td> 
					<input type='text' id = 'newCust_city' style = 'width: 10em' class = 'cust_required'> &nbsp; 
					<select type='text' id = 'newCust_state' style = 'width: 5em' class = 'custom-select  cust_required'>
						<option></option>
							<?php 
								//read from query into arrays
								for ($i = 0; $i < sizeof($states); $i++){
							?>
							<option><?= $states[$i]; ?></option>
							<?php
								}
							?>
					</select> &nbsp; <input type='text' id = 'newCust_zip' style = 'width: 5em' class = 'cust_required'>
				</td>
			</tr>
			<tr>
				<th class = 'mc_head'> Contact </th>
				<td> 
					<input id = 'newCust_contact' class = 'standard_input cust_required'/>
				</td>
			</tr>
			<tr>
				<th class = 'mc_head'> Contact Phone </th>
				<td> 
					<input id = 'newCust_phone' class = 'standard_input cust_required'/>
				</td>
			</tr>
			<tr>
				<th class = 'mc_head'> Contact Email </th>
				<td> 
					<input id = 'newCust_email' class = 'standard_input cust_required'/>
				</td>
			</tr>
			<tr>
				<td style = 'padding-top: 1em'><button onclick = z.create_customer()>Create Customer</button></td>
			</tr>
		</table>
	</div>
		
	<!-- external js libraries -->
	<!-- used for ajax calls -->
	<script	src = "https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
	<script src="https://apis.google.com/js/platform.js?onload=init" async defer></script>
	
	<!-- used for jquery -->
	<script src="https://code.jquery.com/jquery-1.12.4.js"></script>
	<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
	
	<!-- used for pdf generation -->
	<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.3.0-beta.1/pdfmake.min.js" integrity="sha512-G332POpNexhCYGoyPfct/0/K1BZc4vHO5XSzRENRML0evYCaRpAUNxFinoIJCZFJlGGnOWJbtMLgEGRtiCJ0Yw==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.3.0-beta.1/standard-fonts/Times.js" integrity="sha512-KSVIiw2otDZjf/c/0OW7x/4Fy4lM7bRBdR7fQnUVUOMUZJfX/bZNrlkCHonnlwq3UlVc43+Z6Md2HeUGa2eMqw==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

	<!-- internally defined js files -->
	<script src="javascript/js_helper.js?<?= $version ?>-1"></script>
	<script src="javascript/fst_js_functions.js"></script>
	<script src = "javascript/accounting.js"></script>
	<script src = "javascript/utils.js"></script>
			
	<script>
		
		//Namespace
		var z = {}
		
		//new will be used to determine what kind of project this is
		var multi_location = null;
		
		//pass url link
		var sub_link = '<?= $sub_link; ?>';
		
		//pass varibles that may help make a decision
		var cq_quote = '<?= $cq_quote; ?>',
			cq_quote_list = <?= json_encode($quote_list); ?>,
			url_location = '',
			url_customer = '';
			
		//pass URL to JS
		var sub_url = '<?= $sub_link ?>';
		
		//pass arrays to js from PHP
		var locations = <?= json_encode($locations) ?>, 
			quote = <?= json_encode($quote) ?>, 
			quote_locations = <?= json_encode($quote_locations) ?>,
			quote_customers = <?= json_encode($quote_customers) ?>,
			quote_value = <?= json_encode($quote_value) ?>,
			quote_description = <?= json_encode($quote_description) ?>,	
			customers = <?= json_encode($customers) ?>, 
			customers_id = <?= json_encode($customers_id) ?>, 	
			main_categories = <?= json_encode($main_categories) ?>, 
			full_cat = <?= json_encode($full_cat) ?>, 
			full_cat_description = <?= json_encode($full_cat_description) ?>;
			
		//holds customer pm info used for the quote
		var customer_pm = <?= json_encode($customer_pm); ?>,
			customer_pm_phone = <?= json_encode($customer_pm_phone); ?>,
			customer_pm_email = <?= json_encode($customer_pm_email); ?>, 
			customers = <?= json_encode($customers); ?>, 
			customers_id = <?= json_encode($customers_id); ?>;
		
		//categorizes each row into categories (i = internal, e = external, s = , m = materials, t = travel)
		var summaryVals = <?= json_encode($summary_array); ?>;
			
		//set options to locations array
		
		var options_location = {
			source: locations,
			minLength: 2
		};
		
		//choose selector (input with location as class)
		var selector_location = '#location';
		
		//on keydown, show autocomplete after 2 characters
		$(document).on('keydown.autocomplete', selector_location, function() {
			$(this).autocomplete(options_location);
		});
		
		//set options to locations array
		var options_customer = {
			source: customers,
			minLength: 2
		};
		
		//choose selector (input with location as class)
		var selector_customer = '#customer';
		
		//on keydown, show autocomplete after 2 characters
		$(document).on('keydown.autocomplete', selector_customer, function() {
			$(this).autocomplete(options_customer);
		});
		
		$(document).keypress(
		  function(event){
			if (event.which == '13') {
			  event.preventDefault();
			}
		});
		
		//global to tell site that we are combining
		var combining = false;
		
		//moves from one step to the next
		z.next = function(event=null){
						
			//if search, grab quotes and list them out for user to select
			if (event == "Search"){
								
				//show table displaying quotes
				u.eid("quote_div").style.display = "block";
				
				//loop through criteria entered and grab quotes that meet the criteria
				z.addQuotes();
				
			}
			
			//if Create, combine linked quotes and move to next menu where user will be able to export and preview quote
			else if (event == "Create"){
				
				//transfer scope and description
				u.eid("final_scope_of_work").value = u.eid("scope_of_work").value;
				u.eid("final_description").value = u.eid("description").value;
				
				//grab checkbox and quote number classes
				var checks = document.getElementsByClassName("quote_checkbox"), 
					quote_numbers = document.getElementsByClassName("quote_number");

				//array to hold list of quotes
				var quote_list = [];

				//loop through all elements in the class
				for (var i = 0; i < checks.length; i++){
					if (checks[i].checked)						
						quote_list.push(list_of_quotes[i]);

				}

				//once we have our list, pass this array to new function to grab info needed
				//second argument true if creating a new quote
				z.get_info(quote_list, true);
				
				//create links to quotes that are easy to navigate
				generate_links(quote_list);
				
			}
			
			//if Existing, use global for linked quotes to grab info, use global to read in scope of work and desription as well
			else if (event == "Existing"){
				
				//transfer scope and description
				u.eid("final_scope_of_work").value = u.eid("scope_of_work").value;
				u.eid("final_description").value = u.eid("description").value;

				//once we have our list, pass this array to new function to grab info needed
				//second argument true if creating a new quote
				z.get_info(cq_quote_list, false);

				//create links to quotes that are easy to navigate
				generate_links(cq_quote_list);
				
				//resize text area
				$(function () {
					$("#final_scope_of_work").each(function () {
						this.style.height = 0;
						this.style.height = (this.scrollHeight+20)+'px';
					});
				});
				
			}
			
			//if it has already been created, lets move to the next module (preview and display)
			else if (event == "Created"){
				
				//hide step 1
				u.eid("step1").style.display = "none";
				
				//show step 2
				u.eid("step2").style.display = "block";
			
				//reset combining
				combining = false;
				
			}

		}
		
		//init global to hold list of quotes
		var list_of_quotes = [];
		
		//handles refreshing quote values (if any updates are made)
		function refresh_values(){
			
			//init list of quotes
			var quote_list = [];
						
			//loop through project info array, add quotes # and call get info
			for (var i = 0; i < project_info[0].length; i++){
				//push to array
				quote_list.push(project_info[0][i].quoteNumber);
			}
		
			//call get info with new list
			z.get_info(quote_list, false)
			
		}
		
		//handles ajax call to save values for linked quote
		function save(){
			
			//grab other variables to be passed
			var sow = u.eid("final_scope_of_work").value,
				description = u.eid("final_description").value,
				draft = u.eid("pdf_version").value,
				sub_date = u.eid("date_submitted").value,
				exp_date = u.eid("date_expired").value;
			
			//init form data variable
			var fd = new FormData();
			
			//pass info to be saved
			fd.append('sow', sow);
			fd.append('description', description);
			fd.append('draft', draft);
			fd.append('sub_date', sub_date);
			fd.append('exp_date', exp_date);
			
			//pass quote #
			fd.append('cq_quote_number', cq_quote);
			
			//tell (lets helper know what to do)
			fd.append('tell', 'save')
			
			//ajax request to communicate with database
			$.ajax({
				url: 'quoteLink_helper.php',
				type: 'POST',
				processData: false,
				contentType: false,
				data: fd,
				success : function (response) {
					
					//if we have any response, alert (likely an error)
					if (response != ""){
						alert (response);
					}
					else{
						alert ('Changes have been saved.');
					}
					
				}
			});
			
		}
		
		//generates list of links (used list_of_quotes global)
		function generate_links(quote_list){
			
			//removed previous links
			document.querySelectorAll('.single_link').forEach(function(a){
				a.remove()
			})
									
			//loop through list of quotes and generate link for each one
			for (var i = 0; i < quote_list.length; i++){
				var ul = document.getElementById("linked_ul");
				var li = document.createElement("li");
				li.setAttribute('class', 'linked_ul');
				var a = document.createElement("a");
				a.setAttribute('href', sub_link + 'application.php?quote=' + quote_list[i]);
				a.setAttribute('target', '_blank');
				a.innerHTML = 'Link to Quote #' + quote_list[i];
				li.appendChild(a);
				ul.appendChild(li);
				
			}
			
		}
		
		//handles showing and hiding date picker based on draft/final version
		z.show_date_picker = function(version) {
			
			//check if version is final, if it is, show date picker
			if (version == "Final"){
				u.eid("sub_row").style.visibility = "visible";
				u.eid("exp_row").style.visibility = "visible";
			}
			else{
				u.eid("sub_row").style.visibility = "collapse";
				u.eid("exp_row").style.visibility = "collapse";
			}
			
		}
		
		//updates expiration date based on submitted date
		z.update_expiration = function(sub_date){
        
			//set current expiration window (30 days)
			var exp_days = 31;
			
			//update sub_date to js date
			sub_date = new Date(sub_date)
			
			//set exp_date based on date picker
			var exp_date = new Date(sub_date);
			
			//add exp_days to exp_date;
			exp_date.setDate(exp_date.getDate() + exp_days);
            
            //grab day
            var day = exp_date.getDate();
            //convert to string
            day = day.toString();
            
            //adjust if length = 1
            if (day.length == 1)
            	day = "0" + day;
                
            //grab month
            var month = exp_date.getMonth() + 1;
            //convert to string
            month = month.toString();
            
            //adjust if length = 1
            if (month.length == 1)
            	month = "0" + month;
                
			//grab year
            var year = exp_date.getFullYear();
            
            //put together into string for output
            exp_date = year + "-" + month + "-" + day;
                              
			//set in expired date picker
			u.eid("date_expired").value = exp_date;
			
		}
		
		//handles adding quotes for step 4A according to location and customer
		z.addQuotes = function(){
					
			//removed previous list
			document.querySelectorAll('.quote_list').forEach(function(a){
				a.remove()
			})
			
			//grab customer and location
			var customer = u.eid("customer").value, 
				location = u.eid("location").value;
			
			//reset list of quotes
			list_of_quotes = [];
			
			//loop through quotes and add rows for any that match customer and location
			for (var i = 0; i < quote.length; i++){
				
				//check customer first
				if (quote_customers[i] == customer.trim()){
				
					//only check location if it is filled out
					if (quote_locations[i] == location || location == ""){

						//grab table
						var table = u.eid("quote_table");

						//insert new row and add classname to it
						var row = table.insertRow(-1);
						row.classList.add("quote_list");

						//checkbox
						var cell = row.insertCell(0);
						cell.innerHTML = "<input type = 'checkbox' class = 'quote_checkbox'>";

						//quote Number
						var cell = row.insertCell(1);
						cell.innerHTML = quote[i];

						//$ value
						var cell = row.insertCell(2);
						cell.innerHTML = accounting.formatMoney(quote_value[i]);

						//description
						var cell = row.insertCell(3);
						cell.innerHTML = quote_description[i];

						//push quote number
						list_of_quotes.push(quote[i]);

					}
				}
				
			}
			
		}
		
		//global to hold all information about a project
		var project_info;
		
		//handles grabbing information necessary to create linked quotes
		z.get_info = function(quote_list, create_new = false){
			
			//turn combining to true
			combining = true;
			
			//grab other variables to be passed
			var sow = u.eid("scope_of_work").value,
				description = u.eid("description").value,
				customer = u.eid("customer").value;
			
			//init form data variable
			var fd = new FormData();
			
			//pass reference quote and tell
			fd.append('quote_list', JSON.stringify(quote_list));
			fd.append('tell', 'get_info');
			fd.append('create_new', create_new);
			
			//pass other info that will be saved
			fd.append('sow', sow);
			fd.append('description', description);
			fd.append('customer', customer);
			
			//ajax request to communicate with database
			$.ajax({
				url: 'quoteLink_helper.php',
				type: 'POST',
				processData: false,
				contentType: false,
				data: fd,
				success : function (response) {
					
					//transfer response to global
					project_info = $.parseJSON(response); 
					console.log(project_info);
					
					//if cq_quote does not have a value, reload the page
					if (cq_quote == ""){
						//seperate into quote and version
						var quote = project_info[6].substring(0, 10);
						var version = project_info[6].substring(11);
						
						debugger;
						
						//adjust version
						version = parseInt(version);
						version = version.toString();
						
						//change URL
						location = sub_link + "quoteLink.php?cq=" + quote + "v" + version;
						//cq_quote = project_info[6];
					}
					
				}
			});
			
		}
		
		// Because of security restrictions, getImageFromUrl will
		// not load images from other domains.  Chrome has added
		// security restrictions that prevent it from loading images
		// when running local files.  Run with: chromium --allow-file-access-from-files --allow-file-access
		// to temporarily get around this issue.
		var getImageFromUrl = function(url, callback) {
			var img = new Image();

			img.onError = function() {
				alert('Cannot load image: "'+url+'"');
			};
			img.onload = function() {
				callback(img);
			};
			img.src = url;
		}
		
		//global used to handle print preview
		var print_preview = false;
		
		//handles initial export steps (print preview decision & loading image)
		z.export_pdf_handler = function(dec){
			print_preview = dec;
			getImageFromUrl('images/PW_Std Logo.png', new_pdf);
		}
		
		//handle pdf export
		function new_pdf(imgData){
			
			//grab variables used in quote
			var scope = u.eid("final_scope_of_work").value.trim();
			
			//will hold header info 
			var header_left = ""; 
			
			//project location/description, city, state
			//use the first project loaded in
			var project_loc = project_info[0][0].location_name, 
				project_desc = u.eid("final_description").value,
				project_city = project_info[0][0].city, 
				project_state = project_info[0][0].state,
				cust_id = project_info[0][0].custID; 
			
			//customer specific info
			var customer = project_info[0][0].customer;
			
			//init customer info
			var cust_contact = "", 
				cust_phone = "", 
				cust_email = "";
			
			//grab customer pm index
			var pm_index = customer_pm.indexOf(project_info[0][0].customer_pm);
			
			//if index != -1
			if (pm_index != -1){
				cust_contact = customer_pm[pm_index], 
				cust_phone = customer_pm_phone[pm_index], 
				cust_email = customer_pm_email[pm_index];
			}
			
			//add info to header
			header_left += project_loc + " - " + project_desc + "\n"; 
			header_left += project_city + ", " + project_state + "\n";
			
			//check if cust_id is null
			if (cust_id != ""){
				header_left += "Customer PN: " + cust_id + "\n"; 
			}
			
			//add extra space
			header_left += "\n";
			
			//general customer info
			header_left += customer + "\n"; 
			header_left += cust_contact + "\n"; 
			header_left += cust_phone + "\n"; 
			header_left += cust_email + "\n"; 
			
			//set sub_date based on if this is a draft or not
			var draft_option = u.eid("pdf_version").value,
				sub_date = "", 
				exp_date = "", 
				watermark = "NON BINDING";
			
			//check if this is a draft or a final copy
			if (draft_option == "Final"){
				//grab date from date picker
				sub_date = format_date(u.eid("date_submitted").value);
				
				//grab expired date from hidden date picker (date_expired)
				exp_date = format_date(u.eid("date_expired").value);
				
				//unset watermark
				watermark = "";
			}
			
			//create the right side of the header
			var header_right = "";
			
			//break out the quote number and version
			var vp_num = cq_quote.substr(0, 10), 
				quote_revision = cq_quote.substr(11);
			
			//edit quote_revision based on size
			if (quote_revision.length == 1)
				quote_revision = "00" + quote_revision;
			else if (quote_revision.length == 2)
				quote_revision = "0" + quote_revision;
			
			//set header on the right side
			header_right+= vp_num + "\n"; 
			header_right+= quote_revision + "\n\n"; 
			header_right+= sub_date + "\n"; 
			header_right+= exp_date + "\n"; 
			
			//add "project number" to vp_num for header
			vp_num = "Project Number: " + vp_num;
			
			//used to generate image base 64 url
			var c = document.createElement('canvas');
			var img = document.getElementById('pw_logo');
			c.height = img.naturalHeight;
			c.width = img.naturalWidth;
			var ctx = c.getContext('2d');

			ctx.drawImage(img, 0, 0, c.width, c.height);
			var base64String = c.toDataURL();
			
			
			//********************
			
			//generate document based on criteria
			var docDefinition = {
				pageSize: 'A4',
				pageMargins: [40, 50, 40, 100], //[horizontal, vertical] or [left, top, right, bottom]
				defaultStyle: {
					font: 'Times'	
				},
				footer: [
					{
						text: '\n\nPierson Wireless Corp. | 11414 South 145th St. | Omaha, Nebraska 68138\n\nPlease submit orders to sales-orders@piersonwireless.com or fax 402-625-6101 for processing.', 
					 	alignment: 'center', 
						style: 'footer_style'
					}
				],
				header: function(currentPage, pageCount, pageSize) {
					// first page, just show page number
					if (currentPage == 1){
						return [
						  { text: currentPage, alignment: 'right', style: 'header_rep' },
						]
					}
					//after first, show project loc, project #, and page #
					else{
						return [
							{
								columns: [
									{
										text: project_loc, 
										style: 'header_rep',
										alignment: 'left'
									}, 
									{
										text: vp_num, 
										style: 'header_rep', 
										alignment: 'center'
									}, 
									{
										text: currentPage, 
										alignment: 'right',
										style: 'header_rep'
									}
								], 
							},
						]
					}
				  },
				watermark: {text: watermark, color: 'gray', opacity: 0.1, bold: true},
				content:[
					{
						image: base64String,
						width: 100, 
						style: 'header_logo'
					},
					{
						columns: [
							{
								width: 350,
								text: header_left, 
								style: 'header_main'
							}, 
							{
								text: 'Project Number: \nQuote Revision: \n\nCreation Date: \nExpiration Date:', 
								style: 'header_main'
							}, 
							{
								text: header_right, 
								style: 'header_main'
							}
						]
					},
					{
						text: 'Scope of Work', 
						style: 'header_style'
					},
					{
						text: scope, 
						style: 'body_text'
					},  
					//generates table based on type 
					table_handler('quote_summary'),
					table_handler('total_summary'),
					generate_quotes()
					
				], 
				styles: {
					header_style: {
						fontSize: 16, 
						bold: true, 
						margin: [0, 20, 0, 10]
					}, 
					header_sub: {
						fontSize: 14, 
						bold: true,
						italics: true, 
						margin: [0, 0, 0, 10]
					}, 
					header_logo: {
						margin: [-10, 0, 0, 0]
					}, 
					header_main: {
						fontSize: 10.5, 
						margin: [0, 20, 0, 0],
						lineHeight: 1.2
					}, 
					header_rep: {
						fontSize: 9, 
						margin: [40, 20, 40, 10], 
						color: 'gray'
					}, 
					body_text: {
						fontSize: 10.5, 
						margin: [0, 0, 0, 15],
						lineHeight: 1.2,
						alignment: 'justify'
					},
					table_header: {
						fontSize: 10, 
						bold: true, 
						fillColor: '#114B95', 
						color: 'white'
						
					}, 
					table_body: {
						fontSize: 9, 
						margin: [0, 10, 0, 10], 
						unbreakable: true,
						lineHeight: 1.2
					}, 
					total_row: {
						fontSize: 9.5, 
						bold: true
					}, 
					footer_style: {
						fontSize: 8, 
						italics: true, 
						color: 'gray'
					}, 
					italics_row: {
						italics: true, 
						fontSize: 9
					}
					
				}
			};			
			
			//********PDF PRINT PREVIEW
			if (print_preview){
				
				pdfMake.createPdf(docDefinition).getDataUrl().then((dataUrl) => {
					//set src to dataURL
					u.eid("target_iframe").src = dataUrl;
				}, err => {
					console.error(err);
				});

				pdfMake.createPdf(docDefinition).getDataUrl();
				
				//show div holding this
				u.eid("print_preview_window").style.display = "block";

			}
			else{
				
				//try to get customer id
				var customer_id = customer;
				var cust_index = customers.indexOf(customer);
				
				if (cust_index != -1)
					customer_id = customers_id[cust_index];
				
				//create name 
				var output_name = "PW - " + customer_id + " - " + project_desc + " - Quote " + cq_quote;
				pdfMake.createPdf(docDefinition).download(output_name);
			}
						
		}
		
		//function that cycles through quotes and exports to pdf
		function generate_quotes(){
			
			//set variable to be returned
			var return_var = [];
			
			//loop through quotes and generate each quote independently
			for (var i = 0; i < project_info[0].length; i++){
				//first reset globabl
				reset_globals_link(i);
				
				//run table_handler for all types
				return_var.push(quote_headers());
				return_var.push(table_handler('bom'));
				return_var.push(table_handler('services'));
				return_var.push(table_handler('total'));
				
				//add clarifications
				return_var.push(add_clarifications('project'));
				
			}
			
			//add general clarifications
			return_var.push(add_clarifications('general'));
			
			//send back return variable
			return return_var;
		}
		
		//handles adding clarifications (based on type)
		//type can be = 'project' or 'general'
		function add_clarifications(type){
			
			//init return variable
			var return_var = [];
			
			//if project, then just loop through global array and title project specific
			if (type == "project"){
				
				//check size of clarifications (if none, state no clarifications for this quote)
				if (clarifications.length != 0){
					//push objected needed to created clar section
					return_var.push({
						text: 'Project Specific Clarifications (Quote #' + basic_info.projectNumber + ')', 
						style: 'header_style'
					});
					return_var.push({
						style: 'body_text',
						ul: clarifications
					});
				}

				//return variable
				return return_var;
				
			}
			else if (type == "general"){
				
				//init general_clars array
				var general_clars = [];
				
				//cycle through all general sections and only add uniques to an array
				for (var i = 0; i < project_info[5].length; i++){
					//inner loop to actually move through clarifications for a project
					for (var j = 0; j < project_info[5][i].length; j++){
						//call unique array function which only adds unique values to an array
						general_clars.push(project_info[5][i][j]);
					}					
				}
				
				//remove duplicated from general_clars
				general_clars = [...new Set(general_clars)];

				//push objected needed to created clar section
				return_var.push({
					text: 'General Clarifications', 
					pageBreak: 'before',
					style: 'header_style'
				});
				
				//check size of clarifications (if none, state no clarifications for this quote)
				if (general_clars.length == 0){
					return_var.push({
						style: 'body_text',
						text: 'There are no general clarifications for this quote.'
					});
				}
				else{
					return_var.push({
						style: 'body_text',
						ul: general_clars
					});
				}

				//return variable
				return return_var;
				
			}
		}
		
		//globals that hold all information necessary to build out a quote
		var bom_array = []; 		//holds bom info
		var services_array = []; 	//holds service info
		var clarifications = [];	//holds clarifications
		var basic_info = [];		//holds basic info about the project
		var bom_sum = 0; 			//holds the sum of all bom
		var services_sum = 0;		//holds sum of all services
		var bom_sum_disc = 0		//holds sum after discount of bom
		var services_sum_disc = 0;	//holds sum after discount of services
		var l_disc = 0;				//holds service(labor) discount
		var m_disc = 0;				//holds material discount
		var mmd_sum = 0;			//holds MMD total
		var show_disc = false;		//show discount decision variable
		var hide_mats = false;		//hide materials decision variable
		var hide_services = false;	//hide services decision variable
		var hide_price = false;		//hide part price decision variable
		var group_bom = false;		//group bom decision variable
		var is_mmd = false;			//tell if the project is treated like MMD
		var show_nonMMD = false;	//shows non-MMD part (only applicable for verizon projects)
		var hide_matlog = false;	//hide material logistcs into whatever drop down the designer has chosen
		
		//resets globabl variables that will be used in table_handler & buildTableBody
		function reset_globals_link(id){
				
			//reset sum variables
			bom_sum = 0; 			//holds the sum of all bom
			services_sum = 0;		//holds sum of all services
			bom_sum_disc = 0		//holds sum after discount of bom
			services_sum_disc = 0;	//holds sum after discount of services
			mmd_sum = 0;			//holds total MMD value (used in summary section)
			
			//set decision booleans
			show_disc = false;		//show discount decision variable
			hide_mats = false;		//hide materials decision variable
			hide_services = false;	//hide services decision variable
			hide_price = false;		//hide part price decision variable
			group_bom = false;		//group bom decision variable
			is_mmd = false;			//tell if the project is treated like MMD
			show_nonMMD = false;	//shows non-MMD part (only applicable for verizon projects)
			hide_matlog = false;	//hide material logistcs into whatever drop down the designer has chosen
			
			//check each on in the database and flip to true if on
			if (project_info[0][id].discount_opt == "on")
				show_disc = true;
			if (project_info[0][id].hideMat_opt == "on")
				hide_mats = true;
			if (project_info[0][id].hideServ_opt == "on")
				hide_services = true;
			if (project_info[0][id].hidePrice_opt == "on")
				hide_price = true;
			if (project_info[0][id].groupBOM_opt == "on")
				group_bom = true;
			if (project_info[0][id].nonMMD_opt == "on")
				show_nonMMD = true;
			if (project_info[0][id].customer.toLowerCase() == "verizon")
				is_mmd = true;
			if (project_info[0][id].hidelog_opt == "on")
				hide_matlog = true;
			
			//set discount variables
			l_disc = parseFloat(project_info[3][id].labor_disc);
			m_disc = parseFloat(project_info[3][id].mat_disc);
			
			//loop through services and build object
			services_array = []; 
			
			for (var i = 0; i < project_info[1][id].length; i++){
				
				//build temp array to hold needed info
				var tempArray;
				
				//if mainCat is travel, save main_cat as role
				if (project_info[1][id][i].mainCat == 'Travel'){
					tempArray = {
						key: project_info[1][id][i].key,
						mainCat: project_info[1][id][i].role,
						subCat: "",
						price: parseFloat(project_info[1][id][i].price),
						category: project_info[1][id][i].category,
						seperate: false
					};
				}
				else{
					//init list_seperate
					var list_seperate = false;
					
					//check if list_seperate is on
					if (project_info[1][id][i].seperate == "on")
						list_seperate = true;
					
					tempArray = {
						key: project_info[1][id][i].key,
						mainCat: project_info[1][id][i].mainCat,
						subCat: project_info[1][id][i].subCat,
						price: parseFloat(project_info[1][id][i].price),
						category: project_info[1][id][i].category,
						seperate: list_seperate
					};
				}

				//push to bom_array
				services_array.push(tempArray);				
			}
			
			//read out for testing
			console.log(services_array);
			
			//loop through BOM and build object
			bom_array = []; 
			
			//init unit price and total price
			var unit_price = 0, unit_price_adjusted = 0, total_price = 0, total_price_adjusted = 0;
				mat_logflex = Math.round(((parseFloat(grab_key('matL'))) / project_info[2][id].length) * 100) / 100, 
				mat_logtotal = parseFloat(grab_key('matL')),
				mat_logrunning = 0,
				mat_applied = 0, 
				mat_new = true;
			
			//loop through material logistics to see if we need to use old or new method
			for (var i = 0; i < project_info[2][id].length; i++){
				if (project_info[2][id][i].matL == "" || project_info[2][id][i].matL == null){
					mat_new = false;
					break;
				}
			}
						
			for (var i = 0; i < project_info[2][id].length; i++){
				
				//if we are hiding material logistics (and this is not MMD) bake the mat logistcs into each part
				if (hide_matlog){

					//if new, use matL cost saved to fst_boms
					if (mat_new){
						unit_price = parseFloat(project_info[2][id][i].price) + parseFloat(project_info[2][id][i].matL);
						unit_price_adjusted = parseFloat((project_info[2][id][i].price * (1-m_disc)).toFixed(2));	//standard unit price
						unit_price_adjusted += (project_info[2][id][i].matL * (1 - m_disc));																//unit price + material logstics
						unit_price_adjusted = parseFloat(unit_price_adjusted.toFixed(2));
						total_price = unit_price * parseFloat(project_info[2][id][i].quantity);
						total_price_adjusted = unit_price_adjusted * parseFloat(project_info[2][id][i].quantity);
					}
					//if old, use old logic
					else{
						//grab unit price
						unit_price = parseFloat(project_info[2][id][i].price);

						//to calculate material logistics, take the average until the last part, then add whatever is leftover
						if (i == (project_info[2][id].length - 1)){
							unit_price = unit_price + ((mat_logtotal - mat_logrunning)/parseFloat(project_info[2][id][i].quantity));
							unit_price = Math.round(unit_price * 100) / 100;
							total_price = unit_price * parseFloat(project_info[2][id][i].quantity);
							mat_logrunning+= (mat_logtotal - mat_logrunning);

						}
						else{
							//calculate total price
							total_price = (unit_price * parseFloat(project_info[2][id][i].quantity)) + mat_logflex;

							//adjust unit based on total (round to 2)
							unit_price = total_price / parseFloat(project_info[2][id][i].quantity);
							unit_price = Math.round(unit_price * 100) / 100;

							//recalculate total price
							total_price = (unit_price * parseFloat(project_info[2][id][i].quantity));

							//adjust total again for new unit
							mat_applied = total_price - (parseFloat(project_info[2][id][i].price) * parseFloat(project_info[2][id][i].quantity));
							console.log(mat_applied);
							mat_logrunning+= mat_applied;

						}

						//default adjusted to unit
						unit_price_adjusted = unit_price;
						total_price_adjusted = total_price;
					}
				
				}
				else{
					unit_price = parseFloat(project_info[2][id][i].price);
					unit_price = unit_price.toFixed(2);
					unit_price_adjusted = parseFloat(project_info[2][id][i].price) * (1-m_disc);
					unit_price_adjusted = unit_price_adjusted.toFixed(2);
					total_price = unit_price * parseFloat(project_info[2][id][i].quantity);
					total_price_adjusted = unit_price_adjusted * parseFloat(project_info[2][id][i].quantity);
				}
				
				//build temp array to hold needed info
				var tempArray;
				tempArray = {
					partNumber: project_info[2][id][i].partNumber,
					description: project_info[2][id][i].description,
					manufacturer: project_info[2][id][i].manufacturer,
					quantity: parseFloat(project_info[2][id][i].quantity),
					price: unit_price,
					price_adjusted: unit_price_adjusted, 
					total_price: total_price,
					total_price_adjusted: total_price_adjusted,
					category: project_info[2][id][i].category, 
					mmd: project_info[2][id][i].mmd
				};
				
				//push to bom_array
				bom_array.push(tempArray);				
			}
			
			//read out for testing
			console.log(bom_array);
			
			//loop through services and build object
			basic_info = []; 
			
			//set taxes
			var user_tax = project_info[0][id].taxes;
			
			//check if taxes is null, if so, make 0
			if (project_info[0][id].taxes == null)
				user_tax = 0;
			
			//init customer info
			var cust_contact = "", 
				cust_phone = "", 
				cust_email = "";
			
			//grab customer pm index
			var pm_index = customer_pm.indexOf(project_info[0][id].customer_pm);
			
			//if index != -1
			if (pm_index != -1){
				cust_contact = customer_pm[pm_index], 
				cust_phone = customer_pm_phone[pm_index], 
				cust_email = customer_pm_email[pm_index];
			}
			
			//set basic info based on current project id
			basic_info = {
				location: project_info[0][id].location_name, 
				description: project_info[0][id].phaseName,
				street: project_info[0][id].address,
				city: project_info[0][id].city,
				state: project_info[0][id].state,
				zip: project_info[0][id].zip,
				customer: project_info[0][id].customer,
				custID: project_info[0][id].custID,
				pm: cust_contact,
				pm_number: cust_phone,
				pm_email: cust_email,
				projectNumber: project_info[0][id].quoteNumber,
				revision: project_info[0][id].quoteNumber.substr(project_info[0][id].quoteNumber.length - 1),
				scope_of_work: project_info[0][id].sow,	
				taxes: parseFloat(user_tax),
				labor_total: parseFloat(project_info[0][id].sPrice),
				mat_total: parseFloat(project_info[0][id].mPrice),
				mat_logistics: parseFloat(grab_key('matL'))
			};
			
			//adjust revision based on quoteNumber
			//if (basic_info.projectNumber.length > 10){
			//	basic_info.revision = basic_info.projectNumber.substr(11);
				
			//}
			
			//read out for testing
			console.log(basic_info);
			
			//set clarifications = to array passed from server
			clarifications = project_info[4][id];
			
			//read out for testing
			console.log(clarifications);
			
		}
		
		//generate BOM in PDF
		function quote_headers(){
			
			//set return variable
			var return_var = [];
			
			var headers = createHeaders([
				"Quote " + basic_info.projectNumber + " - " + basic_info.description
			]);
			
			//init quote break body & push headers
			var quote_break = [];
			quote_break.push(headers);
			
			//horizontal bar to denote seperate quote
			return_var.push({
				style: 'table_body',
				pageBreak: 'before',
				table: {
					widths: '*',
					headerRows: 1,
					body: quote_break
				}
			});
			
			//used to generate image base 64 url
			var c = document.createElement('canvas');
			var img = document.getElementById('pw_logo');
			c.height = img.naturalHeight;
			c.width = img.naturalWidth;
			var ctx = c.getContext('2d');

			ctx.drawImage(img, 0, 0, c.width, c.height);
			var base64String = c.toDataURL();
			
			//init variables to be used (header left and right)
			var h_left = "";
			var h_right = "";
	
			//init address variable (and build out)
			var address = "";
			
			//check for blanks and then add to array
			if (basic_info.street != "")
				address += basic_info.street + ", ";
			
			if (basic_info.city != "")
				address += basic_info.city + ", ";
			
			if (basic_info.state != "")
				address += basic_info.state + " ";
			
			if (basic_info.zip != "")
				address += basic_info.zip;
			
			//trim address for extra spaces
			address = address.trim();			
			
			//add info to header			
			h_left += basic_info.location + " - " + basic_info.description + "\n"; 
			h_left += address + "\n";
			
			//check if cust_id is null
			if (basic_info.custID != ""){
				h_left += "Customer PN: " + basic_info.custID + "\n"; 
			}
			
			//add extra space
			h_left += "\n";
			
			//general customer info
			h_left += basic_info.customer + "\n"; 
			h_left += basic_info.pm + "\n"; 
			h_left += basic_info.pm_number + "\n"; 
			h_left += basic_info.pm_email + "\n"; 
			
			//grab sub date and set expiration date
			var sub_date = format_date(u.eid("date_submitted").value), 
				exp_date = format_date(u.eid("date_expired").value);
			
			//reformat revision
			if (basic_info.revision.length == 1)
				basic_info.revision = "00" + basic_info.revision.length;
			else if (basic_info.revision.length == 2)
				basic_info.revision = "0" + basic_info.revision.length;
			
			h_right+= basic_info.projectNumber.substr(0, 10) + "\n"; 
			h_right+= basic_info.revision + "\n\n"; 
			h_right+= sub_date + "\n"; 
			h_right+= exp_date + "\n"; 
			
			//push objects needed
			//push logo
			return_var.push({
				image: base64String,
				width: 100, 
				style: 'header_logo'
			});
			
			//push header information for quote
			return_var.push({
				columns: [
					{
						width: 350,
						text: h_left, 
						style: 'header_main'
					}, 
					{
						text: 'Project Number: \nQuote Revision: \n\nCreation Date: \nExpiration Date:', 
						style: 'header_main'
					}, 
					{
						text: h_right, 
						style: 'header_main'
					}
				]
			});
			
			//push scope of work header
			return_var.push({
				text: 'Scope of Work', 
				style: 'header_style'
			});
			
			//push scope of work body
			return_var.push({
				text: basic_info.scope_of_work, 
				style: 'body_text'
			});
			
			//return object
			return return_var;
		}
		
		//generate BOM in PDF
		function table_handler(type){
			
			if (type == 'bom'){
				
				//if we don't want to show materials, skip adding the table
				if (!hide_mats){
							
					//first check to see if there are any parts in the BOM
					if (bom_array.length == 0){
						return {
							style: 'table_body',
							table: {
								widths: '*',
								headerRows: 1,
								body: buildTableBody(type)
							}
						};
					}
					
					//init width array
					var width_array = [];
					
					//5 = [description, manufacturer, part #, mmd, quantity]
					if (group_bom){
						width_array = ['*', 58];
					}
					//5 = [description, manufacturer, part #, mmd, quantity]
					else if (show_nonMMD && hide_price){
						width_array = [160, 85, 120, 45, 58];
					}
					//7 = [description, manufacturer, part #, mmd, quantity, unit price, total price]
					else if(show_nonMMD){
						width_array = [107, 68, 102, 25, 45, 45, 58];
					}
					//4 = [description, manufacturer, part #, quantity]
					else if(hide_price){
						width_array = [160, 130, 130, 58];
					}
					//6 = [description, manufacturer, part #, quantity, unit price, total price]
					else{
						width_array = [142, 68, 102, 45, 45, 58];
					}
					
					//pass width array and type and build table body
					return {
							style: 'table_body',
							dontBreakRows: true,
							table: {
								widths: width_array,
								headerRows: 1,
								body: buildTableBody(type)
							}
						};
				}
			}
			
			else if (type == 'services'){
				
				//if checked, don't show
				if (!hide_services){
				
					return {
						style: 'table_body',
						unbreakable: true,
						table: {
							widths: ['*', 58],
							headerRows: 1,
							body: buildTableBody(type)
						}
					};
				
				}
			}
			
			else if (type == 'total'){
				
				return {
					style: 'table_body',
					unbreakable: true,
					table: {
						widths: ['*', 58],
						headerRows: 1,
						body: buildTableBody(type)
					}
				};
				
			}
			
			else if (type == 'quote_summary'){
				
				return {
					style: 'table_body',
					unbreakable: true,
					table: {
						widths: ['*', 58],
						headerRows: 1,
						body: buildTableBody(type)
					}
				};
				
			}
			else if (type == 'total_summary'){
				
				return {
					style: 'table_body',
					unbreakable: true,
					table: {
						widths: ['*', 58],
						headerRows: 1,
						body: buildTableBody(type)
					}
				};
				
			}
			
			//return nothing if we make it to the end
			return;
			
		}
			
		//temp global to be fixed
		const inst = 1;
		
		//builds table based on type of table
		function buildTableBody(type){
			
			var body = [];
			
			if (type == "bom"){
				
				var priceTotal, //each line total (unit * quantity)
					unitPrice, //used temporarily for discounts when not shown
					bomTotal = 0, //entire bom total
					bomTotal_discount = 0, //entire bom total after discount
					mmdTotal = 0; //holds total for mmd $

				//headers will look different depending on option selected
				if (group_bom){
					//init category array
					var group_category = [];
					
					//pass table headers as array
					var headers = createHeaders([
						"Category",
						"Quantity"
					]);
				}
				else if (show_nonMMD && hide_price){
					//pass table headers as array
					var headers = createHeaders([
						"Description",
						"Manufacturer",
						"Part #",
						"MMD",
						"Quantity"
					]);
				}
				else if (show_nonMMD){
					//pass table headers as array
					var headers = createHeaders([
						"Description",
						"Manufacturer",
						"Part #",
						"MMD",
						"Quantity",
						"Unit Price",
						"Total Price"
					]);
				}
				else if(hide_price){
					//pass table headers as array
					var headers = createHeaders([
						"Description",
						"Manufacturer",
						"Part #",
						"Quantity"
					]);
				}
				else{
					//pass table headers as array
					var headers = createHeaders([
						"Description",
						"Manufacturer",
						"Part #",
						"Quantity",
						"Unit Price",
						"Total Price"
					]);
				}
				
				//add headers to body
				body.push(headers);
				
				//grab material logistics and misc materials (MMD) if they apply
				var material_logistics = grab_key('matL'), 
					misc_mmd = grab_key('miscM');

				//If we have no parts, print out on the first row that there are no parts included in this quote. 
				if (bom_array.length == 0){
					
					if (!show_nonMMD){
						body.push([{text: 'This quote does not include any parts.', colSpan: 6, alignment: 'left', style: 'italics_row'}, {}, {}, {}, {}, {}]);
					}
					else if (hide_price){
						body.push([{text: 'This quote does not include any parts.', colSpan: 4, alignment: 'left', style: 'italics_row'}, {}, {}, {}]);
					}
					else{
						body.push([{text: 'This quote does not include any parts.', colSpan: 7, alignment: 'left', style: 'italics_row'}, {}, {}, {}, {}, {}, {}]);
					}
					
					//flip material logistics decision to false (we have nowhere to bury it)
					hide_matlog = false;
					
				}
				else{
				
					//loop through all BOM parts and build table / add up totals
					for (var i = 0; i < bom_array.length; i++){

						//show part will decide if we want to add this to the BOM
						var show_part = true;

						//set to false if the customer is verizon and show_nonMMD is true
						if ((is_mmd && !show_nonMMD) || bom_array[i].mmd == "Misc")
							show_part = false;
						
						//override of mmd is true
						if (bom_array[i].mmd == "Yes")
							show_part = true;

						//if we just want the category, cycle through parts, group like categories and push to pdf
						if (group_bom){
							//call function that returns array of unique categories and quantities
							//unique_with_quantity(current array, next category, next quantity)
							group_category = unique_with_quantity(group_category, bom_array[i].category, bom_array[i].quantity);
							
						}
						//else cycle through parts like normal
						else if (show_part){

							//init current row
							var dataRow = [];

							//push info to datarow
							dataRow.push(bom_array[i].description);
							dataRow.push(bom_array[i].manufacturer);
							dataRow.push(bom_array[i].partNumber);
							
							//if showing mmd, add mmd column
							if (show_nonMMD){
								if (bom_array[i].mmd == "Yes")
									dataRow.push("Yes");
								else
									dataRow.push("No");
								
							}

							//push part quantity
							dataRow.push(bom_array[i].quantity);
							
							//if we want to hide part price, skip this part
							if (!hide_price){
							
								//apply any discounts, if applicable
								if (m_disc !== 0){

									//check if we want to show materials discount
									if (show_disc && m_disc > 0){
										
										//first grab and add to table as is
										bomTotal += bom_array[i].total_price;

										//add to pdf
										dataRow.push({text: accounting.formatMoney(bom_array[i].price), alignment: 'right'});
										dataRow.push({text: accounting.formatMoney(bom_array[i].total_price), alignment: 'right'});

										//apply discount and add to seperate variable
										bomTotal_discount += bom_array[i].total_price_adjusted;


									}
									//we need to show line by line so quote adds up correctly
									else{
										//find total discounted price
										var discount_price = bom_array[i].total_price_adjusted;
										bomTotal += discount_price;

										//find unit price after discount
										var unitPrice = bom_array[i].price_adjusted;

										//add to pdf
										dataRow.push({text: accounting.formatMoney(unitPrice), alignment: 'right'});
										dataRow.push({text: accounting.formatMoney(discount_price), alignment: 'right'});

									}

								}
								else{									
									//add to total
									bomTotal += bom_array[i].total_price;

									//push rows to table
									dataRow.push({text: accounting.formatMoney(bom_array[i].price), alignment: 'right'});
									dataRow.push({text: accounting.formatMoney(bom_array[i].total_price), alignment: 'right'});
								}
								
								//adjust for mmd totals (if applicable
								if (is_mmd){
									//check if part is mmd
									if (bom_array[i].mmd == "Yes"){
										
										//subtract from BOM total
										bomTotal = bomTotal - bom_array[i].total_price;
										
										//add to mmdTotal
										mmdTotal += bom_array[i].total_price;
										
									}
									
									
								}
						
							}

							//add row to table
							body.push(dataRow);
							
						}

					}
				
					//if this is just categories, loop through categories and add to table
					if (group_bom){
													
						for (var i = 0; i < group_category.length; i++){
							//init current row
							var dataRow = [];
							
							//search for index of 
							var cat_index = full_cat.findIndex(element => element == group_category[i][0]);

							//if we find a match, add that, if not, add current category name
							if (cat_index == -1)
								dataRow.push(group_category[i][0]);
							else
								dataRow.push(full_cat_description[cat_index]);
							
							//push quantity
							dataRow.push(group_category[i][1]);
							
							//add to table
							body.push(dataRow);
						}
						
					}

					//get index of matM
					var matM_index = services_array.findIndex(object => {
						return object.key == "matM";
					});

					//check subcontractor materials. If > 0, add as itemized line
					if (matM_index != -1 && services_array[matM_index].price > 0){
						
						//init current row
						var dataRow = [];

						//push info to datarow
						dataRow.push("Subcontractor Materials");

						//don't add extra lines if only showing categories
						if (!group_bom){
							dataRow.push("");
							dataRow.push("");
						}
						
						//default quantity 1
						dataRow.push("1");

						//don't show if hiding price for parts
						if (!hide_price && !group_bom){
							dataRow.push({text: accounting.formatMoney(services_array[matM_index].price), alignment: 'right'});
							dataRow.push({text: accounting.formatMoney(services_array[matM_index].price), alignment: 'right'});
						}

						body.push(dataRow);
						
						//add to BOM total
						bomTotal += services_array[matM_index].price;
					}

					//init total row
					var total_row; 
				
					//if hiding material pricing, we still need to show total, grab from basic_info.mat_total
					if (hide_price || group_bom){
						//grab bomtotal
						bomTotal = basic_info.mat_total;
						
						//check if we are hiding materials
						if (!hide_matlog)
							bomTotal -= material_logistics;
						
						//adjust for discount
						bomTotal_discount = bomTotal * (1 - m_disc);
						
						//if we are not showing discount, adjust for discount on bomTotal
						if (!show_disc)
							bomTotal = bomTotal_discount;
					}

					//if we want to show discount, add row
					if (show_disc && m_disc > 0){

						//add subtotal line
						//table_row_handler(name, price, type, show_nonMMD, hide_price, group_bom)
						body.push(total_row_handler('PW Provided Materials', bomTotal, 'bold', show_nonMMD, hide_price, group_bom)); 

						//check if we are hiding material logistics
						if (hide_matlog){
							body.push(total_row_handler('PW Provided Materials', basic_info.mat_total, 'bold', show_nonMMD, hide_price, group_bom));
						}
						else{
							body.push(total_row_handler('PW Provided Materials', bomTotal, 'bold', show_nonMMD, hide_price, group_bom));
						}
						
						//if we have logistics, add these to the material section
						if (material_logistics > 0 && !hide_matlog){
							body.push(total_row_handler('Material Logistics', material_logistics, 'bold', show_nonMMD, hide_price, group_bom));
						}

						//subtotal line
						body.push(total_row_handler('Subtotal Materials', material_logistics + bomTotal, 'bold', show_nonMMD, hide_price, group_bom));

						//adjust for logistics
						bomTotal_discount += (material_logistics * (1 - m_disc));

						//make equal to global
						mdisc_total = bomTotal_discount; 

						//use percentage to create discount amount that is listed on BOM
						var mat_discount = m_disc * (bomTotal + material_logistics) * -1;					

						//add Material Discount line
						//table_row_handler(name, price, type, show_nonMMD, hide_price, group_bom)
						body.push(total_row_handler('Material Discount', mat_discount, 'bold', show_nonMMD, hide_price, group_bom));

						//add total materials line
						//table_row_handler(name, price, type, show_nonMMD, hide_price, group_bom)
						body.push(total_row_handler('Total Materials', bomTotal_discount, 'bold', show_nonMMD, hide_price, group_bom));

						//make bom total = to global material total
						bom_sum = bomTotal_discount;
					}
					else{
						//add total materials line
						//table_row_handler(name, price, type, show_nonMMD, hide_price, group_bom)
						if (is_mmd){
							body.push(total_row_handler('MMD Materials', mmdTotal, 'bold', show_nonMMD, hide_price, group_bom));
							
							//if anything is left in bomTotal, list that as well
							if (bomTotal > 0){
								body.push(total_row_handler('PW Provided Materials', bomTotal, 'bold', show_nonMMD, hide_price, group_bom));
								body.push(total_row_handler('Total Materials', bomTotal + mmdTotal, 'bold', show_nonMMD, hide_price, group_bom));
							}
							
							//account for mmdTotal
							bom_sum += mmdTotal;
							
						}
						else{
							//if grouping category, fix bomTotal to mat_total - materal logistics (not being calculated elsewhere)
							if (group_bom)
								bomTotal = basic_info.mat_total - (material_logistics * (1-m_disc));

							//check if we are hiding material logistics
							if (hide_matlog){
								body.push(total_row_handler('PW Provided Materials', basic_info.mat_total, 'bold', show_nonMMD, hide_price, group_bom));
							}
							else{
								body.push(total_row_handler('PW Provided Materials', bomTotal, 'bold', show_nonMMD, hide_price, group_bom));
							}
						}
						
						//if we have logistics & this is not verizon, add these to the material section
						if (material_logistics > 0 && !is_mmd){
							//adjust for discount/mark-up
							material_logistics *= (1 - m_disc);
							
							//check if we are hdiing the logistics
							if (!hide_matlog){
								body.push(total_row_handler('Material Logistics', material_logistics, 'bold', show_nonMMD, hide_price, group_bom));
								body.push(total_row_handler('Total Materials', material_logistics + bomTotal, 'bold', show_nonMMD, hide_price, group_bom));
								
								//add material logistics to global if applies
								bom_sum += material_logistics;
							}

							
						}

						//make bom total = to global material total
						bom_sum += bomTotal;

					}
					
				}

			}
			
			//build services table
			else if (type == "services"){

				//table headers
				var headers = createHeaders([
					"Services",
					"Total Price"
				]);
				
				//add to body
				body.push(headers);
				
				//create variable to hold section totals (and temp array to create objects inside current row)
				var section_totals = [], tempArray = [];
				
				//init counter
				var counter = 0;
				
				//cycle through main categories and set to $0 for section totals
				for (var i = 0; i < main_categories.length; i++){
					//reset tempArray
					tempArray = [];
					
					//loop through each category within a main_cat to see if list seperate is checked
					while (services_array[counter].mainCat == main_categories[i]){
						//if the service line is checked, add to section_totals
						if (services_array[counter].seperate){
							
							//create object
							tempArray = {
								mainCat: services_array[counter].subCat,
								price: 0,
								disc_price: 0
							}

							//push to section totals
							section_totals.push(tempArray);
						}
						
						//increment counter
						counter++;
						
						//check counter to see if we need to break
						if (counter == services_array.length){
							counter--; //reduce back so we don't get error
							break;
						}
					}
					
					//create object
					tempArray = {
						mainCat: main_categories[i],
						price: 0,
						disc_price: 0
					}
					
					//push to section totals
					section_totals.push(tempArray);
					
				}
								
				//loop through services array
				for (var i = 0; i < services_array.length; i++){
					
					//loop through section totals until we find a match
					for (var curr_row = 0; curr_row < section_totals.length; curr_row++){
						
						//first check for sub category match
						if (services_array[i].subCat == section_totals[curr_row].mainCat)
							break;
						
						//then check for match main category
						if (services_array[i].mainCat == section_totals[curr_row].mainCat)
							break;
					}
					
					//if we found a match, lets add it to the section_total
					if (curr_row < section_totals.length){
						
						//if this is MMD Shipping & Overhead, move to install line
						if (services_array[i].key == 'mmdSH')
							curr_row = inst;
							
						//apply discount if applicable
						if (services_array[i].category == "i" && l_disc != 0){

							//we want customer to see discount, implement on the line and add to serviceTotal_discount
							if (show_disc && l_disc > 0){
								
								//first grab current row price, as is
								section_totals[curr_row].price += services_array[i].price;
								
								//then apply discount and add to disc_price
								section_totals[curr_row].disc_price += services_array[i].price - (services_array[i].price * l_disc);

							}
							//we need to apply line by line so quote adds up correctly, apply any discount directly to line for both discount and price
							else{
								//first grab current row price, as is
								section_totals[curr_row].price += services_array[i].price - (services_array[i].price * l_disc);
								
								//then apply discount and add to disc_price
								section_totals[curr_row].disc_price += services_array[i].price - (services_array[i].price * l_disc);
							}

						}
						else{
							//no discount applies to this line, add amount to section total same for both
							section_totals[curr_row].price += services_array[i].price;
							section_totals[curr_row].disc_price += services_array[i].price;
						}							
									
					}
					//if this is materials, it will be treated differently
					else if (services_array[i].mainCat == "Materials"){
						
						//group subcontractor materials with install category
						if (services_array[i].key == 'matM'){
							/*

							IGNORE (MOVED TO BE ITEMIZED IN MATERIALS)

							//for now lets add to install/testing of components - this will be where subcontractor currently is
							var row_price = services_array[i].price;
							if (row_price > 0){
								//adjust for discount (check for type first)
								if (summaryVals[i] == "i")
									row_price *= (1-l_disc)
								else if (summaryVals[i] == "m")
									row_price *= (1-m_disc)

								//add to install section total
								section_totals[inst].price += row_price;
								section_totals[inst].disc_price += row_price;
							}
							*/
						}
						//only look at material rows not mmd or subcontractor
						else if (services_array[i].key != "mmdP" && services_array[i].key != "mmdA" && services_array[i].key != "matM"){
							if (is_mmd && !show_nonMMD){
								var row_price = services_array[i].price;
								if (row_price > 0){
									//adjust for discount (check for type first)
									if (summaryVals[i] == "i")
										row_price *= (1-l_disc)
									else if (summaryVals[i] == "m")
										row_price *= (1-m_disc)
									
									//add to misc section total
									//add to install if matL
									if (services_array[i].key == 'matL'){
										section_totals[inst].price += row_price;
										section_totals[inst].disc_price += row_price;
									}
									else{
										section_totals[inst].price += row_price;
										section_totals[inst].disc_price += row_price;
									}
									
								}
								
							}
							//still need to add logistics AND misc materials
							else if ((services_array[i].key == 'matL' && is_mmd) || (services_array[i].key == 'miscM' && is_mmd)){
								var row_price = services_array[i].price;
								if (row_price > 0){
									//adjust for discount (check for type first)
									if (summaryVals[i] == "i")
										row_price *= (1-l_disc)
									else if (summaryVals[i] == "m")
										row_price *= (1-m_disc)
									
									//add to misc section total
									section_totals[inst].price += row_price;
									section_totals[inst].disc_price += row_price;
								}
							}
						}
						
					}
					//else will be discounts
					else{
						//do nothing for discount sections
					}
										
				}
				
				//loop through section totals, if greater than 0, add to quote
				for (var i = 0; i < section_totals.length; i++){
					
					//check section total
					if (section_totals[i].price > 0){
						
						//initialize data row
						var dataRow = [];
					
						//add to section totals
						services_sum += section_totals[i].price;
						services_sum_disc += section_totals[i].disc_price;

						//create data row for table
						dataRow = [{text: section_totals[i].mainCat}, {text: accounting.formatMoney(section_totals[i].price), alignment: 'right'}];	

						//push row to table
						body.push(dataRow);
					}
					
				}
				
				//if we do not have any services, add disclaimer that this quote does not include any services
				if (services_sum == 0){
					body.push([{text: 'This quote does not include any services', colSpan: 2, alignment: 'left', style: 'italics_row'}, {}]);
				}
				else{
					
					//if we want to show discount, add row
					if (show_disc && l_disc > 0){

						//set subtotal
						total_row = [{text: 'Service Subtotal', alignment: 'right', style: 'total_row'}, {text: accounting.formatMoney(services_sum), alignment: 'right', style: 'total_row'}];
						body.push(total_row);

						//create discount string
						//only use service that applies (iServiceTotal)
						var labor_discount = services_sum_disc - services_sum;

						var discount_row = [{text: 'Service Discount', alignment: 'right', style: 'total_row'}, {text: accounting.formatMoney(labor_discount), alignment: 'right', style: 'total_row'}];
						body.push(discount_row);

						discount_row = [{text: 'Total Services', alignment: 'right', style: 'total_row'}, {text: accounting.formatMoney(services_sum_disc), alignment: 'right', style: 'total_row'}];
						body.push(discount_row);
						
						//reset services_sum to the discounted amount
						services_sum = services_sum_disc;

					}
					//just show total services number
					else{
						total_row = [{text: 'Total Services', alignment: 'right', style: 'total_row'}, {text: accounting.formatMoney(services_sum), alignment: 'right', style: 'total_row'}];
						body.push(total_row);
					}

				}
			}
			
			//build totals table
			else if (type == "total"){
				
				//if so, set globals to FST summary values
				if (hide_mats){
					
					//set to summary total
					bom_sum = basic_info.mat_total;
					
					//if customer is verizon, grab mmd price manually
					if (is_mmd){
						bom_sum = accounting.unformat(u.eid("price_mmdP").value) + accounting.unformat(u.eid("price_mmdA").value);
						
						//adjust for discount
						bom_sum *= (1 - m_disc);
					}
					
					
				}
				if (hide_services){
					
					//set to summary total
					services_sum = basic_info.labor_total;
					
					//if customer is verizon, subtract m_total from total project cost
					if (is_mmd)
						services_sum = (basic_info.labor_total + basic_info.mat_total) - bom_sum;
					
				}
				
				//if hiding logistics, set bom_sum = mat total
				if (hide_matlog)
					bom_sum = basic_info.mat_total;
			
				//set header rows of summary table	
				var header_row = [{text: 'Summary', style: 'table_header'}, {text: 'Total Price', style: 'table_header'}];
				
				//add header row to table
				body.push(header_row);
				
				//initiaze taxes
				var taxes = basic_info.taxes;
				var total_pc; //will hold total project cost
				
				//clear data row
				var dataRow = [];
				
				//total materials
				dataRow = [{text: 'Total Materials'}, {text: accounting.formatMoney(bom_sum), alignment: 'right'}];
				body.push(dataRow);
				
				//total services
				dataRow = [{text: 'Total Services'}, {text: accounting.formatMoney(services_sum), alignment: 'right'}];
				body.push(dataRow);

				//add services and materials for total project cost so far
				total_pc = services_sum + bom_sum;
				
				//check if taxes is greater than 0 (only add if this applies)
				if (taxes > 0){
					//total sub-total
					dataRow = [{text: 'Subtotal'}, {text: accounting.formatMoney(total_pc), alignment: 'right'}];
					body.push(dataRow);

					//adjust for taxes
					total_pc *= (1+taxes);

					var tax_full = (taxes * 100).toFixed(2);
					var tax_string = tax_full + '%';

					taxes = (services_sum + bom_sum) * (taxes);

					//add taxes
					dataRow = [{text: 'Taxes (' + tax_string + ')'}, {text: accounting.formatMoney(taxes), alignment: 'right'}];
					body.push(dataRow);
				}
				
				//total project cost
				dataRow = [{text: 'Total Project Cost', style: 'total_row', alignment: 'right'}, {text: accounting.formatMoney(total_pc), alignment: 'right', style: 'total_row'}];
				body.push(dataRow);
			}
			
			//builds individual quote table summary section
			else if (type == "quote_summary"){
			
				//set header rows of summary table	
				var header_row = [{text: 'Quote Summary', style: 'table_header'}, {text: 'Total Price', style: 'table_header'}];
				
				body.push(header_row);
				
				//initialize total project cost variable, and summary variable
				var project_total = 0, project_summary;
								
				//loop through quotes and add seperate line for each quote
				for (var i = 0; i < project_info[0].length; i++){
					
					//add to project_total
					project_total+= parseFloat(project_info[0][i]['totalPrice']);

					//initalize dataRow to be used
					var dataRow = [];

					//set taxes = 0(to be updated)
					taxes = 0;
					
					//only add tax line and subtotal if tax > 0
					if (taxes > 0){
						//to be added if/when we use taxes
						
					}
					//no taxes apply, lets list the total out for each quote
					else{

						//update project summary
						project_summary = "Quote " + project_info[0][i].quoteNumber + " - " + project_info[0][i].phaseName;
						
						//total materials
						dataRow = [{text: project_summary}, {text: accounting.formatMoney(parseFloat(project_info[0][i]['totalPrice'])), alignment: 'right'}];
						body.push(dataRow);
					}
				}
				
				//total project cost
				dataRow = [{text: 'Total Project Cost', style: 'total_row', alignment: 'right'}, {text: accounting.formatMoney(project_total), alignment: 'right', style: 'total_row'}];
				body.push(dataRow);
			}
			//builds summary table
			else if (type == "total_summary"){
			
				//initialize total variables
				var mat_total = 0,
					labor_total = 0;
								
				//loop through quotes and 
				for (var i = 0; i < project_info[0].length; i++){
					//add to material and labor totals
					mat_total+= parseFloat(project_info[0][i]['mPrice']);
					labor_total+= parseFloat(project_info[0][i]['sPrice']);
				}
				
				//set header rows of summary table	
				var header_row = [{text: 'Total Summary', style: 'table_header'}, {text: 'Total Price', style: 'table_header'}];
				
				body.push(header_row);
				
				var taxes = parseFloat(basic_info.taxes); //will be pulled from document
				var total_pc; //will hold total project cost
				
				var dataRow = [];
				
				//total materials
				dataRow = [{text: 'Total Materials'}, {text: accounting.formatMoney(mat_total), alignment: 'right'}];
				body.push(dataRow);
				
				//total services
				dataRow = [{text: 'Total Services'}, {text: accounting.formatMoney(labor_total), alignment: 'right'}];
				body.push(dataRow);
				
				//set total_pc
				total_pc = mat_total + labor_total
				
				//only add tax line and subtotal if tax > 0
				if (taxes > 0){
				
					//total sub-total
					dataRow = [{text: 'Subtotal'}, {text: accounting.formatMoney(total_pc), alignment: 'right'}];
					body.push(dataRow);

					//format taxes
					total_pc = (mat_total + labor_total) * (1+taxes);

					var tax_full = (taxes * 100).toFixed(2);
					var tax_string = tax_full + '%';

					taxes = (mat_total + labor_total) * (taxes);

					//add taxes
					dataRow = [{text: 'Taxes (' + tax_string + ')'}, {text: accounting.formatMoney(taxes), alignment: 'right'}];
					body.push(dataRow);
				}
				
				//total project cost
				dataRow = [{text: 'Total Project Cost', style: 'total_row', alignment: 'right'}, {text: accounting.formatMoney(total_pc), alignment: 'right', style: 'total_row'}];
				body.push(dataRow);
			}
			
			return body;
			
		}
		
		
		/*	//builds individual quote table summary section
			else if (type == "quote_summary"){
			
				//set header rows of summary table	
				var header_row = [{text: 'Quote Summary', style: 'table_header'}, {text: 'Total Price', style: 'table_header'}];
				
				body.push(header_row);
				
				//initialize total project cost variable, and summary variable
				var project_total = 0, project_summary;
								
				//loop through quotes and add seperate line for each quote
				for (var i = 0; i < project_info[0].length; i++){
					
					//add to project_total
					project_total+= parseFloat(project_info[0][i]['totalPrice']);

					//initalize dataRow to be used
					var dataRow = [];

					//set taxes = 0(to be updated)
					taxes = 0;
					
					//only add tax line and subtotal if tax > 0
					if (taxes > 0){
						//to be added if/when we use taxes
						
					}
					//no taxes apply, lets list the total out for each quote
					else{

						//update project summary
						project_summary = "Quote " + project_info[0][i].quoteNumber + " - " + project_info[0][i].phaseName;
						
						//total materials
						dataRow = [{text: project_summary}, {text: accounting.formatMoney(parseFloat(project_info[0][i]['totalPrice'])), alignment: 'right'}];
						body.push(dataRow);
					}
				}
				
				//total project cost
				dataRow = [{text: 'Total Project Cost', style: 'total_row', alignment: 'right'}, {text: accounting.formatMoney(project_total), alignment: 'right', style: 'total_row'}];
				body.push(dataRow);
			}
			//builds summary table
			else if (type == "total_summary"){
			
				//initialize total variables
				var mat_total = 0,
					labor_total = 0;
								
				//loop through quotes and 
				for (var i = 0; i < project_info[0].length; i++){
					//add to material and labor totals
					mat_total+= parseFloat(project_info[0][i]['mPrice']);
					labor_total+= parseFloat(project_info[0][i]['sPrice']);
				}
				
				//set header rows of summary table	
				var header_row = [{text: 'Total Summary', style: 'table_header'}, {text: 'Total Price', style: 'table_header'}];
				
				body.push(header_row);
				
				var taxes = parseFloat(basic_info.taxes); //will be pulled from document
				var total_pc; //will hold total project cost
				
				var dataRow = [];
				
				//total materials
				dataRow = [{text: 'Total Materials'}, {text: accounting.formatMoney(mat_total), alignment: 'right'}];
				body.push(dataRow);
				
				//total services
				dataRow = [{text: 'Total Services'}, {text: accounting.formatMoney(labor_total), alignment: 'right'}];
				body.push(dataRow);
				
				//set total_pc
				total_pc = mat_total + labor_total
				
				//only add tax line and subtotal if tax > 0
				if (taxes > 0){
				
					//total sub-total
					dataRow = [{text: 'Subtotal'}, {text: accounting.formatMoney(total_pc), alignment: 'right'}];
					body.push(dataRow);

					//format taxes
					total_pc = (mat_total + labor_total) * (1+taxes);

					var tax_full = (taxes * 100).toFixed(2);
					var tax_string = tax_full + '%';

					taxes = (mat_total + labor_total) * (taxes);

					//add taxes
					dataRow = [{text: 'Taxes (' + tax_string + ')'}, {text: accounting.formatMoney(taxes), alignment: 'right'}];
					body.push(dataRow);
				}
				
				//total project cost
				dataRow = [{text: 'Total Project Cost', style: 'total_row', alignment: 'right'}, {text: accounting.formatMoney(total_pc), alignment: 'right', style: 'total_row'}];
				body.push(dataRow);
			}
			
			return body;
			
		}
		*/
		
		//handles finding key price in the services array (if it exists)
		//key that is passed can be found in the fst_body array (this is used for miscM and matL currently)
		function grab_key(key){
			//loop through services array
			for (var i = 0; i < services_array.length; i++){
				//check key
				if (services_array[i].key == key)
					return services_array[i].price
				
			}
			
			//if we haven't returned anything yet, return 0;
			return 0;
			
		}
		
		//handle creating total rows (in one place for easier updates)
		//row_name = the name of the row (left side)
		//price = total price of row
		//type = type of row (currently only takes bold)
		//cust_pref = passes value if another column needs to be added
		function total_row_handler(row_name, price, type, cust_pref = false, hide_price = false, group_bom = false){
			
			var row_style = null; 
			
			if (type = "bold"){
				row_style = "total_row";
			}
			
			//look at cust_pref and hide_price bools and decide how many columns need to be added
			if (group_bom){
				return [{text: row_name, alignment: 'right', style: row_style}, {text: accounting.formatMoney(price),  alignment: 'right', style: 'total_row'}];
			}
			else if (cust_pref && hide_price){
				return [{text: row_name, colSpan: 4, alignment: 'right', style: row_style}, {}, {}, {}, {text: accounting.formatMoney(price),  alignment: 'right', style: 'total_row'}];
			}
			else if (cust_pref){
				return [{text: row_name, colSpan: 6, alignment: 'right', style: row_style}, {}, {}, {}, {}, {}, {text: accounting.formatMoney(price),  alignment: 'right', style: 'total_row'}];
			}
			else if (hide_price){
				return [{text: row_name, colSpan: 3, alignment: 'right', style: row_style}, {}, {}, {text: accounting.formatMoney(price),  alignment: 'right', style: 'total_row'}];
			}
			else{
				return [{text: row_name, colSpan: 5, alignment: 'right', style: row_style}, {}, {}, {}, {}, {text: accounting.formatMoney(price),  alignment: 'right', style: 'total_row'}];
			}
			
		}
		
		//create table headers
		function createHeaders(keys) {
			var result = [];
			for (var i = 0; i < keys.length; i += 1) {
				result.push({
					text: keys[i],
					style: 'table_header'
					//prompt: keys[i],
					//width: size[i],
					//align: "center",
					//padding: 0
				});
			}
			return result;
		}
		
		//used to open page 2 of work orders dialog
		$('#workOrdersPage2').on('click', function() {
			var screenheight = $(window).height();
			var desc = 0;
			
			$(".requiredPR").each(function(){
				
				this.classList.remove("required_error");
				
				// Test if the div element is empty
				if (!$(this).val()){
					this.classList.add("required_error");
					desc = 1;
				}
			});
			
			if (desc == 0){
			
				$( "#workOrders-dialog-page1" ).dialog('close');

				$( "#workOrders-dialog-page2" ).dialog({
					width: "auto",
					height: screenheight - 50,
					dialogClass: "fixedDialog",
				});
			}
		});
				
		
		//handles changing tabs
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
		
		//windows onload
		window.onload = function () {
			u.eid("defaultOpen").click();
						
			//if cq_quote is non-empty, skip first steps and load in previous quotes
			if (cq_quote != "")
				z.next('Existing');
			
		}
		
		$(document).ajaxStart(function () {
			waiting('on');
		});

		$(document).ajaxStop(function () {
			waiting('off');
			if (combining){
				z.next('Created');
			}

		});
	

	</script>
	
</body>
	
<?php

//reset return address once the page has loaded
unset($_SESSION['returnAddress']);

//close SQL connection
$mysqli -> close();

?>
	
</html>