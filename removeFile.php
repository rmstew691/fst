<?php

if (isset($_POST['targ'])){
	
	$excel = $_POST['targ'];
	
	//Step 5: Force Download to user
	// header('Content-Description: File Transfer');
	// header('Content-Type: application/octet-stream');
	// header('Content-Disposition: attachment; filename="'.basename($excel).'"');
	// header('Expires: 0');
	// header('Cache-Control: must-revalidate');
	// header('Pragma: public');
	// header('Content-Length: ' . filesize($excel));
	// flush(); // Flush system output buffer
	// readfile($excel);
	
	unlink($_POST['targ']);
	
}
