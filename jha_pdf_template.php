<?php

/****************
 * 
 * Author: Alex Borchers
 * This file is intended to design the JHA PDF, and will not be used in production
 * 
 *****************/

//get access to session variables
session_start();

//get access to php html renderings
include('phpFunctions_html.php');

//get access to other php functions used throughout many applications
include('phpFunctions.php');

//load in database configuration
require_once 'config.php';

//include constants sheet
include('constants.php');

//used to grab actual link for the current address
$actual_link = "https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

//Save current site so we can return after log in
$_SESSION['returnAddress'] = $actual_link;

//init fstUser array
$fstUser = [];

//Make sure user has privileges
//check session variable first
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

//verify user
sessionCheck($fstUser['accessLevel']);

?>

<!DOCTYPE html>
<html>
<head>
<style>
    
    /** insert styles here **/
    .tabcontent{
      padding: 70px 20px;
    }

</style>
<!-- add any external style sheets here -->
<link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css'> 
<link rel='stylesheet' href='stylesheets/element-styles.css'> 
<link rel="shortcut icon" type="image/x-icon" href="images/P-Logo.png" />
<link href = "stylesheets/jquery-ui-themes-1.13.0/themes/ui-lightness/jquery-ui.css" rel = "stylesheet">
<title>FST TEMPLATE (v<?= $version ?>) - Pierson Wireless</title>
</head>
<body>

<?php

//render header by using create_navigation_bar function (takes two arrays = 1 = name of buttons, 2 = id's of divs to open)
$header_names = ['Tab 1'];   //what will appear on the tabs
$header_ids = ['div1'];                                                       //must match a <div> element inside of body

echo create_navigation_bar($header_names, $header_ids, "", $fstUser);

?>

<div id = 'div1' class = 'tabcontent'>

    <button onclick = 'export_pdf_handler(true, "JHA Form")'>Preview JHA Form</button><br>
    <iframe src="" id = 'attachment' width = '800px' height = '1000px' ></iframe>

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
<script type = "text/javascript" src="javascript/js_helper.js?<?= $version; ?>-1"></script>
<script src = "javascript/accounting.js"></script>

<!--load pdf renderer-->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.3.0-beta.1/pdfmake.min.js" integrity="sha512-G332POpNexhCYGoyPfct/0/K1BZc4vHO5XSzRENRML0evYCaRpAUNxFinoIJCZFJlGGnOWJbtMLgEGRtiCJ0Yw==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.3.0-beta.1/standard-fonts/Times.js" integrity="sha512-KSVIiw2otDZjf/c/0OW7x/4Fy4lM7bRBdR7fQnUVUOMUZJfX/bZNrlkCHonnlwq3UlVc43+Z6Md2HeUGa2eMqw==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.3.0-beta.1/vfs_fonts.min.js" integrity="sha512-6RDwGHTexMgLUqN/M2wHQ5KIR9T3WVbXd7hg0bnT+vs5ssavSnCica4Uw0EJnrErHzQa6LRfItjECPqRt4iZmA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

<script>

    /*const urls = [
        {
            family: 'MyFont',
            style: 'normal',
            url: 'stylesheets/fonts/Titillium Web/titillium-normal.txt'
        },
        {
            family: 'MyFont',
            style: 'italic',
            url: 'stylesheets/fonts/Titillium Web/titillium-italic.txt'
        },
        {
            family: 'MyFont',
            style: 'bold',
            url: 'stylesheets/fonts/Titillium Web/titillium-bold.txt'
        }
    ];

    const promises = urls.map(font => {
        return fetch(font.url)
            .then(response => response.text())
            .then(text => ({ [font.style]: text }));
    });

    Promise.all(promises)
    .then(fonts => {
        // Combine font styles into single font definition
        const fontDefinitions = fonts.reduce((acc, curr, index) => {
        acc[urls[index].family] = curr;
        return acc;
        }, {});

        // Register fonts with pdfmake
        pdfMake.fonts = {
        ...pdfMake.fonts,
        ...fontDefinitions
        };
    })
    .catch(error => {
        console.log('Error fetching font files:', error);
    });*/

    /*fetch('stylesheets/fonts/Titillium Web/titillium-normal.txt')
    .then(response => response.text())
    .then(fontData => {
        // Define the custom font in the pdfmake font dictionary
        pdfMake.fonts = {
            MyFont: {
                normal: 'data:application/font-woff;charset=utf-8;base64,' + fontData                
            }
        };
    });*/

    // Load the font file using a XMLHttpRequest
    var xhr = new XMLHttpRequest();
    xhr.open('GET', 'stylesheets/fonts/Titillium_Web/TITILLIUMWEB-REGULAR.TTF', true);
    xhr.responseType = 'arraybuffer';

    xhr.onload = function () {
        if (xhr.status === 200) {
            // Encode the font file as base64
            var fontData = btoa(String.fromCharCode.apply(null, new Uint8Array(xhr.response)));

            // Create the vfs object if it doesn't exist
            if (!pdfMake.vfs) {
                pdfMake.vfs = {};
            }

            // Add the font to the virtual file system
            pdfMake.virtualfs.storage['TITILLIUMWEB-REGULAR.TTF'] = fontData;

            // Define the font family
            pdfMake.fonts = {
                MyFont: {
                    normal: 'TITILLIUMWEB-REGULAR.TTF'
                }
            };

            // Set the default font
            pdfMake.font = 'MyFont';
        }
    };

    xhr.send();

    console.log(pdfMake);

    //global used to handle print preview
    var print_preview = false;
    
    function export_pdf_handler(dec){
        print_preview = dec;
        getImageFromUrl('images/PW_Std Logo.png', render_jha_form);
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
    
    //global that holds current pq_detail index
    var pq_det_index = -1;
    
    //handles rendering PDF for pick-ticket AND packing slip
    //passes logo as imgData and WS/PW as type
    function render_jha_form(imgData){
        
        //used to generate image base 64 url
        var c = document.createElement('canvas');
        
        //grab logo & resize
        var img = u.eid('pw_logo');
        var logo_width = 100;
        var column_width = 400;        
        c.height = img.naturalHeight;
        c.width = img.naturalWidth;
        var ctx = c.getContext('2d');
        ctx.drawImage(img, 0, 0, c.width, c.height);
        
        //hold image object used in pdf
        var base64String = c.toDataURL();
        
        //define column width
        var left_width = 75;
        
        //get today's date
        //let today = new Date().toLocaleDateString()
        
        //generate document based on criteria
        var docDefinition = {
            pageSize: 'A4',
            pageOrientation: 'portrait',
            pageMargins: [40, 30, 40, 60], //[horizontal, vertical] or [left, top, right, bottom]
            defaultStyle: {
                font: 'MyFont'	
            },
            /*header: [
                {
                    text: shop + " Pick Ticket", 
                    alignment: 'left', 
                    style: 'header_style'
                }
            ],*/
            footer: [
                {
                    text: '\n\nPierson Wireless Corp.\n\nThis JHA must be completed for any new project and updated when the job tasks or equipment changes.\n\nSend the completed JHA to Safety@piersonwireless.com', 
                    alignment: 'center', 
                    style: 'footer_style'
                }
            ],
            content:[
                {
                    image: base64String,
                    width: logo_width, 
                    style: 'header_logo',
                    alignment: 'right',
                    lineHeight: 6
                },
                // {
                //     //black line that runs from top of vendor header
                //     canvas:
                //     [
                //         {
                //             type: 'line',
                //             x1: 150, y1: 0,
                //             x2: 515, y2: 0, 
                //             lineWidth: 2
                //         }
                //     ]
                // },
                render_pdf_table('single_block', 'JOB HAZARD ANALYSIS (JHA) FORM:'),
                {
                    text: 'Project Name / Job Code:', 
                    alignment: 'left',
                    style: 'sub_header'
                },
                {
                    columns: [
                        {
                            text: 'Title of Job or Task: \nPerson Completing this JHA: ', 
                            alignment: 'left',
                            style: 'sub_header',
                            width: 170
                        },
                        {
                            text: 'BA Test Project\nAlex Borchers', 
                            alignment: 'left',
                            style: 'sub_header_answer', 
                            width: 120
                        }, 
                        {
                            text: "", 
                            width: 10
                        },
                        {
                            text: "Revision: \nDate Completed: ", 
                            alignment: 'left',
                            style: 'sub_header',
                            width: 100
                        },
                        {
                            text: '1\n1/10/2023', 
                            alignment: 'left',
                            style: 'sub_header_answer', 
                            width: 100
                        }, 
                    ],
                },
                render_pdf_table('ppe'),
                render_pdf_table('hazard_id')
            ], 
            styles: {
                header_style: {
                    fontSize: 12, 
                    color: 'gray',
                    margin: [40, 15, 0, 0] //[left, top, right, bottom]
                }, 
                header_sub: {
                    fontSize: 14, 
                    margin: [0, 0, 0, 10] //[left, top, right, bottom]
                }, 
                header_logo: {
                    margin: [0, 0, 0, 10] //[left, top, right, bottom]
                }, 
                header_main: {
                    fontSize: 10.5, 
                    margin: [0, 20, 0, 0], //[left, top, right, bottom]
                    lineHeight: 1.2
                }, 
                header_pick: {
                    fontSize: 28, 
                    margin: [40, 0, 40, 10], //[left, top, right, bottom]
                },
                header_date: {
                    fontSize: 24, 
                    color: 'red',
                    margin: [40, 0, 0, 10] //[left, top, right, bottom]
                }, 
                body_text: {
                    fontSize: 12, 
                    margin: [0, 0, 0, 0], //[left, top, right, bottom]
                    lineHeight: 1.2,
                    alignment: 'justify'
                },
                sub_header: {
                    fontSize: 12, 
                    margin: [0, 0, 0, 0], //[left, top, right, bottom]
                    lineHeight: 1.4,
                    alignment: 'justify',
                },
                sub_header_answer: {
                    fontSize: 11, 
                    margin: [0, 0, 0, 0], //[left, top, right, bottom]
                    lineHeight: 1.4,
                    alignment: 'justify'
                },
                red_header: {
                    fontSize: 12, 
                    margin: [0, 0, 0, 0], //[left, top, right, bottom]
                    lineHeight: 1.2,
                    alignment: 'justify',
                    color: 'red'
                },
                table_header_margin: {
                    fontSize: 10, 
                    fillColor: '#114B95', 
                    color: 'white',
                    alignment: 'center',
                    margin: [0, 5, 0, 5] //[left, top, right, bottom]
                    
                },
                table_header: {
                    fontSize: 11, 
                    fillColor: '#114B95', 
                    color: 'white',
                    alignment: 'center'						
                },
                table_body: {
                    fontSize: 10, 
                    margin: [0, 30, 0, 10],  //[left, top, right, bottom]
                    unbreakable: true,
                    lineHeight: 1.2
                }, 
                single_block: {
                    fontSize: 12, 
                    margin: [0, 10, 0, 10], //[left, top, right, bottom]
                    unbreakable: true,
                    lineHeight: 1.2
                }, 
                total_row: {
                    fontSize: 9.5, 
                }, 
                footer_style: {
                    fontSize: 9, 
                    margin:[40, -10, 40, 0] //[left, top, right, bottom]
                }, 
                italics_row: {
                    fontSize: 9
                }
            }
        };			
        
        //********PDF PRINT PREVIEW
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
        //*******SAVE TO SERVER & EXECUTE
        else{
            
            //if not print preview, save copy to server for email
            pdfMake.createPdf(docDefinition).getBuffer().then(function(buffer) {

                var blob = new Blob([buffer]);

                var reader = new FileReader();
                
                // this function is triggered once a call to readAsDataURL returns
                reader.onload = function(event) {
                    var fd = new FormData();
                    fd.append('fname', 'temp.pdf');
                    fd.append('data', event.target.result);
                    fd.append("mo_id", allocations_mo[current_index].mo_id);
                    fd.append('tell', 'temp_pdf')

                    $.ajax({
                        type: 'POST',
                        url: 'src.php', // Change to PHP filename
                        data: fd,
                        processData: false,
                        contentType: false
                    }).done(function(data) {
                        
                        //execute close & submit function (send MO) once finished
                        z.close_and_submit();

                    });
                };

                // trigger the read from the reader...
                reader.readAsDataURL(blob);
            });
        }
    }

    //builds table based on type of table
    function render_pdf_table(type, text = null){
        
        //just send back formatting for single block
        if (type == "single_block"){
            
            //return format AND body 
            return {
                style: 'single_block',
                table: {
                    widths: ['*'],
                    heights: [20],
                    headerRows: 1,
                    body: render_pdf_body(type, text)
                }
            };
        }
        //PPE table
        else if (type == "ppe"){
            
            //return format AND body 
            return {
                style: 'single_block',
                table: {
                    widths: ['*', '*', '*', '*'],
                    headerRows: 1,
                    body: render_pdf_body(type, text)
                }
            };
        }
        //PPE table
        else if (type == "hazard_id"){
            
            //return format AND body 
            return {
                style: 'single_block',
                table: {
                    widths: ['*', '*', '*', '*', '*', '*'],
                    headerRows: 1,
                    body: render_pdf_body(type, text)
                }
            };
        }
        //PPE table
        else if (type == "basic_job_steps"){
        
            //return format AND body 
            return {
                style: 'single_block',
                table: {
                    widths: [120, 120, '*'],
                    headerRows: 1,
                    body: render_pdf_body(type, text)
                }
            };
        }        		
    }

    //renders PDF table headers and body if necessary
    function render_pdf_body(type, text = null){
    
        //init body to send back
        var body = [];
        
        //just send back formatting for single block
        if (type == "single_block"){
            
            //pass table headers as array
            var headers = createHeaders([
                text
            ], 'table_header_margin');

            //add headers to body
            body.push(headers);
            
        }
        else if (type == "ppe"){
            body.push([{text: 'Recommended Personal Protective Equipment (PPE)\nCheck all personal protective equipment required to safely perform the job or task', colSpan: 4, alignment: 'center', style: 'table_header'}, {}, {}, {}]);
            body.push([
                { text: 'Test.', alignment: 'left'}, 
                { text: 'Test.', alignment: 'left'}, 
                { text: 'Test.', alignment: 'left'}, 
                { text: 'Test.', alignment: 'left'}, 
            ]);
        }
        else if (type == "hazard_id"){
            body.push([{text: 'Hazard Identification – Check All That Apply', colSpan: 6, alignment: 'center', style: 'table_header'}, {}, {}, {}]);
            body.push([
                { text: 'Test.', alignment: 'left'}, 
                { text: 'Test.', alignment: 'left'}, 
                { text: 'Test.', alignment: 'left'}, 
                { text: 'Test.', alignment: 'left'}, 
                { text: 'Test.', alignment: 'left'}, 
                { text: 'Test.', alignment: 'left'}, 
            ]);
        }
        
        //return our table
        return body;
        
    }
    
    //global for sequence count
    var seq_count = 1;
    
    //create table headers
    function createHeaders(keys, style) {
        var result = [];
        for (var i = 0; i < keys.length; i += 1) {
            result.push({
                text: keys[i],
                style: style
                //prompt: keys[i],
                //width: size[i],
                //align: "center",
                //padding: 0
            });
        }
        return result;
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