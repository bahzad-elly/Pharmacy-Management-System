<?php
// db_connect.php

$host = 'localhost';
$db_name = 'pharmacy_db';
$username = 'root';  
$password = '';     
$dsn = "mysql:host=" . $host . ";dbname=" . $db_name . ";charset=utf8mb4";

 $options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,  
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,        
    PDO::ATTR_EMULATE_PREPARES   => false,                  
];

try {
 
    $pdo = new PDO($dsn, $username, $password, $options);
    
    
} catch (PDOException $e) {
    error_log("Connection failed: " . $e->getMessage());
    die("A database error occurred. Please contact the system administrator.");
}
?>