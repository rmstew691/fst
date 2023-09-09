// JavaScript Document
//handles rendering PDF for pick-ticket AND packing slip
//passes logo as imgData and WS/PW as type
function render_cop_bom_pdf(bom, grid, print_preview, file_name = "Test.pdf"){
	
	// Generate header
	var c = document.createElement('canvas');
	var img = u.eid('pw_logo');
	var logo_width = 125;
	c.height = img.naturalHeight;
	c.width = img.naturalWidth;
	var ctx = c.getContext('2d');
	ctx.drawImage(img, 0, 0, c.width, c.height);
	var img_string = c.toDataURL();

	//will hold header info 
	var header_left = ""; 
			
	// Set project streeet (trim if no address 2)
	var project_street = grid['address'] + " " + grid['address2'];
	project_street.trim();
	
	//add info to header
	header_left += grid['location_name'] + " - " + grid['phaseName'] + "\n"; 
	header_left += project_street + "\n" 
	header_left += grid['city'] + ", " + grid['state'] + " " + grid['zip'] + "\n";
	
	//check if cust_id is null
	if (grid['customer_id'] != null && grid['customer_id'] != "")
		header_left += "Customer PN: " + grid['customer_id'] + "\n"; 
	
	//general customer info
	header_left += "\n" + grid['customer'] + "\n"; 
	
	//add to header
	header_left += grid['customer_pm'] + "\n";
	
	//check phone and email
	if (grid['customer_pm_phone'] != null && grid['customer_pm_phone'] != "" && grid['customer_pm_phone'].length > 5)
		header_left += grid['customer_pm_phone'] + "\n"; 
	if(grid['customer_pm_email'] != null && grid['customer_pm_email'] != "")
		header_left += grid['customer_pm_email'] + "\n"; 
	
	//create the right side of the header
	var header_right = "";
	
	//logic for quote/revision number until we're fully integrated
	var quote_revision = "", 
		vp_num = "";
	
	//check length of quotenumber (if greater than 8, new, if less, than old)
	if (grid['quoteNumber'].length > 8){
		vp_num = grid['quoteNumber'].substr(0, grid['quoteNumber'].length - 4);
		quote_revision = grid['quoteNumber'].substr(grid['quoteNumber'].length - 3);
	}
	else{
		vp_num = grid['quoteNumber'].substr(0, grid['quoteNumber'].indexOf("v")),
		quote_revision = grid['quoteNumber'].substr(grid['quoteNumber'].indexOf("v") + 1);
	}

	header_right+= vp_num + "\n"; 
	header_right+= quote_revision + "\n\n"; 
	header_right+= format_date(grid['sub_date']) + "\n"; 
	header_right+= format_date(grid['exp_date']) + "\n"; 

	// Set headers & keys for render_pdf_table
	var headers = ["Description", "Manufacturer", "Part #", "Quantity"];
	var keys = ["description", "manufacturer", "partNumber", "quantity"];
	
	//generate document based on criteria
	var docDefinition = {
		pageSize: 'A4',
		pageOrientation: 'portrait',
		pageMargins: [40, 30, 40, 60], //[horizontal, vertical] or [left, top, right, bottom]
		defaultStyle: {
			font: 'Times'	
		},
		content:[
			{
				columns: [
					{
						image: img_string,
						width: logo_width, 
						style: 'header_logo',
						lineHeight: 6
					}
				],
			},
			{
				columns: [
					{
						width: 375,
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
				text: 'Provided By Pierson Wireless', 
				alignment: 'left',
				style: 'header_style'
			},
			render_pdf_table(headers, keys, bom),
		], 
		styles: {
			header_logo: {
				margin: [-16, -17, 0, 10] //[left, top, right, bottom]
			}, 
			header_main: {
				fontSize: 9, 
				margin: [0, 0, 0, 15],	// margin: [left, top, right, bottom]
				lineHeight: 1.2
			}, 
			header_style: {
				fontSize: 9, 
				bold: true,
				margin: [0, 0, 0, 0] 	//[left, top, right, bottom]
			}, 
			table_header: {
				fontSize: 9, 
				fillColor: '#114B95', 
				color: 'white',
				bold: true,
				alignment: 'left'						
			},
			table_body: {
				fontSize: 8, 
				margin: [0, 5, 0, 10],	//[left, top, right, bottom]
				unbreakable: true,
				lineHeight: 1.2
			}
		}
	};			
	
	// PDF PRINT PREVIEW
	if (print_preview){
		
		pdfMake.createPdf(docDefinition).getDataUrl().then((dataUrl) => {
			//set src to dataURL
			u.eid("attachment").src = dataUrl;
		}, err => {
			console.error(err);
		});

		pdfMake.createPdf(docDefinition).getDataUrl();
		
		//show div holding this
		u.eid("attachment").style.display = "block";
		
	}
	// DOWNLOAD
	// SAVE TO SERVER & EXECUTE
	else{
		
		//download
		pdfMake.createPdf(docDefinition).download(file_name);
	}
}

//builds table based on type of table
function render_pdf_table(header, key, bom){
	
	// Add styling & table width/heights
	return {
		style: 'table_body',
		table: {
			widths: ['*', 100, 100, 50],
			headerRows: 1,
			dontBreakRows: true,
			body: render_pdf_body(header, key, bom)
		}
	};	
}

// renders PDF table headers and body if necessary
function render_pdf_body(header, key, bom){

	//init body to send back
	var body = [];

	//pass table headers as array
	var headers = createHeaders(header, 'table_header');

	//add headers to body
	body.push(headers);

	// Loop through BOM and add text to body
	for (var i = 0; i < bom.length; i++){
		var row = [];
		for (var j = 0; j < key.length; j++){
			row.push({
				text: bom[i][key[j]],
				alignment: 'left'
			})
		}
		body.push(row);
		console.log(row);
	}

	console.log(body);

	//return our table body
	return body;
	
}

//create table headers
function createHeaders(header, style) {
	var result = [];
	for (var i = 0; i < header.length; i += 1) {
		result.push({
			text: header[i],
			style: style
			//prompt: header[i],
			//width: size[i],
			//align: "center",
			//padding: 0
		});
	}
	return result;
}
