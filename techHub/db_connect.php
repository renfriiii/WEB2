<?php
$serverName = $_SERVER['SERVER_NAME'] ?? 'localhost';


if ($serverName === 'localhost' || $serverName === '127.0.0.1') {
    $db_host = 'localhost';
    $db_user = 'root'; 
    $db_pass = '';     
    $db_name = 'techHub'; 
} else {
    
}






$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);


if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
