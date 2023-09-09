<?php
include_once "config.php";



//database configuration file
$dbHost     = "pw-sql-lab.mysql.database.azure.com";
$dbUsername = "pwadmin@pw-sql-lab";
$dbPassword = "h@rdhat-b0Rax-eas1ly-stony";
$dbName     = "fst_test_db";
$port       = 3306;

//establish connection to sql database
$con = mysqli_init();
mysqli_real_connect($con, $dbHost, $dbUsername, $dbPassword, $dbName, $port);
$mysqli = new mysqli($dbHost, $dbUsername, $dbPassword, $dbName);

$query = "select * from inv_hq,rom_tool_bda_parts where quantity > 2 && cost != 0 limit 10";
$result = mysqli_query($con, $query);

$return_arr = array();

while ($row = mysqli_fetch_array($result)) {
    $id = intval($row['id']);
    $cost = floatval($row['cost']);
    $price = floatval($row['price']);
    $quantity = intval($row['quantity']);
    $description = $row['description'];
    $part_description = $row['part_description'];
    $partNumber = $row['partNumber'];
    $total_cost = $quantity * $cost;

    // Format numbers to 2 decimal places
    $cost = number_format($cost, 3);
    $price = number_format($price, 3);
    $total_cost = number_format($total_cost, 3);


    $return_arr[] = array(
        "id" => $id,
        "cost" => $cost,
        "price" => $price,
        "quantity" => $quantity,
        "description" => $description,
        "part_description" => $part_description,
        "partNumber" => $partNumber,
        "total_cost" => $total_cost
    );
}

// Encoding array in JSON format
$json_response = json_encode($return_arr);

// Send JSON response to AJAX call
echo $json_response;


$con->close();
