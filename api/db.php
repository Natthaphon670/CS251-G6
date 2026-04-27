<?php
$host = 'localhost';
$dbname = 'DepartmentStoreDB';
$username = 'root'; // หรือ username ที่คุณใช้ใน XAMPP
$password = ''; // หรือ password ที่คุณใช้ใน XAMPP

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>