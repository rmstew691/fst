<?php
// Handle CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');


// Handle the AJAX request and send the response
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Retrieve the data from the MySQL table (you need to set up your MySQL connection)
    $connection = new mysqli('pw-sql-lab.mysql.database.azure.com', 'pwadmin@pw-sql-lab', 'h@rdhat-b0Rax-eas1ly-stony', 'fst_test_db', 3306);
    if ($connection->connect_error) {
        die('Connection failed: ' . $connection->connect_error);
    }

    $query = "SELECT street,rt_customer,rt_address,rt_pocname,rt_pocnumber data FROM fst_grid,fst_locations";


    // $query = "SELECT customer data FROM fst_grid limit 1";
    // $query = "SELECT  locations_street data FROM fst_locations";
    $result = $connection->query($query);

    $data = array();
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    }

    // Send the data as the AJAX response
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}



// Handle the AJAX request and send the response
// if ($_SERVER['REQUEST_METHOD'] === 'GET') {
//     // Retrieve the search parameter from the query string
//     $customer = $_GET['customer'];

//     // Retrieve the data from the MySQL table (you need to set up your MySQL connection)
//     $connection = new mysqli('pw-sql-lab.mysql.database.azure.com', 'pwadmin@pw-sql-lab', 'h@rdhat-b0Rax-eas1ly-stony', 'fst_test_db', 3306);
//     if ($connection->connect_error) {
//         die('Connection failed: ' . $connection->connect_error);
//     }

//     // Prepare and execute the query
//     $statement = $connection->prepare('SELECT customer FROM fst_grid WHERE customer = ?');
//     $statement->bind_param('s', $customer);
//     $statement->execute();

//     // Retrieve the result
//     $result = $statement->get_result();
//     $data = $result->fetch_assoc();

//     // Send the data as the AJAX response
//     header('Content-Type: application/json');
//     echo json_encode($data);
//     exit;
// }
