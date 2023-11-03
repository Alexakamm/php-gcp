<?php

// Database configuration
$username = 'root';                        // or your username
$password = 'Database2023';                // your password
$host = 'dreamhoops:us-east4:friendsdb';  // this is actually not needed for unix_socket connections
$dbname = 'dreamhoops';                   // your database name
$dsn = "mysql:unix_socket=/cloudsql/dreamhoops:us-east4:friendsdb;dbname=dreamhoops";

try {
    // Create a PDO instance (connect to the database)
    $pdo = new PDO($dsn, $username, $password);
} catch (PDOException $e) {
    // If there is an error connecting to the database
    die("Could not connect to the database: " . $e->getMessage());
}

