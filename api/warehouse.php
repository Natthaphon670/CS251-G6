<?php
session_start();
require '../db.php'; 

header('Content-Type: application/json; charset=utf-8');

// 1. เช็กสิทธิ์
if (!isset($_SESSION['TenantID']) || $_SESSION['Role'] !== 'Tenant') {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit();
}

$tenant_id = $_SESSION['TenantID'];

// 2. กรณีมีการยิงคำสั่งเพื่อ "เพิ่มสินค้า" (POST)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $data = json_decode(file_get_contents("php://input"), true);
    $target_warehouse = $data['warehouse_id'] ?? '';
    $add_amount = intval($data['add_amount'] ?? 0);

    if ($add_amount > 0 && !empty($target_warehouse)) {
        try {
            $sql_add = "UPDATE Warehouse SET WarehouseQuantity = WarehouseQuantity + :add_val, LastUpdate = NOW() WHERE WarehouseID = :wid";
            $stmt_add = $conn->prepare($sql_add);
            $stmt_add->execute([
                ':add_val' => $add_amount, 
                ':wid' => $target_warehouse
            ]);
            
            echo json_encode(["status" => "success", "message" => "อัปเดตจำนวนสินค้าสำเร็จ"]);
        } catch(PDOException $e) {
            echo json_encode(["status" => "error", "message" => $e->getMessage()]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "จำนวนไม่ถูกต้อง หรือไม่พบรหัสคลังสินค้า"]);
    }
    exit(); // จบการทำงานของ POST แค่ตรงนี้
}

// 3. กรณีดึงข้อมูลตารางสินค้ามาแสดง (GET)
try {
    $sql_inventory = "SELECT p.ProductID, p.ProductName, w.WarehouseID, w.WarehouseQuantity 
                      FROM Product p
                      JOIN Store st ON p.ProductID = st.ProductID
                      JOIN Warehouse w ON st.WarehouseID = w.WarehouseID
                      JOIN Sale s ON p.ProductID = s.ProductID
                      WHERE s.TenantID = :tenant_id
                      GROUP BY p.ProductID";
    $stmt_inv = $conn->prepare($sql_inventory);
    $stmt_inv->execute([':tenant_id' => $tenant_id]);
    $inventory = $stmt_inv->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(["status" => "success", "data" => $inventory]);
} catch(PDOException $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>