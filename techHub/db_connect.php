<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
$serverName = $_SERVER['SERVER_NAME'] ?? 'localhost';


if ($serverName === 'localhost' || $serverName === '127.0.0.1') {
    $db_host = 'localhost';
    $db_user = 'root';
    $db_pass = '';
    $db_name = 'techHub';
} else {
    $db_host = 'localhost';
    $db_user = 'u801377270_techhub_db';
    $db_pass = 'Techhub@2025';
    $db_name = 'u801377270_techhub_db';
}


$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);


if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
