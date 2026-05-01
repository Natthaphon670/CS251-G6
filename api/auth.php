<?php
ob_start(); // เริ่มต้นการเก็บ Output ไว้ใน Buffer
session_start();
require_once 'db.php'; // ตรวจสอบว่าไฟล์ชื่อ db.php และอยู่ในโฟลเดอร์ api/

header('Content-Type: application/json; charset=utf-8');

function jsonOut($code, $message, $data = []) {
    ob_clean(); // ล้างข้อความ Error หรือขยะ HTML ทั้งหมดก่อนหน้านี้
    echo json_encode(['status' => $code, 'message' => $message, 'data' => $data]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$user = trim($input['username'] ?? '');
$pass = trim($input['password'] ?? '');

try {
    // ใช้งาน $conn จากไฟล์ db.php
    $stmt = $conn->prepare("SELECT u.*, t.TenantID, t.TenantName FROM UserAccount u LEFT JOIN Tenant t ON u.AccountID = t.AccountID WHERE u.Username = ? LIMIT 1");
    $stmt->execute([$user]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$userData || ($pass !== $userData['Password'] && !password_verify($pass, $userData['Password']))) {
        jsonOut(401, "Username หรือ Password ไม่ถูกต้อง");
    }

    // เก็บค่าลง Session
    $_SESSION['isLoggedIn'] = true;
    $_SESSION['tenant_id'] = $userData['TenantID'];
    $_SESSION['role'] = $userData['Role'];

    jsonOut(200, "Success", [
        "account_id" => $userData['AccountID'],
        "username" => $userData['Username'],
        "role" => $userData['Role'],
        "tenant_id" => $userData['TenantID'],
        "tenant_name" => $userData['TenantName']
    ]);

} catch (Exception $e) {
    jsonOut(500, "Database Error: " . $e->getMessage());
}