<?php
include_once "config.php";

$dbHost     = "pw-sql-lab.mysql.database.azure.com";
$dbUsername = "pwadmin@pw-sql-lab";
$dbPassword = "h@rdhat-b0Rax-eas1ly-stony";
$dbName     = "fst_test_db";
$port       = 3306;

$query = "select * from inv_hq,rom_tool_bda_parts where partNumber = part_number limit 10";

$result = mysqli_query($con, $query);


$return_arr = array();

$total_cost = 0;


while ($row = mysqli_fetch_array($result)) {
    if ($row['price'] == null || $row['price'] == "undefined") {
        $row['price'] = floatval(0);
    }
    if ($row['cost'] == null || $row['cost'] == "undefined") {
        $row['cost'] = number_format(0, 2);
    }
    if ($row['quantity'] == null || $row['quantity'] == "undefined") {
        $row['quantity'] = 1.00;
    }
    if ($row['part_description'] == null || $row['part_description'] == "undefined") {
        $row['part_description'] = "N/A";
    }
    if ($row['total_cost'] == null || $row['total_cost'] == "undefined" || $row['total_cost'] == "NAN") {
        $row['total_cost'] = floatval("444");
    }

    array_push($return_arr, $row);

    $id = $row['id'];
    $quantity = $row['quantity'];
    $part_description = $row['part_description'];
    $cost = $row['cost'];
    //$total_cost = $row['total_cost'];
    $price = $row['price'];
}
$return_arr[] = array(
    "id" => $id,
    "quantity" => $quantity,
    "part_description" => $part_description,
    "cost" => $cost,
    "total_cost" => $total_cost,
    "price" => $price
);

// Encoding array in JSON format
echo json_encode($return_arr);


function get_total_all_records()
{
}


$con->close();
