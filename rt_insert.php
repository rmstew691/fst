<?php
// include "config.php";

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



// Retrieve the input data
$rt_customer = isset($_POST['customer']) ? $_POST['customer'] : "";


// Construct the SQL INSERT statement
$query = "INSERT INTO rt_fst_grid (`customer`) VALUES ('$rt_customer')";
$result = mysqli_query($con, $query);

// Check if the insertion was successful
if ($result) {
    // Retrieve the inserted data
    $selectQuery = "SELECT customer FROM rt_fst_grid WHERE customer = '$rt_customer'";
    $selectResult = mysqli_query($con, $selectQuery);

    // Create an array to store the fetched data
    $data = array();

    // Fetch the data and add it to the array
    while ($row = mysqli_fetch_assoc($selectResult)) {
        $data[] = $row;
    }

    // Send the response as JSON
    echo json_encode($data);
} else {
    // Insertion failed
    echo json_encode(array('error' => 'Failed to insert data into the database'));
}

// Close the database connection
mysqli_close($con);
