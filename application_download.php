<?php

$export_data = unserialize($_POST['export_data']);

$pName = $_POST['dllocation_name'];
$qNum = $_POST['dlQuoteNumber'];
$vpNum = $_POST['dlVPNum'];
$tell = $_POST['tell'];

if ($tell == "BOM"){
	$filename = "BOM - " . $pName . " - VP#" . $vpNum . " - Q#" . $qNum . " - (" . date("m-d-Y") . ").csv";
	$export_data = unserialize($_POST['export_data']);
}
else{
	$filename = "CSV VP#" . $vpNum . ".csv";
	
	if ($tell == "fst_csv"){
		$export_data = unserialize($_POST['export_data']);
	}
	else if($tell == "bom_csv"){
		$export_data = unserialize($_POST['bom_data']);
	}
	else if($tell == "init_csv"){
		$export_data = unserialize($_POST['init_data']);
	}
	
}


//File creation
$file = fopen($filename, "w");

foreach($export_data as $line){
	fputcsv($file, $line);
}

fclose($file);

// download
header("Content-Description: File Transfer");
header("Content-Disposition: attachment; filename=".$filename);
header("Content-Type: application/csv; "); 

readfile($filename);

// deleting file
unlink($filename);
exit();


?>