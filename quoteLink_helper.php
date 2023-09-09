<?php

//init session variables
session_start();

// Load the database configuration file
require_once 'config.php';

//include php functions
include('phpFunctions.php');

//Handles searching a reading back services, travel info, labor rates, etc for another quote
if ($_POST['tell'] == "get_info"){
	
	//decode list of quotes
	$quotes = json_decode($_POST['quote_list']);
	
	//initialize arrays to send back
	//project info
	$basic_info = [];
	
	//main services
	$services = [];

	//materials
	$materials = [];
	
	//discounts
	$discounts = [];
	
	//clarifications
	$all_project_clarifications = [];
	$all_general_clarifications = [];
	
	//contract num and version
	$contract_ref = "";
	$current_version = "";
	
	//loop through all quotes and grab relevant information
	for ($i = 0; $i < sizeof($quotes); $i++){
	
		//Step 1: Basic Info
		
		//reset temp array
		$temp_array = [];

		//run query to grab any basic info
		$query = "select * from fst_grid where quoteNumber = '" . $quotes[$i]. "' LIMIT 1;";
		$result = mysqli_query($con, $query);

		//grab array of values
		$basic = mysqli_fetch_array($result);
		
		//push to basic info array
		array_push($basic_info, $basic);
		
		//Step 2: Services
		
		//reset temp services
		$temp_services = [];

		//run query to grab all service lines
		$query = "select a.key, a.mainCat, a.subCat, a.price, a.role, a.list_seperate, b.category, b.id from fst_values a, fst_body b where quoteNumber = '" . $quotes[$i] . "' AND a.key = b.keyName ORDER BY b.id;";
		$result = mysqli_query($con, $query);
		
		//check to see if we returned anything
		if (mysqli_num_rows($result) > 0) {

			//loop and add to arrays
			while($rows = mysqli_fetch_assoc($result)){
				
				//check to see if price is > 0 (only applies for prices greater than 0)
				if ($rows['price'] > 0){
				
					//clear temp_array
					$temp_array = [];
						
					//set temp services
					$temp_array = array('key'=>$rows['key'], 
									   'mainCat'=>$rows['mainCat'],
									   'subCat'=>$rows['subCat'],
									   'price'=>$rows['price'], 
									   'category'=>$rows['category'], 
									   'role'=>$rows['role'],
									   'seperate'=>$rows['list_seperate']
									   ); 

					//push to services array
					array_push($temp_services, $temp_array);
					
				}
			}   
		}
		
		//push to main array
		array_push($services, $temp_services);
		
		
		//Step 3: Materials
		
		//reset temp array
		$temp_array = [];
		
		//run query to grab all service lines
		$query = "select description, manufacturer, partNumber, quantity, price, partCategory, mmd, matL from fst_boms where quoteNumber = '" . $quotes[$i] . "' AND type = 'P' GROUP BY id ORDER BY mmd desc, partCategory;";
		$result = mysqli_query($con, $query);
		
		//check to see if we returned anything
		if (mysqli_num_rows($result) > 0) {

			//loop and add to arrays
			while($rows = mysqli_fetch_assoc($result)){
				//clear temp_services
				$temp_materials = [];

				//create array for row of services
				$temp_materials = array('partNumber'=>$rows['partNumber'], 
									   'description'=>$rows['description'], 
									   'manufacturer'=>$rows['manufacturer'], 
									   'quantity'=>$rows['quantity'], 
									   'price'=>$rows['price'],
									   'category'=>$rows['partCategory'], 
									   'mmd'=>$rows['mmd'], 
									   'matL'=>$rows['matL']
									   );

				//push to temp array
				array_push($temp_array, $temp_materials);
			}                                                                                                                                                                                                                                                                  
		}
		
		//push to main array 
		array_push($materials, $temp_array);
		
		//Step 4: Discounts (labor and material)
		
		//reset temp array
		$temp_array = [];
		
		//run query to grab any discounts that apply
		//init both to 0
		$labor_disc = 0;
		$mat_disc = 0;
		
		//labor
		$query = "select markupPerc from fst_values where quoteNumber = '" . $quotes[$i] . "' AND fst_values.key = 'laborD';";
		$result = mysqli_query($con, $query);
		
		//check to see if we returned anything
		if (mysqli_num_rows($result) > 0) {
			//grab array of values
			$discount = mysqli_fetch_array($result);
			$labor_disc = $discount['markupPerc'];
		}
		
		//material
		$query = "select markupPerc from fst_values where quoteNumber = '" . $quotes[$i] . "' AND fst_values.key = 'matD';";
		$result = mysqli_query($con, $query);
		
		//check to see if we returned anything
		if (mysqli_num_rows($result) > 0) {
			//grab array of values
			$discount = mysqli_fetch_array($result);
			$mat_disc = $discount['markupPerc'];
		}
		
		//add to temp_array
		$temp_array = array('labor_disc'=>$labor_disc, 
						   'mat_disc'=>$mat_disc
						   );

		//push to services array
		array_push($discounts, $temp_array);
		
		
		//Step 5: Clarifications
		
		
		//initalize different clarifications arrays
		$all_clarifications = [];
		$all_clarifications_id = [];

		//arrays passed to actual clar section
		$project_clarifications = [];
		$type_clarifications = [];
		$general_clarifications = [];

		//query all saved clarifications
		//this will be used to help decide what a clar is based on the index
		$query = "select * from fst_clarifications;";
		$result = mysqli_query($con, $query);

		while($rows = mysqli_fetch_assoc($result)){
			array_push($all_clarifications, $rows['clarification']);
			array_push($all_clarifications_id, $rows['id']);
		}

		//query any saved clarifications and save to arrays
		$query = "SELECT type, IF(clar_id is null, concat('A|', clar_full), concat('B|', clar_id)) as clar from fst_quote_clarifications where quoteNumber = '" . $quotes[$i] . "';";
		$result = mysqli_query($con, $query);

		while($rows = mysqli_fetch_assoc($result)){

			//use type and first letter to decide what to do with each clarification
			$first_letter = substr($rows['clar'], 0, 1);
			$clar_string = substr($rows['clar'], 2, strlen($rows['clar']));

			if ($first_letter == "A"){
				//do nothing, we have the clar string
			}
			//if B is the first letter, this is a tell that we have the clar_id, find index and save clar
			elseif ($first_letter == "B"){
				$index = search_array($all_clarifications_id, intval($clar_string));
				$clar_string = $all_clarifications[$index];
			}

			//depending on type, add to correct array
			if ($rows['type'] == 1){
				array_push($project_clarifications, $clar_string);
			}
			elseif($rows['type'] == 2){
				array_push($type_clarifications, $clar_string);
			}
			elseif($rows['type'] == 3){
				//ignore, we'll collect all general together
			}
		}

		//check to see which clarifications were added, if none, lets use the defaults
		//if no project type exists, grab saved for given types
		if(sizeof($type_clarifications) == 0){
			//if project type exists, look for clarifications
			if ($basic['projectType'] != ""){
				$query = 'select a.clarification from fst_clarifications a, fst_clarifications_init b where a.id = b.clar_id and b.type = "All (' . $basic['projectType'] . ')"';
				$result = mysqli_query($con, $query);

				while($rows = mysqli_fetch_assoc($result)){
					array_push($type_clarifications, $rows['clarification']);
				}
			}

			//if subtype exists, look for clarifications
			if ($basic['subType'] != ""){
				$query = 'select a.clarification from fst_clarifications a, fst_clarifications_init b where a.id = b.clar_id and b.type = "' . $basic['subType'] . '"';
				$result = mysqli_query($con, $query);

				while($rows = mysqli_fetch_assoc($result)){
					array_push($type_clarifications, $rows['clarification']);
				}
			}
		}
		
		//loop through type and add to project
		for ($j = 0; $j < sizeof($type_clarifications); $j++){
			array_push($project_clarifications, $type_clarifications[$j]);
		}
		
		//if no general clars have been saved, grab saved generals
		if (sizeof($general_clarifications) == 0){
			$query = 'SELECT a.clarification FROM fst_clarifications a, fst_clarifications_init b WHERE a.id = b.clar_id AND b.type = "general"';
			$result = mysqli_query($con, $query);

			while($rows = mysqli_fetch_assoc($result)){
				array_push($general_clarifications, $rows['clarification']);
			}
		}
		
		//push to all_project_specific and all_general
		array_push($all_project_clarifications, $project_clarifications);
		array_push($all_general_clarifications, $general_clarifications);
		
		
		//Step 6: If this is the first quote we are grabbing, lets use the contract # to create a unique ID for the project
		//also check 'create_new' boolean - tells if we are going to create a new entry or just refresh and existing
		if ($i == 0 && $_POST['create_new'] == 'true'){
			
			//query the existing number to get the most up to date version 
			$query = "SELECT * FROM fst_grid WHERE quoteNumber like '" . $basic['vpContractNumber'] . "CQ%' ORDER BY quoteNumber desc LIMIT 1;";
			$result = mysqli_query($con, $query);
		
			//check to see if we returned anything
			if (mysqli_num_rows($result) > 0) {
				$last = mysqli_fetch_array($result);
				$last_v = substr($last['quoteNumber'], -1);
				$current_version = intval($last_v) + 1;
			}
			else{
				$current_version = 1;
			}
			
			//set contract reference number to be used the rest of the way
			$contract_ref = $basic['vpContractNumber'];
			
			//insert new entry to fst_grid
			//$query = "INSERT INTO fst_grid version, quoteNumber, location_name, phaseName, customer, qMaterials, qServices, totalPrice, sow, lastUpdate, draft, sub_date, exp_date VALUE (''); ";
			$query = "INSERT INTO fst_grid (quoteNumber, location_name, phaseName, customer, sow, lastUpdate, site_pin) 
									VALUES ('" . $contract_ref . "CQv" . $current_version . "', 'Combined Quote','" . $_POST['description'] . "', '" . $_POST['customer'] . "', '" . $_POST['sow'] . "', NOW(), '" . rand(100000, 999999) . "'); ";

			//call custom_query (executes query (2nd parameter) returns false if successful (true if error is returned) - in phpFunctions.php)
			//outputs error message if error occurs & sends to fst@pw.com for troubleshooting
			if(custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__))
				return;
			
			//turn current version to string
			$current_version = strval($current_version);
			
			//adjust version to new verbage
			if (strlen($current_version) == 1)
				$current_version = "00" . $current_version;
			elseif(strlen($current_version) == 2)
				$current_version = "0" . $current_version;
			
		}
		
		//if the variable holding create new is true, then create a new entry for this group of quotes
		if ($_POST['create_new'] == 'true'){
			//Step 7: Using the unique ID lets save it to fst_grid_link so we can pull this info in the future.
			$query = "INSERT INTO fst_grid_linked (combinedQ, quoteNumber) VALUES ('" . $contract_ref . "CQ-" . $current_version . "', '" . $basic['quoteNumber'] . "');";

			//call custom_query (executes query (2nd parameter) returns false if successful (true if error is returned) - in phpFunctions.php)
			//outputs error message if error occurs & sends to fst@pw.com for troubleshooting
			if(custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__))
				return;
		}
		
		
	}
	
	//return list of arrays to client side
	echo json_encode(array($basic_info, $services, $materials, $discounts, $all_project_clarifications, $all_general_clarifications, $contract_ref . "CQ-" . $current_version));

	return;
	
}

//handles saving values to database
if ($_POST['tell'] == "save"){
	
	//build query & save
	$query = "UPDATE fst_grid SET phaseName = '" . mysql_escape_mimic($_POST['description']) . "', sow = '" . mysql_escape_mimic($_POST['sow']) . "', draft = '" . mysql_escape_mimic($_POST['draft']) . "', sub_date = '" . mysql_escape_mimic($_POST['sub_date']) . "', exp_date = '" . mysql_escape_mimic($_POST['exp_date']) . "', lastUpdate = NOW() WHERE quoteNumber = '" . $_POST['cq_quote_number'] . "';";
	custom_query($con, $query, $_SERVER['REQUEST_URI'], __LINE__);
	
	return;
	
}





