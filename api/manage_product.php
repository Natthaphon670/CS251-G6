<?php
session_start();
require '../db.php'; 

header('Content-Type: application/json; charset=utf-8');

// 1. เช็กสิทธิ์การเข้าถึง
if (!isset($_SESSION['TenantID']) || $_SESSION['Role'] !== 'Tenant') {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit();
}

// 2. รับข้อมูลแบบ POST JSON (ที่ส่งมาจาก JavaScript)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // อ่านข้อมูล JSON ที่ส่งมา
    $data = json_decode(file_get_contents("php://input"), true);
    
    $pid = $data['pid'] ?? '';
    $pname = $data['pname'] ?? '';
    $sid = $data['sid'] ?? '';
    $price = $data['price'] ?? 0;

    // เช็กว่ากรอกครบไหม
    if(empty($pid) || empty($pname) || empty($sid)) {
        echo json_encode(["status" => "error", "message" => "กรุณากรอกข้อมูลให้ครบถ้วน"]);
        exit();
    }

    try {
        $conn->beginTransaction();

        // เพิ่มข้อมูลลงตาราง Product
        $sql1 = "INSERT INTO Product (ProductID, ProductName, ProductPrice) VALUES (:pid, :pname, :price)";
        $stmt1 = $conn->prepare($sql1);
        $stmt1->execute([':pid' => $pid, ':pname' => $pname, ':price' => $price]);

        // เพิ่มข้อมูลลงตาราง Supply (เชื่อม Product กับ Supplier)
        $sql2 = "INSERT INTO Supply (ProductID, SupplierID) VALUES (:pid, :sid)";
        $stmt2 = $conn->prepare($sql2);
        $stmt2->execute([':pid' => $pid, ':sid' => $sid]);

        $conn->commit();
        echo json_encode(["status" => "success", "message" => "บันทึกข้อมูลสินค้าสำเร็จ!"]);

    } catch (Exception $e) {
        $conn->rollBack();
        $errorMsg = $e->getMessage();
        
        // ดัก Error กรณีใส่รหัส Supplier มั่ว (Foreign Key Fails)
        if (strpos($errorMsg, '1452') !== false || strpos($errorMsg, 'Foreign key constraint') !== false) {
            $errorMsg = "รหัส Supplier (SID) ไม่มีในระบบ กรุณาตรวจสอบอีกครั้ง";
        } else if (strpos($errorMsg, '1062') !== false || strpos($errorMsg, 'Duplicate entry') !== false) {
            $errorMsg = "รหัสสินค้านี้ (PID) มีอยู่ในระบบแล้ว";
        }
        
        echo json_encode(["status" => "error", "message" => $errorMsg]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Invalid Request Method"]);
}
?>