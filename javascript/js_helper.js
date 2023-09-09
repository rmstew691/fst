// JavaScript Document

//formats date from date picker
//param 1 = DATETIME data from SQL (formatted like 2022-09-06 03:54:45)
//returns date like (09/06/2022)
function format_date(old_date){
	
	//if date is null return nothing
	if (old_date == "" || old_date === null)
		return "";

	//grab year
	var year = old_date.substring(0, 4);
	
	//grab month
	var month = old_date.substring(5, 7);
	
	//grab day
	var day = old_date.substring(8, 10);
	
	//return formatted date
	return month + "/" + day + "/" + year;
	
}

//convert utc to local
function utc_to_local(date){

	//check for blank times
	if (date == "0000-00-00 00:00:00")
		return "";

	var date_local = new Date(date + ' UTC');

	//date & time, convert to central time zone
	var y = date_local.getFullYear(),
		m = date_local.getMonth() + 1,
		d = date_local.getDate(),
		hours = date_local.getHours(),
		minutes = date_local.getMinutes();

	var time = time_format(hours, minutes);

	return m + "-" + d + "-" + y + " at " + time;
}

//changes military time to standard
function time_format(hours, minutes){

	//init time to be returned
	var timeValue;

	//use hours to check if this needs to be am or pm
	if (hours > 0 && hours <= 12) {
		timeValue= "" + hours;
	} 
	else if (hours > 12) {
		timeValue= "" + (hours - 12);
	} 
	else if (hours == 0) {
		timeValue= "12";
	}

	timeValue += (minutes < 10) ? ":0" + minutes : ":" + minutes;  // get minutes
	timeValue += (hours >= 12) ? " PM" : " AM";  // get AM/PM

	// return value
	return timeValue;
}

//checks closed date to see if it was closed today
//ref: https://stackoverflow.com/questions/8215556/how-to-check-if-input-date-is-equal-to-todays-date
function check_closed_today(check_date){

	//convert datetime UTC to local
	var today = new Date();
	var closed_date = new Date(check_date + ' UTC');

	//set hours, mins, secs, millasecs to 0 (should format dates to look the same)
	if (today.setHours(0, 0, 0, 0) == closed_date.setHours(0,0,0,0))
		return true;
	else
		return false;

}

/**@author Alex Borchers
 * Handles opening dialog with custom settings
 * @param {string} id (ID of the dialog you would like to open)
 * @param {string} left (optional) (ID of element you want dialog to open to the left of)
 * @param {string} right (optional) (ID of element you want dialog to open to the left of)
 */
function open_dialog(id, left = null, right = null){

	//get screen height
	var screenHeight = $(window).height();
	var screenWidth = $(window).width();

	// check if left OR right is set
	if (left !== null){
		$( "#" + id ).dialog({
			width: "auto",
			height: "auto",
			dialogClass: "fixedDialog",
			maxHeight: screenHeight - 50
		});

		$( "#" + id ).dialog("widget").position({
			my: 'left',
			at: 'right',
			of: $( "#" + left )
		});
	}
	else if (right !== null){
		$( "#" + id ).dialog({
			width: "auto",
			height: "auto",
			dialogClass: "fixedDialog",
			maxHeight: screenHeight - 50
		});

		$( "#" + id ).dialog("widget").position({
			my: 'right',
			at: 'left',
			of: $( "#" + right )
		});
	}
	else{
		$( "#" + id ).dialog({
			dialogClass: "fixedDialog",
			maxHeight: screenHeight - 50,
			width: "auto",
			height: "auto"
		});
	}

	// Adjust height of dialog if it exceeds the screen width
	var width = $('#' + id).outerWidth();
	
	if (width > screenWidth - 25){
		close_dialog(id);
		$( "#" + id ).dialog({
			dialogClass: "fixedDialog",
			maxHeight: screenHeight - 50,
			height: "auto",
			width: screenWidth - 25
		});
	}
}

/**@author Alex Borchers
 * Handles closing dialog box
 * @param {string} id (ID of the dialog you would like to close)
 */
function close_dialog(id){
	$( "#" + id ).dialog('close');
}

//takes in unique alphabetically sorted array, name, and quantity, and returns alphabetically sorted array with name and quanitty added
function unique_with_quantity(current_array, category, quantity){

	//init temp array that will be used to add values to current_array
	var temp_array = [];

	//if the array is blank, add category and quantity and return
	if (current_array.length == 0){
		temp_array.push(category);
		temp_array.push(quantity); 

		current_array.push(temp_array);
		return current_array;
	}

	//holds new array (we'll add as we go since this is already sorted)
	var new_array = [];
	var stop = false; //flip to true once we pass where this would fit in

	//else, cycle through existing array. If we find a match, add quantity and return. If not, add to array, and return
	for (var i = 0; i < current_array.length; i++){

		//if stop, just continue to add to new array
		if (stop){
			new_array.push(current_array[i]);
		}
		else{

			//check category
			if (current_array[i][0] == category){
				current_array[i][1]+= quantity;
				return current_array;
			}
			else{
				if (current_array[i][0] > category){
					temp_array.push(category);
					temp_array.push(quantity);
					new_array.push(temp_array);
					new_array.push(current_array[i]);
					stop = true;

				}
				else{
					new_array.push(current_array[i]);
				}


			}
		}

	}

	//check stop, if it is still = false, these we need to add the most recent value to the new array
	if (!stop){
		temp_array.push(category);
		temp_array.push(quantity);
		new_array.push(temp_array);
	}

	//return new array if we get through all categories without a match
	return new_array;
}

//checks to make sure all required fields are not blank
//param 1 = class object (u.class("example"))
function check_submit(class_name){

	//init bool to decide if we pass
	var error = false;

	//jquery to loop through a class
	for (var i = 0; i < class_name.length; i++){
		
		//if blank, highlight yellow and set pass to false
		if (class_name[i].value.trim() == "" || class_name[i].value == null){
			class_name[i].classList.add("required_error");
			error = true;
		}
		//otherwise set background to standard color
		else{
			class_name[i].classList.remove("required_error");
		}
		
	}
	
	//return pass value
	return error;
	
}

//function used to get the number of allocated parts
//param 1 = part # we are looking for
//param 2 = (array) shops we are looking for
//param 3 = pq_detail object (must contain part_id, pq_overview_status, status)
function get_allocated(partNumber, shops, pq_detail){

	//init array and set to 0 for all shops requested
	var shop_allocated = [];
	for (var i = 0; i < shops.length; i++){
		shop_allocated[i] = 0;
	}

	//loop through pq_detail and add to totals
	for (var i = 0; i < pq_detail.length; i++){

		//get shop index
		var shop_index = shops.indexOf(pq_detail[i].decision);

		//search for an allocated match in general
		if (pq_detail[i].status == "Pending" && pq_detail[i].part_id.toLowerCase() == partNumber.toLowerCase() && shop_index > -1){
			shop_allocated[shop_index] += parseInt(pq_detail[i].q_allocated);
		}
	}

	var obj = {};

	shops.forEach((shop, index) => {
		obj[shop] = shop_allocated[index];
	});

	return obj;

}

/**
 * used to get reels related to a part
 * 
 * @param {int} id (id from fst_pq_detail db table)
 * @param {Array[{object}]} reel_assignments (database table inv_reel_requests)
 * 
 * @returns reel_string (comma seperated string of all reel matches)
 */
function get_reel_string(id, reel_assignments){

	//remove all NULL pq_detail IDs
	var reels = reel_assignments.filter(function (reel) {
		return reel.pq_detail_id != null;
	});

	//filter all reels related to id
	var reel_list = reels.filter(function (reel) {
		return reel.pq_detail_id == id || reel.pq_detail_id.includes("|" + id + "(");
	});

	//if length 0, return empty string
	if (reel_list.length == 0)
		return "";

	//otherwise loop and create string
	var reel_string = "";

	for (var i = 0; i < reel_list.length; i++){

		//if this is a bulk reel, preface with BR
		if (parseInt(reel_list[i].bulk) == 1)
			reel_string += "BR";

		//treat last row differently
		if (i == reel_list.length - 1)
			reel_string += reel_list[i].reel_id;
		else
			reel_string += reel_list[i].reel_id + ", ";

	}

	return reel_string;
}

/**
 * 
 * @param {*} inventory 
 * @param {*} table_id 
 * @param {*} category 
 * @param {*} manufacturer 
 * @param {*} part 
 * @param {*} class_attributes 
 * @param {*} target_rows 
 * @param {*} url 
 */
function filter_catalog(inventory, table_id, category, manufacturer, part, class_attributes, url){

	//hold bold desc
	var hot_tell = "", cutsheet_front = "", cutsheet_back = "", status_tell = "";
			
	// get table
	var table = u.eid(table_id).getElementsByTagName('tbody')[0];

	//remove everything from current table
	document.querySelectorAll('.invBody').forEach(function(a){
		a.remove()
	})
				
	//check for any search attributes available
	var attributes = [];

	// loop through user defined class attributes, if we have any selected, make sure we filter on this as well
	for (var i = 0; i < class_attributes.length; i++){

		//only push if user has requested to filter by something
		if (class_attributes[i].value != "")
			attributes.push({
				type: class_attributes[i].id.substr(7),
				value: class_attributes[i].value
			});
	}

	// filter inventory based on user defined criteria
	var filtered_inventory = inventory.filter(function (object) {
		return (category == null || category == "" || category.toLowerCase() == object.partCategory.toLowerCase()) &&
				(manufacturer == null || manufacturer == "" || manufacturer.toLowerCase() == object.manufacturer.toLowerCase()) &&
				(part == null || part == "" || object.partNumber.toLowerCase().includes(part.toLowerCase()));
	});

	// cycle through each part and add to table
	for (var i = 0; i < filtered_inventory.length; i++){

		// check attributes
		var add = true;

		// cycle through attributes and see if we find any matches
		for (var j = 0; j < attributes.length; j++){
			if (filtered_inventory[i][attributes[j]['type']] != attributes[j]['value'])
				add = false
		}

		if (add){

			// if we pass checks, add to table
			// insert new row and add classname to it
			var row = table.insertRow(-1);
			row.classList.add("invBody");

			// add classes depending on part attributes (preferred, hot, cut_sheets, etc.)
			if (filtered_inventory[i].pref_part == "TRUE" || filtered_inventory[i].hot_part == "TRUE")
				row.classList.add("bold_row");
					
			if (filtered_inventory[i].hot_part == "TRUE")
				hot_tell = "HOT LIST: ";
			else
				hot_tell = "";

			// check for cutsheet
			if (filtered_inventory[i].cut_sheet == null || filtered_inventory[i].cut_sheet.trim() == ""){
				cutsheet_front = "";
				cutsheet_back = "";
			}
			else{
				cutsheet_front = "<a href = '" + filtered_inventory[i].cut_sheet + "' target = '_blank' class = 'cutsheet_link'>";
				cutsheet_back = "</a>";
			}

			// check for statuses
			// add class to row if this is discontinued
			if (filtered_inventory[i].status == "Discontinued"){
				row.classList.add("discontinued_row");
				status_tell = "[Discontinued] ";
			}
			else if (filtered_inventory[i].status == "EOL"){
				row.classList.add("eol_row");
				status_tell = "[EOL] ";
			}
			else
				status_tell = "";
				
			//init counter
			var counter = 0;
				

			// first two only apply to materialEntry
			if (url == "materialEntry"){

				//insert first column, allows user to select part
				var cell = row.insertCell(counter);
				cell.innerHTML = "<button onclick='add_from_catalog(this)'>Add to Cart</button>";
				counter++;
				
				//insert last column, allows user to select quantity
				var cell = row.insertCell(counter);
				cell.innerHTML = "<input id = 'q_" + i + "' class = 'quantity' type = 'Number' value = '0' min = '0'>";
				counter++;

			}
			
			//part category
			var cell = row.insertCell(counter);
			cell.innerHTML = filtered_inventory[i].partCategory;
			counter++;
			
			//part description
			var cell = row.insertCell(counter);
			cell.innerHTML = filtered_inventory[i].partDescription;
			counter++;
			
			//part number
			var cell = row.insertCell(counter);
			cell.innerHTML = status_tell + cutsheet_front + hot_tell + filtered_inventory[i].partNumber + cutsheet_back;
			counter++;
			
			//part oem
			var cell = row.insertCell(counter);
			cell.innerHTML = filtered_inventory[i].manufacturer;
			counter++;
			
			//part uom
			var cell = row.insertCell(counter);
			cell.innerHTML = filtered_inventory[i].uom;
			counter++;
			
			//part cost
			var cell = row.insertCell(counter);
			cell.innerHTML = accounting.formatMoney(filtered_inventory[i].cost);
			counter++;
			
			//part price
			var cell = row.insertCell(counter);
			cell.innerHTML = accounting.formatMoney(filtered_inventory[i].price);
			counter++;						
			
			//OMA stock
			var cell = row.insertCell(counter);
			cell.style.textAlign = "center";
			cell.innerHTML = (parseInt(filtered_inventory[i]['OMA-1']) + parseInt(filtered_inventory[i]['OMA-2']) + parseInt(filtered_inventory[i]['OMA-3']));
			counter++;
			
			//CHA stock
			var cell = row.insertCell(counter);
			cell.style.textAlign = "center";
			cell.innerHTML = (parseInt(filtered_inventory[i]['CHA-1']) + parseInt(filtered_inventory[i]['CHA-3']));
			counter++;

			//if PW-Kit, add +/- icon to show parts inside of kit
			if (filtered_inventory[i].partCategory == "PW-KITS"){
				var cell = row.insertCell(counter);
				cell.style.textAlign = "center";
				cell.innerHTML = "<button onclick = 'show_kit_contents(this)' class = 'show_hide_button'>+</button>";
				counter++;
			}
		}
	}
	
	//show table
	u.eid(table_id).style.display = "block";
}

/**
 * Handles updating subtype drop-down
 * @author Alex Borchers
 * @param {HTMLElement} targ 		<select> element that is changed
 * @param {String}		sub_id  	the ID that we want to update
 * @param {Array}		sub_types  	array of objects (matches general_subtype db table)
 * @return void
 */
function update_sub_type_dropdown(targ, sub_id, sub_types){

	// get select to modify
	var select = u.eid(sub_id);

	// remove previous options
	for (var i = select.length - 1; i > -1; i--){
		select[i].remove();
	}

	// get all sub types related to selected type
	var use_types = sub_types.filter(function (t) {
		return t.projectType == targ.value;
	});

	// create blank options
	var option = document.createElement("option");
	select.appendChild(option);

	// loop through types and add to select
	for (var i = 0; i < use_types.length; i++){

		// create new option and set value
		var option = document.createElement("option");
		option.innerHTML = use_types[i].subType;
		option.value = use_types[i].subType;
		select.appendChild(option);
	}
}

/**
 * Adds the specified number of business days to today's date and returns the resulting date as a string in the format "yyyy-mm-dd".
 * 
 * @param {number} num_business_days - The number of business days to add to today's date. A business day is defined as a weekday (Monday through Friday) that is not a public holiday.
 * @returns {string} The resulting date as a string in the format "yyyy-mm-dd".
 */
function add_business_days_to_date(num_business_days) {
	var date = new Date();
	var day_of_week = date.getDay(); // 0 = Sunday, 6 = Saturday
  
	// Calculate the number of days to add to the date
	var days_to_add = num_business_days;
  
	// Loop through the number of business days to add
	while (days_to_add > 0) {
	  // Add one day to the date
	  date.setDate(date.getDate() + 1);
  
	  // Check if the date is a weekend day (Saturday or Sunday)
	  day_of_week = date.getDay();
	  if (day_of_week >= 6) {
		continue; // skip weekends
	  }
  
	  // Subtract one from the number of business days left to add
	  days_to_add--;
	}
  
	// Format the date as a string in the format "yyyy-mm-dd" for use in an HTML <input type='date'> tag
	var year = date.getFullYear();
	var month = ('0' + (date.getMonth() + 1)).slice(-2);
	var day = ('0' + date.getDate()).slice(-2);
	var formatted_date = year + '-' + month + '-' + day;
  
	// Return the formatted date string
	return formatted_date;
}

/**
 * Handles getting table counts and updating <span> tags in each header
 * @author Alex Borchers
 * @param {array} group_codes (handles all group_codes, related to <span> counter tag)
 * @returns void
 */
function update_table_counts(group_codes){

	// Count for each group
	for (var i = 0; i < group_codes.length; i++){
		var table = u.eid("searchTable" + i);
		var tbody = table.querySelector('tbody');
		u.eid(group_codes[i] + "_count").innerHTML = tbody.rows.length;

		// If 0, hide table
		if (tbody.rows.length == 0){
			// Get subheader related to group, hide table and flip array
			var sub_header = u.eid("sub_header" + i);
			const spans = sub_header.querySelectorAll('span');

			// Loop through each <span> element and check if it has the "my-class" class
			spans.forEach(function(span) {
				if (span.classList.contains('ui-icon-triangle-1-s')){
					span.classList.remove('ui-icon-triangle-1-s');
					span.classList.add('ui-icon-triangle-1-e');	
					u.eid("searchTable" + i).classList.add("hide");
				}
			});
		}
	}
}

/**@author Alex Borchers
 * Handles printing JHA form to pdf
 * @returns boolean (true = success, false = error)
 */
function print_jha(){

	// open mini window with print contents
	var mywindow = window.open('', 'PRINT', 'height=800,width=1200');

	// create custom page title
	var title = u.eid("project_name").value;

	// create page with contents of div written as only HTML
	mywindow.document.write('<html><head><title>' + title + '</title>');
	mywindow.document.write('<link rel="stylesheet" href="stylesheets/element-styles.css">');
	mywindow.document.write('<link rel="stylesheet" href="stylesheets/jha_styles.css">');
	mywindow.document.write('<link rel="stylesheet" href="stylesheets/jha_styles_print.css">');
	mywindow.document.write('</head><body >');
	mywindow.document.write(u.eid("jha_dialog").innerHTML);

	// embed script inside of new window that causes page to auto print and auto close once printed
	mywindow.document.write('<scr'+'ipt type="text/javascript">');	//add string concat for <script> tags to avoid misinterpretation from browser
	
	// loop through all stored values in current JHA form & transfer to new page
	//for (var i = 0; i < jha_key.length; i++){
		//mywindow.document.write('document.getElementById("project_name").value = "' + u.eid("project_name").value + '";');
	//}

	// loop through all input fields & checkboxes & update value
	$('#jha_dialog input').each(function () {
		
		if (this.type == "checkbox")
			mywindow.document.write('document.getElementById("' + this.id + '").checked = ' + u.eid(this.id).checked + ';');
		else
			mywindow.document.write('document.getElementById("' + this.id + '").value = "' + u.eid(this.id).value + '";');

	});

	// loop through all textarea's and update as well
	$('#jha_dialog textarea').each(function () {
		mywindow.document.write('document.getElementById("' + this.id + '").value = "' + u.eid(this.id).value + '";');
	});

	// set window to default open print & close when done
	mywindow.document.write('window.print();');
	mywindow.document.write('setTimeout(window.close, 0);');
	mywindow.document.write('</scr'+'ipt>');						//add string concat for <script> tags to avoid misinterpretation from browser

	// close out body and html
	mywindow.document.write('</body></html>');

	// close/focus necessary for IE >= 10
	mywindow.document.close(); 
	mywindow.focus();

	// return true if successful
	return true;
}

/**
 * Handles validating edit access for service requests
 * @param {Object} request 	The service request object (see fst_grid_service_request db table)
 * @param {Object} user 	The user object (see fst_users db table)
 * @returns {Boolean} 
 */
function validate_edit(request, user){

	// If user is admin, allow edit
	if (user.accessLevel == "Admin")
		return true;

	// If user is a design/estimation team manager, allow edit
	if ((request.group == "Design" || request.group == "Estimation") && (user.role == "Designer/Estimator" && user.manager == "checked"))
		return true;
	
	// If user is a FSE team manager, allow edit
	if (request.group == "FSE" && (user.role == "FSE" && user.manager == "checked"))
		return true;

	// If user is a Ops team manager, allow edit (for all groups)
	if (user.role == "Ops" && user.manager == "checked")
		return true;

	// If user is a COP team manager, allow edit
	if (user.role == "COP" && user.manager == "checked")
		return true;

	// Otherwise, return false
	return false;
}

/**@author Alex Borchers
 * Handles getting google drive ID
 * Google drive link is in the form https://drive.google.com/drive/folders/1gf1BWnku0EmtESxYyBcVVaNctf467nI7
 * where 1gf1BWnku0EmtESxYyBcVVaNctf467nI7 is the id
 * 
 * @returns id
 */
function get_google_drive_id(link){

	// if blank, return empty string
	if (link == "")
		return "";

	// get position of folders/
	var folder_pos = link.indexOf("folders/");

	// return empty string if we don't find it
	if (folder_pos == -1)
		return "";

	// if we pass checks, grab id and return
	var id = link.substr(folder_pos + 8);
	return id;

}

/**
 * Handles showing/hiding tables in dashboard
 * @param {HTMLElement} targ 
 * @param {string} id 
 */
function expand_dashboard_table(targ, id){		

	// Find all <span> elements that are children of the clicked <h3>
	const spans = targ.querySelectorAll('span');

	// Loop through each <span> element and check if it has the "my-class" class
	spans.forEach(function(span) {
		// Expand
		if (span.classList.contains('ui-icon-triangle-1-e')) {
			span.classList.remove('ui-icon-triangle-1-e');
			span.classList.add('ui-icon-triangle-1-s');
			u.eid("searchTable" + id).classList.remove("hide");
		}
		// Collapse
		else if (span.classList.contains('ui-icon-triangle-1-s')){
			span.classList.remove('ui-icon-triangle-1-s');
			span.classList.add('ui-icon-triangle-1-e');	
			u.eid("searchTable" + id).classList.add("hide");
		}
	});
}


/**
 * Handles showing/hiding tables in dashboard
 * @param {HTMLElement} targ 
 * @param {string} id 
 */
/*
function expand_dashboard_table_transition(targ, id){	

	// Find all <span> elements that are children of the clicked <h3>
	const spans = targ.querySelectorAll('span');

	// Loop through each <span> element and check if it has the "my-class" class
	spans.forEach(function(span) {
		// Expand
		if (span.classList.contains('ui-icon-triangle-1-e')) {
			span.classList.remove('ui-icon-triangle-1-e');
			span.classList.add('ui-icon-triangle-1-s');
			u.eid("dash_div" + id).classList.add('expanded');

			// Set height w/ 50px buffer
			var height = u.eid("searchTable" + id).offsetHeight + 50;
			u.eid("dash_div" + id).classList.remove('expanded');
			u.eid("dash_div" + id).style.height = height + "px";
		}
		// Collapse
		else if (span.classList.contains('ui-icon-triangle-1-s')){
			span.classList.remove('ui-icon-triangle-1-s');
			span.classList.add('ui-icon-triangle-1-e');	
			u.eid("dash_div" + id).classList.remove('expanded');
			u.eid("dash_div" + id).style.height = "0px";
		}
	});
}

function set_dashboard_div_heights(){
	var id = 0;
	document.querySelectorAll('.dash_div').forEach(function(obj){
		obj.style.height = obj.offsetHeight + "px";
		u.eid("dash_div" + id).style.height = height + "px";
		id++;
	}
}
*/

/**
 * Handles expanding / collapsing all tables in dashboard
 * @author Alex Borchers
 * @param {boolean} expand (true = expand all)
 */
function expand_all_tables(expand){
	
	// Create setting for display option
	if (expand){
		document.querySelectorAll('.ui-icon-triangle-1-e').forEach(function(obj){
			obj.classList.remove('ui-icon-triangle-1-e');
			obj.classList.add('ui-icon-triangle-1-s');
			
			// Get table id
			var h3 = obj.parentNode;
			var div = h3.parentNode;
			var id = div.id.substr(10);
			u.eid("searchTable" + id).classList.remove("hide");
		})
	}
	else{
		document.querySelectorAll('.ui-icon-triangle-1-s').forEach(function(obj){
			obj.classList.remove('ui-icon-triangle-1-s');
			obj.classList.add('ui-icon-triangle-1-e');

			// Get table id
			var h3 = obj.parentNode;
			var div = h3.parentNode;
			var id = div.id.substr(10);
			u.eid("searchTable" + id).classList.add("hide");
		})
	}
}

/**
 * Handles showing tables with data avaible (used by dashboards)
 * @param {ClassList} tables 
 * @param {ClassList} dropdowns 
 * @param {Array} visible_tables
 */
function show_relevant_tables(tables, dropdowns, visible_tables){
	for (var i = 0; i < tables.length; i++){
		if (visible_tables.includes(tables[i].id)){
			dropdowns[i].classList.remove('ui-icon-triangle-1-e');
			dropdowns[i].classList.add('ui-icon-triangle-1-s');
			u.eid("searchTable" + i).classList.remove("hide");
		}
		else{
			dropdowns[i].classList.remove('ui-icon-triangle-1-s');
			dropdowns[i].classList.add('ui-icon-triangle-1-e');	
			u.eid("searchTable" + i).classList.add("hide");
		}
	}
}

/**
 * Handles updating even/odd table styles look for dashboards
 * @author Alex Borchers
 * @param {HTMLElement} table - The table to update
 */
function update_dashboard_table_styles(table){

	// Set even/odd. Flip at each row
	var cls = "even";
	table.querySelectorAll('tr.table_row').forEach(function(obj){
		if (obj.style.display != "none"){
			obj.classList.remove("even");
			obj.classList.remove("odd");
			obj.classList.add(cls);
			cls = cls == "even" ? "odd" : "even";
		}
	})
}

/**
 * Handles creating spinner for mobile interface
 */
function create_spinner(){
	// Create spinner
	const circle_loader = document.createElement('div');
	circle_loader.classList.add('circle-loader');
	const checkmark = document.createElement('div');
	checkmark.classList.add("checkmark");
	checkmark.classList.add("draw");
	circle_loader.appendChild(checkmark);
	document.body.appendChild(circle_loader);
	
	// Create helper text
	const helper_text = document.createElement('div');
	helper_text.id = "spinner_helper";
	helper_text.innerHTML = "Your request is being processed.";
	document.body.appendChild(helper_text); 
}

//used to flip cursor to waiting and back
function waiting (desc){
	if(desc == "on"){
		$( "html" ).addClass( "waiting_cursor" );
		$( "body" ).addClass( "waiting_cursor" );
		$( "input" ).addClass( "waiting_cursor" );
		$( "button" ).addClass( "waiting_cursor" );
		$( "a" ).addClass( "waiting_cursor" );
		$( "li" ).addClass( "waiting_cursor" );
		$( "ul" ).addClass( "waiting_cursor" );
	}
	else{
		$( "html" ).removeClass( "waiting_cursor" );
		$( "body" ).removeClass( "waiting_cursor" );
		$( "input" ).removeClass( "waiting_cursor" );
		$( "button" ).removeClass( "waiting_cursor" );
		$( "a" ).removeClass( "waiting_cursor" );
		$( "li" ).removeClass( "waiting_cursor" );
		$( "ul" ).removeClass( "waiting_cursor" );
	}
}

/**
 * Redirects the user to href1 or href2 based on the screen width.
 * @author Alex Borchers
 * @param {number} threshold - The screen width threshold in pixels.
 * @param {string} opt - Desktop option (if on, ignore threshold).
 * @param {string} mobile - The URL to redirect to if the screen width is less than or equal to the threshold.
 */
function redirect_by_screen_width(threshold, opt, mobile) {
	
	// Check desktop opt
	if (opt == "on")
		return;

	// Get screenwidth
	var screen_width = window.innerWidth || document.documentElement.clientWidth || document.body.clientWidth;

	// Redirect if under threshold
	if (screen_width < threshold)
		window.location.href = mobile;
}

/* FOLLOWING FUNCTIONS ARE USED FOR NAVIGATION BAR */
//open side navigation bar
function open_nav() {
	document.getElementById("mySidenav").style.width = "250px";
	//document.body.classList.add("side_out");
	var div = document.createElement("div");
	document.body.appendChild(div);
	div.id = "side_out";
}

//close side navgation bar
function close_nav() {
	document.getElementById("mySidenav").style.width = "0";
	document.getElementById("side_out").remove();
}

/* When the user clicks on the button, 
toggle between hiding and showing the dropdown content */
function navbar_dropdown(id) {

	//toggle between previous state
	if (document.getElementById(id).style.display == "block")
		document.getElementById(id).style.display = "none";
	else
		document.getElementById(id).style.display = "block";

	//make sure other IDs are hidden
	//all_ids = ["user_options", "myNotifications"];
	all_ids = ["user_options"];
	for (var i = 0; i < all_ids.length; i++){
		if (!all_ids[i].includes(id))
			document.getElementById(all_ids[i]).style.display = "none";
	}
}

// Close the dropdown if the user clicks outside of it
window.onclick = function(e) {
	if (!e.target.matches('.dropbtn') && !e.target.matches('.user_profile_image')) {
	//if (!e.target.matches('.user_profile_image')) {
		//document.getElementById("myNotifications").style.display = "None";
		document.getElementById("user_options").style.display = "None";
	}
}