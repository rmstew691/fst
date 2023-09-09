<?php

// Database configuration
$dbHost = "pw-sql-lab.mysql.database.azure.com";
$dbUsername = "pwadmin@pw-sql-lab";
$dbPassword = "h@rdhat-b0Rax-eas1ly-stony";
$dbName = "fst_test_db";
$port = 3306;

// Establish database connection
$con = mysqli_connect($dbHost, $dbUsername, $dbPassword, $dbName, $port);
